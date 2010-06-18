<?php
/**
 * <after pointcut="emailing_ReadSendingAction::_execute"
 *        class="tracker_ReadSendingTracker" method="execute" />
 */
class tracker_ReadSendingTracker
{
	function execute($context, $request)
	{
		$subscriber = $this->getSubscriber($request);
		$sending = $this->getSending($request);
		if ($subscriber !== null && $sending !== null)
		{
			$actorsCollector = tracker_ActorsCollector::getInstance();
			$actorsCollector->addActorId("emailing_subscriber:".$subscriber->getId());
			$actorsCollector->addActorId("emailing_sending:".$sending->getId());
			tracker_Logger::log("emailing_readsending");
		}
	}
}