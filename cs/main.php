<?php
/**
 * Module Name: Class Scheduler
 * Module Slug: cs
 * Description: Web-based weekly class timetable builder for school administrators.
 *              Manage sections, assign subjects to time slots, link instructors and rooms,
 *              and view a full weekly grid.a
 * Version: 1.0.0
 * Author: BNTM Framework
 * Icon: <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
 */

if (!defined('ABSPATH')) exit;

// ─────────────────────────────────────────────────────────────
// MODULE CONSTANTS
// ─────────────────────────────────────────────────────────────
define('BNTM_CS_PATH', dirname(__FILE__) . '/');
define('BNTM_CS_URL',  plugin_dir_url(__FILE__));

define('BNTM_CS_TIME_SLOTS', [
    '7:30 - 9:00',
    '9:00 - 10:30',
    '10:30 - 12:00',
    '12:00 - 1:30',
    '1:30 - 3:00',
    '3:00 - 4:30',
    '4:30 - 6:00',
    '6:00 - 7:30',
]);

define('BNTM_CS_DAY_GROUPS', [
    'mon_thu' => 'Monday / Thursday',
    'tue_fri' => 'Tuesday / Friday',
    'wed_sat' => 'Wednesday / Saturday',
]);

// ─────────────────────────────────────────────────────────────
// CORE MODULE FUNCTIONS
// ─────────────────────────────────────────────────────────────

function bntm_cs_get_pages() {
    return [
        'Class Scheduler' => '[bntm_class_scheduler]',
        'Section Timetable' => '[bntm_section_timetable]',
    ];
}

function bntm_cs_get_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $prefix  = $wpdb->prefix;

    return [
        'cs_sections' => "CREATE TABLE {$prefix}cs_sections (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            section_name VARCHAR(100) NOT NULL,
            course_code VARCHAR(20) NOT NULL,
            curriculum VARCHAR(120) NOT NULL DEFAULT '',
            year_level TINYINT NOT NULL DEFAULT 1,
            block VARCHAR(10) NOT NULL,
            academic_year VARCHAR(20) NOT NULL DEFAULT '',
            semester VARCHAR(20) NOT NULL DEFAULT 'First',
            status ENUM('active','archived') NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) {$charset};",

        'cs_courses' => "CREATE TABLE {$prefix}cs_courses (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            course_code VARCHAR(20) NOT NULL,
            course_title VARCHAR(180) NOT NULL DEFAULT '',
            department TEXT NOT NULL DEFAULT '',
            curriculum VARCHAR(120) NOT NULL DEFAULT '',
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) {$charset};",

        'cs_schedules' => "CREATE TABLE {$prefix}cs_schedules (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            section_id BIGINT UNSIGNED NOT NULL,
            subject_code VARCHAR(50) NOT NULL,
            subject_name VARCHAR(150) NOT NULL DEFAULT '',
            instructor_initials VARCHAR(30) NOT NULL DEFAULT '',
            instructor_name VARCHAR(100) NOT NULL DEFAULT '',
            room VARCHAR(30) NOT NULL DEFAULT '',
            day_group VARCHAR(50) NOT NULL,
            time_slot VARCHAR(20) NOT NULL,
            schedule_type ENUM('lecture','lab','both') NOT NULL DEFAULT 'lecture',
            units TINYINT NOT NULL DEFAULT 3,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_section (section_id)
        ) {$charset};",

        'cs_instructors' => "CREATE TABLE {$prefix}cs_instructors (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            initials VARCHAR(30) NOT NULL,
            full_name VARCHAR(150) NOT NULL,
            department VARCHAR(100) NOT NULL DEFAULT '',
            subjects_handled TEXT NOT NULL DEFAULT '',
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) {$charset};",

        'cs_rooms' => "CREATE TABLE {$prefix}cs_rooms (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            room_code VARCHAR(30) NOT NULL,
            building VARCHAR(80) NOT NULL DEFAULT '',
            capacity SMALLINT NOT NULL DEFAULT 40,
            room_type ENUM('lecture','lab','both') NOT NULL DEFAULT 'lecture',
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) {$charset};",

        'cs_departments' => "CREATE TABLE {$prefix}cs_departments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            dept_name VARCHAR(150) NOT NULL,
            dept_code VARCHAR(30) NOT NULL DEFAULT '',
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) {$charset};",
    ];
}

function bntm_cs_get_shortcodes() {
    return [
        'bntm_class_scheduler'   => 'bntm_shortcode_cs',
        'bntm_section_timetable' => 'bntm_shortcode_cs_public',
    ];
}

function bntm_cs_create_tables() {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $tables = bntm_cs_get_tables();
    foreach ($tables as $sql) {
        dbDelta($sql);
    }
    return count($tables);
}

// ─────────────────────────────────────────────────────────────
// AJAX ACTION HOOKS
// ─────────────────────────────────────────────────────────────
add_action('wp_ajax_cs_save_section',         'bntm_ajax_cs_save_section');
add_action('wp_ajax_cs_delete_section',       'bntm_ajax_cs_delete_section');
add_action('wp_ajax_cs_save_schedule',        'bntm_ajax_cs_save_schedule');
add_action('wp_ajax_cs_delete_schedule',      'bntm_ajax_cs_delete_schedule');
add_action('wp_ajax_cs_save_instructor',      'bntm_ajax_cs_save_instructor');
add_action('wp_ajax_cs_delete_instructor',    'bntm_ajax_cs_delete_instructor');
add_action('wp_ajax_cs_save_room',            'bntm_ajax_cs_save_room');
add_action('wp_ajax_cs_delete_room',          'bntm_ajax_cs_delete_room');
add_action('wp_ajax_cs_get_section_data',     'bntm_ajax_cs_get_section_data');
add_action('wp_ajax_cs_bulk_import_section',  'bntm_ajax_cs_bulk_import_section');
add_action('wp_ajax_cs_save_department',      'bntm_ajax_cs_save_department');
add_action('wp_ajax_cs_delete_department',    'bntm_ajax_cs_delete_department');
add_action('wp_ajax_cs_save_course',          'bntm_ajax_cs_save_course');
add_action('wp_ajax_cs_delete_course',        'bntm_ajax_cs_delete_course');
add_action('wp_ajax_cs_bulk_import_instructors', 'bntm_ajax_cs_bulk_import_instructors');
add_action('wp_ajax_cs_bulk_import_rooms',       'bntm_ajax_cs_bulk_import_rooms');
add_action('wp_ajax_cs_bulk_import_departments', 'bntm_ajax_cs_bulk_import_departments');
add_action('wp_ajax_cs_bulk_import_sections',    'bntm_ajax_cs_bulk_import_sections');

// ─────────────────────────────────────────────────────────────
// MAIN DASHBOARD SHORTCODE
// ─────────────────────────────────────────────────────────────

