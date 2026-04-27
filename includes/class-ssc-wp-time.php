<?php
/**
 * Format “now” in the WordPress site timezone (not UTC).
 *
 * @package Steinum_Sport_Clothes
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'SSC_TESTING' ) ) {
	exit;
}

/**
 * Wraps wp_date / date_i18n so PDF filenames and “Dato” match local time.
 */
class SSC_WP_Time {

	/**
	 * @param string $php_format Same as PHP date() / gmdate() format string.
	 */
	public static function format( string $php_format ): string {
		if ( function_exists( 'wp_date' ) ) {
			return wp_date( $php_format );
		}
		if ( function_exists( 'date_i18n' ) && function_exists( 'current_time' ) ) {
			return (string) date_i18n( $php_format, (int) current_time( 'timestamp' ) );
		}
		return gmdate( $php_format );
	}
}
