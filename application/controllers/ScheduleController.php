<?php

class ScheduleController extends Zend_Controller_Action
{

    protected $sched_sess = null;

    public function init()
    {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('event-feed', 'json')
                    ->addActionContext('make-context-menu', 'json')
					->addActionContext('add-show-dialog', 'json')
					->addActionContext('add-show', 'json')
					->addActionContext('move-show', 'json')
					->addActionContext('resize-show', 'json')
					->addActionContext('delete-show', 'json')
					->addActionContext('schedule-show', 'json')
					->addActionContext('schedule-show-dialog', 'json')
                    ->addActionContext('show-content-dialog', 'json')
					->addActionContext('clear-show', 'json')
                    ->addActionContext('get-current-playlist', 'json')	
					->addActionContext('find-playlists', 'json')
					->addActionContext('remove-group', 'json')	
                    ->addActionContext('edit-show', 'json')
                    ->addActionContext('add-show', 'json')
                    ->addActionContext('cancel-show', 'json')
                    ->initContext();

		$this->sched_sess = new Zend_Session_Namespace("schedule");
    }

    public function indexAction()
    {
        $this->view->headScript()->appendFile('/js/contextmenu/jjmenu.js','text/javascript');
		$this->view->headScript()->appendFile('/js/datatables/js/jquery.dataTables.js','text/javascript');
        $this->view->headScript()->appendFile('/js/fullcalendar/fullcalendar.min.js','text/javascript');
        $this->view->headScript()->appendFile('/js/timepicker/jquery.ui.timepicker-0.0.6.js','text/javascript');
		$this->view->headScript()->appendFile('/js/colorpicker/js/colorpicker.js','text/javascript');
    	$this->view->headScript()->appendFile('/js/airtime/schedule/full-calendar-functions.js','text/javascript');
		$this->view->headScript()->appendFile('/js/airtime/schedule/add-show.js','text/javascript');
    	$this->view->headScript()->appendFile('/js/airtime/schedule/schedule.js','text/javascript');

		$this->view->headLink()->appendStylesheet('/css/jquery-ui-timepicker.css');
        $this->view->headLink()->appendStylesheet('/css/fullcalendar.css');
		$this->view->headLink()->appendStylesheet('/css/colorpicker/css/colorpicker.css');
		$this->view->headLink()->appendStylesheet('/css/add-show.css');
        $this->view->headLink()->appendStylesheet('/css/contextmenu.css');

        $request = $this->getRequest();
        $formWhat = new Application_Form_AddShowWhat();
		$formWhat->removeDecorator('DtDdWrapper');
		$formWho = new Application_Form_AddShowWho();
		$formWho->removeDecorator('DtDdWrapper');
		$formWhen = new Application_Form_AddShowWhen();
		$formWhen->removeDecorator('DtDdWrapper');
		$formRepeats = new Application_Form_AddShowRepeats();
		$formRepeats->removeDecorator('DtDdWrapper');
		$formStyle = new Application_Form_AddShowStyle();
		$formStyle->removeDecorator('DtDdWrapper');

        $this->view->what = $formWhat;
		$this->view->when = $formWhen;
		$this->view->repeats = $formRepeats;
		$this->view->who = $formWho;
		$this->view->style = $formStyle;

        $userInfo = Zend_Auth::getInstance()->getStorage()->read();
        $user = new User($userInfo->id);
        $this->view->isAdmin = $user->isAdmin();
    }

    public function eventFeedAction()
    {
        $start = $this->_getParam('start', null);
		$end = $this->_getParam('end', null);
		
		$userInfo = Zend_Auth::getInstance()->getStorage()->read();
        $user = new User($userInfo->id);
        if($user->isAdmin())
            $editable = true;
        else
            $editable = false;

		$this->view->events = Show::getFullCalendarEvents($start, $end, $editable);
    }

    public function moveShowAction()
    {
        $deltaDay = $this->_getParam('day');
		$deltaMin = $this->_getParam('min');
		$showInstanceId = $this->_getParam('showInstanceId');

        $userInfo = Zend_Auth::getInstance()->getStorage()->read();
        $user = new User($userInfo->id);

        if($user->isAdmin()) {
		    $show = new ShowInstance($showInstanceId);
		    $error = $show->moveShow($deltaDay, $deltaMin);
        }

		if(isset($error))
			$this->view->error = $error;
    }

    public function resizeShowAction()
    {
        $deltaDay = $this->_getParam('day');
		$deltaMin = $this->_getParam('min');
		$showInstanceId = $this->_getParam('showInstanceId');

        $userInfo = Zend_Auth::getInstance()->getStorage()->read();
        $user = new User($userInfo->id);

        if($user->isAdmin()) {
		    $show = new ShowInstance($showInstanceId);
		    $error = $show->resizeShow($deltaDay, $deltaMin);
        }

		if(isset($error))
			$this->view->error = $error;
    }