function bntm_shortcode_cs() {
    if (!is_user_logged_in()) {
        return '<div class="bntm-notice">Please log in to access the Class Scheduler.</div>';
    }

    $active_tab  = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';

    $tabs = [
        'overview'    => 'Overview',
        'timetable'   => 'Timetable View',
        'courses'     => 'Courses',
        'sections'    => 'Sections',
        'schedule'    => 'Schedule Entry',
        'instructors' => 'Instructors',
        'rooms'       => 'Rooms',
        'departments' => 'Departments',
    ];

    ob_start();
    ?>
    <script>var ajaxurl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';</script>

    <style>
    @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600;700&display=swap');

    /* ════════════════════════════════════════════════════
       DESIGN TOKENS — Deep Navy + Warm Slate Academic Theme
       ════════════════════════════════════════════════════ */
    :root {
        --cs-navy:         #0f1e35;
        --cs-navy-mid:     #172842;
        --cs-navy-light:   #1e3555;
        --cs-navy-rim:     #2a4a6e;
        --cs-amber:        #e8a838;
        --cs-amber-light:  #f4c46a;
        --cs-amber-dim:    #c48c24;
        --cs-surface:      #ffffff;
        --cs-surface-2:    #f5f7fa;
        --cs-surface-3:    #edf0f5;
        --cs-border:       #dde3ec;
        --cs-border-soft:  #eaeff5;
        --cs-text-primary: #111827;
        --cs-text-secondary:#374151;
        --cs-text-muted:   #6b7280;
        --cs-accent:       #1a4f8a;
        --cs-accent-hover: #133d6e;
        --cs-blue-dark:    #0f2d52;
        --cs-blue-mid:     #1a4f8a;
        --cs-blue-light:   #3b82c4;
        --cs-blue-muted:   #7aa8d4;
        --cs-shadow-sm:    0 1px 3px rgba(15,30,53,.07), 0 1px 2px rgba(15,30,53,.05);
        --cs-shadow-md:    0 4px 16px rgba(15,30,53,.10), 0 2px 6px rgba(15,30,53,.07);
        --cs-shadow-lg:    0 20px 50px rgba(15,30,53,.18);
        --cs-radius:       10px;
        --cs-radius-sm:    7px;
        --cs-radius-lg:    14px;
        --sidebar-w:       220px;
    }

    html, body { width: 100%; min-height: 100%; margin: 0; padding: 0; }

    /* ── App Shell ──────────────────────────────────────── */
    .cs-wrap {
        width: 100%;
        min-height: 100vh;
        margin: 0;
        padding: 0;
        font-family: 'DM Sans', system-ui, sans-serif;
        color: var(--cs-text-primary);
        background: var(--cs-surface-2);
        box-sizing: border-box;
        display: flex;
        flex-direction: column;
    }
    .cs-wrap * { box-sizing: border-box; }

    /* ── Top Bar ─────────────────────────────────────────── */
    .cs-topbar {
        background: var(--cs-navy);
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0 24px;
        height: 58px;
        flex-shrink: 0;
        position: sticky;
        top: 0;
        z-index: 100;
        box-shadow: 0 2px 12px rgba(0,0,0,.25);
    }
    .cs-topbar-brand {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        font-family: 'Playfair Display', Georgia, serif;
        font-weight: 700;
        color: #ffffff;
        font-size: 20px;
        letter-spacing: .3px;
    }
    .cs-topbar-brand-dot {
        width: 8px; height: 8px;
        border-radius: 50%;
        background: var(--cs-amber);
        display: inline-block;
        margin-bottom: 2px;
    }
    .cs-topbar-icon {
        width: 34px; height: 34px;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 8px;
        background: rgba(232,168,56,.15);
        color: var(--cs-amber);
    }
    .cs-back-btn {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 7px 16px;
        border-radius: 6px;
        background: rgba(255,255,255,.10);
        border: 1px solid rgba(255,255,255,.18);
        color: #e8ecf2;
        font-family: 'DM Sans', sans-serif;
        font-size: 13px;
        font-weight: 500;
        text-decoration: none;
        transition: all .18s;
    }
    .cs-back-btn:hover {
        background: rgba(255,255,255,.18);
        color: #ffffff;
    }

    /* ── Layout: Sidebar + Content ─────────────────────── */
    .cs-layout {
        display: flex;
        flex: 1;
        min-height: 0;
    }

    /* ── Sidebar Navigation ─────────────────────────────── */
    .cs-sidebar {
        width: var(--sidebar-w);
        flex-shrink: 0;
        background: var(--cs-navy-mid);
        padding: 20px 12px;
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-height: calc(100vh - 58px);
        position: sticky;
        top: 58px;
        align-self: flex-start;
    }
    .cs-sidebar-label {
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 1.2px;
        text-transform: uppercase;
        color: rgba(255,255,255,.32);
        padding: 4px 10px 8px;
        margin-top: 8px;
    }
    .cs-sidebar-label:first-child { margin-top: 0; }
    .cs-tab-btn {
        display: flex;
        align-items: center;
        gap: 9px;
        width: 100%;
        padding: 9px 12px;
        border: none;
        background: transparent;
        cursor: pointer;
        font-family: 'DM Sans', sans-serif;
        font-size: 13.5px;
        font-weight: 500;
        color: rgba(255,255,255,.62);
        border-radius: 7px;
        transition: all .16s ease;
        text-align: left;
    }
    .cs-tab-btn .tab-icon {
        width: 18px; height: 18px;
        flex-shrink: 0;
        opacity: .7;
    }
    .cs-tab-btn:hover {
        color: #ffffff;
        background: rgba(255,255,255,.08);
    }
    .cs-tab-btn:hover .tab-icon { opacity: 1; }
    .cs-tab-btn.active {
        background: var(--cs-amber);
        color: var(--cs-navy);
        font-weight: 700;
        box-shadow: 0 2px 10px rgba(232,168,56,.35);
    }
    .cs-tab-btn.active .tab-icon { opacity: 1; }

    /* ── Main Content Area ──────────────────────────────── */
    .cs-main {
        flex: 1;
        padding: 28px 32px;
        min-width: 0;
        overflow-x: hidden;
    }

    /* ── Page Header ─────────────────────────────────────── */
    .cs-page-header {
        margin-bottom: 28px;
    }
    .cs-page-title {
        font-family: 'Playfair Display', Georgia, serif;
        font-size: 26px;
        font-weight: 700;
        color: var(--cs-navy);
        margin: 0 0 4px;
        line-height: 1.2;
    }
    .cs-page-subtitle {
        font-size: 13.5px;
        color: var(--cs-text-muted);
        margin: 0;
    }

    /* ── Stat Cards ─────────────────────────────────────── */
    .cs-stat-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 16px;
        margin-bottom: 28px;
    }
    .cs-stat-card {
        background: var(--cs-surface);
        border: 1px solid var(--cs-border);
        border-radius: var(--cs-radius);
        padding: 20px 20px 18px;
        display: flex;
        align-items: flex-start;
        gap: 14px;
        box-shadow: var(--cs-shadow-sm);
        transition: box-shadow .2s, transform .2s;
        position: relative;
        overflow: hidden;
    }
    .cs-stat-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--cs-amber), var(--cs-amber-light));
        border-radius: 10px 10px 0 0;
    }
    .cs-stat-card:hover {
        box-shadow: var(--cs-shadow-md);
        transform: translateY(-2px);
    }
    .cs-stat-icon {
        width: 44px; height: 44px;
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
        background: var(--cs-surface-3);
    }
    .cs-stat-label {
        font-size: 11px;
        font-weight: 600;
        letter-spacing: .7px;
        text-transform: uppercase;
        color: var(--cs-text-muted);
        margin-bottom: 5px;
    }
    .cs-stat-num {
        font-family: 'Playfair Display', Georgia, serif;
        font-size: 30px;
        font-weight: 700;
        color: var(--cs-navy);
        line-height: 1;
    }

    /* ── Panels ──────────────────────────────────────────── */
    .cs-panel {
        background: var(--cs-surface);
        border: 1px solid var(--cs-border);
        border-radius: var(--cs-radius);
        padding: 24px 26px;
        margin-bottom: 20px;
        box-shadow: var(--cs-shadow-sm);
    }
    .cs-panel h3 {
        font-family: 'Playfair Display', Georgia, serif;
        font-size: 17px;
        font-weight: 700;
        color: var(--cs-navy);
        margin: 0 0 18px;
        padding-bottom: 14px;
        border-bottom: 1px solid var(--cs-border);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .cs-panel h3::before {
        content: '';
        display: inline-block;
        width: 4px; height: 18px;
        background: var(--cs-amber);
        border-radius: 3px;
        flex-shrink: 0;
    }
    .cs-panel h4 {
        font-family: 'DM Sans', sans-serif;
        font-size: 13.5px;
        font-weight: 700;
        color: var(--cs-blue-mid);
        margin: 0 0 12px;
        text-transform: uppercase;
        letter-spacing: .5px;
    }

    /* ── Panel Action Bar ─────────────────────────────────── */
    .cs-panel-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }
    .cs-panel-actions-right {
        margin-left: auto;
        display: flex; gap: 8px;
    }

    /* ── Forms ───────────────────────────────────────────── */
    .cs-form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 18px;
    }
    .cs-field {
        display: flex;
        flex-direction: column;
        gap: 0;
    }
    .cs-field label {
        display: block;
        font-size: 11px;
        font-weight: 700;
        color: var(--cs-text-secondary);
        margin-bottom: 7px;
        text-transform: uppercase;
        letter-spacing: .7px;
    }
    .cs-field input,
    .cs-field select,
    .cs-field textarea {
        width: 100%;
        padding: 9px 13px;
        border: 1.5px solid var(--cs-border);
        border-radius: var(--cs-radius-sm);
        font-family: 'DM Sans', sans-serif;
        font-size: 13.5px;
        color: var(--cs-text-primary);
        background: var(--cs-surface);
        transition: border-color .15s, box-shadow .15s;
        line-height: 1.4;
    }
    .cs-field input::placeholder,
    .cs-field textarea::placeholder { color: #a0aec0; }
    .cs-field input:focus,
    .cs-field select:focus,
    .cs-field textarea:focus {
        outline: none;
        border-color: var(--cs-blue-mid);
        box-shadow: 0 0 0 3px rgba(26,79,138,.12);
        background: #fff;
    }
    .cs-field.span2 { grid-column: span 2; }
    .cs-field.span3 { grid-column: span 3; }

    /* ── Buttons ──────────────────────────────────────────── */
    .cs-btn {
        display: inline-flex; align-items: center; gap: 7px;
        padding: 9px 18px;
        border-radius: var(--cs-radius-sm);
        font-family: 'DM Sans', sans-serif;
        font-size: 13.5px;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: all .16s ease;
        letter-spacing: .1px;
        white-space: nowrap;
    }
    .cs-btn-primary {
        background: var(--cs-navy);
        color: #ffffff;
        box-shadow: 0 2px 6px rgba(15,30,53,.22);
    }
    .cs-btn-primary:hover {
        background: var(--cs-navy-light);
        box-shadow: 0 4px 14px rgba(15,30,53,.30);
        transform: translateY(-1px);
    }
    .cs-btn-amber {
        background: var(--cs-amber);
        color: var(--cs-navy);
        box-shadow: 0 2px 6px rgba(232,168,56,.30);
    }
    .cs-btn-amber:hover {
        background: var(--cs-amber-light);
        box-shadow: 0 4px 12px rgba(232,168,56,.40);
        transform: translateY(-1px);
    }
    .cs-btn-secondary {
        background: var(--cs-surface);
        color: var(--cs-text-secondary);
        border: 1.5px solid var(--cs-border);
    }
    .cs-btn-secondary:hover {
        background: var(--cs-surface-3);
        color: var(--cs-text-primary);
        border-color: #b8c4d4;
    }
    .cs-btn-danger {
        background: #fff1f1;
        color: #c0192f;
        border: 1.5px solid #f9c6c6;
    }
    .cs-btn-danger:hover {
        background: #ffe0e0;
        color: #9a1224;
        border-color: #f4a8a8;
    }
    .cs-btn-sm { padding: 5px 12px; font-size: 12px; gap: 5px; }
    .cs-btn:disabled { opacity: .48; cursor: not-allowed; transform: none !important; box-shadow: none !important; }

    /* ── Cascade Action Button ───────────────────────────── */
    .cs-action-wrap { position: relative; display: inline-block; }
    .cs-action-trigger {
        width: 30px; height: 30px; padding: 0;
        border-radius: 50%;
        background: var(--cs-surface);
        border: 1.5px solid var(--cs-border);
        color: var(--cs-text-secondary);
        cursor: pointer;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 18px; line-height: 1;
        transition: background .15s, border-color .15s, color .15s;
        vertical-align: middle;
    }
    .cs-action-trigger:hover { background: var(--cs-surface-3); border-color: #b8c4d4; color: var(--cs-text-primary); }
    .cs-action-trigger.cs-act-open { background: var(--cs-primary); border-color: var(--cs-primary); color: #fff; }
    .cs-action-menu {
        position: absolute;
        top: 0;
        right: 36px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        pointer-events: none;
        opacity: 0;
        transform: translateX(6px);
        transition: opacity .18s ease, transform .18s ease;
    }
    .cs-action-menu.cs-act-open {
        opacity: 1;
        transform: translateX(0);
        pointer-events: auto;
    }
    .cs-action-menu .cs-btn {
        white-space: nowrap;
        box-shadow: 0 3px 10px rgba(0,0,0,.13);
        position: relative;
        z-index: 1;
        margin-top: 4px;
        border-radius: 6px !important;
        transition: all .14s ease;
    }
    .cs-action-menu .cs-btn:first-child { margin-top: 0; }
    .cs-action-menu .cs-btn:hover { z-index: 3; transform: translateX(-3px); }
    .cs-btn-group {
        display: flex; gap: 8px; flex-wrap: wrap;
        margin-top: 22px; align-items: center;
        padding-top: 18px;
        border-top: 1px solid var(--cs-border-soft);
    }

    /* Modal buttons use the same classes — explicit scope just in case */
    .cs-modal .cs-btn {
        display: inline-flex; align-items: center; gap: 7px;
        padding: 9px 18px;
        border-radius: var(--cs-radius-sm);
        font-family: 'DM Sans', sans-serif;
        font-size: 13.5px;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: all .16s ease;
    }
    .cs-modal .cs-btn-primary { background: var(--cs-navy); color: #ffffff; box-shadow: 0 2px 6px rgba(15,30,53,.22); }
    .cs-modal .cs-btn-primary:hover { background: var(--cs-navy-light); transform: translateY(-1px); }
    .cs-modal .cs-btn-secondary { background: var(--cs-surface); color: var(--cs-text-secondary); border: 1.5px solid var(--cs-border); }
    .cs-modal .cs-btn-secondary:hover { background: var(--cs-surface-3); color: var(--cs-text-primary); }
    .cs-modal .cs-btn:disabled { opacity: .48; cursor: not-allowed; transform: none !important; }

    /* ── Table ───────────────────────────────────────────── */
    .cs-table-wrap {
        overflow-x: auto;
        border-radius: var(--cs-radius-sm);
        border: 1px solid var(--cs-border);
    }
    .cs-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13.5px;
    }
    .cs-table thead th {
        background: var(--cs-navy);
        padding: 11px 16px;
        text-align: left;
        font-size: 10.5px;
        font-weight: 700;
        color: rgba(255,255,255,.75);
        text-transform: uppercase;
        letter-spacing: .8px;
        border-bottom: none;
    }
    .cs-table thead th:first-child { border-radius: 7px 0 0 0; }
    .cs-table thead th:last-child  { border-radius: 0 7px 0 0; }
    .cs-table tbody td {
        padding: 12px 16px;
        border-bottom: 1px solid var(--cs-border-soft);
        vertical-align: middle;
        color: var(--cs-text-primary);
    }
    .cs-table tbody tr:last-child td { border-bottom: none; }
    .cs-table tbody tr:hover { background: var(--cs-surface-2); }
    .cs-table tbody tr:hover td { border-bottom-color: var(--cs-border); }

    /* ── Badges ──────────────────────────────────────────── */
    .cs-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 11.5px;
        font-weight: 600;
        letter-spacing: .2px;
    }
    .cs-badge-green  { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .cs-badge-gray   { background: var(--cs-surface-3); color: var(--cs-text-muted); border: 1px solid var(--cs-border); }
    .cs-badge-blue   { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
    .cs-badge-yellow { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }

    /* ── Notices ─────────────────────────────────────────── */
    .cs-notice {
        padding: 11px 15px;
        border-radius: var(--cs-radius-sm);
        font-size: 13.5px;
        margin: 12px 0;
        font-weight: 500;
    }
    .cs-notice-success { background: #f0fdf4; color: #15803d; border-left: 3px solid #22c55e; }
    .cs-notice-error   { background: #fff1f2; color: #be123c; border-left: 3px solid #f43f5e; }
    .cs-notice-info    { background: #eff6ff; color: #1d4ed8; border-left: 3px solid #3b82f6; }

    /* ── Timetable Grid ──────────────────────────────────── */
    .cs-grid-wrap { overflow-x: auto; border-radius: var(--cs-radius-sm); }
    .cs-timetable {
        width: 100%;
        border-collapse: collapse;
        font-size: 12.5px;
        min-width: 780px;
    }
    .cs-timetable th {
        background: var(--cs-navy);
        color: rgba(255,255,255,.85);
        padding: 12px 10px;
        text-align: center;
        font-family: 'DM Sans', sans-serif;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .8px;
        text-transform: uppercase;
        border: 1px solid var(--cs-navy-light);
    }
    .cs-timetable td {
        border: 1px solid var(--cs-border);
        padding: 0;
        vertical-align: top;
        min-width: 110px;
    }
    .cs-slot-label {
        background: var(--cs-navy-mid);
        color: rgba(255,255,255,.75);
        padding: 10px 14px;
        font-weight: 600;
        font-size: 11.5px;
        white-space: nowrap;
        text-align: center;
        border: 1px solid var(--cs-navy-light);
    }
    .cs-cell { padding: 7px 8px; min-height: 68px; background: var(--cs-surface); }
    .cs-cell-entry {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 6px;
        padding: 6px 9px;
        line-height: 1.5;
        color: #1e3a6e;
        border-left: 3px solid var(--cs-blue-mid);
    }
    .cs-cell-entry.lab {
        background: #f0fdf4;
        border-color: #bbf7d0;
        border-left-color: #16a34a;
        color: #14532d;
    }
    .cs-cell-entry .ce-code { font-weight: 700; font-size: 12px; display: block; }
    .cs-cell-entry .ce-inst { font-size: 11px; color: #2563eb; opacity: .8; }
    .cs-cell-entry .ce-room { font-size: 11px; color: var(--cs-text-muted); font-style: italic; }
    .cs-cell-empty { background: var(--cs-surface-2); }

    /* ── Modal ───────────────────────────────────────────── */
    .cs-modal-overlay {
        display: none;
        position: fixed; inset: 0;
        background: rgba(10,20,40,.55);
        backdrop-filter: blur(3px);
        z-index: 9999;
        align-items: center;
        justify-content: center;
    }
    .cs-modal-overlay.open { display: flex; }
    .cs-modal {
        background-color: #ffffff;
        border-radius: var(--cs-radius-lg);
        padding: 32px 34px;
        width: 94%;
        max-width: 600px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 24px 64px rgba(10,20,40,.30);
        border: 1px solid var(--cs-border);
        position: relative;
        font-family: 'DM Sans', sans-serif;
        font-size: 13.5px;
        color: var(--cs-text-primary);
        box-sizing: border-box;
        animation: csModalIn .2s ease;
    }
    @keyframes csModalIn {
        from { opacity: 0; transform: translateY(12px) scale(.98); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
    }
    .cs-modal * { box-sizing: border-box; }
    .cs-modal-title {
        font-family: 'Playfair Display', Georgia, serif;
        font-size: 18px;
        font-weight: 700;
        color: var(--cs-navy);
        margin: 0 0 22px;
        padding-bottom: 16px;
        border-bottom: 1px solid var(--cs-border);
    }
    .cs-modal-close {
        position: absolute; top: 20px; right: 22px;
        background: var(--cs-surface-3);
        border: 1px solid var(--cs-border);
        border-radius: 50%;
        width: 32px; height: 32px;
        cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        color: var(--cs-text-muted);
        font-size: 16px;
        transition: all .15s;
    }
    .cs-modal-close:hover {
        background: var(--cs-border);
        color: var(--cs-text-primary);
    }
    .cs-modal .cs-form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 18px;
    }
    .cs-modal .cs-field { display: flex; flex-direction: column; }
    .cs-modal .cs-field label {
        display: block;
        font-family: 'DM Sans', sans-serif;
        font-size: 11px;
        font-weight: 700;
        color: var(--cs-text-secondary);
        margin-bottom: 7px;
        text-transform: uppercase;
        letter-spacing: .7px;
    }
    .cs-modal .cs-field input,
    .cs-modal .cs-field select,
    .cs-modal .cs-field textarea {
        width: 100%;
        padding: 9px 13px;
        border: 1.5px solid var(--cs-border);
        border-radius: var(--cs-radius-sm);
        font-family: 'DM Sans', sans-serif;
        font-size: 13.5px;
        color: var(--cs-text-primary);
        background-color: var(--cs-surface);
        transition: border-color .15s, box-shadow .15s;
        line-height: 1.4;
        -webkit-appearance: auto;
        appearance: auto;
    }
    .cs-modal .cs-field input::placeholder,
    .cs-modal .cs-field textarea::placeholder { color: #a0aec0; }
    .cs-modal .cs-field input:focus,
    .cs-modal .cs-field select:focus,
    .cs-modal .cs-field textarea:focus {
        outline: none;
        border-color: var(--cs-blue-mid);
        box-shadow: 0 0 0 3px rgba(26,79,138,.12);
        background-color: #fff;
    }
    .cs-modal .cs-field select option { background-color: #fff; color: var(--cs-text-primary); }
    .cs-modal .cs-btn-group {
        display: flex; gap: 8px; flex-wrap: wrap;
        margin-top: 22px; align-items: center;
        padding-top: 18px;
        border-top: 1px solid var(--cs-border-soft);
    }
    .cs-modal .cs-notice {
        padding: 11px 15px; border-radius: 7px;
        font-size: 13.5px; margin: 12px 0; font-weight: 500;
    }
    .cs-modal .cs-notice-error   { background: #fff1f2; color: #be123c; border-left: 3px solid #f43f5e; }
    .cs-modal .cs-notice-success { background: #f0fdf4; color: #15803d; border-left: 3px solid #22c55e; }
    .cs-modal .cs-notice-info    { background: #eff6ff; color: #1d4ed8; border-left: 3px solid #3b82f6; }

    /* ── Quick Start Guide ────────────────────────────────── */
    .cs-quickstart { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
    .cs-quickstart li {
        display: flex; align-items: flex-start; gap: 12px;
        padding: 11px 14px;
        background: var(--cs-surface-2);
        border-radius: 8px;
        border: 1px solid var(--cs-border-soft);
        font-size: 13.5px;
        color: var(--cs-text-secondary);
        line-height: 1.5;
    }
    .cs-quickstart li .qs-num {
        width: 22px; height: 22px; border-radius: 50%;
        background: var(--cs-navy); color: var(--cs-amber);
        font-size: 11px; font-weight: 700;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0; margin-top: 1px;
    }

    /* ── Subjects Handled Picker ─────────────────────── */
    .cs-subj-list { border: 1.5px solid var(--cs-border); border-radius: 8px; overflow: hidden; }
    .cs-subj-list-header {
        background: var(--cs-surface-2);
        padding: 8px 14px;
        border-bottom: 1px solid var(--cs-border);
        font-size: 11px; font-weight: 700;
        text-transform: uppercase; letter-spacing: .7px;
        color: var(--cs-text-muted);
    }
    .cs-subj-list-body { max-height: 180px; overflow-y: auto; padding: 8px 10px; display: flex; flex-direction: column; gap: 4px; }
    .cs-subj-item {
        display: flex; align-items: center; gap: 10px;
        padding: 7px 10px;
        border-radius: 6px;
        border: 1.5px solid var(--cs-border-soft);
        cursor: pointer;
        font-size: 13px; font-weight: 400;
        transition: border-color .12s, background .12s;
        user-select: none;
    }
    .cs-subj-item:hover { border-color: var(--cs-blue-mid); background: rgba(26,79,138,.04); }
    .cs-subj-item input[type=checkbox] { accent-color: var(--cs-navy); width: 15px; height: 15px; flex-shrink: 0; cursor: pointer; margin: 0; }
    .cs-subj-item strong { font-weight: 700; color: var(--cs-text-primary); white-space: nowrap; }
    .cs-subj-item em { color: var(--cs-text-muted); font-size: 12px; font-style: normal; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .cs-subj-empty {
        background: var(--cs-surface-2);
        border: 1.5px dashed var(--cs-border);
        border-radius: 8px;
        padding: 16px;
        text-align: center;
        color: var(--cs-text-muted);
        font-size: 13px;
    }
    .cs-subj-warn { background: #fffbeb; border: 1.5px solid #fde68a; border-radius: 8px; padding: 12px 14px; color: #92400e; font-size: 13px; }

    /* ── Print ───────────────────────────────────────────── */
    @media print {
        .cs-topbar, .cs-sidebar, .cs-btn-group,
        .cs-panel:not(.cs-print-target), .cs-filter-bar { display: none !important; }
        .cs-main { padding: 0 !important; }
        .cs-layout { display: block !important; }
        .cs-print-target { display: block !important; }
    }

    /* ── Responsive ──────────────────────────────────────── */
    @media (max-width: 768px) {
        .cs-layout { flex-direction: column; }
        .cs-sidebar {
            width: 100%; min-height: unset; position: static;
            flex-direction: row; flex-wrap: wrap;
            padding: 10px;
        }
        .cs-sidebar-label { display: none; }
        .cs-tab-btn { padding: 7px 11px; font-size: 12.5px; flex: 0 0 auto; }
        .cs-main { padding: 18px 16px; }
        .cs-topbar-brand { font-size: 16px; }
    }
    </style>

    <div class="cs-wrap">
        <div class="cs-topbar">
            <div class="cs-topbar-brand">
                <span class="cs-topbar-icon">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2" stroke-width="2"/><line x1="16" y1="2" x2="16" y2="6" stroke-width="2"/><line x1="8" y1="2" x2="8" y2="6" stroke-width="2"/><line x1="3" y1="10" x2="21" y2="10" stroke-width="2"/></svg>
                </span>
                <span>Class Scheduler</span>
                <span class="cs-topbar-brand-dot"></span>
            </div>
            <a class="cs-back-btn" href="<?php echo esc_url(admin_url()); ?>">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Dashboard
            </a>
        </div>

        <div class="cs-layout">
            <!-- Sidebar Navigation -->
            <nav class="cs-sidebar">
                <div class="cs-sidebar-label">Main</div>
                <button class="cs-tab-btn <?php echo $active_tab === 'overview' ? 'active' : ''; ?>" data-tab="overview">
                    <svg class="tab-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                    Overview
                </button>
                <button class="cs-tab-btn <?php echo $active_tab === 'timetable' ? 'active' : ''; ?>" data-tab="timetable">
                    <svg class="tab-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" stroke-width="2"/><line x1="3" y1="10" x2="21" y2="10" stroke-width="2"/><line x1="9" y1="4" x2="9" y2="20" stroke-width="2"/></svg>
                    Timetable View
                </button>

                <div class="cs-sidebar-label">Manage</div>
                <button class="cs-tab-btn <?php echo $active_tab === 'sections' ? 'active' : ''; ?>" data-tab="sections">
                    <svg class="tab-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/></svg>
                    Sections
                </button>
                <button class="cs-tab-btn <?php echo $active_tab === 'courses' ? 'active' : ''; ?>" data-tab="courses">
                    <svg class="tab-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h8M8 12h8m-8 5h5"/><rect x="4" y="3" width="16" height="18" rx="2" stroke-width="2"/></svg>
                    Courses
                </button>
                <button class="cs-tab-btn <?php echo $active_tab === 'schedule' ? 'active' : ''; ?>" data-tab="schedule">
                    <svg class="tab-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Schedule Entry
                </button>
                <button class="cs-tab-btn <?php echo $active_tab === 'instructors' ? 'active' : ''; ?>" data-tab="instructors">
                    <svg class="tab-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Instructors
                </button>
                <button class="cs-tab-btn <?php echo $active_tab === 'rooms' ? 'active' : ''; ?>" data-tab="rooms">
                    <svg class="tab-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    Rooms
                </button>
                <button class="cs-tab-btn <?php echo $active_tab === 'departments' ? 'active' : ''; ?>" data-tab="departments">
                    <svg class="tab-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16M3 21h18M9 7h1m-1 4h1m4-4h1m-1 4h1M5 21V9a2 2 0 012-2h10a2 2 0 012 2v12"/></svg>
                    Departments
                </button>
            </nav>

            <!-- Main Content -->
            <div class="cs-main">
                <div id="cs-tab-overview"    class="cs-tab-content" <?php echo $active_tab !== 'overview'    ? 'style="display:none"' : ''; ?>>
                    <?php echo cs_overview_tab(); ?>
                </div>
                <div id="cs-tab-timetable"   class="cs-tab-content" <?php echo $active_tab !== 'timetable'   ? 'style="display:none"' : ''; ?>>
                    <?php echo cs_timetable_tab(); ?>
                </div>
                <div id="cs-tab-sections"    class="cs-tab-content" <?php echo $active_tab !== 'sections'    ? 'style="display:none"' : ''; ?>>
                    <?php echo cs_sections_tab(); ?>
                </div>
                <div id="cs-tab-courses"     class="cs-tab-content" <?php echo $active_tab !== 'courses'     ? 'style="display:none"' : ''; ?>>
                    <?php echo cs_courses_tab(); ?>
                </div>
                <div id="cs-tab-schedule"    class="cs-tab-content" <?php echo $active_tab !== 'schedule'    ? 'style="display:none"' : ''; ?>>
                    <?php echo cs_schedule_tab(); ?>
                </div>
                <div id="cs-tab-instructors" class="cs-tab-content" <?php echo $active_tab !== 'instructors' ? 'style="display:none"' : ''; ?>>
                    <?php echo cs_instructors_tab(); ?>
                </div>
                <div id="cs-tab-rooms"       class="cs-tab-content" <?php echo $active_tab !== 'rooms'       ? 'style="display:none"' : ''; ?>>
                    <?php echo cs_rooms_tab(); ?>
                </div>
                <div id="cs-tab-departments" class="cs-tab-content" <?php echo $active_tab !== 'departments' ? 'style="display:none"' : ''; ?>>
                    <?php echo cs_departments_tab(); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Shared Modal -->
    <div class="cs-modal-overlay" id="cs-modal-overlay">
        <div class="cs-modal" id="cs-modal">
            <button class="cs-modal-close" id="cs-modal-close">&times;</button>
            <div id="cs-modal-body"></div>
        </div>
    </div>

    <script>
    (function () {
        // Tab switching
        document.querySelectorAll('.cs-tab-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.cs-tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.cs-tab-content').forEach(c => c.style.display = 'none');
                this.classList.add('active');
                document.getElementById('cs-tab-' + this.dataset.tab).style.display = 'block';
            });
        });

        // Modal helpers
        window.csOpenModal = function (html) {
            document.getElementById('cs-modal-body').innerHTML = html;
            document.getElementById('cs-modal-overlay').classList.add('open');
        };
        window.csCloseModal = function () {
            document.getElementById('cs-modal-overlay').classList.remove('open');
        };
        document.getElementById('cs-modal-close').addEventListener('click', csCloseModal);
        document.getElementById('cs-modal-overlay').addEventListener('click', function (e) {
            if (e.target === this) csCloseModal();
        });

        // Generic AJAX helper
        window.csAjax = function (formData) {
            return fetch(ajaxurl, { method: 'POST', body: formData }).then(r => r.json());
        };

        // Show notice helper
        window.csNotice = function (el, message, type) {
            type = type || 'success';
            el.innerHTML = '<div class="cs-notice cs-notice-' + type + '">' + message + '</div>';
            setTimeout(function () { el.innerHTML = ''; }, 4000);
        };

        // Cascade action menu toggle
        document.addEventListener('click', function (e) {
            var trigger = e.target.closest('.cs-action-trigger');
            if (trigger) {
                e.stopPropagation();
                var wrap = trigger.closest('.cs-action-wrap');
                var menu = wrap.querySelector('.cs-action-menu');
                var isOpen = menu.classList.contains('cs-act-open');
                document.querySelectorAll('.cs-action-menu.cs-act-open').forEach(function (m) {
                    m.classList.remove('cs-act-open');
                    m.closest('.cs-action-wrap').querySelector('.cs-action-trigger').classList.remove('cs-act-open');
                });
                if (!isOpen) {
                    menu.classList.add('cs-act-open');
                    trigger.classList.add('cs-act-open');
                }
                return;
            }
            document.querySelectorAll('.cs-action-menu.cs-act-open').forEach(function (m) {
                m.classList.remove('cs-act-open');
                m.closest('.cs-action-wrap').querySelector('.cs-action-trigger').classList.remove('cs-act-open');
            });
        });
    })();
    </script>
    <?php
    $content = ob_get_clean();
    return $content;
}

// ─────────────────────────────────────────────────────────────
// TAB: OVERVIEW
// ─────────────────────────────────────────────────────────────

function cs_overview_tab() {
    global $wpdb;

    $sections_count    = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cs_sections WHERE status='active'");
    $schedules_count   = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cs_schedules");
    $instructors_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cs_instructors WHERE status='active'");
    $rooms_count       = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cs_rooms WHERE status='active'");

    $recent_sections = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}cs_sections ORDER BY created_at DESC LIMIT 5"
    );

    ob_start();
    ?>
    <div class="cs-stat-row">
        <div class="cs-stat-card">
            <div class="cs-stat-icon" style="background:linear-gradient(135deg,#0f1e35,#1e3555)">
                <svg width="22" height="22" fill="none" stroke="#e8a838" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/></svg>
            </div>
            <div>
                <div class="cs-stat-label">Active Sections</div>
                <div class="cs-stat-num"><?php echo $sections_count; ?></div>
            </div>
        </div>
        <div class="cs-stat-card">
            <div class="cs-stat-icon" style="background:linear-gradient(135deg,#172842,#2a4a6e)">
                <svg width="22" height="22" fill="none" stroke="#e8a838" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" stroke-width="2"/><line x1="16" y1="2" x2="16" y2="6" stroke-width="2"/><line x1="8" y1="2" x2="8" y2="6" stroke-width="2"/><line x1="3" y1="10" x2="21" y2="10" stroke-width="2"/></svg>
            </div>
            <div>
                <div class="cs-stat-label">Schedule Entries</div>
                <div class="cs-stat-num"><?php echo $schedules_count; ?></div>
            </div>
        </div>
        <div class="cs-stat-card">
            <div class="cs-stat-icon" style="background:linear-gradient(135deg,#0f1e35,#1a4f8a)">
                <svg width="22" height="22" fill="none" stroke="#e8a838" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            </div>
            <div>
                <div class="cs-stat-label">Instructors</div>
                <div class="cs-stat-num"><?php echo $instructors_count; ?></div>
            </div>
        </div>
        <div class="cs-stat-card">
            <div class="cs-stat-icon" style="background:linear-gradient(135deg,#172842,#1e3555)">
                <svg width="22" height="22" fill="none" stroke="#e8a838" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            </div>
            <div>
                <div class="cs-stat-label">Rooms</div>
                <div class="cs-stat-num"><?php echo $rooms_count; ?></div>
            </div>
        </div>
    </div>

    <div class="cs-panel">
        <h3>Quick Start Guide</h3>
        <ol class="cs-quickstart">
            <li><span class="qs-num">1</span><span>Go to <strong>Departments</strong> and add your college or department units (e.g. College of Engineering).</span></li>
            <li><span class="qs-num">2</span><span>Go to <strong>Rooms</strong> and add your classrooms and laboratories.</span></li>
            <li><span class="qs-num">3</span><span>Go to <strong>Sections</strong> and create your class groups (e.g. CHE3 A1).</span></li>
            <li><span class="qs-num">4</span><span>Go to <strong>Schedule Entry</strong> to assign subjects, rooms, and time slots to each section.</span></li>
            <li><span class="qs-num">5</span><span>Go to <strong>Instructors</strong> and add your faculty members, assigning them to their department.</span></li>
            <li><span class="qs-num">6</span><span>View the complete timetable grid in <strong>Timetable View</strong>.</span></li>
        </ol>
    </div>

    <?php if (!empty($recent_sections)): ?>
    <div class="cs-panel">
        <h3>Recent Sections</h3>
        <div class="cs-table-wrap">
        <table class="cs-table">
            <thead>
                <tr>
                    <th>Section</th>
                    <th>Academic Year</th>
                    <th>Semester</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_sections as $sec): ?>
                <tr>
                    <td><strong><?php echo esc_html($sec->section_name); ?></strong></td>
                    <td><?php echo esc_html($sec->academic_year ?: '—'); ?></td>
                    <td><?php echo esc_html($sec->semester); ?></td>
                    <td>
                        <span class="cs-badge <?php echo $sec->status === 'active' ? 'cs-badge-green' : 'cs-badge-gray'; ?>">
                            <?php echo ucfirst($sec->status); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}

// ─────────────────────────────────────────────────────────────
// TAB: TIMETABLE VIEW
// ─────────────────────────────────────────────────────────────

function cs_timetable_tab() {
    global $wpdb;

    $sections = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}cs_sections WHERE status='active' ORDER BY section_name ASC"
    );

    $filter_section    = isset($_GET['cs_section'])    ? intval($_GET['cs_section'])                    : 0;
    $filter_year       = isset($_GET['cs_year'])       ? sanitize_text_field($_GET['cs_year'])           : '';
    $filter_sem        = isset($_GET['cs_sem'])        ? sanitize_text_field($_GET['cs_sem'])            : '';
    $filter_instructor = isset($_GET['cs_instructor']) ? sanitize_text_field($_GET['cs_instructor'])     : '';
    $filter_yr_level   = isset($_GET['cs_yr_level'])   ? sanitize_text_field($_GET['cs_yr_level'])       : '';

    // Fetch all instructors for the filter dropdown
    $all_instructors = $wpdb->get_results(
        "SELECT DISTINCT instructor_initials, instructor_name FROM {$wpdb->prefix}cs_schedules WHERE instructor_initials != '' ORDER BY instructor_initials ASC"
    );

    // Build section list to render
    $render_sections = [];
    foreach ($sections as $sec) {
        if ($filter_section && $sec->id != $filter_section) continue;
        if ($filter_year  && $sec->academic_year !== $filter_year) continue;
        if ($filter_sem   && $sec->semester !== $filter_sem) continue;
        if ($filter_yr_level !== '' && (string)$sec->year_level !== $filter_yr_level) continue;
        $render_sections[] = $sec;
    }

    ob_start();
    ?>
    <div class="cs-panel cs-filter-bar">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div class="cs-field">
                <label>Section</label>
                <select id="cs-filter-section">
                    <option value="">All Sections</option>
                    <?php foreach ($sections as $s): ?>
                    <option value="<?php echo $s->id; ?>" <?php selected($filter_section, $s->id); ?>>
                        <?php echo esc_html($s->section_name); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="cs-field">
                <label>Year Level</label>
                <select id="cs-filter-yr-level">
                    <option value="">All Year Levels</option>
                    <?php foreach ([1,2,3,4,5] as $yl): ?>
                    <option value="<?php echo $yl; ?>" <?php selected($filter_yr_level, (string)$yl); ?>>Year <?php echo $yl; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="cs-field">
                <label>Instructor</label>
                <select id="cs-filter-instructor">
                    <option value="">All Instructors</option>
                    <?php foreach ($all_instructors as $inst): ?>
                    <option value="<?php echo esc_attr($inst->instructor_initials); ?>" <?php selected($filter_instructor, $inst->instructor_initials); ?>>
                        <?php echo esc_html($inst->instructor_initials); ?><?php if ($inst->instructor_name): ?> — <?php echo esc_html($inst->instructor_name); ?><?php endif; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="cs-field">
                <label>Academic Year</label>
                <input type="text" id="cs-filter-year" value="<?php echo esc_attr($filter_year); ?>" placeholder="e.g. 2024-2025">
            </div>
            <div class="cs-field">
                <label>Semester</label>
                <select id="cs-filter-sem">
                    <option value="">All Semesters</option>
                    <option value="First"  <?php selected($filter_sem, 'First'); ?>>First</option>
                    <option value="Second" <?php selected($filter_sem, 'Second'); ?>>Second</option>
                    <option value="Summer" <?php selected($filter_sem, 'Summer'); ?>>Summer</option>
                </select>
            </div>
            <!-- empty cell to keep grid even, buttons span full width below -->
            <div></div>
        </div>
        <div style="display:flex;gap:10px;margin-top:16px;padding-top:14px;border-top:1px solid var(--cs-border-soft);">
            <button class="cs-btn cs-btn-primary"   id="cs-apply-filter" style="flex:1;">Apply Filter</button>
            <button class="cs-btn cs-btn-secondary" id="cs-clear-filter" style="flex:1;">Clear</button>
            <button class="cs-btn cs-btn-secondary" id="cs-print-btn"   style="flex:1;">
                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Print
            </button>
        </div>
    </div>

    <div id="cs-timetable-area">
    <?php if (empty($render_sections)): ?>
        <div class="cs-notice cs-notice-info">No sections found. Create sections in the Sections tab first.</div>
    <?php else: ?>
        <?php foreach ($render_sections as $sec):
            // Load all schedule entries for this section
            $entries = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}cs_schedules WHERE section_id=%d",
                $sec->id
            ));
            // Apply instructor filter if set
            if ($filter_instructor !== '') {
                $entries = array_filter($entries, function($e) use ($filter_instructor) {
                    return strtoupper($e->instructor_initials) === strtoupper($filter_instructor);
                });
                $entries = array_values($entries);
            }

            // Determine canonical day groups and time slots from the legacy map + any custom values
            $legacy_dg_map = [
                'mon_thu' => ['Mon', 'Thu'],
                'tue_fri' => ['Tue', 'Fri'],
                'wed_sat' => ['Wed', 'Sat'],
            ];
            $day_order = ['Mon'=>0,'Tue'=>1,'Wed'=>2,'Thu'=>3,'Fri'=>4,'Sat'=>5,'Sun'=>6];
            $day_labels = ['Mon'=>'Monday','Tue'=>'Tuesday','Wed'=>'Wednesday','Thu'=>'Thursday',
                           'Fri'=>'Friday','Sat'=>'Saturday','Sun'=>'Sunday'];

            // Helper: normalise time_slot to "HH:MM-HH:MM"
            $norm_slot = function($s) {
                if (preg_match('/^(\d{1,2}:\d{2})\s*[-–]\s*(\d{1,2}:\d{2})$/', trim($s), $m)) {
                    $pad = fn($t) => strlen($t) === 4 ? '0'.$t : $t;
                    return $pad($m[1]) . '-' . $pad($m[2]);
                }
                return trim($s);
            };

            // Expand entries into (day_abbrev, time_slot) pairs
            $active_days = [];   // day abbrevs actually used
            $active_slots = [];  // normalised time slots used
            $grid = [];          // $grid[day_abbrev][time_slot] = entry

            foreach ($entries as $e) {
                $slot = $norm_slot($e->time_slot);

                // Determine which days this entry covers
                $days_for_entry = [];
                if (isset($legacy_dg_map[$e->day_group])) {
                    // legacy key like "mon_thu"
                    $days_for_entry = $legacy_dg_map[$e->day_group];
                } else {
                    // new CSV format like "Mon,Wed,Fri"
                    $days_for_entry = array_filter(
                        array_map('trim', explode(',', $e->day_group)),
                        fn($d) => isset($day_order[$d])
                    );
                }

                foreach ($days_for_entry as $day) {
                    if (!in_array($day, $active_days)) $active_days[] = $day;
                    $grid[$day][$slot] = $e;
                }
                if (!in_array($slot, $active_slots)) $active_slots[] = $slot;
            }

            // Sort days by canonical week order, slots alphabetically (HH:MM sorts correctly)
            usort($active_days, fn($a,$b) => ($day_order[$a]??99) - ($day_order[$b]??99));
            sort($active_slots);

            // Fall back to a default slot list if section has no entries yet
            if (empty($active_slots)) $active_slots = array_map($norm_slot, BNTM_CS_TIME_SLOTS);
            if (empty($active_days))  $active_days  = ['Mon','Tue','Wed','Thu','Fri','Sat'];
        ?>
        <div class="cs-panel cs-print-target" style="margin-bottom:28px;">
            <h3>
                <?php echo esc_html($sec->section_name); ?>
                <?php if ($sec->academic_year): ?>
                <span style="font-weight:400;color:#5c7ea6;font-size:13px;margin-left:8px;"><?php echo esc_html($sec->academic_year); ?> &bull; <?php echo esc_html($sec->semester); ?> Semester</span>
                <?php endif; ?>
            </h3>
            <div class="cs-grid-wrap">
            <table class="cs-timetable">
                <thead>
                    <tr>
                        <th style="min-width:110px;">Time</th>
                        <?php foreach ($active_days as $day): ?>
                        <th><?php echo esc_html($day_labels[$day] ?? $day); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($active_slots as $slot): ?>
                    <tr>
                        <td class="cs-slot-label"><?php echo esc_html(str_replace('-', ' – ', $slot)); ?></td>
                        <?php foreach ($active_days as $day):
                            if (isset($grid[$day][$slot])):
                                $e = $grid[$day][$slot];
                                $is_lab = (strpos($e->subject_code, ' L') !== false);
                        ?>
                        <td class="cs-cell">
                            <div class="cs-cell-entry <?php echo $is_lab ? 'lab' : ''; ?>">
                                <span class="ce-code"><?php echo esc_html($e->subject_code); ?></span>
                                <span class="ce-inst"><?php echo esc_html($e->instructor_initials); ?></span>
                                <span class="ce-room"><?php echo esc_html($e->room); ?></span>
                            </div>
                        </td>
                        <?php else: ?>
                        <td class="cs-cell cs-cell-empty"></td>
                        <?php endif; endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
    </div>

    <script>
    document.getElementById('cs-apply-filter').addEventListener('click', function () {
        const sec  = document.getElementById('cs-filter-section').value;
        const yr   = document.getElementById('cs-filter-year').value;
        const sem  = document.getElementById('cs-filter-sem').value;
        const inst = document.getElementById('cs-filter-instructor').value;
        const yl   = document.getElementById('cs-filter-yr-level').value;
        const url  = new URL(window.location.href);
        sec  ? url.searchParams.set('cs_section', sec)       : url.searchParams.delete('cs_section');
        yr   ? url.searchParams.set('cs_year', yr)           : url.searchParams.delete('cs_year');
        sem  ? url.searchParams.set('cs_sem', sem)           : url.searchParams.delete('cs_sem');
        inst ? url.searchParams.set('cs_instructor', inst)   : url.searchParams.delete('cs_instructor');
        yl   ? url.searchParams.set('cs_yr_level', yl)       : url.searchParams.delete('cs_yr_level');
        window.location.href = url.toString() + '#cs-tab-timetable';
    });
    document.getElementById('cs-clear-filter').addEventListener('click', function () {
        const url = new URL(window.location.href);
        ['cs_section','cs_year','cs_sem','cs_instructor','cs_yr_level'].forEach(p => url.searchParams.delete(p));
        window.location.href = url.toString() + '#cs-tab-timetable';
    });
    document.getElementById('cs-print-btn').addEventListener('click', function () { window.print(); });
    </script>
    <?php
    return ob_get_clean();
}

// ─────────────────────────────────────────────────────────────
// TAB: SECTIONS
// ─────────────────────────────────────────────────────────────

function cs_courses_tab() {
    global $wpdb;
    $course_table = $wpdb->prefix . 'cs_courses';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$course_table}'") !== $course_table) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$course_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            course_code VARCHAR(20) NOT NULL,
            course_title VARCHAR(180) NOT NULL DEFAULT '',
            department TEXT NOT NULL DEFAULT '',
            curriculum VARCHAR(120) NOT NULL DEFAULT '',
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) {$charset};");
    }

    $courses = $wpdb->get_results("SELECT * FROM {$course_table} ORDER BY course_code ASC, curriculum ASC");
    $departments = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}cs_departments WHERE status='active' ORDER BY dept_name ASC"
    );
    $nonce = wp_create_nonce('cs_courses_nonce');

    ob_start();
    ?>
    <div class="cs-panel">
        <h3>Courses</h3>
        <button class="cs-btn cs-btn-primary" id="cs-add-course-btn">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Course
        </button>
        <div id="cs-course-notice"></div>
    </div>

    <div class="cs-panel">
        <?php if (empty($courses)): ?>
        <p style="color:#5c7ea6;font-size:13.5px;">No courses yet. Add one so sections can select from your list.</p>
        <?php else: ?>
        <div class="cs-table-wrap">
        <table class="cs-table">
            <thead>
                <tr><th>Course Code</th><th>Course Title</th><th>Department(s)</th><th>Curriculum</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($courses as $course): ?>
                <tr>
                    <td><strong><?php echo esc_html($course->course_code); ?></strong></td>
                    <td><?php echo esc_html($course->course_title ?: '—'); ?></td>
                    <td><?php
                        $depts = array_filter(array_map('trim', explode(',', $course->department)));
                        if ($depts) {
                            foreach ($depts as $d) echo '<span class="cs-badge cs-badge-blue" style="margin:1px 2px;">' . esc_html($d) . '</span>';
                        } else { echo '—'; }
                    ?></td>
                    <td><?php echo esc_html($course->curriculum ?: '—'); ?></td>
                    <td><span class="cs-badge <?php echo $course->status === 'active' ? 'cs-badge-green' : 'cs-badge-gray'; ?>"><?php echo ucfirst($course->status); ?></span></td>
                    <td>
                        <div class="cs-action-wrap">
                            <button class="cs-action-trigger" title="Actions">&#8942;</button>
                            <div class="cs-action-menu">
                                <button class="cs-btn cs-btn-secondary cs-btn-sm cs-edit-course"
                                        data-id="<?php echo $course->id; ?>"
                                        data-course="<?php echo esc_attr($course->course_code); ?>"
                                        data-title="<?php echo esc_attr($course->course_title); ?>"
                                        data-department="<?php echo esc_attr($course->department); ?>"
                                        data-curriculum="<?php echo esc_attr($course->curriculum); ?>"
                                        data-status="<?php echo esc_attr($course->status); ?>">Edit</button>
                                <button class="cs-btn cs-btn-danger cs-btn-sm cs-del-course"
                                        data-id="<?php echo $course->id; ?>"
                                        data-name="<?php echo esc_attr($course->course_code . ' ' . $course->curriculum); ?>">Delete</button>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <script>
    (function () {
        const nonce = '<?php echo $nonce; ?>';
        const deptOptions = <?php echo json_encode(array_map(function($d){ return ['name'=>$d->dept_name,'code'=>$d->dept_code]; }, $departments)); ?>;

        function courseForm(data) {
            data = data || {};
            const selectedDepts = (data.department||'').split(',').map(s => s.trim()).filter(Boolean);
            const deptSelect = deptOptions.length
                ? `<div style="max-height:130px;overflow-y:auto;border:1px solid #dce3f0;border-radius:6px;padding:8px 10px;display:flex;flex-direction:column;gap:5px;">
                    ${deptOptions.map(d => {
                        const checked = selectedDepts.includes(d.name) ? 'checked' : '';
                        return `<label style="display:flex;align-items:center;gap:6px;font-weight:normal;cursor:pointer;">
                            <input type="checkbox" class="cf-dept-check" value="${d.name.replace(/"/g,'&quot;')}" ${checked}>
                            ${d.name}${d.code?' <small style=\'color:#5c7ea6;\'>('+d.code+')</small>':''}
                        </label>`;
                    }).join('')}
                   </div>`
                : `<input type="text" id="cf-department" value="${data.department||''}" placeholder="e.g. Engineering, Science (comma-separated)" maxlength="500">`;
            return `
            <h2 class="cs-modal-title">${data.id ? 'Edit Course' : 'Add Course'}</h2>
            <div class="cs-form-grid">
                <div class="cs-field">
                    <label>Course Code *</label>
                    <input type="text" id="cf-course" value="${data.course||''}" placeholder="e.g. BSIT" maxlength="20">
                </div>
                <div class="cs-field">
                    <label>Course Title</label>
                    <input type="text" id="cf-title" value="${data.title||''}" placeholder="e.g. Bachelor of Science in Information Technology" maxlength="180">
                </div>
                <div class="cs-field">
                    <label>Department</label>
                    ${deptSelect}
                </div>
                <div class="cs-field">
                    <label>Curriculum</label>
                    <input type="text" id="cf-curriculum" value="${data.curriculum||''}" placeholder="e.g. 2024" maxlength="120">
                </div>
                ${data.id ? `<div class="cs-field">
                    <label>Status</label>
                    <select id="cf-status">
                        <option value="active" ${data.status==='active'?'selected':''}>Active</option>
                        <option value="inactive" ${data.status==='inactive'?'selected':''}>Inactive</option>
                    </select>
                </div>` : ''}
            </div>
            <div id="cf-notice"></div>
            <div class="cs-btn-group">
                <button class="cs-btn cs-btn-primary" id="cf-save">Save Course</button>
                <button class="cs-btn cs-btn-secondary" onclick="csCloseModal()">Cancel</button>
            </div>
            <input type="hidden" id="cf-id" value="${data.id||0}">`;
        }

        function bindCourseSave() {
            document.getElementById('cf-save').addEventListener('click', function () {
                const btn = this;
                const fd = new FormData();
                fd.append('action', 'cs_save_course');
                fd.append('nonce', nonce);
                fd.append('id', document.getElementById('cf-id').value);
                fd.append('course_code', document.getElementById('cf-course').value.trim().toUpperCase());
                fd.append('course_title', document.getElementById('cf-title').value.trim());
                const deptChecks = document.querySelectorAll('.cf-dept-check:checked');
                const deptVal = deptChecks.length
                    ? Array.from(deptChecks).map(c => c.value).join(', ')
                    : (document.getElementById('cf-department') ? document.getElementById('cf-department').value.trim() : '');
                fd.append('department', deptVal);
                fd.append('curriculum', document.getElementById('cf-curriculum').value.trim());
                const statusEl = document.getElementById('cf-status');
                if (statusEl) fd.append('status', statusEl.value);
                btn.disabled = true; btn.textContent = 'Saving...';
                csAjax(fd).then(json => {
                    if (json.success) location.reload();
                    else { csNotice(document.getElementById('cf-notice'), json.data.message, 'error'); btn.disabled=false; btn.textContent='Save Course'; }
                });
            });
        }

        document.getElementById('cs-add-course-btn').addEventListener('click', function () {
            csOpenModal(courseForm()); bindCourseSave();
        });

        document.querySelectorAll('.cs-edit-course').forEach(function (btn) {
            btn.addEventListener('click', function () {
                csOpenModal(courseForm({
                    id: this.dataset.id,
                    course: this.dataset.course,
                    title: this.dataset.title,
                    department: this.dataset.department,
                    curriculum: this.dataset.curriculum,
                    status: this.dataset.status
                }));
                bindCourseSave();
            });
        });

        document.querySelectorAll('.cs-del-course').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm('Delete course "' + this.dataset.name + '"?')) return;
                const fd = new FormData();
                fd.append('action', 'cs_delete_course');
                fd.append('nonce', nonce);
                fd.append('id', this.dataset.id);
                csAjax(fd).then(json => {
                    if (json.success) location.reload();
                    else csNotice(document.getElementById('cs-course-notice'), json.data.message, 'error');
                });
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

