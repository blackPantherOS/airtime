<?php

class Show {

    private $_showId;

    public function __construct($showId=NULL)
    {
        $this->_showId = $showId;
    }

    public function getName()
    {
        $show = CcShowQuery::create()->findPK($this->_showId);
        return $show->getDbName();
    }

    public function setName($name)
    {
        $show = CcShowQuery::create()->findPK($this->_showId);
        $show->setDbName($name);
        RabbitMq::PushSchedule();
    }

    public function getDescription()
    {
        $show = CcShowQuery::create()->findPK($this->_showId);
        return $show->getDbDescription();
    }

    public function setDescription($description)
    {
        $show = CcShowQuery::create()->findPK($this->_showId);
        $show->setDbDescription($description);
    }

    public function getColor()
    {
        $show = CcShowQuery::create()->findPK($this->_showId);
        return $show->getDbColor();
    }

    public function setColor($color)
    {
        $show = CcShowQuery::create()->findPK($this->_showId);
        $show->setDbColor($color);
    }

    public function getUrl()
    {
        $show = CcShowQuery::create()->findPK($this->_showId);
        return $show->getDbUrl();
    }

    public function setUrl($p_url)
    {
        $show = CcShowQuery::create()->findPK($this->_showId);
        $show->setDbUrl($p_url);
    }

    public function getGenre()
    {
        $show = CcShowQuery::create()->findPK($this->_showId);
        return $show->getDbGenre();
    }

    public function setGenre($p_genre)
    {
        $show = CcShowQuery::create()->findPK($this->_showId);
        $show->setDbGenre($p_genre);
    }

    public function getBackgroundColor()
    {
        $show = CcShowQuery::create()->findPK($this->_showId);
        return $show->getDbBackgroundColor();
    }

    public function setBackgroundColor($backgroundColor)
    {
        $show = CcShowQuery::create()->findPK($this->_showId);
        $show->setDbBackgroundColor($backgroundColor);
    }

    public function getId()
    {
        return $this->_showId;
    }

    public function getHosts()
    {
        global $CC_DBC;

        $sql = "SELECT first_name, last_name
                FROM cc_show_hosts LEFT JOIN cc_subjs ON cc_show_hosts.subjs_id = cc_subjs.id
                    WHERE show_id = {$this->_showId}";

        $hosts = $CC_DBC->GetAll($sql);

        $res = array();
        foreach($hosts as $host) {
            $res[] = $host['first_name']." ".$host['last_name'];
        }

        return $res;
    }

    public function cancelShow($day_timestamp)
    {
        global $CC_DBC;

        $timeinfo = explode(" ", $day_timestamp);

        CcShowDaysQuery::create()
            ->filterByDbShowId($this->_showId)
            ->update(array('DbLastShow' => $timeinfo[0]));

        $sql = "DELETE FROM cc_show_instances
                    WHERE starts >= '{$day_timestamp}' AND show_id = {$this->_showId}";

        $CC_DBC->query($sql);
        RabbitMq::PushSchedule();
    }

    /**
     * Remove Show Instances that occur on days of the week specified
     * by input array. For example, if array contains one value of "0",
     * then all show instances that occur on Sunday are removed.
     *
     * @param array p_uncheckedDays
     *      An array specifying which days 
     */
    public function removeUncheckedDaysInstances($p_uncheckedDays)
    {
        global $CC_DBC;
        
        $uncheckedDaysImploded = implode(",", $p_uncheckedDays);
        $showId = $this->getId();
        $sql = "DELETE FROM cc_show_instances"
            ." WHERE EXTRACT(DOW FROM starts) IN ($uncheckedDaysImploded)"
            ." AND starts > current_timestamp "
            ." AND show_id = $showId";

        $CC_DBC->query($sql);        
    }
    
    /**
     * Check whether the current show originated
     * from a recording.
     *
     * @return boolean
     *      true if originated from recording, otherwise false.
     */
    public function isRecorded(){
        $showInstancesRow = CcShowInstancesQuery::create()
        ->filterByDbShowId($this->getId())
        ->filterByDbRecord(1)
        ->findOne();

        return !is_null($showInstancesRow);
    }

    /**
     * Check whether the current show has rebroadcasts of a recorded
     * show. Should be used in conjunction with isRecorded().
     *
     * @return boolean
     *      true if show has rebroadcasts, otherwise false.
     */
    public function isRebroadcast()
    {
         $showInstancesRow = CcShowInstancesQuery::create()
        ->filterByDbShowId($this->_showId)
        ->filterByDbRebroadcast(1)
        ->findOne();

        return !is_null($showInstancesRow);       
    }

    public function getRebroadcastsAbsolute()
    {
        global $CC_DBC;
    
        $showId = $this->getId();
        $sql = "SELECT date(starts) "
                ."FROM cc_show_instances "
                ."WHERE show_id = $showId "
                ."AND record = 1";
        $baseDate = $CC_DBC->GetOne($sql);

        if (is_null($baseDate)){
            return array();
        }

        $sql = "SELECT date(DATE '$baseDate' + day_offset::INTERVAL) as start_date, start_time FROM cc_show_rebroadcast "
            ."WHERE show_id = $showId "
            ."ORDER BY start_date";
            
        return $CC_DBC->GetAll($sql);
    }

    public function getRebroadcastsRelative()
    {
        global $CC_DBC;
    
        $showId = $this->getId();
        $sql = "SELECT day_offset, start_time FROM cc_show_rebroadcast "
            ."WHERE show_id = $showId "
            ."ORDER BY day_offset";
            
        return $CC_DBC->GetAll($sql);
    }
    
    /**
     * Check whether the current show is set to repeat
     * repeating shows.
     *
     * @return boolean
     *      true if repeating shows, otherwise false.
     */
    public function isRepeating()
    {
        $showDaysRow = CcShowDaysQuery::create()
        ->filterByDbShowId($this->_showId)
        ->findOne();

        if (!is_null($showDaysRow)){
            return ($showDaysRow->getDbRepeatType() != -1);
        } else
            return false;
    }
    
    /**
     * Get the repeat type of the show. Show can have repeat
     * type of "weekly", "bi-weekly" and "monthly". These values
     * are represented by 0, 1, and 2 respectively. 
     *
     * @return int
     *      Return the integer corresponding to the repeat type.
     */
    public function getRepeatType()
    {
        $showDaysRow = CcShowDaysQuery::create()
        ->filterByDbShowId($this->_showId)
        ->findOne();

        if (!is_null($showDaysRow)){
            return $showDaysRow->getDbRepeatType();
        } else
            return -1;
    }