    public function deleteShowAction()
    {
        $showInstanceId = $this->_getParam('id');
                        		                                       
		$userInfo = Zend_Auth::getInstance()->getStorage()->read();
		$user = new User($userInfo->id);

        if($user->isAdmin()) {
		    $show = new ShowInstance($showInstanceId);
		    $show->deleteShow();
        }
    }

    public function makeContextMenuAction()
    {
        $id = $this->_getParam('id');
        $today_timestamp = date("Y-m-d H:i:s");

        $userInfo = Zend_Auth::getInstance()->getStorage()->read();
        $user = new User($userInfo->id);

        $show = new ShowInstance($id);

		$params = '/format/json/id/#id#';

		if (strtotime($today_timestamp) < strtotime($show->getShowStart())) {
            if ($user->isHost($show->getShowId()) || $user->isAdmin()) {	      
                $menu[] = array('action' => array('type' => 'ajax', 'url' => '/Schedule/schedule-show-dialog'.$params, 'callback' => 'window["buildScheduleDialog"]'), 'title' => 'Add Content');
            }
    }
    $menu[] = array('action' => array('type' => 'ajax', 'url' => '/Schedule/show-content-dialog'.$params, 'callback' => 'window["buildContentDialog"]'), 
							'title' => 'Show Content');
		if (strtotime($today_timestamp) < strtotime($show->getShowStart())) {
            if ($user->isAdmin()) {
                $menu[] = array('action' => array('type' => 'ajax', 'url' => '/Schedule/delete-show'.$params, 'callback' => 'window["scheduleRefetchEvents"]'), 'title' => 'Delete This Instance');
                $menu[] = array('action' => array('type' => 'ajax', 'url' => '/Schedule/cancel-show'.$params, 'callback' => 'window["scheduleRefetchEvents"]'), 'title' => 'Delete This Instance and All Following');
            }
            if ($user->isHost($show->getShowId()) || $user->isAdmin()) {
			          $menu[] = array('action' => array('type' => 'ajax', 'url' => '/Schedule/clear-show'.$params, 'callback' => 'window["scheduleRefetchEvents"]'), 'title' => 'Remove All Content');
            }
		}

		
		//returns format jjmenu is looking for.
		die(json_encode($menu));
    }

    public function scheduleShowAction()
    {
        $showInstanceId = $this->sched_sess->showInstanceId;
		$search = $this->_getParam('search', null);
		$plId = $this->_getParam('plId');

		if($search == "") {
			$search = null;
		}

		$userInfo = Zend_Auth::getInstance()->getStorage()->read();
        $user = new User($userInfo->id);
		$show = new ShowInstance($showInstanceId);

        if($user->isHost($show->getShowId()) || $user->isAdmin()) {
		    $show->scheduleShow(array($plId));
        }

		$this->view->showContent = $show->getShowContent();
		$this->view->timeFilled = $show->getTimeScheduled();
		$this->view->percentFilled = $show->getPercentScheduled();

		$this->view->chosen = $this->view->render('schedule/scheduled-content.phtml');	
		unset($this->view->showContent);
    }

    public function clearShowAction()
    {
        $showInstanceId = $this->_getParam('id');
        $userInfo = Zend_Auth::getInstance()->getStorage()->read();
        $user = new User($userInfo->id);
        $show = new ShowInstance($showInstanceId);

        if($user->isHost($show->getShowId()) || $user->isAdmin())
            $show->clearShow();
    }

    public function getCurrentPlaylistAction()
    {
        $this->view->entries = Schedule::GetPlayOrderRange();
    }

    public function findPlaylistsAction()
    {
        $post = $this->getRequest()->getPost();
                        
		$show = new ShowInstance($this->sched_sess->showInstanceId);
		$playlists = $show->searchPlaylistsForShow($post);

		//for datatables
		die(json_encode($playlists));
    }

    public function removeGroupAction()
    {
        $showInstanceId = $this->sched_sess->showInstanceId;
        $group_id = $this->_getParam('groupId');
		$search = $this->_getParam('search', null);

		$userInfo = Zend_Auth::getInstance()->getStorage()->read();
        $user = new User($userInfo->id);
        $show = new ShowInstance($showInstanceId);

        if($user->isHost($show->getShowId()) || $user->isAdmin()) {
		    $show->removeGroupFromShow($group_id);
        }

		$this->view->showContent = $show->getShowContent();
		$this->view->timeFilled = $show->getTimeScheduled();
		$this->view->percentFilled = $show->getPercentScheduled();
		$this->view->chosen = $this->view->render('schedule/scheduled-content.phtml');	
		unset($this->view->showContent);
    }

