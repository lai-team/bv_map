<?php
/**
 * Compatibility layer for symbols this plugin does not define.
 *
 * BV Map depends on functions and globals supplied by sibling plugins and the
 * active theme. None of them were guarded, so a deactivated plugin or a theme
 * switch took the whole site down with a fatal. Every external symbol is reached
 * through a wrapper here, and every wrapper degrades instead of fataling.
 *
 * Provider map:
 *   bv_parentcat, bv_childcat, bv_userlocation  -> plugins/voyage/voyage.php
 *   bv_member_privilege                         -> plugins/voyage/includes/search_filter.php
 *   $GLOBALS['special_categories']              -> plugins/bv_geotagged_media/index.php
 *   isImage, isVideo                            -> bv_geotagged_media, but stranded in a
 *                                                  .php_gentrit file WordPress never loads,
 *                                                  so they are defined here instead
 *   WP_GeoUtil                                  -> plugins/wp-geometa
 *   get_field, have_rows, get_sub_field         -> advanced-custom-fields-pro
 *
 * @package BV_Map
 */

defined( 'ABSPATH' ) || exit;

/**
 * Category slugs excluded from journey grouping.
 *
 * Read from the global published by bv_geotagged_media. That plugin only loads
 * before this one by alphabetical luck; when it is absent the global is null and
 * in_array() throws a TypeError on PHP 8.
 *
 * @return string[]
 */
function bv_map_special_categories() {
	$cats = isset( $GLOBALS['special_categories'] ) ? $GLOBALS['special_categories'] : null;

	return is_array( $cats ) ? $cats : array();
}

/**
 * Resolve a set of category objects to their top-level ancestors.
 *
 * @param array $categories Category objects.
 * @return int[] Term IDs.
 */
function bv_map_parent_cats( $categories ) {
	if ( function_exists( 'bv_parentcat' ) ) {
		return (array) bv_parentcat( $categories );
	}

	// Without voyage, fall back to the categories as given.
	return array_values( wp_list_pluck( (array) $categories, 'term_id' ) );
}

/**
 * Whether the current user may see member-only content.
 *
 * Mirrors voyage's bv_member_privilege(). This is a server-side capability check
 * and must never be derived from request input.
 *
 * @return bool
 */
function bv_map_member_privilege() {
	if ( function_exists( 'bv_member_privilege' ) ) {
		return (bool) bv_member_privilege();
	}

	return current_user_can( 'read_private_posts' );
}

/**
 * Visitor location, normalised to an object with latitude and longitude.
 *
 * bv_userlocation() returns null when GeoIP resolution fails; the map script
 * dereferenced it unconditionally.
 *
 * @return object
 */
function bv_map_user_location() {
	$location = function_exists( 'bv_userlocation' ) ? bv_userlocation() : null;

	if ( ! is_object( $location ) || ! isset( $location->latitude, $location->longitude ) ) {
		return (object) array(
			'latitude'  => 1,
			'longitude' => -1,
		);
	}

	return $location;
}

/**
 * Whether the spatial meta layer (WP-GeoMeta) is available.
 *
 * @return bool
 */
function bv_map_has_geoutil() {
	return class_exists( 'WP_GeoUtil' );
}

/**
 * Whether Advanced Custom Fields is available.
 *
 * @return bool
 */
function bv_map_has_acf() {
	return function_exists( 'get_field' );
}

/**
 * Read an ACF field without requiring ACF.
 *
 * @param string     $selector Field name.
 * @param int|string $post_id  Post ID.
 * @param mixed      $default  Returned when ACF is absent or the field is empty.
 * @return mixed
 */
function bv_map_get_field( $selector, $post_id = false, $default = null ) {
	if ( ! bv_map_has_acf() ) {
		return $default;
	}

	$value = get_field( $selector, $post_id );

	return ( null === $value || false === $value || '' === $value ) ? $default : $value;
}

if ( ! function_exists( 'isImage' ) ) {
	/**
	 * Whether a URL points at an image, by extension.
	 *
	 * Defined in bv_geotagged_media/gtm_plugin_main.php_gentrit, which WordPress
	 * never loads because of the file extension. Reproduced here so the story
	 * list template does not fatal.
	 *
	 * @param string $url File URL or path.
	 * @return bool
	 */
	function isImage( $url ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
		return bv_map_url_has_extension( $url, array( 'JPG', 'JPEG', 'PNG', 'GIF', 'TIFF', 'RAW', 'WEBP' ) );
	}
}

if ( ! function_exists( 'isVideo' ) ) {
	/**
	 * Whether a URL points at a video, by extension.
	 *
	 * @param string $url File URL or path.
	 * @return bool
	 */
	function isVideo( $url ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
		return bv_map_url_has_extension(
			$url,
			array( 'WEBM', 'MPG', 'MP2', 'MPEG', 'MPE', 'MPV', 'OGG', 'MP4', 'M4P', 'M4V', 'AVI', 'WMV', 'MOV', 'QT', 'FLV', 'SWF' )
		);
	}
}

/**
 * Match a URL's extension against a whitelist.
 *
 * Strips any query string first, which the original extension check did not.
 *
 * @param string   $url        File URL or path.
 * @param string[] $extensions Uppercase extensions to accept.
 * @return bool
 */
function bv_map_url_has_extension( $url, $extensions ) {
	if ( ! is_string( $url ) || '' === $url ) {
		return false;
	}

	$path      = wp_parse_url( $url, PHP_URL_PATH );
	$extension = pathinfo( $path ? $path : $url, PATHINFO_EXTENSION );

	return in_array( strtoupper( $extension ), $extensions, true );
}
