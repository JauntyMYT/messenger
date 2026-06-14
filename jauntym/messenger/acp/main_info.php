<?php
/**
 * JauntyM Messenger
 * @copyright (c) 2026 JauntyM
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace jauntym\messenger\acp;

class main_info
{
	public function module()
	{
		return [
			'filename' => '\jauntym\messenger\acp\main_module',
			'title'    => 'ACP_JAUNTYM_MESSENGER_TITLE',
			'modes'    => [
				'settings' => [
					'title' => 'ACP_JAUNTYM_MESSENGER_SETTINGS',
					'auth'  => 'ext_jauntym/messenger && acl_a_board',
					'cat'   => ['ACP_JAUNTYM_MESSENGER_TITLE'],
				],
			],
		];
	}
}
