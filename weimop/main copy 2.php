<?php
/**
 * Module Name: WESM/EIMOP Trading Dashboard
 * Module Slug: weimop
 * Description: WESM/EIMOP real-time monitoring. Fetches live + historical data from IEMOP NMMS MPI
 *              via SOAP/HTTPS with PFX mutual-TLS authentication.
 *              Data stored in WordPress MySQL database with tables like wp_weimop_rtd_schedules.
 * Version: 8.0.0
 * Author: BNTM
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'BNTM_WEIMOP_PATH',       dirname( __FILE__ ) . '/' );
define( 'BNTM_WEIMOP_URL',        plugin_dir_url( __FILE__ ) );
define( 'BNTM_WEIMOP_PFX_SUBDIR', 'pfx_folder' );
define( 'BNTM_WEIMOP_VER',        '8.0.0' );

/* -------------------------------------------------------
   A. PATH HELPERS
------------------------------------------------------- */

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

/* -------------------------------------------------------
   B. MYSQL TABLE CONFIGURATION
------------------------------------------------------- */

function bntm_weimop_db_tables() {
    global $wpdb;
    return [
        'RTDSchedules' => $wpdb->prefix . 'weimop_rtd_schedules',
        'HAPResults'   => $wpdb->prefix . 'weimop_hap_results',
        'DAPResults'   => $wpdb->prefix . 'weimop_dap_results',
        'OCCResourcesComplianceDetail' => $wpdb->prefix . 'weimop_occ_resources',
        'ExtractorLog' => $wpdb->prefix . 'weimop_extractor_log',
    ];
}

function bntm_weimop_open_db( $readonly = false ) {
    global $wpdb;
    return $wpdb;
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
        'weimop_rtd_schedules' => "CREATE TABLE {$p}weimop_rtd_schedules (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            TIME_INTERVAL VARCHAR(50) NOT NULL UNIQUE,
            SCHEDULE DECIMAL(12,4) DEFAULT 0, LMP DECIMAL(12,4) DEFAULT 0,
            PRICE_NODE VARCHAR(100) DEFAULT '', UNIT_ID VARCHAR(100) DEFAULT '', MARKET_RUN VARCHAR(100) DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_time_interval (TIME_INTERVAL)
        ) {$c};",
        'weimop_hap_results' => "CREATE TABLE {$p}weimop_hap_results (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            TIME_INTERVAL VARCHAR(50) NOT NULL UNIQUE,
            PRICE DECIMAL(12,4) DEFAULT 0, PRICE_NODE VARCHAR(100) DEFAULT '', MARKET_RUN VARCHAR(100) DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_time_interval (TIME_INTERVAL)
        ) {$c};",
        'weimop_dap_results' => "CREATE TABLE {$p}weimop_dap_results (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            TIME_INTERVAL VARCHAR(50) NOT NULL UNIQUE,
            PRICE DECIMAL(12,4) DEFAULT 0, PRICE_NODE VARCHAR(100) DEFAULT '', MARKET_RUN VARCHAR(100) DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_time_interval (TIME_INTERVAL)
        ) {$c};",
        'weimop_occ_resources' => "CREATE TABLE {$p}weimop_occ_resources (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            TIME_INTERVAL VARCHAR(50) NOT NULL UNIQUE,
            OFFERED_CAP DECIMAL(12,4) DEFAULT 0, SCHEDULED_CAP DECIMAL(12,4) DEFAULT 0,
            UNIT_ID VARCHAR(100) DEFAULT '', REGION VARCHAR(100) DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_time_interval (TIME_INTERVAL)
        ) {$c};",
        'weimop_extractor_log' => "CREATE TABLE {$p}weimop_extractor_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            run_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(20) DEFAULT 'ok', message TEXT DEFAULT '', rows_added INT DEFAULT 0
        ) {$c};",
    ];
}

function bntm_weimop_create_tables() {
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    foreach ( bntm_weimop_get_tables() as $sql ) dbDelta( $sql );
}

