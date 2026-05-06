<?php
/**
 * Module Name: Inventory Management 
 * Module Slug: in
 * Description: Complete inventory management with costing, batch tracking, and Finance integration
 * Version: 2.2.0
 */

if (!defined('ABSPATH')) exit;

define('BNTM_IN_PATH', dirname(__FILE__) . '/');
define('BNTM_IN_URL', plugin_dir_url(__FILE__));

if (!function_exists('in_current_user_can_manage_business')) {
    function in_current_user_can_manage_business() {
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

if (!function_exists('in_get_current_business_id')) {
    function in_get_current_business_id() {
        if (function_exists('bntm_get_current_business_id')) {
            $business_id = absint(bntm_get_current_business_id());
            if ($business_id > 0) {
                return $business_id;
            }
        }

        return absint(get_current_user_id());
    }
}

if (!function_exists('in_get_product_limit_for_business')) {
    function in_get_product_limit_for_business($business_id) {
        global $wpdb;

        $business_id = absint($business_id);
        $custom_limits = function_exists('bntm_get_business_option')
            ? bntm_get_business_option('custom_limits', [], $business_id)
            : [];

        if (isset($custom_limits['in_products'])) {
            return (int) $custom_limits['in_products'];
        }

        $table_limits = get_option('bntm_table_limits', []);
        $table = $wpdb->prefix . 'in_products';
        return isset($table_limits[$table]) ? (int) $table_limits[$table] : 0;
    }
}

if (!function_exists('in_get_setting_for_business')) {
    function in_get_setting_for_business($key, $default = '', $business_id = 0) {
        $business_id = absint($business_id ?: in_get_current_business_id());

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

if (!function_exists('in_update_setting_for_business')) {
    function in_update_setting_for_business($key, $value, $business_id = 0) {
        $business_id = absint($business_id ?: in_get_current_business_id());

        if ($business_id > 0 && function_exists('bntm_get_scoped_setting_option_key')) {
            return update_option(bntm_get_scoped_setting_option_key($key, $business_id), $value);
        }

        if (function_exists('bntm_update_setting')) {
            return bntm_update_setting($key, $value);
        }

        return update_option($key, $value);
    }
}

/* ---------- MODULE CONFIG ---------- */
function bntm_in_get_pages() {
    return ['Inventory' => '[in_dashboard]'];
}

function bntm_in_get_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $prefix  = $wpdb->prefix;
    return [
        'in_products' => "CREATE TABLE {$prefix}in_products (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            name VARCHAR(255) NOT NULL,
            sku VARCHAR(50),
            barcode VARCHAR(100) UNIQUE,
            inventory_type VARCHAR(50) DEFAULT 'Product',
            cost_per_unit DECIMAL(10,4) DEFAULT 0,
            selling_price DECIMAL(10,2) NOT NULL,
            stock_quantity INT DEFAULT 0,
            reorder_level INT DEFAULT 10,
            description TEXT,
            image VARCHAR(500),
            batch_yield INT DEFAULT 1,
            labor_cost_per_batch DECIMAL(10,2) DEFAULT 0,
            packaging_cost_per_unit DECIMAL(10,4) DEFAULT 0,
            variable_overhead_per_unit DECIMAL(10,4) DEFAULT 0,
            costing_materials LONGTEXT,
            costing_fixed_costs LONGTEXT,
            target_margin_pct DECIMAL(5,2) DEFAULT 30,
            selling_price_per_batch TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_sku (sku),
            INDEX idx_barcode (barcode),
            INDEX idx_stock (stock_quantity),
            INDEX idx_type (inventory_type)
        ) {$charset};",

        'in_batches' => "CREATE TABLE {$prefix}in_batches (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            product_id BIGINT UNSIGNED NOT NULL,
            batch_code VARCHAR(100) NOT NULL,
            type ENUM('stock_in','stock_out') DEFAULT 'stock_in',
            quantity INT NOT NULL,
            batches_count INT DEFAULT 1,
            cost_per_unit DECIMAL(10,4) DEFAULT 0,
            total_cost DECIMAL(10,2) NOT NULL,
            reference_number VARCHAR(100),
            manufacture_date DATE,
            notes TEXT,
            include_in_finance TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_product (product_id),
            INDEX idx_batch_code (batch_code),
            INDEX idx_type (type),
            INDEX idx_date (manufacture_date)
        ) {$charset};"
    ];
}

function bntm_in_get_shortcodes() {
    return ['in_dashboard' => 'bntm_in_shortcode_dashboard'];
}

function bntm_in_create_tables() {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $tables = bntm_in_get_tables();
    foreach ($tables as $sql) { dbDelta($sql); }
    return count($tables);
}

function in_normalize_material_name($name) {
    $name = strtolower(trim((string) $name));
    $name = preg_replace('/\s*\(material\)\s*$/i', '', $name);
    return preg_replace('/\s+/', ' ', $name);
}

function in_get_raw_material_records($business_id = 0) {
    global $wpdb;
    $table = $wpdb->prefix . 'in_products';
    $business_id = absint($business_id ?: in_get_current_business_id());
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name, stock_quantity, cost_per_unit FROM {$table} WHERE business_id = %d AND inventory_type='Raw Material' ORDER BY name ASC",
        $business_id
    ));
    $records = [];
    foreach ($rows as $row) {
        $records[in_normalize_material_name($row->name)] = [
            'id' => (int) $row->id,
            'name' => $row->name,
            'stock_quantity' => (float) $row->stock_quantity,
            'cost_per_unit' => (float) $row->cost_per_unit,
        ];
    }
    return $records;
}

function in_sanitize_costing_materials($materials_raw) {
    $materials = is_array($materials_raw) ? $materials_raw : [];
    $normalized_materials = [];
    $seen_materials = [];

    foreach ($materials as $material) {
        $material_name   = sanitize_text_field($material['name'] ?? '');
        $normalized_name = in_normalize_material_name($material_name);
        if ($normalized_name === '') continue;
        if (isset($seen_materials[$normalized_name])) {
            wp_send_json_error(['message' => 'Duplicate material names are not allowed.']);
        }
        $seen_materials[$normalized_name] = true;

        $from_inventory = !empty($material['fromInventory']);
        $normalized_materials[] = [
            'name' => $material_name,
            'qty' => max(0.001, floatval($material['qty'] ?? 0)),
            'uom' => sanitize_text_field($material['uom'] ?? 'pcs'),
            'cost' => max(0, floatval($material['cost'] ?? 0)),
            'addToInventory' => $from_inventory ? false : !empty($material['addToInventory']),
            'fromInventory' => $from_inventory,
            'inventory_id' => absint($material['inventory_id'] ?? 0),
        ];
    }

    return $normalized_materials;
}

function in_calculate_batch_material_cost($materials, $business_id = 0) {
    $materials = is_array($materials) ? $materials : [];
    return array_reduce($materials, function($carry, $material) {
        return $carry + floatval($material['cost'] ?? 0);
    }, 0.0);
}

function in_material_is_inventory_backed($material, $business_id = 0) {
    $name = in_normalize_material_name($material['name'] ?? '');
    if ($name === '') {
        return false;
    }
    if (!empty($material['fromInventory'])) {
        return true;
    }

    $business_id = absint($business_id ?: in_get_current_business_id());
    if ($business_id <= 0) {
        return false;
    }

    static $raw_cache = [];
    if (!isset($raw_cache[$business_id])) {
        $raw_cache[$business_id] = in_get_raw_material_records($business_id);
    }

    return isset($raw_cache[$business_id][$name]);
}

function in_calculate_stock_in_finance_expense($product, $batch) {
    $inventory_type = $product->inventory_type ?? '';
    if (in_array($inventory_type, ['Raw Material', 'Supplies', 'Other', 'Equipment'], true)) {
        return floatval($batch->total_cost);
    }

    $business_id = absint($product->business_id ?? $batch->business_id ?? 0);
    $materials = json_decode($product->costing_materials ?: '[]', true) ?: [];
    $fixed_costs = json_decode($product->costing_fixed_costs ?: '[]', true) ?: [];
    $units = max(0, floatval($batch->quantity));
    $batches_count = max(1, intval($batch->batches_count ?: 1));

    $external_material_batch_cost = 0;
    foreach ($materials as $material) {
        if (!in_material_is_inventory_backed($material, $business_id)) {
            $external_material_batch_cost += max(0, floatval($material['cost'] ?? 0));
        }
    }

    $labor_total = max(0, floatval($product->labor_cost_per_batch ?? 0)) * $batches_count;
    $packaging_total = max(0, floatval($product->packaging_cost_per_unit ?? 0)) * $units;
    $variable_total = max(0, floatval($product->variable_overhead_per_unit ?? 0)) * $units;
    $fixed_total = array_reduce($fixed_costs, function($carry, $fixed) use ($units) {
        return $carry + (max(0, floatval($fixed['amount'] ?? 0)) * $units);
    }, 0.0);

    return $external_material_batch_cost * $batches_count + $labor_total + $packaging_total + $variable_total + $fixed_total;
}

/* ---------- AJAX REGISTRATIONS ---------- */
add_action('wp_ajax_in_add_product',           'bntm_ajax_in_add_product');
add_action('wp_ajax_in_update_product',        'bntm_ajax_in_update_product');
add_action('wp_ajax_in_delete_product',        'bntm_ajax_in_delete_product');
add_action('wp_ajax_in_add_batch',             'bntm_ajax_in_add_batch');
add_action('wp_ajax_in_delete_batch',          'bntm_ajax_in_delete_batch');
add_action('wp_ajax_in_import_batch_expense',  'bntm_ajax_in_import_batch_expense');
add_action('wp_ajax_in_revert_batch_expense',  'bntm_ajax_in_revert_batch_expense');
add_action('wp_ajax_in_upload_product_image',  'bntm_ajax_in_upload_product_image');
add_action('wp_ajax_in_save_settings',         'bntm_ajax_in_save_settings');

/* ============================================================
   SHARED SVG ICONS
   ============================================================ */
function in_icon($name, $size = 20) {
    $icons = [
        'box'         => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',
        'layers'      => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>',
        'dollar'      => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
        'trending-up' => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>',
        'alert'       => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        'x-circle'    => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
        'arrow-down'  => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>',
        'arrow-up'    => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>',
        'cpu'         => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="1" x2="9" y2="4"/><line x1="15" y1="1" x2="15" y2="4"/><line x1="9" y1="20" x2="9" y2="23"/><line x1="15" y1="20" x2="15" y2="23"/><line x1="20" y1="9" x2="23" y2="9"/><line x1="20" y1="14" x2="23" y2="14"/><line x1="1" y1="9" x2="4" y2="9"/><line x1="1" y1="14" x2="4" y2="14"/></svg>',
        'refresh'     => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.5"/></svg>',
        'check'       => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
        'image'       => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
        'tag'         => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>',
        'list'        => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>',
        'flask'       => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3h6m-6 0v6.5L4.5 16a4 4 0 0 0 3.55 5.88h7.9A4 4 0 0 0 19.5 16L15 9.5V3M9 3h6"/></svg>',
        'bar-chart'   => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/><line x1="2" y1="20" x2="22" y2="20"/></svg>',
        'settings'    => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
        'upload'      => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>',
        'package'     => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',
        'info'        => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
        'zap'         => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>',
        'clock'       => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        'filter'      => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>',
    ];
    return $icons[$name] ?? '';
}

/* ============================================================
   MAIN SHORTCODE
   ============================================================ */
function bntm_in_shortcode_dashboard() {
    if (!in_current_user_can_manage_business()) {
        return '<div class="bntm-notice">Please log in to access the Inventory dashboard.</div>';
    }
    $business_id = in_get_current_business_id();
    if ($business_id <= 0) {
        return '<div class="bntm-notice">Business context is unavailable.</div>';
    }
    $active_tab  = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
    ob_start(); ?>
    <div class="bntm-inventory-container">
        <div class="bntm-tabs">
            <a href="?tab=overview"  class="bntm-tab <?php echo $active_tab==='overview'  ?'active':''; ?>">Overview</a>
            <a href="?tab=products"  class="bntm-tab <?php echo $active_tab==='products'  ?'active':''; ?>">Products</a>
            <a href="?tab=batches"   class="bntm-tab <?php echo $active_tab==='batches'   ?'active':''; ?>">Batches</a>
            <?php if (function_exists('bntm_is_module_enabled') && bntm_is_module_enabled('fn') && bntm_is_module_visible('fn')): ?>
            <a href="?tab=import"    class="bntm-tab <?php echo $active_tab==='import'    ?'active':''; ?>">Import to Finance</a>
            <?php endif; ?>
            <a href="?tab=settings"  class="bntm-tab <?php echo $active_tab==='settings'  ?'active':''; ?>">Settings</a>
        </div>
        <div class="bntm-tab-content">
            <?php
            if     ($active_tab==='overview')  echo in_overview_tab($business_id);
            elseif ($active_tab==='products')  echo in_products_tab($business_id);
            elseif ($active_tab==='batches')   echo in_batches_tab($business_id);
            elseif ($active_tab==='import')    echo in_import_tab($business_id);
            elseif ($active_tab==='settings')  echo in_settings_tab($business_id);
            ?>
        </div>
    </div>
    <?php
    $content = ob_get_clean();
    return function_exists('bntm_universal_container')
        ? bntm_universal_container('Inventory Management', $content)
        : $content;
}

/* ============================================================
   OVERVIEW TAB — ENHANCED: full material stock display
   ============================================================ */
