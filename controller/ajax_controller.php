<?php
/**
 * JauntyM Messenger
 * @copyright (c) 2026 JauntyM
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace jauntym\messenger\controller;

use phpbb\config\config;
use phpbb\db\driver\driver_interface;
use phpbb\request\request_interface;
use phpbb\user;
use phpbb\language\language;
use phpbb\auth\auth;
use Symfony\Component\HttpFoundation\JsonResponse;

class ajax_controller
{
	/** @var config */
	protected $config;
	/** @var driver_interface */
	protected $db;
	/** @var request_interface */
	protected $request;
	/** @var user */
	protected $user;
	/** @var language */
	protected $language;
	/** @var auth */
	protected $auth;

	protected $conv_table;
	protected $cu_table;
	protected $msg_table;
	protected $block_table;
	protected $uid;
	protected $root_path;
	protected $php_ext;

	const PAGE_SIZE   = 30;
	const TYPING_WINDOW = 8;
	const VERSION = '1.3.7';

	/**
	 * Actions that write to the database. Each one must arrive as a POST carrying a
	 * valid link hash, so it cannot be triggered by a cross-site request.
	 */
	protected static $write_actions = ['send', 'start', 'typing', 'read', 'delete_message', 'block', 'unblock', 'hide'];

	public function __construct(config $config, driver_interface $db, request_interface $request, user $user, language $language, auth $auth, $table_prefix, $root_path, $php_ext)
	{
		$this->config   = $config;
		$this->db       = $db;
		$this->request  = $request;
		$this->user     = $user;
		$this->language = $language;
		$this->auth     = $auth;
		$this->root_path = $root_path;
		$this->php_ext   = $php_ext;

		$this->conv_table  = $table_prefix . 'jauntym_conversations';
		$this->cu_table    = $table_prefix . 'jauntym_conv_users';
		$this->msg_table   = $table_prefix . 'jauntym_messages';
		$this->block_table = $table_prefix . 'jauntym_blocks';
		$this->uid         = (int) $this->user->data['user_id'];
	}

	/**
	 * Routed controllers don't auto-load every phpBB function file, so make sure
	 * the ones we rely on are available before handling a request.
	 */
	protected function ensure_functions()
	{
		if (!function_exists('utf8_clean_string'))
		{
			include($this->root_path . 'includes/utf/utf_tools.' . $this->php_ext);
		}
		if (!function_exists('generate_text_for_storage'))
		{
			include($this->root_path . 'includes/functions_content.' . $this->php_ext);
		}
		if (!function_exists('phpbb_get_user_avatar'))
		{
			include($this->root_path . 'includes/functions_display.' . $this->php_ext);
		}
	}

	public function handle()
	{
		$action = $this->request->variable('action', '');

		if ($action === 'version')
		{
			return new JsonResponse(['ok' => true, 'version' => self::VERSION]);
		}

		if ($this->uid === ANONYMOUS)
		{
			return $this->error('JAUNTYM_M_ERR_LOGIN', 403);
		}
		if (!$this->auth->acl_get('u_jauntym_messenger'))
		{
			return $this->error('JAUNTYM_M_ERR_NOPERM', 403);
		}

		// CSRF guard for every state-changing action: POST only, with a valid link
		// hash read from the POST data (never from the query string).
		if (in_array($action, self::$write_actions, true) && !$this->check_token())
		{
			return $this->error('JAUNTYM_M_ERR_TOKEN', 403);
		}

		$this->ensure_functions();

		try
		{
			switch ($action)
			{
				case 'conversations':  return $this->list_conversations();
				case 'messages':       return $this->get_messages();
				case 'send':           return $this->send_message();
				case 'start':          return $this->start_conversation();
				case 'search_users':   return $this->search_users();
				case 'typing':         return $this->set_typing();
				case 'read':           return $this->read_conversation();
				case 'delete_message': return $this->delete_message();
				case 'block':          return $this->block(true);
				case 'unblock':        return $this->block(false);
				case 'hide':           return $this->hide_conversation();
				default:               return $this->error('JAUNTYM_M_ERR_GENERIC', 400);
			}
		}
		catch (\Throwable $e)
		{
			// Never expose the exception text (it can carry SQL or filesystem paths).
			return $this->error('JAUNTYM_M_ERR_GENERIC', 500);
		}
	}

	/**
	 * True only for a POST request carrying a valid 'jauntym_messenger' link hash.
	 */
	protected function check_token()
	{
		if (strtoupper($this->request->server('REQUEST_METHOD')) !== 'POST')
		{
			return false;
		}

		$hash = $this->request->variable('hash', '', false, request_interface::POST);

		return check_link_hash($hash, 'jauntym_messenger');
	}

	/* ----------------------------------------------------------------- *
	 *  Endpoints
	 * ----------------------------------------------------------------- */

	/**
	 * The conversation list is polled, so it is built from a fixed number of
	 * queries however many conversations the member has.
	 */
	protected function list_conversations()
	{
		$sql = 'SELECT c.conv_id, c.conv_last_time, c.conv_last_msg_id, cu.cu_unread
			FROM ' . $this->conv_table . ' c, ' . $this->cu_table . ' cu
			WHERE cu.user_id = ' . (int) $this->uid . '
				AND cu.cu_hidden = 0
				AND c.conv_id = cu.conv_id
			ORDER BY c.conv_last_time DESC';
		$result = $this->db->sql_query_limit($sql, 60);

		$rows = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[(int) $row['conv_id']] = $row;
		}
		$this->db->sql_freeresult($result);

		if (empty($rows))
		{
			return new JsonResponse(['ok' => true, 'conversations' => []]);
		}

		$sql = 'SELECT conv_id, user_id
			FROM ' . $this->cu_table . '
			WHERE ' . $this->db->sql_in_set('conv_id', array_keys($rows)) . '
				AND user_id <> ' . (int) $this->uid;
		$result = $this->db->sql_query($sql);

		$partner_of = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$partner_of[(int) $row['conv_id']] = (int) $row['user_id'];
		}
		$this->db->sql_freeresult($result);

		$partner_ids = array_values(array_unique($partner_of));
		$users       = $this->load_users($partner_ids);
		$sessions    = $this->load_sessions($partner_ids);
		$blocked     = array_flip($this->all_block_ids());

		$last_ids = [];
		foreach ($rows as $row)
		{
			if ((int) $row['conv_last_msg_id'] > 0)
			{
				$last_ids[] = (int) $row['conv_last_msg_id'];
			}
		}
		$snippets = $this->load_snippets($last_ids);

		$out = [];
		foreach ($rows as $conv_id => $row)
		{
			$pid = isset($partner_of[$conv_id]) ? $partner_of[$conv_id] : 0;
			if (!$pid || !isset($users[$pid]))
			{
				continue;
			}

			$u        = $users[$pid];
			$presence = $this->presence($u, isset($sessions[$pid]) ? $sessions[$pid] : null, isset($blocked[$pid]));
			$last_id  = (int) $row['conv_last_msg_id'];
			$last     = isset($snippets[$last_id]) ? $snippets[$last_id] : ['text' => '', 'mine' => false];

			$out[] = [
				'conv_id'      => $conv_id,
				'user_id'      => $pid,
				'name'         => $u['username'],
				'avatar'       => function_exists('phpbb_get_user_avatar') ? phpbb_get_user_avatar($u) : '',
				'online'       => $presence['online'],
				'snippet'      => $last['text'],
				'snippet_mine' => $last['mine'],
				'time'         => $row['conv_last_time'] ? $this->user->format_date((int) $row['conv_last_time']) : '',
				'unread'       => (int) $row['cu_unread'],
			];
		}

		return new JsonResponse(['ok' => true, 'conversations' => $out]);
	}

	/**
	 * Read-only: returns a page of messages. Marking the conversation read is a
	 * separate POST ('read'), which the client sends once it has shown the messages.
	 */
	protected function get_messages()
	{
		$conv_id = $this->request->variable('conv_id', 0);
		$before  = $this->request->variable('before', 0);

		$member = $this->member_row($conv_id);
		if (!$member)
		{
			return $this->error('JAUNTYM_M_ERR_NOT_MEMBER', 403);
		}

		$where = 'conv_id = ' . (int) $conv_id;
		if ($before > 0)
		{
			$where .= ' AND msg_id < ' . (int) $before;
		}

		$sql = 'SELECT msg_id, author_id, msg_text, bbcode_uid, bbcode_bitfield, bbcode_options, msg_time, msg_deleted
			FROM ' . $this->msg_table . '
			WHERE ' . $where . '
			ORDER BY msg_id DESC';
		$result = $this->db->sql_query_limit($sql, self::PAGE_SIZE + 1);

		$rows = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = $row;
		}
		$this->db->sql_freeresult($result);

		$has_more = (count($rows) > self::PAGE_SIZE);
		if ($has_more)
		{
			array_pop($rows);
		}
		$rows = array_reverse($rows);

		$messages = [];
		foreach ($rows as $row)
		{
			$render = $this->render_text($row);
			$messages[] = [
				'msg_id'    => (int) $row['msg_id'],
				'author_id' => (int) $row['author_id'],
				'mine'      => ((int) $row['author_id'] === $this->uid),
				'html'      => $render['html'],
				'deleted'   => $render['deleted'],
				'time'      => $this->user->format_date((int) $row['msg_time']),
			];
		}

		$pid     = $this->partner_id($conv_id);
		$block   = $this->block_state($pid);
		$blocked = $block['i_blocked'] || $block['blocked_me'];
		$state   = $this->partner_state($conv_id, $blocked);

		return new JsonResponse([
			'ok'                => true,
			'conv_id'           => (int) $conv_id,
			'messages'          => $messages,
			'has_more'          => $has_more,
			'unread'            => (int) $member['cu_unread'],
			'partner'           => $this->build_partner($pid, $block),
			'partner_read_id'   => $state['read_id'],
			'partner_read_time' => $state['read_time'],
			'partner_typing'    => $state['typing'],
		]);
	}

	protected function send_message()
	{
		// CSRF token is verified for every write action in handle().
		if (!$this->can_dm())
		{
			return $this->error('JAUNTYM_M_ERR_DM', 403);
		}

		$text    = trim($this->request->variable('text', '', true));
		$conv_id = $this->request->variable('conv_id', 0);
		$to_user = $this->request->variable('to_user', 0);

		if ($text === '')
		{
			return $this->error('JAUNTYM_M_ERR_EMPTY', 400);
		}
		if (utf8_strlen($text) > (int) $this->config['jauntym_msgr_maxlen'])
		{
			return $this->error('JAUNTYM_M_ERR_TOOLONG', 400);
		}

		if ($conv_id === 0 && $to_user > 0)
		{
			if ($to_user === $this->uid)
			{
				return $this->error('JAUNTYM_M_ERR_SELF', 400);
			}

			$block = $this->block_state($to_user);
			if ($block['i_blocked'])
			{
				return $this->error('JAUNTYM_M_ERR_YOU_BLOCKED', 403);
			}
			if ($block['blocked_me'])
			{
				return $this->error('JAUNTYM_M_ERR_BLOCKED', 403);
			}

			$conv_id = $this->find_or_create_direct($to_user);
			if (!$conv_id)
			{
				return $this->error('JAUNTYM_M_ERR_RECIPIENT', 400);
			}
		}

		if (!$this->is_member($conv_id))
		{
			return $this->error('JAUNTYM_M_ERR_NOT_MEMBER', 403);
		}

		// Block check against the existing partner.
		$partner_id = $this->partner_id($conv_id);
		if ($partner_id)
		{
			$block = $this->block_state($partner_id);
			if ($block['i_blocked'])
			{
				return $this->error('JAUNTYM_M_ERR_YOU_BLOCKED', 403);
			}
			if ($block['blocked_me'])
			{
				return $this->error('JAUNTYM_M_ERR_BLOCKED', 403);
			}
		}

		$uid = $bitfield = '';
		$flags = 0;
		$stored = $this->store_text($text, $uid, $bitfield, $flags);

		$now = time();
		$sql = 'INSERT INTO ' . $this->msg_table . ' ' . $this->db->sql_build_array('INSERT', [
			'conv_id'         => (int) $conv_id,
			'author_id'       => $this->uid,
			'msg_text'        => $stored,
			'bbcode_uid'      => $uid,
			'bbcode_bitfield' => $bitfield,
			'bbcode_options'  => (int) $flags,
			'msg_time'        => $now,
		]);
		$this->db->sql_query($sql);
		$msg_id = (int) $this->db->sql_nextid();

		$sql = 'UPDATE ' . $this->conv_table . '
			SET conv_last_msg_id = ' . (int) $msg_id . ', conv_last_time = ' . (int) $now . '
			WHERE conv_id = ' . (int) $conv_id;
		$this->db->sql_query($sql);

		$sql = 'UPDATE ' . $this->cu_table . '
			SET cu_unread = cu_unread + 1, cu_hidden = 0
			WHERE conv_id = ' . (int) $conv_id . ' AND user_id <> ' . (int) $this->uid;
		$this->db->sql_query($sql);

		$sql = 'UPDATE ' . $this->cu_table . '
			SET cu_last_read_id = ' . (int) $msg_id . ', cu_last_read_time = ' . (int) $now . ', cu_unread = 0, cu_hidden = 0, cu_typing_time = 0
			WHERE conv_id = ' . (int) $conv_id . ' AND user_id = ' . (int) $this->uid;
		$this->db->sql_query($sql);

		return new JsonResponse([
			'ok'      => true,
			'conv_id' => (int) $conv_id,
			'message' => [
				'msg_id'    => $msg_id,
				'author_id' => $this->uid,
				'mine'      => true,
				'html'      => generate_text_for_display($stored, $uid, $bitfield, (int) $flags, true),
				'deleted'   => false,
				'time'      => $this->user->format_date($now),
			],
		]);
	}

	protected function start_conversation()
	{
		if (!$this->can_dm())
		{
			return $this->error('JAUNTYM_M_ERR_DM', 403);
		}

		$to_user = $this->request->variable('user_id', 0);
		if ($to_user === $this->uid)
		{
			return $this->error('JAUNTYM_M_ERR_SELF', 400);
		}

		$block = $this->block_state($to_user);
		if ($block['i_blocked'])
		{
			return $this->error('JAUNTYM_M_ERR_YOU_BLOCKED', 403);
		}
		if ($block['blocked_me'])
		{
			return $this->error('JAUNTYM_M_ERR_BLOCKED', 403);
		}

		$conv_id = $this->find_or_create_direct($to_user);
		if (!$conv_id)
		{
			return $this->error('JAUNTYM_M_ERR_RECIPIENT', 400);
		}

		$sql = 'UPDATE ' . $this->cu_table . '
			SET cu_hidden = 0
			WHERE conv_id = ' . (int) $conv_id . ' AND user_id = ' . (int) $this->uid;
		$this->db->sql_query($sql);

		return new JsonResponse(['ok' => true, 'conv_id' => (int) $conv_id]);
	}

	protected function search_users()
	{
		if (!$this->can_dm())
		{
			return $this->error('JAUNTYM_M_ERR_DM', 403);
		}

		$raw = trim($this->request->variable('q', '', true));
		$q = function_exists('utf8_clean_string') ? utf8_clean_string($raw) : strtolower($raw);
		$len = function_exists('utf8_strlen') ? utf8_strlen($q) : strlen($q);
		if ($len < max(1, (int) $this->config['jauntym_msgr_minsearch']))
		{
			return new JsonResponse(['ok' => true, 'users' => []]);
		}

		$fold = function_exists('utf8_case_fold_nfc') ? utf8_case_fold_nfc($q) : strtolower($q);
		$like = $this->db->sql_like_expression($this->db->get_any_char() . $fold . $this->db->get_any_char());

		// Exclude anyone in a block relationship with me (either direction).
		$blocked_ids = $this->all_block_ids();
		$exclude = ' AND user_id <> ' . (int) $this->uid;
		if (!empty($blocked_ids))
		{
			$exclude .= ' AND ' . $this->db->sql_in_set('user_id', $blocked_ids, true);
		}

		$sql = 'SELECT user_id, username, user_avatar, user_avatar_type, user_avatar_width, user_avatar_height
			FROM ' . USERS_TABLE . '
			WHERE username_clean ' . $like . '
				AND ' . $this->db->sql_in_set('user_type', [USER_NORMAL, USER_FOUNDER]) . $exclude . '
			ORDER BY username_clean ASC';
		$result = $this->db->sql_query_limit($sql, 8);

		$users = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$users[] = [
				'user_id' => (int) $row['user_id'],
				'name'    => $row['username'],
				'avatar'  => function_exists('phpbb_get_user_avatar') ? phpbb_get_user_avatar($row) : '',
			];
		}
		$this->db->sql_freeresult($result);

		return new JsonResponse(['ok' => true, 'users' => $users]);
	}

	protected function set_typing()
	{
		if (empty($this->config['jauntym_msgr_typing']))
		{
			return new JsonResponse(['ok' => true]);
		}

		$conv_id = $this->request->variable('conv_id', 0);
		if (!$this->is_member($conv_id))
		{
			return new JsonResponse(['ok' => true]);
		}

		// Blocking works in both directions: neither side's typing is recorded.
		$block = $this->block_state($this->partner_id($conv_id));
		if ($block['i_blocked'] || $block['blocked_me'])
		{
			return new JsonResponse(['ok' => true]);
		}

		$sql = 'UPDATE ' . $this->cu_table . '
			SET cu_typing_time = ' . (int) time() . '
			WHERE conv_id = ' . (int) $conv_id . ' AND user_id = ' . (int) $this->uid;
		$this->db->sql_query($sql);

		return new JsonResponse(['ok' => true]);
	}

	protected function read_conversation()
	{
		$conv_id = $this->request->variable('conv_id', 0);
		if (!$this->is_member($conv_id))
		{
			return $this->error('JAUNTYM_M_ERR_NOT_MEMBER', 403);
		}

		$this->mark_read($conv_id);

		return new JsonResponse(['ok' => true]);
	}

	protected function delete_message()
	{
		$msg_id = $this->request->variable('msg_id', 0);
		if ($msg_id <= 0)
		{
			return $this->error('JAUNTYM_M_ERR_GENERIC', 400);
		}

		$sql = 'SELECT m.conv_id
			FROM ' . $this->msg_table . ' m, ' . $this->cu_table . ' cu
			WHERE m.msg_id = ' . (int) $msg_id . '
				AND m.author_id = ' . (int) $this->uid . '
				AND cu.conv_id = m.conv_id
				AND cu.user_id = ' . (int) $this->uid;
		$result = $this->db->sql_query_limit($sql, 1);
		$conv_id = (int) $this->db->sql_fetchfield('conv_id');
		$this->db->sql_freeresult($result);

		if (!$conv_id)
		{
			return $this->error('JAUNTYM_M_ERR_NOT_MEMBER', 403);
		}

		$sql = 'UPDATE ' . $this->msg_table . '
			SET msg_deleted = ' . (int) time() . '
			WHERE msg_id = ' . (int) $msg_id;
		$this->db->sql_query($sql);

		return new JsonResponse(['ok' => true]);
	}

	protected function block($on)
	{
		$target = $this->request->variable('user_id', 0);
		if ($target <= 0 || $target === $this->uid)
		{
			return $this->error('JAUNTYM_M_ERR_GENERIC', 400);
		}

		if ($on)
		{
			$sql = 'SELECT 1 AS x FROM ' . $this->block_table . '
				WHERE blocker_id = ' . (int) $this->uid . ' AND blocked_id = ' . (int) $target;
			$result = $this->db->sql_query_limit($sql, 1);
			$exists = (bool) $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);

			if (!$exists)
			{
				$sql = 'INSERT INTO ' . $this->block_table . ' ' . $this->db->sql_build_array('INSERT', [
					'blocker_id' => $this->uid,
					'blocked_id' => (int) $target,
					'block_time' => time(),
				]);
				$this->db->sql_query($sql);
			}
		}
		else
		{
			$sql = 'DELETE FROM ' . $this->block_table . '
				WHERE blocker_id = ' . (int) $this->uid . ' AND blocked_id = ' . (int) $target;
			$this->db->sql_query($sql);
		}

		return new JsonResponse(['ok' => true, 'blocked' => (bool) $on]);
	}

	protected function hide_conversation()
	{
		$conv_id = $this->request->variable('conv_id', 0);
		if ($this->is_member($conv_id))
		{
			$sql = 'UPDATE ' . $this->cu_table . '
				SET cu_hidden = 1
				WHERE conv_id = ' . (int) $conv_id . ' AND user_id = ' . (int) $this->uid;
			$this->db->sql_query($sql);
		}

		return new JsonResponse(['ok' => true]);
	}

	/* ----------------------------------------------------------------- *
	 *  Helpers
	 * ----------------------------------------------------------------- */

	protected function can_dm()
	{
		return (bool) $this->auth->acl_get('u_jauntym_dm');
	}

	protected function is_member($conv_id)
	{
		return (bool) $this->member_row($conv_id);
	}

	/**
	 * The current member's row in a conversation, or null if they are not in it.
	 */
	protected function member_row($conv_id)
	{
		$conv_id = (int) $conv_id;
		if ($conv_id <= 0)
		{
			return null;
		}

		$sql = 'SELECT cu_unread FROM ' . $this->cu_table . '
			WHERE conv_id = ' . (int) $conv_id . ' AND user_id = ' . (int) $this->uid;
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row ?: null;
	}

	protected function mark_read($conv_id)
	{
		$conv_id = (int) $conv_id;

		$sql = 'SELECT MAX(msg_id) AS m FROM ' . $this->msg_table . ' WHERE conv_id = ' . (int) $conv_id;
		$result = $this->db->sql_query($sql);
		$max = (int) $this->db->sql_fetchfield('m');
		$this->db->sql_freeresult($result);

		$sql = 'UPDATE ' . $this->cu_table . '
			SET cu_last_read_id = ' . (int) $max . ', cu_last_read_time = ' . (int) time() . ', cu_unread = 0
			WHERE conv_id = ' . (int) $conv_id . ' AND user_id = ' . (int) $this->uid;
		$this->db->sql_query($sql);
	}

	/**
	 * Read receipt and typing state of the other member. When either side has
	 * blocked the other, a neutral state is returned instead.
	 */
	protected function partner_state($conv_id, $blocked)
	{
		$state = ['read_id' => 0, 'read_time' => '', 'typing' => false];
		if ($blocked)
		{
			return $state;
		}

		$sql = 'SELECT cu_last_read_id, cu_last_read_time, cu_typing_time
			FROM ' . $this->cu_table . '
			WHERE conv_id = ' . (int) $conv_id . ' AND user_id <> ' . (int) $this->uid;
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$row)
		{
			return $state;
		}

		$state['read_id']   = (int) $row['cu_last_read_id'];
		$state['read_time'] = $row['cu_last_read_time'] ? $this->user->format_date((int) $row['cu_last_read_time']) : '';
		if (!empty($this->config['jauntym_msgr_typing']))
		{
			$state['typing'] = ((int) $row['cu_typing_time'] > (time() - self::TYPING_WINDOW));
		}

		return $state;
	}

	protected function partner_id($conv_id)
	{
		$sql = 'SELECT user_id FROM ' . $this->cu_table . '
			WHERE conv_id = ' . (int) $conv_id . ' AND user_id <> ' . (int) $this->uid;
		$result = $this->db->sql_query_limit($sql, 1);
		$pid = (int) $this->db->sql_fetchfield('user_id');
		$this->db->sql_freeresult($result);

		return $pid;
	}

	protected function build_partner($pid, array $block)
	{
		if (!$pid)
		{
			return null;
		}

		$users = $this->load_users([$pid]);
		if (!isset($users[$pid]))
		{
			return null;
		}

		$u        = $users[$pid];
		$sessions = $this->load_sessions([$pid]);
		$presence = $this->presence($u, isset($sessions[$pid]) ? $sessions[$pid] : null, $block['i_blocked'] || $block['blocked_me']);

		return [
			'user_id'   => (int) $pid,
			'name'      => $u['username'],
			'avatar'    => function_exists('phpbb_get_user_avatar') ? phpbb_get_user_avatar($u) : '',
			'online'    => $presence['online'],
			'last_seen' => $presence['last_seen'],
			'i_blocked' => $block['i_blocked'],
		];
	}

	/**
	 * User rows keyed by user_id, with the columns needed for display and presence.
	 */
	protected function load_users(array $user_ids)
	{
		if (empty($user_ids))
		{
			return [];
		}

		// user_last_active only exists from phpBB 3.3.12.
		$last_active = phpbb_version_compare($this->config['version'], '3.3.12', '>=') ? ', user_last_active' : '';

		$sql = 'SELECT user_id, username, user_lastvisit, user_allow_viewonline' . $last_active . ',
				user_avatar, user_avatar_type, user_avatar_width, user_avatar_height
			FROM ' . USERS_TABLE . '
			WHERE ' . $this->db->sql_in_set('user_id', array_map('intval', $user_ids));
		$result = $this->db->sql_query($sql);

		$users = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$users[(int) $row['user_id']] = $row;
		}
		$this->db->sql_freeresult($result);

		return $users;
	}

	/**
	 * Latest session time per user, and whether every session is visible. Mirrors
	 * memberlist.php: MIN(session_viewonline) means one hidden session hides them.
	 */
	protected function load_sessions(array $user_ids)
	{
		if (empty($user_ids))
		{
			return [];
		}

		$sql = 'SELECT session_user_id, MAX(session_time) AS session_time, MIN(session_viewonline) AS session_viewonline
			FROM ' . SESSIONS_TABLE . '
			WHERE ' . $this->db->sql_in_set('session_user_id', array_map('intval', $user_ids)) . '
			GROUP BY session_user_id';
		$result = $this->db->sql_query($sql);

		$sessions = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$sessions[(int) $row['session_user_id']] = $row;
		}
		$this->db->sql_freeresult($result);

		return $sessions;
	}

	/**
	 * Online flag and "last seen" text, applying the same rules as the profile page
	 * (phpbb_show_profile()): a member who hides their online status is shown as
	 * offline with no last-seen time, unless the viewer has u_viewonline. Nothing is
	 * shown between members where either has blocked the other.
	 */
	protected function presence(array $u, $session, $blocked)
	{
		$presence = ['online' => false, 'last_seen' => ''];
		if ($blocked)
		{
			return $presence;
		}

		$can_view_hidden = $this->auth->acl_get('u_viewonline');
		$session_time    = $session ? (int) $session['session_time'] : 0;

		if (!empty($this->config['load_onlinetrack']) && $session)
		{
			$window = (int) $this->config['load_online_time'] * 60;
			$presence['online'] = (time() - $window < $session_time)
				&& (!empty($session['session_viewonline']) || $can_view_hidden);
		}

		if (!empty($u['user_allow_viewonline']) || $can_view_hidden)
		{
			$last = !empty($u['user_last_active']) ? (int) $u['user_last_active'] : ($session_time ?: (int) $u['user_lastvisit']);
			if ($last)
			{
				$presence['last_seen'] = $this->language->lang('JAUNTYM_M_LAST_SEEN', $this->user->format_date($last));
			}
		}

		return $presence;
	}

	protected function block_state($other)
	{
		$other = (int) $other;
		$state = ['i_blocked' => false, 'blocked_me' => false];
		if ($other <= 0)
		{
			return $state;
		}

		$sql = 'SELECT blocker_id, blocked_id FROM ' . $this->block_table . '
			WHERE (blocker_id = ' . (int) $this->uid . ' AND blocked_id = ' . (int) $other . ')
				OR (blocker_id = ' . (int) $other . ' AND blocked_id = ' . (int) $this->uid . ')';
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			if ((int) $row['blocker_id'] === $this->uid)
			{
				$state['i_blocked'] = true;
			}
			else
			{
				$state['blocked_me'] = true;
			}
		}
		$this->db->sql_freeresult($result);

		return $state;
	}

	protected function all_block_ids()
	{
		$ids = [];
		$sql = 'SELECT blocker_id, blocked_id FROM ' . $this->block_table . '
			WHERE blocker_id = ' . (int) $this->uid . ' OR blocked_id = ' . (int) $this->uid;
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$other = ((int) $row['blocker_id'] === $this->uid) ? (int) $row['blocked_id'] : (int) $row['blocker_id'];
			$ids[$other] = $other;
		}
		$this->db->sql_freeresult($result);

		return array_values($ids);
	}

	/**
	 * Plain-text previews of the given messages, keyed by msg_id.
	 */
	protected function load_snippets(array $msg_ids)
	{
		if (empty($msg_ids))
		{
			return [];
		}

		$sql = 'SELECT msg_id, author_id, msg_text, bbcode_uid, bbcode_bitfield, bbcode_options, msg_deleted
			FROM ' . $this->msg_table . '
			WHERE ' . $this->db->sql_in_set('msg_id', array_map('intval', $msg_ids));
		$result = $this->db->sql_query($sql);

		$snippets = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$snippets[(int) $row['msg_id']] = $this->format_snippet($row);
		}
		$this->db->sql_freeresult($result);

		return $snippets;
	}

	protected function format_snippet(array $row)
	{
		$mine = ((int) $row['author_id'] === $this->uid);

		if ((int) $row['msg_deleted'] > 0)
		{
			return ['text' => $this->language->lang('JAUNTYM_M_DELETED'), 'mine' => $mine];
		}

		$plain = generate_text_for_display($row['msg_text'], $row['bbcode_uid'], $row['bbcode_bitfield'], (int) $row['bbcode_options'], true);
		$plain = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($plain), ENT_QUOTES, 'UTF-8')));
		if (utf8_strlen($plain) > 48)
		{
			$plain = utf8_substr($plain, 0, 47) . '…';
		}

		return ['text' => $plain, 'mine' => $mine];
	}

	/**
	 * Canonical key for the direct conversation between two members. The column
	 * carries a unique index, so a pair can only ever have one direct conversation.
	 */
	protected function direct_key($a, $b)
	{
		$a = (int) $a;
		$b = (int) $b;

		return min($a, $b) . '_' . max($a, $b);
	}

	protected function find_direct($key)
	{
		$sql = 'SELECT conv_id FROM ' . $this->conv_table . "
			WHERE conv_key = '" . $this->db->sql_escape($key) . "'";
		$result = $this->db->sql_query_limit($sql, 1);
		$conv_id = (int) $this->db->sql_fetchfield('conv_id');
		$this->db->sql_freeresult($result);

		return $conv_id;
	}

	protected function find_or_create_direct($other)
	{
		$other = (int) $other;
		if ($other <= 0 || $other === $this->uid)
		{
			return 0;
		}

		$sql = 'SELECT user_id FROM ' . USERS_TABLE . '
			WHERE user_id = ' . (int) $other . '
				AND ' . $this->db->sql_in_set('user_type', [USER_NORMAL, USER_FOUNDER]);
		$result = $this->db->sql_query($sql);
		$exists = (int) $this->db->sql_fetchfield('user_id');
		$this->db->sql_freeresult($result);

		if (!$exists)
		{
			return 0;
		}

		$key   = $this->direct_key($this->uid, $other);
		$found = $this->find_direct($key);
		if ($found)
		{
			return $found;
		}

		$now = time();
		$this->db->sql_transaction('begin');

		$this->db->sql_return_on_error(true);
		$inserted = $this->db->sql_query('INSERT INTO ' . $this->conv_table . ' ' . $this->db->sql_build_array('INSERT', [
			'conv_type'      => 0,
			'conv_title'     => '',
			'conv_key'       => $key,
			'conv_created'   => $now,
			'conv_last_time' => $now,
		]));
		$this->db->sql_return_on_error(false);

		if (!$inserted)
		{
			// A concurrent request created this pair's conversation first and the
			// unique index rejected ours (phpBB has already rolled back). Use theirs.
			return $this->find_direct($key);
		}

		$conv_id = (int) $this->db->sql_nextid();

		$this->db->sql_multi_insert($this->cu_table, [
			['conv_id' => $conv_id, 'user_id' => $this->uid, 'cu_joined' => $now, 'cu_hidden' => 0],
			['conv_id' => $conv_id, 'user_id' => $other,     'cu_joined' => $now, 'cu_hidden' => 0],
		]);

		$this->db->sql_transaction('commit');

		return $conv_id;
	}

	protected function store_text($text, &$uid, &$bitfield, &$flags)
	{
		$uid = $bitfield = '';
		$flags = 0;
		$allow_bbcode  = !empty($this->config['jauntym_msgr_bbcode']);
		$allow_smilies = !empty($this->config['jauntym_msgr_smilies']);

		generate_text_for_storage($text, $uid, $bitfield, $flags, $allow_bbcode, true, $allow_smilies);

		return $text;
	}

	protected function render_text($row)
	{
		if ((int) $row['msg_deleted'] > 0)
		{
			return [
				'html'    => '<em class="jauntym-deleted">' . $this->language->lang('JAUNTYM_M_DELETED') . '</em>',
				'deleted' => true,
			];
		}

		return [
			'html'    => generate_text_for_display($row['msg_text'], $row['bbcode_uid'], $row['bbcode_bitfield'], (int) $row['bbcode_options'], true),
			'deleted' => false,
		];
	}

	protected function error($lang_key, $status = 400)
	{
		return new JsonResponse(['ok' => false, 'error' => $this->language->lang($lang_key)], $status);
	}
}
