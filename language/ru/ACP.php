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
	'ACP_POLLS_ON_INDEX_TITLE'          => 'Настройки опросов на главной странице',
	'ACP_POI_POLL_POSITION'             => 'Положение опросов на главной',
	'ACP_POI_POLLS_FORUM_ID'            => 'Список ID форумов',
	'ACP_POI_POLLS_FORUM_ID_EXPLAIN'    => 'Через запятую, если пусто- то будут включены все форумы',
	'ACP_POI_EXCLUDE_ID'                => 'Если да, то указанные выше будут в исключениях, если нет, то будут использоваться только они',
	'ACP_POI_POLL_HIDE'                 => 'Скрывать завершившиеся опросы',
	'ACP_POI_POLL_LIMIT'                => 'Максимальное число опросов, отображающихся на главной',

	'BEFORE_FORUM_LIST' => 'Перед списком разделов',
	'AFTER_FORUM_LIST'  => 'После списка разделов',
));
