<?php
/**
 * Module Name: Photo Booth
 * Module Slug: pb
 * Description: Mobile-first PWA photo booth system for events. Guests scan a QR code, capture photos with guided prompts, and upload them to a shared real-time gallery displayed on screen.
 * Version: 1.0.0
 * Author: BNTM
 * Icon: <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
 */

if (!defined('ABSPATH')) exit;

define('BNTM_PB_PATH', dirname(__FILE__) . '/');
define('BNTM_PB_URL', plugin_dir_url(__FILE__));
define('BNTM_PB_DB_VERSION', '1.0.1');

// ============================================================
// MODULE CONFIGURATION
// ============================================================

function bntm_pb_get_pages() {
    return [
        'Photo Booth Dashboard' => '[pb_dashboard]',
        'Photo Booth Upload'    => '[pb_upload]',
        'Photo Gallery'         => '[pb_gallery]',
        'Photo Display'         => '[pb_display]',
    ];
}

function bntm_pb_get_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $prefix  = $wpdb->prefix;

    return [
        'pb_events' => "CREATE TABLE {$prefix}pb_events (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            event_date DATE,
            max_photos INT DEFAULT 0,
            status VARCHAR(50) NOT NULL DEFAULT 'inactive',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id)
        ) {$charset};",

        'pb_prompts' => "CREATE TABLE {$prefix}pb_prompts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            event_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            prompt_text VARCHAR(255) NOT NULL,
            sort_order INT DEFAULT 0,
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            is_free_capture TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_event (event_id)
        ) {$charset};",

        'pb_photos' => "CREATE TABLE {$prefix}pb_photos (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            event_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            prompt_id BIGINT UNSIGNED DEFAULT 0,
            guest_name VARCHAR(100),
            file_path VARCHAR(500) NOT NULL,
            file_size INT DEFAULT 0,
            width INT DEFAULT 0,
            height INT DEFAULT 0,
            status VARCHAR(50) NOT NULL DEFAULT 'pending',
            guest_ip VARCHAR(45),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_event (event_id)
        ) {$charset};",
    ];
}

function bntm_pb_get_shortcodes() {
    return [
        'pb_dashboard' => 'bntm_shortcode_pb',
        'pb_upload'    => 'bntm_shortcode_pb_upload',
        'pb_gallery'   => 'bntm_shortcode_pb_gallery',
        'pb_display'   => 'bntm_shortcode_pb_display',
    ];
}

function bntm_pb_create_tables() {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $tables = bntm_pb_get_tables();
    foreach ($tables as $sql) {
        dbDelta($sql);
    }
    return count($tables);
}

function bntm_pb_tables_exist() {
    global $wpdb;

    $required_tables = [
        $wpdb->prefix . 'pb_events',
        $wpdb->prefix . 'pb_prompts',
        $wpdb->prefix . 'pb_photos',
    ];

    foreach ($required_tables as $table_name) {
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
        if ($exists !== $table_name) {
            return false;
        }
    }

    return true;
}

function bntm_pb_ensure_tables() {
    $installed_version = get_option('bntm_pb_db_version', '');
    if ($installed_version === BNTM_PB_DB_VERSION && bntm_pb_tables_exist()) {
        return;
    }

    bntm_pb_create_tables();
    update_option('bntm_pb_db_version', BNTM_PB_DB_VERSION, false);
}

add_action('init', 'bntm_pb_ensure_tables');

function pb_get_frontend_page_url($slug, $shortcode, $query_args = []) {
    $page = get_page_by_path($slug);

    if (!$page || empty($page->ID)) {
        global $wpdb;
        $like = '%[' . $wpdb->esc_like($shortcode) . '%';
        $page_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE %s
             ORDER BY ID ASC LIMIT 1",
            $like
        ));
        if ($page_id > 0) {
            $page = get_post($page_id);
        }
    }

    if (!$page || empty($page->ID)) {
        return '#';
    }

    $url = get_permalink($page->ID);
    if (!empty($query_args)) {
        $url = add_query_arg($query_args, $url);
    }
    return $url;
}

// ============================================================
// AJAX ACTION HOOKS
// ============================================================

add_action('wp_ajax_pb_get_recent_uploads',    'bntm_ajax_pb_get_recent_uploads');
add_action('wp_ajax_pb_add_event',             'bntm_ajax_pb_add_event');
add_action('wp_ajax_pb_edit_event',            'bntm_ajax_pb_edit_event');
add_action('wp_ajax_pb_delete_event',          'bntm_ajax_pb_delete_event');
add_action('wp_ajax_pb_toggle_event_status',   'bntm_ajax_pb_toggle_event_status');
add_action('wp_ajax_pb_add_prompt',            'bntm_ajax_pb_add_prompt');
add_action('wp_ajax_pb_edit_prompt',           'bntm_ajax_pb_edit_prompt');
add_action('wp_ajax_pb_delete_prompt',         'bntm_ajax_pb_delete_prompt');
add_action('wp_ajax_pb_reorder_prompt',        'bntm_ajax_pb_reorder_prompt');
add_action('wp_ajax_pb_toggle_prompt_status',  'bntm_ajax_pb_toggle_prompt_status');
add_action('wp_ajax_pb_get_photos',            'bntm_ajax_pb_get_photos');
add_action('wp_ajax_pb_update_photo_status',   'bntm_ajax_pb_update_photo_status');
add_action('wp_ajax_pb_delete_photo',          'bntm_ajax_pb_delete_photo');
add_action('wp_ajax_pb_bulk_action_photos',    'bntm_ajax_pb_bulk_action_photos');
add_action('wp_ajax_pb_save_display_settings', 'bntm_ajax_pb_save_display_settings');
add_action('wp_ajax_pb_save_settings',         'bntm_ajax_pb_save_settings');

// Public AJAX — upload and gallery polling
add_action('wp_ajax_pb_upload_photo',          'bntm_ajax_pb_upload_photo');
add_action('wp_ajax_nopriv_pb_upload_photo',   'bntm_ajax_pb_upload_photo');
add_action('wp_ajax_pb_get_event_data',        'bntm_ajax_pb_get_event_data');
add_action('wp_ajax_nopriv_pb_get_event_data', 'bntm_ajax_pb_get_event_data');
add_action('wp_ajax_pb_poll_gallery',          'bntm_ajax_pb_poll_gallery');
add_action('wp_ajax_nopriv_pb_poll_gallery',   'bntm_ajax_pb_poll_gallery');

// ============================================================
// MAIN DASHBOARD SHORTCODE
// ============================================================