function bntm_weimop_import_sql_data() {
    global $wpdb;
    
    $sql_file = BNTM_WEIMOP_PATH . 'sql.sql';
    if (!file_exists($sql_file)) {
        return ['success' => false, 'message' => 'sql.sql file not found at ' . $sql_file];
    }
    
    $sql_content = file_get_contents($sql_file);
    if (!$sql_content) {
        return ['success' => false, 'message' => 'Unable to read sql.sql file'];
    }
    
    // Split by semicolon and filter empty statements
    $statements = array_filter(array_map('trim', explode(';', $sql_content)));
    
    $imported = 0;
    $failed = 0;
    $errors = [];
    
    foreach ($statements as $statement) {
        if (empty($statement) || strpos(trim($statement), '--') === 0) continue;
        
        // Replace table names to use WordPress prefix
        $statement = str_ireplace(
            ['DROP TABLE IF EXISTS RTDSchedules', 'CREATE TABLE RTDSchedules', 'INSERT INTO RTDSchedules'],
            ['DROP TABLE IF EXISTS ' . $wpdb->prefix . 'weimop_rtd_schedules', 'CREATE TABLE ' . $wpdb->prefix . 'weimop_rtd_schedules', 'INSERT INTO ' . $wpdb->prefix . 'weimop_rtd_schedules'],
            $statement
        );
        $statement = str_ireplace(
            ['DROP TABLE IF EXISTS HAPSchedules', 'CREATE TABLE HAPSchedules', 'INSERT INTO HAPSchedules'],
            ['DROP TABLE IF EXISTS ' . $wpdb->prefix . 'weimop_hap_results', 'CREATE TABLE ' . $wpdb->prefix . 'weimop_hap_results', 'INSERT INTO ' . $wpdb->prefix . 'weimop_hap_results'],
            $statement
        );
        
        try {
            $result = $wpdb->query($statement);
            if ($result !== false) {
                $imported++;
            } else {
                $failed++;
                if (!empty($wpdb->last_error)) {
                    $errors[] = $wpdb->last_error;
                }
            }
        } catch (Exception $e) {
            $failed++;
            $errors[] = $e->getMessage();
        }
    }
    
    return [
        'success' => $failed === 0,
        'message' => "Imported $imported statements, $failed failed.",
        'imported' => $imported,
        'failed' => $failed,
        'errors' => array_slice($errors, 0, 5)  // Return first 5 errors
    ];
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
add_action( 'wp_ajax_weimop_fetch_historical',    'bntm_ajax_weimop_fetch_historical'    );
add_action( 'wp_ajax_weimop_import_sql_data',     'bntm_ajax_weimop_import_sql_data'     );
add_action( 'wp_ajax_weimop_diag',               'bntm_ajax_weimop_diag'               );

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
            <a href="?tab=settings"    class="weimop-tab <?php echo $active_tab === 'settings'    ? 'is-active' : ''; ?>">Settings</a>
        </nav>

        <div class="weimop-content">
            <?php
            if     ( $active_tab === 'overview'    ) echo weimop_tab_overview();
            elseif ( $active_tab === 'trading'     ) echo weimop_tab_trading();
            elseif ( $active_tab === 'suggestions' ) echo weimop_tab_suggestions();
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

function weimop_tab_trading() { ob_start(); ?>
    <div class="weimop-card">
        <div class="weimop-card__header"><h3 class="weimop-card__title">Trading Workspace</h3></div>
        <p>Order blotter, position management, PnL and settlement preview &mdash; scheduled for next release.</p>
    </div>
<?php return ob_get_clean(); }

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
    $pfx_ok  = ! empty( $s['nmms_cert_path'] ) && file_exists( $s['nmms_cert_path'] );

    ob_start(); ?>

    <!-- Database info -->
    <div class="weimop-card weimop-card--info">
        <div class="weimop-card__header">
            <h3 class="weimop-card__title">WordPress MySQL Database</h3>
            <form method="post" style="margin-left:auto;">
                <?php wp_nonce_field('weimop_bootstrap_db'); ?>
                <input type="hidden" name="weimop_action" value="bootstrap_db">
                <button type="submit" class="weimop-btn weimop-btn--sm">Re-initialize Tables</button>
            </form>
        </div>
        <table class="weimop-info-table">
            <tr><td>Database</td><td><code><?php global $wpdb; echo esc_html( DB_NAME ); ?></code></td></tr>
            <?php
            global $wpdb;
            $tables = bntm_weimop_db_tables();
            $rtd_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables['RTDSchedules']}");
            $hap_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables['HAPResults']}");
            $dap_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables['DAPResults']}");
            $occ_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables['OCCResourcesComplianceDetail']}");
            ?>
            <tr><td>RTDSchedules</td><td><?php echo $rtd_count; ?> rows</td></tr>
            <tr><td>HAPResults</td><td><?php echo $hap_count; ?> rows</td></tr>
            <tr><td>DAPResults</td><td><?php echo $dap_count; ?> rows</td></tr>
            <tr><td>OCC Resources</td><td><?php echo $occ_count; ?> rows</td></tr>
            <tr><td>Status</td><td><span class="weimop-status-dot weimop-status-dot--ok"></span> MySQL active</td></tr>
        </table>
        <?php
        if ( isset($_POST['weimop_action']) && $_POST['weimop_action'] === 'bootstrap_db' && check_admin_referer('weimop_bootstrap_db') ) {
            bntm_weimop_create_tables();
            echo '<p class="weimop-notice weimop-notice--ok">Tables re-initialized successfully.</p>';
        }
        ?>
    </div>

    <!-- SQL Data Import -->
    <div class="weimop-card">
        <div class="weimop-card__header">
            <h3 class="weimop-card__title">Import Historical Data</h3>
        </div>
        <p style="color:var(--wesm-muted);font-size:12px;margin:0 0 12px;">Load RTDSchedules and HAPResults from <code>sql.sql</code> file into MySQL database.</p>
        <button type="button" class="weimop-btn weimop-btn--primary" id="weimop-import-sql-btn">Import SQL Data</button>
        <div id="weimop-import-msg" style="display:none;font-size:12px;padding:8px 12px;border-radius:6px;margin-top:12px;"></div>
    </div>

    <script>
    (function() {
        var importBtn = document.getElementById('weimop-import-sql-btn');
        var importMsg = document.getElementById('weimop-import-msg');
        
        if (importBtn) {
            importBtn.addEventListener('click', function() {
                importBtn.disabled = true;
                importBtn.textContent = 'Importing...';
                importMsg.style.display = 'block';
                importMsg.textContent = 'Processing...';
                importMsg.className = '';
                
                var fd = new FormData();
                fd.append('action', 'weimop_import_sql_data');
                fd.append('nonce', weimop_nonce);
                
                fetch(ajaxurl, {method: 'POST', body: fd})
                    .then(r => r.json())
                    .then(resp => {
                        if (resp.success) {
                            importMsg.textContent = resp.data.message + ' ✓';
                            importMsg.className = 'weimop-notice weimop-notice--ok';
                            setTimeout(() => location.reload(), 2000);
                        } else {
                            importMsg.textContent = (resp.data?.message || 'Import failed');
                            importMsg.className = 'weimop-notice weimop-notice--error';
                        }
                    })
                    .catch(e => {
                        importMsg.textContent = 'Network error: ' + e.message;
                        importMsg.className = 'weimop-notice weimop-notice--error';
                    })
                    .finally(() => {
                        importBtn.disabled = false;
                        importBtn.textContent = 'Import SQL Data';
                    });
            });
        }
    })();
    </script>

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
                'db_connected' => 'WordPress MySQL database connected',
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
            <strong>✓ Current Certificate:</strong> <?php echo esc_html(basename($s['nmms_cert_path'])); ?><br>
            <code style="font-size:11px;background:rgba(0,0,0,0.2);padding:4px 6px;border-radius:3px;display:block;margin-top:6px;word-break:break-all;"><?php echo esc_html($s['nmms_cert_path']); ?></code>
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
                           value="<?php echo esc_attr($s['nmms_cert_path']); ?>" placeholder="Auto-filled on certificate upload">
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

    $url = ! empty($s['nmms_url']) ? $s['nmms_url'] : $s['nmms_main_url'];

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

    $ch = curl_init();
    $curl_opts = [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $soap_body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: "exportResults"',
        ],
    ];

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
    curl_close($ch);

    if ( ! $use_native_pfx ) {
        @unlink($cert_file);
        @unlink($key_file);
    }

    if ( $curl_err )         return [ 'error' => 'cURL error: ' . $curl_err ];
    if ( $http_code !== 200 ) return [ 'error' => 'HTTP ' . $http_code . ' from NMMS MPI service.' ];
    if ( ! $response )       return [ 'error' => 'Empty response from NMMS MPI service.' ];

    return [ 'xml' => $response, 'http_code' => $http_code ];
}

