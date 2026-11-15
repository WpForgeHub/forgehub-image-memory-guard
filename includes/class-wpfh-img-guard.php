<?php
/**
 * WPFH Image Memory Guard Core Engine
 *
 * @package WPFH_Image_Memory_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPFH_IMG_Guard {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'prefilter_upload_memory' ) );
		add_filter( 'image_memory_limit', array( $this, 'increase_image_memory_limit' ) );
		add_action( 'wp_generate_attachment_metadata', array( $this, 'cleanup_memory_after_processing' ), 99 );
	}

	/**
	 * Convert PHP shorthand bytes (e.g. "256M") to integer bytes
	 */
	public static function shorthand_to_bytes( $value ) {
		$value = trim( $value );
		$last  = strtolower( substr( $value, -1 ) );
		$val   = floatval( $value );

		switch ( $last ) {
			case 'g':
				$val *= 1024 * 1024 * 1024;
				break;
			case 'm':
				$val *= 1024 * 1024;
				break;
			case 'k':
				$val *= 1024;
				break;
		}

		return intval( $val );
	}

	/**
	 * Pre-calculate memory required to process an uploaded image
	 */
	public function prefilter_upload_memory( $file ) {
		// Only check raster images (JPEG, PNG, WebP)
		$type = isset( $file['type'] ) ? $file['type'] : '';
		if ( ! in_array( $type, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
			return $file;
		}

		$tmp_name = isset( $file['tmp_name'] ) ? $file['tmp_name'] : '';
		if ( empty( $tmp_name ) || ! file_exists( $tmp_name ) ) {
			return $file;
		}

		// Read header without loading image into GD/Imagick memory
		$image_info = @getimagesize( $tmp_name );
		if ( ! $image_info ) {
			return $file;
		}

		$width    = $image_info[0];
		$height   = $image_info[1];
		$channels = isset( $image_info['channels'] ) ? $image_info['channels'] : 4;
		$bpp      = isset( $image_info['bits'] ) ? $image_info['bits'] : 8;

		// Estimated decompression canvas memory + working buffer (1.8x factor)
		$required_bytes = ( $width * $height * $channels * ( $bpp / 8 ) * 1.8 );
		$current_usage  = memory_get_usage( true );
		$total_needed   = $current_usage + $required_bytes;

		$memory_limit_str = ini_get( 'memory_limit' );
		$memory_limit     = self::shorthand_to_bytes( $memory_limit_str );

		// -1 means unlimited
		if ( $memory_limit > 0 ) {
			$safety_threshold = $memory_limit * 0.90; // 90% budget cap

			if ( $total_needed > $safety_threshold ) {
				$needed_mb = round( $total_needed / 1024 / 1024, 1 );
				$limit_mb  = round( $memory_limit / 1024 / 1024, 1 );

				// Check if auto-scale is allowed or return graceful rejection
				$auto_downscale = get_option( 'wpfh_img_auto_downscale', 'yes' );
				if ( 'yes' === $auto_downscale && function_exists( 'wp_get_image_editor' ) ) {
					// Attempt safe editor downscale
					$editor = wp_get_image_editor( $tmp_name );
					if ( ! is_wp_error( $editor ) ) {
						// Scale down to max 2560px
						$editor->resize( 2560, 2560 );
						$editor->save( $tmp_name );
						return $file;
					}
				}

				// Graceful error notification instead of fatal white screen
				$file['error'] = sprintf(
					__( 'Upload prevented by Image Memory Guard: This image requires %1$s MB memory to decompress, but your server memory limit is %2$s MB. Please resize the image before uploading.', 'wpfh-image-memory-guard' ),
					$needed_mb,
					$limit_mb
				);
			}
		}

		return $file;
	}

	/**
	 * Dynamically elevate image memory limit to 512M if possible
	 */
	public function increase_image_memory_limit( $limit ) {
		$target_limit = '512M';
		$target_bytes = self::shorthand_to_bytes( $target_limit );
		$current_bytes= self::shorthand_to_bytes( $limit );

		if ( $current_bytes < $target_bytes ) {
			@ini_set( 'memory_limit', $target_limit );
			return $target_limit;
		}

		return $limit;
	}

	/**
	 * Force garbage collection after intensive thumbnail generation
	 */
	public function cleanup_memory_after_processing( $metadata ) {
		if ( function_exists( 'gc_collect_cycles' ) ) {
			gc_collect_cycles();
		}
		return $metadata;
	}
}
