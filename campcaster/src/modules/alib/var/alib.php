<?php
require_once 'subj.php';

define('USE_ALIB_CLASSES', TRUE);
define('ALIBERR_NOTLOGGED', 30);
define('ALIBERR_NOTEXISTS', 31);

/**
 * Alib class
 *
 * Authentication/authorization class
 *
 * @author Tomas Hlava <th@red2head.com>
 * @author Paul Baranowski <paul@paulbaranowski.org>
 * @version $Revision$
 * @package Campcaster
 * @subpackage Alib
 * @copyright 2006 MDLF, Inc.
 * @license http://www.gnu.org/licenses/gpl.txt
 * @link http://www.campware.org
 */
class Alib extends Subjects {
	/**
	 * The name of a database table.
	 *
	 * @var string
	 */
    public $permTable;

    /**
     * The name of a database table.
     *
     * @var string
     */
    public $sessTable;

    //var $login = NULL;
    //var $userid = NULL;
    public $sessid = NULL;

    /**
     * Constructor
     *
     * @param DB $dbc
     * @param array $config
     */
    public function __construct(&$dbc, $config)
    {
        parent::__construct($dbc, $config);
        $this->permTable = $config['tblNamePrefix'].'perms';
        $this->sessTable = $config['tblNamePrefix'].'sess';
    } // constructor


    /* ======================================================= public methods */

    /* ----------------------------------------------- session/authentication */

    /**
     * Authenticate and create session
     *
     * @param string $login
     * @param string $pass
     * @return boolean|sessionId|PEAR_Error
     */
    public function login($login, $pass)
    {
        if (FALSE === $this->authenticate($login, $pass)) {
            $this->setTimeStamp($login, TRUE);
            return FALSE;
        }
        $sessid = $this->_createSessid();
        if (PEAR::isError($sessid)) {
            return $sessid;
        }
        $userid = $this->getSubjId($login);
        $sql = "INSERT INTO {$this->sessTable} (sessid, userid, login, ts)
            VALUES('$sessid', '$userid', '$login', now())";
        $r = $this->dbc->query($sql);
        if (PEAR::isError($r)) {
            return $r;
        }
        //$this->login = $login;
        //$this->userid = $userid;
        //$this->sessid = $sessid;
        $this->setTimeStamp($login, FALSE);
        return $sessid;
    } // fn login


    /**
     * Logout and destroy session
     *
     * @param string $sessid
     * @return true|PEAR_Error
     */
    public function logout($sessid)
    {
        $ct = $this->checkAuthToken($sessid);
        if ($ct === FALSE) {
            return PEAR::raiseError('Alib::logout: not logged ($ct)',
                ALIBERR_NOTLOGGED, PEAR_ERROR_RETURN);
        } elseif (PEAR::isError($ct)) {
            return $ct;
        } else {
            $sql = "DELETE FROM {$this->sessTable}
                WHERE sessid='$sessid'";
            $r = $this->dbc->query($sql);
            if (PEAR::isError($r)) {
                return $r;
            }
            //$this->login = NULL;
            //$this->userid = NULL;
            //$this->sessid = NULL;
            return TRUE;
        }
    } // fn logout


    /**
     * Return true if the token is valid
     *
     * @param string $sessid
     * @return boolean|PEAR_Error
     */
    private function checkAuthToken($sessid)
    {
        $sql = "SELECT count(*) as cnt FROM {$this->sessTable}
            WHERE sessid='$sessid'";
        $c = $this->dbc->getOne($sql);
        return ($c == 1 ? TRUE : (PEAR::isError($c) ? $c : FALSE ));
    } //fn checkAuthToken


    /**
     * Set valid token in alib object
     *
     * @param string $sessid
     * @return TRUE|PEAR_Error
     */
//    public function setAuthToken($sessid)
//    {
//        $r = $this->checkAuthToken($sessid);
//        if (PEAR::isError($r)) {
//        	return $r;
//        }
//        if (!$r) {
//            return PEAR::raiseError("ALib::setAuthToken: invalid token ($sessid)");
//        }
//        //$this->sessid = $sessid;
//        return TRUE;
//    } // fn setAuthToken


