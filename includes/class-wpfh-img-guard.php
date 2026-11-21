<?php
/**
 * Image Memory Guard Core Engine
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
		$value = trim( (string) $value );
		if ( '-1' === $value ) {
			return -1;
		}
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

		$width    = (int) $image_info[0];
		$height   = (int) $image_info[1];
		$channels = isset( $image_info['channels'] ) ? (int) $image_info['channels'] : 4;
		$bpp      = isset( $image_info['bits'] ) ? (int) $image_info['bits'] : 8;

		// Estimated decompression canvas memory + working buffer (1.8x factor)
		$required_bytes = ( $width * $height * $channels * ( $bpp / 8 ) * 1.8 );
		$current_usage  = memory_get_usage( true );
		$total_needed   = $current_usage + $required_bytes;

		$memory_limit_str = ini_get( 'memory_limit' );
		$memory_limit     = self::shorthand_to_bytes( $memory_limit_str );
		$auto_downscale   = get_option( 'wpfh_img_auto_downscale', 'yes' );
		$max_dim          = max( 320, absint( get_option( 'wpfh_img_max_dimension', 2560 ) ) );

		// -1 means unlimited
		$exceeds_budget = ( $memory_limit > 0 && $total_needed > ( $memory_limit * 0.90 ) );

		if ( $exceeds_budget ) {
			// NEVER load an over-budget image at full resolution into GD/wp_get_image_editor() — that triggers the exact OOM Fatal Error we are preventing.
			// Only Imagick's JPEG DCT shrink-on-load ('jpeg:size' hint set BEFORE readImage) can safely decode at reduced memory.
			if ( 'yes' === $auto_downscale && 'image/jpeg' === $type && class_exists( 'Imagick' ) ) {
				try {
					$imagick = new Imagick();
					$imagick->setOption( 'jpeg:size', $max_dim . 'x' . $max_dim );
					$imagick->readImage( $tmp_name );
					if ( method_exists( $imagick, 'autoOrient' ) ) {
						$imagick->autoOrient();
					}
					$imagick->thumbnailImage( $max_dim, $max_dim, true );
					$imagick->setImageCompressionQuality( 85 );
					$imagick->writeImage( $tmp_name );
					$imagick->clear();
					$imagick->destroy();
					return $file;
				} catch ( Exception $e ) {
					// Fall through to graceful rejection below.
				}
			}

			$needed_mb = round( $total_needed / 1024 / 1024, 1 );
			$limit_mb  = round( $memory_limit / 1024 / 1024, 1 );

			// Graceful error notification instead of fatal white screen
			$file['error'] = sprintf(
				/* translators: 1: Required memory in megabytes, 2: Server memory limit in megabytes. */
				__( 'Upload prevented by Image Memory Guard: This image requires %1$s MB memory to decompress, but your server memory limit is %2$s MB. Please resize the image before uploading.', 'forgehub-image-memory-guard' ),
				$needed_mb,
				$limit_mb
			);
			return $file;
		}

		// Safe proactive downscale when within memory budget and image exceeds configured max dimension.
		if ( 'yes' === $auto_downscale && ( $width > $max_dim || $height > $max_dim ) && function_exists( 'wp_get_image_editor' ) ) {
			$editor = wp_get_image_editor( $tmp_name );
			if ( ! is_wp_error( $editor ) ) {
				$resized = $editor->resize( $max_dim, $max_dim, false );
				if ( ! is_wp_error( $resized ) ) {
					$saved = $editor->save( $tmp_name, $type );
					if ( ! is_wp_error( $saved ) && ! empty( $saved['path'] ) && $saved['path'] !== $tmp_name && file_exists( $saved['path'] ) ) {
						@rename( $saved['path'], $tmp_name );
					}
				}
			}
		}

		return $file;
	}

	/**
	 * Dynamically elevate image memory limit to 512M if possible (never downgrade unlimited -1).
	 */
	public function increase_image_memory_limit( $limit ) {
		$current_bytes = self::shorthand_to_bytes( $limit );
		if ( $current_bytes < 0 ) {
			return $limit;
		}

		$target_limit = '512M';
		$target_bytes = self::shorthand_to_bytes( $target_limit );

		if ( $current_bytes < $target_bytes ) {
			// WordPress core (wp_raise_memory_limit) applies the returned value itself.
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
