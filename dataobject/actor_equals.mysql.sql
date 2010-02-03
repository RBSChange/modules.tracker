CREATE TABLE IF NOT EXISTS `tracker_actor_equals` (
`actor1` varchar(50) character set latin1 collate latin1_general_ci NOT NULL,
`actor2` varchar(50) character set latin1 collate latin1_general_ci NOT NULL,
KEY  (`actor1`),
UNIQUE (`actor1` , `actor2`) 
) TYPE=MyISAM CHARACTER SET utf8 COLLATE utf8_bin;