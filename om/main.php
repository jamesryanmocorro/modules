<?php
/**
 * Module Name: Order Management
 * Module Slug: om
 * Description: Comprehensive order transaction management with inventory synchronization, finance export, printable order documents, and operational dashboards.
 * Version: 1.0.0
 * Author: BNTM
 * Icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3h8l5 5v11a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><path d="M16 3v5h5"/><path d="M10 12h8"/><path d="M10 16h8"/><path d="m7 12 1.5 1.5L10 11"/><path d="m7 16 1.5 1.5L10 15"/></svg>
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Module constants
define('BNTM_OM_PATH', dirname(__FILE__) . '/');
define('BNTM_OM_URL', plugin_dir_url(__FILE__));

if (!function_exists('om_current_user_can_manage_business')) {
    function om_current_user_can_manage_business() {
        if (!is_user_logged_in()) {
            return false;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        $role = function_exists('bntm_get_user_role') ? bntm_get_user_role(get_current_user_id()) : '';
        return in_array($role, ['owner', 'manager'], true);
    }
}

if (!function_exists('om_get_current_business_id')) {
    function om_get_current_business_id($user_id = 0) {
        $user_id = absint($user_id ?: get_current_user_id());

        if ($user_id > 0 && function_exists('bntm_get_user_business_id')) {
            $business_id = absint(bntm_get_user_business_id($user_id));
            if ($business_id > 0) {
                return $business_id;
            }
        }

        if ($user_id > 0) {
            $business_id = absint(get_user_meta($user_id, 'bntm_business_id', true));
            if ($business_id > 0) {
                return $business_id;
            }
        }

        if (function_exists('bntm_get_current_business_id')) {
            return absint(bntm_get_current_business_id());
        }

        return 0;
    }
}

if (!function_exists('om_get_setting_for_business')) {
    function om_get_setting_for_business($key, $default = '', $business_id = 0) {
        $business_id = absint($business_id ?: om_get_current_business_id());

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
}

if (!function_exists('om_update_setting_for_business')) {
    function om_update_setting_for_business($key, $value, $business_id = 0) {
        $business_id = absint($business_id ?: om_get_current_business_id());

        if ($business_id > 0 && function_exists('bntm_get_scoped_setting_option_key')) {
            return update_option(bntm_get_scoped_setting_option_key($key, $business_id), $value);
        }

        if (function_exists('bntm_set_setting')) {
            return bntm_set_setting($key, $value);
        }

        return update_option($key, $value);
    }
}

if (!function_exists('om_get_business_team_users')) {
    function om_get_business_team_users($business_id = 0) {
        $business_id = absint($business_id ?: om_get_current_business_id());
        if ($business_id <= 0) {
            return [];
        }

        $users = get_users([
            'meta_key' => 'bntm_business_id',
            'meta_value' => $business_id,
            'orderby' => 'display_name',
            'order' => 'ASC',
            'fields' => ['ID', 'display_name'],
        ]);

        return is_array($users) ? $users : [];
    }
}

/* ---------- MODULE CONFIGURATION ---------- */

function bntm_om_get_pages() {
    return [
        'Order Management'           => '[om_dashboard]',
        'Order Transaction Document' => '[om_order_view]',
        'Order Lookup'               => '[om_order_lookup]',
    ];
}

function bntm_om_get_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $prefix  = $wpdb->prefix;

    return [
        'om_orders' => "CREATE TABLE {$prefix}om_orders (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            order_number VARCHAR(50) UNIQUE NOT NULL,
            transaction_category VARCHAR(100) NOT NULL,
            customer_name VARCHAR(255) NULL,
            customer_email VARCHAR(255) NULL,
            customer_phone VARCHAR(50) NULL,
            customer_company VARCHAR(255) NULL,
            processor_user_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'open',
            payment_status VARCHAR(50) NOT NULL DEFAULT 'unpaid',
            fulfillment_status VARCHAR(50) NOT NULL DEFAULT 'pending',
            subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
            discount DECIMAL(12,2) NOT NULL DEFAULT 0,
            tax DECIMAL(12,2) NOT NULL DEFAULT 0,
            shipping_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
            total DECIMAL(12,2) NOT NULL DEFAULT 0,
            inventory_sync_mode VARCHAR(50) NOT NULL DEFAULT 'on_completed',
            finance_export_status VARCHAR(50) NOT NULL DEFAULT 'not_exported',
            source_module VARCHAR(50) DEFAULT 'manual',
            source_reference VARCHAR(100) NULL,
            notes TEXT NULL,
            internal_notes TEXT NULL,
            ordered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            due_date DATE NULL,
            completed_at DATETIME NULL,
            printed_at DATETIME NULL,
            stock_deducted_at DATETIME NULL,
            status_changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_status (status),
            INDEX idx_payment_status (payment_status),
            INDEX idx_processor (processor_user_id),
            INDEX idx_category (transaction_category),
            INDEX idx_finance_export (finance_export_status)
        ) {$charset};",

        'om_order_items' => "CREATE TABLE {$prefix}om_order_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            order_id BIGINT UNSIGNED NOT NULL,
            inventory_product_id BIGINT UNSIGNED DEFAULT NULL,
            product_rand_id VARCHAR(20) NULL,
            product_name VARCHAR(255) NOT NULL,
            sku VARCHAR(100) NULL,
            unit VARCHAR(50) NULL,
            quantity INT NOT NULL DEFAULT 1,
            available_stock INT NOT NULL DEFAULT 0,
            unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
            line_discount DECIMAL(12,2) NOT NULL DEFAULT 0,
            line_tax DECIMAL(12,2) NOT NULL DEFAULT 0,
            line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
            notes TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_order (order_id),
            INDEX idx_inventory_product (inventory_product_id),
            INDEX idx_product_rand (product_rand_id)
        ) {$charset};",

        'om_inventory_items' => "CREATE TABLE {$prefix}om_inventory_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            inventory_product_id BIGINT UNSIGNED NOT NULL,
            product_rand_id VARCHAR(20) NOT NULL,
            name VARCHAR(255) NOT NULL,
            sku VARCHAR(100) NULL,
            category VARCHAR(100) NULL,
            price DECIMAL(12,2) NOT NULL DEFAULT 0,
            stock INT NOT NULL DEFAULT 0,
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            last_synced_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_inventory_product (inventory_product_id),
            INDEX idx_product_rand (product_rand_id),
            INDEX idx_status (status)
        ) {$charset};",

        'om_order_status_logs' => "CREATE TABLE {$prefix}om_order_status_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            order_id BIGINT UNSIGNED NOT NULL,
            old_status VARCHAR(50) NULL,
            new_status VARCHAR(50) NOT NULL,
            changed_by BIGINT UNSIGNED NOT NULL,
            remarks TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_order (order_id),
            INDEX idx_status (new_status),
            INDEX idx_changed_by (changed_by)
        ) {$charset};",

        'om_finance_exports' => "CREATE TABLE {$prefix}om_finance_exports (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            order_id BIGINT UNSIGNED NOT NULL,
            fn_transaction_id BIGINT UNSIGNED DEFAULT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'exported',
            export_type VARCHAR(50) NOT NULL DEFAULT 'income',
            amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            exported_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            reverted_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_order (order_id),
            INDEX idx_fn_transaction (fn_transaction_id),
            INDEX idx_status (status)
        ) {$charset};",

        'om_categories' => "CREATE TABLE {$prefix}om_categories (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            name VARCHAR(100) NOT NULL,
            description TEXT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_status (status),
            INDEX idx_sort (sort_order)
        ) {$charset};",
    ];
}

function bntm_om_get_shortcodes() {
    return [
        'om_dashboard'    => 'bntm_shortcode_om',
        'om_order_view'   => 'bntm_shortcode_om_order_view',
        'om_order_lookup' => 'bntm_shortcode_om_order_lookup',
    ];
}

function bntm_om_create_tables() {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $tables = bntm_om_get_tables();
    foreach ($tables as $sql) {
        dbDelta($sql);
    }
    om_ensure_default_categories(om_get_current_business_id());
    return count($tables);
}

/* ---------- AJAX ACTION HOOKS ---------- */

add_action('wp_ajax_om_save_order', 'bntm_ajax_om_save_order');
add_action('wp_ajax_om_delete_order', 'bntm_ajax_om_delete_order');
add_action('wp_ajax_om_get_order', 'bntm_ajax_om_get_order');
add_action('wp_ajax_om_change_order_status', 'bntm_ajax_om_change_order_status');
add_action('wp_ajax_om_duplicate_order', 'bntm_ajax_om_duplicate_order');
add_action('wp_ajax_om_search_products', 'bntm_ajax_om_search_products');
add_action('wp_ajax_om_import_items', 'bntm_ajax_om_import_items');
add_action('wp_ajax_om_refresh_stock', 'bntm_ajax_om_refresh_stock');
add_action('wp_ajax_om_save_categories', 'bntm_ajax_om_save_categories');
add_action('wp_ajax_om_save_settings', 'bntm_ajax_om_save_settings');
add_action('wp_ajax_om_export_order', 'bntm_ajax_om_export_order');
add_action('wp_ajax_om_revert_order_export', 'bntm_ajax_om_revert_order_export');
add_action('wp_ajax_om_mark_printed', 'bntm_ajax_om_mark_printed');

/* ---------- MAIN DASHBOARD SHORTCODE ---------- */

