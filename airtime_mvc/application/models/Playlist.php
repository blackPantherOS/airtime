<?php

require_once 'formatters/LengthFormatter.php';

/**
 *
 * @package Airtime
 * @copyright 2010 Sourcefabric O.P.S.
 * @license http://www.gnu.org/licenses/gpl.txt
 */
class Application_Model_Playlist
{
    /**
     * propel connection object.
     */
    private $con;

    /**
     * unique id for the playlist.
     */
    private $id;

    /**
     * propel object for this playlist.
     */
    private $pl;

    /**
     * info needed to insert a new playlist element.
     */
    private $plItem = array(
        "id" => "",
        "pos" => "",
        "cliplength" => "",
        "cuein" => "00:00:00",
        "cueout" => "00:00:00",
        "fadein" => "0.0",
        "fadeout" => "0.0",
    );

    //using propel's phpNames.
    private $categories = array(
        "dc:title" => "Name",
        "dc:creator" => "Creator",
        "dc:description" => "Description",
        "dcterms:extent" => "Length"
    );
    
    private static $modifier2CriteriaMap = array(
            "contains" => Criteria::ILIKE,
            "does not contain" => Criteria::NOT_ILIKE,
            "is" => Criteria::EQUAL,
            "is not" => Criteria::NOT_EQUAL,
            "starts with" => Criteria::ILIKE,
            "ends with" => Criteria::ILIKE,
            "is greater than" => Criteria::GREATER_THAN,
            "is less than" => Criteria::LESS_THAN,
            "is in the range" => Criteria::CUSTOM);
    
    private static $criteria2PeerMap = array(
            0 => "Select criteria",
            "album_title" => "DbAlbumTitle",
            "artist_name" => "DbArtistName",
            "bit_rate" => "DbBitRate",
            "bpm" => "DbBpm",
            "comments" => "DbComments",
            "composer" => "DbComposer",
            "conductor" => "DbConductor",
            "utime" => "DbUtime",
            "mtime" => "DbMtime",
            "disc_number" => "DbDiscNumber",
            "genre" => "DbGenre",
            "isrc_number" => "DbIsrcNumber",
            "label" => "DbLabel",
            "language" => "DbLanguage",
            "length" => "DbLength",
            "lyricist" => "DbLyricist",
            "mood" => "DbMood",
            "name" => "DbName",
            "orchestra" => "DbOrchestra",
            "radio_station_name" => "DbRadioStation",
            "rating" => "DbRating",
            "sample_rate" => "DbSampleRate",
            "soundcloud_id" => "DbSoundcloudId",
            "track_title" => "DbTrackTitle",
            "track_num" => "DbTrackNum",
            "year" => "DbYear"
    );


    public function __construct($id=null, $con=null)
    {
        if (isset($id)) {
            $this->pl = CcPlaylistQuery::create()->findPK($id);

            if (is_null($this->pl)) {
                throw new PlaylistNotFoundException();
            }
        } else {
            $this->pl = new CcPlaylist();
            $this->pl->setDbUTime("now", new DateTimeZone("UTC"));
            $this->pl->save();
        }

        $defaultFade = Application_Model_Preference::GetDefaultFade();
        if ($defaultFade !== "") {
            //fade is in format SS.uuuuuu

            $this->plItem["fadein"] = $defaultFade;
            $this->plItem["fadeout"] = $defaultFade;
        }

        $this->con = isset($con) ? $con : Propel::getConnection(CcPlaylistPeer::DATABASE_NAME);
        $this->id = $this->pl->getDbId();
    }

    /**
     * Return local ID of virtual file.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

     /**
     * Rename stored virtual playlist
     *
     * @param string $p_newname
     */
    public function setName($p_newname)
    {
        $this->pl->setDbName($p_newname);
        $this->pl->setDbMtime(new DateTime("now", new DateTimeZone("UTC")));
        $this->pl->save($this->con);
    }

     /**
     * Get mnemonic playlist name
     *
     * @return string
     */
    public function getName()
    {
        return $this->pl->getDbName();
    }

    public function setDescription($p_description)
    {
        $this->pl->setDbDescription($p_description);
        $this->pl->setDbMtime(new DateTime("now", new DateTimeZone("UTC")));
        $this->pl->save($this->con);
    }

    public function getDescription()
    {
        return $this->pl->getDbDescription();
    }

    public function getCreator()
    {
        return $this->pl->getCcSubjs()->getDbLogin();
    }

    public function getCreatorId()
    {
        return $this->pl->getCcSubjs()->getDbId();
    }

    public function setCreator($p_id)
    {
        $this->pl->setDbCreatorId($p_id);
        $this->pl->setDbMtime(new DateTime("now", new DateTimeZone("UTC")));
        $this->pl->save($this->con);
    }

    public function getLastModified($format = null)
    {
        return $this->pl->getDbMtime($format);
    }

