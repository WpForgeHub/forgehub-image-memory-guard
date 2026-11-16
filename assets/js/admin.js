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
			var limitMb = parseFloat($('#calc_result').data('limit-mb')) || 256;
			var currentMb = parseFloat($('#calc_result').data('current-mb')) || 30;

			if (w <= 0 || h <= 0) {
				alert('Please enter valid dimensions.');
				return;
			}

			// Required memory calculation: RGBA (4 bytes) * 1.8 overhead buffer
			var decompBytes = w * h * 4 * 1.8;
			var decompMb = Math.round((decompBytes / (1024 * 1024)) * 10) / 10;
			var totalMb = Math.round((currentMb + decompMb) * 10) / 10;
			var percent = Math.round((totalMb / limitMb) * 100);

			var resDiv = $('#calc_result');
			resDiv.removeClass('safe warning danger');

			var html = '<strong>Resolution:</strong> ' + w + ' × ' + h + ' px (' + (Math.round(w * h / 100000) / 10) + ' MP)<br>';
			html += '<strong>Decompression Memory:</strong> ~' + decompMb + ' MB<br>';
			html += '<strong>Total Peak Memory Needed:</strong> ~' + totalMb + ' MB of ' + limitMb + ' MB limit (' + percent + '%)<br><br>';

			if (percent < 70) {
				resDiv.addClass('safe');
				html += '✅ <strong>Safe to Upload:</strong> This image requires well within your server memory limit.';
			} else if (percent <= 90) {
				resDiv.addClass('warning');
				html += '⚠️ <strong>Moderate Risk:</strong> This image consumes a high portion of memory. Concurrent uploads may fail.';
			} else {
				resDiv.addClass('danger');
				html += '🚨 <strong>Memory Exhaustion Risk:</strong> Uploading this image may trigger a PHP Fatal Error! Image Memory Guard auto-downscaling recommended.';
			}

			resDiv.html(html).show();
		});

		// Trigger initial calculation
		$('#btn_calculate_budget').trigger('click');
	});
})(jQuery);
