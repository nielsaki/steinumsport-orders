<?php
/**
 * Pure-PHP sanitization + validation (no WP dependency).
 *
 * @package Steinum_Sport_Clothes
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'SSC_TESTING' ) ) {
	exit;
}

/**
 * Form input sanitizer + validator. Order lines: trikot (kyn), t-shirt, rashguard, SpeedCoach, NK Stopur.
 */
class SSC_Sanitizer {

	/** Gomul sløg, verða til t-shirt í teksti. */
	public const ITEM_CLOTHES  = 'clothes';

	public const ITEM_TRIKOT     = 'trikot';
	public const ITEM_TSHIRT     = 'tshirt';
	public const ITEM_RASHGUARD  = 'rashguard';
	public const ITEM_SPEEDCOACH = 'speedcoach';
	public const ITEM_NK_STOPUR  = 'nk_stopur';

	public const GENDER_WOMEN = 'women';
	public const GENDER_MEN   = 'men';

	/** @return array<string, string> item_id => display label */
	public static function item_types(): array {
		if ( class_exists( 'SSC_Order_Items' ) ) {
			$base = SSC_Order_Items::labels_map();
		} else {
			$base = array(
				self::ITEM_TRIKOT     => 'Trikot',
				self::ITEM_TSHIRT     => 'T-shirt',
				self::ITEM_RASHGUARD  => 'Rashguard',
				self::ITEM_SPEEDCOACH => 'SpeedCoach',
				self::ITEM_NK_STOPUR  => 'NK Stopur',
			);
		}
		if ( function_exists( 'apply_filters' ) ) {
			/** @var array<string, string> $out */
			$out = apply_filters( 'ssc_item_types', $base );
			return is_array( $out ) && $out ? $out : $base;
		}
		return $base;
	}

	/** @return array<string, string> */
	public static function gender_labels(): array {
		return array(
			self::GENDER_WOMEN => 'Kvinnur',
			self::GENDER_MEN   => 'Menn',
		);
	}

	/** @return list<string> */
	public static function size_options(): array {
		return array( 'XS', 'S', 'M', 'L', 'XL', 'XXL' );
	}

	/**
	 * Ynskt farv (SpeedCoach / NK Stopur): gildi í forminum → vísitala.
	 *
	 * @return array<string, string> slug => label
	 */
	public static function farv_options(): array {
		$base = array(
			'blatt'  => 'Blátt',
			'reytt'  => 'Reytt',
			'svart'  => 'Svart',
			'gult'   => 'Gult',
		);
		if ( function_exists( 'apply_filters' ) ) {
			/** @var array<string, string> $out */
			$out = apply_filters( 'ssc_farv_options', $base );
			return is_array( $out ) && $out ? $out : $base;
		}
		return $base;
	}

	public static function item_needs_size( string $item ): bool {
		return class_exists( 'SSC_Order_Items' )
			? SSC_Order_Items::item_needs_size( $item )
			: in_array( $item, array( self::ITEM_TRIKOT, self::ITEM_TSHIRT, self::ITEM_RASHGUARD ), true );
	}

	public static function item_needs_gender( string $item ): bool {
		return class_exists( 'SSC_Order_Items' )
			? SSC_Order_Items::item_needs_gender( $item )
			: ( self::ITEM_TRIKOT === $item );
	}

	public static function item_is_speedcoach( string $item ): bool {
		return self::ITEM_SPEEDCOACH === $item;
	}

	public static function item_uses_farv( string $item ): bool {
		return class_exists( 'SSC_Order_Items' )
			? SSC_Order_Items::item_uses_farv( $item )
			: in_array( $item, array( self::ITEM_SPEEDCOACH, self::ITEM_NK_STOPUR ), true );
	}

