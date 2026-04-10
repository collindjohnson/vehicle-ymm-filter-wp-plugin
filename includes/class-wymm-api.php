<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WYMM_API {

	const BASE        = 'https://api.wheel-size.com/v2';
	const CACHE_TTL   = MONTH_IN_SECONDS;
	const OPT_API_KEY = 'wymm_api_key';
	const OPT_TTL     = 'wymm_cache_ttl';

	public static function api_key() {
		return trim( (string) get_option( self::OPT_API_KEY, '' ) );
	}

	public static function ttl() {
		$ttl = (int) get_option( self::OPT_TTL, self::CACHE_TTL );
		return $ttl > 0 ? $ttl : self::CACHE_TTL;
	}

	public static function get_makes() {
		$key = 'wymm_makes';
		$cached = get_transient( $key );
		if ( false !== $cached ) {
			return $cached;
		}
		$data = self::request( '/makes/' );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$list = self::normalize( $data );
		set_transient( $key, $list, self::ttl() );
		return $list;
	}

	public static function get_models( $make_slug ) {
		$make_slug = sanitize_key( $make_slug );
		if ( '' === $make_slug ) {
			return array();
		}
		$key = 'wymm_models_' . $make_slug;
		$cached = get_transient( $key );
		if ( false !== $cached ) {
			return $cached;
		}
		$data = self::request( '/models/', array( 'make' => $make_slug ) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$list = self::normalize( $data );
		set_transient( $key, $list, self::ttl() );
		return $list;
	}

	public static function get_years( $make_slug, $model_slug ) {
		$make_slug  = sanitize_key( $make_slug );
		$model_slug = sanitize_key( $model_slug );
		if ( '' === $make_slug || '' === $model_slug ) {
			return array();
		}
		$key = 'wymm_years_' . $make_slug . '_' . $model_slug;
		$cached = get_transient( $key );
		if ( false !== $cached ) {
			return $cached;
		}
		$data = self::request( '/years/', array(
			'make'  => $make_slug,
			'model' => $model_slug,
		) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$years = array();
		$rows  = isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : (array) $data;
		foreach ( $rows as $row ) {
			if ( is_scalar( $row ) ) {
				$years[] = (int) $row;
			} elseif ( is_array( $row ) && isset( $row['year'] ) ) {
				$years[] = (int) $row['year'];
			} elseif ( is_array( $row ) && isset( $row['slug'] ) ) {
				$years[] = (int) $row['slug'];
			}
		}
		$years = array_values( array_unique( array_filter( $years ) ) );
		rsort( $years );
		set_transient( $key, $years, self::ttl() );
		return $years;
	}

	public static function clear_cache() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wymm_%' OR option_name LIKE '_transient_timeout_wymm_%'" );
	}

	private static function request( $path, $query = array() ) {
		$key = self::api_key();
		if ( '' === $key ) {
			return new WP_Error( 'wymm_no_key', __( 'Wheel-Size API key is not configured.', 'wheel-ymm-filter' ) );
		}
		$query['user-key'] = $key;
		$url = self::BASE . $path . '?' . http_build_query( $query );

		$response = wp_remote_get( $url, array(
			'timeout' => 10,
			'headers' => array( 'Accept' => 'application/json' ),
		) );

		if ( is_wp_error( $response ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[WYMM] request failed: ' . $response->get_error_message() );
			}
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		if ( $code < 200 || $code >= 300 ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[WYMM] HTTP ' . $code . ' from ' . $path . ': ' . $body );
			}
			return new WP_Error( 'wymm_http_' . $code, sprintf( 'Wheel-Size API returned HTTP %d', $code ) );
		}
		$decoded = json_decode( $body, true );
		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'wymm_bad_json', 'Wheel-Size API returned invalid JSON.' );
		}
		return $decoded;
	}

	private static function normalize( $data ) {
		$rows = isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : (array) $data;
		$out  = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$slug = isset( $row['slug'] ) ? sanitize_key( $row['slug'] ) : '';
			$name = isset( $row['name'] ) ? (string) $row['name'] : $slug;
			if ( '' === $slug ) {
				continue;
			}
			$out[] = array( 'slug' => $slug, 'name' => $name );
		}
		usort( $out, function ( $a, $b ) {
			return strcasecmp( $a['name'], $b['name'] );
		} );
		return $out;
	}
}
