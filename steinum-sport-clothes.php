<?php
/**
 * Plugin Name:       Steinum Sport — Klæðir
 * Description:       Form fyri at taka ímóti tilkunnum um klæðir til kappróðrarbátar. Sendir admin-tilkunn + PDF-kvittan til kundan og goymir tilkunnirnar í dátagrunninum.
 * Version:           2.4.5
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Niels Áki Mørk, Steinum Sport
 * License:           GPL-2.0-or-later
 * Text Domain:       steinum-sport-clothes
 * Domain Path:       /languages
 *
 * @package Steinum_Sport_Clothes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SSC_VERSION', '2.4.5' );
define( 'SSC_FILE', __FILE__ );
define( 'SSC_DIR', plugin_dir_path( __FILE__ ) );
define( 'SSC_URL', plugin_dir_url( __FILE__ ) );

require_once SSC_DIR . 'includes/class-ssc-sanitizer.php';
require_once SSC_DIR . 'includes/class-ssc-email-builder.php';
require_once SSC_DIR . 'includes/class-ssc-pdf.php';
require_once SSC_DIR . 'includes/class-ssc-order-excel.php';
require_once SSC_DIR . 'includes/class-ssc-logger.php';
require_once SSC_DIR . 'includes/class-ssc-mail.php';
require_once SSC_DIR . 'includes/class-ssc-store.php';
require_once SSC_DIR . 'includes/class-ssc-submission.php';
require_once SSC_DIR . 'includes/class-ssc-form.php';
require_once SSC_DIR . 'includes/class-ssc-settings.php';
require_once SSC_DIR . 'includes/admin/class-ssc-admin-list-table.php';
require_once SSC_DIR . 'includes/admin/class-ssc-admin-submissions.php';
require_once SSC_DIR . 'includes/class-ssc-plugin.php';

( new SSC_Plugin() )->register();
