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
	
	private static function registerShutdown()
	{
		if (!self::$shutdownRegistered)
		{
			register_shutdown_function(array('tracker_Logger','shutdownLog'));
			self::$shutdownRegistered = true;
		}
	}
	
	static function shutdownLog()
	{
		if (f_util_ArrayUtils::isEmpty(self::$logs))
		{
			return;
		}
		
		if (Framework::hasConfiguration('modules/tracker/mongoDB'))
		{
			$logger = new tracker_LoggerMongo();
		}
		else
		{
			$logger = new tracker_LoggerPDOMysql();
		}
		$logger->save(self::$logs);
	}
}

class tracker_LoggerPDOMysql 
{
	/**
	 * @param array<String, String> $connectionInfos
	 * @return PDO
	 */
	protected function getConnection($connectionInfos)
	{
		$protocol = 'mysql';
		$dsnOptions = array();
		
		$database = isset($connectionInfos['database']) ? $connectionInfos['database'] : null;
		$password = isset($connectionInfos['password']) ? $connectionInfos['password'] : null;
		$username = isset($connectionInfos['user']) ? $connectionInfos['user'] : null;
		
		if ($database !== null)
		{
			$dsnOptions[] = 'dbname='.$database;	
		}
		$unix_socket = isset($connectionInfos['unix_socket']) ? $connectionInfos['unix_socket'] : null;
		if ($unix_socket != null)
		{
			$dsnOptions[] = 'unix_socket='.$unix_socket;
		}
		else
		{
			$host = isset($connectionInfos['host']) ? $connectionInfos['host'] : 'localhost';
			$dsnOptions[] = 'host='.$host;
			$port = isset($connectionInfos['port']) ? $connectionInfos['port'] : 3306;
			$dsnOptions[] = 'port='.$port;
		}
		
		$dsn = $protocol.':'.join(';', $dsnOptions);
		$pdo = new PDO($dsn, $username, $password);
		$emulatePrepares = isset($connectionInfos['emulate_prepares']) ? f_util_Convert::toBoolean($connectionInfos['emulate_prepares']) : false;
		if ($emulatePrepares == true)
		{
			$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
		}
		return $pdo;
	}
	
	/**
	 * @return PDO
	 */
	private function getTrackerConnection()
	{
		if (Framework::hasConfiguration('databases/tracker'))
		{
			$connectionInfos = Framework::getConfiguration('databases/tracker');
		}
		else
		{
			$connectionInfos = Framework::getConfiguration('databases/webapp');
		}
		return $this->getConnection($connectionInfos);
	}	
	
	/**
	 * @param PDO $connection
	 * @param string $sessionId
	 * @param string $event
	 * @param string $actorIds
	 * @param string $vars
	 * @return boolean
	 */
	private function track($connection, $sessionId, $event, $actorIds, $vars)
	{
		$stmt = $connection->prepare("INSERT INTO tracker_logs VALUES (NULL, :date, :sessionId, :event, :actorIds, :vars)");
		if ($stmt === false)
		{
			$errorCode = $connection->errorCode();
			$msg = "Driver ERROR Code (". $errorCode . ") : " . var_export($connection->errorInfo(), true)."\n";
			Framework::error($msg);
			return false;
		}
		$stmt->bindValue(":date", time(), PDO::PARAM_INT);
		$stmt->bindValue(":sessionId", $sessionId);
		$stmt->bindValue(":event", $event);
		$stmt->bindValue(":actorIds", $actorIds);
		$stmt->bindValue(":vars", $vars);
		if (!$stmt->execute())
		{
			$errorCode = $stmt->errorCode();
			$msg = "Driver ERROR Code (". $errorCode . ") : " . var_export($stmt->errorInfo(), true)."\n";
			Framework::error($msg);
			return false;
		}
		return true;
	}

