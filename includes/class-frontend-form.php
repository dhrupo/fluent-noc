<?php
/**
 * Frontend application form
 *
 * @package Office_NOC_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ONOC_Frontend_Form {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}
	
	/**
	 * Enqueue scripts and styles
	 */
	public function enqueue_scripts() {
		wp_enqueue_style(
			'onoc-frontend',
			ONOC_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			ONOC_VERSION
		);
		
		wp_enqueue_script(
			'onoc-frontend',
			ONOC_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			ONOC_VERSION,
			true
		);
		
		wp_localize_script( 'onoc-frontend', 'onocData', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'onoc_submit_request' ),
		) );
	}
	
	/**
	 * Render form shortcode
	 */
	public static function render_form() {
		// Check rate limiting
		if ( ! self::check_rate_limit() ) {
			return '<div class="onoc-error">Too many requests. Please try again later.</div>';
		}
		
		ob_start();
		?>
		<div class="onoc-application-form">
			<form id="onoc-application-form" method="post">
				<?php wp_nonce_field( 'onoc_submit_request', 'onoc_nonce' ); ?>
				
				<div class="onoc-form-group">
					<label for="onoc-full-name"><?php esc_html_e( 'Full Name', 'office-noc-manager' ); ?> <span class="required">*</span></label>
					<input type="text" id="onoc-full-name" name="full_name" required>
				</div>
				
				<div class="onoc-form-group">
					<label for="onoc-employee-id"><?php esc_html_e( 'Employee ID', 'office-noc-manager' ); ?> <span class="required">*</span></label>
					<input type="text" id="onoc-employee-id" name="employee_id" required>
				</div>
				
				<div class="onoc-form-group">
					<label for="onoc-email"><?php esc_html_e( 'Email', 'office-noc-manager' ); ?> <span class="required">*</span></label>
					<input type="email" id="onoc-email" name="email" required>
				</div>
				
				<div class="onoc-form-row">
					<div class="onoc-form-group">
						<label for="onoc-joining-date"><?php esc_html_e( 'Joining Date', 'office-noc-manager' ); ?> <span class="required">*</span></label>
						<input type="date" id="onoc-joining-date" name="joining_date" required>
					</div>
					
					<div class="onoc-form-group">
						<label for="onoc-position"><?php esc_html_e( 'Position', 'office-noc-manager' ); ?> <span class="required">*</span></label>
						<input type="text" id="onoc-position" name="position" required>
					</div>
				</div>
				
				<div class="onoc-form-group">
					<label for="onoc-department"><?php esc_html_e( 'Department', 'office-noc-manager' ); ?> <span class="required">*</span></label>
					<input type="text" id="onoc-department" name="department" required>
				</div>
				
				<div class="onoc-form-group">
					<label for="onoc-visiting-country"><?php esc_html_e( 'Visiting Country', 'office-noc-manager' ); ?> <span class="required">*</span></label>
					<select id="onoc-visiting-country" name="visiting_country" required>
						<option value=""><?php esc_html_e( '-- Select Country --', 'office-noc-manager' ); ?></option>
						<?php
						$countries = require ONOC_PLUGIN_DIR . 'includes/country-names.php';
						foreach ( $countries as $code => $name ) {
							echo '<option value="' . esc_attr( $name ) . '">' . esc_html( $name ) . '</option>';
						}
						?>
					</select>
				</div>
				
				<div class="onoc-form-group">
					<label for="onoc-purpose"><?php esc_html_e( 'Purpose of Visit', 'office-noc-manager' ); ?> <span class="required">*</span></label>
					<textarea id="onoc-purpose" name="purpose" rows="4" required></textarea>
				</div>
				
				<div class="onoc-form-row">
					<div class="onoc-form-group">
						<label for="onoc-leave-start"><?php esc_html_e( 'Leave Start Date', 'office-noc-manager' ); ?> <span class="required">*</span></label>
						<input type="date" id="onoc-leave-start" name="leave_start" min="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>" required>
					</div>
					
					<div class="onoc-form-group">
						<label for="onoc-leave-end"><?php esc_html_e( 'Leave End Date', 'office-noc-manager' ); ?> <span class="required">*</span></label>
						<input type="date" id="onoc-leave-end" name="leave_end" min="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>" required>
					</div>
				</div>
				
				<div class="onoc-form-messages"></div>
				
				<button type="submit" class="onoc-submit-btn">
					<?php esc_html_e( 'Submit Request', 'office-noc-manager' ); ?>
				</button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}
	
	/**
	 * Handle form submission
	 */
	public static function handle_submission() {
		// Verify nonce
		if ( ! isset( $_POST['onoc_nonce'] ) || ! wp_verify_nonce( $_POST['onoc_nonce'], 'onoc_submit_request' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed.' ) );
		}
		
		// Check rate limiting
		if ( ! self::check_rate_limit() ) {
			wp_send_json_error( array( 'message' => 'Too many requests. Please try again later.' ) );
		}
		
		// Validate and sanitize input
		$data = array(
			'full_name'       => isset( $_POST['full_name'] ) ? sanitize_text_field( $_POST['full_name'] ) : '',
			'employee_id'     => isset( $_POST['employee_id'] ) ? sanitize_text_field( $_POST['employee_id'] ) : '',
			'email'           => isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '',
			'joining_date'    => isset( $_POST['joining_date'] ) ? sanitize_text_field( $_POST['joining_date'] ) : '',
			'position'        => isset( $_POST['position'] ) ? sanitize_text_field( $_POST['position'] ) : '',
			'department'      => isset( $_POST['department'] ) ? sanitize_text_field( $_POST['department'] ) : '',
			'visiting_country' => isset( $_POST['visiting_country'] ) ? sanitize_text_field( $_POST['visiting_country'] ) : '',
			'purpose'         => isset( $_POST['purpose'] ) ? sanitize_textarea_field( $_POST['purpose'] ) : '',
			'leave_start'     => isset( $_POST['leave_start'] ) ? sanitize_text_field( $_POST['leave_start'] ) : '',
			'leave_end'       => isset( $_POST['leave_end'] ) ? sanitize_text_field( $_POST['leave_end'] ) : '',
		);
		
		// Validate required fields
		$errors = array();
		if ( empty( $data['full_name'] ) ) {
			$errors[] = 'Full name is required.';
		}
		if ( empty( $data['employee_id'] ) ) {
			$errors[] = 'Employee ID is required.';
		}
		if ( empty( $data['email'] ) || ! is_email( $data['email'] ) ) {
			$errors[] = 'Valid email is required.';
		}
		if ( empty( $data['joining_date'] ) ) {
			$errors[] = 'Joining date is required.';
		}
		if ( empty( $data['position'] ) ) {
			$errors[] = 'Position is required.';
		}
		if ( empty( $data['department'] ) ) {
			$errors[] = 'Department is required.';
		}
		if ( empty( $data['visiting_country'] ) ) {
			$errors[] = 'Visiting country is required.';
		}
		if ( empty( $data['purpose'] ) ) {
			$errors[] = 'Purpose of visit is required.';
		}
		if ( empty( $data['leave_start'] ) ) {
			$errors[] = 'Leave start date is required.';
		}
		if ( empty( $data['leave_end'] ) ) {
			$errors[] = 'Leave end date is required.';
		}
		
		// Validate dates
		$today = date( 'Y-m-d' );
		
		if ( ! empty( $data['leave_start'] ) ) {
			if ( strtotime( $data['leave_start'] ) < strtotime( $today ) ) {
				$errors[] = 'Leave start date cannot be in the past.';
			}
		}
		
		if ( ! empty( $data['leave_end'] ) ) {
			if ( strtotime( $data['leave_end'] ) < strtotime( $today ) ) {
				$errors[] = 'Leave end date cannot be in the past.';
			}
		}
		
		if ( ! empty( $data['leave_start'] ) && ! empty( $data['leave_end'] ) ) {
			if ( strtotime( $data['leave_start'] ) > strtotime( $data['leave_end'] ) ) {
				$errors[] = 'Leave end date must be after start date.';
			}
		}
		
		if ( ! empty( $errors ) ) {
			wp_send_json_error( array( 'message' => implode( ' ', $errors ) ) );
		}
		
		// Save to database
		$db = new ONOC_DB();
		$result = $db->insert_request( $data );
		
		if ( ! $result['success'] ) {
			wp_send_json_error( array( 'message' => 'Failed to save request. Please try again.' ) );
		}
		
		// Record rate limit
		self::record_rate_limit();
		
		// Send confirmation email
		$email = new ONOC_Email();
		$email->send_submission_confirmation( $data['email'], $result['reference_id'] );
		
		wp_send_json_success( array(
			'message'      => 'Your NOC request has been submitted successfully. Reference ID: ' . $result['reference_id'],
			'reference_id' => $result['reference_id'],
		) );
	}
	
	/**
	 * Check rate limiting
	 */
	private static function check_rate_limit() {
		$ip = self::get_client_ip();
		$key = 'onoc_rate_limit_' . md5( $ip );
		$limit = get_transient( $key );
		
		if ( $limit === false ) {
			return true;
		}
		
		// Allow max 3 submissions per hour
		return (int) $limit < 3;
	}
	
	/**
	 * Record rate limit
	 */
	private static function record_rate_limit() {
		$ip = self::get_client_ip();
		$key = 'onoc_rate_limit_' . md5( $ip );
		$current = get_transient( $key );
		
		if ( $current === false ) {
			set_transient( $key, 1, HOUR_IN_SECONDS );
		} else {
			set_transient( $key, (int) $current + 1, HOUR_IN_SECONDS );
		}
	}
	
	/**
	 * Get client IP address
	 */
	private static function get_client_ip() {
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_REAL_IP',
			'HTTP_X_FORWARDED_FOR',
			'REMOTE_ADDR',
		);
		
		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = $_SERVER[ $key ];
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = explode( ',', $ip )[0];
				}
				return trim( $ip );
			}
		}
		
		return '0.0.0.0';
	}
}

