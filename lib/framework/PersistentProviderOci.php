<?php
class tracker_PersistentProviderOci extends f_persistentdocument_PersistentProviderOci
{
	function track($event, $actorIds, $vars)
	{
		throw new Exception(__METHOD__." is not implemented");
	}
}