function in_overview_tab($business_id) {
    global $wpdb;
    $pt = $wpdb->prefix.'in_products';
    $bt = $wpdb->prefix.'in_batches';

    $total_products        = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $pt WHERE business_id = %d", $business_id));
    $total_stock           = $wpdb->get_var($wpdb->prepare("SELECT SUM(stock_quantity) FROM $pt WHERE business_id = %d", $business_id));
    $low_stock_items       = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $pt WHERE business_id = %d AND stock_quantity <= reorder_level AND stock_quantity > 0", $business_id));
    $out_of_stock          = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $pt WHERE business_id = %d AND stock_quantity = 0", $business_id));
    $total_inventory_value = $wpdb->get_var($wpdb->prepare("SELECT SUM(stock_quantity * cost_per_unit) FROM $pt WHERE business_id = %d", $business_id));
    $potential_revenue     = $wpdb->get_var($wpdb->prepare("SELECT SUM(stock_quantity * selling_price) FROM $pt WHERE business_id = %d", $business_id));
    $stock_in_count        = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $bt WHERE business_id = %d AND type='stock_in'  AND DATE(created_at)>=DATE_SUB(NOW(),INTERVAL 30 DAY)", $business_id));
    $stock_out_count       = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $bt WHERE business_id = %d AND type='stock_out' AND DATE(created_at)>=DATE_SUB(NOW(),INTERVAL 30 DAY)", $business_id));
    $top_products          = $wpdb->get_results($wpdb->prepare("SELECT name,stock_quantity,reorder_level FROM $pt WHERE business_id = %d AND stock_quantity>0 ORDER BY stock_quantity DESC LIMIT 10", $business_id), ARRAY_A) ?: [];
    $inventory_by_type     = $wpdb->get_results($wpdb->prepare("SELECT inventory_type,COUNT(*) as count,SUM(stock_quantity) as total_stock FROM $pt WHERE business_id = %d GROUP BY inventory_type", $business_id), ARRAY_A);
    $stock_status          = $wpdb->get_results($wpdb->prepare("SELECT CASE WHEN stock_quantity=0 THEN 'Out of Stock' WHEN stock_quantity<=reorder_level THEN 'Low Stock' ELSE 'In Stock' END as status,COUNT(*) as count FROM $pt WHERE business_id = %d GROUP BY status", $business_id), ARRAY_A);
    $recent_transactions   = $wpdb->get_results($wpdb->prepare("SELECT b.*,p.name as product_name FROM $bt b LEFT JOIN $pt p ON b.product_id=p.id WHERE b.business_id = %d ORDER BY b.created_at DESC LIMIT 10", $business_id));
    $low_stock_products    = $wpdb->get_results($wpdb->prepare("SELECT * FROM $pt WHERE business_id = %d AND stock_quantity<=reorder_level ORDER BY stock_quantity ASC LIMIT 10", $business_id));

    /* --- build raw materials map --- */
    $all_products = $wpdb->get_results($wpdb->prepare("SELECT * FROM $pt WHERE business_id = %d ORDER BY name ASC", $business_id));
    $production_readiness = [];
    $raw_materials_map = [];

    foreach ($all_products as $p) {
        if ($p->inventory_type === 'Raw Material') {
            $key = strtolower(trim($p->name));
            $raw_materials_map[$key] = [
                'stock' => (float)$p->stock_quantity,
                'reorder' => (int)$p->reorder_level,
                'cost' => (float)$p->cost_per_unit,
            ];
            $clean = preg_replace('/\s*\(material\)\s*$/i', '', $key);
            $raw_materials_map[$clean] = $raw_materials_map[$key];
        }
    }

    foreach ($all_products as $p) {
        $mats = json_decode($p->costing_materials ?: '[]', true) ?: [];
        if (empty($mats)) continue;
        $yld = max(1, intval($p->batch_yield));
        $can_produce = PHP_INT_MAX;
        $material_details = [];

        foreach ($mats as $m) {
            $mat_key = strtolower(trim($m['name']));
            $mat_data = $raw_materials_map[$mat_key] ?? null;
            $avail = $mat_data ? $mat_data['stock'] : null;
            $reorder = $mat_data ? $mat_data['reorder'] : 0;
            $qty_needed_per_batch = floatval($m['qty'] ?? 0);

            $material_details[] = [
                'name'             => $m['name'],
                'qty_per_batch'    => $qty_needed_per_batch,
                'uom'              => $m['uom'] ?? '',
                'available'        => $avail,
                'reorder'          => $reorder,
                'tracked'          => $avail !== null,
                'batches_possible' => ($avail !== null && $qty_needed_per_batch > 0) ? floor($avail / $qty_needed_per_batch) : ($avail !== null ? PHP_INT_MAX : null),
            ];

            if ($avail !== null) {
                $batches_possible = $qty_needed_per_batch > 0 ? floor($avail / $qty_needed_per_batch) : PHP_INT_MAX;
                $can_produce = min($can_produce, $batches_possible);
            }
        }

        $total_units = $can_produce === PHP_INT_MAX ? null : ($can_produce * $yld);
        $has_untracked = array_filter($material_details, fn($md) => !$md['tracked']);
        $production_readiness[] = [
            'name'             => $p->name,
            'type'             => $p->inventory_type,
            'yield'            => $yld,
            'can_batches'      => $can_produce === PHP_INT_MAX ? 'N/A' : $can_produce,
            'can_units'        => $total_units,
            'materials'        => $material_details,
            'has_materials'    => !empty($mats),
            'has_untracked'    => !empty($has_untracked),
        ];
    }
    $production_readiness = array_filter($production_readiness, fn($r) => $r['has_materials']);
    $production_readiness = array_values($production_readiness);

    $pot_profit = floatval($potential_revenue) - floatval($total_inventory_value);

    ob_start(); ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:16px;">
        <div>
            <h3 style="margin:0;font-size:18px;">Inventory overview</h3>
            <p style="margin:4px 0 0;color:#6b7280;font-size:13px;">Stock position, production readiness, and quick actions.</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="<?php echo add_query_arg('tab','products',get_permalink()); ?>" class="bntm-btn-primary bntm-btn-small">+ Add Product</a>
            <a href="<?php echo add_query_arg('tab','batches',get_permalink()); ?>" class="bntm-btn-secondary bntm-btn-small">+ Add Batch</a>
        </div>
    </div>

    <style>
    .in-dashboard-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:24px;}
    .in-stat-card{background:#fff;padding:20px;border-radius:10px;display:flex;align-items:flex-start;gap:14px;border:1px solid #e5e7eb;transition:box-shadow .2s;}
    .in-stat-card:hover{box-shadow:0 4px 12px rgba(0,0,0,.07);}
    .in-stat-icon{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    .in-stat-icon.ic-neutral{background:#f3f4f6;color:#374151;}
    .in-stat-icon.ic-green{background:#ecfdf5;color:#059669;}
    .in-stat-icon.ic-red{background:#fef2f2;color:#dc2626;}
    .in-stat-icon.ic-amber{background:#fffbeb;color:#d97706;}
    .in-stat-icon.ic-blue{background:#eff6ff;color:#2563eb;}
    .in-stat-content h3{margin:0 0 4px;font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:.06em;font-weight:600;}
    .in-stat-number{font-size:24px;font-weight:700;color:#111827;margin:0;line-height:1.1;}
    .in-stat-content small{color:#9ca3af;font-size:11px;}
    .in-charts-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;margin-bottom:24px;}
    .in-chart-card{background:#fff;padding:20px;border-radius:10px;border:1px solid #e5e7eb;}
    .in-chart-card h3{margin:0 0 16px;font-size:14px;font-weight:600;color:#111827;display:flex;align-items:center;gap:8px;}
    .in-chart-card canvas{max-height:280px;}
    .in-chart-large{grid-column:1/-1;}
    .in-alert-section,.in-recent-section,.in-production-section{background:#fff;padding:20px;border-radius:10px;border:1px solid #e5e7eb;margin-bottom:16px;}
    .in-section-title{margin:0 0 16px;font-size:15px;font-weight:600;color:#111827;display:flex;align-items:center;gap:8px;}
    .in-prod-card{background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:14px;margin-bottom:10px;}
    .in-prod-card-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;}
    .in-prod-card-name{font-weight:600;font-size:13px;color:#111827;}
    .in-prod-badge{font-size:11px;padding:3px 8px;border-radius:99px;font-weight:600;}
    .badge-ok{background:#dcfce7;color:#166534;}
    .badge-limited{background:#fef9c3;color:#854d0e;}
    .badge-cannot{background:#fee2e2;color:#991b1b;}
    .badge-na{background:#f3f4f6;color:#6b7280;}
    /* Materials table inside production card */
    .in-mat-table{width:100%;border-collapse:collapse;font-size:12px;margin-top:8px;}
    .in-mat-table th{background:#f1f5f9;color:#475569;font-weight:600;text-transform:uppercase;font-size:10px;letter-spacing:.05em;padding:5px 8px;text-align:left;border-bottom:1px solid #e2e8f0;}
    .in-mat-table td{padding:6px 8px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
    .in-mat-table tr:last-child td{border-bottom:none;}
    .in-mat-table tr.mat-row-ok td{background:#f0fdf4;}
    .in-mat-table tr.mat-row-bad td{background:#fff5f5;}
    .in-mat-table tr.mat-row-untracked td{background:#fffbeb;}
    .in-mat-avail-bar{height:6px;border-radius:3px;background:#e5e7eb;overflow:hidden;margin-top:3px;min-width:60px;}
    .in-mat-avail-fill{height:100%;border-radius:3px;transition:width .3s;}
    .fill-ok{background:#10b981;}
    .fill-low{background:#f59e0b;}
    .fill-zero{background:#ef4444;}
    .in-mat-chip{font-size:10px;padding:2px 6px;border-radius:99px;display:inline-block;font-weight:600;}
    .chip-ok{background:#dcfce7;color:#166534;}
    .chip-missing{background:#fee2e2;color:#991b1b;}
    .chip-untracked{background:#fef9c3;color:#854d0e;}
    </style>

    <div class="in-dashboard-stats">
        <div class="in-stat-card">
            <div class="in-stat-icon ic-neutral"><?php echo in_icon('box', 22); ?></div>
            <div class="in-stat-content">
                <h3>Total Products</h3>
                <p class="in-stat-number"><?php echo $total_products ?: 0; ?></p>
                <small>Unique items tracked</small>
            </div>
        </div>
        <div class="in-stat-card">
            <div class="in-stat-icon ic-blue"><?php echo in_icon('layers', 22); ?></div>
            <div class="in-stat-content">
                <h3>Total Stock Units</h3>
                <p class="in-stat-number"><?php echo number_format($total_stock ?: 0); ?></p>
                <small>Items in inventory</small>
            </div>
        </div>
        <div class="in-stat-card">
            <div class="in-stat-icon ic-neutral"><?php echo in_icon('dollar', 22); ?></div>
            <div class="in-stat-content">
                <h3>Inventory Value</h3>
                <p class="in-stat-number">&#8369;<?php echo number_format($total_inventory_value ?: 0, 2); ?></p>
                <small>Total cost value</small>
            </div>
        </div>
        <div class="in-stat-card">
            <div class="in-stat-icon ic-green"><?php echo in_icon('trending-up', 22); ?></div>
            <div class="in-stat-content">
                <h3>Potential Revenue</h3>
                <p class="in-stat-number">&#8369;<?php echo number_format($potential_revenue ?: 0, 2); ?></p>
                <small>If all stock sold</small>
            </div>
        </div>
        <div class="in-stat-card">
            <div class="in-stat-icon <?php echo $pot_profit >= 0 ? 'ic-green' : 'ic-red'; ?>"><?php echo in_icon('bar-chart', 22); ?></div>
            <div class="in-stat-content">
                <h3>Potential Profit</h3>
                <p class="in-stat-number" style="color:<?php echo $pot_profit >= 0 ? '#059669' : '#dc2626'; ?>">&#8369;<?php echo number_format($pot_profit, 2); ?></p>
                <small>Revenue minus cost</small>
            </div>
        </div>
        <div class="in-stat-card">
            <div class="in-stat-icon <?php echo $low_stock_items > 0 ? 'ic-amber' : 'ic-green'; ?>"><?php echo in_icon('alert', 22); ?></div>
            <div class="in-stat-content">
                <h3>Low Stock Alerts</h3>
                <p class="in-stat-number" style="color:<?php echo $low_stock_items > 0 ? '#d97706' : '#059669'; ?>"><?php echo $low_stock_items; ?></p>
                <small>At or below reorder level</small>
            </div>
        </div>
        <div class="in-stat-card">
            <div class="in-stat-icon <?php echo $out_of_stock > 0 ? 'ic-red' : 'ic-green'; ?>"><?php echo in_icon('x-circle', 22); ?></div>
            <div class="in-stat-content">
                <h3>Out of Stock</h3>
                <p class="in-stat-number" style="color:<?php echo $out_of_stock > 0 ? '#dc2626' : '#059669'; ?>"><?php echo $out_of_stock; ?></p>
                <small>Items with 0 stock</small>
            </div>
        </div>
        <div class="in-stat-card">
            <div class="in-stat-icon ic-green"><?php echo in_icon('arrow-down', 22); ?></div>
            <div class="in-stat-content">
                <h3>Stock In (30d)</h3>
                <p class="in-stat-number" style="color:#059669;"><?php echo $stock_in_count; ?></p>
                <small>Incoming transactions</small>
            </div>
        </div>
        <div class="in-stat-card">
            <div class="in-stat-icon ic-red"><?php echo in_icon('arrow-up', 22); ?></div>
            <div class="in-stat-content">
                <h3>Stock Out (30d)</h3>
                <p class="in-stat-number" style="color:#dc2626;"><?php echo $stock_out_count; ?></p>
                <small>Outgoing transactions</small>
            </div>
        </div>
    </div>

    <div class="in-charts-grid">
        <div class="in-chart-card in-chart-large">
            <h3><?php echo in_icon('bar-chart', 16); ?> Top 10 Products by Stock Level</h3>
            <canvas id="productStockChart"></canvas>
        </div>
        <div class="in-chart-card">
            <h3><?php echo in_icon('layers', 16); ?> Inventory by Type</h3>
            <canvas id="inventoryTypeChart"></canvas>
        </div>
        <div class="in-chart-card">
            <h3><?php echo in_icon('cpu', 16); ?> Stock Status</h3>
            <canvas id="stockStatusChart"></canvas>
        </div>
    </div>

    <!-- ====================================================
         PRODUCTION READINESS — enhanced with full material table
         ==================================================== -->
    <?php if (!empty($production_readiness)): ?>
    <div class="in-production-section">
        <div class="in-section-title"><?php echo in_icon('zap', 18); ?> Production Readiness &amp; Material Stock</div>
        <p style="font-size:13px;color:#6b7280;margin:0 0 16px;">Based on current raw material stock. All required materials are shown below each product with current available stock, needed quantity per batch, and how many batches can be produced.</p>

        <?php foreach ($production_readiness as $pr):
            $cb = $pr['can_batches'];
            $cu = $pr['can_units'];
            if ($cb === 'N/A') { $badge = 'badge-na'; $badge_text = 'No Material Stocks Tracked'; }
            elseif ($cb == 0)  { $badge = 'badge-cannot'; $badge_text = 'Cannot Produce'; }
            elseif ($cb < 3)   { $badge = 'badge-limited'; $badge_text = 'Limited Stock'; }
            else               { $badge = 'badge-ok'; $badge_text = 'Ready to Produce'; }
        ?>
        <div class="in-prod-card">
            <div class="in-prod-card-header">
                <div>
                    <span class="in-prod-card-name"><?php echo esc_html($pr['name']); ?></span>
                    <span style="font-size:11px;color:#6b7280;margin-left:8px;"><?php echo esc_html($pr['type']); ?> &bull; Yield: <?php echo $pr['yield']; ?> unit<?php echo $pr['yield']!=1?'s':''; ?>/batch</span>
                </div>
                <span class="in-prod-badge <?php echo $badge; ?>"><?php echo $badge_text; ?></span>
            </div>

            <?php if ($cb !== 'N/A'): ?>
            <div style="font-size:12px;color:#374151;margin-bottom:10px;padding:8px 10px;background:#f1f5f9;border-radius:6px;display:inline-flex;align-items:center;gap:16px;flex-wrap:wrap;">
                <span>Can produce: <strong style="color:<?php echo $cb==0?'#dc2626':($cb<3?'#d97706':'#059669'); ?>"><?php echo $cb; ?> batch<?php echo $cb!=1?'es':''; ?></strong></span>
                <?php if ($cu !== null): ?>
                <span style="color:#6b7280;">&rarr;</span>
                <span>Total units: <strong style="color:<?php echo $cu==0?'#dc2626':($cu<3?'#d97706':'#059669'); ?>"><?php echo $cu; ?> unit<?php echo $cu!=1?'s':''; ?></strong></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Materials table -->
            <table class="in-mat-table">
                <thead>
                    <tr>
                        <th>Material</th>
                        <th>Needed / batch</th>
                        <th>Available Stock</th>
                        <th>Reorder Level</th>
                        <th>Batches Possible</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pr['materials'] as $md):
                    if (!$md['tracked']) {
                        $row_class = 'mat-row-untracked';
                        $fill_class = 'fill-zero';
                        $fill_pct = 0;
                        $chip_class = 'chip-untracked';
                        $chip_text = 'Not Tracked';
                        $batches_disp = '—';
                    } else {
                        $avail = $md['available'];
                        $reorder = $md['reorder'];
                        $needed = $md['qty_per_batch'];
                        $bp = $md['batches_possible'];
                        if ($bp === null) $bp = 0;
                        $fill_pct = $reorder > 0 ? min(100, ($avail / max($reorder, $needed)) * 100) : ($avail > 0 ? 100 : 0);
                        if ($avail <= 0) { $row_class='mat-row-bad'; $fill_class='fill-zero'; $chip_class='chip-missing'; $chip_text='Out of Stock'; }
                        elseif ($bp == 0) { $row_class='mat-row-bad'; $fill_class='fill-zero'; $chip_class='chip-missing'; $chip_text='Insufficient'; }
                        elseif ($avail <= $reorder) { $row_class='mat-row-untracked'; $fill_class='fill-low'; $chip_class='chip-untracked'; $chip_text='Low Stock'; }
                        else { $row_class='mat-row-ok'; $fill_class='fill-ok'; $chip_class='chip-ok'; $chip_text='Sufficient'; }
                        $batches_disp = $bp === PHP_INT_MAX ? '∞' : $bp;
                    }
                ?>
                <tr class="<?php echo $row_class; ?>">
                    <td><strong><?php echo esc_html($md['name']); ?></strong></td>
                    <td><?php echo number_format($md['qty_per_batch'], 3); ?> <?php echo esc_html($md['uom']); ?></td>
                    <td>
                        <?php if ($md['tracked']): ?>
                            <span style="font-weight:600;"><?php echo number_format($md['available'], 2); ?></span>
                            <div class="in-mat-avail-bar"><div class="in-mat-avail-fill <?php echo $fill_class; ?>" style="width:<?php echo $fill_pct; ?>%"></div></div>
                        <?php else: ?>
                            <span style="color:#9ca3af;font-style:italic;">No inventory record</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $md['tracked'] ? number_format($md['reorder']) : '—'; ?></td>
                    <td><strong><?php echo $batches_disp; ?></strong></td>
                    <td><span class="in-mat-chip <?php echo $chip_class; ?>"><?php echo $chip_text; ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($low_stock_products)): ?>
    <div class="in-alert-section">
        <div class="in-section-title"><?php echo in_icon('alert', 18); ?> Low Stock Alerts</div>
        <div class="bntm-table-wrapper"><table class="bntm-table">
            <thead><tr><th>Product</th><th>Type</th><th>Stock</th><th>Reorder</th><th>Deficit</th><th>Status</th></tr></thead>
            <tbody><?php foreach ($low_stock_products as $p): ?>
            <tr>
                <td style="font-weight:500;"><?php echo esc_html($p->name); ?></td>
                <td><?php echo esc_html($p->inventory_type); ?></td>
                <td><?php echo $p->stock_quantity; ?></td>
                <td><?php echo $p->reorder_level; ?></td>
                <td style="color:#dc2626;"><?php echo max(0, $p->reorder_level - $p->stock_quantity); ?></td>
                <td><?php echo $p->stock_quantity == 0
                    ? '<span style="background:#fee2e2;color:#991b1b;font-size:11px;font-weight:600;padding:2px 8px;border-radius:99px;">Out of Stock</span>'
                    : '<span style="background:#fef2f2;color:#dc2626;font-size:11px;font-weight:600;padding:2px 8px;border-radius:99px;">Low Stock</span>'; ?></td>
            </tr>
            <?php endforeach; ?></tbody>
        </table></div>
    </div>
    <?php endif; ?>

    <div class="in-recent-section">
        <div class="in-section-title"><?php echo in_icon('clock', 18); ?> Recent Transactions</div>
        <?php if (empty($recent_transactions)): ?>
            <p style="color:#9ca3af;font-size:13px;">No transactions recorded yet.</p>
        <?php else: ?>
        <div class="bntm-table-wrapper"><table class="bntm-table">
            <thead><tr><th>Date</th><th>Type</th><th>Reference</th><th>Product</th><th>Qty</th><th>Total Cost</th></tr></thead>
            <tbody><?php foreach ($recent_transactions as $t): ?>
            <tr>
                <td><?php echo date('M d, Y', strtotime($t->created_at)); ?></td>
                <td><?php echo $t->type === 'stock_in'
                    ? '<span style="display:inline-flex;align-items:center;gap:4px;color:#059669;font-weight:600;font-size:12px;">'.in_icon('arrow-down',14).' Stock In</span>'
                    : '<span style="display:inline-flex;align-items:center;gap:4px;color:#dc2626;font-weight:600;font-size:12px;">'.in_icon('arrow-up',14).' Stock Out</span>'; ?></td>
                <td><?php echo esc_html($t->reference_number ?: $t->batch_code); ?></td>
                <td><?php echo esc_html($t->product_name); ?></td>
                <td><?php echo $t->quantity; ?></td>
                <td>&#8369;<?php echo number_format($t->total_cost, 2); ?></td>
            </tr>
            <?php endforeach; ?></tbody>
        </table></div>
        <?php endif; ?>
    </div>

    <script>
    (function(){
        const tp=<?php echo json_encode($top_products); ?>;
        const colors=['#374151','#6b7280','#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#06b6d4','#84cc16'];
        const psc=document.getElementById('productStockChart');
        if(psc&&tp.length>0){new Chart(psc,{type:'bar',data:{labels:tp.map(p=>p.name),datasets:[{label:'Stock',data:tp.map(p=>parseInt(p.stock_quantity)),backgroundColor:colors.slice(0,tp.length)},{label:'Reorder',data:tp.map(p=>parseInt(p.reorder_level)),type:'line',borderColor:'#f59e0b',borderDash:[5,5],pointRadius:0,backgroundColor:'transparent'}]},options:{indexAxis:'y',responsive:true,maintainAspectRatio:true,plugins:{legend:{position:'top'}}}});}
        const itc=document.getElementById('inventoryTypeChart');
        if(itc){const d=<?php echo json_encode($inventory_by_type); ?>;if(d&&d.length>0)new Chart(itc,{type:'doughnut',data:{labels:d.map(t=>t.inventory_type),datasets:[{data:d.map(t=>parseInt(t.total_stock)),backgroundColor:['#374151','#6b7280','#9ca3af','#d1d5db','#e5e7eb'],borderColor:'#fff',borderWidth:2}]},options:{responsive:true,maintainAspectRatio:true,plugins:{legend:{position:'bottom'}}}});}
        const ssc=document.getElementById('stockStatusChart');
        if(ssc){const d=<?php echo json_encode($stock_status); ?>;if(d&&d.length>0)new Chart(ssc,{type:'pie',data:{labels:d.map(s=>s.status),datasets:[{data:d.map(s=>parseInt(s.count)),backgroundColor:['#10b981','#f59e0b','#ef4444'],borderColor:'#fff',borderWidth:2}]},options:{responsive:true,maintainAspectRatio:true,plugins:{legend:{position:'bottom'}}}});}
    })();
    </script>
    <?php return ob_get_clean();
}

/* ============================================================
   PRODUCTS TAB — with inventory type filter dropdown
   ============================================================ */
function in_products_tab($business_id) {
    global $wpdb;
    $table    = $wpdb->prefix.'in_products';
    $products = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE business_id = %d ORDER BY id DESC", $business_id));
    $raw_material_catalog = array_values(array_map(function($item) {
        return [
            'id' => $item['id'],
            'name' => $item['name'],
            'normalized' => in_normalize_material_name($item['name']),
            'stock_quantity' => $item['stock_quantity'],
            'cost_per_unit' => $item['cost_per_unit'],
        ];
    }, in_get_raw_material_records($business_id)));
    $nonce    = wp_create_nonce('in_nonce');
    $upnonce  = wp_create_nonce('in_upload_image');

    $current_count  = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE business_id = %d", $business_id));
    $product_limit  = in_get_product_limit_for_business($business_id);
    $limit_text     = $product_limit > 0 ? " ({$current_count}/{$product_limit})" : " ({$current_count})";
    $limit_reached  = $product_limit > 0 && $current_count >= $product_limit;
    $def_margin     = floatval(function_exists('bntm_get_setting') ? bntm_get_setting('fn_target_margin', 30) : 30);

    // Collect all distinct inventory types for the filter
    $all_types = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT inventory_type FROM $table WHERE business_id = %d ORDER BY inventory_type ASC", $business_id));

    ob_start(); ?>
<script>var ajaxurl='<?php echo admin_url('admin-ajax.php'); ?>';</script>

<style>
/* OM-style modal */
.in-modal{position:fixed;inset:0;background:rgba(15,23,42,.6);z-index:1000;display:flex;align-items:flex-start;justify-content:center;padding:24px 16px;overflow-y:auto;}
.in-modal-content{background:#fff;border-radius:14px;width:100%;max-width:880px;box-shadow:0 20px 60px rgba(0,0,0,.18);display:flex;flex-direction:column;margin:auto;}
.in-modal-header{display:flex;justify-content:space-between;align-items:flex-start;padding:22px 28px 0;border-bottom:1px solid #e5e7eb;padding-bottom:16px;}
.in-modal-header h3{margin:0;font-size:17px;font-weight:700;color:#0f172a;}
.in-modal-header p{margin:4px 0 0;font-size:13px;color:#64748b;}
.in-modal-close-btn{background:none;border:none;cursor:pointer;width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#64748b;font-size:20px;transition:.15s;}
.in-modal-close-btn:hover{background:#f1f5f9;color:#0f172a;}
.in-modal-body{padding:20px 28px 28px;overflow-y:auto;max-height:calc(100vh - 180px);}
.in-section-block{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:18px;margin-bottom:18px;}
.in-section-block-title{font-size:12px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.07em;display:flex;align-items:center;gap:6px;margin:0 0 14px;}
.in-inline-fields{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;}
.in-field{display:flex;flex-direction:column;gap:4px;}
.in-field label{font-size:12px;font-weight:600;color:#374151;}
.in-field input,.in-field select,.in-field textarea{padding:8px 10px;border:1px solid #d1d5db;border-radius:7px;font-size:13px;background:#fff;width:100%;box-sizing:border-box;transition:.15s;}
.in-field input:focus,.in-field select:focus,.in-field textarea:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.12);}
.in-field input[readonly]{background:#f3f4f6;color:#6b7280;}
.in-field textarea{resize:vertical;min-height:68px;}
.in-mat-row-hdr{display:grid;grid-template-columns:2fr 1fr 70px 1fr 36px;gap:6px;font-size:10px;color:#6b7280;text-transform:uppercase;letter-spacing:.04em;padding:0 4px;margin-bottom:4px;}
.in-mat-row{display:grid;grid-template-columns:2fr 1fr 70px 1fr 36px;gap:6px;margin-bottom:5px;align-items:center;}
.in-mat-input{font-size:12px;padding:5px 7px;border:1px solid #d1d5db;border-radius:6px;width:100%;box-sizing:border-box;}
.in-fixed-row-hdr{display:grid;grid-template-columns:2fr 1fr 36px;gap:6px;font-size:10px;color:#6b7280;text-transform:uppercase;letter-spacing:.04em;padding:0 4px;margin-bottom:4px;}
.in-fixed-row{display:grid;grid-template-columns:2fr 1fr 36px;gap:6px;margin-bottom:5px;align-items:center;}
.in-summary-box{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:14px;margin-top:12px;}
.in-summary-row{display:flex;justify-content:space-between;align-items:center;padding:5px 0;font-size:13px;border-bottom:1px solid #f1f5f9;}
.in-summary-row:last-child{border-bottom:none;}
.in-toggle{display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;color:#374151;}
.in-toggle input{appearance:none;width:36px;height:20px;background:#d1d5db;border-radius:99px;cursor:pointer;transition:.2s;position:relative;}
.in-toggle input:checked{background:#3b82f6;}
.in-toggle input::after{content:'';position:absolute;top:2px;left:2px;width:16px;height:16px;background:#fff;border-radius:50%;transition:.2s;}
.in-toggle input:checked::after{left:18px;}
.in-mat-suggest{margin-top:6px;font-size:12px;color:#6b7280;}
.in-mat-suggest strong{color:#1f2937;}
.in-mat-exists{color:#166534;font-weight:600;}
.in-mat-warning{color:#b45309;font-weight:600;}
.in-mat-suggest-list{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px;}
.in-mat-suggest-btn{border:1px solid #bfdbfe;background:#eff6ff;color:#1d4ed8;border-radius:999px;padding:5px 10px;font-size:12px;cursor:pointer;}
.in-mat-suggest-btn:hover{background:#dbeafe;}
.in-linked-note{font-size:11px;color:#2563eb;margin-top:4px;}
.in-modal-footer{display:flex;gap:10px;padding:16px 28px;border-top:1px solid #e5e7eb;background:#f8fafc;border-radius:0 0 14px 14px;}
/* upload area */
.bntm-upload-area{border:2px dashed #d1d5db;border-radius:8px;padding:20px;text-align:center;background:#f9fafb;transition:.2s;}
.bntm-upload-area.dragover{border-color:#3b82f6;background:#eff6ff;}
.in-product-image-preview{position:relative;display:inline-block;margin-bottom:12px;padding:8px;border:2px solid #e5e7eb;border-radius:8px;background:#f9fafb;}
.in-product-image-preview img{max-width:160px;max-height:160px;display:block;}
.bntm-btn-remove-logo{position:absolute;top:-10px;right:-10px;background:#ef4444;color:#fff;border:none;border-radius:50%;width:26px;height:26px;cursor:pointer;font-size:13px;line-height:1;}
/* filter bar */
.in-filter-bar{display:flex;align-items:center;gap:10px;margin-bottom:14px;flex-wrap:wrap;}
.in-filter-bar select{padding:7px 10px;border:1px solid #d1d5db;border-radius:7px;font-size:13px;background:#fff;}
</style>

<!-- ADD PRODUCT MODAL -->
<div id="add-product-modal" class="in-modal" style="display:none;">
  <div class="in-modal-content">
    <div class="in-modal-header">
      <div>
        <h3>Add New Product / Item</h3>
        <p>Fill in product details, costing, and materials below.</p>
      </div>
      <button type="button" class="in-modal-close-btn close-in-modal" data-modal="add-product-modal">&times;</button>
    </div>
    <div class="in-modal-body">
    <form id="in-add-product-form" class="bntm-form">

      <!-- Image -->
      <div class="in-section-block">
        <div class="in-section-block-title"><?php echo in_icon('image', 14); ?> Product Image</div>
        <div class="in-product-image-preview" id="product-image-preview" style="display:none;">
          <img src="" alt=""><button type="button" class="bntm-btn-remove-logo" id="remove-product-image">&#10005;</button>
        </div>
        <div class="bntm-upload-area" id="product-upload-area">
          <input type="file" id="product-image-upload" accept="image/*" style="display:none;">
          <button type="button" class="bntm-btn bntm-btn-secondary" id="product-upload-btn">Choose Image</button>
          <p style="margin:8px 0;color:#6b7280;font-size:13px;">or drag and drop here</p>
          <small>JPG / PNG, max 2 MB</small>
        </div>
        <input type="hidden" id="product_image" name="product_image">
      </div>

      <!-- Basic Info -->
      <div class="in-section-block">
        <div class="in-section-block-title"><?php echo in_icon('tag', 14); ?> Basic Information</div>
        <div class="in-inline-fields">
          <div class="in-field" style="grid-column:1/-1;">
            <label>Product / Item Name *</label>
            <input type="text" name="name" id="add-name" required>
          </div>
          <div class="in-field">
            <label>Inventory Type *</label>
            <select name="inventory_type" id="add-inventory-type" required>
              <option value="Product">Product</option>
              <option value="Raw Material">Raw Material</option>
              <option value="Finished Goods">Finished Goods</option>
              <option value="Supplies">Supplies</option>
              <option value="Equipment">Equipment</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div class="in-field">
            <label>SKU</label>
            <input type="text" name="sku" placeholder="Auto-generated if blank">
          </div>
          <div class="in-field" id="add-selling-price-wrap">
            <label>Selling Price / unit *</label>
            <input type="number" name="selling_price" id="add-selling-price" step="0.01" min="0" required>
          </div>
          <div class="in-field" id="add-direct-cost-wrap" style="display:none;">
            <label>Cost Per Unit *</label>
            <input type="number" name="direct_cost_per_unit" id="add-direct-cost" step="0.0001" min="0">
            <small style="color:#6b7280;font-size:11px;">Used for raw materials, supplies, and other cost-only items.</small>
          </div>
          <div class="in-field">
            <label>Reorder Level *</label>
            <input type="number" name="reorder_level" value="10" required min="0">
          </div>
          <div class="in-field">
            <label>Initial Stock Qty</label>
            <input type="number" name="initial_stock" value="0" min="0">
          </div>
          <div class="in-field" id="add-target-margin-wrap">
            <label>Target Margin %</label>
            <input type="number" id="add-target-margin" name="target_margin_pct" value="<?php echo $def_margin; ?>" min="1" max="99">
          </div>
          <div class="in-field" style="grid-column:1/-1;">
            <label>Description</label>
            <textarea name="description" placeholder="Product description"></textarea>
          </div>
        </div>
      </div>

      <!-- Costing -->
      <div class="in-section-block" id="add-costing-section">
        <div class="in-section-block-title"><?php echo in_icon('settings', 14); ?> Product Costing</div>
        <div class="in-inline-fields">
          <div class="in-field">
            <label>Batch yield (units/batch)</label>
            <input type="number" id="add-yield" name="batch_yield" value="1" min="1" step="1">
            <small style="color:#6b7280;font-size:11px;">e.g. 1 recipe → 12 cupcakes = 12</small>
          </div>
          <div class="in-field">
            <label>Labor cost / batch</label>
            <input type="number" id="add-labor" name="labor_cost_per_batch" value="0" step="0.01" min="0">
          </div>
          <div class="in-field">
            <label>Packaging cost / unit</label>
            <input type="number" id="add-pkg" name="packaging_cost_per_unit" value="0" step="0.01" min="0">
          </div>
          <div class="in-field">
            <label>Variable overhead / unit</label>
            <input type="number" id="add-var" name="variable_overhead_per_unit" value="0" step="0.01" min="0">
            <small style="color:#6b7280;font-size:11px;">Gas, electricity, consumables</small>
          </div>
        </div>

        <!-- Materials -->
        <div style="margin-top:16px;padding-top:14px;border-top:1px solid #e2e8f0;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <strong style="font-size:13px;">Batch Materials / Ingredients</strong>
          </div>
          <small style="display:block;color:#6b7280;font-size:11px;margin-bottom:8px;">Enter the total line cost for the quantity used in one batch. Raw material records will store cost per unit automatically.</small>
          <div class="in-mat-row-hdr">
            <div>Material</div><div>Qty/batch</div><div>UOM</div><div>Line Cost</div><div></div>
          </div>
          <div id="add-mat-list"></div>
          <div class="in-mat-row" style="margin-top:6px;">
            <input type="text"   id="add-mat-name" class="in-mat-input" placeholder="e.g. Flour">
            <input type="number" id="add-mat-qty"  class="in-mat-input" placeholder="1" value="1" step="0.001" min="0.001">
            <input type="text"   id="add-mat-uom"  class="in-mat-input" placeholder="kg">
            <input type="number" id="add-mat-cost" class="in-mat-input" placeholder="0.00" step="0.0001" min="0">
            <button type="button" id="add-mat-btn" class="bntm-btn-small bntm-btn-secondary" style="padding:5px 8px;white-space:nowrap;">+Add</button>
          </div>
          <div id="add-mat-suggest" class="in-mat-suggest"></div>
          <label class="in-toggle" id="add-mat-create-wrap" style="display:none;margin-top:6px;">
            <input type="checkbox" id="add-mat-create-toggle">
            <span>Create this as a new Raw Material item when saving</span>
          </label>
          <div id="add-mat-total" style="text-align:right;font-size:12px;color:#6b7280;margin-top:6px;">Batch material cost: &#8369;0.00</div>
        </div>

        <!-- Fixed Costs -->
        <div style="margin-top:14px;padding-top:12px;border-top:1px solid #e2e8f0;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <strong style="font-size:13px;">Fixed Cost Allocations / unit</strong>
            <small style="color:#6b7280;">e.g. rent ÷ expected monthly units</small>
          </div>
          <div class="in-fixed-row-hdr"><div>Description</div><div>Amount/unit</div><div></div></div>
          <div id="add-fixed-list"></div>
          <div class="in-fixed-row" style="margin-top:6px;">
            <input type="text"   id="add-fixed-name" class="in-mat-input" placeholder="e.g. Rent allocation">
            <input type="number" id="add-fixed-amt"  class="in-mat-input" placeholder="0.00" step="0.01" min="0">
            <button type="button" id="add-fixed-btn" class="bntm-btn-small bntm-btn-secondary" style="padding:5px 8px;white-space:nowrap;">+Add</button>
          </div>
          <div id="add-fixed-total" style="text-align:right;font-size:12px;color:#6b7280;margin-top:6px;">Total fixed alloc.: &#8369;0.00</div>
        </div>

        <!-- Live Summary -->
        <div class="in-summary-box" id="add-cost-summary">
          <div style="font-size:13px;font-weight:600;margin-bottom:8px;display:flex;align-items:center;gap:6px;"><?php echo in_icon('bar-chart', 15); ?> Live Cost Breakdown</div>
          <div class="in-summary-row"><span>Batch yield</span><span id="add-ls-yield">1 unit</span></div>
          <div class="in-summary-row"><span>Batch material cost</span><span id="add-ls-mat">&#8369;0.00</span></div>
          <div class="in-summary-row"><span>Material cost / unit</span><span id="add-ls-mat-pu">&#8369;0.00</span></div>
          <div class="in-summary-row"><span>Labor / unit</span><span id="add-ls-labor">&#8369;0.00</span></div>
          <div class="in-summary-row"><span>Packaging / unit</span><span id="add-ls-pkg">&#8369;0.00</span></div>
          <div class="in-summary-row"><span>Variable overhead / unit</span><span id="add-ls-var">&#8369;0.00</span></div>
          <div class="in-summary-row"><span>Fixed allocation / unit</span><span id="add-ls-fixed">&#8369;0.00</span></div>
          <div class="in-summary-row" style="border-top:2px solid #e2e8f0;margin-top:4px;font-weight:600;"><span>Total cost / unit</span><span id="add-ls-total" style="color:#ef4444;">&#8369;0.00</span></div>
          <div class="in-summary-row"><span>Selling price / unit</span><span id="add-ls-sell" style="color:#10b981;">&#8369;0.00</span></div>
          <div class="in-summary-row"><span>Contribution margin / unit</span><span id="add-ls-contrib">&#8369;0.00</span></div>
          <div class="in-summary-row" style="font-size:15px;"><span>Gross margin</span><span id="add-ls-margin" style="font-weight:700;">—</span></div>
          <div class="in-summary-row"><span>Break-even (fixed alloc.)</span><span id="add-ls-be">—</span></div>
          <div style="border-top:2px solid #e2e8f0;margin-top:8px;padding-top:8px;">
            <div style="font-size:12px;font-weight:600;color:#374151;margin-bottom:6px;text-transform:uppercase;letter-spacing:.04em;">Per Batch Totals</div>
            <div class="in-summary-row"><span>Total cost for 1 batch</span><span id="add-ls-batch-cost" style="font-weight:600;color:#374151;">&#8369;0.00</span></div>
            <div class="in-summary-row"><span>Potential revenue (1 batch)</span><span id="add-ls-batch-rev" style="color:#10b981;font-weight:600;">&#8369;0.00</span></div>
            <div class="in-summary-row" style="font-size:14px;"><span>Potential profit (1 batch)</span><span id="add-ls-batch-profit" style="font-weight:700;">&#8369;0.00</span></div>
          </div>
        </div>

        <input type="hidden" name="costing_materials"   id="add-costing-materials">
        <input type="hidden" name="costing_fixed_costs" id="add-costing-fixed">
      </div>

      <div id="add-product-msg" style="margin-top:8px;"></div>
    </form>
    </div>
    <div class="in-modal-footer">
      <button type="submit" form="in-add-product-form" class="bntm-btn-primary">Add Product</button>
      <button type="button" class="close-in-modal bntm-btn-secondary" data-modal="add-product-modal">Cancel</button>
    </div>
  </div>
</div>

<!-- EDIT PRODUCT MODAL -->
<div id="edit-product-modal" class="in-modal" style="display:none;">
  <div class="in-modal-content">
    <div class="in-modal-header">
      <div>
        <h3>Edit Product / Item</h3>
        <p>Update product details, costing, and materials.</p>
      </div>
      <button type="button" class="in-modal-close-btn close-in-modal" data-modal="edit-product-modal">&times;</button>
    </div>
    <div class="in-modal-body">
    <form id="in-edit-product-form" class="bntm-form">
      <input type="hidden" id="edit-product-id" name="product_id">

      <!-- Image -->
      <div class="in-section-block">
        <div class="in-section-block-title"><?php echo in_icon('image', 14); ?> Product Image</div>
        <div class="in-product-image-preview" id="edit-product-image-preview" style="display:none;">
          <img src="" alt=""><button type="button" class="bntm-btn-remove-logo" id="remove-edit-product-image">&#10005;</button>
        </div>
        <div class="bntm-upload-area" id="edit-product-upload-area">
          <input type="file" id="edit-product-image-upload" accept="image/*" style="display:none;">
          <button type="button" class="bntm-btn bntm-btn-secondary" id="edit-product-upload-btn">Choose Image</button>
          <p style="margin:8px 0;color:#6b7280;font-size:13px;">or drag and drop here</p>
          <small>JPG / PNG, max 2 MB</small>
        </div>
        <input type="hidden" id="edit-product_image" name="product_image">
      </div>

      <!-- Basic Info -->
      <div class="in-section-block">
        <div class="in-section-block-title"><?php echo in_icon('tag', 14); ?> Basic Information</div>
        <div class="in-inline-fields">
          <div class="in-field" style="grid-column:1/-1;">
            <label>Product / Item Name *</label>
            <input type="text" id="edit-name" name="name" required>
          </div>
          <div class="in-field">
            <label>Inventory Type *</label>
            <select id="edit-inventory-type" name="inventory_type" required>
              <option value="Product">Product</option><option value="Raw Material">Raw Material</option>
              <option value="Finished Goods">Finished Goods</option><option value="Supplies">Supplies</option>
              <option value="Equipment">Equipment</option><option value="Other">Other</option>
            </select>
          </div>
          <div class="in-field">
            <label>SKU</label>
            <input type="text" id="edit-sku" name="sku">
          </div>
          <div class="in-field" id="edit-selling-price-wrap">
            <label>Selling Price / unit *</label>
            <input type="number" id="edit-selling-price" name="selling_price" step="0.01" required min="0">
          </div>
          <div class="in-field" id="edit-direct-cost-wrap" style="display:none;">
            <label>Cost Per Unit *</label>
            <input type="number" name="direct_cost_per_unit" id="edit-direct-cost" step="0.0001" min="0">
            <small style="color:#6b7280;font-size:11px;">Used for raw materials, supplies, and other cost-only items.</small>
          </div>
          <div class="in-field">
            <label>Reorder Level *</label>
            <input type="number" id="edit-reorder-level" name="reorder_level" required min="0">
          </div>
          <div class="in-field">
            <label>Current Stock</label>
            <input type="number" id="edit-current-stock" readonly>
            <small style="color:#6b7280;font-size:11px;">Adjust via Batches tab</small>
          </div>
          <div class="in-field" id="edit-target-margin-wrap">
            <label>Target Margin %</label>
            <input type="number" id="edit-target-margin" name="target_margin_pct" value="<?php echo $def_margin; ?>" min="1" max="99">
          </div>
          <div class="in-field" style="grid-column:1/-1;">
            <label>Description</label>
            <textarea id="edit-description" name="description"></textarea>
          </div>
        </div>
      </div>

      <!-- Costing -->
      <div class="in-section-block" id="edit-costing-section">
        <div class="in-section-block-title"><?php echo in_icon('settings', 14); ?> Product Costing</div>
        <div class="in-inline-fields">
          <div class="in-field">
            <label>Batch yield</label>
            <input type="number" id="edit-yield" name="batch_yield" value="1" min="1" step="1">
          </div>
          <div class="in-field">
            <label>Labor cost / batch</label>
            <input type="number" id="edit-labor" name="labor_cost_per_batch" value="0" step="0.01" min="0">
          </div>
          <div class="in-field">
            <label>Packaging cost / unit</label>
            <input type="number" id="edit-pkg" name="packaging_cost_per_unit" value="0" step="0.01" min="0">
          </div>
          <div class="in-field">
            <label>Variable overhead / unit</label>
            <input type="number" id="edit-var" name="variable_overhead_per_unit" value="0" step="0.01" min="0">
          </div>
        </div>

        <!-- Materials -->
        <div style="margin-top:16px;padding-top:14px;border-top:1px solid #e2e8f0;">
          <strong style="font-size:13px;">Batch Materials / Ingredients</strong>
          <small style="display:block;color:#6b7280;font-size:11px;margin:6px 0 0;">Enter the total line cost for the quantity used in one batch. Raw material records will store cost per unit automatically.</small>
          <div class="in-mat-row-hdr" style="margin-top:8px;">
            <div>Material</div><div>Qty/batch</div><div>UOM</div><div>Line Cost</div><div></div>
          </div>
          <div id="edit-mat-list"></div>
          <div class="in-mat-row" style="margin-top:6px;">
            <input type="text"   id="edit-mat-name" class="in-mat-input" placeholder="e.g. Flour">
            <input type="number" id="edit-mat-qty"  class="in-mat-input" placeholder="1" value="1" step="0.001" min="0.001">
            <input type="text"   id="edit-mat-uom"  class="in-mat-input" placeholder="kg">
            <input type="number" id="edit-mat-cost" class="in-mat-input" placeholder="0.00" step="0.0001" min="0">
            <button type="button" id="edit-mat-btn" class="bntm-btn-small bntm-btn-secondary" style="padding:5px 8px;white-space:nowrap;">+Add</button>
          </div>
          <div id="edit-mat-suggest" class="in-mat-suggest"></div>
          <label class="in-toggle" id="edit-mat-create-wrap" style="display:none;margin-top:6px;">
            <input type="checkbox" id="edit-mat-create-toggle">
            <span>Create this as a new Raw Material item when saving</span>
          </label>
          <div id="edit-mat-total" style="text-align:right;font-size:12px;color:#6b7280;margin-top:6px;">Batch material cost: &#8369;0.00</div>
        </div>

        <!-- Fixed Costs -->
        <div style="margin-top:14px;padding-top:12px;border-top:1px solid #e2e8f0;">
          <strong style="font-size:13px;">Fixed Cost Allocations / unit</strong>
          <div class="in-fixed-row-hdr" style="margin-top:8px;"><div>Description</div><div>Amount/unit</div><div></div></div>
          <div id="edit-fixed-list"></div>
          <div class="in-fixed-row" style="margin-top:6px;">
            <input type="text"   id="edit-fixed-name" class="in-mat-input" placeholder="e.g. Rent allocation">
            <input type="number" id="edit-fixed-amt"  class="in-mat-input" placeholder="0.00" step="0.01" min="0">
            <button type="button" id="edit-fixed-btn" class="bntm-btn-small bntm-btn-secondary" style="padding:5px 8px;white-space:nowrap;">+Add</button>
          </div>
          <div id="edit-fixed-total" style="text-align:right;font-size:12px;color:#6b7280;margin-top:6px;">Total fixed alloc.: &#8369;0.00</div>
        </div>

        <!-- live summary -->
        <div class="in-summary-box">
          <div style="font-size:13px;font-weight:600;margin-bottom:8px;display:flex;align-items:center;gap:6px;"><?php echo in_icon('bar-chart', 15); ?> Live Cost Breakdown</div>
          <div class="in-summary-row"><span>Batch yield</span><span id="edit-ls-yield">1 unit</span></div>
          <div class="in-summary-row"><span>Batch material cost</span><span id="edit-ls-mat">&#8369;0.00</span></div>
          <div class="in-summary-row"><span>Material cost / unit</span><span id="edit-ls-mat-pu">&#8369;0.00</span></div>
          <div class="in-summary-row"><span>Labor / unit</span><span id="edit-ls-labor">&#8369;0.00</span></div>
          <div class="in-summary-row"><span>Packaging / unit</span><span id="edit-ls-pkg">&#8369;0.00</span></div>
          <div class="in-summary-row"><span>Variable overhead / unit</span><span id="edit-ls-var">&#8369;0.00</span></div>
          <div class="in-summary-row"><span>Fixed allocation / unit</span><span id="edit-ls-fixed">&#8369;0.00</span></div>
          <div class="in-summary-row" style="border-top:2px solid #e2e8f0;margin-top:4px;font-weight:600;"><span>Total cost / unit</span><span id="edit-ls-total" style="color:#ef4444;">&#8369;0.00</span></div>
          <div class="in-summary-row"><span>Selling price / unit</span><span id="edit-ls-sell" style="color:#10b981;">&#8369;0.00</span></div>
          <div class="in-summary-row"><span>Contribution margin / unit</span><span id="edit-ls-contrib">&#8369;0.00</span></div>
          <div class="in-summary-row" style="font-size:15px;"><span>Gross margin</span><span id="edit-ls-margin" style="font-weight:700;">—</span></div>
          <div class="in-summary-row"><span>Break-even (fixed alloc.)</span><span id="edit-ls-be">—</span></div>
          <div style="border-top:2px solid #e2e8f0;margin-top:8px;padding-top:8px;">
            <div style="font-size:12px;font-weight:600;color:#374151;margin-bottom:6px;text-transform:uppercase;letter-spacing:.04em;">Per Batch Totals</div>
            <div class="in-summary-row"><span>Total cost for 1 batch</span><span id="edit-ls-batch-cost" style="font-weight:600;color:#374151;">&#8369;0.00</span></div>
            <div class="in-summary-row"><span>Potential revenue (1 batch)</span><span id="edit-ls-batch-rev" style="color:#10b981;font-weight:600;">&#8369;0.00</span></div>
            <div class="in-summary-row" style="font-size:14px;"><span>Potential profit (1 batch)</span><span id="edit-ls-batch-profit" style="font-weight:700;">&#8369;0.00</span></div>
          </div>
        </div>

        <input type="hidden" name="costing_materials"   id="edit-costing-materials">
        <input type="hidden" name="costing_fixed_costs" id="edit-costing-fixed">
      </div>

      <div id="edit-product-msg" style="margin-top:8px;"></div>
    </form>
    </div>
    <div class="in-modal-footer">
      <button type="submit" form="in-edit-product-form" class="bntm-btn-primary">Update Product</button>
      <button type="button" class="close-in-modal bntm-btn-secondary" data-modal="edit-product-modal">Cancel</button>
    </div>
  </div>
</div>

<!-- PRODUCT LIST -->
<div class="bntm-form-section">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
    <div>
      <button id="open-add-product-modal" class="bntm-btn-primary" <?php echo $limit_reached ? 'disabled' : ''; ?>>
        + Add New Product / Item<?php echo $limit_text; ?>
      </button>
      <?php if ($limit_reached): ?>
      <div style="background:#fee2e2;border:1px solid #fca5a5;padding:8px 12px;border-radius:6px;margin-top:8px;font-size:13px;">
        Product limit reached (max <?php echo $product_limit; ?>).
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- TYPE FILTER -->
  <div class="in-filter-bar">
    <?php echo in_icon('filter', 14); ?>
    <label style="font-size:13px;font-weight:600;color:#374151;">Filter by Type:</label>
    <select id="in-type-filter">
      <option value="Product">Product</option>
      <option value="">All Types</option>
      <?php foreach ($all_types as $t): if ($t === 'Product') continue; ?>
      <option value="<?php echo esc_attr($t); ?>"><?php echo esc_html($t); ?></option>
      <?php endforeach; ?>
    </select>
    <span id="in-type-count" style="font-size:13px;color:#6b7280;"></span>
  </div>

  <?php if (empty($products)): ?>
  <p style="color:#6b7280;font-size:14px;">No products found. Click "Add New Product" to get started.</p>
  <?php else: ?>
  <div class="bntm-table-wrapper">
    <table class="bntm-table" id="in-products-table">
      <thead><tr>
        <th>Image</th><th>Type</th><th>Name</th><th>SKU</th>
        <th>Cost/unit</th><th>Selling Price</th><th>Margin</th>
        <th>Stock</th><th>Reorder</th><th>Status</th><th>Actions</th>
      </tr></thead>
      <tbody>
      <?php foreach ($products as $p):
          $mats      = json_decode($p->costing_materials ?: '[]', true) ?: [];
          $fixeds    = json_decode($p->costing_fixed_costs ?: '[]', true) ?: [];
          $is_cost_only = in_array($p->inventory_type, ['Raw Material', 'Supplies', 'Other'], true);
          $batch_mat = in_calculate_batch_material_cost($mats, $business_id);
          $yield2    = max(1, intval($p->batch_yield));
          $mat_pu    = $batch_mat / $yield2;
          $labor_pu  = floatval($p->labor_cost_per_batch) / $yield2;
          $pkg_pu    = floatval($p->packaging_cost_per_unit);
          $var_pu    = floatval($p->variable_overhead_per_unit);
          $fixed_pu  = array_reduce($fixeds, fn($c, $f) => $c + floatval($f['amount']), 0);
          $cost_pu   = $is_cost_only ? floatval($p->cost_per_unit) : ($mat_pu + $labor_pu + $pkg_pu + $var_pu + $fixed_pu);
          $sell      = floatval($p->selling_price);
          $margin    = $sell > 0 ? ($sell - $cost_pu) / $sell * 100 : 0;
          $mcol      = $margin >= floatval($p->target_margin_pct) ? '#10b981' : ($margin >= 0 ? '#f59e0b' : '#ef4444');
      ?>
      <tr data-type="<?php echo esc_attr($p->inventory_type); ?>">
        <td>
          <?php if ($p->image): ?>
            <img src="<?php echo esc_url($p->image); ?>" style="width:46px;height:46px;object-fit:cover;border-radius:6px;">
          <?php else: ?>
            <div style="width:46px;height:46px;background:#e5e7eb;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#9ca3af;"><?php echo in_icon('package', 20); ?></div>
          <?php endif; ?>
        </td>
        <td><?php echo esc_html($p->inventory_type); ?></td>
        <td style="font-weight:500;"><?php echo esc_html($p->name); ?></td>
        <td><?php echo esc_html($p->sku); ?></td>
        <td>&#8369;<?php echo number_format($cost_pu, 4); ?></td>
        <td><?php echo $sell > 0 ? '&#8369;'.number_format($sell, 2) : '—'; ?></td>
        <td style="color:<?php echo $mcol; ?>;font-weight:600;"><?php echo $sell > 0 ? round($margin, 1).'%' : '—'; ?></td>
        <td><?php echo $p->stock_quantity; ?></td>
        <td><?php echo $p->reorder_level; ?></td>
        <td>
          <?php if ($p->stock_quantity == 0): ?>
            <span style="background:#fee2e2;color:#991b1b;font-size:11px;font-weight:600;padding:2px 8px;border-radius:99px;">Out of Stock</span>
          <?php elseif ($p->stock_quantity <= $p->reorder_level): ?>
            <span style="background:#fef2f2;color:#dc2626;font-size:11px;font-weight:600;padding:2px 8px;border-radius:99px;">Low Stock</span>
          <?php else: ?>
            <span style="background:#dcfce7;color:#166534;font-size:11px;font-weight:600;padding:2px 8px;border-radius:99px;">In Stock</span>
          <?php endif; ?>
        </td>
        <td style="white-space:nowrap;">
          <button class="bntm-btn-small in-edit-product"
            data-id="<?php echo $p->id; ?>"
            data-name="<?php echo esc_attr($p->name); ?>"
            data-sku="<?php echo esc_attr($p->sku); ?>"
            data-type="<?php echo esc_attr($p->inventory_type); ?>"
            data-sell="<?php echo $sell; ?>"
            data-stock="<?php echo $p->stock_quantity; ?>"
            data-reorder="<?php echo $p->reorder_level; ?>"
            data-description="<?php echo esc_attr($p->description); ?>"
            data-image="<?php echo esc_attr($p->image); ?>"
            data-yield="<?php echo $yield2; ?>"
            data-labor="<?php echo floatval($p->labor_cost_per_batch); ?>"
            data-pkg="<?php echo $pkg_pu; ?>"
            data-var="<?php echo $var_pu; ?>"
            data-cost="<?php echo floatval($p->cost_per_unit); ?>"
            data-margin="<?php echo floatval($p->target_margin_pct); ?>"
            data-materials="<?php echo esc_attr($p->costing_materials ?: '[]'); ?>"
            data-fixedcosts="<?php echo esc_attr($p->costing_fixed_costs ?: '[]'); ?>">Edit</button>
          <button class="bntm-btn-small bntm-btn-danger in-delete-product" data-id="<?php echo $p->id; ?>">Delete</button>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<script>
(function(){
    var upNonce = '<?php echo esc_js($upnonce); ?>';
    var inNonce = '<?php echo esc_js($nonce); ?>';
    var rawMaterialCatalog = <?php echo wp_json_encode($raw_material_catalog); ?>;

    /* ---- TYPE FILTER ---- */
    var typeFilter = document.getElementById('in-type-filter');
    var typeCount  = document.getElementById('in-type-count');
    function applyTypeFilter(){
        var val = typeFilter ? typeFilter.value : '';
        var rows = document.querySelectorAll('#in-products-table tbody tr');
        var visible = 0;
        rows.forEach(function(row){
            if(!val || row.dataset.type === val){ row.style.display=''; visible++; }
            else row.style.display='none';
        });
        if(typeCount) typeCount.textContent = visible + ' item'+(visible!==1?'s':'')+' shown';
    }
    if(typeFilter){
        typeFilter.addEventListener('change', applyTypeFilter);
        applyTypeFilter(); // default: show only Product
    }

    /* ---- MODAL CLOSE ---- */
    function closeModal(modalId){ var m=document.getElementById(modalId); if(m) m.style.display='none'; }
    document.querySelectorAll('.close-in-modal').forEach(function(btn){
        btn.addEventListener('click',function(){ closeModal(this.dataset.modal||this.closest('.in-modal').id); });
    });
    document.querySelectorAll('.in-modal').forEach(function(m){
        m.addEventListener('click',function(e){ if(e.target===this) closeModal(this.id); });
    });

    function fmt(n){ return '\u20B1'+parseFloat(n||0).toFixed(2); }
    function normalizeMaterialName(name){
        return String(name||'').toLowerCase().replace(/\s*\(material\)\s*$/i,'').replace(/\s+/g,' ').trim();
    }
    function findExactMaterial(name){
        var normalized = normalizeMaterialName(name);
        return rawMaterialCatalog.find(function(item){ return item.normalized === normalized; }) || null;
    }
    function findSimilarMaterials(name){
        var normalized = normalizeMaterialName(name);
        if(!normalized){ return []; }
        return rawMaterialCatalog.filter(function(item){
            return item.normalized.indexOf(normalized) !== -1 || normalized.indexOf(item.normalized) !== -1;
        }).slice(0,5);
    }
    function materialExistsInCurrentList(pfx, name){
        var normalized = normalizeMaterialName(name);
        return getMats(pfx).some(function(item){ return normalizeMaterialName(item.name) === normalized; });
    }
    function renderMaterialAssist(pfx){
        var input = document.getElementById(pfx+'mat-name');
        var suggest = document.getElementById(pfx+'mat-suggest');
        var wrap = document.getElementById(pfx+'mat-create-wrap');
        var toggle = document.getElementById(pfx+'mat-create-toggle');
        if(!input || !suggest || !wrap || !toggle){ return; }
        var exact = findExactMaterial(input.value);
        var similar = findSimilarMaterials(input.value);
        if(!input.value.trim()){ suggest.innerHTML=''; wrap.style.display='none'; toggle.checked=false; return; }
        if(exact){
            suggest.innerHTML='<span class="in-mat-exists">Existing raw material found.</span><div class="in-mat-suggest-list"><button type="button" class="in-mat-suggest-btn pm-suggest-pick" data-pfx="'+pfx+'" data-name="'+escAttr(exact.name)+'">'+escAttr(exact.name)+' · stock '+exact.stock_quantity+'</button></div><div class="in-linked-note">Picking this links the recipe to tracked inventory and uses the current raw-material unit cost as the starting line cost.</div>';
            wrap.style.display='none'; toggle.checked=false;
        } else {
            if(similar.length) suggest.innerHTML='<span class="in-mat-warning">Similar tracked materials:</span><div class="in-mat-suggest-list">'+similar.map(function(item){ return '<button type="button" class="in-mat-suggest-btn pm-suggest-pick" data-pfx="'+pfx+'" data-name="'+escAttr(item.name)+'">'+escAttr(item.name)+' · stock '+item.stock_quantity+'</button>'; }).join('')+'</div>';
            else suggest.innerHTML='No existing raw material match found yet.';
            wrap.style.display='flex';
        }
        suggest.querySelectorAll('.pm-suggest-pick').forEach(function(btn){
            btn.addEventListener('click',function(){
                input.value=this.dataset.name;
                document.getElementById(pfx+'mat-cost').value='0';
                toggle.checked=false;
                renderMaterialAssist(pfx);
            });
        });
    }

    /* IMAGE UPLOAD */
    function setupImgUpload(pfx){
        var area    = document.getElementById(pfx+'product-upload-area');
        var btn     = document.getElementById(pfx+'product-upload-btn');
        var input   = document.getElementById(pfx+'product-image-upload');
        var preview = document.getElementById(pfx+'product-image-preview');
        var removeB = document.getElementById('remove-'+pfx+'product-image');
        var hidden  = document.getElementById(pfx+'product_image');
        if(!btn) return;
        btn.addEventListener('click',function(){ input.click(); });
        input.addEventListener('change',function(){ if(this.files[0]) doUpload(this.files[0],pfx); });
        area.addEventListener('dragover',function(e){ e.preventDefault(); area.classList.add('dragover'); });
        area.addEventListener('dragleave',function(){ area.classList.remove('dragover'); });
        area.addEventListener('drop',function(e){ e.preventDefault(); area.classList.remove('dragover'); if(e.dataTransfer.files[0]) doUpload(e.dataTransfer.files[0],pfx); });
        removeB.addEventListener('click',function(){ preview.style.display='none'; area.style.display='block'; hidden.value=''; });
    }
    function doUpload(file,pfx){
        if(file.size>2*1024*1024){ alert('Max 2 MB'); return; }
        var fd=new FormData();
        fd.append('action','in_upload_product_image'); fd.append('image',file); fd.append('_ajax_nonce',upNonce);
        var btn=document.getElementById(pfx+'product-upload-btn');
        btn.disabled=true; btn.textContent='Uploading...';
        fetch(ajaxurl,{method:'POST',body:fd}).then(function(r){ return r.json(); }).then(function(j){
            btn.disabled=false; btn.textContent='Choose Image';
            if(j.success){
                var prev=document.getElementById(pfx+'product-image-preview');
                prev.querySelector('img').src=j.data.url; prev.style.display='inline-block';
                document.getElementById(pfx+'product-upload-area').style.display='none';
                document.getElementById(pfx+'product_image').value=j.data.url;
            } else alert('Upload failed: '+j.data);
        });
    }
    setupImgUpload(''); setupImgUpload('edit-');

    /* COSTING ENGINE */
    var addMats=[], addFixeds=[], editMats=[], editFixeds=[];
    function getMats(pfx){ return pfx==='add-' ? addMats : editMats; }
    function getFixeds(pfx){ return pfx==='add-' ? addFixeds : editFixeds; }

    function renderMats(pfx){
        var mats=getMats(pfx);
        var list=document.getElementById(pfx+'mat-list');
        var totEl=document.getElementById(pfx+'mat-total');
        var batchTot=0;
        if(!mats.length){
            list.innerHTML='<p style="font-size:12px;color:#9ca3af;padding:4px 0;">No materials added yet.</p>';
            totEl.textContent='Batch material cost: \u20B10.00';
            recalc(pfx); return;
        }
        list.innerHTML=mats.map(function(m,i){
            var linkedInventory = !!findExactMaterial(m.name);
            var fromInventory=!!m.fromInventory || linkedInventory;
            var lineCost=parseFloat(m.cost)||0; batchTot+=lineCost;
            var toggleHtml=fromInventory ? '' : '<label class="in-toggle" style="grid-column:1/-1;margin-top:3px;font-size:11px;">'
                +'<input type="checkbox" class="pm-inv-toggle" data-pfx="'+pfx+'" data-idx="'+i+'"'+(m.addToInventory?' checked':'')+'>'
                +'<span>Also add this material as an Inventory Item</span></label>';
            var linkedNote=fromInventory ? '<div class="in-linked-note">Linked to existing raw material stock. </div>' : '';
            return '<div style="border:1px solid #e2e8f0;border-radius:7px;padding:7px 8px;margin-bottom:6px;">'
                +'<div class="in-mat-row" data-idx="'+i+'">'
                +'<input type="text" class="in-mat-input pm-name" data-pfx="'+pfx+'" data-idx="'+i+'" value="'+escAttr(m.name)+'"'+(fromInventory?' disabled':'')+'>'
                +'<input type="number" class="in-mat-input pm-qty" data-pfx="'+pfx+'" data-idx="'+i+'" value="'+m.qty+'" step="0.001" min="0.001">'
                +'<input type="text" class="in-mat-input pm-uom" data-pfx="'+pfx+'" data-idx="'+i+'" value="'+escAttr(m.uom||'')+'">'
                +'<input type="number" class="in-mat-input pm-cost" data-pfx="'+pfx+'" data-idx="'+i+'" value="'+(m.cost||0)+'" step="0.0001" min="0" placeholder="Line cost / batch">'
                +'<button type="button" class="bntm-btn-small bntm-btn-danger pm-del" data-pfx="'+pfx+'" data-idx="'+i+'" style="padding:4px 7px;">\u00D7</button>'
                +'</div>'+toggleHtml+linkedNote+'</div>';
        }).join('');
        totEl.textContent='Batch material cost: \u20B1'+batchTot.toFixed(2);
        list.querySelectorAll('.pm-name').forEach(function(el){ el.addEventListener('input',function(){ getMats(this.dataset.pfx)[this.dataset.idx].name=this.value; }); });
        list.querySelectorAll('.pm-qty').forEach(function(el){ el.addEventListener('input',function(){ getMats(this.dataset.pfx)[this.dataset.idx].qty=parseFloat(this.value)||0; renderMats(this.dataset.pfx); }); });
        list.querySelectorAll('.pm-cost').forEach(function(el){ el.addEventListener('input',function(){ getMats(this.dataset.pfx)[this.dataset.idx].cost=parseFloat(this.value)||0; renderMats(this.dataset.pfx); }); });
        list.querySelectorAll('.pm-uom').forEach(function(el){ el.addEventListener('input',function(){ getMats(this.dataset.pfx)[this.dataset.idx].uom=this.value; }); });
        list.querySelectorAll('.pm-del').forEach(function(el){ el.addEventListener('click',function(){ getMats(this.dataset.pfx).splice(parseInt(this.dataset.idx),1); renderMats(this.dataset.pfx); }); });
        list.querySelectorAll('.pm-inv-toggle').forEach(function(el){ el.addEventListener('change',function(){ getMats(this.dataset.pfx)[parseInt(this.dataset.idx)].addToInventory=this.checked; }); });
        recalc(pfx);
    }

    function renderFixeds(pfx){
        var fixeds=getFixeds(pfx);
        var list=document.getElementById(pfx+'fixed-list');
        var totEl=document.getElementById(pfx+'fixed-total');
        var tot=0;
        if(!fixeds.length){
            list.innerHTML='<p style="font-size:12px;color:#9ca3af;padding:4px 0;">No fixed costs added yet.</p>';
            totEl.textContent='Total fixed alloc.: \u20B10.00';
            recalc(pfx); return;
        }
        list.innerHTML=fixeds.map(function(f,i){
            tot+=parseFloat(f.amount);
            return '<div class="in-fixed-row" data-idx="'+i+'">'
                +'<input type="text" class="in-mat-input pf-name" data-pfx="'+pfx+'" data-idx="'+i+'" value="'+escAttr(f.name||'')+'">'
                +'<input type="number" class="in-mat-input pf-amt" data-pfx="'+pfx+'" data-idx="'+i+'" value="'+f.amount+'" step="0.01" min="0">'
                +'<button type="button" class="bntm-btn-small bntm-btn-danger pf-del" data-pfx="'+pfx+'" data-idx="'+i+'" style="padding:4px 7px;">\u00D7</button>'
                +'</div>';
        }).join('');
        totEl.textContent='Total fixed alloc.: \u20B1'+tot.toFixed(2);
        list.querySelectorAll('.pf-name').forEach(function(el){ el.addEventListener('input',function(){ getFixeds(this.dataset.pfx)[this.dataset.idx].name=this.value; }); });
        list.querySelectorAll('.pf-amt').forEach(function(el){ el.addEventListener('input',function(){ getFixeds(this.dataset.pfx)[this.dataset.idx].amount=parseFloat(this.value)||0; renderFixeds(this.dataset.pfx); }); });
        list.querySelectorAll('.pf-del').forEach(function(el){ el.addEventListener('click',function(){ getFixeds(this.dataset.pfx).splice(parseInt(this.dataset.idx),1); renderFixeds(this.dataset.pfx); }); });
        recalc(pfx);
    }

    function recalc(pfx){
        var mats=getMats(pfx); var fixeds=getFixeds(pfx);
        var batchMat=mats.reduce(function(s,m){ return s+(parseFloat(m.cost)||0); },0);
        var yld=parseInt(document.getElementById(pfx+'yield').value)||1;
        var matPu=batchMat/yld;
        var laborPu=(parseFloat(document.getElementById(pfx+'labor').value)||0)/yld;
        var pkgPu=parseFloat(document.getElementById(pfx+'pkg').value)||0;
        var varPu=parseFloat(document.getElementById(pfx+'var').value)||0;
        var fixedPu=fixeds.reduce(function(s,f){ return s+parseFloat(f.amount||0); },0);
        var total=matPu+laborPu+pkgPu+varPu+fixedPu;
        var sell=parseFloat(document.getElementById(pfx+'selling-price').value)||0;
        var contrib=sell-matPu-laborPu-pkgPu-varPu;
        var margin=sell>0?(sell-total)/sell*100:0;
        var tgtM=parseFloat(document.getElementById(pfx+'target-margin').value)||30;
        var be=(fixedPu>0&&contrib>0)?Math.ceil(fixedPu/contrib):'—';
        var mCol=margin>=tgtM?'#10b981':(margin>=0?'#f59e0b':'#ef4444');
        var batchCost=total*yld; var batchRev=sell*yld; var batchProfit=batchRev-batchCost;
        var bpCol=batchProfit>=0?'#10b981':'#ef4444';
        function s(id,v){ var el=document.getElementById(id); if(el) el.textContent=v; }
        function sc(id,c){ var el=document.getElementById(id); if(el) el.style.color=c; }
        s(pfx+'ls-yield',yld+' unit'+(yld!==1?'s':''));
        s(pfx+'ls-mat',fmt(batchMat)); s(pfx+'ls-mat-pu',fmt(matPu));
        s(pfx+'ls-labor',fmt(laborPu)); s(pfx+'ls-pkg',fmt(pkgPu));
        s(pfx+'ls-var',fmt(varPu)); s(pfx+'ls-fixed',fmt(fixedPu));
        s(pfx+'ls-total',fmt(total)); sc(pfx+'ls-total',mCol);
        s(pfx+'ls-sell',fmt(sell)); s(pfx+'ls-contrib',fmt(contrib));
        s(pfx+'ls-be',typeof be==='number'?be.toLocaleString():be);
        var mel=document.getElementById(pfx+'ls-margin');
        if(mel){ mel.textContent=margin.toFixed(1)+'%'; mel.style.color=mCol; }
        s(pfx+'ls-batch-cost',fmt(batchCost)); s(pfx+'ls-batch-rev',fmt(batchRev));
        s(pfx+'ls-batch-profit',fmt(batchProfit)); sc(pfx+'ls-batch-profit',bpCol);
        var hm=document.getElementById(pfx+'costing-materials');
        var hf=document.getElementById(pfx+'costing-fixed');
        if(hm) hm.value=JSON.stringify(mats);
        if(hf) hf.value=JSON.stringify(fixeds);
    }

    function escAttr(s){ return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;'); }
    function isCostOnlyType(type){ return ['Raw Material','Supplies','Other'].indexOf(type) !== -1; }
    function toggleProductMode(pfx, type){
        var costOnly = isCostOnlyType(type);
        var costingSection = document.getElementById(pfx+'costing-section');
        var sellWrap = document.getElementById(pfx+'selling-price-wrap');
        var directWrap = document.getElementById(pfx+'direct-cost-wrap');
        var marginWrap = document.getElementById(pfx+'target-margin-wrap');
        var sellInput = document.getElementById(pfx+'selling-price');
        var directInput = document.getElementById(pfx+'direct-cost');
        if (costingSection) costingSection.style.display = costOnly ? 'none' : 'block';
        if (sellWrap) sellWrap.style.display = costOnly ? 'none' : 'flex';
        if (directWrap) directWrap.style.display = costOnly ? 'flex' : 'none';
        if (marginWrap) marginWrap.style.display = costOnly ? 'none' : 'flex';
        if (sellInput) sellInput.required = !costOnly;
        if (directInput) directInput.required = costOnly;
        if (costOnly) {
            if (sellInput) sellInput.value = '0';
        } else if (directInput) {
            directInput.value = '';
        }
    }

    function wireAddMat(pfx){
        var nameInput=document.getElementById(pfx+'mat-name');
        if(nameInput) nameInput.addEventListener('input',function(){ renderMaterialAssist(pfx); });
        document.getElementById(pfx+'mat-btn').addEventListener('click',function(){
            var n=document.getElementById(pfx+'mat-name').value.trim();
            if(!n){ alert('Enter material name.'); return; }
            if(materialExistsInCurrentList(pfx,n)){ alert('That material is already added to this product.'); return; }
            var qty=parseFloat(document.getElementById(pfx+'mat-qty').value)||1;
            var cost=parseFloat(document.getElementById(pfx+'mat-cost').value)||0;
            var exact=findExactMaterial(n);
            var addToggle=document.getElementById(pfx+'mat-create-toggle');
            var canonicalName=exact?exact.name:n;
            var fromInventory=!!exact;
            var effectiveCost = fromInventory ? ((parseFloat(exact.cost_per_unit)||0) * qty) : cost;
            getMats(pfx).push({ name:canonicalName, qty:qty, uom:document.getElementById(pfx+'mat-uom').value.trim()||'pcs', cost:effectiveCost, addToInventory:fromInventory?false:!!(addToggle&&addToggle.checked), fromInventory:fromInventory, inventory_id:fromInventory?exact.id:0 });
            document.getElementById(pfx+'mat-name').value='';
            document.getElementById(pfx+'mat-qty').value='1';
            document.getElementById(pfx+'mat-uom').value='';
            document.getElementById(pfx+'mat-cost').value='';
            if(addToggle) addToggle.checked=false;
            renderMaterialAssist(pfx); renderMats(pfx);
        });
    }
    function wireAddFixed(pfx){
        document.getElementById(pfx+'fixed-btn').addEventListener('click',function(){
            var n=document.getElementById(pfx+'fixed-name').value.trim();
            if(!n){ alert('Enter fixed cost description.'); return; }
            getFixeds(pfx).push({ name:n, amount:parseFloat(document.getElementById(pfx+'fixed-amt').value)||0 });
            document.getElementById(pfx+'fixed-name').value='';
            document.getElementById(pfx+'fixed-amt').value='';
            renderFixeds(pfx);
        });
    }
    function wireCostInputs(pfx){
        ['yield','labor','pkg','var','selling-price','target-margin'].forEach(function(id){
            var el=document.getElementById(pfx+id);
            if(el) el.addEventListener('input',function(){ recalc(pfx); });
        });
    }

    wireAddMat('add-'); wireAddFixed('add-'); wireCostInputs('add-');
    wireAddMat('edit-'); wireAddFixed('edit-'); wireCostInputs('edit-');
    renderMats('add-'); renderFixeds('add-');
    renderMats('edit-'); renderFixeds('edit-');
    renderMaterialAssist('add-'); renderMaterialAssist('edit-');
    document.getElementById('add-inventory-type').addEventListener('change', function(){ toggleProductMode('add-', this.value); });
    document.getElementById('edit-inventory-type').addEventListener('change', function(){ toggleProductMode('edit-', this.value); });
    toggleProductMode('add-', document.getElementById('add-inventory-type').value);
    toggleProductMode('edit-', document.getElementById('edit-inventory-type').value);

    /* OPEN ADD */
    var addBtn=document.getElementById('open-add-product-modal');
    if(addBtn) addBtn.addEventListener('click',function(){
        document.getElementById('in-add-product-form').reset();
        document.getElementById('product-image-preview').style.display='none';
        document.getElementById('product-upload-area').style.display='block';
        document.getElementById('product_image').value='';
        addMats.length=0; addFixeds.length=0;
        renderMats('add-'); renderFixeds('add-');
        renderMaterialAssist('add-');
        toggleProductMode('add-', document.getElementById('add-inventory-type').value);
        document.getElementById('add-product-modal').style.display='flex';
    });

    /* EDIT */
    document.querySelectorAll('.in-edit-product').forEach(function(btn){
        btn.addEventListener('click',function(){
            var d=this.dataset;
            document.getElementById('edit-product-id').value=d.id;
            document.getElementById('edit-name').value=d.name;
            document.getElementById('edit-sku').value=d.sku;
            document.getElementById('edit-inventory-type').value=d.type;
            document.getElementById('edit-selling-price').value=d.sell;
            document.getElementById('edit-current-stock').value=d.stock;
            document.getElementById('edit-reorder-level').value=d.reorder;
            document.getElementById('edit-description').value=d.description;
            document.getElementById('edit-yield').value=d.yield;
            document.getElementById('edit-labor').value=d.labor;
            document.getElementById('edit-pkg').value=d.pkg;
            document.getElementById('edit-var').value=d['var']||0;
            document.getElementById('edit-target-margin').value=d.margin;
            document.getElementById('edit-direct-cost').value=d.cost || '';
            var prev=document.getElementById('edit-product-image-preview');
            var ua=document.getElementById('edit-product-upload-area');
            var hi=document.getElementById('edit-product_image');
            if(d.image){ prev.querySelector('img').src=d.image; prev.style.display='inline-block'; ua.style.display='none'; hi.value=d.image; }
            else{ prev.style.display='none'; ua.style.display='block'; hi.value=''; }
            try{ editMats.length=0; JSON.parse(d.materials||'[]').forEach(function(x){ editMats.push(x); }); }catch(e){ editMats.length=0; }
            try{ editFixeds.length=0; JSON.parse(d.fixedcosts||'[]').forEach(function(x){ editFixeds.push(x); }); }catch(e){ editFixeds.length=0; }
            renderMats('edit-'); renderFixeds('edit-');
            renderMaterialAssist('edit-');
            toggleProductMode('edit-', d.type);
            document.getElementById('edit-product-modal').style.display='flex';
        });
    });

    /* SUBMIT ADD */
    document.getElementById('in-add-product-form').addEventListener('submit',function(e){
        e.preventDefault(); recalc('add-');
        var fd=new FormData(this);
        fd.append('action','in_add_product'); fd.append('nonce',inNonce);
        var btn=document.querySelector('button[form="in-add-product-form"]');
        btn.disabled=true; btn.textContent='Adding...';
        fetch(ajaxurl,{method:'POST',body:fd}).then(function(r){ return r.json(); }).then(function(j){
            document.getElementById('add-product-msg').innerHTML='<div class="bntm-notice bntm-notice-'+(j.success?'success':'error')+'">'+j.data.message+'</div>';
            btn.disabled=false; btn.textContent='Add Product';
            if(j.success) setTimeout(function(){ location.reload(); },1200);
        });
    });

    /* SUBMIT EDIT */
    document.getElementById('in-edit-product-form').addEventListener('submit',function(e){
        e.preventDefault(); recalc('edit-');
        var fd=new FormData(this);
        fd.append('action','in_update_product'); fd.append('nonce',inNonce);
        var btn=document.querySelector('button[form="in-edit-product-form"]');
        btn.disabled=true; btn.textContent='Updating...';
        fetch(ajaxurl,{method:'POST',body:fd}).then(function(r){ return r.json(); }).then(function(j){
            document.getElementById('edit-product-msg').innerHTML='<div class="bntm-notice bntm-notice-'+(j.success?'success':'error')+'">'+j.data.message+'</div>';
            btn.disabled=false; btn.textContent='Update Product';
            if(j.success) setTimeout(function(){ location.reload(); },1200);
        });
    });

    /* DELETE */
    document.querySelectorAll('.in-delete-product').forEach(function(btn){
        btn.addEventListener('click',function(){
            if(!confirm('Delete this product?')) return;
            var fd=new FormData();
            fd.append('action','in_delete_product'); fd.append('product_id',this.dataset.id); fd.append('nonce',inNonce);
            fetch(ajaxurl,{method:'POST',body:fd}).then(function(r){ return r.json(); }).then(function(j){
                if(j.success) location.reload(); else alert(j.data.message);
            });
        });
    });
})();
</script>
<?php return ob_get_clean();
}

/* ============================================================
   BATCHES TAB — stock-out now always reflected as sales in finance
   ============================================================ */
function in_batches_tab($business_id) {
    global $wpdb;
    $pt = $wpdb->prefix.'in_products';
    $bt = $wpdb->prefix.'in_batches';

    $products = $wpdb->get_results($wpdb->prepare("SELECT * FROM $pt WHERE business_id = %d ORDER BY name ASC", $business_id));
    $batches  = $wpdb->get_results($wpdb->prepare("
        SELECT b.*, p.name as product_name, p.selling_price as product_selling_price
        FROM $bt b LEFT JOIN $pt p ON b.product_id=p.id
        WHERE b.business_id = %d
        ORDER BY b.created_at DESC", $business_id));
    $nonce = wp_create_nonce('in_nonce');

    $prod_data = [];
    foreach ($products as $p) {
        $mats   = json_decode($p->costing_materials ?: '[]', true) ?: [];
        $fixeds = json_decode($p->costing_fixed_costs ?: '[]', true) ?: [];
        $is_cost_only = in_array($p->inventory_type, ['Raw Material', 'Supplies', 'Other'], true);
        $batch_mat = 0;
        $yld    = max(1, intval($p->batch_yield));
        $mat_pu = 0;
        $lab_pu = floatval($p->labor_cost_per_batch) / $yld;
        $pkg_pu = floatval($p->packaging_cost_per_unit);
        $var_pu = floatval($p->variable_overhead_per_unit);
        $fix_pu = array_reduce($fixeds, fn($c,$f)=>$c+floatval($f['amount']), 0);
        $cost_pu = $is_cost_only ? floatval($p->cost_per_unit) : ($mat_pu + $lab_pu + $pkg_pu + $var_pu + $fix_pu);
        $prod_data[$p->id] = [
            'cost_per_unit'   => round($cost_pu, 4),
            'selling_price'   => floatval($p->selling_price),
            'batch_yield'     => $yld,
            'batch_mat_cost'  => round($batch_mat, 4),
            'materials'       => $mats,
            'name'            => $p->name,
            'inventory_type'  => $p->inventory_type,
        ];
    }

    $raw_stock = [];
    foreach ($products as $p) {
        if ($p->inventory_type === 'Raw Material') {
            $clean = preg_replace('/\s*\(material\)\s*$/i', '', strtolower(trim($p->name)));
            $raw_stock[$clean] = intval($p->stock_quantity);
            $raw_stock[strtolower(trim($p->name))] = intval($p->stock_quantity);
        }
    }

    ob_start(); ?>
<script>var ajaxurl='<?php echo admin_url('admin-ajax.php'); ?>';</script>

<style>
.in-toggle{display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;color:#374151;}
.in-toggle input{appearance:none;width:36px;height:20px;background:#d1d5db;border-radius:99px;cursor:pointer;transition:.2s;position:relative;}
.in-toggle input:checked{background:#3b82f6;}
.in-toggle input::after{content:'';position:absolute;top:2px;left:2px;width:16px;height:16px;background:#fff;border-radius:50%;transition:.2s;}
.in-toggle input:checked::after{left:18px;}
.in-mat-req-list{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;}
.in-mat-req-chip{font-size:12px;padding:3px 10px;border-radius:99px;border:1px solid;display:inline-flex;align-items:center;gap:5px;}
.chip-ok{background:#dcfce7;color:#166534;border-color:#bbf7d0;}
.chip-bad{background:#fee2e2;color:#991b1b;border-color:#fecaca;}
/* Stock-out sales preview box */
.in-sales-preview{background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:14px;margin-top:10px;display:none;}
.in-sales-preview h4{margin:0 0 8px;font-size:13px;font-weight:600;color:#166534;display:flex;align-items:center;gap:6px;}
.in-sales-row{display:flex;justify-content:space-between;font-size:13px;padding:4px 0;border-bottom:1px solid #dcfce7;}
.in-sales-row:last-child{border-bottom:none;}
.in-sales-total{font-size:15px;font-weight:700;color:#059669;margin-top:4px;}
</style>

<div class="bntm-form-section">
    <h3>Stock Movement</h3>
    <form id="in-add-batch-form" class="bntm-form">
        <div class="bntm-form-row">
            <div class="bntm-form-group">
                <label>Product *</label>
                <select name="product_id" id="batch-product-select" required>
                    <option value="">-- Select Product --</option>
                    <?php foreach ($products as $p): ?>
                    <option value="<?php echo $p->id; ?>" data-stock="<?php echo $p->stock_quantity; ?>">
                        <?php echo esc_html($p->name); ?> (Stock: <?php echo $p->stock_quantity; ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="bntm-form-group">
                <label>Transaction Type *</label>
                <select name="type" id="batch-type-select" required>
                    <option value="stock_in">Stock In (Receive / Produce)</option>
                    <option value="stock_out">Stock Out (Sell / Use)</option>
                </select>
            </div>
        </div>

        <!-- Materials required panel (stock in only) -->
        <div id="batch-materials-panel" style="display:none;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px;margin-bottom:12px;">
            <div style="font-size:13px;font-weight:600;margin-bottom:6px;color:#374151;">Materials Required (per batch)</div>
            <div id="batch-mat-req-list" class="in-mat-req-list"></div>
            <div id="batch-mat-warning" style="display:none;background:#fee2e2;border:1px solid #fecaca;border-radius:6px;padding:10px 12px;margin-top:10px;font-size:13px;color:#991b1b;font-weight:500;"></div>
        </div>

        <!-- Cost info panel -->
        <div id="batch-cost-info" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px;margin-bottom:12px;display:none;font-size:13px;">
            <strong>Costing from product profile:</strong>
            <div style="display:flex;gap:20px;flex-wrap:wrap;margin-top:6px;">
                <span>Cost/unit: <strong id="bci-cpu">—</strong></span>
                <span>Selling price/unit: <strong id="bci-sell">—</strong></span>
                <span>Batch yield: <strong id="bci-yield">—</strong> units</span>
                <span>Batch material cost: <strong id="bci-bmc">—</strong></span>
            </div>
        </div>

        <!-- By batch toggle (stock in only) -->
        <div id="by-batch-wrap" style="margin-bottom:12px;display:none;">
            <label class="in-toggle">
                <input type="checkbox" id="batch-by-batch" name="by_batch" value="1">
                <span>Enter quantity <strong>by number of batches</strong> (not individual units)</span>
            </label>
            <small style="display:block;margin-top:4px;color:#6b7280;margin-left:44px;" id="by-batch-hint"></small>
        </div>

        <div class="bntm-form-row">
            <div class="bntm-form-group">
                <label id="qty-label">Quantity *</label>
                <input type="number" name="quantity" id="batch-quantity" min="1" required>
                <small id="qty-hint"></small>
            </div>
            <div class="bntm-form-group">
                <label>Reference Number</label>
                <input type="text" name="reference_number" placeholder="e.g. PO-001, INV-001">
            </div>
        </div>

        <div class="bntm-form-row">
            <div class="bntm-form-group" id="batch-cpu-group">
                <label>Cost Per Unit</label>
                <input type="number" name="cost_per_unit" id="batch-cpu" step="0.0001" min="0">
                <small id="cpu-hint">Auto-filled from product costing profile</small>
            </div>
            <div class="bntm-form-group" id="batch-total-cost-group">
                <label>Total Cost</label>
                <input type="number" id="batch-total-cost-input" step="0.01" min="0">
                <small id="batch-total-cost-hint">Edit total cost or cost per unit. The other value updates automatically.</small>
            </div>
            <div class="bntm-form-group">
                <label>Date</label>
                <input type="date" name="transaction_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
        </div>

        <!-- STOCK OUT -->
        <div id="stock-out-info-wrap" style="display:none;margin-bottom:12px;">
            <label class="in-toggle">
                <input type="checkbox" id="include-in-finance" name="include_in_finance" value="1" checked>
                <span>Treat this stock-out as a <strong>sale</strong> and use selling price</span>
            </label>
            <small id="stock-out-mode-help" style="display:block;margin-left:44px;color:#3b82f6;font-size:12px;margin-top:3px;">If checked, Finance records income using selling price. If unchecked, the movement is recorded using cost only.</small>
           
            <!-- Sales preview box -->
            <div class="in-sales-preview" id="stock-out-sales-preview">
                <h4><?php echo in_icon('dollar', 14); ?> Sales Revenue Preview</h4>
                <div class="in-sales-row"><span>Units to sell</span><span id="so-units">—</span></div>
                <div class="in-sales-row"><span>Selling price / unit</span><span id="so-sell-price">—</span></div>
                <div class="in-sales-row"><span>Gross profit / unit</span><span id="so-profit-pu">—</span></div>
                <div style="border-top:2px solid #86efac;margin-top:6px;padding-top:6px;display:flex;justify-content:space-between;">
                    <span style="font-size:13px;font-weight:600;">Total Sales Revenue</span>
                    <span class="in-sales-total" id="so-total-revenue">—</span>
                </div>
                <div style="display:flex;justify-content:space-between;margin-top:4px;">
                    <span style="font-size:13px;font-weight:600;color:#374151;">Estimated Gross Profit</span>
                    <span style="font-size:15px;font-weight:700;" id="so-total-profit">—</span>
                </div>
            </div>
        </div>

        <!-- total cost preview -->
        <div style="background:#e0f2fe;padding:14px;border-radius:8px;margin-top:10px;">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                <div>
                    <h3 style="margin:0;color:#0c4a6e;font-size:18px;">Total Cost: &#8369;<span id="total-cost-display">0.00</span></h3>
                    <p style="margin:4px 0 0;color:#075985;font-size:12px;" id="total-cost-desc">Quantity x Cost Per Unit</p>
                </div>
                <div style="text-align:right;font-size:12px;color:#0284c7;">
                    <div>Units to stock: <strong id="units-preview">—</strong></div>
                    <div id="batches-preview" style="display:none;">Batches: <strong id="batches-count-preview">—</strong></div>
                </div>
            </div>
        </div>

        <div class="bntm-form-group" style="margin-top:12px;">
            <label>Notes</label>
            <textarea name="notes" rows="2" placeholder="Additional information about this transaction"></textarea>
        </div>

        <button type="submit" id="batch-submit-btn" class="bntm-btn-primary">Record Transaction</button>
        <div id="batch-message" style="margin-top:8px;"></div>
    </form>
</div>

<!-- history -->
<div class="bntm-form-section">
    <h3>Stock Movement History (<?php echo count($batches); ?>)</h3>
    <?php if (empty($batches)): ?>
        <p>No stock movements recorded yet.</p>
    <?php else: ?>
    <div class="bntm-table-wrapper">
        <table class="bntm-table">
            <thead><tr>
                <th>Date</th><th>Type</th><th>Reference</th><th>Product</th>
                <th>Units</th><th>Batches</th><th>Cost/unit</th><th>Selling/unit</th><th>Total Cost</th><th>Sales Value</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($batches as $b):
                $sell_price = floatval($b->product_selling_price);
                $sales_value = ($b->type === 'stock_out' && !empty($b->include_in_finance)) ? $b->quantity * $sell_price : null;
            ?>
            <tr>
                <td><?php echo date('M d, Y', strtotime($b->manufacture_date ?: $b->created_at)); ?></td>
                <td><?php echo $b->type === 'stock_in'
                    ? '<span style="display:inline-flex;align-items:center;gap:4px;color:#059669;font-weight:600;font-size:12px;">'.in_icon('arrow-down',14).' In</span>'
                    : '<span style="display:inline-flex;align-items:center;gap:4px;color:#dc2626;font-weight:600;font-size:12px;">'.in_icon('arrow-up',14).' Out</span>'; ?></td>
                <td><?php echo esc_html($b->reference_number ?: $b->batch_code); ?></td>
                <td><?php echo esc_html($b->product_name); ?></td>
                <td><?php echo $b->quantity; ?></td>
                <td><?php echo $b->batches_count > 1 ? $b->batches_count.' batches' : '—'; ?></td>
                <td><?php echo $b->type === 'stock_out' ? '<span style="color:#9ca3af;">—</span>' : '&#8369;'.number_format($b->cost_per_unit, 4); ?></td>
                <td><?php echo $sell_price > 0 ? '&#8369;'.number_format($sell_price, 2) : '—'; ?></td>
                <td>&#8369;<?php echo number_format($b->total_cost, 2); ?></td>
                <td><?php echo $sales_value !== null
                    ? '<span style="color:#059669;font-weight:600;">&#8369;'.number_format($sales_value, 2).'</span>'
                    : '<span style="color:#9ca3af;">—</span>'; ?></td>
               
                <td>
                    <button class="bntm-btn-small bntm-btn-danger in-delete-batch"
                        data-id="<?php echo $b->id; ?>"
                        data-type="<?php echo $b->type; ?>"
                        data-qty="<?php echo $b->quantity; ?>">Delete</button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
(function(){
    var nonce    = '<?php echo esc_js($nonce); ?>';
    var prodData = <?php echo json_encode($prod_data); ?>;
    var rawStock = <?php echo json_encode($raw_stock); ?>;

    var selProd   = document.getElementById('batch-product-select');
    var selType   = document.getElementById('batch-type-select');
    var qtyInput  = document.getElementById('batch-quantity');
    var cpuInput  = document.getElementById('batch-cpu');
    var totalCostInput = document.getElementById('batch-total-cost-input');
    var cpuGroup  = document.getElementById('batch-cpu-group');
    var totalCostGroup = document.getElementById('batch-total-cost-group');
    var byBatch   = document.getElementById('batch-by-batch');
    var bbWrap    = document.getElementById('by-batch-wrap');
    var bciPanel  = document.getElementById('batch-cost-info');
    var matPanel  = document.getElementById('batch-materials-panel');
    var soInfoWrap= document.getElementById('stock-out-info-wrap');
    var soPreview = document.getElementById('stock-out-sales-preview');
    var saleToggle = document.getElementById('include-in-finance');
    var submitBtn = document.getElementById('batch-submit-btn');
    var syncingBatchCost = false;

    function getYield(){ var d=prodData[selProd.value]; return d?d.batch_yield:1; }
    function setBatchSubmitEnabled(enabled){
        if(submitBtn){ submitBtn.disabled = !enabled; }
    }
    function clearMaterialCheck(){
        var warning=document.getElementById('batch-mat-warning');
        var list=document.getElementById('batch-mat-req-list');
        matPanel.style.display='none';
        if(list) list.innerHTML='';
        if(warning){ warning.style.display='none'; warning.textContent=''; }
        setBatchSubmitEnabled(true);
    }

    function checkMaterials(pid, numBatches){
        var d=prodData[pid];
        if(!d||!d.materials||!d.materials.length){ clearMaterialCheck(); return true; }
        matPanel.style.display='block';
        var list=document.getElementById('batch-mat-req-list');
        var warning=document.getElementById('batch-mat-warning');
        var canProduce=true; var missingList=[]; var html='';
        d.materials.forEach(function(m){
            var needed=(m.qty||0)*numBatches;
            var matKey=m.name.trim().toLowerCase();
            var avail=rawStock[matKey]!==undefined?rawStock[matKey]:null;
            var chipClass, label;
            if(avail===null){ chipClass='chip-bad'; label=m.name+': no raw material inventory record found'; canProduce=false; missingList.push(m.name); }
            else if(avail>=needed){ chipClass='chip-ok'; label=m.name+': need '+needed+' '+(m.uom||'')+', have '+avail; }
            else { chipClass='chip-bad'; label=m.name+': need '+needed+' '+(m.uom||'')+', only '+avail+' available'; canProduce=false; missingList.push(m.name); }
            html+='<span class="in-mat-req-chip '+chipClass+'">'+label+'</span>';
        });
        list.innerHTML=html;
        if(!canProduce&&missingList.length){ warning.style.display='block'; warning.textContent='Not enough stock for: '+missingList.join(', ')+'. Please restock before producing.'; setBatchSubmitEnabled(false); }
        else { warning.style.display='none'; setBatchSubmitEnabled(true); }
        return canProduce;
    }

    function updateSalesPreview(){
        var type=selType.value;
        var pid=selProd.value;
        var d=prodData[pid]||null;
        if(type!=='stock_out'||!d||!saleToggle.checked){ soPreview.style.display='none'; return; }
        var rawQty=parseInt(qtyInput.value)||0;
        var units=rawQty;
        var sellPrice=d.selling_price;
        var totalRevenue=units*sellPrice;
        var totalProfit=0;
        var profitCol='#059669';
        function sf(id,v){ var el=document.getElementById(id); if(el) el.textContent=v; }
        function sfc(id,c){ var el=document.getElementById(id); if(el) el.style.color=c; }
        sf('so-units', units+' unit'+(units!==1?'s':''));
        sf('so-sell-price', '\u20B1'+sellPrice.toFixed(2));
        sf('so-profit-pu', '—');
        sf('so-total-revenue', '\u20B1'+totalRevenue.toFixed(2));
        sf('so-total-profit', '—');
        sfc('so-total-profit', profitCol);
        soPreview.style.display = units>0 ? 'block' : 'none';
    }

    function updateUI(){
        var pid=selProd.value;
        var type=selType.value;
        var d=prodData[pid]||null;
        setBatchSubmitEnabled(true);

        if(d&&pid){
            document.getElementById('bci-cpu').textContent='\u20B1'+parseFloat(d.cost_per_unit).toFixed(4);
            document.getElementById('bci-sell').textContent='\u20B1'+parseFloat(d.selling_price).toFixed(2);
            document.getElementById('bci-yield').textContent=d.batch_yield;
            document.getElementById('bci-bmc').textContent='\u20B1'+parseFloat(d.batch_mat_cost).toFixed(2);
            cpuInput.value=parseFloat(d.cost_per_unit).toFixed(4);
        }

        if(type==='stock_in'&&d&&pid){
            bciPanel.style.display='block';
            if(cpuGroup) cpuGroup.style.display='';
            if(totalCostGroup) totalCostGroup.style.display='';
        } else {
            bciPanel.style.display='none';
            if(cpuGroup) cpuGroup.style.display='none';
            if(totalCostGroup) totalCostGroup.style.display='none';
        }

        if(type==='stock_in'&&d&&d.batch_yield>1){
            bbWrap.style.display='block';
            byBatch.checked = true;
            document.getElementById('by-batch-hint').textContent='Each batch = '+d.batch_yield+' units. Enter how many batches you produced.';
        } else { bbWrap.style.display='none'; byBatch.checked=false; }
    
        /* stock-out: sale toggle controls selling-price behavior */
        soInfoWrap.style.display=type==='stock_out'?'block':'none';
        if(type==='stock_out'){
            if(!d || d.inventory_type === 'Raw Material' || d.inventory_type === 'Supplies' || d.inventory_type === 'Other' || parseFloat(d.selling_price||0) <= 0){
                saleToggle.checked = false;
            }
        } else {
            saleToggle.checked = false;
        }
        
        if(type==='stock_in'&&pid&&d&&byBatch.checked){
            var rawQty=parseInt(qtyInput.value)||1;
            checkMaterials(pid,rawQty);
        } else { clearMaterialCheck(); }

        updateQtyLabel(); updateTotal(); updateSalesPreview();
    }

    function updateQtyLabel(){
        var isByBatch=byBatch.checked;
        var yld=getYield();
        var ql=document.getElementById('qty-label');
        var qh=document.getElementById('qty-hint');
        if(isByBatch){ ql.textContent='Number of Batches *'; qh.textContent='Each batch yields '+yld+' units. Total units = batches x '+yld+'.'; }
        else { ql.textContent='Quantity (units) *'; qh.textContent=''; }
        updateTotal(); updateSalesPreview();
    }

    function updateTotal(){
        var rawQty=parseInt(qtyInput.value)||0;
        var cpu=parseFloat(cpuInput.value)||0;
        var yld=getYield();
        var isBB=byBatch.checked;
        var units=isBB?rawQty*yld:rawQty;
        var total=units*cpu;
        if(!syncingBatchCost && totalCostInput){ totalCostInput.value=total ? total.toFixed(2) : ''; }
        document.getElementById('total-cost-display').textContent=total.toFixed(2);
        document.getElementById('units-preview').textContent=units+' unit'+(units!==1?'s':'');
        var bPrev=document.getElementById('batches-preview');
        var bCnt=document.getElementById('batches-count-preview');
        if(isBB){ bPrev.style.display='block'; bCnt.textContent=rawQty+' batch'+(rawQty!==1?'es':''); document.getElementById('total-cost-desc').textContent=rawQty+' batches x '+yld+' units/batch x \u20B1'+cpu.toFixed(4)+'/unit'; }
        else { bPrev.style.display='none'; document.getElementById('total-cost-desc').textContent=rawQty+' units x \u20B1'+cpu.toFixed(4)+'/unit'; }
        if(selType.value==='stock_in'&&selProd.value&&isBB){
            var d=prodData[selProd.value];
            if(d){ checkMaterials(selProd.value, rawQty); }
        } else if(selType.value==='stock_in') {
            clearMaterialCheck();
        }
        updateSalesPreview();
    }

    selProd.addEventListener('change', updateUI);
    selType.addEventListener('change', updateUI);
    qtyInput.addEventListener('input', updateTotal);
    cpuInput.addEventListener('input', function(){ updateTotal(); updateSalesPreview(); });
    if(totalCostInput){
        totalCostInput.addEventListener('input', function(){
            var rawQty=parseInt(qtyInput.value)||0;
            var yld=getYield();
            var units=byBatch.checked?rawQty*yld:rawQty;
            var total=parseFloat(this.value)||0;
            syncingBatchCost = true;
            cpuInput.value = units > 0 ? (total / units).toFixed(4) : '0.0000';
            syncingBatchCost = false;
            updateTotal();
            updateSalesPreview();
        });
    }
    byBatch.addEventListener('change', updateQtyLabel);
    saleToggle.addEventListener('change', updateSalesPreview);

    /* SUBMIT */
    document.getElementById('in-add-batch-form').addEventListener('submit',function(e){
        e.preventDefault();
        var fd=new FormData(this);
        fd.append('action','in_add_batch'); fd.append('nonce',nonce);
        var btn=document.getElementById('batch-submit-btn');
        btn.disabled=true; btn.textContent='Processing...';
        fetch(ajaxurl,{method:'POST',body:fd}).then(function(r){ return r.json(); }).then(function(j){
            var msg=document.getElementById('batch-message');
            msg.innerHTML='<div class="bntm-notice bntm-notice-'+(j.success?'success':'error')+'">'+j.data.message+'</div>';
            btn.disabled=false; btn.textContent='Record Transaction';
            if(j.success) setTimeout(function(){ location.reload(); },1500);
        }).catch(function(){
            btn.disabled=false; btn.textContent='Record Transaction';
        });
    });

    /* DELETE */
    document.querySelectorAll('.in-delete-batch').forEach(function(btn){
        btn.addEventListener('click',function(){
            var type=this.dataset.type, qty=this.dataset.qty;
            var action=type==='stock_in'?'reduce':'increase';
            if(!confirm('Delete this transaction?\n\nThis will '+action+' product stock by '+qty+' units.')) return;
            var fd=new FormData();
            fd.append('action','in_delete_batch'); fd.append('batch_id',this.dataset.id); fd.append('nonce',nonce);
            this.disabled=true; this.textContent='…';
            var self=this;
            fetch(ajaxurl,{method:'POST',body:fd}).then(function(r){ return r.json(); }).then(function(j){
                if(j.success) location.reload(); else { alert(j.data.message); self.disabled=false; self.textContent='Delete'; }
            });
        });
    });
})();
</script>
<?php return ob_get_clean();
}

/* ============================================================
   AJAX: ADD PRODUCT
   ============================================================ */
function bntm_ajax_in_add_product() {
    check_ajax_referer('in_nonce', 'nonce');
    if (!in_current_user_can_manage_business()) wp_send_json_error(['message'=>'Unauthorized']);

    global $wpdb;
    $table       = $wpdb->prefix.'in_products';
    $business_id = in_get_current_business_id();
    $inventory_type = sanitize_text_field($_POST['inventory_type'] ?? 'Product');
    $cost_only_types = ['Raw Material', 'Supplies', 'Other'];
    $is_cost_only = in_array($inventory_type, $cost_only_types, true);

    $product_limit = in_get_product_limit_for_business($business_id);
    if ($product_limit > 0) {
        $cnt = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE business_id = %d", $business_id));
        if ($cnt >= $product_limit) wp_send_json_error(['message'=>"Product limit ({$product_limit}) reached."]);
    }

    $sku     = sanitize_text_field($_POST['sku'] ?? '');
    $barcode = sanitize_text_field($_POST['barcode'] ?? '');
    if (empty($sku))     $sku     = 'INV-'.strtoupper(substr(md5(uniqid()), 0, 8));
    if (empty($barcode)) $barcode = 'TMP-'.str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

    if ($wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE business_id = %d AND barcode=%s", $business_id, $barcode)))
        wp_send_json_error(['message'=>'Barcode already exists.']);

    $mats_raw   = wp_unslash($_POST['costing_materials'] ?? '[]');
    $fixeds     = sanitize_text_field($_POST['costing_fixed_costs'] ?? '[]');
    $mats_arr   = json_decode($mats_raw, true) ?: [];
    $fixeds_arr = json_decode(stripslashes($fixeds), true) ?: [];
    $mats_arr = in_sanitize_costing_materials($mats_arr);
    $mats     = wp_json_encode($mats_arr);
    $yld      = $is_cost_only ? 1 : max(1, intval($_POST['batch_yield'] ?? 1));
    $batch_mat= in_calculate_batch_material_cost($mats_arr, $business_id);
    $mat_pu   = $batch_mat / $yld;
    $lab_pu   = $is_cost_only ? 0 : floatval($_POST['labor_cost_per_batch'] ?? 0) / $yld;
    $pkg_pu   = $is_cost_only ? 0 : floatval($_POST['packaging_cost_per_unit'] ?? 0);
    $var_pu   = $is_cost_only ? 0 : floatval($_POST['variable_overhead_per_unit'] ?? 0);
    $fix_pu   = $is_cost_only ? 0 : array_reduce($fixeds_arr, fn($c,$f)=>$c+floatval($f['amount'] ?? 0), 0);
    $cost_pu  = $is_cost_only ? floatval($_POST['direct_cost_per_unit'] ?? 0) : ($mat_pu + $lab_pu + $pkg_pu + $var_pu + $fix_pu);
    $selling_price = $is_cost_only ? 0 : floatval($_POST['selling_price']);
    if ($is_cost_only) {
        $mats = '[]';
        $fixeds = '[]';
        $mats_arr = [];
        $fixeds_arr = [];
    }

    $existing_raw_materials = in_get_raw_material_records($business_id);
    foreach ($mats_arr as $m) {
        if (empty($m['fromInventory']) && !empty($m['addToInventory']) && !empty($m['name'])) {
            $nn = in_normalize_material_name($m['name']);
            if (isset($existing_raw_materials[$nn])) continue;
            $unit_cost = floatval($m['qty'] ?? 0) > 0 ? floatval($m['cost'] ?? 0) / floatval($m['qty'] ?? 0) : floatval($m['cost'] ?? 0);
            $wpdb->insert($table, [
                'rand_id'=>function_exists('bntm_rand_id')?bntm_rand_id():wp_generate_uuid4(),
                'business_id'=>$business_id,'name'=>sanitize_text_field($m['name']).' (Material)',
                'sku'=>'MAT-'.strtoupper(substr(md5($m['name'].uniqid()),0,8)),
                'barcode'=>'TMP-'.str_pad(rand(0,999999),6,'0',STR_PAD_LEFT),
                'inventory_type'=>'Raw Material','cost_per_unit'=>$unit_cost,'selling_price'=>0,
                'stock_quantity'=>0,'reorder_level'=>10,
            ],['%s','%d','%s','%s','%s','%s','%f','%f','%d','%d']);
        }
    }

    $result = $wpdb->insert($table, [
        'rand_id'=>function_exists('bntm_rand_id')?bntm_rand_id():wp_generate_uuid4(),
        'business_id'=>$business_id,'name'=>sanitize_text_field($_POST['name']),
        'sku'=>$sku,'barcode'=>$barcode,
        'inventory_type'=>$inventory_type,
        'cost_per_unit'=>$cost_pu,'selling_price'=>$selling_price,
        'stock_quantity'=>intval($_POST['initial_stock'] ?? 0),'reorder_level'=>intval($_POST['reorder_level'] ?? 10),
        'description'=>sanitize_textarea_field($_POST['description'] ?? ''),
        'image'=>esc_url_raw($_POST['product_image'] ?? ''),
        'batch_yield'=>$yld,'labor_cost_per_batch'=>$is_cost_only ? 0 : floatval($_POST['labor_cost_per_batch'] ?? 0),
        'packaging_cost_per_unit'=>$pkg_pu,'variable_overhead_per_unit'=>$var_pu,
        'costing_materials'=>$mats,'costing_fixed_costs'=>$fixeds,
        'target_margin_pct'=>$is_cost_only ? 0 : floatval($_POST['target_margin_pct'] ?? 30),
    ],['%s','%d','%s','%s','%s','%s','%f','%f','%d','%d','%s','%s','%d','%f','%f','%f','%s','%s','%f']);

    if ($result) wp_send_json_success(['message'=>'Product added successfully!']);
    else wp_send_json_error(['message'=>'Failed to add product. '.$wpdb->last_error]);
}

/* ============================================================
   AJAX: UPDATE PRODUCT
   ============================================================ */
function bntm_ajax_in_update_product() {
    check_ajax_referer('in_nonce', 'nonce');
    if (!in_current_user_can_manage_business()) wp_send_json_error(['message'=>'Unauthorized']);

    global $wpdb;
    $table      = $wpdb->prefix.'in_products';
    $business_id = in_get_current_business_id();
    $product_id = intval($_POST['product_id']);
    $inventory_type = sanitize_text_field($_POST['inventory_type'] ?? 'Product');
    $cost_only_types = ['Raw Material', 'Supplies', 'Other'];
    $is_cost_only = in_array($inventory_type, $cost_only_types, true);
    $product    = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d AND business_id = %d", $product_id, $business_id));
    if (!$product) wp_send_json_error(['message'=>'Product not found.']);

    $barcode = sanitize_text_field($_POST['barcode'] ?? '');
    if (empty($barcode)) $barcode = 'TMP-'.str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    if ($barcode !== $product->barcode) {
        if ($wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE business_id = %d AND barcode=%s AND id!=%d", $business_id, $barcode, $product_id)))
            wp_send_json_error(['message'=>'Barcode already exists.']);
    }

    $mats_raw   = wp_unslash($_POST['costing_materials'] ?? '[]');
    $fixeds     = sanitize_text_field($_POST['costing_fixed_costs'] ?? '[]');
    $mats_arr   = json_decode($mats_raw, true) ?: [];
    $fixeds_arr = json_decode(stripslashes($fixeds), true) ?: [];
    $mats_arr = in_sanitize_costing_materials($mats_arr);
    $mats     = wp_json_encode($mats_arr);
    $yld      = $is_cost_only ? 1 : max(1, intval($_POST['batch_yield'] ?? 1));
    $batch_mat= in_calculate_batch_material_cost($mats_arr, $business_id);
    $mat_pu   = $batch_mat / $yld;
    $lab_pu   = $is_cost_only ? 0 : floatval($_POST['labor_cost_per_batch'] ?? 0) / $yld;
    $pkg_pu   = $is_cost_only ? 0 : floatval($_POST['packaging_cost_per_unit'] ?? 0);
    $var_pu   = $is_cost_only ? 0 : floatval($_POST['variable_overhead_per_unit'] ?? 0);
    $fix_pu   = $is_cost_only ? 0 : array_reduce($fixeds_arr, fn($c,$f)=>$c+floatval($f['amount'] ?? 0), 0);
    $cost_pu  = $is_cost_only ? floatval($_POST['direct_cost_per_unit'] ?? 0) : ($mat_pu + $lab_pu + $pkg_pu + $var_pu + $fix_pu);
    $selling_price = $is_cost_only ? 0 : floatval($_POST['selling_price']);
    if ($is_cost_only) {
        $mats = '[]';
        $fixeds = '[]';
        $mats_arr = [];
        $fixeds_arr = [];
    }

    $existing_raw_materials = in_get_raw_material_records($business_id);
    foreach ($mats_arr as $m) {
        if (empty($m['fromInventory']) && !empty($m['addToInventory']) && !empty($m['name'])) {
            $nn = in_normalize_material_name($m['name']);
            if (!isset($existing_raw_materials[$nn])) {
                $unit_cost = floatval($m['qty'] ?? 0) > 0 ? floatval($m['cost'] ?? 0) / floatval($m['qty'] ?? 0) : floatval($m['cost'] ?? 0);
                $wpdb->insert($table, [
                    'rand_id'=>function_exists('bntm_rand_id')?bntm_rand_id():wp_generate_uuid4(),
                    'business_id'=>$business_id,'name'=>sanitize_text_field($m['name']).' (Material)',
                    'sku'=>'MAT-'.strtoupper(substr(md5($m['name'].uniqid()),0,8)),
                    'barcode'=>'TMP-'.str_pad(rand(0,999999),6,'0',STR_PAD_LEFT),
                    'inventory_type'=>'Raw Material','cost_per_unit'=>$unit_cost,'selling_price'=>0,
                    'stock_quantity'=>0,'reorder_level'=>10,
                ],['%s','%d','%s','%s','%s','%s','%f','%f','%d','%d']);
            }
        }
    }

    $result = $wpdb->update($table, [
        'name'=>sanitize_text_field($_POST['name']),'sku'=>sanitize_text_field($_POST['sku'] ?? ''),
        'barcode'=>$barcode,'inventory_type'=>$inventory_type,
        'cost_per_unit'=>$cost_pu,'selling_price'=>$selling_price,
        'reorder_level'=>intval($_POST['reorder_level']),
        'description'=>sanitize_textarea_field($_POST['description'] ?? ''),
        'image'=>esc_url_raw($_POST['product_image'] ?? ''),
        'batch_yield'=>$yld,'labor_cost_per_batch'=>$is_cost_only ? 0 : floatval($_POST['labor_cost_per_batch'] ?? 0),
        'packaging_cost_per_unit'=>$pkg_pu,'variable_overhead_per_unit'=>$var_pu,
        'costing_materials'=>$mats,'costing_fixed_costs'=>$fixeds,
        'target_margin_pct'=>$is_cost_only ? 0 : floatval($_POST['target_margin_pct'] ?? 30),
    ],['id'=>$product_id, 'business_id'=>$business_id],
    ['%s','%s','%s','%s','%f','%f','%d','%s','%s','%d','%f','%f','%f','%s','%s','%f'],['%d','%d']);

    if ($result !== false) wp_send_json_success(['message'=>'Product updated successfully!']);
    else wp_send_json_error(['message'=>'Failed to update product.']);
}

/* ============================================================
   AJAX: DELETE PRODUCT
   ============================================================ */
function bntm_ajax_in_delete_product() {
    check_ajax_referer('in_nonce', 'nonce');
    if (!in_current_user_can_manage_business()) wp_send_json_error(['message'=>'Unauthorized']);
    global $wpdb;
    $result = $wpdb->delete($wpdb->prefix.'in_products', ['id'=>intval($_POST['product_id']), 'business_id'=>in_get_current_business_id()], ['%d','%d']);
    if ($result) wp_send_json_success(['message'=>'Deleted.']);
    else         wp_send_json_error(['message'=>'Delete failed.']);
}

function in_check_material_stock_for_production($product, $units) {
    $materials = json_decode($product->costing_materials ?: '[]', true) ?: [];
    if (empty($materials)) return ['success'=>true,'materials'=>[],'batches'=>0];
    $yield          = max(1, intval($product->batch_yield));
    $batches_needed = max(1, floatval($units) / $yield);
    $raw_materials  = in_get_raw_material_records($product->business_id ?? 0);
    $material_plan  = []; $missing = [];
    foreach ($materials as $material) {
        $name       = sanitize_text_field($material['name'] ?? '');
        $normalized = in_normalize_material_name($name);
        $qty_per_batch = floatval($material['qty'] ?? 0);
        $needed     = $qty_per_batch * $batches_needed;
        $record     = $raw_materials[$normalized] ?? null;
        if (!$record)  { $missing[] = $name.' (not found in raw materials)'; continue; }
        if ($record['stock_quantity'] < $needed) { $missing[] = $name.' (need '.$needed.', have '.$record['stock_quantity'].')'; continue; }
        $material_plan[] = ['product_id'=>$record['id'],'name'=>$record['name'],'needed'=>$needed];
    }
    if (!empty($missing)) return ['success'=>false,'message'=>'Insufficient materials: '.implode(', ', $missing)];
    return ['success'=>true,'materials'=>$material_plan,'batches'=>$batches_needed];
}

function in_sync_batch_to_finance($batch_id, $business_id = 0) {
    global $wpdb;

    $txn = $wpdb->prefix.'fn_transactions';
    $bt  = $wpdb->prefix.'in_batches';
    $pt  = $wpdb->prefix.'in_products';
    $business_id = absint($business_id ?: in_get_current_business_id());

    $batch = $wpdb->get_row($wpdb->prepare(
        "SELECT b.*, p.name as product_name, p.selling_price, p.inventory_type, p.business_id,
                p.costing_materials, p.costing_fixed_costs, p.labor_cost_per_batch,
                p.packaging_cost_per_unit, p.variable_overhead_per_unit
         FROM {$bt} b
         LEFT JOIN {$pt} p ON b.product_id = p.id
         WHERE b.id = %d AND b.business_id = %d",
        $batch_id,
        $business_id
    ));
    if (!$batch) {
        return ['success' => false, 'message' => 'Batch not found.'];
    }

    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$txn} WHERE business_id = %d AND reference_type = %s AND reference_id = %d",
        $business_id,
        'inventory_batch',
        $batch_id
    ));
    if ($existing) {
        return ['success' => true, 'message' => 'Batch already synced.', 'already_synced' => true];
    }

    $is_income = $batch->type === 'stock_out' && !empty($batch->include_in_finance);
    $amount = $is_income
        ? (floatval($batch->quantity) * floatval($batch->selling_price))
        : ($batch->type === 'stock_in'
            ? in_calculate_stock_in_finance_expense($batch, $batch)
            : floatval($batch->total_cost));
    $inserted = $wpdb->insert($txn, [
        'rand_id'        => function_exists('bntm_rand_id') ? bntm_rand_id() : wp_generate_uuid4(),
        'business_id'    => $business_id,
        'type'           => $is_income ? 'income' : 'expense',
        'amount'         => $amount,
        'category'       => $is_income ? 'Inventory Sales' : ($batch->type === 'stock_out' ? 'Inventory Usage' : 'Inventory Purchase'),
        'notes'          => $is_income
            ? "Inventory Stock Sale\nRef: {$batch->reference_number}\nProduct: {$batch->product_name}\nQty: {$batch->quantity} units @ P".number_format(floatval($batch->selling_price), 2)."/unit"
            : (($batch->type === 'stock_out')
                ? "Inventory Stock Usage\nRef: {$batch->reference_number}\nProduct: {$batch->product_name}\nQty: {$batch->quantity} units @ P".number_format(floatval($batch->cost_per_unit), 4)."/unit"
                : "Inventory Stock In\nRef: {$batch->reference_number}\nProduct: {$batch->product_name}\nQty: {$batch->quantity} units\nFinance expense synced: P".number_format(floatval($amount), 2)),
        'reference_type' => 'inventory_batch',
        'reference_id'   => $batch_id,
        'created_at'     => current_time('mysql'),
    ]);

    if (!$inserted) {
        return ['success' => false, 'message' => 'Failed to sync batch to Finance.'];
    }

    if (function_exists('fn_update_cashflow_summary')) {
        fn_update_cashflow_summary($business_id);
    }

    return ['success' => true, 'message' => 'Batch synced to Finance.'];
}

function in_revert_batch_finance_sync($batch_id, $business_id = 0) {
    global $wpdb;

    $txn = $wpdb->prefix.'fn_transactions';
    $business_id = absint($business_id ?: in_get_current_business_id());
    $deleted = $wpdb->delete($txn, [
        'reference_type' => 'inventory_batch',
        'reference_id'   => $batch_id,
        'business_id'    => $business_id,
    ], ['%s','%d','%d']);

    if ($deleted && function_exists('fn_update_cashflow_summary')) {
        fn_update_cashflow_summary($business_id);
    }

    return ['success' => $deleted !== false, 'deleted' => intval($deleted)];
}

/* ============================================================
   AJAX: ADD BATCH — stock-out always include_in_finance=1
   ============================================================ */
function bntm_ajax_in_add_batch() {
    check_ajax_referer('in_nonce', 'nonce');
    if (!in_current_user_can_manage_business()) wp_send_json_error(['message'=>'Unauthorized']);

    global $wpdb;
    $bt = $wpdb->prefix.'in_batches';
    $pt = $wpdb->prefix.'in_products';
    $business_id = in_get_current_business_id();

    $product_id  = intval($_POST['product_id']);
    $type        = sanitize_text_field($_POST['type']);
    $raw_qty     = intval($_POST['quantity']);
    $by_batch    = !empty($_POST['by_batch']);
    $cpu         = floatval($_POST['cost_per_unit']);
    $ref         = sanitize_text_field($_POST['reference_number'] ?? '');
    $date        = sanitize_text_field($_POST['transaction_date']);
    $notes       = sanitize_textarea_field($_POST['notes'] ?? '');

    $include_in_finance = ($type === 'stock_out' && !empty($_POST['include_in_finance'])) ? 1 : 0;

    $product = $wpdb->get_row($wpdb->prepare("SELECT * FROM $pt WHERE id=%d AND business_id = %d", $product_id, $business_id));
    if (!$product) wp_send_json_error(['message'=>'Product not found.']);

    $yld         = max(1, intval($product->batch_yield));
    $batches_cnt = $by_batch ? $raw_qty : 1;
    $units       = $by_batch ? ($raw_qty * $yld) : $raw_qty;

    if ($type === 'stock_out' && $product->stock_quantity < $units)
        wp_send_json_error(['message'=>'Insufficient stock. Available: '.$product->stock_quantity]);

    $material_check = ['success'=>true,'materials'=>[]];
    if ($type === 'stock_in' && $by_batch) {
        $material_check = in_check_material_stock_for_production($product, $units);
        if (!$material_check['success']) wp_send_json_error(['message'=>$material_check['message']]);
        $batches_cnt = max(1, intval($raw_qty));
    }

    $total_cost = $units * $cpu;
    if (empty($ref)) $ref = strtoupper($type).'-'.date('Ymd').'-'.substr(md5(uniqid()), 0, 6);

    $wpdb->query('START TRANSACTION');
    try {
        $result = $wpdb->insert($bt, [
            'rand_id'=>function_exists('bntm_rand_id')?bntm_rand_id():wp_generate_uuid4(),
            'business_id'=>$business_id,'product_id'=>$product_id,'batch_code'=>$ref,
            'type'=>$type,'quantity'=>$units,'batches_count'=>$batches_cnt,'cost_per_unit'=>$cpu,
            'total_cost'=>$total_cost,'reference_number'=>$ref,'manufacture_date'=>$date,
            'notes'=>$notes,'include_in_finance'=>$include_in_finance,
        ],['%s','%d','%d','%s','%s','%d','%d','%f','%f','%s','%s','%s','%d']);

        if (!$result) throw new Exception('Failed to record transaction.');

        $sign = $type === 'stock_in' ? '+' : '-';
        $stock_result = $wpdb->query($wpdb->prepare("UPDATE $pt SET stock_quantity=stock_quantity{$sign}%d WHERE id=%d AND business_id = %d", $units, $product_id, $business_id));
        if ($stock_result === false) throw new Exception('Failed to update product stock.');

        if ($type === 'stock_in' && $by_batch && !empty($material_check['materials'])) {
            foreach ($material_check['materials'] as $material) {
                $mr = $wpdb->query($wpdb->prepare("UPDATE $pt SET stock_quantity=stock_quantity-%f WHERE id=%d AND business_id = %d AND stock_quantity>=%f", $material['needed'], $material['product_id'], $business_id, $material['needed']));
                if ($mr === false || $mr === 0) throw new Exception('Failed to deduct material stock for '.$material['name'].'.');
            }
        }

        $wpdb->query('COMMIT');

        $batch_id = intval($wpdb->insert_id);
        $finance_sync = in_sync_batch_to_finance($batch_id, $business_id);
        if (!$finance_sync['success'] && empty($finance_sync['already_synced'])) {
            throw new Exception($finance_sync['message']);
        }

        if ($type === 'stock_out' && $include_in_finance) {
            $sell_price = floatval($product->selling_price);
            $sales_total = $units * $sell_price;
            $msg = "Stock removed: {$units} unit(s). Sale of &#8369;".number_format($sales_total,2)." synced to Finance automatically.";
        } elseif ($type === 'stock_out') {
            $msg = "Stock removed: {$units} unit(s). Usage synced to Finance at cost.";
        } else {
            $msg = $by_batch
                ? "Stock added: {$units} unit(s)".($batches_cnt>1?" ({$batches_cnt} batch(es))":'').". Materials deducted from raw inventory and synced to Finance."
                : "Stock added: {$units} unit(s). Recorded using quantity-based costing without deducting raw material inventory.";
        }
        wp_send_json_success(['message'=>$msg]);
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        wp_send_json_error(['message'=>$e->getMessage()]);
    }
}

/* ============================================================
   AJAX: DELETE BATCH
   ============================================================ */
function bntm_ajax_in_delete_batch() {
    check_ajax_referer('in_nonce', 'nonce');
    if (!in_current_user_can_manage_business()) wp_send_json_error(['message'=>'Unauthorized']);
    global $wpdb;
    $bt    = $wpdb->prefix.'in_batches';
    $pt    = $wpdb->prefix.'in_products';
    $business_id = in_get_current_business_id();
    $batch = $wpdb->get_row($wpdb->prepare("SELECT * FROM $bt WHERE id=%d AND business_id = %d", intval($_POST['batch_id']), $business_id));
    if (!$batch) wp_send_json_error(['message'=>'Batch not found.']);
    in_revert_batch_finance_sync($batch->id, $business_id);
    $result = $wpdb->delete($bt, ['id'=>$batch->id, 'business_id'=>$business_id], ['%d','%d']);
    if ($result) {
        $sign = $batch->type === 'stock_in' ? '-' : '+';
        $wpdb->query($wpdb->prepare("UPDATE $pt SET stock_quantity=GREATEST(0,stock_quantity{$sign}%d) WHERE id=%d AND business_id = %d", $batch->quantity, $batch->product_id, $business_id));
        wp_send_json_success(['message'=>'Transaction deleted and stock reversed.']);
    } else wp_send_json_error(['message'=>'Delete failed.']);
}

/* ============================================================
   AJAX: IMAGE UPLOAD
   ============================================================ */
function bntm_ajax_in_upload_product_image() {
    check_ajax_referer('in_upload_image', '_ajax_nonce');
    if (!in_current_user_can_manage_business()) wp_send_json_error('Unauthorized');
    if (!isset($_FILES['image'])) wp_send_json_error('No file uploaded');
    require_once(ABSPATH.'wp-admin/includes/image.php');
    require_once(ABSPATH.'wp-admin/includes/file.php');
    require_once(ABSPATH.'wp-admin/includes/media.php');
    $upload = wp_handle_upload($_FILES['image'], ['test_form'=>false]);
    if (isset($upload['error'])) wp_send_json_error($upload['error']);
    wp_send_json_success(['url'=>$upload['url']]);
}

/* ============================================================
   IMPORT TAB — stock-out shown as income using selling price
   ============================================================ */
function in_import_tab($business_id) {
    global $wpdb;
    $bt  = $wpdb->prefix.'in_batches';
    $txn = $wpdb->prefix.'fn_transactions';
    $pt  = $wpdb->prefix.'in_products';

    $batches = $wpdb->get_results($wpdb->prepare("
        SELECT b.*,p.name as product_name,p.selling_price,
        CASE
            WHEN b.type='stock_out' AND b.include_in_finance=1 THEN (b.quantity * COALESCE(p.selling_price, 0))
            ELSE b.total_cost
        END as finance_amount,
        CASE
            WHEN b.type='stock_out' AND b.include_in_finance=1 THEN 'income'
            ELSE 'expense'
        END as finance_type,
        (SELECT COUNT(*) FROM {$txn} WHERE business_id = %d AND reference_type='inventory_batch' AND reference_id=b.id) as is_imported
        FROM {$bt} b LEFT JOIN {$pt} p ON b.product_id=p.id
        WHERE b.business_id = %d
          AND ((b.type='stock_in' AND b.total_cost>0)
           OR b.type='stock_out')
        ORDER BY b.created_at DESC", $business_id, $business_id));
    $nonce = wp_create_nonce('in_nonce');
    ob_start(); ?>
<script>var ajaxurl='<?php echo admin_url('admin-ajax.php'); ?>';</script>
<div class="bntm-form-section">
    <h3>Import Inventory Transactions to Finance</h3>
    <p>Stock-in transactions import as <strong>expenses</strong> (cost value). Stock-out transactions import as <strong>income</strong> using the product's <strong>selling price</strong>.</p>
    <div style="margin-bottom:12px;">
        <label style="cursor:pointer;margin-right:18px;"><input type="checkbox" id="sel-all-ni"> Select all (not imported)</label>
        <label style="cursor:pointer;"><input type="checkbox" id="sel-all-im"> Select all (imported)</label>
    </div>
    <div style="margin-bottom:14px;">
        <button id="bulk-import-btn" class="bntm-btn-primary" style="margin-right:8px;">Import Selected</button>
        <button id="bulk-revert-btn" class="bntm-btn-secondary">Revert Selected</button>
        <span id="sel-count" style="margin-left:12px;color:#6b7280;font-size:13px;"></span>
    </div>
    <div class="bntm-table-wrapper"><table class="bntm-table">
        <thead><tr><th width="36"></th><th>Date</th><th>Reference</th><th>Flow</th><th>Product</th><th>Units</th><th>Finance Value</th><th>Basis</th><th>Status</th></tr></thead>
        <tbody>
        <?php if (empty($batches)): ?><tr><td colspan="9" style="text-align:center;">No eligible transactions.</td></tr>
        <?php else: foreach ($batches as $b): ?>
        <tr>
            <td><input type="checkbox" class="bc <?php echo $b->is_imported ? 'im-bc' : 'ni-bc'; ?>"
                data-id="<?php echo $b->id; ?>" data-amount="<?php echo esc_attr($b->finance_amount); ?>"
                data-ref="<?php echo esc_attr($b->reference_number ?: $b->batch_code); ?>"
                data-product="<?php echo esc_attr($b->product_name); ?>"
                data-qty="<?php echo $b->quantity; ?>"
                data-cost="<?php echo esc_attr($b->cost_per_unit); ?>"
                data-selling="<?php echo esc_attr($b->selling_price); ?>"
                data-flow="<?php echo esc_attr($b->finance_type); ?>"
                data-batchtype="<?php echo esc_attr($b->type); ?>"
                data-imported="<?php echo $b->is_imported ? '1' : '0'; ?>"></td>
            <td><?php echo date('M d, Y', strtotime($b->manufacture_date ?: $b->created_at)); ?></td>
            <td><?php echo esc_html($b->reference_number ?: $b->batch_code); ?></td>
            <td><?php echo $b->finance_type === 'income'
                ? '<span style="background:#dcfce7;color:#166534;font-size:11px;padding:2px 8px;border-radius:99px;">Income</span>'
                : '<span style="background:#fee2e2;color:#991b1b;font-size:11px;padding:2px 8px;border-radius:99px;">Expense</span>'; ?></td>
            <td><?php echo esc_html($b->product_name); ?></td>
            <td><?php echo $b->quantity; ?></td>
            <td style="font-weight:600;color:<?php echo $b->finance_type==='income'?'#059669':'#374151'; ?>">&#8369;<?php echo number_format($b->finance_amount, 2); ?></td>
            <td style="font-size:11px;color:#6b7280;">
                <?php echo $b->type === 'stock_out'
                    ? '@ &#8369;'.number_format($b->selling_price,2).'/unit (selling price)'
                    : '@ &#8369;'.number_format($b->cost_per_unit,4).'/unit (cost)'; ?>
            </td>
            <td><?php echo $b->is_imported
                ? '<span style="background:#dcfce7;color:#166534;font-size:11px;padding:2px 8px;border-radius:99px;">Imported</span>'
                : '<span style="color:#9ca3af;font-size:11px;">Not imported</span>'; ?></td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
<script>
(function(){
    var nonce='<?php echo esc_js($nonce); ?>';
    function updCount(){ var n=document.querySelectorAll('.bc:checked').length; document.getElementById('sel-count').textContent=n?n+' selected':''; }
    document.getElementById('sel-all-ni').addEventListener('change',function(){ document.querySelectorAll('.ni-bc').forEach(function(c){ c.checked=this.checked; }.bind(this)); if(this.checked) document.getElementById('sel-all-im').checked=false; updCount(); });
    document.getElementById('sel-all-im').addEventListener('change',function(){ document.querySelectorAll('.im-bc').forEach(function(c){ c.checked=this.checked; }.bind(this)); if(this.checked) document.getElementById('sel-all-ni').checked=false; updCount(); });
    document.querySelectorAll('.bc').forEach(function(c){ c.addEventListener('change',updCount); });

    document.getElementById('bulk-import-btn').addEventListener('click',function(){
        var sel=Array.from(document.querySelectorAll('.bc:checked')).filter(function(c){ return c.dataset.imported==='0'; });
        if(!sel.length){ alert('Select at least one not-imported item.'); return; }
        var tot=sel.reduce(function(s,c){ return s+parseFloat(c.dataset.amount); },0);
        if(!confirm('Import '+sel.length+' item(s)? Total finance value: \u20B1'+tot.toFixed(2))) return;
        this.disabled=true; this.textContent='Importing...';
        var done=0;
        sel.forEach(function(c){
            var fd=new FormData();
            fd.append('action','in_import_batch_expense'); fd.append('batch_id',c.dataset.id);
            fd.append('amount',c.dataset.amount); fd.append('reference',c.dataset.ref);
            fd.append('product',c.dataset.product); fd.append('quantity',c.dataset.qty);
            fd.append('cost_per_unit',c.dataset.cost); fd.append('selling_price',c.dataset.selling);
            fd.append('finance_type',c.dataset.flow); fd.append('batch_type',c.dataset.batchtype);
            fd.append('nonce',nonce);
            fetch(ajaxurl,{method:'POST',body:fd}).then(function(r){ return r.json(); }).then(function(j){
                done++;
                if(done===sel.length){ alert('Imported '+sel.length+' item(s).'); location.reload(); }
            });
        });
    });

    document.getElementById('bulk-revert-btn').addEventListener('click',function(){
        var sel=Array.from(document.querySelectorAll('.bc:checked')).filter(function(c){ return c.dataset.imported==='1'; });
        if(!sel.length){ alert('Select at least one imported item.'); return; }
        if(!confirm('Revert '+sel.length+' item(s) from Finance?')) return;
        this.disabled=true; this.textContent='Reverting...';
        var done=0;
        sel.forEach(function(c){
            var fd=new FormData();
            fd.append('action','in_revert_batch_expense'); fd.append('batch_id',c.dataset.id); fd.append('nonce',nonce);
            fetch(ajaxurl,{method:'POST',body:fd}).then(function(r){ return r.json(); }).then(function(j){
                done++;
                if(done===sel.length){ alert('Reverted.'); location.reload(); }
            });
        });
    });
})();
</script>
<?php return ob_get_clean();
}

/* ============================================================
   AJAX: IMPORT BATCH — uses selling price for stock_out income
   ============================================================ */
function bntm_ajax_in_import_batch_expense() {
    check_ajax_referer('in_nonce', 'nonce');
    if (!in_current_user_can_manage_business()) wp_send_json_error(['message'=>'Unauthorized']);
    global $wpdb;
    $txn  = $wpdb->prefix.'fn_transactions';
    $bt   = $wpdb->prefix.'in_batches';
    $business_id = in_get_current_business_id();
    $bid  = intval($_POST['batch_id']);
    $amt  = floatval($_POST['amount']);
    $ref  = sanitize_text_field($_POST['reference']);
    $prod = sanitize_text_field($_POST['product']);
    $qty  = sanitize_text_field($_POST['quantity']);
    $cpu  = sanitize_text_field($_POST['cost_per_unit']);
    $selling_price = sanitize_text_field($_POST['selling_price'] ?? '0');
    $finance_type  = sanitize_text_field($_POST['finance_type'] ?? 'expense');
    $batch_type    = sanitize_text_field($_POST['batch_type'] ?? 'stock_in');

    if (!$wpdb->get_var($wpdb->prepare("SELECT id FROM {$bt} WHERE id = %d AND business_id = %d", $bid, $business_id)))
        wp_send_json_error(['message'=>'Batch not found.']);

    if ($wpdb->get_var($wpdb->prepare("SELECT id FROM {$txn} WHERE business_id = %d AND reference_type='inventory_batch' AND reference_id=%d", $business_id, $bid)))
        wp_send_json_error(['message'=>'Already imported.']);

    $is_income = $finance_type === 'income' && $batch_type === 'stock_out';

    /* For stock-out, use the selling price as the finance amount */
    if ($is_income) {
        $finance_amount = floatval($qty) * floatval($selling_price);
    } else {
        $finance_amount = $amt;
    }

    $result = in_sync_batch_to_finance($bid, $business_id);
    if ($result['success']) {
        wp_send_json_success(['message'=>'Imported successfully.']);
    }
    wp_send_json_error(['message'=>$result['message']]);
}

/* ============================================================
   AJAX: REVERT BATCH
   ============================================================ */
function bntm_ajax_in_revert_batch_expense() {
    check_ajax_referer('in_nonce', 'nonce');
    if (!in_current_user_can_manage_business()) wp_send_json_error(['message'=>'Unauthorized']);
    global $wpdb;
    $bt = $wpdb->prefix.'in_batches';
    $business_id = in_get_current_business_id();
    if (!$wpdb->get_var($wpdb->prepare("SELECT id FROM {$bt} WHERE id = %d AND business_id = %d", intval($_POST['batch_id']), $business_id)))
        wp_send_json_error(['message'=>'Batch not found.']);
    $result = in_revert_batch_finance_sync(intval($_POST['batch_id']), $business_id);
    if (!empty($result['deleted'])) {
        wp_send_json_success(['message'=>'Reverted.']);
    }
    wp_send_json_error(['message'=>'Revert failed.']);
}

/* ============================================================
   SETTINGS TAB
   ============================================================ */
function in_settings_tab($business_id) {
    $nonce = wp_create_nonce('in_nonce');
    ob_start(); ?>
<div class="bntm-form-section">
    <h3>Inventory Settings</h3>
    <p style="font-size:13px;color:#6b7280;margin-bottom:16px;">These settings apply only to the current business.</p>
    <form id="in-settings-form" class="bntm-form">
        <div class="bntm-form-group">
            <label>Default Reorder Level</label>
            <input type="number" name="default_reorder_level" value="<?php echo esc_attr(in_get_setting_for_business('in_default_reorder_level','10', $business_id)); ?>">
        </div>
        <div class="bntm-form-group">
            <label>Low Stock Alert Email</label>
            <input type="email" name="low_stock_email" value="<?php echo esc_attr(in_get_setting_for_business('in_low_stock_email','', $business_id)); ?>" placeholder="your@email.com">
        </div>
        <button type="submit" class="bntm-btn-primary">Save Settings</button>
        <div id="settings-message"></div>
    </form>
</div>
<script>
var ajaxurl='<?php echo admin_url('admin-ajax.php'); ?>';
document.getElementById('in-settings-form').addEventListener('submit',function(e){
    e.preventDefault();
    var fd=new FormData(this); fd.append('action','in_save_settings'); fd.append('nonce','<?php echo esc_js($nonce); ?>');
    var btn=this.querySelector('button[type="submit"]'); btn.disabled=true; btn.textContent='Saving...';
    fetch(ajaxurl,{method:'POST',body:fd}).then(function(r){ return r.json(); }).then(function(j){
        document.getElementById('settings-message').innerHTML='<div class="bntm-notice bntm-notice-'+(j.success?'success':'error')+'">'+j.data.message+'</div>';
        btn.disabled=false; btn.textContent='Save Settings';
    });
});
</script>
<?php return ob_get_clean();
}

function bntm_ajax_in_save_settings() {
    check_ajax_referer('in_nonce', 'nonce');
    if (!in_current_user_can_manage_business()) wp_send_json_error(['message'=>'Unauthorized']);
    $business_id = in_get_current_business_id();
    in_update_setting_for_business('in_default_reorder_level', intval($_POST['default_reorder_level']), $business_id);
    in_update_setting_for_business('in_low_stock_email', sanitize_email($_POST['low_stock_email']), $business_id);
    wp_send_json_success(['message'=>'Settings saved!']);
}