function bntm_shortcode_om() {
    if (!om_current_user_can_manage_business()) {
        return '<div class="bntm-notice">Please log in.</div>';
    }

    $business_id  = om_get_current_business_id();
    if ($business_id <= 0) {
        return '<div class="bntm-notice">Business context is unavailable.</div>';
    }

    $active_tab   = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
    $nonce        = wp_create_nonce('om_nonce');

    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    var omNonce = '<?php echo esc_js($nonce); ?>';
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <div class="bntm-om-container">
        <div class="bntm-tabs">
            <a href="?tab=overview" class="bntm-tab <?php echo $active_tab === 'overview' ? 'active' : ''; ?>">Overview</a>
            <a href="?tab=orders" class="bntm-tab <?php echo $active_tab === 'orders' ? 'active' : ''; ?>">Orders</a>
            <?php if (bntm_is_module_enabled('in') && bntm_is_module_visible('in')): ?>
            <a href="?tab=inventory" class="bntm-tab <?php echo $active_tab === 'inventory' ? 'active' : ''; ?>">Inventory Sync</a>
            <?php endif; ?>
            <?php if (bntm_is_module_enabled('fn') && bntm_is_module_visible('fn')): ?>
            <a href="?tab=finance" class="bntm-tab <?php echo $active_tab === 'finance' ? 'active' : ''; ?>">Finance Export</a>
            <?php endif; ?>
            <a href="?tab=settings" class="bntm-tab <?php echo $active_tab === 'settings' ? 'active' : ''; ?>">Settings</a>
        </div>

        <div class="bntm-tab-content">
            <?php if ($active_tab === 'overview'): ?>
                <?php echo om_overview_tab($business_id); ?>
            <?php elseif ($active_tab === 'orders'): ?>
                <?php echo om_orders_tab($business_id); ?>
            <?php elseif ($active_tab === 'inventory'): ?>
                <?php echo om_inventory_tab($business_id); ?>
            <?php elseif ($active_tab === 'finance'): ?>
                <?php echo om_finance_tab($business_id); ?>
            <?php elseif ($active_tab === 'settings'): ?>
                <?php echo om_settings_tab($business_id); ?>
            <?php endif; ?>
        </div>
    </div>

    <style>
    .bntm-om-container .bntm-form-section{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:20px;margin-bottom:20px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
    .bntm-om-container .bntm-table-wrapper{overflow:auto;border:1px solid #e5e7eb;border-radius:14px;background:#fff}
    .bntm-om-container .bntm-table{width:100%;border-collapse:collapse}
    .bntm-om-container .bntm-table th,.bntm-om-container .bntm-table td{padding:12px 14px;border-bottom:1px solid #f1f5f9;font-size:13px;vertical-align:top}
    .bntm-om-container .bntm-table th{background:#f8fafc;font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#64748b}
    .bntm-om-container .bntm-stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:20px}
    .bntm-om-container .bntm-stat-card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:18px;display:flex;gap:14px;align-items:flex-start;box-shadow:0 1px 2px rgba(0,0,0,.04)}
    .bntm-om-container .stat-icon{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .bntm-om-container .stat-content h3{margin:0 0 6px;font-size:13px;color:#64748b;font-weight:600}
    .bntm-om-container .stat-number{margin:0;font-size:28px;color:#0f172a;font-weight:700;line-height:1.1}
    .bntm-om-container .stat-label{font-size:12px;color:#94a3b8}
    .om-grid-2{display:grid;grid-template-columns:1.5fr 1fr;gap:20px}
    .om-grid-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}
    .om-header-row,.om-section-head{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
    .om-quick-actions{display:flex;gap:10px;flex-wrap:wrap}
    .om-kicker{display:inline-flex;align-items:center;gap:8px;padding:6px 10px;background:#eff6ff;color:#1d4ed8;border-radius:999px;font-size:12px;font-weight:700}
    .om-badge{display:inline-flex;align-items:center;padding:5px 10px;border-radius:999px;font-size:11px;font-weight:700;white-space:nowrap}
    .om-badge-open{background:#e2e8f0;color:#334155}.om-badge-pending{background:#dbeafe;color:#1d4ed8}.om-badge-processing{background:#fef3c7;color:#b45309}.om-badge-completed{background:#dcfce7;color:#15803d}.om-badge-cancelled{background:#fee2e2;color:#b91c1c}
    .om-badge-unpaid{background:#f8fafc;color:#475569}.om-badge-partially_paid{background:#ffedd5;color:#c2410c}.om-badge-paid{background:#dcfce7;color:#15803d}
    .om-badge-exported{background:#dcfce7;color:#166534}.om-badge-not_exported{background:#f8fafc;color:#475569}
    .om-inline-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
    .om-field{display:flex;flex-direction:column;gap:6px}
    .om-field label{font-size:12px;color:#475569;font-weight:600}
    .om-field input,.om-field select,.om-field textarea{width:100%;padding:10px 12px;border:1px solid #dbe2ea;border-radius:10px;font-size:13px;background:#fff}
    .om-field textarea{min-height:92px;resize:vertical}
    .om-modal{display:none;position:fixed;inset:0;z-index:10000;background:rgba(15,23,42,.55);padding:20px;overflow:auto}
    .om-modal-content{max-width:1120px;margin:0 auto;background:#fff;border-radius:18px;box-shadow:0 24px 48px rgba(15,23,42,.24);overflow:hidden}
    .om-modal-header{display:flex;justify-content:space-between;align-items:center;padding:18px 22px;border-bottom:1px solid #e5e7eb;background:#f8fafc}
    .om-modal-body{padding:22px}
    .om-modal-close{border:none;background:transparent;font-size:26px;line-height:1;cursor:pointer;color:#64748b}
    .om-lines{display:flex;flex-direction:column;gap:12px}
    .om-line-row{display:grid;grid-template-columns:2fr .8fr .9fr .9fr .9fr auto;gap:10px;align-items:end;background:#f8fafc;padding:12px;border:1px solid #e5e7eb;border-radius:12px}
    .om-line-meta{font-size:11px;color:#64748b}
    .om-chart-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px}
    .om-chart-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px}
    .om-chart-card h3{margin:0 0 16px;font-size:15px;color:#0f172a}
    .om-chart-box{height:280px}
    .om-insight-list{display:grid;gap:12px}
    .om-insight{display:flex;justify-content:space-between;gap:12px;padding:12px;border:1px solid #e5e7eb;border-radius:12px;background:#fff}
    .om-page-card-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px}
    .om-page-card{border:1px solid #e5e7eb;border-radius:14px;padding:16px;background:#fff}
    .om-empty{padding:30px;color:#64748b;text-align:center}
    .om-toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px}
    .om-toolbar form{display:flex;gap:8px;flex-wrap:wrap}
    .om-notice-target{margin-top:12px}
    @media (max-width: 980px){.om-grid-2,.om-chart-grid,.om-inline-fields,.om-grid-3{grid-template-columns:1fr}.om-line-row{grid-template-columns:1fr 1fr}.om-modal{padding:10px}}
    </style>

    <script>
    (function() {
        window.omToast = function(targetId, message, success) {
            var target = document.getElementById(targetId);
            if (!target) return;
            target.innerHTML = '<div class="bntm-notice ' + (success ? 'bntm-notice-success' : 'bntm-notice-error') + '">' + message + '</div>';
        };
        window.omOpenModal = function(id) {
            var modal = document.getElementById(id);
            if (modal) modal.style.display = 'block';
        };
        window.omCloseModal = function(id) {
            var modal = document.getElementById(id);
            if (modal) modal.style.display = 'none';
        };
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('om-modal')) {
                e.target.style.display = 'none';
            }
            if (e.target.classList.contains('om-modal-close') && e.target.dataset.modal) {
                omCloseModal(e.target.dataset.modal);
            }
        });
    })();
    </script>
    <?php
    $content = ob_get_clean();
    return bntm_universal_container('Order Management', $content);
}

/* ---------- TAB RENDERING FUNCTIONS ---------- */

function om_overview_tab($business_id) {
    global $wpdb;

    $orders_table   = $wpdb->prefix . 'om_orders';
    $items_table    = $wpdb->prefix . 'om_order_items';
    $sync_table     = $wpdb->prefix . 'om_inventory_items';
    $stats          = om_get_stats($business_id);
    $monthly_trend  = $wpdb->get_results($wpdb->prepare(
        "SELECT DATE_FORMAT(ordered_at, '%%Y-%%m') as ym,
                COUNT(*) as order_count,
                COALESCE(SUM(total),0) as revenue
         FROM {$orders_table}
         WHERE business_id = %d
           AND ordered_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         GROUP BY ym ORDER BY ym ASC",
        $business_id
    ));
    $status_mix = $wpdb->get_results($wpdb->prepare(
        "SELECT status, COUNT(*) as count FROM {$orders_table}
         WHERE business_id = %d
         GROUP BY status ORDER BY count DESC",
        $business_id
    ));
    $top_products = $wpdb->get_results($wpdb->prepare(
        "SELECT product_name, SUM(quantity) as qty, SUM(line_total) as amount
         FROM {$items_table}
         WHERE business_id = %d
         GROUP BY product_name
         ORDER BY qty DESC
         LIMIT 5",
        $business_id
    ));
    $processor_stats = $wpdb->get_results($wpdb->prepare(
        "SELECT u.display_name, COUNT(o.id) as order_count, COALESCE(SUM(o.total),0) as total_amount
         FROM {$orders_table} o
         LEFT JOIN {$wpdb->users} u ON u.ID = o.processor_user_id
         WHERE o.business_id = %d
         GROUP BY o.processor_user_id
         ORDER BY order_count DESC
         LIMIT 5",
        $business_id
    ));
    $low_stock = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$sync_table} WHERE business_id = %d AND stock <= 5 ORDER BY stock ASC LIMIT 6",
        $business_id
    ));
    $recent_orders = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$orders_table} WHERE business_id = %d ORDER BY ordered_at DESC LIMIT 6",
        $business_id
    ));

    ob_start();
    ?>
   <style>
    .om-stat-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:16px; margin-bottom:24px; }
    .om-stat-card { background:#fff; padding:20px; border-radius:10px; display:flex; align-items:flex-start; gap:14px; border:1px solid #e5e7eb; transition:box-shadow .2s; }
    .om-stat-card:hover { box-shadow:0 4px 12px rgba(0,0,0,.07); }
    .om-stat-icon { width:44px; height:44px; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .om-stat-content h3 { margin:0 0 4px; font-size:11px; color:#6b7280; text-transform:uppercase; letter-spacing:.06em; font-weight:600; }
    .om-stat-number { font-size:22px; font-weight:700; color:#111827; margin:0 0 2px; line-height:1.1; }
    .om-stat-content small { color:#9ca3af; font-size:11px; }
    </style>

    <div class="om-stat-grid">
    
        <!-- Total Orders -->
        <div class="om-stat-card">
            <div class="om-stat-icon" style="background:#eff6ff;color:#2563eb;">
                <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2Z"/></svg>
            </div>
            <div class="om-stat-content">
                <h3>Total Orders</h3>
                <p class="om-stat-number"><?php echo number_format($stats['total_orders']); ?></p>
                <small><?php echo number_format($stats['completed_orders']); ?> completed</small>
            </div>
        </div>
    
        <!-- Total Revenue -->
        <div class="om-stat-card">
            <div class="om-stat-icon" style="background:#ecfdf5;color:#059669;">
                <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-3.314 0-6 1.79-6 4s2.686 4 6 4 6-1.79 6-4-2.686-4-6-4Zm0 0V5m0 11v3"/></svg>
            </div>
            <div class="om-stat-content">
                <h3>Total Revenue</h3>
                <p class="om-stat-number"><?php echo esc_html(om_format_price($stats['total_revenue'])); ?></p>
                <small><?php echo esc_html(om_format_price($stats['avg_order_value'])); ?> average order</small>
            </div>
        </div>
    
        <!-- Active Queue -->
        <div class="om-stat-card">
            <div class="om-stat-icon" style="background:#fffbeb;color:#d97706;">
                <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
            </div>
            <div class="om-stat-content">
                <h3>Active Queue</h3>
                <p class="om-stat-number"><?php echo number_format($stats['active_queue']); ?></p>
                <small><?php echo number_format($stats['pending_orders']); ?> pending review</small>
            </div>
        </div>
    
        <!-- Synced Products -->
        <div class="om-stat-card">
            <div class="om-stat-icon" style="background:<?php echo $stats['low_stock_items']>0?'#fef2f2':'#ecfdf5'; ?>;color:<?php echo $stats['low_stock_items']>0?'#dc2626':'#059669'; ?>;">
                <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M6 7l1 13h10l1-13M10 11v5m4-5v5M9 7V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3"/></svg>
            </div>
            <div class="om-stat-content">
                <h3>Synced Products</h3>
                <p class="om-stat-number"><?php echo number_format($stats['synced_products']); ?></p>
                <small><?php echo number_format($stats['low_stock_items']); ?> low stock flags</small>
            </div>
        </div>
    
    </div>

    <div class="bntm-form-section">
        <div class="om-header-row">
            <div>
                <span class="om-kicker">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                    Quick and Deep Order Insight
                </span>
                <h3 style="margin:10px 0 0;">Operational command view</h3>
                <p style="margin:6px 0 0;color:#64748b;">Track order load, stock impact, processor output, and revenue readiness from one place.</p>
            </div>
            <div class="om-quick-actions">
                <a href="?tab=orders" class="bntm-btn-primary">Add Order</a>
                <?php if (bntm_is_module_enabled('in') && bntm_is_module_visible('in')): ?>
                <a href="?tab=inventory" class="bntm-btn-secondary">Sync Inventory</a>
                <?php endif; ?>
                <?php if (bntm_is_module_enabled('fn') && bntm_is_module_visible('fn')): ?>
                <a href="?tab=finance" class="bntm-btn-secondary">Review Finance Export</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="om-chart-grid">
        <div class="om-chart-card">
            <h3>Order Volume and Revenue Trend</h3>
            <div class="om-chart-box"><canvas id="om-trend-chart"></canvas></div>
        </div>
        <div class="om-chart-card">
            <h3>Status Distribution</h3>
            <div class="om-chart-box"><canvas id="om-status-chart"></canvas></div>
        </div>
        <div class="om-chart-card">
            <h3>Top Products by Quantity</h3>
            <div class="om-chart-box"><canvas id="om-products-chart"></canvas></div>
        </div>
        <div class="om-chart-card">
            <h3>Processor Performance</h3>
            <div class="om-chart-box"><canvas id="om-processor-chart"></canvas></div>
        </div>
    </div>

    <div class="om-grid-2" style="margin-top:20px;">
        <div class="bntm-form-section">
            <div class="om-section-head">
                <h3 style="margin:0;">Recent Orders</h3>
                <a href="?tab=orders" class="bntm-btn-secondary bntm-btn-small">View All</a>
            </div>
            <div class="bntm-table-wrapper" style="margin-top:16px;">
                <table class="bntm-table">
                    <thead>
                        <tr><th>Order</th><th>Status</th><th>Processor</th><th>Total</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_orders)): ?>
                            <tr><td colspan="5" class="om-empty">No orders yet.</td></tr>
                        <?php else: foreach ($recent_orders as $order): $user = get_userdata($order->processor_user_id); ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($order->order_number); ?></strong>
                                    <div style="color:#64748b;"><?php echo esc_html($order->customer_name ?: 'Walk-in / Internal'); ?></div>
                                </td>
                                <td><span class="om-badge om-badge-<?php echo esc_attr($order->status); ?>"><?php echo esc_html(ucfirst(str_replace('_', ' ', $order->status))); ?></span></td>
                                <td><?php echo esc_html($user ? $user->display_name : 'Unknown'); ?></td>
                                <td><?php echo esc_html(om_format_price($order->total)); ?></td>
                                <td><?php echo esc_html(date_i18n('M d, Y', strtotime($order->ordered_at))); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bntm-form-section">
            <div class="om-section-head">
                <h3 style="margin:0;">Low Stock Attention</h3>
                <?php if (bntm_is_module_enabled('in') && bntm_is_module_visible('in')): ?>
                <a href="?tab=inventory" class="bntm-btn-secondary bntm-btn-small">Open Inventory Sync</a>
                <?php endif; ?>
            </div>
            <div class="om-insight-list" style="margin-top:16px;">
                <?php if (empty($low_stock)): ?>
                    <div class="om-empty" style="padding:20px 0;">No low-stock items flagged.</div>
                <?php else: foreach ($low_stock as $item): ?>
                    <div class="om-insight">
                        <div>
                            <strong><?php echo esc_html($item->name); ?></strong>
                            <div style="color:#64748b;font-size:12px;"><?php echo esc_html($item->sku ?: 'No SKU'); ?></div>
                        </div>
                        <div style="text-align:right;">
                            <strong style="color:#b91c1c;"><?php echo intval($item->stock); ?> left</strong>
                            <div style="color:#64748b;font-size:12px;"><?php echo esc_html(om_format_price($item->price)); ?></div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <div class="bntm-form-section">
        <h3>Frontend Pages</h3>
            <p style="color:#64748b;margin-bottom:16px;">Use these pages for printable order documents and lookup directly from your orders workflow.</p>
        <div class="om-page-card-grid">
            <div class="om-page-card">
                <div class="om-section-head">
                    <strong>Order Transaction Document</strong>
                    <span class="om-badge om-badge-processing">Logged-in</span>
                </div>
                <p style="color:#64748b;">Printable order document for every transaction.</p>
                <div class="om-quick-actions">
                    <a href="<?php echo esc_url(om_get_order_view_url('')); ?>" target="_blank" class="bntm-btn-primary bntm-btn-small">Open Page</a>
                </div>
            </div>
            <div class="om-page-card">
                <div class="om-section-head">
                    <strong>Order Lookup</strong>
                    <span class="om-badge om-badge-pending">Public</span>
                </div>
                <p style="color:#64748b;">Shared lookup page for order number checking.</p>
                <div class="om-quick-actions">
                    <a href="<?php echo esc_url(om_get_order_lookup_url()); ?>" target="_blank" class="bntm-btn-primary bntm-btn-small">Open Page</a>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        const trendData = <?php echo wp_json_encode($monthly_trend); ?>;
        const statusData = <?php echo wp_json_encode($status_mix); ?>;
        const productData = <?php echo wp_json_encode($top_products); ?>;
        const processorData = <?php echo wp_json_encode($processor_stats); ?>;

        const trendCanvas = document.getElementById('om-trend-chart');
        if (trendCanvas && trendData.length) {
            new Chart(trendCanvas, {
                type: 'line',
                data: {
                    labels: trendData.map(r => r.ym),
                    datasets: [
                        {label: 'Orders', data: trendData.map(r => parseInt(r.order_count, 10)), borderColor: '#0f766e', backgroundColor: 'rgba(15,118,110,.12)', yAxisID: 'y'},
                        {label: 'Revenue', data: trendData.map(r => parseFloat(r.revenue)), borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,.12)', yAxisID: 'y1'}
                    ]
                },
                options: {responsive: true, maintainAspectRatio: false, interaction: {mode: 'index', intersect: false}, scales: {y: {beginAtZero: true}, y1: {beginAtZero: true, position: 'right', grid: {drawOnChartArea: false}}}}
            });
        }

        const statusCanvas = document.getElementById('om-status-chart');
        if (statusCanvas && statusData.length) {
            new Chart(statusCanvas, {
                type: 'doughnut',
                data: {
                    labels: statusData.map(r => r.status),
                    datasets: [{data: statusData.map(r => parseInt(r.count, 10)), backgroundColor: ['#94a3b8','#60a5fa','#f59e0b','#22c55e','#ef4444']}]
                },
                options: {responsive: true, maintainAspectRatio: false}
            });
        }

        const productsCanvas = document.getElementById('om-products-chart');
        if (productsCanvas && productData.length) {
            new Chart(productsCanvas, {
                type: 'bar',
                data: {
                    labels: productData.map(r => r.product_name),
                    datasets: [{label: 'Quantity', data: productData.map(r => parseInt(r.qty, 10)), backgroundColor: '#0ea5e9'}]
                },
                options: {responsive: true, maintainAspectRatio: false, indexAxis: 'y'}
            });
        }

        const processorCanvas = document.getElementById('om-processor-chart');
        if (processorCanvas && processorData.length) {
            new Chart(processorCanvas, {
                type: 'bar',
                data: {
                    labels: processorData.map(r => r.display_name || 'Unknown'),
                    datasets: [
                        {label: 'Orders', data: processorData.map(r => parseInt(r.order_count, 10)), backgroundColor: '#10b981'},
                        {label: 'Revenue', data: processorData.map(r => parseFloat(r.total_amount)), backgroundColor: '#2563eb'}
                    ]
                },
                options: {responsive: true, maintainAspectRatio: false}
            });
        }
    })();
    </script>
    <?php
    return ob_get_clean();
}

function om_orders_tab($business_id) {
    global $wpdb;

    $orders_table = $wpdb->prefix . 'om_orders';
    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    $processor_filter = isset($_GET['processor']) ? intval($_GET['processor']) : 0;
    $where = "WHERE business_id = %d";
    $params = [$business_id];
    if ($status_filter !== '') {
        $where .= " AND status = %s";
        $params[] = $status_filter;
    }
    if ($processor_filter > 0) {
        $where .= " AND processor_user_id = %d";
        $params[] = $processor_filter;
    }
    $orders = empty($params)
        ? $wpdb->get_results("SELECT * FROM {$orders_table} {$where} ORDER BY ordered_at DESC LIMIT 100")
        : $wpdb->get_results($wpdb->prepare("SELECT * FROM {$orders_table} {$where} ORDER BY ordered_at DESC LIMIT 100", $params));
    $synced_items = om_get_synced_products($business_id);
    $processors   = om_get_business_team_users($business_id);
    $categories   = om_get_categories($business_id);

    ob_start();
    ?>
    <div class="bntm-form-section">
        <div class="om-toolbar">
            <div>
                <h3 style="margin:0;">Orders</h3>
                <p style="margin:6px 0 0;color:#64748b;">Create multi-line order transactions, assign processors, track status, and print formal order documents.</p>
            </div>
            <div class="om-quick-actions">
                <button type="button" id="om-open-create-order" class="bntm-btn-primary">Add Order</button>
                <button type="button" id="om-open-create-order-quick" class="bntm-btn-secondary">Quick Add Transaction</button>
            </div>
        </div>
        <form method="get">
            <input type="hidden" name="tab" value="orders">
            <select name="status">
                <option value="">All statuses</option>
                <?php foreach (om_get_status_options() as $status_key => $label): ?>
                    <option value="<?php echo esc_attr($status_key); ?>" <?php selected($status_filter, $status_key); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="processor">
                <option value="0">All processors</option>
                <?php foreach ($processors as $processor): ?>
                    <option value="<?php echo intval($processor->ID); ?>" <?php selected($processor_filter, $processor->ID); ?>><?php echo esc_html($processor->display_name); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="bntm-btn-primary bntm-btn-small">Filter</button>
            <?php if ($status_filter !== '' || $processor_filter > 0): ?>
                <a href="?tab=orders" class="bntm-btn-secondary bntm-btn-small">Clear</a>
            <?php endif; ?>
        </form>
        <div id="om-orders-message" class="om-notice-target"></div>
    </div>

    <div class="bntm-table-wrapper">
        <table class="bntm-table">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Category</th>
                    <th>Processor</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th>Total</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="8" class="om-empty">No orders found.</td></tr>
                <?php else: foreach ($orders as $order): $processor = get_userdata($order->processor_user_id); ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($order->order_number); ?></strong>
                            <div style="color:#64748b;"><?php echo esc_html(date_i18n('M d, Y g:i A', strtotime($order->ordered_at))); ?></div>
                        </td>
                        <td>
                            <strong><?php echo esc_html($order->customer_name ?: 'Walk-in / Internal'); ?></strong>
                            <div style="color:#64748b;"><?php echo esc_html($order->customer_email ?: 'No email'); ?></div>
                        </td>
                        <td><?php echo esc_html($order->transaction_category); ?></td>
                        <td><?php echo esc_html($processor ? $processor->display_name : 'Unknown'); ?></td>
                        <td><span class="om-badge om-badge-<?php echo esc_attr($order->status); ?>"><?php echo esc_html(ucfirst(str_replace('_', ' ', $order->status))); ?></span></td>
                        <td><span class="om-badge om-badge-<?php echo esc_attr($order->payment_status); ?>"><?php echo esc_html(ucfirst(str_replace('_', ' ', $order->payment_status))); ?></span></td>
                        <td><?php echo esc_html(om_format_price($order->total)); ?></td>
                        <td>
                            <div class="om-quick-actions">
                                <button type="button" class="bntm-btn-secondary bntm-btn-small om-edit-order" data-id="<?php echo intval($order->id); ?>">Edit</button>
                                <button type="button" class="bntm-btn-secondary bntm-btn-small om-duplicate-order" data-id="<?php echo intval($order->id); ?>">Duplicate</button>
                                <a href="<?php echo esc_url(om_get_order_view_url($order->rand_id)); ?>" target="_blank" class="bntm-btn-secondary bntm-btn-small">Print</a>
                                <button type="button" class="bntm-btn-danger bntm-btn-small om-delete-order" data-id="<?php echo intval($order->id); ?>">Delete</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <div class="om-modal" id="om-order-modal">
        <div class="om-modal-content">
            <div class="om-modal-header">
                <div>
                    <h2 id="om-order-modal-title" style="margin:0;">Add Order</h2>
                    <p style="margin:6px 0 0;color:#64748b;">Capture line items, processor, payment state, and order notes in one transaction record.</p>
                </div>
                <button type="button" class="om-modal-close" data-modal="om-order-modal">&times;</button>
            </div>
            <div class="om-modal-body">
                <form id="om-order-form">
                    <input type="hidden" name="order_id" id="om-order-id" value="0">
                    <input type="hidden" name="line_items_json" id="om-line-items-json" value="">
                    

                    <div class="bntm-form-section" style="margin-top:18px;">
                        <div class="om-section-head">
                            <div>
                                <h3 style="margin:0;">Products / Line Items</h3>
                                <p style="margin:6px 0 0;color:#64748b;">Pull item details from Inventory, adjust quantities, and add more lines as needed.</p>
                            </div>
                            <button type="button" class="bntm-btn-secondary bntm-btn-small" id="om-add-line">Add More Line</button>
                        </div>
                        <div class="om-lines" id="om-lines-container"></div>
                    </div>
                    <div class="om-inline-fields">
                        <div class="om-field"><label>Customer Name</label><input type="text" name="customer_name" id="om-customer-name" placeholder="Customer or internal request name"></div>
                        <div class="om-field"><label>Customer Email</label><input type="email" name="customer_email" id="om-customer-email" placeholder="customer@example.com"></div>
                        <div class="om-field"><label>Customer Phone</label><input type="text" name="customer_phone" id="om-customer-phone" placeholder="Optional"></div>
                        <div class="om-field"><label>Customer Company</label><input type="text" name="customer_company" id="om-customer-company" placeholder="Optional"></div>
                        <div class="om-field">
                            <label>Transaction Category</label>
                            <select name="transaction_category" id="om-transaction-category">
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo esc_attr($category->name); ?>"><?php echo esc_html($category->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="om-field">
                            <label>Processor</label>
                            <select name="processor_user_id" id="om-processor">
                                <?php foreach ($processors as $processor): ?>
                                    <option value="<?php echo intval($processor->ID); ?>" <?php selected($processor->ID, get_current_user_id()); ?>><?php echo esc_html($processor->display_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="om-field">
                            <label>Status</label>
                            <select name="status" id="om-status">
                                <?php foreach (om_get_status_options() as $status_key => $label): ?>
                                    <option value="<?php echo esc_attr($status_key); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="om-field">
                            <label>Payment Status</label>
                            <select name="payment_status" id="om-payment-status">
                                <?php foreach (om_get_payment_status_options() as $status_key => $label): ?>
                                    <option value="<?php echo esc_attr($status_key); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="om-field"><label>Due Date</label><input type="date" name="due_date" id="om-due-date"></div>
                        <div class="om-field"><label>Order Date</label><input type="datetime-local" name="ordered_at" id="om-ordered-at" value="<?php echo esc_attr(date('Y-m-d\TH:i')); ?>"></div>
                    </div>
                    <div class="om-inline-fields">
                        <div class="om-field"><label>Notes</label><textarea name="notes" id="om-notes" placeholder="Visible transaction notes"></textarea></div>
                        <div class="om-field"><label>Internal Notes</label><textarea name="internal_notes" id="om-internal-notes" placeholder="Internal remarks, approvals, or handling reminders"></textarea></div>
                        <div class="om-field"><label>Discount</label><input type="number" step="0.01" min="0" name="discount" id="om-discount" value="0"></div>
                        <div class="om-field"><label>Tax</label><input type="number" step="0.01" min="0" name="tax" id="om-tax" value="0"></div>
                        <div class="om-field"><label>Shipping Fee</label><input type="number" step="0.01" min="0" name="shipping_fee" id="om-shipping-fee" value="0"></div>
                        <div class="om-field"><label>Total</label><input type="text" id="om-total-display" value="<?php echo esc_attr(om_format_price(0)); ?>" readonly></div>
                    </div>

                    <div class="om-quick-actions" style="margin-top:18px;">
                        <button type="submit" class="bntm-btn-primary" id="om-save-order-btn">Save Order</button>
                        <button type="button" class="bntm-btn-secondary" onclick="omCloseModal('om-order-modal')">Cancel</button>
                    </div>
                    <div id="om-order-form-message" class="om-notice-target"></div>
                </form>
            </div>
        </div>
    </div>

    <script>
    (function() {
        const products = <?php echo wp_json_encode(array_map(function($item) {
            return [
                'id' => (int) $item->id,
                'inventory_product_id' => (int) $item->inventory_product_id,
                'rand_id' => $item->product_rand_id,
                'name' => $item->name,
                'sku' => $item->sku,
                'category' => $item->category,
                'price' => (float) $item->price,
                'stock' => (int) $item->stock,
                'unit' => 'unit',
            ];
        }, $synced_items)); ?>;
        const linesContainer = document.getElementById('om-lines-container');
        const saveBtn = document.getElementById('om-save-order-btn');
        const orderForm = document.getElementById('om-order-form');

        function buildOptions(selectedId) {
            let html = '<option value="">Select inventory product</option>';
            products.forEach(function(product) {
                html += '<option value="' + product.inventory_product_id + '"' + (String(selectedId) === String(product.inventory_product_id) ? ' selected' : '') + '>' + product.name + ' (' + product.stock + ' in stock)</option>';
            });
            return html;
        }

        function updateGrandTotal() {
            let subtotal = 0;
            linesContainer.querySelectorAll('.om-line-row').forEach(function(row) {
                const qty = parseFloat(row.querySelector('.om-line-qty').value || 0);
                const price = parseFloat(row.querySelector('.om-line-price').value || 0);
                const discount = parseFloat(row.querySelector('.om-line-discount').value || 0);
                subtotal += Math.max(0, (qty * price) - discount);
            });
            const orderDiscount = parseFloat(document.getElementById('om-discount').value || 0);
            const tax = parseFloat(document.getElementById('om-tax').value || 0);
            const shipping = parseFloat(document.getElementById('om-shipping-fee').value || 0);
            const total = Math.max(0, subtotal - orderDiscount + tax + shipping);
            document.getElementById('om-total-display').value = '<?php echo esc_js(om_currency_symbol()); ?>' + total.toFixed(2);
        }

        function updateLineTotal(row) {
            const qty = parseFloat(row.querySelector('.om-line-qty').value || 0);
            const price = parseFloat(row.querySelector('.om-line-price').value || 0);
            const discount = parseFloat(row.querySelector('.om-line-discount').value || 0);
            const total = Math.max(0, (qty * price) - discount);
            row.querySelector('.om-line-total').value = '<?php echo esc_js(om_currency_symbol()); ?>' + total.toFixed(2);
            updateGrandTotal();
        }

        function updateLineProduct(row) {
            const select = row.querySelector('.om-line-product');
            const meta = row.querySelector('.om-line-meta');
            const price = row.querySelector('.om-line-price');
            const product = products.find(function(item) { return String(item.inventory_product_id) === String(select.value); });
            if (!product) {
                meta.textContent = '';
                price.value = 0;
                updateLineTotal(row);
                return;
            }
            price.value = product.price;
            meta.textContent = (product.sku || 'No SKU') + ' | Stock: ' + product.stock + ' | ' + (product.category || 'Uncategorized');
            updateLineTotal(row);
        }

        function bindLineRow(row) {
            row.querySelector('.om-line-product').addEventListener('change', function() { updateLineProduct(row); });
            row.querySelector('.om-line-qty').addEventListener('input', function() { updateLineTotal(row); });
            row.querySelector('.om-line-price').addEventListener('input', function() { updateLineTotal(row); });
            row.querySelector('.om-line-discount').addEventListener('input', function() { updateLineTotal(row); });
            row.querySelector('.om-remove-line').addEventListener('click', function() {
                row.remove();
                if (!linesContainer.children.length) createLineRow();
                updateGrandTotal();
            });
        }

        function createLineRow(data) {
            const row = document.createElement('div');
            row.className = 'om-line-row';
            row.innerHTML =
                '<div class="om-field"><label>Product</label><select class="om-line-product">' + buildOptions(data && data.inventory_product_id ? data.inventory_product_id : '') + '</select><div class="om-line-meta"></div></div>' +
                '<div class="om-field"><label>Quantity</label><input type="number" min="1" class="om-line-qty" value="' + (data && data.quantity ? data.quantity : 1) + '"></div>' +
                '<div class="om-field"><label>Unit Price</label><input type="number" step="0.01" min="0" class="om-line-price" value="' + (data && data.unit_price ? data.unit_price : 0) + '"></div>' +
                '<div class="om-field"><label>Line Discount</label><input type="number" step="0.01" min="0" class="om-line-discount" value="' + (data && data.line_discount ? data.line_discount : 0) + '"></div>' +
                '<div class="om-field"><label>Line Total</label><input type="text" class="om-line-total" value="<?php echo esc_js(om_format_price(0)); ?>" readonly></div>' +
                '<div class="om-field"><label>&nbsp;</label><button type="button" class="bntm-btn-danger bntm-btn-small om-remove-line">Remove</button></div>';
            linesContainer.appendChild(row);
            bindLineRow(row);
            if (data && data.inventory_product_id) {
                row.querySelector('.om-line-product').value = String(data.inventory_product_id);
                updateLineProduct(row);
                row.querySelector('.om-line-price').value = data.unit_price || 0;
                row.querySelector('.om-line-discount').value = data.line_discount || 0;
            }
            updateLineTotal(row);
        }

        function resetForm() {
            orderForm.reset();
            document.getElementById('om-order-id').value = '0';
            document.getElementById('om-order-modal-title').textContent = 'Add Order';
            document.getElementById('om-ordered-at').value = '<?php echo esc_js(date('Y-m-d\TH:i')); ?>';
            linesContainer.innerHTML = '';
            createLineRow();
            document.getElementById('om-order-form-message').innerHTML = '';
            updateGrandTotal();
        }

        function gatherLineItems() {
            const items = [];
            let hasError = false;
            linesContainer.querySelectorAll('.om-line-row').forEach(function(row) {
                const productId = row.querySelector('.om-line-product').value;
                const qty = parseInt(row.querySelector('.om-line-qty').value || '0', 10);
                const price = parseFloat(row.querySelector('.om-line-price').value || '0');
                const discount = parseFloat(row.querySelector('.om-line-discount').value || '0');
                const product = products.find(function(item) { return String(item.inventory_product_id) === String(productId); });
                if (!productId || !product || qty <= 0) {
                    hasError = true;
                    return;
                }
                items.push({
                    inventory_product_id: product.inventory_product_id,
                    synced_item_id: product.id,
                    product_rand_id: product.rand_id,
                    product_name: product.name,
                    sku: product.sku,
                    unit: product.unit,
                    quantity: qty,
                    available_stock: product.stock,
                    unit_price: price,
                    line_discount: discount,
                    line_tax: 0,
                    line_total: Math.max(0, (qty * price) - discount)
                });
            });
            return hasError ? false : items;
        }

        document.getElementById('om-open-create-order').addEventListener('click', function() { resetForm(); omOpenModal('om-order-modal'); });
        document.getElementById('om-open-create-order-quick').addEventListener('click', function() { resetForm(); omOpenModal('om-order-modal'); });
        document.getElementById('om-add-line').addEventListener('click', function() { createLineRow(); });
        ['om-discount', 'om-tax', 'om-shipping-fee'].forEach(function(id) { document.getElementById(id).addEventListener('input', updateGrandTotal); });

        orderForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const items = gatherLineItems();
            if (!items || !items.length) {
                omToast('om-order-form-message', 'Please add at least one valid product line with quantity.', false);
                return;
            }
            document.getElementById('om-line-items-json').value = JSON.stringify(items);
            const formData = new FormData(orderForm);
            formData.append('action', 'om_save_order');
            formData.append('nonce', omNonce);
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';
            fetch(ajaxurl, {method: 'POST', body: formData})
                .then(function(r) { return r.json(); })
                .then(function(json) {
                    omToast('om-order-form-message', json.data.message, json.success);
                    if (json.success) {
                        setTimeout(function() { window.location.reload(); }, 900);
                    } else {
                        saveBtn.disabled = false;
                        saveBtn.textContent = 'Save Order';
                    }
                })
                .catch(function() {
                    omToast('om-order-form-message', 'Unable to save order right now.', false);
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save Order';
                });
        });

        document.querySelectorAll('.om-edit-order').forEach(function(button) {
            button.addEventListener('click', function() {
                const data = new FormData();
                data.append('action', 'om_get_order');
                data.append('nonce', omNonce);
                data.append('order_id', this.dataset.id);
                fetch(ajaxurl, {method: 'POST', body: data})
                    .then(function(r) { return r.json(); })
                    .then(function(json) {
                        if (!json.success) { omToast('om-orders-message', json.data.message, false); return; }
                        resetForm();
                        const order = json.data.order;
                        document.getElementById('om-order-modal-title').textContent = 'Edit Order';
                        document.getElementById('om-order-id').value = order.id;
                        document.getElementById('om-customer-name').value = order.customer_name || '';
                        document.getElementById('om-customer-email').value = order.customer_email || '';
                        document.getElementById('om-customer-phone').value = order.customer_phone || '';
                        document.getElementById('om-customer-company').value = order.customer_company || '';
                        document.getElementById('om-transaction-category').value = order.transaction_category || '';
                        document.getElementById('om-processor').value = order.processor_user_id || '<?php echo intval(get_current_user_id()); ?>';
                        document.getElementById('om-status').value = order.status || 'open';
                        document.getElementById('om-payment-status').value = order.payment_status || 'unpaid';
                        document.getElementById('om-due-date').value = order.due_date || '';
                        document.getElementById('om-ordered-at').value = order.ordered_at_local || '';
                        document.getElementById('om-notes').value = order.notes || '';
                        document.getElementById('om-internal-notes').value = order.internal_notes || '';
                        document.getElementById('om-discount').value = order.discount || 0;
                        document.getElementById('om-tax').value = order.tax || 0;
                        document.getElementById('om-shipping-fee').value = order.shipping_fee || 0;
                        linesContainer.innerHTML = '';
                        if (json.data.items && json.data.items.length) json.data.items.forEach(function(item) { createLineRow(item); }); else createLineRow();
                        updateGrandTotal();
                        omOpenModal('om-order-modal');
                    });
            });
        });

        document.querySelectorAll('.om-delete-order').forEach(function(button) {
            button.addEventListener('click', function() {
                if (!confirm('Delete this order? Completed or exported orders cannot be deleted.')) return;
                const data = new FormData();
                data.append('action', 'om_delete_order');
                data.append('nonce', omNonce);
                data.append('order_id', this.dataset.id);
                fetch(ajaxurl, {method: 'POST', body: data})
                    .then(function(r) { return r.json(); })
                    .then(function(json) {
                        omToast('om-orders-message', json.data.message, json.success);
                        if (json.success) setTimeout(function() { window.location.reload(); }, 900);
                    });
            });
        });

        document.querySelectorAll('.om-duplicate-order').forEach(function(button) {
            button.addEventListener('click', function() {
                const data = new FormData();
                data.append('action', 'om_duplicate_order');
                data.append('nonce', omNonce);
                data.append('order_id', this.dataset.id);
                fetch(ajaxurl, {method: 'POST', body: data})
                    .then(function(r) { return r.json(); })
                    .then(function(json) {
                        omToast('om-orders-message', json.data.message, json.success);
                        if (json.success) setTimeout(function() { window.location.reload(); }, 900);
                    });
            });
        });

        createLineRow();
    })();
    </script>
    <?php
    return ob_get_clean();
}

function om_documents_tab($business_id) {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'om_orders';
    $orders = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$orders_table} WHERE business_id = %d ORDER BY ordered_at DESC LIMIT 100",
        $business_id
    ));

    ob_start();
    ?>
    <div class="bntm-form-section">
        <div class="om-header-row">
            <div>
                <h3 style="margin:0;">Order Documents</h3>
                <p style="margin:6px 0 0;color:#64748b;">Every order can produce a printable transaction document using the same clean print workflow as your quotation pages.</p>
            </div>
        </div>
        <div id="om-documents-message" class="om-notice-target"></div>
    </div>

    <div class="bntm-table-wrapper">
        <table class="bntm-table">
            <thead>
                <tr><th>Order</th><th>Customer</th><th>Status</th><th>Printed</th><th>Document</th></tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="5" class="om-empty">No documents available.</td></tr>
                <?php else: foreach ($orders as $order): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($order->order_number); ?></strong>
                            <div style="color:#64748b;"><?php echo esc_html(date_i18n('M d, Y g:i A', strtotime($order->ordered_at))); ?></div>
                        </td>
                        <td><?php echo esc_html($order->customer_name ?: 'Walk-in / Internal'); ?></td>
                        <td><span class="om-badge om-badge-<?php echo esc_attr($order->status); ?>"><?php echo esc_html(ucfirst(str_replace('_', ' ', $order->status))); ?></span></td>
                        <td><?php echo $order->printed_at ? esc_html(date_i18n('M d, Y g:i A', strtotime($order->printed_at))) : '<span style="color:#64748b;">Not printed yet</span>'; ?></td>
                        <td>
                            <div class="om-quick-actions">
                                <button type="button" class="bntm-btn-primary bntm-btn-small om-print-order" data-id="<?php echo intval($order->id); ?>" data-url="<?php echo esc_url(om_get_order_view_url($order->rand_id)); ?>">Open / Print</button>
                                <a href="<?php echo esc_url(om_get_order_view_url($order->rand_id)); ?>" target="_blank" class="bntm-btn-secondary bntm-btn-small">Preview</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <script>
    (function() {
        document.querySelectorAll('.om-print-order').forEach(function(button) {
            button.addEventListener('click', function() {
                const data = new FormData();
                data.append('action', 'om_mark_printed');
                data.append('nonce', omNonce);
                data.append('order_id', this.dataset.id);
                fetch(ajaxurl, {method: 'POST', body: data})
                    .then(function(r) { return r.json(); })
                    .then(function() {
                        window.open(button.dataset.url, '_blank');
                    });
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

function om_inventory_tab($business_id) {
    global $wpdb;
    $current_table     = $wpdb->prefix . 'om_inventory_items';
    $in_products_table = $wpdb->prefix . 'in_products';
    $available_imports = [];
    if (bntm_is_module_enabled('in') && bntm_is_module_visible('in')) {
        $available_imports = $wpdb->get_results($wpdb->prepare("
            SELECT inprod.*
            FROM {$in_products_table} AS inprod
            LEFT JOIN {$current_table} AS current ON inprod.id = current.inventory_product_id AND current.business_id = %d
            WHERE current.id IS NULL
              AND inprod.business_id = %d
              AND inprod.inventory_type = %s
            ORDER BY inprod.name ASC
        ", $business_id, $business_id, 'Product'));
    }
    $synced_items = om_get_synced_products($business_id);
    $stock_trigger = om_get_setting_for_business('om_stock_deduction_status', 'completed', $business_id);

    ob_start();
    ?>
    <div class="bntm-form-section">
        <h3>Inventory Sync</h3>
        <?php if (!bntm_is_module_enabled('in') || !bntm_is_module_visible('in')): ?>
            <p style="color:#b91c1c;">Inventory module is not enabled. Order Management can still store orders, but product pull and stock sync are unavailable.</p>
        <?php else: ?>
            <p style="color:#64748b;">Import products from Inventory, keep pricing and stock aligned, and control when stock is deducted from completed orders.</p>
            <div class="om-quick-actions" style="margin-top:12px;">
                <label style="display:flex;align-items:center;gap:8px;">
                    <span style="font-size:13px;color:#475569;">Deduct stock when order becomes:</span>
                    <select id="om-stock-trigger">
                        <?php foreach (om_get_status_options() as $status_key => $label): ?>
                            <option value="<?php echo esc_attr($status_key); ?>" <?php selected($stock_trigger, $status_key); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="button" id="om-save-stock-trigger" class="bntm-btn-secondary bntm-btn-small">Save Trigger</button>
                <button type="button" id="om-refresh-stock" class="bntm-btn-primary bntm-btn-small">Refresh Synced Stock</button>
            </div>
            <div id="om-inventory-message" class="om-notice-target"></div>
        <?php endif; ?>
    </div>

    <?php if (bntm_is_module_enabled('in') && bntm_is_module_visible('in')): ?>
    <div class="bntm-form-section">
        <h3>Import from Inventory</h3>
        <?php if (!empty($available_imports)): ?>
            <div style="margin:15px 0;">
                <label style="cursor:pointer;"><input type="checkbox" id="om-select-all-imports"> <strong>Select All</strong></label>
            </div>
            <div class="bntm-table-wrapper">
                <table class="bntm-table">
                    <thead><tr><th></th><th>Product</th><th>SKU</th><th>Price</th><th>Stock</th></tr></thead>
                    <tbody>
                        <?php foreach ($available_imports as $item): ?>
                            <tr>
                                <td><input type="checkbox" name="import_items[]" value="<?php echo intval($item->id); ?>"></td>
                                <td><?php echo esc_html($item->name); ?></td>
                                <td><?php echo esc_html($item->sku); ?></td>
                                <td><?php echo esc_html(om_format_price($item->selling_price)); ?></td>
                                <td><?php echo intval($item->stock_quantity); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="om-quick-actions" style="margin-top:14px;">
                <button id="om-import-selected-btn" class="bntm-btn-primary">Import Selected Items</button>
            </div>
        <?php else: ?>
            <p>No inventory products available for import.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="bntm-form-section">
        <h3>Synced Order Products</h3>
        <div class="bntm-table-wrapper">
            <table class="bntm-table">
                <thead><tr><th>Name</th><th>SKU</th><th>Category</th><th>Price</th><th>Stock</th><th>Last Synced</th></tr></thead>
                <tbody>
                    <?php if (empty($synced_items)): ?>
                        <tr><td colspan="6" class="om-empty">No synced products yet.</td></tr>
                    <?php else: foreach ($synced_items as $item): ?>
                        <tr>
                            <td><?php echo esc_html($item->name); ?></td>
                            <td><?php echo esc_html($item->sku); ?></td>
                            <td><?php echo esc_html($item->category ?: 'Uncategorized'); ?></td>
                            <td><?php echo esc_html(om_format_price($item->price)); ?></td>
                            <td><?php echo intval($item->stock); ?></td>
                            <td><?php echo esc_html(date_i18n('M d, Y g:i A', strtotime($item->last_synced_at))); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    (function() {
        const importSelectAll = document.getElementById('om-select-all-imports');
        if (importSelectAll) {
            importSelectAll.addEventListener('change', function() {
                document.querySelectorAll('input[name="import_items[]"]').forEach(cb => cb.checked = this.checked);
            });
        }
        const importBtn = document.getElementById('om-import-selected-btn');
        if (importBtn) {
            importBtn.addEventListener('click', function() {
                const selected = Array.from(document.querySelectorAll('input[name="import_items[]"]:checked')).map(cb => cb.value);
                if (!selected.length) { omToast('om-inventory-message', 'Please select at least one product to import.', false); return; }
                const formData = new FormData();
                formData.append('action', 'om_import_items');
                formData.append('nonce', omNonce);
                formData.append('item_ids', JSON.stringify(selected));
                importBtn.disabled = true;
                importBtn.textContent = 'Importing...';
                fetch(ajaxurl, {method:'POST', body: formData})
                    .then(r => r.json())
                    .then(json => {
                        omToast('om-inventory-message', json.data.message, json.success);
                        if (json.success) setTimeout(function(){ window.location.reload(); }, 900);
                        else { importBtn.disabled = false; importBtn.textContent = 'Import Selected Items'; }
                    });
            });
        }
        const refreshBtn = document.getElementById('om-refresh-stock');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function() {
                const formData = new FormData();
                formData.append('action', 'om_refresh_stock');
                formData.append('nonce', omNonce);
                refreshBtn.disabled = true;
                refreshBtn.textContent = 'Refreshing...';
                fetch(ajaxurl, {method:'POST', body: formData})
                    .then(r => r.json())
                    .then(json => {
                        omToast('om-inventory-message', json.data.message, json.success);
                        if (json.success) setTimeout(function(){ window.location.reload(); }, 900);
                        else { refreshBtn.disabled = false; refreshBtn.textContent = 'Refresh Synced Stock'; }
                    });
            });
        }
        const saveTriggerBtn = document.getElementById('om-save-stock-trigger');
        if (saveTriggerBtn) {
            saveTriggerBtn.addEventListener('click', function() {
                const formData = new FormData();
                formData.append('action', 'om_save_settings');
                formData.append('nonce', omNonce);
                formData.append('stock_deduction_status', document.getElementById('om-stock-trigger').value);
                fetch(ajaxurl, {method:'POST', body: formData})
                    .then(r => r.json())
                    .then(json => omToast('om-inventory-message', json.data.message, json.success));
            });
        }
    })();
    </script>
    <?php
    return ob_get_clean();
}

function om_finance_tab($business_id) {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'om_orders';
    $exports_table = $wpdb->prefix . 'om_finance_exports';
    $orders = $wpdb->get_results($wpdb->prepare(
        "SELECT o.*,
                (SELECT COUNT(*) FROM {$exports_table} e WHERE e.order_id = o.id AND e.business_id = %d AND e.status = 'exported') as is_exported
         FROM {$orders_table} o
         WHERE o.business_id = %d
         ORDER BY o.ordered_at DESC",
        $business_id,
        $business_id
    ));

    ob_start();
    ?>
    <div class="bntm-form-section">
        <h3>Export to Finance Module</h3>
        <p>Export completed and paid order transactions to Finance as income records, or revert exported records when needed.</p>
        <div id="om-finance-message" class="om-notice-target"></div>
    </div>

    <div class="bntm-table-wrapper">
        <table class="bntm-table">
            <thead>
                <tr><th width="40"></th><th>Order</th><th>Status</th><th>Payment</th><th>Total</th><th>Export Status</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="7" class="om-empty">No orders available for export.</td></tr>
                <?php else: foreach ($orders as $order): ?>
                    <tr>
                        <td><input type="checkbox" class="om-finance-checkbox" data-id="<?php echo intval($order->id); ?>" data-amount="<?php echo esc_attr($order->total); ?>" data-exported="<?php echo intval($order->is_exported > 0); ?>"></td>
                        <td><strong><?php echo esc_html($order->order_number); ?></strong></td>
                        <td><span class="om-badge om-badge-<?php echo esc_attr($order->status); ?>"><?php echo esc_html(ucfirst($order->status)); ?></span></td>
                        <td><span class="om-badge om-badge-<?php echo esc_attr($order->payment_status); ?>"><?php echo esc_html(ucfirst(str_replace('_', ' ', $order->payment_status))); ?></span></td>
                        <td><?php echo esc_html(om_format_price($order->total)); ?></td>
                        <td><?php if ($order->is_exported): ?><span class="om-badge om-badge-exported">Exported</span><?php else: ?><span class="om-badge om-badge-not_exported">Not Exported</span><?php endif; ?></td>
                        <td>
                            <?php if ($order->is_exported): ?>
                                <button type="button" class="bntm-btn-secondary bntm-btn-small om-revert-finance" data-id="<?php echo intval($order->id); ?>">Revert</button>
                            <?php else: ?>
                                <button type="button" class="bntm-btn-primary bntm-btn-small om-export-finance" data-id="<?php echo intval($order->id); ?>">Export</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <div class="om-quick-actions" style="margin-top:16px;">
        <button type="button" id="om-bulk-export" class="bntm-btn-primary">Export Selected</button>
        <button type="button" id="om-bulk-revert" class="bntm-btn-secondary">Revert Selected</button>
    </div>

    <script>
    (function() {
        function runBulk(actionName, filterExported) {
            const selected = Array.from(document.querySelectorAll('.om-finance-checkbox:checked')).filter(cb => String(filterExported) === cb.dataset.exported);
            if (!selected.length) { omToast('om-finance-message', 'Please select matching orders first.', false); return; }
            let completed = 0;
            selected.forEach(function(checkbox) {
                const formData = new FormData();
                formData.append('action', actionName);
                formData.append('nonce', omNonce);
                formData.append('order_id', checkbox.dataset.id);
                fetch(ajaxurl, {method:'POST', body:formData}).then(r => r.json()).then(function() {
                    completed++;
                    if (completed === selected.length) window.location.reload();
                });
            });
        }
        document.querySelectorAll('.om-export-finance').forEach(function(button) {
            button.addEventListener('click', function() {
                const formData = new FormData();
                formData.append('action', 'om_export_order');
                formData.append('nonce', omNonce);
                formData.append('order_id', button.dataset.id);
                fetch(ajaxurl, {method:'POST', body:formData}).then(r => r.json()).then(json => { omToast('om-finance-message', json.data.message, json.success); if (json.success) setTimeout(function(){ window.location.reload(); }, 900); });
            });
        });
        document.querySelectorAll('.om-revert-finance').forEach(function(button) {
            button.addEventListener('click', function() {
                const formData = new FormData();
                formData.append('action', 'om_revert_order_export');
                formData.append('nonce', omNonce);
                formData.append('order_id', button.dataset.id);
                fetch(ajaxurl, {method:'POST', body:formData}).then(r => r.json()).then(json => { omToast('om-finance-message', json.data.message, json.success); if (json.success) setTimeout(function(){ window.location.reload(); }, 900); });
            });
        });
        document.getElementById('om-bulk-export').addEventListener('click', function() { runBulk('om_export_order', 0); });
        document.getElementById('om-bulk-revert').addEventListener('click', function() { runBulk('om_revert_order_export', 1); });
    })();
    </script>
    <?php
    return ob_get_clean();
}

function om_settings_tab($business_id) {
    $categories = om_get_categories($business_id);
    $category_lines = implode("\n", array_map(function($item) { return $item->name; }, $categories));
    $prefix = om_get_setting_for_business('om_order_prefix', 'ORD', $business_id);
    $next_number = intval(om_get_setting_for_business('om_next_order_number', 1, $business_id));
    $stock_deduction_status = om_get_setting_for_business('om_stock_deduction_status', 'completed', $business_id);
    $finance_category = om_get_setting_for_business('om_finance_category', 'Order Sales', $business_id);
    $document_footer = om_get_setting_for_business('om_document_footer', 'Thank you for your order.', $business_id);
    $lookup_enabled = om_get_setting_for_business('om_lookup_enabled', 'yes', $business_id);

    ob_start();
    ?>
    <div class="om-grid-2">
        <div class="bntm-form-section">
            <h3>Order Defaults and Document Rules</h3>
            <form id="om-settings-form">
                <div class="om-inline-fields">
                    <div class="om-field"><label>Order Prefix</label><input type="text" name="order_prefix" value="<?php echo esc_attr($prefix); ?>"></div>
                    <div class="om-field"><label>Next Order Number</label><input type="number" name="next_order_number" min="1" value="<?php echo esc_attr($next_number); ?>"></div>
                    <div class="om-field">
                        <label>Stock Deduction Trigger</label>
                        <select name="stock_deduction_status">
                            <?php foreach (om_get_status_options() as $status_key => $label): ?>
                                <option value="<?php echo esc_attr($status_key); ?>" <?php selected($stock_deduction_status, $status_key); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="om-field"><label>Finance Category</label><input type="text" name="finance_category" value="<?php echo esc_attr($finance_category); ?>"></div>
                    <div class="om-field">
                        <label>Order Lookup Access</label>
                        <select name="lookup_enabled">
                            <option value="yes" <?php selected($lookup_enabled, 'yes'); ?>>Enabled</option>
                            <option value="no" <?php selected($lookup_enabled, 'no'); ?>>Disabled</option>
                        </select>
                    </div>
                    <div class="om-field"><label>Document Footer</label><textarea name="document_footer"><?php echo esc_textarea($document_footer); ?></textarea></div>
                </div>
                <div class="om-quick-actions" style="margin-top:18px;"><button type="submit" class="bntm-btn-primary">Save Settings</button></div>
                <div id="om-settings-message" class="om-notice-target"></div>
            </form>
        </div>

        <div class="bntm-form-section">
            <h3>Transaction Categories</h3>
            <p style="color:#64748b;">One category per line. The first line will be treated as the default category if no explicit default exists.</p>
            <form id="om-categories-form">
                <div class="om-field"><label>Categories</label><textarea name="categories_text" style="min-height:260px;"><?php echo esc_textarea($category_lines); ?></textarea></div>
                <div class="om-quick-actions" style="margin-top:18px;"><button type="submit" class="bntm-btn-primary">Save Categories</button></div>
                <div id="om-categories-message" class="om-notice-target"></div>
            </form>
        </div>
    </div>

    <script>
    (function() {
        document.getElementById('om-settings-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'om_save_settings');
            formData.append('nonce', omNonce);
            fetch(ajaxurl, {method:'POST', body:formData}).then(r => r.json()).then(json => omToast('om-settings-message', json.data.message, json.success));
        });
        document.getElementById('om-categories-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'om_save_categories');
            formData.append('nonce', omNonce);
            fetch(ajaxurl, {method:'POST', body:formData}).then(r => r.json()).then(json => omToast('om-categories-message', json.data.message, json.success));
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

/* ---------- AJAX HANDLER FUNCTIONS ---------- */

function bntm_ajax_om_save_order() {
    check_ajax_referer('om_nonce', 'nonce');
    if (!om_current_user_can_manage_business()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $orders_table = $wpdb->prefix . 'om_orders';
    $items_table  = $wpdb->prefix . 'om_order_items';
    $business_id  = om_get_current_business_id();
    $order_id     = intval($_POST['order_id'] ?? 0);
    $status       = sanitize_text_field($_POST['status'] ?? 'open');
    $payment_status = sanitize_text_field($_POST['payment_status'] ?? 'unpaid');
    $category     = sanitize_text_field($_POST['transaction_category'] ?? om_get_default_category_name($business_id));
    $processor_id = intval($_POST['processor_user_id'] ?? get_current_user_id());
    $customer_name = sanitize_text_field($_POST['customer_name'] ?? '');
    $customer_email = sanitize_text_field($_POST['customer_email'] ?? '');
    $customer_phone = sanitize_text_field($_POST['customer_phone'] ?? '');
    $customer_company = sanitize_text_field($_POST['customer_company'] ?? '');
    $notes = sanitize_textarea_field($_POST['notes'] ?? '');
    $internal_notes = sanitize_textarea_field($_POST['internal_notes'] ?? '');
    $discount = floatval($_POST['discount'] ?? 0);
    $tax = floatval($_POST['tax'] ?? 0);
    $shipping_fee = floatval($_POST['shipping_fee'] ?? 0);
    $due_date = sanitize_text_field($_POST['due_date'] ?? '');
    $ordered_at_local = sanitize_text_field($_POST['ordered_at'] ?? '');
    $ordered_at = $ordered_at_local ? date('Y-m-d H:i:s', strtotime($ordered_at_local)) : current_time('mysql');
    $line_items = json_decode(stripslashes($_POST['line_items_json'] ?? '[]'), true);

    if (empty($line_items) || !is_array($line_items)) {
        wp_send_json_error(['message' => 'Please add at least one product line.']);
    }

    $subtotal = 0;
    foreach ($line_items as $line) {
        $qty = intval($line['quantity'] ?? 0);
        if ($qty <= 0) wp_send_json_error(['message' => 'Each line item must have quantity greater than zero.']);
        $subtotal += max(0, (floatval($line['unit_price'] ?? 0) * $qty) - floatval($line['line_discount'] ?? 0));
    }
    $total = max(0, $subtotal - $discount + $tax + $shipping_fee);

    $old_order = null;
    if ($order_id > 0) {
        $old_order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$orders_table} WHERE id=%d AND business_id=%d", $order_id, $business_id));
        if (!$old_order) wp_send_json_error(['message' => 'Order not found.']);
    }

    $valid_processor_ids = array_map('intval', wp_list_pluck(om_get_business_team_users($business_id), 'ID'));
    if (!in_array($business_id, $valid_processor_ids, true)) {
        $valid_processor_ids[] = $business_id;
    }
    if (!in_array($processor_id, $valid_processor_ids, true)) {
        $processor_id = get_current_user_id();
    }

    $wpdb->query('START TRANSACTION');
    try {
        $order_data = [
            'business_id' => $business_id,
            'transaction_category' => $category,
            'customer_name' => $customer_name,
            'customer_email' => $customer_email,
            'customer_phone' => $customer_phone,
            'customer_company' => $customer_company,
            'processor_user_id' => $processor_id ?: get_current_user_id(),
            'status' => $status,
            'payment_status' => $payment_status,
            'fulfillment_status' => $status === 'completed' ? 'fulfilled' : ($status === 'cancelled' ? 'cancelled' : 'pending'),
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'shipping_fee' => $shipping_fee,
            'total' => $total,
            'inventory_sync_mode' => 'on_completed',
            'notes' => $notes,
            'internal_notes' => $internal_notes,
            'ordered_at' => $ordered_at,
            'due_date' => $due_date ?: null,
            'status_changed_at' => current_time('mysql'),
        ];
        if ($status === 'completed') $order_data['completed_at'] = current_time('mysql');

        if ($old_order) {
            $wpdb->update($orders_table, $order_data, ['id' => $order_id, 'business_id' => $business_id], null, ['%d', '%d']);
        } else {
            $order_data['rand_id'] = function_exists('bntm_rand_id') ? bntm_rand_id() : wp_generate_uuid4();
            $order_data['order_number'] = om_generate_order_number();
            $order_data['finance_export_status'] = 'not_exported';
            $order_data['source_module'] = 'manual';
            if (!$wpdb->insert($orders_table, $order_data)) throw new Exception('Failed to create order.');
            $order_id = intval($wpdb->insert_id);
        }

        $wpdb->delete($items_table, ['order_id' => $order_id, 'business_id' => $business_id], ['%d', '%d']);
        foreach ($line_items as $line) {
            $qty = intval($line['quantity'] ?? 0);
            $price = floatval($line['unit_price'] ?? 0);
            $line_discount = floatval($line['line_discount'] ?? 0);
            $line_tax = floatval($line['line_tax'] ?? 0);
            $line_total = max(0, ($qty * $price) - $line_discount + $line_tax);
            if (!$wpdb->insert($items_table, [
                'rand_id' => function_exists('bntm_rand_id') ? bntm_rand_id() : wp_generate_uuid4(),
                'business_id' => $business_id,
                'order_id' => $order_id,
                'inventory_product_id' => intval($line['inventory_product_id'] ?? 0),
                'product_rand_id' => sanitize_text_field($line['product_rand_id'] ?? ''),
                'product_name' => sanitize_text_field($line['product_name'] ?? ''),
                'sku' => sanitize_text_field($line['sku'] ?? ''),
                'unit' => sanitize_text_field($line['unit'] ?? 'unit'),
                'quantity' => $qty,
                'available_stock' => intval($line['available_stock'] ?? 0),
                'unit_price' => $price,
                'line_discount' => $line_discount,
                'line_tax' => $line_tax,
                'line_total' => $line_total,
                'notes' => sanitize_textarea_field($line['notes'] ?? ''),
            ])) throw new Exception('Failed to save order item.');
        }

        om_log_order_status($business_id, $order_id, $old_order ? $old_order->status : null, $status, $internal_notes);
        $wpdb->query('COMMIT');
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        wp_send_json_error(['message' => $e->getMessage()]);
    }

    $deduction_status = om_get_setting_for_business('om_stock_deduction_status', 'completed', $business_id);
    $new_order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$orders_table} WHERE id = %d AND business_id = %d", $order_id, $business_id));
    if ($new_order && $new_order->status === $deduction_status && empty($new_order->stock_deducted_at)) {
        $deduction = om_apply_stock_deduction_for_order($order_id);
        if (!$deduction['success']) {
            if ($old_order) {
                $wpdb->update($orders_table, ['status' => $old_order->status], ['id' => $order_id, 'business_id' => $business_id], ['%s'], ['%d', '%d']);
            }
            wp_send_json_error(['message' => 'Order saved, but stock deduction failed: ' . $deduction['message']]);
        }
    }

    if ($new_order && $new_order->status === 'completed' && $new_order->payment_status === 'paid') {
        $finance_sync = om_auto_export_order_if_ready($order_id, $business_id);
        if (!$finance_sync['success'] && empty($finance_sync['already_exported'])) {
            wp_send_json_error(['message' => 'Order saved, but Finance sync failed: ' . $finance_sync['message']]);
        }
    }

    wp_send_json_success(['message' => $old_order ? 'Order updated successfully.' : 'Order created successfully.']);
}

function bntm_ajax_om_delete_order() {
    check_ajax_referer('om_nonce', 'nonce');
    if (!om_current_user_can_manage_business()) wp_send_json_error(['message' => 'Unauthorized']);
    global $wpdb;
    $orders_table = $wpdb->prefix . 'om_orders';
    $items_table = $wpdb->prefix . 'om_order_items';
    $order_id = intval($_POST['order_id'] ?? 0);
    $business_id = om_get_current_business_id();
    $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$orders_table} WHERE id=%d AND business_id=%d", $order_id, $business_id));
    if (!$order) wp_send_json_error(['message' => 'Order not found.']);
    if (in_array($order->status, ['completed']) || $order->finance_export_status === 'exported') {
        wp_send_json_error(['message' => 'Completed or exported orders cannot be deleted.']);
    }
    $wpdb->query('START TRANSACTION');
    $wpdb->delete($items_table, ['order_id' => $order_id, 'business_id' => $business_id], ['%d', '%d']);
    $deleted = $wpdb->delete($orders_table, ['id' => $order_id, 'business_id' => $business_id], ['%d', '%d']);
    if ($deleted) {
        $wpdb->query('COMMIT');
        wp_send_json_success(['message' => 'Order deleted successfully.']);
    }
    $wpdb->query('ROLLBACK');
    wp_send_json_error(['message' => 'Unable to delete order.']);
}

function bntm_ajax_om_get_order() {
    check_ajax_referer('om_nonce', 'nonce');
    if (!om_current_user_can_manage_business()) wp_send_json_error(['message' => 'Unauthorized']);
    global $wpdb;
    $orders_table = $wpdb->prefix . 'om_orders';
    $items_table = $wpdb->prefix . 'om_order_items';
    $order_id = intval($_POST['order_id'] ?? 0);
    $business_id = om_get_current_business_id();
    $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$orders_table} WHERE id=%d AND business_id=%d", $order_id, $business_id));
    if (!$order) wp_send_json_error(['message' => 'Order not found.']);
    $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$items_table} WHERE order_id=%d AND business_id=%d ORDER BY id ASC", $order_id, $business_id));
    $order->ordered_at_local = date('Y-m-d\TH:i', strtotime($order->ordered_at));
    wp_send_json_success(['order' => $order, 'items' => $items]);
}

function bntm_ajax_om_change_order_status() {
    check_ajax_referer('om_nonce', 'nonce');
    if (!om_current_user_can_manage_business()) wp_send_json_error(['message' => 'Unauthorized']);
    global $wpdb;
    $orders_table = $wpdb->prefix . 'om_orders';
    $order_id = intval($_POST['order_id'] ?? 0);
    $new_status = sanitize_text_field($_POST['status'] ?? '');
    $remarks = sanitize_textarea_field($_POST['remarks'] ?? '');
    $business_id = om_get_current_business_id();
    $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$orders_table} WHERE id=%d AND business_id=%d", $order_id, $business_id));
    if (!$order) wp_send_json_error(['message' => 'Order not found.']);
    $updated = $wpdb->update($orders_table, [
        'status' => $new_status,
        'status_changed_at' => current_time('mysql'),
        'completed_at' => $new_status === 'completed' ? current_time('mysql') : $order->completed_at,
    ], ['id' => $order_id, 'business_id' => $business_id], ['%s', '%s', '%s'], ['%d', '%d']);
    if ($updated === false) wp_send_json_error(['message' => 'Unable to update order status.']);
    om_log_order_status($business_id, $order_id, $order->status, $new_status, $remarks);
    $deduction_status = om_get_setting_for_business('om_stock_deduction_status', 'completed', $business_id);
    if ($new_status === $deduction_status && empty($order->stock_deducted_at)) {
        $deduction = om_apply_stock_deduction_for_order($order_id);
        if (!$deduction['success']) wp_send_json_error(['message' => 'Status updated, but stock deduction failed: ' . $deduction['message']]);
    }
    $updated_order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$orders_table} WHERE id=%d AND business_id=%d", $order_id, $business_id));
    if ($updated_order && $updated_order->status === 'completed' && $updated_order->payment_status === 'paid') {
        $finance_sync = om_auto_export_order_if_ready($order_id, $business_id);
        if (!$finance_sync['success'] && empty($finance_sync['already_exported'])) {
            wp_send_json_error(['message' => 'Status updated, but Finance sync failed: ' . $finance_sync['message']]);
        }
    }
    wp_send_json_success(['message' => 'Order status updated successfully.']);
}

function bntm_ajax_om_duplicate_order() {
    check_ajax_referer('om_nonce', 'nonce');
    if (!om_current_user_can_manage_business()) wp_send_json_error(['message' => 'Unauthorized']);
    global $wpdb;
    $orders_table = $wpdb->prefix . 'om_orders';
    $items_table = $wpdb->prefix . 'om_order_items';
    $order_id = intval($_POST['order_id'] ?? 0);
    $business_id = om_get_current_business_id();
    $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$orders_table} WHERE id=%d AND business_id=%d", $order_id, $business_id));
    $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$items_table} WHERE order_id=%d AND business_id=%d", $order_id, $business_id));
    if (!$order) wp_send_json_error(['message' => 'Order not found.']);
    $wpdb->query('START TRANSACTION');
    $copied = [
        'rand_id' => function_exists('bntm_rand_id') ? bntm_rand_id() : wp_generate_uuid4(),
        'business_id' => $business_id,
        'order_number' => om_generate_order_number(),
        'transaction_category' => $order->transaction_category,
        'customer_name' => $order->customer_name,
        'customer_email' => $order->customer_email,
        'customer_phone' => $order->customer_phone,
        'customer_company' => $order->customer_company,
        'processor_user_id' => get_current_user_id(),
        'status' => 'open',
        'payment_status' => 'unpaid',
        'fulfillment_status' => 'pending',
        'subtotal' => $order->subtotal,
        'discount' => $order->discount,
        'tax' => $order->tax,
        'shipping_fee' => $order->shipping_fee,
        'total' => $order->total,
        'inventory_sync_mode' => $order->inventory_sync_mode,
        'finance_export_status' => 'not_exported',
        'source_module' => 'manual',
        'source_reference' => $order->order_number,
        'notes' => $order->notes,
        'internal_notes' => $order->internal_notes,
        'ordered_at' => current_time('mysql'),
    ];
    if (!$wpdb->insert($orders_table, $copied)) {
        $wpdb->query('ROLLBACK');
        wp_send_json_error(['message' => 'Unable to duplicate order.']);
    }
    $new_order_id = intval($wpdb->insert_id);
    foreach ($items as $item) {
        $wpdb->insert($items_table, [
            'rand_id' => function_exists('bntm_rand_id') ? bntm_rand_id() : wp_generate_uuid4(),
            'business_id' => $business_id,
            'order_id' => $new_order_id,
            'inventory_product_id' => $item->inventory_product_id,
            'product_rand_id' => $item->product_rand_id,
            'product_name' => $item->product_name,
            'sku' => $item->sku,
            'unit' => $item->unit,
            'quantity' => $item->quantity,
            'available_stock' => $item->available_stock,
            'unit_price' => $item->unit_price,
            'line_discount' => $item->line_discount,
            'line_tax' => $item->line_tax,
            'line_total' => $item->line_total,
            'notes' => $item->notes,
        ]);
    }
    $wpdb->query('COMMIT');
    wp_send_json_success(['message' => 'Order duplicated successfully.']);
}

function bntm_ajax_om_search_products() {
    check_ajax_referer('om_nonce', 'nonce');
    if (!om_current_user_can_manage_business()) wp_send_json_error(['message' => 'Unauthorized']);
    global $wpdb;
    $table = $wpdb->prefix . 'om_inventory_items';
    $business_id = om_get_current_business_id();
    $search = sanitize_text_field($_POST['search'] ?? '');
    $items = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE business_id = %d AND (name LIKE %s OR sku LIKE %s) ORDER BY name ASC LIMIT 20",
        $business_id,
        '%' . $wpdb->esc_like($search) . '%',
        '%' . $wpdb->esc_like($search) . '%'
    ));
    wp_send_json_success(['items' => $items]);
}

function bntm_ajax_om_import_items() {
    check_ajax_referer('om_nonce', 'nonce');
    if (!om_current_user_can_manage_business()) wp_send_json_error(['message' => 'Unauthorized']);
    global $wpdb;
    $current_table = $wpdb->prefix . 'om_inventory_items';
    $in_table      = $wpdb->prefix . 'in_products';
    $business_id   = om_get_current_business_id();
    $item_ids      = json_decode(stripslashes($_POST['item_ids'] ?? '[]'), true);
    if (empty($item_ids) || !is_array($item_ids)) wp_send_json_error(['message' => 'No inventory items selected.']);
    $placeholders = implode(',', array_fill(0, count($item_ids), '%d'));
    $query_params = array_merge([$business_id], $item_ids);
    $items = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$in_table} WHERE business_id = %d AND id IN ($placeholders)",
        $query_params
    ));
    if (empty($items)) wp_send_json_error(['message' => 'No inventory items found.']);
    $imported = 0;
    foreach ($items as $item) {
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$current_table} WHERE business_id = %d AND inventory_product_id = %d",
            $business_id,
            $item->id
        ));
        if ($exists) continue;
        $result = $wpdb->insert($current_table, [
            'rand_id'             => $item->rand_id,
            'business_id'         => $business_id,
            'inventory_product_id'=> $item->id,
            'product_rand_id'     => $item->rand_id,
            'name'                => $item->name,
            'sku'                 => $item->sku,
            'category'            => $item->inventory_type,
            'price'               => $item->selling_price,
            'stock'               => $item->stock_quantity,
            'status'              => 'active',
            'last_synced_at'      => current_time('mysql'),
        ], ['%s','%d','%d','%s','%s','%s','%s','%f','%d','%s','%s']);
        if ($result) $imported++;
    }
    wp_send_json_success(['message' => "Successfully imported {$imported} product(s)."]);
}

