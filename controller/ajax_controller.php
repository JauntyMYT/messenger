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
	const VERSION = '1.3.6';

	/**
	 * Actions that write to the database. Each one must arrive as a POST carrying a
	 * valid link hash, so it cannot be triggered by a cross-site request.
	 */
	protected static $write_actions = ['send', 'start', 'typing', 'delete_message', 'block', 'unblock', 'hide'];

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

		$out = [];
		foreach ($rows as $conv_id => $row)
		{
			$partner = $this->get_partner($conv_id);
			if (!$partner)
			{
				continue;
			}
			$last = $this->last_message_snippet((int) $row['conv_last_msg_id']);

			$out[] = [
				'conv_id'      => $conv_id,
				'user_id'      => (int) $partner['user_id'],
				'name'         => $partner['name'],
				'avatar'       => $partner['avatar'],
				'online'       => $partner['online'],
				'snippet'      => $last['text'],
				'snippet_mine' => $last['mine'],
				'time'         => $row['conv_last_time'] ? $this->user->format_date((int) $row['conv_last_time']) : '',
				'unread'       => (int) $row['cu_unread'],
			];
		}

		return new JsonResponse(['ok' => true, 'conversations' => $out]);
	}

	protected function get_messages()
	{
		$conv_id = $this->request->variable('conv_id', 0);
		$before  = $this->request->variable('before', 0);

		if (!$this->is_member($conv_id))
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

		if ($before === 0)
		{
			$this->mark_read($conv_id);
		}

		$partner = $this->get_partner($conv_id);
		$state   = $this->partner_state($conv_id);

		return new JsonResponse([
			'ok'                => true,
			'conv_id'           => (int) $conv_id,
			'messages'          => $messages,
			'has_more'          => $has_more,
			'partner'           => $partner,
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
		if ($this->is_member($conv_id))
		{
			$sql = 'UPDATE ' . $this->cu_table . '
				SET cu_typing_time = ' . time() . '
				WHERE conv_id = ' . (int) $conv_id . ' AND user_id = ' . (int) $this->uid;
			$this->db->sql_query($sql);
		}

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
			SET msg_deleted = ' . time() . "
			WHERE msg_id = " . (int) $msg_id;
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
		$conv_id = (int) $conv_id;
		if ($conv_id <= 0)
		{
			return false;
		}

		$sql = 'SELECT 1 AS x FROM ' . $this->cu_table . '
			WHERE conv_id = ' . (int) $conv_id . ' AND user_id = ' . (int) $this->uid;
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return (bool) $row;
	}

	protected function mark_read($conv_id)
	{
		$conv_id = (int) $conv_id;

		$sql = 'SELECT MAX(msg_id) AS m FROM ' . $this->msg_table . ' WHERE conv_id = ' . (int) $conv_id;
		$result = $this->db->sql_query($sql);
		$max = (int) $this->db->sql_fetchfield('m');
		$this->db->sql_freeresult($result);

		$sql = 'UPDATE ' . $this->cu_table . '
			SET cu_last_read_id = ' . (int) $max . ', cu_last_read_time = ' . time() . ', cu_unread = 0
			WHERE conv_id = ' . (int) $conv_id . ' AND user_id = ' . (int) $this->uid;
		$this->db->sql_query($sql);
	}

	protected function partner_state($conv_id)
	{
		$sql = 'SELECT cu_last_read_id, cu_last_read_time, cu_typing_time
			FROM ' . $this->cu_table . '
			WHERE conv_id = ' . (int) $conv_id . ' AND user_id <> ' . (int) $this->uid;
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		$typing = false;
		if (!empty($this->config['jauntym_msgr_typing']) && $row)
		{
			$typing = ((int) $row['cu_typing_time'] > (time() - self::TYPING_WINDOW));
		}

		return [
			'read_id'   => $row ? (int) $row['cu_last_read_id'] : 0,
			'read_time' => ($row && $row['cu_last_read_time']) ? $this->user->format_date((int) $row['cu_last_read_time']) : '',
			'typing'    => $typing,
		];
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

	protected function get_partner($conv_id)
	{
		$pid = $this->partner_id($conv_id);
		if (!$pid)
		{
			return null;
		}

		$sql = 'SELECT user_id, username, user_lastvisit,
				user_avatar, user_avatar_type, user_avatar_width, user_avatar_height
			FROM ' . USERS_TABLE . '
			WHERE user_id = ' . (int) $pid;
		$result = $this->db->sql_query($sql);
		$u = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$u)
		{
			return null;
		}

		$block = $this->block_state($pid);

		return [
			'user_id'   => $pid,
			'name'      => $u['username'],
			'avatar'    => function_exists('phpbb_get_user_avatar') ? phpbb_get_user_avatar($u) : '',
			'online'    => $this->is_online($pid),
			'last_seen' => $u['user_lastvisit']
				? $this->language->lang('JAUNTYM_M_LAST_SEEN', $this->user->format_date((int) $u['user_lastvisit']))
				: '',
			'i_blocked' => $block['i_blocked'],
		];
	}

	protected function is_online($user_id)
	{
		$window = time() - ((int) $this->config['load_online_time'] * 60);

		$sql = 'SELECT MAX(session_time) AS t FROM ' . SESSIONS_TABLE . '
			WHERE session_user_id = ' . (int) $user_id;
		$result = $this->db->sql_query($sql);
		$t = (int) $this->db->sql_fetchfield('t');
		$this->db->sql_freeresult($result);

		return $t > $window;
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

	protected function last_message_snippet($msg_id)
	{
		if ($msg_id <= 0)
		{
			return ['text' => '', 'mine' => false];
		}

		$sql = 'SELECT author_id, msg_text, bbcode_uid, bbcode_bitfield, bbcode_options, msg_deleted
			FROM ' . $this->msg_table . ' WHERE msg_id = ' . (int) $msg_id;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$row)
		{
			return ['text' => '', 'mine' => false];
		}

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

		$sql = 'SELECT a.conv_id
			FROM ' . $this->cu_table . ' a, ' . $this->cu_table . ' b, ' . $this->conv_table . ' c
			WHERE a.user_id = ' . (int) $this->uid . '
				AND b.user_id = ' . (int) $other . '
				AND a.conv_id = b.conv_id
				AND c.conv_id = a.conv_id
				AND c.conv_type = 0';
		$result = $this->db->sql_query_limit($sql, 1);
		$found = (int) $this->db->sql_fetchfield('conv_id');
		$this->db->sql_freeresult($result);

		if ($found)
		{
			return $found;
		}

		$now = time();
		$sql = 'INSERT INTO ' . $this->conv_table . ' ' . $this->db->sql_build_array('INSERT', [
			'conv_type'      => 0,
			'conv_title'     => '',
			'conv_created'   => $now,
			'conv_last_time' => $now,
		]);
		$this->db->sql_query($sql);
		$conv_id = (int) $this->db->sql_nextid();

		$this->db->sql_multi_insert($this->cu_table, [
			['conv_id' => $conv_id, 'user_id' => $this->uid, 'cu_joined' => $now, 'cu_hidden' => 0],
			['conv_id' => $conv_id, 'user_id' => $other,     'cu_joined' => $now, 'cu_hidden' => 0],
		]);

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
