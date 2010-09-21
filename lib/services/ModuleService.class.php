<?php
/**
 * @package modules.tracker.lib.services
 */
class tracker_ModuleService extends ModuleBaseService
{
	/**
	 * Singleton
	 * @var tracker_ModuleService
	 */
	private static $instance = null;

	/**
	 * @return tracker_ModuleService
	 */
	public static function getInstance()
	{
		if (is_null(self::$instance))
		{
			self::$instance = self::getServiceClassInstance(get_class());
		}
		return self::$instance;
	}

	function computeLogs()
	{
		if (Framework::hasConfiguration('modules/tracker/mongoDB'))
		{
			$provider = new tracker_LoggerMongo();
			$provider->computeLogs();
		}
		else
		{
			$provider = new tracker_LoggerPDOMysql();
			$provider->computeLogs();
		}
	}
	
	/**
	 * @param Integer $documentId
	 * @return f_persistentdocument_PersistentTreeNode
	 */
	//	public function getParentNodeForPermissions($documentId)
	//	{
	//		// Define this method to handle permissions on a virtual tree node. Example available in list module.
	//	}
}