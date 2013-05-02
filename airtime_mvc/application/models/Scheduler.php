<?php

class Application_Model_Scheduler
{
    private $con;
    private $fileInfo = array(
            "id" => "",
            "cliplength" => "",
            "cuein" => "00:00:00",
            "cueout" => "00:00:00",
            "fadein" => "00:00:00",
            "fadeout" => "00:00:00",
            "sched_id" => null,
            "type" => 0 //default type of '0' to represent files. type '1' represents a webstream
        );

    private $epochNow;
    private $nowDT;
    private $user;
    
    private $crossfadeDuration;

    private $checkUserPermissions = true;

    public function __construct()
    {
        $this->con = Propel::getConnection(CcSchedulePeer::DATABASE_NAME);

        //subtracting one because sometimes when we cancel a track, we set its end time
        //to epochNow and then send the new schedule to pypo. Sometimes the currently cancelled
        //track can still be included in the new schedule because it may have a few ms left to play.
        //subtracting 1 second from epochNow resolves this issue.
        $this->epochNow = microtime(true)-1;
        $this->nowDT = DateTime::createFromFormat("U.u", $this->epochNow, new DateTimeZone("UTC"));

        if ($this->nowDT === false) {
            // DateTime::createFromFormat does not support millisecond string formatting in PHP 5.3.2 (Ubuntu 10.04).
            // In PHP 5.3.3 (Ubuntu 10.10), this has been fixed.
            $this->nowDT = DateTime::createFromFormat("U", time(), new DateTimeZone("UTC"));
        }

        $this->user = Application_Model_User::getCurrentUser();
        
        $this->crossfadeDuration = Application_Model_Preference::GetDefaultCrossfadeDuration();
    }

    public function setCheckUserPermissions($value)
    {
        $this->checkUserPermissions = $value;
    }

    private function validateItemMove($itemsToMove, $destination)
    {
        $destinationInstanceId = $destination["instance"];
        $destinationCcShowInstance = CcShowInstancesQuery::create()
            ->findPk($destinationInstanceId);
        $isDestinationLinked = $destinationCcShowInstance->getCcShow()->isLinked();

        foreach ($itemsToMove as $itemToMove) {
            $sourceInstanceId = $itemToMove["instance"];
            $ccShowInstance = CcShowInstancesQuery::create()
                ->findPk($sourceInstanceId);

            //does the item being moved belong to a linked show
            $isSourceLinked = $ccShowInstance->getCcShow()->isLinked();

            if ($isDestinationLinked && !$isSourceLinked) {
                throw new Exception("Cannot move items into linked shows");
            } elseif (!$isDestinationLinked && $isSourceLinked) {
                throw new Exception("Cannot move items out of linked shows");
            } elseif ($isSourceLinked && $sourceInstanceId != $destinationInstanceId) {
                throw new Exception(_("Cannot move items out of linked shows"));
            }
        }
    }

