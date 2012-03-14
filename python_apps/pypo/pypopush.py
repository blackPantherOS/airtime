import os
import sys
import time
import logging
import logging.config
import logging.handlers
import pickle
import telnetlib
import calendar
import json
import math

"""
It is possible to use a list as a queue, where the first element added is the first element 
retrieved ("first-in, first-out"); however, lists are not efficient for this purpose. Let's use
"deque"
"""
from collections import deque

from threading import Thread
from api_clients import api_client
from configobj import ConfigObj


# configure logging
logging.config.fileConfig("logging.cfg")

# loading config file
try:
    config = ConfigObj('/etc/airtime/pypo.cfg')
    LS_HOST = config['ls_host']
    LS_PORT = config['ls_port']
    PUSH_INTERVAL = 2
    MAX_LIQUIDSOAP_QUEUE_LENGTH = 2
except Exception, e:
    logger = logging.getLogger()
    logger.error('Error loading config file %s', e)
    sys.exit()

class PypoPush(Thread):
    def __init__(self, q):
        Thread.__init__(self)
        self.api_client = api_client.api_client_factory(config)
        self.queue = q

        self.media = dict()

        self.liquidsoap_state_play = True
        self.push_ahead = 10
        self.last_end_time = 0
        
        self.liquidsoap_queue = deque()
        
        self.logger = logging.getLogger('push')
        
    def push(self):
        """
        The Push Loop - the push loop periodically checks if there is a playlist 
        that should be scheduled at the current time.
        If yes, the current liquidsoap playlist gets replaced with the corresponding one,
        then liquidsoap is asked (via telnet) to reload and immediately play it.
        """
        
        self.update_liquidsoap_queue()

        timenow = time.time()
        # get a new schedule from pypo-fetch
        if not self.queue.empty():
            # make sure we get the latest schedule
            while not self.queue.empty():
                self.media = self.queue.get()
                
            self.logger.debug("Received data from pypo-fetch")          
            self.logger.debug('media %s' % json.dumps(self.media))
            self.handle_new_media(self.media)
                

        media = self.media
        
        if len(self.liquidsoap_queue) < MAX_LIQUIDSOAP_QUEUE_LENGTH:
            """
            We only care about what's scheduled if the number of items in the queue is less
            than our max queue limit.
            """
            currently_on_air = False
            if media:
                tnow = time.gmtime(timenow)
                tcoming = time.gmtime(timenow + self.push_ahead)
                str_tnow_s = "%04d-%02d-%02d-%02d-%02d-%02d" % (tnow[0], tnow[1], tnow[2], tnow[3], tnow[4], tnow[5])
                str_tcoming_s = "%04d-%02d-%02d-%02d-%02d-%02d" % (tcoming[0], tcoming[1], tcoming[2], tcoming[3], tcoming[4], tcoming[5])
                            
                for key in media.keys():
                    media_item = media[key]
                    item_start = media_item['start'][0:19]
                    
                    if str_tnow_s <= item_start and item_start < str_tcoming_s:
                        """
                        If the media item starts in the next 30 seconds, push it to the queue.
                        """
                        self.logger.debug('Preparing to push media item scheduled at: %s', key)
                                  
                        if self.push_to_liquidsoap(media_item):
                            self.logger.debug("Pushed to liquidsoap, updating 'played' status.")
                            
                            """
                            Temporary solution to make sure we don't push the same track multiple times.
                            """
                            del media[key]
                            
                            currently_on_air = True
                            self.liquidsoap_state_play = True
                        
    def push_to_liquidsoap(self, media_item):
        """
        This function looks at the media item, and either pushes it to the Liquidsoap
        queue immediately, or if the queue is empty - waits until the start time of the
        media item before pushing it. 
        """        
        try:
            if media_item["start"] == self.last_end_time:
                """
                this media item is attached to the end of the last
                track, so let's push it now so that Liquidsoap can start playing
                it immediately after (and prepare crossfades if need be).
                """
                self.logger.debug("Push track immediately.")
                self.telnet_to_liquidsoap(media_item)
                self.last_end_time = media_item["end"]
            else:
                """
                this media item does not start right after a current playing track.
                We need to sleep, and then wake up when this track starts.
                """
                self.logger.debug("sleep until track start.")
                self.sleep_until_start(media_item)
                
                self.telnet_to_liquidsoap(media_item)
                self.last_end_time = media_item["end"]
        except Exception, e:
            self.logger.error('Pypo Push Exception: %s', e)
            return False
            
        return True
    
    def update_liquidsoap_queue(self):
        """
        the queue variable liquidsoap_queue is our attempt to mirror
        what liquidsoap actually has in its own queue. Liquidsoap automatically
        updates its own queue when an item finishes playing, we have to do this
        manually. 
        
        This function will iterate through the liquidsoap_queue and remove items
        whose end time are in the past.
        """
        
        tnow = time.gmtime(timenow)
        str_tnow_s = "%04d-%02d-%02d-%02d-%02d-%02d" % (tnow[0], tnow[1], tnow[2], tnow[3], tnow[4], tnow[5])
        
        while len(self.liquidsoap_queue) > 0:
            if self.liquidsoap_queue[0]["end"] < str_tnow_s:
                self.liquidsoap_queue.popleft()
    
    def handle_new_media(self, media):
        """
        This function's purpose is to gracefully handle situations where
        Liquidsoap already has a track in its queue, but the schedule 
        has changed.
        """
        
        """
        First let's connect to Liquidsoap and find what media items are in its queue.
        
        We will compare these items to the schedule we've received and decide if any
        action needs to take place.  
        """
        tn = telnetlib.Telnet(LS_HOST, LS_PORT)
        
        msg = 'queue.queue %s\n' % media_item["queue_id"]
        tn.write(msg)
        response = tn.read_until("\r\n").strip("\r\n")
        tn.write('exit\n')
        tn.read_all()
        
        list = response.split(" ")
        
        liquidsoap_queue_mirror = []
        
        for l in list:
            if l in self.pushed_objects:
                liquidsoap_queue_mirror.append(self.pushed_objects[l])
            else:
                self.logger.error("ID exists in liquidsoap queue that does not exist in our pushed_objects queue")
        
        #TODO: Keys should already be sorted. Verify this. 
        sorted_keys = sort(media.keys())
        
        if len(liquidsoap_queue_mirror) == 0:
            """
            liquidsoap doesn't have anything in its queue, so we have nothing 
            to worry about.
            """
            pass
        
        if len(liquidsoap_queue_mirror) == 1:
            if liquidsoap_queue_mirror[0]['id'] != media_item[sorted_keys[0]]['id']:
                """
                liquidsoap queue does not match the newest schedule. The queue is only of
                length 1, and so that means the item in the queue is playing. Need to do source.skip
                """
                self.remove_from_liquidsoap_queue(liquidsoap_queue_mirror[0])
                
        
        if len(liquidsoap_queue_mirror) == 2:
            if liquidsoap_queue_mirror[0]['id'] == media_item[sorted_keys[0]]['id'] \
                and liquidsoap_queue_mirror[1]['id'] == media_item[sorted_keys[1]]['id']:
                """
                What's in the queue matches what's in the schedule. Nothing to do.
                """
                pass
            elif liquidsoap_queue_mirror[0]['id'] == media_item[sorted_keys[0]]['id'] \
                and liquidsoap_queue_mirror[1]['id'] != media_item[sorted_keys[1]]['id']:
                """
                instruct liquidsoap to remove the second item from the queue
                """
                self.remove_from_liquidsoap_queue(liquidsoap_queue_mirror[1])
            elif liquidsoap_queue_mirror[0]['id'] != media_item[sorted_keys[0]]['id']:
                """
                remove both items from the queue. Remove in reverse order so that source.skip
                doesn't skip to the second song which we also plan on removing.
                """
                self.remove_from_liquidsoap_queue(liquidsoap_queue_mirror[1])
                self.remove_from_liquidsoap_queue(liquidsoap_queue_mirror[0])
                
    def remove_from_liquidsoap_queue(self, media_item):
        if 'queue_id' in media_item:
            queue_id = media_item['queue_id']
            
            tn = telnetlib.Telnet(LS_HOST, LS_PORT)
            msg = "queue.remove %s\n" % queue_id
            tn.write(msg)
            response = tn.read_until("\r\n").strip("\r\n")
            
            if "No such request in my queue" in response:
                """
                Cannot remove because Liquidsoap started playing the item. Need
                to use source.skip instead
                """
                msg = "source.skip"
                tn.write("source.skip")
                
            tn.write("exit\n")
            tn.read_all()
        else:
            self.logger.error("'queue_id' key doesn't exist in media_item dict()")

    def sleep_until_start(self, media_item):
        """
        The purpose of this function is to look at the difference between
        "now" and when the media_item starts, and sleep for that period of time.
        After waking from sleep, this function returns.
        """
        
        mi_start = media_item['start'][0:19]
        
        #strptime returns struct_time in local time
        epoch_start = calendar.timegm(time.strptime(mi_start, '%Y-%m-%d-%H-%M-%S'))
        
        #Return the time as a floating point number expressed in seconds since the epoch, in UTC.
        epoch_now = time.time()
        
        self.logger.debug("Epoch start: %s" % epoch_start)
        self.logger.debug("Epoch now: %s" % epoch_now)

        sleep_time = epoch_start - epoch_now

        if sleep_time < 0:
            sleep_time = 0

        self.logger.debug('sleeping for %s s' % (sleep_time))
        time.sleep(sleep_time)

    def telnet_to_liquidsoap(self, media_item):
        """
        telnets to liquidsoap and pushes the media_item to its queue. Push the
        show name of every media_item as well, just to keep Liquidsoap up-to-date
        about which show is playing.
        """
        
        tn = telnetlib.Telnet(LS_HOST, LS_PORT)
        
        #tn.write(("vars.pypo_data %s\n"%liquidsoap_data["schedule_id"]).encode('utf-8'))
        
        annotation = media_item['annotation']
        msg = 'queue.push %s\n' % annotation.encode('utf-8')
        self.logger.debug(msg)
        tn.write(msg)
        queue_id = tn.read_until("\r\n").strip("\r\n")
        
        #remember the media_item's queue id which we may use
        #later if we need to remove it from the queue.
        media_item['queue_id'] = queue_id
        
        #add media_item to the end of our queue
        self.pushed_objects[queue_id] = media_item
        
        show_name = media_item['show_name']
        msg = 'vars.show_name %s\n' % show_name.encode('utf-8')
        tn.write(msg)
        self.logger.debug(msg)
        
        tn.write("exit\n")
        self.logger.debug(tn.read_all())
                     
    def run(self):
        loops = 0
        heartbeat_period = math.floor(30/PUSH_INTERVAL)
        
        while True:
            if loops % heartbeat_period == 0:
                self.logger.info("heartbeat")
                loops = 0
            try: self.push()
            except Exception, e:
                self.logger.error('Pypo Push Exception: %s', e)
            time.sleep(PUSH_INTERVAL)
            loops += 1
