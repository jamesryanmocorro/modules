<?php
/**
 * Module Name: Finance Management
 * Module Slug: fn
 * Description: Business finance, planning, costing, budgeting, MBA dashboard
 * Version: 2.1.0
 * Author: BNTM Hub
 * Icon: 💰
 *
 * DATABASE TABLES:
 *  fn_transactions         — income / expense ledger
 *  fn_cashflow_summary     — monthly rollup
 *  fn_recurring_expenses   — scheduled recurring cost items
 *  fn_budgets              — monthly budget targets per category
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'BNTM_FN_PATH', dirname( __FILE__ ) . '/' );
define( 'BNTM_FN_URL',  plugin_dir_url( __FILE__ ) );

/* ============================================================
   MODULE CONFIGURATION
   ============================================================ */

function bntm_fn_get_pages() {
    return [ 'Finance' => '[fn_page]' ];
}

function bntm_fn_get_shortcodes() {
    return [
        'fn_page'      => 'bntm_shortcode_fn_page',
        'fn_dashboard' => 'bntm_shortcode_fn_page',
    ];
}

function bntm_fn_get_tables() {
    global $wpdb;
    $c = $wpdb->get_charset_collate();
    $p = $wpdb->prefix;
    return [
        'fn_transactions' => "CREATE TABLE {$p}fn_transactions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            type VARCHAR(10) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            category VARCHAR(100),
            notes TEXT,
            reference_type VARCHAR(50) NULL,
            reference_id BIGINT UNSIGNED NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_business (business_id), INDEX idx_type (type),
            INDEX idx_category (category), INDEX idx_date (created_at),
            INDEX idx_reference (reference_type, reference_id)
        ) {$c};",
        'fn_cashflow_summary' => "CREATE TABLE {$p}fn_cashflow_summary (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            period VARCHAR(20) NOT NULL,
            total_income DECIMAL(10,2) DEFAULT 0,
            total_expense DECIMAL(10,2) DEFAULT 0,
            balance DECIMAL(10,2) DEFAULT 0,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business_period (business_id, period)
        ) {$c};",
        'fn_recurring_expenses' => "CREATE TABLE {$p}fn_recurring_expenses (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            name VARCHAR(150) NOT NULL,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            category VARCHAR(100) DEFAULT 'Fixed Cost',
            frequency ENUM('monthly','weekly','annual') DEFAULT 'monthly',
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_business (business_id)
        ) {$c};",
        'fn_budgets' => "CREATE TABLE {$p}fn_budgets (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            period VARCHAR(7) NOT NULL COMMENT 'YYYY-MM',
            category VARCHAR(100) NOT NULL,
            budget_type ENUM('income','expense') NOT NULL,
            budgeted_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            INDEX idx_business_period (business_id, period)
        ) {$c};"
    ];
}

function bntm_fn_create_tables() {
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    foreach ( bntm_fn_get_tables() as $sql ) dbDelta( $sql );

    if ( ! bntm_get_setting( 'fn_income_categories' ) ) {
        bntm_set_setting( 'fn_income_categories', json_encode( ['Sales','Services','Investment','Other Income'] ) );
    }
    if ( ! bntm_get_setting( 'fn_expense_categories' ) ) {
        bntm_set_setting( 'fn_expense_categories', json_encode( ['Rent','Utilities','Payroll','Supplies','Marketing','Transportation','Maintenance','Other Expense'] ) );
    }
    return count( bntm_fn_get_tables() );
}

/* ============================================================
   AJAX HOOKS
   ============================================================ */
add_action( 'wp_ajax_fn_save_transaction',   'bntm_ajax_fn_save_transaction' );
add_action('wp_ajax_fn_update_transaction', 'bntm_ajax_fn_update_transaction');
add_action( 'wp_ajax_fn_delete_transaction', 'bntm_ajax_fn_delete_transaction' );
add_action( 'wp_ajax_fn_export_csv',         'bntm_ajax_fn_export_csv' );
add_action( 'wp_ajax_fn_save_categories',    'bntm_ajax_fn_save_categories' );
add_action( 'wp_ajax_fn_save_recurring',     'bntm_ajax_fn_save_recurring' );
add_action( 'wp_ajax_fn_delete_recurring',   'bntm_ajax_fn_delete_recurring' );
add_action( 'wp_ajax_fn_save_budget',        'bntm_ajax_fn_save_budget' );
add_action( 'wp_ajax_fn_delete_budget',      'bntm_ajax_fn_delete_budget' );
add_action( 'wp_ajax_fn_generate_pdf',       'bntm_ajax_fn_generate_pdf' );
add_action( 'wp_ajax_fn_import_order',       'bntm_ajax_fn_import_order' );
add_action( 'wp_ajax_fn_revert_order',       'bntm_ajax_fn_revert_order' );

/* ============================================================
   MAIN SHORTCODE
   ============================================================ */
