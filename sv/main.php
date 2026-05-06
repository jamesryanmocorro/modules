<?php
/**
* Module Name: Startup Validator
* Module Slug: sv
* Description: A comprehensive landing page module to validate startup ideas. Features a minimalist public landing page with waitlist capture, and a guided dashboard editor for content management and lead tracking.
* Version: 1.0.0
* Author: BNTM
* Icon: <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M15.09 14c.18.26.3.56.3.89V18H8.61v-3.11c0-.33.12-.63.3-.89a6 6 0 1 1 6.18 0Z"/><path d="M12 2a7 7 0 0 0-7 7c0 2.38 1.19 4.47 3 5.74V17a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2v-2.26c1.81-1.27 3-3.36 3-5.74a7 7 0 0 0-7-7Z"/></svg>
*/
if (!defined('ABSPATH')) exit;

define('BNTM_SV_PATH', dirname(__FILE__) . '/');
define('BNTM_SV_URL', plugin_dir_url(__FILE__));

// ==========================================
// MODULE CONFIGURATION
// ==========================================

function bntm_sv_get_pages() {
return [
'Startup Validator Dashboard' => '[sv_dashboard]',
'Validation Landing Page' => '[sv_landing]'
];
}

function bntm_sv_get_tables() {
global $wpdb;
$charset = $wpdb->get_charset_collate();
$prefix = $wpdb->prefix;
return [
'sv_landing_content' => "CREATE TABLE {$prefix}sv_landing_content (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
rand_id VARCHAR(20) UNIQUE NOT NULL,
business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
block_type VARCHAR(50) NOT NULL DEFAULT 'text',
block_data LONGTEXT NOT NULL,
sort_order INT DEFAULT 0,
status ENUM('published','draft') DEFAULT 'draft',
created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
INDEX idx_business (business_id)
) {$charset};",
'sv_waitlist_leads' => "CREATE TABLE {$prefix}sv_waitlist_leads (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
rand_id VARCHAR(20) UNIQUE NOT NULL,
business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
email VARCHAR(191) NOT NULL,
full_name VARCHAR(100),
source_tag VARCHAR(50) DEFAULT 'direct',
status ENUM('new','contacted','converted') DEFAULT 'new',
created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
INDEX idx_business (business_id),
UNIQUE KEY idx_business_email (business_id, email)
) {$charset};"
];
}

function bntm_sv_get_shortcodes() {
return [
'sv_dashboard' => 'bntm_shortcode_sv',
'sv_landing' => 'bntm_shortcode_sv_landing'
];
}

function bntm_sv_create_tables() {
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
$tables = bntm_sv_get_tables();
foreach ($tables as $sql) {
dbDelta($sql);
}
return count($tables);
}

// ==========================================
// AJAX ACTION HOOKS
// ==========================================

add_action('wp_ajax_sv_save_content', 'bntm_ajax_sv_save_content');
add_action('wp_ajax_sv_get_leads', 'bntm_ajax_sv_get_leads');
add_action('wp_ajax_sv_update_lead_status', 'bntm_ajax_sv_update_lead_status');
add_action('wp_ajax_sv_save_settings', 'bntm_ajax_sv_save_settings');
add_action('wp_ajax_sv_submit_lead', 'bntm_ajax_sv_submit_lead');
add_action('wp_ajax_nopriv_sv_submit_lead', 'bntm_ajax_sv_submit_lead');

// ==========================================
// MAIN DASHBOARD SHORTCODE
// ==========================================

