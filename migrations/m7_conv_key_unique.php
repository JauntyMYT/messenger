<?php
/**
 * JauntyM Messenger
 * @copyright (c) 2026 JauntyM
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace jauntym\messenger\migrations;

/**
 * Enforces one direct conversation per pair of members. Runs after m6 has keyed
 * every existing conversation and merged any duplicates.
 */
class m7_conv_key_unique extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return $this->db_tools->sql_unique_index_exists($this->table_prefix . 'jauntym_conversations', 'conv_key');
	}

	public static function depends_on()
	{
		return ['\jauntym\messenger\migrations\m6_conv_key'];
	}

	public function update_schema()
	{
		return [
			'add_unique_index' => [
				$this->table_prefix . 'jauntym_conversations' => [
					'conv_key' => ['conv_key'],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_keys' => [
				$this->table_prefix . 'jauntym_conversations' => ['conv_key'],
			],
		];
	}
}
