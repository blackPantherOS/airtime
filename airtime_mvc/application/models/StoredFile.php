<?php

/**
 *  StoredFile class
 *
 * @package Airtime
 * @subpackage StorageServer
 * @copyright 2010 Sourcefabric O.P.S.
 * @license http://www.gnu.org/licenses/gpl.txt
 * @see MetaData
 */
class StoredFile {

    /**
     * @holds propel database object
     */
    private $_file;

    /**
     * array of db metadata -> propel
     */
    private $_dbMD = array (
        "track_title" => "DbTrackTitle",
        "artist_name" => "DbArtistName",
        "album_title" => "DbAlbumTitle",
        "genre" => "DbGenre",
        "mood" => "DbMood",
        "track_number" => "DbTrackNumber",
        "bpm" => "DbBpm",
        "label" => "DbLabel",
        "composer" => "DbComposer",
        "encoded_by" => "DbEncodedBy",
        "conductor" => "DbConductor",
        "year" => "DbYear",
        "info_url" => "DbInfoUrl",
        "isrc_number" => "DbIsrcNumber",
        "copyright" => "DbCopyright",
        "length" => "DbLength",
        "bit_rate" => "DbBitRate",
        "sample_rate" => "DbSampleRate",
        "mime" => "DbMime",
        "filepath" => "DbFilepath",
        "md5" => "DbMd5",
        "ftype" => "DbFtype"
    );

    public function __construct()
    {

    }

    public function getId()
    {
        return $this->_file->getDbId();
    }

    public function getGunId() {
        return $this->_file->getDbGunid();
    }

    public function getFormat()
    {
        return $this->_file->getDbFtype();
    }

    public function setFormat($p_format)
    {
        $this->_file->setDbFtype($p_format);
    }

    /**
     * Set multiple metadata values using defined metadata constants.
     *
     * @param array $p_md
     *  example: $p_md['MDATA_KEY_URL'] = 'http://www.fake.com'
     */
	public function setMetadata($p_md=null)
    {
        if (is_null($p_md)) {
            $this->setDbColMetadata();
        }
        else {
            $dbMd = array();
            foreach ($p_md as $mdConst => $mdValue) {
                $dbMd[constant($mdConst)] = $mdValue;
            }
            $this->setDbColMetadata($dbMd);
        }
    }

    public static function getFileCount()
    {
		global $CC_CONFIG, $CC_DBC;
        $sql = "SELECT count(*) as cnt FROM ".$CC_CONFIG["filesTable"]." WHERE state='ready'";
        return $CC_DBC->GetOne($sql);    	
    }
    
    /**
     * Set multiple metadata values using database columns as indexes.
     *
     * @param array $p_md
     *  example: $p_md['url'] = 'http://www.fake.com'
     */
	public function setDbColMetadata($p_md=null)
    {
        if (is_null($p_md)) {
            foreach ($this->_dbMD as $dbColumn => $propelColumn) {
                $method = "set$propelColumn";
                $this->_file->$method(null);
            }
        }
        else {
            foreach ($p_md as $dbColumn => $mdValue) {
                //don't blank out name, defaults to original filename on first insertion to database.
                if($dbColumn == "track_title" && (is_null($mdValue) || $mdValue == "")) {
                    continue;
                }
                $propelColumn = $this->_dbMD[$dbColumn];
                $method = "set$propelColumn";
                $this->_file->$method($mdValue);
            }
        }

        $this->_file->save();
    }

    /**
     * Set metadata element value
     *
     * @param string $category
     * 		Metadata element by metadata constant
     * @param string $value
     * 		value to store, if NULL then delete record
     */
    public function setMetadataValue($p_category, $p_value)
    {
        $this->setDbColMetadataValue(constant($p_category), $p_value);
    }

    /**
     * Set metadata element value
     *
     * @param string $category
     * 		Metadata element by db column
     * @param string $value
     * 		value to store, if NULL then delete record
     */
    public function setDbColMetadataValue($p_category, $p_value)
    {
        //don't blank out name, defaults to original filename on first insertion to database.
        if($p_category == "track_title" && (is_null($p_value) || $p_value == "")) {
            return;
        }
        $propelColumn = $this->_dbMD[$p_category];
        $method = "set$propelColumn";
        $this->_file->$method($p_value);
        $this->_file->save();
    }

    /**
     * Get one metadata value.
     *
     * @param string $p_category (MDATA_KEY_URL)
     * @return string
     */
    public function getMetadataValue($p_category)
    {
        return $this->getDbColMetadataValue(constant($p_category));
    }

