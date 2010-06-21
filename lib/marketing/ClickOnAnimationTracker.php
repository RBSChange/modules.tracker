<?php
/**
 * <before pointcut="marketing_ClickOnAnimationAction::_execute"
 * 		class="tracker_ClickOnAnimationTracker" method="createFromClickAction" />
 */
class tracker_ClickOnAnimationTracker
{
	/**
	 * @param Context $context
	 * @param Request $request
	 */
	public function createFromClickAction($request, $response)
	{
		tracker_Logger::log("marketing_animation_click", array('productId' => $request->getParameter('productId'), 'animationId' => $request->getParameter('animationId')));
	}
}