import os
from pydispatch import dispatcher
from media.monitor.events import NewFile, DeleteFile
from media.monitor.log import Loggable
import media.monitor.pure as mmp

class Bootstrapper(Loggable):
    """
    Bootstrapper reads all the info in the filesystem flushes organize
    events and watch events
    """
    def __init__(self,db,last_ran,org_channels,watch_channels):
        self.db = db
        self.org_channels = org_channels
        self.watch_channels = watch_channels
        self.last_ran = last_ran

    def flush_watch(self):
        """
        Syncs the file system into the database. Walks over deleted/new/modified files since
        the last run in mediamonitor and sends requests to make the database consistent with
        file system
        """
        # Songs is a dictionary where every key is the watched the directory
        # and the value is a set with all the files in that directory.
        songs = {}
        modded = deleted = 0
        signal_by_path = dict( (pc.signal, pc.path) for pc in self.watch_channels )
        for pc in self.watch_channels:
            songs[ pc.path ] = set()
            for f in mmp.walk_supported(pc.path, clean_empties=False):
                songs[ pc.path ].add(f)
                # We decide whether to update a file's metadata by checking
                # its system modification date. If it's above the value
                # self.last_ran which is passed to us that means media monitor
                # wasn't aware when this changes occured in the filesystem
                # hence it will send the correct events to sync the database
                # with the filesystem
                if os.path.getmtime(f) > self.last_ran:
                    modded += 1
                    dispatcher.send(signal=pc.signal, sender=self, event=DeleteFile(f))
                    dispatcher.send(signal=pc.signal, sender=self, event=NewFile(f))
        # Want all files in the database that are not in the filesystem
        for watch_dir in self.db.list_directories():
            db_songs = self.db.directory_get_files(watch_dir)
            # Get all the files that are in the database but in the file
            # system. These are the files marked for deletions
            for to_delete in db_songs.difference(songs[watch_dir]):
                # need the correct watch channel signal to call delete
                if watch_dir in signal_by_path:
                    dispatcher.send(signal=signal_by_path[watch_dir], sender=self, event=DeleteFile(f))
                    # TODO : get rid of this, we should never delete files from
                    # the FS even if they are deleted in airtime. Instead we
                    # should put this file on a global ignore list until it's
                    # re-added or something
                    # os.remove(to_delete)
                    deleted += 1
                else:
                    self.logger.error("Could not find the signal corresponding to path: '%s'" % watch_dir)
        self.logger.info( "Flushed watch directories. (modified, deleted) = (%d, %d)"
                         % (modded, deleted) )