    /* -------------------------------------------------------- authorization */
    /**
     * Insert permission record
     *
     * @param int $sid
     * 		local user/group id
     * @param string $action
     * @param int $oid
     * 		local object id
     * @param string $type
     * 		'A'|'D' (allow/deny)
     * @return int
     * 		local permission id
     */
    public function addPerm($sid, $action, $oid, $type='A')
    {
        $permid = $this->dbc->nextId("{$this->permTable}_id_seq");
        $sql = "INSERT INTO {$this->permTable} (permid, subj, action, obj, type)
            VALUES ($permid, $sid, '$action', $oid, '$type')";
        $r = $this->dbc->query($sql);
        if (PEAR::isError($r)) {
            return($r);
        }
        return $permid;
    } // fn addPerm


    /**
     * Remove permission record
     *
     * @param int $permid
     * 		local permission id
     * @param int $subj
     * 		local user/group id
     * @param int $obj
     * 		local object id
     * @return boolean|PEAR_Error
     */
    public function removePerm($permid=NULL, $subj=NULL, $obj=NULL)
    {
        $ca = array();
        if ($permid) {
            $ca[] = "permid=$permid";
        }
        if ($subj) {
            $ca[] = "subj=$subj";
        }
        if ($obj) {
            $ca[] = "obj=$obj";
        }
        $cond = join(" AND ", $ca);
        if (!$cond) {
            return TRUE;
        }
        $sql = "DELETE FROM {$this->permTable} WHERE $cond";
        return $this->dbc->query($sql);
    } // fn removePerm


    /**
     * Return object related with permission record
     *
     * @param int $permid
     * 		local permission id
     * @return int
     * 		local object id
     */
    protected function _getPermOid($permid)
    {
        $sql = "SELECT obj FROM {$this->permTable} WHERE permid=$permid";
        $res = $this->dbc->getOne($sql);
        return $res;
    } // fn _getPermOid


    /**
     * Check if specified subject have permission to specified action
     * on specified object
     *
     * Look for sequence of corresponding permissions and order it by
     * relevence, then test the most relevant for result.
     * High relevence have direct permission (directly for specified subject
     * and object. Relevance order is done by level distance in the object
     * tree, level distance in subjects (user/group system).
     * Similar way is used for permissions related to object classes.
     * But class-related permissions have lower priority then
     * object-tree-related.
     * Support for object classes can be disabled by USE_ALIB_CLASSES const.
     *
     * @param int $sid
     * 		subject id (user or group id)
     * @param string $action
     * 		from set defined in config
     * @param int $oid
     * 		object id, optional (default: root node)
     * @return mixed
     * 		boolean/err
     */
    public function checkPerm($sid, $action, $oid=NULL)
    {
        if (!is_numeric($sid)) {
            return FALSE;
        }
        if (is_null($oid) or $oid=='') {
            $oid = $this->getRootNode();
        }
        if (PEAR::isError($oid)) {
            return $oid;
        }
        if (!is_numeric($oid)) {
            return FALSE;
        }
        // query construction
        //      shortcuts:
        //          p: permTable,
        //          s: subjTable, m smembTable,
        //          t: treeTable ts: structTable,
        //          c: classTable, cm: cmembTable
        // main query elements:
        $q_flds = "m.level , p.subj, s.login, action, p.type, p.obj";
        $q_from = "{$this->permTable} p ";
        // joins for solving users/groups:
        $q_join = "LEFT JOIN {$this->subjTable} s ON s.id=p.subj ";
        $q_join .= "LEFT JOIN {$this->smembTable} m ON m.gid=p.subj ";
        $q_cond = "p.action in('_all', '$action') AND
            (s.id=$sid OR m.uid=$sid) ";
        // coalesce -1 for higher priority of nongroup rows:
        // action DESC order for lower priority of '_all':
        $q_ordb = "ORDER BY coalesce(m.level,-1), action DESC, p.type DESC";
        $q_flds0 = $q_flds;
        $q_from0 = $q_from;
        $q_join0 = $q_join;
        $q_cond0 = $q_cond;
        $q_ordb0 = $q_ordb;
        //  joins for solving object tree:
        $q_flds .= ", t.name, ts.level as tlevel";
        $q_join .= "LEFT JOIN {$this->treeTable} t ON t.id=p.obj ";
        $q_join .= "LEFT JOIN {$this->structTable} ts ON ts.parid=p.obj ";
        $q_cond .= " AND (t.id=$oid OR ts.objid=$oid)";
        // action DESC order is hack for lower priority of '_all':
        $q_ordb = "ORDER BY coalesce(ts.level,0), m.level, action DESC, p.type DESC";
        // query by tree:
        $query1 = "SELECT $q_flds FROM $q_from $q_join WHERE $q_cond $q_ordb";
        $r1 = $this->dbc->getAll($query1);
        if (PEAR::isError($r1)) {
            return($r1);
        }
        //  if there is row with type='A' on the top => permit
        $AllowedByTree =
            (is_array($r1) && count($r1)>0 && $r1[0]['type']=='A');
        $DeniedByTree =
            (is_array($r1) && count($r1)>0 && $r1[0]['type']=='D');

        if (!USE_ALIB_CLASSES) {
            return $AllowedbyTree;
        }

        // joins for solving object classes:
        $q_flds = $q_flds0.", c.cname ";
        $q_join = $q_join0."LEFT JOIN {$this->classTable} c ON c.id=p.obj ";
        $q_join .= "LEFT JOIN {$this->cmembTable} cm ON cm.cid=p.obj ";
        $q_cond = $q_cond0." AND (c.id=$oid OR cm.objid=$oid)";
        $q_ordb = $q_ordb0;
        // query by class:
        $query2 = "SELECT $q_flds FROM $q_from $q_join WHERE $q_cond $q_ordb";
        $r2 = $this->dbc->getAll($query2);
        if (PEAR::isError($r2)) {
            return($r2);
        }
        $AllowedByClass =
            (is_array($r2) && count($r2)>0 && $r2[0]['type']=='A');
        // not used now:
        // $DeniedByClass =
        //    (is_array($r2) && count($r2)>0 && $r2[0]['type']=='D');
        $res = ($AllowedByTree || (!$DeniedByTree && $AllowedByClass));
#        echo"<pre>\nsid=$sid, action=$action, oid=$oid\n"; var_dump($r1); echo"\n---\n$query1\n---\n\n"; var_dump($r2); echo"\n---\n$query2\n---\n\n"; exit;
        return $res;
    } // fn checkPerm


