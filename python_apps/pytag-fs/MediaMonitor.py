#!/usr/local/bin/python
import logging
import logging.config
import json
import time
import datetime
import os
import sys
import hashlib

from subprocess import Popen, PIPE, STDOUT

from configobj import ConfigObj

import pyinotify
from pyinotify import WatchManager, Notifier, ProcessEvent

from api_clients import api_client

# configure logging
try:
    logging.config.fileConfig("logging.cfg")
except Exception, e:
    print 'Error configuring logging: ', e
    sys.exit()

# loading config file
try:
    config = ConfigObj('/etc/airtime/MediaMonitor.cfg')
except Exception, e:
    print 'Error loading config file: ', e
    sys.exit()

class MediaMonitor(ProcessEvent):

    def my_init(self):
        """
        Method automatically called from ProcessEvent.__init__(). Additional
        keyworded arguments passed to ProcessEvent.__init__() are then
        delegated to my_init().
        """
        self.api_client = api_client.api_client_factory(config)

    def process_IN_CREATE(self, event):
        if not event.dir :
            #This is a newly imported file.
            print "%s: %s" %  (event.maskname, os.path.join(event.path, event.name))

    #event.path : /srv/airtime/stor/bd2
    #event.name : bd2aa73b58d9c8abcced989621846e99.mp3
    #event.pathname : /srv/airtime/stor/bd2/bd2aa73b58d9c8abcced989621846e99.mp3
    def process_IN_MODIFY(self, event):
        if not event.dir :
            p = Popen(["pytags", event.pathname], stdout=PIPE, stderr=STDOUT)
            output = p.stdout.read().decode("utf-8").strip()

            #get md5, most likely different
            f = file(event.pathname, 'rb')
            m = hashlib.md5()
            m.update(f.read())
            md5 = m.hexdigest()

            md = {'filepath':event.pathname, 'md5':md5}
            for tag in output.split("\n")[2:] :
                key,value = tag.split("=")
                md[key] = value

            data = {'md': md}

            response = self.api_client.update_media_metadata(data)

        print "%s: path: %s name: %s" %  (event.maskname, event.path, event.name)

    def process_default(self, event):
        print "%s: %s" %  (event.maskname, os.path.join(event.path, event.name))

if __name__ == '__main__':

    print 'Media Monitor'

    try:
        # watched events
        mask = pyinotify.IN_CREATE | pyinotify.IN_MODIFY

        wm = WatchManager()
        wdd = wm.add_watch('/srv/airtime/stor', mask, rec=True, auto_add=True)

        notifier = Notifier(wm, MediaMonitor(), read_freq=10, timeout=1)
        notifier.coalesce_events()
        notifier.loop()
    except KeyboardInterrupt:
        notifier.stop()


