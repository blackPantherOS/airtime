#!/usr/local/bin/python
import sys
import os
import logging
from configobj import ConfigObj
from optparse import OptionParser, OptionValueError
from api_clients import api_client
import json
import shutil

# create logger
logger = logging.getLogger()

# no logging
ch = logging.StreamHandler()
logging.disable(50)

# add ch to logger
logger.addHandler(ch)


# loading config file
try:
    config = ConfigObj('/etc/airtime/media-monitor.cfg')
except Exception, e:
    print('Error loading config file: %s', e)
    sys.exit()

api_client = api_client.api_client_factory(config)

#helper functions
# copy or move files
# flag should be 'copy' or 'move'
def copy_or_move_files_to(paths, dest, flag):
    for path in paths:
        if(os.path.exists(path)):
            if(os.path.isdir(path)):
                #construct full path
                sub_path = []
                for temp in os.listdir(path):
                    sub_path.append(path+temp)
                copy_or_move_files_to(sub_path, dest, flag)
            elif(os.path.isfile(path)):
                #copy file to dest
                ext = os.path.splitext(path)[1]
                if( 'mp3' in ext or 'ogg' in ext ):
                    destfile = dest+os.path.basename(path)
                    if(flag == 'copy'):
                        print "Copying %(src)s to %(dest)s....." % {'src':path, 'dest':destfile}
                        shutil.copy2(path, destfile)
                    elif(flag == 'move'):
                        print "Moving %(src)s to %(dest)s....." % {'src':path, 'dest':destfile}
                        shutil.move(path, destfile)
        else:
            print "Cannot find file or path: %s" % path
            
def helper_get_stor_dir():
    res = api_client.list_all_watched_dirs()
    if(res is None):
        return res
    else:
        if(res['dirs']['1'][-1] != '/'):
            out = res['dirs']['1']+'/'
            return out
        else:
            return res['dirs']['1']

def checkOtherOption(args):
    for i in args:
        if('-' in i):
            return True
    
def errorIfMultipleOption(args):
    if(checkOtherOption(args)):
        raise OptionValueError("This option cannot be combined with other options")
    
def printHelp():
    storage_dir = helper_get_stor_dir()
    if(storage_dir is None):
        storage_dir = "Unknown" 
    else:
        storage_dir += "imported/"
    print """
    ========================
     Airtime Import Script
    ========================
    There are two ways to import audio files into Airtime:

    1) Copy or move files into the storage folder

       Copied or moved files will be placed into the folder:
       %s
        
       Files will be automatically organized into the structure
       "Artist/Album/TrackNumber-TrackName-Bitrate.file_extension".

    2) Add a folder to the Airtime library("watch" a folder)
    
       All the files in the watched folder will be imported to Airtime and the
       folder will be monitored to automatically detect any changes. Hence any
       changes done in the folder(add, delete, edit a file) will trigger 
       updates in Airtime libarary.
       """ % storage_dir
    parser.print_help()
    print ""

def CopyAction(option, opt, value, parser):
    errorIfMultipleOption(parser.rargs)
    stor = helper_get_stor_dir()
    if(stor is None):
        exit("Unable to connect to the server.")
    dest = stor+"organize/"
    copy_or_move_files_to(parser.rargs, dest, 'copy')

def MoveAction(option, opt, value, parser):
    errorIfMultipleOption(parser.rargs)
    stor = helper_get_stor_dir()
    if(stor is None):
        exit("Unable to connect to the server.")
    dest = stor+"organize/"
    copy_or_move_files_to(parser.rargs, dest, 'move')

def WatchAddAction(option, opt, value, parser):
    errorIfMultipleOption(parser.rargs)
    if(len(parser.rargs) > 1):
        raise OptionValueError("Too many arguments. This option need exactly one argument.")
    path = parser.rargs[0]
    if(os.path.isdir(path)):
        res = api_client.add_watched_dir(path)
        if(res is None):
            exit("Unable to connect to the server.")
        # sucess
        if(res['msg']['code'] == 0):
            print "%s added to watched folder list successfully" % path
        else:
            print "Adding a watched folder failed. : %s" % res['msg']['error']
    else:
        print "Given path is not a directory: %s" % path

def WatchListAction(option, opt, value, parser):
    errorIfMultipleOption(parser.rargs)
    if(len(parser.rargs) > 0):
        raise OptionValueError("This option doesn't take any argument.")
    res = api_client.list_all_watched_dirs()
    if(res is None):
        exit("Unable to connect to the server.")
    dirs = res["dirs"].items()
    # there will be always 1 which is storage folder
    if(len(dirs) == 1):
            print "No watch folders found"
    else:
        for key, v in dirs:
            if(key != '1'):
                print v

def WatchRemoveAction(option, opt, value, parser):
    errorIfMultipleOption(parser.rargs)
    if(len(parser.rargs) > 1):
        raise OptionValueError("Too many arguments. This option need exactly one argument.")
    path = parser.rargs[0]
    if(os.path.isdir(path)):
        res = api_client.remove_watched_dir(path)
        if(res is None):
            exit("Unable to connect to the server.")
        # sucess
        if(res['msg']['code'] == 0):
            print "%s removed from watched folder list successfully" % path
        else:
            print "Removing a watched folder failed. : %s" % res['msg']['error']
    else:
        print "Given path is not a directory: %s" % path
        
def StorageSetAction(option, opt, value, parser):
    errorIfMultipleOption(parser.rargs)
    if(len(parser.rargs) > 1):
        raise OptionValueError("Too many arguments. This option need exactly one argument.")
    if(os.path.isdir(values)):
        res = api_client.set_storage_dir(values)
        if(res is None):
            exit("Unable to connect to the server.")
        # sucess
        if(res['msg']['code'] == 0):
            print "Successfully set storage folder to %s" % values
        else:
            print "Setting storage folder to failed.: %s" % res['msg']['error']
    else:
        print "Given path is not a directory: %s" % values
def StorageGetAction(option, opt, value, parser):
    errorIfMultipleOption(parser.rargs)
    if(len(parser.rargs) > 0):
        raise OptionValueError("This option doesn't take any argument.")
    print helper_get_stor_dir()

parser = OptionParser(add_help_option=False)
parser.add_option('-c','--copy', action='callback', callback=CopyAction, metavar='FILE', help='Copy FILE(s) into the storage directory.\nYou can specify multiple files or directories.')
parser.add_option('-m','--move', action='callback', callback=MoveAction, metavar='FILE', help='Move FILE(s) into the storage directory.\nYou can specify multiple files or directories.')
parser.add_option('--watch-add', action='callback', callback=WatchAddAction, help='Add DIR to the watched folders list.')
parser.add_option('--watch-list', action='callback', callback=WatchListAction, help='Show the list of folders that are watched.')
parser.add_option('--watch-remove', action='callback', callback=WatchRemoveAction, help='Remove DIR from the watched folders list.')
parser.add_option('--storage-dir-set', action='callback', callback=StorageSetAction, help='Set storage dir to DIR.')
parser.add_option('--storage-dir-get', action='callback', callback=StorageGetAction, help='Show the current storage dir.')
parser.add_option('-h', '--help', dest='help', action='store_true', help='show this help message and exit')

if('-l' in sys.argv or '--link' in sys.argv):
    print "\nThe [-l][--link] option is deprecated. Please use the --watch-add option.\nTry 'airtime-import -h' for more detail.\n"
    sys.exit()
if('-h' in sys.argv):
    printHelp()
    sys.exit()
if(len(sys.argv) == 1):
    printHelp()
    sys.exit()
    
(option, args) = parser.parse_args()
if option.help:
    printHelp()
    sys.exit()




