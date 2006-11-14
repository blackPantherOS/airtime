<?
define('INDCH', ' ');

require_once "XmlParser.php";

/**
 * SmilPlaylist class
 *
 * @author Tomas Hlava <th@red2head.com>
 * @author Paul Baranowski <paul@paulbaranowski.org>
 * @version $Revision: 1848 $
 * @package Campcaster
 * @subpackage StorageServer
 * @copyright 2006 MDLF, Inc.
 * @license http://www.gnu.org/licenses/gpl.txt
 * @link http://www.campware.org
 */
class SmilPlaylist {

    /**
     *  Parse SMIL file or string
     *
     * @param string $data
     * 		local path to SMIL file or SMIL string
     * @param string $loc
     * 		location: 'file'|'string'
     * @return array
     * 		reference, parse result tree (or PEAR::error)
     */
    function &parse($data='', $loc='file')
    {
        return XmlParser::parse($data, $loc);
    }


    /**
     *  Import SMIL file to storage
     *
     * @param GreenBox $gb
     * 		reference to GreenBox object
     * @param string $aPath
     * 		absolute path part of imported file (e.g. /home/user/campcaster)
     * @param string $rPath
     * 		relative path/filename part of imported file
     *      (e.g. playlists/playlist_1.smil)
     * @param array $gunids
     * 		hash relation from filenames to gunids
     * @param string $plid
     * 		playlist gunid
     * @param int $parid
     * 		destination folder local id
     * @param int $subjid
     * 		local subject (user) id (id of user doing the import)
     * @return Playlist
     */
    function &import(&$gb, $aPath, $rPath, &$gunids, $plid, $parid, $subjid=NULL)
    {
        $parr = compact('parid', 'subjid', 'aPath', 'plid', 'rPath');
        $path = realpath("$aPath/$rPath");
        if (FALSE === $path) {
            return PEAR::raiseError(
                "SmilPlaylist::import: file doesn't exist ($aPath/$rPath)"
            );
        }
        $lspl = $r = SmilPlaylist::convert2lspl(
            $gb, $path, $gunids, $parr);
        if (PEAR::isError($r)) {
        	return $r;
        }
        require_once "Playlist.php";
        $pl =& Playlist::create($gb, $plid, "imported_SMIL", $parid);
        if (PEAR::isError($pl)) {
        	return $pl;
        }
        $r = $pl->lock($gb, $subjid);
        if (PEAR::isError($r)) {
        	return $r;
        }
        $r = $pl->replaceMetaData($lspl, 'string', 'playlist');
        if (PEAR::isError($r)) {
        	return $r;
        }
        $r = $pl->unLock($gb);
        if (PEAR::isError($r)) {
        	return $r;
        }
        return $pl;
    }


    /**
     *  Import SMIL file to storage
     *
     * @param GreenBox $gb
     * @param string $data
     * 		local path to SMIL file
     * @param hasharray $gunids
     * 		hash relation from filenames to gunids
     * @param array $parr
     * 		array of parid, subjid, aPath, plid, rPath
     * @return string
     * 		XML of playlist in Campcaster playlist format
     */
    function convert2lspl(&$gb, $data, &$gunids, $parr)
    {
        extract($parr);
        $tree = $r = SmilPlaylist::parse($data);
        if (PEAR::isError($r)) {
        	return $r;
        }
        if ($tree->name != 'smil') {
            return PEAR::raiseError("SmilPlaylist::parse: smil tag expected");
        }
        if (isset($tree->children[1])) {
            return PEAR::raiseError(sprintf(
                "SmilPlaylist::parse: unexpected tag %s in tag smil",
                $tree->children[1]->name
            ));
        }
        $res = SmilPlaylistBodyElement::convert2lspl(
            $gb, $tree->children[0], &$gunids, $parr);
        return $res;
    }

} // SmilPlaylist


