# -*- coding: utf-8 -*-

from datetime import datetime
from datetime import timedelta

import sys
import time
import logging
import logging.config
import telnetlib
import calendar
import json
import math
from pypofetch import PypoFetch

from Queue import Empty

from threading import Thread

from api_clients import api_client
from std_err_override import LogWriter
from configobj import ConfigObj


# configure logging
logging.config.fileConfig("logging.cfg")
logger = logging.getLogger()
LogWriter.override_std_err(logger)

#need to wait for Python 2.7 for this..
#logging.captureWarnings(True)

# loading config file
try:
    config = ConfigObj('/etc/airtime/pypo.cfg')
    LS_HOST = config['ls_host']
    LS_PORT = config['ls_port']
    PUSH_INTERVAL = 2
    MAX_LIQUIDSOAP_QUEUE_LENGTH = 2
except Exception, e:
    logger.error('Error loading config file %s', e)
    sys.exit()

class PypoPush(Thread):
    def __init__(self, q, telnet_lock):
        Thread.__init__(self)
        self.api_client = api_client.api_client_factory(config)
        self.queue = q

        self.telnet_lock = telnet_lock

        self.pushed_objects = {}
        self.logger = logging.getLogger('push')
        
    def main(self):
        loops = 0
        heartbeat_period = math.floor(30/PUSH_INTERVAL)
        
        next_media_item_chain = None
        media_schedule = None
        time_until_next_play = None
        chains = None
                
        while True:
            try:
                if time_until_next_play is None:
                    media_schedule = self.queue.get(block=True)
                else:
                    media_schedule = self.queue.get(block=True, timeout=time_until_next_play)
                                        
                chains = self.get_all_chains(media_schedule)                         
                
                #We get to the following lines only if a schedule was received.
                liquidsoap_queue_approx = self.get_queue_items_from_liquidsoap()

                current_event_chain = self.get_current_chain(chains)                
                if len(current_event_chain) > 0 and len(liquidsoap_queue_approx) == 0:
                    #Something is scheduled but Liquidsoap is not playing anything!
                    #Need to schedule it immediately..this might happen if Liquidsoap crashed.
                    chains.remove(current_event_chain)
                    
                    self.modify_cue_point(current_event_chain[0])
                    next_media_item_chain = current_event_chain
                    time_until_next_play = 0
                else:
                    media_chain = filter(lambda item: (item["type"] == "file"), current_event_chain)
                    self.handle_new_media_schedule(media_schedule, liquidsoap_queue_approx, media_chain)
                    
                    next_media_item_chain = self.get_next_schedule_chain(chains)
                    
                    self.logger.debug("Next schedule chain: %s", next_media_item_chain)                
                    if next_media_item_chain is not None:
                        chains.remove(next_media_item_chain)
                        tnow = datetime.utcnow()
                        chain_start = datetime.strptime(next_media_item_chain[0]['start'], "%Y-%m-%d-%H-%M-%S")
                        time_until_next_play = self.date_interval_to_seconds(chain_start - tnow)
                        self.logger.debug("Blocking %s seconds until show start", time_until_next_play)
                    else:
                        self.logger.debug("Blocking indefinitely since no show scheduled")
                        time_until_next_play = None
            except Empty, e:
                #We only get here when a new chain of tracks are ready to be played.
                self.push_to_liquidsoap(next_media_item_chain)
                                
                next_media_item_chain = self.get_next_schedule_chain(chains)
                if next_media_item_chain is not None:
                    tnow = datetime.utcnow()
                    chain_start = datetime.strptime(next_media_item_chain[0]['start'], "%Y-%m-%d-%H-%M-%S")
                    time_until_next_play = self.date_interval_to_seconds(chain_start - tnow)
                    self.logger.debug("Blocking %s seconds until show start", time_until_next_play)
                else:
                    self.logger.debug("Blocking indefinitely since no show scheduled next")
                    time_until_next_play = None
                
            if loops % heartbeat_period == 0:
                self.logger.info("heartbeat")
                loops = 0
            loops += 1

    def get_queue_items_from_liquidsoap(self):
        """
        This function connects to Liquidsoap to find what media items are in its queue.
        """
        try:
            self.telnet_lock.acquire()
            tn = telnetlib.Telnet(LS_HOST, LS_PORT)
            
            msg = 'queue.queue\n'
            tn.write(msg)
            response = tn.read_until("\r\n").strip(" \r\n")
            tn.write('exit\n')
            tn.read_all()
        except Exception, e:
            self.logger.error("Error connecting to Liquidsoap: %s", e)
            response = []
        finally:
            self.telnet_lock.release()
        
        liquidsoap_queue_approx = []
        
        if len(response) > 0:
            items_in_queue = response.split(" ")
            
            self.logger.debug("items_in_queue: %s", items_in_queue)
            
            for item in items_in_queue:
                if item in self.pushed_objects:
                    liquidsoap_queue_approx.append(self.pushed_objects[item])
                else:
                    """
                    We should only reach here if Pypo crashed and restarted (because self.pushed_objects was reset). In this case
                    let's clear the entire Liquidsoap queue. 
                    """
                    self.logger.error("ID exists in liquidsoap queue that does not exist in our pushed_objects queue: " + item)
                    self.clear_liquidsoap_queue()
                    liquidsoap_queue_approx = []
                    break
                
        return liquidsoap_queue_approx
            
    def handle_new_media_schedule(self, media_schedule, liquidsoap_queue_approx, media_chain):
        """
        This function's purpose is to gracefully handle situations where
        Liquidsoap already has a track in its queue, but the schedule 
        has changed. If the schedule has changed, this function's job is to
        call other functions that will connect to Liquidsoap and alter its
        queue.
        """
        
        problem_at_iteration, problem_start_time = self.find_removed_items(media_schedule, liquidsoap_queue_approx)
        
        if problem_at_iteration is not None:
            #Items that are in Liquidsoap's queue aren't scheduled anymore. We need to connect
            #and remove these items.
            self.logger.debug("Change in link %s of current chain", problem_at_iteration)
            self.remove_from_liquidsoap_queue(problem_at_iteration, liquidsoap_queue_approx)
        
        if problem_at_iteration is None and len(media_chain) > len(liquidsoap_queue_approx):
            self.logger.debug("New schedule has longer current chain.")
            problem_at_iteration = len(liquidsoap_queue_approx)
        
        if problem_at_iteration is not None:
            self.logger.debug("Change in chain at link %s", problem_at_iteration)
            
            chain_to_push = media_chain[problem_at_iteration:]
            if len(chain_to_push) > 0:
                self.modify_cue_point(chain_to_push[0])
                self.push_to_liquidsoap(chain_to_push)
        
    """
    Compare whats in the liquidsoap_queue to the new schedule we just
    received in media_schedule. This function only iterates over liquidsoap_queue_approx
    and finds if every item in that list is still scheduled in "media_schedule". It doesn't 
    take care of the case where media_schedule has more items than liquidsoap_queue_approx
    """
    def find_removed_items(self, media_schedule, liquidsoap_queue_approx):
        #iterate through the items we got from the liquidsoap queue and 
        #see if they are the same as the newly received schedule
        iteration = 0
        problem_at_iteration = None
        problem_start_time = None
        for queue_item in liquidsoap_queue_approx:
            if queue_item['start'] in media_schedule.keys():
                media_item = media_schedule[queue_item['start']]
                if queue_item['id'] == media_item['id']:
                    if queue_item['end'] == media_item['end']:
                        #Everything OK for this iteration.
                        pass
                    else:
                        problem_at_iteration = iteration
                        problem_start_time = queue_item['start']
                        break 
                else:
                    #A different item has been scheduled at the same time! Need to remove
                    #all tracks from the Liquidsoap queue starting at this point, and re-add
                    #them. 
                    problem_at_iteration = iteration
                    problem_start_time = queue_item['start']
                    break
            else:
                #There are no more items scheduled for this time! The user has shortened
                #the playlist, so we simply need to remove tracks from the queue. 
                problem_at_iteration = iteration
                problem_start_time = queue_item['start']
                break
            iteration+=1
        return (problem_at_iteration, problem_start_time)
        
        
        
    def get_all_chains(self, media_schedule):
        chains = []
        
        current_chain = []
        
        sorted_keys = sorted(media_schedule.keys())
        
        for mkey in sorted_keys:
            media_item = media_schedule[mkey]
            if media_item['type'] == "event":
                chains.append([media_item])
            elif len(current_chain) == 0:
                current_chain.append(media_item)
            elif media_item['start'] == current_chain[-1]['end']:
                current_chain.append(media_item)
            else:
                #current item is not a continuation of the chain.
                #Start a new one instead
                chains.append(current_chain)
                current_chain = [media_item]
                
        if len(current_chain) > 0:
            chains.append(current_chain)
            
        return chains
    
    def modify_cue_point(self, link):
        tnow = datetime.utcnow()
        
        link_start = datetime.strptime(link['start'], "%Y-%m-%d-%H-%M-%S")
        
        diff_td = tnow - link_start
        diff_sec = self.date_interval_to_seconds(diff_td)
        
        if diff_sec > 0:
            self.logger.debug("media item was supposed to start %s ago. Preparing to start..", diff_sec)
            original_cue_in_td = timedelta(seconds=float(link['cue_in']))
            link['cue_in'] = self.date_interval_to_seconds(original_cue_in_td) + diff_sec
    
    
    def get_current_chain(self, chains):
        tnow = datetime.utcnow()
        current_chain = []

        for chain in chains:
            iteration = 0
            for link in chain:
                link_start = datetime.strptime(link['start'], "%Y-%m-%d-%H-%M-%S")
                link_end = datetime.strptime(link['end'], "%Y-%m-%d-%H-%M-%S")
                
                self.logger.debug("tnow %s, chain_start %s", tnow, link_start)
                if link_start <= tnow and tnow < link_end:
                    current_chain = chain[iteration:]
                    break
                iteration += 1
                            
        return current_chain
                
    """
    The purpose of this function is to take a look at the last received schedule from
    pypo-fetch and return the next chain of media_items. A chain is defined as a sequence 
    of media_items where the end time of media_item 'n' is the start time of media_item
    'n+1'
    """
    def get_next_schedule_chain(self, chains):                
        #all media_items are now divided into chains. Let's find the one that
        #starts closest in the future.
        tnow = datetime.utcnow()
        closest_start = None
        closest_chain = None
        for chain in chains:
            chain_start = datetime.strptime(chain[0]['start'], "%Y-%m-%d-%H-%M-%S")
            self.logger.debug("tnow %s, chain_start %s", tnow, chain_start)
            if (closest_start == None or chain_start < closest_start) and chain_start > tnow:
                closest_start = chain_start
                closest_chain = chain
                
        return closest_chain
        
                   
    def date_interval_to_seconds(self, interval):
        return (interval.microseconds + (interval.seconds + interval.days * 24 * 3600) * 10**6) / float(10**6)
                        
    def push_to_liquidsoap(self, event_chain):
        
        try:
            for media_item in event_chain:
                if media_item['type'] == "file":
                    self.telnet_to_liquidsoap(media_item)
                elif media_item['type'] == "event":
                    if media_item['event_type'] == "kick_out":
                        PypoFetch.disconnect_source(self.logger, self.telnet_lock, "live_dj")
                    elif media_item['event_type'] == "switch_off":
                        PypoFetch.switch_source(self.logger, self.telnet_lock, "live_dj", "off")
        except Exception, e:
            self.logger.error('Pypo Push Exception: %s', e)
                                            
    def clear_liquidsoap_queue(self):
        self.logger.debug("Clearing Liquidsoap queue")
        try:
            self.telnet_lock.acquire()
            tn = telnetlib.Telnet(LS_HOST, LS_PORT)
            msg = "source.skip\n"
            tn.write(msg)                
            tn.write("exit\n")
            tn.read_all()
        except Exception, e:
            self.logger.error(str(e))
        finally:
            self.telnet_lock.release()        
                
    def remove_from_liquidsoap_queue(self, problem_at_iteration, liquidsoap_queue_approx):        
        iteration = 0
        
        try:
            self.telnet_lock.acquire()
            tn = telnetlib.Telnet(LS_HOST, LS_PORT)
            
            for queue_item in liquidsoap_queue_approx:
                if iteration >= problem_at_iteration:

                    msg = "queue.remove %s\n" % queue_item['queue_id']
                    self.logger.debug(msg)
                    tn.write(msg)
                    response = tn.read_until("\r\n").strip("\r\n")
                    
                    if "No such request in my queue" in response:
                        """
                        Cannot remove because Liquidsoap started playing the item. Need
                        to use source.skip instead
                        """
                        msg = "source.skip\n"
                        self.logger.debug(msg)
                        tn.write(msg)
                iteration += 1
                        
            tn.write("exit\n")
            tn.read_all()
        except Exception, e:
            self.logger.error(str(e))
        finally:
            self.telnet_lock.release()
                
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
        try:
            self.telnet_lock.acquire()
            tn = telnetlib.Telnet(LS_HOST, LS_PORT)
            
            #tn.write(("vars.pypo_data %s\n"%liquidsoap_data["schedule_id"]).encode('utf-8'))
            
            annotation = self.create_liquidsoap_annotation(media_item)
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
        except Exception, e:
            self.logger.error(str(e))
        finally:
            self.telnet_lock.release()
            
    def create_liquidsoap_annotation(self, media):        
        # we need lia_start_next value in the annotate. That is the value that controlls overlap duration of crossfade.
        return 'annotate:media_id="%s",liq_start_next="0",liq_fade_in="%s",liq_fade_out="%s",liq_cue_in="%s",liq_cue_out="%s",schedule_table_id="%s":%s' \
            % (media['id'], float(media['fade_in'])/1000, float(media['fade_out'])/1000, float(media['cue_in']), float(media['cue_out']), media['row_id'], media['dst'])
                     
    def run(self):
        try: self.main()
        except Exception, e:
            import traceback
            top = traceback.format_exc()
            self.logger.error('Pypo Push Exception: %s', top)
            
