<?php
/**
 * Plain-text log file used in test mode.
 *
 * @package Steinum_Sport_Clothes
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'SSC_TESTING' ) ) {
	exit;
}

/**
 * Tiny file logger.
 */
class SSC_Logger {

	public static function path(): string {
		if ( defined( 'SSC_EMAIL_LOG_FILE' ) && '' !== (string) SSC_EMAIL_LOG_FILE ) {
			return (string) SSC_EMAIL_LOG_FILE;
		}
		if ( function_exists( 'wp_upload_dir' ) ) {
			$uploads = wp_upload_dir();
			$base    = isset( $uploads['basedir'] ) ? (string) $uploads['basedir'] : sys_get_temp_dir();
			$dir     = rtrim( $base, '/\\' ) . '/steinum-sport-clothes';
			if ( ! is_dir( $dir ) ) {
				@mkdir( $dir, 0775, true );
			}
			return $dir . '/email-test.log';
		}
		return sys_get_temp_dir() . '/ssc-email-test.log';
	}

	public static function log( string $message ): void {
		$path = self::path();
		$dir  = dirname( $path );
		if ( ! is_dir( $dir ) ) {
			@mkdir( $dir, 0775, true );
		}
		$prefix = '[' . gmdate( 'Y-m-d H:i:s' ) . ' UTC] ';
		@file_put_contents( $path, $prefix . $message . "\n", FILE_APPEND | LOCK_EX );
	}

	public static function tail( int $bytes = 4096 ): string {
		$path = self::path();
		if ( ! is_file( $path ) ) {
			return '';
		}
		$size = (int) filesize( $path );
		$fp   = @fopen( $path, 'rb' );
		if ( ! $fp ) {
			return '';
		}
		if ( $size > $bytes ) {
			fseek( $fp, -$bytes, SEEK_END );
			fgets( $fp );
		}
		$out = stream_get_contents( $fp );
		fclose( $fp );
		return false === $out ? '' : (string) $out;
	}

	public static function clear(): void {
		$path = self::path();
		if ( is_file( $path ) ) {
			@file_put_contents( $path, '' );
		}
	}
}