function bntm_ajax_om_refresh_stock() {
    check_ajax_referer('om_nonce', 'nonce');
    if (!om_current_user_can_manage_business()) wp_send_json_error(['message' => 'Unauthorized']);
    $result = om_refresh_synced_stock(om_get_current_business_id());
    if ($result['success']) wp_send_json_success(['message' => $result['message']]);
    wp_send_json_error(['message' => $result['message']]);
}

function bntm_ajax_om_save_categories() {
    check_ajax_referer('om_nonce', 'nonce');
    if (!om_current_user_can_manage_business()) wp_send_json_error(['message' => 'Unauthorized']);
    global $wpdb;
    $table = $wpdb->prefix . 'om_categories';
    $business_id = om_get_current_business_id();
    $text = sanitize_textarea_field($_POST['categories_text'] ?? '');
    $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $text))));
    if (empty($lines)) wp_send_json_error(['message' => 'Please provide at least one category.']);
    $wpdb->query('START TRANSACTION');
    $wpdb->delete($table, ['business_id' => $business_id], ['%d']);
    foreach ($lines as $index => $line) {
        $wpdb->insert($table, [
            'rand_id' => function_exists('bntm_rand_id') ? bntm_rand_id() : wp_generate_uuid4(),
            'business_id' => $business_id,
            'name' => $line,
            'description' => '',
            'sort_order' => $index + 1,
            'status' => 'active',
            'is_default' => $index === 0 ? 1 : 0,
        ]);
    }
    $wpdb->query('COMMIT');
    wp_send_json_success(['message' => 'Categories saved successfully.']);
}

