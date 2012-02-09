<?php

class ShowbuilderController extends Zend_Controller_Action
{

    public function init()
    {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('schedule-move', 'json')
                    ->addActionContext('schedule-add', 'json')
                    ->addActionContext('schedule-remove', 'json')
                    ->addActionContext('builder-feed', 'json')
                    ->initContext();
    }

    public function indexAction() {

        $this->_helper->layout->setLayout('builder');

        $this->view->headScript()->appendFile($this->view->baseUrl('/js/airtime/library/events/library_showbuilder.js'),'text/javascript');

        $this->_helper->actionStack('library', 'library');
        $this->_helper->actionStack('builder', 'showbuilder');
    }

    public function builderAction() {

        $request = $this->getRequest();
        $baseUrl = $request->getBaseUrl();

        $now = time();
        $from = $request->getParam("from", $now);
        $to = $request->getParam("to", $now+(24*60*60));

        $start = DateTime::createFromFormat("U", $from, new DateTimeZone("UTC"));
        $start->setTimezone(new DateTimeZone(date_default_timezone_get()));
        $end = DateTime::createFromFormat("U", $to, new DateTimeZone("UTC"));
        $end->setTimezone(new DateTimeZone(date_default_timezone_get()));

        $this->view->start_date = $start->format("Y-m-d");
        $this->view->start_time = $start->format("H:i");
        $this->view->end_date = $end->format("Y-m-d");
        $this->view->end_time = $end->format("H:i");

        $this->view->headScript()->appendScript("var serverTimezoneOffset = ".date("Z")."; //in seconds");
        $this->view->headScript()->appendFile($baseUrl.'/js/timepicker/jquery.ui.timepicker.js','text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'/js/airtime/showbuilder/builder.js','text/javascript');

        $this->view->headLink()->appendStylesheet($baseUrl.'/css/jquery.ui.timepicker.css');
        $this->view->headLink()->appendStylesheet($baseUrl.'/css/showbuilder.css');

        $this->_helper->viewRenderer->setResponseSegment('builder');
    }

    public function builderFeedAction() {

        $request = $this->getRequest();
        $current_time = time();

        $starts_epoch = $request->getParam("start", $current_time);
        //default ends is 24 hours after starts.
        $ends_epoch = $request->getParam("end", $current_time + (60*60*24));

        $startsDT = DateTime::createFromFormat("U", $starts_epoch, new DateTimeZone("UTC"));
        $endsDT = DateTime::createFromFormat("U", $ends_epoch, new DateTimeZone("UTC"));

        Logging::log("showbuilder starts {$startsDT->format("Y-m-d H:i:s")}");
        Logging::log("showbuilder ends {$endsDT->format("Y-m-d H:i:s")}");

        $showBuilder = new Application_Model_ShowBuilder($startsDT, $endsDT);

        $this->view->schedule = $showBuilder->GetItems();
    }

    public function scheduleAddAction() {

        $request = $this->getRequest();

        $mediaItems = $request->getParam("mediaIds", null);
        $scheduledIds = $request->getParam("schedIds", null);

        $json = array();

        try {
            $scheduler = new Application_Model_Scheduler();
            $scheduler->scheduleAfter($scheduledIds, $mediaItems);

            $json["message"]="success... maybe";
        }
        catch (Exception $e) {
            $json["message"]=$e->getMessage();
            Logging::log($e->getMessage());
        }

        $this->view->data = $json;
    }

    public function scheduleRemoveAction()
    {
        $request = $this->getRequest();

        $ids = $request->getParam("ids", null);

        $json = array();

        try {
            $scheduler = new Application_Model_Scheduler();
            $scheduler->removeItems($ids);

            $json["message"]="success... maybe";
        }
        catch (Exception $e) {
            $json["message"]=$e->getMessage();
            Logging::log($e->getMessage());
        }

        $this->view->data = $json;
    }

    public function scheduleMoveAction() {

        $request = $this->getRequest();

        $selectedItem = $request->getParam("selectedItem");
        $afterItem = $request->getParam("afterItem");

        $json = array();

        try {
            $scheduler = new Application_Model_Scheduler();
            $scheduler->moveItem($selectedItem, $afterItem);

            $json["message"]="success... maybe";
        }
        catch (Exception $e) {
            $json["message"]=$e->getMessage();
            Logging::log($e->getMessage());
        }

        $this->view->data = $json;
    }
}