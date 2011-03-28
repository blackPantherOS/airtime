<?php
require_once("StoredFile.php");

class ScheduleGroup {

    private $groupId;

    public function __construct($p_groupId = null) {
        $this->groupId = $p_groupId;
    }

    /**
     * Return true if the schedule group exists in the DB.
     * @return boolean
     */
    public function exists() {
        global $CC_CONFIG, $CC_DBC;
        $sql = "SELECT COUNT(*) FROM ".$CC_CONFIG['scheduleTable']
                ." WHERE group_id=".$this->groupId;
        $result = $CC_DBC->GetOne($sql);
        if (PEAR::isError($result)) {
            return $result;
        }
        return $result != "0";
    }

    /**
     * Add a music clip or playlist to the schedule.
     *
     * @param $p_datetime
     *    In the format YYYY-MM-DD HH:MM:SS.mmmmmm
     * @param $p_audioFileId
     *    (optional, either this or $p_playlistId must be set) DB ID of the audio file
     * @param $p_playlistId
     *    (optional, either this of $p_audioFileId must be set) DB ID of the playlist
     * @param $p_options
     *    Does nothing at the moment.
     *
     * @return int|PEAR_Error
     *    Return PEAR_Error if the item could not be added.
     *    Error code 555 is a scheduling conflict.
     */
    public function add($show_instance, $p_datetime, $p_audioFileId = null, $p_playlistId = null, $p_options = null) {
        global $CC_CONFIG, $CC_DBC;

        if (!is_null($p_audioFileId)) {
            // Schedule a single audio track

            // Load existing track
            $track = StoredFile::Recall($p_audioFileId);
            if (is_null($track)) {
                return new PEAR_Error("Could not find audio track.");
            }

            // Check if there are any conflicts with existing entries
            $metadata = $track->getMetadata();
            $length = trim($metadata["length"]);
            if (empty($length)) {
                return new PEAR_Error("Length is empty.");
            }
           
            // Insert into the table
            $this->groupId = $CC_DBC->GetOne("SELECT nextval('schedule_group_id_seq')");
           
            $sql = "INSERT INTO ".$CC_CONFIG["scheduleTable"]
            ." (instance_id, starts, ends, clip_length, group_id, file_id, cue_out)"
            ." VALUES ($show_instance, TIMESTAMP '$p_datetime', "
            ." (TIMESTAMP '$p_datetime' + INTERVAL '$length'),"
            ." '$length',"
            ." {$this->groupId}, $p_audioFileId, '$length')";
            $result = $CC_DBC->query($sql);
            if (PEAR::isError($result)) {
                //var_dump($sql);
                return $result;
            }

        } 
        elseif (!is_null($p_playlistId)){
            // Schedule a whole playlist

            // Load existing playlist
            $playlist = Playlist::Recall($p_playlistId);
            if (is_null($playlist)) {
                return new PEAR_Error("Could not find playlist.");
            }

            // Check if there are any conflicts with existing entries
            $length = trim($playlist->getLength());
            //var_dump($length);
            if (empty($length)) {
                return new PEAR_Error("Length is empty.");
            }

            // Insert all items into the schedule
            $this->groupId = $CC_DBC->GetOne("SELECT nextval('schedule_group_id_seq')");
            $itemStartTime = $p_datetime;

            $plItems = $playlist->getContents();
            //var_dump($plItems);
            foreach ($plItems as $row) {
                $trackLength = $row["cliplength"];
                //var_dump($trackLength);
                $sql = "INSERT INTO ".$CC_CONFIG["scheduleTable"]
                ." (instance_id, playlist_id, starts, ends, group_id, file_id,"
                ." clip_length, cue_in, cue_out, fade_in, fade_out)"
                ." VALUES ($show_instance, $p_playlistId, TIMESTAMP '$itemStartTime', "
                ." (TIMESTAMP '$itemStartTime' + INTERVAL '$trackLength'),"
                ." '{$this->groupId}', '{$row['file_id']}', '$trackLength', '{$row['cuein']}',"
                ." '{$row['cueout']}', '{$row['fadein']}','{$row['fadeout']}')";
                $result = $CC_DBC->query($sql);
                if (PEAR::isError($result)) {
                    //var_dump($sql);
                    return $result;
                }
                $itemStartTime = $CC_DBC->getOne("SELECT TIMESTAMP '$itemStartTime' + INTERVAL '$trackLength'");
            }
        }
        RabbitMq::PushSchedule();
        return $this->groupId;
    }

