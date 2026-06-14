<?php
/**
 * JauntyM Messenger
 * @copyright (c) 2026 JauntyM
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace jauntym\messenger\migrations;

class m1_initial_schema extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return $this->db_tools->sql_table_exists($this->table_prefix . 'jauntym_conversations');
	}

	public static function depends_on()
	{
		// No core-version dependency: these tables are self-contained, so the
		// migration is fulfillable on any phpBB 3.2.x / 3.3.x board.
		return [];
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'jauntym_conversations' => [
					'COLUMNS' => [
						'conv_id'          => ['UINT', null, 'auto_increment'],
						'conv_type'        => ['UINT', 0],      // 0 = direct (1:1), reserved for future group chats
						'conv_title'       => ['VCHAR', ''],
						'conv_created'     => ['TIMESTAMP', 0],
						'conv_last_msg_id' => ['UINT', 0],
						'conv_last_time'   => ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY' => 'conv_id',
				],

				$this->table_prefix . 'jauntym_conv_users' => [
					'COLUMNS' => [
						'conv_id'           => ['UINT', 0],
						'user_id'           => ['UINT', 0],
						'cu_joined'         => ['TIMESTAMP', 0],
						'cu_last_read_id'   => ['UINT', 0],
						'cu_last_read_time' => ['TIMESTAMP', 0],
						'cu_unread'         => ['UINT', 0],
						'cu_hidden'         => ['BOOL', 0],
					],
					'PRIMARY_KEY' => ['conv_id', 'user_id'],
					'KEYS' => [
						'cu_user' => ['INDEX', 'user_id'],
					],
				],

				$this->table_prefix . 'jauntym_messages' => [
					'COLUMNS' => [
						'msg_id'    => ['UINT', null, 'auto_increment'],
						'conv_id'   => ['UINT', 0],
						'author_id' => ['UINT', 0],
						'msg_text'  => ['MTEXT_UNI', ''],
						'msg_time'  => ['TIMESTAMP', 0],
						'msg_edited'=> ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY' => 'msg_id',
					'KEYS' => [
						'm_conv' => ['INDEX', 'conv_id'],
						'm_time' => ['INDEX', 'msg_time'],
					],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'jauntym_conversations',
				$this->table_prefix . 'jauntym_conv_users',
				$this->table_prefix . 'jauntym_messages',
			],
		];
	}
}
