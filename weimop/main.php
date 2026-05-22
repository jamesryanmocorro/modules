<?php
/**
 * Module Name: WESM/EIMOP Trading Dashboard
 * Module Slug: weimop
 * Description: WESM/EIMOP real-time monitoring. Fetches live + historical data from IEMOP NMMS MPI
 *              via SOAP/HTTPS with PFX mutual-TLS authentication.
 *              SQLite auto-created at uploads/weimop/weimop.sqlite.
 * Version: 8.0.0
 * Author: BNTM
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'BNTM_WEIMOP_PATH',       dirname( __FILE__ ) . '/' );
define( 'BNTM_WEIMOP_URL',        plugin_dir_url( __FILE__ ) );
define( 'BNTM_WEIMOP_PFX_SUBDIR', 'pfx_folder' );
define( 'BNTM_WEIMOP_CONF_SUBDIR','weimop_conf' );
define( 'BNTM_WEIMOP_DB_SUBDIR',  'weimop' );
define( 'BNTM_WEIMOP_DB_FILE',    'weimop.sqlite' );
define( 'BNTM_WEIMOP_DEFAULT_EXPORT_CONF', 'D:/download/Dashboard_2025-09-08/Dashboard_2025-09-08/NMMS MPI Web Services Data Extract Guide/ExportResultsConf.xml' );
define( 'BNTM_WEIMOP_VER',        '8.0.0' );

/* -------------------------------------------------------
   A. PATH HELPERS
------------------------------------------------------- */

function bntm_weimop_db_path() {
    $upload = wp_upload_dir();
    $dir    = trailingslashit( $upload['basedir'] ) . BNTM_WEIMOP_DB_SUBDIR;
    if ( ! file_exists( $dir ) ) {
        wp_mkdir_p( $dir );
        file_put_contents( $dir . '/.htaccess', "Deny from all\n" );
        file_put_contents( $dir . '/index.php',  '<?php // silence' );
    }
    return $dir . '/' . BNTM_WEIMOP_DB_FILE;
}

function bntm_weimop_pfx_dir() {
    $upload = wp_upload_dir();
    $dir    = trailingslashit( $upload['basedir'] ) . BNTM_WEIMOP_PFX_SUBDIR;
    if ( ! file_exists( $dir ) ) {
        wp_mkdir_p( $dir );
        file_put_contents( $dir . '/.htaccess', "Deny from all\n" );
        file_put_contents( $dir . '/index.php',  '<?php // silence' );
    }
    return $dir;
}

function bntm_weimop_pem_dir() {
    $dir = bntm_weimop_pfx_dir() . '/pem';
    if ( ! file_exists( $dir ) ) wp_mkdir_p( $dir );
    return $dir;
}

function bntm_weimop_conf_dir() {
    $upload = wp_upload_dir();
    $dir    = trailingslashit( $upload['basedir'] ) . BNTM_WEIMOP_CONF_SUBDIR;
    if ( ! file_exists( $dir ) ) {
        wp_mkdir_p( $dir );
        file_put_contents( $dir . '/.htaccess', "Deny from all\n" );
        file_put_contents( $dir . '/index.php',  '<?php // silence' );
    }
    return $dir;
}

/* -------------------------------------------------------
   B. SQLITE SCHEMA + OPEN
------------------------------------------------------- */

function bntm_weimop_sqlite_schema() {
    return [
        'RTDSchedules' => "CREATE TABLE IF NOT EXISTS RTDSchedules (
            TIME_INTERVAL DATE UNIQUE,
            RESOURCE_NAME TEXT,
            SCHEDULE TEXT,
            LMP TEXT,
            LOSS_FACTOR TEXT,
            LMP_ENERGY TEXT,
            LMP_LOSS TEXT,
            LMP_CONGESTION TEXT
        );",
        'HAPSchedules' => "CREATE TABLE IF NOT EXISTS HAPSchedules (
            TIME_INTERVAL DATE UNIQUE,
            RESOURCE_NAME TEXT,
            SCHEDULE TEXT,
            LMP TEXT,
            LOSS_FACTOR TEXT,
            LMP_ENERGY TEXT,
            LMP_LOSS TEXT,
            LMP_CONGESTION TEXT
        );",
        'HAPResults' => "CREATE TABLE IF NOT EXISTS HAPResults (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            TIME_INTERVAL TEXT NOT NULL UNIQUE,
            PRICE REAL DEFAULT 0, PRICE_NODE TEXT DEFAULT '', MARKET_RUN TEXT DEFAULT '',
            created_at TEXT DEFAULT (datetime('now'))
        );",
        'DAPResults' => "CREATE TABLE IF NOT EXISTS DAPResults (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            TIME_INTERVAL TEXT NOT NULL UNIQUE,
            PRICE REAL DEFAULT 0, PRICE_NODE TEXT DEFAULT '', MARKET_RUN TEXT DEFAULT '',
            created_at TEXT DEFAULT (datetime('now'))
        );",
        'OCCResourcesComplianceDetail' => "CREATE TABLE IF NOT EXISTS OCCResourcesComplianceDetail (
            TIME_INTERVAL TEXT UNIQUE,
            RESOURCE_NAME TEXT,
            REGISTERED_CAP INTEGER,
            OFFERED_CAP INTEGER,
            NON_COMPLIANCE_FLAG TEXT,
            NON_COMPLIANCE_COUNT INTEGER,
            PROBABLE_BREACH TEXT,
            PREVIOUS_VIOLATION TEXT,
            OCC_ENABLED TEXT,
            COMPLIANCE_EXEMPT TEXT,
            EMAIL_SENT INTEGER DEFAULT 0
        );",
        'ExtractorLog' => "CREATE TABLE IF NOT EXISTS ExtractorLog (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            run_time TEXT DEFAULT (datetime('now')),
            status TEXT DEFAULT 'ok', message TEXT DEFAULT '', rows_added INTEGER DEFAULT 0
        );",
        'iemop_market_log' => "CREATE TABLE IF NOT EXISTS iemop_market_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            dataset TEXT NOT NULL,
            filename TEXT NOT NULL,
            file_date TEXT NOT NULL,
            rows_imported INTEGER DEFAULT 0,
            imported_at TEXT DEFAULT (datetime('now')),
            UNIQUE(dataset, filename)
        );",
        'iemop_rtd_mcp' => "CREATE TABLE IF NOT EXISTS iemop_rtd_mcp (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            file_date     TEXT NOT NULL,
            time_interval TEXT,
            resource_name TEXT,
            region        TEXT,
            commodity_type TEXT,
            marginal_price REAL,
            raw_json      TEXT,
            UNIQUE(file_date, time_interval, resource_name, commodity_type)
        );",
        'iemop_rtd_regional' => "CREATE TABLE IF NOT EXISTS iemop_rtd_regional (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            file_date      TEXT NOT NULL,
            time_interval  TEXT,
            region         TEXT,
            commodity_type TEXT,
            generation     REAL,
            mkt_reqt       REAL,
            mkt_import     REAL,
            mkt_export     REAL,
            raw_json       TEXT,
            UNIQUE(file_date, time_interval, region, commodity_type)
        );",
        'iemop_rtd_reserve_mcp' => "CREATE TABLE IF NOT EXISTS iemop_rtd_reserve_mcp (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            file_date      TEXT NOT NULL,
            time_interval  TEXT,
            resource_name  TEXT,
            region         TEXT,
            commodity_type TEXT,
            marginal_price REAL,
            raw_json       TEXT,
            UNIQUE(file_date, time_interval, resource_name, commodity_type)
        );",
        'iemop_rtd_congestion' => "CREATE TABLE IF NOT EXISTS iemop_rtd_congestion (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            file_date       TEXT NOT NULL,
            time_interval   TEXT,
            equipment_name  TEXT,
            station_name    TEXT,
            congest_type    TEXT,
            binding_limit   REAL,
            mw_flow         REAL,
            overload_mw     REAL,
            pct_mw          REAL,
            raw_json        TEXT,
            UNIQUE(file_date, time_interval, equipment_name)
        );",
        'iemop_rtd_reserve_sched' => "CREATE TABLE IF NOT EXISTS iemop_rtd_reserve_sched (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            file_date      TEXT NOT NULL,
            time_interval  TEXT,
            resource_name  TEXT,
            region         TEXT,
            commodity_type TEXT,
            sched_mw       REAL,
            price          REAL,
            raw_json       TEXT,
            UNIQUE(file_date, time_interval, resource_name, commodity_type)
        );",
    ];
}

function bntm_weimop_open_db( $readonly = false ) {
    if ( ! class_exists( 'SQLite3' ) ) return null;
    $path  = bntm_weimop_db_path();
    $flags = $readonly ? SQLITE3_OPEN_READONLY : ( SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE );
    try {
        $db = new SQLite3( $path, $flags );
        $db->enableExceptions( true );
        if ( ! $readonly ) {
            $db->exec( 'PRAGMA journal_mode=WAL;' );
            foreach ( bntm_weimop_sqlite_schema() as $sql ) $db->exec( $sql );
        }
        return $db;
    } catch ( Exception $e ) { return null; }
}

/* -------------------------------------------------------
   C. WP TABLE CONFIG
------------------------------------------------------- */

function bntm_weimop_get_pages()     { return [ 'WESM/EIMOP Trading Dashboard' => '[weimop_dashboard]' ]; }
function bntm_weimop_get_shortcodes(){ return [ 'weimop_dashboard' => 'bntm_shortcode_weimop' ]; }

function bntm_weimop_get_tables() {
    global $wpdb; $c = $wpdb->get_charset_collate(); $p = $wpdb->prefix;
    return [
        'weimop_watchlist' => "CREATE TABLE {$p}weimop_watchlist (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            instrument VARCHAR(64) NOT NULL, note TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id)
        ) {$c};",
    ];
}

function bntm_weimop_create_tables() {
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    foreach ( bntm_weimop_get_tables() as $sql ) dbDelta( $sql );
    bntm_weimop_open_db( false );
}

/* -------------------------------------------------------
   D. AJAX HOOKS
------------------------------------------------------- */

add_action( 'wp_ajax_weimop_get_market_snapshot', 'bntm_ajax_weimop_get_market_snapshot' );
add_action( 'wp_ajax_weimop_get_chart_series',    'bntm_ajax_weimop_get_chart_series'    );
add_action( 'wp_ajax_weimop_get_hap_series',      'bntm_ajax_weimop_get_hap_series'      );
add_action( 'wp_ajax_weimop_get_dap_series',      'bntm_ajax_weimop_get_dap_series'      );
add_action( 'wp_ajax_weimop_get_occ_series',      'bntm_ajax_weimop_get_occ_series'      );
add_action( 'wp_ajax_weimop_connection_status',   'bntm_ajax_weimop_connection_status'   );
add_action( 'wp_ajax_weimop_save_settings',       'bntm_ajax_weimop_save_settings'       );
add_action( 'wp_ajax_weimop_upload_pfx',          'bntm_ajax_weimop_upload_pfx'          );
add_action( 'wp_ajax_weimop_upload_export_conf',  'bntm_ajax_weimop_upload_export_conf'  );
add_action( 'wp_ajax_weimop_fetch_historical',    'bntm_ajax_weimop_fetch_historical'    );
add_action( 'wp_ajax_weimop_diag',               'bntm_ajax_weimop_diag'               );
add_action( 'wp_ajax_weimop_fetch_market_data',  'bntm_ajax_weimop_fetch_market_data'  );
add_action( 'wp_ajax_weimop_get_md_chart',          'bntm_ajax_weimop_get_md_chart'          );
add_action( 'wp_ajax_weimop_get_md_regions',        'bntm_ajax_weimop_get_md_regions'        );
add_action( 'wp_ajax_weimop_get_md_resources',      'bntm_ajax_weimop_get_md_resources'      );
add_action( 'wp_ajax_weimop_import_all_files',      'bntm_ajax_weimop_import_all_files'      );
add_action( 'wp_ajax_weimop_get_trading_series',    'bntm_ajax_weimop_get_trading_series'    );
add_action( 'weimop_md_auto_fetch_cron',         'bntm_weimop_md_cron_run'             );
add_filter( 'cron_schedules',                    function( $s ) {
    if ( ! isset( $s['weimop_hourly'] ) ) {
        $s['weimop_hourly'] = [ 'interval' => 3600, 'display' => 'WEIMOP Hourly' ];
    }
    return $s;
} );

/* -------------------------------------------------------
   E. SHORTCODE REGISTRATION
------------------------------------------------------- */

function bntm_weimop_register_shortcodes() {
    add_shortcode( 'weimop_dashboard', 'bntm_shortcode_weimop' );
}
add_action( 'init', 'bntm_weimop_register_shortcodes' );

/* -------------------------------------------------------
   F. MAIN SHORTCODE
   All CSS, external scripts, config, and JS are loaded
   directly here. No separate enqueue function.
   Config uses <script type="application/json"> so WP
   filters (wptexturize, wpautop) never touch it.
   JS is output as a PHP heredoc string, also filter-safe.
------------------------------------------------------- */

function bntm_shortcode_weimop() {
    if ( ! is_user_logged_in() ) return '<p>Please log in to view this dashboard.</p>';

    bntm_weimop_open_db( false );

    $uid        = get_current_user_id();
    $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'overview';
    $settings   = bntm_weimop_get_settings( $uid );

    $config = wp_json_encode( [
        'ajaxurl'   => admin_url( 'admin-ajax.php' ),
        'nonce'     => wp_create_nonce( 'weimop_nonce' ),
        'refreshMs' => max( 60, (int) $settings['refresh_interval'] ) * 1000,
        'tab'       => $active_tab,
    ] );

    ob_start();

    echo '<style>' . bntm_weimop_get_css() . '</style>';
    ?>
    <script src="https://unpkg.com/lightweight-charts@4.2.3/dist/lightweight-charts.standalone.production.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/node-forge@1.3.1/dist/forge.min.js"></script>
    <script type="application/json" id="weimop-config-data"><?php echo $config; ?></script>

    <div class="weimop-wrap">

        <div id="weimop-conn-banner" class="weimop-banner weimop-banner--checking">
            <span id="weimop-conn-icon" class="weimop-banner__icon"></span>
            <span id="weimop-conn-text">Checking connection...</span>
            <a href="?tab=settings" id="weimop-conn-fix" class="weimop-banner__link" style="display:none;">Go to Settings</a>
        </div>

        <nav class="weimop-tabs">
            <a href="?tab=overview"    class="weimop-tab <?php echo $active_tab === 'overview'    ? 'is-active' : ''; ?>">Market Charts</a>
            <a href="?tab=trading"     class="weimop-tab <?php echo $active_tab === 'trading'     ? 'is-active' : ''; ?>">Trading</a>
            <a href="?tab=suggestions" class="weimop-tab <?php echo $active_tab === 'suggestions' ? 'is-active' : ''; ?>">Suggestions</a>
            <a href="?tab=marketdata"  class="weimop-tab <?php echo $active_tab === 'marketdata'  ? 'is-active' : ''; ?>">Market Data</a>
            <a href="?tab=settings"    class="weimop-tab <?php echo $active_tab === 'settings'    ? 'is-active' : ''; ?>">Settings</a>
        </nav>

        <div class="weimop-content">
            <?php
            if     ( $active_tab === 'overview'    ) echo weimop_tab_overview();
            elseif ( $active_tab === 'trading'     ) echo weimop_tab_trading();
            elseif ( $active_tab === 'suggestions' ) echo weimop_tab_suggestions();
            elseif ( $active_tab === 'marketdata'  ) echo weimop_tab_market_data();
            elseif ( $active_tab === 'settings'    ) echo weimop_tab_settings( $uid, $settings );
            ?>
        </div>
    </div>

    <script><?php echo bntm_weimop_get_js(); ?></script>
    <?php
    $out = ob_get_clean();
    return function_exists( 'bntm_universal_container' )
        ? bntm_universal_container( 'WESM/EIMOP Trading Dashboard', $out )
        : $out;
}

/* -------------------------------------------------------
   G. TAB -- OVERVIEW
------------------------------------------------------- */

function weimop_tab_overview() { ob_start(); ?>
    <div class="weimop-card">
        <div class="weimop-card__header">
            <h3 class="weimop-card__title">Live Market Snapshot</h3>
            <div class="weimop-card__actions">
                <span id="weimop-last-fetch" class="weimop-meta-text">--</span>
                <button type="button" id="weimop-refresh-all" class="weimop-btn weimop-btn--sm">Refresh</button>
                <span id="weimop-auto-badge" class="weimop-badge weimop-badge--live">Live</span>
            </div>
        </div>
        <div id="weimop-fetch-status" class="weimop-inline-notice" style="display:none;"></div>
        <div class="weimop-kpi-grid" id="weimop-snapshot">
            <?php foreach ( [
                'last_interval' => [ 'Last Interval',         'PHT'     ],
                'rtd_schedule'  => [ 'RTD Schedule',           'MW'      ],
                'lmp_price'     => [ 'LMP Price',              'PHP/MWh' ],
                'offered_cap'   => [ 'Offered Capacity (OCC)', 'MW'      ],
                'hap_price'     => [ 'HAP Price',              'PHP/MWh' ],
                'dap_price'     => [ 'DAP Price',              'PHP/MWh' ],
            ] as $key => [ $label, $unit ] ): ?>
            <div class="weimop-kpi">
                <span class="weimop-kpi__label"><?php echo esc_html( $label ); ?></span>
                <span class="weimop-kpi__value" data-key="<?php echo esc_attr( $key ); ?>">--</span>
                <span class="weimop-kpi__unit"><?php echo esc_html( $unit ); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php foreach ( [
        'weimop-rtd-chart' => [ 'RTD Schedule vs LMP',              'RTDSchedules &middot; Last 288 intervals (~24 h at 5-min cadence)'     ],
        'weimop-hap-chart' => [ 'HAP &mdash; Hourly Average Price',  'HAPResults &middot; Last 48 hours'                                     ],
        'weimop-dap-chart' => [ 'DAP &mdash; Day-Ahead Price',       'DAPResults &middot; Next 24-hour projection'                           ],
        'weimop-occ-chart' => [ 'OCC &mdash; Offered vs Scheduled',  'OCCResourcesComplianceDetail &middot; Last 288 intervals'              ],
    ] as $id => [ $title, $source ] ): ?>
    <div class="weimop-card">
        <div class="weimop-card__header">
            <h3 class="weimop-card__title"><?php echo $title; ?></h3>
        </div>
        <div id="<?php echo esc_attr( $id ); ?>" class="weimop-chart-wrap">
            <div class="weimop-chart-empty" id="<?php echo esc_attr( $id ); ?>-placeholder">
                <p>Loading...</p>
            </div>
        </div>
        <p class="weimop-source-label">Source: <code><?php echo $source; ?></code></p>
    </div>
    <?php endforeach;
    return ob_get_clean();
}

/* -------------------------------------------------------
   H. TAB -- TRADING / SUGGESTIONS
------------------------------------------------------- */

