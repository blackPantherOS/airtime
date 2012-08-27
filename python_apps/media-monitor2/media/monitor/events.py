# -*- coding: utf-8 -*-
import os
import abc
import media.monitor.pure   as mmp
import media.monitor.owners as owners
from media.monitor.pure       import LazyProperty
from media.monitor.metadata   import Metadata
from media.monitor.log        import Loggable
from media.monitor.exceptions import BadSongFile

class PathChannel(object):
    def __init__(self, signal, path):
        self.signal = signal
        self.path   = path

# TODO : Move this to it's file. Also possible unsingleton and use it as a
# simple module just like m.m.owners
class EventRegistry(object):
    """
    This class's main use is to keep track all events with a cookie attribute.
    This is done mainly because some events must be 'morphed' into other events
    because we later detect that they are move events instead of delete events.
    """
    registry = {}
    @staticmethod
    def register(evt): EventRegistry.registry[evt.cookie] = evt
    @staticmethod
    def unregister(evt): del EventRegistry.registry[evt.cookie]
    @staticmethod
    def registered(evt): return evt.cookie in EventRegistry.registry
    @staticmethod
    def matching(evt):
        event = EventRegistry.registry[evt.cookie]
        # Want to disallow accessing the same event twice
        EventRegistry.unregister(event)
        return event
    def __init__(self,*args,**kwargs):
        raise Exception("You can instantiate this class. Must only use class \
                         methods")

class HasMetaData(object):
    """
    Any class that inherits from this class gains the metadata attribute that
    loads metadata from the class's 'path' attribute. This is done lazily so
    there is no performance penalty to inheriting from this and subsequent
    calls to metadata are cached
    """
    __metaclass__ = abc.ABCMeta
    @LazyProperty
    def metadata(self): return Metadata(self.path)

class BaseEvent(Loggable):
    __metaclass__ = abc.ABCMeta
    def __init__(self, raw_event):
        # TODO : clean up this idiotic hack
        # we should use keyword constructors instead of this behaviour checking
        # bs to initialize BaseEvent
        if hasattr(raw_event,"pathname"):
            self._raw_event = raw_event
            self.path = os.path.normpath(raw_event.pathname)
        else: self.path = raw_event
        self.owner = owners.get_owner()
        self._pack_hook = lambda: None # no op
        # into another event

    def reset_hook(self):
        self._pack_hook()
        self._pack_hook = lambda: None

    def exists(self): return os.path.exists(self.path)

    @LazyProperty
    def cookie(self): return getattr( self._raw_event, 'cookie', None )

    def __str__(self):
        return "Event(%s). Path(%s)" % ( self.path, self.__class__.__name__)

    def add_safe_pack_hook(self,k):
        """
        adds a callable object (function) that will be called after the event
        has been "safe_packed"
        """
        self._pack_hook = k

    # As opposed to unsafe_pack...
    def safe_pack(self):
        """
        returns exceptions instead of throwing them to be consistent with
        events that must catch their own BadSongFile exceptions since generate
        a set of exceptions instead of a single one
        """
        # pack will only throw an exception if it processes one file but this
        # is a little bit hacky
        try:
            self._pack_hook()
            ret = self.pack()
            owners.remove_file_owner(self.path)
            return ret
        except BadSongFile as e: return [e]

    # nothing to see here, please move along
    def morph_into(self, evt):
        self.logger.info("Morphing %s into %s" % ( str(self), str(evt) ) )
        self._raw_event   = evt
        self.path         = evt.path
        self.__class__    = evt.__class__
        # We don't transfer the _pack_hook over to the new event
        return self

    def assign_owner(self,req):
        """
        Packs self.owner to req if the owner is valid. I.e. it's not -1. This
        method is used by various events that would like to pass owner as a
        parameter. NewFile for example.
        """
        if self.owner != -1: req['MDATA_KEY_OWNER_ID']