    /**
     * Get the end date for a repeating show in the format yyyy-mm-dd
     *
     * @return string
     *      Return the end date for the repeating show or the empty
     *      string if there is no end.
     */
    public function getRepeatingEndDate(){
        global $CC_DBC;
    
        $showId = $this->getId();
        $sql = "SELECT last_show FROM cc_show_days"
            ." WHERE show_id = $showId";
            
        $endDate = $CC_DBC->GetOne($sql);
        
        if (is_null($endDate)){
            return "";
        } else {
            return $endDate;
        }
    }
    
    public function deleteAllInstances(){
        global $CC_DBC;

        $showId = $this->getId();
        $sql = "DELETE FROM cc_show_instances "
                ."WHERE starts > current_timestamp "
                ."AND show_id = $showId";
                   
        $CC_DBC->query($sql); 
    }
    
    public function removeAllInstancesAfterDate($p_date){
        global $CC_DBC;

        $showId = $this->getId();
        $sql = "DELETE FROM cc_show_instances "
                ."WHERE date(starts) > DATE '$p_date' "
                ."AND starts > current_timestamp "
                ."AND show_id = $showId";
                   
        $CC_DBC->query($sql); 
    }
    
    public function removeAllInstancesBeforeDate($p_date){
        global $CC_DBC;

        $showId = $this->getId();
        $sql = "DELETE FROM cc_show_instances "
                ."WHERE date(starts) < DATE '$p_date' "
                ."AND starts > current_timestamp "
                ."AND show_id = $showId";
                   
        $CC_DBC->query($sql);     
    }
    
    public function getStartDate(){
        global $CC_DBC;
    
        $showId = $this->getId();
        $sql = "SELECT first_show FROM cc_show_days"
            ." WHERE show_id = $showId";
            
        $firstDate = $CC_DBC->GetOne($sql);
        
        if (is_null($firstDate)){
            return "";
        } else {
            return $firstDate;
        }
    }
        
    public function getStartTime(){
        global $CC_DBC;
    
        $showId = $this->getId();
        $sql = "SELECT start_time FROM cc_show_days"
            ." WHERE show_id = $showId";
            
        $startTime = $CC_DBC->GetOne($sql);
        
        if (is_null($startTime)){
            return "";
        } else {
            return $startTime;
        }
    }
    
    public function getAllFutureInstanceIds(){
        global $CC_DBC;
        
        $showId = $this->getId();
        $sql = "SELECT id from cc_show_instances "
            ." WHERE show_id = $showId"
            ." AND starts > current_timestamp";
                      
        $rows = $CC_DBC->GetAll($sql);

        $instance_ids = array();
        foreach ($rows as $row){
            $instance_ids[] = $row["id"];
        }
        return $instance_ids;
    }
    
    private function updateDurationTime($p_data){
        //need to update cc_show_instances, cc_show_days
    
        global $CC_DBC;
        
        $sql = "UPDATE cc_show_days "
                ."SET duration = '$p_data[add_show_duration]' "
                ."WHERE show_id = $p_data[add_show_id]";
        $CC_DBC->query($sql);
        
        $sql = "UPDATE cc_show_instances "
                ."SET ends = starts + INTERVAL '$p_data[add_show_duration]' "
                ."WHERE show_id = $p_data[add_show_id] "
                ."AND starts > current_timestamp";              
        $CC_DBC->query($sql);

    }
    
    private function updateStartDateTime($p_data, $p_endDate){
        //need to update cc_schedule, cc_show_instances, cc_show_days
        
        global $CC_DBC;
        
        $sql = "UPDATE cc_show_days "
                ."SET start_time = TIME '$p_data[add_show_start_time]', "
                ."first_show = DATE '$p_data[add_show_start_date]', ";
        if (strlen ($p_endDate) == 0){
            $sql .= "last_show = NULL ";
        } else {
            $sql .= "last_show = DATE '$p_endDate' ";
        }
        $sql .= "WHERE show_id = $p_data[add_show_id]";
        $CC_DBC->query($sql);
        
        $oldStartDateTimeEpoch = strtotime($this->getStartDate()." ".$this->getStartTime());
        $newStartDateTimeEpoch = strtotime($p_data['add_show_start_date']." ".$p_data['add_show_start_time']);
        $diff = $newStartDateTimeEpoch - $oldStartDateTimeEpoch;

        $sql = "UPDATE cc_show_instances "
                ."SET starts = starts + INTERVAL '$diff sec', "
                ."ends = ends + INTERVAL '$diff sec' "
                ."WHERE show_id = $p_data[add_show_id] "
                ."AND starts > current_timestamp";                  
        $CC_DBC->query($sql);
        
        $showInstanceIds = $this->getAllFutureInstanceIds();
        if (count($showInstanceIds) > 0 && $diff != 0){
            $showIdsImploded = implode(",", $showInstanceIds);
            $sql = "UPDATE cc_schedule "
                    ."SET starts = starts + INTERVAL '$diff sec', "
                    ."ends = ends + INTERVAL '$diff sec' "
                    ."WHERE instance_id IN ($showIdsImploded)";              
            $CC_DBC->query($sql);
        }
    }
    
    public function getDuration(){
        $showDay = CcShowDaysQuery::create()->filterByDbShowId($this->getId())->findOne();
        return $showDay->getDbDuration();       
    }
    
    public function getShowDays(){
        $showDays = CcShowDaysQuery::create()->filterByDbShowId($this->getId())->find();
        
        $days = array();
        foreach ($showDays as $showDay){
            array_push($days, $showDay->getDbDay());
        }
        
        return $days;
    }
        
    public function hasInstance(){
        return (!is_null($this->getInstance()));
    }
    
    public function getInstance(){
        $showInstances = CcShowInstancesQuery::create()->filterByDbShowId($this->getId())->findOne();
        return $showInstances;
    }
    
    public function hasInstanceOnDate($p_timestamp){
        return (!is_null($this->getInstanceOnDate($p_timestamp)));
    }
        
    public function getInstanceOnDate($p_timestamp){
        global $CC_DBC;
        
        $showId = $this->getId();
        $sql = "SELECT id FROM cc_show_instances"
            ." WHERE date(starts) = date(TIMESTAMP '$p_timestamp') "
            ." AND show_id = $showId";
                
        $row = $CC_DBC->GetOne($sql);
        return CcShowInstancesQuery::create()->findPk($row);
    }
    