    public function addFileAfter($show_instance, $p_groupId, $p_audioFileId) {
        global $CC_CONFIG, $CC_DBC;
        // Get the end time for the given entry
        $sql = "SELECT MAX(ends) FROM ".$CC_CONFIG["scheduleTable"]
        ." WHERE group_id=$p_groupId";
        $startTime = $CC_DBC->GetOne($sql);
        return $this->add($show_instance, $startTime, $p_audioFileId);
    }

    public function addPlaylistAfter($show_instance, $p_groupId, $p_playlistId) {
        global $CC_CONFIG, $CC_DBC;
        // Get the end time for the given entry
        $sql = "SELECT MAX(ends) FROM ".$CC_CONFIG["scheduleTable"]
        ." WHERE group_id=$p_groupId";

        $startTime = $CC_DBC->GetOne($sql);
        return $this->add($show_instance, $startTime, null, $p_playlistId);
    }

    /**
     * Remove the group from the schedule.
     * Note: does not check if it is in the past, you can remove anything.
     *
     * @return boolean
     *    TRUE on success, false if there is no group ID defined.
     */
    public function remove() {
        global $CC_CONFIG, $CC_DBC;
        if (is_null($this->groupId) || !is_numeric($this->groupId)) {
            return false;
        }
        $sql = "DELETE FROM ".$CC_CONFIG["scheduleTable"]
        ." WHERE group_id = ".$this->groupId;
        //echo $sql;
        $retVal = $CC_DBC->query($sql);
        RabbitMq::PushSchedule();
        return $retVal;
    }

    /**
     * Return the number of items in this group.
     * @return string
     */
    public function count() {
        global $CC_CONFIG, $CC_DBC;
        $sql = "SELECT COUNT(*) FROM {$CC_CONFIG['scheduleTable']}"
        ." WHERE group_id={$this->groupId}";
        return $CC_DBC->GetOne($sql);
    }

    /*
     * Return the list of items in this group as a 2D array.
     * @return array
     */
    public function getItems() {
        global $CC_CONFIG, $CC_DBC;
        $sql = "SELECT "
        ." st.id,"
        ." st.file_id,"
        ." st.cue_in,"
        ." st.cue_out,"
        ." st.clip_length,"
        ." st.fade_in,"
        ." st.fade_out"
        ." FROM $CC_CONFIG[scheduleTable] as st"
        ." LEFT JOIN $CC_CONFIG[showInstances] as si"
        ." ON st.instance_id = si.id"
        ." WHERE st.group_id=$this->groupId"
        ." AND st.starts < si.ends";
        return $CC_DBC->GetAll($sql);
    }

    public function notifyGroupStartPlay() {
        global $CC_CONFIG, $CC_DBC;
        $sql = "UPDATE ".$CC_CONFIG['scheduleTable']
                ." SET schedule_group_played=TRUE"
                ." WHERE group_id=".$this->groupId;
        $retVal = $CC_DBC->query($sql);
        return $retVal;
    }

    public function notifyMediaItemStartPlay($p_fileId) {
        global $CC_CONFIG, $CC_DBC;
        $sql = "UPDATE ".$CC_CONFIG['scheduleTable']
                ." SET media_item_played=TRUE"
                ." WHERE group_id=".$this->groupId
                ." AND file_id=".pg_escape_string($p_fileId);
        $retVal = $CC_DBC->query($sql);
        return $retVal;
    }
}