class FakePyinotify(object):
    """
    sometimes we must create our own pyinotify like objects to
    instantiate objects from the classes below whenever we want to turn
    a single event into multiple events
    """
    def __init__(self, path): self.pathname = path

class OrganizeFile(BaseEvent, HasMetaData):
    def __init__(self, *args, **kwargs):
        super(OrganizeFile, self).__init__(*args, **kwargs)
    def pack(self):
        raise AttributeError("You can't send organize events to airtime!!!")

class NewFile(BaseEvent, HasMetaData):
    """
    NewFile events are the only events that contain MDATA_KEY_OWNER_ID metadata
    in them.
    """
    def __init__(self, *args, **kwargs):
        super(NewFile, self).__init__(*args, **kwargs)
    def pack(self):
        """
        packs turns an event into a media monitor request
        """
        req_dict = self.metadata.extract()
        req_dict['mode'] = u'create'
        self.assign_owner(req_dict)
        req_dict['MDATA_KEY_FILEPATH'] = unicode( self.path )
        return [req_dict]

class DeleteFile(BaseEvent):
    """
    DeleteFile event only contains the path to be deleted. No other metadata
    can be or is included.  (This is because this event is fired after the
    deletion occurs).
    """
    def __init__(self, *args, **kwargs):
        super(DeleteFile, self).__init__(*args, **kwargs)
    def pack(self):
        req_dict = {}
        req_dict['mode'] = u'delete'
        req_dict['MDATA_KEY_FILEPATH'] = unicode( self.path )
        return [req_dict]

class MoveFile(BaseEvent, HasMetaData):
    """
    Path argument should be the new path of the file that was moved
    """
    def __init__(self, *args, **kwargs):
        super(MoveFile, self).__init__(*args, **kwargs)
    def pack(self):
        req_dict = {}
        req_dict['mode'] = u'moved'
        req_dict['MDATA_KEY_MD5'] = self.metadata.extract()['MDATA_KEY_MD5']
        req_dict['MDATA_KEY_FILEPATH'] = unicode( self.path )
        return [req_dict]

class ModifyFile(BaseEvent, HasMetaData):
    def __init__(self, *args, **kwargs):
        super(ModifyFile, self).__init__(*args, **kwargs)
    def pack(self):
        req_dict = self.metadata.extract()
        req_dict['mode'] = u'modify'
        # path to directory that is to be removed
        req_dict['MDATA_KEY_FILEPATH'] = unicode( self.path )
        return [req_dict]

def map_events(directory, constructor):
    """
    Walks 'directory' and creates an event using 'constructor'. Returns a list
    of the constructed events.
    """
    # -unknown-path should not appear in the path here but more testing
    # might be necessary
    for f in mmp.walk_supported(directory, clean_empties=False):
        try:
            for e in constructor( FakePyinotify(f) ).pack(): yield e
        except BadSongFile as e: yield e

class DeleteDir(BaseEvent):
    """
    A DeleteDir event unfolds itself into a list of DeleteFile events for every
    file in the directory.
    """
    def __init__(self, *args, **kwargs):
        super(DeleteDir, self).__init__(*args, **kwargs)
    def pack(self):
        return map_events( self.path, DeleteFile )

class MoveDir(BaseEvent):
    """
    A MoveDir event unfolds itself into a list of MoveFile events for every
    file in the directory.
    """
    def __init__(self, *args, **kwargs):
        super(MoveDir, self).__init__(*args, **kwargs)
    def pack(self):
        return map_events( self.path, MoveFile )

class DeleteDirWatch(BaseEvent):
    """
    Deleting a watched directory is different from deleting any other
    directory.  Hence we must have a separate event to handle this case
    """
    def __init__(self, *args, **kwargs):
        super(DeleteDirWatch, self).__init__(*args, **kwargs)
    def pack(self):
        req_dict = {}
        req_dict['mode']               = u'delete_dir'
        req_dict['MDATA_KEY_FILEPATH'] = unicode( self.path + "/" )
        return [req_dict]

