<?php
require_once "RawMediaData.php";
require_once "MetaData.php";
require_once dirname(__FILE__)."/../../getid3/var/getid3.php";

/**
 *  StoredFile class
 *
 *  Campcaster file storage support class.<br>
 *  Represents one virtual file in storage. Virtual file has up to two parts:
 *  <ul>
 *      <li>metada in database - represented by MetaData class</li>
 *      <li>binary media data in real file
 *          - represented by RawMediaData class</li>
 *  </ul>
 *
 * @author $Author$
 * @version $Revision$
 * @package Campcaster
 * @subpackage StorageServer
 * @see GreenBox
 * @see MetaData
 * @see RawMediaData
 */
class StoredFile {
	var $gb;
	var $dbc;
	var $filesTable;
	var $accessTable;
	var $gunid;
	var $resDir;
	var $accessDir;
	var $rmd;
	var $md;

    /* ========================================================== constructor */
    /**
     *  Constructor, but shouldn't be externally called
     *
     *  @param GreenBox $gb
     *  @param string $gunid
     * 		optional, globally unique id of file
     *  @return this
     */
    function StoredFile(&$gb, $gunid=NULL)
    {
        $this->gb =& $gb;
        $this->dbc =& $gb->dbc;
        $this->filesTable = $gb->filesTable;
        $this->accessTable= $gb->accessTable;
        $this->gunid = $gunid;
        if (is_null($this->gunid)) {
            $this->gunid = $this->_createGunid();
        }
        $this->resDir = $this->_getResDir($this->gunid);
        $this->accessDir = $this->gb->accessDir;
        $this->rmd =& new RawMediaData($this->gunid, $this->resDir);
        $this->md =& new MetaData($gb, $this->gunid, $this->resDir);
#        return $this->gunid;
    }


