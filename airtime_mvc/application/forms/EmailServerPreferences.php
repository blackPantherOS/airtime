<?php
require_once 'customvalidators/ConditionalNotEmpty.php';
require_once 'customvalidators/PasswordNotEmpty.php';

class Application_Form_EmailServerPreferences extends Zend_Form_SubForm
{
    private $isSaas;

    public function init()
    {
        $isSaas = Application_Model_Preference::GetPlanLevel() == 'disabled'?false:true;
        $this->isSaas = $isSaas;

        $this->setDecorators(array(
            array('ViewScript', array('viewScript' => 'form/preferences_email_server.phtml', "isSaas" => $isSaas))
        ));

        // Enable system emails
        $this->addElement('checkbox', 'enableSystemEmail', array(
            'label' => 'Enable System Emails (Password Reset)',
            'required' => false,
            'value' => Application_Model_Preference::GetEnableSystemEmail(),
            'decorators' => array(
                'ViewHelper'
            )
        ));

        $this->addElement('text', 'systemEmail', array(
            'class' => 'input_text',
            'label' => 'Reset Password \'From\' Email',
            'value' => Application_Model_Preference::GetSystemEmail(),
            'readonly' => true,
            'decorators' => array('viewHelper')
        ));

        $this->addElement('checkbox', 'configureMailServer', array(
            'label' => 'Configure Mail Server',
            'required' => false,
            'value' => Application_Model_Preference::GetMailServerConfigured(),
            'decorators' => array (
                'viewHelper'
            )
        ));
        
        $this->addElement('checkbox', 'msRequiresAuth', array(
            'label' => 'Requires Authentication',
            'required' => false,
            'value' => Application_Model_Preference::GetMailServerRequiresAuth(),
            'decorators' => array(
            	'viewHelper'
            )
        ));

        $this->addElement('text', 'mailServer', array(
            'class' => 'input_text',
            'label' => 'Mail Server',
            'value' => Application_Model_Preference::GetMailServer(),
            'readonly' => true,
            'decorators' => array('viewHelper'),
            'allowEmpty' => false,
            'validators' => array(
                new ConditionalNotEmpty(array(
                	'configureMailServer' => '1'
                ))
            )
        ));

        $this->addElement('text', 'email', array(
            'class' => 'input_text',
            'label' => 'Email Address',
            'value' => Application_Model_Preference::GetMailServerEmailAddress(),
            'readonly' => true,
            'decorators' => array('viewHelper'),
            'allowEmpty' => false,
            'validators' => array(
                new ConditionalNotEmpty(array(
                	'configureMailServer' => '1',
                    'msRequiresAuth' => '1'
                ))
            )
        ));

        $this->addElement('password', 'ms_password', array(
            'class' => 'input_text',
            'label' => 'Password',
            'value' => Application_Model_Preference::GetMailServerPassword(),
            'readonly' => true,
            'decorators' => array('viewHelper'),
            'allowEmpty' => false,
            'validators' => array(
                new ConditionalNotEmpty(array(
                	'configureMailServer' => '1',
                	'msRequiresAuth' => '1'
                ))
            ),
            'renderPassword' => true
        ));

        $port = new Zend_Form_Element_Text('port');
        $port->class = 'input_text';
        $port->setRequired(false)
            ->setValue(Application_Model_Preference::GetMailServerPort())
            ->setLabel('Port')
            ->setAttrib('readonly', true)
            ->setDecorators(array('viewHelper'));

        $this->addElement($port);

    }


}

