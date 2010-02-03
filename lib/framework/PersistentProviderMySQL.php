<?php
class tracker_PersistentProviderMySQL extends f_persistentdocument_PersistentProviderMySql
{
	/**
	 * @var PDO
	 */
	private $trackConnection;

	function track($sessionId, $event, $actorIds, $vars)
	{
		$connection = $this->getTrackerConnection();
		$stmt = $connection->prepare("INSERT INTO tracker_logs VALUES (NULL, :date, :sessionId, :event, :actorIds, :vars)");
		if ($stmt === false)
		{
			$errorCode = $connection->errorCode();
			$msg = "Driver ERROR Code (". $errorCode . ") : " . var_export($connection->errorInfo(), true)."\n";
			throw new f_DatabaseException($errorCode, $msg);
		}
		$stmt->bindValue(":date", time(), PDO::PARAM_INT);
		$stmt->bindValue(":sessionId", $sessionId);
		$stmt->bindValue(":event", $event);
		$stmt->bindValue(":actorIds", $actorIds);
		$stmt->bindValue(":vars", $vars);
		if (!$stmt->execute())
		{
			$this->showError($stmt);
		}
	}

	/**
	 * @return PDO
	 */
	function getTrackerConnection()
	{
		if ($this->trackConnection === null)
		{
			$trackerInfo = Framework::getConfiguration('databases/tracker');
			$this->trackConnection = $this->getConnection($trackerInfo);
			register_shutdown_function(array($this, "closeTrackerConnection"));
		}
		return $this->trackConnection;
	}

	private function closeTrackerConnection()
	{
		$this->trackConnection = null;
	}
}