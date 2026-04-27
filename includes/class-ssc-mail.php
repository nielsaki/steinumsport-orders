<?php
/**
 * wp_mail() wrapper with test-mode handling.
 *
 * Test-mode constants (set in wp-config.php for staging / local):
 *   SSC_EMAIL_TEST_MODE  bool   – redirect mail + add banner
 *   SSC_EMAIL_TEST_TO    string – override recipient (default: admin email)
 *   SSC_EMAIL_DRY_RUN    bool   – skip wp_mail entirely (only log)
 *   SSC_EMAIL_LOG_FILE   string – path for log file
 *
 * @package Steinum_Sport_Clothes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends mail through wp_mail() while honoring test-mode constants.
 */
class SSC_Mail {

	public const TYPE_ADMIN            = 'Admin tilkunn';
	public const TYPE_RECEIPT          = 'Kvittan';
	public const TYPE_CONTACT_RECEIPT  = 'Kvittan (kontakt)';

	public static function is_test_mode(): bool {
		return defined( 'SSC_EMAIL_TEST_MODE' ) && (bool) SSC_EMAIL_TEST_MODE;
	}

	public static function is_dry_run(): bool {
		return defined( 'SSC_EMAIL_DRY_RUN' ) && (bool) SSC_EMAIL_DRY_RUN;
	}

	public static function test_to(): string {
		if ( defined( 'SSC_EMAIL_TEST_TO' ) && '' !== (string) SSC_EMAIL_TEST_TO ) {
			return (string) SSC_EMAIL_TEST_TO;
		}
		return (string) get_option( 'admin_email', '' );
	}

	/**
	 * @param string|array<int, string> $to
	 * @param array<int, string>        $headers
	 * @param array<int, string>        $attachments
	 */
	public static function send( $to, string $subject, string $body, array $headers = array(), array $attachments = array(), string $type = self::TYPE_ADMIN ): bool {
		$original_to = is_array( $to ) ? implode( ', ', $to ) : (string) $to;

		if ( self::is_test_mode() ) {
			$banner  = '*** PRÓVINGARHAMUR — ' . $type . ' ***' . "\n";
			$banner .= 'Upphavligur móttakari: ' . $original_to . "\n";
			$banner .= 'Strikað er sent til:    ' . self::test_to() . "\n";
			$banner .= str_repeat( '-', 60 ) . "\n\n";
			$body    = $banner . $body;
			$to      = self::test_to();
			$subject = '[PRÓV] ' . $subject;
		}

		$log_lines   = array();
		$log_lines[] = '----------------------------------------';
		$log_lines[] = 'TYPE:    ' . $type;
		$log_lines[] = 'TO:      ' . ( is_array( $to ) ? implode( ', ', $to ) : (string) $to );
		if ( $original_to !== ( is_array( $to ) ? implode( ', ', $to ) : (string) $to ) ) {
			$log_lines[] = 'ORIG-TO: ' . $original_to;
		}
		$log_lines[] = 'SUBJECT: ' . $subject;
		if ( $headers ) {
			$log_lines[] = 'HEADERS: ' . implode( ' | ', $headers );
		}
		if ( $attachments ) {
			$attached = array();
			foreach ( $attachments as $att ) {
				$attached[] = is_file( $att )
					? basename( $att ) . ' (' . filesize( $att ) . ' bytes)'
					: (string) $att;
			}
			$log_lines[] = 'ATTACH:  ' . implode( ', ', $attached );
		}
		$log_lines[] = '';
		$log_lines[] = $body;

		SSC_Logger::log( implode( "\n", $log_lines ) );

		if ( self::is_dry_run() ) {
			return true;
		}

		if ( function_exists( 'wp_mail' ) ) {
			return (bool) wp_mail( $to, $subject, $body, $headers, $attachments );
		}
		return false;
	}
}