function bntm_shortcode_pb() {
    if (!is_user_logged_in()) {
        return '<div class="bntm-notice">Please log in.</div>';
    }

    bntm_pb_ensure_tables();

    $current_user = wp_get_current_user();
    $business_id  = $current_user->ID;
    $active_tab   = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';

    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    var pb_nonce = '<?php echo wp_create_nonce('pb_nonce'); ?>';
    </script>

    <div class="bntm-pb-container">
        <div class="bntm-tabs">
            <a href="?tab=overview"  class="bntm-tab <?php echo $active_tab === 'overview'  ? 'active' : ''; ?>">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" stroke-width="2" rx="1"/><rect x="14" y="3" width="7" height="7" stroke-width="2" rx="1"/><rect x="3" y="14" width="7" height="7" stroke-width="2" rx="1"/><rect x="14" y="14" width="7" height="7" stroke-width="2" rx="1"/></svg>
                Overview
            </a>
            <a href="?tab=events"    class="bntm-tab <?php echo $active_tab === 'events'    ? 'active' : ''; ?>">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" stroke-width="2"/><line x1="16" y1="2" x2="16" y2="6" stroke-width="2"/><line x1="8" y1="2" x2="8" y2="6" stroke-width="2"/><line x1="3" y1="10" x2="21" y2="10" stroke-width="2"/></svg>
                Events
            </a>
            <a href="?tab=prompts"   class="bntm-tab <?php echo $active_tab === 'prompts'   ? 'active' : ''; ?>">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" stroke-width="2"/></svg>
                Prompts
            </a>
            <a href="?tab=photos"    class="bntm-tab <?php echo $active_tab === 'photos'    ? 'active' : ''; ?>">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z" stroke-width="2"/><circle cx="12" cy="13" r="4" stroke-width="2"/></svg>
                Photos
            </a>
            <a href="?tab=display"   class="bntm-tab <?php echo $active_tab === 'display'   ? 'active' : ''; ?>">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2" stroke-width="2"/><line x1="8" y1="21" x2="16" y2="21" stroke-width="2"/><line x1="12" y1="17" x2="12" y2="21" stroke-width="2"/></svg>
                Display
            </a>
            <a href="?tab=settings"  class="bntm-tab <?php echo $active_tab === 'settings'  ? 'active' : ''; ?>">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3" stroke-width="2"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" stroke-width="2"/></svg>
                Settings
            </a>
        </div>

        <div class="bntm-tab-content">
            <?php if ($active_tab === 'overview'): ?>
                <?php echo pb_overview_tab($business_id); ?>
            <?php elseif ($active_tab === 'events'): ?>
                <?php echo pb_events_tab($business_id); ?>
            <?php elseif ($active_tab === 'prompts'): ?>
                <?php echo pb_prompts_tab($business_id); ?>
            <?php elseif ($active_tab === 'photos'): ?>
                <?php echo pb_photos_tab($business_id); ?>
            <?php elseif ($active_tab === 'display'): ?>
                <?php echo pb_display_settings_tab($business_id); ?>
            <?php elseif ($active_tab === 'settings'): ?>
                <?php echo pb_settings_tab($business_id); ?>
            <?php endif; ?>
        </div>
    </div>

    <style>
    .bntm-pb-container { width: 100%; }

    /* Modal */
    .pb-modal-overlay {
        display: none; position: fixed; inset: 0;
        background: rgba(0,0,0,0.55); z-index: 9999;
        align-items: center; justify-content: center;
    }
    .pb-modal-overlay.open { display: flex; }
    .pb-modal {
        background: #fff; border-radius: 12px; padding: 28px;
        width: 90%; max-width: 520px; max-height: 90vh;
        overflow-y: auto; position: relative;
        box-shadow: 0 20px 60px rgba(0,0,0,0.25);
    }
    .pb-modal h3 { margin: 0 0 20px; font-size: 18px; font-weight: 700; }
    .pb-modal-close {
        position: absolute; top: 16px; right: 16px;
        background: none; border: none; cursor: pointer;
        color: #9ca3af; font-size: 20px; line-height: 1;
        width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;
        border-radius: 50%;
    }
    .pb-modal-close:hover { background: #f3f4f6; color: #374151; }

    /* Photo grid */
    .pb-photo-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 10px; margin-top: 16px;
    }
    .pb-photo-thumb {
        aspect-ratio: 1; border-radius: 8px; overflow: hidden;
        position: relative; cursor: pointer; background: #f3f4f6;
    }
    .pb-photo-thumb img {
        width: 100%; height: 100%; object-fit: cover;
        transition: transform 0.2s;
    }
    .pb-photo-thumb:hover img { transform: scale(1.05); }
    .pb-photo-thumb .pb-status-dot {
        position: absolute; top: 6px; right: 6px;
        width: 10px; height: 10px; border-radius: 50%;
        border: 2px solid #fff;
    }
    .pb-status-dot.approved { background: #10b981; }
    .pb-status-dot.pending  { background: #f59e0b; }
    .pb-status-dot.flagged  { background: #ef4444; }

    /* Lightbox */
    .pb-lightbox {
        display: none; position: fixed; inset: 0;
        background: rgba(0,0,0,0.92); z-index: 10000;
        align-items: center; justify-content: center;
    }
    .pb-lightbox.open { display: flex; }
    .pb-lightbox-inner { position: relative; max-width: 90vw; max-height: 90vh; }
    .pb-lightbox-inner img { max-width: 90vw; max-height: 80vh; border-radius: 8px; display: block; }
    .pb-lightbox-info {
        position: absolute; bottom: 0; left: 0; right: 0;
        background: linear-gradient(transparent, rgba(0,0,0,0.8));
        color: #fff; padding: 16px; border-radius: 0 0 8px 8px;
        font-size: 13px;
    }
    .pb-lightbox-close {
        position: absolute; top: -36px; right: 0;
        background: rgba(255,255,255,0.15); border: none; color: #fff;
        cursor: pointer; border-radius: 50%; width: 32px; height: 32px;
        display: flex; align-items: center; justify-content: center; font-size: 18px;
    }
    .pb-lightbox-close:hover { background: rgba(255,255,255,0.3); }

    /* QR preview */
    .pb-qr-wrap { text-align: center; padding: 16px; background: #f9fafb; border-radius: 8px; }
    .pb-qr-wrap img { max-width: 180px; border-radius: 4px; }

    /* Stats row overrides */
    .pb-stat-active { background: linear-gradient(135deg, var(--bntm-primary), var(--bntm-primary-hover)); }

    /* Event cards in overview */
    .pb-active-event-banner {
        background: linear-gradient(135deg, var(--bntm-primary), var(--bntm-primary-hover));
        color: #fff; border-radius: 12px; padding: 20px 24px;
        display: flex; align-items: center; gap: 20px; flex-wrap: wrap;
        margin-bottom: 24px;
    }
    .pb-active-event-banner h4 { margin: 0 0 4px; font-size: 18px; font-weight: 700; }
    .pb-active-event-banner p  { margin: 0; opacity: 0.85; font-size: 13px; }
    .pb-active-event-banner .pb-event-actions { margin-left: auto; display: flex; gap: 10px; flex-wrap: wrap; }
    .pb-active-event-banner a, .pb-active-event-banner button {
        background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.4);
        color: #fff; padding: 8px 14px; border-radius: 8px; font-size: 13px;
        cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;
    }
    .pb-active-event-banner a:hover, .pb-active-event-banner button:hover {
        background: rgba(255,255,255,0.35);
    }

    /* Frontend pages grid */
    .bntm-frontend-pages-grid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 16px; margin-top: 16px;
    }
    .bntm-page-card {
        border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden;
        display: flex; flex-direction: column;
    }
    .bntm-page-card-header {
        padding: 14px 16px; background: #f9fafb;
        display: flex; align-items: center; justify-content: space-between;
        border-bottom: 1px solid #e5e7eb;
    }
    .bntm-page-card-icon { color: #6b7280; }
    .bntm-page-audience-badge {
        font-size: 11px; font-weight: 600; padding: 3px 8px;
        border-radius: 20px; text-transform: uppercase; letter-spacing: 0.5px;
    }
    .bntm-badge-public   { background: #dcfce7; color: #16a34a; }
    .bntm-badge-loggedin { background: #dbeafe; color: #1d4ed8; }
    .bntm-page-card-body { padding: 14px 16px; flex: 1; }
    .bntm-page-card-body h4 { margin: 0 0 6px; font-size: 14px; font-weight: 600; }
    .bntm-page-card-body p  { margin: 0; font-size: 12px; color: #6b7280; }
    .bntm-page-card-footer {
        padding: 12px 16px; border-top: 1px solid #e5e7eb;
        display: flex; gap: 8px; background: #fff;
    }
    </style>

    <script>
    // Shared modal helpers
    function pbOpenModal(id) {
        const el = document.getElementById(id);
        if (el) el.classList.add('open');
    }
    function pbCloseModal(id) {
        const el = document.getElementById(id);
        if (el) el.classList.remove('open');
    }
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('pb-modal-overlay')) {
            e.target.classList.remove('open');
        }
        if (e.target.classList.contains('pb-lightbox')) {
            e.target.classList.remove('open');
        }
    });

    // Copy URL helper
    function pbCopyUrl(url, msgEl) {
        if (!url || url === '#') {
            if (msgEl) {
                msgEl.innerHTML = '<div class="bntm-notice bntm-notice-error">Upload page URL is not available yet. Create/publish the page first.</div>';
                setTimeout(() => { msgEl.innerHTML = ''; }, 2800);
            }
            return;
        }

        function showCopied() {
            if (msgEl) {
                msgEl.innerHTML = '<div class="bntm-notice bntm-notice-success">URL copied to clipboard!</div>';
                setTimeout(() => { msgEl.innerHTML = ''; }, 2500);
            }
        }

        function fallbackCopy(text) {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            ta.setSelectionRange(0, ta.value.length);
            const ok = document.execCommand('copy');
            document.body.removeChild(ta);
            if (ok) {
                showCopied();
            } else if (msgEl) {
                msgEl.innerHTML = '<div class="bntm-notice bntm-notice-error">Could not copy automatically. Please copy manually.</div>';
                setTimeout(() => { msgEl.innerHTML = ''; }, 3000);
            }
        }

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(url).then(showCopied).catch(function() {
                fallbackCopy(url);
            });
        } else {
            fallbackCopy(url);
        }
    }

    // Toast
    function pbToast(msg, type) {
        const t = document.createElement('div');
        t.className = 'bntm-notice bntm-notice-' + (type || 'success');
        t.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:99999;min-width:240px;box-shadow:0 4px 16px rgba(0,0,0,0.15);';
        t.textContent = msg;
        document.body.appendChild(t);
        setTimeout(() => t.remove(), 3000);
    }
    </script>
    <?php
    $content = ob_get_clean();
    return bntm_universal_container('Photo Booth', $content);
}

// ============================================================
// TAB: OVERVIEW
// ============================================================

function pb_overview_tab($business_id) {
    global $wpdb;
    $events_table = $wpdb->prefix . 'pb_events';
    $photos_table = $wpdb->prefix . 'pb_photos';

    $total_events  = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$events_table} WHERE business_id = %d", $business_id));
    $total_photos  = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$photos_table} WHERE business_id = %d", $business_id));
    $pending_photos= (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$photos_table} WHERE business_id = %d AND status = 'pending'", $business_id));
    $active_event  = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$events_table} WHERE business_id = %d AND status = 'active' LIMIT 1", $business_id));

    $recent_photos = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$photos_table} WHERE business_id = %d AND status = 'approved' ORDER BY created_at DESC LIMIT 20",
        $business_id
    ));

    $upload_dir = wp_upload_dir();

    ob_start();
    ?>
    <!-- Stat Cards -->
    <div class="bntm-stats-row">
        <div class="bntm-stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
                <svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24">
                    <rect x="3" y="4" width="18" height="18" rx="2" stroke-width="2"/>
                    <line x1="16" y1="2" x2="16" y2="6" stroke-width="2"/>
                    <line x1="8" y1="2" x2="8" y2="6" stroke-width="2"/>
                    <line x1="3" y1="10" x2="21" y2="10" stroke-width="2"/>
                </svg>
            </div>
            <div class="stat-content">
                <h3>Total Events</h3>
                <p class="stat-number"><?php echo number_format($total_events); ?></p>
                <span class="stat-label">All time</span>
            </div>
        </div>
        <div class="bntm-stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #8b5cf6, #6d28d9);">
                <svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24">
                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z" stroke-width="2"/>
                    <circle cx="12" cy="13" r="4" stroke-width="2"/>
                </svg>
            </div>
            <div class="stat-content">
                <h3>Total Photos</h3>
                <p class="stat-number"><?php echo number_format($total_photos); ?></p>
                <span class="stat-label">Uploaded</span>
            </div>
        </div>
        <div class="bntm-stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                <svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" stroke-width="2"/>
                    <line x1="12" y1="8" x2="12" y2="12" stroke-width="2"/>
                    <line x1="12" y1="16" x2="12.01" y2="16" stroke-width="2"/>
                </svg>
            </div>
            <div class="stat-content">
                <h3>Pending Review</h3>
                <p class="stat-number"><?php echo number_format($pending_photos); ?></p>
                <span class="stat-label">Awaiting approval</span>
            </div>
        </div>
        <div class="bntm-stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                <svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div class="stat-content">
                <h3>Active Event</h3>
                <p class="stat-number" style="font-size:16px;"><?php echo $active_event ? esc_html(mb_strimwidth($active_event->title, 0, 18, '…')) : '—'; ?></p>
                <span class="stat-label"><?php echo $active_event ? 'Live now' : 'None active'; ?></span>
            </div>
        </div>
    </div>

    <!-- Active Event Banner -->
    <?php if ($active_event): ?>
    <?php
    $upload_url  = pb_get_frontend_page_url('photo-booth-upload', 'pb_upload',  ['event_id' => $active_event->rand_id]);
    $gallery_url = pb_get_frontend_page_url('photo-gallery', 'pb_gallery', ['event_id' => $active_event->rand_id]);
    $display_url = pb_get_frontend_page_url('photo-display', 'pb_display', ['event_id' => $active_event->rand_id]);
    ?>
    <div class="pb-active-event-banner">
        <div>
            <svg width="36" height="36" fill="rgba(255,255,255,0.3)" stroke="white" viewBox="0 0 24 24" style="flex-shrink:0;">
                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z" stroke-width="2"/>
                <circle cx="12" cy="13" r="4" stroke-width="2"/>
            </svg>
        </div>
        <div>
            <h4><?php echo esc_html($active_event->title); ?></h4>
            <p>Live event &mdash; guests can scan and upload photos now</p>
        </div>
        <div class="pb-event-actions">
            <button onclick="pbCopyUrl('<?php echo esc_js($upload_url); ?>', document.getElementById('pb-copy-msg'))" type="button">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" stroke-width="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" stroke-width="2"/></svg>
                Copy Upload URL
            </button>
            <a href="<?php echo esc_url($gallery_url); ?>" target="_blank">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" stroke-width="2"/></svg>
                Gallery
            </a>
            <a href="<?php echo esc_url($display_url); ?>" target="_blank">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2" stroke-width="2"/><line x1="8" y1="21" x2="16" y2="21" stroke-width="2"/><line x1="12" y1="17" x2="12" y2="21" stroke-width="2"/></svg>
                Display
            </a>
        </div>
    </div>
    <div id="pb-copy-msg" style="margin-bottom:12px;"></div>
    <?php endif; ?>

    <!-- Recent Photos -->
    <div class="bntm-form-section">
        <h3>Recent Uploads</h3>
        <?php if (empty($recent_photos)): ?>
            <p style="color:#6b7280;">No approved photos yet. Photos will appear here once uploaded and approved.</p>
        <?php else: ?>
        <div class="pb-photo-grid">
            <?php foreach ($recent_photos as $photo): ?>
            <?php $thumb_url = $upload_dir['baseurl'] . '/' . $photo->file_path; ?>
            <div class="pb-photo-thumb" onclick="pbOpenLightbox('<?php echo esc_js($thumb_url); ?>', '<?php echo esc_js($photo->guest_name ?: 'Guest'); ?>', '')">
                <img src="<?php echo esc_url($thumb_url); ?>" alt="" loading="lazy">
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Frontend Pages -->
    <div class="bntm-form-section">
        <h3>Frontend Pages</h3>
        <p style="color:#6b7280;margin-bottom:16px;">Share these links with your guests and display screens.</p>
        <div class="bntm-frontend-pages-grid">
            <?php
            $pages_map = [
                ['slug' => 'photo-booth-upload', 'shortcode' => 'pb_upload',  'title' => 'Photo Booth Upload', 'desc' => 'Mobile camera & upload experience for guests', 'audience' => 'Public', 'badge' => 'bntm-badge-public'],
                ['slug' => 'photo-gallery',      'shortcode' => 'pb_gallery', 'title' => 'Photo Gallery',      'desc' => 'Real-time shared gallery of approved photos',  'audience' => 'Public', 'badge' => 'bntm-badge-public'],
                ['slug' => 'photo-display',      'shortcode' => 'pb_display', 'title' => 'Photo Display',      'desc' => 'Fullscreen slideshow for TV or projector',      'audience' => 'Public', 'badge' => 'bntm-badge-public'],
            ];
            foreach ($pages_map as $pm):
                $query = [];
                if (!empty($active_event) && !empty($active_event->rand_id)) {
                    $query['event_id'] = $active_event->rand_id;
                }
                $url = pb_get_frontend_page_url($pm['slug'], $pm['shortcode'], $query);
            ?>
            <div class="bntm-page-card">
                <div class="bntm-page-card-header">
                    <div class="bntm-page-card-icon">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                    </div>
                    <span class="bntm-page-audience-badge <?php echo $pm['badge']; ?>"><?php echo $pm['audience']; ?></span>
                </div>
                <div class="bntm-page-card-body">
                    <h4><?php echo esc_html($pm['title']); ?></h4>
                    <p><?php echo esc_html($pm['desc']); ?></p>
                </div>
                <div class="bntm-page-card-footer">
                    <a href="<?php echo esc_url($url); ?>" target="_blank" class="bntm-btn-primary bntm-btn-small">Open Page</a>
                    <button class="bntm-btn-secondary bntm-btn-small" onclick="pbCopyUrl('<?php echo esc_js($url); ?>', document.getElementById('pb-pages-msg'))">Copy URL</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div id="pb-pages-msg" style="margin-top:10px;"></div>
    </div>

    <!-- Lightbox -->
    <div class="pb-lightbox" id="pb-overview-lightbox">
        <div class="pb-lightbox-inner">
            <button class="pb-lightbox-close" onclick="document.getElementById('pb-overview-lightbox').classList.remove('open')">&times;</button>
            <img id="pb-lightbox-img" src="" alt="">
            <div class="pb-lightbox-info">
                <strong id="pb-lightbox-name"></strong>
            </div>
        </div>
    </div>

    <style>
    .pb-photo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px,1fr)); gap: 8px; }
    </style>

    <script>
    (function() {
        window.pbOpenLightbox = function(url, name, prompt) {
            document.getElementById('pb-lightbox-img').src  = url;
            document.getElementById('pb-lightbox-name').textContent = name + (prompt ? ' — ' + prompt : '');
            document.getElementById('pb-overview-lightbox').classList.add('open');
        };
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ============================================================
// TAB: EVENTS
// ============================================================

function pb_events_tab($business_id) {
    global $wpdb;
    bntm_pb_ensure_tables();

    $events_table = $wpdb->prefix . 'pb_events';
    $photos_table = $wpdb->prefix . 'pb_photos';

    // Load events with a simple query first, then attach photo counts.
    // This avoids correlated-subquery edge cases on some DB configurations.
    $events = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$events_table} WHERE business_id = %d ORDER BY id DESC",
        $business_id
    ));

    $photo_counts = [];
    if (!empty($events)) {
        $event_ids = array_map('intval', wp_list_pluck($events, 'id'));
        $placeholders = implode(',', array_fill(0, count($event_ids), '%d'));
        $count_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT event_id, COUNT(*) as cnt
             FROM {$photos_table}
             WHERE business_id = %d AND event_id IN ($placeholders)
             GROUP BY event_id",
            array_merge([$business_id], $event_ids)
        ));
        if (!empty($count_rows)) {
            foreach ($count_rows as $row) {
                $photo_counts[(int) $row->event_id] = (int) $row->cnt;
            }
        }
    }

    $nonce = wp_create_nonce('pb_nonce');

    $event_page_url = pb_get_frontend_page_url('photo-booth-upload', 'pb_upload');

    ob_start();
    ?>
    <div class="bntm-form-section" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div>
            <h3 style="margin:0;">Events</h3>
            <p style="margin:4px 0 0;color:#6b7280;font-size:13px;">Create and manage photo booth events. Only one event can be active at a time.</p>
        </div>
        <button class="bntm-btn-primary" onclick="pbOpenModal('pb-add-event-modal')">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:4px;"><line x1="12" y1="5" x2="12" y2="19" stroke-width="2"/><line x1="5" y1="12" x2="19" y2="12" stroke-width="2"/></svg>
            Add Event
        </button>
    </div>

    <div class="bntm-table-wrapper">
        <table class="bntm-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Date</th>
                    <th>Photos</th>
                    <th>Max Photos</th>
                    <th>Status</th>
                    <th>QR / URL</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($events)): ?>
                <tr><td colspan="7" style="text-align:center;color:#6b7280;">No events yet. Create your first event to get started.</td></tr>
                <?php else: foreach ($events as $ev):
                    $upload_url = $event_page_url !== '#'
                        ? add_query_arg('event_id', $ev->rand_id, $event_page_url)
                        : '#';
                ?>
                <tr>
                    <td><strong><?php echo esc_html($ev->title); ?></strong></td>
                    <td><?php echo $ev->event_date ? esc_html(date('M d, Y', strtotime($ev->event_date))) : '—'; ?></td>
                    <td><?php echo number_format(isset($photo_counts[(int) $ev->id]) ? $photo_counts[(int) $ev->id] : 0); ?></td>
                    <td><?php echo $ev->max_photos > 0 ? number_format($ev->max_photos) : 'Unlimited'; ?></td>
                    <td>
                        <span class="pb-status-badge pb-status-<?php echo esc_attr($ev->status); ?>">
                            <?php echo ucfirst(esc_html($ev->status)); ?>
                        </span>
                    </td>
                    <td>
                        <button class="bntm-btn-secondary bntm-btn-small"
                                onclick="pbCopyUrl('<?php echo esc_js($upload_url); ?>', document.getElementById('pb-events-msg'))">
                            Copy URL
                        </button>
                    </td>
                    <td>
                        <button class="bntm-btn-secondary bntm-btn-small pb-edit-event-btn"
                                data-id="<?php echo $ev->id; ?>"
                                data-rand="<?php echo esc_attr($ev->rand_id); ?>"
                                data-title="<?php echo esc_attr($ev->title); ?>"
                                data-desc="<?php echo esc_attr($ev->description); ?>"
                                data-date="<?php echo esc_attr($ev->event_date); ?>"
                                data-max="<?php echo esc_attr($ev->max_photos); ?>"
                                data-status="<?php echo esc_attr($ev->status); ?>">
                            Edit
                        </button>
                        <?php if ($ev->status !== 'active'): ?>
                        <button class="bntm-btn-primary bntm-btn-small pb-toggle-event-btn"
                                data-id="<?php echo $ev->id; ?>" data-action="activate"
                                data-nonce="<?php echo $nonce; ?>">
                            Activate
                        </button>
                        <?php else: ?>
                        <button class="bntm-btn-secondary bntm-btn-small pb-toggle-event-btn"
                                data-id="<?php echo $ev->id; ?>" data-action="deactivate"
                                data-nonce="<?php echo $nonce; ?>">
                            Deactivate
                        </button>
                        <?php endif; ?>
                        <button class="bntm-btn-danger bntm-btn-small pb-delete-event-btn"
                                data-id="<?php echo $ev->id; ?>"
                                data-count="<?php echo isset($photo_counts[(int) $ev->id]) ? $photo_counts[(int) $ev->id] : 0; ?>"
                                data-nonce="<?php echo $nonce; ?>">
                            Delete
                        </button>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <div id="pb-events-msg" style="margin-top:10px;"></div>

    <!-- Add Event Modal -->
    <div class="pb-modal-overlay" id="pb-add-event-modal">
        <div class="pb-modal">
            <button class="pb-modal-close" onclick="pbCloseModal('pb-add-event-modal')">&times;</button>
            <h3>Add New Event</h3>
            <div class="bntm-form-group">
                <label>Event Title *</label>
                <input type="text" id="pb-add-title" placeholder="e.g. John & Jane's Wedding">
            </div>
            <div class="bntm-form-group">
                <label>Description</label>
                <textarea id="pb-add-desc" rows="2" placeholder="Optional event description"></textarea>
            </div>
            <div class="bntm-form-group">
                <label>Event Date</label>
                <input type="date" id="pb-add-date">
            </div>
            <div class="bntm-form-group">
                <label>Max Photos (0 = unlimited)</label>
                <input type="number" id="pb-add-max" value="0" min="0">
            </div>
            <div style="display:flex;gap:10px;margin-top:8px;">
                <button class="bntm-btn-primary" id="pb-add-event-submit" data-nonce="<?php echo $nonce; ?>">Create Event</button>
                <button class="bntm-btn-secondary" onclick="pbCloseModal('pb-add-event-modal')">Cancel</button>
            </div>
            <div id="pb-add-event-msg" style="margin-top:10px;"></div>
        </div>
    </div>

    <!-- Edit Event Modal -->
    <div class="pb-modal-overlay" id="pb-edit-event-modal">
        <div class="pb-modal">
            <button class="pb-modal-close" onclick="pbCloseModal('pb-edit-event-modal')">&times;</button>
            <h3>Edit Event</h3>
            <input type="hidden" id="pb-edit-event-id">
            <div class="bntm-form-group">
                <label>Event Title *</label>
                <input type="text" id="pb-edit-title">
            </div>
            <div class="bntm-form-group">
                <label>Description</label>
                <textarea id="pb-edit-desc" rows="2"></textarea>
            </div>
            <div class="bntm-form-group">
                <label>Event Date</label>
                <input type="date" id="pb-edit-date">
            </div>
            <div class="bntm-form-group">
                <label>Max Photos (0 = unlimited)</label>
                <input type="number" id="pb-edit-max" min="0">
            </div>
            <div style="display:flex;gap:10px;margin-top:8px;">
                <button class="bntm-btn-primary" id="pb-edit-event-submit" data-nonce="<?php echo $nonce; ?>">Save Changes</button>
                <button class="bntm-btn-secondary" onclick="pbCloseModal('pb-edit-event-modal')">Cancel</button>
            </div>
            <div id="pb-edit-event-msg" style="margin-top:10px;"></div>
        </div>
    </div>

    <style>
    .pb-status-badge { padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600; }
    .pb-status-active   { background:#dcfce7;color:#16a34a; }
    .pb-status-inactive { background:#f3f4f6;color:#6b7280; }
    </style>

    <script>
    (function() {
        // Add event
        document.getElementById('pb-add-event-submit').addEventListener('click', function() {
            const title = document.getElementById('pb-add-title').value.trim();
            if (!title) { alert('Event title is required'); return; }
            this.disabled = true; this.textContent = 'Creating...';
            const fd = new FormData();
            fd.append('action', 'pb_add_event');
            fd.append('nonce', this.dataset.nonce);
            fd.append('title', title);
            fd.append('description', document.getElementById('pb-add-desc').value);
            fd.append('event_date', document.getElementById('pb-add-date').value);
            fd.append('max_photos', document.getElementById('pb-add-max').value);
            fetch(ajaxurl, {method:'POST',body:fd}).then(r=>r.json()).then(json=>{
                const msg = document.getElementById('pb-add-event-msg');
                msg.innerHTML = '<div class="bntm-notice bntm-notice-'+(json.success?'success':'error')+'">'+json.data.message+'</div>';
                if (json.success) setTimeout(()=>location.reload(), 1200);
                else { this.disabled = false; this.textContent = 'Create Event'; }
            }).catch(()=>{
                const msg = document.getElementById('pb-add-event-msg');
                msg.innerHTML = '<div class="bntm-notice bntm-notice-error">Request failed. Please refresh and try again.</div>';
                this.disabled = false; this.textContent = 'Create Event';
            });
        });

        // Open edit modal
        document.querySelectorAll('.pb-edit-event-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('pb-edit-event-id').value = this.dataset.id;
                document.getElementById('pb-edit-title').value    = this.dataset.title;
                document.getElementById('pb-edit-desc').value     = this.dataset.desc;
                document.getElementById('pb-edit-date').value     = this.dataset.date;
                document.getElementById('pb-edit-max').value      = this.dataset.max;
                pbOpenModal('pb-edit-event-modal');
            });
        });

        // Save edit
        document.getElementById('pb-edit-event-submit').addEventListener('click', function() {
            const title = document.getElementById('pb-edit-title').value.trim();
            if (!title) { alert('Event title is required'); return; }
            this.disabled = true; this.textContent = 'Saving...';
            const fd = new FormData();
            fd.append('action', 'pb_edit_event');
            fd.append('nonce', this.dataset.nonce);
            fd.append('event_id', document.getElementById('pb-edit-event-id').value);
            fd.append('title', title);
            fd.append('description', document.getElementById('pb-edit-desc').value);
            fd.append('event_date', document.getElementById('pb-edit-date').value);
            fd.append('max_photos', document.getElementById('pb-edit-max').value);
            fetch(ajaxurl, {method:'POST',body:fd}).then(r=>r.json()).then(json=>{
                const msg = document.getElementById('pb-edit-event-msg');
                msg.innerHTML = '<div class="bntm-notice bntm-notice-'+(json.success?'success':'error')+'">'+json.data.message+'</div>';
                if (json.success) setTimeout(()=>location.reload(), 1200);
                else { this.disabled = false; this.textContent = 'Save Changes'; }
            }).catch(()=>{
                const msg = document.getElementById('pb-edit-event-msg');
                msg.innerHTML = '<div class="bntm-notice bntm-notice-error">Request failed. Please refresh and try again.</div>';
                this.disabled = false; this.textContent = 'Save Changes';
            });
        });

        // Toggle status
        document.querySelectorAll('.pb-toggle-event-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const action = this.dataset.action;
                if (!confirm(action === 'activate' ? 'Activate this event? This will deactivate all other events.' : 'Deactivate this event?')) return;
                this.disabled = true;
                const fd = new FormData();
                fd.append('action', 'pb_toggle_event_status');
                fd.append('nonce', this.dataset.nonce);
                fd.append('event_id', this.dataset.id);
                fd.append('new_status', action === 'activate' ? 'active' : 'inactive');
                fetch(ajaxurl, {method:'POST',body:fd}).then(r=>r.json()).then(json=>{
                    if (json.success) location.reload();
                    else { pbToast(json.data.message, 'error'); this.disabled = false; }
                });
            });
        });

        // Delete event
        document.querySelectorAll('.pb-delete-event-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const count = parseInt(this.dataset.count);
                const msg = count > 0 ? `This event has ${count} photo(s). Deleting it will also remove all associated photos. Continue?` : 'Delete this event?';
                if (!confirm(msg)) return;
                this.disabled = true;
                const fd = new FormData();
                fd.append('action', 'pb_delete_event');
                fd.append('nonce', this.dataset.nonce);
                fd.append('event_id', this.dataset.id);
                fetch(ajaxurl, {method:'POST',body:fd}).then(r=>r.json()).then(json=>{
                    if (json.success) location.reload();
                    else { pbToast(json.data.message, 'error'); this.disabled = false; }
                });
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ============================================================
// TAB: PROMPTS
// ============================================================

function pb_prompts_tab($business_id) {
    global $wpdb;
    $events_table  = $wpdb->prefix . 'pb_events';
    $prompts_table = $wpdb->prefix . 'pb_prompts';

    $events  = $wpdb->get_results($wpdb->prepare("SELECT id, title FROM {$events_table} WHERE business_id = %d ORDER BY created_at DESC", $business_id));
    $prompts = $wpdb->get_results($wpdb->prepare(
        "SELECT p.*, e.title as event_title FROM {$prompts_table} p
         LEFT JOIN {$events_table} e ON p.event_id = e.id
         WHERE p.business_id = %d ORDER BY p.event_id, p.sort_order ASC",
        $business_id
    ));

    $nonce = wp_create_nonce('pb_nonce');

    ob_start();
    ?>
    <div class="bntm-form-section" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div>
            <h3 style="margin:0;">Photo Prompts</h3>
            <p style="margin:4px 0 0;color:#6b7280;font-size:13px;">Prompts guide guests on what to photograph. Each event includes a Free Capture prompt automatically.</p>
        </div>
        <button class="bntm-btn-primary" onclick="pbOpenModal('pb-add-prompt-modal')">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:4px;"><line x1="12" y1="5" x2="12" y2="19" stroke-width="2"/><line x1="5" y1="12" x2="19" y2="12" stroke-width="2"/></svg>
            Add Prompt
        </button>
    </div>

    <div class="bntm-table-wrapper">
        <table class="bntm-table">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Prompt</th>
                    <th>Event</th>
                    <th>Status</th>
                    <th>Type</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($prompts)): ?>
                <tr><td colspan="6" style="text-align:center;color:#6b7280;">No prompts yet.</td></tr>
                <?php else: foreach ($prompts as $pr): ?>
                <tr>
                    <td style="text-align:center;">
                        <?php if (!$pr->is_free_capture): ?>
                        <span style="display:flex;gap:2px;justify-content:center;">
                            <button class="bntm-btn-secondary bntm-btn-small pb-reorder-btn" data-id="<?php echo $pr->id; ?>" data-dir="up" data-nonce="<?php echo $nonce; ?>" title="Move up">&#8593;</button>
                            <button class="bntm-btn-secondary bntm-btn-small pb-reorder-btn" data-id="<?php echo $pr->id; ?>" data-dir="down" data-nonce="<?php echo $nonce; ?>" title="Move down">&#8595;</button>
                        </span>
                        <?php else: ?>
                        <span style="color:#9ca3af;">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($pr->prompt_text); ?></td>
                    <td><?php echo esc_html($pr->event_title ?: '—'); ?></td>
                    <td>
                        <span class="pb-status-badge pb-status-<?php echo esc_attr($pr->status); ?>">
                            <?php echo ucfirst(esc_html($pr->status)); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($pr->is_free_capture): ?>
                        <span style="background:#ede9fe;color:#7c3aed;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;">Free Capture</span>
                        <?php else: ?>
                        <span style="background:#f3f4f6;color:#374151;padding:2px 8px;border-radius:20px;font-size:11px;">Guided</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!$pr->is_free_capture): ?>
                        <button class="bntm-btn-secondary bntm-btn-small pb-edit-prompt-btn"
                                data-id="<?php echo $pr->id; ?>"
                                data-text="<?php echo esc_attr($pr->prompt_text); ?>"
                                data-event="<?php echo esc_attr($pr->event_id); ?>">
                            Edit
                        </button>
                        <button class="bntm-btn-secondary bntm-btn-small pb-toggle-prompt-btn"
                                data-id="<?php echo $pr->id; ?>"
                                data-status="<?php echo esc_attr($pr->status); ?>"
                                data-nonce="<?php echo $nonce; ?>">
                            <?php echo $pr->status === 'active' ? 'Disable' : 'Enable'; ?>
                        </button>
                        <button class="bntm-btn-danger bntm-btn-small pb-delete-prompt-btn"
                                data-id="<?php echo $pr->id; ?>"
                                data-nonce="<?php echo $nonce; ?>">
                            Delete
                        </button>
                        <?php else: ?>
                        <span style="color:#9ca3af;font-size:12px;">Protected</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Add Prompt Modal -->
    <div class="pb-modal-overlay" id="pb-add-prompt-modal">
        <div class="pb-modal">
            <button class="pb-modal-close" onclick="pbCloseModal('pb-add-prompt-modal')">&times;</button>
            <h3>Add Prompt</h3>
            <div class="bntm-form-group">
                <label>Prompt Text *</label>
                <input type="text" id="pb-add-prompt-text" placeholder="e.g. Bride eating cake">
            </div>
            <div class="bntm-form-group">
                <label>Event *</label>
                <select id="pb-add-prompt-event">
                    <option value="">Select Event</option>
                    <?php foreach ($events as $ev): ?>
                    <option value="<?php echo $ev->id; ?>"><?php echo esc_html($ev->title); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex;gap:10px;margin-top:8px;">
                <button class="bntm-btn-primary" id="pb-add-prompt-submit" data-nonce="<?php echo $nonce; ?>">Add Prompt</button>
                <button class="bntm-btn-secondary" onclick="pbCloseModal('pb-add-prompt-modal')">Cancel</button>
            </div>
            <div id="pb-add-prompt-msg" style="margin-top:10px;"></div>
        </div>
    </div>

    <!-- Edit Prompt Modal -->
    <div class="pb-modal-overlay" id="pb-edit-prompt-modal">
        <div class="pb-modal">
            <button class="pb-modal-close" onclick="pbCloseModal('pb-edit-prompt-modal')">&times;</button>
            <h3>Edit Prompt</h3>
            <input type="hidden" id="pb-edit-prompt-id">
            <div class="bntm-form-group">
                <label>Prompt Text *</label>
                <input type="text" id="pb-edit-prompt-text">
            </div>
            <div class="bntm-form-group">
                <label>Event *</label>
                <select id="pb-edit-prompt-event">
                    <?php foreach ($events as $ev): ?>
                    <option value="<?php echo $ev->id; ?>"><?php echo esc_html($ev->title); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex;gap:10px;margin-top:8px;">
                <button class="bntm-btn-primary" id="pb-edit-prompt-submit" data-nonce="<?php echo $nonce; ?>">Save</button>
                <button class="bntm-btn-secondary" onclick="pbCloseModal('pb-edit-prompt-modal')">Cancel</button>
            </div>
            <div id="pb-edit-prompt-msg" style="margin-top:10px;"></div>
        </div>
    </div>

    <script>
    (function() {
        document.getElementById('pb-add-prompt-submit').addEventListener('click', function() {
            const text  = document.getElementById('pb-add-prompt-text').value.trim();
            const event = document.getElementById('pb-add-prompt-event').value;
            if (!text || !event) { alert('Prompt text and event are required'); return; }
            this.disabled = true; this.textContent = 'Adding...';
            const fd = new FormData();
            fd.append('action','pb_add_prompt'); fd.append('nonce',this.dataset.nonce);
            fd.append('prompt_text',text); fd.append('event_id',event);
            fetch(ajaxurl,{method:'POST',body:fd}).then(r=>r.json()).then(json=>{
                const msg = document.getElementById('pb-add-prompt-msg');
                msg.innerHTML='<div class="bntm-notice bntm-notice-'+(json.success?'success':'error')+'">'+json.data.message+'</div>';
                if(json.success) setTimeout(()=>location.reload(),1200);
                else { this.disabled=false; this.textContent='Add Prompt'; }
            });
        });

        document.querySelectorAll('.pb-edit-prompt-btn').forEach(btn=>{
            btn.addEventListener('click',function(){
                document.getElementById('pb-edit-prompt-id').value   = this.dataset.id;
                document.getElementById('pb-edit-prompt-text').value = this.dataset.text;
                document.getElementById('pb-edit-prompt-event').value= this.dataset.event;
                pbOpenModal('pb-edit-prompt-modal');
            });
        });

        document.getElementById('pb-edit-prompt-submit').addEventListener('click', function(){
            const text=document.getElementById('pb-edit-prompt-text').value.trim();
            if(!text){alert('Prompt text required');return;}
            this.disabled=true; this.textContent='Saving...';
            const fd=new FormData();
            fd.append('action','pb_edit_prompt'); fd.append('nonce',this.dataset.nonce);
            fd.append('prompt_id',document.getElementById('pb-edit-prompt-id').value);
            fd.append('prompt_text',text);
            fd.append('event_id',document.getElementById('pb-edit-prompt-event').value);
            fetch(ajaxurl,{method:'POST',body:fd}).then(r=>r.json()).then(json=>{
                const msg=document.getElementById('pb-edit-prompt-msg');
                msg.innerHTML='<div class="bntm-notice bntm-notice-'+(json.success?'success':'error')+'">'+json.data.message+'</div>';
                if(json.success) setTimeout(()=>location.reload(),1200);
                else{this.disabled=false;this.textContent='Save';}
            });
        });

        document.querySelectorAll('.pb-toggle-prompt-btn').forEach(btn=>{
            btn.addEventListener('click',function(){
                const newStatus=this.dataset.status==='active'?'inactive':'active';
                this.disabled=true;
                const fd=new FormData();
                fd.append('action','pb_toggle_prompt_status'); fd.append('nonce',this.dataset.nonce);
                fd.append('prompt_id',this.dataset.id); fd.append('new_status',newStatus);
                fetch(ajaxurl,{method:'POST',body:fd}).then(r=>r.json()).then(json=>{
                    if(json.success) location.reload();
                    else{pbToast(json.data.message,'error');this.disabled=false;}
                });
            });
        });

        document.querySelectorAll('.pb-delete-prompt-btn').forEach(btn=>{
            btn.addEventListener('click',function(){
                if(!confirm('Delete this prompt?')) return;
                this.disabled=true;
                const fd=new FormData();
                fd.append('action','pb_delete_prompt'); fd.append('nonce',this.dataset.nonce);
                fd.append('prompt_id',this.dataset.id);
                fetch(ajaxurl,{method:'POST',body:fd}).then(r=>r.json()).then(json=>{
                    if(json.success) location.reload();
                    else{pbToast(json.data.message,'error');this.disabled=false;}
                });
            });
        });

        document.querySelectorAll('.pb-reorder-btn').forEach(btn=>{
            btn.addEventListener('click',function(){
                this.disabled=true;
                const fd=new FormData();
                fd.append('action','pb_reorder_prompt'); fd.append('nonce',this.dataset.nonce);
                fd.append('prompt_id',this.dataset.id); fd.append('direction',this.dataset.dir);
                fetch(ajaxurl,{method:'POST',body:fd}).then(r=>r.json()).then(json=>{
                    if(json.success) location.reload();
                    else{pbToast(json.data.message,'error');this.disabled=false;}
                });
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ============================================================
// TAB: PHOTOS
// ============================================================

function pb_photos_tab($business_id) {
    global $wpdb;
    $events_table  = $wpdb->prefix . 'pb_events';
    $photos_table  = $wpdb->prefix . 'pb_photos';
    $prompts_table = $wpdb->prefix . 'pb_prompts';

    $filter_event  = isset($_GET['filter_event'])  ? intval($_GET['filter_event'])              : 0;
    $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']): '';

    $events = $wpdb->get_results($wpdb->prepare("SELECT id, title FROM {$events_table} WHERE business_id = %d ORDER BY created_at DESC", $business_id));

    $where = "WHERE ph.business_id = {$business_id}";
    if ($filter_event)  $where .= " AND ph.event_id = {$filter_event}";
    if ($filter_status) $where .= " AND ph.status = '" . esc_sql($filter_status) . "'";

    $photos = $wpdb->get_results("
        SELECT ph.*, e.title as event_title, pr.prompt_text
        FROM {$photos_table} ph
        LEFT JOIN {$events_table} e ON ph.event_id = e.id
        LEFT JOIN {$prompts_table} pr ON ph.prompt_id = pr.id
        {$where} ORDER BY ph.created_at DESC LIMIT 200
    ");

    $upload_dir = wp_upload_dir();
    $nonce = wp_create_nonce('pb_nonce');

    ob_start();
    ?>
    <div class="bntm-form-section">
        <h3>Photos</h3>
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px;">
            <select id="pb-filter-event" onchange="pbApplyPhotoFilter()">
                <option value="">All Events</option>
                <?php foreach ($events as $ev): ?>
                <option value="<?php echo $ev->id; ?>" <?php selected($filter_event, $ev->id); ?>><?php echo esc_html($ev->title); ?></option>
                <?php endforeach; ?>
            </select>
            <select id="pb-filter-status" onchange="pbApplyPhotoFilter()">
                <option value="" <?php selected($filter_status,''); ?>>All Statuses</option>
                <option value="approved" <?php selected($filter_status,'approved'); ?>>Approved</option>
                <option value="pending"  <?php selected($filter_status,'pending'); ?>>Pending</option>
                <option value="flagged"  <?php selected($filter_status,'flagged'); ?>>Flagged</option>
            </select>
        </div>

        <!-- Bulk actions -->
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;" id="pb-bulk-bar" style="display:none;">
            <span id="pb-bulk-count" style="line-height:34px;color:#6b7280;font-size:13px;"></span>
            <button class="bntm-btn-primary bntm-btn-small" onclick="pbBulkAction('approved')" data-nonce="<?php echo $nonce; ?>">Approve Selected</button>
            <button class="bntm-btn-secondary bntm-btn-small" onclick="pbBulkAction('flagged')" data-nonce="<?php echo $nonce; ?>">Flag Selected</button>
            <button class="bntm-btn-danger bntm-btn-small" onclick="pbBulkDelete()" data-nonce="<?php echo $nonce; ?>">Delete Selected</button>
        </div>

        <?php if (empty($photos)): ?>
            <p style="color:#6b7280;">No photos found with the selected filters.</p>
        <?php else: ?>
        <div class="pb-photo-admin-grid">
            <?php foreach ($photos as $photo):
                $img_url = $upload_dir['baseurl'] . '/' . $photo->file_path;
            ?>
            <div class="pb-admin-thumb" data-id="<?php echo $photo->id; ?>">
                <label class="pb-admin-check">
                    <input type="checkbox" class="pb-photo-check" value="<?php echo $photo->id; ?>" onchange="pbUpdateBulkBar()">
                </label>
                <img src="<?php echo esc_url($img_url); ?>" alt="" loading="lazy"
                     onclick="pbOpenAdminLightbox(<?php echo esc_js(json_encode(['url'=>$img_url,'name'=>$photo->guest_name,'prompt'=>$photo->prompt_text,'event'=>$photo->event_title,'time'=>$photo->created_at,'status'=>$photo->status,'id'=>$photo->id])); ?>)">
                <div class="pb-admin-thumb-status pb-status-dot <?php echo esc_attr($photo->status); ?>"></div>
                <div class="pb-admin-thumb-footer">
                    <span class="pb-status-badge pb-status-<?php echo esc_attr($photo->status); ?>" style="font-size:10px;"><?php echo ucfirst($photo->status); ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Admin Lightbox -->
    <div class="pb-lightbox" id="pb-admin-lightbox">
        <div class="pb-lightbox-inner" style="text-align:center;">
            <button class="pb-lightbox-close" onclick="document.getElementById('pb-admin-lightbox').classList.remove('open')">&times;</button>
            <img id="pb-admin-lb-img" src="" alt="" style="max-width:85vw;max-height:70vh;border-radius:8px;display:block;margin:0 auto;">
            <div style="background:rgba(255,255,255,0.1);border-radius:0 0 8px 8px;padding:14px 20px;color:#fff;text-align:left;">
                <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:10px;">
                    <div><small style="opacity:.7;">Guest</small><br><strong id="pb-admin-lb-name"></strong></div>
                    <div><small style="opacity:.7;">Prompt</small><br><strong id="pb-admin-lb-prompt"></strong></div>
                    <div><small style="opacity:.7;">Event</small><br><strong id="pb-admin-lb-event"></strong></div>
                    <div><small style="opacity:.7;">Time</small><br><strong id="pb-admin-lb-time"></strong></div>
                    <div><small style="opacity:.7;">Status</small><br><strong id="pb-admin-lb-status"></strong></div>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button class="bntm-btn-primary bntm-btn-small" id="pb-lb-approve" data-nonce="<?php echo $nonce; ?>">Approve</button>
                    <button class="bntm-btn-secondary bntm-btn-small" id="pb-lb-flag" data-nonce="<?php echo $nonce; ?>">Flag</button>
                    <button class="bntm-btn-danger bntm-btn-small" id="pb-lb-delete" data-nonce="<?php echo $nonce; ?>">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <style>
    .pb-photo-admin-grid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(130px,1fr)); gap: 10px;
    }
    .pb-admin-thumb {
        position: relative; aspect-ratio: 1; border-radius: 8px; overflow: hidden;
        cursor: pointer; background: #f3f4f6;
    }
    .pb-admin-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
    .pb-admin-check {
        position:absolute; top:6px; left:6px; z-index:2;
        background: rgba(255,255,255,0.85); border-radius:4px; padding:2px;
        cursor:pointer;
    }
    .pb-admin-thumb-status { position:absolute; top:6px; right:6px; }
    .pb-admin-thumb-footer {
        position:absolute; bottom:0; left:0; right:0; padding:4px 6px;
        background:linear-gradient(transparent,rgba(0,0,0,0.6));
    }
    </style>

    <script>
    (function(){
        let currentLbId = null;

        window.pbApplyPhotoFilter = function() {
            const ev = document.getElementById('pb-filter-event').value;
            const st = document.getElementById('pb-filter-status').value;
            let url = new URL(location.href);
            url.searchParams.set('tab','photos');
            if (ev) url.searchParams.set('filter_event', ev); else url.searchParams.delete('filter_event');
            if (st) url.searchParams.set('filter_status', st); else url.searchParams.delete('filter_status');
            location.href = url.toString();
        };

        window.pbUpdateBulkBar = function() {
            const count = document.querySelectorAll('.pb-photo-check:checked').length;
            const bar   = document.getElementById('pb-bulk-bar');
            document.getElementById('pb-bulk-count').textContent = count + ' selected';
            bar.style.display = count > 0 ? 'flex' : 'none';
        };

        window.pbBulkAction = function(status) {
            const ids = Array.from(document.querySelectorAll('.pb-photo-check:checked')).map(c=>c.value);
            if (!ids.length) return;
            if (!confirm('Update '+ids.length+' photo(s) to '+status+'?')) return;
            const fd = new FormData();
            fd.append('action','pb_bulk_action_photos');
            fd.append('nonce','<?php echo $nonce; ?>');
            fd.append('photo_ids', JSON.stringify(ids));
            fd.append('bulk_action', status);
            fetch(ajaxurl,{method:'POST',body:fd}).then(r=>r.json()).then(json=>{
                if(json.success) location.reload();
                else pbToast(json.data.message,'error');
            });
        };

        window.pbBulkDelete = function() {
            const ids = Array.from(document.querySelectorAll('.pb-photo-check:checked')).map(c=>c.value);
            if (!ids.length) return;
            if (!confirm('Permanently delete '+ids.length+' photo(s)?')) return;
            const fd = new FormData();
            fd.append('action','pb_bulk_action_photos');
            fd.append('nonce','<?php echo $nonce; ?>');
            fd.append('photo_ids', JSON.stringify(ids));
            fd.append('bulk_action','delete');
            fetch(ajaxurl,{method:'POST',body:fd}).then(r=>r.json()).then(json=>{
                if(json.success) location.reload();
                else pbToast(json.data.message,'error');
            });
        };

        window.pbOpenAdminLightbox = function(data) {
            currentLbId = data.id;
            document.getElementById('pb-admin-lb-img').src       = data.url;
            document.getElementById('pb-admin-lb-name').textContent   = data.name || 'Anonymous';
            document.getElementById('pb-admin-lb-prompt').textContent = data.prompt || 'Free Capture';
            document.getElementById('pb-admin-lb-event').textContent  = data.event || '—';
            document.getElementById('pb-admin-lb-time').textContent   = data.time;
            document.getElementById('pb-admin-lb-status').textContent = data.status;
            document.getElementById('pb-admin-lightbox').classList.add('open');
        };

        function lbAction(action, status) {
            if (!currentLbId) return;
            if (action === 'delete' && !confirm('Delete this photo permanently?')) return;
            const fd = new FormData();
            if (action === 'delete') {
                fd.append('action','pb_delete_photo'); fd.append('photo_id',currentLbId);
            } else {
                fd.append('action','pb_update_photo_status'); fd.append('photo_id',currentLbId); fd.append('status',status);
            }
            fd.append('nonce','<?php echo $nonce; ?>');
            fetch(ajaxurl,{method:'POST',body:fd}).then(r=>r.json()).then(json=>{
                if(json.success){document.getElementById('pb-admin-lightbox').classList.remove('open');location.reload();}
                else pbToast(json.data.message,'error');
            });
        }
        document.getElementById('pb-lb-approve').addEventListener('click',()=>lbAction('status','approved'));
        document.getElementById('pb-lb-flag').addEventListener('click',()=>lbAction('status','flagged'));
        document.getElementById('pb-lb-delete').addEventListener('click',()=>lbAction('delete'));
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ============================================================
// TAB: DISPLAY SETTINGS
// ============================================================

function pb_display_settings_tab($business_id) {
    $nonce         = wp_create_nonce('pb_nonce');
    $layout        = bntm_get_setting('pb_display_layout',     'slideshow');
    $speed         = bntm_get_setting('pb_display_speed',      '5');
    $show_name     = bntm_get_setting('pb_display_show_name',  '1');
    $poll_interval = bntm_get_setting('pb_display_poll',       '10');
    $bg_color      = bntm_get_setting('pb_display_bg_color',   '#111111');

    $display_page = get_page_by_path('photo-display');
    $display_url  = $display_page ? get_permalink($display_page->ID) : '#';

    ob_start();
    ?>
    <div class="bntm-form-section">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
            <div>
                <h3 style="margin:0;">Display &amp; Slideshow Settings</h3>
                <p style="margin:4px 0 0;color:#6b7280;font-size:13px;">Configure how photos appear on TV screens and projectors.</p>
            </div>
            <a href="<?php echo esc_url($display_url); ?>" target="_blank" class="bntm-btn-secondary bntm-btn-small">
                Preview Display Page
            </a>
        </div>

        <div class="bntm-form-group">
            <label>Layout Mode</label>
            <select id="pb-disp-layout">
                <option value="slideshow" <?php selected($layout,'slideshow'); ?>>Slideshow (auto-advancing)</option>
                <option value="grid"      <?php selected($layout,'grid'); ?>>Grid (all photos visible)</option>
            </select>
        </div>
        <div class="bntm-form-group">
            <label>Slide Duration (seconds)</label>
            <input type="number" id="pb-disp-speed" value="<?php echo esc_attr($speed); ?>" min="2" max="60">
            <small style="color:#6b7280;">How long each photo is shown in slideshow mode.</small>
        </div>
        <div class="bntm-form-group">
            <label>Gallery Poll Interval (seconds)</label>
            <input type="number" id="pb-disp-poll" value="<?php echo esc_attr($poll_interval); ?>" min="5" max="120">
            <small style="color:#6b7280;">How often the display checks for new photos.</small>
        </div>
        <div class="bntm-form-group">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                <input type="checkbox" id="pb-disp-show-name" <?php checked($show_name,'1'); ?>>
                Show uploader name on display
            </label>
        </div>
        <div class="bntm-form-group">
            <label>Display Background Color</label>
            <input type="color" id="pb-disp-bg-color" value="<?php echo esc_attr($bg_color); ?>">
        </div>

        <button class="bntm-btn-primary" id="pb-save-display-btn" data-nonce="<?php echo $nonce; ?>">Save Display Settings</button>
        <div id="pb-display-msg" style="margin-top:12px;"></div>
    </div>

    <script>
    (function(){
        document.getElementById('pb-save-display-btn').addEventListener('click', function(){
            this.disabled=true; this.textContent='Saving...';
            const fd=new FormData();
            fd.append('action','pb_save_display_settings'); fd.append('nonce',this.dataset.nonce);
            fd.append('layout',   document.getElementById('pb-disp-layout').value);
            fd.append('speed',    document.getElementById('pb-disp-speed').value);
            fd.append('poll',     document.getElementById('pb-disp-poll').value);
            fd.append('show_name',document.getElementById('pb-disp-show-name').checked?'1':'0');
            fd.append('bg_color', document.getElementById('pb-disp-bg-color').value);
            fetch(ajaxurl,{method:'POST',body:fd}).then(r=>r.json()).then(json=>{
                document.getElementById('pb-display-msg').innerHTML=
                    '<div class="bntm-notice bntm-notice-'+(json.success?'success':'error')+'">'+json.data.message+'</div>';
                this.disabled=false; this.textContent='Save Display Settings';
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ============================================================
// TAB: SETTINGS
// ============================================================

function pb_settings_tab($business_id) {
    $nonce         = wp_create_nonce('pb_nonce');
    $max_size      = bntm_get_setting('pb_max_upload_size',  '10');
    $max_width     = bntm_get_setting('pb_max_image_width',  '1280');
    $moderation    = bntm_get_setting('pb_moderation_mode',  'auto');
    $guest_name    = bntm_get_setting('pb_guest_name_mode',  'optional');
    $cooldown      = bntm_get_setting('pb_upload_cooldown',  '30');
    $allowed_types = bntm_get_setting('pb_allowed_types',    'jpeg,png,webp');
    $pwa_name      = bntm_get_setting('pb_pwa_name',         'Photo Booth');
    $pwa_color     = bntm_get_setting('pb_pwa_theme_color',  '#6366f1');

    ob_start();
    ?>
    <div class="bntm-form-section">
        <h3>Upload Settings</h3>
        <div class="bntm-form-group">
            <label>Max Upload File Size (MB)</label>
            <input type="number" id="pb-max-size" value="<?php echo esc_attr($max_size); ?>" min="1" max="50">
        </div>
        <div class="bntm-form-group">
            <label>Max Image Width (px, server-side resize)</label>
            <input type="number" id="pb-max-width" value="<?php echo esc_attr($max_width); ?>" min="640" max="4096">
            <small style="color:#6b7280;">Images wider than this are resized on upload.</small>
        </div>
        <div class="bntm-form-group">
            <label>Allowed File Types</label>
            <input type="text" id="pb-allowed-types" value="<?php echo esc_attr($allowed_types); ?>">
            <small style="color:#6b7280;">Comma-separated: jpeg, png, webp</small>
        </div>
        <div class="bntm-form-group">
            <label>Upload Cooldown (seconds per guest IP)</label>
            <input type="number" id="pb-cooldown" value="<?php echo esc_attr($cooldown); ?>" min="0" max="3600">
            <small style="color:#6b7280;">Minimum seconds between uploads from the same IP. Set 0 to disable.</small>
        </div>
    </div>

    <div class="bntm-form-section">
        <h3>Moderation</h3>
        <div class="bntm-form-group">
            <label>Photo Approval Mode</label>
            <select id="pb-moderation">
                <option value="auto"   <?php selected($moderation,'auto'); ?>>Auto-approve (photos appear in gallery immediately)</option>
                <option value="manual" <?php selected($moderation,'manual'); ?>>Manual approval (review before showing)</option>
            </select>
        </div>
    </div>

    <div class="bntm-form-section">
        <h3>Guest Identity</h3>
        <div class="bntm-form-group">
            <label>Guest Name Field</label>
            <select id="pb-guest-name">
                <option value="hidden"   <?php selected($guest_name,'hidden'); ?>>Hidden (no name asked)</option>
                <option value="optional" <?php selected($guest_name,'optional'); ?>>Optional (guest can skip)</option>
                <option value="required" <?php selected($guest_name,'required'); ?>>Required (must enter name)</option>
            </select>
        </div>
    </div>

    <div class="bntm-form-section">
        <h3>PWA Settings</h3>
        <div class="bntm-form-group">
            <label>App Name (shown on home screen)</label>
            <input type="text" id="pb-pwa-name" value="<?php echo esc_attr($pwa_name); ?>">
        </div>
        <div class="bntm-form-group">
            <label>Theme Color</label>
            <input type="color" id="pb-pwa-color" value="<?php echo esc_attr($pwa_color); ?>">
        </div>
    </div>

    <div class="bntm-form-section" style="background:transparent;border:none;padding:0;">
        <button class="bntm-btn-primary" id="pb-save-settings-btn" data-nonce="<?php echo $nonce; ?>">Save All Settings</button>
        <div id="pb-settings-msg" style="margin-top:12px;"></div>
    </div>

    <script>
    (function(){
        document.getElementById('pb-save-settings-btn').addEventListener('click', function(){
            this.disabled=true; this.textContent='Saving...';
            const fd=new FormData();
            fd.append('action','pb_save_settings'); fd.append('nonce',this.dataset.nonce);
            fd.append('max_upload_size',  document.getElementById('pb-max-size').value);
            fd.append('max_image_width',  document.getElementById('pb-max-width').value);
            fd.append('allowed_types',    document.getElementById('pb-allowed-types').value);
            fd.append('cooldown',         document.getElementById('pb-cooldown').value);
            fd.append('moderation_mode',  document.getElementById('pb-moderation').value);
            fd.append('guest_name_mode',  document.getElementById('pb-guest-name').value);
            fd.append('pwa_name',         document.getElementById('pb-pwa-name').value);
            fd.append('pwa_theme_color',  document.getElementById('pb-pwa-color').value);
            fetch(ajaxurl,{method:'POST',body:fd}).then(r=>r.json()).then(json=>{
                document.getElementById('pb-settings-msg').innerHTML=
                    '<div class="bntm-notice bntm-notice-'+(json.success?'success':'error')+'">'+json.data.message+'</div>';
                this.disabled=false; this.textContent='Save All Settings';
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ============================================================
// AJAX HANDLERS — DASHBOARD
// ============================================================

function bntm_ajax_pb_add_event() {
    check_ajax_referer('pb_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message' => 'Unauthorized']); }

    bntm_pb_ensure_tables();

    global $wpdb;
    $business_id = get_current_user_id();
    $title       = sanitize_text_field($_POST['title']);
    $description = sanitize_textarea_field($_POST['description']);
    $event_date_raw = sanitize_text_field($_POST['event_date']);
    $event_date  = $event_date_raw !== '' ? $event_date_raw : null;
    $max_photos  = max(0, intval($_POST['max_photos']));

    if (empty($title)) { wp_send_json_error(['message' => 'Event title is required']); }
    if ($event_date !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date)) {
        wp_send_json_error(['message' => 'Invalid event date format']);
    }

    $rand_id = bntm_rand_id();
    $result  = $wpdb->insert(
        $wpdb->prefix . 'pb_events',
        ['rand_id' => $rand_id, 'business_id' => $business_id, 'title' => $title,
         'description' => $description, 'event_date' => $event_date, 'max_photos' => $max_photos, 'status' => 'inactive'],
        ['%s','%d','%s','%s','%s','%d','%s']
    );

    if (!$result) {
        wp_send_json_error(['message' => 'Failed to create event' . (!empty($wpdb->last_error) ? ': ' . $wpdb->last_error : '')]);
    }

    $event_id = $wpdb->insert_id;
    // Auto-create Free Capture prompt
    $wpdb->insert(
        $wpdb->prefix . 'pb_prompts',
        ['rand_id' => bntm_rand_id(), 'business_id' => $business_id, 'event_id' => $event_id,
         'prompt_text' => 'Free Capture', 'sort_order' => 0, 'status' => 'active', 'is_free_capture' => 1],
        ['%s','%d','%d','%s','%d','%s','%d']
    );

    wp_send_json_success(['message' => 'Event created successfully!']);
}

function bntm_ajax_pb_edit_event() {
    check_ajax_referer('pb_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message' => 'Unauthorized']); }

    global $wpdb;
    $business_id = get_current_user_id();
    $event_id    = intval($_POST['event_id']);
    $title       = sanitize_text_field($_POST['title']);
    $description = sanitize_textarea_field($_POST['description']);
    $event_date_raw = sanitize_text_field($_POST['event_date']);
    $event_date  = $event_date_raw !== '' ? $event_date_raw : null;
    $max_photos  = max(0, intval($_POST['max_photos']));

    if (empty($title)) { wp_send_json_error(['message' => 'Event title is required']); }
    if ($event_date !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date)) {
        wp_send_json_error(['message' => 'Invalid event date format']);
    }

    $result = $wpdb->update(
        $wpdb->prefix . 'pb_events',
        ['title' => $title, 'description' => $description,
         'event_date' => $event_date, 'max_photos' => $max_photos],
        ['id' => $event_id, 'business_id' => $business_id],
        ['%s','%s','%s','%d'], ['%d','%d']
    );

    if ($result === false) {
        wp_send_json_error(['message' => 'Failed to update event' . (!empty($wpdb->last_error) ? ': ' . $wpdb->last_error : '')]);
    }
    wp_send_json_success(['message' => 'Event updated successfully!']);
}

function bntm_ajax_pb_delete_event() {
    check_ajax_referer('pb_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message' => 'Unauthorized']); }

    global $wpdb;
    $business_id = get_current_user_id();
    $event_id    = intval($_POST['event_id']);

    // Delete photos from filesystem
    $photos = $wpdb->get_results($wpdb->prepare(
        "SELECT file_path FROM {$wpdb->prefix}pb_photos WHERE event_id = %d AND business_id = %d",
        $event_id, $business_id
    ));
    $upload_dir = wp_upload_dir();
    foreach ($photos as $photo) {
        $file = $upload_dir['basedir'] . '/' . $photo->file_path;
        if (file_exists($file)) @unlink($file);
    }

    $wpdb->delete($wpdb->prefix . 'pb_photos',  ['event_id' => $event_id, 'business_id' => $business_id], ['%d','%d']);
    $wpdb->delete($wpdb->prefix . 'pb_prompts', ['event_id' => $event_id, 'business_id' => $business_id], ['%d','%d']);
    $result = $wpdb->delete($wpdb->prefix . 'pb_events', ['id' => $event_id, 'business_id' => $business_id], ['%d','%d']);

    if ($result) wp_send_json_success(['message' => 'Event deleted successfully']);
    else         wp_send_json_error(['message' => 'Failed to delete event']);
}

function bntm_ajax_pb_toggle_event_status() {
    check_ajax_referer('pb_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message' => 'Unauthorized']); }

    global $wpdb;
    $business_id = get_current_user_id();
    $event_id    = intval($_POST['event_id']);
    $new_status  = sanitize_text_field($_POST['new_status']);

    if (!in_array($new_status, ['active','inactive'])) { wp_send_json_error(['message' => 'Invalid status']); }

    $wpdb->query('START TRANSACTION');
    try {
        if ($new_status === 'active') {
            // Deactivate all other events
            $wpdb->update($wpdb->prefix . 'pb_events', ['status' => 'inactive'],
                ['status' => 'active', 'business_id' => $business_id], ['%s'], ['%s','%d']);
        }
        $r = $wpdb->update($wpdb->prefix . 'pb_events', ['status' => $new_status],
            ['id' => $event_id, 'business_id' => $business_id], ['%s'], ['%d','%d']);
        if ($r === false) throw new Exception('Update failed');
        $wpdb->query('COMMIT');
        wp_send_json_success(['message' => 'Event status updated']);
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        wp_send_json_error(['message' => 'Failed to update status']);
    }
}

function bntm_ajax_pb_add_prompt() {
    check_ajax_referer('pb_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message' => 'Unauthorized']); }

    global $wpdb;
    $business_id = get_current_user_id();
    $prompt_text = sanitize_text_field($_POST['prompt_text']);
    $event_id    = intval($_POST['event_id']);

    if (empty($prompt_text) || !$event_id) { wp_send_json_error(['message' => 'Prompt text and event are required']); }

    $max_order = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT MAX(sort_order) FROM {$wpdb->prefix}pb_prompts WHERE event_id = %d", $event_id
    ));

    $result = $wpdb->insert(
        $wpdb->prefix . 'pb_prompts',
        ['rand_id' => bntm_rand_id(), 'business_id' => $business_id, 'event_id' => $event_id,
         'prompt_text' => $prompt_text, 'sort_order' => $max_order + 1, 'status' => 'active', 'is_free_capture' => 0],
        ['%s','%d','%d','%s','%d','%s','%d']
    );

    if ($result) wp_send_json_success(['message' => 'Prompt added successfully!']);
    else         wp_send_json_error(['message' => 'Failed to add prompt']);
}

function bntm_ajax_pb_edit_prompt() {
    check_ajax_referer('pb_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message' => 'Unauthorized']); }

    global $wpdb;
    $business_id = get_current_user_id();
    $prompt_id   = intval($_POST['prompt_id']);
    $prompt_text = sanitize_text_field($_POST['prompt_text']);
    $event_id    = intval($_POST['event_id']);

    $result = $wpdb->update(
        $wpdb->prefix . 'pb_prompts',
        ['prompt_text' => $prompt_text, 'event_id' => $event_id],
        ['id' => $prompt_id, 'business_id' => $business_id, 'is_free_capture' => 0],
        ['%s','%d'], ['%d','%d','%d']
    );

    if ($result !== false) wp_send_json_success(['message' => 'Prompt updated']);
    else                   wp_send_json_error(['message' => 'Failed to update prompt']);
}

function bntm_ajax_pb_delete_prompt() {
    check_ajax_referer('pb_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message' => 'Unauthorized']); }

    global $wpdb;
    $business_id = get_current_user_id();
    $prompt_id   = intval($_POST['prompt_id']);

    $result = $wpdb->delete(
        $wpdb->prefix . 'pb_prompts',
        ['id' => $prompt_id, 'business_id' => $business_id, 'is_free_capture' => 0],
        ['%d','%d','%d']
    );

    if ($result) wp_send_json_success(['message' => 'Prompt deleted']);
    else         wp_send_json_error(['message' => 'Cannot delete this prompt']);
}

function bntm_ajax_pb_toggle_prompt_status() {
    check_ajax_referer('pb_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message' => 'Unauthorized']); }

    global $wpdb;
    $business_id = get_current_user_id();
    $prompt_id   = intval($_POST['prompt_id']);
    $new_status  = sanitize_text_field($_POST['new_status']);

    if (!in_array($new_status, ['active','inactive'])) { wp_send_json_error(['message' => 'Invalid status']); }

    $result = $wpdb->update(
        $wpdb->prefix . 'pb_prompts',
        ['status' => $new_status],
        ['id' => $prompt_id, 'business_id' => $business_id],
        ['%s'], ['%d','%d']
    );

    if ($result !== false) wp_send_json_success(['message' => 'Status updated']);
    else                   wp_send_json_error(['message' => 'Failed to update status']);
}

function bntm_ajax_pb_reorder_prompt() {
    check_ajax_referer('pb_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message' => 'Unauthorized']); }

    global $wpdb;
    $business_id = get_current_user_id();
    $prompt_id   = intval($_POST['prompt_id']);
    $direction   = sanitize_text_field($_POST['direction']);

    $current = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}pb_prompts WHERE id = %d AND business_id = %d", $prompt_id, $business_id
    ));
    if (!$current) { wp_send_json_error(['message' => 'Prompt not found']); }

    if ($direction === 'up') {
        $swap = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}pb_prompts WHERE event_id = %d AND sort_order < %d AND is_free_capture = 0 ORDER BY sort_order DESC LIMIT 1",
            $current->event_id, $current->sort_order
        ));
    } else {
        $swap = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}pb_prompts WHERE event_id = %d AND sort_order > %d AND is_free_capture = 0 ORDER BY sort_order ASC LIMIT 1",
            $current->event_id, $current->sort_order
        ));
    }

    if (!$swap) { wp_send_json_success(['message' => 'Already at limit']); }

    $wpdb->update($wpdb->prefix . 'pb_prompts', ['sort_order' => $swap->sort_order],  ['id' => $current->id], ['%d'], ['%d']);
    $wpdb->update($wpdb->prefix . 'pb_prompts', ['sort_order' => $current->sort_order],['id' => $swap->id],    ['%d'], ['%d']);

    wp_send_json_success(['message' => 'Reordered']);
}

