<?php
/**
 * @package Airtime
 * @subpackage StorageServer
 * @copyright 2010 Sourcefabric O.P.S.
 * @license http://www.gnu.org/licenses/gpl.txt
 */

//Pear classes.
set_include_path(__DIR__.'/../airtime_mvc/library/pear' . PATH_SEPARATOR . get_include_path());
require_once('DB.php');

if(exec("whoami") != "root"){
    echo "Must be root user.\n";
    exit(1);
}

global $CC_DBC, $CC_CONFIG;

$values = parse_ini_file('/etc/airtime/airtime.conf', true);

// Database config
$CC_CONFIG['dsn']['username'] = $values['database']['dbuser'];
$CC_CONFIG['dsn']['password'] = $values['database']['dbpass'];
$CC_CONFIG['dsn']['hostspec'] = $values['database']['host'];
$CC_CONFIG['dsn']['phptype'] = 'pgsql';
$CC_CONFIG['dsn']['database'] = $values['database']['dbname'];

$CC_DBC = DB::connect($CC_CONFIG['dsn'], FALSE);

if (PEAR::isError($CC_DBC)) {
    echo $CC_DBC->getMessage().PHP_EOL;
    echo $CC_DBC->getUserInfo().PHP_EOL;
    echo "Database connection problem.".PHP_EOL;
    echo "Check if database '{$CC_CONFIG['dsn']['database']}' exists".
        " with corresponding permissions.".PHP_EOL;
    exit(1);
} else {
    echo "* Connected to database".PHP_EOL;
    $CC_DBC->setFetchMode(DB_FETCHMODE_ASSOC);
}

$sql = "SELECT valstr FROM cc_pref WHERE keystr = 'system_version'";
$version = $CC_DBC->GetOne($sql);

if (PEAR::isError($version)) {
    $version = false;
}

if (!$version){

    $sql = "SELECT * FROM ".$p_name;
    $result = $CC_DBC->GetOne($sql);
    if (!PEAR::isError($result)) {
        $version = "1.7.0";
        echo "Airtime Version: ".$version." ".PHP_EOL;
    }
    else {
        $version = "1.6";
        echo "Airtime Version: ".$version." ".PHP_EOL;
    }
}

echo "******************************** Update Begin *********************************".PHP_EOL;

//convert strings like 1.9.0-devel to 1.9.0
$version = substr($version, 0, 5);

if (strcmp($version, "1.7.0") < 0){
    system("php ".__DIR__."/upgrades/airtime-1.7/airtime-upgrade.php");
}
if (strcmp($version, "1.8.0") < 0){
    system("php ".__DIR__."/upgrades/airtime-1.8/airtime-upgrade.php");
}
if (strcmp($version, "1.8.1") < 0){
    system("php ".__DIR__."/upgrades/airtime-1.8.1/airtime-upgrade.php");
}
if (strcmp($version, "1.8.2") < 0){
    system("php ".__DIR__."/upgrades/airtime-1.8.2/airtime-upgrade.php");
}
if (strcmp($version, "1.9.0") < 0){
    system("php ".__DIR__."/upgrades/airtime-1.9/airtime-upgrade.php");
}


//set the new version in the database.
$sql = "DELETE FROM cc_pref WHERE keystr = 'system_version'";
$CC_DBC->query($sql);
$sql = "INSERT INTO cc_pref (keystr, valstr) VALUES ('system_version', '1.9.0-devel')";
$CC_DBC->query($sql);


echo PHP_EOL."*** Updating Recorder ***".PHP_EOL;
system("python ".__DIR__."/../python_apps/show-recorder/install/recorder-install.py");

echo PHP_EOL."*** Updating Pypo ***".PHP_EOL;
system("python ".__DIR__."/../python_apps/pypo/install/pypo-install.py");

echo "******************************* Update Complete *******************************".PHP_EOL;


