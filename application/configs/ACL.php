<?php

require_once 'Acl_plugin.php';

$ccAcl = new Zend_Acl();

$ccAcl->addRole(new Zend_Acl_Role('G'))
      ->addRole(new Zend_Acl_Role('H'), 'G')
      ->addRole(new Zend_Acl_Role('A'), 'H');

$ccAcl->add(new Zend_Acl_Resource('library'))
	  ->add(new Zend_Acl_Resource('index'))
	  ->add(new Zend_Acl_Resource('user'))
	  ->add(new Zend_Acl_Resource('error'))
      ->add(new Zend_Acl_Resource('login'))
	  ->add(new Zend_Acl_Resource('playlist'))
	  ->add(new Zend_Acl_Resource('sideplaylist'))
	  ->add(new Zend_Acl_Resource('plupload'))
	  ->add(new Zend_Acl_Resource('schedule'))
	  ->add(new Zend_Acl_Resource('api'))
	  ->add(new Zend_Acl_Resource('nowplaying'))
	  ->add(new Zend_Acl_Resource('search'))
      ->add(new Zend_Acl_Resource('preference'));

/** Creating permissions */
$ccAcl->allow('G', 'index')
	  ->allow('G', 'login')
	  ->allow('G', 'error')
	  ->allow('G', 'nowplaying')
	  ->allow('G', 'api')
      ->allow('G', 'schedule')
      ->allow('H', 'library')
      ->allow('H', 'search')
      ->allow('H', 'plupload')
	  ->allow('H', 'playlist')
	  ->allow('H', 'sideplaylist')
	  ->allow('A', 'user')
      ->allow('A', 'preference');

$aclPlugin = new Zend_Controller_Plugin_Acl($ccAcl);

Zend_View_Helper_Navigation_HelperAbstract::setDefaultAcl($ccAcl);

$front = Zend_Controller_Front::getInstance();
$front->registerPlugin($aclPlugin);
