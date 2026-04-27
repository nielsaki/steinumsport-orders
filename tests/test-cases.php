<?php
/**
 * Standalone test cases (no PHPUnit). Each function returns void and uses
 * ssc_assert*() helpers from run-tests.php.
 *
 * @package Steinum_Sport_Clothes
 */

if ( ! defined( 'SSC_TESTING' ) ) {
	exit;
}

/**
 * Save PDF bytes under tests/preview-pdfs/ so you can open them after
 *   php tests/run-tests.php
 * Files are stable names (overwritten on each run). Not committed (see .gitignore).
 * (Named without the ssc_test_ prefix so run-tests.php does not treat it as a test case.)
 *
 * After a successful `ssc_test_submission_pipeline_persists_and_logs` run, open:
 * - last-submission.pdf
 * - last-submission.xlsx
 */
function ssc_preview_write_pdf( string $bytes, string $filename_base ): void {
	$dir = __DIR__ . '/preview-pdfs';
	if ( ! is_dir( $dir ) && ! @mkdir( $dir, 0775, true ) && ! is_dir( $dir ) ) {
		return;
	}
	$base = (string) preg_replace( '/[^a-z0-9._-]+/i', '-', $filename_base );
	$base = trim( $base, '-.' ) ?: 'preview';
	@file_put_contents( $dir . '/' . $base . '.pdf', $bytes );
}

/**
 * Copy generated .xlsx to tests/preview-pdfs/ for local inspection.
 */
function ssc_preview_copy_xlsx( string $src_path, string $filename_base ): void {
	if ( '' === $src_path || ! is_readable( $src_path ) ) {
		return;
	}
	$dir = __DIR__ . '/preview-pdfs';
	if ( ! is_dir( $dir ) && ! @mkdir( $dir, 0775, true ) && ! is_dir( $dir ) ) {
		return;
	}
	$base = (string) preg_replace( '/[^a-z0-9._-]+/i', '-', $filename_base );
	$base = trim( $base, '-.' ) ?: 'preview';
	@copy( $src_path, $dir . '/' . $base . '.xlsx' );
}

/* ------------------------------------------------------------------ */
/* SSC_Sanitizer                                                       */
/* ------------------------------------------------------------------ */

function ssc_test_sanitizer_trims_text(): void {
	$out = SSC_Sanitizer::sanitize(
		array(
			'club_name'     => "  Felag\nfoo\t  ",
			'boat_name'     => 'Bátur',
			'order_lines'   => array(
				array( 'item' => 'clothes', 'size' => 'M', 'qty' => 4, 'name' => '  Eva  ' ),
				array( 'item' => 'clothes', 'size' => 'L', 'qty' => 0, 'name' => '<b>x</b>' ),
			),
			'contact_name'  => 'Hans  Hansen',
			'contact_email'   => 'k@EXAMPLE.fo',
			'phone'         => '+298 12 34 56',
			'billing_email' => 'TEST@Example.COM',
		)
	);
	ssc_assert_eq( 'Felag foo', $out['club_name'], 'club_name collapsed whitespace' );
	$ol = $out['order_lines'];
	ssc_assert_true( is_array( $ol ) && count( $ol ) >= 1, 'order lines kept' );
	ssc_assert_eq( 'Eva', $ol[0]['name'] ?? 'x', 'name trimmed' );
	ssc_assert_eq( 'x', $ol[1]['name'] ?? '', 'html stripped in name' );
	ssc_assert_eq( 'test@example.com', $out['billing_email'], 'email lowercased' );
	ssc_assert_eq( 'k@example.fo', $out['contact_email'] ?? '', 'contact email lowercased' );
	ssc_assert_eq( '+298 12 34 56', $out['phone'], 'phone kept (only +, spaces, digits)' );
}

function ssc_test_sanitizer_phone_strips_to_allowed(): void {
	$out = SSC_Sanitizer::sanitize(
		array(
			'club_name'     => 'A',
			'boat_name'     => '',
			'order_lines'   => array(
				array( 'item' => 'trikot', 'gender' => 'women', 'size' => 'M', 'qty' => 1, 'name' => '' ),
			),
			'contact_name'  => 'C',
			'contact_email' => 'c@a.co',
			'phone'         => 'Mitt nummar +298 12 34 56',
			'billing_email' => 'a@b.co',
		)
	);
	ssc_assert_eq( '+298 12 34 56', $out['phone'] );
}