function cs_sections_tab() {
    global $wpdb;
    $course_table = $wpdb->prefix . 'cs_courses';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$course_table}'") !== $course_table) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$course_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            course_code VARCHAR(20) NOT NULL,
            course_title VARCHAR(180) NOT NULL DEFAULT '',
            department TEXT NOT NULL DEFAULT '',
            curriculum VARCHAR(120) NOT NULL DEFAULT '',
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) {$charset};");
    }

    $courses = $wpdb->get_results("SELECT * FROM {$course_table} WHERE status='active' ORDER BY course_code ASC, curriculum ASC");
    $sections = $wpdb->get_results(
        "SELECT s.*, (SELECT COUNT(*) FROM {$wpdb->prefix}cs_schedules WHERE section_id=s.id) as entry_count
         FROM {$wpdb->prefix}cs_sections s ORDER BY s.section_name ASC"
    );

    $nonce = wp_create_nonce('cs_sections_nonce');
    $courses_json = wp_json_encode(array_map(function($c){
        return [
            'course_code' => $c->course_code,
            'course_title'=> $c->course_title,
            'department'  => $c->department,
            'curriculum'  => $c->curriculum,
        ];
    }, $courses));

    ob_start();
    ?>
    <div class="cs-panel">
        <h3>Class Sections</h3>
        <button class="cs-btn cs-btn-primary" id="cs-add-section-btn">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Section
        </button>
        <button class="cs-btn cs-btn-secondary" id="cs-bulk-section-btn" style="margin-left:8px;">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            Bulk Import
        </button>
        <div id="cs-section-notice"></div>
    </div>

    <div class="cs-panel">
        <?php if (empty($sections)): ?>
        <p style="color:#5c7ea6;font-size:13.5px;">No sections yet. Click "Add Section" to create one.</p>
        <?php else: ?>
        <div class="cs-table-wrap">
        <table class="cs-table">
            <thead>
                <tr>
                    <th>Section Name</th>
                    <th>Course</th>
                    <th>Year</th>
                    <th>Block</th>
                    <th>Acad. Year</th>
                    <th>Semester</th>
                    <th>Entries</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sections as $sec): ?>
                <tr>
                    <td><strong><?php echo esc_html($sec->section_name); ?></strong></td>
                    <td><?php echo esc_html($sec->course_code); ?></td>
                    <td>Year <?php echo esc_html($sec->year_level); ?></td>
                    <td><?php echo esc_html($sec->block); ?></td>
                    <td><?php echo esc_html($sec->academic_year ?: '—'); ?></td>
                    <td><?php echo esc_html($sec->semester); ?></td>
                    <td><span class="cs-badge cs-badge-blue"><?php echo (int)$sec->entry_count; ?></span></td>
                    <td><span class="cs-badge <?php echo $sec->status === 'active' ? 'cs-badge-green' : 'cs-badge-gray'; ?>"><?php echo ucfirst($sec->status); ?></span></td>
                    <td>
                        <div class="cs-action-wrap">
                            <button class="cs-action-trigger" title="Actions">&#8942;</button>
                            <div class="cs-action-menu">
                                <button class="cs-btn cs-btn-secondary cs-btn-sm cs-edit-section"
                                        data-id="<?php echo $sec->id; ?>"
                                        data-course="<?php echo esc_attr($sec->course_code); ?>"
                                        data-year="<?php echo esc_attr($sec->year_level); ?>"
                                        data-block="<?php echo esc_attr($sec->block); ?>"
                                        data-acad="<?php echo esc_attr($sec->academic_year); ?>"
                                        data-sem="<?php echo esc_attr($sec->semester); ?>"
                                        data-status="<?php echo esc_attr($sec->status); ?>">Edit</button>
                                <button class="cs-btn cs-btn-danger cs-btn-sm cs-del-section"
                                        data-id="<?php echo $sec->id; ?>"
                                        data-name="<?php echo esc_attr($sec->section_name); ?>">Delete</button>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <script>
    (function () {
        const nonce = '<?php echo $nonce; ?>';
        const courses = <?php echo $courses_json ?: '[]'; ?>;

        function sectionForm(data) {
            data = data || {};
            const selectedCourse = (data.course || '').toUpperCase();
            const courseOptions = courses.map(c => {
                const courseSelected = c.course_code.toUpperCase() === selectedCourse;
                const title = c.course_title ? ` - ${c.course_title}` : '';
                return `<option value="${c.course_code.replace(/"/g, '&quot;')}" ${courseSelected ? 'selected' : ''}>${c.course_code}${title}</option>`;
            }).join('');
            return `
            <h2 class="cs-modal-title">${data.id ? 'Edit Section' : 'Add Section'}</h2>
            <div class="cs-form-grid">
                <div class="cs-field">
                    <label>Course Code *</label>
                    <select id="sf-course">
                        <option value="">Select course</option>
                        ${courseOptions}
                    </select>
                </div>
                <div class="cs-field">
                    <label>Year Level *</label>
                    <select id="sf-year">
                        ${[1,2,3,4,5].map(y=>`<option value="${y}" ${data.year==y?'selected':''}>${y}</option>`).join('')}
                    </select>
                </div>
                <div class="cs-field">
                    <label>Block *</label>
                    <input type="text" id="sf-block" value="${data.block||''}" placeholder="e.g. A1" maxlength="10">
                </div>
                <div class="cs-field">
                    <label>Academic Year</label>
                    <input type="text" id="sf-acad" value="${data.acad||''}" placeholder="e.g. 2024-2025">
                </div>
                <div class="cs-field">
                    <label>Semester</label>
                    <select id="sf-sem">
                        ${['First','Second','Summer'].map(s=>`<option value="${s}" ${data.sem===s?'selected':''}>${s}</option>`).join('')}
                    </select>
                </div>
                ${data.id ? `<div class="cs-field">
                    <label>Status</label>
                    <select id="sf-status">
                        <option value="active" ${data.status==='active'?'selected':''}>Active</option>
                        <option value="archived" ${data.status==='archived'?'selected':''}>Archived</option>
                    </select>
                </div>` : ''}
            </div>
            <div id="sf-notice"></div>
            <div class="cs-btn-group">
                <button class="cs-btn cs-btn-primary" id="sf-save">Save Section</button>
                <button class="cs-btn cs-btn-secondary" onclick="csCloseModal()">Cancel</button>
            </div>
            <input type="hidden" id="sf-id" value="${data.id||0}">`;
        }

        document.getElementById('cs-add-section-btn').addEventListener('click', function () {
            csOpenModal(sectionForm());
            bindSectionSave();
        });

        document.querySelectorAll('.cs-edit-section').forEach(function (btn) {
            btn.addEventListener('click', function () {
                csOpenModal(sectionForm({
                    id: this.dataset.id, course: this.dataset.course,
                    year: this.dataset.year, block: this.dataset.block,
                    acad: this.dataset.acad, sem: this.dataset.sem,
                    status: this.dataset.status
                }));
                bindSectionSave();
            });
        });

        function bindSectionSave() {
            document.getElementById('sf-save').addEventListener('click', function () {
                const btn = this;
                const fd  = new FormData();
                fd.append('action', 'cs_save_section');
                fd.append('nonce', nonce);
                fd.append('id', document.getElementById('sf-id').value);
                fd.append('course_code', document.getElementById('sf-course').value.trim().toUpperCase());
                fd.append('year_level', document.getElementById('sf-year').value);
                fd.append('block', document.getElementById('sf-block').value.trim().toUpperCase());
                fd.append('academic_year', document.getElementById('sf-acad').value.trim());
                fd.append('semester', document.getElementById('sf-sem').value);
                const statusEl = document.getElementById('sf-status');
                if (statusEl) fd.append('status', statusEl.value);
                btn.disabled = true; btn.textContent = 'Saving...';
                csAjax(fd).then(json => {
                    if (json.success) { location.reload(); }
                    else {
                        csNotice(document.getElementById('sf-notice'), json.data.message, 'error');
                        btn.disabled = false; btn.textContent = 'Save Section';
                    }
                });
            });
        }

        document.querySelectorAll('.cs-del-section').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm('Delete section "' + this.dataset.name + '" and ALL its schedule entries? This cannot be undone.')) return;
                const fd = new FormData();
                fd.append('action', 'cs_delete_section');
                fd.append('nonce', nonce);
                fd.append('id', this.dataset.id);
                csAjax(fd).then(json => {
                    if (json.success) location.reload();
                    else csNotice(document.getElementById('cs-section-notice'), json.data.message, 'error');
                });
            });
        });

        // ── Bulk Import Sections ──
        document.getElementById('cs-bulk-section-btn').addEventListener('click', function () {
            csOpenModal(`
                <h2 class="cs-modal-title">Bulk Import Sections</h2>
                <p style="font-size:13px;color:#5c7ea6;margin:0 0 12px;">Paste CSV data below. Each row: <code>course_code, year_level, block, academic_year, semester</code><br>Example: <code>CHE, 3, A1, 2024-2025, First</code></p>
                <div class="cs-field"><label>CSV Data</label><textarea id="bulk-sec-csv" rows="10" placeholder="CHE, 3, A1, 2024-2025, First&#10;IT, 2, B2, 2024-2025, Second" style="font-family:monospace;font-size:12.5px;"></textarea></div>
                <div id="bulk-sec-notice"></div>
                <div class="cs-btn-group">
                    <button class="cs-btn cs-btn-primary" id="bulk-sec-save">Import</button>
                    <button class="cs-btn cs-btn-secondary" onclick="csCloseModal()">Cancel</button>
                </div>`);
            document.getElementById('bulk-sec-save').addEventListener('click', function () {
                const btn = this;
                const lines = document.getElementById('bulk-sec-csv').value.trim().split('\n').filter(l => l.trim());
                if (!lines.length) { csNotice(document.getElementById('bulk-sec-notice'), 'No data entered.', 'error'); return; }
                const rows = lines.map(l => {
                    const p = l.split(',').map(s => s.trim());
                    return { course_code: p[0]||'', year_level: p[1]||'1', block: p[2]||'', academic_year: p[3]||'', semester: p[4]||'First' };
                });
                btn.disabled = true; btn.textContent = 'Importing...';
                const fd = new FormData();
                fd.append('action', 'cs_bulk_import_sections');
                fd.append('nonce', nonce);
                fd.append('rows', JSON.stringify(rows));
                csAjax(fd).then(json => {
                    if (json.success) { csNotice(document.getElementById('bulk-sec-notice'), json.data.message, 'success'); setTimeout(() => location.reload(), 1200); }
                    else { csNotice(document.getElementById('bulk-sec-notice'), json.data.message, 'error'); btn.disabled=false; btn.textContent='Import'; }
                });
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ─────────────────────────────────────────────────────────────
// TAB: SCHEDULE ENTRY
// ─────────────────────────────────────────────────────────────

function cs_schedule_tab() {
    global $wpdb;

    $sections    = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}cs_sections WHERE status='active' ORDER BY section_name ASC"
    );
    $instructors = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}cs_instructors WHERE status='active' ORDER BY full_name ASC"
    );
    $rooms = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}cs_rooms WHERE status='active' ORDER BY room_code ASC"
    );

    // Build section → department map via cs_courses
    $courses_dept = $wpdb->get_results(
        "SELECT course_code, department FROM {$wpdb->prefix}cs_courses"
    );
    $course_dept_map = [];
    foreach ($courses_dept as $cd) {
        $course_dept_map[$cd->course_code] = $cd->department;
    }
    $section_dept_map = [];
    foreach ($sections as $s) {
        $section_dept_map[$s->id] = $course_dept_map[$s->course_code] ?? '';
    }

    $selected_section = isset($_GET['cs_sched_section']) ? intval($_GET['cs_sched_section']) : (empty($sections) ? 0 : $sections[0]->id);
    $nonce = wp_create_nonce('cs_schedule_nonce');

    $entries = [];
    if ($selected_section) {
        $entries = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cs_schedules WHERE section_id=%d ORDER BY day_group ASC, time_slot ASC",
            $selected_section
        ));
    }

    ob_start();
    ?>
    <div class="cs-panel">
        <h3>Schedule Entry</h3>
        <div class="cs-form-grid" style="align-items:flex-end;">
            <div class="cs-field">
                <label>Select Section</label>
                <select id="cs-sched-section-filter">
                    <?php if (empty($sections)): ?>
                    <option value="">No sections available</option>
                    <?php else: foreach ($sections as $s): ?>
                    <option value="<?php echo $s->id; ?>" <?php selected($selected_section, $s->id); ?>>
                        <?php echo esc_html($s->section_name); ?>
                    </option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
            <div>
                <button class="cs-btn cs-btn-secondary" id="cs-go-section">View</button>
                <button class="cs-btn cs-btn-primary" id="cs-add-entry-btn" style="margin-left:6px;">
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add Entry
                </button>
            </div>
        </div>
    </div>

    <div class="cs-panel" id="cs-entries-panel">
        <h3>Entries for Section</h3>
        <div id="cs-sched-notice"></div>
        <?php if (empty($entries)): ?>
        <p style="color:#5c7ea6;font-size:13.5px;">No schedule entries for this section yet.</p>
        <?php else: ?>
        <div class="cs-table-wrap">
        <table class="cs-table">
            <thead>
                <tr>
                    <th>Subject Code</th>
                    <th>Subject Name</th>
                    <th>Day Group</th>
                    <th>Time Slot</th>
                    <th>Instructor</th>
                    <th>Room</th>
                    <th>Type</th>
                    <th>Units</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $e): ?>
                <tr>
                    <td><strong><?php echo esc_html($e->subject_code); ?></strong></td>
                    <td><?php echo esc_html($e->subject_name ?: '—'); ?></td>
                    <td><?php
                        // Format day_group: "Mon,Wed,Fri" → "Mon, Wed, Fri"
                        // or legacy key → human label
                        $dg_raw = $e->day_group;
                        $legacy_labels = ['mon_thu'=>'Mon / Thu','tue_fri'=>'Tue / Fri','wed_sat'=>'Wed / Sat'];
                        echo esc_html($legacy_labels[$dg_raw] ?? implode(', ', array_map('trim', explode(',', $dg_raw))));
                    ?></td>
                    <td><?php
                        // Format time_slot: "07:30-09:00" → "07:30 – 09:00"
                        echo esc_html(preg_replace('/^(\d{2}:\d{2})-(\d{2}:\d{2})$/', '$1 – $2', $e->time_slot));
                    ?></td>
                    <td><?php echo esc_html($e->instructor_initials); ?> <?php if ($e->instructor_name): ?><small style="color:#5c7ea6;">(<?php echo esc_html($e->instructor_name); ?>)</small><?php endif; ?></td>
                    <td><?php echo esc_html($e->room); ?></td>
                    <td><span class="cs-badge <?php echo $e->schedule_type === 'lab' ? 'cs-badge-green' : 'cs-badge-blue'; ?>"><?php echo ucfirst($e->schedule_type); ?></span></td>
                    <td><?php echo (int)$e->units; ?></td>
                    <td>
                        <div class="cs-action-wrap">
                            <button class="cs-action-trigger" title="Actions">&#8942;</button>
                            <div class="cs-action-menu">
                                <button class="cs-btn cs-btn-secondary cs-btn-sm cs-edit-entry"
                                    data-id="<?php echo $e->id; ?>"
                                    data-section="<?php echo $e->section_id; ?>"
                                    data-code="<?php echo esc_attr($e->subject_code); ?>"
                                    data-name="<?php echo esc_attr($e->subject_name); ?>"
                                    data-initials="<?php echo esc_attr($e->instructor_initials); ?>"
                                    data-instname="<?php echo esc_attr($e->instructor_name); ?>"
                                    data-room="<?php echo esc_attr($e->room); ?>"
                                    data-dg="<?php echo esc_attr($e->day_group); ?>"
                                    data-slot="<?php echo esc_attr($e->time_slot); ?>"
                                    data-type="<?php echo esc_attr($e->schedule_type); ?>"
                                    data-units="<?php echo esc_attr($e->units); ?>">Edit</button>
                                <button class="cs-btn cs-btn-danger cs-btn-sm cs-del-entry" data-id="<?php echo $e->id; ?>">Delete</button>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <script>
    (function () {
        const nonce      = '<?php echo $nonce; ?>';
        const sections   = <?php echo json_encode(array_map(fn($s) => ['id'=>$s->id,'name'=>$s->section_name], $sections)); ?>;
        const instructors= <?php echo json_encode(array_map(fn($i) => ['id'=>$i->id,'initials'=>$i->initials,'name'=>$i->full_name,'dept'=>$i->department], $instructors)); ?>;
        const rooms      = <?php echo json_encode(array_map(fn($r) => ['id'=>$r->id,'code'=>$r->room_code], $rooms)); ?>;
        const sectionId  = <?php echo $selected_section ?: 0; ?>;
        const sectionDepts = <?php echo json_encode($section_dept_map); ?>;

        document.getElementById('cs-go-section').addEventListener('click', function () {
            const url = new URL(window.location.href);
            url.searchParams.set('cs_sched_section', document.getElementById('cs-sched-section-filter').value);
            window.location.href = url.toString() + '#cs-tab-schedule';
        });

        // ── Day / time helpers ────────────────────────────────────────────
        const ALL_DAYS = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];

        // Parse stored day_group "Mon,Wed,Fri" → Set of abbrevs
        function parseDays(str) {
            if (!str) return new Set();
            return new Set(str.split(',').map(d => d.trim()).filter(Boolean));
        }

        // Parse stored time_slot "07:30-09:00" → {start, end} (both "HH:MM")
        function parseSlot(str) {
            if (!str) return { start: '', end: '' };
            // Support both "07:30-09:00" and legacy "7:30 - 9:00"
            const m = str.match(/(\d{1,2}:\d{2})\s*[-–]\s*(\d{1,2}:\d{2})/);
            if (!m) return { start: '', end: '' };
            const pad = t => t.length === 4 ? '0' + t : t; // "7:30" → "07:30"
            return { start: pad(m[1]), end: pad(m[2]) };
        }

        function entryForm(data) {
            data = data || {};
            const secOptions = sections.map(s =>
                `<option value="${s.id}" ${(data.section||sectionId)==s.id?'selected':''}>${s.name}</option>`
            ).join('');
            // Filter instructors by the selected section's course department
            const activeSectionId = data.section || sectionId;
            const secDeptRaw = sectionDepts[activeSectionId] || '';
            const secDepts = secDeptRaw.split(',').map(d => d.trim()).filter(Boolean);
            const filteredInsts = secDepts.length
                ? instructors.filter(i => !i.dept || secDepts.some(d => d === i.dept))
                : instructors;
            const instOptions = '<option value="">-- Select Instructor --</option>' + filteredInsts.map(i =>
                `<option value="${i.initials}|${i.name}" ${data.initials===i.initials?'selected':''}>${i.name} (${i.initials})</option>`
            ).join('');
            const roomOptions = '<option value="">-- Select Room --</option>' + rooms.map(r =>
                `<option value="${r.code}">${r.code}</option>`
            ).join('');

            const activeDays = parseDays(data.dg || '');
            const dayChecks = ALL_DAYS.map(d =>
                `<label class="ef-day-label">
                    <input type="checkbox" class="ef-day-cb" value="${d}" ${activeDays.has(d)?'checked':''}>
                    <span>${d}</span>
                </label>`
            ).join('');

            const { start, end } = parseSlot(data.slot || '');

            return `
            <style>
            .ef-day-picker{display:flex;flex-wrap:wrap;gap:6px;margin-top:4px;}
            .ef-day-label{display:inline-flex;align-items:center;gap:5px;padding:5px 11px;border:1.5px solid var(--cs-border,#dde3ec);border-radius:20px;cursor:pointer;font-size:12.5px;font-weight:600;color:var(--cs-text-secondary,#374151);transition:all .15s;user-select:none;}
            .ef-day-label:hover{border-color:var(--cs-navy,#0f1e35);color:var(--cs-navy,#0f1e35);}
            .ef-day-label input{display:none;}
            .ef-day-label:has(input:checked){background:var(--cs-navy,#0f1e35);border-color:var(--cs-navy,#0f1e35);color:var(--cs-amber,#e8a838);}
            .ef-time-row{display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:8px;margin-top:4px;}
            .ef-time-sep{font-size:13px;font-weight:700;color:var(--cs-text-muted,#6b7280);text-align:center;}
            .ef-time-row input[type=time]{padding:8px 10px;border:1.5px solid var(--cs-border,#dde3ec);border-radius:7px;font-family:inherit;font-size:13.5px;color:var(--cs-text-primary,#111827);background:#fff;width:100%;}
            .ef-time-row input[type=time]:focus{outline:none;border-color:var(--cs-blue-mid,#1a4f8a);box-shadow:0 0 0 3px rgba(26,79,138,.12);}
            </style>
            <h2 class="cs-modal-title">${data.id ? 'Edit Entry' : 'Add Schedule Entry'}</h2>
            <div class="cs-form-grid">
                <div class="cs-field">
                    <label>Section *</label>
                    <select id="ef-section">${secOptions}</select>
                </div>
                <div class="cs-field" style="grid-column:span 1;">
                    <label>Subject Code * <small style="color:#5c7ea6;">(add " L" for lab)</small></label>
                    <input type="text" id="ef-code" value="${data.code||''}" placeholder="e.g. MATH 89 or CHEM 86 L">
                </div>
                <div class="cs-field" style="grid-column:1/-1;">
                    <label>Day Group *</label>
                    <div class="ef-day-picker">${dayChecks}</div>
                </div>
                <div class="cs-field" style="grid-column:1/-1;">
                    <label>Time *</label>
                    <div class="ef-time-row">
                        <input type="time" id="ef-start" value="${start}" step="300">
                        <span class="ef-time-sep">to</span>
                        <input type="time" id="ef-end"   value="${end}"   step="300">
                    </div>
                </div>
                <div class="cs-field">
                    <label>Subject Name</label>
                    <input type="text" id="ef-name" value="${data.name||''}" placeholder="Full subject name (optional)">
                </div>
                <div class="cs-field">
                    <label>Instructor</label>
                    <select id="ef-inst">${instOptions}</select>
                </div>
                <div class="cs-field">
                    <label>Instructor Initials</label>
                    <input type="text" id="ef-initials" value="${data.initials||''}" placeholder="Auto-filled or custom">
                </div>
                <div class="cs-field">
                    <label>Room (from list)</label>
                    <select id="ef-room-select">${roomOptions}</select>
                </div>
                <div class="cs-field">
                    <label>Room Override <small style="color:#5c7ea6;">(overrides list)</small></label>
                    <input type="text" id="ef-room" value="${data.room||''}" placeholder="Custom room code">
                </div>
                <div class="cs-field">
                    <label>Schedule Type</label>
                    <select id="ef-type">
                        <option value="lecture" ${data.type==='lecture'?'selected':''}>Lecture</option>
                        <option value="lab"     ${data.type==='lab'?'selected':''}>Lab</option>
                        <option value="both"    ${data.type==='both'?'selected':''}>Both</option>
                    </select>
                </div>
                <div class="cs-field">
                    <label>Units</label>
                    <input type="number" id="ef-units" value="${data.units||3}" min="1" max="9">
                </div>
            </div>
            <div id="ef-notice"></div>
            <div class="cs-btn-group">
                <button class="cs-btn cs-btn-primary" id="ef-save">Save Entry</button>
                <button class="cs-btn cs-btn-secondary" onclick="csCloseModal()">Cancel</button>
            </div>
            <input type="hidden" id="ef-id" value="${data.id||0}">`;
        }

        function buildInstOptions(sectionId, selectedInitials) {
            const deptRaw = sectionDepts[sectionId] || '';
            const depts = deptRaw.split(',').map(d => d.trim()).filter(Boolean);
            const filtered = depts.length
                ? instructors.filter(i => !i.dept || depts.some(d => d === i.dept))
                : instructors;
            return '<option value="">-- Select Instructor --</option>' + filtered.map(i =>
                `<option value="${i.initials}|${i.name}" ${(selectedInitials||'')===i.initials?'selected':''}>${i.name} (${i.initials})</option>`
            ).join('');
        }

        function bindEntryForm() {
            // Re-filter instructor list when section changes
            document.getElementById('ef-section').addEventListener('change', function () {
                const instEl = document.getElementById('ef-inst');
                instEl.innerHTML = buildInstOptions(this.value, '');
                document.getElementById('ef-initials').value = '';
            });

            // Auto-fill initials from instructor dropdown
            document.getElementById('ef-inst').addEventListener('change', function () {
                const parts = this.value.split('|');
                document.getElementById('ef-initials').value = parts[0] || '';
            });
            document.getElementById('ef-save').addEventListener('click', function () {
                const btn = this;

                // Collect checked days → "Mon,Wed,Fri"
                const checkedDays = [...document.querySelectorAll('.ef-day-cb:checked')]
                    .map(cb => cb.value);
                if (!checkedDays.length) {
                    csNotice(document.getElementById('ef-notice'), 'Please select at least one day.', 'error');
                    return;
                }
                const dayGroup = checkedDays.join(',');

                // Compose time_slot → "07:30-09:00"
                const startVal = document.getElementById('ef-start').value;
                const endVal   = document.getElementById('ef-end').value;
                if (!startVal || !endVal) {
                    csNotice(document.getElementById('ef-notice'), 'Please set both Start Time and End Time.', 'error');
                    return;
                }
                if (startVal >= endVal) {
                    csNotice(document.getElementById('ef-notice'), 'End Time must be after Start Time.', 'error');
                    return;
                }
                const timeSlot = startVal + '-' + endVal;  // e.g. "07:30-09:00"

                const roomOverride = document.getElementById('ef-room').value.trim();
                const roomSelect   = document.getElementById('ef-room-select').value;
                const roomFinal    = roomOverride || roomSelect;
                const fd = new FormData();
                fd.append('action', 'cs_save_schedule');
                fd.append('nonce', nonce);
                fd.append('id', document.getElementById('ef-id').value);
                fd.append('section_id', document.getElementById('ef-section').value);
                fd.append('day_group', dayGroup);
                fd.append('time_slot', timeSlot);
                fd.append('subject_code', document.getElementById('ef-code').value.trim());
                fd.append('subject_name', document.getElementById('ef-name').value.trim());
                fd.append('instructor_initials', document.getElementById('ef-initials').value.trim().toUpperCase());
                const instParts = document.getElementById('ef-inst').value.split('|');
                fd.append('instructor_name', instParts[1] || '');
                fd.append('room', roomFinal.toUpperCase());
                fd.append('schedule_type', document.getElementById('ef-type').value);
                fd.append('units', document.getElementById('ef-units').value);
                btn.disabled = true; btn.textContent = 'Saving...';
                csAjax(fd).then(json => {
                    if (json.success) location.reload();
                    else {
                        csNotice(document.getElementById('ef-notice'), json.data.message, 'error');
                        btn.disabled = false; btn.textContent = 'Save Entry';
                    }
                });
            });
        }

        document.getElementById('cs-add-entry-btn').addEventListener('click', function () {
            csOpenModal(entryForm());
            bindEntryForm();
        });

        document.querySelectorAll('.cs-edit-entry').forEach(function (btn) {
            btn.addEventListener('click', function () {
                csOpenModal(entryForm({
                    id: this.dataset.id, section: this.dataset.section,
                    code: this.dataset.code, name: this.dataset.name,
                    initials: this.dataset.initials, instname: this.dataset.instname,
                    room: this.dataset.room, dg: this.dataset.dg,
                    slot: this.dataset.slot, type: this.dataset.type,
                    units: this.dataset.units
                }));
                bindEntryForm();
            });
        });

        document.querySelectorAll('.cs-del-entry').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm('Delete this schedule entry?')) return;
                const fd = new FormData();
                fd.append('action', 'cs_delete_schedule');
                fd.append('nonce', nonce);
                fd.append('id', this.dataset.id);
                csAjax(fd).then(json => {
                    if (json.success) location.reload();
                    else csNotice(document.getElementById('cs-sched-notice'), json.data.message, 'error');
                });
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ─────────────────────────────────────────────────────────────
// TAB: INSTRUCTORS
// ─────────────────────────────────────────────────────────────

