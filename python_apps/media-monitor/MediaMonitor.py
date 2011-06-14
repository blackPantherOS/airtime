#!/usr/local/bin/python
import logging
import logging.config
import json
import time
import datetime
import os
import sys
import hashlib
import json
import shutil
import math

from collections import deque
from pwd import getpwnam
from subprocess import Popen, PIPE, STDOUT

from configobj import ConfigObj

import mutagen
import pyinotify
from pyinotify import WatchManager, Notifier, ProcessEvent

# For RabbitMQ
from kombu.connection import BrokerConnection
from kombu.messaging import Exchange, Queue, Consumer, Producer
from api_clients import api_client

MODE_CREATE = "create"
MODE_MODIFY = "modify"
MODE_MOVED = "moved"
MODE_DELETE = "delete"

global storage_directory
global plupload_directory

# configure logging
try:
    logging.config.fileConfig("logging.cfg")
except Exception, e:
    print 'Error configuring logging: ', e
    sys.exit()

# loading config file
try:
    config = ConfigObj('/etc/airtime/media-monitor.cfg')
except Exception, e:
    logger = logging.getLogger();
    logger.error('Error loading config file: %s', e)
    sys.exit()

"""
list of supported easy tags in mutagen version 1.20
['albumartistsort', 'musicbrainz_albumstatus', 'lyricist', 'releasecountry', 'date', 'performer', 'musicbrainz_albumartistid', 'composer', 'encodedby', 'tracknumber', 'musicbrainz_albumid', 'album', 'asin', 'musicbrainz_artistid', 'mood', 'copyright', 'author', 'media', 'length', 'version', 'artistsort', 'titlesort', 'discsubtitle', 'website', 'musicip_fingerprint', 'conductor', 'compilation', 'barcode', 'performer:*', 'composersort', 'musicbrainz_discid', 'musicbrainz_albumtype', 'genre', 'isrc', 'discnumber', 'musicbrainz_trmid', 'replaygain_*_gain', 'musicip_puid', 'artist', 'title', 'bpm', 'musicbrainz_trackid', 'arranger', 'albumsort', 'replaygain_*_peak', 'organization']
"""

class AirtimeNotifier(Notifier):

    def __init__(self, watch_manager, default_proc_fun=None, read_freq=0, threshold=0, timeout=None):
        Notifier.__init__(self, watch_manager, default_proc_fun, read_freq, threshold, timeout)

        self.airtime2mutagen = {\
        "MDATA_KEY_TITLE": "title",\
        "MDATA_KEY_CREATOR": "artist",\
        "MDATA_KEY_SOURCE": "album",\
        "MDATA_KEY_GENRE": "genre",\
        "MDATA_KEY_MOOD": "mood",\
        "MDATA_KEY_TRACKNUMBER": "tracknumber",\
        "MDATA_KEY_BPM": "bpm",\
        "MDATA_KEY_LABEL": "organization",\
        "MDATA_KEY_COMPOSER": "composer",\
        "MDATA_KEY_ENCODER": "encodedby",\
        "MDATA_KEY_CONDUCTOR": "conductor",\
        "MDATA_KEY_YEAR": "date",\
        "MDATA_KEY_URL": "website",\
        "MDATA_KEY_ISRC": "isrc",\
        "MDATA_KEY_COPYRIGHT": "copyright",\
        }

        schedule_exchange = Exchange("airtime-media-monitor", "direct", durable=True, auto_delete=True)
        schedule_queue = Queue("media-monitor", exchange=schedule_exchange, key="filesystem")
        self.connection = BrokerConnection(config["rabbitmq_host"], config["rabbitmq_user"], config["rabbitmq_password"], "/")
        channel = self.connection.channel()
        consumer = Consumer(channel, schedule_queue)
        consumer.register_callback(self.handle_message)
        consumer.consume()

    def handle_message(self, body, message):
        # ACK the message to take it off the queue
        message.ack()

        logger = logging.getLogger('root')
        logger.info("Received md from RabbitMQ: " + body)

        m =  json.loads(message.body)
        airtime_file = mutagen.File(m['filepath'], easy=True)
        del m['filepath']
        for key in m.keys() :
            if m[key] != "" :
                airtime_file[self.airtime2mutagen[key]] = m[key]

        airtime_file.save()

