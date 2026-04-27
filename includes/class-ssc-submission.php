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

		$settings = SSC_Settings::current();

		$admin_to       = $settings['admin_to'];
		$boat_name      = (string) ( $data['boat_name'] ?? '' );
		$admin_subject  = self::interpolate( $settings['admin_subject'], $site_name, (string) $data['club_name'], $boat_name );
		$admin_body     = SSC_Email_Builder::admin_body( $data, $labels, $site_name );

		$customer_subj   = self::interpolate( $settings['receipt_subject'], $site_name, (string) $data['club_name'], $boat_name );
		$customer_body   = SSC_Email_Builder::receipt_body( $data, $labels, $settings['receipt_intro'] );

		$pdf_path = '';
		try {
			$pdf       = ( new SSC_PDF() )->render(
				$data,
				$labels,
				$settings['pdf_title'],
				array(
					'Dato' => SSC_WP_Time::format( 'Y-m-d H:i' ),
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

		// Kvittan: always to contact email (not billing); skip if same inbox as admin notification.
		$contact_norm  = strtolower( trim( (string) ( $data['contact_email'] ?? '' ) ) );
		$contact_email = (string) ( $data['contact_email'] ?? '' );
		$contact_ok    = '' !== $contact_norm && false !== filter_var( $contact_email, FILTER_VALIDATE_EMAIL );
		$contact_dup_admin = '' !== $admin_to_norm && $admin_to_norm === $contact_norm;
		if ( $contact_ok && ! $contact_dup_admin ) {
			SSC_Mail::send(
				$contact_email,
				$customer_subj,
				$customer_body,
				$headers,
				$pdf_path ? array( $pdf_path ) : array(),
				SSC_Mail::TYPE_CONTACT_RECEIPT
			);
		}

		do_action( 'ssc_after_submission', $id, $data, $pdf_path, $xlsx_path );

		return $admin_ok;
	}

	private static function interpolate( string $template, string $site, string $club, string $boat = '' ): string {
		return strtr(
			$template,
			array(
				'{site}' => $site,
				'{club}' => $club,
				'{boat}' => $boat,
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
		$file = $dir . '/' . SSC_WP_Time::format( 'Ymd-His' ) . '-' . $slug . '.pdf';
		$ok   = (bool) @file_put_contents( $file, $bytes );
		return $ok ? $file : '';
	}
}