    public static function deletePossiblyInvalidInstances($p_data, $p_show, $p_endDate, $isRecorded, $repeatType)
    {
        if ($p_data['add_show_repeats'] != $p_show->isRepeating()
            || $isRecorded){
            //repeat option was toggled or show is recorded.
            $p_show->deleteAllInstances();
        }
        if ($p_data['add_show_start_date'] != $p_show->getStartDate()
            || $p_data['add_show_start_time'] != $p_show->getStartTime()){
            //start date/time has changed
            
            $newDate = strtotime($p_data['add_show_start_date']);
            $oldDate = strtotime($p_show->getStartDate());
            if ($newDate > $oldDate){
                $p_show->removeAllInstancesBeforeDate($p_data['add_show_start_date']);
            }            

            $p_show->updateStartDateTime($p_data, $p_endDate);
        }
        if ($p_data['add_show_duration'] != $p_show->getDuration()){
            //duration has changed
            $p_show->updateDurationTime($p_data);
        }
    
        if ($p_data['add_show_repeats']){
            if ($repeatType != $p_show->getRepeatType()){
                //repeat type changed.
                $p_show->deleteAllInstances();
            } else {
                //repeat type is the same, check if the days of the week are the same
                $repeatingDaysChanged = false;
                $showDaysArray = $p_show->getShowDays();
                if (count($p_data['add_show_day_check']) == count($showDaysArray)){
                    //same number of days checked, lets see if they are the same numbers
                    $intersect = array_intersect($p_data['add_show_day_check'], $showDaysArray);
                    if (count($intersect) != count($p_data['add_show_day_check'])){
                        $repeatingDaysChanged = true;
                    }
                } else {
                    $repeatingDaysChanged = true;
                }
                
                if ($repeatingDaysChanged){
                    $daysRemoved = array_diff($showDaysArray, $p_data['add_show_day_check']);
                    
                    if (count($daysRemoved) > 0){
                        $p_show->removeUncheckedDaysInstances($daysRemoved);
                    }
                }
            }
            
            //Check if end date for the repeat option has changed. If so, need to take care
            //of deleting possible invalid Show Instances.
            if ((strlen($p_show->getRepeatingEndDate()) == 0) == $p_data['add_show_no_end']){
                //show "Never Ends" option was toggled.
                if ($p_data['add_show_no_end']){
                } else {
                    $p_show->removeAllInstancesAfterDate($p_endDate);
                }
            }
            if ($p_show->getRepeatingEndDate() != $p_data['add_show_end_date']){
                //end date was changed.
                
                $newDate = strtotime($p_data['add_show_end_date']);
                $oldDate = strtotime($p_show->getRepeatingEndDate());
                if ($newDate < $oldDate){
                    $p_show->removeAllInstancesAfterDate($p_endDate);
                }
            }
        }
    }

    /**
     * Create a show.
     *
     * Note: end dates are non inclusive.
     *
     * @param array $data
     * @return int
     *     Show ID
     */
    public static function create($data)
    {
        $con = Propel::getConnection(CcShowPeer::DATABASE_NAME);

        $sql = "SELECT EXTRACT(DOW FROM TIMESTAMP '{$data['add_show_start_date']} {$data['add_show_start_time']}')";
        $r = $con->query($sql);
        $startDow = $r->fetchColumn(0);

        if ($data['add_show_no_end']) {
            $endDate = NULL;
            //$data['add_show_repeats'] = 1;
        }
        else if ($data['add_show_repeats']) {
            $sql = "SELECT date '{$data['add_show_end_date']}' + INTERVAL '1 day' ";
            $r = $con->query($sql);
            $endDate = $r->fetchColumn(0);
        }
        else {
            $sql = "SELECT date '{$data['add_show_start_date']}' + INTERVAL '1 day' ";
            $r = $con->query($sql);
            $endDate = $r->fetchColumn(0);
        }

        //only want the day of the week from the start date.
        if (!$data['add_show_repeats']) {
            $data['add_show_day_check'] = array($startDow);
        } else if ($data['add_show_repeats'] && $data['add_show_day_check'] == "") {
            $data['add_show_day_check'] = array($startDow);
        }

        //find repeat type or set to a non repeating show.
        $repeatType = ($data['add_show_repeats']) ? $data['add_show_repeat_type'] : -1;

        if ($data['add_show_id'] == -1){
            $ccShow = new CcShow();
        } else {
            $ccShow = CcShowQuery::create()->findPK($data['add_show_id']);
        }
        $ccShow->setDbName($data['add_show_name']);
        $ccShow->setDbDescription($data['add_show_description']);
        $ccShow->setDbUrl($data['add_show_url']);
        $ccShow->setDbGenre($data['add_show_genre']);
        $ccShow->setDbColor($data['add_show_color']);
        $ccShow->setDbBackgroundColor($data['add_show_background_color']);
        $ccShow->save();

        $showId = $ccShow->getDbId();
        $show = new Show($showId);

        //If show is a new show (not updated), then get
        //isRecorded from POST data. Otherwise get it from
        //the database since the user is not allowed to
        //update this option.
        if ($data['add_show_id'] == -1){
            $isRecorded = ($data['add_show_record']) ? 1 : 0;
        } else {
            $isRecorded = $show->isRecorded();
        } 

        
        if ($data['add_show_id'] != -1){
            Show::deletePossiblyInvalidInstances($data, $show, $endDate, $isRecorded, $repeatType);
        }
        
        //check if we are adding or updating a show, and if updating
        //erase all the show's show_days information first.
        if ($data['add_show_id'] != -1){
            CcShowDaysQuery::create()->filterByDbShowId($data['add_show_id'])->delete();
        }
        
        //don't set day for monthly repeat type, it's invalid.
        if ($data['add_show_repeats'] && $data['add_show_repeat_type'] == 2){
            $showDay = new CcShowDays();
            $showDay->setDbFirstShow($data['add_show_start_date']);
            $showDay->setDbLastShow($endDate);
            $showDay->setDbStartTime($data['add_show_start_time']);
            $showDay->setDbDuration($data['add_show_duration']);
            $showDay->setDbRepeatType($repeatType);
            $showDay->setDbShowId($showId);
            $showDay->setDbRecord($isRecorded);
            $showDay->save();
        } else {
            foreach ($data['add_show_day_check'] as $day) {
                if ($startDow !== $day){

                    if ($startDow > $day)
                        $daysAdd = 6 - $startDow + 1 + $day;
                    else
                        $daysAdd = $day - $startDow;

                    $sql = "SELECT date '{$data['add_show_start_date']}' + INTERVAL '{$daysAdd} day' ";
                    $r = $con->query($sql);
                    $start = $r->fetchColumn(0);
                }
                else {
                    $start = $data['add_show_start_date'];
                }

                if (strtotime($start) < strtotime($endDate) || is_null($endDate)) {
                    $showDay = new CcShowDays();
                    $showDay->setDbFirstShow($start);
                    $showDay->setDbLastShow($endDate);
                    $showDay->setDbStartTime($data['add_show_start_time']);
                    $showDay->setDbDuration($data['add_show_duration']);
                    $showDay->setDbDay($day);
                    $showDay->setDbRepeatType($repeatType);
                    $showDay->setDbShowId($showId);
                    $showDay->setDbRecord($isRecorded);
                    $showDay->save();
                }
            }
        }

        //check if we are adding or updating a show, and if updating
        //erase all the show's show_rebroadcast information first.
        if ($data['add_show_id'] != -1){
            CcShowRebroadcastQuery::create()->filterByDbShowId($data['add_show_id'])->delete();
        }
        //adding rows to cc_show_rebroadcast
        if ($isRecorded && $data['add_show_rebroadcast'] && $repeatType != -1) {

            for ($i=1; $i<=10; $i++) {

                if ($data['add_show_rebroadcast_date_'.$i]) {
                    $showRebroad = new CcShowRebroadcast();
                    $showRebroad->setDbDayOffset($data['add_show_rebroadcast_date_'.$i]);
                    $showRebroad->setDbStartTime($data['add_show_rebroadcast_time_'.$i]);
                    $showRebroad->setDbShowId($showId);
                    $showRebroad->save();
                }
            }
        } else if ($isRecorded && $data['add_show_rebroadcast'] && $repeatType == -1){

            for ($i=1; $i<=10; $i++) {

                if ($data['add_show_rebroadcast_date_absolute_'.$i]) {
                    $sql = "SELECT date '{$data['add_show_rebroadcast_date_absolute_'.$i]}' - date '{$data['add_show_start_date']}' ";
                    $r = $con->query($sql);
                    $offset_days = $r->fetchColumn(0);

                    $showRebroad = new CcShowRebroadcast();
                    $showRebroad->setDbDayOffset($offset_days." days");
                    $showRebroad->setDbStartTime($data['add_show_rebroadcast_time_absolute_'.$i]);
                    $showRebroad->setDbShowId($showId);
                    $showRebroad->save();
                }
            }
        }

        //check if we are adding or updating a show, and if updating
        //erase all the show's show_rebroadcast information first.
        if ($data['add_show_id'] != -1){
            CcShowHostsQuery::create()->filterByDbShow($data['add_show_id'])->delete();
        }
        if (is_array($data['add_show_hosts'])) {
            //add selected hosts to cc_show_hosts table.
            foreach ($data['add_show_hosts'] as $host) {
                $showHost = new CcShowHosts();
                $showHost->setDbShow($showId);
                $showHost->setDbHost($host);
                $showHost->save();
            }
        }

        Show::populateShowUntil($showId);
        RabbitMq::PushSchedule();
        return $showId;
    }