class Schedule {

    function __construct() {

    }

    /**
     * Return true if there is nothing in the schedule for the given start time
     * up to the length of time after that.
     *
     * @param string $p_datetime
     *    In the format YYYY-MM-DD HH:MM:SS.mmmmmm
     * @param string $p_length
     *    In the format HH:MM:SS.mmmmmm
     * @return boolean|PEAR_Error
     */
    public static function isScheduleEmptyInRange($p_datetime, $p_length) {
        global $CC_CONFIG, $CC_DBC;
        if (empty($p_length)) {
            return new PEAR_Error("Schedule::isSchedulerEmptyInRange: param p_length is empty.");
        }
        $sql = "SELECT COUNT(*) FROM ".$CC_CONFIG["scheduleTable"]
        ." WHERE (starts >= '$p_datetime') "
        ." AND (ends <= (TIMESTAMP '$p_datetime' + INTERVAL '$p_length'))";
        //$_SESSION["debug"] = $sql;
        //echo $sql;
        $count = $CC_DBC->GetOne($sql);
        //var_dump($count);
        return ($count == '0');
    }

    public static function getTimeUnScheduledInRange($instance_id, $s_datetime, $e_datetime) {
        global $CC_CONFIG, $CC_DBC;

        $sql = "SELECT SUM(clip_length) FROM $CC_CONFIG[scheduleTable]"
            ." WHERE instance_id = $instance_id";

        $time = $CC_DBC->GetOne($sql);

        if(is_null($time))
            $time = 0;

        $sql = "SELECT TIMESTAMP '{$e_datetime}' - TIMESTAMP '{$s_datetime}'";
        $length = $CC_DBC->GetOne($sql);

        $sql = "SELECT INTERVAL '{$length}' - INTERVAL '{$time}'";
        $time_left =$CC_DBC->GetOne($sql);

        return $time_left;
    }

    public static function getTimeScheduledInRange($s_datetime, $e_datetime) {
        global $CC_CONFIG, $CC_DBC;

        $sql = "SELECT SUM(clip_length) FROM ".$CC_CONFIG["scheduleTable"]."
            WHERE (starts >= '{$s_datetime}')
            AND (ends <= '{$e_datetime}')";

        $res = $CC_DBC->GetOne($sql);

        if(is_null($res))
            return 0;

        return $res;
    }

    public static function GetTotalShowTime($instance_id) {
        global $CC_CONFIG, $CC_DBC;

        $sql = "SELECT SUM(clip_length) FROM $CC_CONFIG[scheduleTable]"
            ." WHERE instance_id = $instance_id";

        $res = $CC_DBC->GetOne($sql);

        if(is_null($res))
            return 0;

        return $res;
    }

    public static function GetPercentScheduled($instance_id, $s_datetime, $e_datetime)
    {
        $time = Schedule::GetTotalShowTime($instance_id);

        $s_epoch = strtotime($s_datetime);
        $e_epoch = strtotime($e_datetime);

        $con = Propel::getConnection(CcSchedulePeer::DATABASE_NAME);
        $sql = "SELECT EXTRACT(EPOCH FROM INTERVAL '{$time}')";
        $r = $con->query($sql);
        $i_epoch = $r->fetchColumn(0);

        $percent = ceil(($i_epoch / ($e_epoch - $s_epoch)) * 100);

        if ($percent > 100)
            $percent = 100;

        return $percent;
    }


    /**
     * Return TRUE if file is going to be played in the future.
     *
     * @param string $p_fileId
     */
    public function IsFileScheduledInTheFuture($p_fileId)
    {
        global $CC_CONFIG, $CC_DBC;
        $sql = "SELECT COUNT(*) FROM ".$CC_CONFIG["scheduleTable"]
        ." WHERE file_id = {$p_fileId} AND starts > NOW()";
        $count = $CC_DBC->GetOne($sql);
        if (is_numeric($count) && ($count != '0')) {
            return TRUE;
        } else {
            return FALSE;
        }
    }


