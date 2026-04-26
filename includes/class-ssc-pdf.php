<?php
/**
 * Minimal pure-PHP PDF generator for the order receipt.
 *
 * Uses built-in Type1 Helvetica + WinAnsi (CP1252) encoding, which covers
 * all Faroese characters (á é í ó ú ý ð ø æ + uppercase).
 *
 * @package Steinum_Sport_Clothes
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'SSC_TESTING' ) ) {
	exit;
}

/**
 * PDF receipt renderer.
 */
class SSC_PDF {

	private int $page_w = 595;
	private int $page_h = 842;
	private int $margin_top = 56;
	private int $margin_left = 56;
	private int $margin_right = 56;
	private int $margin_bottom = 64;
	/** Indent (points) for field values and wrapped lines, under the label. */
	private int $value_indent = 14;
	/**
	 * Inner vertical padding to match white field cards (see {@see self::field_block} $pad_t).
	 * Symmetric: same gap from border to ink, top and bottom, for one-line “blue” boxes.
	 */
	private const CARD_PAD = 8.0;

	/** @var array<int, string> Object bodies; IDs are 1-indexed. */
	private array $objects = array();
	/** @var array<int, int> */
	private array $page_ids = array();
	private string $stream = '';
	private int $y = 0;
	private int $font_regular_id = 0;
	private int $font_bold_id = 0;

	/**
	 * Render receipt PDF bytes.
	 *
	 * @param array<string, string|int> $data
	 * @param array<string, string>     $labels
	 * @param string                    $title  Unused; PDF head is fixed (kvittan + brand). Kept for callers.
	 * @param array<string, string>     $meta   Extra header rows.
	 */
	public function render( array $data, array $labels, string $title, array $meta = array() ): string {
		$this->objects  = array();
		$this->page_ids = array();
		$this->stream   = '';

		$this->add_object( '' );
		$this->add_object( '' );

		$this->font_regular_id = $this->add_object(
			'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>'
		);
		$this->font_bold_id = $this->add_object(
			'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>'
		);

		$this->start_page();
		$this->page_top_brand_bar();
		$this->heading_receipt();

		if ( $meta ) {
			$this->meta_pill( $meta );
		}

		$this->section_block_title( 'Felag og bátur' );
		$this->field_block( (string) ( $labels['club_name'] ?? 'Felag' ), (string) ( $data['club_name'] ?? '' ) );
		$this->field_block( (string) ( $labels['boat_name'] ?? 'Bátur' ), (string) ( $data['boat_name'] ?? '' ) );

		$this->section_block_title( 'Kontakt' );
		$this->field_block( (string) ( $labels['contact_name'] ?? 'Kontakt' ), (string) ( $data['contact_name'] ?? '' ) );
		$this->field_block( (string) ( $labels['contact_email'] ?? 'Teldupostur' ), (string) ( $data['contact_email'] ?? '' ) );
		$this->field_block( (string) ( $labels['phone'] ?? 'Telefon' ), (string) ( $data['phone'] ?? '' ) );

		$this->section_block_title( 'Rokning' );
		$this->line( 'Fakturering / rokning skal sendast til:', 8, true, 0, true );
		$this->spacer( 2 );
		$bill = trim( (string) ( $data['billing_email'] ?? '' ) );
		if ( '' === $bill ) {
			$bill = '—';
		}
		$this->billing_email_callout( $bill );
		$this->set_text_rgb( 0, 0, 0 );
		$this->spacer( 10 );

		$order_lbl = (string) ( $labels['order_lines'] ?? 'Bíleggingar' );
		$this->section_block_title( $order_lbl );
		$olines = $data['order_lines'] ?? array();
		$olines = is_array( $olines ) ? $olines : array();
		$this->render_order_table( SSC_Sanitizer::order_lines_pdf_table_rows( $olines ) );

		$this->page_footer_line();

		return $this->finish();
	}

	/**
	 * Cap height and descender depth (pt) for Helvetica/Helvetica-Bold text positioning.
	 *
	 * @return array{asc: float, desc: float}
	 */
	private static function helvetica_ink( int $size, bool $bold ): array {
		$asc_k = $bold ? 0.72 : 0.68;
		$asc   = (float) $size * $asc_k;
		$desc  = (float) $size * 0.23;
		return array(
			'asc'  => $asc,
			'desc' => $desc,
		);
	}

