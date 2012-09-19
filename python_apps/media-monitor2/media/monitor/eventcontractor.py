from media.monitor.log    import Loggable
from media.monitor.events import DeleteFile

class EventContractor(Loggable):
    def __init__(self):
        self.store = {}
    def event_registered(self, evt):
        """
        returns true if the event is registered which means that there is
        another "unpacked" event somewhere out there with the same path
        """
        return evt.path in self.store

    def get_old_event(self, evt):
        """
        get the previously registered event with the same path as 'evt'
        """
        return self.store[ evt.path ]

    def register(self, evt):
        if self.event_registered(evt):
            ev_proxy = self.get_old_event(evt)
            if ev_proxy.same_event(evt):
                ev_proxy.merge_proxy(evt)
                return False
            # delete overrides any other event
            elif evt.is_event(DeleteFile):
                ev_proxy.merge_proxy(evt)
                return False
            else:
                ev_proxy.run_hook()
                ev_proxy.reset_hook()

        self.store[ evt.path ] = evt
        evt.set_pack_hook( lambda : self.__unregister(evt) )
        return True

    def __unregister(self, evt):
        del self.store[evt.path]


# Delete this class when done using
class EventContractor2(Loggable):
    """
    This class is responsible for "contracting" events together to ease the
    load on airtime. It does this by morphing old events into newer ones
    """
    def __init__(self):
        self.store = {}

    def event_registered(self, evt):
        """
        returns true if the event is registered which means that there is
        another "unpacked" event somewhere out there with the same path
        """
        return evt.path in self.store

    def get_old_event(self, evt):
        """
        get the previously registered event with the same path as 'evt'
        """
        return self.store[ evt.path ]

    def register(self, evt):
        """
        Returns true if event was actually registered. This means that
        no old events were touched. On the other hand returns false if
        some other event in the storage was morphed into this newer one.
        Which should mean that the old event should be discarded.
        """
        if self.event_registered(evt):
            old_e = self.get_old_event(evt)
            # TODO : Perhaps there are other events that we can "contract"
            # together
            # If two events are of the same type we can safely discard the old
            # one
            if evt.__class__ == old_e.__class__:
                old_e.morph_into(evt)
                return False
            # delete overrides any other event
            elif isinstance(evt, DeleteFile):
                old_e.morph_into(evt)
                return False
            # Unregister the old event anyway, because we only want to keep
            # track of the old one. This means that the old event cannot be
            # morphed again and new events with the same path will only be
            # checked against the newest event 'evt' in this case
            self.unregister( old_e )
        evt.add_safe_pack_hook( lambda : self.__unregister(evt) )

        if evt.path in self.store:
            self.logger.warn("Clean up should have been called by '%s'" %
                    str(evt))
            self.logger.warn("Overwriting event for '%s'" % evt.path)

        self.store[ evt.path ] = evt
        return True # We actually added something, hence we return true.

    def unregister(self, evt):
        evt.reset_hook()

    def __unregister(self, evt):
        try: del self.store[evt.path]
        except KeyError as e:
            self.logger.info("Contractor failed to unrecord event: '%s'" \
                    % evt.path)
        except Exception as e: self.unexpected_exception(e)
