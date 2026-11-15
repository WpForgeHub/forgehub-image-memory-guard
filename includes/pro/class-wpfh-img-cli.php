<?php
/**
 * WPFH Image Memory Guard — Pro WP-CLI Media Library Audit Tool
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
        WP_CLI::line( 'Queueing batch WebP conversion...' );
        if ( class_exists( 'WPFH_IMG_Queue' ) ) {
            WPFH_IMG_Queue::get_instance()->process_batch();
            WP_CLI::success( 'Batch WebP processing executed successfully.' );
        }
    }
}