function bntm_ajax_pb_update_photo_status() {
    check_ajax_referer('pb_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message' => 'Unauthorized']); }

    global $wpdb;
    $business_id = get_current_user_id();
    $photo_id    = intval($_POST['photo_id']);
    $status      = sanitize_text_field($_POST['status']);

    if (!in_array($status, ['approved','pending','flagged'])) { wp_send_json_error(['message' => 'Invalid status']); }

    $result = $wpdb->update(
        $wpdb->prefix . 'pb_photos',
        ['status' => $status],
        ['id' => $photo_id, 'business_id' => $business_id],
        ['%s'], ['%d','%d']
    );

    if ($result !== false) wp_send_json_success(['message' => 'Photo status updated']);
    else                   wp_send_json_error(['message' => 'Failed to update status']);
}

function bntm_ajax_pb_delete_photo() {
    check_ajax_referer('pb_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message' => 'Unauthorized']); }

    global $wpdb;
    $business_id = get_current_user_id();
    $photo_id    = intval($_POST['photo_id']);

    $photo = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}pb_photos WHERE id = %d AND business_id = %d", $photo_id, $business_id
    ));
    if (!$photo) { wp_send_json_error(['message' => 'Photo not found']); }

    $upload_dir = wp_upload_dir();
    $file = $upload_dir['basedir'] . '/' . $photo->file_path;
    if (file_exists($file)) @unlink($file);

    $result = $wpdb->delete($wpdb->prefix . 'pb_photos', ['id' => $photo_id, 'business_id' => $business_id], ['%d','%d']);

    if ($result) wp_send_json_success(['message' => 'Photo deleted']);
    else         wp_send_json_error(['message' => 'Failed to delete photo']);
}

