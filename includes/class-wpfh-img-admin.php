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

		if ( is_admin() && class_exists( 'WPFH_Review_Nudge' ) ) {
			$nudge = new WPFH_Review_Nudge(
				array(
					'plugin_slug'   => 'forgehub-image-memory-guard',
					'plugin_name'   => 'ForgeHub Image Memory Guard',
					'option_prefix' => 'wpfh_img',
					'wporg_slug'    => 'forgehub-image-memory-guard',
					'github_url'    => 'https://github.com/WpForgeHub/forgehub-image-memory-guard',
					'min_actions'   => 5,
					'min_days'      => 7,
					'trigger_mode'  => 'or',
					'screens'       => array( 'settings_page_forgehub-image-memory-guard', 'upload', 'plugins' ),
					'strings'       => array(
						/* translators: %s: Plugin name. */
						'heading'     => sprintf( __( 'Enjoying %s?', 'forgehub-image-memory-guard' ), 'ForgeHub Image Memory Guard' ),
						/* translators: %s: Plugin name. */
						'message'     => sprintf( __( 'If %s has helped protect your uploads, a quick 5-star review helps other site owners discover it and keeps development active.', 'forgehub-image-memory-guard' ), 'ForgeHub Image Memory Guard' ),
						'rate_cta'    => __( 'Rate 5 Stars on WordPress.org', 'forgehub-image-memory-guard' ),
						'github_cta'  => __( 'Star on GitHub', 'forgehub-image-memory-guard' ),
						'later_cta'   => __( 'Maybe later', 'forgehub-image-memory-guard' ),
						'dismiss_cta' => __( 'Already did / Dismiss', 'forgehub-image-memory-guard' ),
					),
				)
			);
			$nudge->register();
		}
	}

	private function get_upgrade_url() {
		$url = 'https://wpforgehub.com/product/image-memory-guard/#pricing';
		// <wpfh-pro-only>
		if ( function_exists( 'wpfh_img_fs' ) ) {
			$url = wpfh_img_fs()->get_upgrade_url();
		}
		// </wpfh-pro-only>
		return $url;
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
		$is_pro        = false;
		// <wpfh-pro-only>
		$is_pro        = function_exists( 'wpfh_img_fs' ) && wpfh_img_fs()->can_use_premium_code();
		// </wpfh-pro-only>
		if ( ! $is_pro ) {
			$pro_link = '<a href="' . esc_url( $this->get_upgrade_url() ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Priority Support & Pro', 'forgehub-image-memory-guard' ) . '</a>';
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
		$is_pro         = false;
		// <wpfh-pro-only>
		$is_pro         = function_exists( 'wpfh_img_fs' ) && wpfh_img_fs()->can_use_premium_code();
		// </wpfh-pro-only>
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
			<p class="wpfh-img-footer-note" style="margin-top:20px;color:#50575e;font-size:13px;">
				<?php
				printf(
					/* translators: %s: link to optional Pro edition and priority support */
					esc_html__( 'Need background WebP/AVIF batch queues, WP-CLI audits, or priority support? %s', 'forgehub-image-memory-guard' ),
					'<a href="' . esc_url( $this->get_upgrade_url() ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Priority Support & Pro', 'forgehub-image-memory-guard' ) . '</a>'
				);
				?>
			</p>
			<?php endif; ?>
		</div>
		<?php
	}
}
