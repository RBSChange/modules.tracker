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
		// TODO OK : this should use some extra methods on PersistentProvider to permit OCI and others....
		// TODO: this is a very naive approach (..)
		$pp = f_persistentdocument_PersistentProvider::getInstance();
		$con = $pp->getTrackerConnection();
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

	/**
	 * @param Integer $documentId
	 * @return f_persistentdocument_PersistentTreeNode
	 */
	//	public function getParentNodeForPermissions($documentId)
	//	{
	//		// Define this method to handle permissions on a virtual tree node. Example available in list module.
	//	}
}