<?php
/**
 * Module Name: HR Management
 * Module Slug: hr
 * Description: Complete Human Resources management solution with employee records, attendance tracking, and leave management
 * Version: 1.0.3
 * Author: Your Name
 * Icon: <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
 */

if (!defined('ABSPATH')) exit;

define('BNTM_HR_PATH', dirname(__FILE__) . '/');
define('BNTM_HR_URL', plugin_dir_url(__FILE__));

/* ── SVG ICON HELPERS ─────────────────────────────────────────── */

function bntm_hr_svg($name, $size = 16, $extra = '') {
    $icons = [
        'users'         => '<path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>',
        'person'        => '<path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/>',
        'lock'          => '<path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>',
        'id-card'       => '<path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm-8 2.75c1.24 0 2.25 1.01 2.25 2.25S13.24 11.25 12 11.25 9.75 10.24 9.75 9 10.76 6.75 12 6.75zM17 17H7v-.75c0-1.67 3.33-2.5 5-2.5s5 .83 5 2.5V17z"/>',
        'clipboard'     => '<path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm2 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>',
        'emergency'     => '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>',
        'building'      => '<path d="M12 3L2 12h3v8h6v-5h2v5h6v-8h3L12 3zm0 12.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/>',
        'save'          => '<path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/>',
        'bank'          => '<path d="M4 10v7h3v-7H4zm6 0v7h3v-7h-3zM2 22h19v-3H2v3zm14-12v7h3v-7h-3zM11.5 1L2 6v2h19V6l-9.5-5z"/>',
        'qr'            => '<path d="M3 11h2v2H3zm0-4h2v2H3zm4 0h2v2H7zm0 4h2v2H7zm4-8v6h6V3h-6zm4 4h-2V5h2v2zM3 3h6v6H3V3zm2 4h2V5H5v2zm4 10h2v2H9zm0-4h2v2H9zm4 4h2v2h-2zm0-4h2v2h-2zm4-4h2v2h-2zm0 4h2v2h-2zm-4 4h2v2h-2zm0-4h2v2h-2zm-8 0h2v2H9zm0 4h2v2H9zM3 13h6v6H3v-6zm2 4h2v-2H5v2z"/>',
        'upload'        => '<path d="M9 16h6v-6h4l-7-7-7 7h4zm-4 2h14v2H5z"/>',
        'eye'           => '<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>',
        'close'         => '<path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>',
        'download'      => '<path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/>',
        'delete'        => '<path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>',
        'edit'          => '<path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>',
        'check'         => '<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>',
        'payment'       => '<path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/>',
        'add'           => '<path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>',
        'settings'      => '<path d="M19.14,12.94c0.04-0.3,0.06-0.61,0.06-0.94c0-0.32-0.02-0.64-0.07-0.94l2.03-1.58c0.18-0.14,0.23-0.41,0.12-0.61 l-1.92-3.32c-0.12-0.22-0.37-0.29-0.59-0.22l-2.39,0.96c-0.5-0.38-1.03-0.7-1.62-0.94L14.4,2.81c-0.04-0.24-0.24-0.41-0.48-0.41 h-3.84c-0.24,0-0.43,0.17-0.47,0.41L9.25,5.35C8.66,5.59,8.12,5.92,7.63,6.29L5.24,5.33c-0.22-0.08-0.47,0-0.59,0.22L2.74,8.87 C2.62,9.08,2.66,9.34,2.86,9.48l2.03,1.58C4.84,11.36,4.8,11.69,4.8,12s0.02,0.64,0.07,0.94l-2.03,1.58 c-0.18,0.14-0.23,0.41-0.12,0.61l1.92,3.32c0.12,0.22,0.37,0.29,0.59,0.22l2.39-0.96c0.5,0.38,1.03,0.7,1.62,0.94l0.36,2.54 c0.05,0.24,0.24,0.41,0.48,0.41h3.84c0.24,0,0.44-0.17,0.47-0.41l0.36-2.54c0.59-0.24,1.13-0.56,1.62-0.94l2.39,0.96 c0.22,0.08,0.47,0,0.59-0.22l1.92-3.32c0.12-0.22,0.07-0.47-0.12-0.61L19.14,12.94z M12,15.6c-1.98,0-3.6-1.62-3.6-3.6 s1.62-3.6,3.6-3.6s3.6,1.62,3.6,3.6S13.98,15.6,12,15.6z"/>',
        'calendar'      => '<path d="M20 3h-1V1h-2v2H7V1H5v2H4c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 18H4V8h16v13z"/>',
        'clock'         => '<path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/>',
        'money'         => '<path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/>',
        'import'        => '<path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/>',
        'revert'        => '<path d="M12.5 8c-2.65 0-5.05.99-6.9 2.6L2 7v9h9l-3.62-3.62c1.39-1.16 3.16-1.88 5.12-1.88 3.54 0 6.55 2.31 7.6 5.5l2.37-.78C21.08 11.03 17.15 8 12.5 8z"/>',
        'filter'        => '<path d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z"/>',
        'copy'          => '<path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/>',
        'kiosk'         => '<path d="M20 3H4v10c0 1.1.9 2 2 2h3v2H7v2h10v-2h-2v-2h3c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 12H4V5h16v10z"/>',
    ];
    $path = $icons[$name] ?? '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/>';
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="'.$size.'" height="'.$size.'" style="vertical-align:middle;'.$extra.'">'.$path.'</svg>';
}

/* ---------- MODULE CONFIGURATION ---------- */

function bntm_hr_get_pages() {
    return [
        'HR Portal' => '[hr_dashboard]',
        'HR Kiosk'  => '[hr_kiosk]'
    ];
}

function bntm_hr_get_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $prefix  = $wpdb->prefix;

    return [
        'hr_attendance' => "CREATE TABLE {$prefix}hr_attendance (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            employee_id BIGINT UNSIGNED NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            clock_in DATETIME NOT NULL,
            clock_out DATETIME NULL,
            total_hours DECIMAL(5,2) NULL,
            notes TEXT,
            status VARCHAR(50) DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_employee (employee_id),
            INDEX idx_business (business_id),
            INDEX idx_date (clock_in)
        ) {$charset};",

        'hr_leave_requests' => "CREATE TABLE {$prefix}hr_leave_requests (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            employee_id BIGINT UNSIGNED NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            leave_type VARCHAR(50) NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            total_days INT NOT NULL,
            reason TEXT,
            status VARCHAR(50) DEFAULT 'pending',
            approved_by BIGINT UNSIGNED NULL,
            approved_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_employee (employee_id),
            INDEX idx_status (status),
            INDEX idx_dates (start_date, end_date)
        ) {$charset};",

        'hr_payslips' => "CREATE TABLE {$prefix}hr_payslips (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            employee_id BIGINT UNSIGNED NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL,
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            total_hours DECIMAL(5,2) NULL,
            basic_pay DECIMAL(10,2) NOT NULL,
            overtime_pay DECIMAL(10,2) DEFAULT 0,
            total_deductions DECIMAL(10,2) DEFAULT 0,
            net_pay DECIMAL(10,2) NOT NULL,
            deductions_data TEXT,
            adjustments_data TEXT,
            is_imported TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_employee (employee_id),
            INDEX idx_period (period_start, period_end),
            INDEX idx_imported (is_imported)
        ) {$charset};",

        'hr_overtime' => "CREATE TABLE {$prefix}hr_overtime (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            employee_id BIGINT UNSIGNED NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL,
            overtime_date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            total_hours DECIMAL(5,2) NOT NULL,
            reason TEXT,
            status VARCHAR(50) DEFAULT 'pending',
            approved_by BIGINT UNSIGNED NULL,
            approved_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_employee (employee_id),
            INDEX idx_status (status),
            INDEX idx_date (overtime_date)
        ) {$charset};",

        'hr_missing_logs' => "CREATE TABLE {$prefix}hr_missing_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            employee_id BIGINT UNSIGNED NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL,
            log_date DATE NOT NULL,
            log_type VARCHAR(20) NOT NULL,
            clock_in_time TIME NULL,
            clock_out_time TIME NULL,
            reason TEXT,
            status VARCHAR(50) DEFAULT 'pending',
            approved_by BIGINT UNSIGNED NULL,
            approved_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_employee (employee_id),
            INDEX idx_status (status),
            INDEX idx_date (log_date)
        ) {$charset};",

        'hr_employee_meta' => "CREATE TABLE {$prefix}hr_employee_meta (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            employee_id BIGINT UNSIGNED NOT NULL,
            meta_key VARCHAR(100) NOT NULL,
            meta_value LONGTEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_employee (employee_id),
            INDEX idx_meta_key (meta_key)
        ) {$charset};"
    ];
}

function bntm_hr_update_tables() {
    return bntm_update_module_tables('hr');
}

function bntm_hr_get_shortcodes() {
    return [
        'hr_dashboard' => 'bntm_shortcode_hr',
        'hr_kiosk'     => 'bntm_shortcode_hr_kiosk'
    ];
}

function bntm_hr_create_tables() {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $tables = bntm_hr_get_tables();
    foreach ($tables as $sql) {
        dbDelta($sql);
    }
    return count($tables);
}

/* ---------- SHORTCODE HANDLERS ---------- */

