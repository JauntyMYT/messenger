<?php
/**
 * JauntyM Messenger
 * @copyright (c) 2026 JauntyM
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace jauntym\messenger\migrations;

class m3_settings extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return isset($this->config['jauntym_msgr_poll']);
	}

	public static function depends_on()
	{
		return ['\jauntym\messenger\migrations\m2_phase2_schema'];
	}

	public function update_data()
	{
		return [
			['config.add', ['jauntym_msgr_poll', 15]],
			['config.add', ['jauntym_msgr_maxlen', 5000]],
			['config.add', ['jauntym_msgr_minsearch', 2]],
			['config.add', ['jauntym_msgr_bbcode', 1]],
			['config.add', ['jauntym_msgr_smilies', 1]],
			['config.add', ['jauntym_msgr_typing', 1]],

			['permission.add', ['u_jauntym_messenger', true]],
			['permission.permission_set', ['ROLE_USER_FULL', 'u_jauntym_messenger', 'role']],
			['permission.permission_set', ['ROLE_USER_STANDARD', 'u_jauntym_messenger', 'role']],
		];
	}
}