    /**
     * Get all the show instances in the given time range.
     *
     * @param string $start_timestamp
     *      In the format "YYYY-MM-DD HH:mm:ss".  This time is inclusive.
     * @param string $end_timestamp
     *      In the format "YYYY-MM-DD HH:mm:ss". This time is inclusive.
     * @param unknown_type $excludeInstance
     * @param boolean $onlyRecord
     * @return array
     */
    public static function getShows($start_timestamp, $end_timestamp, $excludeInstance=NULL, $onlyRecord=FALSE)
    {
        global $CC_DBC;

        $showsPopUntil = Application_Model_Preference::GetShowsPopulatedUntil();

        //if application is requesting shows past our previous populated until date, generate shows up until this point.
        if ($showsPopUntil == "" || strtotime($showsPopUntil) < strtotime($end_timestamp)) {
            Show::populateAllShowsInRange($showsPopUntil, $end_timestamp);
            Application_Model_Preference::SetShowsPopulatedUntil($end_timestamp);
        }

        $sql = "SELECT starts, ends, record, rebroadcast, soundcloud_id, instance_id, show_id, name, description,
                color, background_color, cc_show_instances.id AS instance_id
            FROM cc_show_instances
            LEFT JOIN cc_show ON cc_show.id = cc_show_instances.show_id";

        //only want shows that are starting at the time or later.
        if ($onlyRecord) {

            $sql = $sql." WHERE (starts >= '{$start_timestamp}' AND starts < timestamp '{$end_timestamp}')";
            $sql = $sql." AND (record = 1)";
        }
        else {

            $sql = $sql." WHERE ((starts >= '{$start_timestamp}' AND starts < '{$end_timestamp}')
                OR (ends > '{$start_timestamp}' AND ends <= '{$end_timestamp}')
                OR (starts <= '{$start_timestamp}' AND ends >= '{$end_timestamp}'))";
        }


        if (isset($excludeInstance)) {
            foreach($excludeInstance as $instance) {
                $sql_exclude[] = "cc_show_instances.id != {$instance}";
            }

            $exclude = join(" OR ", $sql_exclude);

            $sql = $sql." AND ({$exclude})";
        }

