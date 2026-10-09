<?php
/**
 * Shortcodes.
 *
 * @package BV_Map
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render the map container.
 *
 * Usage: [bv_map_shortcode post_id="123" height="40vh"]
 *
 * @param array|string $atts Shortcode attributes.
 * @return string
 */
function bv_map_shortcode( $atts ) {
	// WordPress passes an empty string, not an array, when a shortcode is used
	// without attributes — indexing it directly raised an illegal string offset.
	$atts = shortcode_atts(
		array(
			'post_id' => '',
			'height'  => '500px',
		),
		(array) $atts,
		'bv_map_shortcode'
	);

	// shortcode_atts always sets post_id, so the previous `?? get_the_ID()`
	// could never fire and callers that omitted it got an empty meta lookup.
	$post_id = $atts['post_id'] ? absint( $atts['post_id'] ) : get_the_ID();

	$GLOBALS['current_post_id'] = $post_id;

	bv_map_enqueue_assets( $post_id );

	$size = get_post_meta( $post_id, 'small_map_image_size', true );
	$size = $size ? (float) $size : 4;

	// A bare number is invalid CSS and collapses the container; only the theme
	// passing an explicit unit kept this working.
	$height = trim( (string) $atts['height'] );
	if ( '' === $height ) {
		$height = '500px';
	} elseif ( is_numeric( $height ) ) {
		$height .= 'px';
	}

	$style = sprintf(
		'.mapboxgl-marker{width:%srem;}#map{position:relative;width:100%%;height:%s;}',
		esc_attr( $size ),
		esc_attr( $height )
	);

	return sprintf(
		'<style>%s</style><div id="map" class="is-style-wide" style="width:100%%;"></div>',
		$style
	);
}
add_shortcode( 'bv_map_shortcode', 'bv_map_shortcode' );

/**
 * Render the story list.
 *
 * @return string
 */
function bv_list_shortcode() {
	$template = BV_MAP_PLUGIN_DIR . 'templates/list-stories.php';

	if ( ! file_exists( $template ) ) {
		return '';
	}

	// A shortcode callback must return its output. This previously included the
	// template directly, so the entire list printed at the top of the document
	// instead of where the shortcode sat.
	ob_start();
	include $template;

	return ob_get_clean();
}
add_shortcode( 'bv_list_shortcode', 'bv_list_shortcode' );