	/** @return array<string, string> */
	public static function labels(): array {
		// Order: felag + bátur → kontakt → bíleggingar (easy to scan in PDF / teldupostur).
		return array(
			'club_name'      => 'Navn á felagi',
			'boat_name'      => 'Navn á báti (valfrítt)',
			'contact_name'   => 'Kontaktpersónur',
			'contact_email'  => 'Teldupostur hjá kontaktpersóninum',
			'phone'          => 'Telefonnummar hjá kontaktpersóninum',
			'billing_email'  => 'Hvar skal rokningin sendast',
			'order_lines'    => 'Bíleggingar',
		);
	}

	/**
	 * Kravt utan: navn á báti.
	 *
	 * @return array<int, string>
	 */
	public static function required(): array {
		return array( 'club_name', 'contact_name', 'contact_email', 'phone', 'billing_email' );
	}

	/**
	 * @param array<string, mixed> $input Already-unslashed data.
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $input ): array {
		$raw_lines = $input['order_lines'] ?? array();
		if ( ! is_array( $raw_lines ) ) {
			$raw_lines = array();
		}

		$items     = self::item_types();
		$out_lines = array();

		foreach ( $raw_lines as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$item = self::text( $row['item'] ?? '' );
			if ( self::ITEM_CLOTHES === $item ) {
				$item = self::ITEM_TSHIRT;
			}
			if ( '' === $item || ! array_key_exists( $item, $items ) ) {
				$item = '';
			}

			$size         = self::text( $row['size'] ?? '' );
			$size         = in_array( $size, self::size_options(), true ) ? $size : '';
			$qty          = self::pos_int( $row['qty'] ?? 0 );
			$name         = self::text( $row['name'] ?? '' );
			$gender       = self::text( $row['gender'] ?? '' );
			$bumper_color = self::text( $row['bumper_color'] ?? '' );
			if ( self::item_uses_farv( $item ) ) {
				$valid_farvar = array_keys( self::farv_options() );
				$bumper_color   = in_array( $bumper_color, $valid_farvar, true ) ? $bumper_color : '';
			}

			if ( ! in_array( $gender, array( self::GENDER_WOMEN, self::GENDER_MEN ), true ) ) {
				$gender = self::GENDER_WOMEN;
			}
			if ( ! self::item_needs_gender( $item ) ) {
				$gender = '';
			}

			if ( self::item_uses_farv( $item ) ) {
				$size = '';
			} else {
				$bumper_color = '';
			}

			$out_line = array(
				'item'         => $item,
				'size'         => $size,
				'gender'       => $gender,
				'bumper_color' => $bumper_color,
				'qty'          => $qty,
				'name'         => $name,
			);

			if ( self::is_blank_line( $out_line ) ) {
				continue;
			}
			$out_lines[] = $out_line;
		}

		return array(
			'club_name'      => self::text( $input['club_name'] ?? '' ),
			'boat_name'      => self::text( $input['boat_name'] ?? '' ),
			'order_lines'    => $out_lines,
			'contact_name'   => self::text( $input['contact_name'] ?? '' ),
			'contact_email'  => self::email( $input['contact_email'] ?? '' ),
			'phone'          => self::phone( $input['phone'] ?? '' ),
			'billing_email'  => self::email( $input['billing_email'] ?? '' ),
		);
	}

	/**
	 * @param array<string, int|string> $row
	 */
	public static function is_blank_line( array $row ): bool {
		$item = (string) ( $row['item'] ?? '' );
		if ( self::ITEM_CLOTHES === $item ) {
			$item = self::ITEM_TSHIRT;
		}
		if ( '' === $item || ! array_key_exists( $item, self::item_types() ) ) {
			return true;
		}
		$qty  = (int) ( $row['qty'] ?? 0 );
		$size = (string) ( $row['size'] ?? '' );
		$name = trim( (string) ( $row['name'] ?? '' ) );
		$bc   = trim( (string) ( $row['bumper_color'] ?? '' ) );
		if ( self::item_uses_farv( $item ) ) {
			return $qty < 1 && '' === $bc;
		}
		return $qty < 1 && '' === $size && '' === $name;
	}