    /* ---------------------------------------------------------- object tree */

    /**
     * Remove all permissions on object and then remove object itself
     *
     * @param int $id
     * @return void|PEAR_Error
     */
    public function removeObj($id)
    {
        $r = $this->removePerm(NULL, NULL, $id);
        if (PEAR::isError($r)) {
            return $r;
        }
        return parent::removeObj($id);
    } // fn removeObj

    /* --------------------------------------------------------- users/groups */

    /**
     * Remove all permissions of subject and then remove subject itself
     *
     * @param string $login
     * @return void|PEAR_Error
     */
    public function removeSubj($login)
    {
        $uid = $this->getSubjId($login);
        if (PEAR::isError($uid)) {
            return $uid;
        }
        if (is_null($uid)){
            return $this->dbc->raiseError("Alib::removeSubj: Subj not found ($login)",
                ALIBERR_NOTEXISTS,  PEAR_ERROR_RETURN);
        }
        $r = $this->removePerm(NULL, $uid);
        if (PEAR::isError($r)) {
            return $r;
        }
        return parent::removeSubj($login, $uid);
    } // fn removeSubj


    /* ------------------------------------------------------------- sessions */
    /**
     * Get login from session id (token)
     *
     * @param string $sessid
     * @return string|PEAR_Error
     */
    public function getSessLogin($sessid)
    {
        $sql = "SELECT login FROM {$this->sessTable} WHERE sessid='$sessid'";
        $r = $this->dbc->getOne($sql);
        if (PEAR::isError($r)) {
            return $r;
        }
        if (is_null($r)){
            return PEAR::raiseError("Alib::getSessLogin:".
                " invalid session id ($sessid)",
                ALIBERR_NOTEXISTS,  PEAR_ERROR_RETURN);
        }
        return $r;
    } // fn getSessLogin


    /**
     * Get user id from session id.
     *
     * @param string $sessid
     * @return int|PEAR_Error
     */
    public function getSessUserId($sessid)
    {
        $sql = "SELECT userid FROM {$this->sessTable} WHERE sessid='$sessid'";
        $r = $this->dbc->getOne($sql);
        if (PEAR::isError($r)) {
            return $r;
        }
        if (is_null($r)){
            return PEAR::raiseError("Alib::getSessUserId:".
                " invalid session id ($sessid)",
                ALIBERR_NOTEXISTS,  PEAR_ERROR_RETURN);
        }
        return $r;
    } // fn getSessUserId


    /* --------------------------------------------------------- info methods */
    /**
     * Get all permissions on object.
     *
     * @param int $id
     * @return array|null|PEAR_Error
     */
    public function getObjPerms($id)
    {
        $sql = "SELECT s.login, p.* FROM {$this->permTable} p, {$this->subjTable} s
            WHERE s.id=p.subj AND p.obj=$id";
        return $this->dbc->getAll($sql);
    } // fn getObjPerms