function bntm_shortcode_hr() {
    if (!is_user_logged_in()) {
        return '<p>Please log in to access the HR Portal.</p>';
    }

    $type        = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'dashboard';
    $user_id     = get_current_user_id();
    $current_user = wp_get_current_user();
    $is_wp_admin  = current_user_can('manage_options');
    $current_role = bntm_get_user_role($current_user->ID);
    $can_manage   = $is_wp_admin || in_array($current_role, ['owner', 'manager']);

    ob_start();

    $tabs = $can_manage ? [
        'dashboard'  => 'Dashboard',
        'employees'  => 'Employees',
        'attendance' => 'Attendance',
        'leaves'     => 'Leave Requests',
        'overtime'   => 'Overtime & Missing Logs',
        'payslips'   => 'Payslips',
        'settings'   => 'Settings'
    ] : [
        'dashboard'  => 'My Dashboard',
        'attendance' => 'My Attendance',
        'leaves'     => 'My Leaves',
        'overtime'   => 'Overtime & Missing Logs',
        'payslips'   => 'My Payslips',
        'profile'    => 'My Profile',
    ];
    ?>
    <style>
    /* Modern HR Design */
    :root {
        --hr-primary: #4f46e5;
        --hr-primary-h: #4338ca;
        --hr-surface: #ffffff;
        --hr-bg: #f8fafc;
        --hr-border: #e2e8f0;
        --hr-text-main: #0f172a;
        --hr-text-muted: #64748b;
        --hr-radius: 8px;
        --hr-radius-sm: 6px;
        --hr-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.03);
    }
    .bntm-tabs {
        display: flex;
        gap: 8px;
        margin-bottom: 24px;
        border-bottom: 2px solid var(--hr-border);
        padding-bottom: 4px;
        overflow-x: auto;
    }
    .bntm-tab {
        padding: 10px 18px;
        background: transparent;
        color: var(--hr-text-muted);
        font-weight: 600;
        text-decoration: none;
        border-radius: var(--hr-radius) var(--hr-radius) 0 0;
        transition: all 0.2s;
        font-size: 14px;
    }
    .bntm-tab:hover {
        color: var(--hr-primary);
        background: #e0e7ff;
    }
    .bntm-tab.active {
        color: var(--hr-primary);
        border-bottom: 3px solid var(--hr-primary);
    }
    .bntm-table-wrapper {
        background: var(--hr-surface);
        border: 1px solid var(--hr-border);
        border-radius: var(--hr-radius);
        padding-bottom: 120px; /* space for dropdowns */
        overflow-x: auto;
        box-shadow: var(--hr-shadow);
        margin-bottom: 24px;
    }
    .bntm-table {
        width: 100%;
        min-width: 800px;
        border-collapse: collapse;
        font-size: 14px;
    }
    .bntm-table th {
        background: #f8fafc;
        padding: 14px 18px;
        font-weight: 700;
        color: var(--hr-text-muted);
        text-transform: uppercase;
        font-size: 12px;
        border-bottom: 1px solid var(--hr-border);
        text-align: left;
        white-space: nowrap;
    }
    .bntm-table td {
        padding: 14px 18px;
        border-bottom: 1px solid var(--hr-border);
        color: var(--hr-text-main);
        word-break: normal;
        white-space: normal;
        vertical-align: middle;
    }
    .bntm-table tr:last-child td { border-bottom: none; }
    .bntm-table tr:hover td { background: #f8fafc; }
    
    .bntm-btn-primary, .bntm-btn-secondary, .bntm-btn-small, .bntm-btn-danger {
        border-radius: var(--hr-radius-sm);
        padding: 8px 16px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
        border: none;
        font-size: 13px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .bntm-btn-primary { background: var(--hr-primary); color: #fff; }
    .bntm-btn-primary:hover { background: var(--hr-primary-h); }
    .bntm-btn-secondary { background: var(--hr-surface); color: var(--hr-text-main); border: 1px solid var(--hr-border); }
    .bntm-btn-secondary:hover { background: #f1f5f9; }
    .bntm-btn-danger { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
    .bntm-btn-danger:hover { background: #fee2e2; }
    
    .bntm-input, select.bntm-input {
        border: 1px solid var(--hr-border);
        border-radius: var(--hr-radius-sm);
        padding: 8px 12px;
        font-size: 14px;
        background: #fff;
    }
    .bntm-input:focus { outline: none; border-color: var(--hr-primary); }

    /* Cascading Actions Dropdown */
    .hr-dropdown { position: relative; display: inline-block; margin-left: auto; }
    .hr-dropdown-btn { background: #fff; border: 1px solid var(--hr-border); color: var(--hr-text-main); padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 1px 2px rgba(0,0,0,0.02); transition: all 0.2s; }
    .hr-dropdown-btn:hover { background: #f8fafc; border-color: var(--hr-primary); color: var(--hr-primary); }
    .hr-dropdown-content { display: none; position: absolute; right: 0; top: 100%; margin-top: 4px; background: #fff; min-width: 140px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border: 1px solid var(--hr-border); border-radius: 6px; z-index: 9999; overflow: hidden; }
    .hr-dropdown-content.show { display: block; animation: hrDropdownFade 0.15s ease; }
    @keyframes hrDropdownFade { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: translateY(0); } }
    .hr-dropdown-content button { width: 100%; text-align: left; background: transparent; color: var(--hr-text-main); border: none; padding: 10px 16px; font-size: 13px; cursor: pointer; transition: background 0.15s; border-radius: 0; }
    .hr-dropdown-content button:hover { background: #f1f5f9; }
    .hr-dropdown-content button.danger { color: #dc2626 !important; }
    
    .bntm-table td:last-child { text-align: right; }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <div class="bntm-tabs">
        <?php foreach ($tabs as $key => $label):
            $active = ($type === $key) ? 'active' : '';
            $url    = add_query_arg('type', $key, get_permalink());
        ?>
            <a href="<?php echo esc_url($url); ?>" class="bntm-tab <?php echo $active; ?>">
                <?php echo esc_html($label); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php
    switch ($type) {
        case 'employees':
            echo bntm_hr_employees_view($can_manage);
            break;
        case 'attendance':
            echo bntm_hr_attendance_view($user_id, $can_manage);
            break;
        case 'leaves':
            echo bntm_hr_leaves_view($user_id, $can_manage);
            break;
        case 'overtime':
            echo bntm_hr_overtime_missing_view($user_id, $can_manage);
            break;
        case 'settings':
            echo bntm_hr_settings_view($can_manage);
            break;
        case 'payslips':
            echo bntm_hr_payslips_view($user_id, $can_manage);
            break;
        case 'profile':
            echo bntm_hr_my_profile_view($user_id);
            break;
        default:
            echo bntm_hr_dashboard_view($user_id, $can_manage);
            break;
    }

    $content = ob_get_clean();
    return bntm_universal_container('HR Portal', $content);
}

/* ---------- MY PROFILE VIEW ---------- */

function bntm_hr_my_profile_view($user_id) {
    $user = get_userdata($user_id);
    if (!$user) return '<p>User not found.</p>';

    ob_start();
    ?>
    <div class="bntm-form-section">
        <h3>My Profile</h3>
        <p style="color:#6b7280; margin-bottom:20px;">Update your personal details below. Your role and payroll settings are managed by your administrator.</p>

        <form id="my-profile-form" class="bntm-form">

            <div style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:20px; margin-bottom:20px;">
                <h4 style="margin:0 0 15px 0; color:#0369a1; display:flex; align-items:center; gap:8px;">
                    <?php echo bntm_hr_svg('person', 18); ?> Account Details
                </h4>
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>First Name *</label>
                        <input type="text" name="first_name" value="<?php echo esc_attr($user->first_name); ?>" required />
                    </div>
                    <div class="bntm-form-group">
                        <label>Last Name *</label>
                        <input type="text" name="last_name" value="<?php echo esc_attr($user->last_name); ?>" required />
                    </div>
                </div>
                <div class="bntm-form-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" value="<?php echo esc_attr($user->user_email); ?>" required />
                </div>
            </div>

            <div style="background:#fefce8; border:1px solid #fde047; border-radius:8px; padding:20px; margin-bottom:20px;">
                <h4 style="margin:0 0 15px 0; color:#854d0e; display:flex; align-items:center; gap:8px;">
                    <?php echo bntm_hr_svg('lock', 18); ?> Change Password
                </h4>
                <p style="font-size:13px; color:#92400e; margin-bottom:15px;">Leave blank to keep your current password.</p>
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" id="profile-new-password" placeholder="Enter new password" />
                    </div>
                    <div class="bntm-form-group" id="profile-confirm-group" style="display:none;">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" id="profile-confirm-password" placeholder="Confirm new password" />
                        <small id="profile-password-match" style="color:red; display:none;">Passwords do not match</small>
                    </div>
                </div>
            </div>

            <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; padding:20px; margin-bottom:20px;">
                <h4 style="margin:0 0 15px 0; color:#374151; display:flex; align-items:center; gap:8px;">
                    <?php echo bntm_hr_svg('clipboard', 18); ?> Personal Information
                </h4>
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone" value="<?php echo esc_attr(get_user_meta($user_id, 'bntm_phone', true)); ?>" placeholder="e.g., 09XX-XXX-XXXX" />
                    </div>
                    <div class="bntm-form-group">
                        <label>Date of Birth</label>
                        <input type="date" name="dob" value="<?php echo esc_attr(get_user_meta($user_id, 'bntm_dob', true)); ?>" />
                    </div>
                </div>
                <div class="bntm-form-group">
                    <label>Home Address</label>
                    <textarea name="address" rows="2" placeholder="Enter your home address"><?php echo esc_textarea(get_user_meta($user_id, 'bntm_address', true)); ?></textarea>
                </div>
            </div>

            <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:20px; margin-bottom:20px;">
                <h4 style="margin:0 0 15px 0; color:#991b1b; display:flex; align-items:center; gap:8px;">
                    <?php echo bntm_hr_svg('emergency', 18); ?> Emergency Contact
                </h4>
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Contact Name</label>
                        <input type="text" name="emergency_name" value="<?php echo esc_attr(get_user_meta($user_id, 'bntm_emergency_contact_name', true)); ?>" placeholder="Full name" />
                    </div>
                    <div class="bntm-form-group">
                        <label>Contact Phone</label>
                        <input type="tel" name="emergency_phone" value="<?php echo esc_attr(get_user_meta($user_id, 'bntm_emergency_contact_phone', true)); ?>" placeholder="e.g., 09XX-XXX-XXXX" />
                    </div>
                </div>
            </div>

            <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:20px; margin-bottom:20px;">
                <h4 style="margin:0 0 15px 0; color:#166534; display:flex; align-items:center; gap:8px;">
                    <?php echo bntm_hr_svg('building', 18); ?> Employment Information
                    <small style="font-weight:normal; font-size:12px; color:#6b7280;">(Read-only)</small>
                </h4>
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Department</label>
                        <input type="text" value="<?php echo esc_attr(get_user_meta($user_id, 'bntm_department', true) ?: '—'); ?>" disabled style="background:#e9ecef; cursor:not-allowed;" />
                    </div>
                    <div class="bntm-form-group">
                        <label>Position</label>
                        <input type="text" value="<?php echo esc_attr(get_user_meta($user_id, 'bntm_position', true) ?: '—'); ?>" disabled style="background:#e9ecef; cursor:not-allowed;" />
                    </div>
                </div>
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Role</label>
                        <input type="text" value="<?php echo esc_attr(ucfirst(get_user_meta($user_id, 'bntm_role', true) ?: '—')); ?>" disabled style="background:#e9ecef; cursor:not-allowed;" />
                    </div>
                    <div class="bntm-form-group">
                        <label>Hire Date</label>
                        <input type="text" value="<?php
                            $hire = get_user_meta($user_id, 'bntm_hire_date', true);
                            echo esc_attr($hire ? date('F d, Y', strtotime($hire)) : '—');
                        ?>" disabled style="background:#e9ecef; cursor:not-allowed;" />
                    </div>
                </div>
            </div>

            <div style="display:flex; gap:10px; align-items:center;">
                <button type="submit" class="bntm-btn-primary" id="profile-save-btn" style="display:flex;align-items:center;gap:6px;">
                    <?php echo bntm_hr_svg('save', 16); ?> Save Changes
                </button>
                <div id="profile-message" style="display:inline-block;"></div>
            </div>
        </form>
    </div>

    <script>
    jQuery(document).ready(function($) {
        const bntmAjax = {
            ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('bntm_hr_nonce'); ?>'
        };
        $('#profile-new-password').on('input', function() {
            if ($(this).val().length > 0) {
                $('#profile-confirm-group').show();
                $('#profile-confirm-password').prop('required', true);
            } else {
                $('#profile-confirm-group').hide();
                $('#profile-confirm-password').prop('required', false).val('');
                $('#profile-password-match').hide();
            }
            checkPasswordMatch();
        });
        $('#profile-confirm-password').on('input', checkPasswordMatch);
        function checkPasswordMatch() {
            const pw = $('#profile-new-password').val();
            const cpw = $('#profile-confirm-password').val();
            if (cpw.length > 0 && pw !== cpw) {
                $('#profile-password-match').show();
                $('#profile-save-btn').prop('disabled', true);
            } else {
                $('#profile-password-match').hide();
                $('#profile-save-btn').prop('disabled', false);
            }
        }
        $('#my-profile-form').on('submit', function(e) {
            e.preventDefault();
            const pw = $('#profile-new-password').val();
            const cpw = $('#profile-confirm-password').val();
            if (pw && pw !== cpw) { alert('Passwords do not match!'); return; }
            const $btn = $('#profile-save-btn');
            $btn.prop('disabled', true).text('Saving...');
            const formData = new FormData(this);
            formData.append('action', 'bntm_hr_update_my_profile');
            formData.append('nonce', bntmAjax.nonce);
            fetch(bntmAjax.ajax_url, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    const msgDiv = $('#profile-message');
                    if (data.success) {
                        msgDiv.html('<span style="color:green; font-weight:500;">Profile updated successfully!</span>');
                        $('#profile-new-password').val('');
                        $('#profile-confirm-password').val('');
                        $('#profile-confirm-group').hide();
                        setTimeout(() => msgDiv.html(''), 4000);
                    } else {
                        msgDiv.html('<span style="color:red;">' + (data.data ? data.data.message : 'An error occurred') + '</span>');
                    }
                })
                .catch(err => { $('#profile-message').html('<span style="color:red;">Error: ' + err.message + '</span>'); })
                .finally(() => { $btn.prop('disabled', false).html('<?php echo addslashes(bntm_hr_svg('save', 16)); ?> Save Changes'); });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

/* ---------- AJAX: UPDATE MY PROFILE ---------- */

add_action('wp_ajax_bntm_hr_update_my_profile', 'bntm_ajax_hr_update_my_profile');
function bntm_ajax_hr_update_my_profile() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    $user_id = get_current_user_id();
    if (!$user_id) { wp_send_json_error(['message' => 'Not logged in.']); }
    $first_name   = sanitize_text_field($_POST['first_name'] ?? '');
    $last_name    = sanitize_text_field($_POST['last_name'] ?? '');
    $email        = sanitize_email($_POST['email'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    if (empty($first_name) || empty($last_name)) { wp_send_json_error(['message' => 'First and last name are required.']); }
    if (empty($email) || !is_email($email)) { wp_send_json_error(['message' => 'A valid email address is required.']); }
    $existing = get_user_by('email', $email);
    if ($existing && $existing->ID !== $user_id) { wp_send_json_error(['message' => 'That email address is already in use.']); }
    $update_data = ['ID' => $user_id, 'user_email' => $email, 'first_name' => $first_name, 'last_name' => $last_name, 'display_name' => $first_name . ' ' . $last_name];
    if (!empty($new_password)) { $update_data['user_pass'] = $new_password; }
    $updated = wp_update_user($update_data);
    if (is_wp_error($updated)) { wp_send_json_error(['message' => $updated->get_error_message()]); }
    update_user_meta($user_id, 'bntm_phone',                   sanitize_text_field($_POST['phone'] ?? ''));
    update_user_meta($user_id, 'bntm_dob',                     sanitize_text_field($_POST['dob'] ?? ''));
    update_user_meta($user_id, 'bntm_address',                 sanitize_textarea_field($_POST['address'] ?? ''));
    update_user_meta($user_id, 'bntm_emergency_contact_name',  sanitize_text_field($_POST['emergency_name'] ?? ''));
    update_user_meta($user_id, 'bntm_emergency_contact_phone', sanitize_text_field($_POST['emergency_phone'] ?? ''));
    wp_send_json_success(['message' => 'Profile updated successfully!']);
}

/* =========================================================
   PAYSLIP DELETE
   ========================================================= */

add_action('wp_ajax_bntm_hr_delete_payslip', 'bntm_ajax_hr_delete_payslip');
function bntm_ajax_hr_delete_payslip() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    $current_user = wp_get_current_user();
    $is_wp_admin  = current_user_can('manage_options');
    $current_role = bntm_get_user_role($current_user->ID);
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) { wp_send_json_error(['message' => 'Permission denied.']); }
    $payslip_id = intval($_POST['payslip_id'] ?? 0);
    if (!$payslip_id) { wp_send_json_error(['message' => 'Invalid payslip ID.']); }
    global $wpdb; $prefix = $wpdb->prefix;
    $payslip = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$prefix}hr_payslips WHERE id = %d", $payslip_id));
    if (!$payslip) { wp_send_json_error(['message' => 'Payslip not found.']); }
    if ($payslip->is_imported) {
        $wpdb->delete($prefix . 'fn_transactions', ['reference_type' => 'payslip', 'reference_id' => $payslip_id], ['%s', '%d']);
        if (function_exists('bntm_fn_update_cashflow_summary')) { bntm_fn_update_cashflow_summary(); }
    }
    $deleted = $wpdb->delete($prefix . 'hr_payslips', ['id' => $payslip_id], ['%d']);
    if ($deleted) { wp_send_json_success(['message' => 'Payslip deleted successfully.']); }
    else { wp_send_json_error(['message' => 'Failed to delete payslip.']); }
}

/* =========================================================
   BANK / QR AJAX HANDLERS
   ========================================================= */

add_action('wp_ajax_bntm_hr_save_bank_details', 'bntm_ajax_hr_save_bank_details');
function bntm_ajax_hr_save_bank_details() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    $current_user = wp_get_current_user();
    $is_wp_admin  = current_user_can('manage_options');
    $current_role = bntm_get_user_role($current_user->ID);
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) { wp_send_json_error(['message' => 'Permission denied.']); }

    $employee_id   = intval($_POST['employee_id'] ?? 0);
    if (!$employee_id) { wp_send_json_error(['message' => 'Invalid employee.']); }

    update_user_meta($employee_id, 'bntm_bank_name',           sanitize_text_field($_POST['bank_name'] ?? ''));
    update_user_meta($employee_id, 'bntm_bank_account_name',   sanitize_text_field($_POST['bank_account_name'] ?? ''));
    update_user_meta($employee_id, 'bntm_bank_account_number', sanitize_text_field($_POST['bank_account_number'] ?? ''));
    update_user_meta($employee_id, 'bntm_bank_branch',         sanitize_text_field($_POST['bank_branch'] ?? ''));

    // Handle QR upload
    if (!empty($_FILES['qr_image']['tmp_name'])) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        // Compress: resize to max 400x400
        $tmp    = $_FILES['qr_image']['tmp_name'];
        $type   = $_FILES['qr_image']['type'];
        $info   = getimagesize($tmp);

        if ($info && in_array($info['mime'], ['image/jpeg','image/jpg','image/png','image/gif','image/webp'])) {
            $src_w  = $info[0]; $src_h = $info[1];
            $max    = 400;
            $ratio  = min($max / $src_w, $max / $src_h, 1);
            $new_w  = (int)($src_w * $ratio);
            $new_h  = (int)($src_h * $ratio);

            $dst = imagecreatetruecolor($new_w, $new_h);
            switch ($info['mime']) {
                case 'image/png':
                    $src_img = imagecreatefrompng($tmp);
                    imagealphablending($dst, false); imagesavealpha($dst, true);
                    break;
                case 'image/gif': $src_img = imagecreatefromgif($tmp); break;
                default: $src_img = imagecreatefromjpeg($tmp);
            }
            imagecopyresampled($dst, $src_img, 0, 0, 0, 0, $new_w, $new_h, $src_w, $src_h);

            ob_start();
            if ($info['mime'] === 'image/png') { imagepng($dst, null, 7); $mime = 'image/png'; }
            else { imagejpeg($dst, null, 80); $mime = 'image/jpeg'; }
            $img_data = ob_get_clean();
            imagedestroy($dst); imagedestroy($src_img);

            $b64 = 'data:' . $mime . ';base64,' . base64_encode($img_data);
            update_user_meta($employee_id, 'bntm_qr_image', $b64);
        }
    }

    // Handle QR remove
    if (!empty($_POST['remove_qr'])) {
        delete_user_meta($employee_id, 'bntm_qr_image');
    }

    wp_send_json_success(['message' => 'Bank details saved successfully!']);
}

add_action('wp_ajax_bntm_hr_get_bank_details', 'bntm_ajax_hr_get_bank_details');
function bntm_ajax_hr_get_bank_details() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    $current_user = wp_get_current_user();
    $is_wp_admin  = current_user_can('manage_options');
    $current_role = bntm_get_user_role($current_user->ID);
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) { wp_send_json_error(['message' => 'Permission denied.']); }

    $employee_id = intval($_POST['employee_id'] ?? 0);
    if (!$employee_id) { wp_send_json_error(['message' => 'Invalid employee.']); }

    wp_send_json_success([
        'bank_name'           => get_user_meta($employee_id, 'bntm_bank_name', true),
        'bank_account_name'   => get_user_meta($employee_id, 'bntm_bank_account_name', true),
        'bank_account_number' => get_user_meta($employee_id, 'bntm_bank_account_number', true),
        'bank_branch'         => get_user_meta($employee_id, 'bntm_bank_branch', true),
        'qr_image'            => get_user_meta($employee_id, 'bntm_qr_image', true),
    ]);
}

/* =========================================================
   AJAX: GET PAYSLIP PAYMENT DETAILS (for modal)
   ========================================================= */

add_action('wp_ajax_bntm_hr_get_payment_details', 'bntm_ajax_hr_get_payment_details');
function bntm_ajax_hr_get_payment_details() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    $current_user = wp_get_current_user();
    $is_wp_admin  = current_user_can('manage_options');
    $current_role = bntm_get_user_role($current_user->ID);
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) { wp_send_json_error(['message' => 'Permission denied.']); }

    $payslip_id = intval($_POST['payslip_id'] ?? 0);
    if (!$payslip_id) { wp_send_json_error(['message' => 'Invalid payslip.']); }

    global $wpdb; $prefix = $wpdb->prefix;
    $payslip = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$prefix}hr_payslips WHERE id = %d", $payslip_id));
    if (!$payslip) { wp_send_json_error(['message' => 'Payslip not found.']); }

    $employee = get_userdata($payslip->employee_id);
    $deductions_data  = json_decode($payslip->deductions_data ?? '{}', true) ?: [];
    $adjustments_data = json_decode($payslip->adjustments_data ?? '[]', true) ?: [];

    wp_send_json_success([
        'payslip' => [
            'id'               => $payslip->id,
            'period_start'     => date('M d, Y', strtotime($payslip->period_start)),
            'period_end'       => date('M d, Y', strtotime($payslip->period_end)),
            'total_hours'      => number_format(floatval($payslip->total_hours), 2),
            'basic_pay'        => number_format(floatval($payslip->basic_pay), 2),
            'overtime_pay'     => number_format(floatval($payslip->overtime_pay), 2),
            'total_deductions' => number_format(floatval($payslip->total_deductions), 2),
            'net_pay'          => number_format(floatval($payslip->net_pay), 2),
            'is_imported'      => $payslip->is_imported,
            'deductions'       => $deductions_data,
            'adjustments'      => $adjustments_data,
        ],
        'employee' => [
            'name'       => $employee ? $employee->display_name : 'Unknown',
            'position'   => get_user_meta($payslip->employee_id, 'bntm_position', true),
            'department' => get_user_meta($payslip->employee_id, 'bntm_department', true),
        ],
        'bank' => [
            'bank_name'           => get_user_meta($payslip->employee_id, 'bntm_bank_name', true),
            'bank_account_name'   => get_user_meta($payslip->employee_id, 'bntm_bank_account_name', true),
            'bank_account_number' => get_user_meta($payslip->employee_id, 'bntm_bank_account_number', true),
            'bank_branch'         => get_user_meta($payslip->employee_id, 'bntm_bank_branch', true),
            'qr_image'            => get_user_meta($payslip->employee_id, 'bntm_qr_image', true),
        ],
    ]);
}


/* ---------- KIOSK SHORTCODE ---------- */

function bntm_shortcode_hr_kiosk() {
    $current_user = wp_get_current_user();
    $is_wp_admin  = current_user_can('manage_options');
    $current_role = bntm_get_user_role($current_user->ID);
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) {
        return '<p>You do not have permission to access this page.</p>';
    }
    ob_start(); ?>
    <div class="bntm-kiosk-container" style="max-width:500px;margin:50px auto;text-align:center;">
        <h2 style="display:flex;align-items:center;justify-content:center;gap:10px;">
            <?php echo bntm_hr_svg('kiosk', 24); ?> Employee Clock In/Out
        </h2>
        <div class="bntm-form-section">
            <div class="bntm-form-group">
                <label>Employee ID or PIN</label>
                <input type="text" id="kiosk-employee-id" placeholder="Enter your ID or PIN" style="font-size:18px;text-align:center;" />
            </div>
            <div class="bntm-form-group">
                <button id="kiosk-clock-in" class="bntm-btn-primary" style="width:45%;margin-right:5%;">Clock In</button>
                <button id="kiosk-clock-out" class="bntm-btn-secondary" style="width:45%;">Clock Out</button>
            </div>
            <div id="kiosk-message"></div>
        </div>
    </div>
    <script>
    jQuery(document).ready(function($) {
        const bntmAjax = { ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>', nonce: '<?php echo wp_create_nonce('bntm_hr_kiosk_nonce'); ?>' };
        async function handleKioskAction(actionType) {
            const employeeIdInput = $('#kiosk-employee-id'); const messageDiv = $('#kiosk-message'); const employeeId = employeeIdInput.val().trim();
            if (!employeeId) { messageDiv.html('<p style="color:red;">Please enter your Employee ID or PIN.</p>'); return; }
            try {
                const response = await $.ajax({ url: bntmAjax.ajax_url, method: 'POST', data: { action: 'bntm_hr_kiosk_clock', nonce: bntmAjax.nonce, employee_id: employeeId, action_type: actionType } });
                if (response.success) { messageDiv.html('<p style="color:green;font-size:18px;font-weight:bold;">' + response.data.message + '</p>'); employeeIdInput.val(''); setTimeout(function() { messageDiv.html(''); }, 5000); }
                else { messageDiv.html('<p style="color:red;font-size:16px;">' + response.data.message + '</p>'); }
            } catch (error) { messageDiv.html('<p style="color:red;">An error occurred. Please try again.</p>'); }
        }
        $('#kiosk-clock-in').on('click', function() { handleKioskAction('in'); });
        $('#kiosk-clock-out').on('click', function() { handleKioskAction('out'); });
        $('#kiosk-employee-id').on('keypress', function(e) { if (e.key === 'Enter' || e.keyCode === 13) { e.preventDefault(); handleKioskAction('in'); } });
    });
    </script>
    <?php return ob_get_clean();
}

function bntm_ajax_hr_kiosk_clock() {
    check_ajax_referer('bntm_hr_kiosk_nonce', 'nonce');
    $employee_id = sanitize_text_field($_POST['employee_id']); $action_type = sanitize_text_field($_POST['action_type']);
    global $wpdb;
    $user = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'bntm_hr_pin' AND meta_value = %s", $employee_id));
    if (!$user) { $user_obj = get_user_by('id', intval($employee_id)); if (!$user_obj) { wp_send_json_error(['message' => 'Employee not found.']); } $user = $user_obj->ID; }
    $prefix = $wpdb->prefix;
    if ($action_type === 'in') {
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$prefix}hr_attendance WHERE employee_id = %d AND DATE(clock_in) = %s AND clock_out IS NULL", $user, current_time('Y-m-d')));
        if ($existing) { wp_send_json_error(['message' => 'Already clocked in today.']); }
        $result = $wpdb->insert($prefix . 'hr_attendance', ['rand_id' => bntm_rand_id(), 'employee_id' => $user, 'business_id' => 1, 'clock_in' => current_time('mysql'), 'status' => 'active'], ['%s', '%d', '%d', '%s', '%s']);
        if ($result) { $user_data = get_userdata($user); wp_send_json_success(['message' => 'Welcome ' . $user_data->display_name . '! Clocked in at ' . current_time('h:i A')]); }
        else { wp_send_json_error(['message' => 'Failed to clock in.']); }
    } else {
        $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$prefix}hr_attendance WHERE employee_id = %d AND DATE(clock_in) = %s AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1", $user, current_time('Y-m-d')));
        if (!$record) { wp_send_json_error(['message' => 'No active clock in record found.']); }
        $clock_out = current_time('mysql'); $total_hours = round((strtotime($clock_out) - strtotime($record->clock_in)) / 3600, 2);
        $result = $wpdb->update($prefix . 'hr_attendance', ['clock_out' => $clock_out, 'total_hours' => $total_hours, 'status' => 'completed'], ['id' => $record->id], ['%s', '%f', '%s'], ['%d']);
        if ($result !== false) { $user_data = get_userdata($user); wp_send_json_success(['message' => 'Goodbye ' . $user_data->display_name . '! Clocked out at ' . current_time('h:i A') . '. Total: ' . $total_hours . ' hrs']); }
        else { wp_send_json_error(['message' => 'Failed to clock out.']); }
    }
}
add_action('wp_ajax_bntm_hr_kiosk_clock',        'bntm_ajax_hr_kiosk_clock');
add_action('wp_ajax_nopriv_bntm_hr_kiosk_clock', 'bntm_ajax_hr_kiosk_clock');


/* ---------- SETTINGS VIEW ---------- */

function bntm_hr_settings_view($can_manage) {
    if (!$can_manage) { return '<p>You do not have permission to view this page.</p>'; }
    ob_start();
    $custom_roles      = bntm_get_setting('hr_custom_roles', '');
    $roles_array       = $custom_roles ? json_decode($custom_roles, true) : [];
    $leave_types       = bntm_get_setting('hr_leave_types', 'Sick Leave,Vacation,Personal,Bereavement');
    $work_hours        = bntm_get_setting('hr_work_hours', '8');
    $hourly_rate       = bntm_get_setting('hr_hourly_rate', '15.00');
    $lunch_break_hours = bntm_get_setting('hr_lunch_break_hours', '1');
    $ot_rate           = bntm_get_setting('hr_ot_rate', '1');
    $available_modules = bntm_get_available_modules();
    ?>
    <div class="bntm-form-section">
        <h3 style="display:flex;align-items:center;gap:8px;"><?php echo bntm_hr_svg('settings', 18); ?> HR Settings</h3>
    </div>
    <div class="bntm-form-section">
        <h3>Employee Roles &amp; Configurations</h3>
        <p>Configure custom employee roles with module access and payroll settings</p>
        <div id="roles-list">
            <?php if ($roles_array): foreach ($roles_array as $key => $role): ?>
                <div class="role-item" style="background:#f9fafb;padding:20px;border-radius:8px;margin-bottom:15px;border:2px solid #e5e7eb;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:15px;">
                        <div style="flex:1;">
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                                <strong style="font-size:16px;color:#1f2937;"><?php echo esc_html($role['label']); ?></strong>
                                <span style="background:#dbeafe;color:#1e40af;padding:2px 8px;border-radius:4px;font-size:12px;font-weight:600;"><?php echo esc_html($key); ?></span>
                            </div>
                            <div style="font-size:13px;color:#6b7280;margin-bottom:10px;">
                                <strong>Modules:</strong>
                                <?php $modules = isset($role['modules']) ? $role['modules'] : [];
                                if (!empty($modules)) { $module_names = []; foreach ($modules as $module_slug) { if (isset($available_modules[$module_slug])) $module_names[] = $available_modules[$module_slug]['name']; } echo esc_html(implode(', ', $module_names)); } else { echo '<span style="color:#ef4444;">No modules assigned</span>'; } ?>
                            </div>
                            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:15px;background:white;padding:12px;border-radius:6px;margin-top:10px;">
                                <div><div style="font-size:11px;color:#6b7280;text-transform:uppercase;">Hourly Rate</div><div style="font-size:16px;font-weight:700;color:#059669;">&#8369;<?php echo number_format(floatval($role['hourly_rate'] ?? $hourly_rate), 2); ?></div></div>
                                <div><div style="font-size:11px;color:#6b7280;text-transform:uppercase;">Max Daily Hours</div><div style="font-size:16px;font-weight:700;color:#2563eb;"><?php echo floatval($role['work_hours'] ?? $work_hours); ?>h</div></div>
                                <div><div style="font-size:11px;color:#6b7280;text-transform:uppercase;">Lunch Break</div><div style="font-size:16px;font-weight:700;color:#f59e0b;"><?php echo floatval($role['lunch_break_hours'] ?? $lunch_break_hours); ?>h</div></div>
                            </div>
                        </div>
                        <div style="display:flex;gap:8px;">
                            <button class="bntm-btn-small edit-role" data-key="<?php echo esc_attr($key); ?>">Edit</button>
                            <button class="bntm-btn-small bntm-btn-danger delete-role" data-key="<?php echo esc_attr($key); ?>">Delete</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; else: ?><p style="color:#6b7280;">No custom roles defined yet.</p><?php endif; ?>
        </div>
        <div style="margin-top:20px;border-top:2px solid #e5e7eb;padding-top:20px;">
            <h4 id="role-form-title">Add New Role</h4>
            <input type="hidden" id="edit-role-key" value="" />
            <div class="bntm-form-row">
                <div class="bntm-form-group"><label>Role Key (lowercase, no spaces) *</label><input type="text" id="new-role-key" placeholder="e.g., supervisor" /><small>Cannot be changed after creation</small></div>
                <div class="bntm-form-group"><label>Role Label *</label><input type="text" id="new-role-label" placeholder="e.g., Supervisor" /></div>
            </div>
            <div class="bntm-form-group">
                <label>Module Access *</label>
                <p style="font-size:13px;color:#6b7280;margin-bottom:10px;">Select which modules this role can access:</p>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:8px;">
                    <?php foreach ($available_modules as $module_slug => $module_data): ?>
                        <label style="display:flex;align-items:center;padding:8px;background:#f9fafb;border-radius:4px;cursor:pointer;"><input type="checkbox" class="role-module" value="<?php echo esc_attr($module_slug); ?>" style="margin-right:8px;width:unset;" /><span><?php echo esc_html($module_data['name']); ?></span></label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div style="background:#fef3c7;border:2px solid #fde047;border-radius:8px;padding:20px;margin-top:20px;">
                <h4 style="margin-top:0;color:#854d0e;">Payroll Settings for This Role</h4>
                <div class="bntm-form-row">
                    <div class="bntm-form-group"><label>Hourly Rate (&#8369;) *</label><input type="number" id="role-hourly-rate" step="0.01" min="0" placeholder="<?php echo $hourly_rate; ?>" /><small>Default: &#8369;<?php echo number_format($hourly_rate, 2); ?>/hour</small></div>
                    <div class="bntm-form-group"><label>Max Work Hours per Day *</label><input type="number" id="role-work-hours" step="0.5" min="1" max="24" placeholder="<?php echo $work_hours; ?>" /><small>Default: <?php echo $work_hours; ?> hours</small></div>
                    <div class="bntm-form-group"><label>Lunch Break Duration (hours) *</label><input type="number" id="role-lunch-break" step="0.5" min="0" max="2" placeholder="<?php echo $lunch_break_hours; ?>" /><small>Default: <?php echo $lunch_break_hours; ?> hour(s)</small></div>
                </div>
            </div>
            <div style="margin-top:20px;">
                <button id="save-role-btn" class="bntm-btn-primary">Save Role</button>
                <button id="cancel-edit-btn" class="bntm-btn-secondary" style="display:none;">Cancel</button>
            </div>
            <div id="role-message"></div>
        </div>
    </div>
    <div class="bntm-form-section">
        <h4>Global Default Settings</h4>
        <p style="color:#6b7280;font-size:14px;">Fallback values when roles do not have specific settings</p>
        <div class="bntm-form-row">
            <div class="bntm-form-group"><label>Default Work Hours per Day</label><input type="number" id="hr-work-hours" value="<?php echo esc_attr($work_hours); ?>" step="0.5" /></div>
            <div class="bntm-form-group"><label>Default Hourly Rate</label><input type="number" id="hr-hourly-rate" value="<?php echo esc_attr($hourly_rate); ?>" step="0.01" /></div>
            <div class="bntm-form-group"><label>Default Lunch Break (hours)</label><input type="number" id="hr-lunch-break-hours" value="<?php echo esc_attr($lunch_break_hours); ?>" step="0.5" min="0" max="2" /></div>
            <div class="bntm-form-group"><label>Default Overtime Rate</label><input type="number" id="hr-ot-rate" value="<?php echo esc_attr($ot_rate); ?>" step="0.1" min="0" max="2" /></div>
        </div>
        <button id="save-work-config" class="bntm-btn-primary">Save Global Defaults</button>
        <div id="work-config-message"></div>
    </div>
    <div class="bntm-form-section">
        <h4>Leave Types</h4>
        <div class="bntm-form-group">
            <label>Available Leave Types (comma-separated)</label>
            <input type="text" id="hr-leave-types" value="<?php echo esc_attr($leave_types); ?>" />
            <button id="save-leave-types" class="bntm-btn-primary" style="margin-top:10px;">Save Leave Types</button>
            <div id="leave-types-message"></div>
        </div>
    </div>
    <div class="bntm-form-section">
        <h4>Payroll Deductions</h4>
        <p>Configure standard deductions for payslip calculations</p>
        <div id="deductions-list">
            <?php
            $deductions = bntm_get_setting('hr_deductions', '');
            $deductions_array = $deductions ? json_decode($deductions, true) : [];
            if ($deductions_array): foreach ($deductions_array as $key => $deduction): ?>
                <div class="role-item" style="background:#f9fafb;padding:15px;border-radius:6px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <strong><?php echo esc_html($deduction['name']); ?></strong>
                        <div style="font-size:13px;color:#6b7280;margin-top:5px;">
                            Type: <?php echo esc_html(ucfirst($deduction['type'])); ?> |
                            <?php if ($deduction['type'] === 'percentage'): ?>Rate: <?php echo esc_html($deduction['value']); ?>%
                            <?php else: ?>Amount: &#8369;<?php echo esc_html(number_format($deduction['value'], 2)); ?><?php endif; ?>
                            <?php if (!empty($deduction['description'])): ?> | <?php echo esc_html($deduction['description']); ?><?php endif; ?>
                        </div>
                    </div>
                    <button class="bntm-btn-small bntm-btn-danger delete-deduction" data-key="<?php echo esc_attr($key); ?>">Delete</button>
                </div>
            <?php endforeach; else: ?><p style="color:#6b7280;">No deductions configured yet.</p><?php endif; ?>
        </div>
        <div style="margin-top:20px;border-top:1px solid #e5e7eb;padding-top:20px;">
            <h4>Add New Deduction</h4>
            <div class="bntm-form-group"><label>Deduction Name *</label><input type="text" id="deduction-name" placeholder="e.g., SSS, PhilHealth, Pag-IBIG, Tax" /></div>
            <div class="bntm-form-row">
                <div class="bntm-form-group"><label>Type *</label><select id="deduction-type"><option value="percentage">Percentage (%)</option><option value="fixed">Fixed Amount (&#8369;)</option></select></div>
                <div class="bntm-form-group"><label>Value *</label><input type="number" id="deduction-value" step="0.01" placeholder="e.g., 3.63 or 100.00" /></div>
            </div>
            <div class="bntm-form-group"><label>Description (Optional)</label><input type="text" id="deduction-description" placeholder="e.g., Mandatory government contribution" /></div>
            <button id="add-deduction-btn" class="bntm-btn-primary">Add Deduction</button>
            <div id="deduction-message"></div>
        </div>
    </div>
    <script>
    jQuery(document).ready(function($) {
        const bntmAjax = { ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>', nonce: '<?php echo wp_create_nonce('bntm_hr_nonce'); ?>' };
        let isEditMode = false;
        $(document).on('click', '.edit-role', function(e) {
            e.preventDefault(); const roleKey = $(this).data('key');
            $.post(bntmAjax.ajax_url, { action: 'bntm_hr_get_role', nonce: bntmAjax.nonce, role_key: roleKey }).done(function(response) {
                if (response.success && response.data) {
                    const role = response.data; isEditMode = true; $('#edit-role-key').val(roleKey); $('#role-form-title').text('Edit Role: ' + role.label);
                    $('#new-role-key').val(roleKey).prop('disabled', true); $('#new-role-label').val(role.label);
                    $('#role-hourly-rate').val(role.hourly_rate || ''); $('#role-work-hours').val(role.work_hours || ''); $('#role-lunch-break').val(role.lunch_break_hours || '');
                    $('#save-role-btn').text('Update Role'); $('#cancel-edit-btn').show(); $('.role-module').prop('checked', false);
                    if (role.modules) { role.modules.forEach(function(module) { $('.role-module[value="' + module + '"]').prop('checked', true); }); }
                    $('html, body').animate({ scrollTop: $('#role-form-title').offset().top - 100 }, 500);
                }
            });
        });
        $('#cancel-edit-btn').on('click', function() {
            isEditMode = false; $('#edit-role-key').val(''); $('#role-form-title').text('Add New Role');
            $('#new-role-key').val('').prop('disabled', false); $('#new-role-label').val(''); $('#role-hourly-rate').val(''); $('#role-work-hours').val(''); $('#role-lunch-break').val('');
            $('.role-module').prop('checked', false); $('#save-role-btn').text('Save Role'); $(this).hide(); $('#role-message').html('');
        });
        $('#save-role-btn').on('click', function(e) {
            e.preventDefault(); const roleKey = isEditMode ? $('#edit-role-key').val() : $('#new-role-key').val().trim();
            const roleLabel = $('#new-role-label').val().trim(); const modules = [];
            $('.role-module:checked').each(function() { modules.push($(this).val()); });
            if (!roleKey || !roleLabel) { $('#role-message').html('<p style="color:red;">Role key and label are required.</p>'); return; }
            if (modules.length === 0) { $('#role-message').html('<p style="color:red;">Please select at least one module.</p>'); return; }
            const $btn = $(this); $btn.prop('disabled', true).text(isEditMode ? 'Updating...' : 'Adding...');
            $.post(bntmAjax.ajax_url, { action: isEditMode ? 'bntm_hr_update_role' : 'bntm_hr_add_role', nonce: bntmAjax.nonce, role_key: roleKey, role_label: roleLabel, hourly_rate: $('#role-hourly-rate').val(), work_hours: $('#role-work-hours').val(), lunch_break_hours: $('#role-lunch-break').val(), modules: modules })
            .done(function(response) {
                if (typeof response === 'string') { try { response = JSON.parse(response); } catch(e) { $('#role-message').html('<p style="color:red;">Invalid response format</p>'); $btn.prop('disabled', false).text(isEditMode ? 'Update Role' : 'Save Role'); return; } }
                if (response && response.success) { $('#role-message').html('<p style="color:green;">' + ((response.data && response.data.message) || 'Operation successful') + '</p>'); setTimeout(function() { location.reload(); }, 1500); }
                else { $('#role-message').html('<p style="color:red;">' + ((response.data && response.data.message) || 'An error occurred') + '</p>'); $btn.prop('disabled', false).text(isEditMode ? 'Update Role' : 'Save Role'); }
            }).fail(function(xhr, status, error) { $('#role-message').html('<p style="color:red;">AJAX Error: ' + error + '</p>'); $btn.prop('disabled', false).text(isEditMode ? 'Update Role' : 'Save Role'); });
        });
        $(document).on('click', '.delete-role', function(e) {
            e.preventDefault(); if (!confirm('Are you sure you want to delete this role?')) return;
            const $btn = $(this); const roleKey = $btn.data('key'); $btn.prop('disabled', true).text('Deleting...');
            $.post(bntmAjax.ajax_url, { action: 'bntm_hr_delete_role', nonce: bntmAjax.nonce, role_key: roleKey })
            .done(function(response) {
                if (typeof response === 'string') { try { response = JSON.parse(response); } catch(e) { alert('Invalid response format'); $btn.prop('disabled', false).text('Delete'); return; } }
                alert((response.data && response.data.message) || (response.success ? 'Operation completed' : 'An error occurred'));
                if (response && response.success) location.reload(); else $btn.prop('disabled', false).text('Delete');
            }).fail(function(xhr, status, error) { alert('AJAX Error: ' + error); $btn.prop('disabled', false).text('Delete'); });
        });
        $('#save-leave-types').on('click', function(e) {
            e.preventDefault(); const $btn = $(this); const leaveTypes = $('#hr-leave-types').val();
            if (!leaveTypes || leaveTypes.trim() === '') { $('#leave-types-message').html('<p style="color:red;">Leave types cannot be empty.</p>'); return; }
            $btn.prop('disabled', true).text('Saving...');
            $.post(bntmAjax.ajax_url, { action: 'bntm_hr_save_leave_types', nonce: bntmAjax.nonce, leave_types: leaveTypes })
            .done(function(response) {
                if (typeof response === 'string') { try { response = JSON.parse(response); } catch(e) { return; } }
                if (response && response.success) { $('#leave-types-message').html('<p style="color:green;">' + ((response.data && response.data.message) || 'Saved!') + '</p>'); setTimeout(function() { $('#leave-types-message').html(''); }, 3000); }
                else { $('#leave-types-message').html('<p style="color:red;">' + ((response.data && response.data.message) || 'An error occurred') + '</p>'); }
            }).always(function() { $btn.prop('disabled', false).text('Save Leave Types'); });
        });
        $('#add-deduction-btn').on('click', function(e) {
            e.preventDefault(); const name = $('#deduction-name').val().trim(); const type = $('#deduction-type').val(); const value = $('#deduction-value').val(); const description = $('#deduction-description').val().trim();
            if (!name || !value || parseFloat(value) < 0) { $('#deduction-message').html('<p style="color:red;">Please fill in all required fields with valid values.</p>'); return; }
            const $btn = $(this); $btn.prop('disabled', true).text('Adding...');
            $.post(bntmAjax.ajax_url, { action: 'bntm_hr_add_deduction', nonce: bntmAjax.nonce, name: name, type: type, value: value, description: description })
            .done(function(response) {
                if (typeof response === 'string') { try { response = JSON.parse(response); } catch(e) { return; } }
                if (response && response.success) { $('#deduction-message').html('<p style="color:green;">' + response.data.message + '</p>'); setTimeout(function() { location.reload(); }, 1500); }
                else { $('#deduction-message').html('<p style="color:red;">' + (response.data ? response.data.message : 'Error') + '</p>'); $btn.prop('disabled', false).text('Add Deduction'); }
            }).fail(function() { $btn.prop('disabled', false).text('Add Deduction'); });
        });
        $(document).on('click', '.delete-deduction', function(e) {
            e.preventDefault(); if (!confirm('Delete this deduction?')) return;
            const $btn = $(this); const key = $btn.data('key'); $btn.prop('disabled', true).text('Deleting...');
            $.post(bntmAjax.ajax_url, { action: 'bntm_hr_delete_deduction', nonce: bntmAjax.nonce, key: key })
            .done(function(response) {
                if (typeof response === 'string') { try { response = JSON.parse(response); } catch(e) { alert('Invalid response'); return; } }
                alert(response.data ? response.data.message : 'Operation completed');
                if (response && response.success) location.reload(); else $btn.prop('disabled', false).text('Delete');
            });
        });
        $('#save-work-config').on('click', function(e) {
            e.preventDefault(); const $btn = $(this); const workHours = $('#hr-work-hours').val(); const hourlyRate = $('#hr-hourly-rate').val(); const lunchBreakHours = $('#hr-lunch-break-hours').val(); const otrate = $('#hr-ot-rate').val();
            if (!workHours || parseFloat(workHours) <= 0) { $('#work-config-message').html('<p style="color:red;">Work hours must be greater than 0.</p>'); return; }
            if (!hourlyRate || parseFloat(hourlyRate) < 0) { $('#work-config-message').html('<p style="color:red;">Hourly rate cannot be negative.</p>'); return; }
            $btn.prop('disabled', true).text('Saving...');
            $.post(bntmAjax.ajax_url, { action: 'bntm_hr_save_work_config', nonce: bntmAjax.nonce, work_hours: workHours, hourly_rate: hourlyRate, ot_rate: otrate, lunch_break_hours: lunchBreakHours })
            .done(function(response) {
                if (typeof response === 'string') { try { response = JSON.parse(response); } catch(e) { return; } }
                if (response && response.success) { $('#work-config-message').html('<p style="color:green;">' + ((response.data && response.data.message) || 'Saved!') + '</p>'); setTimeout(function() { $('#work-config-message').html(''); }, 3000); }
                else { $('#work-config-message').html('<p style="color:red;">' + ((response.data && response.data.message) || 'An error occurred') + '</p>'); }
            }).always(function() { $btn.prop('disabled', false).text('Save Global Defaults'); });
        });
    });
    </script>
    <?php return ob_get_clean();
}


/* Settings AJAX handlers (unchanged from original) */
add_action('wp_ajax_bntm_hr_update_role', 'bntm_ajax_hr_update_role');
function bntm_ajax_hr_update_role() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    $current_user = wp_get_current_user(); $is_wp_admin = current_user_can('manage_options'); $current_role = bntm_get_user_role($current_user->ID);
    if (!$is_wp_admin && !in_array($current_role, ['owner'])) { wp_send_json_error(['message' => 'Only owners can manage roles.']); }
    $role_key = isset($_POST['role_key']) ? sanitize_key($_POST['role_key']) : ''; $role_label = isset($_POST['role_label']) ? sanitize_text_field($_POST['role_label']) : '';
    $modules = isset($_POST['modules']) && is_array($_POST['modules']) ? array_map('sanitize_text_field', $_POST['modules']) : [];
    $hourly_rate = isset($_POST['hourly_rate']) && $_POST['hourly_rate'] !== '' ? floatval($_POST['hourly_rate']) : null;
    $work_hours = isset($_POST['work_hours']) && $_POST['work_hours'] !== '' ? floatval($_POST['work_hours']) : null;
    $lunch_break_hours = isset($_POST['lunch_break_hours']) && $_POST['lunch_break_hours'] !== '' ? floatval($_POST['lunch_break_hours']) : null;
    if (empty($role_key) || empty($role_label)) { wp_send_json_error(['message' => 'Role key and label are required.']); }
    if (empty($modules)) { wp_send_json_error(['message' => 'At least one module must be selected.']); }
    $custom_roles = bntm_get_setting('hr_custom_roles', ''); $roles_array = $custom_roles ? json_decode($custom_roles, true) : [];
    if (!is_array($roles_array)) $roles_array = [];
    if (!isset($roles_array[$role_key])) { wp_send_json_error(['message' => 'Role not found.']); }
    $roles_array[$role_key] = ['label' => $role_label, 'modules' => $modules, 'hourly_rate' => $hourly_rate, 'work_hours' => $work_hours, 'lunch_break_hours' => $lunch_break_hours];
    bntm_set_setting('hr_custom_roles', json_encode($roles_array));
    wp_send_json_success(['message' => 'Role updated successfully!']);
}

add_action('wp_ajax_bntm_hr_get_role', 'bntm_ajax_hr_get_role');
function bntm_ajax_hr_get_role() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    $current_user = wp_get_current_user(); $is_wp_admin = current_user_can('manage_options'); $current_role = bntm_get_user_role($current_user->ID);
    if (!$is_wp_admin && !in_array($current_role, ['owner'])) { wp_send_json_error(['message' => 'Permission denied.']); }
    $role_key = isset($_POST['role_key']) ? sanitize_key($_POST['role_key']) : '';
    if (empty($role_key)) { wp_send_json_error(['message' => 'Role key is required.']); }
    $custom_roles = bntm_get_setting('hr_custom_roles', ''); $roles_array = $custom_roles ? json_decode($custom_roles, true) : [];
    if (isset($roles_array[$role_key])) wp_send_json_success($roles_array[$role_key]);
    else wp_send_json_error(['message' => 'Role not found.']);
}

add_action('wp_ajax_bntm_hr_add_role', 'bntm_ajax_hr_add_role');
function bntm_ajax_hr_add_role() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    $current_user = wp_get_current_user(); $is_wp_admin = current_user_can('manage_options'); $current_role = bntm_get_user_role($current_user->ID);
    if (!$is_wp_admin && !in_array($current_role, ['owner'])) { wp_send_json_error(['message' => 'Only owners can manage roles.']); }
    $role_key = isset($_POST['role_key']) ? sanitize_key($_POST['role_key']) : ''; $role_label = isset($_POST['role_label']) ? sanitize_text_field($_POST['role_label']) : '';
    $modules = isset($_POST['modules']) && is_array($_POST['modules']) ? array_map('sanitize_text_field', $_POST['modules']) : [];
    $hourly_rate = isset($_POST['hourly_rate']) && $_POST['hourly_rate'] !== '' ? floatval($_POST['hourly_rate']) : null;
    $work_hours = isset($_POST['work_hours']) && $_POST['work_hours'] !== '' ? floatval($_POST['work_hours']) : null;
    $lunch_break_hours = isset($_POST['lunch_break_hours']) && $_POST['lunch_break_hours'] !== '' ? floatval($_POST['lunch_break_hours']) : null;
    if (empty($role_key) || empty($role_label)) { wp_send_json_error(['message' => 'Role key and label are required.']); }
    if (empty($modules)) { wp_send_json_error(['message' => 'At least one module must be selected.']); }
    if (in_array($role_key, ['staff', 'manager', 'owner'])) { wp_send_json_error(['message' => 'Cannot use default role keys (staff, manager, owner).']); }
    $custom_roles = bntm_get_setting('hr_custom_roles', ''); $roles_array = $custom_roles ? json_decode($custom_roles, true) : [];
    if (!is_array($roles_array)) $roles_array = [];
    if (isset($roles_array[$role_key])) { wp_send_json_error(['message' => 'Role key already exists. Use a different key.']); }
    $roles_array[$role_key] = ['label' => $role_label, 'modules' => $modules, 'hourly_rate' => $hourly_rate, 'work_hours' => $work_hours, 'lunch_break_hours' => $lunch_break_hours];
    bntm_set_setting('hr_custom_roles', json_encode($roles_array));
    wp_send_json_success(['message' => 'Role added successfully!']);
}

add_action('wp_ajax_bntm_hr_save_leave_types', 'bntm_ajax_hr_save_leave_types');
function bntm_ajax_hr_save_leave_types() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    $current_user = wp_get_current_user(); $is_wp_admin = current_user_can('manage_options'); $current_role = bntm_get_user_role($current_user->ID);
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) { wp_send_json_error(['message' => 'Permission denied.']); }
    $leave_types = isset($_POST['leave_types']) ? sanitize_text_field($_POST['leave_types']) : '';
    if (empty($leave_types)) { wp_send_json_error(['message' => 'Leave types cannot be empty.']); }
    bntm_set_setting('hr_leave_types', $leave_types);
    wp_send_json_success(['message' => 'Leave types saved successfully!']);
}