	/**
	 * @param array<string, int|string> $row
	 */
	public static function is_line_valid( array $row ): bool {
		$item = (string) ( $row['item'] ?? '' );
		if ( self::ITEM_CLOTHES === $item ) {
			$item = self::ITEM_TSHIRT;
		}
		if ( '' === $item || ! array_key_exists( $item, self::item_types() ) ) {
			return false;
		}
		$qty   = max( 0, (int) ( $row['qty'] ?? 0 ) );
		$size  = (string) ( $row['size'] ?? '' );
		$gen   = (string) ( $row['gender'] ?? '' );
		$bumper = trim( (string) ( $row['bumper_color'] ?? '' ) );
		if ( $qty < 1 ) {
			return false;
		}
		if ( self::item_uses_farv( $item ) ) {
			return in_array( $bumper, array_keys( self::farv_options() ), true );
		}
		if ( ! in_array( $size, self::size_options(), true ) ) {
			return false;
		}
		if ( self::item_needs_gender( $item ) ) {
			return in_array( $gen, array( self::GENDER_WOMEN, self::GENDER_MEN ), true );
		}
		return true;
	}

	/**
	 * @param array<int, array<string, int|string>> $lines
	 */
	public static function format_order_lines( array $lines ): string {
		$types  = self::item_types();
		$glabel = self::gender_labels();
		$out    = array();
		$n      = 0;
		$flarv = self::farv_options();
		foreach ( $lines as $row ) {
			$n++;
			$item  = (string) ( $row['item'] ?? self::ITEM_TSHIRT );
			if ( self::ITEM_CLOTHES === $item ) {
				$item = self::ITEM_TSHIRT;
			}
			$ilab  = $types[ $item ] ?? $item;
			$size  = (string) ( $row['size'] ?? '' );
			$qty   = (int) ( $row['qty'] ?? 0 );
			$name  = trim( (string) ( $row['name'] ?? '' ) );
			$gen   = (string) ( $row['gender'] ?? '' );
			$bum   = (string) ( $row['bumper_color'] ?? '' );
			$bumlab = isset( $flarv[ $bum ] ) ? $flarv[ $bum ] : $bum;

			$parts = array( "{$n}. {$ilab}" );
			if ( self::item_needs_gender( $item ) && isset( $glabel[ $gen ] ) ) {
				$parts[] = 'kyn: ' . $glabel[ $gen ];
			}
			if ( self::item_needs_size( $item ) && '' !== $size ) {
				$parts[] = 'stødd ' . $size;
			}
			if ( self::item_uses_farv( $item ) && '' !== $bum ) {
				$parts[] = 'ynskt farv: ' . $bumlab;
			}
			$parts[] = 'nøgd ' . max( 1, $qty );
			if ( '' !== $name ) {
				$parts[] = 'nøvn: ' . $name;
			}
			$out[] = implode( ', ', $parts );
		}
		return $out ? implode( "\n", $out ) : '-';
	}

