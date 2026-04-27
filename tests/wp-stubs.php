<?php
/**
 * WordPress function stubs + SQLite-backed $wpdb for running the plugin
 * under plain PHP (CLI test runner and tests/serve.php).
 *
 * Modeled on afturgjalds-skipan: enough WP surface area for the plugin's
 * classes to load and their flow to be exercised.
 *
 * @package Steinum_Sport_Clothes
 */

if ( ! defined( 'SSC_TESTING' ) ) {
	define( 'SSC_TESTING', true );
}
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'WPINC' ) ) {
	define( 'WPINC', 'wp-includes' );
}
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', true );
}
if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', sys_get_temp_dir() . '/ssc-wp-content' );
	@mkdir( WP_CONTENT_DIR . '/uploads', 0775, true );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 60 * MINUTE_IN_SECONDS );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 24 * HOUR_IN_SECONDS );
}

/* ------------------------------------------------------------------ */
/* Hook + filter registry                                              */
/* ------------------------------------------------------------------ */

global $ssc_test_hooks, $ssc_test_options, $ssc_test_transients, $ssc_test_emails, $ssc_test_redirects;
$ssc_test_hooks       = array();
$ssc_test_options     = array(
	'admin_email' => 'admin@example.test',
	'blogname'    => 'Steinum Sport (próvingar-vevur)',
	'siteurl'     => 'http://localhost:9090',
);
$ssc_test_transients  = array();
$ssc_test_emails      = array();
$ssc_test_redirects   = array();

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
		return add_filter( $tag, $callback, $priority, $accepted_args );
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
		global $ssc_test_hooks;
		$ssc_test_hooks[ $tag ][ $priority ][] = array( $callback, $accepted_args );
		return true;
	}
}
if ( ! function_exists( 'remove_all_filters' ) ) {
	function remove_all_filters( $tag, $priority = false ) {
		global $ssc_test_hooks;
		unset( $ssc_test_hooks[ $tag ] );
	}
}
if ( ! function_exists( 'do_action' ) ) {
	function do_action( $tag, ...$args ) {
		apply_filters( $tag, ...$args );
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value = null, ...$args ) {
		global $ssc_test_hooks;
		if ( empty( $ssc_test_hooks[ $tag ] ) ) {
			return $value;
		}
		ksort( $ssc_test_hooks[ $tag ] );
		foreach ( $ssc_test_hooks[ $tag ] as $callbacks ) {
			foreach ( $callbacks as [$cb, $accepted] ) {
				$call_args = array_merge( array( $value ), $args );
				$call_args = array_slice( $call_args, 0, max( 1, (int) $accepted ) );
				$value     = call_user_func_array( $cb, $call_args );
			}
		}
		return $value;
	}
}

if ( ! function_exists( 'add_shortcode' ) ) {
	function add_shortcode( $tag, $cb ) {
		global $ssc_test_hooks;
		$ssc_test_hooks[ '__shortcode_' . $tag ] = $cb;
	}
}
if ( ! function_exists( 'do_shortcode' ) ) {
	function do_shortcode( $content ) {
		global $ssc_test_hooks;
		return preg_replace_callback(
			'/\[(\w+)(?:\s+[^\]]*)?\]/',
			static function ( $m ) use ( $ssc_test_hooks ) {
				$tag = '__shortcode_' . $m[1];
				return isset( $ssc_test_hooks[ $tag ] )
					? (string) call_user_func( $ssc_test_hooks[ $tag ], array() )
					: $m[0];
			},
			(string) $content
		);
	}
}

