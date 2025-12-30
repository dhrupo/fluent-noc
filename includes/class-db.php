<?php
/**
 * Database operations for NOC requests
 *
 * @package Office_NOC_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ONOC_DB {
	
	/**
	 * Table name
	 */
	private $table_name;
	
	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'noc_requests';
	}
	
	/**
	 * Create database table
	 */
	public function create_table() {
		global $wpdb;
		
		$charset_collate = $wpdb->get_charset_collate();
		
		$sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			reference_id VARCHAR(50) NOT NULL,
			full_name VARCHAR(255) NOT NULL,
			employee_id VARCHAR(100) NOT NULL,
			email VARCHAR(255) NOT NULL,
			joining_date DATE,
			position VARCHAR(255),
			department VARCHAR(255),
			visiting_country VARCHAR(100) NOT NULL,
			purpose TEXT NOT NULL,
			leave_start DATE NOT NULL,
			leave_end DATE NOT NULL,
			status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
			hr_note TEXT,
			pdf_url TEXT,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY reference_id (reference_id),
			KEY status (status),
			KEY email (email),
			KEY created_at (created_at)
		) $charset_collate;";
		
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		
		// Add new columns if they don't exist (for existing installations)
		$columns_to_add = array(
			'employee_id' => "VARCHAR(100) NOT NULL DEFAULT ''",
			'joining_date' => 'DATE NULL',
			'position' => 'VARCHAR(255) NULL',
			'department' => 'VARCHAR(255) NULL',
		);
		
		foreach ( $columns_to_add as $column => $definition ) {
			$column_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$this->table_name} LIKE %s", $column ) );
			if ( empty( $column_exists ) ) {
				$after = ( $column === 'employee_id' ) ? 'AFTER full_name' : ( ( $column === 'joining_date' ) ? 'AFTER email' : ( ( $column === 'position' ) ? 'AFTER joining_date' : 'AFTER position' ) );
				// Use $wpdb->prepare for safety, but ALTER TABLE doesn't support placeholders for column names
				// So we sanitize the column name and definition
				$column_safe = sanitize_key( $column );
				$wpdb->query( "ALTER TABLE {$this->table_name} ADD COLUMN `{$column_safe}` {$definition} {$after}" );
			}
		}
		
		// Modify employee_id to be NOT NULL if it's currently nullable
		$wpdb->query( "ALTER TABLE {$this->table_name} MODIFY COLUMN employee_id VARCHAR(100) NOT NULL DEFAULT ''" );
	}
	
	/**
	 * Generate unique reference ID (non-guessable, alphanumeric only)
	 */
	public function generate_reference_id() {
		global $wpdb;
		
		// Generate a random, non-guessable reference ID
		// Format: NOCYYYYXXXXXXXX (year + 8 random alphanumeric characters, no dashes or special chars)
		$year = date( 'Y' );
		$max_attempts = 10;
		$attempt = 0;
		
		do {
			// Generate random 8-character alphanumeric string (only uppercase letters and numbers)
			$random_part = strtoupper( wp_generate_password( 8, false, '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ' ) );
			// Remove any non-alphanumeric characters just to be safe
			$random_part = preg_replace( '/[^A-Z0-9]/', '', $random_part );
			$reference_id = "NOC{$year}{$random_part}";
			
			// Check if it already exists
			$exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} WHERE reference_id = %s",
				$reference_id
			) );
			
			$attempt++;
		} while ( $exists > 0 && $attempt < $max_attempts );
		
		// If still duplicate after max attempts, add more randomness
		if ( $exists > 0 ) {
			$random_part = strtoupper( wp_generate_password( 10, false, '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ' ) );
			$random_part = preg_replace( '/[^A-Z0-9]/', '', $random_part );
			$reference_id = "NOC{$year}{$random_part}";
		}
		
		return $reference_id;
	}
	
	/**
	 * Insert new request
	 */
	public function insert_request( $data ) {
		global $wpdb;
		
		// Generate reference ID
		$reference_id = $this->generate_reference_id();
		
		// Prepare data
		$insert_data = array(
			'reference_id'    => $reference_id,
			'full_name'       => sanitize_text_field( $data['full_name'] ),
			'employee_id'     => isset( $data['employee_id'] ) && ! empty( $data['employee_id'] ) ? sanitize_text_field( $data['employee_id'] ) : '',
			'email'           => sanitize_email( $data['email'] ),
			'joining_date'    => isset( $data['joining_date'] ) ? sanitize_text_field( $data['joining_date'] ) : null,
			'position'        => isset( $data['position'] ) ? sanitize_text_field( $data['position'] ) : '',
			'department'      => isset( $data['department'] ) ? sanitize_text_field( $data['department'] ) : '',
			'visiting_country' => sanitize_text_field( $data['visiting_country'] ),
			'purpose'         => sanitize_textarea_field( $data['purpose'] ),
			'leave_start'     => sanitize_text_field( $data['leave_start'] ),
			'leave_end'       => sanitize_text_field( $data['leave_end'] ),
			'status'          => 'pending',
		);
		
		// Ensure employee_id is not empty (required field)
		if ( empty( $insert_data['employee_id'] ) ) {
			return array(
				'success' => false,
				'error'   => 'Employee ID is required.',
			);
		}
		
		$result = $wpdb->insert( $this->table_name, $insert_data );
		
		if ( $result ) {
			return array(
				'success' => true,
				'id'      => $wpdb->insert_id,
				'reference_id' => $reference_id,
			);
		}
		
		return array(
			'success' => false,
			'error'   => $wpdb->last_error,
		);
	}
	
	/**
	 * Get request by ID
	 */
	public function get_request( $id ) {
		global $wpdb;
		
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE id = %d",
			$id
		), ARRAY_A );
	}
	
	/**
	 * Get request by reference ID
	 */
	public function get_request_by_reference( $reference_id ) {
		global $wpdb;
		
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE reference_id = %s",
			$reference_id
		), ARRAY_A );
	}
	
	/**
	 * Get requests by status
	 */
	public function get_requests_by_status( $status, $page = 1, $per_page = 20 ) {
		global $wpdb;
		
		$offset = ( $page - 1 ) * $per_page;
		
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$this->table_name} 
			WHERE status = %s 
			ORDER BY created_at DESC 
			LIMIT %d OFFSET %d",
			$status,
			$per_page,
			$offset
		), ARRAY_A );
		
		$total = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table_name} WHERE status = %s",
			$status
		) );
		
		return array(
			'requests' => $results,
			'total'    => (int) $total,
			'pages'    => ceil( $total / $per_page ),
		);
	}
	
	/**
	 * Get requests with filters
	 */
	public function get_requests_filtered( $args = array() ) {
		global $wpdb;
		
		$defaults = array(
			'status'     => '', // empty = all
			'search'     => '', // search by name or email
			'date_from'  => '', // start date filter
			'date_to'    => '', // end date filter
			'page'       => 1,
			'per_page'   => 20,
		);
		
		$args = wp_parse_args( $args, $defaults );
		
		$where = array( '1=1' );
		$where_values = array();
		
		// Status filter
		if ( ! empty( $args['status'] ) && in_array( $args['status'], array( 'pending', 'approved', 'rejected' ), true ) ) {
			$where[] = "status = %s";
			$where_values[] = $args['status'];
		}
		
		// Search filter (name or email)
		if ( ! empty( $args['search'] ) ) {
			$search_term = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = "(full_name LIKE %s OR email LIKE %s)";
			$where_values[] = $search_term;
			$where_values[] = $search_term;
		}
		
		// Date range filter (based on created_at)
		if ( ! empty( $args['date_from'] ) ) {
			$where[] = "DATE(created_at) >= %s";
			$where_values[] = $args['date_from'];
		}
		
		if ( ! empty( $args['date_to'] ) ) {
			$where[] = "DATE(created_at) <= %s";
			$where_values[] = $args['date_to'];
		}
		
		$where_clause = implode( ' AND ', $where );
		$offset = ( $args['page'] - 1 ) * $args['per_page'];
		
		// Store count values before adding LIMIT/OFFSET
		$count_values = $where_values;
		
		// Build query - always add LIMIT and OFFSET
		$where_values[] = $args['per_page'];
		$where_values[] = $offset;
		
		$query = "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		
		// Get results
		$results = $wpdb->get_results( $wpdb->prepare( $query, $where_values ), ARRAY_A );
		
		// Get total count
		$count_query = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}";
		
		if ( ! empty( $count_values ) ) {
			$total = $wpdb->get_var( $wpdb->prepare( $count_query, $count_values ) );
		} else {
			$total = $wpdb->get_var( $count_query );
		}
		
		return array(
			'requests' => $results ? $results : array(),
			'total'    => (int) $total,
			'pages'    => ceil( (int) $total / $args['per_page'] ),
		);
	}
	
	/**
	 * Update request
	 */
	public function update_request( $id, $data ) {
		global $wpdb;
		
		$update_data = array();
		
		if ( isset( $data['status'] ) ) {
			$update_data['status'] = sanitize_text_field( $data['status'] );
		}
		
		if ( isset( $data['hr_note'] ) ) {
			$update_data['hr_note'] = sanitize_textarea_field( $data['hr_note'] );
		}
		
		if ( isset( $data['pdf_url'] ) ) {
			$update_data['pdf_url'] = esc_url_raw( $data['pdf_url'] );
		}
		
		if ( empty( $update_data ) ) {
			return false;
		}
		
		$result = $wpdb->update(
			$this->table_name,
			$update_data,
			array( 'id' => $id ),
			null,
			array( '%d' )
		);
		
		return $result !== false;
	}
	
	/**
	 * Approve request
	 */
	public function approve_request( $id, $pdf_url, $hr_note = '' ) {
		$update_data = array(
			'status'  => 'approved',
			'pdf_url' => $pdf_url,
		);
		
		// HR note is required
		if ( ! empty( $hr_note ) ) {
			$update_data['hr_note'] = $hr_note;
		}
		
		return $this->update_request( $id, $update_data );
	}
	
	/**
	 * Reject request
	 */
	public function reject_request( $id, $hr_note ) {
		return $this->update_request( $id, array(
			'status'  => 'rejected',
			'hr_note' => $hr_note,
		) );
	}
	
	/**
	 * Get table name
	 */
	public function get_table_name() {
		return $this->table_name;
	}
}

