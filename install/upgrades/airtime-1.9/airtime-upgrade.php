<?php
/**
 * @package Airtime
 * @subpackage StorageServer
 * @copyright 2010 Sourcefabric O.P.S.
 * @license http://www.gnu.org/licenses/gpl.txt
 */

set_include_path(__DIR__.'/../../../airtime_mvc/library' . PATH_SEPARATOR . get_include_path());
require_once __DIR__.'/../../../airtime_mvc/application/configs/conf.php';
require_once(dirname(__FILE__).'/../../include/AirtimeInstall.php');
require_once(dirname(__FILE__).'/../../include/AirtimeIni.php');

AirtimeInstall::CreateZendPhpLogFile();

const CONF_DIR_BINARIES = "/usr/lib/airtime";
const CONF_FILE_AIRTIME = "/etc/airtime/airtime.conf";

function BypassMigrations($version)
{
    $appDir = __DIR__."/../../airtime_mvc";
    $dir = __DIR__;
    $command = "php $appDir/library/doctrine/migrations/doctrine-migrations.phar ".
                "--configuration=$dir/../../DoctrineMigrations/migrations.xml ".
                "--db-configuration=$appDir/library/doctrine/migrations/migrations-db.php ".
                "--no-interaction --add migrations:version $version";
    system($command);
}

function MigrateTablesToVersion($version)
{
    $appDir = __DIR__."/../../airtime_mvc";
    $dir = __DIR__;
    $command = "php $appDir/library/doctrine/migrations/doctrine-migrations.phar ".
                "--configuration=$dir/../../DoctrineMigrations/migrations.xml ".
                "--db-configuration=$appDir/library/doctrine/migrations/migrations-db.php ".
                "--no-interaction migrations:migrate $version";
    system($command);
}

function InstallAirtimePhpServerCode($phpDir)
{
    global $CC_CONFIG;

    $AIRTIME_SRC = realpath(__DIR__.'/../../../airtime_mvc');

    echo "* Installing PHP code to ".$phpDir.PHP_EOL;
    exec("mkdir -p ".$phpDir);
    exec("cp -R ".$AIRTIME_SRC."/* ".$phpDir);
}

function CopyUtils()
{
    $utilsSrc = __DIR__."/../../../utils";

    echo "* Installing binaries to ".CONF_DIR_BINARIES.PHP_EOL;
    exec("mkdir -p ".CONF_DIR_BINARIES);
    exec("cp -R ".$utilsSrc." ".CONF_DIR_BINARIES);
}

/* Removes pypo, media-monitor, show-recorder and utils from system. These will
   be reinstalled by the main airtime-upgrade script. */
function UninstallBinaries()
{
    echo "* Removing Airtime binaries from ".CONF_DIR_BINARIES.PHP_EOL;
    exec('rm -rf "'.CONF_DIR_BINARIES.'"');
}


function removeOldAirtimeImport(){
    exec('rm -f "/usr/lib/airtime/utils/airtime-import"');
    exec('rm -f "/usr/lib/airtime/utils/airtime-import.php"');
}

function updateAirtimeImportSymLink(){
    $dir = "/usr/lib/airtime/utils/airtime-import/airtime-import";
    exec("ln -s $dir /usr/bin/airtime-import");
}

/* In version 1.9.0 we have have switched from daemontools to more traditional
 * init.d daemon system. Let's remove all the daemontools files
 */

exec("/usr/bin/airtime-pypo-stop");
exec("/usr/bin/airtime-show-recorder-stop");

exec("svc -d /etc/service/pypo");
exec("svc -d /etc/service/pypo/log");
exec("svc -d /etc/service/pypo-liquidsoap");
exec("svc -d /etc/service/pypo-liquidsoap/log");
exec("svc -d /etc/service/recorder");
exec("svc -d /etc/service/recorder/log");

$pathnames = array("/usr/bin/airtime-pypo-start",
                "/usr/bin/airtime-pypo-stop",
                "/usr/bin/airtime-show-recorder-start",
                "/usr/bin/airtime-show-recorder-stop",
                "/usr/bin/airtime-media-monitor-start",
                "/usr/bin/airtime-media-monitor-stop",
                "/etc/service/pypo",
                "/etc/service/pypo-liquidsoap",
                "/etc/service/media-monitor",
                "/etc/service/recorder",
                "/var/log/airtime/pypo/main",
                "/var/log/airtime/pypo-liquidsoap/main",
                "/var/log/airtime/show-recorder/main"
                );

foreach ($pathnames as $pn){
    echo "Removing $pn\n";
    exec("rm -rf \"$pn\"");
}


$values = parse_ini_file(CONF_FILE_AIRTIME, true);
$phpDir = $values['general']['airtime_dir'];

InstallAirtimePhpServerCode($phpDir);

//update utils (/usr/lib/airtime) folder
UninstallBinaries();
CopyUtils();

removeOldAirtimeImport();
updateAirtimeImportSymLink();


//need to change database because old format had full path while new database has partial paths
//also need to add new column
