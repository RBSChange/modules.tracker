CREATE TABLE IF NOT EXISTS `tracker_logs` (
`id` bigint(11) unsigned NOT NULL auto_increment,
`date` int(11) NOT NULL,
`session_id` varchar(50) character set latin1 collate latin1_general_ci NOT NULL,
`event` varchar(50) character set latin1 collate latin1_general_ci NOT NULL,
`actor_ids` TEXT character set latin1 collate latin1_general_ci NULL,
`vars` TEXT NOT NULL,
PRIMARY KEY  (`id`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_bin;