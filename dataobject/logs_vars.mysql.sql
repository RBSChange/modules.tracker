CREATE TABLE IF NOT EXISTS `tracker_logs_vars` (
`log_id` bigint(11) unsigned,
`name` varchar(50) character set latin1 collate latin1_general_ci NOT NULL,
`value` varchar(255),
KEY (`log_id`),
KEY (`name`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_bin;