function cs_instructors_tab() {
    global $wpdb;

    $instructors = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}cs_instructors ORDER BY full_name ASC"
    );

    $departments = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}cs_departments WHERE status='active' ORDER BY dept_name ASC"
    );

    $courses_data = $wpdb->get_results(
        "SELECT course_code, course_title, department FROM {$wpdb->prefix}cs_courses WHERE status='active' ORDER BY course_code ASC"
    );

    $nonce = wp_create_nonce('cs_instructors_nonce');

    ob_start();
    ?>
    <div class="cs-panel">
        <h3>Instructors</h3>
        <button class="cs-btn cs-btn-primary" id="cs-add-inst-btn">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Instructor
        </button>
        <button class="cs-btn cs-btn-secondary" id="cs-bulk-inst-btn" style="margin-left:8px;">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            Bulk Import
        </button>
        <div id="cs-inst-notice"></div>
    </div>

    <div class="cs-panel">
        <?php if (empty($instructors)): ?>
        <p style="color:#5c7ea6;font-size:13.5px;">No instructors yet.</p>
        <?php else: ?>
        <div class="cs-table-wrap">
        <table class="cs-table">
            <thead>
                <tr><th>Initials</th><th>Full Name</th><th>Department</th><th>Subjects Handled</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($instructors as $inst): ?>
                <tr>
                    <td><strong><?php echo esc_html($inst->initials); ?></strong></td>
                    <td><?php echo esc_html($inst->full_name); ?></td>
                    <td><?php echo esc_html($inst->department ?: '—'); ?></td>
                    <td><?php echo esc_html($inst->subjects_handled ?: '—'); ?></td>
                    <td><span class="cs-badge <?php echo $inst->status === 'active' ? 'cs-badge-green' : 'cs-badge-gray'; ?>"><?php echo ucfirst($inst->status); ?></span></td>
                    <td>
                        <div class="cs-action-wrap">
                            <button class="cs-action-trigger" title="Actions">&#8942;</button>
                            <div class="cs-action-menu">
                                <button class="cs-btn cs-btn-secondary cs-btn-sm cs-edit-inst"
                                    data-id="<?php echo $inst->id; ?>"
                                    data-initials="<?php echo esc_attr($inst->initials); ?>"
                                    data-name="<?php echo esc_attr($inst->full_name); ?>"
                                    data-dept="<?php echo esc_attr($inst->department); ?>"
                                    data-subjects="<?php echo esc_attr($inst->subjects_handled); ?>"
                                    data-status="<?php echo esc_attr($inst->status); ?>">Edit</button>
                                <button class="cs-btn cs-btn-danger cs-btn-sm cs-del-inst" data-id="<?php echo $inst->id; ?>">Delete</button>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <script>
    (function () {
        const nonce = '<?php echo $nonce; ?>';
        const deptOptions = <?php echo json_encode(array_map(function($d){ return ['id'=>$d->id,'name'=>$d->dept_name,'code'=>$d->dept_code]; }, $departments)); ?>;
        const coursesData = <?php echo json_encode(array_map(function($c){ return ['code'=>$c->course_code,'title'=>$c->course_title,'dept'=>$c->department]; }, $courses_data)); ?>;

        function buildSubjectCheckboxes(deptName, selected) {
            const sel = (selected||'').split(',').map(s => s.trim()).filter(Boolean);
            if (!deptName) {
                return '<div class="cs-subj-empty">Select a department to see available courses.</div>';
            }
            const filtered = coursesData.filter(c => c.dept.split(',').map(d => d.trim()).includes(deptName));
            if (!filtered.length) {
                return '<div class="cs-subj-warn">No courses found for this department.</div>';
            }
            let items = '';
            filtered.forEach(c => {
                const checked = sel.includes(c.code) ? 'checked' : '';
                items += '<label class="cs-subj-item">'
                    + '<input type="checkbox" class="if-subj-check" value="' + c.code.replace(/"/g,'&quot;') + '" ' + checked + '>'
                    + '<strong>' + c.code + '</strong>'
                    + (c.title ? '<em>' + c.title + '</em>' : '')
                    + '</label>';
            });
            return '<div class="cs-subj-list">'
                + '<div class="cs-subj-list-header">' + filtered.length + ' course' + (filtered.length !== 1 ? 's' : '') + ' — select all that apply</div>'
                + '<div class="cs-subj-list-body">' + items + '</div>'
                + '</div>';
        }

        function instForm(data) {
            data = data || {};
            const deptSelect = deptOptions.length
                ? `<select id="if-dept">
                    <option value="">— Select Department —</option>
                    ${deptOptions.map(d=>`<option value="${d.name.replace(/"/g,'&quot;')}" ${data.dept===d.name?'selected':''}>${d.name}${d.code?' ('+d.code+')':''}</option>`).join('')}
                   </select>`
                : `<input type="text" id="if-dept" value="${data.dept||''}" placeholder="No departments added yet">`;
            const statusField = data.id
                ? `<div class="cs-field">
                    <label>Status</label>
                    <select id="if-status">
                        <option value="active" ${data.status==='active'?'selected':''}>Active</option>
                        <option value="inactive" ${data.status==='inactive'?'selected':''}>Inactive</option>
                    </select>
                   </div>`
                : '';
            return `
            <h2 class="cs-modal-title">${data.id ? 'Edit Instructor' : 'Add Instructor'}</h2>

            <div style="display:grid;grid-template-columns:150px 1fr;gap:16px;margin-bottom:18px;">
                <div class="cs-field">
                    <label>Initials * <small style="color:#5c7ea6;">(timetable)</small></label>
                    <input type="text" id="if-initials" value="${data.initials||''}" placeholder="e.g. EORTIZ" maxlength="30">
                </div>
                <div class="cs-field">
                    <label>Full Name *</label>
                    <input type="text" id="if-name" value="${data.name||''}" placeholder="Full legal name">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:${data.id ? '1fr 150px' : '1fr'};gap:16px;margin-bottom:18px;">
                <div class="cs-field">
                    <label>Department</label>
                    ${deptSelect}
                </div>
                ${statusField}
            </div>

            <div class="cs-field" style="margin-bottom:4px;">
                <label>Subjects Handled <small style="color:#5c7ea6;">(courses from selected department)</small></label>
                <div id="if-subjects-wrap" style="margin-top:6px;">${buildSubjectCheckboxes(data.dept||'', data.subjects||'')}</div>
            </div>

            <div id="if-notice"></div>
            <div class="cs-btn-group">
                <button class="cs-btn cs-btn-primary" id="if-save">Save Instructor</button>
                <button class="cs-btn cs-btn-secondary" onclick="csCloseModal()">Cancel</button>
            </div>
            <input type="hidden" id="if-id" value="${data.id||0}">`;
        }

        function bindInstForm() {
            // Re-populate subject checkboxes when department changes
            const deptEl = document.getElementById('if-dept');
            if (deptEl && deptEl.tagName === 'SELECT') {
                deptEl.addEventListener('change', function () {
                    document.getElementById('if-subjects-wrap').innerHTML = buildSubjectCheckboxes(this.value, '');
                });
            }
            document.getElementById('if-save').addEventListener('click', function () {
                const btn = this;
                const fd = new FormData();
                fd.append('action', 'cs_save_instructor');
                fd.append('nonce', nonce);
                fd.append('id', document.getElementById('if-id').value);
                fd.append('initials', document.getElementById('if-initials').value.trim().toUpperCase());
                fd.append('full_name', document.getElementById('if-name').value.trim());
                fd.append('department', document.getElementById('if-dept').value.trim());
                const subjChecks = document.querySelectorAll('.if-subj-check:checked');
                fd.append('subjects_handled', Array.from(subjChecks).map(c => c.value).join(', '));
                const statusEl = document.getElementById('if-status');
                if (statusEl) fd.append('status', statusEl.value);
                btn.disabled = true; btn.textContent = 'Saving...';
                csAjax(fd).then(json => {
                    if (json.success) location.reload();
                    else { csNotice(document.getElementById('if-notice'), json.data.message, 'error'); btn.disabled=false; btn.textContent='Save Instructor'; }
                });
            });
        }

        document.getElementById('cs-add-inst-btn').addEventListener('click', function () {
            csOpenModal(instForm()); bindInstForm();
        });

        document.querySelectorAll('.cs-edit-inst').forEach(function (btn) {
            btn.addEventListener('click', function () {
                csOpenModal(instForm({ id: this.dataset.id, initials: this.dataset.initials, name: this.dataset.name, dept: this.dataset.dept, subjects: this.dataset.subjects, status: this.dataset.status }));
                bindInstForm();
            });
        });

        document.querySelectorAll('.cs-del-inst').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm('Delete this instructor?')) return;
                const fd = new FormData();
                fd.append('action', 'cs_delete_instructor');
                fd.append('nonce', nonce);
                fd.append('id', this.dataset.id);
                csAjax(fd).then(json => {
                    if (json.success) location.reload();
                    else csNotice(document.getElementById('cs-inst-notice'), json.data.message, 'error');
                });
            });
        });

        // ── Bulk Import Instructors ──
        document.getElementById('cs-bulk-inst-btn').addEventListener('click', function () {
            csOpenModal(`
                <h2 class="cs-modal-title">Bulk Import Instructors</h2>
                <p style="font-size:13px;color:#5c7ea6;margin:0 0 12px;">Paste CSV data below. Each row: <code>initials, full_name, department, subjects_handled</code><br>Example: <code>EORTIZ, Eduardo Ortiz, College of Engineering, Math 101 | Physics 201</code></p>
                <div class="cs-field"><label>CSV Data</label><textarea id="bulk-inst-csv" rows="10" placeholder="EORTIZ, Eduardo Ortiz, College of Engineering&#10;JSMITH, Jane Smith, College of Arts" style="font-family:monospace;font-size:12.5px;"></textarea></div>
                <div id="bulk-inst-notice"></div>
                <div class="cs-btn-group">
                    <button class="cs-btn cs-btn-primary" id="bulk-inst-save">Import</button>
                    <button class="cs-btn cs-btn-secondary" onclick="csCloseModal()">Cancel</button>
                </div>`);
            document.getElementById('bulk-inst-save').addEventListener('click', function () {
                const btn = this;
                const lines = document.getElementById('bulk-inst-csv').value.trim().split('\n').filter(l => l.trim());
                if (!lines.length) { csNotice(document.getElementById('bulk-inst-notice'), 'No data entered.', 'error'); return; }
                const rows = lines.map(l => {
                    const p = l.split(',').map(s => s.trim());
                    return { initials: p[0]||'', full_name: p[1]||'', department: p[2]||'', subjects_handled: p.slice(3).join(',').trim() };
                });
                btn.disabled = true; btn.textContent = 'Importing...';
                const fd = new FormData();
                fd.append('action', 'cs_bulk_import_instructors');
                fd.append('nonce', nonce);
                fd.append('rows', JSON.stringify(rows));
                csAjax(fd).then(json => {
                    if (json.success) { csNotice(document.getElementById('bulk-inst-notice'), json.data.message, 'success'); setTimeout(() => location.reload(), 1200); }
                    else { csNotice(document.getElementById('bulk-inst-notice'), json.data.message, 'error'); btn.disabled=false; btn.textContent='Import'; }
                });
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ─────────────────────────────────────────────────────────────
// TAB: ROOMS
// ─────────────────────────────────────────────────────────────

function cs_rooms_tab() {
    global $wpdb;

    $rooms = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}cs_rooms ORDER BY room_code ASC"
    );

    $nonce = wp_create_nonce('cs_rooms_nonce');

    ob_start();
    ?>
    <div class="cs-panel">
        <h3>Rooms</h3>
        <button class="cs-btn cs-btn-primary" id="cs-add-room-btn">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Room
        </button>
        <button class="cs-btn cs-btn-secondary" id="cs-bulk-room-btn" style="margin-left:8px;">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            Bulk Import
        </button>
        <div id="cs-room-notice"></div>
    </div>

    <div class="cs-panel">
        <?php if (empty($rooms)): ?>
        <p style="color:#5c7ea6;font-size:13.5px;">No rooms yet.</p>
        <?php else: ?>
        <div class="cs-table-wrap">
        <table class="cs-table">
            <thead>
                <tr><th>Room Code</th><th>Building</th><th>Capacity</th><th>Type</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($rooms as $r): ?>
                <tr>
                    <td><strong><?php echo esc_html($r->room_code); ?></strong></td>
                    <td><?php echo esc_html($r->building ?: '—'); ?></td>
                    <td><?php echo (int)$r->capacity; ?></td>
                    <td><span class="cs-badge cs-badge-blue"><?php echo ucfirst($r->room_type); ?></span></td>
                    <td><span class="cs-badge <?php echo $r->status === 'active' ? 'cs-badge-green' : 'cs-badge-gray'; ?>"><?php echo ucfirst($r->status); ?></span></td>
                    <td>
                        <div class="cs-action-wrap">
                            <button class="cs-action-trigger" title="Actions">&#8942;</button>
                            <div class="cs-action-menu">
                                <button class="cs-btn cs-btn-secondary cs-btn-sm cs-edit-room"
                                    data-id="<?php echo $r->id; ?>"
                                    data-code="<?php echo esc_attr($r->room_code); ?>"
                                    data-building="<?php echo esc_attr($r->building); ?>"
                                    data-capacity="<?php echo esc_attr($r->capacity); ?>"
                                    data-type="<?php echo esc_attr($r->room_type); ?>"
                                    data-status="<?php echo esc_attr($r->status); ?>">Edit</button>
                                <button class="cs-btn cs-btn-danger cs-btn-sm cs-del-room" data-id="<?php echo $r->id; ?>">Delete</button>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <script>
    (function () {
        const nonce = '<?php echo $nonce; ?>';

        function roomForm(data) {
            data = data || {};
            return `
            <h2 class="cs-modal-title">${data.id ? 'Edit Room' : 'Add Room'}</h2>
            <div class="cs-form-grid">
                <div class="cs-field">
                    <label>Room Code * <small style="color:#5c7ea6;">(shown on timetable)</small></label>
                    <input type="text" id="rf-code" value="${data.code||''}" placeholder="e.g. E207" maxlength="30">
                </div>
                <div class="cs-field">
                    <label>Building</label>
                    <input type="text" id="rf-building" value="${data.building||''}" placeholder="Optional">
                </div>
                <div class="cs-field">
                    <label>Capacity</label>
                    <input type="number" id="rf-capacity" value="${data.capacity||40}" min="1" max="999">
                </div>
                <div class="cs-field">
                    <label>Room Type</label>
                    <select id="rf-type">
                        <option value="lecture" ${data.type==='lecture'?'selected':''}>Lecture</option>
                        <option value="lab"     ${data.type==='lab'?'selected':''}>Lab</option>
                        <option value="both"    ${data.type==='both'?'selected':''}>Both</option>
                    </select>
                </div>
                ${data.id ? `<div class="cs-field">
                    <label>Status</label>
                    <select id="rf-status">
                        <option value="active" ${data.status==='active'?'selected':''}>Active</option>
                        <option value="inactive" ${data.status==='inactive'?'selected':''}>Inactive</option>
                    </select>
                </div>` : ''}
            </div>
            <div id="rf-notice"></div>
            <div class="cs-btn-group">
                <button class="cs-btn cs-btn-primary" id="rf-save">Save Room</button>
                <button class="cs-btn cs-btn-secondary" onclick="csCloseModal()">Cancel</button>
            </div>
            <input type="hidden" id="rf-id" value="${data.id||0}">`;
        }

        function bindRoomForm() {
            document.getElementById('rf-save').addEventListener('click', function () {
                const btn = this;
                const fd = new FormData();
                fd.append('action', 'cs_save_room');
                fd.append('nonce', nonce);
                fd.append('id', document.getElementById('rf-id').value);
                fd.append('room_code', document.getElementById('rf-code').value.trim().toUpperCase());
                fd.append('building', document.getElementById('rf-building').value.trim());
                fd.append('capacity', document.getElementById('rf-capacity').value);
                fd.append('room_type', document.getElementById('rf-type').value);
                const statusEl = document.getElementById('rf-status');
                if (statusEl) fd.append('status', statusEl.value);
                btn.disabled = true; btn.textContent = 'Saving...';
                csAjax(fd).then(json => {
                    if (json.success) location.reload();
                    else { csNotice(document.getElementById('rf-notice'), json.data.message, 'error'); btn.disabled=false; btn.textContent='Save Room'; }
                });
            });
        }

        document.getElementById('cs-add-room-btn').addEventListener('click', function () {
            csOpenModal(roomForm()); bindRoomForm();
        });

        document.querySelectorAll('.cs-edit-room').forEach(function (btn) {
            btn.addEventListener('click', function () {
                csOpenModal(roomForm({ id: this.dataset.id, code: this.dataset.code, building: this.dataset.building, capacity: this.dataset.capacity, type: this.dataset.type, status: this.dataset.status }));
                bindRoomForm();
            });
        });

        document.querySelectorAll('.cs-del-room').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm('Delete this room?')) return;
                const fd = new FormData();
                fd.append('action', 'cs_delete_room');
                fd.append('nonce', nonce);
                fd.append('id', this.dataset.id);
                csAjax(fd).then(json => {
                    if (json.success) location.reload();
                    else csNotice(document.getElementById('cs-room-notice'), json.data.message, 'error');
                });
            });
        });

        // ── Bulk Import Rooms ──
        document.getElementById('cs-bulk-room-btn').addEventListener('click', function () {
            csOpenModal(`
                <h2 class="cs-modal-title">Bulk Import Rooms</h2>
                <p style="font-size:13px;color:#5c7ea6;margin:0 0 12px;">Paste CSV data below. Each row: <code>room_code, building, capacity, room_type</code><br>Room type: <code>lecture</code>, <code>lab</code>, or <code>both</code><br>Example: <code>E207, Engineering Building, 40, lecture</code></p>
                <div class="cs-field"><label>CSV Data</label><textarea id="bulk-room-csv" rows="10" placeholder="E207, Engineering Building, 40, lecture&#10;LAB1, Science Hall, 30, lab" style="font-family:monospace;font-size:12.5px;"></textarea></div>
                <div id="bulk-room-notice"></div>
                <div class="cs-btn-group">
                    <button class="cs-btn cs-btn-primary" id="bulk-room-save">Import</button>
                    <button class="cs-btn cs-btn-secondary" onclick="csCloseModal()">Cancel</button>
                </div>`);
            document.getElementById('bulk-room-save').addEventListener('click', function () {
                const btn = this;
                const lines = document.getElementById('bulk-room-csv').value.trim().split('\n').filter(l => l.trim());
                if (!lines.length) { csNotice(document.getElementById('bulk-room-notice'), 'No data entered.', 'error'); return; }
                const rows = lines.map(l => {
                    const p = l.split(',').map(s => s.trim());
                    return { room_code: p[0]||'', building: p[1]||'', capacity: p[2]||'40', room_type: p[3]||'lecture' };
                });
                btn.disabled = true; btn.textContent = 'Importing...';
                const fd = new FormData();
                fd.append('action', 'cs_bulk_import_rooms');
                fd.append('nonce', nonce);
                fd.append('rows', JSON.stringify(rows));
                csAjax(fd).then(json => {
                    if (json.success) { csNotice(document.getElementById('bulk-room-notice'), json.data.message, 'success'); setTimeout(() => location.reload(), 1200); }
                    else { csNotice(document.getElementById('bulk-room-notice'), json.data.message, 'error'); btn.disabled=false; btn.textContent='Import'; }
                });
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ─────────────────────────────────────────────────────────────
// AJAX: SECTIONS
// ─────────────────────────────────────────────────────────────

function bntm_ajax_cs_save_section() {
    check_ajax_referer('cs_sections_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $id           = intval($_POST['id']);
    $course_code  = strtoupper(sanitize_text_field($_POST['course_code']));
    $year_level   = intval($_POST['year_level']);
    $block        = strtoupper(sanitize_text_field($_POST['block']));
    $academic_year= sanitize_text_field($_POST['academic_year'] ?? '');
    $semester     = sanitize_text_field($_POST['semester'] ?? 'First');
    $status       = sanitize_text_field($_POST['status'] ?? 'active');

    if (empty($course_code) || empty($block) || $year_level < 1) {
        wp_send_json_error(['message' => 'Course code, year level, and block are required.']);
    }

    $section_name = $course_code . $year_level . ' ' . $block;

    $table = $wpdb->prefix . 'cs_sections';

    if ($id > 0) {
        $result = $wpdb->update($table,
            compact('section_name','course_code','year_level','block','academic_year','semester','status'),
            ['id' => $id],
            ['%s','%s','%d','%s','%s','%s','%s'], ['%d']
        );
        if ($result !== false) wp_send_json_success(['message' => 'Section updated.']);
        else wp_send_json_error(['message' => 'Failed to update section.']);
    } else {
        $rand_id = function_exists('bntm_rand_id') ? bntm_rand_id() : wp_generate_password(12, false);
        $result  = $wpdb->insert($table,
            ['rand_id'=>$rand_id,'section_name'=>$section_name,
             'course_code'=>$course_code,'year_level'=>$year_level,'block'=>$block,
             'academic_year'=>$academic_year,'semester'=>$semester,'status'=>'active'],
            ['%s','%s','%s','%d','%s','%s','%s','%s']
        );
        if ($result) wp_send_json_success(['message' => 'Section created.', 'id' => $wpdb->insert_id]);
        else wp_send_json_error(['message' => 'Failed to create section.']);
    }
}

function bntm_ajax_cs_delete_section() {
    check_ajax_referer('cs_sections_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $id          = intval($_POST['id']);

    // Cascade delete schedule entries
    $wpdb->delete($wpdb->prefix . 'cs_schedules', ['section_id' => $id], ['%d']);
    $result = $wpdb->delete($wpdb->prefix . 'cs_sections', ['id' => $id], ['%d']);

    if ($result) wp_send_json_success(['message' => 'Section deleted.']);
    else wp_send_json_error(['message' => 'Failed to delete section.']);
}

// ─────────────────────────────────────────────────────────────
// AJAX: SCHEDULES
// ─────────────────────────────────────────────────────────────

function bntm_ajax_cs_save_schedule() {
    check_ajax_referer('cs_schedule_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $id                 = intval($_POST['id']);
    $section_id         = intval($_POST['section_id']);
    $subject_code       = sanitize_text_field($_POST['subject_code']);
    $subject_name       = sanitize_text_field($_POST['subject_name'] ?? '');
    $instructor_initials= strtoupper(sanitize_text_field($_POST['instructor_initials'] ?? ''));
    $instructor_name    = sanitize_text_field($_POST['instructor_name'] ?? '');
    $room               = strtoupper(sanitize_text_field($_POST['room'] ?? ''));
    $schedule_type      = sanitize_text_field($_POST['schedule_type'] ?? 'lecture');
    $units              = intval($_POST['units'] ?? 3);

    // ── Day group: comma-separated abbreviations e.g. "Mon,Wed,Fri" ──────
    $valid_days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
    $raw_days   = array_map('trim', explode(',', sanitize_text_field($_POST['day_group'] ?? '')));
    $clean_days = array_values(array_filter($raw_days, fn($d) => in_array($d, $valid_days)));
    $day_group  = implode(',', $clean_days);

    // ── Time slot: "HH:MM-HH:MM" 24-hour ─────────────────────────────────
    $raw_slot  = sanitize_text_field($_POST['time_slot'] ?? '');
    // Accept both "07:30-09:00" (new) and "7:30 - 9:00" (legacy text)
    if (preg_match('/^(\d{1,2}:\d{2})\s*[-–]\s*(\d{1,2}:\d{2})$/', $raw_slot, $m)) {
        $pad      = fn($t) => strlen($t) === 4 ? '0'.$t : $t;
        $time_slot = $pad($m[1]) . '-' . $pad($m[2]);
    } else {
        $time_slot = $raw_slot; // store as-is if format is unrecognised
    }

    if (!$section_id || empty($subject_code) || empty($day_group) || empty($time_slot)) {
        wp_send_json_error(['message' => 'Section, subject code, day group, and time slot are required.']);
    }

    $table = $wpdb->prefix . 'cs_schedules';

    // Conflict check: any existing entry for this section whose day_group shares
    // at least one day with the new entry AND has the same time_slot.
    $existing = $wpdb->get_results($wpdb->prepare(
        "SELECT id, day_group FROM {$table} WHERE section_id=%d AND time_slot=%s AND id != %d",
        $section_id, $time_slot, $id
    ));
    $new_days = $clean_days;
    foreach ($existing as $ex) {
        $ex_days = array_map('trim', explode(',', $ex->day_group));
        $overlap = array_intersect($new_days, $ex_days);
        if (!empty($overlap)) {
            wp_send_json_error(['message' =>
                'Conflict: A subject is already scheduled on ' .
                implode(', ', $overlap) . ' at ' . $time_slot . '.'
            ]);
        }
    }

    $data   = compact('section_id','subject_code','subject_name',
                      'instructor_initials','instructor_name','room','day_group',
                      'time_slot','schedule_type','units');
    $format = ['%d','%s','%s','%s','%s','%s','%s','%s','%s','%d'];

    if ($id > 0) {
        $result = $wpdb->update($table, $data, ['id'=>$id], $format, ['%d']);
        if ($result !== false) wp_send_json_success(['message' => 'Entry updated.']);
        else wp_send_json_error(['message' => 'Failed to update entry.']);
    } else {
        $rand_id = function_exists('bntm_rand_id') ? bntm_rand_id() : wp_generate_password(12, false);
        $data['rand_id'] = $rand_id;
        $result = $wpdb->insert($table, $data, array_merge(['%s'], $format));
        if ($result) wp_send_json_success(['message' => 'Entry added.', 'id' => $wpdb->insert_id]);
        else wp_send_json_error(['message' => 'Failed to add entry.']);
    }
}

function bntm_ajax_cs_delete_schedule() {
    check_ajax_referer('cs_schedule_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $id          = intval($_POST['id']);
    $result      = $wpdb->delete($wpdb->prefix . 'cs_schedules', ['id'=>$id], ['%d']);

    if ($result) wp_send_json_success(['message' => 'Entry deleted.']);
    else wp_send_json_error(['message' => 'Failed to delete entry.']);
}

function bntm_ajax_cs_get_section_data() {
    check_ajax_referer('cs_schedule_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $section_id  = intval($_POST['section_id']);

    $entries = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}cs_schedules WHERE section_id=%d",
        $section_id
    ));

    wp_send_json_success(['entries' => $entries]);
}

function bntm_ajax_cs_bulk_import_section() {
    check_ajax_referer('cs_schedule_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $section_id  = intval($_POST['section_id']);
    $entries     = json_decode(stripslashes($_POST['entries']), true);

    if (empty($entries) || !is_array($entries)) {
        wp_send_json_error(['message' => 'No entries provided.']);
    }

    $table   = $wpdb->prefix . 'cs_schedules';
    $imported = 0;

    foreach ($entries as $e) {
        $dg   = sanitize_text_field($e['day_group'] ?? '');
        $slot = sanitize_text_field($e['time_slot'] ?? '');
        if (empty($dg) || empty($slot)) continue;

        // Skip duplicates
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE section_id=%d AND day_group=%s AND time_slot=%s",
            $section_id, $dg, $slot
        ));
        if ($exists) continue;

        $rand_id = function_exists('bntm_rand_id') ? bntm_rand_id() : wp_generate_password(12, false);
        $result  = $wpdb->insert($table, [
            'rand_id'             => $rand_id,
            'section_id'          => $section_id,
            'subject_code'        => sanitize_text_field($e['subject_code'] ?? ''),
            'subject_name'        => sanitize_text_field($e['subject_name'] ?? ''),
            'instructor_initials' => strtoupper(sanitize_text_field($e['instructor_initials'] ?? '')),
            'instructor_name'     => sanitize_text_field($e['instructor_name'] ?? ''),
            'room'                => strtoupper(sanitize_text_field($e['room'] ?? '')),
            'day_group'           => $dg,
            'time_slot'           => $slot,
            'schedule_type'       => sanitize_text_field($e['schedule_type'] ?? 'lecture'),
            'units'               => intval($e['units'] ?? 3),
        ], ['%s','%d','%s','%s','%s','%s','%s','%s','%s','%s','%d']);

        if ($result) $imported++;
    }

    wp_send_json_success(['message' => "Imported {$imported} entr" . ($imported === 1 ? 'y' : 'ies') . '.',"imported" => $imported]);
}

// ─────────────────────────────────────────────────────────────
// AJAX: INSTRUCTORS
// ─────────────────────────────────────────────────────────────

function bntm_ajax_cs_save_instructor() {
    check_ajax_referer('cs_instructors_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $id               = intval($_POST['id']);
    $initials         = strtoupper(sanitize_text_field($_POST['initials']));
    $full_name        = sanitize_text_field($_POST['full_name']);
    $department       = sanitize_text_field($_POST['department'] ?? '');
    $subjects_handled = sanitize_text_field($_POST['subjects_handled'] ?? '');
    $status           = sanitize_text_field($_POST['status'] ?? 'active');

    if (empty($initials) || empty($full_name)) {
        wp_send_json_error(['message' => 'Initials and full name are required.']);
    }

    $table = $wpdb->prefix . 'cs_instructors';

    if ($id > 0) {
        $result = $wpdb->update($table,
            compact('initials','full_name','department','subjects_handled','status'),
            ['id'=>$id],
            ['%s','%s','%s','%s','%s'], ['%d']
        );
        if ($result !== false) wp_send_json_success(['message' => 'Instructor updated.']);
        else wp_send_json_error(['message' => 'Failed to update.']);
    } else {
        $rand_id = function_exists('bntm_rand_id') ? bntm_rand_id() : wp_generate_password(12, false);
        $result  = $wpdb->insert($table,
            ['rand_id'=>$rand_id,'initials'=>$initials,
             'full_name'=>$full_name,'department'=>$department,'subjects_handled'=>$subjects_handled,'status'=>'active'],
            ['%s','%s','%s','%s','%s','%s']
        );
        if ($result) wp_send_json_success(['message' => 'Instructor added.']);
        else wp_send_json_error(['message' => 'Failed to add instructor.']);
    }
}

function bntm_ajax_cs_delete_instructor() {
    check_ajax_referer('cs_instructors_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $id          = intval($_POST['id']);
    $result      = $wpdb->delete($wpdb->prefix . 'cs_instructors', ['id'=>$id], ['%d']);

    if ($result) wp_send_json_success(['message' => 'Instructor deleted.']);
    else wp_send_json_error(['message' => 'Failed to delete.']);
}

// ─────────────────────────────────────────────────────────────
// AJAX: ROOMS
// ─────────────────────────────────────────────────────────────

function bntm_ajax_cs_save_room() {
    check_ajax_referer('cs_rooms_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $id          = intval($_POST['id']);
    $room_code   = strtoupper(sanitize_text_field($_POST['room_code']));
    $building    = sanitize_text_field($_POST['building'] ?? '');
    $capacity    = intval($_POST['capacity'] ?? 40);
    $room_type   = sanitize_text_field($_POST['room_type'] ?? 'lecture');
    $status      = sanitize_text_field($_POST['status'] ?? 'active');

    if (empty($room_code)) {
        wp_send_json_error(['message' => 'Room code is required.']);
    }

    $table = $wpdb->prefix . 'cs_rooms';

    if ($id > 0) {
        $result = $wpdb->update($table,
            compact('room_code','building','capacity','room_type','status'),
            ['id'=>$id],
            ['%s','%s','%d','%s','%s'], ['%d']
        );
        if ($result !== false) wp_send_json_success(['message' => 'Room updated.']);
        else wp_send_json_error(['message' => 'Failed to update room.']);
    } else {
        $rand_id = function_exists('bntm_rand_id') ? bntm_rand_id() : wp_generate_password(12, false);
        $result  = $wpdb->insert($table,
            ['rand_id'=>$rand_id,'room_code'=>$room_code,
             'building'=>$building,'capacity'=>$capacity,'room_type'=>$room_type,'status'=>'active'],
            ['%s','%s','%s','%d','%s','%s']
        );
        if ($result) wp_send_json_success(['message' => 'Room added.']);
        else wp_send_json_error(['message' => 'Failed to add room.']);
    }
}

function bntm_ajax_cs_delete_room() {
    check_ajax_referer('cs_rooms_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $id          = intval($_POST['id']);
    $result      = $wpdb->delete($wpdb->prefix . 'cs_rooms', ['id'=>$id], ['%d']);

    if ($result) wp_send_json_success(['message' => 'Room deleted.']);
    else wp_send_json_error(['message' => 'Failed to delete.']);
}

// ─────────────────────────────────────────────────────────────
// TAB: DEPARTMENTS
// ─────────────────────────────────────────────────────────────

function cs_departments_tab() {
    global $wpdb;

    $departments = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}cs_departments ORDER BY dept_name ASC"
    );

    $nonce = wp_create_nonce('cs_departments_nonce');

    ob_start();
    ?>
    <div class="cs-panel">
        <h3>Departments</h3>
        <button class="cs-btn cs-btn-primary" id="cs-add-dept-btn">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Department
        </button>
        <button class="cs-btn cs-btn-secondary" id="cs-bulk-dept-btn" style="margin-left:8px;">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            Bulk Import
        </button>
        <div id="cs-dept-notice"></div>
    </div>

    <div class="cs-panel">
        <?php if (empty($departments)): ?>
        <p style="color:#5c7ea6;font-size:13.5px;">No departments yet. Add a department to start assigning instructors.</p>
        <?php else: ?>
        <div class="cs-table-wrap">
        <table class="cs-table">
            <thead>
                <tr><th>Department Name</th><th>Code</th><th>Instructors</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($departments as $dept):
                    $inst_count = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}cs_instructors WHERE department=%s AND status='active'",
                        $dept->dept_name
                    ));
                ?>
                <tr>
                    <td><strong><?php echo esc_html($dept->dept_name); ?></strong></td>
                    <td><?php echo esc_html($dept->dept_code ?: '—'); ?></td>
                    <td><span class="cs-badge cs-badge-blue"><?php echo $inst_count; ?></span></td>
                    <td><span class="cs-badge <?php echo $dept->status === 'active' ? 'cs-badge-green' : 'cs-badge-gray'; ?>"><?php echo ucfirst($dept->status); ?></span></td>
                    <td>
                        <div class="cs-action-wrap">
                            <button class="cs-action-trigger" title="Actions">&#8942;</button>
                            <div class="cs-action-menu">
                                <button class="cs-btn cs-btn-secondary cs-btn-sm cs-edit-dept"
                                    data-id="<?php echo $dept->id; ?>"
                                    data-name="<?php echo esc_attr($dept->dept_name); ?>"
                                    data-code="<?php echo esc_attr($dept->dept_code); ?>"
                                    data-status="<?php echo esc_attr($dept->status); ?>">Edit</button>
                                <button class="cs-btn cs-btn-danger cs-btn-sm cs-del-dept" data-id="<?php echo $dept->id; ?>" data-name="<?php echo esc_attr($dept->dept_name); ?>">Delete</button>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <script>
    (function () {
        const nonce = '<?php echo $nonce; ?>';

        function deptForm(data) {
            data = data || {};
            return `
            <h2 class="cs-modal-title">${data.id ? 'Edit Department' : 'Add Department'}</h2>
            <div class="cs-form-grid">
                <div class="cs-field">
                    <label>Department Name *</label>
                    <input type="text" id="df-name" value="${data.name||''}" placeholder="e.g. College of Engineering" maxlength="150">
                </div>
                <div class="cs-field">
                    <label>Department Code <small style="color:#5c7ea6;">(optional)</small></label>
                    <input type="text" id="df-code" value="${data.code||''}" placeholder="e.g. COE" maxlength="30">
                </div>
                ${data.id ? `<div class="cs-field">
                    <label>Status</label>
                    <select id="df-status">
                        <option value="active"   ${data.status==='active'?'selected':''}>Active</option>
                        <option value="inactive" ${data.status==='inactive'?'selected':''}>Inactive</option>
                    </select>
                </div>` : ''}
            </div>
            <div id="df-notice"></div>
            <div class="cs-btn-group">
                <button class="cs-btn cs-btn-primary" id="df-save">Save Department</button>
                <button class="cs-btn cs-btn-secondary" onclick="csCloseModal()">Cancel</button>
            </div>
            <input type="hidden" id="df-id" value="${data.id||0}">`;
        }

        function bindDeptForm() {
            document.getElementById('df-save').addEventListener('click', function () {
                const btn = this;
                const fd = new FormData();
                fd.append('action', 'cs_save_department');
                fd.append('nonce', nonce);
                fd.append('id', document.getElementById('df-id').value);
                fd.append('dept_name', document.getElementById('df-name').value.trim());
                fd.append('dept_code', document.getElementById('df-code').value.trim().toUpperCase());
                const statusEl = document.getElementById('df-status');
                if (statusEl) fd.append('status', statusEl.value);
                btn.disabled = true; btn.textContent = 'Saving...';
                csAjax(fd).then(json => {
                    if (json.success) location.reload();
                    else { csNotice(document.getElementById('df-notice'), json.data.message, 'error'); btn.disabled=false; btn.textContent='Save Department'; }
                });
            });
        }

        document.getElementById('cs-add-dept-btn').addEventListener('click', function () {
            csOpenModal(deptForm()); bindDeptForm();
        });

        document.querySelectorAll('.cs-edit-dept').forEach(function (btn) {
            btn.addEventListener('click', function () {
                csOpenModal(deptForm({ id: this.dataset.id, name: this.dataset.name, code: this.dataset.code, status: this.dataset.status }));
                bindDeptForm();
            });
        });

        document.querySelectorAll('.cs-del-dept').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm('Delete department "' + this.dataset.name + '"? Instructors assigned to this department will not be deleted.')) return;
                const fd = new FormData();
                fd.append('action', 'cs_delete_department');
                fd.append('nonce', nonce);
                fd.append('id', this.dataset.id);
                csAjax(fd).then(json => {
                    if (json.success) location.reload();
                    else csNotice(document.getElementById('cs-dept-notice'), json.data.message, 'error');
                });
            });
        });

        // ── Bulk Import Departments ──
        document.getElementById('cs-bulk-dept-btn').addEventListener('click', function () {
            csOpenModal(`
                <h2 class="cs-modal-title">Bulk Import Departments</h2>
                <p style="font-size:13px;color:#5c7ea6;margin:0 0 12px;">Paste CSV data below. Each row: <code>dept_name, dept_code</code><br>Example: <code>College of Engineering, COE</code></p>
                <div class="cs-field"><label>CSV Data</label><textarea id="bulk-dept-csv" rows="10" placeholder="College of Engineering, COE&#10;College of Arts and Sciences, CAS" style="font-family:monospace;font-size:12.5px;"></textarea></div>
                <div id="bulk-dept-notice"></div>
                <div class="cs-btn-group">
                    <button class="cs-btn cs-btn-primary" id="bulk-dept-save">Import</button>
                    <button class="cs-btn cs-btn-secondary" onclick="csCloseModal()">Cancel</button>
                </div>`);
            document.getElementById('bulk-dept-save').addEventListener('click', function () {
                const btn = this;
                const lines = document.getElementById('bulk-dept-csv').value.trim().split('\n').filter(l => l.trim());
                if (!lines.length) { csNotice(document.getElementById('bulk-dept-notice'), 'No data entered.', 'error'); return; }
                const rows = lines.map(l => {
                    const idx = l.indexOf(',');
                    const name = idx >= 0 ? l.substring(0, idx).trim() : l.trim();
                    const code = idx >= 0 ? l.substring(idx + 1).trim() : '';
                    return { dept_name: name, dept_code: code };
                });
                btn.disabled = true; btn.textContent = 'Importing...';
                const fd = new FormData();
                fd.append('action', 'cs_bulk_import_departments');
                fd.append('nonce', nonce);
                fd.append('rows', JSON.stringify(rows));
                csAjax(fd).then(json => {
                    if (json.success) { csNotice(document.getElementById('bulk-dept-notice'), json.data.message, 'success'); setTimeout(() => location.reload(), 1200); }
                    else { csNotice(document.getElementById('bulk-dept-notice'), json.data.message, 'error'); btn.disabled=false; btn.textContent='Import'; }
                });
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ─────────────────────────────────────────────────────────────
// AJAX: DEPARTMENTS
// ─────────────────────────────────────────────────────────────

function bntm_ajax_cs_save_department() {
    check_ajax_referer('cs_departments_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;

    // Ensure the departments table exists (handles installs before this feature was added)
    $table = $wpdb->prefix . 'cs_departments';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            dept_name VARCHAR(150) NOT NULL,
            dept_code VARCHAR(30) NOT NULL DEFAULT '',
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) {$charset};");
    }
    $id          = intval($_POST['id']);
    $dept_name   = sanitize_text_field($_POST['dept_name'] ?? '');
    $dept_code   = strtoupper(sanitize_text_field($_POST['dept_code'] ?? ''));
    $status      = sanitize_text_field($_POST['status'] ?? 'active');

    if (empty($dept_name)) {
        wp_send_json_error(['message' => 'Department name is required.']);
    }

    $table = $wpdb->prefix . 'cs_departments';

    if ($id > 0) {
        $result = $wpdb->update($table,
            compact('dept_name','dept_code','status'),
            ['id' => $id],
            ['%s','%s','%s'], ['%d']
        );
        if ($result !== false) wp_send_json_success(['message' => 'Department updated.']);
        else wp_send_json_error(['message' => 'Failed to update department.']);
    } else {
        $rand_id = function_exists('bntm_rand_id') ? bntm_rand_id() : wp_generate_password(12, false);
        $result  = $wpdb->insert($table,
            ['rand_id'=>$rand_id,'dept_name'=>$dept_name,'dept_code'=>$dept_code,'status'=>'active'],
            ['%s','%s','%s','%s']
        );
        if ($result) wp_send_json_success(['message' => 'Department added.', 'id' => $wpdb->insert_id]);
        else wp_send_json_error(['message' => 'Failed to add department.']);
    }
}

function bntm_ajax_cs_delete_department() {
    check_ajax_referer('cs_departments_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $id          = intval($_POST['id']);
    $result      = $wpdb->delete($wpdb->prefix . 'cs_departments', ['id'=>$id], ['%d']);

    if ($result) wp_send_json_success(['message' => 'Department deleted.']);
    else wp_send_json_error(['message' => 'Failed to delete.']);
}

// ─────────────────────────────────────────────────────────────
// AJAX: BULK IMPORTS
// ─────────────────────────────────────────────────────────────

function bntm_ajax_cs_save_course() {
    check_ajax_referer('cs_courses_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $id          = intval($_POST['id']);
    $course_code = strtoupper(sanitize_text_field($_POST['course_code'] ?? ''));
    $course_title= sanitize_text_field($_POST['course_title'] ?? '');
    $department  = sanitize_textarea_field($_POST['department'] ?? '');
    $curriculum  = sanitize_text_field($_POST['curriculum'] ?? '');
    $status      = sanitize_text_field($_POST['status'] ?? 'active');

    if (empty($course_code)) {
        wp_send_json_error(['message' => 'Course code is required.']);
    }

    $table = $wpdb->prefix . 'cs_courses';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            course_code VARCHAR(20) NOT NULL,
            course_title VARCHAR(180) NOT NULL DEFAULT '',
            department TEXT NOT NULL DEFAULT '',
            curriculum VARCHAR(120) NOT NULL DEFAULT '',
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) {$charset};");
    }
    $title_col_exists = $wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'course_title'");
    if (!$title_col_exists) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN course_title VARCHAR(180) NOT NULL DEFAULT '' AFTER course_code");
    }
    $department_col_exists = $wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'department'");
    if (!$department_col_exists) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN department TEXT NOT NULL DEFAULT '' AFTER course_title");
    }

    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table} WHERE course_code=%s AND curriculum=%s AND id<>%d",
        $course_code, $curriculum, $id
    ));
    if ($existing) {
        wp_send_json_error(['message' => 'This course and curriculum already exists.']);
    }

    if ($id > 0) {
        $result = $wpdb->update($table,
            compact('course_code','course_title','department','curriculum','status'),
            ['id'=>$id],
            ['%s','%s','%s','%s','%s'],
            ['%d']
        );
        if ($result !== false) wp_send_json_success(['message' => 'Course updated.']);
        else wp_send_json_error(['message' => 'Failed to update course.']);
    } else {
        $rand_id = function_exists('bntm_rand_id') ? bntm_rand_id() : wp_generate_password(12, false);
        $result = $wpdb->insert($table,
            ['rand_id'=>$rand_id,'course_code'=>$course_code,'course_title'=>$course_title,'department'=>$department,'curriculum'=>$curriculum,'status'=>'active'],
            ['%s','%s','%s','%s','%s','%s']
        );
        if ($result) wp_send_json_success(['message' => 'Course added.', 'id' => $wpdb->insert_id]);
        else wp_send_json_error(['message' => 'Failed to add course.']);
    }
}

function bntm_ajax_cs_delete_course() {
    check_ajax_referer('cs_courses_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $id          = intval($_POST['id']);
    $result      = $wpdb->delete($wpdb->prefix . 'cs_courses', ['id'=>$id], ['%d']);

    if ($result) wp_send_json_success(['message' => 'Course deleted.']);
    else wp_send_json_error(['message' => 'Failed to delete.']);
}

function bntm_ajax_cs_bulk_import_sections() {
    check_ajax_referer('cs_sections_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $rows        = json_decode(stripslashes($_POST['rows']), true);

    if (empty($rows) || !is_array($rows)) {
        wp_send_json_error(['message' => 'No data provided.']);
    }

    $table    = $wpdb->prefix . 'cs_sections';
    $imported = 0;
    $skipped  = 0;

    foreach ($rows as $r) {
        $course_code   = strtoupper(sanitize_text_field($r['course_code'] ?? ''));
        $year_level    = intval($r['year_level'] ?? 1);
        $block         = strtoupper(sanitize_text_field($r['block'] ?? ''));
        $academic_year = sanitize_text_field($r['academic_year'] ?? '');
        $semester      = sanitize_text_field($r['semester'] ?? 'First');

        if (empty($course_code) || empty($block) || $year_level < 1) { $skipped++; continue; }

        $section_name = $course_code . $year_level . ' ' . $block;

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE section_name=%s",
            $section_name
        ));
        if ($exists) { $skipped++; continue; }

        $rand_id = function_exists('bntm_rand_id') ? bntm_rand_id() : wp_generate_password(12, false);
        $result  = $wpdb->insert($table, [
            'rand_id'       => $rand_id,
            'section_name'  => $section_name,
            'course_code'   => $course_code,
            'year_level'    => $year_level,
            'block'         => $block,
            'academic_year' => $academic_year,
            'semester'      => $semester,
            'status'        => 'active',
        ], ['%s','%s','%s','%d','%s','%s','%s','%s']);
        if ($result) $imported++;
    }

    $msg = "Imported {$imported} section" . ($imported !== 1 ? 's' : '');
    if ($skipped) $msg .= ", skipped {$skipped} (missing fields or duplicates)";
    wp_send_json_success(['message' => $msg . '.', 'imported' => $imported]);
}

function bntm_ajax_cs_bulk_import_instructors() {
    check_ajax_referer('cs_instructors_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $rows        = json_decode(stripslashes($_POST['rows']), true);

    if (empty($rows) || !is_array($rows)) {
        wp_send_json_error(['message' => 'No data provided.']);
    }

    $table    = $wpdb->prefix . 'cs_instructors';
    $imported = 0;
    $skipped  = 0;

    foreach ($rows as $r) {
        $initials         = strtoupper(sanitize_text_field($r['initials'] ?? ''));
        $full_name        = sanitize_text_field($r['full_name'] ?? '');
        $department       = sanitize_text_field($r['department'] ?? '');
        $subjects_handled = sanitize_text_field($r['subjects_handled'] ?? '');

        if (empty($initials) || empty($full_name)) { $skipped++; continue; }

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE initials=%s",
            $initials
        ));
        if ($exists) { $skipped++; continue; }

        $rand_id = function_exists('bntm_rand_id') ? bntm_rand_id() : wp_generate_password(12, false);
        $result  = $wpdb->insert($table, [
            'rand_id'          => $rand_id,
            'initials'         => $initials,
            'full_name'        => $full_name,
            'department'       => $department,
            'subjects_handled' => $subjects_handled,
            'status'           => 'active',
        ], ['%s','%s','%s','%s','%s','%s']);
        if ($result) $imported++;
    }

    $msg = "Imported {$imported} instructor" . ($imported !== 1 ? 's' : '');
    if ($skipped) $msg .= ", skipped {$skipped} (missing fields or duplicate initials)";
    wp_send_json_success(['message' => $msg . '.', 'imported' => $imported]);
}

function bntm_ajax_cs_bulk_import_rooms() {
    check_ajax_referer('cs_rooms_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $rows        = json_decode(stripslashes($_POST['rows']), true);

    if (empty($rows) || !is_array($rows)) {
        wp_send_json_error(['message' => 'No data provided.']);
    }

    $table         = $wpdb->prefix . 'cs_rooms';
    $valid_types   = ['lecture', 'lab', 'both'];
    $imported      = 0;
    $skipped       = 0;

    foreach ($rows as $r) {
        $room_code = strtoupper(sanitize_text_field($r['room_code'] ?? ''));
        $building  = sanitize_text_field($r['building'] ?? '');
        $capacity  = max(1, intval($r['capacity'] ?? 40));
        $room_type = in_array($r['room_type'] ?? '', $valid_types) ? $r['room_type'] : 'lecture';

        if (empty($room_code)) { $skipped++; continue; }

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE room_code=%s",
            $room_code
        ));
        if ($exists) { $skipped++; continue; }

        $rand_id = function_exists('bntm_rand_id') ? bntm_rand_id() : wp_generate_password(12, false);
        $result  = $wpdb->insert($table, [
            'rand_id'    => $rand_id,
            'room_code'  => $room_code,
            'building'   => $building,
            'capacity'   => $capacity,
            'room_type'  => $room_type,
            'status'     => 'active',
        ], ['%s','%s','%s','%d','%s','%s']);
        if ($result) $imported++;
    }

    $msg = "Imported {$imported} room" . ($imported !== 1 ? 's' : '');
    if ($skipped) $msg .= ", skipped {$skipped} (missing room code or duplicates)";
    wp_send_json_success(['message' => $msg . '.', 'imported' => $imported]);
}

function bntm_ajax_cs_bulk_import_departments() {
    check_ajax_referer('cs_departments_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $rows        = json_decode(stripslashes($_POST['rows']), true);

    if (empty($rows) || !is_array($rows)) {
        wp_send_json_error(['message' => 'No data provided.']);
    }

    // Ensure table exists
    $table = $wpdb->prefix . 'cs_departments';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            dept_name VARCHAR(150) NOT NULL,
            dept_code VARCHAR(30) NOT NULL DEFAULT '',
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) {$charset};");
    }

    $imported = 0;
    $skipped  = 0;

    foreach ($rows as $r) {
        $dept_name = sanitize_text_field($r['dept_name'] ?? '');
        $dept_code = strtoupper(sanitize_text_field($r['dept_code'] ?? ''));

        if (empty($dept_name)) { $skipped++; continue; }

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE dept_name=%s",
            $dept_name
        ));
        if ($exists) { $skipped++; continue; }

        $rand_id = function_exists('bntm_rand_id') ? bntm_rand_id() : wp_generate_password(12, false);
        $result  = $wpdb->insert($table, [
            'rand_id'    => $rand_id,
            'dept_name'  => $dept_name,
            'dept_code'  => $dept_code,
            'status'     => 'active',
        ], ['%s','%s','%s','%s']);
        if ($result) $imported++;
    }

    $msg = "Imported {$imported} department" . ($imported !== 1 ? 's' : '');
    if ($skipped) $msg .= ", skipped {$skipped} (missing name or duplicates)";
    wp_send_json_success(['message' => $msg . '.', 'imported' => $imported]);
}

// ─────────────────────────────────────────────────────────────
// PUBLIC SHORTCODE: SECTION TIMETABLE
// ─────────────────────────────────────────────────────────────

function bntm_shortcode_cs_public() {
    global $wpdb;

    $section_id = isset($_GET['section']) ? intval($_GET['section']) : 0;

    if (!$section_id) {
        return '<div class="cs-notice cs-notice-info" style="font-family:sans-serif;">Please provide a section ID in the URL: <code>?section=ID</code></div>';
    }

    $section = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}cs_sections WHERE id=%d AND status='active'",
        $section_id
    ));

    if (!$section) {
        return '<div class="cs-notice cs-notice-error" style="font-family:sans-serif;">Section not found.</div>';
    }

    $entries = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}cs_schedules WHERE section_id=%d",
        $section_id
    ));

    $legacy_dg_map = [
        'mon_thu' => ['Mon','Thu'],
        'tue_fri' => ['Tue','Fri'],
        'wed_sat' => ['Wed','Sat'],
    ];
    $day_order = ['Mon'=>0,'Tue'=>1,'Wed'=>2,'Thu'=>3,'Fri'=>4,'Sat'=>5,'Sun'=>6];
    $day_labels = ['Mon'=>'Monday','Tue'=>'Tuesday','Wed'=>'Wednesday','Thu'=>'Thursday',
                   'Fri'=>'Friday','Sat'=>'Saturday','Sun'=>'Sunday'];

    $norm_slot = function($s) {
        if (preg_match('/^(\d{1,2}:\d{2})\s*[-–]\s*(\d{1,2}:\d{2})$/', trim($s), $m)) {
            $pad = fn($t) => strlen($t) === 4 ? '0'.$t : $t;
            return $pad($m[1]) . '-' . $pad($m[2]);
        }
        return trim($s);
    };

    $pub_active_days  = [];
    $pub_active_slots = [];
    $pub_grid = [];

    foreach ($entries as $e) {
        $slot = $norm_slot($e->time_slot);
        if (isset($legacy_dg_map[$e->day_group])) {
            $days_here = $legacy_dg_map[$e->day_group];
        } else {
            $days_here = array_filter(
                array_map('trim', explode(',', $e->day_group)),
                fn($d) => isset($day_order[$d])
            );
        }
        foreach ($days_here as $day) {
            if (!in_array($day, $pub_active_days)) $pub_active_days[] = $day;
            $pub_grid[$day][$slot] = $e;
        }
        if (!in_array($slot, $pub_active_slots)) $pub_active_slots[] = $slot;
    }

    usort($pub_active_days,  fn($a,$b) => ($day_order[$a]??99) - ($day_order[$b]??99));
    sort($pub_active_slots);

    if (empty($pub_active_days))  $pub_active_days  = ['Mon','Tue','Wed','Thu','Fri','Sat'];
    if (empty($pub_active_slots)) $pub_active_slots = ['07:30-09:00'];

    ob_start();
    ?>
    <style>
    @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600;700&display=swap');
    .cs-pub * { box-sizing: border-box; font-family: 'DM Sans', system-ui, sans-serif; }
    .cs-pub h2 { font-family: 'Playfair Display', Georgia, serif; font-size: 22px; font-weight: 700; color: #0f1e35; margin: 0 0 4px; }
    .cs-pub .sub { font-size: 13px; color: #6b7280; margin-bottom: 18px; font-weight: 500; }
    .cs-pub-wrap { overflow-x: auto; border-radius: 8px; border: 1px solid #dde3ec; box-shadow: 0 2px 8px rgba(15,30,53,.07); }
    .cs-pub-table { width: 100%; border-collapse: collapse; font-size: 12.5px; min-width: 700px; }
    .cs-pub-table th { background: #0f1e35; color: rgba(255,255,255,.85); padding: 12px 10px; text-align: center; border: 1px solid #1e3555; font-size: 11px; font-weight: 700; letter-spacing: .8px; text-transform: uppercase; }
    .cs-pub-table td { border: 1px solid #dde3ec; padding: 0; vertical-align: top; }
    .cs-pub-slot { background: #172842; color: rgba(255,255,255,.72); padding: 10px 14px; font-weight: 600; font-size: 11.5px; text-align: center; white-space: nowrap; border: 1px solid #1e3555; }
    .cs-pub-cell { padding: 7px 8px; min-height: 64px; background: #ffffff; }
    .cs-pub-entry { background: #eff6ff; border: 1px solid #bfdbfe; border-left: 3px solid #1a4f8a; border-radius: 6px; padding: 5px 8px; line-height: 1.5; }
    .cs-pub-entry.lab { background: #f0fdf4; border-color: #bbf7d0; border-left-color: #16a34a; }
    .cs-pub-entry b { display: block; font-size: 12px; color: #1e3a6e; font-weight: 700; }
    .cs-pub-entry span { font-size: 11px; color: #4a5563; display: block; }
    .cs-pub-entry.lab b { color: #14532d; }
    .cs-pub-empty { background: #f5f7fa; }
    .cs-pub-legend { display: flex; gap: 16px; margin-bottom: 12px; font-size: 12px; font-weight: 600; color: #6b7280; }
    .cs-pub-legend-item { display: flex; align-items: center; gap: 6px; }
    .cs-pub-legend-box { width: 14px; height: 14px; border-radius: 3px; }
    </style>
    <div class="cs-pub">
        <h2><?php echo esc_html($section->section_name); ?></h2>
        <div class="sub">
            <?php echo esc_html($section->academic_year ?: ''); ?>
            <?php if ($section->academic_year && $section->semester): echo ' &bull; '; endif; ?>
            <?php echo esc_html($section->semester); ?> Semester
        </div>
        <div class="cs-pub-legend">
            <div class="cs-pub-legend-item"><div class="cs-pub-legend-box" style="background:#e8eef7;border:1px solid #c0cfe8"></div> Lecture</div>
            <div class="cs-pub-legend-item"><div class="cs-pub-legend-box" style="background:#e9f5ee;border:1px solid #a8d8b8"></div> Lab</div>
        </div>
        <div class="cs-pub-wrap">
        <table class="cs-pub-table">
            <thead>
                <tr>
                    <th style="min-width:100px;">Time</th>
                    <?php foreach ($pub_active_days as $day): ?>
                    <th><?php echo esc_html($day_labels[$day] ?? $day); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pub_active_slots as $slot): ?>
                <tr>
                    <td class="cs-pub-slot"><?php echo esc_html(str_replace('-', ' – ', $slot)); ?></td>
                    <?php foreach ($pub_active_days as $day):
                        if (isset($pub_grid[$day][$slot])):
                            $e = $pub_grid[$day][$slot];
                            $is_lab = strpos($e->subject_code, ' L') !== false;
                    ?>
                    <td class="cs-pub-cell">
                        <div class="cs-pub-entry <?php echo $is_lab ? 'lab' : ''; ?>">
                            <b><?php echo esc_html($e->subject_code); ?></b>
                            <span><?php echo esc_html($e->instructor_initials); ?></span>
                            <span><?php echo esc_html($e->room); ?></span>
                        </div>
                    </td>
                    <?php else: ?>
                    <td class="cs-pub-cell cs-pub-empty"></td>
                    <?php endif; endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
