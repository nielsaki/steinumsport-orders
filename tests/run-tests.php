<?php
/**
 * CLI test runner. Run from plugin root:
 *
 *   php tests/run-tests.php
 *
 * Exits 0 on success, 1 on any failure.
 *
 * @package Steinum_Sport_Clothes
 */

require_once __DIR__ . '/bootstrap.php';

global $ssc_test_assertions, $ssc_test_failures;
$ssc_test_assertions = 0;
$ssc_test_failures   = array();

function ssc_assert_true( $cond, string $msg = 'expected true' ): void {
	global $ssc_test_assertions;
	$ssc_test_assertions++;
	if ( ! $cond ) {
		throw new RuntimeException( $msg );
	}
}
function ssc_assert_false( $cond, string $msg = 'expected false' ): void {
	ssc_assert_true( ! $cond, $msg );
}
function ssc_assert_eq( $expected, $actual, string $msg = '' ): void {
	global $ssc_test_assertions;
	$ssc_test_assertions++;
	if ( $expected !== $actual ) {
		throw new RuntimeException(
			'Expected ' . var_export( $expected, true )
			. ' but got ' . var_export( $actual, true )
			. ( $msg ? " — $msg" : '' )
		);
	}
}
function ssc_assert_contains( string $needle, string $haystack, string $msg = '' ): void {
	global $ssc_test_assertions;
	$ssc_test_assertions++;
	if ( false === strpos( $haystack, $needle ) ) {
		throw new RuntimeException(
			'Substring not found: «' . $needle . '»'
			. ( $msg ? " — $msg" : '' )
		);
	}
}

require_once __DIR__ . '/test-cases.php';

$test_funcs = array_values(
	array_filter(
		get_defined_functions()['user'],
		static function ( string $f ): bool {
			return str_starts_with( $f, 'ssc_test_' )
				&& ! in_array( $f, array( 'ssc_test_reset' ), true );
		}
	)
);
sort( $test_funcs );

$start  = microtime( true );
$count  = 0;
$failed = 0;

foreach ( $test_funcs as $fn ) {
	$count++;
	try {
		$fn();
		echo "  ✓ {$fn}\n";
	} catch ( Throwable $e ) {
		$failed++;
		$ssc_test_failures[] = array( $fn, $e );
		echo "  ✗ {$fn}\n    " . $e->getMessage() . "\n";
	}
}

$elapsed = number_format( ( microtime( true ) - $start ) * 1000, 1 );
echo "\n";
echo "{$count} tests, {$ssc_test_assertions} assertions, {$failed} failed ({$elapsed} ms)\n";
$preview_dir = __DIR__ . '/preview-pdfs';
if ( 0 === $failed && is_dir( $preview_dir ) ) {
	$has_pdf  = (bool) glob( $preview_dir . '/*.pdf' );
	$has_xlsx = (bool) glob( $preview_dir . '/*.xlsx' );
	if ( $has_pdf || $has_xlsx ) {
		$kinds = array();
		if ( $has_pdf ) {
			$kinds[] = 'PDF';
		}
		if ( $has_xlsx ) {
			$kinds[] = 'Excel';
		}
		echo implode( ' + ', $kinds ) . ' previews: ' . $preview_dir . "\n";
	}
}

exit( $failed > 0 ? 1 : 0 );
