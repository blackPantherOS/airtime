<?php
define('ALIBERR_NOTGR', 20);
define('ALIBERR_BADSMEMB', 21);

/**
 * Subj class
 *
 * users + groups
 * with "linearized recursive membership" ;)
 *   (allow adding users to groups or groups to groups)
 *
 * @package Airtime
 * @subpackage Alib
 * @copyright 2010 Sourcefabric O.P.S.
 * @license http://www.gnu.org/licenses/gpl.txt
 */
class Application_Model_Subjects
{
    /* ======================================================= public methods */

    public static function increaseLoginAttempts($login)
    {
        $con = Propel::getConnection();
        //$sql = "UPDATE cc_subjs SET login_attempts = login_attempts+1"
        //    ." WHERE login='$login'";
        //$res = $con->exec($sql);
        
        $sql = "UPDATE cc_subjs SET login_attempts = login_attempts + 1 WHERE login = :login";
        
        $paramMap[':login'] = $login;
        
        Application_Common_Database::prepareAndExecute($sql, $paramMap, 'execute');

        return (intval($res) > 0);
    }

    public static function resetLoginAttempts($login)
    {
        $con = Propel::getConnection();
        $sql = "UPDATE cc_subjs SET login_attempts = '0'"
            ." WHERE login='$login'";
        $res = $con->exec($sql);

        return true;
    }

    public static function getLoginAttempts($login)
    {
        $con = Propel::getConnection();
        $sql = "SELECT login_attempts FROM cc_subjs WHERE login='$login'";
        $res = $con->query($sql)->fetchColumn(0);

        return ($res !== false) ? $res : 0;
    }

} // class Subjects
