# -*- coding: utf-8 -*-
from kombu.messaging import Exchange, Queue, Consumer
from kombu.connection import BrokerConnection
import json
import os
import time
import copy

from media.monitor.exceptions import BadSongFile
from media.monitor.metadata import Metadata
from media.monitor.log import Loggable
from media.monitor.syncdb import SyncDB
from media.monitor.exceptions import DirectoryIsNotListed
from media.monitor.bootstrap import Bootstrapper
from media.monitor.listeners import FileMediator

from api_clients import api_client as apc

# Do not confuse with media monitor 1's AirtimeNotifier class that more related
# to pyinotify's Notifier class. AirtimeNotifier just notifies when events come
# from Airtime itself. I.E. changes made in the web UI that must be updated
# through media monitor

class AirtimeNotifier(Loggable):
    """
    AirtimeNotifier is responsible for interecepting RabbitMQ messages and feeding them to the
    event_handler object it was initialized with. The only thing it does to the messages is parse
    them from json
    """
    def __init__(self, cfg, message_receiver):
        self.cfg = cfg
        try:
            self.handler = message_receiver
            self.logger.info("Initializing RabbitMQ message consumer...")
            schedule_exchange = Exchange("airtime-media-monitor", "direct", durable=True, auto_delete=True)
            schedule_queue = Queue("media-monitor", exchange=schedule_exchange, key="filesystem")
            self.connection = BrokerConnection(cfg["rabbitmq_host"], cfg["rabbitmq_user"],
                    cfg["rabbitmq_password"], cfg["rabbitmq_vhost"])
            channel = self.connection.channel()
            consumer = Consumer(channel, schedule_queue)
            consumer.register_callback(self.handle_message)
            consumer.consume()
            self.logger.info("Initialized RabbitMQ consumer.")
        except Exception as e:
            self.logger.info("Failed to initialize RabbitMQ consumer")
            self.logger.error(e)

    def handle_message(self, body, message):
        """
        Messages received from RabbitMQ are handled here. These messages
        instruct media-monitor of events such as a new directory being watched,
        file metadata has been changed, or any other changes to the config of
        media-monitor via the web UI.
        """
        message.ack()
        self.logger.info("Received md from RabbitMQ: %s" % str(body))
        m = json.loads(message.body)
        self.handler.message(m)


