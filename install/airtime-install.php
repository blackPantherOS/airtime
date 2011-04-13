<?php
/**
 * @package Airtime
 * @copyright 2010 Sourcefabric O.P.S.
 * @license http://www.gnu.org/licenses/gpl.txt
 */

echo "******************************** Install Begin *********************************".PHP_EOL;

require_once(dirname(__FILE__).'/include/AirtimeIni.php');
require_once(dirname(__FILE__).'/include/AirtimeInstall.php');

AirtimeInstall::ExitIfNotRoot();
AirtimeIni::CreateIniFile();
AirtimeIni::UpdateIniFiles();

require_once(dirname(__FILE__).'/../application/configs/conf.php');

echo PHP_EOL."*** Installing Airtime ".AIRTIME_VERSION." ***".PHP_EOL;

echo PHP_EOL."*** Database Installation ***".PHP_EOL;

echo "* Creating Airtime database user".PHP_EOL;
AirtimeInstall::CreateDatabaseUser();

echo "* Creating Airtime database".PHP_EOL;
AirtimeInstall::CreateDatabase();

AirtimeInstall::DbConnect(true);

echo "* Installing Postgresql scripting language".PHP_EOL;
AirtimeInstall::InstallPostgresScriptingLanguage();

echo "* Creating database tables".PHP_EOL;
AirtimeInstall::CreateDatabaseTables();

echo "* Storage directory setup".PHP_EOL;
AirtimeInstall::SetupStorageDirectory($CC_CONFIG);

echo "* Giving Apache permission to access the storage directory".PHP_EOL;
AirtimeInstall::ChangeDirOwnerToWebserver($CC_CONFIG["storageDir"]);

echo "* Creating /usr/bin symlinks".PHP_EOL;
AirtimeInstall::CreateSymlinks($CC_CONFIG["storageDir"]);

echo PHP_EOL."*** Pypo Installation ***".PHP_EOL;
system("python ".__DIR__."/../python_apps/pypo/install/pypo-install.py");

echo PHP_EOL."*** Recorder Installation ***".PHP_EOL;
system("python ".__DIR__."/../python_apps/show-recorder/install/recorder-install.py");


echo "******************************* Install Complete *******************************".PHP_EOL;

