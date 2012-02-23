<?php
class UsersettingsController extends Zend_Controller_Action
{

    public function init()
    {
        /* Initialize action controller here */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('get-library-datatable', 'json')
                    ->addActionContext('set-library-datatable', 'json')
                    ->addActionContext('get-timeline-datatable', 'json')
                    ->addActionContext('set-timeline-datatable', 'json')
                    ->initContext();
    }

    public function setLibraryDatatableAction() {

        $request = $this->getRequest();
        $settings = $request->getParam("settings");

        $data = serialize($settings);
        Application_Model_Preference::SetValue("library_datatable", $data, true);
    }

    public function getLibraryDatatableAction() {

        $data = Application_Model_Preference::GetValue("library_datatable", true);
        if ($data != "") {
            $this->view->settings = unserialize($data);
        }
    }

    public function setTimelineDatatableAction() {

        $request = $this->getRequest();
        $settings = $request->getParam("settings");

        $data = serialize($settings);
        Application_Model_Preference::SetValue("timeline_datatable", $data, true);
    }

    public function getTimelineDatatableAction() {

        $data = Application_Model_Preference::GetValue("timeline_datatable", true);
        if ($data != "") {
            $this->view->settings = unserialize($data);
        }
    }
}