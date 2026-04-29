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
	 * @return list<array{id: string, label: string, needs_gender: bool, needs_size: bool, uses_farv: bool, sizes: list<string>}>
	 */
	public static function builtin_catalog(): array {
		$fall = SSC_Sanitizer::size_options();
		return array(
			array(
				'id'            => SSC_Sanitizer::ITEM_TRIKOT,
				'label'         => 'Trikot',
				'needs_gender'  => true,
				'needs_size'    => true,
				'sizes'         => $fall,
				'uses_farv'     => false,
			),
			array(
				'id'            => SSC_Sanitizer::ITEM_TSHIRT,
				'label'         => 'T-shirt',
				'needs_gender'  => false,
				'needs_size'    => true,
				'sizes'         => $fall,
				'uses_farv'     => false,
			),
			array(
				'id'            => SSC_Sanitizer::ITEM_RASHGUARD,
				'label'         => 'Rashguard',
				'needs_gender'  => false,
				'needs_size'    => true,
				'sizes'         => $fall,
				'uses_farv'     => false,
			),
			array(
				'id'            => SSC_Sanitizer::ITEM_SPEEDCOACH,
				'label'         => 'SpeedCoach',
				'needs_gender'  => false,
				'needs_size'    => false,
				'sizes'         => array(),
				'uses_farv'     => true,
			),
			array(
				'id'            => SSC_Sanitizer::ITEM_NK_STOPUR,
				'label'         => 'NK Stopur',
				'needs_gender'  => false,
				'needs_size'    => false,
				'sizes'         => array(),
				'uses_farv'     => true,
			),
		);
	}

	/**
	 * URL-safe key from display name (underscores, a–z / 0–9 / hyphen).
	 */
	public static function slug_from_label( string $label ): string {
		$s = $label;
		if ( function_exists( 'remove_accents' ) ) {
			$s = remove_accents( $s );
		} elseif ( function_exists( 'iconv' ) ) {
			$t = @iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $s );
			if ( is_string( $t ) && '' !== $t ) {
				$s = $t;
			}
		}
		$s      = strtolower( $s );
		$s      = (string) preg_replace( '/[^a-z0-9]+/', '_', $s );
		$s      = trim( $s, '_' );
		$s      = (string) preg_replace( '/_+/', '_', $s );
		if ( '' === $s ) {
			$s = 'item';
		} elseif ( ! preg_match( '/^[a-z]/', $s ) ) {
			$s = 'n_' . $s;
		}
		if ( strlen( $s ) > 63 ) {
			$s = substr( $s, 0, 63 );
			$s = rtrim( $s, '_' );
		}
		if ( '' === $s || ! preg_match( '/^[a-z][a-z0-9_-]{0,62}$/', $s ) ) {
			return 'item';
		}
		return $s;
	}

	/**
	 * @param array<string, bool> $used slug => true
	 */
	public static function uniquify_slug( string $base, array &$used ): string {
		$id_pat = '/^[a-z][a-z0-9_-]{0,62}$/';
		if ( ! preg_match( $id_pat, $base ) ) {
			$base = 'item';
		}
		if ( ! isset( $used[ $base ] ) ) {
			$used[ $base ] = true;
			return $base;
		}
		$n = 2;
		do {
			$suffix = '_' . $n;
			/** @var int $trim */
			$trim = 63 - strlen( $suffix );
			$trim = max( 1, $trim );
			$cand = substr( $base, 0, $trim ) . $suffix;
			$cand = rtrim( $cand, '_' );
			++$n;
		} while ( isset( $used[ $cand ] ) || ! preg_match( $id_pat, $cand ) );
		$used[ $cand ] = true;
		return $cand;
	}

	/**
	 * Sizes offered in the public form when "Stødd" is on for this row (stored list; validated against globals).
	 *
	 * @param array{sizes?: list<string>|mixed, needs_size?:bool, ...} $row Catalog row fragment.
	 * @return list<string>
	 */
	public static function sizes_allowed_from_row( array $row ): array {
		$glob = SSC_Sanitizer::size_options();
		if ( empty( $row['needs_size'] ) ) {
			return $glob;
		}
		$raw = isset( $row['sizes'] ) && is_array( $row['sizes'] ) ? $row['sizes'] : array();
		$clean = array_values( array_intersect( $glob, array_map( 'strval', $raw ) ) );
		return $clean !== array() ? $clean : $glob;
	}

	/** @return list<string> Sizes valid for `$item`'s dropdown + server validation */
	public static function sizes_for_item( string $item ): array {
		$b = self::by_id();
		if ( isset( $b[ $item ] ) ) {
			return self::sizes_allowed_from_row( $b[ $item ] );
		}
		return SSC_Sanitizer::size_options();
	}

	/**
	 * @param mixed $raw
	 * @return list<array{id: string, label: string, needs_gender: bool, needs_size: bool, sizes: list<string>, uses_farv: bool}>
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
			$needs_gender = ! empty( $row['needs_gender'] );
			$needs_size   = ! empty( $row['needs_size'] );
			$sizes_raw    = isset( $row['sizes'] ) && is_array( $row['sizes'] ) ? $row['sizes'] : array();
			$sizes_clean  = array_values( array_intersect( SSC_Sanitizer::size_options(), array_map( 'strval', $sizes_raw ) ) );
			if ( $needs_size && $sizes_clean === array() ) {
				$sizes_clean = SSC_Sanitizer::size_options();
			}
			if ( ! $needs_size ) {
				$sizes_clean = array();
			}

			$out[] = array(
				'id'            => $id,
				'label'         => $label,
				'needs_gender'  => $needs_gender,
				'needs_size'    => $needs_size,
				'sizes'         => $sizes_clean,
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
	 * @return array<string, array{g: bool, s: bool, f: bool, sizes: list<string>}>
	 */
	public static function frontend_rules_map(): array {
		$out = array();
		foreach ( self::get_catalog() as $row ) {
			$res            = array(
				'g'     => ! empty( $row['needs_gender'] ),
				's'     => ! empty( $row['needs_size'] ),
				'f'     => ! empty( $row['uses_farv'] ),
				'sizes' => ! empty( $row['needs_size'] ) ? self::sizes_allowed_from_row( $row ) : array(),
			);
			$out[ $row['id'] ] = $res;
		}
		return $out;
	}

	/**
	 * @param mixed $post
	 * @return list<array{id: string, label: string, needs_gender: bool, needs_size: bool, sizes: list<string>, uses_farv: bool}>
	 */
	public static function sanitize_posted_rows( $post ): array {
		if ( ! is_array( $post ) ) {
			return self::builtin_catalog();
		}
		$used       = array();
		$normalized = array();
		$id_pat     = '/^[a-z][a-z0-9_-]{0,62}$/';
		foreach ( $post as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( function_exists( 'sanitize_text_field' ) ) {
				$lab = sanitize_text_field( (string) ( $row['label'] ?? '' ) );
			} else {
				$lab = (string) ( $row['label'] ?? '' );
			}
			if ( '' === trim( $lab ) ) {
				continue;
			}
			$raw_stable = isset( $row['stable_id'] ) ? (string) $row['stable_id'] : '';
			$raw_stable = function_exists( 'sanitize_key' ) ? strtolower( sanitize_key( $raw_stable ) ) : strtolower( (string) preg_replace( '/[^a-z0-9_-]+/i', '', $raw_stable ) );
			if ( '' !== $raw_stable && strlen( $raw_stable ) <= 63 && preg_match( $id_pat, $raw_stable ) ) {
				$id = self::uniquify_slug( $raw_stable, $used );
			} else {
				$base = self::slug_from_label( $lab );
				$id   = self::uniquify_slug( $base, $used );
			}
			$needs_sz        = ! empty( $row['needs_size'] );
			$picked_sizes    = array();
			$sizes_from_post = isset( $row['sizes'] ) && is_array( $row['sizes'] ) ? $row['sizes'] : array();
			if ( $needs_sz ) {
				foreach ( SSC_Sanitizer::size_options() as $zs ) {
					if ( ! empty( $sizes_from_post[ $zs ] ) ) {
						$picked_sizes[] = $zs;
					}
				}
			}
			$sizes_final = array();
			if ( $needs_sz ) {
				$sizes_final = array() !== $picked_sizes ? $picked_sizes : SSC_Sanitizer::size_options();
			}

			$normalized[] = array(
				'id'            => $id,
				'label'         => $lab,
				'needs_gender'  => ! empty( $row['needs_gender'] ),
				'needs_size'    => $needs_sz,
				'sizes'         => $sizes_final,
				'uses_farv'     => ! empty( $row['uses_farv'] ),
			);
		}
		return self::normalize_rows( $normalized );
	}

	/** @param list<array{id: string, label: string, needs_gender: bool, needs_size: bool, sizes?: list<string>, uses_farv: bool}> $rows */
	public static function save_rows( array $rows ): bool {
		if ( ! function_exists( 'update_option' ) ) {
			return false;
		}
		return update_option( self::OPTION, $rows, false );
	}
}
