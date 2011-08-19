<?php
class Application_Form_StreamSettingSubForm extends Zend_Form_SubForm{
    private $prefix;
    private $setting;
    private $stream_types;
    private $stream_bitrates;
    
    public function init()
    {
        
    }
    
    public function setPrefix($prefix){
        $this->prefix = $prefix;
    }
    
    public function setSetting($setting){
        $this->setting = $setting;
    }
    
    public function setStreamTypes($stream_types){
        $this->stream_types = $stream_types;
    }
    
    public function setStreamBitrates($stream_bitrates){
        $this->stream_bitrates = $stream_bitrates;
    }
    
    public function startForm(){
        $prefix = "s".$this->prefix;
        $stream_number = $this->prefix;
        $setting = $this->setting;
        $stream_types = $this->stream_types;
        $stream_bitrates = $this->stream_bitrates;
        
        $this->setIsArray(true);
        $this->setElementsBelongTo($prefix."_data");
        
        $enable = new Zend_Form_Element_Checkbox('enable');
        $enable->setLabel('Enabled:')
                            ->setValue($setting['output_'.$prefix] != 'disabled'?1:0)
                            ->setDecorators(array('ViewHelper'));
        $this->addElement($enable);
        
        $type = new Zend_Form_Element_Select('type');
        $type->setLabel("Type:")
                ->setMultiOptions($stream_types)
                ->setValue($setting[$prefix.'_type'])
                ->setDecorators(array('ViewHelper'));
        $this->addElement($type);
        
        $bitrate = new Zend_Form_Element_Select('bitrate');
        $bitrate->setLabel("Bitrate:")
                ->setMultiOptions($stream_bitrates)
                ->setValue($setting[$prefix.'_bitrate'])
                ->setDecorators(array('ViewHelper'));
        $this->addElement($bitrate);
        
        $output = new Zend_Form_Element_Select('output');
        $output->setLabel("Output to:")
                ->setMultiOptions(array("icecast"=>"Icecast", "shoutcast"=>"Shoutcast"))
                ->setValue($setting['output_'.$prefix])
                ->setDecorators(array('ViewHelper'));
        $this->addElement($output);
        
        $host = new Zend_Form_Element_Text('host');
        $host->setLabel("Server")
                ->setValue($setting[$prefix.'_host'])
                ->setDecorators(array('ViewHelper'));
        $this->addElement($host);
        
        $port = new Zend_Form_Element_Text('port');
        $port->setLabel("Port")
                ->setValue($setting[$prefix.'_port'])
                ->setDecorators(array('ViewHelper'));
        $this->addElement($port);
        
        $pass = new Zend_Form_Element_Text('pass');
        $pass->setLabel("Password")
                ->setValue($setting[$prefix.'_pass'])
                ->setDecorators(array('ViewHelper'));
        $this->addElement($pass);
        
        $genre = new Zend_Form_Element_Text('genre');
        $genre->setLabel("Genre:")
                ->setValue($setting[$prefix.'_genre'])
                ->setDecorators(array('ViewHelper'));
        $this->addElement($genre);
        
        $url = new Zend_Form_Element_Text('url');
        $url->setLabel("URL")
                ->setValue($setting[$prefix.'_url'])
                ->setDecorators(array('ViewHelper'));
        $this->addElement($url);
        
        $description = new Zend_Form_Element_Text('description');
        $description->setLabel("Name/Description")
                ->setValue($setting[$prefix.'_description'])
                ->setDecorators(array('ViewHelper'));
        $this->addElement($description);
        
        $mount_info = explode('.',$setting[$prefix.'_mount']);
        $mount = new Zend_Form_Element_Text('mount');
        $mount->setLabel("Mount Point")
                ->setValue($mount_info[0])
                ->setDecorators(array('ViewHelper'));
        $this->addElement($mount);
        
        $this->setDecorators(array(
            array('ViewScript', array('viewScript' => 'form/stream-setting-form.phtml', "stream_number"=>$stream_number))
        ));
    }
    
    public function isValid ($data){
        $isValid = parent::isValid($data);
        if($data['enable'] == 1){
            if($data['host'] == ''){
                $element = $this->getElement("host");
                $element->addError("Server cannot be empty.");
                $isValid = false;
            }
            if($data['port'] == ''){
                $element = $this->getElement("port");
                $element->addError("Port cannot be empty.");
                $isValid = false;
            }
            if($data['pass'] == ''){
                $element = $this->getElement("pass");
                $element->addError("Password cannot be empty.");
                $isValid = false;
            }
            if($data['output'] == 'icecast'){
                if($data['mount'] == ''){
                    $element = $this->getElement("mount");
                    $element->addError("Mount cannot be empty with Icecast server.");
                    $isValid = false;
                }
            }
        }
        return $isValid;
    }
}