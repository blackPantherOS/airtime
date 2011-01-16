<?php

class PlaylistController extends Zend_Controller_Action
{

    protected $pl_sess = null;

    public function init()
    {
        if(!Zend_Auth::getInstance()->hasIdentity())
        {
            $this->_redirect('login/index');
        }

        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('add-item', 'json')
					->addActionContext('delete-item', 'json')
					->addActionContext('set-fade', 'json')
					->addActionContext('set-cue', 'json')
					->addActionContext('move-item', 'json')
					->addActionContext('close', 'json')
					->addActionContext('edit', 'json')
					->addActionContext('delete-active', 'json')
					->addActionContext('delete', 'json')
                    ->initContext();

        $this->pl_sess = new Zend_Session_Namespace(UI_PLAYLIST_SESSNAME);
    }

	private function getPlaylist()
	{
		$pl_sess = $this->pl_sess;
                        
		if(isset($pl_sess->id)) {

			$pl = Playlist::Recall($pl_sess->id);
			if($pl === FALSE) {
				unset($pl_sess->id);
				$this->_helper->redirector('index');
			}

			return $pl;
		}
		
		$this->_helper->redirector('index');
	}

	private function changePlaylist($pl_id){
		
		$pl_sess = $this->pl_sess;

		if(isset($pl_sess->id)) {

			$pl = Playlist::Recall($pl_sess->id);
			if($pl !== FALSE) {
				$this->closePlaylist($pl);
			}
		}
	
		$userInfo = Zend_Auth::getInstance()->getStorage()->read();

		$pl = Playlist::Recall($pl_id);
		if($pl === FALSE) {
			return FALSE;
		}		
		$pl->lock($userInfo->id);
		$pl_sess->id = $pl_id;	
	}

	private function closePlaylist($pl)
    {
        $userInfo = Zend_Auth::getInstance()->getStorage()->read();
        $res = $pl->unlock($userInfo->id);

		$pl_sess = $this->pl_sess;
		unset($pl_sess->id);

        return $res;
    }

    public function indexAction()
    {
        $this->view->headScript()->appendFile('/js/airtime/library/spl.js','text/javascript');
		$this->view->headLink()->appendStylesheet('/css/playlist_builder.css');

		$this->_helper->viewRenderer->setResponseSegment('spl'); 

        $pl_sess = $this->pl_sess;
                        
		if(isset($pl_sess->id)) {

			$pl = $this->getPlaylist();
			
			$this->view->pl = $pl;
		}
    }

    public function newAction()
    {
        $pl_sess = $this->pl_sess;
		$userInfo = Zend_Auth::getInstance()->getStorage()->read();

        $pl = new Playlist();
        $pl_id = $pl->create("Test Zend Auth");
		$pl->setPLMetaData('dc:creator', $userInfo->login);

		$this->changePlaylist($pl_id);

		$this->_helper->redirector('metadata');
    }

    public function metadataAction()
    {                                                  
        $request = $this->getRequest();
        $form = new Application_Form_PlaylistMetadata();

		$pl_id = $this->_getParam('id', null);
		//not a new playlist
		if(!is_null($pl_id)) {
			$this->changePlaylist($pl_id); 
 
			$pl = $this->getPlaylist();
			$title = $pl->getPLMetaData(UI_MDATA_KEY_TITLE);
			$desc = $pl->getPLMetaData(UI_MDATA_KEY_DESCRIPTION);

			$data = array( 'title' => $title, 'description' => $desc);  
			$form->populate($data);  
		}
 
        if ($request->isPost()) {
            if ($form->isValid($request->getPost())) {  
    
				$formdata = $form->getValues();

				$pl = $this->getPlaylist();
				$pl->setPLMetaData(UI_MDATA_KEY_TITLE, $formdata["title"]);
				
				if(isset($formdata["description"])) {
					$pl->setPLMetaData(UI_MDATA_KEY_DESCRIPTION, $formdata["description"]);
				}

				$this->_helper->redirector('edit');
            }
        }
 
        $this->view->form = $form;
    }

