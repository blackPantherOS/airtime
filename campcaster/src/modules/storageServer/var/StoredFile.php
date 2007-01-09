<?php
require_once("RawMediaData.php");
require_once("MetaData.php");
require_once(dirname(__FILE__)."/../../getid3/var/getid3.php");

/**
 *  StoredFile class
 *
 *  Campcaster file storage support class.<br>
 *  Represents one virtual file in storage. Virtual file has up to two parts:
 *  <ul>
 *      <li>metadata in database - represented by MetaData class</li>
 *      <li>binary media data in real file - represented by RawMediaData class</li>
 *  </ul>
 *
 * @author Tomas Hlava <th@red2head.com>
 * @author Paul Baranowski <paul@paulbaranowski.org>
 * @version $Revision$
 * @package Campcaster
 * @subpackage StorageServer
 * @copyright 2006 MDLF, Inc.
 * @license http://www.gnu.org/licenses/gpl.txt
 * @link http://www.campware.org
 * @see MetaData
 * @see RawMediaData
 */
class StoredFile {
	/**
	 * Unique ID for the file.
	 *
	 * @var int
	 */
	public $gunid;

	/**
	 * Directory where the file is located.
	 *
	 * @var string
	 */
	private $resDir;

	/**
	 * @var RawMediaData
	 */
	public $rmd;

	/**
	 * @var MetaData
	 */
	public $md;

    /* ========================================================== constructor */
    /**
     * Constructor, but shouldn't be externally called
     *
     * @param string $gunid
     *  	globally unique id of file
     */
    public function __construct($gunid=NULL)
    {
        global $CC_CONFIG;
        global $CC_DBC;
        $this->gunid = $gunid;
        if (is_null($this->gunid)) {
            $this->gunid = StoredFile::CreateGunid();
        }
        $this->resDir = $this->_getResDir($this->gunid);
        $this->rmd = new RawMediaData($this->gunid, $this->resDir);
        $this->md = new MetaData($this->gunid, $this->resDir);
    }


