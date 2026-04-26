<?php
/**
 * WP_List_Table for submissions.
 *
 * @package Steinum_Sport_Clothes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	$ssc_wp_lt = ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	if ( is_file( $ssc_wp_lt ) ) {
		require_once $ssc_wp_lt;
	}
}

/**
 * Submissions list table.
 */
class SSC_Admin_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'fráboðan',
				'plural'   => 'fráboðanir',
				'ajax'     => false,
			)
		);
	}

	/** @return array<string, string> */
	public function get_columns() {
		return array(
			'cb'            => '<input type="checkbox" />',
			'created_at'    => 'Dato',
			'club_name'     => 'Felag',
			'boat_name'     => 'Bátur',
			'people'        => 'Nøgd (bíleggingar)',
			'contact_name'  => 'Kontakt',
			'billing_email' => 'Teldupostur',
			'pdf'           => 'PDF',
			'status'        => 'Støða',
		);
	}

	/** @return array<string, array{0:string,1:bool}> */
	protected function get_sortable_columns() {
		return array(
			'created_at' => array( 'created_at', true ),
			'club_name'  => array( 'club_name', false ),
			'status'     => array( 'status', false ),
		);
	}

	/** @return array<string, string> */
	public function get_bulk_actions() {
		return array( 'delete' => 'Strika' );
	}

	protected function get_views() {
		$base    = remove_query_arg( array( 'status', 'paged' ) );
		$current = isset( $_GET['status'] ) ? (string) $_GET['status'] : '';
		$views   = array(
			'all' => sprintf(
				'<a href="%s"%s>Allar</a>',
				esc_url( $base ),
				'' === $current ? ' class="current"' : ''
			),
		);
		foreach ( SSC_Store::statuses() as $key => $label ) {
			$views[ $key ] = sprintf(
				'<a href="%s"%s>%s</a>',
				esc_url( add_query_arg( 'status', $key, $base ) ),
				$current === $key ? ' class="current"' : '',
				esc_html( $label )
			);
		}
		return $views;
	}

	public function prepare_items() {
		$per_page = 20;
		$page     = $this->get_pagenum();

		$filters = array(
			'status' => isset( $_GET['status'] ) ? (string) $_GET['status'] : '',
			'search' => isset( $_GET['s'] ) ? (string) $_GET['s'] : '',
			'from'   => isset( $_GET['from'] ) ? (string) $_GET['from'] : '',
			'to'     => isset( $_GET['to'] ) ? (string) $_GET['to'] : '',
		);
		$res = SSC_Store::all(
			$filters,
			array(
				'per_page' => $per_page,
				'page'     => $page,
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->items           = $res['rows'];
		$this->set_pagination_args(
			array(
				'total_items' => $res['total'],
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( max( 1, $res['total'] ) / $per_page ),
			)
		);
	}

	/**
	 * @param array<string, mixed> $item
	 */
	protected function column_default( $item, $col ) {
		switch ( $col ) {
			case 'created_at':
				return esc_html( (string) $item[ $col ] );
			case 'club_name':
				$view_url = add_query_arg(
					array(
						'page' => SSC_Admin_Submissions::PAGE,
						'view' => (int) $item['id'],
					),
					admin_url( 'admin.php' )
				);
				return sprintf(
					'<strong><a href="%s">%s</a></strong>',
					esc_url( $view_url ),
					esc_html( (string) $item['club_name'] )
				);
			case 'people':
				$tot = SSC_Store::total_qty_from_row( $item );
				return (string) (int) $tot;
			case 'pdf':
				$u = SSC_Store::admin_pdf_url( (int) $item['id'] );
				if ( '' === $u ) {
					return '<span class="na" style="color:#a7a7a7">—</span>';
				}
				return '<a class="button button-small" href="' . esc_url( $u ) . '" target="_blank" rel="noopener">'
					. esc_html__( 'Sí', 'steinum-sport-clothes' ) . '</a>';
			case 'status':
				$labels = SSC_Store::statuses();
				$key    = (string) $item['status'];
				return '<span class="ssc-status ssc-status--' . esc_attr( $key ) . '">'
					. esc_html( $labels[ $key ] ?? $key )
					. '</span>';
			default:
				return isset( $item[ $col ] ) ? esc_html( (string) $item[ $col ] ) : '';
		}
	}

	/** @param array<string, mixed> $item */
	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="ids[]" value="%d" />', (int) $item['id'] );
	}
}
