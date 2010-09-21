<?php

require_once(dirname(__FILE__).'/../conf.php');
require_once('PHPUnit.php');
require_once('DB.php');
require_once(dirname(__FILE__).'/../GreenBox.php');
require_once(dirname(__FILE__).'/../Playlist.php');

$dsn = $CC_CONFIG['dsn'];
$CC_DBC = DB::connect($dsn, TRUE);
if (PEAR::isError($CC_DBC)) {
	echo "ERROR: ".$CC_DBC->getMessage()." ".$CC_DBC->getUserInfo()."\n";
	exit(1);
}
$CC_DBC->setFetchMode(DB_FETCHMODE_ASSOC);

class PlayListTests extends PHPUnit_TestCase {

    private $greenbox;

    function __construct($name) {
        parent::__construct($name);
    }

    function setup() {
        global $CC_CONFIG, $CC_DBC;
        $this->greenbox = new GreenBox();
     
    }
    
    function testGBCreatePlaylist() {
        
        $pl = new Playlist();
        $pl_id = $pl->create("create");
        
        if (PEAR::isError($pl_id)) {
            $this->fail("problems creating playlist.");
            return;
        }
    }
    
    function testGBLock() {
        $pl = new Playlist();
        $pl_id = $pl->create("lock test");
        
        $sessid = Alib::Login('root', 'q');
       
        $res = $this->greenbox->lockPlaylistForEdit($pl_id, $sessid);
        
        if($res !== TRUE) {
            $this->fail("problems locking playlist for editing.");
            return;
        }
    }
    
    function testGBUnLock() {
        $pl = new Playlist();
        $pl_id = $pl->create("unlock test");
        
        $sessid = Alib::Login('root', 'q');
        
        $this->greenbox->lockPlaylistForEdit($pl_id, $sessid);
        $res = $this->greenbox->releaseLockedPlaylist($pl_id, $sessid);
        
        if($res !== TRUE) {
           $this->fail("problems unlocking playlist."); 
           return;
        }
    }
    
    function testGBSetPLMetaData() {
        $pl = new Playlist();
        $pl_id = $pl->create("set meta data test");
       
        $res = $this->greenbox->setPLMetadataValue($pl_id, "dc:title", "A Title");
       
        if($res !== TRUE) {
           $this->fail("problems setting playlist metadata.");
           return; 
        }     
    }
    
    function testGBGetPLMetaData() {
        $pl = new Playlist();
        $name = "Testing";
        $pl_id = $pl->create($name);
       
        $res = $this->greenbox->getPLMetadataValue($pl_id, "dc:title");
        
        if($res !== $name) {
           $this->fail("problems getting playlist metadata."); 
           return;
        } 
    }
    
    function testAddAudioClip() {
        $pl = new Playlist();
        $pl_id = $pl->create("Add");
        
        $res = $this->greenbox->addAudioClipToPlaylist($pl_id, '1');
        
        if($res !== TRUE) {
           $this->fail("problems adding audioclip to playlist.");
           return; 
        } 
    }
    
    function testMoveAudioClip() {
        $pl = new Playlist();
        $pl_id = $pl->create("Move");
        
        $this->greenbox->addAudioClipToPlaylist($pl_id, '1');
        $this->greenbox->addAudioClipToPlaylist($pl_id, '2');
        
        $res = $this->greenbox->moveAudioClipInPlaylist($pl_id, 0, 1);
        
        if($res !== TRUE) {
           $this->fail("problems moving audioclip in playlist.");
           return; 
        } 
    }
    
    function testDeleteAudioClip() {
        $pl = new Playlist();
        $pl_id = $pl->create("Delete");
        
        $this->greenbox->addAudioClipToPlaylist($pl_id, '1');
        $res = $this->greenbox->delAudioClipFromPlaylist($pl_id, 0);
       
        if($res !== TRUE) {
           $this->fail("problems deleting audioclip from playlist.");
           return; 
        } 
    }
    
}

?>