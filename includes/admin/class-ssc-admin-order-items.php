<?php
/**
 * Steinum Sport → Items admin screen (order-line catalog).
 *
 * @package Steinum_Sport_Clothes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Saved catalog of order-line types (slug, label, which fields appear).
 */
class SSC_Admin_Order_Items {

	public const PAGE = 'ssc-order-items';

	public function register(): void {
		add_action( 'admin_post_ssc_save_order_items', array( $this, 'handle_save' ) );
		add_action( 'admin_post_ssc_reset_order_items', array( $this, 'handle_reset' ) );
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$catalog = SSC_Order_Items::get_catalog();
		$saved   = isset( $_GET['ssc_items_saved'] ) ? (string) sanitize_text_field( wp_unslash( (string) $_GET['ssc_items_saved'] ) ) : '';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Steinum Sport — Items', 'steinum-sport-clothes' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Áset sløg í fall-listan í skrásetingarformularinum. Burknapunkt merkir hvørji felt kundi skal vita (kyn, støð, farv …).', 'steinum-sport-clothes' ); ?>
			</p>

			<?php if ( '1' === $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Broytingar eru goymdar.', 'steinum-sport-clothes' ); ?></p></div>
			<?php elseif ( '0' === $saved ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Goymdir miseydnaðust.', 'steinum-sport-clothes' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="ssc-order-items-form">
				<?php wp_nonce_field( 'ssc_save_order_items' ); ?>
				<input type="hidden" name="action" value="ssc_save_order_items" />

				<table class="widefat striped ssc-order-items-table" style="max-width:960px;">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Burknapunkt (slug)', 'steinum-sport-clothes' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Heiti (víst)', 'steinum-sport-clothes' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Kyn', 'steinum-sport-clothes' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Stødd', 'steinum-sport-clothes' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Ynskt farv', 'steinum-sport-clothes' ); ?></th>
						</tr>
					</thead>
					<tbody id="ssc-order-items-rows">
						<?php
						foreach ( $catalog as $i => $row ) {
							self::render_row_inputs( $i, $row );
						}
						?>
					</tbody>
				</table>

				<p>
					<button type="button" class="button" id="ssc-order-items-add-row"><?php esc_html_e( 'Legg røð afturat', 'steinum-sport-clothes' ); ?></button>
				</p>

				<?php submit_button( __( 'Goym', 'steinum-sport-clothes' ) ); ?>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:1.5em;" onsubmit="return confirm('<?php echo esc_js( __( 'Endurseta til sjálvvirkt grundgevið?', 'steinum-sport-clothes' ) ); ?>');">
				<?php wp_nonce_field( 'ssc_reset_order_items' ); ?>
				<input type="hidden" name="action" value="ssc_reset_order_items" />
				<?php submit_button( __( 'Endurset grundgevið', 'steinum-sport-clothes' ), 'secondary' ); ?>
			</form>
			<script>
			(function () {
				var btn = document.getElementById('ssc-order-items-add-row');
				var tbody = document.getElementById('ssc-order-items-rows');
				if (!btn || !tbody) return;
				function nextIdx() {
					var n = tbody.querySelectorAll('tr').length;
					return n;
				}
				btn.addEventListener('click', function () {
					var tr = tbody.querySelector('tr');
					if (!tr) return;
					var ix = nextIdx();
					var clone = tr.cloneNode(true);
					var inp = clone.querySelectorAll('input');
					var k;
					for (k = 0; k < inp.length; k++) {
						var nm = inp[k].name;
						if (!nm || nm.indexOf('ssc_items') === -1) continue;
						nm = nm.replace(/ssc_items\[\d]/, 'ssc_items[' + ix + ']');
						inp[k].name = nm;
						if (inp[k].type === 'text') inp[k].value = '';
						else if (inp[k].type === 'checkbox') inp[k].checked = false;
					}
					tbody.appendChild(clone);
				});
			})();
			</script>
		</div>
		<?php
	}

	/**
	 * @param array{id: string, label: string, needs_gender: bool, needs_size: bool, uses_farv: bool} $row
	 */
	private static function render_row_inputs( int $i, array $row ): void {
		$id   = (string) ( $row['id'] ?? '' );
		$lab  = (string) ( $row['label'] ?? '' );
		$g    = ! empty( $row['needs_gender'] );
		$s    = ! empty( $row['needs_size'] );
		$f    = ! empty( $row['uses_farv'] );
		?>
		<tr class="ssc-order-item-row">
			<td>
				<input type="text" name="ssc_items[<?php echo (int) $i; ?>][id]" value="<?php echo esc_attr( $id ); ?>"
					class="regular-text code" autocomplete="off" />
			</td>
			<td>
				<input type="text" name="ssc_items[<?php echo (int) $i; ?>][label]" value="<?php echo esc_attr( $lab ); ?>" class="regular-text" autocomplete="off" />
			</td>
			<td>
				<label>
					<input type="checkbox" name="ssc_items[<?php echo (int) $i; ?>][needs_gender]" value="1" <?php checked( $g ); ?> />
				</label>
			</td>
			<td>
				<label>
					<input type="checkbox" name="ssc_items[<?php echo (int) $i; ?>][needs_size]" value="1" <?php checked( $s ); ?> />
				</label>
			</td>
			<td>
				<label>
					<input type="checkbox" name="ssc_items[<?php echo (int) $i; ?>][uses_farv]" value="1" <?php checked( $f ); ?> />
				</label>
			</td>
		</tr>
		<?php
	}

	public function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Ikki loyvi.', 'steinum-sport-clothes' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'ssc_save_order_items' );

		$posted = isset( $_POST['ssc_items'] ) ? wp_unslash( $_POST['ssc_items'] ) : array();
		$rows   = SSC_Order_Items::sanitize_posted_rows( $posted );
		$ok     = SSC_Order_Items::save_rows( $rows );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'            => self::PAGE,
					'ssc_items_saved' => $ok ? '1' : '0',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function handle_reset(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Ikki loyvi.', 'steinum-sport-clothes' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'ssc_reset_order_items' );

		$deleted = delete_option( SSC_Order_Items::OPTION );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'            => self::PAGE,
					'ssc_items_saved' => $deleted !== false ? '1' : '0',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
