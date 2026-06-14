<?php
/**
 * JauntyM Messenger
 * @copyright (c) 2026 JauntyM
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace jauntym\messenger\event;

use phpbb\controller\helper;
use phpbb\template\template;
use phpbb\user;
use phpbb\db\driver\driver_interface;
use phpbb\config\config;
use phpbb\auth\auth;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	/** @var helper */
	protected $helper;
	/** @var template */
	protected $template;
	/** @var user */
	protected $user;
	/** @var driver_interface */
	protected $db;
	/** @var config */
	protected $config;
	/** @var auth */
	protected $auth;
	/** @var string */
	protected $cu_table;

	public function __construct(helper $helper, template $template, user $user, driver_interface $db, config $config, auth $auth, $table_prefix)
	{
		$this->helper   = $helper;
		$this->template = $template;
		$this->user     = $user;
		$this->db       = $db;
		$this->config   = $config;
		$this->auth     = $auth;
		$this->cu_table = $table_prefix . 'jauntym_conv_users';
	}

	public static function getSubscribedEvents()
	{
		return [
			'core.user_setup'   => 'load_language',
			'core.page_header'  => 'add_navigation',
			'core.permissions'  => 'add_permissions',
		];
	}

	public function load_language($event)
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = [
			'ext_name' => 'jauntym/messenger',
			'lang_set' => 'common',
		];
		$event['lang_set_ext'] = $lang_set_ext;
	}

	public function add_permissions($event)
	{
		$permissions = $event['permissions'];
		$permissions['u_jauntym_messenger'] = ['lang' => 'ACL_U_JAUNTYM_MESSENGER', 'cat' => 'misc'];
		$permissions['u_jauntym_dm']        = ['lang' => 'ACL_U_JAUNTYM_DM', 'cat' => 'misc'];
		$event['permissions'] = $permissions;
	}

	public function add_navigation()
	{
		$registered = ((int) $this->user->data['user_id'] !== ANONYMOUS && empty($this->user->data['is_bot']))
			&& $this->auth->acl_get('u_jauntym_messenger');
		$unread = 0;

		if ($registered)
		{
			$sql = 'SELECT SUM(cu_unread) AS total
				FROM ' . $this->cu_table . '
				WHERE user_id = ' . (int) $this->user->data['user_id'] . '
					AND cu_hidden = 0';
			$result = $this->db->sql_query($sql);
			$unread = (int) $this->db->sql_fetchfield('total');
			$this->db->sql_freeresult($result);
		}

		$this->template->assign_vars([
			'S_JAUNTYM_MESSENGER'      => $registered,
			'S_JAUNTYM_CAN_DM'         => $registered && $this->auth->acl_get('u_jauntym_dm'),
			'U_JAUNTYM_MESSENGER'      => $this->helper->route('jauntym_messenger_index'),
			'U_JAUNTYM_M_AJAX'         => $this->helper->route('jauntym_messenger_ajax'),
			'JAUNTYM_M_SID'            => $registered ? $this->user->session_id : '',
			'JAUNTYM_M_TOKEN'          => $registered ? generate_link_hash('jauntym_messenger') : '',
			'JAUNTYM_M_ME'             => (int) $this->user->data['user_id'],
			'JAUNTYM_M_POLL'           => max(5, (int) $this->config['jauntym_msgr_poll']) * 1000,
			'JAUNTYM_MESSENGER_UNREAD' => $unread,
		]);
	}
}
