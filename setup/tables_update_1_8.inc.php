<?php
/**
 * EGroupware - API Setup
 *
 * Update scripts 1.8 --> 14.1
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage setup
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Update from the stable 1.8 branch to the new devel branch 1.9.xxx
 */
function phpgwapi_upgrade1_8_001()
{
	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.9.001';
}
function phpgwapi_upgrade1_8_002()
{
	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.9.001';
}
function phpgwapi_upgrade1_8_003()
{
	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.9.001';
}
/**
 * Combiupdate 1.8.004/5/6: includes Trunk updates 1.9.001-1.9.010+1.9.013
 *
 * @return string
 */
function phpgwapi_upgrade1_8_004()
{
	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.8.005';
}
function phpgwapi_upgrade1_8_005()
{
	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.8.006';
}
function phpgwapi_upgrade1_8_006()
{
	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.8.007';
}
function phpgwapi_upgrade1_8_007()
{
	// run the two not included updates
	phpgwapi_upgrade1_9_010();
	phpgwapi_upgrade1_9_011();

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.9.013';
}

/**
 * Add index to improve import of contacts using a custom field as primary key
 *
 * @return string
 */
function phpgwapi_upgrade1_9_001()
{
	$GLOBALS['egw_setup']->oProc->CreateIndex('egw_addressbook_extra',
		array('contact_name','contact_value(32)'));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.9.002';
}

function phpgwapi_upgrade1_9_002()
{
	/* done by RefreshTable() anyway
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_links','deleted',array(
		'type' => 'timestamp'
	));*/
	$GLOBALS['egw_setup']->oProc->RefreshTable('egw_links',array(
		'fd' => array(
			'link_id' => array('type' => 'auto','nullable' => False),
			'link_app1' => array('type' => 'varchar','precision' => '25','nullable' => False),
			'link_id1' => array('type' => 'varchar','precision' => '50','nullable' => False),
			'link_app2' => array('type' => 'varchar','precision' => '25','nullable' => False),
			'link_id2' => array('type' => 'varchar','precision' => '50','nullable' => False),
			'link_remark' => array('type' => 'varchar','precision' => '100'),
			'link_lastmod' => array('type' => 'int','precision' => '8','nullable' => False),
			'link_owner' => array('type' => 'int','precision' => '4','nullable' => False),
			'deleted' => array('type' => 'timestamp')
		),
		'pk' => array('link_id'),
		'fk' => array(),
		'ix' => array('deleted',array('link_app1','link_id1','link_lastmod'),array('link_app2','link_id2','link_lastmod')),
		'uc' => array()
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.9.003';
}


function phpgwapi_upgrade1_9_003()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_addressbook','adr_one_countrycode',array(
		'type' => 'varchar',
		'precision' => '2'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_addressbook','adr_two_countrycode',array(
		'type' => 'varchar',
		'precision' => '2'
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.9.004';
}


/**
 * Update script to populate country codes
 *
 * Sets country code for any recognized country in any installed language, then clears the country name
 * to avoid conflicts / confusion.
 */
function phpgwapi_upgrade1_9_004()
{
	// Get all installed translations for names
	$country = new country();
	$country_query = 'SELECT DISTINCT message_id, content'.
		' FROM ' . translation::LANG_TABLE .
		" WHERE message_id IN (" . implode(',', array_map(
			array($GLOBALS['egw_setup']->db, 'quote'),
				array_values($country->countries()))) . ")".
		' ORDER BY message_id';
	$result = $GLOBALS['egw_setup']->oProc->query($country_query, __LINE__, __FILE__);

	$country_list = array();
	$current_name = null;
	$id = null;
	foreach($result as $row) {
		if($row['message_id'] != $current_name) {
			$current_name = $row['message_id'];
			$id = array_search(strtoupper($current_name), $country->countries());
			if(!$id) continue;
		}
		$country_list[$id][] = $row['content'];
	}

	// Build conversion
	$case = 'CASE UPPER(adr_%1$s_countryname)';
	foreach($country_list as $key => $names) {
		foreach($names as $name) {
			$case .= "\n" . "WHEN UPPER(\"$name\") THEN '$key'";
		}
	}
	$case .= ' END';

	$sql = 'UPDATE egw_addressbook SET ';
	$sql .= "adr_one_countrycode = (" . sprintf($case, 'one') . '),';
	$sql .= "adr_two_countrycode = (" . sprintf($case, 'two') . ')';

	// Change names (if there are changes, gives sql error otherwise!)
	if ($country_list) $GLOBALS['egw_setup']->oProc->query($sql,__LINE__,__FILE__);

	// Clear text names
	$GLOBALS['egw_setup']->oProc->query('UPDATE egw_addressbook SET adr_one_countryname = NULL WHERE adr_one_countrycode IS NOT NULL',__LINE__,__FILE__);
	$GLOBALS['egw_setup']->oProc->query('UPDATE egw_addressbook SET adr_two_countryname = NULL WHERE adr_two_countrycode IS NOT NULL',__LINE__,__FILE__);
	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.9.005';
}


/**
 * Add index to li (login time) column to speed up maintenance (periodic delete of old rows)
 *
 * Delete some obsolete / since a long time not used tables:
 * - egw_vfs (replaced by egw_sqlfs in 1.6)
 * - egw_(app_)sessions (not used since 1.4)
 */
function phpgwapi_upgrade1_9_005()
{
	$GLOBALS['egw_setup']->oProc->CreateIndex('egw_access_log','li');

	$GLOBALS['egw_setup']->oProc->DropTable('egw_app_sessions');
	$GLOBALS['egw_setup']->oProc->DropTable('egw_sessions');
	$GLOBALS['egw_setup']->oProc->DropTable('egw_vfs');

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.9.006';
}

/**
 * Add column to store CalDAV name given by client
 */
function phpgwapi_upgrade1_9_006()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_addressbook','carddav_name',array(
		'type' => 'varchar',
		'precision' => '64',
		'comment' => 'name part of CardDAV URL, if specified by client'
	));
	$GLOBALS['egw_setup']->db->query($sql='UPDATE egw_addressbook SET carddav_name='.
		$GLOBALS['egw_setup']->db->concat(
			$GLOBALS['egw_setup']->db->to_varchar('contact_id'),"'.vcf'"),__LINE__,__FILE__);

	$GLOBALS['egw_setup']->oProc->CreateIndex('egw_addressbook','carddav_name');

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.9.007';
}