function bntm_ajax_om_save_settings() {
    check_ajax_referer('om_nonce', 'nonce');
    if (!om_current_user_can_manage_business()) wp_send_json_error(['message' => 'Unauthorized']);
    $business_id = om_get_current_business_id();
    if (isset($_POST['order_prefix'])) om_update_setting_for_business('om_order_prefix', sanitize_text_field($_POST['order_prefix']), $business_id);
    if (isset($_POST['next_order_number'])) om_update_setting_for_business('om_next_order_number', max(1, intval($_POST['next_order_number'])), $business_id);
    if (isset($_POST['stock_deduction_status'])) om_update_setting_for_business('om_stock_deduction_status', sanitize_text_field($_POST['stock_deduction_status']), $business_id);
    if (isset($_POST['finance_category'])) om_update_setting_for_business('om_finance_category', sanitize_text_field($_POST['finance_category']), $business_id);
    if (isset($_POST['document_footer'])) om_update_setting_for_business('om_document_footer', sanitize_textarea_field($_POST['document_footer']), $business_id);
    if (isset($_POST['lookup_enabled'])) om_update_setting_for_business('om_lookup_enabled', sanitize_text_field($_POST['lookup_enabled']), $business_id);
    wp_send_json_success(['message' => 'Order Management settings saved successfully.']);
}

