<?php
/**
 * REST endpoint serving map points for the current viewport.
 *
 * Replaces includes/ajax.php and includes/ajax_geo.php, which were directly
 * reachable PHP files that bootstrapped WordPress by string-munging __DIR__ to
 * locate wp-load.php, read $_POST without sanitisation or a nonce, and took the
 * caller's word for whether they were a member.
 *
 * @package BV_Map
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the points route.
 */
function bv_map_register_rest_routes() {
	register_rest_route(
		BV_MAP_REST_NAMESPACE,
		'/points',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'bv_map_rest_points',
			// The feed is public, but which posts it contains is decided
			// server-side in bv_map_private_tax_query() — never by the caller.
			'permission_callback' => '__return_true',
			'args'                => array(
				'box_to_load' => array(
					'required'    => true,
					'type'        => 'object',
					'description' => __( 'GeoJSON polygon covering the area to load.', 'bv-map' ),
				),
				'term_id'     => array(
					'required'          => false,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
					'description'       => __( 'Journey category to restrict results to.', 'bv-map' ),
				),
				'taxonomy'    => array(
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_key',
				),
				'slug'        => array(
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_title',
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'bv_map_register_rest_routes' );

/**
 * Recursively clamp every number in a GeoJSON structure to a sane coordinate.
 *
 * The polygon arrives as nested arrays of [ lng, lat ] pairs. Rather than trust
 * the shape, walk it and coerce every scalar to a bounded float, dropping
 * anything that is not numeric.
 *
 * @param mixed $value Raw decoded value.
 * @param int   $depth Recursion guard.
 * @return mixed
 */
function bv_map_sanitize_geojson( $value, $depth = 0 ) {
	if ( $depth > 12 ) {
		return null;
	}

	if ( is_array( $value ) ) {
		$clean = array();
		foreach ( $value as $key => $item ) {
			$clean_key           = is_string( $key ) ? sanitize_key( $key ) : $key;
			$clean[ $clean_key ] = bv_map_sanitize_geojson( $item, $depth + 1 );
		}

		return $clean;
	}

	if ( is_numeric( $value ) ) {
		// Wide enough for a buffered world-spanning box, narrow enough to keep
		// nonsense out of the spatial query.
		return max( -1000.0, min( 1000.0, (float) $value ) );
	}

	if ( is_string( $value ) ) {
		return sanitize_text_field( $value );
	}

	if ( is_bool( $value ) || is_null( $value ) ) {
		return $value;
	}

	return null;
}

/**
 * Return the map features intersecting the requested box.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function bv_map_rest_points( WP_REST_Request $request ) {
	if ( ! bv_map_has_geoutil() ) {
		return new WP_Error(
			'bv_map_missing_geoutil',
			__( 'Spatial support is unavailable on this site.', 'bv-map' ),
			array( 'status' => 501 )
		);
	}

	$box = bv_map_sanitize_geojson( $request->get_param( 'box_to_load' ) );

	if ( empty( $box ) || ! is_array( $box ) ) {
		return new WP_Error(
			'bv_map_invalid_box',
			__( 'A valid bounding box is required.', 'bv-map' ),
			array( 'status' => 400 )
		);
	}

	$term_id  = (int) $request->get_param( 'term_id' );
	$taxonomy = $request->get_param( 'taxonomy' );
	$slug     = $request->get_param( 'slug' );

	$is_journey = $term_id > 0
		&& 'category' === $taxonomy
		&& ! in_array( $slug, bv_map_special_categories(), true );

	$geom = wp_json_encode( $box );

	if ( $is_journey ) {
		$posts       = bv_query_rectangle( $geom, 'geom_line2__', 'LIKE', array( $term_id ), 'cat' )->posts;
		$attachments = bv_get_geom_attachments( $posts, $term_id );
	} else {
		$posts       = bv_query_rectangle( $geom, 'geom_line2__', 'LIKE' )->posts;
		$attachments = bv_get_geom_attachments( $posts );
	}

	// The map expects a bare array of features.
	return rest_ensure_response( null === $attachments ? array() : $attachments );
}
