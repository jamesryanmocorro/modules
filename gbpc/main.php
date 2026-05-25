<?php
/**
 * Module Name: GDB Converter
 * Module Slug: gdbc
 * Description: Upload and parse .gdb (Generic Binary/Proprietary) files, preview data in browser, then export to CSV download or insert directly into a MySQL table.
 * Version: 1.0.0
 * Author: BNTM Framework
 * Icon: file-convert
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Module constants
define('BNTM_GDBC_PATH', dirname(__FILE__) . '/');
define('BNTM_GDBC_URL', plugin_dir_url(__FILE__));
define('BNTM_GDBC_UPLOAD_DIR', WP_CONTENT_DIR . '/bntm-gdbc-uploads/');


// ─────────────────────────────────────────────────────────────
// 1. CORE MODULE CONFIGURATION FUNCTIONS
// ─────────────────────────────────────────────────────────────

function bntm_gdbc_get_pages() {
    return [
        'GDB Converter' => '[bntm_gdbc_dashboard]',
    ];
}

function bntm_gdbc_get_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $prefix  = $wpdb->prefix;

    return [
        'gdbc_jobs' => "CREATE TABLE {$prefix}gdbc_jobs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            original_filename VARCHAR(255) NOT NULL,
            stored_filename VARCHAR(255) NOT NULL,
            file_size BIGINT UNSIGNED DEFAULT 0,
            row_count INT UNSIGNED DEFAULT 0,
            column_map LONGTEXT DEFAULT NULL,
            status ENUM('pending','parsed','exported','inserted','error') DEFAULT 'pending',
            error_message TEXT DEFAULT NULL,
            target_table VARCHAR(128) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_status (status)
        ) {$charset};",

        'gdbc_rows' => "CREATE TABLE {$prefix}gdbc_rows (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            job_id BIGINT UNSIGNED NOT NULL,
            row_index INT UNSIGNED NOT NULL,
            row_data LONGTEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_job (job_id),
            INDEX idx_row (job_id, row_index)
        ) {$charset};",
    ];
}

function bntm_gdbc_get_shortcodes() {
    return [
        'bntm_gdbc_dashboard' => 'bntm_shortcode_gdbc',
    ];
}

function bntm_gdbc_create_tables() {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $tables = bntm_gdbc_get_tables();
    foreach ($tables as $sql) {
        dbDelta($sql);
    }
    return count($tables);
}

// Ensure upload directory exists
function bntm_gdbc_ensure_upload_dir() {
    if (!file_exists(BNTM_GDBC_UPLOAD_DIR)) {
        wp_mkdir_p(BNTM_GDBC_UPLOAD_DIR);
        file_put_contents(BNTM_GDBC_UPLOAD_DIR . '.htaccess', "deny from all\n");
    }
}


// ─────────────────────────────────────────────────────────────
// 2. AJAX ACTION HOOKS
// ─────────────────────────────────────────────────────────────

add_action('wp_ajax_gdbc_upload_file',       'bntm_ajax_gdbc_upload_file');
add_action('wp_ajax_gdbc_get_preview',       'bntm_ajax_gdbc_get_preview');
add_action('wp_ajax_gdbc_export_csv',        'bntm_ajax_gdbc_export_csv');
add_action('wp_ajax_gdbc_insert_mysql',      'bntm_ajax_gdbc_insert_mysql');
add_action('wp_ajax_gdbc_delete_job',        'bntm_ajax_gdbc_delete_job');
add_action('wp_ajax_gdbc_save_column_map',   'bntm_ajax_gdbc_save_column_map');
add_action('wp_ajax_gdbc_get_db_tables',     'bntm_ajax_gdbc_get_db_tables');


// ─────────────────────────────────────────────────────────────
// 3. MAIN DASHBOARD SHORTCODE
// ─────────────────────────────────────────────────────────────

function bntm_shortcode_gdbc() {
    if (!is_user_logged_in()) {
        return '<div class="bntm-notice">Please log in to use the GDB Converter.</div>';
    }

    $current_user = wp_get_current_user();
    $business_id  = $current_user->ID;
    $active_tab   = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'convert';

    ob_start();
    ?>
    <script>var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';</script>

    <div class="bntm-gdbc-container">

        <!-- Tab Navigation -->
        <div class="bntm-tabs">
            <a href="?tab=convert"  class="bntm-tab <?php echo $active_tab === 'convert'  ? 'active' : ''; ?>">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                Convert
            </a>
            <a href="?tab=history" class="bntm-tab <?php echo $active_tab === 'history' ? 'active' : ''; ?>">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                History
            </a>
            <a href="?tab=settings" class="bntm-tab <?php echo $active_tab === 'settings' ? 'active' : ''; ?>">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Settings
            </a>
        </div>

        <!-- Tab Content -->
        <div class="bntm-tab-content">
            <?php
            if ($active_tab === 'convert')  echo gdbc_convert_tab($business_id);
            elseif ($active_tab === 'history') echo gdbc_history_tab($business_id);
            elseif ($active_tab === 'settings') echo gdbc_settings_tab($business_id);
            ?>
        </div>
    </div>

    <!-- Global Modal -->
    <div id="gdbc-modal-overlay" class="gdbc-modal-overlay" style="display:none;">
        <div class="gdbc-modal">
            <div class="gdbc-modal-header">
                <h3 id="gdbc-modal-title">Preview Data</h3>
                <button id="gdbc-modal-close" class="gdbc-modal-close" aria-label="Close">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div id="gdbc-modal-body" class="gdbc-modal-body"></div>
            <div id="gdbc-modal-footer" class="gdbc-modal-footer"></div>
        </div>
    </div>

    <!-- Column Mapping Modal -->
    <div id="gdbc-colmap-overlay" class="gdbc-modal-overlay" style="display:none;">
        <div class="gdbc-modal gdbc-modal-wide">
            <div class="gdbc-modal-header">
                <h3>Column Mapping</h3>
                <button class="gdbc-modal-close" data-modal="gdbc-colmap-overlay" aria-label="Close">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div id="gdbc-colmap-body" class="gdbc-modal-body"></div>
            <div class="gdbc-modal-footer">
                <button id="gdbc-save-colmap-btn" class="bntm-btn-primary">Save Mapping</button>
                <button class="bntm-btn-secondary" data-modal="gdbc-colmap-overlay">Cancel</button>
            </div>
        </div>
    </div>

    <?php
    // Global styles injected once in dashboard
    echo gdbc_global_styles();
    echo gdbc_global_scripts();

    $content = ob_get_clean();
    return bntm_universal_container('GDB Converter', $content);
}


// ─────────────────────────────────────────────────────────────
// 4. TAB FUNCTIONS
// ─────────────────────────────────────────────────────────────

/**
 * Convert Tab — upload + drag-drop zone
 */
