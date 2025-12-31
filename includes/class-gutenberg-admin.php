<?php
/**
 * Gutenberg block-based admin interface
 *
 * @package Office_NOC_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ONOC_Gutenberg_Admin {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_blocks' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_block_assets' ) );
		add_action( 'wp_ajax_onoc_remove_image', array( $this, 'ajax_remove_image' ) );
	}
	
	/**
	 * Register custom blocks
	 */
	public function register_blocks() {
		// Request List Block
		register_block_type( 'onoc/request-list', array(
			'render_callback' => array( $this, 'render_request_list' ),
			'attributes'      => array(
				'status' => array(
					'type'    => 'string',
					'default' => 'pending',
				),
				'perPage' => array(
					'type'    => 'number',
					'default' => 20,
				),
			),
		) );
		
		// Request Review Block
		register_block_type( 'onoc/request-review', array(
			'render_callback' => array( $this, 'render_request_review' ),
			'attributes'      => array(
				'requestId' => array(
					'type' => 'number',
				),
			),
		) );
		
		// Settings Block
		register_block_type( 'onoc/settings', array(
			'render_callback' => array( $this, 'render_settings' ),
		) );
		
		// PDF Template Designer Block
		register_block_type( 'onoc/pdf-template-designer', array(
			'render_callback' => array( $this, 'render_template_designer' ),
		) );
	}
	
	/**
	 * Enqueue block assets
	 */
	public function enqueue_block_assets( $hook ) {
		if ( strpos( $hook, 'office-noc' ) === false ) {
			return;
		}
		
		// Enqueue Gutenberg editor
		wp_enqueue_script( 'wp-blocks' );
		wp_enqueue_script( 'wp-element' );
		// wp-editor is deprecated, use wp-block-editor for WordPress 5.8+
		if ( function_exists( 'wp_enqueue_script' ) ) {
			// Check WordPress version for compatibility
			global $wp_version;
			if ( version_compare( $wp_version, '5.8', '>=' ) ) {
				wp_enqueue_script( 'wp-block-editor' );
			} else {
				wp_enqueue_script( 'wp-editor' );
			}
		}
		wp_enqueue_script( 'wp-components' );
		wp_enqueue_script( 'wp-data' );
		wp_enqueue_script( 'wp-api-fetch' );
		
		wp_enqueue_style( 'wp-edit-blocks' );
		wp_enqueue_style( 'wp-components' );
		
		// Enqueue our block scripts if they exist
		if ( file_exists( ONOC_PLUGIN_DIR . 'assets/js/build/index.js' ) ) {
			// Build dependencies array based on WordPress version
			global $wp_version;
			$dependencies = array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch' );
			if ( version_compare( $wp_version, '5.8', '>=' ) ) {
				$dependencies[] = 'wp-block-editor';
			} else {
				$dependencies[] = 'wp-editor';
			}
			
			wp_enqueue_script(
				'onoc-blocks',
				ONOC_PLUGIN_URL . 'assets/js/build/index.js',
				$dependencies,
				ONOC_VERSION,
				true
			);
		}
	}
	
	/**
	 * Render request list block
	 */
	public function render_request_list( $attributes ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return '<p>' . esc_html__( 'You do not have permission to view this.', 'office-noc-manager' ) . '</p>';
		}
		
		// Get filter parameters
		$status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
		$search = isset( $_GET['search'] ) ? sanitize_text_field( $_GET['search'] ) : '';
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : '';
		$date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( $_GET['date_to'] ) : '';
		$per_page = isset( $attributes['perPage'] ) ? (int) $attributes['perPage'] : 20;
		$page = isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1;
		
		$db = new ONOC_DB();
		$result = $db->get_requests_filtered( array(
			'status'    => $status,
			'search'    => $search,
			'date_from' => $date_from,
			'date_to'   => $date_to,
			'page'      => $page,
			'per_page'  => $per_page,
		) );
		
		ob_start();
		?>
		<div class="onoc-request-list">
			<!-- Filters -->
			<div class="onoc-filters" style="background: #fff; padding: 15px; margin-bottom: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
				<form method="get" action="" id="onoc-filter-form" style="display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;">
					<input type="hidden" name="page" value="office-noc">
					
					<!-- Status Filter -->
					<div style="flex: 1; min-width: 150px;">
						<label for="onoc-status-filter" style="display: block; margin-bottom: 5px; font-weight: 600;">
							<?php esc_html_e( 'Status', 'office-noc-manager' ); ?>
						</label>
						<select id="onoc-status-filter" name="status" style="width: 100%; padding: 5px;">
							<option value=""><?php esc_html_e( 'All', 'office-noc-manager' ); ?></option>
							<option value="pending" <?php selected( $status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'office-noc-manager' ); ?></option>
							<option value="approved" <?php selected( $status, 'approved' ); ?>><?php esc_html_e( 'Approved', 'office-noc-manager' ); ?></option>
							<option value="rejected" <?php selected( $status, 'rejected' ); ?>><?php esc_html_e( 'Rejected', 'office-noc-manager' ); ?></option>
						</select>
					</div>
					
					<!-- Search Filter -->
					<div style="flex: 1; min-width: 200px;">
						<label for="onoc-search-filter" style="display: block; margin-bottom: 5px; font-weight: 600;">
							<?php esc_html_e( 'Search (Name/Email)', 'office-noc-manager' ); ?>
						</label>
						<input type="text" id="onoc-search-filter" name="search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search by name or email...', 'office-noc-manager' ); ?>" style="width: 100%; padding: 5px;">
					</div>
					
					<!-- Date From -->
					<div style="flex: 1; min-width: 150px;">
						<label for="onoc-date-from" style="display: block; margin-bottom: 5px; font-weight: 600;">
							<?php esc_html_e( 'From Date', 'office-noc-manager' ); ?>
						</label>
						<input type="date" id="onoc-date-from" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" style="width: 100%; padding: 5px;">
					</div>
					
					<!-- Date To -->
					<div style="flex: 1; min-width: 150px;">
						<label for="onoc-date-to" style="display: block; margin-bottom: 5px; font-weight: 600;">
							<?php esc_html_e( 'To Date', 'office-noc-manager' ); ?>
						</label>
						<input type="date" id="onoc-date-to" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" style="width: 100%; padding: 5px;">
					</div>
					
					<!-- Filter Buttons -->
					<div style="display: flex; gap: 10px;">
						<button type="submit" class="button button-primary">
							<?php esc_html_e( 'Filter', 'office-noc-manager' ); ?>
						</button>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=office-noc' ) ); ?>" class="button">
							<?php esc_html_e( 'Reset', 'office-noc-manager' ); ?>
						</a>
					</div>
				</form>
			</div>
			
			<!-- Results Count -->
			<?php if ( ! empty( $result['requests'] ) || ! empty( $status ) || ! empty( $search ) || ! empty( $date_from ) || ! empty( $date_to ) ) : ?>
				<p style="margin-bottom: 15px;">
					<strong><?php echo esc_html( $result['total'] ); ?></strong> 
					<?php esc_html_e( 'request(s) found', 'office-noc-manager' ); ?>
				</p>
			<?php endif; ?>
			
			<?php if ( empty( $result['requests'] ) ) : ?>
				<p><?php esc_html_e( 'No requests found.', 'office-noc-manager' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Reference ID', 'office-noc-manager' ); ?></th>
							<th><?php esc_html_e( 'Name', 'office-noc-manager' ); ?></th>
							<th><?php esc_html_e( 'Email', 'office-noc-manager' ); ?></th>
							<th><?php esc_html_e( 'Country', 'office-noc-manager' ); ?></th>
							<th><?php esc_html_e( 'Leave Period', 'office-noc-manager' ); ?></th>
							<th><?php esc_html_e( 'Status', 'office-noc-manager' ); ?></th>
							<th><?php esc_html_e( 'Submitted', 'office-noc-manager' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'office-noc-manager' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $result['requests'] as $request ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $request['reference_id'] ); ?></strong></td>
								<td>
									<?php echo esc_html( $request['full_name'] ); ?>
									<?php if ( ! empty( $request['employee_id'] ) ) : ?>
										<br/><small style="color: #666;">ID: <?php echo esc_html( $request['employee_id'] ); ?></small>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $request['email'] ); ?></td>
								<td><?php echo esc_html( $request['visiting_country'] ); ?></td>
								<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $request['leave_start'] ) ) ); ?> - <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $request['leave_end'] ) ) ); ?></td>
								<td>
									<?php
									$status_class = '';
									$status_text = '';
									switch ( $request['status'] ) {
										case 'approved':
											$status_class = 'status-approved';
											$status_text = __( 'Approved', 'office-noc-manager' );
											break;
										case 'rejected':
											$status_class = 'status-rejected';
											$status_text = __( 'Rejected', 'office-noc-manager' );
											break;
										default:
											$status_class = 'status-pending';
											$status_text = __( 'Pending', 'office-noc-manager' );
									}
									?>
									<span class="onoc-status-badge <?php echo esc_attr( $status_class ); ?>" style="padding: 4px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; text-transform: uppercase;">
										<?php echo esc_html( $status_text ); ?>
									</span>
								</td>
								<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $request['created_at'] ) ) ); ?></td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=office-noc&action=review&id=' . $request['id'] ) ); ?>" class="button button-small">
										<?php esc_html_e( 'Review', 'office-noc-manager' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				
				<?php if ( $result['pages'] > 1 ) : ?>
					<div class="onoc-pagination" style="margin-top: 20px;">
						<?php
						$pagination_args = array(
							'base'    => add_query_arg( 'paged', '%#%' ),
							'format'  => '',
							'current' => $page,
							'total'   => $result['pages'],
						);
						
						// Preserve filter parameters in pagination
						if ( ! empty( $status ) ) {
							$pagination_args['add_args'] = array( 'status' => $status );
						}
						if ( ! empty( $search ) ) {
							$pagination_args['add_args']['search'] = $search;
						}
						if ( ! empty( $date_from ) ) {
							$pagination_args['add_args']['date_from'] = $date_from;
						}
						if ( ! empty( $date_to ) ) {
							$pagination_args['add_args']['date_to'] = $date_to;
						}
						
						echo paginate_links( $pagination_args );
						?>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
	
	/**
	 * Render request review block
	 */
	public function render_request_review( $attributes ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return '<p>' . esc_html__( 'You do not have permission to view this.', 'office-noc-manager' ) . '</p>';
		}
		
		$request_id = isset( $attributes['requestId'] ) ? (int) $attributes['requestId'] : 0;
		
		if ( ! $request_id ) {
			$request_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		}
		
		if ( ! $request_id ) {
			return '<p>' . esc_html__( 'No request ID provided.', 'office-noc-manager' ) . '</p>';
		}
		
		$db = new ONOC_DB();
		$request = $db->get_request( $request_id );
		
		if ( ! $request ) {
			return '<p>' . esc_html__( 'Request not found.', 'office-noc-manager' ) . '</p>';
		}
		
		ob_start();
		?>
		<div class="onoc-request-review" data-request-id="<?php echo esc_attr( $request_id ); ?>">
			<div class="onoc-review-details">
				<h2><?php esc_html_e( 'Request Details', 'office-noc-manager' ); ?></h2>
				
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Reference ID', 'office-noc-manager' ); ?></th>
						<td><strong><?php echo esc_html( $request['reference_id'] ); ?></strong></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Full Name', 'office-noc-manager' ); ?></th>
						<td><?php echo esc_html( $request['full_name'] ); ?></td>
					</tr>
					<?php if ( ! empty( $request['employee_id'] ) ) : ?>
					<tr>
						<th><?php esc_html_e( 'Employee ID', 'office-noc-manager' ); ?></th>
						<td><?php echo esc_html( $request['employee_id'] ); ?></td>
					</tr>
					<?php endif; ?>
					<?php if ( ! empty( $request['joining_date'] ) ) : ?>
					<tr>
						<th><?php esc_html_e( 'Joining Date', 'office-noc-manager' ); ?></th>
						<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $request['joining_date'] ) ) ); ?></td>
					</tr>
					<?php endif; ?>
					<?php if ( ! empty( $request['position'] ) ) : ?>
					<tr>
						<th><?php esc_html_e( 'Position', 'office-noc-manager' ); ?></th>
						<td><?php echo esc_html( $request['position'] ); ?></td>
					</tr>
					<?php endif; ?>
					<?php if ( ! empty( $request['department'] ) ) : ?>
					<tr>
						<th><?php esc_html_e( 'Department', 'office-noc-manager' ); ?></th>
						<td><?php echo esc_html( $request['department'] ); ?></td>
					</tr>
					<?php endif; ?>
					<tr>
						<th><?php esc_html_e( 'Email', 'office-noc-manager' ); ?></th>
						<td><?php echo esc_html( $request['email'] ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Visiting Country', 'office-noc-manager' ); ?></th>
						<td><?php echo esc_html( $request['visiting_country'] ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Purpose', 'office-noc-manager' ); ?></th>
						<td><?php echo esc_html( $request['purpose'] ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Leave Start Date', 'office-noc-manager' ); ?></th>
						<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $request['leave_start'] ) ) ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Leave End Date', 'office-noc-manager' ); ?></th>
						<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $request['leave_end'] ) ) ); ?></td>
					</tr>
					<?php if ( ! empty( $request['leave_start'] ) && ! empty( $request['leave_end'] ) ) : ?>
						<?php
						$start_date = new DateTime( $request['leave_start'] );
						$end_date = new DateTime( $request['leave_end'] );
						$interval = $start_date->diff( $end_date );
						$total_days = $interval->days + 1; // Include both start and end days
						?>
						<tr>
							<th><?php esc_html_e( 'Total Leave Days', 'office-noc-manager' ); ?></th>
							<td><strong><?php echo esc_html( $total_days ); ?></strong> <?php echo esc_html( _n( 'day', 'days', $total_days, 'office-noc-manager' ) ); ?></td>
						</tr>
					<?php endif; ?>
					<tr>
						<th><?php esc_html_e( 'Status', 'office-noc-manager' ); ?></th>
						<td>
							<span class="onoc-status onoc-status-<?php echo esc_attr( $request['status'] ); ?>">
								<?php echo esc_html( ucfirst( $request['status'] ) ); ?>
							</span>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Submitted', 'office-noc-manager' ); ?></th>
						<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $request['created_at'] ) ) ); ?></td>
					</tr>
				</table>
				
				<?php if ( $request['status'] === 'pending' ) : ?>
					<div class="onoc-review-actions">
						<h3><?php esc_html_e( 'HR Note', 'office-noc-manager' ); ?> <span class="required" style="color: #d63638;">*</span></h3>
						<p class="description"><?php esc_html_e( 'Please provide a note that will be saved with the approval or rejection.', 'office-noc-manager' ); ?></p>
						<textarea id="onoc-hr-note" name="onoc-hr-note" rows="4" style="width: 100%;" required placeholder="<?php esc_attr_e( 'Enter approval note or rejection reason...', 'office-noc-manager' ); ?>"></textarea>
						
						<div class="onoc-action-buttons">
							<button type="button" class="button button-primary onoc-approve-btn" data-request-id="<?php echo esc_attr( $request_id ); ?>">
								<?php esc_html_e( 'Approve', 'office-noc-manager' ); ?>
							</button>
							<button type="button" class="button button-secondary onoc-reject-btn" data-request-id="<?php echo esc_attr( $request_id ); ?>">
								<?php esc_html_e( 'Reject', 'office-noc-manager' ); ?>
							</button>
						</div>
					</div>
				<?php elseif ( $request['status'] === 'approved' ) : ?>
					<div class="onoc-approved-info">
						<?php if ( ! empty( $request['hr_note'] ) ) : ?>
							<p><strong><?php esc_html_e( 'Approval Note:', 'office-noc-manager' ); ?></strong></p>
							<p><?php echo esc_html( $request['hr_note'] ); ?></p>
						<?php endif; ?>
						<?php if ( ! empty( $request['pdf_url'] ) ) : ?>
							<p><a href="<?php echo esc_url( $request['pdf_url'] ); ?>" class="button" target="_blank"><?php esc_html_e( 'View PDF', 'office-noc-manager' ); ?></a></p>
						<?php endif; ?>
					</div>
				<?php elseif ( $request['status'] === 'rejected' && ! empty( $request['hr_note'] ) ) : ?>
					<div class="onoc-rejected-info">
						<p><strong><?php esc_html_e( 'Rejection Reason:', 'office-noc-manager' ); ?></strong></p>
						<p><?php echo esc_html( $request['hr_note'] ); ?></p>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
	
	/**
	 * Render settings block
	 */
	public function render_settings( $attributes ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return '<p>' . esc_html__( 'You do not have permission to view this.', 'office-noc-manager' ) . '</p>';
		}
		
		// Handle form submission
		if ( isset( $_POST['onoc_save_settings'] ) && wp_verify_nonce( $_POST['onoc_settings_nonce'], 'onoc_save_settings' ) ) {
			update_option( 'onoc_company_name', sanitize_text_field( $_POST['company_name'] ) );
			update_option( 'onoc_company_address', sanitize_textarea_field( $_POST['company_address'] ) );
			update_option( 'onoc_company_phone', sanitize_text_field( $_POST['company_phone'] ) );
			update_option( 'onoc_company_email', sanitize_email( $_POST['company_email'] ) );
			update_option( 'onoc_hr_name', sanitize_text_field( $_POST['hr_name'] ) );
			update_option( 'onoc_hr_title', sanitize_text_field( $_POST['hr_title'] ) );
			update_option( 'onoc_email_from_name', sanitize_text_field( $_POST['email_from_name'] ) );
			update_option( 'onoc_email_from_address', sanitize_email( $_POST['email_from_address'] ) );
			
			// Handle file uploads
			if ( ! function_exists( 'wp_handle_upload' ) ) {
				require_once( ABSPATH . 'wp-admin/includes/file.php' );
			}
			
			// Handle signature upload
			if ( ! empty( $_FILES['signature_image_file']['name'] ) ) {
				$uploadedfile = $_FILES['signature_image_file'];
				
				// Validate file type - only JPG/JPEG allowed
				$file_type = wp_check_filetype( $uploadedfile['name'] );
				$allowed_types = array( 'jpg', 'jpeg' );
				
				if ( ! in_array( strtolower( $file_type['ext'] ), $allowed_types, true ) ) {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'HR Signature Image must be a JPG/JPEG file. PNG and other formats are not supported.', 'office-noc-manager' ) . '</p></div>';
				} else {
					$upload_overrides = array( 'test_form' => false );
					$movefile = wp_handle_upload( $uploadedfile, $upload_overrides );
					if ( $movefile && ! isset( $movefile['error'] ) ) {
						update_option( 'onoc_signature_image', $movefile['url'] );
						// Also store file path for easier access
						if ( isset( $movefile['file'] ) ) {
							update_option( 'onoc_signature_image_path', $movefile['file'] );
						}
					} elseif ( isset( $movefile['error'] ) ) {
						echo '<div class="notice notice-error"><p>' . esc_html( $movefile['error'] ) . '</p></div>';
					}
				}
			}
			
			// Handle PDF header upload
			if ( ! empty( $_FILES['pdf_header_file']['name'] ) ) {
				$uploadedfile = $_FILES['pdf_header_file'];
				
				// Validate file type - only JPG/JPEG allowed
				$file_type = wp_check_filetype( $uploadedfile['name'] );
				$allowed_types = array( 'jpg', 'jpeg' );
				
				if ( ! in_array( strtolower( $file_type['ext'] ), $allowed_types, true ) ) {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'PDF Header Image must be a JPG/JPEG file. PNG and other formats are not supported.', 'office-noc-manager' ) . '</p></div>';
				} else {
					$upload_overrides = array( 'test_form' => false );
					$movefile = wp_handle_upload( $uploadedfile, $upload_overrides );
					if ( $movefile && ! isset( $movefile['error'] ) ) {
						update_option( 'onoc_pdf_header', $movefile['url'] );
						// Also store file path for easier access
						if ( isset( $movefile['file'] ) ) {
							update_option( 'onoc_pdf_header_path', $movefile['file'] );
						}
					} elseif ( isset( $movefile['error'] ) ) {
						echo '<div class="notice notice-error"><p>' . esc_html( $movefile['error'] ) . '</p></div>';
					}
				}
			}
			
			// Handle PDF footer upload
			if ( ! empty( $_FILES['pdf_footer_file']['name'] ) ) {
				$uploadedfile = $_FILES['pdf_footer_file'];
				
				// Validate file type - only JPG/JPEG allowed
				$file_type = wp_check_filetype( $uploadedfile['name'] );
				$allowed_types = array( 'jpg', 'jpeg' );
				
				if ( ! in_array( strtolower( $file_type['ext'] ), $allowed_types, true ) ) {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'PDF Footer Image must be a JPG/JPEG file. PNG and other formats are not supported.', 'office-noc-manager' ) . '</p></div>';
				} else {
					$upload_overrides = array( 'test_form' => false );
					$movefile = wp_handle_upload( $uploadedfile, $upload_overrides );
					if ( $movefile && ! isset( $movefile['error'] ) ) {
						update_option( 'onoc_pdf_footer', $movefile['url'] );
						// Also store file path for easier access
						if ( isset( $movefile['file'] ) ) {
							update_option( 'onoc_pdf_footer_path', $movefile['file'] );
						}
					} elseif ( isset( $movefile['error'] ) ) {
						echo '<div class="notice notice-error"><p>' . esc_html( $movefile['error'] ) . '</p></div>';
					}
				}
			}
			
			// Also allow URL input for signature (for backward compatibility)
			if ( ! empty( $_POST['signature_image'] ) && empty( $_FILES['signature_image_file']['name'] ) ) {
				update_option( 'onoc_signature_image', esc_url_raw( $_POST['signature_image'] ) );
			}
			
			// Also allow URL input for header/footer
			if ( ! empty( $_POST['pdf_header'] ) && empty( $_FILES['pdf_header_file']['name'] ) ) {
				update_option( 'onoc_pdf_header', esc_url_raw( $_POST['pdf_header'] ) );
			}
			if ( ! empty( $_POST['pdf_footer'] ) && empty( $_FILES['pdf_footer_file']['name'] ) ) {
				update_option( 'onoc_pdf_footer', esc_url_raw( $_POST['pdf_footer'] ) );
			}
			
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved successfully.', 'office-noc-manager' ) . '</p></div>';
		}
		
		ob_start();
		?>
		<div class="onoc-settings">
			<?php if ( isset( $_GET['removed'] ) && $_GET['removed'] == '1' ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Image removed successfully.', 'office-noc-manager' ); ?></p>
				</div>
			<?php endif; ?>
			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'onoc_save_settings', 'onoc_settings_nonce' ); ?>
				
				<table class="form-table">
					<tr>
						<th><label for="company_name"><?php esc_html_e( 'Company Name', 'office-noc-manager' ); ?></label></th>
						<td>
							<input type="text" id="company_name" name="company_name" value="<?php echo esc_attr( get_option( 'onoc_company_name', '' ) ); ?>" class="regular-text" />
						</td>
					</tr>
					<tr>
						<th><label for="company_address"><?php esc_html_e( 'Company Address', 'office-noc-manager' ); ?></label></th>
						<td>
							<textarea id="company_address" name="company_address" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'onoc_company_address', '' ) ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th><label for="company_phone"><?php esc_html_e( 'Company Phone', 'office-noc-manager' ); ?></label></th>
						<td>
							<input type="text" id="company_phone" name="company_phone" value="<?php echo esc_attr( get_option( 'onoc_company_phone', '' ) ); ?>" class="regular-text" />
						</td>
					</tr>
					<tr>
						<th><label for="company_email"><?php esc_html_e( 'Company Email', 'office-noc-manager' ); ?></label></th>
						<td>
							<input type="email" id="company_email" name="company_email" value="<?php echo esc_attr( get_option( 'onoc_company_email', '' ) ); ?>" class="regular-text" />
						</td>
					</tr>
					<tr>
						<th><label for="hr_name"><?php esc_html_e( 'HR Manager Name', 'office-noc-manager' ); ?></label></th>
						<td>
							<input type="text" id="hr_name" name="hr_name" value="<?php echo esc_attr( get_option( 'onoc_hr_name', '' ) ); ?>" class="regular-text" />
						</td>
					</tr>
					<tr>
						<th><label for="hr_title"><?php esc_html_e( 'HR Manager Title', 'office-noc-manager' ); ?></label></th>
						<td>
							<input type="text" id="hr_title" name="hr_title" value="<?php echo esc_attr( get_option( 'onoc_hr_title', '' ) ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'e.g., Human Resource Manager', 'office-noc-manager' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="signature_image_file"><?php esc_html_e( 'HR Signature Image', 'office-noc-manager' ); ?></label></th>
						<td>
							<input type="file" id="signature_image_file" name="signature_image_file" accept="image/jpeg,image/jpg,.jpg,.jpeg" />
							<p id="onoc-signature-image-container" style="margin-top: 10px;">
								<?php if ( get_option( 'onoc_signature_image' ) ) : ?>
									<img src="<?php echo esc_url( get_option( 'onoc_signature_image' ) ); ?>" style="max-width: 200px; max-height: 100px; display: block; margin-bottom: 10px;" />
									<button type="button" class="button button-secondary onoc-remove-image-btn" data-image-type="signature"><?php esc_html_e( 'Remove Image', 'office-noc-manager' ); ?></button>
								<?php endif; ?>
							</p>
							<p class="description"><?php esc_html_e( 'Upload signature image file (JPG/JPEG only - PNG not supported)', 'office-noc-manager' ); ?></p>
							<p class="description"><?php esc_html_e( 'Or enter URL:', 'office-noc-manager' ); ?></p>
							<input type="url" id="signature_image" name="signature_image" value="<?php echo esc_url( get_option( 'onoc_signature_image', '' ) ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Or paste image URL here', 'office-noc-manager' ); ?>" />
						</td>
					</tr>
					<tr>
						<th><label for="pdf_header_file"><?php esc_html_e( 'PDF Header Image', 'office-noc-manager' ); ?></label></th>
						<td>
							<input type="file" id="pdf_header_file" name="pdf_header_file" accept="image/jpeg,image/jpg,.jpg,.jpeg" />
							<p id="onoc-header-image-container" style="margin-top: 10px;">
								<?php if ( get_option( 'onoc_pdf_header' ) ) : ?>
									<img src="<?php echo esc_url( get_option( 'onoc_pdf_header' ) ); ?>" style="max-width: 100%; max-height: 150px; display: block; margin-bottom: 10px;" />
									<button type="button" class="button button-secondary onoc-remove-image-btn" data-image-type="header"><?php esc_html_e( 'Remove Image', 'office-noc-manager' ); ?></button>
								<?php endif; ?>
							</p>
							<p class="description"><?php esc_html_e( 'Upload office letterhead/header image for PDF (JPG/JPEG only - PNG not supported)', 'office-noc-manager' ); ?></p>
							<p class="description"><?php esc_html_e( 'Or enter URL:', 'office-noc-manager' ); ?></p>
							<input type="url" id="pdf_header" name="pdf_header" value="<?php echo esc_url( get_option( 'onoc_pdf_header', '' ) ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Or paste image URL here', 'office-noc-manager' ); ?>" />
						</td>
					</tr>
					<tr>
						<th><label for="pdf_footer_file"><?php esc_html_e( 'PDF Footer Image', 'office-noc-manager' ); ?></label></th>
						<td>
							<input type="file" id="pdf_footer_file" name="pdf_footer_file" accept="image/jpeg,image/jpg,.jpg,.jpeg" />
							<p id="onoc-footer-image-container" style="margin-top: 10px;">
								<?php if ( get_option( 'onoc_pdf_footer' ) ) : ?>
									<img src="<?php echo esc_url( get_option( 'onoc_pdf_footer' ) ); ?>" style="max-width: 100%; max-height: 100px; display: block; margin-bottom: 10px;" />
									<button type="button" class="button button-secondary onoc-remove-image-btn" data-image-type="footer"><?php esc_html_e( 'Remove Image', 'office-noc-manager' ); ?></button>
								<?php endif; ?>
							</p>
							<p class="description"><?php esc_html_e( 'Upload office footer image for PDF (JPG/JPEG only - PNG not supported)', 'office-noc-manager' ); ?></p>
							<p class="description"><?php esc_html_e( 'Or enter URL:', 'office-noc-manager' ); ?></p>
							<input type="url" id="pdf_footer" name="pdf_footer" value="<?php echo esc_url( get_option( 'onoc_pdf_footer', '' ) ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Or paste image URL here', 'office-noc-manager' ); ?>" />
						</td>
					</tr>
					<tr>
						<th><label for="email_from_name"><?php esc_html_e( 'Email From Name', 'office-noc-manager' ); ?></label></th>
						<td>
							<input type="text" id="email_from_name" name="email_from_name" value="<?php echo esc_attr( get_option( 'onoc_email_from_name', get_bloginfo( 'name' ) ) ); ?>" class="regular-text" />
						</td>
					</tr>
					<tr>
						<th><label for="email_from_address"><?php esc_html_e( 'Email From Address', 'office-noc-manager' ); ?></label></th>
						<td>
							<input type="email" id="email_from_address" name="email_from_address" value="<?php echo esc_attr( get_option( 'onoc_email_from_address', get_option( 'admin_email' ) ) ); ?>" class="regular-text" />
						</td>
					</tr>
				</table>
				
				<?php submit_button( __( 'Save Settings', 'office-noc-manager' ), 'primary', 'onoc_save_settings' ); ?>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}
	
	/**
	 * Render template designer block
	 */
	public function render_template_designer( $attributes ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return '<p>' . esc_html__( 'You do not have permission to view this.', 'office-noc-manager' ) . '</p>';
		}
		
		// Handle template save
		if ( isset( $_POST['onoc_save_template'] ) && wp_verify_nonce( $_POST['onoc_template_nonce'], 'onoc_save_template' ) ) {
			$template_json = isset( $_POST['template_json'] ) ? wp_unslash( $_POST['template_json'] ) : '';
			// Validate JSON
			if ( ! empty( $template_json ) ) {
				$decoded = json_decode( $template_json, true );
				if ( json_last_error() === JSON_ERROR_NONE ) {
					update_option( 'noc_pdf_template', $template_json );
					echo '<div class="notice notice-success"><p>' . esc_html__( 'Template saved successfully.', 'office-noc-manager' ) . '</p></div>';
				} else {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Invalid JSON format.', 'office-noc-manager' ) . '</p></div>';
				}
			} else {
				update_option( 'noc_pdf_template', '' );
				echo '<div class="notice notice-success"><p>' . esc_html__( 'Template cleared.', 'office-noc-manager' ) . '</p></div>';
			}
		}
		
		$template_json = get_option( 'noc_pdf_template', '' );
		
		ob_start();
		?>
		<div class="onoc-template-designer">
			<div class="onoc-template-info">
				<p><?php esc_html_e( 'Design your PDF template using Gutenberg blocks. The template is stored as block JSON. Use placeholders like {{full_name}}, {{reference_id}}, {{visiting_country}}, etc. in your block content.', 'office-noc-manager' ); ?></p>
				<p><strong><?php esc_html_e( 'Available Placeholders:', 'office-noc-manager' ); ?></strong></p>
				<ul>
					<li><code>{{full_name}}</code> - <?php esc_html_e( 'Employee full name', 'office-noc-manager' ); ?></li>
					<li><code>{{email}}</code> - <?php esc_html_e( 'Employee email', 'office-noc-manager' ); ?></li>
					<li><code>{{reference_id}}</code> - <?php esc_html_e( 'NOC reference ID', 'office-noc-manager' ); ?></li>
					<li><code>{{visiting_country}}</code> - <?php esc_html_e( 'Destination country', 'office-noc-manager' ); ?></li>
					<li><code>{{purpose}}</code> - <?php esc_html_e( 'Purpose of visit', 'office-noc-manager' ); ?></li>
					<li><code>{{leave_start}}</code> - <?php esc_html_e( 'Leave start date', 'office-noc-manager' ); ?></li>
					<li><code>{{leave_end}}</code> - <?php esc_html_e( 'Leave end date', 'office-noc-manager' ); ?></li>
					<li><code>{{issue_date}}</code> - <?php esc_html_e( 'Current date', 'office-noc-manager' ); ?></li>
					<li><code>{{employee_id}}</code> - <?php esc_html_e( 'Employee ID', 'office-noc-manager' ); ?></li>
					<li><code>{{joining_date}}</code> - <?php esc_html_e( 'Employee joining date', 'office-noc-manager' ); ?></li>
					<li><code>{{position}}</code> - <?php esc_html_e( 'Employee position', 'office-noc-manager' ); ?></li>
					<li><code>{{department}}</code> - <?php esc_html_e( 'Employee department', 'office-noc-manager' ); ?></li>
					<li><code>{{number_of_days}}</code> - <?php esc_html_e( 'Number of days (calculated from leave dates)', 'office-noc-manager' ); ?></li>
					<li><code>{{qr_code}}</code> - <?php esc_html_e( 'QR code image (for image blocks) - links to verification page', 'office-noc-manager' ); ?></li>
					<li><code>{{company_name}}</code> - <?php esc_html_e( 'Company name from settings', 'office-noc-manager' ); ?></li>
					<li><code>{{signature}}</code> - <?php esc_html_e( 'HR signature (for image blocks)', 'office-noc-manager' ); ?></li>
					<li><code>{{company_phone}}</code> - <?php esc_html_e( 'Company phone', 'office-noc-manager' ); ?></li>
					<li><code>{{company_email}}</code> - <?php esc_html_e( 'Company email', 'office-noc-manager' ); ?></li>
				</ul>
				<p><em><?php esc_html_e( 'Note: To use the full Gutenberg editor, you can create a draft post/page, design your template there, then copy the block JSON and paste it below.', 'office-noc-manager' ); ?></em></p>
			</div>
			
			<form method="post" id="onoc-template-save-form">
				<?php wp_nonce_field( 'onoc_save_template', 'onoc_template_nonce' ); ?>
				
				<div class="onoc-template-editor-wrapper" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin-top: 20px;">
					<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
						<label for="onoc-template-json" style="margin: 0; font-size: 14px; font-weight: 600;">
							<?php esc_html_e( 'Block JSON Template', 'office-noc-manager' ); ?>
						</label>
						<div class="onoc-json-toolbar" style="display: flex; gap: 10px;">
							<button type="button" class="button button-small onoc-format-json" title="<?php esc_attr_e( 'Format JSON', 'office-noc-manager' ); ?>">
								<?php esc_html_e( 'Format', 'office-noc-manager' ); ?>
							</button>
							<button type="button" class="button button-small onoc-validate-json" title="<?php esc_attr_e( 'Validate JSON', 'office-noc-manager' ); ?>">
								<?php esc_html_e( 'Validate', 'office-noc-manager' ); ?>
							</button>
							<button type="button" class="button button-small onoc-load-default" title="<?php esc_attr_e( 'Load Default Template', 'office-noc-manager' ); ?>">
								<?php esc_html_e( 'Load Default', 'office-noc-manager' ); ?>
							</button>
							<button type="button" class="button button-small onoc-clear-json" title="<?php esc_attr_e( 'Clear JSON', 'office-noc-manager' ); ?>">
								<?php esc_html_e( 'Clear', 'office-noc-manager' ); ?>
							</button>
						</div>
					</div>
					
					<div style="position: relative;">
						<textarea id="onoc-template-json" name="template_json" rows="20" style="width: 100%; font-family: 'Courier New', Courier, monospace; font-size: 13px; line-height: 1.6; padding: 15px; border: 1px solid #8c8f94; background: #f6f7f7; resize: vertical; tab-size: 2;"><?php echo esc_textarea( $template_json ); ?></textarea>
						<div id="onoc-json-status" style="margin-top: 8px; font-size: 12px; color: #646970;"></div>
					</div>
					
					<div style="margin-top: 10px;">
						<p class="description" style="margin: 5px 0;">
							<?php esc_html_e( 'Paste your Gutenberg block JSON here. You can get this from the block editor by using browser developer tools or WordPress block editor APIs.', 'office-noc-manager' ); ?>
						</p>
						<p class="description" style="margin: 5px 0;">
							<strong><?php esc_html_e( 'Tip:', 'office-noc-manager' ); ?></strong> 
							<?php esc_html_e( 'Use the Format button to beautify your JSON, or Load Default to start with a pre-configured template.', 'office-noc-manager' ); ?>
						</p>
					</div>
				</div>
				
				<div class="onoc-template-actions" style="margin-top: 20px; display: flex; gap: 10px; align-items: center;">
					<?php submit_button( __( 'Save Template', 'office-noc-manager' ), 'primary', 'onoc_save_template' ); ?>
					<button type="button" class="button onoc-preview-pdf-btn">
						<?php esc_html_e( 'Preview PDF', 'office-noc-manager' ); ?>
					</button>
					<span id="onoc-json-char-count" style="margin-left: auto; color: #646970; font-size: 12px;"></span>
				</div>
			</form>
			
			<script type="text/javascript">
			jQuery(document).ready(function($) {
				var $textarea = $('#onoc-template-json');
				var $status = $('#onoc-json-status');
				var $charCount = $('#onoc-json-char-count');
				
				// Update character count
				function updateCharCount() {
					var text = $textarea.val();
					var lines = text.split('\n').length;
					var chars = text.length;
					$charCount.text(chars + ' characters, ' + lines + ' lines');
				}
				
				// Format JSON
				$('.onoc-format-json').on('click', function() {
					var jsonText = $textarea.val().trim();
					if (!jsonText) {
						$status.html('<span style="color: #d63638;">⚠ ' + <?php echo wp_json_encode( __( 'No JSON to format.', 'office-noc-manager' ) ); ?> + '</span>');
						return;
					}
					
					try {
						var parsed = JSON.parse(jsonText);
						var formatted = JSON.stringify(parsed, null, 2);
						$textarea.val(formatted);
						$status.html('<span style="color: #00a32a;">✓ ' + <?php echo wp_json_encode( __( 'JSON formatted successfully.', 'office-noc-manager' ) ); ?> + '</span>');
						updateCharCount();
					} catch (e) {
						$status.html('<span style="color: #d63638;">✗ ' + <?php echo wp_json_encode( __( 'Invalid JSON:', 'office-noc-manager' ) ); ?> + ' ' + e.message + '</span>');
					}
				});
				
				// Validate JSON
				$('.onoc-validate-json').on('click', function() {
					var jsonText = $textarea.val().trim();
					if (!jsonText) {
						$status.html('<span style="color: #d63638;">⚠ ' + <?php echo wp_json_encode( __( 'No JSON to validate.', 'office-noc-manager' ) ); ?> + '</span>');
						return;
					}
					
					try {
						var parsed = JSON.parse(jsonText);
						if (Array.isArray(parsed) && parsed.length > 0) {
							var blockCount = parsed.length;
							$status.html('<span style="color: #00a32a;">✓ ' + <?php echo wp_json_encode( __( 'Valid JSON with', 'office-noc-manager' ) ); ?> + ' ' + blockCount + ' ' + <?php echo wp_json_encode( __( 'block(s).', 'office-noc-manager' ) ); ?> + '</span>');
						} else {
							$status.html('<span style="color: #d63638;">⚠ ' + <?php echo wp_json_encode( __( 'JSON is valid but empty or not an array.', 'office-noc-manager' ) ); ?> + '</span>');
						}
					} catch (e) {
						$status.html('<span style="color: #d63638;">✗ ' + <?php echo wp_json_encode( __( 'Invalid JSON:', 'office-noc-manager' ) ); ?> + ' ' + e.message + '</span>');
					}
				});
				
				// Load default template
				$('.onoc-load-default').on('click', function() {
					if (!confirm(<?php echo wp_json_encode( __( 'This will replace your current template with the default template. Continue?', 'office-noc-manager' ) ); ?>)) {
						return;
					}
					
					var defaultTemplate = <?php echo wp_json_encode( ONOC_Template_Helper::get_default_template() ); ?>;
					var formatted = JSON.stringify(defaultTemplate, null, 2);
					$textarea.val(formatted);
					$status.html('<span style="color: #00a32a;">✓ ' + <?php echo wp_json_encode( __( 'Default template loaded.', 'office-noc-manager' ) ); ?> + '</span>');
					updateCharCount();
				});
				
				// Clear JSON
				$('.onoc-clear-json').on('click', function() {
					if (!confirm(<?php echo wp_json_encode( __( 'Are you sure you want to clear the template?', 'office-noc-manager' ) ); ?>)) {
						return;
					}
					$textarea.val('');
					$status.html('<span style="color: #646970;">' + <?php echo wp_json_encode( __( 'Template cleared.', 'office-noc-manager' ) ); ?> + '</span>');
					updateCharCount();
				});
				
				// Auto-validate on paste and change
				$textarea.on('input paste', function() {
					updateCharCount();
					// Clear status after a delay
					setTimeout(function() {
						if ($status.text().indexOf('✓') === -1 && $status.text().indexOf('✗') === -1) {
							$status.html('');
						}
					}, 3000);
				});
				
				// Validate on form submit
				$('#onoc-template-save-form').on('submit', function(e) {
					var jsonText = $textarea.val().trim();
					if (jsonText) {
						try {
							JSON.parse(jsonText);
						} catch (err) {
							e.preventDefault();
							alert(<?php echo wp_json_encode( __( 'Invalid JSON format. Please fix the errors before saving.', 'office-noc-manager' ) ); ?> + '\n\n' + err.message);
							$status.html('<span style="color: #d63638;">✗ ' + <?php echo wp_json_encode( __( 'Invalid JSON:', 'office-noc-manager' ) ); ?> + ' ' + err.message + '</span>');
							return false;
						}
					}
				});
				
				// Initial character count
				updateCharCount();
				
				// Auto-format on Ctrl+Shift+F or Cmd+Shift+F
				$textarea.on('keydown', function(e) {
					if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.keyCode === 70) {
						e.preventDefault();
						$('.onoc-format-json').click();
					}
				});
			});
			</script>
		</div>
		<?php
		return ob_get_clean();
	}
	
	/**
	 * Approve request via AJAX
	 */
	public static function approve_request() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'onoc_admin_actions' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed.' ) );
		}
		
		// Check capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}
		
		$request_id = isset( $_POST['request_id'] ) ? (int) $_POST['request_id'] : 0;
		$hr_note = isset( $_POST['hr_note'] ) ? trim( sanitize_textarea_field( $_POST['hr_note'] ) ) : '';
		
		if ( ! $request_id ) {
			wp_send_json_error( array( 'message' => 'Invalid request ID.' ) );
		}
		
		// Validate HR note is required
		if ( empty( $hr_note ) ) {
			wp_send_json_error( array( 'message' => __( 'HR note is required for approval.', 'office-noc-manager' ) ) );
		}
		
		// Generate PDF
		$pdf_url = ONOC_PDF_Generator::generate_pdf( $request_id );
		
		if ( ! $pdf_url ) {
			wp_send_json_error( array( 'message' => 'Failed to generate PDF.' ) );
		}
		
		// Update request
		$db = new ONOC_DB();
		$success = $db->approve_request( $request_id, $pdf_url, $hr_note );
		
		if ( ! $success ) {
			wp_send_json_error( array( 'message' => 'Failed to update request.' ) );
		}
		
		// Send approval email
		$email = new ONOC_Email();
		$email->send_approval_email( $request_id );
		
		wp_send_json_success( array(
			'message' => 'Request approved successfully. PDF generated and email sent.',
			'pdf_url' => $pdf_url,
		) );
	}
	
	/**
	 * Reject request via AJAX
	 */
	public static function reject_request() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'onoc_admin_actions' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed.' ) );
		}
		
		// Check capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}
		
		$request_id = isset( $_POST['request_id'] ) ? (int) $_POST['request_id'] : 0;
		$hr_note = isset( $_POST['hr_note'] ) ? trim( sanitize_textarea_field( $_POST['hr_note'] ) ) : '';
		
		if ( ! $request_id ) {
			wp_send_json_error( array( 'message' => 'Invalid request ID.' ) );
		}
		
		// Validate HR note is required
		if ( empty( $hr_note ) ) {
			wp_send_json_error( array( 'message' => __( 'HR note (rejection reason) is required.', 'office-noc-manager' ) ) );
		}
		
		// Update request
		$db = new ONOC_DB();
		$success = $db->reject_request( $request_id, $hr_note );
		
		if ( ! $success ) {
			wp_send_json_error( array( 'message' => 'Failed to update request.' ) );
		}
		
		// Send rejection email
		$email = new ONOC_Email();
		$email->send_rejection_email( $request_id );
		
		wp_send_json_success( array(
			'message' => 'Request rejected. Email sent to applicant.',
		) );
	}
	
	/**
	 * AJAX handler for removing images
	 */
	public function ajax_remove_image() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'onoc_admin_actions' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'office-noc-manager' ) ) );
		}
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'office-noc-manager' ) ) );
		}
		
		$image_type = isset( $_POST['image_type'] ) ? sanitize_text_field( $_POST['image_type'] ) : '';
		
		if ( ! in_array( $image_type, array( 'signature', 'header', 'footer' ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid image type.', 'office-noc-manager' ) ) );
		}
		
		switch ( $image_type ) {
			case 'signature':
				$file_path = get_option( 'onoc_signature_image_path', '' );
				if ( $file_path && file_exists( $file_path ) ) {
					@unlink( $file_path );
				}
				delete_option( 'onoc_signature_image' );
				delete_option( 'onoc_signature_image_path' );
				$container_id = 'onoc-signature-image-container';
				break;
			case 'header':
				$file_path = get_option( 'onoc_pdf_header_path', '' );
				if ( $file_path && file_exists( $file_path ) ) {
					@unlink( $file_path );
				}
				delete_option( 'onoc_pdf_header' );
				delete_option( 'onoc_pdf_header_path' );
				$container_id = 'onoc-header-image-container';
				break;
			case 'footer':
				$file_path = get_option( 'onoc_pdf_footer_path', '' );
				if ( $file_path && file_exists( $file_path ) ) {
					@unlink( $file_path );
				}
				delete_option( 'onoc_pdf_footer' );
				delete_option( 'onoc_pdf_footer_path' );
				$container_id = 'onoc-footer-image-container';
				break;
		}
		
		wp_send_json_success( array(
			'message' => __( 'Image removed successfully.', 'office-noc-manager' ),
			'container_id' => $container_id
		) );
	}
}

