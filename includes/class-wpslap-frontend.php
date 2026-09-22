<?php
/**
 * Comportamento das entradas-ligazón fóra do formulario: permalink externo,
 * imaxe destacada desde URL e apertura en nova lapela.
 *
 * @package WPShowLinksAsPosts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPSLAP_Frontend
 */
class WPSLAP_Frontend {

	/**
	 * URLs externas pintadas nesta páxina que deben abrirse en nova lapela.
	 *
	 * @var string[]
	 */
	private static $new_tab_urls = array();

	/**
	 * Rexistra os hooks.
	 */
	public static function init() {
		// O permalink da entrada é a URL externa (listaxes, bloques, RSS, «Ver» na admin…).
		add_filter( 'post_link', array( __CLASS__, 'filter_permalink' ), 20, 2 );

		// Se alguén chega á URL interna da entrada, redirixímolo.
		add_action( 'template_redirect', array( __CLASS__, 'redirect_single' ) );

		// Imaxe destacada desde URL externa.
		add_filter( 'has_post_thumbnail', array( __CLASS__, 'has_thumbnail' ), 10, 2 );
		add_filter( 'post_thumbnail_html', array( __CLASS__, 'thumbnail_html' ), 10, 5 );
		add_filter( 'post_thumbnail_url', array( __CLASS__, 'thumbnail_url' ), 10, 2 );

		// Clase CSS para poder darlle estilo nos temas.
		add_filter( 'post_class', array( __CLASS__, 'post_class' ), 10, 3 );

		// Non meter as ligazóns externas no sitemap do sitio.
		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'sitemap_exclude' ), 10, 2 );

		// Nova lapela.
		add_action( 'wp_footer', array( __CLASS__, 'print_new_tab_script' ), 99 );
	}

	/**
	 * Permalink → URL externa.
	 *
	 * @param string  $permalink Permalink.
	 * @param WP_Post $post      Entrada.
	 * @return string
	 */
	public static function filter_permalink( $permalink, $post ) {
		$url = wpslap_get_link_url( $post );
		if ( '' === $url ) {
			return $permalink;
		}
		if ( ! is_admin() && get_post_meta( $post->ID, WPSLAP_META_NEW_TAB, true ) ) {
			self::$new_tab_urls[ $url ] = true;
		}
		return $url;
	}

	/**
	 * Redirixe a vista individual á URL externa.
	 */
	public static function redirect_single() {
		if ( ! is_singular( 'post' ) ) {
			return;
		}
		$url = wpslap_get_link_url( get_queried_object_id() );
		if ( '' !== $url ) {
			wp_redirect( $url, 302, 'Wordpress Show Links as Posts' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- a URL é externa a propósito.
			exit;
		}
	}

	/**
	 * URL da imaxe externa dunha entrada (se ten e non ten miniatura da mediateca).
	 *
	 * @param int|WP_Post|null $post Entrada.
	 * @return string
	 */
	private static function external_image( $post ) {
		$post = get_post( $post );
		if ( ! $post || ! wpslap_is_link_post( $post ) ) {
			return '';
		}
		return (string) get_post_meta( $post->ID, WPSLAP_META_IMAGE_URL, true );
	}

	/**
	 * @param bool             $has  Ten miniatura.
	 * @param int|WP_Post|null $post Entrada.
	 * @return bool
	 */
	public static function has_thumbnail( $has, $post ) {
		return $has || '' !== self::external_image( $post );
	}

	/**
	 * @param string       $html     HTML.
	 * @param int          $post_id  ID.
	 * @param int          $thumb_id ID da miniatura.
	 * @param string|int[] $size     Tamaño.
	 * @param string|array $attr     Atributos.
	 * @return string
	 */
	public static function thumbnail_html( $html, $post_id, $thumb_id, $size, $attr ) {
		if ( '' !== $html || $thumb_id ) {
			return $html;
		}
		$src = self::external_image( $post_id );
		if ( '' === $src ) {
			return $html;
		}

		$size_class = is_array( $size ) ? implode( 'x', array_map( 'intval', $size ) ) : (string) $size;
		$attr       = wp_parse_args(
			$attr,
			array(
				'class'    => 'attachment-' . $size_class . ' size-' . $size_class . ' wp-post-image',
				'alt'      => get_the_title( $post_id ),
				'loading'  => 'lazy',
				'decoding' => 'async',
			)
		);
		$attr['src'] = $src;

		$out = '<img';
		foreach ( $attr as $name => $value ) {
			if ( is_scalar( $value ) ) {
				$value = 'src' === $name ? esc_url( $value ) : esc_attr( $value );
				$out  .= ' ' . esc_attr( $name ) . '="' . $value . '"';
			}
		}
		return $out . ' />';
	}

	/**
	 * @param string|false     $url  URL da miniatura.
	 * @param int|WP_Post|null $post Entrada.
	 * @return string|false
	 */
	public static function thumbnail_url( $url, $post ) {
		if ( $url ) {
			return $url;
		}
		$src = self::external_image( $post );
		return '' !== $src ? $src : $url;
	}

	/**
	 * @param string[] $classes Clases.
	 * @param string[] $class   Clases extra.
	 * @param int      $post_id ID.
	 * @return string[]
	 */
	public static function post_class( $classes, $class, $post_id ) {
		if ( wpslap_is_link_post( $post_id ) ) {
			$classes[] = 'wpslap-link';
		}
		return $classes;
	}

	/**
	 * @param array  $args      Argumentos da consulta.
	 * @param string $post_type Tipo.
	 * @return array
	 */
	public static function sitemap_exclude( $args, $post_type ) {
		if ( 'post' !== $post_type ) {
			return $args;
		}
		$args['meta_query']   = isset( $args['meta_query'] ) ? (array) $args['meta_query'] : array(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		$args['meta_query'][] = array(
			'key'     => WPSLAP_META_URL,
			'compare' => 'NOT EXISTS',
		);
		return $args;
	}

	/**
	 * Engade target="_blank" ás ligazóns que apuntan a entradas-ligazón con esa opción.
	 */
	public static function print_new_tab_script() {
		if ( empty( self::$new_tab_urls ) ) {
			return;
		}
		$urls = wp_json_encode( array_keys( self::$new_tab_urls ) );
		$js   = "(function(){var u={};{$urls}.forEach(function(x){u[x]=1;});"
			. "document.querySelectorAll('a[href]').forEach(function(a){"
			. "if(u[a.getAttribute('href')]){a.target='_blank';"
			. "var r=(a.rel||'').split(/\\s+/);['noopener','noreferrer'].forEach(function(v){if(r.indexOf(v)<0)r.push(v);});"
			. "a.rel=r.join(' ').trim();}});})();";
		wp_print_inline_script_tag( $js, array( 'id' => 'wpslap-new-tab' ) );
	}
}
