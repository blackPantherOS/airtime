<?php

require_once 'formatters/LengthFormatter.php';
require_once 'formatters/SamplerateFormatter.php';
require_once 'formatters/BitrateFormatter.php';

class LibraryController extends Zend_Controller_Action
{

    protected $obj_sess = null;
    protected $search_sess = null;

    public function init()
    {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('contents-feed', 'json')
                    ->addActionContext('delete', 'json')
                    ->addActionContext('delete-group', 'json')
                    ->addActionContext('context-menu', 'json')
                    ->addActionContext('get-file-metadata', 'html')
                    ->addActionContext('upload-file-soundcloud', 'json')
                    ->addActionContext('get-upload-to-soundcloud-status', 'json')
                    ->addActionContext('set-num-entries', 'json')
                    ->initContext();

        $this->obj_sess = new Zend_Session_Namespace(UI_PLAYLISTCONTROLLER_OBJ_SESSNAME);
        $this->search_sess = new Zend_Session_Namespace("search");
    }

    public function contextMenuAction()
    {
        $id = $this->_getParam('id');
        $type = $this->_getParam('type');
        //playlist||timeline
        $screen = $this->_getParam('screen');
        $request = $this->getRequest();
        $baseUrl = $request->getBaseUrl();
        $menu = array();

        $userInfo = Zend_Auth::getInstance()->getStorage()->read();
        $user = new Application_Model_User($userInfo->id);

        //Open a jPlayer window and play the audio clip.
        $menu["play"] = array("name"=> "Preview", "icon" => "play");

        $isAdminOrPM = $user->isUserType(array(UTYPE_ADMIN, UTYPE_PROGRAM_MANAGER));

        if ($type === "audioclip") {

            $file = Application_Model_StoredFile::Recall($id);

            if (isset($this->obj_sess->id) && $screen == "playlist") {
                // if the user is not admin or pm, check the creator and see if this person owns the playlist or Block
                if ($this->obj_sess->type == 'playlist') {
                    $obj = new Application_Model_Playlist($this->obj_sess->id);
                } else {
                    $obj = new Application_Model_Block($this->obj_sess->id);
                }
                if ($isAdminOrPM || $obj->getCreatorId() == $user->getId()) {
                    if ($this->obj_sess->type === "playlist") {
                        $menu["pl_add"] = array("name"=> "Add to Playlist", "icon" => "add-playlist", "icon" => "copy");
                    } else {
                        $menu["pl_add"] = array("name"=> "Add to Smart Playlist", "icon" => "add-playlist", "icon" => "copy");
                    }
                }
            }
            if ($isAdminOrPM) {
                $menu["del"] = array("name"=> "Delete", "icon" => "delete", "url" => "/library/delete");
                $menu["edit"] = array("name"=> "Edit Metadata", "icon" => "edit", "url" => "/library/edit-file-md/id/{$id}");
            }

            $url = $file->getRelativeFileUrl($baseUrl).'/download/true';
            $menu["download"] = array("name" => "Download", "icon" => "download", "url" => $url);
        } elseif ($type === "playlist" || $type === "block") {
            if ($type === 'playlist') {
                $obj = new Application_Model_Playlist($id);
            } else {
                $obj = new Application_Model_Block($id);
                if (!$obj->isStatic()){
                    unset($menu["play"]);
                }
                if (($isAdminOrPM || $obj->getCreatorId() == $user->getId()) && $screen == "playlist") {
                    if ($this->obj_sess->type === "playlist") {
                        $menu["pl_add"] = array("name"=> "Add to Playlist", "icon" => "add-playlist", "icon" => "copy");
                    }
                }
            }
            
            if ($this->obj_sess->id !== $id && $screen == "playlist") {
                if ($isAdminOrPM || $obj->getCreatorId() == $user->getId()) {
                    $menu["edit"] = array("name"=> "Edit", "icon" => "edit");
                }
            }
            if ($isAdminOrPM || $obj->getCreatorId() == $user->getId()) {
                $menu["del"] = array("name"=> "Delete", "icon" => "delete", "url" => "/library/delete");
            }
        } else if ($type == "stream") {

            $obj = new Application_Model_Webstream($id);
            if (isset($this->obj_sess->id) && $screen == "playlist") {
                if ($isAdminOrPM || $obj->getCreatorId() == $user->getId()) {
                    if ($this->obj_sess->type === "playlist") {
                        $menu["pl_add"] = array("name"=> "Add to Playlist", "icon" => "add-playlist", "icon" => "copy");
                    } else {
                        $menu["pl_add"] = array("name"=> "Add to Smart Playlist", "icon" => "add-playlist", "icon" => "copy");
                    }
                }
            }
            if ($isAdminOrPM || $obj->getCreatorId() == $user->getId()) {
                $menu["del"] = array("name"=> "Delete", "icon" => "delete", "url" => "/library/delete");
                $menu["edit"] = array("name"=> "Edit", "icon" => "edit", "url" => "/library/edit-file-md/id/{$id}");
            }
          

        }

        //SOUNDCLOUD MENU OPTIONS
        if ($type === "audioclip" && Application_Model_Preference::GetUploadToSoundcloudOption()) {

            //create a menu separator
            $menu["sep1"] = "-----------";

            //create a sub menu for Soundcloud actions.
            $menu["soundcloud"] = array("name" => "Soundcloud", "icon" => "soundcloud", "items" => array());

            $scid = $file->getSoundCloudId();

            if ($scid > 0) {
                $url = $file->getSoundCloudLinkToFile();
                $menu["soundcloud"]["items"]["view"] = array("name" => "View on Soundcloud", "icon" => "soundcloud", "url" => $url);
            }

            if (!is_null($scid)) {
                $text = "Re-upload to SoundCloud";
            } else {
                $text = "Upload to SoundCloud";
            }

            $menu["soundcloud"]["items"]["upload"] = array("name" => $text, "icon" => "soundcloud", "url" => "/library/upload-file-soundcloud/id/{$id}");
        }

        $this->view->items = $menu;
    }