function gdbc_convert_tab($business_id) {
    global $wpdb;
    $jobs_table = $wpdb->prefix . 'gdbc_jobs';

    // Recent jobs (last 5) for quick access
    $recent_jobs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$jobs_table} WHERE business_id = %d ORDER BY created_at DESC LIMIT 5",
        $business_id
    ));

    $nonce = wp_create_nonce('gdbc_upload_nonce');

    ob_start();
    ?>
    <!-- Stats Row -->
    <div class="bntm-stats-row">
        <?php
        $total  = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$jobs_table} WHERE business_id=%d", $business_id));
        $parsed = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$jobs_table} WHERE business_id=%d AND status IN ('parsed','exported','inserted')", $business_id));
        $exported = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$jobs_table} WHERE business_id=%d AND status='exported'", $business_id));
        $inserted = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$jobs_table} WHERE business_id=%d AND status='inserted'", $business_id));
        $total_rows = (int)$wpdb->get_var($wpdb->prepare("SELECT SUM(row_count) FROM {$jobs_table} WHERE business_id=%d", $business_id));
        ?>
        <div class="bntm-stat-card">
            <div class="stat-icon" style="background:linear-gradient(135deg,#0ea5e9,#0284c7)">
                <svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <div class="stat-content">
                <h3>Total Files</h3>
                <p class="stat-number"><?php echo number_format($total); ?></p>
                <span class="stat-label"><?php echo $parsed; ?> parsed</span>
            </div>
        </div>
        <div class="bntm-stat-card">
            <div class="stat-icon" style="background:linear-gradient(135deg,#10b981,#059669)">
                <svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
            </div>
            <div class="stat-content">
                <h3>CSV Exports</h3>
                <p class="stat-number"><?php echo number_format($exported); ?></p>
                <span class="stat-label">downloaded</span>
            </div>
        </div>
        <div class="bntm-stat-card">
            <div class="stat-icon" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed)">
                <svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 7v10c0 2 1 3 3 3h10c2 0 3-1 3-3V7M4 7c0-2 1-3 3-3h10c2 0 3 1 3 3M4 7h16"/>
                </svg>
            </div>
            <div class="stat-content">
                <h3>DB Inserts</h3>
                <p class="stat-number"><?php echo number_format($inserted); ?></p>
                <span class="stat-label">into MySQL</span>
            </div>
        </div>
        <div class="bntm-stat-card">
            <div class="stat-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706)">
                <svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                </svg>
            </div>
            <div class="stat-content">
                <h3>Total Rows</h3>
                <p class="stat-number"><?php echo number_format($total_rows ?: 0); ?></p>
                <span class="stat-label">processed</span>
            </div>
        </div>
    </div>

    <!-- Upload Zone -->
    <div class="bntm-form-section">
        <h3>Upload GDB File</h3>
        <p style="color:#6b7280;margin-top:4px;margin-bottom:20px;">
            Upload a <code>.gdb</code> file to parse, preview, and export its contents.
        </p>

        <div id="gdbc-dropzone" class="gdbc-dropzone" data-nonce="<?php echo $nonce; ?>">
            <div class="gdbc-dropzone-inner">
                <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#94a3b8;margin-bottom:12px;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>
                <p class="gdbc-dropzone-label">Drag &amp; drop your <strong>.gdb</strong> file here</p>
                <p style="color:#94a3b8;font-size:13px;margin:4px 0 16px;">or</p>
                <label for="gdbc-file-input" class="bntm-btn-primary" style="cursor:pointer;">
                    Browse File
                </label>
                <input type="file" id="gdbc-file-input" accept=".gdb,.bin" style="display:none;">
                <p style="font-size:12px;color:#94a3b8;margin-top:12px;">Maximum file size: 1.5 GB</p>
            </div>
        </div>

        <!-- Upload Progress -->
        <div id="gdbc-upload-progress" style="display:none;margin-top:16px;">
            <div class="gdbc-progress-bar-wrap">
                <div id="gdbc-progress-bar" class="gdbc-progress-bar" style="width:0%"></div>
            </div>
            <p id="gdbc-progress-label" style="font-size:13px;color:#6b7280;margin-top:8px;">Uploading...</p>
        </div>

        <div id="gdbc-upload-message" style="margin-top:12px;"></div>
    </div>

    <!-- Recent Jobs Quick Access -->
    <?php if (!empty($recent_jobs)): ?>
    <div class="bntm-form-section">
        <h3>Recent Conversions</h3>
        <div class="bntm-table-wrapper" style="margin-top:16px;">
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th>Filename</th>
                        <th>Rows</th>
                        <th>Status</th>
                        <th>Uploaded</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_jobs as $job): ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <svg width="16" height="16" fill="none" stroke="#94a3b8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <?php echo esc_html($job->original_filename); ?>
                            </div>
                        </td>
                        <td><?php echo number_format($job->row_count); ?></td>
                        <td><?php echo gdbc_status_badge($job->status); ?></td>
                        <td style="color:#6b7280;font-size:13px;"><?php echo date('M d, Y H:i', strtotime($job->created_at)); ?></td>
                        <td>
                            <?php if (in_array($job->status, ['parsed','exported','inserted'])): ?>
                            <button class="bntm-btn-small bntm-btn-secondary gdbc-preview-btn"
                                    data-job-id="<?php echo $job->id; ?>"
                                    data-rand-id="<?php echo esc_attr($job->rand_id); ?>"
                                    data-nonce="<?php echo wp_create_nonce('gdbc_preview_' . $job->id); ?>">
                                Preview
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="margin-top:12px;">
            <a href="?tab=history" style="font-size:13px;color:#0ea5e9;">View all conversions &rarr;</a>
        </div>
    </div>
    <?php endif; ?>

    <?php
    return ob_get_clean();
}


/**
 * History Tab
 */
function gdbc_history_tab($business_id) {
    global $wpdb;
    $jobs_table = $wpdb->prefix . 'gdbc_jobs';

    $page      = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page  = 20;
    $offset    = ($page - 1) * $per_page;
    $status_f  = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : '';

    $where = $wpdb->prepare("WHERE business_id = %d", $business_id);
    if ($status_f) $where .= $wpdb->prepare(" AND status = %s", $status_f);

    $total_items = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$jobs_table} {$where}");
    $jobs = $wpdb->get_results("SELECT * FROM {$jobs_table} {$where} ORDER BY created_at DESC LIMIT {$per_page} OFFSET {$offset}");
    $total_pages = ceil($total_items / $per_page);

    $del_nonce = wp_create_nonce('gdbc_delete_nonce');

    ob_start();
    ?>
    <div class="bntm-form-section">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
            <h3 style="margin:0;">Conversion History</h3>
            <div style="display:flex;gap:10px;align-items:center;">
                <select id="gdbc-status-filter" class="bntm-select" onchange="gdbcFilterStatus(this.value)">
                    <option value="">All Statuses</option>
                    <option value="parsed"   <?php selected($status_f,'parsed'); ?>>Parsed</option>
                    <option value="exported" <?php selected($status_f,'exported'); ?>>CSV Exported</option>
                    <option value="inserted" <?php selected($status_f,'inserted'); ?>>MySQL Inserted</option>
                    <option value="error"    <?php selected($status_f,'error'); ?>>Error</option>
                </select>
            </div>
        </div>

        <div class="bntm-table-wrapper">
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="gdbc-select-all"></th>
                        <th>File</th>
                        <th>Size</th>
                        <th>Rows</th>
                        <th>Status</th>
                        <th>Target Table</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($jobs)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center;padding:40px;color:#94a3b8;">
                            No conversion jobs found.
                        </td>
                    </tr>
                    <?php else: foreach ($jobs as $job): ?>
                    <tr data-job-id="<?php echo $job->id; ?>">
                        <td><input type="checkbox" class="gdbc-row-check" value="<?php echo $job->id; ?>"></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <svg width="14" height="14" fill="none" stroke="#94a3b8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <span style="font-size:13px;"><?php echo esc_html($job->original_filename); ?></span>
                            </div>
                        </td>
                        <td style="color:#6b7280;font-size:13px;"><?php echo gdbc_format_bytes($job->file_size); ?></td>
                        <td><?php echo number_format($job->row_count); ?></td>
                        <td><?php echo gdbc_status_badge($job->status); ?></td>
                        <td style="font-size:13px;color:#6b7280;">
                            <?php echo $job->target_table ? esc_html($job->target_table) : '—'; ?>
                        </td>
                        <td style="font-size:13px;color:#6b7280;"><?php echo date('M d, Y H:i', strtotime($job->created_at)); ?></td>
                        <td>
                            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                <?php if (in_array($job->status, ['parsed','exported','inserted'])): ?>
                                <button class="bntm-btn-small bntm-btn-secondary gdbc-preview-btn"
                                        data-job-id="<?php echo $job->id; ?>"
                                        data-nonce="<?php echo wp_create_nonce('gdbc_preview_' . $job->id); ?>">
                                    Preview
                                </button>
                                <button class="bntm-btn-small bntm-btn-primary gdbc-export-csv-btn"
                                        data-job-id="<?php echo $job->id; ?>"
                                        data-filename="<?php echo esc_attr(pathinfo($job->original_filename, PATHINFO_FILENAME)); ?>"
                                        data-nonce="<?php echo wp_create_nonce('gdbc_export_' . $job->id); ?>">
                                    CSV
                                </button>
                                <button class="bntm-btn-small gdbc-btn-mysql gdbc-insert-mysql-btn"
                                        data-job-id="<?php echo $job->id; ?>"
                                        data-nonce="<?php echo wp_create_nonce('gdbc_insert_' . $job->id); ?>">
                                    MySQL
                                </button>
                                <?php endif; ?>
                                <button class="bntm-btn-small bntm-btn-danger gdbc-delete-btn"
                                        data-job-id="<?php echo $job->id; ?>"
                                        data-nonce="<?php echo $del_nonce; ?>">
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Bulk Actions -->
        <div style="display:flex;gap:10px;align-items:center;margin-top:14px;">
            <span id="gdbc-selected-count" style="font-size:13px;color:#6b7280;"></span>
            <button id="gdbc-bulk-delete-btn" class="bntm-btn-small bntm-btn-danger"
                    style="display:none;" data-nonce="<?php echo $del_nonce; ?>">
                Delete Selected
            </button>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="gdbc-pagination">
            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                <a href="?tab=history&paged=<?php echo $p; ?><?php echo $status_f ? '&status_filter='.$status_f : ''; ?>"
                   class="gdbc-page-link <?php echo $p === $page ? 'active' : ''; ?>">
                    <?php echo $p; ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
    function gdbcFilterStatus(val) {
        const url = new URL(window.location.href);
        url.searchParams.set('tab','history');
        if (val) url.searchParams.set('status_filter', val);
        else url.searchParams.delete('status_filter');
        url.searchParams.delete('paged');
        window.location.href = url.toString();
    }

    (function(){
        const selectAll = document.getElementById('gdbc-select-all');
        const countLabel = document.getElementById('gdbc-selected-count');
        const bulkDeleteBtn = document.getElementById('gdbc-bulk-delete-btn');

        function updateBulk() {
            const checked = document.querySelectorAll('.gdbc-row-check:checked');
            countLabel.textContent = checked.length > 0 ? checked.length + ' selected' : '';
            bulkDeleteBtn.style.display = checked.length > 0 ? 'inline-flex' : 'none';
        }

        if (selectAll) {
            selectAll.addEventListener('change', function(){
                document.querySelectorAll('.gdbc-row-check').forEach(c => c.checked = this.checked);
                updateBulk();
            });
        }

        document.querySelectorAll('.gdbc-row-check').forEach(c => c.addEventListener('change', updateBulk));

        if (bulkDeleteBtn) {
            bulkDeleteBtn.addEventListener('click', function(){
                const ids = Array.from(document.querySelectorAll('.gdbc-row-check:checked')).map(c => c.value);
                if (!ids.length || !confirm('Delete ' + ids.length + ' job(s)? This cannot be undone.')) return;
                gdbcBulkDelete(ids, this.dataset.nonce);
            });
        }
    })();
    </script>
    <?php
    return ob_get_clean();
}