    public function getSize()
    {
        return $this->pl->countCcPlaylistcontentss();
    }

    /**
     * Get the entire playlist as a two dimentional array, sorted in order of play.
     * @param boolean $filterFiles if this is true, it will only return files that has
     *             file_exists flag set to true
     * @return array
     */
    public function getContents($filterFiles=false)
    {
        Logging::log("Getting contents for playlist {$this->id}");

        $files = array();
        $query = CcPlaylistcontentsQuery::create()
                ->filterByDbPlaylistId($this->id);

        if ($filterFiles) {
            $query->useCcFilesQuery()
                     ->filterByDbFileExists(true)
                  ->endUse();
        }
        $query->orderByDbPosition()
              ->leftJoinWith('CcFiles');
        $rows = $query->find($this->con);

        $i = 0;
        $offset = 0;
        foreach ($rows as $row) {
          $files[$i] = $row->toArray(BasePeer::TYPE_FIELDNAME, true, true);


          $clipSec = Application_Model_Playlist::playlistTimeToSeconds($files[$i]['cliplength']);
          $offset += $clipSec;
          $offset_cliplength = Application_Model_Playlist::secondsToPlaylistTime($offset);

          //format the length for UI.
          $formatter = new LengthFormatter($files[$i]['cliplength']);
          $files[$i]['cliplength'] = $formatter->format();

          $formatter = new LengthFormatter($offset_cliplength);
          $files[$i]['offset'] = $formatter->format();

          $i++;
        }

        return $files;
    }

    /**
    * The database stores fades in 00:00:00 Time format with optional millisecond resolution .000000
    * but this isn't practical since fades shouldn't be very long usuall 1 second or less. This function
    * will normalize the fade so that it looks like 00.000000 to the user.
    **/
    public function normalizeFade($fade)
    {
      //First get rid of the first six characters 00:00: which will be added back later for db update
      $fade = substr($fade, 6);

      //Second add .000000 if the fade does't have milliseconds format already
      $dbFadeStrPos = strpos( $fade, '.' );
      if ( $dbFadeStrPos === False )
         $fade .= '.000000';
      else
         while( strlen( $fade ) < 9 )
            $fade .= '0';

      //done, just need to set back the formated values
      return $fade;
    }

    //aggregate column on playlistcontents cliplength column.
    public function getLength()
    {
        return $this->pl->getDbLength();
    }

    private function insertPlaylistElement($info)
    {
        $row = new CcPlaylistcontents();
        $row->setDbPlaylistId($this->id);
        $row->setDbFileId($info["id"]);
        $row->setDbPosition($info["pos"]);
        $row->setDbCliplength($info["cliplength"]);
        $row->setDbCuein($info["cuein"]);
        $row->setDbCueout($info["cueout"]);
        $row->setDbFadein($info["fadein"]);
        $row->setDbFadeout($info["fadeout"]);
        $row->save($this->con);
        // above save result update on cc_playlist table on length column.
        // but $this->pl doesn't get updated automatically
        // so we need to manually grab it again from DB so it has updated values
        // It is something to do FORMAT_ON_DEMAND( Lazy Loading )
        $this->pl = CcPlaylistQuery::create()->findPK($this->id);
    }

    /*
     *
     */
    private function buildEntry($p_item, $pos)
    {
        $file = CcFilesQuery::create()->findPK($p_item, $this->con);

        if (isset($file) && $file->getDbFileExists()) {
            $entry = $this->plItem;
            $entry["id"] = $file->getDbId();
            $entry["pos"] = $pos;
            $entry["cliplength"] = $file->getDbLength();
            $entry["cueout"] = $file->getDbLength();

            return $entry;
        } else {
            throw new Exception("trying to add a file that does not exist.");
        }
    }
    
    public function isStatic(){
        if ($this->pl->getDbType() == "static") {
            return true;
        } else {
            return false;
        }
    }

