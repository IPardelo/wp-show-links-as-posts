<?php
/**
 * Importar / exportar ligazóns en CSV (Ferramentas → Importar / Exportar ligazóns).
 *
 * @package WPShowLinksAsPosts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPSLAP_Importer
 */
class WPSLAP_Importer {

	const PAGE_SLUG = 'wpslap-import';
	const ACTION    = 'wpslap_import';
	const NONCE     = 'wpslap_import_nonce';
	const EXPORT    = 'wpslap_export';
	const SRC_META  = '_wpslap_source_url';

	/**
	 * Alias aceptados na cabeceira do CSV (sen acentos e en minúsculas).
	 *
	 * @var array<string,string[]>
	 */
	private static $columns = array(
		'date'  => array( 'data', 'date', 'fecha' ),
		'time'  => array( 'hora', 'time' ),
		'title' => array( 'titulo', 'title', 'nome', 'nombre' ),
		'url'   => array( 'ligazon', 'link', 'url', 'enlace' ),
		'image' => array( 'imaxe', 'image', 'imagen', 'img' ),
		'cats'  => array( 'categorias', 'categoria', 'categories', 'category' ),
	);

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_import' ) );
		add_action( 'admin_post_' . self::EXPORT, array( __CLASS__, 'handle_export' ) );
	}

	/**
	 * URL da pantalla.
	 *
	 * @return string
	 */
	public static function page_url() {
		return add_query_arg( 'page', self::PAGE_SLUG, admin_url( 'tools.php' ) );
	}

	/**
	 * Submenú «Ferramentas → Importar / Exportar ligazóns», xusto despois de «Exportar».
	 */
	public static function register_page() {
		global $submenu;

		// Posición xusto despois de export.php (buscámola por se outro plugin cambiou a orde).
		$position = 3;
		if ( isset( $submenu['tools.php'] ) ) {
			$i = 0;
			foreach ( $submenu['tools.php'] as $item ) {
				$i++;
				if ( isset( $item[2] ) && 'export.php' === $item[2] ) {
					$position = $i;
					break;
				}
			}
		}

		add_submenu_page(
			'tools.php',
			__( 'Importar / Exportar ligazóns', 'wp-show-links-as-posts' ),
			__( 'Importar / Exportar ligazóns', 'wp-show-links-as-posts' ),
			'edit_posts',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' ),
			$position
		);
	}

	/**
	 * Pantalla: formulario e resultado da última importación.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Non tes permisos para facer isto.', 'wp-show-links-as-posts' ) );
		}

		$key    = 'wpslap_import_' . get_current_user_id();
		$result = isset( $_GET['done'] ) ? get_transient( $key ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$error  = isset( $_GET['wpslap_error'] ) ? sanitize_key( wp_unslash( $_GET['wpslap_error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$errors = array(
			'file'  => __( 'Non se puido ler o ficheiro. Escolle un CSV.', 'wp-show-links-as-posts' ),
			'empty' => __( 'O CSV non ten filas con título e ligazón.', 'wp-show-links-as-posts' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Importar / Exportar ligazóns', 'wp-show-links-as-posts' ); ?></h1>

			<?php if ( $error && isset( $errors[ $error ] ) ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $errors[ $error ] ); ?></p></div>
			<?php endif; ?>

			<?php
			if ( is_array( $result ) ) :
				delete_transient( $key );
				?>
				<div class="notice notice-success">
					<p>
						<?php
						printf(
							/* translators: 1: creadas, 2: omitidas, 3: erros */
							esc_html__( 'Importación rematada: %1$d creadas, %2$d omitidas (xa existían) e %3$d con erros.', 'wp-show-links-as-posts' ),
							(int) $result['created'],
							(int) $result['skipped'],
							(int) $result['failed']
						);
						?>
						<a href="<?php echo esc_url( add_query_arg( 'wpslap_links', 1, admin_url( 'edit.php' ) ) ); ?>"><?php esc_html_e( 'Ver as ligazóns', 'wp-show-links-as-posts' ); ?></a>
					</p>
				</div>
				<table class="widefat striped" style="max-width:1100px;margin-bottom:2em">
					<thead><tr>
						<th style="width:50px"><?php esc_html_e( 'Liña', 'wp-show-links-as-posts' ); ?></th>
						<th><?php esc_html_e( 'Título', 'wp-show-links-as-posts' ); ?></th>
						<th style="width:40%"><?php esc_html_e( 'Resultado', 'wp-show-links-as-posts' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $result['rows'] as $row ) : ?>
						<tr>
							<td><?php echo (int) $row['line']; ?></td>
							<td>
								<?php if ( ! empty( $row['post_id'] ) ) : ?>
									<a href="<?php echo esc_url( WPSLAP_Admin::form_url( $row['post_id'] ) ); ?>"><?php echo esc_html( $row['title'] ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $row['title'] ); ?>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $row['msg'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Importar', 'wp-show-links-as-posts' ); ?></h2>
			<p><?php esc_html_e( 'Sube un CSV cunha fila por ligazón. A primeira fila é a cabeceira con estas columnas (en calquera orde):', 'wp-show-links-as-posts' ); ?></p>
			<table class="widefat" style="max-width:700px">
				<tbody>
					<tr><td><code>data</code></td><td><?php esc_html_e( 'dd/mm/aaaa ou aaaa-mm-dd', 'wp-show-links-as-posts' ); ?></td></tr>
					<tr><td><code>hora</code></td><td><?php esc_html_e( 'hh:mm (opcional)', 'wp-show-links-as-posts' ); ?></td></tr>
					<tr><td><code>titulo</code></td><td><?php esc_html_e( 'obrigatorio', 'wp-show-links-as-posts' ); ?></td></tr>
					<tr><td><code>ligazon</code></td><td><?php esc_html_e( 'obrigatorio', 'wp-show-links-as-posts' ); ?></td></tr>
					<tr><td><code>imaxe</code></td><td><?php esc_html_e( 'URL da imaxe (opcional)', 'wp-show-links-as-posts' ); ?></td></tr>
					<tr><td><code>categorias</code></td><td><?php esc_html_e( 'nomes separados por comas (opcional; as que non existan créanse)', 'wp-show-links-as-posts' ); ?></td></tr>
				</tbody>
			</table>
			<p class="description"><?php esc_html_e( 'Serve separado por comas ou por punto e coma (o CSV que garda Excel). As ligazóns que xa existen na web omítense, así que podes importar o mesmo ficheiro dúas veces sen duplicar nada.', 'wp-show-links-as-posts' ); ?></p>

			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
				<?php wp_nonce_field( self::ACTION, self::NONCE ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wpslap-csv"><?php esc_html_e( 'Ficheiro CSV', 'wp-show-links-as-posts' ); ?></label></th>
						<td><input type="file" id="wpslap-csv" name="wpslap_csv" accept=".csv,text/csv" required></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Imaxes', 'wp-show-links-as-posts' ); ?></th>
						<td>
							<fieldset>
								<?php if ( current_user_can( 'upload_files' ) ) : ?>
									<label><input type="radio" name="wpslap_images" value="library" checked> <?php esc_html_e( 'Descargalas á mediateca (recomendado)', 'wp-show-links-as-posts' ); ?></label><br>
								<?php endif; ?>
								<label><input type="radio" name="wpslap_images" value="url" <?php checked( ! current_user_can( 'upload_files' ) ); ?>> <?php esc_html_e( 'Usar a URL externa', 'wp-show-links-as-posts' ); ?></label><br>
								<label><input type="radio" name="wpslap_images" value="none"> <?php esc_html_e( 'Non importar imaxes', 'wp-show-links-as-posts' ); ?></label>
							</fieldset>
							<p class="description"><?php esc_html_e( 'Se unha imaxe non se pode descargar, úsase a súa URL externa. As imaxes repetidas só se descargan unha vez.', 'wp-show-links-as-posts' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpslap-cat"><?php esc_html_e( 'Categoría por defecto', 'wp-show-links-as-posts' ); ?></label></th>
						<td>
							<?php
							wp_dropdown_categories(
								array(
									'name'             => 'wpslap_category',
									'id'               => 'wpslap-cat',
									'hide_empty'       => false,
									'hierarchical'     => true,
									'selected'         => (int) get_option( 'default_category' ),
									'show_option_none' => __( '— Categoría por defecto —', 'wp-show-links-as-posts' ),
									'option_none_value' => 0,
								)
							);
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpslap-istatus"><?php esc_html_e( 'Estado', 'wp-show-links-as-posts' ); ?></label></th>
						<td>
							<select id="wpslap-istatus" name="wpslap_status">
								<option value="publish"><?php esc_html_e( 'Publicadas', 'wp-show-links-as-posts' ); ?></option>
								<option value="draft"><?php esc_html_e( 'Borrador', 'wp-show-links-as-posts' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Nova lapela', 'wp-show-links-as-posts' ); ?></th>
						<td><label><input type="checkbox" name="wpslap_new_tab" value="1" checked> <?php esc_html_e( 'Abrir as ligazóns nunha nova lapela', 'wp-show-links-as-posts' ); ?></label></td>
					</tr>
				</table>
				<?php submit_button( __( 'Importar', 'wp-show-links-as-posts' ) ); ?>
			</form>

			<hr>

			<h2><?php esc_html_e( 'Exportar', 'wp-show-links-as-posts' ); ?></h2>
			<p><?php esc_html_e( 'Descarga todas as ligazóns nun CSV co mesmo formato que acepta o importador: serve como copia de seguridade ou para levalas a outra web.', 'wp-show-links-as-posts' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::EXPORT ); ?>">
				<?php wp_nonce_field( self::EXPORT, self::NONCE ); ?>
				<p>
					<label><input type="checkbox" name="wpslap_include_drafts" value="1"> <?php esc_html_e( 'Incluír borradores, pendentes, privadas e programadas', 'wp-show-links-as-posts' ); ?></label>
				</p>
				<?php submit_button( __( 'Descargar CSV', 'wp-show-links-as-posts' ), 'secondary' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Procesa o CSV.
	 */
	public static function handle_import() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Non tes permisos para facer isto.', 'wp-show-links-as-posts' ), 403 );
		}
		check_admin_referer( self::ACTION, self::NONCE );

		$back = static function ( $error ) {
			wp_safe_redirect( add_query_arg( 'wpslap_error', $error, self::page_url() ) );
			exit;
		};

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$file = isset( $_FILES['wpslap_csv'] ) ? $_FILES['wpslap_csv'] : null;
		if ( ! $file || ! empty( $file['error'] ) || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			$back( 'file' );
		}

		$rows = self::parse_csv( file_get_contents( $file['tmp_name'] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! $rows ) {
			$back( 'empty' );
		}

		$images = isset( $_POST['wpslap_images'] ) ? sanitize_key( wp_unslash( $_POST['wpslap_images'] ) ) : 'url';
		if ( 'library' === $images && ! current_user_can( 'upload_files' ) ) {
			$images = 'url';
		}
		$cat     = isset( $_POST['wpslap_category'] ) ? absint( $_POST['wpslap_category'] ) : 0;
		$status  = ( isset( $_POST['wpslap_status'] ) && 'draft' === $_POST['wpslap_status'] ) ? 'draft' : 'publish';
		$status  = ( 'publish' === $status && ! current_user_can( 'publish_posts' ) ) ? 'pending' : $status;
		$new_tab = empty( $_POST['wpslap_new_tab'] ) ? '0' : '1';

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		wp_defer_term_counting( true );

		if ( 'library' === $images ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$result    = array( 'created' => 0, 'skipped' => 0, 'failed' => 0, 'rows' => array() );
		$att_cache = array();

		foreach ( $rows as $row ) {
			$line  = $row['line'];
			$title = sanitize_text_field( $row['title'] );
			$url   = esc_url_raw( trim( $row['url'] ), array( 'http', 'https' ) );
			$img   = esc_url_raw( trim( $row['image'] ), array( 'http', 'https' ) );
			$parts = $url ? wp_parse_url( $url ) : false;

			if ( '' === $title || ! $parts || empty( $parts['host'] ) ) {
				$result['failed']++;
				$result['rows'][] = array( 'line' => $line, 'title' => $title, 'msg' => __( 'Erro: falta o título ou a ligazón non é válida.', 'wp-show-links-as-posts' ) );
				continue;
			}

			$existing = self::find_existing( $url );
			if ( $existing ) {
				$result['skipped']++;
				$result['rows'][] = array( 'line' => $line, 'title' => $title, 'post_id' => $existing, 'msg' => __( 'Omitida: esta ligazón xa existe.', 'wp-show-links-as-posts' ) );
				continue;
			}

			$date    = self::parse_date( $row['date'], $row['time'] );
			$post_id = wp_insert_post(
				wp_slash(
					array(
						'post_type'     => 'post',
						'post_title'    => $title,
						'post_content'  => '',
						'post_status'   => $status,
						'post_author'   => get_current_user_id(),
						'post_date'     => $date,
						'post_date_gmt' => get_gmt_from_date( $date ),
						'edit_date'     => true,
						'post_category' => $cat ? array( $cat ) : array(),
						'meta_input'    => array(
							WPSLAP_META_URL     => $url,
							WPSLAP_META_NEW_TAB => $new_tab,
						),
					)
				),
				true
			);

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				$result['failed']++;
				$result['rows'][] = array( 'line' => $line, 'title' => $title, 'msg' => __( 'Erro ao crear a entrada.', 'wp-show-links-as-posts' ) );
				continue;
			}

			set_post_format( $post_id, 'link' );
			$msg = __( 'Creada.', 'wp-show-links-as-posts' );

			if ( '' !== $row['cats'] ) {
				$cat_ids = self::resolve_categories( $row['cats'] );
				if ( $cat_ids ) {
					wp_set_post_categories( $post_id, $cat_ids );
				}
			}

			if ( $img && 'none' !== $images ) {
				$att_id = 0;
				if ( 'library' === $images ) {
					$att_id = isset( $att_cache[ $img ] ) ? $att_cache[ $img ] : self::find_attachment( $img );
					if ( ! $att_id ) {
						$att_id = media_sideload_image( $img, $post_id, $title, 'id' );
						if ( is_wp_error( $att_id ) ) {
							$att_id = 0;
						} else {
							update_post_meta( $att_id, self::SRC_META, $img );
						}
					}
				}
				if ( $att_id ) {
					$att_cache[ $img ] = $att_id;
					set_post_thumbnail( $post_id, $att_id );
					$msg = __( 'Creada, coa imaxe na mediateca.', 'wp-show-links-as-posts' );
				} else {
					update_post_meta( $post_id, WPSLAP_META_IMAGE_URL, $img );
					$msg = 'library' === $images
						? __( 'Creada. A imaxe non se puido descargar: úsase a URL externa.', 'wp-show-links-as-posts' )
						: __( 'Creada, coa imaxe desde URL.', 'wp-show-links-as-posts' );
				}
			} elseif ( ! $img ) {
				$msg = __( 'Creada, sen imaxe.', 'wp-show-links-as-posts' );
			}

			$result['created']++;
			$result['rows'][] = array( 'line' => $line, 'title' => $title, 'post_id' => $post_id, 'msg' => $msg );
		}

		wp_defer_term_counting( false );

		set_transient( 'wpslap_import_' . get_current_user_id(), $result, 30 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'done', 1, self::page_url() ) );
		exit;
	}

	/**
	 * Descarga todas as ligazóns nun CSV.
	 */
	public static function handle_export() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Non tes permisos para facer isto.', 'wp-show-links-as-posts' ), 403 );
		}
		check_admin_referer( self::EXPORT, self::NONCE );

		$statuses = empty( $_POST['wpslap_include_drafts'] )
			? array( 'publish' )
			: array( 'publish', 'future', 'draft', 'pending', 'private' );

		$ids = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => $statuses,
				'meta_key'       => WPSLAP_META_URL, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="ligazons-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $out, "\xEF\xBB\xBF" ); // BOM para que Excel lía ben os acentos. // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fputcsv( $out, array( 'data', 'hora', 'titulo', 'ligazon', 'imaxe', 'categorias' ), ',', '"', '\\' );

		foreach ( $ids as $id ) {
			$post  = get_post( $id );
			$thumb = get_post_thumbnail_id( $post );
			$image = $thumb ? wp_get_attachment_url( $thumb ) : (string) get_post_meta( $id, WPSLAP_META_IMAGE_URL, true );
			$cats  = wp_get_post_categories( $id, array( 'fields' => 'names' ) );

			fputcsv(
				$out,
				array(
					mysql2date( 'd/m/Y', $post->post_date ),
					mysql2date( 'H:i', $post->post_date ),
					html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
					wpslap_get_link_url( $post ),
					$image ? $image : '',
					implode( ', ', (array) $cats ),
				),
				',',
				'"',
				'\\'
			);
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Converte «Prensa, Entrevistas» en IDs de categoría, creando as que falten
	 * se o usuario pode xestionar categorías.
	 *
	 * @param string $list Nomes separados por comas ou barras verticais.
	 * @return int[]
	 */
	private static function resolve_categories( $list ) {
		$ids = array();
		foreach ( preg_split( '/[,|]/', $list ) as $name ) {
			$name = trim( $name );
			if ( '' === $name ) {
				continue;
			}
			$term = get_term_by( 'name', $name, 'category' );
			if ( ! $term ) {
				$term = get_term_by( 'slug', sanitize_title( $name ), 'category' );
			}
			if ( $term ) {
				$ids[] = (int) $term->term_id;
			} elseif ( current_user_can( 'manage_categories' ) ) {
				$new = wp_insert_term( $name, 'category' );
				if ( ! is_wp_error( $new ) ) {
					$ids[] = (int) $new['term_id'];
				}
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Le o CSV e devolve filas con date/time/title/url/image.
	 *
	 * @param string $raw Contido do ficheiro.
	 * @return array
	 */
	private static function parse_csv( $raw ) {
		$raw = (string) $raw;
		if ( '' === trim( $raw ) ) {
			return array();
		}
		// Quitar BOM e pasar a UTF-8 se vén en Windows-1252 / ISO-8859-1.
		$raw = preg_replace( '/^\xEF\xBB\xBF/', '', $raw );
		if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $raw, 'UTF-8' ) ) {
			$raw = mb_convert_encoding( $raw, 'UTF-8', 'Windows-1252' );
		}

		$first = strtok( $raw, "\n" );
		$delim = substr_count( $first, ';' ) > substr_count( $first, ',' ) ? ';' : ',';

		$fh = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $fh, $raw ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		rewind( $fh );

		$header = fgetcsv( $fh, 0, $delim, '"', '\\' );
		$map    = self::map_header( (array) $header );
		$line   = 1;

		// Sen cabeceira recoñecible: orde por defecto e a primeira fila son datos.
		if ( ! isset( $map['title'], $map['url'] ) ) {
			$map = array( 'date' => 0, 'time' => 1, 'title' => 2, 'url' => 3, 'image' => 4, 'cats' => 5 );
			rewind( $fh );
			$line = 0;
		}

		$rows = array();
		while ( false !== ( $cols = fgetcsv( $fh, 0, $delim, '"', '\\' ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			$line++;
			if ( array( null ) === $cols || '' === trim( implode( '', $cols ) ) ) {
				continue;
			}
			$get    = static function ( $key ) use ( $map, $cols ) {
				return isset( $map[ $key ], $cols[ $map[ $key ] ] ) ? trim( (string) $cols[ $map[ $key ] ] ) : '';
			};
			$rows[] = array(
				'line'  => $line,
				'date'  => $get( 'date' ),
				'time'  => $get( 'time' ),
				'title' => $get( 'title' ),
				'url'   => $get( 'url' ),
				'image' => $get( 'image' ),
				'cats'  => $get( 'cats' ),
			);
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return $rows;
	}

	/**
	 * Relaciona as columnas da cabeceira cos campos.
	 *
	 * @param array $header Cabeceira.
	 * @return array<string,int>
	 */
	private static function map_header( array $header ) {
		$map = array();
		foreach ( $header as $i => $name ) {
			$name = strtolower( remove_accents( trim( (string) $name ) ) );
			foreach ( self::$columns as $field => $aliases ) {
				if ( ! isset( $map[ $field ] ) && in_array( $name, $aliases, true ) ) {
					$map[ $field ] = $i;
				}
			}
		}
		return $map;
	}

	/**
	 * Converte data e hora do CSV a 'Y-m-d H:i:s' (hora local do sitio).
	 *
	 * @param string $date Data.
	 * @param string $time Hora.
	 * @return string
	 */
	private static function parse_date( $date, $time ) {
		$date = trim( $date );
		$time = str_replace( '.', ':', trim( $time ) );
		if ( '' === $date ) {
			return current_time( 'mysql' );
		}
		if ( ! preg_match( '/^\d{1,2}:\d{2}(:\d{2})?$/', $time ) ) {
			$time = '00:00';
		}
		$value   = $date . ' ' . ( strlen( $time ) === 4 ? '0' . $time : $time );
		$formats = array( 'd/m/Y H:i', 'd/m/Y H:i:s', 'j/n/Y H:i', 'd-m-Y H:i', 'Y-m-d H:i', 'Y-m-d H:i:s' );
		foreach ( $formats as $format ) {
			$dt = date_create_immutable_from_format( '!' . $format, $value, wp_timezone() );
			if ( $dt ) {
				return $dt->format( 'Y-m-d H:i:s' );
			}
		}
		$ts = strtotime( $date );
		return $ts ? gmdate( 'Y-m-d', $ts ) . ' ' . $time . ':00' : current_time( 'mysql' );
	}

	/**
	 * Busca unha entrada-ligazón coa mesma URL (en calquera estado agás papeleira).
	 *
	 * @param string $url URL.
	 * @return int
	 */
	private static function find_existing( $url ) {
		$ids = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'meta_key'       => WPSLAP_META_URL, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $url, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
			)
		);
		return $ids ? (int) $ids[0] : 0;
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
				'meta_key'       => self::SRC_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $img, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}
}
