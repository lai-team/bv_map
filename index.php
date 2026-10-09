<?php
/**
 * Plugin Name: BV Map 
 * Description: Display a map which conmtains images and their locations based on some posts given
 * Plugin URI: https://github.com/digfish/geotagged-media
 * Author: Gentrit, Lai Consulting Team
 * Author URI: https://github.com/gentritbiba
 * GitLab Plugin URI: https://gitlab.com/beauvoyage/bv_map
 * Version: 0.1
 */

define('MAPBOX_VER','v2.4.0');
//define('MAPBOX_VER','v3.13.0');
//define('TURF_VER','7.2.0');
define('TURF_VER','6.5.0');
define("BV_MAP_PLUGIN_URL",plugin_dir_url( __FILE__ ) );
//define("BV_MAP_PLUGIN_DIR",plugin_dir_path( __FILE__ ) );

include_once( __DIR__ . '/includes/spatialquery.php' );

/**
 * Returns an array from an object
 * @param object $d the object we want to retrun as array
 * @return array 
 */    
function objectToArray($d) {
	if (is_object($d)) {
		// Gets the properties of the given object
		// with get_object_vars function
		$d = get_object_vars($d);
	}

	if (is_array($d)) {
		/*
		 * Return array converted to object
		 * Using __FUNCTION__ (Magic constant)
		 * for recursive call
		 */
		return array_map(__FUNCTION__, $d);
	}
	else {
		// Return array
		return $d;
	}
}

function bv_get_geom_attachments($posts,$cat_id=NULL){
	foreach($posts as $post_obj){
		// var_dump($post_obj->ID);
		$cats = $cat_id? array( (int) $cat_id): bv_parentcat( get_the_category($post_obj->ID) );
		$media_id = get_post_thumbnail_id($post_obj->ID);
		$attachment = bv_get_geompoint($post_obj);
		//$attachment->properties->thumbnail = wp_get_attachment_thumb_url ( $media_id);
		if(!empty($attachment)){
			$attachment->properties->journeys = $cats;
			foreach ( $attachment-> properties->journeys as $cat){
				$metakey='geom_line2__'.$cat;
				$attachment->properties->{$metakey}= json_decode(get_post_meta($post_obj->ID,$metakey,TRUE));
			}

			$attachments[] = $attachment;
		}
	}
	/*
	usort($attachments, function($a, $b) {
		return (int)$a['datetime'] - (int)$b['datetime'];
	});
	 */
	return $attachments??null;
}

function bv_get_geom_futureevents($ref_attachment,$cat_id=NULL){
	$special_categories_local=$GLOBALS['special_categories'];  
	$attachments = array();
	//error_log(print_r('cat: '. json_encode($category),true));
	//if (! $category || ! is_a($category,'WP_Term') || ! $category->taxonomy =='category' || in_array($category->slug,$special_categories_local)) return;
	global $wpdb;
	$date_format = get_option( 'date_format' );
	$order      = 'DESC';
	$direction  = '>';

	$args = $cat_id?array( 'cat' => $cat_id,
				'posts_per_page'=>-1,):array();
	if (isset($ref_attachment) && !empty($ref_attachment)){
		$args += array(
			'post__not_in' => [ $ref_attachment->id ],
			'meta_query'     => [
				[
					'key'     => '_EventStartDate',
					'value'   => $ref_attachment->properties->datetime,
					'type'    => 'DATETIME',
					'compare' => $direction,
				],
				[
					'key'     => '_EventHideFromUpcoming',
					'compare' => 'NOT EXISTS',
				],
				'relation'    => 'AND',
			],
		);
	}

	/*
        $events_orm = tribe_events();
        $events_orm->order_by( 'event_date', $order );
        $events_orm->by_args( $args );
        $query = $events_orm->get_query();

        // Fetch the posts
        $query->get_posts();
        $posts = $query->posts;

	foreach($posts as $post_obj){
		$cats = $cat_id? bv_childcat( $cat_id,get_the_category($post_obj->ID)): bv_parentcat( get_the_category($post_obj->ID)) ;
		//$cats = $cat_id? array($cat_id): bv_parentcat( get_the_category($post_obj->ID)) ;
		//error_log(print_r('future Events: ' . json_encode($post_obj),true));
		$attachment = bv_get_geompoint($post_obj);
		$attachment->properties->journeys = $cats;
		$attachment->properties->datetime_start = $post_obj->_EventStartDate??$post_obj->post_date;
		$attachment->properties->datetime_end = $post_obj->_EventEndDate??$post_obj->post_date;
		$attachment->properties->date = 
			function_exists('tribe_get_start_date')?tribe_get_start_date($post_obj->ID,false):date( $date_format, strtotime($attachment->properties->datetime_start )) ;
		//$attachment->properties->date = date( $date_format, strtotime($attachment->properties->datetime_start )) ;
		//$attachment->properties->date = DateTime::createFromFormat('Y-m-d H:i:s', $attachment->properties->datetime_start)->format('M j');
		//if($attachment->properties->datetime_start != $attachment->properties->datetime_end){
		if( tribe_get_start_date($post_obj->ID,false) != tribe_get_end_date($post_obj->ID,false)){
			$attachment->properties->datetime_delta = human_time_diff(strtotime($attachment->properties->datetime_start ) , 
			       						strtotime($attachment->properties->datetime_end ) );  
		}
	#	foreach ( $attachment-> properties->journeys as $cat){
	#		$metakey='geom_line2__'.$cat;
	#		$attachment->properties->{$metakey}= json_decode(get_post_meta($post_obj->ID,$metakey,TRUE));
	#	}
		$attachments[] = $attachment;
	}
	if (isset($ref_attachment) && !empty($ref_attachment)){
		$attachments[] = $ref_attachment;
	}
		 */
	 
	return $attachments;
}