    /*
     * @param array $p_items
     *     an array of audioclips to add to the playlist
     * @param int|null $p_afterItem
     *     item which to add the new items after in the playlist, null if added to the end.
     * @param string (before|after) $addAfter
     *      whether to add the clips before or after the selected item.
     */
    public function addAudioClips($p_items, $p_afterItem=NULL, $addType = 'after')
    {
        $this->con->beginTransaction();
        $contentsToUpdate = array();

        try {

            if (is_numeric($p_afterItem)) {
                Logging::log("Finding playlist content item {$p_afterItem}");

                $afterItem = CcPlaylistcontentsQuery::create()->findPK($p_afterItem);
                $index = $afterItem->getDbPosition();
                Logging::log("index is {$index}");
                $pos = ($addType == 'after') ? $index + 1 : $index;

                $contentsToUpdate = CcPlaylistcontentsQuery::create()
                    ->filterByDbPlaylistId($this->id)
                    ->filterByDbPosition($pos, Criteria::GREATER_EQUAL)
                    ->orderByDbPosition()
                    ->find($this->con);

                Logging::log("Adding to playlist");
                Logging::log("at position {$pos}");
            } else {

                //add to the end of the playlist
                if ($addType == 'after') {
                    $pos = $this->getSize();
                }
                //add to the beginning of the playlist.
                else {
                    $pos = 0;

                    $contentsToUpdate = CcPlaylistcontentsQuery::create()
                        ->filterByDbPlaylistId($this->id)
                        ->orderByDbPosition()
                        ->find($this->con);
                }

                $contentsToUpdate = CcPlaylistcontentsQuery::create()
                    ->filterByDbPlaylistId($this->id)
                    ->filterByDbPosition($pos, Criteria::GREATER_EQUAL)
                    ->orderByDbPosition()
                    ->find($this->con);

                Logging::log("Adding to playlist");
                Logging::log("at position {$pos}");
            }

            foreach ($p_items as $ac) {
                Logging::log("Adding audio file {$ac}");

                $res = $this->insertPlaylistElement($this->buildEntry($ac, $pos));
                $pos = $pos + 1;
            }

            //reset the positions of the remaining items.
            for ($i = 0; $i < count($contentsToUpdate); $i++) {
                $contentsToUpdate[$i]->setDbPosition($pos);
                $contentsToUpdate[$i]->save($this->con);
                $pos = $pos + 1;
            }

            $this->pl->setDbMtime(new DateTime("now", new DateTimeZone("UTC")));
            $this->pl->save($this->con);

            $this->con->commit();
        } catch (Exception $e) {
            $this->con->rollback();
            throw $e;
        }
    }

    /**
     * Move audioClip to the new position in the playlist
     *
     * @param array $p_items
     *      array of unique ids of the selected items
     * @param int $p_afterItem
     *      unique id of the item to move the clip after
     */
    public function moveAudioClips($p_items, $p_afterItem=NULL)
    {
        $this->con->beginTransaction();

        try {

            $contentsToMove = CcPlaylistcontentsQuery::create()
                    ->filterByDbId($p_items, Criteria::IN)
                    ->orderByDbPosition()
                    ->find($this->con);

            $otherContent = CcPlaylistcontentsQuery::create()
                    ->filterByDbId($p_items, Criteria::NOT_IN)
                    ->filterByDbPlaylistId($this->id)
                    ->orderByDbPosition()
                    ->find($this->con);

            $pos = 0;
            //moving items to beginning of the playlist.
            if (is_null($p_afterItem)) {
                Logging::log("moving items to beginning of playlist");

                foreach ($contentsToMove as $item) {
                    Logging::log("item {$item->getDbId()} to pos {$pos}");
                    $item->setDbPosition($pos);
                    $item->save($this->con);
                    $pos = $pos + 1;
                }
                foreach ($otherContent as $item) {
                    Logging::log("item {$item->getDbId()} to pos {$pos}");
                    $item->setDbPosition($pos);
                    $item->save($this->con);
                    $pos = $pos + 1;
                }
            } else {
                Logging::log("moving items after {$p_afterItem}");

                foreach ($otherContent as $item) {
                    Logging::log("item {$item->getDbId()} to pos {$pos}");
                    $item->setDbPosition($pos);
                    $item->save($this->con);
                    $pos = $pos + 1;

                    if ($item->getDbId() == $p_afterItem) {
                        foreach ($contentsToMove as $move) {
                            Logging::log("item {$move->getDbId()} to pos {$pos}");
                            $move->setDbPosition($pos);
                            $move->save($this->con);
                            $pos = $pos + 1;
                        }
                    }
                }
            }

            $this->con->commit();
        } catch (Exception $e) {
            $this->con->rollback();
            throw $e;
        }

        $this->pl = CcPlaylistQuery::create()->findPK($this->id);
        $this->pl->setDbMtime(new DateTime("now", new DateTimeZone("UTC")));
        $this->pl->save($this->con);
    }

    /**
     * Remove audioClip from playlist
     *
     * @param array $p_items
     *         array of unique item ids to remove from the playlist..
     */
    public function delAudioClips($p_items)
    {

        $this->con->beginTransaction();

        try {

            CcPlaylistcontentsQuery::create()
                ->findPKs($p_items)
                ->delete($this->con);

            $contents = CcPlaylistcontentsQuery::create()
                ->filterByDbPlaylistId($this->id)
                ->orderByDbPosition()
                ->find($this->con);

            //reset the positions of the remaining items.
            for ($i = 0; $i < count($contents); $i++) {
                $contents[$i]->setDbPosition($i);
                $contents[$i]->save($this->con);
            }

            $this->pl->setDbMtime(new DateTime("now", new DateTimeZone("UTC")));
            $this->pl->save($this->con);

            $this->con->commit();
        } catch (Exception $e) {
            $this->con->rollback();
            throw $e;
        }
    }

