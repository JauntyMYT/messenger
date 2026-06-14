<?php
/**
 * JauntyM Messenger
 * @copyright (c) 2026 JauntyM
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace jauntym\messenger\migrations;

class m5_dm_permission extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		$sql = 'SELECT auth_option_id FROM ' . ACL_OPTIONS_TABLE . " WHERE auth_option = 'u_jauntym_dm'";
		$result = $this->db->sql_query($sql);
		$id = $this->db->sql_fetchfield('auth_option_id');
		$this->db->sql_freeresult($result);
		return (bool) $id;
	}

	public static function depends_on()
	{
		return ['\jauntym\messenger\migrations\m4_acp_module'];
	}

	public function update_data()
	{
		return [
			['permission.add', ['u_jauntym_dm', true]],
			['permission.permission_set', ['ROLE_USER_FULL', 'u_jauntym_dm', 'role']],
			['permission.permission_set', ['ROLE_USER_STANDARD', 'u_jauntym_dm', 'role']],
		];
	}
}
