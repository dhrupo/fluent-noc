<?php
/**
 * Template helper - provides default template
 *
 * @package Office_NOC_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ONOC_Template_Helper {
	
	/**
	 * Get default template blocks (formal letter format)
	 */
	public static function get_default_template() {
		return array(
			array(
				'blockName' => 'core/paragraph',
				'attrs'     => array(),
				'innerContent' => array( '<strong>Date:</strong> {{issue_date}}' ),
			),
			array(
				'blockName' => 'core/spacer',
				'attrs'     => array( 'height' => 10 ),
			),
			array(
				'blockName' => 'core/paragraph',
				'attrs'     => array(),
				'innerContent' => array( 'To whom it may concern' ),
			),
			array(
				'blockName' => 'core/spacer',
				'attrs'     => array( 'height' => 10 ),
			),
			array(
				'blockName' => 'core/paragraph',
				'attrs'     => array(),
				'innerContent' => array( '<strong>Subject:</strong> Regarding No Objection Letter for {{full_name}}' ),
			),
			array(
				'blockName' => 'core/spacer',
				'attrs'     => array( 'height' => 10 ),
			),
			array(
				'blockName' => 'core/paragraph',
				'attrs'     => array(),
				'innerContent' => array( 'This letter is to confirm that <strong>{{full_name}}</strong> (Employee ID: <strong>{{employee_id}}</strong>) is an employee with our company on a full-time basis. He has been with {{company_name}} since {{joining_date}}. He is currently working as <strong>{{position}}</strong> at the <strong>{{department}}</strong> Department of {{company_name}}.' ),
			),
			array(
				'blockName' => 'core/spacer',
				'attrs'     => array( 'height' => 8 ),
			),
			array(
				'blockName' => 'core/paragraph',
				'attrs'     => array(),
				'innerContent' => array( 'Mr./Ms. {{full_name}} has expressed interest in visiting <strong>{{visiting_country}}</strong>, for {{purpose}}. Our organization has no objection regarding his/her visit to {{visiting_country}}, for <strong>{{number_of_days}}</strong> days. His/her leave for that trip has been sanctioned from <strong>{{leave_start}}</strong> to <strong>{{leave_end}}</strong>. On the expiry of consent, he/she will report for duty as soon as he/she returns.' ),
			),
			array(
				'blockName' => 'core/spacer',
				'attrs'     => array( 'height' => 8 ),
			),
			array(
				'blockName' => 'core/paragraph',
				'attrs'     => array(),
				'innerContent' => array( 'Please feel free to contact me if your office should require any further information.' ),
			),
			array(
				'blockName' => 'core/spacer',
				'attrs'     => array( 'height' => 20 ),
			),
			array(
				'blockName' => 'core/image',
				'attrs'     => array(
					'url' => '{{signature}}',
					'alt' => 'HR Signature',
					'align' => 'left',
					'width' => 100,
					'height' => 50,
				),
			),
			array(
				'blockName' => 'core/paragraph',
				'attrs'     => array(),
				'innerContent' => array( '{{hr_name}}<br/>{{hr_title}}<br/>{{company_name}}<br/>{{company_address}}<br/>Email: {{company_email}}<br/>Phone: {{company_phone}}</br>Reference ID: <strong>{{reference_id}}</strong>' ),
			),
			array(
				'blockName' => 'core/image',
				'attrs'     => array(
					'url' => '{{qr_code}}',
					'alt' => 'QR Code',
					'align' => 'center',
					'width' => 60,
					'height' => 60,
				),
			),
		);
	}
	
	/**
	 * Initialize default template if none exists
	 */
	public static function init_default_template() {
		$existing = get_option( 'noc_pdf_template', '' );
		if ( empty( $existing ) ) {
			$default = self::get_default_template();
			update_option( 'noc_pdf_template', wp_json_encode( $default ) );
		}
	}
}

