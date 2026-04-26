<?php
/**
 * Frontend form: shortcode renderer + POST handler.
 *
 * @package Steinum_Sport_Clothes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the shortcode and handles POST submissions.
 */
class SSC_Form {

	public const ACTION    = 'ssc_submit';
	public const NONCE     = 'ssc_form';
	public const SHORTCODE = 'steinum_sport_clothes_form';
	public const SHORTCODE_ALIAS = 'ssc_form';

	public function register(): void {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
		add_shortcode( self::SHORTCODE_ALIAS, array( $this, 'render' ) );
		add_action( 'init', array( $this, 'maybe_handle_submit' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	public function register_assets(): void {
		wp_register_style(
			'ssc-frontend',
			SSC_URL . 'assets/css/frontend.css',
			array(),
			SSC_VERSION
		);
		/* Kept for compatibility; the shortcode inlines the same file so the UI works without wp_footer. */
		wp_register_script(
			'ssc-lines',
			SSC_URL . 'assets/js/lines.js',
			array(),
			SSC_VERSION,
			true
		);
	}

	/**
	 * Sample submission for admin "Send royndar-tilkunn".
	 *
	 * @return array<string, mixed>
	 */
	public static function sample_data( string $billing_email = '' ): array {
		if ( '' === $billing_email || ! filter_var( $billing_email, FILTER_VALIDATE_EMAIL ) ) {
			$billing_email = (string) get_option( 'admin_email', 'test@example.com' );
		}
		$contact = 'hans@example.com';
		return array(
			'club_name'     => 'Kappróðrarfelag Havnar',
			'boat_name'     => 'Selin (royndarbátur)',
			'order_lines'   => array(
				array(
					'item'   => SSC_Sanitizer::ITEM_TRIKOT,
					'gender' => SSC_Sanitizer::GENDER_WOMEN,
					'size'   => 'M',
					'qty'    => 2,
					'name'   => 'Maria',
				),
				array(
					'item'   => SSC_Sanitizer::ITEM_TSHIRT,
					'gender' => '',
					'size'   => 'L',
					'qty'    => 1,
					'name'   => 'Jón',
				),
				array(
					'item'         => SSC_Sanitizer::ITEM_SPEEDCOACH,
					'gender'       => '',
					'size'         => '',
					'bumper_color' => 'gult',
					'qty'          => 1,
					'name'         => '',
				),
			),
			'contact_name'  => 'Hans Hansen (royndarprógv)',
			'contact_email' => $contact,
			'phone'         => '+298 12 34 56',
			'billing_email' => $billing_email,
		);
	}

	private function redirect_target( bool $ok ): string {
		$ref = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( (string) $_SERVER['HTTP_REFERER'] ) ) : '';
		if ( '' === $ref ) {
			$ref = home_url( '/' );
		}
		return add_query_arg(
			array( 'ssc_status' => $ok ? 'ok' : 'fail' ),
			$ref
		);
	}

	public function maybe_handle_submit(): void {
		if ( empty( $_POST['action'] ) || self::ACTION !== $_POST['action'] ) {
			return;
		}
		check_admin_referer( self::NONCE );

		$raw    = wp_unslash( $_POST );
		$data   = SSC_Sanitizer::sanitize( is_array( $raw ) ? $raw : array() );
		$errors = SSC_Sanitizer::validate( $data );

		if ( ! empty( $_POST['ssc_hp'] ) ) {
			$errors[] = '__honeypot';
		}

		if ( $errors ) {
			set_transient( 'ssc_errors_' . self::client_key(), $errors, 60 );
			set_transient( 'ssc_input_' . self::client_key(), $data, 60 );
			wp_safe_redirect( $this->redirect_target( false ) );
			exit;
		}

		$ok = ( new SSC_Submission() )->handle( $data );

		delete_transient( 'ssc_errors_' . self::client_key() );
		delete_transient( 'ssc_input_' . self::client_key() );
		wp_safe_redirect( $this->redirect_target( $ok ) );
		exit;
	}

	/**
	 * @param array<string, string|int|array> $input
	 */
	private function get_lines_for_render( array $input ): array {
		$def = array(
			'item'         => '',
			'size'         => '',
			'gender'       => SSC_Sanitizer::GENDER_WOMEN,
			'bumper_color' => '',
			'qty'          => 0,
			'name'         => '',
		);
		$lines = $input['order_lines'] ?? array( $def );
		if ( ! is_array( $lines ) || ! $lines ) {
			$lines = array( $def );
		}
		$out   = array();
		$items = SSC_Sanitizer::item_types();
		foreach ( $lines as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$item = (string) ( $row['item'] ?? '' );
			if ( SSC_Sanitizer::ITEM_CLOTHES === $item ) {
				$item = SSC_Sanitizer::ITEM_TSHIRT;
			}
			if ( $item !== '' && ! array_key_exists( $item, $items ) ) {
				$item = '';
			}
			$g = (string) ( $row['gender'] ?? $def['gender'] );
			if ( ! in_array( $g, array( SSC_Sanitizer::GENDER_WOMEN, SSC_Sanitizer::GENDER_MEN ), true ) ) {
				$g = SSC_Sanitizer::GENDER_WOMEN;
			}
			$out[] = array(
				'item'         => $item,
				'size'         => (string) ( $row['size'] ?? '' ),
				'gender'       => $g,
				'bumper_color' => (string) ( $row['bumper_color'] ?? '' ),
				'qty'          => max( 0, (int) ( $row['qty'] ?? 0 ) ),
				'name'         => (string) ( $row['name'] ?? '' ),
			);
		}
		return $out ? $out : array( $def );
	}

	/**
	 * @param int                             $index
	 * @param array<string, int|string>       $line
	 * @param array<int, string>              $errors
	 * @param int                             $line_count  Total order lines in this form (for remove-button visibility).
	 */
	private function render_line_row( int $index, array $line, array $errors, int $line_count ): void {
		$item  = (string) ( $line['item'] ?? '' );
		$size  = (string) ( $line['size'] ?? '' );
		$qty   = max( 0, (int) ( $line['qty'] ?? 0 ) );
		$name  = (string) ( $line['name'] ?? '' );
		$gend  = (string) ( $line['gender'] ?? SSC_Sanitizer::GENDER_WOMEN );
		$bum   = (string) ( $line['bumper_color'] ?? '' );
		$items = SSC_Sanitizer::item_types();
		$glabels = SSC_Sanitizer::gender_labels();
		$sizes   = SSC_Sanitizer::size_options();
		$farv    = SSC_Sanitizer::farv_options();

		$has_item    = ( $item !== '' && array_key_exists( $item, $items ) );
		$show_remove = $line_count > 1;
		$show_g      = $has_item && SSC_Sanitizer::ITEM_TRIKOT === $item;
		$show_sz  = $has_item && SSC_Sanitizer::item_needs_size( $item );
		$show_sp  = $has_item && SSC_Sanitizer::item_uses_farv( $item );
		$show_nm  = $has_item && ! $show_sp;
		$show_qty = $has_item;
		$qty_val  = ( $has_item && $qty > 0 ) ? (string) (int) $qty : '';
		$qty_lab  = __( 'Nøgd', 'steinum-sport-clothes' );
		?>
		<div class="ssc-line-row" data-idx="<?php echo (int) $index; ?>">
			<button
				type="button"
				class="ssc-line-close ssc-remove-line<?php echo $show_remove ? '' : ' is-ssc-hidden'; ?>"
				data-ssc-line-part="action"
				aria-label="<?php esc_attr_e( 'Strika línju', 'steinum-sport-clothes' ); ?>"
				<?php echo $show_remove ? '' : ' aria-hidden="true"'; ?>
			>×</button>
			<div class="ssc-line-grid" data-ssc-has-item="<?php echo $has_item ? '1' : '0'; ?>">
				<div class="ssc-line-field">
					<label for="ssc-item-<?php echo (int) $index; ?>"><?php esc_html_e( 'Slag', 'steinum-sport-clothes' ); ?></label>
					<select id="ssc-item-<?php echo (int) $index; ?>" class="ssc-line-item" name="order_lines[<?php echo (int) $index; ?>][item]" required onchange="if(typeof window.sscLineItemChanged===&quot;function&quot;){window.sscLineItemChanged(this);}">
						<option value="" <?php selected( $item, '' ); ?>><?php esc_html_e( '— vel slag', 'steinum-sport-clothes' ); ?></option>
						<?php foreach ( $items as $k => $lab ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $item, $k ); ?>><?php echo esc_html( $lab ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="ssc-line-field ssc-sub ssc-sub-trikot-gender<?php echo $show_g ? '' : ' is-ssc-hidden'; ?>" data-ssc-line-part="trikot-gender"<?php echo $show_g ? '' : ' aria-hidden="true"'; ?>>
					<label for="ssc-gender-<?php echo (int) $index; ?>"><?php esc_html_e( 'Kyn', 'steinum-sport-clothes' ); ?></label>
					<select id="ssc-gender-<?php echo (int) $index; ?>" class="ssc-line-gender" name="order_lines[<?php echo (int) $index; ?>][gender]"<?php echo $show_g ? ' required' : ' disabled'; ?>>
						<?php foreach ( $glabels as $gk => $glab ) : ?>
							<option value="<?php echo esc_attr( $gk ); ?>" <?php selected( $gend, $gk ); ?>><?php echo esc_html( $glab ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="ssc-line-field ssc-sub ssc-sub-size<?php echo $show_sz ? '' : ' is-ssc-hidden'; ?>" data-ssc-line-part="size"<?php echo $show_sz ? '' : ' aria-hidden="true"'; ?>>
					<label for="ssc-size-<?php echo (int) $index; ?>"><?php esc_html_e( 'Stødd', 'steinum-sport-clothes' ); ?></label>
					<select id="ssc-size-<?php echo (int) $index; ?>" class="ssc-line-size" name="order_lines[<?php echo (int) $index; ?>][size]"<?php echo $show_sz ? ' required' : ' disabled'; ?>>
						<option value=""><?php esc_html_e( '— vel —', 'steinum-sport-clothes' ); ?></option>
						<?php foreach ( $sizes as $s ) : ?>
							<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $size, $s ); ?>><?php echo esc_html( $s ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="ssc-line-field ssc-sub ssc-sub-speed<?php echo $show_sp ? '' : ' is-ssc-hidden'; ?>" data-ssc-line-part="bumper"<?php echo $show_sp ? '' : ' aria-hidden="true"'; ?>>
					<label for="ssc-bump-<?php echo (int) $index; ?>"><?php esc_html_e( 'Ynskta farvu', 'steinum-sport-clothes' ); ?></label>
					<select id="ssc-bump-<?php echo (int) $index; ?>" class="ssc-line-bump" name="order_lines[<?php echo (int) $index; ?>][bumper_color]"<?php echo $show_sp ? '' : ' disabled'; ?><?php echo $show_sp ? ' required' : ''; ?>>
						<option value=""><?php esc_html_e( '— vel —', 'steinum-sport-clothes' ); ?></option>
						<?php foreach ( $farv as $fk => $flab ) : ?>
							<option value="<?php echo esc_attr( $fk ); ?>" <?php selected( $bum, $fk ); ?>><?php echo esc_html( $flab ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="ssc-line-field ssc-sub ssc-sub-qty<?php echo $show_qty ? '' : ' is-ssc-hidden'; ?>" data-ssc-line-part="qty"<?php echo $show_qty ? '' : ' aria-hidden="true"'; ?>>
					<label for="ssc-qty-<?php echo (int) $index; ?>"><span class="ssc-qty-label"><?php echo esc_html( $qty_lab ); ?></span></label>
					<input id="ssc-qty-<?php echo (int) $index; ?>" class="ssc-line-qty" type="number" name="order_lines[<?php echo (int) $index; ?>][qty]" value="<?php echo esc_attr( $qty_val ); ?>" min="1" step="1" inputmode="numeric" <?php echo $has_item ? 'required' : 'disabled'; ?> />
				</div>
				<div class="ssc-line-field ssc-line-field--name ssc-sub ssc-sub-name<?php echo $show_nm ? '' : ' is-ssc-hidden'; ?>" data-ssc-line-part="name"<?php echo $show_nm ? '' : ' aria-hidden="true"'; ?>>
					<label for="ssc-name-<?php echo (int) $index; ?>"><?php esc_html_e( 'Navn (valfrítt)', 'steinum-sport-clothes' ); ?></label>
					<input id="ssc-name-<?php echo (int) $index; ?>" type="text" class="ssc-line-name" name="order_lines[<?php echo (int) $index; ?>][name]" value="<?php echo esc_attr( $name ); ?>" autocomplete="name" />
				</div>
			</div>
		</div>
		<?php
	}

	public function render(): string {
		wp_enqueue_style( 'ssc-frontend' );

		$labels  = SSC_Sanitizer::labels();
		$key     = self::client_key();
		$errors  = (array) get_transient( 'ssc_errors_' . $key );
		$input   = (array) get_transient( 'ssc_input_' . $key );
		$lines   = $this->get_lines_for_render( $input );

		ob_start();
		$status = isset( $_GET['ssc_status'] ) ? (string) $_GET['ssc_status'] : '';
		if ( 'ok' === $status ) {
			echo '<div class="ssc-notice ssc-notice--ok" role="status">'
				. esc_html__( 'Takk fyri! Innskráseting móttikin.', 'steinum-sport-clothes' )
				. '</div>';
		} elseif ( 'fail' === $status ) {
			echo '<div class="ssc-notice ssc-notice--err" role="alert">'
				. esc_html__( 'Onkur villa hendi. Vinarliga royn aftur.', 'steinum-sport-clothes' )
				. '</div>';
		}
		?>
		<form class="ssc-form" method="post">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
			<?php wp_nonce_field( self::NONCE ); ?>
			<p style="position:absolute;left:-9999px;" aria-hidden="true">
				<label>Lat tóman<input type="text" name="ssc_hp" tabindex="-1" autocomplete="off" /></label>
			</p>

			<?php
			$club_err = in_array( 'club_name', $errors, true );
			$boat_err = in_array( 'boat_name', $errors, true );
			$club_v   = isset( $input['club_name'] ) ? (string) $input['club_name'] : '';
			$boat_v   = isset( $input['boat_name'] ) ? (string) $input['boat_name'] : '';
			?>
			<div class="ssc-form__section">
				<h3 class="ssc-form__section-title"><?php esc_html_e( 'Felag & bátur', 'steinum-sport-clothes' ); ?></h3>
				<p class="ssc-field<?php echo $club_err ? ' ssc-field--error' : ''; ?>">
					<label for="ssc-club_name"><?php echo esc_html( $labels['club_name'] ); ?></label>
					<input type="text" id="ssc-club_name" name="club_name" value="<?php echo esc_attr( $club_v ); ?>" required autocomplete="organization" />
				</p>
				<p class="ssc-field<?php echo $boat_err ? ' ssc-field--error' : ''; ?>">
					<label for="ssc-boat_name"><?php echo esc_html( $labels['boat_name'] ); ?></label>
					<input type="text" id="ssc-boat_name" name="boat_name" value="<?php echo esc_attr( $boat_v ); ?>" autocomplete="off" />
				</p>
			</div>

			<fieldset class="ssc-lines<?php echo in_array( 'order_lines', $errors, true ) ? ' ssc-field--error' : ''; ?>">
				<legend><?php echo esc_html( $labels['order_lines'] ); ?></legend>
				<p class="description"><?php esc_html_e( 'Fyll inn øll felt, sum tónast, eftir at tú hevur valt slag (kyn, stødd, nøgd, ynskt farv …). «Navn» undir bíleggingu er valfrítt.', 'steinum-sport-clothes' ); ?></p>
				<div id="ssc-line-rows" class="ssc-line-rows">
					<?php
					$i          = 0;
					$line_count = count( $lines );
					foreach ( $lines as $line ) {
						$this->render_line_row( $i, is_array( $line ) ? $line : array(), $errors, $line_count );
						++$i;
					}
					?>
				</div>
				<p>
					<button type="button" class="ssc-add-line" id="ssc-add-line"><?php esc_html_e( 'Legg at línju', 'steinum-sport-clothes' ); ?></button>
				</p>
			</fieldset>

			<div class="ssc-form__section ssc-form__section--contact">
			<h3 class="ssc-form__section-title"><?php esc_html_e( 'Kontakt & rokning', 'steinum-sport-clothes' ); ?></h3>
			<?php
			$rest = array( 'contact_name', 'contact_email', 'phone', 'billing_email' );
			foreach ( $rest as $k ) :
				$label   = $labels[ $k ];
				$value   = isset( $input[ $k ] ) ? (string) $input[ $k ] : '';
				$has_err = in_array( $k, $errors, true );
				$type    = ( 'billing_email' === $k || 'contact_email' === $k ) ? 'email' : ( 'phone' === $k ? 'tel' : 'text' );
				$id      = 'ssc-' . $k;
				$ac      = 'off';
				if ( 'contact_name' === $k ) {
					$ac = 'name';
				} elseif ( 'contact_email' === $k || 'billing_email' === $k ) {
					$ac = 'email';
				} elseif ( 'phone' === $k ) {
					$ac = 'tel';
				}
				?>
				<?php
				$attr_extra = '';
				if ( 'phone' === $k ) {
					$attr_extra = ' pattern="^(?=.*[0-9])[+0-9 ]{1,64}$" maxlength="64"'
						. ' title="' . esc_attr( __( 'Bert: +, tóm rúm og tøl', 'steinum-sport-clothes' ) ) . '"'
						. ' inputmode="tel"';
				}
				?>
				<p class="ssc-field<?php echo $has_err ? ' ssc-field--error' : ''; ?>">
					<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
					<input type="<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $k ); ?>"
						value="<?php echo esc_attr( $value ); ?>"
						autocomplete="<?php echo esc_attr( $ac ); ?>"
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attributes built with esc_attr in $attr_extra.
						echo $attr_extra;
						?>
						required
					/>
				</p>
			<?php endforeach; ?>
			</div>

			<p class="ssc-actions">
				<button type="submit" class="ssc-submit"><?php esc_html_e( 'Send', 'steinum-sport-clothes' ); ?></button>
			</p>
		</form>
		<?php
		$this->print_order_lines_script();
		?>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Inlines the line-row script right after the form so it runs in WordPress Playground, pages without
	 * wp_footer, or when the external asset URL is blocked. Must match assets/js/lines.js.
	 */
	private function print_order_lines_script(): void {
		$l10n = array(
			'qtyDefault' => __( 'Nøgd', 'steinum-sport-clothes' ),
			'qtySpeed'   => __( 'Nøgd', 'steinum-sport-clothes' ),
		);
		$path = SSC_DIR . 'assets/js/lines.js';
		if ( ! is_readable( $path ) ) {
			return;
		}
		$js = file_get_contents( $path );
		if ( ! is_string( $js ) || '' === $js ) {
			return;
		}
		$l10n_json = function_exists( 'wp_json_encode' ) ? (string) wp_json_encode( $l10n ) : (string) json_encode( $l10n );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON from wp_json_encode( array ).
		echo '<script>window.sscLinesL10n=' . $l10n_json . ";</script>\n";
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static asset from disk, not user input.
		echo '<script id="ssc-order-lines-js">' . $js . "</script>\n";
	}

	private static function client_key(): string {
		$ip = $_SERVER['REMOTE_ADDR'] ?? '';
		$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
		return md5( $ip . '|' . $ua );
	}
}
