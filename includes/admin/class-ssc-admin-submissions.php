<?php
/**
 * Admin submissions page (list + detail view).
 *
 * @package Steinum_Sport_Clothes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Steinum Sport → Fráboðanir admin screen.
 */
class SSC_Admin_Submissions {

	public const PAGE          = 'ssc-submissions';
	public const NONCE_ACTION  = 'ssc_admin_action';
	public const NONCE_NAME    = 'ssc_nonce';

	public function register(): void {
		add_action( 'admin_init', array( $this, 'maybe_handle_list_bulk_delete' ), 1 );
		add_action( 'admin_post_ssc_submission_action', array( $this, 'handle_action' ) );
		add_action( 'admin_post_ssc_purge', array( $this, 'handle_purge' ) );
		add_action( 'admin_post_ssc_view_pdf', array( $this, 'handle_view_pdf' ) );
		add_action( 'admin_post_ssc_view_excel', array( $this, 'handle_view_excel' ) );
		add_action( 'admin_post_ssc_quick_status', array( $this, 'handle_quick_status' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
	}

	/**
	 * Bulk delete must POST to admin.php so WP_List_Table's `action` / `action2` fields are not
	 * overwritten by admin-post.php's required `action` parameter.
	 */
	public function maybe_handle_list_bulk_delete(): void {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( empty( $_POST[ self::NONCE_NAME ] ) || empty( $_POST['page'] ) || self::PAGE !== (string) wp_unslash( $_POST['page'] ) ) {
			return;
		}
		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		$action  = isset( $_POST['action'] ) ? (string) wp_unslash( $_POST['action'] ) : '-1';
		$action2 = isset( $_POST['action2'] ) ? (string) wp_unslash( $_POST['action2'] ) : '-1';
		$bulk    = ( '-1' !== $action ) ? $action : $action2;
		if ( 'delete' !== $bulk ) {
			return;
		}

		$ids = isset( $_POST['ids'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['ids'] ) ) : array();
		$ids = array_values( array_filter( $ids ) );
		if ( ! $ids ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => self::PAGE,
						'ssc_msg' => 'no_selection',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		SSC_Store::delete_many( $ids );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::PAGE,
					'ssc_msg' => 'bulk_deleted',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function enqueue_admin_styles( string $hook ): void {
		if ( false === strpos( $hook, self::PAGE ) ) {
			return;
		}
		wp_register_style( 'ssc-admin', SSC_URL . 'assets/css/admin.css', array(), SSC_VERSION );
		wp_enqueue_style( 'ssc-admin' );
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Ikki loyvi.', 'steinum-sport-clothes' ) );
		}

		$view = isset( $_GET['view'] ) ? (int) $_GET['view'] : 0;
		if ( $view > 0 ) {
			$this->render_detail( $view );
			return;
		}
		$this->render_list();
	}

	private function render_list(): void {
		$table = new SSC_Admin_List_Table();
		$table->prepare_items();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Fráboðanir', 'steinum-sport-clothes' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . SSC_Settings::PAGE ) ); ?>" class="page-title-action"><?php esc_html_e( 'Stillingar', 'steinum-sport-clothes' ); ?></a>
			<hr class="wp-header-end" />

			<?php $this->maybe_render_admin_notice(); ?>

			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>" />
				<?php $table->views(); ?>
				<p class="search-box">
					<label class="screen-reader-text" for="ssc-search">Leita</label>
					<input type="search" id="ssc-search" name="s" value="<?php echo esc_attr( (string) ( $_GET['s'] ?? '' ) ); ?>" />
					<input type="date" name="from" value="<?php echo esc_attr( (string) ( $_GET['from'] ?? '' ) ); ?>" />
					<input type="date" name="to"   value="<?php echo esc_attr( (string) ( $_GET['to'] ?? '' ) ); ?>" />
					<?php submit_button( __( 'Filtrera', 'steinum-sport-clothes' ), 'secondary', '', false ); ?>
				</p>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>" />
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
				<?php $table->display(); ?>
			</form>

			<hr />
			<h2><?php esc_html_e( 'Viðlíkahald', 'steinum-sport-clothes' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Strika fráboðanir? Hetta er ikki gjørt um aftur.');">
				<input type="hidden" name="action" value="ssc_purge" />
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
				<label>
					<?php esc_html_e( 'Strika fráboðanir eldri enn', 'steinum-sport-clothes' ); ?>
					<input type="number" name="days" value="180" min="1" step="1" />
					<?php esc_html_e( 'dagar', 'steinum-sport-clothes' ); ?>
				</label>
				<?php submit_button( __( 'Strika nú', 'steinum-sport-clothes' ), 'delete', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	private function render_detail( int $id ): void {
		$row = SSC_Store::find( $id );
		if ( ! $row ) {
			echo '<div class="wrap"><h1>Ikki funnin</h1><p><a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ) . '">Aftur</a></p></div>';
			return;
		}
		$labels = SSC_Sanitizer::labels();
		$lines  = array();
		if ( ! empty( $row['lines_json'] ) ) {
			$dec = json_decode( (string) $row['lines_json'], true );
			if ( is_array( $dec ) ) {
				$lines = $dec;
			}
		}
		?>
		<div class="wrap">
			<h1>
				<?php echo esc_html( '#' . (int) $row['id'] . ' — ' . $row['club_name'] ); ?>
				<?php
				$pdf_u = SSC_Store::admin_pdf_url( (int) $row['id'] );
				if ( '' !== $pdf_u ) {
					printf(
						' <a href="%s" class="page-title-action" target="_blank" rel="noopener">%s</a>',
						esc_url( $pdf_u ),
						esc_html__( 'Sí PDF', 'steinum-sport-clothes' )
					);
				}
				$xls_u = SSC_Store::admin_excel_url( (int) $row['id'] );
				if ( '' !== $xls_u ) {
					printf(
						' <a href="%s" class="page-title-action">%s</a>',
						esc_url( $xls_u ),
						esc_html__( 'Sí Excel', 'steinum-sport-clothes' )
					);
				}
				?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ); ?>" class="page-title-action"><?php esc_html_e( '← Aftur', 'steinum-sport-clothes' ); ?></a>
			</h1>

			<?php $this->maybe_render_admin_notice(); ?>

			<div class="ssc-detail-grid" style="display:grid;grid-template-columns:2fr 1fr;gap:24px;align-items:start;">
				<div>
					<table class="widefat striped">
						<tbody>
							<tr><th>Dato</th><td><?php echo esc_html( (string) $row['created_at'] ); ?></td></tr>
							<tr><th>Støða</th><td><?php echo esc_html( SSC_Store::statuses()[ (string) $row['status'] ] ?? (string) $row['status'] ); ?></td></tr>
							<?php foreach ( $labels as $key => $label ) : ?>
								<?php if ( 'order_lines' === $key ) : ?>
									<tr>
										<th><?php echo esc_html( $label ); ?></th>
										<td>
											<?php
											if ( $lines ) {
												echo '<table class="widefat" style="margin:0;">';
												echo '<thead><tr><th>#</th><th>Slag</th><th>Kyn</th><th>Stødd</th><th>Farva</th><th>Nøgd</th><th>Navn</th></tr></thead><tbody>';
												$it = SSC_Sanitizer::item_types();
												$gl = SSC_Sanitizer::gender_labels();
												$i  = 0;
												foreach ( $lines as $ln ) {
													if ( ! is_array( $ln ) ) {
														continue;
													}
													++$i;
													$ii  = (string) ( $ln['item'] ?? '' );
													if ( SSC_Sanitizer::ITEM_CLOTHES === $ii ) {
														$ii = SSC_Sanitizer::ITEM_TSHIRT;
													}
													$ilb = $it[ $ii ] ?? $ii;
													$gkv = '';
													if ( SSC_Sanitizer::item_needs_gender( $ii ) ) {
														$gk = (string) ( $ln['gender'] ?? '' );
														$gkv = $gl[ $gk ] ?? $gk;
													}
													$bum_raw = (string) ( $ln['bumper_color'] ?? '' );
													$bum     = '';
													if ( SSC_Sanitizer::item_uses_farv( $ii ) ) {
														$lbl = class_exists( 'SSC_Order_Items' )
															? SSC_Order_Items::farv_label_for_slug( $ii, $bum_raw )
															: '';
														if ( '' === $lbl && '' !== $bum_raw ) {
															$fa = SSC_Sanitizer::farv_options();
															$lbl = $fa[ $bum_raw ] ?? $bum_raw;
														}
														$bum = $lbl;
													}
													printf(
														'<tr><td>%d</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%d</td><td>%s</td></tr>',
														$i,
														esc_html( $ilb ),
														esc_html( $gkv ),
														esc_html( (string) ( $ln['size'] ?? '' ) ),
														esc_html( $bum ),
														(int) ( $ln['qty'] ?? 0 ),
														esc_html( (string) ( $ln['name'] ?? '' ) )
													);
												}
												echo '</tbody></table>';
											} else {
												// Gomul røð: kvinnur/menn + tekst
												$leg = (int) ( $row['count_women'] ?? 0 ) . ' / ' . (int) ( $row['count_men'] ?? 0 )
													. "\n" . (string) ( $row['sizes_women'] ?? '' ) . "\n" . (string) ( $row['sizes_men'] ?? '' );
												echo nl2br( esc_html( trim( $leg ) ) );
											}
											?>
										</td>
									</tr>
								<?php else : ?>
								<tr>
									<th><?php echo esc_html( $label ); ?></th>
									<td><?php echo nl2br( esc_html( (string) ( $row[ $key ] ?? '' ) ) ); ?></td>
								</tr>
								<?php endif; ?>
							<?php endforeach; ?>
						</tbody>
					</table>

					<h2><?php esc_html_e( 'Sendur teldupostur (admin)', 'steinum-sport-clothes' ); ?></h2>
					<pre style="background:#f6f6f6;border:1px solid #ddd;padding:12px;white-space:pre-wrap;"><?php echo esc_html( (string) $row['email_body'] ); ?></pre>
				</div>

				<div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="ssc_submission_action" />
						<input type="hidden" name="op" value="update" />
						<input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>" />
						<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>

						<h2><?php esc_html_e( 'Skift støðu', 'steinum-sport-clothes' ); ?></h2>
						<select name="status">
							<?php foreach ( SSC_Store::statuses() as $k => $v ) : ?>
								<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $row['status'], $k ); ?>><?php echo esc_html( $v ); ?></option>
							<?php endforeach; ?>
						</select>

						<h2><?php esc_html_e( 'Intern viðmerking', 'steinum-sport-clothes' ); ?></h2>
						<textarea name="note" rows="6" class="large-text"><?php echo esc_textarea( (string) $row['note'] ); ?></textarea>

						<p><?php submit_button( __( 'Goym', 'steinum-sport-clothes' ), 'primary', 'save', false ); ?></p>
					</form>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Strika hesa fráboðanina?');">
						<input type="hidden" name="action" value="ssc_submission_action" />
						<input type="hidden" name="op" value="delete" />
						<input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>" />
						<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
						<?php submit_button( __( 'Strika', 'steinum-sport-clothes' ), 'delete', 'submit', false ); ?>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	public function handle_action(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Ikki loyvi.' );
		}
		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		$op  = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( (string) $_POST['op'] ) ) : '';
		$id  = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$msg = '';

		switch ( $op ) {
			case 'update':
				$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( (string) $_POST['status'] ) ) : '';
				$note   = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['note'] ) ) : '';
				SSC_Store::set_status( $id, $status, $note );
				$msg = 'updated';
				break;
			case 'delete':
				SSC_Store::delete( $id );
				$msg = 'deleted';
				$id  = 0;
				break;
			case 'bulk_delete':
				$ids = isset( $_POST['ids'] ) ? array_map( 'intval', (array) $_POST['ids'] ) : array();
				SSC_Store::delete_many( $ids );
				$msg = 'bulk_deleted';
				break;
		}

		$args = array( 'page' => self::PAGE );
		if ( $id > 0 && 'updated' === $msg ) {
			$args['view'] = $id;
		}
		if ( '' !== $msg ) {
			$args['ssc_msg'] = $msg;
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_view_pdf(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Ikki loyvi.', 'steinum-sport-clothes' ) );
		}
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( $id < 1 || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ), 'ssc_view_pdf_' . $id ) ) {
			wp_die( esc_html__( 'Ógyldugt ummæli.', 'steinum-sport-clothes' ) );
		}
		if ( SSC_Store::output_stored_pdf( $id ) ) {
			exit;
		}
		wp_die( esc_html__( 'PDF ikki funnin ella værdur ikki læstur.', 'steinum-sport-clothes' ) );
	}

	public function handle_quick_status(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Ikki loyvi.', 'steinum-sport-clothes' ) );
		}
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( $id < 1 || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ), 'ssc_quick_status_' . $id ) ) {
			wp_die( esc_html__( 'Ógyldugt ummæli.', 'steinum-sport-clothes' ) );
		}
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( (string) $_GET['status'] ) ) : '';
		if ( SSC_Store::set_status( $id, $status, null ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => self::PAGE,
						'ssc_msg' => 'status_updated',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE ) );
		exit;
	}

	public function handle_view_excel(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Ikki loyvi.', 'steinum-sport-clothes' ) );
		}
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( $id < 1 || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ), 'ssc_view_excel_' . $id ) ) {
			wp_die( esc_html__( 'Ógyldugt ummæli.', 'steinum-sport-clothes' ) );
		}
		if ( SSC_Store::output_excel_for_submission( $id ) ) {
			exit;
		}
		wp_die( esc_html__( 'Excel ikki stovnað.', 'steinum-sport-clothes' ) );
	}

	public function handle_purge(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Ikki loyvi.' );
		}
		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );
		$days    = isset( $_POST['days'] ) ? max( 1, (int) $_POST['days'] ) : 180;
		$deleted = SSC_Store::purge_older_than( $days );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::PAGE,
					'ssc_msg' => 'purged',
					'n'       => $deleted,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	private function maybe_render_admin_notice(): void {
		if ( empty( $_GET['ssc_msg'] ) ) {
			return;
		}
		$msg = (string) $_GET['ssc_msg'];
		$map = array(
			'updated'        => array( 'success', 'Fráboðan dagført.' ),
			'status_updated' => array( 'success', 'Støða dagført.' ),
			'deleted'        => array( 'success', 'Fráboðan strikað.' ),
			'bulk_deleted'   => array( 'success', 'Fráboðanir strikaðar.' ),
			'no_selection'   => array( 'warning', 'Vel minst eina røð til strikan.' ),
			'purged'         => array( 'success', sprintf( 'Strikaðar: %d.', (int) ( $_GET['n'] ?? 0 ) ) ),
		);
		if ( ! isset( $map[ $msg ] ) ) {
			return;
		}
		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $map[ $msg ][0] ),
			esc_html( $map[ $msg ][1] )
		);
	}
}