function bntm_shortcode_fn_page() {
    if ( ! is_user_logged_in() ) {
        return '<div class="bntm-notice bntm-notice-error">Please log in to access Finance.</div>';
    }

    if ( fn_get_current_business_id() <= 0 ) {
        return '<div class="bntm-notice bntm-notice-error">Business context is unavailable for Finance.</div>';
    }

    $tab = isset( $_GET['type'] ) ? sanitize_text_field( $_GET['type'] ) : 'dashboard';

    $tabs = [
        'dashboard'   => 'Dashboard',
        'recurring'   => 'Recurring Expenses',
        'budgets'     => 'Budgets',
        'transactions'=> 'Transactions',
        'reports'     => 'Reports',
        'settings'    => 'Settings',
    ];

    ob_start(); ?>
    <style>
    :root {
        --fn-primary: #1a1a2e;
        --fn-accent: #e94560;
        --fn-green:  #10b981;
        --fn-amber:  #f59e0b;
        --fn-blue:   #3b82f6;
        --fn-red:    #ef4444;
        --color-background-primary:   #ffffff;
        --color-background-secondary: #f9fafb;
        --color-background-tertiary:  #f3f4f6;
        --color-text-primary:   #111827;
        --color-text-secondary: #6b7280;
        --color-border-primary:   #e5e7eb;
        --color-border-secondary: #d1d5db;
        --color-border-tertiary:  #e5e7eb;
        --color-shadow: rgba(0, 0, 0, 0.05);
    }
    .fn-wrap { --fn-primary:#1a1a2e; --fn-accent:#e94560; --fn-green:#10b981; --fn-amber:#f59e0b; --fn-blue:#3b82f6; --fn-red:#ef4444; }
    .fn-chart-wrap { position:relative; height:220px; width:100%; }
    .fn-kpi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:14px; margin-bottom:20px; }
    .fn-kpi { background:var(--color-background-secondary); border:1px solid var(--color-border-tertiary); border-radius:12px; padding:18px 16px; position:relative; overflow:hidden; }
    .fn-kpi-label { font-size:11px; text-transform:uppercase; letter-spacing:.06em; color:var(--color-text-secondary); margin-bottom:6px; }
    .fn-kpi-value { font-size:22px; font-weight:600; color:var(--color-text-primary); line-height:1.2; }
    .fn-kpi-sub { font-size:12px; margin-top:4px; }
    .fn-kpi-bar { position:absolute; bottom:0; left:0; height:3px; background:var(--fn-accent); border-radius:0 0 12px 12px; transition:width .6s; }
    .fn-chart-grid { display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px; margin-bottom:20px; }
    .fn-chart-box { background:var(--color-background-primary); border:1px solid var(--color-border-tertiary); border-radius:12px; padding:16px; }
    .fn-chart-box h4 { font-size:13px; font-weight:500; color:var(--color-text-secondary); margin:0 0 12px; text-transform:uppercase; letter-spacing:.05em; }
    .fn-two-col { display:grid; grid-template-columns:1.4fr 1fr; gap:14px; margin-bottom:20px; }
    .fn-full { margin-bottom:20px; }
    .fn-table { width:100%; border-collapse:collapse; font-size:13px; }
    .fn-table th { background:var(--color-background-tertiary); padding:9px 12px; text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:var(--color-text-secondary); border-bottom:1px solid var(--color-border-tertiary); }
    .fn-table td { padding:9px 12px; border-bottom:1px solid var(--color-border-tertiary); color:var(--color-text-primary); }
    .fn-table tr:last-child td { border-bottom:none; }
    .fn-badge { display:inline-block; padding:2px 8px; border-radius:20px; font-size:11px; font-weight:600; }
    .fn-badge-income  { background:#d1fae5; color:#065f46; }
    .fn-badge-expense { background:#fee2e2; color:#991b1b; }
    .fn-badge-ok      { background:#d1fae5; color:#065f46; }
    .fn-badge-warn    { background:#fef3c7; color:#92400e; }
    .fn-badge-bad     { background:#fee2e2; color:#991b1b; }
    .fn-bar-track { background:var(--color-border-tertiary); border-radius:4px; height:6px; overflow:hidden; }
    .fn-bar-fill  { height:6px; border-radius:4px; transition:width .5s; }
    .fn-section-hdr { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:8px; }
    .fn-section-hdr h3 { margin:0; font-size:16px; }
    .fn-input { width:100%; padding:8px 10px; border:1px solid var(--color-border-secondary); border-radius:6px; background:var(--color-background-secondary); color:var(--color-text-primary); font-size:13px; }
    .fn-input:focus { outline:none; border-color:var(--fn-blue,#3b82f6); box-shadow:0 0 0 2px rgba(59,130,246,.15); }
    @media(max-width:900px){
        .fn-chart-grid { grid-template-columns:1fr; }
        .fn-two-col { grid-template-columns:1fr; }
    }
    @media(max-width:600px){
        .fn-kpi-grid { grid-template-columns:1fr 1fr; }
    }
    </style>

    <div class="fn-wrap">
    <div class="bntm-tabs">
        <?php foreach ( $tabs as $slug => $label ) : ?>
        <a href="<?php echo add_query_arg( 'type', $slug, get_permalink() ); ?>"
           class="bntm-tab <?php echo $tab === $slug ? 'active' : ''; ?>">
            <?php echo esc_html( $label ); ?>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="bntm-tab-content" style="margin-top:20px;">
    <?php
    switch ( $tab ) {
        case 'dashboard':    echo fn_dashboard_tab();    break;
        case 'recurring':    echo fn_recurring_tab();    break;
        case 'budgets':      echo fn_budgets_tab();      break;
        case 'transactions': echo fn_transactions_tab(); break;
        case 'reports':      echo fn_reports_tab();      break;
        case 'settings':     echo fn_settings_tab();     break;
        default:             echo fn_dashboard_tab();
    }
    ?>
    </div>
    </div>
    <?php
    $content = ob_get_clean();
    return bntm_universal_container( 'Finance', $content );
}

/* ============================================================
   HELPER: shared JS url + nonce head
   ============================================================ */
function fn_js_head() {
    return '<script>var ajaxurl="' . admin_url('admin-ajax.php') . '";var fnNonce="' . wp_create_nonce('bntm_fn_action') . '";</script>';
}

function fn_get_current_business_id() {
    if (function_exists('bntm_get_current_business_id')) {
        $business_id = absint(bntm_get_current_business_id());
        if ($business_id > 0) {
            return $business_id;
        }
    }

    return absint(get_current_user_id());
}

function fn_get_setting_for_business($key, $default = '', $business_id = 0) {
    $business_id = absint($business_id ?: fn_get_current_business_id());

    if ($business_id > 0 && function_exists('bntm_get_scoped_setting_option_key')) {
        $scoped_value = get_option(bntm_get_scoped_setting_option_key($key, $business_id), null);
        if ($scoped_value !== null) {
            return $scoped_value;
        }
    }

    if ($business_id > 0 && function_exists('bntm_get_business_option')) {
        $legacy_value = bntm_get_business_option('setting_' . $key, null, $business_id);
        if ($legacy_value !== null) {
            return $legacy_value;
        }
    }

    return function_exists('bntm_get_setting') ? bntm_get_setting($key, $default) : $default;
}

function fn_update_setting_for_business($key, $value, $business_id = 0) {
    $business_id = absint($business_id ?: fn_get_current_business_id());

    if ($business_id > 0 && function_exists('bntm_get_scoped_setting_option_key')) {
        return update_option(bntm_get_scoped_setting_option_key($key, $business_id), $value);
    }

    if (function_exists('bntm_set_setting')) {
        return bntm_set_setting($key, $value);
    }

    return update_option($key, $value);
}

function fn_get_categories_for_business($type, $business_id = 0) {
    $business_id = absint($business_id ?: fn_get_current_business_id());
    $defaults = [
        'income' => ['Sales','Services','Investment','Other Income'],
        'expense' => ['Rent','Utilities','Payroll','Supplies','Marketing','Transportation','Maintenance','Other Expense'],
    ];

    $setting_key = $type === 'expense' ? 'fn_expense_categories' : 'fn_income_categories';
    $raw_value = fn_get_setting_for_business($setting_key, wp_json_encode($defaults[$type] ?? []), $business_id);
    $categories = json_decode((string) $raw_value, true);

    if (!is_array($categories) || empty($categories)) {
        $categories = $defaults[$type] ?? [];
    }

    return array_values(array_filter(array_map('sanitize_text_field', $categories)));
}

/* ============================================================
   HELPER FUNCTIONS — data
   ============================================================ */
function fn_get_stats( $year = null, $month = null ) {
    global $wpdb;
    $t = $wpdb->prefix . 'fn_transactions';
    $business_id = fn_get_current_business_id();

    $all = $wpdb->get_row( $wpdb->prepare( "SELECT
        SUM(CASE WHEN type='income' THEN amount ELSE 0 END) as total_income,
        SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) as total_expense,
        SUM(CASE WHEN type='income' THEN amount ELSE -amount END) as balance
        FROM {$t}
        WHERE business_id = %d", $business_id ) );

    $y = $year  ?: date('Y');
    $m = $month ?: date('m');

    $cur = $wpdb->get_row( $wpdb->prepare(
        "SELECT
         SUM(CASE WHEN type='income' THEN amount ELSE 0 END) as income,
         SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) as expense,
         SUM(CASE WHEN type='income' THEN amount ELSE -amount END) as net
         FROM {$t} WHERE business_id = %d AND YEAR(created_at)=%d AND MONTH(created_at)=%d", $business_id, $y, $m ) );

    $prev_y = $m == 1 ? $y - 1 : $y;
    $prev_m = $m == 1 ? 12     : $m - 1;
    $prev = $wpdb->get_row( $wpdb->prepare(
        "SELECT SUM(CASE WHEN type='income' THEN amount ELSE 0 END) as income
         FROM {$t} WHERE business_id = %d AND YEAR(created_at)=%d AND MONTH(created_at)=%d", $business_id, $prev_y, $prev_m ) );

    $growth = ( $prev->income > 0 )
        ? ( ( floatval($cur->income) - floatval($prev->income) ) / floatval($prev->income) * 100 )
        : 0;

    return [
        'total_income'   => floatval( $all->total_income  ?? 0 ),
        'total_expense'  => floatval( $all->total_expense ?? 0 ),
        'balance'        => floatval( $all->balance       ?? 0 ),
        'month_income'   => floatval( $cur->income  ?? 0 ),
        'month_expense'  => floatval( $cur->expense ?? 0 ),
        'month_net'      => floatval( $cur->net     ?? 0 ),
        'growth_pct'     => round( $growth, 1 ),
    ];
}

function fn_get_monthly_series( $months = 6 ) {
    global $wpdb;
    $t = $wpdb->prefix . 'fn_transactions';
    $business_id = fn_get_current_business_id();
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT DATE_FORMAT(created_at,'%%Y-%%m') as period,
                SUM(CASE WHEN type='income' THEN amount ELSE 0 END) as income,
                SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) as expense
         FROM {$t}
         WHERE business_id = %d
         AND created_at >= DATE_SUB(NOW(), INTERVAL %d MONTH)
         GROUP BY period ORDER BY period ASC", $business_id, $months ) );
    return $rows;
}

function fn_get_category_breakdown( $type, $year, $month ) {
    global $wpdb;
    $t = $wpdb->prefix . 'fn_transactions';
    $business_id = fn_get_current_business_id();
    return $wpdb->get_results( $wpdb->prepare(
        "SELECT category, SUM(amount) as total FROM {$t}
         WHERE business_id = %d AND type=%s AND YEAR(created_at)=%d AND MONTH(created_at)=%d
         GROUP BY category ORDER BY total DESC", $business_id, $type, $year, $month ) );
}

function fn_get_recurring_total() {
    global $wpdb;
    $t = $wpdb->prefix . 'fn_recurring_expenses';
    $business_id = fn_get_current_business_id();
    if ( ! $wpdb->get_var("SHOW TABLES LIKE '{$t}'") ) return 0;
    $monthly = $wpdb->get_var($wpdb->prepare("SELECT SUM(amount) FROM {$t} WHERE business_id = %d AND is_active=1 AND frequency='monthly'", $business_id));
    $weekly  = $wpdb->get_var($wpdb->prepare("SELECT SUM(amount) FROM {$t} WHERE business_id = %d AND is_active=1 AND frequency='weekly'", $business_id));
    $annual  = $wpdb->get_var($wpdb->prepare("SELECT SUM(amount) FROM {$t} WHERE business_id = %d AND is_active=1 AND frequency='annual'", $business_id));
    return floatval($monthly) + floatval($weekly)*4.33 + floatval($annual)/12;
}

function fn_currency() {
    return bntm_get_setting('ec_currency','PHP');
}

function fn_fmt( $n ) {
    return fn_currency() . number_format(floatval($n),2);
}

function fn_update_cashflow_summary( $business_id = 0 ) {
    global $wpdb;
    $tx = $wpdb->prefix . 'fn_transactions';
    $sm = $wpdb->prefix . 'fn_cashflow_summary';
    $business_id = absint($business_id ?: fn_get_current_business_id());
    $period = date('Y-m');
    if ($business_id <= 0) {
        return;
    }
    $stats = $wpdb->get_row($wpdb->prepare("SELECT
        SUM(CASE WHEN type='income' THEN amount ELSE 0 END) as i,
        SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) as e,
        SUM(CASE WHEN type='income' THEN amount ELSE -amount END) as b
        FROM {$tx} WHERE business_id = %d AND DATE_FORMAT(created_at,'%Y-%m')=%s", $business_id, $period));
    $data = [
        'period'=>$period,'business_id'=>$business_id,
        'total_income'=>$stats->i??0,'total_expense'=>$stats->e??0,'balance'=>$stats->b??0
    ];
    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$sm} WHERE business_id = %d AND period=%s", $business_id, $period));
    if ($exists) $wpdb->update($sm,$data,['id'=>$exists]);
    else { $data['rand_id']=bntm_rand_id(); $wpdb->insert($sm,$data); }
}

function fn_get_inventory_potential_revenue($business_id = 0) {
    global $wpdb;

    $business_id = absint($business_id ?: fn_get_current_business_id());
    $table = $wpdb->prefix . 'in_products';
    if ($business_id <= 0 || !$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table))) {
        return 0.0;
    }

    return floatval($wpdb->get_var($wpdb->prepare(
        "SELECT SUM(stock_quantity * selling_price) FROM {$table} WHERE business_id = %d AND inventory_type != %s",
        $business_id,
        'Raw Material'
    )));
}

function fn_get_unrealized_order_profit($business_id = 0) {
    global $wpdb;

    $business_id = absint($business_id ?: fn_get_current_business_id());
    $orders_table = $wpdb->prefix . 'om_orders';
    $items_table = $wpdb->prefix . 'om_order_items';
    $products_table = $wpdb->prefix . 'in_products';

    if ($business_id <= 0 || !$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $orders_table))) {
        return 0.0;
    }

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT o.id, o.total, COALESCE(SUM(oi.quantity * COALESCE(p.cost_per_unit, 0)), 0) AS estimated_cost
         FROM {$orders_table} o
         LEFT JOIN {$items_table} oi ON oi.order_id = o.id AND oi.business_id = o.business_id
         LEFT JOIN {$products_table} p ON p.id = oi.inventory_product_id AND p.business_id = o.business_id
         WHERE o.business_id = %d
           AND o.status != %s
           AND o.payment_status != %s
         GROUP BY o.id, o.total",
        $business_id,
        'cancelled',
        'paid'
    ));

    $profit = 0.0;
    foreach ($rows as $row) {
        $profit += floatval($row->total) - floatval($row->estimated_cost);
    }

    return $profit;
}

/* ============================================================
   TAB: DASHBOARD
   ============================================================ */
function fn_dashboard_tab() {
    $currency = fn_currency();
    $stats    = fn_get_stats();
    $series   = fn_get_monthly_series(6);
    $fixed    = fn_get_recurring_total();
    $target   = floatval( bntm_get_setting('fn_monthly_revenue_target', 0) );
    $business_id = fn_get_current_business_id();
    $potential_revenue = fn_get_inventory_potential_revenue($business_id);
    $unrealized_profit = fn_get_unrealized_order_profit($business_id);
 
    global $wpdb;
    $y = date('Y'); $m = date('m');
    $bt = $wpdb->prefix . 'fn_budgets';
    $budgets_exist   = $wpdb->get_var("SHOW TABLES LIKE '{$bt}'");
    $budget_income   = $budgets_exist ? floatval($wpdb->get_var($wpdb->prepare("SELECT SUM(budgeted_amount) FROM {$bt} WHERE business_id=%d AND period=%s AND budget_type='income'",  $business_id, "$y-$m"))) : 0;
    $budget_expense  = $budgets_exist ? floatval($wpdb->get_var($wpdb->prepare("SELECT SUM(budgeted_amount) FROM {$bt} WHERE business_id=%d AND period=%s AND budget_type='expense'", $business_id, "$y-$m"))) : 0;
 
    /* ── Derived metrics ── */
    $gross_margin    = $stats['month_income'] > 0 ? ( $stats['month_income'] - $stats['month_expense'] ) / $stats['month_income'] * 100 : 0;
    $net_margin      = $stats['total_income'] > 0 ? $stats['balance'] / $stats['total_income'] * 100 : 0;
    $expense_ratio   = $stats['month_income'] > 0 ? $stats['month_expense'] / $stats['month_income'] * 100 : 0;
 
    /* ── Runway calculation ──
       runway = all-time balance ÷ monthly fixed costs
       If net monthly cash flow is positive we also compute "months until target" */
    $balance          = $stats['balance'] + $potential_revenue + $unrealized_profit;
    $monthly_burn     = $fixed > 0 ? $fixed : max($stats['month_expense'], 0.01);
    $runway_months    = $monthly_burn > 0 ? floor( $balance / $monthly_burn ) : 999;
    $monthly_net      = $stats['month_net'];
    $cash_flow_status = $monthly_net >= 0 ? 'positive' : 'negative';
 
    // Project balance for next 12 months using current month net as trend
    $proj_labels  = [];
    $proj_balance = [];
    
    $running_bal = $balance;
    $monthly_burn = $fixed > 0 ? $fixed : 0;
    
    $max_months = 24; // you can adjust
    
    for ($i = 1; $i <= $max_months; $i++) {
        $proj_labels[] = '"' . date('M y', strtotime("+{$i} months")) . '"';
    
        // PURE RUNWAY: subtract only recurring expenses
        $running_bal -= $monthly_burn;
    
        $proj_balance[] = round($running_bal, 2);
    
        if ($running_bal <= 0) break; // stop when money runs out
    }
    // Find when balance goes negative in projection
    $runway_cross = null;
    foreach ($proj_balance as $idx => $bal) {
        if ($bal <= 0) {
            $runway_cross = $idx + 1;
            break;
        }
    }
 
    /* ── Chart series ── */
    $labels = []; $income_arr = []; $expense_arr = [];
    foreach ( $series as $row ) {
        $labels[]      = '"' . date('M y', strtotime($row->period.'-01')) . '"';
        $income_arr[]  = floatval($row->income);
        $expense_arr[] = floatval($row->expense);
    }
    if ( empty($labels) ) { $labels = ['""']; $income_arr = [0]; $expense_arr = [0]; }
 
    /* ── Category breakdowns ── */
    $exp_breakdown = fn_get_category_breakdown('expense', $y, $m);
    $inc_breakdown = fn_get_category_breakdown('income',  $y, $m);
 
    /* ── Avg monthly revenue (last 3 months) ── */
    $last3 = $wpdb->get_var($wpdb->prepare("SELECT AVG(inc) FROM (
        SELECT SUM(CASE WHEN type='income' THEN amount ELSE 0 END) as inc
        FROM {$wpdb->prefix}fn_transactions
        WHERE business_id = %d
        AND created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
        GROUP BY DATE_FORMAT(created_at,'%Y-%m')
    ) t", $business_id));
    $avg_monthly_rev = floatval($last3);
 
    /* ── Top expense category ── */
    $top_exp_cat   = !empty($exp_breakdown) ? $exp_breakdown[0]->category : '—';
    $top_exp_amt   = !empty($exp_breakdown) ? floatval($exp_breakdown[0]->total) : 0;
 
    /* ── Break-even distance ── */
    $be_gap = $fixed > 0 && $stats['month_income'] > 0
        ? $stats['month_income'] - $fixed
        : null;
 
    ob_start(); ?>
    <?php echo fn_js_head(); ?>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:16px;">
        <div>
            <h3 style="margin:0;font-size:18px;">Finance dashboard</h3>
            <p style="margin:4px 0 0;color:var(--color-text-secondary);font-size:13px;">Cash position, inventory upside, and pending order profit in one view.</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="<?php echo add_query_arg('type','transactions',get_permalink()); ?>" class="bntm-btn-primary bntm-btn-small">+ Add Transaction</a>
            <a href="<?php echo add_query_arg('type','recurring',get_permalink()); ?>" class="bntm-btn-secondary bntm-btn-small">Recurring</a>
            <a href="<?php echo add_query_arg('type','budgets',get_permalink()); ?>" class="bntm-btn-secondary bntm-btn-small">Budgets</a>
        </div>
    </div>
 
   <!-- ═══════════════════════════════════════════
     ROW 1 — KPI grid (in-style stat cards)
════════════════════════════════════════════ -->
<style>
.fn-stat-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); gap:16px; margin-bottom:24px; }
.fn-stat-card { background:var(--color-background-primary); padding:20px; border-radius:10px; display:flex; align-items:flex-start; gap:14px; border:1px solid var(--color-border-primary); transition:box-shadow .2s; }
.fn-stat-card:hover { box-shadow:0 4px 12px var(--color-shadow); }
.fn-stat-icon { width:44px; height:44px; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.fn-stat-icon.ic-green  { background:#ecfdf5; color:#059669; }
.fn-stat-icon.ic-blue   { background:#eff6ff; color:#2563eb; }
.fn-stat-icon.ic-amber  { background:#fffbeb; color:#d97706; }
.fn-stat-icon.ic-red    { background:#fef2f2; color:#dc2626; }
.fn-stat-icon.ic-purple { background:#f5f3ff; color:#7c3aed; }
.fn-stat-icon.ic-neutral{ background:#f3f4f6; color:#374151; }
.fn-stat-content h3 { margin:0 0 4px; font-size:11px; color:var(--color-text-secondary); text-transform:uppercase; letter-spacing:.06em; font-weight:600; }
.fn-stat-number { font-size:22px; font-weight:700; color:var(--color-text-primary); margin:0; line-height:1.1; }
.fn-stat-content small { color:var(--color-text-secondary); font-size:11px; }
.fn-stat-bar { height:3px; border-radius:2px; margin-top:8px; }
</style>

<div class="fn-stat-grid">

    <!-- Revenue this month -->
    <div class="fn-stat-card">
        <div class="fn-stat-icon ic-green">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
        </div>
        <div class="fn-stat-content" style="flex:1;min-width:0;">
            <h3>Revenue this month</h3>
            <p class="fn-stat-number" style="color:#10b981;"><?php echo fn_fmt($stats['month_income']); ?></p>
            <small style="color:<?php echo $stats['growth_pct']>=0?'#10b981':'#ef4444'; ?>;">
                <?php echo ($stats['growth_pct']>=0?'▲':'▼').abs($stats['growth_pct']); ?>% vs last month
            </small>
            <?php if ($target > 0) : $pct = min(100, round($stats['month_income']/$target*100)); ?>
            <div class="fn-bar-track" style="margin-top:8px;"><div class="fn-bar-fill" style="width:<?php echo $pct; ?>%;background:#10b981;"></div></div>
            <small><?php echo $pct; ?>% of <?php echo fn_fmt($target); ?> target</small>
            <?php endif; ?>
        </div>
    </div>

    <!-- Net this month -->
    <div class="fn-stat-card">
        <div class="fn-stat-icon <?php echo $stats['month_net']>=0?'ic-green':'ic-red'; ?>">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div class="fn-stat-content" style="flex:1;min-width:0;">
            <h3>Net this month</h3>
            <p class="fn-stat-number" style="color:<?php echo $stats['month_net']>=0?'#10b981':'#ef4444'; ?>;"><?php echo fn_fmt($stats['month_net']); ?></p>
            <small>Gross margin: <?php echo round($gross_margin,1); ?>%</small>
            <div class="fn-bar-track" style="margin-top:8px;"><div class="fn-bar-fill" style="width:<?php echo min(100,max(0,abs($gross_margin))); ?>%;background:<?php echo $stats['month_net']>=0?'#10b981':'#ef4444'; ?>;"></div></div>
        </div>
    </div>

    <!-- All-time balance -->
    <div class="fn-stat-card">
        <div class="fn-stat-icon <?php echo $balance>=0?'ic-blue':'ic-red'; ?>">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/></svg>
        </div>
        <div class="fn-stat-content" style="flex:1;min-width:0;">
            <h3>All-time balance</h3>
            <p class="fn-stat-number" style="color:<?php echo $balance>=0?'#3b82f6':'#ef4444'; ?>;"><?php echo fn_fmt($balance); ?></p>
            <small>Net margin: <?php echo round($net_margin,1); ?>%</small>
        </div>
    </div>

    <!-- Fixed costs -->
    <div class="fn-stat-card">
        <div class="fn-stat-icon ic-amber">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
        </div>
        <div class="fn-stat-content" style="flex:1;min-width:0;">
            <h3>Fixed costs / mo</h3>
            <p class="fn-stat-number" style="color:#f59e0b;"><?php echo fn_fmt($fixed); ?></p>
            <small><?php echo $stats['month_income']>0 ? round($fixed/$stats['month_income']*100,1).'% of revenue' : 'No revenue yet'; ?></small>
            <div class="fn-bar-track" style="margin-top:8px;"><div class="fn-bar-fill" style="width:<?php echo $stats['month_income']>0?min(100,round($fixed/$stats['month_income']*100)):0; ?>%;background:#f59e0b;"></div></div>
        </div>
    </div>

    <!-- Avg 3-month revenue -->
    <div class="fn-stat-card">
        <div class="fn-stat-icon ic-purple">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/><line x1="2" y1="20" x2="22" y2="20"/></svg>
        </div>
        <div class="fn-stat-content" style="flex:1;min-width:0;">
            <h3>Avg revenue (3 mo)</h3>
            <p class="fn-stat-number"><?php echo fn_fmt($avg_monthly_rev); ?></p>
            <small>Monthly rolling average</small>
        </div>
    </div>

    <!-- Expense ratio -->
    <div class="fn-stat-card">
        <?php
        $er_cls = $expense_ratio<=70 ? 'ic-green' : ($expense_ratio<=90 ? 'ic-amber' : 'ic-red');
        $er_col = $expense_ratio<=70 ? '#10b981' : ($expense_ratio<=90 ? '#f59e0b' : '#ef4444');
        ?>
        <div class="fn-stat-icon <?php echo $er_cls; ?>">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <div class="fn-stat-content" style="flex:1;min-width:0;">
            <h3>Expense ratio</h3>
            <p class="fn-stat-number" style="color:<?php echo $er_col; ?>;"><?php echo round($expense_ratio,1); ?>%</p>
            <small>Expenses ÷ Revenue</small>
            <div class="fn-bar-track" style="margin-top:8px;"><div class="fn-bar-fill" style="width:<?php echo min(100,$expense_ratio); ?>%;background:<?php echo $er_col; ?>;"></div></div>
        </div>
    </div>

    <div class="fn-stat-card">
        <div class="fn-stat-icon ic-blue">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 7h18"/><path d="M6 11h12"/><path d="M9 15h6"/><path d="M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/></svg>
        </div>
        <div class="fn-stat-content" style="flex:1;min-width:0;">
            <h3>Potential inventory revenue</h3>
            <p class="fn-stat-number" style="color:#3b82f6;"><?php echo fn_fmt($potential_revenue); ?></p>
            <small>Estimated from current sellable stock in Inventory.</small>
        </div>
    </div>

    <div class="fn-stat-card">
        <div class="fn-stat-icon ic-purple">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 1v22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div class="fn-stat-content" style="flex:1;min-width:0;">
            <h3>Unrealized profit</h3>
            <p class="fn-stat-number" style="color:<?php echo $unrealized_profit >= 0 ? '#7c3aed' : '#ef4444'; ?>;"><?php echo fn_fmt($unrealized_profit); ?></p>
            <small>Estimated profit from unpaid orders still open in Order Management.</small>
        </div>
    </div>

</div>
 
    <!-- ═══════════════════════════════════════════
         ROW 2 — Runway banner
    ════════════════════════════════════════════ -->
    <?php
    if ($balance > 0 && $monthly_burn > 0) :
        $runway_col  = $runway_months >= 6 ? '#10b981' : ($runway_months >= 3 ? '#f59e0b' : '#ef4444');
        $runway_bg   = $runway_months >= 6 ? 'rgba(16,185,129,.08)' : ($runway_months >= 3 ? 'rgba(245,158,11,.08)' : 'rgba(239,68,68,.08)');
        $runway_border = $runway_months >= 6 ? 'rgba(16,185,129,.25)' : ($runway_months >= 3 ? 'rgba(245,158,11,.25)' : 'rgba(239,68,68,.25)');
        $runway_icon = $runway_months >= 6 ? '▲' : ($runway_months >= 3 ? '●' : '▼');
        $runway_msg  = $runway_months >= 6
            ? 'Healthy runway. Current balance covers at least ' . $runway_months . ' months of fixed costs.'
            : ($runway_months >= 3
                ? 'Moderate runway. ' . $runway_months . ' months of fixed costs covered — monitor closely.'
                : 'Low runway. Balance covers only ' . $runway_months . ' month(s) of fixed costs.');
        if ($runway_cross !== null) {
            $runway_msg .= ' Projected balance turns negative in ~' . $runway_cross . ' month(s) at current trajectory.';
        } elseif ($cash_flow_status === 'positive') {
            $runway_msg .= ' Cash flow is positive — balance is projected to grow.';
        }
    ?>
    <div style="border:1px solid <?php echo $runway_border; ?>;background:<?php echo $runway_bg; ?>;border-radius:12px;padding:16px 20px;margin-bottom:20px;display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap;">
        <div style="flex:0 0 auto;text-align:center;min-width:90px;">
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--color-text-secondary);margin-bottom:4px;">Runway</div>
            <div style="font-size:30px;font-weight:600;color:<?php echo $runway_col; ?>;line-height:1.1;"><?php echo $runway_months >= 999 ? '∞' : $runway_months; ?></div>
            <div style="font-size:11px;color:var(--color-text-secondary);">months</div>
        </div>
        <div style="flex:1;min-width:200px;">
            <div style="font-size:13px;font-weight:500;color:<?php echo $runway_col; ?>;margin-bottom:4px;"><?php echo $runway_icon; ?> <?php echo $runway_months >= 6 ? 'Healthy' : ($runway_months >= 3 ? 'Watch' : 'Critical'); ?></div>
            <div style="font-size:13px;color:var(--color-text-secondary);line-height:1.5;"><?php echo $runway_msg; ?></div>
        </div>
        <div style="flex:0 0 auto;display:grid;grid-template-columns:1fr 1fr;gap:10px;min-width:220px;">
            <div style="background:var(--color-background-primary);border-radius:8px;padding:10px;border:1px solid var(--color-border-tertiary);">
                <div style="font-size:10px;text-transform:uppercase;letter-spacing:.05em;color:var(--color-text-secondary);margin-bottom:2px;">Balance</div>
                <div style="font-size:14px;font-weight:500;color:<?php echo $balance>=0?'#3b82f6':'#ef4444'; ?>;"><?php echo fn_fmt($balance); ?></div>
            </div>
            <div style="background:var(--color-background-primary);border-radius:8px;padding:10px;border:1px solid var(--color-border-tertiary);">
                <div style="font-size:10px;text-transform:uppercase;letter-spacing:.05em;color:var(--color-text-secondary);margin-bottom:2px;">Monthly burn</div>
                <div style="font-size:14px;font-weight:500;color:#f59e0b;"><?php echo fn_fmt($monthly_burn); ?></div>
            </div>
            <div style="background:var(--color-background-primary);border-radius:8px;padding:10px;border:1px solid var(--color-border-tertiary);">
                <div style="font-size:10px;text-transform:uppercase;letter-spacing:.05em;color:var(--color-text-secondary);margin-bottom:2px;">Mo. cash flow</div>
                <div style="font-size:14px;font-weight:500;color:<?php echo $monthly_net>=0?'#10b981':'#ef4444'; ?>;"><?php echo fn_fmt($monthly_net); ?></div>
            </div>
            <div style="background:var(--color-background-primary);border-radius:8px;padding:10px;border:1px solid var(--color-border-tertiary);">
                <div style="font-size:10px;text-transform:uppercase;letter-spacing:.05em;color:var(--color-text-secondary);margin-bottom:2px;">Break-even gap</div>
                <div style="font-size:14px;font-weight:500;color:<?php echo $be_gap!==null&&$be_gap>=0?'#10b981':'#ef4444'; ?>;">
                    <?php echo $be_gap !== null ? fn_fmt($be_gap) : '—'; ?>
                </div>
            </div>
            <div style="background:var(--color-background-primary);border-radius:8px;padding:10px;border:1px solid var(--color-border-tertiary);">
                <div style="font-size:10px;text-transform:uppercase;letter-spacing:.05em;color:var(--color-text-secondary);margin-bottom:2px;">Potential revenue</div>
                <div style="font-size:14px;font-weight:500;color:#3b82f6;"><?php echo fn_fmt($potential_revenue); ?></div>
            </div>
            <div style="background:var(--color-background-primary);border-radius:8px;padding:10px;border:1px solid var(--color-border-tertiary);">
                <div style="font-size:10px;text-transform:uppercase;letter-spacing:.05em;color:var(--color-text-secondary);margin-bottom:2px;">Unrealized profit</div>
                <div style="font-size:14px;font-weight:500;color:<?php echo $unrealized_profit >= 0 ? '#7c3aed' : '#ef4444'; ?>;"><?php echo fn_fmt($unrealized_profit); ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>
 
    <!-- ═══════════════════════════════════════════
         ROW 3 — Three charts
    ════════════════════════════════════════════ -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:20px;">
 
        <!-- Chart 1: Revenue vs Expense trend -->
        <div class="fn-chart-box">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                <h4 style="margin:0;font-size:12px;font-weight:500;color:var(--color-text-secondary);text-transform:uppercase;letter-spacing:.05em;">Revenue vs expenses</h4>
                <div style="display:flex;gap:10px;font-size:11px;color:var(--color-text-secondary);">
                    <span style="display:flex;align-items:center;gap:4px;"><span style="width:10px;height:3px;background:#10b981;border-radius:2px;display:inline-block;"></span>Revenue</span>
                    <span style="display:flex;align-items:center;gap:4px;"><span style="width:10px;height:3px;background:#ef4444;border-radius:2px;display:inline-block;"></span>Expenses</span>
                </div>
            </div>
            <div style="position:relative;height:200px;">
                <canvas id="fn-trend-chart"></canvas>
            </div>
        </div>
 
        <!-- Chart 2: Monthly net cash flow -->
        <div class="fn-chart-box">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                <h4 style="margin:0;font-size:12px;font-weight:500;color:var(--color-text-secondary);text-transform:uppercase;letter-spacing:.05em;">Monthly net cash flow</h4>
                <div style="display:flex;gap:10px;font-size:11px;color:var(--color-text-secondary);">
                    <span style="display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;background:rgba(16,185,129,.7);border-radius:2px;display:inline-block;"></span>Positive</span>
                    <span style="display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;background:rgba(239,68,68,.7);border-radius:2px;display:inline-block;"></span>Negative</span>
                </div>
            </div>
            <div style="position:relative;height:200px;">
                <canvas id="fn-cashflow-chart"></canvas>
            </div>
        </div>
 
        <!-- Chart 3: Risk & efficiency gauges -->
        <div class="fn-chart-box">
            <h4 style="margin:0 0 12px;font-size:12px;font-weight:500;color:var(--color-text-secondary);text-transform:uppercase;letter-spacing:.05em;">Risk &amp; efficiency</h4>
            <?php
            $gauges = [
                ['label'=>'Gross margin',     'val'=>round($gross_margin,1),  'suffix'=>'%', 'ok'=>40,  'warn'=>20,  'ok_inv'=>false, 'tip'=>'> 40% healthy'],
                ['label'=>'Net margin',       'val'=>round($net_margin,1),    'suffix'=>'%', 'ok'=>15,  'warn'=>5,   'ok_inv'=>false, 'tip'=>'> 15% healthy'],
                ['label'=>'Expense ratio',    'val'=>round($expense_ratio,1), 'suffix'=>'%', 'ok'=>70,  'warn'=>90,  'ok_inv'=>true,  'tip'=>'< 70% healthy'],
                ['label'=>'Revenue vs target','val'=>$target>0?round($stats['month_income']/$target*100,1):0, 'suffix'=>'%','ok'=>90,'warn'=>60,'ok_inv'=>false,'tip'=>'> 90% healthy'],
                ['label'=>'Fixed cost ratio', 'val'=>$stats['month_income']>0?round($fixed/$stats['month_income']*100,1):0,'suffix'=>'%','ok'=>40,'warn'=>70,'ok_inv'=>true,'tip'=>'< 40% healthy'],
            ];
            foreach ($gauges as $g) :
                $v = $g['val']; $inv = !empty($g['ok_inv']);
                if ($inv) { $col = $v<=$g['ok']?'#10b981':($v<=$g['warn']?'#f59e0b':'#ef4444'); }
                else       { $col = $v>=$g['ok']?'#10b981':($v>=$g['warn']?'#f59e0b':'#ef4444'); }
                $bar = min(100,max(0,abs($v)));
            ?>
            <div style="margin-bottom:10px;">
                <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:3px;">
                    <span style="color:var(--color-text-secondary);"><?php echo $g['label']; ?> <span style="font-size:10px;opacity:.6;"><?php echo $g['tip']; ?></span></span>
                    <span style="font-weight:500;color:<?php echo $col; ?>;"><?php echo $v.$g['suffix']; ?></span>
                </div>
                <div class="fn-bar-track"><div class="fn-bar-fill" style="width:<?php echo $bar; ?>%;background:<?php echo $col; ?>;"></div></div>
            </div>
            <?php endforeach; ?>
        </div>
 
    </div>
 
    <!-- ═══════════════════════════════════════════
         ROW 4 — Projection runway chart + Profitability
    ════════════════════════════════════════════ -->
    <div style="display:grid;grid-template-columns:1.6fr 1fr;gap:14px;margin-bottom:20px;">
 
        <!-- Projection runway chart -->
        <div class="fn-chart-box">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;flex-wrap:wrap;gap:6px;">
                <div>
                    <h4 style="margin:0 0 2px;font-size:12px;font-weight:500;color:var(--color-text-secondary);text-transform:uppercase;letter-spacing:.05em;">12-month balance projection</h4>
                    <div style="font-size:11px;color:var(--color-text-secondary);">Based on current growth rate (<?php echo $stats['growth_pct']; ?>%) &amp; fixed costs (<?php echo fn_fmt($fixed); ?>/mo)</div>
                </div>
                <div style="display:flex;gap:10px;font-size:11px;color:var(--color-text-secondary);">
                    <span style="display:flex;align-items:center;gap:4px;"><span style="width:10px;height:3px;background:#3b82f6;border-radius:2px;display:inline-block;"></span>Balance</span>
                    <span style="display:flex;align-items:center;gap:4px;"><span style="width:10px;height:3px;background:#10b981;border-radius:2px;display:inline-block;"></span>Income</span>
                    <span style="display:flex;align-items:center;gap:4px;"><span style="width:10px;height:3px;background:#ef4444;border-radius:2px;display:inline-block;"></span>Expense</span>
                </div>
            </div>
            <div style="position:relative;height:220px;">
                <canvas id="fn-runway-chart"></canvas>
            </div>
        </div>
 
        <!-- Profitability breakdown table -->
        <div class="fn-chart-box">
            <h4 style="margin:0 0 12px;font-size:12px;font-weight:500;color:var(--color-text-secondary);text-transform:uppercase;letter-spacing:.05em;">Profitability — <?php echo date('M Y'); ?></h4>
            <table class="fn-table" style="font-size:12px;">
                <thead><tr><th>Metric</th><th style="text-align:right;">Amount</th><th style="text-align:right;">%</th></tr></thead>
                <tbody>
                <?php
                $pb_rows = [
                    ['Revenue',     $stats['month_income'],  100,  '#10b981'],
                    ['Expenses',    $stats['month_expense'], $stats['month_income']>0?round($stats['month_expense']/$stats['month_income']*100,1):0, '#ef4444'],
                    ['Gross profit',$stats['month_income']-$stats['month_expense'], round($gross_margin,1), '#3b82f6'],
                    ['Fixed costs', $fixed, $stats['month_income']>0?round($fixed/$stats['month_income']*100,1):0, '#f59e0b'],
                    ['Net profit',  $stats['month_income']-$stats['month_expense']-$fixed, $stats['month_income']>0?round(($stats['month_income']-$stats['month_expense']-$fixed)/$stats['month_income']*100,1):0, '#8b5cf6'],
                ];
                foreach ($pb_rows as $r) : $col=$r[3]; ?>
                <tr>
                    <td style="color:var(--color-text-primary);"><?php echo $r[0]; ?></td>
                    <td style="text-align:right;font-weight:500;color:<?php echo $col; ?>;"><?php echo fn_fmt($r[1]); ?></td>
                    <td style="text-align:right;color:var(--color-text-secondary);"><?php echo $r[2]; ?>%</td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
 
            <?php if ($be_gap !== null) : ?>
            <div style="margin-top:12px;padding:10px;background:var(--color-background-tertiary);border-radius:8px;font-size:12px;">
                <div style="font-weight:500;margin-bottom:4px;color:var(--color-text-primary);">Break-even status</div>
                <div style="color:<?php echo $be_gap>=0?'#10b981':'#ef4444'; ?>;">
                    <?php echo $be_gap >= 0
                        ? '✓ Covering fixed costs by ' . fn_fmt($be_gap)
                        : '✗ ' . fn_fmt(abs($be_gap)) . ' short of covering fixed costs'; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
 
    </div>
 
    <!-- ═══════════════════════════════════════════
         ROW 5 — Budget vs actual + Category breakdown
    ════════════════════════════════════════════ -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;">
 
        <!-- Budget vs Actual -->
        <div class="fn-chart-box">
            <h4 style="margin:0 0 12px;font-size:12px;font-weight:500;color:var(--color-text-secondary);text-transform:uppercase;letter-spacing:.05em;">Budget vs actual — <?php echo date('M Y'); ?></h4>
            <?php if (!$budget_income && !$budget_expense) : ?>
            <p style="color:var(--color-text-secondary);font-size:13px;margin:0;">No budgets set. <a href="<?php echo add_query_arg('type','budgets',get_permalink()); ?>" style="color:#3b82f6;">Set budgets →</a></p>
            <?php else : ?>
 
            <?php if ($budget_income > 0) :
                $pi = min(100, round($stats['month_income']/$budget_income*100));
                $pi_col = $pi>=100?'#10b981':($pi>=70?'#f59e0b':'#ef4444'); ?>
            <div style="margin-bottom:12px;">
                <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:3px;">
                    <span style="color:var(--color-text-secondary);">Revenue</span>
                    <span><?php echo fn_fmt($stats['month_income']); ?> <span style="color:var(--color-text-secondary);">/ <?php echo fn_fmt($budget_income); ?></span></span>
                </div>
                <div class="fn-bar-track"><div class="fn-bar-fill" style="width:<?php echo $pi; ?>%;background:#10b981;"></div></div>
                <div style="font-size:11px;margin-top:2px;color:<?php echo $pi_col; ?>;"><?php echo $pi; ?>% achieved</div>
            </div>
            <?php endif; ?>
 
            <?php if ($budget_expense > 0) :
                $pe = min(100, round($stats['month_expense']/$budget_expense*100));
                $pe_col = $pe<=80?'#10b981':($pe<=100?'#f59e0b':'#ef4444'); ?>
            <div style="margin-bottom:12px;">
                <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:3px;">
                    <span style="color:var(--color-text-secondary);">Expenses</span>
                    <span><?php echo fn_fmt($stats['month_expense']); ?> <span style="color:var(--color-text-secondary);">/ <?php echo fn_fmt($budget_expense); ?></span></span>
                </div>
                <div class="fn-bar-track"><div class="fn-bar-fill" style="width:<?php echo $pe; ?>%;background:#ef4444;"></div></div>
                <div style="font-size:11px;margin-top:2px;color:<?php echo $pe_col; ?>;"><?php echo $pe; ?>% used</div>
            </div>
            <?php endif; ?>
 
            <?php if ($budget_income > 0 && $budget_expense > 0) :
                $budgeted_net = $budget_income - $budget_expense;
                $actual_net   = $stats['month_net'];
                $net_var      = $actual_net - $budgeted_net;
            ?>
            <div style="border-top:1px solid var(--color-border-tertiary);padding-top:10px;margin-top:4px;font-size:12px;">
                <div style="display:flex;justify-content:space-between;margin-bottom:2px;">
                    <span style="color:var(--color-text-secondary);">Budgeted net</span>
                    <span><?php echo fn_fmt($budgeted_net); ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:2px;">
                    <span style="color:var(--color-text-secondary);">Actual net</span>
                    <span style="color:<?php echo $actual_net>=0?'#10b981':'#ef4444'; ?>;"><?php echo fn_fmt($actual_net); ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;font-weight:500;">
                    <span style="color:var(--color-text-secondary);">Variance</span>
                    <span style="color:<?php echo $net_var>=0?'#10b981':'#ef4444'; ?>;"><?php echo ($net_var>=0?'+':'').fn_fmt($net_var); ?></span>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
 
        <!-- Expense category breakdown -->
        <div class="fn-chart-box">
            <h4 style="margin:0 0 12px;font-size:12px;font-weight:500;color:var(--color-text-secondary);text-transform:uppercase;letter-spacing:.05em;">Expenses by category — <?php echo date('M Y'); ?></h4>
            <?php if (empty($exp_breakdown)) : ?>
            <p style="color:var(--color-text-secondary);font-size:13px;margin:0;">No expenses recorded this month.</p>
            <?php else :
                $total_exp = array_sum(array_column((array)$exp_breakdown,'total'));
                $colors = ['#ef4444','#f59e0b','#8b5cf6','#3b82f6','#10b981','#06b6d4','#f97316','#ec4899'];
                foreach (array_slice((array)$exp_breakdown,0,6) as $idx => $cat) :
                    $pct3 = $total_exp>0?round($cat->total/$total_exp*100):0;
                    $col3 = $colors[$idx % count($colors)];
            ?>
            <div style="margin-bottom:8px;">
                <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:3px;">
                    <span style="display:flex;align-items:center;gap:6px;color:var(--color-text-primary);">
                        <span style="width:8px;height:8px;border-radius:50%;background:<?php echo $col3; ?>;flex-shrink:0;display:inline-block;"></span>
                        <?php echo esc_html($cat->category); ?>
                    </span>
                    <span style="color:var(--color-text-secondary);"><?php echo fn_fmt($cat->total); ?> <span style="font-size:10px;">(<?php echo $pct3; ?>%)</span></span>
                </div>
                <div class="fn-bar-track"><div class="fn-bar-fill" style="width:<?php echo $pct3; ?>%;background:<?php echo $col3; ?>;"></div></div>
            </div>
            <?php endforeach; ?>
            <div style="margin-top:8px;padding-top:8px;border-top:1px solid var(--color-border-tertiary);font-size:12px;display:flex;justify-content:space-between;">
                <span style="color:var(--color-text-secondary);">Total expenses</span>
                <span style="font-weight:500;color:#ef4444;"><?php echo fn_fmt($total_exp); ?></span>
            </div>
            <?php endif; ?>
        </div>
 
    </div>
 
    <!-- ═══════════════════════════════════════════
         ROW 6 — Income breakdown + Recent transactions
    ════════════════════════════════════════════ -->
    <div style="display:grid;grid-template-columns:1fr 2fr;gap:14px;margin-bottom:20px;">
 
        <!-- Income category breakdown -->
        <div class="fn-chart-box">
            <h4 style="margin:0 0 12px;font-size:12px;font-weight:500;color:var(--color-text-secondary);text-transform:uppercase;letter-spacing:.05em;">Income by category — <?php echo date('M Y'); ?></h4>
            <?php if (empty($inc_breakdown)) : ?>
            <p style="color:var(--color-text-secondary);font-size:13px;margin:0;">No income recorded this month.</p>
            <?php else :
                $total_inc2 = array_sum(array_column((array)$inc_breakdown,'total'));
                $inc_colors = ['#10b981','#3b82f6','#8b5cf6','#f59e0b','#06b6d4','#f97316'];
                foreach ((array)$inc_breakdown as $idx2 => $cat2) :
                    $pct4 = $total_inc2>0?round($cat2->total/$total_inc2*100):0;
                    $col4 = $inc_colors[$idx2 % count($inc_colors)];
            ?>
            <div style="margin-bottom:8px;">
                <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:3px;">
                    <span style="display:flex;align-items:center;gap:6px;color:var(--color-text-primary);">
                        <span style="width:8px;height:8px;border-radius:50%;background:<?php echo $col4; ?>;flex-shrink:0;display:inline-block;"></span>
                        <?php echo esc_html($cat2->category); ?>
                    </span>
                    <span style="color:var(--color-text-secondary);"><?php echo $pct4; ?>%</span>
                </div>
                <div class="fn-bar-track"><div class="fn-bar-fill" style="width:<?php echo $pct4; ?>%;background:<?php echo $col4; ?>;"></div></div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
 
        <!-- Recent transactions -->
        <div class="fn-chart-box">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <h4 style="margin:0;font-size:12px;font-weight:500;color:var(--color-text-secondary);text-transform:uppercase;letter-spacing:.05em;">Recent transactions</h4>
                <a href="<?php echo add_query_arg('type','transactions',get_permalink()); ?>" style="font-size:12px;color:#3b82f6;text-decoration:none;">View all →</a>
            </div>
            <?php
            $recent = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}fn_transactions WHERE business_id = %d ORDER BY created_at DESC LIMIT 8", $business_id));
            if (empty($recent)) : ?>
            <p style="color:var(--color-text-secondary);font-size:13px;margin:0;">No transactions yet.</p>
            <?php else : ?>
            <table class="fn-table" style="font-size:12px;">
                <thead><tr><th>Date</th><th>Type</th><th>Category</th><th>Amount</th><th>Notes</th></tr></thead>
                <tbody>
                <?php foreach ($recent as $tx) : ?>
                <tr>
                    <td style="color:var(--color-text-secondary);"><?php echo date('M d', strtotime($tx->created_at)); ?></td>
                    <td><span class="fn-badge fn-badge-<?php echo $tx->type; ?>"><?php echo ucfirst($tx->type); ?></span></td>
                    <td><?php echo esc_html($tx->category); ?></td>
                    <td style="font-weight:500;color:<?php echo $tx->type==='income'?'#10b981':'#ef4444'; ?>;"><?php echo fn_fmt($tx->amount); ?></td>
                    <td style="color:var(--color-text-secondary);"><?php echo esc_html(mb_strimwidth($tx->notes??'',0,30,'…')); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
 
    </div>
 
    <!-- Chart.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <script>
    (function(){
        var C     = '<?php echo esc_js(fn_currency()); ?>';
        var isDark= window.matchMedia('(prefers-color-scheme:dark)').matches;
        var gc    = isDark ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.06)';
        var tc    = isDark ? 'rgba(255,255,255,0.45)' : 'rgba(0,0,0,0.45)';
        var fmt   = function(v){ return C + Math.abs(v).toLocaleString('en-US',{minimumFractionDigits:0,maximumFractionDigits:0}); };
        Chart.defaults.font.family = 'system-ui,sans-serif';
        Chart.defaults.font.size   = 11;
 
        var sharedScales = function(yLabel, xLabel){
            return {
                x: {
                    grid: { color: gc },
                    ticks: { color: tc, maxRotation: 45, autoSkip: false },
                    title: { display: !!xLabel, text: xLabel||'', color: tc, font:{ size:10 } }
                },
                y: {
                    grid: { color: gc },
                    ticks: { color: tc, callback: function(v){ return C + v.toLocaleString('en-US',{maximumFractionDigits:0}); } },
                    title: { display: !!yLabel, text: yLabel||'', color: tc, font:{ size:10 } }
                }
            };
        };
 
        var sharedPlugins = function(extra){
            return Object.assign({
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(c){ return ' ' + c.dataset.label + ': ' + C + parseFloat(c.raw).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}); }
                    }
                }
            }, extra||{});
        };
 
        /* ── Chart 1: Revenue vs Expense ── */
        var labels  = [<?php echo implode(',', $labels); ?>];
        var income  = [<?php echo implode(',', $income_arr); ?>];
        var expense = [<?php echo implode(',', $expense_arr); ?>];
 
        var ctx1 = document.getElementById('fn-trend-chart');
        if(ctx1){ new Chart(ctx1, {
            type: 'line',
            data: { labels: labels, datasets: [
                { label:'Revenue', data:income,  borderColor:'#10b981', backgroundColor:'rgba(16,185,129,.1)', tension:.4, fill:true, pointRadius:3, pointHoverRadius:5 },
                { label:'Expenses',data:expense, borderColor:'#ef4444', backgroundColor:'rgba(239,68,68,.1)',  tension:.4, fill:true, pointRadius:3, pointHoverRadius:5 }
            ]},
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode:'index', intersect:false },
                plugins: sharedPlugins(),
                scales: sharedScales('Amount (<?php echo esc_js(fn_currency()); ?>)', 'Month')
            }
        }); }
 
        /* ── Chart 2: Net cash flow ── */
        var netFlow = income.map(function(v,i){ return v - expense[i]; });
        var ctx2 = document.getElementById('fn-cashflow-chart');
        if(ctx2){ new Chart(ctx2, {
            type: 'bar',
            data: { labels: labels, datasets: [
                { label:'Net cash flow', data:netFlow,
                  backgroundColor: netFlow.map(function(v){ return v>=0?'rgba(16,185,129,.75)':'rgba(239,68,68,.75)'; }),
                  borderRadius: 4, borderSkipped: false }
            ]},
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode:'index', intersect:false },
                plugins: sharedPlugins({ annotation:{ annotations:{
                    zero:{ type:'line', yMin:0, yMax:0, borderColor: isDark?'rgba(255,255,255,.2)':'rgba(0,0,0,.2)', borderWidth:1, borderDash:[4,4] }
                }}}),
                scales: Object.assign(sharedScales('Net (<?php echo esc_js(fn_currency()); ?>)', 'Month'),{
                    x: { grid:{color:gc}, ticks:{color:tc,maxRotation:45,autoSkip:false}, title:{display:true,text:'Month',color:tc,font:{size:10}} },
                    y: { grid:{color:gc}, ticks:{color:tc, callback:function(v){
                        return (v<0?'-':'')+C+Math.abs(v).toLocaleString('en-US',{maximumFractionDigits:0});
                    }}, title:{display:true,text:'Net (<?php echo esc_js(fn_currency()); ?>)',color:tc,font:{size:10}} }
                })
            }
        }); }
 
       var projLabels  = [<?php echo implode(',', $proj_labels); ?>];
        var projBalance = [<?php echo implode(',', $proj_balance); ?>];
        
        var ctx3 = document.getElementById('fn-runway-chart');
        if(ctx3){ new Chart(ctx3, {
            type: 'line',
            data: { 
                labels: projLabels, 
                datasets: [
                    { 
                        label:'Runway balance',
                        data: projBalance,
                        borderColor:'#3b82f6',
                        backgroundColor:'rgba(59,130,246,.1)',
                        tension:.3,
                        fill:true,
                        pointRadius:3,
                        pointHoverRadius:5,
                        borderWidth:2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode:'index', intersect:false },
                plugins: sharedPlugins({
                    annotation:{ annotations:{
                        zeroLine:{
                            type:'line',
                            yMin:0,
                            yMax:0,
                            borderColor:'rgba(239,68,68,.5)',
                            borderWidth:1,
                            borderDash:[5,5]
                        }
                    }}
                }),
                scales: {
                    x: {
                        grid:{color:gc},
                        ticks:{color:tc,maxRotation:45,autoSkip:false},
                        title:{display:true,text:'Month',color:tc,font:{size:10}}
                    },
                    y: {
                        grid:{color:gc},
                        ticks:{color:tc,callback:function(v){
                            return (v<0?'-':'')+C+Math.abs(v).toLocaleString();
                        }},
                        title:{display:true,text:'Balance',color:tc,font:{size:10}}
                    }
                }
            }
        }); }
 
    })();
    </script>
    <?php
    return ob_get_clean();
}

/* ============================================================
   TAB: RECURRING EXPENSES
   ============================================================ */
function fn_recurring_tab() {
    global $wpdb;
    $t        = $wpdb->prefix . 'fn_recurring_expenses';
    $bid      = fn_get_current_business_id();
    $currency = fn_currency();
    $nonce    = wp_create_nonce('bntm_fn_action');

    if (!$wpdb->get_var("SHOW TABLES LIKE '{$t}'")) bntm_fn_create_tables();
    $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$t} WHERE business_id=%d ORDER BY is_active DESC, name ASC", $bid));

    $monthly_total = fn_get_recurring_total();

    // Use expense categories from settings (same source as Transactions tab)
    $expense_cats = fn_get_categories_for_business('expense', $bid);

    ob_start(); ?>
    <?php echo fn_js_head(); ?>
    <div class="bntm-form-section">
        <div class="fn-section-hdr">
            <div>
                <h3 style="margin:0 0 4px;">Recurring expenses</h3>
                <p style="font-size:13px;color:var(--color-text-secondary);margin:0;">Monthly equivalent: <strong><?php echo fn_fmt($monthly_total); ?></strong></p>
            </div>
            <button class="bntm-btn-primary bntm-btn-small" id="fn-add-recurring-btn">+ Add expense</button>
        </div>

        <!-- Add/Edit form (hidden by default) -->
        <div id="fn-recurring-form-wrap" style="display:none;background:var(--color-background-secondary);border-radius:10px;padding:16px;margin-bottom:16px;">
            <h4 style="margin:0 0 12px;font-size:14px;" id="fn-recurring-form-title">Add recurring expense</h4>
            <form id="fn-recurring-form" class="bntm-form">
                <input type="hidden" id="fr-id" name="expense_id" value="0">
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Expense name *</label>
                        <input type="text" id="fr-name" name="name" class="fn-input" required placeholder="e.g. Rent">
                    </div>
                    <div class="bntm-form-group">
                        <label>Amount (<?php echo $currency; ?>) *</label>
                        <input type="number" id="fr-amount" name="amount" class="fn-input" step="0.01" min="0" required>
                    </div>
                    <div class="bntm-form-group">
                        <label>Category</label>
                        <select id="fr-category" name="category" class="fn-input">
                            <?php foreach ($expense_cats as $cat) : ?>
                            <option value="<?php echo esc_attr($cat); ?>"><?php echo esc_html($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="bntm-form-group">
                        <label>Frequency</label>
                        <select id="fr-freq" name="frequency" class="fn-input">
                            <option value="monthly">Monthly</option>
                            <option value="weekly">Weekly</option>
                            <option value="annual">Annual</option>
                        </select>
                    </div>
                </div>
                <input type="hidden" name="_ajax_nonce" value="<?php echo $nonce; ?>">
                <input type="hidden" name="action" value="fn_save_recurring">
                <button type="submit" class="bntm-btn-primary bntm-btn-small">Save</button>
                <button type="button" class="bntm-btn-secondary bntm-btn-small" id="fn-recurring-cancel" style="margin-left:6px;">Cancel</button>
                <div id="fn-recurring-msg" style="margin-top:6px;"></div>
            </form>
        </div>

        <?php if (empty($items)) : ?>
        <p style="color:var(--color-text-secondary);">No recurring expenses yet. Add rent, payroll, utilities, etc.</p>
        <?php else : ?>
        <table class="fn-table">
            <thead><tr><th>Name</th><th>Category</th><th>Amount</th><th>Frequency</th><th>Monthly equiv.</th><th>Active</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($items as $item) :
                $mo_equiv = $item->frequency==='monthly' ? $item->amount : ($item->frequency==='weekly' ? $item->amount*4.33 : $item->amount/12);
            ?>
            <tr style="<?php echo $item->is_active ? '' : 'opacity:.5;'; ?>">
                <td style="font-weight:500;"><?php echo esc_html($item->name); ?></td>
                <td><?php echo esc_html($item->category); ?></td>
                <td><?php echo fn_fmt($item->amount); ?></td>
                <td><?php echo ucfirst($item->frequency); ?></td>
                <td style="color:#f59e0b;"><?php echo fn_fmt($mo_equiv); ?></td>
                <td><span class="fn-badge <?php echo $item->is_active?'fn-badge-ok':'fn-badge-bad'; ?>"><?php echo $item->is_active?'Active':'Inactive'; ?></span></td>
                <td>
                    <button class="bntm-btn-small bntm-btn-secondary fn-rec-edit"
                        data-id="<?php echo $item->id; ?>"
                        data-name="<?php echo esc_attr($item->name); ?>"
                        data-amount="<?php echo $item->amount; ?>"
                        data-cat="<?php echo esc_attr($item->category); ?>"
                        data-freq="<?php echo $item->frequency; ?>">Edit</button>
                    <button class="bntm-btn-small bntm-btn-danger fn-rec-del" data-id="<?php echo $item->id; ?>">Delete</button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:600;">
                    <td colspan="4">Total monthly fixed costs</td>
                    <td style="color:#f59e0b;"><?php echo fn_fmt($monthly_total); ?></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
        <?php endif; ?>
    </div>
    <script>
    (function(){
        var wrap = document.getElementById('fn-recurring-form-wrap');

        document.getElementById('fn-add-recurring-btn').addEventListener('click', function(){
            document.getElementById('fn-recurring-form-title').textContent = 'Add recurring expense';
            document.getElementById('fr-id').value     = '0';
            document.getElementById('fr-name').value   = '';
            document.getElementById('fr-amount').value = '';
            document.getElementById('fr-category').value = document.getElementById('fr-category').options[0].value;
            document.getElementById('fr-freq').value   = 'monthly';
            wrap.style.display = 'block';
            document.getElementById('fr-name').focus();
        });

        document.getElementById('fn-recurring-cancel').addEventListener('click', function(){
            wrap.style.display = 'none';
        });

        document.querySelectorAll('.fn-rec-edit').forEach(function(btn){
            btn.addEventListener('click', function(){
                document.getElementById('fn-recurring-form-title').textContent = 'Edit expense';
                document.getElementById('fr-id').value     = this.dataset.id;
                document.getElementById('fr-name').value   = this.dataset.name;
                document.getElementById('fr-amount').value = this.dataset.amount;
                document.getElementById('fr-freq').value   = this.dataset.freq;
                // Set category select to matching option
                var sel = document.getElementById('fr-category');
                var cat = this.dataset.cat;
                var matched = false;
                for (var i = 0; i < sel.options.length; i++) {
                    if (sel.options[i].value === cat) { sel.selectedIndex = i; matched = true; break; }
                }
                // If saved category not in list, temporarily add it
                if (!matched) {
                    var opt = document.createElement('option');
                    opt.value = cat; opt.textContent = cat; opt.dataset.temp = '1';
                    sel.appendChild(opt);
                    sel.value = cat;
                }
                wrap.style.display = 'block';
                document.getElementById('fr-name').focus();
            });
        });

        document.getElementById('fn-recurring-form').addEventListener('submit', function(e){
            e.preventDefault();
            var btn = this.querySelector('button[type="submit"]'); btn.disabled = true; btn.textContent = 'Saving...';
            fetch(ajaxurl, {method:'POST', body:new FormData(this)}).then(r=>r.json()).then(j=>{
                document.getElementById('fn-recurring-msg').innerHTML = '<div class="bntm-notice bntm-notice-'+(j.success?'success':'error')+'">'+j.data+'</div>';
                btn.disabled = false; btn.textContent = 'Save';
                if (j.success) setTimeout(()=>location.reload(), 1000);
            });
        });

        document.querySelectorAll('.fn-rec-del').forEach(function(btn){
            btn.addEventListener('click', function(){
                if (!confirm('Delete this recurring expense?')) return;
                var fd = new FormData();
                fd.append('action','fn_delete_recurring');
                fd.append('expense_id', this.dataset.id);
                fd.append('_ajax_nonce', fnNonce);
                fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(j=>{
                    if (j.success) location.reload(); else alert(j.data);
                });
            });
        });
    })();
    </script>
    <?php return ob_get_clean();
}

/* ============================================================
   TAB: BUDGETS
   ============================================================ */
function fn_budgets_tab() {
    global $wpdb;
    $bt       = $wpdb->prefix . 'fn_budgets';
    $tt       = $wpdb->prefix . 'fn_transactions';
    $bid      = fn_get_current_business_id();
    $currency = fn_currency();
    $nonce    = wp_create_nonce('bntm_fn_action');
    $y        = isset($_GET['by']) ? intval($_GET['by']) : date('Y');
    $m        = isset($_GET['bm']) ? intval($_GET['bm']) : date('m');
    $period   = sprintf('%04d-%02d',$y,$m);

    if (!$wpdb->get_var("SHOW TABLES LIKE '{$bt}'")) bntm_fn_create_tables();

    $budgets = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$bt} WHERE business_id=%d AND period=%s ORDER BY budget_type, category",
        $bid, $period));
    $actuals = $wpdb->get_results($wpdb->prepare(
        "SELECT type, category, SUM(amount) as total FROM {$tt} WHERE business_id=%d AND YEAR(created_at)=%d AND MONTH(created_at)=%d GROUP BY type, category",
        $bid, $y, $m));
    $actual_map = [];
    foreach ($actuals as $a) $actual_map[$a->type][$a->category] = floatval($a->total);

    // Same category sources as Transactions tab
    $income_cats  = fn_get_categories_for_business('income', $bid);
    $expense_cats = fn_get_categories_for_business('expense', $bid);

    ob_start(); ?>
    <?php echo fn_js_head(); ?>
    <div class="bntm-form-section">
        <div class="fn-section-hdr">
            <h3>Budgets — <?php echo date('F Y',mktime(0,0,0,$m,1,$y)); ?></h3>
            <form method="GET" style="display:flex;gap:8px;align-items:center;">
                <input type="hidden" name="type" value="budgets">
                <select name="bm" class="fn-input" style="padding:5px 8px;font-size:12px;width:auto;">
                    <?php for($i=1;$i<=12;$i++): ?><option value="<?php echo $i; ?>" <?php selected($m,$i); ?>><?php echo date('F',mktime(0,0,0,$i,1)); ?></option><?php endfor; ?>
                </select>
                <select name="by" class="fn-input" style="padding:5px 8px;font-size:12px;width:auto;">
                    <?php for($yi=date('Y');$yi>=date('Y')-3;$yi--): ?><option value="<?php echo $yi; ?>" <?php selected($y,$yi); ?>><?php echo $yi; ?></option><?php endfor; ?>
                </select>
                <button type="submit" class="bntm-btn-primary bntm-btn-small">Go</button>
            </form>
        </div>

        <!-- Quick add budget row -->
        <div style="background:var(--color-background-secondary);border-radius:10px;padding:14px;margin-bottom:16px;">
            <h4 style="margin:0 0 10px;font-size:13px;">Add / update budget line</h4>
            <form id="fn-budget-form" class="bntm-form" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">
                <div class="bntm-form-group" style="margin:0;flex:1;min-width:120px;">
                    <label style="font-size:11px;">Type</label>
                    <select id="fb-type" name="budget_type" class="fn-input" style="font-size:12px;padding:6px 8px;">
                        <option value="income">Income</option>
                        <option value="expense">Expense</option>
                    </select>
                </div>
                <div class="bntm-form-group" style="margin:0;flex:2;min-width:160px;">
                    <label style="font-size:11px;">Category</label>
                    <select id="fb-cat" name="category" class="fn-input" style="font-size:12px;padding:6px 8px;">
                        <optgroup label="Income" id="fb-income-opts">
                            <?php foreach ($income_cats as $cat) : ?>
                            <option value="<?php echo esc_attr($cat); ?>"><?php echo esc_html($cat); ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Expense" id="fb-expense-opts" style="display:none;">
                            <?php foreach ($expense_cats as $cat) : ?>
                            <option value="<?php echo esc_attr($cat); ?>"><?php echo esc_html($cat); ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>
                <div class="bntm-form-group" style="margin:0;flex:1;min-width:100px;">
                    <label style="font-size:11px;">Amount</label>
                    <input type="number" name="budgeted_amount" class="fn-input" style="font-size:12px;padding:6px 8px;" step="0.01" min="0">
                </div>
                <input type="hidden" name="period" value="<?php echo $period; ?>">
                <input type="hidden" name="_ajax_nonce" value="<?php echo $nonce; ?>">
                <input type="hidden" name="action" value="fn_save_budget">
                <button type="submit" class="bntm-btn-primary bntm-btn-small">Save</button>
            </form>
            <div id="fn-budget-msg" style="margin-top:6px;"></div>
        </div>

        <?php foreach (['income'=>'Income budgets','expense'=>'Expense budgets'] as $btype=>$btitle) :
            $rows = array_filter($budgets, function($b) use($btype){ return $b->budget_type===$btype; });
        ?>
        <h4 style="margin:16px 0 8px;"><?php echo $btitle; ?></h4>
        <table class="fn-table">
            <thead><tr><th>Category</th><th>Budgeted</th><th>Actual</th><th>Variance</th><th>%</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($rows)) : ?>
            <tr><td colspan="6" style="color:var(--color-text-secondary);">No <?php echo $btype; ?> budgets set for this period.</td></tr>
            <?php else : foreach($rows as $b) :
                $actual = $actual_map[$btype][$b->category] ?? 0;
                $var    = $btype==='income' ? $actual-$b->budgeted_amount : $b->budgeted_amount-$actual;
                $pct    = $b->budgeted_amount>0 ? round($actual/$b->budgeted_amount*100) : 0;
                $col    = $var>=0 ? '#10b981' : '#ef4444';
            ?>
            <tr>
                <td><?php echo esc_html($b->category); ?></td>
                <td><?php echo fn_fmt($b->budgeted_amount); ?></td>
                <td><?php echo fn_fmt($actual); ?></td>
                <td style="color:<?php echo $col; ?>;font-weight:500;"><?php echo ($var>=0?'+':'').fn_fmt($var); ?></td>
                <td>
                    <div class="fn-bar-track" style="min-width:60px;"><div class="fn-bar-fill" style="width:<?php echo min(100,$pct); ?>%;background:<?php echo $btype==='income'?'#10b981':'#ef4444'; ?>;"></div></div>
                    <span style="font-size:11px;"><?php echo $pct; ?>%</span>
                </td>
                <td><button class="bntm-btn-small bntm-btn-danger fn-del-budget" data-id="<?php echo $b->id; ?>">Del</button></td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        <?php endforeach; ?>
    </div>
    <script>
    (function(){
        // Mirror the Transactions tab: switch category optgroup based on type
        document.getElementById('fb-type').addEventListener('change', function(){
            var isInc = this.value === 'income';
            document.getElementById('fb-income-opts').style.display  = isInc ? '' : 'none';
            document.getElementById('fb-expense-opts').style.display = isInc ? 'none' : '';
            var firstOpt = document.getElementById(isInc ? 'fb-income-opts' : 'fb-expense-opts').querySelector('option');
            if (firstOpt) firstOpt.selected = true;
        });

        document.getElementById('fn-budget-form').addEventListener('submit', function(e){
            e.preventDefault();
            var btn = this.querySelector('button[type="submit"]'); btn.disabled = true; btn.textContent = '...';
            fetch(ajaxurl, {method:'POST', body:new FormData(this)}).then(r=>r.json()).then(j=>{
                document.getElementById('fn-budget-msg').innerHTML = '<div class="bntm-notice bntm-notice-'+(j.success?'success':'error')+'">'+j.data+'</div>';
                btn.disabled = false; btn.textContent = 'Save';
                if (j.success) setTimeout(()=>location.reload(), 1000);
            });
        });

        document.querySelectorAll('.fn-del-budget').forEach(function(btn){
            btn.addEventListener('click', function(){
                if (!confirm('Delete budget line?')) return;
                var fd = new FormData();
                fd.append('action','fn_delete_budget');
                fd.append('budget_id', this.dataset.id);
                fd.append('_ajax_nonce', fnNonce);
                fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(j=>{
                    if (j.success) location.reload();
                });
            });
        });
    })();
    </script>
    <?php return ob_get_clean();
}

