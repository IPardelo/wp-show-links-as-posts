<?php
/**
 * Parte de administración: botón, formulario e integración coa lista de entradas.
 *
 * @package WPShowLinksAsPosts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPSLAP_Admin
 */
class WPSLAP_Admin {

	const PAGE_SLUG = 'wpslap-link';
	const ACTION    = 'wpslap_save_link';
	const NONCE     = 'wpslap_save_link_nonce';

	/**
	 * Rexistra os hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_save' ) );
		add_action( 'load-post.php', array( __CLASS__, 'redirect_classic_edit' ) );

		add_filter( 'get_edit_post_link', array( __CLASS__, 'filter_edit_link' ), 10, 3 );
		add_filter( 'display_post_states', array( __CLASS__, 'post_states' ), 10, 2 );
		add_filter( 'views_edit-post', array( __CLASS__, 'add_view' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_list_query' ) );
	}

	/**
	 * URL do formulario (novo ou edición).
	 *
	 * @param int $post_id ID da entrada a editar (0 = nova).
	 * @return string
	 */
	public static function form_url( $post_id = 0 ) {
		$args = array( 'page' => self::PAGE_SLUG );
		if ( $post_id ) {
			$args['post'] = (int) $post_id;
		}
		return add_query_arg( $args, admin_url( 'edit.php' ) );
	}

	/**
	 * Submenú «Entradas → Engadir ligazón».
	 */
	public static function register_page() {
		add_submenu_page(
			'edit.php',
			__( 'Engadir ligazón', 'wp-show-links-as-posts' ),
			__( 'Engadir ligazón', 'wp-show-links-as-posts' ),
			'edit_posts',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' ),
			2
		);
	}

	/**
	 * Scripts e estilos.
	 *
	 * @param string $hook Hook da pantalla actual.
	 */
	public static function enqueue( $hook ) {
		$screen = get_current_screen();

		// Botón «Engadir ligazón» xunto a «Engadir entrada».
		if ( $screen && 'edit-post' === $screen->id && current_user_can( 'edit_posts' ) ) {
			wp_enqueue_script( 'wpslap-list', WPSLAP_URL . 'assets/admin-list.js', array(), WPSLAP_VERSION, true );
			wp_localize_script(
				'wpslap-list',
				'wpslapList',
				array(
					'url'   => self::form_url(),
					'label' => __( 'Engadir ligazón', 'wp-show-links-as-posts' ),
				)
			);
		}

		// Formulario.
		if ( $screen && 'posts_page_' . self::PAGE_SLUG === $screen->id ) {
			wp_enqueue_media();
			wp_enqueue_style( 'wpslap-form', WPSLAP_URL . 'assets/admin-form.css', array(), WPSLAP_VERSION );
			wp_enqueue_script( 'wpslap-form', WPSLAP_URL . 'assets/admin-form.js', array( 'jquery' ), WPSLAP_VERSION, true );
			wp_localize_script(
				'wpslap-form',
				'wpslapForm',
				array(
					'frameTitle'  => __( 'Escoller imaxe destacada', 'wp-show-links-as-posts' ),
					'frameButton' => __( 'Usar esta imaxe', 'wp-show-links-as-posts' ),
				)
			);
		}
	}

	/**
	 * O título da entrada na lista (e calquera ligazón «Editar») leva ao noso formulario.
	 *
	 * @param string $link    Ligazón de edición.
	 * @param int    $post_id ID.
	 * @param string $context 'display' ou 'raw'.
	 * @return string
	 */
	public static function filter_edit_link( $link, $post_id, $context ) {
		if ( ! wpslap_is_link_post( $post_id ) ) {
			return $link;
		}
		$url = self::form_url( $post_id );
		return 'display' === $context ? esc_url( $url ) : $url;
	}