function om_auto_export_order_if_ready($order_id, $business_id = 0) {
    global $wpdb;

    $orders_table = $wpdb->prefix . 'om_orders';
    $exports_table = $wpdb->prefix . 'om_finance_exports';
    $fn_table = $wpdb->prefix . 'fn_transactions';
    $business_id = absint($business_id ?: om_get_current_business_id());
    if ($business_id <= 0 || $order_id <= 0) {
        return ['success' => false, 'message' => 'Order context unavailable.'];
    }

    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$orders_table} WHERE id = %d AND business_id = %d",
        $order_id,
        $business_id
    ));
    if (!$order) {
        return ['success' => false, 'message' => 'Order not found.'];
    }

    if ($order->status !== 'completed' || $order->payment_status !== 'paid') {
        return ['success' => false, 'message' => 'Order is not yet completed and paid.'];
    }

    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$fn_table} WHERE business_id = %d AND reference_type = %s AND reference_id = %d",
        $business_id,
        'om_order',
        $order_id
    ));
    if ($existing) {
        if ($order->finance_export_status !== 'exported') {
            $wpdb->update($orders_table, ['finance_export_status' => 'exported'], ['id' => $order_id, 'business_id' => $business_id], ['%s'], ['%d', '%d']);
        }
        return ['success' => true, 'message' => 'Order already exported.', 'already_exported' => true];
    }

    $category = om_get_setting_for_business('om_finance_category', 'Order Sales', $business_id);
    $inserted = $wpdb->insert($fn_table, [
        'rand_id' => function_exists('bntm_rand_id') ? bntm_rand_id() : wp_generate_uuid4(),
        'business_id' => $business_id,
        'type' => 'income',
        'amount' => $order->total,
        'category' => $category,
        'notes' => 'Order #' . $order->order_number,
        'reference_type' => 'om_order',
        'reference_id' => $order_id,
        'created_at' => current_time('mysql'),
    ], ['%s','%d','%s','%f','%s','%s','%s','%d','%s']);
    if (!$inserted) {
        return ['success' => false, 'message' => 'Failed to export order to Finance.'];
    }

    $fn_id = intval($wpdb->insert_id);
    $wpdb->insert($exports_table, [
        'rand_id' => function_exists('bntm_rand_id') ? bntm_rand_id() : wp_generate_uuid4(),
        'business_id' => $business_id,
        'order_id' => $order_id,
        'fn_transaction_id' => $fn_id,
        'status' => 'exported',
        'export_type' => 'income',
        'amount' => $order->total,
        'exported_at' => current_time('mysql'),
    ]);
    $wpdb->update($orders_table, ['finance_export_status' => 'exported'], ['id' => $order_id, 'business_id' => $business_id], ['%s'], ['%d', '%d']);
    if (function_exists('fn_update_cashflow_summary')) {
        fn_update_cashflow_summary($business_id);
    }

    return ['success' => true, 'message' => 'Order exported to Finance successfully.'];
}

