<?php

function bv_bufferbox($pt1, $pt2, $factor,$reverse=false){
	if ($reverse){
		array_reverse($ne);
		array_reverse($sw);
	}	
	$geom_pt1 = WP_GeoUtil::point($pt1[0],$pt1[1]);
	$geom_pt2 = WP_GeoUtil::point($pt2[0],$pt2[1]);
	$distance=WP_GeoUtil::st_distance($geom_pt1,$geom_pt2);
	$box=WP_GeoUtil::st_buffer( WP_GeoUtil::st_makeenvelope( $geom_pt1, $geom_pt2), $factor * $distance , WP_GeoUtil::ST_Buffer_Strategy('join_miter', 2)  );
	return($box);
}

function bv_query_rectangle($geom_poly,$qkey='geom_point',$compare_key='=', $included_terms=array(),$taxonomy='category_name',$excluded_terms=array(),$post_type='post',$order='DESC'){
	$special_categories_local=$GLOBALS['special_categories'];  
	$arr_query = array(
		//'meta_type' => 'CHAR',
		//'meta_value' => True,
		"meta_query" => array(
			'relation' => 'AND',
			array(
				"key" => $qkey,
				//"type" => 'NUMERIC',
				"compare_key" => $compare_key,
				"compare" => "ST_INTERSECTS",
				"value" => $geom_poly
			),
			array(
				'key' => 'geom_point',
				'compare' => 'EXISTS' // this should work...
			),
		),
		'posts_per_page' => -1,
		'post_type' => $post_type,
		'post_status' => 'publish',
		'order' => $order,
		'orderby' => 'date',
	);
	/*
	$category = get_queried_object();
		error_log( print_r('queried_obj: '. $category,true) );
	if(get_category($category->term_id) && !in_array($category->slug,$special_categories_local)){
		$arr_query += array(
			'orderby' => 'date',
			'cat'=> $category->term_id ,
		);
		//echo $category->term_id;
	}else{
		$arr_query += array(
			'orderby' => 'modified',
		);
	}
	 */

	$restrict_cat=(isset($included_terms) && !empty($included_terms) );
	if ( $restrict_cat ){
		$arr_query += array( $taxonomy => implode(",",$included_terms));
	}

	//error_log(print_r('arr_query: '.json_encode($arr_query),true));
	//return($arr_query);
	$withinbox_query = @(new WP_Query( $arr_query)); 
//	error_log(print_r('withinB0x: '. json_encode($withinbox_query),true));

	return($withinbox_query);
}

function bv_get_geompoint($post_obj){
        $media_id = get_post_thumbnail_id($post_obj->ID);
        if($attachment = json_decode(get_post_meta($post_obj->ID, 'geom_point',TRUE))??json_decode(get_post_meta($media_id, 'geom_point',TRUE)) ){
		if( empty($attachment) ) return;
                $attachment->id = $post_obj->ID;
                $attachment->properties->posttype = $post_obj->post_type;
                $attachment->properties->title = $post_obj->post_title;
                if($media_id){
                        $attachment->properties->id = $media_id;
                        $attachment->properties->datetime = get_post_meta($media_id, 'datetime',TRUE);
                        $attachment->properties->thumbnail = wp_get_attachment_image_src($media_id, 'thumbnail')[0];
                        //$attachment->properties->thumbnail = wp_get_attachment_image_src($media_id, 'medium')[0];
                }
                return $attachment;
        }
}