function weimop_tab_trading() {
    ob_start();
    $db       = bntm_weimop_open_db( true );
    $datasets = bntm_weimop_md_datasets();
    $date_ranges = [];
    $regions_by_ds   = [];
    $resources_by_ds  = [];
    if ( $db ) {
        foreach ( $datasets as $key => $ds ) {
            $r = $db->querySingle( "SELECT MIN(file_date) AS min_d, MAX(file_date) AS max_d FROM {$ds['table']}", true );
            if ( is_array( $r ) && ! empty( $r['max_d'] ) ) $date_ranges[ $key ] = $r;
            // Collect distinct regions where applicable
            $region_col = null;
            if ( in_array( $key, [ 'mcp', 'regional', 'reserve_mcp', 'reserve_sched' ], true ) ) $region_col = 'region';
            if ( $region_col ) {
                try {
                    $rres = $db->query( "SELECT DISTINCT {$region_col} FROM {$ds['table']} WHERE {$region_col} IS NOT NULL AND {$region_col} != '' ORDER BY {$region_col} ASC" );
                    while ( $row = $rres->fetchArray( SQLITE3_NUM ) ) {
                        if ( ! empty( $row[0] ) ) $regions_by_ds[ $key ][] = $row[0];
                    }
                } catch ( Exception $e ) {}
            }
            // Collect distinct resources where applicable
            $resource_col = null;
            if ( in_array( $key, [ 'mcp', 'reserve_mcp', 'reserve_sched' ], true ) ) $resource_col = 'resource_name';
            elseif ( $key === 'congestion' ) $resource_col = 'equipment_name';
            if ( $resource_col ) {
                try {
                    $rres2 = $db->query( "SELECT DISTINCT {$resource_col} FROM {$ds['table']} WHERE {$resource_col} IS NOT NULL AND {$resource_col} != '' ORDER BY {$resource_col} ASC" );
                    while ( $row = $rres2->fetchArray( SQLITE3_NUM ) ) {
                        if ( ! empty( $row[0] ) ) $resources_by_ds[ $key ][] = $row[0];
                    }
                } catch ( Exception $e ) {}
            }
        }
        $db->close();
    }
    // Merge all regions for a global region filter
    $all_regions = [];
    foreach ( $regions_by_ds as $rlist ) {
        foreach ( $rlist as $rg ) {
            if ( ! in_array( $rg, $all_regions ) ) $all_regions[] = $rg;
        }
    }
    sort( $all_regions );
    // Merge all resources for a global resource filter
    $all_resources = [];
    foreach ( $resources_by_ds as $rlist ) {
        foreach ( $rlist as $rs ) {
            if ( ! in_array( $rs, $all_resources ) ) $all_resources[] = $rs;
        }
    }
    sort( $all_resources );

    $all_max = '';
    foreach ( $date_ranges as $dr ) {
        if ( ! $all_max || $dr['max_d'] > $all_max ) $all_max = $dr['max_d'];
    }
    $fmt_html = function( $d ) {
        return strlen( $d ) === 8 ? substr( $d, 0, 4 ) . '-' . substr( $d, 4, 2 ) . '-' . substr( $d, 6, 2 ) : $d;
    };
    $def = $all_max ? $fmt_html( $all_max ) : date( 'Y-m-d' );

    $chart_configs = [
        'mcp'          => [ 'title' => 'RTD Market Clearing Price',   'subtitle' => 'Marginal Price per Resource &middot; PHP/MWh',          'color' => '#2962ff' ],
        'regional'     => [ 'title' => 'RTD Regional Summaries',      'subtitle' => 'Generation by Region &middot; MW',                      'color' => '#00b746' ],
        'reserve_mcp'  => [ 'title' => 'RTD Reserve MCP',             'subtitle' => 'Reserve Marginal Price per Resource &middot; PHP/MWh',  'color' => '#7b1fa2' ],
        'congestion'   => [ 'title' => 'Congestions in RTD',          'subtitle' => 'MW Flow per Equipment',                                 'color' => '#ef5350' ],
        'reserve_sched'=> [ 'title' => 'RTD Reserve Schedules',       'subtitle' => 'Schedule Price per Resource &middot; PHP/MWh',          'color' => '#ff6d00' ],
    ];
    ?>
    <div class="weimop-tv-wrap">

        <!-- Toolbar -->
        <div class="weimop-tv-toolbar">
            <span style="font-size:13px;font-weight:700;color:#d1d4dc;letter-spacing:.3px;white-space:nowrap;">RTD Trading</span>
            <label class="weimop-tv-toolbar-label">From
                <input type="date" id="weimop-tg-from" class="weimop-tv-input" value="<?php echo esc_attr( $def ); ?>">
            </label>
            <label class="weimop-tv-toolbar-label">To
                <input type="date" id="weimop-tg-to" class="weimop-tv-input" value="<?php echo esc_attr( $def ); ?>">
            </label>
            <label class="weimop-tv-toolbar-label">Region
                <select id="weimop-tg-region" class="weimop-tv-select">
                    <option value="">All Regions</option>
                    <?php foreach ( $all_regions as $rg ): ?>
                    <option value="<?php echo esc_attr( $rg ); ?>"><?php echo esc_html( $rg ); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="weimop-tv-toolbar-label">Resource
                <select id="weimop-tg-resource" class="weimop-tv-select" style="min-width:160px;">
                    <option value="">All Resources</option>
                    <?php foreach ( $all_resources as $rs ): ?>
                    <option value="<?php echo esc_attr( $rs ); ?>"><?php echo esc_html( $rs ); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button id="weimop-tg-load" class="weimop-tv-btn">Load Charts</button>
            <span id="weimop-tg-status" style="font-size:11px;color:#787b86;"></span>
        </div>

        <!-- RTD Schedule vs LMP panel (NMMS live data) -->
        <div class="weimop-tv-panel" id="weimop-tv-panel-rtdsched">
            <div class="weimop-tv-panel-header">
                <span class="weimop-tv-panel-title" style="border-left:3px solid #f48024;padding-left:8px;">RTD Schedule vs LMP</span>
                <span class="weimop-tv-panel-sub">NMMS MPI &middot; Schedule MW (left) / LMP PHP per MWh (right)</span>
                <div class="weimop-tv-panel-stats" id="weimop-tg-stats-rtdsched">
                    <span class="weimop-tv-stat"><span class="weimop-tv-stat-lbl">Pts</span><span class="weimop-tv-stat-val" data-k="count">—</span></span>
                </div>
            </div>
            <div class="weimop-tv-chart" id="weimop-tg-chart-rtdsched">
                <div class="weimop-tv-placeholder" id="weimop-tg-ph-rtdsched"><p>Loading RTD Schedule data…</p></div>
                <div class="weimop-tv-tooltip" id="weimop-tg-tt-rtdsched" style="display:none;pointer-events:none;"></div>
            </div>
            <div id="weimop-tg-legend-rtdsched" class="weimop-tv-legend"></div>
        </div>

        <!-- 5 IEMOP panels -->
        <?php foreach ( $chart_configs as $key => $cfg ):
            $dr     = $date_ranges[ $key ] ?? null;
            $latest = $dr ? date( 'M d, Y', strtotime( $fmt_html( $dr['max_d'] ) ) ) : null;
        ?>
        <div class="weimop-tv-panel" id="weimop-tv-panel-<?php echo esc_attr( $key ); ?>">
            <div class="weimop-tv-panel-header">
                <span class="weimop-tv-panel-title" style="border-left:3px solid <?php echo esc_attr( $cfg['color'] ); ?>;padding-left:8px;"><?php echo esc_html( $cfg['title'] ); ?></span>
                <span class="weimop-tv-panel-sub"><?php echo $cfg['subtitle']; ?></span>
                <?php if ( $latest ): ?>
                <span class="weimop-tv-badge" style="color:<?php echo esc_attr( $cfg['color'] ); ?>;border-color:<?php echo esc_attr( $cfg['color'] ); ?>44;">Latest: <?php echo esc_html( $latest ); ?></span>
                <?php endif; ?>
                <div class="weimop-tv-panel-stats" id="weimop-tg-stats-<?php echo esc_attr( $key ); ?>">
                    <span class="weimop-tv-stat"><span class="weimop-tv-stat-lbl">Min</span><span class="weimop-tv-stat-val" data-k="min">—</span></span>
                    <span class="weimop-tv-stat"><span class="weimop-tv-stat-lbl">Max</span><span class="weimop-tv-stat-val" data-k="max">—</span></span>
                    <span class="weimop-tv-stat"><span class="weimop-tv-stat-lbl">Avg</span><span class="weimop-tv-stat-val" data-k="avg">—</span></span>
                    <span class="weimop-tv-stat"><span class="weimop-tv-stat-lbl">Pts</span><span class="weimop-tv-stat-val" data-k="count">—</span></span>
                </div>
            </div>
            <div class="weimop-tv-chart" id="weimop-tg-chart-<?php echo esc_attr( $key ); ?>">
                <div class="weimop-tv-placeholder" id="weimop-tg-ph-<?php echo esc_attr( $key ); ?>">
                    <p><?php echo $dr ? 'Click "Load Charts" to view data.' : 'No data yet — fetch via Market Data tab.'; ?></p>
                </div>
                <div class="weimop-tv-tooltip" id="weimop-tg-tt-<?php echo esc_attr( $key ); ?>" style="display:none;pointer-events:none;"></div>
            </div>
            <div id="weimop-tg-legend-<?php echo esc_attr( $key ); ?>" class="weimop-tv-legend"></div>
        </div>
        <?php endforeach; ?>
    </div>

    <script>
    (function(){
        var cfgEl = document.getElementById('weimop-config-data');
        var CFG   = cfgEl ? JSON.parse(cfgEl.textContent || cfgEl.innerHTML) : {};
        var AJAX  = CFG.ajaxurl || '';
        var NONCE = CFG.nonce   || '';

        function tgPost(data) {
            var fd = new FormData();
            fd.append('nonce', NONCE);
            Object.keys(data).forEach(function(k){ fd.append(k, data[k]); });
            return fetch(AJAX, { method:'POST', body:fd }).then(function(r){ return r.json(); });
        }

        var TV_BG   = '#131722';
        var TV_GRID = '#1e2230';
        var TV_TEXT = '#d1d4dc';
        var TV_MUTED = '#787b86';
        var TV_BORDER = '#2a2e39';

        var KEYS    = ['mcp','regional','reserve_mcp','congestion','reserve_sched'];
        var PALETTE = ['#2962ff','#00b746','#ab47bc','#ef5350','#ff6d00','#00bcd4','#fdd835','#5c6bc0','#ec407a','#26a69a'];
        var charts  = {};

        function makeTvChart(id, opts) {
            if (!window.LightweightCharts) return null;
            var el = document.getElementById(id);
            if (!el) return null;
            var defaults = {
                layout:{ textColor:TV_TEXT, background:{ type:'solid', color:TV_BG } },
                rightPriceScale:{ borderColor:TV_BORDER },
                leftPriceScale:{ visible: false, borderColor:TV_BORDER },
                timeScale:{ borderColor:TV_BORDER, timeVisible:true, secondsVisible:false },
                grid:{ vertLines:{ color:TV_GRID }, horzLines:{ color:TV_GRID } },
                crosshair:{ mode:LightweightCharts.CrosshairMode.Normal },
                handleScroll:true, handleScale:true, height:300,
            };
            if (opts) Object.assign(defaults, opts);
            return LightweightCharts.createChart(el, defaults);
        }

        // Init chart objects for the 5 IEMOP panels
        KEYS.forEach(function(key){
            var el = document.getElementById('weimop-tg-chart-' + key);
            if (el && window.LightweightCharts) {
                charts[key] = { chart: makeTvChart('weimop-tg-chart-' + key), series: [] };
            }
        });

        // RTD Schedule vs LMP dual-axis chart
        var rtdChart = null;
        var rtdSchedEl = document.getElementById('weimop-tg-chart-rtdsched');
        if (rtdSchedEl && window.LightweightCharts) {
            rtdChart = makeTvChart('weimop-tg-chart-rtdsched', {
                leftPriceScale:{ visible: true, borderColor:TV_BORDER },
                rightPriceScale:{ borderColor:TV_BORDER },
            });
            charts['rtdsched'] = { chart: rtdChart, series: [] };
            loadRtdSched();
        } else {
            var ph = document.getElementById('weimop-tg-ph-rtdsched');
            if (ph) ph.querySelector('p').textContent = 'LightweightCharts not loaded.';
        }

        window.addEventListener('resize', function(){
            var allKeys = KEYS.concat(['rtdsched']);
            allKeys.forEach(function(key){
                var c = charts[key];
                var el = document.getElementById('weimop-tg-chart-' + key);
                if (c && c.chart && el) c.chart.applyOptions({ width: el.clientWidth });
            });
        });

        function dateToFd(d){ return d ? d.replace(/-/g,'') : ''; }
        function fmtNum(v){ return parseFloat(v).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2}); }

        function setTooltip(key, chart, seriesList, seriesLabels) {
            chart.subscribeCrosshairMove(function(param){
                var tt = document.getElementById('weimop-tg-tt-' + key);
                if (!tt) return;
                if (!param.point || !param.time) { tt.style.display = 'none'; return; }
                var t = param.time;
                var tStr = typeof t === 'number'
                    ? new Date(t * 1000).toLocaleString('en-PH',{month:'short',day:'2-digit',hour:'2-digit',minute:'2-digit'})
                    : String(t);
                var html = '<div style="font-size:11px;font-weight:700;color:#d1d4dc;margin-bottom:4px;">' + tStr + '</div>';
                seriesList.forEach(function(s, i){
                    var v = param.seriesData && param.seriesData.get ? param.seriesData.get(s) : null;
                    if (v && v.value !== undefined) {
                        var color = PALETTE[i % PALETTE.length];
                        html += '<div style="display:flex;align-items:center;gap:6px;font-size:11px;"><span style="width:8px;height:8px;border-radius:50%;background:' + color + ';flex-shrink:0;display:inline-block;"></span><span style="color:#787b86;">' + (seriesLabels[i] || '') + '</span><span style="color:#d1d4dc;font-variant-numeric:tabular-nums;margin-left:auto;padding-left:12px;">' + fmtNum(v.value) + '</span></div>';
                    }
                });
                tt.innerHTML = html;
                var x = param.point ? param.point.x : 0;
                var y = param.point ? param.point.y : 0;
                var container = document.getElementById('weimop-tg-chart-' + key);
                var cw = container ? container.clientWidth : 400;
                var ch = container ? container.clientHeight : 300;
                var tw = 200, th = 80;
                var left = x + 14;
                var top  = y - 10;
                if (left + tw > cw) left = x - tw - 14;
                if (top + th  > ch) top  = ch - th - 10;
                tt.style.left    = left + 'px';
                tt.style.top     = top  + 'px';
                tt.style.display = 'block';
            });
        }

        function loadRtdSched() {
            var ph = document.getElementById('weimop-tg-ph-rtdsched');
            tgPost({ action:'weimop_get_chart_series' }).then(function(j){
                if (!j.success || !j.data) {
                    if (ph) { ph.querySelector('p').textContent = 'No RTD schedule data.'; ph.style.display = 'flex'; }
                    return;
                }
                var d = j.data;
                var rtdPts = d.rtd || [];
                var lmpPts = d.lmp || [];
                if (!rtdPts.length && !lmpPts.length) {
                    if (ph) { ph.querySelector('p').textContent = 'No RTD data yet — fetch via Settings.'; ph.style.display = 'flex'; }
                    return;
                }
                if (ph) ph.style.display = 'none';

                var c = charts['rtdsched'];
                c.series.forEach(function(s){ try{ c.chart.removeSeries(s); }catch(e){} });
                c.series = [];

                var schedSeries = c.chart.addLineSeries({
                    color: '#f48024', lineWidth: 2,
                    priceScaleId: 'left',
                    title: 'Schedule MW',
                    lastValueVisible: true, priceLineVisible: false,
                });
                var lmpSeries = c.chart.addAreaSeries({
                    lineColor: '#2962ff', topColor: 'rgba(41,98,255,0.28)', bottomColor: 'rgba(41,98,255,0.02)',
                    lineWidth: 2,
                    priceScaleId: 'right',
                    title: 'LMP',
                    lastValueVisible: true, priceLineVisible: false,
                });

                schedSeries.setData(rtdPts.filter(function(p){ return p.time && !isNaN(p.value); }).map(function(p){ return { time:p.time, value:p.value }; }));
                lmpSeries.setData(lmpPts.filter(function(p){ return p.time && !isNaN(p.value); }).map(function(p){ return { time:p.time, value:p.value }; }));

                c.series = [schedSeries, lmpSeries];
                c.chart.timeScale().fitContent();

                var legend = document.getElementById('weimop-tg-legend-rtdsched');
                if (legend) legend.innerHTML = '<span class="weimop-tv-legend-item"><span class="weimop-tv-legend-dot" style="background:#f48024"></span>Schedule MW</span><span class="weimop-tv-legend-item"><span class="weimop-tv-legend-dot" style="background:#2962ff"></span>LMP PHP/MWh</span>';

                var stats = document.getElementById('weimop-tg-stats-rtdsched');
                if (stats) stats.querySelector('[data-k="count"]').textContent = (rtdPts.length).toLocaleString();

                setTooltip('rtdsched', c.chart, c.series, ['Schedule MW', 'LMP PHP/MWh']);
            }).catch(function(e){
                if (ph) { ph.querySelector('p').textContent = 'Failed: ' + e.message; ph.style.display = 'flex'; }
            });
        }

        function loadDataset(key, fd_from, fd_to, region, resource) {
            var ph     = document.getElementById('weimop-tg-ph-' + key);
            var stats  = document.getElementById('weimop-tg-stats-' + key);
            var legend = document.getElementById('weimop-tg-legend-' + key);
            if (ph) { ph.querySelector('p').textContent = 'Loading…'; ph.style.display = 'flex'; }
            if (legend) legend.innerHTML = '';

            var postData = { action:'weimop_get_trading_series', dataset:key, date_from:fd_from, date_to:fd_to };
            if (region)   postData.region   = region;
            if (resource) postData.resource = resource;

            tgPost(postData).then(function(j){
                if (!j.success || !j.data) {
                    if (ph) { ph.querySelector('p').textContent = 'Error: ' + ((j.data && j.data.message) ? j.data.message : 'Unknown'); ph.style.display = 'flex'; }
                    return;
                }
                var d = j.data;
                var seriesObj = d.series || {};
                var seriesKeys = Object.keys(seriesObj);

                if (!seriesKeys.length || !d.count) {
                    if (ph) { ph.querySelector('p').textContent = 'No data for selected range.'; ph.style.display = 'flex'; }
                    return;
                }
                if (ph) ph.style.display = 'none';

                var c = charts[key];
                if (c) {
                    c.series.forEach(function(s){ try{ c.chart.removeSeries(s); }catch(e){} });
                    c.series = [];
                }

                var allVals = [];
                var legendHtml = '';
                var colorIdx = 0;
                var seriesLabels = [];

                seriesKeys.slice(0, 10).forEach(function(sk){
                    var pts = seriesObj[sk];
                    if (!pts || !pts.length) return;
                    var color = PALETTE[colorIdx++ % PALETTE.length];
                    var isSingle = seriesKeys.length === 1;
                    var ls;
                    if (isSingle) {
                        ls = c.chart.addAreaSeries({
                            lineColor: color,
                            topColor: color.replace(/^#/, 'rgba(').replace(/(..)(..)(..)$/, function(_, r, g, b){
                                return parseInt(r,16)+','+parseInt(g,16)+','+parseInt(b,16)+',0.28)';
                            }),
                            bottomColor: color.replace(/^#/, 'rgba(').replace(/(..)(..)(..)$/, function(_, r, g, b){
                                return parseInt(r,16)+','+parseInt(g,16)+','+parseInt(b,16)+',0.02)';
                            }),
                            lineWidth: 2,
                            lastValueVisible: true, priceLineVisible: false,
                        });
                    } else {
                        ls = c.chart.addLineSeries({
                            color: color,
                            lineWidth: seriesKeys.length > 5 ? 1 : 2,
                            lastValueVisible: false,
                            priceLineVisible: false,
                        });
                    }
                    var lwData = pts.filter(function(p){ return p.time && !isNaN(p.value); })
                                    .map(function(p){ return { time:p.time, value:p.value }; });
                    ls.setData(lwData);
                    c.series.push(ls);
                    seriesLabels.push(sk);
                    pts.forEach(function(p){ if (!isNaN(p.value)) allVals.push(p.value); });
                    legendHtml += '<span class="weimop-tv-legend-item"><span class="weimop-tv-legend-dot" style="background:' + color + '"></span>' + sk + '</span>';
                });

                c.chart.timeScale().fitContent();
                if (legend) legend.innerHTML = legendHtml;

                if (allVals.length && stats) {
                    var mn  = Math.min.apply(null, allVals);
                    var mx  = Math.max.apply(null, allVals);
                    var avg = allVals.reduce(function(a,b){ return a+b; }, 0) / allVals.length;
                    var minEl  = stats.querySelector('[data-k="min"]');
                    var maxEl  = stats.querySelector('[data-k="max"]');
                    var avgEl  = stats.querySelector('[data-k="avg"]');
                    var cntEl  = stats.querySelector('[data-k="count"]');
                    if (minEl) minEl.textContent  = fmtNum(mn);
                    if (maxEl) maxEl.textContent  = fmtNum(mx);
                    if (avgEl) avgEl.textContent  = fmtNum(avg);
                    if (cntEl) cntEl.textContent  = d.count.toLocaleString();
                }

                setTooltip(key, c.chart, c.series, seriesLabels);
            }).catch(function(e){
                if (ph) { ph.querySelector('p').textContent = 'Request failed: ' + e.message; ph.style.display = 'flex'; }
            });
        }

        var loadBtn = document.getElementById('weimop-tg-load');
        if (loadBtn) {
            loadBtn.addEventListener('click', function(){
                var fromVal  = document.getElementById('weimop-tg-from').value;
                var toVal    = document.getElementById('weimop-tg-to').value;
                var region   = document.getElementById('weimop-tg-region')   ? document.getElementById('weimop-tg-region').value   : '';
                var resource = document.getElementById('weimop-tg-resource') ? document.getElementById('weimop-tg-resource').value : '';
                var fd_from  = dateToFd(fromVal);
                var fd_to    = dateToFd(toVal);
                var status   = document.getElementById('weimop-tg-status');
                if (!fd_from || !fd_to) { status.textContent = 'Select a date range first.'; return; }
                status.textContent = 'Loading…';
                KEYS.forEach(function(key){ loadDataset(key, fd_from, fd_to, region, resource); });
                setTimeout(function(){ status.textContent = ''; }, 3000);
            });
        }
    })();
    </script>
    <?php
    return ob_get_clean();
}

function weimop_tab_suggestions() { ob_start(); ?>
    <div class="weimop-card">
        <div class="weimop-card__header"><h3 class="weimop-card__title">Suggested Additions</h3></div>
        <ol class="weimop-list">
            <li>NMMS MPI extractor scheduler and health monitor.</li>
            <li>RTD Schedule vs LMP with +1.5% / -3.0% OCC compliance bands.</li>
            <li>Per-interval OCC compliance alerts and threshold breach engine.</li>
            <li>Rule-based strategy cards for WESM/EIMOP dispatch decisions.</li>
            <li>Daily settlement and reconciliation panel.</li>
            <li>HAP/DAP spread alert when DAP exceeds HAP by a configurable margin.</li>
        </ol>
    </div>
<?php return ob_get_clean(); }

/* -------------------------------------------------------
   I. TAB -- SETTINGS
------------------------------------------------------- */

function weimop_tab_settings( $uid, $s ) {
    $status  = bntm_weimop_check_connection();
    $ok      = empty( $status['issues'] );
    $db_path = bntm_weimop_db_path();
    $pfx_ok  = ! empty( $s['nmms_cert_path'] ) && file_exists( $s['nmms_cert_path'] );

    ob_start(); ?>

    <!-- Database info -->
    <div class="weimop-card weimop-card--info">
        <div class="weimop-card__header">
            <h3 class="weimop-card__title">SQLite Database</h3>
            <form method="post" style="margin-left:auto;">
                <?php wp_nonce_field('weimop_bootstrap_db'); ?>
                <input type="hidden" name="weimop_action" value="bootstrap_db">
                <button type="submit" class="weimop-btn weimop-btn--sm">Re-initialize Tables</button>
            </form>
        </div>
        <table class="weimop-info-table">
            <tr><td>Path</td><td><code><?php echo esc_html( $db_path ); ?></code></td></tr>
            <?php if ( file_exists( $db_path ) ):
                $db = bntm_weimop_open_db( true );
                $tc = 0; $rc = 0;
                if ( $db ) {
                    $tc = (int) $db->querySingle("SELECT COUNT(*) FROM sqlite_master WHERE type='table'");
                    try { $rc = (int) $db->querySingle("SELECT COUNT(*) FROM RTDSchedules"); } catch(Exception $e){}
                    $db->close();
                } ?>
            <tr><td>File size</td><td><?php echo round( filesize($db_path)/1024, 1 ); ?> KB</td></tr>
            <tr><td>Tables</td><td><?php echo $tc; ?></td></tr>
            <tr><td>RTD rows</td><td><?php echo $rc; ?></td></tr>
            <tr><td>Status</td><td><span class="weimop-status-dot weimop-status-dot--ok"></span> File exists</td></tr>
            <?php else: ?>
            <tr><td>Status</td><td><span class="weimop-status-dot weimop-status-dot--warn"></span> Not yet created &mdash; will be created on next page load</td></tr>
            <?php endif; ?>
        </table>
        <?php
        if ( isset($_POST['weimop_action']) && $_POST['weimop_action'] === 'bootstrap_db' && check_admin_referer('weimop_bootstrap_db') ) {
            $db = bntm_weimop_open_db(false);
            echo $db
                ? '<p class="weimop-notice weimop-notice--ok">Tables re-initialized successfully.</p>'
                : '<p class="weimop-notice weimop-notice--error">Re-initialization failed. Verify the PHP SQLite3 extension is enabled.</p>';
            if ($db) $db->close();
        }
        ?>
    </div>


    <!-- PFX / OpenSSL Diagnostics -->
    <div class="weimop-card" id="weimop-diag-card">
        <div class="weimop-card__header">
            <h3 class="weimop-card__title">PFX &amp; OpenSSL Diagnostics</h3>
            <div style="margin-left:auto;display:flex;align-items:center;gap:8px;">
                <input type="password" id="weimop-diag-password" class="weimop-input weimop-input--sm" placeholder="Certificate password">
                <button type="button" id="weimop-diag-btn" class="weimop-btn weimop-btn--primary weimop-btn--sm">Run Diagnostics</button>
            </div>
        </div>
        <p class="weimop-muted">
            Runs a server-side check of PHP extensions, the .pfx file, and password parsing.
            If <strong>Fetch Previous 24h Data</strong> fails with a password error, run this first.
        </p>
        <div id="weimop-diag-results" style="display:none;">
            <table class="weimop-info-table" id="weimop-diag-table"></table>
            <pre id="weimop-diag-raw" class="weimop-diag-raw" style="display:none;margin-top:10px;font-size:11px;background:#f2f5f8;padding:10px;border-radius:4px;overflow:auto;white-space:pre-wrap;"></pre>
        </div>
    </div>

    <!-- Connection status -->
    <div class="weimop-card weimop-card--<?php echo $ok ? 'ok' : 'warn'; ?>">
        <div class="weimop-card__header">
            <h3 class="weimop-card__title">
                <span class="weimop-status-dot weimop-status-dot--<?php echo $ok ? 'ok' : 'warn'; ?>"></span>
                <?php echo $ok ? 'Connection Ready' : 'Setup Incomplete'; ?>
            </h3>
            <?php if ( $ok ): ?>
            <div style="margin-left:auto;display:flex;align-items:center;gap:10px;">
                <div class="weimop-backfill-password-row" id="weimop-backfill-row">
                    <input type="password" id="weimop-backfill-password"
                           class="weimop-input weimop-input--sm"
                           placeholder="Certificate password">
                    <button type="button" id="weimop-backfill-btn" class="weimop-btn weimop-btn--primary weimop-btn--sm">
                        Fetch Previous 24h Data from IEMOP
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php if ( ! $ok ): ?>
        <p class="weimop-muted">Resolve the items below before attempting a data connection.</p>
        <ul class="weimop-issue-list">
            <?php foreach ( $status['issues'] as $issue ): ?>
            <li><?php echo esc_html($issue); ?></li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p class="weimop-muted">
            All prerequisite checks passed. Enter your certificate password and click
            <strong>Fetch Previous 24h Data from IEMOP</strong> to backfill the database,
            or wait for your NMMS MPI extractor to populate it automatically.
        </p>
        <?php endif; ?>
        <div id="weimop-backfill-status" class="weimop-notice" style="display:none;margin-top:10px;"></div>
        <div class="weimop-checks-grid">
            <?php foreach ( [
                'sqlite3_ext'  => 'PHP SQLite3 extension enabled',
                'db_exists'    => 'SQLite DB file exists',
                'db_writable'  => 'SQLite DB is writable',
                'tables_exist' => 'All WESM tables present',
                'has_rtd'      => 'RTDSchedules has data',
                'has_hap'      => 'HAPResults has data',
                'has_dap'      => 'DAPResults has data',
                'has_occ'      => 'OCC table has data',
                'pfx_set'      => 'Certificate path set',
                'pfx_exists'   => 'Certificate file exists',
                'curl_ssl'     => 'cURL + OpenSSL available',
                'nmms_url'     => 'NMMS Main URL set',
                'cert_name'    => 'Certificate Name (CN) set',
            ] as $key => $label ):
                $pass = $status['checks'][$key] ?? false; ?>
            <div class="weimop-check weimop-check--<?php echo $pass ? 'pass' : 'fail'; ?>">
                <span class="weimop-check__icon"><?php echo $pass ? '&#10003;' : '&#10007;'; ?></span>
                <?php echo esc_html($label); ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Certificate upload -->
    <div class="weimop-card">
        <div class="weimop-card__header">
            <h3 class="weimop-card__title">NMMS Certificate Upload (.pfx / .p12)</h3>
        </div>
        <p class="weimop-muted">
            Upload your IEMOP NMMS MPI mutual-TLS certificate. The file is stored in
            <code>uploads/<?php echo esc_html(BNTM_WEIMOP_PFX_SUBDIR); ?>/</code>
            with HTTP access blocked. The server extracts the certificate and private key
            to temporary PEM files at request time and deletes them immediately after.
        </p>
        <?php if ( $pfx_ok ): ?>
        <div class="weimop-notice weimop-notice--ok" style="margin-bottom:12px;">
            Current certificate: <strong><?php echo esc_html(basename($s['nmms_cert_path'])); ?></strong>
            &mdash; <?php echo esc_html($s['nmms_cert_path']); ?>
        </div>
        <?php endif; ?>
        <div id="weimop-pfx-drop-zone" class="weimop-dropzone">
            <p id="weimop-pfx-drop-label">Drag and drop a .pfx or .p12 file here, or click to browse</p>
            <input type="file" id="weimop-pfx-file-input" accept=".pfx,.p12" style="display:none;">
        </div>
        <div class="weimop-pfx-controls">
            <div class="weimop-form-group" style="flex:1;">
                <label class="weimop-label">Certificate Password <span class="weimop-muted">(used to read cert details &mdash; not stored in plain text)</span></label>
                <input type="password" id="weimop-pfx-password" class="weimop-input" placeholder="Leave blank if none">
            </div>
            <button type="button" class="weimop-btn weimop-btn--primary" id="weimop-pfx-upload-btn" disabled>Upload and Read Certificate</button>
        </div>
        <div id="weimop-pfx-progress" style="display:none;margin-top:10px;">
            <div class="weimop-progress"><div id="weimop-pfx-bar" class="weimop-progress__fill" style="width:0%"></div></div>
            <span id="weimop-pfx-progress-label" class="weimop-muted">Uploading...</span>
        </div>
        <div id="weimop-pfx-status" class="weimop-notice" style="display:none;margin-top:8px;"></div>
        <div id="weimop-pfx-preview" class="weimop-cert-preview" style="display:none;"></div>
        <button type="button" class="weimop-btn" id="weimop-pfx-apply-btn" style="display:none;margin-top:10px;">
            Apply Certificate Info to Fields Below
        </button>
    </div>

    <!-- Settings form -->
    <form id="weimop-settings-form">

        <div class="weimop-card">
            <div class="weimop-card__header"><h3 class="weimop-card__title">Email Alerts</h3></div>
            <div class="weimop-form-grid">
                <div class="weimop-form-group">
                    <label class="weimop-label">Sender Address</label>
                    <input type="text" class="weimop-input" name="email_sender" value="<?php echo esc_attr($s['email_sender']); ?>">
                </div>
                <div class="weimop-form-group">
                    <label class="weimop-label">Password <span class="weimop-muted">(leave blank to keep current)</span></label>
                    <input type="password" class="weimop-input" name="email_password" value="">
                </div>
                <div class="weimop-form-group">
                    <label class="weimop-label">Recipients <span class="weimop-muted">(comma-separated)</span></label>
                    <input type="text" class="weimop-input" name="email_recipients" value="<?php echo esc_attr($s['email_recipients']); ?>">
                </div>
            </div>
        </div>

        <div class="weimop-card">
            <div class="weimop-card__header"><h3 class="weimop-card__title">Dashboard Settings</h3></div>
            <div class="weimop-form-grid">
                <div class="weimop-form-group">
                    <label class="weimop-label">Price Alert Threshold <span class="weimop-muted">(PHP/MWh)</span></label>
                    <input type="number" class="weimop-input" step="0.01" name="price_alert" value="<?php echo esc_attr($s['price_alert']); ?>">
                </div>
                <div class="weimop-form-group">
                    <label class="weimop-label">Auto-Refresh Interval <span class="weimop-muted">(seconds, minimum 60)</span></label>
                    <input type="number" class="weimop-input" step="1" min="60" name="refresh_interval" value="<?php echo esc_attr($s['refresh_interval']); ?>">
                </div>
            </div>
        </div>

        <div class="weimop-card">
            <div class="weimop-card__header"><h3 class="weimop-card__title">NMMS MPI &mdash; Connection</h3></div>
            <div class="weimop-form-grid">
                <div class="weimop-form-group">
                    <label class="weimop-label">Main URL <?php echo empty($s['nmms_main_url']) ? '<span class="weimop-field-flag weimop-field-flag--warn">Missing</span>' : '<span class="weimop-field-flag weimop-field-flag--ok">Set</span>'; ?></label>
                    <input type="text" class="weimop-input" name="nmms_main_url" value="<?php echo esc_attr($s['nmms_main_url']); ?>">
                </div>
                <div class="weimop-form-group">
                    <label class="weimop-label">Backup URL</label>
                    <input type="text" class="weimop-input" name="nmms_backup_url" value="<?php echo esc_attr($s['nmms_backup_url']); ?>">
                </div>
                <div class="weimop-form-group">
                    <label class="weimop-label">Service URL</label>
                    <input type="text" class="weimop-input" name="nmms_url" value="<?php echo esc_attr($s['nmms_url']); ?>">
                </div>
                <div class="weimop-form-group">
                    <label class="weimop-label">Operation</label>
                    <input type="text" class="weimop-input" name="nmms_operation" value="<?php echo esc_attr($s['nmms_operation']); ?>">
                </div>
            </div>
        </div>

        <div class="weimop-card">
            <div class="weimop-card__header"><h3 class="weimop-card__title">NMMS MPI &mdash; Certificate <span class="weimop-muted">(auto-filled on upload)</span></h3></div>
            <div class="weimop-form-grid">
                <div class="weimop-form-group">
                    <label class="weimop-label">Certificate Name (CN) <?php echo empty($s['nmms_cert_name']) ? '<span class="weimop-field-flag weimop-field-flag--warn">Missing</span>' : '<span class="weimop-field-flag weimop-field-flag--ok">Set</span>'; ?></label>
                    <input type="text" class="weimop-input" name="nmms_cert_name" id="weimop-cert-name" value="<?php echo esc_attr($s['nmms_cert_name']); ?>">
                </div>
                <div class="weimop-form-group">
                    <label class="weimop-label">Certificate File Path
                        <?php if ($pfx_ok) echo '<span class="weimop-field-flag weimop-field-flag--ok">File found</span>';
                        elseif (!empty($s['nmms_cert_path'])) echo '<span class="weimop-field-flag weimop-field-flag--warn">File not found</span>';
                        else echo '<span class="weimop-field-flag weimop-field-flag--warn">Upload above to set</span>'; ?>
                    </label>
                    <input type="text" class="weimop-input" name="nmms_cert_path" id="weimop-cert-path"
                           value="<?php echo esc_attr($s['nmms_cert_path']); ?>" placeholder="Auto-filled on certificate upload" readonly>
                </div>
                <div class="weimop-form-group">
                    <label class="weimop-label">Certificate Password <span class="weimop-muted">(leave blank to keep current)</span></label>
                    <input type="password" class="weimop-input" name="nmms_cert_password" id="weimop-cert-password" value="">
                </div>
                <div class="weimop-form-group">
                    <label class="weimop-label">Friendly Name</label>
                    <input type="text" class="weimop-input" name="nmms_friendly_name" id="weimop-friendly-name" value="<?php echo esc_attr($s['nmms_friendly_name']); ?>">
                </div>
            </div>
        </div>

        <div class="weimop-card">
            <div class="weimop-card__header"><h3 class="weimop-card__title">NMMS MPI &mdash; Request Parameters</h3></div>
            <p class="weimop-muted">These values are sent in the SOAP exportResults request body to the IEMOP NMMS MPI service.</p>
            <div class="weimop-pfx-controls" style="margin-bottom:10px;">
                <div class="weimop-form-group" style="flex:1;">
                    <label class="weimop-label">Upload ExportResultsConf.xml <span class="weimop-muted">(recommended instead of typing full path)</span></label>
                    <input type="file" id="weimop-export-conf-file" accept=".xml" class="weimop-input">
                </div>
                <button type="button" class="weimop-btn weimop-btn--primary" id="weimop-upload-export-conf-btn">Upload ExportResultsConf</button>
            </div>
            <div id="weimop-export-conf-status" class="weimop-notice" style="display:none;margin-bottom:10px;"></div>
            <div class="weimop-form-grid">
                <?php foreach ( [
                    'nmms_result_type'  => 'Result Type (e.g. RTD_LMP)',
                    'nmms_market_run'   => 'Market Run (e.g. RTD)',
                    'nmms_region_name'  => 'Region Name (e.g. LUZON)',
                    'nmms_run_time'     => 'Run Time (e.g. 00)',
                    'nmms_commodity'    => 'Commodity (e.g. ELECTRICITY)',
                    'nmms_price_node'   => 'Price Node (e.g. LUZON)',
                    'nmms_unit_id'      => 'Unit ID',
                    'nmms_export_conf'  => 'ExportResultsConf Path',
                    'nmms_interval_end' => 'Interval End (auto-set on fetch)',
                ] as $name => $label ): ?>
                <div class="weimop-form-group">
                    <label class="weimop-label"><?php echo esc_html($label); ?></label>
                    <input type="text" class="weimop-input" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($s[$name]); ?>">
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </form>

    <div class="weimop-form-actions">
        <button type="button" class="weimop-btn weimop-btn--primary" id="weimop-save-settings">Save Settings</button>
        <span id="weimop-settings-msg" class="weimop-notice" style="display:none;"></span>
    </div>

    <?php return ob_get_clean();
}

