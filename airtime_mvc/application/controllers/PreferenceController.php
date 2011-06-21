<?php

class PreferenceController extends Zend_Controller_Action
{

    public function init()
    {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('server-browse', 'json')
                    ->addActionContext('reload-watch-directory', 'json')
                    ->addActionContext('remove-watch-directory', 'json')
                    ->initContext();
    }

    public function indexAction()
    {
        $request = $this->getRequest();
        $baseUrl = $request->getBaseUrl();

        $this->view->headScript()->appendFile($baseUrl.'/js/airtime/preferences/preferences.js','text/javascript');
        $this->view->statusMsg = "";

        $form = new Application_Form_Preferences();

        if ($request->isPost()) {

            if ($form->isValid($request->getPost())) {

                $values = $form->getValues();

                Application_Model_Preference::SetHeadTitle($values["preferences_general"]["stationName"], $this->view);
                Application_Model_Preference::SetDefaultFade($values["preferences_general"]["stationDefaultFade"]);
                Application_Model_Preference::SetStreamLabelFormat($values["preferences_general"]["streamFormat"]);
                Application_Model_Preference::SetAllow3rdPartyApi($values["preferences_general"]["thirdPartyApi"]);
                Application_Model_Preference::SetWatchedDirectory($values["preferences_general"]["watchedFolder"]);

                Application_Model_Preference::SetDoSoundCloudUpload($values["preferences_soundcloud"]["UseSoundCloud"]);
                Application_Model_Preference::SetSoundCloudUser($values["preferences_soundcloud"]["SoundCloudUser"]);
                Application_Model_Preference::SetSoundCloudPassword($values["preferences_soundcloud"]["SoundCloudPassword"]);
                Application_Model_Preference::SetSoundCloudTags($values["preferences_soundcloud"]["SoundCloudTags"]);
                Application_Model_Preference::SetSoundCloudGenre($values["preferences_soundcloud"]["SoundCloudGenre"]);
                Application_Model_Preference::SetSoundCloudTrackType($values["preferences_soundcloud"]["SoundCloudTrackType"]);
                Application_Model_Preference::SetSoundCloudLicense($values["preferences_soundcloud"]["SoundCloudLicense"]);

                $this->view->statusMsg = "<div class='success'>Preferences updated.</div>";
            }
        }

        $this->view->form = $form;
    }

    public function directoryConfigAction()
    {
        $request = $this->getRequest();
        $baseUrl = $request->getBaseUrl();

        $this->view->headScript()->appendFile($baseUrl.'/js/serverbrowse/serverbrowser.js','text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'/js/airtime/preferences/preferences.js','text/javascript');

        $watched_dirs_pref = new Application_Form_WatchedDirPreferences();
        $watched_dirs_pref->setWatchedDirs();

        $this->view->form = $watched_dirs_pref;
    }

    public function serverBrowseAction()
    {
        $request = $this->getRequest();
        $path = $request->getParam("path", null);

        $result = array();

        if(is_null($path))
        {
            $element = array();
            $element["name"] = "path should be specified";
            $element["isFolder"] = false;
            $element["isError"] = true;
            $result[$path] = $element;
        }
        else
        {
            $path = $path.'/';
            $handle =  opendir($path);
            while (false !== ($file = readdir($handle))) {
                if ($file != "." && $file != "..") {
                    //only show directories that aren't private.
                    if (is_dir($path.$file) && substr($file, 0, 1) != ".") {
                        $element = array();
                        $element["name"] = $file;
                        $element["isFolder"] = true;
                        $element["isError"] = false;
                        $result[$file] = $element;
                    }
                }
            }
        }
        ksort($result);
        //returns format serverBrowse is looking for.
        die(json_encode($result));
    }

    public function reloadWatchDirectoryAction()
    {
        $chosen = $this->getRequest()->getParam("dir");
        $watched_dirs_form = new Application_Form_WatchedDirPreferences();
        $watched_dirs_form->populate(array('watchedFolder' => $chosen));
        $bool = $watched_dirs_form->verifyChosenFolder();

        if ($bool === true) {
            MusicDir::addWatchedDir($chosen);
            $data = array();
            $data["directory"] = $chosen;
            RabbitMq::SendMessageToMediaMonitor("new_watch", $data);
        }

        $watched_dirs_form->setWatchedDirs();

        $this->view->subform = $watched_dirs_form->render();
    }

    public function removeWatchDirectoryAction()
    {
        $chosen = $this->getRequest()->getParam("dir");

        $dir = MusicDir::getDirByPath($chosen);
        $dir->remove();

        $data = array();
        $data["directory"] = $chosen;
        RabbitMq::SendMessageToMediaMonitor("remove_watch", $data);
    }
}