/* ------------------------------------------------------------------ */
/* Options + transients                                                */
/* ------------------------------------------------------------------ */

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		global $ssc_test_options;
		return array_key_exists( $name, $ssc_test_options ) ? $ssc_test_options[ $name ] : $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value, $autoload = null ) {
		global $ssc_test_options;
		$ssc_test_options[ $name ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $name ) {
		global $ssc_test_options;
		unset( $ssc_test_options[ $name ] );
		return true;
	}
}
if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) {
		global $ssc_test_transients;
		if ( ! isset( $ssc_test_transients[ $key ] ) ) {
			return false;
		}
		[$exp, $val] = $ssc_test_transients[ $key ];
		if ( $exp > 0 && $exp < time() ) {
			unset( $ssc_test_transients[ $key ] );
			return false;
		}
		return $val;
	}
}
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $key, $value, $ttl = 0 ) {
		global $ssc_test_transients;
		$ssc_test_transients[ $key ] = array( $ttl > 0 ? time() + $ttl : 0, $value );
		return true;
	}
}
if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $key ) {
		global $ssc_test_transients;
		unset( $ssc_test_transients[ $key ] );
		return true;
	}
}

/* ------------------------------------------------------------------ */
/* Sanitization / escaping                                             */
/* ------------------------------------------------------------------ */

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $val ) {
		if ( is_array( $val ) ) {
			return array_map( 'wp_unslash', $val );
		}
		return is_string( $val ) ? stripslashes( $val ) : $val;
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		$str = (string) $str;
		$str = strip_tags( $str );
		$str = (string) preg_replace( "/[\r\n\t\0\x0B]+/u", ' ', $str );
		$str = (string) preg_replace( '/\s+/u', ' ', $str );
		return trim( $str );
	}
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $str ) {
		$str = (string) $str;
		$str = strip_tags( $str );
		$str = str_replace( array( "\r\n", "\r" ), "\n", $str );
		return trim( $str );
	}
}
if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $email ) {
		$email = sanitize_text_field( $email );
		return filter_var( $email, FILTER_VALIDATE_EMAIL ) ? strtolower( $email ) : '';
	}
}
if ( ! function_exists( 'is_email' ) ) {
	function is_email( $email ) {
		return (bool) filter_var( (string) $email, FILTER_VALIDATE_EMAIL );
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( (string) preg_replace( '/[^a-z0-9_\-]/i', '', (string) $key ) );
	}
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $s ) {
		return htmlspecialchars( (string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $s ) { return esc_html( $s ); }
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $s ) { return esc_html( $s ); }
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $s ) { return (string) $s; }
}
if ( ! function_exists( 'esc_textarea' ) ) {
	function esc_textarea( $s ) { return esc_html( $s ); }
}
if ( ! function_exists( '__' ) ) {
	function __( $s, $domain = null ) { return (string) $s; }
}
if ( ! function_exists( '_e' ) ) {
	function _e( $s, $domain = null ) { echo $s; }
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $s, $domain = null ) { return esc_html( $s ); }
}
if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $s, $domain = null ) { echo esc_html( $s ); }
}
if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $s, $domain = null ) { return esc_attr( $s ); }
}
if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( $s, $domain = null ) { echo esc_attr( $s ); }
}

/* ------------------------------------------------------------------ */
/* Misc WP helpers                                                     */
/* ------------------------------------------------------------------ */