function bntm_ajax_pb_bulk_action_photos() {
    check_ajax_referer('pb_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message' => 'Unauthorized']); }

    global $wpdb;
    $business_id  = get_current_user_id();
    $photo_ids    = json_decode(stripslashes($_POST['photo_ids']), true);
    $bulk_action  = sanitize_text_field($_POST['bulk_action']);

    if (empty($photo_ids) || !is_array($photo_ids)) { wp_send_json_error(['message' => 'No photos selected']); }

    $photo_ids = array_map('intval', $photo_ids);
    $placeholders = implode(',', array_fill(0, count($photo_ids), '%d'));

    if ($bulk_action === 'delete') {
        $photos = $wpdb->get_results($wpdb->prepare(
            "SELECT file_path FROM {$wpdb->prefix}pb_photos WHERE id IN ($placeholders) AND business_id = %d",
            array_merge($photo_ids, [$business_id])
        ));
        $upload_dir = wp_upload_dir();
        foreach ($photos as $photo) {
            $file = $upload_dir['basedir'] . '/' . $photo->file_path;
            if (file_exists($file)) @unlink($file);
        }
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}pb_photos WHERE id IN ($placeholders) AND business_id = %d",
            array_merge($photo_ids, [$business_id])
        ));
        wp_send_json_success(['message' => 'Photos deleted']);
    } elseif (in_array($bulk_action, ['approved','pending','flagged'])) {
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}pb_photos SET status = %s WHERE id IN ($placeholders) AND business_id = %d",
            array_merge([$bulk_action], $photo_ids, [$business_id])
        ));
        wp_send_json_success(['message' => 'Photos updated to ' . $bulk_action]);
    } else {
        wp_send_json_error(['message' => 'Invalid action']);
    }
}