function bntm_weimop_parse_nmms_response( $xml_string ) {
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xml_string);
    if ( ! $xml ) return [ 'error' => 'Invalid XML in NMMS response.' ];
    $rows = [];
    foreach ( $xml->xpath('//*[local-name()="return"]') as $row ) {
        $r = [];
        foreach ($row->children() as $child) $r[$child->getName()] = (string)$child;
        if ( ! empty($r) ) $rows[] = $r;
    }
    return [ 'rows' => $rows ];
}

function bntm_weimop_insert_rows( $db, $table, $rows ) {
    global $wpdb;
    $inserted = 0;
    $tables = bntm_weimop_db_tables();
    $table_name = $tables[$table] ?? null;
    if (!$table_name) return 0;
    
    foreach ($rows as $r) {
        $ts = $r['timeInterval'] ?? $r['TIME_INTERVAL'] ?? '';
        if (!$ts) continue;
        try {
            switch ($table) {
                case 'RTDSchedules':
                    $wpdb->query($wpdb->prepare("INSERT INTO {$table_name} (TIME_INTERVAL,SCHEDULE,LMP,UNIT_ID) VALUES (%s,%f,%f,%s) ON DUPLICATE KEY UPDATE SCHEDULE=VALUES(SCHEDULE), LMP=VALUES(LMP), UNIT_ID=VALUES(UNIT_ID), updated_at=NOW()",
                        $ts, (float)($r['schedule']??$r['SCHEDULE']??0), (float)($r['lmp']??$r['LMP']??0), $r['unitId']??$r['UNIT_ID']??''
                    ));
                    $inserted++; break;
                case 'HAPResults':
                    $wpdb->query($wpdb->prepare("INSERT INTO {$table_name} (TIME_INTERVAL,PRICE) VALUES (%s,%f) ON DUPLICATE KEY UPDATE PRICE=VALUES(PRICE), updated_at=NOW()",
                        $ts, (float)($r['price']??$r['PRICE']??0)
                    ));
                    $inserted++; break;
                case 'DAPResults':
                    $wpdb->query($wpdb->prepare("INSERT INTO {$table_name} (TIME_INTERVAL,PRICE) VALUES (%s,%f) ON DUPLICATE KEY UPDATE PRICE=VALUES(PRICE), updated_at=NOW()",
                        $ts, (float)($r['price']??$r['PRICE']??0)
                    ));
                    $inserted++; break;
                case 'OCCResourcesComplianceDetail':
                    $wpdb->query($wpdb->prepare("INSERT INTO {$table_name} (TIME_INTERVAL,OFFERED_CAP,SCHEDULED_CAP,UNIT_ID) VALUES (%s,%f,%f,%s) ON DUPLICATE KEY UPDATE OFFERED_CAP=VALUES(OFFERED_CAP), SCHEDULED_CAP=VALUES(SCHEDULED_CAP), UNIT_ID=VALUES(UNIT_ID), updated_at=NOW()",
                        $ts, (float)($r['offeredCapacity']??$r['OFFERED_CAP']??0), (float)($r['scheduledCapacity']??$r['SCHEDULED_CAP']??0), $r['unitId']??$r['UNIT_ID']??''
                    ));
                    $inserted++; break;
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

    $now            = time();
    $step           = 300;
    $intervals      = 288;
    $total_inserted = 0;
    $errors         = [];

    $fetch_types = ! empty($s['nmms_result_type'])
        ? [ [ 'type' => $s['nmms_result_type'], 'table' => 'RTDSchedules' ] ]
        : [
            [ 'type' => 'RTD_LMP',  'table' => 'RTDSchedules' ],
            [ 'type' => 'HAP',      'table' => 'HAPResults'   ],
            [ 'type' => 'DAP',      'table' => 'DAPResults'   ],
            [ 'type' => 'OCC_COMP', 'table' => 'OCCResourcesComplianceDetail' ],
          ];

    global $wpdb;
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
            $parsed = bntm_weimop_parse_nmms_response($result['xml']);
            if ( isset($parsed['error']) || empty($parsed['rows']) ) continue;
            $type_inserted += bntm_weimop_insert_rows($wpdb, $ft['table'], $parsed['rows']);
        }
        $total_inserted += $type_inserted;
        try {
            $tables = bntm_weimop_db_tables();
            $wpdb->insert($tables['ExtractorLog'], [
                'status' => 'ok',
                'message' => $ft['type'] . ' backfill',
                'rows_added' => $type_inserted,
            ]);
        } catch(Exception $e) {}
    }

    $msg = 'Backfill complete. ' . $total_inserted . ' row(s) inserted.';
    if ($errors) $msg .= ' Note: ' . implode(' | ', array_slice($errors, 0, 3));

    wp_send_json_success(['message' => $msg, 'inserted' => $total_inserted, 'errors' => $errors]);
}

/* -------------------------------------------------------
   L. CONNECTION CHECK
------------------------------------------------------- */

function bntm_weimop_check_connection() {
    global $wpdb;
    $s      = bntm_weimop_get_settings(get_current_user_id());
    $checks = []; $issues = [];

    $checks['db_connected'] = $wpdb->check_connection();
    if (!$checks['db_connected']) $issues[] = 'WordPress database connection failed.';

    $checks['tables_exist'] = $checks['has_rtd'] = $checks['has_hap'] = $checks['has_dap'] = $checks['has_occ'] = false;
    
    if ($checks['db_connected']) {
        $tables = bntm_weimop_db_tables();
        try {
            // Check if all required tables exist using fast INFORMATION_SCHEMA queries
            $needed  = ['RTDSchedules','HAPResults','DAPResults','OCCResourcesComplianceDetail'];
            $checks['tables_exist'] = true;
            foreach ($needed as $table) {
                $exists = $wpdb->get_var($wpdb->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s LIMIT 1", $tables[$table]));
                if (!$exists) {
                    $checks['tables_exist'] = false;
                    break;
                }
            }
            if (!$checks['tables_exist']) $issues[] = 'One or more WESM tables are missing. Click Re-initialize Tables.';
            
            // Check if tables have data (using fast EXISTS queries)
            $checks['has_rtd'] = (int)$wpdb->get_var("SELECT 1 FROM {$tables['RTDSchedules']} LIMIT 1") === 1;
            $checks['has_hap'] = (int)$wpdb->get_var("SELECT 1 FROM {$tables['HAPResults']} LIMIT 1") === 1;
            $checks['has_dap'] = (int)$wpdb->get_var("SELECT 1 FROM {$tables['DAPResults']} LIMIT 1") === 1;
            $checks['has_occ'] = (int)$wpdb->get_var("SELECT 1 FROM {$tables['OCCResourcesComplianceDetail']} LIMIT 1") === 1;
        } catch (Exception $e) { $issues[] = 'Database query error: ' . $e->getMessage(); }
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
   SQL DATA IMPORT HANDLER
   Reads sql.sql and imports RTD/HAP/DAP data into MySQL
------------------------------------------------------- */

function bntm_ajax_weimop_import_sql_data() {
    check_ajax_referer('weimop_nonce','nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message'=>'Unauthorized']);
    if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'Insufficient permissions']);
    
    try {
        $result = bntm_weimop_import_sql_data();
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result, 400);
        }
    } catch (Exception $e) {
        wp_send_json_error(['message' => 'Import failed: ' . $e->getMessage()], 500);
    }
}

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
    $chk('MySQL database connection',       $wpdb->check_connection(),
         $wpdb->check_connection() ? 'Active' : 'Failed');

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

    // NMMS URL reachability (HEAD request, no cert needed)
    $url = !empty($s['nmms_url']) ? $s['nmms_url'] : ($s['nmms_main_url'] ?? '');
    if (!empty($url) && function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch,[
            CURLOPT_URL            => $url,
            CURLOPT_NOBODY         => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_SSL_VERIFYPEER => false, // just checking reachability
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        $reachable = ($code > 0 && $code < 600);
        $chk('NMMS MPI URL reachable', $reachable,
             $reachable ? 'HTTP '.$code.' from '.$url : 'Could not reach '.$url.($err ? ': '.$err : ' (timeout or DNS failure)'));
    } else {
        $chk('NMMS MPI URL set', !empty($url), !empty($url) ? $url : 'Not set');
    }

    wp_send_json_success(['report'=>$report,'all_pass'=>$all_pass]);
}