if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return rtrim( (string) get_option( 'siteurl' ), '/' ) . '/' . ltrim( (string) $path, '/' );
	}
}
if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return home_url( 'wp-admin/' . ltrim( (string) $path, '/' ) );
	}
}
if ( ! function_exists( 'site_url' ) ) {
	function site_url( $path = '' ) { return home_url( $path ); }
}
if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type, $gmt = false ) {
		if ( 'timestamp' === $type || 'U' === $type ) {
			return time();
		}
		return gmdate( 'Y-m-d H:i:s', time() );
	}
}
if ( ! function_exists( 'date_i18n' ) ) {
	function date_i18n( $format, $timestamp = false, $gmt = false ) {
		$t = false === $timestamp ? time() : (int) $timestamp;
		return gmdate( $format, $t );
	}
}
if ( ! function_exists( 'wp_date' ) ) {
	function wp_date( $format, $timestamp = null, $timezone = null ) {
		$t = null === $timestamp ? time() : (int) $timestamp;
		return gmdate( $format, $t );
	}
}
if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( $key, $value = null, $url = null ) {
		$args = is_array( $key ) ? $key : array( $key => $value );
		if ( null === $url ) {
			$url = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/';
		}
		$parts  = parse_url( $url );
		$query  = array();
		if ( ! empty( $parts['query'] ) ) {
			parse_str( (string) $parts['query'], $query );
		}
		foreach ( $args as $k => $v ) {
			if ( null === $v || false === $v ) {
				unset( $query[ $k ] );
			} else {
				$query[ $k ] = $v;
			}
		}
		$out = ( $parts['scheme'] ?? '' ? $parts['scheme'] . '://' : '' )
			. ( $parts['host'] ?? '' )
			. ( $parts['path'] ?? '' );
		if ( $query ) {
			$out .= '?' . http_build_query( $query );
		}
		return $out;
	}
}
if ( ! function_exists( 'remove_query_arg' ) ) {
	function remove_query_arg( $key, $url = null ) {
		$keys = (array) $key;
		$args = array();
		foreach ( $keys as $k ) {
			$args[ $k ] = false;
		}
		return add_query_arg( $args, null, $url );
	}
}
if ( ! function_exists( 'wp_safe_redirect' ) ) {
	function wp_safe_redirect( $url, $status = 302 ) {
		global $ssc_test_redirects;
		$ssc_test_redirects[] = (string) $url;
		if ( ! defined( 'SSC_NO_HTTP_REDIRECT' ) && ! headers_sent() ) {
			header( 'Location: ' . $url, true, $status );
		}
		return true;
	}
}
if ( ! function_exists( 'wp_redirect' ) ) {
	function wp_redirect( $url, $status = 302 ) { return wp_safe_redirect( $url, $status ); }
}
if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $msg = '', $title = '', $args = array() ) {
		throw new RuntimeException( is_string( $msg ) ? $msg : 'wp_die' );
	}
}
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $cap ) { return true; }
}
if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ) { return md5( 'ssc-test-' . $action ); }
}
if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( $nonce, $action = -1 ) {
		return $nonce === wp_create_nonce( $action ) ? 1 : false;
	}
}
if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true ) {
		$out = '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( wp_create_nonce( $action ) ) . '" />';
		if ( $echo ) {
			echo $out;
		}
		return $out;
	}
}
if ( ! function_exists( 'check_admin_referer' ) ) {
	function check_admin_referer( $action = -1, $name = '_wpnonce' ) {
		$nonce = $_REQUEST[ $name ] ?? '';
		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			throw new RuntimeException( 'Bad nonce' );
		}
		return 1;
	}
}
if ( ! function_exists( 'submit_button' ) ) {
	function submit_button( $text = 'Save', $type = 'primary', $name = 'submit', $wrap = true, $other = '' ) {
		$btn = '<button type="submit" class="button button-' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '">' . esc_html( $text ) . '</button>';
		if ( $wrap ) { $btn = '<p class="submit">' . $btn . '</p>'; }
		echo $btn;
	}
}
if ( ! function_exists( 'settings_fields' ) ) {
	function settings_fields( $group ) {
		echo '<input type="hidden" name="option_page" value="' . esc_attr( $group ) . '" />';
		wp_nonce_field( $group . '-options' );
	}
}
if ( ! function_exists( 'register_setting' ) ) {
	function register_setting( $group, $option, $args = array() ) { /* noop */ }
}
if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook( $file, $cb ) { /* noop in tests */ }
}
if ( ! function_exists( 'add_menu_page' ) ) {
	function add_menu_page( ...$args ) { /* noop */ }
}
if ( ! function_exists( 'add_submenu_page' ) ) {
	function add_submenu_page( ...$args ) { /* noop */ }
}
if ( ! function_exists( 'wp_register_style' ) ) {
	function wp_register_style( ...$args ) { /* noop */ }
}
if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( ...$args ) { /* noop */ }
}
if ( ! function_exists( 'wp_register_script' ) ) {
	function wp_register_script( ...$args ) { /* noop */ }
}
if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( ...$args ) { /* noop */ }
}
if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) { return rtrim( dirname( $file ), '/\\' ) . '/'; }
}
if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( $file ) { return '/'; }
}
if ( ! function_exists( 'wp_upload_dir' ) ) {
	function wp_upload_dir() {
		$base = WP_CONTENT_DIR . '/uploads';
		if ( ! is_dir( $base ) ) {
			@mkdir( $base, 0775, true );
		}
		return array( 'basedir' => $base, 'baseurl' => '/uploads' );
	}
}
if ( ! function_exists( 'selected' ) ) {
	function selected( $a, $b = true, $echo = true ) {
		$out = ( (string) $a === (string) $b ) ? ' selected="selected"' : '';
		if ( $echo ) { echo $out; }
		return $out;
	}
}
if ( ! function_exists( 'checked' ) ) {
	function checked( $a, $b = true, $echo = true ) {
		$out = ( (string) $a === (string) $b || ( ! empty( $a ) && ! empty( $b ) ) ) ? ' checked="checked"' : '';
		if ( $echo ) { echo $out; }
		return $out;
	}
}
if ( ! function_exists( '__return_true' ) ) {
	function __return_true() { return true; }
}
if ( ! function_exists( '__return_false' ) ) {
	function __return_false() { return false; }
}
if ( ! function_exists( 'wp_mail' ) ) {
	function wp_mail( $to, $subject, $message, $headers = array(), $attachments = array() ) {
		global $ssc_test_emails;
		$ssc_test_emails[] = compact( 'to', 'subject', 'message', 'headers', 'attachments' );
		return true;
	}
}

