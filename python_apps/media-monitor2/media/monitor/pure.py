# -*- coding: utf-8 -*-
import copy
import os
supported_extensions =  ["mp3", "ogg"]
unicode_unknown = u'unknown'

def is_airtime_show_recorder(md):
    return md['MDATA_KEY_CREATOR'] == u'Airtime Show Recorder'

def extension(path):
    """
    return extension of path, empty string otherwise. Prefer
    to return empty string instead of None because of bad handling of "maybe"
    types in python. I.e. interpreter won't enforce None checks on the programmer
    >>> extension("testing.php")
    'php'
    >>> extension('/no/extension')
    ''
    >>> extension('/path/extension.ml')
    'ml'
    """
    ext = path.split(".")
    if len(ext) < 2: return ""
    else: return ext[-1]

def apply_rules_dict(d, rules):
    """ NOTE: this function isn't actually pure but probably should be...  """
    for k, rule in rules.iteritems():
        if k in d: d[k] = rule(d[k])

def default_to(dictionary, keys, default):
    """ NOTE: this function mutates dictionary as well. The name for this module
    is terrible. Change it later."""
    for k in keys:
        if not (k in dictionary): dictionary[k] = default

def normalized_metadata(md):
    """ consumes a dictionary of metadata and returns a new dictionary with the
    formatted meta data """
    new_md = copy.deepcopy(md)
    # replace all slashes with dashes
    for k,v in new_md.iteritems(): new_md[k] = v.replace('/','-')
    # Specific rules that are applied in a per attribute basis
    format_rules = {
        # It's very likely that the following isn't strictly necessary. But the old
        # code would cast MDATA_KEY_TRACKNUMBER to an integer as a byproduct of
        # formatting the track number to 2 digits.
        'MDATA_KEY_TRACKNUMBER' : lambda x: int(x),
        'MDATA_KEY_BITRATE' : lambda x: str(x / 1000) + "kbps",
        # note: you don't actually need the lambda here. It's only used for clarity
        'MDATA_KEY_FILEPATH' : lambda x: os.path.normpath(x),
    }
    path_md = ['MDATA_KEY_TITLE', 'MDATA_KEY_CREATOR', 'MDATA_KEY_SOURCE',
               'MDATA_KEY_TRACKNUMBER', 'MDATA_KEY_BITRATE']
    # note that we could have saved a bit of code by rewriting new_md using
    # defaultdict(lambda x: "unknown"). But it seems to be too implicit and
    # could possibly lead to subtle bugs down the road. Plus the following
    # approach gives us the flexibility to use different defaults for
    # different attributes
    default_to(dictionary=new_md, keys=path_md, default=unicode_unknown)
    # should apply the format_rules last
    apply_rules_dict(new_md, format_rules)
    # In the case where the creator is 'Airtime Show Recorder' we would like to
    # format the MDATA_KEY_TITLE slightly differently
    # Note: I don't know why I'm doing a unicode string comparison here
    # that part is copied from the original code
    if is_airtime_show_recorder(md):
        hour,minute,second,name = md['MDATA_KEY_TITLE'].split("-",4)
        # We assume that MDATA_KEY_YEAR is always given for airtime recorded
        # shows
        new_md['MDATA_KEY_TITLE'] = '%s-%s-%s:%s:%s' % \
            (name, new_md['MDATA_KEY_YEAR'], hour, minute, second)
        # IMPORTANT: in the original code. MDATA_KEY_FILEPATH would also
        # be set to the original path of the file for airtime recorded shows
        # (before it was "organized"). We will skip this procedure for now
        # because it's not clear why it was done
    return new_md

def organized_path(self, old_path, root_path, normal_md):
    """
    old_path - path where file is store at the moment <= maybe not necessary?
    root_path - the parent directory where all organized files go
    normal_md - original meta data of the file as given by mutagen AFTER being normalized
    return value: new file path
    """
    filepath = None
    ext = extension(filepath)
    # The blocks for each if statement look awfully similar. Perhaps there is a
    # way to simplify this code
    if is_airtime_show_recorder(normal_md):
        fname = u'%s-%s-%s.%s' % ( normal_md['MDATA_KEY_YEAR'], normal_md['MDATA_KEY_TITLE'],
                normal_md['MDATA_KEY_BITRATE'], ext )
        yyyy, mm, _ = normal_md['MDATA_KEY_YEAR'].split('-',3)
        path = os.path.join(root_path,"recorded", yyyy, mm)
        filepath = os.path.join(path,fname)
    elif normal_md['MDATA_KEY_TRACKNUMBER'] == unicode_unknown:
        fname = u'%s-%s.%s' % (normal_md['MDATA_KEY_TITLE'], normal_md['MDATA_KEY_BITRATE'], ext)
        path = os.path.join(root_path, "imported", normal_md['MDATA_KEY_CREATOR'],
                            normal_md['MDATA_KEY_SOURCE'] )
        filepath = os.path.join(path, fname)
    else: # The "normal" case
        fname = u'%s-%s-%s.%s' % (normal_md['MDATA_KEY_TRACKNUMBER'], normal_md['MDATA_KEY_TITLE'],
                                  normal_md['MDATA_KEY_BITRATE'], ext)
        path = os.path.join(root_path, "imported", normal_md['MDATA_KEY_CREATOR'],
                            normal_md['MDATA_KEY_SOURCE'])
        filepath = os.path.join(path, fname)
    return filepath


if __name__ == '__main__':
    import doctest
    doctest.testmod()
