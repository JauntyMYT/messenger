<?php
/**
 * JauntyM Messenger
 * @copyright (c) 2026 JauntyM
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace jauntym\messenger\acp;

class main_module
{
	/** @var string */
	public $u_action;
	/** @var string */
	public $page_title;
	/** @var string */
	public $tpl_name;

	public function main($id, $mode)
	{
		global $config, $request, $template, $user;

		$user->add_lang_ext('jauntym/messenger', 'acp_messenger');

		$this->tpl_name   = 'acp_messenger_settings';
		$this->page_title = $user->lang('ACP_JAUNTYM_MESSENGER_SETTINGS');

		$form_key = 'jauntym_messenger_acp';
		add_form_key($form_key);

		if ($request->is_set_post('submit'))
		{
			if (!check_form_key($form_key))
			{
				trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$config->set('jauntym_msgr_poll',      max(5, $request->variable('jauntym_msgr_poll', 15)));
			$config->set('jauntym_msgr_maxlen',    max(1, $request->variable('jauntym_msgr_maxlen', 5000)));
			$config->set('jauntym_msgr_minsearch', max(1, $request->variable('jauntym_msgr_minsearch', 2)));
			$config->set('jauntym_msgr_bbcode',    $request->variable('jauntym_msgr_bbcode', 0));
			$config->set('jauntym_msgr_smilies',   $request->variable('jauntym_msgr_smilies', 0));
			$config->set('jauntym_msgr_typing',    $request->variable('jauntym_msgr_typing', 0));

			trigger_error($user->lang('ACP_JAUNTYM_SETTINGS_SAVED') . adm_back_link($this->u_action));
		}

		$template->assign_vars([
			'U_ACTION'      => $this->u_action,
			'JAUNTYM_POLL'      => (int) $config['jauntym_msgr_poll'],
			'JAUNTYM_MAXLEN'    => (int) $config['jauntym_msgr_maxlen'],
			'JAUNTYM_MINSEARCH' => (int) $config['jauntym_msgr_minsearch'],
			'JAUNTYM_BBCODE'    => !empty($config['jauntym_msgr_bbcode']),
			'JAUNTYM_SMILIES'   => !empty($config['jauntym_msgr_smilies']),
			'JAUNTYM_TYPING'    => !empty($config['jauntym_msgr_typing']),
		]);
	}
}
