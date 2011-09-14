<?php

class SystemstatusController extends Zend_Controller_Action
{
    public function init()
    {

    }

    public function indexAction()
    {
        $status = array(
            "icecast2"=>Application_Model_Systemstatus::GetIcecastStatus(),
            "pypo"=>Application_Model_Systemstatus::GetPypoStatus(),
            "liquidsoap"=>Application_Model_Systemstatus::GetLiquidsoapStatus(),
            "show-recorder"=>Application_Model_Systemstatus::GetShowRecorderStatus(),
            "media-monitor"=>Application_Model_Systemstatus::GetMediaMonitorStatus()
        );
        
        $this->view->status = $status;
    }

    public function getLogFileAction()
    {
        $log_files = array("pypo"=>"/var/log/airtime/pypo/pypo.log",
                "liquidsoap"=>"/var/log/airtime/pypo-liquidsoap/ls_script.log",
                "media-monitor"=>"/var/log/airtime/media-monitor/media-monitor.log",
                "show-recorder"=>"/var/log/airtime/show-recorder/show-recorder.log",
                "icecast2"=>"/var/log/icecast2/error.log");

        $id = $this->_getParam('id');
        Logging::log($id);

        if (array_key_exists($id, $log_files)){
            $filepath = $log_files[$id];
            $filename = basename($filepath);
            header("Content-Disposition: attachment; filename=$filename");
            header("Content-Length: " . filesize($filepath));
            // !! binary mode !!
            $fp = fopen($filepath, 'rb');

            //We can have multiple levels of output buffering. Need to
            //keep looping until all have been disabled!!!
            //http://www.php.net/manual/en/function.ob-end-flush.php
            while (@ob_end_flush());

            fpassthru($fp);
            fclose($fp);

            //make sure to exit here so that no other output is sent.
            exit;
        }
    }
}
