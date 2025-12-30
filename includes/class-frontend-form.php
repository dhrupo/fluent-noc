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
		// if ( ! self::check_rate_limit() ) {
		// 	return '<div class="onoc-error">Too many requests. Please try again later.</div>';
		// }
		
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
						<input type="date" id="onoc-joining-date" name="joining_date" max="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>" required>
						<span class="onoc-field-error" id="onoc-joining-date-error" style="display: none; color: #d63638; font-size: 12px; margin-top: 5px;"></span>
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
		// if ( ! self::check_rate_limit() ) {
		// 	wp_send_json_error( array( 'message' => 'Too many requests. Please try again later.' ) );
		// }
		
		// Get today's date for validation
		$today = date( 'Y-m-d' );
		
		// Validate and sanitize input
		$data = array(
			'full_name'       => isset( $_POST['full_name'] ) ? trim( sanitize_text_field( $_POST['full_name'] ) ) : '',
			'employee_id'     => isset( $_POST['employee_id'] ) ? trim( sanitize_text_field( $_POST['employee_id'] ) ) : '',
			'email'           => isset( $_POST['email'] ) ? trim( sanitize_email( $_POST['email'] ) ) : '',
			'joining_date'    => isset( $_POST['joining_date'] ) ? trim( sanitize_text_field( $_POST['joining_date'] ) ) : '',
			'position'        => isset( $_POST['position'] ) ? trim( sanitize_text_field( $_POST['position'] ) ) : '',
			'department'      => isset( $_POST['department'] ) ? trim( sanitize_text_field( $_POST['department'] ) ) : '',
			'visiting_country' => isset( $_POST['visiting_country'] ) ? trim( sanitize_text_field( $_POST['visiting_country'] ) ) : '',
			'purpose'         => isset( $_POST['purpose'] ) ? trim( sanitize_textarea_field( $_POST['purpose'] ) ) : '',
			'leave_start'     => isset( $_POST['leave_start'] ) ? trim( sanitize_text_field( $_POST['leave_start'] ) ) : '',
			'leave_end'       => isset( $_POST['leave_end'] ) ? trim( sanitize_text_field( $_POST['leave_end'] ) ) : '',
		);
		
		// Validate required fields
		$errors = array();
		
		// Full Name validation
		if ( empty( $data['full_name'] ) ) {
			$errors[] = __( 'Full name is required.', 'office-noc-manager' );
		} elseif ( strlen( $data['full_name'] ) < 2 ) {
			$errors[] = __( 'Full name must be at least 2 characters long.', 'office-noc-manager' );
		}
		
		// Employee ID validation
		if ( empty( $data['employee_id'] ) ) {
			$errors[] = __( 'Employee ID is required.', 'office-noc-manager' );
		} elseif ( strlen( $data['employee_id'] ) < 1 ) {
			$errors[] = __( 'Employee ID cannot be empty.', 'office-noc-manager' );
		}
		
		// Email validation
		if ( empty( $data['email'] ) ) {
			$errors[] = __( 'Email is required.', 'office-noc-manager' );
		} elseif ( ! is_email( $data['email'] ) ) {
			$errors[] = __( 'Please enter a valid email address.', 'office-noc-manager' );
		}
		
		// Joining Date validation
		if ( empty( $data['joining_date'] ) ) {
			$errors[] = __( 'Joining date is required.', 'office-noc-manager' );
		} else {
			// Validate date format (YYYY-MM-DD)
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $data['joining_date'] ) ) {
				$errors[] = __( 'Invalid joining date format.', 'office-noc-manager' );
			} elseif ( strtotime( $data['joining_date'] ) === false ) {
				$errors[] = __( 'Invalid joining date.', 'office-noc-manager' );
			} elseif ( strtotime( $data['joining_date'] ) > strtotime( $today ) ) {
				$errors[] = __( 'Joining date cannot be in the future.', 'office-noc-manager' );
			}
		}
		
		// Position validation
		if ( empty( $data['position'] ) ) {
			$errors[] = __( 'Position is required.', 'office-noc-manager' );
		} elseif ( strlen( $data['position'] ) < 2 ) {
			$errors[] = __( 'Position must be at least 2 characters long.', 'office-noc-manager' );
		}
		
		// Department validation
		if ( empty( $data['department'] ) ) {
			$errors[] = __( 'Department is required.', 'office-noc-manager' );
		} elseif ( strlen( $data['department'] ) < 2 ) {
			$errors[] = __( 'Department must be at least 2 characters long.', 'office-noc-manager' );
		}
		
		// Visiting Country validation
		if ( empty( $data['visiting_country'] ) ) {
			$errors[] = __( 'Visiting country is required.', 'office-noc-manager' );
		}
		
		// Purpose validation
		if ( empty( $data['purpose'] ) ) {
			$errors[] = __( 'Purpose of visit is required.', 'office-noc-manager' );
		} elseif ( strlen( $data['purpose'] ) < 10 ) {
			$errors[] = __( 'Purpose of visit must be at least 10 characters long.', 'office-noc-manager' );
		}
		
		// Leave Start Date validation
		if ( empty( $data['leave_start'] ) ) {
			$errors[] = __( 'Leave start date is required.', 'office-noc-manager' );
		} else {
			// Validate date format (YYYY-MM-DD)
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $data['leave_start'] ) ) {
				$errors[] = __( 'Invalid leave start date format.', 'office-noc-manager' );
			} elseif ( strtotime( $data['leave_start'] ) === false ) {
				$errors[] = __( 'Invalid leave start date.', 'office-noc-manager' );
			} elseif ( strtotime( $data['leave_start'] ) < strtotime( $today ) ) {
				$errors[] = __( 'Leave start date cannot be in the past.', 'office-noc-manager' );
			}
		}
		
		// Leave End Date validation
		if ( empty( $data['leave_end'] ) ) {
			$errors[] = __( 'Leave end date is required.', 'office-noc-manager' );
		} else {
			// Validate date format (YYYY-MM-DD)
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $data['leave_end'] ) ) {
				$errors[] = __( 'Invalid leave end date format.', 'office-noc-manager' );
			} elseif ( strtotime( $data['leave_end'] ) === false ) {
				$errors[] = __( 'Invalid leave end date.', 'office-noc-manager' );
			} elseif ( strtotime( $data['leave_end'] ) < strtotime( $today ) ) {
				$errors[] = __( 'Leave end date cannot be in the past.', 'office-noc-manager' );
			}
		}
		
		// Validate date relationships
		if ( ! empty( $data['leave_start'] ) && ! empty( $data['leave_end'] ) ) {
			if ( strtotime( $data['leave_start'] ) !== false && strtotime( $data['leave_end'] ) !== false ) {
				if ( strtotime( $data['leave_start'] ) > strtotime( $data['leave_end'] ) ) {
					$errors[] = __( 'Leave end date must be after start date.', 'office-noc-manager' );
				}
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
		$email->send_submission_confirmation( $data['email'], $result['reference_id'], $data['full_name'] );
		
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
			if ( ! empty( $_SERVER[ $key ] ) && is_string( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( $_SERVER[ $key ] );
				if ( strpos( $ip, ',' ) !== false ) {
					$ip_parts = explode( ',', $ip );
					$ip = isset( $ip_parts[0] ) ? $ip_parts[0] : $ip;
				}
				return trim( $ip );
			}
		}
		
		return '0.0.0.0';
	}
}

