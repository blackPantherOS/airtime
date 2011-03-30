<?php
/* THIS FILE IS NOT MEANT FOR CUSTOMIZING.
 * PLEASE EDIT THE FOLLOWING TO CHANGE YOUR CONFIG:
 * /etc/airtime/airtime.conf
 * /etc/airtime/pypo.cfg
 * /etc/airtime/recorder.cfg
 */
 
define('AIRTIME_VERSION', '1.7.0-alpha');
define('AIRTIME_COPYRIGHT_DATE', '2010-2011');
define('AIRTIME_REST_VERSION', '1.1');

global $CC_CONFIG;
$values = load_airtime_config();

$CC_CONFIG = array(

    // Name of the web server user
    'webServerUser' => $values['general']['webServerUser'],

    'rabbitmq' => $values['rabbitmq'],

    'baseFilesDir' => $values['general']['baseFilesDir'],
    // main directory for storing binary media files
    'storageDir'    =>  $values['general']['baseFilesDir']."/stor",

	// Database config
    'dsn' => array(
                'username'      => $values['database']['dbuser'],
                'password'      => $values['database']['dbpass'],
                'hostspec'      => $values['database']['host'],
                'phptype'       => 'pgsql',
                'database'      => $values['database']['dbname']),

    // prefix for table names in the database
    'tblNamePrefix' => 'cc_',

    /* ================================================ storage configuration */

    'apiKey' => array($values['general']['api_key']),
    'apiPath' => '/api/',

    'soundcloud-client-id' => '2CLCxcSXYzx7QhhPVHN4A',
    'soundcloud-client-secret' => 'pZ7beWmF06epXLHVUP1ufOg2oEnIt9XhE8l8xt0bBs',

    "rootDir" => __DIR__."/../..",
    'pearPath'      =>  dirname(__FILE__).'/../../library/pear',
    'zendPath'      =>  dirname(__FILE__).'/../../library/Zend',
    'phingPath'      =>  dirname(__FILE__).'/../../library/phing',

);

// Add database table names
$CC_CONFIG['playListTable'] = $CC_CONFIG['tblNamePrefix'].'playlist';
$CC_CONFIG['playListContentsTable'] = $CC_CONFIG['tblNamePrefix'].'playlistcontents';
$CC_CONFIG['filesTable'] = $CC_CONFIG['tblNamePrefix'].'files';
$CC_CONFIG['accessTable'] = $CC_CONFIG['tblNamePrefix'].'access';
$CC_CONFIG['permTable'] = $CC_CONFIG['tblNamePrefix'].'perms';
$CC_CONFIG['sessTable'] = $CC_CONFIG['tblNamePrefix'].'sess';
$CC_CONFIG['subjTable'] = $CC_CONFIG['tblNamePrefix'].'subjs';
$CC_CONFIG['smembTable'] = $CC_CONFIG['tblNamePrefix'].'smemb';
$CC_CONFIG['prefTable'] = $CC_CONFIG['tblNamePrefix'].'pref';
$CC_CONFIG['scheduleTable'] = $CC_CONFIG['tblNamePrefix'].'schedule';
$CC_CONFIG['playListTimeView'] = $CC_CONFIG['tblNamePrefix'].'playlisttimes';
$CC_CONFIG['showSchedule'] = $CC_CONFIG['tblNamePrefix'].'show_schedule';
$CC_CONFIG['showDays'] = $CC_CONFIG['tblNamePrefix'].'show_days';
$CC_CONFIG['showTable'] = $CC_CONFIG['tblNamePrefix'].'show';
$CC_CONFIG['showInstances'] = $CC_CONFIG['tblNamePrefix'].'show_instances';

$CC_CONFIG['playListSequence'] = $CC_CONFIG['playListTable'].'_id';
$CC_CONFIG['filesSequence'] = $CC_CONFIG['filesTable'].'_id';
$CC_CONFIG['prefSequence'] = $CC_CONFIG['prefTable'].'_id';
$CC_CONFIG['permSequence'] = $CC_CONFIG['permTable'].'_id';
$CC_CONFIG['subjSequence'] = $CC_CONFIG['subjTable'].'_id';
$CC_CONFIG['smembSequence'] = $CC_CONFIG['smembTable'].'_id';

// Add libs to the PHP path
$old_include_path = get_include_path();
set_include_path('.'.PATH_SEPARATOR.$CC_CONFIG['pearPath']
					.PATH_SEPARATOR.$CC_CONFIG['zendPath']
					.PATH_SEPARATOR.$old_include_path);

function load_airtime_config(){
	$ini_array = parse_ini_file('/etc/airtime/airtime.conf', true);
    return $ini_array;
}
