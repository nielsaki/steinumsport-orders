<?php
/**
 * Local preview without WordPress.
 *
 *   php -S localhost:9090 -t . tests/serve.php
 *
 * Renders the form on the left, the email log on the right, and the
 * stored submissions below. Backed by a persistent SQLite file so you
 * can play with delete / status across requests.
 *
 * Test mode + dry run are forced ON, so no real mail is ever sent.
 *
 * @package Steinum_Sport_Clothes
 */

if ( PHP_SAPI === 'cli-server' ) {
	$path = parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH ) ?: '/';
	if ( $path !== '/' && preg_match( '/\.(css|js|png|jpe?g|gif|svg|ico|woff2?|ttf|pdf)$/i', $path ) ) {
		$abs = dirname( __DIR__ ) . $path;
		if ( is_file( $abs ) ) {
			return false;
		}
	}
}

if ( ! defined( 'SSC_EMAIL_TEST_MODE' ) ) {
	define( 'SSC_EMAIL_TEST_MODE', true );
}
if ( ! defined( 'SSC_EMAIL_DRY_RUN' ) ) {
	define( 'SSC_EMAIL_DRY_RUN', true );
}
if ( ! defined( 'SSC_EMAIL_LOG_FILE' ) ) {
	define( 'SSC_EMAIL_LOG_FILE', sys_get_temp_dir() . '/ssc-preview.log' );
}

$ssc_db_file = sys_get_temp_dir() . '/ssc-preview.sqlite';

require __DIR__ . '/wp-stubs.php';

global $wpdb;
$wpdb = new SSC_Test_WPDB( $ssc_db_file );

require_once dirname( __DIR__ ) . '/steinum-sport-clothes.php';

SSC_Store::maybe_install();

// Mirror the last PDF + admin Excel to fixed paths so the local UI can link to them.
$ssc_preview_last_pdf = __DIR__ . '/preview-pdfs/last-submission.pdf';
$ssc_preview_last_xls = __DIR__ . '/preview-pdfs/last-submission.xls';
add_action(
	'ssc_after_submission',
	static function ( $id, $data, $pdf_path, $xlsx_path = '' ) use ( $ssc_preview_last_pdf, $ssc_preview_last_xls ) {
		unset( $id, $data );
		$dir = dirname( $ssc_preview_last_pdf );
		if ( ! is_dir( $dir ) ) {
			@mkdir( $dir, 0775, true );
		}
		if ( is_string( $pdf_path ) && '' !== $pdf_path && is_readable( $pdf_path ) ) {
			@copy( $pdf_path, $ssc_preview_last_pdf );
		}
		if ( is_string( $xlsx_path ) && '' !== $xlsx_path && is_readable( $xlsx_path ) ) {
			@copy( $xlsx_path, $ssc_preview_last_xls );
		}
	},
	10,
	4
);

/**
 * Pre-filled input for the local “demo” button (1–5 random order lines, fixed contact/felag).
 *
 * @return array<string, mixed>
 */
function ssc_preview_demo_input(): array {
	$items   = array(
		SSC_Sanitizer::ITEM_TRIKOT,
		SSC_Sanitizer::ITEM_TSHIRT,
		SSC_Sanitizer::ITEM_RASHGUARD,
		SSC_Sanitizer::ITEM_SPEEDCOACH,
		SSC_Sanitizer::ITEM_NK_STOPUR,
	);
	$sizes   = SSC_Sanitizer::size_options();
	$genders = array( SSC_Sanitizer::GENDER_MEN, SSC_Sanitizer::GENDER_WOMEN );
	$farv    = array_keys( SSC_Sanitizer::farv_options() );
	$n_lines = random_int( 1, 5 );
	$lines   = array();
	for ( $i = 0; $i < $n_lines; $i++ ) {
		$item = $items[ random_int( 0, count( $items ) - 1 ) ];
		$line = array(
			'item'         => $item,
			'gender'       => SSC_Sanitizer::GENDER_WOMEN,
			'size'         => '',
			'bumper_color' => '',
			'qty'          => random_int( 1, 3 ),
			'name'         => 1 === random_int( 0, 1 ) ? ( 'Dømi ' . ( $i + 1 ) ) : '',
		);
		if ( SSC_Sanitizer::item_needs_gender( $item ) ) {
			$line['gender'] = $genders[ random_int( 0, 1 ) ];
		}
		if ( SSC_Sanitizer::item_needs_size( $item ) && $sizes ) {
			$line['size'] = $sizes[ random_int( 0, count( $sizes ) - 1 ) ];
		}
		if ( SSC_Sanitizer::item_uses_farv( $item ) && $farv ) {
			$line['bumper_color'] = $farv[ random_int( 0, count( $farv ) - 1 ) ];
		}
		$lines[] = $line;
	}
	return array(
		'club_name'     => 'Havnar Róðrarfelag',
		'boat_name'     => 'Havnarbáturin',
		'contact_name'  => 'Niels Áki Mørk',
		'contact_email' => 'niels.aki.mork@gmail.com',
		'phone'         => '+298 223336',
		'billing_email' => 'steinum@steinum.net',
		'order_lines'   => $lines,
	);
}

