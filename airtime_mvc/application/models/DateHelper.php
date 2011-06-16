<?php

class DateHelper
{
    private $_timestamp;

    function __construct()
    {
        $this->_timestamp = date("U");
    }

    /**
     * Get time of object construction in the format
     * YYYY-MM-DD HH:mm:ss
     */
    function getTimestamp()
    {
        return date("Y-m-d H:i:s", $this->_timestamp);
    }

    /**
     * Get date of object construction in the format
     * YY:mm:dd
     */
    function getDate()
    {
        return date("Y-m-d", $this->_timestamp);
    }

    /**
     * Get time of object construction in the format
     * HH:mm:ss
     */
    function getTime()
    {
        return date("H:i:s", $this->_timestamp);
    }

    /**
     * Set the internal timestamp of the object.
     */
    function setDate($dateString)
    {
        $this->_timestamp = strtotime($dateString);
    }

    /**
     *
     * Enter description here ...
     */
    function getNowDayStartDiff()
    {
        $dayStartTS = strtotime(date("Y-m-d", $this->_timestamp));
        return $this->_timestamp - $dayStartTS;
    }

    function getNowDayEndDiff()
    {
        $dayEndTS = strtotime(date("Y-m-d", $this->_timestamp+(86400)));
        return $dayEndTS - $this->_timestamp;
    }

    function getEpochTime()
    {
        return $this->_timestamp;
    }

    public static function TimeDiff($time1, $time2)
    {
        return strtotime($time2) - strtotime($time1);
    }

    public static function ConvertMSToHHMMSSmm($time)
    {
        $hours = floor($time / 3600000);
        $time -= 3600000*$hours;

        $minutes = floor($time / 60000);
        $time -= 60000*$minutes;

        $seconds = floor($time / 1000);
        $time -= 1000*$seconds;

        $ms = $time;

        if (strlen($hours) == 1)
        $hours = "0".$hours;
        if (strlen($minutes) == 1)
        $minutes = "0".$minutes;
        if (strlen($seconds) == 1)
        $seconds = "0".$seconds;

        return $hours.":".$minutes.":".$seconds.".".$ms;
    }

    /**
     * This function formats a time by removing seconds
     *
     * When we receive a time from the database we get the
     * format "hh:mm:ss". But when dealing with show times, we
     * do not care about the seconds.
     *
     * @param int $p_timestamp
     *      The value which to format.
     * @return int
     *      The timestamp with the new format "hh:mm", or
     *      the original input parameter, if it does not have
     *      the correct format.
     */
    public static function removeSecondsFromTime($p_timestamp)
    {
        //Format is in hh:mm:ss. We want hh:mm
        $timeExplode = explode(":", $p_timestamp);

        if (count($timeExplode) == 3)
            return $timeExplode[0].":".$timeExplode[1];
        else
            return $p_timestamp;
    }

    public static function getDateFromTimestamp($p_timestamp){
        $explode = explode(" ", $p_timestamp);
        return $explode[0];
    }

    public static function getTimeFromTimestamp($p_timestamp){
        $explode = explode(" ", $p_timestamp);
        return $explode[1];
    }
    
    /* Given a track length in the format HH:MM:SS.mm, we want to 
     * convert this to seconds. This is useful for Liquidsoap which
     * likes input parameters give in seconds. 
     * For example, 00:06:31.444, should be converted to 391.444 seconds 
     * @param int $p_time
     *      The time interval in format HH:MM:SS.mm we wish to
     *      convert to seconds.
     * @return int
     *      The input parameter converted to seconds.
     */
    public static function calculateLengthInSeconds($p_time){
        
        if (2 !== substr_count($p_time, ":")){
            return FALSE;
        }
               
        if (1 === substr_count($p_time, ".")){
            list($hhmmss, $ms) = explode(".", $p_time);
        } else {
            $hhmmss = $p_time;
            $ms = 0;
        }
        
        list($hours, $minutes, $seconds) = explode(":", $hhmmss);
        
        $totalSeconds = $hours*3600 + $minutes*60 + $seconds + $ms/1000;
    
        return $totalSeconds;
    }
}

