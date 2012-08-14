# -*- coding: utf-8 -*-
import os
import abc
from media.monitor.pure import LazyProperty
from media.monitor.monitor import Metadata

class PathChannel(object):
    """a dumb struct; python has no record types"""
    def __init__(self, signal, path):
        self.signal = signal
        self.path = path

# It would be good if we could parameterize this class by the attribute
# that would contain the path to obtain the meta data. But it would be too much
# work
class HasMetaData(object):
    __metaclass__ = abc.ABCMeta
    @LazyProperty
    def metadata(self):
        return Metadata(self.path)

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