/**
 * After POST, send user back to the same path as the form (serves in subfolder installs).
 */
function ssc_preview_safe_redirect( string $default_path ): void {
	$to = $default_path;
	if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
		$path = parse_url( (string) $_SERVER['HTTP_REFERER'], PHP_URL_PATH );
		if ( is_string( $path ) && '' !== $path ) {
			$to = $path;
		}
	}
	header( 'Location: ' . $to, true, 303 );
}

// JSON for the in-browser "Dæmi-data" (avoids broken redirects and transient key mismatches).
if ( 'GET' === ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) && isset( $_GET['ssc_demo_json'] ) && '1' === (string) $_GET['ssc_demo_json'] ) {
	@header( 'Content-Type: application/json; charset=utf-8' );
	@header( 'Cache-Control: no-store' );
	$payload = ssc_preview_demo_input();
	$enc     = function_exists( 'wp_json_encode' ) ? (string) wp_json_encode( $payload, JSON_UNESCAPED_UNICODE ) : (string) json_encode( $payload, JSON_UNESCAPED_UNICODE );
	echo $enc;
	exit;
}

if ( 'GET' === ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) && isset( $_GET['ssc_preview_pdf'] ) ) {
	$rid = (int) $_GET['ssc_preview_pdf'];
	if ( $rid > 0 && SSC_Store::output_stored_pdf( $rid ) ) {
		exit;
	}
	if ( $rid > 0 ) {
		http_response_code( 404 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo 'PDF ikki funnin.';
		exit;
	}
}

if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	$preview = isset( $_POST['ssc_preview_action'] ) ? (string) $_POST['ssc_preview_action'] : '';
	switch ( $preview ) {
		case 'clear_log':
			SSC_Logger::clear();
			header( 'Location: /' );
			exit;
		case 'delete_all':
			$wpdb->pdo->exec( 'DELETE FROM ' . SSC_Store::table_name() );
			header( 'Location: /' );
			exit;
		case 'delete_one':
			SSC_Store::delete( (int) ( $_POST['id'] ?? 0 ) );
			header( 'Location: /' );
			exit;
		case 'set_status':
			SSC_Store::set_status( (int) ( $_POST['id'] ?? 0 ), (string) ( $_POST['status'] ?? '' ) );
			header( 'Location: /' );
			exit;
		case 'demo_fill':
			$ck = md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) . '|' . (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );
			set_transient( 'ssc_input_' . $ck, ssc_preview_demo_input(), 600 );
			set_transient( 'ssc_errors_' . $ck, array(), 600 );
			header( 'Location: ' . ssc_preview_safe_redirect( '/' ) );
			exit;
	}

	if ( SSC_Form::ACTION === ( $_POST['action'] ?? '' ) ) {
		try {
			do_action( 'init' );
		} catch ( \Throwable $e ) {
			http_response_code( 400 );
			echo '<pre>Submission error: ' . esc_html( $e->getMessage() ) . '</pre>';
			exit;
		}
	}
}

$form_html   = ( new SSC_Form() )->render();
$log_text    = SSC_Logger::tail( 12000 );
$submissions = SSC_Store::all( array(), array( 'per_page' => 100 ) );
$status_msg  = '';
$status_kind = '';
if ( isset( $_GET['ssc_status'] ) ) {
	$status_kind = 'ok' === $_GET['ssc_status'] ? 'ok' : 'err';
	$status_msg  = 'ok' === $_GET['ssc_status']
		? 'Tilkunn móttikin (próvingarhamur — einki teldupostur er sent).'
		: 'Onkur villa hendi.';
}
$ssc_last_pdf_href  = is_readable( $ssc_preview_last_pdf ) ? '/tests/preview-pdfs/last-submission.pdf' : '';
$ssc_last_pdf_ctime = ( '' !== $ssc_last_pdf_href && is_file( $ssc_preview_last_pdf ) ) ? (int) filemtime( $ssc_preview_last_pdf ) : 0;
$ssc_open_pdf_href  = ( '' !== $ssc_last_pdf_href && $ssc_last_pdf_ctime > 0 ) ? ( $ssc_last_pdf_href . '?v=' . $ssc_last_pdf_ctime ) : '';
$ssc_last_xls_href  = is_readable( $ssc_preview_last_xls ) ? '/tests/preview-pdfs/last-submission.xls' : '';
$ssc_last_xls_ctime = ( '' !== $ssc_last_xls_href && is_file( $ssc_preview_last_xls ) ) ? (int) filemtime( $ssc_preview_last_xls ) : 0;
$ssc_open_xls_href  = ( '' !== $ssc_last_xls_href && $ssc_last_xls_ctime > 0 ) ? ( $ssc_last_xls_href . '?v=' . $ssc_last_xls_ctime ) : '';

