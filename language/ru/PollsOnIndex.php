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
	'POLLS_COUNT_LANG'  => 'Опросы (всего %d)',
	'GO_TOPIC'          => 'перейти в тему',
));