    /**
     * Returns array indexed by:
     *    "playlistId"/"playlist_id" (aliases to the same thing)
     *    "start"/"starts" (aliases to the same thing) as YYYY-MM-DD HH:MM:SS.nnnnnn
     *    "end"/"ends" (aliases to the same thing) as YYYY-MM-DD HH:MM:SS.nnnnnn
     *    "group_id"/"id" (aliases to the same thing)
     *    "clip_length" (for audio clips this is the length of the audio clip,
     *                   for playlists this is the length of the entire playlist)
     *    "name" (playlist only)
     *    "creator" (playlist only)
     *    "file_id" (audioclip only)
     *    "count" (number of items in the playlist, always 1 for audioclips.
     *      Note that playlists with one item will also have count = 1.
     *
     * @param string $p_fromDateTime
     *    In the format YYYY-MM-DD HH:MM:SS.nnnnnn
     * @param string $p_toDateTime
     *    In the format YYYY-MM-DD HH:MM:SS.nnnnnn
     * @param boolean $p_playlistsOnly
     *    Retrieve playlists as a single item.
     * @return array
     *      Returns empty array if nothing found
     */

    public static function GetItems($p_currentDateTime, $p_toDateTime, $p_playlistsOnly = true)
    {
        global $CC_CONFIG, $CC_DBC;
        $rows = array();
        if (!$p_playlistsOnly) {
            $sql = "SELECT * FROM ".$CC_CONFIG["scheduleTable"]
            ." WHERE (starts >= TIMESTAMP '$p_currentDateTime') "
            ." AND (ends <= TIMESTAMP '$p_toDateTime')";
            $rows = $CC_DBC->GetAll($sql);
            foreach ($rows as &$row) {
                $row["count"] = "1";
                $row["playlistId"] = $row["playlist_id"];
                $row["start"] = $row["starts"];
                $row["end"] = $row["ends"];
                $row["id"] = $row["group_id"];
            }
        } else {
            $sql = "SELECT MIN(st.name) AS name,"
            ." MIN(pt.creator) AS creator,"
            ." st.group_id, "
            ." SUM(st.clip_length) AS clip_length,"
            ." MIN(st.file_id) AS file_id,"
            ." COUNT(*) as count,"
            ." MIN(st.playlist_id) AS playlist_id,"
            ." MIN(st.starts) AS starts,"
            ." MAX(st.ends) AS ends,"
            ." MIN(sh.name) AS show_name"
            ." FROM $CC_CONFIG[scheduleTable] as st"
            ." LEFT JOIN $CC_CONFIG[playListTable] as pt"
            ." ON st.playlist_id = pt.id"
            ." LEFT JOIN $CC_CONFIG[showInstances] as si"
            ." ON st.instance_id = si.id"
            ." LEFT JOIN $CC_CONFIG[showTable] as sh"
            ." ON si.show_id = sh.id"
            ." WHERE (st.ends >= TIMESTAMP '$p_currentDateTime')"
            ." AND (st.ends <= TIMESTAMP '$p_toDateTime')"
            //next line makes sure that we aren't returning items that
            //are past the show's scheduled timeslot.
            ." AND (st.starts < si.ends)"
            ." GROUP BY st.group_id"
            ." ORDER BY starts";

            $rows = $CC_DBC->GetAll($sql);
            if (!PEAR::isError($rows)) {
                foreach ($rows as &$row) {
                    $row["playlistId"] = $row["playlist_id"];
                    $row["start"] = $row["starts"];
                    $row["end"] = $row["ends"];
                    $row["id"] = $row["group_id"];
                }
            }
        }
        return $rows;
    }

