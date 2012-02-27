import os
import sys
import time
import calendar
import logging
import logging.config
import shutil
import random
import string
import json
import telnetlib
import math
from threading import Thread
from subprocess import Popen, PIPE
from datetime import datetime
from datetime import timedelta
import filecmp

# For RabbitMQ
from kombu.connection import BrokerConnection
from kombu.messaging import Exchange, Queue, Consumer, Producer
from kombu.exceptions import MessageStateError
from kombu.simple import SimpleQueue

from api_clients import api_client

from configobj import ConfigObj

# configure logging
logging.config.fileConfig("logging.cfg")

# loading config file
try:
    config = ConfigObj('/etc/airtime/pypo.cfg')
    LS_HOST = config['ls_host']
    LS_PORT = config['ls_port']
    POLL_INTERVAL = int(config['poll_interval'])

except Exception, e:
    logger = logging.getLogger()
    logger.error('Error loading config file: %s', e)
    sys.exit()

class PypoFetch(Thread):
    def __init__(self, q):
        Thread.__init__(self)
        self.api_client = api_client.api_client_factory(config)
        
        self.logger = logging.getLogger();
        
        self.cache_dir = os.path.join(config["cache_dir"], "scheduler")
        self.logger.info("Creating cache directory at %s", self.cache_dir)
        
        self.queue = q
        self.schedule_data = []
        self.logger.info("PypoFetch: init complete")

    def init_rabbit_mq(self):
        self.logger.info("Initializing RabbitMQ stuff")
        try:
            schedule_exchange = Exchange("airtime-pypo", "direct", durable=True, auto_delete=True)
            schedule_queue = Queue("pypo-fetch", exchange=schedule_exchange, key="foo")
            connection = BrokerConnection(config["rabbitmq_host"], config["rabbitmq_user"], config["rabbitmq_password"], config["rabbitmq_vhost"])
            channel = connection.channel()
            self.simple_queue = SimpleQueue(channel, schedule_queue)
        except Exception, e:
            self.logger.error(e)
            return False
            
        return True
    
    """
    Handle a message from RabbitMQ, put it into our yucky global var.
    Hopefully there is a better way to do this.
    """
    def handle_message(self, message):
        try:        
            self.logger.info("Received event from RabbitMQ: %s" % message)
            
            m =  json.loads(message)
            command = m['event_type']
            self.logger.info("Handling command: " + command)
        
            if command == 'update_schedule':
                self.schedule_data  = m['schedule']
                self.process_schedule(self.schedule_data, False)
            elif command == 'update_stream_setting':
                self.logger.info("Updating stream setting...")
                self.regenerateLiquidsoapConf(m['setting'])
            elif command == 'update_stream_format':
                self.logger.info("Updating stream format...")
                self.update_liquidsoap_stream_format(m['stream_format'])
            elif command == 'update_station_name':
                self.logger.info("Updating station name...")
                self.update_liquidsoap_station_name(m['station_name'])
            elif command == 'cancel_current_show':
                self.logger.info("Cancel current show command received...")
                self.stop_current_show()
        except Exception, e:
            self.logger.error("Exception in handling RabbitMQ message: %s", e)
        
    def stop_current_show(self):
        self.logger.debug('Notifying Liquidsoap to stop playback.')
        try:
            tn = telnetlib.Telnet(LS_HOST, LS_PORT)
            tn.write('source.skip\n')
            tn.write('exit\n')
            tn.read_all()
        except Exception, e:
            self.logger.debug(e)
            self.logger.debug('Could not connect to liquidsoap')
    
    def regenerateLiquidsoapConf(self, setting):
        existing = {}
        # create a temp file
        fh = open('/etc/airtime/liquidsoap.cfg', 'r')
        self.logger.info("Reading existing config...")
        # read existing conf file and build dict
        while 1:
            line = fh.readline()
            if not line:
                break
            
            line = line.strip()
            if line.find('#') == 0:
                continue
            # if empty line
            if not line:
                continue
            key, value = line.split('=')
            key = key.strip()
            value = value.strip()
            value = value.replace('"', '')
            if value == "" or value == "0":
                value = ''
            existing[key] =  value
        fh.close()
        
        # dict flag for any change in cofig
        change = {}
        # this flag is to detect diable -> disable change
        # in that case, we don't want to restart even if there are chnges.
        state_change_restart = {}
        #restart flag
        restart = False
        
        self.logger.info("Looking for changes...")
        # look for changes
        for s in setting:
            if "output_sound_device" in s[u'keyname'] or "icecast_vorbis_metadata" in s[u'keyname']:
                dump, stream = s[u'keyname'].split('_', 1)
                state_change_restart[stream] = False
                # This is the case where restart is required no matter what
                if (existing[s[u'keyname']] != s[u'value']):
                    self.logger.info("'Need-to-restart' state detected for %s...", s[u'keyname'])
                    restart = True;
            else:
                stream, dump = s[u'keyname'].split('_',1)
                if "_output" in s[u'keyname']:
                    if (existing[s[u'keyname']] != s[u'value']):
                        self.logger.info("'Need-to-restart' state detected for %s...", s[u'keyname'])
                        restart = True;
                        state_change_restart[stream] = True
                    elif ( s[u'value'] != 'disabled'):
                        state_change_restart[stream] = True
                    else:
                        state_change_restart[stream] = False
                else:
                    # setting inital value
                    if stream not in change:
                        change[stream] = False
                    if not (s[u'value'] == existing[s[u'keyname']]):
                        self.logger.info("Keyname: %s, Curent value: %s, New Value: %s", s[u'keyname'], existing[s[u'keyname']], s[u'value'])
                        change[stream] = True
                        
        # set flag change for sound_device alway True
        self.logger.info("Change:%s, State_Change:%s...", change, state_change_restart)
        
        for k, v in state_change_restart.items():
            if k == "sound_device" and v:
                restart = True
            elif v and change[k]:
                self.logger.info("'Need-to-restart' state detected for %s...", k)
                restart = True
        # rewrite
        if restart:
            fh = open('/etc/airtime/liquidsoap.cfg', 'w')
            self.logger.info("Rewriting liquidsoap.cfg...")
            fh.write("################################################\n")
            fh.write("# THIS FILE IS AUTO GENERATED. DO NOT CHANGE!! #\n")
            fh.write("################################################\n")
            for d in setting:
                buffer = d[u'keyname'] + " = "
                if(d[u'type'] == 'string'):
                    temp = d[u'value']
                    if(temp == ""):
                        temp = ""
                    buffer += "\"" + temp + "\""
                else:
                    temp = d[u'value']
                    if(temp == ""):
                        temp = "0"
                    buffer += temp
                buffer += "\n"
                fh.write(api_client.encode_to(buffer))
            fh.write("log_file = \"/var/log/airtime/pypo-liquidsoap/<script>.log\"\n");
            fh.close()
            # restarting pypo.
            # we could just restart liquidsoap but it take more time somehow.
            self.logger.info("Restarting pypo...")
            sys.exit(0)
        else:
            self.logger.info("No change detected in setting...")
            self.update_liquidsoap_connection_status()
    """
        updates the status of liquidsoap connection to the streaming server
        This fucntion updates the bootup time variable in liquidsoap script
    """
    def update_liquidsoap_connection_status(self):
        tn = telnetlib.Telnet(LS_HOST, LS_PORT)
        # update the boot up time of liquidsoap. Since liquidsoap is not restarting,
        # we are manually adjusting the bootup time variable so the status msg will get
        # updated.
        current_time = time.time()
        boot_up_time_command = "vars.bootup_time "+str(current_time)+"\n"
        tn.write(boot_up_time_command)
        tn.write("streams.connection_status\n")
        tn.write('exit\n')
        
        output = tn.read_all()
        output_list = output.split("\r\n")
        stream_info = output_list[2]
        
        # streamin info is in the form of:
        # eg. s1:true,2:true,3:false
        streams = stream_info.split(",")
        self.logger.info(streams)
        
        fake_time = current_time + 1
        for s in streams:
            info = s.split(':')
            stream_id = info[0]
            status = info[1]
            if(status == "true"):
                self.api_client.notify_liquidsoap_status("OK", stream_id, str(fake_time))
                
    def update_liquidsoap_stream_format(self, stream_format):
        # Push stream metadata to liquidsoap
        # TODO: THIS LIQUIDSOAP STUFF NEEDS TO BE MOVED TO PYPO-PUSH!!!
        try:
            self.logger.info(LS_HOST)
            self.logger.info(LS_PORT)
            tn = telnetlib.Telnet(LS_HOST, LS_PORT)
            command = ('vars.stream_metadata_type %s\n' % stream_format).encode('utf-8')
            self.logger.info(command)
            tn.write(command)
            tn.write('exit\n')
            tn.read_all()
        except Exception, e:
            self.logger.error("Exception %s", e)
    
    def update_liquidsoap_station_name(self, station_name):
        # Push stream metadata to liquidsoap
        # TODO: THIS LIQUIDSOAP STUFF NEEDS TO BE MOVED TO PYPO-PUSH!!!
        try:
            self.logger.info(LS_HOST)
            self.logger.info(LS_PORT)
            tn = telnetlib.Telnet(LS_HOST, LS_PORT)
            command = ('vars.station_name %s\n' % station_name).encode('utf-8')
            self.logger.info(command)
            tn.write(command)
            tn.write('exit\n')
            tn.read_all()
        except Exception, e:
            self.logger.error("Exception %s", e)

    """
    Process the schedule
     - Reads the scheduled entries of a given range (actual time +/- "prepare_ahead" / "cache_for")
     - Saves a serialized file of the schedule
     - playlists are prepared. (brought to liquidsoap format) and, if not mounted via nsf, files are copied
       to the cache dir (Folder-structure: cache/YYYY-MM-DD-hh-mm-ss)
     - runs the cleanup routine, to get rid of unused cached files
    """
    def process_schedule(self, schedule_data, bootstrapping):
        media = schedule_data["media"]

        # Download all the media and put playlists in liquidsoap "annotate" format
        try:
             media = self.prepare_media(media, bootstrapping)
        except Exception, e: self.logger.error("%s", e)

        # Send the data to pypo-push
        scheduled_data = dict()
        scheduled_data['liquidsoap_annotation_queue'] = liquidsoap_annotation_queue
        self.queue.put(media)

        """
        # cleanup
        try: self.cleanup()
        except Exception, e: self.logger.error("%s", e)
        """

        

    def prepare_media(self, media, bootstrapping):
        """
        Iterate through the list of media items in "media" and 
        download them.
        """
        try:
            mediaKeys = sorted(media.iterkeys())
            for mkey in mediaKeys:
                self.logger.debug("Media item starting at %s", mkey)
                media_item = media[mkey]
                
                if bootstrapping:            
                    check_for_crash(media_item)

                # create playlist directory
                try:
                    """
                    Extract year, month, date from mkey
                    """
                    y_m_d = mkey[0:10]
                    download_dir = os.mkdir(os.path.join(self.cache_dir, y_m_d))
                    fileExt = os.path.splitext(media_item['uri'])[1]
                    dst = os.path.join(download_dir, media_item['id']+fileExt)
                except Exception, e:
                    self.logger.warning(e)
                
                if self.handle_media_file(media_item, dst):
                    entry = create_liquidsoap_annotation(media_item, dst)
                    #entry['show_name'] = playlist['show_name']
                    entry['show_name'] = "TODO"
                    media_item["annotation"] = entry
                
        except Exception, e:
            self.logger.error("%s", e)
                
        return media
    

    def create_liquidsoap_annotation(media, dst):
        pl_entry = \
            'annotate:media_id="%s",liq_start_next="%s",liq_fade_in="%s",liq_fade_out="%s",liq_cue_in="%s",liq_cue_out="%s",schedule_table_id="%s":%s' \
            % (media['id'], 0, \
            float(media['fade_in']) / 1000, \
            float(media['fade_out']) / 1000, \
            float(media['cue_in']), \
            float(media['cue_out']), \
            media['row_id'], dst)

        """
        Tracks are only added to the playlist if they are accessible
        on the file system and larger than 0 bytes.
        So this can lead to playlists shorter than expectet.
        (there is a hardware silence detector for this cases...)
        """
        entry = dict()
        entry['type'] = 'file'
        entry['annotate'] = pl_entry        
        return entry
        
    def check_for_crash(media_item):
        start = media_item['start']
        end = media_item['end']
        
        dtnow = datetime.utcnow()
        str_tnow_s = dtnow.strftime('%Y-%m-%d-%H-%M-%S')
                        
        if start <= str_tnow_s and str_tnow_s < end:
            #song is currently playing and we just started pypo. Maybe there
            #was a power outage? Let's restart playback of this song.
            start_split = map(int, start.split('-'))
            media_start = datetime(start_split[0], start_split[1], start_split[2], start_split[3], start_split[4], start_split[5], 0, None)
            self.logger.debug("Found media item that started at %s.", media_start)
            
            delta = dtnow - media_start #we get a TimeDelta object from this operation
            self.logger.info("Starting media item  at %d second point", delta.seconds)
            
            """
            Set the cue_in. This is used by Liquidsoap to determine at what point in the media
            item it should start playing. If the cue_in happens to be > cue_out, then make cue_in = cue_out
            """
            media_item['cue_in'] = delta.seconds + 10 if delta.seconds + 10 < media_item['cue_out'] else media_item['cue_out']
            
            """
            Set the start time, which is used by pypo-push to determine when a media item is scheduled.
            Pushing the start time into the future will ensure pypo-push will push this to Liquidsoap.
            """
            td = timedelta(seconds=10)
            media_item['start'] = (dtnow + td).strftime('%Y-%m-%d-%H-%M-%S')
            self.logger.info("Crash detected, setting playlist to restart at %s", (dtnow + td).strftime('%Y-%m-%d-%H-%M-%S'))
    
    def handle_media_file(self, media_item, dst):
        """
        Download and cache the media item.
        """
        
        self.logger.debug("Processing track %s", media_item['uri'])

        try:
            #blocking function to download the media item
            self.download_file(media_item, dst)
            
            if os.access(dst, os.R_OK):
                # check filesize (avoid zero-byte files)
                try: 
                    fsize = os.path.getsize(dst)
                    if fsize > 0:
                        return True
                except Exception, e:
                    self.logger.error("%s", e)
                    fsize = 0
            else:
                self.logger.warning("Cannot read file %s.", dst)

        except Exception, e: 
            self.logger.info("%s", e)
            
        return False


    """
    Download a file from a remote server and store it in the cache.
    """
    def download_file(self, media_item, dst):
        if os.path.isfile(dst):
            pass
            #self.logger.debug("file already in cache: %s", dst)
        else:
            self.logger.debug("try to download %s", media_item['uri'])
            self.api_client.get_media(media_item['uri'], dst)
    
    """
    Cleans up folders in cache_dir. Look for modification date older than "now - CACHE_FOR"
    and deletes them.
    """
    def cleanup(self):
        offset = 3600 * int(config["cache_for"])
        now = time.time()

        for r, d, f in os.walk(self.cache_dir):
            for dir in d:
                try:
                    timestamp = calendar.timegm(time.strptime(dir, "%Y-%m-%d-%H-%M-%S"))
                    if (now - timestamp) > offset:
                        try:
                            self.logger.debug('trying to remove  %s - timestamp: %s', os.path.join(r, dir), timestamp)
                            shutil.rmtree(os.path.join(r, dir))
                        except Exception, e:
                            self.logger.error("%s", e)
                            pass
                        else:
                            self.logger.info('sucessfully removed %s', os.path.join(r, dir))
                except Exception, e:
                    self.logger.error(e)


    def main(self):
        try: os.mkdir(self.cache_dir)
        except Exception, e: pass

        try:
            # Bootstrap: since we are just starting up, we need to grab the
            # most recent schedule.  After that we can just wait for updates. 
            success, self.schedule_data = self.api_client.get_schedule()
            if success:
                self.logger.info("Bootstrap schedule received: %s", self.schedule_data)
                self.process_schedule(self.schedule_data, True)
            self.logger.info("Bootstrap complete: got initial copy of the schedule")


            while not self.init_rabbit_mq():
                self.logger.error("Error connecting to RabbitMQ Server. Trying again in few seconds")
                time.sleep(5)
        except Exception, e:
            self.logger.error(str(e))

        loops = 1        
        while True:
            self.logger.info("Loop #%s", loops)
            try:               
                try:
                    message = self.simple_queue.get(block=True)
                    self.handle_message(message.payload)
                    # ACK the message to take it off the queue
                    message.ack()
                except MessageStateError, m:
                    self.logger.error("Message ACK error: %s", m)
            except Exception, e:
                """
                There is a problem with the RabbitMq messenger service. Let's
                log the error and get the schedule via HTTP polling
                """
                self.logger.error("Exception, %s", e)
                
                status, self.schedule_data = self.api_client.get_schedule()
                if status == 1:
                    self.process_schedule(self.schedule_data, False)

            loops += 1        

    """
    Main loop of the thread:
    Wait for schedule updates from RabbitMQ, but in case there arent any,
    poll the server to get the upcoming schedule.
    """
    def run(self):
        while True:
            self.main()