class AirtimeMessageReceiver(Loggable):
    def __init__(self, cfg, manager):
        self.dispatch_table = {
                'md_update' : self.md_update,
                'new_watch' : self.new_watch,
                'remove_watch' : self.remove_watch,
                'rescan_watch' : self.rescan_watch,
                'change_stor' : self.change_storage,
                'file_delete' : self.file_delete,
        }
        self.cfg = cfg
        self.manager = manager
    def message(self, msg):
        """
        This method is called by an AirtimeNotifier instance that consumes the Rabbit MQ events
        that trigger this. The method return true when the event was executed and false when it
        wasn't
        """
        msg = copy.deepcopy(msg)
        if msg['event_type'] in self.dispatch_table:
            evt = msg['event_type']
            del msg['event_type']
            self.logger.info("Handling RabbitMQ message: '%s'" % evt)
            self._execute_message(evt,msg)
            return True
        else:
            self.logger.info("Received invalid message with 'event_type': '%s'" % msg['event_type'])
            self.logger.info("Message details: %s" % str(msg))
            return False
    def _execute_message(self,evt,message):
        self.dispatch_table[evt](message)


    def __request_now_bootstrap(self, directory_id=None, directory=None):
        sdb = SyncDB(apc.AirtimeApiClient.create_right_config())
        if directory_id == None: directory_id = sdb.directories[directory]
        if directory_id in sdb.id_lookup:
            d = sdb.id_lookup[directory_id]
            bs = Bootstrapper(sdb, self.manager.watch_signal())
            bs.flush_watch( directory=d, last_ran=time.time() )
        else:
            raise DirectoryIsNotListed(directory_id)

    def supported_messages(self):
        return self.dispatch_table.keys()

    def md_update(self, msg):
        self.logger.info("Updating metadata for: '%s'" % msg['MDATA_KEY_FILEPATH'])
        md_path = msg['MDATA_KEY_FILEPATH']
        try:
            Metadata.write_unsafe(path=md_path, md=msg)
        except BadSongFile as e:
            self.logger.info("Cannot find metadata file: '%s'" % e.path)
        except Exception as e:
            # TODO : add md_path to problem path or something?
            self.logger.info("Unknown error when writing metadata to: '%s'" % md_path)

    def new_watch(self, msg):
        self.logger.info("Creating watch for directory: '%s'" % msg['directory'])
        if not os.path.exists(msg['directory']):
            try: os.makedirs(msg['directory'])
            except Exception as e:
                self.logger.info("Failed to create watched dir '%s'" % msg['directory'])
                self.logger.info(str(e))
            # Is this clever or stupid?
            else: self.new_watch(msg)
        else:
            # TODO : Refactor this; breaks encapsulation.
            self.manager.watch_listener.flush_events(msg['directory'])
            self.manager.add_watch_directory(msg['directory'])

    def remove_watch(self, msg):
        self.logger.info("Removing watch from directory: '%s'" % msg['directory'])
        self.manager.remove_watch_directory(msg['directory'])

    def rescan_watch(self, msg):
        self.logger.info("Trying to rescan watched directory: '%s'" % msg['directory'])
        try:
            self.__request_now_bootstrap(msg['id'])
        except DirectoryIsNotListed as e:
            self.logger.info("Bad rescan request")
            self.logger.info( str(e) )
        except Exception as e:
            self.logger.info("Bad rescan request. Unknown error.")
            self.logger.info( str(e) )
        else:
            self.logger.info("Successfully re-scanned: '%s'" % msg['directory'])

    def change_storage(self, msg):
        new_storage_directory = msg['directory']
        new_import = os.path.join(new_storage_directory, 'imported')
        new_organize = os.path.join(new_storage_directory, 'organize')
        for d in [new_import, new_organize]:
            if os.path.exists(d):
                self.logger.info("Changing storage to existing dir: '%s'" % d)
            else:
                try: os.makedirs(d)
                except Exception as e:
                    self.logger.info("Could not create dir for storage '%s'" % d)
                    self.logger.info(str(e))

        if all([ os.path.exists(d) for d in [new_import, new_organize] ]):
            self.manager.set_store_path(new_import)
            try:
                self.__request_now_bootstrap( directory=new_import )
            except Exception as e:
                self.logger.info("Did not bootstrap off directory '%s'. Probably not in airtime db" % new_import)
                self.logger.info(str(e))
            # set_organize_path should automatically flush new_organize
            self.manager.set_organize_path(new_organize)
        else:
            self.logger.info("Change storage procedure failed, could not create directories")

    def file_delete(self, msg):
        # deletes should be requested only from imported folder but we don't
        # verify that.
        self.logger.info("Attempting to delete(maybe) '%s'" % msg['filepath'])
        if msg['delete']:
            self.logger.info("Clippy confirmation was received, actually deleting file...")
            if os.path.exists(msg['filepath']):
                try:
                    self.logger.info("Attempting to delete '%s'" % msg['filepath'])
                    FileMediator.ignore(msg['filepath'])
                    os.unlink(msg['filepath'])
                except Exception as e:
                    self.logger.info("Failed to delete '%s'" % msg['filepath'])
                    self.logger.info("Error: " % str(e))
            else:
                self.logger.info("Attempting to delete file '%s' that does not exist. Full request coming:"
                        % msg['filepath'])
                self.logger.info(msg)
        else:
            self.logger.info("No clippy confirmation, ignoring event. Out of curiousity we will print some details.")
            self.logger.info(msg)