    /**
     * Returns data related to the scheduled items.
     *
     * @param int $prev
     * @param int $next
     * @return date
     */
    public static function GetPlayOrderRange($prev = 1, $next = 1)
    {
        if (!is_int($prev) || !is_int($next)){
            //must enter integers to specify ranges
            return array();
        }

        global $CC_CONFIG;

        $date = new Application_Model_DateHelper;
        $timeNow = $date->getDate();
        return array("env"=>APPLICATION_ENV,
            "schedulerTime"=>gmdate("Y-m-d H:i:s"),
            "previous"=>Schedule::GetScheduledItemData($timeNow, -1, $prev, "24 hours"),
            "current"=>Schedule::GetScheduledItemData($timeNow, 0),
            "next"=>Schedule::GetScheduledItemData($timeNow, 1, $next, "48 hours"),
            "currentShow"=>Show_DAL::GetCurrentShow($timeNow),
            "nextShow"=>Show_DAL::GetNextShows($timeNow, 1),
            "timezone"=> date("T"),
            "timezoneOffset"=> date("Z"),
            "apiKey"=>$CC_CONFIG['apiKey'][0]);
    }

    /**
     * Builds an SQL Query for accessing scheduled item information from
     * the database.
     *
     * @param int $timeNow
     * @param int $timePeriod
     * @param int $count
     * @param String $interval
     * @return date
     *
     * $timeNow is the the currentTime in the format "Y-m-d H:i:s".
     * For example: 2011-02-02 22:00:54
     *
     * $timePeriod can be either negative, zero or positive. This is used
     * to indicate whether we want items from the past, present or future.
     *
     * $count indicates how many results we want to limit ourselves to.
     *
     * $interval is used to indicate how far into the past or future we
     * want to search the database. For example "5 days", "18 hours", "60 minutes",
     * "30 seconds" etc.
     */
    public static function GetScheduledItemData($timeStamp, $timePeriod=0, $count = 0, $interval="0 hours")
    {
        global $CC_CONFIG, $CC_DBC;

        $sql = "SELECT DISTINCT pt.name, ft.track_title, ft.artist_name, ft.album_title, st.starts, st.ends, st.clip_length, st.media_item_played, st.group_id, show.name as show_name, st.instance_id"
        ." FROM $CC_CONFIG[scheduleTable] st, $CC_CONFIG[filesTable] ft, $CC_CONFIG[playListTable] pt, $CC_CONFIG[showInstances] si, $CC_CONFIG[showTable] show"
        ." WHERE st.playlist_id = pt.id"
        ." AND st.file_id = ft.id"
        ." AND st.instance_id = si.id"
        ." AND si.show_id = show.id"
        ." AND st.starts < si.ends";

        if ($timePeriod < 0){
        	$sql .= " AND st.ends < TIMESTAMP '$timeStamp'"
        	." AND st.ends > (TIMESTAMP '$timeStamp' - INTERVAL '$interval')"
  	        ." ORDER BY st.starts DESC"
        	." LIMIT $count";
		} else if ($timePeriod == 0){
	        $sql .= " AND st.starts <= TIMESTAMP '$timeStamp'"
    	    ." AND st.ends >= TIMESTAMP '$timeStamp'";
		} else if ($timePeriod > 0){
        	$sql .= " AND st.starts > TIMESTAMP '$timeStamp'"
        	." AND st.starts < (TIMESTAMP '$timeStamp' + INTERVAL '$interval')"
        	." ORDER BY st.starts"
        	." LIMIT $count";
		}

        $rows = $CC_DBC->GetAll($sql);
        return $rows;
	}

