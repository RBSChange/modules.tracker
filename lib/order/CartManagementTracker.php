<?php
/**
 * <before pointcut="order_CartService::addProduct"
 *         class="tracker_CartManagementTracker" method="addProduct" />
 */
class tracker_CartManagementTracker
{
	function addProduct($product, $quantity, $properties)
	{
		$cart = $this->getDocumentInstanceFromSession();
		$vars = array("product" => $product->getId(), "quantity" => $quantity);
		if ($cart->isEmpty())
		{
			tracker_Logger::log("order_cart_new");
		}
		tracker_Logger::log("order_cart_addproduct", $vars);
	}
}