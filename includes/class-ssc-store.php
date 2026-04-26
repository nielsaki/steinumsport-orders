<?php
/**
 * Custom DB table for storing form submissions.
 *
 * @package Steinum_Sport_Clothes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Submissions store.
 */
class SSC_Store {

	public const SCHEMA_VERSION = '6';
	public const OPTION_SCHEMA  = 'ssc_db_schema_version';
	public const TABLE          = 'ssc_submissions';

	public const STATUS_RECEIVED   = 'received';
	public const STATUS_PROCESSING = 'processing';
	public const STATUS_DELIVERED  = 'delivered';
	public const STATUS_CANCELLED  = 'cancelled';

	/**
	 * Total pieces from stored row (new: lines_json; gomul: kvinnur+menn).
	 *
	 * @param array<string, mixed> $row
	 */
	public static function total_qty_from_row( array $row ): int {
		if ( ! empty( $row['lines_json'] ) ) {
			$j = json_decode( (string) $row['lines_json'], true );
			if ( is_array( $j ) ) {
				$s = 0;
				foreach ( $j as $ln ) {
					if ( is_array( $ln ) ) {
						$s += max( 0, (int) ( $ln['qty'] ?? 0 ) );
					}
				}
				return $s;
			}
		}
		return (int) ( $row['count_women'] ?? 0 ) + (int) ( $row['count_men'] ?? 0 );
	}

	/** @return array<string, string> */
	public static function statuses(): array {
		return array(
			self::STATUS_RECEIVED   => 'Móttikin',
			self::STATUS_PROCESSING => 'Í arbeiði',
			self::STATUS_DELIVERED  => 'Avgreitt',
			self::STATUS_CANCELLED  => 'Avlýst',
		);
	}

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Create or upgrade the submissions table.
	 *
	 * Called from the plugin activation hook and on schema-version mismatch.
	 */
	public static function maybe_install(): void {
		self::ensure_missing_table_columns();

		$current = (string) get_option( self::OPTION_SCHEMA, '' );
		if ( $current === self::SCHEMA_VERSION ) {
			return;
		}

		global $wpdb;
		$table   = self::table_name();
		$charset = method_exists( $wpdb, 'get_charset_collate' ) ? $wpdb->get_charset_collate() : '';

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'received',
			club_name VARCHAR(255) NOT NULL,
			boat_name VARCHAR(255) NOT NULL,
			count_women INT UNSIGNED NOT NULL DEFAULT 0,
			count_men INT UNSIGNED NOT NULL DEFAULT 0,
			sizes_women TEXT NOT NULL,
			sizes_men TEXT NOT NULL,
			contact_name VARCHAR(255) NOT NULL,
			contact_email VARCHAR(255) NOT NULL,
			phone VARCHAR(64) NOT NULL,
			billing_email VARCHAR(255) NOT NULL,
			email_body LONGTEXT NOT NULL,
			note TEXT NOT NULL,
			lines_json LONGTEXT NOT NULL,
			pdf_path VARCHAR(512) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY idx_status (status),
			KEY idx_created (created_at),
			KEY idx_email (billing_email)
		) {$charset};";

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		dbDelta( $sql );

