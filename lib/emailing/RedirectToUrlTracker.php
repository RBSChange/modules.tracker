<?php
/**
 * <after pointcut="emailing_RedirectToUrlAction::_execute"
 *        class="tracker_RedirectToUrlTracker" method="execute" />
 */
class tracker_RedirectToUrlTracker
{
	function execute($context, $request)
	{
		$subscriber = $this->getSubscriber($request);
		$sending = $this->getSending($request);
		
		$actorsCollector = tracker_ActorsCollector::getInstance();
		$actorsCollector->addActorId("emailing_subscriber:".$subscriber->getId());
		$actorsCollector->addActorId("emailing_sending:".$sending->getId());
		
		$url = emailing_EmailingHelper::decryptUrl($request->getParameter('url'));
		tracker_Logger::log("emailing_redirecttourl", array("url" => $url));
	}
}