    public function getFadeInfo($pos)
    {
        Logging::log("Getting fade info for pos {$pos}");

        $row = CcPlaylistcontentsQuery::create()
            ->joinWith(CcFilesPeer::OM_CLASS)
            ->filterByDbPlaylistId($this->id)
            ->filterByDbPosition($pos)
            ->findOne();
            
        

        #Propel returns values in form 00.000000 format which is for only seconds.
        $fadeIn = $row->getDbFadein();
        $fadeOut = $row->getDbFadeout();
        return array($fadeIn, $fadeOut);
	}

    /**
     * Change fadeIn and fadeOut values for playlist Element
     *
     * @param int $pos
     *         position of audioclip in playlist
     * @param string $fadeIn
     *         new value in ss.ssssss or extent format
     * @param string $fadeOut
     *         new value in ss.ssssss or extent format
     * @return boolean
     */
    public function changeFadeInfo($id, $fadeIn, $fadeOut)
    {
        //See issue CC-2065, pad the fadeIn and fadeOut so that it is TIME compatable with the DB schema
        //For the top level PlayList either fadeIn or fadeOut will sometimes be Null so need a gaurd against
       //setting it to nonNull for checks down below
        $fadeIn = $fadeIn?'00:00:'.$fadeIn:$fadeIn;
        $fadeOut = $fadeOut?'00:00:'.$fadeOut:$fadeOut;

        $this->con->beginTransaction();

        $errArray= array();

        try {
            $row = CcPlaylistcontentsQuery::create()->findPK($id);

            if (is_null($row)) {
                throw new Exception("Playlist item does not exist.");
            }

            $clipLength = $row->getDbCliplength();

            if (!is_null($fadeIn)) {

                $sql = "SELECT INTERVAL '{$fadeIn}' > INTERVAL '{$clipLength}'";
                $r = $this->con->query($sql);
                if ($r->fetchColumn(0)) {
                    //"Fade In can't be larger than overall playlength.";
                    $fadeIn = $clipLength;
                }
                $row->setDbFadein($fadeIn);
            }
            if (!is_null($fadeOut)) {

                $sql = "SELECT INTERVAL '{$fadeOut}' > INTERVAL '{$clipLength}'";
                $r = $this->con->query($sql);
                if ($r->fetchColumn(0)) {
                    //Fade Out can't be larger than overall playlength.";
                    $fadeOut = $clipLength;
                }
                $row->setDbFadeout($fadeOut);
            }

            $row->save($this->con);
            $this->pl->setDbMtime(new DateTime("now", new DateTimeZone("UTC")));
            $this->pl->save($this->con);

            $this->con->commit();
        } catch (Exception $e) {
            $this->con->rollback();
            throw $e;
        }

        return array("fadeIn" => $fadeIn, "fadeOut" => $fadeOut);
    }

    public function setPlaylistfades($fadein, $fadeout)
    {
        if (isset($fadein)) {
            Logging::log("Setting playlist fade in {$fadein}");
            $row = CcPlaylistcontentsQuery::create()
                ->filterByDbPlaylistId($this->id)
                ->filterByDbPosition(0)
                ->findOne($this->con);

            $this->changeFadeInfo($row->getDbId(), $fadein, null);
        }

        if (isset($fadeout)) {
            Logging::log("Setting playlist fade out {$fadeout}");
            $row = CcPlaylistcontentsQuery::create()
                ->filterByDbPlaylistId($this->id)
                ->filterByDbPosition($this->getSize()-1)
                ->findOne($this->con);

            $this->changeFadeInfo($row->getDbId(), null, $fadeout);
        }
    }

