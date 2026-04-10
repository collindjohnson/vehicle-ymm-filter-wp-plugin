<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WYMM_API {

	const BASE        = 'https://api.wheel-size.com/v2';
	const CACHE_TTL   = WEEK_IN_SECONDS;
	const OPT_API_KEY = 'wymm_api_key';
	const OPT_TTL     = 'wymm_cache_ttl';

	public static function api_key() {
		if ( defined( 'WYMM_API_KEY' ) && WYMM_API_KEY ) {
			return trim( (string) WYMM_API_KEY );
		}
		$option = trim( (string) get_option( self::OPT_API_KEY, '' ) );
		return (string) apply_filters( 'wymm_api_key', $option );
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

	const MIN_YEAR = 1900;

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
		$max_year = (int) gmdate( 'Y' ) + 2;
		$years    = array();
		$rows     = isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : (array) $data;
		foreach ( $rows as $row ) {
			$candidate = null;
			if ( is_scalar( $row ) ) {
				$candidate = (int) $row;
			} elseif ( is_array( $row ) && isset( $row['year'] ) ) {
				$candidate = (int) $row['year'];
			} elseif ( is_array( $row ) && isset( $row['slug'] ) ) {
				$candidate = (int) $row['slug'];
			}
			if ( null === $candidate || $candidate < self::MIN_YEAR || $candidate > $max_year ) {
				continue;
			}
			$years[] = $candidate;
		}
		$years = array_values( array_unique( $years ) );
		rsort( $years );
		set_transient( $key, $years, self::ttl() );
		return $years;
	}

	public static function clear_cache() {
		global $wpdb;
		$like_value   = $wpdb->esc_like( '_transient_wymm_' ) . '%';
		$like_timeout = $wpdb->esc_like( '_transient_timeout_wymm_' ) . '%';
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$like_value,
			$like_timeout
		) );
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'wymm_fitments' );
		}
	}

	const RATE_WINDOW = 60;   // seconds
	const RATE_MAX    = 60;   // requests per window per site
	const RATE_GROUP  = 'wymm_rate';

	private static function rate_limit_ok() {
		$bucket = 'wymm_rate_' . (int) floor( time() / self::RATE_WINDOW );
		$ttl    = self::RATE_WINDOW + 5;

		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) {
			wp_cache_add( $bucket, 0, self::RATE_GROUP, $ttl );
			$count = wp_cache_incr( $bucket, 1, self::RATE_GROUP );
			if ( false === $count ) {
				return true;
			}
			return (int) $count <= self::RATE_MAX;
		}

		$lock   = $bucket . '_lock';
		$waited = 0;
		while ( ! wp_cache_add( $lock, 1, self::RATE_GROUP, 2 ) && $waited < 200 ) {
			usleep( 10000 );
			$waited += 10;
		}
		$count = (int) get_transient( $bucket );
		if ( $count >= self::RATE_MAX ) {
			wp_cache_delete( $lock, self::RATE_GROUP );
			return false;
		}
		set_transient( $bucket, $count + 1, $ttl );
		wp_cache_delete( $lock, self::RATE_GROUP );
		return true;
	}

	private static function request( $path, $query = array() ) {
		$key = self::api_key();
		if ( '' === $key ) {
			return new WP_Error( 'wymm_no_key', __( 'Wheel-Size API key is not configured.', 'wheel-ymm-filter' ) );
		}
		if ( ! self::rate_limit_ok() ) {
			return new WP_Error( 'wymm_rate_limited', __( 'Wheel-Size API rate limit reached. Please try again shortly.', 'wheel-ymm-filter' ) );
		}
		$query['user-key'] = $key;
		$url = self::BASE . $path . '?' . http_build_query( $query );

		$response = wp_remote_get( $url, array(
			'timeout'     => 5,
			'redirection' => 2,
			'sslverify'   => true,
			'headers'     => array( 'Accept' => 'application/json' ),
			'user-agent'  => 'WheelYMMFilter/' . WYMM_VERSION . '; ' . home_url( '/' ),
		) );

		if ( is_wp_error( $response ) ) {
			self::safe_log( 'request failed: ' . $response->get_error_message() );
			return new WP_Error( 'wymm_transport', __( 'Could not reach the Wheel-Size API.', 'wheel-ymm-filter' ) );
		}
		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		if ( $code < 200 || $code >= 300 ) {
			self::safe_log( 'HTTP ' . (int) $code . ' from ' . $path );
			$generic = ( 401 === $code || 403 === $code )
				? __( 'Wheel-Size API rejected the request. Check your API key.', 'wheel-ymm-filter' )
				: __( 'Wheel-Size API returned an error.', 'wheel-ymm-filter' );
			return new WP_Error( 'wymm_http_' . (int) $code, $generic );
		}
		if ( strlen( $body ) > 2 * MB_IN_BYTES ) {
			return new WP_Error( 'wymm_oversized', __( 'Wheel-Size API response was too large.', 'wheel-ymm-filter' ) );
		}
		$decoded = json_decode( $body, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			self::safe_log( 'json decode failed: ' . json_last_error_msg() );
			return new WP_Error( 'wymm_bad_json', __( 'Wheel-Size API returned invalid JSON.', 'wheel-ymm-filter' ) );
		}
		return $decoded;
	}

	private static function safe_log( $msg ) {
		if ( ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
			return;
		}
		$key = self::api_key();
		if ( '' !== $key ) {
			$msg = str_replace( $key, '***', (string) $msg );
		}
		error_log( '[WYMM] ' . $msg );
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
