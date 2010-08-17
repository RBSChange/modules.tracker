<?php
class tracker_Logger
{
	private static $shutdownRegistered = false;
	private static $logs = array();
	
	static function log($event, $vars = array())
	{
		self::registerShutdown();
		$collector = tracker_ActorsCollector::getInstance();
		$actorIds = $collector->getActorIds();
		$sessionId = $collector->getSessionId();
		self::$logs[] = array("sessionId" => $sessionId, "event" => $event, "actorIds" => $actorIds, "vars" => $vars);
	}
	
	static function shutdownLog()
	{
		if (defined("TRACKER_MODE") && TRACKER_MODE == "mysql")
		{
			$track = "trackWithMySql";
		}
		else 
		{
			$track = "trackWithMongo";
		}
		
		self::$track();
	}
	
	private static function trackWithMySql()
	{
		foreach (self::$logs as $log)
		{
			$actorIdsStr = (f_util_ArrayUtils::isNotEmpty($log["actorIds"])) ? join(",", $log["actorIds"]) : null;
			f_persistentdocument_PersistentProvider::getInstance()->track($log["sessionId"], $log["event"], $actorIdsStr, JsonService::getInstance()->encode($log["vars"]));
		}
	}
	
	private static function trackWithMongo()
	{
		if (f_util_ArrayUtils::isNotEmpty(self::$logs))
		{
			$mongoCollection = f_MongoProvider::getInstance()->getWriteMongo()->trackerLogs;
			foreach (self::$logs as $log)
			{
				$actorIds = array_values($log["actorIds"]);
				$actorIds[] = $log["sessionId"];
				$log["vars"]["time"] = time();
				/*try
				{*/
					$mongoCollection->insert(array("event" => $log["event"], "actorIds" => $actorIds, "vars" => $log["vars"]));
				/*}
				catch (MongoCursorException $e)
				{
					Framework::exception($e);
				}*/
			}
		}
	}
	
	private static function registerShutdown()
	{
		if (!self::$shutdownRegistered)
		{
			register_shutdown_function(array('tracker_Logger','shutdownLog'));
			self::$shutdownRegistered = true;
		}
	}
}