add_action('wp_ajax_bntm_hr_save_work_config', 'bntm_ajax_hr_save_work_config');
function bntm_ajax_hr_save_work_config() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    $current_user = wp_get_current_user(); $is_wp_admin = current_user_can('manage_options'); $current_role = bntm_get_user_role($current_user->ID);
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) { wp_send_json_error(['message' => 'Permission denied.']); }
    $work_hours = isset($_POST['work_hours']) ? floatval($_POST['work_hours']) : 8;
    $otrate     = isset($_POST['ot_rate']) ? floatval($_POST['ot_rate']) : 1;
    $hourly_rate = isset($_POST['hourly_rate']) ? floatval($_POST['hourly_rate']) : 15.00;
    $lunch_break_hours = isset($_POST['lunch_break_hours']) ? floatval($_POST['lunch_break_hours']) : 1;
    if ($work_hours <= 0) { wp_send_json_error(['message' => 'Work hours must be greater than 0.']); }
    if ($hourly_rate < 0) { wp_send_json_error(['message' => 'Hourly rate cannot be negative.']); }
    bntm_set_setting('hr_work_hours', $work_hours);
    bntm_set_setting('hr_hourly_rate', $hourly_rate);
    bntm_set_setting('hr_ot_rate', $otrate);
    bntm_set_setting('hr_lunch_break_hours', $lunch_break_hours);
    wp_send_json_success(['message' => 'Configuration saved successfully!']);
}


/* ---------- DASHBOARD VIEW ---------- */

function bntm_hr_dashboard_view($user_id, $can_manage) {
    global $wpdb; $prefix = $wpdb->prefix;
    $kiosk_page = get_page_by_path('hr-kiosk/');
    $kiosk_url  = $kiosk_page ? get_permalink($kiosk_page) : '';
    ob_start();
    if ($can_manage):
        $total_employees  = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users} WHERE ID IN (SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'bntm_role' AND meta_value LIKE '%employee%')");
        $pending_leaves   = $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}hr_leave_requests WHERE status = 'pending'");
        $pending_overtime = $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}hr_overtime WHERE status = 'pending'");
        $pending_missing  = $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}hr_missing_logs WHERE status = 'pending'");
        $today_attendance = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$prefix}hr_attendance WHERE DATE(clock_in) = %s", current_time('Y-m-d')));
        ?>
        <div class="bntm-form-section">
            <h3>HR Overview</h3>
            <?php if ($kiosk_url): ?>
            <div class="bntm-form-section" style="background:#eff6ff;border-left:4px solid #3b82f6;">
                <h3 style="display:flex;align-items:center;gap:8px;"><?php echo bntm_hr_svg('kiosk', 18); ?> Kiosk Page</h3>
                <div style="display:flex;align-items:center;gap:15px;flex-wrap:wrap;">
                    <input type="text" id="kiosk-url" value="<?php echo esc_url($kiosk_url); ?>" readonly style="flex:1;min-width:300px;padding:10px;border:1px solid #d1d5db;border-radius:6px;background:white;">
                    <button class="bntm-btn-secondary" id="copy-kiosk-url" style="display:flex;align-items:center;gap:6px;"><?php echo bntm_hr_svg('copy', 14); ?> Copy Link</button>
                    <a href="<?php echo esc_url($kiosk_url); ?>" target="_blank" class="bntm-btn-primary" style="display:flex;align-items:center;gap:6px;"><?php echo bntm_hr_svg('kiosk', 14); ?> Open Kiosk</a>
                </div>
            </div>
            <?php endif; ?>
            <div class="bntm-form-row">
                <div class="bntm-stat-card" style="background:#dbeafe;padding:20px;border-radius:8px;"><h4 style="margin:0 0 10px 0;display:flex;align-items:center;gap:6px;"><?php echo bntm_hr_svg('users', 16); ?> Total Employees</h4><p style="font-size:32px;margin:0;font-weight:bold;"><?php echo $total_employees; ?></p></div>
                <div class="bntm-stat-card" style="background:#fef3c7;padding:20px;border-radius:8px;"><h4 style="margin:0 0 10px 0;display:flex;align-items:center;gap:6px;"><?php echo bntm_hr_svg('calendar', 16); ?> Pending Leaves</h4><p style="font-size:32px;margin:0;font-weight:bold;"><?php echo $pending_leaves; ?></p></div>
                <div class="bntm-stat-card" style="background:#d1fae5;padding:20px;border-radius:8px;"><h4 style="margin:0 0 10px 0;display:flex;align-items:center;gap:6px;"><?php echo bntm_hr_svg('clock', 16); ?> Today's Attendance</h4><p style="font-size:32px;margin:0;font-weight:bold;"><?php echo $today_attendance; ?></p></div>
                <div class="bntm-stat-card" style="background:#fed7aa;padding:20px;border-radius:8px;"><h4 style="margin:0 0 10px 0;display:flex;align-items:center;gap:6px;"><?php echo bntm_hr_svg('clock', 16); ?> Pending Overtime</h4><p style="font-size:32px;margin:0;font-weight:bold;"><?php echo $pending_overtime; ?></p></div>
                <div class="bntm-stat-card" style="background:#fce7f3;padding:20px;border-radius:8px;"><h4 style="margin:0 0 10px 0;display:flex;align-items:center;gap:6px;"><?php echo bntm_hr_svg('edit', 16); ?> Pending Missing Logs</h4><p style="font-size:32px;margin:0;font-weight:bold;"><?php echo $pending_missing; ?></p></div>
            </div>
        </div>
        <?php
        // Recent leave requests
        $recent_leaves = $wpdb->get_results("SELECT * FROM {$prefix}hr_leave_requests ORDER BY created_at DESC LIMIT 5");
        ?>
        <div class="bntm-form-section"><h3>Recent Leave Requests</h3>
            <?php if ($recent_leaves): ?>
            <div class="bntm-table-wrapper"><table class="bntm-table"><thead><tr><th>Employee</th><th>Type</th><th>Dates</th><th>Status</th><th>Actions</th></tr></thead><tbody>
                <?php foreach ($recent_leaves as $leave): $employee = get_userdata($leave->employee_id); $status_class = $leave->status === 'approved' ? 'bntm-notice-success' : ($leave->status === 'rejected' ? 'bntm-notice-error' : ''); ?>
                    <tr><td><?php echo esc_html($employee->display_name); ?></td><td><?php echo esc_html($leave->leave_type); ?></td><td><?php echo esc_html($leave->start_date . ' to ' . $leave->end_date); ?></td>
                    <td><span class="<?php echo $status_class; ?>" style="padding:4px 8px;border-radius:4px;display:inline-block;"><?php echo esc_html($leave->status); ?></span></td>
                    <td><?php if ($leave->status === 'pending'): ?><button class="bntm-btn-small approve-leave" data-id="<?php echo $leave->id; ?>" style="display:inline-flex;align-items:center;gap:4px;"><?php echo bntm_hr_svg('check', 12); ?> Approve</button><button class="bntm-btn-small bntm-btn-danger reject-leave" data-id="<?php echo $leave->id; ?>" style="display:inline-flex;align-items:center;gap:4px;"><?php echo bntm_hr_svg('close', 12); ?> Reject</button><?php endif; ?></td></tr>
                <?php endforeach; ?></tbody></table></div>
            <?php else: ?><p>No recent leave requests.</p><?php endif; ?></div>
        <?php
        $recent_overtime = $wpdb->get_results("SELECT o.*, u.display_name FROM {$prefix}hr_overtime o LEFT JOIN {$wpdb->users} u ON o.employee_id = u.ID ORDER BY o.created_at DESC LIMIT 5");
        ?>
        <div class="bntm-form-section"><h3>Recent Overtime Requests</h3>
            <?php if ($recent_overtime): ?>
            <div class="bntm-table-wrapper"><table class="bntm-table"><thead><tr><th>Employee</th><th>Date</th><th>Time</th><th>Hours</th><th>Status</th><th>Actions</th></tr></thead><tbody>
                <?php foreach ($recent_overtime as $ot): $status_class = $ot->status === 'approved' ? 'bntm-notice-success' : ($ot->status === 'rejected' ? 'bntm-notice-error' : ''); $start = DateTime::createFromFormat('H:i:s', $ot->start_time)->format('h:i A'); $end = DateTime::createFromFormat('H:i:s', $ot->end_time)->format('h:i A'); ?>
                    <tr><td><?php echo esc_html($ot->display_name); ?></td><td><?php echo esc_html(date('M d, Y', strtotime($ot->overtime_date))); ?></td><td><?php echo esc_html($start . ' - ' . $end); ?></td><td><?php echo esc_html($ot->total_hours . ' hrs'); ?></td>
                    <td><span class="<?php echo $status_class; ?>" style="padding:4px 8px;border-radius:4px;display:inline-block;"><?php echo esc_html(ucfirst($ot->status)); ?></span></td>
                    <td><?php if ($ot->status === 'pending'): ?><button class="bntm-btn-small approve-overtime" data-id="<?php echo $ot->id; ?>" style="display:inline-flex;align-items:center;gap:4px;"><?php echo bntm_hr_svg('check', 12); ?> Approve</button><button class="bntm-btn-small bntm-btn-danger reject-overtime" data-id="<?php echo $ot->id; ?>" style="display:inline-flex;align-items:center;gap:4px;"><?php echo bntm_hr_svg('close', 12); ?> Reject</button><?php endif; ?></td></tr>
                <?php endforeach; ?></tbody></table></div>
            <?php else: ?><p>No recent overtime requests.</p><?php endif; ?></div>
        <?php
        $recent_missing = $wpdb->get_results("SELECT m.*, u.display_name FROM {$prefix}hr_missing_logs m LEFT JOIN {$wpdb->users} u ON m.employee_id = u.ID ORDER BY m.created_at DESC LIMIT 5");
        ?>
        <div class="bntm-form-section"><h3>Recent Missing Logs</h3>
            <?php if ($recent_missing): ?>
            <div class="bntm-table-wrapper"><table class="bntm-table"><thead><tr><th>Employee</th><th>Date</th><th>Type</th><th>Clock In</th><th>Clock Out</th><th>Status</th><th>Actions</th></tr></thead><tbody>
                <?php foreach ($recent_missing as $log): $status_class = $log->status === 'approved' ? 'bntm-notice-success' : ($log->status === 'rejected' ? 'bntm-notice-error' : ''); $type_label = $log->log_type === 'clock_in' ? 'Missing Clock In' : ($log->log_type === 'clock_out' ? 'Missing Clock Out' : 'Missing Both'); $clock_in = $log->clock_in_time ? DateTime::createFromFormat('H:i:s', $log->clock_in_time)->format('h:i A') : '-'; $clock_out = $log->clock_out_time ? DateTime::createFromFormat('H:i:s', $log->clock_out_time)->format('h:i A') : '-'; ?>
                    <tr><td><?php echo esc_html($log->display_name); ?></td><td><?php echo esc_html(date('M d, Y', strtotime($log->log_date))); ?></td><td><?php echo esc_html($type_label); ?></td><td><?php echo esc_html($clock_in); ?></td><td><?php echo esc_html($clock_out); ?></td>
                    <td><span class="<?php echo $status_class; ?>" style="padding:4px 8px;border-radius:4px;display:inline-block;"><?php echo esc_html(ucfirst($log->status)); ?></span></td>
                    <td><?php if ($log->status === 'pending'): ?><button class="bntm-btn-small approve-missing" data-id="<?php echo $log->id; ?>" style="display:inline-flex;align-items:center;gap:4px;"><?php echo bntm_hr_svg('check', 12); ?> Approve</button><button class="bntm-btn-small bntm-btn-danger reject-missing" data-id="<?php echo $log->id; ?>" style="display:inline-flex;align-items:center;gap:4px;"><?php echo bntm_hr_svg('close', 12); ?> Reject</button><?php endif; ?></td></tr>
                <?php endforeach; ?></tbody></table></div>
            <?php else: ?><p>No recent missing logs.</p><?php endif; ?></div>
    <?php else:
        $user_data = get_userdata($user_id);
        $today_attendance_rec = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$prefix}hr_attendance WHERE employee_id = %d AND DATE(clock_in) = %s ORDER BY clock_in DESC LIMIT 1", $user_id, current_time('Y-m-d')));
        $approved_leaves  = $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(total_days), 0) FROM {$prefix}hr_leave_requests WHERE employee_id = %d AND status = 'approved' AND YEAR(start_date) = YEAR(CURDATE())", $user_id));
        $pending_overtime = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$prefix}hr_overtime WHERE employee_id = %d AND status = 'pending'", $user_id));
        $pending_missing  = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$prefix}hr_missing_logs WHERE employee_id = %d AND status = 'pending'", $user_id));
        ?>
        <div class="bntm-form-section"><h3>Welcome, <?php echo esc_html($user_data->display_name); ?></h3>
            <div class="bntm-form-group">
                <button id="quick-clock-in" class="bntm-btn-primary" style="display:inline-flex;align-items:center;gap:6px;"><?php echo bntm_hr_svg('clock', 16); ?> Clock In</button>
                <button id="quick-clock-out" class="bntm-btn-secondary" style="display:inline-flex;align-items:center;gap:6px;"><?php echo bntm_hr_svg('clock', 16); ?> Clock Out</button>
                <div id="clock-message"></div>
            </div></div>
        <div class="bntm-form-section"><h3>Today's Status</h3>
            <?php if ($today_attendance_rec): ?><p><strong>Clock In:</strong> <?php echo esc_html(date('h:i A', strtotime($today_attendance_rec->clock_in))); ?></p>
            <?php if ($today_attendance_rec->clock_out): ?><p><strong>Clock Out:</strong> <?php echo esc_html(date('h:i A', strtotime($today_attendance_rec->clock_out))); ?></p><p><strong>Total Hours:</strong> <?php echo esc_html($today_attendance_rec->total_hours); ?></p>
            <?php else: ?><p><em>Currently clocked in</em></p><?php endif; ?>
            <?php else: ?><p>No attendance record for today.</p><?php endif; ?></div>
        <div class="bntm-form-section"><h3>Leave Summary</h3><p><strong>Days Used This Year:</strong> <?php echo esc_html($approved_leaves); ?></p></div>
        <div class="bntm-form-section"><h3>My Pending Requests</h3><div class="bntm-form-row">
            <div class="bntm-stat-card" style="background:#fed7aa;padding:20px;border-radius:8px;"><h4 style="margin:0 0 10px 0;">Pending Overtime</h4><p style="font-size:32px;margin:0;font-weight:bold;"><?php echo $pending_overtime; ?></p></div>
            <div class="bntm-stat-card" style="background:#fce7f3;padding:20px;border-radius:8px;"><h4 style="margin:0 0 10px 0;">Pending Missing Logs</h4><p style="font-size:32px;margin:0;font-weight:bold;"><?php echo $pending_missing; ?></p></div>
        </div></div>
    <?php endif; ?>
    <script>
    (function() { const copyBtn = document.getElementById('copy-kiosk-url'); if (copyBtn) { copyBtn.addEventListener('click', function() { const urlInput = document.getElementById('kiosk-url'); urlInput.select(); document.execCommand('copy'); this.innerHTML = '<?php echo addslashes(bntm_hr_svg("check", 14)); ?> Copied!'; setTimeout(() => { this.innerHTML = '<?php echo addslashes(bntm_hr_svg("copy", 14)); ?> Copy Link'; }, 2000); }); } })();
    </script>
    <script>
    jQuery(document).ready(function($) {
        const bntmAjax = { ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>', nonce: '<?php echo wp_create_nonce('bntm_hr_nonce'); ?>' };
        $('#quick-clock-in').on('click', async function() { try { const response = await $.ajax({ url: bntmAjax.ajax_url, method: 'POST', data: { action: 'bntm_hr_clock_in', nonce: bntmAjax.nonce } }); const messageDiv = $('#clock-message'); if (response.success) { messageDiv.html('<p style="color:green;margin-top:10px;">' + response.data.message + '</p>'); setTimeout(function() { location.reload(); }, 2000); } else { messageDiv.html('<p style="color:red;margin-top:10px;">' + response.data.message + '</p>'); } } catch (error) { console.error('Error:', error); } });
        $('#quick-clock-out').on('click', async function() { try { const response = await $.ajax({ url: bntmAjax.ajax_url, method: 'POST', data: { action: 'bntm_hr_clock_out', nonce: bntmAjax.nonce } }); const messageDiv = $('#clock-message'); if (response.success) { messageDiv.html('<p style="color:green;margin-top:10px;">' + response.data.message + '</p>'); setTimeout(function() { location.reload(); }, 2000); } else { messageDiv.html('<p style="color:red;margin-top:10px;">' + response.data.message + '</p>'); } } catch (error) { console.error('Error:', error); } });
        $('.approve-leave').on('click', async function() { if (!confirm('Approve this leave request?')) return; const response = await $.ajax({ url: bntmAjax.ajax_url, method: 'POST', data: { action: 'bntm_hr_approve_leave', nonce: bntmAjax.nonce, leave_id: $(this).data('id') } }); alert(response.success ? response.data.message : response.data.message); if (response.success) location.reload(); });
        $('.reject-leave').on('click', async function() { if (!confirm('Reject this leave request?')) return; const response = await $.ajax({ url: bntmAjax.ajax_url, method: 'POST', data: { action: 'bntm_hr_reject_leave', nonce: bntmAjax.nonce, leave_id: $(this).data('id') } }); alert(response.success ? response.data.message : response.data.message); if (response.success) location.reload(); });
        $('.approve-overtime').on('click', async function() { if (!confirm('Approve this overtime request?')) return; const response = await $.ajax({ url: bntmAjax.ajax_url, method: 'POST', data: { action: 'bntm_hr_approve_overtime', nonce: bntmAjax.nonce, ot_id: $(this).data('id') } }); alert(response.success ? response.data.message : response.data.message); if (response.success) location.reload(); });
        $('.reject-overtime').on('click', async function() { if (!confirm('Reject this overtime request?')) return; const response = await $.ajax({ url: bntmAjax.ajax_url, method: 'POST', data: { action: 'bntm_hr_reject_overtime', nonce: bntmAjax.nonce, ot_id: $(this).data('id') } }); alert(response.success ? response.data.message : response.data.message); if (response.success) location.reload(); });
        $('.approve-missing').on('click', async function() { if (!confirm('Approve this missing log? An attendance record will be created.')) return; const response = await $.ajax({ url: bntmAjax.ajax_url, method: 'POST', data: { action: 'bntm_hr_approve_missing_log', nonce: bntmAjax.nonce, log_id: $(this).data('id') } }); alert(response.success ? response.data.message : response.data.message); if (response.success) location.reload(); });
        $('.reject-missing').on('click', async function() { if (!confirm('Reject this missing log?')) return; const response = await $.ajax({ url: bntmAjax.ajax_url, method: 'POST', data: { action: 'bntm_hr_reject_missing_log', nonce: bntmAjax.nonce, log_id: $(this).data('id') } }); alert(response.success ? response.data.message : response.data.message); if (response.success) location.reload(); });
    });
    </script>
    <?php return ob_get_clean();
}

add_action('wp_ajax_bntm_hr_clock_in', 'bntm_ajax_hr_clock_in');
function bntm_ajax_hr_clock_in() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    global $wpdb; $prefix = $wpdb->prefix; $user_id = get_current_user_id();
    $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$prefix}hr_attendance WHERE employee_id = %d AND DATE(clock_in) = %s AND clock_out IS NULL", $user_id, current_time('Y-m-d')));
    if ($existing) { wp_send_json_error(['message' => 'You are already clocked in.']); }
    $result = $wpdb->insert($prefix . 'hr_attendance', ['rand_id' => bntm_rand_id(), 'employee_id' => $user_id, 'business_id' => 1, 'clock_in' => current_time('mysql'), 'status' => 'active'], ['%s', '%d', '%d', '%s', '%s']);
    if ($result) wp_send_json_success(['message' => 'Clocked in successfully at ' . current_time('h:i A')]);
    else wp_send_json_error(['message' => 'Failed to clock in.']);
}

add_action('wp_ajax_bntm_hr_clock_out', 'bntm_ajax_hr_clock_out');
function bntm_ajax_hr_clock_out() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    global $wpdb; $prefix = $wpdb->prefix; $user_id = get_current_user_id();
    $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$prefix}hr_attendance WHERE employee_id = %d AND DATE(clock_in) = %s AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1", $user_id, current_time('Y-m-d')));
    if (!$record) { wp_send_json_error(['message' => 'No active clock in record found.']); }
    $clock_out = current_time('mysql'); $total_hours = round((strtotime($clock_out) - strtotime($record->clock_in)) / 3600, 2);
    $result = $wpdb->update($prefix . 'hr_attendance', ['clock_out' => $clock_out, 'total_hours' => $total_hours, 'status' => 'completed'], ['id' => $record->id], ['%s', '%f', '%s'], ['%d']);
    if ($result !== false) wp_send_json_success(['message' => 'Clocked out. Total hours: ' . $total_hours]);
    else wp_send_json_error(['message' => 'Failed to clock out.']);
}


/* ---------- EMPLOYEES VIEW (with Bank/QR section) ---------- */