     /**
     * Get one metadata value.
     *
     * @param string $p_category (url)
     * @return string
     */
    public function getDbColMetadataValue($p_category)
    {
        $propelColumn = $this->_dbMD[$p_category];
        $method = "get$propelColumn";
        return $this->_file->$method();
    }

    /**
     * Get metadata as array, indexed by the column names in the database.
     *
     * @return array
     */
    public function getDbColMetadata()
    {
        $md = array();
        foreach ($this->_dbMD as $dbColumn => $propelColumn) {
            $method = "get$propelColumn";
            $md[$dbColumn] = $this->_file->$method();
        }

        return $md;
    }

    /**
     * Get metadata as array, indexed by the constant names.
     *
     * @return array
     */
    public function getMetadata()
    {
        $c = get_defined_constants(true);
        $md = array();

        foreach ($c['user'] as $constant => $value) {
            if (preg_match('/^MDATA_KEY/', $constant)) {
                if (isset($this->_dbMD[$value])) {
                    $md[$constant] = $this->getDbColMetadataValue($value);
                }
            }
        }

        return $md;
    }

    /**
     * Delete and insert media file
     *
     * @param string $p_localFilePath
     *      local path
     * @return TRUE|PEAR_Error
     */
    public function replaceFile($p_localFilePath, $p_copyMedia=TRUE)
    {
        // Dont do anything if the source and destination files are
        // the same.
        if ($this->name == $p_localFilePath) {
            return TRUE;
        }

        if ($this->exists) {
            $r = $this->deleteFile();
            if (PEAR::isError($r)) {
                return $r;
            }
        }
        return $this->addFile($p_localFilePath, $p_copyMedia);
    }

    /**
     * Set state of virtual file
     *
     * @param string $p_state
     * 		'empty'|'incomplete'|'ready'|'edited'
     * @param int $p_editedby
     * 		 user id | 'NULL' for clear editedBy field
     * @return TRUE|PEAR_Error
     */
    public function setState($p_state, $p_editedby=NULL)
    {
        global $CC_CONFIG, $CC_DBC;
        $escapedState = pg_escape_string($p_state);
        $eb = (!is_null($p_editedby) ? ", editedBy=$p_editedby" : '');
        $sql = "UPDATE ".$CC_CONFIG['filesTable']
        ." SET state='$escapedState'$eb, mtime=now()"
        ." WHERE gunid='{$this->gunid}'";
        $res = $CC_DBC->query($sql);
        if (PEAR::isError($res)) {
            return $res;
        }
        $this->state = $p_state;
        $this->editedby = $p_editedby;
        return TRUE;
    }

    /**
     * Returns an array of playlist objects that this file is a part of.
     * @return array
     */
    public function getPlaylists() {
        global $CC_CONFIG, $CC_DBC;
        $sql = "SELECT playlist_id "
        ." FROM ".$CC_CONFIG['playistTable']
        ." WHERE file_id='{$this->id}'";
        $ids = $CC_DBC->getAll($sql);
        $playlists = array();
        if (is_array($ids) && count($ids) > 0) {
            foreach ($ids as $id) {
                $playlists[] = Playlist::Recall($id);
            }
        }
        return $playlists;
    }

    /**
     * Delete stored virtual file
     *
     * @param boolean $p_deleteFile
     *
     * @return void|PEAR_Error
     */
    public function delete()
    {
        if ($this->exists()) {
            if ($this->getFormat() == 'audioclip') {
                $res = $this->deleteFile();
                if (PEAR::isError($res)) {
                    return $res;
                }
            }
        }

        Playlist::DeleteFileFromAllPlaylists($this->getId());
        $this->_file->delete();
    }

    /**
     * Delete media file from filesystem.
     * You cant delete a file if it is being accessed.
     * You cant delete a file if it is scheduled to be played in the future.
     * The file will be removed from all playlists it is a part of.
     *
     * @return void|PEAR_Error
     */
    public function deleteFile()
    {
        global $CC_CONFIG;

        if ($this->isAccessed()) {
            return PEAR::raiseError('Cannot delete a file that is currently accessed.');
        }

        // Check if the file is scheduled to be played in the future
        if (Schedule::IsFileScheduledInTheFuture($this->getId())) {
            return PEAR::raiseError('Cannot delete a file that is scheduled in the future.');
        }

        // Only delete the file from filesystem if it has been copied to the storage directory
        if (substr($this->getFilePath(), 0, strlen($CC_CONFIG["storageDir"])) == $CC_CONFIG["storageDir"]) {
            // Delete the file
            $res = unlink($this->getFilePath());
            if (!$res) {
                return PEAR::raiseError("StoredFile::deleteFile: unlink failed ({$this->getFilePath()})");
            }
        }
    }

