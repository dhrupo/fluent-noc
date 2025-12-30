/**
 * Frontend form JavaScript
 */

(function($) {
	'use strict';
	
	$(document).ready(function() {
		var $startDate = $('#onoc-leave-start');
		var $endDate = $('#onoc-leave-end');
		
		// Get today's date in YYYY-MM-DD format
		var today = new Date();
		var todayStr = today.getFullYear() + '-' + 
			String(today.getMonth() + 1).padStart(2, '0') + '-' + 
			String(today.getDate()).padStart(2, '0');
		
		// Set minimum date to today for start date
		$startDate.attr('min', todayStr);
		
		// Set minimum date to today for end date
		$endDate.attr('min', todayStr);
		
		// When start date changes, update end date minimum
		$startDate.on('change', function() {
			var startDateVal = $(this).val();
			if (startDateVal) {
				// Set end date minimum to start date
				$endDate.attr('min', startDateVal);
				
				// If end date is before start date, clear it
				if ($endDate.val() && $endDate.val() < startDateVal) {
					$endDate.val('');
				}
			} else {
				// If start date is cleared, reset end date minimum to today
				$endDate.attr('min', todayStr);
			}
		});
		
		// Validate end date when it changes
		$endDate.on('change', function() {
			var endDateVal = $(this).val();
			var startDateVal = $startDate.val();
			
			if (endDateVal && startDateVal && endDateVal < startDateVal) {
				alert('Leave End Date must be after Leave Start Date.');
				$(this).val('');
			}
		});
		
		$('#onoc-application-form').on('submit', function(e) {
			e.preventDefault();
			
			var $form = $(this);
			var $submitBtn = $form.find('.onoc-submit-btn');
			var $messages = $form.find('.onoc-form-messages');
			
			// Validate dates before submission
			var startDateVal = $startDate.val();
			var endDateVal = $endDate.val();
			
			if (startDateVal && startDateVal < todayStr) {
				$messages.addClass('error').html('<p>Leave Start Date cannot be in the past.</p>');
				return;
			}
			
			if (endDateVal && endDateVal < todayStr) {
				$messages.addClass('error').html('<p>Leave End Date cannot be in the past.</p>');
				return;
			}
			
			if (startDateVal && endDateVal && endDateVal < startDateVal) {
				$messages.addClass('error').html('<p>Leave End Date must be after Leave Start Date.</p>');
				return;
			}
			
			// Disable submit button
			$submitBtn.prop('disabled', true).text('Submitting...');
			$messages.removeClass('success error').html('');
			
			// Get form data
			var formData = $form.serialize();
			formData += '&action=onoc_submit_request';
			
			// Submit via AJAX
			$.ajax({
				url: onocData.ajaxUrl,
				type: 'POST',
				data: formData,
				success: function(response) {
					if (response.success) {
						$messages.addClass('success').html('<p>' + response.data.message + '</p>');
						$form[0].reset();
						// Reset date minimums after form reset
						$startDate.attr('min', todayStr);
						$endDate.attr('min', todayStr);
					} else {
						$messages.addClass('error').html('<p>' + response.data.message + '</p>');
					}
				},
				error: function() {
					$messages.addClass('error').html('<p>An error occurred. Please try again.</p>');
				},
				complete: function() {
					$submitBtn.prop('disabled', false).text('Submit Request');
					
					// Scroll to messages
					$('html, body').animate({
						scrollTop: $messages.offset().top - 100
					}, 500);
				}
			});
		});
	});
})(jQuery);