/**
 * @author Tomas Hlava <th@red2head.com>
 * @author Paul Baranowski <paul@paulbaranowski.org>
 * @version $Revision$
 * @package Campcaster
 * @subpackage StorageServer
 * @copyright 2006 MDLF, Inc.
 * @license http://www.gnu.org/licenses/gpl.txt
 * @link http://www.campware.org
 */
class SmilPlaylistBodyElement {

    function convert2lspl(&$gb, &$tree, &$gunids, $parr, $ind='')
    {
        extract($parr);
        $ind2 = $ind.INDCH;
        if ($tree->name != 'body') {
            return PEAR::raiseError("SmilPlaylist::parse: body tag expected");
        }
        if (isset($tree->children[1])) {
            return PEAR::raiseError(sprintf(
                "SmilPlaylist::parse: unexpected tag %s in tag body",
                $tree->children[1]->name
            ));
        }
        $res = $r = SmilPlaylistParElement::convert2lspl(
            $gb, $tree->children[0], &$gunids, $parr, $ind2);
        if (PEAR::isError($r)) {
        	return $r;
        }
        $title = basename($rPath);
        $playlength = '0';
        $res = "$ind<?xml version=\"1.0\" encoding=\"utf-8\" ?>\n".
            "$ind<playlist id=\"$plid\" playlength=\"$playlength\" title=\"$title\">\n".
            "$ind2<metadata/>\n".
            "$res".
            "$ind</playlist>\n";
        return $res;
    }

} // class SmilPlaylistBodyElement


/**
 * @author Tomas Hlava <th@red2head.com>
 * @author Paul Baranowski <paul@paulbaranowski.org>
 * @version $Revision$
 * @package Campcaster
 * @subpackage StorageServer
 * @copyright 2006 MDLF, Inc.
 * @license http://www.gnu.org/licenses/gpl.txt
 * @link http://www.campware.org
 */
class SmilPlaylistParElement {

	function convert2lspl(&$gb, &$tree, &$gunids, $parr, $ind='')
    {
        extract($parr);
        if ($tree->name != 'par') {
            return PEAR::raiseError("SmilPlaylist::parse: par tag expected");
        }
        $res = '';
        foreach ($tree->children as $i => $ch) {
            $ch =& $tree->children[$i];
            $r = SmilPlaylistAudioElement::convert2lspl(
                $gb, $ch, &$gunids, $parr, $ind.INDCH);
            if (PEAR::isError($r)) {
            	return $r;
            }
            $res .= $r;
        }
        return $res;
    }
}


/**
 * @author Tomas Hlava <th@red2head.com>
 * @author Paul Baranowski <paul@paulbaranowski.org>
 * @version $Revision$
 * @package Campcaster
 * @subpackage StorageServer
 * @copyright 2006 MDLF, Inc.
 * @license http://www.gnu.org/licenses/gpl.txt
 * @link http://www.campware.org
 */