/**
 * Add columns for session list (dla, action), make sessionid primary key and TS 64bit
 */
function phpgwapi_upgrade1_9_007()
{
	$GLOBALS['egw_setup']->oProc->RefreshTable('egw_access_log',array(
		'fd' => array(
			'sessionid' => array('type' => 'auto','nullable' => False,'comment' => 'primary key'),
			'loginid' => array('type' => 'varchar','precision' => '64','nullable' => False,'comment' => 'username used to login'),
			'ip' => array('type' => 'varchar','precision' => '40','nullable' => False,'comment' => 'ip of user'),
			'li' => array('type' => 'int','precision' => '8','nullable' => False,'comment' => 'TS if login'),
			'lo' => array('type' => 'int','precision' => '8','comment' => 'TD of logout'),
			'account_id' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0','comment' => 'numerical account id'),
			'session_dla' => array('type' => 'int','precision' => '8','comment' => 'TS of last user action'),
			'session_action' => array('type' => 'varchar','precision' => '64','comment' => 'menuaction or path of last user action'),
			'session_php' => array('type' => 'char','precision' => '64','nullable' => False,'comment' => 'php session-id or error-message'),
			'notification_heartbeat' => array('type' => 'int','precision' => '8','comment' => 'TS of last notification request')
		),
		'pk' => array('sessionid'),
		'fk' => array(),
		'ix' => array('li','lo','session_dla','notification_heartbeat'),
		'uc' => array()
	),array(
		'session_php' => 'sessionid',
		'sessionid' => 'NULL',	// to NOT copy old sessionid, but create a new sequence
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.9.008';
}

/**
 * Alter column cat_owner to varchar(255) to support multiple owners/groups per cat
 */
function phpgwapi_upgrade1_9_008()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_categories','cat_owner',array(
		'type' => 'varchar',
		'precision' => '255',
		'nullable' => False,
		'default' => '0'
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.9.009';
}

/**
 * Alter column account_pwd to varchar(128) to allow to store sha256_crypt hashes
 *
 * Enable password migration to new default "securest available", if current hash is the default (sql: md5, ldap: des)
 * or the 1.9.009 migration to ssha is running
 */
function phpgwapi_upgrade1_9_009()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_accounts','account_pwd',array(
		'type' => 'varchar',
		'precision' => '128',
		'nullable' => False
	));

	// query password hashing from database
	$config = array(
		'auth_type' => 'sql',
		'account_repository' => null,	// default is auth_type
		'sql_encryption_type' => 'md5',
		'ldap_encryption_type' => 'des',
		'pwd_migration_allowed' => null,	// default off
		'pwd_migration_types' => null,
	);
	foreach($GLOBALS['egw_setup']->db->select('egw_config','config_name,config_value',array(
		'config_app' => 'phpgwapi',
		'config_name' => array_keys($config),
	),__LINE__,__FILE__) as $row)
	{
		$config[$row['config_name']] = $row['config_value'];
	}
	if (!isset($config['account_repository'])) $config['account_repository'] = $config['auth_type'];

	// changing pw hashing only, if we auth agains our own account repository and no migration already active
	if ($config['auth_type'] == $config['account_repository'] &&
		(!$config['pwd_migration_allowed'] || $config['pwd_migration_types'] == 'md5,crypt'))	// 1.9.009 migration to ssha
	{
		require_once EGW_SERVER_ROOT.'/setup/inc/hook_config.inc.php';	// for sql_passwdhashes to get securest available password hash
		$securest = null;
		sql_passwdhashes(array(), true, $securest);
		// OpenLDAP has no own support for extended crypt like sha512_crypt, but relys the OS crypt implementation,
		// do NOT automatically migrate to anything above SSHA for OS other then Linux (Darwin can not auth anymore!)
		if ($config['auth_type'] == 'sql' && in_array($config['sql_encryption_type'], array('md5','ssha')) ||
			$config['auth_type'] == 'ldap'&& in_array($config['ldap_encryption_type'], array('des','ssha')) &&
				(PHP_OS == 'Linux' || $securest == 'ssha'))
		{
			$config['pwd_migration_types'] = 'md5,crypt';	// des is called crypt in hash
			if ($config['pwd_migration_allowed'] && $securest != 'ssha') $config['pwd_migration_types'] .= ',ssha';
			$config['sql_encryption_type'] = $config['ldap_encryption_type'] = $securest;
			$config['pwd_migration_allowed'] = 'True';
			echo "<p>Enabling password migration to $securest</p>\n";
		}
		foreach($config as $name => $value)
		{
			$GLOBALS['egw_setup']->db->insert('egw_config',array(
				'config_value' => $value,
			),array(
				'config_app' => 'phpgwapi',
				'config_name' => $name,
			),__LINE__,__FILE__);
		}
	}

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.9.010';
}