function bv_get_attachment_map_data($post_id){
	$special_categories_local=$GLOBALS['special_categories'];  
	$attachments=array();
	$args = array(
		'post_type' => 'post',
	);
	$category = get_queried_object();
	if( $category && is_a($category,'WP_Term') && $category->taxonomy =='category' && !in_array($category->slug,$special_categories_local)){
		$args += array(
			'numberposts' => 10,
			'cat'   => $category->term_id  ,
			'meta_query' => array(
				array(
				 'key' => 'geom_point',
				 'compare' => 'EXISTS' // this should work...
				),
			)
		);
		$posts=get_posts($args);
		$attachments = bv_get_geom_attachments($posts,$category->term_id);
		//$events = bv_get_geom_events($post,$category);
	}else{ 
		$args += array(
			'numberposts'=> 10,
			'meta_query' => array(
				array(
				 'key' => 'geom_point',
				 'compare' => 'EXISTS' // this should work...
				),
			)
		);
		$posts=get_posts($args);
		$attachments = bv_get_geom_attachments($posts);
	}
	//error_log(print_r('json attachments:   '. json_encode($attachments),true));
	return $attachments;
}

/*
function getClientIP(){  
	if (array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER)){
		return explode(",", strval($_SERVER["HTTP_X_FORWARDED_FOR"]))[0]; 
	}else if (array_key_exists('REMOTE_ADDR', $_SERVER)) { 
		return $_SERVER["REMOTE_ADDR"]; 
	}else if (array_key_exists('HTTP_CLIENT_IP', $_SERVER)) {
		return $_SERVER["HTTP_CLIENT_IP"]; 
	} 

	return '';
}
 */


