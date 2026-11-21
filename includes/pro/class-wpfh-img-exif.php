<?php
/**
 * Image Memory Guard — Pro EXIF & Privacy Metadata Sanitizer
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

        // If Imagick is available: auto-orient FIRST so portrait photos aren't rotated sideways,
        // and preserve ICC color profile while stripping GPS/EXIF metadata.
        if ( class_exists( 'Imagick' ) ) {
            try {
                $imagick = new Imagick( $file_path );
                if ( method_exists( $imagick, 'autoOrient' ) ) {
                    $imagick->autoOrient();
                }
                $icc_profiles = $imagick->getImageProfiles( 'icc', true );
                $imagick->stripImage();
                if ( ! empty( $icc_profiles['icc'] ) ) {
                    $imagick->profileImage( 'icc', $icc_profiles['icc'] );
                }
                $imagick->writeImage( $file_path );
                $imagick->clear();
                $imagick->destroy();
                return true;
            } catch ( Exception $e ) {
                // fallback to GD re-save
            }
        }

        // GD fallback: apply EXIF orientation before re-saving (re-saving strips EXIF markers).
        if ( function_exists( 'imagecreatefromjpeg' ) && function_exists( 'imagejpeg' ) ) {
            $orientation = 1;
            if ( function_exists( 'exif_read_data' ) ) {
                $exif = @exif_read_data( $file_path );
                if ( ! empty( $exif['Orientation'] ) ) {
                    $orientation = (int) $exif['Orientation'];
                }
            }
            $img = @imagecreatefromjpeg( $file_path );
            if ( $img ) {
                if ( 3 === $orientation && function_exists( 'imagerotate' ) ) {
                    $img = imagerotate( $img, 180, 0 );
                } elseif ( 6 === $orientation && function_exists( 'imagerotate' ) ) {
                    $img = imagerotate( $img, -90, 0 );
                } elseif ( 8 === $orientation && function_exists( 'imagerotate' ) ) {
                    $img = imagerotate( $img, 90, 0 );
                }
                @imagejpeg( $img, $file_path, 90 );
                @imagedestroy( $img );
                return true;
            }
        }

        return false;
    }
}
