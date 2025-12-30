<?php
/**
 * Plugin Name: Fluent NOC Manager
 * Plugin URI: https://github.com/dhrupo/fluent-noc
 * Description: Manage No Objection Certificate (NOC) requests with frontend application form, admin review panel, PDF generation, and email notifications.
 * Version: 1.0.0
 * Author: Dhrupo
 * Author URI: https://profiles.wordpress.org/dhrupo
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: office-noc-manager
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Tested up to: 6.5
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'ONOC_VERSION', '1.0.0' );
define( 'ONOC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ONOC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ONOC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class
 */
class Office_NOC_Manager {
	
	/**
	 * Single instance of the class
	 */
	private static $instance = null;
	
	/**
	 * Get single instance
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init();
	}
	
	/**
	 * Initialize plugin
	 */
	private function init() {
		// Load dependencies
		$this->load_dependencies();
		
		// Register activation/deactivation hooks
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
		
		// Initialize on plugins loaded
		add_action( 'plugins_loaded', array( $this, 'load_plugin' ) );
	}
	
	/**
	 * Load plugin dependencies
	 */
	private function load_dependencies() {
		// Autoloader for classes
		spl_autoload_register( array( $this, 'autoload_classes' ) );
		
		// Load Composer autoloader if available
		if ( file_exists( ONOC_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
			require_once ONOC_PLUGIN_DIR . 'vendor/autoload.php';
		}
	}
	
	/**
	 * Autoloader for plugin classes
	 */
	private function autoload_classes( $class_name ) {
		// Only load our classes
		if ( strpos( $class_name, 'ONOC_' ) !== 0 ) {
			return;
		}
		
		// Convert class name to file name
		$class_name = str_replace( 'ONOC_', '', $class_name );
		$class_name = str_replace( '_', '-', $class_name );
		$class_name = strtolower( $class_name );
		
		$file_path = ONOC_PLUGIN_DIR . 'includes/class-' . $class_name . '.php';
		
		if ( file_exists( $file_path ) ) {
			require_once $file_path;
		}
	}
	
	/**
	 * Plugin activation
	 */
	public function activate() {
		// Create database table
		$db = new ONOC_DB();
		$db->create_table();
		
		// Create upload directory
		$this->create_upload_directory();
		
		// Set default options
		$this->set_default_options();
		
		// Add rewrite rules
		add_rewrite_rule( '^noc-verification/?$', 'index.php?onoc_verification=1', 'top' );
		add_rewrite_tag( '%onoc_verification%', '([^&]+)' );
		
		// Flush rewrite rules
		flush_rewrite_rules();
	}
	
	/**
	 * Plugin deactivation
	 */
	public function deactivate() {
		// Flush rewrite rules
		flush_rewrite_rules();
	}
	
	/**
	 * Create upload directory for PDFs
	 */
	private function create_upload_directory() {
		$upload_dir = wp_upload_dir();
		$noc_dir = $upload_dir['basedir'] . '/noc-pdfs';
		
		if ( ! file_exists( $noc_dir ) ) {
			wp_mkdir_p( $noc_dir );
			
			// Add .htaccess for security
			$htaccess_content = "Order deny,allow\nDeny from all\n";
			file_put_contents( $noc_dir . '/.htaccess', $htaccess_content );
		}
	}
	
	/**
	 * Set default plugin options
	 */
	private function set_default_options() {
		if ( ! get_option( 'onoc_company_name' ) ) {
			update_option( 'onoc_company_name', '' );
		}
		if ( ! get_option( 'onoc_company_logo' ) ) {
			update_option( 'onoc_company_logo', '' );
		}
		if ( ! get_option( 'onoc_signature_image' ) ) {
			update_option( 'onoc_signature_image', '' );
		}
		if ( ! get_option( 'onoc_pdf_header' ) ) {
			update_option( 'onoc_pdf_header', '' );
		}
		if ( ! get_option( 'onoc_pdf_footer' ) ) {
			update_option( 'onoc_pdf_footer', '' );
		}
		if ( ! get_option( 'onoc_company_address' ) ) {
			update_option( 'onoc_company_address', '' );
		}
		if ( ! get_option( 'onoc_company_phone' ) ) {
			update_option( 'onoc_company_phone', '' );
		}
		if ( ! get_option( 'onoc_company_email' ) ) {
			update_option( 'onoc_company_email', '' );
		}
		if ( ! get_option( 'onoc_hr_name' ) ) {
			update_option( 'onoc_hr_name', '' );
		}
		if ( ! get_option( 'onoc_hr_title' ) ) {
			update_option( 'onoc_hr_title', '' );
		}
		if ( ! get_option( 'onoc_email_from_name' ) ) {
			update_option( 'onoc_email_from_name', get_bloginfo( 'name' ) );
		}
		if ( ! get_option( 'onoc_email_from_address' ) ) {
			update_option( 'onoc_email_from_address', get_option( 'admin_email' ) );
		}
		
		// Initialize default PDF template
		ONOC_Template_Helper::init_default_template();
	}
	
	/**
	 * Load plugin functionality
	 */
	public function load_plugin() {
		// Initialize admin
		if ( is_admin() ) {
			new ONOC_Admin();
			new ONOC_Gutenberg_Admin();
		}
		
		// Initialize frontend
		new ONOC_Frontend_Form();
		new ONOC_Verification();
		
		// Register shortcodes
		add_shortcode( 'noc_application_form', array( 'ONOC_Frontend_Form', 'render_form' ) );
		
		// Register AJAX handlers
		add_action( 'wp_ajax_onoc_submit_request', array( 'ONOC_Frontend_Form', 'handle_submission' ) );
		add_action( 'wp_ajax_nopriv_onoc_submit_request', array( 'ONOC_Frontend_Form', 'handle_submission' ) );
		add_action( 'wp_ajax_onoc_approve_request', array( 'ONOC_Gutenberg_Admin', 'approve_request' ) );
		add_action( 'wp_ajax_onoc_reject_request', array( 'ONOC_Gutenberg_Admin', 'reject_request' ) );
		add_action( 'wp_ajax_onoc_preview_pdf', array( 'ONOC_PDF_Generator', 'preview_pdf' ) );
	}
}

// Initialize plugin
Office_NOC_Manager::get_instance();

