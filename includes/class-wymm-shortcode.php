<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WYMM_Shortcode {

	public static function register() {
		add_shortcode( 'wheel_ymm_filter', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function enqueue() {
		wp_register_script(
			'wymm-frontend',
			WYMM_URL . 'assets/js/frontend-filter.js',
			array( 'jquery' ),
			WYMM_VERSION,
			true
		);
		wp_localize_script( 'wymm-frontend', 'WYMM', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => WYMM_Ajax::public_nonce(),
			'version' => WYMM_VERSION,
			'i18n'    => array(
				'make'      => __( 'Make', 'wheel-ymm-filter' ),
				'model'     => __( 'Model', 'wheel-ymm-filter' ),
				'year'      => __( 'Year', 'wheel-ymm-filter' ),
				'loading'   => __( 'Loading…', 'wheel-ymm-filter' ),
				'error'     => __( '(error)', 'wheel-ymm-filter' ),
				'selectAll' => __( 'Please select year, make, and model.', 'wheel-ymm-filter' ),
			),
		) );
		wp_register_style( 'wymm-frontend', WYMM_URL . 'assets/css/frontend-filter.css', array(), WYMM_VERSION );
	}

	public static function render( $atts ) {
		wp_enqueue_script( 'wymm-frontend' );
		wp_enqueue_style( 'wymm-frontend' );

		$atts = shortcode_atts( array(
			'redirect' => wc_get_page_permalink( 'shop' ),
			'layout'   => 'horizontal',
		), $atts, 'wheel_ymm_filter' );

		$layout = in_array( $atts['layout'], array( 'horizontal', 'stacked' ), true ) ? $atts['layout'] : 'horizontal';

		ob_start();
		?>
		<form class="wymm-filter wymm-layout-<?php echo esc_attr( $layout ); ?>" method="get" action="<?php echo esc_url( $atts['redirect'] ); ?>">
			<div class="wymm-field">
				<label>Year</label>
				<select name="wymm_year" class="wymm-f-year" disabled><option value="">Year</option></select>
			</div>
			<div class="wymm-field">
				<label>Make</label>
				<select name="wymm_make" class="wymm-f-make"><option value="">Loading…</option></select>
			</div>
			<div class="wymm-field">
				<label>Model</label>
				<select name="wymm_model" class="wymm-f-model" disabled><option value="">Model</option></select>
			</div>
			<div class="wymm-field wymm-submit">
				<button type="submit" class="button wymm-find">Find Wheels</button>
			</div>
		</form>
		<?php
		return ob_get_clean();
	}
}