function bntm_ajax_om_export_order() {
    check_ajax_referer('om_nonce', 'nonce');
    if (!om_current_user_can_manage_business()) wp_send_json_error(['message' => 'Unauthorized']);
    global $wpdb;
    $orders_table = $wpdb->prefix . 'om_orders';
    $exports_table = $wpdb->prefix . 'om_finance_exports';
    $fn_table = $wpdb->prefix . 'fn_transactions';
    $order_id = intval($_POST['order_id'] ?? 0);
    $business_id = om_get_current_business_id();
    $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$orders_table} WHERE id=%d AND business_id=%d", $order_id, $business_id));
    if (!$order) wp_send_json_error(['message' => 'Order not found.']);
    if (!in_array($order->status, ['completed']) || !in_array($order->payment_status, ['paid', 'partially_paid'])) {
        wp_send_json_error(['message' => 'Only completed orders with paid or partially paid status can be exported.']);
    }
    $result = om_auto_export_order_if_ready($order_id, $business_id);
    if (!$result['success']) wp_send_json_error(['message' => $result['message']]);
    if (!empty($result['already_exported'])) wp_send_json_error(['message' => 'This order is already exported to Finance.']);
    wp_send_json_success(['message' => $result['message']]);
}

function bntm_ajax_om_revert_order_export() {
    check_ajax_referer('om_nonce', 'nonce');
    if (!om_current_user_can_manage_business()) wp_send_json_error(['message' => 'Unauthorized']);
    global $wpdb;
    $orders_table = $wpdb->prefix . 'om_orders';
    $exports_table = $wpdb->prefix . 'om_finance_exports';
    $fn_table = $wpdb->prefix . 'fn_transactions';
    $order_id = intval($_POST['order_id'] ?? 0);
    $business_id = om_get_current_business_id();
    $order = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$orders_table} WHERE id = %d AND business_id = %d", $order_id, $business_id));
    if (!$order) wp_send_json_error(['message' => 'Order not found.']);
    $fn_id = $wpdb->get_var($wpdb->prepare(
        "SELECT fn_transaction_id FROM {$exports_table} WHERE order_id=%d AND business_id=%d AND status='exported' ORDER BY id DESC LIMIT 1",
        $order_id,
        $business_id
    ));
    if (!$fn_id) {
        $fn_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$fn_table} WHERE business_id=%d AND reference_type=%s AND reference_id=%d LIMIT 1",
            $business_id,
            'om_order',
            $order_id
        ));
    }
    if (!$fn_id) wp_send_json_error(['message' => 'No Finance export found for this order.']);
    $wpdb->delete($fn_table, ['id' => intval($fn_id), 'business_id' => $business_id], ['%d', '%d']);
    $wpdb->update($exports_table, ['status' => 'reverted', 'reverted_at' => current_time('mysql')], ['order_id' => $order_id, 'business_id' => $business_id], ['%s','%s'], ['%d','%d']);
    $wpdb->update($orders_table, ['finance_export_status' => 'not_exported'], ['id' => $order_id, 'business_id' => $business_id], ['%s'], ['%d', '%d']);
    if (function_exists('fn_update_cashflow_summary')) fn_update_cashflow_summary($business_id);
    wp_send_json_success(['message' => 'Finance export reverted successfully.']);
}

