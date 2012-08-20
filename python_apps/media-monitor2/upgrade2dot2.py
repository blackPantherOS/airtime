import os
from configobj import ConfigObj
import traceback

def upgrade(source, destination):
    """
    Must be ran as sudo. will do upgrade of configuration files by filling in
    missing values according to upgrade_data
    """
    if not os.path.exists(source):
        print("Cannot upgrade '%s'. Skipping this file" % source)
        return
    try:
        cfg_source, cfg_dest = ConfigObj(source), ConfigObj(destination)
        for key, val in cfg_source.iteritems():
            if key not in cfg_dest: cfg_dest[key] = val
        cfg_dest.write()
    except Exception:
        print("Error upgrading")
        print( traceback.format_exc() )
