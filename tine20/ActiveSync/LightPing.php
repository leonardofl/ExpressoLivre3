<?php

/**
 * Syncope
 *
 * @package     ActiveSync
 * @license     http://www.tine20.org/licenses/lgpl.html LGPL Version 3
 * @copyright   Copyright (c) 2008-2013 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Emerson F. Nobre <emerson-faria.nobre@serpro.gov.br>
 */

/**
 * class to handle ActiveSync Ping command with low processing.
 * Workarround to avoid overloading of server. 
 * You can change the nosyncinterval value in config. The default is 300 seconds.
 * TODO: Remove this class when sync overload down 
 *
 * @package     ActiveSync
 */
class ActiveSync_LightPing {

    const STATUS_NO_CHANGES_FOUND = 1;
    const STATUS_CHANGES_FOUND = 2;
    const STATUS_MISSING_PARAMETERS = 3;
    const STATUS_REQUEST_FORMAT_ERROR = 4;
    const STATUS_INTERVAL_TO_GREAT_OR_SMALL = 5;
    const STATUS_TO_MUCH_FOLDERS = 6;
    const STATUS_FOLDER_NOT_FOUND = 7;
    const STATUS_GENERAL_ERROR = 8;
    const PING_TIMEOUT = 60;
    const PING_QUIET_INTERVAL = 170;

    protected $_defaultNameSpace = 'uri:Ping';
    protected $_documentElement = 'Ping';

