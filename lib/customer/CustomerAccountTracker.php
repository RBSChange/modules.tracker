<?php
/**
 * <after-returning pointcut="customer_BlockCreateaccountAction::executeSave"
 *        class="tracker_CustomerAccountTracker" method="createFromCreateBlock" />
 * <after-returning pointcut="order_BlockIdentifyStepAction::executeCreateAccount"
 *        class="tracker_CustomerAccountTracker" method="createFromIdentifyStep" />
 */
class tracker_CustomerAccountTracker
{
	/**
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @param customer_CustomerWrapperBean $customerWrapper
	 */
	function createFromCreateBlock($request, $response, customer_CustomerWrapperBean $customerWrapper)
	{
		tracker_Logger::log("customer_customer_create");
	}

	/**
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @param order_IdentifyStepBean $identifyStep
	 */
	function createFromIdentifyStep($request, $response, order_IdentifyStepBean $identifyStep)
	{
		tracker_Logger::log("customer_customer_create");
	}
}