from setuptools import setup
from subprocess import call
import sys
import os

script_path = os.path.dirname(os.path.realpath(__file__))
print script_path
os.chdir(script_path)

# Allows us to avoid installing the upstart init script when deploying on Airtime Pro:
if '--no-init-script' in sys.argv:
    data_files = []
    sys.argv.remove('--no-init-script') # super hax
else:
    pypo_files = []
    for root, dirnames, filenames in os.walk('pypo'):
        for filename in filenames:
            pypo_files.append(os.path.join(root, filename))
        
    data_files = [
                  ('/etc/airtime', ['install/notify_logging.cfg']),
                  ('/etc/airtime', ['install/pypo_logging.cfg']),
                  ('/usr/lib/systemd/system/', ['systemd/system/airtime-liquidsoap.service']),
                  ('/usr/lib/systemd/system/', ['systemd/system/airtime-media-monitor.service']),
                  ('/usr/lib/systemd/system/', ['systemd/system/airtime-playout.service']),
                  ('/usr/lib/tmpfiles.d/', ['systemd/tmpfiles/airtime-liquidsoap.conf']),
                  ('/var/log/airtime/pypo', []),
                  ('/var/log/airtime/pypo-liquidsoap', []),
                  ('/var/tmp/airtime/pypo', []),
                  ('/var/tmp/airtime/pypo/cache', []),
                  ('/var/tmp/airtime/pypo/files', []),
                  ('/var/tmp/airtime/pypo/tmp', []),
                 ]
    print data_files

setup(name='airtime-playout',
      version='1.0',
      description='Airtime Playout Engine',
      url='http://github.com/sourcefabric/Airtime',
      author='sourcefabric',
      license='AGPLv3',
      packages=['pypo', 'pypo.media', 'pypo.media.update',
                'liquidsoap', 'liquidsoap.library'],
      package_data={'': ['*.liq', '*.cfg']},
      scripts=[
          'bin/airtime-playout',
          'bin/airtime-liquidsoap',
          'bin/pyponotify'
      ],
      install_requires=[
          'amqplib',
          'anyjson',
          'argparse',
          'configobj',
          'docopt',
          'kombu',
          'mutagen',
          'poster',
          'PyDispatcher',
          'pyinotify',
          'pytz',
          'requests',
          'wsgiref'
      ],
      zip_safe=False,
      data_files=data_files)

# Reload the initctl config so that playout services works
if data_files:
    print "Reloading initctl configuration"
    #call(['initctl', 'reload-configuration'])
    print "Run \"service airtime-playout start\" and \"service airtime-liquidsoap start\""
