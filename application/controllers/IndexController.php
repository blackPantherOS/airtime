<?php

class IndexController extends Zend_Controller_Action
{

    public function init()
    {
        
    }

    public function indexAction()
    {
        $this->_forward('index', 'login');
    }

    public function mainAction()
    {             
        $this->_helper->layout->setLayout('layout');
    }

    public function newfieldAction()
    {
        // action body
    }

    public function displayAction()
    {
        // action body
    }


}







