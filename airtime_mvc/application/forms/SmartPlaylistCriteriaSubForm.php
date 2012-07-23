<?php
class Application_Form_SmartPlaylistCriteriaSubForm extends Zend_Form_SubForm
{
    private $setNumber;
    
    public function init(){
        
    }
    
    public function setCriteriaSetNumber($p_num)
    {
        $this->setNumber = $p_num;
    }
    
    public function startForm($p_playlistId)
    {
        $criteriaOptions = array(
            0 => "Select criteria",
            "album_title" => "Album",
            "bit_rate" => "Bit Rate",
            "bpm" => "Bpm",
            "comments" => "Comments",
            "composer" => "Composer",
            "conductor" => "Conductor",
            "artist_name" => "Creator",
            "disc_number" => "Disc Number",
            "genre" => "Genre",
            "isrc_number" => "ISRC",
            "label" => "Label",
            "language" => "Language",
            "mtime" => "Last Modified",
            "lptime" => "Last Played",
            "length" => "Length",
            "lyricist" => "Lyricist",
            "mood" => "Mood",
            "name" => "Name",
            "orchestra" => "Orchestra",
            "radio_station_name" => "Radio Station Name",
            "rating" => "Rating",
            "sample_rate" => "Sample Rate",
            "track_title" => "Title",
            "track_num" => "Track Number",
            "utime" => "Uploaded",
            "year" => "Year"               
        );
        
        $criteriaTypes = array(
            0 => "",
            "album_title" => "s",
            "artist_name" => "s",
            "bit_rate" => "n",
            "bpm" => "n",
            "comments" => "s",
            "composer" => "s",
            "conductor" => "s",
            "utime" => "n",
            "mtime" => "n",
            "lptime" => "n",
            "disc_number" => "n",
            "genre" => "s",
            "isrc_number" => "s",
            "label" => "s",
            "language" => "s",
            "length" => "n",
            "lyricist" => "s",
            "mood" => "s",
            "name" => "s",
            "orchestra" => "s",
            "radio_station_name" => "s",
            "rating" => "n",
            "sample_rate" => "n",
            "track_title" => "s",
            "track_num" => "n",
            "year" => "n"
        );
        
        $stringCriteriaOptions = array(
            "0" => "Select modifier",
            "contains" => "contains",
            "does not contain" => "does not contain",
            "is" => "is",
            "is not" => "is not",
            "starts with" => "starts with",
            "ends with" => "ends with"
        );
        
        $numericCriteriaOptions = array(
            "0" => "Select modifier",
            "is" => "is",
            "is not" => "is not",
            "is greater than" => "is greater than",
            "is less than" => "is less than",
            "is in the range" => "is in the range"
        );
        
        $limitOptions = array(
            "hours" => "hours",
            "minutes" => "minutes",
            "items" => "items"
        );
        
        /*
        // load type
        $out = CcPlaylistQuery::create()->findPk($p_playlistId);
        if ($out->getDbType() == "static") {
            $playlistType = 0;
        } else {
            $playlistType = 1;
        }
        
        
        $spType = new Zend_Form_Element_Radio('sp_type');
        $spType->setLabel('Set smart playlist type:')
               ->setDecorators(array('viewHelper'))
               ->setMultiOptions(array(
                    'static' => 'Static',
                    'dynamic' => 'Dynamic'
                ))
               ->setValue($playlistType);
        $this->addElement($spType);
        */
        
        // load criteria from db
        $out = CcPlaylistcriteriaQuery::create()->findByDbPlaylistId($p_playlistId);
        
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
        
        $openSmartPlaylistOption = false;
        if (!empty($storedCrit)) {
            $openSmartPlaylistOption = true;
        }
        
        $numElements = count($criteriaOptions);
        for ($i = 0; $i < $numElements; $i++) {
            $criteriaType = "";
            
            $criteria = new Zend_Form_Element_Select("sp_criteria_field_".$this->setNumber."_".$i);
            $criteria->setAttrib('class', 'input_select sp_input_select')
                     ->setValue('Select criteria')
                     ->setDecorators(array('viewHelper'))
                     ->setMultiOptions($criteriaOptions);
            if ($i != 0 && !isset($storedCrit["crit"][$i])){
                $criteria->setAttrib('disabled', 'disabled');
            }
            if (isset($storedCrit["crit"][$i])) {
                $criteriaType = $criteriaTypes[$storedCrit["crit"][$i]["criteria"]];
                $criteria->setValue($storedCrit["crit"][$i]["criteria"]);
            }
            $this->addElement($criteria);
            
            $criteriaModifers = new Zend_Form_Element_Select("sp_criteria_modifier_".$this->setNumber."_".$i);
            $criteriaModifers->setValue('Select modifier')
                             ->setAttrib('class', 'input_select sp_input_select')
                             ->setDecorators(array('viewHelper'));
            if ($i != 0 && !isset($storedCrit["crit"][$i])){
                $criteriaModifers->setAttrib('disabled', 'disabled');
            }
            if (isset($storedCrit["crit"][$i])) {
                if($criteriaType == "s"){
                    $criteriaModifers->setMultiOptions($stringCriteriaOptions);
                }else{
                    $criteriaModifers->setMultiOptions($numericCriteriaOptions);
                }
                $criteriaModifers->setValue($storedCrit["crit"][$i]["modifier"]);
            }else{
                $criteriaModifers->setMultiOptions(array('0' => 'Select modifier'));
            }
            $this->addElement($criteriaModifers);
        
            $criteriaValue = new Zend_Form_Element_Text("sp_criteria_value_".$this->setNumber."_".$i);
            $criteriaValue->setAttrib('class', 'input_text sp_input_text')
                          ->setDecorators(array('viewHelper'));
            if ($i != 0 && !isset($storedCrit["crit"][$i])){
                $criteriaValue->setAttrib('disabled', 'disabled');
            }
            if (isset($storedCrit["crit"][$i])) {
                $criteriaValue->setValue($storedCrit["crit"][$i]["value"]);
            }
            $this->addElement($criteriaValue);
            
            $criteriaExtra = new Zend_Form_Element_Text("sp_criteria_extra_".$this->setNumber."_".$i);
            $criteriaExtra->setAttrib('class', 'input_text sp_extra_input_text')
                          ->setDecorators(array('viewHelper'));
            if (isset($storedCrit["crit"][$i]["extra"])) {
                $criteriaExtra->setValue($storedCrit["crit"][$i]["extra"]);
                $criteriaValue->setAttrib('class', 'input_text sp_extra_input_text');
            }else{
                $criteriaExtra->setAttrib('disabled', 'disabled');
            }
            $this->addElement($criteriaExtra);
        }
        
        $limit = new Zend_Form_Element_Select('sp_limit_options_'.$this->setNumber);
        $limit->setAttrib('class', 'sp_input_select')
              ->setDecorators(array('viewHelper'))
              ->setMultiOptions($limitOptions);
        if (isset($storedCrit["limit"])) {
            $limit->setValue($storedCrit["limit"]["modifier"]);
        }
        $this->addElement($limit);
        
        $limitValue = new Zend_Form_Element_Text('sp_limit_value_'.$this->setNumber);
        $limitValue->setAttrib('class', 'sp_input_text_limit')
                   ->setLabel('Limit to')
                   ->setDecorators(array('viewHelper'));
        $this->addElement($limitValue);
        if (isset($storedCrit["limit"])) {
            $limitValue->setValue($storedCrit["limit"]["value"]);
        }
        
        //getting playlist content candidate count that meets criteria
        $pl = new Application_Model_Playlist($p_playlistId);
        $files = $pl->getListofFilesMeetCriteria();
        
        Logging::log($this->getElements());
        Logging::log(count($this->getElements()));
        
        $this->setDecorators(array(
                array('ViewScript', array('viewScript' => 'form/smart-playlist-criteria.phtml', "openOption"=> $openSmartPlaylistOption,
                        'criteriasLength' => count($criteriaOptions), 'poolCount' => $files['count'], 'setNumber' => $this->setNumber))
        ));
    }
}