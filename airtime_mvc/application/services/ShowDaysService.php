<?php

class Application_Service_ShowDaysService
{
    private $showId;

    public function __construct($id)
    {
        $this->showId = $id;
    }

    /**
     * 
     * Deletes all the cc_show_days entries for a specific show
     * that is currently being edited. They will get recreated with
     * the new show day specs
     */
    public function deleteShowDays()
    {
        CcShowDaysQuery::create()->filterByDbShowId($this->showId)->delete();
    }

    /**
     * 
     * Determines what the show end date should be based on
     * the form data
     * 
     * @param $showData add/edit show form data
     * @return DateTime object in user's local timezone
     */
    public function calculateEndDate($showData)
    {
        if ($showData['add_show_no_end']) {
            $endDate = NULL;
        } elseif ($showData['add_show_repeats']) {
            $endDate = new DateTime($showData['add_show_end_date']);
            $endDate->add(new DateInterval("P1D"));
        } else {
            $endDate = new DateTime($showData['add_show_start_date']);
            $endDate->add(new DateInterval("P1D"));
        }

        return $endDate;
    }

    /**
     * 
     * Sets the fields for a cc_show_days table row
     * @param $showData
     * @param $showId
     * @param $userId
     * @param $repeatType
     * @param $isRecorded
     * @param $showDay ccShowDay object we are setting values on
     */
    public function setShowDays($showData, $repeatType, $isRecorded)
    {
        $startDateTime = new DateTime($showData['add_show_start_date']." ".$showData['add_show_start_time']);

        $endDateTime = $this->calculateEndDate($showData);
        if (!is_null($endDateTime)) {
            $endDate = $endDateTime->format("Y-m-d");
        } else {
            $endDate = $endDateTime;
        }

        /* What we are doing here is checking if the show repeats or if
         * any repeating days have been checked. If not, then by default
         * the "selected" DOW is the initial day.
         * DOW in local time.
         */
        $startDow = date("w", $startDateTime->getTimestamp());
        if (!$showData['add_show_repeats']) {
            $showData['add_show_day_check'] = array($startDow);
        } elseif ($showData['add_show_repeats'] && $showData['add_show_day_check'] == "") {
            $showData['add_show_day_check'] = array($startDow);
        }

        // Don't set day for monthly repeat type, it's invalid
        if ($showData['add_show_repeats'] && $showData['add_show_repeat_type'] == 2) {
            $showDay = new CcShowDays();
            $showDay->setDbFirstShow($startDateTime->format("Y-m-d"));
            $showDay->setDbLastShow($endDate);
            $showDay->setDbStartTime($startDateTime->format("H:i:s"));
            $showDay->setDbTimezone(Application_Model_Preference::GetTimezone());
            $showDay->setDbDuration($showData['add_show_duration']);
            $showDay->setDbRepeatType($repeatType);
            $showDay->setDbShowId($this->showId);
            $showDay->setDbRecord($isRecorded);
            $showDay->save();
        } else {
            foreach ($showData['add_show_day_check'] as $day) {
                $daysAdd=0;
                $startDateTimeClone = clone $startDateTime;
                if ($startDow !== $day) {
                    if ($startDow > $day)
                        $daysAdd = 6 - $startDow + 1 + $day;
                    else
                        $daysAdd = $day - $startDow;

                    $startDateTimeClone->add(new DateInterval("P".$daysAdd."D"));
                }
                if (is_null($endDate) || $startDateTimeClone->getTimestamp() <= $endDateTime->getTimestamp()) {
                    $showDay = new CcShowDays();
                    $showDay->setDbFirstShow($startDateTimeClone->format("Y-m-d"));
                    $showDay->setDbLastShow($endDate);
                    $showDay->setDbStartTime($startDateTimeClone->format("H:i"));
                    $showDay->setDbTimezone(Application_Model_Preference::GetTimezone());
                    $showDay->setDbDuration($showData['add_show_duration']);
                    $showDay->setDbDay($day);
                    $showDay->setDbRepeatType($repeatType);
                    $showDay->setDbShowId($this->showId);
                    $showDay->setDbRecord($isRecorded);
                    $showDay->save();
                }
            }
        }
    }

    /**
     * 
     * Gets the cc_show_days entries for a specific show
     * 
     * @return array of ccShowDays objects
     */
    public function getShowDays()
    {
        return CcShowDaysQuery::create()->filterByDbShowId(
            $this->showId)->find();
    }

    public function getStartDateAndTime()
    {
        //CcShowDays object
        $showDay = $this->getCurrentShowDay();

        $dt = new DateTime($showDay->getDbFirstShow()." ".$showDay->getDbStartTime(),
            new DateTimeZone($showDay->getDbTimezone()));
        $dt->setTimezone(new DateTimeZone("UTC"));

        return $dt->format("Y-m-d H:i");
    }

    /**
     * 
     * Returns a CcShowDays object of the show that
     * is currently being edited.
     */
    public function getCurrentShowDay()
    {
        return CcShowDaysQuery::create()->filterByDbShowId($this->showId)
            ->findOne();
    }

    public function getRepeatingEndDate()
    {
        $sql = <<<SQL
SELECT last_show
FROM cc_show_days
WHERE show_id = :showId
ORDER BY last_show DESC
SQL;

        $query = Application_Common_Database::prepareAndExecute( $sql,
            array( 'showId' => $this->showId ), 'column' );

        return ($query !== false) ? $query : false;
    }

    public function getNextStartDateTime($showDay)
    {
        $nextPopDate = $showDay->getDbNextPopDate();
        $startTime = $showDay->getDbStartTime();

        if (isset($nextPopDate)) {
            return $nextPopDate." ".$startTime;
        } else {
            return $showDay->getDbFirstShow()." ".$startTime;
        }
    }
}