    /**
     * Returns true if media file exists
     * @return boolean
     */
    public function exists()
    {
        if ($this->_file->isDeleted()) {
            return false;
        }
        if ($this->getFormat() == 'audioclip') {
            return $this->existsFile();
        }
    }

    /**
     * Returns true if raw media file exists
     * @return boolean
     */
    public function existsFile() {

        $filepath = $this->_file->getDbFilepath();

        if (!isset($filepath) || !file_exists($filepath) || !is_readable($filepath)) {
            return false;
        }
        else {
            return true;
        }
    }

     /**
     * Returns true if virtual file is currently in use.<br>
     *
     * @return boolean
     */
    public function isAccessed()
    {
       return ($this->_file->getDbCurrentlyaccessing() > 0);
    }

    /**
     * Return suitable extension.
     *
     * @return string
     * 		file extension without a dot
     */
    public function getFileExtension()
    {
        $mime = $this->_file->getDbMime();

        if ($mime == "audio/vorbis") {
            return "ogg";
        }
        else if ($mime == "audio/mp3") {
            return "mp3";
        }
    }

    /**
     * Get real filename of raw media data
     *
     * @return string
     */
    public function getFilePath()
    {
        return $this->_file->getDbFilepath();
    }

    /**
     * Get the URL to access this file.
     */
    public function getFileUrl()
    {
        global $CC_CONFIG;
        return "http://$CC_CONFIG[baseUrl]:$CC_CONFIG[basePort]/api/get-media/file/".$this->getGunId().".".$this->getFileExtension();
    }

    public static function Insert($md=null)
    {
        $file = new CcFiles();
        $file->setDbGunid(md5(uniqid("", true)));
        $file->save();

        $storedFile = new StoredFile();
        $storedFile->_file = $file;

        if(isset($md)) {
            $storedFile->setMetadata($md);
       }

       return $storedFile;
    }

    /**
     * Fetch instance of StoreFile object.<br>
     * Should be supplied with only ONE parameter, all the rest should
     * be NULL.
     *
     * @param int $p_id
     * 		local id
     * @param string $p_gunid
     * 		global unique id of file
     * @param string $p_md5sum
     *    MD5 sum of the file
     * @return StoredFile|NULL
     *    Return NULL if the object doesnt exist in the DB.
     */
    public static function Recall($p_id=null, $p_gunid=null, $p_md5sum=null, $p_filepath=null)
    {
        if (isset($p_id)) {
            $file = CcFilesQuery::create()->findPK(intval($p_id));
        }
        else if (isset($p_gunid)) {
            $file = CcFilesQuery::create()
                            ->filterByDbGunid($p_gunid)
                            ->findOne();
        }
        else if (isset($p_md5sum)) {
            $file = CcFilesQuery::create()
                            ->filterByDbMd5($p_md5sum)
                            ->findOne();
        }
        else if (isset($p_filepath)) {
            $file = CcFilesQuery::create()
                            ->filterByDbFilepath($p_filepath)
                            ->findOne();
        }
        else {
            return null;
        }

        if (isset($file)) {
            $storedFile = new StoredFile();
            $storedFile->_file = $file;

            return $storedFile;
        }
        else {
            return null;
        }
    }

    /**
     * Create instance of StoreFile object and recall existing file
     * by gunid.
     *
     * @param string $p_gunid
     * 		global unique id of file
     * @return StoredFile|NULL
     */
    public static function RecallByGunid($p_gunid)
    {
        return StoredFile::Recall(null, $p_gunid);
    }


    /**
     * Fetch the StoredFile by looking up the MD5 value.
     *
     * @param string $p_md5sum
     * @return StoredFile|NULL
     */
    public static function RecallByMd5($p_md5sum)
    {
        return StoredFile::Recall(null, null, $p_md5sum);
    }

    /**
     * Fetch the StoredFile by looking up its filepath.
     *
     * @param string $p_filepath path of file stored in Airtime.
     * @return StoredFile|NULL
     */
    public static function RecallByFilepath($p_filepath)
    {
        return StoredFile::Recall(null, null, null, $p_filepath);
    }