    /*
     * make sure any incoming requests for scheduling are ligit.
    *
    * @param array $items, an array containing pks of cc_schedule items.
    */
    private function validateRequest($items, $addAction=false)
    {
        //$items is where tracks get inserted (they are schedule locations)

        $nowEpoch = floatval($this->nowDT->format("U.u"));

        for ($i = 0; $i < count($items); $i++) {
            $id = $items[$i]["id"];

            //could be added to the beginning of a show, which sends id = 0;
            if ($id > 0) {
                //schedule_id of where we are inserting after?
                $schedInfo[$id] = $items[$i]["instance"];
            }

            //format is instance_id => timestamp
            $instanceInfo[$items[$i]["instance"]] = $items[$i]["timestamp"];
        }

        if (count($instanceInfo) === 0) {
            throw new Exception("Invalid Request.");
        }

        $schedIds = array();
        if (isset($schedInfo)) {
            $schedIds = array_keys($schedInfo);
        }
        $schedItems = CcScheduleQuery::create()->findPKs($schedIds, $this->con);
        $instanceIds = array_keys($instanceInfo);
        $showInstances = CcShowInstancesQuery::create()->findPKs($instanceIds, $this->con);

        //an item has been deleted
        if (count($schedIds) !== count($schedItems)) {
            throw new OutDatedScheduleException(_("The schedule you're viewing is out of date! (sched mismatch)"));
        }

        //a show has been deleted
        if (count($instanceIds) !== count($showInstances)) {
            throw new OutDatedScheduleException(_("The schedule you're viewing is out of date! (instance mismatch)"));
        }

        foreach ($schedItems as $schedItem) {
            $id = $schedItem->getDbId();
            $instance = $schedItem->getCcShowInstances($this->con);

            if (intval($schedInfo[$id]) !== $instance->getDbId()) {
                throw new OutDatedScheduleException(_("The schedule you're viewing is out of date!"));
            }
        }

        foreach ($showInstances as $instance) {

            $id = $instance->getDbId();
            $show = $instance->getCcShow($this->con);

            if ($this->checkUserPermissions && $this->user->canSchedule($show->getDbId()) === false) {
                throw new Exception(sprintf(_("You are not allowed to schedule show %s."), $show->getDbName()));
            }

            if ($instance->getDbRecord()) {
                throw new Exception(_("You cannot add files to recording shows."));
            }

            $showEndEpoch = floatval($instance->getDbEnds("U.u"));

            if ($showEndEpoch < $nowEpoch) {
                throw new OutDatedScheduleException(sprintf(_("The show %s is over and cannot be scheduled."), $show->getDbName()));
            }

            $ts = intval($instanceInfo[$id]);
            $lastSchedTs = intval($instance->getDbLastScheduled("U")) ? : 0;
            if ($ts < $lastSchedTs) {
                Logging::info("ts {$ts} last sched {$lastSchedTs}");
                throw new OutDatedScheduleException(sprintf(_("The show %s has been previously updated!"), $show->getDbName()));
            }

            /*
             * Does the afterItem belong to a show that is linked AND
             * currently playing?
             * If yes, throw an exception
             */
            if ($addAction) {
                $ccShow = $instance->getCcShow();
                if ($ccShow->isLinked()) {
                    //get all the linked shows instances and check if
                    //any of them are currently playing
                    $ccShowInstances = $ccShow->getCcShowInstancess();
                    $timeNowUTC = gmdate("Y-m-d H:i:s");
                    foreach ($ccShowInstances as $ccShowInstance) {

                        if ($ccShowInstance->getDbStarts() <= $timeNowUTC &&
                            $ccShowInstance->getDbEnds() > $timeNowUTC) {
                            throw new Exception(_("Content in linked shows must be scheduled before or after any one is broadcasted"));
                        }
                    }
                }
            }
        }
    }

