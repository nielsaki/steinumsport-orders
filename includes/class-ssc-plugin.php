<?php
/**
 * Plugin wiring: hooks + top-level admin menu.
 *
 * @package Steinum_Sport_Clothes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires every module into WordPress.
 */
class SSC_Plugin {

	private SSC_Form $form;
	private SSC_Settings $settings;
	private SSC_Admin_Submissions $admin_submissions;

	public function __construct() {
		$this->form              = new SSC_Form();
		$this->settings          = new SSC_Settings();
		$this->admin_submissions = new SSC_Admin_Submissions();
	}

	public function register(): void {
		register_activation_hook( SSC_FILE, array( $this, 'on_activate' ) );

		add_action( 'plugins_loaded', array( SSC_Store::class, 'maybe_install' ) );

		$this->form->register();
		$this->settings->register();
		$this->admin_submissions->register();

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	public function on_activate(): void {
		SSC_Store::maybe_install();
	}

	public function register_menu(): void {
		add_menu_page(
			__( 'Steinum Sport', 'steinum-sport-clothes' ),
			__( 'Steinum Sport', 'steinum-sport-clothes' ),
			'manage_options',
			SSC_Admin_Submissions::PAGE,
			array( $this->admin_submissions, 'render_page' ),
			'dashicons-tag',
			26
		);

		add_submenu_page(
			SSC_Admin_Submissions::PAGE,
			__( 'Fráboðanir', 'steinum-sport-clothes' ),
			__( 'Fráboðanir', 'steinum-sport-clothes' ),
			'manage_options',
			SSC_Admin_Submissions::PAGE,
			array( $this->admin_submissions, 'render_page' )
		);

		add_submenu_page(
			SSC_Admin_Submissions::PAGE,
			__( 'Stillingar', 'steinum-sport-clothes' ),
			__( 'Stillingar', 'steinum-sport-clothes' ),
			'manage_options',
			SSC_Settings::PAGE,
			array( $this->settings, 'render_page' )
		);
	}
}