	public static function searchFilesForPlaylistBuilder($datatables) {
		global $CC_CONFIG;

		$displayData = array("track_title", "artist_name", "album_title", "track_number", "length", "ftype");

		$plSelect = "SELECT ";
        $fileSelect = "SELECT ";
        foreach ($displayData as $key){

            if($key === "track_title"){
                $plSelect .= "name AS ".$key.", ";
                $fileSelect .= $key.", ";
            }
			else if ($key === "ftype"){
                $plSelect .= "'playlist' AS ".$key.", ";
                $fileSelect .= $key.", ";
            }
            else if ($key === "artist_name"){
                $plSelect .= "creator AS ".$key.", ";
                $fileSelect .= $key.", ";
            }
            else if ($key === "length"){
                $plSelect .= $key.", ";
                $fileSelect .= $key.", ";
            }
            else {
                $plSelect .= "NULL AS ".$key.", ";
                $fileSelect .= $key.", ";
            }
        }

		$fromTable = " ((".$plSelect."PL.id
		    FROM ".$CC_CONFIG["playListTable"]." AS PL
			LEFT JOIN ".$CC_CONFIG['playListTimeView']." AS PLT USING(id))

		    UNION

		    (".$fileSelect."id FROM ".$CC_CONFIG["filesTable"]." AS FILES)) AS RESULTS";

		return StoredFile::searchFiles($fromTable, $datatables);

	}

	public static function searchPlaylistsForSchedule($datatables)
    {
		$fromTable = "cc_playlist AS pl LEFT JOIN cc_playlisttimes AS plt USING(id) LEFT JOIN cc_subjs AS sub ON pl.editedby = sub.id";
        //$datatables["optWhere"][] = "INTERVAL '{$time_remaining}' > INTERVAL '00:00:00'";
        $datatables["optWhere"][] = "plt.length > INTERVAL '00:00:00'";

		return StoredFile::searchFiles($fromTable, $datatables);
	}

	public static function searchFiles($fromTable, $data)
	{
		global $CC_CONFIG, $CC_DBC;

		$columnsDisplayed = explode(",", $data["sColumns"]);

		if($data["sSearch"] !== "")
			$searchTerms = explode(" ", $data["sSearch"]);

		$selectorCount = "SELECT COUNT(*)";
		$selectorRows = "SELECT ". join("," , $columnsDisplayed);

        $sql = $selectorCount." FROM ".$fromTable;
		$totalRows = $CC_DBC->getOne($sql);

		//	Where clause
		if(isset($data["optWhere"])) {
			$where[] = join(" AND ", $data["optWhere"]);
		}

		if(isset($searchTerms)) {
			$searchCols = array();
			for($i=0; $i<$data["iColumns"]; $i++) {
				if($data["bSearchable_".$i] == "true") {
					$searchCols[] = $columnsDisplayed[$i];
				}
			}

			$outerCond = array();

			foreach($searchTerms as $term) {
				$innerCond = array();

				foreach($searchCols as $col) {
                    $escapedTerm = pg_escape_string($term);
					$innerCond[] = "{$col}::text ILIKE '%{$escapedTerm}%'";
				}
				$outerCond[] = "(".join(" OR ", $innerCond).")";
			}
			$where[] = "(".join(" AND ", $outerCond).")";
		}
		// End Where clause

		// Order By clause
		$orderby = array();
		for($i=0; $i<$data["iSortingCols"]; $i++){
			$orderby[] = $columnsDisplayed[$data["iSortCol_".$i]]." ".$data["sSortDir_".$i];
		}
		$orderby[] = "id";
		$orderby = join("," , $orderby);
		// End Order By clause

		//ordered by integer as expected by datatables.
		$CC_DBC->setFetchMode(DB_FETCHMODE_ORDERED);

		if(isset($where)) {
			$where = join(" AND ", $where);
			$sql = $selectorCount." FROM ".$fromTable." WHERE ".$where;
			$totalDisplayRows = $CC_DBC->getOne($sql);
			$sql = $selectorRows." FROM ".$fromTable." WHERE ".$where." ORDER BY ".$orderby." OFFSET ".$data["iDisplayStart"]." LIMIT ".$data["iDisplayLength"];
		}
		else {
			$sql = $selectorRows." FROM ".$fromTable." ORDER BY ".$orderby." OFFSET ".$data["iDisplayStart"]." LIMIT ".$data["iDisplayLength"];
		}

		$results = $CC_DBC->getAll($sql);
		//echo $results;
		//echo $sql;

		//put back to default fetch mode.
		$CC_DBC->setFetchMode(DB_FETCHMODE_ASSOC);

		if(!isset($totalDisplayRows)) {
			$totalDisplayRows = $totalRows;
		}

		return array("sEcho" => intval($data["sEcho"]), "iTotalDisplayRecords" => $totalDisplayRows, "iTotalRecords" => $totalRows, "aaData" => $results);
	}

    public static function uploadFile($p_targetDir)
    {
        // HTTP headers for no cache etc
		header('Content-type: text/plain; charset=UTF-8');
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header("Cache-Control: no-store, no-cache, must-revalidate");
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache");

		// Settings
		$cleanupTargetDir = false; // Remove old files
		$maxFileAge = 60 * 60; // Temp file age in seconds

		// 5 minutes execution time
		@set_time_limit(5 * 60);
		// usleep(5000);

		// Get parameters
		$chunk = isset($_REQUEST["chunk"]) ? $_REQUEST["chunk"] : 0;
		$chunks = isset($_REQUEST["chunks"]) ? $_REQUEST["chunks"] : 0;
		$fileName = isset($_REQUEST["name"]) ? $_REQUEST["name"] : '';

		// Clean the fileName for security reasons
		//$fileName = preg_replace('/[^\w\._]+/', '', $fileName);

		// Create target dir
		if (!file_exists($p_targetDir))
			@mkdir($p_targetDir);

		// Remove old temp files
		if (is_dir($p_targetDir) && ($dir = opendir($p_targetDir))) {
			while (($file = readdir($dir)) !== false) {
				$filePath = $p_targetDir . DIRECTORY_SEPARATOR . $file;

				// Remove temp files if they are older than the max age
				if (preg_match('/\.tmp$/', $file) && (filemtime($filePath) < time() - $maxFileAge))
					@unlink($filePath);
			}

			closedir($dir);
		} else
			die('{"jsonrpc" : "2.0", "error" : {"code": 100, "message": "Failed to open temp directory."}, "id" : "id"}');

		// Look for the content type header
		if (isset($_SERVER["HTTP_CONTENT_TYPE"]))
			$contentType = $_SERVER["HTTP_CONTENT_TYPE"];

		if (isset($_SERVER["CONTENT_TYPE"]))
			$contentType = $_SERVER["CONTENT_TYPE"];

		if (strpos($contentType, "multipart") !== false) {
			if (isset($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
				// Open temp file
				$out = fopen($p_targetDir . DIRECTORY_SEPARATOR . $fileName, $chunk == 0 ? "wb" : "ab");
				if ($out) {
					// Read binary input stream and append it to temp file
					$in = fopen($_FILES['file']['tmp_name'], "rb");

					if ($in) {
						while ($buff = fread($in, 4096))
							fwrite($out, $buff);
					} else
						die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream."}, "id" : "id"}');

					fclose($out);
					unlink($_FILES['file']['tmp_name']);
				} else
					die('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "Failed to open output stream."}, "id" : "id"}');
			} else
				die('{"jsonrpc" : "2.0", "error" : {"code": 103, "message": "Failed to move uploaded file."}, "id" : "id"}');
		} else {
			// Open temp file
			$out = fopen($p_targetDir . DIRECTORY_SEPARATOR . $fileName, $chunk == 0 ? "wb" : "ab");
			if ($out) {
				// Read binary input stream and append it to temp file
				$in = fopen("php://input", "rb");

				if ($in) {
					while ($buff = fread($in, 4096))
						fwrite($out, $buff);
				} else
					die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream."}, "id" : "id"}');

				fclose($out);
			} else
				die('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "Failed to open output stream."}, "id" : "id"}');
		}

		$audio_file = $p_targetDir . DIRECTORY_SEPARATOR . $fileName;

		$md5 = md5_file($audio_file);
		$duplicate = StoredFile::RecallByMd5($md5);
		if ($duplicate) {
			if (PEAR::isError($duplicate)) {
				die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": ' . $duplicate->getMessage() .'}}');
			}
			else {
                if (file_exists($duplicate->getRealFilePath())) {
				    $duplicateName = $duplicate->getMetadataValue(UI_MDATA_KEY_TITLE);
				    die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "An identical audioclip named ' . $duplicateName . ' already exists in the storage server."}}');
                }
                else {
                    $res = $duplicate->replaceFile($audio_file);
                    if (PEAR::isError($res)) {
				        die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": ' . $duplicate->getMessage() .'}}');
			        }
                    return $duplicate;
                }
			}
		}
		else {

		    global $CC_CONFIG;
		    $stor = $CC_CONFIG["storageDir"];
		    $audio_stor = $stor . DIRECTORY_SEPARATOR . $fileName;

            $md = array();
            $md['MDATA_KEY_MD5'] = $md5;
            $md['MDATA_KEY_FILEPATH'] = $audio_stor;
            $md['MDATA_KEY_TITLE'] = $fileName;

		    StoredFile::Insert($md);
            $r = @chmod($audio_file, 0666);
            $r = @rename($audio_file, $audio_stor);
		}

    }

}

