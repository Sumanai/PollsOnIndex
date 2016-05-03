$(document).ready(function () {
phpbb.addAjaxCallback('vote_poll', function(res) {
	if (typeof res.success !== 'undefined') {
		var poll = $(this);
		var panel = poll.find('.panel');
		var resultsVisible = poll.find('dl:first-child .resultbar').is(':visible');
		var mostVotes = 0;

		// Set min-height to prevent the page from jumping when the content changes
		var updatePanelHeight = function (height) {
			height = (typeof height === 'undefined') ? panel.find('.inner').outerHeight() : height;
			panel.css('min-height', height);
		};
		updatePanelHeight();

		// Remove the View results link
		if (!resultsVisible) {
			poll.find('.poll_view_results').hide(500);
		}

		if (!res.can_vote) {
			poll.find('.polls, .poll_max_votes, .poll_vote, .poll_option_select').fadeOut(500, function () {
				poll.find('.resultbar, .poll_option_percent, .poll_total_votes').show();
			});
		} else {
			// If the user can still vote, simply slide down the results
			poll.find('.resultbar, .poll_option_percent, .poll_total_votes').show(500);
		}

		// Get the votes count of the highest poll option
		poll.find('[data-poll-option-id]').each(function() {
			var option = $(this);
			var optionId = option.attr('data-poll-option-id');
			mostVotes = (res.vote_counts[optionId] >= mostVotes) ? res.vote_counts[optionId] : mostVotes;
		});

		// Update the total votes count
		poll.find('.poll_total_vote_cnt').html(res.total_votes);

		// Update each option
		poll.find('[data-poll-option-id]').each(function() {
			var $this = $(this);
			var optionId = $this.attr('data-poll-option-id');
			var voted = (typeof res.user_votes[optionId] !== 'undefined');
			var mostVoted = (res.vote_counts[optionId] === mostVotes);
			var percent = (!res.total_votes) ? 0 : Math.round((res.vote_counts[optionId] / res.total_votes) * 100);
			var percentRel = (mostVotes === 0) ? 0 : Math.round((res.vote_counts[optionId] / mostVotes) * 100);
			var altText;

			altText = $this.attr('data-alt-text');
			if (voted) {
				$this.attr('title', $.trim(altText));
			} else {
				$this.attr('title', '');
			};
			$this.toggleClass('voted', voted);
			$this.toggleClass('most-votes', mostVoted);

			// Update the bars
			var bar = $this.find('.resultbar div');
			var barTimeLapse = (res.can_vote) ? 500 : 1500;
			var newBarClass = (percent === 100) ? 'pollbar5' : 'pollbar' + (Math.floor(percent / 20) + 1);

			setTimeout(function () {
				bar.animate({ width: percentRel + '%' }, 500)
					.removeClass('pollbar1 pollbar2 pollbar3 pollbar4 pollbar5')
					.addClass(newBarClass)
					.html(res.vote_counts[optionId]);

				var percentText = percent ? percent + '%' : res.NO_VOTES;
				$this.find('.poll_option_percent').html(percentText);
			}, barTimeLapse);
		});

		if (!res.can_vote) {
			poll.find('.polls').delay(400).fadeIn(500);
		}

		// Display "Your vote has been cast." message. Disappears after 5 seconds.
		var confirmationDelay = (res.can_vote) ? 300 : 900;
		poll.find('.vote-submitted').delay(confirmationDelay).slideDown(200, function() {
			if (resultsVisible) {
				updatePanelHeight();
			}

			$(this).delay(5000).fadeOut(500, function() {
				resizePanel(300);
			});
		});

		// Remove the gap resulting from removing options
		setTimeout(function() {
			resizePanel(500);
		}, 1500);

		var resizePanel = function (time) {
			var panelHeight = panel.height();
			var innerHeight = panel.find('.inner').outerHeight();

			if (panelHeight !== innerHeight) {
				panel.css({ minHeight: '', height: panelHeight })
					.animate({ height: innerHeight }, time, function () {
						panel.css({ minHeight: innerHeight, height: '' });
					});
			}
		};
	}
});

jQuery(function($) {
	$('.control-buttons .previous').click(function() {
		$('#poll-row form:last').insertBefore('#poll-row form:first');
		$('#current-poll').text($('#poll-row form:first').data('poll-number') + 1);
		return false;
	});
	$('.control-buttons .next').click(function() {
		$('#poll-row form:first').insertAfter('#poll-row form:last');
		$('#current-poll').text($('#poll-row form:first').data('poll-number') + 1);
		return false;
	});
});

});