	/** Rough string width in points (Helvetica) for table cell centering. */
	private function approx_text_width_pts( string $text, int $size, bool $bold ): float {
		$k = $bold ? 0.55 : 0.48;
		$n = max( 1, self::mb_len( $text ) );
		return $k * (float) $size * (float) $n;
	}

	/**
	 * Baseline y so 8pt text is vertically centered in a row of height $row_h, top at y = $y_top.
	 */
	private function table_baseline_vcenter( float $y_top, float $row_h, bool $bold ): float {
		$ink = self::helvetica_ink( 8, $bold );
		$ym  = $y_top - $row_h / 2.0;
		return $ym - ( $ink['asc'] - $ink['desc'] ) / 2.0;
	}

	/**
	 * @param array<int, array{idx: string, type: string, kyn: string, stodd: string, farv: string, nogn: string, navn: string}> $table_rows
	 */
	private function render_order_table( array $table_rows ): void {
		$row_h = 16.0;
		$w     = array( 22, 92, 46, 42, 70, 34, 177 );
		$hdrs  = array( '#', 'Slag', 'Kyn', 'Stødd', 'Farv', 'Nøgd', 'Navn' );
		$x0    = (float) $this->margin_left;
		$xr    = (float) ( $this->page_w - $this->margin_right );

		if ( ! $table_rows ) {
			$this->set_text_gray( 0.4 );
			$this->line( '—', 9, false, $this->value_indent );
			$this->set_text_rgb( 0, 0, 0 );
			$this->spacer( 8 );
			return;
		}

		$need = 36 + (int) ( ( count( $table_rows ) + 1 ) * 18 );
		$this->ensure_space( min( 600, $need ) );
		$y_table_top = (float) $this->y;
		$hdr         = $this->render_table_header_row( $x0, $xr, $w, $hdrs, $row_h );
		$row_idx     = 0;
		foreach ( $table_rows as $r ) {
			$this->ensure_space( (int) ( $row_h + 5 ) );
			$cur   = (float) $this->y;
			$bg_r  = ( 0 === ( $row_idx % 2 ) ) ? 0.998 : 0.975;
			$bg_g  = ( 0 === ( $row_idx % 2 ) ) ? 0.998 : 0.985;
			$bg_b  = ( 0 === ( $row_idx % 2 ) ) ? 0.999 : 0.995;
			$this->rect_fill( $x0, $cur - $row_h, $xr - $x0, $row_h, $bg_r, $bg_g, $bg_b );
			$sum   = 0.0;
			$cells = array( $r['idx'], $r['type'], $r['kyn'], $r['stodd'], $r['farv'], $r['nogn'], $r['navn'] );
			$this->set_text_rgb( 0.1, 0.14, 0.2 );
			$by_body = $this->table_baseline_vcenter( $cur, $row_h, true );
			for ( $c = 0; $c < 7; $c++ ) {
				$txt    = $this->truncate_for_cell( (string) $cells[ $c ], (int) max( 4, $w[ $c ] - 2 ) );
				$cell_l = $x0 + $sum;
				$cw     = (float) $w[ $c ];
				if ( 0 === $c ) {
					$tw = $this->approx_text_width_pts( $txt, 8, true );
					$cx = $cell_l + ( $cw - $tw ) / 2.0;
					$this->text_at( $cx, $by_body, $txt, 8, true );
				} else {
					$cx = $cell_l + 3.0;
					$this->text_at( $cx, $by_body, $txt, 8, false );
				}
				$sum += (float) $w[ $c ];
			}
			$this->y = (int) round( $cur - $row_h );
			++$row_idx;
		}
		$this->set_text_rgb( 0, 0, 0 );
		$y_table_bot = (float) $this->y;
		$y_frame_top = (float) ( is_array( $hdr ) ? $hdr['y_top'] : $y_table_top );
		$frame_h     = $y_frame_top - $y_table_bot;
		if ( $frame_h > 1.0 && is_array( $hdr ) ) {
			$this->rect_stroke( $x0, $y_table_bot, $xr - $x0, $frame_h, 0.35, 0.8, 0.84, 0.9 );
			$sum = 0.0;
			for ( $b = 1; $b < 7; $b++ ) {
				$sum += (float) $w[ $b - 1 ];
				$xv = $x0 + $sum;
				$this->stroke_vline( $xv, $y_table_bot, (float) $hdr['y_top'], 0.88 );
			}
		}
		$this->spacer( 8 );
	}

