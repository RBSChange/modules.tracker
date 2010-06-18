<?php
/**
 * <before pointcut="ads_ClickAction::_execute"
 * 		class="tracker_ClickOnBannerTracker" method="createFromClickAction" />
 */
class tracker_ClickOnBannerTracker
{
	/**
	 * @param Context $context
	 * @param Request $request
	 */
	public function createFromClickAction($request, $response)
	{
		tracker_Logger::log("ads_banner_click", array('bannerId' => $request->getParameter('banner')));
	}
}