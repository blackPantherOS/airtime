#!/bin/sh
recorder_user="pypo"
export HOME="/var/tmp/airtime/show-recorder/"
export TERM=xterm

# Location of pypo_cli.py Python script
recorder_path="/usr/lib/airtime/show-recorder/bin/"
recorder_script="testrecordscript.py"

api_client_path="/usr/lib/airtime/pypo/"
cd ${recorder_path}

echo "*** Daemontools: starting daemon"
exec 2>&1
# Note the -u when calling python! we need it to get unbuffered binary stdout and stderr

sudo PYTHONPATH=${api_client_path} -u ${recorder_user} python -u ${recorder_path}${recorder_script}
# EOF