/* WP_List_Table stub for non-WP environments. Just enough surface for tests. */
if ( ! class_exists( 'WP_List_Table' ) ) {
	class WP_List_Table {
		/** @var array<int, array<string, mixed>> */
		public $items = array();
		protected array $_args = array();
		protected array $_pagination_args = array();
		protected array $_column_headers = array();

		public function __construct( $args = array() ) {
			$this->_args = is_array( $args ) ? $args : array();
		}
		public function get_pagenum() {
			return max( 1, (int) ( $_GET['paged'] ?? 1 ) );
		}
		public function set_pagination_args( $args ) { $this->_pagination_args = (array) $args; }
		public function get_columns() { return array(); }
		protected function get_sortable_columns() { return array(); }
		public function get_bulk_actions() { return array(); }
		protected function get_views() { return array(); }
		public function prepare_items() {}
		public function display() { /* basic noop */ }
		public function views() {
			$views = $this->get_views();
			if ( ! $views ) { return; }
			echo '<ul class="subsubsub">';
			foreach ( $views as $key => $view ) {
				echo '<li>' . $view . '</li>';
			}
			echo '</ul>';
		}
		protected function column_default( $item, $col ) { return ''; }
		protected function column_cb( $item ) { return ''; }
	}
}
if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

/* ------------------------------------------------------------------ */
/* Minimal SQLite-backed $wpdb                                         */
/* ------------------------------------------------------------------ */

class SSC_Test_WPDB {

	public string $prefix    = 'wp_';
	public int $insert_id    = 0;
	public string $last_error = '';
	public PDO $pdo;

	private string $db_file;

	public function __construct( ?string $db_file = null ) {
		$this->db_file = $db_file ?? sys_get_temp_dir() . '/ssc-test.sqlite';
		$this->pdo     = new PDO( 'sqlite:' . $this->db_file );
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	}

	public function get_charset_collate(): string { return ''; }

	public function esc_like( string $text ): string {
		return addcslashes( $text, '_%\\' );
	}