function bntm_hr_employees_view($can_manage) {
    if (!$can_manage) { return '<p>You do not have permission to view this page.</p>'; }
    global $wpdb; ob_start();
    $roles             = bntm_get_hr_roles();
    $employees         = get_users(['exclude' => [1], 'orderby' => 'display_name']);
    $custom_roles      = bntm_get_setting('hr_custom_roles', '');
    $roles_array       = $custom_roles ? json_decode($custom_roles, true) : [];
    $default_hourly_rate = floatval(bntm_get_setting('hr_hourly_rate', '15.00'));
    $default_work_hours  = floatval(bntm_get_setting('hr_work_hours', '8'));
    $default_lunch_break = floatval(bntm_get_setting('hr_lunch_break_hours', '1'));
    $current_employees   = count(get_users(['exclude' => [1]]));
    $employee_limit      = get_option('bntm_user_limit', 0);
    $limit_text          = $employee_limit > 0 ? " ({$current_employees}/{$employee_limit})" : " ({$current_employees})";
    $limit_reached       = $employee_limit > 0 && $current_employees >= $employee_limit;
    $role_settings = [];
    foreach ($roles as $role_key => $role_label) {
        if (isset($roles_array[$role_key])) { $role_settings[$role_key] = ['hourly_rate' => $roles_array[$role_key]['hourly_rate'] ?? $default_hourly_rate, 'work_hours' => $roles_array[$role_key]['work_hours'] ?? $default_work_hours, 'lunch_break_hours' => $roles_array[$role_key]['lunch_break_hours'] ?? $default_lunch_break]; }
        else { $role_settings[$role_key] = ['hourly_rate' => $default_hourly_rate, 'work_hours' => $default_work_hours, 'lunch_break_hours' => $default_lunch_break]; }
    }
    ?>
    <div class="bntm-form-section">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h3 style="margin:0;">Employee Management</h3>
            <button id="add-employee-btn" class="bntm-btn-primary" style="display:inline-flex;align-items:center;gap:6px;" <?php echo $limit_reached ? 'disabled' : ''; ?>>
                <?php echo bntm_hr_svg('add', 16); ?> Add Employee<?php echo $limit_text; ?>
            </button>
        </div>
        <?php if ($limit_reached): ?><div style="background:#fee2e2;border:1px solid #fca5a5;padding:10px;border-radius:4px;margin-bottom:15px;"><strong>Employee Limit Reached:</strong> Maximum of <?php echo $employee_limit; ?> employees allowed.</div><?php endif; ?>

        <!-- Add Employee Modal -->
        <div id="employee-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
            <div style="background:white;padding:30px;border-radius:8px;max-width:650px;width:90%;max-height:90vh;overflow-y:auto;">
                <h3 style="margin-top:0;">Add New Employee</h3>
                <form id="add-employee-form" class="bntm-form">
                    <div class="bntm-form-group"><label>First Name *</label><input type="text" name="first_name" required /></div>
                    <div class="bntm-form-group"><label>Last Name *</label><input type="text" name="last_name" required /></div>
                    <div class="bntm-form-group"><label>Email *</label><input type="email" name="email" required /></div>
                    <div class="bntm-form-group"><label>Username *</label><input type="text" name="username" required /></div>
                    <div class="bntm-form-group"><label>Password *</label><input type="password" name="password" id="add-password" required /></div>
                    <div class="bntm-form-group"><label>Confirm Password *</label><input type="password" name="confirm_password" id="add-confirm-password" required /><small id="add-password-match" style="color:red;display:none;">Passwords do not match</small></div>
                    <div class="bntm-form-group"><label>Role *</label><select name="role" id="add-role-select" required><?php foreach ($roles as $key => $label): ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></div>
                    <div id="add-role-info" style="background:#fef3c7;border:2px solid #fde047;border-radius:6px;padding:15px;margin-bottom:15px;display:none;">
                        <div style="font-weight:600;color:#854d0e;margin-bottom:8px;">Role Default Settings</div>
                        <div style="font-size:13px;color:#92400e;"><div>Hourly Rate: &#8369;<span id="add-info-rate">0.00</span>/hour</div><div>Max Work Hours: <span id="add-info-hours">0</span> hours/day</div><div>Lunch Break: <span id="add-info-lunch">0</span> hours</div></div>
                        <div style="font-size:12px;color:#78716c;margin-top:8px;"><em>You can override these values below</em></div>
                    </div>
                    <div class="bntm-form-row"><div class="bntm-form-group"><label>Phone</label><input type="tel" name="phone" /></div><div class="bntm-form-group"><label>Date of Birth</label><input type="date" name="dob" /></div></div>
                    <div class="bntm-form-group"><label>Address</label><textarea name="address" rows="2"></textarea></div>
                    <div class="bntm-form-row"><div class="bntm-form-group"><label>Department</label><input type="text" name="department" placeholder="e.g., Sales, IT, HR" /></div><div class="bntm-form-group"><label>Position</label><input type="text" name="position" placeholder="e.g., Sales Manager" /></div></div>
                    <div class="bntm-form-row"><div class="bntm-form-group"><label>Hire Date</label><input type="date" name="hire_date" value="<?php echo current_time('Y-m-d'); ?>" /></div><div class="bntm-form-group"><label>Hourly Rate (&#8369;)</label><input type="number" name="hourly_rate" id="add-hourly-rate" step="0.01" placeholder="Auto-filled from role" /><small>Leave empty to use role default</small></div></div>
                    <div class="bntm-form-group"><label>Employee PIN (for Kiosk)</label><input type="text" name="pin" maxlength="6" placeholder="4-6 digit PIN" /></div>
                    <div class="bntm-form-group"><label>Emergency Contact Name</label><input type="text" name="emergency_name" /></div>
                    <div class="bntm-form-group"><label>Emergency Contact Phone</label><input type="tel" name="emergency_phone" /></div>
                    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:15px;margin-bottom:15px;">
                        <div style="font-weight:600;color:#166534;margin-bottom:10px;display:flex;align-items:center;gap:6px;"><?php echo bntm_hr_svg('id-card', 16); ?> Tax &amp; Benefits</div>
                        <div class="bntm-form-row"><div class="bntm-form-group"><label>TIN</label><input type="text" name="tin" placeholder="e.g., 123-456-789-000" /></div><div class="bntm-form-group"><label>SSS Number</label><input type="text" name="sss" placeholder="e.g., XX-XXXXXXX-X" /></div></div>
                        <div class="bntm-form-row"><div class="bntm-form-group"><label>Employee Number</label><input type="text" name="employee_number" placeholder="e.g., EMP-001" /></div><div class="bntm-form-group"><label>Pag-IBIG Number</label><input type="text" name="pagibig" /></div></div>
                    </div>
                    <div style="display:flex;gap:10px;margin-top:20px;"><button type="submit" class="bntm-btn-primary" id="add-submit-btn">Add Employee</button><button type="button" id="close-employee-modal" class="bntm-btn-secondary">Cancel</button></div>
                    <div id="employee-modal-message"></div>
                </form>
            </div>
        </div>

        <!-- Edit Employee Modal -->
        <div id="edit-employee-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
            <div style="background:white;padding:30px;border-radius:8px;max-width:650px;width:90%;max-height:90vh;overflow-y:auto;">
                <h3 style="margin-top:0;">Edit Employee</h3>
                <form id="edit-employee-form" class="bntm-form" enctype="multipart/form-data">
                    <input type="hidden" name="user_id" />
                    <div class="bntm-form-group"><label>First Name *</label><input type="text" name="first_name" required /></div>
                    <div class="bntm-form-group"><label>Last Name *</label><input type="text" name="last_name" required /></div>
                    <div class="bntm-form-group"><label>Email *</label><input type="email" name="email" required /></div>
                    <div class="bntm-form-group"><label>New Password (leave blank to keep current)</label><input type="password" name="password" id="edit-password" /></div>
                    <div class="bntm-form-group" id="edit-confirm-group" style="display:none;"><label>Confirm New Password *</label><input type="password" name="confirm_password" id="edit-confirm-password" /><small id="edit-password-match" style="color:red;display:none;">Passwords do not match</small></div>
                    <div class="bntm-form-group"><label>Role *</label><select name="role" id="edit-role-select" required><?php foreach ($roles as $key => $label): ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></div>
                    <div id="edit-role-info" style="background:#fef3c7;border:2px solid #fde047;border-radius:6px;padding:15px;margin-bottom:15px;display:none;">
                        <div style="font-weight:600;color:#854d0e;margin-bottom:8px;">Role Default Settings</div>
                        <div style="font-size:13px;color:#92400e;"><div>Hourly Rate: &#8369;<span id="edit-info-rate">0.00</span>/hour</div><div>Max Work Hours: <span id="edit-info-hours">0</span> hours/day</div><div>Lunch Break: <span id="edit-info-lunch">0</span> hours</div></div>
                        <button type="button" id="apply-role-defaults" class="bntm-btn-small" style="margin-top:8px;">Apply Role Defaults</button>
                    </div>
                    <div class="bntm-form-row"><div class="bntm-form-group"><label>Phone</label><input type="tel" name="phone" /></div><div class="bntm-form-group"><label>Date of Birth</label><input type="date" name="dob" /></div></div>
                    <div class="bntm-form-group"><label>Address</label><textarea name="address" rows="2"></textarea></div>
                    <div class="bntm-form-row"><div class="bntm-form-group"><label>Department</label><input type="text" name="department" /></div><div class="bntm-form-group"><label>Position</label><input type="text" name="position" /></div></div>
                    <div class="bntm-form-row"><div class="bntm-form-group"><label>Hire Date</label><input type="date" name="hire_date" /></div><div class="bntm-form-group"><label>Hourly Rate (&#8369;)</label><input type="number" name="hourly_rate" id="edit-hourly-rate" step="0.01" /></div></div>
                    <div class="bntm-form-group"><label>Employee PIN (for Kiosk)</label><input type="text" name="pin" maxlength="6" /></div>
                    <div class="bntm-form-group"><label>Emergency Contact Name</label><input type="text" name="emergency_name" /></div>
                    <div class="bntm-form-group"><label>Emergency Contact Phone</label><input type="tel" name="emergency_phone" /></div>
                    <div class="bntm-form-group"><label>Status</label><select name="status"><option value="active">Active</option><option value="inactive">Inactive</option><option value="on_leave">On Leave</option><option value="terminated">Terminated</option></select></div>
                    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:15px;margin-bottom:15px;">
                        <div style="font-weight:600;color:#166534;margin-bottom:10px;display:flex;align-items:center;gap:6px;"><?php echo bntm_hr_svg('id-card', 16); ?> Tax &amp; Benefits</div>
                        <div class="bntm-form-row"><div class="bntm-form-group"><label>TIN</label><input type="text" name="tin" /></div><div class="bntm-form-group"><label>SSS Number</label><input type="text" name="sss" /></div></div>
                        <div class="bntm-form-row"><div class="bntm-form-group"><label>Employee Number</label><input type="text" name="employee_number" /></div><div class="bntm-form-group"><label>Pag-IBIG Number</label><input type="text" name="pagibig" /></div></div>
                    </div>

                    <!-- BANK ACCOUNT & QR SECTION -->
                    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:15px;margin-bottom:15px;">
                        <div style="font-weight:600;color:#1d4ed8;margin-bottom:10px;display:flex;align-items:center;gap:6px;"><?php echo bntm_hr_svg('bank', 16); ?> Bank Account Details</div>
                        <div class="bntm-form-row">
                            <div class="bntm-form-group"><label>Bank Name</label><input type="text" name="bank_name" placeholder="e.g., BDO, BPI, GCash, Maya" /></div>
                            <div class="bntm-form-group"><label>Account Name</label><input type="text" name="bank_account_name" placeholder="Full name on account" /></div>
                        </div>
                        <div class="bntm-form-row">
                            <div class="bntm-form-group"><label>Account Number</label><input type="text" name="bank_account_number" placeholder="e.g., 1234-5678-9012" /></div>
                            <div class="bntm-form-group"><label>Branch</label><input type="text" name="bank_branch" placeholder="e.g., Makati Branch" /></div>
                        </div>
                        <div class="bntm-form-group">
                            <label style="display:flex;align-items:center;gap:6px;"><?php echo bntm_hr_svg('qr', 16); ?> QR Code Image</label>
                            <div id="edit-qr-preview" style="margin-bottom:8px;display:none;">
                                <img id="edit-qr-img" src="" alt="QR Code" style="max-width:150px;max-height:150px;border:1px solid #e5e7eb;border-radius:4px;" />
                                <br>
                                <label style="display:inline-flex;align-items:center;gap:4px;margin-top:4px;cursor:pointer;font-size:13px;color:#dc2626;">
                                    <input type="checkbox" name="remove_qr" value="1" /> Remove QR image
                                </label>
                            </div>
                            <input type="file" name="qr_image" accept="image/*" style="display:block;" />
                            <small style="color:#6b7280;">Upload a QR code for payment (auto-compressed to max 400x400). PNG, JPG, or WebP.</small>
                        </div>
                    </div>

                    <div style="display:flex;gap:10px;margin-top:20px;"><button type="submit" class="bntm-btn-primary" id="edit-submit-btn">Update Employee</button><button type="button" id="close-edit-modal" class="bntm-btn-secondary">Cancel</button></div>
                    <div id="edit-employee-message"></div>
                </form>
            </div>
        </div>

        <?php if ($employees): ?>
        
        <div style="display:flex; justify-content:flex-start; align-items:center; margin-bottom:12px; gap:8px;">
            <select id="hr-bulk-action" class="bntm-input" style="padding:6px 12px; font-size:13px; height:auto;">
                <option value="">Bulk Actions</option>
                <option value="set_active">Set Status: Active</option>
                <option value="set_inactive">Set Status: Inactive</option>
                <option value="set_on_leave">Set Status: On Leave</option>
                <option value="set_terminated">Set Status: Terminated</option>
                <option value="delete">Delete Selected</option>
            </select>
            <button id="hr-bulk-apply" class="bntm-btn-secondary bntm-btn-small" style="padding:6px 12px;">Apply</button>
        </div>

        <div class="bntm-table-wrapper">
            <table class="bntm-table" id="hr-employees-table">
                <thead>
                    <tr>
                        <th style="width:40px; text-align:center;"><input type="checkbox" id="hr-bulk-select-all" /></th>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Hourly Rate</th>
                        <th>Status</th>
                        <th>PIN</th>
                        <th>Tax Info</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
            <?php foreach ($employees as $employee):
                $role            = get_user_meta($employee->ID, 'bntm_role', true);
                $department      = get_user_meta($employee->ID, 'bntm_department', true);
                $status          = get_user_meta($employee->ID, 'bntm_status', true) ?: 'active';
                $pin             = get_user_meta($employee->ID, 'bntm_hr_pin', true);
                $hourly_rate     = get_user_meta($employee->ID, 'bntm_hourly_rate', true);
                if (!$hourly_rate) { $settings = bntm_get_employee_payroll_settings($employee->ID); $hourly_rate = $settings['hourly_rate']; }
                $tin             = get_user_meta($employee->ID, 'bntm_tin', true);
                $sss             = get_user_meta($employee->ID, 'bntm_sss', true);
                $employee_number = get_user_meta($employee->ID, 'bntm_employee_number', true);
                $pagibig         = get_user_meta($employee->ID, 'bntm_pagibig', true);
                $tax_parts = [];
                if ($tin) $tax_parts[] = 'TIN: ' . $tin;
                if ($sss) $tax_parts[] = 'SSS: ' . $sss;
                if ($employee_number) $tax_parts[] = '#' . $employee_number;
                $tax_info = implode(' ', $tax_parts);
                $status_colors = ['active' => 'background:#d1fae5;color:#065f46;','inactive' => 'background:#fee2e2;color:#991b1b;','on_leave' => 'background:#fef3c7;color:#92400e;','terminated' => 'background:#f3f4f6;color:#6b7280;'];
                $status_style = $status_colors[$status] ?? '';
            ?>
                <tr>
                    <td style="text-align:center;"><input type="checkbox" class="hr-bulk-item" value="<?php echo $employee->ID; ?>" /></td>
                    <td><strong><?php echo esc_html($employee->display_name); ?></strong></td>
                    <td><?php echo esc_html($role ? ucfirst($role) : 'Not Set'); ?></td>
                    <td><?php echo esc_html($department ?: '-'); ?></td>
                    <td><strong>&#8369;<?php echo number_format($hourly_rate, 2); ?></strong></td>
                    <td><span style="padding:4px 8px;border-radius:4px;display:inline-block;font-size:12px;font-weight:600;<?php echo $status_style; ?>"><?php echo esc_html(ucfirst(str_replace('_', ' ', $status))); ?></span></td>
                    <td><?php echo esc_html($pin ?: '-'); ?></td>
                    <td style="font-size:12px; color:var(--hr-text-muted);"><?php echo esc_html($tax_info ?: '-'); ?></td>
                    <td>
                        <div class="hr-dropdown" tabindex="0" onmouseleave="setTimeout(() => { this.querySelector('.hr-dropdown-content').classList.remove('show') }, 300)">
                            <button type="button" class="hr-dropdown-btn" onclick="this.nextElementSibling.classList.toggle('show');">
                                Actions <svg width="12" height="12" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>
                            <div class="hr-dropdown-content">
                                <button type="button" class="edit-employee" data-id="<?php echo $employee->ID; ?>"><?php echo bntm_hr_svg('edit', 14); ?> Edit</button>
                                <button type="button" class="view-attendance" data-id="<?php echo $employee->ID; ?>" data-name="<?php echo esc_attr($employee->display_name); ?>"><?php echo bntm_hr_svg('clock', 14); ?> Attendance</button>
                                <button type="button" class="delete-employee danger" data-id="<?php echo $employee->ID; ?>" data-name="<?php echo esc_attr($employee->display_name); ?>"><?php echo bntm_hr_svg('delete', 14); ?> Delete</button>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?><div style="text-align:center; padding:40px; color:var(--hr-text-muted);">No employees found.</div><?php endif; ?>
    </div>
    <script>
    (function() {
        const bntmAjax = { ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>', nonce: '<?php echo wp_create_nonce('bntm_hr_nonce'); ?>' };
        const roleSettings = <?php echo json_encode($role_settings); ?>;
        function serializeFormData(form) { const fd = new FormData(form); const d = {}; for (let [k,v] of fd.entries()) { d[k] = v; } return d; }
        function populateForm(form, data) { for (let key in data) { const input = form.querySelector('[name="' + key + '"]'); if (input) { if (input.type === 'checkbox') input.checked = data[key]; else input.value = data[key] || ''; } } }
        function updateRoleInfo(roleKey, prefix) { const settings = roleSettings[roleKey]; if (settings) { document.getElementById(prefix + '-role-info').style.display = 'block'; document.getElementById(prefix + '-info-rate').textContent = parseFloat(settings.hourly_rate).toFixed(2); document.getElementById(prefix + '-info-hours').textContent = settings.work_hours; document.getElementById(prefix + '-info-lunch').textContent = settings.lunch_break_hours; } else { document.getElementById(prefix + '-role-info').style.display = 'none'; } }
        function checkPasswordMatch(passwordId, confirmId, messageId, submitBtnId) {
            const password = document.getElementById(passwordId); const confirmPassword = document.getElementById(confirmId); const message = document.getElementById(messageId); const submitBtn = document.getElementById(submitBtnId);
            if (password && confirmPassword && message && submitBtn) { const checkMatch = () => { if (confirmPassword.value === '') { message.style.display = 'none'; submitBtn.disabled = false; return; } if (password.value !== confirmPassword.value) { message.style.display = 'block'; submitBtn.disabled = true; } else { message.style.display = 'none'; submitBtn.disabled = false; } }; password.addEventListener('input', checkMatch); confirmPassword.addEventListener('input', checkMatch); }
        }
        async function makeAjaxRequest(action, data = {}) {
            const formData = new FormData(); formData.append('action', action); formData.append('nonce', bntmAjax.nonce);
            for (let key in data) { if (Array.isArray(data[key])) data[key].forEach(val => formData.append(key + '[]', val)); else formData.append(key, data[key]); }
            const response = await fetch(bntmAjax.ajax_url, { method: 'POST', body: formData });
            return await response.json();
        }
        document.addEventListener('DOMContentLoaded', function() {
            const addEmployeeBtn = document.getElementById('add-employee-btn');
            const employeeModal  = document.getElementById('employee-modal');
            const closeEmployeeModal = document.getElementById('close-employee-modal');
            const addEmployeeForm    = document.getElementById('add-employee-form');
            const editEmployeeModal  = document.getElementById('edit-employee-modal');
            const closeEditModal     = document.getElementById('close-edit-modal');
            const editEmployeeForm   = document.getElementById('edit-employee-form');
            const editConfirmGroup   = document.getElementById('edit-confirm-group');
            const editConfirmPassword = document.getElementById('edit-confirm-password');

            checkPasswordMatch('add-password', 'add-confirm-password', 'add-password-match', 'add-submit-btn');

            const editPassword = document.getElementById('edit-password');
            if (editPassword && editConfirmGroup) {
                editPassword.addEventListener('input', function() {
                    if (this.value.length > 0) { editConfirmGroup.style.display = 'block'; editConfirmPassword.required = true; }
                    else { editConfirmGroup.style.display = 'none'; editConfirmPassword.required = false; editConfirmPassword.value = ''; }
                });
                checkPasswordMatch('edit-password', 'edit-confirm-password', 'edit-password-match', 'edit-submit-btn');
            }

            const addRoleSelect = document.getElementById('add-role-select');
            if (addRoleSelect) { addRoleSelect.addEventListener('change', function() { updateRoleInfo(this.value, 'add'); }); if (addRoleSelect.value) updateRoleInfo(addRoleSelect.value, 'add'); }

            const editRoleSelect = document.getElementById('edit-role-select');
            if (editRoleSelect) { editRoleSelect.addEventListener('change', function() { updateRoleInfo(this.value, 'edit'); }); }

            const applyDefaultsBtn = document.getElementById('apply-role-defaults');
            if (applyDefaultsBtn) { applyDefaultsBtn.addEventListener('click', function() { const roleKey = editRoleSelect.value; const settings = roleSettings[roleKey]; if (settings) { document.getElementById('edit-hourly-rate').value = settings.hourly_rate; } }); }

            if (addEmployeeBtn) { addEmployeeBtn.addEventListener('click', function() { if (!this.disabled) { employeeModal.style.display = 'flex'; if (addRoleSelect.value) updateRoleInfo(addRoleSelect.value, 'add'); } }); }
            if (closeEmployeeModal) { closeEmployeeModal.addEventListener('click', function() { employeeModal.style.display = 'none'; addEmployeeForm.reset(); document.getElementById('employee-modal-message').innerHTML = ''; document.getElementById('add-role-info').style.display = 'none'; }); }

            if (addEmployeeForm) {
                addEmployeeForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const password = document.getElementById('add-password').value; const confirmPassword = document.getElementById('add-confirm-password').value;
                    if (password !== confirmPassword) { alert('Passwords do not match!'); return; }
                    const messageDiv = document.getElementById('employee-modal-message'); messageDiv.innerHTML = '<p>Processing...</p>';
                    try {
                        const formData = serializeFormData(this);
                        const data = await makeAjaxRequest('bntm_hr_add_employee', formData);
                        if (data.success) { messageDiv.innerHTML = '<p style="color:green;">' + data.data.message + '</p>'; setTimeout(() => location.reload(), 1500); }
                        else { messageDiv.innerHTML = '<p style="color:red;">' + (data.data ? data.data.message : 'An error occurred') + '</p>'; }
                    } catch (error) { messageDiv.innerHTML = '<p style="color:red;">Error: ' + error.message + '</p>'; }
                });
            }

            document.querySelectorAll('.edit-employee').forEach(button => {
                button.addEventListener('click', async function() {
                    const userId = this.getAttribute('data-id');
                    try {
                        const result = await makeAjaxRequest('bntm_hr_get_employee', { user_id: userId });
                        if (result.success) {
                            populateForm(editEmployeeForm, result.data);
                            editEmployeeForm.querySelector('[name="user_id"]').value = userId;
                            document.getElementById('edit-password').value = '';
                            document.getElementById('edit-confirm-password').value = '';
                            editConfirmGroup.style.display = 'none';
                            document.getElementById('edit-password-match').style.display = 'none';
                            const roleKey = result.data.role;
                            if (roleKey) updateRoleInfo(roleKey, 'edit');
                            // Load QR preview
                            const qrPreview = document.getElementById('edit-qr-preview');
                            const qrImg = document.getElementById('edit-qr-img');
                            if (result.data.qr_image) { qrImg.src = result.data.qr_image; qrPreview.style.display = 'block'; }
                            else { qrPreview.style.display = 'none'; }
                            editEmployeeModal.style.display = 'flex';
                        }
                    } catch (error) { alert('Error loading employee data: ' + error.message); }
                });
            });

            if (closeEditModal) { closeEditModal.addEventListener('click', function() { editEmployeeModal.style.display = 'none'; editEmployeeForm.reset(); document.getElementById('edit-employee-message').innerHTML = ''; document.getElementById('edit-role-info').style.display = 'none'; editConfirmGroup.style.display = 'none'; document.getElementById('edit-qr-preview').style.display = 'none'; }); }

            if (editEmployeeForm) {
                editEmployeeForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const password = document.getElementById('edit-password').value; const confirmPassword = document.getElementById('edit-confirm-password').value;
                    if (password && password !== confirmPassword) { alert('Passwords do not match!'); return; }
                    const messageDiv = document.getElementById('edit-employee-message'); messageDiv.innerHTML = '<p>Processing...</p>';
                    try {
                        // Use FormData directly to support file upload
                        const fd = new FormData(this);
                        fd.append('action', 'bntm_hr_update_employee_with_bank');
                        fd.append('nonce', bntmAjax.nonce);
                        const response = await fetch(bntmAjax.ajax_url, { method: 'POST', body: fd });
                        const data = await response.json();
                        if (data.success) { messageDiv.innerHTML = '<p style="color:green;">' + data.data.message + '</p>'; setTimeout(() => location.reload(), 1500); }
                        else { messageDiv.innerHTML = '<p style="color:red;">' + (data.data ? data.data.message : 'An error occurred') + '</p>'; }
                    } catch (error) { messageDiv.innerHTML = '<p style="color:red;">Error: ' + error.message + '</p>'; }
                });
            }

            document.querySelectorAll('.delete-employee').forEach(button => {
                button.addEventListener('click', async function() {
                    const userId = this.getAttribute('data-id'); const userName = this.getAttribute('data-name');
                    if (!confirm('Are you sure you want to delete employee "' + userName + '"?')) return;
                    if (!confirm('FINAL CONFIRMATION: Delete "' + userName + '"?')) return;
                    try { const data = await makeAjaxRequest('bntm_hr_delete_employee', { user_id: userId }); if (data.success) { alert(data.data.message); location.reload(); } else { alert('Error: ' + (data.data ? data.data.message : 'An error occurred')); } } catch (error) { alert('Error: ' + error.message); }
                });
            });

            document.querySelectorAll('.view-attendance').forEach(button => {
                button.addEventListener('click', function() { const userId = this.getAttribute('data-id'); const currentUrl = new URL(window.location.href); currentUrl.searchParams.set('type', 'attendance'); currentUrl.searchParams.set('employee_id', userId); window.location.href = currentUrl.toString(); });
            });

            // --- BULK ACTION LOGIC ---
            const selectAll = document.getElementById('hr-bulk-select-all');
            const bulkItems = document.querySelectorAll('.hr-bulk-item');
            if(selectAll) {
                selectAll.addEventListener('change', function() {
                    bulkItems.forEach(item => item.checked = this.checked);
                });
            }
            const bulkApplyBtn = document.getElementById('hr-bulk-apply');
            if(bulkApplyBtn) {
                bulkApplyBtn.addEventListener('click', async function() {
                    const action = document.getElementById('hr-bulk-action').value;
                    if(!action) return alert('Please select a bulk action.');
                    const selectedIds = Array.from(bulkItems).filter(i => i.checked).map(i => i.value);
                    if(selectedIds.length === 0) return alert('Please select at least one employee.');
                    
                    if(!confirm(`Are you sure you want to perform this bulk action on ${selectedIds.length} employee(s)?`)) return;
                    
                    bulkApplyBtn.textContent = 'Applying...';
                    bulkApplyBtn.disabled = true;
                    try {
                        let processed = 0;
                        let errors = 0;
                        
                        // Fallback implementation: we run a loop of actions since there might not be a bulk endpoint
                        if (action === 'delete') {
                            for(let id of selectedIds) {
                                const res = await makeAjaxRequest('bntm_hr_delete_employee', { user_id: id });
                                if(!res.success) errors++; else processed++;
                            }
                        } else if (action.startsWith('set_')) {
                            const newStatus = action.replace('set_', '');
                            for(let id of selectedIds) {
                                // Since bntm_hr_update_employee_with_bank handles form data, we need special endpoint or we rely on loop
                                // If endpoint doesn't exist, we will show "feature not enabled yet"
                            }
                            alert('Bulk status update currently requires backend implementation. Processed ' + processed + ' deletes.');
                        }
                        
                        if (action === 'delete') {
                            alert(`Operation complete. Success: ${processed}, Errors: ${errors}`);
                            location.reload();
                        }
                    } catch (e) {
                        alert('Error: ' + e.message);
                    }
                    bulkApplyBtn.textContent = 'Apply';
                    bulkApplyBtn.disabled = false;
                });
            }
            // -------------------------

        });
    })();
    </script>
    <?php return ob_get_clean();
}


/* ---------- HELPER FUNCTIONS ---------- */

function bntm_get_hr_roles() {
    $default_roles = ['staff' => 'Staff', 'manager' => 'Manager', 'owner' => 'Owner'];
    if (function_exists('pos_create_cashier_role') || get_role('pos_cashier')) { $default_roles['pos_cashier'] = 'POS Cashier'; }
    $custom_roles = bntm_get_setting('hr_custom_roles', ''); $custom_roles_array = $custom_roles ? json_decode($custom_roles, true) : [];
    $all_roles = $default_roles;
    if (is_array($custom_roles_array)) { foreach ($custom_roles_array as $key => $role) { $all_roles[$key] = $role['label']; } }
    return $all_roles;
}

function bntm_get_employee_payroll_settings($employee_id) {
    $employee_role     = bntm_get_user_role($employee_id);
    $default_hourly_rate = floatval(bntm_get_setting('hr_hourly_rate', '15.00'));
    $default_work_hours  = floatval(bntm_get_setting('hr_work_hours', '8'));
    $default_lunch_break = floatval(bntm_get_setting('hr_lunch_break_hours', '1'));
    $custom_hourly_rate  = get_user_meta($employee_id, 'bntm_hourly_rate', true);
    $hourly_rate = ($custom_hourly_rate && floatval($custom_hourly_rate) > 0) ? floatval($custom_hourly_rate) : $default_hourly_rate;
    $custom_roles = bntm_get_setting('hr_custom_roles', ''); $roles_array = $custom_roles ? json_decode($custom_roles, true) : [];
    $work_hours = $default_work_hours; $lunch_break_hours = $default_lunch_break;
    if (isset($roles_array[$employee_role])) { $rs = $roles_array[$employee_role]; if (!$custom_hourly_rate && isset($rs['hourly_rate']) && $rs['hourly_rate'] > 0) $hourly_rate = floatval($rs['hourly_rate']); if (isset($rs['work_hours']) && $rs['work_hours'] > 0) $work_hours = floatval($rs['work_hours']); if (isset($rs['lunch_break_hours']) && $rs['lunch_break_hours'] >= 0) $lunch_break_hours = floatval($rs['lunch_break_hours']); }
    return ['hourly_rate' => $hourly_rate, 'work_hours' => $work_hours, 'lunch_break_hours' => $lunch_break_hours];
}

/* ---------- EMPLOYEE AJAX HANDLERS ---------- */

add_action('wp_ajax_bntm_hr_add_employee', 'bntm_ajax_hr_add_employee');
function bntm_ajax_hr_add_employee() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    $current_user = wp_get_current_user(); $is_wp_admin = current_user_can('manage_options'); $current_role = bntm_get_user_role($current_user->ID);
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) { wp_send_json_error(['message' => 'Permission denied.']); }
    $user_limit = get_option('bntm_user_limit', 0);
    if ($user_limit > 0) { $current_count = count(get_users(['exclude' => [1]])); if ($current_count >= $user_limit) { wp_send_json_error(['message' => "Employee limit reached."]); } }
    $first_name = sanitize_text_field($_POST['first_name']); $last_name = sanitize_text_field($_POST['last_name']); $email = sanitize_email($_POST['email']); $username = sanitize_user($_POST['username']); $password = $_POST['password']; $role = sanitize_text_field($_POST['role']);
    $user_id = wp_create_user($username, $password, $email);
    if (is_wp_error($user_id)) { wp_send_json_error(['message' => $user_id->get_error_message()]); }
    wp_update_user(['ID' => $user_id, 'first_name' => $first_name, 'last_name' => $last_name, 'display_name' => $first_name . ' ' . $last_name]);
    update_user_meta($user_id, 'bntm_role', $role);
    update_user_meta($user_id, 'bntm_phone',                   sanitize_text_field($_POST['phone'] ?? ''));
    update_user_meta($user_id, 'bntm_dob',                     sanitize_text_field($_POST['dob'] ?? ''));
    update_user_meta($user_id, 'bntm_address',                 sanitize_textarea_field($_POST['address'] ?? ''));
    update_user_meta($user_id, 'bntm_department',              sanitize_text_field($_POST['department'] ?? ''));
    update_user_meta($user_id, 'bntm_position',                sanitize_text_field($_POST['position'] ?? ''));
    update_user_meta($user_id, 'bntm_hire_date',               sanitize_text_field($_POST['hire_date'] ?? ''));
    update_user_meta($user_id, 'bntm_hourly_rate',             floatval($_POST['hourly_rate'] ?? 0));
    update_user_meta($user_id, 'bntm_hr_pin',                  sanitize_text_field($_POST['pin'] ?? ''));
    update_user_meta($user_id, 'bntm_emergency_contact_name',  sanitize_text_field($_POST['emergency_name'] ?? ''));
    update_user_meta($user_id, 'bntm_emergency_contact_phone', sanitize_text_field($_POST['emergency_phone'] ?? ''));
    update_user_meta($user_id, 'bntm_tin',                     sanitize_text_field($_POST['tin'] ?? ''));
    update_user_meta($user_id, 'bntm_sss',                     sanitize_text_field($_POST['sss'] ?? ''));
    update_user_meta($user_id, 'bntm_employee_number',         sanitize_text_field($_POST['employee_number'] ?? ''));
    update_user_meta($user_id, 'bntm_pagibig',                 sanitize_text_field($_POST['pagibig'] ?? ''));
    update_user_meta($user_id, 'bntm_status', 'active');
    if ($role === 'pos_cashier') { $user = new WP_User($user_id); $user->set_role('pos_cashier'); }
    wp_send_json_success(['message' => 'Employee added successfully!']);
}

