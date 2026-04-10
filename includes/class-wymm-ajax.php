<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WYMM_Ajax {

	const NONCE_PUBLIC = 'wymm_public';
	const NONCE_ADMIN  = 'wymm_admin';

	const PER_IP_LIMIT  = 30;
	const PER_IP_WINDOW = 60;

	public static function register() {
		$public_endpoints = array( 'wymm_get_makes', 'wymm_get_models', 'wymm_get_years' );
		foreach ( $public_endpoints as $ep ) {
			add_action( 'wp_ajax_' . $ep, array( __CLASS__, $ep ) );
			add_action( 'wp_ajax_nopriv_' . $ep, array( __CLASS__, $ep ) );
		}

		$admin_endpoints = array( 'wymm_admin_get_makes', 'wymm_admin_get_models', 'wymm_admin_get_years' );
		foreach ( $admin_endpoints as $ep ) {
			add_action( 'wp_ajax_' . $ep, array( __CLASS__, $ep ) );
		}

		add_action( 'wp_ajax_wymm_clear_cache', array( __CLASS__, 'clear_cache' ) );
		add_action( 'wp_ajax_wymm_test_api', array( __CLASS__, 'test_api' ) );
	}

	private static function check_nonce( $action ) {
		$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			wp_send_json_error( array( 'message' => 'Bad nonce' ), 403 );
		}
	}

	private static function per_ip_ok() {
		$raw_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		$ip     = preg_replace( '/[^0-9a-fA-F:.]/', '', $raw_ip );
		if ( '' === $ip ) {
			$ip = 'unknown';
		}
		$bucket = 'wymm_ip_' . md5( $ip ) . '_' . (int) floor( time() / self::PER_IP_WINDOW );
		$count  = (int) get_transient( $bucket );
		if ( $count >= self::PER_IP_LIMIT ) {
			wp_send_json_error( array( 'message' => 'Too many requests' ), 429 );
		}
		set_transient( $bucket, $count + 1, self::PER_IP_WINDOW + 30 );
	}

	private static function require_fitment_access() {
		self::check_nonce( self::NONCE_ADMIN );
		if ( ! current_user_can( WYMM_Admin::fitment_capability() ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
	}

	private static function require_settings_access() {
		self::check_nonce( self::NONCE_ADMIN );
		if ( ! current_user_can( WYMM_Admin::settings_capability() ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
	}

	public static function wymm_get_makes() {
		self::per_ip_ok();
		self::check_nonce( self::NONCE_PUBLIC );
		wp_send_json_success( WYMM_Fitments::public_makes() );
	}

	public static function wymm_get_models() {
		self::per_ip_ok();
		self::check_nonce( self::NONCE_PUBLIC );
		$make = isset( $_REQUEST['make'] ) ? sanitize_key( wp_unslash( $_REQUEST['make'] ) ) : '';
		wp_send_json_success( WYMM_Fitments::public_models( $make ) );
	}

	public static function wymm_get_years() {
		self::per_ip_ok();
		self::check_nonce( self::NONCE_PUBLIC );
		$make  = isset( $_REQUEST['make'] ) ? sanitize_key( wp_unslash( $_REQUEST['make'] ) ) : '';
		$model = isset( $_REQUEST['model'] ) ? sanitize_key( wp_unslash( $_REQUEST['model'] ) ) : '';
		wp_send_json_success( WYMM_Fitments::public_years( $make, $model ) );
	}

	public static function wymm_admin_get_makes() {
		self::require_fitment_access();
		$data = WYMM_API::get_makes();
		if ( is_wp_error( $data ) ) {
			wp_send_json_error( array( 'message' => $data->get_error_message() ), 500 );
		}
		wp_send_json_success( $data );
	}

	public static function wymm_admin_get_models() {
		self::require_fitment_access();
		$make = isset( $_REQUEST['make'] ) ? sanitize_key( wp_unslash( $_REQUEST['make'] ) ) : '';
		$data = WYMM_API::get_models( $make );
		if ( is_wp_error( $data ) ) {
			wp_send_json_error( array( 'message' => $data->get_error_message() ), 500 );
		}
		wp_send_json_success( $data );
	}

	public static function wymm_admin_get_years() {
		self::require_fitment_access();
		$make  = isset( $_REQUEST['make'] ) ? sanitize_key( wp_unslash( $_REQUEST['make'] ) ) : '';
		$model = isset( $_REQUEST['model'] ) ? sanitize_key( wp_unslash( $_REQUEST['model'] ) ) : '';
		$data  = WYMM_API::get_years( $make, $model );
		if ( is_wp_error( $data ) ) {
			wp_send_json_error( array( 'message' => $data->get_error_message() ), 500 );
		}
		wp_send_json_success( $data );
	}

	public static function clear_cache() {
		self::require_settings_access();
		WYMM_API::clear_cache();
		wp_send_json_success( array( 'message' => 'Cache cleared' ) );
	}

	public static function test_api() {
		self::require_settings_access();
		$makes = WYMM_API::get_makes();
		if ( is_wp_error( $makes ) ) {
			wp_send_json_error( array( 'message' => $makes->get_error_message() ), 500 );
		}
		wp_send_json_success( array( 'count' => count( $makes ) ) );
	}

	public static function public_nonce() {
		return wp_create_nonce( self::NONCE_PUBLIC );
	}

	public static function admin_nonce() {
		return wp_create_nonce( self::NONCE_ADMIN );
	}
}
