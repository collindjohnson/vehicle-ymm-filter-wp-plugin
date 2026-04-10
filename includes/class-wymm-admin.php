<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WYMM_Admin {

	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'option_page_capability_wymm_settings', array( __CLASS__, 'option_page_capability' ) );

		// Simple product fitment panel.
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'product_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'product_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_simple' ) );

		// Variation fitment panel.
		add_action( 'woocommerce_product_after_variable_attributes', array( __CLASS__, 'variation_panel' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( __CLASS__, 'save_variation' ), 10, 2 );

		// Clean up fitments when a product is deleted.
		add_action( 'before_delete_post', array( __CLASS__, 'on_delete' ) );
	}

	public static function settings_capability() {
		return (string) apply_filters( 'wymm_settings_capability', 'manage_options' );
	}

	public static function fitment_capability() {
		return (string) apply_filters( 'wymm_fitment_capability', 'edit_products' );
	}

	public static function option_page_capability( $capability ) {
		return self::settings_capability();
	}

	public static function menu() {
		add_submenu_page(
			'woocommerce',
			'Wheel YMM Filter',
			'Wheel YMM',
			self::settings_capability(),
			'wymm-settings',
			array( __CLASS__, 'render_settings' )
		);
	}

	public static function register_settings() {
		register_setting( 'wymm_settings', WYMM_API::OPT_API_KEY, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_api_key' ) ) );
		register_setting( 'wymm_settings', WYMM_API::OPT_TTL, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_ttl' ) ) );
	}

	public static function sanitize_api_key( $value ) {
		if ( WYMM_API::has_external_key() ) {
			$existing = get_option( WYMM_API::OPT_API_KEY, '' );
			return is_string( $existing ) ? $existing : '';
		}

		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		if ( '' === $value ) {
			$existing = get_option( WYMM_API::OPT_API_KEY, '' );
			return is_string( $existing ) ? $existing : '';
		}

		return sanitize_text_field( $value );
	}

	public static function sanitize_ttl( $value ) {
		$ttl = absint( $value );
		if ( $ttl < 60 ) {
			$ttl = WYMM_API::CACHE_TTL;
		}
		return $ttl;
	}

	public static function render_settings() {
		if ( ! current_user_can( self::settings_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wheel-ymm-filter' ) );
		}
		$has_external = WYMM_API::has_external_key();
		$key_stored   = '' !== trim( (string) get_option( WYMM_API::OPT_API_KEY, '' ) );
		$ttl          = get_option( WYMM_API::OPT_TTL, WYMM_API::CACHE_TTL );
		?>
		<div class="wrap">
			<h1>Wheel YMM Filter</h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'wymm_settings' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="wymm_api_key">Wheel-Size API key</label></th>
						<td>
							<?php if ( $has_external ) : ?>
								<input type="password" id="wymm_api_key" value="" class="regular-text" autocomplete="off" spellcheck="false" placeholder="<?php echo esc_attr__( 'Managed outside WordPress.', 'wheel-ymm-filter' ); ?>" disabled />
								<p class="description"><?php esc_html_e( 'Your user-key is provided outside of WordPress and is not editable here.', 'wheel-ymm-filter' ); ?></p>
							<?php else : ?>
								<input type="password" id="wymm_api_key" name="<?php echo esc_attr( WYMM_API::OPT_API_KEY ); ?>" value="" class="regular-text" autocomplete="off" spellcheck="false" placeholder="<?php echo esc_attr( $key_stored ? __( 'Stored securely. Leave blank to keep the current key.', 'wheel-ymm-filter' ) : __( 'Paste a new API key.', 'wheel-ymm-filter' ) ); ?>" />
								<p class="description">Your <code>user-key</code> from <a href="https://api.wheel-size.com/" target="_blank" rel="noopener noreferrer">wheel-size.com</a>. The current key is never rendered back into this page.</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wymm_cache_ttl">Cache TTL (seconds)</label></th>
						<td>
							<input type="number" id="wymm_cache_ttl" name="<?php echo esc_attr( WYMM_API::OPT_TTL ); ?>" value="<?php echo esc_attr( $ttl ); ?>" class="small-text" min="60" />
							<p class="description">How long to cache makes/models/years before re-fetching. Default: 7 days.</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<h2>Diagnostics</h2>
			<p>
				<button type="button" class="button" id="wymm-test-api">Test API</button>
				<button type="button" class="button" id="wymm-clear-cache">Clear taxonomy cache</button>
				<span id="wymm-diag-msg" style="margin-left:10px;"></span>
			</p>

			<h2>Shortcode</h2>
			<p>Place <code>[wheel_ymm_filter]</code> on any page to render the Year / Make / Model filter. Customers are redirected to the shop with filters applied.</p>
			<p>Attributes: <code>redirect="https://example.com/shop/"</code>, <code>layout="horizontal|stacked"</code>.</p>
		</div>
		<?php
	}

	public static function enqueue( $hook ) {
		$screen      = get_current_screen();
		$is_product  = $screen && 'product' === $screen->post_type;
		$is_settings = 'woocommerce_page_wymm-settings' === $hook;

		if ( $is_product ) {
			wp_enqueue_script(
				'wymm-admin-fitments',
				WYMM_URL . 'assets/js/admin-fitments.js',
				array( 'jquery' ),
				WYMM_VERSION,
				true
			);
			wp_localize_script( 'wymm-admin-fitments', 'WYMM', array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => WYMM_Ajax::admin_nonce(),
				'actions' => array(
					'getMakes'  => 'wymm_admin_get_makes',
					'getModels' => 'wymm_admin_get_models',
					'getYears'  => 'wymm_admin_get_years',
				),
				'i18n'    => array(
					'selectAll'    => __( 'Select make, model, and year.', 'wheel-ymm-filter' ),
					'selectMakeModel' => __( 'Select make and model first.', 'wheel-ymm-filter' ),
					'invalidRange' => __( 'Enter a valid year range.', 'wheel-ymm-filter' ),
					'rangeTooLarge' => __( 'Maximum year range is 50 years.', 'wheel-ymm-filter' ),
					'errorLoading' => __( '(error loading makes)', 'wheel-ymm-filter' ),
					'error'        => __( '(error)', 'wheel-ymm-filter' ),
					'model'        => __( 'Model', 'wheel-ymm-filter' ),
					'year'         => __( 'Year', 'wheel-ymm-filter' ),
					'loading'      => __( 'Loading…', 'wheel-ymm-filter' ),
				),
			) );
		}

		if ( $is_settings ) {
			wp_enqueue_script(
				'wymm-admin-settings',
				WYMM_URL . 'assets/js/admin-settings.js',
				array( 'jquery' ),
				WYMM_VERSION,
				true
			);
			wp_localize_script( 'wymm-admin-settings', 'WYMM_SETTINGS', array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => WYMM_Ajax::admin_nonce(),
				'i18n'    => array(
					'testing'      => __( 'Testing…', 'wheel-ymm-filter' ),
					'okMakes'      => __( 'OK — %d makes available.', 'wheel-ymm-filter' ),
					'cacheCleared' => __( 'Cache cleared.', 'wheel-ymm-filter' ),
					'error'        => __( 'Error: %s', 'wheel-ymm-filter' ),
				),
			) );
		}
	}

	public static function product_tab( $tabs ) {
		$tabs['wymm_fitments'] = array(
			'label'    => 'YMM Fitments',
			'target'   => 'wymm_fitments_panel',
			'class'    => array(),
			'priority' => 70,
		);
		return $tabs;
	}

	public static function product_panel() {
		global $post;
		$product_id = $post ? (int) $post->ID : 0;
		$fitments   = WYMM_Fitments::get_for_product( $product_id, 0 );
		?>
		<div id="wymm_fitments_panel" class="panel woocommerce_options_panel">
			<div class="options_group">
				<p class="form-field"><strong>Year / Make / Model fitments for this product</strong></p>
				<p class="form-field description">For variable products, use the per-variation fitments in the Variations tab instead.</p>
				<?php self::render_fitment_editor( 'wymm_product_fitments', $fitments ); ?>
			</div>
		</div>
		<?php
	}

	public static function variation_panel( $loop, $variation_data, $variation ) {
		$variation_id = (int) $variation->ID;
		$product_id   = (int) $variation->post_parent;
		$fitments     = WYMM_Fitments::get_for_variation_prefetched( $product_id, $variation_id );
		$field_name   = 'wymm_variation_fitments[' . $variation_id . ']';
		?>
		<div class="wymm-variation-fitments" style="width:100%;padding:10px;border-top:1px solid #eee;margin-top:10px;">
			<p><strong>YMM fitments for this variation</strong></p>
			<?php self::render_fitment_editor( $field_name, $fitments, true ); ?>
		</div>
		<?php
	}

	private static function render_fitment_editor( $field_base, $fitments, $compact = false ) {
		?>
		<div class="wymm-editor" data-field="<?php echo esc_attr( $field_base ); ?>" style="padding:8px;">
			<div class="wymm-row" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-bottom:6px;">
				<select class="wymm-make"><option value="">Loading makes…</option></select>
				<select class="wymm-model" disabled><option value="">Model</option></select>
				<select class="wymm-year" disabled><option value="">Year</option></select>
				<button type="button" class="button wymm-add-one">Add</button>
				<span style="margin:0 6px;color:#888;">or range:</span>
				<input type="number" class="wymm-year-from" placeholder="From" min="1900" max="2100" style="width:80px;" />
				<input type="number" class="wymm-year-to" placeholder="To" min="1900" max="2100" style="width:80px;" />
				<button type="button" class="button wymm-add-range">Add range</button>
			</div>
			<table class="widefat wymm-list" style="max-width:640px;">
				<thead><tr><th>Make</th><th>Model</th><th>Year</th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $fitments as $f ) : ?>
					<tr data-make="<?php echo esc_attr( $f['make_slug'] ); ?>" data-model="<?php echo esc_attr( $f['model_slug'] ); ?>" data-year="<?php echo esc_attr( $f['year'] ); ?>">
						<td><?php echo esc_html( $f['make_slug'] ); ?><input type="hidden" name="<?php echo esc_attr( $field_base ); ?>[make_slug][]" value="<?php echo esc_attr( $f['make_slug'] ); ?>" /></td>
						<td><?php echo esc_html( $f['model_slug'] ); ?><input type="hidden" name="<?php echo esc_attr( $field_base ); ?>[model_slug][]" value="<?php echo esc_attr( $f['model_slug'] ); ?>" /></td>
						<td><?php echo esc_html( $f['year'] ); ?><input type="hidden" name="<?php echo esc_attr( $field_base ); ?>[year][]" value="<?php echo esc_attr( $f['year'] ); ?>" /></td>
						<td><button type="button" class="button wymm-remove">Remove</button></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<input type="hidden" name="<?php echo esc_attr( $field_base ); ?>[_present]" value="1" />
		</div>
		<?php
	}

	public static function save_simple( $product_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		$nonce = isset( $_POST['woocommerce_meta_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'woocommerce_save_data' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_product', $product_id ) ) {
			return;
		}
		if ( ! isset( $_POST['wymm_product_fitments']['_present'] ) ) {
			return;
		}
		$posted   = isset( $_POST['wymm_product_fitments'] ) && is_array( $_POST['wymm_product_fitments'] ) ? wp_unslash( $_POST['wymm_product_fitments'] ) : array();
		$fitments = self::parse_posted( $posted );
		WYMM_Fitments::replace_for_variation( (int) $product_id, 0, $fitments );
	}

	public static function save_variation( $variation_id, $i ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		// WooCommerce triggers this from its own AJAX save, which verifies its own nonce;
		// re-check capability here and validate structure defensively.
		if ( ! current_user_can( 'edit_post', $variation_id ) ) {
			return;
		}
		$variation_id = (int) $variation_id;
		if ( empty( $_POST['wymm_variation_fitments'][ $variation_id ]['_present'] ) ) {
			return;
		}
		$raw      = $_POST['wymm_variation_fitments'][ $variation_id ];
		$posted   = is_array( $raw ) ? wp_unslash( $raw ) : array();
		$fitments = self::parse_posted( $posted );
		$parent   = (int) wp_get_post_parent_id( $variation_id );
		WYMM_Fitments::replace_for_variation( $parent, $variation_id, $fitments );
	}

	const MIN_YEAR = 1900;
	const MAX_YEAR = 2100;
	const MAX_FITMENTS_PER_SAVE = 5000;

	private static function parse_posted( $posted ) {
		if ( ! is_array( $posted ) ) {
			return array();
		}
		$makes  = isset( $posted['make_slug'] ) && is_array( $posted['make_slug'] ) ? $posted['make_slug'] : array();
		$models = isset( $posted['model_slug'] ) && is_array( $posted['model_slug'] ) ? $posted['model_slug'] : array();
		$years  = isset( $posted['year'] ) && is_array( $posted['year'] ) ? $posted['year'] : array();
		$count  = min( count( $makes ), count( $models ), count( $years ), self::MAX_FITMENTS_PER_SAVE );
		$out    = array();
		$seen   = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$make  = sanitize_key( is_scalar( $makes[ $i ] ) ? (string) $makes[ $i ] : '' );
			$model = sanitize_key( is_scalar( $models[ $i ] ) ? (string) $models[ $i ] : '' );
			$year  = is_scalar( $years[ $i ] ) ? (int) $years[ $i ] : 0;
			if ( '' === $make || '' === $model ) {
				continue;
			}
			if ( $year < self::MIN_YEAR || $year > self::MAX_YEAR ) {
				continue;
			}
			$key = $make . '|' . $model . '|' . $year;
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[] = array(
				'make_slug'  => $make,
				'model_slug' => $model,
				'year'       => $year,
			);
		}
		return $out;
	}

	public static function on_delete( $post_id ) {
		$type = get_post_type( $post_id );
		if ( 'product' === $type || 'product_variation' === $type ) {
			WYMM_Fitments::delete_for_product( $post_id );
		}
	}
}