    public function scheduleShowDialogAction()
    {
        $showInstanceId = $this->_getParam('id');
        $this->sched_sess->showInstanceId = $showInstanceId;
        
        $show = new ShowInstance($showInstanceId);
        $start_timestamp = $show->getShowStart();
		$end_timestamp = $show->getShowEnd();

        //check to make sure show doesn't overlap.
        if(Show::getShows($start_timestamp, $end_timestamp, array($showInstanceId))) {
            $this->view->error = "cannot schedule an overlapping show.";
            return;
        }
		
        $start = explode(" ", $start_timestamp);
        $end = explode(" ", $end_timestamp);
        $startTime = explode(":", $start[1]);
        $endTime = explode(":", $end[1]);
        $dateInfo_s = getDate(strtotime($start_timestamp));
        $dateInfo_e = getDate(strtotime($end_timestamp));
		
		$this->view->showContent = $show->getShowContent();
		$this->view->timeFilled = $show->getTimeScheduled();
        $this->view->showName = $show->getName();
		$this->view->showLength = $show->getShowLength();
		$this->view->percentFilled = $show->getPercentScheduled();

        $this->view->s_wday = $dateInfo_s['weekday'];
        $this->view->s_month = $dateInfo_s['month'];
        $this->view->s_day = $dateInfo_s['mday'];
        $this->view->e_wday = $dateInfo_e['weekday'];
        $this->view->e_month = $dateInfo_e['month'];
        $this->view->e_day = $dateInfo_e['mday'];
        $this->view->startTime = sprintf("%d:%02d", $startTime[0], $startTime[1]);
        $this->view->endTime = sprintf("%d:%02d", $endTime[0], $endTime[1]);

		$this->view->chosen = $this->view->render('schedule/scheduled-content.phtml');	
		$this->view->dialog = $this->view->render('schedule/schedule-show-dialog.phtml');
		unset($this->view->showContent);
    }

    public function showContentDialogAction()
    {
        $showInstanceId = $this->_getParam('id');
		$show = new ShowInstance($showInstanceId);

		$this->view->showContent = $show->getShowListContent();
        $this->view->dialog = $this->view->render('schedule/show-content-dialog.phtml');
        unset($this->view->showContent);
    }

    public function editShowAction()
    {
        $showInstanceId = $this->_getParam('id');
        $showInstance = new ShowInstance($showInstanceId);

        $show = new Show($showInstance->getShowId());
    }

    public function addShowAction()
    {
        $js = $this->_getParam('data');
        $data = array();
       
        //need to convert from serialized jQuery array.
        foreach($js as $j){
            $data[$j["name"]] = $j["value"];
        }
        $data['add_show_hosts'] =  $this->_getParam('hosts');
        $data['add_show_day_check'] =  $this->_getParam('days');

        $formWhat = new Application_Form_AddShowWhat();
		$formWho = new Application_Form_AddShowWho();
		$formWhen = new Application_Form_AddShowWhen();
		$formRepeats = new Application_Form_AddShowRepeats();
		$formStyle = new Application_Form_AddShowStyle();

		$formWhat->removeDecorator('DtDdWrapper');
		$formWho->removeDecorator('DtDdWrapper');
		$formWhen->removeDecorator('DtDdWrapper');
		$formRepeats->removeDecorator('DtDdWrapper');
		$formStyle->removeDecorator('DtDdWrapper');

        $this->view->what = $formWhat;
	    $this->view->when = $formWhen;
	    $this->view->repeats = $formRepeats;
	    $this->view->who = $formWho;
	    $this->view->style = $formStyle;

		$what = $formWhat->isValid($data);
		$when = $formWhen->isValid($data);
        if($when) {
            $when = $formWhen->checkReliantFields($data);
        }

        if($data["add_show_repeats"]) {
		    $repeats = $formRepeats->isValid($data);
            if($repeats) {
                $repeats = $formRepeats->checkReliantFields($data);
            }
        }
        else {
            $repeats = 1; //make it valid, results don't matter anyways.
        }

		$who = $formWho->isValid($data);
		$style = $formStyle->isValid($data);

        if ($what && $when && $repeats && $who && $style) {  
		
            $userInfo = Zend_Auth::getInstance()->getStorage()->read();
            $user = new User($userInfo->id);
			if($user->isAdmin()) {
			    Show::addShow($data);
            }

            //send back a new form for the user.
            $formWhat->reset();
		    $formWho->reset();
		    $formWhen->reset();
            $formWhen->populate(array('add_show_start_date' => date("Y-m-d"),
                                      'add_show_start_time' => '0:00',
                                      'add_show_duration' => '1:00'));
		    $formRepeats->reset();
            $formRepeats->populate(array('add_show_end_date' => date("Y-m-d")));
		    $formStyle->reset();
            
            $this->view->newForm = $this->view->render('schedule/add-show-form.phtml');
		}
        else {

            $this->view->form = $this->view->render('schedule/add-show-form.phtml');
        }
    }

    public function cancelShowAction()
    {
        $userInfo = Zend_Auth::getInstance()->getStorage()->read();
        $user = new User($userInfo->id);
		
        if($user->isAdmin()) {
		    $showInstanceId = $this->_getParam('id');

            $showInstance = new ShowInstance($showInstanceId);
            $show = new Show($showInstance->getShowId());

            $show->cancelShow($showInstance->getShowStart());
        }   
    }
}








