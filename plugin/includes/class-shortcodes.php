<?php
/**
 * Shortcodes Handler Class
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Revora_Shortcodes {

	public function __construct() {
		add_shortcode( 'revora_reviews', array( $this, 'render_reviews_shortcode' ) );
		add_shortcode( 'revora_form', array( $this, 'render_form_shortcode' ) );
		add_action( 'wp_head', array( $this, 'maybe_inject_schema' ) );
	}

	/**
	 * Render Reviews Shortcode
	 * [revora_reviews category="category-slug" limit="6"]
	 */
	public function render_reviews_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'category'   => '',
			'limit'      => 6,
			'columns'    => 3,
			'card_style' => 'classic',
		), $atts, 'revora_reviews' );

		$db = new Revora_DB();
		$reviews = $db->get_approved_reviews( $atts['category'], $atts['limit'] );
		$total_reviews = $db->get_total_approved_count( $atts['category'] );
		$settings = wp_parse_args( get_option( 'revora_settings', array() ), array(
			'primary_color' => '#d64e11',
			'star_color'    => '#fbbf24',
			'layout'        => 'grid',
			'show_stars'    => '1',
			'enable_schema' => '1',
		) );

		if ( empty( $reviews ) ) {
			return '<p class="revora-no-reviews">' . esc_html__( 'No reviews yet.', 'revora' ) . '</p>';
		}

		ob_start();

		// Schema is now handled via maybe_inject_schema() hooked to wp_head
		?>
		<?php
		// Dynamic Styles for this shortcode instance
		$widget_id = uniqid( 'revora-' );
		$custom_css = "
			.{$widget_id} {
				--revora-primary: " . esc_attr( $settings['primary_color'] ) . ";
				--revora-star-filled: " . esc_attr( $settings['star_color'] ) . ";
			}
		";
		wp_add_inline_style( 'revora-frontend', $custom_css );
		?>
		<div class="revora-reviews-container <?php echo esc_attr( $widget_id ); ?>" 
			data-category="<?php echo esc_attr( $atts['category'] ); ?>" 
			data-limit="<?php echo esc_attr( $atts['limit'] ); ?>"
			data-columns="<?php echo esc_attr( $atts['columns'] ); ?>"
			data-card-style="<?php echo esc_attr( $atts['card_style'] ); ?>">
			
			<div class="revora-reviews-grid revora-grid-cols-<?php echo esc_attr( $atts['columns'] ); ?>">
				
				<?php foreach ( $reviews as $review ) : ?>
					<div class="revora-review-card style-<?php echo esc_attr( $atts['card_style'] ); ?>">
						<div class="revora-review-header">
							<div class="revora-review-meta">
								<span class="revora-review-author"><?php echo esc_html( $review->name ); ?></span>
								<span class="revora-review-date"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $review->created_at ) ) ); ?></span>
							</div>
							<?php if ( '1' === $settings['show_stars'] ) : ?>
								<div class="revora-review-rating">
									<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
										<span class="dashicons dashicons-star-filled <?php echo esc_attr( $i <= $review->rating ? 'filled' : 'empty' ); ?>"></span>
									<?php endfor; ?>
								</div>
							<?php endif; ?>
						</div>
						<h4 class="revora-review-title"><?php echo esc_html( $review->title ); ?></h4>
						<div class="revora-review-content">
							<?php echo wp_kses_post( wpautop( esc_html( $review->content ) ) ); ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			
			<?php if ( count( $reviews ) < $total_reviews ) : ?>
				<div class="revora-load-more-container">
					<button class="revora-load-more-btn" data-page="1">
						<span class="btn-text"><?php esc_html_e( 'Load More Reviews', 'revora' ); ?></span>
						<span class="revora-spinner"></span>
					</button>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Maybe inject Schema.org JSON-LD in head
	 */
	public function maybe_inject_schema() {
		global $post;
		
		if ( ! is_a( $post, 'WP_Post' ) ) {
			return;
		}

		$settings = get_option( 'revora_settings' );
		if ( empty( $settings['enable_schema'] ) || '1' !== $settings['enable_schema'] ) {
			return;
		}

		if ( has_shortcode( $post->post_content, 'revora_reviews' ) ) {
			// Extract category from shortcode
			preg_match( '/\[revora_reviews[^\]]*category=["\']([^"\']+)["\']/', $post->post_content, $matches );
			$category = isset( $matches[1] ) ? $matches[1] : '';
			
			$db = new Revora_DB();
			$reviews = $db->get_approved_reviews( $category, 6 ); // Last 6 for schema
			
			if ( ! empty( $reviews ) ) {
				$this->inject_schema( $reviews, $category );
			}
		}
	}

	/**
	 * Output the actual JSON-LD script
	 */
	private function inject_schema( $reviews, $category = 'All' ) {
		$total_rating = 0;
		$count = count( $reviews );
		foreach ( $reviews as $r ) {
			$total_rating += $r->rating;
		}
		$avg = $count > 0 ? round( $total_rating / $count, 1 ) : 0;

		$schema = array(
			'@context' => 'https://schema.org/',
			'@type'    => 'Product',
			'name'     => ! empty( $category ) ? $category : __( 'Service/Product Reviews', 'revora' ),
			'aggregateRating' => array(
				'@type'       => 'AggregateRating',
				'ratingValue' => $avg,
				'reviewCount' => $count,
				'bestRating'  => '5',
				'worstRating' => '1',
			),
			'review' => array(),
		);

		foreach ( $reviews as $review ) {
			$schema['review'][] = array(
				'@type'  => 'Review',
				'reviewRating' => array(
					'@type'       => 'Rating',
					'ratingValue' => $review->rating,
				),
				'author' => array(
					'@type' => 'Person',
					'name'  => $review->name,
				),
				'headline'      => $review->title,
				'reviewBody'    => $review->content,
				'datePublished' => $review->created_at,
			);
		}

		echo '<script type="application/ld+json">' . wp_json_encode( $schema ) . '</script>';

	}

	/**
	 * Render Form Shortcode
	 * [revora_form category=""]
	 */
	public function render_form_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'category' => '',
		), $atts, 'revora_form' );

		ob_start();
		$db = new Revora_DB();
		$categories = $db->get_categories();
		$settings = get_option( 'revora_settings' );
		$has_category = ! empty( $atts['category'] );

		// Dynamic Styles for this shortcode instance
		$widget_id = uniqid( 'revora-form-' );
		$custom_css = "
			.{$widget_id} {
				--revora-primary: " . esc_attr( $settings['primary_color'] ) . ";
				--revora-star-filled: " . esc_attr( $settings['star_color'] ) . ";
			}
		";
		wp_add_inline_style( 'revora-frontend', $custom_css );
		?>
		<div class="revora-form-container <?php echo esc_attr( $widget_id ); ?>">
			<h3><?php esc_html_e( 'Submit a Review', 'revora' ); ?></h3>
			<form id="revora-review-form" class="revora-form">
				<?php wp_nonce_field( 'revora_submit_nonce', 'nonce' ); ?>
				
				<?php 
				$category_slug = ! empty( $atts['category'] ) ? $atts['category'] : 'unknown';
				?>
				<input type="hidden" name="category_slug" value="<?php echo esc_attr( $category_slug ); ?>">
				
				<div class="revora-form-row">
					<div class="revora-form-field">
						<label for="revora_name"><?php esc_html_e( 'Your Name', 'revora' ); ?></label>
						<input type="text" name="name" id="revora_name" placeholder="<?php esc_attr_e( 'John Doe', 'revora' ); ?>" required>
					</div>
					<div class="revora-form-field">
						<label for="revora_email"><?php esc_html_e( 'Your Email', 'revora' ); ?></label>
						<input type="email" name="email" id="revora_email" placeholder="<?php esc_attr_e( 'john@example.com', 'revora' ); ?>" required>
					</div>
				</div>

				<div class="revora-form-field">
					<label><?php esc_html_e( 'Rating', 'revora' ); ?></label>
					<div class="revora-rating-input">
						<?php for ( $i = 5; $i >= 1; $i-- ) : ?>
							<input type="radio" id="star-<?php echo absint( $i ); ?>" name="rating" value="<?php echo absint( $i ); ?>" <?php checked( $i, 5 ); ?> required />
							<label for="star-<?php echo absint( $i ); ?>" title="<?php echo absint( $i ); ?> stars">
								<svg viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
							</label>
						<?php endfor; ?>
					</div>
				</div>

				<div class="revora-form-field">
					<label for="revora_title"><?php esc_html_e( 'Review Title', 'revora' ); ?></label>
					<input type="text" name="title" id="revora_title" placeholder="<?php esc_attr_e( 'Summarize your experience', 'revora' ); ?>" required>
				</div>

				<div class="revora-form-field">
					<label for="revora_content"><?php esc_html_e( 'Review Content', 'revora' ); ?></label>
					<textarea name="content" id="revora_content" rows="5" placeholder="<?php esc_attr_e( 'Share your detailed experience... (minimum 25 characters)', 'revora' ); ?>" required minlength="25"></textarea>
				</div>

				<div id="revora-form-message" class="revora-form-message"></div>
				
				<div class="revora-form-footer">
					<button type="submit" class="revora-submit-btn">
						<span class="btn-text"><?php esc_html_e( 'Submit Review', 'revora' ); ?></span>
						<span class="revora-spinner"></span>
					</button>
				</div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}
}
