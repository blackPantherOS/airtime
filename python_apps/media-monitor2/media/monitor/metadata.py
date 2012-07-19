# -*- coding: utf-8 -*-
import mutagen
import math
import copy
from media.monitor.exceptions import BadSongFile
from media.monitor.log import Loggable
import media.monitor.pure as mmp

"""
list of supported easy tags in mutagen version 1.20
['albumartistsort', 'musicbrainz_albumstatus', 'lyricist', 'releasecountry',
'date', 'performer', 'musicbrainz_albumartistid', 'composer', 'encodedby',
'tracknumber', 'musicbrainz_albumid', 'album', 'asin', 'musicbrainz_artistid',
'mood', 'copyright', 'author', 'media', 'length', 'version', 'artistsort',
'titlesort', 'discsubtitle', 'website', 'musicip_fingerprint', 'conductor',
'compilation', 'barcode', 'performer:*', 'composersort', 'musicbrainz_discid',
'musicbrainz_albumtype', 'genre', 'isrc', 'discnumber', 'musicbrainz_trmid',
'replaygain_*_gain', 'musicip_puid', 'artist', 'title', 'bpm', 'musicbrainz_trackid',
'arranger', 'albumsort', 'replaygain_*_peak', 'organization']
"""

airtime2mutagen = {
    "MDATA_KEY_TITLE": "title",
    "MDATA_KEY_CREATOR": "artist",
    "MDATA_KEY_SOURCE": "album",
    "MDATA_KEY_GENRE": "genre",
    "MDATA_KEY_MOOD": "mood",
    "MDATA_KEY_TRACKNUMBER": "tracknumber",
    "MDATA_KEY_BPM": "bpm",
    "MDATA_KEY_LABEL": "organization",
    "MDATA_KEY_COMPOSER": "composer",
    "MDATA_KEY_ENCODER": "encodedby",
    "MDATA_KEY_CONDUCTOR": "conductor",
    "MDATA_KEY_YEAR": "date",
    "MDATA_KEY_URL": "website",
    "MDATA_KEY_ISRC": "isrc",
    "MDATA_KEY_COPYRIGHT": "copyright",
}

# Some airtime attributes are special because they must use the mutagen object
# itself to calculate the value that they need. The lambda associated with each
# key should attempt to extract the corresponding value from the mutagen object
# itself pass as 'm'. In the case when nothing can be extracted the lambda
# should return some default value to be assigned anyway or None so that the
# airtime metadata object will skip the attribute outright.

airtime_special = {
    "MDATA_KEY_DURATION" : lambda m: getattr(m.info, "length", 0.0),
    "MDATA_KEY_BITRATE" : lambda m: getattr(m.info, "bitrate", 0),
    "MDATA_KEY_SAMPLERATE" : lambda m: format_length(getattr(m.info, "sample_rate", 0)),
    "MDATA_KEY_MIME" : lambda m: m.mime[0] if len(m.mime) > 0 else u'',
}
mutagen2airtime = dict( (v,k) for k,v in airtime2mutagen.iteritems() if isinstance(v, str) )

truncate_table = {
        'MDATA_KEY_GENRE' : 64,
        'MDATA_KEY_TITLE' : 512,
        'MDATA_KEY_CREATOR' : 512,
        'MDATA_KEY_SOURCE' : 512,
        'MDATA_KEY_MOOD' : 64,
        'MDATA_KEY_LABEL' : 512,
        'MDATA_KEY_COMPOSER' : 512,
        'MDATA_KEY_ENCODER' : 255,
        'MDATA_KEY_CONDUCTOR' : 512,
        'MDATA_KEY_YEAR' : 16,
        'MDATA_KEY_URL' : 512,
        'MDATA_KEY_ISRC' : 512,
        'MDATA_KEY_COPYRIGHT' : 512,
}

def format_length(mutagen_length):
    """Convert mutagen length to airtime length"""
    t = float(mutagen_length)
    h = int(math.floor(t / 3600))
    t = t % 3600
    m = int(math.floor(t / 60))
    s = t % 60
    # will be ss.uuu
    s = str(s)
    seconds = s.split(".")
    s = seconds[0]
    # have a maximum of 6 subseconds.
    if len(seconds[1]) >= 6: ss = seconds[1][0:6]
    else: ss = seconds[1][0:]
    return "%s:%s:%s.%s" % (h, m, s, ss)

def truncate_to_length(item, length):
    if isinstance(item, int): item = str(item)
    if isinstance(item, basestring):
        if len(item) > length: return item[0:length]
        else: return item

class Metadata(Loggable):
    def __init__(self, fpath):
        try: full_mutagen  = mutagen.File(self.path, easy=True)
        except Exception: raise BadSongFile(self.path)
        # TODO : Simplify the way all of these rules are handled right not it's
        # extremely unclear and needs to be refactored.
        metadata = {}
        # Load only the metadata avilable in mutagen into metdata
        for k,v in full_mutagen:
            # Special handling of attributes here
            if isinstance(v, list):
                if len(v) == 1: metadata[k] = v[0]
                else: raise Exception("Unknown mutagen %s:%s" % (k,str(v)))
            else: metadata[k] = v
        self.__metadata = {}
        # Start populating a dictionary of airtime metadata in __metadata
        for muta_k, muta_v in metadata.iteritems():
            # We must check if we can actually translate the mutagen key into
            # an airtime key before doing the conversion
            if muta_k in mutagen2airtime:
                airtime_key = mutagen2airtime[muta_k]
                # Apply truncation in the case where airtime_key is in our
                # truncation table
                muta_v = truncate_to_length(muta_v, truncate_table[airtime_key]) \
                         if airtime_key in truncate_table else muta_v
                self.__metadata[ airtime_key ] = muta_v
        # Now we extra the special values that are calculated from the mutagen
        # object itself:
        for special_key,f in airtime_special:
            new_val = f(full_mutagen)
            if new_val is not None:
                self.__metadata[special_key] = f(full_mutagen)
        # Finally, we "normalize" all the metadata here:
        self.__metadata = mmp.normalized_metadata(self.__metadata, fpath)
        # Now we must load the md5:
        self.__metadata['MDATA_KEY_MD5'] = mmp.fild_md5(fpath)

    def extract(self):
        return copy.deepcopy(self.__metadata)