        return $CC_DBC->GetAll($sql);
    }

    private static function setNextPop($next_date, $show_id, $day)
    {
        $nextInfo = explode(" ", $next_date);

        $repeatInfo = CcShowDaysQuery::create()
            ->filterByDbShowId($show_id)
            ->filterByDbDay($day)
            ->findOne();

        $repeatInfo->setDbNextPopDate($nextInfo[0])
            ->save();
    }

    //for a show with repeat_type == -1
    private static function populateNonRepeatingShow($show_id, $first_show, $start_time, $duration, $day, $record, $end_timestamp)
    {
        global $CC_DBC;

        $next_date = $first_show." ".$start_time;

        if (strtotime($next_date) < strtotime($end_timestamp)) {

            $start = $next_date;

            $sql = "SELECT timestamp '{$start}' + interval '{$duration}'";
            $end = $CC_DBC->GetOne($sql);
            
            $show = new Show($show_id);
            if ($show->hasInstance()){
                $showInstance = $show->getInstance();
            } else {
                $showInstance = new CcShowInstances();
            }
            
            $showInstance->setDbShowId($show_id);
            $showInstance->setDbStarts($start);
            $showInstance->setDbEnds($end);
            $showInstance->setDbRecord($record);
            $showInstance->save();
            //$show->addInstance($showInstance);
        

            $show_instance_id = $showInstance->getDbId();

            $sql = "SELECT * FROM cc_show_rebroadcast WHERE show_id={$show_id}";
            $rebroadcasts = $CC_DBC->GetAll($sql);

            foreach($rebroadcasts as $rebroadcast) {

                $timeinfo = explode(" ", $start);

                $sql = "SELECT timestamp '{$timeinfo[0]}' + interval '{$rebroadcast["day_offset"]}' + interval '{$rebroadcast["start_time"]}'";
                $rebroadcast_start_time = $CC_DBC->GetOne($sql);

                $sql = "SELECT timestamp '{$rebroadcast_start_time}' + interval '{$duration}'";
                $rebroadcast_end_time = $CC_DBC->GetOne($sql);

                $newRebroadcastInstance = new CcShowInstances();
                $newRebroadcastInstance->setDbShowId($show_id);
                $newRebroadcastInstance->setDbStarts($rebroadcast_start_time);
                $newRebroadcastInstance->setDbEnds($rebroadcast_end_time);
                $newRebroadcastInstance->setDbRecord(0);
                $newRebroadcastInstance->setDbRebroadcast(1);
                $newRebroadcastInstance->setDbOriginalShow($show_instance_id);
                $newRebroadcastInstance->save();
            }
        }
        RabbitMq::PushSchedule();
    }

    //for a show with repeat_type == 0,1,2
    private static function populateRepeatingShow($show_id, $next_pop_date, $first_show, $last_show,
                                $start_time, $duration, $day, $record, $end_timestamp, $interval)
    {
        global $CC_DBC;

        if(isset($next_pop_date)) {
            $next_date = $next_pop_date." ".$start_time;
        }
        else {
            $next_date = $first_show." ".$start_time;
        }

        $sql = "SELECT * FROM cc_show_rebroadcast WHERE show_id={$show_id}";
        $rebroadcasts = $CC_DBC->GetAll($sql);
        $show = new Show($show_id);

        while(strtotime($next_date) < strtotime($end_timestamp) && (strtotime($last_show) > strtotime($next_date) || is_null($last_show))) {

            $start = $next_date;

            $sql = "SELECT timestamp '{$start}' + interval '{$duration}'";
            $end = $CC_DBC->GetOne($sql);
           
            if ($show->hasInstanceOnDate($start)){
                $showInstance = $show->getInstanceOnDate($start);
            } else {
                $showInstance = new CcShowInstances();
            }
            $showInstance->setDbShowId($show_id);
            $showInstance->setDbStarts($start);
            $showInstance->setDbEnds($end);
            $showInstance->setDbRecord($record);
            $showInstance->save();
            //$show->addInstance($showInstance);
        

            $show_instance_id = $showInstance->getDbId();

            foreach($rebroadcasts as $rebroadcast) {

                $timeinfo = explode(" ", $next_date);

                $sql = "SELECT timestamp '{$timeinfo[0]}' + interval '{$rebroadcast["day_offset"]}' + interval '{$rebroadcast["start_time"]}'";
                $rebroadcast_start_time = $CC_DBC->GetOne($sql);

                $sql = "SELECT timestamp '{$rebroadcast_start_time}' + interval '{$duration}'";
                $rebroadcast_end_time = $CC_DBC->GetOne($sql);

                $newRebroadcastInstance = new CcShowInstances();
                $newRebroadcastInstance->setDbShowId($show_id);
                $newRebroadcastInstance->setDbStarts($rebroadcast_start_time);
                $newRebroadcastInstance->setDbEnds($rebroadcast_end_time);
                $newRebroadcastInstance->setDbRecord(0);
                $newRebroadcastInstance->setDbRebroadcast(1);
                $newRebroadcastInstance->setDbOriginalShow($show_instance_id);
                $newRebroadcastInstance->save();
            }

            $sql = "SELECT timestamp '{$start}' + interval '{$interval}'";
            $next_date = $CC_DBC->GetOne($sql);
        }

        Show::setNextPop($next_date, $show_id, $day);
        RabbitMq::PushSchedule();
    }

    private static function populateShow($repeatType, $show_id, $next_pop_date,
                $first_show, $last_show, $start_time, $duration, $day, $record, $end_timestamp) {

        if($repeatType == -1) {
            Show::populateNonRepeatingShow($show_id, $first_show, $start_time, $duration, $day, $record, $end_timestamp);
        }
        else if($repeatType == 0) {
            Show::populateRepeatingShow($show_id, $next_pop_date, $first_show, $last_show,
                    $start_time, $duration, $day, $record, $end_timestamp, '7 days');
        }
        else if($repeatType == 1) {
            Show::populateRepeatingShow($show_id, $next_pop_date, $first_show, $last_show,
                    $start_time, $duration, $day, $record, $end_timestamp, '14 days');
        }
        else if($repeatType == 2) {
            Show::populateRepeatingShow($show_id, $next_pop_date, $first_show, $last_show,
                    $start_time, $duration, $day, $record, $end_timestamp, '1 month');
        }
    }


    /**
     * Generate repeating show instances for a single show up to the given date.
     * If no date is given, use the one in the user's preferences, which is stored
     * automatically by FullCalendar as the furthest date in the future the user
     * has looked at.
     *
     * @param int $p_showId
     * @param string $p_date
     *        In the format "YYYY-MM-DD HH:mm:ss"
     */
    public static function populateShowUntil($p_showId, $p_date = NULL)
    {
        global $CC_DBC;
        if (is_null($p_date)) {
            $p_date = Application_Model_Preference::GetShowsPopulatedUntil();

            if ($p_date == "") {
                $today_timestamp = date("Y-m-d");
                Application_Model_Preference::SetShowsPopulatedUntil($today_timestamp);
            }
        }

        $sql = "SELECT * FROM cc_show_days WHERE show_id = $p_showId";
        $res = $CC_DBC->GetAll($sql);

        foreach ($res as $row) {
            Show::populateShow($row["repeat_type"], $row["show_id"], $row["next_pop_date"], $row["first_show"],
                               $row["last_show"], $row["start_time"], $row["duration"], $row["day"], $row["record"], $p_date);
        }
    }

    /**
     * Generate all the repeating shows in the given range.
     *
     * @param string $p_startTimestamp
     *         In the format "YYYY-MM-DD HH:mm:ss"
     * @param string $p_endTimestamp
     *         In the format "YYYY-MM-DD HH:mm:ss"
     */
    public static function populateAllShowsInRange($p_startTimestamp, $p_endTimestamp)
    {
        global $CC_DBC;

        if ($p_startTimestamp != "") {
            $sql = "SELECT * FROM cc_show_days
                    WHERE last_show IS NULL
                    OR first_show < '{$p_endTimestamp}' AND last_show > '{$p_startTimestamp}'";
        }
        else {
            $today_timestamp = date("Y-m-d");
            $sql = "SELECT * FROM cc_show_days
                    WHERE last_show IS NULL
                    OR first_show < '{$p_endTimestamp}' AND last_show > '{$today_timestamp}'";
        }

        $res = $CC_DBC->GetAll($sql);

        foreach ($res as $row) {
            Show::populateShow($row["repeat_type"], $row["show_id"], $row["next_pop_date"], $row["first_show"],
                               $row["last_show"], $row["start_time"], $row["duration"], $row["day"], $row["record"], $p_endTimestamp);
        }
    }

    /**
     *
     * @param string $start
     *      In the format "YYYY-MM-DD HH:mm:ss"
     * @param string $end
     *         In the format "YYYY-MM-DD HH:mm:ss"
     * @param boolean $editable
     */
    public static function getFullCalendarEvents($start, $end, $editable=false)
    {
        $events = array();
        $options = array();

        $start_range = new DateTime($start);
        $end_range = new DateTime($end);
        $interval = $start_range->diff($end_range);
        $days =  $interval->format('%a');

        $shows = Show::getShows($start, $end);

        $today_timestamp = date("Y-m-d H:i:s");
        foreach ($shows as $show) {
            //only bother calculating percent for week or day view.
            if(intval($days) <= 7) {
                $show_instance = new ShowInstance($show["instance_id"]);
                $options["percent"] =  $show_instance->getPercentScheduled();
            }

            if ($editable && strtotime($today_timestamp) < strtotime($show["starts"])) {
                $options["editable"] = true;
                $events[] = Show::makeFullCalendarEvent($show, $options);
            }
            else {
                $events[] = Show::makeFullCalendarEvent($show, $options);
            }
        }

        return $events;
    }

    private static function makeFullCalendarEvent($show, $options=array()) 
    {
        $event = array();

        if($show["rebroadcast"]) {
            $event["disableResizing"] = true;
        }

        $event["id"] = $show["instance_id"];
        $event["title"] = $show["name"];
        $event["start"] = $show["starts"];
        $event["end"] = $show["ends"];
        $event["allDay"] = false;
        $event["description"] = $show["description"];
        $event["showId"] = $show["show_id"];
        $event["record"] = intval($show["record"]);
        $event["rebroadcast"] = intval($show["rebroadcast"]);
        $event["soundcloud_id"] = (is_null($show["soundcloud_id"]) ? -1 : $show["soundcloud_id"]);

        //event colouring
        if($show["color"] != "") {
            $event["textColor"] = "#".$show["color"];
        }
        if($show["background_color"] != "") {
            $event["color"] = "#".$show["background_color"];
        }    

        foreach($options as $key=>$value) {
            $event[$key] = $value;
        }

        return $event;
    }

    public static function getDateFromTimestamp($p_timestamp){
        $explode = explode(" ", $p_timestamp);
        return $explode[0];
    }
    
    public static function getTimeFromTimestamp($p_timestamp){
        $explode = explode(" ", $p_timestamp);
        return $explode[1];
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
}

