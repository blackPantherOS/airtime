<?php

class AudiopreviewController extends Zend_Controller_Action
{
    public function init()
    {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('show-preview', 'json')
                    ->addActionContext('audio-preview', 'json')
                    ->addActionContext('get-show', 'json')
                    ->addActionContext('playlist-preview', 'json')
                    ->addActionContext('get-playlist', 'json')
                    ->initContext();
    }

    /**
     * Simply sets up the view to play the required audio track.
     *  Gets the parameters from the request and sets them to the view.
     */
    public function audioPreviewAction()
    {
        global $CC_CONFIG;

        $audioFileID = $this->_getParam('audioFileID');
        $audioFileArtist = $this->_getParam('audioFileArtist');
        $audioFileTitle = $this->_getParam('audioFileTitle');
        $type = $this->_getParam('type');

        $request = $this->getRequest();
        $baseUrl = $request->getBaseUrl();

        $this->view->headScript()->appendFile(
            $baseUrl.'/js/airtime/audiopreview/preview_jplayer.js?'.$CC_CONFIG['airtime_version'],
            'text/javascript');
        $this->view->headScript()->appendFile(
            $baseUrl.'/js/jplayer/jplayer.playlist.min.js?'.$CC_CONFIG['airtime_version'],
            'text/javascript');
        $this->view->headLink()->appendStylesheet(
            $baseUrl.'/js/jplayer/skin/jplayer.airtime.audio.preview.css?'.$CC_CONFIG['airtime_version']);
        $this->_helper->layout->setLayout('audioPlayer');

        $logo = Application_Model_Preference::GetStationLogo();
        if ($logo) {
            $this->view->logo = "data:image/png;base64,$logo";
        } else {
            $this->view->logo = "$baseUrl/css/images/airtime_logo_jp.png";
        }

        if ($type == "audioclip") {
            $uri = "/api/get-media/file/".$audioFileID;
            $media = Application_Model_StoredFile::Recall($audioFileID);
            $mime = $media->getPropelOrm()->getDbMime();
        } elseif ($type == "stream") {
            $webstream = CcWebstreamQuery::create()->findPk($audioFileID);
            $uri = $webstream->getDbUrl();
            $mime = $webstream->getDbMime();
        } else {
            throw new Exception("Unknown type for audio preview!");
        }

        $this->view->uri = $uri;
        $this->view->mime = $mime;
        $this->view->audioFileID = $audioFileID;
        $this->view->audioFileArtist = $audioFileArtist;
        $this->view->audioFileTitle = $audioFileTitle;
        $this->view->type = $type;

        $this->_helper->viewRenderer->setRender('audio-preview');
    }