/**
 * Create template directory and set default document_dir preference of addressbook, calendar, infolog, tracker, timesheet and projectmanager
 */
function phpgwapi_upgrade1_9_010()
{
	$admins = $GLOBALS['egw_setup']->add_account('Admins','Admin','Group',False,False);

	egw_vfs::$is_root = true;
	$prefs = new preferences();
	$prefs->read_repository(false);
	foreach(array('','addressbook', 'calendar', 'infolog', 'tracker', 'timesheet', 'projectmanager') as $app)
	{
		if ($app && !file_exists(EGW_SERVER_ROOT.'/'.$app)) continue;

		// create directory and set permissions: Admins writable and other readable
		$dir = '/templates'.($app ? '/'.$app : '');
		egw_vfs::mkdir($dir, 075, STREAM_MKDIR_RECURSIVE);
		egw_vfs::chgrp($dir, abs($admins));
		egw_vfs::chmod($dir, 075);
		if (!$app) continue;

		// set default preference for app (preserving a maybe already set document-directory)
		if ($prefs->default[$app]['document_dir']) $dir .= ' '.$prefs->default[$app]['document_dir'];
		$prefs->add($app, 'document_dir', $dir, 'default');
	}
	$prefs->save_repository(false, 'default');
	egw_vfs::$is_root = false;

	// check if categories are already updated to global == cat_owner=0
	// has to run as API update, before apps (tracker) creates new global cats with cat_owner=0 stopping the update
	categories::invalidate_cache();
	categories::init_cache();	// updates the cats

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.9.011';
}

/**
 * Language: French --> Français
 */
function phpgwapi_upgrade1_9_011()
{
	$GLOBALS['egw_setup']->db->insert($GLOBALS['egw_setup']->languages_table,array('lang_name' => 'Français'),array('lang_id' => 'fr'),__LINE__,__FILE__);

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.9.012';
}

/**
 * Add index for contact_modified to improve performance of ctag generation on big installtions
 */
function phpgwapi_upgrade1_9_012()
{
	$GLOBALS['egw_setup']->oProc->CreateIndex('egw_addressbook','contact_modified');

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.9.013';
}

/**
 * Adding neccessary information to return list to (Apple) CardDAV clients as "Group"
 */
