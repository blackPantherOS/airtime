<?php
/**
 * @package Airtime
 * @subpackage StorageServer
 * @copyright 2010 Sourcefabric O.P.S.
 * @license http://www.gnu.org/licenses/gpl.txt
 */

// Do not allow remote execution
$arr = array_diff_assoc($_SERVER, $_ENV);
if (isset($arr["DOCUMENT_ROOT"]) && ($arr["DOCUMENT_ROOT"] != "") ) {
    header("HTTP/1.1 400");
    header("Content-type: text/plain; charset=UTF-8");
    echo "400 Not executable\r\n";
    exit(1);
}

require_once(dirname(__FILE__).'/../application/configs/conf.php');
require_once(dirname(__FILE__).'/installInit.php');

echo "******************************** Install Begin *********************************".PHP_EOL;

checkIfRoot();
createAPIKey();
updateINIKeyValues('../build/build.properties', 'project.home', realpath(__dir__.'/../'));


echo PHP_EOL."*** Database Installation ***".PHP_EOL;

echo "* Creating Airtime Database User".PHP_EOL;
createAirtimeDatabaseUser();

echo "* Creating Airtime Database".PHP_EOL;
createAirtimeDatabase();


airtime_db_connect(true);

echo "* Install Postgresql Scripting Language".PHP_EOL;
installPostgresScriptingLanguage();

echo "* Creating Database Tables".PHP_EOL;
createAirtimeDatabaseTables();

echo "* Storage Directory Setup".PHP_EOL;
storageDirectorySetup($CC_CONFIG);

echo "* Setting Dir Permissions".PHP_EOL;
install_setDirPermissions($CC_CONFIG["storageDir"]);

echo "* Importing Sample Audio Clips".PHP_EOL;
system(__DIR__."/../utils/airtime-import --copy ../audio_samples/ > /dev/null");

echo PHP_EOL."*** Pypo Installation ***".PHP_EOL;
system("python ".__DIR__."/../pypo/install/pypo-install.py");


echo "******************************* Install Complete *******************************".PHP_EOL;

