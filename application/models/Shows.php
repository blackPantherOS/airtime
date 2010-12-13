<?php

class Show {

	private $_user;

	public function __construct($user=NULL)
    {
		$this->_user = $user;    
    }

	//end dates are non inclusive.
	public function addShow($data) {
	
		$con = Propel::getConnection("campcaster");

		$sql = "SELECT time '{$data['start_time']}' + INTERVAL '{$data['duration']} hour' ";
		$r = $con->query($sql);
        $endTime = $r->fetchColumn(0); 

		$sql = "SELECT EXTRACT(DOW FROM TIMESTAMP '{$data['start_date']} {$data['start_time']}')";
		$r = $con->query($sql);
        $startDow = $r->fetchColumn(0);  

		if($data['no_end']) {
			$endDate = NULL;
			$data['repeats'] = 1;
		}
		else if($data['repeats']) {
			$sql = "SELECT date '{$data['end_date']}' + INTERVAL '1 day' ";
			$r = $con->query($sql);
        	$endDate = $r->fetchColumn(0); 
		}
		else {
			$sql = "SELECT date '{$data['start_date']}' + INTERVAL '1 day' ";
			$r = $con->query($sql);
        	$endDate = $r->fetchColumn(0);
		} 

		if($data['day_check'] === null) {
			$data['day_check'] = array($startDow);
		} 

		$overlap =  $this->getShows($data['start_date'], $endDate, $data['day_check'], $data['start_time'], $endTime);

		if(count($overlap) > 0) {
			return $overlap;
		}
		
		$show = new CcShow();
			$show->setDbName($data['name']);
			$show->setDbRepeats($data['repeats']);
			$show->setDbDescription($data['description']);
			$show->save();      

		$showId = $show->getDbId();

		foreach ($data['day_check'] as $day) {

			if($startDow !== $day){
				
				if($startDow > $day)
					$daysAdd = 6 - $startDow + 1 + $day;
				else
					$daysAdd = $day - $startDow;				

				$sql = "SELECT date '{$data['start_date']}' + INTERVAL '{$daysAdd} day' ";
				$r = $con->query($sql);
				$start = $r->fetchColumn(0); 
			}
			else {
				$start = $data['start_date'];
			}

			$showDay = new CcShowDays();
			$showDay->setDbFirstShow($start);
			$showDay->setDbLastShow($endDate);
			$showDay->setDbStartTime($data['start_time']);
			$showDay->setDbEndTime($endTime);
			$showDay->setDbDay($day);
			$showDay->setDbShowId($showId);
			$showDay->save();
		}
		
		foreach ($data['hosts'] as $host) {
			$showHost = new CcShowHosts();
			$showHost->setDbShow($showId);
			$showHost->setDbHost($host);
			$showHost->save();
		}
	}

	public function moveShow($showId, $deltaDay, $deltaMin){
		global $CC_DBC;

		$sql = "SELECT * FROM cc_show_days WHERE show_id = '{$showId}'";
		$res = $CC_DBC->GetAll($sql);

		$show = $res[0];
		$start = $show["first_show"];
		$end = $show["last_show"];
		$days = array();

		$hours = $deltaMin/60;
		if($hours > 0)
			$hours = floor($hours);
		else
			$hours = ceil($hours);

		$mins = abs($deltaMin%60);

		$sql = "SELECT time '{$show["start_time"]}' + interval '{$hours}:{$mins}'";
		$s_time = $CC_DBC->GetOne($sql);

		$sql = "SELECT time '{$show["end_time"]}' + interval '{$hours}:{$mins}'";
		$e_time = $CC_DBC->GetOne($sql);

		foreach($res as $show) {
			$days[] = $show["day"] + $deltaDay;
		}

		//need to check each specific day if times different then merge arrays.
		$overlap =  $this->getShows($start, $end, $days, $s_time, $e_time, array($showId));

		if(count($overlap) > 0) {
			return $overlap;
		}

		foreach($res as $row) {
			$show = CcShowDaysQuery::create()->findPK($row["id"]);
			$show->setDbStartTime($s_time);
			$show->setDbEndTime($e_time);
			$show->save();
		}		
	}

	public function resizeShow($showId, $deltaDay, $deltaMin){
		global $CC_DBC;

		$sql = "SELECT * FROM cc_show_days WHERE show_id = '{$showId}'";
		$res = $CC_DBC->GetAll($sql);

		$show = $res[0];
		$start = $show["first_show"];
		$end = $show["last_show"];
		$days = array();

		$hours = $deltaMin/60;
		if($hours > 0)
			$hours = floor($hours);
		else
			$hours = ceil($hours);

		$mins = abs($deltaMin%60);

		$s_time = $show["start_time"];

		$sql = "SELECT time '{$show["end_time"]}' + interval '{$hours}:{$mins}'";
		$e_time = $CC_DBC->GetOne($sql);

		foreach($res as $show) {
			$days[] = $show["day"] + $deltaDay;
		}

		//need to check each specific day if times different then merge arrays.
		$overlap =  $this->getShows($start, $end, $days, $s_time, $e_time, array($showId));

		if(count($overlap) > 0) {
			return $overlap;
		}

		foreach($res as $row) {
			$show = CcShowDaysQuery::create()->findPK($row["id"]);
			$show->setDbStartTime($s_time);
			$show->setDbEndTime($e_time);
			$show->save();
		}		

	}