/* -------------------------------------------------------
   J. NMMS MPI SOAP CALLER
   Extracts cert+key from .pfx to temp PEM files,
   calls IEMOP via cURL with mutual TLS, cleans up.
   $cert_pass must be the RAW plain-text password
   (no sanitize_text_field, no htmlspecialchars).
------------------------------------------------------- */


/* -------------------------------------------------------
   PFX -> PEM EXTRACTION HELPER
   OpenSSL 3.x dropped legacy encryption (RC2/3DES) used
   by older Windows PFX exports. We try three methods:

   Method 1: openssl_pkcs12_read()
             Works if PHP was compiled with legacy OpenSSL
             support or the PFX uses modern encryption.

   Method 2: shell `openssl pkcs12 -legacy`
             Uses the system openssl binary with the
             -legacy flag (available in OpenSSL 3.x).
             Requires exec() or shell_exec() to be enabled.

   Method 3: Return native_pfx=true
             Tell cURL to read the PFX directly via
             CURLOPT_SSLCERTTYPE=P12. cURL uses its own
             OpenSSL context which often has legacy support
             enabled even when PHP does not.
             This is the most reliable fallback.
------------------------------------------------------- */

function bntm_weimop_extract_pem_from_pfx( $pfx_path, $pfx_pass, $cert_file, $key_file ) {
    $pfx_data = file_get_contents( $pfx_path );
    if ( $pfx_data === false )
        return [ 'error' => 'Cannot read certificate file at: ' . $pfx_path ];

    // Method 1: openssl_pkcs12_read (PHP built-in)
    if ( function_exists('openssl_pkcs12_read') ) {
        $certs = [];
        // Suppress errors — we handle them manually
        $ok = @openssl_pkcs12_read( $pfx_data, $certs, $pfx_pass );
        if ( $ok && ! empty($certs['cert']) && ! empty($certs['pkey']) ) {
            file_put_contents( $cert_file, $certs['cert'] );
            file_put_contents( $key_file,  $certs['pkey'] );
            return [ 'method' => 1, 'native_pfx' => false ];
        }
    }

    // Method 2: shell openssl binary with -legacy flag
    // Only attempt if exec() is available and not disabled
    if ( function_exists('exec') && ! in_array('exec', array_map('trim', explode(',', ini_get('disable_functions')))) ) {
        $pass_escaped = escapeshellarg( $pfx_pass );
        $pfx_escaped  = escapeshellarg( $pfx_path );
        $cert_escaped = escapeshellarg( $cert_file );
        $key_escaped  = escapeshellarg( $key_file );

        // Try with -legacy (OpenSSL 3.x)
        $cmd_cert = "openssl pkcs12 -legacy -in {$pfx_escaped} -clcerts -nokeys -out {$cert_escaped} -passin pass:{$pass_escaped} 2>&1";
        $cmd_key  = "openssl pkcs12 -legacy -in {$pfx_escaped} -nocerts -nodes  -out {$key_escaped}  -passin pass:{$pass_escaped} 2>&1";
        exec( $cmd_cert, $out1, $rc1 );
        exec( $cmd_key,  $out2, $rc2 );

        if ( $rc1 === 0 && $rc2 === 0 && file_exists($cert_file) && filesize($cert_file) > 0 ) {
            return [ 'method' => 2, 'native_pfx' => false ];
        }

        // Also try without -legacy (in case PFX uses modern encryption)
        $cmd_cert2 = "openssl pkcs12 -in {$pfx_escaped} -clcerts -nokeys -out {$cert_escaped} -passin pass:{$pass_escaped} 2>&1";
        $cmd_key2  = "openssl pkcs12 -in {$pfx_escaped} -nocerts -nodes  -out {$key_escaped}  -passin pass:{$pass_escaped} 2>&1";
        exec( $cmd_cert2, $out3, $rc3 );
        exec( $cmd_key2,  $out4, $rc4 );

        if ( $rc3 === 0 && $rc4 === 0 && file_exists($cert_file) && filesize($cert_file) > 0 ) {
            return [ 'method' => '2b', 'native_pfx' => false ];
        }
    }

    // Method 3: Let cURL read the PFX natively via CURLOPT_SSLCERTTYPE=P12
    // cURL's OpenSSL context often has legacy algorithms enabled.
    // No file extraction needed — signal caller to use P12 mode.
    return [ 'method' => 3, 'native_pfx' => true ];
}

function bntm_weimop_debug_enabled() {
    return defined('WP_DEBUG') && WP_DEBUG;
}

function bntm_weimop_debug_log( $tag, $context = [] ) {
    if ( ! bntm_weimop_debug_enabled() ) return;
    $upload = wp_upload_dir();
    $dir = trailingslashit($upload['basedir']) . 'weimop';
    if ( ! file_exists($dir) ) wp_mkdir_p($dir);
    $file = trailingslashit($dir) . 'weimop-debug.log';
    $line = gmdate('c') . ' [' . $tag . '] ' . wp_json_encode($context) . PHP_EOL;
    @file_put_contents($file, $line, FILE_APPEND);
}

function bntm_weimop_nmms_build_urls( $s ) {
    $request_urls = [];
    if ( ! empty($s['nmms_url']) ) {
        $request_urls[] = trim( (string) $s['nmms_url'] );
    } else {
        if ( ! empty($s['nmms_main_url']) ) $request_urls[] = trim( (string) $s['nmms_main_url'] );
        if ( ! empty($s['nmms_backup_url']) && trim((string)$s['nmms_backup_url']) !== trim((string)($s['nmms_main_url'] ?? '')) ) {
            $request_urls[] = trim( (string) $s['nmms_backup_url'] );
        }
    }

    // IEMOP MPI commonly responds on HTTP; if HTTPS is saved, try HTTP first to avoid 30s timeout per attempt.
    $expanded_urls = [];
    foreach ( $request_urls as $u ) {
        if ( stripos($u, 'https://') === 0 ) {
            $expanded_urls[] = 'http://' . substr($u, 8);
            $expanded_urls[] = $u;
        } else {
            $expanded_urls[] = $u;
        }
    }

    return array_values(array_unique(array_filter($expanded_urls)));
}

function bntm_weimop_nmms_soap_request( $s, $result_type, $interval_end ) {
    if ( empty($s['nmms_cert_path']) || ! file_exists($s['nmms_cert_path']) )
        return [ 'error' => 'Certificate file not found: ' . $s['nmms_cert_path'] ];
    if ( ! function_exists('openssl_pkcs12_read') )
        return [ 'error' => 'PHP OpenSSL extension not available.' ];
    if ( ! function_exists('curl_init') )
        return [ 'error' => 'PHP cURL extension not available.' ];

    $pfx_pass  = $s['_cert_pass_raw'] ?? '';
    $pem_dir   = bntm_weimop_pem_dir();
    $uid       = get_current_user_id();
    $cert_file = $pem_dir . '/cert_' . $uid . '.pem';
    $key_file  = $pem_dir . '/key_'  . $uid . '.pem';

    // Try to extract PEM from PFX using three methods in order:
    // 1. openssl_pkcs12_read() — works on PHP/OpenSSL < 3 or compiled with legacy
    // 2. shell openssl command with -legacy flag — works on OpenSSL 3.x servers
    // 3. Pass PFX directly to cURL (CURLOPT_SSLCERTTYPE=P12) — no extraction needed
    $extracted = bntm_weimop_extract_pem_from_pfx(
        $s['nmms_cert_path'], $pfx_pass, $cert_file, $key_file
    );
    if ( isset($extracted['error']) ) return $extracted;
    $use_native_pfx = $extracted['native_pfx'] ?? false;

    $soap_body = '<?xml version="1.0" encoding="utf-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:ns="http://www.siemens.com/ptd/mms/mmsbase">
  <soapenv:Header/>
  <soapenv:Body>
    <ns:exportResults>
      <ns:operation>'   . htmlspecialchars($s['nmms_operation'] ?: 'exportResults', ENT_XML1) . '</ns:operation>
      <ns:resultType>'  . htmlspecialchars($result_type, ENT_XML1)                            . '</ns:resultType>
      <ns:marketRun>'   . htmlspecialchars($s['nmms_market_run'], ENT_XML1)                   . '</ns:marketRun>
      <ns:regionName>'  . htmlspecialchars($s['nmms_region_name'], ENT_XML1)                  . '</ns:regionName>
      <ns:runTime>'     . htmlspecialchars($s['nmms_run_time'], ENT_XML1)                     . '</ns:runTime>
      <ns:commodity>'   . htmlspecialchars($s['nmms_commodity'], ENT_XML1)                    . '</ns:commodity>
      <ns:priceNode>'   . htmlspecialchars($s['nmms_price_node'], ENT_XML1)                   . '</ns:priceNode>
      <ns:intervalEnd>' . htmlspecialchars($interval_end, ENT_XML1)                           . '</ns:intervalEnd>
      <ns:unitId>'      . htmlspecialchars($s['nmms_unit_id'], ENT_XML1)                      . '</ns:unitId>
    </ns:exportResults>
  </soapenv:Body>
</soapenv:Envelope>';

    $request_urls = bntm_weimop_nmms_build_urls( $s );
    if ( empty($request_urls) ) return [ 'error' => 'NMMS URL is not configured.' ];

    $response = false;
    $http_code = 0;
    $last_error = '';
    $attempt_errors = [];
    bntm_weimop_debug_log('soap_prepare', [
        'result_type'  => $result_type,
        'interval_end' => $interval_end,
        'operation'    => (string)($s['nmms_operation'] ?? ''),
        'marketRun'    => (string)($s['nmms_market_run'] ?? ''),
        'regionName'   => (string)($s['nmms_region_name'] ?? ''),
        'runTime'      => (string)($s['nmms_run_time'] ?? ''),
        'commodity'    => (string)($s['nmms_commodity'] ?? ''),
        'priceNode'    => (string)($s['nmms_price_node'] ?? ''),
        'unitId'       => (string)($s['nmms_unit_id'] ?? ''),
        'urls'         => $request_urls,
        'cert_exists'  => file_exists((string)$s['nmms_cert_path']),
        'pfx_mode'     => $use_native_pfx ? 'native_p12' : 'pem_pair',
    ]);

    foreach ( $request_urls as $url ) {
        $ch = curl_init();
        $curl_opts = [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $soap_body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSLVERSION     => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: "exportResults"',
            ],
        ];

        $is_https = stripos((string)$url, 'https://') === 0;

        if ( $use_native_pfx ) {
            // Method 3: cURL reads PFX directly — no PEM extraction needed
            // Works on OpenSSL 3.x without legacy flag
            $curl_opts[CURLOPT_SSLCERT]         = $s['nmms_cert_path'];
            $curl_opts[CURLOPT_SSLCERTTYPE]     = 'P12';
            $curl_opts[CURLOPT_SSLCERTPASSWD]   = $pfx_pass;
        } else {
            // Methods 1 & 2: PEM files already extracted
            $curl_opts[CURLOPT_SSLCERT] = $cert_file;
            $curl_opts[CURLOPT_SSLKEY]  = $key_file;
        }

        curl_setopt_array( $ch, $curl_opts );
        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err  = curl_error($ch);
        $curl_errno = curl_errno($ch);
        $conn_time_ms = (int) curl_getinfo($ch, CURLINFO_CONNECT_TIME_T);
        $total_time_ms = (int) curl_getinfo($ch, CURLINFO_TOTAL_TIME_T);
        curl_close($ch);

        // Some NMMS environments fail strict TLS due to trust chain/cipher/protocol mismatch.
        // If HTTPS fails at TLS layer, retry once with a compatibility profile.
        if ( $is_https && ! empty($curl_err) ) {
            $ssl_errnos = [35, 51, 58, 60, 77, 83, 90];
            $is_ssl_trust_error = in_array((int)$curl_errno, $ssl_errnos, true)
                || stripos($curl_err, 'certificate') !== false
                || stripos($curl_err, 'schannel') !== false
                || stripos($curl_err, 'untrusted') !== false
                || stripos($curl_err, 'handshake') !== false
                || stripos($curl_err, 'sslv3 alert') !== false;

            if ( $is_ssl_trust_error ) {
                $ch2 = curl_init();
                $curl_opts[CURLOPT_SSL_VERIFYPEER] = false;
                $curl_opts[CURLOPT_SSL_VERIFYHOST] = 0;
                $curl_opts[CURLOPT_SSLVERSION]     = CURL_SSLVERSION_TLSv1_2;
                if ( defined('CURLOPT_SSL_CIPHER_LIST') ) {
                    $curl_opts[CURLOPT_SSL_CIPHER_LIST] = 'DEFAULT@SECLEVEL=0';
                }
                curl_setopt_array( $ch2, $curl_opts );
                $response  = curl_exec($ch2);
                $http_code = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                $curl_err  = curl_error($ch2);
                $curl_errno = curl_errno($ch2);
                $conn_time_ms = (int) curl_getinfo($ch2, CURLINFO_CONNECT_TIME_T);
                $total_time_ms = (int) curl_getinfo($ch2, CURLINFO_TOTAL_TIME_T);
                curl_close($ch2);

                bntm_weimop_debug_log('soap_ssl_retry_insecure', [
                    'url'           => $url,
                    'http_code'     => $http_code,
                    'curl_errno'    => $curl_errno,
                    'curl_error'    => $curl_err,
                    'connect_ms'    => $conn_time_ms,
                    'total_ms'      => $total_time_ms,
                ]);
            }
        }

        if ( ! empty($curl_err) ) {
            $last_error = 'cURL error: ' . $curl_err;
            $attempt_errors[] = $url . ' -> ' . $last_error;
            bntm_weimop_debug_log('soap_attempt_fail', [
                'url'           => $url,
                'http_code'     => $http_code,
                'curl_errno'    => $curl_errno,
                'curl_error'    => $curl_err,
                'connect_ms'    => $conn_time_ms,
                'total_ms'      => $total_time_ms,
            ]);
            continue;
        }
        if ( $http_code !== 200 ) {
            $last_error = 'HTTP ' . $http_code . ' from NMMS MPI service.';
            $attempt_errors[] = $url . ' -> ' . $last_error;
            bntm_weimop_debug_log('soap_attempt_http', [
                'url'           => $url,
                'http_code'     => $http_code,
                'connect_ms'    => $conn_time_ms,
                'total_ms'      => $total_time_ms,
                'response_head' => substr((string)$response, 0, 300),
            ]);
            continue;
        }
        if ( ! $response ) {
            $last_error = 'Empty response from NMMS MPI service.';
            $attempt_errors[] = $url . ' -> ' . $last_error;
            bntm_weimop_debug_log('soap_attempt_empty', [
                'url'           => $url,
                'http_code'     => $http_code,
                'connect_ms'    => $conn_time_ms,
                'total_ms'      => $total_time_ms,
            ]);
            continue;
        }

        // Success on this URL
        $last_error = '';
        bntm_weimop_debug_log('soap_attempt_ok', [
            'url'           => $url,
            'http_code'     => $http_code,
            'connect_ms'    => $conn_time_ms,
            'total_ms'      => $total_time_ms,
            'response_head' => substr((string)$response, 0, 300),
        ]);
        break;
    }

    if ( ! $use_native_pfx ) {
        @unlink($cert_file);
        @unlink($key_file);
    }

    if ( ! empty($last_error) ) {
        bntm_weimop_debug_log('soap_failed', [ 'errors' => $attempt_errors ]);
        return [ 'error' => 'NMMS request failed. ' . implode(' | ', $attempt_errors) ];
    }

    return [ 'xml' => $response, 'http_code' => $http_code ];
}

function bntm_weimop_nmms_result_type_candidates( $result_type ) {
    $rt = strtoupper( trim( (string) $result_type ) );
    if ( $rt === '' ) return [];

    $candidates = [ $rt, str_replace('_', '', $rt) ];
    $alias_map = [
        'RTD_LMP'   => 'RTDLMP',
        'HAP'       => 'DIPCLMP',
        'DAP'       => 'TIPCLMP',
        'OCC_COMP'  => 'OCCResourceComplianceDetail',
        'OCC_COMPLIANCE' => 'OCCResourceComplianceDetail',
    ];

    if ( isset( $alias_map[ $rt ] ) ) {
        $candidates[] = strtoupper( $alias_map[ $rt ] );
    }

    return array_values( array_unique( array_filter( $candidates ) ) );
}

function bntm_weimop_load_export_results_conf( $config_path, $result_type ) {
    if ( trim( (string) $config_path ) === '' && defined('BNTM_WEIMOP_DEFAULT_EXPORT_CONF') ) {
        $config_path = BNTM_WEIMOP_DEFAULT_EXPORT_CONF;
    }
    $config_path = trim( (string) $config_path );
    $config_path = str_replace('\\', '/', $config_path);
    if ( $config_path === '' ) return [ 'error' => 'ExportResultsConf path is not set.' ];
    if ( ! file_exists( $config_path ) ) return [ 'error' => 'ExportResultsConf file not found: ' . $config_path ];
    if ( ! class_exists( 'DOMDocument' ) ) return [ 'error' => 'PHP DOM extension is not available.' ];

    libxml_use_internal_errors( true );
    $doc = new DOMDocument();
    if ( ! @$doc->load( $config_path ) ) {
        libxml_clear_errors();
        return [ 'error' => 'ExportResultsConf XML could not be loaded.' ];
    }

    $xp = new DOMXPath( $doc );
    $type_nodes = $xp->query( "//*[local-name()='ExportResultsType']" );
    if ( ! $type_nodes || $type_nodes->length === 0 ) {
        return [ 'error' => 'No ExportResultsType nodes found in ExportResultsConf.' ];
    }

    $target_node = null;
    $candidates = bntm_weimop_nmms_result_type_candidates( $result_type );
    foreach ( $type_nodes as $node ) {
        $name_attr = $node->attributes ? $node->attributes->getNamedItem('Name') : null;
        if ( ! $name_attr ) continue;
        $name = strtoupper( trim( (string) $name_attr->nodeValue ) );
        if ( in_array( $name, $candidates, true ) ) {
            $target_node = $node;
            break;
        }
    }

    if ( ! $target_node ) {
        return [ 'error' => 'Result type not found in ExportResultsConf: ' . $result_type ];
    }

    $read_text_list = function ( $xpath ) use ( $xp, $target_node ) {
        $out = [];
        $nodes = $xp->query( $xpath, $target_node );
        if ( ! $nodes ) return $out;
        foreach ( $nodes as $n ) {
            $out[] = trim( (string) $n->textContent );
        }
        return $out;
    };

    return [
        'header_names'        => $read_text_list( ".//*[local-name()='ResultsSummary']/*[local-name()='Header']/*[local-name()='HeaderElement']/*[local-name()='Name']" ),
        'header_data_types'   => $read_text_list( ".//*[local-name()='ResultsSummary']/*[local-name()='Header']/*[local-name()='HeaderElement']/*[local-name()='DataType']" ),
        'header_data_lengths' => array_map( 'intval', $read_text_list( ".//*[local-name()='ResultsSummary']/*[local-name()='Header']/*[local-name()='HeaderElement']/*[local-name()='DataLength']" ) ),
        'body_names'          => $read_text_list( ".//*[local-name()='ResultsSummary']/*[local-name()='Body']/*[local-name()='Column']/*[local-name()='Name']" ),
        'body_data_types'     => $read_text_list( ".//*[local-name()='ResultsSummary']/*[local-name()='Body']/*[local-name()='Column']/*[local-name()='DataType']" ),
        'body_data_lengths'   => array_map( 'intval', $read_text_list( ".//*[local-name()='ResultsSummary']/*[local-name()='Body']/*[local-name()='Column']/*[local-name()='DataLength']" ) ),
        'path'                => $config_path,
    ];
}

function bntm_weimop_decode_int32_le( $chunk ) {
    $v = unpack( 'V', $chunk )[1];
    return $v >= 0x80000000 ? $v - 0x100000000 : $v;
}

function bntm_weimop_decode_int64_le( $chunk ) {
    $parts = unpack( 'V2', $chunk );
    $value = ( (int) $parts[2] << 32 ) | (int) $parts[1];
    if ( $parts[2] & 0x80000000 ) $value -= 18446744073709551616.0;
    return (int) $value;
}

function bntm_weimop_decode_nmms_binary_rows( $binary, $result_type, $market_run, $conf ) {
    if ( $binary === '' ) return [ 'rows' => [] ];

    // The Siemens payload is read from the end of the byte array.
    if ( pack('S', 1) === "\x01\x00" ) {
        $binary = strrev( $binary );
    }

    $index = strlen( $binary );
    $header_names = $conf['header_names'] ?? [];
    $header_types = $conf['header_data_types'] ?? [];
    $body_names   = $conf['body_names'] ?? [];
    $body_types   = $conf['body_data_types'] ?? [];
    $body_lengths = $conf['body_data_lengths'] ?? [];

    $no_of_rows = 0;
    if ( isset( $header_names[0], $header_types[0] )
         && strcasecmp( $header_names[0], 'NoOfRows' ) === 0
         && strtolower( $header_types[0] ) === 'int' ) {
        if ( $index < 4 ) return [ 'rows' => [] ];
        $index -= 4;
        $no_of_rows = bntm_weimop_decode_int32_le( substr( $binary, $index, 4 ) );
    }

    if ( $no_of_rows <= 0 ) return [ 'rows' => [] ];

    $no_of_columns = count( $body_names );
    if ( isset( $header_names[1], $header_types[1] )
         && strcasecmp( $header_names[1], 'NoOfColumns' ) === 0
         && strtolower( $header_types[1] ) === 'int' ) {
        if ( $index < 4 ) return [ 'rows' => [] ];
        $index -= 4;
        $no_of_columns = max( 0, bntm_weimop_decode_int32_le( substr( $binary, $index, 4 ) ) );
    }

    $rows = [];
    $market_run_up = strtoupper( trim( (string) $market_run ) );
    $result_type_up = strtoupper( trim( (string) $result_type ) );

    for ( $i = 0; $i < $no_of_rows; $i++ ) {
        $row = [];
        for ( $j = 0; $j < $no_of_columns; $j++ ) {
            $name   = $body_names[ $j ] ?? ('COL_' . $j);
            $type   = strtolower( $body_types[ $j ] ?? '' );
            $length = (int) ( $body_lengths[ $j ] ?? 0 );

            if ( $type === 'long' ) {
                if ( $index < 8 ) break 2;
                $index -= 8;
                $timestamp_ms = bntm_weimop_decode_int64_le( substr( $binary, $index, 8 ) );
                $seconds = (int) floor( $timestamp_ms / 1000 );
                $dt = date( 'Y-m-d H:i:s', $seconds );

                if ( $name === 'TIME_INTERVAL' && ( $market_run_up === 'WAP' || $market_run_up === 'DAP' || $result_type_up === 'TIPCLMP' ) ) {
                    $dt = date( 'Y-m-d H:i:s', strtotime( $dt ) + 3600 );
                } elseif ( $name === 'TIME_INTERVAL' && $market_run_up === 'HAP' ) {
                    $dt = date( 'Y-m-d H:i:s', strtotime( $dt ) + 300 );
                }

                $row[ $name ] = $dt;
            } elseif ( $type === 'string' ) {
                if ( $length < 0 || $index < $length ) break 2;
                $index -= $length;
                $raw = substr( $binary, $index, $length );
                $row[ $name ] = trim( strrev( $raw ), "\0 \t\n\r\0\x0B" );
            } elseif ( $type === 'double' ) {
                if ( $index < 8 ) break 2;
                $index -= 8;
                $row[ $name ] = unpack( 'e', substr( $binary, $index, 8 ) )[1];
            } elseif ( $type === 'int' ) {
                if ( $index < 4 ) break 2;
                $index -= 4;
                $row[ $name ] = bntm_weimop_decode_int32_le( substr( $binary, $index, 4 ) );
            }
        }

        if ( ! empty( $row ) ) $rows[] = $row;
    }

    return [ 'rows' => $rows ];
}

function bntm_weimop_parse_nmms_response( $xml_string, $result_type = '', $settings = [] ) {
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xml_string);
    if ( ! $xml ) return [ 'error' => 'Invalid XML in NMMS response.' ];

    $return_nodes = $xml->xpath('//*[local-name()="return"]');
    if ( ! $return_nodes ) return [ 'rows' => [] ];

    $first_return = $return_nodes[0];
    if ( $first_return instanceof SimpleXMLElement && count( $first_return->children() ) > 0 ) {
        $rows = [];
        foreach ( $return_nodes as $row ) {
            $r = [];
            foreach ( $row->children() as $child ) $r[$child->getName()] = (string) $child;
            if ( ! empty($r) ) $rows[] = $r;
        }
        return [ 'rows' => $rows ];
    }

    // Mode B: SOAP return is base64-encoded binary payload.
    $payload = trim( (string) $first_return );
    if ( $payload === '' ) return [ 'rows' => [] ];

    $binary = base64_decode( preg_replace( '/\s+/', '', $payload ), true );
    if ( $binary === false ) return [ 'rows' => [] ];

    $conf = bntm_weimop_load_export_results_conf( $settings['nmms_export_conf'] ?? '', $result_type );
    if ( isset( $conf['error'] ) ) {
        return [ 'error' => $conf['error'] ];
    }

    return bntm_weimop_decode_nmms_binary_rows(
        $binary,
        $result_type,
        $settings['nmms_market_run'] ?? '',
        $conf
    );
}