    public static function GetShowInstanceItems($instance_id)
    {
        global $CC_CONFIG, $CC_DBC;

        $sql = "SELECT DISTINCT pt.name, ft.track_title, ft.artist_name, ft.album_title, st.starts, st.ends, st.clip_length, st.media_item_played, st.group_id, show.name as show_name, st.instance_id"
        ." FROM $CC_CONFIG[scheduleTable] st, $CC_CONFIG[filesTable] ft, $CC_CONFIG[playListTable] pt, $CC_CONFIG[showInstances] si, $CC_CONFIG[showTable] show"
        ." WHERE st.playlist_id = pt.id"
        ." AND st.file_id = ft.id"
        ." AND st.instance_id = si.id"
        ." AND si.show_id = show.id"
        ." AND instance_id = $instance_id"
        ." ORDER BY st.starts";

        $rows = $CC_DBC->GetAll($sql);
        return $rows;
    }

    public static function UpdateMediaPlayedStatus($p_id)
    {
        global $CC_CONFIG, $CC_DBC;
        $sql = "UPDATE ".$CC_CONFIG['scheduleTable']
                ." SET media_item_played=TRUE"
                ." WHERE id=$p_id";
        $retVal = $CC_DBC->query($sql);
        return $retVal;
    }


    /**
     * Convert a time string in the format "YYYY-MM-DD HH:mm:SS"
     * to "YYYY-MM-DD-HH-mm-SS".
     *
     * @param string $p_time
     * @return string
     */
    private static function AirtimeTimeToPypoTime($p_time)
    {
        $p_time = substr($p_time, 0, 19);
        $p_time = str_replace(" ", "-", $p_time);
        $p_time = str_replace(":", "-", $p_time);
        return $p_time;
    }

    /**
     * Convert a time string in the format "YYYY-MM-DD-HH-mm-SS" to
     * "YYYY-MM-DD HH:mm:SS".
     *
     * @param string $p_time
     * @return string
     */
    private static function PypoTimeToAirtimeTime($p_time)
    {
        $t = explode("-", $p_time);
        return $t[0]."-".$t[1]."-".$t[2]." ".$t[3].":".$t[4].":00";
    }

