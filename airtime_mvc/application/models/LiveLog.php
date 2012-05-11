<?php

class Application_Model_LiveLog
{
	
    public static function GetLiveShowDuration() {
        try {
            $con = Propel::getConnection();
            
            $rows = self::GetNumLogs();
            Logging::log($rows);
            if ($data['count'] > 1) {
            	$sql = "SELECT * FROM CC_LIVE_LOG"
            	     ." WHERE state = 'L'"
            	     ." and (start_time >= (now() - INTERVAL '1 day'))"
            	     ." ORDER BY id";
                
                $rows = $con->query($sql)->fetchAll();
                $duration = self::GetDuration($rows);
                return $duration;
            }
            else if ($data['count'] == 1 && $data['state'] == 'S') {
                $duration = new DateTime("00:00:00");
                return $duration->format("H:i:s");
            }
            else if ($data['count'] == 1 && $data['state'] == 'L') {
                $duration = new DateTime("23:59:59");
                return $duration->format("H:i:s");
            }
            
        } catch (Exception $e) {
            header('HTTP/1.0 503 Service Unavailable');
            Logging::log("Could not connect to database.");
            exit;            
        }	
    }
    
    public static function GetScheduledDuration() {
        try {
            $con = Propel::getConnection();
            
            if (self::GetNumLogs() > 1) {
            
    	        $sql = "SELECT * FROM CC_LIVE_LOG"
            	     ." WHERE state = 'S'"
            	     ." and (start_time >= (now() - INTERVAL '1 day'))"
            	     ." ORDER BY id";
                
                $rows = $con->query($sql)->fetchAll();
                $duration = self::GetDuration($rows);
                return $duration;
            }
            else {
                $duration = new DateTime("23:59:59");
                return $duration->format("H:i:s");
            }
            
        } catch (Exception $e) {
            header('HTTP/1.0 503 Service Unavailable');
            Logging::log("Could not connect to database.");
            exit;            
        }
    }
    
    public static function GetDuration($rows) {
        $duration = new DateTime("00:00:00");
        foreach($rows as $row) {
            $end = new DateTime($row['end_time']);
            $start = new DateTime($row['start_time']);
            $duration->add($start->diff($end));
        }
        $duration = $duration->format("H:i:s");
        return $duration;
    }
    
    /* Returns number of entries in cc_live_log
     * within the last 24 hours
     */
    public static function GetNumLogs() {
        try {
            $con = Propel::getConnection();
            /*
            $sql = "SELECT count(*), state FROM CC_LIVE_LOG"
                 ." WHERE (start_time >= (now() - INTERVAL '1 day'))"
                 ." GROUP BY state";
                 
            $rows = $con->query($sql)->fetchAll();
            return $rows;
            */
            
        } catch (Exception $e) {
            header('HTTP/1.0 503 Service Unavailable');
            Logging::log("Could not connect to database.");
            exit; 
        }
    }
    
    public static function SetNewLogTime($state, $dateTime){
        try {
            $con = Propel::getConnection();
            
            if ($state == 'L') {
            	self::SetEndTime('S', $dateTime);
            }
            
            /* Check if airtime is currently broadcasting live.
             * Only insert new state if scheduled switch is on
             * or live broadcasting is off
             */
            $sql_select = "SELECT max(id) from CC_LIVE_LOG"
                        ." WHERE state='L' and end_time is NULL";
            $id = $con->query($sql_select)->fetchColumn(0);
            
            if ($id == null) {
            	
                $sql_insert = "INSERT INTO CC_LIVE_LOG (state, start_time)" 
                            ." VALUES ('$state', '{$dateTime->format("Y-m-d H:i:s")}')";
                $con->exec($sql_insert);
            }
            
        } catch (Exception $e) {
            header('HTTP/1.0 503 Service Unavailable');
            Logging::log("SetNewLogTime - Could not connect to database.");
            exit; 
        }
    }
    
    public static function SetEndTime($state, $dateTime){
        try {
            $con = Propel::getConnection();
            
            if ($state == 'L') {
                $dj_live = Application_Model_Preference::GetSourceSwitchStatus('live_dj');
                $master_live = Application_Model_Preference::GetSourceSwitchStatus('master_dj');
            }
            
            if (($dj_live=='off' && $master_live=='off') || $state == 'S') {
            	$sql = "SELECT max(id) FROM CC_LIVE_LOG"
                     ." WHERE state = '$state'"
                     ." UNION"
                     ." SELECT max(id) FROM CC_LIVE_LOG";
                $row = $con->query($sql)->fetch();
                
                if ($row != null && $row['max'] == $row[0]) {
                    $update_sql = "UPDATE CC_LIVE_LOG"
                                ." SET end_time = '{$dateTime->format("Y-m-d H:i:s")}'"
                                ." WHERE id = '$row[0]'";
                    $con->exec($update_sql);
                }
                
                //if live broadcasting is off, turn scheduled play on
                $scheduled = Application_Model_Preference::GetSourceSwitchStatus('scheduled_play');
                if ($state == 'L' && $scheduled=='on') {
                    self::SetNewLogTime('S', $dateTime);
                }
            }
            
        } catch (Exception $e) {
            header('HTTP/1.0 503 Service Unavailable');
            Logging::log("SetEndTime - Could not connect to database.");
            exit; 
        }
    }
	
}