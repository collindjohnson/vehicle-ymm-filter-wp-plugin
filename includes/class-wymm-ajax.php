<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WYMM_Ajax {

	const NONCE = 'wymm_ajax';

	public static function register() {
		$endpoints = array( 'wymm_get_makes', 'wymm_get_models', 'wymm_get_years' );
		foreach ( $endpoints as $ep ) {
			add_action( 'wp_ajax_' . $ep, array( __CLASS__, $ep ) );
			add_action( 'wp_ajax_nopriv_' . $ep, array( __CLASS__, $ep ) );
		}
		add_action( 'wp_ajax_wymm_clear_cache', array( __CLASS__, 'clear_cache' ) );
		add_action( 'wp_ajax_wymm_test_api', array( __CLASS__, 'test_api' ) );
	}

	private static function check_nonce() {
		$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			wp_send_json_error( array( 'message' => 'Bad nonce' ), 403 );
		}
	}

	public static function wymm_get_makes() {
		self::check_nonce();
		$data = WYMM_API::get_makes();
		if ( is_wp_error( $data ) ) {
			wp_send_json_error( array( 'message' => $data->get_error_message() ), 500 );
		}
		wp_send_json_success( $data );
	}

	public static function wymm_get_models() {
		self::check_nonce();
		$make = isset( $_REQUEST['make'] ) ? sanitize_key( wp_unslash( $_REQUEST['make'] ) ) : '';
		$data = WYMM_API::get_models( $make );
		if ( is_wp_error( $data ) ) {
			wp_send_json_error( array( 'message' => $data->get_error_message() ), 500 );
		}
		wp_send_json_success( $data );
	}

	public static function wymm_get_years() {
		self::check_nonce();
		$make  = isset( $_REQUEST['make'] ) ? sanitize_key( wp_unslash( $_REQUEST['make'] ) ) : '';
		$model = isset( $_REQUEST['model'] ) ? sanitize_key( wp_unslash( $_REQUEST['model'] ) ) : '';
		$data  = WYMM_API::get_years( $make, $model );
		if ( is_wp_error( $data ) ) {
			wp_send_json_error( array( 'message' => $data->get_error_message() ), 500 );
		}
		wp_send_json_success( $data );
	}

	public static function clear_cache() {
		self::check_nonce();
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		WYMM_API::clear_cache();
		wp_send_json_success( array( 'message' => 'Cache cleared' ) );
	}

	public static function test_api() {
		self::check_nonce();
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		$makes = WYMM_API::get_makes();
		if ( is_wp_error( $makes ) ) {
			wp_send_json_error( array( 'message' => $makes->get_error_message() ), 500 );
		}
		wp_send_json_success( array( 'count' => count( $makes ) ) );
	}

	public static function nonce() {
		return wp_create_nonce( self::NONCE );
	}
}
