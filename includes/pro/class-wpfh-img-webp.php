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
            self::convert_to_avif( $upload['file'] );
        }

        return $upload;
    }

    public static function convert_to_avif( $file_path, $quality = 75 ) {
        if ( ! file_exists( $file_path ) ) {
            return false;
        }

        $info = @getimagesize( $file_path );
        if ( ! $info ) {
            return false;
        }

        $mime      = $info['mime'];
        $avif_path = preg_replace( '/\.(jpe?g|png)$/i', '.avif', $file_path );
        if ( $avif_path === $file_path ) {
            return false;
        }

        if ( file_exists( $avif_path ) && filemtime( $avif_path ) >= filemtime( $file_path ) ) {
            return $avif_path;
        }

        // Prefer GD imageavif() on PHP 8.1+ when available.
        if ( function_exists( 'imageavif' ) ) {
            $image = null;
            if ( 'image/jpeg' === $mime && function_exists( 'imagecreatefromjpeg' ) ) {
                $image = @imagecreatefromjpeg( $file_path );
            } elseif ( 'image/png' === $mime && function_exists( 'imagecreatefrompng' ) ) {
                $image = @imagecreatefrompng( $file_path );
                if ( $image ) {
                    imagepalettetotruecolor( $image );
                    imagealphablending( $image, true );
                    imagesavealpha( $image, true );
                }
            }

            if ( $image ) {
                $success = @imageavif( $image, $avif_path, $quality );
                imagedestroy( $image );
                usleep( 50000 );
                if ( $success && file_exists( $avif_path ) ) {
                    return $avif_path;
                }
            }
        }

        // Fallback to Imagick if compiled with AVIF support.
        if ( class_exists( 'Imagick' ) && method_exists( 'Imagick', 'queryFormats' ) ) {
            $formats = Imagick::queryFormats( 'AVIF' );
            if ( ! empty( $formats ) ) {
                try {
                    $imagick = new Imagick( $file_path );
                    $imagick->setImageFormat( 'avif' );
                    $imagick->setImageCompressionQuality( $quality );
                    $imagick->writeImage( $avif_path );
                    $imagick->clear();
                    $imagick->destroy();
                    usleep( 50000 );
                    return $avif_path;
                } catch ( Exception $e ) {
                    return false;
                }
            }
        }

        return false;
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
