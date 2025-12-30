<?php
/**
 * PDF generator using Dompdf
 *
 * @package Office_NOC_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ONOC_PDF_Generator {
	
	/**
	 * Generate PDF for request
	 */
	public static function generate_pdf( $request_id ) {
		$db = new ONOC_DB();
		$request = $db->get_request( $request_id );
		
		if ( ! $request ) {
			return false;
		}
		
		// Get template
		$template_json = get_option( 'noc_pdf_template', '' );
		if ( empty( $template_json ) ) {
			return false;
		}
		
		// Generate QR code
		$qr_code = self::generate_qr_code( $request['reference_id'] );
		
		// Prepare data for placeholders
		$data = array(
			'full_name'       => $request['full_name'],
			'employee_id'     => isset( $request['employee_id'] ) ? $request['employee_id'] : '',
			'email'           => $request['email'],
			'reference_id'    => $request['reference_id'],
			'joining_date'    => isset( $request['joining_date'] ) ? $request['joining_date'] : '',
			'position'        => isset( $request['position'] ) ? $request['position'] : '',
			'department'      => isset( $request['department'] ) ? $request['department'] : '',
			'visiting_country' => $request['visiting_country'],
			'purpose'         => $request['purpose'],
			'leave_start'     => $request['leave_start'],
			'leave_end'       => $request['leave_end'],
			'qr_code'         => $qr_code,
		);
		
		// Render blocks to HTML
		$renderer = new ONOC_Block_Renderer();
		$renderer->set_placeholders( $data );
		$html = $renderer->render_blocks( $template_json );
		
		// Add PDF-specific CSS
		$html = self::wrap_html( $html );
		
		// Generate PDF
		$pdf_path = self::create_pdf( $html, $request['reference_id'] );
		
		return $pdf_path;
	}
	
	/**
	 * Generate QR code
	 */
	private static function generate_qr_code( $reference_id ) {
		if ( file_exists( ONOC_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
			require_once ONOC_PLUGIN_DIR . 'vendor/autoload.php';
		}
		
		if ( ! class_exists( 'Endroid\QrCode\QrCode' ) ) {
			return '';
		}
		
		try {
			// Use rawurlencode for proper URL encoding
			$verification_url = home_url( '/noc-verification/?ref=' . rawurlencode( $reference_id ) );
			
			$qrCode = new \Endroid\QrCode\QrCode( $verification_url );
			$qrCode->setSize( 200 );
			$qrCode->setMargin( 10 );
			
			$writer = new \Endroid\QrCode\Writer\PngWriter();
			$result = $writer->write( $qrCode );
			
			// Convert to base64 data URI
			$data_uri = $result->getDataUri();
			
			return $data_uri;
		} catch ( Exception $e ) {
			return '';
		}
	}
	
	/**
	 * Convert image URL to absolute URL or base64 for PDF
	 */
	private static function get_image_for_pdf( $url ) {
		if ( empty( $url ) ) {
			return '';
		}
		
		if ( strpos( $url, 'data:' ) === 0 ) {
			return $url;
		}
		
		$upload_dir = wp_upload_dir();
		$file_path = '';
		
		if ( file_exists( $url ) && is_file( $url ) ) {
			$file_path = $url;
		} elseif ( strpos( $url, 'http://' ) === 0 || strpos( $url, 'https://' ) === 0 ) {
			if ( strpos( $url, $upload_dir['baseurl'] ) !== false ) {
				$file_path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $url );
			} else {
				$parsed_url = parse_url( $url );
				$url_path = isset( $parsed_url['path'] ) ? $parsed_url['path'] : '';
				
				if ( strpos( $url_path, '/wp-content/uploads/' ) !== false ) {
					$file_path = ABSPATH . ltrim( $url_path, '/' );
				} elseif ( ! empty( $url_path ) ) {
					$file_path = ABSPATH . ltrim( $url_path, '/' );
				}
			}
		} elseif ( strpos( $url, $upload_dir['baseurl'] ) !== false ) {
			$file_path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $url );
		} elseif ( strpos( $url, '/' ) === 0 ) {
			$file_path = ABSPATH . ltrim( $url, '/' );
		} elseif ( strpos( $url, 'wp-content/uploads' ) !== false ) {
			$file_path = ABSPATH . ltrim( $url, '/' );
		}
		
		if ( ! empty( $file_path ) && file_exists( $file_path ) && is_readable( $file_path ) ) {
			$image_data = @file_get_contents( $file_path );
			if ( $image_data !== false ) {
				$image_info = @getimagesize( $file_path );
				if ( $image_info !== false ) {
					$mime_type = $image_info['mime'];
					$base64 = base64_encode( $image_data );
					return 'data:' . $mime_type . ';base64,' . $base64;
				}
			}
		}
		
		if ( strpos( $url, 'http://' ) === 0 || strpos( $url, 'https://' ) === 0 ) {
			return $url;
		}
		
		if ( strpos( $url, '/' ) === 0 ) {
			return home_url( $url );
		}
		
		if ( strpos( $url, 'http' ) !== 0 ) {
			return home_url( '/' . ltrim( $url, '/' ) );
		}
		
		return $url;
	}
	
	/**
	 * Wrap HTML with PDF-specific styling and header/footer
	 */
	private static function wrap_html( $content ) {
		// Get header and footer images (try file path first, then URL)
		$header_path = get_option( 'onoc_pdf_header_path', '' );
		$header_image = ! empty( $header_path ) ? $header_path : get_option( 'onoc_pdf_header', '' );
		
		$footer_path = get_option( 'onoc_pdf_footer_path', '' );
		$footer_image = ! empty( $footer_path ) ? $footer_path : get_option( 'onoc_pdf_footer', '' );
		
		$header_html = '';
		if ( ! empty( $header_image ) ) {
			$header_url = self::get_image_for_pdf( $header_image );
			if ( ! empty( $header_url ) ) {
				$header_html = '<div class="onoc-pdf-header"><img src="' . esc_attr( $header_url ) . '" style="width: 100%; max-height: 100px; object-fit: contain;" /></div>';
			}
		}
		
		$footer_html = '';
		if ( ! empty( $footer_image ) ) {
			$footer_url = self::get_image_for_pdf( $footer_image );
			if ( ! empty( $footer_url ) ) {
				$footer_html = '<div class="onoc-pdf-footer"><img src="' . esc_attr( $footer_url ) . '" style="width: 100%; max-height: 100px; object-fit: contain; display: block; margin: 0 auto;" alt="Footer" /></div>';
			}
		}
		
		$html = '<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<style>
		@page {
			margin-top: 0;
			margin-bottom: 0;
			margin-left: 2cm;
			margin-right: 2cm;
			size: A4 portrait;
		}
		body {
			font-family: "Times New Roman", Times, serif;
			font-size: 11pt;
			line-height: 1.5;
			color: #000;
			margin: 0;
			padding: 0;
		}
		.onoc-pdf-header {
			margin-bottom: 15px;
			text-align: center;
			page-break-after: avoid;
		}
		.onoc-pdf-header img {
			max-height: 100px;
		}
		.onoc-pdf-content {
			margin: 10px 0;
		}
		.onoc-pdf-footer {
			margin-top: 50px;
			text-align: center;
			page-break-before: avoid;
			width: 100%;
			clear: both;
			position: relative;
		}
		.onoc-pdf-footer img {
			display: block;
			margin: 0 auto;
			max-width: 100%;
			max-height: 80px;
			height: auto;
		}
		h1, h2, h3, h4, h5, h6 {
			margin-top: 0;
			margin-bottom: 8px;
			font-weight: bold;
		}
		p {
			margin: 8px 0;
			text-align: justify;
		}
		.wp-block-spacer {
			height: 10px !important;
			margin: 0;
			padding: 0;
		}
		.wp-block-image img {
			max-width: 100%;
			height: auto;
		}
		.wp-block-columns {
			display: flex;
			gap: 20px;
		}
		.wp-block-column {
			flex: 1;
		}
		.wp-block-image img {
			max-width: 100%;
			height: auto;
		}
		.wp-block-separator {
			border: none;
			border-top: 1px solid #ccc;
			margin: 20px 0;
		}
	</style>
</head>
<body>
	' . $header_html . '
	<div class="onoc-pdf-content">
		' . $content . '
	</div>
	' . $footer_html . '
</body>
</html>';
		
		return $html;
	}
	
	/**
	 * Create PDF file
	 */
	private static function create_pdf( $html, $reference_id ) {
		if ( file_exists( ONOC_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
			require_once ONOC_PLUGIN_DIR . 'vendor/autoload.php';
		}
		
		if ( ! class_exists( 'Dompdf\Dompdf' ) ) {
			return false;
		}
		
		try {
			$options = new \Dompdf\Options();
			$options->set( 'isRemoteEnabled', true );
			$options->set( 'isHtml5ParserEnabled', true );
			$options->set( 'isPhpEnabled', false );
			
			$dompdf = new \Dompdf\Dompdf( $options );
			$dompdf->loadHtml( $html );
			$dompdf->setPaper( 'A4', 'portrait' );
			$dompdf->render();
			
			$upload_dir = wp_upload_dir();
			$noc_dir = $upload_dir['basedir'] . '/noc-pdfs';
			
			if ( ! file_exists( $noc_dir ) ) {
				wp_mkdir_p( $noc_dir );
			}
			
			$filename = 'noc-' . sanitize_file_name( $reference_id ) . '.pdf';
			$filepath = $noc_dir . '/' . $filename;
			file_put_contents( $filepath, $dompdf->output() );
			
			// Return URL
			return $upload_dir['baseurl'] . '/noc-pdfs/' . $filename;
		} catch ( Exception $e ) {
			return false;
		}
	}
	
	/**
	 * Preview PDF with sample data
	 */
	public static function preview_pdf() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'onoc_admin_actions' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed.' ) );
		}
		
		// Check capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}
		
		$template_json = get_option( 'noc_pdf_template', '' );
		if ( empty( $template_json ) ) {
			wp_send_json_error( array( 'message' => 'No template found.' ) );
		}
		$data = array(
			'full_name'        => 'John Doe',
			'employee_id'      => '11111111',
			'email'            => 'john.doe@example.com',
			'reference_id'     => 'NOC2025A1B2C3D4',
			'joining_date'     => '2020-01-15',
			'position'         => 'Software Engineer',
			'department'       => 'Engineering',
			'visiting_country' => 'Malaysia',
			'purpose'          => 'Official business travel and meetings',
			'leave_start'      => date( 'Y-m-d', strtotime( '+7 days' ) ),
			'leave_end'        => date( 'Y-m-d', strtotime( '+14 days' ) ),
			'qr_code'          => self::generate_qr_code( 'NOC2025A1B2C3D4' ),
		);
		
		$renderer = new ONOC_Block_Renderer();
		$renderer->set_placeholders( $data );
		$html = $renderer->render_blocks( $template_json );
		$html = self::wrap_html( $html );
		
		if ( file_exists( ONOC_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
			require_once ONOC_PLUGIN_DIR . 'vendor/autoload.php';
		}
		if ( ! class_exists( 'Dompdf\Dompdf' ) ) {
			wp_send_json_error( array( 
				'message' => 'Dompdf library not available. Please run "composer install" in the plugin directory.',
				'debug' => array(
					'autoloader_exists' => file_exists( ONOC_PLUGIN_DIR . 'vendor/autoload.php' ),
					'dompdf_exists' => file_exists( ONOC_PLUGIN_DIR . 'vendor/dompdf/dompdf/src/Dompdf.php' ),
				)
			) );
		}
		
		try {
			$options = new \Dompdf\Options();
			$options->set( 'isRemoteEnabled', true );
			$options->set( 'isHtml5ParserEnabled', true );
			$options->set( 'isPhpEnabled', false );
			
			$dompdf = new \Dompdf\Dompdf( $options );
			$dompdf->loadHtml( $html );
			$dompdf->setPaper( 'A4', 'portrait' );
			$dompdf->render();
			
			// Return PDF as base64
			$pdf_output = $dompdf->output();
			$base64 = base64_encode( $pdf_output );
			
			wp_send_json_success( array(
				'pdf' => 'data:application/pdf;base64,' . $base64,
			) );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => 'Failed to generate PDF: ' . $e->getMessage() ) );
		}
	}
}

