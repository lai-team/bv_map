<?php
/**
 * Turning posts into GeoJSON features for the map.
 *
 * bv_get_geompoint() and bv_get_attachment_map_data() are called from the active
 * theme (digital-nomad-child) and from 9to5voyage, so their names and signatures
 * are public API. Do not rename them.
 *
 * @package BV_Map
 */

defined( 'ABSPATH' ) || exit;

/**
 * Read a post's GeoJSON point, falling back to its featured image.
 *
 * @param WP_Post|object $post_obj Post object.
 * @return object|null GeoJSON feature, or null when the post has no usable geometry.
 */
function bv_get_geompoint( $post_obj ) {
	if ( ! is_object( $post_obj ) || empty( $post_obj->ID ) ) {
		return null;
	}

	$media_id = get_post_thumbnail_id( $post_obj->ID );

	$attachment = json_decode( get_post_meta( $post_obj->ID, 'geom_point', true ) );
	if ( empty( $attachment ) && $media_id ) {
		$attachment = json_decode( get_post_meta( $media_id, 'geom_point', true ) );
	}

	// json_decode returns null on malformed meta and a scalar on a bare value;
	// only an object carries the geometry/properties shape the map expects.
	if ( ! is_object( $attachment ) ) {
		return null;
	}

	if ( ! isset( $attachment->properties ) || ! is_object( $attachment->properties ) ) {
		$attachment->properties = new stdClass();
	}

	$attachment->id                     = $post_obj->ID;
	$attachment->properties->posttype   = $post_obj->post_type;
	$attachment->properties->title      = $post_obj->post_title;

	if ( $media_id ) {
		$attachment->properties->id        = $media_id;
		$attachment->properties->datetime  = get_post_meta( $media_id, 'datetime', true );
		$thumbnail                         = wp_get_attachment_image_src( $media_id, 'thumbnail' );
		$attachment->properties->thumbnail = $thumbnail ? $thumbnail[0] : '';
	}

	return $attachment;
}

/**
 * Build GeoJSON features for a set of posts, including their journey lines.
 *
 * @param array    $posts  Post objects.
 * @param int|null $cat_id Restrict journeys to this category.
 * @return array|null Features, or null when none of the posts carry geometry.
 */
function bv_get_geom_attachments( $posts, $cat_id = null ) {
	$attachments = array();

	foreach ( (array) $posts as $post_obj ) {
		$attachment = bv_get_geompoint( $post_obj );
		if ( empty( $attachment ) ) {
			continue;
		}

		$cats = $cat_id
			? array( (int) $cat_id )
			: bv_map_parent_cats( get_the_category( $post_obj->ID ) );

		$attachment->properties->journeys = $cats;

		foreach ( $cats as $cat ) {
			$metakey                              = 'geom_line2__' . $cat;
			$attachment->properties->{$metakey} = json_decode( get_post_meta( $post_obj->ID, $metakey, true ) );
		}

		$attachments[] = $attachment;
	}

	// Historically returned null rather than an empty array; callers still rely
	// on the falsy-but-not-array case, so preserve it.
	return empty( $attachments ) ? null : $attachments;
}

/**
 * Future-dated events near a reference point.
 *
 * The body of this function has been commented out since before the repository
 * existed, so it returns an empty array and the map's waypoint/itinerary layers
 * never receive data (bvVarMap.waypoints is null in production). Retained
 * deliberately rather than removed. Reviving it means restoring the tribe_events()
 * query below and verifying it against the installed The Events Calendar.
 *
 * @param object   $ref_attachment Reference feature.
 * @param int|null $cat_id         Category to scope to.
 * @return array Always empty in the current build.
 */
