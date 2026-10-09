<?php
/**
 * Script and style registration.
 *
 * Assets are registered on wp_enqueue_scripts and only enqueued when the
 * shortcode, widget or block actually renders, so pages without a map stay clean.
 *
 * @package BV_Map
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cache-busting version for a bundled asset.
 *
 * Previously every asset passed false as the version, so WordPress fell back to
 * its own version string and shipped updates behind a stale cache key.
 *
 * @param string $relative_path Path relative to the plugin root.
 * @return string
 */
function bv_map_asset_version( $relative_path ) {
	$file = BV_MAP_PLUGIN_DIR . ltrim( $relative_path, '/' );

	return file_exists( $file ) ? (string) filemtime( $file ) : BV_MAP_VERSION;
}

/**
 * Register the map's scripts and styles.
 *
 * Deliberately NOT hooked to wp_enqueue_scripts. The active theme enqueues
 *
 *     wp_enqueue_script( 'bv-list-stories-script', …, array( 'jquery', 'bv-map-js' ) )
 *
 * in the head group. If 'bv-map-js' is already registered when that runs,
 * WordPress resolves the dependency and prints the map script inside <head> —
 * before the shortcode has run, so wp_localize_script() attaches bvVarMap to an
 * already-printed handle and the data is dropped, and main.js executes before
 * the #map container exists.
 *
 * Leaving the handle unregistered until the shortcode asks for it means the
 * theme's dependency cannot resolve during wp_head, so WordPress defers that
 * whole chain to the footer — which is where it has always been printed.
 *
 * @see bv_map_enqueue_assets()
 */
function bv_map_register_assets() {
	// Mapbox GL and Turf are served from this domain rather than a CDN: the site
	// runs a consent manager, and third-party asset hosts receive visitor IPs
	// before consent is collected.
	wp_register_script(
		'turf',
		BV_MAP_PLUGIN_URL . 'assets/vendor/turf.min.js',
		array(),
		bv_map_asset_version( 'assets/vendor/turf.min.js' ),
		false
	);

	wp_register_script(
		'mapbox',
		BV_MAP_PLUGIN_URL . 'assets/vendor/mapbox-gl.js',
		array(),
		bv_map_asset_version( 'assets/vendor/mapbox-gl.js' ),
		false
	);

	wp_register_style(
		'mapbox-css',
		BV_MAP_PLUGIN_URL . 'assets/vendor/mapbox-gl.css',
		array(),
		bv_map_asset_version( 'assets/vendor/mapbox-gl.css' )
	);

	wp_register_script(
		'bv-map-js',
		BV_MAP_PLUGIN_URL . 'assets/js/main.js',
		array( 'jquery', 'turf', 'mapbox' ),
		bv_map_asset_version( 'assets/js/main.js' ),
		false
	);

	wp_register_style(
		'map-style',
		BV_MAP_PLUGIN_URL . 'assets/css/style.css',
		array( 'mapbox-css' ),
		bv_map_asset_version( 'assets/css/style.css' )
	);
}

/**
 * Enqueue the map assets and hand the script its configuration.
 *
 * Registration happens here rather than on wp_enqueue_scripts so that the
 * handle does not exist while the head scripts are being printed. See
 * bv_map_register_assets() for why that matters.
 *
 * @param int|string $post_id Post the map is rendering for.
 */
function bv_map_enqueue_assets( $post_id = 0 ) {
	if ( ! wp_script_is( 'bv-map-js', 'registered' ) ) {
		bv_map_register_assets();
	}

	// If something has already printed the handle, wp_localize_script() below
	// would silently discard bvVarMap. Warn while debugging rather than leaving
	// a blank map to diagnose from the browser.
	if ( wp_script_is( 'bv-map-js', 'done' ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		trigger_error( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			'BV Map: bv-map-js was printed before the shortcode ran, so bvVarMap could not be attached.',
			E_USER_WARNING
		);
	}

	wp_enqueue_script( 'bv-map-js' );
	wp_enqueue_style( 'map-style' );

	$attachments = bv_get_attachment_map_data( $post_id );
	$queried_obj = get_queried_object();

	$waypoints = ( $queried_obj instanceof WP_Term && ! empty( $attachments ) )
		? bv_get_geom_futureevents( $attachments[0], $queried_obj->term_id )
		: null;

	wp_localize_script(
		'bv-map-js',
		'bvVarMap',
		array(
			'plugin_dir'      => BV_MAP_PLUGIN_URL,
			'mapbox_api_key'  => defined( 'MAPBOX_TOKEN' ) ? MAPBOX_TOKEN : '',
			'maxZoom'         => 12,
			'minZoom'         => 4,
			'mapStyle'        => 'mapbox://styles/' . ( defined( 'MAPBOX_STYLE_OUTDOOR' ) ? MAPBOX_STYLE_OUTDOOR : 'mapbox/outdoors-v11' ),
			'sharpness'       => '1.12',
			'first_locations' => bv_map_first_locations( $attachments ),
			'waypoints'       => $waypoints,
			'route_styles'    => get_post_meta( get_the_ID(), 'route_style', true ),
			'queried_obj'     => $queried_obj,
			'user_location'   => bv_map_user_location(),
			'private_member'  => bv_map_member_privilege(),
			'rest_url'        => esc_url_raw( rest_url( BV_MAP_REST_NAMESPACE . '/points' ) ),
			'rest_nonce'      => wp_create_nonce( 'wp_rest' ),
		)
	);
}
