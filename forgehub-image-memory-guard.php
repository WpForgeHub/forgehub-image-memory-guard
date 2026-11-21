<?php
/**
 * Plugin Name:       ForgeHub Image Memory Guard
 * Plugin URI:        https://wpforgehub.com/product/image-memory-guard/
 * Description:       Proactive memory protection for WordPress media library. Pre-calculates image memory budgets, prevents PHP fatal memory exhaustion, and enables safe auto-scaling.
 * Version:           1.1.7
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            WPForgeHub
 * Author URI:        https://wpforgehub.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       forgehub-image-memory-guard
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPFH_IMG_VERSION', '1.1.7' );
define( 'WPFH_IMG_FILE', __FILE__ );
define( 'WPFH_IMG_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPFH_IMG_URL', plugin_dir_url( __FILE__ ) );

// <wpfh-pro-only>
/**
 * Extensible helper wrapper
 */
if ( ! function_exists( 'wpfh_img_fs' ) ) {
	function wpfh_img_fs() {
		global $wpfh_img_fs;

		if ( ! isset( $wpfh_img_fs ) ) {
			$fs_path = WPFH_IMG_PATH . 'freemius/start.php';
			if ( file_exists( $fs_path ) ) {
				require_once $fs_path;
				$wpfh_img_fs = fs_dynamic_init( array(
					'id'             => '45083',
					'slug'           => 'wpfh-image-memory-guard',
					'type'           => 'plugin',
					'public_key'     => 'pk_c3faa2866670be0e103e2a06f97c7',
					'is_premium'     => false,
					'has_addons'     => false,
					'has_paid_plans' => true,
					'menu'           => array(
						'slug'       => 'forgehub-image-memory-guard',
						'first-path' => 'options-general.php?page=forgehub-image-memory-guard',
						'parent'     => array(
							'slug' => 'options-general.php',
						),
					),
				) );
				// Freemius forbids uninstall.php in premium builds; use its after_uninstall hook there.
				$wpfh_img_fs->add_action( 'after_uninstall', 'wpfh_img_uninstall_cleanup' );
			} else {
				// Clean fallback for standard WordPress distribution
				$wpfh_img_fs = new class {
					public function can_use_premium_code() {
						// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
						return (bool) apply_filters( 'fs_can_use_premium_code__wpfh-image-memory-guard', false );
					}
					public function is__premium_only() { return false; }
					public function is_plan( $plan ) {
						// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
						return (bool) apply_filters( 'fs_is_plan__wpfh-image-memory-guard', false, $plan );
					}
					public function get_upgrade_url() {
						return 'https://wpforgehub.com/product/image-memory-guard/';
					}
				};
			}
		}

		return $wpfh_img_fs;
	}

	wpfh_img_fs();
}

/**
 * Removes plugin options (Freemius premium build; free build uses uninstall.php).
 */
function wpfh_img_uninstall_cleanup() {
	delete_option( 'wpfh_img_auto_downscale' );
	delete_option( 'wpfh_img_max_dimension' );
	delete_option( 'wpfh_img_background_queue' );
	wp_clear_scheduled_hook( 'wpfh_img_process_queue_batch' );
}
// </wpfh-pro-only>

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
		// Note: load_plugin_textdomain is intentionally omitted as WordPress.org automatically loads translations since WP 4.6.

		require_once WPFH_IMG_PATH . 'includes/class-wpfh-img-guard.php';
		require_once WPFH_IMG_PATH . 'includes/class-wpfh-img-admin.php';

		WPFH_IMG_Guard::get_instance();
		WPFH_IMG_Admin::get_instance();

		// <wpfh-pro-only>
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
		// </wpfh-pro-only>
	}
}

WPFH_Image_Memory_Guard::get_instance();
