<?php
/**
 * Configurable ordering-line catalog (stored in WP options).
 *
 * @package Steinum_Sport_Clothes
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'SSC_TESTING' ) ) {
	exit;
}

/**
 * Slug + label + which fields each line type uses.
 */
class SSC_Order_Items {

	public const OPTION = 'ssc_order_items_v1';

	/**
	 * Default catalog when nothing is saved yet (same as former hardcoded list).
	 *
	 * @return list<array{id: string, label: string, needs_gender: bool, needs_size: bool, uses_farv: bool}>
	 */
	public static function builtin_catalog(): array {
		return array(
			array(
				'id'            => SSC_Sanitizer::ITEM_TRIKOT,
				'label'         => 'Trikot',
				'needs_gender'  => true,
				'needs_size'    => true,
				'uses_farv'     => false,
			),
			array(
				'id'            => SSC_Sanitizer::ITEM_TSHIRT,
				'label'         => 'T-shirt',
				'needs_gender'  => false,
				'needs_size'    => true,
				'uses_farv'     => false,
			),
			array(
				'id'            => SSC_Sanitizer::ITEM_RASHGUARD,
				'label'         => 'Rashguard',
				'needs_gender'  => false,
				'needs_size'    => true,
				'uses_farv'     => false,
			),
			array(
				'id'            => SSC_Sanitizer::ITEM_SPEEDCOACH,
				'label'         => 'SpeedCoach',
				'needs_gender'  => false,
				'needs_size'    => false,
				'uses_farv'     => true,
			),
			array(
				'id'            => SSC_Sanitizer::ITEM_NK_STOPUR,
				'label'         => 'NK Stopur',
				'needs_gender'  => false,
				'needs_size'    => false,
				'uses_farv'     => true,
			),
		);
	}

	/**
	 * @param mixed $raw
	 * @return list<array{id: string, label: string, needs_gender: bool, needs_size: bool, uses_farv: bool}>
	 */
	public static function normalize_rows( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return self::builtin_catalog();
		}
		$out    = array();
		$seen   = array();
		$id_pat = '/^[a-z][a-z0-9_-]{0,62}$/';
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id = isset( $row['id'] ) ? strtolower( sanitize_key( (string) $row['id'] ) ) : '';
			if ( '' === $id || strlen( $id ) > 64 || ! preg_match( $id_pat, $id ) ) {
				continue;
			}
			if ( isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$label       = sanitize_text_field( (string) ( $row['label'] ?? '' ) );
			if ( '' === trim( $label ) ) {
				continue;
			}
			$out[] = array(
				'id'            => $id,
				'label'         => $label,
				'needs_gender'  => ! empty( $row['needs_gender'] ),
				'needs_size'    => ! empty( $row['needs_size'] ),
				'uses_farv'     => ! empty( $row['uses_farv'] ),
			);
		}
		return $out ? $out : self::builtin_catalog();
	}

	/**
	 * @return list<array{id: string, label: string, needs_gender: bool, needs_size: bool, uses_farv: bool}>
	 */
	public static function get_catalog(): array {
		$saved = function_exists( 'get_option' ) ? get_option( self::OPTION, null ) : null;
		$base  = ( is_array( $saved ) && $saved !== array() ) ? self::normalize_rows( $saved ) : self::builtin_catalog();
		if ( ! function_exists( 'apply_filters' ) ) {
			return $base;
		}
		/** @var list<array{id: string, label: string, needs_gender: bool, needs_size: bool, uses_farv: bool}> $filtered */
		$filtered = apply_filters( 'ssc_order_items_catalog', $base );
		return self::normalize_rows( $filtered );
	}

	/**
	 * @return array<string, array{id: string, label: string, needs_gender: bool, needs_size: bool, uses_farv: bool}>
	 */
	private static function by_id(): array {
		$o = array();
		foreach ( self::get_catalog() as $row ) {
			$o[ $row['id'] ] = $row;
		}
		return $o;
	}

	/** @return array<string, string> slug => admin label */
	public static function labels_map(): array {
		$m = array();
		foreach ( self::get_catalog() as $row ) {
			$m[ $row['id'] ] = $row['label'];
		}
		return $m;
	}

	public static function item_needs_gender( string $item ): bool {
		$b = self::by_id();
		return isset( $b[ $item ] ) ? $b[ $item ]['needs_gender'] : false;
	}

	public static function item_needs_size( string $item ): bool {
		$b = self::by_id();
		return isset( $b[ $item ] ) ? $b[ $item ]['needs_size'] : false;
	}

	public static function item_uses_farv( string $item ): bool {
		$b = self::by_id();
		return isset( $b[ $item ] ) ? $b[ $item ]['uses_farv'] : false;
	}

	/**
	 * For wp_json_encode → window.sscItemRules in frontend script.
	 *
	 * @return array<string, array{g: bool, s: bool, f: bool}>
	 */
	public static function frontend_rules_map(): array {
		$out = array();
		foreach ( self::get_catalog() as $row ) {
			$out[ $row['id'] ] = array(
				'g' => $row['needs_gender'],
				's' => $row['needs_size'],
				'f' => $row['uses_farv'],
			);
		}
		return $out;
	}

	/**
	 * @param mixed $post
	 * @return list<array{id: string, label: string, needs_gender: bool, needs_size: bool, uses_farv: bool}>
	 */
	public static function sanitize_posted_rows( $post ): array {
		if ( ! is_array( $post ) ) {
			return self::builtin_catalog();
		}
		$normalized = array();
		foreach ( $post as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id = strtolower( sanitize_key( (string) ( $row['id'] ?? '' ) ) );
			if ( function_exists( 'sanitize_text_field' ) ) {
				$lab = sanitize_text_field( (string) ( $row['label'] ?? '' ) );
			} else {
				$lab = (string) ( $row['label'] ?? '' );
			}
			$normalized[] = array(
				'id'            => $id,
				'label'         => $lab,
				'needs_gender'  => ! empty( $row['needs_gender'] ),
				'needs_size'    => ! empty( $row['needs_size'] ),
				'uses_farv'     => ! empty( $row['uses_farv'] ),
			);
		}
		return self::normalize_rows( $normalized );
	}

	/** @param list<array{id: string, label: string, needs_gender: bool, needs_size: bool, uses_farv: bool}> $rows */
	public static function save_rows( array $rows ): bool {
		if ( ! function_exists( 'update_option' ) ) {
			return false;
		}
		return update_option( self::OPTION, $rows, false );
	}
}