	/**
	 * @param array<int, float>    $w
	 * @param array<int, string> $hdrs
	 * @return array{y_top: float, line_y: float}
	 */
	private function render_table_header_row( float $x0, float $xr, array $w, array $hdrs, float $row_h ): array {
		$sum    = 0.0;
		$pad    = self::CARD_PAD;
		$ink    = self::helvetica_ink( 8, true );
		$yb     = (float) $this->y;
		$line_y = $yb - $ink['desc'] - $pad;
		$top    = $yb + $ink['asc'] + $pad;
		$fill_h = $top - $line_y;
		$this->rect_fill( $x0, $line_y, $xr - $x0, $fill_h, 0.1, 0.22, 0.4 );
		$this->set_text_rgb( 0.99, 0.99, 0.995 );
		$hb = $this->table_baseline_vcenter( $top, $top - $line_y, true );
		for ( $c = 0; $c < 7; $c++ ) {
			$txt    = $this->truncate_for_cell( $hdrs[ $c ], (int) ( $w[ $c ] - 2 ) );
			$cell_l = $x0 + $sum;
			$cw     = (float) $w[ $c ];
			if ( 0 === $c ) {
				$tw = $this->approx_text_width_pts( $txt, 8, true );
				$cx = $cell_l + ( $cw - $tw ) / 2.0;
			} else {
				$cx = $cell_l + 3.0;
			}
			$this->text_at( $cx, $hb, $txt, 8, true );
			$sum += (float) $w[ $c ];
		}
		$this->set_text_rgb( 0, 0, 0 );
		// No stroke here — avoids a “white gap” and double rule; body row meets header at $line_y.
		$this->y = (int) round( $line_y );
		return array(
			'y_top'  => $top,
			'line_y' => $line_y,
		);
	}

	/**
	 * One line of meta (e.g. date) in a light pill under the main title.
	 *
	 * @param array<string, string> $meta
	 */
	private function meta_pill( array $meta ): void {
		$parts = array();
		foreach ( $meta as $k => $v ) {
			$parts[] = (string) $k . ' · ' . (string) $v;
		}
		$text     = implode( '   ·   ', $parts );
		$side_pad = 6.0;
		$vpad     = self::CARD_PAD;
		$ink      = self::helvetica_ink( 8, false );
		$h_est    = 2.0 * $vpad + $ink['asc'] + $ink['desc'];
		$this->ensure_space( (int) ( $h_est + 8 ) );
		$top  = (float) $this->y;
		$bl   = $top - $vpad - $ink['asc'];
		$bot  = $bl - $ink['desc'] - $vpad;
		$h    = $top - $bot;
		$x0   = (float) $this->margin_left;
		$x1   = (float) ( $this->page_w - $this->margin_right );
		$this->rect_fill( $x0, $bot, $x1 - $x0, $h, 0.95, 0.97, 0.998 );
		$this->rect_stroke( $x0, $bot, $x1 - $x0, $h, 0.3, 0.84, 0.88, 0.92 );
		$this->set_text_rgb( 0.32, 0.38, 0.45 );
		$this->text_at( $x0 + $side_pad, $bl, $text, 8, false );
		$this->set_text_rgb( 0, 0, 0 );
		$this->y = (int) round( $bot - 7.0 );
	}

