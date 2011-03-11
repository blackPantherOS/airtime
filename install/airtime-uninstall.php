<?php
/**
 * @package Airtime
 * @copyright 2010 Sourcefabric O.P.S.
 * @license http://www.gnu.org/licenses/gpl.txt
 */

// Do not allow remote execution.
$arr = array_diff_assoc($_SERVER, $_ENV);
if (isset($arr["DOCUMENT_ROOT"]) && ($arr["DOCUMENT_ROOT"] != "") ) {
    header("HTTP/1.1 400");
    header("Content-type: text/plain; charset=UTF-8");
    echo "400 Not executable".PHP_EOL;
    exit;
}

require_once(dirname(__FILE__).'/../application/configs/conf.php');
require_once(dirname(__FILE__).'/installInit.php');

// Need to check that we are superuser before running this.
checkIfRoot();


echo "******************************* Uninstall Begin ********************************".PHP_EOL;
//------------------------------------------------------------------------
// Delete the database
// Note: Do not put a call to airtime_db_connect()
// before this function, even if you called $CC_DBC->disconnect(), there will
// still be a connection to the database and you wont be able to delete it.
//------------------------------------------------------------------------
echo " * Dropping the database '".$CC_CONFIG['dsn']['database']."'...".PHP_EOL;
$command = "sudo -u postgres dropdb {$CC_CONFIG['dsn']['database']} 2> /dev/null";
@exec($command, $output, $dbDeleteFailed);

//------------------------------------------------------------------------
// Delete DB tables
// We do this if dropping the database fails above.
//------------------------------------------------------------------------
if ($dbDeleteFailed) {
  echo " * Couldn't delete the database, so deleting all the DB tables...".PHP_EOL;
  airtime_db_connect(true);

  if (!PEAR::isError($CC_DBC)) {
      $sql = "select * from pg_tables where tableowner = 'airtime'";
      $rows = airtime_get_query($sql);

      foreach ($rows as $row){
          $tablename = $row["tablename"];
          echo "   * Removing database table $tablename...";

          if (airtime_db_table_exists($tablename)){
              $sql = "DROP TABLE $tablename CASCADE";
              airtime_install_query($sql, false);
                  
              $CC_DBC->dropSequence($tablename."_id");
          }
          echo "done.".PHP_EOL;
      }
  }
}

//------------------------------------------------------------------------
// Delete the user
//------------------------------------------------------------------------
echo " * Deleting database user '{$CC_CONFIG['dsn']['username']}'...".PHP_EOL;
$command = "sudo -u postgres psql postgres --command \"DROP USER {$CC_CONFIG['dsn']['username']}\" 2> /dev/null";
@exec($command, $output, $results);
if ($results == 0) {
  echo "   * User '{$CC_CONFIG['dsn']['username']}' deleted.".PHP_EOL;
} else {
  echo "   * Nothing to delete..".PHP_EOL;
}


//------------------------------------------------------------------------
// Delete files
//------------------------------------------------------------------------
airtime_uninstall_delete_files($CC_CONFIG['storageDir']);


$command = "python ".__DIR__."/../pypo/install/pypo-uninstall.py";
system($command);
echo "****************************** Uninstall Complete ******************************".PHP_EOL;

