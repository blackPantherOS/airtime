<?php

class ScheduleController extends Zend_Controller_Action
{

    protected $sched_sess = null;

    public function init()
    {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('event-feed', 'json')
                    ->addActionContext('event-feed-preload', 'json')
                    ->addActionContext('make-context-menu', 'json')
                    ->addActionContext('add-show-dialog', 'json')
                    ->addActionContext('add-show', 'json')
                    ->addActionContext('edit-show', 'json')
                    ->addActionContext('move-show', 'json')
                    ->addActionContext('resize-show', 'json')
                    ->addActionContext('delete-show-instance', 'json')
                    ->addActionContext('show-content-dialog', 'json')
                    ->addActionContext('clear-show', 'json')
                    ->addActionContext('get-current-playlist', 'json')
                    ->addActionContext('remove-group', 'json')
                    ->addActionContext('populate-show-form', 'json')
                    ->addActionContext('populate-repeating-show-instance-form', 'json')
                    ->addActionContext('delete-show', 'json')
                    ->addActionContext('cancel-current-show', 'json')
                    ->addActionContext('get-form', 'json')
                    ->addActionContext('upload-to-sound-cloud', 'json')
                    ->addActionContext('content-context-menu', 'json')
                    ->addActionContext('set-time-scale', 'json')
                    ->addActionContext('set-time-interval', 'json')
                    ->addActionContext('edit-repeating-show-instance', 'json')
                    ->addActionContext('dj-edit-show', 'json')
                    ->addActionContext('calculate-duration', 'json')
                    ->addActionContext('get-current-show', 'json')
                    ->addActionContext('update-future-is-scheduled', 'json')
                    ->initContext();

        $this->sched_sess = new Zend_Session_Namespace("schedule");
    }