function bntm_ajax_pb_save_display_settings() {
    check_ajax_referer('pb_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message' => 'Unauthorized']); }

    $layout    = sanitize_text_field($_POST['layout']);
    $speed     = intval($_POST['speed']);
    $poll      = intval($_POST['poll']);
    $show_name = sanitize_text_field($_POST['show_name']);
    $bg_color  = sanitize_hex_color($_POST['bg_color']);

    bntm_set_setting('pb_display_layout',    $layout);
    bntm_set_setting('pb_display_speed',     $speed);
    bntm_set_setting('pb_display_poll',      $poll);
    bntm_set_setting('pb_display_show_name', $show_name);
    bntm_set_setting('pb_display_bg_color',  $bg_color);

    wp_send_json_success(['message' => 'Display settings saved!']);
}

function bntm_ajax_pb_save_settings() {
    check_ajax_referer('pb_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message' => 'Unauthorized']); }

    bntm_set_setting('pb_max_upload_size', intval($_POST['max_upload_size']));
    bntm_set_setting('pb_max_image_width', intval($_POST['max_image_width']));
    bntm_set_setting('pb_allowed_types',   sanitize_text_field($_POST['allowed_types']));
    bntm_set_setting('pb_upload_cooldown', intval($_POST['cooldown']));
    bntm_set_setting('pb_moderation_mode', sanitize_text_field($_POST['moderation_mode']));
    bntm_set_setting('pb_guest_name_mode', sanitize_text_field($_POST['guest_name_mode']));
    bntm_set_setting('pb_pwa_name',        sanitize_text_field($_POST['pwa_name']));
    bntm_set_setting('pb_pwa_theme_color', sanitize_hex_color($_POST['pwa_theme_color']));

    wp_send_json_success(['message' => 'Settings saved successfully!']);
}

// ============================================================
// AJAX HANDLERS — PUBLIC (UPLOAD & POLLING)
// ============================================================

function bntm_ajax_pb_get_event_data() {
    $event_rand_id = sanitize_text_field($_GET['event_id'] ?? $_POST['event_id'] ?? '');
    if (!$event_rand_id) { wp_send_json_error(['message' => 'No event specified']); }

    global $wpdb;
    $event = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}pb_events WHERE rand_id = %s AND status = 'active'", $event_rand_id
    ));
    if (!$event) { wp_send_json_error(['message' => 'Event not found or not active']); }

    $prompts = $wpdb->get_results($wpdb->prepare(
        "SELECT id, rand_id, prompt_text, is_free_capture FROM {$wpdb->prefix}pb_prompts
         WHERE event_id = %d AND status = 'active' ORDER BY is_free_capture DESC, sort_order ASC",
        $event->id
    ));

    $photo_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}pb_photos WHERE event_id = %d", $event->id
    ));

    $max_reached = $event->max_photos > 0 && $photo_count >= $event->max_photos;

    $guest_name_mode = bntm_get_setting('pb_guest_name_mode', 'optional');
    $moderation      = bntm_get_setting('pb_moderation_mode', 'auto');

    wp_send_json_success([
        'event'           => ['id' => $event->rand_id, 'title' => $event->title, 'description' => $event->description],
        'prompts'         => $prompts,
        'max_reached'     => $max_reached,
        'guest_name_mode' => $guest_name_mode,
        'moderation'      => $moderation,
    ]);
}

function bntm_ajax_pb_upload_photo() {
    $event_rand_id = sanitize_text_field($_POST['event_id'] ?? '');
    if (!$event_rand_id) { wp_send_json_error(['message' => 'No event specified']); }

    global $wpdb;
    $event = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}pb_events WHERE rand_id = %s AND status = 'active'", $event_rand_id
    ));
    if (!$event) { wp_send_json_error(['message' => 'Event not found or not active']); }

    // Check max photos
    if ($event->max_photos > 0) {
        $count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}pb_photos WHERE event_id = %d", $event->id));
        if ($count >= $event->max_photos) { wp_send_json_error(['message' => 'Photo limit reached for this event']); }
    }

    // Cooldown check
    $cooldown = intval(bntm_get_setting('pb_upload_cooldown', '30'));
    $guest_ip = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');
    if ($cooldown > 0 && $guest_ip) {
        $last_upload = $wpdb->get_var($wpdb->prepare(
            "SELECT created_at FROM {$wpdb->prefix}pb_photos WHERE event_id = %d AND guest_ip = %s ORDER BY created_at DESC LIMIT 1",
            $event->id, $guest_ip
        ));
        if ($last_upload) {
            $seconds_since = time() - strtotime($last_upload);
            if ($seconds_since < $cooldown) {
                wp_send_json_error(['message' => "Please wait " . ($cooldown - $seconds_since) . " seconds before uploading again"]);
            }
        }
    }

    // Validate file
    if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error(['message' => 'No valid file received']);
    }

    $max_size_bytes = intval(bntm_get_setting('pb_max_upload_size', '10')) * 1024 * 1024;
    if ($_FILES['photo']['size'] > $max_size_bytes) {
        wp_send_json_error(['message' => 'File too large']);
    }

    $allowed_types = array_map('trim', explode(',', bntm_get_setting('pb_allowed_types', 'jpeg,png,webp')));
    $allowed_mime  = [];
    foreach ($allowed_types as $t) {
        if ($t === 'jpeg') $allowed_mime[] = 'image/jpeg';
        if ($t === 'png')  $allowed_mime[] = 'image/png';
        if ($t === 'webp') $allowed_mime[] = 'image/webp';
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $_FILES['photo']['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowed_mime)) {
        wp_send_json_error(['message' => 'File type not allowed']);
    }

    // Save file
    $upload_dir  = wp_upload_dir();
    $sub_dir     = 'pb/' . $event->rand_id . '/' . date('Y/m');
    $target_dir  = $upload_dir['basedir'] . '/' . $sub_dir;
    if (!file_exists($target_dir)) wp_mkdir_p($target_dir);

    $ext       = $mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg');
    $rand_id   = bntm_rand_id();
    $filename  = $rand_id . '.' . $ext;
    $file_path = $sub_dir . '/' . $filename;
    $full_path = $target_dir . '/' . $filename;

    if (!move_uploaded_file($_FILES['photo']['tmp_name'], $full_path)) {
        wp_send_json_error(['message' => 'Failed to save file']);
    }

    // Server-side resize
    $max_width = intval(bntm_get_setting('pb_max_image_width', '1280'));
    pb_resize_image($full_path, $max_width, $mime);

    $image_size = @getimagesize($full_path);
    $width      = $image_size ? $image_size[0] : 0;
    $height     = $image_size ? $image_size[1] : 0;
    $file_size  = filesize($full_path);

    $moderation  = bntm_get_setting('pb_moderation_mode', 'auto');
    $status      = $moderation === 'auto' ? 'approved' : 'pending';

    $prompt_id   = intval($_POST['prompt_id'] ?? 0);
    $guest_name  = sanitize_text_field($_POST['guest_name'] ?? '');

    $result = $wpdb->insert(
        $wpdb->prefix . 'pb_photos',
        ['rand_id' => $rand_id, 'business_id' => $event->business_id,
         'event_id' => $event->id, 'prompt_id' => $prompt_id,
         'guest_name' => $guest_name, 'file_path' => $file_path,
         'file_size' => $file_size, 'width' => $width, 'height' => $height,
         'status' => $status, 'guest_ip' => $guest_ip],
        ['%s','%d','%d','%d','%s','%s','%d','%d','%d','%s','%s']
    );

    if ($result) {
        wp_send_json_success([
            'message'  => 'Photo uploaded successfully!',
            'status'   => $status,
            'photo_id' => $rand_id,
        ]);
    } else {
        @unlink($full_path);
        wp_send_json_error(['message' => 'Failed to save photo record']);
    }
}

function bntm_ajax_pb_poll_gallery() {
    $event_rand_id = sanitize_text_field($_GET['event_id'] ?? $_POST['event_id'] ?? '');
    $since         = sanitize_text_field($_GET['since'] ?? $_POST['since'] ?? '');

    if (!$event_rand_id) { wp_send_json_error(['message' => 'No event specified']); }

    global $wpdb;
    $event = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}pb_events WHERE rand_id = %s", $event_rand_id
    ));
    if (!$event) { wp_send_json_error(['message' => 'Event not found']); }

    $where = $wpdb->prepare("WHERE event_id = %d AND status = 'approved'", $event->id);
    if ($since) $where .= $wpdb->prepare(" AND created_at > %s", $since);

    $photos = $wpdb->get_results("
        SELECT rand_id, file_path, guest_name, created_at FROM {$wpdb->prefix}pb_photos
        {$where} ORDER BY created_at DESC LIMIT 50
    ");

    $upload_dir = wp_upload_dir();
    $result     = [];
    foreach ($photos as $p) {
        $result[] = [
            'id'         => $p->rand_id,
            'url'        => $upload_dir['baseurl'] . '/' . $p->file_path,
            'guest_name' => $p->guest_name ?: 'Guest',
            'created_at' => $p->created_at,
        ];
    }

    wp_send_json_success(['photos' => $result]);
}

// ============================================================
// FRONTEND SHORTCODE: UPLOAD PAGE (PWA)
// ============================================================

