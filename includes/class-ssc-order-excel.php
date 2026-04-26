<?php
/**
 * Admin-only order sheet as Excel 2003 XML (opens in Microsoft Excel, no extra PHP deps).
 *
 * Layout (A–C unused):
 * - E1 “Name of club”, E2 “Name of boat” — black on gray. F1 club, F2 boat — red, no fill.
 * - D4:H4 = QTY, PRODUCT, Size, NOTES, ANYNAME — black on gray.
 * - D5+ — red, no fill. ANYNAME: empty when there is no name (not “—”). NOTES (G) empty. PRODUCT: …
 * - SpeedCoach / NK Stopur omitted (still on PDF).
 * - Bold Arial 11. All order workbooks (this class only): default width 65, column **E** 95,
 *   `AutoFitWidth="0"`, columns 1–32; change `DEFAULT_COL_WIDTH` / `COL_E_WIDTH` for future files.
 *
 * @package Steinum_Sport_Clothes
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'SSC_TESTING' ) ) {
	exit;
}

/**
 * @package Steinum_Sport_Clothes
 */
class SSC_Order_Excel {

	/** Slightly darker than Excel’s light gray for header rows. */
	private const GRAY = '#BFBFBF';

	/** Default column width (A–D, F–AF…); column E uses `COL_E_WIDTH`. */
	private const DEFAULT_COL_WIDTH = '65';

	/** Column E (ss:Index 5) — labels + PRODUCT column. */
	private const COL_E_WIDTH = '95';