	/**
	 * @param array $logs
	 */
	public function save($logs)
	{
		$pdo = $this->getTrackerConnection();
		foreach ($logs as $log)
		{
			Framework::info(__METHOD__ . ' ' . $log["event"]);
			$actorIdsStr = (f_util_ArrayUtils::isNotEmpty($log["actorIds"])) ? join(",", $log["actorIds"]) : null;
			if (!$this->track($pdo, $log["sessionId"], $log["event"], $actorIdsStr, JsonService::getInstance()->encode($log["vars"])))
			{
				break;
			}
		}
	}
	
	public function computeLogs()
	{
		$con = $this->getTrackerConnection();
		$maxrow = $con->query("select max(log_id) from tracker_logs_computed")->fetchAll(PDO::FETCH_COLUMN);
		$maxId = ($maxrow[0] !== null) ? $maxrow[0] : 0;

		$getEquals = $con->prepare("select actor2 from tracker_actor_equals where actor1 = :actor1");
		$insertComputed = $con->prepare("insert into tracker_logs_computed values(:logId, :actorType, :actorIdValue)");
		$getComputedByActorId = $con->prepare("select log_id from tracker_logs_computed where log_id <= $maxId and actor_type = :actorType and actor_id = :actorIdValue");
		$equals = $con->prepare("insert into tracker_actor_equals values(:actor1, :actor2)");
		$insertLogVar = $con->prepare("insert into tracker_logs_vars values(:logId, :name, :value)");
		
		// Collect actorId "equalities"
		foreach ($con->query("select distinct(actor_ids), session_id from tracker_logs where actor_ids is not null and id > $maxId")->fetchAll(PDO::FETCH_NUM) as $row)
		{
			$actorIds = explode(",", $row[0]);
			$actorIds[] = $row[1];

			$actorCount = count($actorIds);
			for($i = 0; $i < $actorCount; $i++)
			{
				for($j = $i+1; $j < $actorCount; $j++)
				{
					echo "*********\n";
					$equals->bindValue(":actor1", $actorIds[$i]);
					$equals->bindValue(":actor2", $actorIds[$j]);
					$equals->execute();
					$equals->bindValue(":actor1", $actorIds[$j]);
					$equals->bindValue(":actor2", $actorIds[$i]);

					if ($equals->execute() > 0)
					{
						echo "Stored ".$actorIds[$i]." = ".$actorIds[$j]."\n";
						$getEquals->bindValue(":actor1", $actorIds[$i]);
						$getEquals->execute();
						$ids = $getEquals->fetchAll(PDO::FETCH_COLUMN);
						$getEquals->bindValue(":actor1", $actorIds[$j]);
						$getEquals->execute();
						$ids = array_merge($ids, $getEquals->fetchAll(PDO::FETCH_COLUMN));
						$idsCount = count($ids);
						
						for ($k = 0; $k < $idsCount; $k++)
						{
							for ($l = $k+1; $l < $idsCount; $l++)
							{
								if (($ids[$k] == $actorIds[$i] && $ids[$l] == $actorIds[$j])
								|| ($ids[$k] == $actorIds[$l] && $ids[$l] == $actorIds[$i]))
								{
									continue;
								}

								$equals->bindValue(":actor1", $ids[$k]);
								$equals->bindValue(":actor2", $ids[$l]);
								$equals->execute();
								$equals->bindValue(":actor1", $ids[$l]);
								$equals->bindValue(":actor2", $ids[$k]);
								if ($equals->execute() > 0)
								{
									echo "Implies ".$ids[$k]." = ".$ids[$l]."\n";
									// Duplicate existing logs_computed lines that implies $ids[$k] or $ids[$l]
									foreach (array($ids[$k], $ids[$l]) as $newId)
									{
										list($actorType, $actorIdValue) = explode(":", $newId);
										if ($actorIdValue === null)
										{
											$actorIdValue = $actorType;
											$actorType = "session";
										}
										$getComputedByActorId->bindValue(":actorType", $actorType);
										$getComputedByActorId->bindValue(":actorIdValue", $actorIdValue);
										$getComputedByActorId->execute();
										foreach ($getComputedByActorId->fetchAll(PDO::FETCH_COLUMN) as $oldLogId)
										{
											$insertComputed->bindValue(":logId", $oldLogId);
											$insertComputed->bindValue(":actorType", $actorType);
											$insertComputed->bindValue(":actorIdValue", $actorIdValue);
											$insertComputed->execute();
										}
									}
								}
							}
						}
					}
				}
			}
		}

		$jsonService = JsonService::getInstance();
		foreach ($con->query("select id, session_id, actor_ids, vars from tracker_logs where id > $maxId")->fetchAll(PDO::FETCH_ASSOC) as $logRow)
		{
			echo "Process log ".$logRow["id"]."\n";

			// Explode log vars so we can request them
			$logVars = $jsonService->decode($logRow["vars"]);
			if (f_util_ArrayUtils::isNotEmpty($logVars))
			{
				
				$insertLogVar->bindValue(":logId", $logRow["id"]);
				foreach ($logVars as $key => $value)
				{
					$insertLogVar->bindValue(":name", $key);
					$insertLogVar->bindValue(":value", $value);
					$insertLogVar->execute();
				}
			}

			// Get computed actor ids using collected equalities
			if ($logRow["actor_ids"] !== null)
			{
				$actorIds = explode(",", $logRow["actor_ids"]);
			}
			else
			{
				$actorIds = array();
			}
			$actorIds[] = $logRow["session_id"];


			$computedActorIds = $actorIds;
			foreach ($actorIds as $actorId)
			{
				$getEquals->bindValue(":actor1", $actorId);
				$getEquals->execute();
				$computedActorIds = array_merge($computedActorIds, $getEquals->fetchAll(PDO::FETCH_COLUMN));
			}
			$computedActorIds = array_unique($computedActorIds);

			// Create computed logs
			$insertComputed->bindValue(":logId", $logRow["id"]);
			foreach ($computedActorIds as $actorId)
			{
				list($actorType, $actorIdValue) = explode(":", $actorId);
				if ($actorIdValue === null)
				{
					$actorIdValue = $actorType;
					$actorType = "session";
				}
				$insertComputed->bindValue(":actorType", $actorType);
				$insertComputed->bindValue(":actorIdValue", $actorIdValue);
				$insertComputed->execute();
			}
		}
	}
}

