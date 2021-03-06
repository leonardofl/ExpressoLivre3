<?php

class Messenger_Frontend_Http extends Tinebase_Frontend_Http_Abstract
{

    protected $_applicationName = 'Messenger';

    /**
     * Returns all JS files which must be included for this app
     *
     * @return array Array of filenames
     */
    public function getJsFilesToInclude()
    {
        return array(
            'Messenger/js/Application.js',
            'Messenger/js/jquery-1.7.1.min.js'
        );
    }

    public function getFile($name, $tmpfile, $downloadOption)
    {
        if ($downloadOption == 'yes') {
            header('Cache-Control: private, max-age=0');
            header("Expires: -1");
            // overwrite Pragma header from session
            header("Pragma: cache");
            header('Content-Disposition: attachment; filename="' . $name . '"');
            readfile($tmpfile);
        }
        unlink($tmpfile);
    }
    
    public function downloadHistory($jid, $contact, $date)
    {
        $filename = $date . '.json';
        $file = Messenger_Controller::HISTORY_PATH . '/' . $jid . '/' . $contact . '/' . $filename;
        
        if (file_exists($file)) {
            $filename = str_replace('@', '_', $jid) . '--' . str_replace('@', '_', $contact) . '--' . $date . '.txt';
            
            header('Content-Description: File Transfer');
            header('Cache-Control: private, max-age=0');
            header("Expires: -1");
            header("Pragma: cache");
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            //readfile($filename);
            
            $lines = explode("\n", file_get_contents($file));
            foreach ($lines as $line)
            {
                $history = json_decode($line);
                $user = $history->dir == to ? $contact : Tinebase_Translation::getTranslation('Messenger')->_('ME');
                echo $user . ' (' . $history->time . ') ==> ' . $history->msg . "\n";
            }
        }
    }
    
    public function uploadTempFile()
    {
        try {
            $this->checkAuth();

            // close session to allow other requests
            Zend_Session::writeClose(true);

            $tempFile = Messenger_TempFile::getInstance()->uploadTempFile();

            die(Zend_Json::encode(array(
               'status'   => 'success',
               'tempFile' => $tempFile->toArray(),
            )));
        } catch (Tinebase_Exception $exception) {
            Tinebase_Core::getLogger()->WARN(__METHOD__ . '::' . __LINE__ . " File upload could not be done, due to the following exception: \n" . $exception);

            if (! headers_sent()) {
               header("HTTP/1.0 500 Internal Server Error");
            }
            die(Zend_Json::encode(array(
                'status'   => 'failed',
            )));
        }
    }
    
    /**
     * checks if a user is logged in. If not we redirect to login
     */
    protected function checkAuth()
    {
        try {
            Tinebase_Core::getUser();
        } catch (Exception $e) {
            header('HTTP/1.0 403 Forbidden');
            exit;
        }
    }

}