    public function deleteAction()
    {
        //array containing id and type of media to delete.
        $mediaItems = $this->_getParam('media', null);

        $user = Application_Model_User::getCurrentUser();
        $isAdminOrPM = $user->isUserType(array(UTYPE_ADMIN, UTYPE_PROGRAM_MANAGER));

        $files = array();
        $playlists = array();
        $blocks = array();
        $streams = array();

        $message = null;

        foreach ($mediaItems as $media) {

            if ($media["type"] === "audioclip") {
                $files[] = intval($media["id"]);
            } elseif ($media["type"] === "playlist") {
                $playlists[] = intval($media["id"]);
            } elseif ($media["type"] === "block") {
                $blocks[] = intval($media["id"]);
            } elseif ($media["type"] === "stream") {
                $streams[] = intval($media["id"]);
            }
        }

        try {
            Application_Model_Playlist::deletePlaylists($playlists, $user->getId());
        } catch (PlaylistNoPermissionException $e) {
            $this->view->message = "You don't have permission to delete selected items.";
            return;
        }


        try {
            Application_Model_Block::deleteBlocks($blocks, $user->getId());
        } catch (Exception $e) {
            //TODO: warn user that not all blocks could be deleted. 
        }

        try {
            Application_Model_Webstream::deleteStreams($streams, $user->getId());
        } catch (Exception $e) {
            //TODO: warn user that not all streams could be deleted.
            Logging::log($e);
        }

        foreach ($files as $id) {

            $file = Application_Model_StoredFile::Recall($id);

            if (isset($file)) {
                try {
                    $res = $file->delete(true);
                } catch (Exception $e) {
                    //could throw a scheduled in future exception.
                    $message = "Could not delete some scheduled files.";
                }
            }
        }

        if (isset($message)) {
            $this->view->message = $message;
        }
    }

    public function contentsFeedAction()
    {
        $params = $this->getRequest()->getParams();

        $r = Application_Model_StoredFile::searchLibraryFiles($params);

        //TODO move this to the datatables row callback.
        foreach ($r["aaData"] as &$data) {

            if ($data['ftype'] == 'audioclip') {
                $file = Application_Model_StoredFile::Recall($data['id']);
                $scid = $file->getSoundCloudId();

                if ($scid == "-2") {
                    $data['track_title'] .= '<span class="small-icon progress"/>';
                } elseif ($scid == "-3") {
                    $data['track_title'] .= '<span class="small-icon sc-error"/>';
                } elseif (!is_null($scid)) {
                    $data['track_title'] .= '<span class="small-icon soundcloud"/>';
                }
            }
        }

        $this->view->sEcho = $r["sEcho"];
        $this->view->iTotalDisplayRecords = $r["iTotalDisplayRecords"];
        $this->view->iTotalRecords = $r["iTotalRecords"];
        $this->view->files = $r["aaData"];
    }

