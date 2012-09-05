<?php

//error_reporting(E_ALL|E_STRICT);
//error_reporting(E_ALL);
//ini_set('display_errors', 'on');

// Define path to application directory
defined('APPLICATION_PATH')
    || define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/../application'));

// Define application environment
defined('APPLICATION_ENV')
    || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production'));

// Ensure library/ is on include_path
set_include_path(implode(PATH_SEPARATOR, array(
    get_include_path(),
    realpath(APPLICATION_PATH . '/../library')
)));

set_include_path(APPLICATION_PATH . '/common' . PATH_SEPARATOR . get_include_path());

//Propel classes.
set_include_path(APPLICATION_PATH . '/models' . PATH_SEPARATOR . get_include_path());

//Controller plugins.
set_include_path(APPLICATION_PATH . '/controllers/plugins' . PATH_SEPARATOR . get_include_path());

//Zend framework
if (file_exists('/usr/share/php/libzend-framework-php')) {
    set_include_path('/usr/share/php/libzend-framework-php' . PATH_SEPARATOR . get_include_path());
}

/** Zend_Application */
require_once 'Zend/Application.php';

// Create application, bootstrap, and run
$application = new Zend_Application(
    APPLICATION_ENV,
    APPLICATION_PATH . '/configs/application.ini'
);

$sapi_type = php_sapi_name();
if (substr($sapi_type, 0, 3) == 'cli') {
    set_include_path(APPLICATION_PATH . PATH_SEPARATOR . get_include_path());
    require_once("Bootstrap.php");
} else {
    $application->bootstrap()
            ->run();
}


