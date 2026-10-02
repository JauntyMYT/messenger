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
	'JAUNTYM_MESSENGER'                 => 'Messages',
	'JAUNTYM_MESSENGER_TITLE'           => 'Messages',
	'JAUNTYM_M_NEW_MESSAGE'             => 'New message',
	'JAUNTYM_M_SEARCH_CONVERSATIONS'    => 'Search messages',
	'JAUNTYM_M_SEARCH_PEOPLE'           => 'Search for a member…',
	'JAUNTYM_M_TYPE_MESSAGE'            => 'Type a message…',
	'JAUNTYM_M_SEND'                    => 'Send',
	'JAUNTYM_M_CLOSE'                   => 'Close',
	'JAUNTYM_M_NO_CONVERSATIONS'        => 'No conversations yet. Start one with the New message button.',
	'JAUNTYM_M_PICK_CONVERSATION'       => 'Select a conversation to start chatting.',
	'JAUNTYM_M_SEEN'                    => 'Seen',
	'JAUNTYM_M_SENT'                    => 'Sent',
	'JAUNTYM_M_ONLINE'                  => 'Online',
	'JAUNTYM_M_LAST_SEEN'               => 'Last seen %s',
	'JAUNTYM_M_LOAD_OLDER'              => 'Load older messages',
	'JAUNTYM_M_OPEN_FULL'               => 'Open in Messenger',
	'JAUNTYM_M_DM'                      => 'Message',
	'JAUNTYM_M_EMPTY_THREAD'            => 'No messages yet — say hello!',
	'JAUNTYM_M_LOGIN_EXPLAIN'           => 'You need to be logged in to view your messages.',
	'JAUNTYM_M_TYPING'                  => 'typing…',
	'JAUNTYM_M_DELETED'                 => 'This message was deleted',
	'JAUNTYM_M_DELETE'                  => 'Delete',
	'JAUNTYM_M_CONFIRM_DELETE'          => 'Delete this message?',
	'JAUNTYM_M_MENU'                    => 'Options',
	'JAUNTYM_M_BLOCK'                   => 'Block user',
	'JAUNTYM_M_UNBLOCK'                 => 'Unblock user',
	'JAUNTYM_M_HIDE_CONV'               => 'Hide conversation',
	'JAUNTYM_M_CONFIRM_BLOCK'           => 'Block this member? You will no longer be able to message each other.',
	'JAUNTYM_M_YOU'                     => 'You:',
	'JAUNTYM_M_BADGE_MAX'               => '99+',

	// AJAX / error strings
	'JAUNTYM_M_ERR_AUTH'                => 'You are not allowed to use the messenger.',
	'JAUNTYM_M_ERR_LOGIN'               => 'Your session was not recognised. Please log out and back in, then try again.',
	'JAUNTYM_M_ERR_NOPERM'              => 'You do not have permission to use the messenger.',
	'JAUNTYM_M_ERR_TOKEN'               => 'Your session has expired. Please refresh the page and try again.',
	'JAUNTYM_M_ERR_EMPTY'               => 'You cannot send an empty message.',
	'JAUNTYM_M_ERR_TOOLONG'             => 'Your message is too long.',
	'JAUNTYM_M_ERR_RECIPIENT'           => 'That member could not be found.',
	'JAUNTYM_M_ERR_SELF'                => 'You cannot message yourself.',
	'JAUNTYM_M_ERR_NOT_MEMBER'          => 'You are not part of that conversation.',
	'JAUNTYM_M_ERR_BLOCKED'             => 'You cannot message this member.',
	'JAUNTYM_M_ERR_YOU_BLOCKED'         => 'You have blocked this member. Unblock them to send a message.',
	'JAUNTYM_M_ERR_GENERIC'             => 'Something went wrong. Please try again.',
	'JAUNTYM_M_ERR_DM'                  => 'You do not have permission to send direct messages.',

	// permission name shown in ACP
	'ACL_U_JAUNTYM_MESSENGER'           => 'Can use the conversation messenger',
	'ACL_U_JAUNTYM_DM'                  => 'Can send direct messages (start chats and message members)',

	// ACP module tree titles (loaded everywhere so the menu renders)
	'ACP_JAUNTYM_MESSENGER_TITLE'       => 'JauntyM Messenger',
	'ACP_JAUNTYM_MESSENGER_SETTINGS'    => 'Messenger settings',
]);