    public function editFileMdAction()
    {
        $user = Application_Model_User::getCurrentUser();
        $isAdminOrPM = $user->isUserType(array(UTYPE_ADMIN, UTYPE_PROGRAM_MANAGER));
        if (!$isAdminOrPM) {
            return;
        }

        $request = $this->getRequest();
        $form = new Application_Form_EditAudioMD();

        $file_id = $this->_getParam('id', null);
        $file = Application_Model_StoredFile::Recall($file_id);
        $form->populate($file->getDbColMetadata());

        if ($request->isPost()) {
            if ($form->isValid($request->getPost())) {

                $formdata = $form->getValues();
                $file->setDbColMetadata($formdata);

                $data = $file->getMetadata();

                // set MDATA_KEY_FILEPATH
                $data['MDATA_KEY_FILEPATH'] = $file->getFilePath();
                Logging::log($data['MDATA_KEY_FILEPATH']);
                Application_Model_RabbitMq::SendMessageToMediaMonitor("md_update", $data);

                $this->_redirect('playlist/index');
            }
        }

        $this->view->form = $form;
    }

    public function getFileMetadataAction()
    {
        $id = $this->_getParam('id');
        $type = $this->_getParam('type');

        try {
            if ($type == "audioclip") {
                $file = Application_Model_StoredFile::Recall($id);
                $this->view->type = $type;
                $md = $file->getMetadata();

                foreach ($md as $key => $value) {
                    if ($key == 'MDATA_KEY_DIRECTORY') {
                        $musicDir = Application_Model_MusicDir::getDirByPK($value);
                        $md['MDATA_KEY_FILEPATH'] = Application_Common_OsPath::join($musicDir->getDirectory(), $md['MDATA_KEY_FILEPATH']);
                    }
                }

                $formatter = new SamplerateFormatter($md["MDATA_KEY_SAMPLERATE"]);
                $md["MDATA_KEY_SAMPLERATE"] = $formatter->format();

                $formatter = new BitrateFormatter($md["MDATA_KEY_BITRATE"]);
                $md["MDATA_KEY_BITRATE"] = $formatter->format();

                $formatter = new LengthFormatter($md["MDATA_KEY_DURATION"]);
                $md["MDATA_KEY_DURATION"] = $formatter->format();

                $this->view->md = $md;

            } else if ($type == "playlist") {

                $file = new Application_Model_Playlist($id);
                $this->view->type = $type;
                $md = $file->getAllPLMetaData();

                $formatter = new LengthFormatter($md["dcterms:extent"]);
                $md["dcterms:extent"] = $formatter->format();

                $this->view->md = $md;
                $this->view->contents = $file->getContents();
            } else if ($type == "block") {
                $block = new Application_Model_Block($id);
                $this->view->type = $type;
                $md = $block->getAllPLMetaData();

                $formatter = new LengthFormatter($md["dcterms:extent"]);
                $md["dcterms:extent"] = $formatter->format();

                $this->view->md = $md;
                if ($block->isStatic()) {
                    $this->view->blType = 'Static';
                    $this->view->contents = $block->getContents();
                } else {
                    $this->view->blType = 'Dynamic';
                    $this->view->contents = $block->getCriteria();
                }
                $this->view->block = $block;
            } else if ($type == "stream") {
                $file = new Application_Model_Webstream($id);

                $md = $file->getMetadata();

                $this->view->md = $md;
                $this->view->type = $type;
            }
        } catch (Exception $e) {
            Logging::log($e->getMessage());
        }
    }

    public function uploadFileSoundcloudAction()
    {
        $id = $this->_getParam('id');
        $res = exec("/usr/lib/airtime/utils/soundcloud-uploader $id > /dev/null &");
        // we should die with ui info
        die();
    }

    public function getUploadToSoundcloudStatusAction()
    {
        $id = $this->_getParam('id');
        $type = $this->_getParam('type');

        if ($type == "show") {
            $show_instance = new Application_Model_ShowInstance($id);
            $this->view->sc_id = $show_instance->getSoundCloudFileId();
            $file = $show_instance->getRecordedFile();
            $this->view->error_code = $file->getSoundCloudErrorCode();
            $this->view->error_msg = $file->getSoundCloudErrorMsg();
        } elseif ($type == "file") {
            $file = Application_Model_StoredFile::Recall($id);
            $this->view->sc_id = $file->getSoundCloudId();
            $this->view->error_code = $file->getSoundCloudErrorCode();
            $this->view->error_msg = $file->getSoundCloudErrorMsg();
        }
    }
}
