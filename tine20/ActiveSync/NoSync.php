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
 * class to handle ActiveSync Sync command with low processing and return nothing.
 * Workarround to avoid overloading of server. 
 * You can change the nosyncinterval value in config. The default is 300 seconds.
 * TODO: Remove this class when sync overload down 
 *
 * @package     ActiveSync
 */

class ActiveSync_NoSync
{
	const NO_SYNC_INTERVAL = 300;

	public static function handle()
	{
		$_user = $_REQUEST['User'];
		$_device = $_REQUEST['DeviceId'];
		$_body = fopen('php://input', 'r');

		if ($_SERVER['CONTENT_TYPE'] == 'application/vnd.ms-sync.wbxml') {
			// decode wbxml request
			try {
				$decoder = new Wbxml_Decoder($_body);
				$requestBody = $decoder->decode();
			} catch(Wbxml_Exception_UnexpectedEndOfFile $e) {
				$requestBody = NULL;
			}
		} else {
			$requestBody = $_body;
		}
		// input xml
		$xml = simplexml_import_dom($requestBody);
		
		$hasClientCommands = false;
		foreach ($xml->Collections->Collection as $xmlCollection) {
			if (isset($xmlCollection->Commands)) {
				$hasClientCommands = true;
				break;
			}
		}
		
		if (!$hasClientCommands) {
			if (PHP_SAPI !== 'cli') {
				header("MS-Server-ActiveSync: 8.3");
			}
		
			// Create response
			$imp = new DOMImplementation();
		
			// Creates a DOMDocumentType instance
			$dtd = $imp->createDocumentType('AirSync', "-//AIRSYNC//DTD AirSync//EN", "http://www.microsoft.com/");
		
			// Creates a DOMDocument instance
			$_outputDom = $imp->createDocument('uri:AirSync', 'Sync', $dtd);
			$_outputDom->formatOutput = false;
			$_outputDom->encoding     = 'utf-8';
			$_outputDom->documentElement->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:AirSyncBase' , 'uri:AirSyncBase');
		
			$sync = $_outputDom->documentElement;
		
			$collections = $sync->appendChild($_outputDom->createElementNS('uri:AirSync', 'Collections'));
		
			Tinebase_Core::setupConfig();
		
			// Server Timezone must be setup before logger, as logger has timehandling!
			Tinebase_Core::setupServerTimezone();
		
			Tinebase_Core::setupLogger();
		
			// Database Connection must be setup before cache because setupCache uses constant "SQL_TABLE_PREFIX"
			Tinebase_Core::setupDatabaseConnection();
		
			Syncope_Registry::set('deviceBackend',       new Syncope_Backend_Device(Tinebase_Core::getDb(), SQL_TABLE_PREFIX . 'acsync_'));
			Syncope_Registry::set('syncStateBackend',    new Syncope_Backend_SyncState(Tinebase_Core::getDb(), SQL_TABLE_PREFIX . 'acsync_'));
			Syncope_Registry::set('folderBackend',       new Syncope_Backend_Folder(Tinebase_Core::getDb(), SQL_TABLE_PREFIX . 'acsync_'));
		
			$_deviceBackend       = Syncope_Registry::get('deviceBackend');
			$_syncStateBackend    = Syncope_Registry::get('syncStateBackend');
			$_folderBackend       = Syncope_Registry::get('folderBackend');
		
			$accountsController = Tinebase_User::getInstance();
			$device = $_deviceBackend->getUserDevice($accountsController->getFullUserByLoginName($_user)->accountId , $_device);
		
			if (isset(Tinebase_Core::getConfig()->nosyncinterval)) {
				$_nosyncinterval = Tinebase_Core::getConfig()->nosyncinterval;
			} else {
				$_nosyncinterval = self::NO_SYNC_INTERVAL;
			}
			
			$now = new DateTime('now', new DateTimeZone('utc'));
			$force_full_sync = false;
		
			foreach ($xml->Collections->Collection as $xmlCollection) {
				try {
					$folder = $_folderBackend->getFolder($device, (string)$xmlCollection->CollectionId);
					$syncState = $_syncStateBackend->getSyncState($device, $folder->id);
				} catch(Syncope_Exception_NotFound $e) {
					$force_full_sync = true;
					break;
				}
				
				if ($syncState->pingfoundchanges == 0) {
					$force_full_sync = true;
					break;						
				}
						
				if ($syncState->counter < 3) {
					$force_full_sync = true;
					break;
				}
		
				if (isset($syncState->pendingdata) != '') {
					$force_full_sync = true;
					break;
				}
		
				if (($now->getTimestamp() - $syncState->lastsyncfull->getTimestamp()) > $_nosyncinterval) {
					$force_full_sync = true;
					break;
				}
		
				$collection = $collections->appendChild($_outputDom->createElementNS('uri:AirSync', 'Collection'));
				if (isset($xmlCollection->Class)) {
					$collection->appendChild($_outputDom->createElementNS('uri:AirSync', 'Class', $xmlCollection->Class));
				}
				$collection->appendChild($_outputDom->createElementNS('uri:AirSync', 'SyncKey', (int)$xmlCollection->SyncKey));
				$collection->appendChild($_outputDom->createElementNS('uri:AirSync', 'CollectionId', (string)$xmlCollection->CollectionId));
				$collection->appendChild($_outputDom->createElementNS('uri:AirSync', 'Status', 1)); //STATUS_SUCCESS
		
				// Nao eh mais necessario. Tem outra rotina que mata syncs antigos
				//$syncState->lastsync = $now;
				//$_syncStateBackend->update($syncState);
			}
		
			if (!$force_full_sync) {
				$outputStream = fopen("php://temp", 'r+');
				$encoder = new Wbxml_Encoder($outputStream, 'UTF-8', 3);
				// Creates an instance of the DOMImplementation class
				$encoder->encode($_outputDom);
		
				header("Content-Type: application/vnd.ms-sync.wbxml");
		
				rewind($outputStream);
				fpassthru($outputStream);
				exit(0);
			}
		}		
	}
}