<?php
/**
 * Block editor integration.
 *
 * @package BV_Map
 */

defined( 'ABSPATH' ) || exit;

/**
 * Load the block's script and styles in the editor.
 *
 * Renamed from shaiful_gutenberg_notice_block_admin(), which carried the name of
 * the tutorial it was copied from.
 */
function bv_map_enqueue_block_editor_assets() {
	wp_enqueue_script(
		'bv-map-block',
		BV_MAP_PLUGIN_URL . 'assets/js/block.js',
		array( 'wp-blocks', 'wp-element', 'wp-i18n' ),
		bv_map_asset_version( 'assets/js/block.js' ),
		true
	);

	wp_enqueue_style(
		'bv-map-block-editor',
		BV_MAP_PLUGIN_URL . 'assets/css/editor-block.css',
		array(),
		bv_map_asset_version( 'assets/css/editor-block.css' )
	);
}
add_action( 'enqueue_block_editor_assets', 'bv_map_enqueue_block_editor_assets' );