/**
 * Settings Tab
 */
function gdbc_settings_tab($business_id) {
    $max_size       = bntm_get_setting('gdbc_max_file_size', '1536');
    $delimiter      = bntm_get_setting('gdbc_default_delimiter', ',');
    $encoding       = bntm_get_setting('gdbc_default_encoding', 'UTF-8');
    $nonce          = wp_create_nonce('gdbc_settings_nonce');

    ob_start();
    ?>
    <div class="bntm-form-section">
        <h3>Converter Settings</h3>
        <p style="color:#6b7280;margin-top:4px;margin-bottom:24px;">Configure default behavior for GDB file parsing and export.</p>

        <div class="bntm-form-grid">
            <div class="bntm-form-group">
                <label>Max Upload Size (MB)</label>
                <input type="number" id="gdbc-max-size" class="bntm-input" value="<?php echo esc_attr($max_size); ?>" min="1" max="1536">
                <small>Maximum size for uploaded .gdb files (up to 1536 MB = 1.5 GB).</small>
            </div>
            <div class="bntm-form-group">
                <label>Default CSV Delimiter</label>
                <select id="gdbc-delimiter" class="bntm-select">
                    <option value=","  <?php selected($delimiter, ','); ?>>Comma ( , )</option>
                    <option value=";"  <?php selected($delimiter, ';'); ?>>Semicolon ( ; )</option>
                    <option value="|"  <?php selected($delimiter, '|'); ?>>Pipe ( | )</option>
                    <option value="\t" <?php selected($delimiter, "\t"); ?>>Tab</option>
                </select>
                <small>Field delimiter used in CSV exports.</small>
            </div>
            <div class="bntm-form-group">
                <label>Output Encoding</label>
                <select id="gdbc-encoding" class="bntm-select">
                    <option value="UTF-8"       <?php selected($encoding, 'UTF-8'); ?>>UTF-8</option>
                    <option value="UTF-8-BOM"   <?php selected($encoding, 'UTF-8-BOM'); ?>>UTF-8 with BOM (Excel)</option>
                    <option value="ISO-8859-1"  <?php selected($encoding, 'ISO-8859-1'); ?>>ISO-8859-1</option>
                </select>
                <small>Character encoding for CSV output. Use UTF-8 with BOM for Excel compatibility.</small>
            </div>
        </div>

        <div style="margin-top:24px;display:flex;gap:10px;align-items:center;">
            <button id="gdbc-save-settings-btn" class="bntm-btn-primary" data-nonce="<?php echo $nonce; ?>">
                Save Settings
            </button>
            <div id="gdbc-settings-message"></div>
        </div>
    </div>

    <div class="bntm-form-section">
        <h3>Upload Directory</h3>
        <p style="color:#6b7280;margin-top:4px;">
            Uploaded files are stored temporarily at:
        </p>
        <code style="display:block;background:#f1f5f9;padding:10px 14px;border-radius:6px;font-size:13px;margin-top:8px;word-break:break-all;">
            <?php echo esc_html(BNTM_GDBC_UPLOAD_DIR); ?>
        </code>
        <p style="color:#6b7280;font-size:13px;margin-top:10px;">
            The directory is protected from public access via <code>.htaccess</code>.
            Only parsed data is stored in the database; raw files are kept for re-export.
        </p>
    </div>

    <script>
    (function(){
        document.getElementById('gdbc-save-settings-btn').addEventListener('click', function(){
            const btn = this;
            const nonce = btn.dataset.nonce;
            const fd = new FormData();
            fd.append('action', 'gdbc_save_settings');
            fd.append('nonce', nonce);
            fd.append('max_file_size', document.getElementById('gdbc-max-size').value);
            fd.append('default_delimiter', document.getElementById('gdbc-delimiter').value);
            fd.append('default_encoding', document.getElementById('gdbc-encoding').value);

            btn.disabled = true; btn.textContent = 'Saving...';

            fetch(ajaxurl, {method:'POST', body:fd})
            .then(r => r.json())
            .then(json => {
                const msg = document.getElementById('gdbc-settings-message');
                msg.innerHTML = '<span class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '" style="display:inline-block;padding:6px 14px;">' + json.data.message + '</span>';
                btn.disabled = false; btn.textContent = 'Save Settings';
                setTimeout(() => msg.innerHTML = '', 4000);
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}


// ─────────────────────────────────────────────────────────────
// 5. AJAX HANDLERS
// ─────────────────────────────────────────────────────────────

/**
 * Upload & Parse GDB File
 */
function bntm_ajax_gdbc_upload_file() {
    check_ajax_referer('gdbc_upload_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    if (empty($_FILES['gdbc_file'])) {
        wp_send_json_error(['message' => 'No file uploaded.']);
    }

    $file = $_FILES['gdbc_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error(['message' => 'Upload error code: ' . $file['error']]);
    }

    $max_mb = (int)bntm_get_setting('gdbc_max_file_size', '1536');
    if ($file['size'] > $max_mb * 1024 * 1024) {
        wp_send_json_error(['message' => "File exceeds maximum allowed size of {$max_mb} MB."]);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['gdb', 'bin'])) {
        wp_send_json_error(['message' => 'Only .gdb or .bin files are allowed.']);
    }

    bntm_gdbc_ensure_upload_dir();

    $rand_id       = bntm_rand_id();
    $stored_name   = $rand_id . '_' . sanitize_file_name($file['name']);
    $stored_path   = BNTM_GDBC_UPLOAD_DIR . $stored_name;

    if (!move_uploaded_file($file['tmp_name'], $stored_path)) {
        wp_send_json_error(['message' => 'Failed to move uploaded file.']);
    }

    // Parse the GDB file
    $parse_result = gdbc_parse_gdb_file($stored_path);

    if (!$parse_result['success']) {
        @unlink($stored_path);
        wp_send_json_error(['message' => $parse_result['message']]);
    }

    global $wpdb;
    $jobs_table = $wpdb->prefix . 'gdbc_jobs';
    $rows_table = $wpdb->prefix . 'gdbc_rows';
    $business_id = get_current_user_id();

    $wpdb->insert($jobs_table, [
        'rand_id'           => $rand_id,
        'business_id'       => $business_id,
        'original_filename' => $file['name'],
        'stored_filename'   => $stored_name,
        'file_size'         => $file['size'],
        'row_count'         => count($parse_result['rows']),
        'column_map'        => json_encode($parse_result['headers']),
        'status'            => 'parsed',
    ], ['%s','%d','%s','%s','%d','%d','%s','%s']);

    $job_id = $wpdb->insert_id;

    // Insert rows in batches of 100
    $rows = $parse_result['rows'];
    foreach (array_chunk($rows, 100) as $chunk_idx => $chunk) {
        foreach ($chunk as $row_idx => $row) {
            $wpdb->insert($rows_table, [
                'job_id'    => $job_id,
                'row_index' => ($chunk_idx * 100) + $row_idx,
                'row_data'  => json_encode($row),
            ], ['%d','%d','%s']);
        }
    }

    wp_send_json_success([
        'message'   => 'File parsed successfully! ' . count($rows) . ' rows found.',
        'job_id'    => $job_id,
        'rand_id'   => $rand_id,
        'row_count' => count($rows),
        'headers'   => $parse_result['headers'],
        'preview'   => array_slice($rows, 0, 5),
        'nonce_preview' => wp_create_nonce('gdbc_preview_' . $job_id),
        'nonce_export'  => wp_create_nonce('gdbc_export_' . $job_id),
        'nonce_insert'  => wp_create_nonce('gdbc_insert_' . $job_id),
        'filename_base' => pathinfo($file['name'], PATHINFO_FILENAME),
    ]);
}

/**
 * Get Preview Data
 */
function bntm_ajax_gdbc_get_preview() {
    $job_id = intval($_POST['job_id'] ?? 0);
    check_ajax_referer('gdbc_preview_' . $job_id, 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $jobs_table = $wpdb->prefix . 'gdbc_jobs';
    $rows_table = $wpdb->prefix . 'gdbc_rows';

    $job = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$jobs_table} WHERE id = %d AND business_id = %d",
        $job_id, get_current_user_id()
    ));

    if (!$job) {
        wp_send_json_error(['message' => 'Job not found.']);
    }

    $limit  = max(1, min(500, intval($_POST['limit'] ?? 50)));
    $offset = max(0, intval($_POST['offset'] ?? 0));

    $raw_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT row_data FROM {$rows_table} WHERE job_id = %d ORDER BY row_index ASC LIMIT %d OFFSET %d",
        $job_id, $limit, $offset
    ));

    $rows = array_map(fn($r) => json_decode($r->row_data, true), $raw_rows);

    wp_send_json_success([
        'headers'   => json_decode($job->column_map, true),
        'rows'      => $rows,
        'total'     => $job->row_count,
        'limit'     => $limit,
        'offset'    => $offset,
        'job_id'    => $job_id,
        'filename'  => $job->original_filename,
    ]);
}

/**
 * Export to CSV
 */
function bntm_ajax_gdbc_export_csv() {
    $job_id = intval($_POST['job_id'] ?? 0);
    check_ajax_referer('gdbc_export_' . $job_id, 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $jobs_table = $wpdb->prefix . 'gdbc_jobs';
    $rows_table = $wpdb->prefix . 'gdbc_rows';

    $job = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$jobs_table} WHERE id = %d AND business_id = %d",
        $job_id, get_current_user_id()
    ));

    if (!$job) {
        wp_send_json_error(['message' => 'Job not found.']);
    }

    $headers  = json_decode($job->column_map, true);
    $raw_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT row_data FROM {$rows_table} WHERE job_id = %d ORDER BY row_index ASC",
        $job_id
    ));

    $delimiter = bntm_get_setting('gdbc_default_delimiter', ',');
    $encoding  = bntm_get_setting('gdbc_default_encoding', 'UTF-8');

    $csv = '';
    if ($encoding === 'UTF-8-BOM') {
        $csv .= "\xEF\xBB\xBF"; // BOM
    }

    // Header row
    $csv .= implode($delimiter, array_map('gdbc_csv_escape', $headers)) . "\r\n";

    foreach ($raw_rows as $raw) {
        $row = json_decode($raw->row_data, true);
        $line = [];
        foreach ($headers as $h) {
            $line[] = gdbc_csv_escape($row[$h] ?? '');
        }
        $csv .= implode($delimiter, $line) . "\r\n";
    }

    // Mark as exported
    $wpdb->update($jobs_table, ['status' => 'exported'], ['id' => $job_id], ['%s'], ['%d']);

    $filename = pathinfo($job->original_filename, PATHINFO_FILENAME) . '_' . date('Ymd_His') . '.csv';

    wp_send_json_success([
        'message'   => 'CSV generated.',
        'csv'       => base64_encode($csv),
        'filename'  => $filename,
    ]);
}