	/**
	 * Mimic WP's prepare(): substitutes %d / %s / %f placeholders.
	 *
	 * @param mixed[]|mixed $args
	 */
	public function prepare( string $sql, ...$args ): string {
		if ( count( $args ) === 1 && is_array( $args[0] ) ) {
			$args = $args[0];
		}
		$out = '';
		$ai  = 0;
		$i   = 0;
		$len = strlen( $sql );
		while ( $i < $len ) {
			$c = $sql[ $i ];
			if ( '%' === $c && $i + 1 < $len ) {
				$nxt = $sql[ $i + 1 ];
				if ( in_array( $nxt, array( 'd', 's', 'f' ), true ) ) {
					$val = $args[ $ai++ ] ?? '';
					switch ( $nxt ) {
						case 'd': $out .= (string) (int) $val; break;
						case 'f': $out .= (string) (float) $val; break;
						case 's': $out .= "'" . str_replace( array( "\\", "'" ), array( "\\\\", "''" ), (string) $val ) . "'"; break;
					}
					$i += 2;
					continue;
				}
			}
			$out .= $c;
			$i++;
		}
		return $out;
	}

	public function query( string $sql ) {
		try {
			if ( preg_match( '/^\s*(INSERT|UPDATE|DELETE|REPLACE)\b/i', $sql ) ) {
				$rows            = $this->pdo->exec( $sql );
				$this->insert_id = (int) $this->pdo->lastInsertId();
				return $rows === false ? false : (int) $rows;
			}
			$st = $this->pdo->query( $sql );
			return $st;
		} catch ( PDOException $e ) {
			$this->last_error = $e->getMessage();
			return false;
		}
	}

	public function get_var( string $sql ) {
		$st = $this->pdo->query( $sql );
		if ( ! $st ) { return null; }
		$row = $st->fetch( PDO::FETCH_NUM );
		return $row ? $row[0] : null;
	}

	public function get_row( string $sql, $output = ARRAY_A ) {
		$st = $this->pdo->query( $sql );
		if ( ! $st ) { return null; }
		return $st->fetch( PDO::FETCH_ASSOC ) ?: null;
	}

	public function get_results( string $sql, $output = ARRAY_A ) {
		$st = $this->pdo->query( $sql );
		if ( ! $st ) { return array(); }
		return $st->fetchAll( PDO::FETCH_ASSOC );
	}

	public function insert( string $table, array $data, $format = null ): int {
		$cols     = array_keys( $data );
		$placeh   = array();
		$values   = array();
		foreach ( $cols as $c ) {
			$placeh[] = ':' . $c;
			$values[ ':' . $c ] = $data[ $c ];
		}
		$sql = sprintf(
			'INSERT INTO %s (%s) VALUES (%s)',
			$table,
			implode( ', ', $cols ),
			implode( ', ', $placeh )
		);
		$st  = $this->pdo->prepare( $sql );
		$ok  = $st->execute( $values );
		if ( $ok ) {
			$this->insert_id = (int) $this->pdo->lastInsertId();
			return 1;
		}
		return 0;
	}

	public function update( string $table, array $data, array $where, $format = null, $where_format = null ) {
		$set    = array();
		$values = array();
		foreach ( $data as $k => $v ) {
			$set[]              = $k . ' = :s_' . $k;
			$values[ ':s_' . $k ] = $v;
		}
		$cond = array();
		foreach ( $where as $k => $v ) {
			$cond[]              = $k . ' = :w_' . $k;
			$values[ ':w_' . $k ] = $v;
		}
		$sql = sprintf( 'UPDATE %s SET %s WHERE %s', $table, implode( ', ', $set ), implode( ' AND ', $cond ) );
		$st  = $this->pdo->prepare( $sql );
		$ok  = $st->execute( $values );
		return $ok ? $st->rowCount() : false;
	}

	public function delete( string $table, array $where, $where_format = null ) {
		$cond   = array();
		$values = array();
		foreach ( $where as $k => $v ) {
			$cond[]              = $k . ' = :w_' . $k;
			$values[ ':w_' . $k ] = $v;
		}
		$sql = sprintf( 'DELETE FROM %s WHERE %s', $table, implode( ' AND ', $cond ) );
		$st  = $this->pdo->prepare( $sql );
		$ok  = $st->execute( $values );
		return $ok ? $st->rowCount() : false;
	}