    /*
     * @param $id
     * @param $type
     *
     * @return $files
     */
    private function retrieveMediaFiles($id, $type)
    {
        $files = array();

        if ($type === "audioclip") {
            $file = CcFilesQuery::create()->findPK($id, $this->con);
            $storedFile = new Application_Model_StoredFile($file, $this->con);

            if (is_null($file) || !$file->visible()) {
                throw new Exception(_("A selected File does not exist!"));
            } else {
                $data = $this->fileInfo;
                $data["id"] = $id;
                $data["cliplength"] = $storedFile->getRealClipLength(
                    $file->getDbCuein(),
                    $file->getDbCueout());

                $data["cuein"] = $file->getDbCuein();
                $data["cueout"] = $file->getDbCueout();

                //fade is in format SS.uuuuuu
                $data["fadein"] = Application_Model_Preference::GetDefaultFadeIn();
                $data["fadeout"] = Application_Model_Preference::GetDefaultFadeOut();

                $files[] = $data;
            }
        } elseif ($type === "playlist") {
            $pl = new Application_Model_Playlist($id);
            $contents = $pl->getContents();

            foreach ($contents as $plItem) {
                if ($plItem['type'] == 0) {
                    $data["id"] = $plItem['item_id'];
                    $data["cliplength"] = $plItem['length'];
                    $data["cuein"] = $plItem['cuein'];
                    $data["cueout"] = $plItem['cueout'];
                    $data["fadein"] = $plItem['fadein'];
                    $data["fadeout"] = $plItem['fadeout'];
                    $data["type"] = 0;
                    $files[] = $data;
                } elseif ($plItem['type'] == 1) {
                    $data["id"] = $plItem['item_id'];
                    $data["cliplength"] = $plItem['length'];
                    $data["cuein"] = $plItem['cuein'];
                    $data["cueout"] = $plItem['cueout'];
                    $data["fadein"] = "00.500000";//$plItem['fadein'];
                    $data["fadeout"] = "00.500000";//$plItem['fadeout'];
                    $data["type"] = 1;
                    $files[] = $data;
                } elseif ($plItem['type'] == 2) {
                    // if it's a block
                    $bl = new Application_Model_Block($plItem['item_id']);
                    if ($bl->isStatic()) {
                        foreach ($bl->getContents() as $track) {
                            $data["id"] = $track['item_id'];
                            $data["cliplength"] = $track['length'];
                            $data["cuein"] = $track['cuein'];
                            $data["cueout"] = $track['cueout'];
                            $data["fadein"] = $track['fadein'];
                            $data["fadeout"] = $track['fadeout'];
                            $data["type"] = 0;
                            $files[] = $data;
                        }
                    } else {
                        $dynamicFiles = $bl->getListOfFilesUnderLimit();
                        foreach ($dynamicFiles as $f) {
                            $fileId = $f['id'];
                            $file = CcFilesQuery::create()->findPk($fileId);
                            if (isset($file) && $file->visible()) {
                                $data["id"] = $file->getDbId();
                                $data["cuein"] = $file->getDbCuein();
                                $data["cueout"] = $file->getDbCueout();

                                $cuein = Application_Common_DateHelper::calculateLengthInSeconds($data["cuein"]);
                                $cueout = Application_Common_DateHelper::calculateLengthInSeconds($data["cueout"]);
                                $data["cliplength"] = Application_Common_DateHelper::secondsToPlaylistTime($cueout - $cuein);
                                
                                //fade is in format SS.uuuuuu
                                $data["fadein"] = Application_Model_Preference::GetDefaultFadeIn();
                                $data["fadeout"] = Application_Model_Preference::GetDefaultFadeOut();
                                
                                $data["type"] = 0;
                                $files[] = $data;
                            }
                        }
                    }
                }
            }
        } elseif ($type == "stream") {
            //need to return
             $stream = CcWebstreamQuery::create()->findPK($id, $this->con);

            if (is_null($stream) /* || !$file->visible() */) {
                throw new Exception(_("A selected File does not exist!"));
            } else {
                $data = $this->fileInfo;
                $data["id"] = $id;
                $data["cliplength"] = $stream->getDbLength();
                $data["cueout"] = $stream->getDbLength();
                $data["type"] = 1;

                //fade is in format SS.uuuuuu
                $data["fadein"] = Application_Model_Preference::GetDefaultFadeIn();
                $data["fadeout"] = Application_Model_Preference::GetDefaultFadeOut();

                $files[] = $data;
            }
        } elseif ($type == "block") {
            $bl = new Application_Model_Block($id);
            if ($bl->isStatic()) {
                foreach ($bl->getContents() as $track) {
                    $data["id"] = $track['item_id'];
                    $data["cliplength"] = $track['length'];
                    $data["cuein"] = $track['cuein'];
                    $data["cueout"] = $track['cueout'];
                    $data["fadein"] = $track['fadein'];
                    $data["fadeout"] = $track['fadeout'];
                    $data["type"] = 0;
                    $files[] = $data;
                }
            } else {
                $dynamicFiles = $bl->getListOfFilesUnderLimit();
                foreach ($dynamicFiles as $f) {
                    $fileId = $f['id'];
                    $file = CcFilesQuery::create()->findPk($fileId);
                    if (isset($file) && $file->visible()) {
                        $data["id"] = $file->getDbId();
                        $data["cuein"] = $file->getDbCuein();
                        $data["cueout"] = $file->getDbCueout();

                        $cuein = Application_Common_DateHelper::calculateLengthInSeconds($data["cuein"]);
                        $cueout = Application_Common_DateHelper::calculateLengthInSeconds($data["cueout"]);
                        $data["cliplength"] = Application_Common_DateHelper::secondsToPlaylistTime($cueout - $cuein);
                        
                        //fade is in format SS.uuuuuu
                		$data["fadein"] = Application_Model_Preference::GetDefaultFadeIn();
                		$data["fadeout"] = Application_Model_Preference::GetDefaultFadeOut();
                		
                        $data["type"] = 0;
                        $files[] = $data;
                    }
                }
            }
        }

        return $files;
    }
    
    /*
     * @param DateTime startDT in UTC
    *  @param string duration
    *      in format H:i:s.u (could be more that 24 hours)
    *
    * @return DateTime endDT in UTC
    */
    private function findTimeDifference($p_startDT, $p_seconds)
    {
    	$startEpoch = $p_startDT->format("U.u");
    	
    	//add two float numbers to 6 subsecond precision
    	//DateTime::createFromFormat("U.u") will have a problem if there is no decimal in the resulting number.
    	$newEpoch = bcsub($startEpoch , (string) $p_seconds, 6);
    
    	$dt = DateTime::createFromFormat("U.u", $newEpoch, new DateTimeZone("UTC"));
    
    	if ($dt === false) {
    		//PHP 5.3.2 problem
    		$dt = DateTime::createFromFormat("U", intval($newEpoch), new DateTimeZone("UTC"));
    	}
    
    	return $dt;
    }