/**
 * Insert into MySQL Table
 */
function bntm_ajax_gdbc_insert_mysql() {
    $job_id = intval($_POST['job_id'] ?? 0);
    check_ajax_referer('gdbc_insert_' . $job_id, 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $jobs_table    = $wpdb->prefix . 'gdbc_jobs';
    $rows_table    = $wpdb->prefix . 'gdbc_rows';

    $job = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$jobs_table} WHERE id = %d AND business_id = %d",
        $job_id, get_current_user_id()
    ));

    if (!$job) {
        wp_send_json_error(['message' => 'Job not found.']);
    }

    $target_table = sanitize_text_field($_POST['target_table'] ?? '');
    $col_map      = json_decode(stripslashes($_POST['column_map'] ?? '{}'), true);

    if (empty($target_table)) {
        wp_send_json_error(['message' => 'Please select a target table.']);
    }

    // Verify table exists in this WP installation
    $all_tables   = $wpdb->get_col("SHOW TABLES");
    if (!in_array($target_table, $all_tables)) {
        wp_send_json_error(['message' => 'Table does not exist in database.']);
    }

    $raw_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT row_data FROM {$rows_table} WHERE job_id = %d ORDER BY row_index ASC",
        $job_id
    ));

    $headers   = json_decode($job->column_map, true);
    $inserted  = 0;
    $failed    = 0;

    $wpdb->query('START TRANSACTION');

    foreach ($raw_rows as $raw) {
        $row  = json_decode($raw->row_data, true);
        $data = [];

        // Map columns
        foreach ($col_map as $gdb_col => $db_col) {
            if (!empty($db_col) && isset($row[$gdb_col])) {
                $data[$db_col] = $row[$gdb_col];
            }
        }

        if (empty($data)) {
            $failed++;
            continue;
        }

        $formats = array_fill(0, count($data), '%s');
        $result  = $wpdb->insert($target_table, $data, $formats);

        if ($result !== false) $inserted++;
        else $failed++;
    }

    if ($failed > count($raw_rows) / 2) {
        $wpdb->query('ROLLBACK');
        wp_send_json_error(['message' => "Too many insert failures ({$failed}). Transaction rolled back."]);
    }

    $wpdb->query('COMMIT');

    // Update job
    $wpdb->update($jobs_table, [
        'status'       => 'inserted',
        'target_table' => $target_table,
    ], ['id' => $job_id], ['%s','%s'], ['%d']);

    wp_send_json_success([
        'message'  => "Inserted {$inserted} row(s) into `{$target_table}`." . ($failed ? " {$failed} row(s) skipped." : ''),
        'inserted' => $inserted,
        'failed'   => $failed,
    ]);
}

/**
 * Delete Job
 */
function bntm_ajax_gdbc_delete_job() {
    check_ajax_referer('gdbc_delete_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $jobs_table = $wpdb->prefix . 'gdbc_jobs';
    $rows_table = $wpdb->prefix . 'gdbc_rows';

    $job_ids = json_decode(stripslashes($_POST['job_ids'] ?? '[]'), true);
    if (empty($job_ids) || !is_array($job_ids)) {
        wp_send_json_error(['message' => 'No jobs specified.']);
    }

    $business_id = get_current_user_id();
    $deleted     = 0;

    foreach ($job_ids as $job_id) {
        $job_id = intval($job_id);
        $job    = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$jobs_table} WHERE id = %d AND business_id = %d",
            $job_id, $business_id
        ));

        if (!$job) continue;

        // Remove stored file
        $stored_path = BNTM_GDBC_UPLOAD_DIR . $job->stored_filename;
        if (file_exists($stored_path)) {
            @unlink($stored_path);
        }

        // Remove rows
        $wpdb->delete($rows_table, ['job_id' => $job_id], ['%d']);

        // Remove job
        if ($wpdb->delete($jobs_table, ['id' => $job_id], ['%d'])) {
            $deleted++;
        }
    }

    wp_send_json_success(['message' => "Deleted {$deleted} job(s).", 'deleted' => $deleted]);
}

/**
 * Get DB Tables (for MySQL insert target)
 */
