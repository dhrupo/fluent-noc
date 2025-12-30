/**
 * Admin JavaScript
 */

(function($) {
	'use strict';
	
	$(document).ready(function() {
		// Approve request
		$(document).on('click', '.onoc-approve-btn', function(e) {
			e.preventDefault();
			
			var $btn = $(this);
			var requestId = $btn.data('request-id');
			
			if (!confirm('Are you sure you want to approve this request?')) {
				return;
			}
			
			$btn.prop('disabled', true).text('Processing...');
			
			$.ajax({
				url: onocAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'onoc_approve_request',
					request_id: requestId,
					nonce: onocAdmin.nonce
				},
				success: function(response) {
					if (response.success) {
						alert(response.data.message);
						location.reload();
					} else {
						alert(response.data.message);
						$btn.prop('disabled', false).text('Approve');
					}
				},
				error: function() {
					alert('An error occurred. Please try again.');
					$btn.prop('disabled', false).text('Approve');
				}
			});
		});
		
		// Reject request
		$(document).on('click', '.onoc-reject-btn', function(e) {
			e.preventDefault();
			
			var $btn = $(this);
			var requestId = $btn.data('request-id');
			var hrNote = $('#onoc-hr-note').val();
			
			if (!hrNote || !hrNote.trim()) {
				alert('Please provide a rejection reason.');
				$('#onoc-hr-note').focus();
				return;
			}
			
			if (!confirm('Are you sure you want to reject this request?')) {
				return;
			}
			
			$btn.prop('disabled', true).text('Processing...');
			
			$.ajax({
				url: onocAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'onoc_reject_request',
					request_id: requestId,
					hr_note: hrNote,
					nonce: onocAdmin.nonce
				},
				success: function(response) {
					if (response.success) {
						alert(response.data.message);
						location.reload();
					} else {
						alert(response.data.message);
						$btn.prop('disabled', false).text('Reject');
					}
				},
				error: function() {
					alert('An error occurred. Please try again.');
					$btn.prop('disabled', false).text('Reject');
				}
			});
		});
		
		// Preview PDF
		$(document).on('click', '.onoc-preview-pdf-btn', function(e) {
			e.preventDefault();
			
			var $btn = $(this);
			$btn.prop('disabled', true).text('Generating Preview...');
			
			$.ajax({
				url: onocAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'onoc_preview_pdf',
					nonce: onocAdmin.nonce
				},
				success: function(response) {
					if (response.success) {
						// Open PDF in new window
						var newWindow = window.open();
						newWindow.document.write('<iframe src="' + response.data.pdf + '" style="width:100%;height:100%;border:none;"></iframe>');
					} else {
						alert(response.data.message);
					}
				},
				error: function() {
					alert('An error occurred. Please try again.');
				},
				complete: function() {
					$btn.prop('disabled', false).text('Preview PDF');
				}
			});
		});
		
		// Remove image
		$(document).on('click', '.onoc-remove-image-btn', function(e) {
			e.preventDefault();
			
			var $btn = $(this);
			var imageType = $btn.data('image-type');
			var $container = $btn.closest('p');
			
			if (!confirm('Are you sure you want to remove this image?')) {
				return;
			}
			
			$btn.prop('disabled', true).text('Removing...');
			
			$.ajax({
				url: onocAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'onoc_remove_image',
					image_type: imageType,
					nonce: onocAdmin.nonce
				},
				success: function(response) {
					if (response.success) {
						// Show success message
						var $message = $('#onoc-image-removal-message');
						$message.removeClass('notice-error').addClass('notice notice-success is-dismissible');
						$message.html('<p>' + response.data.message + '</p>').fadeIn();
						
						// Remove the image container
						$container.fadeOut(300, function() {
							$(this).remove();
						});
						
						// Clear the corresponding URL input field
						var urlInputId = '';
						if (imageType === 'signature') {
							urlInputId = '#signature_image';
						} else if (imageType === 'header') {
							urlInputId = '#pdf_header';
						} else if (imageType === 'footer') {
							urlInputId = '#pdf_footer';
						}
						
						if (urlInputId) {
							$(urlInputId).val('');
						}
						
						// Hide message after 3 seconds
						setTimeout(function() {
							$message.fadeOut();
						}, 3000);
					} else {
						alert(response.data.message);
						$btn.prop('disabled', false).text('Remove Image');
					}
				},
				error: function() {
					alert('An error occurred. Please try again.');
					$btn.prop('disabled', false).text('Remove Image');
				}
			});
		});
	});
})(jQuery);

