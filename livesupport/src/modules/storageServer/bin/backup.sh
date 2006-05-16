#!/bin/bash

# param $1: workdir what we would like to tar
# param $2: output file: the .tar file
# param $3: statusfile
echo "==>"
date +\ %Y%m%d\ %H:%M:%S
echo "backup2.sh: create tarball $1 to $2"
echo "backup2.sh: status: #$3#"
echo "<=="
echo -n "working" > $3;
touch $2 || { echo -n "fail" > $3; exit 1; }
#sleep 120
cd $1
tar cf $2 * || { echo -n "fail" > $3; exit 1; }
chmod 666 $2

echo -n "success" > $3