function bntm_shortcode_pb_upload() {
    $pwa_name  = bntm_get_setting('pb_pwa_name',        'Photo Booth');
    $pwa_color = bntm_get_setting('pb_pwa_theme_color', '#6366f1');
    $ajax_url  = admin_url('admin-ajax.php');
    $max_upload_mb = intval(bntm_get_setting('pb_max_upload_size', '10'));
    $max_upload_mb = $max_upload_mb > 0 ? $max_upload_mb : 10;

    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="<?php echo esc_attr($pwa_color); ?>">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?php echo esc_attr($pwa_name); ?>">
    <title><?php echo esc_html($pwa_name); ?></title>
    <link rel="manifest" href="<?php echo esc_url(add_query_arg('pb_manifest', '1', home_url('/'))); ?>">
    <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
        --pb-accent: <?php echo esc_attr($pwa_color); ?>;
        --pb-dark: #0f0f0f;
        --pb-surface: #1a1a1a;
        --pb-card: #242424;
        --pb-text: #f5f5f5;
        --pb-muted: #888;
        --pb-radius: 16px;
    }
    html, body {
        width: 100%; height: 100%; overflow: hidden;
        background: var(--pb-dark); color: var(--pb-text);
        font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Segoe UI', sans-serif;
        -webkit-tap-highlight-color: transparent;
    }
    #pb-app { width: 100%; height: 100vh; position: relative; overflow: hidden; }

    /* Screens */
    .pb-screen {
        position: absolute; inset: 0; display: none;
        flex-direction: column; align-items: center; justify-content: flex-start;
        padding: 0; overflow-y: auto;
        -webkit-overflow-scrolling: touch;
    }
    .pb-screen.active { display: flex; }

    /* Welcome screen */
    #pb-screen-welcome {
        background: radial-gradient(ellipse at 60% 0%, color-mix(in srgb, var(--pb-accent) 25%, transparent) 0%, var(--pb-dark) 60%);
        justify-content: center; text-align: center; padding: 40px 28px;
    }
    .pb-welcome-icon {
        width: 96px; height: 96px; border-radius: 24px;
        background: var(--pb-accent); display: flex; align-items: center; justify-content: center;
        margin: 0 auto 32px; box-shadow: 0 20px 60px color-mix(in srgb, var(--pb-accent) 40%, transparent);
    }
    .pb-welcome-icon svg { width: 52px; height: 52px; }
    #pb-screen-welcome h1 { font-size: 32px; font-weight: 800; letter-spacing: -0.5px; margin-bottom: 12px; }
    #pb-screen-welcome p  { font-size: 16px; color: var(--pb-muted); line-height: 1.6; margin-bottom: 40px; max-width: 280px; }
    #pb-event-title-display { color: var(--pb-accent); font-weight: 700; }

    /* Big button */
    .pb-btn-big {
        width: 100%; max-width: 320px; padding: 18px 32px;
        background: var(--pb-accent); color: #fff; border: none;
        border-radius: var(--pb-radius); font-size: 18px; font-weight: 700;
        cursor: pointer; letter-spacing: -0.3px;
        box-shadow: 0 8px 32px color-mix(in srgb, var(--pb-accent) 35%, transparent);
        transition: transform 0.15s, box-shadow 0.15s;
        -webkit-tap-highlight-color: transparent;
    }
    .pb-btn-big:active { transform: scale(0.97); box-shadow: none; }
    .pb-btn-outline {
        width: 100%; max-width: 320px; padding: 16px 32px;
        background: transparent; color: var(--pb-text);
        border: 1.5px solid rgba(255,255,255,0.2);
        border-radius: var(--pb-radius); font-size: 16px; font-weight: 600;
        cursor: pointer; margin-top: 12px;
        transition: background 0.15s, border-color 0.15s;
    }
    .pb-btn-outline:active { background: rgba(255,255,255,0.08); }

    /* Prompt screen */
    #pb-screen-prompts { padding: 0; justify-content: flex-start; }
    .pb-screen-header {
        width: 100%; padding: 20px 20px 0;
        display: flex; align-items: center; gap: 12px;
    }
    .pb-back-btn {
        width: 40px; height: 40px; border-radius: 50%; background: var(--pb-card);
        border: none; color: var(--pb-text); cursor: pointer; display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }
    .pb-screen-header h2 { font-size: 20px; font-weight: 700; }
    .pb-screen-header p  { font-size: 13px; color: var(--pb-muted); margin-top: 2px; }
    .pb-prompts-list { width: 100%; padding: 20px; display: flex; flex-direction: column; gap: 12px; }
    .pb-prompt-card {
        width: 100%; padding: 20px; border-radius: var(--pb-radius);
        background: var(--pb-card); border: 1.5px solid transparent;
        cursor: pointer; text-align: left;
        transition: border-color 0.15s, transform 0.15s;
        display: flex; align-items: center; gap: 16px;
    }
    .pb-prompt-card:active { transform: scale(0.98); }
    .pb-prompt-card.free { border-color: var(--pb-accent); background: color-mix(in srgb, var(--pb-accent) 10%, var(--pb-card)); }
    .pb-prompt-icon {
        width: 44px; height: 44px; border-radius: 12px; flex-shrink: 0;
        background: color-mix(in srgb, var(--pb-accent) 15%, transparent);
        display: flex; align-items: center; justify-content: center;
    }
    .pb-prompt-card .pb-prompt-text { font-size: 17px; font-weight: 600; }
    .pb-prompt-card .pb-prompt-sub  { font-size: 12px; color: var(--pb-muted); margin-top: 3px; }

    /* Camera screen */
    #pb-screen-camera { background: #000; justify-content: center; padding: 0; }
    #pb-video {
        width: 100%; height: 100vh; object-fit: cover;
        position: absolute; inset: 0;
    }
    .pb-camera-overlay {
        position: absolute; inset: 0;
        display: flex; flex-direction: column; justify-content: space-between;
        padding: 60px 24px 40px;
        background: linear-gradient(180deg, rgba(0,0,0,0.5) 0%, transparent 25%, transparent 65%, rgba(0,0,0,0.7) 100%);
    }
    .pb-camera-top {
        display: flex; justify-content: space-between; align-items: center;
    }
    .pb-camera-prompt-badge {
        background: rgba(0,0,0,0.6); backdrop-filter: blur(10px);
        color: #fff; padding: 8px 14px; border-radius: 20px;
        font-size: 13px; font-weight: 600; max-width: 200px;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .pb-flip-btn {
        width: 44px; height: 44px; border-radius: 50%;
        background: rgba(0,0,0,0.6); backdrop-filter: blur(10px);
        border: none; cursor: pointer; color: #fff;
        display: flex; align-items: center; justify-content: center;
    }
    .pb-camera-bottom { display: flex; align-items: center; justify-content: center; position: relative; }
    .pb-capture-btn {
        width: 80px; height: 80px; border-radius: 50%;
        border: 4px solid #fff; background: transparent; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        transition: transform 0.1s;
        -webkit-tap-highlight-color: transparent;
    }
    .pb-capture-btn:active { transform: scale(0.92); }
    .pb-capture-btn-inner {
        width: 62px; height: 62px; border-radius: 50%; background: #fff;
        transition: transform 0.1s;
    }
    .pb-capture-btn:active .pb-capture-btn-inner { transform: scale(0.88); }
    #pb-camera-close {
        position: absolute; left: 0;
        width: 44px; height: 44px; border-radius: 50%;
        background: rgba(0,0,0,0.6); backdrop-filter: blur(10px);
        border: none; cursor: pointer; color: #fff;
        display: flex; align-items: center; justify-content: center;
    }
    #pb-canvas { display: none; }

    /* Preview screen */
    #pb-screen-preview { background: #000; justify-content: flex-start; }
    #pb-preview-img {
        width: 100%; flex-shrink: 0;
        max-height: 55vh; object-fit: contain; display: block;
    }
    .pb-preview-controls { width: 100%; padding: 20px 20px 8px; }
    .pb-preview-controls h3 { font-size: 18px; font-weight: 700; margin-bottom: 14px; }
    .pb-filter-row {
        display: flex; gap: 10px; overflow-x: auto; padding-bottom: 12px;
        -webkit-overflow-scrolling: touch; scrollbar-width: none;
    }
    .pb-filter-row::-webkit-scrollbar { display: none; }
    .pb-filter-chip {
        flex-shrink: 0; padding: 8px 16px; border-radius: 20px;
        background: var(--pb-card); border: 1.5px solid transparent;
        color: var(--pb-text); font-size: 13px; font-weight: 600; cursor: pointer;
        transition: border-color 0.15s;
    }
    .pb-filter-chip.active { border-color: var(--pb-accent); color: var(--pb-accent); }
    .pb-brightness-row { margin-top: 14px; display: flex; align-items: center; gap: 12px; }
    .pb-brightness-row label { font-size: 13px; color: var(--pb-muted); white-space: nowrap; }
    .pb-brightness-row input[type=range] {
        flex: 1; appearance: none; height: 4px;
        background: var(--pb-card); border-radius: 4px; outline: none;
    }
    .pb-brightness-row input[type=range]::-webkit-slider-thumb {
        appearance: none; width: 20px; height: 20px; border-radius: 50%;
        background: var(--pb-accent); cursor: pointer;
    }
    .pb-preview-actions {
        width: 100%; padding: 16px 20px;
        display: flex; gap: 12px; flex-direction: column;
    }

    /* Name screen */
    #pb-screen-name {
        padding: 40px 28px; justify-content: flex-start; gap: 0;
    }
    #pb-screen-name h2 { font-size: 26px; font-weight: 800; margin-bottom: 8px; }
    #pb-screen-name p  { font-size: 15px; color: var(--pb-muted); margin-bottom: 36px; }
    .pb-name-input {
        width: 100%; padding: 18px; border-radius: var(--pb-radius);
        background: var(--pb-card); border: 1.5px solid rgba(255,255,255,0.1);
        color: var(--pb-text); font-size: 18px; outline: none;
        margin-bottom: 20px;
        transition: border-color 0.2s;
    }
    .pb-name-input:focus { border-color: var(--pb-accent); }

    /* Upload progress */
    #pb-screen-uploading {
        justify-content: center; align-items: center; padding: 40px 28px; text-align: center;
        gap: 24px;
    }
    .pb-spinner {
        width: 64px; height: 64px; border-radius: 50%;
        border: 4px solid var(--pb-card);
        border-top-color: var(--pb-accent);
        animation: pbSpin 0.8s linear infinite;
    }
    @keyframes pbSpin { to { transform: rotate(360deg); } }
    #pb-screen-uploading h3 { font-size: 22px; font-weight: 700; }
    #pb-screen-uploading p  { font-size: 14px; color: var(--pb-muted); }
    .pb-progress-bar {
        width: 100%; max-width: 280px; height: 6px;
        background: var(--pb-card); border-radius: 6px; overflow: hidden;
    }
    .pb-progress-fill {
        height: 100%; background: var(--pb-accent); border-radius: 6px;
        width: 0%; transition: width 0.3s;
    }

    /* Success screen */
    #pb-screen-success {
        justify-content: center; align-items: center; padding: 40px 28px; text-align: center; gap: 20px;
        background: radial-gradient(ellipse at 50% 40%, color-mix(in srgb, var(--pb-accent) 20%, transparent), var(--pb-dark) 70%);
    }
    .pb-success-icon {
        width: 100px; height: 100px; border-radius: 50%;
        background: color-mix(in srgb, var(--pb-accent) 15%, transparent);
        border: 3px solid var(--pb-accent); display: flex; align-items: center; justify-content: center;
    }
    #pb-screen-success h2 { font-size: 30px; font-weight: 800; }
    #pb-screen-success p  { font-size: 16px; color: var(--pb-muted); max-width: 260px; line-height: 1.6; }
    #pb-screen-success .pb-pending-note {
        background: color-mix(in srgb, #f59e0b 10%, transparent);
        border: 1px solid color-mix(in srgb, #f59e0b 40%, transparent);
        color: #f59e0b; padding: 12px 18px; border-radius: 10px;
        font-size: 13px; max-width: 280px; line-height: 1.5;
    }

    /* Error / offline notice */
    .pb-offline-banner {
        position: fixed; bottom: 0; left: 0; right: 0; z-index: 100;
        background: #f59e0b; color: #000; padding: 10px 20px;
        font-size: 14px; font-weight: 600; text-align: center;
        transform: translateY(100%); transition: transform 0.3s;
    }
    .pb-offline-banner.show { transform: translateY(0); }

    /* Loading state */
    #pb-loading {
        position: fixed; inset: 0; background: var(--pb-dark);
        display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 20px;
        z-index: 200;
    }
    #pb-loading.hidden { display: none; }
    #pb-loading svg { width: 64px; height: 64px; color: var(--pb-accent); }
    #pb-loading p { font-size: 16px; color: var(--pb-muted); }

    #pb-error-screen {
        position: fixed; inset: 0; background: var(--pb-dark);
        display: none; flex-direction: column; align-items: center; justify-content: center;
        text-align: center; padding: 40px; gap: 16px;
    }
    #pb-error-screen h2 { font-size: 24px; font-weight: 800; }
    #pb-error-screen p  { font-size: 15px; color: var(--pb-muted); }

    @media (min-width: 480px) {
        #pb-app { max-width: 430px; margin: 0 auto; box-shadow: 0 0 80px rgba(0,0,0,0.5); }
    }
    </style>
    </head>
    <body>
    <div id="pb-app">
        <!-- Loading -->
        <div id="pb-loading">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z" stroke-width="1.5"/>
                <circle cx="12" cy="13" r="4" stroke-width="1.5"/>
            </svg>
            <p>Loading event...</p>
        </div>

        <!-- Error -->
        <div id="pb-error-screen">
            <svg width="64" height="64" fill="none" stroke="#ef4444" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10" stroke-width="1.5"/>
                <line x1="12" y1="8" x2="12" y2="12" stroke-width="2"/>
                <line x1="12" y1="16" x2="12.01" y2="16" stroke-width="2"/>
            </svg>
            <h2>Event Not Found</h2>
            <p id="pb-error-msg">This event is not active or the link is invalid.</p>
        </div>

        <!-- Screen 1: Welcome -->
        <div class="pb-screen" id="pb-screen-welcome">
            <div class="pb-welcome-icon">
                <svg fill="none" stroke="white" viewBox="0 0 24 24">
                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z" stroke-width="2"/>
                    <circle cx="12" cy="13" r="4" stroke-width="2"/>
                </svg>
            </div>
            <h1><span id="pb-event-title-display">Photo Booth</span></h1>
            <p id="pb-event-desc-display">Get ready to capture some great moments!</p>
            <button class="pb-btn-big" id="pb-start-btn">Start Taking Photos</button>
        </div>

        <!-- Screen 2: Prompts -->
        <div class="pb-screen" id="pb-screen-prompts">
            <div class="pb-screen-header">
                <button class="pb-back-btn" onclick="pbShowScreen('welcome')">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7" stroke-width="2" stroke-linecap="round"/></svg>
                </button>
                <div>
                    <h2>Choose a Prompt</h2>
                    <p>What moment are you capturing?</p>
                </div>
            </div>
            <div class="pb-prompts-list" id="pb-prompts-list"></div>
            <div style="width:100%;padding:0 20px 20px;">
                <input type="file" accept="image/*" capture="environment" id="pb-file-input" style="display:none;">
                <button class="pb-btn-outline" id="pb-file-btn" style="max-width:none;">Use Photo From Device</button>
            </div>
        </div>

        <!-- Screen 3: Camera -->
        <div class="pb-screen" id="pb-screen-camera">
            <video id="pb-video" autoplay playsinline muted></video>
            <canvas id="pb-canvas"></canvas>
            <div class="pb-camera-overlay">
                <div class="pb-camera-top">
                    <span class="pb-camera-prompt-badge" id="pb-camera-prompt-label">Free Capture</span>
                    <button class="pb-flip-btn" id="pb-flip-btn" title="Flip camera">
                        <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M1 4v6h6M23 20v-6h-6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10M23 14l-4.64 4.36A9 9 0 0 1 3.51 15" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                </div>
                <div class="pb-camera-bottom">
                    <button id="pb-camera-close" onclick="pbShowScreen('prompts')">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18" stroke-width="2"/><line x1="6" y1="6" x2="18" y2="18" stroke-width="2"/></svg>
                    </button>
                    <button class="pb-capture-btn" id="pb-capture-btn" aria-label="Take photo">
                        <div class="pb-capture-btn-inner"></div>
                    </button>
                </div>
            </div>
        </div>

        <!-- Screen 4: Preview -->
        <div class="pb-screen" id="pb-screen-preview">
            <img id="pb-preview-img" src="" alt="Preview">
            <div class="pb-preview-controls">
                <h3>Preview</h3>
                <div class="pb-filter-row">
                    <button class="pb-filter-chip active" data-filter="none">Normal</button>
                    <button class="pb-filter-chip" data-filter="warm">Warm</button>
                    <button class="pb-filter-chip" data-filter="cool">Cool</button>
                    <button class="pb-filter-chip" data-filter="bw">B&amp;W</button>
                    <button class="pb-filter-chip" data-filter="vivid">Vivid</button>
                    <button class="pb-filter-chip" data-filter="fade">Fade</button>
                </div>
                <div class="pb-brightness-row">
                    <label>Brightness</label>
                    <input type="range" id="pb-brightness-slider" min="60" max="160" value="100">
                </div>
                <div class="pb-brightness-row" style="margin-top:10px;">
                    <label>Contrast</label>
                    <input type="range" id="pb-contrast-slider" min="70" max="170" value="100">
                </div>
                <div class="pb-filter-row" style="margin-top:14px;">
                    <button class="pb-filter-chip active" data-crop="original">Original Crop</button>
                    <button class="pb-filter-chip" data-crop="square">Square Crop</button>
                </div>
            </div>
            <div class="pb-preview-actions">
                <button class="pb-btn-big" id="pb-confirm-btn">Confirm Photo</button>
                <button class="pb-btn-outline" onclick="pbShowScreen('camera')">Retake</button>
            </div>
        </div>

        <!-- Screen 5: Name -->
        <div class="pb-screen" id="pb-screen-name">
            <h2>What's your name?</h2>
            <p id="pb-name-subtitle">Add your name to the photo (optional)</p>
            <input type="text" class="pb-name-input" id="pb-guest-name-input" placeholder="Your name..." autocomplete="name">
            <button class="pb-btn-big" id="pb-name-confirm-btn">Upload Photo</button>
            <button class="pb-btn-outline" id="pb-name-skip-btn" style="margin-top:10px;">Skip</button>
        </div>

        <!-- Screen 6: Uploading -->
        <div class="pb-screen" id="pb-screen-uploading">
            <div class="pb-spinner"></div>
            <h3>Uploading...</h3>
            <p>Please keep this screen open</p>
            <div class="pb-progress-bar"><div class="pb-progress-fill" id="pb-progress-fill"></div></div>
        </div>

        <!-- Screen 7: Success -->
        <div class="pb-screen" id="pb-screen-success">
            <div class="pb-success-icon">
                <svg width="52" height="52" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <polyline points="20 6 9 17 4 12" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h2>Photo Uploaded!</h2>
            <p>Your photo has been added to the event.</p>
            <div class="pb-pending-note" id="pb-pending-note" style="display:none;">
                Your photo is pending review and will appear in the gallery once approved.
            </div>
            <button class="pb-btn-big" id="pb-take-another-btn">Take Another Photo</button>
        </div>

        <!-- Offline Banner -->
        <div class="pb-offline-banner" id="pb-offline-banner">No connection — uploads will retry when online</div>
    </div>

    <script>
    (function() {
        const AJAX = '<?php echo esc_js($ajax_url); ?>';
        const SW_URL = '<?php echo esc_js(add_query_arg('pb_sw', '1', home_url('/'))); ?>';
        const MAX_UPLOAD_MB = <?php echo intval($max_upload_mb); ?>;
        const MAX_UPLOAD_BYTES = MAX_UPLOAD_MB * 1024 * 1024;
        const params = new URLSearchParams(location.search);
        const eventId = params.get('event_id') || '';

        let eventData      = null;
        let selectedPrompt = null;
        let capturedBlob   = null;
        let selectedFilter = 'none';
        let selectedCrop   = 'original';
        let brightness     = 100;
        let contrast       = 100;
        let stream         = null;
        let facingMode     = 'environment';
        let uploadDebounce = false;

        const filters = {
            none  : 'none',
            warm  : 'sepia(0.35) saturate(1.45)',
            cool  : 'hue-rotate(20deg) saturate(1.1)',
            bw    : 'grayscale(1)',
            vivid : 'saturate(1.9) contrast(1.08)',
            fade  : 'saturate(0.65) brightness(1.08) contrast(0.92)',
        };

        function escHtml(s) {
            return String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function showScreen(name) {
            document.querySelectorAll('.pb-screen').forEach(s => s.classList.remove('active'));
            const el = document.getElementById('pb-screen-' + name);
            if (el) el.classList.add('active');
            if (name !== 'camera' && stream) stopCamera();
        }
        window.pbShowScreen = showScreen;

        function showError(msg) {
            document.getElementById('pb-loading').classList.add('hidden');
            document.getElementById('pb-error-msg').textContent = msg;
            document.getElementById('pb-error-screen').style.display = 'flex';
        }

        function resetPreviewControls() {
            selectedFilter = 'none';
            selectedCrop = 'original';
            brightness = 100;
            contrast = 100;
            document.getElementById('pb-brightness-slider').value = 100;
            document.getElementById('pb-contrast-slider').value = 100;
            document.querySelectorAll('.pb-filter-chip[data-filter]').forEach(c => c.classList.remove('active'));
            document.querySelectorAll('.pb-filter-chip[data-crop]').forEach(c => c.classList.remove('active'));
            const defaultFilter = document.querySelector('.pb-filter-chip[data-filter="none"]');
            const defaultCrop = document.querySelector('.pb-filter-chip[data-crop="original"]');
            if (defaultFilter) defaultFilter.classList.add('active');
            if (defaultCrop) defaultCrop.classList.add('active');
            applyPreviewStyle();
        }

        function applyPreviewStyle() {
            const img = document.getElementById('pb-preview-img');
            const filter = filters[selectedFilter] || 'none';
            const b = (brightness / 100).toFixed(2);
            const c = (contrast / 100).toFixed(2);
            const extra = ' brightness(' + b + ') contrast(' + c + ')';
            img.style.filter = (filter === 'none' ? '' : filter) + extra;
            img.style.objectFit = selectedCrop === 'square' ? 'cover' : 'contain';
            img.style.aspectRatio = selectedCrop === 'square' ? '1 / 1' : 'auto';
        }

        function blobToImage(blob) {
            return new Promise((resolve, reject) => {
                const tmpUrl = URL.createObjectURL(blob);
                const img = new Image();
                img.onload = function() {
                    URL.revokeObjectURL(tmpUrl);
                    resolve(img);
                };
                img.onerror = function() {
                    URL.revokeObjectURL(tmpUrl);
                    reject(new Error('Failed to read image'));
                };
                img.src = tmpUrl;
            });
        }

        async function processImageBlobForUpload(sourceBlob) {
            const src = await blobToImage(sourceBlob);
            let sx = 0;
            let sy = 0;
            let sw = src.naturalWidth;
            let sh = src.naturalHeight;

            if (selectedCrop === 'square') {
                const size = Math.min(sw, sh);
                sx = Math.floor((sw - size) / 2);
                sy = Math.floor((sh - size) / 2);
                sw = size;
                sh = size;
            }

            const maxWidth = 1280;
            let targetW = sw;
            let targetH = sh;
            if (targetW > maxWidth) {
                targetH = Math.round(targetH * (maxWidth / targetW));
                targetW = maxWidth;
            }

            const canvas = document.getElementById('pb-canvas');
            canvas.width = targetW;
            canvas.height = targetH;
            const ctx = canvas.getContext('2d');
            const filter = filters[selectedFilter] || 'none';
            const b = (brightness / 100).toFixed(2);
            const c = (contrast / 100).toFixed(2);
            ctx.filter = (filter === 'none' ? '' : filter) + ' brightness(' + b + ') contrast(' + c + ')';
            ctx.drawImage(src, sx, sy, sw, sh, 0, 0, targetW, targetH);

            return new Promise((resolve, reject) => {
                canvas.toBlob(blob => {
                    if (!blob) return reject(new Error('Failed to process image'));
                    resolve(blob);
                }, 'image/jpeg', 0.8);
            });
        }

        function setPreviewFromBlob(blob) {
            capturedBlob = blob;
            const img = document.getElementById('pb-preview-img');
            if (img.dataset.objectUrl) URL.revokeObjectURL(img.dataset.objectUrl);
            const objectUrl = URL.createObjectURL(blob);
            img.dataset.objectUrl = objectUrl;
            img.src = objectUrl;
            resetPreviewControls();
            showScreen('preview');
        }

        async function loadEvent() {
            if (!eventId) {
                showError('No event ID in URL. Please scan the QR code again.');
                return;
            }

            try {
                const fd = new FormData();
                fd.append('action', 'pb_get_event_data');
                fd.append('event_id', eventId);
                const res = await fetch(AJAX, { method: 'POST', body: fd });
                const json = await res.json();

                if (!json.success) {
                    showError(json.data && json.data.message ? json.data.message : 'Event not found');
                    return;
                }

                eventData = json.data;
                document.getElementById('pb-event-title-display').textContent = eventData.event.title;
                if (eventData.event.description) {
                    document.getElementById('pb-event-desc-display').textContent = eventData.event.description;
                }

                if (eventData.max_reached) {
                    const btn = document.getElementById('pb-start-btn');
                    btn.textContent = 'Event Full';
                    btn.disabled = true;
                    btn.style.opacity = '0.5';
                }

                const nameMode = eventData.guest_name_mode;
                if (nameMode === 'required') {
                    document.getElementById('pb-name-skip-btn').style.display = 'none';
                    document.getElementById('pb-name-subtitle').textContent = 'Please enter your name (required)';
                }

                renderPrompts(eventData.prompts || []);
                document.getElementById('pb-loading').classList.add('hidden');
                showScreen('welcome');
            } catch (e) {
                showError('Failed to load event. Check your connection.');
            }
        }

        function renderPrompts(prompts) {
            const list = document.getElementById('pb-prompts-list');
            list.innerHTML = '';

            prompts.forEach(p => {
                const card = document.createElement('button');
                card.className = 'pb-prompt-card' + (p.is_free_capture ? ' free' : '');
                card.innerHTML = '\n                    <div class="pb-prompt-icon">\n                        <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24">\n                            ' +
                            (p.is_free_capture
                                ? '<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z" stroke-width="2"/><circle cx="12" cy="13" r="4" stroke-width="2"/>'
                                : '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" stroke-width="2"/>') +
                            '\n                        </svg>\n                    </div>\n                    <div>\n                        <div class="pb-prompt-text">' + escHtml(p.prompt_text) + '</div>\n                        <div class="pb-prompt-sub">' + (p.is_free_capture ? 'Capture anything you like' : 'Guided prompt') + '</div>\n                    </div>';
                card.addEventListener('click', function() { selectPrompt(p); });
                list.appendChild(card);
            });

            if (!prompts.length) {
                list.innerHTML = '<div style="padding:20px;color:#aaa;">No active prompts yet.</div>';
            }
        }

        function selectPrompt(prompt) {
            selectedPrompt = prompt;
            document.getElementById('pb-camera-prompt-label').textContent = prompt.prompt_text || 'Free Capture';
            showScreen('camera');
            startCamera();
        }

        async function startCamera() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                alert('Camera is not available on this browser. You can still upload from your device photos.');
                showScreen('prompts');
                return;
            }

            try {
                stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: facingMode,
                        width: { ideal: 1920 },
                        height: { ideal: 1080 }
                    },
                    audio: false
                });
                document.getElementById('pb-video').srcObject = stream;
            } catch (e) {
                alert('Camera permission was denied. You can use "Use Photo From Device" instead.');
                showScreen('prompts');
            }
        }

        function stopCamera() {
            if (!stream) return;
            stream.getTracks().forEach(track => track.stop());
            stream = null;
        }

        function isImageFile(file) {
            return !!file && /^image\//i.test(file.type || '');
        }

        function validateClientFileSize(blob) {
            if (!blob) return false;
            if (blob.size > MAX_UPLOAD_BYTES) {
                alert('Image is too large. Maximum allowed size is ' + MAX_UPLOAD_MB + 'MB.');
                return false;
            }
            return true;
        }

        document.getElementById('pb-flip-btn').addEventListener('click', function() {
            facingMode = facingMode === 'environment' ? 'user' : 'environment';
            stopCamera();
            startCamera();
        });

        document.getElementById('pb-capture-btn').addEventListener('click', function() {
            const video = document.getElementById('pb-video');
            const canvas = document.getElementById('pb-canvas');
            const maxW = 1600;
            let w = video.videoWidth;
            let h = video.videoHeight;
            if (!w || !h) return;
            if (w > maxW) {
                h = Math.round(h * (maxW / w));
                w = maxW;
            }

            canvas.width = w;
            canvas.height = h;
            const ctx = canvas.getContext('2d');
            if (facingMode === 'user') {
                ctx.translate(w, 0);
                ctx.scale(-1, 1);
            }
            ctx.drawImage(video, 0, 0, w, h);
            canvas.toBlob(function(blob) {
                if (!blob) return;
                setPreviewFromBlob(blob);
                stopCamera();
            }, 'image/jpeg', 0.88);
        });

        document.getElementById('pb-file-btn').addEventListener('click', function() {
            if (!selectedPrompt) {
                selectedPrompt = { id: 0, prompt_text: 'Free Capture' };
            }
            document.getElementById('pb-file-input').click();
        });

        document.getElementById('pb-file-input').addEventListener('change', function(e) {
            const file = e.target.files && e.target.files[0] ? e.target.files[0] : null;
            if (!file) return;
            if (!isImageFile(file)) {
                alert('Only image files are allowed.');
                e.target.value = '';
                return;
            }
            setPreviewFromBlob(file);
            e.target.value = '';
        });

        document.querySelectorAll('.pb-filter-chip[data-filter]').forEach(chip => {
            chip.addEventListener('click', function() {
                document.querySelectorAll('.pb-filter-chip[data-filter]').forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                selectedFilter = this.dataset.filter;
                applyPreviewStyle();
            });
        });

        document.querySelectorAll('.pb-filter-chip[data-crop]').forEach(chip => {
            chip.addEventListener('click', function() {
                document.querySelectorAll('.pb-filter-chip[data-crop]').forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                selectedCrop = this.dataset.crop;
                applyPreviewStyle();
            });
        });

        document.getElementById('pb-brightness-slider').addEventListener('input', function() {
            brightness = Number(this.value || 100);
            applyPreviewStyle();
        });

        document.getElementById('pb-contrast-slider').addEventListener('input', function() {
            contrast = Number(this.value || 100);
            applyPreviewStyle();
        });

        document.getElementById('pb-confirm-btn').addEventListener('click', async function() {
            if (!capturedBlob) return;
            try {
                const optimized = await processImageBlobForUpload(capturedBlob);
                if (!validateClientFileSize(optimized)) return;
                capturedBlob = optimized;
                const nameMode = eventData && eventData.guest_name_mode ? eventData.guest_name_mode : 'optional';
                if (nameMode === 'hidden') {
                    doUpload('');
                } else {
                    showScreen('name');
                }
            } catch (e) {
                alert('Could not process image. Please try another capture.');
            }
        });

        function animateProgress(done) {
            const fill = document.getElementById('pb-progress-fill');
            if (done) {
                fill.style.width = '100%';
                return;
            }
            fill.style.width = '0%';
            let progress = 0;
            const timer = setInterval(function() {
                progress += Math.random() * 14;
                if (progress >= 90) {
                    progress = 90;
                    clearInterval(timer);
                }
                fill.style.width = progress + '%';
            }, 280);
        }

        function uploadPhotoBlob(payload) {
            const fd = new FormData();
            fd.append('action', 'pb_upload_photo');
            fd.append('event_id', payload.eventId);
            fd.append('prompt_id', String(payload.promptId || 0));
            fd.append('guest_name', payload.guestName || '');
            fd.append('photo', payload.blob, 'photo.jpg');
            return fetch(AJAX, { method: 'POST', body: fd }).then(r => r.json());
        }

        function openQueueDb() {
            return new Promise((resolve, reject) => {
                const req = indexedDB.open('pb_queue', 1);
                req.onupgradeneeded = function(e) {
                    const db = e.target.result;
                    if (!db.objectStoreNames.contains('uploads')) {
                        db.createObjectStore('uploads', { keyPath: 'id', autoIncrement: true });
                    }
                };
                req.onsuccess = function(e) { resolve(e.target.result); };
                req.onerror = function() { reject(req.error); };
            });
        }

        async function queueUpload(blob, guestName) {
            const db = await openQueueDb();
            await new Promise((resolve, reject) => {
                const tx = db.transaction('uploads', 'readwrite');
                tx.oncomplete = resolve;
                tx.onerror = () => reject(tx.error);
                tx.objectStore('uploads').add({
                    eventId: eventId,
                    promptId: selectedPrompt ? selectedPrompt.id : 0,
                    guestName: guestName || '',
                    blob: blob,
                    createdAt: Date.now()
                });
            });
            db.close();
            document.getElementById('pb-pending-note').style.display = 'block';
            showScreen('success');
        }

        async function retryQueue() {
            if (!navigator.onLine) return;
            try {
                const db = await openQueueDb();
                const items = await new Promise((resolve, reject) => {
                    const tx = db.transaction('uploads', 'readonly');
                    const req = tx.objectStore('uploads').getAll();
                    req.onsuccess = () => resolve(req.result || []);
                    req.onerror = () => reject(req.error);
                });

                for (let i = 0; i < items.length; i++) {
                    const item = items[i];
                    if (!item || !item.blob) continue;
                    try {
                        const response = await uploadPhotoBlob(item);
                        if (response && response.success) {
                            await new Promise((resolve, reject) => {
                                const tx = db.transaction('uploads', 'readwrite');
                                tx.oncomplete = resolve;
                                tx.onerror = () => reject(tx.error);
                                tx.objectStore('uploads').delete(item.id);
                            });
                        }
                    } catch (e) {
                        break;
                    }
                }
                db.close();
            } catch (e) {}
        }

        function handleUploadFromName(skip) {
            const nameMode = eventData && eventData.guest_name_mode ? eventData.guest_name_mode : 'optional';
            const name = document.getElementById('pb-guest-name-input').value.trim();
            if (nameMode === 'required' && !name && !skip) {
                document.getElementById('pb-guest-name-input').focus();
                return;
            }
            doUpload(skip ? '' : name);
        }

        async function doUpload(guestName) {
            if (uploadDebounce || !capturedBlob) return;
            uploadDebounce = true;
            showScreen('uploading');
            animateProgress(false);

            if (!validateClientFileSize(capturedBlob)) {
                uploadDebounce = false;
                showScreen('preview');
                return;
            }

            if (!navigator.onLine) {
                await queueUpload(capturedBlob, guestName);
                uploadDebounce = false;
                return;
            }

            try {
                const response = await uploadPhotoBlob({
                    eventId: eventId,
                    promptId: selectedPrompt ? selectedPrompt.id : 0,
                    guestName: guestName,
                    blob: capturedBlob
                });

                uploadDebounce = false;
                if (response && response.success) {
                    animateProgress(true);
                    const pending = response.data && response.data.status === 'pending';
                    document.getElementById('pb-pending-note').style.display = pending ? 'block' : 'none';
                    showScreen('success');
                    return;
                }

                alert(response && response.data && response.data.message ? response.data.message : 'Upload failed. Please try again.');
                showScreen('preview');
            } catch (e) {
                await queueUpload(capturedBlob, guestName);
                uploadDebounce = false;
            }
        }

        document.getElementById('pb-name-confirm-btn').addEventListener('click', function() { handleUploadFromName(false); });
        document.getElementById('pb-name-skip-btn').addEventListener('click', function() { handleUploadFromName(true); });

        document.getElementById('pb-take-another-btn').addEventListener('click', function() {
            capturedBlob = null;
            selectedPrompt = null;
            document.getElementById('pb-guest-name-input').value = '';
            showScreen('prompts');
        });

        document.getElementById('pb-start-btn').addEventListener('click', function() {
            showScreen('prompts');
        });

        const offlineBanner = document.getElementById('pb-offline-banner');
        window.addEventListener('offline', function() {
            offlineBanner.classList.add('show');
        });
        window.addEventListener('online', function() {
            offlineBanner.classList.remove('show');
            retryQueue();
        });

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register(SW_URL).catch(function() {});
            });
        }

        if (navigator.onLine) {
            retryQueue();
        } else {
            offlineBanner.classList.add('show');
        }

        loadEvent();
    })();
    </script>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

