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

class ActiveSync_NoPing
{
	const STATUS_NO_CHANGES_FOUND           = 1;
	const PING_TIMEOUT = 60;
	const PING_QUIET_INTERVAL = 170;

	protected $_defaultNameSpace = 'uri:Ping';
	protected $_documentElement = 'Ping';
	
	public static function handle()
	{
		$intervalStart = time();
		$_user = $_REQUEST['User'];
		$_device = $_REQUEST['DeviceId'];
		$_body = fopen('php://input', 'r');
		
		Tinebase_Core::setupConfig();
		
		// Server Timezone must be setup before logger, as logger has timehandling!
		Tinebase_Core::setupServerTimezone();
		
		Tinebase_Core::setupLogger();
		
		// Database Connection must be setup before cache because setupCache uses constant "SQL_TABLE_PREFIX"
		Tinebase_Core::setupDatabaseConnection();
		
		Syncope_Registry::set('deviceBackend',       new Syncope_Backend_Device(Tinebase_Core::getDb(), SQL_TABLE_PREFIX . 'acsync_'));
	
		$_deviceBackend       = Syncope_Registry::get('deviceBackend');
		
		$accountsController = Tinebase_User::getInstance();
		$device = $_deviceBackend->getUserDevice($accountsController->getFullUserByLoginName($_user)->accountId , $_device);
		
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
		
		// the client does not send a wbxml document, if the Ping parameters did not change compared with the last request
		if($requestBody instanceof DOMDocument) {
			$xml = simplexml_import_dom($requestBody);
			$xml->registerXPathNamespace('Ping', 'Ping');
		
			if(isset($xml->HeartBeatInterval)) {
				$device->pinglifetime = (int)$xml->HeartBeatInterval;
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
		
		do {
			//Tinebase_Core::getDb()->closeConnection();
			sleep($_pingtimeout);
		
			$device = $_deviceBackend->get($device->id);
			if (isset($device->lastping) && ($device->lastping->getTimestamp() > $pingDateTime->getTimestamp())){
				break;
			}
	
			$secondsLeft = $intervalEnd - time();
		} while(($secondsLeft - $_pingtimeout - 10) > 0);
		

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
		$_outputDom->encoding     = 'utf-8';
		$_outputDom->documentElement->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:AirSyncBase' , 'uri:AirSyncBase');
	
		$ping = $_outputDom->documentElement;
		$ping->appendChild($_outputDom->createElementNS('uri:Ping', 'Status', self::STATUS_NO_CHANGES_FOUND));
		
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
