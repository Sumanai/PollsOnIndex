<?php
/**
*
* @package phpBB Extension - PollsOnIndex
* @copyright (c) 2016 Sumanai
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace Sumanai\PollsOnIndex\migrations;

class pollonindex_1_0_0 extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return isset($this->config['poi_version']) && version_compare($this->config['poi_version'], '1.0.0', '>=');
	}

	static public function depends_on()
	{
		return array('\phpbb\db\migration\data\v310\dev');
	}

	public function update_data()
	{
		return array(
			array('config.add', array('poi_poll_position', '-1')),
			array('config.add', array('poi_poll_forum_id', '')),
			array('config.add', array('poi_poll_exclude_id', 0)),
			array('config.add', array('poi_poll_hide', 0)),
			array('config.add', array('poi_poll_limit', 5)),

			// Current version
			array('config.add', array('poi_version', '1.0.0')),
		);
	}
}