// ============================================================
// FRONTEND SHORTCODE: GALLERY PAGE
// ============================================================

function bntm_shortcode_pb_gallery() {
    $ajax_url = admin_url('admin-ajax.php');
    $event_id = sanitize_text_field($_GET['event_id'] ?? '');
    $poll_int = intval(bntm_get_setting('pb_display_poll', '10')) * 1000;

    if (!$event_id) {
        return '<div style="text-align:center;padding:60px 20px;font-family:sans-serif;"><p>No event specified. Please scan the QR code.</p></div>';
    }

    global $wpdb;
    $event = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}pb_events WHERE rand_id = %s", $event_id));

    $photos = [];
    if ($event) {
        $upload_dir = wp_upload_dir();
        $photos     = $wpdb->get_results($wpdb->prepare(
            "SELECT rand_id, file_path, guest_name, created_at FROM {$wpdb->prefix}pb_photos
             WHERE event_id = %d AND status = 'approved' ORDER BY created_at DESC",
            $event->id
        ));
    }

    $pwa_color = bntm_get_setting('pb_pwa_theme_color', '#6366f1');
    $upload_dir = wp_upload_dir();

    ob_start();
    ?>
    <style>
    .pb-gallery-page {
        min-height: 100vh; background: #0f0f0f; color: #f5f5f5;
        font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Segoe UI', sans-serif;
        padding: 0;
    }
    .pb-gallery-header {
        padding: 24px 20px 16px; display: flex; align-items: center; gap: 16px;
        border-bottom: 1px solid rgba(255,255,255,0.08);
        position: sticky; top: 0; z-index: 10;
        background: rgba(15,15,15,0.95); backdrop-filter: blur(12px);
    }
    .pb-gallery-header h1 { font-size: 22px; font-weight: 800; margin: 0; flex: 1; }
    .pb-gallery-header .pb-count {
        font-size: 13px; color: #888; background: rgba(255,255,255,0.06);
        padding: 4px 10px; border-radius: 20px;
    }
    .pb-live-dot {
        width: 8px; height: 8px; border-radius: 50%;
        background: #10b981; display: inline-block; margin-right: 6px;
        animation: pbPulse 2s infinite;
    }
    @keyframes pbPulse {
        0%,100%{opacity:1;} 50%{opacity:0.4;}
    }
    .pb-gallery-grid {
        columns: 2; column-gap: 6px; padding: 8px 6px;
    }
    @media(min-width:600px){ .pb-gallery-grid{columns:3;} }
    @media(min-width:900px){ .pb-gallery-grid{columns:4;} }
    .pb-gallery-item {
        break-inside: avoid; margin-bottom: 6px;
        border-radius: 8px; overflow: hidden; cursor: pointer;
        position: relative; background: #1a1a1a;
    }
    .pb-gallery-item img { width: 100%; display: block; }
    .pb-gallery-item .pb-gallery-caption {
        position: absolute; bottom: 0; left: 0; right: 0;
        background: linear-gradient(transparent, rgba(0,0,0,0.75));
        padding: 20px 8px 8px;
        font-size: 11px; color: rgba(255,255,255,0.85);
        opacity: 0; transition: opacity 0.2s;
    }
    .pb-gallery-item:hover .pb-gallery-caption { opacity: 1; }
    .pb-new-toast {
        position: fixed; top: 80px; left: 50%; transform: translateX(-50%) translateY(-20px);
        background: <?php echo esc_js($pwa_color); ?>; color: #fff;
        padding: 10px 20px; border-radius: 20px; font-size: 14px; font-weight: 600;
        opacity: 0; transition: opacity 0.3s, transform 0.3s; pointer-events: none; z-index: 100;
        box-shadow: 0 4px 20px rgba(0,0,0,0.4);
    }
    .pb-new-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
    .pb-gallery-lb {
        display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.95); z-index: 200;
        align-items: center; justify-content: center; flex-direction: column;
    }
    .pb-gallery-lb.open { display: flex; }
    .pb-gallery-lb img { max-width:96vw; max-height:85vh; border-radius:8px; object-fit:contain; }
    .pb-gallery-lb-info { color:#fff; margin-top:12px; font-size:13px; text-align:center; opacity:.75; }
    .pb-gallery-lb-close {
        position:fixed; top:20px; right:20px; background:rgba(255,255,255,0.12);
        border:none; color:#fff; font-size:24px; cursor:pointer; width:44px;height:44px;
        border-radius:50%; display:flex;align-items:center;justify-content:center;
    }
    .pb-empty-state { text-align:center;padding:80px 20px; }
    .pb-empty-state p { color:#555; font-size:16px; margin-top:16px; }
    </style>

    <div class="pb-gallery-page">
        <div class="pb-gallery-header">
            <div>
                <h1><?php echo $event ? esc_html($event->title) : 'Gallery'; ?></h1>
                <div style="margin-top:4px;font-size:13px;color:#888;">
                    <span class="pb-live-dot"></span>Live Gallery &bull; <span id="pb-photo-count"><?php echo count($photos); ?></span> photos
                </div>
            </div>
        </div>

        <div class="pb-gallery-grid" id="pb-gallery-grid">
            <?php if (empty($photos)): ?>
            <div style="column-span:all;" class="pb-empty-state">
                <svg width="64" height="64" fill="none" stroke="#444" viewBox="0 0 24 24">
                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z" stroke-width="1.5"/>
                    <circle cx="12" cy="13" r="4" stroke-width="1.5"/>
                </svg>
                <p>No photos yet. Be the first to upload!</p>
            </div>
            <?php else: foreach ($photos as $p):
                $img_url = $upload_dir['baseurl'] . '/' . $p->file_path;
            ?>
            <div class="pb-gallery-item" data-id="<?php echo esc_attr($p->rand_id); ?>"
                 onclick="pbGalleryLightbox('<?php echo esc_js($img_url); ?>', '<?php echo esc_js($p->guest_name ?: 'Guest'); ?>')">
                <img src="<?php echo esc_url($img_url); ?>" alt="" loading="lazy">
                <div class="pb-gallery-caption"><?php echo esc_html($p->guest_name ?: 'Guest'); ?></div>
            </div>
            <?php endforeach; endif; ?>
        </div>

        <div class="pb-new-toast" id="pb-new-toast">New photos added!</div>

        <!-- Lightbox -->
        <div class="pb-gallery-lb" id="pb-gallery-lb" onclick="this.classList.remove('open')">
            <button class="pb-gallery-lb-close" onclick="document.getElementById('pb-gallery-lb').classList.remove('open')">&times;</button>
            <img id="pb-gallery-lb-img" src="" alt="">
            <div class="pb-gallery-lb-info" id="pb-gallery-lb-info"></div>
        </div>
    </div>

    <script>
    (function(){
        const AJAX     = '<?php echo esc_js($ajax_url); ?>';
        const eventId  = '<?php echo esc_js($event_id); ?>';
        const pollMs   = <?php echo intval($poll_int); ?>;
        let lastTime   = '<?php echo !empty($photos) ? esc_js(end($photos)->created_at ?? '') : ''; ?>';
        let knownIds   = new Set(<?php
            echo json_encode(array_column($photos, 'rand_id'));
        ?>);

        window.pbGalleryLightbox = function(url, name) {
            document.getElementById('pb-gallery-lb-img').src  = url;
            document.getElementById('pb-gallery-lb-info').textContent = name;
            document.getElementById('pb-gallery-lb').classList.add('open');
        };

        function addPhotos(photos) {
            if (!photos.length) return;
            const grid = document.getElementById('pb-gallery-grid');
            // Remove empty state if present
            const empty = grid.querySelector('.pb-empty-state');
            if (empty) empty.parentElement.removeChild(empty);

            photos.forEach(p => {
                if (knownIds.has(p.id)) return;
                knownIds.add(p.id);
                const div = document.createElement('div');
                div.className = 'pb-gallery-item';
                div.setAttribute('data-id', p.id);
                div.onclick = () => pbGalleryLightbox(p.url, p.guest_name);
                div.innerHTML = `<img src="${p.url}" alt="" loading="lazy"><div class="pb-gallery-caption">${p.guest_name}</div>`;
                grid.insertBefore(div, grid.firstChild);
            });

            document.getElementById('pb-photo-count').textContent = knownIds.size;
            const toast = document.getElementById('pb-new-toast');
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 3000);

            if (photos[0]?.created_at) lastTime = photos[0].created_at;
        }

        function pollGallery() {
            const fd = new FormData();
            fd.append('action', 'pb_poll_gallery');
            fd.append('event_id', eventId);
            if (lastTime) fd.append('since', lastTime);
            fetch(AJAX,{method:'POST',body:fd}).then(r=>r.json()).then(json=>{
                if(json.success && json.data.photos.length) addPhotos(json.data.photos);
            }).catch(()=>{});
        }

        if (eventId) setInterval(pollGallery, pollMs);
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ============================================================
// FRONTEND SHORTCODE: DISPLAY / SLIDESHOW PAGE
// ============================================================

function bntm_shortcode_pb_display() {
    $event_id  = sanitize_text_field($_GET['event_id'] ?? '');
    $ajax_url  = admin_url('admin-ajax.php');
    $layout    = bntm_get_setting('pb_display_layout',     'slideshow');
    $speed     = intval(bntm_get_setting('pb_display_speed', '5')) * 1000;
    $show_name = bntm_get_setting('pb_display_show_name',  '1') === '1';
    $poll_int  = intval(bntm_get_setting('pb_display_poll', '10')) * 1000;
    $bg_color  = bntm_get_setting('pb_display_bg_color',   '#111111');

    global $wpdb;
    $event  = $event_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}pb_events WHERE rand_id = %s", $event_id)) : null;
    $photos = [];
    if ($event) {
        $upload_dir = wp_upload_dir();
        $photos     = $wpdb->get_results($wpdb->prepare(
            "SELECT rand_id, file_path, guest_name FROM {$wpdb->prefix}pb_photos WHERE event_id = %d AND status = 'approved' ORDER BY created_at DESC",
            $event->id
        ));
    }

    $upload_dir = wp_upload_dir();

    ob_start();
    ?>
    <style>
    html, body { margin:0;padding:0;width:100%;height:100%;overflow:hidden;background:<?php echo esc_attr($bg_color); ?>; }
    .pb-display-wrap {
        width:100vw; height:100vh; position:relative; overflow:hidden;
        background: <?php echo esc_attr($bg_color); ?>;
        font-family: -apple-system, BlinkMacSystemFont, sans-serif;
    }

    /* Slideshow */
    .pb-slide {
        position:absolute; inset:0; opacity:0; transition:opacity 1s;
        display:flex; align-items:center; justify-content:center;
    }
    .pb-slide.active { opacity:1; z-index:1; }
    .pb-slide img {
        max-width:100%; max-height:100%; object-fit:contain;
        width:100vw; height:100vh;
    }
    .pb-slide-caption {
        position:absolute; bottom:0; left:0; right:0;
        padding:24px 32px 32px;
        background:linear-gradient(transparent, rgba(0,0,0,0.7));
        color:#fff; font-size:22px; font-weight:700;
        <?php echo $show_name ? '' : 'display:none;'; ?>
    }

    /* Grid */
    .pb-display-grid {
        display:none; width:100%;height:100%; overflow:hidden;
        display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr));
        gap:4px; padding:4px;
        <?php echo $layout === 'slideshow' ? 'display:none;' : ''; ?>
    }
    .pb-display-grid-item { overflow:hidden; background:#1a1a1a; position:relative; }
    .pb-display-grid-item img { width:100%;height:100%;object-fit:cover;display:block; }
    .pb-display-grid-item .pb-grid-caption {
        position:absolute;bottom:0;left:0;right:0;
        background:linear-gradient(transparent,rgba(0,0,0,0.6));
        color:#fff;padding:12px;font-size:13px;font-weight:600;
        <?php echo $show_name ? '' : 'display:none;'; ?>
    }

    /* Event title overlay */
    .pb-display-title {
        position:fixed; top:20px; left:28px; z-index:10;
        color:rgba(255,255,255,0.6); font-size:16px; font-weight:700;
        letter-spacing:0.5px; text-shadow:0 2px 8px rgba(0,0,0,0.5);
    }

    /* Empty */
    .pb-display-empty {
        display:flex; flex-direction:column; align-items:center; justify-content:center;
        height:100%; color:rgba(255,255,255,0.2); gap:16px;
    }

    /* Fullscreen btn */
    .pb-fullscreen-btn {
        position:fixed; bottom:20px; right:20px; z-index:20;
        background:rgba(255,255,255,0.1); border:none; color:#fff; cursor:pointer;
        width:44px;height:44px;border-radius:50%; display:flex;align-items:center;justify-content:center;
        backdrop-filter:blur(8px);
    }
    .pb-fullscreen-btn:hover{background:rgba(255,255,255,0.2);}
    </style>

    <div class="pb-display-wrap" id="pb-display-wrap">
        <?php if ($event): ?>
        <div class="pb-display-title"><?php echo esc_html($event->title); ?></div>
        <?php endif; ?>

        <?php if (empty($photos)): ?>
        <div class="pb-display-empty">
            <svg width="80" height="80" fill="none" stroke="currentColor" viewBox="0 0 24 24" opacity=".3">
                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z" stroke-width="1.5"/>
                <circle cx="12" cy="13" r="4" stroke-width="1.5"/>
            </svg>
            <p style="font-size:22px;font-weight:700;color:rgba(255,255,255,0.3);">Waiting for photos...</p>
        </div>
        <?php elseif ($layout === 'slideshow'): ?>
        <div id="pb-slideshow-wrap">
            <?php foreach ($photos as $i => $p):
                $img_url = $upload_dir['baseurl'] . '/' . $p->file_path;
            ?>
            <div class="pb-slide <?php echo $i === 0 ? 'active' : ''; ?>" data-id="<?php echo esc_attr($p->rand_id); ?>">
                <img src="<?php echo esc_url($img_url); ?>" alt="">
                <div class="pb-slide-caption"><?php echo esc_html($p->guest_name ?: 'Guest'); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="pb-display-grid" id="pb-display-grid">
            <?php foreach ($photos as $p):
                $img_url = $upload_dir['baseurl'] . '/' . $p->file_path;
            ?>
            <div class="pb-display-grid-item" data-id="<?php echo esc_attr($p->rand_id); ?>">
                <img src="<?php echo esc_url($img_url); ?>" alt="" loading="lazy">
                <div class="pb-grid-caption"><?php echo esc_html($p->guest_name ?: 'Guest'); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <button class="pb-fullscreen-btn" id="pb-fullscreen-btn" title="Fullscreen">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3" stroke-width="2"/>
            </svg>
        </button>
    </div>

    <script>
    (function(){
        const AJAX    = '<?php echo esc_js($ajax_url); ?>';
        const eventId = '<?php echo esc_js($event_id); ?>';
        const speed   = <?php echo intval($speed); ?>;
        const pollMs  = <?php echo intval($poll_int); ?>;
        const layout  = '<?php echo esc_js($layout); ?>';
        const showName= <?php echo $show_name ? 'true' : 'false'; ?>;
        let   knownIds= new Set(<?php echo json_encode(array_column($photos, 'rand_id')); ?>);
        let   lastTime= '';
        let   currentSlide = 0;

        // Slideshow
        if (layout === 'slideshow') {
            const slides = () => document.querySelectorAll('.pb-slide');
            function nextSlide() {
                const all = slides();
                if (all.length <= 1) return;
                all[currentSlide].classList.remove('active');
                currentSlide = (currentSlide + 1) % all.length;
                all[currentSlide].classList.add('active');
            }
            if (slides().length > 0) setInterval(nextSlide, speed);
        }

        // Poll for new photos
        function pollDisplay() {
            const fd = new FormData();
            fd.append('action','pb_poll_gallery');
            fd.append('event_id', eventId);
            if (lastTime) fd.append('since', lastTime);
            fetch(AJAX,{method:'POST',body:fd}).then(r=>r.json()).then(json=>{
                if (!json.success || !json.data.photos.length) return;
                json.data.photos.forEach(p => {
                    if (knownIds.has(p.id)) return;
                    knownIds.add(p.id);
                    if (p.created_at) lastTime = p.created_at;
                    if (layout === 'slideshow') {
                        const wrap = document.getElementById('pb-slideshow-wrap');
                        if (!wrap) return;
                        const div = document.createElement('div');
                        div.className = 'pb-slide';
                        div.dataset.id = p.id;
                        div.innerHTML = `<img src="${p.url}" alt=""><div class="pb-slide-caption" style="${showName?'':'display:none;'}">${p.guest_name}</div>`;
                        wrap.appendChild(div);
                    } else {
                        const grid = document.getElementById('pb-display-grid');
                        if (!grid) return;
                        const div = document.createElement('div');
                        div.className = 'pb-display-grid-item';
                        div.innerHTML = `<img src="${p.url}" alt="" loading="lazy"><div class="pb-grid-caption" style="${showName?'':'display:none;'}">${p.guest_name}</div>`;
                        grid.insertBefore(div, grid.firstChild);
                    }
                });
            }).catch(()=>{});
        }
        if (eventId) setInterval(pollDisplay, pollMs);

        // Fullscreen
        document.getElementById('pb-fullscreen-btn').addEventListener('click', () => {
            const el = document.getElementById('pb-display-wrap');
            if (!document.fullscreenElement) {
                (el.requestFullscreen || el.webkitRequestFullscreen).call(el);
            } else {
                (document.exitFullscreen || document.webkitExitFullscreen).call(document);
            }
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ============================================================
// PWA MANIFEST
// ============================================================

add_action('template_redirect', 'pb_serve_manifest');
function pb_serve_manifest() {
    if (isset($_GET['pb_sw'])) {
        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: /');
        $manifest_url = add_query_arg('pb_manifest', '1', home_url('/'));
        $upload_page = home_url('/');
        echo "const PB_CACHE = 'pb-shell-v1';\n";
        echo "const PB_ASSETS = ['", esc_js($upload_page), "', '", esc_js($manifest_url), "'];\n";
        echo "self.addEventListener('install', event => {\n";
        echo "  event.waitUntil(caches.open(PB_CACHE).then(cache => cache.addAll(PB_ASSETS)).then(() => self.skipWaiting()));\n";
        echo "});\n";
        echo "self.addEventListener('activate', event => {\n";
        echo "  event.waitUntil(caches.keys().then(keys => Promise.all(keys.map(k => k !== PB_CACHE ? caches.delete(k) : Promise.resolve()))).then(() => self.clients.claim()));\n";
        echo "});\n";
        echo "self.addEventListener('fetch', event => {\n";
        echo "  if (event.request.method !== 'GET') return;\n";
        echo "  event.respondWith(caches.match(event.request).then(cached => cached || fetch(event.request).then(response => {\n";
        echo "    if (!response || response.status !== 200) return response;\n";
        echo "    const clone = response.clone();\n";
        echo "    caches.open(PB_CACHE).then(cache => cache.put(event.request, clone));\n";
        echo "    return response;\n";
        echo "  }).catch(() => caches.match('", esc_js($upload_page), "'))));\n";
        echo "});\n";
        exit;
    }

    if (!isset($_GET['pb_manifest'])) return;
    $pwa_name  = bntm_get_setting('pb_pwa_name',        'Photo Booth');
    $pwa_color = bntm_get_setting('pb_pwa_theme_color', '#6366f1');
    header('Content-Type: application/manifest+json');
    $start_url = add_query_arg('event_id', sanitize_text_field($_GET['event_id'] ?? ''), home_url('/'));
    if (empty($_GET['event_id'])) {
        $start_url = home_url('/');
    }
    echo json_encode([
        'name'             => $pwa_name,
        'short_name'       => $pwa_name,
        'start_url'        => $start_url,
        'display'          => 'standalone',
        'background_color' => '#0f0f0f',
        'theme_color'      => $pwa_color,
        'icons'            => [
            ['src' => admin_url('images/wordpress-logo.svg'), 'sizes' => '512x512', 'type' => 'image/svg+xml'],
        ],
    ]);
    exit;
}

// ============================================================
// HELPER FUNCTIONS
// ============================================================

function pb_resize_image($file_path, $max_width, $mime) {
    if (!function_exists('imagecreatefromjpeg')) return;
    $info = @getimagesize($file_path);
    if (!$info || $info[0] <= $max_width) return;

    $src_w = $info[0]; $src_h = $info[1];
    $new_w = $max_width; $new_h = (int) round($src_h * $max_width / $src_w);

    switch ($mime) {
        case 'image/jpeg': $src = @imagecreatefromjpeg($file_path); break;
        case 'image/png':  $src = @imagecreatefrompng($file_path);  break;
        case 'image/webp': $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file_path) : null; break;
        default:           return;
    }
    if (!$src) return;

    $dst = imagecreatetruecolor($new_w, $new_h);
    if ($mime === 'image/png') {
        imagealphablending($dst, false); imagesavealpha($dst, true);
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_w, $new_h, $src_w, $src_h);

    switch ($mime) {
        case 'image/jpeg': imagejpeg($dst, $file_path, 82); break;
        case 'image/png':  imagepng($dst, $file_path, 7);   break;
        case 'image/webp': if (function_exists('imagewebp')) imagewebp($dst, $file_path, 82); break;
    }
    imagedestroy($src); imagedestroy($dst);
}

function pb_get_stats($business_id) {
    global $wpdb;
    return [
        'total_events'  => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}pb_events WHERE business_id = %d", $business_id)),
        'total_photos'  => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}pb_photos WHERE business_id = %d", $business_id)),
        'pending_photos'=> (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}pb_photos WHERE business_id = %d AND status='pending'", $business_id)),
        'active_event'  => $wpdb->get_var($wpdb->prepare("SELECT title FROM {$wpdb->prefix}pb_events WHERE business_id = %d AND status='active' LIMIT 1", $business_id)),
    ];
}
