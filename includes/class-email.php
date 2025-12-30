<?php
/**
 * Email notifications
 *
 * @package Office_NOC_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ONOC_Email {
	
	/**
	 * Send submission confirmation email
	 */
	public function send_submission_confirmation( $email, $reference_id, $full_name = '' ) {
		$subject = __( 'NOC Request Submitted', 'office-noc-manager' );
		$message = $this->get_submission_email_template( $reference_id, $full_name );
		
		$from_name = get_option( 'onoc_email_from_name', '' );
		$from_address = get_option( 'onoc_email_from_address', get_option( 'admin_email' ) );
		
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
		);
		
		// Only add From header if we have valid values
		if ( ! empty( $from_name ) && ! empty( $from_address ) && is_email( $from_address ) ) {
			$headers[] = 'From: ' . sanitize_text_field( $from_name ) . ' <' . sanitize_email( $from_address ) . '>';
		}
		
		wp_mail( $email, $subject, $message, $headers );
	}
	
	/**
	 * Send approval email with PDF attachment
	 */
	public function send_approval_email( $request_id ) {
		$db = new ONOC_DB();
		$request = $db->get_request( $request_id );
		
		if ( ! $request || empty( $request['pdf_url'] ) ) {
			return false;
		}
		
		$subject = __( 'NOC Request Approved', 'office-noc-manager' );
		$message = $this->get_approval_email_template( $request );
		
		$from_name = get_option( 'onoc_email_from_name', '' );
		$from_address = get_option( 'onoc_email_from_address', get_option( 'admin_email' ) );
		
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
		);
		
		// Only add From header if we have valid values
		if ( ! empty( $from_name ) && ! empty( $from_address ) && is_email( $from_address ) ) {
			$headers[] = 'From: ' . sanitize_text_field( $from_name ) . ' <' . sanitize_email( $from_address ) . '>';
		}
		
		// Get PDF file path
		$upload_dir = wp_upload_dir();
		$pdf_filename = 'noc-' . sanitize_file_name( $request['reference_id'] ) . '.pdf';
		$pdf_path = $upload_dir['basedir'] . '/noc-pdfs/' . $pdf_filename;
		
		$attachments = array();
		
		// Try primary path first
		if ( ! empty( $pdf_path ) && is_string( $pdf_path ) && file_exists( $pdf_path ) && is_file( $pdf_path ) ) {
			$attachments[] = $pdf_path;
		} else {
			// Try to get from URL if path doesn't exist
			if ( ! empty( $request['pdf_url'] ) && is_string( $request['pdf_url'] ) ) {
				$pdf_url_path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $request['pdf_url'] );
				if ( ! empty( $pdf_url_path ) && is_string( $pdf_url_path ) && file_exists( $pdf_url_path ) && is_file( $pdf_url_path ) ) {
					$attachments[] = $pdf_url_path;
				}
			}
		}
		
		// Ensure attachments array only contains valid file path strings
		$attachments = array_filter( $attachments, function( $path ) {
			return is_string( $path ) && ! empty( $path ) && file_exists( $path ) && is_file( $path );
		} );
		
		// Re-index array to ensure it's a simple indexed array
		$attachments = array_values( $attachments );
		
		// Send email with PDF attachment
		$mail_result = wp_mail( $request['email'], $subject, $message, $headers, $attachments );
		
		// Log if attachment failed (for debugging)
		if ( empty( $attachments ) ) {
			error_log( 'ONOC: PDF attachment not found for request ID: ' . $request_id . ', Path: ' . $pdf_path );
		}
		
		return $mail_result;
	}
	
	/**
	 * Send rejection email
	 */
	public function send_rejection_email( $request_id ) {
		$db = new ONOC_DB();
		$request = $db->get_request( $request_id );
		
		if ( ! $request ) {
			return false;
		}
		
		$subject = __( 'NOC Request Not Approved', 'office-noc-manager' );
		$message = $this->get_rejection_email_template( $request );
		
		$from_name = get_option( 'onoc_email_from_name', '' );
		$from_address = get_option( 'onoc_email_from_address', get_option( 'admin_email' ) );
		
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
		);
		
		// Only add From header if we have valid values
		if ( ! empty( $from_name ) && ! empty( $from_address ) && is_email( $from_address ) ) {
			$headers[] = 'From: ' . sanitize_text_field( $from_name ) . ' <' . sanitize_email( $from_address ) . '>';
		}
		
		wp_mail( $request['email'], $subject, $message, $headers );
		
		return true;
	}
	
	/**
	 * Get submission email template
	 */
	private function get_submission_email_template( $reference_id, $full_name = '' ) {
		$greeting_name = ! empty( $full_name ) ? esc_html( $full_name ) : __( 'Applicant', 'office-noc-manager' );
		
		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
		</head>
		<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
			<div style="max-width: 600px; margin: 0 auto; padding: 20px;">
				<h2 style="color: #2c3e50;">NOC Request Submitted</h2>
				<p>Dear <?php echo $greeting_name; ?>,</p>
				<p>Your NOC request has been submitted successfully and is under review.</p>
				<p><strong>Reference ID:</strong> <?php echo esc_html( $reference_id ); ?></p>
				<p>You will receive an email notification once your request has been reviewed.</p>
				<p>Thank you for your patience.</p>
				<hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
				<p style="font-size: 12px; color: #999;">This is an automated email. Please do not reply.</p>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}
	
	/**
	 * Get approval email template
	 */
	private function get_approval_email_template( $request ) {
		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
		</head>
		<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
			<div style="max-width: 600px; margin: 0 auto; padding: 20px;">
				<h2 style="color: #27ae60;">NOC Request Approved</h2>
				<p>Dear <?php echo esc_html( $request['full_name'] ); ?>,</p>
				<p>Your NOC request has been approved.</p>
				<p><strong>Reference ID:</strong> <?php echo esc_html( $request['reference_id'] ); ?></p>
				<p><strong>Visiting Country:</strong> <?php echo esc_html( $request['visiting_country'] ); ?></p>
				<p><strong>Leave Period:</strong> <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $request['leave_start'] ) ) ); ?> - <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $request['leave_end'] ) ) ); ?></p>
				<p>Please find your NOC certificate attached to this email.</p>
				<p>You can also verify your NOC online using the following link:</p>
				<p><a href="<?php echo esc_url( home_url( '/noc-verification/?ref=' . rawurlencode( $request['reference_id'] ) ) ); ?>" style="color: #3498db;">Verify NOC Online</a></p>
				<p>Thank you.</p>
				<hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
				<p style="font-size: 12px; color: #999;">This is an automated email. Please do not reply.</p>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}
	
	/**
	 * Get rejection email template
	 */
	private function get_rejection_email_template( $request ) {
		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
		</head>
		<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
			<div style="max-width: 600px; margin: 0 auto; padding: 20px;">
				<h2 style="color: #e74c3c;">NOC Request Not Approved</h2>
				<p>Dear <?php echo esc_html( $request['full_name'] ); ?>,</p>
				<p>We regret to inform you that your NOC request has not been approved.</p>
				<p><strong>Reference ID:</strong> <?php echo esc_html( $request['reference_id'] ); ?></p>
				<?php if ( ! empty( $request['visiting_country'] ) ) : ?>
					<p><strong>Visiting Country:</strong> <?php echo esc_html( $request['visiting_country'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $request['leave_start'] ) && ! empty( $request['leave_end'] ) ) : ?>
					<p><strong>Leave Period:</strong> <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $request['leave_start'] ) ) ); ?> - <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $request['leave_end'] ) ) ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $request['hr_note'] ) ) : ?>
					<div style="background-color: #fff3cd; border-left: 4px solid #e74c3c; padding: 15px; margin: 20px 0;">
						<p style="margin: 0 0 10px 0;"><strong style="color: #e74c3c;">Rejection Reason:</strong></p>
						<p style="margin: 0; white-space: pre-wrap;"><?php echo esc_html( $request['hr_note'] ); ?></p>
					</div>
				<?php endif; ?>
				<p>If you have any questions, please contact HR.</p>
				<p>Thank you.</p>
				<hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
				<p style="font-size: 12px; color: #999;">This is an automated email. Please do not reply.</p>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}
}

