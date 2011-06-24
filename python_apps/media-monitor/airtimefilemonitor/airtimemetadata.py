import os
import hashlib
import mutagen
import logging
import math

"""
list of supported easy tags in mutagen version 1.20
['albumartistsort', 'musicbrainz_albumstatus', 'lyricist', 'releasecountry', 'date', 'performer', 'musicbrainz_albumartistid', 'composer', 'encodedby', 'tracknumber', 'musicbrainz_albumid', 'album', 'asin', 'musicbrainz_artistid', 'mood', 'copyright', 'author', 'media', 'length', 'version', 'artistsort', 'titlesort', 'discsubtitle', 'website', 'musicip_fingerprint', 'conductor', 'compilation', 'barcode', 'performer:*', 'composersort', 'musicbrainz_discid', 'musicbrainz_albumtype', 'genre', 'isrc', 'discnumber', 'musicbrainz_trmid', 'replaygain_*_gain', 'musicip_puid', 'artist', 'title', 'bpm', 'musicbrainz_trackid', 'arranger', 'albumsort', 'replaygain_*_peak', 'organization']
"""

class AirtimeMetadata:

    def __init__(self):

        self.airtime2mutagen = {\
        "MDATA_KEY_TITLE": "title",\
        "MDATA_KEY_CREATOR": "artist",\
        "MDATA_KEY_SOURCE": "album",\
        "MDATA_KEY_GENRE": "genre",\
        "MDATA_KEY_MOOD": "mood",\
        "MDATA_KEY_TRACKNUMBER": "tracknumber",\
        "MDATA_KEY_BPM": "bpm",\
        "MDATA_KEY_LABEL": "organization",\
        "MDATA_KEY_COMPOSER": "composer",\
        "MDATA_KEY_ENCODER": "encodedby",\
        "MDATA_KEY_CONDUCTOR": "conductor",\
        "MDATA_KEY_YEAR": "date",\
        "MDATA_KEY_URL": "website",\
        "MDATA_KEY_ISRC": "isrc",\
        "MDATA_KEY_COPYRIGHT": "copyright",\
        }

        self.mutagen2airtime = {\
        "title": "MDATA_KEY_TITLE",\
        "artist": "MDATA_KEY_CREATOR",\
        "album": "MDATA_KEY_SOURCE",\
        "genre": "MDATA_KEY_GENRE",\
        "mood": "MDATA_KEY_MOOD",\
        "tracknumber": "MDATA_KEY_TRACKNUMBER",\
        "bpm": "MDATA_KEY_BPM",\
        "organization": "MDATA_KEY_LABEL",\
        "composer": "MDATA_KEY_COMPOSER",\
        "encodedby": "MDATA_KEY_ENCODER",\
        "conductor": "MDATA_KEY_CONDUCTOR",\
        "date": "MDATA_KEY_YEAR",\
        "website": "MDATA_KEY_URL",\
        "isrc": "MDATA_KEY_ISRC",\
        "copyright": "MDATA_KEY_COPYRIGHT",\
        }

        self.logger = logging.getLogger()

    def get_md5(self, filepath):
        f = open(filepath, 'rb')
        m = hashlib.md5()
        m.update(f.read())
        md5 = m.hexdigest()

        return md5

    ## mutagen_length is in seconds with the format (d+).dd
    ## return format hh:mm:ss.uuu
    def format_length(self, mutagen_length):
        t = float(mutagen_length)
        h = int(math.floor(t/3600))
        t = t % 3600
        m = int(math.floor(t/60))

        s = t % 60
        # will be ss.uuu
        s = str(s)
        seconds = s.split(".")
        s = seconds[0]

        # have a maximum of 6 subseconds.
        if len(seconds[1]) >= 6:
            ss = seconds[1][0:6]
        else:
            ss = seconds[1][0:]

        length = "%s:%s:%s.%s" % (h, m, s, ss)

        return length

    def save_md_to_file(self, m):
        try:
            airtime_file = mutagen.File(m['MDATA_KEY_FILEPATH'], easy=True)

            for key in m.keys() :
                if key in self.airtime2mutagen:
                    value = m[key]
                    if ((value is not None) and (len(str(value)) > 0)):
                        airtime_file[self.airtime2mutagen[key]] = str(value)


            airtime_file.save()
        except Exception, e:
            self.logger.error('Trying to save md')
            self.logger.error('Exception: %s', e)
            self.logger.error('Filepath %s', m['MDATA_KEY_FILEPATH'])

    def get_md_from_file(self, filepath):

        md = {}
        md5 = self.get_md5(filepath)
        md['MDATA_KEY_MD5'] = md5

        file_info = mutagen.File(filepath, easy=True)

        self.logger.info(file_info)

        #check if file has any metadata
        if file_info is not None:
            for key in file_info.keys() :
                if key in self.mutagen2airtime :
                    md[self.mutagen2airtime[key]] = file_info[key][0]

        if 'MDATA_KEY_TITLE' not in md:
            #get rid of file extention from original name, name might have more than 1 '.' in it.
            original_name = os.path.basename(filepath)
            original_name = original_name.split(".")[0:-1]
            original_name = ''.join(original_name)
            md['MDATA_KEY_TITLE'] = original_name

        #incase track number is in format u'4/11'
        if 'MDATA_KEY_TRACKNUMBER' in md:
            if isinstance(md['MDATA_KEY_TRACKNUMBER'], basestring):
                md['MDATA_KEY_TRACKNUMBER'] = md['MDATA_KEY_TRACKNUMBER'].split("/")[0]

        md['MDATA_KEY_BITRATE'] = file_info.info.bitrate
        md['MDATA_KEY_SAMPLERATE'] = file_info.info.sample_rate
        md['MDATA_KEY_DURATION'] = self.format_length(file_info.info.length)
        md['MDATA_KEY_MIME'] = file_info.mime[0]

        if "mp3" in md['MDATA_KEY_MIME']:
            md['MDATA_KEY_FTYPE'] = "audioclip"
        elif "vorbis" in md['MDATA_KEY_MIME']:
            md['MDATA_KEY_FTYPE'] = "audioclip"

        #do this so object can be urlencoded properly.
        for key in md.keys():
            if(isinstance(md[key], basestring)):
                md[key] = md[key].encode('utf-8')

        return md