function bntm_weimop_migrate_core_tables( $db ) {
    $needs = false;

    $rtd_cols = [];
    $res = $db->query("PRAGMA table_info(RTDSchedules)");
    while($res && ($c = $res->fetchArray(SQLITE3_ASSOC))) $rtd_cols[] = $c['name'];
    if ( empty($rtd_cols) || !in_array('RESOURCE_NAME', $rtd_cols, true) || !in_array('LMP_CONGESTION', $rtd_cols, true) ) $needs = true;

    $hap_exists = false;
    $res = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='HAPSchedules'");
    if ($res) $hap_exists = true;
    if (!$hap_exists) $needs = true;

    $occ_cols = [];
    $res = $db->query("PRAGMA table_info(OCCResourcesComplianceDetail)");
    while($res && ($c = $res->fetchArray(SQLITE3_ASSOC))) $occ_cols[] = $c['name'];
    if ( empty($occ_cols) || !in_array('REGISTERED_CAP', $occ_cols, true) || !in_array('NON_COMPLIANCE_FLAG', $occ_cols, true) ) $needs = true;

    if (!$needs) return;

    foreach (['RTDSchedules','HAPSchedules','OCCResourcesComplianceDetail'] as $t) {
        $db->exec("DROP TABLE IF EXISTS {$t}");
    }
    $schema = bntm_weimop_sqlite_schema();
    foreach (['RTDSchedules','HAPSchedules','OCCResourcesComplianceDetail'] as $t) {
        if (!empty($schema[$t])) $db->exec($schema[$t]);
    }
}

function bntm_weimop_insert_rows( $db, $table, $rows ) {
    $inserted = 0;
    foreach ($rows as $r) {
        $ts = $r['timeInterval'] ?? $r['TIME_INTERVAL'] ?? '';
        if (!$ts) continue;
        try {
            switch ($table) {
                case 'RTDSchedules':
                    $st = $db->prepare("INSERT OR REPLACE INTO RTDSchedules (TIME_INTERVAL,RESOURCE_NAME,SCHEDULE,LMP,LOSS_FACTOR,LMP_ENERGY,LMP_LOSS,LMP_CONGESTION) VALUES (?,?,?,?,?,?,?,?)");
                    $st->bindValue(1,$ts);
                    $st->bindValue(2,(string)($r['resourceName']??$r['RESOURCE_NAME']??$r['unitId']??$r['UNIT_ID']??''));
                    $st->bindValue(3,(string)($r['schedule']??$r['SCHEDULE']??'0'));
                    $st->bindValue(4,(string)($r['lmp']??$r['LMP']??'0'));
                    $st->bindValue(5,(string)($r['lossFactor']??$r['LOSS_FACTOR']??''));
                    $st->bindValue(6,(string)($r['lmpEnergy']??$r['LMP_ENERGY']??''));
                    $st->bindValue(7,(string)($r['lmpLoss']??$r['LMP_LOSS']??''));
                    $st->bindValue(8,(string)($r['lmpCongestion']??$r['LMP_CONGESTION']??''));
                    $st->execute(); $inserted++; break;
                case 'HAPSchedules':
                    $st = $db->prepare("INSERT OR REPLACE INTO HAPSchedules (TIME_INTERVAL,RESOURCE_NAME,SCHEDULE,LMP,LOSS_FACTOR,LMP_ENERGY,LMP_LOSS,LMP_CONGESTION) VALUES (?,?,?,?,?,?,?,?)");
                    $st->bindValue(1,$ts);
                    $st->bindValue(2,(string)($r['resourceName']??$r['RESOURCE_NAME']??$r['unitId']??$r['UNIT_ID']??''));
                    $st->bindValue(3,(string)($r['schedule']??$r['SCHEDULE']??'0'));
                    $st->bindValue(4,(string)($r['lmp']??$r['LMP']??$r['price']??$r['PRICE']??'0'));
                    $st->bindValue(5,(string)($r['lossFactor']??$r['LOSS_FACTOR']??''));
                    $st->bindValue(6,(string)($r['lmpEnergy']??$r['LMP_ENERGY']??''));
                    $st->bindValue(7,(string)($r['lmpLoss']??$r['LMP_LOSS']??''));
                    $st->bindValue(8,(string)($r['lmpCongestion']??$r['LMP_CONGESTION']??''));
                    $st->execute(); $inserted++; break;
                case 'DAPResults':
                    $st = $db->prepare("INSERT OR REPLACE INTO DAPResults (TIME_INTERVAL,PRICE) VALUES (?,?)");
                    $st->bindValue(1,$ts); $st->bindValue(2,(float)($r['price']??$r['PRICE']??0));
                    $st->execute(); $inserted++; break;
                case 'OCCResourcesComplianceDetail':
                    $st = $db->prepare("INSERT OR REPLACE INTO OCCResourcesComplianceDetail (TIME_INTERVAL,RESOURCE_NAME,REGISTERED_CAP,OFFERED_CAP,NON_COMPLIANCE_FLAG,NON_COMPLIANCE_COUNT,PROBABLE_BREACH,PREVIOUS_VIOLATION,OCC_ENABLED,COMPLIANCE_EXEMPT,EMAIL_SENT) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
                    $st->bindValue(1,$ts);
                    $st->bindValue(2,(string)($r['resourceName']??$r['RESOURCE_NAME']??$r['unitId']??$r['UNIT_ID']??''));
                    $st->bindValue(3,(int)($r['registeredCapacity']??$r['REGISTERED_CAP']??0));
                    $st->bindValue(4,(int)($r['offeredCapacity']??$r['OFFERED_CAP']??0));
                    $st->bindValue(5,(string)($r['nonComplianceFlag']??$r['NON_COMPLIANCE_FLAG']??''));
                    $st->bindValue(6,(int)($r['nonComplianceCount']??$r['NON_COMPLIANCE_COUNT']??0));
                    $st->bindValue(7,(string)($r['probableBreach']??$r['PROBABLE_BREACH']??''));
                    $st->bindValue(8,(string)($r['previousViolation']??$r['PREVIOUS_VIOLATION']??''));
                    $st->bindValue(9,(string)($r['occEnabled']??$r['OCC_ENABLED']??''));
                    $st->bindValue(10,(string)($r['complianceExempt']??$r['COMPLIANCE_EXEMPT']??''));
                    $st->bindValue(11,(int)($r['emailSent']??$r['EMAIL_SENT']??0));
                    $st->execute(); $inserted++; break;
            }
        } catch (Exception $e) {}
    }
    return $inserted;
}

/* -------------------------------------------------------
   K. AJAX -- FETCH HISTORICAL DATA
   CRITICAL FIX: cert password is passed via POST field
   'cert_password'. Use wp_unslash() ONLY — never
   sanitize_text_field() on passwords as it strips
   special characters that are valid in passwords.
------------------------------------------------------- */

function bntm_ajax_weimop_fetch_historical() {
    check_ajax_referer('weimop_nonce','nonce');
    if ( ! is_user_logged_in() ) wp_send_json_error(['message'=>'Unauthorized']);

    $uid = get_current_user_id();
    $s   = bntm_weimop_get_settings($uid);

    if ( empty($s['nmms_cert_path']) || ! file_exists($s['nmms_cert_path']) )
        wp_send_json_error(['message'=>'Certificate file not found. Upload your .pfx first.']);
    if ( ! function_exists('openssl_pkcs12_read') )
        wp_send_json_error(['message'=>'PHP OpenSSL extension not available on this server.']);
    if ( ! function_exists('curl_init') )
        wp_send_json_error(['message'=>'PHP cURL extension not available on this server.']);

    // Raw password: wp_unslash only — sanitize_text_field would strip special chars
    $s['_cert_pass_raw'] = isset($_POST['cert_password']) ? wp_unslash($_POST['cert_password']) : '';

    $db = bntm_weimop_open_db(false);
    if (!$db) wp_send_json_error(['message'=>'Cannot open SQLite database.']);
    bntm_weimop_migrate_core_tables($db);

    $now            = time();
    $step           = 300;
    $intervals      = 288;
    $total_inserted = 0;
    $errors         = [];

    $fetch_types = ! empty($s['nmms_result_type'])
        ? [ [ 'type' => $s['nmms_result_type'], 'table' => 'RTDSchedules' ] ]
        : [
            [ 'type' => 'RTD_LMP',  'table' => 'RTDSchedules' ],
            [ 'type' => 'HAP',      'table' => 'HAPSchedules' ],
            [ 'type' => 'DAP',      'table' => 'DAPResults'   ],
            [ 'type' => 'OCC_COMP', 'table' => 'OCCResourcesComplianceDetail' ],
          ];

    foreach ( $fetch_types as $ft ) {
        $type_inserted = 0;
        for ( $i = 1; $i <= $intervals; $i++ ) {
            $ts           = $now - ($intervals - $i) * $step;
            $interval_end = date('Y-m-d H:i:s', $ts - ($ts % $step));
            $result       = bntm_weimop_nmms_soap_request($s, $ft['type'], $interval_end);
            if ( isset($result['error']) ) {
                $errors[] = $ft['type'] . ' @ ' . $interval_end . ': ' . $result['error'];
                break;
            }
            $parsed = bntm_weimop_parse_nmms_response($result['xml'], $ft['type'], $s);
            if ( isset($parsed['error']) || empty($parsed['rows']) ) continue;
            $type_inserted += bntm_weimop_insert_rows($db, $ft['table'], $parsed['rows']);
        }
        $total_inserted += $type_inserted;
        try {
            $db->exec("INSERT INTO ExtractorLog (status,message,rows_added) VALUES ('ok','" .
                SQLite3::escapeString($ft['type'].' backfill') . "'," . $type_inserted . ")");
        } catch(Exception $e) {}
    }

    $db->close();

    $msg = 'Backfill complete. ' . $total_inserted . ' row(s) inserted.';
    if ($errors) $msg .= ' Note: ' . implode(' | ', array_slice($errors, 0, 3));

    wp_send_json_success(['message' => $msg, 'inserted' => $total_inserted, 'errors' => $errors]);
}

/* -------------------------------------------------------
   L. CONNECTION CHECK
------------------------------------------------------- */

function bntm_weimop_check_connection() {
    $s      = bntm_weimop_get_settings(get_current_user_id());
    $checks = []; $issues = [];
    $path   = bntm_weimop_db_path();

    $checks['sqlite3_ext'] = class_exists('SQLite3');
    if (!$checks['sqlite3_ext']) $issues[] = 'PHP SQLite3 extension not enabled on this server.';

    $checks['db_exists']   = file_exists($path);
    $checks['db_writable'] = $checks['db_exists'] && is_writable($path);
    if (!$checks['db_exists']) $issues[] = 'SQLite database file not found. Click Re-initialize Tables.';

    $checks['tables_exist'] = $checks['has_rtd'] = $checks['has_hap'] = $checks['has_dap'] = $checks['has_occ'] = false;

    if ($checks['db_exists'] && $checks['sqlite3_ext']) {
        $db = bntm_weimop_open_db(true);
        if ($db) {
            try {
                $needed  = ['RTDSchedules','HAPSchedules','DAPResults','OCCResourcesComplianceDetail'];
                $present = [];
                $res = $db->query("SELECT name FROM sqlite_master WHERE type='table'");
                while ($r = $res->fetchArray(SQLITE3_ASSOC)) $present[] = $r['name'];
                $checks['tables_exist'] = count(array_intersect($needed,$present)) === count($needed);
                if (!$checks['tables_exist']) $issues[] = 'One or more WESM tables are missing. Click Re-initialize Tables.';
                $checks['has_rtd'] = (int)$db->querySingle("SELECT COUNT(*) FROM RTDSchedules") > 0;
                $checks['has_hap'] = ((int)$db->querySingle("SELECT COUNT(*) FROM HAPSchedules") > 0) || ((int)$db->querySingle("SELECT COUNT(*) FROM HAPResults") > 0);
                $checks['has_dap'] = (int)$db->querySingle("SELECT COUNT(*) FROM DAPResults")   > 0;
                $checks['has_occ'] = (int)$db->querySingle("SELECT COUNT(*) FROM OCCResourcesComplianceDetail") > 0;
            } catch (Exception $e) { $issues[] = 'Database query error: ' . $e->getMessage(); }
            $db->close();
        }
    }

    $checks['pfx_set']    = !empty($s['nmms_cert_path']);
    $checks['pfx_exists'] = $checks['pfx_set'] && file_exists($s['nmms_cert_path']);
    $checks['curl_ssl']   = function_exists('curl_init') && function_exists('openssl_pkcs12_read');

    if (!$checks['pfx_set'])      $issues[] = 'Certificate path not configured. Upload your .pfx file.';
    elseif (!$checks['pfx_exists']) $issues[] = 'Certificate file not found at saved path: ' . $s['nmms_cert_path'];
    if (!$checks['curl_ssl'])     $issues[] = 'PHP cURL or OpenSSL extension not available on this server.';

    $checks['nmms_url']  = !empty($s['nmms_main_url']);
    $checks['cert_name'] = !empty($s['nmms_cert_name']);
    if (!$checks['nmms_url'])  $issues[] = 'NMMS Main URL is not set.';
    if (!$checks['cert_name']) $issues[] = 'Certificate Name (CN) not set. Upload your .pfx to auto-fill.';

    return ['checks'=>$checks,'issues'=>$issues,'ok'=>empty($issues)];
}

/* -------------------------------------------------------
   M. AJAX -- MISC HANDLERS
------------------------------------------------------- */


/* -------------------------------------------------------
   DIAGNOSTIC HANDLER
   Tests every prerequisite for openssl_pkcs12_read and
   the NMMS SOAP call. Returns a structured report so the
   user can see exactly what is failing server-side.
------------------------------------------------------- */

function bntm_ajax_weimop_diag() {
    check_ajax_referer('weimop_nonce','nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message'=>'Unauthorized']);

    $uid      = get_current_user_id();
    $s        = bntm_weimop_get_settings($uid);
    $report   = [];
    $all_pass = true;

    // Helper
    $chk = function($label, $pass, $detail='') use (&$report, &$all_pass) {
        if (!$pass) $all_pass = false;
        $report[] = ['label'=>$label,'pass'=>(bool)$pass,'detail'=>$detail];
    };

    // PHP extensions
    $chk('PHP OpenSSL extension loaded',    extension_loaded('openssl'),
         extension_loaded('openssl') ? 'openssl '.OPENSSL_VERSION_TEXT : 'Not loaded - contact your host to enable php-openssl');
    $chk('openssl_pkcs12_read() exists',   function_exists('openssl_pkcs12_read'),
         function_exists('openssl_pkcs12_read') ? 'Available' : 'Function missing despite openssl being loaded - unusual; try PHP 7.4+');
    $chk('PHP cURL extension loaded',       extension_loaded('curl'),
         extension_loaded('curl') ? curl_version()['version'] ?? 'ok' : 'Not loaded - contact your host to enable php-curl');
    $chk('PHP SQLite3 extension loaded',    class_exists('SQLite3'),
         class_exists('SQLite3') ? 'Available' : 'Not loaded');

    // PFX file
    $pfx_path = $s['nmms_cert_path'] ?? '';
    $chk('Certificate path is set',         !empty($pfx_path),
         !empty($pfx_path) ? $pfx_path : 'Not set - upload your .pfx in the Certificate section');

    $pfx_exists = !empty($pfx_path) && file_exists($pfx_path);
    $chk('Certificate file exists on disk', $pfx_exists,
         $pfx_exists ? 'Found at '.$pfx_path : 'File not found at: '.$pfx_path);

    $pfx_readable = $pfx_exists && is_readable($pfx_path);
    $chk('Certificate file is readable',    $pfx_readable,
         $pfx_readable ? 'Readable' : 'File exists but PHP cannot read it - check file permissions (should be 0644)');

    // PFX size sanity
    if ($pfx_exists) {
        $sz = filesize($pfx_path);
        $chk('Certificate file size is reasonable', $sz > 100 && $sz < 1048576,
             $sz.' bytes'.($sz <= 100 ? ' - suspiciously small, may be corrupt' : ''));
    }

    // Try reading PFX with provided password
    // Raw password - wp_unslash ONLY, never sanitize_text_field
    $raw_pass = isset($_POST['cert_password']) ? wp_unslash($_POST['cert_password']) : '';
    $pass_len = strlen($raw_pass);
    $chk('Password received by server',  true,
         $pass_len > 0 ? $pass_len.' characters received' : 'Empty password (blank = no password on cert)');

    if ($pfx_readable && function_exists('openssl_pkcs12_read')) {
        $pfx_data = file_get_contents($pfx_path);
        $certs    = [];
        // Clear any stale OpenSSL errors first
        while (openssl_error_string() !== false) {}

        $ok_read  = @openssl_pkcs12_read($pfx_data, $certs, $raw_pass);

        $ossl_err = '';
        while ($e = openssl_error_string()) $ossl_err .= $e . ' ';
        $ossl_err = trim($ossl_err);

        $chk('Method 1: openssl_pkcs12_read()', $ok_read,
             $ok_read
                 ? 'Success — PFX opened with PHP built-in'
                 : 'Failed: '.$ossl_err
        );

        // Method 2: test shell openssl binary
        $shell_ok  = false;
        $shell_msg = 'exec() disabled on this server — cannot test';
        if (function_exists('exec') && !in_array('exec', array_map('trim', explode(',', ini_get('disable_functions'))))) {
            $tmp_cert = sys_get_temp_dir().'/weimop_test_cert_'.$uid.'.pem';
            $pe = escapeshellarg($pfx_path);
            $pw = escapeshellarg($raw_pass);
            $to = escapeshellarg($tmp_cert);
            exec("openssl pkcs12 -legacy -in $pe -clcerts -nokeys -out $to -passin pass:$pw 2>&1", $sout, $src);
            if ($src === 0 && file_exists($tmp_cert) && filesize($tmp_cert) > 0) {
                $shell_ok  = true;
                $shell_msg = 'Success with openssl -legacy flag';
                @unlink($tmp_cert);
            } else {
                // Try without -legacy
                exec("openssl pkcs12 -in $pe -clcerts -nokeys -out $to -passin pass:$pw 2>&1", $sout2, $src2);
                if ($src2 === 0 && file_exists($tmp_cert) && filesize($tmp_cert) > 0) {
                    $shell_ok  = true;
                    $shell_msg = 'Success without -legacy flag (modern PFX)';
                    @unlink($tmp_cert);
                } else {
                    $shell_msg = 'Failed (exit '.$src.'): '.implode(' ', array_slice($sout, 0, 2));
                    @unlink($tmp_cert);
                }
            }
        }
        $chk('Method 2: shell openssl pkcs12', $shell_ok, $shell_msg);

        // Method 3: cURL P12 — always available if cURL is loaded
        $curl_p12_ok = function_exists('curl_init');
        $chk('Method 3: cURL P12 direct (CURLOPT_SSLCERTTYPE=P12)', $curl_p12_ok,
             $curl_p12_ok
                 ? 'cURL available — P12 passthrough will be used as fallback (most compatible)'
                 : 'cURL not available'
        );

        // For cert details, use whichever method worked
        if (!$ok_read && $shell_ok) {
            // re-extract for display
            $tmp2 = sys_get_temp_dir().'/weimop_disp_'.$uid.'.pem';
            exec("openssl pkcs12 -legacy -in $pe -clcerts -nokeys -out ".escapeshellarg($tmp2)." -passin pass:$pw 2>&1");
            if (file_exists($tmp2)) {
                $cert_pem = file_get_contents($tmp2); @unlink($tmp2);
                $certs['cert'] = $cert_pem;
            }
        }

        if ($ok_read) {
            $chk('Certificate bag present',    isset($certs['cert']) && !empty($certs['cert']),
                 isset($certs['cert']) ? 'cert key present ('.strlen($certs['cert']).' bytes)' : 'cert key missing in PFX');
            $chk('Private key bag present',    isset($certs['pkey']) && !empty($certs['pkey']),
                 isset($certs['pkey']) ? 'pkey present' : 'private key missing - PFX may be cert-only');

            // Parse cert to show subject
            if (!empty($certs['cert'])) {
                $parsed = openssl_x509_parse($certs['cert']);
                if ($parsed) {
                    $cn     = $parsed['subject']['CN'] ?? '(none)';
                    $expiry = isset($parsed['validTo_time_t'])
                        ? date('Y-m-d', $parsed['validTo_time_t']).' ('.round(($parsed['validTo_time_t']-time())/86400).' days)'
                        : '(unknown)';
                    $chk('Certificate Subject CN',   true, $cn);
                    $chk('Certificate Expiry',       time() < ($parsed['validTo_time_t']??0),
                         $expiry);
                }
            }
        }
    }

    // NMMS URL reachability check across configured candidates (service/main/backup + HTTP fallback)
    $urls = bntm_weimop_nmms_build_urls( $s );
    if (!empty($urls) && function_exists('curl_init')) {
        $reachable = false;
        $details = [];
        foreach ( $urls as $url ) {
            $ch = curl_init();
            curl_setopt_array($ch,[
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_SSL_VERIFYPEER => false, // reachability probe only
                CURLOPT_SSL_VERIFYHOST => 0,
            ]);
            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);

            $ok = ($code > 0 && $code < 600);
            if ( $ok ) {
                $reachable = true;
                $soap_hint = (is_string($body) && stripos($body, 'No such operation: null') !== false)
                    ? ' (SOAP endpoint reachable via GET; use POST exportResults in app)'
                    : '';
                $details[] = 'OK HTTP '.$code.' from '.$url.$soap_hint;
                break;
            }

            $details[] = 'Fail '.$url.($err ? ': '.$err : ($code ? ' HTTP '.$code : ' (timeout or DNS failure)'));
        }

        $chk('NMMS MPI URL reachable', $reachable, implode(' | ', $details));
    } else {
        $chk('NMMS MPI URL set', !empty($urls), !empty($urls) ? implode(' | ', $urls) : 'Not set');
    }

    // SOAP debug probe: capture raw XML/fault body for troubleshooting.
    $soap_debug = [
        'attempted'  => false,
        'ok'         => false,
        'resultType' => '',
        'intervalEnd'=> '',
        'httpCode'   => 0,
        'xml'        => '',
        'error'      => '',
    ];

    if ( ! empty($urls) && $pfx_readable && function_exists('curl_init') ) {
        $diag_result_type = trim((string)($s['nmms_result_type'] ?? ''));
        if ( $diag_result_type === '' ) $diag_result_type = 'RTD_LMP';

        $ts = time();
        $interval_end = date('Y-m-d H:i:s', $ts - ($ts % 300));

        $s['_cert_pass_raw'] = $raw_pass;
        $soap_debug['attempted']   = true;
        $soap_debug['resultType']  = $diag_result_type;
        $soap_debug['intervalEnd'] = $interval_end;

        $soap_probe = bntm_weimop_nmms_soap_request( $s, $diag_result_type, $interval_end );
        if ( isset($soap_probe['error']) ) {
            $soap_debug['error'] = (string) $soap_probe['error'];
            $chk('SOAP exportResults probe', false, $soap_debug['error']);
        } else {
            $soap_debug['ok'] = true;
            $soap_debug['httpCode'] = (int)($soap_probe['http_code'] ?? 0);
            $raw_xml = (string)($soap_probe['xml'] ?? '');
            if ( strlen($raw_xml) > 12000 ) {
                $raw_xml = substr($raw_xml, 0, 12000) . "\n... [truncated]";
            }
            $soap_debug['xml'] = $raw_xml;
            $chk('SOAP exportResults probe', true, 'HTTP '.$soap_debug['httpCode'].' (raw XML captured)');
        }
    }

    wp_send_json_success([
        'report' => $report,
        'all_pass' => $all_pass,
        'soap_debug' => $soap_debug,
    ]);
}

function bntm_ajax_weimop_connection_status() {
    check_ajax_referer('weimop_nonce','nonce');
    if (!is_user_logged_in()) wp_send_json_error();
    wp_send_json_success(bntm_weimop_check_connection());
}

function bntm_ajax_weimop_upload_pfx() {
    check_ajax_referer('weimop_nonce','nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message'=>'Unauthorized']);
    if (empty($_FILES['pfx_file']) || $_FILES['pfx_file']['error'] !== UPLOAD_ERR_OK)
        wp_send_json_error(['message'=>'File upload failed (code: '.($_FILES['pfx_file']['error']??'?').').']);
    $file = $_FILES['pfx_file'];
    $ext  = strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
    if (!in_array($ext,['pfx','p12'],true)) wp_send_json_error(['message'=>'Only .pfx or .p12 files are permitted.']);
    $dir      = bntm_weimop_pfx_dir();
    $uid      = get_current_user_id();
    $filename = 'cert_u' . $uid . '_' . sanitize_file_name($file['name']);
    $dest     = trailingslashit($dir) . $filename;
    if (!move_uploaded_file($file['tmp_name'],$dest))
        wp_send_json_error(['message'=>'Could not write file. Check directory permissions on '.$dir]);
    $settings = bntm_weimop_get_settings($uid);
    $settings['nmms_cert_path'] = $dest;
    update_user_meta($uid,'bntm_weimop_settings',$settings);
    wp_send_json_success(['path'=>$dest,'filename'=>$filename,'message'=>'Certificate uploaded and path saved.']);
}

function bntm_ajax_weimop_upload_export_conf() {
    check_ajax_referer('weimop_nonce','nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message'=>'Unauthorized']);

    if (empty($_FILES['export_conf_file']) || $_FILES['export_conf_file']['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error(['message'=>'File upload failed (code: '.($_FILES['export_conf_file']['error']??'?').').']);
    }

    $file = $_FILES['export_conf_file'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'xml') {
        wp_send_json_error(['message'=>'Only .xml files are allowed for ExportResultsConf.']);
    }

    $dir      = bntm_weimop_conf_dir();
    $uid      = get_current_user_id();
    $filename = 'export_conf_u' . $uid . '_' . sanitize_file_name($file['name']);
    $dest     = trailingslashit($dir) . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        wp_send_json_error(['message'=>'Could not write file. Check directory permissions on '.$dir]);
    }

    $settings = bntm_weimop_get_settings($uid);
    $settings['nmms_export_conf'] = $dest;
    update_user_meta($uid,'bntm_weimop_settings',$settings);

    wp_send_json_success([
        'path' => $dest,
        'filename' => $filename,
        'message' => 'ExportResultsConf uploaded and path saved.'
    ]);
}

function bntm_ajax_weimop_save_settings() {
    check_ajax_referer('weimop_nonce','nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message'=>'Unauthorized']);
    $uid = get_current_user_id();
    $cur = bntm_weimop_get_settings($uid);
    $new = [
        'email_sender'       => sanitize_text_field($_POST['email_sender']??''),
        'email_password'     => $cur['email_password'],
        'email_recipients'   => sanitize_text_field($_POST['email_recipients']??''),
        'price_alert'        => floatval($_POST['price_alert']??0),
        'refresh_interval'   => max(60,intval($_POST['refresh_interval']??300)),
        'nmms_operation'     => sanitize_text_field($_POST['nmms_operation']??''),
        'nmms_main_url'      => esc_url_raw($_POST['nmms_main_url']??''),
        'nmms_backup_url'    => esc_url_raw($_POST['nmms_backup_url']??''),
        'nmms_url'           => esc_url_raw($_POST['nmms_url']??''),
        'nmms_cert_name'     => sanitize_text_field($_POST['nmms_cert_name']??''),
        'nmms_cert_path'     => sanitize_text_field(!empty($_POST['nmms_cert_path'])?$_POST['nmms_cert_path']:$cur['nmms_cert_path']),
        'nmms_cert_password' => $cur['nmms_cert_password'],
        'nmms_friendly_name' => sanitize_text_field($_POST['nmms_friendly_name']??''),
        'nmms_export_conf'   => sanitize_text_field($_POST['nmms_export_conf']??''),
        'nmms_result_type'   => sanitize_text_field($_POST['nmms_result_type']??''),
        'nmms_market_run'    => sanitize_text_field($_POST['nmms_market_run']??''),
        'nmms_region_name'   => sanitize_text_field($_POST['nmms_region_name']??''),
        'nmms_run_time'      => sanitize_text_field($_POST['nmms_run_time']??''),
        'nmms_commodity'     => sanitize_text_field($_POST['nmms_commodity']??''),
        'nmms_price_node'    => sanitize_text_field($_POST['nmms_price_node']??''),
        'nmms_interval_end'  => sanitize_text_field($_POST['nmms_interval_end']??''),
        'nmms_unit_id'       => sanitize_text_field($_POST['nmms_unit_id']??''),
    ];
    // Passwords: wp_unslash only — sanitize_text_field strips special chars
    $ep = isset($_POST['email_password'])     ? wp_unslash($_POST['email_password'])     : '';
    if ($ep !== '') $new['email_password'] = wp_hash_password($ep);
    $cp = isset($_POST['nmms_cert_password']) ? wp_unslash($_POST['nmms_cert_password']) : '';
    if ($cp !== '') $new['nmms_cert_password'] = wp_hash_password($cp);
    update_user_meta($uid,'bntm_weimop_settings',$new);
    wp_send_json_success(['message'=>'Settings saved successfully.','status'=>bntm_weimop_check_connection()]);
}

