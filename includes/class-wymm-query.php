<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WYMM_Query {

	public static function register() {
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'woocommerce_product_query', array( __CLASS__, 'filter_product_query' ) );
		add_action( 'woocommerce_before_shop_loop', array( __CLASS__, 'active_filter_notice' ), 5 );
	}

	public static function query_vars( $vars ) {
		$vars[] = 'wymm_make';
		$vars[] = 'wymm_model';
		$vars[] = 'wymm_year';
		return $vars;
	}

	private static function requested() {
		$make  = isset( $_GET['wymm_make'] ) ? sanitize_key( wp_unslash( $_GET['wymm_make'] ) ) : '';
		$model = isset( $_GET['wymm_model'] ) ? sanitize_key( wp_unslash( $_GET['wymm_model'] ) ) : '';
		$year  = isset( $_GET['wymm_year'] ) ? (int) $_GET['wymm_year'] : 0;
		if ( '' === $make || '' === $model || ! $year ) {
			return null;
		}
		return array( $make, $model, $year );
	}

	public static function filter_product_query( $q ) {
		$req = self::requested();
		if ( ! $req ) {
			return;
		}
		list( $make, $model, $year ) = $req;
		$ids = WYMM_Fitments::product_ids_for_ymm( $make, $model, $year );
		if ( empty( $ids ) ) {
			$q->set( 'post__in', array( 0 ) );
			return;
		}
		$existing = (array) $q->get( 'post__in' );
		if ( ! empty( $existing ) ) {
			$ids = array_values( array_intersect( $existing, $ids ) );
			if ( empty( $ids ) ) {
				$ids = array( 0 );
			}
		}
		$q->set( 'post__in', $ids );
	}

	public static function active_filter_notice() {
		$req = self::requested();
		if ( ! $req ) {
			return;
		}
		list( $make, $model, $year ) = $req;
		$clear_url = remove_query_arg( array( 'wymm_make', 'wymm_model', 'wymm_year' ) );
		$label = sprintf( '%d %s %s', (int) $year, esc_html( ucfirst( str_replace( '-', ' ', $make ) ) ), esc_html( ucfirst( str_replace( '-', ' ', $model ) ) ) );
		echo '<div class="wymm-active-filter"><span>Showing wheels for: <strong>' . $label . '</strong></span><a href="' . esc_url( $clear_url ) . '">Clear filter</a></div>';
	}
}
