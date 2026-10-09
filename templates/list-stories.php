<?php
/**
 * Story list for a journey category.
 *
 * Rendered through bv_list_shortcode(), which output-buffers this file.
 * Previously templates/../list-stories-view.php.
 *
 * @package BV_Map
 */

defined( 'ABSPATH' ) || exit;

$bv_map_queried = get_queried_object();

// Previously dereferenced ->term_id unconditionally, which warned on every page
// that is not a term archive.
if ( ! $bv_map_queried instanceof WP_Term ) {
	return;
}

$bv_map_term_id = (int) $bv_map_queried->term_id;

// Hardcoded false upstream, which disables every editing control below. Kept
// off deliberately: the buttons call createStory()/deleteStory(), which live in
// the theme and are not guaranteed to exist. Switch to a capability check such
// as current_user_can( 'edit_posts' ) only once those handlers are confirmed.
$bv_map_is_admin = false;

$bv_map_vjs_ver     = '7.7.5';
$bv_map_photo_count = 0;

// The 4th argument of wp_enqueue_script() is $ver, not $in_footer. Passing true
// there left the handle queued for a head that had already rendered, so the
// script never loaded.
wp_enqueue_script(
	'bv-map-inview',
	get_template_directory_uri() . '/custom-widgets/list-stories.js',
	array( 'jquery' ),
	BV_MAP_VERSION,
	true
);

wp_enqueue_style(
	'video-js',
	'https://vjs.zencdn.net/' . $bv_map_vjs_ver . '/video-js.css',
	array(),
	$bv_map_vjs_ver
);

wp_enqueue_style(
	'video-js-city',
	'https://unpkg.com/@videojs/themes@1/dist/city/index.css',
	array( 'video-js' ),
	'1'
);

wp_enqueue_script(
	'video-js',
	'https://vjs.zencdn.net/' . $bv_map_vjs_ver . '/video.js',
	array(),
	$bv_map_vjs_ver,
	true
);

