<?php
interface tracker_ActorCollector
{
	/**
	 * @return String
	 */
	function getActorId();

	/**
	 * @return String
	 */
	function getKey();
}

class tracker_ActorsCollector
{
	/**
	 * @var tracker_ActorCollector[]
	 */
	private $collectors;

	/**
	 * @var tracker_ActorsCollector
	 */
	private static $instance;

	private $actorIds = array();
	private $actorIdsByCollector;
	private $sessionId;

	private function __construct()
	{
		// emtpy
	}

	/**
	 * @return tracker_ActorsCollector
	 */
	function getInstance()
	{
		if (self::$instance === null)
		{
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * @return String[]
	 */
	function getActorIds()
	{
		if ($this->actorIdsByCollector === null)
		{
			$this->loadCollectors();
			$actorIds = array();
			$sessionCollector = new tracker_SessionActorCollector();
			foreach ($this->collectors as $collector)
			{
				$actorId = $collector->getActorId();
				if ($actorId !== null)
				{
					$actorIds[$collector->getKey()] = $collector->getKey().":".$actorId;
				}
			} 
			$this->actorIdsByCollector = $actorIds;
			$this->sessionId = $sessionCollector->getActorId();
		}

		return array_unique(array_merge($this->actorIds, $this->actorIdsByCollector));
	}
	
	/**
	 * @param String $actorId
	 */
	function addActorId($actorId)
	{
		$this->actorIds[] = $actorId;
	}
	
	/**
	 * @return String
	 */
	function getSessionId()
	{
		return $this->sessionId;
	}
	
	// private methods

	private function loadCollectors()
	{
		$collectors = array();
		foreach (Framework::getConfigurationValue("modules/tracker/collectors") as $collectorName)
		{
			$collectors[] = new $collectorName();
		}
		$this->collectors = $collectors;
	}
}

class tracker_SessionActorCollector implements tracker_ActorCollector
{
	/**
	 * @return String
	 */
	function getActorId()
	{
		if (!isset($_SESSION["tracker_firsthittime"]))
		{
			$_SESSION["tracker_firsthittime"] = time();
		}
		return $_SESSION["tracker_firsthittime"]."_".session_id();
	}

	/**
	 * @return String
	 */
	function getKey()
	{
		return "ss";
	}
}

class tracker_FrontendUserActorCollector implements tracker_ActorCollector
{
	/**
	 * @return String
	 */
	function getActorId()
	{
		$frontUser = users_UserService::getInstance()->getCurrentFrontEndUser();
		if ($frontUser === null)
		{
			return null;
		}
		return $frontUser->getId();
	}

	/**
	 * @return String
	 */
	function getKey()
	{
		return "users_frontenduser";
	}
}

class tracker_BackendUserActorCollector implements tracker_ActorCollector
{
	/**
	 * @return String
	 */
	function getActorId()
	{
		$backUser = users_UserService::getInstance()->getCurrentBackEndUser();
		if ($backUser === null)
		{
			return null;
		}
		return $backUser->getId();
	}

	/**
	 * @return String
	 */
	function getKey()
	{
		return "users_backenduser";
	}
}

class tracker_CustomerActorCollector implements tracker_ActorCollector
{
	/**
	 * @return String
	 */
	function getActorId()
	{
		$customer = customer_CustomerService::getInstance()->getCurrentCustomer();
		if ($customer === null)
		{
			return null;
		}
		return $customer->getId();
	}

	/**
	 * @return String
	 */
	function getKey()
	{
		return "customer_customer";
	}
}