class tracker_LoggerMongo 
{
	
	/**
	 * @return f_MongoProvider
	 */
	private function getTrackerConnection()
	{
		return new f_MongoProvider(Framework::getConfiguration('modules/tracker/mongoDB'));
	}
	/**
	 * @param array $logs
	 */
	private function save($logs)
	{
		$provider = $this->getTrackerConnection();
		$mongoCollection = $provider->getCollection('trackerLogs', true);
		foreach ($logs as $log)
		{
			$actorIds = array_values($log["actorIds"]);
			$actorIds[] = $log["sessionId"];
			$log["vars"]["time"] = time();
			$mongoCollection->insert(array("event" => $log["event"], "actorIds" => $actorIds, "vars" => $log["vars"]));
		}
	}	
	
	public function computeLogs()
	{
		// database connection
		$mongo = $this->getTrackerConnection();
		$trackCol =  $mongo->getCollection('trackerLogs', true);
		$computeCol = $mongo->getCollection('computedTrackerLogs', true);
		unset($mongo);
		
		$logs = $trackCol->find();
		$compute = $computeCol->find();
		
		echo "Processing...\n";
		foreach ($logs as $log)
		{
			$isComputed = false;
			foreach ($compute as $c)
			{
				$isEqual = false;
				foreach ($log["actorIds"] as $act)
				{
					if (in_array($act, $c["actorIds"]))
					{
						$isEqual = true;
						break;
					}
				}
				
				if ($isEqual)
				{	
					$actorIds = array_unique(array_merge($c["actorIds"], $log["actorIds"]));
					$logEvent = array($log["event"] => array($log["vars"]));
					$c["events"] = array_merge_recursive($c["events"], $logEvent);
					try
					{
						$computeCol->update(array("_id" => new MongoId($c["_id"])), 
							array('$set' => array("actorIds" => $actorIds, "events" => $c["events"])),
							array("safe" => true));
					}
					catch (MongoCursorException $e)
					{
						echo " => Update failed\n";
						Framework::exception($e);
					}
					$isComputed = true;
					break;
				}
			}
			
			if (!$isComputed)
			{
				try
				{
					$computeCol->insert(array("actorIds" => $log["actorIds"], "events" => array($log["event"] => array($log["vars"]))),
						array("safe" => true));
				}
				catch (MongoCursorException $e)
				{
					echo " => Insert failed\n";
					Framework::exception($e);
				}
			}
		}
		$this->consolidate($computeCol);
		echo "computedTrackerLogs collection updated\n";
		$trackCol->drop();
		echo "trackerLogs collection cleaned\n";
	}
	
