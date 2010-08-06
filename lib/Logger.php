<?php
class tracker_Logger
{
	private static $mongoDB = null;
	private static $mongoCollection = null;
	private static $methodToUse = null;
	//private static $shutdownRegistered = false;
	//private static $logs = array();
	
	static function log($event, $vars = array())
	{
		$collector = tracker_ActorsCollector::getInstance();
		$actorIds = $collector->getActorIds();
		$sessionId = $collector->getSessionId();
		/*$actorIdsStr = (f_util_ArrayUtils::isNotEmpty($actorIds)) ? join(",", $actorIds) : null;
		f_persistentdocument_PersistentProvider::getInstance()->track($sessionId, $event, $actorIdsStr, JsonService::getInstance()->encode($vars));*/
		
		//self::$logs[] = array("sessionId" => $sessionId, "event" => $event, "actorIds" => $actorIds, "vars" => $vars);
		
		if (self::$methodToUse === null)
		{
			if (defined("TRACKER_MODE") && TRACKER_MODE == "mysql")
			{
				self::$methodToUse = "trackWithMySql";
			}
			else 
			{
				self::$methodToUse = "trackWithMongo";
			}
		}
		$track = self::$methodToUse;
		self::$track($sessionId, $event, $actorIds, $vars);
	}
	
	/*static function shutdownLog()
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
	}*/
	
	private static function trackWithMySql($sessionId, $event, $actorIds, $vars)
	{
		$actorIdsStr = (f_util_ArrayUtils::isNotEmpty($actorIds)) ? join(",", $actorIds) : null;
		f_persistentdocument_PersistentProvider::getInstance()->track($sessionId, $event, $actorIdsStr, JsonService::getInstance()->encode($vars));
	}
	
	private static function trackWithMongo($sessionId, $event, $actorIds, $vars)
	{
		//array_push($actorIds, $sessionId);
		$actorIds = array_values($actorIds);
		$actorIds[] = $sessionId;
		$vars["time"] = time();
		try
		{
			self::getMongo()->insert(array("event" => $event, "actorIds" => $actorIds, "vars" => $vars));
			/*self::getMongo()->update(array("sessionId" => $sessionId), 
				array("event" => $event, "actorIds" => $actorIds, "vars" => $vars), 
				array("upsert" => true, "safe" => true));*/
		}
		catch (MongoCursorException $e)
		{
			Framework::exception($e);
		}
	}
	
	/**
	 * @return MongoCollection
	 */
	private static function getMongo()
	{
		if (self::$mongoDB === null)
		{
			$connectionString = null;
			$config = Framework::getConfiguration("mongoDB");
			
			if (isset($config["authentication"]["username"]) && isset($config["authentication"]["password"]) && 
				$config["authentication"]["username"] !== '' && $config["authentication"]["password"] !== '')
			{
				$connectionString .= $config["authentication"]["username"].':'.$config["authentication"]["password"].'@';
			}
			
			$connectionString .= implode(",", $config["serversCacheService"]);
			
			if ($connectionString != null)
			{
				$connectionString = "mongodb://".$connectionString;
			}
			
			try
			{
				self::$mongoDB = new Mongo($connectionString, array("persistent" => "mongo"));
				self::$mongoCollection = self::$mongoDB->$config["database"]["name"]->trackerLogs;
			}
			catch (MongoConnnectionException $e)
			{
				Framework::exception($e);
			}
		}
		return self::$mongoCollection;
	}
	
	/*private function registerShutdown()
	{
		if (!self::$shutdownRegistered)
		{
			register_shutdown_function(array('tracker_Logger','shutdownLog'));
			self::$shutdownRegistered = true;
		}
	}*/
}