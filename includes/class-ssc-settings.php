<?php
/**
 * Admin Settings page (Steinum Sport → Stillingar).
 *
 * @package Steinum_Sport_Clothes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings page + option helpers.
 */
class SSC_Settings {

	public const OPTION = 'ssc_settings';
	public const GROUP  = 'ssc_settings_group';
	public const PAGE   = 'ssc-settings';

	/** @return array<string, string|bool> */
	public static function defaults(): array {
		return array(
			'admin_to'        => (string) get_option( 'admin_email', '' ),
			'admin_subject'   => 'Nýggj tilkunn frá {site} – {club}',
			'receipt_enabled'         => true,
			'contact_receipt_enabled' => false,
			'receipt_subject'         => 'Tín tilkunn er móttikin – {site}',
			'receipt_intro'   => 'Halló og takk fyri tína tilkunn. Vit hava móttikin uppskotið og fylgja tær yvir lutirnar.',
			'from_name'       => '',
			'from_email'      => '',
			'pdf_title'       => 'Bíllegingarváttan',
		);
	}

	/** @return array<string, string|bool> */
	public static function current(): array {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( self::defaults(), $saved );
	}

	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action(
			'admin_post_ssc_send_test',
			array( $this, 'handle_test_submission' )
		);
	}

	public function register_settings(): void {
		register_setting(
			self::GROUP,
			self::OPTION,
			array( 'sanitize_callback' => array( $this, 'sanitize' ) )
		);
	}

	/**
	 * @param mixed $input
	 * @return array<string, string|bool>
	 */
	public function sanitize( $input ): array {
		$d   = self::defaults();
		$in  = is_array( $input ) ? $input : array();
		$out = array();

		$out['admin_to']        = sanitize_email( (string) ( $in['admin_to'] ?? $d['admin_to'] ) ) ?: (string) $d['admin_to'];
		$out['admin_subject']   = sanitize_text_field( (string) ( $in['admin_subject'] ?? $d['admin_subject'] ) );
		$out['receipt_enabled']         = ! empty( $in['receipt_enabled'] );
		$out['contact_receipt_enabled'] = ! empty( $in['contact_receipt_enabled'] );
		$out['receipt_subject']         = sanitize_text_field( (string) ( $in['receipt_subject'] ?? $d['receipt_subject'] ) );
		$out['receipt_intro']   = sanitize_textarea_field( (string) ( $in['receipt_intro'] ?? $d['receipt_intro'] ) );
		$out['from_name']       = sanitize_text_field( (string) ( $in['from_name'] ?? '' ) );
		$out['from_email']      = sanitize_email( (string) ( $in['from_email'] ?? '' ) );
		$out['pdf_title']       = sanitize_text_field( (string) ( $in['pdf_title'] ?? $d['pdf_title'] ) );

		return $out;
	}

	public function render_page(): void {
		$opts = self::current();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Steinum Sport – Stillingar', 'steinum-sport-clothes' ); ?></h1>

			<?php if ( isset( $_GET['ssc_tested'] ) ) : ?>
				<div class="notice notice-<?php echo '1' === $_GET['ssc_tested'] ? 'success' : 'error'; ?> is-dismissible">
					<p><?php echo '1' === $_GET['ssc_tested']
						? esc_html__( 'Royndar-tilkunn varð send.', 'steinum-sport-clothes' )
						: esc_html__( 'Royndar-tilkunn miseydnaðist.', 'steinum-sport-clothes' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="ssc_admin_to"><?php esc_html_e( 'Admin teldupostur', 'steinum-sport-clothes' ); ?></label></th>
						<td><input type="email" id="ssc_admin_to" name="<?php echo esc_attr( self::OPTION ); ?>[admin_to]" value="<?php echo esc_attr( $opts['admin_to'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th><label for="ssc_admin_subject"><?php esc_html_e( 'Admin yvirskrift', 'steinum-sport-clothes' ); ?></label></th>
						<td>
							<input type="text" id="ssc_admin_subject" name="<?php echo esc_attr( self::OPTION ); ?>[admin_subject]" value="<?php echo esc_attr( $opts['admin_subject'] ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Til ráðis: {site}, {club}.', 'steinum-sport-clothes' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Send kvittan til kundan', 'steinum-sport-clothes' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[receipt_enabled]" value="1" <?php checked( (bool) $opts['receipt_enabled'] ); ?> />
								<?php esc_html_e( 'Ja, send eitt PDF-kvittan til faktureringstelduposten', 'steinum-sport-clothes' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Send kvittan til kontaktpersón', 'steinum-sport-clothes' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[contact_receipt_enabled]" value="1" <?php checked( (bool) $opts['contact_receipt_enabled'] ); ?> />
								<?php esc_html_e( 'Ja, send somu PDF-kvittan til teldupostinum hjá kontaktpersóninum', 'steinum-sport-clothes' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Ikki sendt um telduposturin er sama sum admin, ella longu fekk kvittan sum faktureringsteldupostur.', 'steinum-sport-clothes' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="ssc_receipt_subject"><?php esc_html_e( 'Kvittan yvirskrift', 'steinum-sport-clothes' ); ?></label></th>
						<td><input type="text" id="ssc_receipt_subject" name="<?php echo esc_attr( self::OPTION ); ?>[receipt_subject]" value="<?php echo esc_attr( $opts['receipt_subject'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th><label for="ssc_receipt_intro"><?php esc_html_e( 'Kvittan innleiðing', 'steinum-sport-clothes' ); ?></label></th>
						<td><textarea id="ssc_receipt_intro" name="<?php echo esc_attr( self::OPTION ); ?>[receipt_intro]" rows="4" class="large-text"><?php echo esc_textarea( $opts['receipt_intro'] ); ?></textarea></td>
					</tr>
					<tr>
						<th><label for="ssc_from_name"><?php esc_html_e( 'Avsendari (navn)', 'steinum-sport-clothes' ); ?></label></th>
						<td><input type="text" id="ssc_from_name" name="<?php echo esc_attr( self::OPTION ); ?>[from_name]" value="<?php echo esc_attr( $opts['from_name'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th><label for="ssc_from_email"><?php esc_html_e( 'Avsendari (teldupostur)', 'steinum-sport-clothes' ); ?></label></th>
						<td><input type="email" id="ssc_from_email" name="<?php echo esc_attr( self::OPTION ); ?>[from_email]" value="<?php echo esc_attr( $opts['from_email'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th><label for="ssc_pdf_title"><?php esc_html_e( 'PDF heiti', 'steinum-sport-clothes' ); ?></label></th>
						<td><input type="text" id="ssc_pdf_title" name="<?php echo esc_attr( self::OPTION ); ?>[pdf_title]" value="<?php echo esc_attr( $opts['pdf_title'] ); ?>" class="regular-text" /></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<hr />
			<h2><?php esc_html_e( 'Royndar-tilkunn', 'steinum-sport-clothes' ); ?></h2>
			<p><?php esc_html_e( 'Send eina tilkunn við royndarinnihaldi til at vita um teldupostur, PDF og DB-goymsla virka.', 'steinum-sport-clothes' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'ssc_send_test' ); ?>
				<input type="hidden" name="action" value="ssc_send_test" />
				<input type="email" name="test_email" placeholder="<?php esc_attr_e( 'Faktureringsteldupostur (valfrítt)', 'steinum-sport-clothes' ); ?>" class="regular-text" />
				<?php submit_button( __( 'Send royndar-tilkunn', 'steinum-sport-clothes' ), 'secondary', 'submit', false ); ?>
			</form>

			<hr />
			<h2><?php esc_html_e( 'Próvingarhamur', 'steinum-sport-clothes' ); ?></h2>
			<table class="widefat striped" style="max-width:720px">
				<tbody>
					<tr><th><?php esc_html_e( 'Próvingarhamur', 'steinum-sport-clothes' ); ?></th>
						<td><?php echo SSC_Mail::is_test_mode() ? '<strong>Á</strong>' : 'Av'; ?></td></tr>
					<tr><th><?php esc_html_e( 'Dry run', 'steinum-sport-clothes' ); ?></th>
						<td><?php echo SSC_Mail::is_dry_run() ? '<strong>Á</strong>' : 'Av'; ?></td></tr>
					<tr><th><?php esc_html_e( 'Royndar-móttakari', 'steinum-sport-clothes' ); ?></th>
						<td><?php echo esc_html( SSC_Mail::test_to() ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Log-fíla', 'steinum-sport-clothes' ); ?></th>
						<td><code><?php echo esc_html( SSC_Logger::path() ); ?></code></td></tr>
				</tbody>
			</table>

			<?php if ( SSC_Mail::is_test_mode() ) : ?>
				<h3><?php esc_html_e( 'Seinastu log-linjur', 'steinum-sport-clothes' ); ?></h3>
				<pre style="background:#111;color:#0f0;padding:12px;max-height:300px;overflow:auto;"><?php echo esc_html( SSC_Logger::tail( 6000 ) ); ?></pre>
			<?php endif; ?>
		</div>
		<?php
	}

	public function handle_test_submission(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Ikki loyvi.', 'steinum-sport-clothes' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'ssc_send_test' );

		$email = isset( $_POST['test_email'] )
			? sanitize_email( wp_unslash( (string) $_POST['test_email'] ) )
			: '';

		$data = SSC_Form::sample_data( $email );
		$ok   = ( new SSC_Submission() )->handle( $data );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => self::PAGE,
					'ssc_tested' => $ok ? '1' : '0',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
