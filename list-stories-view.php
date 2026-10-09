<?php

define('VJS_VER','7.7.5');
$post_id=get_queried_object()->term_id;
$is_user_blog_admin =false;
wp_enqueue_script('inview-js',get_template_directory_uri(  ).'/custom-widgets/list-stories.js',array('jquery'),true);
?>
    <link href="https://vjs.zencdn.net/". VJS_VER ."/video-js.css" rel="stylesheet" /><!-- Videojs base style -->
    <link href="https://unpkg.com/@videojs/themes@1/dist/city/index.css" rel="stylesheet"><!-- Videojs city theme style -->
    <script src="https://vjs.zencdn.net/ie8/1.1.2/videojs-ie8.min.js"></script><!-- Videojs script -->
    <style>
	button{
	    transition: all 0.4s ease-in-out !important;
	}
	input,textarea{
	    width:100%;
	}
	footer{
	    display: none;
	    position:relative;
	}
	#page-container{
	    padding-bottom:0 !important;
	}
    </style>
    <div class="container-full">
	<div class="row justify-content-center m-0">
	    <div class="col-md-12 m-0 p-0 journey" id="stories-holder">
		<div id="load-more-top"></div>
		<div id="stories-list" class="pr-2 pl-2 pb-5">

		    <?php if($is_user_blog_admin):?>
			<button class="add-story-btn btn btn-primary w-100 text-white mt-3 mb-n3 block" value="<?php echo $post_id ?>" onclick="createStory(<?php echo $post_id?>)" ><h4>Add Story</h4></button>
		    <?php endif;?>

<?php
$photo_counter = 0;
$args=array(
	'post_type' =>'post',
	'cat'   => $post_id,
	'order' =>'ASC'
);
$the_query = new WP_Query($args);
if($the_query->have_posts(  )):
	while($the_query->have_posts()):$the_query->the_post();?>

<div class="story-post" data-id="<?php
// Print the coorinates of the first picture of the story
echo get_post_thumbnail_id( ); ?>"
	data-coordinates="<?php
	$thumbnail = get_field('images')[0]['image']['sizes']['thumbnail'];
$geodata = get_field('geom_point');
echo json_encode(json_decode($geodata)->geometry->coordinates);
// $image_array will be used on the map
?>">


				<div class="card">
				    <div class="card-body">
					<div class="row mb-3 edit-title align-items-center">
					    <div class="col-md-9">
						<h3><?php the_title();?></h3>
						<?php if(get_post_status( ) == 'draft'):?> <small class="text-warning">Draft</small> <?php endif;?>
					    </div>
					    <?php if($is_user_blog_admin):?>
						<div class="col-md-3 text-center text-md-right">
						    <div class='dropdown float-right'>
							<button class='btn btn-secondary dropdown-toggle' type='button' id='dropdownMenuButton' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'>
							    Options
							</button>
							<div class='dropdown-menu' aria-labelledby='dropdownMenuButton'>
							    <a href="<?php echo get_home_url() .'/edit-post-v2=?pid='. get_the_ID(  )?>" class='dropdown-item change-title-btn text-warning font-weight-bold href='#'> Edit Post</a>
							    <a class='dropdown-item text-danger font-weight-bold' href='#' onclick='deleteStory(this,<?php echo get_the_ID()?>)'> Delete Journey</a>
							</div>
						    </div>
						</div>
					    <?php endif;?>
					</div>
					<hr>
					<div class="row mb-3 edit-description">
					    <div class="col-md-12">
						<p><?php the_content();?></p>
					    </div>
					</div>
					<div class="row mb-3 edit-images">
					    <div class="image-list w-100">
<?php while(have_rows('images')):the_row();$photo_counter++; // $photo_counter is used so we can show the number of jorney images
$media_url=esc_url(get_sub_field('image')['url']);
$media_id=get_sub_field('image')['ID'];
// echo $media_id;
?>
						    <div class="image-wrapper mb-1">
							<?php if(isImage($media_url)):?>
							    <img class="img w-100" src="<?php echo $media_url?>" />
							<?php elseif(isVideo($media_url)):?>
							    <video
								id="my-video"
								class="video-js vjs-theme-city"
								controls
								preload="metadata"
								data-setup="{}" style="width:100%;height:auto; min-height:500px;position:relative"
							    ><source src="<?php echo $media_url?>"/></video>
							<?php endif;?>
							<p><?php echo get_field('description', $media_id);?></p>
						    </div>
						<?php endwhile;?>
					    </div>

					</div>
				    </div>
				</div>
			    </div>
<?php
endwhile;

?>
			<div id="load-more-bottom"></div>

			<?php if($is_user_blog_admin):?>
			<h3 class="mt-2">Connection between points</h3>
			<div id="route-selector">
			    <input name="line-connection-style" type="radio" class="w-auto mr-1" data-id="<?php echo $post_id?>" id="straing-lines" value="0" <?php if((int)get_field('route_style',$post_id)===0) echo 'checked'?>><label class="m-0" for="straing-lines">Straight Lines</label><br>
			    <input name="line-connection-style" type="radio" class="w-auto mr-1" data-id="<?php echo $post_id?>" id="driving-route" value="1" <?php if((int)get_field('route_style',$post_id)===1) echo 'checked'?>><label class="m-0" for="driving-route">Driving Route</label><br>
			</div>
			<button class="add-story-btn btn btn-primary w-100 text-white mt-3 block" value="<?php echo $post_id ?>" onclick="createStory(<?php echo $post_id?>)" ><h4>Add Story</h4></button>
		    <?php endif;?>
		    <?php endif;?>
		</div>
		<div>
		</div>
	    </div>

	</div>

    </div>
    </div>


    <script src="https://vjs.zencdn.net/7.7.5/video.js"></script><!-- Videojs script -->
