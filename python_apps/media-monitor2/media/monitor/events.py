# -*- coding: utf-8 -*-
import os
import mutagen
import abc
from media.monitor.exceptions import BadSongFile
from media.monitor.pure import LazyProperty

class PathChannel(object):
    """a dumb struct; python has no record types"""
    def __init__(self, signal, path):
        self.signal = signal
        self.path = path

# It would be good if we could parameterize this class by the attribute
# that would contain the path to obtain the meta data. But it would be too much
# work for little reward
class HasMetaData(object):
    # TODO : add documentation for HasMetaData
    __metaclass__ = abc.ABCMeta
    # doing weird bullshit here because python constructors only
    # call the constructor of the leftmost superclass.
    @LazyProperty
    def metadata(self):
        # Normally this would go in init but we don't like
        # relying on consumers of this behaviour to have to call
        # the constructor
        try: f  = mutagen.File(self.path, easy=True)
        except Exception: raise BadSongFile(self.path)
        metadata = {}
        for k,v in f:
            # Special handling of attributes here
            if isinstance(v, list):
                if len(v) == 1: metadata[k] = v[0]
                else: raise Exception("Weird mutagen %s:%s" % (k,str(v)))
            else: metadata[k] = v
        return metadata

class BaseEvent(object):
    __metaclass__ = abc.ABCMeta
    def __init__(self, raw_event):
        # TODO : clean up this idiotic hack
        # we should use keyword constructors instead of this behaviour checking
        # bs to initialize BaseEvent
        if hasattr(raw_event,"pathname"):
            self.__raw_event = raw_event
            self.path = os.path.normpath(raw_event.pathname)
        else: self.path = raw_event
    def exists(self): return os.path.exists(self.path)
    def __str__(self):
        return "Event. Path: %s" % self.__raw_event.pathname

class OrganizeFile(BaseEvent, HasMetaData):
    def __init__(self, *args, **kwargs): super(OrganizeFile, self).__init__(*args, **kwargs)
class NewFile(BaseEvent, HasMetaData):
    def __init__(self, *args, **kwargs): super(NewFile, self).__init__(*args, **kwargs)
class DeleteFile(BaseEvent):
    def __init__(self, *args, **kwargs): super(DeleteFile, self).__init__(*args, **kwargs)