function bntm_ajax_weimop_connection_status() {
    check_ajax_referer('weimop_nonce','nonce');
    if (!is_user_logged_in()) wp_send_json_error();
    wp_send_json_success(bntm_weimop_check_connection());
}

function bntm_ajax_weimop_upload_pfx() {
    check_ajax_referer('weimop_nonce','nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message'=>'Unauthorized']);
    if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'Insufficient permissions']);
    
    try {
        if (empty($_FILES['pfx_file']) || $_FILES['pfx_file']['error'] !== UPLOAD_ERR_OK)
            throw new Exception('File upload failed (code: '.($_FILES['pfx_file']['error']??'unknown').').', 400);
        
        $file = $_FILES['pfx_file'];
        $ext  = strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
        if (!in_array($ext,['pfx','p12'],true)) throw new Exception('Only .pfx or .p12 files are permitted.', 400);
        
        if ($file['size'] > 5242880) throw new Exception('File size exceeds 5MB limit.', 400);
        
        $dir      = bntm_weimop_pfx_dir();
        $uid      = get_current_user_id();
        $filename = 'cert_u' . $uid . '_' . time() . '_' . sanitize_file_name($file['name']);
        $dest     = trailingslashit($dir) . $filename;
        
        if (!is_dir($dir)) wp_mkdir_p($dir);
        if (!is_writable($dir)) throw new Exception('Upload directory is not writable: '.$dir, 500);
        if (!move_uploaded_file($file['tmp_name'],$dest)) throw new Exception('Could not write file.', 500);
        
        // Normalize path for storage and use
        $normalized_path = wp_normalize_path($dest);
        
        $settings = bntm_weimop_get_settings($uid);
        $settings['nmms_cert_path'] = $normalized_path;
        update_user_meta($uid,'bntm_weimop_settings',$settings);
        
        wp_send_json_success(['path'=>$normalized_path,'filename'=>$filename,'message'=>'Certificate uploaded successfully.']);
    } catch (Exception $e) {
        wp_send_json_error(['message'=>$e->getMessage(),'code'=>$e->getCode()], $e->getCode() ?: 400);
    }
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
        'nmms_cert_path'     => wp_normalize_path(!empty($_POST['nmms_cert_path'])?$_POST['nmms_cert_path']:$cur['nmms_cert_path']),
        'nmms_cert_password' => $cur['nmms_cert_password'],
        'nmms_friendly_name' => sanitize_text_field($_POST['nmms_friendly_name']??''),
        'nmms_export_conf'   => wp_normalize_path(sanitize_text_field($_POST['nmms_export_conf']??'')),
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
   N. MYSQL READERS