function ssc_test_sanitizer_validate_phone_requires_digit(): void {
	$errors = SSC_Sanitizer::validate(
		SSC_Sanitizer::sanitize(
			array(
				'club_name'     => 'A',
				'boat_name'     => '',
				'contact_name'  => 'C',
				'contact_email' => 'c@a.co',
				'phone'         => ' + + ',
				'billing_email' => 'a@b.co',
				'order_lines'   => array(
					array( 'item' => 'trikot', 'gender' => 'women', 'size' => 'M', 'qty' => 1, 'name' => '' ),
				),
			)
		)
	);
	ssc_assert_true( in_array( 'phone', $errors, true ), 'phone must contain at least one digit' );
}

function ssc_test_sanitizer_validate_required(): void {
	$errors = SSC_Sanitizer::validate( SSC_Sanitizer::sanitize( array() ) );
	foreach ( array( 'club_name', 'contact_name', 'contact_email', 'phone', 'billing_email' ) as $key ) {
		ssc_assert_true( in_array( $key, $errors, true ), "missing $key flagged" );
	}
	ssc_assert_false( in_array( 'boat_name', $errors, true ), 'boat_name is optional' );
}

function ssc_test_sanitizer_validate_invalid_email(): void {
	$errors = SSC_Sanitizer::validate(
		SSC_Sanitizer::sanitize(
			array(
				'club_name'     => 'A',
				'boat_name'     => '',
				'contact_name'  => 'C',
				'contact_email'  => 'ok@a.co',
				'phone'         => '1',
				'billing_email' => 'not-an-email',
			)
		)
	);
	ssc_assert_true( in_array( 'billing_email', $errors, true ), 'bad email flagged' );
}

function ssc_test_sanitizer_validate_ok(): void {
	$errors = SSC_Sanitizer::validate(
		SSC_Sanitizer::sanitize(
			array(
				'club_name'     => 'A',
				'boat_name'     => '',
				'contact_name'  => 'C',
				'contact_email'  => 'c@a.co',
				'phone'         => '1',
				'billing_email' => 'a@b.co',
				'order_lines'   => array(
					array( 'item' => 'clothes', 'size' => 'M', 'qty' => 1, 'name' => '' ),
				),
			)
		)
	);
	ssc_assert_eq( array(), $errors, 'no errors on valid input' );
}

/* ------------------------------------------------------------------ */
/* SSC_Email_Builder                                                   */
/* ------------------------------------------------------------------ */

function ssc_test_email_builder_admin_includes_all_fields(): void {
	$labels = SSC_Sanitizer::labels();
	$data   = SSC_Form::sample_data( 'a@b.co' );
	$body   = SSC_Email_Builder::admin_body( $data, $labels, 'Steinum Sport' );

	ssc_assert_contains( 'Steinum Sport', $body, 'site name in admin body' );
	foreach ( $labels as $label ) {
		ssc_assert_contains( $label . ':', $body, 'label «' . $label . '» appears' );
	}
	ssc_assert_contains( 'Kappróðrarfelag Havnar', $body, 'club value present' );
}

function ssc_test_email_builder_receipt_has_intro_and_footer(): void {
	$labels = SSC_Sanitizer::labels();
	$data   = SSC_Form::sample_data( 'kunda@example.com' );
	$body   = SSC_Email_Builder::receipt_body( $data, $labels, 'Halló og takk fyri tilkunnina.' );

	ssc_assert_contains( 'Halló og takk', $body );
	ssc_assert_contains( 'samandráttur', $body );
	ssc_assert_contains( 'PDF-kvittan er viðheft', $body );
}

/* ------------------------------------------------------------------ */
/* SSC_PDF                                                             */
/* ------------------------------------------------------------------ */

function ssc_test_pdf_output_is_valid_header_and_eof(): void {
	$pdf  = ( new SSC_PDF() )->render(
		SSC_Form::sample_data( 'a@b.co' ),
		SSC_Sanitizer::labels(),
		'Steinum Sport — Klæðir',
		array( 'Dato' => '2026-04-24' )
	);

	ssc_assert_true( str_starts_with( $pdf, '%PDF-1.4' ), 'PDF header' );
	ssc_assert_true( str_contains( $pdf, '%%EOF' ), 'PDF EOF marker' );
	ssc_assert_true( strlen( $pdf ) > 1000, 'PDF non-trivial size' );
}

function ssc_test_pdf_encodes_faroese_characters(): void {
	ssc_assert_eq( "F\xF8royar", SSC_PDF::encode( 'Føroyar' ), 'CP1252 byte for ø' );
	ssc_assert_eq( '\\(test\\)', SSC_PDF::encode( '(test)' ), 'parens escaped' );
}

/* ------------------------------------------------------------------ */
/* SSC_Order_Excel                                                     */
/* ------------------------------------------------------------------ */

