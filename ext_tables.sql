#
# Table structure for table 'tx_fourallportal_domain_model_server'
#
CREATE TABLE tx_fourallportal_domain_model_server (

	uid int(11) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,

	domain varchar(255) DEFAULT '' NOT NULL,
	customer_name varchar(255) DEFAULT '' NOT NULL,
	username varchar(255) DEFAULT '' NOT NULL,
	password varchar(255) DEFAULT '' NOT NULL,
	active smallint(5) unsigned DEFAULT '0' NOT NULL,
	modules int(11) unsigned DEFAULT '0' NOT NULL,

	tstamp int(11) unsigned DEFAULT '0' NOT NULL,
	crdate int(11) unsigned DEFAULT '0' NOT NULL,
	cruser_id int(11) unsigned DEFAULT '0' NOT NULL,
	deleted smallint(5) unsigned DEFAULT '0' NOT NULL,
	hidden smallint(5) unsigned DEFAULT '0' NOT NULL,

	PRIMARY KEY (uid),
	KEY parent (pid),

);

#
# Table structure for table 'tx_fourallportal_domain_model_module'
#
CREATE TABLE tx_fourallportal_domain_model_module (

	uid int(11) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,

	server int(11) unsigned DEFAULT '0' NOT NULL,

	connector_name varchar(255) DEFAULT '' NOT NULL,
	mapping_class varchar(255) DEFAULT '' NOT NULL,
	config_hash varchar(255) DEFAULT '' NOT NULL,
	last_event_id int(11) DEFAULT '0' NOT NULL,
	shell_path varchar(255) DEFAULT '' NOT NULL,
	storage_pid int(11) DEFAULT '0' NOT NULL,
	fal_storage int(11) DEFAULT '0' NOT NULL,

	tstamp int(11) unsigned DEFAULT '0' NOT NULL,
	crdate int(11) unsigned DEFAULT '0' NOT NULL,
	cruser_id int(11) unsigned DEFAULT '0' NOT NULL,

	PRIMARY KEY (uid),
	KEY parent (pid),

);

#
# Table structure for table 'tx_fourallportal_domain_model_event'
#
CREATE TABLE tx_fourallportal_domain_model_event (

	uid int(11) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,

	event_id int(11) DEFAULT '0' NOT NULL,
	event_type varchar(255) DEFAULT '' NOT NULL,
	status varchar(255) DEFAULT '' NOT NULL,
	skip_until int(11) DEFAULT '0' NOT NULL,
	object_id varchar(255) DEFAULT '' NOT NULL,
	module int(11) unsigned DEFAULT '0',

	tstamp int(11) unsigned DEFAULT '0' NOT NULL,
	crdate int(11) unsigned DEFAULT '0' NOT NULL,
	cruser_id int(11) unsigned DEFAULT '0' NOT NULL,
	deleted smallint(5) unsigned DEFAULT '0' NOT NULL,

	PRIMARY KEY (uid),
	KEY parent (pid),

);

#
# Table structure for table 'tx_fourallportal_domain_model_module'
#
CREATE TABLE tx_fourallportal_domain_model_module (

	server int(11) unsigned DEFAULT '0' NOT NULL,

);


#
# Table structure for table 'sys_file'
#
CREATE TABLE sys_file (
    remote_id varchar(255) DEFAULT '' NOT NULL,
    KEY remote_id (remote_id)
);