    /*
     * @param DateTime startDT in UTC
     * @param string duration
     *      in format H:i:s.u (could be more that 24 hours)
     *
     * @return DateTime endDT in UTC
     */
    private function findEndTime($p_startDT, $p_duration)
    {
        $startEpoch = $p_startDT->format("U.u");
        $durationSeconds = Application_Common_DateHelper::playlistTimeToSeconds($p_duration);

        //add two float numbers to 6 subsecond precision
        //DateTime::createFromFormat("U.u") will have a problem if there is no decimal in the resulting number.
        $endEpoch = bcadd($startEpoch , (string) $durationSeconds, 6);

        $dt = DateTime::createFromFormat("U.u", $endEpoch, new DateTimeZone("UTC"));

        if ($dt === false) {
            //PHP 5.3.2 problem
            $dt = DateTime::createFromFormat("U", intval($endEpoch), new DateTimeZone("UTC"));
        }

        return $dt;
    }

    private function findNextStartTime($DT, $instance)
    {
        $sEpoch = $DT->format("U.u");
        $nEpoch = $this->epochNow;

        //check for if the show has started.
        if (bccomp( $nEpoch , $sEpoch , 6) === 1) {
            //need some kind of placeholder for cc_schedule.
            //playout_status will be -1.
            $nextDT = $this->nowDT;

            $length = bcsub($nEpoch , $sEpoch , 6);
            $cliplength = Application_Common_DateHelper::secondsToPlaylistTime($length);

            //fillers are for only storing a chunk of time space that has already passed.
            $filler = new CcSchedule();
            $filler->setDbStarts($DT)
                ->setDbEnds($this->nowDT)
                ->setDbClipLength($cliplength)
                ->setDbCueIn('00:00:00')
                ->setDbCueOut('00:00:00')
                ->setDbPlayoutStatus(-1)
                ->setDbInstanceId($instance->getDbId())
                ->save($this->con);
        } else {
            $nextDT = $DT;
        }

        return $nextDT;
    }
    
    /*
     * @param int $showInstance
     *   This function recalculates the start/end times of items in a gapless show to
     *   account for crossfade durations.
     */
    private function calculateCrossfades($showInstance)
    {
    	Logging::info("adjusting start, end times of scheduled items to account for crossfades show instance #".$showInstance);
    
    	$instance = CcShowInstancesQuery::create()->findPK($showInstance, $this->con);
    	if (is_null($instance)) {
    		throw new OutDatedScheduleException(_("The schedule you're viewing is out of date!"));
    	}
    
    	$itemStartDT = $instance->getDbStarts(null);
    	$itemEndDT = null;
    
    	$schedule = CcScheduleQuery::create()
    		->filterByDbInstanceId($showInstance)
    		->orderByDbStarts()
    		->find($this->con);
    
    	foreach ($schedule as $item) {
    
    		$itemEndDT = $item->getDbEnds(null);
    		
    		$item
    			->setDbStarts($itemStartDT)
    			->setDbEnds($itemEndDT);
    
    		$itemStartDT = $this->findTimeDifference($itemEndDT, $this->crossfadeDuration);
    		$itemEndDT = $this->findEndTime($itemStartDT, $item->getDbClipLength());
    	}
    
    	$schedule->save($this->con);
    }

    /*
     * @param int $showInstance
    * @param array $exclude
    *   ids of sched items to remove from the calulation.
    *   This function squeezes all items of a show together so that
    *   there are no gaps between them.
    */
    public function removeGaps($showInstance, $exclude=null)
    {
        Logging::info("removing gaps from show instance #".$showInstance);

        $instance = CcShowInstancesQuery::create()->findPK($showInstance, $this->con);
        if (is_null($instance)) {
            throw new OutDatedScheduleException(_("The schedule you're viewing is out of date!"));
        }

        $itemStartDT = $instance->getDbStarts(null);

        $schedule = CcScheduleQuery::create()
            ->filterByDbInstanceId($showInstance)
            ->filterByDbId($exclude, Criteria::NOT_IN)
            ->orderByDbStarts()
            ->find($this->con);

        foreach ($schedule as $item) {

            $itemEndDT = $this->findEndTime($itemStartDT, $item->getDbClipLength());

            $item->setDbStarts($itemStartDT)
                ->setDbEnds($itemEndDT);

            $itemStartDT = $itemEndDT;
        }

        $schedule->save($this->con);
    }

