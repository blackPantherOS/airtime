<?php

define('UTYPE_HOST', 'H');
define('UTYPE_ADMIN', 'A');
define('UTYPE_GUEST', 'G');
define('UTYPE_PROGRAM_MANAGER', 'P');

class Application_Model_User
{
    private $_userInstance;

    public function __construct($userId)
    {
        if (empty($userId)) {
            $this->_userInstance = $this->createUser();
        } else {
            $this->_userInstance = CcSubjsQuery::create()->findPK($userId);

            if (is_null($this->_userInstance)) {
                throw new Exception();
            }
        }
    }

    public function getId()
    {
        return $this->_userInstance->getDbId();
    }

    public function isGuest()
    {
        return $this->getType() == UTYPE_GUEST;
    }

    public function isHostOfShow($showId)
    {
        $userId = $this->_userInstance->getDbId();

        return CcShowHostsQuery::create()
            ->filterByDbShow($showId)
            ->filterByDbHost($userId)->count() > 0;
    }

    public function isHost()
    {
        return $this->isUserType(UTYPE_HOST);
    }

    public function isPM()
    {
        return $this->isUserType(UTYPE_PROGRAM_MANAGER);
    }

    public function isAdmin()
    {
        return $this->isUserType(UTYPE_ADMIN);
    }

    public function canSchedule($p_showId)
    {
        $type = $this->getType();
        $result = false;

        if ($type === UTYPE_ADMIN ||
            $type === UTYPE_PROGRAM_MANAGER ||
            CcShowHostsQuery::create()->filterByDbShow($p_showId)->filterByDbHost($this->getId())->count() > 0) {
            $result = true;
        }

        return $result;
    }

    public function isUserType($type)
    {
        if (is_array($type)) {
            $result = false;
            foreach ($type as $t) {
                switch ($t) {
                    case UTYPE_ADMIN:
                        $result = $this->_userInstance->getDbType() === 'A';
                        break;
                    case UTYPE_HOST:
                        $result = $this->_userInstance->getDbType() === 'H';
                        break;
                    case UTYPE_PROGRAM_MANAGER:
                        $result = $this->_userInstance->getDbType() === 'P';
                        break;
                }
                if ($result) {
                    return $result;
                }
            }
        } else {
            switch ($type) {
                case UTYPE_ADMIN:
                    return $this->_userInstance->getDbType() === 'A';
                case UTYPE_HOST:
                    return $this->_userInstance->getDbId() === 'H';
                case UTYPE_PROGRAM_MANAGER:
                    return $this->_userInstance->getDbType() === 'P';
            }
        }
    }

    public function setLogin($login)
    {
        $user = $this->_userInstance;
        $user->setDbLogin($login);
    }

    public function setPassword($password)
    {
        $user = $this->_userInstance;
        $user->setDbPass(md5($password));
    }

    public function setFirstName($firstName)
    {
        $user = $this->_userInstance;
        $user->setDbFirstName($firstName);
    }

    public function setLastName($lastName)
    {
        $user = $this->_userInstance;
        $user->setDbLastName($lastName);
    }

    public function setType($type)
    {
        $user = $this->_userInstance;
        $user->setDbType($type);
    }

    public function setEmail($email)
    {
        $user = $this->_userInstance;
        $user->setDbEmail(strtolower($email));
    }

    public function setCellPhone($cellPhone)
    {
        $user = $this->_userInstance;
        $user->setDbCellPhone($cellPhone);
    }

    public function setSkype($skype)
    {
        $user = $this->_userInstance;
        $user->setDbSkypeContact($skype);
    }

    public function setJabber($jabber)
    {
        $user = $this->_userInstance;
        $user->setDbJabberContact($jabber);
    }

    public function getLogin()
    {
        $user = $this->_userInstance;

        return $user->getDbLogin();
    }

    public function getPassword()
    {
        $user = $this->_userInstance;

        return $user->getDbPass();
    }

    public function getFirstName()
    {
        $user = $this->_userInstance;

        return $user->getDbFirstName();
    }

    public function getLastName()
    {
        $user = $this->_userInstance;

        return $user->getDbLastName();
    }

    public function getType()
    {
        $user = $this->_userInstance;

        return $user->getDbType();
    }

    public function getEmail()
    {
        $user = $this->_userInstance;

        return $user->getDbEmail();
    }

    public function getCellPhone()
    {
        $user = $this->_userInstance;

        return $user->getDbCellPhone();
    }

