<?php
/**
 * WPFH Image Memory Guard Admin Settings & Diagnostics
 *
 * @package WPFH_Image_Memory_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPFH_IMG_Admin {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WPFH_IMG_FILE ), array( $this, 'add_action_links' ) );
	}

	public function add_menu_page() {
		add_options_page(
			__( 'Image Memory Guard', 'wpfh-image-memory-guard' ),
			__( 'Image Memory Guard', 'wpfh-image-memory-guard' ),
			'manage_options',
			'wpfh-image-memory-guard',
			array( $this, 'render_admin_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'wpfh_img_settings',
			'wpfh_img_auto_downscale',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'yes',
			)
		);
		register_setting(
			'wpfh_img_settings',
			'wpfh_img_max_dimension',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 2560,
			)
		);
	}

	public function add_action_links( $links ) {
		$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=wpfh-image-memory-guard' ) ) . '">' . __( 'Diagnostics', 'wpfh-image-memory-guard' ) . '</a>';
		$pro_link      = '<a href="https://wpforgehub.com/plugins/wpfh-image-memory-guard/" style="color:#10b981;font-weight:700;" target="_blank">' . __( 'Upgrade to Pro', 'wpfh-image-memory-guard' ) . '</a>';
		array_unshift( $links, $settings_link, $pro_link );
		return $links;
	}

	public function enqueue_assets( $hook ) {
		if ( 'settings_page_wpfh-image-memory-guard' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'wpfh-img-admin',
			WPFH_IMG_URL . 'assets/css/admin.css',
			array(),
			WPFH_IMG_VERSION
		);

		wp_enqueue_script(
			'wpfh-img-admin',
			WPFH_IMG_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			WPFH_IMG_VERSION,
			true
		);
	}

	public function render_admin_page() {
		$memory_limit   = ini_get( 'memory_limit' );
		$limit_bytes    = WPFH_IMG_Guard::shorthand_to_bytes( $memory_limit );
		$current_bytes  = memory_get_usage( true );
		$peak_bytes     = memory_get_peak_usage( true );
		$current_mb     = round( $current_bytes / 1024 / 1024, 1 );
		$peak_mb        = round( $peak_bytes / 1024 / 1024, 1 );
		$limit_mb       = round( $limit_bytes / 1024 / 1024, 1 );

		$engine = extension_loaded( 'imagick' ) && class_exists( 'Imagick' ) ? 'ImageMagick (Imagick)' : 'GD Library';

		// Calculate approximate max safe resolution (assuming RGBA 4 bytes * 1.8 overhead)
		$available_decomp_bytes = ( $limit_bytes * 0.75 ) - $current_bytes;
		$max_pixels             = max( 0, $available_decomp_bytes / ( 4 * 1.8 ) );
		$max_dim                = floor( sqrt( $max_pixels ) );
		?>
		<div class="wrap wpfh-img-wrap">
			<h1>🛡️ <?php esc_html_e( 'WPFH Image Memory Guard', 'wpfh-image-memory-guard' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Proactive memory safety diagnostics and decompression budget calculator.', 'wpfh-image-memory-guard' ); ?></p>

			<!-- Diagnostics Grid -->
			<div class="wpfh-stat-grid">
				<div class="wpfh-stat-card">
					<div class="wpfh-stat-label"><?php esc_html_e( 'PHP Memory Limit', 'wpfh-image-memory-guard' ); ?></div>
					<div class="wpfh-stat-value highlight"><?php echo esc_html( $memory_limit ); ?></div>
					<div class="wpfh-stat-desc"><?php echo esc_html( $limit_mb ); ?> MB Total Available</div>
				</div>
				<div class="wpfh-stat-card">
					<div class="wpfh-stat-label"><?php esc_html_e( 'Peak Memory Used', 'wpfh-image-memory-guard' ); ?></div>
					<div class="wpfh-stat-value"><?php echo esc_html( $peak_mb ); ?> MB</div>
					<div class="wpfh-stat-desc"><?php echo esc_html( $current_mb ); ?> MB Current Baseline</div>
				</div>
				<div class="wpfh-stat-card">
					<div class="wpfh-stat-label"><?php esc_html_e( 'Active Image Engine', 'wpfh-image-memory-guard' ); ?></div>
					<div class="wpfh-stat-value"><?php echo esc_html( $engine ); ?></div>
					<div class="wpfh-stat-desc"><?php esc_html_e( 'PHP Graphics Driver', 'wpfh-image-memory-guard' ); ?></div>
				</div>
				<div class="wpfh-stat-card">
					<div class="wpfh-stat-label"><?php esc_html_e( 'Max Safe Upload Resolution', 'wpfh-image-memory-guard' ); ?></div>
					<div class="wpfh-stat-value highlight-green"><?php echo esc_html( "{$max_dim} × {$max_dim} px" ); ?></div>
					<div class="wpfh-stat-desc"><?php esc_html_e( 'Safe Decompression Budget', 'wpfh-image-memory-guard' ); ?></div>
				</div>
			</div>

			<!-- Live Budget Calculator -->
			<div class="wpfh-calc-box">
				<h3>🧮 <?php esc_html_e( 'Interactive Memory Budget Calculator', 'wpfh-image-memory-guard' ); ?></h3>
				<p><?php esc_html_e( 'Enter dimensions of an image to test whether it would exceed server memory before uploading.', 'wpfh-image-memory-guard' ); ?></p>
				<div class="wpfh-calc-form">
					<div class="wpfh-calc-field">
						<label><?php esc_html_e( 'Width (px):', 'wpfh-image-memory-guard' ); ?></label>
						<input type="number" id="calc_width" value="4000" min="100" max="20000" />
					</div>
					<div class="wpfh-calc-field">
						<label><?php esc_html_e( 'Height (px):', 'wpfh-image-memory-guard' ); ?></label>
						<input type="number" id="calc_height" value="3000" min="100" max="20000" />
					</div>
					<button type="button" id="btn_calculate_budget" class="button button-primary"><?php esc_html_e( 'Calculate Memory Budget', 'wpfh-image-memory-guard' ); ?></button>
				</div>
				<div id="calc_result" class="wpfh-calc-result" data-limit-mb="<?php echo esc_attr( $limit_mb ); ?>" data-current-mb="<?php echo esc_attr( $current_mb ); ?>">
					<!-- Calculated via JS -->
				</div>
			</div>

			<!-- Pro Upsell -->
			<div class="wpfh-pro-card" style="background:#0f172a;border:1px solid #1e293b;border-radius:12px;padding:24px;margin-top:28px;color:#e2e8f0;max-width:850px;">
				<div style="display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #334155;padding-bottom:16px;margin-bottom:16px;">
					<div>
						<h3 style="margin:0;font-size:18px;color:#f8fafc;">🚀 WPFH Image Memory Guard Pro</h3>
						<p style="margin:4px 0 0 0;color:#94a3b8;font-size:13px;">Enterprise background batch resizing and WebP/AVIF generation.</p>
					</div>
					<a href="https://wpforgehub.com/plugins/wpfh-image-memory-guard/" target="_blank" style="background:#10b981;color:#ffffff;padding:8px 16px;border-radius:6px;text-decoration:none;font-weight:700;font-size:13px;display:inline-block;">Upgrade to Pro &rarr;</a>
				</div>
				<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;font-size:13px;">
					<div style="background:#1e293b;padding:12px 16px;border-radius:8px;">
						<strong style="color:#38bdf8;">🔄 Background Chunked Resizing</strong>
						<p style="margin:4px 0 0 0;color:#cbd5e1;">Offload huge thumbnail generation to Action Scheduler queues with 0 frontend slowdown.</p>
					</div>
					<div style="background:#1e293b;padding:12px 16px;border-radius:8px;">
						<strong style="color:#38bdf8;">⚡ WebP & AVIF Dual Engine</strong>
						<p style="margin:4px 0 0 0;color:#cbd5e1;">Automatically generates next-gen WebP & AVIF copies with CPU throttling safeguards.</p>
					</div>
					<div style="background:#1e293b;padding:12px 16px;border-radius:8px;">
						<strong style="color:#38bdf8;">💻 WP-CLI Image Audit</strong>
						<p style="margin:4px 0 0 0;color:#cbd5e1;">Command line tools to scan your entire media library for memory-hog attachments.</p>
					</div>
					<div style="background:#1e293b;padding:12px 16px;border-radius:8px;">
						<strong style="color:#38bdf8;">🛡️ Automatic EXIF Sanitizer</strong>
						<p style="margin:4px 0 0 0;color:#cbd5e1;">Strips privacy-sensitive GPS and camera metadata before canvas decompression.</p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
