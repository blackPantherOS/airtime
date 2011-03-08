<?php

class Application_Model_Nowplaying
{

    public static function FindBeginningOfShow($rows){
        $numRows = count($rows);

        $newCopy = array();

        for ($i=0; $i<$numRows; $i++){
            $currentRow = $rows[$i];
            if ($i == 0 || ($i != 0 && $currentRow['instance_id'] != $rows[$i-1]['instance_id'])){
                //$currentRow is first instance of a show.
                $group = $currentRow;
                $group['group'] = 'x';
                $group['item_starts'] = $group['show_starts'];
                $group['item_ends'] = $group['show_ends'];

                array_push($newCopy, $group);
            }
            array_push($newCopy, $currentRow);
        }

        return $newCopy;
    }

    public static function FindGapAtEndOfShow($rows){
        $numRows = count($rows);

        $newCopy = array();

        for ($i=0; $i<$numRows; $i++){
            $currentRow = $rows[$i];
            array_push($newCopy, $currentRow);
            if ($i+1 == $numRows || ($i+1 !=$numRows && $currentRow['instance_id'] != $rows[$i+1]['instance_id'])){
                //$row is the last instance in the show.

                if ($currentRow['item_ends'] == ""){
                    //show is empty and has no scheduled items in it. Therefore
                    //the gap is the entire length of the show.
                    $currentRow['item_ends'] = $currentRow['show_starts'];
                }
                
                $diff = strtotime($currentRow['show_ends']) - strtotime($currentRow['item_ends']);
                if ($diff > 0){
                    //gap at the end of show. Lets create a "gap" row
                    $gap = $currentRow;
                    $gap['gap'] = '';
                    $gap['item_starts'] = $diff;

                    array_push($newCopy, $gap);
                }
            }
        }
        return $newCopy;
    }

    public static function FilterRowsByDate($rows, $date, $startCutoff, $endCutoff){
        $dateNow = new Application_Model_DateHelper;
        $timeNow = $dateNow->getDate();

        $data = array();
        //iterate over each show, and calculate information for it.
        $numItems = count($rows);
        for ($i=0; $i<$numItems; $i++){
            $item = $rows[$i];

            if ((strtotime($item['item_ends']) > $date->getEpochTime() - $startCutoff
                && strtotime($item['item_starts']) < $date->getEpochTime() + $endCutoff) || array_key_exists("group", $item) || array_key_exists("gap", $item)){

                if (array_key_exists("group", $item)){
                    $type = "g";
                } else if (array_key_exists("gap", $item)){
                    $type = "b";
                } else if (strtotime($item['item_ends']) < strtotime($timeNow)){
                    $type = "p";
                } else if (strtotime($item['item_starts']) < strtotime($timeNow) && strtotime($timeNow) < strtotime($item['item_ends'])
                    && strtotime($item['show_starts']) < strtotime($timeNow) && strtotime($timeNow) < strtotime($item['show_ends'])){
                    $type = "c";
                } else {
                    $type = "n";
                }

                if (strtotime($item['item_ends']) > strtotime($item['show_ends']))
                    $type = "over";

                array_push($data, array($type, $item["item_starts"], $item["item_starts"], $item["item_ends"], $item["clip_length"], $item["track_title"], $item["artist_name"], $item["album_title"], $item["playlist_name"], $item["show_name"], $item["instance_id"]));
            }
        }

        return $data;
    }

    public static function GetDataGridData($viewType, $dateString){

        if ($viewType == "now"){
            $date = new Application_Model_DateHelper;
            $timeNow = $date->getDate();

            $startCutoff = 60;
            $endCutoff = 86400; //60*60*24 - seconds in a day
        } else {
            $date = new Application_Model_DateHelper;
            $time = $date->getTime();
            $date->setDate($dateString." ".$time);
            $timeNow = $date->getDate();

            $startCutoff = $date->getNowDayStartDiff();
            $endCutoff = $date->getNowDayEndDiff();
        }


        $rows = Show_DAL::GetShowsInRange($timeNow, $startCutoff, $endCutoff);
        $rows = Application_Model_Nowplaying::FindBeginningOfShow($rows);
        $rows = Application_Model_Nowplaying::FindGapAtEndOfShow($rows);
        //$rows = FindGapsBetweenShows
        $data = Application_Model_Nowplaying::FilterRowsByDate($rows, $date, $startCutoff, $endCutoff);


        $date = new Application_Model_DateHelper;
        $timeNow = $date->getDate();
        return array("currentShow"=>Show_DAL::GetCurrentShow($timeNow), "rows"=>$data);
    }
}
