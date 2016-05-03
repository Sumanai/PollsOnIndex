<?php
/**
*
* @package phpBB Extension - PollsOnIndex
* @copyright (c) 2016 Sumanai
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace Sumanai\PollsOnIndex\event;

/**
* Event listener
*/
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	private $auth;
	private $config;
	private $db;
	private $request;
	private $template;
	private $user;

	private $forums_table;
	private $poll_options_table;
	private $poll_votes_table;
	private $posts_table;
	private $topics_table;
	private $users_table;

	private $root_path;
	private $php_ex;

	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\config\config $config,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\request\request_interface $request,
		\phpbb\template\template $template,
		\phpbb\user $user,

		$forums_table,
		$poll_options_table,
		$poll_votes_table,
		$posts_table,
		$topics_table,
		$users_table,

		$root_path,
		$php_ex
	) {
		$this->auth = $auth;
		$this->config = $config;
		$this->db = $db;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;

		$this->forums_table = $forums_table;
		$this->poll_options_table = $poll_options_table;
		$this->poll_votes_table = $poll_votes_table;
		$this->posts_table = $posts_table;
		$this->topics_table = $topics_table;
		$this->users_table = $users_table;

		$this->root_path = $root_path;
		$this->php_ex = $php_ex;
	}

	static public function getSubscribedEvents()
	{
		return array(
			'core.index_modify_page_title'      => 'display_polls_on_index',
			'core.acp_board_config_edit_add'    => 'add_config',
		);
	}

	public function display_polls_on_index($event)
	{
		$forum_list = array_unique(array_keys($this->auth->acl_getf('f_read', true)));

		if($this->config['poi_poll_forum_id'] !== '')
		{
			$poll_forums_config = explode(',' ,$this->config['poi_poll_forum_id']);

			if($this->config['poi_poll_exclude_id'])
			{
				$forum_list = array_unique(array_diff($forum_list, $poll_forums_config));
			}
			else
			{
				$forum_list = array_unique(array_intersect($poll_forums_config, $forum_list));
			}
		}

		// Available user forums are not found, get out
		if(!sizeof($forum_list))
		{
			return;
		}

		if ($this->config['poi_poll_hide'])
		{
			$poll_hide = "AND (t.poll_start + t.poll_length > ". time() ." OR t.poll_length = 0)";
		}
		else
		{
			$poll_hide = '';
		}

		// Get the available polls of the public topics
		$sql = 'SELECT t.*, p.bbcode_bitfield, p.bbcode_uid, f.forum_status
			FROM ' . $this->topics_table . ' t, ' . $this->posts_table . ' p, ' . $this->forums_table . ' f
			WHERE t.forum_id = f.forum_id AND t.topic_visibility = ' . ITEM_APPROVED . ' AND t.poll_start > 0
				AND ' . $this->db->sql_in_set('t.forum_id', $forum_list) . "
				AND t.topic_moved_id = 0
				AND p.post_id = t.topic_first_post_id
				{$poll_hide}
			ORDER BY t.poll_start DESC";
		$result = $this->db->sql_query_limit($sql, (int) $this->config['poi_poll_limit']);

		$polls_data = $show_voters_topic_id_list = array();
		while($row = $this->db->sql_fetchrow($result))
		{
			$polls_data[$row['topic_id']] = $row;
			// For phpBBex, we select topics displaying voted
			if (isset($row['poll_show_voters']) && $row['poll_show_voters'])
			{
				$show_voters_topic_id_list[] = $row['topic_id'];
			}
		}
		$this->db->sql_freeresult($result);

		// Available user polls not found, get out
		if(!sizeof($polls_data))
		{
			return;
		}

		// Topics list in have polls
		$topic_list = array_keys($polls_data);

		$in_phpbbex = isset($this->config['phpbbex_version']) && version_compare($this->config['phpbbex_version'], '2.0.0', '>=');

		$all_voters = $all_votes = array();
		if ($in_phpbbex)
		{
			if(sizeof($show_voters_topic_id_list))
			{
				$sql = 'SELECT u.user_id, u.username, u.user_colour, pv.poll_option_id, pv.vote_time, pv.topic_id
					FROM ' . $this->poll_votes_table . ' pv, ' . $this->users_table . ' u
					WHERE ' . $this->db->sql_in_set('pv.topic_id', $show_voters_topic_id_list) . '
						AND pv.vote_user_id = u.user_id
					ORDER BY pv.vote_time ASC, pv.vote_user_id ASC';
				$result = $this->db->sql_query($sql);

				while ($row = $this->db->sql_fetchrow($result))
				{
					$all_voters[$row['topic_id']][(int)$row['user_id']] = true;
					$all_votes[$row['topic_id']][(int)$row['poll_option_id']][] = $row;
				}
				$this->db->sql_freeresult($result);
			}

			if (count($show_voters_topic_id_list) < count($topic_list))
			{
				$sql = 'SELECT COUNT(DISTINCT vote_user_id) as count, topic_id
					FROM ' . $this->poll_votes_table . '
					WHERE ' . $this->db->sql_in_set('topic_id', array_diff($topic_list, $show_voters_topic_id_list)) . '
					GROUP BY topic_id';
				$result = $this->db->sql_query($sql);
				while ($row = $this->db->sql_fetchrow($result))
				{
					$all_voters[$row['topic_id']] = $row['count'];
				}
				$this->db->sql_freeresult($result);
			}
		}

		add_form_key('posting');

		$this->user->add_lang('viewtopic');
		$this->user->add_lang_ext('Sumanai/PollsOnIndex', 'PollsOnIndex');

		// Get the answers in polls
		$sql = 'SELECT o.*
			FROM ' . $this->poll_options_table . ' o
			WHERE ' . $this->db->sql_in_set('o.topic_id', $topic_list) . '
			ORDER BY o.poll_option_id';
		$result = $this->db->sql_query($sql);

		$all_poll_info = $option_id = $all_vote_counts = array();
		while ($row = $this->db->sql_fetchrow($result))
		{
			$row['bbcode_bitfield'] = $polls_data[$row['topic_id']]['bbcode_bitfield'];
			$row['bbcode_uid'] = $polls_data[$row['topic_id']]['bbcode_uid'];
			$all_poll_info[$row['topic_id']][] = $row;

			$option_id = (int) $row['poll_option_id'];
			$all_vote_counts[$row['topic_id']][$option_id] = (int) $row['poll_option_total'];
		}
		$this->db->sql_freeresult($result);

		// Get the answer options, for which the user has voted
		$cur_voted_ids = array();
		if ($this->user->data['is_registered'])
		{
			$sql = 'SELECT poll_option_id, topic_id
				FROM ' . $this->poll_votes_table . '
				WHERE ' . $this->db->sql_in_set('topic_id', $topic_list) . '
					AND vote_user_id = ' . $this->user->data['user_id'];
			$result = $this->db->sql_query($sql);

			while ($row = $this->db->sql_fetchrow($result))
			{
				$cur_voted_ids[$row['topic_id']][] = $row['poll_option_id'];
			}
			$this->db->sql_freeresult($result);
		}
		else
		{
			// Cookie based guest tracking ... I don't like this but hum ho
			// it's oft requested. This relies on "nice" users who don't feel
			// the need to delete cookies to mess with results.
			foreach ($topic_list as $topic_id)
			{
				if ($this->request->is_set($this->config['cookie_name'] . '_poll_' . $topic_id, \phpbb\request\request_interface::COOKIE))
				{
					$cur_voted_ids[$topic_id] = explode(',', $this->request->variable($this->config['cookie_name'] . '_poll_' . $topic_id, '', true, \phpbb\request\request_interface::COOKIE));
					$cur_voted_ids[$topic_id] = array_map('intval', $cur_voted_id);
				}
			}
		}

		foreach ($polls_data as $topic_id => $topic_data)
		{
			$cur_voted_id = isset($cur_voted_ids[$topic_id]) ? $cur_voted_ids[$topic_id] : array();
			$vote_counts = $all_vote_counts[$topic_id];
			$forum_id = $topic_data['forum_id'];
			$poll_info = $all_poll_info[$topic_id];

			$voters_total = '';
			if ($in_phpbbex)
			{
				$voters = isset($all_voters[$topic_id]) ? $all_voters[$topic_id] : 0;
				$voters_total = is_array($voters) ? count($voters) : $voters;
				$votes = isset($all_votes[$topic_id]) ? $all_votes[$topic_id] : '';

				foreach ($poll_info as &$option)
				{
					$option['poll_option_voters'] = '';
					if (empty($votes[(int)$option['poll_option_id']]))
					{
						continue;
					}

					foreach ($votes[(int)$option['poll_option_id']] as $vote)
					{
						$option['poll_option_voters'] .= ', ' . get_username_string('full', $vote['user_id'], $vote['username'], $vote['user_colour'], $vote['username'], false, $vote['vote_time'] ? $this->user->format_date($vote['vote_time']) : '');
					}
					$option['poll_option_voters'] = ltrim($option['poll_option_voters'], ', ');
				}
				unset($option);
			}

			// General Viewtopic URL for return links
			$viewtopic_url = append_sid("{$this->root_path}viewtopic.{$this->php_ex}", "f=$forum_id&amp;t=$topic_id");

			// Can not vote at all if no vote permission
			$s_can_vote = ($this->auth->acl_get('f_vote', $forum_id) &&
				(($topic_data['poll_length'] != 0 && $topic_data['poll_start'] + $topic_data['poll_length'] > time()) || $topic_data['poll_length'] == 0) &&
				$topic_data['topic_status'] != ITEM_LOCKED &&
				$topic_data['forum_status'] != ITEM_LOCKED &&
				(!sizeof($cur_voted_id) ||
				($this->auth->acl_get('f_votechg', $forum_id) && $topic_data['poll_vote_change']))) ? true : false;
			$s_display_results = (!$s_can_vote || ($s_can_vote && sizeof($cur_voted_id))) ? true : false;

			$poll_total = 0;
			$poll_most = 0;
			foreach ($poll_info as $poll_option)
			{
				$poll_total += $poll_option['poll_option_total'];
				$poll_most = ($poll_option['poll_option_total'] >= $poll_most) ? $poll_option['poll_option_total'] : $poll_most;
			}

			$parse_flags = ($poll_info[0]['bbcode_bitfield'] ? OPTION_FLAG_BBCODE : 0) | OPTION_FLAG_SMILIES;

			for ($i = 0, $size = sizeof($poll_info); $i < $size; $i++)
			{
				$poll_info[$i]['poll_option_text'] = \generate_text_for_display($poll_info[$i]['poll_option_text'], $poll_info[$i]['bbcode_uid'], $poll_info[0]['bbcode_bitfield'], $parse_flags, true);
			}

			$topic_data['poll_title'] = \generate_text_for_display($topic_data['poll_title'], $poll_info[0]['bbcode_uid'], $poll_info[0]['bbcode_bitfield'], $parse_flags, true);

			$poll_template_data = $poll_options_template_data = array();
			foreach ($poll_info as $poll_option)
			{
				if ($in_phpbbex)
				{
					$option_pct = ($voters_total > 0) ? $poll_option['poll_option_total'] / $voters_total : 0;
				}
				else
				{
					$option_pct = ($poll_total > 0) ? $poll_option['poll_option_total'] / $poll_total : 0;
				}
				$option_pct_txt = sprintf("%.1d%%", round($option_pct * 100));
				$option_pct_rel = ($poll_most > 0) ? $poll_option['poll_option_total'] / $poll_most : 0;
				$option_pct_rel_txt = sprintf("%.1d%%", round($option_pct_rel * 100));
				$option_most_votes = ($poll_option['poll_option_total'] > 0 && $poll_option['poll_option_total'] == $poll_most) ? true : false;

				$poll_options_template_data[] = array(
					'POLL_OPTION_ID'           => $poll_option['poll_option_id'],
					'POLL_OPTION_CAPTION'      => $poll_option['poll_option_text'],
					'POLL_OPTION_RESULT'       => $poll_option['poll_option_total'],
					'POLL_OPTION_PERCENT'      => $option_pct_txt,
					'POLL_OPTION_PERCENT_REL'  => $option_pct_rel_txt,
					'POLL_OPTION_PCT'          => round($option_pct * 100),
					'POLL_OPTION_WIDTH'        => round($option_pct * 250),
					'POLL_OPTION_VOTERS'       => isset($poll_option['poll_option_voters']) ? $poll_option['poll_option_voters'] : '',
					'POLL_OPTION_VOTED'        => (in_array($poll_option['poll_option_id'], $cur_voted_id)) ? true : false,
					'POLL_OPTION_MOST_VOTES'   => $option_most_votes,
				);
			}

			$poll_end = $topic_data['poll_length'] + $topic_data['poll_start'];

			$poll_template_data = array(
				'POLL_TOPIC_ID'     => $topic_id,
				'POLL_QUESTION'     => $topic_data['poll_title'],
				'POLL_VOTED'        => count($cur_voted_id) > 0,
				'TOTAL_VOTES'       => $poll_total,
				'TOTAL_VOTERS'      => $voters_total,

				'POLL_AUTHOR'       => get_username_string('full', $topic_data['topic_poster'], $topic_data['topic_first_poster_name'], $topic_data['topic_first_poster_colour']),
				'POLL_TIME'         => $this->user->format_date($topic_data['poll_start']),

				'L_MAX_VOTES'       => $this->user->lang('MAX_OPTIONS_SELECT', (int) $topic_data['poll_max_options']),
				'L_POLL_LENGTH'     > ($topic_data['poll_length']) ? sprintf($this->user->lang[($poll_end > time()) ? 'POLL_RUN_TILL' : 'POLL_ENDED_AT'], $this->user->format_date($poll_end)) : '',

				'S_CAN_VOTE'        => $s_can_vote,
				'S_DISPLAY_RESULTS' => $s_display_results,
				'S_IS_MULTI_CHOICE' => ($topic_data['poll_max_options'] > 1) ? true : false,
				'S_POLL_ACTION'     => $viewtopic_url,
				'S_SHOW_VOTERS'     => (isset($topic_data['poll_show_voters'])) ? true : false,

				'U_VIEW_RESULTS'    => $viewtopic_url . '&amp;view=viewpoll',
			);

			$this->template->assign_block_vars('poll_row', $poll_template_data);

			$this->template->assign_block_vars_array('poll_row.poll_option', $poll_options_template_data);

			unset($poll_end, $poll_info, $poll_options_template_data, $poll_template_data, $voted_id);
		}

		$this->template->assign_vars(array(
			'S_HAS_POLL'        => true,
			'S_PHPBBEX'         => $in_phpbbex,
			'N_POLLS'           => count($topic_list),
			'POLLS_POSITION'    => $this->config['poi_poll_position'],
			'POLLS_COUNT_LANG'  => $this->user->lang('POLLS_COUNT_LANG', count($polls_data)),
		));
	}

	public function add_config($event)
	{
		$mode = $event['mode'];
		if ($mode == 'features')
		{
			$this->user->add_lang_ext('Sumanai/PollsOnIndex', 'ACP');

			$display_vars = $event['display_vars'];

			// We add a new legend, but we need to search for the last legend instead of hard-coding
			$submit_key = array_search('ACP_SUBMIT_CHANGES', $display_vars['vars']);
			$submit_legend_number = substr($submit_key, 6);
			$display_vars['vars']['legend'.$submit_legend_number] = 'ACP_POLLS_ON_INDEX_TITLE';
			$new_vars = array(
				'poi_poll_position' => array(
					'lang'      => 'ACP_POI_POLL_POSITION',
					'validate'  => 'string',
					'type'      => 'select',
					'function'  => '\Sumanai\PollsOnIndex\event\select_display_after_forums',
					'explain'   => false,
				),
				'poi_poll_forum_id' => array(
					'lang'      => 'ACP_POI_POLLS_FORUM_ID',
					'validate'  => 'string',
					'type'      => 'text:40:255',
					'explain'   => true,
				),
				'poi_poll_exclude_id' => array(
					'lang'      => 'ACP_POI_EXCLUDE_ID',
					'validate'  => 'bool',
					'type'      => 'radio:yes_no',
					'explain'   => false,
				),
				'poi_poll_hide' => array(
					'lang'      => 'ACP_POI_POLL_HIDE',
					'validate'  => 'bool',
					'type'      => 'radio:yes_no',
					'explain'   => false,
				),
				'poi_poll_limit' => array(
					'lang'      => 'ACP_POI_POLL_LIMIT',
					'validate'  => 'int:0:999',
					'type'      => 'number:0:999',
					'explain'   => false,
				),
				'legend'.($submit_legend_number + 1) => 'ACP_SUBMIT_CHANGES',
			);

			$display_vars['vars'] = phpbb_insert_config_array($display_vars['vars'], $new_vars, array('after' => $submit_key));
			$event['display_vars'] = $display_vars;
		}
	}

	public function select_display_after_forums()
	{
		$option_array = array(
			array(-1, ($this->config['poi_poll_position'] == -1), $this->user->lang('BEFORE_FORUM_LIST')),
			array(-2, ($this->config['poi_poll_position'] == -2), $this->user->lang('AFTER_FORUM_LIST')),
		);

		$option_text = '';
		foreach ($option_array as $option)
		{
			$option_text .= '<option value="' . $option[0] . ($option[1] ? '" selected="selected">' : '">') . $option[2] . '</option>';
		}

		return $option_text;
	}
}

// It is necessary here because phpBB can not call a function of the unknown to him class. Proxy request.
function select_display_after_forums()
{
	global $phpbb_container;

	$listener = $phpbb_container->get('Sumanai.PollsOnIndex.listener');
	return $listener->select_display_after_forums();
}