	/**
	 * @param MongoCollection $computeCol
	 */
	private function consolidate($computeCol)
	{
		$compute = $computeCol->find();
		
		foreach ($compute as $c)
		{
			foreach ($compute as $c2)
			{
				if ($c["_id"] != $c2["_id"])
				{
					$isEqual = false;
					foreach ($c["actorIds"] as $act)
					{
						if (in_array($act, $c2["actorIds"]))
						{
							$isEqual = true;
							break;
						}
					}
					if ($isEqual)
					{
						$actorIds = array_unique(array_merge($c["actorIds"], $c2["actorIds"]));
						$events = array_merge_recursive($c["events"], $c2["events"]);
						try
						{
							$computeCol->insert(array("actorIds" => $actorIds, "events" => $events),
								array("safe" => true));
							$computeCol->remove(array("_id" => new MongoId($c["_id"])));
							$computeCol->remove(array("_id" => new MongoId($c2["_id"])));
						}
						catch (MongoCursorException $e)
						{
							echo " => Consolidation failed\n";
							Framework::exception($e);
						}
					}
				}
			}
		}
		echo "Consolidation\n";
	}
	
	public function getProductInfos($product, $minTime = null, $maxTime = null)
	{
		$mongo = $this->getTrackerConnection()->getCollection('computedTrackerLogs');
		if ($minTime === null)
		{
			$minTime = 0;
		}
		if ($maxTime === null)
		{
			$maxTime = time();
		}
		
		$object = $mongo->find(array("events.order_cart_addproduct.product" => $product, 
									 "events.order_cart_addproduct.time" => array('$gt' => $minTime, '$lt' => $maxTime)), 
							   array("actorIds" => true, "events.order_cart_addproduct.quantity" => true, 
							   		 "events.order_cart_addproduct.time" => true, 
							   		 "events.order_cart_addproduct.product" => true));
		
		$results = array();
		foreach ($object as $result)
		{
			$infos = array();
			foreach ($result["events"]["order_cart_addproduct"] as $res)
			{
				if ($res["product"] == $product && $res["time"] > $minTime && $res["time"] < $maxTime)
				{
					$infos[] = array("time" => $res["time"], "quantity" => $res["quantity"]);
				}
			}
			
			$results[] = array("actorIds" => $result["actorIds"], "infos" => $infos);
		}
		//f_util_FileUtils::writeAndCreateContainer(f_util_FileUtils::buildLogPath("test.log"), var_export($results, true), f_util_FileUtils::OVERRIDE);
		return f_util_ArrayUtils::isEmpty($results) ? null : $results;
	}
	
