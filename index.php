<?php
/**
 * Plugin Name: BV Map
 * Description: Displays geotagged posts on a Mapbox map, joining them into journey lines.
 * Plugin URI: https://github.com/lai-team/bv_map
 * Author: Gentrit, Lai Consulting Team
 * Author URI: https://github.com/gentritbiba
 * GitLab Plugin URI: https://gitlab.com/beauvoyage/bv_map
 * Version: 0.2.0
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * Text Domain: bv-map
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * This file is the plugin's entry point and must keep its name: WordPress
 * identifies a plugin by "<folder>/<file>.php", so renaming it would make an
 * upgrade look like a different plugin and silently deactivate the live one.
 *
 * @package BV_Map
 */

defined( 'ABSPATH' ) || exit;

define( 'BV_MAP_VERSION', '0.2.0' );
define( 'BV_MAP_PLUGIN_FILE', __FILE__ );
define( 'BV_MAP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BV_MAP_REST_NAMESPACE', 'bv-map/v1' );

// Consumed by themes/9to5voyage/custom-widgets/map.php as well as this plugin.
if ( ! defined( 'BV_MAP_PLUGIN_URL' ) ) {
	define( 'BV_MAP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// Versions of the vendored libraries in assets/vendor/, for reference when
// updating them. They are no longer used to build CDN URLs.
define( 'BV_MAP_MAPBOX_VER', 'v2.4.0' );
define( 'BV_MAP_TURF_VER', '6.5.0' );

require_once BV_MAP_PLUGIN_DIR . 'includes/compat.php';
require_once BV_MAP_PLUGIN_DIR . 'includes/geo-query.php';
require_once BV_MAP_PLUGIN_DIR . 'includes/geo-data.php';
require_once BV_MAP_PLUGIN_DIR . 'includes/assets.php';
require_once BV_MAP_PLUGIN_DIR . 'includes/shortcodes.php';
require_once BV_MAP_PLUGIN_DIR . 'includes/widget.php';
require_once BV_MAP_PLUGIN_DIR . 'includes/block.php';
require_once BV_MAP_PLUGIN_DIR . 'includes/rest.php';

/**
 * Load translations.
 */
function bv_map_load_textdomain() {
	load_plugin_textdomain( 'bv-map', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'bv_map_load_textdomain' );

/**
 * Missing dependencies, as human-readable names.
 *
 * @return string[]
 */
function bv_map_missing_dependencies() {
	$missing = array();

	if ( ! bv_map_has_geoutil() ) {
		$missing[] = 'WP-GeoMeta';
	}

	if ( ! bv_map_has_acf() ) {
		$missing[] = 'Advanced Custom Fields';
	}

	if ( ! defined( 'MAPBOX_TOKEN' ) ) {
		$missing[] = __( "a MAPBOX_TOKEN constant in wp-config.php", 'bv-map' );
	}

	return $missing;
}

/**
 * Warn in wp-admin when a dependency is missing.
 *
 * These were previously unguarded: an undefined MAPBOX_TOKEN is a fatal Error on
 * PHP 8, and a missing WP_GeoUtil fataled on the first spatial query.
 */
function bv_map_dependency_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$missing = bv_map_missing_dependencies();
	if ( empty( $missing ) ) {
		return;
	}

	printf(
		'<div class="notice notice-warning"><p><strong>%s</strong> %s</p></div>',
		esc_html__( 'BV Map:', 'bv-map' ),
		esc_html(
			sprintf(
				/* translators: %s: comma-separated list of missing dependencies. */
				__( 'the map is disabled because this site is missing %s.', 'bv-map' ),
				implode( ', ', $missing )
			)
		)
	);
}
add_action( 'admin_notices', 'bv_map_dependency_notice' );
