<?php
if(!isset($_POST['mapboxjs_bounding_box'])){
    header( '400 Bad Request' );
    exit( 'Bad Request' );
}
$path = preg_replace('/wp-content.*$/','',__DIR__);
require_once($path.'wp-load.php');
// print_r($_POST);
$bb = $_POST['mapboxjs_bounding_box'];
$queried_obj = (object)  $_POST['query'] ;
$member = $_POST['member'];
//error_log(print_r('q obj: '. json_encode( $queried_obj),true));
//$box_toload=bv_bufferbox($bb['ne'],$bb['sw'],1);
//$box_view=bv_bufferbox($bb['ne'],$bb['sw'],0.7);
$box_toload = $_POST['box_to_load'];

//error_log(print_r('box to load: '. json_encode($box_toload),true));

//if($member) remove_action( 'pre_get_posts', 'bv_query_exclude_private');

$special_categories_local=$GLOBALS['special_categories'];
if( property_exists($queried_obj, 'taxonomy') && $queried_obj->taxonomy=='category' && !in_array($queried_obj->slug,$special_categories_local)){
//if( property_exists($queried_obj, 'taxonomy') && $queried_obj->taxonomy=='category' ){
	//$posts = bv_query_rectangle($box_toload,'geom_point',"=",array($queried_obj->term_id),'cat')->posts;
	$posts = bv_query_rectangle(json_encode($box_toload),'geom_line2__',"LIKE",array($queried_obj->term_id),'cat')->posts;
	$attachments= bv_get_geom_attachments($posts,$queried_obj->term_id);
}else{
	$posts = bv_query_rectangle(json_encode($box_toload),'geom_line2__',"LIKE")->posts;
	//$posts = bv_query_rectangle($box_toload,'geom_point',"=")->posts;
	$attachments= bv_get_geom_attachments($posts);
}

//if($member) add_action( 'pre_get_posts', 'bv_query_exclude_private');

//error_log(print_r('json aTtachs: '. json_encode($attachments)));
print_r(json_encode($attachments));
