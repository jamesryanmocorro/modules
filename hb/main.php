<?php
/**
 * Module Name: Hotel Booking Management
 * Module Slug: hb
 * Description: Complete booking system for hotels and villas with quotation generation, calendar management, and email notifications
 * Version: 2.0.0
 * Author: BNTM Framework
 */

if (!defined('ABSPATH')) exit;

define('BNTM_HB_PATH', dirname(__FILE__) . '/');
define('BNTM_HB_URL', plugin_dir_url(__FILE__));

// ============================================================================
// MODULE CONFIGURATION
// ============================================================================

function bntm_hb_get_pages() {
    return [
        'Hotel & Villa Dashboard' => '[hb_dashboard]',
        'Browse Rooms & Villas' => '[hb_browse]',
        'Booking Form' => '[hb_booking_form]',
        'Booking Form Embed' => '[hb_booking_form_embed]',
        'View Quotation' => '[hb_view_quotation]',
    ];
}

function bntm_hb_get_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $prefix = $wpdb->prefix;
    
    return [
        'hb_properties' => "CREATE TABLE {$prefix}hb_properties (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            name VARCHAR(255) NOT NULL,
            type ENUM('room', 'villa') NOT NULL DEFAULT 'room',
            description TEXT,
            short_description VARCHAR(500),
            capacity INT NOT NULL DEFAULT 2,
            base_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status ENUM('active', 'inactive', 'maintenance') NOT NULL DEFAULT 'active',
            amenities TEXT,
            images TEXT,
            sort_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_type (type),
            INDEX idx_status (status)
        ) {$charset};",
        
        'hb_bookings' => "CREATE TABLE {$prefix}hb_bookings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            quotation_number VARCHAR(50) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            property_id BIGINT UNSIGNED NOT NULL,
            property_name VARCHAR(255) NOT NULL DEFAULT '',
            customer_name VARCHAR(255) NOT NULL,
            customer_email VARCHAR(255) NOT NULL,
            customer_phone VARCHAR(50) NOT NULL,
            check_in DATE NOT NULL,
            check_out DATE NOT NULL,
            num_guests INT NOT NULL DEFAULT 1,
            num_nights INT NOT NULL DEFAULT 1,
            num_rooms INT NOT NULL DEFAULT 1,
            subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            grand_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status ENUM('pending', 'quoted', 'payment_pending', 'confirmed', 'cancelled', 'completed') NOT NULL DEFAULT 'quoted',
            payment_status ENUM('unpaid', 'partial', 'paid') NOT NULL DEFAULT 'unpaid',
            notes TEXT,
            admin_notes TEXT,
            email_sent TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_property (property_id),
            INDEX idx_status (status),
            INDEX idx_dates (check_in, check_out),
            INDEX idx_quotation (quotation_number)
        ) {$charset};",
        'hb_booking_addons' => "CREATE TABLE {$prefix}hb_booking_addons (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            booking_id BIGINT UNSIGNED NOT NULL,
            description VARCHAR(255) NOT NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            quantity INT NOT NULL DEFAULT 1,
            subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_booking (booking_id)
        ) {$charset};"
    ];
}

function bntm_hb_get_shortcodes() {
    return [
        'hb_dashboard' => 'bntm_shortcode_hb_dashboard',
        'hb_browse' => 'bntm_shortcode_hb_browse',
        'hb_booking_form' => 'bntm_shortcode_hb_booking_form',
        'hb_booking_form_embed' => 'bntm_shortcode_hb_booking_form_embed',
        'hb_view_quotation' => 'bntm_shortcode_hb_view_quotation',
    ];
}

function bntm_hb_create_tables() {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $tables = bntm_hb_get_tables();
    foreach ($tables as $sql) {
        dbDelta($sql);
    }
    return count($tables);
}

// ============================================================================
// AJAX HOOKS
// ============================================================================

add_action('wp_ajax_hb_add_property', 'bntm_ajax_hb_add_property');
add_action('wp_ajax_hb_update_property', 'bntm_ajax_hb_update_property');
add_action('wp_ajax_hb_delete_property', 'bntm_ajax_hb_delete_property');
add_action('wp_ajax_hb_update_booking_status', 'bntm_ajax_hb_update_booking_status');
add_action('wp_ajax_hb_update_booking', 'bntm_ajax_hb_update_booking');
add_action('wp_ajax_hb_save_tax_rate', 'bntm_ajax_hb_save_tax_rate');
add_action('wp_ajax_hb_delete_booking', 'bntm_ajax_hb_delete_booking');
add_action('wp_ajax_hb_resend_quotation', 'bntm_ajax_hb_resend_quotation');
add_action('wp_ajax_hb_process_payment', 'bntm_ajax_hb_process_payment');
add_action('wp_ajax_nopriv_hb_process_payment', 'bntm_ajax_hb_process_payment');
add_action('wp_ajax_hb_confirm_booking', 'bntm_ajax_hb_confirm_booking');
add_action('wp_ajax_nopriv_hb_payment_callback', 'bntm_ajax_hb_payment_callback');
add_action('wp_ajax_upload_hb_attachment', 'bntm_ajax_upload_hb_attachment');

add_action('wp_ajax_hb_get_blocked_dates',       'bntm_ajax_hb_get_blocked_dates');
add_action('wp_ajax_nopriv_hb_get_blocked_dates','bntm_ajax_hb_get_blocked_dates');
add_action('wp_ajax_hb_submit_booking',          'bntm_ajax_hb_submit_booking');
add_action('wp_ajax_nopriv_hb_submit_booking',   'bntm_ajax_hb_submit_booking');

add_action('wp_ajax_hb_save_addons',  'bntm_ajax_hb_save_addons');
add_action('wp_ajax_hb_get_addons',   'bntm_ajax_hb_get_addons');

add_action('wp_ajax_hb_save_payment_method',    'bntm_ajax_hb_save_payment_method');
add_action('wp_ajax_hb_remove_payment_method',  'bntm_ajax_hb_remove_payment_method');
add_action('wp_ajax_hb_save_payment_settings',  'bntm_ajax_hb_save_payment_settings');

// ============================================================================
// MAIN DASHBOARD SHORTCODE
// ============================================================================