------------------------------------------------------- */

function bntm_weimop_fetch_rows($wpdb,$sql){
    return $wpdb->get_results($sql, ARRAY_A) ?: [];
}
function bntm_weimop_read_snapshot(){
    global $wpdb;
    $o=['last_interval'=>'--','rtd_schedule'=>'--','lmp_price'=>'--','offered_cap'=>'--','hap_price'=>'--','dap_price'=>'--'];
    $tables = bntm_weimop_db_tables();
    try{
        $r=$wpdb->get_row("SELECT TIME_INTERVAL,SCHEDULE,LMP FROM {$tables['RTDSchedules']} ORDER BY TIME_INTERVAL DESC LIMIT 1", ARRAY_A);
        if(is_array($r)){$o['last_interval']=(string)($r['TIME_INTERVAL']??'--');$o['rtd_schedule']=isset($r['SCHEDULE'])?number_format((float)$r['SCHEDULE'],2):'--';$o['lmp_price']=isset($r['LMP'])?number_format((float)$r['LMP'],2):'--';}
        $r=$wpdb->get_row("SELECT OFFERED_CAP FROM {$tables['OCCResourcesComplianceDetail']} ORDER BY TIME_INTERVAL DESC LIMIT 1", ARRAY_A);
        if(is_array($r)&&isset($r['OFFERED_CAP']))$o['offered_cap']=number_format((float)$r['OFFERED_CAP'],2);
        $r=$wpdb->get_row("SELECT PRICE FROM {$tables['HAPResults']} ORDER BY TIME_INTERVAL DESC LIMIT 1", ARRAY_A);
        if(is_array($r)&&isset($r['PRICE']))$o['hap_price']=number_format((float)$r['PRICE'],2);
        $r=$wpdb->get_row("SELECT PRICE FROM {$tables['DAPResults']} ORDER BY TIME_INTERVAL DESC LIMIT 1", ARRAY_A);
        if(is_array($r)&&isset($r['PRICE']))$o['dap_price']=number_format((float)$r['PRICE'],2);
    }catch(Exception $e){}
    return $o;
}
function bntm_weimop_read_rtd_series(){
    global $wpdb;
    $o=['rtd'=>[],'lmp'=>[]];
    $tables = bntm_weimop_db_tables();
    $rows=array_reverse(bntm_weimop_fetch_rows($wpdb,"SELECT TIME_INTERVAL,SCHEDULE,LMP FROM {$tables['RTDSchedules']} ORDER BY TIME_INTERVAL DESC LIMIT 288"));
    foreach($rows as $r){$ts=strtotime((string)($r['TIME_INTERVAL']??''));if(!$ts)continue;$o['rtd'][]=['time'=>$ts,'value'=>(float)($r['SCHEDULE']??0)];$o['lmp'][]=['time'=>$ts,'value'=>(float)($r['LMP']??0)];}
    return $o;
}
function bntm_weimop_read_hap_series(){
    global $wpdb;
    $o=['hap'=>[]];
    $tables = bntm_weimop_db_tables();
    $rows=array_reverse(bntm_weimop_fetch_rows($wpdb,"SELECT TIME_INTERVAL,PRICE FROM {$tables['HAPResults']} ORDER BY TIME_INTERVAL DESC LIMIT 48"));
    foreach($rows as $r){$ts=strtotime((string)($r['TIME_INTERVAL']??''));if(!$ts)continue;$o['hap'][]=['time'=>$ts,'value'=>(float)($r['PRICE']??0)];}
    return $o;
}
function bntm_weimop_read_dap_series(){
    global $wpdb;
    $o=['dap'=>[]];
    $tables = bntm_weimop_db_tables();
    $rows=bntm_weimop_fetch_rows($wpdb,"SELECT TIME_INTERVAL,PRICE FROM {$tables['DAPResults']} ORDER BY TIME_INTERVAL ASC LIMIT 288");
    foreach($rows as $r){$ts=strtotime((string)($r['TIME_INTERVAL']??''));if(!$ts)continue;$o['dap'][]=['time'=>$ts,'value'=>(float)($r['PRICE']??0)];}
    return $o;
}
function bntm_weimop_read_occ_series(){
    global $wpdb;
    $o=['offered'=>[],'scheduled'=>[]];
    $tables = bntm_weimop_db_tables();
    $rows=array_reverse(bntm_weimop_fetch_rows($wpdb,"SELECT TIME_INTERVAL,OFFERED_CAP,SCHEDULED_CAP FROM {$tables['OCCResourcesComplianceDetail']} ORDER BY TIME_INTERVAL DESC LIMIT 288"));
    foreach($rows as $r){$ts=strtotime((string)($r['TIME_INTERVAL']??''));if(!$ts)continue;$o['offered'][]=['time'=>$ts,'value'=>(float)($r['OFFERED_CAP']??0)];$o['scheduled'][]=['time'=>$ts,'value'=>(float)($r['SCHEDULED_CAP']??0)];}
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
        'nmms_main_url'=>'https://mpiwebp.iemop.ph/SiemensServices/ExportResultsServiceImpl',
        'nmms_backup_url'=>'https://mpiwebb.iemop.ph/SiemensServices/ExportResultsServiceImpl',
        'nmms_url'=>'','nmms_cert_name'=>'','nmms_cert_path'=>'',
        'nmms_cert_password'=>'','nmms_friendly_name'=>'','nmms_export_conf'=>'',
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
   P. CSS  -- professional, no emoji, clean typography
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
}
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
            if (raw)  { raw.style.display='none'; }
        }).catch(function(err) {
            diagBtn.disabled = false; diagBtn.textContent = 'Run Diagnostics';
            if (res) { res.style.display='block'; tbl.innerHTML='<tr><td colspan="2" class="weimop-diag-fail">Request failed: '+esc(err.message)+'</td></tr>'; }
        });
    });
}

})();
JSCODE;
}