    public function getSkype()
    {
        $user = $this->_userInstance;

        return $user->getDbSkypeContact();
    }

    public function getJabber()
    {
        $user = $this->_userInstance;

        return $user->getDbJabberContact();

    }

    public function save()
    {
        $this->_userInstance->save();
    }

    public function delete()
    {
        if (!$this->_userInstance->isDeleted()) {
            $this->_userInstance->delete();
        }
    }
    public function getOwnedFiles() 
    {
        $user = $this->_userInstance;
        // do we need a find call at the end here?
        return $user->getCcFilessRelatedByDbOwnerId();
    }

    public function donateFilesTo($user)
    {
        $my_files = $this->getOwnedFiles();
        foreach ($my_files as $file) {
            $file->reassignTo($user);
        }
    }

    public function deleteAllFiles()
    {
        $my_files = $this->getOwnedFiles();
        foreach ($files as $file) {
            $file->delete();
        }
    }

    private function createUser()
    {
        $user = new CcSubjs();

        return $user;
    }

    public static function getUsersOfType($type)
    {
        return CcSubjsQuery::create()->filterByDbType($type)->find();
    }
    public static function getFirstAdminId()
    {
        $admins = Application_Model_User::getUsersOfType('A');
        if (count($admins) > 0) { // found admin => pick first one

            return $admins[0]->getDbId();
        } else {
            Logging::warn("Warning. no admins found in database");

            return null;
        }
    }
    public static function getUsers(array $type, $search=null)
    {
        $con     = Propel::getConnection();

        $sql_gen = "SELECT login AS value, login AS label, id as index FROM cc_subjs ";
        $sql     = $sql_gen;

        $type = array_map( function($t) {
            return "type = '{$t}'";
        }, $type);

        $sql_type = join(" OR ", $type);

        $sql      = $sql_gen ." WHERE (". $sql_type.") ";

        if (!is_null($search)) {
            $like = "login ILIKE '%{$search}%'";

            $sql  = $sql . " AND ".$like;
        }

        $sql = $sql ." ORDER BY login";

        return $con->query($sql)->fetchAll();;
    }

    public static function getUserCount($type=null)
    {
        $con = Propel::getConnection();
        $sql = '';
        $sql_gen = "SELECT count(*) AS cnt FROM cc_subjs ";

        if (!isset($type)) {
            $sql = $sql_gen;
        } else {
            if (is_array($type)) {
                for ($i=0; $i<count($type); $i++) {
                    $type[$i] = "type = '{$type[$i]}'";
                }
                $sql_type = join(" OR ", $type);
            } else {
                $sql_type = "type = {$type}";
            }

            $sql = $sql_gen ." WHERE (". $sql_type.") ";
        }

        $query = $con->query($sql)->fetchColumn(0);

        return ($query !== false) ? $query : null;
    }

    public static function getHosts($search=null)
    {
        return Application_Model_User::getUsers(array('H'), $search);
    }

    public static function getUsersDataTablesInfo($datatables)
    {

        $con = Propel::getConnection(CcSubjsPeer::DATABASE_NAME);

        $displayColumns = array("id", "login", "first_name", "last_name", "type");
        $fromTable = "cc_subjs";

        // get current user
        $username = "";
        $auth = Zend_Auth::getInstance();

        if ($auth->hasIdentity()) {
            $username = $auth->getIdentity()->login;
        }

        $res = Application_Model_Datatables::findEntries($con, $displayColumns, $fromTable, $datatables);

        // mark record which is for the current user
        foreach ($res['aaData'] as &$record) {
            if ($record['login'] == $username) {
                $record['delete'] = "self";
            } else {
                $record['delete'] = "";
            }
        }

        return $res;
    }

    public static function getUserData($id)
    {
        $con = Propel::getConnection();

        $sql = "SELECT login, first_name, last_name, type, id, email, cell_phone, skype_contact, jabber_contact"
        ." FROM cc_subjs"
        ." WHERE id = $id";

        return $con->query($sql)->fetch();
    }

    public static function getCurrentUser()
    {
        $userinfo = Zend_Auth::getInstance()->getStorage()->read();
        if (is_null($userinfo)) {
            return null;
        }
        try {
            return new self($userinfo->id);
        } catch (Exception $e) {
            //we get here if $userinfo->id is defined, but doesn't exist
            //in the database anymore.
            Zend_Auth::getInstance()->clearIdentity();

            return null;
        }
    }
}
