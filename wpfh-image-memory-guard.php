<?php
/**
 * Plugin Name:       WPFH Image Memory Guard
 * Plugin URI:        https://wpforgehub.com/plugins/wpfh-image-memory-guard/
 * Description:       Proactive memory protection for WordPress media library. Pre-calculates image memory budgets, prevents PHP fatal memory exhaustion, and enables safe auto-scaling.
 * Version:           1.0.0
 * Author:            WPForgeHub
 * Author URI:        https://wpforgehub.com
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wpfh-image-memory-guard
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPFH_IMG_VERSION', '1.0.0' );
define( 'WPFH_IMG_FILE', __FILE__ );
define( 'WPFH_IMG_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPFH_IMG_URL', plugin_dir_url( __FILE__ ) );

/**
 * Freemius SDK integration wrapper
 */
if ( ! function_exists( 'wpfh_img_fs' ) ) {
	function wpfh_img_fs() {
		global $wpfh_img_fs;

		if ( ! isset( $wpfh_img_fs ) ) {
			if ( file_exists( WPFH_IMG_PATH . 'freemius/start.php' ) ) {
				require_once WPFH_IMG_PATH . 'freemius/start.php';
			}

			if ( function_exists( 'fs_dynamic_init' ) ) {
				$wpfh_img_fs = fs_dynamic_init( array(
					'id'             => '17963',
					'slug'           => 'wpfh-image-memory-guard',
					'type'           => 'plugin',
					'public_key'     => 'pk_dummy_img_placeholder_key',
					'is_premium'     => false,
					'has_addons'     => false,
					'has_paid_plans' => true,
					'menu'           => array(
						'slug'       => 'wpfh-image-memory-guard',
						'first-path' => 'options-general.php?page=wpfh-image-memory-guard',
					),
				) );
			}
		}

		return $wpfh_img_fs;
	}

	if ( file_exists( WPFH_IMG_PATH . 'freemius/start.php' ) ) {
		wpfh_img_fs();
	}
}

/**
 * Main Plugin Class
 */
final class WPFH_Image_Memory_Guard {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ), 20 );
	}

	public function init() {
		load_plugin_textdomain( 'wpfh-image-memory-guard', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		require_once WPFH_IMG_PATH . 'includes/class-wpfh-img-guard.php';
		require_once WPFH_IMG_PATH . 'includes/class-wpfh-img-admin.php';

		WPFH_IMG_Guard::get_instance();
		WPFH_IMG_Admin::get_instance();
	}
}

WPFH_Image_Memory_Guard::get_instance();