function bntm_shortcode_sv() {
if (!is_user_logged_in()) {
return '<div class="bntm-notice bntm-notice-error">Please log in to access the Startup Validator dashboard.</div>';
}
$current_user = wp_get_current_user();
$business_id = $current_user->ID;
$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'content_editor';

ob_start();
?>
<script>
var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
</script>
<div class="bntm-sv-container">
    <div class="bntm-tabs">
        <a href="?tab=content_editor" class="bntm-tab <?php echo $active_tab === 'content_editor' ? 'active' : ''; ?>">Content Editor</a>
        <a href="?tab=leads_analytics" class="bntm-tab <?php echo $active_tab === 'leads_analytics' ? 'active' : ''; ?>">Leads & Analytics</a>
        <a href="?tab=settings" class="bntm-tab <?php echo $active_tab === 'settings' ? 'active' : ''; ?>">Settings</a>
    </div>
    <div class="bntm-tab-content">
        <?php if ($active_tab === 'content_editor'): ?>
            <?php echo sv_content_editor_tab($business_id); ?>
        <?php elseif ($active_tab === 'leads_analytics'): ?>
            <?php echo sv_leads_analytics_tab($business_id); ?>
        <?php elseif ($active_tab === 'settings'): ?>
            <?php echo sv_settings_tab($business_id); ?>
        <?php endif; ?>
    </div>
</div>
<style>
    /* Shared Dashboard Styles */
    .bntm-sv-container { padding: 20px 0; }
    .bntm-sv-preview-iframe { width: 100%; height: 500px; border: 1px solid #e5e7eb; border-radius: 8px; margin-top: 20px; background: #fff; }
    .sv-editor-step { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin-bottom: 15px; transition: box-shadow 0.2s; }
    .sv-editor-step:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
    .sv-step-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; cursor: pointer; }
    .sv-step-header h4 { margin: 0; font-size: 1.1rem; }
    .sv-step-toggle { background: none; border: none; font-size: 1.2rem; cursor: pointer; color: var(--bntm-primary); }
    .sv-step-body { display: none; }
    .sv-step-body.open { display: block; }
    .sv-preview-toggle { display: inline-flex; align-items: center; gap: 8px; margin-bottom: 15px; padding: 8px 12px; background: #f3f4f6; border-radius: 6px; cursor: pointer; font-size: 0.9rem; }
    .sv-preview-toggle input { margin: 0; }
    .sv-status-badge { display: inline-block; padding: 4px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
    .sv-status-new { background: #e0f2fe; color: #0284c7; }
    .sv-status-contacted { background: #fef3c7; color: #d97706; }
    .sv-status-converted { background: #dcfce7; color: #16a34a; }
    .sv-lead-actions select { padding: 4px 8px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.85rem; }
</style>
<script>
/* Shared JS Utilities */
(function() {
    const tabs = document.querySelectorAll('.bntm-tab');
    tabs.forEach(tab => tab.addEventListener('click', function(e) { e.preventDefault(); window.location.href = this.href; }));
})();
</script>
<?php
$content = ob_get_clean();
return bntm_universal_container('Startup Validator', $content);
}
// ==========================================
// TAB RENDERING FUNCTIONS
// ==========================================

function sv_content_editor_tab($business_id) {
global $wpdb;
$table = $wpdb->prefix . 'sv_landing_content';
$blocks = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE business_id = %d ORDER BY sort_order ASC", $business_id));
$nonce = wp_create_nonce('sv_nonce');
$settings = get_option('bntm_sv_settings_' . $business_id, []);
$is_published = isset($settings['status']) && $settings['status'] === 'published';

if (empty($blocks)) {
    $blocks = sv_generate_default_blocks($business_id);
}

ob_start();
?>
<div class="bntm-form-section">
    <h3>Guided Landing Page Editor</h3>
    <p style="color:#6b7280;">Edit each section below to customize your validation page. Changes are saved to drafts. Publish via Settings.</p>
    
    <div class="sv-preview-toggle">
        <input type="checkbox" id="sv-preview-mode">
        <label for="sv-preview-mode">Enable Live Preview</label>
    </div>

    <div id="sv-editor-accordion">
        <?php foreach ($blocks as $index => $block): 
            $data = json_decode($block->block_data, true);
            $is_open = ($index === 0) ? 'open' : '';
        ?>
        <div class="sv-editor-step" data-block-id="<?php echo $block->rand_id; ?>" data-type="<?php echo esc_attr($block->block_type); ?>">
            <div class="sv-step-header">
                <h4><?php echo esc_html(ucwords(str_replace('_', ' ', $block->block_type))); ?> Block</h4>
                <button class="sv-step-toggle">▼</button>
            </div>
            <div class="sv-step-body <?php echo $is_open; ?>">
                <input type="hidden" class="sv-block-type" value="<?php echo esc_attr($block->block_type); ?>">
                <?php if ($block->block_type === 'hero'): ?>
                    <div class="bntm-form-group">
                        <label>Main Headline</label>
                        <input type="text" class="sv-field-title" value="<?php echo esc_attr($data['title'] ?? ''); ?>">
                    </div>
                    <div class="bntm-form-group">
                        <label>Subheadline</label>
                        <textarea class="sv-field-subtitle" rows="2"><?php echo esc_textarea($data['subtitle'] ?? ''); ?></textarea>
                    </div>
                <?php elseif ($block->block_type === 'value_prop'): ?>
                    <div class="bntm-form-group">
                        <label>Core Value Statement</label>
                        <textarea class="sv-field-content" rows="3"><?php echo esc_textarea($data['content'] ?? ''); ?></textarea>
                    </div>
                <?php elseif ($block->block_type === 'features'): ?>
                    <div class="bntm-form-group">
                        <label>Feature 1</label>
                        <input type="text" class="sv-feature-item" value="<?php echo esc_attr($data['items'][0] ?? ''); ?>" placeholder="Feature name">
                    </div>
                    <div class="bntm-form-group">
                        <label>Feature 2</label>
                        <input type="text" class="sv-feature-item" value="<?php echo esc_attr($data['items'][1] ?? ''); ?>" placeholder="Feature name">
                    </div>
                    <div class="bntm-form-group">
                        <label>Feature 3</label>
                        <input type="text" class="sv-feature-item" value="<?php echo esc_attr($data['items'][2] ?? ''); ?>" placeholder="Feature name">
                    </div>
                <?php elseif ($block->block_type === 'faq'): ?>
                    <div class="bntm-form-group">
                        <label>Q&A JSON (Array of {q, a})</label>
                        <textarea class="sv-field-faq" rows="4"><?php echo esc_textarea(json_encode($data['items'] ?? [['q'=>'Is this free?','a'=>'Yes, early access is completely free.']], JSON_PRETTY_PRINT)); ?></textarea>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <button id="sv-save-content-btn" class="bntm-btn-primary" data-nonce="<?php echo $nonce; ?>">Save All Changes</button>
    <div id="sv-editor-message"></div>
</div>

<div class="bntm-form-section" id="sv-preview-panel" style="display:none; margin-top:20px;">
    <h3>Live Preview</h3>
    <p style="color:#6b7280; font-size:0.9rem;">This reflects your saved draft content.</p>
    <iframe id="sv-preview-iframe" class="sv-preview-iframe" srcdoc="Loading..."></iframe>
</div>

<style>
    /* Tab Specific */
    .bntm-sv-container .bntm-form-group { margin-bottom: 15px; }
    .bntm-sv-container .bntm-form-group label { display: block; font-weight: 500; margin-bottom: 5px; font-size: 0.9rem; }
    .bntm-sv-container input[type="text"], .bntm-sv-container textarea { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; }
    .bntm-sv-container textarea { resize: vertical; }
</style>
<script>
(function() {
    const toggleBtns = document.querySelectorAll('.sv-step-toggle');
    toggleBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            this.parentElement.nextElementSibling.classList.toggle('open');
            this.textContent = this.parentElement.nextElementSibling.classList.contains('open') ? '▲' : '▼';
        });
    });

    const previewToggle = document.getElementById('sv-preview-mode');
    const previewPanel = document.getElementById('sv-preview-panel');
    const previewIframe = document.getElementById('sv-preview-iframe');
    if (previewToggle) {
        previewToggle.addEventListener('change', function() {
            previewPanel.style.display = this.checked ? 'block' : 'none';
        });
    }

    const saveBtn = document.getElementById('sv-save-content-btn');
    if (saveBtn) {
        saveBtn.addEventListener('click', function() {
            const blocks = Array.from(document.querySelectorAll('.sv-editor-step')).map(step => {
                const type = step.dataset.type;
                const id = step.dataset.blockId;
                let data = {};
                if (type === 'hero') {
                    data = { title: step.querySelector('.sv-field-title').value, subtitle: step.querySelector('.sv-field-subtitle').value };
                } else if (type === 'value_prop') {
                    data = { content: step.querySelector('.sv-field-content').value };
                } else if (type === 'features') {
                    data = { items: Array.from(step.querySelectorAll('.sv-feature-item')).map(i => i.value) };
                } else if (type === 'faq') {
                    try { data = { items: JSON.parse(step.querySelector('.sv-field-faq').value) }; } catch(e) { alert('Invalid FAQ JSON format'); return null; }
                }
                return { rand_id: id, block_type: type, block_data: JSON.stringify(data) };
            }).filter(Boolean);

            this.disabled = true; this.textContent = 'Saving...';
            const formData = new FormData();
            formData.append('action', 'sv_save_content');
            formData.append('blocks', JSON.stringify(blocks));
            formData.append('nonce', this.dataset.nonce);

            fetch(ajaxurl, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(json => {
                const msg = document.getElementById('sv-editor-message');
                msg.innerHTML = '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + json.data.message + '</div>';
                saveBtn.disabled = false; saveBtn.textContent = 'Save All Changes';
                if (json.success && previewPanel.style.display !== 'none') updatePreview(blocks);
            });
        });
    }

    function updatePreview(blocks) {
        let html = '<style>body{font-family:system-ui,sans-serif;margin:0;padding:40px;background:#f8fafc;color:#1e293b}.sv-wrap{max-width:800px;margin:0 auto;background:#fff;padding:40px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.05)}.sv-btn{background:var(--bntm-primary);color:#fff;padding:12px 24px;border-radius:8px;border:none;cursor:pointer;font-weight:600}.sv-input{padding:12px;border:1px solid #cbd5e1;border-radius:8px;width:100%;margin-bottom:10px}.sv-faq{margin-top:10px;border-bottom:1px solid #e2e8f0;padding:15px 0}.sv-q{font-weight:600;margin-bottom:5px}.sv-a{color:#475569}.sv-feature{display:flex;align-items:center;gap:10px;margin-bottom:8px}.sv-icon{color:#10b981}</style><div class="sv-wrap">';
        blocks.forEach(b => {
            const d = JSON.parse(b.block_data);
            if (b.block_type === 'hero') html += `<h1 style="margin:0 0 10px;font-size:2.2rem">${d.title || 'Headline'}</h1><p style="color:#64748b;font-size:1.1rem;margin:0 0 30px">${d.subtitle || 'Subheadline'}</p>`;
            if (b.block_type === 'value_prop') html += `<p style="font-size:1.2rem;line-height:1.6;margin:0 0 30px">${d.content || 'Value prop'}</p>`;
            if (b.block_type === 'features') html += `<div style="margin-bottom:30px">${d.items.map(i => `<div class="sv-feature"><span class="sv-icon">✓</span><span>${i}</span></div>`).join('')}</div>`;
            if (b.block_type === 'faq' && d.items) d.items.forEach(f => { html += `<div class="sv-faq"><div class="sv-q">${f.q}</div><div class="sv-a">${f.a}</div></div>`; });
        });
        html += `<form style="margin-top:30px;padding:20px;background:#f1f5f9;border-radius:8px"><h3>Join Waitlist</h3><input class="sv-input" placeholder="Your Email" disabled><button class="sv-btn" disabled type="button">Notify Me</button></form></div>`;
        previewIframe.srcdoc = html;
    }
})();
</script>
<?php
return ob_get_clean();
}

function sv_leads_analytics_tab($business_id) {
global $wpdb;
$table = $wpdb->prefix . 'sv_waitlist_leads';
$total_leads = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT() FROM {$table} WHERE business_id = %d", $business_id));
$converted = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT() FROM {$table} WHERE business_id = %d AND status = 'converted'", $business_id));
$nonce = wp_create_nonce('sv_nonce');
ob_start();
?>
<div class="bntm-stats-row">
    <div class="bntm-stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);">
            <svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M23 21v-2a4 4 0 00-3-3.87"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 3.13a4 4 0 010 7.75"/></svg>
        </div>
        <div class="stat-content">
            <h3>Total Leads</h3>
            <p class="stat-number"><?php echo number_format($total_leads); ?></p>
            <span class="stat-label">Submissions captured</span>
        </div>
    </div>
    <div class="bntm-stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #10b981 0%, #047857 100%);">
            <svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <div class="stat-content">
            <h3>Converted</h3>
            <p class="stat-number"><?php echo number_format($converted); ?></p>
            <span class="stat-label">High intent</span>
        </div>
    </div>
</div>

<div class="bntm-form-section" style="margin-top:25px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
        <h3 style="margin:0;">Leads Database</h3>
        <div style="display:flex;gap:10px;">
            <select id="sv-lead-filter" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;">
                <option value="all">All Statuses</option>
                <option value="new">New</option>
                <option value="contacted">Contacted</option>
                <option value="converted">Converted</option>
            </select>
            <button id="sv-export-csv" class="bntm-btn-secondary" style="white-space:nowrap;">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align:-2px;margin-right:5px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Export CSV
            </button>
        </div>
    </div>
    <div class="bntm-table-wrapper">
        <table class="bntm-table" id="sv-leads-table">
            <thead>
                <tr><th>Name</th><th>Email</th><th>Date</th><th>Status</th><th>Action</th></tr>
            </thead>
            <tbody id="sv-leads-body">
                <tr><td colspan="5" style="text-align:center;color:#6b7280;">Loading leads...</td></tr>
            </tbody>
        </table>
    </div>
    <div id="sv-leads-message"></div>
</div>

<script>
(function() {
    const leadsBody = document.getElementById('sv-leads-body');
    const filterSelect = document.getElementById('sv-lead-filter');
    const nonce = '<?php echo $nonce; ?>';
    let currentLeads = [];

    function renderLeads(leads) {
        if (leads.length === 0) {
            leadsBody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#6b7280;">No leads found.</td></tr>';
            return;
        }
        leadsBody.innerHTML = leads.map(lead => `
            <tr>
                <td>${lead.full_name || 'Anonymous'}</td>
                <td>${lead.email}</td>
                <td>${new Date(lead.created_at).toLocaleDateString()}</td>
                <td><span class="sv-status-badge sv-status-${lead.status}">${lead.status}</span></td>
                <td class="sv-lead-actions">
                    <select data-id="${lead.rand_id}" data-nonce="${nonce}" onchange="updateLeadStatus(this)">
                        <option value="new" ${lead.status==='new'?'selected':''}>New</option>
                        <option value="contacted" ${lead.status==='contacted'?'selected':''}>Contacted</option>
                        <option value="converted" ${lead.status==='converted'?'selected':''}>Converted</option>
                    </select>
                </td>
            </tr>
        `).join('');
    }

    window.updateLeadStatus = function(el) {
        const id = el.dataset.id;
        const status = el.value;
        const formData = new FormData();
        formData.append('action', 'sv_update_lead_status');
        formData.append('rand_id', id);
        formData.append('status', status);
        formData.append('nonce', el.dataset.nonce);
        fetch(ajaxurl, {method:'POST', body:formData})
        .then(r=>r.json()).then(json=>{
            if(!json.success) alert(json.data.message);
        });
    };

    function fetchLeads() {
        const formData = new FormData();
        formData.append('action', 'sv_get_leads');
        formData.append('filter', filterSelect.value);
        formData.append('nonce', nonce);
        fetch(ajaxurl, {method:'POST', body:formData})
        .then(r=>r.json()).then(json=>{
            if(json.success) { currentLeads = json.data.leads; renderLeads(currentLeads); }
        });
    }

    filterSelect.addEventListener('change', fetchLeads);
    fetchLeads();

    document.getElementById('sv-export-csv').addEventListener('click', function() {
        if(currentLeads.length === 0) { alert('No leads to export.'); return; }
        const headers = ['Name','Email','Status','Source','Date'];
        const rows = currentLeads.map(l => [l.full_name, l.email, l.status, l.source_tag, new Date(l.created_at).toLocaleString()]);
        const csv = [headers, ...rows].map(r => r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\n');
        const blob = new Blob([csv], {type:'text/csv'});
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a'); a.href = url; a.download = 'sv_leads_export.csv'; a.click();
    });
})();
</script>
<?php
return ob_get_clean();
}
function sv_settings_tab($business_id) {
$settings = get_option('bntm_sv_settings_' . $business_id, []);
$is_published = isset($settings['status']) && $settings['status'] === 'published';
$meta_title = isset($settings['meta_title']) ? esc_attr($settings['meta_title']) : '';
$meta_desc = isset($settings['meta_desc']) ? esc_attr($settings['meta_desc']) : '';
$notify_email = isset($settings['notify_email']) ? esc_attr($settings['notify_email']) : '1';
$nonce = wp_create_nonce('sv_nonce');
ob_start();
?>
<div class="bntm-form-section">
    <h3>Page Publishing & SEO</h3>
    <div class="bntm-form-group">
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
            <input type="checkbox" id="sv-publish-toggle" <?php checked($is_published); ?>>
            <strong>Publish Landing Page</strong>
        </label>
        <small style="color:#6b7280;">Make the validation page live for public visitors.</small>
    </div>
    <div class="bntm-form-group">
        <label>SEO Page Title</label>
        <input type="text" id="sv-meta-title" value="<?php echo $meta_title; ?>" placeholder="e.g. Startup Name - Coming Soon">
    </div>
    <div class="bntm-form-group">
        <label>SEO Meta Description</label>
        <textarea id="sv-meta-desc" rows="3" placeholder="Brief description for search engines..."><?php echo $meta_desc; ?></textarea>
    </div>
</div>

<div class="bntm-form-section">
    <h3>Notifications</h3>
    <div class="bntm-form-group">
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
            <input type="checkbox" id="sv-notify-toggle" <?php checked($notify_email, '1'); ?>>
            <strong>Email me when a new lead submits</strong>
        </label>
    </div>
</div>

<button id="sv-save-settings-btn" class="bntm-btn-primary" data-nonce="<?php echo $nonce; ?>">Save Settings</button>
<div id="sv-settings-message"></div>

<script>
(function() {
    document.getElementById('sv-save-settings-btn').addEventListener('click', function() {
        const formData = new FormData();
        formData.append('action', 'sv_save_settings');
        formData.append('status', document.getElementById('sv-publish-toggle').checked ? 'published' : 'draft');
        formData.append('meta_title', document.getElementById('sv-meta-title').value);
        formData.append('meta_desc', document.getElementById('sv-meta-desc').value);
        formData.append('notify_email', document.getElementById('sv-notify-toggle').checked ? '1' : '0');
        formData.append('nonce', this.dataset.nonce);
        
        this.disabled = true; this.textContent = 'Saving...';
        fetch(ajaxurl, {method:'POST', body:formData})
        .then(r=>r.json()).then(json=>{
            const msg = document.getElementById('sv-settings-message');
            msg.innerHTML = '<div class="bntm-notice bntm-notice-'+(json.success?'success':'error')+'">'+json.data.message+'</div>';
            this.disabled = false; this.textContent = 'Save Settings';
        });
    });
})();
</script>
<?php
return ob_get_clean();
}
// ==========================================
// AJAX HANDLER FUNCTIONS
// ==========================================
function bntm_ajax_sv_save_content() {
check_ajax_referer('sv_nonce', 'nonce');
if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
global $wpdb;
$business_id = get_current_user_id();
$table = $wpdb->prefix . 'sv_landing_content';
$blocks = json_decode(stripslashes($_POST['blocks']), true);
if (!is_array($blocks)) wp_send_json_error(['message' => 'Invalid data format']);

$saved = 0;
$wpdb->query('START TRANSACTION');
try {
    foreach ($blocks as $index => $block) {
        $data = $wpdb->prepare("SELECT id FROM {$table} WHERE rand_id = %s AND business_id = %d", $block['rand_id'], $business_id);
        $exists = $wpdb->get_var($data);
        
        if ($exists) {
            $wpdb->update($table, ['block_data' => $block['block_data'], 'sort_order' => $index], ['id' => $exists], ['%s','%d'], ['%d']);
        } else {
            $wpdb->insert($table, ['rand_id' => $block['rand_id'], 'business_id' => $business_id, 'block_type' => $block['block_type'], 'block_data' => $block['block_data'], 'sort_order' => $index], ['%s','%d','%s','%s','%d']);
        }
        $saved++;
    }
    $wpdb->query('COMMIT');
    wp_send_json_success(['message' => "{$saved} blocks saved successfully."]);
} catch (Exception $e) {
    $wpdb->query('ROLLBACK');
    wp_send_json_error(['message' => 'Failed to save blocks.']);
}
}

function bntm_ajax_sv_get_leads() {
check_ajax_referer('sv_nonce', 'nonce');
if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
global $wpdb;
$business_id = get_current_user_id();
$table = $wpdb->prefix . 'sv_waitlist_leads';
$filter = sanitize_text_field($_POST['filter']);
$query = "SELECT * FROM {$table} WHERE business_id = %d";
$params = [$business_id];
if ($filter !== 'all') {
    $query .= " AND status = %s";
    $params[] = $filter;
}
$query .= " ORDER BY created_at DESC";

$leads = $wpdb->get_results($wpdb->prepare($query, $params));
wp_send_json_success(['leads' => $leads]);
}
function bntm_ajax_sv_update_lead_status() {
check_ajax_referer('sv_nonce', 'nonce');
if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
global $wpdb;
$table = $wpdb->prefix . 'sv_waitlist_leads';
$rand_id = sanitize_text_field($_POST['rand_id']);
$status = sanitize_text_field($_POST['status']);
if (!in_array($status, ['new','contacted','converted'])) wp_send_json_error(['message' => 'Invalid status']);

$wpdb->update($table, ['status' => $status], ['rand_id' => $rand_id], ['%s'], ['%s']);
wp_send_json_success(['message' => 'Status updated']);
}
function bntm_ajax_sv_save_settings() {
check_ajax_referer('sv_nonce', 'nonce');
if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
$business_id = get_current_user_id();
$settings = [
'status' => sanitize_text_field($_POST['status']),
'meta_title' => sanitize_text_field($_POST['meta_title']),
'meta_desc' => sanitize_textarea_field($_POST['meta_desc']),
'notify_email' => sanitize_text_field($POST['notify_email'])
];
update_option('bntm_sv_settings' . $business_id, $settings);
wp_send_json_success(['message' => 'Settings saved successfully.']);
}
function bntm_ajax_sv_submit_lead() {
global $wpdb;
// Honeypot check
if (!empty($_POST['hp_url'])) wp_send_json_error(['message' => 'Bot detected']);
$email = sanitize_email($_POST['email']);
if (!is_email($email)) wp_send_json_error(['message' => 'Invalid email address']);

$business_id = intval($_POST['business_id']);
$table = $wpdb->prefix . 'sv_waitlist_leads';

$exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE business_id = %d AND email = %s", $business_id, $email));
if ($exists) wp_send_json_error(['message' => 'You are already on the waitlist.']);

$result = $wpdb->insert($table, [
    'rand_id' => bntm_rand_id(),
    'business_id' => $business_id,
    'email' => $email,
    'full_name' => sanitize_text_field($_POST['full_name'] ?? ''),
    'source_tag' => sanitize_text_field($_POST['source'] ?? 'direct'),
    'status' => 'new'
], ['%s','%d','%s','%s','%s','%s']);

if ($result) {
    $settings = get_option('bntm_sv_settings_' . $business_id, []);
    if (isset($settings['notify_email']) && $settings['notify_email'] == '1') {
        $user = get_user_by('id', $business_id);
        if ($user) wp_mail($user->user_email, 'New Waitlist Signup', "A new user ({$_POST['email']}) joined your waitlist.", 'From: ' . get_option('admin_email'));
    }
    wp_send_json_success(['message' => 'Successfully joined waitlist!']);
} else {
    wp_send_json_error(['message' => 'Submission failed.']);
}
}
// ==========================================
// FRONTEND SHORTCODE FUNCTIONS
// ==========================================
function bntm_shortcode_sv_landing() {
global $wpdb;
$table = $wpdb->prefix . 'sv_landing_content';
$settings_table = $wpdb->prefix . 'options'; // Using WP options pattern for business settings
// Determine business ID. Default to 1 for public if not specified.
$biz_id = isset($_GET['biz_id']) ? intval($_GET['biz_id']) : 1;

$blocks = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE business_id = %d AND status = 'published' ORDER BY sort_order ASC", $biz_id));
$settings = get_option('bntm_sv_settings_' . $biz_id, []);

if (isset($settings['status']) && $settings['status'] !== 'published') {
    return '<div style="text-align:center;padding:40px;font-family:system-ui,sans-serif;color:#6b7280;">This page is currently under maintenance.</div>';
}

ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($settings['meta_title']) ? esc_html($settings['meta_title']) : 'Startup Validator'; ?></title>
    <style>
        :root { --sv-primary: #2563eb; --sv-bg: #ffffff; --sv-text: #0f172a; --sv-muted: #64748b; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--sv-bg); color: var(--sv-text); line-height: 1.6; }
        .sv-container { max-width: 720px; margin: 0 auto; padding: 60px 20px; }
        .sv-hero { text-align: center; margin-bottom: 60px; }
        .sv-hero h1 { font-size: 2.5rem; font-weight: 800; letter-spacing: -0.02em; margin-bottom: 16px; line-height: 1.1; }
        .sv-hero p { font-size: 1.25rem; color: var(--sv-muted); max-width: 500px; margin: 0 auto; }
        .sv-value { background: #f8fafc; padding: 40px; border-radius: 16px; margin-bottom: 50px; }
        .sv-value p { font-size: 1.1rem; text-align: center; color: var(--sv-text); }
        .sv-features { display: grid; gap: 20px; margin-bottom: 50px; }
        .sv-feature { display: flex; align-items: flex-start; gap: 12px; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px; }
        .sv-feature-icon { flex-shrink: 0; width: 24px; height: 24px; background: #10b981; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: bold; margin-top: 4px; }
        .sv-faq-item { border-bottom: 1px solid #e2e8f0; padding: 20px 0; }
        .sv-faq-item:last-child { border-bottom: none; }
        .sv-faq-q { font-weight: 600; font-size: 1.05rem; margin-bottom: 8px; }
        .sv-faq-a { color: var(--sv-muted); }
        .sv-waitlist { background: #0f172a; color: #fff; padding: 50px; border-radius: 20px; text-align: center; margin-top: 60px; }
        .sv-waitlist h2 { margin-bottom: 10px; font-size: 1.8rem; }
        .sv-waitlist p { color: #94a3b8; margin-bottom: 25px; }
        .sv-form { display: flex; flex-direction: column; gap: 12px; max-width: 400px; margin: 0 auto; }
        .sv-input { padding: 14px 16px; border: 1px solid #334155; border-radius: 8px; background: #1e293b; color: #fff; font-size: 1rem; }
        .sv-btn { padding: 14px; background: var(--sv-primary); color: #fff; border: none; border-radius: 8px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: opacity 0.2s; }
        .sv-btn:hover { opacity: 0.9; }
        .sv-btn:disabled { background: #475569; cursor: not-allowed; }
        .sv-message { margin-top: 15px; font-size: 0.9rem; min-height: 20px; }
        .sv-success { color: #10b981; } .sv-error { color: #ef4444; }
        .sv-footer { text-align: center; margin-top: 40px; color: var(--sv-muted); font-size: 0.85rem; }
        @media(max-width:600px){ .sv-hero h1{font-size:2rem} .sv-container{padding:40px 16px} }
    </style>
</head>
<body>
    <div class="sv-container">
        <?php foreach ($blocks as $block): 
            $data = json_decode($block->block_data, true);
            if ($block->block_type === 'hero'): ?>
                <section class="sv-hero">
                    <h1><?php echo esc_html($data['title'] ?? 'Validate Your Idea'); ?></h1>
                    <p><?php echo nl2br(esc_html($data['subtitle'] ?? 'Collect early interest and prove demand before you build.')); ?></p>
                </section>
            <?php elseif ($block->block_type === 'value_prop'): ?>
                <section class="sv-value">
                    <p><?php echo nl2br(esc_html($data['content'] ?? '')); ?></p>
                </section>
            <?php elseif ($block->block_type === 'features' && !empty($data['items'])): ?>
                <section class="sv-features">
                    <?php foreach (array_filter($data['items']) as $item): ?>
                        <div class="sv-feature">
                            <div class="sv-feature-icon">✓</div>
                            <span><?php echo esc_html($item); ?></span>
                        </div>
                    <?php endforeach; ?>
                </section>
            <?php elseif ($block->block_type === 'faq' && !empty($data['items'])): ?>
                <section class="sv-faq-section">
                    <?php foreach ($data['items'] as $faq): ?>
                        <div class="sv-faq-item">
                            <div class="sv-faq-q"><?php echo esc_html($faq['q'] ?? ''); ?></div>
                            <div class="sv-faq-a"><?php echo esc_html($faq['a'] ?? ''); ?></div>
                        </div>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>
        <?php endforeach; ?>

        <section class="sv-waitlist">
            <h2>Join the Waitlist</h2>
            <p>Be the first to know when we launch. No spam, ever.</p>
            <form id="sv-waitlist-form" class="sv-form">
                <input type="text" name="full_name" placeholder="Your Name (optional)" class="sv-input">
                <input type="email" name="email" placeholder="Your Email Address" required class="sv-input">
                <input type="text" name="hp_url" style="display:none;">
                <input type="hidden" name="business_id" value="<?php echo intval($biz_id); ?>">
                <button type="submit" class="sv-btn" id="sv-submit-btn">Notify Me</button>
            </form>
            <div id="sv-form-message" class="sv-message"></div>
        </section>

        <div class="sv-footer">
            &copy; <?php echo date('Y'); ?> Startup Validator. All rights reserved.
        </div>
    </div>

    <script>
    document.getElementById('sv-waitlist-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = document.getElementById('sv-submit-btn');
        const msg = document.getElementById('sv-form-message');
        const formData = new FormData(this);
        formData.append('action', 'sv_submit_lead');
        
        btn.disabled = true; btn.textContent = 'Submitting...';
        msg.textContent = ''; msg.className = 'sv-message';
        
        fetch(ajaxurl, {method:'POST', body:formData})
        .then(r=>r.json()).then(json=>{
            if(json.success) {
                msg.textContent = json.data.message;
                msg.classList.add('sv-success');
                this.reset();
            } else {
                msg.textContent = json.data.message;
                msg.classList.add('sv-error');
            }
            btn.disabled = false; btn.textContent = 'Notify Me';
        });
    });
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
</body>
</html>
<?php
return ob_get_clean();
}
// ==========================================
// HELPER FUNCTIONS
// ==========================================
function sv_generate_default_blocks($business_id) {
global $wpdb;
$table = $wpdb->prefix . 'sv_landing_content';
$defaults = [
['type' => 'hero', 'data' => ['title' => 'Your Startup Name', 'subtitle' => 'Solving a real problem for a growing market. Join us on the journey.']],
['type' => 'value_prop', 'data' => ['content' => 'We are building a streamlined solution to simplify how businesses manage their daily operations. Early access is limited.']],
['type' => 'features', 'data' => ['items' => ['Real-time analytics', 'Automated workflows', 'Zero configuration needed']]],
['type' => 'faq', 'data' => ['items' => [['q'=>'When will you launch?', 'a'=>'We are targeting Q3 2026 for public release.'], ['q'=>'Is there a free tier?', 'a'=>'Yes, early adopters will receive a lifetime free tier.']]]]
];
$blocks = [];
foreach ($defaults as $index => $def) {
$rand_id = bntm_rand_id();
$wpdb->insert($table, [
'rand_id' => $rand_id, 'business_id' => $business_id,
'block_type' => $def['type'], 'block_data' => json_encode($def['data']),
'sort_order' => $index, 'status' => 'draft'
], ['%s','%d','%s','%s','%d','%s']);
$blocks[] = ['rand_id' => $rand_id, 'block_type' => $def['type'], 'block_data' => json_encode($def['data']), 'sort_order' => $index];
}
return $blocks;
}