function ssc_test_order_excel_spreadsheetml_layout(): void {
	$xml = SSC_Order_Excel::to_spreadsheetml( SSC_Form::sample_data( 'a@b.co' ) );
	ssc_assert_contains( 'QTY', $xml );
	ssc_assert_contains( 'PRODUCT', $xml );
	ssc_assert_contains( '<Data ss:Type="String">NOTES</Data>', $xml, 'G4 NOTES header' );
	ssc_assert_contains( 'Name of club', $xml );
	ssc_assert_contains( 'Name of boat', $xml );
	ssc_assert_contains( 'RedPlain', $xml );
	ssc_assert_contains( 'GrayBlack', $xml );
	ssc_assert_contains( 'ss:FontName="Arial"', $xml, 'Arial' );
	ssc_assert_contains( 'ss:Size="11"', $xml, '11pt' );
	ssc_assert_contains( 'ss:Bold="1"', $xml, 'bold' );
	ssc_assert_contains( 'ss:DefaultColumnWidth="65"', $xml, 'default column width 65' );
	ssc_assert_contains( 'ss:Index="1" ss:AutoFitWidth="0" ss:Width="65"', $xml, 'col A width 65' );
	ssc_assert_contains( 'ss:Index="5" ss:AutoFitWidth="0" ss:Width="95"', $xml, 'col E width 95' );
	ssc_assert_contains( 'ss:Index="32" ss:AutoFitWidth="0" ss:Width="65"', $xml, 'last col width 65' );
	ssc_assert_false( str_contains( $xml, 'Kvinnur' ), 'NOTES column empty — no kyn text' );
	ssc_assert_contains( '<?mso-application progid="Excel.Sheet"?>', $xml );
	// Sample lines: trikot (women), t-shirt, SpeedCoach (last omitted from Excel table).
	ssc_assert_contains( 'Womens Singlet', $xml );
	ssc_assert_contains( 'Profit Tshirt', $xml );
	ssc_assert_false( str_contains( $xml, 'SpeedCoach' ), 'SpeedCoach not in Excel' );

	$men_rg = SSC_Form::sample_data( 'a@b.co' );
	$men_rg['order_lines'] = array(
		array(
			'item'   => SSC_Sanitizer::ITEM_TRIKOT,
			'gender' => SSC_Sanitizer::GENDER_MEN,
			'size'   => 'M',
			'qty'    => 1,
			'name'   => '',
		),
		array(
			'item'   => SSC_Sanitizer::ITEM_RASHGUARD,
			'gender' => '',
			'size'   => 'L',
			'qty'    => 1,
			'name'   => '',
		),
	);
	$xml2 = SSC_Order_Excel::to_spreadsheetml( $men_rg );
	ssc_assert_contains( 'Mens Singlet', $xml2 );
	ssc_assert_contains( 'Rashguards', $xml2 );

	$no_name = SSC_Form::sample_data( 'a@b.co' );
	$no_name['order_lines'] = array(
		array(
			'item'   => SSC_Sanitizer::ITEM_TSHIRT,
			'gender' => '',
			'size'   => 'M',
			'qty'    => 1,
			'name'   => '',
		),
	);
	$xml3 = SSC_Order_Excel::to_spreadsheetml( $no_name );
	ssc_assert_false( str_contains( $xml3, '<Data ss:Type="String">—</Data>' ), 'ANYNAME empty, not em dash' );
}

/* ------------------------------------------------------------------ */
/* SSC_Store (DB)                                                      */
/* ------------------------------------------------------------------ */

function ssc_test_store_insert_and_find(): void {
	ssc_test_reset();
	SSC_Store::maybe_install();

	$id = SSC_Store::insert( SSC_Form::sample_data( 'a@b.co' ), 'BODY' );
	ssc_assert_true( $id > 0, 'returns row id' );

	$row = SSC_Store::find( $id );
	ssc_assert_true( is_array( $row ), 'find returns row' );
	ssc_assert_eq( 'Kappróðrarfelag Havnar', $row['club_name'] );
	ssc_assert_eq( 'received', $row['status'] );
	ssc_assert_eq( 'BODY', $row['email_body'] );
	ssc_assert_eq( 'hans@example.com', (string) ( $row['contact_email'] ?? '' ) );
}

