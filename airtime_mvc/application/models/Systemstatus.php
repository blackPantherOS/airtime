<?php

class Application_Model_Systemstatus
{

    public static function GetMonitStatus($p_ip){

        $url = "http://$p_ip:2812/_status?format=xml";
        
        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
        curl_setopt($ch, CURLOPT_URL, $url);  
        curl_setopt($ch, CURLOPT_USERPWD, "admin:monit");
        $result = curl_exec($ch);
        curl_close($ch);

        $docRoot = null;
        if ($result != ""){
            $xmlDoc = new DOMDocument();
            $xmlDoc->loadXML($result);
            $docRoot = $xmlDoc->documentElement;
        }

        return $docRoot;
    }
    
    public static function ExtractServiceInformation($p_docRoot, $p_serviceName){

        $starting = array(
                        "name"=>"",
                        "process_id"=>"STARTING...",
                        "uptime_seconds"=>"-1",
                        "status"=>true,
                        "memory_perc"=>"0%",
                        "memory_kb"=>"0",
                        "cpu_perc"=>"0%");
                      
        $notRunning = array(
                        "name"=>$p_serviceName,
                        "process_id"=>"FAILED",
                        "uptime_seconds"=>"-1",
                        "status"=>false,
                        "memory_perc"=>"0%",
                        "memory_kb"=>"0",
                        "cpu_perc"=>"0%"
                      );
        $data = $notRunning;

        
        if (!is_null($p_docRoot)){
            foreach ($p_docRoot->getElementsByTagName("service") AS $item)
            {
                if ($item->getElementsByTagName("name")->item(0)->nodeValue == $p_serviceName){

                    $monitor = $item->getElementsByTagName("monitor");
                    if ($monitor->length > 0){
                        $status = $monitor->item(0)->nodeValue;
                        if ($status == "2"){
                            $data = $starting;
                        } else if ($status == 0){
                            $data = $notRunning;
                        }
                    }

                    $process_id = $item->getElementsByTagName("name");
                    if ($process_id->length > 0){
                        $data["name"] = $process_id->item(0)->nodeValue;
                    }
                    
                    $process_id = $item->getElementsByTagName("pid");
                    if ($process_id->length > 0){
                        $data["process_id"] = $process_id->item(0)->nodeValue;
                        $data["status"] = true;
                    }

                    $uptime = $item->getElementsByTagName("uptime");
                    if ($uptime->length > 0){
                        $data["uptime_seconds"] = $uptime->item(0)->nodeValue;
                    }
                    
                    $memory = $item->getElementsByTagName("memory");
                    if ($memory->length > 0){
                        $data["memory_perc"] = $memory->item(0)->getElementsByTagName("percenttotal")->item(0)->nodeValue."%";
                        $data["memory_kb"] = $memory->item(0)->getElementsByTagName("kilobytetotal")->item(0)->nodeValue;
                    }
                    
                    $cpu = $item->getElementsByTagName("cpu");
                    if ($cpu->length > 0){
                        $data["cpu_perc"] = $cpu->item(0)->getElementsByTagName("percent")->item(0)->nodeValue."%";
                    }
                    break;
                }
            }
        }
        return $data;
    }

    public static function GetPlatformInfo(){
        $data = array("release"=>"UNKNOWN",
                      "machine"=>"UNKNOWN",
                      "memory"=>"UNKNOWN",
                      "swap"=>"UNKNOWN");

        $docRoot = self::GetMonitStatus("localhost");
        if (!is_null($docRoot)){
            foreach ($docRoot->getElementsByTagName("platform") AS $item)
            {
                $data["release"] = $item->getElementsByTagName("release")->item(0)->nodeValue;
                $data["machine"] = $item->getElementsByTagName("machine")->item(0)->nodeValue;
                $data["memory"] = $item->getElementsByTagName("memory")->item(0)->nodeValue;
                $data["swap"] = $item->getElementsByTagName("swap")->item(0)->nodeValue;         
            }
        }
        
        return $data;

    }

    public static function GetPypoStatus(){

        $component = CcServiceRegisterQuery::create()->findOneByDbName("pypo");
        $ip = $component->getDbIp();
        
        $docRoot = self::GetMonitStatus($ip);
        $data = self::ExtractServiceInformation($docRoot, "airtime-playout");

        return $data;
    }
    
    public static function GetLiquidsoapStatus(){

        $component = CcServiceRegisterQuery::create()->findOneByDbName("pypo");
        $ip = $component->getDbIp();
        
        $docRoot = self::GetMonitStatus($ip);
        $data = self::ExtractServiceInformation($docRoot, "airtime-liquidsoap");

        return $data;
    }
    
    public static function GetShowRecorderStatus(){

        $component = CcServiceRegisterQuery::create()->findOneByDbName("show-recorder");
        $ip = $component->getDbIp();
        
        $docRoot = self::GetMonitStatus($ip);
        $data = self::ExtractServiceInformation($docRoot, "airtime-show-recorder");

        return $data;
    }
    
    public static function GetMediaMonitorStatus(){

        $component = CcServiceRegisterQuery::create()->findOneByDbName("media-monitor");
        $ip = $component->getDbIp();
        
        $docRoot = self::GetMonitStatus($ip);
        $data = self::ExtractServiceInformation($docRoot, "airtime-media-monitor");

        return $data;
    }
    
    public static function GetIcecastStatus(){     
        $docRoot = self::GetMonitStatus("localhost");
        $data = self::ExtractServiceInformation($docRoot, "icecast2");

        return $data;
    }

    public static function GetRabbitMqStatus(){     
        $docRoot = self::GetMonitStatus("localhost");
        $data = self::ExtractServiceInformation($docRoot, "rabbitmq-server");

        return $data;
    }
    
    public static function GetDiskInfo(){
        /* First lets get all the watched directories. Then we can group them
         * into the same paritions by comparing the partition sizes. */
        $musicDirs = Application_Model_MusicDir::getWatchedDirs();
        $musicDirs[] = Application_Model_MusicDir::getStorDir();


        $partions = array();

        foreach($musicDirs as $md){
            $totalSpace = disk_total_space($md->getDirectory());

            if (!isset($partitions[$totalSpace])){
                $partitions[$totalSpace] = new StdClass;
                $partitions[$totalSpace]->totalSpace = $totalSpace;
                $partitions[$totalSpace]->totalFreeSpace = disk_free_space($md->getDirectory());
            }
            
            $partitions[$totalSpace]->dirs[] = $md->getDirectory();
        }

        return array_values($partitions);
    }
}