    /**
     * 
     * Enter description here ...
     * @param $scheduleItems
     *     cc_schedule items, where the items get inserted after
     * @param $filesToInsert
     *     array of schedule item info, what gets inserted into cc_schedule
     * @param $adjustSched
     */
    private function insertAfter($scheduleItems, $mediaItems, $filesToInsert=null, $adjustSched=true, $moveAction=false)
    {
        try {
            $affectedShowInstances = array();

            //dont want to recalculate times for moved items
            //only moved items have a sched_id
            $excludeIds = array();

            $startProfile = microtime(true);

            $temp = array();
            $instance = null;
            /* Items in shows are ordered by position number. We need to know
             * the position when adding/moving items in linked shows so they are
             * added or moved in the correct position
             */
            $pos = 0;

            foreach ($scheduleItems as $schedule) {
                $id = intval($schedule["id"]);

                /* Find out if the show where the cursor position (where an item will
                 * be inserted) is located is linked or not. If the show is linked,
                 * we need to make sure there isn't another cursor selection in one of it's
                 * linked shows. If there is that will cause a duplication, in the least,
                 * of inserted items
                 */
                if ($id != 0) {
                    $ccSchedule = CcScheduleQuery::create()->findPk($schedule["id"]);
                    $ccShowInstance = CcShowInstancesQuery::create()->findPk($ccSchedule->getDbInstanceId());
                    $ccShow = $ccShowInstance->getCcShow();
                    if ($ccShow->isLinked()) {
                        $unique = $ccShow->getDbId() . $ccSchedule->getDbPosition();
                        if (!in_array($unique, $temp)) {
                            $temp[] = $unique;
                        } else {
                            continue;
                        }
                    }
                } else {
                    $ccShowInstance = CcShowInstancesQuery::create()->findPk($schedule["instance"]);
                    $ccShow = $ccShowInstance->getccShow();
                    if ($ccShow->isLinked()) {
                        $unique = $ccShow->getDbId() . "a";
                        if (!in_array($unique, $temp)) {
                            $temp[] = $unique;
                        } else {
                            continue;
                        }
                    }
                }

                /* If the show where the cursor position is located is linked
                 * we need to insert the items for each linked instance belonging
                 * to that show
                 */
                $instances = $this->getInstances($schedule["instance"]);
                foreach($instances as $instance) {
                    if ($id !== 0) {
                        $schedItem = CcScheduleQuery::create()->findPK($id, $this->con);
                        /* We use the selected cursor's position to find the same
                         * positions in every other linked instance
                         */
                        $pos = $schedItem->getDbPosition();

                        $ccSchedule = CcScheduleQuery::create()
                            ->filterByDbInstanceId($instance->getDbId())
                            ->filterByDbPosition($pos)
                            ->findOne();

                        //$schedItemEndDT = $schedItem->getDbEnds(null);
                        $schedItemEndDT = $ccSchedule->getDbEnds(null);
                        $nextStartDT = $this->findNextStartTime($schedItemEndDT, $instance);

                        $pos++;
                    }
                    //selected empty row to add after
                    else {
                        $showStartDT = $instance->getDbStarts(null);
                        $nextStartDT = $this->findNextStartTime($showStartDT, $instance);

                        //show is empty so start position counter at 0
                        $pos = 0;
                    }

                    if (!in_array($instance->getDbId(), $affectedShowInstances)) {
                        $affectedShowInstances[] = $instance->getDbId();
                    }

                    /*
                     * $adjustSched is true if there are schedule items
                     * following the item just inserted, per show instance
                     */
                    if ($adjustSched === true) {

                        $pstart = microtime(true);

                        $initalStartDT = clone $nextStartDT;

                        $pend = microtime(true);
                        Logging::debug("finding all following items.");
                        Logging::debug(floatval($pend) - floatval($pstart));
                    }

                    if (is_null($filesToInsert)) {
                        $filesToInsert = array();
                        foreach ($mediaItems as $media) {
                            $filesToInsert = array_merge($filesToInsert, $this->retrieveMediaFiles($media["id"], $media["type"]));
                        }
                    }
                    foreach ($filesToInsert as $file) {
                        //item existed previously and is being moved.
                        //need to keep same id for resources if we want REST.
                        if (isset($file['sched_id'])) {
                            $sched = CcScheduleQuery::create()->findPk($file["sched_id"]);

                            $excludeIds[] = intval($sched->getDbId());

                            $file["cliplength"] = $sched->getDbClipLength();
                            $file["cuein"] = $sched->getDbCueIn();
                            $file["cueout"] = $sched->getDbCueOut();
                            $file["fadein"] = $sched->getDbFadeIn();
                            $file["fadeout"] = $sched->getDbFadeOut();
                        } else {
                            $sched = new CcSchedule();
                        }

                        $endTimeDT = $this->findEndTime($nextStartDT, $file['cliplength']);
                        // default fades are in seconds
                        // we need to convert to '00:00:00' format
                        $file['fadein'] = Application_Common_DateHelper::secondsToPlaylistTime($file['fadein']);
                        $file['fadeout'] = Application_Common_DateHelper::secondsToPlaylistTime($file['fadeout']);

                        $sched->setDbStarts($nextStartDT)
                            ->setDbEnds($endTimeDT)
                            ->setDbCueIn($file['cuein'])
                            ->setDbCueOut($file['cueout'])
                            ->setDbFadeIn($file['fadein'])
                            ->setDbFadeOut($file['fadeout'])
                            ->setDbClipLength($file['cliplength'])
                            ->setDbPosition($pos);
                            //->setDbInstanceId($instance->getDbId());
                        if (!$moveAction) {
                            $sched->setDbInstanceId($instance->getDbId());
                        }

                        switch ($file["type"]) {
                            case 0:
                                $sched->setDbFileId($file['id']);
                                break;
                            case 1:
                                $sched->setDbStreamId($file['id']);
                                break;
                            default: break;
                        }

                        $sched->save($this->con);

                        $nextStartDT = $endTimeDT;
                        $pos++;

                        /* If we are adjusting start and end times for items
                         * after the insert location, we need to exclude the
                         * schedule item we just inserted because it has correct
                         * start and end times*/
                        $excludeIds[] = $sched->getDbId();

                    }//all files have been inserted/moved

                    // update is_scheduled flag for each cc_file
                    foreach ($filesToInsert as $file) {
                        $db_file = CcFilesQuery::create()->findPk($file['id'], $this->con);
                        $db_file->setDbIsScheduled(true);
                        $db_file->save($this->con);
                    }
                    /* Reset files to insert so we can get a new set of files. We have
                     * to do this in case we are inserting a dynamic block
                     */
                    if (!$moveAction) {
                        $filesToInsert = null;
                    }

                    if ($adjustSched === true) {
                        $followingSchedItems = CcScheduleQuery::create()
                            ->filterByDBStarts($initalStartDT->format("Y-m-d H:i:s.u"), Criteria::GREATER_EQUAL)
                            ->filterByDbInstanceId($instance->getDbId())
                            ->filterByDbId($excludeIds, Criteria::NOT_IN)
                            ->orderByDbStarts()
                            ->find($this->con);

                        $pstart = microtime(true);

                        //recalculate the start/end times after the inserted items.
                        foreach ($followingSchedItems as $item) {
                            $endTimeDT = $this->findEndTime($nextStartDT, $item->getDbClipLength());

                            $item->setDbStarts($nextStartDT);
                            $item->setDbEnds($endTimeDT);
                            $item->setDbPosition($pos);
                            $item->save($this->con);
                            $nextStartDT = $endTimeDT;
                            $pos++;
                        }

                        $pend = microtime(true);
                        Logging::debug("adjusting all following items.");
                        Logging::debug(floatval($pend) - floatval($pstart));
                        
                        $this->calculateCrossfades($instance->getDbId());
                    }
                }//for each instance
                
            }//for each schedule location

            $endProfile = microtime(true);
            Logging::debug("finished adding scheduled items.");
            Logging::debug(floatval($endProfile) - floatval($startProfile));

            //update the status flag in cc_schedule.
            $instances = CcShowInstancesQuery::create()
                ->filterByPrimaryKeys($affectedShowInstances)
                ->find($this->con);

            $startProfile = microtime(true);

            foreach ($instances as $instance) {
                $instance->updateScheduleStatus($this->con);
            }

            $endProfile = microtime(true);
            Logging::debug("updating show instances status.");
            Logging::debug(floatval($endProfile) - floatval($startProfile));

            $startProfile = microtime(true);

            //update the last scheduled timestamp.
            CcShowInstancesQuery::create()
                ->filterByPrimaryKeys($affectedShowInstances)
                ->update(array('DbLastScheduled' => new DateTime("now", new DateTimeZone("UTC"))), $this->con);

            $endProfile = microtime(true);
            Logging::debug("updating last scheduled timestamp.");
            Logging::debug(floatval($endProfile) - floatval($startProfile));
        } catch (Exception $e) {
            Logging::debug($e->getMessage());
            throw $e;
        }
    }

