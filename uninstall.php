<?php
/**
 * WPFH Image Memory Guard Uninstall
 *
 * @package WPFH_Image_Memory_Guard
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wpfh_img_auto_downscale' );
delete_option( 'wpfh_img_max_dimension' );
