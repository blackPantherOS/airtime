#!/bin/bash
#-------------------------------------------------------------------------------
#   Copyright (c) 2010 Sourcefabric O.P.S.
#
#   This file is part of the Campcaster project.
#   http://campcaster.sourcefabric.org/
#
#   Campcaster is free software; you can redistribute it and/or modify
#   it under the terms of the GNU General Public License as published by
#   the Free Software Foundation; either version 2 of the License, or
#   (at your option) any later version.
#
#   Campcaster is distributed in the hope that it will be useful,
#   but WITHOUT ANY WARRANTY; without even the implied warranty of
#   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#   GNU General Public License for more details.
#
#   You should have received a copy of the GNU General Public License
#   along with Campcaster; if not, write to the Free Software
#   Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
#
#-------------------------------------------------------------------------------                                                                                
#-------------------------------------------------------------------------------
#  This script dumps the schema of the Campcaster database.
#
#  To get usage help, try the -h option
#-------------------------------------------------------------------------------

#-------------------------------------------------------------------------------
#  Determine directories, files
#-------------------------------------------------------------------------------

reldir=`dirname $0`/..
phpdir=ls_storageAdmin_phppart_dir
if [ "$phpdir" == "ls_storageAdmin_phppart_dir" ]
then
    phpdir=`cd $reldir/var; pwd`
fi
filelistpathname=.

#-------------------------------------------------------------------------------
#  Print the usage information for this script.
#-------------------------------------------------------------------------------
printUsage()
{
    echo "This script dumps the schema of the Campcaster database.";
    echo "parameters:";
    echo "";
    echo "  -h, --help          Print this message and exit.";
    echo "";
}

#-------------------------------------------------------------------------------
#  Process command line parameters
#-------------------------------------------------------------------------------
CMD=${0##*/}

opts=$(getopt -o h -l help -n $CMD -- "$@") || exit 1
eval set -- "$opts"
while true; do
    case "$1" in
        -h|--help)
            printUsage;
            exit 0;;
        --)
            shift;
            break;;
        *)
            echo "Unrecognized option $1.";
            printUsage;
            exit 1;
    esac
done

#-------------------------------------------------------------------------------
#   Do the schema dump
#-------------------------------------------------------------------------------

cd $phpdir
php -q dumpDbSchema.php

#-------------------------------------------------------------------------------
#   Say goodbye
#-------------------------------------------------------------------------------
echo "-- End of dump."
