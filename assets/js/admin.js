/**
 * Image Memory Guard Admin Scripts
 */
(function($) {
	'use strict';

	$(document).ready(function() {
		$('#btn_calculate_budget').on('click', function(e) {
			e.preventDefault();

			var w = parseInt($('#calc_width').val(), 10) || 0;
			var h = parseInt($('#calc_height').val(), 10) || 0;
			var rawLimit = parseFloat($('#calc_result').data('limit-mb'));
			var isUnlimited = (!isNaN(rawLimit) && rawLimit < 0);
			var limitMb = (!isNaN(rawLimit) && rawLimit > 0) ? rawLimit : 256;
			var currentMb = parseFloat($('#calc_result').data('current-mb')) || 30;

			if (w <= 0 || h <= 0) {
				alert('Please enter valid dimensions.');
				return;
			}

			// Required memory calculation: RGBA (4 bytes) * 1.8 overhead buffer
			var decompBytes = w * h * 4 * 1.8;
			var decompMb = Math.round((decompBytes / (1024 * 1024)) * 10) / 10;
			var totalMb = Math.round((currentMb + decompMb) * 10) / 10;

			var resDiv = $('#calc_result');
			resDiv.removeClass('safe warning danger');

			var html = '<strong>Resolution:</strong> ' + w + ' × ' + h + ' px (' + (Math.round(w * h / 100000) / 10) + ' MP)<br>';
			html += '<strong>Decompression Memory:</strong> ~' + decompMb + ' MB<br>';

			if (isUnlimited) {
				html += '<strong>Total Peak Memory Needed:</strong> ~' + totalMb + ' MB (Server limit: Unlimited)<br><br>';
				resDiv.addClass('safe');
				html += '✅ <strong>Safe to Upload:</strong> PHP memory limit is set to unlimited (-1).';
			} else {
				var percent = Math.round((totalMb / limitMb) * 100);
				html += '<strong>Total Peak Memory Needed:</strong> ~' + totalMb + ' MB of ' + limitMb + ' MB limit (' + percent + '%)<br><br>';

				if (percent < 70) {
					resDiv.addClass('safe');
					html += '✅ <strong>Safe to Upload:</strong> This image requires well within your server memory limit.';
				} else if (percent <= 90) {
					resDiv.addClass('warning');
					html += '⚠️ <strong>Moderate Risk:</strong> This image consumes a high portion of memory. Concurrent uploads may fail.';
				} else {
					resDiv.addClass('danger');
					html += '🚨 <strong>Memory Exhaustion Risk:</strong> Uploading this image may trigger a PHP Fatal Error! Image Memory Guard will prevent or safely downscale it.';
				}
			}

			resDiv.html(html).show();
		});

		// Pro Background Queue trigger
		$('#wpfh_img_start_queue_btn').on('click', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var $fb = $('#wpfh_img_queue_feedback');
			if (typeof wpfhImgAdmin === 'undefined') {
				return;
			}
			$btn.prop('disabled', true);
			$fb.css('color', '#0284c7').text('Queueing media library...');
			$.post(wpfhImgAdmin.ajax_url, {
				action: 'wpfh_img_start_batch_queue',
				_ajax_nonce: wpfhImgAdmin.nonce
			}, function(res) {
				$btn.prop('disabled', false);
				if (res && res.success) {
					$('#wpfh_img_queue_count').text(res.data.queued_count);
					$fb.css('color', '#10b981').text('Queued ' + res.data.queued_count + ' images for background optimization.');
				} else {
					$fb.css('color', '#ef4444').text((res && res.data) ? res.data : 'Failed to start queue.');
				}
			}).fail(function() {
				$btn.prop('disabled', false);
				$fb.css('color', '#ef4444').text('Network error.');
			});
		});

		// Trigger initial calculation
		$('#btn_calculate_budget').trigger('click');
	});
})(jQuery);
