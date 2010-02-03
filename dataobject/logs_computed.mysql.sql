CREATE TABLE IF NOT EXISTS `tracker_logs_computed` (
`log_id` bigint(11) unsigned,
`actor_type` varchar(20) character set latin1 collate latin1_general_ci NOT NULL, 
`actor_id` varchar(50) character set latin1 collate latin1_general_ci NOT NULL,
KEY  (`log_id`),
KEY  (`actor_type`, `actor_id`)
) TYPE=MyISAM CHARACTER SET utf8 COLLATE utf8_bin;