class ShowInstance {

    private $_instanceId;

    public function __construct($instanceId)
    {
        $this->_instanceId = $instanceId;
    }

    public function getShowId()
    {
        $showInstance = CcShowInstancesQuery::create()->findPK($this->_instanceId);
        return $showInstance->getDbShowId();
    }

    public function getShowInstanceId()
    {
        return $this->_instanceId;
    }

    public function isRebroadcast()
    {
        $showInstance = CcShowInstancesQuery::create()->findPK($this->_instanceId);
        return $showInstance->getDbOriginalShow();
    }

    public function isRecorded()
    {
        $showInstance = CcShowInstancesQuery::create()->findPK($this->_instanceId);
        return $showInstance->getDbRecord();
    }

    public function getName()
    {
        $show = CcShowQuery::create()->findPK($this->getShowId());
        return $show->getDbName();
    }

    public function getGenre()
    {
        $show = CcShowQuery::create()->findPK($this->getShowId());
        return $show->getDbGenre();
    }

    public function getShowStart()
    {
        $showInstance = CcShowInstancesQuery::create()->findPK($this->_instanceId);
        return $showInstance->getDbStarts();
    }

    public function getShowEnd()
    {
        $showInstance = CcShowInstancesQuery::create()->findPK($this->_instanceId);
        return $showInstance->getDbEnds();
    }

    public function getStartDate()
    {
        $showStart = $this->getShowStart();
        $showStartExplode = explode(" ", $showStart);
        return $showStartExplode[0];
    }

    public function getStartTime()
    {
        $showStart = $this->getShowStart();
        $showStartExplode = explode(" ", $showStart);

        return $showStartExplode[1];
    }
    
    public function setSoundCloudFileId($p_soundcloud_id)
    {
        $showInstance = CcShowInstancesQuery::create()->findPK($this->_instanceId);
        $showInstance->setDbSoundCloudId($p_soundcloud_id)
            ->save();
    }

    public function getSoundCloudFileId()
    {
        $showInstance = CcShowInstancesQuery::create()->findPK($this->_instanceId);
        return $showInstance->getDbSoundCloudId();
    }

    public function getRecordedFile()
    {
        $showInstance = CcShowInstancesQuery::create()->findPK($this->_instanceId);
        $file_id =  $showInstance->getDbRecordedFile();

        if(isset($file_id)) {
            $file =  StoredFile::Recall($file_id);

            if (PEAR::isError($file)) {
                return null;
            }

            if(file_exists($file->getRealFilePath())) {
                return $file;
            }
        }
        
        return null;
    }

    public function setShowStart($start)
    {
        $showInstance = CcShowInstancesQuery::create()->findPK($this->_instanceId);
        $showInstance->setDbStarts($start)
            ->save();
        RabbitMq::PushSchedule();
    }

    public function setShowEnd($end)
    {
        $showInstance = CcShowInstancesQuery::create()->findPK($this->_instanceId);
        $showInstance->setDbEnds($end)
            ->save();
        RabbitMq::PushSchedule();
    }

    public function updateScheduledTime()
    {
        $con = Propel::getConnection(CcShowInstancesPeer::DATABASE_NAME);
        $showInstance = CcShowInstancesQuery::create()->findPK($this->_instanceId);
        $showInstance->updateDbTimeFilled($con);
    }

    public function moveScheduledShowContent($deltaDay, $deltaHours, $deltaMin)
    {
        global $CC_DBC;

        $sql = "UPDATE cc_schedule
                   SET starts = (starts + interval '{$deltaDay} days' + interval '{$deltaHours}:{$deltaMin}'),
                        ends = (ends + interval '{$deltaDay} days' + interval '{$deltaHours}:{$deltaMin}')
                   WHERE instance_id = '{$this->_instanceId}'";

        $CC_DBC->query($sql);
        RabbitMq::PushSchedule();
    }

