
# -*- coding: utf-8 -*-
import unittest
import os
import sys
from api_clients import api_client as apc

class TestApiClient(unittest.TestCase):
    def setUp(self):
        test_path = '/home/rudi/Airtime/python_apps/media-monitor2/tests/api_client.cfg'
        if not os.path.exists(test_path):
            print("path for config does not exist: '%s' % test_path")
            # TODO : is there a cleaner way to exit the unit testing?
            sys.exit(1)
        self.apc = apc.AirtimeApiClient(config_path=test_path)
        self.apc.register_component("api-client-tester")
        # All of the following requests should error out in some way
        self.bad_requests = [
                { 'mode' : 'dang it', 'is_record' : 0},
                { 'mode' : 'damn frank', 'is_record' : 1 },
                { 'no_mode' : 'at_all' },
        ]

    def test_bad_requests(self):
        responses = self.apc.send_media_monitor_requests(self.bad_requests, dry=True)
        for response in responses:
            self.assertTrue( 'key' in response )
            self.assertTrue( 'error' in response )
            print("Response: '%s'" % response)

    # We don't actually test any well formed requests because it is more
    # involved

if __name__ == '__main__': unittest.main()