//Map shortcode start
function bv_map_shortcode($atts){
	//error_log(print_r('qobj: '.json_encode(get_queried_object()) . BV_MAP_PLUGIN_URL,true));
	// wp_enqueue_script( 'turf', 'https://npmcdn.com/@turf/turf/turf.min.js', array('jquery'),false);
	//wp_enqueue_script( 'turf', 'https://unpkg.com/@turf/turf@6.2.0-alpha.1/dist/turf.min.js', array('jquery'),false);
	wp_enqueue_script( 'turf', 'https://unpkg.com/@turf/turf@' . TURF_VER .'/turf.min.js', array('jquery'),false);
	wp_enqueue_script( 'mapbox', 'https://api.tiles.mapbox.com/mapbox-gl-js/'. MAPBOX_VER .'/mapbox-gl.js', array('jquery'),false);
	wp_enqueue_script( 'bv-map-js', BV_MAP_PLUGIN_URL . 'assets/js/main.js', array('jquery','turf','mapbox'),false);
	wp_enqueue_style( 'mapbox-css','https://api.tiles.mapbox.com/mapbox-gl-js/'. MAPBOX_VER .'/mapbox-gl.css');
	wp_enqueue_style( 'map-style',BV_MAP_PLUGIN_URL . 'assets/css/style.css');


	$first_attachments = bv_get_attachment_map_data(get_the_ID());
	//error_log(print_r('1st Attach: ' . json_encode($first_attachment),true));
	$waypoints	= ( get_queried_object() && $first_attachments )?bv_get_geom_futureevents($first_attachments[0],get_queried_object()->term_id):null;
	//error_log(print_r('wayponTs: ' . json_encode($waypoints),true));
	if ($first_attachments || $waypoints){
		$tmp_locations       = array_filter(array_map( function($a) { return $a->geometry->coordinates; }, $first_attachments??$waypoints ));
		$first_locations       = array_splice($tmp_locations,0,3);
	}else{
		$first_locations=[];
	}
	//$first_locations       = array_splice(array_filter(array_map( function($a) { return $a->geometry->coordinates; }, $first_attachments??$waypoints )),0,3);
	// Send variables to the javascript file
	wp_localize_script( 'bv-map-js', 'bvVarMap', array(
		'plugin_dir'        => BV_MAP_PLUGIN_URL,
		'mapbox_api_key'    => MAPBOX_TOKEN,
		'maxZoom'           => 12, //Maximum zoom on map fit bounds
		'minZoom'	=> 4,
		'mapStyle'          => 'mapbox://styles/' . MAPBOX_STYLE_OUTDOOR, // The mapboxjs style
		//'mapStyle'          => "mapbox://styles/gentritbiba/ck4xa3sus1bm21cpk4mqhwfks", // The mapboxjs style
		'sharpness'         => "1.12", // Decides how sharp will the curves connecting 2 lines be 
		'first_locations'       => $first_locations,
		//'waypoints'	=> get_queried_object()?bv_get_geom_futureevents($first_attachments[0],get_queried_object()->term_id):null,
		'waypoints'	=> $waypoints,
		'route_styles'      => get_post_meta( get_the_ID(), 'route_style', true ), 
		'queried_obj'	=> get_queried_object(),
		'user_location'     => bv_userlocation()  ,
		'private_member'	=>  bv_member_privilege(),
		//'user_location'     => json_encode(bv_userlocation() )  ,
	));
	// This shortcode takes the post_id attribute ( The post id of the post you want to extract the media from )
	extract(shortcode_atts( array('post_id' => ''), $atts));
	$GLOBALS['current_post_id'] = $post_id??get_the_ID();

	$size = get_post_meta($post_id,'small_map_image_size',true);
	if(!$size)$size=4;
	$height = $atts['height']??500;
	// require_once 'map.php';
	return "
    <style>
	.mapboxgl-marker{
	    width: {$size}rem;
	}
	#map { position: relative; width: 100%;height:{$height};}
	</style>
    <div id='map' style='width:100%;' class='is-style-wide'></div>
    ";
}
add_shortcode( 'bv_map_shortcode', 'bv_map_shortcode' );



//Map shortcode start
function bv_list_shortcode(){
	include( __DIR__ . '/list-stories-view.php');
}
add_shortcode( 'bv_list_shortcode', 'bv_list_shortcode' );


// Map shortcode end


// Register and load the widget
function bv_map_load_widget() {
	register_widget( 'bv_map_widget' );
}
add_action( 'widgets_init', 'bv_map_load_widget' );

// Creating the widget 
class bv_map_widget extends WP_Widget {

	function __construct() {
		parent::__construct(

			// Base ID of your widget
			'bv_map_widget', 

			// Widget name will appear in UI
			__('beauVoyage Map', 'bv_map_widget_domain'), 

			// Widget description
			array( 'description' => __( 'illustrate journeys geographically', 'bv_map_widget_domain' ), )
		);
	}

	// Creating widget front-end

	public function widget( $args, $instance ) {
		$title = apply_filters( 'widget_title', $instance['title'] );

		// before and after widget arguments are defined by themes
		//echo $args['before_widget'];
		if ( ! empty( $title ) )
			echo $args['before_title'] . $title . $args['after_title'];

		// This is where you run the code and display the output
		echo do_shortcode( "[bv_map_shortcode asd=12]" );
	}

	// Widget Backend 
	public function form( $instance ) {
		if ( isset( $instance[ 'title' ] ) ) {
			$title = $instance[ 'title' ];
		}
		else {
			$title = __( 'New title', 'bv_map_widget_domain' );
		}
		// Widget admin form
?>
    <p>
    <label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label> 
    <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
    </p>
<?php 
	}

	// Updating widget replacing old instances with new
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
		return $instance;
	}
} // Clas


// Load assets for wp-admin when editor is active
function shaiful_gutenberg_notice_block_admin() {
	wp_enqueue_script(
		'gutenberg-map-block',
		plugins_url( 'assets/js/block.js', __FILE__ ),
		array( 'wp-blocks', 'wp-element' )
	);

	wp_enqueue_style(
		'gutenberg-map-block',
		plugins_url( 'assets/css/editor-block.css', __FILE__ ),
		array()
	);
}

add_action( 'enqueue_block_editor_assets', 'shaiful_gutenberg_notice_block_admin' );

// Load assets for frontend
function shaiful_gutenberg_notice_block_frontend() {

	wp_enqueue_style(
		'gutenberg-map-block',
		plugins_url( 'assets/css/front-block.css', __FILE__ ),
		array()
	);
}
add_action( 'wp_enqueue_scripts', 'shaiful_gutenberg_notice_block_frontend' );
