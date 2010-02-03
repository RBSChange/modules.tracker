<?php
class tracker_Logger
{
	static function log($event, $vars = array())
	{
		$collector = tracker_ActorsCollector::getInstance();
		$actorIds = $collector->getActorIds();
		$sessionId = $collector->getSessionId();
		$actorIdsStr = (f_util_ArrayUtils::isNotEmpty($actorIds)) ? join(",", $actorIds) : null;
		f_persistentdocument_PersistentProvider::getInstance()->track($sessionId, $event, $actorIdsStr, JsonService::getInstance()->encode($vars));
	}
}