function bntm_ajax_gdbc_get_db_tables() {
    check_ajax_referer('gdbc_insert_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $tables = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}%'");

    // Get columns for each table
    $table_data = [];
    foreach ($tables as $table) {
        $cols = $wpdb->get_results("SHOW COLUMNS FROM `{$table}`");
        $table_data[$table] = array_map(fn($c) => $c->Field, $cols);
    }

    wp_send_json_success(['tables' => $table_data]);
}

/**
 * Save Column Map
 */
function bntm_ajax_gdbc_save_column_map() {
    check_ajax_referer('gdbc_colmap_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $job_id    = intval($_POST['job_id'] ?? 0);
    $col_map   = json_decode(stripslashes($_POST['column_map'] ?? '{}'), true);

    global $wpdb;
    $jobs_table = $wpdb->prefix . 'gdbc_jobs';

    $job = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$jobs_table} WHERE id = %d AND business_id = %d",
        $job_id, get_current_user_id()
    ));

    if (!$job) {
        wp_send_json_error(['message' => 'Job not found.']);
    }

    $wpdb->update($jobs_table, ['column_map' => json_encode($col_map)], ['id' => $job_id], ['%s'], ['%d']);

    wp_send_json_success(['message' => 'Column mapping saved.']);
}

// Settings save
add_action('wp_ajax_gdbc_save_settings', function() {
    check_ajax_referer('gdbc_settings_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message' => 'Unauthorized']); }

    bntm_set_setting('gdbc_max_file_size',      sanitize_text_field($_POST['max_file_size'] ?? '1536'));
    bntm_set_setting('gdbc_default_delimiter',  sanitize_text_field($_POST['default_delimiter'] ?? ','));
    bntm_set_setting('gdbc_default_encoding',   sanitize_text_field($_POST['default_encoding'] ?? 'UTF-8'));

    wp_send_json_success(['message' => 'Settings saved successfully.']);
});


// ─────────────────────────────────────────────────────────────
// 6. HELPER / UTILITY FUNCTIONS
// ─────────────────────────────────────────────────────────────

/**
 * Parse a .gdb binary file into structured rows.
 *
 * GDB files are treated as structured binary records.
 * Strategy (progressive fallback):
 *   1. Check for a printable-text section map at file head (magic byte 0x47 0x42 0x50)
 *   2. Try to extract null-delimited or newline-delimited text records
 *   3. Fall back to hex-dump rows for fully opaque binary content
 */
function gdbc_parse_gdb_file($path) {
    $fh = @fopen($path, 'rb');
    if (!$fh) {
        return ['success' => false, 'message' => 'Cannot open file for reading.'];
    }

    $file_size = filesize($path);
    $magic     = fread($fh, 3);
    rewind($fh);

    $content = fread($fh, $file_size);
    fclose($fh);

    // --- Strategy 1: GDB magic header (0x47 0x42 0x50 = "GBP") ---
    if (strlen($magic) === 3 && $magic === "GBP") {
        return gdbc_parse_gbp_structured($content);
    }

    // --- Strategy 2: Looks like CSV/text wrapped in binary ---
    $printable_ratio = gdbc_printable_ratio($content);
    if ($printable_ratio > 0.85) {
        return gdbc_parse_text_content($content);
    }

    // --- Strategy 3: Delimited binary records ---
    $result = gdbc_parse_binary_records($content);
    if ($result['success'] && count($result['rows']) > 0) {
        return $result;
    }

    // --- Strategy 4: Hex dump fallback ---
    return gdbc_parse_hexdump($content);
}

/**
 * Parse a formally structured GDB file with magic header "GBP"
 * Format assumed:
 *   Bytes 0-2:  "GBP" magic
 *   Byte  3:    version (uint8)
 *   Bytes 4-5:  number of columns (uint16 LE)
 *   Bytes 6-7:  number of rows    (uint16 LE)
 *   Then: column names as null-terminated strings
 *   Then: rows, each field null-terminated string
 */
function gdbc_parse_gbp_structured($content) {
    $len = strlen($content);
    if ($len < 8) return ['success'=>false,'message'=>'File too short to be a valid GDB.'];

    $pos     = 3; // skip magic
    $version = ord($content[$pos++]);
    $num_cols = unpack('v', substr($content, $pos, 2))[1]; $pos += 2;
    $num_rows = unpack('v', substr($content, $pos, 2))[1]; $pos += 2;

    if ($num_cols === 0 || $num_cols > 500) {
        return ['success'=>false,'message'=>'Invalid column count in GDB header.'];
    }

    // Read column names
    $headers = [];
    for ($c = 0; $c < $num_cols; $c++) {
        $end = strpos($content, "\0", $pos);
        if ($end === false) return ['success'=>false,'message'=>'Malformed column name at position '.$pos];
        $headers[] = substr($content, $pos, $end - $pos);
        $pos = $end + 1;
    }

    // Read rows
    $rows = [];
    for ($r = 0; $r < $num_rows && $pos < $len; $r++) {
        $row = [];
        for ($c = 0; $c < $num_cols; $c++) {
            $end = strpos($content, "\0", $pos);
            if ($end === false) {
                // Last field might not have null terminator
                $row[$headers[$c]] = substr($content, $pos);
                $pos = $len;
                break;
            }
            $row[$headers[$c]] = substr($content, $pos, $end - $pos);
            $pos = $end + 1;
        }
        if (!empty($row)) $rows[] = $row;
    }

    if (empty($rows)) {
        return ['success'=>false,'message'=>'GDB file parsed but contains no data rows.'];
    }

    return ['success'=>true,'headers'=>$headers,'rows'=>$rows,'message'=>''];
}

/**
 * Parse text-heavy content (CSV/TSV/pipe-delimited embedded in binary wrapper)
 */
function gdbc_parse_text_content($content) {
    // Strip null bytes
    $clean = str_replace("\0", '', $content);
    // Normalise line endings
    $clean = str_replace(["\r\n","\r"], "\n", $clean);
    $lines = array_filter(array_map('trim', explode("\n", $clean)));

    if (count($lines) < 2) {
        return ['success'=>false,'message'=>'Not enough lines found in file content.'];
    }

    // Detect delimiter
    $sample = array_slice(array_values($lines), 0, 5);
    $delimiters = [',', ';', "\t", '|'];
    $best_delim = ',';
    $best_count = 0;
    foreach ($delimiters as $d) {
        $cnt = array_sum(array_map(fn($l) => substr_count($l, $d), $sample));
        if ($cnt > $best_count) { $best_count = $cnt; $best_delim = $d; }
    }

    $lines = array_values($lines);
    $headers = str_getcsv(array_shift($lines), $best_delim);
    $headers = array_map('trim', $headers);

    if (empty(array_filter($headers))) {
        return ['success'=>false,'message'=>'Could not detect column headers.'];
    }

    $rows = [];
    foreach ($lines as $line) {
        $fields = str_getcsv($line, $best_delim);
        $row    = [];
        foreach ($headers as $i => $h) {
            $row[$h] = isset($fields[$i]) ? trim($fields[$i]) : '';
        }
        $rows[] = $row;
    }

    return ['success'=>true,'headers'=>$headers,'rows'=>$rows,'message'=>''];
}

/**
 * Parse binary records using fixed-width chunking (heuristic)
 */
function gdbc_parse_binary_records($content) {
    // Try to find repeating null-delimited string blocks
    $parts   = explode("\0", $content);
    $parts   = array_values(array_filter(array_map('trim', $parts), fn($p) => strlen($p) > 0 && strlen($p) < 256));

    if (count($parts) < 4) {
        return ['success'=>false,'message'=>'','rows'=>[]];
    }

    // Guess column count (most common group size)
    $group_sizes = [];
    for ($g = 2; $g <= min(20, intval(count($parts)/2)); $g++) {
        if (count($parts) % $g === 0) $group_sizes[$g] = 0;
    }

    if (empty($group_sizes)) {
        // Just use 4 columns
        $num_cols = min(4, count($parts));
    } else {
        $num_cols = array_keys($group_sizes)[0];
    }

    // First group = headers
    $headers = array_slice($parts, 0, $num_cols);
    $data    = array_slice($parts, $num_cols);
    $rows    = [];

    for ($i = 0; $i + $num_cols <= count($data); $i += $num_cols) {
        $row = [];
        for ($c = 0; $c < $num_cols; $c++) {
            $row[$headers[$c]] = $data[$i + $c] ?? '';
        }
        $rows[] = $row;
    }

    if (count($rows) === 0) {
        return ['success'=>false,'message'=>'','rows'=>[]];
    }

    return ['success'=>true,'headers'=>$headers,'rows'=>$rows,'message'=>''];
}

/**
 * Hex-dump fallback: each 16-byte chunk becomes one row
 */
