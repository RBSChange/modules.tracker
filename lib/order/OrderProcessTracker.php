<?php
/**
 * <before pointcut="order_BlockIdentifyStepAction::execute"
 *        class="tracker_OrderProcessTracker" method="start" />
 * <before pointcut="payment_ConnectorService::setPaymentResult"
 *        class="tracker_OrderProcessTracker" method="end" />
 */
class tracker_OrderProcessTracker
{
	/**
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 */
	function start($request, $response)
	{
		tracker_Logger::log("order_orderprocess_start");
	}

	/**
	 * @param payment_Transaction $response
	 * @param payment_Order $order
	 */
	function end($response, $order)
	{
		$user = $order->getPaymentUser();
		$customer = customer_CustomerService::getInstance()->getByUser($user);
		$actorsCollector = tracker_ActorsCollector::getInstance();
		$actorsCollector->addActorId("customer_customer:".$customer->getId());
		
		$vars = array("order" => $order);
		tracker_Logger::log("order_orderprocess_end", $vars);
		
		if ($response->isAccepted())
		{
			tracker_Logger::log("order_order_payed", $vars);
		}
		else if ($response->isFailed())
		{
			tracker_Logger::log("order_order_paymentfailed", $vars);
		}
		else
		{
			tracker_Logger::log("order_order_paymentdelayed", $vars);
		}
	}
}