function ssc_test_store_filter_by_status_and_search(): void {
	ssc_test_reset();
	SSC_Store::maybe_install();

	$id1 = SSC_Store::insert( array_merge( SSC_Form::sample_data( 'a@b.co' ), array( 'club_name' => 'Klakksvík' ) ), '' );
	$id2 = SSC_Store::insert( array_merge( SSC_Form::sample_data( 'c@d.co' ), array( 'club_name' => 'Tórshavn' ) ), '' );
	SSC_Store::set_status( $id2, SSC_Store::STATUS_DELIVERED );

	$res = SSC_Store::all( array( 'status' => 'received' ) );
	ssc_assert_eq( 1, $res['total'], 'only received counted' );
	ssc_assert_eq( 'Klakksvík', $res['rows'][0]['club_name'] );

	$res = SSC_Store::all( array( 'search' => 'Tórs' ) );
	ssc_assert_eq( 1, $res['total'], 'search match' );
	ssc_assert_eq( 'Tórshavn', $res['rows'][0]['club_name'] );
}

function ssc_test_store_status_changes(): void {
	ssc_test_reset();
	SSC_Store::maybe_install();

	$id = SSC_Store::insert( SSC_Form::sample_data( 'a@b.co' ), '' );
	ssc_assert_true( SSC_Store::set_status( $id, SSC_Store::STATUS_PROCESSING, 'Bílag-nr 42' ) );
	ssc_assert_false( SSC_Store::set_status( $id, 'bogus' ), 'invalid status rejected' );

	$row = SSC_Store::find( $id );
	ssc_assert_eq( 'processing', $row['status'] );
	ssc_assert_eq( 'Bílag-nr 42', $row['note'] );
}

function ssc_test_store_delete_and_purge(): void {
	ssc_test_reset();
	SSC_Store::maybe_install();

	$ids = array();
	for ( $i = 0; $i < 5; $i++ ) {
		$ids[] = SSC_Store::insert( SSC_Form::sample_data( "u$i@x.co" ), '' );
	}
	ssc_assert_true( SSC_Store::delete( $ids[0] ) );
	ssc_assert_eq( 2, SSC_Store::delete_many( array( $ids[1], $ids[2] ) ) );
	ssc_assert_eq( 2, SSC_Store::all()['total'], 'two left' );

	SSC_Store::purge_older_than( 0 );
	ssc_assert_eq( 2, SSC_Store::all()['total'], 'purge with 0 days is a no-op' );
}

function ssc_test_store_disabled_via_filter(): void {
	ssc_test_reset();
	SSC_Store::maybe_install();
	add_filter( 'ssc_store_submission', '__return_false' );

	$id = SSC_Store::insert( SSC_Form::sample_data( 'a@b.co' ), '' );
	ssc_assert_eq( 0, $id, 'returns 0 when filter disables storage' );
	ssc_assert_eq( 0, SSC_Store::all()['total'] );
}

/* ------------------------------------------------------------------ */
/* SSC_Mail / SSC_Logger                                               */
/* ------------------------------------------------------------------ */

function ssc_test_mail_logs_in_test_mode(): void {
	ssc_test_reset();

	if ( ! defined( 'SSC_EMAIL_TEST_MODE' ) ) {
		define( 'SSC_EMAIL_TEST_MODE', true );
	}
	if ( ! defined( 'SSC_EMAIL_DRY_RUN' ) ) {
		define( 'SSC_EMAIL_DRY_RUN', true );
	}
	if ( ! defined( 'SSC_EMAIL_LOG_FILE' ) ) {
		define( 'SSC_EMAIL_LOG_FILE', sys_get_temp_dir() . '/ssc-test-email.log' );
	}
	SSC_Logger::clear();

	$ok = SSC_Mail::send( 'real@target.test', 'Hey', 'Body' );
	ssc_assert_true( $ok, 'dry-run returns true' );

	$log = SSC_Logger::tail( 8000 );
	ssc_assert_contains( 'PRÓV', $log, 'test banner present in subject' );
	ssc_assert_contains( 'real@target.test', $log, 'orig recipient logged' );
}

/* ------------------------------------------------------------------ */
/* SSC_Submission (full pipeline)                                      */
/* ------------------------------------------------------------------ */