/* ============================================================
   TAB: TRANSACTIONS
   ============================================================ */
function fn_transactions_tab() {
    global $wpdb;
    $t        = $wpdb->prefix . 'fn_transactions';
    $business_id = fn_get_current_business_id();
    $currency = fn_currency();
    $nonce    = wp_create_nonce('bntm_fn_action');
    $per_page = 15;
    $pg       = max(1,intval($_GET['fn_page']??1));
    $offset   = ($pg-1)*$per_page;
    $filter_type = isset($_GET['ft']) ? sanitize_text_field($_GET['ft']) : '';
    $filter_cat  = isset($_GET['fc']) ? sanitize_text_field($_GET['fc']) : '';

    $where = 'business_id=%d'; $args = [$business_id];
    if ($filter_type) { $where .= ' AND type=%s'; $args[] = $filter_type; }
    if ($filter_cat)  { $where .= ' AND category=%s'; $args[] = $filter_cat; }

    $total = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t} WHERE {$where}", ...$args));
    $rows  = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$t} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d", ...array_merge($args,[$per_page,$offset])));
    $total_pages = ceil($total/$per_page);

    $income_cats  = fn_get_categories_for_business('income', $business_id);
    $expense_cats = fn_get_categories_for_business('expense', $business_id);

    ob_start(); ?>
    <?php echo fn_js_head(); ?>
    <div class="bntm-form-section">
        <div class="fn-section-hdr"><h3>Add transaction</h3></div>
        <form id="fn-txn-form" class="bntm-form">
            <input type="hidden" name="id" id="txn-id">
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>Date</label>
                    <input type="date" name="created_at" class="fn-input" required>
                </div>
                <div class="bntm-form-group">
                    <label>Type</label>
                    <select id="txn-type" name="type" class="fn-input">
                        <option value="income">Income</option>
                        <option value="expense">Expense</option>
                    </select>
                </div>
                <div class="bntm-form-group">
                    <label>Amount (<?php echo $currency; ?>)</label>
                    <input type="number" name="amount" class="fn-input" step="0.01" min="0" required>
                </div>
                <div class="bntm-form-group">
                    <label>Category</label>
                    <select id="txn-cat" name="category" class="fn-input">
                        <optgroup label="Income" id="txn-income-opts">
                            <?php foreach ($income_cats as $cat) : ?><option value="<?php echo esc_attr($cat); ?>"><?php echo esc_html($cat); ?></option><?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Expense" id="txn-expense-opts" style="display:none;">
                            <?php foreach ($expense_cats as $cat) : ?><option value="<?php echo esc_attr($cat); ?>"><?php echo esc_html($cat); ?></option><?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>
                <div class="bntm-form-group">
                    <label>Notes</label>
                    <input type="text" name="notes" class="fn-input" placeholder="Optional">
                </div>
            </div>
           <input type="hidden" name="action" id="txn-action" value="fn_save_transaction">
            <input type="hidden" name="_ajax_nonce" value="<?php echo $nonce; ?>">
            <button type="submit" class="bntm-btn-primary">Add</button>
            <div id="fn-txn-msg" style="margin-top:8px;"></div>
        </form>
    </div>

    <!-- Filter bar -->
    <div style="display:flex;gap:8px;margin:16px 0;flex-wrap:wrap;align-items:center;">
        <form method="GET" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
            <input type="hidden" name="type" value="transactions">
            <select name="ft" class="fn-input" style="padding:5px 8px;font-size:12px;width:auto;">
                <option value="">All types</option>
                <option value="income"  <?php selected($filter_type,'income'); ?>>Income</option>
                <option value="expense" <?php selected($filter_type,'expense'); ?>>Expense</option>
            </select>
            <input type="text" name="fc" value="<?php echo esc_attr($filter_cat); ?>" class="fn-input" style="padding:5px 8px;font-size:12px;width:140px;" placeholder="Filter category">
            <button type="submit" class="bntm-btn-primary bntm-btn-small">Filter</button>
            <?php if ($filter_type||$filter_cat) : ?><a href="<?php echo add_query_arg('type','transactions',get_permalink()); ?>" class="bntm-btn-secondary bntm-btn-small">Clear</a><?php endif; ?>
        </form>
        <a href="<?php echo admin_url('admin-ajax.php?action=fn_export_csv&_ajax_nonce='.$nonce); ?>" class="bntm-btn-secondary bntm-btn-small" target="_blank">Export CSV</a>
        <span style="margin-left:auto;font-size:13px;color:var(--color-text-secondary);"><?php echo $total; ?> record(s)</span>
    </div>

    <div class="bntm-form-section">
        <table class="fn-table">
            <thead><tr><th>Date</th><th>Type</th><th>Category</th><th>Amount</th><th>Notes</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($rows)) : ?>
            <tr><td colspan="6" style="text-align:center;color:var(--color-text-secondary);">No transactions found.</td></tr>
            <?php else: foreach ($rows as $tx) : ?>
            <tr>
                <td><?php echo date('M d, Y',strtotime($tx->created_at)); ?></td>
                <td><span class="fn-badge fn-badge-<?php echo $tx->type; ?>"><?php echo ucfirst($tx->type); ?></span></td>
                <td><?php echo esc_html($tx->category); ?></td>
                <td style="font-weight:500;color:<?php echo $tx->type==='income'?'#10b981':'#ef4444'; ?>;"><?php echo fn_fmt($tx->amount); ?></td>
                <td style="font-size:12px;color:var(--color-text-secondary);"><?php echo esc_html(mb_strimwidth($tx->notes??'',0,50,'…')); ?></td>
                <td>
                    <button class="bntm-btn-small fn-edit-txn" data-tx='<?php echo json_encode($tx); ?>'>Edit</button>
                    <button class="bntm-btn-small bntm-btn-danger fn-del-txn" data-id="<?php echo $tx->id; ?>">Del</button>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>

        <?php if ($total_pages>1) : ?>
        <div style="display:flex;gap:6px;margin-top:14px;flex-wrap:wrap;align-items:center;">
            <?php $base=remove_query_arg('fn_page');
            if ($pg>1): ?><a href="<?php echo esc_url(add_query_arg('fn_page',$pg-1,$base)); ?>" class="bntm-btn-secondary bntm-btn-small">← Prev</a><?php endif; ?>
            <span style="font-size:13px;color:var(--color-text-secondary);">Page <?php echo $pg; ?> of <?php echo $total_pages; ?></span>
            <?php if ($pg<$total_pages): ?><a href="<?php echo esc_url(add_query_arg('fn_page',$pg+1,$base)); ?>" class="bntm-btn-secondary bntm-btn-small">Next →</a><?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
    document.getElementById('txn-type').addEventListener('change', function(){
        var isInc = this.value === 'income';
        document.getElementById('txn-income-opts').style.display  = isInc ? '' : 'none';
        document.getElementById('txn-expense-opts').style.display = isInc ? 'none' : '';
        var firstOpt = document.getElementById(isInc ? 'txn-income-opts' : 'txn-expense-opts').querySelector('option');
        if (firstOpt) firstOpt.selected = true;
    });
    document.getElementById('fn-txn-form').addEventListener('submit', function(e){
        e.preventDefault();
        var btn = this.querySelector('button[type="submit"]'); btn.disabled = true; btn.textContent = 'Adding...';
        fetch(ajaxurl, {method:'POST', body:new FormData(this)}).then(r=>r.json()).then(j=>{
            document.getElementById('fn-txn-msg').innerHTML = '<div class="bntm-notice bntm-notice-'+(j.success?'success':'error')+'">'+j.data+'</div>';
            btn.disabled = false; btn.textContent = 'Add';
           if (j.success) { 
                this.reset(); 
                document.getElementById('txn-id').value = '';
                document.getElementById('txn-action').value = 'fn_save_transaction';
                setTimeout(()=>location.reload(), 900); 
            }
        });
    });
    document.querySelectorAll('.fn-edit-txn').forEach(function(btn){
        btn.addEventListener('click', function(){
            var tx = JSON.parse(this.dataset.tx);
    
            document.querySelector('[name="type"]').value = tx.type;
            document.querySelector('[name="amount"]').value = tx.amount;
            document.querySelector('[name="category"]').value = tx.category;
            document.querySelector('[name="notes"]').value = tx.notes || '';
            document.querySelector('[name="created_at"]').value = tx.created_at.split(' ')[0];
    
            document.getElementById('txn-id').value = tx.id;
            document.getElementById('txn-action').value = 'fn_update_transaction';
    
            window.scrollTo({top:0, behavior:'smooth'});
        });
    });
    document.querySelectorAll('.fn-del-txn').forEach(function(btn){
        btn.addEventListener('click', function(){
            if (!confirm('Delete this transaction?')) return;
            var fd = new FormData();
            fd.append('action','fn_delete_transaction');
            fd.append('id', this.dataset.id);
            fd.append('_ajax_nonce', fnNonce);
            fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(j=>{
                if (j.success) location.reload(); else alert(j.data);
            });
        });
    });
    </script>
    <?php return ob_get_clean();
}

