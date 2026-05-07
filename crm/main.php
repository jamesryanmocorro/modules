<?php
/**
 * Module Name: CRM
 * Module Slug: crm
 * Description: Customer Relationship Management with sales pipeline, quotations, and automated billing
 * Version: 1.0.2
 * Author: Your Name
 * Icon: 👥
 */

// Prevent direct access
if (!defined('ABSPATH'))
    exit;

// Module constants
define('BNTM_CRM_PATH', dirname(__FILE__) . '/');
define('BNTM_CRM_URL', plugin_dir_url(__FILE__));

/* ---------- MODULE CONFIGURATION ---------- */

/**
 * Get module pages
 */
function bntm_crm_get_pages()
{
    return [
        'CRM Dashboard' => '[crm_dashboard]'
    ];
}

/**
 * Get module database tables
 */
function bntm_crm_get_tables()
{
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $prefix = $wpdb->prefix;

    return [
        'crm_customers' => "CREATE TABLE {$prefix}crm_customers (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            type ENUM('customer', 'lead') DEFAULT 'lead',
            name VARCHAR(255) NOT NULL,
            contact_number VARCHAR(50),
            email VARCHAR(255),
            company VARCHAR(255),
            address TEXT,
            birthday DATE,
            anniversary DATE,
            source VARCHAR(100),
            tags TEXT,
            status VARCHAR(50) DEFAULT 'active',
            notes TEXT,
            assigned_to BIGINT UNSIGNED,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_type (type),
            INDEX idx_status (status),
            INDEX idx_assigned (assigned_to),
            INDEX idx_email (email),
            INDEX idx_contact (contact_number)
        ) {$charset};",

        'crm_deals' => "CREATE TABLE {$prefix}crm_deals (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            customer_id BIGINT UNSIGNED NOT NULL,
            deal_name VARCHAR(255) NOT NULL,
            deal_value DECIMAL(12,2) DEFAULT 0,
            service_type VARCHAR(50) DEFAULT 'BNTM HUB',
            estimated_value DECIMAL(12,2) DEFAULT 0,
            actual_value DECIMAL(12,2) DEFAULT 0,
            stage VARCHAR(50) DEFAULT 'lead',
            probability INT DEFAULT 0,
            expected_close_date DATE,
            actual_close_date DATE,
            exploratory_meeting_date DATE,
            contract_signing_date DATE,
            project_turnover_date DATE,
            next_step VARCHAR(255),
            next_step_due_date DATE,
            invoice_status VARCHAR(50) DEFAULT 'unpaid',
            initial_payment DECIMAL(12,2) DEFAULT 0,
            remaining_balance DECIMAL(12,2) DEFAULT 0,
            is_installment TINYINT(1) DEFAULT 0,
            installment_notes TEXT,
            handoff_scope TEXT,
            handoff_notes TEXT,
            document_links TEXT,
            last_activity_at DATETIME,
            products TEXT,
            notes TEXT,
            assigned_to BIGINT UNSIGNED,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_customer (customer_id),
            INDEX idx_stage (stage),
            INDEX idx_assigned (assigned_to),
            INDEX idx_close_date (expected_close_date),
            INDEX idx_next_step_due (next_step_due_date),
            INDEX idx_invoice_status (invoice_status),
            FOREIGN KEY (customer_id) REFERENCES {$prefix}crm_customers(id) ON DELETE CASCADE
        ) {$charset};",

        'crm_pipeline_stages' => "CREATE TABLE {$prefix}crm_pipeline_stages (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            stage_name VARCHAR(100) NOT NULL,
            stage_order INT DEFAULT 0,
            color VARCHAR(7) DEFAULT '#3b82f6',
            is_active BOOLEAN DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_order (stage_order)
        ) {$charset};",



        'crm_activities' => "CREATE TABLE {$prefix}crm_activities (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            customer_id BIGINT UNSIGNED NOT NULL,
            deal_id BIGINT UNSIGNED,
            activity_type VARCHAR(50) NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            due_date DATETIME,
            completed BOOLEAN DEFAULT 0,
            completed_at DATETIME,
            assigned_to BIGINT UNSIGNED,
            created_by BIGINT UNSIGNED,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_customer (customer_id),
            INDEX idx_deal (deal_id),
            INDEX idx_due (due_date),
            INDEX idx_assigned (assigned_to),
            FOREIGN KEY (customer_id) REFERENCES {$prefix}crm_customers(id) ON DELETE CASCADE
        ) {$charset};"
    ];
}

/**
 * Get module shortcodes
 */
function bntm_crm_get_shortcodes()
{
    return [
        'crm_dashboard' => 'bntm_shortcode_crm_dashboard',
        'crm_quotation_view' => 'bntm_shortcode_crm_quotation_view'
    ];
}

/**
 * Create module tables
 */
function bntm_crm_create_tables()
{
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    $tables = bntm_crm_get_tables();

    foreach ($tables as $sql) {
        dbDelta($sql);
    }

    // Insert default pipeline stages
    crm_insert_default_pipeline_stages();

    return count($tables);
}

/**
 * Insert default pipeline stages
 */
function crm_insert_default_pipeline_stages()
{
    global $wpdb;
    $table = $wpdb->prefix . 'crm_pipeline_stages';
    $business_id = get_current_user_id();

    // Check if stages already exist - REMOVED business_id filter
    $exists = $wpdb->get_var("SELECT COUNT(*) FROM $table");

    if ($exists > 0)
        return;

    $default_stages = [
        ['stage_name' => 'Lead', 'stage_order' => 1, 'color' => '#6b7280'],
        ['stage_name' => 'Contacted', 'stage_order' => 2, 'color' => '#3b82f6'],
        ['stage_name' => 'Qualified', 'stage_order' => 3, 'color' => '#8b5cf6'],
        ['stage_name' => 'Quotation Sent', 'stage_order' => 4, 'color' => '#f59e0b'],
        ['stage_name' => 'Negotiation', 'stage_order' => 5, 'color' => '#ef4444'],
        ['stage_name' => 'Won', 'stage_order' => 6, 'color' => '#10b981'],
        ['stage_name' => 'Lost', 'stage_order' => 7, 'color' => '#dc2626']
    ];

    foreach ($default_stages as $stage) {
        $wpdb->insert($table, array_merge($stage, ['business_id' => $business_id]));
    }
}

// AJAX handlers
add_action('wp_ajax_crm_create_customer', 'bntm_ajax_crm_create_customer');
add_action('wp_ajax_crm_update_customer', 'bntm_ajax_crm_update_customer');
add_action('wp_ajax_crm_delete_customer', 'bntm_ajax_crm_delete_customer');
add_action('wp_ajax_crm_convert_to_customer', 'bntm_ajax_crm_convert_to_customer');
add_action('wp_ajax_crm_create_deal', 'bntm_ajax_crm_create_deal');
add_action('wp_ajax_crm_update_deal_stage', 'bntm_ajax_crm_update_deal_stage');
add_action('wp_ajax_crm_save_pipeline_stages', 'bntm_ajax_crm_save_pipeline_stages');
add_action('wp_ajax_crm_get_customer', 'bntm_ajax_crm_get_customer');
add_action('wp_ajax_crm_get_deal', 'bntm_ajax_crm_get_deal');
add_action('wp_ajax_crm_add_activity', 'bntm_ajax_crm_add_activity');
add_action('wp_ajax_crm_get_activities', 'bntm_ajax_crm_get_activities');

// Cron job for auto-generating invoices
add_action('init', 'crm_schedule_invoice_generation');
add_action('crm_generate_scheduled_invoices_hook', 'crm_process_scheduled_invoices');

function crm_schedule_invoice_generation()
{
    if (!wp_next_scheduled('crm_generate_scheduled_invoices_hook')) {
        wp_schedule_event(time(), 'daily', 'crm_generate_scheduled_invoices_hook');
    }
}

