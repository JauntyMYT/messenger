<?php
/**
 * JauntyM Messenger
 * @copyright (c) 2026 JauntyM
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = [];
}

$lang = array_merge($lang, [
	'ACP_JAUNTYM_MESSENGER_EXPLAIN' => 'Configure how the conversation-style messenger behaves on your board.',

	'ACP_JAUNTYM_GENERAL'           => 'General',
	'ACP_JAUNTYM_FEATURES'          => 'Features',
	'ACP_JAUNTYM_SECONDS'           => 'seconds',

	'ACP_JAUNTYM_POLL'              => 'Refresh interval',
	'ACP_JAUNTYM_POLL_EXPLAIN'      => 'How often the open chat checks for new messages and read receipts. Lower feels more "live" but adds server load. Minimum 5 seconds.',
	'ACP_JAUNTYM_MAXLEN'           => 'Maximum message length',
	'ACP_JAUNTYM_MAXLEN_EXPLAIN'   => 'The longest single message a member can send, in characters.',
	'ACP_JAUNTYM_MINSEARCH'        => 'Minimum search characters',
	'ACP_JAUNTYM_MINSEARCH_EXPLAIN'=> 'How many characters must be typed before the member search runs.',

	'ACP_JAUNTYM_BBCODE'           => 'Allow BBCode',
	'ACP_JAUNTYM_BBCODE_EXPLAIN'   => 'Allow BBCode formatting (bold, links, quotes, etc.) inside messages.',
	'ACP_JAUNTYM_SMILIES'          => 'Allow smilies',
	'ACP_JAUNTYM_SMILIES_EXPLAIN'  => 'Render board smilies inside messages.',
	'ACP_JAUNTYM_TYPING'           => 'Typing indicators',
	'ACP_JAUNTYM_TYPING_EXPLAIN'   => 'Show a "typing…" indicator to the other person while a member is writing (updates on the refresh interval).',

	'ACP_JAUNTYM_SETTINGS_SAVED'   => 'JauntyM Messenger settings saved successfully.',
]);