    private function getInstances($instanceId)
    {
        $ccShowInstance = CcShowInstancesQuery::create()->findPk($instanceId);
        $ccShow = $ccShowInstance->getCcShow();
        if ($ccShow->isLinked()) {
            return $ccShow->getCcShowInstancess();
        } else {
            return array($ccShowInstance);
        }
    }

    /*
     * @param array $scheduleItems (schedule_id and instance_id it belongs to)
     * @param array $mediaItems (file|block|playlist|webstream)
     */
    public function scheduleAfter($scheduleItems, $mediaItems, $adjustSched = true)
    {
        $this->con->beginTransaction();

        try {
            $this->validateRequest($scheduleItems, true);

            /*
             * create array of arrays
             * array of schedule item info
             * (sched_id is the cc_schedule id and is set if an item is being
             *  moved because it is already in cc_schedule)
             * [0] = Array(
             *     id => 1,
             *     cliplength => 00:04:32,
             *     cuein => 00:00:00,
             *     cueout => 00:04:32,
             *     fadein => 00.5,
             *     fadeout => 00.5,
             *     sched_id => ,
             *     type => 0)
             * [1] = Array(
             *     id => 2,
             *     cliplength => 00:05:07,
             *     cuein => 00:00:00,
             *     cueout => 00:05:07,
             *     fadein => 00.5,
             *     fadeout => 00.5,
             *     sched_id => ,
             *     type => 0)
             */
            $this->insertAfter($scheduleItems, $mediaItems, null, $adjustSched);

            $this->con->commit();

            Application_Model_RabbitMq::PushSchedule();
        } catch (Exception $e) {
            $this->con->rollback();
            throw $e;
        }
    }

