<?php
/**
 * Public verification page
 *
 * @package Office_NOC_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ONOC_Verification {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'add_rewrite_rule' ) );
		add_action( 'template_redirect', array( $this, 'handle_verification' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}
	
	/**
	 * Add rewrite rule for verification page
	 */
	public function add_rewrite_rule() {
		add_rewrite_rule( '^noc-verification/?$', 'index.php?onoc_verification=1', 'top' );
		add_rewrite_tag( '%onoc_verification%', '([^&]+)' );
		
		// Flush rewrite rules if our rule doesn't exist (only once)
		$rules = get_option( 'rewrite_rules' );
		if ( ! isset( $rules['^noc-verification/?$'] ) ) {
			flush_rewrite_rules( false );
		}
	}
	
	/**
	 * Handle verification page
	 */
	public function handle_verification() {
		// Check both query var and direct URL parameter
		$is_verification_page = get_query_var( 'onoc_verification' ) || ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '/noc-verification' ) !== false );
		
		if ( ! $is_verification_page ) {
			return;
		}
		
		$reference_id = isset( $_GET['ref'] ) ? $_GET['ref'] : '';
		
		// URL decode first in case it's encoded
		$reference_id = urldecode( $reference_id );
		
		// Clean reference ID - remove any special characters, keep only alphanumeric
		$reference_id = preg_replace( '/[^A-Z0-9]/i', '', $reference_id );
		
		if ( empty( $reference_id ) ) {
			$this->render_verification_page( null, 'No reference ID provided.' );
			exit;
		}
		
		$db = new ONOC_DB();
		
		// Try exact match first (for new alphanumeric-only IDs)
		$request = $db->get_request_by_reference( $reference_id );
		
		// If not found, try searching by cleaning stored reference IDs (for backward compatibility with old IDs that had dashes)
		if ( ! $request ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'noc_requests';
			// Search for reference IDs that match when we remove all non-alphanumeric characters
			$results = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(reference_id, '-', ''), '.', ''), '<', ''), '>', ''), '(', ''), ')', ''), '[', ''), ']', ''), '{', ''), '}', '') = %s",
				$reference_id
			), ARRAY_A );
			
			if ( ! empty( $results ) ) {
				$request = $results[0];
			}
		}
		
		if ( ! $request ) {
			$this->render_verification_page( null, 'Invalid reference ID.' );
			exit;
		}
		
		// Only show approved requests
		if ( $request['status'] !== 'approved' ) {
			$this->render_verification_page( null, 'NOC not valid or not approved.' );
			exit;
		}
		
		$this->render_verification_page( $request );
		exit;
	}
	
	/**
	 * Render verification page
	 */
	private function render_verification_page( $request = null, $error = '' ) {
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<title><?php esc_html_e( 'NOC Verification', 'office-noc-manager' ); ?></title>
			<?php wp_head(); ?>
		</head>
		<body <?php body_class(); ?>>
			<div class="onoc-verification-container">
				<div class="onoc-verification-content">
					<?php if ( $request ) : ?>
						<div class="onoc-verification-success">
							<div class="onoc-verification-icon">✓</div>
							<h1><?php esc_html_e( 'NOC VERIFIED', 'office-noc-manager' ); ?></h1>
							
							<div class="onoc-verification-details">
								<div class="onoc-verification-field">
									<strong><?php esc_html_e( 'Name:', 'office-noc-manager' ); ?></strong>
									<span><?php echo esc_html( $request['full_name'] ); ?></span>
								</div>
								
								<div class="onoc-verification-field">
									<strong><?php esc_html_e( 'Visiting Country:', 'office-noc-manager' ); ?></strong>
									<span><?php echo esc_html( $request['visiting_country'] ); ?></span>
								</div>
								
								<div class="onoc-verification-field">
									<strong><?php esc_html_e( 'Purpose:', 'office-noc-manager' ); ?></strong>
									<span><?php echo esc_html( $request['purpose'] ); ?></span>
								</div>
								
								<div class="onoc-verification-field">
									<strong><?php esc_html_e( 'Leave Period:', 'office-noc-manager' ); ?></strong>
									<span><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $request['leave_start'] ) ) ); ?> – <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $request['leave_end'] ) ) ); ?></span>
								</div>
								
								<div class="onoc-verification-field">
									<strong><?php esc_html_e( 'Status:', 'office-noc-manager' ); ?></strong>
									<span class="onoc-status-approved"><?php esc_html_e( 'Approved', 'office-noc-manager' ); ?></span>
								</div>
								
								<div class="onoc-verification-field">
									<strong><?php esc_html_e( 'Reference ID:', 'office-noc-manager' ); ?></strong>
									<span><?php echo esc_html( $request['reference_id'] ); ?></span>
								</div>
								
								<div class="onoc-verification-field">
									<strong><?php esc_html_e( 'Issued On:', 'office-noc-manager' ); ?></strong>
									<span><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $request['created_at'] ) ) ); ?></span>
								</div>
							</div>
						</div>
					<?php else : ?>
						<div class="onoc-verification-error">
							<div class="onoc-verification-icon">✖</div>
							<h1><?php esc_html_e( 'NOC NOT VALID', 'office-noc-manager' ); ?></h1>
							<p><?php echo esc_html( $error ); ?></p>
						</div>
					<?php endif; ?>
				</div>
			</div>
			<?php wp_footer(); ?>
		</body>
		</html>
		<?php
	}
	
	/**
	 * Enqueue scripts and styles
	 */
	public function enqueue_scripts() {
		if ( ! get_query_var( 'onoc_verification' ) ) {
			return;
		}
		
		wp_enqueue_style(
			'onoc-verification',
			ONOC_PLUGIN_URL . 'assets/css/verification.css',
			array(),
			ONOC_VERSION
		);
	}
}