function bntm_ajax_weimop_get_market_snapshot() {
    check_ajax_referer('weimop_nonce','nonce'); if(!is_user_logged_in())wp_send_json_error();
    wp_send_json_success(bntm_weimop_read_snapshot());
}
function bntm_ajax_weimop_get_chart_series() {
    check_ajax_referer('weimop_nonce','nonce'); if(!is_user_logged_in())wp_send_json_error();
    wp_send_json_success(bntm_weimop_read_rtd_series());
}
function bntm_ajax_weimop_get_hap_series() {
    check_ajax_referer('weimop_nonce','nonce'); if(!is_user_logged_in())wp_send_json_error();
    wp_send_json_success(bntm_weimop_read_hap_series());
}
function bntm_ajax_weimop_get_dap_series() {
    check_ajax_referer('weimop_nonce','nonce'); if(!is_user_logged_in())wp_send_json_error();
    wp_send_json_success(bntm_weimop_read_dap_series());
}
function bntm_ajax_weimop_get_occ_series() {
    check_ajax_referer('weimop_nonce','nonce'); if(!is_user_logged_in())wp_send_json_error();
    wp_send_json_success(bntm_weimop_read_occ_series());
}

/* -------------------------------------------------------
   N. SQLITE READERS
------------------------------------------------------- */

function bntm_weimop_fetch_rows($db,$sql){
    $r=[];try{$res=$db->query($sql);while($row=$res->fetchArray(SQLITE3_ASSOC))$r[]=$row;}catch(Exception $e){}return $r;
}
function bntm_weimop_read_snapshot(){
    $o=['last_interval'=>'--','rtd_schedule'=>'--','lmp_price'=>'--','offered_cap'=>'--','hap_price'=>'--','dap_price'=>'--'];
    $db=bntm_weimop_open_db(true);if(!$db)return $o;
    try{
        $r=$db->querySingle("SELECT TIME_INTERVAL,SCHEDULE,LMP FROM RTDSchedules ORDER BY TIME_INTERVAL DESC LIMIT 1",true);
        if(is_array($r)){$o['last_interval']=(string)($r['TIME_INTERVAL']??'--');$o['rtd_schedule']=isset($r['SCHEDULE'])?number_format((float)$r['SCHEDULE'],2):'--';$o['lmp_price']=isset($r['LMP'])?number_format((float)$r['LMP'],2):'--';}
        $r=$db->querySingle("SELECT OFFERED_CAP FROM OCCResourcesComplianceDetail ORDER BY TIME_INTERVAL DESC LIMIT 1",true);
        if(is_array($r)&&isset($r['OFFERED_CAP']))$o['offered_cap']=number_format((float)$r['OFFERED_CAP'],2);
        $r=$db->querySingle("SELECT LMP FROM HAPSchedules ORDER BY TIME_INTERVAL DESC LIMIT 1",true);
        if(is_array($r)&&isset($r['LMP']))$o['hap_price']=number_format((float)$r['LMP'],2);
        else {
            $r=$db->querySingle("SELECT PRICE FROM HAPResults ORDER BY TIME_INTERVAL DESC LIMIT 1",true);
            if(is_array($r)&&isset($r['PRICE']))$o['hap_price']=number_format((float)$r['PRICE'],2);
        }
        $r=$db->querySingle("SELECT PRICE FROM DAPResults ORDER BY TIME_INTERVAL DESC LIMIT 1",true);
        if(is_array($r)&&isset($r['PRICE']))$o['dap_price']=number_format((float)$r['PRICE'],2);
    }catch(Exception $e){}
    $db->close();return $o;
}
function bntm_weimop_read_rtd_series(){
    $o=['rtd'=>[],'lmp'=>[]];$db=bntm_weimop_open_db(true);if(!$db)return $o;
    $rows=array_reverse(bntm_weimop_fetch_rows($db,"SELECT TIME_INTERVAL,SCHEDULE,LMP FROM RTDSchedules ORDER BY TIME_INTERVAL DESC LIMIT 288"));
    $db->close();
    foreach($rows as $r){$ts=strtotime((string)($r['TIME_INTERVAL']??''));if(!$ts)continue;$o['rtd'][]=['time'=>$ts,'value'=>(float)($r['SCHEDULE']??0)];$o['lmp'][]=['time'=>$ts,'value'=>(float)($r['LMP']??0)];}
    return $o;
}
function bntm_weimop_read_hap_series(){
    $o=['hap'=>[]];$db=bntm_weimop_open_db(true);if(!$db)return $o;
    $rows=array_reverse(bntm_weimop_fetch_rows($db,"SELECT TIME_INTERVAL,LMP FROM HAPSchedules ORDER BY TIME_INTERVAL DESC LIMIT 48"));
    if(empty($rows)) $rows=array_reverse(bntm_weimop_fetch_rows($db,"SELECT TIME_INTERVAL,PRICE FROM HAPResults ORDER BY TIME_INTERVAL DESC LIMIT 48"));
    $db->close();
    foreach($rows as $r){$ts=strtotime((string)($r['TIME_INTERVAL']??''));if(!$ts)continue;$o['hap'][]=['time'=>$ts,'value'=>(float)($r['LMP']??$r['PRICE']??0)];}
    return $o;
}
function bntm_weimop_read_dap_series(){
    $o=['dap'=>[]];$db=bntm_weimop_open_db(true);if(!$db)return $o;
    $rows=bntm_weimop_fetch_rows($db,"SELECT TIME_INTERVAL,PRICE FROM DAPResults ORDER BY TIME_INTERVAL ASC LIMIT 288");
    $db->close();
    foreach($rows as $r){$ts=strtotime((string)($r['TIME_INTERVAL']??''));if(!$ts)continue;$o['dap'][]=['time'=>$ts,'value'=>(float)($r['PRICE']??0)];}
    return $o;
}
function bntm_weimop_read_occ_series(){
    $o=['offered'=>[],'scheduled'=>[]];$db=bntm_weimop_open_db(true);if(!$db)return $o;
    $rows=array_reverse(bntm_weimop_fetch_rows($db,"SELECT TIME_INTERVAL,OFFERED_CAP FROM OCCResourcesComplianceDetail ORDER BY TIME_INTERVAL DESC LIMIT 288"));
    $db->close();
    foreach($rows as $r){$ts=strtotime((string)($r['TIME_INTERVAL']??''));if(!$ts)continue;$off=(float)($r['OFFERED_CAP']??0);$o['offered'][]=['time'=>$ts,'value'=>$off];$o['scheduled'][]=['time'=>$ts,'value'=>0];}
    return $o;
}

/* -------------------------------------------------------
   O. SETTINGS DEFAULTS & GETTER
------------------------------------------------------- */

function bntm_weimop_default_settings(){
    return [
        'email_sender'=>'','email_password'=>'','email_recipients'=>'',
        'price_alert'=>0,'refresh_interval'=>300,
        'nmms_operation'=>'exportResults',
        'nmms_main_url'=>'http://mpiwebp.iemop.ph/SiemensServices/ExportResultsServiceImpl',
        'nmms_backup_url'=>'http://mpiwebb.iemop.ph/SiemensServices/ExportResultsServiceImpl',
        'nmms_url'=>'','nmms_cert_name'=>'','nmms_cert_path'=>'',
        'nmms_cert_password'=>'','nmms_friendly_name'=>'','nmms_export_conf'=>BNTM_WEIMOP_DEFAULT_EXPORT_CONF,
        'nmms_result_type'=>'','nmms_market_run'=>'','nmms_region_name'=>'',
        'nmms_run_time'=>'','nmms_commodity'=>'','nmms_price_node'=>'',
        'nmms_interval_end'=>'','nmms_unit_id'=>'',
    ];
}
function bntm_weimop_get_settings($uid){
    $s=get_user_meta($uid,'bntm_weimop_settings',true);
    if(!is_array($s))$s=[];
    return array_merge(bntm_weimop_default_settings(),$s);
}

/* -------------------------------------------------------
   K. IEMOP PUBLIC MARKET DATA SCRAPER
   Scrapes daily CSV files from iemop.ph/market-data/
   and stores into SQLite database tables.
------------------------------------------------------- */

function bntm_weimop_md_datasets() {
    return [
        'mcp' => [
            'label'    => 'RTD Market Clearing Price',
            'post_id'  => 5787,
            'folder'   => 'MP',
            'prefix'   => 'MP_',
            'table'    => 'iemop_rtd_mcp',
            'importer' => 'bntm_weimop_md_import_mcp',
            'color'    => '#2563eb',
        ],
        'regional' => [
            'label'    => 'RTD Regional Summaries',
            'post_id'  => 5760,
            'folder'   => 'RTDREG',
            'prefix'   => 'RTDREG_',
            'table'    => 'iemop_rtd_regional',
            'importer' => 'bntm_weimop_md_import_regional',
            'color'    => '#16a34a',
        ],
        'reserve_mcp' => [
            'label'    => 'RTD Reserve MCP',
            'post_id'  => 327794,
            'folder'   => 'MPRESERVE',
            'prefix'   => 'MP_RESERVE_',
            'table'    => 'iemop_rtd_reserve_mcp',
            'importer' => 'bntm_weimop_md_import_reserve_mcp',
            'color'    => '#9333ea',
        ],
        'congestion' => [
            'label'    => 'Congestions in RTD',
            'post_id'  => 5782,
            'folder'   => 'RTDCV',
            'prefix'   => 'RTDCV_',
            'table'    => 'iemop_rtd_congestion',
            'importer' => 'bntm_weimop_md_import_congestion',
            'color'    => '#dc2626',
        ],
        'reserve_sched' => [
            'label'    => 'RTD Reserve Schedules',
            'post_id'  => 261204,
            'folder'   => 'RTDRS',
            'prefix'   => 'RTDRS_',
            'table'    => 'iemop_rtd_reserve_sched',
            'importer' => 'bntm_weimop_md_import_reserve_sched',
            'color'    => '#ea580c',
        ],
    ];
}

/* Parse CSV text → array of assoc rows */
function bntm_weimop_md_parse_csv( $text ) {
    $text = ltrim( $text, "\xEF\xBB\xBF" );
    $lines = preg_split( '/\r\n|\r|\n/', trim( $text ) );
    if ( count( $lines ) < 2 ) return [];
    $headers = str_getcsv( array_shift( $lines ) );
    $headers = array_map( 'trim', $headers );
    $rows = [];
    foreach ( $lines as $line ) {
        if ( trim( $line ) === '' ) continue;
        $vals = str_getcsv( $line );
        if ( count( $vals ) !== count( $headers ) ) continue;
        $rows[] = array_combine( $headers, $vals );
    }
    return $rows;
}

/* Case-insensitive column lookup */
function bntm_weimop_md_col( $row, $candidates ) {
    $lower = [];
    foreach ( $row as $k => $v ) $lower[ strtolower( trim( $k ) ) ] = $v;
    foreach ( $candidates as $c ) {
        $key = strtolower( trim( $c ) );
        if ( isset( $lower[ $key ] ) ) return $lower[ $key ];
    }
    return null;
}

/* Local storage dir for downloaded CSV files, organised by dataset */
function bntm_weimop_md_local_dir() {
    $upload = wp_upload_dir();
    $dir = trailingslashit( $upload['basedir'] ) . 'weimop/market_data';
    if ( ! file_exists( $dir ) ) wp_mkdir_p( $dir );
    $ht = $dir . '/.htaccess';
    if ( ! file_exists( $ht ) ) file_put_contents( $ht, "Deny from all\n" );
    return $dir;
}

/* -------------------------------------------------------
   STEP 1: Call IEMOP's internal AJAX API to get the full
   file list for a dataset.  Returns:
     [ 'files' => [ ['filename'=>..., 'url'=>..., 'date'=>...], ... ], 'cached'=>bool ]
   Cached to {dataset}_filelist.json for 1 hour.

   How it works:
   - IEMOP uses a WP plugin (iemop-market-downloads) that serves
     file lists via admin-ajax.php action=display_filtered_market_data_files
   - Each file path is returned as a Base64-encoded server filesystem path
     e.g. /var/www/html/wp-content/uploads/downloads/data/MP/MP_20260515.csv
   - Direct download URL = https://www.iemop.ph + path-without-server-prefix
------------------------------------------------------- */
function bntm_weimop_md_get_file_list( $post_id, $folder, $dataset_key ) {
    $cache_file = bntm_weimop_md_local_dir() . "/{$dataset_key}_filelist.json";
    if ( file_exists( $cache_file ) && ( time() - filemtime( $cache_file ) ) < 3600 ) {
        $cached = json_decode( file_get_contents( $cache_file ), true );
        if ( is_array( $cached ) ) return [ 'files' => $cached, 'cached' => true ];
    }

    $response = wp_remote_post( 'https://www.iemop.ph/wp-admin/admin-ajax.php', [
        'timeout' => 20,
        'body'    => [
            'action'     => 'display_filtered_market_data_files',
            'sort'       => '',
            'datefilter' => '',
            'page'       => 1,
            'post_id'    => $post_id,
        ],
        'headers' => [ 'User-Agent' => 'Mozilla/5.0 (compatible; BNTM-Hub/1.0)' ],
    ] );

    if ( is_wp_error( $response ) ) {
        return [ 'files' => [], 'error' => $response->get_error_message() ];
    }
    $body = wp_remote_retrieve_body( $response );
    $json = json_decode( $body, true );
    if ( ! is_array( $json ) || empty( $json['source'] ) ) {
        return [ 'files' => [], 'error' => 'Empty or invalid AJAX response' ];
    }

    $files = [];
    foreach ( $json['source'] as $b64 ) {
        $server_path = base64_decode( $b64 );
        // Strip Linux server root to get the web-accessible path
        $web_path = preg_replace( '#^/var/www/html#', '', $server_path );
        $url      = 'https://www.iemop.ph' . $web_path;
        $info     = $json['data'][ $b64 ] ?? [];
        $files[]  = [
            'filename' => $info['filename'] ?? basename( $server_path ),
            'url'      => $url,
            'date'     => $info['date'] ?? '',
        ];
    }

    file_put_contents( $cache_file, wp_json_encode( $files ) );
    return [ 'files' => $files, 'cached' => false ];
}

/* -------------------------------------------------------
   Get ALL pages of files from IEMOP for a dataset.
   Returns flat array of ['filename','url','date'] entries.
------------------------------------------------------- */
function bntm_weimop_md_get_all_pages_files( $post_id, $folder, $dataset_key ) {
    $all  = [];
    $page = 1;
    while ( true ) {
        $response = wp_remote_post( 'https://www.iemop.ph/wp-admin/admin-ajax.php', [
            'timeout' => 20,
            'body'    => [
                'action'     => 'display_filtered_market_data_files',
                'sort'       => '',
                'datefilter' => '',
                'page'       => $page,
                'post_id'    => $post_id,
            ],
            'headers' => [ 'User-Agent' => 'Mozilla/5.0 (compatible; BNTM-Hub/1.0)' ],
        ] );
        if ( is_wp_error( $response ) ) break;
        $body = wp_remote_retrieve_body( $response );
        $json = json_decode( $body, true );
        if ( ! is_array( $json ) || empty( $json['source'] ) ) break;
        foreach ( $json['source'] as $b64 ) {
            $server_path = base64_decode( $b64 );
            $web_path    = preg_replace( '#^/var/www/html#', '', $server_path );
            $url         = 'https://www.iemop.ph' . $web_path;
            $info        = $json['data'][ $b64 ] ?? [];
            $all[]       = [
                'filename' => $info['filename'] ?? basename( $server_path ),
                'url'      => $url,
                'date'     => $info['date'] ?? '',
            ];
        }
        $page++;
        if ( $page > 100 ) break; // safety cap — 100 pages max
    }
    return $all;
}

/* -------------------------------------------------------
   STEP 2: Download a CSV file and save it to local disk.
   Organised as market_data/{dataset_key}/{filename}
   Returns [ 'path' => ..., 'cached' => bool ] or [ 'error' => ... ]
------------------------------------------------------- */
function bntm_weimop_md_download_csv( $url, $filename, $dataset_key ) {
    $dir = bntm_weimop_md_local_dir() . '/' . sanitize_file_name( $dataset_key );
    if ( ! file_exists( $dir ) ) wp_mkdir_p( $dir );
    $dest = $dir . '/' . sanitize_file_name( $filename );

    // Use cached file if it already exists and is non-empty
    if ( file_exists( $dest ) && filesize( $dest ) > 0 ) {
        return [ 'path' => $dest, 'cached' => true ];
    }

    $response = wp_remote_get( $url, [
        'timeout'    => 40,
        'user-agent' => 'Mozilla/5.0 (compatible; BNTM-Hub/1.0)',
        'redirection'=> 5,
    ] );
    if ( is_wp_error( $response ) ) return [ 'error' => $response->get_error_message() ];
    $code = wp_remote_retrieve_response_code( $response );
    if ( $code !== 200 ) return [ 'error' => "HTTP $code" ];
    $body = wp_remote_retrieve_body( $response );
    if ( empty( $body ) ) return [ 'error' => 'Empty response' ];

    file_put_contents( $dest, $body );
    return [ 'path' => $dest, 'cached' => false ];
}

/* ---- Per-dataset importers ---- */

function bntm_weimop_md_import_mcp( $db, $rows, $file_date ) {
    $count = 0;
    $stmt  = $db->prepare(
        "INSERT OR IGNORE INTO iemop_rtd_mcp
             (file_date, time_interval, resource_name, region, commodity_type, marginal_price, raw_json)
         VALUES (:fd, :ti, :rn, :rg, :ct, :mp, :rj)"
    );
    foreach ( $rows as $r ) {
        $ti  = bntm_weimop_md_col( $r, ['TIME_INTERVAL', 'INTERVAL'] );
        $rn  = bntm_weimop_md_col( $r, ['RESOURCE_NAME', 'RESOURCE', 'PRICE_NODE', 'NODE'] );
        $rg  = bntm_weimop_md_col( $r, ['REGION_NAME', 'REGION'] );
        $ct  = bntm_weimop_md_col( $r, ['COMMODITY_TYPE', 'COMMODITY'] );
        $mp  = (float) bntm_weimop_md_col( $r, ['MARGINAL_PRICE', 'LMP', 'MCP', 'PRICE'] );
        $stmt->bindValue( ':fd',  $file_date,           SQLITE3_TEXT );
        $stmt->bindValue( ':ti',  (string) $ti,         SQLITE3_TEXT );
        $stmt->bindValue( ':rn',  (string) $rn,         SQLITE3_TEXT );
        $stmt->bindValue( ':rg',  (string) $rg,         SQLITE3_TEXT );
        $stmt->bindValue( ':ct',  (string) $ct,         SQLITE3_TEXT );
        $stmt->bindValue( ':mp',  $mp,                  SQLITE3_FLOAT );
        $stmt->bindValue( ':rj',  wp_json_encode( $r ), SQLITE3_TEXT );
        $stmt->execute();
        $count += $db->changes();
    }
    return $count;
}

function bntm_weimop_md_import_regional( $db, $rows, $file_date ) {
    $count = 0;
    $stmt  = $db->prepare(
        "INSERT OR IGNORE INTO iemop_rtd_regional
             (file_date, time_interval, region, commodity_type, generation, mkt_reqt, mkt_import, mkt_export, raw_json)
         VALUES (:fd, :ti, :rg, :ct, :ge, :mr, :mi, :me, :rj)"
    );
    foreach ( $rows as $r ) {
        $ti  = bntm_weimop_md_col( $r, ['TIME_INTERVAL', 'INTERVAL'] );
        $rg  = bntm_weimop_md_col( $r, ['REGION_NAME', 'REGION'] );
        $ct  = bntm_weimop_md_col( $r, ['COMMODITY_TYPE', 'COMMODITY'] );
        $ge  = (float) bntm_weimop_md_col( $r, ['GENERATION', 'GEN', 'DISPATCH'] );
        $mr  = (float) bntm_weimop_md_col( $r, ['MKT_REQT', 'REQUIREMENT'] );
        $mi  = (float) bntm_weimop_md_col( $r, ['MKT_IMPORT', 'IMPORT'] );
        $me  = (float) bntm_weimop_md_col( $r, ['MKT_EXPORT', 'EXPORT'] );
        $stmt->bindValue( ':fd',  $file_date,           SQLITE3_TEXT );
        $stmt->bindValue( ':ti',  (string) $ti,         SQLITE3_TEXT );
        $stmt->bindValue( ':rg',  (string) $rg,         SQLITE3_TEXT );
        $stmt->bindValue( ':ct',  (string) $ct,         SQLITE3_TEXT );
        $stmt->bindValue( ':ge',  $ge,                  SQLITE3_FLOAT );
        $stmt->bindValue( ':mr',  $mr,                  SQLITE3_FLOAT );
        $stmt->bindValue( ':mi',  $mi,                  SQLITE3_FLOAT );
        $stmt->bindValue( ':me',  $me,                  SQLITE3_FLOAT );
        $stmt->bindValue( ':rj',  wp_json_encode( $r ), SQLITE3_TEXT );
        $stmt->execute();
        $count += $db->changes();
    }
    return $count;
}

function bntm_weimop_md_import_reserve_mcp( $db, $rows, $file_date ) {
    $count = 0;
    $stmt  = $db->prepare(
        "INSERT OR IGNORE INTO iemop_rtd_reserve_mcp
             (file_date, time_interval, resource_name, region, commodity_type, marginal_price, raw_json)
         VALUES (:fd, :ti, :rn, :rg, :ct, :mp, :rj)"
    );
    foreach ( $rows as $r ) {
        $ti  = bntm_weimop_md_col( $r, ['TIME_INTERVAL', 'INTERVAL'] );
        $rn  = bntm_weimop_md_col( $r, ['RESOURCE_NAME', 'RESOURCE'] );
        $rg  = bntm_weimop_md_col( $r, ['REGION_NAME', 'REGION'] );
        $ct  = bntm_weimop_md_col( $r, ['COMMODITY_TYPE', 'COMMODITY'] );
        $mp  = (float) bntm_weimop_md_col( $r, ['MARGINAL_PRICE', 'PRICE', 'MCP', 'LMP'] );
        $stmt->bindValue( ':fd',  $file_date,           SQLITE3_TEXT );
        $stmt->bindValue( ':ti',  (string) $ti,         SQLITE3_TEXT );
        $stmt->bindValue( ':rn',  (string) $rn,         SQLITE3_TEXT );
        $stmt->bindValue( ':rg',  (string) $rg,         SQLITE3_TEXT );
        $stmt->bindValue( ':ct',  (string) $ct,         SQLITE3_TEXT );
        $stmt->bindValue( ':mp',  $mp,                  SQLITE3_FLOAT );
        $stmt->bindValue( ':rj',  wp_json_encode( $r ), SQLITE3_TEXT );
        $stmt->execute();
        $count += $db->changes();
    }
    return $count;
}

function bntm_weimop_md_import_congestion( $db, $rows, $file_date ) {
    $count = 0;
    $stmt  = $db->prepare(
        "INSERT OR IGNORE INTO iemop_rtd_congestion
             (file_date, time_interval, equipment_name, station_name, congest_type, binding_limit, mw_flow, overload_mw, pct_mw, raw_json)
         VALUES (:fd, :ti, :en, :sn, :cg, :bl, :mf, :ow, :pm, :rj)"
    );
    foreach ( $rows as $r ) {
        $ti  = bntm_weimop_md_col( $r, ['TIME_INTERVAL', 'INTERVAL'] );
        $en  = bntm_weimop_md_col( $r, ['EQUIPMENT_NAME', 'EQUIPMENT', 'CONSTRAINT_NAME', 'NAME'] );
        $sn  = bntm_weimop_md_col( $r, ['STATION_NAME', 'STATION'] );
        $cg  = bntm_weimop_md_col( $r, ['CONGEST_TYPE', 'CONGESTION_TYPE', 'TYPE'] );
        $bl  = (float) bntm_weimop_md_col( $r, ['BINDING_LIMIT', 'LIMIT'] );
        $mf  = (float) bntm_weimop_md_col( $r, ['MW_FLOW', 'FLOW', 'SHADOW_PRICE'] );
        $ow  = (float) bntm_weimop_md_col( $r, ['OVERLOAD_MW', 'OVERLOAD'] );
        $pm  = (float) bntm_weimop_md_col( $r, ['PCT_MW', 'PCT', 'PERCENT'] );
        $stmt->bindValue( ':fd',  $file_date,           SQLITE3_TEXT );
        $stmt->bindValue( ':ti',  (string) $ti,         SQLITE3_TEXT );
        $stmt->bindValue( ':en',  (string) $en,         SQLITE3_TEXT );
        $stmt->bindValue( ':sn',  (string) $sn,         SQLITE3_TEXT );
        $stmt->bindValue( ':cg',  (string) $cg,         SQLITE3_TEXT );
        $stmt->bindValue( ':bl',  $bl,                  SQLITE3_FLOAT );
        $stmt->bindValue( ':mf',  $mf,                  SQLITE3_FLOAT );
        $stmt->bindValue( ':ow',  $ow,                  SQLITE3_FLOAT );
        $stmt->bindValue( ':pm',  $pm,                  SQLITE3_FLOAT );
        $stmt->bindValue( ':rj',  wp_json_encode( $r ), SQLITE3_TEXT );
        $stmt->execute();
        $count += $db->changes();
    }
    return $count;
}

function bntm_weimop_md_import_reserve_sched( $db, $rows, $file_date ) {
    $count = 0;
    $stmt  = $db->prepare(
        "INSERT OR IGNORE INTO iemop_rtd_reserve_sched
             (file_date, time_interval, resource_name, region, commodity_type, sched_mw, price, raw_json)
         VALUES (:fd, :ti, :rn, :rg, :ct, :sm, :pr, :rj)"
    );
    foreach ( $rows as $r ) {
        $ti  = bntm_weimop_md_col( $r, ['TIME_INTERVAL', 'INTERVAL'] );
        $rn  = bntm_weimop_md_col( $r, ['RESOURCE_NAME', 'RESOURCE', 'UNIT', 'GENERATOR'] );
        $rg  = bntm_weimop_md_col( $r, ['REGION_NAME', 'REGION'] );
        $ct  = bntm_weimop_md_col( $r, ['COMMODITY_TYPE', 'COMMODITY'] );
        $sm  = (float) bntm_weimop_md_col( $r, ['SCHED_MW', 'SCHEDULE', 'DISPATCH', 'MW'] );
        $pr  = (float) bntm_weimop_md_col( $r, ['PRICE', 'MARGINAL_PRICE', 'MCP'] );
        $stmt->bindValue( ':fd',  $file_date,           SQLITE3_TEXT );
        $stmt->bindValue( ':ti',  (string) $ti,         SQLITE3_TEXT );
        $stmt->bindValue( ':rn',  (string) $rn,         SQLITE3_TEXT );
        $stmt->bindValue( ':rg',  (string) $rg,         SQLITE3_TEXT );
        $stmt->bindValue( ':ct',  (string) $ct,         SQLITE3_TEXT );
        $stmt->bindValue( ':sm',  $sm,                  SQLITE3_FLOAT );
        $stmt->bindValue( ':pr',  $pr,                  SQLITE3_FLOAT );
        $stmt->bindValue( ':rj',  wp_json_encode( $r ), SQLITE3_TEXT );
        $stmt->execute();
        $count += $db->changes();
    }
    return $count;
}

