<?php
/**
 * Plugin Name:       WPFH Image Memory Guard
 * Plugin URI:        https://wpforgehub.com/plugins/wpfh-image-memory-guard/
 * Description:       Proactive memory protection for WordPress media library. Pre-calculates image memory budgets, prevents PHP fatal memory exhaustion, and enables safe auto-scaling.
 * Version:           1.1.0
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

define( 'WPFH_IMG_VERSION', '1.1.0' );
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
			} else {
				// Fallback object for Free / Filter-based Pro distribution
				$wpfh_img_fs = new class {
					public function can_use_premium_code() {
						return (bool) apply_filters( 'fs_can_use_premium_code__wpfh-image-memory-guard', false );
					}
					public function is__premium_only() { return false; }
					public function is_plan( $plan ) {
						return (bool) apply_filters( 'fs_is_plan__wpfh-image-memory-guard', false, $plan );
					}
					public function get_upgrade_url() {
						return 'https://wpforgehub.com/plugins/wpfh-image-memory-guard/#pricing';
					}
				};
			}
		}

		return $wpfh_img_fs;
	}

	wpfh_img_fs();
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

		// Pro Modules
		if ( function_exists( 'wpfh_img_fs' ) && wpfh_img_fs()->can_use_premium_code() ) {
			$pro_queue = WPFH_IMG_PATH . 'includes/pro/class-wpfh-img-queue.php';
			$pro_webp  = WPFH_IMG_PATH . 'includes/pro/class-wpfh-img-webp.php';
			$pro_exif  = WPFH_IMG_PATH . 'includes/pro/class-wpfh-img-exif.php';
			$pro_cli   = WPFH_IMG_PATH . 'includes/pro/class-wpfh-img-cli.php';

			if ( file_exists( $pro_queue ) ) {
				require_once $pro_queue;
				WPFH_IMG_Queue::get_instance();
			}
			if ( file_exists( $pro_webp ) ) {
				require_once $pro_webp;
				WPFH_IMG_WebP::get_instance();
			}
			if ( file_exists( $pro_exif ) ) {
				require_once $pro_exif;
				WPFH_IMG_EXIF::get_instance();
			}
			if ( file_exists( $pro_cli ) ) {
				require_once $pro_cli;
				WPFH_IMG_CLI::init();
			}
		}
	}
}

WPFH_Image_Memory_Guard::get_instance();