    /**
     * Change cueIn/cueOut values for playlist element
     *
     * @param int $pos
     *         position of audioclip in playlist
     * @param string $cueIn
     *         new value in ss.ssssss or extent format
     * @param string $cueOut
     *         new value in ss.ssssss or extent format
     * @return boolean or pear error object
     */
    public function changeClipLength($id, $cueIn, $cueOut)
    {
        $this->con->beginTransaction();

        $errArray= array();

        try {
            if (is_null($cueIn) && is_null($cueOut)) {
                $errArray["error"] = "Cue in and cue out are null.";

                return $errArray;
            }

            $row = CcPlaylistcontentsQuery::create()
                ->joinWith(CcFilesPeer::OM_CLASS)
                ->filterByPrimaryKey($id)
                ->findOne($this->con);

            if (is_null($row)) {
                throw new Exception("Playlist item does not exist.");
            }

            $oldCueIn = $row->getDBCuein();
            $oldCueOut = $row->getDbCueout();
            $fadeIn = $row->getDbFadein();
            $fadeOut = $row->getDbFadeout();

            $file = $row->getCcFiles($this->con);
            $origLength = $file->getDbLength();

            if (!is_null($cueIn) && !is_null($cueOut)) {

                if ($cueOut === "") {
                    $cueOut = $origLength;
                }

                $sql = "SELECT INTERVAL '{$cueIn}' > INTERVAL '{$cueOut}'";
                $r = $this->con->query($sql);
                if ($r->fetchColumn(0)) {
                    $errArray["error"] = "Can't set cue in to be larger than cue out.";

                    return $errArray;
                }

                $sql = "SELECT INTERVAL '{$cueOut}' > INTERVAL '{$origLength}'";
                $r = $this->con->query($sql);
                if ($r->fetchColumn(0)) {
                    $errArray["error"] = "Can't set cue out to be greater than file length.";

                    return $errArray;
                }

                $sql = "SELECT INTERVAL '{$cueOut}' - INTERVAL '{$cueIn}'";
                $r = $this->con->query($sql);
                $cliplength = $r->fetchColumn(0);

                $row->setDbCuein($cueIn);
                $row->setDbCueout($cueOut);
                $row->setDBCliplength($cliplength);

            } elseif (!is_null($cueIn)) {

                $sql = "SELECT INTERVAL '{$cueIn}' > INTERVAL '{$oldCueOut}'";
                $r = $this->con->query($sql);
                if ($r->fetchColumn(0)) {
                    $errArray["error"] = "Can't set cue in to be larger than cue out.";

                    return $errArray;
                }

                $sql = "SELECT INTERVAL '{$oldCueOut}' - INTERVAL '{$cueIn}'";
                $r = $this->con->query($sql);
                $cliplength = $r->fetchColumn(0);

                $row->setDbCuein($cueIn);
                $row->setDBCliplength($cliplength);
            } elseif (!is_null($cueOut)) {

                if ($cueOut === "") {
                    $cueOut = $origLength;
                }

                $sql = "SELECT INTERVAL '{$cueOut}' < INTERVAL '{$oldCueIn}'";
                $r = $this->con->query($sql);
                if ($r->fetchColumn(0)) {
                    $errArray["error"] = "Can't set cue out to be smaller than cue in.";

                    return $errArray;
                }

                $sql = "SELECT INTERVAL '{$cueOut}' > INTERVAL '{$origLength}'";
                $r = $this->con->query($sql);
                if ($r->fetchColumn(0)) {
                    $errArray["error"] = "Can't set cue out to be greater than file length.";

                    return $errArray;
                }

                $sql = "SELECT INTERVAL '{$cueOut}' - INTERVAL '{$oldCueIn}'";
                $r = $this->con->query($sql);
                $cliplength = $r->fetchColumn(0);

                $row->setDbCueout($cueOut);
                $row->setDBCliplength($cliplength);
            }

            $cliplength = $row->getDbCliplength();

            $sql = "SELECT INTERVAL '{$fadeIn}' > INTERVAL '{$cliplength}'";
            $r = $this->con->query($sql);
            if ($r->fetchColumn(0)) {
                $fadeIn = $cliplength;
                $row->setDbFadein($fadeIn);
            }

            $sql = "SELECT INTERVAL '{$fadeOut}' > INTERVAL '{$cliplength}'";
            $r = $this->con->query($sql);
            if ($r->fetchColumn(0)) {
                $fadeOut = $cliplength;
                $row->setDbFadein($fadeOut);
            }

            $row->save($this->con);
            $this->pl->setDbMtime(new DateTime("now", new DateTimeZone("UTC")));
            $this->pl->save($this->con);

            $this->con->commit();
        } catch (Exception $e) {
            $this->con->rollback();
            throw $e;
        }

        return array("cliplength"=> $cliplength, "cueIn"=> $cueIn, "cueOut"=> $cueOut, "length"=> $this->getLength(),
                        "fadeIn"=> $fadeIn, "fadeOut"=> $fadeOut);
    }

    public function getAllPLMetaData()
    {
        $categories = $this->categories;
        $md = array();

        foreach ($categories as $key => $val) {
            $method = 'get' . $val;
            $md[$key] = $this->$method();
        }

        return $md;
    }

    public function getPLMetaData($category)
    {
        $cat = $this->categories[$category];
        $method = 'get' . $cat;

        return $this->$method();
    }

    public function setPLMetaData($category, $value)
    {
        $cat = $this->categories[$category];

        $method = 'set' . $cat;
        $this->$method($value);
    }

    /**
     * This function is used for calculations! Don't modify for display purposes!
     *
     * Convert playlist time value to float seconds
     *
     * @param string $plt
     *         playlist interval value (HH:mm:ss.dddddd)
     * @return int
     *         seconds
     */
    public static function playlistTimeToSeconds($plt)
    {
        $arr =  preg_split('/:/', $plt);
        if (isset($arr[2])) {
          return (intval($arr[0])*60 + intval($arr[1]))*60 + floatval($arr[2]);
        }
        if (isset($arr[1])) {
            return intval($arr[0])*60 + floatval($arr[1]);
        }

        return floatval($arr[0]);
    }