		update_option( self::OPTION_SCHEMA, self::SCHEMA_VERSION, false );
	}

	/**
	 * Add columns expected by the current code when the table predates them or dbDelta
	 * did not apply (option drift, SQLite, or partial upgrades).
	 *
	 * @see self::SCHEMA_VERSION — bump so maybe_install() runs full dbDelta when out of date.
	 */
	private static function ensure_missing_table_columns(): void {
		global $wpdb;
		$table = self::table_name();
		if ( ! self::table_exists( $table ) ) {
			return;
		}
		$is_sql = self::is_sqlite_wpdb( $wpdb );
		/** @var array<string, array{0: string, 1: string}> $column_ddls [ mysql, sqlite ] */
		$column_ddls = array(
			'contact_email' => array(
				"ALTER TABLE `{$table}` ADD COLUMN `contact_email` VARCHAR(255) NOT NULL DEFAULT ''",
				"ALTER TABLE {$table} ADD COLUMN contact_email TEXT NOT NULL DEFAULT ''",
			),
			'lines_json'    => array(
				"ALTER TABLE `{$table}` ADD COLUMN `lines_json` LONGTEXT NOT NULL DEFAULT ''",
				"ALTER TABLE {$table} ADD COLUMN lines_json TEXT NOT NULL DEFAULT ''",
			),
			'pdf_path'      => array(
				"ALTER TABLE `{$table}` ADD COLUMN `pdf_path` VARCHAR(512) NOT NULL DEFAULT ''",
				"ALTER TABLE {$table} ADD COLUMN pdf_path TEXT NOT NULL DEFAULT ''",
			),
			'note'          => array(
				"ALTER TABLE `{$table}` ADD COLUMN `note` TEXT NOT NULL DEFAULT ''",
				"ALTER TABLE {$table} ADD COLUMN note TEXT NOT NULL DEFAULT ''",
			),
		);
		foreach ( $column_ddls as $col => $ddls ) {
			if ( self::has_column( $table, $col ) ) {
				continue;
			}
			$sql = $is_sql ? $ddls[1] : $ddls[0];
			$wpdb->query( $sql );
		}
	}

	/**
	 * @param \wpdb|object $wpdb
	 */
	private static function is_sqlite_wpdb( $wpdb ): bool {
		return is_object( $wpdb ) && isset( $wpdb->pdo ) && $wpdb->pdo instanceof \PDO
			&& 'sqlite' === $wpdb->pdo->getAttribute( \PDO::ATTR_DRIVER_NAME );
	}

	private static function table_exists( string $table ): bool {
		global $wpdb;
		if ( self::is_sqlite_wpdb( $wpdb ) ) {
			$t = preg_replace( '/[^A-Za-z0-9_]/', '', $table );
			if ( '' === $t ) {
				return false;
			}
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT name FROM sqlite_master WHERE type = %s AND name = %s', 'table', $t ), ARRAY_A );
			return is_array( $row ) && ! empty( $row['name'] );
		}
		$one = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return ! empty( $one );
	}

	private static function has_column( string $table, string $column ): bool {
		global $wpdb;
		if ( self::is_sqlite_wpdb( $wpdb ) ) {
			$t = preg_replace( '/[^A-Za-z0-9_]/', '', $table );
			if ( '' === $t ) {
				return false;
			}
			$info = $wpdb->get_results( "PRAGMA table_info({$t})" );
			foreach ( (array) $info as $row ) {
				if ( (string) ( $row['name'] ?? '' ) === $column ) {
					return true;
				}
			}
			return false;
		}
		$rows = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}`" );
		foreach ( (array) $rows as $r ) {
			$f = is_array( $r ) ? ( $r['Field'] ?? '' ) : ( $r->Field ?? '' );
			if ( (string) $f === $column ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Persist a submission. Returns the new row ID, or 0 on failure / when storage is disabled.
	 *
	 * @param array<string, string|int> $data       Sanitized submission data.
	 * @param string                    $email_body Plain-text admin email body (for audit).
	 * @param string                    $pdf_path   Server path to the receipt PDF, if any.
	 */
	public static function insert( array $data, string $email_body = '', string $pdf_path = '' ): int {
		if ( false === apply_filters( 'ssc_store_submission', true, $data ) ) {
			return 0;
		}
		global $wpdb;
		$now   = gmdate( 'Y-m-d H:i:s' );
		$lines = $data['order_lines'] ?? array();
		if ( ! is_array( $lines ) ) {
			$lines = array();
		}
		$total_qty = 0;
		foreach ( $lines as $ln ) {
			if ( is_array( $ln ) ) {
				$total_qty += max( 0, (int) ( $ln['qty'] ?? 0 ) );
			}
		}
		$lines_json = function_exists( 'wp_json_encode' )
			? (string) wp_json_encode( $lines )
			: (string) json_encode( $lines );

		$row = array(
			'created_at'    => $now,
			'updated_at'    => $now,
			'status'        => self::STATUS_RECEIVED,
			'club_name'     => (string) ( $data['club_name'] ?? '' ),
			'boat_name'     => (string) ( $data['boat_name'] ?? '' ),
			'count_women'   => $total_qty,
			'count_men'     => 0,
			'sizes_women'   => '',
			'sizes_men'     => '',
			'contact_name'  => (string) ( $data['contact_name'] ?? '' ),
			'contact_email' => (string) ( $data['contact_email'] ?? '' ),
			'phone'         => (string) ( $data['phone'] ?? '' ),
			'billing_email' => (string) ( $data['billing_email'] ?? '' ),
			'email_body'    => (string) $email_body,
			'note'          => '',
			'lines_json'    => $lines_json,
			'pdf_path'      => (string) $pdf_path,
		);
		$ok = $wpdb->insert( self::table_name(), $row );
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Fetch submissions matching simple filters.
	 *
	 * @param array{status?: string, search?: string, from?: string, to?: string} $filters
	 * @param array{per_page?: int, page?: int}                                    $pagination
	 * @return array{rows: array<int, array<string, mixed>>, total: int}
	 */
	public static function all( array $filters = array(), array $pagination = array() ): array {
		global $wpdb;
		$table = self::table_name();

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $filters['status'] ) && array_key_exists( $filters['status'], self::statuses() ) ) {
			$where[]  = 'status = %s';
			$params[] = (string) $filters['status'];
		}
		if ( ! empty( $filters['search'] ) ) {
			$like     = '%' . self::esc_like( (string) $filters['search'] ) . '%';
			$where[]  = '(club_name LIKE %s OR boat_name LIKE %s OR contact_name LIKE %s OR contact_email LIKE %s OR billing_email LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		if ( ! empty( $filters['from'] ) ) {
			$where[]  = 'created_at >= %s';
			$params[] = (string) $filters['from'] . ' 00:00:00';
		}
		if ( ! empty( $filters['to'] ) ) {
			$where[]  = 'created_at <= %s';
			$params[] = (string) $filters['to'] . ' 23:59:59';
		}

		$per_page = isset( $pagination['per_page'] ) ? max( 1, (int) $pagination['per_page'] ) : 25;
		$page     = isset( $pagination['page'] ) ? max( 1, (int) $pagination['page'] ) : 1;
		$offset   = ( $page - 1 ) * $per_page;

		$where_sql = implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$rows_sql  = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";

		$total = $params
			? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) )
			: (int) $wpdb->get_var( $count_sql );

		$rows_params = array_merge( $params, array( $per_page, $offset ) );
		$rows        = (array) $wpdb->get_results( $wpdb->prepare( $rows_sql, $rows_params ), ARRAY_A );

		return array(
			'rows'  => $rows,
			'total' => $total,
		);
	}

	/** @return array<string, mixed>|null */
	public static function find( int $id ): ?array {
		global $wpdb;
		$table = self::table_name();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	public static function set_status( int $id, string $status, ?string $note = null ): bool {
		if ( ! array_key_exists( $status, self::statuses() ) ) {
			return false;
		}
		global $wpdb;
		$update = array(
			'status'     => $status,
			'updated_at' => gmdate( 'Y-m-d H:i:s' ),
		);
		if ( null !== $note ) {
			$update['note'] = $note;
		}
		$ok = $wpdb->update( self::table_name(), $update, array( 'id' => $id ) );
		return false !== $ok;
	}

	public static function delete( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( self::table_name(), array( 'id' => $id ) );
	}

	/**
	 * True if the file is under wp-uploads/.../steinum-sport-clothes/ or the temp copy of the same.
	 */
	public static function pdf_path_is_servable( string $path ): bool {
		if ( '' === $path || str_contains( $path, "\0" ) || ! is_file( $path ) || ! is_readable( $path ) ) {
			return false;
		}
		$real = realpath( $path );
		if ( false === $real ) {
			return false;
		}
		$norm = static function ( string $p ): string {
			return rtrim( str_replace( '\\', '/', $p ), '/' );
		};
		$real_n    = $norm( $real );
		$roots     = array();
		if ( function_exists( 'wp_upload_dir' ) ) {
			$u = wp_upload_dir();
			if ( ! empty( $u['basedir'] ) ) {
				$roots[] = $norm( (string) $u['basedir'] ) . '/steinum-sport-clothes';
			}
		}
		$roots[] = $norm( (string) sys_get_temp_dir() ) . '/steinum-sport-clothes';
		foreach ( $roots as $root ) {
			if ( '' === $root || '/' === $root ) {
				continue;
			}
			$rd = is_dir( $root ) ? realpath( $root ) : false;
			$base = $rd ? $norm( $rd ) : $root;
			if ( $real_n === $base || str_starts_with( $real_n, $base . '/' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array<string, mixed> $row Row from self::find / all().
	 */
	public static function row_has_viewable_pdf( array $row ): bool {
		$p = (string) ( $row['pdf_path'] ?? '' );
		return $p !== '' && self::pdf_path_is_servable( $p );
	}

	/**
	 * Send headers and the PDF for a stored submission, if the file still exists. Caller should exit.
	 */
	public static function output_stored_pdf( int $id ): bool {
		$row = self::find( $id );
		if ( ! is_array( $row ) || empty( $row['pdf_path'] ) ) {
			return false;
		}
		$path = (string) $row['pdf_path'];
		if ( ! self::pdf_path_is_servable( $path ) ) {
			return false;
		}
		$size = (int) @filesize( $path );
		if ( ! headers_sent() ) {
			header( 'Content-Type: application/pdf' );
			header( 'Content-Disposition: inline; filename="ssc-receipt-' . $id . '.pdf"' );
			if ( $size > 0 ) {
				header( 'Content-Length: ' . (string) $size );
			}
			if ( function_exists( 'nocache_headers' ) ) {
				nocache_headers();
			}
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		readfile( $path );
		return true;
	}

	/**
	 * @return string Admin URL to stream the stored PDF, or empty if not available.
	 */
	public static function admin_pdf_url( int $id ): string {
		if ( $id < 1 || ! function_exists( 'admin_url' ) || ! function_exists( 'add_query_arg' ) || ! function_exists( 'wp_create_nonce' ) ) {
			return '';
		}
		$row = self::find( $id );
		if ( ! is_array( $row ) || empty( $row['pdf_path'] ) ) {
			return '';
		}
		if ( ! self::pdf_path_is_servable( (string) $row['pdf_path'] ) ) {
			return '';
		}
		return (string) add_query_arg(
			array(
				'action'   => 'ssc_view_pdf',
				'id'       => $id,
				'_wpnonce' => wp_create_nonce( 'ssc_view_pdf_' . $id ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/** @param array<int, int> $ids */
	public static function delete_many( array $ids ): int {
		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( ! $ids ) {
			return 0;
		}
		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$sql          = $wpdb->prepare( 'DELETE FROM ' . self::table_name() . " WHERE id IN ({$placeholders})", $ids );
		return (int) $wpdb->query( $sql );
	}

	public static function purge_older_than( int $days ): int {
		$days = max( 0, $days );
		if ( 0 === $days ) {
			return 0;
		}
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS_FALLBACK() ) );
		return (int) $wpdb->query(
			$wpdb->prepare( 'DELETE FROM ' . self::table_name() . ' WHERE created_at < %s', $cutoff )
		);
	}

	private static function esc_like( string $text ): string {
		global $wpdb;
		if ( method_exists( $wpdb, 'esc_like' ) ) {
			return $wpdb->esc_like( $text );
		}
		return addcslashes( $text, '_%\\' );
	}
}

if ( ! function_exists( 'DAY_IN_SECONDS_FALLBACK' ) ) {
	/**
	 * Returns DAY_IN_SECONDS, falling back to 86400 outside of WordPress.
	 */
	function DAY_IN_SECONDS_FALLBACK(): int { // phpcs:ignore
		return defined( 'DAY_IN_SECONDS' ) ? (int) DAY_IN_SECONDS : 86400;
	}
}