    public function moveShow($deltaDay, $deltaMin)
    {
        global $CC_DBC;

        $hours = $deltaMin/60;
        if($hours > 0)
            $hours = floor($hours);
        else
            $hours = ceil($hours);

        $mins = abs($deltaMin%60);

        $starts = $this->getShowStart();
        $ends = $this->getShowEnd();

        $sql = "SELECT timestamp '{$starts}' + interval '{$deltaDay} days' + interval '{$hours}:{$mins}'";
        $new_starts = $CC_DBC->GetOne($sql);

        $sql = "SELECT timestamp '{$ends}' + interval '{$deltaDay} days' + interval '{$hours}:{$mins}'";
        $new_ends = $CC_DBC->GetOne($sql);

        $today_timestamp = date("Y-m-d H:i:s");
        if(strtotime($today_timestamp) > strtotime($new_starts)) {
            return "can't move show into past";
        }

        $overlap = Show::getShows($new_starts, $new_ends, array($this->_instanceId));

        if(count($overlap) > 0) {
            return "Should not overlap shows";
        }

        $rebroadcast = $this->isRebroadcast();
        if($rebroadcast) {
            $sql = "SELECT timestamp '{$new_starts}' < (SELECT starts FROM cc_show_instances WHERE id = {$rebroadcast})";
            $isBeforeRecordedOriginal = $CC_DBC->GetOne($sql);

            if($isBeforeRecordedOriginal === 't'){
                return "Cannot move a rebroadcast show before its original";
            }
        }

        $this->moveScheduledShowContent($deltaDay, $hours, $mins);
        $this->setShowStart($new_starts);
        $this->setShowEnd($new_ends);
        RabbitMq::PushSchedule();
    }

    public function resizeShow($deltaDay, $deltaMin)
    {
        global $CC_DBC;

        $hours = $deltaMin/60;
        if($hours > 0)
            $hours = floor($hours);
        else
            $hours = ceil($hours);

        $mins = abs($deltaMin%60);

        $starts = $this->getShowStart();
        $ends = $this->getShowEnd();

        $sql = "SELECT timestamp '{$ends}' + interval '{$deltaDay} days' + interval '{$hours}:{$mins}'";
        $new_ends = $CC_DBC->GetOne($sql);

        //only need to check overlap if show increased in size.
        if(strtotime($new_ends) > strtotime($ends)) {
            $overlap =  Show::getShows($ends, $new_ends);

            if(count($overlap) > 0) {
                return "Should not overlap shows";
            }
        }
        //with overbooking no longer need to check already scheduled content still fits.

        //must update length of all rebroadcast instances.
        if($this->isRecorded()) {
            $sql = "UPDATE cc_show_instances SET ends = (ends + interval '{$deltaDay} days' + interval '{$hours}:{$mins}')
                    WHERE rebroadcast = 1 AND instance_id = {$this->_instanceId}";
            $CC_DBC->query($sql);
        }

        $this->setShowEnd($new_ends);
        RabbitMq::PushSchedule();
    }

    /**
     * Get the group ID for this show.
     *
     */
    private function getLastGroupId()
    {
        global $CC_DBC;
        $sql = "SELECT group_id FROM cc_schedule WHERE instance_id = '{$this->_instanceId}' ORDER BY ends DESC LIMIT 1";
        $res = $CC_DBC->GetOne($sql);
        return $res;
    }

    /**
     * Add a playlist as the last item of the current show.
     *
     * @param int $plId
     *         Playlist ID.
     */
    public function addPlaylistToShow($plId)
    {
        $sched = new ScheduleGroup();
        $lastGroupId = $this->getLastGroupId();

        if (is_null($lastGroupId)) {
            $groupId = $sched->add($this->_instanceId, $this->getShowStart(), null, $plId);
        }
        else {
            $groupId = $sched->addPlaylistAfter($this->_instanceId, $lastGroupId, $plId);
        }
        RabbitMq::PushSchedule();
        $this->updateScheduledTime();
    }

    /**
     * Add a media file as the last item in the show.
     *
     * @param int $file_id
     */
    public function addFileToShow($file_id)
    {
        $sched = new ScheduleGroup();
        $lastGroupId = $this->getLastGroupId();

        if (is_null($lastGroupId)) {
            $groupId = $sched->add($this->_instanceId, $this->getShowStart(), $file_id);
        }
        else {
            $groupId = $sched->addFileAfter($this->_instanceId, $lastGroupId, $file_id);
        }
        RabbitMq::PushSchedule();
        $this->updateScheduledTime();
    }

    /**
     * Add the given playlists to the show.
     *
     * @param array $plIds
     *         An array of playlist IDs.
     */
    public function scheduleShow($plIds)
    {
        foreach ($plIds as $plId) {
            $this->addPlaylistToShow($plId);
        }
    }

    public function removeGroupFromShow($group_id)
    {
        global $CC_DBC;

        $sql = "SELECT MAX(ends) as end_timestamp, (MAX(ends) - MIN(starts)) as length
                    FROM cc_schedule
                    WHERE group_id = '{$group_id}'";

        $groupBoundry = $CC_DBC->GetRow($sql);

        $group = CcScheduleQuery::create()
            ->filterByDbGroupId($group_id)
            ->delete();

        $sql = "UPDATE cc_schedule
                    SET starts = (starts - INTERVAL '{$groupBoundry["length"]}'), ends = (ends - INTERVAL '{$groupBoundry["length"]}')
                    WHERE starts >= '{$groupBoundry["end_timestamp"]}' AND instance_id = {$this->_instanceId}";

        $CC_DBC->query($sql);
        RabbitMq::PushSchedule();
        $this->updateScheduledTime();
    }

    public function clearShow()
    {
        CcScheduleQuery::create()
            ->filterByDbInstanceId($this->_instanceId)
            ->delete();
        RabbitMq::PushSchedule();
        $this->updateScheduledTime();
    }

    public function deleteShow()
    {
        CcShowInstancesQuery::create()
            ->findPK($this->_instanceId)
            ->delete();
        RabbitMq::PushSchedule();
    }

    public function setRecordedFile($file_id)
    {
        $showInstance = CcShowInstancesQuery::create()
            ->findPK($this->_instanceId);
        $showInstance->setDbRecordedFile($file_id)
            ->save();

        $rebroadcasts = CcShowInstancesQuery::create()
            ->filterByDbOriginalShow($this->_instanceId)
            ->find();

        foreach ($rebroadcasts as $rebroadcast) {

            $rebroad = new ShowInstance($rebroadcast->getDbId());
            $rebroad->addFileToShow($file_id);
        }
    }

    public function getTimeScheduled()
    {
        $showInstance = CcShowInstancesQuery::create()->findPK($this->_instanceId);
        $time = $showInstance->getDbTimeFilled();

        if(is_null($time)) {
            $time = "00:00:00";
        }
        return $time;
    }

    public function getPercentScheduled()
    {
        $start_timestamp = $this->getShowStart();
        $end_timestamp = $this->getShowEnd();
        $time_filled = $this->getTimeScheduled();
        
        $s_epoch = strtotime($start_timestamp);
        $e_epoch = strtotime($end_timestamp);
        $i_epoch = Schedule::WallTimeToMillisecs($time_filled) / 1000;

        $percent = ceil(($i_epoch / ($e_epoch - $s_epoch)) * 100);

        if ($percent > 100)
            $percent = 100;

        return $percent;
    }