    /*
     * @param array $selectedItem
     * @param array $afterItem
     */
    public function moveItem($selectedItems, $afterItems, $adjustSched = true)
    {
        $startProfile = microtime(true);

        $this->con->beginTransaction();
        $this->con->useDebug(true);

        try {

            $this->validateItemMove($selectedItems, $afterItems[0]);
            $this->validateRequest($selectedItems);
            $this->validateRequest($afterItems);

            $endProfile = microtime(true);
            Logging::debug("validating move request took:");
            Logging::debug(floatval($endProfile) - floatval($startProfile));

            $afterInstance = CcShowInstancesQuery::create()->findPK($afterItems[0]["instance"], $this->con);

            //map show instances to cc_schedule primary keys.
            $modifiedMap = array();
            $movedData = array();

            //prepare each of the selected items.
            for ($i = 0; $i < count($selectedItems); $i++) {

                $selected = CcScheduleQuery::create()->findPk($selectedItems[$i]["id"], $this->con);
                $selectedInstance = $selected->getCcShowInstances($this->con);

                $data = $this->fileInfo;
                $data["id"] = $selected->getDbFileId();
                $data["cliplength"] = $selected->getDbClipLength();
                $data["cuein"] = $selected->getDbCueIn();
                $data["cueout"] = $selected->getDbCueOut();
                $data["fadein"] = $selected->getDbFadeIn();
                $data["fadeout"] = $selected->getDbFadeOut();
                $data["sched_id"] = $selected->getDbId();

                $movedData[] = $data;

                //figure out which items must be removed from calculated show times.
                $showInstanceId = $selectedInstance->getDbId();
                $schedId = $selected->getDbId();
                if (isset($modifiedMap[$showInstanceId])) {
                    array_push($modifiedMap[$showInstanceId], $schedId);
                } else {
                    $modifiedMap[$showInstanceId] = array($schedId);
                }
            }

            //calculate times excluding the to be moved items.
            foreach ($modifiedMap as $instance => $schedIds) {
                $startProfile = microtime(true);

                $this->removeGaps($instance, $schedIds);

                $endProfile = microtime(true);
                Logging::debug("removing gaps from instance $instance:");
                Logging::debug(floatval($endProfile) - floatval($startProfile));
            }

            $startProfile = microtime(true);

            $this->insertAfter($afterItems, null, $movedData, $adjustSched, true);

            $endProfile = microtime(true);
            Logging::debug("inserting after removing gaps.");
            Logging::debug(floatval($endProfile) - floatval($startProfile));

            $modified = array_keys($modifiedMap);
            //need to adjust shows we have moved items from.
            foreach ($modified as $instanceId) {

                $instance = CcShowInstancesQuery::create()->findPK($instanceId, $this->con);
                $instance->updateScheduleStatus($this->con);
            }

            $this->con->useDebug(false);
            $this->con->commit();

            Application_Model_RabbitMq::PushSchedule();
        } catch (Exception $e) {
            $this->con->rollback();
            throw $e;
        }
    }

