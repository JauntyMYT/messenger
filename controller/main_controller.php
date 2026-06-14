<?php
/**
 * JauntyM Messenger
 * @copyright (c) 2026 JauntyM
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace jauntym\messenger\controller;

use phpbb\controller\helper;
use phpbb\template\template;
use phpbb\user;
use phpbb\language\language;
use phpbb\request\request_interface;
use phpbb\auth\auth;
use phpbb\config\config;
use phpbb\exception\http_exception;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class main_controller
{
	/** @var helper */
	protected $helper;
	/** @var template */
	protected $template;
	/** @var user */
	protected $user;
	/** @var language */
	protected $language;
	/** @var request_interface */
	protected $request;
	/** @var auth */
	protected $auth;
	/** @var config */
	protected $config;

	public function __construct(helper $helper, template $template, user $user, language $language, request_interface $request, auth $auth, config $config)
	{
		$this->helper   = $helper;
		$this->template = $template;
		$this->user     = $user;
		$this->language = $language;
		$this->request  = $request;
		$this->auth     = $auth;
		$this->config   = $config;
	}

	public function index()
	{
		if ((int) $this->user->data['user_id'] === ANONYMOUS)
		{
			login_box('', $this->language->lang('JAUNTYM_M_LOGIN_EXPLAIN'));
		}

		if (!$this->auth->acl_get('u_jauntym_messenger'))
		{
			throw new http_exception(403, 'JAUNTYM_M_ERR_AUTH');
		}

		$ajax_url = $this->helper->route(
			'jauntym_messenger_ajax',
			[],
			false,
			false,
			UrlGeneratorInterface::ABSOLUTE_URL
		);

		$this->template->assign_vars([
			'JAUNTYM_AJAX_URL'    => $ajax_url,
			'JAUNTYM_TOKEN'       => generate_link_hash('jauntym_messenger'),
			'JAUNTYM_CUR_USER_ID' => (int) $this->user->data['user_id'],
			'JAUNTYM_SID'         => $this->user->session_id,
			'JAUNTYM_POLL_MS'     => max(5, (int) $this->config['jauntym_msgr_poll']) * 1000,
			'JAUNTYM_MAXLEN'      => (int) $this->config['jauntym_msgr_maxlen'],
			'JAUNTYM_MINSEARCH'   => max(1, (int) $this->config['jauntym_msgr_minsearch']),
			'JAUNTYM_TYPING_ON'   => !empty($this->config['jauntym_msgr_typing']) ? 1 : 0,
			'S_JAUNTYM_CAN_DM'    => (bool) $this->auth->acl_get('u_jauntym_dm'),
		]);

		return $this->helper->render('messenger_body.html', $this->language->lang('JAUNTYM_MESSENGER_TITLE'));
	}
}
