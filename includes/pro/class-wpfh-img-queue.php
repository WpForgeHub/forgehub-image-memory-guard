<?php
/**
 * Image Memory Guard — Pro Background Chunked Processing Queue
 *
 * Implements Action Scheduler / Cron based asynchronous image processing
 * to handle mass media optimizations without hitting PHP execution timeouts.
 *
 * @package WPForgeHub\ImageMemoryGuard\Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPFH_IMG_Queue {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wpfh_img_process_queue_batch', array( $this, 'process_batch' ) );
        add_action( 'wp_ajax_wpfh_img_start_batch_queue', array( $this, 'ajax_start_queue' ) );
    }

    public static function enqueue_attachment( $attachment_id ) {
        $queue = get_option( 'wpfh_img_background_queue', array() );
        if ( ! in_array( $attachment_id, $queue, true ) ) {
            $queue[] = absint( $attachment_id );
            update_option( 'wpfh_img_background_queue', $queue, false );
        }

        if ( ! wp_next_scheduled( 'wpfh_img_process_queue_batch' ) ) {
            wp_schedule_single_event( time() + 10, 'wpfh_img_process_queue_batch' );
        }
    }

    public function process_batch() {
        $queue = get_option( 'wpfh_img_background_queue', array() );
        if ( empty( $queue ) ) {
            return;
        }

        $chunk_size = 5;
        $batch = array_splice( $queue, 0, $chunk_size );

        foreach ( $batch as $attachment_id ) {
            $file = get_attached_file( $attachment_id );
            if ( $file && file_exists( $file ) ) {
                // Apply WebP conversion or resizing if enabled
                if ( class_exists( 'WPFH_IMG_WebP' ) ) {
                    WPFH_IMG_WebP::convert_to_webp( $file );
                }
            }
        }

        update_option( 'wpfh_img_background_queue', $queue, false );

        // If items remain in queue, schedule next batch
        if ( ! empty( $queue ) ) {
            wp_schedule_single_event( time() + 15, 'wpfh_img_process_queue_batch' );
        }
    }

    public function ajax_start_queue() {
        check_ajax_referer( 'wpfh_img_admin_nonce' );

        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        $attachments = get_posts( array(
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'inherit',
            'posts_per_page' => 50,
            'fields'         => 'ids',
        ) );

        update_option( 'wpfh_img_background_queue', $attachments, false );
        if ( ! wp_next_scheduled( 'wpfh_img_process_queue_batch' ) ) {
            wp_schedule_single_event( time() + 5, 'wpfh_img_process_queue_batch' );
        }

        wp_send_json_success( array( 'queued_count' => count( $attachments ) ) );
    }
}
