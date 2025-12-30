<?php
/**
 * Admin panel setup
 *
 * @package Office_NOC_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ONOC_Admin {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}
	
	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'Office NOC', 'office-noc-manager' ),
			__( 'Office NOC', 'office-noc-manager' ),
			'manage_options',
			'office-noc',
			array( $this, 'render_requests_page' ),
			'dashicons-clipboard',
			30
		);
		
		add_submenu_page(
			'office-noc',
			__( 'NOC Requests', 'office-noc-manager' ),
			__( 'NOC Requests', 'office-noc-manager' ),
			'manage_options',
			'office-noc',
			array( $this, 'render_requests_page' )
		);
		
		add_submenu_page(
			'office-noc',
			__( 'PDF Template Designer', 'office-noc-manager' ),
			__( 'PDF Template', 'office-noc-manager' ),
			'manage_options',
			'office-noc-template',
			array( $this, 'render_template_designer_page' )
		);
		
		add_submenu_page(
			'office-noc',
			__( 'Settings', 'office-noc-manager' ),
			__( 'Settings', 'office-noc-manager' ),
			'manage_options',
			'office-noc-settings',
			array( $this, 'render_settings_page' )
		);
	}
	
	/**
	 * Enqueue admin scripts
	 */
	public function enqueue_scripts( $hook ) {
		// Only load on our admin pages - check for our menu slug
		if ( strpos( $hook, 'office-noc' ) === false ) {
			return;
		}
		
		// Enqueue admin CSS
		wp_enqueue_style(
			'onoc-admin',
			ONOC_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			ONOC_VERSION
		);
		
		// Enqueue admin JavaScript
		wp_enqueue_script(
			'onoc-admin',
			ONOC_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			ONOC_VERSION,
			true
		);
		
		// Localize script with AJAX data
		wp_localize_script( 'onoc-admin', 'onocAdmin', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'onoc_admin_actions' ),
		) );
	}
	
	/**
	 * Render requests page (unified)
	 */
	public function render_requests_page() {
		// Check if we're viewing a single request
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'review' && isset( $_GET['id'] ) ) {
			$this->render_request_review_page();
			return;
		}
		
		echo '<div class="wrap"><h1>' . esc_html__( 'NOC Requests', 'office-noc-manager' ) . '</h1>';
		
		// Render the request list block
		$gutenberg_admin = new ONOC_Gutenberg_Admin();
		echo $gutenberg_admin->render_request_list( array() );
		
		echo '</div>';
	}
	
	/**
	 * Render request review page
	 */
	private function render_request_review_page() {
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Review Request', 'office-noc-manager' ) . '</h1>';
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=office-noc' ) ) . '" class="button">← ' . esc_html__( 'Back to NOC Requests', 'office-noc-manager' ) . '</a></p>';
		
		// Render the request review block
		$gutenberg_admin = new ONOC_Gutenberg_Admin();
		$request_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		echo $gutenberg_admin->render_request_review( array( 'requestId' => $request_id ) );
		
		echo '</div>';
	}
	
	/**
	 * Render template designer page
	 */
	public function render_template_designer_page() {
		echo '<div class="wrap"><h1>' . esc_html__( 'PDF Template Designer', 'office-noc-manager' ) . '</h1>';
		
		// Render the template designer block
		$gutenberg_admin = new ONOC_Gutenberg_Admin();
		echo $gutenberg_admin->render_template_designer( array() );
		
		echo '</div>';
	}
	
	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		echo '<div class="wrap"><h1>' . esc_html__( 'Settings', 'office-noc-manager' ) . '</h1>';
		
		// Render the settings block
		$gutenberg_admin = new ONOC_Gutenberg_Admin();
		echo $gutenberg_admin->render_settings( array() );
		
		echo '</div>';
	}
}

