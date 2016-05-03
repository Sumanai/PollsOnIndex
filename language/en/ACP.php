<?php
/**
*
* @package phpBB Extension - PollsOnIndex
* @copyright (c) 2016 Sumanai
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

$lang = array_merge($lang, array(
	'ACP_POLLS_ON_INDEX_TITLE'          => 'Settings polls on main page',
	'ACP_POI_POLL_POSITION'             => 'Poll position in the main page',
	'ACP_POI_POLLS_FORUM_ID'            => 'Polls forum ID',
	'ACP_POI_POLLS_FORUM_ID_EXPLAIN'    => 'A comma separated, if the emptiness then all forums will be included',
	'ACP_POI_EXCLUDE_ID'                => 'If yes, then the above will be exceptions, if not, they will only be used',
	'ACP_POI_POLL_HIDE'                 => 'Hide ended polls',
	'ACP_POI_POLL_LIMIT'                => 'The maximum number of poll that are displayed on the main page',

	'BEFORE_FORUM_LIST' => 'Before forum list',
	'AFTER_FORUM_LIST'  => 'After forum list',
));