/* ============================================================
   TAB: REPORTS
   ============================================================ */
function fn_reports_tab() {
    $currency = fn_currency();
    $y        = isset($_GET['year'])  ? intval($_GET['year'])  : date('Y');
    $m        = isset($_GET['month']) ? intval($_GET['month']) : date('m');
    $nonce    = wp_create_nonce('bntm_fn_action');

    $monthly   = fn_get_stats($y,$m);
    $breakdown = fn_get_category_breakdown('income',$y,$m);
    $exp_bdown = fn_get_category_breakdown('expense',$y,$m);

    ob_start(); ?>
    <?php echo fn_js_head(); ?>
    <div class="bntm-form-section">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:20px;">
            <h3>Financial reports — <?php echo date('F Y',mktime(0,0,0,$m,1,$y)); ?></h3>
            <button class="bntm-btn-primary bntm-btn-small" onclick="window.open('<?php echo admin_url('admin-ajax.php?action=fn_generate_pdf&month='.$m.'&year='.$y.'&_ajax_nonce='.$nonce); ?>','_blank')">Generate PDF</button>
        </div>

        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;">
            <form method="GET" style="display:flex;gap:6px;">
                <input type="hidden" name="type" value="reports">
                <select name="month" class="fn-input" style="padding:5px 8px;font-size:12px;width:auto;">
                    <?php for($i=1;$i<=12;$i++): ?><option value="<?php echo $i; ?>" <?php selected($m,$i); ?>><?php echo date('F',mktime(0,0,0,$i,1)); ?></option><?php endfor; ?>
                </select>
                <select name="year" class="fn-input" style="padding:5px 8px;font-size:12px;width:auto;">
                    <?php for($yi=date('Y');$yi>=date('Y')-5;$yi--): ?><option value="<?php echo $yi; ?>" <?php selected($y,$yi); ?>><?php echo $yi; ?></option><?php endfor; ?>
                </select>
                <button type="submit" class="bntm-btn-primary bntm-btn-small">View</button>
            </form>
        </div>

        <div class="fn-kpi-grid" style="margin-bottom:20px;">
            <div class="fn-kpi"><div class="fn-kpi-label">Total Revenue</div><div class="fn-kpi-value" style="color:#10b981;"><?php echo fn_fmt($monthly['month_income']); ?></div></div>
            <div class="fn-kpi"><div class="fn-kpi-label">Total Expenses</div><div class="fn-kpi-value" style="color:#ef4444;"><?php echo fn_fmt($monthly['month_expense']); ?></div></div>
            <div class="fn-kpi"><div class="fn-kpi-label">Net Income</div><div class="fn-kpi-value" style="color:<?php echo $monthly['month_net']>=0?'#10b981':'#ef4444'; ?>;"><?php echo fn_fmt($monthly['month_net']); ?></div></div>
        </div>

        <h4 style="margin:0 0 12px;">Income statement — <?php echo date('F Y',mktime(0,0,0,$m,1,$y)); ?></h4>
        <table class="fn-table" style="border:1px solid var(--color-border-tertiary);border-radius:8px;overflow:hidden;">
            <thead><tr><th style="width:70%;">Particulars</th><th style="text-align:right;">Amount (<?php echo $currency; ?>)</th></tr></thead>
            <tbody>
            <tr style="background:var(--color-background-secondary);"><td colspan="2" style="font-weight:600;padding:10px 12px;">REVENUE</td></tr>
            <?php if (empty($breakdown)): ?>
            <tr><td style="padding-left:24px;color:var(--color-text-secondary);">No revenue recorded</td><td style="text-align:right;">—</td></tr>
            <?php else: foreach ($breakdown as $r): ?>
            <tr><td style="padding-left:24px;"><?php echo esc_html($r->category); ?></td><td style="text-align:right;font-family:monospace;"><?php echo number_format($r->total,2); ?></td></tr>
            <?php endforeach; endif; ?>
            <tr style="border-top:1px solid var(--color-border-secondary);font-weight:600;"><td style="padding-left:24px;">Total Revenue</td><td style="text-align:right;font-family:monospace;"><?php echo number_format($monthly['month_income'],2); ?></td></tr>

            <tr style="background:var(--color-background-secondary);"><td colspan="2" style="font-weight:600;padding:10px 12px;">EXPENSES</td></tr>
            <?php if (empty($exp_bdown)): ?>
            <tr><td style="padding-left:24px;color:var(--color-text-secondary);">No expenses recorded</td><td style="text-align:right;">—</td></tr>
            <?php else: foreach ($exp_bdown as $r): ?>
            <tr><td style="padding-left:24px;"><?php echo esc_html($r->category); ?></td><td style="text-align:right;font-family:monospace;"><?php echo number_format($r->total,2); ?></td></tr>
            <?php endforeach; endif; ?>
            <tr style="border-top:1px solid var(--color-border-secondary);font-weight:600;"><td style="padding-left:24px;">Total Expenses</td><td style="text-align:right;font-family:monospace;"><?php echo number_format($monthly['month_expense'],2); ?></td></tr>

            <tr style="border-top:3px double var(--color-border-primary);font-size:15px;font-weight:700;">
                <td style="padding:14px 12px;">NET INCOME <?php echo $monthly['month_net']<0?'(LOSS)':''; ?></td>
                <td style="text-align:right;font-family:monospace;color:<?php echo $monthly['month_net']>=0?'#10b981':'#ef4444'; ?>;">
                    <?php echo $monthly['month_net']<0?'(':''; ?><?php echo $currency; ?><?php echo number_format(abs($monthly['month_net']),2); ?><?php echo $monthly['month_net']<0?')':''; ?>
                </td>
            </tr>
            </tbody>
        </table>

        <div style="margin-top:32px;display:grid;grid-template-columns:1fr 1fr;gap:40px;">
            <div style="text-align:center;"><div style="border-top:1px solid var(--color-border-primary);padding-top:8px;margin-top:50px;font-size:12px;">Prepared by</div></div>
            <div style="text-align:center;"><div style="border-top:1px solid var(--color-border-primary);padding-top:8px;margin-top:50px;font-size:12px;">Approved by</div></div>
        </div>
    </div>
    <?php return ob_get_clean();
}