function phpgwapi_upgrade1_9_013()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_addressbook_lists','list_uid',array(
		'type' => 'varchar',
		'precision' => '255'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_addressbook_lists','list_carddav_name',array(
		'type' => 'varchar',
		'precision' => '64'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_addressbook_lists','list_etag',array(
		'type' => 'int',
		'precision' => '4',
		'nullable' => False,
		'default' => '0'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_addressbook_lists','list_modified',array(
		'type' => 'timestamp',
		'nullable' => False,
		'default' => 'current_timestamp'
	));
	$GLOBALS['egw_setup']->oProc->CreateIndex('egw_addressbook_lists','list_modified');
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_addressbook_lists','list_modifier',array(
		'type' => 'int',
		'precision' => '4'
	));
	$install_id = $GLOBALS['egw_setup']->db->select('egw_config','config_value',array(
		'config_app' => 'phpgwapi',
		'config_name' => 'install_id',
	),__LINE__,__FILE__)->fetchColumn();
	// setting values for existing lists
	$GLOBALS['egw_setup']->db->query('UPDATE egw_addressbook_lists SET '.
		'list_uid='.$GLOBALS['egw_setup']->db->concat("'addressbook-lists-'",'list_id',"'-$install_id'").
		',list_carddav_name='.$GLOBALS['egw_setup']->db->concat("'addressbook-lists-'",'list_id',"'-$install_id.vcf'"),
		__LINE__,__FILE__);
	$GLOBALS['egw_setup']->oProc->CreateIndex('egw_addressbook_lists','list_uid',true);
	$GLOBALS['egw_setup']->oProc->CreateIndex('egw_addressbook_lists','list_carddav_name',true);

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.9.014';
}


function phpgwapi_upgrade1_9_014()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_access_log','user_agent',array(
		'type' => 'varchar',
		'precision' => '255',
		'comment' => 'User-agent of browser/device'
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.9.015';
}


function phpgwapi_upgrade1_9_015()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_history_log','sessionid',array(
		'type' => 'int',
		'precision' => '4',
		'comment' => 'primary key to egw_access_log'
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.9.016';
}


/**
 * Some index to speed up access/update of huge access-logs
 *
 * ALTER TABLE `egw_access_log` ADD INDEX `egw_access_log_session_php` ( `session_php` )
 * ALTER TABLE `egw_access_log` ADD INDEX `egw_access_log_account_id_ip_li` ( `account_id` , `ip` , `li` )
 * ALTER TABLE `egw_access_log` ADD INDEX `egw_access_log_account_id_loginid_li` ( `account_id` , `loginid` , `li` )
 */
function phpgwapi_upgrade1_9_016()
{
	$GLOBALS['egw_setup']->oProc->CreateIndex('egw_access_log','session_php');
	$GLOBALS['egw_setup']->oProc->CreateIndex('egw_access_log',array('account_id','ip','li'));
	$GLOBALS['egw_setup']->oProc->CreateIndex('egw_access_log',array('account_id','loginid','li'));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.9.017';
}


/**
 * Some index to speed up access/update of huge history-logs
 *
 * ALTER TABLE `egw_history_log` ADD INDEX `egw_history_log_appname_record_id_id` (`history_appname`, `history_record_id`, `history_id`)
 * DROP INDEX `egw_history_log_appname_record_id_status_timestamp` ON `egw_history_log`
 */
function phpgwapi_upgrade1_9_017()
{
	$GLOBALS['egw_setup']->oProc->CreateIndex('egw_history_log', array('history_appname','history_record_id','history_id'));
	$GLOBALS['egw_setup']->oProc->DropIndex('egw_history_log', array('history_appname','history_record_id','history_status','history_timestamp'));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.9.018';
}


function phpgwapi_upgrade1_9_018()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_addressbook','carddav_name',array(
		'type' => 'varchar',
		'precision' => '200',
		'comment' => 'name part of CardDAV URL, if specified by client'
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.9.019';
}


function phpgwapi_upgrade1_9_019()
{
	$GLOBALS['egw_setup']->oProc->DropTable('egw_interserv');

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.9.020';
}

/**
 * Create own table for custom-fields and migrate values over from egw_config
 *
 * @return string
 */
