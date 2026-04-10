<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WYMM_Fitments {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . WYMM_TABLE;
	}

	/**
	 * @return array<int,array{id:int,product_id:int,variation_id:int,make_slug:string,model_slug:string,year:int}>
	 */
	public static function get_for_product( $product_id, $variation_id = null ) {
		global $wpdb;
		$table = self::table();
		$product_id = (int) $product_id;
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
		return $rows ? array_map( array( __CLASS__, 'cast_row' ), $rows ) : array();
	}

	public static function add( $product_id, $variation_id, $make_slug, $model_slug, $year ) {
		global $wpdb;
		$make_slug  = sanitize_key( $make_slug );
		$model_slug = sanitize_key( $model_slug );
		$year       = (int) $year;
		if ( ! $product_id || '' === $make_slug || '' === $model_slug || ! $year ) {
			return false;
		}
		// Avoid duplicates.
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
		return (int) $wpdb->insert_id;
	}

	public static function delete_by_id( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );
	}

	public static function delete_for_variation( $product_id, $variation_id ) {
		global $wpdb;
		return $wpdb->delete( self::table(), array(
			'product_id'   => (int) $product_id,
			'variation_id' => (int) $variation_id,
		), array( '%d', '%d' ) );
	}

	/**
	 * Replace the full fitment set for a product+variation with the given list.
	 * Each item: ['make_slug'=>..,'model_slug'=>..,'year'=>..]
	 */
	public static function replace_for_variation( $product_id, $variation_id, array $fitments ) {
		self::delete_for_variation( $product_id, $variation_id );
		foreach ( $fitments as $f ) {
			if ( ! is_array( $f ) ) {
				continue;
			}
			self::add(
				$product_id,
				$variation_id,
				isset( $f['make_slug'] ) ? $f['make_slug'] : '',
				isset( $f['model_slug'] ) ? $f['model_slug'] : '',
				isset( $f['year'] ) ? $f['year'] : 0
			);
		}
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
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT product_id FROM " . self::table() . " WHERE make_slug=%s AND model_slug=%s AND year=%d",
			$make_slug, $model_slug, $year
		) );
		return array_map( 'intval', $ids ?: array() );
	}

	public static function delete_for_product( $product_id ) {
		global $wpdb;
		return $wpdb->delete( self::table(), array( 'product_id' => (int) $product_id ), array( '%d' ) );
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
}
