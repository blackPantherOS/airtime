<?php

class Application_Form_Login extends Zend_Form
{

    public function init()
    { 
		// Set the method for the display form to POST
        $this->setMethod('post');

		// Add username element
        $this->addElement('text', 'username', array(
            'label'      => 'Username:',
            'required'   => true,
            'filters'    => array('StringTrim'),
            'validators' => array(
                'NotEmpty',
            )
        ));

		// Add password element
        $this->addElement('password', 'password', array(
            'label'      => 'Password:',
            'required'   => true,
            'filters'    => array('StringTrim'),
            'validators' => array(
                'NotEmpty',
            )
        ));

		// Add the submit button
        $this->addElement('submit', 'submit', array(
            'ignore'   => true,
            'label'    => 'Login',
        ));

    }


}