    public function indexAction()
    {
        $CC_CONFIG = Config::getConfig();

        $baseUrl = Application_Common_OsPath::getBaseDir();

        $this->view->headScript()->appendFile($baseUrl.'js/contextmenu/jquery.contextMenu.js?'.$CC_CONFIG['airtime_version'],'text/javascript');

        //full-calendar-functions.js requires this variable, so that datePicker widget can be offset to server time instead of client time
        $this->view->headScript()->appendScript("var timezoneOffset = ".date("Z")."; //in seconds");
        $this->view->headScript()->appendFile($baseUrl.'js/airtime/schedule/full-calendar-functions.js?'.$CC_CONFIG['airtime_version'],'text/javascript');

        $this->view->headScript()->appendFile($baseUrl.'js/fullcalendar/fullcalendar.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'js/timepicker/jquery.ui.timepicker.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'js/colorpicker/js/colorpicker.js?'.$CC_CONFIG['airtime_version'],'text/javascript');

        $this->view->headScript()->appendFile($baseUrl.'js/airtime/schedule/add-show.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'js/airtime/schedule/schedule.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'js/blockui/jquery.blockUI.js?'.$CC_CONFIG['airtime_version'],'text/javascript');

        $this->view->headLink()->appendStylesheet($baseUrl.'css/jquery.ui.timepicker.css?'.$CC_CONFIG['airtime_version']);
        $this->view->headLink()->appendStylesheet($baseUrl.'css/fullcalendar.css?'.$CC_CONFIG['airtime_version']);
        $this->view->headLink()->appendStylesheet($baseUrl.'css/colorpicker/css/colorpicker.css?'.$CC_CONFIG['airtime_version']);
        $this->view->headLink()->appendStylesheet($baseUrl.'css/add-show.css?'.$CC_CONFIG['airtime_version']);
        $this->view->headLink()->appendStylesheet($baseUrl.'css/jquery.contextMenu.css?'.$CC_CONFIG['airtime_version']);

        //Start Show builder JS/CSS requirements
        $this->view->headScript()->appendFile($baseUrl.'js/contextmenu/jquery.contextMenu.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'js/datatables/js/jquery.dataTables.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'js/datatables/plugin/dataTables.pluginAPI.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'js/datatables/plugin/dataTables.fnSetFilteringDelay.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'js/datatables/plugin/dataTables.ColVis.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'js/datatables/plugin/dataTables.ColReorder.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'js/datatables/plugin/dataTables.FixedColumns.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'js/datatables/plugin/dataTables.TableTools.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'js/datatables/plugin/dataTables.columnFilter.js?'.$CC_CONFIG['airtime_version'], 'text/javascript');

        $this->view->headScript()->appendFile($baseUrl.'js/airtime/buttons/buttons.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'js/airtime/library/events/library_showbuilder.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'js/airtime/library/library.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'js/airtime/showbuilder/builder.js?'.$CC_CONFIG['airtime_version'],'text/javascript');

        $this->view->headLink()->appendStylesheet($baseUrl.'css/media_library.css?'.$CC_CONFIG['airtime_version']);
        $this->view->headLink()->appendStylesheet($baseUrl.'css/jquery.contextMenu.css?'.$CC_CONFIG['airtime_version']);
        $this->view->headLink()->appendStylesheet($baseUrl.'css/datatables/css/ColVis.css?'.$CC_CONFIG['airtime_version']);
        $this->view->headLink()->appendStylesheet($baseUrl.'css/datatables/css/ColReorder.css?'.$CC_CONFIG['airtime_version']);
        $this->view->headLink()->appendStylesheet($baseUrl.'css/TableTools.css?'.$CC_CONFIG['airtime_version']);
        $this->view->headLink()->appendStylesheet($baseUrl.'css/showbuilder.css?'.$CC_CONFIG['airtime_version']);
        //End Show builder JS/CSS requirements

        $this->createShowFormAction(true);

        $user = Application_Model_User::getCurrentUser();
        if ($user->isUserType(array(UTYPE_ADMIN, UTYPE_PROGRAM_MANAGER))) {
            $this->view->preloadShowForm = true;
        }

        $this->view->addNewShow = true;
        $this->view->headScript()->appendScript(
            "var calendarPref = {};\n".
            "calendarPref.weekStart = ".Application_Model_Preference::GetWeekStartDay().";\n".
            "calendarPref.timestamp = ".time().";\n".
            "calendarPref.timezoneOffset = ".date("Z").";\n".
            "calendarPref.timeScale = '".Application_Model_Preference::GetCalendarTimeScale()."';\n".
            "calendarPref.timeInterval = ".Application_Model_Preference::GetCalendarTimeInterval().";\n".
            "calendarPref.weekStartDay = ".Application_Model_Preference::GetWeekStartDay().";\n".
            "var calendarEvents = null;"
        );
    }

    public function eventFeedAction()
    {
        $service_user = new Application_Service_UserService();
        $currentUser = $service_user->getCurrentUser();

        $start = new DateTime($this->_getParam('start', null));
        $start->setTimezone(new DateTimeZone("UTC"));
        $end = new DateTime($this->_getParam('end', null));
        $end->setTimezone(new DateTimeZone("UTC"));

        $events = &Application_Model_Show::getFullCalendarEvents($start, $end,
            $currentUser->isAdminOrPM());

        $this->view->events = $events;
    }

    public function eventFeedPreloadAction()
    {
        $userInfo = Zend_Auth::getInstance()->getStorage()->read();
        $user = new Application_Model_User($userInfo->id);
        $editable = $user->isUserType(array(UTYPE_ADMIN, UTYPE_PROGRAM_MANAGER));

        $calendar_interval = Application_Model_Preference::GetCalendarTimeScale();
        Logging::info($calendar_interval);
        if ($calendar_interval == "agendaDay") {
            list($start, $end) = Application_Model_Show::getStartEndCurrentDayView();
        } else if ($calendar_interval == "agendaWeek") {
            list($start, $end) = Application_Model_Show::getStartEndCurrentWeekView();
        } else if ($calendar_interval == "month") {
            list($start, $end) = Application_Model_Show::getStartEndCurrentMonthView();
        } else {
            Logging::error("Invalid Calendar Interval '$calendar_interval'");
        }

        $events = &Application_Model_Show::getFullCalendarEvents($start, $end, $editable);
        $this->view->events = $events;
    }

    public function getCurrentShowAction()
    {
        $currentShow = Application_Model_Show::getCurrentShow();
        if (!empty($currentShow)) {
            $this->view->si_id = $currentShow[0]["instance_id"];
            $this->view->current_show = true;
        } else {
            $this->view->current_show = false;
        }
    }

    public function moveShowAction()
    {
        $deltaDay = $this->_getParam('day');
        $deltaMin = $this->_getParam('min');

        try {
            $service_calendar = new Application_Service_CalendarService(
                $this->_getParam('showInstanceId'));
        } catch (Exception $e) {
            $this->view->show_error = true;
            return false;
        }

        $error = $service_calendar->moveShow($deltaDay, $deltaMin);
        if (isset($error)) {
            $this->view->error = $error;
        }
    }

    public function resizeShowAction()
    {
        $deltaDay = $this->_getParam('day');
        $deltaMin = $this->_getParam('min');
        $showId = $this->_getParam('showId');

        $userInfo = Zend_Auth::getInstance()->getStorage()->read();
        $user = new Application_Model_User($userInfo->id);

        if ($user->isUserType(array(UTYPE_ADMIN, UTYPE_PROGRAM_MANAGER))) {
            try {
                $show = new Application_Model_Show($showId);
            } catch (Exception $e) {
                $this->view->show_error = true;

                return false;
            }
            $error = $show->resizeShow($deltaDay, $deltaMin);
        }

        if (isset($error)) {
            $this->view->error = $error;
        }
    }

    public function deleteShowInstanceAction()
    {
        $instanceId = $this->_getParam('id');

        $service_show = new Application_Service_ShowService();
        $showId = $service_show->deleteShow($instanceId, true);

        if (!$showId) {
            $this->view->show_error = true;
        }
        $this->view->show_id = $showId;
    }

    public function uploadToSoundCloudAction()
    {
        $show_instance = $this->_getParam('id');
        try {
            $show_inst = new Application_Model_ShowInstance($show_instance);
        } catch (Exception $e) {
            $this->view->show_error = true;

            return false;
        }

        $file = $show_inst->getRecordedFile();
        $id = $file->getId();
        Application_Model_Soundcloud::uploadSoundcloud($id);
        // we should die with ui info
        $this->_helper->json->sendJson(null);
    }

    public function makeContextMenuAction()
    {
        $instanceId = $this->_getParam('instanceId');

        $service_calendar = new Application_Service_CalendarService($instanceId);

        $this->view->items = $service_calendar->makeContextMenu();
    }

    public function clearShowAction()
    {
        $showInstanceId = $this->_getParam('id');
        $userInfo = Zend_Auth::getInstance()->getStorage()->read();
        $user = new Application_Model_User($userInfo->id);
        try {
            $show = new Application_Model_ShowInstance($showInstanceId);
        } catch (Exception $e) {
            $this->view->show_error = true;

            return false;
        }

        if($user->isUserType(array(UTYPE_ADMIN, UTYPE_PROGRAM_MANAGER)) || $user->isHostOfShow($show->getShowId()))
            $show->clearShow();
    }

    public function getCurrentPlaylistAction()
    {
        $range = Application_Model_Schedule::GetPlayOrderRange();
        $show = Application_Model_Show::getCurrentShow();

        /* Convert all UTC times to localtime before sending back to user. */
        if (isset($range["previous"])) {
            $range["previous"]["starts"] = Application_Common_DateHelper::ConvertToLocalDateTimeString($range["previous"]["starts"]);
            $range["previous"]["ends"] = Application_Common_DateHelper::ConvertToLocalDateTimeString($range["previous"]["ends"]);
        }
        if (isset($range["current"])) {
            $range["current"]["starts"] = Application_Common_DateHelper::ConvertToLocalDateTimeString($range["current"]["starts"]);
            $range["current"]["ends"] = Application_Common_DateHelper::ConvertToLocalDateTimeString($range["current"]["ends"]);
        }
        if (isset($range["next"])) {
            $range["next"]["starts"] = Application_Common_DateHelper::ConvertToLocalDateTimeString($range["next"]["starts"]);
            $range["next"]["ends"] = Application_Common_DateHelper::ConvertToLocalDateTimeString($range["next"]["ends"]);
        }

        Application_Model_Show::convertToLocalTimeZone($range["currentShow"], array("starts", "ends", "start_timestamp", "end_timestamp"));
        Application_Model_Show::convertToLocalTimeZone($range["nextShow"], array("starts", "ends", "start_timestamp", "end_timestamp"));

        $source_status = array();
        $switch_status = array();
        $live_dj = Application_Model_Preference::GetSourceStatus("live_dj");
        $master_dj = Application_Model_Preference::GetSourceStatus("master_dj");

        $scheduled_play_switch = Application_Model_Preference::GetSourceSwitchStatus("scheduled_play");
        $live_dj_switch = Application_Model_Preference::GetSourceSwitchStatus("live_dj");
        $master_dj_switch = Application_Model_Preference::GetSourceSwitchStatus("master_dj");

        //might not be the correct place to implement this but for now let's just do it here
        $source_status['live_dj_source'] = $live_dj;
        $source_status['master_dj_source'] = $master_dj;
        $this->view->source_status = $source_status;

        $switch_status['live_dj_source'] = $live_dj_switch;
        $switch_status['master_dj_source'] = $master_dj_switch;
        $switch_status['scheduled_play'] = $scheduled_play_switch;
        $this->view->switch_status = $switch_status;

        $this->view->entries = $range;
        $this->view->show_name = isset($show[0])?$show[0]["name"]:"";
    }

    public function showContentDialogAction()
    {
        $showInstanceId = $this->_getParam('id');
        try {
            $show = new Application_Model_ShowInstance($showInstanceId);
        } catch (Exception $e) {
            $this->view->show_error = true;

            return false;
        }

        $originalShowId = $show->isRebroadcast();
        if (!is_null($originalShowId)) {
            try {
                $originalShow = new Application_Model_ShowInstance($originalShowId);
            } catch (Exception $e) {
                $this->view->show_error = true;

                return false;
            }
            $originalShowName = $originalShow->getName();
            $originalShowStart = $originalShow->getShowInstanceStart();

            //convert from UTC to user's timezone for display.
            $originalDateTime = new DateTime($originalShowStart, new DateTimeZone("UTC"));
            $originalDateTime->setTimezone(new DateTimeZone(date_default_timezone_get()));
            //$timestamp  = Application_Common_DateHelper::ConvertToLocalDateTimeString($originalDateTime->format("Y-m-d H:i:s"));
            $this->view->additionalShowInfo =
                sprintf(_("Rebroadcast of show %s from %s at %s"),
                    $originalShowName,
                    $originalDateTime->format("l, F jS"),
                    $originalDateTime->format("G:i"));
        }
        $this->view->showLength = $show->getShowLength();
        $this->view->timeFilled = $show->getTimeScheduled();
        $this->view->percentFilled = $show->getPercentScheduled();
        $this->view->showContent = $show->getShowListContent();
        $this->view->dialog = $this->view->render('schedule/show-content-dialog.phtml');
        $this->view->showTitle = htmlspecialchars($show->getName());
        unset($this->view->showContent);
    }

    public function populateRepeatingShowInstanceFormAction()
    {
        $showId = $this->_getParam('showId');
        $instanceId = $this->_getParam('instanceId');
        $service_showForm = new Application_Service_ShowFormService($showId, $instanceId);

        $forms = $this->createShowFormAction();

        $service_showForm->delegateShowInstanceFormPopulation($forms);

        $this->view->addNewShow = false;
        $this->view->action = "edit-repeating-show-instance";
        $this->view->newForm = $this->view->render('schedule/add-show-form.phtml');
    }

    public function populateShowFormAction()
    {
        $service_user = new Application_Service_UserService();
        $currentUser = $service_user->getCurrentUser();

        $showId = $this->_getParam('showId');
        $instanceId = $this->_getParam('instanceId');
        $service_showForm = new Application_Service_ShowFormService($showId, $instanceId);

        $isAdminOrPM = $currentUser->isAdminOrPM();
        /*$isHostOfShow = $currentUser->isHostOfShow($showId);
        // in case a user was once a dj and had been assigned to a show
        // but was then changed to an admin user we need to allow
        // the user to edit the show as an admin (CC-4925)
        if ($isHostOfShow && !$isAdminOrPM) {
            $this->view->action = "dj-edit-show";
        }*/

        $forms = $this->createShowFormAction();

        $service_showForm->delegateShowFormPopulation($forms);

        if (!$isAdminOrPM) {
            foreach ($forms as $form) {
                $form->disable();
            }
        }

        $this->view->action = "edit-show";
        $this->view->newForm = $this->view->render('schedule/add-show-form.phtml');
        $this->view->entries = 5;
    }

    public function getFormAction()
    {
        $service_user = new Application_Service_UserService();
        $currentUser = $service_user->getCurrentUser();

        if ($currentUser->isAdminOrPM()) {
            $this->createShowFormAction(true);
            $this->view->form = $this->view->render('schedule/add-show-form.phtml');
        }
    }

    /*public function djEditShowAction()
    {
        $js = $this->_getParam('data');
        $data = array();

        //need to convert from serialized jQuery array.
        foreach ($js as $j) {
            $data[$j["name"]] = $j["value"];
        }

        //update cc_show
        $show = new Application_Model_Show($data["add_show_id"]);
        $show->setAirtimeAuthFlag($data["cb_airtime_auth"]);
        $show->setCustomAuthFlag($data["cb_custom_auth"]);
        $show->setCustomUsername($data["custom_username"]);
        $show->setCustomPassword($data["custom_password"]);

        $this->view->edit = true;
    }*/

    public function editRepeatingShowInstanceAction(){
        $js = $this->_getParam('data');
        $data = array();

        //need to convert from serialized jQuery array.
        foreach ($js as $j) {
            $data[$j["name"]] = $j["value"];
        }

        $data['add_show_hosts'] =  $this->_getParam('hosts');

        $service_showForm = new Application_Service_ShowFormService(
            $data["add_show_id"], $data["add_show_instance_id"]);
        $service_show = new Application_Service_ShowService();

        $forms = $this->createShowFormAction();

        list($data, $validateStartDate, $validateStartTime, $originalShowStartDateTime) =
            $service_showForm->preEditShowValidationCheck($data);

        if ($service_showForm->validateShowForms($forms, $data, $validateStartDate,
                $originalShowStartDateTime, true, $data["add_show_instance_id"])) {

            $service_show->createShowFromRepeatingInstance($data);

            $this->view->addNewShow = true;
            $this->view->newForm = $this->view->render('schedule/add-show-form.phtml');
        } else {
            if (!$validateStartDate) {
                $this->view->when->getElement('add_show_start_date')->setOptions(array('disabled' => true));
            }
            if (!$validateStartTime) {
                $this->view->when->getElement('add_show_start_time')->setOptions(array('disabled' => true));
            }
            $this->view->rr->getElement('add_show_record')->setOptions(array('disabled' => true));
            $this->view->addNewShow = false;
            $this->view->action = "edit-show";
            $this->view->form = $this->view->render('schedule/add-show-form.phtml');
        }
    }

    public function editShowAction()
    {
        $js = $this->_getParam('data');
        $data = array();

        //need to convert from serialized jQuery array.
        foreach ($js as $j) {
            $data[$j["name"]] = $j["value"];
        }

        $service_showForm = new Application_Service_ShowFormService(
            $data["add_show_id"]);
        $service_show = new Application_Service_ShowService();

        //TODO: move this to js
        $data['add_show_hosts'] =  $this->_getParam('hosts');
        $data['add_show_day_check'] =  $this->_getParam('days');

        if ($data['add_show_day_check'] == "") {
            $data['add_show_day_check'] = null;
        }

        $forms = $this->createShowFormAction();

        list($data, $validateStartDate, $validateStartTime, $originalShowStartDateTime) =
            $service_showForm->preEditShowValidationCheck($data);

        if ($service_showForm->validateShowForms($forms, $data, $validateStartDate,
                $originalShowStartDateTime, true, $data["add_show_instance_id"])) {

            //pass in true to indicate we are updating a show
            $service_show->addUpdateShow($data, true);

            $this->view->addNewShow = true;
            $this->view->newForm = $this->view->render('schedule/add-show-form.phtml');
        } else {
            if (!$validateStartDate) {
                $this->view->when->getElement('add_show_start_date')->setOptions(array('disabled' => true));
            }
            if (!$validateStartTime) {
                $this->view->when->getElement('add_show_start_time')->setOptions(array('disabled' => true));
            }
            $this->view->rr->getElement('add_show_record')->setOptions(array('disabled' => true));
            $this->view->addNewShow = false;
            $this->view->action = "edit-show";
            $this->view->form = $this->view->render('schedule/add-show-form.phtml');
        }
    }

    public function addShowAction()
    {
        $service_showForm = new Application_Service_ShowFormService(null);
        $service_show = new Application_Service_ShowService();

        $js = $this->_getParam('data');
        $data = array();

        //need to convert from serialized jQuery array.
        foreach ($js as $j) {
            $data[$j["name"]] = $j["value"];
        }

        // TODO: move this to js
        $data['add_show_hosts']     = $this->_getParam('hosts');
        $data['add_show_day_check'] = $this->_getParam('days');

        if ($data['add_show_day_check'] == "") {
            $data['add_show_day_check'] = null;
        }

        $forms = $this->createShowFormAction();

        $this->view->addNewShow = true;

        if ($service_showForm->validateShowForms($forms, $data)) {
            $service_show->addUpdateShow($data);

            $this->view->newForm = $this->view->render('schedule/add-show-form.phtml');
            //send new show forms to the user
            $this->createShowFormAction(true);

            Logging::debug("Show creation succeeded");
        } else {
            $this->view->form = $this->view->render('schedule/add-show-form.phtml');
            Logging::debug("Show creation failed");
        }
    }

    public function createShowFormAction($populateDefaults=false)
    {
        $service_showForm = new Application_Service_ShowFormService();

        $forms = $service_showForm->createShowForms();

        // populate forms with default values
        if ($populateDefaults) {
            $service_showForm->populateNewShowForms(
                $forms["what"], $forms["when"], $forms["repeats"]);
        }

        $this->view->what = $forms["what"];
        $this->view->when = $forms["when"];
        $this->view->repeats = $forms["repeats"];
        $this->view->live = $forms["live"];
        $this->view->rr = $forms["record"];
        $this->view->absoluteRebroadcast = $forms["abs_rebroadcast"];
        $this->view->rebroadcast = $forms["rebroadcast"];
        $this->view->who = $forms["who"];
        $this->view->style = $forms["style"];

        return $forms;
    }

    public function deleteShowAction()
    {
        $instanceId = $this->_getParam('id');

        $service_show = new Application_Service_ShowService();
        $showId = $service_show->deleteShow($instanceId);

        if (!$showId) {
            $this->view->show_error = true;
        }
        $this->view->show_id = $showId;
    }

    public function cancelCurrentShowAction()
    {
        $user = Application_Model_User::getCurrentUser();

        if ($user->isUserType(array(UTYPE_ADMIN, UTYPE_PROGRAM_MANAGER))) {
            $id = $this->_getParam('id');

            try {
                $scheduler = new Application_Model_Scheduler();
                $scheduler->cancelShow($id);
                Application_Model_StoredFile::updatePastFilesIsScheduled();
                // send kick out source stream signal to pypo
                $data = array("sourcename"=>"live_dj");
                Application_Model_RabbitMq::SendMessageToPypo("disconnect_source", $data);
            } catch (Exception $e) {
                $this->view->error = $e->getMessage();
                Logging::info($e->getMessage());
            }
        }
    }

    /**
     * Sets the user specific preference for which time scale to use in Calendar.
     * This is only being used by schedule.js at the moment.
     */
    public function setTimeScaleAction()
    {
        Application_Model_Preference::SetCalendarTimeScale($this->_getParam('timeScale'));
    }

/**
     * Sets the user specific preference for which time interval to use in Calendar.
     * This is only being used by schedule.js at the moment.
     */
    public function setTimeIntervalAction()
    {
        Application_Model_Preference::SetCalendarTimeInterval($this->_getParam('timeInterval'));
    }

    public function calculateDurationAction()
    {
        $service_showForm = new Application_Service_ShowFormService();
        $result = $service_showForm->calculateDuration($this->_getParam('startTime'),
            $this->_getParam('endTime'));

        echo Zend_Json::encode($result);
        exit();
    }

    public function updateFutureIsScheduledAction()
    {
        $schedId = $this->_getParam('schedId');
        $redrawLibTable = Application_Model_StoredFile::setIsScheduled($schedId, false);
        $this->_helper->json->sendJson(array("redrawLibTable" => $redrawLibTable));
    }
}