/* ============================================================
   TAB: SETTINGS
   ============================================================ */
function fn_settings_tab() {
    $nonce        = wp_create_nonce('bntm_fn_action');
    $business_id  = fn_get_current_business_id();
    $income_cats  = fn_get_categories_for_business('income', $business_id);
    $expense_cats = fn_get_categories_for_business('expense', $business_id);
    ob_start(); ?>
    <?php echo fn_js_head(); ?>
    <div class="bntm-form-section">
        <h3>Transaction categories</h3>
        <p style="font-size:13px;color:var(--color-text-secondary);margin-bottom:16px;">These categories apply only to the current business across Transactions, Recurring Expenses, and Budgets.</p>
        <form id="fn-cat-form" class="bntm-form">
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>Income categories (one per line)</label>
                    <textarea name="income_categories" class="fn-input" rows="6" style="resize:vertical;"><?php echo implode("\n",$income_cats); ?></textarea>
                </div>
                <div class="bntm-form-group">
                    <label>Expense categories (one per line)</label>
                    <textarea name="expense_categories" class="fn-input" rows="6" style="resize:vertical;"><?php echo implode("\n",$expense_cats); ?></textarea>
                </div>
            </div>
            <input type="hidden" name="action" value="fn_save_categories">
            <input type="hidden" name="_ajax_nonce" value="<?php echo $nonce; ?>">
            <button type="submit" class="bntm-btn-primary">Save categories</button>
            <div id="fn-cat-msg" style="margin-top:8px;"></div>
        </form>
    </div>
    <script>
    document.getElementById('fn-cat-form').addEventListener('submit', function(e){
        e.preventDefault();
        var btn = this.querySelector('button[type="submit"]'); btn.disabled = true; btn.textContent = 'Saving...';
        fetch(ajaxurl, {method:'POST', body:new FormData(this)}).then(r=>r.json()).then(j=>{
            document.getElementById('fn-cat-msg').innerHTML = '<div class="bntm-notice bntm-notice-'+(j.success?'success':'error')+'">'+j.data+'</div>';
            btn.disabled = false; btn.textContent = 'Save categories';
        });
    });
    </script>
    <?php return ob_get_clean();
}

