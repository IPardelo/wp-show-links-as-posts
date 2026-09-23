<?php
/**
 * Shortcode [posts-wp-show-links-as-posts]: lista de entradas normais e
 * entradas-ligazón con deseño propio, independente do tema.
 *
 * @package WPShowLinksAsPosts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPSLAP_Shortcode
 */
class WPSLAP_Shortcode {

	const TAG       = 'posts-wp-show-links-as-posts';
	const PAGE_VAR  = 'wpslap_paxina';
	const STYLE_ID  = 'wpslap-shortcode';

	/**
	 * Rexistra o shortcode e o estilo.
	 */
	public static function init() {
		add_shortcode( self::TAG, array( __CLASS__, 'render' ) );
		add_action(
			'wp_enqueue_scripts',
			static function () {
				wp_register_style( self::STYLE_ID, WPSLAP_URL . 'assets/shortcode.css', array(), WPSLAP_VERSION );
			}
		);
	}

	/**
	 * Pinta a lista.
	 *
	 * @param array|string $atts Atributos.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'cantidade'    => 10,          // Entradas por páxina.
				'tipo'         => 'todas',     // todas | ligazons | entradas.
				'categoria'    => '',          // Slugs separados por comas.
				'paxinacion'   => 'si',        // si | non.
				'extracto'     => 'non',       // si | non (só entradas normais).
				'dominio'      => 'si',        // si | non: mostra o medio da ligazón.
				'formato_data' => 'd/m/Y H:i',
				'texto_ligazon' => __( 'Ir á noticia', 'wp-show-links-as-posts' ),
				'texto_entrada' => __( 'Ler entrada', 'wp-show-links-as-posts' ),
				'cor'          => '',          // Cor de acento, p. ex. #b3141b.
			),
			$atts,
			self::TAG
		);

		$per_page   = max( 1, min( 100, (int) $atts['cantidade'] ) );
		$paginate   = self::yes( $atts['paxinacion'] );
		$paged      = $paginate && isset( $_GET[ self::PAGE_VAR ] ) ? max( 1, absint( $_GET[ self::PAGE_VAR ] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$show_exc   = self::yes( $atts['extracto'] );
		$show_host  = self::yes( $atts['dominio'] );

		$args = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => $per_page,
			'paged'               => $paged,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => ! $paginate,
		);

		$tipo = sanitize_key( $atts['tipo'] );
		if ( in_array( $tipo, array( 'ligazons', 'ligazon', 'links' ), true ) ) {
			$args['meta_query'] = array( array( 'key' => WPSLAP_META_URL, 'compare' => 'EXISTS' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		} elseif ( in_array( $tipo, array( 'entradas', 'posts' ), true ) ) {
			$args['meta_query'] = array( array( 'key' => WPSLAP_META_URL, 'compare' => 'NOT EXISTS' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		$cats = array_filter( array_map( 'sanitize_title', explode( ',', (string) $atts['categoria'] ) ) );
		if ( $cats ) {
			$args['category_name'] = implode( ',', $cats );
		}

		$query = new WP_Query( $args );
		if ( ! $query->have_posts() ) {
			return '<p class="wpslap-empty">' . esc_html__( 'Aínda non hai entradas.', 'wp-show-links-as-posts' ) . '</p>';
		}

		wp_enqueue_style( self::STYLE_ID );

		$style = '';
		$color = sanitize_hex_color( $atts['cor'] );
		if ( $color ) {
			$style = ' style="--wpslap-accent:' . esc_attr( $color ) . '"';
		}

		ob_start();
		echo '<div class="wpslap-wrap"' . $style . '><div class="wpslap-list">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $style xa escapado.

		while ( $query->have_posts() ) {
			$query->the_post();
			self::render_card( get_post(), $atts, $show_exc, $show_host );
		}

		echo '</div>';

		if ( $paginate && $query->max_num_pages > 1 ) {
			$links = paginate_links(
				array(
					'base'      => add_query_arg( self::PAGE_VAR, '%#%', remove_query_arg( self::PAGE_VAR ) ),
					'format'    => '',
					'current'   => $paged,
					'total'     => (int) $query->max_num_pages,
					'prev_text' => '&larr; ' . esc_html__( 'Anteriores', 'wp-show-links-as-posts' ),
					'next_text' => esc_html__( 'Seguintes', 'wp-show-links-as-posts' ) . ' &rarr;',
					'mid_size'  => 1,
				)
			);
			if ( $links ) {
				echo '<nav class="wpslap-pagination" aria-label="' . esc_attr__( 'Paxinación', 'wp-show-links-as-posts' ) . '">' . $links . '</nav>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}

		echo '</div>';

		wp_reset_postdata();
		return ob_get_clean();
	}

	/**
	 * Pinta unha tarxeta.
	 *
	 * @param WP_Post $post      Entrada.
	 * @param array   $atts      Atributos do shortcode.
	 * @param bool    $show_exc  Mostrar extracto.
	 * @param bool    $show_host Mostrar dominio.
	 */
	private static function render_card( $post, $atts, $show_exc, $show_host ) {
		$link_url = wpslap_get_link_url( $post );
		$is_link  = '' !== $link_url;
		$href     = $is_link ? $link_url : get_permalink( $post );
		$new_tab  = $is_link && get_post_meta( $post->ID, WPSLAP_META_NEW_TAB, true );
		$target   = $new_tab ? ' target="_blank" rel="noopener noreferrer"' : '';
		$title    = get_the_title( $post );
		$host     = $is_link ? preg_replace( '/^www\./i', '', (string) wp_parse_url( $link_url, PHP_URL_HOST ) ) : '';
		$image    = get_the_post_thumbnail( $post, 'medium_large', array( 'class' => 'wpslap-card__img', 'alt' => '' ) );
		$label    = $is_link ? $atts['texto_ligazon'] : $atts['texto_entrada'];
		$classes  = 'wpslap-card ' . ( $is_link ? 'wpslap-card--link' : 'wpslap-card--post' ) . ( $image ? '' : ' wpslap-card--noimg' );
		?>
		<article class="<?php echo esc_attr( $classes ); ?>">
			<a class="wpslap-card__media" href="<?php echo esc_url( $href ); ?>"<?php echo $target; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> tabindex="-1" aria-hidden="true">
				<?php if ( $image ) : ?>
					<?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php else : ?>
					<span class="wpslap-card__placeholder"><?php echo self::icon( $is_link ? 'link' : 'doc' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<?php endif; ?>
			</a>

			<div class="wpslap-card__body">
				<?php if ( $is_link && $show_host && $host ) : ?>
					<span class="wpslap-card__source"><?php echo self::icon( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $host ); ?></span>
				<?php endif; ?>

				<h3 class="wpslap-card__title">
					<a href="<?php echo esc_url( $href ); ?>"<?php echo $target; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( wp_strip_all_tags( $title ) ); ?></a>
				</h3>

				<?php
				$excerpt = ( $show_exc && ! $is_link ) ? wp_trim_words( get_the_excerpt( $post ), 28 ) : '';
				if ( '' !== $excerpt ) :
					?>
					<p class="wpslap-card__excerpt"><?php echo esc_html( $excerpt ); ?></p>
				<?php endif; ?>

				<div class="wpslap-card__footer">
					<a class="wpslap-card__button" href="<?php echo esc_url( $href ); ?>"<?php echo $target; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<?php echo esc_html( $label ); ?>
						<?php echo self::icon( $new_tab ? 'external' : 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</a>
					<time class="wpslap-card__date" datetime="<?php echo esc_attr( get_the_date( 'c', $post ) ); ?>">
						<?php echo esc_html( get_the_date( $atts['formato_data'], $post ) ); ?>
						<?php echo self::icon( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</time>
				</div>
			</div>
		</article>
		<?php
	}

	/**
	 * Interpreta si/non.
	 *
	 * @param string $value Valor.
	 * @return bool
	 */
	private static function yes( $value ) {
		return in_array( strtolower( (string) $value ), array( 'si', 'sí', 'yes', '1', 'true' ), true );
	}

	/**
	 * Iconas SVG en liña (trazo, herdan a cor do texto).
	 *
	 * @param string $name Nome.
	 * @return string
	 */
	private static function icon( $name ) {
		$paths = array(
			'calendar' => '<rect x="3" y="4.5" width="18" height="16.5" rx="2"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4"/>',
			'link'     => '<path d="M10 14a4.5 4.5 0 0 0 6.4 0l3-3a4.5 4.5 0 0 0-6.4-6.4l-1.2 1.2"/><path d="M14 10a4.5 4.5 0 0 0-6.4 0l-3 3a4.5 4.5 0 0 0 6.4 6.4l1.2-1.2"/>',
			'external' => '<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
			'arrow'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
			'doc'      => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
		);
		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}
		return '<svg class="wpslap-icon wpslap-icon--' . $name . '" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
	}
}
