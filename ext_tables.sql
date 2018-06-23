#
# Table structure for table 'be_users'
#
CREATE TABLE be_users (
	oauth_identifier varchar(255) DEFAULT '' NOT NULL
);


#
# Table structure for table 'be_groups'
#
CREATE TABLE be_groups (
	gitlabGroup varchar(255) DEFAULT '' NOT NULL
);