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
	private SSC_Admin_Order_Items $admin_order_items;

	public function __construct() {
		$this->form               = new SSC_Form();
		$this->settings           = new SSC_Settings();
		$this->admin_submissions  = new SSC_Admin_Submissions();
		$this->admin_order_items  = new SSC_Admin_Order_Items();
	}

	public function register(): void {
		register_activation_hook( SSC_FILE, array( $this, 'on_activate' ) );

		add_action( 'plugins_loaded', array( SSC_Store::class, 'maybe_install' ) );

		$this->form->register();
		$this->settings->register();
		$this->admin_submissions->register();
		$this->admin_order_items->register();

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	public function on_activate(): void {
		SSC_Store::maybe_install();
	}

	/**
	 * Submenu rows under Steinum Sport. Add entries here instead of duplicate add_submenu_page calls.
	 *
	 * Each entry: page_title (string), menu_title (string), slug (hook suffix), capability (string), callback (callable).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function admin_submenus(): array {
		$items = array(
			array(
				'page_title' => __( 'Fráboðanir', 'steinum-sport-clothes' ),
				'menu_title' => __( 'Fráboðanir', 'steinum-sport-clothes' ),
				'slug'       => SSC_Admin_Submissions::PAGE,
				'capability' => 'manage_options',
				'callback'   => array( $this->admin_submissions, 'render_page' ),
			),
			array(
				'page_title' => __( 'Stillingar', 'steinum-sport-clothes' ),
				'menu_title' => __( 'Stillingar', 'steinum-sport-clothes' ),
				'slug'       => SSC_Settings::PAGE,
				'capability' => 'manage_options',
				'callback'   => array( $this->settings, 'render_page' ),
			),
			array(
				'page_title' => __( 'Items', 'steinum-sport-clothes' ),
				'menu_title' => __( 'Items', 'steinum-sport-clothes' ),
				'slug'       => SSC_Admin_Order_Items::PAGE,
				'capability' => 'manage_options',
				'callback'   => array( $this->admin_order_items, 'render_page' ),
			),
		);

		/**
		 * Submenus under Steinum Sport (below the top-level item).
		 *
		 * @param array<int, array<string, mixed>> $items
		 */
		return apply_filters( 'ssc_admin_submenus', $items );
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

		foreach ( $this->admin_submenus() as $row ) {
			add_submenu_page(
				SSC_Admin_Submissions::PAGE,
				(string) $row['page_title'],
				(string) $row['menu_title'],
				isset( $row['capability'] ) ? (string) $row['capability'] : 'manage_options',
				(string) $row['slug'],
				$row['callback']
			);
		}
	}
}