/* ============================================================
   AJAX HANDLERS
   ============================================================ */

function bntm_ajax_fn_save_transaction() {
    check_ajax_referer('bntm_fn_action');
    if (!is_user_logged_in()) { wp_send_json_error('Please log in.'); }
    global $wpdb;
    $business_id = fn_get_current_business_id();
    if ($business_id <= 0) { wp_send_json_error('Business context unavailable.'); }
    $result = $wpdb->insert($wpdb->prefix.'fn_transactions', [
        'rand_id'     => bntm_rand_id(),
        'business_id' => $business_id,
        'type'        => sanitize_text_field($_POST['type']),
        'amount'      => floatval($_POST['amount']),
        'category'    => sanitize_text_field($_POST['category']),
        'notes'       => sanitize_textarea_field($_POST['notes'] ?? ''),
        'created_at'  => !empty($_POST['created_at']) 
            ? sanitize_text_field($_POST['created_at']) . ' ' . current_time('H:i:s')
            : current_time('mysql'),
    ]);
    if ($result) { fn_update_cashflow_summary($business_id); wp_send_json_success('Transaction added!'); }
    else wp_send_json_error('Failed to save.');
}

function bntm_ajax_fn_update_transaction() {
    check_ajax_referer('bntm_fn_action');
    if (!is_user_logged_in()) wp_send_json_error('Unauthorized');

    global $wpdb;
    $id = intval($_POST['id']);
    $business_id = fn_get_current_business_id();

    if ($business_id <= 0) {
        wp_send_json_error('Business context unavailable.');
    }

    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}fn_transactions WHERE id = %d AND business_id = %d",
        $id,
        $business_id
    ));

    if (!$existing) {
        wp_send_json_error('Transaction not found.');
    }

    $updated = $wpdb->update(
        $wpdb->prefix.'fn_transactions',
        [
            'type'       => sanitize_text_field($_POST['type']),
            'amount'     => floatval($_POST['amount']),
            'category'   => sanitize_text_field($_POST['category']),
            'notes'      => sanitize_textarea_field($_POST['notes'] ?? ''),
            'created_at' => sanitize_text_field($_POST['created_at']) . ' ' . current_time('H:i:s'),
        ],
        ['id' => $id, 'business_id' => $business_id]
    );

    if ($updated !== false) {
        fn_update_cashflow_summary($business_id);
        wp_send_json_success('Transaction updated!');
    } else {
        wp_send_json_error('Update failed.');
    }
}

