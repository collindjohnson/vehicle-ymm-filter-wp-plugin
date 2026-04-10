<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WYMM_Fitments {

	const CACHE_GROUP   = 'wymm_fitments';
	const VERSION_OPT   = 'wymm_cache_version';
	const VERSION_KEY   = 'wymm_cache_version';

	/** @var array<int,array<int,array>>|null in-request prefetch map keyed by product_id */
	private static $prefetch = array();

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . WYMM_TABLE;
	}

	private static function version() {
		$v = wp_cache_get( self::VERSION_KEY, self::CACHE_GROUP );
		if ( false === $v ) {
			$v = (int) get_option( self::VERSION_OPT, 1 );
			if ( $v < 1 ) {
				$v = 1;
			}
			wp_cache_set( self::VERSION_KEY, $v, self::CACHE_GROUP );
		}
		return (int) $v;
	}

	private static function bump_version() {
		$v = self::version() + 1;
		update_option( self::VERSION_OPT, $v, false );
		wp_cache_set( self::VERSION_KEY, $v, self::CACHE_GROUP );
		self::$prefetch = array();
	}

	private static function cache_key( $product_id, $variation_id ) {
		return 'v' . self::version() . '_p' . (int) $product_id . '_v' . ( null === $variation_id ? 'any' : (int) $variation_id );
	}

	private static function invalidate( $product_id, $variation_id = null ) {
		self::bump_version();
	}

	/**
	 * @return array<int,array{id:int,product_id:int,variation_id:int,make_slug:string,model_slug:string,year:int}>
	 */
	public static function get_for_product( $product_id, $variation_id = null ) {
		global $wpdb;
		$product_id = (int) $product_id;
		if ( ! $product_id ) {
			return array();
		}
		$cache_key = self::cache_key( $product_id, $variation_id );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return $cached;
		}
		$table = self::table();
		if ( null === $variation_id ) {
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, product_id, variation_id, make_slug, model_slug, year FROM {$table} WHERE product_id = %d ORDER BY make_slug, model_slug, year DESC",
				$product_id
			), ARRAY_A );
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, product_id, variation_id, make_slug, model_slug, year FROM {$table} WHERE product_id = %d AND variation_id = %d ORDER BY make_slug, model_slug, year DESC",
				$product_id,
				(int) $variation_id
			), ARRAY_A );
		}
		$result = $rows ? array_map( array( __CLASS__, 'cast_row' ), $rows ) : array();
		wp_cache_set( $cache_key, $result, self::CACHE_GROUP, HOUR_IN_SECONDS );
		return $result;
	}

	public static function add( $product_id, $variation_id, $make_slug, $model_slug, $year ) {
		global $wpdb;
		$make_slug  = sanitize_key( $make_slug );
		$model_slug = sanitize_key( $model_slug );
		$year       = (int) $year;
		if ( ! $product_id || '' === $make_slug || '' === $model_slug || ! $year ) {
			return false;
		}
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM " . self::table() . " WHERE product_id=%d AND variation_id=%d AND make_slug=%s AND model_slug=%s AND year=%d",
			$product_id, (int) $variation_id, $make_slug, $model_slug, $year
		) );
		if ( $existing ) {
			return (int) $existing;
		}
		$wpdb->insert( self::table(), array(
			'product_id'   => (int) $product_id,
			'variation_id' => (int) $variation_id,
			'make_slug'    => $make_slug,
			'model_slug'   => $model_slug,
			'year'         => $year,
		), array( '%d', '%d', '%s', '%s', '%d' ) );
		self::invalidate( $product_id, $variation_id );
		return (int) $wpdb->insert_id;
	}

	public static function delete_by_id( $id ) {
		global $wpdb;
		$id  = (int) $id;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT product_id, variation_id FROM " . self::table() . " WHERE id = %d",
			$id
		), ARRAY_A );
		$ok = (bool) $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
		if ( $ok && $row ) {
			self::invalidate( (int) $row['product_id'], (int) $row['variation_id'] );
		}
		return $ok;
	}

	public static function delete_for_variation( $product_id, $variation_id ) {
		global $wpdb;
		$result = $wpdb->delete( self::table(), array(
			'product_id'   => (int) $product_id,
			'variation_id' => (int) $variation_id,
		), array( '%d', '%d' ) );
		self::invalidate( $product_id, $variation_id );
		return $result;
	}

	const INSERT_CHUNK = 200;

	/**
	 * Replace the full fitment set for a product+variation with a chunked, transactional bulk insert.
	 * Each item: ['make_slug'=>..,'model_slug'=>..,'year'=>..]
	 */
	public static function replace_for_variation( $product_id, $variation_id, array $fitments ) {
		global $wpdb;
		$product_id   = (int) $product_id;
		$variation_id = (int) $variation_id;

		// Deduplicate + normalize up front so the delete/insert work is bounded.
		$rows = array();
		$seen = array();
		foreach ( $fitments as $f ) {
			if ( ! is_array( $f ) ) {
				continue;
			}
			$make  = isset( $f['make_slug'] ) ? sanitize_key( $f['make_slug'] ) : '';
			$model = isset( $f['model_slug'] ) ? sanitize_key( $f['model_slug'] ) : '';
			$year  = isset( $f['year'] ) ? (int) $f['year'] : 0;
			if ( '' === $make || '' === $model || ! $year ) {
				continue;
			}
			$key = $make . '|' . $model . '|' . $year;
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$rows[]       = array( $make, $model, $year );
		}

		$table = self::table();

		$wpdb->query( 'START TRANSACTION' );
		try {
			$wpdb->delete(
				$table,
				array(
					'product_id'   => $product_id,
					'variation_id' => $variation_id,
				),
				array( '%d', '%d' )
			);

			foreach ( array_chunk( $rows, self::INSERT_CHUNK ) as $chunk ) {
				$placeholders = array();
				$values       = array();
				foreach ( $chunk as $row ) {
					$placeholders[] = '(%d,%d,%s,%s,%d)';
					array_push( $values, $product_id, $variation_id, $row[0], $row[1], $row[2] );
				}
				$sql = "INSERT INTO {$table} (product_id, variation_id, make_slug, model_slug, year) VALUES " . implode( ',', $placeholders );
				$result = $wpdb->query( $wpdb->prepare( $sql, $values ) );
				if ( false === $result ) {
					throw new RuntimeException( 'Chunk insert failed' );
				}
			}

			$wpdb->query( 'COMMIT' );
		} catch ( Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			return;
		}

		self::invalidate( $product_id, $variation_id );
	}

	/**
	 * @return int[] product IDs that match the given YMM.
	 */
	public static function product_ids_for_ymm( $make_slug, $model_slug, $year ) {
		global $wpdb;
		$make_slug  = sanitize_key( $make_slug );
		$model_slug = sanitize_key( $model_slug );
		$year       = (int) $year;
		if ( '' === $make_slug || '' === $model_slug || ! $year ) {
			return array();
		}
		$cache_key = 'v' . self::version() . '_ymm_' . $make_slug . '_' . $model_slug . '_' . $year;
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return $cached;
		}
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT product_id FROM " . self::table() . " WHERE make_slug=%s AND model_slug=%s AND year=%d",
			$make_slug, $model_slug, $year
		) );
		$result = array_map( 'intval', $ids ?: array() );
		wp_cache_set( $cache_key, $result, self::CACHE_GROUP, HOUR_IN_SECONDS );
		return $result;
	}

	/**
	 * Prefetch all fitments for a product (including all variations) in one query.
	 * Returns an array keyed by variation_id (0 for simple product) whose value is
	 * the list of fitment rows. Safe to call repeatedly — in-request cached.
	 *
	 * @return array<int,array<int,array>>
	 */
	public static function prefetch_for_parent( $product_id ) {
		global $wpdb;
		$product_id = (int) $product_id;
		if ( ! $product_id ) {
			return array();
		}
		if ( isset( self::$prefetch[ $product_id ] ) ) {
			return self::$prefetch[ $product_id ];
		}
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, product_id, variation_id, make_slug, model_slug, year FROM " . self::table() . " WHERE product_id = %d ORDER BY make_slug, model_slug, year DESC",
			$product_id
		), ARRAY_A );
		$map = array();
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$cast = self::cast_row( $row );
				$vid  = (int) $cast['variation_id'];
				if ( ! isset( $map[ $vid ] ) ) {
					$map[ $vid ] = array();
				}
				$map[ $vid ][] = $cast;
			}
		}
		self::$prefetch[ $product_id ] = $map;
		return $map;
	}

	public static function get_for_variation_prefetched( $product_id, $variation_id ) {
		$map = self::prefetch_for_parent( $product_id );
		$vid = (int) $variation_id;
		return isset( $map[ $vid ] ) ? $map[ $vid ] : array();
	}

	public static function delete_for_product( $product_id ) {
		global $wpdb;
		$product_id = (int) $product_id;
		$result     = $wpdb->delete( self::table(), array( 'product_id' => $product_id ), array( '%d' ) );
		self::invalidate( $product_id );
		return $result;
	}

	private static function cast_row( $row ) {
		return array(
			'id'           => (int) $row['id'],
			'product_id'   => (int) $row['product_id'],
			'variation_id' => (int) $row['variation_id'],
			'make_slug'    => (string) $row['make_slug'],
			'model_slug'   => (string) $row['model_slug'],
			'year'         => (int) $row['year'],
		);
	}

	private static function public_label( $slug ) {
		$label = preg_replace( '/[-_]+/', ' ', (string) $slug );
		$label = is_string( $label ) ? trim( $label ) : '';
		return '' === $label ? (string) $slug : ucwords( $label );
	}

	public static function public_makes() {
		global $wpdb;

		$cache_key = 'v' . self::version() . '_public_makes';
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return $cached;
		}

		$table = self::table();
		$posts = $wpdb->posts;
		$rows  = $wpdb->get_col(
			"SELECT DISTINCT f.make_slug
			FROM {$table} f
			INNER JOIN {$posts} p ON p.ID = f.product_id
			WHERE p.post_type = 'product' AND p.post_status = 'publish'
			ORDER BY f.make_slug ASC"
		);

		$result = array();
		foreach ( $rows ?: array() as $slug ) {
			$slug = sanitize_key( $slug );
			if ( '' === $slug ) {
				continue;
			}
			$result[] = array(
				'slug' => $slug,
				'name' => self::public_label( $slug ),
			);
		}

		wp_cache_set( $cache_key, $result, self::CACHE_GROUP, HOUR_IN_SECONDS );
		return $result;
	}

	public static function public_models( $make_slug ) {
		global $wpdb;

		$make_slug = sanitize_key( $make_slug );
		if ( '' === $make_slug ) {
			return array();
		}

		$cache_key = 'v' . self::version() . '_public_models_' . $make_slug;
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return $cached;
		}

		$table = self::table();
		$posts = $wpdb->posts;
		$rows  = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT f.model_slug
			FROM {$table} f
			INNER JOIN {$posts} p ON p.ID = f.product_id
			WHERE p.post_type = 'product' AND p.post_status = 'publish' AND f.make_slug = %s
			ORDER BY f.model_slug ASC",
			$make_slug
		) );

		$result = array();
		foreach ( $rows ?: array() as $slug ) {
			$slug = sanitize_key( $slug );
			if ( '' === $slug ) {
				continue;
			}
			$result[] = array(
				'slug' => $slug,
				'name' => self::public_label( $slug ),
			);
		}

		wp_cache_set( $cache_key, $result, self::CACHE_GROUP, HOUR_IN_SECONDS );
		return $result;
	}

	public static function public_years( $make_slug, $model_slug ) {
		global $wpdb;

		$make_slug  = sanitize_key( $make_slug );
		$model_slug = sanitize_key( $model_slug );
		if ( '' === $make_slug || '' === $model_slug ) {
			return array();
		}

		$cache_key = 'v' . self::version() . '_public_years_' . $make_slug . '_' . $model_slug;
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return $cached;
		}

		$table = self::table();
		$posts = $wpdb->posts;
		$rows  = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT f.year
			FROM {$table} f
			INNER JOIN {$posts} p ON p.ID = f.product_id
			WHERE p.post_type = 'product' AND p.post_status = 'publish' AND f.make_slug = %s AND f.model_slug = %s
			ORDER BY f.year DESC",
			$make_slug,
			$model_slug
		) );

		$result = array_map( 'intval', $rows ?: array() );
		wp_cache_set( $cache_key, $result, self::CACHE_GROUP, HOUR_IN_SECONDS );
		return $result;
	}
}
