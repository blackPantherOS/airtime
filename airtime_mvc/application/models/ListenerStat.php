<?php
class Application_Model_ListenerStat
{
    public function __construct()
    {
    }
    
    public static function getDataPointsWithinRange($p_start, $p_end) {
        $sql = <<<SQL
SELECT cc_listener_count.ID, cc_timestamp.TIMESTAMP, cc_listener_count.LISTENER_COUNT, mount_name
FROM cc_listener_count
INNER JOIN cc_timestamp ON (cc_listener_count.TIMESTAMP_ID=cc_timestamp.ID)
WHERE (cc_timestamp.TIMESTAMP>=:p1 AND cc_timestamp.TIMESTAMP<=:p2)
ORDER BY cc_listener_count.mount_name, cc_timestamp.TIMESTAMP
SQL;
        $data = Application_Common_Database::prepareAndExecute($sql, array('p1'=>$p_start, 'p2'=>$p_end));
        
        $out = array();
        foreach ($data as $d) {
            $out[$d['mount_name']][] = $d;
        }
        
        return $out;
    }
    
    public static function getAllMPNames() {
        $sql = <<<SQL
SELECT DISTINCT mount_name
FROM cc_listener_count
SQL;
        $mps = Application_Common_Database::prepareAndExecute($sql, array());
        $out = array();
        foreach ($mps as $mp) {
            $out[] = $mp['mount_name'];
        }
        return $out;
    }

    public static function insertDataPoints($p_dataPoints) {


        $timestamp_sql = "INSERT INTO cc_timestamp (timestamp) VALUES 
            (:ts::TIMESTAMP) RETURNING id;";

        $mount_name_check_sql = "SELECT id from cc_mount_name WHERE 
            mount_name = :mn;";

        $mount_name_insert_sql = "INSERT INTO cc_mount_name (mount_name) VALUES 
                (:mn) RETURNING id;";

        $stats_sql = "INSERT INTO cc_listener_count (timestamp_id, 
            listener_count, mount_name_id) VALUES (:timestamp_id, 
            :listener_count, :mount_name_id)";

        foreach ($p_dataPoints as $dp) {
            $timestamp_id = Application_Common_Database::prepareAndExecute(
                $timestamp_sql, 
                array('ts'=> $dp['timestamp']), 
                "column");

            $mount_name_id = Application_Common_Database::prepareAndExecute(
                $mount_name_check_sql,
                array('mn' => $dp['mount_name']), 
                "column");

            if (strlen($mount_name_id) == 0) {
                //there is a race condition here where theoretically the row
                //with value "mount_name" could appear, but this is *very* 
                //unlikely and won't break anything even if it happens.
                $mount_name_id = Application_Common_Database::prepareAndExecute(
                    $mount_name_insert_sql,
                    array('mn' => $dp['mount_name']), 
                    "column");              
            }

            Application_Common_Database::prepareAndExecute($stats_sql,
                array('timestamp_id' => $timestamp_id, 
                'listener_count' => $dp["num_listeners"],
                'mount_name_id' => $mount_name_id,
                )
            );
        }

    }



}