	/**
	 * Subtle line + centered tag under the main content.
	 */
	private function page_footer_line(): void {
		$this->ensure_space( 32 );
		$this->spacer( 2 );
		$yf = (float) $this->y;
		$this->stroke_hline( $yf, (float) $this->margin_left, (float) ( $this->page_w - $this->margin_right ), 0.88 );
		$this->y = (int) round( $yf - 5.0 );
		$msg   = 'Steinum Sport  ·  kvittan';
		$fs    = 7;
		$width = 130.0;
		$x     = ( (float) $this->page_w - $width ) / 2.0;
		$this->set_text_gray( 0.48 );
		$this->text_at( $x, (float) $this->y, $msg, $fs, false );
		$this->set_text_rgb( 0, 0, 0 );
		$this->y -= 10;
	}

	/**
	 * Section heading with tinted band, left accent bar, and bottom rule.
	 */
	private function section_block_title( string $title ): void {
		$this->spacer( 10 );
		$pad      = self::CARD_PAD;
		$ink      = self::helvetica_ink( 10, true );
		$band_h   = 2.0 * $pad + $ink['asc'] + $ink['desc'];
		$accent_w = 3.2;
		$inner_l  = (float) ( $this->margin_left + 8.0 + $accent_w );
		$this->ensure_space( (int) ( $band_h + 32 ) );
		$top = (float) $this->y;
		$bl  = $top - $pad - $ink['asc'];
		$bot = $bl - $ink['desc'] - $pad;
		$x0  = (float) $this->margin_left;
		$x1  = (float) ( $this->page_w - $this->margin_right );
		$this->rect_fill( $x0, $bot, $x1 - $x0, $band_h, 0.93, 0.95, 0.99 );
		$this->rect_fill( $x0, $bot, $accent_w, $band_h, 0.12, 0.3, 0.48 );
		$this->set_text_rgb( 0.1, 0.17, 0.3 );
		$this->text_at( $inner_l, $bl, $title, 10, true );
		$this->set_text_rgb( 0, 0, 0 );
		$this->stroke_hline( $bot, $x0, $x1, 0.82 );
		$this->y = (int) round( $bot - 10.0 );
	}

	/**
	 * One label + value in a soft “card” with a left accent bar.
	 */
	private function field_block( string $label, string $value ): void {
		$v     = ( '' === trim( $value ) ) ? '—' : $value;
		$lines = $this->wrap_text_lines( $v, 10, $this->value_indent );
		if ( ! $lines ) {
			$lines = array( '—' );
		}
		$pad_t  = self::CARD_PAD;
		$lab_h  = 9.0;
		$sp     = 2.0;
		$line_h = 12.0;
		$pad_b  = 5.0;
		$box_h  = $pad_t + $lab_h + $sp + ( count( $lines ) * $line_h ) + $pad_b;
		$this->ensure_space( (int) ( $box_h + 8 ) );
		$top  = (float) $this->y;
		$bot  = $top - $box_h;
		$x0   = (float) $this->margin_left;
		$x1   = (float) ( $this->page_w - $this->margin_right );
		$this->rect_fill( $x0, $bot, $x1 - $x0, $box_h, 0.978, 0.988, 0.998 );
		$this->rect_fill( $x0, $bot, 2.8, $box_h, 0.14, 0.3, 0.5 );
		$this->rect_stroke( $x0, $bot, $x1 - $x0, $box_h, 0.22, 0.86, 0.89, 0.92 );
		$ty   = $top - $pad_t - 6.0;
		$txl  = (float) ( $x0 + 8.0 );
		$txv  = (float) ( $this->margin_left + $this->value_indent );
		$this->set_text_rgb( 0.2, 0.26, 0.32 );
		$this->text_at( $txl, $ty, $label, 8, true );
		$ty -= ( $lab_h + $sp );
		$this->set_text_gray( 0.2 );
		foreach ( $lines as $ln ) {
			$this->text_at( $txv, $ty, $ln, 10, false );
			$ty -= $line_h;
		}
		$this->set_text_rgb( 0, 0, 0 );
		$this->y = (int) round( $bot - 6.0 );
	}

	private function text_at( float $x, float $y, string $text, int $size, bool $bold ): void {
		$font = $bold ? 'F2' : 'F1';
		$esc  = self::encode( $text );
		$this->stream .= 'BT' . "\n" . '/' . $font . ' ' . (string) (int) $size . " Tf\n"
			. $x . ' ' . $y . " Td\n"
			. '(' . $esc . ") Tj\n"
			. "ET\n";
	}