class SmilPlaylistAudioElement {
    function convert2lspl(&$gb, &$tree, &$gunids, $parr, $ind='')
    {
        extract($parr);
        $uri = $tree->attrs['src']->val;
        $gunid  = ( isset($gunids[basename($uri)]) ?  $gunids[basename($uri)] : NULL);
        $ind2 = $ind.INDCH;
        if ($tree->name != 'audio') {
            return PEAR::raiseError("SmilPlaylist::parse: audio tag expected");
        }
        if (isset($tree->children[2])) {
            return PEAR::raiseError(sprintf(
                "SmilPlaylist::parse: unexpected tag %s in tag audio",
                $tree->children[2]->name
            ));
        }
        $res = ''; $fadeIn = 0; $fadeOut = 0;
        foreach ($tree->children as $i => $ch) {
            $ch =& $tree->children[$i];
            $r = SmilPlaylistAnimateElement::convert2lspl(
                $gb, $ch, &$gunids, $parr, $ind2);
            if (PEAR::isError($r)) {
            	return $r;
            }
            switch ($r['type']) {
                case "fadeIn":  $fadeIn  = $r['val']; break;
                case "fadeOut": $fadeOut = $r['val']; break;
            }
        }
        if ($fadeIn > 0 || $fadeOut > 0) {
            $fiGunid = StoredFile::_createGunid();
            $fadeIn  = Playlist::_secsToPlTime($fadeIn);
            $fadeOut = Playlist::_secsToPlTime($fadeOut);
            $fInfo   = "$ind2<fadeInfo id=\"$fiGunid\" fadeIn=\"$fadeIn\" fadeOut=\"$fadeOut\"/>\n";
        } else {
        	$fInfo = '';
        }
        $plElGunid  = StoredFile::_createGunid();
        $acGunid     = $gunid;
        $type = 'audioClip';
        if (preg_match("|\.([a-zA-Z0-9]+)$|", $uri, $va)) {
            switch (strtolower($ext = $va[1])) {
                case "lspl":
                case "xml":
                case "smil":
                case "m3u":
                    $type = 'playlist';
                    $acId = $r = $gb->bsImportPlaylistRaw($parid, $gunid,
                        $aPath, $uri, $ext, $gunids, $subjid);
                    if (PEAR::isError($r)) {
                    	return $r;
                    }
                   //break;
                default:
                    $ac = $r = StoredFile::recallByGunid($gb, $gunid);
                    if (PEAR::isError($r)) {
                    	return $r;
                    }
                    $r = $ac->md->getMetadataEl('dcterms:extent');
                    if (PEAR::isError($r)) {
                    	return $r;
                    }
                    $playlength = $r[0]['value'];
            }
        }

        $title = basename($tree->attrs['src']->val);
        $offset = Playlist::_secsToPlTime($tree->attrs['begin']->val);
        $res = "$ind<playlistElement id=\"$plElGunid\" relativeOffset=\"$offset\">\n".
            "$ind2<$type id=\"$acGunid\" playlength=\"$playlength\" title=\"$title\"/>\n".
            $fInfo.
            "$ind</playlistElement>\n";
        return $res;
    }
} // class SmilPlaylistAudioElement


/**
 * @author Tomas Hlava <th@red2head.com>
 * @author Paul Baranowski <paul@paulbaranowski.org>
 * @version $Revision$
 * @package Campcaster
 * @subpackage StorageServer
 * @copyright 2006 MDLF, Inc.
 * @license http://www.gnu.org/licenses/gpl.txt
 * @link http://www.campware.org
 */
class SmilPlaylistAnimateElement {

	function convert2lspl(&$gb, &$tree, &$gunids, $parr, $ind='')
	{
        extract($parr);
        if ($tree->name != 'animate') {
            return PEAR::raiseError("SmilPlaylist::parse: animate tag expected");
        }
        if ($tree->attrs['attributeName']->val == 'soundLevel' &&
            $tree->attrs['from']->val == '0%' &&
            $tree->attrs['to']->val == '100%' &&
            $tree->attrs['calcMode']->val == 'linear' &&
            $tree->attrs['fill']->val == 'freeze' &&
            $tree->attrs['begin']->val == '0s' &&
            preg_match("|^([0-9.]+)s$|", $tree->attrs['end']->val, $va)
        ) {
            return array('type'=>'fadeIn', 'val'=>intval($va[1]));
        }
        if ($tree->attrs['attributeName']->val == 'soundLevel' &&
            $tree->attrs['from']->val == '100%' &&
            $tree->attrs['to']->val == '0%' &&
            $tree->attrs['calcMode']->val == 'linear' &&
            $tree->attrs['fill']->val == 'freeze' &&
            preg_match("|^([0-9.]+)s$|", $tree->attrs['begin']->val, $vaBegin) &&
            preg_match("|^([0-9.]+)s$|", $tree->attrs['end']->val, $vaEnd)
        ) {
            return array('type'=>'fadeOut', 'val'=>($vaEnd[1] - $vaBegin[1]));
        }
        return PEAR::raiseError(
            "SmilPlaylistAnimateElement::convert2lspl: animate parameters too general"
        );
    }
} // class SmilPlaylistAnimateElement

?>