<?php
/* THIS FILE IS NOT MEANT FOR CUSTOMIZING.
 * PLEASE EDIT THE FOLLOWING TO CHANGE YOUR CONFIG:
 * /etc/airtime/airtime.conf
 * /etc/airtime/pypo.cfg
 * /etc/airtime/recorder.cfg
 */

global $CC_CONFIG;

$CC_CONFIG = array(
    // prefix for table names in the database
    'tblNamePrefix' => 'cc_',

    /* ================================================ storage configuration */

    'soundcloud-client-id' => '2CLCxcSXYzx7QhhPVHN4A',
    'soundcloud-client-secret' => 'pZ7beWmF06epXLHVUP1ufOg2oEnIt9XhE8l8xt0bBs',

    "rootDir" => __DIR__."/../.."
);


$configFile = isset($_SERVER['AIRTIME_CONF']) ? $_SERVER['AIRTIME_CONF'] : "/etc/airtime/airtime.conf";
Config::loadConfig($configFile);

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

class Config {
    public static function loadConfig($p_path) {
        global $CC_CONFIG;
        
        $filename = $p_path;
        $values = parse_ini_file($filename, true);

        // Name of the web server user
        $CC_CONFIG['webServerUser'] = $values['general']['web_server_user'];
        $CC_CONFIG['rabbitmq'] = $values['rabbitmq'];

        $CC_CONFIG['baseUrl'] = $values['general']['base_url'];
        $CC_CONFIG['basePort'] = $values['general']['base_port'];
        
        $CC_CONFIG['cache_ahead_hours'] = $values['general']['cache_ahead_hours'];
        
	    // Database config
        $CC_CONFIG['dsn']['username'] = $values['database']['dbuser'];
        $CC_CONFIG['dsn']['password'] = $values['database']['dbpass'];
        $CC_CONFIG['dsn']['hostspec'] = $values['database']['host'];
        $CC_CONFIG['dsn']['phptype'] = 'pgsql';
        $CC_CONFIG['dsn']['database'] = $values['database']['dbname'];

        $CC_CONFIG['apiKey'] = array($values['general']['api_key']);
        
        if (defined('APPLICATION_ENV') && APPLICATION_ENV == "development"){
            $CC_CONFIG['apiKey'][] = "";
        }

        $CC_CONFIG['soundcloud-connection-retries'] = $values['soundcloud']['connection_retries'];
        $CC_CONFIG['soundcloud-connection-wait'] = $values['soundcloud']['time_between_retries'];
        
        if(isset($values['demo']['demo'])){
            $CC_CONFIG['demo'] = $values['demo']['demo'];
        } 
    }
}
