# -*- coding: utf-8 -*-
import unittest
import os
import media.monitor.pure as mmp
from media.monitor.metadata import Metadata

class TestMMP(unittest.TestCase):
    def setUp(self):
        self.md1 = {'MDATA_KEY_MD5': '71185323c2ab0179460546a9d0690107',
                    'MDATA_KEY_FTYPE': 'audioclip',
                    'MDATA_KEY_MIME': 'audio/vorbis',
                    'MDATA_KEY_DURATION': '0:0:25.000687',
                    'MDATA_KEY_SAMPLERATE': 48000,
                    'MDATA_KEY_BITRATE': 64000,
                    'MDATA_KEY_REPLAYGAIN': 0,
                    'MDATA_KEY_TITLE': u'ACDC_-_Back_In_Black-sample-64kbps'}

    def test_apply_rules(self):
        sample_dict = {
            'key' : 'val',
            'test' : 'IT',
        }
        rules = {
            'key' : lambda x : x.upper(),
            'test' : lambda y : y.lower()
        }
        sample_dict = mmp.apply_rules_dict(sample_dict, rules)
        self.assertEqual(sample_dict['key'], 'VAL')
        self.assertEqual(sample_dict['test'], 'it')

    def test_default_to(self):
        sd = { }
        def_keys = ['one','two','three']
        sd = mmp.default_to(dictionary=sd, keys=def_keys, default='DEF')
        for k in def_keys: self.assertEqual( sd[k], 'DEF' )

    def test_normalized_metadata(self):
        # Recorded show test first
        orig = Metadata.airtime_dict({
                'date': [u'2012-08-21'],
                'tracknumber': [u'2'],
                'title': [u'11-29-00-record'],
                'artist': [u'Airtime Show Recorder']
        })
        orga = Metadata.airtime_dict({
                'date': [u'2012-08-21'],
                'tracknumber': [u'2'],
                'artist': [u'Airtime Show Recorder'],
                'title': [u'record-2012-08-21-11:29:00']
        })
        orga['MDATA_KEY_FTYPE']   = u'audioclip'
        orig['MDATA_KEY_BITRATE'] = u'256000'
        orga['MDATA_KEY_BITRATE'] = u'256000'
        old_path = "/home/rudi/recorded/2012-08-21-11:29:00.ogg"
        normalized = mmp.normalized_metadata(orig, old_path)
        normalized['MDATA_KEY_BITRATE'] = u'256000'

        self.assertEqual( orga, normalized )

        organized_base_name = "2012-08-21-11-29-00-record-256kbps.ogg"
        base = "/srv/airtime/stor/"
        organized_path = mmp.organized_path(old_path,base, normalized)
        self.assertEqual(os.path.basename(organized_path), organized_base_name)

    def test_file_md5(self):
        p = os.path.realpath(__file__)
        m1 = mmp.file_md5(p)
        m2 = mmp.file_md5(p,10)
        self.assertTrue( m1 != m2 )
        self.assertRaises( ValueError, lambda : mmp.file_md5('/bull/shit/path') )
        self.assertTrue( m1 == mmp.file_md5(p) )

    def test_sub_path(self):
        f1 = "/home/testing/123.mp3"
        d1 = "/home/testing"
        d2 = "/home/testing/"
        self.assertTrue( mmp.sub_path(d1, f1) )
        self.assertTrue( mmp.sub_path(d2, f1) )

    def test_parse_int(self):
        self.assertEqual( mmp.parse_int("123"), "123" )
        self.assertEqual( mmp.parse_int("123asf"), "123" )
        self.assertEqual( mmp.parse_int("asdf"), None )

if __name__ == '__main__': unittest.main()
