<?php
/**
 * JauntyM Messenger
 * @copyright (c) 2026 JauntyM
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace jauntym\messenger\migrations;

class m2_phase2_schema extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return $this->db_tools->sql_column_exists($this->table_prefix . 'jauntym_messages', 'bbcode_uid');
	}

	public static function depends_on()
	{
		return ['\jauntym\messenger\migrations\m1_initial_schema'];
	}

	public function update_schema()
	{
		return [
			'add_columns' => [
				$this->table_prefix . 'jauntym_messages' => [
					'bbcode_uid'      => ['VCHAR:8', ''],
					'bbcode_bitfield' => ['VCHAR:255', ''],
					'bbcode_options'  => ['UINT:11', 7],
					'msg_deleted'     => ['TIMESTAMP', 0],
				],
				$this->table_prefix . 'jauntym_conv_users' => [
					'cu_typing_time'  => ['TIMESTAMP', 0],
				],
			],
			'add_tables' => [
				$this->table_prefix . 'jauntym_blocks' => [
					'COLUMNS' => [
						'blocker_id' => ['UINT', 0],
						'blocked_id' => ['UINT', 0],
						'block_time' => ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY' => ['blocker_id', 'blocked_id'],
					'KEYS' => [
						'b_blocked' => ['INDEX', 'blocked_id'],
					],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_columns' => [
				$this->table_prefix . 'jauntym_messages'   => ['bbcode_uid', 'bbcode_bitfield', 'bbcode_options', 'msg_deleted'],
				$this->table_prefix . 'jauntym_conv_users'  => ['cu_typing_time'],
			],
			'drop_tables' => [
				$this->table_prefix . 'jauntym_blocks',
			],
		];
	}
}
