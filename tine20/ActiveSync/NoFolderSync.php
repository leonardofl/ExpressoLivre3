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
 * class to handle ActiveSync FolderSync command with low processing.
 * Workarround to avoid overloading of server.
 * You can change the nosyncinterval value in config. The default is 300 seconds.
 * TODO: Remove this class when sync overload down
 *
 * @package     ActiveSync
 */
class ActiveSync_NoFolderSync {

    const STATUS_SUCCESS = 1;
    const NO_SYNC_INTERVAL = 300;

    protected $_defaultNameSpace = 'uri:FolderHierarchy';
    protected $_documentElement = 'FolderSync';

    public static function handle() {
        $force_full_sync = false;
        $_user = $_REQUEST['User'];
        $_device = $_REQUEST['DeviceId'];
        $_body = fopen('php://input', 'r');

        if ($_SERVER['CONTENT_TYPE'] == 'application/vnd.ms-sync.wbxml') {
            // decode wbxml request
            try {
                $decoder = new Wbxml_Decoder($_body);
                $requestBody = $decoder->decode();
            } catch (Wbxml_Exception_UnexpectedEndOfFile $e) {
                $requestBody = NULL;
                $force_full_sync = true;
            }
        } else {
            $requestBody = $_body;
        }

        $xml = simplexml_import_dom($requestBody);
        $syncKey = (int) $xml->SyncKey;

        if ($syncKey == 0)
            $force_full_sync = true;

        if (!$force_full_sync) {
            Tinebase_Core::setupConfig();

            // Server Timezone must be setup before logger, as logger has timehandling!
            Tinebase_Core::setupServerTimezone();

            Tinebase_Core::setupLogger();

            // Database Connection must be setup before cache because setupCache uses constant "SQL_TABLE_PREFIX"
            Tinebase_Core::setupDatabaseConnection();

            Syncope_Registry::set('deviceBackend', new Syncope_Backend_Device(Tinebase_Core::getDb(), SQL_TABLE_PREFIX . 'acsync_'));
            Syncope_Registry::set('syncStateBackend', new Syncope_Backend_SyncState(Tinebase_Core::getDb(), SQL_TABLE_PREFIX . 'acsync_'));

            $_deviceBackend = Syncope_Registry::get('deviceBackend');
            $_syncStateBackend = Syncope_Registry::get('syncStateBackend');

            $accountsController = Tinebase_User::getInstance();
            $device = $_deviceBackend->getUserDevice($accountsController->getFullUserByLoginName($_user)->accountId, $_device);

            $xml = simplexml_import_dom($requestBody);
            $syncKey = (int) $xml->SyncKey;

            if (($syncState = $_syncStateBackend->validate($device, 'FolderSync', $syncKey)) instanceof Syncope_Model_SyncState) {
                if ($syncState->pingfoundchanges == 0) {
                    $force_full_sync = true;
                }
                if (isset(Tinebase_Core::getConfig()->nosyncinterval)) {
                    $_nosyncinterval = Tinebase_Core::getConfig()->nosyncinterval;
                } else {
                    $_nosyncinterval = self::NO_SYNC_INTERVAL;
                }
                $now = new DateTime('now', new DateTimeZone('utc'));
                if (($now->getTimestamp() - $syncState->lastsync->getTimestamp()) > $_nosyncinterval) {
                    $force_full_sync = true;
                }
                if ($syncKey != $syncState->counter) {
                    $force_full_sync = true;
                }
            } else {
                $force_full_sync = true;
            }
        }

        if (!$force_full_sync) {
            if (PHP_SAPI !== 'cli') {
                header("MS-Server-ActiveSync: 8.3");
            }

            // Create response
            $imp = new DOMImplementation();

            // Creates a DOMDocumentType instance
            $dtd = $imp->createDocumentType('AirSync', "-//AIRSYNC//DTD AirSync//EN", "http://www.microsoft.com/");

            // Creates a DOMDocument instance
            $_outputDom = $imp->createDocument('uri:FolderHierarchy', 'FolderSync', $dtd);
            $_outputDom->formatOutput = false;
            $_outputDom->encoding = 'utf-8';
            $_outputDom->documentElement->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:AirSyncBase', 'uri:AirSyncBase');

            $folderSync = $_outputDom->documentElement;
            $folderSync->appendChild($_outputDom->createElementNS('uri:FolderHierarchy', 'Status', self::STATUS_SUCCESS));
            $folderSync->appendChild($_outputDom->createElementNS('uri:FolderHierarchy', 'SyncKey', $syncState->counter));
            $changes = $folderSync->appendChild($_outputDom->createElementNS('uri:FolderHierarchy', 'Changes'));
            $changes->appendChild($_outputDom->createElementNS('uri:FolderHierarchy', 'Count', 0));

            $outputStream = fopen("php://temp", 'r+');
            $encoder = new Wbxml_Encoder($outputStream, 'UTF-8', 3);

            $encoder->encode($_outputDom);

            header("Content-Type: application/vnd.ms-sync.wbxml");

            rewind($outputStream);
            fpassthru($outputStream);
            exit(0);
        }
    }

}