    /* ========= 'factory' methods - should be called to construct StoredFile */
    /**
     *  Create instance of StoredFile object and insert new file
     *
     *  @param GreenBox $gb
     *  @param int $oid
     * 		local object id in the tree
     *  @param string $name
     * 		name of new file
     *  @param string $mediaFileLP
     * 		local path to media file
     *  @param string $metadata
     * 		local path to metadata XML file or XML string
     *  @param string $mdataLoc
     * 		'file'|'string' (optional)
     *  @param global $gunid
     * 		unique id (optional) - for insert file with gunid
     *  @param string $ftype
     * 		internal file type
     *  @param string $className
     * 		class to be constructed (opt.)
     *  @return StoredFile
     */
    function &insert(&$gb, $oid, $name,
        $mediaFileLP='', $metadata='', $mdataLoc='file',
        $gunid=NULL, $ftype=NULL, $className='StoredFile')
    {
        $ac =& new $className($gb, ($gunid ? $gunid : NULL));
        if (PEAR::isError($ac)) {
            return $ac;
        }
        $ac->name = $name;
        $ac->id = $oid;
        $ac->mime = "unknown";
        $emptyState = TRUE;
        if ($ac->name == '') {
            $ac->name = $ac->gunid;
        }
        $escapedName = pg_escape_string($name);
        $escapedFtype = pg_escape_string($ftype);
        $ac->dbc->query("BEGIN");
        $res = $ac->dbc->query("
            INSERT INTO {$ac->filesTable}
                (id, name, gunid, mime, state, ftype, mtime)
            VALUES
                ('$oid', '{$escapedName}', x'{$ac->gunid}'::bigint,
                    '{$ac->mime}', 'incomplete', '$escapedFtype', now())
        ");
        if (PEAR::isError($res)) {
            $ac->dbc->query("ROLLBACK");
            return $res;
        }
        // --- metadata insert:
        if (is_null($metadata) || ($metadata == '') ) {
            $metadata = dirname(__FILE__).'/emptyMdata.xml';
            $mdataLoc = 'file';
        } else {
            $emptyState = FALSE;
        }
        if ( ($mdataLoc == 'file') && !file_exists($metadata)) {
            return PEAR::raiseError("StoredFile::insert: ".
                "metadata file not found ($metadata)");
        }
        $res = $ac->md->insert($metadata, $mdataLoc, $ftype);
        if (PEAR::isError($res)) {
            $ac->dbc->query("ROLLBACK");
            return $res;
        }
        // --- media file insert:
        if ($mediaFileLP != '') {
            if (!file_exists($mediaFileLP)) {
                return PEAR::raiseError("StoredFile::insert: ".
                    "media file not found ($mediaFileLP)");
            }
            $res = $ac->rmd->insert($mediaFileLP);
            if (PEAR::isError($res)) {
                $ac->dbc->query("ROLLBACK");
                return $res;
            }
            $mime = $ac->rmd->getMime();
            //$gb->debugLog("gunid={$ac->gunid}, mime=$mime");
            if ($mime !== FALSE) {
                $res = $ac->setMime($mime);
                if (PEAR::isError($res)) {
                    $ac->dbc->query("ROLLBACK");
                    return $res;
                }
            }
            $emptyState = FALSE;
        }
        if (!$emptyState) {
            $res = $ac->setState('ready');
            if (PEAR::isError($res)) {
                $ac->dbc->query("ROLLBACK");
                return $res;
            }
        }
        $res = $ac->dbc->query("COMMIT");
        if (PEAR::isError($res)) {
            $ac->dbc->query("ROLLBACK");
            return $res;
        }
        return $ac;
    }


    /**
     * Create instance of StoreFile object and recall existing file.<br>
     * Should be supplied oid XOR gunid - not both ;)
     *
     * @param GreenBox $gb
     * @param int $oid
     * 		optional, local object id in the tree
     * @param string $gunid
     * 		optional, global unique id of file
     * @param string $className
     * 		optional classname to recall
     * @return StoredFile
     */
    function &recall(&$gb, $oid='', $gunid='', $className='StoredFile')
    {
        $cond = ($oid != ''
            ? "id='".intval($oid)."'"
            : "gunid=x'$gunid'::bigint"
        );
        $row = $gb->dbc->getRow("
            SELECT id, to_hex(gunid)as gunid, mime, name, ftype
            FROM {$gb->filesTable} WHERE $cond
        ");
        if (PEAR::isError($row)) {
            return $row;
        }
        if (is_null($row)) {
            $r =& PEAR::raiseError(
                "StoredFile::recall: fileobj not exist ($oid/$gunid)",
                GBERR_FOBJNEX
            );
            return $r;
        }
        $gunid = StoredFile::_normalizeGunid($row['gunid']);
        $ac =& new $className($gb, $gunid);
        $ac->mime = $row['mime'];
        $ac->name = $row['name'];
        $ac->id = $row['id'];
        $ac->md->setFormat($row['ftype']);
        return $ac;
    }


    /**
     * Create instance of StoreFile object and recall existing file
     * by gunid.
     *
     * @param GreenBox $gb
     * @param string $gunid
     * 		optional, global unique id of file
     * @param string $className
     * 		optional classname to recall
     * @return StoredFile
     */
    function &recallByGunid(&$gb, $gunid='', $className='StoredFile')
    {
        return StoredFile::recall($gb, '', $gunid, $className);
    }


    /**
     * Create instance of StoreFile object and recall existing file
     * by access token.
     *
     * @param GreenBox $gb
     * @param string $token
     * 		access token
     * @param string $className
     * 		optional classname to recall
     * @return StoredFile
     */
    function recallByToken(&$gb, $token, $className='StoredFile')
    {
        $gunid = $gb->dbc->getOne("
            SELECT to_hex(gunid)as gunid
            FROM {$gb->accessTable}
            WHERE token=x'$token'::bigint
        ");
        if (PEAR::isError($gunid)) {
            return $gunid;
        }
        if (is_null($gunid)) {
            return PEAR::raiseError(
            "StoredFile::recallByToken: invalid token ($token)", GBERR_AOBJNEX);
        }
        $gunid = StoredFile::_normalizeGunid($gunid);
        return StoredFile::recall($gb, '', $gunid, $className);
    }


    /**
     * Create instance of StoredFile object and make copy of existing file
     *
     * @param reference $src to source object
     * @param int $nid
     * 		new local id
     * @return unknown
     */
    function &copyOf(&$src, $nid)
    {
        $ac = StoredFile::insert(
            $src->gb, $nid, $src->name, $src->_getRealRADFname(),
            '', '', NULL, $src->gb->_getType($src->gunid)
        );
        if (PEAR::isError($ac)) {
            return $ac;
        }
        $ac->md->replace($src->md->getMetaData(), 'string');
        return $ac;
    }


    /* ======================================================= public methods */
    /**
     *  Replace existing file with new data
     *
     *  @param int $oid
     * 		local id
     *  @param string $name
     * 		name of file
     *  @param string $mediaFileLP
     * 		local path to media file
     *  @param string $metadata
     * 		local path to metadata XML file or XML string
     *  @param string $mdataLoc
     * 		'file'|'string'
     */
    function replace($oid, $name, $mediaFileLP='', $metadata='',
        $mdataLoc='file')
    {
        $this->dbc->query("BEGIN");
        $res = $this->rename($name);
        if (PEAR::isError($res)) {
            $this->dbc->query("ROLLBACK");
            return $res;
        }
        if ($mediaFileLP != '') {     // media
            $res = $this->replaceRawMediaData($mediaFileLP);
        } else {
            $res = $this->rmd->delete();
        }
        if (PEAR::isError($res)) {
            $this->dbc->query("ROLLBACK");
            return $res;
        }
        if ($metadata != '') {        // metadata
            $res = $this->replaceMetaData($metadata, $mdataLoc);
        } else {
            $res = $this->md->delete();
        }
        if (PEAR::isError($res)) {
            $this->dbc->query("ROLLBACK");
            return $res;
        }
        $res = $this->dbc->query("COMMIT");
        if (PEAR::isError($res)) {
            $this->dbc->query("ROLLBACK");
            return $res;
        }
        return TRUE;
    }


    /**
     * Increase access counter, create access token, insert access record,
     * call access method of RawMediaData
     *
     * @param int $parent
     * 		parent token
     * @return array
     * 		array with: access URL, access token
     */
    function accessRawMediaData($parent='0')
    {
        $realFname = $this->_getRealRADFname();
        $ext = $this->_getExt();
        $res = $this->gb->bsAccess($realFname, $ext, $this->gunid, 'access', $parent);
        if (PEAR::isError($res)) {
            return $res;
        }
        $resultArray =
            array('url'=>"file://{$res['fname']}", 'token'=>$res['token']);
        return $resultArray;
    }


    /**
     * Decrease access couter, delete access record,
     * call release method of RawMediaData
     *
     * @param string $token
     * 		access token
     * @return boolean
     */
    function releaseRawMediaData($token)
    {
        $res = $this->gb->bsRelease($token);
        if (PEAR::isError($res)) {
            return $res;
        }
        return TRUE;
    }


    /**
     * Replace media file only with new binary file
     *
     * @param string $mediaFileLP
     * 		local path to media file
     */
    function replaceRawMediaData($mediaFileLP)
    {
        $res = $this->rmd->replace($mediaFileLP);
        if (PEAR::isError($res)) {
            return $res;
        }
        $mime = $this->rmd->getMime();
        if ($mime !== FALSE) {
            $res = $this->setMime($mime);
            if (PEAR::isError($res)) {
                return $res;
            }
        }
        $r = $this->md->regenerateXmlFile();
        if (PEAR::isError($r)) {
            return $r;
        }
    }


    /**
     * Replace metadata with new XML file
     *
     * @param string $metadata
     * 		local path to metadata XML file or XML string
     * @param string $mdataLoc
     * 		'file'|'string'
     * @param string $format
     * 		metadata format for validation
     *      ('audioclip' | 'playlist' | 'webstream' | NULL)
     *      (NULL = no validation)
     * @return boolean
     */
    function replaceMetaData($metadata, $mdataLoc='file', $format=NULL)
    {
        $this->dbc->query("BEGIN");
        $res = $r = $this->md->replace($metadata, $mdataLoc, $format);
        if (PEAR::isError($r)) {
            $this->dbc->query("ROLLBACK");
            return $r;
        }
        $r = $this->md->regenerateXmlFile();
        if (PEAR::isError($r)) {
            $this->dbc->query("ROLLBACK");
            return $r;
        }
        $res = $r = $this->dbc->query("COMMIT");
        if (PEAR::isError($r)) {
            return $r;
        }
        return TRUE;
    }


    /**
     * Get metadata as XML string
     *
     * @return XML string
     * @see MetaData
     */
    function getMetaData()
    {
        return $this->md->getMetaData();
    }


    /**
     * Analyze file with getid3 module.<br>
     * Obtain some metadata stored in media file.<br>
     * This method should be used for prefilling metadata input form.
     *
     * @return array
     * @see MetaData
     */
    function analyzeMediaFile()
    {
        $ia = $this->rmd->analyze();
        return $ia;
    }


    /**
     * Rename stored virtual file
     *
     * @param string $newname
     * @return TRUE/PEAR_Error
     */
    function rename($newname)
    {
        $escapedName = pg_escape_string($newname);
        $res = $this->dbc->query("
            UPDATE {$this->filesTable} SET name='$escapedName', mtime=now()
            WHERE gunid=x'{$this->gunid}'::bigint
        ");
        if (PEAR::isError($res)) {
            return $res;
        }
        return TRUE;
    }


    /**
     * Set state of virtual file
     *
     * @param string $state
     * 		'empty'|'incomplete'|'ready'|'edited'
     * @param int $editedby
     * 		 user id | 'NULL' for clear editedBy field
     *      (optional)
     * @return TRUE/PEAR_Error
     */
    function setState($state, $editedby=NULL)
    {
        $escapedState = pg_escape_string($state);
        $eb = (!is_null($editedby) ? ", editedBy=$editedby" : '');
        $res = $this->dbc->query("
            UPDATE {$this->filesTable}
            SET state='$escapedState'$eb, mtime=now()
            WHERE gunid=x'{$this->gunid}'::bigint
        ");
        if (PEAR::isError($res)) {
            return $res;
        }
        return TRUE;
    }


    /**
     * Set mime-type of virtual file
     *
     * @param string $mime
     * 		mime-type
     * @return boolean or error
     */
    function setMime($mime)
    {
        if ( !is_string($mime)){
            $mime = 'application/octet-stream';
        }
        $escapedMime = pg_escape_string($mime);
        $res = $this->dbc->query("
            UPDATE {$this->filesTable} SET mime='$escapedMime', mtime=now()
            WHERE gunid=x'{$this->gunid}'::bigint
        ");
        if (PEAR::isError($res)) {
            return $res;
        }
        return TRUE;
    }


    /**
     * Delete stored virtual file
     *
     * @see RawMediaData
     * @see MetaData
     */
    function delete()
    {
        $res = $this->rmd->delete();
        if (PEAR::isError($res)) {
            return $res;
        }
        $res = $this->md->delete();
        if (PEAR::isError($res)) {
            return $res;
        }
        $tokens = $this->dbc->getAll("
            SELECT to_hex(token)as token, ext FROM {$this->accessTable}
            WHERE gunid=x'{$this->gunid}'::bigint
        ");
        if (is_array($tokens)) {
            foreach($tokens as $i=>$item){
                $file = $this->_getAccessFname($item['token'], $item['ext']);
                if (file_exists($file)) {
                    @unlink($file);
                }
            }
        }
        $res = $this->dbc->query("
            DELETE FROM {$this->accessTable}
            WHERE gunid=x'{$this->gunid}'::bigint
        ");
        if (PEAR::isError($res)) {
            return $res;
        }
        $res = $this->dbc->query("
            DELETE FROM {$this->filesTable}
            WHERE gunid=x'{$this->gunid}'::bigint
        ");
        if (PEAR::isError($res)) {
            return $res;
        }
        return TRUE;
    }


    /**
     * Returns true if virtual file is currently in use.<br>
     * Static or dynamic call is possible.
     *
     * @param string $gunid
     * 		optional (for static call), global unique id
     */
    function isAccessed($gunid=NULL)
    {
        if (is_null($gunid)) {
            $gunid = $this->gunid;
        }
        $ca = $this->dbc->getOne("
            SELECT currentlyAccessing FROM {$this->filesTable}
            WHERE gunid=x'$gunid'::bigint
        ");
        if (is_null($ca)) {
            return PEAR::raiseError(
                "StoredFile::isAccessed: invalid gunid ($gunid)",
                GBERR_FOBJNEX
            );
        }
        return ($ca > 0);
    }


    /**
     * Returns true if virtual file is edited
     *
     * @param string $playlistId
     * 		playlist global unique ID
     * @return boolean
     */
    function isEdited($playlistId=NULL)
    {
        if (is_null($playlistId)) {
            $playlistId = $this->gunid;
        }
        $state = $this->_getState($playlistId);
        if ($state != 'edited') {
            return FALSE;
        }
        return TRUE;
    }


    /**
     * Returns id of user editing playlist
     *
     * @param string $playlistId
     * 		playlist global unique ID
     * @return null or int
     * 		id of user editing it
     */
    function isEditedBy($playlistId=NULL)
    {
        if (is_null($playlistId)) {
            $playlistId = $this->gunid;
        }
        $ca = $this->dbc->getOne("
            SELECT editedBy FROM {$this->filesTable}
            WHERE gunid=x'$playlistId'::bigint
        ");
        if ($this->dbc->isError($ca)) {
            return $ca;
        }
        if (is_null($ca)) {
            return $ca;
        }
        return intval($ca);
    }


    /**
     *  Returns local id of virtual file
     *
     */
    function getId()
    {
        return $this->id;
    }


    /**
     *  Returns true if raw media file exists
     *
     */
    function exists()
    {
        $indb = $this->dbc->getRow("
            SELECT to_hex(gunid) FROM {$this->filesTable}
            WHERE gunid=x'{$this->gunid}'::bigint
        ");
        if (PEAR::isError($indb)) {
            return $indb;
        }
        if (is_null($indb)) {
            return FALSE;
        }
        if ($this->gb->_getType($this->gunid) == 'audioclip') {
            return $this->rmd->exists();
        }
        return TRUE;
    }


    /* ==================================================== "private" methods */
    /**
     *  Create new global unique id
     *
     */
    function _createGunid()
    {
        $ip = (isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '');
        $initString =
            microtime().$ip.rand()."org.mdlf.campcaster";
        $hash = md5($initString);
        // non-negative int8
        $hsd = substr($hash, 0, 1);
        $res = dechex(hexdec($hsd)>>1).substr($hash, 1, 15);
        return StoredFile::_normalizeGunid($res);
    }


    /**
     *  Create new global unique id
     *
     */
    function _normalizeGunid($gunid0)
    {
        return str_pad($gunid0, 16, "0", STR_PAD_LEFT);
    }


    /**
     * Get local id from global id.
     * Static or dynamic call is possible.
     *
     * @param string $gunid
     * 		optional (for static call), global unique id of file
     */
    function _idFromGunid($gunid=NULL)
    {
        if (is_null($gunid)) {
            $gunid = $this->$gunid;
        }
        $id = $this->dbc->getOne("
            SELECT id FROM {$this->filesTable}
            WHERE gunid=x'$gunid'::bigint
        ");
        if (is_null($id)) {
            return PEAR::raiseError(
            "StoredFile::_idFromGunid: no such global unique id ($gunid)"
            );
        }
        return $id;
    }


    /**
     * Return suitable extension.
     *
     * @todo make it general - is any tool for it?
     *
     * @return string
     * 		file extension without a dot
     */
    function _getExt()
    {
        $fname = $this->_getFileName();
        $pos   = strrpos($fname, '.');
        if ($pos !== FALSE) {
            $ext   = substr($fname, $pos+1);
            if ($ext !== FALSE) {
                return $ext;
            }
        }
        switch (strtolower($this->mime)) {
            case "audio/mpeg":
                $ext = "mp3";
                break;
            case "audio/x-wav":
            case "audio/x-wave":
                $ext = "wav";
                break;
            case "audio/x-ogg":
            case "application/x-ogg":
                $ext = "ogg";
                break;
            default:
                $ext = "bin";
                break;
        }
        return $ext;
    }


    /**
     * Get mime-type from global id
     *
     * @param string $gunid
     * 		optional, global unique id of file
     * @return string
     * 		mime-type
     */
    function _getMime($gunid=NULL)
    {
        if (is_null($gunid)) {
            $gunid = $this->gunid;
        }
        return $this->dbc->getOne("
            SELECT mime FROM {$this->filesTable}
            WHERE gunid=x'$gunid'::bigint
        ");
    }


    /**
     * Get storage-internal file state
     *
     * @param string $gunid
     * 		optional, global unique id of file
     * @return string
     * 		see install()
     */
    function _getState($gunid=NULL)
    {
        if (is_null($gunid)) {
            $gunid = $this->gunid;
        }
        return $this->dbc->getOne("
            SELECT state FROM {$this->filesTable}
            WHERE gunid=x'$gunid'::bigint
        ");
    }


    /**
     * Get mnemonic file name
     *
     * @param string $gunid
     * 		optional, global unique id of file
     * @return string
     * 		see install()
     */
    function _getFileName($gunid=NULL)
    {
        if (is_null($gunid)) {
            $gunid = $this->gunid;
        }
        return $this->dbc->getOne("
            SELECT name FROM {$this->filesTable}
            WHERE gunid=x'$gunid'::bigint
        ");
    }


    /**
     * Get and optionaly create subdirectory in real filesystem for storing
     * raw media data
     *
     */
    function _getResDir()
    {
        $resDir="{$this->gb->storageDir}/".substr($this->gunid, 0, 3);
        #$this->gb->debugLog("$resDir");
        // see Transport::_getResDir too for resDir name create code
        if (!is_dir($resDir)) {
            mkdir($resDir, 02775);
            chmod($resDir, 02775);
        }
        return $resDir;
    }


    /**
     * Get real filename of raw media data
     *
     * @see RawMediaData
     */
    function _getRealRADFname()
    {
        return $this->rmd->getFname();
    }


    /**
     * Get real filename of metadata file
     *
     * @see MetaData
     */
    function _getRealMDFname()
    {
        return $this->md->getFname();
    }


    /**
     * Create and return name for temporary symlink.
     *
     * @todo Should be more unique
     */
    function _getAccessFname($token, $ext='EXT')
    {
        $token = StoredFile::_normalizeGunid($token);
        return "{$this->accessDir}/$token.$ext";
    }

} // class StoredFile
?>