	/**
	 * @param float $g Gray 0…1 (stroke colour).
	 */
	private function stroke_hline( float $y, float $x1, float $x2, float $g = 0.7 ): void {
		$g  = max( 0.0, min( 1.0, $g ) );
		$w  = 0.4;
		$this->stream .= "q\n{$w} w\n"
			. sprintf( "%.2f", $g ) . ' ' . sprintf( "%.2f", $g ) . ' ' . sprintf( "%.2f", $g ) . " RG\n"
			. $x1 . ' ' . $y . " m " . $x2 . ' ' . $y . " l S\nQ\n";
	}

	/**
	 * @param float $g Gray stroke, 0…1
	 */
	private function stroke_vline( float $x, float $y1, float $y2, float $g = 0.7 ): void {
		if ( $y1 > $y2 ) {
			$t  = $y1;
			$y1 = $y2;
			$y2 = $t;
		}
		$g = max( 0.0, min( 1.0, $g ) );
		$w = 0.25;
		$this->stream .= "q\n{$w} w\n"
			. sprintf( "%.2f", $g ) . ' ' . sprintf( "%.2f", $g ) . ' ' . sprintf( "%.2f", $g ) . " RG\n"
			. $x . ' ' . $y1 . " m " . $x . ' ' . $y2 . " l S\nQ\n";
	}

	/**
	 * Filled axis-aligned rectangle (lower-left at x, y; y increases up the page).
	 */
	private function rect_fill( float $x, float $y, float $w, float $h, float $r, float $g, float $b ): void {
		if ( $w <= 0.0 || $h <= 0.0 ) {
			return;
		}
		$this->stream .= sprintf(
			"q\n%.3f %.3f %.3f rg\n%.2f %.2f %.2f %.2f re\nf\nQ\n",
			$r,
			$g,
			$b,
			$x,
			$y,
			$w,
			$h
		);
	}

	/**
	 * Stroked rectangle (same corner convention as {@see rect_fill}).
	 */
	private function rect_stroke( float $x, float $y, float $w, float $h, float $sw, float $r, float $g, float $b ): void {
		if ( $w <= 0.0 || $h <= 0.0 ) {
			return;
		}
		$sw = max( 0.1, $sw );
		$this->stream .= sprintf(
			"q\n%.2f w\n%.3f %.3f %.3f RG\n%.2f %.2f %.2f %.2f re\nS\nQ\n",
			$sw,
			$r,
			$g,
			$b,
			$x,
			$y,
			$w,
			$h
		);
	}

	/**
	 * Billing e-mail in a light panel with a thin border.
	 */
	private function billing_email_callout( string $value ): void {
		$lines = $this->wrap_text_lines( $value, 11, $this->value_indent );
		if ( ! $lines ) {
			$lines = array( '—' );
		}
		$pad     = self::CARD_PAD;
		$ink     = self::helvetica_ink( 11, true );
		$line_st = 12.0;
		$pad_h   = 4.0;
		$n       = count( $lines );
		$box_h_est = 2.0 * $pad + $ink['asc'] + $ink['desc'] + ( (float) ( $n - 1 ) * $line_st );
		$this->ensure_space( (int) ( $box_h_est + 16.0 ) );
		$top  = (float) $this->y;
		$bl   = $top - $pad - $ink['asc'];
		$bot  = $bl - ( (float) ( $n - 1 ) * $line_st ) - $ink['desc'] - $pad;
		$box_h = $top - $bot;
		$x0   = (float) ( $this->margin_left + $this->value_indent - $pad_h - 1.0 );
		$xr   = (float) ( $this->page_w - $this->margin_right );
		$w    = $xr - $x0;
		$this->rect_fill( $x0, $bot, $w, $box_h, 0.9, 0.95, 0.99 );
		$this->rect_stroke( $x0, $bot, $w, $box_h, 0.38, 0.55, 0.68, 0.88 );
		$this->rect_fill( $x0, $bot + $box_h - 1.4, $w, 1.4, 0.7, 0.88, 0.99 );
		$cy  = (float) $bl;
		$tx  = (float) ( $this->margin_left + $this->value_indent );
		$this->set_text_rgb( 0.08, 0.22, 0.5 );
		foreach ( $lines as $ln ) {
			$this->text_at( $tx, $cy, $ln, 11, true );
			$cy -= (float) $line_st;
		}
		$this->set_text_rgb( 0, 0, 0 );
		$this->y = (int) round( $bot - 5.0 );
	}

