<?php
class Application_Form_SmartBlockCriteria extends Zend_Form_SubForm
{
    
    public function init(){
        
    }
    
    public function startForm($p_blockId)
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
        
        $modRows = array();
        
        // load type
        $out = CcBlockQuery::create()->findPk($p_blockId);
        if ($out->getDbType() == "static") {
            $blockType = 0;
        } else {
            $blockType = 1;
        }
        
        
        $spType = new Zend_Form_Element_Radio('sp_type');
        $spType->setLabel('Set smart block type:')
               ->setDecorators(array('viewHelper'))
               ->setMultiOptions(array(
                    'static' => 'Static',
                    'dynamic' => 'Dynamic'
                ))
               ->setValue($blockType);
        $this->addElement($spType);
       
        // load criteria from db
        $out = CcBlockcriteriaQuery::create()->orderByDbCriteria()->findByDbBlockId($p_blockId);
        $storedCrit = array();
        
        /* Store the previous criteria value
         * We will use this to check if the current row has the same
         * critieria value. If so, we know that this is a modifier row
         
        $tempCrit = '';
        $modrows = array();
        $critRowNum = 0;
        $modRowNum = 0;
        $j = 0;
        */
        foreach ($out as $crit) {
            //$tempCrit = $crit->getDbCriteria();
            $criteria = $crit->getDbCriteria();
            $modifier = $crit->getDbModifier();
            $value = $crit->getDbValue();
            $extra = $crit->getDbExtra();
        
            if($criteria == "limit"){
                $storedCrit["limit"] = array("value"=>$value, "modifier"=>$modifier);
            }else{
                $storedCrit["crit"][] = array("criteria"=>$criteria, "value"=>$value, "modifier"=>$modifier, "extra"=>$extra);
            }
            /*
            //check if row is a modifier row
            if ($critRowNum > 0 && strcmp($tempCrit, $storedCrit["crit"][$critRowNum-1]["criteria"])==0) {
                $modrows[$j][$] = $modRowNum;
                $modRowNum++;
            } else if ($critRowNum > 0) {
                $modRowNum = 0;
                $j++;
            }
            $critRowNum++;
            */
        }
        //Logging::log($modrows);
        
        $openSmartBlockOption = false;
        if (!empty($storedCrit)) {
            $openSmartBlockOption = true;
        }
        
        $numElements = count($criteriaOptions);
        for ($i = 0; $i < $numElements; $i++) {
            $criteriaType = "";
            
            $criteria = new Zend_Form_Element_Select("sp_criteria_field_".$i);
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
            
            $criteriaModifers = new Zend_Form_Element_Select("sp_criteria_modifier_".$i);
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
        
            $criteriaValue = new Zend_Form_Element_Text("sp_criteria_value_".$i);
            $criteriaValue->setAttrib('class', 'input_text sp_input_text')
                          ->setDecorators(array('viewHelper'));
            if ($i != 0 && !isset($storedCrit["crit"][$i])){
                $criteriaValue->setAttrib('disabled', 'disabled');
            }
            if (isset($storedCrit["crit"][$i])) {
                $criteriaValue->setValue($storedCrit["crit"][$i]["value"]);
            }
            $this->addElement($criteriaValue);
            
            $criteriaExtra = new Zend_Form_Element_Text("sp_criteria_extra_".$i);
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
        
        $limit = new Zend_Form_Element_Select('sp_limit_options');
        $limit->setAttrib('class', 'sp_input_select')
              ->setDecorators(array('viewHelper'))
              ->setMultiOptions($limitOptions);
        if (isset($storedCrit["limit"])) {
            $limit->setValue($storedCrit["limit"]["modifier"]);
        }
        $this->addElement($limit);
        
        $limitValue = new Zend_Form_Element_Text('sp_limit_value');
        $limitValue->setAttrib('class', 'sp_input_text_limit')
                   ->setLabel('Limit to')
                   ->setDecorators(array('viewHelper'));
        $this->addElement($limitValue);
        if (isset($storedCrit["limit"])) {
            $limitValue->setValue($storedCrit["limit"]["value"]);
        }
        
        //getting block content candidate count that meets criteria
        $bl = new Application_Model_Block($p_blockId);
        $files = $bl->getListofFilesMeetCriteria();
        
        $save = new Zend_Form_Element_Button('save_button');
        $save->setAttrib('class', 'ui-button ui-state-default sp-button');
        $save->setAttrib('title', 'Save criteria only');
        $save->setIgnore(true);
        $save->setLabel('Save');
        $save->setDecorators(array('viewHelper'));
        $this->addElement($save);
        
        $generate = new Zend_Form_Element_Button('generate_button');
        $generate->setAttrib('class', 'ui-button ui-state-default sp-button');
        $generate->setAttrib('title', 'Save criteria and generate block content');
        $generate->setIgnore(true);
        $generate->setLabel('Generate');
        $generate->setDecorators(array('viewHelper'));
        $this->addElement($generate);
        
        $shuffle = new Zend_Form_Element_Button('shuffle_button');
        $shuffle->setAttrib('class', 'ui-button ui-state-default sp-button');
        $shuffle->setAttrib('title', 'Shuffle block content');
        $shuffle->setIgnore(true);
        $shuffle->setLabel('Shuffle');
        $shuffle->setDecorators(array('viewHelper'));
        $this->addElement($shuffle);

        $this->setDecorators(array(
                array('ViewScript', array('viewScript' => 'form/smart-block-criteria.phtml', "openOption"=> $openSmartBlockOption,
                        'criteriasLength' => count($criteriaOptions), 'poolCount' => $files['count'], 'modRows' => $modRows))
        ));
    }
    
}