    /**
     *  This function is used for calculations! Don't modify for display purposes!
     *
     * Convert float seconds value to playlist time format
     *
     * @param  float  $seconds
     * @return string
     *         interval in playlist time format (HH:mm:ss.d)
     */
    public static function secondsToPlaylistTime($p_seconds)
    {
        $info = explode('.', $p_seconds);
        $seconds = $info[0];
        if (!isset($info[1])) {
            $milliStr = 0;
        } else {
            $milliStr = $info[1];
        }
        $hours = floor($seconds / 3600);
        $seconds -= $hours * 3600;
        $minutes = floor($seconds / 60);
        $seconds -= $minutes * 60;

        $res = sprintf("%02d:%02d:%02d.%s", $hours, $minutes, $seconds, $milliStr);

       return $res;
    }

    public static function getPlaylistCount()
    {
        global $CC_CONFIG;
        $con = Propel::getConnection();
        $sql = 'SELECT count(*) as cnt FROM '.$CC_CONFIG["playListTable"];

        return $con->query($sql)->fetchColumn(0);
    }

    /**
     * Delete the file from all playlists.
     * @param string $p_fileId
     */
    public static function DeleteFileFromAllPlaylists($p_fileId)
    {
        CcPlaylistcontentsQuery::create()->filterByDbFileId($p_fileId)->delete();
    }

    /**
     * Delete playlists that match the ids..
     * @param array $p_ids
     */
    public static function deletePlaylists($p_ids, $p_userId)
    {
        $leftOver = self::playlistsNotOwnedByUser($p_ids, $p_userId);
        if (count($leftOver) == 0) {
            CcPlaylistQuery::create()->findPKs($p_ids)->delete();
        } else {
            throw new PlaylistNoPermissionException;
        }
    }
    
    // This function returns that are not owen by $p_user_id among $p_ids 
    private static function playlistsNotOwnedByUser($p_ids, $p_userId){
        $ownedByUser = CcPlaylistQuery::create()->filterByDbCreatorId($p_userId)->find()->getData();
        $selectedPls = $p_ids;
        $ownedPls = array();
        foreach ($ownedByUser as $pl) {
            if (in_array($pl->getDbId(), $selectedPls)) {
                $ownedPls[] = $pl->getDbId();
            }
        }
        
        $leftOvers = array_diff($selectedPls, $ownedPls);
        return $leftOvers;
    }
    
    /**
     * Delete all files from playlist
     * @param int $p_playlistId
     */
    public function deleteAllFilesFromPlaylist()
    {
        CcPlaylistcontentsQuery::create()->findByDbPlaylistId($this->id)->delete();
    }
    
    
    // smart playlist functions start
    public function shuffleSmartPlaylist(){
        // if it here that means it's static pl
        $this->saveType("static");
        $contents = CcPlaylistcontentsQuery::create()
        ->filterByDbPlaylistId($this->id)
        ->orderByDbPosition()
        ->find();
        $shuffledPos = range(0, count($contents)-1);
        shuffle($shuffledPos);
        $temp = new CcPlaylist();
        foreach ($contents as $item) {
            $item->setDbPosition(array_shift($shuffledPos));
            $item->save();
        }
        return array("result"=>0);
    }
    
    public function saveType($p_playlistType)
    {
        // saving dynamic/static flag
        CcPlaylistQuery::create()->findPk($this->id)->setDbType($p_playlistType)->save();
    }
    
