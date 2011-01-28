<?php

class NowplayingController extends Zend_Controller_Action
{

    public function init()
    {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
                $ajaxContext->addActionContext('get-data-grid-data', 'json')->initContext();
    }

    public function indexAction()
    {
        $this->view->headScript()->appendFile('/js/datatables/js/jquery.dataTables.min.js','text/javascript');
        $this->view->headScript()->appendFile('/js/playlist/nowplayingdatagrid.js','text/javascript');
		$this->view->headLink()->appendStylesheet('/css/pro_dropdown_3.css');
		$this->view->headLink()->appendStylesheet('/css/styles.css');
		//$this->view->headLink()->appendStylesheet('/css/datatables/demo_page.css');
		//$this->view->headLink()->appendStylesheet('/css/datatables/demo_table.css');
		//$this->view->headLink()->appendStylesheet('/css/datatables/demo_table_jui.css');
		//$this->view->headLink()->appendStylesheet('/css/styles.css');
    }

    public function getDataGridDataAction()
    {
        $this->view->entries = Application_Model_Nowplaying::GetDataGridData();
    }

    public function livestreamAction()
    {
        //use bare bones layout (no header bar or menu)
        $this->_helper->layout->setLayout('bare');
    }
}