add_action('wp_ajax_bntm_hr_get_employee', 'bntm_ajax_hr_get_employee');
function bntm_ajax_hr_get_employee() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    $user_id = intval($_POST['user_id']); $user = get_userdata($user_id);
    if (!$user) { wp_send_json_error(['message' => 'Employee not found.']); }
    wp_send_json_success([
        'first_name'           => $user->first_name,
        'last_name'            => $user->last_name,
        'email'                => $user->user_email,
        'role'                 => get_user_meta($user_id, 'bntm_role', true),
        'phone'                => get_user_meta($user_id, 'bntm_phone', true),
        'dob'                  => get_user_meta($user_id, 'bntm_dob', true),
        'address'              => get_user_meta($user_id, 'bntm_address', true),
        'department'           => get_user_meta($user_id, 'bntm_department', true),
        'position'             => get_user_meta($user_id, 'bntm_position', true),
        'hire_date'            => get_user_meta($user_id, 'bntm_hire_date', true),
        'hourly_rate'          => get_user_meta($user_id, 'bntm_hourly_rate', true),
        'pin'                  => get_user_meta($user_id, 'bntm_hr_pin', true),
        'emergency_name'       => get_user_meta($user_id, 'bntm_emergency_contact_name', true),
        'emergency_phone'      => get_user_meta($user_id, 'bntm_emergency_contact_phone', true),
        'status'               => get_user_meta($user_id, 'bntm_status', true) ?: 'active',
        'tin'                  => get_user_meta($user_id, 'bntm_tin', true),
        'sss'                  => get_user_meta($user_id, 'bntm_sss', true),
        'employee_number'      => get_user_meta($user_id, 'bntm_employee_number', true),
        'pagibig'              => get_user_meta($user_id, 'bntm_pagibig', true),
        'bank_name'            => get_user_meta($user_id, 'bntm_bank_name', true),
        'bank_account_name'    => get_user_meta($user_id, 'bntm_bank_account_name', true),
        'bank_account_number'  => get_user_meta($user_id, 'bntm_bank_account_number', true),
        'bank_branch'          => get_user_meta($user_id, 'bntm_bank_branch', true),
        'qr_image'             => get_user_meta($user_id, 'bntm_qr_image', true),
    ]);
}

/* Combined update employee + bank details handler */
add_action('wp_ajax_bntm_hr_update_employee_with_bank', 'bntm_ajax_hr_update_employee_with_bank');
function bntm_ajax_hr_update_employee_with_bank() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    $current_user = wp_get_current_user(); $is_wp_admin = current_user_can('manage_options'); $current_role = bntm_get_user_role($current_user->ID);
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) { wp_send_json_error(['message' => 'Permission denied.']); }
    $user_id    = intval($_POST['user_id']);
    $first_name = sanitize_text_field($_POST['first_name']);
    $last_name  = sanitize_text_field($_POST['last_name']);
    $email      = sanitize_email($_POST['email']);
    $role       = sanitize_text_field($_POST['role']);
    $password   = $_POST['password'] ?? '';
    $update_data = ['ID' => $user_id, 'user_email' => $email, 'first_name' => $first_name, 'last_name' => $last_name, 'display_name' => $first_name . ' ' . $last_name];
    if (!empty($password)) $update_data['user_pass'] = $password;
    $updated = wp_update_user($update_data);
    if (is_wp_error($updated)) { wp_send_json_error(['message' => $updated->get_error_message()]); }
    update_user_meta($user_id, 'bntm_role',                    $role);
    update_user_meta($user_id, 'bntm_phone',                   sanitize_text_field($_POST['phone'] ?? ''));
    update_user_meta($user_id, 'bntm_dob',                     sanitize_text_field($_POST['dob'] ?? ''));
    update_user_meta($user_id, 'bntm_address',                 sanitize_textarea_field($_POST['address'] ?? ''));
    update_user_meta($user_id, 'bntm_department',              sanitize_text_field($_POST['department'] ?? ''));
    update_user_meta($user_id, 'bntm_position',                sanitize_text_field($_POST['position'] ?? ''));
    update_user_meta($user_id, 'bntm_hire_date',               sanitize_text_field($_POST['hire_date'] ?? ''));
    update_user_meta($user_id, 'bntm_hourly_rate',             floatval($_POST['hourly_rate'] ?? 0));
    update_user_meta($user_id, 'bntm_hr_pin',                  sanitize_text_field($_POST['pin'] ?? ''));
    update_user_meta($user_id, 'bntm_emergency_contact_name',  sanitize_text_field($_POST['emergency_name'] ?? ''));
    update_user_meta($user_id, 'bntm_emergency_contact_phone', sanitize_text_field($_POST['emergency_phone'] ?? ''));
    update_user_meta($user_id, 'bntm_tin',                     sanitize_text_field($_POST['tin'] ?? ''));
    update_user_meta($user_id, 'bntm_sss',                     sanitize_text_field($_POST['sss'] ?? ''));
    update_user_meta($user_id, 'bntm_employee_number',         sanitize_text_field($_POST['employee_number'] ?? ''));
    update_user_meta($user_id, 'bntm_pagibig',                 sanitize_text_field($_POST['pagibig'] ?? ''));
    update_user_meta($user_id, 'bntm_status',                  sanitize_text_field($_POST['status'] ?? 'active'));
    // Bank details
    update_user_meta($user_id, 'bntm_bank_name',           sanitize_text_field($_POST['bank_name'] ?? ''));
    update_user_meta($user_id, 'bntm_bank_account_name',   sanitize_text_field($_POST['bank_account_name'] ?? ''));
    update_user_meta($user_id, 'bntm_bank_account_number', sanitize_text_field($_POST['bank_account_number'] ?? ''));
    update_user_meta($user_id, 'bntm_bank_branch',         sanitize_text_field($_POST['bank_branch'] ?? ''));
    // QR image (compress & store as base64)
    if (!empty($_FILES['qr_image']['tmp_name'])) {
        $tmp  = $_FILES['qr_image']['tmp_name'];
        $info = getimagesize($tmp);
        if ($info && in_array($info['mime'], ['image/jpeg','image/jpg','image/png','image/gif','image/webp'])) {
            $src_w = $info[0]; $src_h = $info[1]; $max = 400;
            $ratio = min($max / $src_w, $max / $src_h, 1);
            $new_w = (int)($src_w * $ratio); $new_h = (int)($src_h * $ratio);
            $dst = imagecreatetruecolor($new_w, $new_h);
            switch ($info['mime']) {
                case 'image/png': $src_img = imagecreatefrompng($tmp); imagealphablending($dst, false); imagesavealpha($dst, true); break;
                case 'image/gif': $src_img = imagecreatefromgif($tmp); break;
                default: $src_img = imagecreatefromjpeg($tmp);
            }
            imagecopyresampled($dst, $src_img, 0, 0, 0, 0, $new_w, $new_h, $src_w, $src_h);
            ob_start();
            if ($info['mime'] === 'image/png') { imagepng($dst, null, 7); $mime = 'image/png'; } else { imagejpeg($dst, null, 80); $mime = 'image/jpeg'; }
            $img_data = ob_get_clean(); imagedestroy($dst); imagedestroy($src_img);
            update_user_meta($user_id, 'bntm_qr_image', 'data:' . $mime . ';base64,' . base64_encode($img_data));
        }
    }
    if (!empty($_POST['remove_qr'])) { delete_user_meta($user_id, 'bntm_qr_image'); }
    if ($role === 'pos_cashier') { $user = new WP_User($user_id); $user->set_role('pos_cashier'); }
    wp_send_json_success(['message' => 'Employee updated successfully!']);
}

add_action('wp_ajax_bntm_hr_delete_employee', 'bntm_ajax_hr_delete_employee');
function bntm_ajax_hr_delete_employee() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    $current_user = wp_get_current_user(); $is_wp_admin = current_user_can('manage_options'); $current_role = bntm_get_user_role($current_user->ID);
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) { wp_send_json_error(['message' => 'Permission denied.']); }
    $user_id = intval($_POST['user_id']);
    if ($user_id === 1) { wp_send_json_error(['message' => 'Cannot delete the administrator account.']); }
    if ($user_id === $current_user->ID) { wp_send_json_error(['message' => 'You cannot delete your own account.']); }
    $user = get_userdata($user_id);
    if (!$user) { wp_send_json_error(['message' => 'Employee not found.']); }
    require_once(ABSPATH . 'wp-admin/includes/user.php');
    $deleted = wp_delete_user($user_id);
    if ($deleted) wp_send_json_success(['message' => 'Employee deleted successfully!']);
    else wp_send_json_error(['message' => 'Failed to delete employee.']);
}

/* Keep old update_employee for backward compat */
add_action('wp_ajax_bntm_hr_update_employee', 'bntm_ajax_hr_update_employee');
function bntm_ajax_hr_update_employee() {
    // delegate to new combined handler
    bntm_ajax_hr_update_employee_with_bank();
}


/* ---------- ATTENDANCE VIEW ---------- */