/* ---------- DASHBOARD ---------- */
function bntm_shortcode_crm_dashboard()
{
    if (!is_user_logged_in()) {
        return '<div class="bntm-notice">Please log in to access the CRM dashboard.</div>';
    }

    $current_user = wp_get_current_user();
    $business_id = $current_user->ID;
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'pipeline';

    ob_start();
    ?>
    <style>
        :root {
            --crm-primary: #3b82f6;
            --crm-primary-dark: #2563eb;
            --crm-primary-light: #eff6ff;
            --crm-success: #10b981;
            --crm-success-light: #ecfdf5;
            --crm-warning: #f59e0b;
            --crm-warning-light: #fffbeb;
            --crm-danger: #ef4444;
            --crm-danger-light: #fef2f2;
            --crm-gray-50: #f9fafb;
            --crm-gray-100: #f3f4f6;
            --crm-gray-200: #e5e7eb;
            --crm-gray-300: #d1d5db;
            --crm-gray-400: #9ca3af;
            --crm-gray-500: #6b7280;
            --crm-gray-600: #4b5563;
            --crm-gray-700: #374151;
            --crm-gray-800: #1f2937;
            --crm-gray-900: #111827;
            --crm-shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --crm-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --crm-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --crm-radius: 12px;
            --crm-radius-sm: 8px;
        }

        .bntm-ecommerce-container {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: var(--crm-gray-800);
            background: white;
            border-radius: var(--crm-radius);
            overflow: hidden;
        }

        /* Tabs */
        .bntm-tabs {
            display: flex;
            gap: 8px;
            padding: 16px 20px;
            background: var(--crm-gray-50);
            border-bottom: 1px solid var(--crm-gray-200);
        }

        .bntm-tab {
            padding: 10px 20px;
            border-radius: var(--crm-radius-sm);
            font-weight: 600;
            font-size: 14px;
            color: var(--crm-gray-500);
            text-decoration: none !important;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }

        .bntm-tab:hover {
            background: var(--crm-gray-100);
            color: var(--crm-gray-700);
        }

        .bntm-tab.active {
            background: white;
            color: var(--crm-primary);
            border-color: var(--crm-gray-200);
            box-shadow: var(--crm-shadow-sm);
        }

        /* Pipeline */
        .crm-pipeline-container {
            overflow-x: auto;
            padding: 24px;
            background: #f8fafc;
        }

        .crm-pipeline {
            display: flex;
            gap: 20px;
            min-height: 600px;
            padding-bottom: 20px;
        }

        .pipeline-column {
            flex: 0 0 320px;
            background: var(--crm-gray-100);
            border-radius: var(--crm-radius);
            display: flex;
            flex-direction: column;
            max-height: calc(100vh - 200px);
            border: 1px solid var(--crm-gray-200);
            transition: background 0.2s ease;
        }

        .pipeline-header {
            padding: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--crm-gray-200);
            background: rgba(255, 255, 255, 0.5);
            border-radius: var(--crm-radius) var(--crm-radius) 0 0;
        }

        .pipeline-title {
            font-weight: 700;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pipeline-count {
            background: white;
            color: var(--crm-gray-600);
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            box-shadow: var(--crm-shadow-sm);
            border: 1px solid var(--crm-gray-200);
        }

        .pipeline-deals {
            padding: 12px;
            overflow-y: auto;
            flex-grow: 1;
            scrollbar-width: thin;
        }

        /* Deal Card */
        .deal-card {
            background: white;
            padding: 16px;
            border-radius: var(--crm-radius-sm);
            margin-bottom: 12px;
            border: 1px solid var(--crm-gray-200);
            cursor: grab;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--crm-shadow-sm);
            position: relative;
            overflow: hidden;
        }

        .deal-card:hover {
            box-shadow: var(--crm-shadow);
            transform: translateY(-2px);
            border-color: var(--crm-primary);
        }

        .deal-card:active {
            cursor: grabbing;
        }

        .deal-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--card-accent, var(--crm-primary));
        }

        .deal-card-title {
            font-weight: 700;
            font-size: 15px;
            color: var(--crm-gray-900);
            margin-bottom: 8px;
            line-height: 1.4;
        }

        .deal-card-info {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .deal-card-customer {
            font-size: 13px;
            color: var(--crm-gray-600);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .deal-card-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--crm-gray-100);
        }

        .deal-card-value {
            font-weight: 800;
            color: var(--crm-success);
            font-size: 15px;
        }

        .deal-card-date {
            font-size: 11px;
            color: var(--crm-gray-400);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* Stats Cards */
        .crm-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: var(--crm-radius);
            border: 1px solid var(--crm-gray-200);
            box-shadow: var(--crm-shadow-sm);
            transition: transform 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--crm-shadow);
        }

        .stat-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--crm-gray-500);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 20px;
            font-weight: 800;
            color: var(--crm-gray-900);
        }

        .stat-subvalue {
            font-size: 12px;
            color: var(--crm-gray-400);
            margin-top: 4px;
        }

        /* Modals */
        .bntm-modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            transition: opacity 0.3s ease;
        }

        .bntm-modal-content {
            background-color: #fff;
            margin: 40px auto;
            border-radius: 16px;
            max-width: 800px;
            width: 90%;
            box-shadow: var(--crm-shadow-lg);
            max-height: calc(100vh - 80px);
            display: flex;
            flex-direction: column;
            animation: modalSlideUp 0.3s ease-out;
        }

        @keyframes modalSlideUp {
            from {
                transform: translateY(20px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .bntm-modal-header {
            padding: 24px;
            border-bottom: 1px solid var(--crm-gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            border-radius: 16px 16px 0 0;
        }

        .bntm-modal-header h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
            color: var(--crm-gray-900);
        }

        .bntm-modal-close {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--crm-gray-100);
            color: var(--crm-gray-500);
            cursor: pointer;
            transition: all 0.2s;
        }

        .bntm-modal-close:hover {
            background: var(--crm-danger-light);
            color: var(--crm-danger);
        }

        .bntm-modal .bntm-form {
            padding: 24px;
            overflow-y: auto;
        }

        .bntm-form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .bntm-form-group.full-width {
            grid-column: span 2;
        }

        .bntm-modal-footer {
            padding: 20px 24px;
            border-top: 1px solid var(--crm-gray-200);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            background: var(--crm-gray-50);
            border-radius: 0 0 16px 16px;
        }

        /* Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .status-lead {
            background: var(--crm-gray-100);
            color: var(--crm-gray-600);
        }

        .status-customer {
            background: var(--crm-success-light);
            color: var(--crm-success);
        }

        .status-active {
            background: var(--crm-success-light);
            color: var(--crm-success);
        }

        .status-won {
            background: var(--crm-success-light);
            color: var(--crm-success);
        }

        .status-lost {
            background: var(--crm-danger-light);
            color: var(--crm-danger);
        }

        /* Tables */
        .bntm-table-wrapper {
            border-radius: var(--crm-radius-sm);
            border: 1px solid var(--crm-gray-200);
            overflow: hidden;
            margin-top: 16px;
        }

        .bntm-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .bntm-table th {
            background: var(--crm-gray-50);
            padding: 12px 16px;
            text-align: left;
            font-weight: 700;
            color: var(--crm-gray-600);
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--crm-gray-200);
        }

        .bntm-table td {
            padding: 16px;
            border-bottom: 1px solid var(--crm-gray-100);
            color: var(--crm-gray-700);
        }

        .bntm-table tr:last-child td {
            border-bottom: none;
        }

        .bntm-table tr:hover td {
            background: var(--crm-gray-50);
        }

        /* Buttons */
        .bntm-btn-primary {
            background: var(--crm-primary);
            color: white;
            padding: 10px 20px;
            border-radius: var(--crm-radius-sm);
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .bntm-btn-primary:hover {
            background: var(--crm-primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .bntm-btn-secondary {
            background: white;
            color: var(--crm-gray-700);
            padding: 10px 20px;
            border-radius: var(--crm-radius-sm);
            font-weight: 600;
            border: 1px solid var(--crm-gray-300);
            cursor: pointer;
            transition: all 0.2s;
        }

        .bntm-btn-secondary:hover {
            background: var(--crm-gray-50);
            border-color: var(--crm-gray-400);
        }
    </style>

    <div class="bntm-ecommerce-container">
        <div class="bntm-tabs">
            <a href="?tab=pipeline" class="bntm-tab <?php echo $active_tab === 'pipeline' ? 'active' : ''; ?>">Sales
                Pipeline</a>
            <a href="?tab=customers"
                class="bntm-tab <?php echo $active_tab === 'customers' ? 'active' : ''; ?>">Customers</a>
            <a href="?tab=settings" class="bntm-tab <?php echo $active_tab === 'settings' ? 'active' : ''; ?>">Settings</a>
        </div>

        <div class="bntm-tab-content">
            <?php if ($active_tab === 'pipeline'): ?>
                <?php echo crm_pipeline_tab($business_id); ?>
            <?php elseif ($active_tab === 'customers'): ?>
                <?php echo crm_customers_tab($business_id); ?>
            <?php elseif ($active_tab === 'settings'): ?>
                <?php echo crm_settings_tab($business_id); ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
    $content = ob_get_clean();
    return bntm_universal_container('CRM', $content);
}

function crm_pipeline_tab($business_id)
{
    global $wpdb;
    $deals_table = $wpdb->prefix . 'crm_deals';
    $customers_table = $wpdb->prefix . 'crm_customers';
    $stages_table = $wpdb->prefix . 'crm_pipeline_stages';

    // Get pipeline stages - REMOVED business_id filter
    $stages = $wpdb->get_results(
        "SELECT * FROM $stages_table WHERE is_active = 1 ORDER BY stage_order ASC"
    );

    // Get deals with customer info - REMOVED business_id filter
    $deals = $wpdb->get_results(
        "SELECT d.*, c.name as customer_name, c.email as customer_email 
         FROM $deals_table d
         LEFT JOIN $customers_table c ON d.customer_id = c.id
         ORDER BY d.created_at DESC"
    );

    $now = current_time('timestamp');
    $closed_stages = ['Won', 'Lost'];
    $total_estimated_value = 0;
    $total_actual_value = 0;
    $overdue_followups = 0;
    $unpaid_balance_total = 0;
    $closed_deals = 0;
    $ongoing_deals = 0;
    $stalled_deals = 0;
    $lead_to_turnover_days = [];
    $meeting_to_contract_days = [];
    $project_timeline_days = [];

    foreach ($deals as $deal) {
        $estimated_value = (float) ($deal->estimated_value ?: $deal->deal_value);
        $actual_value = (float) $deal->actual_value;
        $remaining_balance = (float) $deal->remaining_balance;

        $total_estimated_value += $estimated_value;
        $total_actual_value += $actual_value;
        $unpaid_balance_total += $remaining_balance;

        $is_closed = in_array($deal->stage, $closed_stages, true);
        if ($is_closed) {
            $closed_deals++;
        } else {
            $ongoing_deals++;
        }

        if (!empty($deal->next_step_due_date) && strtotime($deal->next_step_due_date) < $now && !$is_closed) {
            $overdue_followups++;
        }

        if (!$is_closed && !empty($deal->updated_at) && (strtotime($deal->updated_at) <= strtotime('-14 days', $now))) {
            $stalled_deals++;
        }

        if (!empty($deal->project_turnover_date) && !empty($deal->created_at)) {
            $lead_to_turnover_days[] = floor((strtotime($deal->project_turnover_date) - strtotime($deal->created_at)) / DAY_IN_SECONDS);
        }

        if (!empty($deal->exploratory_meeting_date) && !empty($deal->contract_signing_date)) {
            $meeting_to_contract_days[] = floor((strtotime($deal->contract_signing_date) - strtotime($deal->exploratory_meeting_date)) / DAY_IN_SECONDS);
        }

        if (!empty($deal->exploratory_meeting_date) && !empty($deal->project_turnover_date)) {
            $project_timeline_days[] = floor((strtotime($deal->project_turnover_date) - strtotime($deal->exploratory_meeting_date)) / DAY_IN_SECONDS);
        }
    }

    $avg_lead_to_turnover = !empty($lead_to_turnover_days) ? round(array_sum($lead_to_turnover_days) / count($lead_to_turnover_days), 1) : 0;
    $avg_meeting_to_contract = !empty($meeting_to_contract_days) ? round(array_sum($meeting_to_contract_days) / count($meeting_to_contract_days), 1) : 0;
    $avg_project_timeline = !empty($project_timeline_days) ? round(array_sum($project_timeline_days) / count($project_timeline_days), 1) : 0;
    $nonce = wp_create_nonce('crm_nonce');

    ob_start();
    ?>
    <script>
        var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        var crmNonce = '<?php echo $nonce; ?>';
    </script>

    <div class="crm-pipeline-container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <h3 style="margin: 0; font-size: 24px; font-weight: 800;">Sales Pipeline</h3>
            <button class="bntm-btn-primary" id="create-deal-btn">
                <span>+</span> Create New Deal
            </button>
        </div>

        <div class="crm-stats-grid">
            <div class="stat-card">
                <div class="stat-label">Deal Value</div>
                <div class="stat-value" style="color: var(--crm-primary);">
                    <?php echo crm_format_price($total_estimated_value); ?></div>
                <div class="stat-subvalue">Estimated Total</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Actual Revenue</div>
                <div class="stat-value" style="color: var(--crm-success);">
                    <?php echo crm_format_price($total_actual_value); ?></div>
                <div class="stat-subvalue">Closed Won</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Unpaid Balance</div>
                <div class="stat-value" style="color: var(--crm-danger);">
                    <?php echo crm_format_price($unpaid_balance_total); ?></div>
                <div class="stat-subvalue">Pending Collection</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Deal Velocity</div>
                <div class="stat-value"><?php echo $avg_meeting_to_contract; ?> Days</div>
                <div class="stat-subvalue">Meeting to Contract</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Active Deals</div>
                <div class="stat-value"><?php echo intval($ongoing_deals); ?></div>
                <div class="stat-subvalue"><?php echo intval($stalled_deals); ?> Stalled (>14d)</div>
            </div>
        </div>

        <div class="crm-pipeline">
            <?php foreach ($stages as $stage):
                $stage_deals = array_filter($deals, function ($deal) use ($stage) {
                    return $deal->stage === $stage->stage_name;
                });
                $deal_count = count($stage_deals);
                $total_value = array_sum(array_column($stage_deals, 'deal_value'));
                ?>
                <div class="pipeline-column" data-stage="<?php echo esc_attr($stage->stage_name); ?>">
                    <div class="pipeline-header">
                        <div class="pipeline-title" style="color: <?php echo esc_attr($stage->color); ?>">
                            <span
                                style="width: 8px; height: 8px; border-radius: 50%; background: <?php echo esc_attr($stage->color); ?>;"></span>
                            <?php echo esc_html($stage->stage_name); ?>
                        </div>
                        <span class="pipeline-count"><?php echo $deal_count; ?></span>
                    </div>

                    <div class="pipeline-deals">
                        <?php if (empty($stage_deals)): ?>
                            <div style="text-align: center; color: var(--crm-gray-400); font-size: 13px; padding: 40px 20px;">
                                <div style="font-size: 24px; margin-bottom: 8px;">📂</div>
                                No deals in this stage
                            </div>
                        <?php else: ?>
                            <?php foreach ($stage_deals as $deal): ?>
                                <div class="deal-card" data-id="<?php echo $deal->id; ?>"
                                    style="--card-accent: <?php echo esc_attr($stage->color); ?>">
                                    <div class="deal-card-title"><?php echo esc_html($deal->deal_name); ?></div>
                                    <div class="deal-card-info">
                                        <div class="deal-card-customer">
                                            <span>👤</span> <?php echo esc_html($deal->customer_name); ?>
                                        </div>
                                        <?php if ($deal->service_type): ?>
                                            <div class="deal-card-customer">
                                                <span>🛠️</span> <?php echo esc_html($deal->service_type); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="deal-card-meta">
                                        <div class="deal-card-value"><?php echo crm_format_price($deal->deal_value); ?></div>
                                        <?php if ($deal->expected_close_date): ?>
                                            <div class="deal-card-date">
                                                <span>📅</span> <?php echo date('M d', strtotime($deal->expected_close_date)); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Create/Edit Deal Modal -->
    <div id="deal-modal" class="bntm-modal">
        <div class="bntm-modal-content">
            <div class="bntm-modal-header">
                <h2 id="deal-modal-title">Create Deal</h2>
                <div class="bntm-modal-close">&times;</div>
            </div>
            <form id="deal-form" class="bntm-form">
                <input type="hidden" name="deal_id" id="deal_id">

                <div class="bntm-form-grid">
                    <div class="bntm-form-group">
                        <label>Customer / Lead *</label>
                        <select name="customer_id" id="deal_customer_id" required>
                            <option value="">Select Customer/Lead</option>
                            <?php
                            $all_customers = $wpdb->get_results("SELECT * FROM $customers_table ORDER BY name ASC");
                            foreach ($all_customers as $customer):
                                ?>
                                <option value="<?php echo $customer->id; ?>">
                                    <?php echo esc_html($customer->name); ?> (<?php echo ucfirst($customer->type); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="bntm-form-group">
                        <label>Deal Name *</label>
                        <input type="text" name="deal_name" id="deal_name" required placeholder="e.g. Website Redesign">
                    </div>

                    <div class="bntm-form-group">
                        <label>Service Type</label>
                        <select name="service_type" id="service_type">
                            <option value="BNTM HUB">BNTM HUB</option>
                            <option value="Enterprise">Enterprise</option>
                            <option value="Spree">Spree</option>
                            <option value="Custom Service">Custom Service</option>
                        </select>
                    </div>

                    <div class="bntm-form-group">
                        <label>Deal Value</label>
                        <input type="number" name="deal_value" id="deal_value" step="0.01" min="0" placeholder="0.00">
                    </div>

                    <div class="bntm-form-group">
                        <label>Pipeline Stage</label>
                        <select name="stage" id="deal_stage">
                            <?php foreach ($stages as $stage): ?>
                                <option value="<?php echo esc_attr($stage->stage_name); ?>">
                                    <?php echo esc_html($stage->stage_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="bntm-form-group">
                        <label>Probability (%)</label>
                        <input type="number" name="probability" id="deal_probability" min="0" max="100" value="50">
                    </div>

                    <div class="bntm-form-group">
                        <label>Expected Close Date</label>
                        <input type="date" name="expected_close_date" id="expected_close_date">
                    </div>

                    <div class="bntm-form-group">
                        <label>Next Step Due Date</label>
                        <input type="date" name="next_step_due_date" id="next_step_due_date">
                    </div>

                    <div class="bntm-form-group full-width">
                        <label>Next Step Description</label>
                        <input type="text" name="next_step" id="next_step" placeholder="What needs to be done next?">
                    </div>

                    <div class="bntm-form-group full-width">
                        <label>Internal Notes</label>
                        <textarea name="notes" id="deal_notes" rows="3"
                            placeholder="Additional details about this deal..."></textarea>
                    </div>

                    <div class="bntm-form-group full-width"
                        style="margin-top: 20px; border-top: 1px solid var(--crm-gray-100); padding-top: 20px;">
                        <h4 style="margin: 0 0 16px 0; font-size: 16px; font-weight: 800;">Payment & Handoff</h4>
                    </div>

                    <div class="bntm-form-group">
                        <label>Invoice Status</label>
                        <select name="invoice_status" id="invoice_status">
                            <option value="unpaid">Unpaid</option>
                            <option value="partial">Partial</option>
                            <option value="paid">Paid</option>
                        </select>
                    </div>

                    <div class="bntm-form-group">
                        <label>Initial Payment</label>
                        <input type="number" name="initial_payment" id="initial_payment" step="0.01" min="0" value="0">
                    </div>

                    <div class="bntm-form-group full-width">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: 700;">
                            <input type="checkbox" name="is_installment" id="is_installment" value="1">
                            Enable Installment-Based Payment
                        </label>
                    </div>

                    <div class="bntm-form-group full-width">
                        <label>Document Links (one per line)</label>
                        <textarea name="document_links" id="document_links" rows="2"
                            placeholder="Links to proposals, contracts, etc."></textarea>
                    </div>
                </div>

                <div class="bntm-modal-footer">
                    <button type="button" class="bntm-btn-secondary modal-cancel">Discard Changes</button>
                    <button type="submit" class="bntm-btn-primary">Save Deal Details</button>
                </div>
                <div id="deal-form-message" style="margin-top: 15px;"></div>
            </form>
        </div>
    </div>

    <!-- Deal Details Modal -->
    <div id="deal-details-modal" class="bntm-modal">
        <div class="bntm-modal-content">
            <div class="bntm-modal-header">
                <h2>Deal Details</h2>
                <span class="bntm-modal-close">&times;</span>
            </div>
            <div id="deal-details-content" style="padding: 20px;">
                <!-- Will be populated via JavaScript -->
            </div>
        </div>
    </div>

    <script>
        (function () {
            const dealModal = document.getElementById('deal-modal');
            const detailsModal = document.getElementById('deal-details-modal');
            const dealForm = document.getElementById('deal-form');

            // Open create deal modal
            document.getElementById('create-deal-btn').addEventListener('click', function () {
                document.getElementById('deal-modal-title').textContent = 'Create Deal';
                dealForm.reset();
                document.getElementById('deal_id').value = '';
                dealModal.style.display = 'block';
            });

            // Close modals
            document.querySelectorAll('.bntm-modal-close, .modal-cancel').forEach(el => {
                el.addEventListener('click', function () {
                    dealModal.style.display = 'none';
                    detailsModal.style.display = 'none';
                });
            });

            window.addEventListener('click', function (e) {
                if (e.target === dealModal) dealModal.style.display = 'none';
                if (e.target === detailsModal) detailsModal.style.display = 'none';
            });

            // Submit deal form
            dealForm.addEventListener('submit', function (e) {
                e.preventDefault();

                const formData = new FormData(this);
                const action = formData.get('deal_id') ? 'crm_update_deal' : 'crm_create_deal';
                formData.append('action', action);
                formData.append('nonce', crmNonce);

                const btn = this.querySelector('button[type="submit"]');
                btn.disabled = true;
                btn.textContent = 'Saving...';

                fetch(ajaxurl, { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(json => {
                        const msg = document.getElementById('deal-form-message');
                        if (json.success) {
                            msg.innerHTML = '<div class="bntm-notice bntm-notice-success">' + json.data.message + '</div>';
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            msg.innerHTML = '<div class="bntm-notice bntm-notice-error">' + json.data.message + '</div>';
                            btn.disabled = false;
                            btn.textContent = 'Save Deal';
                        }
                    });
            });

            // Deal card click - show details
            document.querySelectorAll('.deal-card').forEach(card => {
                card.addEventListener('click', function () {
                    const dealId = this.dataset.id;
                    showDealDetails(dealId);
                });
            });

            function showDealDetails(dealId) {
                const formData = new FormData();
                formData.append('action', 'crm_get_deal');
                formData.append('deal_id', dealId);
                formData.append('nonce', crmNonce);

                fetch(ajaxurl, { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(json => {
                        if (json.success) {
                            const deal = json.data;
                            const content = document.getElementById('deal-details-content');
                            content.innerHTML = `
                    <div style="padding: 24px;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px;">
                            <div>
                                <h3 style="margin: 0 0 4px 0; font-size: 24px; font-weight: 800; color: var(--crm-gray-900);">${deal.deal_name}</h3>
                                <div style="display: flex; align-items: center; gap: 8px; font-size: 14px; color: var(--crm-gray-500);">
                                    <span>👤</span> ${deal.customer_name}
                                    <span style="color: var(--crm-gray-300);">|</span>
                                    <span>🛠️</span> ${deal.service_type || 'General Service'}
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 28px; font-weight: 800; color: var(--crm-success);">${deal.deal_value_formatted}</div>
                                <div class="status-badge" style="margin-top: 4px; background: var(--crm-primary-light); color: var(--crm-primary);">${deal.stage}</div>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 32px;">
                            <div style="background: var(--crm-gray-50); padding: 16px; border-radius: 12px; border: 1px solid var(--crm-gray-100);">
                                <div style="font-size: 11px; font-weight: 700; color: var(--crm-gray-400); text-transform: uppercase; margin-bottom: 8px;">Probability</div>
                                <div style="font-size: 18px; font-weight: 700; color: var(--crm-gray-700);">${deal.probability}%</div>
                                <div style="width: 100%; height: 6px; background: var(--crm-gray-200); border-radius: 3px; margin-top: 8px;">
                                    <div style="width: ${deal.probability}%; height: 100%; background: var(--crm-primary); border-radius: 3px;"></div>
                                </div>
                            </div>
                            <div style="background: var(--crm-gray-50); padding: 16px; border-radius: 12px; border: 1px solid var(--crm-gray-100);">
                                <div style="font-size: 11px; font-weight: 700; color: var(--crm-gray-400); text-transform: uppercase; margin-bottom: 8px;">Invoice Status</div>
                                <div style="font-size: 18px; font-weight: 700; color: var(--crm-gray-700);">${deal.invoice_status_label}</div>
                                <div style="font-size: 12px; color: var(--crm-gray-500); margin-top: 4px;">Bal: ${deal.remaining_balance_formatted}</div>
                            </div>
                            <div style="background: var(--crm-gray-50); padding: 16px; border-radius: 12px; border: 1px solid var(--crm-gray-100);">
                                <div style="font-size: 11px; font-weight: 700; color: var(--crm-gray-400); text-transform: uppercase; margin-bottom: 8px;">Expected Close</div>
                                <div style="font-size: 18px; font-weight: 700; color: var(--crm-gray-700);">${deal.expected_close_date || 'Not set'}</div>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 32px;">
                            <div>
                                <h4 style="margin: 0 0 16px 0; font-size: 16px; font-weight: 800;">Deal Information</h4>
                                <div style="display: flex; flex-direction: column; gap: 12px;">
                                    ${deal.next_step ? `
                                    <div style="background: var(--crm-warning-light); padding: 12px; border-radius: 8px; border-left: 4px solid var(--crm-warning);">
                                        <div style="font-size: 12px; font-weight: 700; color: var(--crm-warning); margin-bottom: 4px;">NEXT STEP</div>
                                        <div style="font-weight: 600; color: var(--crm-gray-800);">${deal.next_step}</div>
                                        ${deal.next_step_due_date ? `<div style="font-size: 11px; margin-top: 4px;">Due: ${deal.next_step_due_date} ${deal.is_followup_overdue ? '<span style="color:var(--crm-danger);">(Overdue)</span>' : ''}</div>` : ''}
                                    </div>` : ''}
                                    
                                    <div style="display: grid; grid-template-columns: 120px 1fr; font-size: 14px;">
                                        <span style="color: var(--crm-gray-400);">Estimated:</span>
                                        <span style="font-weight: 600;">${deal.estimated_value_formatted}</span>
                                    </div>
                                    <div style="display: grid; grid-template-columns: 120px 1fr; font-size: 14px;">
                                        <span style="color: var(--crm-gray-400);">Actual:</span>
                                        <span style="font-weight: 600;">${deal.actual_value_formatted}</span>
                                    </div>
                                    <div style="display: grid; grid-template-columns: 120px 1fr; font-size: 14px;">
                                        <span style="color: var(--crm-gray-400);">Contract:</span>
                                        <span style="font-weight: 600;">${deal.contract_signing_date || 'N/A'}</span>
                                    </div>
                                    <div style="display: grid; grid-template-columns: 120px 1fr; font-size: 14px;">
                                        <span style="color: var(--crm-gray-400);">Turnover:</span>
                                        <span style="font-weight: 600;">${deal.project_turnover_date || 'N/A'}</span>
                                    </div>

                                    ${deal.notes ? `
                                    <div style="margin-top: 16px;">
                                        <div style="font-size: 12px; font-weight: 700; color: var(--crm-gray-400); margin-bottom: 4px;">INTERNAL NOTES</div>
                                        <div style="font-size: 14px; line-height: 1.6; color: var(--crm-gray-600);">${deal.notes}</div>
                                    </div>` : ''}

                                    ${deal.document_links_html ? `
                                    <div style="margin-top: 16px;">
                                        <div style="font-size: 12px; font-weight: 700; color: var(--crm-gray-400); margin-bottom: 4px;">DOCUMENTS</div>
                                        <div style="font-size: 13px;">${deal.document_links_html}</div>
                                    </div>` : ''}
                                </div>
                            </div>

                            <div>
                                <h4 style="margin: 0 0 16px 0; font-size: 16px; font-weight: 800;">Activity Timeline</h4>
                                <div id="deal-activities-list" style="margin-bottom: 20px;">Loading activities...</div>
                                
                                <div style="background: var(--crm-gray-50); padding: 16px; border-radius: 12px; border: 1px solid var(--crm-gray-100);">
                                    <h5 style="margin: 0 0 12px 0; font-size: 13px; font-weight: 800;">Add Activity</h5>
                                    <form id="deal-activity-form" style="display: grid; gap: 12px;">
                                        <input type="hidden" name="deal_id" value="${deal.id}">
                                        <input type="text" name="title" placeholder="What happened?" required style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid var(--crm-gray-200);">
                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                                            <select name="activity_type" required style="padding: 8px; border-radius: 6px; border: 1px solid var(--crm-gray-200);">
                                                <option value="follow_up">Follow-up</option>
                                                <option value="call">Call</option>
                                                <option value="meeting">Meeting</option>
                                                <option value="update">Update</option>
                                            </select>
                                            <input type="datetime-local" name="due_date" style="padding: 8px; border-radius: 6px; border: 1px solid var(--crm-gray-200);">
                                        </div>
                                        <textarea name="description" rows="2" placeholder="Details (optional)" style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid var(--crm-gray-200);"></textarea>
                                        <button type="submit" class="bntm-btn-primary" style="justify-content: center; width: 100%;">Post Activity</button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div style="margin-top: 40px; padding-top: 24px; border-top: 1px solid var(--crm-gray-200); display: flex; gap: 12px;">
                            <button class="bntm-btn-primary" onclick="editDealFromDetails(${deal.id})">
                                Edit Deal
                            </button>
                            <button class="bntm-btn-secondary" onclick="window.open('quotation?tab=quotations&deal_id=${deal.id}', '_blank')">
                                Create Quotation
                            </button>
                            <button class="bntm-btn-secondary" onclick="location.href='?tab=customers&customer_id=${deal.customer_id}'">
                                View Customer Profile
                            </button>
                        </div>
                    </div>
                `;

                            loadDealActivities(deal.id);
                            bindActivityForm();
                            detailsModal.style.display = 'block';
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching deal details:', error);
                    });
            }

            // Add function to edit deal from details modal
            window.editDealFromDetails = function (dealId) {
                detailsModal.style.display = 'none';

                const formData = new FormData();
                formData.append('action', 'crm_get_deal');
                formData.append('deal_id', dealId);
                formData.append('nonce', crmNonce);

                fetch(ajaxurl, { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(json => {
                        if (json.success) {
                            const deal = json.data;
                            document.getElementById('deal-modal-title').textContent = 'Edit Deal';
                            document.getElementById('deal_id').value = deal.id;
                            document.getElementById('deal_customer_id').value = deal.customer_id;
                            document.getElementById('deal_name').value = deal.deal_name;
                            document.getElementById('deal_value').value = deal.deal_value;
                            document.getElementById('service_type').value = deal.service_type || 'BNTM HUB';
                            document.getElementById('estimated_value').value = deal.estimated_value || '';
                            document.getElementById('actual_value').value = deal.actual_value || '';
                            document.getElementById('deal_stage').value = deal.stage;
                            document.getElementById('deal_probability').value = deal.probability;
                            document.getElementById('expected_close_date').value = deal.expected_close_date || '';
                            document.getElementById('exploratory_meeting_date').value = deal.exploratory_meeting_date_raw || '';
                            document.getElementById('contract_signing_date').value = deal.contract_signing_date_raw || '';
                            document.getElementById('project_turnover_date').value = deal.project_turnover_date_raw || '';
                            document.getElementById('next_step').value = deal.next_step || '';
                            document.getElementById('next_step_due_date').value = deal.next_step_due_date_raw || '';
                            document.getElementById('invoice_status').value = deal.invoice_status || 'unpaid';
                            document.getElementById('initial_payment').value = deal.initial_payment || 0;
                            document.getElementById('is_installment').checked = (deal.is_installment == 1);
                            document.getElementById('installment_notes').value = deal.installment_notes || '';
                            document.getElementById('handoff_scope').value = deal.handoff_scope || '';
                            document.getElementById('handoff_notes').value = deal.handoff_notes || '';
                            document.getElementById('document_links').value = deal.document_links || '';
                            document.getElementById('deal_notes').value = deal.notes || '';
                            dealModal.style.display = 'block';
                        }
                    });
            };

            function loadDealActivities(dealId) {
                const formData = new FormData();
                formData.append('action', 'crm_get_activities');
                formData.append('deal_id', dealId);
                formData.append('nonce', crmNonce);

                fetch(ajaxurl, { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(json => {
                        const list = document.getElementById('deal-activities-list');
                        if (!list) return;

                        if (!json.success || !json.data.activities || !json.data.activities.length) {
                            list.innerHTML = '<div style="color:#6b7280; font-size: 13px;">No activities yet.</div>';
                            return;
                        }

                        list.innerHTML = json.data.activities.map(activity => `
                <div style="position: relative; padding-left: 24px; margin-bottom: 20px; border-left: 2px solid var(--crm-gray-200);">
                    <div style="position: absolute; left: -7px; top: 0; width: 12px; height: 12px; border-radius: 50%; background: white; border: 2px solid var(--crm-primary);"></div>
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4px;">
                        <div style="font-weight: 700; color: var(--crm-gray-800); font-size: 14px;">${activity.title}</div>
                        <div style="font-size: 11px; color: var(--crm-gray-400); font-weight: 600; text-transform: uppercase;">${activity.activity_type_label}</div>
                    </div>
                    <div style="font-size: 12px; color: var(--crm-gray-500); margin-bottom: 6px;">${activity.created_at}</div>
                    ${activity.description ? `<div style="font-size: 13px; color: var(--crm-gray-600); line-height: 1.5; background: var(--crm-gray-50); padding: 8px; border-radius: 6px;">${activity.description}</div>` : ''}
                    ${activity.due_date ? `<div style="font-size: 11px; margin-top: 6px; font-weight: 700; color: ${activity.is_overdue ? 'var(--crm-danger)' : 'var(--crm-gray-400)'};">Due: ${activity.due_date}</div>` : ''}
                </div>
            `).join('');
                    });
            }

            function bindActivityForm() {
                const activityForm = document.getElementById('deal-activity-form');
                if (!activityForm || activityForm.dataset.bound === '1') {
                    return;
                }

                activityForm.dataset.bound = '1';
                activityForm.addEventListener('submit', function (e) {
                    e.preventDefault();

                    const formData = new FormData(this);
                    formData.append('action', 'crm_add_activity');
                    formData.append('nonce', crmNonce);

                    fetch(ajaxurl, { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(json => {
                            if (json.success) {
                                this.reset();
                                loadDealActivities(formData.get('deal_id'));
                            } else {
                                alert(json.data.message || 'Failed to add activity');
                            }
                        });
                });
            }
            // Drag and drop functionality for pipeline
            let draggedDeal = null;

            document.querySelectorAll('.deal-card').forEach(card => {
                card.setAttribute('draggable', 'true');

                card.addEventListener('dragstart', function (e) {
                    draggedDeal = this;
                    this.style.opacity = '0.5';
                });

                card.addEventListener('dragend', function () {
                    this.style.opacity = '1';
                });
            });

            document.querySelectorAll('.pipeline-column').forEach(column => {
                column.addEventListener('dragover', function (e) {
                    e.preventDefault();
                    this.style.background = '#e5e7eb';
                });

                column.addEventListener('dragleave', function () {
                    this.style.background = '#f9fafb';
                });

                column.addEventListener('drop', function (e) {
                    e.preventDefault();
                    this.style.background = '#f9fafb';

                    if (draggedDeal) {
                        const dealId = draggedDeal.dataset.id;
                        const newStage = this.dataset.stage;

                        updateDealStage(dealId, newStage);
                    }
                });
            });

            function updateDealStage(dealId, stage) {
                const formData = new FormData();
                formData.append('action', 'crm_update_deal_stage');
                formData.append('deal_id', dealId);
                formData.append('stage', stage);
                formData.append('nonce', crmNonce);

                fetch(ajaxurl, { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(json => {
                        if (json.success) {
                            location.reload();
                        } else {
                            alert('Failed to update deal stage');
                        }
                    });
            }
        })();
    </script>
    <?php
    return ob_get_clean();
}
function crm_customers_tab($business_id)
{
    global $wpdb;
    $customers_table = $wpdb->prefix . 'crm_customers';

    $customers = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $customers_table  ORDER BY created_at DESC",
        $business_id
    ));


    // Get current customer count
    $current_customers = $wpdb->get_var("SELECT COUNT(*) FROM $customers_table");

    // Get customer limit from custom limits (set by tier + addons)
    $custom_limits = get_option('bntm_custom_limits', []);
    $customer_limit = isset($custom_limits['crm_customers']) ? intval($custom_limits['crm_customers']) : 0;

    // Fallback to table limits if custom limits not set
    if ($customer_limit == 0) {
        $table_limits = get_option('bntm_table_limits', []);
        $customer_limit = isset($table_limits[$customers_table]) ? intval($table_limits[$customers_table]) : 0;
    }

    $limit_text = $customer_limit > 0 ? " ({$current_customers}/{$customer_limit})" : " ({$current_customers})";
    $limit_reached = $customer_limit > 0 && $current_customers >= $customer_limit;

    $nonce = wp_create_nonce('crm_nonce');

    ob_start();
    ?>
    <script>
        var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        var crmNonce = '<?php echo $nonce; ?>';
    </script>

    <div style="padding: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <h3 style="margin: 0; font-size: 24px; font-weight: 800;">Customers & Leads <span
                    style="color: var(--crm-gray-400); font-weight: 400;"><?php echo $limit_text; ?></span></h3>
            <button id="create-customer-btn" class="bntm-btn-primary" <?php echo $limit_reached ? 'disabled title="Customer limit reached"' : ''; ?>>
                <span>+</span> Add Customer/Lead
            </button>
        </div>

        <?php if ($limit_reached): ?>
            <div
                style="background: var(--crm-danger-light); border: 1px solid var(--crm-danger); color: var(--crm-danger); padding: 12px 16px; border-radius: var(--crm-radius-sm); margin-bottom: 20px; font-size: 14px; display: flex; align-items: center; gap: 10px;">
                <strong>⚠️ Limit Reached:</strong> Max <?php echo number_format($customer_limit); ?> allowed.
                <a href="<?php echo get_permalink(get_page_by_path('settings')); ?>?tab=billing"
                    style="color: var(--crm-danger); text-decoration: underline; font-weight: 700;">Upgrade Plan</a>
            </div>
        <?php endif; ?>

        <div
            style="margin-bottom: 24px; display: flex; gap: 12px; background: var(--crm-gray-100); padding: 4px; border-radius: 8px; width: fit-content;">
            <label class="filter-toggle">
                <input type="radio" name="customer_filter" value="all" checked style="display: none;">
                <span class="filter-label">All</span>
            </label>
            <label class="filter-toggle">
                <input type="radio" name="customer_filter" value="lead" style="display: none;">
                <span class="filter-label">Leads</span>
            </label>
            <label class="filter-toggle">
                <input type="radio" name="customer_filter" value="customer" style="display: none;">
                <span class="filter-label">Customers</span>
            </label>
        </div>

        <style>
            .filter-toggle input:checked+.filter-label {
                background: white;
                color: var(--crm-primary);
                box-shadow: var(--crm-shadow-sm);
            }

            .filter-label {
                display: inline-block;
                padding: 6px 16px;
                border-radius: 6px;
                font-size: 13px;
                font-weight: 600;
                color: var(--crm-gray-500);
                cursor: pointer;
                transition: all 0.2s;
            }
        </style>

        <?php if (empty($customers)): ?>
            <div
                style="text-align: center; padding: 60px; background: var(--crm-gray-50); border-radius: var(--crm-radius); border: 2px dashed var(--crm-gray-200);">
                <div style="font-size: 40px; margin-bottom: 16px;">👥</div>
                <h4 style="margin: 0 0 8px 0; color: var(--crm-gray-800);">No customers yet</h4>
                <p style="margin: 0; color: var(--crm-gray-500);">Start by adding your first lead or customer.</p>
            </div>
        <?php else: ?>

            <div class="bntm-table-wrapper">
                <table class="bntm-table" id="customers-table">
                    <thead>
                        <tr>
                            <th>Name & Contact</th>
                            <th>Type</th>
                            <th>Email</th>
                            <th>Company</th>
                            <th>Status</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                            <tr data-type="<?php echo esc_attr($customer->type); ?>">
                                <td>
                                    <div style="font-weight: 700; color: var(--crm-gray-900);">
                                        <?php echo esc_html($customer->name); ?></div>
                                    <div style="font-size: 12px; color: var(--crm-gray-500);">
                                        <?php echo esc_html($customer->contact_number); ?></div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo esc_attr($customer->type); ?>">
                                        <?php echo ucfirst($customer->type); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($customer->email); ?></td>
                                <td><?php echo esc_html($customer->company); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo esc_attr($customer->status); ?>">
                                        <?php echo ucfirst($customer->status); ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                        <button class="bntm-btn-secondary edit-customer-btn" data-id="<?php echo $customer->id; ?>"
                                            style="padding: 6px 12px; font-size: 12px;">Edit</button>
                                        <?php if ($customer->type === 'lead'): ?>
                                            <button class="bntm-btn-primary convert-customer-btn" data-id="<?php echo $customer->id; ?>"
                                                style="padding: 6px 12px; font-size: 12px; background: var(--crm-success);">Convert</button>
                                        <?php endif; ?>
                                        <button class="bntm-btn-secondary delete-customer-btn"
                                            data-id="<?php echo $customer->id; ?>"
                                            style="padding: 6px 12px; font-size: 12px; color: var(--crm-danger); border-color: var(--crm-danger-light);">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Create/Edit Customer Modal -->
    <div id="customer-modal" class="bntm-modal">
        <div class="bntm-modal-content">
            <div class="bntm-modal-header">
                <h2 id="customer-modal-title">Add Customer/Lead</h2>
                <div class="bntm-modal-close">&times;</div>
            </div>
            <form id="customer-form" class="bntm-form">
                <input type="hidden" name="customer_id" id="customer_id">

                <div class="bntm-form-grid">
                    <div class="bntm-form-group">
                        <label>Type *</label>
                        <select name="type" id="customer_type" required>
                            <option value="lead">Lead</option>
                            <option value="customer">Customer</option>
                        </select>
                    </div>

                    <div class="bntm-form-group">
                        <label>Full Name *</label>
                        <input type="text" name="name" id="customer_name" required placeholder="John Doe">
                    </div>

                    <div class="bntm-form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" id="customer_email" placeholder="john@example.com">
                    </div>

                    <div class="bntm-form-group">
                        <label>Contact Number</label>
                        <input type="text" name="contact_number" id="customer_contact" placeholder="+1 234 567 890">
                    </div>

                    <div class="bntm-form-group">
                        <label>Company Name</label>
                        <input type="text" name="company" id="customer_company" placeholder="Acme Corp">
                    </div>

                    <div class="bntm-form-group">
                        <label>Lead Source</label>
                        <input type="text" name="source" id="customer_source"
                            placeholder="e.g. Website, Referral, Social Media">
                    </div>

                    <div class="bntm-form-group full-width">
                        <label>Address</label>
                        <textarea name="address" id="customer_address" rows="2"
                            placeholder="Street, City, State, ZIP"></textarea>
                    </div>

                    <div class="bntm-form-group full-width">
                        <label>Tags (comma-separated)</label>
                        <input type="text" name="tags" id="customer_tags" placeholder="e.g. VIP, Enterprise, High-Value">
                    </div>

                    <div class="bntm-form-group full-width">
                        <label>Notes</label>
                        <textarea name="notes" id="customer_notes" rows="3"
                            placeholder="Any additional information..."></textarea>
                    </div>
                </div>

                <div class="bntm-modal-footer">
                    <button type="button" class="bntm-btn-secondary modal-cancel">Cancel</button>
                    <button type="submit" class="bntm-btn-primary">Save Information</button>
                </div>
                <div id="customer-form-message" style="margin-top: 15px;"></div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            const customerModal = document.getElementById('customer-modal');
            const customerForm = document.getElementById('customer-form');

            // Open create customer modal
            document.getElementById('create-customer-btn').addEventListener('click', function () {
                document.getElementById('customer-modal-title').textContent = 'Add Customer/Lead';
                customerForm.reset();
                document.getElementById('customer_id').value = '';
                customerModal.style.display = 'block';
            });

            // Close modal
            document.querySelectorAll('.bntm-modal-close, .modal-cancel').forEach(el => {
                el.addEventListener('click', function () {
                    customerModal.style.display = 'none';
                });
            });

            window.addEventListener('click', function (e) {
                if (e.target === customerModal) customerModal.style.display = 'none';
            });

            // Filter customers
            document.querySelectorAll('input[name="customer_filter"]').forEach(radio => {
                radio.addEventListener('change', function () {
                    const filter = this.value;
                    const rows = document.querySelectorAll('#customers-table tbody tr');

                    rows.forEach(row => {
                        if (filter === 'all') {
                            row.style.display = '';
                        } else {
                            row.style.display = row.dataset.type === filter ? '' : 'none';
                        }
                    });
                });
            });

            // Submit customer form
            customerForm.addEventListener('submit', function (e) {
                e.preventDefault();

                const formData = new FormData(this);
                const action = formData.get('customer_id') ? 'crm_update_customer' : 'crm_create_customer';
                formData.append('action', action);
                formData.append('nonce', crmNonce);

                const btn = this.querySelector('button[type="submit"]');
                btn.disabled = true;
                btn.textContent = 'Saving...';

                fetch(ajaxurl, { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(json => {
                        const msg = document.getElementById('customer-form-message');
                        if (json.success) {
                            msg.innerHTML = '<div class="bntm-notice bntm-notice-success">' + json.data.message + '</div>';
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            msg.innerHTML = '<div class="bntm-notice bntm-notice-error">' + json.data.message + '</div>';
                            btn.disabled = false;
                            btn.textContent = 'Save';
                        }
                    });
            });

            // Edit customer
            document.querySelectorAll('.edit-customer-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    const customerId = this.dataset.id;

                    const formData = new FormData();
                    formData.append('action', 'crm_get_customer');
                    formData.append('customer_id', customerId);
                    formData.append('nonce', crmNonce);

                    fetch(ajaxurl, { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(json => {
                            if (json.success) {
                                const customer = json.data;
                                document.getElementById('customer-modal-title').textContent = 'Edit Customer/Lead';
                                document.getElementById('customer_id').value = customer.id;
                                document.getElementById('customer_type').value = customer.type;
                                document.getElementById('customer_name').value = customer.name;
                                document.getElementById('customer_email').value = customer.email || '';
                                document.getElementById('customer_contact').value = customer.contact_number || '';
                                document.getElementById('customer_company').value = customer.company || '';
                                document.getElementById('customer_address').value = customer.address || '';
                                document.getElementById('customer_source').value = customer.source || '';
                                document.getElementById('customer_tags').value = customer.tags || '';
                                document.getElementById('customer_notes').value = customer.notes || '';
                                customerModal.style.display = 'block';
                            }
                        });
                });
            });

            // Convert to customer
            document.querySelectorAll('.convert-customer-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    if (!confirm('Convert this lead to a customer?')) return;

                    const customerId = this.dataset.id;
                    const formData = new FormData();
                    formData.append('action', 'crm_convert_to_customer');
                    formData.append('customer_id', customerId);
                    formData.append('nonce', crmNonce);

                    fetch(ajaxurl, { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(json => {
                            if (json.success) {
                                alert(json.data.message);
                                location.reload();
                            } else {
                                alert(json.data.message);
                            }
                        });
                });
            });

            // Delete customer
            document.querySelectorAll('.delete-customer-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    if (!confirm('Are you sure you want to delete this customer/lead? This will also delete all related deals, quotations, and billing schedules.')) return;

                    const customerId = this.dataset.id;
                    const formData = new FormData();
                    formData.append('action', 'crm_delete_customer');
                    formData.append('customer_id', customerId);
                    formData.append('nonce', crmNonce);

                    fetch(ajaxurl, { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(json => {
                            if (json.success) {
                                alert(json.data.message);
                                location.reload();
                            } else {
                                alert(json.data.message);
                            }
                        });
                });
            });
        })();
    </script>
    <?php
    return ob_get_clean();
}

function crm_settings_tab($business_id)
{
    global $wpdb;
    $stages_table = $wpdb->prefix . 'crm_pipeline_stages';

    // REMOVED business_id filter
    $stages = $wpdb->get_results(
        "SELECT * FROM $stages_table ORDER BY stage_order ASC"
    );

    $nonce = wp_create_nonce('crm_nonce');

    ob_start();
    ?>
    <script>
        var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        var crmNonce = '<?php echo $nonce; ?>';
    </script>

    <div style="padding: 24px;">
        <div style="margin-bottom: 32px;">
            <h3 style="margin: 0 0 8px 0; font-size: 24px; font-weight: 800;">Pipeline Stages</h3>
            <p style="margin: 0; color: var(--crm-gray-500);">Customize your sales pipeline stages and their visual
                indicators.</p>

            <form id="pipeline-stages-form" style="margin-top: 24px;">
                <div id="stages-list">
                    <?php foreach ($stages as $index => $stage): ?>
                        <div class="stage-row" data-id="<?php echo $stage->id; ?>">
                            <span class="stage-handle">☰</span>
                            <input type="text" name="stages[<?php echo $stage->id; ?>][name]"
                                value="<?php echo esc_attr($stage->stage_name); ?>" placeholder="Stage name" required>
                            <input type="color" name="stages[<?php echo $stage->id; ?>][color]"
                                value="<?php echo esc_attr($stage->color); ?>">
                            <label
                                style="display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: var(--crm-gray-600); cursor: pointer;">
                                <input type="checkbox" name="stages[<?php echo $stage->id; ?>][active]" value="1" <?php checked($stage->is_active, 1); ?>>
                                Active
                            </label>
                            <button type="button" class="bntm-btn-secondary remove-stage-btn"
                                style="color: var(--crm-danger); border-color: var(--crm-danger-light); padding: 6px 12px; font-size: 12px;">Remove</button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" id="add-stage-btn" class="bntm-btn-secondary" style="margin-top: 16px;">
                    + Add New Stage
                </button>

                <div style="margin-top: 24px;">
                    <button type="submit" class="bntm-btn-primary">Save Pipeline Configuration</button>
                </div>
                <div id="pipeline-message" style="margin-top: 16px;"></div>
            </form>
        </div>

        <div style="border-top: 1px solid var(--crm-gray-200); padding-top: 32px;">
            <h3 style="margin: 0 0 8px 0; font-size: 20px; font-weight: 800;">General Settings</h3>
            <p style="margin: 0 0 24px 0; color: var(--crm-gray-500);">Configure default values and regional preferences for
                your CRM.</p>

            <form id="general-settings-form" class="bntm-form" style="padding: 0; max-width: 500px;">
                <div class="bntm-form-group">
                    <label style="font-weight: 700; color: var(--crm-gray-700); margin-bottom: 8px; display: block;">Default
                        Quotation Validity (days)</label>
                    <input type="number" name="quotation_validity_days"
                        value="<?php echo esc_attr(bntm_get_setting('crm_quotation_validity', '30')); ?>" min="1"
                        style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--crm-gray-300);">
                </div>

                <div class="bntm-form-group">
                    <label
                        style="font-weight: 700; color: var(--crm-gray-700); margin-bottom: 8px; display: block;">Currency</label>
                    <select name="currency"
                        style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--crm-gray-300);">
                        <option value="USD" <?php selected(bntm_get_setting('crm_currency', 'PHP'), 'USD'); ?>>USD ($)
                        </option>
                        <option value="EUR" <?php selected(bntm_get_setting('crm_currency', 'PHP'), 'EUR'); ?>>EUR (€)
                        </option>
                        <option value="GBP" <?php selected(bntm_get_setting('crm_currency', 'PHP'), 'GBP'); ?>>GBP (£)
                        </option>
                        <option value="PHP" <?php selected(bntm_get_setting('crm_currency', 'PHP'), 'PHP'); ?>>PHP (₱)
                        </option>
                    </select>
                </div>

                <button type="submit" class="bntm-btn-primary" style="margin-top: 16px;">Save General Settings</button>
                <div id="general-settings-message" style="margin-top: 16px;"></div>
            </form>
        </div>
    </div>

    <style>
        .stage-row {
            display: flex;
            gap: 10px;
            align-items: center;
            padding: 10px;
            background: #f9fafb;
            border-radius: 4px;
            margin-bottom: 10px;
        }

        .stage-handle {
            font-size: 20px;
            color: #9ca3af;
        }

        .stage-row input[type="text"] {
            flex: 1;
        }

        .stage-row input[type="color"] {
            width: 60px;
        }
    </style>

    <script>
        (function () {
            let stageCounter = 1000;

            // Add new stage
            document.getElementById('add-stage-btn').addEventListener('click', function () {
                const stagesList = document.getElementById('stages-list');
                const newStage = document.createElement('div');
                newStage.className = 'stage-row';
                newStage.dataset.id = 'new_' + stageCounter;
                newStage.innerHTML = `
            <span class="stage-handle" style="cursor: move;">☰</span>
            <input type="text" name="stages[new_${stageCounter}][name]" placeholder="Stage name" required>
            <input type="color" name="stages[new_${stageCounter}][color]" value="#3b82f6">
            <label>
                <input type="checkbox" name="stages[new_${stageCounter}][active]" value="1" checked>
                Active
            </label>
            <button type="button" class="bntm-btn-small remove-stage-btn">Remove</button>
        `;
                stagesList.appendChild(newStage);
                stageCounter++;

                // Add remove handler
                newStage.querySelector('.remove-stage-btn').addEventListener('click', function () {
                    if (confirm('Remove this stage?')) {
                        newStage.remove();
                    }
                });
            });

            // Remove stage handlers
            document.querySelectorAll('.remove-stage-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    if (confirm('Remove this stage? Existing deals in this stage will need to be moved.')) {
                        this.closest('.stage-row').remove();
                    }
                });
            });

            // Submit pipeline stages
            document.getElementById('pipeline-stages-form').addEventListener('submit', function (e) {
                e.preventDefault();

                const formData = new FormData(this);
                formData.append('action', 'crm_save_pipeline_stages');
                formData.append('nonce', crmNonce);

                const btn = this.querySelector('button[type="submit"]');
                btn.disabled = true;
                btn.textContent = 'Saving...';

                fetch(ajaxurl, { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(json => {
                        const msg = document.getElementById('pipeline-message');
                        if (json.success) {
                            msg.innerHTML = '<div class="bntm-notice bntm-notice-success">' + json.data.message + '</div>';
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            msg.innerHTML = '<div class="bntm-notice bntm-notice-error">' + json.data.message + '</div>';
                            btn.disabled = false;
                            btn.textContent = 'Save Pipeline Stages';
                        }
                    });
            });

            // Submit general settings
            document.getElementById('general-settings-form').addEventListener('submit', function (e) {
                e.preventDefault();

                const formData = new FormData(this);
                formData.append('action', 'crm_save_general_settings');
                formData.append('nonce', crmNonce);

                const btn = this.querySelector('button[type="submit"]');
                btn.disabled = true;
                btn.textContent = 'Saving...';

                fetch(ajaxurl, { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(json => {
                        const msg = document.getElementById('general-settings-message');
                        msg.innerHTML = '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + json.data.message + '</div>';
                        btn.disabled = false;
                        btn.textContent = 'Save General Settings';
                    });
            });
        })();
    </script>
    <?php
    return ob_get_clean();
}
/* ---------- AJAX HANDLERS ---------- */

function bntm_ajax_crm_create_customer()
{
    check_ajax_referer('crm_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $customers_table = $wpdb->prefix . 'crm_customers';
    $business_id = get_current_user_id();

    $type = sanitize_text_field($_POST['type'] ?? 'lead');
    $name = sanitize_text_field($_POST['name'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    $contact_number = sanitize_text_field($_POST['contact_number'] ?? '');
    $company = sanitize_text_field($_POST['company'] ?? '');
    $address = sanitize_textarea_field($_POST['address'] ?? '');
    $source = sanitize_text_field($_POST['source'] ?? '');
    $tags = sanitize_text_field($_POST['tags'] ?? '');
    $notes = sanitize_textarea_field($_POST['notes'] ?? '');

    if (empty($name)) {
        wp_send_json_error(['message' => 'Name is required']);
    }

    $result = $wpdb->insert($customers_table, [
        'rand_id' => bntm_rand_id(),
        'business_id' => $business_id,
        'type' => $type,
        'name' => $name,
        'email' => $email,
        'contact_number' => $contact_number,
        'company' => $company,
        'address' => $address,
        'source' => $source,
        'tags' => $tags,
        'status' => 'active',
        'notes' => $notes,
        'created_at' => current_time('mysql')
    ]);

    if ($result) {
        wp_send_json_success(['message' => ucfirst($type) . ' created successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to create customer/lead']);
    }
}

function bntm_ajax_crm_update_customer()
{
    check_ajax_referer('crm_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $customers_table = $wpdb->prefix . 'crm_customers';
    $business_id = get_current_user_id();

    $customer_id = intval($_POST['customer_id'] ?? 0);
    $type = sanitize_text_field($_POST['type'] ?? 'lead');
    $name = sanitize_text_field($_POST['name'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    $contact_number = sanitize_text_field($_POST['contact_number'] ?? '');
    $company = sanitize_text_field($_POST['company'] ?? '');
    $address = sanitize_textarea_field($_POST['address'] ?? '');
    $source = sanitize_text_field($_POST['source'] ?? '');
    $tags = sanitize_text_field($_POST['tags'] ?? '');
    $notes = sanitize_textarea_field($_POST['notes'] ?? '');

    if (empty($name)) {
        wp_send_json_error(['message' => 'Name is required']);
    }

    $result = $wpdb->update(
        $customers_table,
        [
            'type' => $type,
            'name' => $name,
            'email' => $email,
            'contact_number' => $contact_number,
            'company' => $company,
            'address' => $address,
            'source' => $source,
            'tags' => $tags,
            'notes' => $notes
        ],
        [
            'id' => $customer_id
        ],
        ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'],
        ['%d']
    );

    if ($result !== false) {
        wp_send_json_success(['message' => 'Customer/Lead updated successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to update customer/lead']);
    }
}

function bntm_ajax_crm_delete_customer()
{
    check_ajax_referer('crm_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $customers_table = $wpdb->prefix . 'crm_customers';
    $business_id = get_current_user_id();
    $customer_id = intval($_POST['customer_id'] ?? 0);

    $result = $wpdb->delete(
        $customers_table,
        ['id' => $customer_id],
        ['%d']
    );

    if ($result) {
        wp_send_json_success(['message' => 'Customer/Lead deleted successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete customer/lead']);
    }
}

function bntm_ajax_crm_get_customer()
{
    check_ajax_referer('crm_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $customers_table = $wpdb->prefix . 'crm_customers';
    $business_id = get_current_user_id();
    $customer_id = intval($_POST['customer_id'] ?? 0);

    $customer = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $customers_table WHERE id = %d AND business_id = %d",
        $customer_id,
        $business_id
    ));

    if ($customer) {
        wp_send_json_success((array) $customer);
    } else {
        wp_send_json_error(['message' => 'Customer not found']);
    }
}

function bntm_ajax_crm_convert_to_customer()
{
    check_ajax_referer('crm_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $customers_table = $wpdb->prefix . 'crm_customers';
    $business_id = get_current_user_id();
    $customer_id = intval($_POST['customer_id'] ?? 0);

    $result = $wpdb->update(
        $customers_table,
        ['type' => 'customer'],
        ['id' => $customer_id],
        ['%s'],
        ['%d']
    );

    if ($result !== false) {
        wp_send_json_success(['message' => 'Lead converted to customer successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to convert lead']);
    }
}

function bntm_ajax_crm_create_deal()
{
    check_ajax_referer('crm_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $deals_table = $wpdb->prefix . 'crm_deals';
    $business_id = get_current_user_id();

    $customer_id = intval($_POST['customer_id'] ?? 0);
    $deal_name = sanitize_text_field($_POST['deal_name'] ?? '');
    $deal_value = floatval($_POST['deal_value'] ?? 0);
    $service_type = sanitize_text_field($_POST['service_type'] ?? 'BNTM HUB');
    $estimated_value = floatval($_POST['estimated_value'] ?? 0);
    $actual_value = floatval($_POST['actual_value'] ?? 0);
    $stage = sanitize_text_field($_POST['stage'] ?? 'Lead');
    $probability = intval($_POST['probability'] ?? 50);
    $expected_close_date = sanitize_text_field($_POST['expected_close_date'] ?? '');
    $exploratory_meeting_date = sanitize_text_field($_POST['exploratory_meeting_date'] ?? '');
    $contract_signing_date = sanitize_text_field($_POST['contract_signing_date'] ?? '');
    $project_turnover_date = sanitize_text_field($_POST['project_turnover_date'] ?? '');
    $next_step = sanitize_text_field($_POST['next_step'] ?? '');
    $next_step_due_date = sanitize_text_field($_POST['next_step_due_date'] ?? '');
    $invoice_status = sanitize_text_field($_POST['invoice_status'] ?? 'unpaid');
    $initial_payment = floatval($_POST['initial_payment'] ?? 0);
    $is_installment = isset($_POST['is_installment']) ? 1 : 0;
    $installment_notes = sanitize_textarea_field($_POST['installment_notes'] ?? '');
    $handoff_scope = sanitize_textarea_field($_POST['handoff_scope'] ?? '');
    $handoff_notes = sanitize_textarea_field($_POST['handoff_notes'] ?? '');
    $document_links = sanitize_textarea_field($_POST['document_links'] ?? '');
    $notes = sanitize_textarea_field($_POST['notes'] ?? '');

    $value_basis = $actual_value > 0 ? $actual_value : ($estimated_value > 0 ? $estimated_value : $deal_value);
    $remaining_balance = max($value_basis - $initial_payment, 0);

    if (empty($deal_name) || $customer_id <= 0) {
        wp_send_json_error(['message' => 'Deal name and customer are required']);
    }

    $result = $wpdb->insert($deals_table, [
        'rand_id' => bntm_rand_id(),
        'business_id' => $business_id,
        'customer_id' => $customer_id,
        'deal_name' => $deal_name,
        'deal_value' => $deal_value,
        'service_type' => $service_type,
        'estimated_value' => $estimated_value,
        'actual_value' => $actual_value,
        'stage' => $stage,
        'probability' => $probability,
        'expected_close_date' => $expected_close_date ?: null,
        'exploratory_meeting_date' => $exploratory_meeting_date ?: null,
        'contract_signing_date' => $contract_signing_date ?: null,
        'project_turnover_date' => $project_turnover_date ?: null,
        'next_step' => $next_step,
        'next_step_due_date' => $next_step_due_date ?: null,
        'invoice_status' => $invoice_status,
        'initial_payment' => $initial_payment,
        'remaining_balance' => $remaining_balance,
        'is_installment' => $is_installment,
        'installment_notes' => $installment_notes,
        'handoff_scope' => $handoff_scope,
        'handoff_notes' => $handoff_notes,
        'document_links' => $document_links,
        'notes' => $notes,
        'created_at' => current_time('mysql')
    ]);

    if ($result) {
        wp_send_json_success(['message' => 'Deal created successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to create deal']);
    }
}

add_action('wp_ajax_crm_update_deal', 'bntm_ajax_crm_update_deal');
function bntm_ajax_crm_update_deal()
{
    check_ajax_referer('crm_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $deals_table = $wpdb->prefix . 'crm_deals';
    $business_id = get_current_user_id();

    $deal_id = intval($_POST['deal_id'] ?? 0);
    $customer_id = intval($_POST['customer_id'] ?? 0);
    $deal_name = sanitize_text_field($_POST['deal_name'] ?? '');
    $deal_value = floatval($_POST['deal_value'] ?? 0);
    $service_type = sanitize_text_field($_POST['service_type'] ?? 'BNTM HUB');
    $estimated_value = floatval($_POST['estimated_value'] ?? 0);
    $actual_value = floatval($_POST['actual_value'] ?? 0);
    $stage = sanitize_text_field($_POST['stage'] ?? 'Lead');
    $probability = intval($_POST['probability'] ?? 50);
    $expected_close_date = sanitize_text_field($_POST['expected_close_date'] ?? '');
    $exploratory_meeting_date = sanitize_text_field($_POST['exploratory_meeting_date'] ?? '');
    $contract_signing_date = sanitize_text_field($_POST['contract_signing_date'] ?? '');
    $project_turnover_date = sanitize_text_field($_POST['project_turnover_date'] ?? '');
    $next_step = sanitize_text_field($_POST['next_step'] ?? '');
    $next_step_due_date = sanitize_text_field($_POST['next_step_due_date'] ?? '');
    $invoice_status = sanitize_text_field($_POST['invoice_status'] ?? 'unpaid');
    $initial_payment = floatval($_POST['initial_payment'] ?? 0);
    $is_installment = isset($_POST['is_installment']) ? 1 : 0;
    $installment_notes = sanitize_textarea_field($_POST['installment_notes'] ?? '');
    $handoff_scope = sanitize_textarea_field($_POST['handoff_scope'] ?? '');
    $handoff_notes = sanitize_textarea_field($_POST['handoff_notes'] ?? '');
    $document_links = sanitize_textarea_field($_POST['document_links'] ?? '');
    $notes = sanitize_textarea_field($_POST['notes'] ?? '');

    $value_basis = $actual_value > 0 ? $actual_value : ($estimated_value > 0 ? $estimated_value : $deal_value);
    $remaining_balance = max($value_basis - $initial_payment, 0);

    if (empty($deal_name) || $customer_id <= 0) {
        wp_send_json_error(['message' => 'Deal name and customer are required']);
    }

    $result = $wpdb->update(
        $deals_table,
        [
            'customer_id' => $customer_id,
            'deal_name' => $deal_name,
            'deal_value' => $deal_value,
            'service_type' => $service_type,
            'estimated_value' => $estimated_value,
            'actual_value' => $actual_value,
            'stage' => $stage,
            'probability' => $probability,
            'expected_close_date' => $expected_close_date ?: null,
            'exploratory_meeting_date' => $exploratory_meeting_date ?: null,
            'contract_signing_date' => $contract_signing_date ?: null,
            'project_turnover_date' => $project_turnover_date ?: null,
            'next_step' => $next_step,
            'next_step_due_date' => $next_step_due_date ?: null,
            'invoice_status' => $invoice_status,
            'initial_payment' => $initial_payment,
            'remaining_balance' => $remaining_balance,
            'is_installment' => $is_installment,
            'installment_notes' => $installment_notes,
            'handoff_scope' => $handoff_scope,
            'handoff_notes' => $handoff_notes,
            'document_links' => $document_links,
            'notes' => $notes
        ],
        [
            'id' => $deal_id,
            'business_id' => $business_id
        ],
        ['%d', '%s', '%f', '%s', '%f', '%f', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%d', '%s', '%s', '%s', '%s'],
        ['%d', '%d']
    );

    if ($result !== false) {
        wp_send_json_success(['message' => 'Deal updated successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to update deal']);
    }
}

function bntm_ajax_crm_get_deal()
{
    check_ajax_referer('crm_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $deals_table = $wpdb->prefix . 'crm_deals';
    $customers_table = $wpdb->prefix . 'crm_customers';
    $business_id = get_current_user_id();
    $deal_id = intval($_POST['deal_id'] ?? 0);

    $deal = $wpdb->get_row($wpdb->prepare(
        "SELECT d.*, c.name as customer_name 
         FROM $deals_table d
         LEFT JOIN $customers_table c ON d.customer_id = c.id
         WHERE d.id = %d",
        $deal_id
    ));

    if ($deal) {
        $deal->deal_value_formatted = crm_format_price($deal->deal_value);
        $deal->estimated_value_formatted = crm_format_price($deal->estimated_value ?: $deal->deal_value);
        $deal->actual_value_formatted = crm_format_price($deal->actual_value);
        $deal->initial_payment_formatted = crm_format_price($deal->initial_payment);
        $deal->remaining_balance_formatted = crm_format_price($deal->remaining_balance);
        $deal->invoice_status_label = ucfirst($deal->invoice_status ?: 'unpaid');

        $deal->expected_close_date_raw = $deal->expected_close_date;
        $deal->exploratory_meeting_date_raw = $deal->exploratory_meeting_date;
        $deal->contract_signing_date_raw = $deal->contract_signing_date;
        $deal->project_turnover_date_raw = $deal->project_turnover_date;
        $deal->next_step_due_date_raw = $deal->next_step_due_date;

        $deal->expected_close_date = $deal->expected_close_date ? date('M d, Y', strtotime($deal->expected_close_date)) : null;
        $deal->exploratory_meeting_date = $deal->exploratory_meeting_date ? date('M d, Y', strtotime($deal->exploratory_meeting_date)) : null;
        $deal->contract_signing_date = $deal->contract_signing_date ? date('M d, Y', strtotime($deal->contract_signing_date)) : null;
        $deal->project_turnover_date = $deal->project_turnover_date ? date('M d, Y', strtotime($deal->project_turnover_date)) : null;
        $deal->next_step_due_date = $deal->next_step_due_date ? date('M d, Y', strtotime($deal->next_step_due_date)) : null;
        $deal->is_followup_overdue = !empty($deal->next_step_due_date_raw) && strtotime($deal->next_step_due_date_raw) < current_time('timestamp') && !in_array($deal->stage, ['Won', 'Lost'], true);

        $deal->document_links_html = '';
        if (!empty($deal->document_links)) {
            $links = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $deal->document_links)));
            $safe_links = [];
            foreach ($links as $link) {
                $safe_links[] = '<a href="' . esc_url($link) . '" target="_blank" rel="noopener noreferrer">' . esc_html($link) . '</a>';
            }
            $deal->document_links_html = implode('<br>', $safe_links);
        }

        wp_send_json_success((array) $deal);
    } else {
        wp_send_json_error(['message' => 'Deal not found']);
    }
}

function bntm_ajax_crm_add_activity()
{
    check_ajax_referer('crm_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $activities_table = $wpdb->prefix . 'crm_activities';
    $deals_table = $wpdb->prefix . 'crm_deals';

    $deal_id = intval($_POST['deal_id'] ?? 0);
    $title = sanitize_text_field($_POST['title'] ?? '');
    $activity_type = sanitize_text_field($_POST['activity_type'] ?? 'update');
    $description = sanitize_textarea_field($_POST['description'] ?? '');
    $due_date = sanitize_text_field($_POST['due_date'] ?? '');

    if ($deal_id <= 0 || empty($title)) {
        wp_send_json_error(['message' => 'Deal and title are required']);
    }

    $deal = $wpdb->get_row($wpdb->prepare("SELECT id, customer_id FROM $deals_table WHERE id = %d", $deal_id));
    if (!$deal) {
        wp_send_json_error(['message' => 'Deal not found']);
    }

    $result = $wpdb->insert(
        $activities_table,
        [
            'business_id' => get_current_user_id(),
            'customer_id' => intval($deal->customer_id),
            'deal_id' => $deal_id,
            'activity_type' => $activity_type,
            'title' => $title,
            'description' => $description,
            'due_date' => $due_date ?: null,
            'assigned_to' => get_current_user_id(),
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql')
        ]
    );

    if (!$result) {
        wp_send_json_error(['message' => 'Failed to add activity']);
    }

    $wpdb->update(
        $deals_table,
        ['last_activity_at' => current_time('mysql')],
        ['id' => $deal_id],
        ['%s'],
        ['%d']
    );

    wp_send_json_success(['message' => 'Activity added']);
}

function bntm_ajax_crm_get_activities()
{
    check_ajax_referer('crm_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $activities_table = $wpdb->prefix . 'crm_activities';
    $deal_id = intval($_POST['deal_id'] ?? 0);

    if ($deal_id <= 0) {
        wp_send_json_error(['message' => 'Deal is required']);
    }

    $activities = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $activities_table WHERE deal_id = %d ORDER BY created_at DESC LIMIT 30",
        $deal_id
    ));

    $type_labels = [
        'follow_up' => 'Follow-up',
        'call' => 'Call',
        'meeting' => 'Meeting',
        'update' => 'Major Update'
    ];

    $mapped = array_map(function ($activity) use ($type_labels) {
        $activity->activity_type_label = $type_labels[$activity->activity_type] ?? ucfirst($activity->activity_type);
        $activity->created_at = date('M d, Y h:i A', strtotime($activity->created_at));
        $activity->due_date = $activity->due_date ? date('M d, Y h:i A', strtotime($activity->due_date)) : null;
        $activity->is_overdue = !empty($activity->due_date) && empty($activity->completed) && strtotime($activity->due_date) < current_time('timestamp');
        return $activity;
    }, $activities);

    wp_send_json_success(['activities' => $mapped]);
}

function bntm_ajax_crm_update_deal_stage()
{
    check_ajax_referer('crm_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $deals_table = $wpdb->prefix . 'crm_deals';
    $business_id = get_current_user_id();

    $deal_id = intval($_POST['deal_id'] ?? 0);
    $stage = sanitize_text_field($_POST['stage'] ?? '');

    $update_data = ['stage' => $stage];

    // If stage is "Won", set actual_close_date
    if ($stage === 'Won') {
        $update_data['actual_close_date'] = current_time('mysql');
    }

    $result = $wpdb->update(
        $deals_table,
        $update_data,
        ['id' => $deal_id],
        array_fill(0, count($update_data), '%s'),
        ['%d']
    );

    if ($result !== false) {
        wp_send_json_success(['message' => 'Deal stage updated successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to update deal stage']);
    }
}

function bntm_ajax_crm_save_pipeline_stages()
{
    check_ajax_referer('crm_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $stages_table = $wpdb->prefix . 'crm_pipeline_stages';
    $business_id = get_current_user_id();

    $stages = $_POST['stages'] ?? [];

    if (empty($stages)) {
        wp_send_json_error(['message' => 'No stages provided']);
    }

    // Get all existing stage IDs
    $existing_stages = $wpdb->get_col("SELECT id FROM $stages_table");
    $submitted_stage_ids = [];

    $order = 1;
    foreach ($stages as $stage_id => $stage_data) {
        $name = sanitize_text_field($stage_data['name'] ?? '');
        $color = sanitize_text_field($stage_data['color'] ?? '#3b82f6');
        $active = isset($stage_data['active']) ? 1 : 0;

        if (empty($name))
            continue;

        if (strpos($stage_id, 'new_') === 0) {
            // Insert new stage
            $wpdb->insert($stages_table, [
                'business_id' => $business_id,
                'stage_name' => $name,
                'stage_order' => $order,
                'color' => $color,
                'is_active' => $active
            ]);
        } else {
            // Update existing stage
            $stage_id_int = intval($stage_id);
            $submitted_stage_ids[] = $stage_id_int;

            $wpdb->update(
                $stages_table,
                [
                    'stage_name' => $name,
                    'stage_order' => $order,
                    'color' => $color,
                    'is_active' => $active
                ],
                ['id' => $stage_id_int],
                ['%s', '%d', '%s', '%d'],
                ['%d']
            );
        }

        $order++;
    }

    // Delete stages that were removed (not in submitted list)
    $stages_to_delete = array_diff($existing_stages, $submitted_stage_ids);
    if (!empty($stages_to_delete)) {
        $placeholders = implode(',', array_fill(0, count($stages_to_delete), '%d'));
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $stages_table WHERE id IN ($placeholders)",
                ...$stages_to_delete
            )
        );
    }

    wp_send_json_success(['message' => 'Pipeline stages saved successfully!']);
}

add_action('wp_ajax_crm_save_general_settings', 'bntm_ajax_crm_save_general_settings');
function bntm_ajax_crm_save_general_settings()
{
    check_ajax_referer('crm_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    bntm_set_setting('crm_quotation_validity', intval($_POST['quotation_validity_days'] ?? 30));
    bntm_set_setting('crm_currency', sanitize_text_field($_POST['currency'] ?? 'PHP'));

    wp_send_json_success(['message' => 'Settings saved successfully!']);
}
/* ---------- QUOTATION VIEW PAGE ---------- */

/* ---------- HELPER FUNCTIONS ---------- */
function crm_format_price($amount)
{
    return '₱' . number_format((float) $amount, 2);
}