function bv_get_geom_futureevents( $ref_attachment, $cat_id = null ) {
	$attachments = array();

	/*
	$special_categories_local = bv_map_special_categories();
	$date_format = get_option( 'date_format' );
	$order       = 'DESC';
	$direction   = '>';

	$args = $cat_id ? array(
		'cat'            => $cat_id,
		'posts_per_page' => -1,
	) : array();

	if ( ! empty( $ref_attachment ) ) {
		$args += array(
			'post__not_in' => array( $ref_attachment->id ),
			'meta_query'   => array(
				array(
					'key'     => '_EventStartDate',
					'value'   => $ref_attachment->properties->datetime,
					'type'    => 'DATETIME',
					'compare' => $direction,
				),
				array(
					'key'     => '_EventHideFromUpcoming',
					'compare' => 'NOT EXISTS',
				),
				'relation'    => 'AND',
			),
		);
	}

	$events_orm = tribe_events();
	$events_orm->order_by( 'event_date', $order );
	$events_orm->by_args( $args );
	$query = $events_orm->get_query();
	$query->get_posts();
	$posts = $query->posts;

	foreach ( $posts as $post_obj ) {
		$cats       = $cat_id ? bv_childcat( $cat_id, get_the_category( $post_obj->ID ) ) : bv_map_parent_cats( get_the_category( $post_obj->ID ) );
		$attachment = bv_get_geompoint( $post_obj );

		$attachment->properties->journeys       = $cats;
		$attachment->properties->datetime_start = $post_obj->_EventStartDate ?? $post_obj->post_date;
		$attachment->properties->datetime_end   = $post_obj->_EventEndDate ?? $post_obj->post_date;
		$attachment->properties->date           = function_exists( 'tribe_get_start_date' )
			? tribe_get_start_date( $post_obj->ID, false )
			: date( $date_format, strtotime( $attachment->properties->datetime_start ) );

		if ( tribe_get_start_date( $post_obj->ID, false ) !== tribe_get_end_date( $post_obj->ID, false ) ) {
			$attachment->properties->datetime_delta = human_time_diff(
				strtotime( $attachment->properties->datetime_start ),
				strtotime( $attachment->properties->datetime_end )
			);
		}

		$attachments[] = $attachment;
	}

	if ( ! empty( $ref_attachment ) ) {
		$attachments[] = $ref_attachment;
	}
	*/

	return $attachments;
}

/**
 * Collect the initial set of map features for the current view.
 *
 * Called by 9to5voyage/custom-widgets/map.php as well as this plugin.
 *
 * @param int $post_id Unused; retained for signature compatibility.
 * @return array|null
 */
function bv_get_attachment_map_data( $post_id ) {
	$args = array(
		'post_type'   => 'post',
		'numberposts' => 10,
		'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'     => 'geom_point',
				'compare' => 'EXISTS',
			),
		),
	);

	$category    = get_queried_object();
	$is_journey  = $category
		&& is_a( $category, 'WP_Term' )
		&& 'category' === $category->taxonomy
		&& ! in_array( $category->slug, bv_map_special_categories(), true );

	$private_clauses = bv_map_private_tax_query();
	if ( ! empty( $private_clauses ) ) {
		$args['tax_query'] = $private_clauses; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
	}

	if ( $is_journey ) {
		$args['cat'] = $category->term_id;

		return bv_get_geom_attachments( get_posts( $args ), $category->term_id );
	}

	return bv_get_geom_attachments( get_posts( $args ) );
}

/**
 * First few coordinates, used to frame the map on load.
 *
 * @param array|null $attachments Features.
 * @param int        $limit       How many coordinate pairs to return.
 * @return array
 */
function bv_map_first_locations( $attachments, $limit = 3 ) {
	if ( empty( $attachments ) ) {
		return array();
	}

	$coords = array();
	foreach ( $attachments as $attachment ) {
		if ( isset( $attachment->geometry->coordinates ) && ! empty( $attachment->geometry->coordinates ) ) {
			$coords[] = $attachment->geometry->coordinates;
		}

		if ( count( $coords ) >= $limit ) {
			break;
		}
	}

	return $coords;
}