	/**
	 * @return array<int, string>
	 */
	private function wrap_text_lines( string $text, int $size, int $indent = 0 ): array {
		$text_w = ( $this->page_w - $this->margin_left - $this->margin_right - $indent );
		$char_w = ( $size * 0.48 );
		$max    = (int) floor( $text_w / $char_w );
		if ( $max < 12 ) {
			$max = 12;
		}
		$words  = explode( ' ', $text );
		$cur    = '';
		$lines  = array();
		foreach ( $words as $w ) {
			$try = ( '' === $cur ) ? $w : ( $cur . ' ' . $w );
			if ( self::mb_len( $try ) > $max && '' !== $cur ) {
				$lines[] = $cur;
				$cur     = $w;
			} else {
				$cur = $try;
			}
		}
		if ( '' !== $cur ) {
			$lines[] = $cur;
		}
		return $lines;
	}

	private function truncate_for_cell( string $text, int $width_points ): string {
		$max = (int) max( 2, floor( (float) $width_points / 4.2 ) );
		$len = self::mb_len( $text );
		if ( $len <= $max ) {
			return $text;
		}
		if ( function_exists( 'mb_substr' ) ) {
			return (string) mb_substr( $text, 0, $max - 1, 'UTF-8' ) . '…';
		}
		return (string) substr( $text, 0, $max - 1 ) . '…';
	}

	private function add_object( string $body ): int {
		$this->objects[] = $body;
		return count( $this->objects );
	}

	private function start_page(): void {
		if ( '' !== $this->stream ) {
			$this->flush_page();
		}
		$this->stream = '';
		$this->y     = $this->page_h - $this->margin_top;
		$this->rect_fill( 0.0, 0.0, (float) $this->page_w, (float) $this->page_h, 0.99, 0.993, 0.997 );
	}

	/** Thin full-bleed bar at the physical top of the page. */
	private function page_top_brand_bar(): void {
		$h = 3.2;
		$this->rect_fill( 0.0, (float) ( $this->page_h - $h ), (float) $this->page_w, $h, 0.12, 0.28, 0.5 );
	}

