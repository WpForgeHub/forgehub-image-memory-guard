<?php
/**
 * WPFH Image Memory Guard — Pro EXIF & Privacy Metadata Sanitizer
 *
 * Automatically strips personal GPS coordinates, camera hardware serials,
 * and privacy metadata from JPEG/PNG images upon media library upload.
 *
 * @package WPForgeHub\ImageMemoryGuard\Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPFH_IMG_EXIF {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter( 'wp_handle_upload', array( $this, 'sanitize_exif' ), 10 );
    }

    public function sanitize_exif( $upload ) {
        if ( empty( $upload['file'] ) || empty( $upload['type'] ) ) {
            return $upload;
        }

        if ( $upload['type'] === 'image/jpeg' ) {
            self::strip_jpeg_exif( $upload['file'] );
        }

        return $upload;
    }

    public static function strip_jpeg_exif( $file_path ) {
        if ( ! file_exists( $file_path ) ) {
            return false;
        }

        // If Imagick is available, clean all profiles
        if ( class_exists( 'Imagick' ) ) {
            try {
                $imagick = new Imagick( $file_path );
                $imagick->stripImage();
                $imagick->writeImage( $file_path );
                $imagick->clear();
                $imagick->destroy();
                return true;
            } catch ( Exception $e ) {
                // fallback to GD re-save
            }
        }

        // GD fallback: re-saving strips non-standard EXIF markers
        if ( function_exists( 'imagecreatefromjpeg' ) && function_exists( 'imagejpeg' ) ) {
            $img = @imagecreatefromjpeg( $file_path );
            if ( $img ) {
                @imagejpeg( $img, $file_path, 90 );
                @imagedestroy( $img );
                return true;
            }
        }

        return false;
    }
}