class MediaMonitor(ProcessEvent):

    def my_init(self):
        """
        Method automatically called from ProcessEvent.__init__(). Additional
        keyworded arguments passed to ProcessEvent.__init__() are then
        delegated to my_init().
        """
        self.api_client = api_client.api_client_factory(config)

        self.mutagen2airtime = {\
        "title": "MDATA_KEY_TITLE",\
        "artist": "MDATA_KEY_CREATOR",\
        "album": "MDATA_KEY_SOURCE",\
        "genre": "MDATA_KEY_GENRE",\
        "mood": "MDATA_KEY_MOOD",\
        "tracknumber": "MDATA_KEY_TRACKNUMBER",\
        "bpm": "MDATA_KEY_BPM",\
        "organization": "MDATA_KEY_LABEL",\
        "composer": "MDATA_KEY_COMPOSER",\
        "encodedby": "MDATA_KEY_ENCODER",\
        "conductor": "MDATA_KEY_CONDUCTOR",\
        "date": "MDATA_KEY_YEAR",\
        "website": "MDATA_KEY_URL",\
        "isrc": "MDATA_KEY_ISRC",\
        "copyright": "MDATA_KEY_COPYRIGHT",\
        }

        self.supported_file_formats = ['mp3', 'ogg']
        self.logger = logging.getLogger('root')
        self.temp_files = {}
        self.moved_files = {}
        self.file_events = deque()

        self.mask =  pyinotify.IN_CREATE | \
                pyinotify.IN_MODIFY | \
                pyinotify.IN_MOVED_FROM | \
                pyinotify.IN_MOVED_TO | \
                pyinotify.IN_DELETE | \
                pyinotify.IN_DELETE_SELF

        self.wm = WatchManager()

        schedule_exchange = Exchange("airtime-media-monitor", "direct", durable=True, auto_delete=True)
        schedule_queue = Queue("media-monitor", exchange=schedule_exchange, key="filesystem")
        connection = BrokerConnection(config["rabbitmq_host"], config["rabbitmq_user"], config["rabbitmq_password"], "/")
        channel = connection.channel()

    def watch_directory(self, directory):
        return self.wm.add_watch(directory, self.mask, rec=True, auto_add=True)

    def is_parent_directory(self, filepath, directory):
        return (directory == filepath[0:len(directory)])

    def get_md5(self, filepath):
        f = open(filepath, 'rb')
        m = hashlib.md5()
        m.update(f.read())
        md5 = m.hexdigest()

        return md5

    ## mutagen_length is in seconds with the format (d+).dd
    ## return format hh:mm:ss.uuu
    def format_length(self, mutagen_length):
        t = float(mutagen_length)
        h = int(math.floor(t/3600))
        t = t % 3600
        m = int(math.floor(t/60))

        s = t % 60
        # will be ss.uuu
        s = str(s)
        s = s[:6]

        length = "%s:%s:%s" % (h, m, s)

        return length

    def ensure_dir(self, filepath):
        directory = os.path.dirname(filepath)

        try:
            omask = os.umask(0)
            if ((not os.path.exists(directory)) or ((os.path.exists(directory) and not os.path.isdir(directory)))):
                os.makedirs(directory, 02775)
                self.watch_directory(directory)
        finally:
            os.umask(omask)

    def create_unique_filename(self, filepath):

        if(os.path.exists(filepath)):
            file_dir = os.path.dirname(filepath)
            filename = os.path.basename(filepath).split(".")[0]
            file_ext = os.path.splitext(filepath)[1]
            i = 1;
            while(True):
                new_filepath = "%s/%s(%s).%s" % (file_dir, filename, i, file_ext)

                if(os.path.exists(new_filepath)):
                    i = i+1;
                else:
                    filepath = new_filepath

        return filepath

    def create_file_path(self, imported_filepath):

        global storage_directory

        original_name = os.path.basename(imported_filepath)
        file_ext = os.path.splitext(imported_filepath)[1]
        file_info = mutagen.File(imported_filepath, easy=True)

        metadata = {'artist':None,
                    'album':None,
                    'title':None,
                    'tracknumber':None}

        for key in metadata.keys():
            if key in file_info:
                metadata[key] = file_info[key][0]

        if metadata['artist'] is not None:
            base = "%s/%s" % (storage_directory, metadata['artist'])
            if metadata['album'] is not None:
                base = "%s/%s" % (base, metadata['album'])
            if metadata['title'] is not None:
                if metadata['tracknumber'] is not None:
                    metadata['tracknumber'] = "%02d" % (int(metadata['tracknumber']))
                    base = "%s/%s - %s" % (base, metadata['tracknumber'], metadata['title'])
                else:
                    base = "%s/%s" % (base, metadata['title'])
            else:
                base = "%s/%s" % (base, original_name)
        else:
            base = "%s/%s" % (storage_directory, original_name)

        base = "%s%s" % (base, file_ext)

        filepath = self.create_unique_filename(base)
        self.ensure_dir(filepath)

        return filepath

    def get_mutagen_info(self, filepath):
        md = {}
        md5 = self.get_md5(filepath)
        md['MDATA_KEY_MD5'] = md5

        file_info = mutagen.File(filepath, easy=True)
        attrs = self.mutagen2airtime
        for key in file_info.keys() :
            if key in attrs :
                md[attrs[key]] = file_info[key][0]

        md['MDATA_KEY_MIME'] = file_info.mime[0]
        md['MDATA_KEY_BITRATE'] = file_info.info.bitrate
        md['MDATA_KEY_SAMPLERATE'] = file_info.info.sample_rate
        md['MDATA_KEY_DURATION'] = self.format_length(file_info.info.length)

        return md


    def update_airtime(self, d):

        filepath = d['filepath']
        mode = d['mode']

        data = None
        md = {}
        md['MDATA_KEY_FILEPATH'] = filepath

        if (os.path.exists(filepath) and (mode == MODE_CREATE)):
            mutagen = self.get_mutagen_info(filepath)
            md.update(mutagen)
            data = {'md': md}
        elif (os.path.exists(filepath) and (mode == MODE_MODIFY)):
            mutagen = self.get_mutagen_info(filepath)
            md.update(mutagen)
            data = {'md': md}
        elif (mode == MODE_MOVED):
            mutagen = self.get_mutagen_info(filepath)
            md.update(mutagen)
            data = {'md': md}
        elif (mode == MODE_DELETE):
            data = {'md': md}

        if data is not None:
            self.logger.info("Updating Change to Airtime")
            response = None
            while response is None:
                response = self.api_client.update_media_metadata(data, mode)
                time.sleep(5)

    def is_temp_file(self, filename):
        info = filename.split(".")

        if(info[-2] in self.supported_file_formats):
            return True
        else:
            return False

    def is_audio_file(self, filename):
        info = filename.split(".")

        if(info[-1] in self.supported_file_formats):
            return True
        else:
            return False

    def process_IN_CREATE(self, event):
        if not event.dir:
            self.logger.info("%s: %s", event.maskname, event.pathname)
            #file created is a tmp file which will be modified and then moved back to the original filename.
            if self.is_temp_file(event.name) :
                self.temp_files[event.pathname] = None
            #This is a newly imported file.
            else :
                global plupload_directory
                #files that have been added through plupload have a placeholder already put in Airtime's database.
                if not self.is_parent_directory(event.pathname, plupload_directory):
                    md5 = self.get_md5(event.pathname)
                    response = self.api_client.check_media_status(md5)

                    #this file is new, md5 does not exist in Airtime.
                    if(response['airtime_status'] == 0):
                        filepath = self.create_file_path(event.pathname)
                        self.file_events.append({'old_filepath': event.pathname, 'mode': MODE_CREATE, 'filepath': filepath})

    def process_IN_MODIFY(self, event):
        if not event.dir:
            self.logger.info("%s: %s", event.maskname, event.pathname)
            global plupload_directory
            #files that have been added through plupload have a placeholder already put in Airtime's database.
            if not self.is_parent_directory(event.pathname, plupload_directory):
                if self.is_audio_file(event.name) :
                    self.file_events.append({'filepath': event.pathname, 'mode': MODE_MODIFY})

    def process_IN_MOVED_FROM(self, event):
        self.logger.info("%s: %s", event.maskname, event.pathname)
        if event.pathname in self.temp_files:
            del self.temp_files[event.pathname]
            self.temp_files[event.cookie] = event.pathname
        else:
            self.moved_files[event.cookie] = event.pathname

    def process_IN_MOVED_TO(self, event):
        self.logger.info("%s: %s", event.maskname, event.pathname)
        if event.cookie in self.temp_files:
            del self.temp_files[event.cookie]
            self.file_events.append({'filepath': event.pathname, 'mode': MODE_MODIFY})
        elif event.cookie in self.moved_files:
            old_filepath = self.moved_files[event.cookie]
            del self.moved_files[event.cookie]

            global plupload_directory
            #add a modify event to send md to Airtime for the plupload file.
            if self.is_parent_directory(old_filepath, plupload_directory):
                #file renamed from /tmp/plupload does not have a path in our naming scheme yet.
                md_filepath = self.create_file_path(event.pathname)
                #move the file a second time to its correct Airtime naming schema.
                os.rename(event.pathname, md_filepath)
                self.file_events.append({'filepath': md_filepath, 'mode': MODE_MOVED})
            else:
                self.file_events.append({'filepath': event.pathname, 'mode': MODE_MOVED})

    def process_IN_DELETE(self, event):
        if not event.dir:
            self.logger.info("%s: %s", event.maskname, event.pathname)
            self.file_events.append({'filepath': event.pathname, 'mode': MODE_DELETE})

    def process_default(self, event):
        self.logger.info("%s: %s", event.maskname, event.pathname)

    def notifier_loop_callback(self, notifier):

        while len(self.file_events) > 0:
            file_info = self.file_events.popleft()

            if(file_info['mode'] == MODE_CREATE):
                os.rename(file_info['old_filepath'], file_info['filepath'])

            self.update_airtime(file_info)

        try:
            notifier.connection.drain_events(timeout=1)
        except Exception, e:
            self.logger.info("%s", e)

if __name__ == '__main__':

    try:
        logger = logging.getLogger('root')
        mm = MediaMonitor()

        response = None
        while response is None:
            response = mm.api_client.setup_media_monitor()
            time.sleep(5)

        storage_directory = response["stor"]
        plupload_directory = response["plupload"]

        wdd = mm.watch_directory(storage_directory)
        logger.info("Added watch to %s", storage_directory)
        logger.info("wdd result %s", wdd[storage_directory])
        wdd = mm.watch_directory(plupload_directory)
        logger.info("Added watch to %s", plupload_directory)
        logger.info("wdd result %s", wdd[plupload_directory])

        notifier = AirtimeNotifier(mm.wm, mm, read_freq=int(config["check_filesystem_events"]), timeout=1)
        notifier.coalesce_events()

        #notifier.loop(callback=mm.notifier_loop_callback)

        while True:
            if(notifier.check_events(1)):
                notifier.read_events()
                notifier.process_events()
            mm.notifier_loop_callback(notifier)

    except KeyboardInterrupt:
        notifier.stop()
    except Exception, e:
        logger.error('Exception: %s', e)