function bntm_shortcode_hb_dashboard() {
    if (!is_user_logged_in()) {
        return '<div class="bntm-notice">Please log in to access the booking dashboard.</div>';
    }
    
    $current_user = wp_get_current_user();
    $business_id = $current_user->ID;
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
    
    ob_start();
    ?>
    <script>var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';</script>
    
    <div class="bntm-hb-container">
        <div class="bntm-tabs">
            <a href="?tab=overview" class="bntm-tab <?php echo $active_tab === 'overview' ? 'active' : ''; ?>">
                Overview
            </a>
            <a href="?tab=bookings" class="bntm-tab <?php echo $active_tab === 'bookings' ? 'active' : ''; ?>">
                Bookings
            </a>
            <a href="?tab=properties" class="bntm-tab <?php echo $active_tab === 'properties' ? 'active' : ''; ?>">
                Rooms & Villas
            </a>
            <a href="?tab=settings" class="bntm-tab <?php echo $active_tab === 'settings' ? 'active' : ''; ?>">
                Settings
            </a>
        </div>
        
        <div class="bntm-tab-content">
            <?php 
            if ($active_tab === 'overview') echo hb_overview_tab($business_id);
            elseif ($active_tab === 'bookings') echo hb_bookings_tab($business_id);
            elseif ($active_tab === 'properties') echo hb_properties_tab($business_id);
            elseif ($active_tab === 'settings') echo hb_settings_tab($business_id);
            ?>
        </div>
    </div>
    
    <style>
    .bntm-tabs {display: flex; gap: 8px; border-bottom: 2px solid #e5e7eb; background: white; padding: 0; margin: 0; flex-wrap: wrap;}
    .bntm-tab {display: flex; align-items: center; gap: 8px; padding: 16px 24px; background: none; border: none; cursor: pointer; color: #718096; font-weight: 600; font-size: 15px; text-decoration: none; transition: all 0.2s; border-bottom: 3px solid transparent; margin-bottom: -2px;}
    .bntm-tab:hover {color: #4a5568; background: #f7fafc;}
    .bntm-tab.active {color: var(--bntm-primary); border-bottom-color: var(--bntm-primary); background: linear-gradient(to bottom, #f7fafc, white);}
    .bntm-tab-content {padding: 32px;}
    .bntm-dashboard-stats {display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;}
    .bntm-stat-card {background: #ffffff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);}
    .bntm-stat-card h3 {margin: 0 0 10px 0; font-size: 14px; color: #6b7280; font-weight: 500;}
    .bntm-stat-number {margin: 0; font-size: 32px; font-weight: 700; color: #111827;}
    .bntm-form-section {background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);}
    .bntm-form-section h3 {margin: 0 0 16px 0; font-size: 18px; font-weight: 700; color: #1a202c;}
    .bntm-form-group {margin-bottom: 20px;}
    .bntm-form-group label {display: block; font-weight: 600; color: #2d3748; margin-bottom: 8px; font-size: 14px;}
    .bntm-input {width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 14px;}
    .bntm-input:focus {outline: none; border-color: var(--bntm-primary); box-shadow: 0 0 0 3px rgba(var(--bntm-primary-rgb), 0.1);}
    .bntm-form-row {display: grid; grid-template-columns: 1fr 1fr; gap: 20px;}
    .bntm-btn-primary {padding: 10px 20px; background: var(--bntm-primary); color: white; border: none; border-radius: 4px; font-weight: 600; cursor: pointer; font-size: 14px;}
    .bntm-btn-primary:hover {opacity: 0.9;}
    .bntm-btn-secondary {padding: 10px 20px; background: #f7fafc; color: #2d3748; border: 1px solid #cbd5e0; border-radius: 4px; font-weight: 600; cursor: pointer; font-size: 14px;}
    .bntm-btn-secondary:hover {background: #edf2f7;}
    .bntm-btn-small {padding: 6px 12px; background: #f7fafc; color: #2d3748; border: 1px solid #cbd5e0; border-radius: 4px; font-weight: 600; cursor: pointer; font-size: 12px;}
    .bntm-btn-small:hover {background: #edf2f7; border-color: var(--bntm-primary); color: var(--bntm-primary);}
    .bntm-btn-danger {color: #c53030; border-color: #fc8181;}
    .bntm-btn-danger:hover {background: #fff5f5; border-color: #f56565; color: #f56565;}
    .bntm-notice {padding: 12px 16px; border-radius: 4px; margin-bottom: 16px;}
    .bntm-notice-success {background: #f0fdf4; color: #166534; border: 1px solid #86efac;}
    .bntm-notice-error {background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5;}
    .bntm-table {width: 100%; border-collapse: collapse; font-size: 14px;}
    .bntm-table thead {background: #f9fafb;}
    .bntm-table th {padding: 12px; text-align: left; font-weight: 600; color: #374151; border-bottom: 2px solid #e5e7eb;}
    .bntm-table td {padding: 12px; border-bottom: 1px solid #e5e7eb;}
    .bntm-table tbody tr:hover {background: #f9fafb;}
    .bntm-modal {display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow: auto;}
    .bntm-modal-content {background-color: #fff; margin: 50px auto; padding: 30px; border-radius: 8px; width: 90%; max-width: 600px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);}
    .bntm-modal-header {display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #e5e7eb;}
    .bntm-modal-close {font-size: 28px; font-weight: bold; color: #6b7280; cursor: pointer; border: none; background: none;}
    .bntm-modal-close:hover {color: #000;}
    </style>
    
    <script>
    function openModal(id) {document.getElementById(id).style.display = 'block';}
    function closeModal(id) {document.getElementById(id).style.display = 'none';}
    window.onclick = function(event) {if (event.target.classList.contains('bntm-modal')) {event.target.style.display = 'none';}}
    </script>
    <?php
    $content = ob_get_clean();
    return bntm_universal_container('Hotel & Villa Booking Management', $content);
}

// ============================================================================
// TAB FUNCTIONS
// ============================================================================

function hb_overview_tab($business_id) {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'hb_bookings';
    $properties_table = $wpdb->prefix . 'hb_properties';
    
    $total_properties = $wpdb->get_var("SELECT COUNT(*) FROM $properties_table WHERE status='active' AND business_id=$business_id");
    $total_bookings = $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table WHERE business_id=$business_id");
    $pending_bookings = $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table WHERE business_id=$business_id AND status IN ('quoted','payment_pending')");
    
    // Get all bookings for calendar
    $all_bookings = $wpdb->get_results("
        SELECT b.*, p.name as property_name 
        FROM $bookings_table b
        LEFT JOIN $properties_table p ON b.property_id = p.id
        WHERE b.business_id = $business_id
        ORDER BY b.check_in ASC
    ");
    
    // Get booking form URL
    $booking_page = get_page_by_path('booking-form-embed');
    $booking_url = $booking_page ? get_permalink($booking_page->ID) : '';
    
    ob_start();
    ?>
    <style>
    .bntm-calendar-container {background: white; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);}
    .bntm-calendar {display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px; background: #e5e7eb; border: 1px solid #e5e7eb; border-radius: 4px; overflow: hidden;}
    .bntm-calendar-header {display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;}
    .bntm-calendar-nav {display: flex; gap: 10px;}
    .bntm-calendar-day {text-align: center; padding: 12px 5px; font-size: 11px; font-weight: 600; color: #6b7280; background: #f9fafb; border-bottom: 1px solid #e5e7eb;}
    .bntm-calendar-date {text-align: center; padding: 8px 5px; font-size: 13px; background: white; cursor: pointer; min-height: 80px; transition: background 0.2s;}
    .bntm-calendar-date:hover {background: #f9fafb;}
    .bntm-calendar-date.other-month {color: #d1d5db;}
    .bntm-calendar-date.today {border: 2px solid var(--bntm-primary); background: #fef3c7;}
    .date-number {font-weight: 600; margin-bottom: 4px;}
    .booking-bars {width: 100%; display: flex; flex-direction: column; gap: 2px; margin-top: 4px;}
    .booking-bar {height: 6px; border-radius: 3px; cursor: pointer; transition: all 0.2s;}
    .booking-bar:hover {height: 8px; opacity: 0.9;}
    .booking-bar.color-0 {background: #3b82f6;}
    .booking-bar.color-1 {background: #10b981;}
    .booking-bar.color-2 {background: #8b5cf6;}
    .booking-bar.color-3 {background: #f59e0b;}
    .booking-bar.cancelled {opacity: 0.5; background: #9ca3af !important;}
    .bntm-booking-list {margin-top: 20px; padding: 15px; background: #f9fafb; border-radius: 4px; max-height: 400px; overflow-y: auto;}
    .bntm-booking-item {padding: 12px; background: white; border-radius: 4px; margin-bottom: 8px; border-left: 4px solid; cursor: pointer;}
    .bntm-booking-item:hover {transform: translateX(4px);}
    </style>
    
    <div class="bntm-dashboard-stats">
        <div class="bntm-stat-card">
            <h3>Active Properties</h3>
            <p class="bntm-stat-number"><?php echo $total_properties; ?></p>
        </div>
        <div class="bntm-stat-card">
            <h3>Total Bookings</h3>
            <p class="bntm-stat-number"><?php echo $total_bookings; ?></p>
        </div>
        <div class="bntm-stat-card">
            <h3>Pending Bookings</h3>
            <p class="bntm-stat-number"><?php echo $pending_bookings; ?></p>
        </div>
    </div>
    
    <div class="bntm-form-section bntm-calendar-container">
        <div class="bntm-calendar-header">
            <h3 style="margin: 0;">Bookings Calendar</h3>
            <div class="bntm-calendar-nav">
                <button class="bntm-btn-small" id="prev-month">◀ Prev</button>
                <button class="bntm-btn-small" id="next-month">Next ▶</button>
            </div>
        </div>
        <h4 id="current-month" style="text-align: center; margin-bottom: 15px;"></h4>
        <div class="bntm-calendar" id="calendar"></div>
        <div id="selected-bookings" class="bntm-booking-list" style="display: none;"></div>
    </div>
    
    <div class="bntm-form-section">
        <h3>Booking Form Embed Code</h3>
        <p>Copy and paste this code to embed the booking form:</p>
        <textarea readonly onclick="this.select()" style="width: 100%; height: 100px; font-family: monospace; padding: 10px; background: #f9fafb; border: 1px solid #d1d5db; border-radius: 4px;"><?php echo esc_html('<iframe src="' . $booking_url . '" width="100%" height="800" frameborder="0"></iframe>'); ?></textarea>
        <button onclick="navigator.clipboard.writeText(this.previousElementSibling.value); alert('Copied!');" class="bntm-btn-primary" style="margin-top: 10px;">
            Copy Code
        </button>
    </div>
    
    <script>
    const bookingsData = <?php echo json_encode($all_bookings); ?>;
    let currentDate = new Date();
    const bookingColors = {};
    bookingsData.forEach((booking, index) => {
        bookingColors[booking.id] = index % 4;
    });
    
    function getDateRange(startDate, endDate) {
        const dates = [];
        const current = new Date(startDate + 'T00:00:00');
        const end = new Date(endDate + 'T00:00:00');
        while (current <= end) {
            dates.push(current.toISOString().split('T')[0]);
            current.setDate(current.getDate() + 1);
        }
        return dates;
    }
    
    function renderCalendar() {
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();
        const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
        
        document.getElementById('current-month').textContent = `${monthNames[month]} ${year}`;
        
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        
        const bookingsByDate = {};
        bookingsData.forEach(booking => {
            if (!booking.check_in || !booking.check_out) return;
            const dateRange = getDateRange(booking.check_in, booking.check_out);
            dateRange.forEach((date) => {
                if (!bookingsByDate[date]) bookingsByDate[date] = [];
                bookingsByDate[date].push({
                    ...booking,
                    colorIndex: bookingColors[booking.id]
                });
            });
        });
        
        let html = '';
        const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        days.forEach(day => {
            html += `<div class="bntm-calendar-day">${day}</div>`;
        });
        
        for (let i = 0; i < firstDay; i++) {
            html += '<div class="bntm-calendar-date other-month"></div>';
        }
        
        const today = new Date();
        const isCurrentMonth = today.getMonth() === month && today.getFullYear() === year;
        
        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const dayBookings = bookingsByDate[dateStr] || [];
            const isToday = isCurrentMonth && today.getDate() === day;
            
            let classes = 'bntm-calendar-date';
            if (isToday) classes += ' today';
            
            let barsHtml = '';
            if (dayBookings.length > 0) {
                barsHtml = '<div class="booking-bars">';
                dayBookings.forEach(booking => {
                    const cancelledClass = booking.status === 'cancelled' ? 'cancelled' : '';
                    barsHtml += `<div class="booking-bar color-${booking.colorIndex} ${cancelledClass}" 
                                     data-date="${dateStr}"
                                     title="${booking.customer_name} - ${booking.property_name}"></div>`;
                });
                barsHtml += '</div>';
            }
            
            html += `<div class="${classes}" data-date="${dateStr}">
                <span class="date-number">${day}</span>
                ${barsHtml}
            </div>`;
        }
        
        document.getElementById('calendar').innerHTML = html;
        
        document.querySelectorAll('.bntm-calendar-date').forEach(el => {
            el.addEventListener('click', function() {
                const date = this.dataset.date;
                if (date) showBookings(date);
            });
        });
    }
    
    function showBookings(date) {
        const bookings = bookingsData.filter(b => {
            if (!b.check_in || !b.check_out) return false;
            const dateRange = getDateRange(b.check_in, b.check_out);
            return dateRange.includes(date);
        });
        
        const container = document.getElementById('selected-bookings');
        if (bookings.length === 0) {
            container.style.display = 'none';
            return;
        }
        
        const dateObj = new Date(date + 'T00:00:00');
        let html = `<h4 style="margin-top: 0;">Bookings on ${dateObj.toLocaleDateString('en-US', {month: 'long', day: 'numeric', year: 'numeric'})}</h4>`;
        
        bookings.forEach(booking => {
            const colorIndex = bookingColors[booking.id];
            html += `
                <div class="bntm-booking-item" style="border-left-color: ${['#3b82f6','#10b981','#8b5cf6','#f59e0b'][colorIndex]};">
                    <strong>${booking.customer_name}</strong><br>
                    <div style="margin: 5px 0; font-size: 13px; color: #6b7280;">
                        ${booking.property_name} | ${booking.num_guests} guests
                    </div>
                    <div style="font-size: 12px; color: #6b7280;">
                        ${new Date(booking.check_in).toLocaleDateString()} - ${new Date(booking.check_out).toLocaleDateString()}
                        (${booking.num_nights} night${booking.num_nights > 1 ? 's' : ''})
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-top: 8px;">
                        <strong>₱${parseFloat(booking.grand_total).toLocaleString('en-US', {minimumFractionDigits: 2})}</strong>
                        <span style="padding: 2px 8px; background: #f3f4f6; border-radius: 3px; font-size: 11px; text-transform: uppercase;">
                            ${booking.status}
                        </span>
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
        container.style.display = 'block';
    }
    
    document.getElementById('prev-month').addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() - 1);
        renderCalendar();
        document.getElementById('selected-bookings').style.display = 'none';
    });
    
    document.getElementById('next-month').addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() + 1);
        renderCalendar();
        document.getElementById('selected-bookings').style.display = 'none';
    });
    
    renderCalendar();
    </script>
    <?php
    return ob_get_clean();
}

function hb_bookings_tab($business_id) {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'hb_bookings';
    $properties_table = $wpdb->prefix . 'hb_properties';
    
    $filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
    
    $where_clause = "b.business_id = %d";
    $params = [$business_id];
    
    if ($filter_status !== 'all') {
        $where_clause .= " AND b.status = %s";
        $params[] = $filter_status;
    }
    
    $bookings = $wpdb->get_results($wpdb->prepare("
        SELECT b.*, p.name as property_name, p.type as property_type
        FROM {$bookings_table} b
        LEFT JOIN {$properties_table} p ON b.property_id = p.id
        WHERE {$where_clause}
        ORDER BY b.created_at DESC
        LIMIT 50
    ", $params));
    
    $nonce = wp_create_nonce('hb_bookings_nonce');
    
    ob_start();
    ?>
    <style>
    .hb-filter-select {padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 14px;}
    .hb-filter-select:focus {outline: none; border-color: var(--bntm-primary);}
    .hb-status-badge {display:inline-block;padding:3px 10px;border-radius:4px;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.3px;}
    .status-quoted          {background:#fefce8;color:#854d0e;border:1px solid #fde68a;}
    .status-payment_pending {background:#fff7ed;color:#9a3412;border:1px solid #fed7aa;}
    .status-confirmed       {background:#f0fdf4;color:#166534;border:1px solid #86efac;}
    .status-cancelled       {background:#f9fafb;color:#6b7280;border:1px solid #d1d5db;}
    .status-completed       {background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;}
    </style>
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="margin: 0;">All Bookings</h3>
        <select id="status-filter" onchange="window.location.href='?tab=bookings&status='+this.value" class="hb-filter-select">
            <option value="all" <?php selected($filter_status, 'all'); ?>>All Bookings</option>
            <option value="quoted" <?php selected($filter_status, 'quoted'); ?>>Quoted</option>
            <option value="payment_pending" <?php selected($filter_status, 'payment_pending'); ?>>Payment Pending</option>
            <option value="confirmed" <?php selected($filter_status, 'confirmed'); ?>>Confirmed</option>
            <option value="completed" <?php selected($filter_status, 'completed'); ?>>Completed</option>
            <option value="cancelled" <?php selected($filter_status, 'cancelled'); ?>>Cancelled</option>
        </select>
    </div>
    
    <?php if (empty($bookings)): ?>
        <div style="text-align: center; padding: 40px; color: #6b7280;">
            <p>No bookings found</p>
        </div>
    <?php else: ?>
        <table class="bntm-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Customer</th>
                    <th>Property</th>
                    <th>Dates</th>
                    <th>Guests</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $booking): ?>
                <tr>
                    <td><?php echo esc_html($booking->quotation_number); ?></td>
                    <td>
                        <?php echo esc_html($booking->customer_name); ?><br>
                        <small style="color: #6b7280;"><?php echo esc_html($booking->customer_email); ?></small>
                    </td>
                    <td><?php echo esc_html($booking->property_name); ?></td>
                    <td>
                        <?php echo date('M d', strtotime($booking->check_in)); ?> - 
                        <?php echo date('M d, Y', strtotime($booking->check_out)); ?><br>
                        <small style="color: #6b7280;"><?php echo $booking->num_nights; ?> night<?php echo $booking->num_nights > 1 ? 's' : ''; ?></small>
                    </td>
                    <td><?php echo $booking->num_guests; ?></td>
                    <td>₱<?php echo number_format($booking->grand_total, 2); ?></td>
                    <td>
                        <span class="hb-status-badge status-<?php echo esc_attr($booking->status); ?>">
                            <?php
                            $labels = [
                                'quoted'          => 'Quoted',
                                'payment_pending' => 'Payment Pending',
                                'confirmed'       => 'Confirmed',
                                'cancelled'       => 'Cancelled',
                                'completed'       => 'Completed',
                            ];
                            echo $labels[$booking->status] ?? ucfirst($booking->status);
                            ?>
                        </span>
                    </td>
                    <td>
                        <a href="<?php echo get_permalink(get_page_by_path('hotel-view-quotation')) . '?q=' . $booking->quotation_number; ?>"
                           class="bntm-btn-small" target="_blank">View</a>
                        <button class="bntm-btn-small hb-edit-booking-btn"
                                data-booking-id="<?php echo $booking->id; ?>"
                                data-nonce="<?php echo $nonce; ?>"
                                data-status="<?php echo esc_attr($booking->status); ?>"
                                data-payment-status="<?php echo esc_attr($booking->payment_status); ?>"
                                data-customer-name="<?php echo esc_attr($booking->customer_name); ?>"
                                data-customer-email="<?php echo esc_attr($booking->customer_email); ?>"
                                data-customer-phone="<?php echo esc_attr($booking->customer_phone); ?>"
                                data-check-in="<?php echo esc_attr($booking->check_in); ?>"
                                data-check-out="<?php echo esc_attr($booking->check_out); ?>"
                                data-num-guests="<?php echo esc_attr($booking->num_guests); ?>"
                                data-num-nights="<?php echo esc_attr($booking->num_nights); ?>"
                                data-subtotal="<?php echo esc_attr($booking->subtotal); ?>"
                                data-tax-rate="<?php echo esc_attr($booking->tax_rate); ?>"
                                data-tax-amount="<?php echo esc_attr($booking->tax_amount); ?>"
                                data-grand-total="<?php echo esc_attr($booking->grand_total); ?>"
                                data-notes="<?php echo esc_attr($booking->notes); ?>"
                                data-admin-notes="<?php echo esc_attr($booking->admin_notes); ?>"
                                >Edit</button>
                        <button class="bntm-btn-small bntm-btn-danger delete-booking-btn"
                                data-booking-id="<?php echo $booking->id; ?>"
                                data-nonce="<?php echo $nonce; ?>">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
<!-- Edit Booking Modal -->
    <div id="hbEditBookingModal" class="bntm-modal">
        <div class="bntm-modal-content" style="max-width:640px;">
            <div class="bntm-modal-header">
                <h3 style="margin:0;">Edit Booking</h3>
                <button class="bntm-modal-close" onclick="closeModal('hbEditBookingModal')">&times;</button>
            </div>

            <div style="max-height:75vh;overflow-y:auto;padding-right:4px;">

                <!-- SECTION: Customer -->
                <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9ca3af;margin:0 0 10px;">Customer</p>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                    <div class="bntm-form-group" style="margin:0;">
                        <label>Full Name</label>
                        <input type="text" id="hbe-customer-name" class="bntm-input">
                    </div>
                    <div class="bntm-form-group" style="margin:0;">
                        <label>Email</label>
                        <input type="email" id="hbe-customer-email" class="bntm-input">
                    </div>
                    <div class="bntm-form-group" style="margin:0;">
                        <label>Phone</label>
                        <input type="tel" id="hbe-customer-phone" class="bntm-input">
                    </div>
                    <div class="bntm-form-group" style="margin:0;">
                        <label>Number of Guests</label>
                        <input type="number" id="hbe-num-guests" class="bntm-input" min="1">
                    </div>
                </div>

                <hr style="border:none;border-top:1px solid #e5e7eb;margin:0 0 16px;">

                <!-- SECTION: Dates -->
                <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9ca3af;margin:0 0 10px;">Dates</p>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:16px;">
                    <div class="bntm-form-group" style="margin:0;">
                        <label>Check-in</label>
                        <input type="date" id="hbe-check-in" class="bntm-input">
                    </div>
                    <div class="bntm-form-group" style="margin:0;">
                        <label>Check-out</label>
                        <input type="date" id="hbe-check-out" class="bntm-input">
                    </div>
                    <div class="bntm-form-group" style="margin:0;">
                        <label>Nights</label>
                        <input type="text" id="hbe-num-nights" class="bntm-input" readonly
                               style="background:#f9fafb;color:#6b7280;">
                    </div>
                </div>

                <hr style="border:none;border-top:1px solid #e5e7eb;margin:0 0 16px;">

                <!-- SECTION: Pricing -->
                <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9ca3af;margin:0 0 10px;">Pricing</p>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:16px;">
                    <div class="bntm-form-group" style="margin:0;">
                        <label>Subtotal (₱)</label>
                        <input type="number" id="hbe-subtotal" class="bntm-input" min="0" step="0.01">
                    </div>
                    <div class="bntm-form-group" style="margin:0;">
                        <label>Tax Rate (%)</label>
                        <input type="number" id="hbe-tax-rate" class="bntm-input" min="0" max="100" step="0.01">
                    </div>
                    <div class="bntm-form-group" style="margin:0;">
                        <label>Grand Total (₱)</label>
                        <input type="text" id="hbe-grand-total" class="bntm-input" readonly
                               style="background:#f9fafb;color:#166534;font-weight:700;">
                    </div>
                </div>

                <hr style="border:none;border-top:1px solid #e5e7eb;margin:0 0 16px;">

                <!-- SECTION: Status -->
                <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9ca3af;margin:0 0 10px;">Status</p>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                    <div class="bntm-form-group" style="margin:0;">
                        <label>Booking Status</label>
                        <select id="hbe-status" class="bntm-input">
                            <option value="quoted">Quoted</option>
                            <option value="payment_pending">Payment Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    <div class="bntm-form-group" style="margin:0;">
                        <label>Payment Status</label>
                        <select id="hbe-payment-status" class="bntm-input">
                            <option value="unpaid">Unpaid</option>
                            <option value="partial">Partial</option>
                            <option value="paid">Paid</option>
                        </select>
                    </div>
                </div>
            <hr style="border:none;border-top:1px solid #e5e7eb;margin:0 0 16px;">

                <!-- SECTION: Add-ons -->
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                    <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9ca3af;margin:0;">Add-ons</p>
                    <button type="button" onclick="hbeAddAddonRow()" 
                            style="padding:4px 12px;background:var(--bntm-primary);color:#fff;border:none;border-radius:4px;font-size:12px;font-weight:600;cursor:pointer;">
                        + Add Item
                    </button>
                </div>

                <div id="hbe-addons-list" style="margin-bottom:10px;">
                    <!-- rows injected by JS -->
                </div>

                <div style="display:flex;justify-content:flex-end;gap:16px;padding:8px 10px;background:#f9fafb;border-radius:6px;font-size:13px;margin-bottom:16px;">
                    <span style="color:#6b7280;">Add-ons Total:</span>
                    <strong id="hbe-addons-total" style="color:var(--bntm-primary);">₱0.00</strong>
                </div>
                <hr style="border:none;border-top:1px solid #e5e7eb;margin:0 0 16px;">

                <!-- SECTION: Notes -->
                <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9ca3af;margin:0 0 10px;">Notes</p>
                <div class="bntm-form-group" style="margin-bottom:12px;">
                    <label>Guest Notes</label>
                    <textarea id="hbe-notes" rows="2" class="bntm-input"></textarea>
                </div>
                <div class="bntm-form-group" style="margin-bottom:16px;">
                    <label>Admin Notes</label>
                    <textarea id="hbe-admin-notes" rows="2" class="bntm-input"></textarea>
                </div>

                <!-- Feedback message -->
                <div id="hb-edit-modal-msg" style="margin-bottom:10px;"></div>

                <!-- Action buttons -->
                <div style="display:flex;gap:10px;">
                    <button type="button" class="bntm-btn-secondary" style="flex:1;"
                            onclick="closeModal('hbEditBookingModal')">Cancel</button>
                    <button type="button" class="bntm-btn-primary" style="flex:1;"
                            id="hb-edit-save-btn" onclick="hbSaveBookingEdit()">Save Changes</button>
                </div>

                <hr style="margin:18px 0;border:none;border-top:1px solid #e5e7eb;">

                <!-- Resend -->
                <p style="font-size:13px;color:#6b7280;margin:0 0 10px;">Resend the quotation email to the customer:</p>
                <button type="button" class="bntm-btn-secondary" style="width:100%;"
                        id="hb-edit-resend-btn" onclick="hbResendFromModal()">📧 Resend Quotation Email</button>

            </div><!-- scroll wrapper -->
        </div>
    </div>

    <script>
    let hbEditBookingId = null;
    let hbEditNonce     = null;

    // ── Open modal & populate all fields ──────────────────────────────────────
document.querySelectorAll('.hb-edit-booking-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const d = this.dataset;
            hbEditBookingId = d.bookingId;
            hbEditNonce     = d.nonce;

            // Customer
            document.getElementById('hbe-customer-name').value  = d.customerName  ?? '';
            document.getElementById('hbe-customer-email').value = d.customerEmail ?? '';
            document.getElementById('hbe-customer-phone').value = d.customerPhone ?? '';
            document.getElementById('hbe-num-guests').value     = d.numGuests     ?? 1;

            // Dates
            document.getElementById('hbe-check-in').value   = d.checkIn  ?? '';
            document.getElementById('hbe-check-out').value  = d.checkOut ?? '';
            document.getElementById('hbe-num-nights').value = d.numNights ?? '';

            // Pricing
            document.getElementById('hbe-subtotal').value    = parseFloat(d.subtotal   ?? 0).toFixed(2);
            document.getElementById('hbe-tax-rate').value    = parseFloat(d.taxRate    ?? 0).toFixed(2);
            document.getElementById('hbe-grand-total').value = parseFloat(d.grandTotal ?? 0).toFixed(2);

            // Status
            document.getElementById('hbe-status').value         = d.status        ?? 'quoted';
            document.getElementById('hbe-payment-status').value = d.paymentStatus ?? 'unpaid';

            // Notes
            document.getElementById('hbe-notes').value       = d.notes      ?? '';
            document.getElementById('hbe-admin-notes').value = d.adminNotes ?? '';

            // Reset UI
            document.getElementById('hb-edit-modal-msg').innerHTML    = '';
            document.getElementById('hb-edit-save-btn').disabled      = false;
            document.getElementById('hb-edit-save-btn').textContent   = 'Save Changes';
            document.getElementById('hb-edit-resend-btn').disabled    = false;
            document.getElementById('hb-edit-resend-btn').textContent = '📧 Resend Quotation Email';
            document.getElementById('hbe-addons-list').innerHTML      = '<p style="color:#9ca3af;font-size:13px;text-align:center;padding:8px 0;">Loading…</p>';
            document.getElementById('hbe-addons-total').textContent   = '₱0.00';

            openModal('hbEditBookingModal');

            // Fetch existing add-ons
            const fd = new FormData();
            fd.append('action',     'hb_get_addons');
            fd.append('booking_id', hbEditBookingId);
            fd.append('nonce',      hbEditNonce);
            fetch(ajaxurl, {method:'POST', body:fd})
                .then(r => r.json())
                .then(json => {
                    if (json.success) hbeRenderAddons(json.data.addons);
                    else document.getElementById('hbe-addons-list').innerHTML = '';
                })
                .catch(() => {
                    document.getElementById('hbe-addons-list').innerHTML = '';
                });
        });
    });

    // ── Auto-recalculate nights when dates change ─────────────────────────────
    ['hbe-check-in', 'hbe-check-out'].forEach(id => {
        document.getElementById(id).addEventListener('change', hbeRecalcNights);
    });

    function hbeRecalcNights() {
        const ci = document.getElementById('hbe-check-in').value;
        const co = document.getElementById('hbe-check-out').value;
        if (!ci || !co) return;
        const diff = Math.round((new Date(co) - new Date(ci)) / 86400000);
        if (diff > 0) document.getElementById('hbe-num-nights').value = diff;
    }

    // ── Auto-recalculate grand total when subtotal or tax rate changes ─────────
    ['hbe-subtotal', 'hbe-tax-rate'].forEach(id => {
        document.getElementById(id).addEventListener('input', hbeRecalcTotal);
    });

    function hbeRecalcTotal() {
        const sub  = parseFloat(document.getElementById('hbe-subtotal').value)  || 0;
        const rate = parseFloat(document.getElementById('hbe-tax-rate').value)  || 0;
        const tot  = sub + (sub * rate / 100);
        document.getElementById('hbe-grand-total').value = tot.toFixed(2);
    }
// ── Add-ons ───────────────────────────────────────────────────────────────
    function hbeRenderAddons(addons) {
        const list = document.getElementById('hbe-addons-list');
        list.innerHTML = '';
        (addons || []).forEach(a => hbeAddAddonRow(a));
        hbeRecalcAddons();
    }

    function hbeAddAddonRow(data) {
        data = data || {};
        const list = document.getElementById('hbe-addons-list');
        const row  = document.createElement('div');
        row.className = 'hbe-addon-row';
        row.style.cssText = 'display:grid;grid-template-columns:1fr 90px 60px 32px;gap:8px;margin-bottom:8px;align-items:center;';
        row.innerHTML = `
            <input type="text"   class="bntm-input hbe-addon-desc"  placeholder="Description"  value="${hbeEsc(data.description ?? '')}" style="font-size:13px;padding:7px 9px;">
            <input type="number" class="bntm-input hbe-addon-price" placeholder="Price"   value="${parseFloat(data.price ?? 0).toFixed(2)}"    min="0" step="0.01" style="font-size:13px;padding:7px 9px;">
            <input type="number" class="bntm-input hbe-addon-qty"   placeholder="Qty"     value="${parseInt(data.quantity ?? 1)}"              min="1"             style="font-size:13px;padding:7px 9px;">
            <button type="button" onclick="this.closest('.hbe-addon-row').remove(); hbeRecalcAddons();"
                    style="width:32px;height:32px;border:1px solid #fca5a5;background:#fff5f5;color:#ef4444;border-radius:4px;font-size:16px;cursor:pointer;line-height:1;">×</button>
        `;
        // Recalc on any input change
        row.querySelectorAll('input').forEach(inp =>
            inp.addEventListener('input', hbeRecalcAddons)
        );
        list.appendChild(row);
        hbeRecalcAddons();
    }

    function hbeRecalcAddons() {
        let total = 0;
        document.querySelectorAll('.hbe-addon-row').forEach(row => {
            const price = parseFloat(row.querySelector('.hbe-addon-price').value) || 0;
            const qty   = parseInt(row.querySelector('.hbe-addon-qty').value)     || 1;
            total += price * qty;
        });
        document.getElementById('hbe-addons-total').textContent =
            '₱' + total.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});

        // Also update the grand total preview in pricing section
        const sub    = parseFloat(document.getElementById('hbe-subtotal').value)  || 0;
        const rate   = parseFloat(document.getElementById('hbe-tax-rate').value)  || 0;
        const taxable = sub + total;
        const tot    = taxable + (taxable * rate / 100);
        document.getElementById('hbe-grand-total').value = tot.toFixed(2);
    }

    function hbeCollectAddons() {
        const addons = [];
        document.querySelectorAll('.hbe-addon-row').forEach(row => {
            const desc  = row.querySelector('.hbe-addon-desc').value.trim();
            const price = parseFloat(row.querySelector('.hbe-addon-price').value) || 0;
            const qty   = parseInt(row.querySelector('.hbe-addon-qty').value)     || 1;
            if (desc) addons.push({description: desc, price, quantity: qty});
        });
        return addons;
    }

    function hbeEsc(str) {
        return String(str).replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
    // ── Save ──────────────────────────────────────────────────────────────────
    function hbSaveBookingEdit() {
        const btn = document.getElementById('hb-edit-save-btn');
        const msg = document.getElementById('hb-edit-modal-msg');

        btn.disabled    = true;
        btn.textContent = 'Saving…';
        msg.innerHTML   = '';

        const fd = new FormData();
        fd.append('action',          'hb_update_booking');
        fd.append('save_addons',     '1');
        fd.append('nonce',           hbEditNonce);
        fd.append('booking_id',      hbEditBookingId);
        fd.append('customer_name',   document.getElementById('hbe-customer-name').value.trim());
        fd.append('customer_email',  document.getElementById('hbe-customer-email').value.trim());
        fd.append('customer_phone',  document.getElementById('hbe-customer-phone').value.trim());
        fd.append('num_guests',      document.getElementById('hbe-num-guests').value);
        fd.append('check_in',        document.getElementById('hbe-check-in').value);
        fd.append('check_out',       document.getElementById('hbe-check-out').value);
        fd.append('subtotal',        document.getElementById('hbe-subtotal').value);
        fd.append('tax_rate',        document.getElementById('hbe-tax-rate').value);
        fd.append('status',          document.getElementById('hbe-status').value);
        fd.append('payment_status',  document.getElementById('hbe-payment-status').value);
        fd.append('notes',           document.getElementById('hbe-notes').value);
        fd.append('admin_notes',     document.getElementById('hbe-admin-notes').value);
        // Collect add-ons and append as indexed array
        const addons = hbeCollectAddons();
        addons.forEach((a, i) => {
            fd.append(`addons[${i}][description]`, a.description);
            fd.append(`addons[${i}][price]`,       a.price);
            fd.append(`addons[${i}][quantity]`,    a.quantity);
        });
        fetch(ajaxurl, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    msg.innerHTML = '<div class="bntm-notice bntm-notice-success">' + json.data.message + '</div>';

                    // Reflect changes back to the table row without reload
                    const editBtn = document.querySelector(`.hb-edit-booking-btn[data-booking-id="${hbEditBookingId}"]`);
                    if (editBtn) {
                        const row = editBtn.closest('tr');
                        const newStatus = document.getElementById('hbe-status').value;
                        const statusLabels = {
                            quoted: 'Quoted', payment_pending: 'Payment Pending',
                            confirmed: 'Confirmed', cancelled: 'Cancelled', completed: 'Completed'
                        };

                        // Update badge
                        const badge = row.querySelector('.hb-status-badge');
                        if (badge) {
                            badge.textContent = statusLabels[newStatus] ?? newStatus;
                            badge.className   = 'hb-status-badge status-' + newStatus;
                        }

                        // Update customer cell
                        const cells = row.querySelectorAll('td');
                        if (cells[1]) cells[1].innerHTML =
                            document.getElementById('hbe-customer-name').value +
                            '<br><small style="color:#6b7280;">' +
                            document.getElementById('hbe-customer-email').value + '</small>';

                        // Update amount cell
                        const tot = parseFloat(document.getElementById('hbe-grand-total').value);
                        if (cells[5]) cells[5].textContent = '₱' + tot.toLocaleString('en-US', {minimumFractionDigits:2});

                        // Update nights cell
                        const nights = json.data.nights;
                        if (cells[3] && nights) {
                            const ci  = document.getElementById('hbe-check-in').value;
                            const co  = document.getElementById('hbe-check-out').value;
                            const cin = new Date(ci+'T00:00:00');
                            const cou = new Date(co+'T00:00:00');
                            cells[3].innerHTML =
                                cin.toLocaleDateString('en-US',{month:'short',day:'numeric'}) + ' – ' +
                                cou.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) +
                                '<br><small style="color:#6b7280;">' + nights + ' night' + (nights>1?'s':'') + '</small>';
                        }

                        // Sync data attributes
                        editBtn.dataset.status        = newStatus;
                        editBtn.dataset.paymentStatus = document.getElementById('hbe-payment-status').value;
                        editBtn.dataset.customerName  = document.getElementById('hbe-customer-name').value;
                        editBtn.dataset.customerEmail = document.getElementById('hbe-customer-email').value;
                        editBtn.dataset.customerPhone = document.getElementById('hbe-customer-phone').value;
                        editBtn.dataset.numGuests     = document.getElementById('hbe-num-guests').value;
                        editBtn.dataset.checkIn       = document.getElementById('hbe-check-in').value;
                        editBtn.dataset.checkOut      = document.getElementById('hbe-check-out').value;
                        editBtn.dataset.numNights     = json.data.nights ?? editBtn.dataset.numNights;
                        editBtn.dataset.subtotal      = json.data.subtotal;
                        editBtn.dataset.taxRate       = document.getElementById('hbe-tax-rate').value;
                        editBtn.dataset.taxAmount     = json.data.tax_amount;
                        editBtn.dataset.grandTotal    = json.data.grand_total;
                        editBtn.dataset.notes         = document.getElementById('hbe-notes').value;
                        editBtn.dataset.adminNotes    = document.getElementById('hbe-admin-notes').value;
                    }

                    btn.disabled    = false;
                    btn.textContent = 'Save Changes';
                } else {
                    msg.innerHTML   = '<div class="bntm-notice bntm-notice-error">' + (json.data?.message ?? 'Error') + '</div>';
                    btn.disabled    = false;
                    btn.textContent = 'Save Changes';
                }
            })
            .catch(() => {
                msg.innerHTML   = '<div class="bntm-notice bntm-notice-error">Network error. Please try again.</div>';
                btn.disabled    = false;
                btn.textContent = 'Save Changes';
            });
    }

    // ── Resend ────────────────────────────────────────────────────────────────
    function hbResendFromModal() {
        const btn = document.getElementById('hb-edit-resend-btn');
        if (!confirm('Resend quotation email to this customer?')) return;

        btn.disabled    = true;
        btn.textContent = 'Sending…';

        const fd = new FormData();
        fd.append('action',     'hb_resend_quotation');
        fd.append('booking_id', hbEditBookingId);
        fd.append('nonce',      hbEditNonce);

        fetch(ajaxurl, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(json => {
                document.getElementById('hb-edit-modal-msg').innerHTML =
                    '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' +
                    json.data.message + '</div>';
                btn.disabled    = false;
                btn.textContent = '📧 Resend Quotation Email';
            })
            .catch(() => {
                btn.disabled    = false;
                btn.textContent = '📧 Resend Quotation Email';
            });
    }

    // ── Delete ────────────────────────────────────────────────────────────────
    document.querySelectorAll('.delete-booking-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            if (!confirm('Delete this booking? This cannot be undone.')) return;

            const fd = new FormData();
            fd.append('action',     'hb_delete_booking');
            fd.append('booking_id', this.dataset.bookingId);
            fd.append('nonce',      this.dataset.nonce);

            fetch(ajaxurl, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(json => {
                    alert(json.data.message);
                    if (json.success) location.reload();
                });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

function hb_properties_tab($business_id) {
    global $wpdb;
    $properties_table = $wpdb->prefix . 'hb_properties';
    
    $properties = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$properties_table}
        WHERE business_id = %d
        ORDER BY sort_order ASC, name ASC
    ", $business_id));
    
    $nonce = wp_create_nonce('hb_properties_nonce');
    
    ob_start();
    ?>
    <style>
    .property-photo-thumb {width: 60px; height: 60px; object-fit: cover; border-radius: 4px;}
    .hb-properties-grid {display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;}
    .hb-property-card {background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); cursor: pointer; transition: transform 0.2s;}
    .hb-property-card:hover {transform: translateY(-4px);}
    .hb-property-image {width: 100%; height: 200px; object-fit: cover; background: #f3f4f6;}
    .hb-property-details {padding: 20px;}
    </style>
    
    <div class="bntm-form-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0;">Rooms & Villas</h3>
            <button onclick="openAddPropertyModal()" class="bntm-btn-primary">Add Room/Villa</button>
        </div>
        
        <div class="hb-properties-grid">
            <?php if (empty($properties)): ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #6b7280;">
                    <p>No Rooms yet. Click "Add Rooms" to get started.</p>
                </div>
            <?php else: foreach ($properties as $prop): 
                $images = !empty($prop->images) ? json_decode($prop->images, true) : [];
                $first_image = !empty($images) ? $images[0] : '';
            ?>
                <div class="hb-property-card">
                    <?php if ($first_image): ?>
                        <img src="<?php echo esc_url($first_image); ?>" class="hb-property-image" alt="<?php echo esc_attr($prop->name); ?>">
                    <?php else: ?>
                        <div class="hb-property-image" style="display: flex; align-items: center; justify-content: center; font-size: 48px;">🏨</div>
                    <?php endif; ?>
                    
                    <div class="hb-property-details">
                        <h4 style="margin: 0 0 8px 0;"><?php echo esc_html($prop->name); ?></h4>
                        <?php if ($prop->short_description): ?>
                            <p style="margin: 0 0 12px 0; font-size: 13px; color: #6b7280;"><?php echo esc_html($prop->short_description); ?></p>
                        <?php endif; ?>
                        
                        <div style="display: flex; gap: 15px; margin-bottom: 12px; font-size: 13px; color: #6b7280;">
                            <span><?php echo $prop->capacity; ?> guests</span>
                            <span>₱<?php echo number_format($prop->base_price, 2); ?>/night</span>
                        </div>
                        
                        <div style="margin-bottom: 12px;">
                            <span style="padding: 4px 10px; background: #f3f4f6; border-radius: 4px; font-size: 11px; text-transform: uppercase;">
                                <?php echo ucfirst($prop->status); ?>
                            </span>
                        </div>
                        
                        <div style="display: flex; gap: 8px;">
                            <button onclick='editProperty(<?php echo json_encode($prop); ?>)' class="bntm-btn-small">Edit</button>
                            <button onclick="deleteProperty(<?php echo $prop->id; ?>, '<?php echo esc_js($prop->name); ?>')" class="bntm-btn-small bntm-btn-danger">Delete</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
    
    <!-- Property Modal -->
    <div id="propertyModal" class="bntm-modal">
        <div class="bntm-modal-content">
            <div class="bntm-modal-header">
                <h3 id="modalTitle" style="margin: 0;">Add Rooms</h3>
                <button class="bntm-modal-close" onclick="closeModal('propertyModal')">&times;</button>
            </div>
            <div>
                <form id="propertyForm" class="bntm-form">
                    <input type="hidden" id="property_id" name="property_id" value="">
                    
                    <div class="bntm-form-group">
                        <label>Room Type *</label>
                        <select name="type" id="property_type" required class="bntm-input">
                            <option value="room">Room</option>
                            <option value="villa">Villa</option>
                        </select>
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Room Name *</label>
                        <input type="text" name="name" id="property_name" required class="bntm-input" placeholder="e.g., Deluxe Ocean View Room">
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Short Description</label>
                        <input type="text" name="short_description" id="property_short_desc" class="bntm-input" maxlength="500">
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Description</label>
                        <textarea name="description" id="property_description" rows="4" class="bntm-input"></textarea>
                    </div>
                    
                    <div class="bntm-form-row">
                        <div class="bntm-form-group">
                            <label>Capacity (Guests) *</label>
                            <input type="number" name="capacity" id="property_capacity" required class="bntm-input" min="1" value="2">
                        </div>
                        <div class="bntm-form-group">
                            <label>Base Price per Night *</label>
                            <input type="number" name="base_price" id="property_price" required class="bntm-input" min="0" step="0.01" value="0.00">
                        </div>
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Status</label>
                        <select name="status" id="property_status" class="bntm-input">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Amenities (one per line)</label>
                        <textarea name="amenities" id="property_amenities" rows="4" class="bntm-input" placeholder="WiFi&#10;Air Conditioning&#10;Mini Bar"></textarea>
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Room Images</label>
                        <input type="file" id="property_file_input" multiple accept="image/*">
                        <div id="upload-preview" style="margin-top: 10px;"></div>
                        <textarea name="images" id="property_images" rows="3" class="bntm-input" placeholder="Or paste image URLs, one per line" style="margin-top: 10px;"></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="button" onclick="closeModal('propertyModal')" class="bntm-btn-secondary" style="flex: 1;">Cancel</button>
                        <button type="submit" class="bntm-btn-primary" style="flex: 1;">Save Property</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    let uploadedImages = [];
    
    function openAddPropertyModal() {
        document.getElementById('modalTitle').textContent = 'Add Property';
        document.getElementById('propertyForm').reset();
        document.getElementById('property_id').value = '';
        uploadedImages = [];
        document.getElementById('upload-preview').innerHTML = '';
        openModal('propertyModal');
    }
    
    function editProperty(property) {
        document.getElementById('modalTitle').textContent = 'Edit Property';
        document.getElementById('property_id').value = property.id;
        document.getElementById('property_type').value = property.type;
        document.getElementById('property_name').value = property.name;
        document.getElementById('property_short_desc').value = property.short_description || '';
        document.getElementById('property_description').value = property.description || '';
        document.getElementById('property_capacity').value = property.capacity;
        document.getElementById('property_price').value = property.base_price;
        document.getElementById('property_status').value = property.status;
        document.getElementById('property_amenities').value = property.amenities || '';
        
        if (property.images) {
            try {
                const images = JSON.parse(property.images);
                document.getElementById('property_images').value = images.join('\n');
            } catch (e) {
                document.getElementById('property_images').value = '';
            }
        }
        
        uploadedImages = [];
        document.getElementById('upload-preview').innerHTML = '';
        openModal('propertyModal');
    }
    
    function deleteProperty(id, name) {
        if (!confirm('Delete property "' + name + '"? This cannot be undone.')) return;
        
        const formData = new FormData();
        formData.append('action', 'hb_delete_property');
        formData.append('property_id', id);
        formData.append('nonce', '<?php echo $nonce; ?>');
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            alert(json.data.message);
            if (json.success) location.reload();
        });
    }
    
    document.getElementById('property_file_input').addEventListener('change', function(e) {
        const files = Array.from(e.target.files);
        uploadedImages = [];
        document.getElementById('upload-preview').innerHTML = '';
        
        files.forEach((file) => {
            if (!file.type.startsWith('image/')) return;
            const reader = new FileReader();
            reader.onload = (e) => {
                uploadedImages.push({file: file, data: e.target.result});
                const preview = document.createElement('div');
                preview.style.cssText = 'display: inline-block; margin: 5px;';
                preview.innerHTML = `<img src="${e.target.result}" style="width: 100px; height: 100px; object-fit: cover; border-radius: 4px;">`;
                document.getElementById('upload-preview').appendChild(preview);
            };
            reader.readAsDataURL(file);
        });
    });
    
    document.getElementById('propertyForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const submitBtn = this.querySelector('button[type="submit"]');
        const propertyId = document.getElementById('property_id').value;
        
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';
        
        const uploadPromises = [];
        const uploadedUrls = [];
        
        if (uploadedImages.length > 0) {
            uploadedImages.forEach((img) => {
                uploadPromises.push(
                    new Promise((resolve, reject) => {
                        const fileFormData = new FormData();
                        fileFormData.append('action', 'upload_hb_attachment');
                        fileFormData.append('file', img.file);
                        
                        fetch(ajaxurl, {method: 'POST', body: fileFormData})
                        .then(r => r.json())
                        .then(json => {
                            if (json.success) {
                                uploadedUrls.push(json.data.url);
                                resolve();
                            } else {
                                reject(new Error(json.data?.message || 'Upload failed'));
                            }
                        })
                        .catch(reject);
                    })
                );
            });
        }
        
        Promise.all(uploadPromises)
        .then(() => {
            const formData = new FormData(this);
            formData.set('images', uploadedUrls.join('\n'));
            formData.append('action', propertyId ? 'hb_update_property' : 'hb_add_property');
            formData.append('nonce', '<?php echo $nonce; ?>');
            
            return fetch(ajaxurl, {method: 'POST', body: formData});
        })
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                alert(json.data.message || 'Property saved successfully!');
                closeModal('propertyModal');
                location.reload();
            } else {
                alert(json.data?.message || 'Failed to save property');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Save Property';
            }
        })
        .catch(err => {
            alert('Error: ' + err.message);
            submitBtn.disabled = false;
            submitBtn.textContent = 'Save Property';
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

function hb_settings_tab($business_id) {
    $tax_rate       = floatval(bntm_get_setting('hb_tax_rate',               '12.00'));
    $downpayment    = intval(bntm_get_setting('hb_downpayment_percentage',   '0'));
    $terms          = bntm_get_setting('hb_terms',                           '');
    $methods        = json_decode(bntm_get_setting('hb_payment_methods',     '[]'), true);
    if (!is_array($methods)) $methods = [];
    $nonce          = wp_create_nonce('hb_settings_nonce');

    ob_start();
    ?>
    <!-- ── Tax ──────────────────────────────────────────────────────────────── -->
    <div class="bntm-form-section">
        <h3>Tax Configuration</h3>
        <p style="color:#6b7280;margin-bottom:16px;">Set the tax rate applied to all bookings.</p>
        <form id="hbTaxForm" style="max-width:400px;">
            <div class="bntm-form-group">
                <label>Tax Rate (%)</label>
                <input type="number" id="hb-tax-rate" name="tax_rate" class="bntm-input"
                       min="0" max="100" step="0.01"
                       value="<?php echo esc_attr($tax_rate); ?>" required>
                <small style="color:#6b7280;">Default: 12.00% (Philippine VAT)</small>
            </div>
            <button type="submit" class="bntm-btn-primary">Save Tax Rate</button>
        </form>
        <div id="hb-tax-msg" style="margin-top:12px;"></div>
    </div>

    <!-- ── Payment Settings ─────────────────────────────────────────────────── -->
    <div class="bntm-form-section">
        <h3>Payment Settings</h3>
        <p style="color:#6b7280;margin-bottom:16px;">
            Configure down payment requirements and terms shown on quotations.
        </p>
        <form id="hbPaySettingsForm" style="max-width:560px;">
            <div class="bntm-form-group">
                <label>Down Payment Percentage (%)</label>
                <input type="number" id="hb-downpayment" name="downpayment_percentage"
                       class="bntm-input" min="0" max="100" step="1"
                       value="<?php echo esc_attr($downpayment); ?>"
                       placeholder="e.g. 50">
                <small style="color:#6b7280;">
                    Set 0 to require full payment only. When set, the quotation will show
                    a down payment amount and remaining balance.
                </small>
            </div>
            <div class="bntm-form-group">
                <label>Terms &amp; Conditions</label>
                <textarea id="hb-terms" name="terms" rows="7" class="bntm-input"
                          placeholder="Enter terms and conditions to display on quotations…"><?php echo esc_textarea($terms); ?></textarea>
                <small style="color:#6b7280;">Displayed at the bottom of every quotation.</small>
            </div>
            <button type="submit" class="bntm-btn-primary">Save Payment Settings</button>
        </form>
        <div id="hb-pay-settings-msg" style="margin-top:12px;"></div>
    </div>

    <!-- ── Payment Methods ──────────────────────────────────────────────────── -->
    <div class="bntm-form-section">
        <h3>Payment Methods</h3>
        <p style="color:#6b7280;margin-bottom:16px;">
            These will appear on every quotation so guests know how to pay.
        </p>

        <!-- Existing methods list -->
        <div id="hb-methods-list" style="margin-bottom:20px;">
            <?php if (empty($methods)): ?>
                <p style="color:#9ca3af;">No payment methods configured yet.</p>
            <?php else: ?>
                <?php foreach ($methods as $i => $m): ?>
                <div style="padding:14px;background:#f9fafb;border-radius:8px;margin-bottom:10px;
                            display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
                    <div>
                        <strong><?php echo esc_html($m['name']); ?></strong>
                        <span style="color:#6b7280;margin-left:8px;font-size:13px;">
                            <?php echo esc_html($m['type']); ?>
                        </span>
                        <?php if (!empty($m['account_name'])): ?>
                            <div style="font-size:13px;color:#6b7280;margin-top:4px;">
                                Account: <?php echo esc_html($m['account_name']); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($m['account_number'])): ?>
                            <div style="font-size:13px;color:#6b7280;">
                                Number: <?php echo esc_html($m['account_number']); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($m['description'])): ?>
                            <div style="font-size:12px;color:#9ca3af;margin-top:4px;">
                                <?php echo esc_html($m['description']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button class="bntm-btn-small bntm-btn-danger hb-remove-method"
                            data-index="<?php echo $i; ?>"
                            data-nonce="<?php echo $nonce; ?>"
                            style="flex-shrink:0;">
                        Remove
                    </button>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Add method form -->
        <div style="padding:20px;background:#f9fafb;border-radius:8px;">
            <h4 style="margin:0 0 16px;">Add Payment Method</h4>
            <form id="hbAddMethodForm">
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Payment Type *</label>
                        <select name="payment_type" class="bntm-input" required>
                            <option value="">Select Type</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Cash">Cash Payment</option>
                            <option value="GCash">GCash</option>
                            <option value="PayMaya">PayMaya</option>
                            <option value="Credit Card">Credit Card</option>
                        </select>
                    </div>
                    <div class="bntm-form-group">
                        <label>Display Name *</label>
                        <input type="text" name="payment_name" class="bntm-input" required
                               placeholder="e.g. BDO Bank Transfer">
                    </div>
                </div>
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Account Name</label>
                        <input type="text" name="account_name" class="bntm-input"
                               placeholder="Account holder name">
                    </div>
                    <div class="bntm-form-group">
                        <label>Account Number</label>
                        <input type="text" name="account_number" class="bntm-input"
                               placeholder="Account / phone number">
                    </div>
                </div>
                <div class="bntm-form-group">
                    <label>Instructions</label>
                    <textarea name="payment_description" class="bntm-input" rows="2"
                              placeholder="e.g. Transfer to this account then send proof of payment."></textarea>
                </div>
                <button type="submit" class="bntm-btn-primary">Add Payment Method</button>
            </form>
            <div id="hb-method-msg" style="margin-top:12px;"></div>
        </div>
    </div>

    <!-- ── Embed codes ───────────────────────────────────────────────────────── -->
    <div class="bntm-form-section">
        <h3>Booking Form Embed</h3>
        <p style="color:#6b7280;margin-bottom:16px;">
            Share this shortcode or iframe to display the booking form externally.
        </p>
        <div style="margin-bottom:20px;">
            <label style="font-weight:600;display:block;margin-bottom:8px;">WordPress Shortcode</label>
            <textarea readonly onclick="this.select()"
                      style="width:100%;height:56px;font-family:monospace;padding:10px;
                             background:#f9fafb;border:1px solid #d1d5db;border-radius:4px;">[hb_booking_form_embed]</textarea>
            <button onclick="navigator.clipboard.writeText('[hb_booking_form_embed]');alert('Copied!');"
                    class="bntm-btn-secondary" style="margin-top:8px;">Copy Shortcode</button>
        </div>
        <?php
        $booking_page = get_page_by_path('booking-form-embed');
        $booking_url  = $booking_page ? get_permalink($booking_page->ID) : '';
        ?>
        <div>
            <label style="font-weight:600;display:block;margin-bottom:8px;">iframe Embed Code</label>
            <textarea readonly onclick="this.select()"
                      style="width:100%;height:72px;font-family:monospace;padding:10px;
                             background:#f9fafb;border:1px solid #d1d5db;border-radius:4px;"><?php
                echo esc_html('<iframe src="' . $booking_url . '" width="100%" height="800" frameborder="0"></iframe>');
            ?></textarea>
            <button onclick="navigator.clipboard.writeText(this.previousElementSibling.value);alert('Copied!');"
                    class="bntm-btn-secondary" style="margin-top:8px;">Copy Code</button>
        </div>
    </div>

    <script>
    (function(){
        const nonce = <?php echo wp_json_encode($nonce); ?>;

        // ── Tax form ──────────────────────────────────────────────────────────
        document.getElementById('hbTaxForm').addEventListener('submit', function(e){
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true; btn.textContent = 'Saving…';

            const fd = new FormData(this);
            fd.append('action', 'hb_save_tax_rate');
            fd.append('nonce',  nonce);

            fetch(ajaxurl, {method:'POST', body:fd})
                .then(r=>r.json())
                .then(j=>{
                    document.getElementById('hb-tax-msg').innerHTML =
                        '<div class="bntm-notice bntm-notice-'+(j.success?'success':'error')+'">'+
                        j.data.message+'</div>';
                    btn.disabled = false; btn.textContent = 'Save Tax Rate';
                });
        });

        // ── Payment settings form ─────────────────────────────────────────────
        document.getElementById('hbPaySettingsForm').addEventListener('submit', function(e){
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true; btn.textContent = 'Saving…';

            const fd = new FormData(this);
            fd.append('action', 'hb_save_payment_settings');
            fd.append('nonce',  nonce);

            fetch(ajaxurl, {method:'POST', body:fd})
                .then(r=>r.json())
                .then(j=>{
                    document.getElementById('hb-pay-settings-msg').innerHTML =
                        '<div class="bntm-notice bntm-notice-'+(j.success?'success':'error')+'">'+
                        j.data.message+'</div>';
                    btn.disabled = false; btn.textContent = 'Save Payment Settings';
                });
        });

        // ── Add payment method ────────────────────────────────────────────────
        document.getElementById('hbAddMethodForm').addEventListener('submit', function(e){
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true; btn.textContent = 'Adding…';

            const fd = new FormData(this);
            fd.append('action', 'hb_save_payment_method');
            fd.append('nonce',  nonce);

            fetch(ajaxurl, {method:'POST', body:fd})
                .then(r=>r.json())
                .then(j=>{
                    document.getElementById('hb-method-msg').innerHTML =
                        '<div class="bntm-notice bntm-notice-'+(j.success?'success':'error')+'">'+
                        j.data.message+'</div>';
                    if(j.success) setTimeout(()=>location.reload(), 1000);
                    else { btn.disabled = false; btn.textContent = 'Add Payment Method'; }
                });
        });

        // ── Remove payment method ─────────────────────────────────────────────
        document.querySelectorAll('.hb-remove-method').forEach(btn => {
            btn.addEventListener('click', function(){
                if(!confirm('Remove this payment method?')) return;
                const fd = new FormData();
                fd.append('action', 'hb_remove_payment_method');
                fd.append('index',  this.dataset.index);
                fd.append('nonce',  this.dataset.nonce);
                fetch(ajaxurl, {method:'POST', body:fd})
                    .then(r=>r.json())
                    .then(j=>{
                        if(j.success) location.reload();
                        else alert(j.data.message);
                    });
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ============================================================================
// PUBLIC SHORTCODES
// ============================================================================

function bntm_shortcode_hb_browse() {
    global $wpdb;
    $properties_table = $wpdb->prefix . 'hb_properties';
    
    $properties = $wpdb->get_results("
        SELECT * FROM {$properties_table}
        WHERE status = 'active'
        ORDER BY sort_order ASC, name ASC
    ");
    
    $booking_page_url = get_permalink(get_page_by_path('booking-form'));
    
    ob_start();
    ?>
    <style>
    .catalog-container {max-width: 1200px; margin: 0 auto; padding: 20px;}
    .catalog-grid {display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;}
    .property-card {background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;}
    .property-card:hover {transform: translateY(-4px); box-shadow: 0 4px 12px rgba(0,0,0,0.15);}
    .property-image {width: 100%; height: 200px; object-fit: cover; background: #f3f4f6;}
    .property-details {padding: 20px;}
    .property-name {font-size: 18px; font-weight: bold; margin-bottom: 8px;}
    .property-type {color: #6b7280; font-size: 14px; margin-bottom: 10px;}
    .property-price {font-size: 24px; font-weight: bold; color: var(--bntm-primary); margin-bottom: 10px;}
    .property-info {display: flex; gap: 15px; font-size: 14px; color: #6b7280; margin-top: 10px;}
    .book-btn {display: inline-block; padding: 10px 20px; background: var(--bntm-primary); color: white; text-decoration: none; border-radius: 4px; font-weight: 600; margin-top: 15px;}
    .book-btn:hover {opacity: 0.9;}
    </style>
    
    <div class="catalog-container">
        <h2 style="text-align: center;">Available Rooms & Villas</h2>
        <div class="catalog-grid">
            <?php if (empty($properties)): ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #6b7280;">
                    <p>No properties available at this time</p>
                </div>
            <?php else: foreach ($properties as $prop): 
                $images = !empty($prop->images) ? json_decode($prop->images, true) : [];
                $first_image = !empty($images) ? $images[0] : '';
                $amenities = !empty($prop->amenities) ? explode("\n", trim($prop->amenities)) : [];
            ?>
                <div class="property-card">
                    <?php if ($first_image): ?>
                        <img src="<?php echo esc_url($first_image); ?>" class="property-image" alt="<?php echo esc_attr($prop->name); ?>">
                    <?php else: ?>
                        <div class="property-image" style="display: flex; align-items: center; justify-content: center; font-size: 48px;">🏨</div>
                    <?php endif; ?>
                    
                    <div class="property-details">
                        <div class="property-name"><?php echo esc_html($prop->name); ?></div>
                        <div class="property-type"><?php echo ucfirst($prop->type); ?></div>
                        <?php if ($prop->short_description): ?>
                            <p style="margin: 0 0 10px 0; font-size: 14px; color: #6b7280;"><?php echo esc_html($prop->short_description); ?></p>
                        <?php endif; ?>
                        <div class="property-price">₱<?php echo number_format($prop->base_price, 2); ?>/night</div>
                        <div class="property-info">
                            <span>Up to <?php echo $prop->capacity; ?> guests</span>
                        </div>
                        <?php if (!empty($amenities)): ?>
                            <div style="margin-top: 10px; display: flex; flex-wrap: wrap; gap: 6px;">
                                <?php foreach (array_slice($amenities, 0, 3) as $amenity): ?>
                                    <span style="padding: 4px 10px; background: #f3f4f6; border-radius: 4px; font-size: 12px;">
                                        <?php echo esc_html(trim($amenity)); ?>
                                    </span>
                                <?php endforeach; ?>
                                <?php if (count($amenities) > 3): ?>
                                    <span style="padding: 4px 10px; background: #f3f4f6; border-radius: 4px; font-size: 12px;">
                                        +<?php echo count($amenities) - 3; ?> more
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <a href="<?php echo $booking_page_url . '?property=' . $prop->rand_id; ?>" class="book-btn">Book Now</a>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}


function bntm_shortcode_hb_booking_form() {
    global $wpdb;
    $pt   = $wpdb->prefix.'hb_properties';
    $rows = $wpdb->get_results("SELECT * FROM $pt WHERE status='active' ORDER BY sort_order,name");
    if (empty($rows)) return '<p style="text-align:center;padding:40px;color:#9ca3af;">No rooms available.</p>';
    $tax   = floatval(bntm_get_setting('hb_tax_rate','12.00'));
    $nonce = wp_create_nonce('hb_form_nonce');
    $js_props = wp_json_encode(array_values($rows));

    ob_start(); ?>
<script>var ajaxurl='<?php echo esc_js(admin_url('admin-ajax.php')); ?>';</script>
<style>
/* ---- shell ---- */
.hbf{max-width:940px;margin:0 auto;padding:20px;font-family:inherit;}
.hbf h2{text-align:center;font-size:26px;font-weight:800;margin:0 0 6px;}
.hbf>p{text-align:center;color:#6b7280;margin:0 0 26px;}
/* progress */
.hbf-prog{display:flex;justify-content:center;align-items:center;margin-bottom:28px;flex-wrap:wrap;gap:0;}
.hbf-ps{display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:#9ca3af;}
.hbf-ps .d{width:28px;height:28px;border-radius:50%;background:#e5e7eb;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;}
.hbf-ps.on .d{background:var(--bntm-primary);color:#fff;}
.hbf-ps.on{color:var(--bntm-primary);}
.hbf-ps.done .d{background:#10b981;color:#fff;}
.hbf-ps.done{color:#10b981;}
.hbf-line{width:36px;height:2px;background:#e5e7eb;margin:0 2px;}
.hbf-line.done{background:#10b981;}
/* steps */
.hbf-step{display:none;}
.hbf-step.on{display:block;}
/* tiles */
.hbf-tiles{display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:18px;}
.hbf-tile{background:#fff;border:2px solid #e5e7eb;border-radius:10px;overflow:hidden;transition:border-color .18s,box-shadow .18s;}
.hbf-tile:hover{border-color:var(--bntm-primary);box-shadow:0 4px 14px rgba(0,0,0,.09);}
.hbf-tile-img{width:100%;height:180px;object-fit:cover;background:#f3f4f6;display:flex;align-items:center;justify-content:center;font-size:48px;}
.hbf-tile-img img{width:100%;height:100%;object-fit:cover;display:block;}
.hbf-tile-body{padding:16px;}
.hbf-tile-name{font-size:16px;font-weight:700;margin:0 0 4px;}
.hbf-tile-type{font-size:12px;color:#6b7280;margin:0 0 8px;}
.hbf-tile-price{font-size:20px;font-weight:700;color:var(--bntm-primary);margin:0 0 10px;}
.hbf-tile-price small{font-size:12px;color:#9ca3af;font-weight:400;}
.hbf-chips{display:flex;flex-wrap:wrap;gap:5px;margin-bottom:12px;}
.hbf-chip{padding:3px 8px;background:#f3f4f6;border-radius:20px;font-size:11px;}
.hbf-tile-btn{width:100%;padding:9px;background:var(--bntm-primary);color:#fff;border:none;border-radius:6px;font-weight:700;font-size:14px;cursor:pointer;transition:opacity .15s;}
.hbf-tile-btn:hover{opacity:.88;}
/* card wrapper for step 2/3/4 */
.hbf-card{max-width:580px;margin:0 auto;background:#fff;padding:26px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.08);}
.hbf-banner{display:flex;align-items:center;gap:12px;padding:12px 14px;background:#f0fdf4;border:1px solid #86efac;border-radius:8px;margin-bottom:20px;}
.hbf-banner-thumb{width:52px;height:52px;border-radius:6px;object-fit:cover;background:#e5e7eb;display:flex;align-items:center;justify-content:center;font-size:26px;flex-shrink:0;}
.hbf-banner-thumb img{width:52px;height:52px;border-radius:6px;object-fit:cover;}
/* availability badge */
.hbf-avail{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:6px;font-size:13px;font-weight:600;margin-bottom:14px;}
.hbf-avail-none{background:#fef2f2;color:#991b1b;border:1px solid #fca5a5;}
.hbf-avail-ok{background:#f0fdf4;color:#166534;border:1px solid #86efac;}
.hbf-avail-checking{background:#fefce8;color:#92400e;border:1px solid #fde68a;}
/* form */
.hbf-fg{margin-bottom:16px;}
.hbf-fg label{display:block;font-weight:600;font-size:13px;color:#374151;margin-bottom:6px;}
.hbf-input{width:100%;padding:11px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;font-family:inherit;}
.hbf-input:focus{outline:none;border-color:var(--bntm-primary);}
.hbf-2c{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
/* price preview (step 4 only) */
.hbf-preview{background:#f9fafb;border-radius:8px;padding:14px;margin:14px 0;}
.hbf-prow{display:flex;justify-content:space-between;font-size:13px;margin-bottom:7px;}
.hbf-prow.big{font-size:17px;font-weight:700;color:var(--bntm-primary);padding-top:9px;border-top:2px solid #e5e7eb;margin-top:6px;}
/* actions */
.hbf-actions{display:flex;gap:12px;margin-top:24px;}
.hbf-btn{padding:11px 22px;border-radius:6px;font-weight:700;font-size:14px;cursor:pointer;border:none;}
.hbf-btn-p{background:var(--bntm-primary);color:#fff;}
.hbf-btn-p:hover:not(:disabled){opacity:.9;}
.hbf-btn-p:disabled{opacity:.5;cursor:not-allowed;}
.hbf-btn-s{background:#f7fafc;color:#374151;border:1px solid #d1d5db;}
.hbf-btn-s:hover{background:#edf2f7;}
/* notices */
.hbf-notice{padding:11px 15px;border-radius:6px;font-size:14px;margin-bottom:14px;}
.hbf-ok{background:#f0fdf4;color:#166534;border:1px solid #86efac;}
.hbf-err{background:#fef2f2;color:#991b1b;border:1px solid #fca5a5;}
/* review grid */
.hbf-rev-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;}
.hbf-rev-item strong{display:block;font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:.4px;margin-bottom:2px;}
@media(max-width:640px){.hbf-2c,.hbf-rev-grid{grid-template-columns:1fr;} .hbf-tiles{grid-template-columns:1fr;}}
</style>

<div class="hbf">
    <h2>Book Your Stay</h2>
    <p>Choose a room and pick your dates</p>

    <!-- PROGRESS -->
    <div class="hbf-prog">
        <div class="hbf-ps on" id="hbprog1"><div class="d">1</div><span>Choose Room</span></div>
        <div class="hbf-line" id="hbline1"></div>
        <div class="hbf-ps" id="hbprog2"><div class="d">2</div><span>Dates</span></div>
        <div class="hbf-line" id="hbline2"></div>
        <div class="hbf-ps" id="hbprog3"><div class="d">3</div><span>Details</span></div>
        <div class="hbf-line" id="hbline3"></div>
        <div class="hbf-ps" id="hbprog4"><div class="d">4</div><span>Confirm</span></div>
    </div>

    <!-- STEP 1: tiles -->
    <div class="hbf-step on" id="hbstep1">
        <div class="hbf-tiles" id="hbf-tiles">
            <?php foreach ($rows as $prop):
                $imgs = !empty($prop->images) ? json_decode($prop->images, true) : [];
                $img  = !empty($imgs) ? $imgs[0] : '';
                $ams  = !empty($prop->amenities) ? array_filter(array_map('trim', explode("\n", $prop->amenities))) : [];
            ?>
            <div class="hbf-tile">
                <div class="hbf-tile-img">
                    <?php if ($img): ?><img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($prop->name); ?>"><?php else: ?>🏨<?php endif; ?>
                </div>
                <div class="hbf-tile-body">
                    <div class="hbf-tile-name"><?php echo esc_html($prop->name); ?></div>
                    <div class="hbf-tile-type"><?php echo ucfirst($prop->type); ?></div>
                    <div class="hbf-tile-price">₱<?php echo number_format($prop->base_price,2); ?><small>/night</small></div>
                    <div class="hbf-chips">
                        <span class="hbf-chip">👥 Up to <?php echo $prop->capacity; ?> guests</span>
                        <?php foreach (array_slice($ams,0,2) as $a): ?><span class="hbf-chip"><?php echo esc_html($a); ?></span><?php endforeach; ?>
                    </div>
                    <?php if ($prop->short_description): ?>
                    <p style="font-size:12px;color:#6b7280;margin:0 0 10px;"><?php echo esc_html($prop->short_description); ?></p>
                    <?php endif; ?>
                    <button type="button" class="hbf-tile-btn" data-idx="<?php echo (int)array_search($prop, $rows); ?>">Select This Room</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- STEP 2: dates only – no qty, no breakdown -->
    <div class="hbf-step" id="hbstep2">
        <div class="hbf-card">
            <!-- Room banner -->
            <div class="hbf-banner" id="hbf-banner">
                <div id="hbf-banner-thumb" class="hbf-banner-thumb">🏨</div>
                <div>
                    <div id="hbf-banner-name" style="font-weight:700;font-size:15px;"></div>
                    <div id="hbf-banner-price" style="color:var(--bntm-primary);font-size:13px;font-weight:600;"></div>
                </div>
            </div>

            <!-- Availability status badge – starts "Not Available" until valid dates chosen -->
            <div id="hbf-avail-badge" class="hbf-avail hbf-avail-none">
                <span id="hbf-avail-dot">✕</span>
                <span id="hbf-avail-txt">Not Available – please select your dates</span>
            </div>

            <div class="hbf-2c">
                <div class="hbf-fg">
                    <label>Check-in *</label>
                    <input type="date" id="hbf-ci" class="hbf-input" min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="hbf-fg">
                    <label>Check-out *</label>
                    <input type="date" id="hbf-co" class="hbf-input" min="<?php echo date('Y-m-d',strtotime('+1 day')); ?>">
                </div>
            </div>

            <div id="hbf-nights-wrap" style="display:none;margin-bottom:14px;">
                <span style="display:inline-block;padding:5px 13px;background:#f0fdf4;border:1px solid #86efac;border-radius:5px;font-weight:700;font-size:13px;color:#166534;" id="hbf-nights-txt"></span>
            </div>

            <div id="hbf-date-err" class="hbf-notice hbf-err" style="display:none;"></div>

            <div class="hbf-actions">
                <button type="button" class="hbf-btn hbf-btn-s" onclick="hbGo(1)">← Back</button>
                <button type="button" class="hbf-btn hbf-btn-p" id="hbf-dates-next" onclick="hbDatesNext()" disabled style="margin-left:auto;">Next →</button>
            </div>
        </div>
    </div>

    <!-- STEP 3: contact -->
    <div class="hbf-step" id="hbstep3">
        <div class="hbf-card">
            <h3 style="margin:0 0 18px;font-size:17px;">Your Contact Details</h3>
            <div class="hbf-2c">
                <div class="hbf-fg"><label>Full Name *</label><input type="text" id="hbf-cn" class="hbf-input" placeholder="Juan Dela Cruz"></div>
                <div class="hbf-fg"><label>Email *</label><input type="email" id="hbf-ce" class="hbf-input" placeholder="juan@email.com"></div>
            </div>
            <div class="hbf-fg"><label>Contact Number *</label><input type="tel" id="hbf-cp" class="hbf-input" placeholder="+63 912 345 6789"></div>
            <div class="hbf-fg"><label>Special Requests <span style="font-weight:400;color:#9ca3af;">(optional)</span></label><textarea id="hbf-cnotes" rows="3" class="hbf-input" placeholder="Early check-in, extra pillows…"></textarea></div>
            <div id="hbf-contact-err" class="hbf-notice hbf-err" style="display:none;"></div>
            <div class="hbf-actions">
                <button type="button" class="hbf-btn hbf-btn-s" onclick="hbGo(2)">← Back</button>
                <button type="button" class="hbf-btn hbf-btn-p" onclick="hbContactNext()" style="margin-left:auto;">Review →</button>
            </div>
        </div>
    </div>

    <!-- STEP 4: review & submit -->
    <div class="hbf-step" id="hbstep4">
        <div class="hbf-card">
            <h3 style="margin:0 0 18px;font-size:17px;">Review Your Booking</h3>

            <div class="hbf-rev-grid">
                <div class="hbf-rev-item"><strong>Room</strong><span id="rev-room">—</span></div>
                <div class="hbf-rev-item"><strong>Check-in</strong><span id="rev-in">—</span></div>
                <div class="hbf-rev-item"><strong>Check-out</strong><span id="rev-out">—</span></div>
                <div class="hbf-rev-item"><strong>Nights</strong><span id="rev-nights">—</span></div>
            </div>
            <div class="hbf-rev-grid" style="margin-bottom:18px;">
                <div class="hbf-rev-item"><strong>Name</strong><span id="rev-name">—</span></div>
                <div class="hbf-rev-item"><strong>Email</strong><span id="rev-email">—</span></div>
                <div class="hbf-rev-item"><strong>Phone</strong><span id="rev-phone">—</span></div>
            </div>
            <div class="hbf-preview">
                <div class="hbf-prow"><span>Rate/Night</span><span id="rev-rate">—</span></div>
                <div class="hbf-prow"><span id="rev-sub-lbl">Subtotal</span><span id="rev-sub">—</span></div>
                <div class="hbf-prow"><span>Tax (<?php echo $tax; ?>%)</span><span id="rev-tax">—</span></div>
                <div class="hbf-prow big"><span>Grand Total</span><span id="rev-tot">—</span></div>
            </div>

            <div id="hbf-submit-msg" style="margin:14px 0;"></div>

            <div class="hbf-actions" id="hbf-submit-actions">
                <button type="button" class="hbf-btn hbf-btn-s" id="hbf-back4" onclick="hbGo(3)">← Back</button>
                <button type="button" class="hbf-btn hbf-btn-p" id="hbf-submit-btn" onclick="hbSubmit()" style="margin-left:auto;">Submit Booking</button>
            </div>

            <!-- shown after success -->
            <div id="hbf-another" style="display:none;text-align:center;margin-top:18px;">
                <p style="color:#6b7280;font-size:14px;margin-bottom:10px;">Want to book another room?</p>
                <button type="button" class="hbf-btn hbf-btn-s" onclick="hbReset()">← Book Another Room</button>
            </div>
        </div>
    </div>
</div><!-- .hbf -->

<script>
(function(){
const PROPS = <?php echo $js_props; ?>;
const TAX   = <?php echo $tax; ?>;
const NONCE = <?php echo wp_json_encode($nonce); ?>;

// ---- state ----
let prop    = null;
let ci      = '';
let co      = '';
let nights  = 0;
let blocked = [];
let availOk = false;   // true when dates have been verified as available
let checkTimer = null; // debounce handle

// ---- helpers ----
const $   = id => document.getElementById(id);
const fmt = n  => '₱' + parseFloat(n).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
const fmtD = s => s ? new Date(s+'T00:00:00').toLocaleDateString('en-US',{month:'long',day:'numeric',year:'numeric'}) : '—';

// ---- progress ----
function hbGo(n){
    [1,2,3,4].forEach(i=>{
        const s=$('hbprog'+i), l=$('hbline'+i);
        s.classList.remove('on','done');
        if(l) l.classList.remove('done');
        if(i < n){ s.classList.add('done'); if(l) l.classList.add('done'); }
        if(i === n) s.classList.add('on');
        $('hbstep'+i).classList.toggle('on', i===n);
    });
    window.scrollTo({top:0,behavior:'smooth'});
}
window.hbGo = hbGo;

// ---- Availability badge helpers ----
function setAvailBadge(state, msg){
    const badge = $('hbf-avail-badge');
    const dot   = $('hbf-avail-dot');
    const txt   = $('hbf-avail-txt');
    badge.className = 'hbf-avail';
    if(state === 'none'){
        badge.classList.add('hbf-avail-none');
        dot.textContent = '✕';
    } else if(state === 'checking'){
        badge.classList.add('hbf-avail-checking');
        dot.textContent = '…';
    } else if(state === 'ok'){
        badge.classList.add('hbf-avail-ok');
        dot.textContent = '✓';
    } else {
        badge.classList.add('hbf-avail-none');
        dot.textContent = '✕';
    }
    txt.textContent = msg;
}

// ---- STEP 1: tile click ----
document.querySelectorAll('.hbf-tile-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
        const idx = parseInt(this.dataset.idx, 10);
        prop = PROPS[idx];
        // Reset dates + state
        ci = ''; co = ''; nights = 0; blocked = []; availOk = false;
        $('hbf-ci').value = '';
        $('hbf-co').value = '';
        $('hbf-nights-wrap').style.display = 'none';
        $('hbf-date-err').style.display    = 'none';
        $('hbf-dates-next').disabled = true;
        setAvailBadge('none', 'Not Available – please select your dates');

        // banner
        $('hbf-banner-name').textContent  = prop.name;
        $('hbf-banner-price').textContent = fmt(prop.base_price) + ' / night';
        let thumb = $('hbf-banner-thumb');
        try {
            const imgs = JSON.parse(prop.images || '[]');
            if(imgs.length){ thumb.innerHTML = '<img src="'+imgs[0]+'" alt="">'; }
            else { thumb.textContent = '🏨'; }
        } catch(e){ thumb.textContent = '🏨'; }

        // Pre-load blocked dates for this property then go to step 2
        fetchBlockedOnly(function(){ hbGo(2); });
    });
});

// Fetch only fully-blocked dates (no date range needed)
function fetchBlockedOnly(cb){
    const fd = new FormData();
    fd.append('action',      'hb_get_blocked_dates');
    fd.append('property_id', prop.id);
    fd.append('nonce',       NONCE);
    fetch(ajaxurl,{method:'POST',body:fd})
        .then(r=>r.json())
        .then(j=>{
            blocked = (j.success && j.data.fully_blocked) ? j.data.fully_blocked : [];
            if(cb) cb();
        })
        .catch(()=>{ blocked=[]; if(cb) cb(); });
}

// ---- date inputs ----
$('hbf-ci').addEventListener('change', function(){
    ci = this.value;
    // update checkout min
    const d = new Date(ci+'T00:00:00'); d.setDate(d.getDate()+1);
    $('hbf-co').min = d.toISOString().split('T')[0];
    if(co && co <= ci){ co=''; $('hbf-co').value=''; }
    triggerAvailCheck();
});

$('hbf-co').addEventListener('change', function(){
    co = this.value;
    triggerAvailCheck();
});

function triggerAvailCheck(){
    // Reset to "not available" immediately when dates change
    availOk = false;
    $('hbf-dates-next').disabled = true;
    $('hbf-nights-wrap').style.display = 'none';
    $('hbf-date-err').style.display    = 'none';

    if(!ci || !co){
        setAvailBadge('none', 'Not Available – please select your dates');
        return;
    }

    const s = new Date(ci+'T00:00:00'), e = new Date(co+'T00:00:00');
    nights = Math.round((e - s) / 86400000);

    if(nights < 1){
        setAvailBadge('none', 'Check-out must be after check-in');
        return;
    }

    // Check locally-blocked dates first
    for(let d=new Date(s); d<e; d.setDate(d.getDate()+1)){
        if(blocked.includes(d.toISOString().split('T')[0])){
            setAvailBadge('none', 'Not Available – dates are fully booked');
            $('hbf-date-err').textContent = 'These dates are fully booked. Please choose different dates.';
            $('hbf-date-err').style.display = 'block';
            return;
        }
    }

    // Show "checking" while we verify with server
    setAvailBadge('checking', 'Checking availability…');

    // Debounce server check
    clearTimeout(checkTimer);
    checkTimer = setTimeout(()=>{ checkAvailServer(); }, 400);
}

function checkAvailServer(){
    const fd = new FormData();
    fd.append('action',      'hb_get_blocked_dates');
    fd.append('property_id', prop.id);
    fd.append('check_in',    ci);
    fd.append('check_out',   co);
    fd.append('nonce',       NONCE);
    fetch(ajaxurl,{method:'POST',body:fd})
        .then(r=>r.json())
        .then(j=>{
            if(!j.success){
                setAvailBadge('none', 'Not Available – could not verify');
                return;
            }
            const avail = parseInt(j.data.available_count);
            if(avail >= 1){
                availOk = true;
                setAvailBadge('ok', 'Available – ' + avail + ' room' + (avail>1?'s':'') + ' for these dates');
                $('hbf-nights-txt').textContent = nights + ' night' + (nights>1?'s':'');
                $('hbf-nights-wrap').style.display = 'block';
                $('hbf-dates-next').disabled = false;
            } else {
                availOk = false;
                setAvailBadge('none', 'Not Available – no rooms left for these dates');
                $('hbf-date-err').textContent = 'No rooms available for the selected dates. Please choose different dates.';
                $('hbf-date-err').style.display = 'block';
            }
        })
        .catch(()=>{
            setAvailBadge('none', 'Not Available – error checking dates');
        });
}

// ---- Step 2 → 3 ----
function hbDatesNext(){
    if(!availOk){ return; }
    hbGo(3);
}
window.hbDatesNext = hbDatesNext;

// ---- Step 3 → 4 ----
function hbContactNext(){
    const name  = $('hbf-cn').value.trim();
    const email = $('hbf-ce').value.trim();
    const phone = $('hbf-cp').value.trim();
    const err   = $('hbf-contact-err');
    if(!name||!email||!phone){ err.textContent='Please fill in Name, Email and Phone.'; err.style.display='block'; return; }
    if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)){ err.textContent='Please enter a valid email address.'; err.style.display='block'; return; }
    err.style.display='none';
    populateReview();
    hbGo(4);
}
window.hbContactNext = hbContactNext;

function populateReview(){
    const rate = parseFloat(prop.base_price);
    const sub  = rate * nights;
    const tax  = sub * TAX / 100;
    const tot  = sub + tax;
    $('rev-room').textContent    = prop.name;
    $('rev-in').textContent      = fmtD(ci);
    $('rev-out').textContent     = fmtD(co);
    $('rev-nights').textContent  = nights + ' night' + (nights>1?'s':'');
    $('rev-name').textContent    = $('hbf-cn').value.trim();
    $('rev-email').textContent   = $('hbf-ce').value.trim();
    $('rev-phone').textContent   = $('hbf-cp').value.trim();
    $('rev-rate').textContent    = fmt(rate);
    $('rev-sub-lbl').textContent = '1 room × ' + nights + ' night' + (nights>1?'s':'');
    $('rev-sub').textContent     = fmt(sub);
    $('rev-tax').textContent     = fmt(tax);
    $('rev-tot').textContent     = fmt(tot);
}

// ---- Submit – always 1 room ----
function hbSubmit(){
    const btn = $('hbf-submit-btn');
    const msg = $('hbf-submit-msg');
    btn.disabled = true; btn.textContent = 'Submitting…';
    msg.innerHTML = '';

    const rate   = parseFloat(prop.base_price);
    const sub    = rate * nights;
    const taxAmt = sub * TAX / 100;
    const tot    = sub + taxAmt;

    const fd = new FormData();
    fd.append('action',         'hb_submit_booking');
    fd.append('nonce',          NONCE);
    fd.append('property_id',    prop.id);
    fd.append('property_name',  prop.name);
    fd.append('business_id',    prop.business_id);
    fd.append('check_in',       ci);
    fd.append('check_out',      co);
    fd.append('num_nights',     nights);
    fd.append('num_rooms',      1);           // always 1 – no quantity selector
    fd.append('subtotal',       sub.toFixed(2));
    fd.append('tax_amount',     taxAmt.toFixed(2));
    fd.append('grand_total',    tot.toFixed(2));
    fd.append('customer_name',  $('hbf-cn').value.trim());
    fd.append('customer_email', $('hbf-ce').value.trim());
    fd.append('customer_phone', $('hbf-cp').value.trim());
    fd.append('notes',          $('hbf-cnotes').value.trim());

    fetch(ajaxurl,{method:'POST',body:fd})
        .then(r=>r.json())
        .then(j=>{
            if(j.success){
                msg.innerHTML='<div class="hbf-notice hbf-ok">'+j.data.message+'</div>';
                $('hbf-submit-btn').style.display='none';
                $('hbf-back4').style.display='none';
                $('hbf-another').style.display='block';
                //if(j.data.quotation_url) setTimeout(()=>{ window.location.href=j.data.quotation_url; },2000);
            } else {
                msg.innerHTML='<div class="hbf-notice hbf-err">'+j.data.message+'</div>';
                btn.disabled=false; btn.textContent='Submit Booking';
            }
        })
        .catch(()=>{
            msg.innerHTML='<div class="hbf-notice hbf-err">An error occurred. Please try again.</div>';
            btn.disabled=false; btn.textContent='Submit Booking';
        });
}
window.hbSubmit = hbSubmit;

// ---- Reset ----
function hbReset(){
    prop=null; ci=''; co=''; nights=0; blocked=[]; availOk=false;
    $('hbf-cn').value=''; $('hbf-ce').value=''; $('hbf-cp').value=''; $('hbf-cnotes').value='';
    $('hbf-submit-btn').style.display='';
    $('hbf-submit-btn').disabled=false;
    $('hbf-submit-btn').textContent='Submit Booking';
    $('hbf-back4').style.display='';
    $('hbf-another').style.display='none';
    $('hbf-submit-msg').innerHTML='';
    hbGo(1);
}
window.hbReset = hbReset;

})();
</script>
<?php
    return ob_get_clean();
}

function bntm_shortcode_hb_booking_form_embed() {
    if (!defined('IFRAME_REQUEST')) define('IFRAME_REQUEST', true);
    return bntm_shortcode_hb_booking_form();
}

function bntm_shortcode_hb_view_quotation() {
    global $wpdb;
    $bookings_table   = $wpdb->prefix . 'hb_bookings';
    $properties_table = $wpdb->prefix . 'hb_properties';
    $addons_table     = $wpdb->prefix . 'hb_booking_addons';

    $quotation_number = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
    if (empty($quotation_number)) {
        return '<div class="bntm-container"><p>Invalid quotation link.</p></div>';
    }

    $booking = $wpdb->get_row($wpdb->prepare("
        SELECT b.*, p.name as property_name, p.type as property_type,
               p.description as property_description, p.amenities, p.images
        FROM {$bookings_table} b
        LEFT JOIN {$properties_table} p ON b.property_id = p.id
        WHERE b.quotation_number = %s
    ", $quotation_number));

    if (!$booking) {
        return '<div class="bntm-container"><p>Quotation not found.</p></div>';
    }

    // ── Add-ons ───────────────────────────────────────────────────────────────
    $addons = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$addons_table} WHERE booking_id = %d ORDER BY id ASC",
        $booking->id
    ));
    $addons_total = 0.00;
    foreach ($addons as $a) $addons_total += floatval($a->subtotal);

    // ── Pricing ───────────────────────────────────────────────────────────────
    $rate_per_night  = $booking->num_nights > 0
                       ? floatval($booking->subtotal) / $booking->num_nights
                       : floatval($booking->subtotal);
    $rooms_subtotal  = floatval($booking->subtotal);
    $taxable         = $rooms_subtotal + $addons_total;
    $tax_amount      = floatval($booking->tax_amount);
    $grand_total     = floatval($booking->grand_total);

    // ── Down payment / balance ────────────────────────────────────────────────
    $downpayment_pct    = intval(bntm_get_setting('hb_downpayment_percentage', '0'));
    $downpayment_amount = $downpayment_pct > 0 ? round($grand_total * $downpayment_pct / 100, 2) : 0;
    $balance            = $grand_total - $downpayment_amount;

    // ── Payment methods & terms ───────────────────────────────────────────────
    $payment_methods = json_decode(bntm_get_setting('hb_payment_methods', '[]'), true);
    if (!is_array($payment_methods)) $payment_methods = [];
    $terms = bntm_get_setting('hb_terms', '');

    // ── Site info ─────────────────────────────────────────────────────────────
    $logo       = bntm_get_site_logo();
    $site_title = bntm_get_site_title();

    // ── Images & amenities ────────────────────────────────────────────────────
    $images    = !empty($booking->images)
                 ? json_decode($booking->images, true) : [];
    $amenities = !empty($booking->amenities)
                 ? array_filter(array_map('trim', explode("\n", $booking->amenities))) : [];

    // ── Status label ──────────────────────────────────────────────────────────
    $status_labels = [
        'quoted'          => 'Quotation Sent',
        'payment_pending' => 'Payment Pending',
        'confirmed'       => 'Confirmed',
        'cancelled'       => 'Cancelled',
        'completed'       => 'Completed',
    ];
    $status_label = $status_labels[$booking->status] ?? ucfirst($booking->status);

    ob_start();
    ?>
    <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    .hbq-wrap { max-width: 800px; margin: 0 auto; padding: 40px 20px; background: white; font-family: Arial, sans-serif; font-size: 11pt; line-height: 1.4; color: #000; }
    .hbq-header { display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 15px; margin-bottom: 20px; border-bottom: 2px solid #000; }
    .hbq-company-name { font-size: 16pt; font-weight: bold; margin-bottom: 5px; }
    .hbq-title { font-size: 22pt; font-weight: bold; text-align: right; }
    .hbq-number { font-size: 14pt; text-align: right; margin-top: 5px; }
    .hbq-info { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 20px; }
    .hbq-info-label { font-weight: bold; font-size: 9pt; text-transform: uppercase; margin-bottom: 5px; }
    .hbq-customer-name { font-weight: bold; font-size: 11pt; margin-bottom: 3px; }
    .hbq-info-table { width: 100%; font-size: 10pt; }
    .hbq-info-table td { padding: 3px 0; }
    .hbq-wrap table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
    .hbq-wrap th { text-align: left; font-weight: bold; padding: 8px 5px; border-bottom: 2px solid #000; font-size: 9pt; text-transform: uppercase; }
    .hbq-wrap td { padding: 8px 5px; border-bottom: 1px solid #ddd; font-size: 10pt; vertical-align: top; }
    .hbq-wrap tbody tr:last-child td { border-bottom: 1px solid #000; }
    .hbq-wrap tfoot td { padding: 6px 5px; border-bottom: none; }
    .hbq-total-row td { padding: 10px 5px; border-top: 2px solid #000 !important; border-bottom: 2px solid #000 !important; }
    .hbq-text-right { text-align: right; }
    .hbq-box { margin: 15px 0; padding: 12px; border: 1px solid #000; }
    .hbq-box-title { font-weight: bold; font-size: 9pt; text-transform: uppercase; margin-bottom: 8px; padding-bottom: 5px; border-bottom: 1px solid #ddd; }
    .hbq-box p { margin: 5px 0; font-size: 10pt; }
    .hbq-prop-img { width: 100%; max-height: 220px; object-fit: cover; border-radius: 4px; margin-bottom: 16px; }
    .hbq-status { display: inline-block; padding: 4px 12px; border-radius: 4px; font-size: 10pt; font-weight: bold; border: 1px solid #000; }
    .hbq-footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #000; text-align: center; font-size: 10pt; }
    .hbq-footer p { margin: 5px 0; }
    .hbq-chip { display: inline-block; padding: 3px 8px; border: 1px solid #ccc; border-radius: 3px; font-size: 9pt; margin: 2px 3px 2px 0; }
    .hbq-pay-method { padding: 10px; background: #f9fafb; border-radius: 4px; margin-bottom: 8px; }
    @media print {
        .hbq-wrap { padding: 20px; }
        .no-print { display: none !important; }
    }
    @media screen and (max-width: 768px) {
        .hbq-header { flex-direction: column; gap: 15px; }
        .hbq-title, .hbq-number { text-align: left; }
        .hbq-info { grid-template-columns: 1fr; gap: 15px; }
    }
    </style>

    <div class="hbq-wrap">

        <!-- Print button -->
        <div style="text-align:center;margin-bottom:20px;" class="no-print">
            <button onclick="window.print()"
                    style="padding:8px 22px;border:1px solid #d1d5db;background:#f9fafb;
                           border-radius:4px;font-size:13px;cursor:pointer;font-weight:600;">
                🖨 Print Quotation
            </button>
        </div>

        <!-- Property image -->
        <?php if (!empty($images) && !empty($images[0])): ?>
        <img src="<?php echo esc_url($images[0]); ?>"
             alt="<?php echo esc_attr($booking->property_name); ?>"
             class="hbq-prop-img">
        <?php endif; ?>

        <!-- Header -->
        <div class="hbq-header">
            <div>
                <?php if ($logo): ?>
                    <img src="<?php echo esc_url($logo); ?>" alt="Logo"
                         style="max-width:120px;max-height:50px;margin-bottom:10px;display:block;">
                <?php endif; ?>
                <div class="hbq-company-name"><?php echo esc_html($site_title ?: 'Hotel Booking'); ?></div>
            </div>
            <div>
                <div class="hbq-title">BOOKING QUOTATION</div>
                <div class="hbq-number">#<?php echo esc_html($booking->quotation_number); ?></div>
            </div>
        </div>

        <!-- Customer + booking meta -->
        <div class="hbq-info">
            <div>
                <div class="hbq-info-label">Customer Details</div>
                <div class="hbq-customer-name"><?php echo esc_html($booking->customer_name); ?></div>
                <div><?php echo esc_html($booking->customer_email); ?></div>
                <div><?php echo esc_html($booking->customer_phone); ?></div>
            </div>
            <div>
                <table class="hbq-info-table">
                    <tr>
                        <td><strong>Check-in:</strong></td>
                        <td><?php echo date('M d, Y', strtotime($booking->check_in)); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Check-out:</strong></td>
                        <td><?php echo date('M d, Y', strtotime($booking->check_out)); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Nights:</strong></td>
                        <td><?php echo $booking->num_nights; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Guests:</strong></td>
                        <td><?php echo $booking->num_guests; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Issued:</strong></td>
                        <td><?php echo date('M d, Y', strtotime($booking->created_at)); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Status:</strong></td>
                        <td><span class="hbq-status"><?php echo esc_html($status_label); ?></span></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Line-items table -->
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="hbq-text-right">Qty</th>
                    <th class="hbq-text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <!-- Room row -->
                <tr>
                    <td>
                        <strong><?php echo esc_html($booking->property_name); ?></strong><br>
                        <span style="font-size:9pt;color:#666;">
                            <?php echo ucfirst($booking->property_type); ?> &mdash;
                            ₱<?php echo number_format($rate_per_night, 2); ?> / night
                        </span>
                        <?php if (!empty($amenities)): ?>
                        <div style="margin-top:6px;">
                            <?php foreach ($amenities as $am): ?>
                                <span class="hbq-chip"><?php echo esc_html($am); ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="hbq-text-right">
                        <?php echo $booking->num_nights; ?> night<?php echo $booking->num_nights > 1 ? 's' : ''; ?>
                    </td>
                    <td class="hbq-text-right">₱<?php echo number_format($rooms_subtotal, 2); ?></td>
                </tr>

                <!-- Add-on rows -->
                <?php foreach ($addons as $addon): ?>
                <tr>
                    <td>
                        <?php echo esc_html($addon->description); ?>
                        <?php if ($addon->quantity > 1): ?>
                            <span style="font-size:9pt;color:#666;">
                                (₱<?php echo number_format($addon->price, 2); ?> × <?php echo $addon->quantity; ?>)
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="hbq-text-right"><?php echo $addon->quantity; ?></td>
                    <td class="hbq-text-right">₱<?php echo number_format($addon->subtotal, 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>

            <tfoot>
                <!-- Subtotals only when add-ons exist -->
                <?php if (!empty($addons)): ?>
                <tr>
                    <td colspan="2" class="hbq-text-right">Room Subtotal</td>
                    <td class="hbq-text-right">₱<?php echo number_format($rooms_subtotal, 2); ?></td>
                </tr>
                <tr>
                    <td colspan="2" class="hbq-text-right">Add-ons Subtotal</td>
                    <td class="hbq-text-right">₱<?php echo number_format($addons_total, 2); ?></td>
                </tr>
                <tr>
                    <td colspan="2" class="hbq-text-right">Taxable Amount</td>
                    <td class="hbq-text-right">₱<?php echo number_format($taxable, 2); ?></td>
                </tr>
                <?php endif; ?>

                <!-- Tax -->
                <tr>
                    <td colspan="2" class="hbq-text-right">
                        Tax (<?php echo number_format($booking->tax_rate, 2); ?>%)
                    </td>
                    <td class="hbq-text-right">₱<?php echo number_format($tax_amount, 2); ?></td>
                </tr>

                <!-- Grand total -->
                <tr>
                    <td colspan="2" class="hbq-text-right"><strong>Grand Total</strong></td>
                    <td class="hbq-text-right"><strong>₱<?php echo number_format($grand_total, 2); ?></strong></td>
                </tr>

                <?php if ($downpayment_pct > 0): ?>
                <!-- Down payment -->
                <tr style="background:#f9fafb;">
                    <td colspan="2" class="hbq-text-right">
                        <strong>Down Payment (<?php echo $downpayment_pct; ?>%)</strong><br>
                        <small style="font-weight:normal;color:#666;">Required to confirm booking</small>
                    </td>
                    <td class="hbq-text-right">
                        <strong>₱<?php echo number_format($downpayment_amount, 2); ?></strong>
                    </td>
                </tr>
                <!-- Remaining balance -->
                <tr style="background:#fff3cd;">
                    <td colspan="2" class="hbq-text-right">
                        <strong>Remaining Balance</strong><br>
                        <small style="font-weight:normal;color:#666;">Due upon check-in</small>
                    </td>
                    <td class="hbq-text-right">
                        <strong style="color:#dc2626;">₱<?php echo number_format($balance, 2); ?></strong>
                    </td>
                </tr>
                <?php endif; ?>

                <!-- Total amount due -->
                <tr class="hbq-total-row">
                    <td colspan="2" class="hbq-text-right">
                        <strong>TOTAL AMOUNT DUE</strong>
                    </td>
                    <td class="hbq-text-right">
                        <strong>₱<?php echo number_format($downpayment_pct > 0 ? $downpayment_amount : $grand_total, 2); ?></strong>
                    </td>
                </tr>
            </tfoot>
        </table>

        <!-- Notes -->
        <?php if (!empty($booking->notes)): ?>
        <div class="hbq-box">
            <div class="hbq-box-title">Special Requests / Notes</div>
            <p><?php echo nl2br(esc_html($booking->notes)); ?></p>
        </div>
        <?php endif; ?>

        <!-- Payment methods -->
        <?php if (!empty($payment_methods)): ?>
        <div class="hbq-box">
            <div class="hbq-box-title">Payment Methods</div>
            <?php foreach ($payment_methods as $method): ?>
            <div class="hbq-pay-method">
                <strong><?php echo esc_html($method['name']); ?></strong>
                <?php if (!empty($method['account_name'])): ?>
                    <br><span style="font-size:9pt;">
                        Account Name: <?php echo esc_html($method['account_name']); ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($method['account_number'])): ?>
                    <br><span style="font-size:9pt;">
                        Account Number: <?php echo esc_html($method['account_number']); ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($method['description'])): ?>
                    <br><span style="font-size:9pt;color:#666;">
                        <?php echo esc_html($method['description']); ?>
                    </span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Admin notes — hidden from print -->
        <?php if (!empty($booking->admin_notes)): ?>
        <div class="hbq-box no-print">
            <div class="hbq-box-title">Admin Notes</div>
            <p><?php echo nl2br(esc_html($booking->admin_notes)); ?></p>
        </div>
        <?php endif; ?>

        <!-- Terms & conditions -->
        <?php if (!empty($terms)): ?>
        <div class="hbq-box">
            <div class="hbq-box-title">Terms &amp; Conditions</div>
            <p style="white-space:pre-line;font-size:9pt;">
                <?php echo nl2br(esc_html($terms)); ?>
            </p>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="hbq-footer">
            <p>Thank you for choosing our services!</p>
            <?php if ($site_title): ?>
                <p><?php echo esc_html($site_title); ?></p>
            <?php endif; ?>
        </div>

    </div><!-- .hbq-wrap -->
    <?php
    return ob_get_clean();
}

// ============================================================================
// AJAX HANDLERS
// ============================================================================

function bntm_ajax_hb_add_property() {
    check_ajax_referer('hb_properties_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'hb_properties';
    
    $images_raw = isset($_POST['images']) ? sanitize_textarea_field($_POST['images']) : '';
    $images = array_filter(array_map('trim', explode("\n", $images_raw)));
    
    $data = [
        'rand_id' => bntm_rand_id(),
        'business_id' => get_current_user_id(),
        'name' => sanitize_text_field($_POST['name']),
        'type' => sanitize_text_field($_POST['type']),
        'short_description' => sanitize_text_field($_POST['short_description']),
        'description' => sanitize_textarea_field($_POST['description']),
        'capacity' => intval($_POST['capacity']),
        'base_price' => floatval($_POST['base_price']),
        'status' => sanitize_text_field($_POST['status']),
        'amenities' => sanitize_textarea_field($_POST['amenities']),
        'images' => json_encode($images)
    ];
    
    $result = $wpdb->insert($table, $data);
    
    if ($result) {
        wp_send_json_success(['message' => 'Property added successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to add property.']);
    }
}

function bntm_ajax_hb_update_property() {
    check_ajax_referer('hb_properties_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'hb_properties';
    
    $property_id = intval($_POST['property_id']);
    $images_raw = isset($_POST['images']) ? sanitize_textarea_field($_POST['images']) : '';
    $images = array_filter(array_map('trim', explode("\n", $images_raw)));
    
    $data = [
        'name' => sanitize_text_field($_POST['name']),
        'type' => sanitize_text_field($_POST['type']),
        'short_description' => sanitize_text_field($_POST['short_description']),
        'description' => sanitize_textarea_field($_POST['description']),
        'capacity' => intval($_POST['capacity']),
        'base_price' => floatval($_POST['base_price']),
        'status' => sanitize_text_field($_POST['status']),
        'amenities' => sanitize_textarea_field($_POST['amenities']),
        'images' => json_encode($images)
    ];
    
    $result = $wpdb->update($table, $data, ['id' => $property_id]);
    
    if ($result !== false) {
        wp_send_json_success(['message' => 'Property updated successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to update property.']);
    }
}

function bntm_ajax_hb_delete_property() {
    check_ajax_referer('hb_properties_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'hb_properties';
    $property_id = intval($_POST['property_id']);
    
    $result = $wpdb->delete($table, ['id' => $property_id]);
    
    if ($result) {
        wp_send_json_success(['message' => 'Property deleted successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete property']);
    }
}

function bntm_ajax_upload_hb_attachment() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    if (empty($_FILES['file'])) {
        wp_send_json_error(['message' => 'No file provided']);
    }
    
    $file = $_FILES['file'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    if (!in_array($file['type'], $allowed_types)) {
        wp_send_json_error(['message' => 'Invalid file type.']);
    }
    
    if ($file['size'] > 5 * 1024 * 1024) {
        wp_send_json_error(['message' => 'File too large (max 5MB).']);
    }
    
    $upload = wp_handle_upload($file, ['test_form' => false]);
    
    if (isset($upload['error'])) {
        wp_send_json_error(['message' => $upload['error']]);
    }
    
    wp_send_json_success(['url' => $upload['url']]);
}

function bntm_ajax_hb_update_booking_status() {
    check_ajax_referer('hb_bookings_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'hb_bookings';
    
    $booking_id = intval($_POST['booking_id']);
    $status = sanitize_text_field($_POST['status']);
    
    $result = $wpdb->update($table, ['status' => $status], ['id' => $booking_id]);
    
    if ($result !== false) {
        wp_send_json_success(['message' => 'Status updated!']);
    } else {
        wp_send_json_error(['message' => 'Failed to update status']);
    }
}
function bntm_ajax_hb_update_booking() {
    check_ajax_referer('hb_bookings_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $table         = $wpdb->prefix . 'hb_bookings';
    $addons_table  = $wpdb->prefix . 'hb_booking_addons';
    $booking_id    = intval($_POST['booking_id']);

    // ── Dates & nights ────────────────────────────────────────────────────────
    $check_in  = sanitize_text_field($_POST['check_in']);
    $check_out = sanitize_text_field($_POST['check_out']);
    $ci_dt     = new DateTime($check_in);
    $co_dt     = new DateTime($check_out);
    $nights    = max(1, (int) $ci_dt->diff($co_dt)->days);

    // ── Pricing ───────────────────────────────────────────────────────────────
    $subtotal_base = floatval($_POST['subtotal']);
    $tax_rate      = floatval($_POST['tax_rate']);

    // ── Save add-ons first so we can include them in totals ───────────────────
    $addons_total = 0.00;

    if (!empty($_POST['save_addons'])) {
        $wpdb->delete($addons_table, ['booking_id' => $booking_id]);

        $addons = isset($_POST['addons']) ? (array) $_POST['addons'] : [];

        foreach ($addons as $addon) {
            $desc         = sanitize_text_field($addon['description'] ?? '');
            $addon_price  = floatval($addon['price']    ?? 0);
            $qty          = max(1, intval($addon['quantity'] ?? 1));
            $addon_sub    = round($addon_price * $qty, 2);

            if (empty($desc)) continue;

            $wpdb->insert($addons_table, [
                'booking_id'  => $booking_id,
                'description' => $desc,
                'price'       => $addon_price,
                'quantity'    => $qty,
                'subtotal'    => $addon_sub,
            ]);

            $addons_total += $addon_sub;
        }
    } else {
        // Add-ons not being updated — pull existing total from DB
        $addons_total = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(subtotal), 0) FROM $addons_table WHERE booking_id = %d",
            $booking_id
        ));
    }

    // ── Recalculate totals including add-ons ──────────────────────────────────
    $taxable     = $subtotal_base + $addons_total;
    $tax_amount  = round($taxable * $tax_rate / 100, 2);
    $grand_total = round($taxable + $tax_amount, 2);

    // ── Build update data ─────────────────────────────────────────────────────
    $data = [
        'customer_name'  => sanitize_text_field($_POST['customer_name']),
        'customer_email' => sanitize_email($_POST['customer_email']),
        'customer_phone' => sanitize_text_field($_POST['customer_phone']),
        'check_in'       => $check_in,
        'check_out'      => $check_out,
        'num_nights'     => $nights,
        'num_guests'     => intval($_POST['num_guests']),
        'subtotal'       => $subtotal_base,
        'tax_rate'       => $tax_rate,
        'tax_amount'     => $tax_amount,
        'grand_total'    => $grand_total,
        'status'         => sanitize_text_field($_POST['status']),
        'payment_status' => sanitize_text_field($_POST['payment_status']),
        'notes'          => sanitize_textarea_field($_POST['notes']),
        'admin_notes'    => sanitize_textarea_field($_POST['admin_notes']),
    ];

    $result = $wpdb->update($table, $data, ['id' => $booking_id]);

    if ($result !== false) {
        wp_send_json_success([
            'message'      => 'Booking updated successfully!',
            'nights'       => $nights,
            'subtotal'     => $subtotal_base,
            'addons_total' => $addons_total,
            'tax_amount'   => $tax_amount,
            'grand_total'  => $grand_total,
        ]);
    } else {
        wp_send_json_error(['message' => 'Failed to update booking: ' . $wpdb->last_error]);
    }
}
function bntm_ajax_hb_get_addons() {
    check_ajax_referer('hb_bookings_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $table      = $wpdb->prefix . 'hb_booking_addons';
    $booking_id = intval($_POST['booking_id']);

    $addons = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE booking_id = %d ORDER BY id ASC",
        $booking_id
    ));

    wp_send_json_success(['addons' => $addons]);
}

function bntm_ajax_hb_save_addons() {
    check_ajax_referer('hb_bookings_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $addons_table  = $wpdb->prefix . 'hb_booking_addons';
    $bookings_table = $wpdb->prefix . 'hb_bookings';
    $booking_id    = intval($_POST['booking_id']);

    // Delete existing addons for this booking then re-insert
    $wpdb->delete($addons_table, ['booking_id' => $booking_id]);

    $addons      = isset($_POST['addons']) ? (array)$_POST['addons'] : [];
    $addons_total = 0.00;

    foreach ($addons as $addon) {
        $desc     = sanitize_text_field($addon['description'] ?? '');
        $price    = floatval($addon['price']    ?? 0);
        $qty      = max(1, intval($addon['quantity'] ?? 1));
        $subtotal = round($price * $qty, 2);

        if (empty($desc)) continue;

        $wpdb->insert($addons_table, [
            'booking_id'  => $booking_id,
            'description' => $desc,
            'price'       => $price,
            'quantity'    => $qty,
            'subtotal'    => $subtotal,
        ]);

        $addons_total += $subtotal;
    }

    // Pull current booking to recalculate grand total
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $bookings_table WHERE id = %d", $booking_id
    ));

    if ($booking) {
        $new_subtotal   = floatval($booking->subtotal) + $addons_total;
        $new_tax        = round($new_subtotal * floatval($booking->tax_rate) / 100, 2);
        $new_grand      = round($new_subtotal + $new_tax, 2);

        $wpdb->update($bookings_table, [
            'tax_amount'  => $new_tax,
            'grand_total' => $new_grand,
        ], ['id' => $booking_id]);
    }

    wp_send_json_success([
        'message'      => 'Add-ons saved successfully!',
        'addons_total' => $addons_total,
        'grand_total'  => $new_grand ?? 0,
    ]);
}

function bntm_ajax_hb_save_tax_rate() {
    check_ajax_referer('hb_settings_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $tax_rate = floatval($_POST['tax_rate']);
    bntm_set_setting('hb_tax_rate', $tax_rate);
    
    wp_send_json_success(['message' => 'Tax rate saved successfully!']);
}

function bntm_ajax_hb_delete_booking() {
    check_ajax_referer('hb_bookings_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'hb_bookings';
    $booking_id = intval($_POST['booking_id']);
    
    $result = $wpdb->delete($table, ['id' => $booking_id]);
    
    if ($result) {
        wp_send_json_success(['message' => 'Booking deleted successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete booking']);
    }
}

function bntm_ajax_hb_resend_quotation() {
    check_ajax_referer('hb_bookings_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $booking_id = intval($_POST['booking_id']);
    $sent = hb_send_quotation_email($booking_id);
    
    if ($sent) {
        wp_send_json_success(['message' => 'Email sent!']);
    } else {
        wp_send_json_error(['message' => 'Failed to send email']);
    }
}

/**
 * Returns fully-blocked dates + available_count for a date range.
 * "Fully blocked" means ALL units are occupied on that day.
 */
function bntm_ajax_hb_get_blocked_dates() {
    check_ajax_referer('hb_form_nonce','nonce');
    global $wpdb;
    $pt  = $wpdb->prefix.'hb_properties';
    $bt  = $wpdb->prefix.'hb_bookings';
    $pid = intval($_POST['property_id']);
    $ci  = sanitize_text_field($_POST['check_in']  ?? '');
    $co  = sanitize_text_field($_POST['check_out'] ?? '');

    $units = (int)$wpdb->get_var($wpdb->prepare("SELECT total_units FROM $pt WHERE id=%d",$pid));
    if (!$units) $units = 1;

    $bkgs  = $wpdb->get_results($wpdb->prepare(
        "SELECT check_in, check_out, num_rooms FROM $bt
         WHERE property_id=%d AND status NOT IN ('cancelled')", $pid));

    // Build day-level occupancy map
    $occ = [];
    foreach ($bkgs as $b) {
        $d   = new DateTime($b->check_in);
        $end = new DateTime($b->check_out);
        while ($d < $end) {
            $ds       = $d->format('Y-m-d');
            $occ[$ds] = ($occ[$ds] ?? 0) + (int)$b->num_rooms;
            $d->modify('+1 day');
        }
    }

    // Dates where ALL units are occupied
    $fully_blocked = [];
    foreach ($occ as $date => $n) {
        if ($n >= $units) $fully_blocked[] = $date;
    }

    // Available count for requested range
    $available_count = $units;
    if ($ci && $co) {
        $d   = new DateTime($ci);
        $end = new DateTime($co);
        $min = $units;
        while ($d < $end) {
            $ds  = $d->format('Y-m-d');
            $min = min($min, $units - ($occ[$ds] ?? 0));
            $d->modify('+1 day');
        }
        $available_count = max(0, $min);
    }

    wp_send_json_success([
        'fully_blocked'   => $fully_blocked,
        'available_count' => $available_count,
        'total_units'     => $units,
    ]);
}

/**
 * Submit booking – always books 1 room (qty selector removed from front-end).
 * Server-side check verifies at least 1 room is free for the entire stay.
 */
function bntm_ajax_hb_submit_booking() {
    check_ajax_referer('hb_form_nonce','nonce');
    global $wpdb;
    $bt  = $wpdb->prefix.'hb_bookings';
    $pt  = $wpdb->prefix.'hb_properties';

    $pid  = intval($_POST['property_id']);
    $ci   = sanitize_text_field($_POST['check_in']);
    $co   = sanitize_text_field($_POST['check_out']);
    $nts  = intval($_POST['num_nights']);
    $rms  = 1; // always 1 – qty selector removed

    // Validate dates
    if (!$ci || !$co || !$nts) {
        wp_send_json_error(['message'=>'Invalid booking data. Please go back and try again.']);
    }

    // Server-side availability check
    $units = (int)$wpdb->get_var($wpdb->prepare("SELECT total_units FROM $pt WHERE id=%d", $pid));
    if (!$units) $units = 1;

    $bkgs = $wpdb->get_results($wpdb->prepare(
        "SELECT check_in, check_out, num_rooms FROM $bt
         WHERE property_id=%d AND status NOT IN ('cancelled')", $pid));

    $occ = [];
    foreach ($bkgs as $b) {
        $d   = new DateTime($b->check_in);
        $e   = new DateTime($b->check_out);
        while ($d < $e) {
            $ds       = $d->format('Y-m-d');
            $occ[$ds] = ($occ[$ds] ?? 0) + (int)$b->num_rooms;
            $d->modify('+1 day');
        }
    }

    // Find minimum available rooms across every night of the stay
    $d   = new DateTime($ci);
    $e   = new DateTime($co);
    $min = $units;
    while ($d < $e) {
        $ds  = $d->format('Y-m-d');
        $min = min($min, $units - ($occ[$ds] ?? 0));
        $d->modify('+1 day');
    }

    if ($min < $rms) {
        wp_send_json_error(['message' => 'Sorry, this room is no longer available for the selected dates. Please choose different dates.']);
    }

    // Build quotation
    $tax_rate = floatval(bntm_get_setting('hb_tax_rate','12.00'));
    $q_num    = 'HB-'.strtoupper(substr(md5(uniqid(mt_rand(),true)),0,10));

    $res = $wpdb->insert($bt,[
        'rand_id'          => bntm_rand_id(),
        'quotation_number' => $q_num,
        'business_id'      => intval($_POST['business_id']),
        'property_id'      => $pid,
        'property_name'    => sanitize_text_field($_POST['property_name']),
        'customer_name'    => sanitize_text_field($_POST['customer_name']),
        'customer_email'   => sanitize_email($_POST['customer_email']),
        'customer_phone'   => sanitize_text_field($_POST['customer_phone']),
        'check_in'         => $ci,
        'check_out'        => $co,
        'num_nights'       => $nts,
        'num_rooms'        => $rms,
        'subtotal'         => floatval($_POST['subtotal']),
        'tax_rate'         => $tax_rate,
        'tax_amount'       => floatval($_POST['tax_amount']),
        'grand_total'      => floatval($_POST['grand_total']),
        'status'           => 'quoted',
        'notes'            => sanitize_textarea_field($_POST['notes'] ?? ''),
    ]);

    if ($res) {
        $booking_id = $wpdb->insert_id;
        hb_send_quotation_email($booking_id);
        $q_url = add_query_arg('q', $q_num, hb_get_quotation_page_url());
        wp_send_json_success([
            'message'       => 'Booking submitted! Redirecting to your quotation…',
            'quotation_url' => $q_url,
        ]);
    } else {
        wp_send_json_error(['message' => 'Failed to save booking: ' . $wpdb->last_error]);
    }
}

function bntm_ajax_hb_save_payment_method() {
    check_ajax_referer('hb_settings_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    $methods = json_decode(bntm_get_setting('hb_payment_methods', '[]'), true);
    if (!is_array($methods)) $methods = [];

    $methods[] = [
        'type'           => sanitize_text_field($_POST['payment_type']        ?? ''),
        'name'           => sanitize_text_field($_POST['payment_name']        ?? ''),
        'account_name'   => sanitize_text_field($_POST['account_name']        ?? ''),
        'account_number' => sanitize_text_field($_POST['account_number']      ?? ''),
        'description'    => sanitize_textarea_field($_POST['payment_description'] ?? ''),
    ];

    bntm_set_setting('hb_payment_methods', json_encode($methods));
    wp_send_json_success(['message' => 'Payment method added!']);
}

function bntm_ajax_hb_remove_payment_method() {
    check_ajax_referer('hb_settings_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    $index   = intval($_POST['index']);
    $methods = json_decode(bntm_get_setting('hb_payment_methods', '[]'), true);
    if (!is_array($methods) || !isset($methods[$index])) {
        wp_send_json_error(['message' => 'Method not found']);
    }

    array_splice($methods, $index, 1);
    bntm_set_setting('hb_payment_methods', json_encode($methods));
    wp_send_json_success(['message' => 'Payment method removed']);
}

function bntm_ajax_hb_save_payment_settings() {
    check_ajax_referer('hb_settings_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    $downpayment = intval($_POST['downpayment_percentage'] ?? 0);
    if ($downpayment < 0 || $downpayment > 100) {
        wp_send_json_error(['message' => 'Down payment must be between 0–100%']);
    }

    bntm_set_setting('hb_downpayment_percentage', $downpayment);
    bntm_set_setting('hb_terms',                  sanitize_textarea_field($_POST['terms'] ?? ''));
    wp_send_json_success(['message' => 'Payment settings saved!']);
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================


function hb_send_quotation_email($booking_id) {
    global $wpdb;
    $b = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}hb_bookings WHERE id=%d",$booking_id));
    if (!$b) return false;
    $url  = add_query_arg('q', $b->quotation_number, hb_get_quotation_page_url());
    $subj = 'Booking Quotation – '.$b->quotation_number;
    $body = "Dear {$b->customer_name},\n\nThank you for your booking!\n\nView quotation:\n{$url}\n\nBest regards";
    $sent = wp_mail($b->customer_email,$subj,$body);
    if ($sent) $wpdb->update($wpdb->prefix.'hb_bookings',['email_sent'=>1],['id'=>$booking_id]);
    return $sent;
}

function hb_get_quotation_page_url() {
    // 1. Most common slug
    $page = get_page_by_path('hotel-view-quotation');
    if ($page) return get_permalink($page->ID);

    // 2. Hyphenated variant
    $page = get_page_by_path('hb-view-quotation');
    if ($page) return get_permalink($page->ID);

    // 3. No-prefix variant
    $page = get_page_by_path('view-quotation');
    if ($page) return get_permalink($page->ID);

    // 4. Scan all pages for the shortcode
    $pages = get_pages(['number' => 200, 'post_status' => 'publish']);
    foreach ($pages as $pg) {
        if (has_shortcode($pg->post_content, 'hb_view_quotation')) {
            return get_permalink($pg->ID);
        }
    }

    // 5. Fallback: search in post meta / title
    $found = get_posts([
        'post_type'   => 'page',
        'post_status' => 'publish',
        's'           => 'quotation',
        'numberposts' => 5,
    ]);
    foreach ($found as $pg) {
        if (has_shortcode($pg->post_content, 'hb_view_quotation')) {
            return get_permalink($pg->ID);
        }
    }

    return home_url('/hotel-view-quotation/'); // last-resort guess
}

// Allow iframe embedding
add_action('send_headers', 'bntm_hb_allow_iframe_embed');
function bntm_hb_allow_iframe_embed() {
    global $post;
    
    if (is_page() && isset($post->post_content)) {
        if (has_shortcode($post->post_content, 'hb_booking_form') || 
            has_shortcode($post->post_content, 'hb_booking_form_embed')) {
            header_remove('X-Frame-Options');
            header('Content-Security-Policy: frame-ancestors *');
            header('X-Frame-Options: ALLOWALL');
        }
    }
}