	/**
	 * Se alguén abre post.php?action=edit dunha entrada-ligazón, mándao ao formulario.
	 */
	public static function redirect_classic_edit() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$action  = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		// phpcs:enable
		if ( 'edit' === $action && $post_id && wpslap_is_link_post( $post_id ) ) {
			wp_safe_redirect( self::form_url( $post_id ) );
			exit;
		}
	}

	/**
	 * Etiqueta «Ligazón» ao lado do título na lista.
	 *
	 * @param array   $states Estados.
	 * @param WP_Post $post   Entrada.
	 * @return array
	 */
	public static function post_states( $states, $post ) {
		if ( wpslap_is_link_post( $post ) ) {
			$states['wpslap'] = __( 'Ligazón', 'wp-show-links-as-posts' );
		}
		return $states;
	}

	/**
	 * Vista «Ligazóns (n)» enriba da lista de entradas.
	 *
	 * @param array $views Vistas.
	 * @return array
	 */
	public static function add_view( $views ) {
		$count = ( new WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => 'any',
				'meta_key'       => WPSLAP_META_URL, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => false,
			)
		) )->found_posts;

		if ( ! $count ) {
			return $views;
		}

		$current = isset( $_GET['wpslap_links'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$views['wpslap'] = sprintf(
			'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
			esc_url( add_query_arg( 'wpslap_links', 1, admin_url( 'edit.php' ) ) ),
			$current ? ' class="current" aria-current="page"' : '',
			esc_html__( 'Ligazóns', 'wp-show-links-as-posts' ),
			(int) $count
		);
		return $views;
	}

	/**
	 * Aplica o filtro da vista «Ligazóns».
	 *
	 * @param WP_Query $query Consulta.
	 */
	public static function filter_list_query( $query ) {
		if ( ! $query->is_main_query() || ! isset( $_GET['wpslap_links'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'edit-post' === $screen->id ) {
			$query->set( 'meta_key', WPSLAP_META_URL );
		}
	}

	/**
	 * Pinta o formulario.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Non tes permisos para facer isto.', 'wp-show-links-as-posts' ) );
		}

		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post    = $post_id ? get_post( $post_id ) : null;

		if ( $post_id ) {
			if ( ! $post || ! wpslap_is_link_post( $post ) ) {
				wp_die( esc_html__( 'Esta entrada non é unha ligazón.', 'wp-show-links-as-posts' ) );
			}
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				wp_die( esc_html__( 'Non tes permisos para editar esta ligazón.', 'wp-show-links-as-posts' ) );
			}
		}

		$title     = $post ? $post->post_title : '';
		$url       = $post ? wpslap_get_link_url( $post ) : '';
		$date      = $post ? mysql2date( 'Y-m-d\TH:i', $post->post_date ) : current_time( 'Y-m-d\TH:i' );
		$status    = $post ? $post->post_status : 'publish';
		$status    = 'future' === $status ? 'publish' : $status;
		$new_tab   = $post ? (bool) get_post_meta( $post_id, WPSLAP_META_NEW_TAB, true ) : true;
		$thumb_id  = $post ? (int) get_post_thumbnail_id( $post ) : 0;
		$image_url = $post ? (string) get_post_meta( $post_id, WPSLAP_META_IMAGE_URL, true ) : '';
		$source    = $thumb_id ? 'library' : ( $image_url ? 'url' : 'none' );
		$thumb_src = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium' ) : '';
		$cats      = $post ? wp_get_post_categories( $post_id ) : array( (int) get_option( 'default_category' ) );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$updated = isset( $_GET['updated'] );
		$error   = isset( $_GET['wpslap_error'] ) ? sanitize_key( wp_unslash( $_GET['wpslap_error'] ) ) : '';
		// phpcs:enable

		$errors = array(
			'title' => __( 'O título é obrigatorio.', 'wp-show-links-as-posts' ),
			'url'   => __( 'A ligazón ten que ser unha URL válida (http ou https).', 'wp-show-links-as-posts' ),
			'save'  => __( 'Non se puido gardar a ligazón.', 'wp-show-links-as-posts' ),
		);
		?>
		<div class="wrap wpslap-wrap">
			<h1 class="wp-heading-inline">
				<?php echo $post ? esc_html__( 'Editar ligazón', 'wp-show-links-as-posts' ) : esc_html__( 'Engadir ligazón', 'wp-show-links-as-posts' ); ?>
			</h1>
			<?php if ( $post ) : ?>
				<a href="<?php echo esc_url( self::form_url() ); ?>" class="page-title-action"><?php esc_html_e( 'Engadir ligazón', 'wp-show-links-as-posts' ); ?></a>
			<?php endif; ?>
			<hr class="wp-header-end">

			<?php if ( $updated && $post ) : ?>
				<div class="notice notice-success is-dismissible"><p>
					<?php esc_html_e( 'Ligazón gardada.', 'wp-show-links-as-posts' ); ?>
					<a href="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>"><?php esc_html_e( 'Volver ás entradas', 'wp-show-links-as-posts' ); ?></a>
				</p></div>
			<?php endif; ?>
			<?php if ( $error && isset( $errors[ $error ] ) ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $errors[ $error ] ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wpslap-form">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
				<input type="hidden" name="post_id" value="<?php echo (int) $post_id; ?>">
				<?php wp_nonce_field( self::ACTION, self::NONCE ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wpslap-title"><?php esc_html_e( 'Título', 'wp-show-links-as-posts' ); ?></label></th>
						<td><input type="text" id="wpslap-title" name="wpslap_title" class="large-text" required value="<?php echo esc_attr( $title ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="wpslap-url"><?php esc_html_e( 'Ligazón', 'wp-show-links-as-posts' ); ?></label></th>
						<td>
							<input type="url" id="wpslap-url" name="wpslap_url" class="large-text code" required placeholder="https://" value="<?php echo esc_attr( $url ); ?>">
							<p class="description"><?php esc_html_e( 'Enderezo ao que levará a entrada ao premela.', 'wp-show-links-as-posts' ); ?></p>
							<label class="wpslap-inline">
								<input type="checkbox" name="wpslap_new_tab" value="1" <?php checked( $new_tab ); ?>>
								<?php esc_html_e( 'Abrir nunha nova lapela', 'wp-show-links-as-posts' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpslap-date"><?php esc_html_e( 'Data e hora', 'wp-show-links-as-posts' ); ?></label></th>
						<td>
							<input type="datetime-local" id="wpslap-date" name="wpslap_date" value="<?php echo esc_attr( $date ); ?>">
							<p class="description"><?php esc_html_e( 'Se a data é futura, a ligazón quedará programada.', 'wp-show-links-as-posts' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Imaxe destacada', 'wp-show-links-as-posts' ); ?></th>
						<td>
							<fieldset class="wpslap-sources">
								<label><input type="radio" name="wpslap_image_source" value="none" <?php checked( $source, 'none' ); ?>> <?php esc_html_e( 'Sen imaxe', 'wp-show-links-as-posts' ); ?></label>
								<label><input type="radio" name="wpslap_image_source" value="library" <?php checked( $source, 'library' ); ?>> <?php esc_html_e( 'Da mediateca', 'wp-show-links-as-posts' ); ?></label>
								<label><input type="radio" name="wpslap_image_source" value="url" <?php checked( $source, 'url' ); ?>> <?php esc_html_e( 'Desde unha URL', 'wp-show-links-as-posts' ); ?></label>
							</fieldset>

							<div class="wpslap-source wpslap-source-library" data-source="library">
								<input type="hidden" id="wpslap-image-id" name="wpslap_image_id" value="<?php echo (int) $thumb_id; ?>">
								<div class="wpslap-preview" id="wpslap-library-preview">
									<?php if ( $thumb_src ) : ?>
										<img src="<?php echo esc_url( $thumb_src ); ?>" alt="">
									<?php endif; ?>
								</div>
								<button type="button" class="button" id="wpslap-pick"><?php esc_html_e( 'Escoller imaxe', 'wp-show-links-as-posts' ); ?></button>
								<button type="button" class="button-link button-link-delete" id="wpslap-remove" <?php echo $thumb_id ? '' : 'hidden'; ?>><?php esc_html_e( 'Quitar', 'wp-show-links-as-posts' ); ?></button>
							</div>

							<div class="wpslap-source wpslap-source-url" data-source="url">
								<input type="url" id="wpslap-image-url" name="wpslap_image_url" class="large-text code" placeholder="https://…/imaxe.jpg" value="<?php echo esc_attr( $image_url ); ?>">
								<div class="wpslap-preview" id="wpslap-url-preview">
									<?php if ( $image_url ) : ?>
										<img src="<?php echo esc_url( $image_url ); ?>" alt="">
									<?php endif; ?>
								</div>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Categorías', 'wp-show-links-as-posts' ); ?></th>
						<td>
							<div class="wpslap-cats">
								<ul class="categorychecklist">
									<?php
									wp_terms_checklist(
										$post_id,
										array(
											'taxonomy'      => 'category',
											'selected_cats' => $cats,
											'checked_ontop' => false,
										)
									);
									?>
								</ul>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpslap-status"><?php esc_html_e( 'Estado', 'wp-show-links-as-posts' ); ?></label></th>
						<td>
							<select id="wpslap-status" name="wpslap_status">
								<option value="publish" <?php selected( $status, 'publish' ); ?>><?php esc_html_e( 'Publicada', 'wp-show-links-as-posts' ); ?></option>
								<option value="pending" <?php selected( $status, 'pending' ); ?>><?php esc_html_e( 'Pendente de revisión', 'wp-show-links-as-posts' ); ?></option>
								<option value="draft" <?php selected( $status, 'draft' ); ?>><?php esc_html_e( 'Borrador', 'wp-show-links-as-posts' ); ?></option>
								<option value="private" <?php selected( $status, 'private' ); ?>><?php esc_html_e( 'Privada', 'wp-show-links-as-posts' ); ?></option>
							</select>
						</td>
					</tr>
				</table>

				<p class="submit">
					<?php submit_button( $post ? __( 'Actualizar ligazón', 'wp-show-links-as-posts' ) : __( 'Gardar ligazón', 'wp-show-links-as-posts' ), 'primary', 'submit', false ); ?>
					<?php if ( $post && current_user_can( 'delete_post', $post_id ) ) : ?>
						<a class="submitdelete wpslap-delete" href="<?php echo esc_url( get_delete_post_link( $post_id ) ); ?>"><?php esc_html_e( 'Mover á papeleira', 'wp-show-links-as-posts' ); ?></a>
					<?php endif; ?>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Garda (crea ou actualiza) a entrada-ligazón.
	 */
	public static function handle_save() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Non tes permisos para facer isto.', 'wp-show-links-as-posts' ), 403 );
		}
		check_admin_referer( self::ACTION, self::NONCE );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( $post_id && ( ! wpslap_is_link_post( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) ) {
			wp_die( esc_html__( 'Non tes permisos para editar esta ligazón.', 'wp-show-links-as-posts' ), 403 );
		}

		$back = static function ( $error ) use ( $post_id ) {
			wp_safe_redirect( add_query_arg( 'wpslap_error', $error, self::form_url( $post_id ) ) );
			exit;
		};

		$title = isset( $_POST['wpslap_title'] ) ? sanitize_text_field( wp_unslash( $_POST['wpslap_title'] ) ) : '';
		$url   = isset( $_POST['wpslap_url'] ) ? esc_url_raw( trim( wp_unslash( $_POST['wpslap_url'] ) ), array( 'http', 'https' ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( '' === $title ) {
			$back( 'title' );
		}
		$parts = $url ? wp_parse_url( $url ) : false;
		if ( ! $parts || empty( $parts['host'] ) ) {
			$back( 'url' );
		}

		// Data (hora local do sitio).
		$raw_date = isset( $_POST['wpslap_date'] ) ? sanitize_text_field( wp_unslash( $_POST['wpslap_date'] ) ) : '';
		$dt       = $raw_date ? date_create_immutable_from_format( 'Y-m-d\TH:i', $raw_date, wp_timezone() ) : false;
		$date     = $dt ? $dt->format( 'Y-m-d H:i:s' ) : current_time( 'mysql' );

		// Estado.
		$status = isset( $_POST['wpslap_status'] ) ? sanitize_key( wp_unslash( $_POST['wpslap_status'] ) ) : 'publish';
		if ( ! in_array( $status, array( 'publish', 'pending', 'draft', 'private' ), true ) ) {
			$status = 'draft';
		}
		if ( in_array( $status, array( 'publish', 'private' ), true ) && ! current_user_can( 'publish_posts' ) ) {
			$status = 'pending';
		}

		$cats = isset( $_POST['post_category'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['post_category'] ) ) : array();
		$cats = array_values( array_filter( $cats ) );

		$postarr = array(
			'post_type'     => 'post',
			'post_title'    => $title,
			'post_status'   => $status,
			'post_date'     => $date,
			'post_date_gmt' => get_gmt_from_date( $date ),
			'edit_date'     => true,
			'post_category' => $cats,
			'meta_input'    => array(
				WPSLAP_META_URL     => $url,
				WPSLAP_META_NEW_TAB => empty( $_POST['wpslap_new_tab'] ) ? '0' : '1',
			),
		);

		if ( $post_id ) {
			$postarr['ID'] = $post_id;
			$result        = wp_update_post( wp_slash( $postarr ), true );
		} else {
			$postarr['post_content'] = '';
			$postarr['post_author']  = get_current_user_id();
			$result                  = wp_insert_post( wp_slash( $postarr ), true );
		}

		if ( is_wp_error( $result ) || ! $result ) {
			$back( 'save' );
		}
		$post_id = (int) $result;

		set_post_format( $post_id, 'link' );

		// Imaxe destacada.
		$source = isset( $_POST['wpslap_image_source'] ) ? sanitize_key( wp_unslash( $_POST['wpslap_image_source'] ) ) : 'none';
		$att_id = isset( $_POST['wpslap_image_id'] ) ? absint( $_POST['wpslap_image_id'] ) : 0;
		$img    = isset( $_POST['wpslap_image_url'] ) ? esc_url_raw( trim( wp_unslash( $_POST['wpslap_image_url'] ) ), array( 'http', 'https' ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( 'library' === $source && $att_id && wp_attachment_is_image( $att_id ) ) {
			set_post_thumbnail( $post_id, $att_id );
			delete_post_meta( $post_id, WPSLAP_META_IMAGE_URL );
		} elseif ( 'url' === $source && $img ) {
			delete_post_thumbnail( $post_id );
			update_post_meta( $post_id, WPSLAP_META_IMAGE_URL, $img );
		} else {
			delete_post_thumbnail( $post_id );
			delete_post_meta( $post_id, WPSLAP_META_IMAGE_URL );
		}

		wp_safe_redirect( add_query_arg( 'updated', 1, self::form_url( $post_id ) ) );
		exit;
	}
}