/* -------------------------------------------------------
   MAIN ORCHESTRATOR

   For each dataset:
   1. Call IEMOP AJAX → get full file list with real URLs
      (cached to {dataset}_filelist.json for 1 hour)
   2. Download each CSV → save to
      wp-content/uploads/weimop/market_data/{dataset}/{filename}
      (skip if file already exists on disk)
   3. Parse saved CSV file → import rows to SQLite
      (skip files already logged in iemop_market_log)
------------------------------------------------------- */
function bntm_weimop_md_fetch_all( $days = 7 ) {
    $db = bntm_weimop_open_db( false );
    if ( ! $db ) return [ 'error' => 'Cannot open SQLite database.' ];

    $results  = [];
    $datasets = bntm_weimop_md_datasets();

    // ---- One-time schema migration ----------------------------------------
    // Detect old schema (missing resource_name column = wrong column mappings).
    // Drop all market tables + clear import log so they get re-imported fresh.
    $old_cols = [];
    $ci = $db->query( "PRAGMA table_info(iemop_rtd_mcp)" );
    while ( $c = $ci->fetchArray( SQLITE3_ASSOC ) ) $old_cols[] = $c['name'];
    if ( ! empty( $old_cols ) && ! in_array( 'resource_name', $old_cols, true ) ) {
        foreach ( ['iemop_rtd_mcp','iemop_rtd_regional','iemop_rtd_reserve_mcp','iemop_rtd_congestion','iemop_rtd_reserve_sched'] as $t ) {
            $db->exec( "DROP TABLE IF EXISTS $t" );
        }
        $db->exec( "DELETE FROM iemop_market_log WHERE dataset IN ('mcp','regional','reserve_mcp','congestion','reserve_sched')" );
        foreach ( bntm_weimop_sqlite_schema() as $schema_sql ) {
            $db->exec( $schema_sql );
        }
        $results[] = [ 'status' => 'info', 'file' => 'migration',
            'msg' => 'Schema migrated: tables recreated with correct column names, import log cleared for re-import.' ];
    }

    // Ensure reserve_sched table exists (may be absent on older installs)
    $db->exec(
        "CREATE TABLE IF NOT EXISTS iemop_rtd_reserve_sched (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            file_date      TEXT NOT NULL,
            time_interval  TEXT,
            resource_name  TEXT,
            region         TEXT,
            commodity_type TEXT,
            sched_mw       REAL,
            price          REAL,
            raw_json       TEXT,
            UNIQUE(file_date, time_interval, resource_name, commodity_type)
        )"
    );

    foreach ( $datasets as $key => $ds ) {

        // --- STEP 1: Get file list via IEMOP AJAX ---
        $list = bntm_weimop_md_get_file_list( $ds['post_id'], $ds['folder'], $key );
        if ( ! empty( $list['error'] ) ) {
            $results[] = [ 'status' => 'error', 'file' => $key, 'msg' => 'File list error: ' . $list['error'] ];
            continue;
        }
        $results[] = [ 'status' => 'info', 'file' => $key,
            'msg' => count( $list['files'] ) . ' files available on IEMOP' .
                ( $list['cached'] ? ' (cached)' : ' (fresh from IEMOP API)' ) ];

        // Build filename → URL map
        $url_map = [];
        foreach ( $list['files'] as $f ) {
            $url_map[ strtolower( $f['filename'] ) ] = $f['url'];
        }

        // --- STEP 2 + 3: For each day's file → download → import ---
        for ( $d = 0; $d < $days; $d++ ) {
            $date     = date( 'Ymd', strtotime( "-{$d} days" ) );
            $filename = $ds['prefix'] . $date . '.csv';

            // Skip if already in DB
            $chk = $db->prepare( "SELECT id FROM iemop_market_log WHERE dataset = :ds AND filename = :fn" );
            $chk->bindValue( ':ds', $key,      SQLITE3_TEXT );
            $chk->bindValue( ':fn', $filename, SQLITE3_TEXT );
            if ( $chk->execute()->fetchArray( SQLITE3_ASSOC ) ) {
                $results[] = [ 'status' => 'skip', 'file' => $filename, 'msg' => 'Already imported' ];
                continue;
            }

            // Find URL from AJAX file list
            $csv_url = $url_map[ strtolower( $filename ) ] ?? null;
            if ( ! $csv_url ) {
                $results[] = [ 'status' => 'not_found', 'file' => $filename,
                    'msg' => 'Not in IEMOP file list (may not be published yet)' ];
                continue;
            }

            // STEP 2: Download CSV → save to disk
            $dl = bntm_weimop_md_download_csv( $csv_url, $filename, $key );
            if ( isset( $dl['error'] ) ) {
                $results[] = [ 'status' => 'error', 'file' => $filename,
                    'msg' => $dl['error'] . " | URL: $csv_url" ];
                continue;
            }

            // STEP 3: Parse saved file → import to DB
            $body  = file_get_contents( $dl['path'] );
            $rows  = bntm_weimop_md_parse_csv( $body );
            $count = 0;
            if ( ! empty( $rows ) && function_exists( $ds['importer'] ) ) {
                $count = call_user_func( $ds['importer'], $db, $rows, $date );
            }

            // Log import
            $ins = $db->prepare(
                "INSERT OR IGNORE INTO iemop_market_log (dataset, filename, file_date, rows_imported)
                 VALUES (:ds, :fn, :fd, :ri)"
            );
            $ins->bindValue( ':ds', $key,      SQLITE3_TEXT );
            $ins->bindValue( ':fn', $filename, SQLITE3_TEXT );
            $ins->bindValue( ':fd', $date,     SQLITE3_TEXT );
            $ins->bindValue( ':ri', $count,    SQLITE3_INTEGER );
            $ins->execute();

            $results[] = [ 'status' => 'ok', 'file' => $filename,
                'msg' => "$count rows imported | saved: wp-content/uploads/weimop/market_data/{$key}/{$filename}" .
                    ( $dl['cached'] ? ' (file was already on disk)' : '' ) ];
        }
    }

    $db->close();
    return [ 'results' => $results ];
}

/* -------------------------------------------------------
   Import ALL available files from local market_data/ folder.
   No HTTP requests — reads only what is already on disk,
   scrapes the CSV data, and inserts into weimop.sqlite.
------------------------------------------------------- */
function bntm_weimop_md_import_all() {
    $db = bntm_weimop_open_db( false );
    if ( ! $db ) return [ 'error' => 'Cannot open SQLite database.' ];

    $results  = [];
    $datasets = bntm_weimop_md_datasets();

    // Schema migration: drop old tables if column names are stale
    $old_cols = [];
    $ci = $db->query( "PRAGMA table_info(iemop_rtd_mcp)" );
    while ( $c = $ci->fetchArray( SQLITE3_ASSOC ) ) $old_cols[] = $c['name'];
    if ( ! empty( $old_cols ) && ! in_array( 'resource_name', $old_cols, true ) ) {
        foreach ( ['iemop_rtd_mcp','iemop_rtd_regional','iemop_rtd_reserve_mcp','iemop_rtd_congestion','iemop_rtd_reserve_sched'] as $t ) {
            $db->exec( "DROP TABLE IF EXISTS $t" );
        }
        $db->exec( "DELETE FROM iemop_market_log WHERE dataset IN ('mcp','regional','reserve_mcp','congestion','reserve_sched')" );
        foreach ( bntm_weimop_sqlite_schema() as $schema_sql ) $db->exec( $schema_sql );
        $results[] = [ 'status' => 'info', 'file' => 'migration',
            'msg' => 'Schema migrated — tables recreated with correct column names, log cleared.' ];
    }

    foreach ( $datasets as $key => $ds ) {
        $local_dir = bntm_weimop_md_local_dir() . "/{$key}";
        $csv_files = is_dir( $local_dir ) ? ( glob( $local_dir . '/*.csv' ) ?: [] ) : [];
        sort( $csv_files ); // chronological order by filename

        if ( empty( $csv_files ) ) {
            $results[] = [ 'status' => 'info', 'file' => $key,
                'msg' => 'No CSV files found in market_data/' . $key . '/' ];
            continue;
        }

        $imported = 0;
        $skipped  = 0;

        foreach ( $csv_files as $local_path ) {
            $filename = basename( $local_path );

            // Extract YYYYMMDD from filename e.g. MP_20260515.csv → 20260515
            if ( ! preg_match( '/' . preg_quote( $ds['prefix'], '/' ) . '(\d{8})\.csv$/i', $filename, $m ) ) continue;
            $date = $m[1];

            // Skip if already in import log
            $chk = $db->prepare( "SELECT id FROM iemop_market_log WHERE dataset = :ds AND filename = :fn" );
            $chk->bindValue( ':ds', $key,      SQLITE3_TEXT );
            $chk->bindValue( ':fn', $filename, SQLITE3_TEXT );
            if ( $chk->execute()->fetchArray( SQLITE3_ASSOC ) ) {
                $skipped++;
                continue;
            }

            // Read local file → parse CSV → import rows
            $body  = file_get_contents( $local_path );
            $rows  = bntm_weimop_md_parse_csv( $body );
            $count = 0;
            if ( ! empty( $rows ) && function_exists( $ds['importer'] ) ) {
                $count = call_user_func( $ds['importer'], $db, $rows, $date );
            }

            // Log the import
            $ins = $db->prepare(
                "INSERT OR IGNORE INTO iemop_market_log (dataset, filename, file_date, rows_imported)
                 VALUES (:ds, :fn, :fd, :ri)"
            );
            $ins->bindValue( ':ds', $key,      SQLITE3_TEXT );
            $ins->bindValue( ':fn', $filename, SQLITE3_TEXT );
            $ins->bindValue( ':fd', $date,     SQLITE3_TEXT );
            $ins->bindValue( ':ri', $count,    SQLITE3_INTEGER );
            $ins->execute();

            $results[] = [ 'status' => 'ok', 'file' => $filename,
                'msg' => "{$count} rows imported into {$ds['table']}" ];
            $imported++;
        }

        $results[] = [ 'status' => 'info', 'file' => $key,
            'msg' => count( $csv_files ) . " files scanned — {$imported} imported, {$skipped} already in DB" ];
    }

    $db->close();
    return [ 'results' => $results ];
}

/* WP Cron callback */
function bntm_weimop_md_cron_run() {
    bntm_weimop_md_fetch_all( 3 );
}

/* ---- AJAX: Fetch all market data ---- */
function bntm_ajax_weimop_fetch_market_data() {
    // Remove time limit — this job makes many outbound HTTP requests
    @set_time_limit( 0 );
    // Start output buffering so stray PHP notices/warnings don't corrupt JSON
    ob_start();

    check_ajax_referer( 'weimop_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) {
        ob_end_clean();
        wp_send_json_error( [ 'message' => 'Unauthorized' ] );
    }

    $days   = max( 1, min( 30, (int) ( $_POST['days'] ?? 7 ) ) );
    $result = bntm_weimop_md_fetch_all( $days );

    $noise = ob_get_clean(); // discard any stray output
    if ( ! empty( $noise ) ) {
        // Attach as debug info so we can see it in the log
        $result['php_notices'] = trim( strip_tags( $noise ) );
    }
    wp_send_json_success( $result );
}

/* ---- AJAX: Import ALL files from IEMOP ---- */
function bntm_ajax_weimop_import_all_files() {
    @set_time_limit( 0 );
    ob_start();
    check_ajax_referer( 'weimop_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) {
        ob_end_clean();
        wp_send_json_error( [ 'message' => 'Unauthorized' ] );
    }
    $result = bntm_weimop_md_import_all();
    $noise  = ob_get_clean();
    if ( ! empty( $noise ) ) $result['php_notices'] = trim( strip_tags( $noise ) );
    wp_send_json_success( $result );
}

/* ---- AJAX: Get chart data for a dataset + date ---- */
/* -------------------------------------------------------
   N2. TRADING GRAPH SERIES AJAX
   Returns time-series data for the 5 RTD datasets for
   LightweightCharts, grouped by key (resource / region /
   equipment), limited to top 10 series by row count.
------------------------------------------------------- */