    /**
     * Get all permissions of subject.
     *
     * @param int $sid
     * @return array
     */
    public function getSubjPerms($sid)
    {
        $sql = "
            SELECT t.name, t.type as otype , p.*
            FROM {$this->permTable} p, {$this->treeTable} t
            WHERE t.id=p.obj AND p.subj=$sid";
        $a1 = $this->dbc->getAll($sql);
        if (PEAR::isError($a1)) {
            return $a1;
        }
        $sql2 = "
            SELECT c.cname as name, 'C'as otype, p.*
            FROM {$this->permTable} p, {$this->classTable} c
            WHERE c.id=p.obj AND p.subj=$sid";
        $a2 = $this->dbc->getAll($sql2);
        if (PEAR::isError($a2)) {
            return $a2;
        }
        return array_merge($a1, $a2);
    } // fn getSubjPerms


    /* ------------------------ info methods related to application structure */
    /* (this part should be added/rewritten to allow defining/modifying/using
     * application structure)
     * (only very simple structure definition - in $config - supported now)
     */

    /**
     * Get all actions
     *
     * @return array
     */
    public function getAllActions()
    {
        return $this->config['allActions'];
    } // fn getAllActions


    /**
     * Get all allowed actions on specified object type.
     *
     * @param string $type
     * @return array
     */
    public function getAllowedActions($type)
    {
        return $this->config['allowedActions'][$type];
    } // fn getAllowedActions


    /* ====================================================== private methods */

    /**
     * Create new session id.  Return the new session ID.
     *
     * @return string
     */
    private function _createSessid()
    {
        for ($c=1; $c>0;){
            $sessid = md5(uniqid(rand()));
            $sql = "SELECT count(*) FROM {$this->sessTable}
                WHERE sessid='$sessid'";
            $c = $this->dbc->getOne($sql);
            if (PEAR::isError($c)) {
                return $c;
            }
        }
        return $sessid;
    } // fn _createSessid


    /* =============================================== test and debug methods */

