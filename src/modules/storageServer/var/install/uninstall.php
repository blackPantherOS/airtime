<?php
/**
 * @package Campcaster
 * @subpackage StorageServer
 * @copyright 2010 Sourcefabric O.P.S.
 * @license http://www.gnu.org/licenses/gpl.txt
 */

// Do not allow remote execution.
$arr = array_diff_assoc($_SERVER, $_ENV);
if (isset($arr["DOCUMENT_ROOT"]) && ($arr["DOCUMENT_ROOT"] != "") ) {
    header("HTTP/1.1 400");
    header("Content-type: text/plain; charset=UTF-8");
    echo "400 Not executable\r\n";
    exit;
}


echo "***************************\n";
echo "* StorageServer Uninstall *\n";
echo "***************************\n";

require_once('../conf.php');
require_once('installInit.php');
campcaster_db_connect(false);
require_once('uninstallStorage.php');
if (!PEAR::isError($CC_DBC)) {
    require_once('uninstallMain.php');
}

echo "************************************\n";
echo "* StorageServer Uninstall Complete *\n";
echo "************************************\n";

?>