    public function editAction()
    {
        $this->view->headScript()->appendFile('/js/airtime/playlist/playlist.js','text/javascript'); 

		$pl_id = $this->_getParam('id', null);
	
		if(!is_null($pl_id)) {
			$this->changePlaylist($pl_id);      
		}
		                      
		$pl = $this->getPlaylist();
		
		$this->view->pl = $pl;
		$this->view->html = $this->view->render('playlist/index.phtml');
		unset($this->view->pl);
    }

    public function addItemAction()
    {    
		$id = $this->_getParam('id');

		if (!is_null($id)) {
			
			$pl = $this->getPlaylist();
			$res = $pl->addAudioClip($id);

			if (PEAR::isError($res)) {
				$this->view->message = $res->getMessage();
			}

			$this->view->pl = $pl;
			$this->view->html = $this->view->render('playlist/update.phtml');
			$this->view->name = $pl->getName();
			$this->view->length = $pl->getLength();

			unset($this->view->pl);
			return;
		}
		$this->view->message =  "a file is not chosen";
    }

    public function moveItemAction()
    {
		$oldPos = $this->_getParam('oldPos');
		$newPos = $this->_getParam('newPos');
		
		$pl = $this->getPlaylist();

		$pl->moveAudioClip($oldPos, $newPos);

		$this->view->pl = $pl;
		$this->view->html = $this->view->render('playlist/update.phtml');
		$this->view->name = $pl->getName();
		$this->view->length = $pl->getLength();

		unset($this->view->pl);
    }

    public function deleteItemAction()
    {
		$positions = $this->_getParam('pos', array());
		
		if (!is_array($positions))
	        $positions = array($positions);

	    //so the automatic updating of playlist positioning doesn't affect removal.
	    sort($positions);
	    $positions = array_reverse($positions);

		$pl = $this->getPlaylist();

	    foreach ($positions as $pos) {
	    	$pl->delAudioClip($pos);        
	    }

		$this->view->pl = $pl;
		$this->view->html = $this->view->render('playlist/update.phtml');
		$this->view->name = $pl->getName();
		$this->view->length = $pl->getLength();

		unset($this->view->pl);		
    }

    public function setCueAction()
    {
		$pos = $this->_getParam('pos');
		$cueIn = $this->_getParam('cueIn', null);
		$cueOut = $this->_getParam('cueOut', null);

		$pl = $this->getPlaylist();
		$response = $pl->changeClipLength($pos, $cueIn, $cueOut);

		die(json_encode($response));
    }

    public function setFadeAction()
    {
		$pos = $this->_getParam('pos');
		$fadeIn = $this->_getParam('fadeIn', null);
		$fadeOut = $this->_getParam('fadeOut', null);

		$pl = $this->getPlaylist();
		
		$response = $pl->changeFadeInfo($pos, $fadeIn, $fadeOut);

		die(json_encode($response));
    }

    public function deleteAction()
    {
        $id = $this->_getParam('id', null);
		$pl = Playlist::Recall($id);
                
		if ($pl !== FALSE) {

			Playlist::Delete($id);

			$pl_sess = $this->pl_sess;

			if($pl_sess->id === $id){
				unset($pl_sess->id);
			}
		}

		$this->view->id = $id;
    }

    public function deleteActiveAction()
    {   
		$pl = $this->getPlaylist();	
		Playlist::Delete($pl->getId());

		$pl_sess = $this->pl_sess;
		unset($pl_sess->id);

		$this->view->html = $this->view->render('playlist/index.phtml');
    }

    public function closeAction()
    {
		$pl = $this->getPlaylist();
		$this->closePlaylist($pl);
		
		$this->view->html = $this->view->render('playlist/index.phtml');	
    }

}























