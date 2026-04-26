<?php
/**
 * Test bootstrap: load WP stubs + plugin source.
 *
 * @package Steinum_Sport_Clothes
 */

require_once __DIR__ . '/wp-stubs.php';

/** Disable HTTP redirects when called from CLI. */
if ( ! defined( 'SSC_NO_HTTP_REDIRECT' ) ) {
	define( 'SSC_NO_HTTP_REDIRECT', true );
}

ssc_test_reset();

require_once dirname( __DIR__ ) . '/steinum-sport-clothes.php';

SSC_Store::maybe_install();
