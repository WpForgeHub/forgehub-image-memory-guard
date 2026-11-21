<?php
/**
 * Image Memory Guard Admin Settings & Diagnostics
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
			__( 'Image Memory Guard', 'forgehub-image-memory-guard' ),
			__( 'Image Memory Guard', 'forgehub-image-memory-guard' ),
			'manage_options',
			'forgehub-image-memory-guard',
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
		$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=forgehub-image-memory-guard' ) ) . '">' . esc_html__( 'Settings', 'forgehub-image-memory-guard' ) . '</a>';
		$is_pro        = function_exists( 'wpfh_img_fs' ) && wpfh_img_fs()->can_use_premium_code();
		if ( ! $is_pro ) {
			$pro_link = '<a href="https://wpforgehub.com/product/image-memory-guard/" style="color:#10b981;font-weight:700;" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Upgrade to Pro', 'forgehub-image-memory-guard' ) . '</a>';
			array_unshift( $links, $settings_link, $pro_link );
		} else {
			array_unshift( $links, $settings_link );
		}
		return $links;
	}

	public function enqueue_assets( $hook ) {
		if ( 'settings_page_forgehub-image-memory-guard' !== $hook ) {
			return;
		}

		$css_path = WPFH_IMG_PATH . 'assets/css/admin.css';
		$js_path  = WPFH_IMG_PATH . 'assets/js/admin.js';

		wp_enqueue_style(
			'wpfh-img-admin',
			WPFH_IMG_URL . 'assets/css/admin.css',
			array(),
			file_exists( $css_path ) ? filemtime( $css_path ) : WPFH_IMG_VERSION
		);

		wp_enqueue_script(
			'wpfh-img-admin',
			WPFH_IMG_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			file_exists( $js_path ) ? filemtime( $js_path ) : WPFH_IMG_VERSION,
			true
		);

		wp_localize_script(
			'wpfh-img-admin',
			'wpfhImgAdmin',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wpfh_img_admin_nonce' ),
			)
		);
	}

	public function render_admin_page() {
		$memory_limit   = ini_get( 'memory_limit' );
		$limit_bytes    = WPFH_IMG_Guard::shorthand_to_bytes( $memory_limit );
		$is_unlimited   = ( $limit_bytes < 0 );
		$current_bytes  = memory_get_usage( true );
		$peak_bytes     = memory_get_peak_usage( true );
		$current_mb     = round( $current_bytes / 1024 / 1024, 1 );
		$peak_mb        = round( $peak_bytes / 1024 / 1024, 1 );
		$limit_mb       = $is_unlimited ? -1 : round( $limit_bytes / 1024 / 1024, 1 );

		$engine = extension_loaded( 'imagick' ) && class_exists( 'Imagick' ) ? 'ImageMagick (Imagick)' : 'GD Library';

		if ( $is_unlimited ) {
			$max_dim_display = __( 'Unlimited', 'forgehub-image-memory-guard' );
		} else {
			// Calculate approximate max safe resolution (assuming RGBA 4 bytes * 1.8 overhead)
			$available_decomp_bytes = ( $limit_bytes * 0.75 ) - $current_bytes;
			$max_pixels             = max( 0, $available_decomp_bytes / ( 4 * 1.8 ) );
			$max_dim                = floor( sqrt( $max_pixels ) );
			$max_dim_display        = "{$max_dim} × {$max_dim} px";
		}

		$auto_downscale = get_option( 'wpfh_img_auto_downscale', 'yes' );
		$max_dimension  = max( 320, absint( get_option( 'wpfh_img_max_dimension', 2560 ) ) );
		$is_pro         = function_exists( 'wpfh_img_fs' ) && wpfh_img_fs()->can_use_premium_code();
		?>
		<div class="wrap wpfh-img-wrap">
			<h1>🛡️ <?php esc_html_e( 'Image Memory Guard', 'forgehub-image-memory-guard' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Proactive memory safety diagnostics and decompression budget calculator.', 'forgehub-image-memory-guard' ); ?></p>

			<!-- Diagnostics Grid -->
			<div class="wpfh-stat-grid">
				<div class="wpfh-stat-card">
					<div class="wpfh-stat-label"><?php esc_html_e( 'PHP Memory Limit', 'forgehub-image-memory-guard' ); ?></div>
					<div class="wpfh-stat-value highlight"><?php echo $is_unlimited ? esc_html__( 'Unlimited (-1)', 'forgehub-image-memory-guard' ) : esc_html( $memory_limit ); ?></div>
					<div class="wpfh-stat-desc">
						<?php
						if ( $is_unlimited ) {
							esc_html_e( 'No PHP memory cap enforced', 'forgehub-image-memory-guard' );
						} else {
							/* translators: %s: Total memory in MB */
							printf( esc_html__( '%s MB Total Available', 'forgehub-image-memory-guard' ), esc_html( $limit_mb ) );
						}
						?>
					</div>
				</div>
				<div class="wpfh-stat-card">
					<div class="wpfh-stat-label"><?php esc_html_e( 'Peak Memory Used', 'forgehub-image-memory-guard' ); ?></div>
					<div class="wpfh-stat-value"><?php echo esc_html( $peak_mb ); ?> MB</div>
					<div class="wpfh-stat-desc"><?php echo esc_html( $current_mb ); ?> MB <?php esc_html_e( 'Current Baseline', 'forgehub-image-memory-guard' ); ?></div>
				</div>
				<div class="wpfh-stat-card">
					<div class="wpfh-stat-label"><?php esc_html_e( 'Active Image Engine', 'forgehub-image-memory-guard' ); ?></div>
					<div class="wpfh-stat-value"><?php echo esc_html( $engine ); ?></div>
					<div class="wpfh-stat-desc"><?php esc_html_e( 'PHP Graphics Driver', 'forgehub-image-memory-guard' ); ?></div>
				</div>
				<div class="wpfh-stat-card">
					<div class="wpfh-stat-label"><?php esc_html_e( 'Max Safe Upload Resolution', 'forgehub-image-memory-guard' ); ?></div>
					<div class="wpfh-stat-value highlight-green"><?php echo esc_html( $max_dim_display ); ?></div>
					<div class="wpfh-stat-desc"><?php esc_html_e( 'Safe Decompression Budget', 'forgehub-image-memory-guard' ); ?></div>
				</div>
			</div>

			<!-- Guard Settings Form -->
			<div class="wpfh-calc-box" style="margin-top:24px;">
				<h3>⚙️ <?php esc_html_e( 'Upload Protection Settings', 'forgehub-image-memory-guard' ); ?></h3>
				<form method="post" action="options.php">
					<?php settings_fields( 'wpfh_img_settings' ); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="wpfh_img_auto_downscale"><?php esc_html_e( 'Safe Auto-Downscale', 'forgehub-image-memory-guard' ); ?></label></th>
							<td>
								<select name="wpfh_img_auto_downscale" id="wpfh_img_auto_downscale">
									<option value="yes" <?php selected( $auto_downscale, 'yes' ); ?>><?php esc_html_e( 'Enabled (Downscale oversized uploads safely)', 'forgehub-image-memory-guard' ); ?></option>
									<option value="no" <?php selected( $auto_downscale, 'no' ); ?>><?php esc_html_e( 'Disabled (Guard & reject over-budget uploads only)', 'forgehub-image-memory-guard' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wpfh_img_max_dimension"><?php esc_html_e( 'Maximum Image Dimension (px)', 'forgehub-image-memory-guard' ); ?></label></th>
							<td>
								<input type="number" name="wpfh_img_max_dimension" id="wpfh_img_max_dimension" value="<?php echo esc_attr( $max_dimension ); ?>" min="320" max="10000" step="10" class="small-text" />
								<p class="description"><?php esc_html_e( 'Maximum width or height (in pixels) when auto-downscaling is enabled. Default: 2560 px.', 'forgehub-image-memory-guard' ); ?></p>
							</td>
						</tr>
					</table>
					<?php submit_button( __( 'Save Settings', 'forgehub-image-memory-guard' ) ); ?>
				</form>
			</div>

			<!-- Live Budget Calculator -->
			<div class="wpfh-calc-box">
				<h3>🧮 <?php esc_html_e( 'Interactive Memory Budget Calculator', 'forgehub-image-memory-guard' ); ?></h3>
				<p><?php esc_html_e( 'Enter dimensions of an image to test whether it would exceed server memory before uploading.', 'forgehub-image-memory-guard' ); ?></p>
				<div class="wpfh-calc-form">
					<div class="wpfh-calc-field">
						<label><?php esc_html_e( 'Width (px):', 'forgehub-image-memory-guard' ); ?></label>
						<input type="number" id="calc_width" value="4000" min="100" max="20000" />
					</div>
					<div class="wpfh-calc-field">
						<label><?php esc_html_e( 'Height (px):', 'forgehub-image-memory-guard' ); ?></label>
						<input type="number" id="calc_height" value="3000" min="100" max="20000" />
					</div>
					<button type="button" id="btn_calculate_budget" class="button button-primary"><?php esc_html_e( 'Calculate Memory Budget', 'forgehub-image-memory-guard' ); ?></button>
				</div>
				<div id="calc_result" class="wpfh-calc-result" data-limit-mb="<?php echo esc_attr( $limit_mb ); ?>" data-current-mb="<?php echo esc_attr( $current_mb ); ?>">
					<!-- Calculated via JS -->
				</div>
			</div>

			<?php if ( $is_pro ) : ?>
			<!-- Pro Background Optimization Queue -->
			<div class="wpfh-calc-box" style="margin-top:24px;">
				<h3>🚀 <?php esc_html_e( 'Pro Background Batch Optimization (WebP & AVIF)', 'forgehub-image-memory-guard' ); ?></h3>
				<p><?php esc_html_e( 'Queue all existing Media Library images for chunked background WebP/AVIF conversion without hitting PHP timeouts.', 'forgehub-image-memory-guard' ); ?></p>
				<?php $queued = get_option( 'wpfh_img_background_queue', array() ); ?>
				<p><strong><?php esc_html_e( 'Currently Queued:', 'forgehub-image-memory-guard' ); ?></strong> <span id="wpfh_img_queue_count"><?php echo count( is_array( $queued ) ? $queued : array() ); ?></span></p>
				<button type="button" id="wpfh_img_start_queue_btn" class="button button-primary"><?php esc_html_e( 'Start Background Optimization Queue', 'forgehub-image-memory-guard' ); ?></button>
				<span id="wpfh_img_queue_feedback" style="margin-left:12px;font-weight:600;"></span>
			</div>
			<?php else : ?>
			<!-- Pro Upsell -->
			<div class="wpfh-pro-card" style="background:#0f172a;border:1px solid #1e293b;border-radius:12px;padding:24px;margin-top:28px;color:#e2e8f0;max-width:850px;">
				<div style="display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #334155;padding-bottom:16px;margin-bottom:16px;">
					<div>
						<h3 style="margin:0;font-size:18px;color:#f8fafc;">🚀 Image Memory Guard Pro</h3>
						<p style="margin:4px 0 0 0;color:#94a3b8;font-size:13px;">Enterprise background batch resizing and WebP/AVIF generation.</p>
					</div>
					<a href="https://wpforgehub.com/product/image-memory-guard/" target="_blank" rel="noopener noreferrer" style="background:#10b981;color:#ffffff;padding:8px 16px;border-radius:6px;text-decoration:none;font-weight:700;font-size:13px;display:inline-block;">Upgrade to Pro &rarr;</a>
				</div>
				<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;font-size:13px;">
					<div style="background:#1e293b;padding:12px 16px;border-radius:8px;">
						<strong style="color:#38bdf8;">🔄 Background Chunked Resizing</strong>
						<p style="margin:4px 0 0 0;color:#cbd5e1;">Offload huge thumbnail generation to background queues with 0 frontend slowdown.</p>
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
						<p style="margin:4px 0 0 0;color:#cbd5e1;">Strips privacy-sensitive GPS and camera metadata while preserving orientation and ICC profiles.</p>
					</div>
				</div>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
