<?php
/**
 * Imaxes externas: descargalas á mediateca para non depender da web de orixe
 * (protección anti-hotlink, bloqueadores de cookies, temas que só usan adxuntos…).
 *
 * @package WPShowLinksAsPosts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPSLAP_Images
 */
class WPSLAP_Images {

	const ACTION = 'wpslap_localize_images';
	const NONCE  = 'wpslap_localize_nonce';

	/**
	 * Tipos de imaxe aceptados → extensión.
	 *
	 * @var array<string,string>
	 */
	private static $mimes = array(
		'image/jpeg' => 'jpg',
		'image/png'  => 'png',
		'image/gif'  => 'gif',
		'image/webp' => 'webp',
		'image/avif' => 'avif',
	);

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_localize' ) );
	}

	/**
	 * Descarga unha imaxe externa á mediateca e devolve o ID do adxunto (0 se falla).
	 * Se esa mesma URL xa se descargou antes, reutiliza o adxunto.
	 *
	 * @param string $img     URL da imaxe.
	 * @param int    $post_id Entrada á que se asocia.
	 * @param string $title   Título do adxunto.
	 * @return int
	 */
	public static function sideload( $img, $post_id = 0, $title = '' ) {
		$img = esc_url_raw( trim( (string) $img ), array( 'http', 'https' ) );
		if ( '' === $img ) {
			return 0;
		}

		$existing = self::find_attachment( $img );
		if ( $existing ) {
			return $existing;
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Moitos xornais rexeitan o user-agent de WordPress ou peticións sen Referer.
		$parts  = wp_parse_url( $img );
		$origin = $parts['scheme'] . '://' . $parts['host'] . '/';
		$filter = static function ( $args ) use ( $origin ) {
			$args['user-agent']         = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36';
			$args['headers']            = isset( $args['headers'] ) ? (array) $args['headers'] : array();
			$args['headers']['Referer'] = $origin;
			$args['headers']['Accept']  = 'image/avif,image/webp,image/png,image/jpeg,image/*;q=0.8';
			return $args;
		};

		add_filter( 'http_request_args', $filter );
		$tmp = download_url( $img, 30 );
		remove_filter( 'http_request_args', $filter );

		if ( is_wp_error( $tmp ) ) {
			return 0;
		}

		// O tipo sácase do contido, non da URL (moitas URL de xornais non levan extensión).
		$mime = wp_get_image_mime( $tmp );
		if ( ! $mime || ! isset( self::$mimes[ $mime ] ) ) {
			wp_delete_file( $tmp );
			return 0;
		}

		$name = isset( $parts['path'] ) ? sanitize_file_name( pathinfo( wp_basename( $parts['path'] ), PATHINFO_FILENAME ) ) : '';
		$file = array(
			'name'     => ( '' !== $name ? $name : 'wpslap-imaxe' ) . '.' . self::$mimes[ $mime ],
			'tmp_name' => $tmp,
		);

		$att_id = media_handle_sideload( $file, $post_id, $title );
		if ( is_wp_error( $att_id ) ) {
			wp_delete_file( $tmp );
			return 0;
		}

		update_post_meta( $att_id, WPSLAP_META_SOURCE_URL, $img );
		return (int) $att_id;
	}

	/**
	 * Pasa á mediateca a imaxe externa dunha entrada-ligazón.
	 *
	 * @param int $post_id ID.
	 * @return bool
	 */
	public static function localize( $post_id ) {
		$img = (string) get_post_meta( $post_id, WPSLAP_META_IMAGE_URL, true );
		if ( '' === $img ) {
			return false;
		}
		$att_id = self::sideload( $img, $post_id, get_the_title( $post_id ) );
		if ( ! $att_id ) {
			return false;
		}
		set_post_thumbnail( $post_id, $att_id );
		delete_post_meta( $post_id, WPSLAP_META_IMAGE_URL );
		return true;
	}

	/**
	 * IDs das entradas-ligazón que aínda usan unha imaxe externa.
	 *
	 * @return int[]
	 */
	public static function external_ids() {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'      => 'post',
					'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
					'meta_key'       => WPSLAP_META_IMAGE_URL, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			)
		);
	}

	/**
	 * Sección «Imaxes externas» na pantalla de Ferramentas.
	 */
	public static function render_section() {
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}

		$key    = 'wpslap_localize_' . get_current_user_id();
		$result = isset( $_GET['localized'] ) ? get_transient( $key ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$count  = count( self::external_ids() );
		?>
		<hr>

		<h2 id="wpslap-images"><?php esc_html_e( 'Imaxes externas', 'wp-show-links-as-posts' ); ?></h2>

		<?php
		if ( is_array( $result ) ) :
			delete_transient( $key );
			?>
			<div class="notice <?php echo $result['failed'] ? 'notice-warning' : 'notice-success'; ?> inline">
				<p>
					<?php
					printf(
						/* translators: 1: descargadas, 2: fallidas */
						esc_html__( 'Descargáronse %1$d imaxes á mediateca. %2$d non se puideron descargar e seguen coa URL externa.', 'wp-show-links-as-posts' ),
						(int) $result['done'],
						(int) $result['failed']
					);
					?>
				</p>
				<?php if ( ! empty( $result['failed_ids'] ) ) : ?>
					<ul style="list-style:disc;margin-left:2em">
						<?php foreach ( $result['failed_ids'] as $id ) : ?>
							<li><a href="<?php echo esc_url( WPSLAP_Admin::form_url( $id ) ); ?>"><?php echo esc_html( get_the_title( $id ) ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<p><?php esc_html_e( 'As imaxes enlazadas desde outra web poden non verse: moitos xornais bloquean que se mostren noutros sitios, e os avisos de cookies adoitan bloquear contido de terceiros ata que o visitante acepta. Gardando unha copia na mediateca as imaxes sempre se ven.', 'wp-show-links-as-posts' ); ?></p>

		<?php if ( $count ) : ?>
			<p>
				<?php
				printf(
					/* translators: %d: número de ligazóns */
					esc_html( _n( 'Hai %d ligazón cunha imaxe externa.', 'Hai %d ligazóns cunha imaxe externa.', $count, 'wp-show-links-as-posts' ) ),
					(int) $count
				);
				?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
				<?php wp_nonce_field( self::ACTION, self::NONCE ); ?>
				<?php submit_button( __( 'Descargar as imaxes á mediateca', 'wp-show-links-as-posts' ), 'secondary' ); ?>
			</form>
		<?php else : ?>
			<p><em><?php esc_html_e( 'Todas as imaxes das ligazóns están xa na mediateca.', 'wp-show-links-as-posts' ); ?></em></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Descarga á mediateca todas as imaxes externas.
	 */
	public static function handle_localize() {
		if ( ! current_user_can( 'edit_posts' ) || ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'Non tes permisos para facer isto.', 'wp-show-links-as-posts' ), 403 );
		}
		check_admin_referer( self::ACTION, self::NONCE );

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		$result = array( 'done' => 0, 'failed' => 0, 'failed_ids' => array() );
		foreach ( self::external_ids() as $id ) {
			if ( ! current_user_can( 'edit_post', $id ) ) {
				continue;
			}
			if ( self::localize( $id ) ) {
				$result['done']++;
			} else {
				$result['failed']++;
				$result['failed_ids'][] = $id;
			}
		}

		set_transient( 'wpslap_localize_' . get_current_user_id(), $result, 30 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'localized', 1, WPSLAP_Importer::page_url() ) . '#wpslap-images' );
		exit;
	}

	/**
	 * Busca unha imaxe xa descargada desde a mesma URL.
	 *
	 * @param string $img URL.
	 * @return int
	 */
	private static function find_attachment( $img ) {
		$ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'meta_key'       => WPSLAP_META_SOURCE_URL, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $img, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}
}