    public static function handle() {
        $intervalStart = time();

        $status = self::STATUS_NO_CHANGES_FOUND;
        $syncTimeStamp = new DateTime(null, new DateTimeZone('UTC'));

        $_user = $_REQUEST['User'];
        $_device = $_REQUEST['DeviceId'];
        $_body = fopen('php://input', 'r');

        /*Tinebase_Core::setupConfig();

        // Server Timezone must be setup before logger, as logger has timehandling!
        Tinebase_Core::setupServerTimezone();

        Tinebase_Core::setupLogger();

        // Database Connection must be setup before cache because setupCache uses constant "SQL_TABLE_PREFIX"
        Tinebase_Core::setupDatabaseConnection();

        Tinebase_Core::set('userTimeZone', 'UTC');*/
        Tinebase_Core::initFramework();

        if (self::_authenticate($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'], $_SERVER['REMOTE_ADDR']) !== true) {
            header('WWW-Authenticate: Basic realm="ActiveSync for Tine 2.0"');
            header('HTTP/1.1 401 Unauthorized');
            return;
        }
        
        Syncope_Registry::setContactsDataClass('ActiveSync_Controller_Contacts');
        Syncope_Registry::setCalendarDataClass('ActiveSync_Controller_Calendar');
        Syncope_Registry::setEmailDataClass('ActiveSync_Controller_Email');
        Syncope_Registry::setTasksDataClass('ActiveSync_Controller_Tasks');

        Syncope_Registry::set('deviceBackend', new Syncope_Backend_Device(Tinebase_Core::getDb(), SQL_TABLE_PREFIX . 'acsync_'));
        Syncope_Registry::set('syncStateBackend', new Syncope_Backend_SyncState(Tinebase_Core::getDb(), SQL_TABLE_PREFIX . 'acsync_'));
        Syncope_Registry::set('folderBackend', new Syncope_Backend_Folder(Tinebase_Core::getDb(), SQL_TABLE_PREFIX . 'acsync_'));
        Syncope_Registry::set('contentStateBackend', new Syncope_Backend_Content(Tinebase_Core::getDb(), SQL_TABLE_PREFIX . 'acsync_'));

        $_deviceBackend = Syncope_Registry::get('deviceBackend');
        $_syncStateBackend = Syncope_Registry::get('syncStateBackend');
        $_folderBackend = Syncope_Registry::get('folderBackend');
        $_contentStateBackend = Syncope_Registry::get('contentStateBackend');

        $accountsController = Tinebase_User::getInstance();
        $device = $_deviceBackend->getUserDevice($accountsController->getFullUserByLoginName($_user)->accountId, $_device);

        if ($_SERVER['CONTENT_TYPE'] == 'application/vnd.ms-sync.wbxml') {
            // decode wbxml request
            try {
                $decoder = new Wbxml_Decoder($_body);
                $requestBody = $decoder->decode();
            } catch (Wbxml_Exception_UnexpectedEndOfFile $e) {
                $requestBody = NULL;
            }
        } else {
            $requestBody = $_body;
        }

        // the client does not send a wbxml document, if the Ping parameters did not change compared with the last request
        if ($requestBody instanceof DOMDocument) {
            $xml = simplexml_import_dom($requestBody);
            $xml->registerXPathNamespace('Ping', 'Ping');

            if (isset($xml->HeartBeatInterval)) {
                $device->pinglifetime = (int) $xml->HeartBeatInterval;
            }

            if (isset($xml->Folders->Folder)) {
                $folders = array();
                foreach ($xml->Folders->Folder as $folderXml) {
                    try {
                        // does the folder exist?
                        $folder = $_folderBackend->getFolder($device, (string) $folderXml->Id);

                        $folders[] = $folder;
                    } catch (Syncope_Exception_NotFound $senf) {
                        if ($this->_logger instanceof Zend_Log)
                            $this->_logger->debug(__METHOD__ . '::' . __LINE__ . " " . $senf->getMessage());
                        $status = self::STATUS_FOLDER_NOT_FOUND;
                        break;
                    }
                }
                $device->pingfolder = serialize($folders);
            }
        }

        $pingDateTime = new DateTime(null, new DateTimeZone('UTC'));
        $device->lastping = $pingDateTime;
        $device = $_deviceBackend->update($device);

        $lifeTime = $device->pinglifetime;
        $intervalEnd = $intervalStart + $lifeTime;
        $secondsLeft = $intervalEnd;

        if (isset(Tinebase_Core::getConfig()->pingtimeout)) {
            $_pingtimeout = Tinebase_Core::getConfig()->pingtimeout;
        } else {
            $_pingtimeout = self::PING_TIMEOUT;
        }

        if (isset(Tinebase_Core::getConfig()->pingquietinterval)) {
            $_pingquietinterval = Tinebase_Core::getConfig()->pingquietinterval;
        } else {
            $_pingquietinterval = self::PING_QUIET_INTERVAL;
        }

        if ($status === self::STATUS_NO_CHANGES_FOUND) {

            $folderWithChanges = array();

            do {
                //Tinebase_Core::getDb()->closeConnection();
                sleep($_pingtimeout);

                $device = $_deviceBackend->get($device->id);
                if (isset($device->lastping) && ($device->lastping->getTimestamp() > $pingDateTime->getTimestamp())) {
                    break;
                }

                $now = new DateTime('now', new DateTimeZone('utc'));

                foreach ((array) $folders as $folder) {
                    //if ($folder->class == 'Email') {
                        $dataController = Syncope_Data_Factory::factory($folder->class, $device, $syncTimeStamp);
                        $syncStateFound = true;
                        try {
                            $syncState = $_syncStateBackend->getSyncState($device, $folder);

                            // Workarround to avoid IMAP and Database overload. Only look for folder changes after a interval
                            if (!isset($lastPingFetch[$folder->id]) || (($now->getTimestamp() - $lastPingFetch[$folder->id]) > $_pingquietinterval)) {
                                $foundChanges = !!$dataController->getCountOfChanges($_contentStateBackend, $folder, $syncState);
                                $lastPingTmp = new DateTime('now', new DateTimeZone('utc'));
                                $lastPingFetch[$folder->id] = $lastPingTmp->getTimestamp();
                            } else {
                                $foundChanges = false;
                            }
                        } catch (Syncope_Exception_NotFound $e) {
                            // folder got never synchronized to client
                            if ($this->_logger instanceof Zend_Log)
                                $this->_logger->debug(__METHOD__ . '::' . __LINE__ . " " . $e->getMessage());
                            if ($this->_logger instanceof Zend_Log)
                                $this->_logger->info(__METHOD__ . '::' . __LINE__ . ' syncstate not found. enforce sync for folder: ' . $folder->folderid);
                            $syncStateFound = false;
                            $foundChanges = true;
                        }

                        if ($foundChanges == true) {
                            $foldersWithChanges[] = $folder;
                            $status = self::STATUS_CHANGES_FOUND;
                        }
                        if ($syncStateFound === true) {
                            $foundChanges ? $syncState->pingfoundchanges = 0 : $syncState->pingfoundchanges = 1; // 0 is true
                            $_syncStateBackend->update($syncState);
                        }
                    //}
                }

                if ($status === self::STATUS_CHANGES_FOUND) {
                    break;
                }

                $secondsLeft = $intervalEnd - time();
                //if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . " DeviceId: " . $this->_device->deviceid . " seconds left: " . $secondsLeft);
            } while (($secondsLeft - $_pingtimeout - 10) > 0);
        }

        if (PHP_SAPI !== 'cli') {
            header("MS-Server-ActiveSync: 8.3");
        }

        // Create response
        $imp = new DOMImplementation();

        // Creates a DOMDocumentType instance
        $dtd = $imp->createDocumentType('AirSync', "-//AIRSYNC//DTD AirSync//EN", "http://www.microsoft.com/");

        // Creates a DOMDocument instance
        $_outputDom = $imp->createDocument('uri:Ping', 'Ping', $dtd);
        $_outputDom->formatOutput = false;
        $_outputDom->encoding = 'utf-8';
        $_outputDom->documentElement->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:AirSyncBase', 'uri:AirSyncBase');

        $ping = $_outputDom->documentElement;
        $ping->appendChild($_outputDom->createElementNS('uri:Ping', 'Status', $status));
        if ($status === self::STATUS_CHANGES_FOUND) {
            $folders = $ping->appendChild($_outputDom->createElementNS('uri:Ping', 'Folders'));

            foreach ($foldersWithChanges as $changedFolder) {
                $folder = $folders->appendChild($_outputDom->createElementNS('uri:Ping', 'Folder', $changedFolder->folderid));
            }
        }

        $outputStream = fopen("php://temp", 'r+');
        $encoder = new Wbxml_Encoder($outputStream, 'UTF-8', 3);
        // Creates an instance of the DOMImplementation class
        $encoder->encode($_outputDom);

        header("Content-Type: application/vnd.ms-sync.wbxml");

        rewind($outputStream);
        fpassthru($outputStream);
        exit(0);
    }
    
    protected function _authenticate($_username, $_password, $_ipAddress)
    {
        $pos = strrchr($_username, '\\');
        
        if($pos !== false) {
            $username = substr(strrchr($_username, '\\'), 1);
        } else {
            $username = $_username;
        }
        
        return Tinebase_Controller::getInstance()->login($username, $_password, $_ipAddress, 'TineActiveSync');
    }

}
