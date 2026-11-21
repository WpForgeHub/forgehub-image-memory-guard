<?php
/**
 * Image Memory Guard — Pro WP-CLI Media Library Audit Tool
 *
 * Provides command line commands:
 * `wp image-guard audit` - Scans media library for high-memory risk images
 * `wp image-guard regenerate` - Batches WebP generation via CLI
 *
 * @package WPForgeHub\ImageMemoryGuard\Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPFH_IMG_CLI {

    public static function init() {
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::add_command( 'image-guard audit', array( __CLASS__, 'audit' ) );
            WP_CLI::add_command( 'image-guard regenerate', array( __CLASS__, 'regenerate' ) );
        }
    }

    public static function audit( $args, $assoc_args ) {
        WP_CLI::line( 'Scanning WordPress Media Library for memory-risky images...' );
        $attachments = get_posts( array(
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'inherit',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ) );

        $risky = 0;
        foreach ( $attachments as $id ) {
            $meta = wp_get_attachment_metadata( $id );
            $w = $meta['width'] ?? 0;
            $h = $meta['height'] ?? 0;
            $budget_mb = ( $w * $h * 4 * 1.8 ) / ( 1024 * 1024 );

            if ( $budget_mb > 64 ) {
                $risky++;
                WP_CLI::warning( sprintf( 'Attachment #%d (%dx%d) requires ~%.1f MB decompressed memory', $id, $w, $h, $budget_mb ) );
            }
        }

        WP_CLI::success( sprintf( 'Audit complete. Scanned %d images. Found %d memory-intensive candidates.', count( $attachments ), $risky ) );
    }

    public static function regenerate( $args, $assoc_args ) {
        if ( ! class_exists( 'WPFH_IMG_Queue' ) ) {
            WP_CLI::error( 'Queue engine is not loaded.' );
        }

        $queue = get_option( 'wpfh_img_background_queue', array() );
        if ( empty( $queue ) ) {
            WP_CLI::line( 'Queue is empty — scanning Media Library for image attachments...' );
            $queue = get_posts( array(
                'post_type'      => 'attachment',
                'post_mime_type' => 'image',
                'post_status'    => 'inherit',
                'posts_per_page' => -1,
                'fields'         => 'ids',
            ) );
            update_option( 'wpfh_img_background_queue', $queue, false );
        }

        $total = count( $queue );
        if ( 0 === $total ) {
            WP_CLI::success( 'No image attachments found to process.' );
            return;
        }

        WP_CLI::line( sprintf( 'Processing %d image attachments for WebP/AVIF conversion...', $total ) );
        $runner = WPFH_IMG_Queue::get_instance();
        $guard  = 0;
        while ( ! empty( get_option( 'wpfh_img_background_queue', array() ) ) && $guard < 10000 ) {
            $runner->process_batch();
            $guard++;
        }

        WP_CLI::success( sprintf( 'Batch WebP/AVIF processing completed for %d images.', $total ) );
    }
}
