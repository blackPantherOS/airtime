import logging
from api_clients import api_client as apc
import json
import shutil
import commands
import os
import sys
from configobj import ConfigObj
import subprocess
import traceback

# create logger
logger = logging.getLogger()

# no logging
ch = logging.StreamHandler()
logging.disable(50)

# add ch to logger
logger.addHandler(ch)

if (os.geteuid() != 0):
    print 'Must be a root user.'
    sys.exit()

# loading config file
try:
    config = ConfigObj('/etc/airtime/media-monitor.cfg')
except Exception, e:
    print('Error loading config file: %s', e)
    sys.exit()

api_client = apc.AirtimeApiClient(config)

try:
    # keep getting few rows at a time for current music_dir (stor
    # or watched folder).
    subtotal = 0
    while True:
        # return a list of pairs where the first value is the
        # file's database row id and the second value is the
        # filepath
        files = api_client.get_files_without_silan_value()
        total_files = len(files)
        if total_files == 0: break
        processed_data = []
        total = 0
        for f in files:
            full_path = f['fp']
            # silence detect(set default queue in and out)
            try:
                command = ['silan', '-f', 'JSON', full_path]
                proc = subprocess.Popen(command, stdout=subprocess.PIPE)
                out = proc.stdout.read()
                info = json.loads(out)
                data = {}
                data['cuein'] = str('{0:f}'.format(info['sound'][0][0]))
                data['cueout'] = str('{0:f}'.format(info['sound'][-1][1]))
                processed_data.append((f['id'], data))
                total += 1
                if (total % 5 == 0):
                    print "Total %s / %s files has been processed.." % (total, total_files)
            except Exception, e:
                print e
                print traceback.format_exc()
                break
        print "Processed: %d songs" % total
        subtotal += total
        total = 0
        try:
            print api_client.update_cue_values_by_silan(processed_data)
        except Exception ,e:
            print e
            print traceback.format_exc()
    print "Total %d songs Processed" % subtotal

except Exception, e:
    print e
    print traceback.format_exc()

#update_cue_values_by_silan
