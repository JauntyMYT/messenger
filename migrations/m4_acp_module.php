<?php
/**
 * JauntyM Messenger
 * @copyright (c) 2026 JauntyM
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace jauntym\messenger\migrations;

class m4_acp_module extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\jauntym\messenger\migrations\m3_settings'];
	}

	public function update_data()
	{
		return [
			['module.add', ['acp', 'ACP_CAT_DOT_MODS', 'ACP_JAUNTYM_MESSENGER_TITLE']],
			['module.add', ['acp', 'ACP_JAUNTYM_MESSENGER_TITLE', [
				'module_basename' => '\jauntym\messenger\acp\main_module',
				'modes'           => ['settings'],
			]]],
		];
	}
}
