<?php
/**
 * JauntyM Messenger
 * @copyright (c) 2026 JauntyM
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace jauntym\messenger\migrations;

/**
 * Adds conv_key, a canonical key for each conversation ("<low user id>_<high user id>"
 * for direct conversations), and fills it in for existing rows. Any duplicate direct
 * conversations already on the board are merged here, so that m7 can put a unique
 * index on the column.
 */
class m6_conv_key extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return $this->db_tools->sql_column_exists($this->table_prefix . 'jauntym_conversations', 'conv_key');
	}

	public static function depends_on()
	{
		return ['\jauntym\messenger\migrations\m5_dm_permission'];
	}

	public function update_schema()
	{
		return [
			'add_columns' => [
				$this->table_prefix . 'jauntym_conversations' => [
					'conv_key' => ['VCHAR:32', ''],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_columns' => [
				$this->table_prefix . 'jauntym_conversations' => ['conv_key'],
			],
		];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'set_conv_keys']]],
			['custom', [[$this, 'merge_duplicate_conversations']]],
		];
	}

	/**
	 * Keys existing conversations in batches, so large boards don't time out. Returns
	 * the last conv_id handled to be called again, or null when finished.
	 */
	public function set_conv_keys($start = 0)
	{
		$conv_table = $this->table_prefix . 'jauntym_conversations';
		$cu_table   = $this->table_prefix . 'jauntym_conv_users';

		$sql = 'SELECT conv_id, conv_type FROM ' . $conv_table . '
			WHERE conv_id > ' . (int) $start . '
			ORDER BY conv_id ASC';
		$result = $this->db->sql_query_limit($sql, 500);

		$types = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$types[(int) $row['conv_id']] = (int) $row['conv_type'];
		}
		$this->db->sql_freeresult($result);

		if (empty($types))
		{
			return null;
		}

		$members = [];
		$sql = 'SELECT conv_id, user_id FROM ' . $cu_table . '
			WHERE ' . $this->db->sql_in_set('conv_id', array_keys($types));
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$members[(int) $row['conv_id']][] = (int) $row['user_id'];
		}
		$this->db->sql_freeresult($result);

		foreach ($types as $conv_id => $type)
		{
			$ids = isset($members[$conv_id]) ? $members[$conv_id] : [];
			if ($type === 0 && count($ids) === 2)
			{
				sort($ids);
				$key = $ids[0] . '_' . $ids[1];
			}
			else
			{
				// Anything that isn't a two-member direct conversation gets a key of
				// its own that can never collide with a direct pair.
				$key = 'x' . $conv_id;
			}

			$sql = 'UPDATE ' . $conv_table . "
				SET conv_key = '" . $this->db->sql_escape($key) . "'
				WHERE conv_id = " . (int) $conv_id;
			$this->db->sql_query($sql);
		}

		return max(array_keys($types));
	}

	/**
	 * Earlier versions could create two direct conversations for the same pair when
	 * two requests raced. Fold each extra one into the oldest.
	 */
	public function merge_duplicate_conversations()
	{
		$conv_table = $this->table_prefix . 'jauntym_conversations';

		$sql = 'SELECT conv_key FROM ' . $conv_table . '
			WHERE conv_type = 0
			GROUP BY conv_key
			HAVING COUNT(conv_id) > 1';
		$result = $this->db->sql_query($sql);
		$keys = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$keys[] = $row['conv_key'];
		}
		$this->db->sql_freeresult($result);

		foreach ($keys as $key)
		{
			$sql = 'SELECT conv_id FROM ' . $conv_table . "
				WHERE conv_key = '" . $this->db->sql_escape($key) . "'
				ORDER BY conv_id ASC";
			$result = $this->db->sql_query($sql);
			$ids = [];
			while ($row = $this->db->sql_fetchrow($result))
			{
				$ids[] = (int) $row['conv_id'];
			}
			$this->db->sql_freeresult($result);

			$keep = array_shift($ids);
			foreach ($ids as $dup)
			{
				$this->merge_conversation($keep, $dup);
			}
		}
	}

	protected function merge_conversation($keep, $dup)
	{
		$conv_table = $this->table_prefix . 'jauntym_conversations';
		$cu_table   = $this->table_prefix . 'jauntym_conv_users';
		$msg_table  = $this->table_prefix . 'jauntym_messages';

		$sql = 'UPDATE ' . $msg_table . '
			SET conv_id = ' . (int) $keep . '
			WHERE conv_id = ' . (int) $dup;
		$this->db->sql_query($sql);

		// Combine each member's unread count, read position and visibility.
		$sql = 'SELECT conv_id, user_id, cu_unread, cu_hidden, cu_last_read_id, cu_last_read_time
			FROM ' . $cu_table . '
			WHERE ' . $this->db->sql_in_set('conv_id', [(int) $keep, (int) $dup]);
		$result = $this->db->sql_query($sql);
		$state = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$state[(int) $row['user_id']][(int) $row['conv_id']] = $row;
		}
		$this->db->sql_freeresult($result);

		foreach ($state as $user_id => $rows)
		{
			if (!isset($rows[$dup]))
			{
				continue;
			}
			if (!isset($rows[$keep]))
			{
				$sql = 'UPDATE ' . $cu_table . '
					SET conv_id = ' . (int) $keep . '
					WHERE conv_id = ' . (int) $dup . ' AND user_id = ' . (int) $user_id;
				$this->db->sql_query($sql);
				continue;
			}

			$a = $rows[$keep];
			$b = $rows[$dup];
			$sql = 'UPDATE ' . $cu_table . '
				SET cu_unread = ' . ((int) $a['cu_unread'] + (int) $b['cu_unread']) . ',
					cu_hidden = ' . (((int) $a['cu_hidden'] && (int) $b['cu_hidden']) ? 1 : 0) . ',
					cu_last_read_id = ' . max((int) $a['cu_last_read_id'], (int) $b['cu_last_read_id']) . ',
					cu_last_read_time = ' . max((int) $a['cu_last_read_time'], (int) $b['cu_last_read_time']) . '
				WHERE conv_id = ' . (int) $keep . ' AND user_id = ' . (int) $user_id;
			$this->db->sql_query($sql);
		}

		// The kept conversation's "last message" is whichever of the two is newer.
		$sql = 'SELECT conv_id, conv_last_msg_id, conv_last_time
			FROM ' . $conv_table . '
			WHERE ' . $this->db->sql_in_set('conv_id', [(int) $keep, (int) $dup]);
		$result = $this->db->sql_query($sql);
		$last_msg = $last_time = 0;
		while ($row = $this->db->sql_fetchrow($result))
		{
			$last_msg  = max($last_msg, (int) $row['conv_last_msg_id']);
			$last_time = max($last_time, (int) $row['conv_last_time']);
		}
		$this->db->sql_freeresult($result);

		$sql = 'UPDATE ' . $conv_table . '
			SET conv_last_msg_id = ' . (int) $last_msg . ', conv_last_time = ' . (int) $last_time . '
			WHERE conv_id = ' . (int) $keep;
		$this->db->sql_query($sql);

		$sql = 'DELETE FROM ' . $cu_table . ' WHERE conv_id = ' . (int) $dup;
		$this->db->sql_query($sql);

		$sql = 'DELETE FROM ' . $conv_table . ' WHERE conv_id = ' . (int) $dup;
		$this->db->sql_query($sql);
	}
}
