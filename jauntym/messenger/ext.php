<?php
/**
 * JauntyM Messenger
 * @copyright (c) 2026 JauntyM
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace jauntym\messenger;

class ext extends \phpbb\extension\base
{
	/**
	 * Require phpBB 3.2.x or 3.3.x to enable this extension.
	 */
	public function is_enableable()
	{
		$config = $this->container->get('config');
		return phpbb_version_compare($config['version'], '3.2.0', '>=')
			&& phpbb_version_compare($config['version'], '4.0.0', '<');
	}
}