	/**
	 * One row per non-blank line, for PDF table columns.
	 *
	 * @param array<int, array<string, int|string>> $lines
	 * @return array<int, array{idx: string, type: string, kyn: string, stodd: string, farv: string, nogn: string, navn: string}>
	 */
	public static function order_lines_pdf_table_rows( array $lines ): array {
		$types  = self::item_types();
		$glabel = self::gender_labels();
		$flarv  = self::farv_options();
		$out    = array();
		$n      = 0;
		foreach ( $lines as $row ) {
			if ( ! is_array( $row ) || self::is_blank_line( $row ) ) {
				continue;
			}
			++$n;
			$item = (string) ( $row['item'] ?? '' );
			if ( self::ITEM_CLOTHES === $item ) {
				$item = self::ITEM_TSHIRT;
			}
			$ilab = $types[ $item ] ?? $item;
			$gen  = (string) ( $row['gender'] ?? '' );
			$kyn  = '—';
			if ( self::item_needs_gender( $item ) && isset( $glabel[ $gen ] ) ) {
				$kyn = $glabel[ $gen ];
			} elseif ( ! self::item_needs_gender( $item ) ) {
				$kyn = '—';
			}
			$stodd = '—';
			if ( self::item_needs_size( $item ) ) {
				$sz = (string) ( $row['size'] ?? '' );
				$stodd = '' !== $sz ? $sz : '—';
			}
			$farv = '—';
			$bum  = (string) ( $row['bumper_color'] ?? '' );
			if ( self::item_uses_farv( $item ) ) {
				$farv = isset( $flarv[ $bum ] ) ? $flarv[ $bum ] : ( '' !== $bum ? $bum : '—' );
			}
			$qty  = max( 1, (int) ( $row['qty'] ?? 0 ) );
			$name = trim( (string) ( $row['name'] ?? '' ) );
			$out[] = array(
				'idx'   => (string) $n,
				'type'  => $ilab,
				'kyn'   => $kyn,
				'stodd' => $stodd,
				'farv'  => $farv,
				'nogn'  => (string) $qty,
				'navn'  => '' !== $name ? $name : '—',
			);
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<int, string> field keys
	 */
	public static function validate( array $data ): array {
		$errors = array();
		foreach ( self::required() as $key ) {
			if ( 'order_lines' === $key ) {
				continue;
			}
			if ( '' === trim( (string) ( $data[ $key ] ?? '' ) ) ) {
				$errors[] = $key;
			}
		}
		if ( ! in_array( 'contact_email', $errors, true ) ) {
			if ( false === filter_var( (string) ( $data['contact_email'] ?? '' ), FILTER_VALIDATE_EMAIL ) ) {
				$errors[] = 'contact_email';
			}
		}
		if ( ! in_array( 'billing_email', $errors, true ) ) {
			if ( false === filter_var( (string) ( $data['billing_email'] ?? '' ), FILTER_VALIDATE_EMAIL ) ) {
				$errors[] = 'billing_email';
			}
		}
		if ( ! in_array( 'phone', $errors, true ) && ! self::is_valid_phone( (string) ( $data['phone'] ?? '' ) ) ) {
			$errors[] = 'phone';
		}

		$lines = $data['order_lines'] ?? array();
		if ( ! is_array( $lines ) || ! $lines ) {
			$errors[] = 'order_lines';
		} else {
			$ok = false;
			foreach ( $lines as $row ) {
				if ( is_array( $row ) && self::is_line_valid( $row ) ) {
					$ok = true;
					break;
				}
			}
			if ( ! $ok ) {
				$errors[] = 'order_lines';
			}
		}

		return array_values( array_unique( $errors ) );
	}

	/**
	 * Telefonnummar: only +, spaces, and digits; internal spaces normalized.
	 *
	 * @param mixed $value
	 */
	public static function phone( $value ): string {
		$s = is_scalar( $value ) ? (string) $value : '';
		$s = (string) preg_replace( '/[^+0-9 ]+/u', '', $s );
		$s = (string) preg_replace( '/\s+/u', ' ', $s );
		return trim( $s );
	}

	/**
	 * True when non-empty and there is at least one digit (after phone() rules).
	 */
	public static function is_valid_phone( string $phone ): bool {
		if ( '' === $phone ) {
			return false;
		}
		return 1 === preg_match( '/\d/u', $phone );
	}

	/** @param mixed $value */
	public static function text( $value ): string {
		$s = is_scalar( $value ) ? (string) $value : '';
		$s = strip_tags( $s );
		$s = (string) preg_replace( '/[\r\n\t\0\x0B]+/u', ' ', $s );
		$s = (string) preg_replace( '/\s+/u', ' ', $s );
		return trim( $s );
	}

	/** @param mixed $value */
	public static function pos_int( $value ): int {
		$i = (int) ( is_scalar( $value ) ? $value : 0 );
		return max( 0, $i );
	}

	/** @param mixed $value */
	public static function email( $value ): string {
		$s = self::text( $value );
		$v = filter_var( $s, FILTER_VALIDATE_EMAIL );
		return is_string( $v ) ? strtolower( $v ) : '';
	}
}