function phpgwapi_upgrade1_9_020()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('egw_customfields',array(
		'fd' => array(
			'cf_id' => array('type' => 'auto','nullable' => False),
			'cf_app' => array('type' => 'varchar','precision' => '50','nullable' => False,'comment' => 'app-name cf belongs too'),
			'cf_name' => array('type' => 'varchar','precision' => '128','nullable' => False,'comment' => 'internal name'),
			'cf_label' => array('type' => 'varchar','precision' => '128','comment' => 'label to display'),
			'cf_type' => array('type' => 'varchar','precision' => '64','nullable' => False,'default' => 'text','comment' => 'type of field'),
			'cf_type2' => array('type' => 'varchar','precision' => '2048','comment' => 'comma-separated subtypes of app, cf is valid for'),
			'cf_help' => array('type' => 'varchar','precision' => '256','comment' => 'helptext'),
			'cf_values' => array('type' => 'varchar','precision' => '8096','comment' => 'json object with value label pairs'),
			'cf_len' => array('type' => 'int','precision' => '2','comment' => 'length or columns of field'),
			'cf_rows' => array('type' => 'int','precision' => '2','comment' => 'rows of field'),
			'cf_order' => array('type' => 'int','precision' => '2','comment' => 'order to display fields'),
			'cf_needed' => array('type' => 'bool','default' => '0','comment' => 'field is required'),
			'cf_private' => array('type' => 'varchar','meta' => 'account-commasep','precision' => '2048','comment' => 'comma-separated account_id'),
			'cf_modifier' => array('type' => 'int','meta' => 'account','precision' => '4','comment' => 'last modifier'),
			'cf_modified' => array('type' => 'timestamp','default' => 'current_timestamp','comment' => 'last modification time')
		),
		'pk' => array('cf_id'),
		'fk' => array(),
		'ix' => array(array('cf_app', 'cf_order')),
		'uc' => array(array('cf_app', 'cf_name'))
	));

	foreach($GLOBALS['egw_setup']->db->select('egw_config', '*', "config_name='customfields'", __LINE__, __FILE__) as $row)
	{
		$cfs = $row['config_value'][1] == ':' ? unserialize($row['config_value']) : json_decode($row['config_value']);
		foreach($cfs as $name => $cf)
		{
			$data = array(
				'cf_name' => $name,
				'cf_app'  => $row['config_app'],
			);
			// fix ancient definition with just name => label
			if (!is_array($cf))
			{
				$cf = ['type' => 'text', 'label' => $cf];
			}
			// convert date format in len to key "format" in values
			if (in_array($cf['type'], array('date', 'date-time')) && !empty($cf['len']) && !is_numeric($cf['len']))
			{
				if (!is_array($cf['values'])) $cf['values'] = array();
				$cf['values']['format'] = $cf['len'];
			}
			foreach($cf as $attr => $val)
			{
				switch($attr)
				{
					case 'private':
					case 'type2':
						$val = $val ? (is_array($val) ? implode(',', $val) : $val) : null;
						break;
					case 'values':
						$val = $val && is_array($val) ? json_encode($val) : null;
						break;
					case 'len':
					case 'rows':
						$val = (string)$val !== '' ? $val : null;
						break;
				}
				$data['cf_'.$attr] = $val;
			}
			$GLOBALS['egw_setup']->db->insert('egw_customfields', $data, false, __LINE__, __FILE__);
		}
	}
	$GLOBALS['egw_setup']->db->delete('egw_config', "config_name='customfields'", __LINE__, __FILE__);

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.9.021';
}


function phpgwapi_upgrade1_9_021()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_customfields','cf_tab',array(
		'type' => 'varchar',
		'precision' => '64',
		'comment' => 'tab customfield should be shown'
	));

	// force new Pixelegg template on update
	if (file_exists(EGW_SERVER_ROOT.'/pixelegg'))
	{
		preferences::change_preference('common', 'template_set', 'pixelegg', null, 'forced');
		preferences::change_preference('common', 'theme', 'pixelegg', null, 'forced');
		preferences::change_preference('common', 'rte_skin', 'moono', null, 'forced');
	}
	preferences::change_preference('common', 'maxmatchs', '20', null, 'forced');

	// switch on history / delete preventions for addressbook, calendar and infolog, if not yet a config exists
	// for new installs this is done in setup_process::save_minimal_config
	foreach(array(
		'phgwapi' => array(
			'history' => 'history',	// addressbook
			'calendar_delete_history' => 'history',
		),
		'infolog' => array(
			'history' => 'history_admin_delete',
		)
	) as $app => $data)
	{
		foreach($data as $name => $value)
		{
			if (!$GLOBALS['egw_setup']->db->select('egw_config','COUNT(*)', $row=array(
				'config_app' => $app,
				'config_name' => $name,
			), __LINE__, __FILE__)->fetchColumn())
			{
				$row['config_value'] = $value;
				$GLOBALS['egw_setup']->db->insert('egw_config', $row, null, __LINE__, __FILE__);
			}
		}
	}

	// drop not used egw_log and egw_log_msg tables
	$GLOBALS['egw_setup']->oProc->DropTable('egw_log');
	$GLOBALS['egw_setup']->oProc->DropTable('egw_log_msg');

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '14.1';
}