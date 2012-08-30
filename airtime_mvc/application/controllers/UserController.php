<?php

class UserController extends Zend_Controller_Action
{

    public function init()
    {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('get-hosts', 'json')
                    ->addActionContext('get-user-data-table-info', 'json')
                    ->addActionContext('get-user-data', 'json')
                    ->addActionContext('remove-user', 'json')
                    ->initContext();
    }

    public function indexAction()
    {
    }

    public function addUserAction()
    {
        global $CC_CONFIG;

        $request = $this->getRequest();
        $baseUrl = $request->getBaseUrl();

        $this->view->headScript()->appendFile($baseUrl.'/js/datatables/js/jquery.dataTables.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'/js/datatables/plugin/dataTables.pluginAPI.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'/js/airtime/user/user.js?'.$CC_CONFIG['airtime_version'],'text/javascript');

        $this->view->headLink()->appendStylesheet($baseUrl.'/css/users.css?'.$CC_CONFIG['airtime_version']);

        $form = new Application_Form_AddUser();

        $this->view->successMessage = "";

        if ($request->isPost()) {
            if ($form->isValid($request->getPost())) {

                $formdata = $form->getValues();
                if (isset($CC_CONFIG['demo']) && $CC_CONFIG['demo'] == 1 && $formdata['login'] == 'admin' && $formdata['user_id'] != 0) {
                    $this->view->successMessage = "<div class='errors'>Specific action is not allowed in demo version!</div>";
                } elseif ($form->validateLogin($formdata)) {
                    $user = new Application_Model_User($formdata['user_id']);
                    $user->setFirstName($formdata['first_name']);
                    $user->setLastName($formdata['last_name']);
                    $user->setLogin($formdata['login']);
                    // We don't allow 6 x's as passwords are not allowed.
                    // The reason is because we that as a password placeholder
                    // on the client side.
                    if ($formdata['password'] != "xxxxxx")
                        $user->setPassword($formdata['password']);
                    $user->setType($formdata['type']);
                    $user->setEmail($formdata['email']);
                    $user->setCellPhone($formdata['cell_phone']);
                    $user->setSkype($formdata['skype']);
                    $user->setJabber($formdata['jabber']);
                    $user->save();

                    $form->reset();

                    if (strlen($formdata['user_id']) == 0) {
                        $this->view->successMessage = "<div class='success'>User added successfully!</div>";
                    } else {
                        $this->view->successMessage = "<div class='success'>User updated successfully!</div>";
                    }
                }
            }
        }

        $this->view->form = $form;
    }

    public function getHostsAction()
    {
        $search            = $this->_getParam('term');
        $res               = Application_Model_User::getHosts($search);
        $this->view->hosts = Application_Model_User::getHosts($search);
    }

    public function getUserDataTableInfoAction()
    {
        $post = $this->getRequest()->getPost();
        $users = Application_Model_User::getUsersDataTablesInfo($post);

        die(json_encode($users));
    }

    public function getUserDataAction()
    {
        $id = $this->_getParam('id');
        $this->view->entries = Application_Model_User::GetUserData($id);
    }

    public function removeUserAction()
    {
        // action body
        $delId = $this->_getParam('id');

        $valid_actions = array("delete_cascade", "reassign_to");

        $files_action = $this->_getParam('deleted_files');

        # TODO : remove this. we only use default for now not to break the UI.
        if (!$files_action) { # set default action
            $files_action = "delete_cascade";
        }

        # only delete when valid action is selected for the owned files
        if (! in_array($files_action, $valid_actions) ) {
            return;
        } 

        $userInfo = Zend_Auth::getInstance()->getStorage()->read();
        $userId = $userInfo->id;

        # Don't let users delete themselves
        if ($delId == $userId) {
            return;
        }

        $user = new Application_Model_User($delId);

        # Take care of the user's files by either assigning them to somebody
        # or deleting them all
        if ($files_action == "delete_cascade") {
            $user->deleteAllFiles();
        } elseif ($files_action == "reassign_to") {
            $new_owner = $this->_getParam("new_owner");
            $user->reassignTo( $new_owner );
        }
        # Finally delete the user
        $this->view->entries = $user->delete();
    }
}