    public function getShowLength()
    {
        global $CC_DBC;

        $start_timestamp = $this->getShowStart();
        $end_timestamp = $this->getShowEnd();

        $sql = "SELECT TIMESTAMP '{$end_timestamp}' - TIMESTAMP '{$start_timestamp}' ";
        $length = $CC_DBC->GetOne($sql);

        return $length;
    }

    public function searchPlaylistsForShow($datatables)
    {
        return StoredFile::searchPlaylistsForSchedule($datatables);
    }

    public function getShowListContent()
    {
        global $CC_DBC;

        $sql = "SELECT *
            FROM (cc_schedule AS s LEFT JOIN cc_files AS f ON f.id = s.file_id
                LEFT JOIN cc_playlist AS p ON p.id = s.playlist_id )

            WHERE s.instance_id = '{$this->_instanceId}' ORDER BY starts";

        return $CC_DBC->GetAll($sql);
    }

    public function getShowContent()
    {
        global $CC_DBC;

        $res = $this->getShowListContent();

        if(count($res) <= 0) {
            return $res;
        }

        $items = array();
        $currGroupId = -1;
        $pl_counter = -1;
        $f_counter = -1;
        foreach ($res as $row) {
            if($currGroupId != $row["group_id"]){
                $currGroupId = $row["group_id"];
                $pl_counter = $pl_counter + 1;
                $f_counter = -1;

                $items[$pl_counter]["pl_name"] = $row["name"];
                $items[$pl_counter]["pl_creator"] = $row["creator"];
                $items[$pl_counter]["pl_description"] = $row["description"];
                $items[$pl_counter]["pl_group"] = $row["group_id"];

                $sql = "SELECT SUM(clip_length) FROM cc_schedule WHERE group_id = '{$currGroupId}'";
                $length = $CC_DBC->GetOne($sql);

                $items[$pl_counter]["pl_length"] = $length;
            }
            $f_counter = $f_counter + 1;

            $items[$pl_counter]["pl_content"][$f_counter]["f_name"] = $row["track_title"];
            $items[$pl_counter]["pl_content"][$f_counter]["f_artist"] = $row["artist_name"];
            $items[$pl_counter]["pl_content"][$f_counter]["f_length"] = $row["length"];
        }

        return $items;
    }
}

/* Show Data Access Layer */
class Show_DAL {

    public static function GetCurrentShow($timeNow)
    {
        global $CC_CONFIG, $CC_DBC;

        $sql = "SELECT si.starts as start_timestamp, si.ends as end_timestamp, s.name, s.id, si.id as instance_id, si.record, s.url"
        ." FROM $CC_CONFIG[showInstances] si, $CC_CONFIG[showTable] s"
        ." WHERE si.show_id = s.id"
        ." AND si.starts <= TIMESTAMP '$timeNow'"
        ." AND si.ends > TIMESTAMP '$timeNow'";

        $rows = $CC_DBC->GetAll($sql);
        return $rows;
    }

    public static function GetNextShows($timeNow, $limit)
    {
        global $CC_CONFIG, $CC_DBC;

        $sql = "SELECT *, si.starts as start_timestamp, si.ends as end_timestamp FROM "
        ." $CC_CONFIG[showInstances] si, $CC_CONFIG[showTable] s"
        ." WHERE si.show_id = s.id"
        ." AND si.starts >= TIMESTAMP '$timeNow'"
        ." AND si.starts < TIMESTAMP '$timeNow' + INTERVAL '48 hours'"
        ." ORDER BY si.starts"
        ." LIMIT $limit";

        $rows = $CC_DBC->GetAll($sql);
        return $rows;
    }

    public static function GetShowsInRange($timeNow, $start, $end)
    {
        global $CC_CONFIG, $CC_DBC;
        $sql = "SELECT"
        ." si.starts as show_starts,"
        ." si.ends as show_ends,"
        ." si.rebroadcast as rebroadcast,"
        ." st.starts as item_starts,"
        ." st.ends as item_ends,"
        ." st.clip_length as clip_length,"
        ." ft.track_title as track_title,"
        ." ft.artist_name as artist_name,"
        ." ft.album_title as album_title,"
        ." s.name as show_name,"
        ." si.id as instance_id,"
        ." pt.name as playlist_name"
        ." FROM $CC_CONFIG[showInstances] si"
        ." LEFT JOIN $CC_CONFIG[scheduleTable] st"
        ." ON st.instance_id = si.id"
        ." LEFT JOIN $CC_CONFIG[playListTable] pt"
        ." ON st.playlist_id = pt.id"
        ." LEFT JOIN $CC_CONFIG[filesTable] ft"
        ." ON st.file_id = ft.id"
        ." LEFT JOIN $CC_CONFIG[showTable] s"
        ." ON si.show_id = s.id"
        ." WHERE ((si.starts < TIMESTAMP '$timeNow' - INTERVAL '$start seconds' AND si.ends > TIMESTAMP '$timeNow' - INTERVAL '$start seconds')"
        ." OR (si.starts > TIMESTAMP '$timeNow' - INTERVAL '$start seconds' AND si.ends < TIMESTAMP '$timeNow' + INTERVAL '$end seconds')"
        ." OR (si.starts < TIMESTAMP '$timeNow' + INTERVAL '$end seconds' AND si.ends > TIMESTAMP '$timeNow' + INTERVAL '$end seconds'))"
        //checking for st.starts IS NULL so that the query also returns shows that do not have any items scheduled.
        ." AND (st.starts < si.ends OR st.starts IS NULL)"
        ." ORDER BY si.starts, st.starts";

        return $CC_DBC->GetAll($sql);
    }

    public static function GetShowsByDayOfWeek($day){
        //DOW FROM TIMESTAMP
        //The day of the week (0 - 6; Sunday is 0) (for timestamp values only)

        //SELECT EXTRACT(DOW FROM TIMESTAMP '2001-02-16 20:38:40');
        //Result: 5

        global $CC_CONFIG, $CC_DBC;
        $sql = "SELECT"
        ." si.starts as show_starts,"
        ." si.ends as show_ends,"
        ." s.name as show_name,"
        ." s.url as url"
        ." FROM $CC_CONFIG[showInstances] si"
        ." LEFT JOIN $CC_CONFIG[showTable] s"
        ." ON si.show_id = s.id"
        ." WHERE EXTRACT(DOW FROM si.starts) = $day"
        ." AND EXTRACT(WEEK FROM si.starts) = EXTRACT(WEEK FROM localtimestamp)"
        ." ORDER BY si.starts";

        //echo $sql;

        return $CC_DBC->GetAll($sql);
    }
}