    /**
     * Saves smart playlist criteria
     * @param array $p_criteria
     */
    public function saveSmartPlaylistCriteria($p_criteria)
    {
        $data = $this->organizeSmartPlyalistCriteria($p_criteria);
        // things we need to check
        // 1. limit value shouldn't be empty and has upperbound of 24 hrs
        // 2. sp_criteria or sp_criteria_modifier shouldn't be 0
        // 3. validate formate according to DB column type
        $multiplier = 1;
        $result = 0;
        $errors = array();
        $error = array();
        
        // saving dynamic/static flag
        $playlistType = $data['etc']['sp_type'] == 0 ? 'static':'dynamic';
        $this->saveType($playlistType);
        
        // validation start
        if ($data['etc']['sp_limit_options'] == 'hours') {
            $multiplier = 60;
        }
        if ($data['etc']['sp_limit_options'] == 'hours' || $data['etc']['sp_limit_options'] == 'mins') {
            if ($data['etc']['sp_limit_value'] == "" || floatval($data['etc']['sp_limit_value']) <= 0) {
                $error[] =  "Limit cannot be empty or smaller than 0";
            } else {
                $mins = $data['etc']['sp_limit_value'] * $multiplier;
                if ($mins > 14400) {
                    $error[] =  "Limit cannot be more than 24 hrs";
                }
            }
        } else {
            if ($data['etc']['sp_limit_value'] == "" || floatval($data['etc']['sp_limit_value']) <= 0) {
                $error[] =  "Limit cannot be empty or smaller than 0";
            } else if (floatval($data['etc']['sp_limit_value']) < 1) {
                $error[] =  "The value should be an integer";
            }
        }
        
        if (count($error) > 0){
            $errors[] = array("element"=>"sp_limit_value", "msg"=>$error);
        }

        foreach ($data['criteria'] as $key=>$d){
            $error = array();
            $column = CcFilesPeer::getTableMap()->getColumnByPhpName(self::$criteria2PeerMap[$d['sp_criteria_field']]);
            // check for not selected select box
            if ($d['sp_criteria_field'] == "0" || $d['sp_criteria_modifier'] == "0"){
                $error[] =  "You must select Criteria and Modifier";
            } else {
                // validation on type of column
                if ($d['sp_criteria_field'] == 'length') {
                    if (!preg_match("/(\d{2}):(\d{2}):(\d{2})/", $d['sp_criteria_value'])) {
                        $error[] =  "'Length' should be in '00:00:00' format";
                    }
                } else if ($column->getType() == PropelColumnTypes::TIMESTAMP) {
                    if (!preg_match("/(\d{4})-(\d{2})-(\d{2})/", $d['sp_criteria_value'])) {
                        $error[] =  "The value should be in timestamp format(eg. 0000-00-00 or 00-00-00 00:00:00";
                    } else if (!Application_Common_DateHelper::checkDateTimeRangeForSQL($d['sp_criteria_value'])) {
                        // check for if it is in valid range( 1753-01-01 ~ 12/31/9999 )
                        $error[] =  "$d[sp_criteria_value] is not a valid date/time string";
                    }
                    
                    if (isset($d['sp_criteria_extra'])) {
                        if (!preg_match("/(\d{4})-(\d{2})-(\d{2})/", $d['sp_criteria_extra'])) {
                            $error[] =  "The value should be in timestamp format(eg. 0000-00-00 or 00-00-00 00:00:00";
                        } else if (!Application_Common_DateHelper::checkDateTimeRangeForSQL($d['sp_criteria_extra'])) {
                            // check for if it is in valid range( 1753-01-01 ~ 12/31/9999 )
                            $error[] =  "$d[sp_criteria_extra] is not a valid date/time string";
                        }
                    }
                } else if ($column->getType() == PropelColumnTypes::INTEGER) {
                    if (!is_numeric($d['sp_criteria_value'])) {
                        $error[] = "The value has to be numeric";
                    }
                    // length check
                    if (intval($d['sp_criteria_value']) >= pow(2,31)) {
                        $error[] = "The value should be less then 2147483648";
                    }
                } else if ($column->getType() == PropelColumnTypes::VARCHAR) {
                    if (strlen($d['sp_criteria_value']) > $column->getSize()) {
                        $error[] = "The value should be less ".$column->getSize()." characters";
                    }
                }
            }
            
            if ($d['sp_criteria_value'] == "") {
                $error[] =  "Value cannot be empty";
            }
            if(count($error) > 0){
                $errors[] = array("element"=>"sp_criteria_field_".$key, "msg"=>$error);
            }
        }
        $result = count($errors) > 0 ? 1 :0;
        if ($result == 0) {
            $this->storeCriteriaIntoDb($data);
        }
        
        //get number of files that meet the criteria
        $files = $this->getListofFilesMeetCriteria();
        
        return array("result"=>$result, "errors"=>$errors, "poolCount"=>$files["count"]);
    }
    
    public function storeCriteriaIntoDb($p_criteriaData){
        // delete criteria under $p_playlistId
        CcPlaylistcriteriaQuery::create()->findByDbPlaylistId($this->id)->delete();
        
        foreach( $p_criteriaData['criteria'] as $d){
            $qry = new CcPlaylistcriteria();
            $qry->setDbCriteria($d['sp_criteria_field'])
                ->setDbModifier($d['sp_criteria_modifier'])
                ->setDbValue($d['sp_criteria_value'])
                ->setDbPlaylistId($this->id);
            
            if (isset($d['sp_criteria_extra'])) {
                $qry->setDbExtra($d['sp_criteria_extra']);
            }
            $qry->save();
        }
        
        // insert limit info
        $qry = new CcPlaylistcriteria();
        $qry->setDbCriteria("limit")
            ->setDbModifier($p_criteriaData['etc']['sp_limit_options'])
            ->setDbValue($p_criteriaData['etc']['sp_limit_value'])
            ->setDbPlaylistId($this->id)
            ->save();
    }
    
    /**
     * generate list of tracks. This function saves creiteria and generate
     * tracks.
     * @param array $p_criteria
     */
    public function generateSmartPlaylist($p_criteria, $returnList=false)
    {
        $result = $this->saveSmartPlaylistCriteria($p_criteria);
        if ($result['result'] != 0) {
            return $result;
        } else {
            $insertList = $this->getListOfFilesUnderLimit();
            $this->deleteAllFilesFromPlaylist();
            $this->addAudioClips(array_keys($insertList));
            return array("result"=>0);
        }
    }
    