	public function getUrlInfos($url, $minTime = null, $maxTime = null)
	{
		$mongo = $this->getTrackerConnection()->getCollection('computedTrackerLogs');
		
		if ($minTime === null)
		{
			$minTime = 0;
		}
		if ($maxTime === null)
		{
			$maxTime = time();
		}
		
		$object = $mongo->find(array("events.website_viewurl.url" => $url, 
									 "events.website_viewurl.time" => array('$gt' => $minTime, '$lt' => $maxTime)), 
							   array("actorIds" => true, "events.website_viewurl.method" => true, 
							   		 "events.website_viewurl.time" => true, "events.website_viewurl.url" => true));
		
		$results = array();
		foreach ($object as $result)
		{
			$infos = array();
			foreach ($result["events"]["website_viewurl"] as $res)
			{
				if ($res["url"] == $url && $res["time"] > $minTime && $res["time"] < $maxTime)
				{
					$infos[] = array("time" => $res["time"], "method" => $res["method"]);
				}
			}
			
			$results[] = array("actorIds" => $result["actorIds"], "infos" => $infos);
		}
		//f_util_FileUtils::writeAndCreateContainer(f_util_FileUtils::buildLogPath("test.log"), var_export($results, true), f_util_FileUtils::OVERRIDE);
		return f_util_ArrayUtils::isEmpty($results) ? null : $results;
	}
	
	public function getUserInfos($user, $minTime = null, $maxTime = null)
	{
		$mongo = $this->getTrackerConnection()->getCollection('computedTrackerLogs');
		
		if ($minTime === null)
		{
			$minTime = 0;
		}
		if ($maxTime === null)
		{
			$maxTime = time();
		}
		if (!is_array($user))
		{
			$user = array($user);
		}
		
		$object = $mongo->find(array("actorIds" => array('$in' => $user)));
		
		$results = array();
		foreach ($object as $result)
		{
			$infos = array();
			foreach ($result["events"] as $name => $event)
			{
				foreach ($event as $res)
				{
					if ($res["time"] > $minTime && $res["time"] < $maxTime)
					{
						$infos[$name][] = $res;
					}
				}
			}
			if (f_util_ArrayUtils::isNotEmpty($infos))
			{
				$results[] = array("actorIds" => $result["actorIds"], "infos" => $infos);
			}
		}
		//f_util_FileUtils::writeAndCreateContainer(f_util_FileUtils::buildLogPath("test.log"), var_export($results, true), f_util_FileUtils::OVERRIDE);
		return f_util_ArrayUtils::isEmpty($results) ? null : $results;
	}
	
	public function getPeriodInfos($minTime = null, $maxTime = null)
	{
		$mongo = $this->getTrackerConnection()->getCollection('computedTrackerLogs');
		
		if ($minTime === null)
		{
			$minTime = 0;
		}
		if ($maxTime === null)
		{
			$maxTime = time();
		}
		
		$object = $mongo->find(array('$where' => new MongoCode("for each(event in this.events)
																{
																	for each(i in event)
																	{
																		if(i.time > minTime && i.time < maxTime)
																		{
																			return true;
																		}
																	}
																}
																return false;", 
																array("minTime" => $minTime,
																	  "maxTime" => $maxTime))));
		
		$results = array();
		foreach ($object as $result)
		{
			$infos = array();
			foreach ($result["events"] as $name => $event)
			{
				foreach ($event as $res)
				{
					if ($res["time"] > $minTime && $res["time"] < $maxTime)
					{
						$infos[$name][] = $res;
					}
				}
			}
			if (f_util_ArrayUtils::isNotEmpty($infos))
			{
				$results[] = array("actorIds" => $result["actorIds"], "infos" => $infos);
			}
		}
		//f_util_FileUtils::writeAndCreateContainer(f_util_FileUtils::buildLogPath("test.log"), var_export($results, true), f_util_FileUtils::OVERRIDE);
		return f_util_ArrayUtils::isEmpty($results) ? null : $results;
	}
}