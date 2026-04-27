<?php
/**
 * Admin order sheet: **.xlsx** (Office Open XML) when ZipArchive exists; else SpreadsheetML **.xml**.
 * (Legacy `.xls` + XML content triggered Excel “format and extension don’t match”.)
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
	 * @return string Path to a temporary `.xlsx` or `.xml` file, or empty string.
	 */
	public static function write_file( array $data ): string {
		$dir = self::storage_dir();
		if ( ! is_dir( $dir ) ) {
			@mkdir( $dir, 0775, true );
		}
		if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
			return '';
		}
		$base = rtrim( $dir, '/\\' ) . '/' . SSC_WP_Time::format( 'Ymd-His' ) . '-order';
		$xlsx = $base . '.xlsx';
		if ( self::write_xlsx_file( $xlsx, $data ) ) {
			return $xlsx;
		}
		$xml  = $base . '.xml';
		$body = self::to_spreadsheetml( $data );
		$out  = "\xEF\xBB\xBF" . $body;
		$ok   = ( false !== @file_put_contents( $xml, $out ) ) && is_readable( $xml );
		return $ok ? $xml : '';
	}

	/**
	 * True Excel workbook (OOXML). Requires PHP zip extension.
	 */
	private static function write_xlsx_file( string $path, array $data ): bool {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return false;
		}
		$zip = new ZipArchive();
		if ( true !== @$zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return false;
		}
		$zip->addFromString( '[Content_Types].xml', self::ooxml_content_types() );
		$zip->addFromString( '_rels/.rels', self::ooxml_rels_root() );
		$zip->addFromString( 'xl/workbook.xml', self::ooxml_workbook() );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', self::ooxml_workbook_rels() );
		$zip->addFromString( 'xl/styles.xml', self::ooxml_styles() );
		$zip->addFromString( 'xl/worksheets/sheet1.xml', self::ooxml_sheet( $data ) );
		$zip->addFromString( 'docProps/core.xml', self::ooxml_core() );
		$zip->addFromString( 'docProps/app.xml', self::ooxml_app() );
		$zip->close();
		return is_readable( $path ) && (int) @filesize( $path ) > 64;
	}

	private static function ooxml_content_types(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
			. '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
			. '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
			. '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
			. '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
			. '</Types>';
	}

	private static function ooxml_rels_root(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
			. '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
			. '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
			. '</Relationships>';
	}

	private static function ooxml_workbook(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
			. 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
			. '<sheets><sheet name="Bílleggingar" sheetId="1" r:id="rId1"/></sheets>'
			. '</workbook>';
	}

	private static function ooxml_workbook_rels(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
			. '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
			. '</Relationships>';
	}

	private static function ooxml_styles(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<fonts count="2">'
			. '<font><sz val="11"/><color rgb="FF000000"/><name val="Arial"/><b/></font>'
			. '<font><sz val="11"/><color rgb="FFFF0000"/><name val="Arial"/><b/></font>'
			. '</fonts>'
			. '<fills count="2">'
			. '<fill><patternFill patternType="none"/></fill>'
			. '<fill><patternFill patternType="solid"><fgColor rgb="FFBFBFBF"/></patternFill></fill>'
			. '</fills>'
			. '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
			. '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
			. '<cellXfs count="3">'
			. '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
			. '<xf numFmtId="0" fontId="0" fillId="1" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
			. '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
			. '</cellXfs>'
			. '</styleSheet>';
	}

	private static function ooxml_core(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
			. 'xmlns:dc="http://purl.org/dc/elements/1.1/">'
			. '<dc:title>Order</dc:title><dc:creator>Steinum Sport</dc:creator>'
			. '</cp:coreProperties>';
	}

	private static function ooxml_app(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">'
			. '<Application>Steinum Sport</Application></Properties>';
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function ooxml_sheet( array $data ): string {
		$rows_in = self::table_rows( $data );
		$max_col = 1;
		$body    = '';
		$rnum    = 0;
		foreach ( $rows_in as $r ) {
			++$rnum;
			$h     = (int) ( $r['h'] ?? 16 );
			$cells = $r['cells'] ?? array();
			$line  = '<row r="' . $rnum . '" ht="' . $h . '" customHeight="1">';
			if ( $cells ) {
				ksort( $cells, SORT_NUMERIC );
				foreach ( $cells as $col_idx => $cell ) {
					$ci = (int) $col_idx;
					if ( $ci > $max_col ) {
						$max_col = $ci;
					}
					$ref = self::col_letter( $ci ) . $rnum;
					$sid = self::ooxml_style_idx( (string) ( $cell['s'] ?? 'Default' ) );
					$txt = (string) ( $cell['t'] ?? '' );
					$line .= '<c r="' . $ref . '" s="' . $sid . '" t="inlineStr"><is>' . self::ooxml_inline_t( $txt ) . '</is></c>';
				}
			}
			$line .= '</row>';
			$body .= $line;
		}
		$last_row = max( 1, $rnum );
		$dim_end  = self::col_letter( max( 8, $max_col ) ) . $last_row;
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<dimension ref="A1:' . $dim_end . '"/>'
			. self::ooxml_cols( max( 32, $max_col ) )
			. '<sheetData>' . $body . '</sheetData>'
			. '</worksheet>';
	}

	private static function ooxml_cols( int $max_col ): string {
		$out = '<cols>';
		for ( $i = 1; $i <= $max_col; $i++ ) {
			$w = ( 5 === $i ) ? 14.5 : 10;
			$out .= '<col min="' . $i . '" max="' . $i . '" width="' . $w . '" customWidth="1"/>';
		}
		$out .= '</cols>';
		return $out;
	}

	private static function ooxml_style_idx( string $style_id ): int {
		if ( 'GrayBlack' === $style_id ) {
			return 1;
		}
		if ( 'RedPlain' === $style_id ) {
			return 2;
		}
		return 0;
	}

	private static function ooxml_inline_t( string $t ): string {
		$inner = htmlspecialchars( $t, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
		$len   = strlen( $t );
		$preserve = '' !== $t && (
			( $len > 0 && ' ' === $t[0] )
			|| ( $len > 0 && ' ' === substr( $t, -1 ) )
			|| false !== strpos( $t, "\n" )
		);
		if ( $preserve ) {
			return '<t xml:space="preserve">' . $inner . '</t>';
		}
		return '<t>' . $inner . '</t>';
	}

	/** 1-based column index → A, B, … Z, AA, … */
	private static function col_letter( int $n ): string {
		$s = '';
		while ( $n > 0 ) {
			$m = ( $n - 1 ) % 26;
			$s = chr( 65 + $m ) . $s;
			$n = intdiv( $n - 1, 26 );
		}
		return '' !== $s ? $s : 'A';
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