    /**
     * Simply sets up the view to play the required playlist track.
     *  Gets the parameters from the request and sets them to the view.
     */
    public function playlistPreviewAction()
    {
        global $CC_CONFIG;

        $playlistIndex = $this->_getParam('playlistIndex');
        $playlistID = $this->_getParam('playlistID');

        $request = $this->getRequest();
        $baseUrl = $request->getBaseUrl();

        $this->view->headScript()->appendFile($baseUrl.'/js/airtime/audiopreview/preview_jplayer.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'/js/jplayer/jplayer.playlist.min.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headLink()->appendStylesheet($baseUrl.'/js/jplayer/skin/jplayer.airtime.audio.preview.css?'.$CC_CONFIG['airtime_version']);
        $this->_helper->layout->setLayout('audioPlayer');

        $logo = Application_Model_Preference::GetStationLogo();
        if ($logo) {
            $this->view->logo = "data:image/png;base64,$logo";
        } else {
            $this->view->logo = "$baseUrl/css/images/airtime_logo_jp.png";
        }
        $this->view->playlistIndex= $playlistIndex;
        $this->view->playlistID = $playlistID;

        $this->_helper->viewRenderer->setRender('audio-preview');
    }

    public function blockPreviewAction()
    {
        global $CC_CONFIG;

        $blockIndex = $this->_getParam('blockIndex');
        $blockId = $this->_getParam('blockId');

        $request = $this->getRequest();
        $baseUrl = $request->getBaseUrl();

        $this->view->headScript()->appendFile($baseUrl.'/js/airtime/audiopreview/preview_jplayer.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'/js/jplayer/jplayer.playlist.min.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headLink()->appendStylesheet($baseUrl.'/js/jplayer/skin/jplayer.airtime.audio.preview.css?'.$CC_CONFIG['airtime_version']);
        $this->_helper->layout->setLayout('audioPlayer');

        $logo = Application_Model_Preference::GetStationLogo();
        if ($logo) {
            $this->view->logo = "data:image/png;base64,$logo";
        } else {
            $this->view->logo = "$baseUrl/css/images/airtime_logo_jp.png";
        }
        $this->view->blockIndex= $blockIndex;
        $this->view->blockId = $blockId;

        $this->_helper->viewRenderer->setRender('audio-preview');
    }
    public function getBlockAction()
    {
        // disable the view and the layout
        $this->view->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        $blockId = $this->_getParam('blockId');

        if (!isset($blockId)) {
            return;
        }

        $bl = new Application_Model_Block($blockId);
        $result = array();
        foreach ($bl->getContents(true) as $ele) {
            $result[] = $this->createElementMap($ele);
        }
        $this->_helper->json($result);
    }
    /**
     *Function will load and return the contents of the requested playlist.
     */
    public function getPlaylistAction()
    {
        // disable the view and the layout
        $this->view->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        $playlistID = $this->_getParam('playlistID');

        if (!isset($playlistID)) {
            return;
        }

        $pl = new Application_Model_Playlist($playlistID);
        $result = Array();

        foreach ($pl->getContents(true) as $ele) {
            if ($ele['type'] == 2) {
                // if element is a block expand and add
                $bl = new Application_Model_Block($ele['item_id']);
                if ($bl->isStatic()) {
                    foreach ($bl->getContents(true) as $track) {
                        $result[] = $this->createElementMap($track);
                    }
                }
            } else {
                $result[] = $this->createElementMap($ele);
            }
        }
        $this->_helper->json($result);
    }

    private function createElementMap($track)
    {
        $elementMap = array( 'element_title' => isset($track['track_title'])?$track['track_title']:"",
                'element_artist' => isset($track['artist_name'])?$track['artist_name']:"",
                'element_id' => isset($track['id'])?$track['id']:"",
                'element_position' => isset($track['position'])?$track['position']:"",
                'mime' => isset($track['mime'])?$track['mime']:""
            );

        /* If the track type is static we know it must be
         * a track because static blocks can only contain
         * tracks
         */
        if ($track['type'] == 'static') {
            $track['type'] = 0;
        }
        $elementMap['type'] = $track['type'];

        if ($track['type'] == 0) {
            $fileExtension = pathinfo($track['path'], PATHINFO_EXTENSION);
            //type is file
            //TODO: use MIME type for this
            if (strtolower($fileExtension) === 'mp3') {
                $elementMap['element_mp3'] = $track['item_id'];
            } elseif (strtolower($fileExtension) === 'ogg') {
                $elementMap['element_oga'] = $track['item_id'];
            } else {
                //the media was neither mp3 or ogg
                throw new Exception("Unknown file type");
            }

            $elementMap['uri'] = "/api/get-media/file/".$track['item_id'];
        } else {
            $elementMap['uri'] = $track['path'];
        }

        return $elementMap;
    }

    /**
     * Simply sets up the view to play the required show track.
     *  Gets the parameters from the request and sets them to the view.
     */
    public function showPreviewAction()
    {
        global $CC_CONFIG;

        $showID = $this->_getParam('showID');
        $showIndex = $this->_getParam('showIndex');

        $request = $this->getRequest();
        $baseUrl = $request->getBaseUrl();

        $this->view->headScript()->appendFile($baseUrl.'/js/airtime/audiopreview/preview_jplayer.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'/js/jplayer/jplayer.playlist.min.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headLink()->appendStylesheet($baseUrl.'/js/jplayer/skin/jplayer.airtime.audio.preview.css?'.$CC_CONFIG['airtime_version']);
        $this->_helper->layout->setLayout('audioPlayer');

        $logo = Application_Model_Preference::GetStationLogo();
        if ($logo) {
            $this->view->logo = "data:image/png;base64,$logo";
        } else {
            $this->view->logo = "$baseUrl/css/images/airtime_logo_jp.png";
        }

        $this->view->showID = $showID;
        $this->view->showIndex = $showIndex;

        $this->_helper->viewRenderer->setRender('audio-preview');
    }

    /**
     *Function will load and return the contents of the requested show.
     */
    public function getShowAction()
    {
        // disable the view and the layout
        $this->view->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        $showID = $this->_getParam('showID');

        if (!isset($showID)) {
            return;
        }

        $showInstance = new Application_Model_ShowInstance($showID);
        $result = array();
        $position = 0;
        foreach ($showInstance->getShowListContent() as $track) {

            $elementMap = array(
                'element_title' => isset($track['track_title']) ? $track['track_title'] : "",
                'element_artist' => isset($track['creator']) ? $track['creator'] : "",
                'element_position' => $position,
                'element_id' => ++$position,
            );

            $elementMap['type'] = $track['type'];
            if ($track['type'] == 0) {
                $fileExtension = pathinfo($track['filepath'], PATHINFO_EXTENSION);
                if (strtolower($fileExtension) === 'mp3') {
                    $elementMap['element_mp3'] = $track['item_id'];
                } elseif (strtolower($fileExtension) === 'ogg') {
                    $elementMap['element_oga'] = $track['item_id'];
                } else {
                    //the media was neither mp3 or ogg
                    throw new Exception("Unknown file type");
                }

                $elementMap['uri'] = "/api/get-media/file/".$track['item_id'];
            } else {
                $elementMap['uri'] = $track['filepath'];
            }
            $result[] = $elementMap;
        }

        $this->_helper->json($result);

    }
}