    /**
     * Return true if the input string is in the format YYYY-MM-DD-HH-mm
     *
     * @param string $p_time
     * @return boolean
     */
    public static function ValidPypoTimeFormat($p_time)
    {
        $t = explode("-", $p_time);
        if (count($t) != 5) {
            return false;
        }
        foreach ($t as $part) {
            if (!is_numeric($part)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Converts a time value as a string (with format HH:MM:SS.mmmmmm) to
     * millisecs.
     *
     * @param string $p_time
     * @return int
     */
    private static function WallTimeToMillisecs($p_time)
    {
        $t = explode(":", $p_time);
        $millisecs = 0;
        if (strpos($t[2], ".")) {
            $secParts = explode(".", $t[2]);
            $millisecs = $secParts[1];
            $millisecs = substr($millisecs, 0, 3);
            $millisecs = intval($millisecs);
            $seconds = intval($secParts[0]);
        } else {
            $seconds = intval($t[2]);
        }
        $ret = $millisecs + ($seconds * 1000) + ($t[1] * 60 * 1000) + ($t[0] * 60 * 60 * 1000);
        return $ret;
    }


    /**
     * Compute the difference between two times in the format "HH:MM:SS.mmmmmm".
     * Note: currently only supports calculating millisec differences.
     *
     * @param string $p_time1
     * @param string $p_time2
     * @return double
     */
    private static function TimeDiff($p_time1, $p_time2)
    {
        $parts1 = explode(".", $p_time1);
        $parts2 = explode(".", $p_time2);
        $diff = 0;
        if ( (count($parts1) > 1) && (count($parts2) > 1) ) {
            $millisec1 = substr($parts1[1], 0, 3);
            $millisec1 = str_pad($millisec1, 3, "0");
            $millisec1 = intval($millisec1);
            $millisec2 = substr($parts2[1], 0, 3);
            $millisec2 = str_pad($millisec2, 3, "0");
            $millisec2 = intval($millisec2);
            $diff = abs($millisec1 - $millisec2)/1000;
        }
        return $diff;
    }


    /**
     * Export the schedule in json formatted for pypo (the liquidsoap scheduler)
     *
     * @param string $p_fromDateTime
     *      In the format "YYYY-MM-DD-HH-mm-SS"
     * @param string $p_toDateTime
     *      In the format "YYYY-MM-DD-HH-mm-SS"
     */
    public static function GetScheduledPlaylists()
    {
        global $CC_CONFIG, $CC_DBC;

        $t1 = new DateTime();
        $range_start = $t1->format("Y-m-d H:i:s");

        $t2 = new DateTime();
        $t2->add(new DateInterval("PT24H"));
        $range_end = $t2->format("Y-m-d H:i:s");

        // Scheduler wants everything in a playlist
        $data = Schedule::GetItems($range_start, $range_end, true);
        $playlists = array();

        if (is_array($data))
        {
            foreach ($data as $dx)
            {
                $start = $dx['start'];

                //chop off subseconds
                $start = substr($start, 0, 19);

                //Start time is the array key, needs to be in the format "YYYY-MM-DD-HH-mm-ss"
                $pkey = Schedule::AirtimeTimeToPypoTime($start);
                $timestamp =  strtotime($start);
                $playlists[$pkey]['source'] = "PLAYLIST";
                $playlists[$pkey]['x_ident'] = $dx['group_id'];
                $playlists[$pkey]['subtype'] = '1'; // Just needs to be between 1 and 4 inclusive
                $playlists[$pkey]['timestamp'] = $timestamp;
                $playlists[$pkey]['duration'] = $dx['clip_length'];
                $playlists[$pkey]['played'] = '0';
                $playlists[$pkey]['schedule_id'] = $dx['group_id'];
                $playlists[$pkey]['show_name'] = $dx['show_name'];
                $playlists[$pkey]['user_id'] = 0;
                $playlists[$pkey]['id'] = $dx['group_id'];
                $playlists[$pkey]['start'] = Schedule::AirtimeTimeToPypoTime($dx["start"]);
                $playlists[$pkey]['end'] = Schedule::AirtimeTimeToPypoTime($dx["end"]);
            }
        }

        foreach ($playlists as &$playlist)
        {
            $scheduleGroup = new ScheduleGroup($playlist["schedule_id"]);
            $items = $scheduleGroup->getItems();
            $medias = array();
            $playlist['subtype'] = '1';
            foreach ($items as $item)
            {
                $storedFile = StoredFile::Recall($item["file_id"]);
                $uri = $storedFile->getFileUrl();

                // For pypo, a cueout of zero means no cueout
                $cueOut = "0";
                if (Schedule::TimeDiff($item["cue_out"], $item["clip_length"]) > 0.001) {
                    $cueOut = Schedule::WallTimeToMillisecs($item["cue_out"]);
                }
                $medias[] = array(
                    'row_id' => $item["id"],
                    'id' => $storedFile->getGunid(),
                    'uri' => $uri,
                    'fade_in' => Schedule::WallTimeToMillisecs($item["fade_in"]),
                    'fade_out' => Schedule::WallTimeToMillisecs($item["fade_out"]),
                    'fade_cross' => 0,
                    'cue_in' => Schedule::WallTimeToMillisecs($item["cue_in"]),
                    'cue_out' => $cueOut,
                    'export_source' => 'scheduler'
                );
            }
            $playlist['medias'] = $medias;
        }

        $result = array();
        $result['status'] = array('range' => array('start' => $range_start, 'end' => $range_end),
                                  'version' => AIRTIME_REST_VERSION);
        $result['playlists'] = $playlists;
        $result['check'] = 1;
        $result['stream_metadata'] = array();
        $result['stream_metadata']['format'] = Application_Model_Preference::GetStreamLabelFormat();
        $result['stream_metadata']['station_name'] = Application_Model_Preference::GetStationName();
        $result['server_timezone'] = date('O');

        return $result;
    }
}

