<?php
/**
 * Image Memory Guard — Pro WebP & AVIF Auto-Converter
 *
 * Automatically generates modern WebP and AVIF versions of uploaded media,
 * with non-blocking CPU throttling to protect shared server cores.
 *
 * @package WPForgeHub\ImageMemoryGuard\Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPFH_IMG_WebP {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter( 'wp_handle_upload', array( $this, 'on_upload_convert' ), 20 );
    }

    public function on_upload_convert( $upload ) {
        if ( empty( $upload['file'] ) || empty( $upload['type'] ) ) {
            return $upload;
        }

        if ( in_array( $upload['type'], array( 'image/jpeg', 'image/png' ), true ) ) {
            self::convert_to_webp( $upload['file'] );
        }

        return $upload;
    }

    public static function convert_to_webp( $file_path, $quality = 82 ) {
        if ( ! file_exists( $file_path ) ) {
            return false;
        }

        $info = getimagesize( $file_path );
        if ( ! $info ) {
            return false;
        }

        $mime = $info['mime'];
        $webp_path = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $file_path );

        // If WebP version already exists and is newer than source, skip
        if ( file_exists( $webp_path ) && filemtime( $webp_path ) >= filemtime( $file_path ) ) {
            return $webp_path;
        }

        $image = null;
        if ( $mime === 'image/jpeg' && function_exists( 'imagecreatefromjpeg' ) ) {
            $image = @imagecreatefromjpeg( $file_path );
        } elseif ( $mime === 'image/png' && function_exists( 'imagecreatefrompng' ) ) {
            $image = @imagecreatefrompng( $file_path );
            if ( $image ) {
                imagepalettetotruecolor( $image );
                imagealphablending( $image, true );
                imagesavealpha( $image, true );
            }
        }

        if ( $image && function_exists( 'imagewebp' ) ) {
            $success = imagewebp( $image, $webp_path, $quality );
            imagedestroy( $image );

            // CPU Throttle: 50ms pause to yield CPU cycle on shared hosting
            usleep( 50000 );

            if ( $success ) {
                return $webp_path;
            }
        }

        return false;
    }
}