function bntm_ajax_fn_delete_transaction() {
    check_ajax_referer('bntm_fn_action');
    if (!is_user_logged_in()) { wp_send_json_error('Unauthorized'); }
    global $wpdb;
    $t   = $wpdb->prefix.'fn_transactions';
    $id  = intval($_POST['id']);
    $business_id = fn_get_current_business_id();
    if ($business_id <= 0) { wp_send_json_error('Business context unavailable.'); }
    $row = $wpdb->get_row($wpdb->prepare("SELECT reference_type FROM {$t} WHERE id=%d AND business_id=%d", $id, $business_id));
    if (!$row) { wp_send_json_error('Transaction not found.'); return; }
    if ($row && $row->reference_type) { wp_send_json_error('Cannot delete imported transactions.'); return; }
    $wpdb->delete($t, ['id'=>$id, 'business_id'=>$business_id], ['%d', '%d']);
    fn_update_cashflow_summary($business_id);
    wp_send_json_success('Deleted.');
}

function bntm_ajax_fn_save_categories() {
    check_ajax_referer('bntm_fn_action');
    if (!is_user_logged_in()) { wp_send_json_error('Unauthorized'); }
    $business_id = fn_get_current_business_id();
    fn_update_setting_for_business(
        'fn_income_categories',
        wp_json_encode(array_values(array_filter(array_map('trim', explode("\n", $_POST['income_categories'] ?? ''))))),
        $business_id
    );
    fn_update_setting_for_business(
        'fn_expense_categories',
        wp_json_encode(array_values(array_filter(array_map('trim', explode("\n", $_POST['expense_categories'] ?? ''))))),
        $business_id
    );
    wp_send_json_success('Categories saved!');
}