function bntm_ajax_om_mark_printed() {
    check_ajax_referer('om_nonce', 'nonce');
    if (!om_current_user_can_manage_business()) wp_send_json_error(['message' => 'Unauthorized']);
    global $wpdb;
    $orders_table = $wpdb->prefix . 'om_orders';
    $order_id = intval($_POST['order_id'] ?? 0);
    $business_id = om_get_current_business_id();
    $updated = $wpdb->update($orders_table, ['printed_at' => current_time('mysql')], ['id' => $order_id, 'business_id' => $business_id], ['%s'], ['%d', '%d']);
    if ($updated === false) wp_send_json_error(['message' => 'Unable to update print status.']);
    wp_send_json_success(['message' => 'Print timestamp updated.']);
}

/* ---------- FRONTEND SHORTCODE FUNCTIONS ---------- */

function bntm_shortcode_om_order_view() {
    $order_rand = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : '';
    if (!$order_rand) return '<div class="bntm-notice">No order selected.</div>';
    if (!is_user_logged_in()) return '<div class="bntm-notice">Please log in.</div>';
    global $wpdb;
    $orders_table = $wpdb->prefix . 'om_orders';
    $items_table = $wpdb->prefix . 'om_order_items';
    $business_id = om_get_current_business_id();
    $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$orders_table} WHERE rand_id=%s AND business_id=%d", $order_rand, $business_id));
    if (!$order) return '<div class="bntm-notice">Order not found.</div>';
    $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$items_table} WHERE order_id=%d AND business_id=%d ORDER BY id ASC", $order->id, $business_id));
    $processor = get_userdata($order->processor_user_id);
    $current_user = wp_get_current_user();
    $wpdb->update($orders_table, ['printed_at' => current_time('mysql')], ['id' => $order->id, 'business_id' => $business_id], ['%s'], ['%d', '%d']);
    $footer = om_get_setting_for_business('om_document_footer', 'Thank you for your order.', $business_id);
    ob_start();
    ?>
    <style>
    .om-print-view{max-width:980px;margin:24px auto;background:#fff;padding:28px;border:1px solid #e5e7eb;border-radius:16px;box-shadow:0 10px 24px rgba(15,23,42,.08);font-family:Arial,sans-serif;color:#0f172a}
    .om-print-head{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;padding-bottom:18px;border-bottom:2px solid #e5e7eb}
    .om-print-title{font-size:28px;font-weight:700;letter-spacing:.04em}.om-print-tag{display:inline-block;padding:5px 10px;background:#eff6ff;border-radius:999px;font-size:11px;font-weight:700;color:#1d4ed8}
    .om-print-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin:24px 0}.om-print-block h4{margin:0 0 10px;font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#64748b}.om-print-block p{margin:0;line-height:1.6}
    .om-print-table{width:100%;border-collapse:collapse;margin-top:16px}.om-print-table th,.om-print-table td{padding:12px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:13px}.om-print-table th{text-transform:uppercase;letter-spacing:.06em;color:#64748b;font-size:11px;background:#f8fafc}
    .om-print-total{margin-left:auto;max-width:320px;margin-top:20px}.om-print-total-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #e5e7eb}.om-print-total-row strong{font-size:18px}
    .om-print-notes{margin-top:24px;padding:16px;background:#f8fafc;border-radius:12px;border:1px solid #e5e7eb}.om-print-actions{text-align:center;margin-top:24px}.om-print-btn{background:var(--bntm-primary);color:#fff;padding:10px 18px;border:none;border-radius:10px;font-weight:600;cursor:pointer}
    @media print{.om-print-actions{display:none!important}.om-print-view{box-shadow:none;border:none;margin:0;max-width:none;padding:0}}@media (max-width:768px){.om-print-head,.om-print-grid{grid-template-columns:1fr;display:grid}}
    </style>
    <div class="om-print-view">
        <div class="om-print-head">
            <div><div class="om-print-title">ORDER TRANSACTION</div><div style="margin-top:8px;"><?php echo esc_html($current_user->display_name); ?></div><div style="color:#64748b;"><?php echo esc_html($current_user->user_email); ?></div></div>
            <div style="text-align:right;"><div class="om-print-tag"><?php echo esc_html(strtoupper($order->status)); ?></div><div style="margin-top:12px;font-size:24px;font-weight:700;"><?php echo esc_html($order->order_number); ?></div><div style="color:#64748b;margin-top:6px;">Date: <?php echo esc_html(date_i18n('M d, Y', strtotime($order->ordered_at))); ?></div><?php if ($order->due_date): ?><div style="color:#64748b;">Due Date: <?php echo esc_html(date_i18n('M d, Y', strtotime($order->due_date))); ?></div><?php endif; ?></div>
        </div>
        <div class="om-print-grid">
            <div class="om-print-block"><h4>Customer</h4><p><strong><?php echo esc_html($order->customer_name ?: 'Walk-in / Internal'); ?></strong><br><?php if ($order->customer_company): ?><?php echo esc_html($order->customer_company); ?><br><?php endif; ?><?php if ($order->customer_email): ?><?php echo esc_html($order->customer_email); ?><br><?php endif; ?><?php if ($order->customer_phone): ?><?php echo esc_html($order->customer_phone); ?><?php endif; ?></p></div>
            <div class="om-print-block"><h4>Transaction Summary</h4><p>Category: <?php echo esc_html($order->transaction_category); ?><br>Processor: <?php echo esc_html($processor ? $processor->display_name : 'Unknown'); ?><br>Payment: <?php echo esc_html(ucfirst(str_replace('_', ' ', $order->payment_status))); ?><br>Fulfillment: <?php echo esc_html(ucfirst(str_replace('_', ' ', $order->fulfillment_status))); ?></p></div>
        </div>
        <table class="om-print-table"><thead><tr><th>Item</th><th>SKU</th><th style="text-align:right;">Qty</th><th style="text-align:right;">Unit Price</th><th style="text-align:right;">Total</th></tr></thead><tbody><?php foreach ($items as $item): ?><tr><td><?php echo esc_html($item->product_name); ?></td><td><?php echo esc_html($item->sku ?: '—'); ?></td><td style="text-align:right;"><?php echo intval($item->quantity); ?></td><td style="text-align:right;"><?php echo esc_html(om_format_price($item->unit_price)); ?></td><td style="text-align:right;"><?php echo esc_html(om_format_price($item->line_total)); ?></td></tr><?php endforeach; ?></tbody></table>
        <div class="om-print-total">
            <div class="om-print-total-row"><span>Subtotal</span><span><?php echo esc_html(om_format_price($order->subtotal)); ?></span></div>
            <div class="om-print-total-row"><span>Discount</span><span><?php echo esc_html(om_format_price($order->discount)); ?></span></div>
            <div class="om-print-total-row"><span>Tax</span><span><?php echo esc_html(om_format_price($order->tax)); ?></span></div>
            <div class="om-print-total-row"><span>Shipping</span><span><?php echo esc_html(om_format_price($order->shipping_fee)); ?></span></div>
            <div class="om-print-total-row"><strong>Total</strong><strong><?php echo esc_html(om_format_price($order->total)); ?></strong></div>
        </div>
        <?php if ($order->notes || $footer): ?><div class="om-print-notes"><?php if ($order->notes): ?><p style="margin:0 0 12px;"><strong>Notes</strong><br><?php echo nl2br(esc_html($order->notes)); ?></p><?php endif; ?><p style="margin:0;"><?php echo nl2br(esc_html($footer)); ?></p></div><?php endif; ?>
        <div class="om-print-actions"><button onclick="window.print()" class="om-print-btn">Print / Download PDF</button></div>
    </div>
    <?php
    return ob_get_clean();
}

function bntm_shortcode_om_order_lookup() {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'om_orders';
    $search = isset($_GET['order_number']) ? sanitize_text_field($_GET['order_number']) : '';
    $order = null;
    if ($search !== '') {
        $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$orders_table} WHERE order_number = %s", $search));
        if ($order && om_get_setting_for_business('om_lookup_enabled', 'yes', $order->business_id) !== 'yes') {
            return '<div class="bntm-notice">Order lookup is currently disabled.</div>';
        }
    }
    ob_start();
    ?>
    <div style="max-width:760px;margin:24px auto;background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:24px;">
        <h2 style="margin-top:0;">Order Lookup</h2>
        <p style="color:#64748b;">Enter the order number to check current status and transaction summary.</p>
        <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;margin:16px 0 24px;">
            <input type="text" name="order_number" value="<?php echo esc_attr($search); ?>" placeholder="e.g. <?php echo esc_attr(bntm_get_setting('om_order_prefix', 'ORD')); ?>-000001" style="flex:1;min-width:220px;padding:12px;border:1px solid #dbe2ea;border-radius:10px;">
            <button type="submit" class="bntm-btn-primary">Lookup Order</button>
        </form>
        <?php if ($search !== '' && !$order): ?>
            <div class="bntm-notice">No order matched that reference number.</div>
        <?php elseif ($order): ?>
            <div style="border:1px solid #e5e7eb;border-radius:14px;padding:18px;background:#f8fafc;">
                <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                    <div><strong style="font-size:18px;"><?php echo esc_html($order->order_number); ?></strong><div style="color:#64748b;"><?php echo esc_html($order->customer_name ?: 'Walk-in / Internal'); ?></div></div>
                    <div style="text-align:right;"><span class="om-badge om-badge-<?php echo esc_attr($order->status); ?>"><?php echo esc_html(ucfirst(str_replace('_', ' ', $order->status))); ?></span><div style="margin-top:8px;color:#64748b;">Payment: <?php echo esc_html(ucfirst(str_replace('_', ' ', $order->payment_status))); ?></div></div>
                </div>
                <div style="margin-top:16px;color:#475569;">Ordered on <?php echo esc_html(date_i18n('M d, Y g:i A', strtotime($order->ordered_at))); ?><br>Total amount: <strong><?php echo esc_html(om_format_price($order->total)); ?></strong></div>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/* ---------- HELPER FUNCTIONS ---------- */

function om_currency_symbol() {
    $currency = om_get_setting_for_business('fn_currency', 'PHP');
    $symbols = ['USD' => '$', 'EUR' => 'EUR ', 'GBP' => 'GBP ', 'PHP' => 'PHP '];
    return $symbols[$currency] ?? 'PHP ';
}

function om_format_price($amount) {
    return om_currency_symbol() . number_format((float) $amount, 2);
}

function om_get_stats($business_id) {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'om_orders';
    $sync_table = $wpdb->prefix . 'om_inventory_items';
    $total_orders = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$orders_table} WHERE business_id = %d", $business_id));
    $completed_orders = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$orders_table} WHERE business_id = %d AND status='completed'", $business_id));
    $pending_orders = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$orders_table} WHERE business_id = %d AND status='pending'", $business_id));
    $processing_orders = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$orders_table} WHERE business_id = %d AND status='processing'", $business_id));
    $total_revenue = (float) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(total),0) FROM {$orders_table} WHERE business_id = %d AND status='completed'", $business_id));
    $avg_order_value = $completed_orders > 0 ? $total_revenue / $completed_orders : 0;
    $synced_products = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$sync_table} WHERE business_id = %d", $business_id));
    $low_stock_items = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$sync_table} WHERE business_id = %d AND stock <= 5", $business_id));
    return [
        'total_orders' => $total_orders,
        'completed_orders' => $completed_orders,
        'pending_orders' => $pending_orders,
        'active_queue' => $pending_orders + $processing_orders,
        'total_revenue' => $total_revenue,
        'avg_order_value' => $avg_order_value,
        'synced_products' => $synced_products,
        'low_stock_items' => $low_stock_items,
    ];
}

