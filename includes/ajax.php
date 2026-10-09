<?php
if(!isset($_POST['mapboxjs_bounding_box'])){
    header( '400 Bad Request' );
    exit( 'Bad Request' );
}
$path = preg_replace('/wp-content.*$/','',__DIR__);
//error_log(print_r("pAtH: ". $path, true));
require_once($path.'wp-load.php');
// print_r($_POST);
$bb = $_POST['mapboxjs_bounding_box'];
$box_toload=bv_bufferbox($bb['ne'],$bb['sw'],1);
$box_view=bv_bufferbox($bb['ne'],$bb['sw'],0.7);
// reload is triggered when $box_view is not st_within $box_toload
// for every reload, posts within $box_toload are to be loaded
$posts = bv_query_rectangle($box_toload,'geom_line2__',"LIKE")->posts;
foreach($posts as $post){
	$attachment = bv_get_geompoint($post);
	/*
        $media_id = get_post_thumbnail_id($post->ID);
        $attachment = json_decode(get_post_meta($media_id, 'geom_point',TRUE));
        $attachment->properties->datetime = get_post_meta($media_id, 'datetime',TRUE);
        $attachment->properties->thumbnail = wp_get_attachment_thumb_url ( $media_id);
        $attachment->properties->id = $media_id;
	 */
	//error_log(print_r('ajaX attachment: '.json_encode($attachment),true));
        $attachments[]=$attachment;
}
print_r(json_encode($attachments));