    public function removeItems($scheduledItems, $adjustSched = true, $cancelShow=false)
    {
        $showInstances = array();
        $this->con->beginTransaction();

        try {

            $this->validateRequest($scheduledItems);

            $scheduledIds = array();
            foreach ($scheduledItems as $item) {
                $scheduledIds[] = $item["id"];
            }

            $removedItems = CcScheduleQuery::create()->findPks($scheduledIds);

            //check to make sure all items selected are up to date
            foreach ($removedItems as $removedItem) {

                $instance = $removedItem->getCcShowInstances($this->con);

                //check if instance is linked and if so get the schedule items
                //for all linked instances so we can delete them too
                if (!$cancelShow && $instance->getCcShow()->isLinked()) {
                    //returns all linked instances if linked
                    $ccShowInstances = $this->getInstances($instance->getDbId());
                    $instanceIds = array();
                    foreach ($ccShowInstances as $ccShowInstance) {
                        $instanceIds[] = $ccShowInstance->getDbId();
                    }
                    /*
                     * Find all the schedule items that are in the same position
                     * as the selected item by the user.
                     * The position of each track is the same across each linked instance
                     */
                    $itemsToDelete = CcScheduleQuery::create()
                        ->filterByDbPosition($removedItem->getDbPosition())
                        ->filterByDbInstanceId($instanceIds, Criteria::IN)
                        ->find();
                    foreach ($itemsToDelete as $item) {
                        if (!$removedItems->contains($item)) {
                            $removedItems->append($item);
                        }
                    }
                }

                //check to truncate the currently playing item instead of deleting it.
                if ($removedItem->isCurrentItem($this->epochNow)) {

                    $nEpoch = $this->epochNow;
                    $sEpoch = $removedItem->getDbStarts('U.u');

                    $length = bcsub($nEpoch , $sEpoch , 6);
                    $cliplength = Application_Common_DateHelper::secondsToPlaylistTime($length);

                    $cueinSec = Application_Common_DateHelper::playlistTimeToSeconds($removedItem->getDbCueIn());
                    $cueOutSec = bcadd($cueinSec , $length, 6);
                    $cueout = Application_Common_DateHelper::secondsToPlaylistTime($cueOutSec);

                    //Set DbEnds - 1 second because otherwise there can be a timing issue
                    //when sending the new schedule to Pypo where Pypo thinks the track is still
                    //playing.
                    $removedItem->setDbCueOut($cueout)
                        ->setDbClipLength($cliplength)
                        ->setDbEnds($this->nowDT)
                        ->save($this->con);
                } else {
                    $removedItem->delete($this->con);
                }
                
                // update is_scheduled in cc_files but only if
                // the file is not scheduled somewhere else
                $fileId = $removedItem->getDbFileId();
                // check if the removed item is scheduled somewhere else
                $futureScheduledFiles = Application_Model_Schedule::getAllFutureScheduledFiles();
                if (!is_null($fileId) && !in_array($fileId, $futureScheduledFiles)) {
                     $db_file = CcFilesQuery::create()->findPk($fileId, $this->con);
                     $db_file->setDbIsScheduled(false)->save($this->con);
                }
            }

            if ($adjustSched === true) {
                //get the show instances of the shows we must adjust times for.
                foreach ($removedItems as $item) {

                    $instance = $item->getDBInstanceId();
                    if (!in_array($instance, $showInstances)) {
                        $showInstances[] = $instance;
                    }
                }

                foreach ($showInstances as $instance) {
                    $this->removeGaps($instance);
                    $this->calculateCrossfades($instance);
                }
            }

            //update the status flag in cc_schedule.
            $instances = CcShowInstancesQuery::create()
                ->filterByPrimaryKeys($showInstances)
                ->find($this->con);

            foreach ($instances as $instance) {
                $instance->updateScheduleStatus($this->con);
            }

            //update the last scheduled timestamp.
            CcShowInstancesQuery::create()
                ->filterByPrimaryKeys($showInstances)
                ->update(array('DbLastScheduled' => new DateTime("now", new DateTimeZone("UTC"))), $this->con);

            $this->con->commit();

            Application_Model_RabbitMq::PushSchedule();
        } catch (Exception $e) {
            $this->con->rollback();
            throw $e;
        }
    }

    /*
     * Used for cancelling the current show instance.
     *
     * @param $p_id id of the show instance to cancel.
     */
    public function cancelShow($p_id)
    {
        $this->con->beginTransaction();

        try {

            $instance = CcShowInstancesQuery::create()->findPK($p_id);

            if (!$instance->getDbRecord()) {

                $items = CcScheduleQuery::create()
                    ->filterByDbInstanceId($p_id)
                    ->filterByDbEnds($this->nowDT, Criteria::GREATER_THAN)
                    ->find($this->con);

                if (count($items) > 0) {
                    $remove = array();
                    $ts = $this->nowDT->format('U');

                    for ($i = 0; $i < count($items); $i++) {
                        $remove[$i]["instance"] = $p_id;
                        $remove[$i]["timestamp"] = $ts;
                        $remove[$i]["id"] = $items[$i]->getDbId();
                    }

                    $this->removeItems($remove, false, true);
                }
            } else {
                $rebroadcasts = $instance->getCcShowInstancessRelatedByDbId(null, $this->con);
                $rebroadcasts->delete($this->con);
            }

            $instance->setDbEnds($this->nowDT);
            $instance->save($this->con);

            $this->con->commit();

            if ($instance->getDbRecord()) {
                Application_Model_RabbitMq::SendMessageToShowRecorder("cancel_recording");
            }
        } catch (Exception $e) {
            $this->con->rollback();
            throw $e;
        }
    }
}

class OutDatedScheduleException extends Exception {}
