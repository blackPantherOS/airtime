#!/usr/local/bin/python
import urllib
import logging
import logging.config
import json
import time
import datetime
import os
import sys
import shutil
from  Queue import Queue

from commandlistener import CommandListener

from configobj import ConfigObj

from poster.encode import multipart_encode
from poster.streaminghttp import register_openers
import urllib2

from subprocess import Popen
from threading import Thread

import mutagen

from api_clients import api_client

# configure logging
try:
    logging.config.fileConfig("logging.cfg")
except Exception, e:
    print 'Error configuring logging: ', e
    sys.exit()

# loading config file
try:
    config = ConfigObj('/etc/airtime/recorder.cfg')
except Exception, e:
    logger = logging.getLogger()
    logger.error('Error loading config file: %s', e)
    sys.exit()

def getDateTimeObj(time):

    timeinfo = time.split(" ")
    date = timeinfo[0].split("-")
    time = timeinfo[1].split(":")

    return datetime.datetime(int(date[0]), int(date[1]), int(date[2]), int(time[0]), int(time[1]), int(time[2]))

class ShowRecorder(Thread):

    def __init__ (self, show_instance, show_name, filelength, start_time, filetype):
        Thread.__init__(self)
        self.api_client = api_client.api_client_factory(config)
        self.filelength = filelength
        self.start_time = start_time
        self.filetype = filetype
        self.show_instance = show_instance
        self.show_name = show_name
        self.logger = logging.getLogger('root')
        self.p = None
        
    def record_show(self):
        length = str(self.filelength)+".0"
        filename = self.start_time
        filename = filename.replace(" ", "-")
        filepath = "%s%s.%s" % (config["base_recorded_files"], filename, self.filetype)

        command = "ecasound -i alsa -o %s -t:%s" % (filepath, length)
        #-ge:0.1,0.1,0,-1
        args = command.split(" ")

        self.logger.info("starting record")
        self.logger.info("command " + command)

        self.p = Popen(args)

        #blocks at the following line until the child process
        #quits
        code = self.p.wait()
        self.p = None

        self.logger.info("finishing record, return code %s", code)
        return code, filepath

    def cancel_recording(self):
        #add 3 second delay before actually cancelling the show. The reason
        #for this is because it appears that ecasound starts 1 second later than
        #it should, and therefore this method is sometimes incorrectly called 1 
        #second before the show ends.
        time.sleep(3)
    
        #send signal interrupt (2)
        self.logger.info("Show manually cancelled!")
        if (self.p is not None):
            self.p.terminate()
            self.p = None

    #if self.p is defined, then the child process ecasound is recording
    def is_recording(self):
        return (self.p is not None)

    def upload_file(self, filepath):

        filename = os.path.split(filepath)[1]

        # Register the streaming http handlers with urllib2
        register_openers()

        # headers contains the necessary Content-Type and Content-Length
        # datagen is a generator object that yields the encoded parameters
        datagen, headers = multipart_encode({"file": open(filepath, "rb"), 'name': filename, 'show_instance': self.show_instance})

        self.api_client.upload_recorded_show(datagen, headers)
    
    def set_metadata_and_save(self, filepath):
        try:
            date = self.start_time
            md = date.split(" ")
            time = md[1].replace(":", "-")
            self.logger.info("time: %s" % time)

            name = time+"-"+self.show_name
            name.encode('utf-8')
            artist = "AIRTIMERECORDERSOURCEFABRIC".encode('utf-8')

            #set some metadata for our file daemon
            recorded_file = mutagen.File(filepath, easy=True)
            recorded_file['title'] = name
            recorded_file['artist'] = artist
            recorded_file['date'] = md[0]
            recorded_file['tracknumber'] = self.show_instance
            recorded_file.save()

        except Exception, e:
            self.logger.error("Exception: %s", e)

    def run(self):
        code, filepath = self.record_show()

        if code == 0:
            try:
                self.logger.info("Preparing to upload %s" % filepath)
    
                self.set_metadata_and_save(filepath)
    
                self.upload_file(filepath)
            except Exceptio, e:
                self.logger.error(e)
        else:
            self.logger.info("problem recording show")
            
class RecordScheduler(Thread):
    def __init__(self, q):
        Thread.__init__(self)
        self.queue = q
        self.shows_to_record = {}
        self.logger = logging.getLogger('root')
    
    def process_shows(self, shows):
        self.logger.info("Processing show schedules...")
        self.shows_to_record = {}
        temp = shows[u'shows']
        for show in temp:
            show_starts = getDateTimeObj(show[u'starts'])
            show_end = getDateTimeObj(show[u'ends'])
            time_delta = show_end - show_starts

            self.shows_to_record[show[u'starts']] = [time_delta, show[u'instance_id'], show[u'name']]
        self.logger.info(self.shows_to_record)
        
    def check_record(self):
        if len(self.shows_to_record) != 0:
            try:
               tnow = datetime.datetime.now()
               sorted_show_keys = sorted(self.shows_to_record.keys())
            
               start_time = sorted_show_keys[0]
               next_show = getDateTimeObj(start_time)
            
               self.logger.debug("Next show %s", next_show)
               self.logger.debug("Now %s", tnow)
            
               delta = next_show - tnow
               min_delta = datetime.timedelta(seconds=5)
            
               if delta <= min_delta:
                   self.logger.debug("sleeping %s seconds until show", delta.seconds)
                   time.sleep(delta.seconds)
            
                   show_length = self.shows_to_record[start_time][0]
                   show_instance = self.shows_to_record[start_time][1]
                   show_name = self.shows_to_record[start_time][2]
            
                   self.sr = ShowRecorder(show_instance, show_name, show_length.seconds, start_time, filetype="mp3")
                   self.sr.start()
            
                   #remove show from shows to record.
                   del self.shows_to_record[start_time]
            except Exception,e :
                self.logger.error(e)
        else:
            self.logger.info("No recording schedule...")
        
        
    def run(self):
        self.logger.info("RecordScheduler started...")
        while True:
            if not self.queue.empty():
                try:
                    self.logger.debug("Received data from command handler")
                    shows = self.queue.get()
                    self.logger.debug('shows %s' % shows)
                    self.process_shows(shows)
                except Exception, e:
                    self.logger.error(e)
            self.check_record()
            time.sleep(1)


if __name__ == '__main__':
    q = Queue()
    
    cl = CommandListener(q)
    cl.start()
    
    rs = RecordScheduler(q) 
    rs.start()
    