function ssc_test_submission_pipeline_persists_and_logs(): void {
	ssc_test_reset();
	SSC_Store::maybe_install();
	SSC_Logger::clear();

	$pdf_dir = rtrim( (string) WP_CONTENT_DIR, '/\\' ) . '/uploads/steinum-sport-clothes';
	$before  = is_dir( $pdf_dir ) ? glob( $pdf_dir . '/*.pdf' ) : array();
	if ( ! is_array( $before ) ) {
		$before = array();
	}
	$before_xlsx = is_dir( $pdf_dir ) ? glob( $pdf_dir . '/*-order.xlsx' ) : array();
	if ( ! is_array( $before_xlsx ) ) {
		$before_xlsx = array();
	}

	$ok = ( new SSC_Submission() )->handle( SSC_Form::sample_data( 'kunda@example.com' ) );
	ssc_assert_true( $ok, 'handle returns true' );

	$res = SSC_Store::all();
	ssc_assert_eq( 1, $res['total'], '1 submission persisted' );
	$row0 = $res['rows'][0] ?? array();
	ssc_assert_true(
		! empty( $row0['pdf_path'] ) && is_readable( (string) $row0['pdf_path'] ),
		'pdf_path persisted and readable'
	);

	$log = SSC_Logger::tail( 12000 );
	ssc_assert_contains( 'TYPE:    Admin tilkunn', $log );
	ssc_assert_contains( 'TYPE:    Kvittan (kontakt)', $log, 'contact receipt mail logged' );
	ssc_assert_contains( '.pdf', $log, 'PDF attachment logged' );
	ssc_assert_contains( 'order.', $log, 'admin Excel attachment logged' );

	// Full pipeline: PDF bytes + write to disk (not only in-memory render).
	ssc_assert_true( is_dir( $pdf_dir ), 'PDF upload subdir created' );
	$after = glob( $pdf_dir . '/*.pdf' );
	if ( ! is_array( $after ) ) {
		$after = array();
	}
	$added = array_values( array_diff( $after, $before ) );
	$path  = $added[0] ?? '';
	if ( '' === $path && $after ) {
		usort(
			$after,
			static function ( $a, $b ) {
				return ( (int) @filemtime( $b ) ) <=> ( (int) @filemtime( $a ) );
			}
		);
		$path = (string) ( $after[0] ?? '' );
	}
	ssc_assert_true( $path !== '' && is_readable( $path ), 'receipt PDF file on disk' );
	$bytes = (string) file_get_contents( $path );
	ssc_assert_true( str_starts_with( $bytes, '%PDF' ), 'PDF file starts with %PDF' );
	ssc_assert_true( str_contains( $bytes, '%%EOF' ), 'PDF has EOF' );
	ssc_assert_true( strlen( $bytes ) > 200, 'PDF non-trivial size' );
	ssc_preview_write_pdf( $bytes, 'last-submission' );

	$after_xlsx = glob( $pdf_dir . '/*-order.xlsx' );
	if ( ! is_array( $after_xlsx ) ) {
		$after_xlsx = array();
	}
	$added_xlsx = array_values( array_diff( $after_xlsx, $before_xlsx ) );
	$xlsx_path  = (string) ( $added_xlsx[0] ?? '' );
	if ( '' === $xlsx_path && $after_xlsx ) {
		usort(
			$after_xlsx,
			static function ( $a, $b ) {
				return ( (int) @filemtime( $b ) ) <=> ( (int) @filemtime( $a ) );
			}
		);
		$xlsx_path = (string) ( $after_xlsx[0] ?? '' );
	}
	if ( $xlsx_path !== '' && is_readable( $xlsx_path ) ) {
		$xlsx_head = (string) file_get_contents( $xlsx_path, false, null, 0, 4 );
		ssc_assert_eq( 'PK' . chr( 0x03 ) . chr( 0x04 ), $xlsx_head, 'order file is a ZIP (.xlsx)' );
		if ( class_exists( 'ZipArchive' ) ) {
			$z = new ZipArchive();
			ssc_assert_true( $z->open( $xlsx_path ) === true, 'open xlsx zip' );
			$sheet = (string) $z->getFromName( 'xl/worksheets/sheet1.xml' );
			$z->close();
			ssc_assert_contains( 'QTY', $sheet, 'xlsx sheet has QTY' );
			ssc_assert_contains( 'PRODUCT', $sheet, 'xlsx sheet has PRODUCT' );
			ssc_assert_false( str_contains( $sheet, 'SpeedCoach' ), 'SpeedCoach not in pipeline Excel' );
			ssc_assert_false( str_contains( $sheet, 'NK Stopur' ), 'NK Stopur not in pipeline Excel' );
		}
		ssc_preview_copy_xlsx( $xlsx_path, 'last-submission' );
	} else {
		$after_xml = glob( $pdf_dir . '/*-order.xml' );
		if ( ! is_array( $after_xml ) ) {
			$after_xml = array();
		}
		$xml_path = (string) ( $after_xml[0] ?? '' );
		ssc_assert_true( $xml_path !== '' && is_readable( $xml_path ), 'order spreadsheet (.xlsx or fallback .xml) on disk' );
		$xml_bytes = (string) file_get_contents( $xml_path );
		ssc_assert_contains( '<?mso-application progid="Excel.Sheet"?>', $xml_bytes, 'fallback xml is SpreadsheetML' );
	}
}
