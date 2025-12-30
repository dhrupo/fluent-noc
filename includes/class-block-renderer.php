<?php
/**
 * Block renderer - converts Gutenberg blocks to HTML
 *
 * @package Office_NOC_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ONOC_Block_Renderer {
	
	/**
	 * Available placeholders
	 */
	private $placeholders = array();
	
	/**
	 * Constructor
	 */
	public function __construct() {
		// Placeholders will be set when rendering
	}
	
	/**
	 * Set placeholders data
	 */
	public function set_placeholders( $data ) {
		$signature_path = get_option( 'onoc_signature_image_path', '' );
		$signature_url = get_option( 'onoc_signature_image', '' );
		$signature = ! empty( $signature_path ) ? $signature_path : $signature_url;
		
		$company_logo = get_option( 'onoc_company_logo', '' );
		$number_of_days = 0;
		if ( ! empty( $data['leave_start'] ) && ! empty( $data['leave_end'] ) ) {
			$start = new DateTime( $data['leave_start'] );
			$end = new DateTime( $data['leave_end'] );
			$diff = $start->diff( $end );
			$number_of_days = $diff->days + 1; // Include both start and end days
		}
		
		$this->placeholders = array(
			'{{full_name}}'       => isset( $data['full_name'] ) ? esc_html( $data['full_name'] ) : '',
			'{{employee_id}}'     => isset( $data['employee_id'] ) ? esc_html( $data['employee_id'] ) : '',
			'{{email}}'           => isset( $data['email'] ) ? esc_html( $data['email'] ) : '',
			'{{reference_id}}'    => isset( $data['reference_id'] ) ? esc_html( $data['reference_id'] ) : '',
			'{{joining_date}}'     => isset( $data['joining_date'] ) ? $this->format_date( $data['joining_date'] ) : '',
			'{{position}}'        => isset( $data['position'] ) ? esc_html( $data['position'] ) : '',
			'{{department}}'      => isset( $data['department'] ) ? esc_html( $data['department'] ) : '',
			'{{visiting_country}}' => isset( $data['visiting_country'] ) ? esc_html( $data['visiting_country'] ) : '',
			'{{purpose}}'         => isset( $data['purpose'] ) ? esc_html( $data['purpose'] ) : '',
			'{{leave_start}}'     => isset( $data['leave_start'] ) ? $this->format_date( $data['leave_start'] ) : '',
			'{{leave_end}}'       => isset( $data['leave_end'] ) ? $this->format_date( $data['leave_end'] ) : '',
			'{{number_of_days}}'  => $number_of_days,
			'{{issue_date}}'     => date_i18n( get_option( 'date_format' ) ),
			'{{qr_code}}'         => isset( $data['qr_code'] ) ? $data['qr_code'] : '',
			'{{company_name}}'    => get_option( 'onoc_company_name', '' ),
			'{{company_logo}}'    => $this->get_image_for_pdf( $company_logo ),
			'{{signature}}'       => $this->get_image_for_pdf( $signature ),
			'{{company_address}}' => get_option( 'onoc_company_address', '' ),
			'{{company_phone}}'   => get_option( 'onoc_company_phone', '' ),
			'{{company_email}}'   => get_option( 'onoc_company_email', '' ),
			'{{hr_name}}'         => get_option( 'onoc_hr_name', '' ),
			'{{hr_title}}'        => get_option( 'onoc_hr_title', '' ),
		);
	}
	
	/**
	 * Render blocks to HTML
	 */
	public function render_blocks( $blocks_json ) {
		if ( empty( $blocks_json ) ) {
			return '';
		}
		
		// Parse JSON if it's a string
		if ( is_string( $blocks_json ) ) {
			$blocks = json_decode( $blocks_json, true );
		} else {
			$blocks = $blocks_json;
		}
		
		if ( ! is_array( $blocks ) ) {
			return '';
		}
		
		$html = '';
		foreach ( $blocks as $block ) {
			$html .= $this->render_block( $block );
		}
		
		return $html;
	}
	
	/**
	 * Render single block
	 */
	private function render_block( $block ) {
		if ( ! isset( $block['blockName'] ) ) {
			return '';
		}
		
		$block_name = $block['blockName'];
		$attributes = isset( $block['attrs'] ) ? $block['attrs'] : array();
		$inner_blocks = isset( $block['innerBlocks'] ) ? $block['innerBlocks'] : array();
		$inner_content = isset( $block['innerContent'] ) ? $block['innerContent'] : array();
		switch ( $block_name ) {
			case 'core/paragraph':
				return $this->render_paragraph( $attributes, $inner_content );
				
			case 'core/heading':
				return $this->render_heading( $attributes, $inner_content );
				
			case 'core/image':
				return $this->render_image( $attributes );
				
			case 'core/columns':
				return $this->render_columns( $attributes, $inner_blocks );
				
			case 'core/column':
				return $this->render_column( $attributes, $inner_blocks );
				
			case 'core/spacer':
				return $this->render_spacer( $attributes );
				
			case 'core/list':
				return $this->render_list( $attributes, $inner_content );
				
			case 'core/quote':
				return $this->render_quote( $attributes, $inner_content );
				
			case 'core/separator':
				return $this->render_separator();
				
			default:
				// Use WordPress render_block if available
				if ( function_exists( 'render_block' ) ) {
					$block_content = serialize_block( $block );
					$rendered = render_block( $block );
					return $this->replace_placeholders( $rendered );
				}
				return '';
		}
	}
	
	/**
	 * Render paragraph block
	 */
	private function render_paragraph( $attributes, $inner_content ) {
		// innerContent can be an array with strings and nulls (nulls are for inner blocks)
		// We need to extract all string content and join them
		$content = '';
		if ( is_array( $inner_content ) ) {
			foreach ( $inner_content as $item ) {
				if ( is_string( $item ) && ! empty( trim( $item ) ) ) {
					$content .= $item;
				}
			}
		} else {
			$content = isset( $inner_content[0] ) ? $inner_content[0] : '';
		}
		
		$content = $this->replace_placeholders( $content );
		$align = isset( $attributes['align'] ) ? ' align' . $attributes['align'] : '';
		$class = isset( $attributes['className'] ) ? ' ' . $attributes['className'] : '';
		
		return '<p class="' . esc_attr( trim( $align . $class ) ) . '">' . $content . '</p>';
	}
	
	/**
	 * Render heading block
	 */
	private function render_heading( $attributes, $inner_content ) {
		$level = isset( $attributes['level'] ) ? $attributes['level'] : 2;
		
		$content = '';
		if ( is_array( $inner_content ) ) {
			foreach ( $inner_content as $item ) {
				if ( is_string( $item ) && ! empty( trim( $item ) ) ) {
					$content .= $item;
				}
			}
		} else {
			$content = isset( $inner_content[0] ) ? $inner_content[0] : '';
		}
		
		$content = $this->replace_placeholders( $content );
		$align = isset( $attributes['align'] ) ? ' align' . $attributes['align'] : '';
		$class = isset( $attributes['className'] ) ? ' ' . $attributes['className'] : '';
		
		return '<h' . $level . ' class="' . esc_attr( trim( $align . $class ) ) . '">' . $content . '</h' . $level . '>';
	}
	
	/**
	 * Convert image URL to absolute URL or base64 for PDF
	 */
	private function get_image_for_pdf( $url ) {
		if ( empty( $url ) ) {
			return '';
		}
		
		// If it's a data URI (base64), return as is
		if ( strpos( $url, 'data:' ) === 0 ) {
			return $url;
		}
		
		$upload_dir = wp_upload_dir();
		$file_path = '';
		
		// If it's already a file path (not a URL), use it directly
		if ( file_exists( $url ) && is_file( $url ) ) {
			$file_path = $url;
		}
		// If it's an absolute URL (http/https), try to convert to file path
		elseif ( strpos( $url, 'http://' ) === 0 || strpos( $url, 'https://' ) === 0 ) {
			// If it's a WordPress upload URL, convert to file path
			if ( strpos( $url, $upload_dir['baseurl'] ) !== false ) {
				$file_path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $url );
			} else {
				// Extract the path from the URL
				$parsed_url = parse_url( $url );
				$url_path = isset( $parsed_url['path'] ) ? $parsed_url['path'] : '';
				
				// If it contains wp-content/uploads, try to construct path
				if ( strpos( $url_path, '/wp-content/uploads/' ) !== false ) {
					$file_path = ABSPATH . ltrim( $url_path, '/' );
				}
				// Try to extract path from domain-relative URL
				elseif ( ! empty( $url_path ) ) {
					$file_path = ABSPATH . ltrim( $url_path, '/' );
				}
			}
		}
		// If it's a WordPress upload URL (without http), convert to file path
		elseif ( strpos( $url, $upload_dir['baseurl'] ) !== false ) {
			$file_path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $url );
		}
		// If relative URL starting with /, try to find file
		elseif ( strpos( $url, '/' ) === 0 ) {
			$file_path = ABSPATH . ltrim( $url, '/' );
		}
		// If it contains wp-content/uploads, try to construct path
		elseif ( strpos( $url, 'wp-content/uploads' ) !== false ) {
			$file_path = ABSPATH . ltrim( $url, '/' );
		}
		
		// If file exists, convert to base64 (most reliable for PDFs)
		if ( ! empty( $file_path ) && file_exists( $file_path ) && is_readable( $file_path ) ) {
			$image_data = @file_get_contents( $file_path );
			if ( $image_data !== false ) {
				$image_info = @getimagesize( $file_path );
				if ( $image_info !== false ) {
					$mime_type = $image_info['mime'];
					$base64 = base64_encode( $image_data );
					return 'data:' . $mime_type . ';base64,' . $base64;
				} else {
					error_log( 'Office NOC Manager: Failed to get image info for: ' . $file_path );
				}
			} else {
				error_log( 'Office NOC Manager: Failed to read image file: ' . $file_path );
			}
		} elseif ( ! empty( $file_path ) ) {
			error_log( 'Office NOC Manager: Image file not found or not readable: ' . $file_path );
		}
		
		// Fallback: return as absolute URL (Dompdf can handle this with isRemoteEnabled)
		if ( strpos( $url, 'http://' ) === 0 || strpos( $url, 'https://' ) === 0 ) {
			return $url;
		}
		
		// Convert relative to absolute
		if ( strpos( $url, '/' ) === 0 ) {
			return home_url( $url );
		}
		
		// Try to make it absolute
		if ( strpos( $url, 'http' ) !== 0 ) {
			return home_url( '/' . ltrim( $url, '/' ) );
		}
		
		return $url;
	}
	
	/**
	 * Render image block
	 */
	private function render_image( $attributes ) {
		$url = isset( $attributes['url'] ) ? $attributes['url'] : '';
		$alt = isset( $attributes['alt'] ) ? $attributes['alt'] : '';
		$width = isset( $attributes['width'] ) ? $attributes['width'] : '';
		$height = isset( $attributes['height'] ) ? $attributes['height'] : '';
		$align = isset( $attributes['align'] ) ? ' align' . $attributes['align'] : '';
		$class = isset( $attributes['className'] ) ? ' ' . $attributes['className'] : '';
		
		if ( $url === '{{qr_code}}' || $url === '{{signature}}' || $url === '{{company_logo}}' ) {
			$url = isset( $this->placeholders[ $url ] ) ? $this->placeholders[ $url ] : '';
		}
		
		if ( strpos( $url, '{{' ) !== false ) {
			$url = $this->replace_placeholders( $url );
		}
		
		if ( empty( $url ) ) {
			return '';
		}
		
		if ( strpos( $url, 'data:' ) === 0 ) {
		} elseif ( strpos( $url, 'http://' ) === 0 || strpos( $url, 'https://' ) === 0 ) {
		} else {
			$converted_url = $this->get_image_for_pdf( $url );
			if ( ! empty( $converted_url ) ) {
				$url = $converted_url;
			}
		}
		
		if ( empty( $url ) ) {
			return '';
		}
		
		$figure_style = '';
		$img_style = '';
		if ( $width ) {
			$img_style .= 'width: ' . esc_attr( $width ) . 'px; ';
		}
		if ( $height ) {
			$img_style .= 'height: ' . esc_attr( $height ) . 'px; ';
		}
		
		return '<figure class="' . esc_attr( trim( $align . $class ) ) . '"' . ( $figure_style ? ' style="' . esc_attr( $figure_style ) . '"' : '' ) . '>
			<img src="' . esc_attr( $url ) . '" alt="' . esc_attr( $alt ) . '"' . ( $img_style ? ' style="' . esc_attr( $img_style ) . '"' : '' ) . ' />
		</figure>';
	}
	
	/**
	 * Render columns block
	 */
	private function render_columns( $attributes, $inner_blocks ) {
		$class = isset( $attributes['className'] ) ? ' ' . $attributes['className'] : '';
		$html = '<div class="wp-block-columns' . esc_attr( $class ) . '">';
		
		foreach ( $inner_blocks as $block ) {
			$html .= $this->render_block( $block );
		}
		
		$html .= '</div>';
		return $html;
	}
	
	/**
	 * Render column block
	 */
	private function render_column( $attributes, $inner_blocks ) {
		$width = isset( $attributes['width'] ) ? ' style="flex-basis: ' . esc_attr( $attributes['width'] ) . '%"' : '';
		$class = isset( $attributes['className'] ) ? ' ' . $attributes['className'] : '';
		$html = '<div class="wp-block-column' . esc_attr( $class ) . '"' . $width . '>';
		
		foreach ( $inner_blocks as $block ) {
			$html .= $this->render_block( $block );
		}
		
		$html .= '</div>';
		return $html;
	}
	
	/**
	 * Render spacer block
	 */
	private function render_spacer( $attributes ) {
		$height = isset( $attributes['height'] ) ? $attributes['height'] : 100;
		return '<div class="wp-block-spacer" style="height: ' . esc_attr( $height ) . 'px;"></div>';
	}
	
	/**
	 * Render list block
	 */
	private function render_list( $attributes, $inner_content ) {
		$ordered = isset( $attributes['ordered'] ) && $attributes['ordered'];
		$tag = $ordered ? 'ol' : 'ul';
		$class = isset( $attributes['className'] ) ? ' ' . $attributes['className'] : '';
		
		$html = '<' . $tag . ' class="wp-block-list' . esc_attr( $class ) . '">';
		foreach ( $inner_content as $item ) {
			if ( ! empty( trim( $item ) ) ) {
				$html .= '<li>' . $this->replace_placeholders( $item ) . '</li>';
			}
		}
		$html .= '</' . $tag . '>';
		
		return $html;
	}
	
	/**
	 * Render quote block
	 */
	private function render_quote( $attributes, $inner_content ) {
		// innerContent can be an array with strings and nulls
		$content = '';
		if ( is_array( $inner_content ) ) {
			foreach ( $inner_content as $item ) {
				if ( is_string( $item ) && ! empty( trim( $item ) ) ) {
					$content .= $item;
				}
			}
		} else {
			$content = isset( $inner_content[0] ) ? $inner_content[0] : '';
		}
		
		$content = $this->replace_placeholders( $content );
		$class = isset( $attributes['className'] ) ? ' ' . $attributes['className'] : '';
		
		return '<blockquote class="wp-block-quote' . esc_attr( $class ) . '">' . $content . '</blockquote>';
	}
	
	/**
	 * Render separator block
	 */
	private function render_separator() {
		return '<hr class="wp-block-separator" />';
	}
	
	/**
	 * Replace placeholders in content
	 * Only supports {{attr}} format
	 */
	private function replace_placeholders( $content ) {
		if ( empty( $this->placeholders ) || empty( $content ) || ! is_string( $content ) ) {
			return $content;
		}
		
		return str_replace( array_keys( $this->placeholders ), array_values( $this->placeholders ), $content );
	}
	
	/**
	 * Format date
	 */
	private function format_date( $date ) {
		if ( empty( $date ) ) {
			return '';
		}
		
		$timestamp = strtotime( $date );
		if ( $timestamp === false ) {
			return $date;
		}
		
		return date_i18n( get_option( 'date_format' ), $timestamp );
	}
}