    /* ========= 'factory' methods - should be called to construct StoredFile */
    /**
     *  Create instance of StoredFile object and insert new file
     *
     *  @param int $oid
     * 		local object id in the tree
     *  @param string $filename
     * 		name of new file
     *  @param string $localFilePath
     * 		local path to media file
     *  @param string $metadata
     * 		local path to metadata XML file or XML string
     *  @param string $mdataLoc
     * 		'file'|'string'
     *  @param global $gunid
     * 		unique id - for insert file with gunid
     *  @param string $ftype
     * 		internal file type
     *  @param boolean $copyMedia
     * 		copy the media file if true, make symlink if false
     *  @return StoredFile
     */
    public static function &insert($oid, $filename, $localFilePath='',
        $metadata='', $mdataLoc='file', $gunid=NULL, $ftype=NULL, $copyMedia=TRUE)
    {
        global $CC_CONFIG, $CC_DBC;
        $ac = new StoredFile(($gunid ? $gunid : NULL));
        if (PEAR::isError($ac)) {
            return $ac;
        }
        $ac->name = $filename;
        $ac->id = $oid;
        $ac->mime = "unknown";
        $emptyState = TRUE;
        if ($ac->name == '') {
            $ac->name = $ac->gunid;
        }
        $md5 = md5_file($localFilePath);
        $escapedName = pg_escape_string($filename);
        $escapedFtype = pg_escape_string($ftype);
        $CC_DBC->query("BEGIN");
        $sql = "INSERT INTO ".$CC_CONFIG['filesTable']
                ."(id, name, gunid, mime, state, ftype, mtime, md5)"
                ."VALUES ('$oid', '{$escapedName}', x'{$ac->gunid}'::bigint,
                 '{$ac->mime}', 'incomplete', '$escapedFtype', now(), '$md5')";
        echo $sql;
        $res = $CC_DBC->query($sql);
        if (PEAR::isError($res)) {
            $CC_DBC->query("ROLLBACK");
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
            $CC_DBC->query("ROLLBACK");
            return $res;
        }
        // --- media file insert:
        if ($localFilePath != '') {
            if (!file_exists($localFilePath)) {
                return PEAR::raiseError("StoredFile::insert: ".
                    "media file not found ($localFilePath)");
            }
            $res = $ac->rmd->insert($localFilePath, $copyMedia);
            if (PEAR::isError($res)) {
                $CC_DBC->query("ROLLBACK");
                return $res;
            }
            $mime = $ac->rmd->getMime();
            if ($mime !== FALSE) {
                $res = $ac->setMime($mime);
                if (PEAR::isError($res)) {
                    $CC_DBC->query("ROLLBACK");
                    return $res;
                }
            }
            $emptyState = FALSE;
        }
        if (!$emptyState) {
            $res = $ac->setState('ready');
            if (PEAR::isError($res)) {
                $CC_DBC->query("ROLLBACK");
                return $res;
            }
        }
        $res = $CC_DBC->query("COMMIT");
        if (PEAR::isError($res)) {
            $CC_DBC->query("ROLLBACK");
            return $res;
        }
        return $ac;
    }


    /**
     * Create instance of StoreFile object and recall existing file.<br>
     * Should be supplied with oid OR gunid - not both.
     *
     * @param int $oid
     * 		local object id in the tree
     * @param string $gunid
     * 		global unique id of file
     * @return StoredFile
     */
    public static function &recall($oid='', $gunid='')
    {
        global $CC_DBC;
        global $CC_CONFIG;
        $cond = ($oid != ''
            ? "id='".intval($oid)."'"
            : "gunid=x'$gunid'::bigint"
        );
        $row = $CC_DBC->getRow("
            SELECT id, to_hex(gunid)as gunid, mime, name, ftype
            FROM ".$CC_CONFIG['filesTable']." WHERE $cond
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
        $gunid = StoredFile::NormalizeGunid($row['gunid']);
        $ac = new StoredFile($gunid);
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
     * @param string $gunid
     * 		global unique id of file
     * @return StoredFile
     */
    public static function &recallByGunid($gunid='')
    {
        return StoredFile::recall('', $gunid);
    }


    /**
     * Create instance of StoreFile object and recall existing file
     * by access token.
     *
     * @param string $token
     * 		access token
     * @return StoredFile
     */
    public static function recallByToken($token)
    {
        global $CC_CONFIG, $CC_DBC;
        $gunid = $CC_DBC->getOne("
            SELECT to_hex(gunid) as gunid
            FROM ".$CC_CONFIG['accessTable']."
            WHERE token=x'$token'::bigint");
        if (PEAR::isError($gunid)) {
            return $gunid;
        }
        if (is_null($gunid)) {
            return PEAR::raiseError(
            "StoredFile::recallByToken: invalid token ($token)", GBERR_AOBJNEX);
        }
        $gunid = StoredFile::NormalizeGunid($gunid);
        return StoredFile::recall('', $gunid);
    }


    /**
     * Check if the MD5 value already exists.
     *
     * @param string $p_md5sum
     * @return StoredFile|FALSE|PEAR_Error
     */
    public static function RecallByMd5($p_md5sum)
    {
        global $CC_CONFIG, $CC_DBC;
        $gunid = $CC_DBC->getOne(
            "SELECT to_hex(gunid) as gunid
            FROM ".$CC_CONFIG['filesTable']."
            WHERE md5='$p_md5sum'");
        if (PEAR::isError($gunid)) {
            return $gunid;
        }
        if ($gunid) {
            $gunid = StoredFile::NormalizeGunid($gunid);
            return StoredFile::recall('', $gunid);
        } else {
            return FALSE;
        }
    }


    /**
     * Create instance of StoredFile object and make copy of existing file
     *
     * @param StoredFile $src
     * 		source object
     * @param int $nid
     * 		new local id
     * @return StoredFile
     */
    public static function &CopyOf(&$src, $nid)
    {
        $ac = StoredFile::insert($nid, $src->name, $src->getRealFileName(),
            '', '', NULL, BasicStor::GetType($src->gunid));
        if (PEAR::isError($ac)) {
            return $ac;
        }
        $ac->md->replace($src->md->getMetadata(), 'string');
        return $ac;
    }


    /**
     * Replace existing file with new data.
     *
     * @param int $oid
     * 		local id
     * @param string $name
     * 		name of file
     * @param string $mediaFileLP
     * 		local path to media file
     * @param string $metadata
     * 		local path to metadata XML file or XML string
     * @param string $mdataLoc
     * 		'file'|'string'
     * @return TRUE|PEAR_Error
     */
    public function replace($oid, $name, $mediaFileLP='', $metadata='',
        $mdataLoc='file')
    {
        global $CC_CONFIG, $CC_DBC;
        $CC_DBC->query("BEGIN");
        $res = $this->rename($name);
        if (PEAR::isError($res)) {
            $CC_DBC->query("ROLLBACK");
            return $res;
        }
        if ($mediaFileLP != '') {
            $res = $this->replaceRawMediaData($mediaFileLP);
        } else {
            $res = $this->rmd->delete();
        }
        if (PEAR::isError($res)) {
            $CC_DBC->query("ROLLBACK");
            return $res;
        }
        if ($metadata != '') {
            $res = $this->replaceMetadata($metadata, $mdataLoc);
        } else {
            $res = $this->md->delete();
        }
        if (PEAR::isError($res)) {
            $CC_DBC->query("ROLLBACK");
            return $res;
        }
        $res = $CC_DBC->query("COMMIT");
        if (PEAR::isError($res)) {
            $CC_DBC->query("ROLLBACK");
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
    public function accessRawMediaData($parent='0')
    {
        $realFname = $this->getRealFileName();
        $ext = $this->getFileExtension();
        $res = BasicStor::bsAccess($realFname, $ext, $this->gunid, 'access', $parent);
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
    public function releaseRawMediaData($token)
    {
        $res = BasicStor::bsRelease($token);
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
     * @return void|PEAR_Error
     */
    public function replaceRawMediaData($mediaFileLP)
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
    public function replaceMetadata($metadata, $mdataLoc='file', $format=NULL)
    {
        global $CC_CONFIG, $CC_DBC;
        $CC_DBC->query("BEGIN");
        $res = $this->md->replace($metadata, $mdataLoc, $format);
        if (PEAR::isError($res)) {
            $CC_DBC->query("ROLLBACK");
            return $res;
        }
        $r = $this->md->regenerateXmlFile();
        if (PEAR::isError($r)) {
            $CC_DBC->query("ROLLBACK");
            return $r;
        }
        $res = $CC_DBC->query("COMMIT");
        if (PEAR::isError($res)) {
            return $res;
        }
        return TRUE;
    }


    /**
     * Get metadata as XML string
     *
     * @return XML string
     * @see MetaData
     */
    public function getMetadata()
    {
        return $this->md->getMetadata();
    }


    /**
     * Analyze file with getid3 module.<br>
     * Obtain some metadata stored in media file.<br>
     * This method should be used for prefilling metadata input form.
     *
     * @return array
     * @see MetaData
     */
    public function analyzeMediaFile()
    {
        $ia = $this->rmd->analyze();
        return $ia;
    }


    /**
     * Rename stored virtual file
     *
     * @param string $newname
     * @return TRUE|PEAR_Error
     */
    public function rename($newname)
    {
        global $CC_CONFIG, $CC_DBC;
        $escapedName = pg_escape_string($newname);
        $res = $CC_DBC->query("
            UPDATE ".$CC_CONFIG['filesTable']." SET name='$escapedName', mtime=now()
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
     * @return TRUE|PEAR_Error
     */
    public function setState($state, $editedby=NULL)
    {
        global $CC_CONFIG, $CC_DBC;
        $escapedState = pg_escape_string($state);
        $eb = (!is_null($editedby) ? ", editedBy=$editedby" : '');
        $res = $CC_DBC->query("
            UPDATE ".$CC_CONFIG['filesTable']."
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
     * @return boolean|PEAR_Error
     */
    public function setMime($mime)
    {
        global $CC_CONFIG, $CC_DBC;
        if ( !is_string($mime)){
            $mime = 'application/octet-stream';
        }
        $escapedMime = pg_escape_string($mime);
        $res = $CC_DBC->query("
            UPDATE ".$CC_CONFIG['filesTable']." SET mime='$escapedMime', mtime=now()
            WHERE gunid=x'{$this->gunid}'::bigint
        ");
        if (PEAR::isError($res)) {
            return $res;
        }
        return TRUE;
    }


    /**
     * Set md5 of virtual file
     *
     * @param string $p_md5sum
     * @return boolean|PEAR_Error
     */
    public function setMd5($p_md5sum)
    {
        global $CC_CONFIG, $CC_DBC;
        $escapedMd5 = pg_escape_string($p_md5sum);
        $res = $CC_DBC->query("
            UPDATE ".$CC_CONFIG['filesTable']." SET md5='$escapedMd5', mtime=now()
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
     * @return TRUE|PEAR_Error
     */
    public function delete()
    {
        global $CC_CONFIG, $CC_DBC;
        $res = $this->rmd->delete();
        if (PEAR::isError($res)) {
            return $res;
        }
        $res = $this->md->delete();
        if (PEAR::isError($res)) {
            return $res;
        }
        $tokens = $CC_DBC->getAll("
            SELECT to_hex(token)as token, ext FROM ".$CC_CONFIG['accessTable']."
            WHERE gunid=x'{$this->gunid}'::bigint
        ");
        if (is_array($tokens)) {
            foreach($tokens as $i=>$item){
                $file = $this->_getAccessFileName($item['token'], $item['ext']);
                if (file_exists($file)) {
                    @unlink($file);
                }
            }
        }
        $res = $CC_DBC->query("
            DELETE FROM ".$CC_CONFIG['accessTable']."
            WHERE gunid=x'{$this->gunid}'::bigint
        ");
        if (PEAR::isError($res)) {
            return $res;
        }
        $res = $CC_DBC->query("
            DELETE FROM ".$CC_CONFIG['filesTable']."
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
     * @return boolean|PEAR_Error
     */
    public function isAccessed($gunid=NULL)
    {
        global $CC_CONFIG, $CC_DBC;
        if (is_null($gunid)) {
            $gunid = $this->gunid;
        }
        $ca = $CC_DBC->getOne("
            SELECT currentlyAccessing FROM ".$CC_CONFIG['filesTable']."
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
    public function isEdited($playlistId=NULL)
    {
        if (is_null($playlistId)) {
            $playlistId = $this->gunid;
        }
        $state = $this->getState($playlistId);
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
     * @return int|null|PEAR_Error
     * 		id of user editing it
     */
    public function isEditedBy($playlistId=NULL)
    {
        global $CC_CONFIG, $CC_DBC;
        if (is_null($playlistId)) {
            $playlistId = $this->gunid;
        }
        $ca = $CC_DBC->getOne("
            SELECT editedBy FROM ".$CC_CONFIG['filesTable']."
            WHERE gunid=x'$playlistId'::bigint
        ");
        if (PEAR::isError($ca)) {
            return $ca;
        }
        if (is_null($ca)) {
            return $ca;
        }
        return intval($ca);
    }


    /**
     * Returns local id of virtual file
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }


    /**
     * Returns true if raw media file exists
     * @return boolean|PEAR_Error
     */
    public function exists()
    {
        global $CC_CONFIG, $CC_DBC;
        $indb = $CC_DBC->getRow(
            "SELECT to_hex(gunid) FROM ".$CC_CONFIG['filesTable']
            ." WHERE gunid=x'{$this->gunid}'::bigint");
        if (PEAR::isError($indb)) {
            return $indb;
        }
        if (is_null($indb)) {
            return FALSE;
        }
        if (BasicStor::GetType($this->gunid) == 'audioclip') {
            return $this->rmd->exists();
        }
        return TRUE;
    }


    /**
     * Create new global unique id
     * @return string
     */
    public static function CreateGunid()
    {
        $ip = (isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '');
        $initString = microtime().$ip.rand()."org.mdlf.campcaster";
        $hash = md5($initString);
        // non-negative int8
        $hsd = substr($hash, 0, 1);
        $res = dechex(hexdec($hsd)>>1).substr($hash, 1, 15);
        return StoredFile::NormalizeGunid($res);
    }


    /**
     * Pad the gunid with zeros if it isnt 16 digits.
     *
     * @return string
     */
    public static function NormalizeGunid($gunid)
    {
        return str_pad($gunid, 16, "0", STR_PAD_LEFT);
    }


    /**
     * Get local id from global id.
     * Static or dynamic call is possible, argument required for
     * static call.
     *
     * @param string $gunid
     * 		optional (for static call), global unique id of file
     */
//    function _idFromGunid($gunid=NULL)
//    {
//        if (is_null($gunid)) {
//            $gunid = $this->$gunid;
//        }
//        $id = $CC_DBC->getOne("
//            SELECT id FROM {$this->filesTable}
//            WHERE gunid=x'$gunid'::bigint
//        ");
//        if (is_null($id)) {
//            return PEAR::raiseError(
//            "StoredFile::_idFromGunid: no such global unique id ($gunid)"
//            );
//        }
//        return $id;
//    }


    /**
     * Return suitable extension.
     *
     * @todo make it general - is any tool for it?
     *
     * @return string
     * 		file extension without a dot
     */
    public function getFileExtension()
    {
        $fname = $this->getFileName();
        $pos = strrpos($fname, '.');
        if ($pos !== FALSE) {
            $ext = substr($fname, $pos+1);
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
     * 		global unique id of file
     * @return string
     * 		mime-type
     */
//    function _getMime($gunid=NULL)
//    {
//        if (is_null($gunid)) {
//            $gunid = $this->gunid;
//        }
//        return $CC_DBC->getOne("
//            SELECT mime FROM {$this->filesTable}
//            WHERE gunid=x'$gunid'::bigint
//        ");
//    }


    /**
     * Get storage-internal file state
     *
     * @param string $gunid
     * 		global unique id of file
     * @return string
     * 		see install()
     */
    public function getState($gunid=NULL)
    {
        global $CC_CONFIG, $CC_DBC;
        if (is_null($gunid)) {
            $gunid = $this->gunid;
        }
        return $CC_DBC->getOne("
            SELECT state FROM ".$CC_CONFIG['filesTable']."
            WHERE gunid=x'$gunid'::bigint
        ");
    }


    /**
     * Get mnemonic file name
     *
     * @param string $gunid
     * 		global unique id of file
     * @return string
     */
    public function getFileName($gunid=NULL)
    {
        global $CC_CONFIG, $CC_DBC;
        if (is_null($gunid)) {
            $gunid = $this->gunid;
        }
        return $CC_DBC->getOne("
            SELECT name FROM ".$CC_CONFIG['filesTable']."
            WHERE gunid=x'$gunid'::bigint
        ");
    }


    /**
     * Get and optionally create subdirectory in real filesystem for storing
     * raw media data.
     *
     * @return string
     */
    private function _getResDir()
    {
        global $CC_CONFIG, $CC_DBC;
        $resDir = $CC_CONFIG['storageDir']."/".substr($this->gunid, 0, 3);
        //$this->gb->debugLog("$resDir");
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
     * @return string
     * @see RawMediaData
     */
    public function getRealFileName()
    {
        return $this->rmd->getFileName();
    }


    /**
     * Get real filename of metadata file
     *
     * @return string
     * @see MetaData
     */
    public function getRealMetadataFileName()
    {
        return $this->md->getFileName();
    }


    /**
     * Create and return name for temporary symlink.
     *
     * @todo Should be more unique
     * @return string
     */
    private function _getAccessFileName($token, $ext='EXT')
    {
        global $CC_CONFIG;
        $token = StoredFile::NormalizeGunid($token);
        return $CC_CONFIG['accessDir']."/$token.$ext";
    }

} // class StoredFile
?>