function bntm_ajax_weimop_get_trading_series() {
    check_ajax_referer( 'weimop_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( [ 'message' => 'Unauthorized' ] );

    $dataset   = sanitize_text_field( $_POST['dataset']   ?? '' );
    $date_from = sanitize_text_field( $_POST['date_from'] ?? '' );
    $date_to   = sanitize_text_field( $_POST['date_to']   ?? '' );
    $region    = sanitize_text_field( $_POST['region']    ?? '' );
    $resource  = sanitize_text_field( $_POST['resource']  ?? '' );
    $datasets  = bntm_weimop_md_datasets();

    if ( ! isset( $datasets[ $dataset ] ) ||
         ! preg_match( '/^\d{8}$/', $date_from ) ||
         ! preg_match( '/^\d{8}$/', $date_to   ) ) {
        wp_send_json_error( [ 'message' => 'Invalid parameters' ] );
        return;
    }

    $db = bntm_weimop_open_db( true );
    if ( ! $db ) { wp_send_json_error( [ 'message' => 'Cannot open database' ] ); return; }

    $table = $datasets[ $dataset ]['table'];
    // Region filter
    $region_datasets = [ 'mcp', 'regional', 'reserve_mcp', 'reserve_sched' ];
    $use_region = $region && in_array( $dataset, $region_datasets, true );
    // Resource filter — column differs per dataset
    $resource_col_map = [ 'mcp' => 'resource_name', 'reserve_mcp' => 'resource_name', 'congestion' => 'equipment_name', 'reserve_sched' => 'resource_name' ];
    $use_resource = $resource && isset( $resource_col_map[ $dataset ] );
    $sql = "SELECT * FROM {$table} WHERE file_date >= :fd_from AND file_date <= :fd_to";
    if ( $use_region )   $sql .= " AND region = :region";
    if ( $use_resource ) $sql .= " AND {$resource_col_map[$dataset]} = :resource";
    $sql .= " ORDER BY time_interval ASC LIMIT 10000";
    $stmt = $db->prepare( $sql );
    $stmt->bindValue( ':fd_from', $date_from, SQLITE3_TEXT );
    $stmt->bindValue( ':fd_to',   $date_to,   SQLITE3_TEXT );
    if ( $use_region )   $stmt->bindValue( ':region',   $region,   SQLITE3_TEXT );
    if ( $use_resource ) $stmt->bindValue( ':resource', $resource, SQLITE3_TEXT );
    $res  = $stmt->execute();
    $rows = [];
    while ( $row = $res->fetchArray( SQLITE3_ASSOC ) ) $rows[] = $row;
    $db->close();

    // Build series grouped by series key
    $series = [];
    foreach ( $rows as $r ) {
        $ti = (string) ( $r['time_interval'] ?? '' );
        $ts = $ti ? strtotime( $ti ) : 0;
        if ( ! $ts ) continue;

        switch ( $dataset ) {
            case 'mcp':
                $val = (float) ( $r['marginal_price'] ?? 0 );
                $sk  = $r['resource_name'] ?? 'MCP';
                break;
            case 'regional':
                $val = (float) ( $r['generation'] ?? 0 );
                $sk  = trim( ( $r['region'] ?? '' ) . ( ! empty( $r['commodity_type'] ) ? '/' . $r['commodity_type'] : '' ) );
                break;
            case 'reserve_mcp':
                $val = (float) ( $r['marginal_price'] ?? 0 );
                $sk  = trim( ( $r['resource_name'] ?? '' ) . ( ! empty( $r['commodity_type'] ) ? '/' . $r['commodity_type'] : '' ) );
                break;
            case 'congestion':
                $val = (float) ( $r['mw_flow'] ?? 0 );
                $sk  = $r['equipment_name'] ?? 'Equipment';
                break;
            case 'reserve_sched':
                $val = (float) ( ! empty( $r['price'] ) ? $r['price'] : ( $r['sched_mw'] ?? 0 ) );
                $sk  = trim( ( $r['resource_name'] ?? '' ) . ( ! empty( $r['commodity_type'] ) ? '/' . $r['commodity_type'] : '' ) );
                break;
            default:
                $val = 0;
                $sk  = 'Value';
        }

        $series[ $sk ][] = [ 'time' => $ts, 'value' => $val ];
    }

    // Sort by count desc, keep top 10
    uasort( $series, function( $a, $b ) { return count( $b ) - count( $a ); } );
    $series = array_slice( $series, 0, 10, true );

    wp_send_json_success( [
        'dataset'   => $dataset,
        'date_from' => $date_from,
        'date_to'   => $date_to,
        'series'    => $series,
        'count'     => count( $rows ),
    ] );
}

function bntm_ajax_weimop_get_md_chart() {
    check_ajax_referer( 'weimop_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( [ 'message' => 'Unauthorized' ] );

    $dataset   = sanitize_text_field( $_POST['dataset'] ?? '' );
    $file_date = sanitize_text_field( $_POST['file_date'] ?? '' );
    $region    = sanitize_text_field( $_POST['region']    ?? '' );
    $resource  = sanitize_text_field( $_POST['resource']  ?? '' );
    $datasets  = bntm_weimop_md_datasets();

    if ( ! isset( $datasets[ $dataset ] ) || ! preg_match( '/^\d{8}$/', $file_date ) ) {
        wp_send_json_error( [ 'message' => 'Invalid params' ] );
    }

    $db = bntm_weimop_open_db( true );
    if ( ! $db ) wp_send_json_error( [ 'message' => 'Cannot open database' ] );

    $table   = $datasets[ $dataset ]['table'];
    $region_datasets  = [ 'mcp', 'regional', 'reserve_mcp', 'reserve_sched' ];
    $resource_col_map = [ 'mcp' => 'resource_name', 'reserve_mcp' => 'resource_name', 'congestion' => 'equipment_name', 'reserve_sched' => 'resource_name' ];
    $use_region   = $region   && in_array( $dataset, $region_datasets, true );
    $use_resource = $resource && isset( $resource_col_map[ $dataset ] );
    $sql = "SELECT * FROM {$table} WHERE file_date = :fd";
    if ( $use_region )   $sql .= " AND region = :region";
    if ( $use_resource ) $sql .= " AND {$resource_col_map[$dataset]} = :resource";
    $sql .= " ORDER BY id ASC";
    $stmt    = $db->prepare( $sql );
    $stmt->bindValue( ':fd', $file_date, SQLITE3_TEXT );
    if ( $use_region )   $stmt->bindValue( ':region',   $region,   SQLITE3_TEXT );
    if ( $use_resource ) $stmt->bindValue( ':resource', $resource, SQLITE3_TEXT );
    $res     = $stmt->execute();
    $rows    = [];
    while ( $row = $res->fetchArray( SQLITE3_ASSOC ) ) $rows[] = $row;
    $db->close();

    // Build chart labels, value series, and table rows
    $labels  = [];
    $values  = [];
    $series  = [];
    $tbl     = [];

    foreach ( $rows as $r ) {
        $ti = $r['time_interval'] ?? '';
        // Determine value column per dataset
        $val = 0;
        if ( $dataset === 'mcp' )              { $val = (float)($r['marginal_price'] ?? 0); $series_key = $r['resource_name'] ?? 'MCP'; }
        elseif ( $dataset === 'regional' )      { $val = (float)($r['generation'] ?? 0);    $series_key = ($r['region'] ?? '') . '/' . ($r['commodity_type'] ?? ''); }
        elseif ( $dataset === 'reserve_mcp' )   { $val = (float)($r['marginal_price'] ?? 0); $series_key = ($r['resource_name'] ?? '') . '/' . ($r['commodity_type'] ?? ''); }
        elseif ( $dataset === 'congestion' )    { $val = (float)($r['mw_flow'] ?? 0);       $series_key = $r['equipment_name'] ?? 'Equipment'; }
        elseif ( $dataset === 'reserve_sched' ) { $val = (float)($r['sched_mw'] ?? 0);      $series_key = ($r['resource_name'] ?? '') . '/' . ($r['commodity_type'] ?? ''); }
        else { $val = 0; $series_key = 'Value'; }

        if ( ! isset( $series[ $series_key ] ) ) $series[ $series_key ] = [];
        $series[ $series_key ][] = [ 'time' => $ti, 'value' => $val ];
        $tbl[] = $r;
    }

    wp_send_json_success( [
        'dataset'  => $dataset,
        'date'     => $file_date,
        'series'   => $series,
        'rows'     => array_slice( $tbl, 0, 200 ),
        'count'    => count( $rows ),
    ] );
}

function bntm_ajax_weimop_get_md_regions() {
    check_ajax_referer( 'weimop_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( [ 'message' => 'Unauthorized' ] );
    $dataset  = sanitize_text_field( $_POST['dataset'] ?? '' );
    $datasets = bntm_weimop_md_datasets();
    if ( ! isset( $datasets[ $dataset ] ) ) wp_send_json_error( [ 'message' => 'Invalid dataset' ] );
    $region_datasets = [ 'mcp', 'regional', 'reserve_mcp', 'reserve_sched' ];
    if ( ! in_array( $dataset, $region_datasets, true ) ) { wp_send_json_success( [ 'regions' => [] ] ); return; }
    $db = bntm_weimop_open_db( true );
    if ( ! $db ) wp_send_json_error( [ 'message' => 'Cannot open database' ] );
    $table   = $datasets[ $dataset ]['table'];
    $regions = [];
    try {
        $res = $db->query( "SELECT DISTINCT region FROM {$table} WHERE region IS NOT NULL AND region != '' ORDER BY region ASC" );
        while ( $row = $res->fetchArray( SQLITE3_NUM ) ) {
            if ( ! empty( $row[0] ) ) $regions[] = $row[0];
        }
    } catch ( Exception $e ) {}
    $db->close();
    wp_send_json_success( [ 'regions' => $regions ] );
}

function bntm_ajax_weimop_get_md_resources() {
    check_ajax_referer( 'weimop_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( [ 'message' => 'Unauthorized' ] );
    $dataset  = sanitize_text_field( $_POST['dataset'] ?? '' );
    $datasets = bntm_weimop_md_datasets();
    if ( ! isset( $datasets[ $dataset ] ) ) wp_send_json_error( [ 'message' => 'Invalid dataset' ] );
    $resource_col_map = [ 'mcp' => 'resource_name', 'reserve_mcp' => 'resource_name', 'congestion' => 'equipment_name', 'reserve_sched' => 'resource_name' ];
    if ( ! isset( $resource_col_map[ $dataset ] ) ) { wp_send_json_success( [ 'resources' => [] ] ); return; }
    $db = bntm_weimop_open_db( true );
    if ( ! $db ) wp_send_json_error( [ 'message' => 'Cannot open database' ] );
    $table   = $datasets[ $dataset ]['table'];
    $col     = $resource_col_map[ $dataset ];
    $resources = [];
    try {
        $res = $db->query( "SELECT DISTINCT {$col} FROM {$table} WHERE {$col} IS NOT NULL AND {$col} != '' ORDER BY {$col} ASC" );
        while ( $row = $res->fetchArray( SQLITE3_NUM ) ) {
            if ( ! empty( $row[0] ) ) $resources[] = $row[0];
        }
    } catch ( Exception $e ) {}
    $db->close();
    wp_send_json_success( [ 'resources' => $resources ] );
}

/* ---- Market Data Tab UI ---- */
function weimop_tab_market_data() {
    ob_start();
    $db       = bntm_weimop_open_db( true );
    $datasets = bntm_weimop_md_datasets();
    $log_rows = [];
    $stats    = [];

    if ( $db ) {
        $log_rows = bntm_weimop_fetch_rows( $db,
            "SELECT dataset, filename, file_date, rows_imported, imported_at
             FROM iemop_market_log ORDER BY file_date DESC, imported_at DESC LIMIT 500"
        );
        foreach ( $datasets as $key => $ds ) {
            $r = bntm_weimop_fetch_rows( $db,
                "SELECT COUNT(*) AS total_rows, MAX(file_date) AS latest_date FROM {$ds['table']}"
            );
            $stats[ $key ] = $r[0] ?? [ 'total_rows' => 0, 'latest_date' => '-' ];
        }
        $db->close();
    }

    // Available dates for selector — per dataset, newest first
    $dates_by_dataset = [];
    foreach ( $log_rows as $lr ) {
        $key = $lr['dataset'];
        if ( ! isset( $dates_by_dataset[ $key ] ) ) $dates_by_dataset[ $key ] = [];
        $fd = $lr['file_date'];
        if ( ! in_array( $fd, $dates_by_dataset[ $key ], true ) ) {
            $dates_by_dataset[ $key ][] = $fd;
        }
    }
    // Sort each dataset's dates descending (latest first)
    foreach ( $dates_by_dataset as $key => $dates ) {
        rsort( $dates_by_dataset[ $key ] );
    }
    ?>
    <div class="weimop-md-wrap">

        <!-- Header & Fetch Controls -->
        <div class="weimop-md-header">
            <h3 class="weimop-md-title">IEMOP Public Market Data</h3>
            <div class="weimop-md-controls">
                <label class="weimop-md-label">Days to fetch:
                    <select id="weimop-md-days" class="weimop-md-select">
                        <option value="3">Last 3 days</option>
                        <option value="7" selected>Last 7 days</option>
                        <option value="14">Last 14 days</option>
                        <option value="30">Last 30 days</option>
                    </select>
                </label>
                <button id="weimop-md-fetch-btn" class="weimop-btn weimop-btn--primary">Fetch from IEMOP</button>
                <button id="weimop-md-import-all-btn" class="weimop-btn weimop-btn--secondary" title="Read all CSV files from the local market_data folder and import into weimop.sqlite">Import Local Files</button>
                <span id="weimop-md-fetch-status" class="weimop-notice" style="display:none;"></span>
            </div>
        </div>

        <!-- Fetch Log (live output) -->
        <div id="weimop-md-log-wrap" style="display:none;" class="weimop-md-log-wrap">
            <strong>Fetch Log</strong>
            <div id="weimop-md-log" class="weimop-md-log"></div>
        </div>

        <!-- Dataset Summary Cards -->
        <div class="weimop-md-cards">
            <?php foreach ( $datasets as $key => $ds ) :
                $stat = $stats[ $key ] ?? [ 'total_rows' => 0, 'latest_date' => '-' ];
                $latest = $stat['latest_date'] !== '-'
                    ? date( 'M d, Y', strtotime( (string) $stat['latest_date'] ) )
                    : 'No data yet';
                $first_date = isset( $dates_by_dataset[ $key ][0] ) ? $dates_by_dataset[ $key ][0] : '';
            ?>
            <div class="weimop-md-card" style="border-top: 3px solid <?php echo esc_attr( $ds['color'] ); ?>">
                <div class="weimop-md-card-title"><?php echo esc_html( $ds['label'] ); ?></div>
                <div class="weimop-md-card-stat"><?php echo number_format( (int)$stat['total_rows'] ); ?> rows</div>
                <div class="weimop-md-card-sub">Latest: <?php echo esc_html( $latest ); ?></div>
                <?php if ( $first_date ) : ?>
                <div class="weimop-md-card-actions">
                    <select class="weimop-md-date-sel weimop-md-select" data-dataset="<?php echo esc_attr( $key ); ?>">
                        <?php foreach ( $dates_by_dataset[ $key ] ?? [] as $fd ) :
                            $label = date( 'M d, Y', strtotime( substr( $fd, 0, 4 ) . '-' . substr( $fd, 4, 2 ) . '-' . substr( $fd, 6, 2 ) ) );
                        ?>
                        <option value="<?php echo esc_attr( $fd ); ?>"><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="weimop-btn weimop-btn--sm weimop-md-view-btn"
                            data-dataset="<?php echo esc_attr( $key ); ?>">View Chart</button>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Chart Area -->
        <div id="weimop-md-chart-wrap" class="weimop-md-chart-wrap" style="display:none;">
            <div class="weimop-md-chart-header">
                <span id="weimop-md-chart-title" class="weimop-md-chart-title"></span>
                <div style="display:flex;align-items:center;gap:8px;margin-left:auto;flex-wrap:wrap;">
                    <label style="font-size:11px;color:#787b86;display:flex;align-items:center;gap:6px;">Region
                        <select id="weimop-md-region" class="weimop-tv-select" style="min-width:120px;">
                            <option value="">All Regions</option>
                        </select>
                    </label>
                    <label style="font-size:11px;color:#787b86;display:flex;align-items:center;gap:6px;">Resource
                        <select id="weimop-md-resource" class="weimop-tv-select" style="min-width:150px;">
                            <option value="">All Resources</option>
                        </select>
                    </label>
                    <button id="weimop-md-chart-close" class="weimop-btn weimop-btn--sm">Close</button>
                </div>
            </div>
            <div id="weimop-md-lw-chart" class="weimop-md-lw-chart">
                <div class="weimop-tv-tooltip" id="weimop-md-tt" style="display:none;pointer-events:none;"></div>
            </div>
            <div id="weimop-md-legend" class="weimop-tv-legend" style="background:#1a1e2e;padding:6px 14px;"></div>
            <div id="weimop-md-table-wrap" class="weimop-md-table-wrap">
                <table class="weimop-md-table">
                    <thead id="weimop-md-table-head"></thead>
                    <tbody id="weimop-md-table-body"></tbody>
                </table>
            </div>
        </div>

        <!-- Import History -->
        <div class="weimop-md-history">
            <h4 class="weimop-md-section-title">Import History (last 100)</h4>
            <?php if ( empty( $log_rows ) ) : ?>
            <p class="weimop-md-empty">No data imported yet. Click "Fetch from IEMOP" to start.</p>
            <?php else : ?>
            <table class="weimop-md-table">
                <thead>
                    <tr><th>Dataset</th><th>File</th><th>Date</th><th>Rows</th><th>Imported At</th></tr>
                </thead>
                <tbody>
                    <?php foreach ( $log_rows as $lr ) : ?>
                    <tr>
                        <td><?php echo esc_html( $datasets[ $lr['dataset'] ]['label'] ?? $lr['dataset'] ); ?></td>
                        <td class="weimop-md-mono"><?php echo esc_html( $lr['filename'] ); ?></td>
                        <td><?php echo esc_html( date( 'M d, Y', strtotime( substr( $lr['file_date'], 0, 4 ) . '-' . substr( $lr['file_date'], 4, 2 ) . '-' . substr( $lr['file_date'], 6, 2 ) ) ) ); ?></td>
                        <td><?php echo number_format( (int)$lr['rows_imported'] ); ?></td>
                        <td><?php echo esc_html( $lr['imported_at'] ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

    </div>

    <script>
    (function(){
        var nonce = (window._weimopCfg && _weimopCfg.nonce)
            ? _weimopCfg.nonce
            : (document.getElementById('weimop-config-data')
                ? JSON.parse(document.getElementById('weimop-config-data').textContent).nonce
                : '');
        var ajaxurl = (window._weimopCfg && _weimopCfg.ajaxurl)
            ? _weimopCfg.ajaxurl
            : (document.getElementById('weimop-config-data')
                ? JSON.parse(document.getElementById('weimop-config-data').textContent).ajaxurl
                : '/wp-admin/admin-ajax.php');

        function mdPost(action, data) {
            var fd = new FormData();
            fd.append('action', action);
            fd.append('nonce', nonce);
            for (var k in data) fd.append(k, data[k]);
            return fetch(ajaxurl, { method: 'POST', body: fd })
                .then(function(r){ return r.json(); });
        }

        // Fetch button
        var fetchBtn = document.getElementById('weimop-md-fetch-btn');
        var fetchStatus = document.getElementById('weimop-md-fetch-status');
        var logWrap = document.getElementById('weimop-md-log-wrap');
        var logEl = document.getElementById('weimop-md-log');
        if (fetchBtn) {
            fetchBtn.addEventListener('click', function() {
                var days = document.getElementById('weimop-md-days').value;
                fetchBtn.disabled = true;
                fetchBtn.textContent = 'Fetching...';
                fetchStatus.style.display = 'none';
                logWrap.style.display = 'block';
                logEl.textContent = 'Fetching ' + days + ' days of data from IEMOP...\n';

                mdPost('weimop_fetch_market_data', { days: days }).then(function(j) {
                    fetchBtn.disabled = false;
                    fetchBtn.textContent = 'Fetch from IEMOP';
                    if (!j.success) {
                        logEl.textContent += 'Error: ' + (j.data && j.data.message ? j.data.message : JSON.stringify(j));
                        return;
                    }
                    if (j.data && j.data.php_notices) {
                        logEl.textContent += '[PHP] ' + j.data.php_notices + '\n';
                    }
                    var results = j.data && j.data.results ? j.data.results : [];
                    if (j.data && j.data.error) {
                        logEl.textContent += 'Error: ' + j.data.error;
                        return;
                    }
                    var ok = 0, skip = 0, err = 0;
                    results.forEach(function(r) {
                        logEl.textContent += '[' + (r.status || '?') + '] ' + r.file + ' — ' + r.msg + '\n';
                        if (r.status === 'ok') ok++;
                        else if (r.status === 'skip') skip++;
                        else err++;
                    });
                    logEl.textContent += '\nDone. ' + ok + ' imported, ' + skip + ' skipped, ' + err + ' errors.\n';
                    fetchStatus.textContent = ok + ' new files imported.';
                    fetchStatus.style.display = 'inline';
                    if (ok > 0) setTimeout(function(){ window.location.reload(); }, 1500);
                }).catch(function(e) {
                    fetchBtn.disabled = false;
                    fetchBtn.textContent = 'Fetch from IEMOP';
                    logEl.textContent += 'Request failed: ' + e.message;
                });
            });
        }

        // Import All Files button
        var importAllBtn = document.getElementById('weimop-md-import-all-btn');
        if (importAllBtn) {
            importAllBtn.addEventListener('click', function() {
                if (!confirm('This will scan the local market_data/ folder and import all CSV files not yet in the database. Continue?')) return;
                importAllBtn.disabled = true;
                importAllBtn.textContent = 'Importing...';
                fetchStatus.style.display = 'none';
                logWrap.style.display = 'block';
                logEl.textContent = 'Scanning local market_data/ folder and importing CSV files...\n';

                mdPost('weimop_import_all_files', {}).then(function(j) {
                    importAllBtn.disabled = false;
                    importAllBtn.textContent = 'Import Local Files';
                    if (!j.success) {
                        logEl.textContent += 'Error: ' + (j.data && j.data.message ? j.data.message : JSON.stringify(j));
                        return;
                    }
                    if (j.data && j.data.php_notices) {
                        logEl.textContent += '[PHP] ' + j.data.php_notices + '\n';
                    }
                    if (j.data && j.data.error) {
                        logEl.textContent += 'Error: ' + j.data.error;
                        return;
                    }
                    var results = j.data && j.data.results ? j.data.results : [];
                    var ok = 0, err = 0;
                    results.forEach(function(r) {
                        if (r.status !== 'skip') logEl.textContent += '[' + (r.status || '?') + '] ' + r.file + ' — ' + r.msg + '\n';
                        if (r.status === 'ok') ok++;
                        else if (r.status === 'error') err++;
                    });
                    logEl.textContent += '\nDone. ' + ok + ' files imported, ' + err + ' errors.\n';
                    fetchStatus.textContent = ok + ' files imported.';
                    fetchStatus.style.display = 'inline';
                    if (ok > 0) setTimeout(function(){ window.location.reload(); }, 2000);
                }).catch(function(e) {
                    importAllBtn.disabled = false;
                    importAllBtn.textContent = 'Import Local Files';
                    logEl.textContent += 'Request failed: ' + e.message;
                });
            });
        }

        // Chart view buttons
        document.querySelectorAll('.weimop-md-view-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var dataset = btn.getAttribute('data-dataset');
                var selEl   = document.querySelector('.weimop-md-date-sel[data-dataset="' + dataset + '"]');
                var fd      = selEl ? selEl.value : '';
                if (!fd) return;
                weimopMdLoadChart(dataset, fd);
            });
        });

        document.getElementById('weimop-md-chart-close') &&
            document.getElementById('weimop-md-chart-close').addEventListener('click', function() {
                document.getElementById('weimop-md-chart-wrap').style.display = 'none';
                if (mdLwChart) { mdLwChart.remove(); mdLwChart = null; }
            });

        var mdLwChart = null;
        var mdLwSeries = [];
        var MD_PALETTE = ['#2962ff','#00b746','#ab47bc','#ef5350','#ff6d00','#00bcd4','#fdd835','#5c6bc0','#ec407a','#26a69a'];
        var mdCurrentDataset = '';
        var mdCurrentDate    = '';

        // Region / Resource selector change triggers reload
        ['weimop-md-region', 'weimop-md-resource'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener('change', function() {
                if (mdCurrentDataset && mdCurrentDate) weimopMdLoadChart(mdCurrentDataset, mdCurrentDate);
            });
        });

        function weimopMdLoadChart(dataset, file_date) {
            mdCurrentDataset = dataset;
            mdCurrentDate    = file_date;
            var wrap     = document.getElementById('weimop-md-chart-wrap');
            var title    = document.getElementById('weimop-md-chart-title');
            var thead    = document.getElementById('weimop-md-table-head');
            var tbody    = document.getElementById('weimop-md-table-body');
            var region   = document.getElementById('weimop-md-region')   ? document.getElementById('weimop-md-region').value   : '';
            var resource = document.getElementById('weimop-md-resource') ? document.getElementById('weimop-md-resource').value : '';
            wrap.style.display = 'block';
            title.textContent  = 'Loading…';
            thead.innerHTML    = '';
            tbody.innerHTML    = '';

            var postData = { dataset: dataset, file_date: file_date };
            if (region)   postData.region   = region;
            if (resource) postData.resource = resource;

            mdPost('weimop_get_md_chart', postData).then(function(j) {
                if (!j.success) { title.textContent = 'Error: ' + (j.data && j.data.message ? j.data.message : 'Unknown'); return; }
                var d = j.data;
                title.textContent = dataset.toUpperCase() + ' — ' + file_date + ' (' + d.count + ' rows)';

                var seriesObj  = d.series || {};
                var seriesKeys = Object.keys(seriesObj);

                // Populate dropdowns on first load (no filters set yet)
                if (!region && !resource && seriesKeys.length) {
                    weimopMdPopulateRegions(dataset);
                    weimopMdPopulateResources(dataset);
                }

                // Build LightweightCharts
                var container = document.getElementById('weimop-md-lw-chart');
                if (!container || !window.LightweightCharts) return;

                if (mdLwChart) { mdLwChart.remove(); mdLwChart = null; mdLwSeries = []; }

                mdLwChart = LightweightCharts.createChart(container, {
                    layout:{ textColor:'#d1d4dc', background:{ type:'solid', color:'#131722' } },
                    rightPriceScale:{ borderColor:'#2a2e39' },
                    timeScale:{ borderColor:'#2a2e39', timeVisible:true, secondsVisible:false },
                    grid:{ vertLines:{ color:'#1e2230' }, horzLines:{ color:'#1e2230' } },
                    crosshair:{ mode:LightweightCharts.CrosshairMode.Normal },
                    handleScroll:true, handleScale:true, height:320,
                });

                var legendHtml = '';
                mdLwSeries = [];
                var seriesLabels = [];
                var colorIdx = 0;

                seriesKeys.slice(0, 10).forEach(function(sk) {
                    var pts = seriesObj[sk];
                    if (!pts || !pts.length) return;
                    var color = MD_PALETTE[colorIdx++ % MD_PALETTE.length];
                    var isSingle = seriesKeys.length === 1;
                    var ts;
                    if (isSingle) {
                        ts = mdLwChart.addAreaSeries({
                            lineColor: color,
                            topColor: hexToRgba(color, 0.28),
                            bottomColor: hexToRgba(color, 0.02),
                            lineWidth: 2,
                            lastValueVisible: true, priceLineVisible: false,
                        });
                    } else {
                        ts = mdLwChart.addLineSeries({
                            color: color,
                            lineWidth: seriesKeys.length > 5 ? 1 : 2,
                            lastValueVisible: false, priceLineVisible: false,
                        });
                    }
                    var lwData = pts.filter(function(p){ return p.time; }).map(function(p){
                        var t = typeof p.time === 'number' ? p.time : (new Date(p.time).getTime() / 1000);
                        return { time: Math.floor(t), value: parseFloat(p.value) || 0 };
                    }).sort(function(a,b){ return a.time - b.time; });
                    ts.setData(lwData);
                    mdLwSeries.push(ts);
                    seriesLabels.push(sk);
                    legendHtml += '<span class="weimop-tv-legend-item"><span class="weimop-tv-legend-dot" style="background:' + color + '"></span>' + sk + '</span>';
                });

                mdLwChart.timeScale().fitContent();
                var legendEl = document.getElementById('weimop-md-legend');
                if (legendEl) legendEl.innerHTML = legendHtml;

                // Tooltip
                mdLwChart.subscribeCrosshairMove(function(param){
                    var tt = document.getElementById('weimop-md-tt');
                    if (!tt) return;
                    if (!param.point || !param.time) { tt.style.display = 'none'; return; }
                    var t = param.time;
                    var tStr = typeof t === 'number'
                        ? new Date(t * 1000).toLocaleString('en-PH',{month:'short',day:'2-digit',hour:'2-digit',minute:'2-digit'})
                        : String(t);
                    var html = '<div style="font-size:11px;font-weight:700;color:#d1d4dc;margin-bottom:4px;">' + tStr + '</div>';
                    mdLwSeries.forEach(function(s, i){
                        var v = param.seriesData && param.seriesData.get ? param.seriesData.get(s) : null;
                        if (v && v.value !== undefined) {
                            var c2 = MD_PALETTE[i % MD_PALETTE.length];
                            html += '<div style="display:flex;align-items:center;gap:6px;font-size:11px;"><span style="width:8px;height:8px;border-radius:50%;background:' + c2 + ';flex-shrink:0;display:inline-block;"></span><span style="color:#787b86;">' + (seriesLabels[i]||'') + '</span><span style="color:#d1d4dc;margin-left:auto;padding-left:12px;">' + parseFloat(v.value).toFixed(2) + '</span></div>';
                        }
                    });
                    tt.innerHTML = html;
                    var cw = container.clientWidth || 600;
                    var ch = container.clientHeight || 320;
                    var tw = 200, th = 80;
                    var left = param.point.x + 14;
                    var top  = param.point.y - 10;
                    if (left + tw > cw) left = param.point.x - tw - 14;
                    if (top + th  > ch) top  = ch - th - 10;
                    tt.style.left = left + 'px';
                    tt.style.top  = top + 'px';
                    tt.style.display = 'block';
                });

                // Table
                var rows = d.rows || [];
                if (rows.length > 0) {
                    var cols = Object.keys(rows[0]).filter(function(c){ return c !== 'id' && c !== 'raw_json'; });
                    thead.innerHTML = '<tr>' + cols.map(function(c){ return '<th>' + c + '</th>'; }).join('') + '</tr>';
                    tbody.innerHTML = rows.map(function(r){
                        return '<tr>' + cols.map(function(c){
                            var v = r[c] !== null && r[c] !== undefined ? r[c] : '';
                            return '<td>' + String(v).substring(0, 60) + '</td>';
                        }).join('') + '</tr>';
                    }).join('');
                }
            });
        }

        function weimopMdPopulateRegions(dataset) {
            var regionSel = document.getElementById('weimop-md-region');
            if (!regionSel) return;
            var regionDs = ['mcp','regional','reserve_mcp','reserve_sched'];
            if (regionDs.indexOf(dataset) === -1) { regionSel.innerHTML = '<option value="">All Regions</option>'; return; }
            mdPost('weimop_get_md_regions', { dataset: dataset }).then(function(j2) {
                if (!j2 || !j2.success || !j2.data || !j2.data.regions) return;
                var cur = regionSel.value;
                regionSel.innerHTML = '<option value="">All Regions</option>';
                j2.data.regions.forEach(function(r) {
                    var opt = document.createElement('option');
                    opt.value = r; opt.textContent = r;
                    if (r === cur) opt.selected = true;
                    regionSel.appendChild(opt);
                });
            }).catch(function(){});
        }

        function weimopMdPopulateResources(dataset) {
            var resourceSel = document.getElementById('weimop-md-resource');
            if (!resourceSel) return;
            var resourceDs = ['mcp','reserve_mcp','congestion','reserve_sched'];
            if (resourceDs.indexOf(dataset) === -1) { resourceSel.innerHTML = '<option value="">All Resources</option>'; return; }
            mdPost('weimop_get_md_resources', { dataset: dataset }).then(function(j2) {
                if (!j2 || !j2.success || !j2.data || !j2.data.resources) return;
                var cur = resourceSel.value;
                resourceSel.innerHTML = '<option value="">All Resources</option>';
                j2.data.resources.forEach(function(r) {
                    var opt = document.createElement('option');
                    opt.value = r; opt.textContent = r;
                    if (r === cur) opt.selected = true;
                    resourceSel.appendChild(opt);
                });
            }).catch(function(){});
        }

        function hexToRgba(hex, alpha) {
            var r = parseInt(hex.slice(1,3),16), g = parseInt(hex.slice(3,5),16), b = parseInt(hex.slice(5,7),16);
            return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
        }
    })();
    </script>
    <?php
    return ob_get_clean();
}

/* -------------------------------------------------------
   O. SETTINGS DEFAULTS & GETTER
------------------------------------------------------- */

function bntm_weimop_get_css(){return '
/* ---- Reset / container ---- */
.weimop-wrap{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;font-size:13px;color:#1a2b3c;background:#f2f5f8;border:1px solid #d0d9e4;border-radius:6px;padding:20px;line-height:1.5;}

/* ---- Banner ---- */
.weimop-banner{display:flex;align-items:center;gap:10px;padding:9px 14px;border-radius:5px;margin-bottom:16px;font-size:12px;font-weight:600;letter-spacing:.2px;}
.weimop-banner--checking{background:#fefce8;border:1px solid #fde047;color:#713f12;}
.weimop-banner--ok{background:#f0fdf4;border:1px solid #86efac;color:#14532d;}
.weimop-banner--warn{background:#fff7ed;border:1px solid #fdba74;color:#7c2d12;}
.weimop-banner--error{background:#fef2f2;border:1px solid #fca5a5;color:#7f1d1d;}
.weimop-banner__icon{width:8px;height:8px;border-radius:50%;background:currentColor;flex-shrink:0;}
.weimop-banner__link{margin-left:auto;color:inherit;font-weight:700;text-decoration:underline;white-space:nowrap;}

/* ---- Tabs ---- */
.weimop-tabs{display:flex;flex-wrap:wrap;gap:4px;margin-bottom:18px;border-bottom:2px solid #dde3ea;padding-bottom:0;}
.weimop-tab{padding:8px 16px;border:1px solid transparent;border-bottom:none;border-radius:4px 4px 0 0;text-decoration:none;color:#4a5568;font-weight:500;font-size:12px;letter-spacing:.3px;text-transform:uppercase;background:transparent;margin-bottom:-2px;}
.weimop-tab:hover{background:#eef1f6;color:#1a2b3c;}
.weimop-tab.is-active{background:#fff;border-color:#dde3ea #dde3ea #fff;color:#102a43;font-weight:700;}

/* ---- Cards ---- */
.weimop-card{background:#fff;border:1px solid #dde3ea;border-radius:6px;padding:16px;margin-bottom:14px;}
.weimop-card--info{background:#f8fafc;border-color:#cbd5e0;}
.weimop-card--ok{border-left:3px solid #22c55e;}
.weimop-card--warn{border-left:3px solid #f59e0b;}
.weimop-card--error{border-left:3px solid #ef4444;}
.weimop-card__header{display:flex;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:12px;padding-bottom:10px;border-bottom:1px solid #eef1f6;}
.weimop-card__title{margin:0;font-size:13px;font-weight:700;color:#102a43;letter-spacing:.2px;}

/* ---- KPI grid ---- */
.weimop-kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;}
.weimop-kpi{background:#f8fafc;border:1px solid #dde3ea;border-radius:5px;padding:12px;}
.weimop-kpi__label{display:block;font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:#718096;margin-bottom:4px;}
.weimop-kpi__value{display:block;font-size:20px;font-weight:700;color:#102a43;font-variant-numeric:tabular-nums;}
.weimop-kpi__unit{font-size:10px;color:#a0aec0;margin-top:2px;display:block;}

/* ---- Charts ---- */
.weimop-chart-wrap{height:320px;position:relative;width:100%;}
.weimop-chart-empty{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:#f8fafc;border-radius:4px;color:#a0aec0;font-size:12px;}
.weimop-source-label{font-size:11px;color:#a0aec0;margin:6px 0 0;}

/* ---- Checks grid ---- */
.weimop-checks-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:5px;margin-top:12px;}
.weimop-check{display:flex;align-items:center;gap:6px;font-size:11px;padding:5px 8px;border-radius:4px;}
.weimop-check--pass{background:#f0fdf4;color:#166534;}
.weimop-check--fail{background:#fef2f2;color:#991b1b;}
.weimop-check__icon{font-size:11px;flex-shrink:0;}

/* ---- Status dots ---- */
.weimop-status-dot{display:inline-block;width:7px;height:7px;border-radius:50%;margin-right:5px;vertical-align:middle;}
.weimop-status-dot--ok{background:#22c55e;}
.weimop-status-dot--warn{background:#f59e0b;}
.weimop-status-dot--error{background:#ef4444;}

/* ---- Badges ---- */
.weimop-badge{font-size:10px;font-weight:700;padding:2px 7px;border-radius:3px;letter-spacing:.4px;text-transform:uppercase;}
.weimop-badge--live{background:#dcfce7;color:#166534;border:1px solid #bbf7d0;}

/* ---- Backfill password row ---- */
.weimop-backfill-password-row{display:flex;align-items:center;gap:8px;}

/* ---- Info table ---- */
.weimop-info-table{border-collapse:collapse;width:100%;font-size:12px;}
.weimop-info-table td{padding:5px 8px;border-bottom:1px solid #eef1f6;vertical-align:top;}
.weimop-info-table td:first-child{color:#718096;width:120px;font-weight:600;white-space:nowrap;}

/* ---- Certificate dropzone ---- */
.weimop-dropzone{border:2px dashed #cbd5e0;border-radius:5px;padding:22px;text-align:center;background:#f8fafc;cursor:pointer;transition:border-color .15s;}
.weimop-dropzone:hover,.weimop-dropzone.drag-over{border-color:#6366f1;background:#eef2ff;}
.weimop-dropzone p{margin:0;font-size:12px;color:#718096;}
.weimop-pfx-controls{display:flex;gap:8px;align-items:flex-end;margin-top:10px;}
.weimop-pfx-controls .weimop-form-group{flex:1;}

/* ---- Progress bar ---- */
.weimop-progress{height:5px;background:#e2e8f0;border-radius:3px;overflow:hidden;margin-bottom:4px;}
.weimop-progress__fill{height:100%;background:#6366f1;transition:width .25s;}

/* ---- Cert preview ---- */
.weimop-cert-preview{margin-top:12px;background:#f8fafc;border:1px solid #dde3ea;border-radius:5px;padding:12px;font-size:11px;}
.weimop-cert-preview table{width:100%;border-collapse:collapse;}
.weimop-cert-preview td{padding:4px 8px;border-bottom:1px solid #eef1f6;}
.weimop-cert-preview td:first-child{color:#718096;width:150px;font-weight:600;}
.weimop-cert-preview td:last-child{color:#1a2b3c;word-break:break-all;}
.weimop-cert-valid{color:#166534;font-weight:700;}
.weimop-cert-expired{color:#991b1b;font-weight:700;}
.weimop-cert-expiring{color:#92400e;font-weight:700;}

/* ---- Forms ---- */
.weimop-form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:10px;}
.weimop-form-group{display:flex;flex-direction:column;gap:4px;}
.weimop-label{font-size:11px;font-weight:600;color:#4a5568;letter-spacing:.2px;}
.weimop-input{border:1px solid #cbd5e0;border-radius:4px;padding:7px 9px;font-size:12px;width:100%;box-sizing:border-box;color:#1a2b3c;background:#fff;}
.weimop-input:focus{outline:none;border-color:#6366f1;box-shadow:0 0 0 2px rgba(99,102,241,.15);}
.weimop-input--sm{padding:5px 8px;font-size:12px;width:180px;}
.weimop-form-actions{display:flex;align-items:center;gap:14px;margin-top:4px;}

/* ---- Buttons ---- */
.weimop-btn{background:#f2f5f8;color:#1a2b3c;border:1px solid #cbd5e0;border-radius:4px;padding:8px 14px;cursor:pointer;font-size:12px;font-weight:600;white-space:nowrap;}
.weimop-btn:hover{background:#e2e8f0;}.weimop-btn:disabled{opacity:.5;cursor:not-allowed;}
.weimop-btn--primary{background:#102a43;color:#fff;border-color:#102a43;}
.weimop-btn--primary:hover{background:#1e3a5f;}
.weimop-btn--sm{padding:5px 10px;font-size:11px;}

/* ---- Notices ---- */
.weimop-notice{font-size:12px;padding:8px 12px;border-radius:4px;border:1px solid transparent;}
.weimop-notice--ok{background:#f0fdf4;border-color:#86efac;color:#166534;}
.weimop-notice--warn{background:#fff7ed;border-color:#fdba74;color:#7c2d12;}
.weimop-notice--error{background:#fef2f2;border-color:#fca5a5;color:#991b1b;}
.weimop-inline-notice{font-size:11px;padding:6px 10px;border-radius:4px;margin-bottom:10px;background:#fefce8;border:1px solid #fde047;color:#713f12;}

/* ---- Misc ---- */
.weimop-muted{color:#718096;font-size:12px;margin:0 0 10px;}
.weimop-meta-text{font-size:11px;color:#a0aec0;}
.weimop-list{margin:0;padding-left:18px;}
.weimop-list li{margin-bottom:5px;font-size:12px;}
.weimop-issue-list{margin:0 0 12px 18px;padding:0;}
.weimop-issue-list li{font-size:12px;color:#92400e;margin-bottom:4px;}
.weimop-field-flag{font-size:10px;font-weight:700;padding:1px 6px;border-radius:3px;margin-left:5px;vertical-align:middle;}
.weimop-field-flag--ok{background:#dcfce7;color:#166534;}
.weimop-field-flag--warn{background:#fef9c3;color:#713f12;}
code{font-size:11px;background:#f2f5f8;border:1px solid #e2e8f0;border-radius:3px;padding:1px 5px;color:#4a5568;}
.weimop-diag-pass{color:#166534;font-weight:700;}.weimop-diag-fail{color:#991b1b;font-weight:700;}.weimop-diag-warn{color:#92400e;font-weight:700;}
@media(max-width:600px){
    .weimop-chart-wrap{height:220px;}
    .weimop-kpi-grid{grid-template-columns:repeat(2,1fr);}
    .weimop-pfx-controls{flex-direction:column;}
    .weimop-backfill-password-row{flex-direction:column;align-items:flex-start;}
    .weimop-input--sm{width:100%;}
    .weimop-md-cards{grid-template-columns:repeat(2,1fr);}
}
/* ---- Market Data Tab ---- */
.weimop-md-wrap{padding:4px 0;}
.weimop-md-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px;}
.weimop-md-title{font-size:15px;font-weight:700;color:#102a43;margin:0;}
.weimop-md-controls{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.weimop-md-select{border:1px solid #cbd5e0;border-radius:4px;padding:5px 8px;font-size:12px;color:#1a2b3c;background:#fff;}
.weimop-md-label{font-size:11px;font-weight:600;color:#4a5568;display:flex;align-items:center;gap:6px;}
.weimop-md-log-wrap{background:#0f172a;border-radius:6px;padding:12px;margin-bottom:16px;}
.weimop-md-log{font-family:monospace;font-size:11px;color:#94a3b8;white-space:pre-wrap;max-height:180px;overflow-y:auto;}
.weimop-md-cards{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;}
.weimop-md-card{background:#fff;border:1px solid #e2e8f0;border-radius:6px;padding:14px;display:flex;flex-direction:column;gap:6px;}
.weimop-md-card-title{font-size:11px;font-weight:700;color:#4a5568;text-transform:uppercase;letter-spacing:.3px;}
.weimop-md-card-stat{font-size:20px;font-weight:700;color:#102a43;}
.weimop-md-card-sub{font-size:11px;color:#718096;}
.weimop-md-card-actions{display:flex;gap:8px;align-items:center;margin-top:4px;flex-wrap:wrap;}
.weimop-md-chart-wrap{background:#fff;border:1px solid #e2e8f0;border-radius:6px;padding:16px;margin-bottom:20px;}
.weimop-md-chart-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;}
.weimop-md-chart-title{font-size:13px;font-weight:700;color:#102a43;}
.weimop-md-canvas{width:100%;height:240px;display:block;}
.weimop-md-table-wrap{overflow-x:auto;margin-top:14px;max-height:280px;overflow-y:auto;}
.weimop-md-table{width:100%;border-collapse:collapse;font-size:11px;}
.weimop-md-table th{background:#f2f5f8;padding:6px 8px;text-align:left;font-weight:600;color:#4a5568;border-bottom:1px solid #e2e8f0;position:sticky;top:0;}
.weimop-md-table td{padding:5px 8px;border-bottom:1px solid #f2f5f8;color:#1a2b3c;vertical-align:top;}
.weimop-md-table tr:hover td{background:#f8fafc;}
.weimop-md-history{margin-top:4px;}
.weimop-md-section-title{font-size:13px;font-weight:700;color:#102a43;margin:0 0 10px;}
.weimop-md-empty{font-size:12px;color:#718096;padding:20px 0;}
.weimop-md-mono{font-family:monospace;font-size:11px;}
/* ---- Trading Graph Tab (TV Dark) ---- */
.weimop-tv-wrap{background:#0d1117;border-radius:8px;overflow:hidden;margin-bottom:0;}
.weimop-tv-toolbar{display:flex;align-items:center;gap:12px;padding:10px 16px;background:#1e222d;border-bottom:1px solid #2a2e39;flex-wrap:wrap;}
.weimop-tv-toolbar-label{display:flex;align-items:center;gap:6px;font-size:11px;color:#787b86;}
.weimop-tv-input{background:#2a2e39;border:1px solid #363c4e;color:#d1d4dc;border-radius:4px;padding:5px 8px;font-size:12px;}
.weimop-tv-select{background:#2a2e39;border:1px solid #363c4e;color:#d1d4dc;border-radius:4px;padding:5px 8px;font-size:12px;}
.weimop-tv-btn{background:#2962ff;color:#fff;border:none;border-radius:4px;padding:6px 14px;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap;}
.weimop-tv-btn:hover{background:#1a53e8;}
.weimop-tv-panel{background:#1e222d;border-bottom:1px solid #2a2e39;}
.weimop-tv-panel-header{display:flex;align-items:center;gap:10px;padding:10px 14px;flex-wrap:wrap;min-height:38px;}
.weimop-tv-panel-title{font-size:12px;font-weight:700;color:#d1d4dc;white-space:nowrap;}
.weimop-tv-panel-sub{font-size:11px;color:#787b86;flex:1;}
.weimop-tv-badge{font-size:10px;font-weight:600;padding:2px 8px;border-radius:3px;border:1px solid;color:#787b86;}
.weimop-tv-panel-stats{display:flex;align-items:center;gap:0;margin-left:auto;flex-wrap:nowrap;}
.weimop-tv-stat{display:flex;flex-direction:column;align-items:center;padding:0 10px;border-right:1px solid #2a2e39;}
.weimop-tv-stat:last-child{border-right:none;}
.weimop-tv-stat-lbl{font-size:9px;text-transform:uppercase;letter-spacing:.5px;color:#787b86;}
.weimop-tv-stat-val{font-size:12px;font-weight:700;color:#d1d4dc;font-variant-numeric:tabular-nums;}
.weimop-tv-chart{height:300px;position:relative;background:#131722;width:100%;}
.weimop-tv-placeholder{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:#131722;color:#787b86;font-size:12px;}
.weimop-tv-tooltip{position:absolute;z-index:100;background:#1e222d;border:1px solid #2a2e39;border-radius:4px;padding:8px 10px;min-width:160px;max-width:260px;box-shadow:0 4px 12px rgba(0,0,0,.5);}
.weimop-tv-legend{display:flex;flex-wrap:wrap;gap:10px;padding:6px 14px;background:#1a1e2e;min-height:28px;}
.weimop-tv-legend-item{display:flex;align-items:center;gap:5px;font-size:10px;color:#787b86;}
.weimop-tv-legend-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;}
/* ---- Market Data LW Chart ---- */
.weimop-md-lw-chart{height:340px;position:relative;background:#131722;border-radius:4px;overflow:hidden;margin-bottom:2px;}
';}

/* -------------------------------------------------------
   Q. JAVASCRIPT  -- heredoc, no PHP injection, no emojis
------------------------------------------------------- */

function bntm_weimop_get_js(){return <<<'JSCODE'
(function(){
'use strict';

var cfgEl = document.getElementById('weimop-config-data');
var CFG   = cfgEl ? JSON.parse(cfgEl.textContent || cfgEl.innerHTML) : {};
var AJAX  = CFG.ajaxurl  || '';
var NONCE = CFG.nonce    || '';
var REFRESH_MS = CFG.refreshMs || 300000;

if (!AJAX || !NONCE) {
    var b = document.getElementById('weimop-conn-banner');
    if (b) { b.className='weimop-banner weimop-banner--error'; document.getElementById('weimop-conn-text').textContent='Configuration missing. Check plugin installation.'; }
    return;
}

function post(action, extra) {
    var fd = new FormData();
    fd.append('action', action); fd.append('nonce', NONCE);
    if (extra) Object.keys(extra).forEach(function(k){ fd.append(k, extra[k]); });
    return fetch(AJAX, {method:'POST',body:fd}).then(function(r){return r.json();});
}
function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

/* connection banner */
function updateBanner(data) {
    var b=document.getElementById('weimop-conn-banner');
    var t=document.getElementById('weimop-conn-text');
    var f=document.getElementById('weimop-conn-fix');
    if (!b) return;
    var hasData = data.checks && (data.checks.has_rtd || data.checks.has_hap);
    var hasIssues = data.issues && data.issues.length > 0;
    if (!hasIssues) {
        b.className='weimop-banner weimop-banner--ok';
        t.textContent='Connection ready.';
        if(f)f.style.display='none';
    } else if (data.checks && data.checks.db_exists && !hasData) {
        b.className='weimop-banner weimop-banner--warn';
        t.textContent='Database is ready but contains no data. Use Settings to fetch historical data from IEMOP.';
        if(f)f.style.display='inline';
    } else {
        b.className='weimop-banner weimop-banner--error';
        var first = (data.issues && data.issues[0]) ? data.issues[0] : 'Setup incomplete.';
        t.textContent = first.length > 100 ? first.slice(0,100)+'...' : first;
        if(f)f.style.display='inline';
    }
}

post('weimop_connection_status').then(function(j){
    if (j.success) updateBanner(j.data);
    else {
        var b=document.getElementById('weimop-conn-banner');
        if(b){b.className='weimop-banner weimop-banner--error';document.getElementById('weimop-conn-text').textContent='Could not reach server.';}
    }
}).catch(function(err){
    var b=document.getElementById('weimop-conn-banner');
    if(b){b.className='weimop-banner weimop-banner--error';document.getElementById('weimop-conn-text').textContent='Network error: '+err.message;}
});

/* KPI snapshot */
var snap = document.getElementById('weimop-snapshot');
function fetchSnap(){
    if(!snap)return;
    post('weimop_get_market_snapshot').then(function(j){
        if(!j.success)return;
        ['last_interval','rtd_schedule','lmp_price','offered_cap','hap_price','dap_price'].forEach(function(k){
            var el=snap.querySelector('[data-key="'+k+'"]');
            if(el)el.textContent=(j.data[k]!==undefined)?j.data[k]:'--';
        });
        var fe=document.getElementById('weimop-last-fetch');
        if(fe)fe.textContent='Updated '+new Date().toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
    }).catch(function(){});
}

/* chart factory */
function makeChart(id){
    var el=document.getElementById(id);
    if(!el||!window.LightweightCharts)return null;
    return LightweightCharts.createChart(el,{
        layout:{textColor:'#4a5568',background:{type:'solid',color:'#ffffff'}},
        rightPriceScale:{borderColor:'#dde3ea'},
        leftPriceScale:{visible:true,borderColor:'#dde3ea'},
        timeScale:{borderColor:'#dde3ea',timeVisible:true,secondsVisible:false},
        grid:{vertLines:{color:'#f2f5f8'},horzLines:{color:'#f2f5f8'}},
        crosshair:{mode:LightweightCharts.CrosshairMode.Normal},
        handleScroll:true,handleScale:true,
    });
}
function hidePH(id){var e=document.getElementById(id+'-placeholder');if(e)e.style.display='none';}
function showPH(id,msg){var e=document.getElementById(id+'-placeholder');if(e){e.querySelector('p').textContent=msg;e.style.display='flex';}}

/* chart 1: RTD vs LMP */
var rtdEl=document.getElementById('weimop-rtd-chart');
var rtdC=null,rtdS=null,lmpS=null;
if(rtdEl&&window.LightweightCharts){
    rtdC=makeChart('weimop-rtd-chart');
    rtdS=rtdC.addLineSeries({color:'#2563eb',lineWidth:2,title:'RTD Schedule (MW)',priceScaleId:'left'});
    lmpS=rtdC.addLineSeries({color:'#dc2626',lineWidth:2,title:'LMP (PHP/MWh)'});
}
function fetchRtd(){
    if(!rtdC)return;
    post('weimop_get_chart_series').then(function(j){
        if(!j.success||!j.data)return;
        var r=j.data.rtd||[],l=j.data.lmp||[];
        if(r.length)rtdS.setData(r);if(l.length)lmpS.setData(l);
        if(r.length||l.length){hidePH('weimop-rtd-chart');rtdC.timeScale().fitContent();}
        else showPH('weimop-rtd-chart','No data available. Fetch historical data from Settings.');
    }).catch(function(){showPH('weimop-rtd-chart','Error loading data.');});
}

/* chart 2: HAP */
var hapEl=document.getElementById('weimop-hap-chart');
var hapC=null,hapS=null;
if(hapEl&&window.LightweightCharts){hapC=makeChart('weimop-hap-chart');hapS=hapC.addLineSeries({color:'#7c3aed',lineWidth:2,title:'HAP (PHP/MWh)'});}
function fetchHap(){
    if(!hapC)return;
    post('weimop_get_hap_series').then(function(j){
        var rows=(j.success&&j.data)?(j.data.hap||[]):[];
        if(rows.length){hapS.setData(rows);hidePH('weimop-hap-chart');hapC.timeScale().fitContent();}
        else showPH('weimop-hap-chart','No data available.');
    }).catch(function(){showPH('weimop-hap-chart','Error loading data.');});
}

/* chart 3: DAP */
var dapEl=document.getElementById('weimop-dap-chart');
var dapC=null,dapS=null;
if(dapEl&&window.LightweightCharts){dapC=makeChart('weimop-dap-chart');dapS=dapC.addLineSeries({color:'#059669',lineWidth:2,title:'DAP (PHP/MWh)'});}
function fetchDap(){
    if(!dapC)return;
    post('weimop_get_dap_series').then(function(j){
        var rows=(j.success&&j.data)?(j.data.dap||[]):[];
        if(rows.length){dapS.setData(rows);hidePH('weimop-dap-chart');dapC.timeScale().fitContent();}
        else showPH('weimop-dap-chart','No data available.');
    }).catch(function(){showPH('weimop-dap-chart','Error loading data.');});
}

/* chart 4: OCC */
var occEl=document.getElementById('weimop-occ-chart');
var occC=null,occOS=null,occSS=null;
if(occEl&&window.LightweightCharts){
    occC=makeChart('weimop-occ-chart');
    occOS=occC.addLineSeries({color:'#d97706',lineWidth:2,title:'Offered Capacity (MW)'});
    occSS=occC.addLineSeries({color:'#64748b',lineWidth:1,title:'Scheduled Capacity (MW)',priceScaleId:'left',lineStyle:1});
}
function fetchOcc(){
    if(!occC)return;
    post('weimop_get_occ_series').then(function(j){
        if(!j.success||!j.data)return;
        var o=j.data.offered||[],s=j.data.scheduled||[];
        if(o.length)occOS.setData(o);if(s.length)occSS.setData(s);
        if(o.length||s.length){hidePH('weimop-occ-chart');occC.timeScale().fitContent();}
        else showPH('weimop-occ-chart','No data available.');
    }).catch(function(){showPH('weimop-occ-chart','Error loading data.');});
}

window.addEventListener('resize',function(){
    [[rtdC,rtdEl],[hapC,hapEl],[dapC,dapEl],[occC,occEl]].forEach(function(p){
        if(p[0]&&p[1])p[0].applyOptions({width:p[1].clientWidth});
    });
});

function refreshAll(){fetchSnap();fetchRtd();fetchHap();fetchDap();fetchOcc();}
if(snap||rtdEl||hapEl||dapEl||occEl){
    refreshAll();
    setInterval(refreshAll,REFRESH_MS);
    var rb=document.getElementById('weimop-refresh-all');
    if(rb)rb.addEventListener('click',refreshAll);
    var badge=document.getElementById('weimop-auto-badge');
    if(badge)setInterval(function(){badge.style.opacity=badge.style.opacity==='0.3'?'1':'0.3';},1800);
}

/* backfill - password comes from inline input, not prompt() */
var backfillBtn = document.getElementById('weimop-backfill-btn');
if (backfillBtn) {
    backfillBtn.addEventListener('click', function() {
        var pwInput = document.getElementById('weimop-backfill-password');
        var pw = pwInput ? pwInput.value : '';
        var bstat = document.getElementById('weimop-backfill-status');
        backfillBtn.disabled = true;
        backfillBtn.textContent = 'Connecting to IEMOP...';
        if (bstat) { bstat.className='weimop-notice weimop-notice--warn'; bstat.style.display='block'; bstat.textContent='Connecting to IEMOP NMMS MPI service and retrieving 24 h of interval data. This may take 1-2 minutes.'; }
        post('weimop_fetch_historical', {cert_password: pw}).then(function(j){
            backfillBtn.disabled = false;
            backfillBtn.textContent = 'Fetch Previous 24h Data from IEMOP';
            if (bstat) {
                bstat.className = j.success ? 'weimop-notice weimop-notice--ok' : 'weimop-notice weimop-notice--error';
                bstat.style.display = 'block';
                bstat.textContent = j.success ? j.data.message : ((j.data&&j.data.message)?j.data.message:'Fetch failed.');
            }
            if (j.success && j.data.inserted > 0) {
                setTimeout(function(){ location.reload(); }, 1800);
            }
        }).catch(function(err){
            backfillBtn.disabled=false;
            backfillBtn.textContent='Fetch Previous 24h Data from IEMOP';
            if(bstat){bstat.className='weimop-notice weimop-notice--error';bstat.style.display='block';bstat.textContent='Request failed: '+err.message;}
        });
    });
}

/* save settings */
var saveBtn=document.getElementById('weimop-save-settings');
if(saveBtn){
    saveBtn.addEventListener('click',function(){
        var fd=new FormData(document.getElementById('weimop-settings-form'));
        fd.append('action','weimop_save_settings');fd.append('nonce',NONCE);
        var msg=document.getElementById('weimop-settings-msg');
        msg.className='weimop-notice weimop-notice--warn';msg.style.display='block';msg.textContent='Saving...';
        fetch(AJAX,{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(j){
            if(j.success){
                msg.className='weimop-notice weimop-notice--ok';msg.textContent=j.data.message;
                if(j.data.status)updateBanner(j.data.status);
                setTimeout(function(){location.reload();},900);
            }else{
                msg.className='weimop-notice weimop-notice--error';
                msg.textContent=(j.data&&j.data.message)?j.data.message:'Save failed.';
            }
        }).catch(function(){msg.className='weimop-notice weimop-notice--error';msg.style.display='block';msg.textContent='Save failed.';});
    });
}

/* PFX upload + client-side cert reader */
var pfxFile=null,parsedCert=null;
var dropZone=document.getElementById('weimop-pfx-drop-zone');
var fileInput=document.getElementById('weimop-pfx-file-input');
var pfxPwd=document.getElementById('weimop-pfx-password');
var uploadBtn=document.getElementById('weimop-pfx-upload-btn');
var preview=document.getElementById('weimop-pfx-preview');
var applyBtn=document.getElementById('weimop-pfx-apply-btn');
var pfxStat=document.getElementById('weimop-pfx-status');

if(dropZone){
    dropZone.addEventListener('dragover',function(e){e.preventDefault();dropZone.classList.add('drag-over');});
    dropZone.addEventListener('dragleave',function(){dropZone.classList.remove('drag-over');});
    dropZone.addEventListener('drop',function(e){e.preventDefault();dropZone.classList.remove('drag-over');if(e.dataTransfer.files[0])loadFile(e.dataTransfer.files[0]);});
    dropZone.addEventListener('click',function(){if(fileInput)fileInput.click();});
}
if(fileInput)fileInput.addEventListener('change',function(){if(fileInput.files[0])loadFile(fileInput.files[0]);});

function loadFile(f){
    if(!/\.(pfx|p12)$/i.test(f.name)){showPS('Only .pfx or .p12 files are accepted.','error');return;}
    pfxFile=f;parsedCert=null;
    document.getElementById('weimop-pfx-drop-label').textContent=f.name+' ('+(f.size/1024).toFixed(1)+' KB) - click Upload and Read Certificate';
    if(preview)preview.style.display='none';
    if(applyBtn)applyBtn.style.display='none';
    if(uploadBtn)uploadBtn.disabled=false;
    showPS('File ready. Enter password if required, then click Upload and Read Certificate.','warn');
}

if(uploadBtn)uploadBtn.addEventListener('click',function(){
    if(!pfxFile){showPS('No file selected.','error');return;}
    var bar=document.getElementById('weimop-pfx-bar');
    var prog=document.getElementById('weimop-pfx-progress');
    var lbl=document.getElementById('weimop-pfx-progress-label');
    if(prog)prog.style.display='block';
    showPS('Uploading...','warn');
    var fd=new FormData();
    fd.append('action','weimop_upload_pfx');fd.append('nonce',NONCE);fd.append('pfx_file',pfxFile);
    var xhr=new XMLHttpRequest();xhr.open('POST',AJAX);
    xhr.upload.onprogress=function(e){if(e.lengthComputable&&bar){var p=Math.round(e.loaded/e.total*100);bar.style.width=p+'%';if(lbl)lbl.textContent='Uploading '+p+'%';}};
    xhr.onload=function(){
        if(prog)prog.style.display='none';
        var resp;try{resp=JSON.parse(xhr.responseText);}catch(e){showPS('Upload failed: invalid server response.','error');return;}
        if(!resp.success){showPS('Upload failed: '+((resp.data&&resp.data.message)?resp.data.message:'Unknown error.'),'error');return;}
        var pf=document.getElementById('weimop-cert-path');if(pf)pf.value=resp.data.path;
        showPS('Certificate uploaded: '+resp.data.path,'ok');
        if(!window.forge)return;
        var reader=new FileReader();
        reader.onload=function(ev){
            try{
                var u8=new Uint8Array(ev.target.result),bin='';
                for(var i=0;i<u8.length;i++)bin+=String.fromCharCode(u8[i]);
                var p12=forge.pkcs12.pkcs12FromAsn1(forge.asn1.fromDer(bin),false,pfxPwd?pfxPwd.value:'');
                var bags=(p12.getBags({bagType:forge.pki.oids.certBag})[forge.pki.oids.certBag])||[];
                if(!bags.length){showPS('Uploaded successfully. No certificate bags found (check password to read details).','ok');return;}
                var bag=bags[0],cert=bag.cert;
                function rdn(s,n){var a=s.getField(n);return a?a.value:'';}
                var der=forge.asn1.toDer(forge.pki.certificateToAsn1(cert)).getBytes();
                var thumb=forge.md.sha1.create().update(der).digest().toHex().match(/.{2}/g).join(':').toUpperCase();
                var now=new Date(),dl=Math.ceil((cert.validity.notAfter-now)/86400000);
                var vc=dl<0?'weimop-cert-expired':dl<=30?'weimop-cert-expiring':'weimop-cert-valid';
                var vt=dl<0?'EXPIRED':dl<=30?'Expiring in '+dl+' day(s)':'Valid ('+dl+' days remaining)';
                var fn=(bag.attributes&&bag.attributes.friendlyName)?(bag.attributes.friendlyName[0]||''):'';
                if(!fn)fn=rdn(cert.subject,'CN');
                parsedCert={fn:fn,cn:rdn(cert.subject,'CN'),o:rdn(cert.subject,'O'),ou:rdn(cert.subject,'OU'),
                    ic:rdn(cert.issuer,'CN'),io:rdn(cert.issuer,'O'),
                    nb:cert.validity.notBefore.toISOString().slice(0,19).replace('T',' ')+' UTC',
                    na:cert.validity.notAfter.toISOString().slice(0,19).replace('T',' ')+' UTC',
                    vt:vt,vc:vc,ser:cert.serialNumber,thumb:thumb,cnt:bags.length};
                if(preview){
                    preview.innerHTML='<strong style="font-size:12px;color:#102a43;display:block;margin-bottom:8px;">Certificate Details</strong><table>'+
                        '<tr><td>Friendly Name</td><td>'+esc(parsedCert.fn)+'</td></tr>'+
                        '<tr><td>Subject CN</td><td>'+esc(parsedCert.cn)+'</td></tr>'+
                        '<tr><td>Organization</td><td>'+esc(parsedCert.o)+'</td></tr>'+
                        '<tr><td>Org Unit</td><td>'+esc(parsedCert.ou)+'</td></tr>'+
                        '<tr><td>Issuer CN</td><td>'+esc(parsedCert.ic)+'</td></tr>'+
                        '<tr><td>Issuer Org</td><td>'+esc(parsedCert.io)+'</td></tr>'+
                        '<tr><td>Valid From</td><td>'+esc(parsedCert.nb)+'</td></tr>'+
                        '<tr><td>Valid Until</td><td>'+esc(parsedCert.na)+'</td></tr>'+
                        '<tr><td>Status</td><td><span class="'+vc+'">'+esc(vt)+'</span></td></tr>'+
                        '<tr><td>SHA-1 Thumbprint</td><td>'+esc(parsedCert.thumb)+'</td></tr>'+
                        '<tr><td>Certs in bundle</td><td>'+parsedCert.cnt+'</td></tr></table>';
                    preview.style.display='block';
                }
                if(applyBtn)applyBtn.style.display='inline-block';
                showPS('Certificate read successfully. Click Apply to populate the CN and Friendly Name fields.','ok');
            }catch(err){showPS('Uploaded. Could not read certificate details: '+(err.message||'incorrect password?'),'ok');}
        };
        reader.readAsArrayBuffer(pfxFile);
    };
    xhr.onerror=function(){if(prog)prog.style.display='none';showPS('Upload failed (network error).','error');};
    xhr.send(fd);
});

if(applyBtn)applyBtn.addEventListener('click',function(){
    if(!parsedCert)return;
    var cf=document.getElementById('weimop-cert-name');if(cf&&parsedCert.cn)cf.value=parsedCert.cn;
    var ff=document.getElementById('weimop-friendly-name');if(ff&&parsedCert.fn)ff.value=parsedCert.fn;
    showPS('Applied - Certificate Name: "'+parsedCert.cn+'" | Friendly Name: "'+parsedCert.fn+'". Save Settings to persist.','ok');
});

function showPS(msg,type){
    if(!pfxStat)return;
    pfxStat.textContent=msg;
    pfxStat.className='weimop-notice weimop-notice--'+(type==='error'?'error':type==='ok'?'ok':'warn');
    pfxStat.style.display='block';
}

/* upload ExportResultsConf.xml */
var expConfFileEl = document.getElementById('weimop-export-conf-file');
var expConfBtn    = document.getElementById('weimop-upload-export-conf-btn');
var expConfStat   = document.getElementById('weimop-export-conf-status');

function showExpConfStatus(msg, type) {
    if (!expConfStat) return;
    expConfStat.textContent = msg;
    expConfStat.className = 'weimop-notice weimop-notice--' + (type === 'error' ? 'error' : type === 'ok' ? 'ok' : 'warn');
    expConfStat.style.display = 'block';
}

if (expConfBtn) {
    expConfBtn.addEventListener('click', function(){
        if (!expConfFileEl || !expConfFileEl.files || !expConfFileEl.files[0]) {
            showExpConfStatus('Select ExportResultsConf.xml first.', 'error');
            return;
        }

        var file = expConfFileEl.files[0];
        if (!/\.xml$/i.test(file.name)) {
            showExpConfStatus('Only .xml files are allowed.', 'error');
            return;
        }

        expConfBtn.disabled = true;
        expConfBtn.textContent = 'Uploading...';
        showExpConfStatus('Uploading ExportResultsConf.xml...', 'warn');

        var fd = new FormData();
        fd.append('action', 'weimop_upload_export_conf');
        fd.append('nonce', NONCE);
        fd.append('export_conf_file', file);

        fetch(AJAX, {method:'POST', body:fd}).then(function(r){ return r.json(); }).then(function(j){
            expConfBtn.disabled = false;
            expConfBtn.textContent = 'Upload ExportResultsConf';

            if (!j.success) {
                showExpConfStatus((j.data && j.data.message) ? j.data.message : 'Upload failed.', 'error');
                return;
            }

            var fld = document.querySelector('input[name="nmms_export_conf"]');
            if (fld) fld.value = j.data.path || '';
            showExpConfStatus('Upload complete. Path applied. Click Save Settings to persist with other changes.', 'ok');
        }).catch(function(err){
            expConfBtn.disabled = false;
            expConfBtn.textContent = 'Upload ExportResultsConf';
            showExpConfStatus('Upload failed: ' + err.message, 'error');
        });
    });
}


/* ---- diagnostics ---- */
var diagBtn = document.getElementById('weimop-diag-btn');
if (diagBtn) {
    diagBtn.addEventListener('click', function() {
        var pw   = (document.getElementById('weimop-diag-password') || {}).value || '';
        var res  = document.getElementById('weimop-diag-results');
        var tbl  = document.getElementById('weimop-diag-table');
        var raw  = document.getElementById('weimop-diag-raw');
        diagBtn.disabled = true; diagBtn.textContent = 'Running...';
        post('weimop_diag', {cert_password: pw}).then(function(j) {
            diagBtn.disabled = false; diagBtn.textContent = 'Run Diagnostics';
            if (!j.success) {
                if (res) { res.style.display='block'; tbl.innerHTML='<tr><td colspan="2" class="weimop-diag-fail">'+esc(j.data&&j.data.message?j.data.message:'Diagnostic request failed.')+'</td></tr>'; }
                return;
            }
            var rows = j.data.report || [];
            var html = rows.map(function(r) {
                var cls = r.pass ? 'weimop-diag-pass' : 'weimop-diag-fail';
                var tick = r.pass ? '&#10003;' : '&#10007;';
                return '<tr><td><span class="'+cls+'">'+tick+'</span> '+esc(r.label)+'</td><td class="'+cls+'">'+esc(r.detail)+'</td></tr>';
            }).join('');
            if (res)  res.style.display = 'block';
            if (tbl)  tbl.innerHTML = html;
            if (raw)  {
                var sd = j.data && j.data.soap_debug ? j.data.soap_debug : null;
                if (sd && sd.attempted) {
                    var head = 'SOAP Probe\n'
                        + 'resultType: ' + (sd.resultType || '') + '\n'
                        + 'intervalEnd: ' + (sd.intervalEnd || '') + '\n'
                        + 'httpCode: ' + (sd.httpCode || 0) + '\n\n';
                    var body = sd.ok
                        ? (sd.xml || '(empty XML response)')
                        : ('Probe error: ' + (sd.error || 'Unknown error'));
                    raw.textContent = head + body;
                    raw.style.display = 'block';
                } else {
                    raw.style.display = 'none';
                }
            }
        }).catch(function(err) {
            diagBtn.disabled = false; diagBtn.textContent = 'Run Diagnostics';
            if (res) { res.style.display='block'; tbl.innerHTML='<tr><td colspan="2" class="weimop-diag-fail">Request failed: '+esc(err.message)+'</td></tr>'; }
        });
    });
}

})();
JSCODE;
}