	/**
	 * Translate a MySQL-flavored CREATE TABLE statement into one or more
	 * SQLite-compatible statements (CREATE TABLE + CREATE INDEX).
	 *
	 * @return array<int, string>
	 */
	public static function translate_schema( string $sql ): array {
		$sql   = trim( $sql );
		$out   = array();
		$lines = preg_split( '/;\s*/', $sql ) ?: array( $sql );

		foreach ( $lines as $stmt ) {
			$stmt = trim( $stmt );
			if ( '' === $stmt ) {
				continue;
			}
			if ( ! preg_match( '/^CREATE\s+TABLE\s+(\S+)\s*\((.+)\)\s*([^)]*)$/is', $stmt, $m ) ) {
				$out[] = $stmt;
				continue;
			}
			$table = $m[1];
			$body  = $m[2];

			$keys     = array();
			$body_out = array();
			foreach ( preg_split( '/,\s*\n/', $body ) ?: array() as $line ) {
				$line = trim( $line );
				$line = rtrim( $line, ',' );
				if ( '' === $line ) { continue; }

				if ( preg_match( '/^KEY\s+(\S+)\s*\(([^)]+)\)\s*$/i', $line, $km ) ) {
					$keys[] = "CREATE INDEX IF NOT EXISTS {$km[1]} ON {$table} ({$km[2]})";
					continue;
				}
				if ( preg_match( '/^PRIMARY\s+KEY\s*\(([^)]+)\)\s*$/i', $line ) ) {
					continue;
				}

				$line = preg_replace( '/\bBIGINT\s+UNSIGNED\b/i', 'INTEGER', $line );
				$line = preg_replace( '/\bINT\s+UNSIGNED\b/i', 'INTEGER', $line );
				$line = preg_replace( '/\bVARCHAR\s*\(\s*\d+\s*\)/i', 'TEXT', $line );
				$line = preg_replace( '/\bDATETIME\b/i', 'TEXT', $line );
				$line = preg_replace( '/\bLONGTEXT\b/i', 'TEXT', $line );

				if ( preg_match( '/^id\b.*AUTO_INCREMENT/i', $line ) ) {
					$line = 'id INTEGER PRIMARY KEY AUTOINCREMENT';
				}
				$line = (string) preg_replace( '/\bAUTO_INCREMENT\b/i', '', $line );

				$body_out[] = $line;
			}

			$out[] = "CREATE TABLE IF NOT EXISTS {$table} (\n  " . implode( ",\n  ", $body_out ) . "\n)";
			$out   = array_merge( $out, $keys );
		}
		return $out;
	}
}

if ( ! function_exists( 'dbDelta' ) ) {
	function dbDelta( $sql ) {
		global $wpdb;
		$stmts = SSC_Test_WPDB::translate_schema( is_array( $sql ) ? implode( ";\n", $sql ) : (string) $sql );
		foreach ( $stmts as $s ) {
			$wpdb->pdo->exec( $s );
		}
		return array();
	}
}

/* ------------------------------------------------------------------ */
/* Test helpers                                                        */
/* ------------------------------------------------------------------ */

/**
 * Reset all in-memory state and rebuild the SQLite DB fresh.
 */
function ssc_test_reset( ?string $db_file = null ): void {
	global $wpdb, $ssc_test_hooks, $ssc_test_options, $ssc_test_transients, $ssc_test_emails, $ssc_test_redirects;
	$ssc_test_hooks       = array();
	$ssc_test_options     = array(
		'admin_email' => 'admin@example.test',
		'blogname'    => 'Steinum Sport (próvingar-vevur)',
		'siteurl'     => 'http://localhost:9090',
	);
	$ssc_test_transients  = array();
	$ssc_test_emails      = array();
	$ssc_test_redirects   = array();

	if ( null === $db_file ) {
		$db_file = sys_get_temp_dir() . '/ssc-test.sqlite';
	}
	if ( is_file( $db_file ) ) {
		@unlink( $db_file );
	}
	$wpdb = new SSC_Test_WPDB( $db_file );
}