$ssc_wp_base         = rtrim( getenv( 'SSC_WP_SITEURL' ) ? (string) getenv( 'SSC_WP_SITEURL' ) : 'http://localhost:8080', '/' );
$ssc_url_submissions = esc_url( $ssc_wp_base . '/wp-admin/admin.php?page=' . SSC_Admin_Submissions::PAGE );
$ssc_url_settings    = esc_url( $ssc_wp_base . '/wp-admin/admin.php?page=' . SSC_Settings::PAGE );

$frontend_css = (string) @file_get_contents( dirname( __DIR__ ) . '/assets/css/frontend.css' );
?>
<!doctype html>
<html lang="fo">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width,initial-scale=1" />
	<title>Steinum Sport — lokal próving</title>
	<style>
		:root {
			--ssc-app-bg0: #e8edf5;
			--ssc-app-bg1: #f0f4fa;
			--ssc-ink: #0f172a;
			--ssc-ink-mid: #475569;
			--ssc-ink-faint: #94a3b8;
			--ssc-card: #ffffff;
			--ssc-card-edge: rgba(15, 23, 42, 0.07);
			--ssc-sh: 0 1px 2px rgba(15, 23, 42, 0.04), 0 8px 24px -4px rgba(15, 23, 42, 0.08);
			--ssc-sh-hover: 0 4px 20px -2px rgba(15, 23, 42, 0.1);
			--ssc-accent: #1e3a5f;
			--ssc-accent-hi: #2d4a7a;
			--ssc-ok: #15803d;
			--ssc-err: #b91c1c;
		}
		* { box-sizing: border-box; }
		html { scroll-behavior: smooth; }
		body {
			margin: 0; min-height: 100vh; color: var(--ssc-ink);
			font: 15px/1.55 system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
			background: linear-gradient(160deg, var(--ssc-app-bg0) 0%, var(--ssc-app-bg1) 40%, #f8fafc 100%);
			background-attachment: fixed;
		}
		.ssc-preview-shell { max-width: min(1920px, 98vw); margin: 0 auto; padding: 0 1.25rem 2rem; }
		/* Header */
		.header-main {
			background: linear-gradient(125deg, #0f2138 0%, var(--ssc-accent) 45%, #243a5c 100%);
			color: #f8fafc; padding: 0;
			box-shadow: 0 4px 24px rgba(15, 23, 42, 0.15);
		}
		.header-main__row {
			display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem 1.5rem;
			padding: 1.15rem 1.75rem 1.25rem;
		}
		.header-main__titlewrap { min-width: 0; }
		.header-main h1 { margin: 0; font-size: 1.35rem; font-weight: 700; letter-spacing: -0.02em; line-height: 1.2; }
		.header-main__tag { display: block; font-size: 0.7rem; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase; color: rgba(248, 250, 252, 0.55); margin-top: 0.35rem; }
		.header-main__actions { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }
		.header-main a.pdf-btn, .header-main button.pdf-btn {
			display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1rem; border-radius: 10px; font-size: 0.875rem; font-weight: 600;
			text-decoration: none; border: 1px solid rgba(255,255,255,0.22); background: rgba(255,255,255,0.97);
			color: var(--ssc-accent); box-shadow: 0 1px 2px rgba(0,0,0,0.06);
			cursor: pointer; font-family: inherit; transition: transform 0.12s, box-shadow 0.2s, background 0.2s;
		}
		.header-main a.pdf-btn:hover, .header-main button.pdf-btn:hover { background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.1); transform: translateY(-1px); }
		.header-main a.demo-btn, .header-main button.demo-btn {
			background: linear-gradient(180deg, #d1fae5 0%, #a7f3d0 100%); color: #065f46; border-color: rgba(6, 95, 70, 0.2);
		}
		.header-main a.demo-btn:hover, .header-main button.demo-btn:hover { background: #a7f3d0; }
		.header-main__sys {
			background: rgba(0,0,0,0.2); border-top: 1px solid rgba(255,255,255,0.1);
			padding: 0.65rem 1.75rem; font-size: 0.75rem; line-height: 1.5; color: rgba(248, 250, 252, 0.8);
		}
		.header-main__sys code {
			background: rgba(0,0,0,0.2); padding: 0.15rem 0.4rem; border-radius: 4px; font-size: 0.7rem; word-break: break-all;
		}
		.header-main .status-banner { margin: 0 1.75rem 1.25rem; padding: 0.7rem 1rem; border-radius: 10px; font-size: 0.9rem; }
		.header-main .status-banner--ok { background: rgba(22, 163, 74, 0.35); border: 1px solid rgba(74, 222, 128, 0.4); }
		.header-main .status-banner--err { background: rgba(185, 28, 28, 0.45); border: 1px solid rgba(252, 165, 165, 0.45); }
		.header-main .status-banner a { color: #fff; font-weight: 600; text-decoration: underline; }
		/* Main grid */
		.preview-main {
			display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr); gap: 1.5rem 1.75rem; padding: 1.5rem 0 0;
		}
		@media (max-width: 1024px) { .preview-main { grid-template-columns: 1fr; } }
		.ssc-preview-col-right { display: flex; flex-direction: column; gap: 1.25rem; min-width: 0; }
		/* Panels (cards) */
		.s-panel {
			background: var(--ssc-card); border-radius: 16px; border: 1px solid var(--ssc-card-edge);
			box-shadow: var(--ssc-sh); overflow: hidden; display: flex; flex-direction: column; min-height: 0; transition: box-shadow 0.25s;
		}
		.s-panel:hover { box-shadow: var(--ssc-sh-hover); }
		.s-panel__head {
			display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem;
			padding: 0.9rem 1.15rem; border-bottom: 1px solid var(--ssc-card-edge);
			background: linear-gradient(180deg, #fcfdff 0%, #f8fafc 100%);
		}
		.s-panel__head-title { display: flex; flex-direction: column; gap: 0.2rem; }
		.s-panel__head-title h2 { margin: 0; font-size: 1.05rem; font-weight: 700; letter-spacing: -0.02em; color: var(--ssc-ink); line-height: 1.2; }
		.s-panel__head-suptitle { font-size: 0.65rem; font-weight: 600; letter-spacing: 0.14em; text-transform: uppercase; color: var(--ssc-ink-faint); }
		.s-panel__body { padding: 1.1rem 1.15rem 1.2rem; flex: 1; }
		.s-panel--form .s-panel__head { border-left: 3px solid var(--ssc-accent); padding-left: 1rem; }
		.s-panel--log .s-panel__head { border-left: 3px solid #6366f1; }
		.s-panel--skjal .s-panel__head { border-left: 3px solid #d97706; }
		.s-panel--wp .s-panel__head { border-left: 3px solid #0d9488; }
		.s-panel--skjal .s-panel__body, .s-panel--wp .s-panel__body { padding-top: 0.85rem; }
		.s-panel--wp__p { margin: 0 0 0.9rem; color: var(--ssc-ink-mid); font-size: 0.9rem; line-height: 1.55; }
		.s-panel--wp__p:last-child { margin-bottom: 0; }
		.s-panel--wp__ul { margin: 0.35rem 0 0.85rem; padding-left: 1.15rem; color: var(--ssc-ink-mid); font-size: 0.88rem; line-height: 1.5; }
		.s-panel--wp__links { display: flex; flex-wrap: wrap; gap: 0.5rem 0.75rem; margin-bottom: 0.9rem; }
		.s-panel--wp__links a { display: inline-flex; align-items: center; padding: 0.45rem 0.9rem; border-radius: 8px; font-size: 0.86rem; font-weight: 600; text-decoration: none; border: 1px solid var(--ssc-card-edge); background: #f8fafc; color: var(--ssc-accent); transition: background 0.15s, box-shadow 0.15s; }
		.s-panel--wp__links a:hover { background: #fff; box-shadow: 0 1px 6px rgba(15, 23, 42, 0.08); }
		.s-panel--wp__links a.s-panel--wp__link--hi { background: var(--ssc-accent); color: #fff; border-color: var(--ssc-accent); }
		.s-panel--wp__links a.s-panel--wp__link--hi:hover { background: var(--ssc-accent-hi); border-color: var(--ssc-accent-hi); }
		.s-panel--wp__hint { display: block; font-size: 0.75rem; color: var(--ssc-ink-faint); line-height: 1.5; }
		.s-panel--skjal__row { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }
		.s-panel--skjal__row a { display: inline-flex; align-items: center; padding: 0.4rem 0.8rem; border-radius: 8px; font-size: 0.86rem; font-weight: 600; text-decoration: none; border: 1px solid #e2e8f0; background: #fff; color: var(--ssc-accent); }
		.s-panel--skjal__row a:hover { background: #f8fafc; }
		.s-panel--log__terminal {
			margin: 0; background: #0a0c10; color: #a7e8b8; padding: 1rem 1.1rem; border-radius: 10px; max-height: 48vh; overflow: auto; white-space: pre-wrap; word-break: break-word;
			font: 12px/1.55 ui-monospace, "SFMono-Regular", Menlo, Consolas, monospace; border: 1px solid #1a1f2e;
			box-shadow: inset 0 1px 0 rgba(255,255,255,0.04);
		}
		.s-term-bar {
			display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.5rem; padding: 0.35rem 0 0.6rem; border-bottom: 1px solid #1f2838; font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b;
		}
		.s-term-dot { width: 10px; height: 10px; border-radius: 50%; }
		.s-term-dot--r { background: #f87171; } .s-term-dot--y { background: #fbbf24; } .s-term-dot--g { background: #4ade80; }
		/* Data section full width */
		.s-data-wrap { padding: 1.5rem 0 0; }
		.s-data-panel { border-radius: 16px; border: 1px solid var(--ssc-card-edge); background: var(--ssc-card); box-shadow: var(--ssc-sh); overflow: hidden; }
		.s-data-panel .s-panel__head { border-left: 3px solid #0d9488; }
		.s-data-panel .s-toolbar { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 0.75rem; }
		.s-data-panel table { width: 100%; border-collapse: collapse; }
		.s-data-panel th, .s-data-panel td { text-align: left; padding: 0.7rem 0.9rem; border-bottom: 1px solid #f1f5f9; vertical-align: top; font-size: 0.875rem; }
		.s-data-panel tbody tr { transition: background 0.12s; }
		.s-data-panel tbody tr:hover { background: #f8fafc; }
		.s-data-panel th { background: #f1f5f9; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; }
		.s-data-panel tr:last-child td { border-bottom: 0; }
		.s-empty { padding: 2.5rem; text-align: center; color: var(--ssc-ink-mid); font-size: 0.95rem; }
		.s-empty strong { color: var(--ssc-ink); }
		/* Buttons in panels */
		button, .s-btn-ghost { font: inherit; padding: 0.45rem 0.8rem; border: 1px solid #d1d5db; background: #fff; border-radius: 8px; cursor: pointer; font-size: 0.85rem; transition: background 0.15s, border-color 0.15s; }
		button.danger { color: #b03a2e; border-color: #fecaca; background: #fff5f5; }
		button.danger:hover { background: #fee2e2; }
		button:not(.primary):not(.danger):hover { background: #f8fafc; }
		.button.primary, button.primary, a.button.primary { background: var(--ssc-accent) !important; color: #fff !important; border-color: var(--ssc-accent) !important; }
		a.button.primary { display: inline-block; text-decoration: none; line-height: 1.2; }
		.button.primary:hover, button.primary:hover, a.button.primary:hover { background: var(--ssc-accent-hi) !important; }
		select { font: inherit; padding: 0.35rem 0.5rem; border: 1px solid #d1d5db; border-radius: 6px; background: #fff; font-size: 0.8rem; }
		.pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; background: #e3e6ea; color: #2c3e50; }
		.pill-received { background: #fff3cd; color: #856404; }
		.pill-processing { background: #d1ecf1; color: #0c5460; }
		.pill-delivered { background: #d4edda; color: #155724; }
		.pill-cancelled { background: #f8d7da; color: #721c24; }
		/* Form: section inside .s-panel__body should not add double padding to .ssc-form */
		.s-panel--form .s-panel__body .ssc-form { max-width: none; }
		<?php echo $frontend_css; ?>
	</style>
</head>
<body>
	<div class="ssc-preview-shell">
	<header class="header-main">
		<div class="header-main__row">
			<div class="header-main__titlewrap">
				<span class="header-main__tag">Steinum Sport</span>
				<h1>Lokal próving</h1>
			</div>
			<div class="header-main__actions">
				<button type="button" class="pdf-btn demo-btn" id="ssc-preview-demo-btn" title="Havnar Róðrarfelag, kontakt, rokning + 1–5 tilvildarligar bíleggingar.">Dæmi-data</button>
				<?php if ( '' !== $ssc_open_pdf_href ) : ?>
					<a class="pdf-btn" href="<?php echo esc_url( $ssc_open_pdf_href ); ?>" target="_blank" rel="noopener">Sí seinasta PDF</a>
				<?php endif; ?>
				<?php if ( '' !== $ssc_open_xls_href ) : ?>
					<a class="pdf-btn" href="<?php echo esc_url( $ssc_open_xls_href ); ?>" target="_blank" rel="noopener" download>Seinasta Excel</a>
				<?php endif; ?>
			</div>
		</div>
		<div class="header-main__sys">
			Próvingarhamur <strong>ON</strong> · Dry-run <strong>ON</strong>
			<br />
			<span style="color:rgba(248,250,252,0.65);">Dátugrunnur</span> <code><?php echo esc_html( $ssc_db_file ); ?></code>
			<br />
			<span style="color:rgba(248,250,252,0.65);">Log</span> <code><?php echo esc_html( SSC_Logger::path() ); ?></code>
		</div>
		<?php if ( '' !== $status_msg ) : ?>
			<div class="status-banner status-banner--<?php echo $status_kind === 'ok' ? 'ok' : 'err'; ?>">
				<?php echo esc_html( $status_msg ); ?>
				<?php if ( 'ok' === $status_kind && '' !== $ssc_open_pdf_href ) : ?>
					<span> — <a href="<?php echo esc_url( $ssc_open_pdf_href ); ?>" target="_blank" rel="noopener">Sí PDF</a></span>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</header>

	<main class="preview-main">
		<section class="s-panel s-panel--form">
			<header class="s-panel__head">
				<div class="s-panel__head-title">
					<span class="s-panel__head-suptitle">Inntak</span>
					<h2>Skrásetingarformular</h2>
				</div>
			</header>
			<div class="s-panel__body">
				<?php echo $form_html; ?>
			</div>
		</section>

		<div class="ssc-preview-col-right">
		<section class="s-panel s-panel--log">
			<header class="s-panel__head s-toolbar">
				<div class="s-panel__head-title">
					<span class="s-panel__head-suptitle">Frágongd</span>
					<h2>Teldupostar loggur</h2>
				</div>
				<form method="post" class="s-btn-ghost" style="margin:0;border:0;padding:0;background:transparent;box-shadow:none;">
					<input type="hidden" name="ssc_preview_action" value="clear_log" />
					<button type="submit" style="box-shadow:none;">Tøm log</button>
				</form>
			</header>
			<div class="s-panel__body" style="padding-top:0.5rem;">
				<div class="s-term-bar">
					<span class="s-term-dot s-term-dot--r" aria-hidden="true"></span>
					<span class="s-term-dot s-term-dot--y" aria-hidden="true"></span>
					<span class="s-term-dot s-term-dot--g" aria-hidden="true"></span>
					<span>roynd@localhost</span>
				</div>
				<pre class="s-panel--log__terminal" role="log" aria-label="Email log"><?php echo '' === trim( $log_text ) ? '(tóm — sendi formuna fyrst)' : esc_html( $log_text ); ?></pre>
			</div>
		</section>

		<section class="s-panel s-panel--skjal" aria-label="Seinastu skjal">
			<header class="s-panel__head">
				<div class="s-panel__head-title">
					<span class="s-panel__head-suptitle">Skjal</span>
					<h2>Seinasta PDF &amp; Excel</h2>
				</div>
			</header>
			<div class="s-panel__body">
				<?php if ( '' !== $ssc_open_pdf_href || '' !== $ssc_open_xls_href ) : ?>
					<div class="s-panel--skjal__row">
						<?php if ( '' !== $ssc_open_pdf_href ) : ?>
							<a href="<?php echo esc_url( $ssc_open_pdf_href ); ?>" target="_blank" rel="noopener">Sí seinasta PDF</a>
						<?php endif; ?>
						<?php if ( '' !== $ssc_open_xls_href ) : ?>
							<a href="<?php echo esc_url( $ssc_open_xls_href ); ?>" target="_blank" rel="noopener" download>Tak niður Excel</a>
						<?php endif; ?>
					</div>
					<p class="s-panel--wp__p" style="margin-top:0.75rem;">Hetta eru skjalin frá <strong>seinastu innsend</strong> á hesum próvingarserverinum — tað samsvarar teldupostinum í logginum.</p>
				<?php else : ?>
					<p class="s-panel--wp__p">Ongi skjal enn. <strong>Send eina innsend</strong>, so koma leinkjurnar higar (og í headerin).</p>
				<?php endif; ?>
			</div>
		</section>

		<section class="s-panel s-panel--wp" aria-label="WordPress stjórn">
			<header class="s-panel__head">
				<div class="s-panel__head-title">
					<span class="s-panel__head-suptitle">Verndandi síða</span>
					<h2>WordPress stjórn</h2>
				</div>
			</header>
			<div class="s-panel__body">
				<p class="s-panel--wp__p">Henda síðan er <strong>lokal roynd</strong> — ikki WordPress. Á verndandi WordPress-síðu finnur tú baksíðu við yvirlit yvir fráboðanir, leiting og stillingar.</p>
				<div class="s-panel--wp__links">
					<a class="s-panel--wp__link--hi" href="<?php echo esc_url( $ssc_url_submissions ); ?>" target="_blank" rel="noopener">Fráboðanir (listi)</a>
					<a href="<?php echo esc_url( $ssc_url_settings ); ?>" target="_blank" rel="noopener">Stillingar</a>
				</div>
				<p class="s-panel--wp__p">Á síðu <em>Fráboðanir</em> sært tú (í WordPress) tabellu við: dato, felag, bátur, nøgd, kontakt, teldupost, PDF, støða; filtreringar eftir støðu, leit og dagsetning — í líknandi stíl sum WordPress-«posts»-listi.</p>
				<ul class="s-panel--wp__ul">
					<li><strong>Stillingar</strong> — teldupost, PDF og tøk skjal.</li>
					<li><strong>Leinkja-uppskoti</strong> — tín WP-adressa er nýtt undir. Um port ella host er onnur, set omgjørd: <code style="font-size:0.8rem;background:#f1f5f9;padding:0.1rem 0.35rem;border-radius:4px;">SSC_WP_SITEURL</code> (t.d. <code style="font-size:0.8rem;background:#f1f5f9;padding:0.1rem 0.35rem;border-radius:4px;">http://localhost:8888</code>).</li>
				</ul>
				<span class="s-panel--wp__hint">URL-exempul (<?php echo esc_html( $ssc_wp_base ); ?>): <code style="word-break: break-all;">/wp-admin/admin.php?page=<?php echo esc_html( SSC_Admin_Submissions::PAGE ); ?></code></span>
			</div>
		</section>
		</div>
	</main>

	<div class="s-data-wrap">
		<div class="s-data-panel">
			<header class="s-panel__head s-toolbar">
				<div class="s-panel__head-title">
					<span class="s-panel__head-suptitle">Dátugrunnur</span>
					<h2>Goymdar fráboðanir (<?php echo (int) $submissions['total']; ?>)</h2>
				</div>
				<form method="post" onsubmit="return confirm('Strika allar fráboðanir?');" style="margin:0;">
					<input type="hidden" name="ssc_preview_action" value="delete_all" />
					<button type="submit" class="danger">Strika allar</button>
				</form>
			</header>
			<?php if ( ! $submissions['rows'] ) : ?>
				<div class="s-empty">Ongar goymdar fráboðanir enn. <strong>Send eina innsend</strong> — hon kemur higar.</div>
			<?php else : ?>
				<table>
					<thead>
						<tr>
							<th>#</th>
							<th>Dato</th>
							<th>Felag</th>
							<th>Bátur</th>
							<th>Nøgd</th>
							<th>Teldupostur</th>
							<th>Støða</th>
							<th>PDF</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $submissions['rows'] as $row ) : ?>
							<tr>
								<td>#<?php echo (int) $row['id']; ?></td>
								<td><?php echo esc_html( (string) $row['created_at'] ); ?></td>
								<td><strong><?php echo esc_html( (string) $row['club_name'] ); ?></strong><br>
									<small style="color:#6b7280;"><?php echo esc_html( (string) $row['contact_name'] ); ?> · <?php echo esc_html( (string) $row['phone'] ); ?></small></td>
								<td><?php echo esc_html( (string) $row['boat_name'] ); ?></td>
								<td><?php echo (int) SSC_Store::total_qty_from_row( $row ); ?></td>
								<td><?php echo esc_html( (string) $row['billing_email'] ); ?></td>
								<td>
									<form method="post" style="margin:0;">
										<input type="hidden" name="ssc_preview_action" value="set_status" />
										<input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>" />
										<select name="status" onchange="this.form.submit()">
											<?php foreach ( SSC_Store::statuses() as $k => $label ) : ?>
												<option value="<?php echo esc_attr( $k ); ?>" <?php echo $row['status'] === $k ? 'selected' : ''; ?>><?php echo esc_html( $label ); ?></option>
											<?php endforeach; ?>
										</select>
									</form>
								</td>
								<td>
									<?php if ( SSC_Store::row_has_viewable_pdf( $row ) ) : ?>
										<a class="button primary" style="text-decoration:none;display:inline-block;" href="/?ssc_preview_pdf=<?php echo (int) $row['id']; ?>" target="_blank" rel="noopener">Sí PDF</a>
									<?php else : ?>
										<span style="color:#9ca3af">—</span>
									<?php endif; ?>
								</td>
								<td>
									<form method="post" style="margin:0;" onsubmit="return confirm('Strika hesa fráboðanina?');">
										<input type="hidden" name="ssc_preview_action" value="delete_one" />
										<input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>" />
										<button type="submit" class="danger">Strika</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>
	</div>
	<script>
	(function () {
		var btn = document.getElementById('ssc-preview-demo-btn');
		if (!btn) { return; }
		btn.addEventListener('click', function () {
			btn.disabled = true;
			fetch('?ssc_demo_json=1', { credentials: 'same-origin' })
				.then(function (r) {
					if (!r.ok) { throw new Error('no'); }
					return r.json();
				})
				.then(applySscPreviewDemo)
				.catch(function () {
					window.alert('Dæmi fekkst ikki hent. Ert tú á lokal próving (php -S … tests/serve.php) og er serverin í gongd?');
				})
				.finally(function () { btn.disabled = false; });
		});
		function applySscPreviewDemo(d) {
			var setv = function (id, v) {
				var el = document.getElementById(id);
				if (el) { el.value = v != null ? v : ''; }
			};
			setv('ssc-club_name', d.club_name);
			setv('ssc-boat_name', d.boat_name);
			setv('ssc-contact_name', d.contact_name);
			setv('ssc-contact_email', d.contact_email);
			setv('ssc-phone', d.phone);
			setv('ssc-billing_email', d.billing_email);
			var c = document.getElementById('ssc-line-rows');
			var add = document.getElementById('ssc-add-line');
			if (!c || !add) { return; }
			var lines = d.order_lines || [];
			var want = lines.length;
			if (want < 1) { want = 1; }
			while (c.querySelectorAll('.ssc-line-row').length > want) {
				var rows = c.querySelectorAll('.ssc-line-row');
				if (rows.length < 2) { break; }
				var b = rows[rows.length - 1].querySelector('.ssc-line-close');
				if (b) { b.click(); }
			}
			while (c.querySelectorAll('.ssc-line-row').length < want) { add.click(); }
			lines.forEach(function (ln, i) {
				var row = c.querySelectorAll('.ssc-line-row')[i];
				if (!row) { return; }
				var it = row.querySelector('.ssc-line-item');
				if (it) {
					it.value = ln.item;
					if (it.dispatchEvent) { it.dispatchEvent(new Event('change', { bubbles: true })); }
					if (window.sscLineItemChanged) {
						try { window.sscLineItemChanged(it); } catch (e) {}
					}
				}
			});
			setTimeout(function () {
				lines.forEach(function (ln, i) {
					var row = c.querySelectorAll('.ssc-line-row')[i];
					if (!row) { return; }
					var g = row.querySelector('.ssc-line-gender');
					if (g && !g.disabled && ln.gender) { g.value = ln.gender; }
					var sz = row.querySelector('.ssc-line-size');
					if (sz && !sz.disabled && ln.size) { sz.value = ln.size; }
					var bp = row.querySelector('.ssc-line-bump');
					if (bp && !bp.disabled && ln.bumper_color) { bp.value = ln.bumper_color; }
					var q = row.querySelector('.ssc-line-qty');
					if (q) {
						q.value = String(ln.qty);
						if (q.removeAttribute) { q.removeAttribute('disabled'); }
					}
					var nm = row.querySelector('.ssc-line-name');
					if (nm) { nm.value = ln.name != null ? ln.name : ''; }
					var is = row.querySelector('.ssc-line-item');
					if (is && window.sscLineItemChanged) {
						try { window.sscLineItemChanged(is); } catch (e) {}
					}
				});
			}, 0);
		}
	}());
	</script>
</body>
</html>