function gdbc_parse_hexdump($content) {
    $chunk_size = 16;
    $len        = strlen($content);
    $headers    = ['offset','hex','ascii'];
    $rows       = [];

    for ($i = 0; $i < $len; $i += $chunk_size) {
        $chunk   = substr($content, $i, $chunk_size);
        $hex     = implode(' ', array_map(fn($b) => sprintf('%02X', ord($b)), str_split($chunk)));
        $ascii   = preg_replace('/[^\x20-\x7E]/', '.', $chunk);
        $rows[]  = [
            'offset' => sprintf('0x%08X', $i),
            'hex'    => $hex,
            'ascii'  => $ascii,
        ];
    }

    return ['success'=>true,'headers'=>$headers,'rows'=>$rows,'message'=>'Opaque binary — shown as hex dump.'];
}

/**
 * Calculate ratio of printable ASCII characters in a string
 */
function gdbc_printable_ratio($content) {
    $len = strlen($content);
    if ($len === 0) return 0;
    $printable = preg_match_all('/[\x09\x0A\x0D\x20-\x7E]/', $content);
    return $printable / $len;
}

/**
 * Escape a value for CSV output
 */
function gdbc_csv_escape($value) {
    $value = (string)$value;
    if (strpos($value, '"') !== false || strpos($value, ',') !== false
        || strpos($value, "\n") !== false || strpos($value, "\r") !== false) {
        return '"' . str_replace('"', '""', $value) . '"';
    }
    return $value;
}

/**
 * Render status badge HTML
 */
function gdbc_status_badge($status) {
    $map = [
        'pending'  => ['color'=>'#92400e','bg'=>'#fef3c7','label'=>'Pending'],
        'parsed'   => ['color'=>'#1e40af','bg'=>'#dbeafe','label'=>'Parsed'],
        'exported' => ['color'=>'#065f46','bg'=>'#d1fae5','label'=>'CSV Exported'],
        'inserted' => ['color'=>'#4c1d95','bg'=>'#ede9fe','label'=>'MySQL Inserted'],
        'error'    => ['color'=>'#991b1b','bg'=>'#fee2e2','label'=>'Error'],
    ];
    $s = $map[$status] ?? ['color'=>'#6b7280','bg'=>'#f3f4f6','label'=>ucfirst($status)];
    return '<span style="display:inline-block;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:600;color:'
        . $s['color'] . ';background:' . $s['bg'] . ';">' . $s['label'] . '</span>';
}

/**
 * Format bytes to human-readable
 */
function gdbc_format_bytes($bytes) {
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}


// ─────────────────────────────────────────────────────────────
// 7. GLOBAL STYLES
// ─────────────────────────────────────────────────────────────

