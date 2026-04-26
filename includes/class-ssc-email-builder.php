<?php
/**
 * Plain-text email body builder (no WP dep).
 *
 * @package Steinum_Sport_Clothes
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'SSC_TESTING' ) ) {
	exit;
}

/**
 * Builds email bodies from sanitized data.
 */
class SSC_Email_Builder {

	/**
	 * @param array<string, string|int> $data
	 * @param array<string, string>     $labels
	 */
	public static function admin_body( array $data, array $labels, string $site = '' ): string {
		$lines = array();
		if ( '' !== $site ) {
			$lines[] = sprintf( 'Nýggj tilkunn frá %s', $site );
			$lines[] = str_repeat( '-', 40 );
			$lines[] = '';
		}
		foreach ( $labels as $key => $label ) {
			$lines[] = $label . ':';
			$lines[] = self::value_as_text( $key, $data[ $key ] ?? null );
			$lines[] = '';
		}
		return rtrim( implode( "\n", $lines ) ) . "\n";
	}

	/**
	 * @param array<string, string|int> $data
	 * @param array<string, string>     $labels
	 */
	public static function receipt_body( array $data, array $labels, string $intro = '' ): string {
		$lines = array();
		if ( '' !== trim( $intro ) ) {
			$lines[] = trim( $intro );
			$lines[] = '';
		}
		$lines[] = 'Her er eitt samandráttur av títt skráseting:';
		$lines[] = str_repeat( '-', 40 );
		foreach ( $labels as $key => $label ) {
			$lines[] = $label . ': ' . self::value_as_text( $key, $data[ $key ] ?? null );
		}
		$lines[] = str_repeat( '-', 40 );
		$lines[] = '';
		$lines[] = 'Ein PDF-kvittan er viðheft.';
		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * @param mixed $val
	 */
	private static function value_as_text( string $key, $val ): string {
		if ( 'order_lines' === $key && is_array( $val ) ) {
			/** @var array<int, array<string, int|string>> $val */
			return SSC_Sanitizer::format_order_lines( $val );
		}
		return (string) $val;
	}
}
