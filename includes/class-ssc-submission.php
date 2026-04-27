<?php
/**
 * Submission orchestrator: validate → email → persist.
 *
 * @package Steinum_Sport_Clothes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Glues sanitizer, mailer, store and PDF generator together.
 */
class SSC_Submission {

	/**
	 * Process an already-sanitized submission.
	 *
	 * @param array<string, string|int> $data Sanitized form data.
	 * @return bool True if at least the admin email was queued / dry-run accepted.
	 */
	public function handle( array $data ): bool {
		$labels    = SSC_Sanitizer::labels();
		$site_name = (string) get_option( 'blogname', '' );
		$site_url  = (string) get_option( 'siteurl', '' );

		$settings = SSC_Settings::current();

		$admin_to       = $settings['admin_to'];
		$admin_subject  = self::interpolate( $settings['admin_subject'], $site_name, (string) $data['club_name'] );
		$admin_body     = SSC_Email_Builder::admin_body( $data, $labels, $site_name );

		$customer_to     = (string) $data['billing_email'];
		$customer_subj   = self::interpolate( $settings['receipt_subject'], $site_name, (string) $data['club_name'] );
		$customer_body   = SSC_Email_Builder::receipt_body( $data, $labels, $settings['receipt_intro'] );

		$pdf_path = '';
		try {
			$pdf       = ( new SSC_PDF() )->render(
				$data,
				$labels,
				$settings['pdf_title'],
				array(
					'Dato' => gmdate( 'Y-m-d H:i' ),
				)
			);
			$pdf_path = self::store_pdf( $pdf, (string) $data['club_name'], (string) $data['boat_name'] );
		} catch ( \Throwable $e ) {
			$pdf_path = '';
		}

		$xlsx_path = (string) SSC_Order_Excel::write_file( $data );

		$from_header = '';
		if ( '' !== $settings['from_email'] ) {
			$from_header = $settings['from_name']
				? sprintf( 'From: "%s" <%s>', $settings['from_name'], $settings['from_email'] )
				: 'From: ' . $settings['from_email'];
		}
		$headers = array_filter( array( 'Content-Type: text/plain; charset=UTF-8', $from_header ) );

		$id = SSC_Store::insert( $data, $admin_body, $pdf_path );

		$admin_files = array();
		if ( '' !== $pdf_path && is_file( $pdf_path ) ) {
			$admin_files[] = $pdf_path;
		}
		if ( '' !== $xlsx_path && is_file( $xlsx_path ) ) {
			$admin_files[] = $xlsx_path;
		}

		$admin_ok = SSC_Mail::send(
			$admin_to,
			$admin_subject,
			$admin_body,
			$headers,
			$admin_files,
			SSC_Mail::TYPE_ADMIN
		);

		$admin_to_norm = strtolower( trim( (string) $admin_to ) );
		$customer_norm = strtolower( trim( $customer_to ) );
		// Same inbox already gets the admin mail (full detail + attachments); skip duplicate kvittan.
		$receipt_redundant = '' !== $admin_to_norm && $admin_to_norm === $customer_norm;

		if ( $settings['receipt_enabled'] && '' !== $customer_to && ! $receipt_redundant ) {
			SSC_Mail::send(
				$customer_to,
				$customer_subj,
				$customer_body,
				$headers,
				$pdf_path ? array( $pdf_path ) : array(),
				SSC_Mail::TYPE_RECEIPT
			);
		}

		do_action( 'ssc_after_submission', $id, $data, $pdf_path, $xlsx_path );

		return $admin_ok;
	}

	private static function interpolate( string $template, string $site, string $club ): string {
		return strtr(
			$template,
			array(
				'{site}' => $site,
				'{club}' => $club,
			)
		);
	}

	private static function store_pdf( string $bytes, string $club, string $boat ): string {
		$dir = '';
		if ( function_exists( 'wp_upload_dir' ) ) {
			$uploads = wp_upload_dir();
			$base    = isset( $uploads['basedir'] ) ? (string) $uploads['basedir'] : sys_get_temp_dir();
			$dir     = rtrim( $base, '/\\' ) . '/steinum-sport-clothes';
		} else {
			$dir = sys_get_temp_dir() . '/steinum-sport-clothes';
		}
		if ( ! is_dir( $dir ) ) {
			@mkdir( $dir, 0775, true );
		}
		$slug = strtolower( preg_replace( '/[^a-z0-9]+/i', '-', $club . '-' . $boat ) ?: 'order' );
		$slug = trim( (string) $slug, '-' ) ?: 'order';
		$file = $dir . '/' . gmdate( 'Ymd-His' ) . '-' . $slug . '.pdf';
		$ok   = (bool) @file_put_contents( $file, $bytes );
		return $ok ? $file : '';
	}
}