function bntm_ajax_fn_save_recurring() {
    check_ajax_referer('bntm_fn_action');
    if (!is_user_logged_in()) { wp_send_json_error('Unauthorized'); }
    global $wpdb;
    $t   = $wpdb->prefix.'fn_recurring_expenses';
    $bid = fn_get_current_business_id();
    $eid = intval($_POST['expense_id']??0);
    if ($bid <= 0) { wp_send_json_error('Business context unavailable.'); }
    $data = [
        'business_id' => $bid,
        'name'        => sanitize_text_field($_POST['name']),
        'amount'      => floatval($_POST['amount']),
        'category'    => sanitize_text_field($_POST['category']??'Other Expense'),
        'frequency'   => sanitize_text_field($_POST['frequency']??'monthly'),
        'is_active'   => 1,
    ];
    if ($eid > 0) {
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$t} WHERE id = %d AND business_id = %d", $eid, $bid));
        if (!$existing) { wp_send_json_error('Recurring expense not found.'); }
        $wpdb->update($t, $data, ['id'=>$eid, 'business_id'=>$bid], null, ['%d', '%d']);
        wp_send_json_success('Updated!');
    } else {
        $data['rand_id'] = bntm_rand_id();
        $wpdb->insert($t, $data);
        wp_send_json_success('Added!');
    }
}

function bntm_ajax_fn_delete_recurring() {
    check_ajax_referer('bntm_fn_action');
    if (!is_user_logged_in()) { wp_send_json_error('Unauthorized'); }
    global $wpdb;
    $business_id = fn_get_current_business_id();
    if ($business_id <= 0) { wp_send_json_error('Business context unavailable.'); }
    $wpdb->delete($wpdb->prefix.'fn_recurring_expenses', [
        'id'          => intval($_POST['expense_id']),
        'business_id' => $business_id,
    ], ['%d', '%d']);
    wp_send_json_success('Deleted.');
}

function bntm_ajax_fn_save_budget() {
    check_ajax_referer('bntm_fn_action');
    if (!is_user_logged_in()) { wp_send_json_error('Unauthorized'); }
    global $wpdb;
    $t      = $wpdb->prefix.'fn_budgets';
    $bid    = fn_get_current_business_id();
    $period = sanitize_text_field($_POST['period']);
    $cat    = sanitize_text_field($_POST['category']);
    $type   = sanitize_text_field($_POST['budget_type']);
    $amt    = floatval($_POST['budgeted_amount']);
    if ($bid <= 0) { wp_send_json_error('Business context unavailable.'); }
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$t} WHERE business_id=%d AND period=%s AND category=%s AND budget_type=%s",
        $bid, $period, $cat, $type));
    if ($exists) {
        $wpdb->update($t, ['budgeted_amount'=>$amt], ['id'=>$exists, 'business_id'=>$bid], null, ['%d', '%d']);
        wp_send_json_success('Budget updated!');
    } else {
        $wpdb->insert($t, ['rand_id'=>bntm_rand_id(),'business_id'=>$bid,'period'=>$period,'category'=>$cat,'budget_type'=>$type,'budgeted_amount'=>$amt]);
        wp_send_json_success('Budget saved!');
    }
}

function bntm_ajax_fn_delete_budget() {
    check_ajax_referer('bntm_fn_action');
    if (!is_user_logged_in()) { wp_send_json_error('Unauthorized'); }
    global $wpdb;
    $business_id = fn_get_current_business_id();
    if ($business_id <= 0) { wp_send_json_error('Business context unavailable.'); }
    $wpdb->delete($wpdb->prefix.'fn_budgets', [
        'id'          => intval($_POST['budget_id']),
        'business_id' => $business_id,
    ], ['%d', '%d']);
    wp_send_json_success('Deleted.');
}

function bntm_ajax_fn_generate_pdf() {
    check_ajax_referer('bntm_fn_action');
    if (!is_user_logged_in()) wp_die('Unauthorized');
    $y       = intval($_GET['year']??date('Y'));
    $m       = intval($_GET['month']??date('m'));
    $stats   = fn_get_stats($y,$m);
    $bdown   = fn_get_category_breakdown('income',$y,$m);
    $edown   = fn_get_category_breakdown('expense',$y,$m);
    $company = bntm_get_setting('fn_biz_name', get_bloginfo('name'));
    $currency= fn_currency();
    $period  = date('F Y',mktime(0,0,0,$m,1,$y));
    ob_start(); ?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
@page{margin:2cm}body{font-family:Arial,sans-serif;font-size:11pt;color:#000}
.hdr{text-align:center;margin-bottom:24px;padding-bottom:12px;border-bottom:2px solid #000}
.hdr h1{margin:0 0 4px;font-size:18pt}table{width:100%;border-collapse:collapse;margin:16px 0}
th{background:#f0f0f0;padding:9px;text-align:left;border-bottom:2px solid #000;font-weight:700}
td{padding:7px 9px;border-bottom:1px solid #ddd}.amt{text-align:right;font-family:'Courier New',monospace}
.sh{background:#f5f5f5;font-weight:700;border-top:2px solid #bbb;border-bottom:1px solid #bbb}
.sub td{font-weight:700;border-top:1px solid #bbb}.tot td{font-size:13pt;font-weight:700;border-top:3px double #000;border-bottom:3px double #000}
.sigs{display:table;width:100%;margin-top:50px}.sc{display:table-cell;width:50%;text-align:center}.sl{border-top:1px solid #000;margin-top:50px;padding-top:6px;font-size:10pt}
.ftr{margin-top:30px;padding-top:10px;border-top:1px solid #ddd;font-size:9pt;color:#777}
</style></head><body>
<div class="hdr"><h1><?php echo esc_html($company); ?></h1><h2 style="font-size:14pt;font-weight:normal;">INCOME STATEMENT</h2>
<p>For the Month of <?php echo $period; ?></p><?php if($currency==='PHP'):?><p style="font-size:9pt;color:#666;">In accordance with Philippine Financial Reporting Standards (PFRS)<br>All amounts in Philippine Pesos (PHP)</p><?php endif;?></div>
<table>
<thead><tr><th style="width:70%;">Particulars</th><th class="amt">Amount (<?php echo $currency;?>)</th></tr></thead>
<tbody>
<tr class="sh"><td colspan="2">REVENUE</td></tr>
<?php if(empty($bdown)):?><tr><td style="padding-left:20px;color:#888;">No revenue recorded</td><td class="amt">—</td></tr>
<?php else:foreach($bdown as $r):?><tr><td style="padding-left:20px;"><?php echo esc_html($r->category);?></td><td class="amt"><?php echo number_format($r->total,2);?></td></tr><?php endforeach;endif;?>
<tr class="sub"><td style="padding-left:20px;">Total Revenue</td><td class="amt"><?php echo number_format($stats['month_income'],2);?></td></tr>
<tr class="sh"><td colspan="2">EXPENSES</td></tr>
<?php if(empty($edown)):?><tr><td style="padding-left:20px;color:#888;">No expenses recorded</td><td class="amt">—</td></tr>
<?php else:foreach($edown as $r):?><tr><td style="padding-left:20px;"><?php echo esc_html($r->category);?></td><td class="amt"><?php echo number_format($r->total,2);?></td></tr><?php endforeach;endif;?>
<tr class="sub"><td style="padding-left:20px;">Total Expenses</td><td class="amt"><?php echo number_format($stats['month_expense'],2);?></td></tr>
<tr class="tot"><td>NET INCOME <?php echo $stats['month_net']<0?'(LOSS)':'';?></td><td class="amt"><?php echo $stats['month_net']<0?'(':'';?><?php echo number_format(abs($stats['month_net']),2);?><?php echo $stats['month_net']<0?')':'';?></td></tr>
</tbody></table>
<div class="sigs"><div class="sc"><div class="sl">Prepared by</div></div><div class="sc"><div class="sl">Approved by</div></div></div>
<div class="ftr"><p>Generated: <?php echo date('F d, Y h:i A');?></p><p>BNTM Hub Finance Management System</p></div>
<script>window.print();</script></body></html>
    <?php
    $html = ob_get_clean();
    header('Content-Type:text/html;charset=UTF-8');
    echo $html;
    exit;
}

function bntm_ajax_fn_export_csv() {
    check_ajax_referer('bntm_fn_action');
    if (!is_user_logged_in()) wp_die('Unauthorized');
    global $wpdb;
    $bid  = fn_get_current_business_id();
    if ($bid <= 0) wp_die('Business context unavailable');
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}fn_transactions WHERE business_id=%d ORDER BY created_at DESC", $bid));
    header('Content-Type:text/csv;charset=utf-8');
    header('Content-Disposition:attachment;filename=finance_export_'.date('Y-m-d').'.csv');
    $out = fopen('php://output','w');
    fputcsv($out,['Date','Type','Category','Amount','Notes','Reference']);
    foreach ($rows as $tx) {
        fputcsv($out,[
            date('Y-m-d H:i:s',strtotime($tx->created_at)),
            ucfirst($tx->type),
            $tx->category,
            number_format($tx->amount,2),
            $tx->notes,
            $tx->reference_type ? $tx->reference_type.'#'.$tx->reference_id : '',
        ]);
    }
    fclose($out);
    exit;
}

/* ============================================================
   CRON
   ============================================================ */
add_action('bntm_fn_update_summary_cron','fn_update_cashflow_summary');
register_activation_hook(__FILE__,'bntm_fn_activate');
function bntm_fn_activate(){
    if(!wp_next_scheduled('bntm_fn_update_summary_cron'))
        wp_schedule_event(time(),'daily','bntm_fn_update_summary_cron');
}
register_deactivation_hook(__FILE__,'bntm_fn_deactivate');
function bntm_fn_deactivate(){
    wp_clear_scheduled_hook('bntm_fn_update_summary_cron');
}