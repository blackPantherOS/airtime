"""
Python part of radio playout (pypo)
"""

import time
from optparse import *
import sys
import signal
import logging
import logging.config
import logging.handlers
import locale
import os
from Queue import Queue

from threading import Lock

from pypopush import PypoPush
from pypofetch import PypoFetch
from pypofile import PypoFile
from recorder import Recorder
from pypomessagehandler import PypoMessageHandler

from configobj import ConfigObj

# custom imports
from api_clients import api_client
from std_err_override import LogWriter

PYPO_VERSION = '1.1'

# Set up command-line options
parser = OptionParser()

# help screen / info
usage = "%prog [options]" + " - python playout system"
parser = OptionParser(usage=usage)

# Options
parser.add_option("-v", "--compat", help="Check compatibility with server API version", default=False, action="store_true", dest="check_compat")

parser.add_option("-t", "--test", help="Do a test to make sure everything is working properly.", default=False, action="store_true", dest="test")
parser.add_option("-b", "--cleanup", help="Cleanup", default=False, action="store_true", dest="cleanup")
parser.add_option("-c", "--check", help="Check the cached schedule and exit", default=False, action="store_true", dest="check")

# parse options
(options, args) = parser.parse_args()


#need to wait for Python 2.7 for this..
#logging.captureWarnings(True)

def configure_locale():
    current_locale = locale.getlocale()
    
    if current_locale[1] is None:
        logger.debug("No locale currently set. Attempting to get default locale.")
        default_locale = locale.getdefaultlocale()
        
        if default_locale[1] is None:
            logger.debug("No default locale exists. Let's try loading from /etc/default/locale")
            if os.path.exists("/etc/default/locale"):
                config = ConfigObj('/etc/default/locale')
                lang = config.get('LANG')
                new_locale = lang
            else:
                logger.error("/etc/default/locale could not be found! Please run 'sudo update-locale' from command-line.")
                sys.exit(1)
        else:
            new_locale = default_locale
            
        logger.debug("New locale set to: " + locale.setlocale(locale.LC_ALL, new_locale))
            
    
    current_locale_encoding = locale.getlocale()[1].lower()
    
    if current_locale_encoding not in ['utf-8', 'utf8']:
        logger.error("Need a UTF-8 locale. Currently '%s'. Exiting..." % current_locale_encoding)

# configure logging
try:
    logging.config.fileConfig("logging.cfg")
    logger = logging.getLogger()
    LogWriter.override_std_err(logger)
except Exception, e:
    print "Couldn't configure logging"
    sys.exit()
    
configure_locale()

# loading config file
try:
    config = ConfigObj('/etc/airtime/pypo.cfg')
except Exception, e:
    logger.error('Error loading config file: %s', e)
    sys.exit()

class Global:
    def __init__(self):
        self.api_client = api_client.api_client_factory(config)
        
    def selfcheck(self):
        self.api_client = api_client.api_client_factory(config)
        return self.api_client.is_server_compatible()
        
    def test_api(self):
        self.api_client.test()

"""
    def check_schedule(self):
        logger = logging.getLogger()

        try:
            schedule_file = open(self.schedule_file, "r")
            schedule = pickle.load(schedule_file)
            schedule_file.close()

        except Exception, e:
            logger.error("%s", e)
            schedule = None

        for pkey in sorted(schedule.iterkeys()):
            playlist = schedule[pkey]
            print '*****************************************'
            print '\033[0;32m%s %s\033[m' % ('scheduled at:', str(pkey))
            print 'cached at :   ' + self.cache_dir + str(pkey)
            print 'played:       ' + str(playlist['played'])
            print 'schedule id:  ' + str(playlist['schedule_id'])
            print 'duration:     ' + str(playlist['duration'])
            print 'source id:    ' + str(playlist['x_ident'])
            print '-----------------------------------------'

            for media in playlist['medias']:
                print media
"""

def keyboardInterruptHandler(signum, frame):
    logger = logging.getLogger()
    logger.info('\nKeyboard Interrupt\n')
    sys.exit(0)


if __name__ == '__main__':
    logger = logging.getLogger()
    logger.info('###########################################')
    logger.info('#             *** pypo  ***               #')
    logger.info('#   Liquidsoap Scheduled Playout System   #')
    logger.info('###########################################')

    signal.signal(signal.SIGINT, keyboardInterruptHandler)

    # initialize
    g = Global()

    while not g.selfcheck(): time.sleep(5)
    
    logger = logging.getLogger()

    if options.test:
        g.test_api()
        sys.exit()

    api_client = api_client.api_client_factory(config)
    api_client.register_component("pypo")

    pypoFetch_q = Queue()
    recorder_q = Queue()
    pypoPush_q = Queue()
    
    telnet_lock = Lock()
    
    """
    This queue is shared between pypo-fetch and pypo-file, where pypo-file
    is the receiver. Pypo-fetch will send every schedule it gets to pypo-file
    and pypo will parse this schedule to determine which file has the highest
    priority, and will retrieve it.
    """
    media_q = Queue()
    
    pmh = PypoMessageHandler(pypoFetch_q, recorder_q)
    pmh.daemon = True
    pmh.start()
    
    pfile = PypoFile(media_q)
    pfile.daemon = True
    pfile.start()
    
    pf = PypoFetch(pypoFetch_q, pypoPush_q, media_q, telnet_lock)
    pf.daemon = True
    pf.start()
    
    pp = PypoPush(pypoPush_q, telnet_lock)
    pp.daemon = True
    pp.start()

    recorder = Recorder(recorder_q)
    recorder.daemon = True
    recorder.start()

    # all join() are commented out becase we want to exit entire pypo
    # if pypofetch is exiting 
    #pmh.join()
    #recorder.join()
    #pp.join()
    pf.join()
    
    logger.info("pypo fetch exit")
    sys.exit()
"""
    if options.check:
        try: g.check_schedule()
        except Exception, e:
            print e

    if options.cleanup:
        try: pf.cleanup('scheduler')
        except Exception, e:
            print e
"""
