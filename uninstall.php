<?php
/**
 * Image Memory Guard Uninstall
 *
 * @package WPFH_Image_Memory_Guard
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wpfh_img_auto_downscale' );
delete_option( 'wpfh_img_max_dimension' );
delete_option( 'wpfh_img_background_queue' );
delete_option( 'wpfh_img_review_nudge' );
wp_clear_scheduled_hook( 'wpfh_img_process_queue_batch' );