	public function deleteShow($showId, $dayId=NULL) {
		CcShowQuery::create()->filterByDbId($showId)->delete();
	}

	public function getShows($start=NULL, $end=NULL, $days=NULL, $s_time=NULL, $e_time=NULL, $exclude_shows=NULL) {
		global $CC_DBC;

		$sql;
	
		$sql_gen = "SELECT cc_show_days.id AS day_id, name, repeats, description, 
			first_show, last_show, start_time, end_time, day, show_id  
			FROM (cc_show LEFT JOIN cc_show_days ON cc_show.id = cc_show_days.show_id)";

		$sql = $sql_gen;

		if(!is_null($start) && !is_null($end)) {
			$sql_range = "(first_show < '{$start}' AND last_show IS NULL) 
					OR (first_show >= '{$start}' AND first_show < '{$end}') 
					OR (last_show >= '{$start}' AND last_show < '{$end}')
					OR (first_show < '{$start}' AND last_show >= '{$end}')";

			$sql = $sql_gen ." WHERE ". $sql_range;
		}
		if(!is_null($start) && is_null($end)) {
			$sql_range = "(first_show <= '{$start}' AND last_show IS NULL) 
					OR (last_show > '{$start}')";

			$sql = $sql_gen ." WHERE ". $sql_range;
		}
		if(!is_null($days)){

			$sql_opt = array();
			foreach ($days as $day) {
				$sql_opt[] = "day = {$day}";
			}
			$sql_day = join(" OR ", $sql_opt);
				
			$sql = $sql_gen ." WHERE ((". $sql_day .") AND (". $sql_range ."))";
		}
		if(!is_null($s_time) && !is_null($e_time)) {
			$sql_time = "(start_time <= '{$s_time}' AND end_time >= '{$e_time}')
				OR (start_time >= '{$s_time}' AND end_time <= '{$e_time}')
				OR (end_time > '{$s_time}' AND end_time <= '{$e_time}')
				OR (start_time >= '{$s_time}' AND start_time < '{$e_time}')";

			$sql = $sql_gen ." WHERE ((". $sql_day .") AND (". $sql_range .") AND (". $sql_time ."))";
		}
		if(!is_null($exclude_shows)){

			$sql_opt = array();
			foreach ($exclude_shows as $showid) {
				$sql_opt[] = "show_id = {$showid}";
			}
			$sql_showid = join(" OR ", $sql_opt);
			
			$sql = $sql_gen ." WHERE ((". $sql_day .") AND NOT (". $sql_showid .") AND (". $sql_range .") AND (". $sql_time ."))";	
		}

		//echo $sql;

		return $CC_DBC->GetAll($sql);	
	}

	public function getFullCalendarEvents($start, $end, $weekday=NULL) {
		global $CC_DBC;
		$shows = array();

		$res = $this->getShows($start, $end, $weekday);

		foreach($res as $row) {

			if(!is_null($start)) { 

				$timeDiff = "SELECT date '{$start}' - date '{$row["first_show"]}' as diff";
				$diff = $CC_DBC->GetOne($timeDiff);

				if($diff > 0) {

					$add = ($diff % 7 === 0) ? $diff : $diff + (7 - $diff % 7);

					$new = "SELECT date '{$row["first_show"]}' + integer '{$add}'";
					$newDate = $CC_DBC->GetOne($new); 
				}
				else {
					$newDate = $row["first_show"];
				}

				$shows[] = $this->makeFullCalendarEvent($row, $newDate);
				
				$end_epoch = strtotime($end);

				//add repeating events until the show end is reached or fullcalendar's end date is reached.
				if($row["repeats"]) {

					if(!is_null($row["last_show"])) {
						$show_end_epoch = strtotime($row["last_show"]);
					}

					while(true) {

						$diff = "SELECT date '{$newDate}' + integer '7'";
						$repeatDate = $CC_DBC->GetOne($diff);
						$repeat_epoch = strtotime($repeatDate);

						//show has finite duration.
						if (isset($show_end_epoch) && $repeat_epoch < $show_end_epoch && $repeat_epoch < $end_epoch) {
							$shows[] = $this->makeFullCalendarEvent($row, $repeatDate);
						}
						//case for non-ending shows.
						else if(!isset($show_end_epoch) && $repeat_epoch < $end_epoch) {
							$shows[] = $this->makeFullCalendarEvent($row, $repeatDate);
						}
						else {
							break;
						}

						$newDate = $repeatDate;
					}					
				}	
			}		
		}

		return $shows;
	}

	private function makeFullCalendarEvent($show, $date, $options=array()) {

		$start = $date."T".$show["start_time"];
		$end = $date."T".$show["end_time"];

		$event = array(
			"id" => $show["show_id"],
			"title" => $show["name"],
			"start" => $start,
			"end" => $end,
			"allDay" => false,
			"description" => $show["description"]
		);

		foreach($options as $key=>$value) {
			$event[$key] = $value;
		}

		if($this->_user->isAdmin() === "A") {
			$event["editable"] = true;
		}

		if($this->_user->isHost($show["show_id"])) {
			$event["isHost"] = true;
		}

		return $event;
	}
}
