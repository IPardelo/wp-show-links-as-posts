<?php
/**
 * Plugin Name:       Wordpress Show Links as Posts
 * Plugin URI:        https://github.com/IPardelo/wp-show-links-as-posts
 * Description:       Engade ligazóns como se fosen entradas.
 * Version:           1.0.0
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            IPardelo
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-show-links-as-posts
 * 
  * @package WPShowLinksAsPosts
 */

defined( 'ABSPATH' ) || exit;

define( 'WPSLAP_VERSION', '1.1.0' );
define( 'WPSLAP_FILE', __FILE__ );
define( 'WPSLAP_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPSLAP_URL', plugin_dir_url( __FILE__ ) );

/** Meta keys. */
define( 'WPSLAP_META_URL', '_wpslap_url' );
define( 'WPSLAP_META_IMAGE_URL', '_wpslap_image_url' );
define( 'WPSLAP_META_NEW_TAB', '_wpslap_new_tab' );

/**
 * Devolve a URL externa dunha entrada-ligazón, ou cadea baleira se non o é.
 *
 * @param int|WP_Post|null $post Entrada.
 * @return string
 */
function wpslap_get_link_url( $post = null ) {
	$post = get_post( $post );
	if ( ! $post || 'post' !== $post->post_type ) {
		return '';
	}
	return (string) get_post_meta( $post->ID, WPSLAP_META_URL, true );
}

/**
 * Indica se unha entrada é unha entrada-ligazón.
 *
 * @param int|WP_Post|null $post Entrada.
 * @return bool
 */
function wpslap_is_link_post( $post = null ) {
	return '' !== wpslap_get_link_url( $post );
}

require_once WPSLAP_DIR . 'includes/class-wpslap-admin.php';
require_once WPSLAP_DIR . 'includes/class-wpslap-frontend.php';
require_once WPSLAP_DIR . 'includes/class-wpslap-shortcode.php';
require_once WPSLAP_DIR . 'includes/class-wpslap-importer.php';

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain( 'wp-show-links-as-posts', false, dirname( plugin_basename( WPSLAP_FILE ) ) . '/languages' );
		WPSLAP_Frontend::init();
		WPSLAP_Shortcode::init();
		if ( is_admin() ) {
			WPSLAP_Admin::init();
			WPSLAP_Importer::init();
		}
	}
);
