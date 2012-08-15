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
            "rating" => "Rating",
            "sample_rate" => "Sample Rate",
            "track_title" => "Title",
            "track_number" => "Track Number",
            "utime" => "Uploaded",
            "year" => "Year"
        );
        
        $criteriaTypes = array(
            0 => "",
            "album_title" => "s",
            "artist_name" => "s",
            "bit_rate" => "n",
            "bpm" => "s",
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
            "rating" => "n",
            "sample_rate" => "n",
            "track_title" => "s",
            "track_number" => "n",
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
        
        // load type
        $out = CcBlockQuery::create()->findPk($p_blockId);
        if ($out->getDbType() == "static") {
            $blockType = 0;
        } else {
            $blockType = 1;
        }
        
        
        $spType = new Zend_Form_Element_Radio('sp_type');
        $spType->setLabel('Set smart playlist type:')
               ->setDecorators(array('viewHelper'))
               ->setMultiOptions(array(
                    'static' => 'Static',
                    'dynamic' => 'Dynamic'
                ))
               ->setValue($blockType);
        $this->addElement($spType);
        
        $bl = new Application_Model_Block($p_blockId);
        $storedCrit = $bl->getCriteria();

        /* $modRoadMap stores the number of same criteria
         * Ex: 3 Album titles, and 2 Track titles
         * We need to know this so we display the form elements properly
         */
        $modRowMap = array();
        
        $openSmartBlockOption = false;
        if (!empty($storedCrit)) {
            $openSmartBlockOption = true;
        }
        
        $criteriaKeys = array();
        if (isset($storedCrit["crit"])) {
            $criteriaKeys = array_keys($storedCrit["crit"]);
        }
        $numElements = count($criteriaOptions);
        for ($i = 0; $i < $numElements; $i++) {
            $criteriaType = "";
            
            if (isset($criteriaKeys[$i])) {
                $critCount = count($storedCrit["crit"][$criteriaKeys[$i]]);
            } else {
                $critCount = 1;
            }
            
            $modRowMap[$i] = $critCount;
            
            /* Loop through all criteria with the same field
             * Ex: all criteria for 'Album'
             */
            for ($j = 0; $j < $critCount; $j++) {
                /****************** CRITERIA ***********/
                if ($j > 0) {
                    $invisible = ' sp-invisible';
                } else {
                    $invisible = '';
                }
                
                $criteria = new Zend_Form_Element_Select("sp_criteria_field_".$i."_".$j);
                $criteria->setAttrib('class', 'input_select sp_input_select'.$invisible)
                         ->setValue('Select criteria')
                         ->setDecorators(array('viewHelper'))
                         ->setMultiOptions($criteriaOptions);
                if ($i != 0 && !isset($criteriaKeys[$i])) {
                    $criteria->setAttrib('disabled', 'disabled');
                }
                
                if (isset($criteriaKeys[$i])) {
                    $criteriaType = $criteriaTypes[$storedCrit["crit"][$criteriaKeys[$i]][$j]["criteria"]];
                    $criteria->setValue($storedCrit["crit"][$criteriaKeys[$i]][$j]["criteria"]);
                }
                $this->addElement($criteria);
                
                /****************** MODIFIER ***********/
                $criteriaModifers = new Zend_Form_Element_Select("sp_criteria_modifier_".$i."_".$j);
                $criteriaModifers->setValue('Select modifier')
                                 ->setAttrib('class', 'input_select sp_input_select')
                                 ->setDecorators(array('viewHelper'));
                if ($i != 0 && !isset($criteriaKeys[$i])) {
                    $criteriaModifers->setAttrib('disabled', 'disabled');
                }
                if (isset($criteriaKeys[$i])) {
                    if($criteriaType == "s"){
                        $criteriaModifers->setMultiOptions($stringCriteriaOptions);
                    }else{
                        $criteriaModifers->setMultiOptions($numericCriteriaOptions);
                    }
                    $criteriaModifers->setValue($storedCrit["crit"][$criteriaKeys[$i]][$j]["modifier"]);
                }else{
                    $criteriaModifers->setMultiOptions(array('0' => 'Select modifier'));
                }
                $this->addElement($criteriaModifers);
                
                /****************** VALUE ***********/
                $criteriaValue = new Zend_Form_Element_Text("sp_criteria_value_".$i."_".$j);
                $criteriaValue->setAttrib('class', 'input_text sp_input_text')
                              ->setDecorators(array('viewHelper'));
                if ($i != 0 && !isset($criteriaKeys[$i])){
                    $criteriaValue->setAttrib('disabled', 'disabled');
                }
                if (isset($criteriaKeys[$i])) {
                    $criteriaValue->setValue($storedCrit["crit"][$criteriaKeys[$i]][$j]["value"]);
                }
                $this->addElement($criteriaValue);
                
                /****************** EXTRA ***********/
                $criteriaExtra = new Zend_Form_Element_Text("sp_criteria_extra_".$i."_".$j);
                $criteriaExtra->setAttrib('class', 'input_text sp_extra_input_text')
                              ->setDecorators(array('viewHelper'));
                if (isset($criteriaKeys[$i]) && isset($storedCrit["crit"][$criteriaKeys[$i]][$j]["extra"])) {
                    $criteriaExtra->setValue($storedCrit["crit"][$criteriaKeys[$i]][$j]["extra"]);
                    $criteriaValue->setAttrib('class', 'input_text sp_extra_input_text');
                }else{
                    $criteriaExtra->setAttrib('disabled', 'disabled');
                }
                $this->addElement($criteriaExtra);
                    
            }//for
            
        }//for
        
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
        $save->setAttrib('class', 'ui-button ui-state-default sp-save');
        $save->setAttrib('title', 'Save playlist');
        $save->setIgnore(true);
        $save->setLabel('Save');
        $save->setDecorators(array('viewHelper'));
        $this->addElement($save);
        
        $generate = new Zend_Form_Element_Button('generate_button');
        $generate->setAttrib('class', 'ui-button ui-state-default sp-button');
        $generate->setAttrib('title', 'Generate playlist content');
        $generate->setIgnore(true);
        $generate->setLabel('Generate');
        $generate->setDecorators(array('viewHelper'));
        $this->addElement($generate);
        
        $shuffle = new Zend_Form_Element_Button('shuffle_button');
        $shuffle->setAttrib('class', 'ui-button ui-state-default sp-button');
        $shuffle->setAttrib('title', 'Shuffle playlist content');
        $shuffle->setIgnore(true);
        $shuffle->setLabel('Shuffle');
        $shuffle->setDecorators(array('viewHelper'));
        $this->addElement($shuffle);

        $this->setDecorators(array(
                array('ViewScript', array('viewScript' => 'form/smart-block-criteria.phtml', "openOption"=> $openSmartBlockOption,
                        'criteriasLength' => count($criteriaOptions), 'poolCount' => $files['count'], 'modRowMap' => $modRowMap))
        ));
    }
    
    function isValid($params){
        Logging::log("isvalid called");
        $isValid = true;
        // reconstruct the params['criteria'] so we can populate the form
        $formData = array();
        foreach ($params['data'] as $ele) {
            $formData[$ele['name']] = $ele['value'];
        }
        $this->populate($formData);
        $criteria2PeerMap = array(
                0 => "Select criteria",
                "album_title" => "DbAlbumTitle",
                "artist_name" => "DbArtistName",
                "bit_rate" => "DbBitRate",
                "bpm" => "DbBpm",
                "comments" => "DbComments",
                "composer" => "DbComposer",
                "conductor" => "DbConductor",
                "utime" => "DbUtime",
                "mtime" => "DbMtime",
                "lptime" => "DbLPtime",
                "disc_number" => "DbDiscNumber",
                "genre" => "DbGenre",
                "isrc_number" => "DbIsrcNumber",
                "label" => "DbLabel",
                "language" => "DbLanguage",
                "length" => "DbLength",
                "lyricist" => "DbLyricist",
                "mood" => "DbMood",
                "name" => "DbName",
                "orchestra" => "DbOrchestra",
                "rating" => "DbRating",
                "sample_rate" => "DbSampleRate",
                "track_title" => "DbTrackTitle",
                "track_number" => "DbTrackNumber",
                "year" => "DbYear"
        );
        $data = Application_Model_Block::organizeSmartPlyalistCriteria($params['data']);
        // things we need to check
        // 1. limit value shouldn't be empty and has upperbound of 24 hrs
        // 2. sp_criteria or sp_criteria_modifier shouldn't be 0
        // 3. validate formate according to DB column type
        $multiplier = 1;
        $result = 0;
        $errors = array();
        $error = array();
        
        // validation start
        if ($data['etc']['sp_limit_options'] == 'hours') {
            $multiplier = 60;
        }
        if ($data['etc']['sp_limit_options'] == 'hours' || $data['etc']['sp_limit_options'] == 'mins') {
            $element = $this->getElement("sp_limit_value");
            if ($data['etc']['sp_limit_value'] == "" || floatval($data['etc']['sp_limit_value']) <= 0) {
                $element->addError("Limit cannot be empty or smaller than 0");
                $isValid = false;
            } else {
                $mins = floatval($data['etc']['sp_limit_value']) * $multiplier;
                if ($mins > 1440) {
                    $element->addError("Limit cannot be more than 24 hrs");
                    $isValid = false;
                }
            }
        } else {
            $element = $this->getElement("sp_limit_value");
            if ($data['etc']['sp_limit_value'] == "" || floatval($data['etc']['sp_limit_value']) <= 0) {
                $element->addError("Limit cannot be empty or smaller than 0");
                $isValid = false;
            } else if (!ctype_digit($data['etc']['sp_limit_value'])) {
                $element->addError("The value should be an integer");
                $isValid = false;
            } else if (intval($data['etc']['sp_limit_value']) > 500) {
                $element->addError("500 is the max item limit value you can set");
                $isValid = false;
            }
        }
        
        $criteriaFieldsUsed = array();
        
        if (isset($data['criteria'])) {
            foreach ($data['criteria'] as $rowKey=>$row) {
                foreach ($row as $key=>$d){
                    $element = $this->getElement("sp_criteria_field_".$rowKey."_".$key);
                    $error = array();
                    // check for not selected select box
                    if ($d['sp_criteria_field'] == "0" || $d['sp_criteria_modifier'] == "0"){
                        $element->addError("You must select Criteria and Modifier");
                        $isValid = false;
                    } else {
                        $column = CcFilesPeer::getTableMap()->getColumnByPhpName($criteria2PeerMap[$d['sp_criteria_field']]);
                        // validation on type of column
                        if ($d['sp_criteria_field'] == 'length') {
                            if (!preg_match("/(\d{2}):(\d{2}):(\d{2})/", $d['sp_criteria_value'])) {
                                $element->addError("'Length' should be in '00:00:00' format");
                                $isValid = false;
                            }
                        } else if ($column->getType() == PropelColumnTypes::TIMESTAMP) {
                            if (!preg_match("/(\d{4})-(\d{2})-(\d{2})/", $d['sp_criteria_value'])) {
                                $element->addError("The value should be in timestamp format(eg. 0000-00-00 or 00-00-00 00:00:00");
                                $isValid = false;
                            } else {
                                $result = Application_Common_DateHelper::checkDateTimeRangeForSQL($d['sp_criteria_value']);
                                if (!$result["success"]) {
                                    // check for if it is in valid range( 1753-01-01 ~ 12/31/9999 )
                                    $element->addError($result["errMsg"]);
                                    $isValid = false;
                                }
                            }
        
                            if (isset($d['sp_criteria_extra'])) {
                                if (!preg_match("/(\d{4})-(\d{2})-(\d{2})/", $d['sp_criteria_extra'])) {
                                    $element->addError("The value should be in timestamp format(eg. 0000-00-00 or 00-00-00 00:00:00");
                                    $isValid = false;
                                } else {
                                    $result = Application_Common_DateHelper::checkDateTimeRangeForSQL($d['sp_criteria_extra']);
                                    if (!$result["success"]) {
                                        // check for if it is in valid range( 1753-01-01 ~ 12/31/9999 )
                                        $element->addError($result["errMsg"]);
                                        $isValid = false;
                                    }
                                }
                            }
                        } else if ($column->getType() == PropelColumnTypes::INTEGER) {
                            if (!is_numeric($d['sp_criteria_value'])) {
                                $element->addError("The value has to be numeric");
                                $isValid = false;
                            }
                            // length check
                            if (intval($d['sp_criteria_value']) >= pow(2,31)) {
                                $element->addError("The value should be less then 2147483648");
                                $isValid = false;
                            }
                        } else if ($column->getType() == PropelColumnTypes::VARCHAR) {
                            if (strlen($d['sp_criteria_value']) > $column->getSize()) {
                                $element->addError("The value should be less ".$column->getSize()." characters");
                                $isValid = false;
                            }
                        }
                    }
        
                    if ($d['sp_criteria_value'] == "") {
                        $element->addError("Value cannot be empty");
                        $isValid = false;
                    }
                }//end foreach
            }//for loop
        }//if
        return $isValid;
    }
}
