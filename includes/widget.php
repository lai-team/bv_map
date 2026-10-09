<?php
/**
 * Map widget.
 *
 * @package BV_Map
 */

defined( 'ABSPATH' ) || exit;

/**
 * Displays the journey map in a widget area.
 *
 * Class name retained for backwards compatibility: changing it would orphan any
 * widget instances already saved in the database.
 */
class bv_map_widget extends WP_Widget { // phpcs:ignore WordPress.NamingConventions.ValidClassName.NotSnakeCaseClassName

	/**
	 * Register the widget.
	 */
	public function __construct() {
		parent::__construct(
			'bv_map_widget',
			__( 'beauVoyage Map', 'bv-map' ),
			array( 'description' => __( 'Illustrate journeys geographically', 'bv-map' ) )
		);
	}

	/**
	 * Front-end output.
	 *
	 * @param array $args     Sidebar arguments.
	 * @param array $instance Saved widget settings.
	 */
	public function widget( $args, $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : '';
		$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutputNotEscaped

		if ( ! empty( $title ) ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutputNotEscaped
		}

		echo do_shortcode( '[bv_map_shortcode]' );

		// The closing wrapper was never emitted, leaving the sidebar markup
		// unbalanced.
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutputNotEscaped
	}

	/**
	 * Settings form.
	 *
	 * @param array $instance Saved widget settings.
	 */
	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : __( 'New title', 'bv-map' );
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
				<?php esc_html_e( 'Title:', 'bv-map' ); ?>
			</label>
			<input class="widefat"
				id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
				type="text"
				value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<?php
	}

	/**
	 * Persist settings.
	 *
	 * @param array $new_instance Submitted values.
	 * @param array $old_instance Previously saved values.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		return array(
			'title' => ! empty( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '',
		);
	}
}

/**
 * Register the widget.
 */
function bv_map_load_widget() {
	register_widget( 'bv_map_widget' );
}
add_action( 'widgets_init', 'bv_map_load_widget' );