	/**
	 * @param array<string, mixed> $data Sanitized submission.
	 * @return string Path to a temporary .xls (XML) file, or empty string.
	 */
	public static function write_file( array $data ): string {
		$dir = self::storage_dir();
		if ( ! is_dir( $dir ) ) {
			@mkdir( $dir, 0775, true );
		}
		if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
			return '';
		}
		$path = rtrim( $dir, '/\\' ) . '/' . gmdate( 'Ymd-His' ) . '-order.xls';
		$body = self::to_spreadsheetml( $data );
		$out  = "\xEF\xBB\xBF" . $body;
		$ok   = ( false !== @file_put_contents( $path, $out ) ) && is_readable( $path );
		return $ok ? $path : '';
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function to_spreadsheetml( array $data ): string {
		$rows = self::table_rows( $data );
		$z    = static function ( string $s ): string {
			return htmlspecialchars( $s, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
		};

		$out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
			. '<?mso-application progid="Excel.Sheet"?>' . "\n"
			. '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" '
			. 'xmlns:o="urn:schemas-microsoft-com:office:office" '
			. 'xmlns:x="urn:schemas-microsoft-com:office:excel" '
			. 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" '
			. 'xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n"
			. '<DocumentProperties xmlns="urn:schemas-microsoft-com:office:office"><Title>Order</Title></DocumentProperties>' . "\n"
			. '<Styles>' . "\n"
			. ' <Style ss:ID="Default" ss:Name="Normal">'
			. '<Font ss:FontName="Arial" ss:Size="11" ss:Bold="1"/>'
			. '<Alignment ss:Vertical="Center"/></Style>' . "\n"
			. ' <Style ss:ID="GrayBlack">'
			. '<Font ss:FontName="Arial" ss:Size="11" ss:Bold="1" ss:Color="#000000"/>'
			. '<Interior ss:Color="' . self::GRAY . '" ss:Pattern="Solid"/>' . "\n"
			. '  <Borders><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BBBBBB"/>'
			. '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BBBBBB"/>'
			. '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BBBBBB"/>'
			. '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BBBBBB"/>' . "\n"
			. '  </Borders></Style>' . "\n"
			. ' <Style ss:ID="RedPlain">'
			. '<Font ss:FontName="Arial" ss:Size="11" ss:Bold="1" ss:Color="#FF0000"/>'
			. '</Style>' . "\n"
			. '</Styles>' . "\n"
			. '<Worksheet ss:Name="Bílleggingar">' . "\n"
			. '<Table ss:DefaultColumnWidth="' . self::DEFAULT_COL_WIDTH . '" ss:DefaultRowHeight="15">' . "\n"
			. self::column_width_xml()
			;

		foreach ( $rows as $r ) {
			$out .= '<Row ss:Height="' . ( (int) ( $r['h'] ?? 16 ) ) . '">' . "\n";
			$cells = $r['cells'] ?? array();
			ksort( $cells, SORT_NUMERIC );
			foreach ( $cells as $col_idx => $cell ) {
				$sid  = (string) ( $cell['s'] ?? 'Default' );
				$txt  = (string) ( $cell['t'] ?? '' );
				$out .= ' <Cell ss:Index="' . (int) $col_idx . '" ss:StyleID="' . $z( $sid ) . '">'
					. '<Data ss:Type="String">' . $z( $txt ) . '</Data></Cell>' . "\n";
			}
			$out .= "</Row>\n";
		}

		$out .= "</Table></Worksheet></Workbook>\n";
		return $out;
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<int, array{h?: int, cells: array<int, array{s: string, t: string}>}>
	 */
	private static function table_rows( array $data ): array {
		$rows         = array();
		$club = (string) ( $data['club_name'] ?? '' );
		$boat = (string) ( $data['boat_name'] ?? '' );

		$rows[] = self::r(
			array(
				5 => array( 's' => 'GrayBlack', 't' => 'Name of club' ),
				6 => array( 's' => 'RedPlain', 't' => $club ),
			),
			18
		);
		$rows[] = self::r(
			array(
				5 => array( 's' => 'GrayBlack', 't' => 'Name of boat' ),
				6 => array( 's' => 'RedPlain', 't' => $boat ),
			),
			18
		);

		$rows[] = self::r( array(), 8 );

		$rows[] = self::r(
			array(
				4  => array( 's' => 'GrayBlack', 't' => 'QTY' ),
				5  => array( 's' => 'GrayBlack', 't' => 'PRODUCT' ),
				6  => array( 's' => 'GrayBlack', 't' => 'Size' ),
				7  => array( 's' => 'GrayBlack', 't' => 'NOTES' ),
				8  => array( 's' => 'GrayBlack', 't' => 'ANYNAME' ),
			),
			20
		);

		$olines = $data['order_lines'] ?? array();
		$olines = is_array( $olines ) ? $olines : array();
		$olines  = self::order_lines_for_excel_table( $olines );
		$table   = SSC_Sanitizer::order_lines_pdf_table_rows( $olines );
		$products = self::excel_product_labels_for_order_lines( $olines );
		foreach ( $table as $i => $line ) {
			$product = (string) ( $products[ $i ] ?? ( $line['type'] ?? '' ) );
			$rows[]  = self::r(
				array(
					4 => array( 's' => 'RedPlain', 't' => (string) ( $line['nogn'] ?? '' ) ),
					5 => array( 's' => 'RedPlain', 't' => $product ),
					6 => array( 's' => 'RedPlain', 't' => (string) ( $line['stodd'] ?? '' ) ),
					7 => array( 's' => 'RedPlain', 't' => '' ),
					8 => array( 's' => 'RedPlain', 't' => self::excel_anyname( (string) ( $line['navn'] ?? '' ) ) ),
				),
				16
			);
		}
		// If no lines, skip data rows — admin still has club/boat rows and headers.
		return $rows;
	}

	/**
	 * Drop SpeedCoach and NK Stopur from the clothing table (manufacturer still sees them on the PDF).
	 *
	 * @param array<int, array<string, int|string>> $olines
	 * @return array<int, array<string, int|string>>
	 */
	private static function order_lines_for_excel_table( array $olines ): array {
		$out = array();
		foreach ( $olines as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$item = (string) ( $row['item'] ?? '' );
			if ( SSC_Sanitizer::ITEM_CLOTHES === $item ) {
				$item = SSC_Sanitizer::ITEM_TSHIRT;
			}
			if ( SSC_Sanitizer::ITEM_SPEEDCOACH === $item || SSC_Sanitizer::ITEM_NK_STOPUR === $item ) {
				continue;
			}
			$out[] = $row;
		}
		return $out;
	}

	/**
	 * One label per order line, same order as `order_lines_pdf_table_rows()` (skips blank lines).
	 *
	 * @param array<int, array<string, int|string>> $olines
	 * @return list<string>
	 */
	private static function excel_product_labels_for_order_lines( array $olines ): array {
		$out = array();
		foreach ( $olines as $row ) {
			if ( ! is_array( $row ) || SSC_Sanitizer::is_blank_line( $row ) ) {
				continue;
			}
			$item = (string) ( $row['item'] ?? '' );
			if ( SSC_Sanitizer::ITEM_CLOTHES === $item ) {
				$item = SSC_Sanitizer::ITEM_TSHIRT;
			}
			$gen = (string) ( $row['gender'] ?? '' );
			$types = SSC_Sanitizer::item_types();
			$fb    = (string) ( $types[ $item ] ?? $item );
			$out[] = self::excel_product_label( $item, $gen, $fb );
		}
		return $out;
	}

	/**
	 * Supplier-facing product text for the PRODUCT column (Excel only).
	 */
	private static function excel_product_label( string $item, string $gender, string $fallback_label ): string {
		if ( SSC_Sanitizer::ITEM_TRIKOT === $item ) {
			if ( SSC_Sanitizer::GENDER_MEN === $gender ) {
				return 'Mens Singlet';
			}
			if ( SSC_Sanitizer::GENDER_WOMEN === $gender ) {
				return 'Womens Singlet';
			}
			return $fallback_label;
		}
		if ( SSC_Sanitizer::ITEM_TSHIRT === $item ) {
			return 'Profit Tshirt';
		}
		if ( SSC_Sanitizer::ITEM_RASHGUARD === $item ) {
			return 'Rashguards';
		}
		return $fallback_label;
	}

	/**
	 * PDF table uses "—" for empty name; Excel ANYNAME should stay blank.
	 */
	private static function excel_anyname( string $navn ): string {
		$t = trim( $navn );
		if ( '' === $t || '—' === $t || '–' === $t || '-' === $t ) {
			return '';
		}
		return $navn;
	}

	/**
	 * First 32 columns: fixed `ss:Width` (E = 95, else 65), `ss:AutoFitWidth="0"`.
	 */
	private static function column_width_xml(): string {
		$def = (string) self::DEFAULT_COL_WIDTH;
		$e   = (string) self::COL_E_WIDTH;
		$out = '';
		for ( $i = 1; $i <= 32; $i++ ) {
			$w = ( 5 === $i ) ? $e : $def;
			$out .= ' <Column ss:Index="' . $i . '" ss:AutoFitWidth="0" ss:Width="' . $w . '"/>' . "\n";
		}
		return $out;
	}

	/**
	 * @param array<int, array{s: string, t: string}> $cells
	 * @return array{h: int, cells: array<int, array{s: string, t: string}>}
	 */
	private static function r( array $cells, int $h = 16 ): array {
		return array(
			'h'     => $h,
			'cells' => $cells,
		);
	}

	private static function storage_dir(): string {
		if ( function_exists( 'wp_upload_dir' ) ) {
			$u = wp_upload_dir();
			if ( empty( $u['error'] ) && ! empty( $u['basedir'] ) ) {
				return rtrim( (string) $u['basedir'], '/\\' ) . '/steinum-sport-clothes';
			}
		}
		return rtrim( sys_get_temp_dir(), '/\\' ) . '/steinum-sport-clothes';
	}
}