function om_check_dependencies() {
    $required = ['in', 'fn'];
    $missing = [];
    foreach ($required as $mod) {
        if (!bntm_is_module_enabled($mod)) $missing[] = $mod;
    }
    return empty($missing) ? true : $missing;
}

function om_get_status_options() {
    return [
        'open' => 'Open',
        'pending' => 'Pending',
        'processing' => 'Processing',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];
}

function om_get_payment_status_options() {
    return [
        'unpaid' => 'Unpaid',
        'partially_paid' => 'Partially Paid',
        'paid' => 'Paid',
    ];
}

function om_ensure_default_categories($business_id = 0) {
    if (!$business_id) return;
    global $wpdb;
    $table = $wpdb->prefix . 'om_categories';
    $exists = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE business_id=%d", $business_id));
    if ($exists > 0) return;
    $defaults = ['Direct Sale', 'Wholesale', 'Internal Request', 'Custom Order'];
    foreach ($defaults as $index => $name) {
        $wpdb->insert($table, [
            'rand_id' => function_exists('bntm_rand_id') ? bntm_rand_id() : wp_generate_uuid4(),
            'business_id' => $business_id,
            'name' => $name,
            'description' => '',
            'sort_order' => $index + 1,
            'status' => 'active',
            'is_default' => $index === 0 ? 1 : 0,
        ]);
    }
}

function om_get_categories($business_id) {
    global $wpdb;
    om_ensure_default_categories($business_id);
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}om_categories WHERE business_id=%d AND status='active' ORDER BY is_default DESC, sort_order ASC, name ASC",
        $business_id
    ));
}

function om_get_default_category_name($business_id) {
    $categories = om_get_categories($business_id);
    return !empty($categories) ? $categories[0]->name : 'Direct Sale';
}

function om_generate_order_number() {
    global $wpdb;

    $orders_table = $wpdb->prefix . 'om_orders';
    $business_id = om_get_current_business_id();
    $prefix = sanitize_text_field(om_get_setting_for_business('om_order_prefix', 'ORD', $business_id));
    $next = max(1, intval(om_get_setting_for_business('om_next_order_number', 1, $business_id)));

    do {
        $number = $prefix . '-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$orders_table} WHERE order_number = %s LIMIT 1", $number));
        $next++;
    } while ($exists);

    om_update_setting_for_business('om_next_order_number', $next, $business_id);

    return $number;
}

function om_get_synced_products($business_id) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}om_inventory_items WHERE business_id = %d ORDER BY name ASC",
        $business_id
    ));
}

function om_refresh_synced_stock($business_id) {
    global $wpdb;
    $current_table = $wpdb->prefix . 'om_inventory_items';
    $in_table = $wpdb->prefix . 'in_products';
    if (!bntm_is_module_enabled('in') || !bntm_is_module_visible('in')) {
        return ['success' => false, 'message' => 'Inventory module is not enabled.'];
    }
    $synced = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$current_table} WHERE business_id = %d",
        $business_id
    ));
    $updated = 0;
    foreach ($synced as $item) {
        $inventory = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$in_table} WHERE id = %d AND business_id = %d",
            $item->inventory_product_id,
            $business_id
        ));
        if (!$inventory) continue;
        $wpdb->update($current_table, [
            'name' => $inventory->name,
            'sku' => $inventory->sku,
            'category' => $inventory->inventory_type,
            'price' => $inventory->selling_price,
            'stock' => $inventory->stock_quantity,
            'status' => 'active',
            'last_synced_at' => current_time('mysql'),
        ], ['id' => $item->id, 'business_id' => $business_id], ['%s','%s','%s','%f','%d','%s','%s'], ['%d', '%d']);
        $updated++;
    }
    return ['success' => true, 'message' => "Refreshed {$updated} synced product record(s)."];
}

function om_log_order_status($business_id, $order_id, $old_status, $new_status, $remarks = '') {
    global $wpdb;
    $wpdb->insert($wpdb->prefix . 'om_order_status_logs', [
        'rand_id' => function_exists('bntm_rand_id') ? bntm_rand_id() : wp_generate_uuid4(),
        'business_id' => $business_id,
        'order_id' => $order_id,
        'old_status' => $old_status,
        'new_status' => $new_status,
        'changed_by' => get_current_user_id(),
        'remarks' => $remarks,
    ]);
}

function om_get_order_view_url($rand_id) {
    $page = get_page_by_path('order-transaction-document');
    $url = $page ? get_permalink($page->ID) : home_url('/order-transaction-document/');
    if ($rand_id !== '') $url = add_query_arg('order', $rand_id, $url);
    return $url;
}

function om_get_order_lookup_url() {
    $page = get_page_by_path('order-lookup');
    return $page ? get_permalink($page->ID) : home_url('/order-lookup/');
}

function om_update_stock_with_logging($product_rand_id, $quantity_change, $reference_number, $notes) {
    global $wpdb;
    $current_table   = $wpdb->prefix . 'om_inventory_items';
    $inventory_table = $wpdb->prefix . 'in_products';
    $batches_table   = $wpdb->prefix . 'in_batches';
    $business_id     = om_get_current_business_id();
    $wpdb->query('START TRANSACTION');
    try {
        $current_item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$current_table} WHERE product_rand_id=%s AND business_id=%d",
            $product_rand_id,
            $business_id
        ));
        if (!$current_item) throw new Exception('Item not found in Order Management sync table.');
        $inventory_item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$inventory_table} WHERE rand_id=%s AND business_id=%d",
            $product_rand_id,
            $business_id
        ));
        if (!$inventory_item) throw new Exception('Linked inventory product not found.');
        if ((int) $inventory_item->stock_quantity < (int) $quantity_change) {
            throw new Exception('Insufficient stock for ' . $inventory_item->name . '. Available: ' . $inventory_item->stock_quantity);
        }
        $new_stock = max(0, intval($inventory_item->stock_quantity) - intval($quantity_change));
        $in_updated = $wpdb->update($inventory_table, ['stock_quantity' => $new_stock], ['id' => $inventory_item->id, 'business_id' => $business_id], ['%d'], ['%d', '%d']);
        if ($in_updated === false) throw new Exception('Failed to update inventory stock');
        $mod_updated = $wpdb->update($current_table, ['stock' => $new_stock, 'last_synced_at' => current_time('mysql')], ['id' => $current_item->id, 'business_id' => $business_id], ['%d','%s'], ['%d', '%d']);
        if ($mod_updated === false) throw new Exception('Failed to update OM synced stock');
        $batch_inserted = $wpdb->insert($batches_table, [
            'rand_id'          => function_exists('bntm_rand_id') ? bntm_rand_id() : wp_generate_uuid4(),
            'business_id'      => $business_id,
            'product_id'       => $inventory_item->id,
            'batch_code'       => 'OM-' . $reference_number,
            'type'             => 'stock_out',
            'reference_number' => $reference_number,
            'quantity'         => intval($quantity_change),
            'cost_per_unit'    => 0.00,
            'total_cost'       => 0.00,
            'notes'            => $notes,
            'created_at'       => current_time('mysql')
        ], ['%s','%d','%d','%s','%s','%s','%d','%f','%f','%s','%s']);
        if (!$batch_inserted) throw new Exception('Failed to log inventory batch');
        $wpdb->query('COMMIT');
        return true;
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        error_log('OM Stock Update Error: ' . $e->getMessage());
        return $e->getMessage();
    }
}

function om_apply_stock_deduction_for_order($order_id) {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'om_orders';
    $items_table  = $wpdb->prefix . 'om_order_items';
    $business_id = om_get_current_business_id();
    $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$orders_table} WHERE id=%d AND business_id=%d", $order_id, $business_id));
    if (!$order) return ['success' => false, 'message' => 'Order not found.'];
    if (!empty($order->stock_deducted_at)) return ['success' => true, 'message' => 'Stock already deducted.'];
    $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$items_table} WHERE order_id=%d AND business_id=%d", $order_id, $business_id));
    if (empty($items)) return ['success' => false, 'message' => 'Order has no items to deduct.'];
    foreach ($items as $item) {
        $result = om_update_stock_with_logging($item->product_rand_id, intval($item->quantity), $order->order_number, 'Order completion stock deduction');
        if ($result !== true) return ['success' => false, 'message' => $result];
    }
    $wpdb->update($orders_table, ['stock_deducted_at' => current_time('mysql')], ['id' => $order_id, 'business_id' => $business_id], ['%s'], ['%d', '%d']);
    return ['success' => true, 'message' => 'Stock deduction completed.'];
}