<?php
/**
 * <before pointcut="website_RewriteUrlAction::_execute"
 *         class="tracker_ViewUrlTracker" method="execute" />
 */
class tracker_ViewUrlTracker
{
	function execute($context, $request)
	{
		$url = "http" . ((isset($_SERVER["HTTPS"])) ? "s" : "") . "://".$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"];
		// TODO: add "engine", "version", "referer" vars ... ?
		tracker_Logger::log("website_viewurl", array("url" => $url, "method" => $_SERVER["REQUEST_METHOD"]));
	}
}