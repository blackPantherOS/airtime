<?php
class uiExchange
{
    function uiExchange(&$uiBase)
    {
        $this->Base         =& $uiBase;
        $this->file         =& $_SESSION['EXCHANGE']['file'];
        $this->folder       =& $_SESSION['EXCHANGE']['folder'];
        if (empty($this->folder)) {
            $this->folder = '/tmp';    
        }
        
        $this->test         = false;
    }
    
    function getBackupToken()
    {
        if ($this->test) return "12345";
        
        
        $token = $this->Base->gb->loadPref($this->Base->sessid, UI_BACKUPTOKEN_KEY);
        
        if (PEAR::isError($token)) {
            return false;    
        }
        
        return $token;
        
    }
    
    function createBackupOpen()
    {
        $criteria = array('filetype' => UI_FILETYPE_ANY);
        $token = $this->Base->gb->createBackupOpen($this->Base->sessid, $criteria);
        
        if (PEAR::isError($token)) {
            $this->Base->_retMsg('Error initializing backup: $1', $token->getMessage());
            return false;    
        }
        
        $this->createBackupCheck();
        
        $this->Base->gb->savePref($this->Base->sessid, UI_BACKUPTOKEN_KEY, $token);
        
        return true;
    }       
    
    function createBackupCheck()
    {
        if ($this->test) return array('status' => 'success', 'tmpfile' => '/tmp/xxx.backup');   
        
        
        $token = $this->getBackupToken();
        
        if ($token === false) {
            return flase;    
        }       
        
        $res   = $this->Base->gb->createBackupCheck($token);
        
        if (PEAR::isError($res)) {
            $this->Base->_retMsg('Unable to check backup status: $1', $res->getMessage());
            return false;    
        }
        
        return $res;
    }
    
    function createBackupClose()
    {
        $token  = $token = $this->getBackupToken(); 
        
        if ($token === false) {
            $this->Base->_retMsg('Token not available');
            return false;    
        } 
        
        $status = $this->Base->gb->createBackupClose($token);   
        
        if (PEAR::isError($status)) {
            $this->Base->_retMsg('Error closing backup: $1', $status->getMessage());
            return false;    
        }
        
        if ($status === true) {
            $this->Base->gb->delPref($this->Base->sessid, UI_BACKUPTOKEN_KEY);        
        }
        
        return $status;
    }
    
    
    function setFolder($subfolder)
    {
        $this->file = false;
        
        if (!is_dir($this->folder.'/'.$subfolder)) {
            # F5 pressed
            return false;       
        }
        $this->folder = realpath($this->folder.'/'.$subfolder);    
    }
    
    function setFile($file)
    {
        if (is_writable($this->folder.'/'.$file)) {
            $this->file = $file;
            
            return true;
        }
        $this->file = false;
        $this->errorMsg = tra('$1: Permission denied', $file);
        
        return false;   
    }
    
    function getPath()
    {
        $path = $this->folder.'/'.$this->file;
        
        if (is_dir($this->folder)) {
            return str_replace('//', '/', $path);  
        }
        
        return false;     
    }
    
    function checkTarget()
    {
        if (!is_writable($this->folder.'/'.$this->file) && !is_writable($this->folder)) {
            return false;   
        } 
        return true; 
    }
    
    
    function completeTarget()
    {
        if (!$this->file) {
            $this->file = 'ls-backup_'.date('Y-m-d');    
        } 
    }
    
    function setTarget($target)
    {
        if (is_dir($target)) { 
            $this->folder = $target;    
        } else {
            $pathinfo = pathinfo($target);
            $this->folder = $pathinfo['dirname']; 
            $this->file   = $pathinfo['basename'];  
        } 
    }
    
    function listFolder()
    {
        $d = dir($this->folder); 
   
        while (false !== ($entry = $d->read())) { 
            $loc = $this->folder.'/'.$entry;
            
            if (is_dir($loc)) {
                $dirs[$entry]  = $this->_getPerm($loc);                
            } else {
                $files[$entry] = $this->_getPerm($loc);    
            }   
            
        } 
        
        @ksort($dirs);
        @ksort($files);  
        
        return array('subdirs' => $dirs, 'files' => $files);
    }
    
    function _getPerm($loc)
    {
        return array(
                    'r' => is_readable($loc),
                    'w' => is_writable($loc),
                    'x' => is_executable($loc),                
                );        
        
    }
}
?>