function gdbc_global_styles() {
    return '<style>
/* ── GDB Converter Global Styles ── */
.bntm-gdbc-container { font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; }

/* Drop Zone */
.gdbc-dropzone {
    border: 2px dashed #cbd5e1;
    border-radius: 12px;
    padding: 48px 32px;
    text-align: center;
    cursor: pointer;
    transition: border-color .2s, background .2s;
    background: #f8fafc;
}
.gdbc-dropzone.drag-over {
    border-color: #0ea5e9;
    background: #f0f9ff;
}
.gdbc-dropzone-label { font-size: 15px; color: #334155; margin: 0; }

/* Progress Bar */
.gdbc-progress-bar-wrap {
    background: #e2e8f0;
    border-radius: 99px;
    height: 8px;
    overflow: hidden;
}
.gdbc-progress-bar {
    height: 100%;
    background: linear-gradient(90deg,#0ea5e9,#38bdf8);
    border-radius: 99px;
    transition: width .3s ease;
}

/* Modal */
.gdbc-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15,23,42,.55);
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    backdrop-filter: blur(2px);
}
.gdbc-modal {
    background: #fff;
    border-radius: 14px;
    width: 100%;
    max-width: 820px;
    max-height: 88vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 25px 60px rgba(0,0,0,.18);
    animation: gdbcModalIn .18s ease;
}
.gdbc-modal-wide { max-width: 1080px; }
@keyframes gdbcModalIn {
    from { opacity:0; transform:translateY(12px) scale(.97); }
    to   { opacity:1; transform:none; }
}
.gdbc-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 24px;
    border-bottom: 1px solid #e2e8f0;
}
.gdbc-modal-header h3 { margin:0; font-size:16px; color:#0f172a; }
.gdbc-modal-close {
    background: none;
    border: none;
    cursor: pointer;
    color: #94a3b8;
    padding: 4px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    transition: color .15s, background .15s;
}
.gdbc-modal-close:hover { color:#0f172a; background:#f1f5f9; }
.gdbc-modal-body {
    flex: 1;
    overflow: auto;
    padding: 20px 24px;
}
.gdbc-modal-footer {
    padding: 14px 24px;
    border-top: 1px solid #e2e8f0;
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

/* Data Preview Table */
.gdbc-preview-table-wrap { overflow-x: auto; }
.gdbc-preview-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.gdbc-preview-table th {
    background: #f8fafc;
    color: #475569;
    font-weight: 600;
    padding: 8px 12px;
    text-align: left;
    border-bottom: 2px solid #e2e8f0;
    white-space: nowrap;
    position: sticky;
    top: 0;
}
.gdbc-preview-table td {
    padding: 7px 12px;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.gdbc-preview-table tr:hover td { background: #f8fafc; }

/* MySQL button */
.gdbc-btn-mysql {
    background: linear-gradient(135deg,#8b5cf6,#7c3aed);
    color: #fff;
    border: none;
    border-radius: 6px;
    padding: 4px 10px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: opacity .15s;
}
.gdbc-btn-mysql:hover { opacity: .88; }

/* Pagination */
.gdbc-pagination {
    display: flex;
    gap: 6px;
    margin-top: 16px;
    flex-wrap: wrap;
}
.gdbc-page-link {
    padding: 5px 12px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    color: #334155;
    text-decoration: none;
    font-size: 13px;
    transition: all .15s;
}
.gdbc-page-link:hover, .gdbc-page-link.active {
    background: #0ea5e9;
    border-color: #0ea5e9;
    color: #fff;
}

/* Column map grid */
.gdbc-colmap-grid {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    gap: 8px;
    align-items: center;
}
.gdbc-colmap-arrow { color: #94a3b8; font-size: 18px; text-align: center; }

/* Responsive */
@media (max-width: 600px) {
    .gdbc-modal { max-height: 96vh; }
    .gdbc-dropzone { padding: 28px 16px; }
}
</style>';
}


// ─────────────────────────────────────────────────────────────
// 8. GLOBAL JAVASCRIPT
// ─────────────────────────────────────────────────────────────

function gdbc_global_scripts() {
    return '<script>
(function(){
"use strict";

/* ── Modal helpers ── */
function openModal(id) {
    document.getElementById(id).style.display = "flex";
    document.body.style.overflow = "hidden";
}
function closeModal(id) {
    document.getElementById(id).style.display = "none";
    document.body.style.overflow = "";
}

document.getElementById("gdbc-modal-close").addEventListener("click", function(){
    closeModal("gdbc-modal-overlay");
});
document.getElementById("gdbc-modal-overlay").addEventListener("click", function(e){
    if (e.target === this) closeModal("gdbc-modal-overlay");
});
document.querySelectorAll(".gdbc-modal-close[data-modal]").forEach(function(btn){
    btn.addEventListener("click", function(){ closeModal(this.dataset.modal); });
});
document.getElementById("gdbc-colmap-overlay").addEventListener("click", function(e){
    if (e.target === this) closeModal("gdbc-colmap-overlay");
});

/* ── Drag-drop upload ── */
var dropzone = document.getElementById("gdbc-dropzone");
var fileInput = document.getElementById("gdbc-file-input");

if (dropzone) {
    ["dragenter","dragover"].forEach(function(evt){
        dropzone.addEventListener(evt, function(e){ e.preventDefault(); dropzone.classList.add("drag-over"); });
    });
    ["dragleave","drop"].forEach(function(evt){
        dropzone.addEventListener(evt, function(e){ e.preventDefault(); dropzone.classList.remove("drag-over"); });
    });
    dropzone.addEventListener("drop", function(e){
        var files = e.dataTransfer.files;
        if (files.length) gdbcUploadFile(files[0]);
    });
    dropzone.addEventListener("click", function(e){
        if (e.target.tagName !== "LABEL" && e.target.tagName !== "INPUT")
            fileInput && fileInput.click();
    });
}
if (fileInput) {
    fileInput.addEventListener("change", function(){
        if (this.files.length) gdbcUploadFile(this.files[0]);
    });
}

function gdbcUploadFile(file) {
    var nonce = dropzone ? dropzone.dataset.nonce : "";
    var ext = file.name.split(".").pop().toLowerCase();
    if (ext !== "gbp" && ext !== "bin") {
        showMsg("gdbc-upload-message", "error", "Only .gdb or .bin files are allowed.");
        return;
    }

    var progress = document.getElementById("gdbc-upload-progress");
    var bar = document.getElementById("gdbc-progress-bar");
    var label = document.getElementById("gdbc-progress-label");
    progress && (progress.style.display = "block");
    bar && (bar.style.width = "10%");
    label && (label.textContent = "Uploading " + file.name + "...");
    showMsg("gdbc-upload-message", "", "");

    var fd = new FormData();
    fd.append("action", "gdbc_upload_file");
    fd.append("nonce", nonce);
    fd.append("gdbc_file", file);

    var xhr = new XMLHttpRequest();
    xhr.open("POST", ajaxurl, true);

    xhr.upload.addEventListener("progress", function(e){
        if (e.lengthComputable) {
            var pct = Math.round((e.loaded / e.total) * 70);
            bar && (bar.style.width = pct + "%");
            label && (label.textContent = "Uploading... " + pct + "%");
        }
    });

    xhr.onload = function() {
        bar && (bar.style.width = "90%");
        label && (label.textContent = "Parsing file...");
        try {
            var json = JSON.parse(xhr.responseText);
            bar && (bar.style.width = "100%");
            setTimeout(function(){
                progress && (progress.style.display = "none");
                bar && (bar.style.width = "0%");
            }, 600);

            if (json.success) {
                showMsg("gdbc-upload-message", "success", json.data.message);
                gdbcOpenPreviewModal(json.data);
                setTimeout(function(){ location.reload(); }, 4500);
            } else {
                showMsg("gdbc-upload-message", "error", json.data.message || "Upload failed.");
            }
        } catch(err) {
            showMsg("gdbc-upload-message", "error", "Server error. Please try again.");
        }
    };

    xhr.onerror = function() {
        progress && (progress.style.display = "none");
        showMsg("gdbc-upload-message", "error", "Network error during upload.");
    };

    xhr.send(fd);
}

/* ── Open preview modal immediately after upload ── */
function gdbcOpenPreviewModal(data) {
    document.getElementById("gdbc-modal-title").textContent = "Preview: " + (data.filename || "");

    var headers = data.headers || [];
    var rows    = data.preview || [];

    var html = "<p style=\'color:#6b7280;font-size:13px;margin-top:0;\'>" +
               "Showing first " + rows.length + " of " + data.row_count + " rows.</p>";
    html += buildPreviewTable(headers, rows);

    document.getElementById("gdbc-modal-body").innerHTML = html;

    var footer = document.getElementById("gdbc-modal-footer");
    footer.innerHTML =
        "<button class=\'bntm-btn-primary\' id=\'gdbc-modal-export-csv\' data-job-id=\'" + data.job_id + "\' data-nonce=\'" + data.nonce_export + "\' data-filename=\'" + (data.filename_base || "export") + "\'>Download CSV</button>" +
        "<button class=\'gdbc-btn-mysql\' id=\'gdbc-modal-mysql\' data-job-id=\'" + data.job_id + "\' data-nonce=\'" + data.nonce_insert + "\'>Insert into MySQL</button>" +
        "<button class=\'bntm-btn-secondary\' id=\'gdbc-modal-full-preview\' data-job-id=\'" + data.job_id + "\' data-nonce=\'" + data.nonce_preview + "\'>Full Preview</button>" +
        "<span id=\'gdbc-modal-action-msg\'></span>";

    wireFooterButtons();
    openModal("gdbc-modal-overlay");
}

/* ── Preview button (history) ── */
document.querySelectorAll(".gdbc-preview-btn").forEach(function(btn){
    btn.addEventListener("click", function(){
        var jobId = this.dataset.jobId;
        var nonce = this.dataset.nonce;
        gdbcLoadPreview(jobId, nonce);
    });
});

function gdbcLoadPreview(jobId, nonce, offset) {
    offset = offset || 0;
    var fd = new FormData();
    fd.append("action", "gdbc_get_preview");
    fd.append("job_id", jobId);
    fd.append("nonce", nonce);
    fd.append("limit", 50);
    fd.append("offset", offset);

    document.getElementById("gdbc-modal-body").innerHTML =
        "<p style=\'text-align:center;padding:32px;color:#94a3b8;\'>Loading preview...</p>";
    openModal("gdbc-modal-overlay");

    fetch(ajaxurl, {method:"POST",body:fd})
    .then(function(r){ return r.json(); })
    .then(function(json){
        if (!json.success) {
            document.getElementById("gdbc-modal-body").innerHTML =
                "<p style=\'color:#ef4444;\'>"+json.data.message+"</p>";
            return;
        }
        var d = json.data;
        document.getElementById("gdbc-modal-title").textContent = "Preview: " + d.filename;

        var html = "<p style=\'color:#6b7280;font-size:13px;margin-top:0;\'>"+
            "Showing rows " + (offset+1) + "–" + Math.min(offset+50, d.total) +
            " of " + d.total + " total.</p>";
        html += buildPreviewTable(d.headers, d.rows);

        // Pagination
        if (d.total > 50) {
            html += "<div style=\'margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;\'>";
            var pages = Math.ceil(d.total / 50);
            var curPage = Math.floor(offset/50);
            for (var p = 0; p < pages; p++) {
                var isActive = p === curPage ? "style=\'background:#0ea5e9;color:#fff;\'" : "";
                html += "<button class=\'gdbc-page-link\' "+isActive+" onclick=\'gdbcLoadPreview("+d.job_id+",\""+nonce+"\","+(p*50)+")\'>"+( p+1)+"</button>";
            }
            html += "</div>";
        }

        document.getElementById("gdbc-modal-body").innerHTML = html;

        var footer = document.getElementById("gdbc-modal-footer");
        footer.innerHTML =
            "<button class=\'bntm-btn-primary gdbc-export-csv-btn\' data-job-id=\'"+d.job_id+"\' data-nonce=\'"+wp_create_nonce_js("gdbc_export_"+d.job_id)+"\' data-filename=\'"+d.filename.replace(/\.[^.]+$/,"")+"\'>Download CSV</button>" +
            "<button class=\'gdbc-btn-mysql gdbc-insert-mysql-btn\' data-job-id=\'"+d.job_id+"\' data-nonce=\'"+wp_create_nonce_js("gdbc_insert_"+d.job_id)+"\'>Insert into MySQL</button>" +
            "<span id=\'gdbc-modal-action-msg\'></span>";

        wireFooterButtons();
    });
}

// Placeholder — nonces for preview-opened modals are embedded per-button in PHP
function wp_create_nonce_js(action) { return ""; } // Nonces are data attrs from PHP

/* ── Build preview table HTML ── */
function buildPreviewTable(headers, rows) {
    if (!headers || !headers.length) return "<p style=\'color:#94a3b8;\'>No data.</p>";
    var html = "<div class=\'gdbc-preview-table-wrap\'><table class=\'gdbc-preview-table\'><thead><tr>";
    headers.forEach(function(h){ html += "<th>"+escHtml(h)+"</th>"; });
    html += "</tr></thead><tbody>";
    if (!rows || !rows.length) {
        html += "<tr><td colspan=\'"+headers.length+"\' style=\'text-align:center;color:#94a3b8;padding:20px;\'>No rows.</td></tr>";
    } else {
        rows.forEach(function(row){
            html += "<tr>";
            headers.forEach(function(h){
                html += "<td title=\'"+escHtml(String(row[h]||""))+"\'>" + escHtml(String(row[h]||"")) + "</td>";
            });
            html += "</tr>";
        });
    }
    html += "</tbody></table></div>";
    return html;
}

/* ── Wire footer action buttons ── */
function wireFooterButtons() {
    // CSV Export
    document.querySelectorAll(".gdbc-export-csv-btn").forEach(function(btn){
        btn.addEventListener("click", function(){
            var jobId    = this.dataset.jobId;
            var nonce    = this.dataset.nonce;
            var filename = this.dataset.filename || "export";
            var el       = this;
            el.disabled  = true; el.textContent = "Generating...";

            var fd = new FormData();
            fd.append("action","gdbc_export_csv");
            fd.append("job_id", jobId);
            fd.append("nonce", nonce);

            fetch(ajaxurl, {method:"POST",body:fd})
            .then(function(r){ return r.json(); })
            .then(function(json){
                el.disabled = false; el.textContent = "Download CSV";
                if (json.success) {
                    var bytes = atob(json.data.csv);
                    var arr   = new Uint8Array(bytes.length);
                    for (var i=0;i<bytes.length;i++) arr[i]=bytes.charCodeAt(i);
                    var blob  = new Blob([arr],{type:"text/csv"});
                    var url   = URL.createObjectURL(blob);
                    var a     = document.createElement("a");
                    a.href    = url;
                    a.download = json.data.filename || filename + ".csv";
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                    gdbcShowModalMsg("success", "CSV downloaded!");
                } else {
                    gdbcShowModalMsg("error", json.data.message || "Export failed.");
                }
            });
        });
    });

    // MySQL Insert
    document.querySelectorAll(".gdbc-insert-mysql-btn").forEach(function(btn){
        btn.addEventListener("click", function(){
            var jobId = this.dataset.jobId;
            var nonce = this.dataset.nonce;
            gdbcOpenMysqlModal(jobId, nonce);
        });
    });
}

/* ── MySQL Insert Modal ── */
function gdbcOpenMysqlModal(jobId, nonce) {
    var body = document.getElementById("gdbc-colmap-body");
    body.innerHTML = "<p style=\'text-align:center;padding:24px;color:#94a3b8;\'>Loading database tables...</p>";
    openModal("gdbc-colmap-overlay");

    // Fetch tables
    var fd = new FormData();
    fd.append("action","gdbc_get_db_tables");
    fd.append("nonce", wp_create_nonce_static("gdbc_insert_nonce"));

    // Also get the job headers
    var fdp = new FormData();
    fdp.append("action","gdbc_get_preview");
    fdp.append("job_id", jobId);
    fdp.append("nonce", nonce);
    fdp.append("limit",1);
    fdp.append("offset",0);

    Promise.all([
        fetch(ajaxurl,{method:"POST",body:fd}).then(function(r){return r.json();}),
        fetch(ajaxurl,{method:"POST",body:fdp}).then(function(r){return r.json();})
    ]).then(function(results){
        var tablesJson  = results[0];
        var previewJson = results[1];

        if (!tablesJson.success) {
            body.innerHTML = "<p style=\'color:#ef4444;\'>Failed to load tables.</p>";
            return;
        }

        var tables  = tablesJson.data.tables;
        var headers = previewJson.success ? previewJson.data.headers : [];
        var tableNames = Object.keys(tables);

        var html = "<div style=\'margin-bottom:20px;\'>" +
            "<label style=\'font-weight:600;display:block;margin-bottom:6px;\'>Target Table</label>" +
            "<select id=\'gdbc-target-table\' class=\'bntm-select\'>" +
            "<option value=\'\'>-- Select Table --</option>";
        tableNames.forEach(function(t){
            html += "<option value=\'"+escHtml(t)+"\'>"+escHtml(t)+"</option>";
        });
        html += "</select></div>";

        html += "<div id=\'gdbc-colmap-fields\' style=\'display:none;\'>" +
            "<h4 style=\'margin:0 0 12px;\'>Column Mapping</h4>" +
            "<p style=\'color:#6b7280;font-size:13px;margin-bottom:16px;\'>Map each GDB column to a database column. Leave blank to skip.</p>" +
            "<div class=\'gdbc-colmap-grid\' style=\'gap:10px 16px;\'>" +
            "<strong style=\'color:#475569;font-size:13px;\'>GDB Column</strong><span></span><strong style=\'color:#475569;font-size:13px;\'>DB Column</strong>";

        headers.forEach(function(h){
            html += "<div style=\'padding:6px 10px;background:#f8fafc;border-radius:6px;font-size:13px;font-weight:500;\'>"+escHtml(h)+"</div>" +
                "<div class=\'gdbc-colmap-arrow\'>&rarr;</div>" +
                "<select class=\'bntm-select gdbc-col-select\' data-gbp-col=\'"+escHtml(h)+"\'>" +
                "<option value=\'\'>-- skip --</option>" +
                "</select>";
        });
        html += "</div></div>";

        body.innerHTML = html;

        // On table select, populate column dropdowns
        document.getElementById("gdbc-target-table").addEventListener("change", function(){
            var tbl = this.value;
            var fields = document.getElementById("gdbc-colmap-fields");
            if (!tbl) { fields.style.display = "none"; return; }

            var cols = tables[tbl] || [];
            document.querySelectorAll(".gdbc-col-select").forEach(function(sel){
                var gdbCol = sel.dataset.gdbCol;
                sel.innerHTML = "<option value=\'\'>-- skip --</option>";
                cols.forEach(function(c){
                    var selected = c.toLowerCase() === gdbCol.toLowerCase() ? " selected" : "";
                    sel.innerHTML += "<option value=\'"+escHtml(c)+"\'"+selected+">"+escHtml(c)+"</option>";
                });
            });
            fields.style.display = "block";
        });

        // Save mapping & insert
        var saveBtn = document.getElementById("gdbc-save-colmap-btn");
        saveBtn.onclick = function(){
            var target = document.getElementById("gdbc-target-table").value;
            if (!target) { alert("Please select a target table."); return; }

            var colMap = {};
            document.querySelectorAll(".gdbc-col-select").forEach(function(sel){
                if (sel.value) colMap[sel.dataset.gdbCol] = sel.value;
            });

            if (!Object.keys(colMap).length) {
                alert("Please map at least one column.");
                return;
            }

            saveBtn.disabled = true; saveBtn.textContent = "Inserting...";

            var fd2 = new FormData();
            fd2.append("action","gdbc_insert_mysql");
            fd2.append("job_id", jobId);
            fd2.append("nonce", nonce);
            fd2.append("target_table", target);
            fd2.append("column_map", JSON.stringify(colMap));

            fetch(ajaxurl,{method:"POST",body:fd2})
            .then(function(r){ return r.json(); })
            .then(function(json){
                saveBtn.disabled = false; saveBtn.textContent = "Save Mapping & Insert";
                if (json.success) {
                    closeModal("gdbc-colmap-overlay");
                    gdbcShowModalMsg("success", json.data.message);
                    setTimeout(function(){ location.reload(); }, 2500);
                } else {
                    alert(json.data.message || "Insert failed.");
                }
            });
        };
    });
}

// Static nonce placeholder (for get_db_tables which is general)
function wp_create_nonce_static(action) {
    // Actual nonce is validated server-side; this is for non-job-specific endpoints
    // We embed it in a meta tag from PHP below
    var el = document.getElementById("gdbc-insert-nonce-val");
    return el ? el.value : "";
}

/* ── Delete (single/bulk) ── */
document.querySelectorAll(".gdbc-delete-btn").forEach(function(btn){
    btn.addEventListener("click", function(){
        if (!confirm("Delete this job and its data?")) return;
        gdbcBulkDelete([this.dataset.jobId], this.dataset.nonce);
    });
});

function gdbcBulkDelete(ids, nonce) {
    var fd = new FormData();
    fd.append("action","gdbc_delete_job");
    fd.append("nonce", nonce);
    fd.append("job_ids", JSON.stringify(ids));

    fetch(ajaxurl,{method:"POST",body:fd})
    .then(function(r){ return r.json(); })
    .then(function(json){
        if (json.success) {
            ids.forEach(function(id){
                var row = document.querySelector("tr[data-job-id=\'"+id+"\']");
                if (row) row.remove();
            });
            alert(json.data.message);
        } else {
            alert(json.data.message || "Delete failed.");
        }
    });
}

/* ── Utility ── */
function showMsg(id, type, msg) {
    var el = document.getElementById(id);
    if (!el) return;
    if (!msg) { el.innerHTML = ""; return; }
    var cls = type === "success" ? "bntm-notice-success" : type === "error" ? "bntm-notice-error" : "";
    el.innerHTML = "<div class=\'bntm-notice " + cls + "\' style=\'margin-top:10px;\'>" + escHtml(msg) + "</div>";
}

function gdbcShowModalMsg(type, msg) {
    var el = document.getElementById("gdbc-modal-action-msg");
    if (!el) return;
    var color = type === "success" ? "#059669" : "#dc2626";
    el.innerHTML = "<span style=\'color:"+color+";font-size:13px;margin-left:8px;\'>"+escHtml(msg)+"</span>";
}

function escHtml(str) {
    return String(str)
        .replace(/&/g,"&amp;")
        .replace(/</g,"&lt;")
        .replace(/>/g,"&gt;")
        .replace(/"/g,"&quot;");
}

// Expose for inline onclick (pagination in preview)
window.gdbcLoadPreview = gdbcLoadPreview;
window.gdbcBulkDelete  = gdbcBulkDelete;

// Wire initial history buttons
wireFooterButtons();

})();
</script>
<!-- Insert nonce for get_db_tables endpoint -->
<input type="hidden" id="gdbc-insert-nonce-val" value="<?php echo wp_create_nonce("gdbc_insert_nonce"); ?>">
';
}