	private function flush_page(): void {
		$content    = $this->stream;
		$content_id = $this->add_object(
			'<< /Length ' . strlen( $content ) . " >>\nstream\n" . $content . "\nendstream"
		);
		$page_id = $this->add_object(
			'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . $this->page_w . ' ' . $this->page_h . ']'
			. ' /Resources << /Font << /F1 ' . $this->font_regular_id . ' 0 R'
			. ' /F2 ' . $this->font_bold_id . ' 0 R >> >>'
			. ' /Contents ' . $content_id . ' 0 R >>'
		);
		$this->page_ids[] = $page_id;
		$this->stream     = '';
	}

	/** Top of the receipt: main title + brand subtitle (light blue), then hairlines. */
	private function heading_receipt(): void {
		$this->ensure_space( 46 );
		$this->set_text_rgb( 0.08, 0.12, 0.2 );
		$this->line( 'Bíllegingarváttan', 19, true, 0, false, true );
		$this->set_text_rgb( 0, 0, 0 );
		$this->spacer( 3 );
		$this->set_text_rgb( 0.32, 0.51, 0.72 );
		$this->line( 'Steinum Sport', 11, false, 0, true );
		$this->set_text_rgb( 0, 0, 0 );
		$this->spacer( 7 );
		$this->hr_double();
		$this->spacer( 12 );
	}

	/** Main title hairlines (subtle “depth”). */
	private function hr_double(): void {
		$x1 = (float) $this->margin_left;
		$x2 = (float) ( $this->page_w - $this->margin_right );
		$y0 = (float) $this->y;
		$this->stream .= "q\n0.35 w\n0.7 0.78 0.9 RG\n{$x1} {$y0} m {$x2} {$y0} l S\nQ\n";
		$this->y -= 1;
		$y1 = (float) $this->y;
		$this->stream .= "q\n0.3 w\n0.88 0.9 0.95 RG\n{$x1} {$y1} m {$x2} {$y1} l S\nQ\n";
	}

	/**
	 * @param int  $indent   Extra x offset.
	 * @param bool $tight    Tighter line gap (meta lines).
	 * @param bool $heading  Title line: slightly looser.
	 */
	private function line( string $text, int $size, bool $bold, int $indent = 0, bool $tight = false, bool $heading = false ): void {
		$gap  = ( $tight || $heading ) ? ( $size + 2 + (int) ( $heading ? 2 : 0 ) ) : ( $size + 3 );
		$this->ensure_space( $gap );
		$font         = $bold ? 'F2' : 'F1';
		$x            = $this->margin_left + $indent;
		$y            = $this->y;
		$esc          = self::encode( $text );
		$this->stream .= "BT\n/{$font} {$size} Tf\n{$x} {$y} Td\n({$esc}) Tj\nET\n";
		$step         = ( $tight && ! $heading ) ? ( $size + 2 ) : ( $size + 3 + (int) ( $heading ? 1 : 0 ) );
		$this->y     -= $step;
	}

	/**
	 * @param int $indent Extra x offset (hanging value under a label).
	 */
	private function wrapped( string $text, int $size, bool $bold, int $indent = 0 ): void {
		$lines = $this->wrap_text_lines( $text, $size, $indent );
		foreach ( $lines as $ln ) {
			$this->line( $ln, $size, $bold, $indent );
		}
	}

	private function set_text_gray( float $g ): void {
		$g = max( 0.0, min( 1.0, $g ) );
		$this->stream .= sprintf( "%.3f g\n", $g );
	}

	private function set_text_rgb( float $r, float $g, float $b ): void {
		$this->stream .= sprintf( "%.3f %.3f %.3f rg\n", $r, $g, $b );
	}

	private function spacer( int $h ): void {
		$this->y -= $h;
	}

	private function ensure_space( int $h ): void {
		if ( $this->y - $h < $this->margin_bottom ) {
			$this->start_page();
		}
	}

	private function finish(): string {
		$this->flush_page();

		$this->objects[0] = '<< /Type /Catalog /Pages 2 0 R >>';
		$kids             = implode(
			' ',
			array_map( static fn( $id ): string => $id . ' 0 R', $this->page_ids )
		);
		$count            = count( $this->page_ids );
		$this->objects[1] = "<< /Type /Pages /Kids [{$kids}] /Count {$count} >>";

		$out     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
		$offsets = array();
		foreach ( $this->objects as $i => $body ) {
			$offsets[ $i + 1 ] = strlen( $out );
			$out              .= ( $i + 1 ) . " 0 obj\n" . $body . "\nendobj\n";
		}
		$xref_pos = strlen( $out );
		$size     = count( $this->objects ) + 1;
		$out     .= "xref\n0 {$size}\n";
		$out     .= sprintf( "%010d %05d f \n", 0, 65535 );
		for ( $i = 1; $i < $size; $i++ ) {
			$out .= sprintf( "%010d %05d n \n", $offsets[ $i ], 0 );
		}
		$out .= "trailer\n<< /Size {$size} /Root 1 0 R >>\nstartxref\n{$xref_pos}\n%%EOF\n";
		return $out;
	}

	/**
	 * UTF-8 → WinAnsi (CP1252) with PDF string escaping.
	 */
	public static function encode( string $text ): string {
		$converted = false;
		if ( function_exists( 'iconv' ) ) {
			$converted = @iconv( 'UTF-8', 'CP1252//TRANSLIT//IGNORE', $text );
		}
		if ( false === $converted ) {
			$converted = function_exists( 'mb_convert_encoding' )
				? (string) mb_convert_encoding( $text, 'CP1252', 'UTF-8' )
				: $text;
		}
		return strtr( (string) $converted, array( '\\' => '\\\\', '(' => '\\(', ')' => '\\)' ) );
	}

	private static function mb_len( string $s ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $s, 'UTF-8' ) : strlen( $s );
	}
}