    public function getListOfFilesUnderLimit()
    {
        $info = $this->getListofFilesMeetCriteria();
        $files = $info['files'];
        $limit = $info['limit'];
        
        $insertList = array();
        $totalTime = 0;
        
        // this moves the pointer to the first element in the collection
        $files->getFirst();
        $iterator = $files->getIterator();
        while ($iterator->valid() && $totalTime < $limit['time']) {
            $id = $iterator->current()->getDbId();
            $length = Application_Common_DateHelper::calculateLengthInSeconds($iterator->current()->getDbLength());
            $insertList[$id] = $length;
            $totalTime += $length;
            if ( !is_null($limit['items']) && $limit['items'] == count($insertList)) {
                break;
            }
            
            $iterator->next();
        }
        return $insertList;
    }
    
    // this function return list of propel object
    public function getListofFilesMeetCriteria()
    {
        $out = CcPlaylistcriteriaQuery::create()->findByDbPlaylistId($this->id);
        $storedCrit = array();
        foreach ($out as $crit) {
            $criteria = $crit->getDbCriteria();
            $modifier = $crit->getDbModifier();
            $value = $crit->getDbValue();
            $extra = $crit->getDbExtra();
        
            if($criteria == "limit"){
                $storedCrit["limit"] = array("value"=>$value, "modifier"=>$modifier);
            }else{
                $storedCrit["crit"][] = array("criteria"=>$criteria, "value"=>$value, "modifier"=>$modifier, "extra"=>$extra);
            }
        }
        
        $qry = CcFilesQuery::create();
        foreach ($storedCrit["crit"] as $criteria) {
            $spCriteriaPhpName = self::$criteria2PeerMap[$criteria['criteria']];
            $spCriteria = $criteria['criteria'];
            
            $spCriteriaModifier = $criteria['modifier'];
            $spCriteriaValue = $criteria['value'];
            
            // change date/time to UTC is the column time is timestamp
            if (CcFilesPeer::getTableMap()->getColumnByPhpName($spCriteriaPhpName)->getType() == PropelColumnTypes::TIMESTAMP) {
                $spCriteriaValue = Application_Common_DateHelper::ConvertToUtcDateTimeString($spCriteriaValue);
            }
            
            if ($spCriteriaModifier == "starts with") {
                $spCriteriaValue = "$spCriteriaValue%";
            } else if ($spCriteriaModifier == "ends with") {
                $spCriteriaValue = "%$spCriteriaValue";
            } else if ($spCriteriaModifier == "contains" || $spCriteriaModifier == "does not contain") {
                $spCriteriaValue = "%$spCriteriaValue%";
            } else if ($spCriteriaModifier == "is in the range") {
                $spCriteriaValue = "$spCriteria > '$spCriteriaValue' AND $spCriteria < '$criteria[extra]'";
            }
            $spCriteriaModifier = self::$modifier2CriteriaMap[$spCriteriaModifier];
            try{
                $qry->filterBy($spCriteriaPhpName, $spCriteriaValue, $spCriteriaModifier);
                $qry->addAscendingOrderByColumn('random()');
            }catch (Exception $e){
                Logging::log($e);
            }
        }
        // construct limit restriction
        $limits = array();
        if ($storedCrit['limit']['modifier'] == "items") {
            $limits['time'] = 1440 * 60;
            $limits['items'] = $storedCrit['limit']['value'];
        } else {
            $limits['time'] = $storedCrit['limit']['modifier'] == "hours" ? intval($storedCrit['limit']['value']) * 60 * 60 : intval($storedCrit['limit']['value'] * 60);
            $limits['items'] = null;
        }
        try{
            $out = $qry->setFormatter(ModelCriteria::FORMAT_ON_DEMAND)->find();
            return array("files"=>$out, "limit"=>$limits, "count"=>$out->count());
        }catch(Exception $e){
            Logging::log($e);
        }
                
    }
    
    private static function organizeSmartPlyalistCriteria($p_criteria)
    {
        $fieldNames = array('sp_criteria_field', 'sp_criteria_modifier', 'sp_criteria_value', 'sp_criteria_extra');
        $output = array();
        foreach ($p_criteria as $ele) {
            $index = strrpos($ele['name'], '_');
            $fieldName = substr($ele['name'], 0, $index);
            if (in_array($fieldName, $fieldNames)) {
                $rowNum = intval(substr($ele['name'], $index+1));
                $output['criteria'][$rowNum][$fieldName] = trim($ele['value']);
            }else{
                $output['etc'][$ele['name']] = $ele['value'];
            }
        }
        
        return $output;
    }
    // smart playlist functions end

} // class Playlist

class PlaylistNotFoundException extends Exception {}
class PlaylistNoPermissionException extends Exception {}
class PlaylistOutDatedException extends Exception {}
class PlaylistDyanmicException extends Exception {}
