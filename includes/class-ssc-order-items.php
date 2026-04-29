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
	 * @return list<array{id: string, label: string, needs_gender: bool, needs_size: bool, uses_farv: bool, sizes: list<string>, farvs: list<array{slug: string, label: string}>}>
	 */
	public static function builtin_catalog(): array {
		$fall = SSC_Sanitizer::size_options();
		$fd   = self::farv_specs_from_slug_label_map( SSC_Sanitizer::farv_options() );
		return array(
			array(
				'id'            => SSC_Sanitizer::ITEM_TRIKOT,
				'label'         => 'Trikot',
				'needs_gender'  => true,
				'needs_size'    => true,
				'sizes'         => $fall,
				'uses_farv'     => false,
				'farvs'         => array(),
			),
			array(
				'id'            => SSC_Sanitizer::ITEM_TSHIRT,
				'label'         => 'T-shirt',
				'needs_gender'  => false,
				'needs_size'    => true,
				'sizes'         => $fall,
				'uses_farv'     => false,
				'farvs'         => array(),
			),
			array(
				'id'            => SSC_Sanitizer::ITEM_RASHGUARD,
				'label'         => 'Rashguard',
				'needs_gender'  => false,
				'needs_size'    => true,
				'sizes'         => $fall,
				'uses_farv'     => false,
				'farvs'         => array(),
			),
			array(
				'id'            => SSC_Sanitizer::ITEM_SPEEDCOACH,
				'label'         => 'SpeedCoach',
				'needs_gender'  => false,
				'needs_size'    => false,
				'sizes'         => array(),
				'uses_farv'     => true,
				'farvs'         => $fd,
			),
			array(
				'id'            => SSC_Sanitizer::ITEM_NK_STOPUR,
				'label'         => 'NK Stopur',
				'needs_gender'  => false,
				'needs_size'    => false,
				'sizes'         => array(),
				'uses_farv'     => true,
				'farvs'         => $fd,
			),
		);
	}

	/**
	 * @param array<string, string> $map slug => visible label (e.g. from ssc_farv_options).
	 * @return list<array{slug: string, label: string}>
	 */
	public static function farv_specs_from_slug_label_map( array $map ): array {
		$out = array();
		foreach ( $map as $slug => $lab ) {
			$slug = (string) sanitize_key( (string) $slug );
			if ( '' === $slug || strlen( $slug ) > 63 ) {
				continue;
			}
			$lab        = sanitize_text_field( (string) $lab );
			$lab        = (string) preg_replace( '/\s+/u', ' ', $lab );
			$lab        = trim( $lab );
			if ( '' === $lab ) {
				continue;
			}
			$out[] = array(
				'slug'  => $slug,
				'label' => self::shorten_label_text( $lab ),
			);
		}
		return $out;
	}

	/** @param list<string> $lines_nonempty One label string per logical line */
	public static function farv_specs_from_label_lines( array $lines ): array {
		$used = array();
		$seen = array();
		$out  = array();
		foreach ( $lines as $line ) {
			$lab = trim( sanitize_text_field( (string) $line ) );
			$lab = (string) preg_replace( '/\s+/u', ' ', $lab );
			if ( '' === $lab || isset( $seen[ $lab ] ) ) {
				continue;
			}
			$seen[ $lab ]    = true;
			$base            = self::slug_from_label( $lab );
			$slug            = self::uniquify_slug( $base, $used );
			$out[]           = array(
				'slug'  => $slug,
				'label' => self::shorten_label_text( $lab ),
			);
			if ( count( $out ) >= 64 ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * @param mixed $raw
	 * @return list<array{slug: string, label: string}>
	 */
	private static function normalize_farvs_payload( $raw, bool $uses_farv ): array {
		if ( ! $uses_farv ) {
			return array();
		}
		$parsed = array();
		if ( is_array( $raw ) ) {
			foreach ( $raw as $entry ) {
				if ( ! is_array( $entry ) ) {
					continue;
				}
				$slug = isset( $entry['slug'] ) ? strtolower( sanitize_key( (string) $entry['slug'] ) ) : '';
				$lab  = sanitize_text_field( (string) ( $entry['label'] ?? '' ) );
				$lab  = trim( (string) preg_replace( '/\s+/u', ' ', $lab ) );
				if ( '' !== $slug && preg_match( '/^[a-z][a-z0-9_-]{0,62}$/', $slug ) && '' !== $lab ) {
					$parsed[] = array(
						'slug'  => $slug,
						'label' => self::shorten_label_text( $lab ),
					);
				}
			}
		}
		$seen_slug = array();
		$dedupe    = array();
		foreach ( $parsed as $p ) {
			if ( isset( $seen_slug[ $p['slug'] ] ) ) {
				continue;
			}
			$seen_slug[ $p['slug'] ] = true;
			$dedupe[]                = $p;
		}
		$parsed = $dedupe;
		if ( $parsed !== array() ) {
			return $parsed;
		}
		return self::farv_specs_from_slug_label_map( SSC_Sanitizer::farv_options() );
	}

	/** @param list<array{slug: string, label: string}> $specs */
	public static function farv_slug_to_label_map( array $specs ): array {
		$m = array();
		foreach ( $specs as $p ) {
			if ( ! empty( $p['slug'] ) && isset( $p['label'] ) ) {
				$m[ (string) $p['slug'] ] = (string) $p['label'];
			}
		}
		return $m;
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

	private static function shorten_label_text( string $lab ): string {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $lab, 0, 160, 'UTF-8' );
		}
		return strlen( $lab ) <= 160 ? $lab : substr( $lab, 0, 160 );
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
	 * @return list<array{id: string, label: string, needs_gender: bool, needs_size: bool, sizes: list<string>, uses_farv: bool, farvs: list<array{slug: string, label: string}>}>
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
			$uses_farv    = ! empty( $row['uses_farv'] );
			$sizes_raw    = isset( $row['sizes'] ) && is_array( $row['sizes'] ) ? $row['sizes'] : array();
			$sizes_clean  = array_values( array_intersect( SSC_Sanitizer::size_options(), array_map( 'strval', $sizes_raw ) ) );
			if ( $needs_size && $sizes_clean === array() ) {
				$sizes_clean = SSC_Sanitizer::size_options();
			}
			if ( ! $needs_size ) {
				$sizes_clean = array();
			}
			$farvs_clean = self::normalize_farvs_payload( $row['farvs'] ?? null, $uses_farv );

			$out[] = array(
				'id'            => $id,
				'label'         => $label,
				'needs_gender'  => $needs_gender,
				'needs_size'    => $needs_size,
				'sizes'         => $sizes_clean,
				'uses_farv'     => $uses_farv,
				'farvs'         => $farvs_clean,
			);
		}
		return $out ? $out : self::builtin_catalog();
	}

	/**
	 * @return list<array{id: string, label: string, needs_gender: bool, needs_size: bool, uses_farv: bool, farvs: list<array{slug: string, label: string}>}>
	 */
	public static function get_catalog(): array {
		$saved = function_exists( 'get_option' ) ? get_option( self::OPTION, null ) : null;
		$base  = ( is_array( $saved ) && $saved !== array() ) ? self::normalize_rows( $saved ) : self::builtin_catalog();
		if ( ! function_exists( 'apply_filters' ) ) {
			return $base;
		}
		/** @var list<array{id: string, label: string, needs_gender: bool, needs_size: bool, uses_farv: bool, farvs?: mixed}> $filtered */
		$filtered = apply_filters( 'ssc_order_items_catalog', $base );
		return self::normalize_rows( $filtered );
	}

	/**
	 * @return array<string, array{id: string, label: string, needs_gender: bool, needs_size: bool, uses_farv: bool, sizes: list<string>, farvs: list<array{slug: string, label: string}>}>
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

	/** @return array<string, string> slug => visible label */
	public static function farv_map_for_item( string $item ): array {
		$b = self::by_id();
		if ( ! isset( $b[ $item ] ) ) {
			return array();
		}
		return self::farv_slug_to_label_map( $b[ $item ]['farvs'] ?? array() );
	}

	/** @return list<string> */
	public static function farv_slugs_for_item( string $item ): array {
		return array_keys( self::farv_map_for_item( $item ) );
	}

	public static function farv_label_for_slug( string $item, string $slug ): string {
		$m = self::farv_map_for_item( $item );
		return $m[ $slug ] ?? '';
	}

	/**
	 * For wp_json_encode → window.sscItemRules in frontend script.
	 *
	 * @return array<string, array{g: bool, s: bool, f: bool, sizes: list<string>, farv: array<string, string>}>
	 */
	public static function frontend_rules_map(): array {
		$out = array();
		foreach ( self::get_catalog() as $row ) {
			$farvs = isset( $row['farvs'] ) && is_array( $row['farvs'] ) ? $row['farvs'] : array();
			$fmap  = self::farv_slug_to_label_map( $farvs );
			$res   = array(
				'g'     => ! empty( $row['needs_gender'] ),
				's'     => ! empty( $row['needs_size'] ),
				'f'     => ! empty( $row['uses_farv'] ),
				'sizes' => ! empty( $row['needs_size'] ) ? self::sizes_allowed_from_row( $row ) : array(),
				'farv'  => $fmap,
			);
			$out[ $row['id'] ] = $res;
		}
		return $out;
	}

	/**
	 * @param mixed $post
	 * @return list<array{id: string, label: string, needs_gender: bool, needs_size: bool, sizes: list<string>, uses_farv: bool, farvs: list<array{slug: string, label: string}>}>
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

			$uses_farv = ! empty( $row['uses_farv'] );
			$farv_txt  = isset( $row['farv_lines'] ) ? str_replace( "\0", '', (string) $row['farv_lines'] ) : '';
			$labels_for_farvs = array();
			foreach ( preg_split( '/\r\n|\r|\n/', $farv_txt ) as $raw_ln ) {
				$ln_t = function_exists( 'sanitize_text_field' )
					? sanitize_text_field( (string) $raw_ln )
					: trim( (string) $raw_ln );
				$ln_t = trim( (string) preg_replace( '/\s+/u', ' ', $ln_t ) );
				if ( '' !== $ln_t ) {
					$labels_for_farvs[] = $ln_t;
				}
			}
			$farvs = $uses_farv ? self::farv_specs_from_label_lines( $labels_for_farvs ) : array();
			if ( $uses_farv && array() === $farvs ) {
				$farvs = self::farv_specs_from_slug_label_map( SSC_Sanitizer::farv_options() );
			}

			$normalized[] = array(
				'id'            => $id,
				'label'         => $lab,
				'needs_gender'  => ! empty( $row['needs_gender'] ),
				'needs_size'    => $needs_sz,
				'sizes'         => $sizes_final,
				'uses_farv'     => $uses_farv,
				'farvs'         => $farvs,
			);
		}
		return self::normalize_rows( $normalized );
	}

	/** @param list<array{id: string, label: string, needs_gender: bool, needs_size: bool, sizes?: list<string>, uses_farv?: bool, farvs?: mixed}> $rows */
	public static function save_rows( array $rows ): bool {
		if ( ! function_exists( 'update_option' ) ) {
			return false;
		}
		return update_option( self::OPTION, $rows, false );
	}
}