$bv_map_query = new WP_Query(
	array(
		'post_type' => 'post',
		'cat'       => $bv_map_term_id,
		'order'     => 'ASC',
	)
);
?>
<div class="container-full">
	<div class="row justify-content-center m-0">
		<div class="col-md-12 m-0 p-0 journey" id="stories-holder">
			<div id="load-more-top"></div>
			<div id="stories-list" class="pr-2 pl-2 pb-5">

				<?php if ( $bv_map_is_admin ) : ?>
					<button class="add-story-btn btn btn-primary w-100 text-white mt-3 mb-n3 block"
						value="<?php echo esc_attr( $bv_map_term_id ); ?>"
						onclick="createStory(<?php echo esc_js( $bv_map_term_id ); ?>)">
						<h4><?php esc_html_e( 'Add Story', 'bv-map' ); ?></h4>
					</button>
				<?php endif; ?>

				<?php
				if ( $bv_map_query->have_posts() ) :
					while ( $bv_map_query->have_posts() ) :
						$bv_map_query->the_post();

						$bv_map_geodata = bv_map_get_field( 'geom_point' );
						$bv_map_coords  = array();

						if ( $bv_map_geodata ) {
							$bv_map_decoded = json_decode( $bv_map_geodata );
							if ( isset( $bv_map_decoded->geometry->coordinates ) ) {
								$bv_map_coords = $bv_map_decoded->geometry->coordinates;
							}
						}
						?>
						<div class="story-post"
							data-id="<?php echo esc_attr( get_post_thumbnail_id() ); ?>"
							data-coordinates="<?php echo esc_attr( wp_json_encode( $bv_map_coords ) ); ?>">
							<div class="card">
								<div class="card-body">
									<div class="row mb-3 edit-title align-items-center">
										<div class="col-md-9">
											<h3><?php the_title(); ?></h3>
											<?php if ( 'draft' === get_post_status() ) : ?>
												<small class="text-warning"><?php esc_html_e( 'Draft', 'bv-map' ); ?></small>
											<?php endif; ?>
										</div>
										<?php if ( $bv_map_is_admin ) : ?>
											<div class="col-md-3 text-center text-md-right">
												<div class="dropdown float-right">
													<button class="btn btn-secondary dropdown-toggle" type="button"
														id="dropdownMenuButton" data-toggle="dropdown"
														aria-haspopup="true" aria-expanded="false">
														<?php esc_html_e( 'Options', 'bv-map' ); ?>
													</button>
													<div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
														<?php // The original left the class attribute unterminated here. ?>
														<a class="dropdown-item change-title-btn text-warning font-weight-bold"
															href="<?php echo esc_url( add_query_arg( 'pid', get_the_ID(), get_home_url( null, '/edit-post-v2' ) ) ); ?>">
															<?php esc_html_e( 'Edit Post', 'bv-map' ); ?>
														</a>
														<a class="dropdown-item text-danger font-weight-bold" href="#"
															onclick="deleteStory(this,<?php echo esc_js( get_the_ID() ); ?>)">
															<?php esc_html_e( 'Delete Journey', 'bv-map' ); ?>
														</a>
													</div>
												</div>
											</div>
										<?php endif; ?>
									</div>
									<hr>
									<div class="row mb-3 edit-description">
										<div class="col-md-12">
											<p><?php the_content(); ?></p>
										</div>
									</div>
									<div class="row mb-3 edit-images">
										<div class="image-list w-100">
											<?php
											// have_rows()/get_sub_field() are ACF; without it this
											// loop fatals rather than simply rendering nothing.
											while ( bv_map_has_acf() && have_rows( 'images' ) ) :
												the_row();
												++$bv_map_photo_count;

												$bv_map_image = get_sub_field( 'image' );
												if ( empty( $bv_map_image['url'] ) ) {
													continue;
												}

												$bv_map_media_url = esc_url( $bv_map_image['url'] );
												$bv_map_media_id  = isset( $bv_map_image['ID'] ) ? (int) $bv_map_image['ID'] : 0;
												?>
												<div class="image-wrapper mb-1">
													<?php if ( isImage( $bv_map_media_url ) ) : ?>
														<img class="img w-100" src="<?php echo esc_url( $bv_map_media_url ); ?>"
															alt="<?php echo esc_attr( get_the_title() ); ?>" />
													<?php elseif ( isVideo( $bv_map_media_url ) ) : ?>
														<video id="video-<?php echo esc_attr( $bv_map_media_id ); ?>"
															class="video-js vjs-theme-city" controls preload="metadata"
															data-setup="{}"
															style="width:100%;height:auto;min-height:500px;position:relative">
															<source src="<?php echo esc_url( $bv_map_media_url ); ?>" />
														</video>
													<?php endif; ?>
													<p><?php echo wp_kses_post( bv_map_get_field( 'description', $bv_map_media_id, '' ) ); ?></p>
												</div>
												<?php
											endwhile;
											?>
										</div>
									</div>
								</div>
							</div>
						</div>
						<?php
					endwhile;
					?>

					<div id="load-more-bottom"></div>

					<?php if ( $bv_map_is_admin ) : ?>
						<h3 class="mt-2"><?php esc_html_e( 'Connection between points', 'bv-map' ); ?></h3>
						<div id="route-selector">
							<?php $bv_map_route_style = (int) bv_map_get_field( 'route_style', $bv_map_term_id, 0 ); ?>
							<input name="line-connection-style" type="radio" class="w-auto mr-1"
								data-id="<?php echo esc_attr( $bv_map_term_id ); ?>" id="straight-lines" value="0"
								<?php checked( 0, $bv_map_route_style ); ?>>
							<label class="m-0" for="straight-lines"><?php esc_html_e( 'Straight Lines', 'bv-map' ); ?></label><br>
							<input name="line-connection-style" type="radio" class="w-auto mr-1"
								data-id="<?php echo esc_attr( $bv_map_term_id ); ?>" id="driving-route" value="1"
								<?php checked( 1, $bv_map_route_style ); ?>>
							<label class="m-0" for="driving-route"><?php esc_html_e( 'Driving Route', 'bv-map' ); ?></label><br>
						</div>
						<button class="add-story-btn btn btn-primary w-100 text-white mt-3 block"
							value="<?php echo esc_attr( $bv_map_term_id ); ?>"
							onclick="createStory(<?php echo esc_js( $bv_map_term_id ); ?>)">
							<h4><?php esc_html_e( 'Add Story', 'bv-map' ); ?></h4>
						</button>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
<?php
// Never called previously, leaving the global post clobbered for everything
// rendered after this template.
wp_reset_postdata();
