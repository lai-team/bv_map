<?php
/**
 * Spatial query helpers.
 *
 * Previously includes/spatialquery.php.
 *
 * @package BV_Map
 */

defined( 'ABSPATH' ) || exit;

/**
 * Build a buffered bounding box around two corner points.
 *
 * @param float[] $pt1     First corner as [ lng, lat ].
 * @param float[] $pt2     Opposite corner as [ lng, lat ].
 * @param float   $factor  Buffer size as a multiple of the diagonal distance.
 * @param bool    $reverse Treat the points as [ lat, lng ] instead.
 * @return mixed Geometry understood by WP_GeoUtil, or null when unavailable.
 */
function bv_bufferbox( $pt1, $pt2, $factor, $reverse = false ) {
	if ( ! bv_map_has_geoutil() ) {
		return null;
	}

	// The original reversed two undefined variables ($ne/$sw rather than the
	// parameters) and discarded array_reverse()'s return value, so this branch
	// had never actually done anything.
	if ( $reverse ) {
		$pt1 = array_reverse( $pt1 );
		$pt2 = array_reverse( $pt2 );
	}

	$geom_pt1 = WP_GeoUtil::point( $pt1[0], $pt1[1] );
	$geom_pt2 = WP_GeoUtil::point( $pt2[0], $pt2[1] );
	$distance = WP_GeoUtil::st_distance( $geom_pt1, $geom_pt2 );

	return WP_GeoUtil::st_buffer(
		WP_GeoUtil::st_makeenvelope( $geom_pt1, $geom_pt2 ),
		$factor * $distance,
		WP_GeoUtil::ST_Buffer_Strategy( 'join_miter', 2 )
	);
}

/**
 * The category whose subtree is restricted to members.
 *
 * Mirrors the term id voyage's bv_query_exclude_private() excludes.
 *
 * @return int
 */
function bv_map_private_category_id() {
	return (int) apply_filters( 'bv_map_private_category_id', 1 );
}

/**
 * Tax query clause excluding member-only categories, for visitors without access.
 *
 * voyage hooks bv_query_exclude_private() onto pre_get_posts, but that callback
 * returns early unless $query->is_main_query(). Every query this plugin runs is a
 * secondary query, so the exclusion never applied to map data and the endpoint
 * filtered on post_status alone — which does not exclude published posts sitting
 * in the restricted category. This applies the same restriction explicitly.
 *
 * @return array Tax query clauses; empty when the viewer may see everything.
 */
function bv_map_private_tax_query() {
	if ( bv_map_member_privilege() ) {
		return array();
	}

	return array(
		array(
			'taxonomy'         => 'category',
			'field'            => 'term_id',
			'terms'            => array( bv_map_private_category_id() ),
			'include_children' => true,
			'operator'         => 'NOT IN',
		),
	);
}

/**
 * Find posts whose geometry intersects a polygon.
 *
 * @param string $geom_poly      GeoJSON polygon to intersect against.
 * @param string $qkey           Meta key holding the geometry.
 * @param string $compare_key    Key comparison operator (e.g. '=' or 'LIKE').
 * @param array  $included_terms Terms to restrict to.
 * @param string $taxonomy       Query var used for $included_terms.
 * @param array  $excluded_terms Unused; kept for signature compatibility.
 * @param string $post_type      Post type to query.
 * @param string $order          Sort direction.
 * @return WP_Query
 */
function bv_query_rectangle( $geom_poly, $qkey = 'geom_point', $compare_key = '=', $included_terms = array(), $taxonomy = 'category_name', $excluded_terms = array(), $post_type = 'post', $order = 'DESC' ) {
	$arr_query = array(
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'relation' => 'AND',
			array(
				'key'         => $qkey,
				'compare_key' => $compare_key,
				'compare'     => 'ST_INTERSECTS',
				'value'       => $geom_poly,
			),
			array(
				'key'     => 'geom_point',
				'compare' => 'EXISTS',
			),
		),
		'posts_per_page' => -1,
		'post_type'      => $post_type,
		'post_status'    => 'publish',
		'order'          => $order,
		'orderby'        => 'date',
	);

	$private_clauses = bv_map_private_tax_query();
	if ( ! empty( $private_clauses ) ) {
		$arr_query['tax_query'] = $private_clauses; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
	}

	if ( ! empty( $included_terms ) ) {
		$arr_query[ $taxonomy ] = implode( ',', array_map( 'intval', (array) $included_terms ) );
	}

	// The original suppressed errors here with @, which hid exactly the spatial
	// query failures worth seeing.
	return new WP_Query( $arr_query );
}