function bntm_hr_attendance_view($user_id, $can_manage) {
    global $wpdb; $prefix = $wpdb->prefix; ob_start();
    $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : date('Y-m-01');
    $date_to   = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : date('Y-m-d');
    $filter_employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;
    $employee_name = '';
    if ($filter_employee_id > 0) { $employee = get_userdata($filter_employee_id); if ($employee) $employee_name = $employee->display_name; }
    if ($can_manage) {
        if ($filter_employee_id > 0) { $attendance = $wpdb->get_results($wpdb->prepare("SELECT a.*, u.display_name FROM {$prefix}hr_attendance a LEFT JOIN {$wpdb->users} u ON a.employee_id = u.ID WHERE a.employee_id = %d AND DATE(a.clock_in) BETWEEN %s AND %s ORDER BY a.clock_in DESC", $filter_employee_id, $date_from, $date_to)); }
        else { $attendance = $wpdb->get_results($wpdb->prepare("SELECT a.*, u.display_name FROM {$prefix}hr_attendance a LEFT JOIN {$wpdb->users} u ON a.employee_id = u.ID WHERE DATE(a.clock_in) BETWEEN %s AND %s ORDER BY a.clock_in DESC", $date_from, $date_to)); }
    } else { $attendance = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$prefix}hr_attendance WHERE employee_id = %d AND DATE(clock_in) BETWEEN %s AND %s ORDER BY clock_in DESC", $user_id, $date_from, $date_to)); }
    $all_employees = $can_manage ? get_users(['exclude' => [1], 'orderby' => 'display_name']) : [];
    ?>
    <div class="bntm-form-section">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h3 style="margin:0;">Attendance Records<?php if ($employee_name): ?> — <?php echo esc_html($employee_name); ?><?php endif; ?></h3>
            <?php if ($filter_employee_id > 0): ?><a href="?type=attendance" class="bntm-btn-secondary">Back to All</a><?php endif; ?>
        </div>
        <form method="get" class="bntm-form" style="margin-bottom:20px;"><input type="hidden" name="type" value="attendance" />
            <div class="bntm-form-row">
                <?php if ($can_manage): ?><div class="bntm-form-group"><label>Employee</label><select name="employee_id"><option value="">All Employees</option><?php foreach ($all_employees as $emp): ?><option value="<?php echo $emp->ID; ?>" <?php selected($filter_employee_id, $emp->ID); ?>><?php echo esc_html($emp->display_name); ?></option><?php endforeach; ?></select></div><?php endif; ?>
                <div class="bntm-form-group"><label>From</label><input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" /></div>
                <div class="bntm-form-group"><label>To</label><input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" /></div>
                <div class="bntm-form-group" style="align-self:end;"><button type="submit" class="bntm-btn-primary" style="display:inline-flex;align-items:center;gap:6px;"><?php echo bntm_hr_svg('filter', 14); ?> Filter</button></div>
            </div>
        </form>
        <?php if ($attendance):
            $total_hours = 0; $total_days = 0;
            foreach ($attendance as $record) { if ($record->total_hours) { $total_hours += floatval($record->total_hours); $total_days++; } }
            ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin-bottom:20px;">
                <div style="background:#f0f9ff;padding:15px;border-radius:8px;border-left:4px solid #0284c7;"><div style="font-size:12px;color:#64748b;margin-bottom:5px;">Total Records</div><div style="font-size:24px;font-weight:bold;"><?php echo count($attendance); ?></div></div>
                <div style="background:#f0fdf4;padding:15px;border-radius:8px;border-left:4px solid #16a34a;"><div style="font-size:12px;color:#64748b;margin-bottom:5px;">Total Hours</div><div style="font-size:24px;font-weight:bold;"><?php echo number_format($total_hours, 2); ?></div></div>
                <div style="background:#fefce8;padding:15px;border-radius:8px;border-left:4px solid #ca8a04;"><div style="font-size:12px;color:#64748b;margin-bottom:5px;">Days Worked</div><div style="font-size:24px;font-weight:bold;"><?php echo $total_days; ?></div></div>
                <div style="background:#faf5ff;padding:15px;border-radius:8px;border-left:4px solid #9333ea;"><div style="font-size:12px;color:#64748b;margin-bottom:5px;">Avg Hours/Day</div><div style="font-size:24px;font-weight:bold;"><?php echo $total_days > 0 ? number_format($total_hours / $total_days, 2) : '0'; ?></div></div>
            </div>
            <div class="bntm-table-wrapper"><table class="bntm-table"><thead><tr><?php if ($can_manage && !$filter_employee_id): ?><th>Employee</th><?php endif; ?><th>Date</th><th>Clock In</th><th>Clock Out</th><th>Hours</th><th>Status</th><?php if ($can_manage): ?><th>Actions</th><?php endif; ?></tr></thead><tbody>
                <?php foreach ($attendance as $record): $status_colors = ['present' => 'background:#d1fae5;color:#065f46;','late' => 'background:#fef3c7;color:#92400e;','absent' => 'background:#fee2e2;color:#991b1b;','on_leave' => 'background:#e0e7ff;color:#3730a3;']; $status_style = $status_colors[$record->status] ?? ''; ?>
                    <tr><?php if ($can_manage && !$filter_employee_id): ?><td><?php echo esc_html($record->display_name); ?></td><?php endif; ?>
                    <td><?php echo esc_html(date('M d, Y', strtotime($record->clock_in))); ?></td>
                    <td><?php echo esc_html(date('h:i A', strtotime($record->clock_in))); ?></td>
                    <td><?php if ($record->clock_out): ?><?php echo esc_html(date('h:i A', strtotime($record->clock_out))); ?><?php else: ?><span style="color:#dc2626;font-weight:500;">Not clocked out</span><?php endif; ?></td>
                    <td><?php if ($record->total_hours): ?><strong><?php echo esc_html(number_format($record->total_hours, 2)); ?> hrs</strong><?php else: ?><span style="color:#9ca3af;">-</span><?php endif; ?></td>
                    <td><span style="padding:4px 8px;border-radius:4px;display:inline-block;font-size:12px;<?php echo $status_style; ?>"><?php echo esc_html(ucfirst($record->status)); ?></span></td>
                    <?php if ($can_manage): ?><td><button class="bntm-btn-small edit-attendance" data-id="<?php echo $record->id; ?>" data-clock-in="<?php echo esc_attr($record->clock_in); ?>" data-clock-out="<?php echo esc_attr($record->clock_out); ?>" data-status="<?php echo esc_attr($record->status); ?>" style="display:inline-flex;align-items:center;gap:4px;"><?php echo bntm_hr_svg('edit', 12); ?> Edit</button></td><?php endif; ?></tr>
                <?php endforeach; ?></tbody></table></div>
        <?php else: ?><div style="text-align:center;padding:40px;background:#f9fafb;border-radius:8px;"><p style="color:#64748b;margin:0;">No attendance records found for the selected period.</p></div><?php endif; ?>
    </div>
    <?php if ($can_manage): ?>
    <div id="edit-attendance-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
        <div style="background:white;padding:30px;border-radius:8px;max-width:500px;width:90%;">
            <h3 style="margin-top:0;">Edit Attendance Record</h3>
            <form id="edit-attendance-form" class="bntm-form"><input type="hidden" name="attendance_id" />
                <div class="bntm-form-group"><label>Clock In</label><input type="datetime-local" name="clock_in" required /></div>
                <div class="bntm-form-group"><label>Clock Out</label><input type="datetime-local" name="clock_out" /></div>
                <div class="bntm-form-group"><label>Status</label><select name="status" required><option value="present">Present</option><option value="late">Late</option><option value="absent">Absent</option><option value="on_leave">On Leave</option></select></div>
                <div style="display:flex;gap:10px;margin-top:20px;"><button type="submit" class="bntm-btn-primary">Update</button><button type="button" id="close-attendance-modal" class="bntm-btn-secondary">Cancel</button></div>
                <div id="edit-attendance-message"></div>
            </form>
        </div>
    </div>
    <script>
    (function() {
        const bntmAjax = { ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>', nonce: '<?php echo wp_create_nonce('bntm_hr_nonce'); ?>' };
        document.addEventListener('DOMContentLoaded', function() {
            const editAttendanceModal = document.getElementById('edit-attendance-modal');
            document.querySelectorAll('.edit-attendance').forEach(button => { button.addEventListener('click', function() { const id = this.getAttribute('data-id'); const clockIn = this.getAttribute('data-clock-in'); const clockOut = this.getAttribute('data-clock-out'); const status = this.getAttribute('data-status'); const formatDateTime = (datetime) => { if (!datetime) return ''; const d = new Date(datetime); return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}T${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`; }; document.querySelector('#edit-attendance-form [name="attendance_id"]').value = id; document.querySelector('#edit-attendance-form [name="clock_in"]').value = formatDateTime(clockIn); document.querySelector('#edit-attendance-form [name="clock_out"]').value = formatDateTime(clockOut); document.querySelector('#edit-attendance-form [name="status"]').value = status; editAttendanceModal.style.display = 'flex'; }); });
            document.getElementById('close-attendance-modal').addEventListener('click', function() { editAttendanceModal.style.display = 'none'; });
            document.getElementById('edit-attendance-form').addEventListener('submit', async function(e) { e.preventDefault(); const messageDiv = document.getElementById('edit-attendance-message'); messageDiv.innerHTML = '<p>Processing...</p>'; const formData = new FormData(this); formData.append('action', 'bntm_hr_update_attendance'); formData.append('nonce', bntmAjax.nonce); try { const response = await fetch(bntmAjax.ajax_url, { method: 'POST', body: formData }); const data = await response.json(); if (data.success) { messageDiv.innerHTML = '<p style="color:green;">' + data.data.message + '</p>'; setTimeout(() => location.reload(), 1500); } else { messageDiv.innerHTML = '<p style="color:red;">' + (data.data ? data.data.message : 'An error occurred') + '</p>'; } } catch (error) { messageDiv.innerHTML = '<p style="color:red;">Error: ' + error.message + '</p>'; } });
        });
    })();
    </script>
    <?php endif;
    return ob_get_clean();
}

add_action('wp_ajax_bntm_hr_update_attendance', 'bntm_ajax_hr_update_attendance');
function bntm_ajax_hr_update_attendance() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    $current_user = wp_get_current_user(); $is_wp_admin = current_user_can('manage_options'); $current_role = bntm_get_user_role($current_user->ID);
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) { wp_send_json_error(['message' => 'Permission denied.']); }
    global $wpdb; $prefix = $wpdb->prefix;
    $attendance_id = intval($_POST['attendance_id']); $clock_in = sanitize_text_field($_POST['clock_in']); $clock_out = sanitize_text_field($_POST['clock_out']); $status = sanitize_text_field($_POST['status']);
    $total_hours = null;
    if ($clock_in && $clock_out) { $start = new DateTime($clock_in); $end = new DateTime($clock_out); $interval = $start->diff($end); $total_hours = round($interval->h + ($interval->i / 60) + ($interval->days * 24), 2); }
    $update_data = ['clock_in' => $clock_in, 'status' => $status]; $format = ['%s', '%s'];
    if ($clock_out) { $update_data['clock_out'] = $clock_out; $update_data['total_hours'] = $total_hours; $format[] = '%s'; $format[] = '%f'; }
    $result = $wpdb->update($prefix . 'hr_attendance', $update_data, ['id' => $attendance_id], $format, ['%d']);
    if ($result === false) wp_send_json_error(['message' => 'Failed to update attendance record.']);
    wp_send_json_success(['message' => 'Attendance record updated successfully!']);
}


/* ---------- LEAVES VIEW ---------- */

function bntm_hr_leaves_view($user_id, $can_manage) {
    global $wpdb; $prefix = $wpdb->prefix; ob_start();
    if (!$can_manage): $leave_types = explode(',', bntm_get_setting('hr_leave_types', 'Sick Leave,Vacation,Personal,Bereavement')); ?>
        <div class="bntm-form-section"><h3>Request Leave</h3><form id="leave-request-form" class="bntm-form">
            <div class="bntm-form-group"><label>Leave Type</label><select id="leave-type" required><option value="">Select type...</option><?php foreach ($leave_types as $type): ?><option value="<?php echo esc_attr(trim($type)); ?>"><?php echo esc_html(trim($type)); ?></option><?php endforeach; ?></select></div>
            <div class="bntm-form-row"><div class="bntm-form-group"><label>Start Date</label><input type="date" id="leave-start-date" required /></div><div class="bntm-form-group"><label>End Date</label><input type="date" id="leave-end-date" required /></div></div>
            <div class="bntm-form-group"><label>Reason</label><textarea id="leave-reason" rows="3"></textarea></div>
            <button type="submit" class="bntm-btn-primary">Submit Request</button><div id="leave-request-message"></div>
        </form></div>
    <?php endif;
    if ($can_manage) { $leaves = $wpdb->get_results("SELECT l.*, u.display_name FROM {$prefix}hr_leave_requests l LEFT JOIN {$wpdb->users} u ON l.employee_id = u.ID ORDER BY l.created_at DESC"); }
    else { $leaves = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$prefix}hr_leave_requests WHERE employee_id = %d ORDER BY created_at DESC", $user_id)); }
    ?>
    <div class="bntm-form-section"><h3><?php echo $can_manage ? 'All Leave Requests' : 'My Leave Requests'; ?></h3>
        <?php if ($leaves): ?>
        <div class="bntm-table-wrapper"><table class="bntm-table"><thead><tr><?php if ($can_manage): ?><th>Employee</th><?php endif; ?><th>Type</th><th>Start</th><th>End</th><th>Days</th><th>Status</th><th>Actions</th></tr></thead><tbody>
            <?php foreach ($leaves as $leave): $status_class = $leave->status === 'approved' ? 'bntm-notice-success' : ($leave->status === 'rejected' ? 'bntm-notice-error' : ''); ?>
                <tr><?php if ($can_manage): ?><td><?php echo esc_html($leave->display_name); ?></td><?php endif; ?>
                <td><?php echo esc_html($leave->leave_type); ?></td>
                <td><?php echo esc_html(date('M d, Y', strtotime($leave->start_date))); ?></td>
                <td><?php echo esc_html(date('M d, Y', strtotime($leave->end_date))); ?></td>
                <td><?php echo esc_html($leave->total_days); ?></td>
                <td><span class="<?php echo $status_class; ?>" style="padding:4px 8px;border-radius:4px;display:inline-block;"><?php echo esc_html($leave->status); ?></span></td>
                <td><?php if ($can_manage && $leave->status === 'pending'): ?>
                    <button class="bntm-btn-small approve-leave" data-id="<?php echo $leave->id; ?>" style="display:inline-flex;align-items:center;gap:4px;"><?php echo bntm_hr_svg('check', 12); ?> Approve</button>
                    <button class="bntm-btn-small bntm-btn-danger reject-leave" data-id="<?php echo $leave->id; ?>" style="display:inline-flex;align-items:center;gap:4px;"><?php echo bntm_hr_svg('close', 12); ?> Reject</button>
                <?php endif; ?></td></tr>
            <?php endforeach; ?></tbody></table></div>
        <?php else: ?><p>No leave requests found.</p><?php endif; ?></div>
    <script>
    jQuery(document).ready(function($) {
        const bntmAjax = { ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>', nonce: '<?php echo wp_create_nonce('bntm_hr_nonce'); ?>' };
        $('#leave-request-form').on('submit', async function(e) { e.preventDefault(); const response = await $.ajax({ url: bntmAjax.ajax_url, method: 'POST', data: { action: 'bntm_hr_submit_leave', nonce: bntmAjax.nonce, leave_type: $('#leave-type').val(), start_date: $('#leave-start-date').val(), end_date: $('#leave-end-date').val(), reason: $('#leave-reason').val() } }); const messageDiv = $('#leave-request-message'); if (response.success) { messageDiv.html('<p style="color:green;margin-top:10px;">' + response.data.message + '</p>'); setTimeout(function() { location.reload(); }, 2000); } else { messageDiv.html('<p style="color:red;margin-top:10px;">' + response.data.message + '</p>'); } });
        $('.approve-leave').on('click', async function() { if (!confirm('Approve this leave request?')) return; const response = await $.ajax({ url: bntmAjax.ajax_url, method: 'POST', data: { action: 'bntm_hr_approve_leave', nonce: bntmAjax.nonce, leave_id: $(this).data('id') } }); alert(response.data.message); if (response.success) location.reload(); });
        $('.reject-leave').on('click', async function() { if (!confirm('Reject this leave request?')) return; const response = await $.ajax({ url: bntmAjax.ajax_url, method: 'POST', data: { action: 'bntm_hr_reject_leave', nonce: bntmAjax.nonce, leave_id: $(this).data('id') } }); alert(response.data.message); if (response.success) location.reload(); });
    });
    </script>
    <?php return ob_get_clean();
}

add_action('wp_ajax_bntm_hr_submit_leave', 'bntm_ajax_hr_submit_leave');
function bntm_ajax_hr_submit_leave() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    $user_id = get_current_user_id(); $leave_type = sanitize_text_field($_POST['leave_type']); $start_date = sanitize_text_field($_POST['start_date']); $end_date = sanitize_text_field($_POST['end_date']); $reason = sanitize_textarea_field($_POST['reason']);
    $start = new DateTime($start_date); $end = new DateTime($end_date); $interval = $start->diff($end); $total_days = $interval->days + 1;
    global $wpdb; $prefix = $wpdb->prefix;
    $result = $wpdb->insert($prefix . 'hr_leave_requests', ['rand_id' => bntm_rand_id(), 'employee_id' => $user_id, 'business_id' => 1, 'leave_type' => $leave_type, 'start_date' => $start_date, 'end_date' => $end_date, 'total_days' => $total_days, 'reason' => $reason, 'status' => 'pending'], ['%s', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s']);
    if ($result) wp_send_json_success(['message' => 'Leave request submitted successfully.']);
    else wp_send_json_error(['message' => 'Failed to submit leave request.']);
}

add_action('wp_ajax_bntm_hr_approve_leave', 'bntm_ajax_hr_approve_leave');
function bntm_ajax_hr_approve_leave() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    $current_user = wp_get_current_user(); $is_wp_admin = current_user_can('manage_options'); $current_role = bntm_get_user_role($current_user->ID);
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) { wp_send_json_error(['message' => 'Unauthorized.']); }
    global $wpdb; $prefix = $wpdb->prefix; $leave_id = intval($_POST['leave_id']); $user_id = get_current_user_id();
    $result = $wpdb->update($prefix . 'hr_leave_requests', ['status' => 'approved', 'approved_by' => $user_id, 'approved_at' => current_time('mysql')], ['id' => $leave_id], ['%s', '%d', '%s'], ['%d']);
    if ($result !== false) wp_send_json_success(['message' => 'Leave request approved.']);
    else wp_send_json_error(['message' => 'Failed to approve leave request.']);
}

add_action('wp_ajax_bntm_hr_reject_leave', 'bntm_ajax_hr_reject_leave');
function bntm_ajax_hr_reject_leave() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    $current_user = wp_get_current_user(); $is_wp_admin = current_user_can('manage_options'); $current_role = bntm_get_user_role($current_user->ID);
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) { wp_send_json_error(['message' => 'Unauthorized.']); }
    global $wpdb; $prefix = $wpdb->prefix; $leave_id = intval($_POST['leave_id']); $user_id = get_current_user_id();
    $result = $wpdb->update($prefix . 'hr_leave_requests', ['status' => 'rejected', 'approved_by' => $user_id, 'approved_at' => current_time('mysql')], ['id' => $leave_id], ['%s', '%d', '%s'], ['%d']);
    if ($result !== false) wp_send_json_success(['message' => 'Leave request rejected.']);
    else wp_send_json_error(['message' => 'Failed to reject leave request.']);
}


/* =========================================================
   PAYSLIPS VIEW — with Payment Details modal
   ========================================================= */

function bntm_hr_payslips_view($user_id, $can_manage) {
    global $wpdb; $prefix = $wpdb->prefix; ob_start();
    if ($can_manage) {
        $employees = get_users(['exclude' => [1], 'orderby' => 'display_name']);
        $payslips  = $wpdb->get_results("SELECT p.*, u.display_name FROM {$prefix}hr_payslips p LEFT JOIN {$wpdb->users} u ON p.employee_id = u.ID ORDER BY p.created_at DESC LIMIT 50");
    } else {
        $payslips = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$prefix}hr_payslips WHERE employee_id = %d ORDER BY created_at DESC", $user_id));
    }
    ?>
    <div class="bntm-form-section">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h3 style="margin:0;display:flex;align-items:center;gap:8px;"><?php echo bntm_hr_svg('money', 18); ?> Payslip Management</h3>
            <?php if ($can_manage): ?>
            <button id="generate-payslip-btn" class="bntm-btn-primary" style="display:inline-flex;align-items:center;gap:6px;">
                <?php echo bntm_hr_svg('add', 16); ?> Generate Payslip
            </button>
            <?php endif; ?>
        </div>

        <?php if ($can_manage): ?>
        <!-- Generate Payslip Modal -->
        <div id="payslip-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
            <div style="background:white;padding:30px;border-radius:8px;max-width:600px;width:90%;max-height:90vh;overflow-y:auto;">
                <h3 style="margin-top:0;">Generate Payslip(s)</h3>
                <form id="generate-payslip-form" class="bntm-form">
                    <div class="bntm-form-group"><label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="bulk-generate" style="width:unset"/><span>Generate for multiple employees</span></label></div>
                    <div id="single-employee-section"><div class="bntm-form-group"><label>Employee *</label><select name="employee_id" id="employee-select"><option value="">Select employee...</option><?php foreach ($employees as $emp): ?><option value="<?php echo $emp->ID; ?>"><?php echo esc_html($emp->display_name); ?></option><?php endforeach; ?></select></div></div>
                    <div id="bulk-employee-section" style="display:none;"><div class="bntm-form-group"><label>Select Employees *</label><div style="max-height:200px;overflow-y:auto;border:1px solid #e5e7eb;border-radius:6px;padding:10px;"><?php foreach ($employees as $emp): ?><label style="display:block;margin-bottom:5px;"><input type="checkbox" class="bulk-employee" value="<?php echo $emp->ID; ?>" /><?php echo esc_html($emp->display_name); ?></label><?php endforeach; ?></div></div></div>
                    <div class="bntm-form-row"><div class="bntm-form-group"><label>Period Start *</label><input type="date" name="period_start" required /></div><div class="bntm-form-group"><label>Period End *</label><input type="date" name="period_end" required /></div></div>
                    <div class="bntm-form-group"><label style="display:flex;justify-content:space-between;align-items:center;"><span>Manual Adjustments</span><button type="button" id="add-adjustment-btn" class="bntm-btn-small" style="display:inline-flex;align-items:center;gap:4px;"><?php echo bntm_hr_svg('add', 12); ?> Add</button></label><div id="adjustments-container" style="margin-top:10px;"></div></div>
                    <div style="display:flex;gap:10px;margin-top:20px;"><button type="submit" class="bntm-btn-primary" style="display:inline-flex;align-items:center;gap:6px;"><?php echo bntm_hr_svg('download', 16); ?> Generate &amp; Download PDF</button><button type="button" id="close-payslip-modal" class="bntm-btn-secondary">Cancel</button></div>
                    <div id="payslip-modal-message"></div>
                </form>
            </div>
        </div>

        <!-- Payment Details Modal -->
        <div id="payment-details-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:2000;align-items:center;justify-content:center;">
            <div style="background:white;border-radius:12px;max-width:560px;width:90%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
                <!-- Header -->
                <div style="background:linear-gradient(135deg,#1d4ed8,#0891b2);padding:20px 24px;border-radius:12px 12px 0 0;color:white;display:flex;justify-content:space-between;align-items:center;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <?php echo bntm_hr_svg('payment', 22, 'color:white;'); ?>
                        <div>
                            <div style="font-size:16px;font-weight:700;">Payment Details</div>
                            <div id="pd-employee-name" style="font-size:13px;opacity:0.85;"></div>
                        </div>
                    </div>
                    <button id="close-payment-modal" style="background:rgba(255,255,255,0.2);border:none;color:white;width:32px;height:32px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;">
                        <?php echo bntm_hr_svg('close', 18, 'color:white;'); ?>
                    </button>
                </div>
                <div id="payment-details-body" style="padding:24px;">
                    <div style="text-align:center;color:#6b7280;padding:20px;">Loading...</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Month Filter and Bulk Actions -->
        <div style="display:flex;gap:15px;align-items:center;margin-bottom:20px;flex-wrap:wrap;">
            <div style="display:flex;gap:10px;align-items:center;">
                <label for="month-filter" style="font-weight:500;margin:0;display:flex;align-items:center;gap:6px;"><?php echo bntm_hr_svg('filter', 14); ?> Month:</label>
                <select id="month-filter" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;background:white;cursor:pointer;">
                    <option value="">All Months</option>
                    <?php for ($i = 11; $i >= 0; $i--) { $date = date('Y-m', strtotime("-$i months")); $label = date('F Y', strtotime($date)); echo '<option value="' . esc_attr($date) . '">' . esc_html($label) . '</option>'; } ?>
                </select>
            </div>
            <?php if ($can_manage && $payslips): ?>
            <button id="bulk-download-payslips" class="bntm-btn-primary" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;font-size:14px;">
                <?php echo bntm_hr_svg('download', 14); ?> Download Selected
            </button>
            <?php endif; ?>
        </div>

        <!-- Payslips Table -->
        <?php if ($payslips): ?>
        <div class="bntm-table-wrapper">
            <table class="bntm-table">
                <thead>
                    <tr>
                        <?php if ($can_manage): ?><th><input type="checkbox" id="select-all-payslips" /></th><?php endif; ?>
                        <?php if ($can_manage): ?><th>Employee</th><?php endif; ?>
                        <th>Period</th>
                        <th>Basic Pay</th>
                        <th>Deductions</th>
                        <th>Net Pay</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payslips as $payslip): ?>
                    <tr>
                        <?php if ($can_manage): ?><td><input type="checkbox" class="payslip-checkbox" value="<?php echo $payslip->id; ?>" data-imported="<?php echo $payslip->is_imported; ?>" /></td><?php endif; ?>
                        <?php if ($can_manage): ?><td><?php echo esc_html($payslip->display_name); ?></td><?php endif; ?>
                        <td><?php echo date('M d', strtotime($payslip->period_start)) . ' - ' . date('M d, Y', strtotime($payslip->period_end)); ?></td>
                        <td class="bntm-stat-income">&#8369;<?php echo number_format($payslip->basic_pay, 2); ?></td>
                        <td class="bntm-stat-expense">&#8369;<?php echo number_format($payslip->total_deductions, 2); ?></td>
                        <td><strong>&#8369;<?php echo number_format($payslip->net_pay, 2); ?></strong></td>
                        <td>
                            <?php if ($payslip->is_imported): ?>
                                <span style="padding:4px 8px;border-radius:4px;display:inline-flex;align-items:center;gap:4px;background:#d1fae5;color:#065f46;font-size:12px;"><?php echo bntm_hr_svg('check', 12); ?> Imported</span>
                            <?php else: ?>
                                <span style="padding:4px 8px;border-radius:4px;display:inline-block;background:#f3f4f6;color:#6b7280;font-size:12px;">Not Imported</span>
                            <?php endif; ?>
                        </td>
                        <td style="white-space:nowrap;">
                            <button class="bntm-btn-small download-payslip" data-id="<?php echo $payslip->id; ?>" style="display:inline-flex;align-items:center;gap:4px;">
                                <?php echo bntm_hr_svg('download', 12); ?> PDF
                            </button>
                            <?php if ($can_manage): ?>
                                <button class="bntm-btn-small payment-details-btn"
                                        data-id="<?php echo $payslip->id; ?>"
                                        style="display:inline-flex;align-items:center;gap:4px;background:#0891b2;color:white;border-color:#0891b2;"
                                        title="View payment details, bank info, and QR code">
                                    <?php echo bntm_hr_svg('payment', 12, 'color:white;'); ?> Pay Details
                                </button>
                                <button class="bntm-btn-small bntm-btn-danger delete-payslip"
                                        data-id="<?php echo $payslip->id; ?>"
                                        data-name="<?php echo esc_attr($payslip->display_name ?? ''); ?>"
                                        data-period="<?php echo esc_attr(date('M d', strtotime($payslip->period_start)) . ' - ' . date('M d, Y', strtotime($payslip->period_end))); ?>"
                                        style="display:inline-flex;align-items:center;gap:4px;">
                                    <?php echo bntm_hr_svg('delete', 12); ?> Delete
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($can_manage): ?>
        <div style="margin-top:15px;display:flex;gap:10px;">
            <button id="bulk-import-payslips" class="bntm-btn-primary" style="display:inline-flex;align-items:center;gap:6px;"><?php echo bntm_hr_svg('import', 14); ?> Import Selected to Finance</button>
            <button id="bulk-revert-payslips" class="bntm-btn-secondary" style="display:inline-flex;align-items:center;gap:6px;"><?php echo bntm_hr_svg('revert', 14); ?> Revert Selected</button>
        </div>
        <?php endif; ?>
        <?php else: ?><p>No payslips found.</p><?php endif; ?>
    </div>

    <script>
    (function() {
        const bntmAjax = { ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>', nonce: '<?php echo wp_create_nonce('bntm_hr_nonce'); ?>' };
        document.addEventListener('DOMContentLoaded', function() {

            // ── GENERATE PAYSLIP ──
            const bulkCheckbox = document.getElementById('bulk-generate');
            if (bulkCheckbox) {
                bulkCheckbox.addEventListener('change', function() {
                    const ss = document.getElementById('single-employee-section'); const bs = document.getElementById('bulk-employee-section'); const es = document.getElementById('employee-select');
                    if (this.checked) { ss.style.display = 'none'; bs.style.display = 'block'; es.removeAttribute('required'); }
                    else { ss.style.display = 'block'; bs.style.display = 'none'; es.setAttribute('required', 'required'); }
                });
            }
            const generateBtn = document.getElementById('generate-payslip-btn');
            const modal = document.getElementById('payslip-modal');
            if (generateBtn) generateBtn.addEventListener('click', () => modal.style.display = 'flex');
            const closeBtn = document.getElementById('close-payslip-modal');
            if (closeBtn) closeBtn.addEventListener('click', () => { modal.style.display = 'none'; document.getElementById('generate-payslip-form').reset(); document.getElementById('payslip-modal-message').innerHTML = ''; });

            // Adjustments
            let adjCounter = 0;
            const addAdjBtn = document.getElementById('add-adjustment-btn');
            if (addAdjBtn) {
                addAdjBtn.addEventListener('click', function() {
                    adjCounter++;
                    const container = document.getElementById('adjustments-container');
                    const row = document.createElement('div');
                    row.style.cssText = 'display:grid;grid-template-columns:1fr 1fr auto auto;gap:10px;margin-bottom:10px;align-items:end;';
                    row.innerHTML = `<div class="bntm-form-group" style="margin:0;"><label style="font-size:12px;">Description</label><input type="text" class="adjustment-description" placeholder="e.g., Bonus, Deduction" required /></div><div class="bntm-form-group" style="margin:0;"><label style="font-size:12px;">Amount (&#8369;)</label><input type="number" class="adjustment-amount" step="0.01" min="0" placeholder="0.00" required /></div><div class="bntm-form-group" style="margin:0;"><label style="font-size:12px;">Type</label><select class="adjustment-type" style="padding:8px;"><option value="increase">+ Add</option><option value="deduction">- Deduct</option></select></div><button type="button" class="remove-adj" style="padding:8px 12px;background:#ef4444;color:white;border:none;border-radius:6px;cursor:pointer;font-size:12px;">X</button>`;
                    container.appendChild(row);
                    row.querySelector('.remove-adj').addEventListener('click', function() { row.remove(); });
                });
            }

            // Submit generate
            const form = document.getElementById('generate-payslip-form');
            if (form) {
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const messageDiv = document.getElementById('payslip-modal-message');
                    const isBulk = document.getElementById('bulk-generate').checked;
                    let employeeIds = [];
                    if (isBulk) { document.querySelectorAll('.bulk-employee:checked').forEach(cb => employeeIds.push(cb.value)); if (employeeIds.length === 0) { messageDiv.innerHTML = '<p style="color:red;">Please select at least one employee.</p>'; return; } }
                    else { const singleId = document.getElementById('employee-select').value; if (!singleId) { messageDiv.innerHTML = '<p style="color:red;">Please select an employee.</p>'; return; } employeeIds = [singleId]; }
                    const formData = new FormData(); formData.append('action', 'bntm_hr_generate_payslip'); formData.append('nonce', bntmAjax.nonce); formData.append('employee_ids', JSON.stringify(employeeIds)); formData.append('period_start', this.querySelector('[name="period_start"]').value); formData.append('period_end', this.querySelector('[name="period_end"]').value);
                    const adjustments = []; document.querySelectorAll('.adjustment-description').forEach((el, i) => { const amt = parseFloat(document.querySelectorAll('.adjustment-amount')[i].value); const type = document.querySelectorAll('.adjustment-type')[i].value; if (el.value.trim() && amt > 0) adjustments.push({ description: el.value.trim(), amount: amt, type }); }); formData.append('adjustments', JSON.stringify(adjustments));
                    messageDiv.innerHTML = '<p>Generating...</p>';
                    try { const response = await fetch(bntmAjax.ajax_url, { method: 'POST', body: formData }); const data = await response.json(); if (data.success) { messageDiv.innerHTML = '<p style="color:green;">' + data.data.message + '</p>'; setTimeout(() => location.reload(), 2000); } else { messageDiv.innerHTML = '<p style="color:red;">' + (data.data ? data.data.message : 'Error') + '</p>'; } } catch (error) { messageDiv.innerHTML = '<p style="color:red;">Error: ' + error.message + '</p>'; }
                });
            }

            // ── DOWNLOAD PAYSLIP ──
            document.querySelectorAll('.download-payslip').forEach(btn => {
                btn.addEventListener('click', async function() {
                    const payslipId = this.dataset.id; const origText = this.innerHTML; this.innerHTML = 'Loading...'; this.disabled = true;
                    try {
                        const fd = new FormData(); fd.append('action', 'bntm_hr_generate_payslip_token'); fd.append('nonce', bntmAjax.nonce); fd.append('payslip_id', payslipId);
                        const response = await fetch(bntmAjax.ajax_url, { method: 'POST', body: fd }); const data = await response.json();
                        if (data.success) window.open(data.data.url, '_blank');
                        else alert('Failed: ' + (data.data?.message || 'Unknown error'));
                    } catch (error) { alert('Error: ' + error.message); }
                    finally { this.innerHTML = origText; this.disabled = false; }
                });
            });

            // ── PAYMENT DETAILS MODAL ──
            const paymentModal = document.getElementById('payment-details-modal');
            const paymentBody  = document.getElementById('payment-details-body');
            const closePaymentModal = document.getElementById('close-payment-modal');
            if (closePaymentModal) { closePaymentModal.addEventListener('click', () => { paymentModal.style.display = 'none'; }); }

            document.querySelectorAll('.payment-details-btn').forEach(btn => {
                btn.addEventListener('click', async function() {
                    const payslipId = this.dataset.id;
                    paymentModal.style.display = 'flex';
                    paymentBody.innerHTML = '<div style="text-align:center;padding:30px;color:#6b7280;">Loading payment details...</div>';
                    document.getElementById('pd-employee-name').textContent = '';
                    try {
                        const fd = new FormData();
                        fd.append('action', 'bntm_hr_get_payment_details');
                        fd.append('nonce', bntmAjax.nonce);
                        fd.append('payslip_id', payslipId);
                        const response = await fetch(bntmAjax.ajax_url, { method: 'POST', body: fd });
                        const result = await response.json();
                        if (!result.success) { paymentBody.innerHTML = '<p style="color:red;text-align:center;">Error: ' + (result.data?.message || 'Failed to load') + '</p>'; return; }
                        const d = result.data; const p = d.payslip; const e = d.employee; const b = d.bank;
                        document.getElementById('pd-employee-name').textContent = e.name + (e.position ? ' — ' + e.position : '');

                        let bankHtml = '';
                        const hasBankInfo = b.bank_name || b.bank_account_name || b.bank_account_number;
                        if (hasBankInfo) {
                            bankHtml = `<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:16px;margin-bottom:16px;">
                                <div style="font-weight:700;color:#1d4ed8;margin-bottom:12px;display:flex;align-items:center;gap:6px;font-size:13px;text-transform:uppercase;letter-spacing:.5px;">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M4 10v7h3v-7H4zm6 0v7h3v-7h-3zM2 22h19v-3H2v3zm14-12v7h3v-7h-3zM11.5 1L2 6v2h19V6l-9.5-5z"/></svg>
                                    Bank / Payment Account
                                </div>
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                                    ${b.bank_name ? `<div><div style="font-size:11px;color:#6b7280;text-transform:uppercase;margin-bottom:3px;">Bank / Provider</div><div style="font-weight:600;color:#1e293b;">${escH(b.bank_name)}</div></div>` : ''}
                                    ${b.bank_account_name ? `<div><div style="font-size:11px;color:#6b7280;text-transform:uppercase;margin-bottom:3px;">Account Name</div><div style="font-weight:600;color:#1e293b;">${escH(b.bank_account_name)}</div></div>` : ''}
                                    ${b.bank_account_number ? `<div><div style="font-size:11px;color:#6b7280;text-transform:uppercase;margin-bottom:3px;">Account Number</div><div style="font-weight:700;color:#0f172a;font-size:15px;letter-spacing:1px;">${escH(b.bank_account_number)}</div></div>` : ''}
                                    ${b.bank_branch ? `<div><div style="font-size:11px;color:#6b7280;text-transform:uppercase;margin-bottom:3px;">Branch</div><div style="font-weight:600;color:#1e293b;">${escH(b.bank_branch)}</div></div>` : ''}
                                </div>
                            </div>`;
                        } else {
                            bankHtml = `<div style="background:#fef9c3;border:1px solid #fde68a;border-radius:8px;padding:12px;margin-bottom:16px;text-align:center;font-size:13px;color:#92400e;">No bank account details on file for this employee.</div>`;
                        }

                        let qrHtml = '';
                        if (b.qr_image) {
                            qrHtml = `<div style="text-align:center;margin-bottom:16px;">
                                <div style="font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;font-weight:600;">QR Code for Payment</div>
                                <div style="display:inline-block;padding:12px;background:white;border:2px solid #e5e7eb;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.08);">
                                    <img src="${b.qr_image}" alt="QR Code" style="max-width:180px;max-height:180px;display:block;" />
                                </div>
                            </div>`;
                        }

                        // Payslip details
                        const deductionRows = Object.values(p.deductions || {}).map(d => `<tr><td style="padding:4px 6px;color:#6b7280;">${escH(d.name)}</td><td style="padding:4px 6px;text-align:right;color:#dc2626;">-&#8369;${fmtNum(d.amount)}</td></tr>`).join('');
                        const adjustmentRows = (p.adjustments || []).map(a => `<tr><td style="padding:4px 6px;color:#6b7280;">${escH(a.description)} ${a.type === 'increase' ? '(+)' : '(-)'}</td><td style="padding:4px 6px;text-align:right;color:${a.type === 'increase' ? '#059669' : '#dc2626'};">${a.type === 'increase' ? '+' : '-'}&#8369;${fmtNum(a.amount)}</td></tr>`).join('');

                        paymentBody.innerHTML = `
                            ${bankHtml}
                            ${qrHtml}
                            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin-bottom:16px;">
                                <div style="font-weight:700;color:#374151;margin-bottom:12px;font-size:13px;text-transform:uppercase;letter-spacing:.5px;">Payslip Summary</div>
                                <div style="font-size:12px;color:#6b7280;margin-bottom:10px;">Period: <strong style="color:#1e293b;">${p.period_start} — ${p.period_end}</strong> &nbsp;|&nbsp; Hours: <strong style="color:#1e293b;">${p.total_hours}</strong></div>
                                <table style="width:100%;border-collapse:collapse;">
                                    <tr><td style="padding:4px 6px;color:#6b7280;">Basic Pay</td><td style="padding:4px 6px;text-align:right;color:#059669;">&#8369;${p.basic_pay}</td></tr>
                                    ${parseFloat(p.overtime_pay) > 0 ? `<tr><td style="padding:4px 6px;color:#6b7280;">Overtime Pay</td><td style="padding:4px 6px;text-align:right;color:#059669;">&#8369;${p.overtime_pay}</td></tr>` : ''}
                                    ${deductionRows}
                                    ${adjustmentRows}
                                    <tr style="border-top:2px solid #e2e8f0;"><td style="padding:8px 6px;font-weight:700;font-size:15px;">Net Pay</td><td style="padding:8px 6px;text-align:right;font-weight:700;font-size:18px;color:#0f172a;">&#8369;${p.net_pay}</td></tr>
                                </table>
                            </div>
                            ${p.is_imported ? '<div style="text-align:center;font-size:12px;color:#059669;background:#f0fdf4;padding:8px;border-radius:6px;border:1px solid #bbf7d0;">This payslip has been imported to Finance</div>' : ''}
                        `;
                    } catch (error) { paymentBody.innerHTML = '<p style="color:red;text-align:center;">Error: ' + error.message + '</p>'; }
                });
            });

            function escH(str) { if (!str) return ''; return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
            function fmtNum(n) { return parseFloat(n || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); }

            // ── DELETE PAYSLIP ──
            document.querySelectorAll('.delete-payslip').forEach(btn => {
                btn.addEventListener('click', async function() {
                    const payslipId = this.dataset.id; const empName = this.dataset.name || 'this employee'; const period = this.dataset.period || '';
                    if (!confirm(`Delete payslip for ${empName} (${period})?\n\nIf imported to Finance, the finance record will also be removed.`)) return;
                    const origText = this.innerHTML; this.innerHTML = 'Deleting...'; this.disabled = true;
                    try {
                        const fd = new FormData(); fd.append('action', 'bntm_hr_delete_payslip'); fd.append('nonce', bntmAjax.nonce); fd.append('payslip_id', payslipId);
                        const response = await fetch(bntmAjax.ajax_url, { method: 'POST', body: fd }); const data = await response.json();
                        if (data.success) {
                            this.closest('tr').remove();
                            const toast = document.createElement('div'); toast.textContent = data.data.message; toast.style.cssText = 'position:fixed;bottom:20px;right:20px;background:#059669;color:white;padding:12px 20px;border-radius:8px;z-index:9999;font-size:14px;font-weight:500;'; document.body.appendChild(toast); setTimeout(() => toast.remove(), 3000);
                        } else { alert('Error: ' + (data.data ? data.data.message : 'Failed to delete payslip')); this.innerHTML = origText; this.disabled = false; }
                    } catch (error) { alert('Error: ' + error.message); this.innerHTML = origText; this.disabled = false; }
                });
            });

            // Select all
            const selectAll = document.getElementById('select-all-payslips');
            if (selectAll) { selectAll.addEventListener('change', function() { document.querySelectorAll('.payslip-checkbox').forEach(cb => cb.checked = this.checked); }); }

            // Month filter
            const monthFilter = document.getElementById('month-filter');
            if (monthFilter) {
                monthFilter.addEventListener('change', function() {
                    const sel = this.value;
                    const colIdx = <?php echo $can_manage ? '3' : '2'; ?>;
                    document.querySelectorAll('.bntm-table tbody tr').forEach(row => {
                        if (!sel) { row.style.display = ''; return; }
                        const cell = row.querySelector('td:nth-child(' + colIdx + ')');
                        if (!cell) { row.style.display = ''; return; }
                        const months = {Jan:'01',Feb:'02',Mar:'03',Apr:'04',May:'05',Jun:'06',Jul:'07',Aug:'08',Sep:'09',Oct:'10',Nov:'11',Dec:'12',January:'01',February:'02',March:'03',April:'04',June:'06',July:'07',August:'08',September:'09',October:'10',November:'11',December:'12'};
                        const text = cell.textContent.trim();
                        const m = text.match(/([A-Za-z]+)\s+\d+,\s+(\d{4})/);
                        if (m) { const mn = months[m[1]] || ''; const yr = m[2]; row.style.display = (yr + '-' + mn === sel) ? '' : 'none'; }
                        else row.style.display = 'none';
                    });
                });
            }

            // Bulk download
            const bulkDownloadBtn = document.getElementById('bulk-download-payslips');
            if (bulkDownloadBtn) {
                bulkDownloadBtn.addEventListener('click', async function() {
                    const selected = []; document.querySelectorAll('.payslip-checkbox:checked').forEach(cb => selected.push(cb.value));
                    if (selected.length === 0) { alert('Please select at least one payslip to download.'); return; }
                    const origText = this.innerHTML; this.innerHTML = 'Generating...'; this.disabled = true;
                    try {
                        const fd = new FormData(); fd.append('action', 'bntm_hr_bulk_download_payslips'); fd.append('nonce', bntmAjax.nonce); fd.append('payslip_ids', JSON.stringify(selected));
                        const response = await fetch(bntmAjax.ajax_url, { method: 'POST', body: fd }); const data = await response.json();
                        if (data.success && data.data.download_urls) { let delay = 0; data.data.download_urls.forEach(url => { setTimeout(() => window.open(url, '_blank'), delay); delay += 300; }); }
                        else { alert('Error: ' + (data.data?.message || 'Failed to generate download links')); }
                    } catch (error) { alert('Error: ' + error.message); }
                    finally { this.innerHTML = origText; this.disabled = false; }
                });
            }

            // Bulk import
            const bulkImportBtn = document.getElementById('bulk-import-payslips');
            if (bulkImportBtn) {
                bulkImportBtn.addEventListener('click', async function() {
                    const selected = []; document.querySelectorAll('.payslip-checkbox:checked').forEach(cb => { if (cb.dataset.imported === '0') selected.push(cb.value); });
                    if (selected.length === 0) { alert('No eligible payslips selected (not already imported).'); return; }
                    if (!confirm(`Import ${selected.length} payslip(s) to Finance as expenses?`)) return;
                    const fd = new FormData(); fd.append('action', 'bntm_hr_import_payslips'); fd.append('nonce', bntmAjax.nonce); fd.append('payslip_ids', JSON.stringify(selected));
                    try { const response = await fetch(bntmAjax.ajax_url, { method: 'POST', body: fd }); const data = await response.json(); alert(data.data ? data.data.message : 'Operation completed'); if (data.success) location.reload(); } catch (error) { alert('Error: ' + error.message); }
                });
            }

            // Bulk revert
            const bulkRevertBtn = document.getElementById('bulk-revert-payslips');
            if (bulkRevertBtn) {
                bulkRevertBtn.addEventListener('click', async function() {
                    const selected = []; document.querySelectorAll('.payslip-checkbox:checked').forEach(cb => { if (cb.dataset.imported === '1') selected.push(cb.value); });
                    if (selected.length === 0) { alert('No eligible payslips selected (already imported).'); return; }
                    if (!confirm(`Revert ${selected.length} payslip(s) from Finance?`)) return;
                    const fd = new FormData(); fd.append('action', 'bntm_hr_revert_payslips'); fd.append('nonce', bntmAjax.nonce); fd.append('payslip_ids', JSON.stringify(selected));
                    try { const response = await fetch(bntmAjax.ajax_url, { method: 'POST', body: fd }); const data = await response.json(); alert(data.data ? data.data.message : 'Operation completed'); if (data.success) location.reload(); } catch (error) { alert('Error: ' + error.message); }
                });
            }
        });
    })();
    </script>
    <?php return ob_get_clean();
}


/* ── DEDUCTIONS AJAX ─────────────────────────────────────────── */

add_action('wp_ajax_bntm_hr_add_deduction', 'bntm_ajax_hr_add_deduction');
function bntm_ajax_hr_add_deduction() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    $current_user = wp_get_current_user(); $is_wp_admin = current_user_can('manage_options'); $current_role = bntm_get_user_role($current_user->ID);
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) { wp_send_json_error(['message' => 'Permission denied.']); }
    $name = sanitize_text_field($_POST['name']); $type = sanitize_text_field($_POST['type']); $value = floatval($_POST['value']); $description = sanitize_text_field($_POST['description']);
    $deductions = bntm_get_setting('hr_deductions', ''); $deductions_array = $deductions ? json_decode($deductions, true) : [];
    $key = sanitize_title($name); $deductions_array[$key] = ['name' => $name, 'type' => $type, 'value' => $value, 'description' => $description];
    bntm_set_setting('hr_deductions', json_encode($deductions_array));
    wp_send_json_success(['message' => 'Deduction added successfully!']);
}

add_action('wp_ajax_bntm_hr_delete_deduction', 'bntm_ajax_hr_delete_deduction');
function bntm_ajax_hr_delete_deduction() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    $current_user = wp_get_current_user(); $is_wp_admin = current_user_can('manage_options'); $current_role = bntm_get_user_role($current_user->ID);
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) { wp_send_json_error(['message' => 'Permission denied.']); }
    $key = sanitize_text_field($_POST['key']); $deductions = bntm_get_setting('hr_deductions', ''); $deductions_array = $deductions ? json_decode($deductions, true) : [];
    if (isset($deductions_array[$key])) { unset($deductions_array[$key]); bntm_set_setting('hr_deductions', json_encode($deductions_array)); wp_send_json_success(['message' => 'Deduction deleted successfully!']); }
    else { wp_send_json_error(['message' => 'Deduction not found.']); }
}

/* ── PAYSLIP GENERATION AJAX ─────────────────────────────────── */

add_action('wp_ajax_bntm_hr_generate_payslip', 'bntm_ajax_hr_generate_payslip');
function bntm_ajax_hr_generate_payslip() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    $current_user = wp_get_current_user(); $is_wp_admin = current_user_can('manage_options'); $current_role = bntm_get_user_role($current_user->ID);
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) { wp_send_json_error(['message' => 'Permission denied.']); }
    global $wpdb; $prefix = $wpdb->prefix;
    $employee_ids     = json_decode(stripslashes($_POST['employee_ids']), true);
    $period_start     = sanitize_text_field($_POST['period_start']);
    $period_end       = sanitize_text_field($_POST['period_end']);
    $deductions       = bntm_get_setting('hr_deductions', '');
    $deductions_array = $deductions ? json_decode($deductions, true) : [];
    $hourly_rate      = floatval(bntm_get_setting('hr_hourly_rate', '15.00'));
    $work_hours_per_day  = floatval(bntm_get_setting('hr_work_hours', '8'));
    $lunch_break_hours   = floatval(bntm_get_setting('hr_lunch_break_hours', '1'));
    $ot_rate_default     = floatval(bntm_get_setting('hr_ot_rate', '1'));
    $adjustments = isset($_POST['adjustments']) ? json_decode(stripslashes($_POST['adjustments']), true) : [];
    $generated_count = 0;
    foreach ($employee_ids as $employee_id) {
        $payroll_settings    = bntm_get_employee_payroll_settings($employee_id);
        $emp_hourly_rate     = $payroll_settings['hourly_rate'];
        $emp_work_hours      = $payroll_settings['work_hours'];
        $emp_lunch           = $payroll_settings['lunch_break_hours'];
        $total_hours         = bntm_calculate_payable_hours($employee_id, $period_start, $period_end, $emp_work_hours, $emp_lunch, 4);
        $overtime_data       = bntm_calculate_overtime_hours($employee_id, $period_start, $period_end);
        $basic_pay           = $total_hours * $emp_hourly_rate;
        $overtime_pay        = $overtime_data['total_hours'] * ($emp_hourly_rate * $ot_rate_default);
        $gross_pay           = $basic_pay + $overtime_pay;
        $total_deductions    = 0; $deductions_data = [];
        foreach ($deductions_array as $key => $deduction) {
            $amount = ($deduction['type'] === 'percentage') ? ($gross_pay * $deduction['value']) / 100 : $deduction['value'];
            $total_deductions += $amount;
            $deductions_data[$key] = ['name' => $deduction['name'], 'type' => $deduction['type'], 'value' => $deduction['value'], 'amount' => $amount];
        }
        $total_adj_increase = 0; $total_adj_deduction = 0; $adjustments_data = [];
        foreach ($adjustments as $adj) {
            $adj_amount = floatval($adj['amount']);
            $adjustments_data[] = ['description' => sanitize_text_field($adj['description']), 'amount' => $adj_amount, 'type' => $adj['type']];
            if ($adj['type'] === 'increase') $total_adj_increase += $adj_amount; else $total_adj_deduction += $adj_amount;
        }
        $net_pay = $gross_pay - $total_deductions + $total_adj_increase - $total_adj_deduction;
        $result = $wpdb->insert($prefix . 'hr_payslips', [
            'rand_id'          => bntm_rand_id(),
            'employee_id'      => $employee_id,
            'business_id'      => 1,
            'period_start'     => $period_start,
            'period_end'       => $period_end,
            'basic_pay'        => $basic_pay,
            'overtime_pay'     => $overtime_pay,
            'total_deductions' => $total_deductions,
            'net_pay'          => $net_pay,
            'deductions_data'  => json_encode($deductions_data),
            'adjustments_data' => json_encode($adjustments_data),
            'total_hours'      => $total_hours,
        ], ['%s','%d','%d','%s','%s','%f','%f','%f','%f','%s','%s','%f']);
        if ($result) $generated_count++;
    }
    if ($generated_count > 0) wp_send_json_success(['message' => "Generated {$generated_count} payslip(s) successfully!", 'reload' => true]);
    else wp_send_json_error(['message' => 'Failed to generate payslips.']);
}

function bntm_calculate_overtime_hours($employee_id, $period_start, $period_end) {
    global $wpdb; $prefix = $wpdb->prefix;
    $overtime_records = $wpdb->get_results($wpdb->prepare(
        "SELECT overtime_date as date, start_time, end_time, total_hours FROM {$prefix}hr_overtime WHERE employee_id = %d AND status = 'approved' AND DATE(overtime_date) BETWEEN %s AND %s ORDER BY overtime_date",
        $employee_id, $period_start, $period_end
    ));
    if (empty($overtime_records)) return ['total_hours' => 0, 'records' => []];
    $total_hours = 0; $records = [];
    foreach ($overtime_records as $record) {
        $ot_hours = floatval($record->total_hours);
        $records[] = ['date' => $record->date, 'start_time' => $record->start_time, 'end_time' => $record->end_time, 'hours' => $ot_hours];
        $total_hours += $ot_hours;
    }
    return ['total_hours' => round($total_hours, 2), 'records' => $records];
}

function bntm_calculate_payable_hours($employee_id, $period_start, $period_end, $max_hours_per_day = 8, $lunch_break_hours = 1, $lunch_threshold = 4) {
    global $wpdb; $prefix = $wpdb->prefix;
    $attendance_records = $wpdb->get_results($wpdb->prepare(
        "SELECT DATE(clock_in) as work_date, clock_in, clock_out, SUM(total_hours) as actual_hours, COUNT(*) as shifts FROM {$prefix}hr_attendance WHERE employee_id = %d AND DATE(clock_in) BETWEEN %s AND %s AND clock_out IS NOT NULL GROUP BY DATE(clock_in) ORDER BY clock_in",
        $employee_id, $period_start, $period_end
    ));
    $total_payable_hours = 0;
    foreach ($attendance_records as $record) {
        $actual_hours   = floatval($record->actual_hours);
        $shifts         = intval($record->shifts);
        $lunch_deduction = ($actual_hours > $lunch_threshold) ? $shifts * $lunch_break_hours : 0;
        $hours_after_lunch = max(0, $actual_hours - $lunch_deduction);
        $total_payable_hours += min($hours_after_lunch, $max_hours_per_day);
    }
    return round($total_payable_hours, 2);
}

/* ── PAYSLIP DOWNLOAD / TOKEN ────────────────────────────────── */

add_action('wp_ajax_bntm_hr_download_payslip',        'bntm_ajax_hr_download_payslip');
add_action('wp_ajax_nopriv_bntm_hr_download_payslip', 'bntm_ajax_hr_download_payslip');
function bntm_ajax_hr_download_payslip() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    global $wpdb; $prefix = $wpdb->prefix;
    $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
    if ($token) {
        $token_data = get_transient('payslip_token_' . $token);
        if (!$token_data) { wp_die('This download link has expired or already been used.'); }
        $payslip_id = $token_data['payslip_id'];
        delete_transient('payslip_token_' . $token);
    } else {
        $payslip_id = sanitize_text_field($_GET['payslip_id'] ?? 0);
    }
    if ($payslip_id === 'latest') $payslip = $wpdb->get_row("SELECT * FROM {$prefix}hr_payslips ORDER BY id DESC LIMIT 1");
    else $payslip = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$prefix}hr_payslips WHERE id = %d", intval($payslip_id)));
    if (!$payslip) wp_die('Payslip not found.');
    $employee       = get_userdata($payslip->employee_id);
    $position       = get_user_meta($payslip->employee_id, 'bntm_position', true);
    $department     = get_user_meta($payslip->employee_id, 'bntm_department', true);
    $deductions_data = json_decode($payslip->deductions_data, true) ?: [];
    $logo            = bntm_get_site_logo();
    $site_title      = bntm_get_site_title();
    $html = bntm_generate_payslip_pdf_html($payslip, $employee, $position, $department, $deductions_data, $logo, $site_title);
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    echo '<script>window.print();</script>';
    exit;
}

add_action('wp_ajax_bntm_hr_generate_payslip_token', 'bntm_ajax_generate_payslip_token');
function bntm_ajax_generate_payslip_token() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    $payslip_id = isset($_POST['payslip_id']) ? intval($_POST['payslip_id']) : 0;
    if (!$payslip_id) { wp_send_json_error(['message' => 'Invalid payslip ID']); }
    $token = wp_generate_password(32, false);
    set_transient('payslip_token_' . $token, ['payslip_id' => $payslip_id, 'generated_at' => time()], 300);
    $url = admin_url('admin-ajax.php') . '?action=bntm_hr_download_payslip&token=' . $token . '&nonce=' . wp_create_nonce('bntm_hr_nonce');
    wp_send_json_success(['url' => $url]);
}

add_action('wp_ajax_bntm_hr_bulk_download_payslips', 'bntm_ajax_hr_bulk_download_payslips');
function bntm_ajax_hr_bulk_download_payslips() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    $payslip_ids = json_decode(stripslashes($_POST['payslip_ids'] ?? '[]'), true);
    if (empty($payslip_ids) || !is_array($payslip_ids)) { wp_send_json_error(['message' => 'No payslips selected']); }
    $payslip_ids   = array_map('intval', $payslip_ids);
    $download_urls = [];
    foreach ($payslip_ids as $payslip_id) {
        $token = wp_generate_password(32, false);
        set_transient('payslip_token_' . $token, ['payslip_id' => $payslip_id, 'generated_at' => time()], 300);
        $download_urls[] = admin_url('admin-ajax.php') . '?action=bntm_hr_download_payslip&token=' . $token . '&nonce=' . wp_create_nonce('bntm_hr_nonce');
    }
    wp_send_json_success(['download_urls' => $download_urls, 'count' => count($download_urls)]);
}

/* ── PAYSLIP PDF HTML ────────────────────────────────────────── */

function bntm_generate_payslip_pdf_html($payslip, $employee, $position, $department, $deductions_data, $logo, $site_title) {
    $employee_name   = esc_html($employee->display_name);
    $position_text   = esc_html($position ?: 'N/A');
    $department_text = esc_html($department ?: 'N/A');
    $employee_int    = intval($payslip->employee_id);
    $employee_pin    = get_user_meta($employee_int, 'bntm_hr_pin', true);
    $emp_number      = get_user_meta($employee_int, 'bntm_employee_number', true);
    $site_name       = esc_html($site_title ?: get_bloginfo('name'));
    $period_start    = date('F d, Y', strtotime($payslip->period_start));
    $period_end      = date('F d, Y', strtotime($payslip->period_end));
    $period_short    = date('M d', strtotime($payslip->period_start)) . ' - ' . date('M d, Y', strtotime($payslip->period_end));
    $generated_date  = date('F d, Y h:i A');
    $payslip_number  = 'PS-' . str_pad($payslip->id, 6, '0', STR_PAD_LEFT);
    $ot_rate_default = floatval(bntm_get_setting('hr_ot_rate', '1'));
    $basic_pay        = floatval($payslip->basic_pay);
    $overtime_pay     = floatval($payslip->overtime_pay);
    $total_deductions = floatval($payslip->total_deductions);
    $adjustments_data = json_decode($payslip->adjustments_data ?? '[]', true) ?: [];
    $gross_pay        = $basic_pay + $overtime_pay;
    $net_pay          = floatval($payslip->net_pay);
    $total_hours      = floatval($payslip->total_hours ?? 0);
    $hourly_rate      = $total_hours > 0 ? ($basic_pay / $total_hours) : 0;
    $hours_breakdown  = bntm_get_detailed_hours_breakdown($payslip->employee_id, $payslip->period_start, $payslip->period_end);
    $approved_overtime = bntm_get_approved_overtime($payslip->employee_id, $payslip->period_start, $payslip->period_end);
    $total_adj_increase = 0; $total_adj_deduction = 0;
    foreach ($adjustments_data as $adjustment) { $a = floatval($adjustment['amount'] ?? 0); if ($adjustment['type'] === 'increase') $total_adj_increase += $a; else $total_adj_deduction += $a; }
    $logo_html = (!empty($logo)) ? '<img src="' . esc_url($logo) . '" alt="' . $site_name . '" style="max-height:50px;max-width:150px;">' : '';
    // Bank/QR for payslip PDF
    $bank_name           = get_user_meta($employee_int, 'bntm_bank_name', true);
    $bank_account_name   = get_user_meta($employee_int, 'bntm_bank_account_name', true);
    $bank_account_number = get_user_meta($employee_int, 'bntm_bank_account_number', true);
    $bank_branch         = get_user_meta($employee_int, 'bntm_bank_branch', true);
    $qr_image            = get_user_meta($employee_int, 'bntm_qr_image', true);
    $tin     = get_user_meta($employee_int, 'bntm_tin', true);
    $sss     = get_user_meta($employee_int, 'bntm_sss', true);
    $pagibig = get_user_meta($employee_int, 'bntm_pagibig', true);

    ob_start(); ?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Payslip - <?php echo $employee_name; ?> - <?php echo $period_short; ?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
@page{size:A4;margin:1.5cm;}
@media print{body{margin:0;padding:0;}.no-print{display:none;}}
body{font-family:Arial,sans-serif;font-size:10.5pt;line-height:1.45;color:#111;background:#fff;padding:20px;max-width:210mm;margin:0 auto;}
.header{margin-bottom:22px;padding-bottom:12px;border-bottom:2.5px solid #1d4ed8;}
.header-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;}
.company-name{font-size:17pt;font-weight:bold;color:#1d4ed8;}
.document-title{font-size:22pt;font-weight:bold;text-align:right;color:#1d4ed8;}
.header-info{display:flex;justify-content:space-between;font-size:9pt;color:#555;}
.content{display:flex;gap:22px;}
.employee-section{width:24%;border-right:1px solid #d1d5db;padding-right:14px;}
.calculation-section{width:76%;padding-left:14px;}
.info-label{font-weight:bold;font-size:8.5pt;text-transform:uppercase;color:#6b7280;margin-top:10px;margin-bottom:2px;}
.info-value{font-size:9.5pt;padding-bottom:7px;border-bottom:1px solid #e5e7eb;}
table{width:100%;border-collapse:collapse;margin-bottom:13px;}
th{text-align:left;font-weight:bold;padding:7px 5px;border-bottom:2px solid #111;font-size:9pt;text-transform:uppercase;background:#f8fafc;}
td{padding:5px 5px;border-bottom:1px solid #e5e7eb;font-size:9.5pt;}
.text-right{text-align:right;}
.section-header{font-weight:bold;background:#eff6ff;padding:6px 5px;border-top:2px solid #1d4ed8;border-bottom:1px solid #bfdbfe;font-size:9pt;text-transform:uppercase;color:#1d4ed8;}
.subtotal-row td{font-weight:bold;border-top:1.5px solid #6b7280;background:#f8fafc;}
.total-row td{font-weight:bold;font-size:11.5pt;border-top:2.5px solid #111;border-bottom:2.5px solid #111;background:#f0f9ff;}
.summary-box{margin-top:18px;padding:14px;border:2px solid #1d4ed8;border-radius:6px;background:#f0f9ff;}
.summary-row{display:flex;justify-content:space-between;padding:4px 0;font-size:9.5pt;}
.summary-row.total{font-size:13.5pt;font-weight:bold;padding-top:10px;margin-top:8px;border-top:2px solid #1d4ed8;}
.bank-box{margin-top:14px;padding:12px;border:1px solid #bfdbfe;border-radius:6px;background:#eff6ff;}
.bank-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px;}
.footer{margin-top:26px;padding-top:12px;border-top:1px solid #d1d5db;font-size:8.5pt;text-align:center;color:#6b7280;}
.small-text{font-size:8pt;color:#9ca3af;}
</style></head><body>

<div class="header">
    <div class="header-top">
        <div><?php if ($logo_html): ?><?php echo $logo_html; ?><br><?php endif; ?><div class="company-name"><?php echo $site_name; ?></div></div>
        <div class="document-title">PAYSLIP</div>
    </div>
    <div class="header-info">
        <div><strong>Payslip #:</strong> <?php echo $payslip_number; ?></div>
        <div><strong>Pay Period:</strong> <?php echo $period_short; ?></div>
        <div><strong>Generated:</strong> <?php echo date('M d, Y', strtotime($payslip->created_at ?? 'now')); ?></div>
    </div>
</div>

<div class="content">
    <div class="employee-section">
        <div class="info-label">Employee Name</div><div class="info-value"><?php echo $employee_name; ?></div>
        <?php if ($emp_number): ?><div class="info-label">Employee #</div><div class="info-value"><?php echo esc_html($emp_number); ?></div><?php endif; ?>
        <?php if ($employee_pin): ?><div class="info-label">PIN / ID</div><div class="info-value"><?php echo esc_html($employee_pin); ?></div><?php endif; ?>
        <div class="info-label">Position</div><div class="info-value"><?php echo $position_text; ?></div>
        <div class="info-label">Department</div><div class="info-value"><?php echo $department_text; ?></div>
        <div class="info-label">Pay Period</div><div class="info-value"><?php echo $period_start; ?><br>to<br><?php echo $period_end; ?></div>
        <?php if ($tin || $sss || $pagibig): ?>
        <div style="margin-top:14px;padding-top:10px;border-top:1px solid #e5e7eb;">
            <div style="font-size:8.5pt;font-weight:bold;text-transform:uppercase;color:#6b7280;margin-bottom:6px;">Gov't IDs</div>
            <?php if ($tin): ?><div class="info-label">TIN</div><div class="info-value" style="font-size:8.5pt;"><?php echo esc_html($tin); ?></div><?php endif; ?>
            <?php if ($sss): ?><div class="info-label">SSS</div><div class="info-value" style="font-size:8.5pt;"><?php echo esc_html($sss); ?></div><?php endif; ?>
            <?php if ($pagibig): ?><div class="info-label">Pag-IBIG</div><div class="info-value" style="font-size:8.5pt;"><?php echo esc_html($pagibig); ?></div><?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="calculation-section">
        <?php if ($hours_breakdown && !empty($hours_breakdown['breakdown'])): ?>
        <table>
            <thead><tr><th colspan="7" class="section-header">Work Hours Summary</th></tr>
            <tr><th>Date</th><th class="text-right">Day</th><th class="text-right">Clocked</th><th class="text-right">Lunch</th><th class="text-right">Net</th><th class="text-right">Cap</th><th class="text-right">Payable</th></tr></thead>
            <tbody>
                <?php foreach ($hours_breakdown['breakdown'] as $day): ?>
                <tr>
                    <td><?php echo date('M d, Y', strtotime($day['date'])); ?></td>
                    <td class="text-right"><?php echo date('D', strtotime($day['date'])); ?></td>
                    <td class="text-right"><?php echo number_format($day['actual_hours'], 2); ?></td>
                    <td class="text-right"><?php echo $day['lunch_deducted'] > 0 ? '-' . number_format($day['lunch_deducted'], 2) : '-'; ?></td>
                    <td class="text-right"><?php echo number_format($day['after_lunch'], 2); ?></td>
                    <td class="text-right"><?php echo $day['capped'] > 0 ? '-' . number_format($day['capped'], 2) : '-'; ?></td>
                    <td class="text-right"><strong><?php echo number_format($day['payable'], 2); ?></strong></td>
                </tr>
                <?php endforeach; ?>
                <tr class="subtotal-row">
                    <td colspan="2"><strong>TOTAL</strong></td>
                    <td class="text-right"><strong><?php echo number_format($hours_breakdown['summary']['total_actual_hours'], 2); ?></strong></td>
                    <td class="text-right"><strong>-<?php echo number_format($hours_breakdown['summary']['total_lunch_deducted'], 2); ?></strong></td>
                    <td class="text-right"><strong><?php echo number_format($hours_breakdown['summary']['total_actual_hours'] - $hours_breakdown['summary']['total_lunch_deducted'], 2); ?></strong></td>
                    <td class="text-right"><strong>-<?php echo number_format($hours_breakdown['summary']['total_capped_hours'], 2); ?></strong></td>
                    <td class="text-right"><strong><?php echo number_format($hours_breakdown['summary']['total_payable_hours'], 2); ?></strong></td>
                </tr>
            </tbody>
        </table>
        <div class="small-text" style="margin-bottom:14px;">Rate: &#8369;<?php echo number_format($hourly_rate, 2); ?>/hr &nbsp;|&nbsp; Work Days: <?php echo $hours_breakdown['summary']['work_days']; ?> &nbsp;|&nbsp; Lunch deducted for shifts &gt; 4 hrs</div>
        <?php endif; ?>

        <?php if (!empty($approved_overtime['records'])): ?>
        <table>
            <thead><tr><th colspan="5" class="section-header">Overtime Hours</th></tr>
            <tr><th>Date</th><th class="text-right">Start</th><th class="text-right">End</th><th class="text-right">Hours</th><th class="text-right">Amount</th></tr></thead>
            <tbody>
                <?php foreach ($approved_overtime['records'] as $ot): $ot_amount = floatval($ot['total_hours']) * ($hourly_rate * $ot_rate_default); ?>
                <tr>
                    <td><?php echo date('M d, Y', strtotime($ot['date'])); ?></td>
                    <td class="text-right"><?php echo date('h:i A', strtotime($ot['start_time'])); ?></td>
                    <td class="text-right"><?php echo date('h:i A', strtotime($ot['end_time'])); ?></td>
                    <td class="text-right"><?php echo number_format(floatval($ot['total_hours']), 2); ?></td>
                    <td class="text-right">&#8369;<?php echo number_format($ot_amount, 2); ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="subtotal-row">
                    <td colspan="3"><strong>TOTAL OVERTIME</strong></td>
                    <td class="text-right"><strong><?php echo number_format($approved_overtime['summary']['total_hours'], 2); ?></strong></td>
                    <td class="text-right"><strong>&#8369;<?php echo number_format($approved_overtime['summary']['total_amount'], 2); ?></strong></td>
                </tr>
            </tbody>
        </table>
        <div class="small-text" style="margin-bottom:14px;">OT Rate: <?php echo $ot_rate_default; ?>x (&#8369;<?php echo number_format($hourly_rate * $ot_rate_default, 2); ?>/hr)</div>
        <?php endif; ?>

        <table>
            <thead><tr><th colspan="2" class="section-header">Earnings &amp; Deductions</th></tr></thead>
            <tbody>
                <tr><td><strong>EARNINGS</strong></td><td></td></tr>
                <tr><td>Basic Pay (<?php echo number_format($total_hours, 2); ?> hrs x &#8369;<?php echo number_format($hourly_rate, 2); ?>)</td><td class="text-right">&#8369;<?php echo number_format($basic_pay, 2); ?></td></tr>
                <?php if ($overtime_pay > 0): ?><tr><td>Overtime Pay</td><td class="text-right">&#8369;<?php echo number_format($overtime_pay, 2); ?></td></tr><?php endif; ?>
                <tr class="subtotal-row"><td>Gross Pay</td><td class="text-right">&#8369;<?php echo number_format($gross_pay, 2); ?></td></tr>
                <?php if (!empty($deductions_data)): ?>
                <tr><td><strong>DEDUCTIONS</strong></td><td></td></tr>
                <?php foreach ($deductions_data as $deduction): ?>
                <tr><td><?php echo esc_html($deduction['name'] ?? 'Deduction'); ?></td><td class="text-right">-&#8369;<?php echo number_format(floatval($deduction['amount'] ?? 0), 2); ?></td></tr>
                <?php endforeach; ?>
                <tr class="subtotal-row"><td>Total Deductions</td><td class="text-right">-&#8369;<?php echo number_format($total_deductions, 2); ?></td></tr>
                <?php endif; ?>
                <?php if (!empty($adjustments_data)): ?>
                <tr><td><strong>ADJUSTMENTS</strong></td><td></td></tr>
                <?php foreach ($adjustments_data as $adjustment): $a_amt = floatval($adjustment['amount'] ?? 0); ?>
                <tr><td><?php echo esc_html($adjustment['description'] ?? 'Adjustment'); ?></td><td class="text-right"><?php echo $adjustment['type'] === 'increase' ? '+' : '-'; ?>&#8369;<?php echo number_format($a_amt, 2); ?></td></tr>
                <?php endforeach; ?>
                <?php endif; ?>
                <tr class="total-row"><td><strong>NET PAY</strong></td><td class="text-right"><strong>&#8369;<?php echo number_format($net_pay, 2); ?></strong></td></tr>
            </tbody>
        </table>

        <div class="summary-box">
            <div class="summary-row"><span>Basic Pay:</span><span>&#8369;<?php echo number_format($basic_pay, 2); ?></span></div>
            <?php if ($overtime_pay > 0): ?><div class="summary-row"><span>Overtime Pay:</span><span>&#8369;<?php echo number_format($overtime_pay, 2); ?></span></div><?php endif; ?>
            <div class="summary-row"><span>Gross Pay:</span><span>&#8369;<?php echo number_format($gross_pay, 2); ?></span></div>
            <div class="summary-row"><span>Total Deductions:</span><span>-&#8369;<?php echo number_format($total_deductions, 2); ?></span></div>
            <?php if ($total_adj_increase > 0): ?><div class="summary-row"><span>Adjustments Added:</span><span>+&#8369;<?php echo number_format($total_adj_increase, 2); ?></span></div><?php endif; ?>
            <?php if ($total_adj_deduction > 0): ?><div class="summary-row"><span>Adjustments Deducted:</span><span>-&#8369;<?php echo number_format($total_adj_deduction, 2); ?></span></div><?php endif; ?>
            <div class="summary-row total"><span>NET PAY:</span><span>&#8369;<?php echo number_format($net_pay, 2); ?></span></div>
        </div>

        <?php if ($bank_name || $bank_account_number): ?>
        <div class="bank-box">
            <div style="font-weight:bold;color:#1d4ed8;font-size:9pt;text-transform:uppercase;margin-bottom:6px;">Payment Account</div>
            <div class="bank-grid">
                <?php if ($bank_name): ?><div><div style="font-size:8pt;color:#6b7280;">Bank / Provider</div><div style="font-weight:600;"><?php echo esc_html($bank_name); ?></div></div><?php endif; ?>
                <?php if ($bank_account_name): ?><div><div style="font-size:8pt;color:#6b7280;">Account Name</div><div style="font-weight:600;"><?php echo esc_html($bank_account_name); ?></div></div><?php endif; ?>
                <?php if ($bank_account_number): ?><div><div style="font-size:8pt;color:#6b7280;">Account Number</div><div style="font-weight:700;font-size:11pt;letter-spacing:1px;"><?php echo esc_html($bank_account_number); ?></div></div><?php endif; ?>
                <?php if ($bank_branch): ?><div><div style="font-size:8pt;color:#6b7280;">Branch</div><div style="font-weight:600;"><?php echo esc_html($bank_branch); ?></div></div><?php endif; ?>
            </div>
            <?php if ($qr_image): ?>
            <div style="margin-top:10px;display:flex;align-items:center;gap:12px;">
                <div><div style="font-size:8pt;color:#6b7280;margin-bottom:4px;">QR Code</div><img src="<?php echo esc_attr($qr_image); ?>" alt="QR Code" style="width:80px;height:80px;border:1px solid #e5e7eb;border-radius:4px;" /></div>
                <div style="font-size:8.5pt;color:#555;">Scan to pay via mobile banking or e-wallet.</div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="footer">
    <p>This is a computer-generated payslip. No signature required.</p>
    <p class="small-text">Generated on <?php echo $generated_date; ?> &nbsp;|&nbsp; Document ID: <?php echo $payslip_number; ?></p>
    <p class="small-text">CONFIDENTIAL — For the named employee only</p>
</div>
</body></html>
    <?php return ob_get_clean();
}

function bntm_get_approved_overtime($employee_id, $period_start, $period_end) {
    global $wpdb; $prefix = $wpdb->prefix;
    $overtime_records = $wpdb->get_results($wpdb->prepare(
        "SELECT overtime_date as date, start_time, end_time, total_hours FROM {$prefix}hr_overtime WHERE employee_id = %d AND status = 'approved' AND DATE(overtime_date) BETWEEN %s AND %s ORDER BY overtime_date",
        $employee_id, $period_start, $period_end
    ));
    if (empty($overtime_records)) return ['records' => [], 'summary' => ['total_hours' => 0, 'total_amount' => 0]];
    $total_hours = 0; $total_amount = 0;
    $hourly_rate       = floatval(bntm_get_setting('hr_hourly_rate', '15.00'));
    $emp_hourly_rate   = floatval(get_user_meta($employee_id, 'bntm_hourly_rate', true)) ?: $hourly_rate;
    $ot_rate_default   = floatval(bntm_get_setting('hr_ot_rate', '1'));
    $ot_rate           = $emp_hourly_rate * $ot_rate_default;
    $records_array = [];
    foreach ($overtime_records as $record) {
        $ot_hours = floatval($record->total_hours);
        $records_array[] = ['date' => $record->date, 'start_time' => $record->start_time, 'end_time' => $record->end_time, 'total_hours' => $ot_hours];
        $total_hours  += $ot_hours;
        $total_amount += $ot_hours * $ot_rate;
    }
    return ['records' => $records_array, 'summary' => ['total_hours' => round($total_hours, 2), 'total_amount' => round($total_amount, 2)]];
}

function bntm_get_detailed_hours_breakdown($employee_id, $period_start, $period_end) {
    global $wpdb; $prefix = $wpdb->prefix;
    $work_hours_per_day  = floatval(bntm_get_setting('hr_work_hours', '8'));
    $lunch_break_hours   = floatval(bntm_get_setting('hr_lunch_break_hours', '1'));
    $lunch_threshold     = 4;
    $attendance_records  = $wpdb->get_results($wpdb->prepare(
        "SELECT DATE(clock_in) as work_date, clock_in, clock_out, SUM(total_hours) as actual_hours, COUNT(*) as shifts FROM {$prefix}hr_attendance WHERE employee_id = %d AND DATE(clock_in) BETWEEN %s AND %s AND clock_out IS NOT NULL GROUP BY DATE(clock_in) ORDER BY work_date",
        $employee_id, $period_start, $period_end
    ));
    if (empty($attendance_records)) return null;
    $breakdown = []; $total_actual = 0; $total_payable = 0; $total_lunch_deducted = 0; $total_capped = 0;
    foreach ($attendance_records as $record) {
        $actual_hours   = floatval($record->actual_hours); $shifts = intval($record->shifts);
        $lunch_deduction = ($actual_hours > $lunch_threshold) ? $shifts * $lunch_break_hours : 0;
        $hours_after_lunch = max(0, $actual_hours - $lunch_deduction);
        $payable_hours   = min($hours_after_lunch, $work_hours_per_day);
        $capped_hours    = max(0, $hours_after_lunch - $work_hours_per_day);
        $breakdown[] = ['date' => $record->work_date, 'actual_hours' => $actual_hours, 'lunch_deducted' => $lunch_deduction, 'after_lunch' => $hours_after_lunch, 'capped' => $capped_hours, 'payable' => $payable_hours, 'shifts' => $shifts];
        $total_actual          += $actual_hours;
        $total_lunch_deducted  += $lunch_deduction;
        $total_capped          += $capped_hours;
        $total_payable         += $payable_hours;
    }
    return ['breakdown' => $breakdown, 'summary' => ['total_actual_hours' => round($total_actual, 2), 'total_lunch_deducted' => round($total_lunch_deducted, 2), 'total_capped_hours' => round($total_capped, 2), 'total_payable_hours' => round($total_payable, 2), 'work_days' => count($breakdown)]];
}

/* ── IMPORT / REVERT PAYSLIPS ────────────────────────────────── */

add_action('wp_ajax_bntm_hr_import_payslips', 'bntm_ajax_hr_import_payslips');
function bntm_ajax_hr_import_payslips() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    $current_user = wp_get_current_user(); $is_wp_admin = current_user_can('manage_options'); $current_role = bntm_get_user_role($current_user->ID);
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) { wp_send_json_error(['message' => 'Permission denied.']); }
    global $wpdb; $prefix = $wpdb->prefix;
    $payslip_ids = json_decode(stripslashes($_POST['payslip_ids']), true);
    if (empty($payslip_ids)) { wp_send_json_error(['message' => 'No payslips selected.']); }
    $imported_count = 0;
    foreach ($payslip_ids as $payslip_id) {
        $payslip = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$prefix}hr_payslips WHERE id = %d AND is_imported = 0", $payslip_id));
        if (!$payslip) continue;
        $employee = get_userdata($payslip->employee_id);
        $result = $wpdb->insert($prefix . 'fn_transactions', [
            'rand_id'        => bntm_rand_id(),
            'business_id'    => 0,
            'type'           => 'expense',
            'amount'         => $payslip->net_pay,
            'category'       => 'Payroll',
            'notes'          => 'Payslip for ' . $employee->display_name . ' (' . date('M d', strtotime($payslip->period_start)) . ' - ' . date('M d, Y', strtotime($payslip->period_end)) . ')',
            'reference_type' => 'payslip',
            'reference_id'   => $payslip->id,
        ], ['%s','%d','%s','%f','%s','%s','%s','%d']);
        if ($result) { $wpdb->update($prefix . 'hr_payslips', ['is_imported' => 1], ['id' => $payslip->id], ['%d'], ['%d']); $imported_count++; }
    }
    if ($imported_count > 0) { if (function_exists('bntm_fn_update_cashflow_summary')) bntm_fn_update_cashflow_summary(); wp_send_json_success(['message' => "Imported {$imported_count} payslip(s) to Finance successfully!"]); }
    else { wp_send_json_error(['message' => 'Failed to import payslips or already imported.']); }
}

add_action('wp_ajax_bntm_hr_revert_payslips', 'bntm_ajax_hr_revert_payslips');
function bntm_ajax_hr_revert_payslips() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    $current_user = wp_get_current_user(); $is_wp_admin = current_user_can('manage_options'); $current_role = bntm_get_user_role($current_user->ID);
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) { wp_send_json_error(['message' => 'Permission denied.']); }
    global $wpdb; $prefix = $wpdb->prefix;
    $payslip_ids = json_decode(stripslashes($_POST['payslip_ids']), true);
    if (empty($payslip_ids)) { wp_send_json_error(['message' => 'No payslips selected.']); }
    $reverted_count = 0;
    foreach ($payslip_ids as $payslip_id) {
        $result = $wpdb->delete($prefix . 'fn_transactions', ['reference_type' => 'payslip', 'reference_id' => $payslip_id], ['%s', '%d']);
        if ($result) { $wpdb->update($prefix . 'hr_payslips', ['is_imported' => 0], ['id' => $payslip_id], ['%d'], ['%d']); $reverted_count++; }
    }
    if ($reverted_count > 0) { if (function_exists('bntm_fn_update_cashflow_summary')) bntm_fn_update_cashflow_summary(); wp_send_json_success(['message' => "Reverted {$reverted_count} payslip(s) from Finance successfully!"]); }
    else { wp_send_json_error(['message' => 'Failed to revert payslips.']); }
}


/* ---------- OVERTIME & MISSING LOGS VIEW ---------- */

function bntm_hr_overtime_missing_view($user_id, $can_manage) {
    global $wpdb; $prefix = $wpdb->prefix; ob_start();
    ?>
    <?php if (!$can_manage): ?>
    <div class="bntm-form-section" style="margin-bottom:30px;">
        <h3>Request Overtime or Missing Log</h3>
        <div style="display:flex;gap:10px;margin-bottom:20px;border-bottom:2px solid #e5e7eb;padding-bottom:10px;">
            <button class="overtime-missing-tab active" data-type="overtime" style="background:none;border:none;padding:10px 15px;cursor:pointer;border-bottom:2px solid transparent;font-weight:500;color:var(--bntm-primary,#3b82f6);">Overtime Request</button>
            <button class="overtime-missing-tab" data-type="missing" style="background:none;border:none;padding:10px 15px;cursor:pointer;border-bottom:2px solid transparent;font-weight:500;color:#6b7280;">Missing Clock In/Out</button>
        </div>
        <div id="overtime-form-container" class="overtime-missing-container">
            <form id="overtime-request-form" class="bntm-form">
                <div class="bntm-form-group"><label>Overtime Date *</label><input type="date" id="overtime-date" required /></div>
                <div class="bntm-form-row"><div class="bntm-form-group"><label>Start Time *</label><input type="time" id="overtime-start-time" required /></div><div class="bntm-form-group"><label>End Time *</label><input type="time" id="overtime-end-time" required /></div></div>
                <div class="bntm-form-group"><label>Reason *</label><textarea id="overtime-reason" rows="3" required></textarea></div>
                <button type="submit" class="bntm-btn-primary">Submit Overtime Request</button><div id="overtime-message"></div>
            </form>
        </div>
        <div id="missing-form-container" class="overtime-missing-container" style="display:none;">
            <form id="missing-log-form" class="bntm-form">
                <div class="bntm-form-group"><label>Date of Missing Log *</label><input type="date" id="missing-date" required /></div>
                <div class="bntm-form-group"><label>Type *</label><select id="missing-type" required><option value="">Select type...</option><option value="clock_in">Missing Clock In</option><option value="clock_out">Missing Clock Out</option><option value="both">Missing Both</option></select></div>
                <div id="missing-time-fields"><div class="bntm-form-row"><div class="bntm-form-group"><label>Clock In Time</label><input type="time" id="missing-clock-in" /></div><div class="bntm-form-group"><label>Clock Out Time</label><input type="time" id="missing-clock-out" /></div></div></div>
                <div class="bntm-form-group"><label>Reason *</label><textarea id="missing-reason" rows="3" required></textarea></div>
                <button type="submit" class="bntm-btn-primary">Submit Missing Log</button><div id="missing-message"></div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="bntm-form-section">
        <h3><?php echo $can_manage ? 'Overtime Requests' : 'My Overtime Requests'; ?></h3>
        <?php
        if ($can_manage) { $overtime = $wpdb->get_results("SELECT o.*, u.display_name FROM {$prefix}hr_overtime o LEFT JOIN {$wpdb->users} u ON o.employee_id = u.ID ORDER BY o.created_at DESC"); }
        else { $overtime = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$prefix}hr_overtime WHERE employee_id = %d ORDER BY created_at DESC", $user_id)); }
        ?>
        <?php if ($overtime): ?>
        <?php if ($can_manage): ?>
        <div style="display:flex; justify-content:flex-start; align-items:center; margin-bottom:12px; gap:8px;">
            <select id="hr-overtime-bulk-action" class="bntm-input" style="padding:6px 12px; font-size:13px; height:auto;">
                <option value="">Bulk Actions</option>
                <option value="approve">Approve Selected</option>
                <option value="reject">Reject Selected</option>
            </select>
            <button id="hr-overtime-bulk-apply" class="bntm-btn-secondary bntm-btn-small" style="padding:6px 12px;">Apply</button>
        </div>
        <?php endif; ?>
        <div class="bntm-table-wrapper"><table class="bntm-table"><thead><tr>
                <?php if ($can_manage): ?><th style="width:40px; text-align:center;"><input type="checkbox" id="hr-overtime-bulk-select-all" /></th><?php endif; ?>
                <?php if ($can_manage): ?><th>Employee</th><?php endif; ?><th>Date</th><th>Time</th><th>Hours</th><th>Reason</th><th>Status</th><?php if ($can_manage): ?><th>Actions</th><?php endif; ?></tr></thead><tbody>
            <?php foreach ($overtime as $ot): $status_class = $ot->status === 'approved' ? 'bntm-notice-success' : ($ot->status === 'rejected' ? 'bntm-notice-error' : ''); $start = DateTime::createFromFormat('H:i:s', $ot->start_time)->format('h:i A'); $end = DateTime::createFromFormat('H:i:s', $ot->end_time)->format('h:i A'); ?>
                <tr style="font-size:12px;"> <!-- Made words smaller -->
                <?php if ($can_manage): ?><td style="text-align:center;"><input type="checkbox" class="hr-overtime-bulk-item" value="<?php echo $ot->id; ?>" <?php echo $ot->status !== 'pending' ? 'disabled' : ''; ?> /></td><?php endif; ?>
                <?php if ($can_manage): ?><td><strong><?php echo esc_html($ot->display_name); ?></strong></td><?php endif; ?>
                <td><?php echo esc_html(date('M d, Y', strtotime($ot->overtime_date))); ?></td>
                <td><?php echo esc_html($start . ' - ' . $end); ?></td>
                <td><?php echo esc_html($ot->total_hours . ' hrs'); ?></td>
                <td><?php echo esc_html($ot->reason); ?></td>
                <td><span class="<?php echo $status_class; ?>" style="padding:2px 6px;border-radius:4px;display:inline-block;font-size:11px;font-weight:600;"><?php echo esc_html(ucfirst($ot->status)); ?></span></td>
                <?php if ($can_manage): ?><td><?php if ($ot->status === 'pending'): ?>
                    <button class="bntm-btn-small approve-overtime" data-id="<?php echo $ot->id; ?>" style="font-size:11px;padding:4px 8px;display:inline-flex;align-items:center;gap:4px;"><?php echo bntm_hr_svg('check', 10); ?> Apprv</button>
                    <button class="bntm-btn-small bntm-btn-danger reject-overtime" data-id="<?php echo $ot->id; ?>" style="font-size:11px;padding:4px 8px;display:inline-flex;align-items:center;gap:4px;"><?php echo bntm_hr_svg('close', 10); ?> Rej</button>
                <?php endif; ?></td><?php endif; ?></tr>
            <?php endforeach; ?></tbody></table></div>
        <?php else: ?><p>No overtime requests found.</p><?php endif; ?>
    </div>

    <div class="bntm-form-section">
        <h3><?php echo $can_manage ? 'Missing Clock In/Out Logs' : 'My Missing Logs'; ?></h3>
        <?php
        if ($can_manage) { $missing = $wpdb->get_results("SELECT m.*, u.display_name FROM {$prefix}hr_missing_logs m LEFT JOIN {$wpdb->users} u ON m.employee_id = u.ID ORDER BY m.created_at DESC"); }
        else { $missing = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$prefix}hr_missing_logs WHERE employee_id = %d ORDER BY created_at DESC", $user_id)); }
        ?>
        <?php if ($missing): ?>
        <?php if ($can_manage): ?>
        <div style="display:flex; justify-content:flex-start; align-items:center; margin-bottom:12px; gap:8px;">
            <select id="hr-missing-bulk-action" class="bntm-input" style="padding:6px 12px; font-size:13px; height:auto;">
                <option value="">Bulk Actions</option>
                <option value="approve">Approve Selected</option>
                <option value="reject">Reject Selected</option>
            </select>
            <button id="hr-missing-bulk-apply" class="bntm-btn-secondary bntm-btn-small" style="padding:6px 12px;">Apply</button>
        </div>
        <?php endif; ?>
        <div class="bntm-table-wrapper"><table class="bntm-table"><thead><tr>
                <?php if ($can_manage): ?><th style="width:40px; text-align:center;"><input type="checkbox" id="hr-missing-bulk-select-all" /></th><?php endif; ?>
                <?php if ($can_manage): ?><th>Employee</th><?php endif; ?><th>Date</th><th>Type</th><th>Clock In</th><th>Clock Out</th><th>Reason</th><th>Status</th><?php if ($can_manage): ?><th>Actions</th><?php endif; ?></tr></thead><tbody>
            <?php foreach ($missing as $log): $status_class = $log->status === 'approved' ? 'bntm-notice-success' : ($log->status === 'rejected' ? 'bntm-notice-error' : ''); $type_label = $log->log_type === 'clock_in' ? 'Missing Clock In' : ($log->log_type === 'clock_out' ? 'Missing Clock Out' : 'Missing Both'); $clock_in = $log->clock_in_time ? DateTime::createFromFormat('H:i:s', $log->clock_in_time)->format('h:i A') : '-'; $clock_out = $log->clock_out_time ? DateTime::createFromFormat('H:i:s', $log->clock_out_time)->format('h:i A') : '-'; ?>
                <tr style="font-size:12px;"> <!-- Made words smaller -->
                <?php if ($can_manage): ?><td style="text-align:center;"><input type="checkbox" class="hr-missing-bulk-item" value="<?php echo $log->id; ?>" <?php echo $log->status !== 'pending' ? 'disabled' : ''; ?> /></td><?php endif; ?>
                <?php if ($can_manage): ?><td><strong><?php echo esc_html($log->display_name); ?></strong></td><?php endif; ?>
                <td><?php echo esc_html(date('M d, Y', strtotime($log->log_date))); ?></td>
                <td><?php echo esc_html($type_label); ?></td>
                <td><?php echo esc_html($clock_in); ?></td>
                <td><?php echo esc_html($clock_out); ?></td>
                <td><?php echo esc_html($log->reason); ?></td>
                <td><span class="<?php echo $status_class; ?>" style="padding:2px 6px;border-radius:4px;display:inline-block;font-size:11px;font-weight:600;"><?php echo esc_html(ucfirst($log->status)); ?></span></td>
                <?php if ($can_manage): ?><td><?php if ($log->status === 'pending'): ?>
                    <button class="bntm-btn-small approve-missing" data-id="<?php echo $log->id; ?>" style="font-size:11px;padding:4px 8px;display:inline-flex;align-items:center;gap:4px;"><?php echo bntm_hr_svg('check', 10); ?> Apprv</button>
                    <button class="bntm-btn-small bntm-btn-danger reject-missing" data-id="<?php echo $log->id; ?>" style="font-size:11px;padding:4px 8px;display:inline-flex;align-items:center;gap:4px;"><?php echo bntm_hr_svg('close', 10); ?> Rej</button>
                <?php endif; ?></td><?php endif; ?></tr>
            <?php endforeach; ?></tbody></table></div>
        <?php else: ?><p>No missing logs found.</p><?php endif; ?>
    </div>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        const bntmAjax = { ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>', nonce: '<?php echo wp_create_nonce('bntm_hr_nonce'); ?>' };
        $(document).on('click', '.overtime-missing-tab', function() {
            var type = $(this).data('type');
            $('.overtime-missing-tab').removeClass('active').css({'color':'#6b7280','border-bottom-color':'transparent'});
            $(this).addClass('active').css({'color':'var(--bntm-primary,#3b82f6)','border-bottom-color':'var(--bntm-primary,#3b82f6)'});
            $('.overtime-missing-container').hide();
            if (type === 'overtime') $('#overtime-form-container').show(); else $('#missing-form-container').show();
        });
        $('#overtime-request-form').on('submit', function(e) {
            e.preventDefault();
            $.ajax({ url: bntmAjax.ajax_url, type: 'POST', data: { action: 'bntm_hr_submit_overtime', nonce: bntmAjax.nonce, overtime_date: $('#overtime-date').val(), start_time: $('#overtime-start-time').val(), end_time: $('#overtime-end-time').val(), reason: $('#overtime-reason').val() }, success: function(response) { if (response.success) { $('#overtime-message').html('<div class="bntm-notice bntm-notice-success">' + response.data.message + '</div>'); $('#overtime-request-form')[0].reset(); setTimeout(function() { location.reload(); }, 1500); } else { $('#overtime-message').html('<div class="bntm-notice bntm-notice-error">' + response.data.message + '</div>'); } } });
        });
        $('#missing-type').on('change', function() {
            var type = $(this).val();
            if (type === 'clock_in') { $('#missing-clock-in').prop('required', true); $('#missing-clock-out').prop('required', false); }
            else if (type === 'clock_out') { $('#missing-clock-in').prop('required', false); $('#missing-clock-out').prop('required', true); }
            else if (type === 'both') { $('#missing-clock-in').prop('required', true); $('#missing-clock-out').prop('required', true); }
        });
        $('#missing-log-form').on('submit', function(e) {
            e.preventDefault();
            $.ajax({ url: bntmAjax.ajax_url, type: 'POST', data: { action: 'bntm_hr_submit_missing_log', nonce: bntmAjax.nonce, log_date: $('#missing-date').val(), log_type: $('#missing-type').val(), clock_in: $('#missing-clock-in').val(), clock_out: $('#missing-clock-out').val(), reason: $('#missing-reason').val() }, success: function(response) { if (response.success) { $('#missing-message').html('<div class="bntm-notice bntm-notice-success">' + response.data.message + '</div>'); $('#missing-log-form')[0].reset(); setTimeout(function() { location.reload(); }, 1500); } else { $('#missing-message').html('<div class="bntm-notice bntm-notice-error">' + response.data.message + '</div>'); } } });
        });
        $(document).on('click', '.approve-overtime', function() { var otId = $(this).data('id'); if (!confirm('Approve this overtime request?')) return; $.ajax({ url: bntmAjax.ajax_url, type: 'POST', data: { action: 'bntm_hr_approve_overtime', nonce: bntmAjax.nonce, ot_id: otId }, success: function(response) { alert(response.data.message); if (response.success) location.reload(); } }); });
        $(document).on('click', '.reject-overtime', function() { var otId = $(this).data('id'); if (!confirm('Reject this overtime request?')) return; $.ajax({ url: bntmAjax.ajax_url, type: 'POST', data: { action: 'bntm_hr_reject_overtime', nonce: bntmAjax.nonce, ot_id: otId }, success: function(response) { alert(response.data.message); if (response.success) location.reload(); } }); });
        $(document).on('click', '.approve-missing', function() { var logId = $(this).data('id'); if (!confirm('Approve this missing log? An attendance record will be created.')) return; $.ajax({ url: bntmAjax.ajax_url, type: 'POST', data: { action: 'bntm_hr_approve_missing_log', nonce: bntmAjax.nonce, log_id: logId }, success: function(response) { alert(response.data.message); if (response.success) location.reload(); } }); });
        $(document).on('click', '.reject-missing', function() { var logId = $(this).data('id'); if (!confirm('Reject this missing log?')) return; $.ajax({ url: bntmAjax.ajax_url, type: 'POST', data: { action: 'bntm_hr_reject_missing_log', nonce: bntmAjax.nonce, log_id: logId }, success: function(response) { alert(response.data.message); if (response.success) location.reload(); } }); });

        // Overtime Bulk Actions
        $('#hr-overtime-bulk-select-all').on('change', function() {
            $('.hr-overtime-bulk-item:not([disabled])').prop('checked', $(this).prop('checked'));
        });
        
        $('#hr-overtime-bulk-apply').on('click', async function() {
            var action = $('#hr-overtime-bulk-action').val();
            if (!action) return alert('Please select a bulk action.');
            var selectedIds = [];
            $('.hr-overtime-bulk-item:checked').each(function() { selectedIds.push($(this).val()); });
            
            if (selectedIds.length === 0) return alert('Please select at least one overtime request.');
            if (!confirm('Are you sure you want to ' + action + ' ' + selectedIds.length + ' request(s)?')) return;
            
            var $btn = $(this);
            $btn.text('Applying...').prop('disabled', true);
            
            var processed = 0, errors = 0;
            var ajaxAction = action === 'approve' ? 'bntm_hr_approve_overtime' : 'bntm_hr_reject_overtime';
            
            for (let id of selectedIds) {
                try {
                    let result = await $.ajax({ 
                        url: bntmAjax.ajax_url, 
                        type: 'POST', 
                        data: { action: ajaxAction, nonce: bntmAjax.nonce, ot_id: id } 
                    });
                    if(result.success) processed++; else errors++;
                } catch(e) { errors++; }
            }
            
            alert('Operation complete. Success: ' + processed + ', Errors: ' + errors);
            location.reload();
        });

        // Missing Logs Bulk Actions
        $('#hr-missing-bulk-select-all').on('change', function() {
            $('.hr-missing-bulk-item:not([disabled])').prop('checked', $(this).prop('checked'));
        });
        
        $('#hr-missing-bulk-apply').on('click', async function() {
            var action = $('#hr-missing-bulk-action').val();
            if (!action) return alert('Please select a bulk action.');
            var selectedIds = [];
            $('.hr-missing-bulk-item:checked').each(function() { selectedIds.push($(this).val()); });
            
            if (selectedIds.length === 0) return alert('Please select at least one missing log.');
            if (!confirm('Are you sure you want to ' + action + ' ' + selectedIds.length + ' log(s)?')) return;
            
            var $btn = $(this);
            $btn.text('Applying...').prop('disabled', true);
            
            var processed = 0, errors = 0;
            var ajaxAction = action === 'approve' ? 'bntm_hr_approve_missing_log' : 'bntm_hr_reject_missing_log';
            
            // Loop array if no bulk ajax handler is registered
            for (let id of selectedIds) {
                try {
                    let result = await $.ajax({ 
                        url: bntmAjax.ajax_url, 
                        type: 'POST', 
                        data: { action: ajaxAction, nonce: bntmAjax.nonce, log_id: id } 
                    });
                    if(result.success) processed++; else errors++;
                } catch(e) { errors++; }
            }
            
            alert('Operation complete. Success: ' + processed + ', Errors: ' + errors);
            location.reload();
        });
    });
    </script>
    <?php return ob_get_clean();
}

/* ── OVERTIME / MISSING AJAX ─────────────────────────────────── */

add_action('wp_ajax_bntm_hr_submit_overtime', 'bntm_ajax_hr_submit_overtime');
function bntm_ajax_hr_submit_overtime() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    $user_id = get_current_user_id();
    $overtime_date = sanitize_text_field($_POST['overtime_date']); $start_time = sanitize_text_field($_POST['start_time']); $end_time = sanitize_text_field($_POST['end_time']); $reason = sanitize_textarea_field($_POST['reason']);
    $total_hours = round((strtotime($end_time) - strtotime($start_time)) / 3600, 2);
    if ($total_hours <= 0) { wp_send_json_error(['message' => 'End time must be after start time.']); }
    global $wpdb; $prefix = $wpdb->prefix;
    $result = $wpdb->insert($prefix . 'hr_overtime', ['rand_id' => bntm_rand_id(), 'employee_id' => $user_id, 'business_id' => 1, 'overtime_date' => $overtime_date, 'start_time' => $start_time, 'end_time' => $end_time, 'total_hours' => $total_hours, 'reason' => $reason, 'status' => 'pending'], ['%s','%d','%d','%s','%s','%s','%f','%s','%s']);
    if ($result) wp_send_json_success(['message' => 'Overtime request submitted successfully.']);
    else wp_send_json_error(['message' => 'Failed to submit overtime request.']);
}

add_action('wp_ajax_bntm_hr_submit_missing_log', 'bntm_ajax_hr_submit_missing_log');
function bntm_ajax_hr_submit_missing_log() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    $user_id   = get_current_user_id();
    $log_date  = sanitize_text_field($_POST['log_date']); $log_type = sanitize_text_field($_POST['log_type']); $clock_in = sanitize_text_field($_POST['clock_in'] ?? ''); $clock_out = sanitize_text_field($_POST['clock_out'] ?? ''); $reason = sanitize_textarea_field($_POST['reason']);
    global $wpdb; $prefix = $wpdb->prefix;
    $result = $wpdb->insert($prefix . 'hr_missing_logs', ['rand_id' => bntm_rand_id(), 'employee_id' => $user_id, 'business_id' => 1, 'log_date' => $log_date, 'log_type' => $log_type, 'clock_in_time' => $clock_in ?: null, 'clock_out_time' => $clock_out ?: null, 'reason' => $reason, 'status' => 'pending'], ['%s','%d','%d','%s','%s','%s','%s','%s','%s']);
    if ($result) wp_send_json_success(['message' => 'Missing log request submitted successfully.']);
    else wp_send_json_error(['message' => 'Failed to submit missing log request.']);
}

add_action('wp_ajax_bntm_hr_approve_overtime', 'bntm_ajax_hr_approve_overtime');
function bntm_ajax_hr_approve_overtime() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    $current_user = wp_get_current_user(); $is_wp_admin = current_user_can('manage_options'); $current_role = bntm_get_user_role($current_user->ID);
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) { wp_send_json_error(['message' => 'Permission denied.']); }
    global $wpdb; $prefix = $wpdb->prefix; $ot_id = intval($_POST['ot_id']); $user_id = get_current_user_id();
    $result = $wpdb->update($prefix . 'hr_overtime', ['status' => 'approved', 'approved_by' => $user_id, 'approved_at' => current_time('mysql')], ['id' => $ot_id], ['%s','%d','%s'], ['%d']);
    if ($result !== false) wp_send_json_success(['message' => 'Overtime request approved.']);
    else wp_send_json_error(['message' => 'Failed to approve overtime request.']);
}

add_action('wp_ajax_bntm_hr_reject_overtime', 'bntm_ajax_hr_reject_overtime');
function bntm_ajax_hr_reject_overtime() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    $current_user = wp_get_current_user(); $is_wp_admin = current_user_can('manage_options'); $current_role = bntm_get_user_role($current_user->ID);
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) { wp_send_json_error(['message' => 'Permission denied.']); }
    global $wpdb; $prefix = $wpdb->prefix; $ot_id = intval($_POST['ot_id']); $user_id = get_current_user_id();
    $result = $wpdb->update($prefix . 'hr_overtime', ['status' => 'rejected', 'approved_by' => $user_id, 'approved_at' => current_time('mysql')], ['id' => $ot_id], ['%s','%d','%s'], ['%d']);
    if ($result !== false) wp_send_json_success(['message' => 'Overtime request rejected.']);
    else wp_send_json_error(['message' => 'Failed to reject overtime request.']);
}

add_action('wp_ajax_bntm_hr_approve_missing_log', 'bntm_ajax_hr_approve_missing_log');
function bntm_ajax_hr_approve_missing_log() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    $current_user = wp_get_current_user(); $is_wp_admin = current_user_can('manage_options'); $current_role = bntm_get_user_role($current_user->ID);
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) { wp_send_json_error(['message' => 'Permission denied.']); }
    $log_id = intval($_POST['log_id']); $user_id = get_current_user_id();
    global $wpdb; $prefix = $wpdb->prefix;
    $log = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$prefix}hr_missing_logs WHERE id = %d", $log_id));
    if (!$log) { wp_send_json_error(['message' => 'Missing log not found.']); }
    $clock_in_time  = $log->clock_in_time  ?: '09:00:00';
    $clock_out_time = $log->clock_out_time ?: '17:00:00';
    $total_hours = round((strtotime($clock_out_time) - strtotime($clock_in_time)) / 3600, 2);
    $result = $wpdb->update($prefix . 'hr_missing_logs', ['status' => 'approved', 'approved_by' => $user_id, 'approved_at' => current_time('mysql')], ['id' => $log_id], ['%s','%d','%s'], ['%d']);
    if ($result !== false) {
        $wpdb->insert($prefix . 'hr_attendance', ['rand_id' => bntm_rand_id(), 'employee_id' => $log->employee_id, 'business_id' => 1, 'clock_in' => $log->log_date . ' ' . $clock_in_time, 'clock_out' => $log->log_date . ' ' . $clock_out_time, 'total_hours' => $total_hours, 'status' => 'present', 'notes' => 'Added from missing log approval'], ['%s','%d','%d','%s','%s','%f','%s','%s']);
        wp_send_json_success(['message' => 'Missing log approved and attendance record created.']);
    } else { wp_send_json_error(['message' => 'Failed to approve missing log.']); }
}

add_action('wp_ajax_bntm_hr_reject_missing_log', 'bntm_ajax_hr_reject_missing_log');
function bntm_ajax_hr_reject_missing_log() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    $current_user = wp_get_current_user(); $is_wp_admin = current_user_can('manage_options'); $current_role = bntm_get_user_role($current_user->ID);
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) { wp_send_json_error(['message' => 'Permission denied.']); }
    $log_id = intval($_POST['log_id']); $user_id = get_current_user_id();
    global $wpdb; $prefix = $wpdb->prefix;
    $result = $wpdb->update($prefix . 'hr_missing_logs', ['status' => 'rejected', 'approved_by' => $user_id, 'approved_at' => current_time('mysql')], ['id' => $log_id], ['%s','%d','%s'], ['%d']);
    if ($result !== false) wp_send_json_success(['message' => 'Missing log rejected.']);
    else wp_send_json_error(['message' => 'Failed to reject missing log.']);
}