    /**
     * Dump all permissions for debug
     *
     * @param string $indstr
     * 		indentation string
     * @param string $ind
     * 		actual indentation
     * @return string
     */
    public function dumpPerms($indstr='    ', $ind='')
    {
        $arr = $this->dbc->getAll("
            SELECT s.login, p.action, p.type
            FROM {$this->permTable} p, {$this->subjTable} s
            WHERE s.id=p.subj
            ORDER BY p.permid
        ");
        if (PEAR::isError($arr)) {
            return $arr;
        }
        $r = $ind.join(', ', array_map(create_function('$v',
                'return "{$v[\'login\']}/{$v[\'action\']}/{$v[\'type\']}";'
            ),
            $arr
        ))."\n";
        return $r;
    } // fn dumpPerms


    /**
     * Delete everything form the permission table and session table.
     *
     * @return void
     */
    public function deleteData()
    {
        $this->dbc->query("DELETE FROM {$this->permTable}");
        $this->dbc->query("DELETE FROM {$this->sessTable}");
        parent::deleteData();
    } // fn deleteData


    /**
     * Insert test permissions
     *
     * @return array
     */
    public function testData()
    {
        parent::testData();
        $t =& $this->tdata['tree'];
        $c =& $this->tdata['classes'];
        $s =& $this->tdata['subjects'];
        $this->dbc->setErrorHandling(PEAR_ERROR_PRINT);
        $perms = array(
            array($s['root'], '_all', $t['root'], 'A'),
            array($s['test1'], '_all', $t['pa'], 'A'),
            array($s['test1'], 'read', $t['s2b'], 'D'),
            array($s['test2'], 'addChilds', $t['pa'], 'D'),
            array($s['test2'], 'read', $t['i2'], 'A'),
            array($s['test2'], 'edit', $t['s1a'], 'A'),
            array($s['test1'], 'addChilds', $t['s2a'], 'D'),
            array($s['test1'], 'addChilds', $t['s2c'], 'D'),
            array($s['gr2'], 'addChilds', $t['i2'], 'A'),
            array($s['test3'], '_all', $t['t1'], 'D'),
        );
        if (USE_ALIB_CLASSES){
            $perms[] = array($s['test3'], 'read', $c['cl_sa'], 'D');
            $perms[] = array($s['test4'], 'editPerms', $c['cl2'], 'A');
        }
        foreach ($perms as $p){
            $o[] = $r = $this->addPerm($p[0], $p[1], $p[2], $p[3]);
            if (PEAR::isError($r)) {
                return $r;
            }
        }
        $this->tdata['perms'] = $o;
    } // fn testData


    /**
     * Make basic test
     *
     * @return boolean|PEAR_Error
     */
    public function test()
    {
        if (PEAR::isError($p = parent::test())) {
            return $p;
        }
        $this->deleteData();
        $r = $this->testData();
        if (PEAR::isError($r)) {
            return $r;
        }
        $this->test_correct = "root/_all/A, test1/_all/A, test1/read/D,".
            " test2/addChilds/D, test2/read/A, test2/edit/A,".
            " test1/addChilds/D, test1/addChilds/D, gr2/addChilds/A,".
            " test3/_all/D";
        if (USE_ALIB_CLASSES){
            $this->test_correct .= ", test3/read/D, test4/editPerms/A";
        }
        $this->test_correct .= "\nno, yes\n";
        $r = $this->dumpPerms();
        if (PEAR::isError($r)) {
            return $r;
        }
        $this->test_dump = $r.
            ($this->checkPerm(
                $this->tdata['subjects']['test1'], 'read',
                $this->tdata['tree']['t1']
            )? 'yes':'no').", ".
            ($this->checkPerm(
                $this->tdata['subjects']['test1'], 'addChilds',
                $this->tdata['tree']['i2']
            )? 'yes':'no')."\n"
        ;
        $this->removePerm($this->tdata['perms'][1]);
        $this->removePerm($this->tdata['perms'][3]);
        $this->test_correct .= "root/_all/A, test1/read/D,".
            " test2/read/A, test2/edit/A,".
            " test1/addChilds/D, test1/addChilds/D, gr2/addChilds/A,".
            " test3/_all/D";
        if (USE_ALIB_CLASSES){
            $this->test_correct .= ", test3/read/D, test4/editPerms/A";
        }
        $this->test_correct .= "\n";
        $this->test_dump .= $this->dumpPerms();
        $this->deleteData();
        if ($this->test_dump==$this->test_correct) {
            $this->test_log.="alib: OK\n"; return TRUE;
        } else {
            return PEAR::raiseError('Alib::test', 1, PEAR_ERROR_DIE, '%s'.
            "<pre>\ncorrect:\n{$this->test_correct}\n".
            "dump:\n{$this->test_dump}\n</pre>\n");
        }
    } // fn test


    /**
     * Create tables + initialize
     *
     * @return void
     */
    public function install()
    {
        parent::install();
        $this->dbc->query("CREATE TABLE {$this->permTable} (
            permid int not null PRIMARY KEY,
            subj int REFERENCES {$this->subjTable} ON DELETE CASCADE,
            action varchar(20),
            obj int,
            type char(1)
        )");
        $this->dbc->query("CREATE UNIQUE INDEX {$this->permTable}_permid_idx
            ON {$this->permTable} (permid)");
        $this->dbc->query("CREATE INDEX {$this->permTable}_subj_obj_idx
            ON {$this->permTable} (subj, obj)");
        $this->dbc->query("CREATE UNIQUE INDEX {$this->permTable}_all_idx
            ON {$this->permTable} (subj, action, obj)");
        $this->dbc->createSequence("{$this->permTable}_id_seq");

        $this->dbc->query("CREATE TABLE {$this->sessTable} (
            sessid char(32) not null PRIMARY KEY,
            userid int REFERENCES {$this->subjTable} ON DELETE CASCADE,
            login varchar(255),
            ts timestamp
        )");
        $this->dbc->query("CREATE UNIQUE INDEX {$this->sessTable}_sessid_idx
            ON {$this->sessTable} (sessid)");
        $this->dbc->query("CREATE INDEX {$this->sessTable}_userid_idx
            ON {$this->sessTable} (userid)");
        $this->dbc->query("CREATE INDEX {$this->sessTable}_login_idx
            ON {$this->sessTable} (login)");
    } // fn install


    /**
     * Drop tables etc.
     *
     * @return void
     */
    public function uninstall()
    {
        $this->dbc->query("DROP TABLE {$this->permTable}");
        $this->dbc->dropSequence("{$this->permTable}_id_seq");
        $this->dbc->query("DROP TABLE {$this->sessTable}");
        parent::uninstall();
    } // fn uninstall

} // class Alib
?>