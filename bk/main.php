      <?php
   /**
    * Module Name: Booking
    * Module Slug: bk
    * Description: Appointment booking system with calendar, time slots, and payment integration
    * Version: 1.0.2
    * Author: Your Name
    * Icon: 📅
    */
   
   // Prevent direct access
   if (!defined('ABSPATH')) exit;
   
   // Module constants
   define('BNTM_BK_PATH', dirname(__FILE__) . '/');
   define('BNTM_BK_URL', plugin_dir_url(__FILE__));
   
   /* ---------- MODULE CONFIGURATION ---------- */
   
   /**
    * Get module pages
    */
   function bntm_bk_get_pages() {
       return [
           'Home' => '[bk_directory]',
           'Profile' => '[bk_business]',
           'Booking' => '[bk_dashboard]',
           'Book Appointment' => '[bk_calendar]',
           'Booking Transaction' => '[bk_transaction]'
       ];
   }
   
   /**
    * Get module database tables
    */
   function bntm_bk_get_tables() {
       global $wpdb;
       $charset = $wpdb->get_charset_collate();
       $prefix  = $wpdb->prefix;
    
       return [
           'bk_services' => "CREATE TABLE {$prefix}bk_services (
               id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
               rand_id VARCHAR(20) UNIQUE NOT NULL,
               business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
               name VARCHAR(255) NOT NULL,
               category VARCHAR(100) DEFAULT '',
               description LONGTEXT,
               duration INT NOT NULL DEFAULT 60,
               price DECIMAL(10,2) NOT NULL DEFAULT 0,
               image_url TEXT,
               status VARCHAR(50) DEFAULT 'active',
               created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
               updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
               INDEX idx_business (business_id),
               INDEX idx_status (status)
           ) {$charset};",
    
           'bk_bookings' => "CREATE TABLE {$prefix}bk_bookings (
               id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
               rand_id VARCHAR(20) UNIQUE NOT NULL,
               business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
               service_id BIGINT UNSIGNED NOT NULL,
               customer_id BIGINT UNSIGNED,
               booking_date DATE NOT NULL,
               start_time TIME NOT NULL,
               end_time TIME NOT NULL,
               customer_name VARCHAR(255) NOT NULL,
               customer_email VARCHAR(255) NOT NULL,
               customer_phone VARCHAR(20),
               customer_notes LONGTEXT,
               status VARCHAR(50) DEFAULT 'pending',
               payment_status VARCHAR(50) DEFAULT 'unpaid',
               amount DECIMAL(10,2) NOT NULL,
               tax DECIMAL(10,2) DEFAULT 0,
               total DECIMAL(10,2) NOT NULL,
               payment_method VARCHAR(50),
               transaction_id VARCHAR(100),
               group_rand_id VARCHAR(30),
               created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
               updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
               INDEX idx_business (business_id),
               INDEX idx_service (service_id),
               INDEX idx_customer (customer_id),
               INDEX idx_date (booking_date),
               INDEX idx_status (status),
               INDEX idx_payment_status (payment_status),
               INDEX idx_group_rand_id (group_rand_id)
           ) {$charset};",
    
           'bk_operating_hours' => "CREATE TABLE {$prefix}bk_operating_hours (
               id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
               rand_id VARCHAR(20) UNIQUE NOT NULL,
               business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
               day_of_week INT NOT NULL,
               start_time TIME NOT NULL,
               end_time TIME NOT NULL,
               is_open BOOLEAN DEFAULT 1,
               created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
               UNIQUE KEY unique_day (business_id, day_of_week),
               INDEX idx_business (business_id)
           ) {$charset};",
    
           'bk_business_profiles' => "CREATE TABLE {$prefix}bk_business_profiles (
               id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
               rand_id VARCHAR(20) UNIQUE NOT NULL,
               business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
               logo_url TEXT,
               cover_url TEXT,
               photo_url TEXT,
               gallery_urls LONGTEXT,
               location VARCHAR(255),
               latitude DECIMAL(10,8),
               longitude DECIMAL(11,8),
               description LONGTEXT,
               accent_color VARCHAR(20),
               is_listed BOOLEAN DEFAULT 1,
               is_featured_home BOOLEAN DEFAULT 0,
               created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
               updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
               UNIQUE KEY unique_business (business_id),
               INDEX idx_business (business_id),
               INDEX idx_listed (is_listed),
               INDEX idx_featured_home (is_featured_home)
           ) {$charset};",
    
           'bk_business_ratings' => "CREATE TABLE {$prefix}bk_business_ratings (
               id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
               rand_id VARCHAR(20) UNIQUE NOT NULL,
               business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
               customer_name VARCHAR(255),
               rating DECIMAL(3,2) NOT NULL DEFAULT 0,
               review LONGTEXT,
               status VARCHAR(50) DEFAULT 'approved',
               created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
               INDEX idx_business (business_id),
               INDEX idx_status (status)
           ) {$charset};",

           'bk_remittances' => "CREATE TABLE {$prefix}bk_remittances (
               id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
               rand_id VARCHAR(30) UNIQUE NOT NULL,
               business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
               frequency_days INT NOT NULL DEFAULT 7,
               period_start DATE NOT NULL,
               period_end DATE NOT NULL,
               booking_ids LONGTEXT,
               booking_refs LONGTEXT,
               gross_total DECIMAL(10,2) NOT NULL DEFAULT 0,
               percentage_fee_rate DECIMAL(8,2) NOT NULL DEFAULT 0,
               percentage_fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
               platform_fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
               net_total DECIMAL(10,2) NOT NULL DEFAULT 0,
               release_reference VARCHAR(100) DEFAULT '',
               breakdown LONGTEXT,
               status VARCHAR(50) DEFAULT 'pending',
               released_at DATETIME NULL,
               created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
               updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
               INDEX idx_business (business_id),
               INDEX idx_status (status),
               INDEX idx_period (period_start, period_end)
           ) {$charset};"
       ];
   }
   
   /**
    * Get module shortcodes
    */
   function bntm_bk_get_shortcodes() {
       return [
           'bk_calendar' => 'bntm_shortcode_bk_calendar',
           'bk_directory' => 'bntm_shortcode_bk_directory',
           'bk_business' => 'bntm_shortcode_bk_business',
           'bk_dashboard' => 'bntm_shortcode_bk_dashboard',
           'bk_transaction' => 'bntm_shortcode_bk_transaction'
       ];
   }
   
   /**
    * Initialize default operating hours
    */
   function bntm_bk_initialize_operating_hours() {
       global $wpdb;
       $business_id = bntm_get_current_business_id();
       $table = $wpdb->prefix . 'bk_operating_hours';
       
       // Check if already initialized
       $existing = $wpdb->get_var($wpdb->prepare(
           "SELECT COUNT(*) FROM $table WHERE business_id = %d",
           $business_id
       ));
       
       // If already initialized, just modify weekends (0 = Sunday, 6 = Saturday)
       if ($existing > 0) {
           $wpdb->query($wpdb->prepare(
               "UPDATE $table 
                SET is_open = 0 
                WHERE business_id = %d 
                AND day_of_week IN (0, 6)",
               $business_id
           ));
           return;
       }
       
       // Default: 9 AM - 5 PM, Monday to Friday
       for ($day = 0; $day <= 6; $day++) {
           $is_open = ($day == 0 || $day == 6) ? 0 : 1;
           $wpdb->insert($table, [
               'rand_id' => bntm_rand_id(),
               'business_id' => $business_id,
               'day_of_week' => $day,
               'start_time' => '09:00:00',
               'end_time' => '17:00:00',
               'is_open' => $is_open
           ], ['%s', '%d', '%d', '%s', '%s', '%d']);
       }
   }
   
   function bk_resolve_business_id($identifier) {
       global $wpdb;
       if (is_numeric($identifier)) {
           return absint($identifier);
       }
       $identifier = sanitize_text_field($identifier);
       if ($identifier === '') {
           return 0;
       }
       $table = $wpdb->prefix . 'bk_business_profiles';
       return absint($wpdb->get_var($wpdb->prepare(
           "SELECT business_id FROM {$table} WHERE rand_id = %s LIMIT 1",
           $identifier
       )));
   }
   
   function bk_generate_public_id() {
       return 'bk' . bntm_rand_id(10);
   }
   
   function bk_get_request_business_id() {
       $raw_id = isset($_REQUEST['id']) ? sanitize_text_field(wp_unslash($_REQUEST['id'])) : '';
       $business_id = $raw_id !== '' ? bk_resolve_business_id($raw_id) : 0;
       if (!$business_id && isset($_REQUEST['business_id'])) {
           $business_id = bk_resolve_business_id(sanitize_text_field(wp_unslash($_REQUEST['business_id'])));
       }
       if (!$business_id && is_user_logged_in()) {
           $business_id = bntm_get_current_business_id();
       }
       return $business_id;
   }
   
   function bk_get_business_public_id($business_id) {
       global $wpdb;
       $business_id = absint($business_id);
       if (!$business_id) {
           return '';
       }
       $table = $wpdb->prefix . 'bk_business_profiles';
       $rand_id = $wpdb->get_var($wpdb->prepare(
           "SELECT rand_id FROM {$table} WHERE business_id = %d LIMIT 1",
           $business_id
       ));
       if ($rand_id && !is_numeric($rand_id)) {
           return $rand_id;
       }
   
       $existing_id = $wpdb->get_var($wpdb->prepare(
           "SELECT id FROM {$table} WHERE business_id = %d LIMIT 1",
           $business_id
       ));
       if ($existing_id) {
           $rand_id = bk_generate_public_id();
           $wpdb->update($table, ['rand_id' => $rand_id], ['id' => $existing_id], ['%s'], ['%d']);
           return $rand_id;
       }
   
       $profile = [
           'rand_id' => bk_generate_public_id(),
           'business_id' => $business_id,
           'logo_url' => bk_get_business_setting($business_id, 'site_logo', ''),
           'description' => bk_get_business_setting($business_id, 'bk_description', ''),
           'accent_color' => bk_get_business_setting($business_id, 'color_primary', '#3b82f6'),
           'is_listed' => 1,
           'created_at' => current_time('mysql'),
       ];
       $wpdb->insert($table, $profile, ['%s', '%d', '%s', '%s', '%s', '%d', '%s']);
       return $profile['rand_id'];
   }
   
   function bk_get_business_setting($business_id, $key, $default = '') {
       global $wpdb;
       $business_id = absint($business_id);
       $table = $wpdb->prefix . 'bntm_settings';
       if ($business_id > 0) {
           $value = $wpdb->get_var($wpdb->prepare(
               "SELECT setting_value FROM {$table} WHERE setting_key = %s",
               'business_' . $business_id . '__' . $key
           ));
           if ($value !== null) {
               return $value;
           }
       }
       return bntm_get_setting($key, $default);
   }

   function bk_get_shared_setting_rows($key) {
       global $wpdb;
       $table = $wpdb->prefix . 'bntm_settings';
       $like_key = $wpdb->esc_like('business_') . '%__' . $wpdb->esc_like($key);
       return $wpdb->get_col($wpdb->prepare(
           "SELECT setting_value FROM {$table} WHERE setting_key = %s OR setting_key LIKE %s ORDER BY id ASC",
           $key,
           $like_key
       ));
   }

   function bk_get_shared_setting($key, $default = '') {
       $rows = bk_get_shared_setting_rows($key);
       if (!empty($rows)) {
           $value = end($rows);
           if ($value !== null) {
               return $value;
           }
       }
       return $default;
   }

   function bk_update_shared_setting($key, $value) {
       global $wpdb;
       $table = $wpdb->prefix . 'bntm_settings';
       $exists = $wpdb->get_var($wpdb->prepare(
           "SELECT id FROM {$table} WHERE setting_key = %s",
           $key
       ));
       if ($exists) {
           $wpdb->update($table, ['setting_value' => $value], ['setting_key' => $key]);
       } else {
           $wpdb->insert($table, ['setting_key' => $key, 'setting_value' => $value]);
       }
   }

   function bk_update_business_setting($business_id, $key, $value) {
       $business_id = absint($business_id);
       if (!$business_id || $key === '') {
           return false;
       }
       return bk_update_shared_setting('business_' . $business_id . '__' . $key, $value);
   }

   function bk_get_setting_rows_with_fallbacks($keys) {
       $rows = [];
       foreach ((array) $keys as $key) {
           foreach (bk_get_shared_setting_rows($key) as $value) {
               $rows[] = $value;
           }
       }
       return $rows;
   }

   function bk_get_setting_with_fallbacks($keys, $default = '') {
       $rows = bk_get_setting_rows_with_fallbacks($keys);
       if (!empty($rows)) {
           $value = end($rows);
           if ($value !== null && $value !== '') {
               return $value;
           }
       }
       return $default;
   }

   function bk_format_price($amount = '') {
       $currency = bntm_get_setting('bk_currency', 'USD');
       $symbols = [
           'USD' => '$',
           'EUR' => '€',
           'GBP' => '£',
           'PHP' => '₱'
       ];
       $symbol = isset($symbols[$currency]) ? $symbols[$currency] : '$';
       return $symbol . number_format($amount, 2);
   }
   
   function bk_get_global_categories() {
       $clean = [];
       foreach (bk_get_setting_rows_with_fallbacks([
           'bk_admin_sports_activities',
           'bk_global_categories',
       ]) as $raw) {
           $decoded = json_decode($raw, true);
           if (!is_array($decoded)) {
               $decoded = preg_split('/[\r\n,]+/', (string) $raw);
               if (!is_array($decoded)) {
                   continue;
               }
           }
           foreach ($decoded as $category) {
               $category = sanitize_text_field($category);
               if ($category !== '' && !in_array($category, $clean, true)) {
                   $clean[] = $category;
               }
           }
       }
       sort($clean, SORT_NATURAL | SORT_FLAG_CASE);
       return $clean;
   }
   
function bk_get_business_categories($business_id) {
       global $wpdb;
       $table = $wpdb->prefix . 'bk_services';
       $rows = $wpdb->get_col($wpdb->prepare(
           "SELECT DISTINCT category FROM {$table} WHERE business_id = %d AND status = 'active' AND category <> '' ORDER BY category ASC",
           absint($business_id)
       ));
       $categories = [];
       foreach ((array) $rows as $category) {
           $category = sanitize_text_field($category);
           if ($category !== '' && !in_array($category, $categories, true)) {
               $categories[] = $category;
           }
       }
    return $categories;
}

function bk_get_home_hero_images() {
    $images = [];
    foreach (bk_get_setting_rows_with_fallbacks([
        'bk_admin_home_hero_carousel_images',
        'bk_home_hero_images',
    ]) as $raw) {
        if ($raw === '') {
            continue;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $decoded = preg_split('/[\r\n,]+/', (string) $raw);
        }
        if (is_array($decoded)) {
            foreach ($decoded as $image) {
                $image = esc_url_raw($image);
                if ($image && !in_array($image, $images, true)) {
                    $images[] = $image;
                }
            }
        }
    }
    if (empty($images)) {
        $legacy = esc_url_raw(bk_get_setting_with_fallbacks([
            'bk_admin_home_hero_image',
            'bk_home_hero_image',
        ], ''));
        if ($legacy) {
            $images[] = $legacy;
        }
    }
    return $images;
}

function bk_get_home_slides() {
    $slides = [];
    foreach (bk_get_setting_rows_with_fallbacks(['bk_admin_home_slides']) as $raw) {
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            continue;
        }
        foreach ($decoded as $slide) {
            if (!is_array($slide)) {
                continue;
            }
            $image_url = esc_url_raw($slide['image_url'] ?? '');
            if (!$image_url) {
                continue;
            }
            $slides[] = [
                'image_url' => $image_url,
                'pre_title' => sanitize_text_field($slide['pre_title'] ?? ''),
                'title' => sanitize_text_field($slide['title'] ?? ''),
                'description' => sanitize_textarea_field($slide['description'] ?? ''),
                'show_hero' => !empty($slide['show_hero']) ? 1 : 0,
                'show_onboarding' => !empty($slide['show_onboarding']) ? 1 : 0,
            ];
        }
    }

    if (!empty($slides)) {
        return $slides;
    }

    $hero_eyebrow = bk_get_setting_with_fallbacks(['bk_admin_home_hero_eyebrow'], 'Explore the');
    $hero_title = bk_get_setting_with_fallbacks(['bk_admin_home_hero_title'], get_bloginfo('name'));
    $hero_description = bk_get_setting_with_fallbacks(['bk_admin_home_hero_description'], 'Find featured venues, browse the directory, and jump into your next booking faster.');
    foreach (bk_get_home_hero_images() as $image_url) {
        $slides[] = [
            'image_url' => $image_url,
            'pre_title' => $hero_eyebrow,
            'title' => $hero_title,
            'description' => $hero_description,
            'show_hero' => 1,
            'show_onboarding' => 1,
        ];
    }
    return $slides;
}

function bk_get_primary_color() {
    $booking_color = bk_get_setting_with_fallbacks([
        'bk_public_primary_color',
    ], '');
    if ($booking_color) {
        $booking_color = sanitize_hex_color($booking_color);
        if ($booking_color) {
            return $booking_color;
        }
    }
    return sanitize_hex_color(bntm_get_setting('color_primary', '#3b82f6')) ?: '#3b82f6';
}

function bk_get_primary_hover_color() {
    $hex = ltrim(bk_get_primary_color(), '#');
    if (strlen($hex) !== 6) {
        return '#2563eb';
    }
    $rgb = [
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
    ];
    foreach ($rgb as &$channel) {
        $channel = max(0, min(255, (int) round($channel * 0.82)));
    }
    unset($channel);
    return sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
}

function bk_get_primary_root_style_tag() {
    return '<style>:root{--bntm-primary:' . esc_attr(bk_get_primary_color()) . ';--bntm-primary-hover:' . esc_attr(bk_get_primary_hover_color()) . ';}</style>';
}

function bk_get_default_amenities_catalog() {
    return [
        'Parking',
        'WiFi',
        'Shower',
        'Locker',
        'Air Conditioning',
        'Restroom',
        'Water Station',
        'Equipment Rental',
        'Waiting Area',
        'Cafeteria',
        'Pro Shop',
        'Wheelchair Access',
        'Security',
        'First Aid',
    ];
}

function bk_parse_amenities_list($raw) {
    $items = [];
    if (is_array($raw)) {
        $values = $raw;
    } else {
        $decoded = json_decode((string) $raw, true);
        if (is_array($decoded)) {
            $values = $decoded;
        } else {
            $values = preg_split('/[\r\n,]+/', (string) $raw);
        }
    }
    foreach ((array) $values as $item) {
        $item = sanitize_text_field($item);
        if ($item !== '' && !in_array($item, $items, true)) {
            $items[] = $item;
        }
    }
    return $items;
}

function bk_get_business_amenities($business_id) {
    return bk_parse_amenities_list(bk_get_business_setting($business_id, 'bk_amenities', ''));
}

function bk_business_shows_amenities($business_id) {
    return bk_get_business_setting($business_id, 'bk_show_amenities', '1') === '1';
}

function bk_get_amenity_icons() {
    return [
        'parking' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="22" height="13" rx="2"/><path d="M16 17v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2"/><path d="M12 17v4"/><line x1="8" y1="21" x2="16" y2="21"/></svg>',
        'wifi' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>',
        'shower' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12l8-8 8 8"/><path d="M5 11v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7"/></svg>',
        'locker' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="3" x2="9" y2="21"/><circle cx="6" cy="12" r="1"/></svg>',
        'air conditioning' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8h18"/><path d="M5 12h14"/><path d="M7 16h10"/></svg>',
        'restroom' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 4a2 2 0 1 1 0 4 2 2 0 0 1 0-4z"/><path d="M17 4a2 2 0 1 1 0 4 2 2 0 0 1 0-4z"/><path d="M7 8v12"/><path d="M17 8v12"/><path d="M4 13h6"/><path d="M14 13h6"/></svg>',
        'water station' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2s5 5.5 5 10a5 5 0 0 1-10 0c0-4.5 5-10 5-10z"/></svg>',
        'equipment rental' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 19l14-14"/><path d="M7 7l10 10"/></svg>',
        'waiting area' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 18v-5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v5"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>',
        'cafeteria' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a3 3 0 0 1 0 6h-1"/><path d="M3 8h15v5a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V8z"/><path d="M6 3v3"/><path d="M10 3v3"/><path d="M14 3v3"/></svg>',
        'pro shop' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6l2 12h14l2-12"/><path d="M9 6V4a3 3 0 0 1 6 0v2"/></svg>',
        'wheelchair access' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="5" r="2"/><path d="M10 7v6h4"/><path d="M12 13l3 7"/><circle cx="10" cy="18" r="4"/><path d="M18 11h-4"/></svg>',
        'security' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 4v5c0 5-3.5 8.5-7 10-3.5-1.5-7-5-7-10V7l7-4z"/></svg>',
        'first aid' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="6" width="18" height="12" rx="2"/><path d="M12 9v6"/><path d="M9 12h6"/></svg>',
        'default' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 8 12 12 16 14"/></svg>',
    ];
}

function bk_render_amenities_grid($amenities) {
    $amenities = bk_parse_amenities_list($amenities);
    if (empty($amenities)) return '';
    $icons = bk_get_amenity_icons();
    ob_start();
    ?>
    <div class="bntm-amenity-grid">
        <?php foreach ($amenities as $am):
            $icon = $icons[strtolower($am)] ?? $icons['default']; ?>
        <div class="bntm-amenity-card">
            <div class="bntm-amenity-icon"><?php echo $icon; ?></div>
            <span class="bntm-amenity-label"><?php echo esc_html($am); ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

function bk_get_remittance_bank_details($business_id = 0) {
    $business_id = absint($business_id);
    if ($business_id > 0) {
        return [
            'bank_name' => bk_get_business_setting($business_id, 'bk_remittance_bank_name', ''),
            'account_name' => bk_get_business_setting($business_id, 'bk_remittance_account_name', ''),
            'account_number' => bk_get_business_setting($business_id, 'bk_remittance_account_number', ''),
            'branch' => bk_get_business_setting($business_id, 'bk_remittance_bank_branch', ''),
            'notes' => bk_get_business_setting($business_id, 'bk_remittance_bank_notes', ''),
        ];
    }
    return [
        'bank_name' => bk_get_setting_with_fallbacks(['bk_remittance_bank_name'], ''),
        'account_name' => bk_get_setting_with_fallbacks(['bk_remittance_account_name'], ''),
        'account_number' => bk_get_setting_with_fallbacks(['bk_remittance_account_number'], ''),
        'branch' => bk_get_setting_with_fallbacks(['bk_remittance_bank_branch'], ''),
        'notes' => bk_get_setting_with_fallbacks(['bk_remittance_bank_notes'], ''),
    ];
}

function bk_get_invoice_script_helpers() {
    return <<<HTML
<script>
if (typeof window.bkPrintInvoice !== 'function') {
    window.bkPrintInvoice = function(id){
        var node = document.getElementById(id);
        if(!node){return;}
        var win = window.open('', '_blank', 'width=960,height=720');
        if(!win){return;}
        win.document.write('<html><head><title>Invoice</title><style>body{font-family:Arial,sans-serif;padding:24px;color:#111827}table{width:100%;border-collapse:collapse}td{padding:10px 0;border-bottom:1px solid #e5e7eb;text-align:left}h4{margin:0 0 8px}</style></head><body>' + node.innerHTML + '</body></html>');
        win.document.close();
        win.focus();
        win.print();
    };
}
</script>
HTML;
}

function bk_get_business_operating_hours($business_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'bk_operating_hours';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE business_id = %d ORDER BY day_of_week ASC",
        absint($business_id)
    ));
}

function bk_get_remittance_frequency_days() {
    $frequency = intval(bk_get_shared_setting('bk_remittance_frequency_days', '7'));
    return in_array($frequency, [7, 15], true) ? $frequency : 7;
}

function bk_generate_remittance_id() {
    return 'REM-' . date('Ymd') . '-' . strtoupper(wp_generate_password(6, false, false));
}
   
   function bk_get_business_name($business_id) {
       $name = get_user_meta($business_id, 'bntm_business_name', true);
       if (!$name) {
           $user = get_userdata($business_id);
           $name = $user ? $user->display_name : bntm_get_site_title();
       }
       return $name;
   }
   
   function bk_generate_return_token() {
       return wp_generate_password(32, false, false);
   }
   
   function bk_get_return_url($booking_id, $token, $result = 'success') {
       return add_query_arg([
           'id' => $booking_id,
           'gateway' => 'paymaya',
           'bk_return_token' => $token,
           'bk_return_result' => $result,
       ], bk_get_page_url_by_shortcode('[bk_transaction]', 'booking-transaction'));
   }

   function bk_encrypt_key($value) {
       if (empty($value)) return '';
       $key = defined('AUTH_KEY') ? AUTH_KEY : 'bk_fallback_key_32chars_padded!!';
       $iv  = openssl_random_pseudo_bytes(16);
       $enc = openssl_encrypt($value, 'AES-256-CBC', substr(hash('sha256', $key), 0, 32), 0, $iv);
       return base64_encode($iv . '::' . $enc);
   }

   function bk_decrypt_key($value) {
       if (empty($value)) return '';
       $key = defined('AUTH_KEY') ? AUTH_KEY : 'bk_fallback_key_32chars_padded!!';
       $parts = explode('::', base64_decode($value), 2);
       if (count($parts) !== 2) return '';
       [$iv, $enc] = $parts;
       return openssl_decrypt($enc, 'AES-256-CBC', substr(hash('sha256', $key), 0, 32), 0, $iv);
   }

   function bk_get_maya_config() {
       return [
           'mode' => get_option('bk_maya_mode', 'sandbox'),
           'public_key' => bk_decrypt_key(get_option('bk_maya_public_key_enc', '')),
           'secret_key' => bk_decrypt_key(get_option('bk_maya_secret_key_enc', '')),
       ];
   }

   function bk_get_paymaya_method() {
       $config = bk_get_maya_config();
       if (empty($config['public_key']) || empty($config['secret_key'])) {
           return null;
       }
       return (object) [
           'id' => 0,
           'name' => 'Maya Checkout',
           'gateway' => 'paymaya',
           'mode' => $config['mode'],
           'config' => wp_json_encode($config),
       ];
   }
   
   function bk_get_page_url_by_shortcode($shortcode, $fallback_slug = '') {
       $pages = get_posts([
           'post_type' => 'page',
           'post_status' => 'publish',
           'numberposts' => 1,
           's' => $shortcode,
       ]);
   
       foreach ($pages as $page) {
           if (has_shortcode($page->post_content, trim($shortcode, '[]'))) {
               return get_permalink($page);
           }
       }
   
       $page = $fallback_slug ? get_page_by_path($fallback_slug) : null;
       return $page ? get_permalink($page) : home_url('/');
   }
   
   function bk_get_profile_url($business_id) {
       return add_query_arg('id', bk_get_business_public_id($business_id), bk_get_page_url_by_shortcode('[bk_business]', 'profile'));
   }
   
   function bk_get_booking_url($business_id) {
       return add_query_arg('id', bk_get_business_public_id($business_id), bk_get_page_url_by_shortcode('[bk_calendar]', 'book-appointment'));
   }
   
   function bk_get_business_profile($business_id) {
       global $wpdb;
       $business_id = absint($business_id);
       $table = $wpdb->prefix . 'bk_business_profiles';
       $profile = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE business_id = %d", $business_id));
       if (!$profile) {
           $profile = (object) [
               'business_id' => $business_id,
               'logo_url' => bk_get_business_setting($business_id, 'site_logo', ''),
               'cover_url' => '',
               'photo_url' => '',
               'gallery_urls' => '[]',
               'location' => '',
               'latitude' => '',
               'longitude' => '',
               'description' => bk_get_business_setting($business_id, 'bk_description', ''),
               'accent_color' => bk_get_business_setting($business_id, 'color_primary', '#3b82f6'),
               'is_listed' => 1,
               'is_featured_home' => 0,
           ];
       }
       return $profile;
   }
   
   function bk_get_business_rating_summary($business_id) {
       global $wpdb;
       $table = $wpdb->prefix . 'bk_business_ratings';
       $row = $wpdb->get_row($wpdb->prepare(
           "SELECT COUNT(*) AS total, AVG(rating) AS average FROM {$table} WHERE business_id = %d AND status = 'approved'",
           $business_id
       ));
       return [
           'total' => $row ? intval($row->total) : 0,
           'average' => $row && $row->average ? round(floatval($row->average), 2) : 0,
       ];
   }

   function bk_render_dashboard_stat_card($title, $value, $icon_svg) {
       return '<div class="bntm-stat-card">'
           . '<div class="bntm-stat-icon bntm-stat-icon-primary">' . $icon_svg . '</div>'
           . '<div class="bntm-stat-content">'
           . '<h3>' . esc_html($title) . '</h3>'
           . '<p class="bntm-stat-number">' . esc_html($value) . '</p>'
           . '</div>'
       . '</div>';
   }

   function bk_overview_tab_script($stats) {
       $bookingsLabels = json_encode(array_column($stats['monthly_bookings_data'], 'month'));
       $bookingsTotals = json_encode(array_column($stats['monthly_bookings_data'], 'total'));
       $servicesLabels = json_encode(array_column($stats['service_bookings_data'], 'name'));
       $servicesTotals = json_encode(array_column($stats['service_bookings_data'], 'total'));
       $statusLabels = json_encode(array_column($stats['status_data'], 'status'));
       $statusCounts = json_encode(array_column($stats['status_data'], 'count'));
       $ajaxurl = admin_url('admin-ajax.php');
       return <<<HTML
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
var ajaxurl = '{$ajaxurl}';
(function() {
    const primaryColor = getComputedStyle(document.documentElement)
        .getPropertyValue('--bntm-primary').trim() || '#374151';

    const bookingsCtx = document.getElementById('bookingsChart');
    if (bookingsCtx) {
        new Chart(bookingsCtx, {
            type: 'line',
            data: {
                labels: {$bookingsLabels},
                datasets: [{
                    label: 'Bookings',
                    data: {$bookingsTotals},
                    borderColor: primaryColor,
                    backgroundColor: primaryColor + '20',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: primaryColor,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#111827',
                        padding: 12,
                        titleFont: { size: 14, weight: '600' },
                        bodyFont: { size: 13 },
                        cornerRadius: 8
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f3f4f6' },
                        ticks: { color: '#6b7280', font: { size: 12 }, precision: 0 }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#6b7280', font: { size: 12 } }
                    }
                }
            }
        });
    }

    const servicesCtx = document.getElementById('servicesChart');
    if (servicesCtx) {
        new Chart(servicesCtx, {
            type: 'doughnut',
            data: {
                labels: {$servicesLabels},
                datasets: [{
                    data: {$servicesTotals},
                    backgroundColor: [
                        primaryColor,
                        '#6b7280',
                        '#9ca3af',
                        '#d1d5db',
                        '#e5e7eb'
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 15, font: { size: 12 }, color: '#374151' }
                    },
                    tooltip: {
                        backgroundColor: '#111827',
                        padding: 12,
                        titleFont: { size: 14, weight: '600' },
                        bodyFont: { size: 13 },
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                return label + ': ' + value + ' bookings';
                            }
                        }
                    }
                }
            }
        });
    }

    const statusCtx = document.getElementById('statusChart');
    if (statusCtx) {
        new Chart(statusCtx, {
            type: 'pie',
            data: {
                labels: {$statusLabels},
                datasets: [{
                    data: {$statusCounts},
                    backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#6b7280'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 15, font: { size: 12 }, color: '#374151' }
                    },
                    tooltip: {
                        backgroundColor: '#111827',
                        padding: 12,
                        titleFont: { size: 14, weight: '600' },
                        bodyFont: { size: 13 },
                        cornerRadius: 8
                    }
                }
            }
        });
    }
})();
</script>
HTML;
   }

   function bk_render_service_category_options($categories, $selected = '') {
       $output = '<option value="">' . esc_html(empty($categories) ? 'No categories yet' : 'Select category') . '</option>';
       foreach ((array) $categories as $category) {
           $output .= '<option value="' . esc_attr($category) . '"' . selected($selected, $category, false) . '>' . esc_html($category) . '</option>';
       }
       return $output;
   }

   function bk_handle_image_upload($field, $business_id, $prefix = 'bk') {
       if (empty($_FILES[$field]) || empty($_FILES[$field]['name'])) {
           return '';
       }
       if (!function_exists('wp_handle_upload')) {
           require_once ABSPATH . 'wp-admin/includes/file.php';
       }
       if (!function_exists('wp_get_image_editor')) {
           require_once ABSPATH . 'wp-admin/includes/image.php';
       }
   
       $file = $_FILES[$field];
       $allowed = ['image/jpeg', 'image/png', 'image/webp'];
       $type = function_exists('mime_content_type') ? mime_content_type($file['tmp_name']) : $file['type'];
       if (!in_array($type, $allowed, true)) {
           return new WP_Error('invalid_image', 'Please upload a JPG, PNG, or WebP image.');
       }
   
       $uploads = wp_upload_dir();
       $dir = trailingslashit($uploads['basedir']) . 'bntm-booking/' . absint($business_id);
       wp_mkdir_p($dir);
       $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
       $filename = sanitize_file_name($prefix . '-' . time() . '-' . wp_generate_password(6, false) . '.' . $ext);
       $target = trailingslashit($dir) . $filename;
   
       $editor = wp_get_image_editor($file['tmp_name']);
       if (is_wp_error($editor)) {
           return $editor;
       }
       $editor->resize(1600, 1200, false);
       $quality = 82;
       do {
           $editor->set_quality($quality);
           $saved = $editor->save($target);
           if (is_wp_error($saved)) {
               return $saved;
           }
           if (filesize($target) <= 500 * 1024 || $quality <= 48) {
               break;
           }
           $quality -= 8;
       } while (true);
   
       if (filesize($target) > 500 * 1024) {
           @unlink($target);
           return new WP_Error('image_too_large', 'Image could not be compressed below 500KB. Please use a smaller image.');
       }
   
       return trailingslashit($uploads['baseurl']) . 'bntm-booking/' . absint($business_id) . '/' . $filename;
   }
   
   
   // AJAX handlers
   add_action('wp_ajax_bk_get_available_slots', 'bntm_ajax_bk_get_available_slots');
   add_action('wp_ajax_nopriv_bk_get_available_slots', 'bntm_ajax_bk_get_available_slots');
   add_action('wp_ajax_bk_book_cart', 'bntm_ajax_bk_book_cart');
   add_action('wp_ajax_nopriv_bk_book_cart', 'bntm_ajax_bk_book_cart');
   add_action('wp_ajax_bk_book_appointment', 'bntm_ajax_bk_book_appointment');
   add_action('wp_ajax_nopriv_bk_book_appointment', 'bntm_ajax_bk_book_appointment');
   add_action('wp_ajax_bk_update_booking_status', 'bntm_ajax_bk_update_booking_status');
   add_action('wp_ajax_bk_generate_remittance_invoice', 'bk_generate_remittance_invoice_handler');
   add_action('wp_ajax_bk_update_remittance_invoice', 'bk_update_remittance_invoice_handler');
   add_action('wp_ajax_bk_delete_remittance_invoice', 'bk_delete_remittance_invoice_handler');
   
   /* ---------- MAIN DASHBOARD SHORTCODE ---------- */
   
function bntm_shortcode_bk_dashboard() {
       if (!is_user_logged_in()) {
           return '<div class="bntm-notice">Please log in to access the Booking dashboard.</div>';
       }
       
       $business_id = bntm_get_current_business_id();
       $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
       
       ob_start();
       ?>
       
       <style>
       .in-modal {
           position: fixed;
           top: 0;
           left: 0;
           width: 100%;
           height: 100%;
           background: rgba(0,0,0,0.5);
           z-index: 1000;
           display: flex;
           align-items: center;
           justify-content: center;
       }
       .in-modal-content {
           background: white;
           padding: 30px;
           border-radius: 8px;
           max-width: 700px;
           width: 90%;
           max-height: 90vh;
           overflow-y: auto;
       }
       </style>
       <div class="bntm-booking-container">
           <div class="bntm-tabs">
               <a href="?tab=overview" class="bntm-tab <?php echo $active_tab === 'overview' ? 'active' : ''; ?>">Overview</a>
               <a href="?tab=services" class="bntm-tab <?php echo $active_tab === 'services' ? 'active' : ''; ?>">Services</a>
               <a href="?tab=calendar" class="bntm-tab <?php echo $active_tab === 'calendar' ? 'active' : ''; ?>">Calendar</a>
               <a href="?tab=bookings" class="bntm-tab <?php echo $active_tab === 'bookings' ? 'active' : ''; ?>">All Bookings</a>
               <a href="?tab=invoices" class="bntm-tab <?php echo $active_tab === 'invoices' ? 'active' : ''; ?>">Invoices</a>
               <a href="?tab=settings" class="bntm-tab <?php echo $active_tab === 'settings' ? 'active' : ''; ?>">Settings</a>
               <?php if (current_user_can('manage_options')): ?>
                   <a href="?tab=admin-payments" class="bntm-tab <?php echo $active_tab === 'admin-payments' ? 'active' : ''; ?>">Admin Settings</a>
                   <a href="?tab=remittance" class="bntm-tab <?php echo $active_tab === 'remittance' ? 'active' : ''; ?>">Remittance</a>
               <?php endif; ?>
           </div>
           
           <div class="bntm-tab-content">
               <?php if ($active_tab === 'overview'): ?>
                   <?php echo bk_overview_tab($business_id); ?>
               <?php elseif ($active_tab === 'services'): ?>
                   <?php echo bk_services_tab($business_id); ?>
               <?php elseif ($active_tab === 'calendar'): ?>
                   <?php echo bk_bookings_calendar_tab($business_id); ?>
               <?php elseif ($active_tab === 'bookings'): ?>
                   <?php echo bk_bookings_tab($business_id); ?>
               <?php elseif ($active_tab === 'invoices'): ?>
                   <?php echo bk_invoices_tab($business_id); ?>
               <?php elseif ($active_tab === 'settings'): ?>
                   <?php echo bk_settings_tab($business_id); ?>
               <?php elseif ($active_tab === 'admin-payments' && current_user_can('manage_options')): ?>
                   <?php echo bk_admin_payments_tab(); ?>
               <?php elseif ($active_tab === 'remittance' && current_user_can('manage_options')): ?>
                   <?php echo bk_remittance_tab(); ?>
               <?php endif; ?>
           </div>
       </div>
       <?php
       
       $content = ob_get_clean();
       return bntm_universal_container('Booking', $content);
   }
   
   /* ---------- TAB FUNCTIONS ---------- */
   function bk_overview_tab($business_id) {
       $stats = bk_get_dashboard_stats($business_id);
       $booking_page = get_page_by_path('book-appointment');
       $booking_url = $booking_page ? add_query_arg('id', bk_get_business_public_id($business_id), get_permalink($booking_page)) : '';
       
       ob_start();
       ?>
       <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
       <script>
       var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
       </script>
       
       <?php if ($booking_url): ?>
       <div class="bntm-booking-page-card">
           <div class="bntm-booking-header">
               <h3>Your Booking Page</h3>
               <span class="bntm-status-badge">Active</span>
           </div>
           <div class="bntm-booking-actions">
               <input type="text" id="booking-url" value="<?php echo esc_url($booking_url); ?>" readonly class="bntm-url-input">
               <button class="bntm-btn-secondary" id="copy-booking-url">Copy Link</button>
               <a href="<?php echo esc_url($booking_url); ?>" target="_blank" class="bntm-btn-primary">View Booking Page</a>
           </div>
       </div>
       <?php endif; ?>
       
       <div class="bntm-dashboard-stats">
           <?php
           echo bk_render_dashboard_stat_card(
               'Total Services',
               $stats['total_services'],
               '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>'
           );
           echo bk_render_dashboard_stat_card(
               'Total Bookings',
               $stats['total_bookings'],
               '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>'
           );
           echo bk_render_dashboard_stat_card(
               'Monthly Revenue',
               bk_format_price($stats['monthly_revenue']),
               '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>'
           );
           echo bk_render_dashboard_stat_card(
               'Pending Bookings',
               $stats['pending_bookings'],
               '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>'
           );
           ?>
       </div>
   
       <div class="bntm-charts-grid">
           <div class="bntm-chart-card bntm-chart-large">
               <h3>Bookings Overview</h3>
               <canvas id="bookingsChart"></canvas>
           </div>
           
           <div class="bntm-chart-card">
               <h3>Top Services by Bookings</h3>
               <canvas id="servicesChart"></canvas>
           </div>
           
           <div class="bntm-chart-card">
               <h3>Booking Status</h3>
               <canvas id="statusChart"></canvas>
           </div>
       </div>
   
       <div class="bntm-recent-bookings-section">
           <h3>Recent Bookings</h3>
           <?php echo bk_render_recent_bookings($business_id, 10); ?>
       </div>
   
       <style>
       .bntm-booking-page-card {
           background: #f8f9fa;
           padding: 24px;
           border-radius: 12px;
           margin-bottom: 30px;
           border: 1px solid #e5e7eb;
       }
       
       .bntm-booking-header {
           display: flex;
           align-items: center;
           justify-content: space-between;
           margin-bottom: 20px;
       }
       
       .bntm-booking-header h3 {
           margin: 0;
           color: #111827;
           font-size: 18px;
           font-weight: 600;
       }
       
       .bntm-status-badge {
           background: #10b981;
           color: #ffffff;
           padding: 4px 12px;
           border-radius: 6px;
           font-size: 12px;
           font-weight: 500;
       }
       
       .bntm-booking-actions {
           display: flex;
           align-items: center;
           gap: 12px;
           flex-wrap: wrap;
       }
       
       .bntm-url-input {
           flex: 1;
           min-width: 300px;
           padding: 12px 16px;
           border: 1px solid #d1d5db;
           border-radius: 8px;
           background: #ffffff;
           color: #374151;
           font-size: 14px;
           font-family: monospace;
           transition: all 0.2s ease;
       }
       
       .bntm-url-input:focus {
           outline: none;
           border-color: #9ca3af;
       }
       
       .bntm-dashboard-stats {
           display: grid;
           grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
           gap: 20px;
           margin-bottom: 30px;
       }
       
       .bntm-stat-card {
           background: #ffffff;
           padding: 24px;
           border-radius: 12px;
           display: flex;
           align-items: flex-start;
           gap: 16px;
           border: 1px solid #e5e7eb;
           transition: all 0.2s ease;
       }
       
       .bntm-stat-card:hover {
           border-color: #d1d5db;
           box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
       }
       
       .bntm-stat-icon {
           width: 48px;
           height: 48px;
           border-radius: 10px;
           display: flex;
           align-items: center;
           justify-content: center;
           flex-shrink: 0;
       }
       
       .bntm-stat-icon-primary {
           background: var(--bntm-primary, #374151);
           color: #ffffff;
       }
       
       .bntm-stat-content {
           flex: 1;
       }
       
       .bntm-stat-content h3 {
           margin: 0 0 8px 0;
           font-size: 13px;
           color: #6b7280;
           font-weight: 500;
           text-transform: uppercase;
           letter-spacing: 0.5px;
       }
       
       .bntm-stat-number {
           font-size: 28px;
           font-weight: 700;
           color: #111827;
           margin: 0;
           line-height: 1;
       }
       
       .bntm-charts-grid {
           display: grid;
           grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
           gap: 20px;
           margin-bottom: 30px;
       }
       
       .bntm-chart-card {
           background: #ffffff;
           padding: 24px;
           border-radius: 12px;
           border: 1px solid #e5e7eb;
       }
       
       .bntm-chart-large {
           grid-column: 1 / -1;
       }
       
       .bntm-chart-card h3 {
           margin: 0 0 20px 0;
           font-size: 16px;
           font-weight: 600;
           color: #111827;
       }
       
       
       <?php echo bk_overview_tab_script($stats); ?>
       <?php
       return ob_get_clean();
   }
   
   function bk_admin_payments_tab() {
       global $wpdb;
       $profiles_table = $wpdb->prefix . 'bk_business_profiles';
       $profile_business_ids = $wpdb->get_col("SELECT business_id FROM {$profiles_table} WHERE business_id > 0 ORDER BY business_id ASC");
       $featured_business_ids = $wpdb->get_col("SELECT business_id FROM {$profiles_table} WHERE is_featured_home = 1 ORDER BY business_id ASC");
       $global_categories = bk_get_global_categories();
       $home_slides = bk_get_home_slides();
       $hero_eyebrow = bk_get_setting_with_fallbacks(['bk_admin_home_hero_eyebrow'], 'Explore the');
       $hero_title = bk_get_setting_with_fallbacks(['bk_admin_home_hero_title'], get_bloginfo('name'));
       $hero_description = bk_get_setting_with_fallbacks(['bk_admin_home_hero_description'], 'Find featured venues, browse the directory, and jump into your next booking faster.');
       $remittance_percentage_fee = bk_get_shared_setting('bk_remittance_percentage_fee', '0');
       $remittance_platform_fee = bk_get_shared_setting('bk_remittance_platform_fee', '0');
       $remittance_frequency_days = bk_get_remittance_frequency_days();
       $public_primary_color = bk_get_setting_with_fallbacks(['bk_public_primary_color'], bk_get_primary_color());
       $maya_config = bk_get_maya_config();
       $maya_mode = $maya_config['mode'];
       $has_maya_public_key = !empty(get_option('bk_maya_public_key_enc', ''));
       $has_maya_secret_key = !empty(get_option('bk_maya_secret_key_enc', ''));
   
       ob_start();
       ?>
       <script>var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';</script>
       <style>
       .bk-admin-tag{display:inline-flex;align-items:center;gap:6px;background:#f1f5f9;border:1px solid #e5e7eb;border-radius:999px;padding:4px 12px;font-size:13px;font-weight:500}
       .bk-admin-tag button{background:none;border:none;cursor:pointer;color:#94a3b8;line-height:1;padding:0}
       .bk-slide-table{width:100%;border-collapse:separate;border-spacing:0 10px}
       .bk-slide-table th{text-align:left;font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:.04em;padding:0 8px}
       .bk-slide-row td{background:#f8fafc;border-top:1px solid #e5e7eb;border-bottom:1px solid #e5e7eb;padding:10px 8px;vertical-align:top}
       .bk-slide-row td:first-child{border-left:1px solid #e5e7eb;border-radius:10px 0 0 10px}
       .bk-slide-row td:last-child{border-right:1px solid #e5e7eb;border-radius:0 10px 10px 0}
       .bk-slide-thumb{width:94px;height:70px;border-radius:8px;object-fit:cover;border:1px solid #e5e7eb;background:#fff;display:block;margin-bottom:8px}
       .bk-slide-photo-remove{border:none;background:#f1f5f9;color:#475569;border-radius:8px;padding:7px 9px;margin-top:6px;cursor:pointer;font-size:12px;font-weight:700}
       .bk-slide-photo-remove:hover{background:#fee2e2;color:#991b1b}
       .bk-slide-table input[type="text"],.bk-slide-table textarea{width:100%;min-width:150px;padding:9px 10px;border:1px solid #d1d5db;border-radius:8px;background:#fff}
       .bk-slide-table textarea{min-height:70px;resize:vertical}
       .bk-slide-checks{display:grid;gap:8px;min-width:120px}
       .bk-slide-switch{display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer}
       .bk-slide-switch input{position:absolute;opacity:0;pointer-events:none}
       .bk-slide-switch span{width:38px;height:22px;border-radius:999px;background:#cbd5e1;position:relative;display:inline-flex;transition:.2s;flex-shrink:0}
       .bk-slide-switch span:before{content:"";position:absolute;width:18px;height:18px;left:2px;top:2px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(15,23,42,.22);transition:.2s}
       .bk-slide-switch input:checked + span{background:var(--bntm-primary,#3b82f6)}
       .bk-slide-switch input:checked + span:before{transform:translateX(16px)}
       .bk-slide-switch em{font-style:normal;color:#334155;font-weight:600}
       .bk-slide-remove{border:none;background:#fee2e2;color:#991b1b;border-radius:8px;padding:8px 10px;cursor:pointer;font-weight:700}
       .bk-admin-picker{position:relative}
       .bk-admin-picker-input{width:100%;padding:12px 14px;border:1px solid #d1d5db;border-radius:12px}
       .bk-admin-picker-menu{position:absolute;top:calc(100% + 8px);left:0;right:0;max-height:260px;overflow:auto;background:#fff;border:1px solid #e5e7eb;border-radius:14px;box-shadow:0 16px 40px rgba(15,23,42,.12);z-index:5;display:none}
       .bk-admin-picker.open .bk-admin-picker-menu{display:block}
       .bk-admin-picker-option{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 14px;cursor:pointer}
       .bk-admin-picker-option:hover{background:#f8fafc}
       .bk-admin-picker-option.is-selected{background:#eff6ff}
       .bk-admin-selected{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
       </style>
       <div class="bntm-form-section">
           <h3>Admin Settings</h3>
           <p>Booking checkout uses the Maya API settings below. Use this panel to control the Home hero, featured businesses, and shared service categories.</p>
           <form id="bk-admin-settings-form" class="bntm-form" enctype="multipart/form-data">
               <div class="bntm-form-section" style="padding:18px;margin-bottom:18px;">
                   <h4 style="margin:0 0 10px;">Maya Payment API</h4>
                   <p style="color:#6b7280;margin:0 0 14px;">Keys are encrypted before storing. Leave key fields blank to keep the existing saved key.</p>
                   <div class="bntm-form-row">
                       <div class="bntm-form-group">
                           <label>Mode</label>
                           <select name="maya_mode">
                               <option value="sandbox" <?php selected($maya_mode, 'sandbox'); ?>>Sandbox</option>
                               <option value="live" <?php selected($maya_mode, 'live'); ?>>Live</option>
                           </select>
                       </div>
                       <div class="bntm-form-group">
                           <label>Public Key <?php echo $has_maya_public_key ? '<span style="color:#16a34a;">Set</span>' : '<span style="color:#dc2626;">Not set</span>'; ?></label>
                           <input type="text" name="maya_public_key" placeholder="pk-..." autocomplete="off" style="font-family:monospace;">
                       </div>
                       <div class="bntm-form-group">
                           <label>Secret Key <?php echo $has_maya_secret_key ? '<span style="color:#16a34a;">Set</span>' : '<span style="color:#dc2626;">Not set</span>'; ?></label>
                           <input type="password" name="maya_secret_key" placeholder="sk-..." autocomplete="new-password" style="font-family:monospace;">
                       </div>
                   </div>
               </div>
               <div class="bntm-form-group">
                   <label>Home Slides</label>
                   <div class="bntm-table-wrapper">
                       <table class="bk-slide-table">
                           <thead>
                               <tr>
                                   <th>Photo</th>
                                   <th>Pre Title</th>
                                   <th>Title</th>
                                   <th>Description</th>
                                   <th>Display</th>
                                   <th></th>
                               </tr>
                           </thead>
                           <tbody id="bk-slide-rows">
                               <?php foreach ($home_slides as $slide): ?>
                               <tr class="bk-slide-row">
                                   <td>
                                       <img class="bk-slide-thumb" src="<?php echo esc_url($slide['image_url']); ?>" alt="">
                                       <input type="file" name="home_slide_images[]" accept="image/jpeg,image/png,image/webp">
                                       <input type="hidden" name="home_slide_existing[]" value="<?php echo esc_attr($slide['image_url']); ?>">
                                       <button type="button" class="bk-slide-photo-remove">Remove Photo</button>
                                   </td>
                                   <td><input type="text" name="home_slide_pre_title[]" value="<?php echo esc_attr($slide['pre_title']); ?>"></td>
                                   <td><input type="text" name="home_slide_title[]" value="<?php echo esc_attr($slide['title']); ?>"></td>
                                   <td><textarea name="home_slide_description[]"><?php echo esc_textarea($slide['description']); ?></textarea></td>
                                   <td>
                                       <div class="bk-slide-checks">
                                           <input type="hidden" name="home_slide_show_hero[]" value="<?php echo !empty($slide['show_hero']) ? '1' : '0'; ?>">
                                           <label class="bk-slide-switch"><input type="checkbox" class="bk-slide-toggle" data-hidden="home_slide_show_hero[]" <?php checked(!empty($slide['show_hero'])); ?>><span></span><em>Hero</em></label>
                                           <input type="hidden" name="home_slide_show_onboarding[]" value="<?php echo !empty($slide['show_onboarding']) ? '1' : '0'; ?>">
                                           <label class="bk-slide-switch"><input type="checkbox" class="bk-slide-toggle" data-hidden="home_slide_show_onboarding[]" <?php checked(!empty($slide['show_onboarding'])); ?>><span></span><em>Onboarding</em></label>
                                       </div>
                                   </td>
                                   <td><button type="button" class="bk-slide-remove">Remove</button></td>
                               </tr>
                               <?php endforeach; ?>
                           </tbody>
                       </table>
                   </div>
                   <button type="button" class="bntm-btn-secondary" id="bk-add-slide-row">Add Slide</button>
                   <small>Each row can appear in the Home hero, onboarding, or both.</small>
               </div>
   
               <div class="bntm-form-group">
                   <label>Featured Businesses on Home</label>
                   <div class="bk-admin-picker" id="bk-featured-picker">
                       <input type="text" class="bk-admin-picker-input" id="bk-featured-search" placeholder="Search businesses to feature">
                       <div class="bk-admin-picker-menu" id="bk-featured-menu">
                           <?php foreach ($profile_business_ids as $profile_business_id): ?>
                               <div class="bk-admin-picker-option <?php echo in_array((string) $profile_business_id, array_map('strval', (array) $featured_business_ids), true) ? 'is-selected' : ''; ?>" data-id="<?php echo esc_attr($profile_business_id); ?>" data-name="<?php echo esc_attr(strtolower(bk_get_business_name($profile_business_id))); ?>">
                                   <span><?php echo esc_html(bk_get_business_name($profile_business_id)); ?></span>
                                   <span><?php echo in_array((string) $profile_business_id, array_map('strval', (array) $featured_business_ids), true) ? 'Selected' : 'Add'; ?></span>
                               </div>
                           <?php endforeach; ?>
                       </div>
                   </div>
                   <div class="bk-admin-selected" id="bk-featured-selected"></div>
                   <div id="bk-featured-hidden-inputs">
                       <?php foreach ($featured_business_ids as $featured_business_id): ?>
                           <input type="hidden" name="featured_business_ids[]" value="<?php echo esc_attr($featured_business_id); ?>">
                       <?php endforeach; ?>
                   </div>
                   <small>You can feature multiple businesses. They will be shown in a two-column grid on Home.</small>
               </div>
   
               <div class="bntm-form-group">
                   <label>Sports and Activities</label>
                   <div id="bk-admin-category-list" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
                       <?php foreach ($global_categories as $category): ?>
                           <span class="bk-admin-tag"><?php echo esc_html($category); ?><button type="button" onclick="bkAdminRemoveCategory(this)">×</button></span>
                       <?php endforeach; ?>
                   </div>
                   <div style="display:flex;gap:8px;flex-wrap:wrap;">
                       <input type="text" id="bk-admin-category-input" placeholder="e.g. Badminton, Tennis, Yoga" style="flex:1;min-width:220px;">
                       <button type="button" class="bntm-btn-secondary" id="bk-admin-add-category">Add Activity</button>
                   </div>
                   <input type="hidden" name="global_categories" id="bk-admin-categories-hidden" value="<?php echo esc_attr(wp_json_encode($global_categories)); ?>">
                   <small>Services will choose from this shared list.</small>
               </div>
               <div class="bntm-form-row">
                   <div class="bntm-form-group">
                       <label>Remittance Percentage Fee (%)</label>
                       <input type="number" name="remittance_percentage_fee" min="0" step="0.01" value="<?php echo esc_attr($remittance_percentage_fee); ?>">
                       <small>Deducted from the gross booking total before remittance.</small>
                   </div>
                   <div class="bntm-form-group">
                       <label>Platform Fee</label>
                       <input type="number" name="remittance_platform_fee" min="0" step="0.01" value="<?php echo esc_attr($remittance_platform_fee); ?>">
                       <small>Fixed fee deducted per remittance invoice.</small>
                   </div>
               </div>
               <div class="bntm-form-group">
                   <label>Remittance Release Schedule</label>
                   <select name="remittance_frequency_days">
                       <option value="7" <?php selected($remittance_frequency_days, 7); ?>>Weekly</option>
                       <option value="15" <?php selected($remittance_frequency_days, 15); ?>>Every 15 Days</option>
                   </select>
                   <small>Used when generating remittance invoices for release of funds.</small>
               </div>
               <div class="bntm-form-group">
                   <label>Booking Public Primary Color</label>
                   <input type="color" name="public_primary_color" value="<?php echo esc_attr($public_primary_color); ?>">
                   <small>Used on the directory, profile, booking transaction, and book appointment pages.</small>
               </div>
               <button type="submit" class="bntm-btn-primary">Save Admin Settings</button>
               <div id="bk-admin-settings-message"></div>
           </form>
           <?php if (bntm_is_module_enabled('op') && bntm_is_module_visible('op')): ?>
               <p style="margin-top:16px"><a class="bntm-btn-secondary" href="<?php echo esc_url(admin_url()); ?>">Open WordPress Admin</a></p>
           <?php else: ?>
               <div class="bntm-notice bntm-notice-warning">Online Payment module is not currently visible. Enable it to configure centralized Maya checkout.</div>
           <?php endif; ?>
       </div>
       <script>
       (function() {
           const form = document.getElementById('bk-admin-settings-form');
           if (!form) return;
           const categoryInput = document.getElementById('bk-admin-category-input');
           const categoryList = document.getElementById('bk-admin-category-list');
           const categoryHidden = document.getElementById('bk-admin-categories-hidden');
           const featuredPicker = document.getElementById('bk-featured-picker');
           const featuredSearch = document.getElementById('bk-featured-search');
           const featuredMenu = document.getElementById('bk-featured-menu');
           const featuredSelected = document.getElementById('bk-featured-selected');
           const featuredHiddenInputs = document.getElementById('bk-featured-hidden-inputs');
           const slideRows = document.getElementById('bk-slide-rows');
           const addSlideRowBtn = document.getElementById('bk-add-slide-row');

           function getSelectedFeaturedIds() {
               return Array.from(featuredHiddenInputs.querySelectorAll('input[name="featured_business_ids[]"]')).map(input => input.value);
           }

           function renderSelectedFeatured() {
               const selectedIds = getSelectedFeaturedIds();
               featuredSelected.innerHTML = '';
               featuredMenu.querySelectorAll('.bk-admin-picker-option').forEach(option => {
                   const id = option.dataset.id;
                   const label = option.querySelector('span');
                   const action = option.querySelectorAll('span')[1];
                   const isSelected = selectedIds.includes(id);
                   option.classList.toggle('is-selected', isSelected);
                   if (action) {
                       action.textContent = isSelected ? 'Selected' : 'Add';
                   }
                   if (isSelected && label) {
                       const tag = document.createElement('span');
                       tag.className = 'bk-admin-tag';
                       tag.innerHTML = label.textContent + '<button type="button" data-remove-id="' + id + '">×</button>';
                       featuredSelected.appendChild(tag);
                   }
               });
           }

           function setFeaturedSelection(ids) {
               featuredHiddenInputs.innerHTML = '';
               ids.forEach(id => {
                   const input = document.createElement('input');
                   input.type = 'hidden';
                   input.name = 'featured_business_ids[]';
                   input.value = id;
                   featuredHiddenInputs.appendChild(input);
               });
               renderSelectedFeatured();
           }

           function bindSlideRow(row) {
               row.querySelectorAll('.bk-slide-toggle').forEach(toggle => {
                   const hidden = toggle.closest('.bk-slide-checks').querySelector('input[type="hidden"][name="' + toggle.dataset.hidden + '"]');
                   if (hidden) {
                       hidden.value = toggle.checked ? '1' : '0';
                       toggle.addEventListener('change', function() {
                           hidden.value = this.checked ? '1' : '0';
                       });
                   }
               });
               const fileInput = row.querySelector('input[type="file"]');
               const thumb = row.querySelector('.bk-slide-thumb');
               const existingInput = row.querySelector('input[name="home_slide_existing[]"]');
               if (fileInput && thumb) {
                   fileInput.addEventListener('change', function() {
                       const file = this.files && this.files[0];
                       if (file) {
                           thumb.src = URL.createObjectURL(file);
                           thumb.style.display = 'block';
                       }
                   });
               }
               const removePhoto = row.querySelector('.bk-slide-photo-remove');
               if (removePhoto) {
                   removePhoto.addEventListener('click', function() {
                       if (existingInput) {
                           existingInput.value = '';
                       }
                       if (fileInput) {
                           fileInput.value = '';
                       }
                       if (thumb) {
                           thumb.removeAttribute('src');
                           thumb.style.display = 'none';
                       }
                   });
               }
           }

           function addSlideRow() {
               const tr = document.createElement('tr');
               tr.className = 'bk-slide-row';
               tr.innerHTML = '<td><img class="bk-slide-thumb" alt="" style="display:none"><input type="file" name="home_slide_images[]" accept="image/jpeg,image/png,image/webp"><input type="hidden" name="home_slide_existing[]" value=""><button type="button" class="bk-slide-photo-remove">Remove Photo</button></td>'
                   + '<td><input type="text" name="home_slide_pre_title[]" value=""></td>'
                   + '<td><input type="text" name="home_slide_title[]" value=""></td>'
                   + '<td><textarea name="home_slide_description[]"></textarea></td>'
                   + '<td><div class="bk-slide-checks"><input type="hidden" name="home_slide_show_hero[]" value="1"><label class="bk-slide-switch"><input type="checkbox" class="bk-slide-toggle" data-hidden="home_slide_show_hero[]" checked><span></span><em>Hero</em></label><input type="hidden" name="home_slide_show_onboarding[]" value="1"><label class="bk-slide-switch"><input type="checkbox" class="bk-slide-toggle" data-hidden="home_slide_show_onboarding[]" checked><span></span><em>Onboarding</em></label></div></td>'
                   + '<td><button type="button" class="bk-slide-remove">Remove</button></td>';
               slideRows.appendChild(tr);
               bindSlideRow(tr);
           }

           function syncAdminCategories() {
               const values = Array.from(categoryList.querySelectorAll('.bk-admin-tag')).map(tag => tag.childNodes[0].textContent.trim()).filter(Boolean);
               categoryHidden.value = JSON.stringify(values);
           }

           window.bkAdminRemoveCategory = function(btn) {
               btn.closest('.bk-admin-tag').remove();
               syncAdminCategories();
           };
   
           function addAdminCategory() {
               const value = (categoryInput.value || '').trim();
               if (!value) return;
               const exists = Array.from(categoryList.querySelectorAll('.bk-admin-tag')).some(tag => tag.childNodes[0].textContent.trim().toLowerCase() === value.toLowerCase());
               if (exists) {
                   categoryInput.value = '';
                   return;
               }
               const tag = document.createElement('span');
               tag.className = 'bk-admin-tag';
               tag.innerHTML = value + '<button type="button" onclick="bkAdminRemoveCategory(this)">×</button>';
               categoryList.appendChild(tag);
               categoryInput.value = '';
               syncAdminCategories();
           }
   
           document.getElementById('bk-admin-add-category').addEventListener('click', addAdminCategory);
           categoryInput.addEventListener('keydown', function(e) {
               if (e.key === 'Enter') {
                   e.preventDefault();
                   addAdminCategory();
               }
           });
           featuredSearch.addEventListener('focus', function() {
               featuredPicker.classList.add('open');
           });
           featuredSearch.addEventListener('input', function() {
               const query = this.value.trim().toLowerCase();
               featuredMenu.querySelectorAll('.bk-admin-picker-option').forEach(option => {
                   option.style.display = !query || option.dataset.name.includes(query) ? '' : 'none';
               });
           });
           featuredMenu.querySelectorAll('.bk-admin-picker-option').forEach(option => {
               option.addEventListener('click', function() {
                   const selectedIds = getSelectedFeaturedIds();
                   const id = this.dataset.id;
                   const nextIds = selectedIds.includes(id)
                       ? selectedIds.filter(value => value !== id)
                       : selectedIds.concat(id);
                   setFeaturedSelection(nextIds);
                   featuredSearch.value = '';
                   featuredMenu.querySelectorAll('.bk-admin-picker-option').forEach(menuOption => {
                       menuOption.style.display = '';
                   });
               });
           });
           featuredSelected.addEventListener('click', function(e) {
               if (e.target.matches('button[data-remove-id]')) {
                   const removeId = e.target.getAttribute('data-remove-id');
                   const nextIds = getSelectedFeaturedIds().filter(id => id !== removeId);
                   setFeaturedSelection(nextIds);
               }
           });
           document.addEventListener('click', function(e) {
               if (!featuredPicker.contains(e.target)) {
                   featuredPicker.classList.remove('open');
               }
           });
           renderSelectedFeatured();
           slideRows.querySelectorAll('.bk-slide-row').forEach(bindSlideRow);
           addSlideRowBtn.addEventListener('click', addSlideRow);
           slideRows.addEventListener('click', function(e) {
               if (e.target.classList.contains('bk-slide-remove')) {
                   e.preventDefault();
                   e.target.closest('.bk-slide-row').remove();
               }
           });

           form.addEventListener('submit', function(e) {
               e.preventDefault();
               syncAdminCategories();
               const fd = new FormData(form);
               fd.append('action', 'bk_save_admin_settings');
               fd.append('nonce', '<?php echo esc_js(wp_create_nonce('bk_nonce')); ?>');
               const btn = form.querySelector('button[type="submit"]');
               btn.disabled = true;
               btn.textContent = 'Saving...';
               fetch(ajaxurl, { method: 'POST', body: fd })
                   .then(r => r.json())
                   .then(json => {
                       document.getElementById('bk-admin-settings-message').innerHTML = '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + json.data.message + '</div>';
                       btn.disabled = false;
                       btn.textContent = 'Save Admin Settings';
                   })
                   .catch(() => {
                       document.getElementById('bk-admin-settings-message').innerHTML = '<div class="bntm-notice bntm-notice-error">Failed to save admin settings.</div>';
                       btn.disabled = false;
                       btn.textContent = 'Save Admin Settings';
                });
           });
       })();
       </script>
       <?php
       return ob_get_clean();
   }
   
   function bk_get_dashboard_stats($business_id) {
       global $wpdb;
       $business_id = absint($business_id ?: bntm_get_current_business_id());
       $services_table = $wpdb->prefix . 'bk_services';
       $bookings_table = $wpdb->prefix . 'bk_bookings';
       
       // Total services
       $total_services = $wpdb->get_var($wpdb->prepare(
           "SELECT COUNT(*) FROM $services_table WHERE business_id = %d",
           $business_id
       ));
       
       // Total bookings
       $total_bookings = $wpdb->get_var($wpdb->prepare(
           "SELECT COUNT(*) FROM $bookings_table WHERE business_id = %d",
           $business_id
       ));
       
       // Monthly revenue
       $monthly_revenue = $wpdb->get_var($wpdb->prepare(
           "SELECT COALESCE(SUM(total), 0) FROM $bookings_table
            WHERE business_id = %d
            AND payment_status IN ('paid', 'verified')
            AND MONTH(created_at) = MONTH(CURRENT_DATE())
            AND YEAR(created_at) = YEAR(CURRENT_DATE())",
           $business_id
       ));
       
       // Pending bookings
       $pending_bookings = $wpdb->get_var($wpdb->prepare(
           "SELECT COUNT(*) FROM $bookings_table WHERE business_id = %d AND status = 'pending'",
           $business_id
       ));
       
       // Monthly bookings data (last 6 months) - each month independent
       $monthly_bookings_data = $wpdb->get_results($wpdb->prepare(
           "SELECT DATE_FORMAT(created_at, '%b %Y') as month, COUNT(*) as total
           FROM $bookings_table
           WHERE business_id = %d
           AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
           GROUP BY YEAR(created_at), MONTH(created_at)
           ORDER BY YEAR(created_at), MONTH(created_at)",
           $business_id
       ),
           ARRAY_A
       );
       
       // If no data, create empty months
       if (empty($monthly_bookings_data)) {
           $monthly_bookings_data = [];
           for ($i = 5; $i >= 0; $i--) {
               $monthly_bookings_data[] = [
                   'month' => date('M Y', strtotime("-$i months")),
                   'total' => 0
               ];
           }
       }
       
       // Service bookings data (Top 5 services)
       $service_bookings_data = $wpdb->get_results($wpdb->prepare(
           "SELECT s.name, COUNT(b.id) as total
           FROM $bookings_table b
           JOIN $services_table s ON b.service_id = s.id
           WHERE b.business_id = %d AND s.business_id = %d
           GROUP BY b.service_id, s.name
           ORDER BY total DESC
           LIMIT 5",
           $business_id,
           $business_id
       ),
           ARRAY_A
       );
       
       // Status data
       $status_data = $wpdb->get_results($wpdb->prepare(
           "SELECT status, COUNT(*) as count
           FROM $bookings_table
           WHERE business_id = %d
           GROUP BY status",
           $business_id
       ),
           ARRAY_A
       );
       
       return [
           'total_services' => intval($total_services),
           'total_bookings' => intval($total_bookings),
           'monthly_revenue' => floatval($monthly_revenue),
           'pending_bookings' => intval($pending_bookings),
           'monthly_bookings_data' => $monthly_bookings_data,
           'service_bookings_data' => $service_bookings_data ?: [],
           'status_data' => $status_data ?: []
       ];
   }
   function bk_services_tab($business_id) {
       global $wpdb;
       $table = $wpdb->prefix . 'bk_services';
       $services = $wpdb->get_results($wpdb->prepare(
           "SELECT * FROM $table WHERE business_id = %d ORDER BY name ASC",
           $business_id
       ));
       
       // Get service limit
       $limits = get_option('bntm_table_limits', []);
       $service_limit = isset($limits[$table]) ? $limits[$table] : 0;
       $current_services = count($services);
       $limit_text = $service_limit > 0 ? " ({$current_services}/{$service_limit})" : " ({$current_services})";
       $limit_reached = $service_limit > 0 && $current_services >= $service_limit;
       
       $nonce = wp_create_nonce('bk_nonce');
       $global_categories = bk_get_global_categories();
       $global_categories_json = wp_json_encode(array_values($global_categories));
       
       ob_start();
       ?>
       <script>
       var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
       var bkServiceCategories = <?php echo $global_categories_json ?: '[]'; ?>;
       </script>
       
       <div class="bntm-form-section" style="background: #f9fafb; ">
           <div class="bk-section-actions">
               <div>
                   <h3>Add New Service</h3>
                   <p>Use service photos to make the booking page and directory easier to scan.</p>
               </div>
               <button type="button" class="bntm-btn-primary" id="open-add-service-modal">Add Service</button>
           </div>
           
           <?php if ($limit_reached): ?>
               <div style="background: #fef3c7; border: 1px solid #fde047; padding: 15px; border-radius: 4px; margin-bottom: 15px;">
                   <strong>⚠️ Service Limit Reached:</strong> Maximum of <?php echo $service_limit; ?> services allowed. This is a warning-only limit, but consider upgrading your plan.
               </div>
           <?php endif; ?>
           <div id="service-message"></div>
       </div>
       <div class="bntm-form-section">
           <h3>Services<?php echo $limit_text; ?></h3>
           
           <?php if (empty($services)): ?>
               <p>No services yet. Add your first service to get started.</p>
           <?php else: ?>
           
           <div class="bntm-table-wrapper">
               <table class="bntm-table">
                   <thead>
                       <tr>
                           <th>Photo</th>
                           <th>Name</th>
                           <th>Category</th>
                           <th>Duration (min)</th>
                           <th>Price</th>
                           <th>Status</th>
                           <th>Actions</th>
                       </tr>
                   </thead>
                   <tbody>
                       <?php foreach ($services as $service): ?>
                           <tr data-service-id="<?php echo $service->id; ?>">
                               <td>
                                   <?php if (!empty($service->image_url)): ?>
                                       <img class="bk-service-thumb" src="<?php echo esc_url($service->image_url); ?>" alt="<?php echo esc_attr($service->name); ?>">
                                   <?php else: ?>
                                       <span class="bk-service-thumb bk-service-thumb-empty">No photo</span>
                                   <?php endif; ?>
                               </td>
                               <td><?php echo esc_html($service->name); ?></td>
                               <td><?php echo esc_html($service->category ?: 'Uncategorized'); ?></td>
                               <td><?php echo esc_html($service->duration); ?></td>
                               <td><?php echo bk_format_price($service->price); ?></td>
                               <td>
                                   <label class="bk-toggle">
                                       <input type="checkbox" 
                                              class="bk-toggle-status" 
                                              data-id="<?php echo $service->id; ?>"
                                              data-nonce="<?php echo $nonce; ?>"
                                              <?php checked($service->status, 'active'); ?>>
                                       <span class="bk-toggle-slider"></span>
                                   </label>
                                   <span class="status-text"><?php echo ucfirst($service->status); ?></span>
                               </td>
                               <td>
                                   <button class="bntm-btn-small bk-edit-service" data-id="<?php echo $service->id; ?>" data-nonce="<?php echo $nonce; ?>">Edit</button>
                                   <button class="bntm-btn-small bntm-btn-danger bk-delete-service" data-id="<?php echo $service->id; ?>" data-nonce="<?php echo $nonce; ?>">Delete</button>
                               </td>
                           </tr>
                       <?php endforeach; ?>
                   </tbody>
               </table>
            </div>
           <?php endif; ?>
       </div>
   
   
       <div id="add-service-modal" class="bk-modal">
           <div class="bk-modal-overlay"></div>
           <div class="bk-modal-content">
               <button class="bk-modal-close">&times;</button>
               <h2>Add Service</h2>
           <form id="add-service-form" class="bntm-form">
               <div class="bntm-form-group">
                   <label>Service Name *</label>
                   <input type="text" name="service_name" placeholder="e.g., Hair Cut, Massage" required>
               </div>
               
               <div class="bntm-form-row">
                   <div class="bntm-form-group">
                       <label>Duration (minutes) *</label>
                       <input type="number" name="duration" value="60" min="15" step="15" required>
                       <small>Must be in 15-minute intervals</small>
                   </div>
                   <div class="bntm-form-group">
                       <label>Price *</label>
                       <input type="number" name="price" value="0" min="0" step="0.01" required>
                   </div>
               </div>
               
               <div class="bntm-form-group">
                   <label>Description</label>
                   <textarea name="description" rows="3" placeholder="Service description"></textarea>
               </div>
   
                <div class="bntm-form-group">
                    <label>Category</label>
                    <select name="category">
                        <option value=""><?php echo empty($global_categories) ? 'No categories yet' : 'Select category'; ?></option>
                        <?php foreach ($global_categories as $category): ?>
                            <option value="<?php echo esc_attr($category); ?>"><?php echo esc_html($category); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small>Categories are managed in Admin Settings. <?php echo empty($global_categories) ? 'Add at least one activity there first.' : ''; ?></small>
                </div>
   
               <div class="bntm-form-group">
                   <label>Service Photo</label>
                   <input type="file" name="service_image" accept="image/jpeg,image/png,image/webp">
                   <small>JPG, PNG, or WebP. The upload is compressed and saved only if it is 500KB or less.</small>
               </div>
               
               <button type="submit" class="bntm-btn-primary" id="add-service-btn">
                   Add Service
               </button>
               <div id="add-service-message"></div>
           </form>
           </div>
       </div>
   
       <!-- Edit Service Modal -->
       <div id="edit-service-modal" class="bk-modal">
           <div class="bk-modal-overlay"></div>
           <div class="bk-modal-content">
               <button class="bk-modal-close">&times;</button>
               <h2>Edit Service</h2>
               
               <form id="edit-service-form" class="bntm-form">
                   <div class="bntm-form-group">
                       <label>Service Name *</label>
                       <input type="text" name="service_name" required>
                   </div>
                   
                   <div class="bntm-form-row">
                       <div class="bntm-form-group">
                           <label>Duration (minutes) *</label>
                           <input type="number" name="duration" min="15" step="15" required>
                           <small>Must be in 15-minute intervals</small>
                       </div>
                       <div class="bntm-form-group">
                           <label>Price *</label>
                           <input type="number" name="price" min="0" step="0.01" required>
                       </div>
                   </div>
                   
                   <div class="bntm-form-group">
                       <label>Description</label>
                       <textarea name="description" rows="3"></textarea>
                   </div>
   
                <div class="bntm-form-group">
                    <label>Category</label>
                    <select name="category">
                        <option value=""><?php echo empty($global_categories) ? 'No categories yet' : 'Select category'; ?></option>
                        <?php foreach ($global_categories as $category): ?>
                            <option value="<?php echo esc_attr($category); ?>"><?php echo esc_html($category); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small>Categories are managed in Admin Settings. <?php echo empty($global_categories) ? 'Add at least one activity there first.' : ''; ?></small>
                </div>
   
                   <div class="bntm-form-group">
                       <label>Service Photo</label>
                       <div id="edit-service-image-preview"></div>
                       <input type="file" name="service_image" accept="image/jpeg,image/png,image/webp">
                       <small>Leave blank to keep the current image. New images are compressed to max 500KB.</small>
                   </div>
                   
                   <input type="hidden" id="edit-service-id" name="service_id">
                   
                   <button type="submit" class="bntm-btn-primary">Save Changes</button>
                   <div id="edit-service-message"></div>
               </form>
           </div>
       </div>
   
       <style>
       .bk-toggle {
           position: relative;
           display: inline-block;
           width: 50px;
           height: 24px;
           margin-right: 10px;
       }
       .bk-toggle input {
           opacity: 0;
           width: 0;
           height: 0;
       }
       .bk-toggle-slider {
           position: absolute;
           cursor: pointer;
           top: 0;
           left: 0;
           right: 0;
           bottom: 0;
           background-color: #ccc;
           transition: .4s;
           border-radius: 24px;
       }
       .bk-toggle-slider:before {
           position: absolute;
           content: "";
           height: 18px;
           width: 18px;
           left: 3px;
           bottom: 3px;
           background-color: white;
           transition: .4s;
           border-radius: 50%;
       }
       .bk-toggle input:checked + .bk-toggle-slider {
           background-color: #059669;
       }
       .bk-toggle input:checked + .bk-toggle-slider:before {
           transform: translateX(26px);
       }
       .status-text {
           font-size: 14px;
           color: #6b7280;
       }
       .bk-section-actions {
           display: flex;
           align-items: center;
           justify-content: space-between;
           gap: 16px;
           flex-wrap: wrap;
       }
       .bk-section-actions h3,
       .bk-section-actions p {
           margin: 0;
       }
       .bk-section-actions p {
           color: #6b7280;
           font-size: 14px;
           margin-top: 4px;
       }
       .bk-service-thumb {
           width: 72px;
           height: 54px;
           object-fit: cover;
           border-radius: 8px;
           border: 1px solid #e5e7eb;
           display: inline-flex;
           align-items: center;
           justify-content: center;
           background: #f9fafb;
           color: #6b7280;
           font-size: 12px;
       }
       
       .bk-modal {
           display: none;
           position: fixed;
           top: 0;
           left: 0;
           width: 100%;
           height: 100%;
           z-index: 10000;
       }
       
       .bk-modal.active {
           display: block;
       }
       
       .bk-modal-overlay {
           position: absolute;
           top: 0;
           left: 0;
           width: 100%;
           height: 100%;
           background: rgba(0, 0, 0, 0.7);
           backdrop-filter: blur(4px);
       }
       
       .bk-modal-content {
           position: absolute;
           top: 50%;
           left: 50%;
           transform: translate(-50%, -50%);
           max-width: 600px;
           background: white;
           border-radius: 12px;
           box-shadow: 0 20px 60px rgba(0,0,0,0.3);
           max-height: 90vh;
           overflow-y: auto;
           overscroll-behavior: contain;
           padding: 30px;
       }
       .bk-modal-content::-webkit-scrollbar {
           width: 10px;
       }
       .bk-modal-content::-webkit-scrollbar-thumb {
           background: #cbd5e1;
           border-radius: 999px;
           border: 2px solid #fff;
       }
       .bk-modal-content::-webkit-scrollbar-track {
           background: #f8fafc;
           border-radius: 999px;
       }
       
       .bk-modal-close {
           position: absolute;
           top: 15px;
           right: 15px;
           background: #f3f4f6;
           border: none;
           width: 36px;
           height: 36px;
           border-radius: 50%;
           font-size: 24px;
           cursor: pointer;
           color: #6b7280;
           transition: all 0.2s;
       }
       
       .bk-modal-close:hover {
           background: #e5e7eb;
           color: #1f2937;
       }
       </style>
       
       <script>
       (function() {
           const addModal = document.getElementById('add-service-modal');
           const editModal = document.getElementById('edit-service-modal');
           const modalOverlay = editModal.querySelector('.bk-modal-overlay');
           const modalClose = editModal.querySelector('.bk-modal-close');
           const openAddModalBtn = document.getElementById('open-add-service-modal');
           const limitReached = <?php echo $limit_reached ? 'true' : 'false'; ?>;
           const serviceLimit = <?php echo $service_limit; ?>;
           function populateCategorySelect(select, selectedValue) {
               if (!select) return;
               const categories = Array.isArray(window.bkServiceCategories) ? window.bkServiceCategories : [];
               const initialLabel = categories.length ? 'Select category' : 'No categories yet';
               select.innerHTML = '';
               const placeholder = document.createElement('option');
               placeholder.value = '';
               placeholder.textContent = initialLabel;
               select.appendChild(placeholder);
               categories.forEach(function(category) {
                   const option = document.createElement('option');
                   option.value = category;
                   option.textContent = category;
                   if ((selectedValue || '') === category) {
                       option.selected = true;
                   }
                   select.appendChild(option);
               });
               if (!selectedValue) {
                   select.value = '';
               }
           }
           function openBkModal(modal) {
               modal.classList.add('active');
               document.body.style.overflow = 'hidden';
           }
           function closeBkModal(modal) {
               modal.classList.remove('active');
               document.body.style.overflow = '';
           }
           if (openAddModalBtn) {
               openAddModalBtn.addEventListener('click', function() {
                   const msg = document.getElementById('service-message');
                   msg.innerHTML = '';
                   if (limitReached) {
                       msg.innerHTML = '<div class="bntm-notice bntm-notice-error">You have reached the service limit (' + serviceLimit + '). Please upgrade your plan to add more services.</div>';
                       return;
                   }
                   document.getElementById('add-service-form').reset();
                   populateCategorySelect(document.querySelector('#add-service-form select[name="category"]'), '');
                   document.getElementById('add-service-message').innerHTML = '';
                   openBkModal(addModal);
               });
           }
           addModal.querySelector('.bk-modal-close').addEventListener('click', function() {
               closeBkModal(addModal);
           });
           addModal.querySelector('.bk-modal-overlay').addEventListener('click', function() {
               closeBkModal(addModal);
           });
           // Add service
   document.getElementById('add-service-form').addEventListener('submit', function(e) {
       e.preventDefault();
   
       const msg = document.getElementById('add-service-message');
       msg.innerHTML = ''; // Clear previous messages
   
       // Stop submission if limit reached
       if (limitReached) {
           msg.innerHTML = '<div class="bntm-notice bntm-notice-error">You have reached the service limit (' + serviceLimit + '). Please upgrade your plan to add more services.</div>';
           return;
       }
   
       const formData = new FormData(this);
       formData.append('action', 'bk_add_service');
       formData.append('nonce', '<?php echo $nonce; ?>');
   
       const btn = this.querySelector('button[type="submit"]');
       const originalText = btn.textContent;
       btn.disabled = true;
       btn.textContent = 'Adding...';
   
       fetch(ajaxurl, { method: 'POST', body: formData })
           .then(r => r.json())
           .then(json => {
               msg.innerHTML = '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + json.data.message + '</div>';
               if (json.success) {
                   setTimeout(() => location.reload(), 1500);
               } else {
                   btn.disabled = false;
                   btn.textContent = originalText;
               }
           })
           .catch(err => {
               msg.innerHTML = '<div class="bntm-notice bntm-notice-error">Error: ' + err.message + '</div>';
               btn.disabled = false;
               btn.textContent = originalText;
           });
   });
   
           
           // Edit service
            document.querySelectorAll('.bk-edit-service').forEach(btn => {
                btn.addEventListener('click', function() {
                    const serviceId = this.dataset.id;
                    const nonce = this.dataset.nonce;
                    
                    const formData = new FormData();
                    formData.append('action', 'bk_edit_service');
                    formData.append('service_id', serviceId);
                    formData.append('nonce', nonce);
                    
                    fetch(ajaxurl, {method: 'POST', body: formData})
                    .then(r => r.json())
                    .then(json => {
                        if (json.success) {
                            const service = json.data;
                            document.getElementById('edit-service-id').value = service.id;
                            
                            // Query inputs within the edit modal
                            const editForm = editModal.querySelector('#edit-service-form');
                            editForm.querySelector('input[name="service_name"]').value = service.name;
                            populateCategorySelect(editForm.querySelector('select[name="category"]'), service.category || '');
                            editForm.querySelector('input[name="duration"]').value = service.duration;
                            editForm.querySelector('input[name="price"]').value = service.price;
                            editForm.querySelector('textarea[name="description"]').value = service.description;
                            document.getElementById('edit-service-image-preview').innerHTML = service.image_url
                               ? '<img class="bk-service-thumb" style="width:120px;height:84px;margin-bottom:10px;" src="' + service.image_url + '" alt="">'
                               : '<span class="bk-service-thumb bk-service-thumb-empty" style="width:120px;height:84px;margin-bottom:10px;">No photo</span>';
                            
                            openBkModal(editModal);
                        } else {
                            alert(json.data.message);
                        }
                    });
                });
            });
           
           // Close modal
           modalClose.addEventListener('click', function() {
               closeBkModal(editModal);
           });
           
           modalOverlay.addEventListener('click', function() {
               closeBkModal(editModal);
           });
           
           // Update service
           document.getElementById('edit-service-form').addEventListener('submit', function(e) {
               e.preventDefault();
               
               const formData = new FormData(this);
               formData.append('action', 'bk_update_service');
               formData.append('nonce', '<?php echo $nonce; ?>');
               
               const btn = this.querySelector('button[type="submit"]');
               const originalText = btn.textContent;
               btn.disabled = true;
               btn.textContent = 'Saving...';
               
               fetch(ajaxurl, {method: 'POST', body: formData})
               .then(r => r.json())
               .then(json => {
                   const msg = document.getElementById('edit-service-message');
                   msg.innerHTML = '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + json.data.message + '</div>';
                   
                   if (json.success) {
                       setTimeout(() => {
                           location.reload();
                       }, 1500);
                   } else {
                       btn.disabled = false;
                       btn.textContent = originalText;
                   }
               })
               .catch(err => {
                   const msg = document.getElementById('edit-service-message');
                   msg.innerHTML = '<div class="bntm-notice bntm-notice-error">Error: ' + err.message + '</div>';
                   btn.disabled = false;
                   btn.textContent = originalText;
               });
           });
           
           // Toggle service status
           document.querySelectorAll('.bk-toggle-status').forEach(toggle => {
               toggle.addEventListener('change', function() {
                   const formData = new FormData();
                   formData.append('action', 'bk_toggle_service_status');
                   formData.append('service_id', this.dataset.id);
                   formData.append('status', this.checked ? 'active' : 'inactive');
                   formData.append('nonce', this.dataset.nonce);
                   
                   fetch(ajaxurl, {method: 'POST', body: formData})
                   .then(r => r.json())
                   .then(json => {
                       if (!json.success) {
                           alert(json.data.message);
                           this.checked = !this.checked;
                       } else {
                           const statusText = this.closest('td').querySelector('.status-text');
                           statusText.textContent = this.checked ? 'Active' : 'Inactive';
                       }
                   });
               });
           });
           
           // Delete service
           document.querySelectorAll('.bk-delete-service').forEach(btn => {
               btn.addEventListener('click', function() {
                   if (!confirm('Are you sure you want to delete this service?')) return;
                   
                   const serviceId = this.dataset.id;
                   const nonce = this.dataset.nonce;
                   
                   const formData = new FormData();
                   formData.append('action', 'bk_delete_service');
                   formData.append('service_id', serviceId);
                   formData.append('nonce', nonce);
                   
                   fetch(ajaxurl, {method: 'POST', body: formData})
                   .then(r => r.json())
                   .then(json => {
                       if (json.success) {
                           location.reload();
                       } else {
                           alert(json.data.message);
                       }
                   });
               });
           });
           populateCategorySelect(document.querySelector('#add-service-form select[name="category"]'), '');
           populateCategorySelect(document.querySelector('#edit-service-form select[name="category"]'), '');
       })();
       </script>
       <?php
       return ob_get_clean();
   }
   
   function bk_bookings_tab($business_id) {
       global $wpdb;
       $bookings_table = $wpdb->prefix . 'bk_bookings';
       $services_table = $wpdb->prefix . 'bk_services';
       $per_page = 10;
       $current_page = max(1, intval($_GET['bk_page'] ?? 1));
       $offset = ($current_page - 1) * $per_page;
       $total_bookings = intval($wpdb->get_var($wpdb->prepare(
           "SELECT COUNT(*) FROM $bookings_table WHERE business_id = %d",
           $business_id
       )));
       $total_pages = max(1, (int) ceil($total_bookings / $per_page));
   
       $bookings = $wpdb->get_results($wpdb->prepare(
           "SELECT b.*, s.name as service_name 
            FROM $bookings_table b
            LEFT JOIN $services_table s ON b.service_id = s.id
            WHERE b.business_id = %d
            ORDER BY b.booking_date DESC, b.start_time DESC
            LIMIT %d OFFSET %d",
           $business_id,
           $per_page,
           $offset
       ));
       
       $nonce = wp_create_nonce('bk_nonce');
       
       ob_start();
       ?>
       <script>
       var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
       </script>
       
       <div class="bntm-form-section">
           <h3>All Bookings (<?php echo esc_html($total_bookings); ?>)</h3>
           <?php if (empty($bookings)): ?>
               <p>No bookings yet.</p>
           <?php else: ?>
           <div class="bntm-table-wrapper">
               <table class="bntm-table">
                   <thead>
                       <tr>
                           <th>Service</th>
                           <th>Reference</th>
                           <th>Customer</th>
                           <th>Date & Time</th>
                           <th>Total</th>
                           <th>Status</th>
                           <th>Payment</th>
                           <th>Actions</th>
                       </tr>
                   </thead>
                   <tbody>
                       <?php foreach ($bookings as $booking): 
                           $view_url = add_query_arg('id', $booking->rand_id, get_permalink(get_page_by_path('booking-transaction')));
                       ?>
                           <tr>
                               <td><?php echo esc_html($booking->service_name); ?></td>
                               <td><strong><?php echo esc_html(!empty($booking->group_rand_id) ? $booking->group_rand_id : $booking->rand_id); ?></strong></td>
                               <td>
                                   <strong><?php echo esc_html($booking->customer_name); ?></strong><br>
                                   <small><?php echo esc_html($booking->customer_email); ?></small>
                               </td>
                               <td>
                                   <?php echo date('M d, Y', strtotime($booking->booking_date)); ?><br>
                                   <small><?php echo date('H:i', strtotime($booking->start_time)); ?> - <?php echo date('H:i', strtotime($booking->end_time)); ?></small>
                               </td>
                               <td><?php echo bk_format_price($booking->total); ?></td>
                               <td>
                                   <select class="bk-booking-status" data-booking-id="<?php echo esc_attr($booking->rand_id); ?>" data-nonce="<?php echo $nonce; ?>">
                                       <option value="pending" <?php selected($booking->status, 'pending'); ?>>Pending</option>
                                       <option value="confirmed" <?php selected($booking->status, 'confirmed'); ?>>Confirmed</option>
                                       <!--<option value="completed" <?php selected($booking->status, 'completed'); ?>>Completed</option>-->
                                       <option value="cancelled" <?php selected($booking->status, 'cancelled'); ?>>Cancelled</option>
                                   </select>
                               </td>
                               <td>
                                   <span class="payment-badge payment-<?php echo esc_attr($booking->payment_status); ?>">
                                       <?php echo ucfirst($booking->payment_status); ?>
                                   </span>
                               </td>
                               <td>
                                   <a href="<?php echo esc_url($view_url); ?>" class="bntm-btn-small">View</a>
                               </td>
                           </tr>
                       <?php endforeach; ?>
                   </tbody>
               </table>
           </div>
           <?php if ($total_pages > 1): ?>
               <div class="bk-pagination">
                   <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                       <a class="bk-page-link <?php echo $i === $current_page ? 'active' : ''; ?>" href="<?php echo esc_url(add_query_arg(['tab' => 'bookings', 'bk_page' => $i])); ?>"><?php echo esc_html($i); ?></a>
                   <?php endfor; ?>
               </div>
           <?php endif; ?>
           <?php endif; ?>
       </div>
   
       <style>
       .payment-badge {
           display: inline-block;
           padding: 4px 12px;
           border-radius: 12px;
           font-size: 12px;
           font-weight: 600;
       }
       .payment-badge.payment-paid {
           background: #d1fae5;
           color: #065f46;
       }
       .payment-badge.payment-unpaid {
           background: #fef3c7;
           color: #92400e;
       }
       .payment-badge.payment-verified {
           background: #d1fae5;
           color: #065f46;
       }
       .bk-pagination {
           display: flex;
           gap: 8px;
           justify-content: center;
           margin-top: 18px;
           flex-wrap: wrap;
       }
       .bk-page-link {
           min-width: 38px;
           height: 38px;
           display: inline-flex;
           align-items: center;
           justify-content: center;
           border: 1px solid #e5e7eb;
           border-radius: 8px;
           text-decoration: none;
           color: #374151;
           background: #fff;
           font-weight: 600;
       }
       .bk-page-link.active {
           background: var(--bntm-primary, #3b82f6);
           border-color: var(--bntm-primary, #3b82f6);
           color: #fff;
       }
       </style>
       
       <script>
       (function() {
           document.querySelectorAll('.bk-booking-status').forEach(select => {
               select.addEventListener('change', function() {
                   const bookingId = this.getAttribute('data-booking-id');
                   const newStatus = this.value;
                   const nonce = this.getAttribute('data-nonce');
                   
                   const formData = new FormData();
                   formData.append('action', 'bk_update_booking_status');
                   formData.append('booking_id', bookingId);
                   formData.append('status', newStatus);
                   formData.append('nonce', nonce);
                   
                   fetch(ajaxurl, {
                       method: 'POST',
                       body: formData
                   })
                   .then(r => r.json())
                   .then(json => {
                       if (json.success) {
                           alert('Booking status updated!');
                       } else {
                           alert('Failed to update status');
                       }
                   });
               });
           });
       })();
       </script>
       <?php
       return ob_get_clean();
   }

   function bk_render_invoice_table($invoices, $show_business = false) {
    return bk_render_invoice_table_v2($invoices, $show_business, false);
}

function bk_render_invoice_table_v2($invoices, $show_business = false, $allow_updates = false) {
       global $wpdb;
       $bookings_table = $wpdb->prefix . 'bk_bookings';
       $services_table = $wpdb->prefix . 'bk_services';
       $remittance_nonce = wp_create_nonce('bk_remittance_nonce');
       if (empty($invoices)) {
           return '<p>No invoices found.</p>';
       }
       ob_start();
       ?>
       <div class="bntm-table-wrapper">
           <table class="bntm-table bk-invoice-table">
               <thead>
                   <tr>
                       <th>Invoice</th>
                       <?php if ($show_business): ?><th>Business</th><?php endif; ?>
                       <th>Period</th>
                       <th>Gross</th>
                       <th>Net</th>
                       <th>Status</th>
                       <th>Reference</th>
                       <th>Actions</th>
                   </tr>
               </thead>
               <tbody>
                   <?php foreach ($invoices as $invoice): ?>
                       <?php
                       $invoice_bookings = [];
                       $bank_details = bk_get_remittance_bank_details($invoice->business_id);
                       $invoice_booking_ids = json_decode($invoice->booking_ids ?: '[]', true);
                       if (!empty($invoice_booking_ids)) {
                           $placeholders = implode(',', array_fill(0, count($invoice_booking_ids), '%d'));
                           $invoice_bookings = $wpdb->get_results($wpdb->prepare(
                               "SELECT b.*, s.name AS service_name
                                FROM {$bookings_table} b
                                LEFT JOIN {$services_table} s ON b.service_id = s.id
                                WHERE b.id IN ({$placeholders})
                                ORDER BY b.booking_date ASC, b.start_time ASC",
                               ...array_map('intval', $invoice_booking_ids)
                           ));
                       }
                       ?>
                       <tr>
                           <td><strong><?php echo esc_html($invoice->rand_id); ?></strong></td>
                           <?php if ($show_business): ?><td><?php echo esc_html(bk_get_business_name($invoice->business_id)); ?></td><?php endif; ?>
                           <td><?php echo esc_html(date_i18n('M j, Y', strtotime($invoice->period_start)) . ' - ' . date_i18n('M j, Y', strtotime($invoice->period_end))); ?></td>
                           <td><?php echo esc_html(bk_format_price($invoice->gross_total)); ?></td>
                           <td><strong><?php echo esc_html(bk_format_price($invoice->net_total)); ?></strong></td>
                           <td><span class="bk-payment-badge payment-<?php echo esc_attr($invoice->status); ?>"><?php echo esc_html(ucfirst($invoice->status)); ?></span></td>
                           <td><?php echo esc_html($invoice->release_reference ?: 'Pending'); ?></td>
                           <td class="bk-invoice-actions">
                               <button type="button" class="bntm-btn-secondary" onclick="bkPrintInvoice('bk-remit-print-<?php echo esc_js($invoice->rand_id); ?>')">View</button>
                               <?php if ($allow_updates): ?>
                               <button type="button" class="bntm-btn-primary bk-open-invoice-update" data-target="bk-remit-update-<?php echo esc_attr($invoice->id); ?>">Update</button>
                               <button type="button" class="bntm-btn-danger bk-delete-invoice" data-remittance-id="<?php echo esc_attr($invoice->id); ?>" data-nonce="<?php echo esc_attr($remittance_nonce); ?>">Delete</button>
                               <?php endif; ?>
                           </td>
                       </tr>
                       <tr class="bk-invoice-detail-row">
                           <td colspan="<?php echo $show_business ? '8' : '7'; ?>">
                               <?php if ($allow_updates): ?>
                               <div class="bk-invoice-update-panel" id="bk-remit-update-<?php echo esc_attr($invoice->id); ?>" style="display:none;">
                                   <div class="bntm-form-row">
                                       <div class="bntm-form-group">
                                           <label>Status</label>
                                           <select class="bk-remit-status" data-remittance-id="<?php echo esc_attr($invoice->id); ?>">
                                               <option value="pending" <?php selected($invoice->status, 'pending'); ?>>Pending</option>
                                               <option value="processing" <?php selected($invoice->status, 'processing'); ?>>Processing</option>
                                               <option value="released" <?php selected($invoice->status, 'released'); ?>>Released</option>
                                           </select>
                                       </div>
                                       <div class="bntm-form-group">
                                           <label>Reference Number</label>
                                           <input type="text" class="bk-remit-reference" data-remittance-id="<?php echo esc_attr($invoice->id); ?>" value="<?php echo esc_attr($invoice->release_reference); ?>">
                                       </div>
                                   </div>
                                   <div class="bntm-form-group">
                                       <label>Breakdown</label>
                                       <textarea class="bk-remit-breakdown" data-remittance-id="<?php echo esc_attr($invoice->id); ?>" rows="3"><?php echo esc_textarea($invoice->breakdown); ?></textarea>
                                   </div>
                                   <button type="button" class="bntm-btn-primary bk-save-invoice-update" data-remittance-id="<?php echo esc_attr($invoice->id); ?>" data-nonce="<?php echo esc_attr($remittance_nonce); ?>">Save Update</button>
                               </div>
                               <?php endif; ?>
                               <div class="bk-remittance-print" id="bk-remit-print-<?php echo esc_attr($invoice->rand_id); ?>" style="display:none;">
                                   <div class="bk-remittance-invoice-head">
                                       <div>
                                           <h4>Remittance Invoice</h4>
                                           <p>Invoice #: <?php echo esc_html($invoice->rand_id); ?></p>
                                           <p>Business: <?php echo esc_html(bk_get_business_name($invoice->business_id)); ?></p>
                                           <p>Period: <?php echo esc_html(date_i18n('F j, Y', strtotime($invoice->period_start)) . ' - ' . date_i18n('F j, Y', strtotime($invoice->period_end))); ?></p>
                                       </div>
                                       <div>
                                           <p>Date Generated: <?php echo esc_html(date_i18n('F j, Y', strtotime($invoice->created_at))); ?></p>
                                           <p>Status: <?php echo esc_html(ucfirst($invoice->status)); ?></p>
                                           <p>Reference: <?php echo esc_html($invoice->release_reference ?: 'Pending'); ?></p>
                                       </div>
                                   </div>
                                   <table class="bk-transaction-table">
                                       <thead>
                                           <tr><td><strong>Service</strong></td><td><strong>Schedule</strong></td><td><strong>Total</strong></td></tr>
                                       </thead>
                                       <tbody>
                                           <?php foreach ($invoice_bookings as $booking_row): ?>
                                           <tr>
                                               <td><?php echo esc_html($booking_row->service_name ?: 'Booking'); ?></td>
                                               <td><?php echo esc_html(date_i18n('M j, Y', strtotime($booking_row->booking_date)) . ' - ' . date_i18n('g:i A', strtotime($booking_row->start_time))); ?></td>
                                               <td><?php echo esc_html(bk_format_price($booking_row->total)); ?></td>
                                           </tr>
                                           <?php endforeach; ?>
                                       </tbody>
                                       <tfoot>
                                           <tr><td colspan="2"><strong>Gross Total</strong></td><td><?php echo esc_html(bk_format_price($invoice->gross_total)); ?></td></tr>
                                           <tr><td colspan="2"><strong>Less <?php echo esc_html(number_format($invoice->percentage_fee_rate, 2)); ?>% Fee</strong></td><td>-<?php echo esc_html(bk_format_price($invoice->percentage_fee_amount)); ?></td></tr>
                                           <tr><td colspan="2"><strong>Less Platform Fee</strong></td><td>-<?php echo esc_html(bk_format_price($invoice->platform_fee_amount)); ?></td></tr>
                                           <tr><td colspan="2"><strong>Net Remittance</strong></td><td><strong><?php echo esc_html(bk_format_price($invoice->net_total)); ?></strong></td></tr>
                                       </tfoot>
                                   </table>
                                   <div class="bk-remittance-bank-box">
                                       <h5>Bank Remittance Details</h5>
                                       <?php if (!empty($bank_details['bank_name'])): ?><p>Bank: <?php echo esc_html($bank_details['bank_name']); ?></p><?php endif; ?>
                                       <?php if (!empty($bank_details['account_name'])): ?><p>Account Name: <?php echo esc_html($bank_details['account_name']); ?></p><?php endif; ?>
                                       <?php if (!empty($bank_details['account_number'])): ?><p>Account Number: <?php echo esc_html($bank_details['account_number']); ?></p><?php endif; ?>
                                       <?php if (!empty($bank_details['branch'])): ?><p>Branch: <?php echo esc_html($bank_details['branch']); ?></p><?php endif; ?>
                                       <?php if (!empty($bank_details['notes'])): ?><p>Notes: <?php echo nl2br(esc_html($bank_details['notes'])); ?></p><?php endif; ?>
                                   </div>
                                   <?php if (!empty($invoice->breakdown)): ?>
                                   <p style="margin-top:16px;"><strong>Breakdown:</strong><br><?php echo nl2br(esc_html($invoice->breakdown)); ?></p>
                                   <?php endif; ?>
                               </div>
                           </td>
                       </tr>
                   <?php endforeach; ?>
               </tbody>
           </table>
       </div>
       <?php
       return ob_get_clean();
   }

   function bk_invoices_tab($business_id) {
       global $wpdb;
       $remittances_table = $wpdb->prefix . 'bk_remittances';
       $invoices = $wpdb->get_results($wpdb->prepare(
           "SELECT * FROM {$remittances_table} WHERE business_id = %d ORDER BY period_end DESC, created_at DESC",
           absint($business_id)
       ));
       ob_start();
       ?>
       <script>var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';</script>
       <?php echo bk_get_invoice_script_helpers(); ?>
       <div class="bntm-form-section">
           <h3>Invoices</h3>
           <p>All remittance invoices generated for your business.</p>
           <?php echo bk_render_invoice_table_v2($invoices, false, false); ?>
       </div>
       <?php
       return ob_get_clean();
   }

   function bk_remittance_tab() {
       global $wpdb;
       $remittances_table = $wpdb->prefix . 'bk_remittances';
       $profiles_table = $wpdb->prefix . 'bk_business_profiles';
       $remittance_nonce = wp_create_nonce('bk_remittance_nonce');
       $selected_business = absint($_GET['remit_business'] ?? 0);
       $selected_start = sanitize_text_field($_GET['remit_start'] ?? '');
       $selected_end = sanitize_text_field($_GET['remit_end'] ?? '');
       $where = ["1=1"];
       $params = [];
       if ($selected_business) {
           $where[] = "business_id = %d";
           $params[] = $selected_business;
       }
       if ($selected_start !== '') {
           $where[] = "period_start >= %s";
           $params[] = $selected_start;
       }
       if ($selected_end !== '') {
           $where[] = "period_end <= %s";
           $params[] = $selected_end;
       }
       $sql = "SELECT * FROM {$remittances_table} WHERE " . implode(' AND ', $where) . " ORDER BY period_end DESC, created_at DESC";
       $invoices = !empty($params) ? $wpdb->get_results($wpdb->prepare($sql, ...$params)) : $wpdb->get_results($sql);
       $business_options = $wpdb->get_col("SELECT business_id FROM {$profiles_table} WHERE business_id > 0 ORDER BY business_id ASC");
       
       ob_start();
       ?>
       <script>var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';</script>
       <?php echo bk_get_invoice_script_helpers(); ?>
       <div class="bntm-form-section">
           <h3>Remittance</h3>
           <p>Generate remittance invoices only when you choose a business and date range.</p>
           <div id="bk-remittance-message"></div>
           <form id="bk-remittance-generate-form" class="bntm-form" style="margin-bottom:24px;">
               <div class="bntm-form-row">
                   <div class="bntm-form-group">
                       <label>Business</label>
                       <select name="business_id" required>
                           <option value="">Select business</option>
                           <?php foreach ($business_options as $business_option): ?>
                           <option value="<?php echo esc_attr($business_option); ?>"><?php echo esc_html(bk_get_business_name($business_option)); ?></option>
                           <?php endforeach; ?>
                       </select>
                   </div>
                   <div class="bntm-form-group">
                       <label>Date Start</label>
                       <input type="date" name="period_start" required>
                   </div>
                   <div class="bntm-form-group">
                       <label>Date End</label>
                       <input type="date" name="period_end" required>
                   </div>
               </div>
               <button type="submit" class="bntm-btn-primary">Generate Invoice</button>
           </form>
           <form method="get" class="bntm-form" style="margin-bottom:20px;">
               <input type="hidden" name="tab" value="remittance">
               <div class="bntm-form-row">
                   <div class="bntm-form-group">
                       <label>Business</label>
                       <select name="remit_business">
                           <option value="">All Businesses</option>
                           <?php foreach ($business_options as $business_option): ?>
                           <option value="<?php echo esc_attr($business_option); ?>" <?php selected($selected_business, intval($business_option)); ?>><?php echo esc_html(bk_get_business_name($business_option)); ?></option>
                           <?php endforeach; ?>
                       </select>
                   </div>
                   <div class="bntm-form-group">
                       <label>Date Start</label>
                       <input type="date" name="remit_start" value="<?php echo esc_attr($selected_start); ?>">
                   </div>
                   <div class="bntm-form-group">
                       <label>Date End</label>
                       <input type="date" name="remit_end" value="<?php echo esc_attr($selected_end); ?>">
                   </div>
               </div>
               <button type="submit" class="bntm-btn-primary">Filter Invoices</button>
           </form>
           <?php echo bk_render_invoice_table_v2($invoices, true, true); ?>
       </div>
       <style>
       .bk-invoice-table td{vertical-align:top}
       .bk-invoice-actions{display:flex;gap:8px;flex-wrap:wrap}
       .bk-invoice-detail-row td{background:#f8fafc}
       .bk-invoice-update-panel{margin-bottom:16px;padding:16px;background:#fff;border:1px solid #e5e7eb;border-radius:12px}
       .payment-processing{background:#dbeafe;color:#1d4ed8}
       .payment-released{background:#dcfce7;color:#166534}
       .payment-pending{background:#fef3c7;color:#92400e}
       .bk-remittance-list{display:grid;gap:18px;margin-top:18px}
       .bk-remittance-card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:20px}
       .bk-remittance-head{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap;margin-bottom:16px}
       .bk-remittance-title{font-size:18px;color:#111827}
       .bk-remittance-meta{font-size:13px;color:#6b7280;margin-top:4px}
       .bk-remittance-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
       .bk-remittance-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:18px}
       .bk-remittance-grid div{background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;padding:14px}
       .bk-remittance-grid span{display:block;font-size:12px;color:#6b7280;margin-bottom:6px}
       .bk-remittance-grid strong{font-size:18px;color:#111827}
       .bk-remittance-net{color:var(--bntm-primary,#3b82f6)}
       .bk-remittance-print{border-top:1px solid #e5e7eb;padding-top:16px}
       .bk-remittance-invoice-head{display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:16px}
       .bk-remittance-invoice-head h4{margin:0 0 6px}
       .bk-remittance-invoice-head p{margin:2px 0;color:#4b5563}
       .bk-remittance-bank-box{margin-top:16px;padding:14px;border:1px solid #e5e7eb;border-radius:12px;background:#fff}
       .bk-remittance-bank-box h5{margin:0 0 10px}
       .bk-remittance-bank-box p{margin:4px 0;color:#374151}
       @media print{
           body *{visibility:hidden}
           .bk-remittance-print,.bk-remittance-print *{visibility:visible}
           .bk-remittance-print{position:absolute;left:0;top:0;width:100%;background:#fff;padding:24px}
       }
       </style>
       <script>
       document.getElementById('bk-remittance-generate-form').addEventListener('submit', function(e){
           e.preventDefault();
           var fd = new FormData(this);
           fd.append('action', 'bk_generate_remittance_invoice');
           fd.append('nonce', '<?php echo esc_js($remittance_nonce); ?>');
           fetch(ajaxurl, {method:'POST', body:fd})
               .then(function(r){ return r.json(); })
               .then(function(json){
                   document.getElementById('bk-remittance-message').innerHTML = '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + json.data.message + '</div>';
                   if (json.success) {
                       setTimeout(function(){ location.reload(); }, 900);
                   }
               });
       });
       document.querySelectorAll('.bk-open-invoice-update').forEach(function(btn){
           btn.addEventListener('click', function(){
               var panel = document.getElementById(this.dataset.target);
               if (panel) {
                   panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
               }
           });
       });
       document.querySelectorAll('.bk-save-invoice-update').forEach(function(btn){
           btn.addEventListener('click', function(){
               var id = this.dataset.remittanceId;
               var fd = new FormData();
               fd.append('action', 'bk_update_remittance_invoice');
               fd.append('nonce', this.dataset.nonce);
               fd.append('remittance_id', id);
               fd.append('status', document.querySelector('.bk-remit-status[data-remittance-id="' + id + '"]').value);
               fd.append('release_reference', document.querySelector('.bk-remit-reference[data-remittance-id="' + id + '"]').value);
               fd.append('breakdown', document.querySelector('.bk-remit-breakdown[data-remittance-id="' + id + '"]').value);
               fetch(ajaxurl, {method:'POST', body:fd})
                   .then(function(r){ return r.json(); })
                   .then(function(json){
                       document.getElementById('bk-remittance-message').innerHTML = '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + json.data.message + '</div>';
                       if (json.success) {
                           setTimeout(function(){ location.reload(); }, 700);
                       }
                   });
           });
       });
       document.querySelectorAll('.bk-delete-invoice').forEach(function(btn){
           btn.addEventListener('click', function(){
               if (!confirm('Delete this invoice?')) { return; }
               var fd = new FormData();
               fd.append('action', 'bk_delete_remittance_invoice');
               fd.append('nonce', this.dataset.nonce);
               fd.append('remittance_id', this.dataset.remittanceId);
               fetch(ajaxurl, {method:'POST', body:fd})
                   .then(function(r){ return r.json(); })
                   .then(function(json){
                       document.getElementById('bk-remittance-message').innerHTML = '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + json.data.message + '</div>';
                       if (json.success) {
                           setTimeout(function(){ location.reload(); }, 700);
                       }
                   });
           });
       });
       </script>
       <?php
       return ob_get_clean();
   }

   function bk_generate_remittance_invoice_handler() {
       check_ajax_referer('bk_remittance_nonce', 'nonce');
       if (!current_user_can('manage_options')) {
           wp_send_json_error(['message' => 'Unauthorized']);
       }
       global $wpdb;
       $bookings_table = $wpdb->prefix . 'bk_bookings';
       $services_table = $wpdb->prefix . 'bk_services';
       $remittances_table = $wpdb->prefix . 'bk_remittances';
       $business_id = absint($_POST['business_id'] ?? 0);
       $period_start = sanitize_text_field($_POST['period_start'] ?? '');
       $period_end = sanitize_text_field($_POST['period_end'] ?? '');
       if (!$business_id || !$period_start || !$period_end) {
           wp_send_json_error(['message' => 'Missing remittance details.']);
       }
       if ($period_start > $period_end) {
           wp_send_json_error(['message' => 'Date start must be earlier than date end.']);
       }

       $existing = $wpdb->get_var($wpdb->prepare(
           "SELECT id FROM {$remittances_table} WHERE business_id = %d AND period_start = %s AND period_end = %s LIMIT 1",
           $business_id,
           $period_start,
           $period_end
       ));
       if ($existing) {
           wp_send_json_error(['message' => 'An invoice already exists for this release period.']);
       }

       $frequency_days = bk_get_remittance_frequency_days();
       $fee_percent = max(0, floatval(bk_get_shared_setting('bk_remittance_percentage_fee', '0')));
       $platform_fee = max(0, floatval(bk_get_shared_setting('bk_remittance_platform_fee', '0')));
       $existing_remittances = $wpdb->get_results("SELECT booking_ids FROM {$remittances_table}");
       $used_booking_ids = [];
       foreach ((array) $existing_remittances as $remittance) {
           $decoded_ids = json_decode($remittance->booking_ids ?: '[]', true);
           if (is_array($decoded_ids)) {
               foreach ($decoded_ids as $booking_id) {
                   $used_booking_ids[] = intval($booking_id);
               }
           }
       }
       $bookings = $wpdb->get_results($wpdb->prepare(
           "SELECT b.*, s.name AS service_name
            FROM {$bookings_table} b
            LEFT JOIN {$services_table} s ON b.service_id = s.id
            WHERE b.business_id = %d
            AND b.payment_status IN ('paid', 'verified')
            AND b.booking_date BETWEEN %s AND %s
            ORDER BY b.booking_date ASC, b.start_time ASC",
           $business_id,
           $period_start,
           $period_end
       ));
       $booking_ids = [];
       $booking_refs = [];
       $gross_total = 0;
       foreach ((array) $bookings as $booking) {
           if (in_array(intval($booking->id), $used_booking_ids, true)) {
               continue;
           }
           $booking_ids[] = intval($booking->id);
           $booking_refs[] = !empty($booking->group_rand_id) ? $booking->group_rand_id : $booking->rand_id;
           $gross_total += floatval($booking->total);
       }
       $booking_refs = array_values(array_unique($booking_refs));
       if (empty($booking_ids)) {
           wp_send_json_error(['message' => 'No eligible paid bookings found for this invoice period.']);
       }

       $percentage_fee_amount = round($gross_total * ($fee_percent / 100), 2);
       $platform_fee_amount = round($platform_fee, 2);
       $net_total = max(0, round($gross_total - $percentage_fee_amount - $platform_fee_amount, 2));
       $result = $wpdb->insert($remittances_table, [
           'rand_id' => bk_generate_remittance_id(),
           'business_id' => $business_id,
           'frequency_days' => $frequency_days,
           'period_start' => $period_start,
           'period_end' => $period_end,
           'booking_ids' => wp_json_encode($booking_ids),
           'booking_refs' => wp_json_encode($booking_refs),
           'gross_total' => $gross_total,
           'percentage_fee_rate' => $fee_percent,
           'percentage_fee_amount' => $percentage_fee_amount,
           'platform_fee_amount' => $platform_fee_amount,
           'net_total' => $net_total,
           'release_reference' => '',
           'breakdown' => '',
           'status' => 'pending',
           'created_at' => current_time('mysql'),
       ], ['%s','%d','%d','%s','%s','%s','%s','%f','%f','%f','%f','%f','%s','%s','%s','%s']);
       if (!$result) {
           wp_send_json_error(['message' => 'Failed to generate the remittance invoice.']);
       }
       wp_send_json_success(['message' => 'Remittance invoice generated successfully.']);
   }

   function bk_update_remittance_invoice_handler() {
       check_ajax_referer('bk_remittance_nonce', 'nonce');
       if (!current_user_can('manage_options')) {
           wp_send_json_error(['message' => 'Unauthorized']);
       }
       global $wpdb;
       $remittances_table = $wpdb->prefix . 'bk_remittances';
       $remittance_id = absint($_POST['remittance_id'] ?? 0);
       $status = sanitize_text_field($_POST['status'] ?? 'pending');
       if (!in_array($status, ['pending', 'processing', 'released'], true)) {
           $status = 'pending';
       }
       $payload = [
           'release_reference' => sanitize_text_field($_POST['release_reference'] ?? ''),
           'breakdown' => sanitize_textarea_field($_POST['breakdown'] ?? ''),
           'status' => $status,
           'released_at' => $status === 'released' ? current_time('mysql') : null,
       ];
       $updated = $wpdb->update(
           $remittances_table,
           $payload,
           ['id' => $remittance_id],
           ['%s', '%s', '%s', '%s'],
           ['%d']
       );
       if ($updated === false) {
           wp_send_json_error(['message' => 'Failed to update invoice details.']);
       }
       wp_send_json_success(['message' => 'Invoice details updated successfully.']);
   }

   function bk_delete_remittance_invoice_handler() {
       check_ajax_referer('bk_remittance_nonce', 'nonce');
       if (!current_user_can('manage_options')) {
           wp_send_json_error(['message' => 'Unauthorized']);
       }
       global $wpdb;
       $remittances_table = $wpdb->prefix . 'bk_remittances';
       $remittance_id = absint($_POST['remittance_id'] ?? 0);
       if (!$remittance_id) {
           wp_send_json_error(['message' => 'Invalid invoice.']);
       }
       $deleted = $wpdb->delete($remittances_table, ['id' => $remittance_id], ['%d']);
       if ($deleted === false) {
           wp_send_json_error(['message' => 'Failed to delete invoice.']);
       }
       wp_send_json_success(['message' => 'Invoice deleted successfully.']);
   }
   
   function bk_operating_hours_tab($business_id) {
       global $wpdb;
       $table = $wpdb->prefix . 'bk_operating_hours';
       $hours = bk_get_business_operating_hours($business_id);
       
       $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
       $nonce = wp_create_nonce('bk_nonce');
       
       ob_start();
       ?>
       <div class="bntm-form-section">
           <h3>Operating Hours</h3>
           <p>Set your business operating hours for each day of the week.</p>
           
           <div class="bntm-table-wrapper">
           <table class="bntm-table" style="margin-top: 20px;">
               <thead>
                   <tr>
                       <th>Day</th>
                       <th>Open</th>
                       <th>Start Time</th>
                       <th>End Time</th>
                       <th>Status</th>
                   </tr>
               </thead>
               <tbody>
                   <?php foreach ($hours as $hour): 
                       $day_name = $days[$hour->day_of_week % 7];
                   ?>
                       <tr>
                           <td><strong><?php echo $day_name; ?></strong></td>
                           <td>
                               <label class="bk-toggle">
                                   <input type="checkbox" 
                                          class="bk-is-open" 
                                          data-id="<?php echo $hour->id; ?>"
                                          data-nonce="<?php echo $nonce; ?>"
                                          <?php checked($hour->is_open, 1); ?>>
                                   <span class="bk-toggle-slider"></span>
                               </label>
                           </td>
                           <td>
                               <input type="time" 
                                      class="bk-start-time" 
                                      data-id="<?php echo $hour->id; ?>"
                                      data-nonce="<?php echo $nonce; ?>"
                                      value="<?php echo esc_attr($hour->start_time); ?>"
                                      style="padding: 6px 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                           </td>
                           <td>
                               <input type="time" 
                                      class="bk-end-time" 
                                      data-id="<?php echo $hour->id; ?>"
                                      data-nonce="<?php echo $nonce; ?>"
                                      value="<?php echo esc_attr($hour->end_time); ?>"
                                      style="padding: 6px 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                           </td>
                           <td>
                               <span class="open-status">
                                   <?php echo $hour->is_open ? '✓ Open' : '✗ Closed'; ?>
                               </span>
                           </td>
                       </tr>
                   <?php endforeach; ?>
               </tbody>
           </table>
            </div>
       </div>
   
       <div class="bntm-form-section" style="background: #f9fafb;">
           <h3>Booking Settings</h3>
           <form id="bk-booking-settings" class="bntm-form">
               <div class="bntm-form-group">
                   <label>Time Slot Interval (minutes) *</label>
                   <select name="slot_interval">
                       <option value="15" <?php selected(bntm_get_setting('bk_slot_interval', '30'), '15'); ?>>15 minutes</option>
                       <option value="30" <?php selected(bntm_get_setting('bk_slot_interval', '30'), '30'); ?>>30 minutes</option>
                       <option value="60" <?php selected(bntm_get_setting('bk_slot_interval', '30'), '60'); ?>>60 minutes</option>
                   </select>
                   <small>Minimum time between available slots</small>
               </div>
               <div class="bntm-form-group">
                   <label>Days to Block Future Bookings *</label>
                   <input type="number" name="advance_booking_days" value="<?php echo esc_attr(bntm_get_setting('bk_advance_booking_days', '30')); ?>" min="1">
                   <small>Customers can book up to this many days in advance</small>
               </div>
   
               <div class="bntm-form-group">
                   <label>Currency</label>
                   <select name="currency">
                       <option value="USD" <?php selected(bntm_get_setting('bk_currency', 'USD'), 'USD'); ?>>USD - US Dollar</option>
                       <option value="EUR" <?php selected(bntm_get_setting('bk_currency', 'USD'), 'EUR'); ?>>EUR - Euro</option>
                       <option value="GBP" <?php selected(bntm_get_setting('bk_currency', 'USD'), 'GBP'); ?>>GBP - British Pound</option>
                       <option value="PHP" <?php selected(bntm_get_setting('bk_currency', 'USD'), 'PHP'); ?>>PHP - Philippine Peso</option>
                   </select>
               </div>
   
               <div class="bntm-form-group">
                   <label>Tax Rate (%)</label>
                   <input type="number" name="tax_rate" step="0.01" value="<?php echo esc_attr(bntm_get_setting('bk_tax_rate', '0')); ?>">
               </div>
   
               <button type="submit" class="bntm-btn-primary">Save Booking Settings</button>
               <div id="booking-settings-message"></div>
           </form>
       </div>
   
       <script>
       var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
   
       (function() {
           // Toggle is_open
           document.querySelectorAll('.bk-is-open').forEach(checkbox => {
               checkbox.addEventListener('change', function() {
                   updateOperatingHour(this.dataset.id, 'is_open', this.checked ? 1 : 0, this.dataset.nonce);
                   const openStatus = this.closest('tr').querySelector('.open-status');
                   openStatus.textContent = this.checked ? '✓ Open' : '✗ Closed';
               });
           });
   
           // Update start time
           document.querySelectorAll('.bk-start-time').forEach(input => {
               input.addEventListener('change', function() {
                   updateOperatingHour(this.dataset.id, 'start_time', this.value, this.dataset.nonce);
               });
           });
   
           // Update end time
           document.querySelectorAll('.bk-end-time').forEach(input => {
               input.addEventListener('change', function() {
                   updateOperatingHour(this.dataset.id, 'end_time', this.value, this.dataset.nonce);
               });
           });
   
           function updateOperatingHour(hourId, field, value, nonce) {
               const formData = new FormData();
               formData.append('action', 'bk_update_operating_hour');
               formData.append('hour_id', hourId);
               formData.append('field', field);
               formData.append('value', value);
               formData.append('nonce', nonce);
   
               fetch(ajaxurl, {method: 'POST', body: formData})
               .then(r => r.json())
               .then(json => {
                   if (!json.success) {
                       alert(json.data.message || 'Failed to update');
                   }
               });
           }
   
           // Save booking settings
           document.getElementById('bk-booking-settings').addEventListener('submit', function(e) {
               e.preventDefault();
   
               const formData = new FormData(this);
               formData.append('action', 'bk_save_settings');
   
               const btn = this.querySelector('button[type="submit"]');
               btn.disabled = true;
               btn.textContent = 'Saving...';
   
               fetch(ajaxurl, {method: 'POST', body: formData})
               .then(r => r.json())
               .then(json => {
                   const msg = document.getElementById('booking-settings-message');
                   msg.innerHTML = '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + json.data.message + '</div>';
                   btn.disabled = false;
                   btn.textContent = 'Save Booking Settings';
               });
           });
       })();
       </script>
       <?php
       return ob_get_clean();
   }
   
   function bk_settings_tab($business_id) {
       $profile = bk_get_business_profile($business_id);
       $nonce   = wp_create_nonce('bk_nonce');
       $lat     = isset($profile->latitude)  && floatval($profile->latitude)  !== 0.0 ? $profile->latitude  : '';
       $lng     = isset($profile->longitude) && floatval($profile->longitude) !== 0.0 ? $profile->longitude : '';
   
       $gallery_raw = $profile->gallery_urls ?? '';
       $gallery_arr = $gallery_raw ? json_decode($gallery_raw, true) : [];
       $bank_details = bk_get_remittance_bank_details($business_id);
       $amenities = bk_get_business_amenities($business_id);
       $amenity_catalog = bk_get_default_amenities_catalog();
       $custom_amenities = array_values(array_filter($amenities, static fn($item) => !in_array($item, $amenity_catalog, true)));
       $operating_hours = bk_get_business_operating_hours($business_id);
       if (empty($operating_hours)) {
           bntm_bk_initialize_operating_hours();
           $operating_hours = bk_get_business_operating_hours($business_id);
       }
       $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    
       ob_start(); ?>
       <script>var ajaxurl='<?php echo admin_url('admin-ajax.php'); ?>';</script>
       <form id="bk-settings-form" class="bntm-form" enctype="multipart/form-data">
       <input type="hidden" name="business_id" value="<?php echo esc_attr($business_id); ?>">
       <style>
       .bks-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px}
       .bks-upload-card{border:2px dashed #e5e7eb;border-radius:10px;padding:14px;text-align:center;cursor:pointer;transition:.2s}
       .bks-upload-card:hover{border-color:var(--bntm-primary,#3b82f6)}
       .bks-preview{width:80px;height:80px;object-fit:cover;border-radius:8px;margin:0 auto 8px;display:block;border:1px solid #e5e7eb}
       .bks-preview.wide{width:100%;height:110px}
       .bk-map-row{display:grid;grid-template-columns:1fr 1fr auto;gap:10px;align-items:end}
       .bks-sport-tag{display:inline-flex;align-items:center;gap:6px;background:#f1f5f9;border:1px solid #e5e7eb;border-radius:999px;padding:4px 12px;font-size:13px;font-weight:500}
       .bks-sport-tag button{background:none;border:none;cursor:pointer;color:#94a3b8;line-height:1;padding:0}
       .bks-sport-input{display:flex;gap:8px;margin-bottom:10px}
       .bks-gallery-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:10px;margin-top:10px}
       .bks-gallery-item{position:relative;border-radius:8px;overflow:hidden}
       .bks-gallery-item img{width:100%;height:90px;object-fit:cover;display:block}
       .bks-gallery-item .del{position:absolute;top:4px;right:4px;background:rgba(0,0,0,.5);border:none;color:#fff;border-radius:50%;width:22px;height:22px;cursor:pointer;font-size:14px;line-height:22px;padding:0}
       .bks-hours-grid{display:grid;gap:10px}
       .bks-hour-row{display:grid;grid-template-columns:minmax(110px,1fr) auto minmax(120px,150px) minmax(120px,150px);gap:10px;align-items:center;padding:10px 12px;border:1px solid #e5e7eb;border-radius:10px;background:#fff}
       .bks-hour-open{display:flex;align-items:center;gap:8px;font-weight:700;color:#374151}
       .bks-hour-row input[type="time"]{padding:8px 10px;border:1px solid #d1d5db;border-radius:8px}
       .bntm-notice{padding:10px 14px;border-radius:8px;margin-top:10px;font-size:14px}
       .bntm-notice-success{background:#dcfce7;color:#16a34a}
       .bntm-notice-error{background:#fee2e2;color:#dc2626}
       </style>
       
       <div class="bntm-form-section">
           <h3 style="margin-top:0">Business Identity</h3>
           <div class="bks-grid">
               <?php foreach([
                   ['logo_image','logo_url','Logo','Square images work best','bks-preview'],
                   ['cover_image','cover_url','Cover Photo','Wide storefront photo','bks-preview wide'],
                   ['photo_image','photo_url','Profile Hero Photo','Shown on the profile page gallery and hero image','bks-preview wide'],
               ] as [$field,$prop,$label,$hint,$cls]): ?>
               <div class="bntm-form-group bks-upload-card" onclick="document.querySelector('[name=<?php echo $field;?>]').click()">
                   <label style="cursor:pointer"><?php echo $label; ?></label>
                   <img class="<?php echo $cls; ?> bks-img-preview" data-for="<?php echo $field; ?>"
                        src="<?php echo esc_url($profile->$prop ?? ''); ?>"
                        style="<?php echo empty($profile->$prop) ? 'display:none;' : ''; ?>">
                   <input type="file" name="<?php echo $field; ?>" accept="image/jpeg,image/png,image/webp" style="display:none" data-preview="<?php echo $field; ?>">
                   <small style="color:#6b7280"><?php echo $hint; ?></small>
               </div>
               <?php endforeach; ?>
               <div class="bntm-form-group">
                   <label>Accent Color</label>
                   <input type="color" name="accent_color" value="<?php echo esc_attr($profile->accent_color ?: bntm_get_setting('color_primary','#3b82f6')); ?>">
                   <small style="color:#6b7280;display:block;margin-top:6px">Used on buttons and highlights.</small>
               </div>
           </div>
   
               <div class="bntm-form-group" style="margin-top:16px">
                   <label>Photo Gallery <small style="color:#6b7280;font-weight:400">(up to 8 photos shown on profile)</small></label>
                   <div class="bks-gallery-grid" id="bks-gallery-preview">
                       <?php foreach($gallery_arr as $i => $gurl): ?>
                   <div class="bks-gallery-item" data-idx="<?php echo $i; ?>" data-existing-url="<?php echo esc_attr($gurl); ?>">
                       <img src="<?php echo esc_url($gurl); ?>">
                       <button type="button" class="del" onclick="bksRemoveGallery(this)">&times;</button>
                   </div>
                   <?php endforeach; ?>
                   </div>
               <input type="file" name="gallery_images[]" id="bks-gallery-input" multiple accept="image/jpeg,image/png,image/webp" style="margin-top:8px;width:100%">
               <input type="hidden" name="gallery_existing" id="bks-gallery-existing" value="<?php echo esc_attr($gallery_raw ?: '[]'); ?>">
           </div>
   
           <div class="bntm-form-group">
               <label>Location</label>
               <input type="text" name="location" value="<?php echo esc_attr($profile->location ?? ''); ?>" placeholder="City, address, or venue">
           </div>
   
           <div class="bk-map-row">
               <div class="bntm-form-group">
                   <label>Latitude</label>
                   <input type="text" id="bk-latitude" name="latitude" value="<?php echo esc_attr($lat); ?>" placeholder="14.5995">
               </div>
               <div class="bntm-form-group">
                   <label>Longitude</label>
                   <input type="text" id="bk-longitude" name="longitude" value="<?php echo esc_attr($lng); ?>" placeholder="120.9842">
               </div>
               <div style="padding-bottom:4px;display:flex;flex-direction:column;gap:6px">
                   <button type="button" class="bntm-btn-secondary" id="bk-use-current-location" style="white-space:nowrap">📍 My Location</button>
                   <a class="bntm-btn-secondary" id="bk-open-map" href="https://www.google.com/maps" target="_blank" rel="noopener" style="text-align:center;text-decoration:none">Open Maps</a>
               </div>
           </div>
           <small style="color:#6b7280">Open Google Maps, right-click a spot, then copy the coordinates.</small>
   
           <div class="bntm-form-group" style="margin-top:14px">
               <label>Business Description</label>
               <textarea name="bk_description" rows="4" placeholder="Tell customers what makes you worth booking."><?php echo esc_textarea($profile->description ?? bk_get_business_setting($business_id, 'bk_description', '')); ?></textarea>
               <small style="color:#6b7280">Used for both the profile page and the booking appointment page.</small>
           </div>
   
           <div class="bntm-form-group">
               <label>Terms &amp; Conditions</label>
               <textarea name="bk_terms" rows="6" placeholder="Enter your booking terms and conditions"><?php echo esc_textarea(bk_get_business_setting($business_id, 'bk_terms', '')); ?></textarea>
           </div>
   
           <div class="bntm-form-group">
               <label>Holiday / Closed Dates</label>
               <textarea name="bk_holidays" rows="3" placeholder="2026-12-25&#10;2026-12-31"><?php echo esc_textarea(bk_get_business_setting($business_id, 'bk_holidays', '')); ?></textarea>
               <small style="color:#6b7280">One date per line (YYYY-MM-DD).</small>
           </div>

           <div class="bntm-form-section" style="padding:18px;margin-top:18px;">
               <h4 style="margin:0 0 14px;">Operating Hours</h4>
               <div class="bks-hours-grid">
                   <?php foreach ($operating_hours as $hour): ?>
                   <div class="bks-hour-row">
                       <strong><?php echo esc_html($days[$hour->day_of_week % 7]); ?></strong>
                       <label class="bks-hour-open">
                           <input type="checkbox" name="hours[<?php echo esc_attr($hour->id); ?>][is_open]" value="1" <?php checked($hour->is_open, 1); ?>>
                           Open
                       </label>
                       <input type="time" name="hours[<?php echo esc_attr($hour->id); ?>][start_time]" value="<?php echo esc_attr(substr($hour->start_time, 0, 5)); ?>">
                       <input type="time" name="hours[<?php echo esc_attr($hour->id); ?>][end_time]" value="<?php echo esc_attr(substr($hour->end_time, 0, 5)); ?>">
                   </div>
                   <?php endforeach; ?>
               </div>
           </div>

           <div class="bntm-form-section" style="padding:18px;margin-top:18px;">
               <h4 style="margin:0 0 14px;">Remittance Bank Details</h4>
               <div class="bntm-form-row">
                   <div class="bntm-form-group">
                       <label>Bank Name</label>
                       <input type="text" name="bk_remittance_bank_name" value="<?php echo esc_attr($bank_details['bank_name']); ?>">
                   </div>
                   <div class="bntm-form-group">
                       <label>Branch</label>
                       <input type="text" name="bk_remittance_bank_branch" value="<?php echo esc_attr($bank_details['branch']); ?>">
                   </div>
               </div>
               <div class="bntm-form-row">
                   <div class="bntm-form-group">
                       <label>Account Name</label>
                       <input type="text" name="bk_remittance_account_name" value="<?php echo esc_attr($bank_details['account_name']); ?>">
                   </div>
                   <div class="bntm-form-group">
                       <label>Account Number</label>
                       <input type="text" name="bk_remittance_account_number" value="<?php echo esc_attr($bank_details['account_number']); ?>">
                   </div>
               </div>
               <div class="bntm-form-group">
                   <label>Notes</label>
                   <textarea name="bk_remittance_bank_notes" rows="3"><?php echo esc_textarea($bank_details['notes']); ?></textarea>
               </div>
           </div>
   
           <label style="display:flex;gap:10px;align-items:center;font-weight:500">
               <input type="checkbox" name="is_listed" value="1" <?php checked(!empty($profile->is_listed)); ?>>
               Show in the booking directory
           </label>
           <label style="display:flex;gap:10px;align-items:center;font-weight:500;margin-top:10px">
               <input type="checkbox" name="show_amenities" value="1" <?php checked(bk_business_shows_amenities($business_id)); ?>>
               Show amenities section on profile page
           </label>
           <div class="bntm-form-section" style="padding:18px;margin-top:18px;">
               <h4 style="margin:0 0 14px;">Amenities</h4>
               <p style="color:#6b7280;margin:0 0 14px;">Choose the amenities that should appear on your public business profile.</p>
               <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;">
                   <?php foreach ($amenity_catalog as $amenity): ?>
                   <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid #e5e7eb;border-radius:10px;background:#fff;">
                       <input type="checkbox" name="bk_amenities[]" value="<?php echo esc_attr($amenity); ?>" <?php checked(in_array($amenity, $amenities, true)); ?>>
                       <span><?php echo esc_html($amenity); ?></span>
                   </label>
                   <?php endforeach; ?>
               </div>
               <div class="bntm-form-group" style="margin-top:14px;">
                   <label>Custom Amenities</label>
                   <textarea name="bk_custom_amenities" rows="3" placeholder="One per line or separated by commas"><?php echo esc_textarea(implode("\n", $custom_amenities)); ?></textarea>
                   <small style="color:#6b7280">Use this for amenities not included in the default list.</small>
               </div>
           </div>
       </div>
    
       <div style="padding:0 0 16px;display:flex;gap:12px;align-items:center">
           <button type="submit" class="bntm-btn-primary">Save Settings</button>
           <div id="booking-settings-message" style="flex:1"></div>
       </div>
       </form>
    
       <script>
       (function(){
           const galleryInput=document.getElementById('bks-gallery-input');
           let pendingGalleryFiles=[];
           /* Image previews */
           document.querySelectorAll('[data-preview]').forEach(input=>{
               input.addEventListener('change',function(){
                   const f=this.files&&this.files[0];
                   const img=document.querySelector('.bks-img-preview[data-for="'+this.dataset.preview+'"]');
                   if(!f||!img)return;
                   img.src=URL.createObjectURL(f);img.style.display='block';
               });
           });
    
           /* Gallery remove */
           function syncPendingGalleryFiles(){
               if(!galleryInput) return;
               const dt=new DataTransfer();
               pendingGalleryFiles.forEach(file=>dt.items.add(file));
               galleryInput.files=dt.files;
           }
           window.bksRemoveGallery=function(btn){
               const item=btn.closest('.bks-gallery-item');
               if(item && item.dataset.newFileIndex!==undefined){
                   const removeIndex=parseInt(item.dataset.newFileIndex,10);
                   pendingGalleryFiles=pendingGalleryFiles.filter((file,index)=>index!==removeIndex);
                   syncPendingGalleryFiles();
                   document.querySelectorAll('#bks-gallery-preview .bks-gallery-item[data-new-file-index]').forEach((newItem,index)=>{
                       newItem.dataset.newFileIndex=index;
                   });
               }
               if(item){ item.remove(); }
               bksUpdateGalleryHidden();
           };
           function bksUpdateGalleryHidden(){
               const existing=[];
               document.querySelectorAll('#bks-gallery-preview .bks-gallery-item[data-existing-url]').forEach(item=>{
                   const url=item.getAttribute('data-existing-url')||'';
                   if(url) existing.push(url);
               });
               document.getElementById('bks-gallery-existing').value=JSON.stringify(existing);
           }
           document.getElementById('bks-gallery-preview').addEventListener('click',function(e){
               if(e.target && e.target.classList.contains('del')){
                   e.preventDefault();
                   e.stopPropagation();
                   window.bksRemoveGallery(e.target);
               }
           },true);
    
           /* Gallery new files preview */
           galleryInput.addEventListener('change',function(){
               const grid=document.getElementById('bks-gallery-preview');
               Array.from(this.files).forEach(file=>{
                   const newIndex=pendingGalleryFiles.push(file)-1;
                   const div=document.createElement('div');div.className='bks-gallery-item';div.dataset.newFileIndex=newIndex;
                   const img=document.createElement('img');img.src=URL.createObjectURL(file);img.style.cssText='width:100%;height:90px;object-fit:cover;display:block';
                   const btn=document.createElement('button');btn.type='button';btn.className='del';btn.textContent='×';btn.onclick=function(){this.closest('.bks-gallery-item').remove();};
                   div.append(img,btn);grid.appendChild(div);
               });
           });
    
           /* Map link */
           const latI=document.getElementById('bk-latitude'),lngI=document.getElementById('bk-longitude'),mapA=document.getElementById('bk-open-map');
           function updateMap(){const q=latI.value&&lngI.value?latI.value+','+lngI.value:'';mapA.href='https://www.google.com/maps/search/?api=1&query='+encodeURIComponent(q||'business location');}
           [latI,lngI].forEach(i=>i&&i.addEventListener('input',updateMap));updateMap();
           document.getElementById('bk-use-current-location').addEventListener('click',function(){
               if(!navigator.geolocation){alert('Geolocation unavailable.');return;}
               navigator.geolocation.getCurrentPosition(p=>{latI.value=p.coords.latitude.toFixed(8);lngI.value=p.coords.longitude.toFixed(8);updateMap();},()=>alert('Location access denied.'));
           });
    
           /* Submit */
           document.getElementById('bk-settings-form').addEventListener('submit',function(e){
               e.preventDefault();
               const fd=new FormData(this);
               fd.append('action','bk_save_booking_settings');
               fd.append('nonce','<?php echo $nonce; ?>');
               const btn=this.querySelector('button[type="submit"]');
               btn.disabled=true;btn.textContent='Saving…';
               fetch(ajaxurl,{method:'POST',body:fd})
               .then(r=>r.json()).then(json=>{
                   const el=document.getElementById('booking-settings-message');
                   el.innerHTML='<div class="bntm-notice bntm-notice-'+(json.success?'success':'error')+'">'+json.data.message+'</div>';
                   btn.disabled=false;btn.textContent='Save Settings';
                   if(json.success)setTimeout(()=>el.innerHTML='',3000);
               });
           });
       })();
       </script>
       <?php
       return ob_get_clean();
   }
    
   /* ---------- CALENDAR & BOOKING PAGE ---------- */
   /* ---------- UPDATED CALENDAR & BOOKING PAGE ---------- */
 
/**
 * BNTM Booking Shortcodes — Redesigned
 * Glassmorphism · Barlow Condensed · Smooth animations
 * --p / --ph CSS variables used consistently throughout
 */

/* ═══════════════════════════════════════════════════════
   SHARED STYLES & FONTS  (injected once per page)
   ═══════════════════════════════════════════════════════ */
function bntm_shared_head() {
    static $done = false;
    if ($done) return;
    $done = true;
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,600;0,700;0,800;0,900;1,800;1,900&family=Barlow:wght@400;500;600;700&display=swap" rel="stylesheet">';
}


/* ═══════════════════════════════════════════════════════
   MODIFIED: bntm_shortcode_bk_calendar()
   Changes:
   - After clicking a date, slots view shows a horizontal
     scrollable date strip (same month) instead of hiding calendar
   - "Add to Cart" bar matches profile page style
   - Floating cart button replaced with footer bar (price + checkout)
   ═══════════════════════════════════════════════════════ */
function bntm_shortcode_bk_calendar() {
    global $wpdb;
    $business_id = bk_get_request_business_id();
    if (!$business_id) return bntm_shortcode_bk_directory();

    $services = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}bk_services WHERE business_id = %d AND status = 'active' ORDER BY name ASC LIMIT 100",
        $business_id
    ));
    if (empty($services)) return '<div class="bntm-container"><p>No services available for booking.</p></div>';

    $profile      = bk_get_business_profile($business_id);
    $logo         = !empty($profile->logo_url) ? $profile->logo_url : bk_get_business_setting($business_id, 'site_logo', '');
    $site_title   = bk_get_business_name($business_id);
    $bk_desc      = $profile->description ?: bk_get_business_setting($business_id, 'bk_description', 'Book your appointment with us.');
    $bk_terms     = bk_get_business_setting($business_id, 'bk_terms', 'By booking an appointment, you agree to our terms and conditions.');
    $nonce        = wp_create_nonce('bk_calendar_nonce');
    $max_date     = date('Y-m-d', strtotime('+' . bntm_get_setting('bk_advance_booking_days', '30') . ' days'));
    $currency_sym = bntm_get_setting('bk_currency', 'USD') === 'PHP' ? '₱' : '$';
    $tax_rate     = floatval(bntm_get_setting('bk_tax_rate', '0'));
    $primary      = bk_get_primary_color();
    $primary_hov  = bk_get_primary_hover_color();
    $pub_id       = esc_js(bk_get_business_public_id($business_id));

    bntm_shared_head();
    ob_start();
    echo bk_get_primary_root_style_tag();
?>
<script>
var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
var bkBusinessId = '<?php echo $pub_id; ?>';
window.formatTime12Hour = window.formatTime12Hour || function(t){if(!t)return'';const[h,m]=t.split(':'),hr=+h,ap=hr>=12?'PM':'AM';return(hr%12||12)+':'+m+' '+ap;};
</script>
<style>:root{--p:<?php echo esc_attr($primary);?>;--ph:<?php echo esc_attr($primary_hov);?>;}</style>
<div class="bkcal-wrap" style="--p:<?php echo esc_attr($primary);?>;--ph:<?php echo esc_attr($primary_hov);?>;">

    <!-- HEADER -->
    <div class="bkcal-header">
        <?php if (!empty($profile->cover_url)): ?>
        <img src="<?php echo esc_url($profile->cover_url); ?>" alt="" class="bkcal-cover">
        <div class="bkcal-cover-grad"></div>
        <?php endif; ?>
        <div class="bkcal-header-inner">
            <?php if ($logo): ?>
            <img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($site_title); ?>" class="bkcal-logo">
            <?php endif; ?>
            <h1 class="bkcal-title"><?php echo esc_html($site_title); ?></h1>
            <?php if ($bk_desc): ?><p class="bkcal-desc"><?php echo esc_html($bk_desc); ?></p><?php endif; ?>
            <?php if (!empty($profile->location)): ?><p class="bkcal-loc">📍 <?php echo esc_html($profile->location); ?></p><?php endif; ?>
        </div>
    </div>

    <!-- CALENDAR VIEW -->
    <div id="bkcal-calendar-view" class="bkcal-panel bkcal-panel--active">
        <div class="bkcal-glass-card">
            <div class="bkcal-month-nav">
                <button id="prev-month" class="bkcal-nav-btn" aria-label="Previous">
                    <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <h2 id="calendar-month-year" class="bkcal-month-label"></h2>
                <button id="next-month" class="bkcal-nav-btn" aria-label="Next">
                    <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
            </div>

            <div class="bkcal-grid">
                <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d): ?>
                    <div class="bkcal-weekday"><?php echo $d; ?></div>
                <?php endforeach; ?>
                <div class="bkcal-days" id="calendar-days"></div>
            </div>

            <div class="bkcal-legend">
                <div class="bkcal-legend-item"><span class="bkcal-dot bkcal-dot--low"></span>Available</div>
                <div class="bkcal-legend-item"><span class="bkcal-dot bkcal-dot--med"></span>Busy</div>
                <div class="bkcal-legend-item"><span class="bkcal-dot bkcal-dot--full"></span>Full</div>
            </div>
        </div>
    </div>

    <!-- SLOTS VIEW (replaces full calendar, shows date strip) -->
    <div id="bkcal-slots-view" class="bkcal-panel" style="display:none;">
        <div class="bkcal-glass-card">

            <!-- Back + Month label -->
            <div class="bkcal-slots-header">
                <button id="back-to-calendar" class="bkcal-back-btn">
                    <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                    Back
                </button>
                <div>
                    <h2 class="bkcal-slots-title">Available Slots</h2>
                    <p class="bkcal-slots-date" id="selected-date-display"></p>
                </div>
            </div>

            <!-- ── SCROLLABLE DATE STRIP (image ref: horizontal date chips) ── -->
            <div class="bkcal-date-strip-wrap">
                <div class="bkcal-date-strip" id="bkcal-date-strip">
                    <!-- populated by JS -->
                </div>
            </div>

            <div class="bkcal-service-filter">
                <label class="bkcal-filter-label">Service</label>
                <div class="bkcal-select-wrap">
                    <select id="customer-service-filter">
                        <option value="">All Services</option>
                        <?php foreach ($services as $s): ?>
                            <option value="<?php echo $s->id; ?>"><?php echo esc_html($s->name); ?> (<?php echo $s->duration; ?> min · <?php echo bk_format_price($s->price); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                </div>
            </div>

            <div id="slots-loading" class="bkcal-loading" style="display:none;">
                <div class="bkcal-spinner"></div>
                <p>Loading slots...</p>
            </div>
            <div id="slots-table-container" style="display:none;overflow-x:auto;">
                <table class="bkcal-table">
                    <thead id="slots-table-head"></thead>
                    <tbody id="slots-table-body"></tbody>
                </table>
            </div>
            <div id="slots-message" style="display:none;" class="bkcal-notice"></div>
        </div>
    </div>

    <!-- TERMS -->
    <div class="bkcal-glass-card bkcal-terms">
        <h3 class="bkcal-terms-title">Booking Terms &amp; Conditions</h3>
        <div class="bkcal-terms-body"><?php echo nl2br(esc_html($bk_terms)); ?></div>
    </div>
</div>

<!-- ── ADD-TO-CART BAR (matches profile style) ── -->
<div id="bk-selected-slot-panel" class="bkcal-slot-addbar" style="display:none;">
    <div class="bkcal-slot-addbar-info">
        <strong id="bk-selected-slot-title"></strong>
        <p id="bk-selected-slot-meta"></p>
    </div>
    <button type="button" class="bkcal-cta-sm" id="bk-add-selected-slot">Add to Cart</button>
</div>

<!-- ── FOOTER CHECKOUT BAR (matches profile: price + book) ── -->
<div class="bkcal-footer" id="bkcal-footer" style="display:none;">
    <button type="button" class="bkcal-footer-btn" id="bk-floating-checkout">
        <span class="bkcal-footer-label">Checkout <span class="bkcal-cart-badge" id="bk-cart-count">0</span></span>
        <small class="bkcal-footer-sub">Total: <span id="bkcal-footer-total">—</span></small>
    </button>
</div>

<!-- BOOKING MODAL -->
<div id="booking-modal" class="bkcal-modal">
    <div class="bkcal-modal-overlay"></div>
    <div class="bkcal-modal-box">
        <button class="bkcal-modal-close">&times;</button>
        <h2 class="bkcal-modal-title">Complete Booking</h2>
        <form id="booking-form">
            <div id="bk-cart-review" class="bkcal-cart-review"></div>

            <div class="bkcal-summary-card">
                <div class="bkcal-summary-row"><span>Selected Times</span><span id="modal-service-name" class="bkcal-summary-val"></span></div>
                <div class="bkcal-summary-row"><span>Total Duration</span><span id="modal-duration" class="bkcal-summary-val"></span> min</div>
                <div class="bkcal-summary-divider"></div>
                <div class="bkcal-summary-row"><span>Unit Price</span><span id="calc-unit-price" class="bkcal-summary-val"></span></div>
                <div class="bkcal-summary-row"><span>Bookings</span><span id="calc-quantity" class="bkcal-summary-val">1</span></div>
                <div class="bkcal-summary-row"><span>Total Duration</span><span id="calc-total-duration" class="bkcal-summary-val"></span></div>
                <div class="bkcal-summary-row"><span>End Time</span><span id="calc-end-time" class="bkcal-summary-val bkcal-summary-val--accent"></span></div>
                <div class="bkcal-summary-divider"></div>
                <div class="bkcal-summary-row bkcal-summary-row--total"><span>Total Price</span><span id="calc-total-price" class="bkcal-summary-val bkcal-summary-val--total"></span></div>
            </div>

            <div id="availability-warning" class="bkcal-warning" style="display:none;">
                ⚠️ <span id="warning-message"></span>
            </div>

            <div class="bkcal-fields">
                <div class="bkcal-field"><label>Full Name <span>*</span></label><input type="text" name="customer_name" required placeholder="Juan dela Cruz"></div>
                <div class="bkcal-field"><label>Email <span>*</span></label><input type="email" name="customer_email" required placeholder="juan@email.com"></div>
                <div class="bkcal-field"><label>Phone <span>*</span></label><input type="tel" name="customer_phone" required placeholder="+63 9XX XXX XXXX"></div>
                <div class="bkcal-field bkcal-field--full"><label>Additional Notes</label><textarea name="customer_notes" rows="3" placeholder="Any special requests…"></textarea></div>
            </div>

            <?php foreach (['service_id','booking_date','start_time','end_time','quantity','amount'] as $f): ?>
                <input type="hidden" name="<?php echo $f; ?>" id="hidden-<?php echo str_replace('_','-',$f); ?>">
            <?php endforeach; ?>

            <button type="submit" class="bkcal-submit" id="complete-booking-btn">Complete Booking</button>
            <div id="booking-message" class="bkcal-booking-msg"></div>
        </form>
    </div>
</div>

<style>
/* ── Tokens ── */
.bkcal-wrap{--p:<?php echo esc_attr($primary);?>;--ph:<?php echo esc_attr($primary_hov);?>;}
.bkcal-wrap,.bkcal-wrap *,.bkcal-modal,.bkcal-modal *,.bkcal-footer{font-family:'Barlow',sans-serif!important;box-sizing:border-box;}

/* ── Layout ── */
.bkcal-wrap{max-width:860px;margin:0 auto;padding:0 0 100px;}

/* ── Header (unchanged) ── */
.bkcal-header{position:relative;min-height:220px;border-radius:24px;overflow:hidden;margin-bottom:20px;background:linear-gradient(135deg,#0f172a,#1e3a5f);}
.bkcal-cover{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block;}
.bkcal-cover-grad{position:absolute;inset:0;background:linear-gradient(to top,rgba(15,23,42,.92) 0%,rgba(15,23,42,.4) 60%,transparent 100%);}
.bkcal-header-inner{position:relative;z-index:2;padding:30px 28px 28px;display:flex;flex-direction:column;align-items:center;text-align:center;}
.bkcal-logo{width:80px;height:80px;object-fit:cover;border-radius:18px;border:4px solid rgba(255,255,255,.2);backdrop-filter:blur(10px);margin-bottom:14px;box-shadow:0 10px 40px rgba(0,0,0,.4);}
.bkcal-title{font-size:34px!important;font-weight:900!important;color:#fff!important;margin:0 0 8px;text-transform:uppercase;letter-spacing:-.5px;}
.bkcal-desc{font-size:14px;color:rgba(255,255,255,.7);margin:0 0 6px;max-width:520px;line-height:1.6;}
.bkcal-loc{font-size:13px;color:rgba(255,255,255,.55);margin:0;}

/* ── Glass card ── */
.bkcal-glass-card{background:rgba(255,255,255,.85);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.6);border-radius:20px;padding:24px;box-shadow:0 8px 32px rgba(15,23,42,.08);margin-bottom:16px;}

/* ── Month nav ── */
.bkcal-month-nav{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;}
.bkcal-month-label{font-size:22px!important;font-weight:800!important;color:#0f172a!important;margin:0;text-transform:uppercase;letter-spacing:.3px;}
.bkcal-nav-btn{width:40px;height:40px;border-radius:12px;border:1.5px solid #e2e8f0;background:rgba(255,255,255,.8);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .18s;}
.bkcal-nav-btn:hover:not(:disabled){border-color:var(--p,#3b82f6);background:var(--p,#3b82f6);color:#fff;}
.bkcal-nav-btn:hover:not(:disabled) svg{stroke:#fff;}
.bkcal-nav-btn:disabled{opacity:.35;cursor:not-allowed;}
.bkcal-nav-btn svg{width:18px;height:18px;stroke:#475569;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round;}

/* ── Calendar grid ── */
.bkcal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:6px;}
.bkcal-weekday{text-align:center;font-size:11px;font-weight:700;color:#94a3b8;padding:6px 0;text-transform:uppercase;letter-spacing:.5px;}
.bkcal-days{display:grid;grid-template-columns:repeat(7,1fr);gap:6px;grid-column:1/-1;}
.bkcal-day{aspect-ratio:1;display:flex;flex-direction:column;align-items:center;justify-content:center;border-radius:12px;cursor:pointer;font-weight:700;font-size:14px;transition:all .2s;border:2px solid transparent;position:relative;color:#1e293b;background:rgba(255,255,255,.6);}
.bkcal-day:hover:not(.bkcal-day--disabled):not(.bkcal-day--other):not(.bkcal-day--full){transform:scale(1.06);box-shadow:0 4px 16px rgba(var(--p-rgb,59,130,246),.18);border-color:var(--p,#3b82f6);}
.bkcal-day--other{color:#cbd5e1;cursor:default;background:transparent;}
.bkcal-day--disabled{opacity:.38;cursor:not-allowed;}
.bkcal-day--low{background:rgba(134,239,172,.25);border-color:#86efac;}
.bkcal-day--med{background:rgba(253,186,116,.25);border-color:#fdba74;}
.bkcal-day--full{background:rgba(248,113,113,.18)!important;border-color:#f87171!important;cursor:not-allowed!important;opacity:.65;}
.bkcal-day--selected{background:var(--p,#3b82f6)!important;border-color:var(--p,#3b82f6)!important;color:#fff!important;box-shadow:0 6px 20px rgba(59,130,246,.3);}
.bkcal-day--no-hours{opacity:.3;cursor:not-allowed;background:rgba(0,0,0,.04);}
.bkcal-day-badge{font-size:9px;font-weight:600;color:inherit;opacity:.75;margin-top:2px;}
.bkcal-day--selected .bkcal-day-badge{color:rgba(255,255,255,.8);}

/* ── Legend ── */
.bkcal-legend{display:flex;gap:18px;justify-content:center;margin-top:16px;padding-top:16px;border-top:1px solid rgba(0,0,0,.06);}
.bkcal-legend-item{display:flex;align-items:center;gap:6px;font-size:12px;color:#64748b;font-weight:500;}
.bkcal-dot{width:10px;height:10px;border-radius:50%;display:inline-block;}
.bkcal-dot--low{background:#86efac;box-shadow:0 0 6px rgba(134,239,172,.6);}
.bkcal-dot--med{background:#fdba74;box-shadow:0 0 6px rgba(253,186,116,.6);}
.bkcal-dot--full{background:#f87171;box-shadow:0 0 6px rgba(248,113,113,.6);}

/* ── Slots header ── */
.bkcal-slots-header{display:flex;align-items:center;gap:16px;margin-bottom:16px;}
.bkcal-back-btn{display:flex;align-items:center;gap:4px;padding:8px 14px;border-radius:10px;border:1.5px solid #e2e8f0;background:rgba(255,255,255,.7);cursor:pointer;font-size:13px;font-weight:700;color:#475569;transition:all .18s;}
.bkcal-back-btn:hover{border-color:var(--p);color:var(--p);}
.bkcal-back-btn svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round;}
.bkcal-slots-title{font-size:20px!important;font-weight:800!important;color:#0f172a!important;margin:0;text-transform:uppercase;}
.bkcal-slots-date{font-size:13px;color:#64748b;margin:3px 0 0;}

/* ── DATE STRIP (horizontal scrollable chips, ref: images) ── */
.bkcal-date-strip-wrap{margin-bottom:18px;overflow:hidden;}
.bkcal-date-strip{display:flex;gap:8px;overflow-x:auto;scrollbar-width:none;padding:4px 2px 8px;}
.bkcal-date-strip::-webkit-scrollbar{display:none;}
.bkcal-date-chip{flex-shrink:0;display:flex;flex-direction:column;align-items:center;justify-content:center;min-width:58px;padding:10px 6px;border-radius:14px;border:1.5px solid #e2e8f0;background:rgba(255,255,255,.8);cursor:pointer;transition:all .18s;gap:2px;}
.bkcal-date-chip:hover:not(.bkcal-date-chip--disabled):not(.bkcal-date-chip--full){border-color:var(--p);background:rgba(59,130,246,.06);}
.bkcal-date-chip--selected{background:var(--p)!important;border-color:var(--p)!important;box-shadow:0 4px 16px rgba(59,130,246,.25);}
.bkcal-date-chip--disabled{opacity:.35;cursor:not-allowed;}
.bkcal-date-chip--full{opacity:.5;cursor:not-allowed;background:rgba(248,113,113,.1)!important;border-color:#f87171!important;}
.bkcal-date-chip--nohours{opacity:.28;cursor:not-allowed;}
.bkcal-chip-day{font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.3px;}
.bkcal-chip-num{font-size:20px;font-weight:900;color:#0f172a;line-height:1;}
.bkcal-date-chip--selected .bkcal-chip-day,
.bkcal-date-chip--selected .bkcal-chip-num{color:#fff!important;}

/* ── Filter ── */
.bkcal-service-filter{margin-bottom:16px;}
.bkcal-filter-label{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#94a3b8;margin-bottom:6px;}
.bkcal-select-wrap{position:relative;}
.bkcal-select-wrap select{width:100%;padding:11px 40px 11px 14px;border:1.5px solid #e2e8f0;border-radius:12px;background:rgba(255,255,255,.8);font-size:14px;color:#0f172a;appearance:none;cursor:pointer;transition:border-color .18s;font-family:'Barlow',sans-serif!important;}
.bkcal-select-wrap select:focus{outline:none;border-color:var(--p);}
.bkcal-select-wrap svg{position:absolute;right:12px;top:50%;transform:translateY(-50%);width:16px;height:16px;stroke:#94a3b8;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;pointer-events:none;}

/* ── Loading ── */
.bkcal-loading{text-align:center;padding:40px;color:#64748b;display:flex;flex-direction:column;align-items:center;gap:12px;}
.bkcal-spinner{width:32px;height:32px;border:3px solid rgba(59,130,246,.15);border-top-color:var(--p,#3b82f6);border-radius:50%;animation:bkcal-spin .7s linear infinite;}
@keyframes bkcal-spin{to{transform:rotate(360deg);}}

/* ── Slots table ── */
.bkcal-table{width:100%;border-collapse:collapse;}
.bkcal-table thead{background:rgba(248,250,252,.9);}
.bkcal-table th{padding:10px 12px;text-align:center;font-size:12px;font-weight:800;color:#0f172a;text-transform:uppercase;letter-spacing:.4px;border-bottom:2px solid #e2e8f0;}
.bkcal-table td{padding:7px 8px;text-align:center;border-bottom:1px solid #f1f5f9;}
.bkcal-table tbody tr:hover{background:rgba(59,130,246,.03);}
.bkcal-slot-btn{width:100%;padding:9px 6px;border-radius:10px;border:2px solid #e2e8f0;background:#fff;cursor:pointer;font-size:13px;font-weight:700;color:#334155;transition:all .18s;line-height:1.3;}
.bkcal-slot-btn:hover:not(.bkcal-slot-btn--booked){border-color:var(--p);color:var(--p);background:rgba(59,130,246,.06);}
.bkcal-slot-btn--available{border-color:#86efac;background:#f0fdf4;color:#166534;}
.bkcal-slot-btn--selected{background:var(--p,#3b82f6)!important;color:#fff!important;border-color:var(--p,#3b82f6)!important;box-shadow:0 4px 16px rgba(59,130,246,.25);}
.bkcal-slot-btn--in-cart{background:#0f172a!important;color:#fff!important;border-color:#0f172a!important;}
.bkcal-slot-btn--booked{background:#fef2f2;color:#b91c1c;border-color:#fca5a5;cursor:not-allowed;}

/* ── Notice ── */
.bkcal-notice{padding:16px 18px;border-radius:12px;background:#fef3c7;border:1.5px solid #fde68a;color:#92400e;font-size:14px;text-align:center;margin-top:12px;}

/* ── Terms ── */
.bkcal-terms{margin-top:0;}
.bkcal-terms-title{font-size:18px!important;font-weight:800!important;color:#0f172a!important;text-transform:uppercase;margin:0 0 12px;}
.bkcal-terms-body{font-size:13px;color:#64748b;line-height:1.75;}

/* ── ADD-TO-CART BAR (matches profile bkp-slot-addbar) ── */
.bkcal-slot-addbar{
    position:fixed;left:50%;transform:translateX(-50%);bottom:92px;z-index:101;
    display:flex;align-items:center;justify-content:space-between;gap:10px;
    width:calc(100% - 32px);max-width:828px;
    padding:13px 16px;
    background:#fff;border:1.5px solid rgba(59,130,246,.22);border-radius:16px;
    box-shadow:0 12px 36px rgba(15,23,42,.16);
}
.bkcal-slot-addbar-info strong{display:block;font-size:13px;color:#0f172a;}
.bkcal-slot-addbar-info p{font-size:12px;color:#64748b;margin:2px 0 0;}
.bkcal-cta-sm{padding:10px 18px;border-radius:10px;border:none;background:var(--p,#3b82f6);color:#fff!important;font-size:14px!important;font-weight:800!important;cursor:pointer;white-space:nowrap;text-transform:uppercase;letter-spacing:.3px;transition:all .18s;}
.bkcal-cta-sm:hover{background:var(--ph,#2563eb);transform:translateY(-1px);}

/* ── FOOTER CHECKOUT BAR (matches profile bkp-footer) ── */
.bkcal-footer{
    position:fixed;bottom:0;left:50%;transform:translateX(-50%);
    width:100%;max-width:860px;
    padding:12px 16px 28px;
    display:flex;align-items:center;justify-content:center;z-index:100;
}
.bkcal-footer-btn{
    width:100%;display:flex!important;flex-direction:column;align-items:center;justify-content:center;gap:3px;
    background:var(--p,#3b82f6)!important;color:#fff!important;
    border:none!important;border-radius:999px!important;padding:13px 28px!important;
    font-size:16px!important;font-weight:900!important;cursor:pointer;text-transform:uppercase;letter-spacing:.5px;
    transition:all .18s;box-shadow:0 6px 24px rgba(59,130,246,.3);font-family:'Barlow',sans-serif!important;
}
.bkcal-footer-btn:hover{background:var(--ph,#2563eb)!important;transform:scale(1.02);}
.bkcal-footer-label{display:flex;align-items:center;gap:8px;font-size:16px;font-weight:900;}
.bkcal-footer-sub{display:block;font-size:11px!important;font-weight:700!important;color:rgba(255,255,255,.82)!important;text-transform:none;letter-spacing:0;}
.bkcal-cart-badge{background:rgba(255,255,255,.25);border-radius:999px;padding:2px 8px;font-size:13px;font-weight:900;}

/* ── Modal ── */
.bkcal-modal{display:none;position:fixed;inset:0;z-index:10000;}
.bkcal-modal.active{display:block;}
.bkcal-modal-overlay{position:absolute;inset:0;background:rgba(15,23,42,.7);backdrop-filter:blur(8px);}
.bkcal-modal-box{position:relative;max-width:560px;margin:30px auto;background:rgba(255,255,255,.96);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.7);border-radius:24px;box-shadow:0 30px 80px rgba(15,23,42,.35);max-height:calc(100vh - 60px);overflow-y:auto;padding:28px 24px;}
.bkcal-modal-close{position:absolute;top:14px;right:14px;width:36px;height:36px;border-radius:50%;background:#f1f5f9;border:none;font-size:20px;cursor:pointer;color:#475569;transition:all .18s;display:flex;align-items:center;justify-content:center;line-height:1;}
.bkcal-modal-close:hover{background:#e2e8f0;color:#0f172a;}
.bkcal-modal-title{font-size:24px!important;font-weight:900!important;color:#0f172a!important;text-transform:uppercase;margin:0 0 18px;}
.bkcal-cart-review{display:grid;gap:8px;margin-bottom:14px;}
.bkcal-cart-item{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:11px 14px;border:1.5px solid #e2e8f0;border-radius:12px;background:#f8fafc;}
.bkcal-cart-item strong{font-size:13px;color:#0f172a;}
.bkcal-cart-item small{font-size:11px;color:#64748b;display:block;margin-top:2px;}
.bkcal-cart-item button{padding:6px 10px;border-radius:8px;border:none;background:#fee2e2;color:#b91c1c;cursor:pointer;font-size:12px;font-weight:700;transition:all .18s;}
.bkcal-cart-item button:hover{background:#fca5a5;}
.bkcal-summary-card{background:linear-gradient(135deg,#f0f9ff,#eff6ff);border:1.5px solid #bfdbfe;border-radius:14px;padding:16px 18px;margin-bottom:18px;}
.bkcal-summary-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;font-size:13px;color:#475569;}
.bkcal-summary-row:last-child{margin-bottom:0;}
.bkcal-summary-val{font-weight:700;color:#0f172a;}
.bkcal-summary-val--accent{color:var(--p,#3b82f6)!important;font-size:15px;}
.bkcal-summary-val--total{color:#059669!important;font-size:17px;}
.bkcal-summary-divider{height:1px;background:rgba(191,219,254,.5);margin:10px 0;}
.bkcal-summary-row--total{font-size:16px;font-weight:800;color:#0f172a;}
.bkcal-warning{background:#fef3c7;border:1.5px solid #fde68a;border-radius:10px;padding:12px 14px;font-size:13px;color:#92400e;margin-bottom:14px;}
.bkcal-fields{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;}
.bkcal-field{display:flex;flex-direction:column;gap:5px;}
.bkcal-field--full{grid-column:1/-1;}
.bkcal-field label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#64748b;}
.bkcal-field label span{color:#ef4444;}
.bkcal-field input,.bkcal-field textarea{padding:11px 14px;border:1.5px solid #e2e8f0;border-radius:11px;font-size:14px;color:#0f172a;background:rgba(255,255,255,.8);transition:border-color .18s;font-family:'Barlow',sans-serif!important;resize:none;}
.bkcal-field input:focus,.bkcal-field textarea:focus{outline:none;border-color:var(--p);}
.bkcal-submit{width:100%;padding:16px;border-radius:14px;border:none;background:var(--p,#3b82f6);color:#fff!important;font-size:17px!important;font-weight:900!important;cursor:pointer;transition:all .18s;text-transform:uppercase;letter-spacing:.5px;margin-top:4px;font-family:'Barlow',sans-serif!important;}
.bkcal-submit:hover:not(:disabled){background:var(--ph,#2563eb);transform:translateY(-1px);box-shadow:0 8px 28px rgba(59,130,246,.35);}
.bkcal-submit:disabled{opacity:.55;cursor:not-allowed;}
.bkcal-booking-msg{margin-top:12px;}
.bkcal-panel{transition:opacity .3s ease;}
.bkcal-glass-card{animation:bkcal-fadein .4s ease both;}
@keyframes bkcal-fadein{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
@media(max-width:640px){
    .bkcal-fields{grid-template-columns:1fr;}
    .bkcal-modal-box{margin:16px;padding:20px 16px;}
    .bkcal-glass-card{padding:16px;}
    .bkcal-day-badge{display:none;}
    .bkcal-slot-addbar{left:12px;right:12px;transform:none;width:auto;max-width:none;bottom:92px;}
}
</style>

<script>
(function(){
    const minDate=new Date();minDate.setHours(0,0,0,0);
    const maxDate=new Date('<?php echo $max_date;?>');
    const taxRate=<?php echo $tax_rate;?>;
    const currencySymbol='<?php echo $currency_sym;?>';

    let currentMonth=new Date('<?php echo date('Y-m');?>-01');
    let selectedDate=null,selectedService=null,selectedServiceData=null;
    let selectedTime=null,selectedEndTime=null;
    let dateAvailability={},selectedSlots=[],bookingCart=[];

    const $=id=>document.getElementById(id);
    const calView=$('bkcal-calendar-view'),slotsView=$('bkcal-slots-view');
    const modal=$('booking-modal'),modalOverlay=modal.querySelector('.bkcal-modal-overlay');
    const serviceFilter=$('customer-service-filter');
    const completeBtn=$('complete-booking-btn');
    const slotPanel=$('bk-selected-slot-panel');
    const slotTitle=$('bk-selected-slot-title'),slotMeta=$('bk-selected-slot-meta');
    const footer=$('bkcal-footer'),footerTotal=$('bkcal-footer-total'),cartCount=$('bk-cart-count');

    function formatDate(d){return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');}
    function bkPrice(n){return currencySymbol+parseFloat(n).toFixed(2);}
    function cartKey(i){return[i.service,i.date,i.time].join('|');}
    function ajax(body,cb){fetch(ajaxurl,{method:'POST',body}).then(r=>r.json()).then(cb).catch(console.error);}

    renderCalendar();loadMonthAvailability();

    $('prev-month').addEventListener('click',()=>{currentMonth.setMonth(currentMonth.getMonth()-1);renderCalendar();loadMonthAvailability();});
    $('next-month').addEventListener('click',()=>{currentMonth.setMonth(currentMonth.getMonth()+1);renderCalendar();loadMonthAvailability();});

    window.loadMonthAvailability = window.loadMonthAvailability || function(){
        const yr=currentMonth.getFullYear(),mo=currentMonth.getMonth();
        const fd=new FormData();
        fd.append('action','bk_get_month_availability');
        fd.append('start_date',formatDate(new Date(yr,mo,1)));
        fd.append('end_date',formatDate(new Date(yr,mo+1,0)));
        fd.append('business_id',bkBusinessId);
        ajax(fd,json=>{if(json.success){dateAvailability=json.data;updateCalendarColors();}});
    }

    window.updateCalendarColors = window.updateCalendarColors || function(){
        document.querySelectorAll('.bkcal-day:not(.bkcal-day--other)').forEach(day=>{
            const d=day.dataset.date,data=dateAvailability[d];
            day.classList.remove('bkcal-day--low','bkcal-day--med','bkcal-day--full','bkcal-day--no-hours');
            day.querySelector('.bkcal-day-badge')?.remove();
            if(!data||data.total_slots===0){day.classList.add('bkcal-day--no-hours');return;}
            const avail=data.total_slots-data.booked_slots;
            if(avail===0||data.percentage>=100){day.classList.add('bkcal-day--full');}
            else if(data.percentage>=50){day.classList.add('bkcal-day--med');}
            else{day.classList.add('bkcal-day--low');}
            const badge=document.createElement('div');badge.className='bkcal-day-badge';badge.textContent=avail+'/'+data.total_slots;day.appendChild(badge);
        });
        /* also refresh strip colors if strip is visible */
        updateDateStripColors();
    }

    function renderCalendar(){
        const yr=currentMonth.getFullYear(),mo=currentMonth.getMonth();
        $('calendar-month-year').textContent=new Date(yr,mo).toLocaleDateString('en-US',{month:'long',year:'numeric'});
        const firstDay=new Date(yr,mo,1),lastDay=new Date(yr,mo+1,0);
        const startDay=firstDay.getDay();
        let html='';
        for(let i=startDay-1;i>=0;i--) html+=`<div class="bkcal-day bkcal-day--other">${new Date(yr,mo,0).getDate()-i}</div>`;
        for(let day=1;day<=lastDay.getDate();day++){
            const date=new Date(yr,mo,day),ds=formatDate(date);
            let cls='bkcal-day';
            if(date<minDate||date>maxDate) cls+=' bkcal-day--disabled';
            if(ds===selectedDate) cls+=' bkcal-day--selected';
            html+=`<div class="${cls}" data-date="${ds}">${day}</div>`;
        }
        const rem=42-(startDay+lastDay.getDate());
        for(let day=1;day<=rem;day++) html+=`<div class="bkcal-day bkcal-day--other">${day}</div>`;
        $('calendar-days').innerHTML=html;

        document.querySelectorAll('.bkcal-day:not(.bkcal-day--disabled):not(.bkcal-day--other)').forEach(day=>{
            day.addEventListener('click',function(){
                if(this.classList.contains('bkcal-day--full')||this.classList.contains('bkcal-day--no-hours')) return;
                selectDate(this.dataset.date);
                /* transition to slots view */
                calView.style.opacity='0';
                setTimeout(()=>{
                    calView.style.display='none';
                    slotsView.style.display='block';
                    slotsView.style.opacity='0';
                    setTimeout(()=>slotsView.style.opacity='1',10);
                    buildDateStrip();
                    loadSlotsTable();
                },300);
            });
        });
        updateCalendarColors();
    }

    /* ── selectDate: shared helper used by calendar click and strip chip click ── */
    function selectDate(ds){
        selectedDate=ds;
        document.querySelectorAll('.bkcal-day').forEach(d=>d.classList.remove('bkcal-day--selected'));
        const calDay=document.querySelector(`.bkcal-day[data-date="${ds}"]`);
        if(calDay) calDay.classList.add('bkcal-day--selected');

        const dateObj=new Date(ds);dateObj.setHours(12,0,0,0);
        $('selected-date-display').textContent=dateObj.toLocaleDateString('en-US',{weekday:'long',month:'long',day:'numeric'});
    }

    /* ── DATE STRIP: build chips for all days in current month ── */
    function buildDateStrip(){
        const yr=currentMonth.getFullYear(),mo=currentMonth.getMonth();
        const lastDay=new Date(yr,mo+1,0).getDate();
        const dayLabels=['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
        let html='';
        for(let day=1;day<=lastDay;day++){
            const date=new Date(yr,mo,day),ds=formatDate(date);
            let cls='bkcal-date-chip';
            const data=dateAvailability[ds];
            if(date<minDate||date>maxDate) cls+=' bkcal-date-chip--disabled';
            else if(data&&(data.total_slots===0)) cls+=' bkcal-date-chip--nohours';
            else if(data&&(data.booked_slots>=data.total_slots||data.percentage>=100)) cls+=' bkcal-date-chip--full';
            if(ds===selectedDate) cls+=' bkcal-date-chip--selected';
            html+=`<div class="${cls}" data-date="${ds}">
                <span class="bkcal-chip-day">${dayLabels[date.getDay()]}</span>
                <span class="bkcal-chip-num">${day}</span>
            </div>`;
        }
        const strip=$('bkcal-date-strip');
        strip.innerHTML=html;

        /* scroll selected chip into view */
        const selChip=strip.querySelector('.bkcal-date-chip--selected');
        if(selChip) setTimeout(()=>selChip.scrollIntoView({behavior:'smooth',block:'nearest',inline:'center'}),80);

        /* chip click handler */
        strip.querySelectorAll('.bkcal-date-chip').forEach(chip=>{
            chip.addEventListener('click',function(){
                if(this.classList.contains('bkcal-date-chip--disabled')||
                   this.classList.contains('bkcal-date-chip--full')||
                   this.classList.contains('bkcal-date-chip--nohours')) return;
                /* deselect all chips */
                strip.querySelectorAll('.bkcal-date-chip').forEach(c=>c.classList.remove('bkcal-date-chip--selected'));
                this.classList.add('bkcal-date-chip--selected');
                selectDate(this.dataset.date);
                /* reset slot selections */
                selectedSlots=[];slotPanel.style.display='none';
                loadSlotsTable();
            });
        });
    }

    function updateDateStripColors(){
        document.querySelectorAll('.bkcal-date-chip').forEach(chip=>{
            const ds=chip.dataset.date,data=dateAvailability[ds];
            chip.classList.remove('bkcal-date-chip--nohours','bkcal-date-chip--full');
            if(!data||data.total_slots===0) chip.classList.add('bkcal-date-chip--nohours');
            else if(data.booked_slots>=data.total_slots||data.percentage>=100) chip.classList.add('bkcal-date-chip--full');
        });
    }

    $('back-to-calendar').addEventListener('click',()=>{
        slotsView.style.opacity='0';
        setTimeout(()=>{slotsView.style.display='none';calView.style.display='block';calView.style.opacity='1';},300);
    });

    serviceFilter.addEventListener('change',()=>{if(selectedDate) loadSlotsTable();});

    function loadSlotsTable(){
        if(!selectedDate)return;
        selectedSlots=[];slotPanel.style.display='none';
        $('slots-loading').style.display='flex';
        $('slots-table-container').style.display='none';
        $('slots-message').style.display='none';
        const fd=new FormData();
        fd.append('action','bk_get_slots_table');
        fd.append('date',selectedDate);
        fd.append('service_filter',serviceFilter.value);
        fd.append('business_id',bkBusinessId);
        ajax(fd,json=>{
            $('slots-loading').style.display='none';
            if(json.success&&json.data.slots?.length){
                renderSlotsTable(json.data);
            } else {
                const msg=$('slots-message');
                msg.style.display='block';
                msg.innerHTML='<strong>No available slots</strong><br>No time slots found for this date. Please try another day.';
            }
        });
    }

    window.renderSlotsTable = window.renderSlotsTable || function(data){
        $('slots-table-head').innerHTML='<tr>'+data.services.map(s=>`<th>${s.name}</th>`).join('')+'</tr>';
        $('slots-table-body').innerHTML=data.slots.map(ts=>'<tr>'+data.services.map(s=>{
            const slot=data.slots_by_service[`${s.id}_${ts.time}`];
            if(slot?.available){
                return `<td><button class="bkcal-slot-btn bkcal-slot-btn--available" data-service="${s.id}" data-service-name="${s.name}" data-service-price="${s.price}" data-service-duration="${s.duration}" data-time="${ts.time}" data-end-time="${slot.end_time}">${formatTime12Hour(ts.time)}<br><small style="font-size:10px;font-weight:500;opacity:.7;">→ ${formatTime12Hour(slot.end_time)}</small></button></td>`;
            }
            return `<td><div class="bkcal-slot-btn bkcal-slot-btn--booked">Booked</div></td>`;
        }).join('')+'</tr>').join('');
        $('slots-table-container').style.display='block';

        document.querySelectorAll('.bkcal-slot-btn.bkcal-slot-btn--available').forEach(btn=>{
            btn.addEventListener('click',function(){
                if(this.classList.contains('bkcal-slot-btn--in-cart'))return;
                const item={service:this.dataset.service,serviceData:{id:this.dataset.service,name:this.dataset.serviceName,price:this.dataset.servicePrice,duration:this.dataset.serviceDuration},date:selectedDate,time:this.dataset.time,endTime:this.dataset.endTime};
                const key=cartKey(item),idx=selectedSlots.findIndex(s=>cartKey(s)===key);
                if(idx>=0){selectedSlots.splice(idx,1);this.classList.remove('bkcal-slot-btn--selected');}
                else{selectedSlots.push(item);this.classList.add('bkcal-slot-btn--selected');}
                selectedService=this.dataset.service;selectedTime=this.dataset.time;selectedEndTime=this.dataset.endTime;
                selectedServiceData={id:this.dataset.service,name:this.dataset.serviceName,price:this.dataset.servicePrice,duration:this.dataset.serviceDuration};
                updateSlotPanel();
            });
        });
        markCartButtons();
    }

    function updateSlotPanel(){
        if(!selectedSlots.length){slotPanel.style.display='none';return;}
        const total=selectedSlots.reduce((s,i)=>s+parseFloat(i.serviceData.price||0),0);
        slotTitle.textContent=selectedSlots.length+' time'+(selectedSlots.length===1?'':'s')+' selected';
        slotMeta.textContent=bkPrice(total)+' · Tap "Add to Cart" to proceed';
        slotPanel.style.display='flex';
    }

    $('bk-add-selected-slot').addEventListener('click',()=>{
        if(!selectedSlots.length){alert('Please select at least one time slot.');return;}
        selectedSlots.forEach(item=>{if(!bookingCart.some(c=>cartKey(c)===cartKey(item)))bookingCart.push({...item,quantity:1});});
        selectedSlots=[];
        updateFooterBar();slotPanel.style.display='none';
        document.querySelectorAll('.bkcal-slot-btn').forEach(b=>b.classList.remove('bkcal-slot-btn--selected'));
        markCartButtons();
    });

    function markCartButtons(){
        document.querySelectorAll('.bkcal-slot-btn.bkcal-slot-btn--available').forEach(btn=>{
            const inCart=bookingCart.some(i=>cartKey(i)===[btn.dataset.service,selectedDate,btn.dataset.time].join('|'));
            btn.classList.toggle('bkcal-slot-btn--in-cart',inCart);
            if(inCart){btn.innerHTML='In Cart';}
            else{btn.innerHTML=formatTime12Hour(btn.dataset.time)+'<br><small style="font-size:10px;font-weight:500;opacity:.7;">→ '+formatTime12Hour(btn.dataset.endTime)+'</small>';}
        });
    }

    /* ── FOOTER BAR (replaces floating cart button) ── */
    function updateFooterBar(){
        cartCount.textContent=bookingCart.length;
        if(!bookingCart.length){footer.style.display='none';return;}
        const sub=bookingCart.reduce((s,i)=>s+parseFloat(i.serviceData.price||0),0);
        const total=sub*(1+taxRate/100);
        footerTotal.textContent=bkPrice(total)+(taxRate?' incl. tax':'');
        footer.style.display='flex';
    }

    window.renderCartReview = window.renderCartReview || function(){
        const wrap=$('bk-cart-review');
        wrap.innerHTML=bookingCart.map((item,i)=>{
            const d=new Date(item.date);d.setHours(12,0,0,0);
            return `<div class="bkcal-cart-item"><div><strong>${item.serviceData.name}</strong><small>${d.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'})} · ${formatTime12Hour(item.time)} → ${formatTime12Hour(item.endTime)}</small></div><button type="button" data-index="${i}">✕</button></div>`;
        }).join('');
        wrap.querySelectorAll('button').forEach(btn=>{
            btn.addEventListener('click',function(){
                bookingCart.splice(+this.dataset.index,1);
                updateFooterBar();renderCartReview();markCartButtons();updateModalCalcs();
                $('modal-service-name').textContent=bookingCart.length+' booking'+(bookingCart.length===1?'':'s');
                if(!bookingCart.length)closeModal();
            });
        });
    }

    $('bk-floating-checkout').addEventListener('click',()=>{
        if(!bookingCart.length)return;
        const f=bookingCart[0];
        selectedService=f.service;selectedServiceData=f.serviceData;selectedDate=f.date;selectedTime=f.time;selectedEndTime=f.endTime;
        openModal();
    });

    window.openModal = window.openModal || function(){
        const sw=window.innerWidth-document.documentElement.clientWidth;
        document.body.style.overflow='hidden';document.body.style.paddingRight=sw+'px';
        $('modal-service-name').textContent=bookingCart.length+' booking'+(bookingCart.length===1?'':'s');
        $('hidden-service-id').value=selectedService;
        $('hidden-booking-date').value=selectedDate;
        $('hidden-start-time').value=selectedTime;
        $('availability-warning').style.display='none';
        completeBtn.disabled=false;
        updateModalCalcs();renderCartReview();
        modal.classList.add('active');
    }

    window.closeModal = window.closeModal || function(){
        modal.classList.remove('active');
        document.body.style.overflow='';document.body.style.paddingRight='';
    }

    modalOverlay.addEventListener('click',closeModal);
    modal.querySelector('.bkcal-modal-close').addEventListener('click',closeModal);
    document.addEventListener('keydown',e=>{if(e.key==='Escape'&&modal.classList.contains('active'))closeModal();});

    function updateModalCalcs(){
        if(bookingCart.length){
            const sub=bookingCart.reduce((s,i)=>s+parseFloat(i.serviceData.price||0),0);
            const dur=bookingCart.reduce((s,i)=>s+parseInt(i.serviceData.duration||0),0);
            const total=sub*(1+taxRate/100);
            $('calc-unit-price').textContent='Multiple services';
            $('calc-quantity').textContent=bookingCart.length;
            $('calc-total-duration').textContent=dur+' min';
            $('calc-end-time').textContent='Separate times';
            $('calc-total-price').textContent=bkPrice(total)+(taxRate?' incl. tax':'');
            $('modal-duration').textContent=dur;
            $('hidden-quantity').value=bookingCart.length;
            $('hidden-amount').value=total;
            return;
        }
        if(!selectedServiceData)return;
        const price=parseFloat(selectedServiceData.price),dur=+selectedServiceData.duration;
        const sub=price,total=sub*(1+taxRate/100);
        const endMs=new Date(selectedDate+' '+selectedTime).getTime()+(dur*60000);
        const endTime=new Date(endMs).toTimeString().substring(0,5);
        $('calc-unit-price').textContent=bkPrice(price);
        $('calc-quantity').textContent=1;
        $('calc-total-duration').textContent=dur+' min';
        $('calc-end-time').textContent=formatTime12Hour(endTime);
        $('calc-total-price').textContent=bkPrice(total)+(taxRate?' incl. tax':'');
        $('modal-duration').textContent=dur;
        $('hidden-end-time').value=endTime;
        $('hidden-quantity').value=1;
        $('hidden-amount').value=total;
    }

    $('booking-form').addEventListener('submit',function(e){
        e.preventDefault();
        if(!selectedTime){alert('Please select a time slot');return;}
        const btn=this.querySelector('button[type="submit"]');
        btn.disabled=true;btn.textContent='Processing…';
        const cart=bookingCart.length?bookingCart.slice():[{service:selectedService,serviceData:selectedServiceData,date:selectedDate,time:selectedTime,endTime:selectedEndTime,quantity:1}];
        const fd=new FormData(this);
        fd.append('action','bk_book_cart');
        fd.append('nonce','<?php echo $nonce;?>');
        fd.append('business_id',bkBusinessId);
        fd.append('cart',JSON.stringify(cart));
        ajax(fd,json=>{
            const msgEl=$('booking-message'),data=json.data||{};
            if(json.success){
                bookingCart=[];updateFooterBar();
                const redir=data.redirect_url||data.redirect;
                msgEl.innerHTML=`<div style="padding:14px;background:#d1fae5;border-radius:10px;color:#065f46;font-weight:600;margin-top:10px;">${data.message||(redir?'Booking created! Redirecting…':'Booking confirmed!')}</div>`;
                if(redir)setTimeout(()=>window.location.href=redir,1500);
                else setTimeout(()=>window.location.reload(),2000);
            } else {
                msgEl.innerHTML=`<div style="padding:14px;background:#fee2e2;border-radius:10px;color:#991b1b;font-weight:600;margin-top:10px;">${data.message||'An error occurred. Please try again.'}</div>`;
                btn.disabled=false;btn.textContent='Complete Booking';
            }
        });
    });
})();
</script>
<?php
    return ob_get_clean();
}


/* ═══════════════════════════════════════════════════════
   2.  DIRECTORY  [bk_directory]
   ═══════════════════════════════════════════════════════ */
function bntm_shortcode_bk_directory() {
    global $wpdb;
    $services_table = $wpdb->prefix . 'bk_services';
    $profiles_table = $wpdb->prefix . 'bk_business_profiles';

    $placeholder_cover = 'data:image/svg+xml;utf8,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 520"><rect width="800" height="520" fill="#0f172a"/><rect x="60" y="70" width="680" height="380" rx="28" fill="#1e293b"/><circle cx="230" cy="220" r="54" fill="#334155"/><path d="M100 380l138-112 96 82 122-146 156 176H100z" fill="#475569"/></svg>');
    $placeholder_logo  = 'data:image/svg+xml;utf8,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 160"><rect width="160" height="160" rx="30" fill="#1e293b"/><circle cx="80" cy="62" r="24" fill="#475569"/><path d="M36 128c9-22 29-34 44-34s35 12 44 34" fill="#475569"/></svg>');

    $show_onboarding = empty($_COOKIE['bk_onboarded']);

    $business_ids = $wpdb->get_col(
        "SELECT DISTINCT s.business_id FROM {$services_table} s
         INNER JOIN {$profiles_table} p ON p.business_id=s.business_id
         WHERE s.status='active' AND s.business_id>0 AND p.is_listed=1
         ORDER BY s.business_id ASC"
    );

    $businesses = [];
    foreach ($business_ids as $bid) {
        $p    = bk_get_business_profile($bid);
        $name = bk_get_business_name($bid);
        $cnt  = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$services_table} WHERE business_id=%d AND status='active'", $bid
        )));
        $rat      = bk_get_business_rating_summary($bid);
        $sports   = bk_get_business_categories($bid);
        $min_price = $wpdb->get_var($wpdb->prepare(
            "SELECT MIN(price) FROM {$services_table} WHERE business_id=%d AND status='active'", $bid
        ));
        $businesses[] = [
            'id'       => $bid,
            'name'     => $name,
            'location' => $p->location ?? '',
            'cover'    => esc_url_raw(trim($p->cover_url ?? '')) ?: $placeholder_cover,
            'logo'     => esc_url_raw(trim($p->logo_url ?? '')) ?: $placeholder_logo,
            'services' => $cnt,
            'rating'   => $rat['total'] ? floatval($rat['average']) : null,
            'total'    => $rat['total'],
            'lat'      => floatval($p->latitude  ?? 0),
            'lng'      => floatval($p->longitude ?? 0),
            'url'      => bk_get_profile_url($bid),
            'bookurl'  => bk_get_booking_url($bid),
            'sports'   => $sports,
            'price'    => $min_price ? bk_format_price($min_price) : '',
            'featured' => !empty($p->is_featured_home),
            'desc'     => wp_trim_words(
                $p->description ?: bk_get_business_setting($bid,'bk_description',''),
                12,'…'
            ),
        ];
    }

    $all_sports          = bk_get_global_categories();
    $featured_businesses = array_values(array_filter($businesses, fn($b) => !empty($b['featured'])));
    usort($businesses,          fn($a,$b) => strcasecmp($a['name'],$b['name']));
    usort($featured_businesses, fn($a,$b) => strcasecmp($a['name'],$b['name']));

    $biz_json          = json_encode($businesses);
    $home_slides       = bk_get_home_slides();
    $hero_slides       = array_values(array_filter($home_slides, static fn($slide) => !empty($slide['show_hero'])));
    $onboarding_slides = array_values(array_filter($home_slides, static fn($slide) => !empty($slide['show_onboarding'])));
    $first_hero_slide  = $hero_slides[0] ?? ($home_slides[0] ?? []);
    $hero_eyebrow      = $first_hero_slide['pre_title'] ?? bk_get_setting_with_fallbacks(['bk_admin_home_hero_eyebrow'],'Explore the');
    $hero_title        = $first_hero_slide['title'] ?? bk_get_setting_with_fallbacks(['bk_admin_home_hero_title'],get_bloginfo('name'));
    $hero_description  = $first_hero_slide['description'] ?? bk_get_setting_with_fallbacks(['bk_admin_home_hero_description'],'Find featured venues, browse the directory, and jump into your next booking faster.');
    $primary_color    = bk_get_primary_color();
    $primary_hover    = bk_get_primary_hover_color();

    $site_logo_raw = bntm_get_site_logo();
    $site_logo_url = '';
    if (is_string($site_logo_raw) && trim($site_logo_raw) !== '') {
        if (filter_var(trim($site_logo_raw), FILTER_VALIDATE_URL)) {
            $site_logo_url = esc_url(trim($site_logo_raw));
        } else {
            preg_match('/src=["\']([^"\']+)["\']/', $site_logo_raw, $m);
            if (!empty($m[1])) $site_logo_url = esc_url($m[1]);
        }
    }
    if (!$site_logo_url) {
        $logo_id = get_theme_mod('custom_logo');
        if ($logo_id) {
            $src = wp_get_attachment_image_url($logo_id,'full');
            if ($src) $site_logo_url = esc_url($src);
        }
    }

    bntm_shared_head();
    ob_start();
    echo bk_get_primary_root_style_tag();
?>
<style>
:root{--p:<?php echo esc_attr($primary_color);?>;--ph:<?php echo esc_attr($primary_hover);?>;}
#bkd-app,#bkd-app *{font-family:'Barlow',sans-serif!important;box-sizing:border-box;}
/* Display font classes */

/* ── ONBOARDING ── */
#bk-onboarding{position:fixed;inset:0;z-index:9999;background:#0a0a0a;overflow:hidden;}
.bk-onboarding-seen #bk-onboarding{display:none!important;}
.bko-logo{position:absolute;top:52px;left:24px;z-index:10;}
.bko-logo img{max-height:38px;width:auto;max-width:140px;object-fit:contain;filter:brightness(0) invert(1);}
.bko-imgs{position:absolute;inset:0;}
.bko-img{position:absolute;inset:0;background:center/cover no-repeat;opacity:0;transition:opacity .75s ease,transform 8s ease;transform:scale(1.06);}
.bko-img.is-active{opacity:1;transform:scale(1);}
.bko-grad{position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,.9) 0%,rgba(0,0,0,.5) 40%,transparent 80%);z-index:1;}
.bko-bottom{position:absolute;bottom:0;left:0;right:0;z-index:2;padding:0 24px 52px;}
.bko-text-track{overflow:hidden;position:relative;min-height:140px;margin-bottom:20px;}
.bko-text-slide{position:absolute;inset:0;display:flex;flex-direction:column;justify-content:flex-end;opacity:0;transform:translateY(20px);transition:opacity .45s ease,transform .45s ease;pointer-events:none;}
.bko-text-slide.is-active{opacity:1;transform:translateY(0);position:relative;pointer-events:auto;}
.bko-title{color:#fff;font-size:38px!important;font-weight:900!important;line-height:1.1;margin:0 0 10px;letter-spacing:-.5px;}
.bko-sub{color:rgba(255,255,255,.55);font-size:14px;line-height:1.6;margin:0;}
.bko-dots{display:flex;gap:6px;margin-bottom:22px;}
.bko-dot{width:8px;height:8px;border-radius:50%;background:rgba(255,255,255,.25);cursor:pointer;transition:.3s;border:none;}
.bko-dot.is-on{background:#fff;width:26px;border-radius:4px;}
.bko-cta{width:100%;padding:18px;background:#fff;color:#111!important;border:none;border-radius:999px;font-size:17px!important;font-weight:700!important;cursor:pointer;letter-spacing:.1px;transition:all .18s;font-family:'Barlow',sans-serif!important;}
.bko-cta:hover{background:#f0f0f0;transform:scale(1.01);}
#bk-onboarding.is-exiting{animation:bkoExit .5s cubic-bezier(.4,0,.2,1) forwards;}
@keyframes bkoExit{to{opacity:0;transform:translateY(-20px) scale(.97);}}

/* ── WRAP ── */
.bkd-wrap{max-width:520px;margin:0 auto;padding:26px 16px 100px;background:linear-gradient(165deg,#f0f4f8 0%,#e8eef5 45%,#f5f5f0 100%);min-height:100vh;}

/* ── HEADER ── */
.bkd-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;}
.bkd-logo img{max-height:36px;width:auto;max-width:120px;object-fit:contain;}
.bkd-greeting{font-size:13px;color:#94a3b8;margin:0 0 3px;font-weight:500;}
.bkd-headline{font-size:36px!important;font-weight:900!important;line-height:1.02;margin:0;color:#0f172a!important;}
.bkd-headline em{font-style:italic;font-size:46px!important;display:block;line-height:.95;color:#0f172a;}

/* ── SEARCH ── */
.bkd-search-wrap{margin-bottom:14px;}
.bkd-search-box{display:flex;align-items:center;gap:10px;background:rgba(255,255,255,.88);border:1.5px solid rgba(0,0,0,.06);border-radius:16px;padding:13px 16px;backdrop-filter:blur(12px);box-shadow:0 2px 16px rgba(0,0,0,.05);transition:all .2s;}
.bkd-search-box:focus-within{box-shadow:0 4px 24px rgba(0,0,0,.09);border-color:var(--p);}
.bkd-search-box svg{stroke:#94a3b8;flex-shrink:0;width:16px;height:16px;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
.bkd-search-box input{border:none;outline:none;flex:1;background:transparent;font-size:15px;color:#0f172a;font-family:'Barlow',sans-serif!important;}
.bkd-search-box input::placeholder{color:#b0bcc8;}

/* ── PILLS ── */
.bkd-pills-row{display:flex;gap:7px;overflow-x:auto;scrollbar-width:none;padding-bottom:4px;margin-bottom:22px;}
.bkd-pills-row::-webkit-scrollbar{display:none;}
.bkd-pill{padding:8px 18px!important;border-radius:999px;border:1.5px solid rgba(0,0,0,.07);background:rgba(255,255,255,.82);font-size:13px!important;font-weight:700!important;cursor:pointer;white-space:nowrap;color:#475569!important;transition:.18s;backdrop-filter:blur(8px);}
.bkd-pill.bkd-pill--active{background:var(--p)!important;color:#fff!important;border-color:var(--p)!important;box-shadow:0 4px 16px rgba(0,0,0,.15);}
.bkd-pill:hover:not(.bkd-pill--active){background:#fff;border-color:rgba(0,0,0,.1);}

/* ── FEATURED AREA ── */
#bkd-featured-area{display:grid;gap:14px;margin-bottom:22px;transition:opacity .3s,transform .3s,max-height .38s,margin .3s;opacity:1;max-height:2000px;}
#bkd-featured-area.is-hidden{opacity:0;max-height:0;margin-bottom:0;pointer-events:none;overflow:hidden;}

/* HERO */
.bkd-hero-carousel{position:relative;border-radius:22px;overflow:hidden;height:500px;background:#1e293b;}
.bkd-hero-track{position:absolute;inset:0;}
.bkd-hero-slide{position:absolute;inset:0;opacity:0;transition:opacity .75s ease;}
.bkd-hero-slide.is-active{opacity:1;}
.bkd-hero-img{width:100%;height:100%;object-fit:cover;}
.bkd-hero-grad{position:absolute;inset:0;background:linear-gradient(to top,rgba(15,23,42,.95) 0%,rgba(15,23,42,.25) 55%,transparent 100%);}
.bkd-hero-content{position:absolute;bottom:0;left:0;right:0;z-index:1;padding:22px 20px;}
.bkd-hero-eyebrow{color:rgba(255,255,255,.6);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:2px;margin:0 0 5px;}
.bkd-hero-title{color:#fff!important;font-size:28px!important;font-weight:900!important;margin:0 0 6px;line-height:1.1;text-transform:uppercase;}
.bkd-hero-desc{color:rgba(255,255,255,.6);font-size:13px;margin:0;line-height:1.5;}
.bkd-hero-dots{position:absolute;bottom:16px;right:18px;z-index:2;display:flex;gap:5px;}
.bkd-hdot{width:6px;height:6px;border-radius:50%;background:rgba(255,255,255,.3);cursor:pointer;transition:.3s;}
.bkd-hdot--on{background:#fff;width:18px;border-radius:3px;}

/* FEATURED GRID */
.bkd-featured-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;}
.bkd-feat-card{position:relative;min-height:190px;border-radius:20px;overflow:hidden;background:center/cover no-repeat;text-decoration:none!important;display:flex;align-items:flex-end;padding:14px;transition:transform .22s,box-shadow .22s;}
.bkd-feat-card:hover{transform:translateY(-3px);box-shadow:0 16px 40px rgba(0,0,0,.25);}
.bkd-feat-card-grad{position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,.9) 0%,rgba(0,0,0,.2) 60%,transparent 100%);}
.bkd-feat-logo-wrap{position:absolute;top:12px;left:12px;width:44px;height:44px;border-radius:12px;overflow:hidden;background:rgba(0,0,0,.6);backdrop-filter:blur(8px);z-index:1;border:1px solid rgba(255,255,255,.15);}
.bkd-feat-logo{width:100%;height:100%;object-fit:cover;}
.bkd-feat-body{position:relative;z-index:1;}
.bkd-feat-name{font-size:17px!important;font-weight:900!important;color:#fff!important;text-transform:uppercase;line-height:1.05;margin-bottom:7px;}
.bkd-feat-cta{display:inline-flex;align-items:center;background:rgba(255,255,255,.15);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.2);color:#fff!important;border-radius:999px;padding:5px 13px;font-size:11px!important;font-weight:800!important;text-transform:uppercase;letter-spacing:.4px;}

/* SECTION HEADER */
.bkd-section-label{display:flex;align-items:center;gap:8px;margin-bottom:14px;}
.bkd-section-label-icon{font-size:14px;}
.bkd-section-label-text{font-size:18px!important;font-weight:900!important;color:#0f172a!important;text-transform:uppercase;letter-spacing:.3px;}
.bkd-section-label-sub{font-size:11px;color:#94a3b8;font-weight:500;margin-top:1px;display:block;}
.bkd-section-label-badge{background:#0f172a;color:#fff;font-size:11px;font-weight:700!important;border-radius:999px;padding:3px 10px;margin-left:auto;}

/* MAP STRIP */
.bkd-map-section{margin-bottom:20px;}
.bkd-map-teaser{display:flex;align-items:center;justify-content:space-between;background:rgba(255,255,255,.85);border:1px solid rgba(0,0,0,.06);border-radius:16px;padding:14px 16px;cursor:pointer;transition:all .2s;backdrop-filter:blur(12px);box-shadow:0 2px 14px rgba(0,0,0,.05);}
.bkd-map-teaser:hover{box-shadow:0 6px 24px rgba(0,0,0,.1);transform:translateY(-1px);}
.bkd-map-teaser-left{display:flex;align-items:center;gap:12px;}
.bkd-map-pin-wrap{width:42px;height:42px;border-radius:12px;background:linear-gradient(135deg,var(--p),var(--ph));display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 4px 14px rgba(0,0,0,.2);}
.bkd-map-pin-wrap svg{width:18px;height:18px;stroke:#fff!important;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
.bkd-map-title{font-size:14px;font-weight:800!important;color:#0f172a;text-transform:uppercase;letter-spacing:.2px;}
.bkd-map-sub{font-size:12px;color:#94a3b8;margin-top:1px;}
.bkd-map-btn{display:flex;align-items:center;gap:5px;background:#0f172a;color:#fff!important;border:none;border-radius:999px;padding:9px 18px;font-size:13px!important;font-weight:700!important;cursor:pointer;text-transform:uppercase;letter-spacing:.3px;transition:all .18s;}
.bkd-map-btn:hover{background:#1e3a5f;transform:scale(1.03);}
.bkd-map-btn svg{width:13px;height:13px;stroke:#fff!important;fill:none;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round;}

/* Full map overlay */
.bkd-map-overlay{position:fixed;inset:0;z-index:12000;opacity:0;visibility:hidden;pointer-events:none;transition:opacity .28s,visibility .28s;}
.bkd-map-overlay.is-open{opacity:1;visibility:visible;pointer-events:auto;}
.bkd-map-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.72);backdrop-filter:blur(8px);}
.bkd-map-shell{position:relative;width:min(calc(100vw - 24px),1100px);height:min(calc(100vh - 24px),820px);margin:12px auto;border-radius:22px;overflow:hidden;background:#fff;box-shadow:0 28px 80px rgba(15,23,42,.3);transform:translateY(20px) scale(.97);transition:transform .3s cubic-bezier(.4,0,.2,1);}
.bkd-map-overlay.is-open .bkd-map-shell{transform:translateY(0) scale(1);}
.bkd-map-close{position:absolute;top:14px;right:14px;z-index:12100;width:44px;height:44px;background:rgba(15,23,42,.8);border:none;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(8px);}
.bkd-map-close svg{width:16px;height:16px;stroke:#fff!important;fill:none;stroke-width:2.5;stroke-linecap:round;}
.bkd-map-container{width:100%;height:100%;}
.bkd-map-popup{position:absolute;left:0;top:0;transform:translate(-50%,-108%);z-index:2000;background:rgba(255,255,255,.95);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.5);border-radius:16px;box-shadow:0 16px 48px rgba(0,0,0,.22);width:240px;overflow:hidden;}
.bkd-popup-x{position:absolute;top:8px;right:8px;background:rgba(0,0,0,.4);border:none;color:#fff;width:24px;height:24px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;z-index:1;}
.bkd-popup-x svg{width:11px;height:11px;stroke:#fff!important;fill:none;stroke-width:2.5;stroke-linecap:round;}
.bkd-popup-img{width:100%;height:110px;object-fit:cover;}
.bkd-popup-img-ph{width:100%;height:110px;background:linear-gradient(135deg,#cbd5e1,#e2e8f0);display:none;}
.bkd-popup-body{padding:11px 13px 13px;}
.bkd-popup-name{font-size:14px!important;font-weight:800!important;color:#0f172a!important;display:block;margin-bottom:2px;text-transform:uppercase;}
.bkd-popup-loc{font-size:11px;color:#94a3b8;margin:0 0 8px;}
.bkd-popup-meta{display:flex;justify-content:space-between;align-items:center;margin-bottom:9px;}
.bkd-popup-rating{color:var(--p);font-size:12px;font-weight:700!important;}
.bkd-popup-price{font-size:11px;font-weight:700!important;color:#0f172a;background:#f1f5f9;border-radius:999px;padding:2px 7px;}
.bkd-popup-btn{display:block;text-align:center;background:#0f172a!important;color:#fff!important;text-decoration:none!important;border-radius:999px;padding:9px;font-size:12px!important;font-weight:800!important;text-transform:uppercase;letter-spacing:.3px;}

/* TILE LIST */
#bkd-list{display:flex;flex-direction:column;gap:10px;}
.bkd-tile{display:flex;background:rgba(255,255,255,.88);border-radius:16px;overflow:hidden;border:1px solid rgba(0,0,0,.04);transition:transform .2s,box-shadow .2s;backdrop-filter:blur(10px);animation:bkdTileIn .35s ease both;}
@keyframes bkdTileIn{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
.bkd-tile:hover{transform:translateY(-2px);box-shadow:0 10px 32px rgba(0,0,0,.1);}
.bkd-tile:nth-child(1){animation-delay:.04s}.bkd-tile:nth-child(2){animation-delay:.08s}.bkd-tile:nth-child(3){animation-delay:.12s}.bkd-tile:nth-child(4){animation-delay:.16s}.bkd-tile:nth-child(n+5){animation-delay:.2s}
.bkd-tile-img-link{width:106px;min-width:106px;align-self:stretch;overflow:hidden;display:block;text-decoration:none;}
.bkd-tile-img{width:100%;height:100%;min-height:108px;object-fit:cover;}
.bkd-tile-img-ph{width:106px;flex-shrink:0;background:linear-gradient(135deg,#e2e8f0,#f1f5f9);min-height:108px;}
.bkd-tile-body{flex:1;padding:12px 13px;display:flex;flex-direction:column;justify-content:space-between;min-width:0;}
.bkd-tile-top{display:flex;justify-content:space-between;align-items:flex-start;gap:6px;margin-bottom:4px;}
.bkd-tile-name-link{text-decoration:none!important;color:inherit!important;}
.bkd-tile-name{font-size:15px!important;font-weight:900!important;color:#0f172a!important;line-height:1.15;text-transform:uppercase;letter-spacing:.2px;}
.bkd-tile-price{background:#f1f5f9;border-radius:999px;padding:3px 9px;font-size:11px;font-weight:700!important;color:#475569;white-space:nowrap;flex-shrink:0;}
.bkd-tile-desc{font-size:12px;color:#94a3b8;line-height:1.5;margin-bottom:9px;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;}
.bkd-tile-bottom{display:flex;align-items:center;justify-content:space-between;}
.bkd-tile-rating{color:var(--p);font-size:13px;font-weight:700!important;}
.bkd-tile-rating span{color:#94a3b8;font-size:11px;font-weight:500!important;}
.bkd-new{color:#94a3b8;font-size:11px;font-weight:600!important;}
.bkd-tile-book{background:#0f172a!important;color:#fff!important;text-decoration:none!important;border-radius:999px;padding:7px 15px;font-size:12px!important;font-weight:800!important;text-transform:uppercase;letter-spacing:.3px;white-space:nowrap;display:inline-block;transition:all .15s;}
.bkd-tile-book:hover{background:#1e3a5f!important;transform:scale(1.04);}
#bkd-no-results{text-align:center;color:#94a3b8;padding:40px 0;font-size:15px;}
.leaflet-popup{display:none!important;}
</style>

<div id="bkd-app">

<?php if ($show_onboarding && !empty($onboarding_slides)): ?>
<script>
(function(){
    try {
        if (window.localStorage && localStorage.getItem('bk_onboarding_seen') === '1') {
            document.cookie = 'bk_onboarded=1;path=/;max-age=31536000';
            document.documentElement.classList.add('bk-onboarding-seen');
        }
    } catch(e) {}
})();
</script>
<div id="bk-onboarding">
    <?php if (!empty($site_logo_url)): ?>
    <div class="bko-logo"><img src="<?php echo $site_logo_url;?>" alt="<?php echo esc_attr(get_bloginfo('name'));?>"></div>
    <?php endif; ?>
    <div class="bko-imgs" id="bko-imgs">
        <?php
        $slides = $onboarding_slides;
        foreach($slides as $i=>$sl):?>
        <div class="bko-img<?php echo $i===0?' is-active':'';?>"<?php if(!empty($sl['image_url'])):?> style="background-image:url('<?php echo esc_url($sl['image_url']);?>')"<?php endif;?>></div>
        <?php endforeach;?>
    </div>
    <div class="bko-grad"></div>
    <div class="bko-bottom">
        <div class="bko-text-track" id="bko-text-track">
            <?php foreach($slides as $i=>$sl):?>
            <div class="bko-text-slide<?php echo $i===0?' is-active':'';?>">
                <?php if(!empty($sl['pre_title'])):?><p class="bko-sub"><?php echo esc_html($sl['pre_title']);?></p><?php endif;?>
                <h2 class="bko-title"><?php echo nl2br(esc_html($sl['title']));?></h2>
                <p class="bko-sub"><?php echo esc_html($sl['description']);?></p>
            </div>
            <?php endforeach;?>
        </div>
        <div class="bko-dots">
            <?php for($j=0;$j<count($slides);$j++):?><span class="bko-dot<?php echo $j===0?' is-on':'';?>" onclick="bkGoSlide(<?php echo $j;?>)"></span><?php endfor;?>
        </div>
        <button class="bko-cta" id="bko-cta" onclick="bkNextSlide()">Get Started</button>
    </div>
</div>
<?php endif;?>

<!-- DIRECTORY -->
<div class="bkd-wrap">
    <div class="bkd-header">
        <div>
            <p class="bkd-greeting">Hi there 👋</p>
            <h1 class="bkd-headline">Explore the<br><em>Courts</em></h1>
        </div>
        <?php if(!empty($site_logo_url)):?>
        <div class="bkd-logo"><img src="<?php echo $site_logo_url;?>" alt="<?php echo esc_attr(get_bloginfo('name'));?>"></div>
        <?php endif;?>
    </div>

    <div class="bkd-search-wrap">
        <div class="bkd-search-box">
            <svg width="16" height="16" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input type="text" id="bkd-search" placeholder="Search courts, clubs…" autocomplete="off">
        </div>
    </div>

    <div class="bkd-pills-row">
        <button class="bkd-pill bkd-pill--active" data-sport="">All</button>
        <?php foreach($all_sports as $sp):?>
        <button class="bkd-pill" data-sport="<?php echo esc_attr($sp);?>"><?php echo esc_html($sp);?></button>
        <?php endforeach;?>
    </div>

    <div id="bkd-featured-area">
        <?php if(!empty($hero_slides)):?>
        <div class="bkd-hero-carousel">
            <div class="bkd-hero-track">
                <?php foreach($hero_slides as $idx=>$hero_slide):?>
                <div class="bkd-hero-slide<?php echo $idx===0?' is-active':'';?>">
                    <img src="<?php echo esc_url($hero_slide['image_url']);?>" alt="" class="bkd-hero-img">
                    <div class="bkd-hero-grad"></div>
                    <div class="bkd-hero-content">
                        <?php if(!empty($hero_slide['pre_title'])):?><p class="bkd-hero-eyebrow"><?php echo esc_html($hero_slide['pre_title']);?></p><?php endif;?>
                        <?php if(!empty($hero_slide['title'])):?><h2 class="bkd-hero-title"><?php echo esc_html($hero_slide['title']);?></h2><?php endif;?>
                        <?php if(!empty($hero_slide['description'])):?><p class="bkd-hero-desc"><?php echo esc_html($hero_slide['description']);?></p><?php endif;?>
                    </div>
                </div>
                <?php endforeach;?>
            </div>
            <?php if(count($hero_slides)>1):?>
            <div class="bkd-hero-dots">
                <?php for($i=0;$i<count($hero_slides);$i++):?><span class="bkd-hdot<?php echo $i===0?' bkd-hdot--on':'';?>" data-idx="<?php echo $i;?>"></span><?php endfor;?>
            </div>
            <?php endif;?>
        </div>
        <?php endif;?>

    <!-- MAP -->
    <div class="bkd-map-section">
        <div class="bkd-map-teaser" onclick="bkdExpandMap()">
            <div class="bkd-map-teaser-left">
                <div class="bkd-map-pin-wrap">
                    <svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                </div>
                <div>
                    <div class="bkd-map-title">Nearby Venues</div>
                    <div class="bkd-map-sub" id="bkd-map-count"><?php echo count($businesses);?> locations on map</div>
                </div>
            </div>
            <button class="bkd-map-btn" onclick="event.stopPropagation();bkdExpandMap()">
                View Map
                <svg viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
            </button>
        </div>
        <div class="bkd-map-overlay" id="bkd-map-panel" aria-hidden="true">
            <div class="bkd-map-backdrop" onclick="bkdCollapseMap()"></div>
            <div class="bkd-map-shell">
                <button class="bkd-map-close" onclick="bkdCollapseMap()"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                <div id="bkd-map" class="bkd-map-container"></div>
                <div id="bkd-map-popup" class="bkd-map-popup" style="display:none">
                    <button class="bkd-popup-x" onclick="document.getElementById('bkd-map-popup').style.display='none'"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                    <img id="bkd-popup-img" class="bkd-popup-img" src="" alt="" style="display:none">
                    <div id="bkd-popup-img-ph" class="bkd-popup-img-ph"></div>
                    <div class="bkd-popup-body">
                        <strong class="bkd-popup-name" id="bkd-popup-name"></strong>
                        <p class="bkd-popup-loc" id="bkd-popup-loc"></p>
                        <div class="bkd-popup-meta">
                            <span class="bkd-popup-rating" id="bkd-popup-rating"></span>
                            <span class="bkd-popup-price" id="bkd-popup-price"></span>
                        </div>
                        <a id="bkd-popup-link" href="#" class="bkd-popup-btn">View Profile</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
        <?php if(!empty($featured_businesses)):?>
        <div class="bkd-section-label">
            <span class="bkd-section-label-icon">★</span>
            <div><span class="bkd-section-label-text">Featured</span></div>
            <span class="bkd-section-label-badge"><?php echo count($featured_businesses);?></span>
        </div>
        <div class="bkd-featured-grid">
            <?php foreach($featured_businesses as $biz):?>
            <a class="bkd-feat-card" href="<?php echo esc_url($biz['url']);?>" style="background-image:url('<?php echo esc_url($biz['cover']);?>');">
                <div class="bkd-feat-card-grad"></div>
                <div class="bkd-feat-logo-wrap"><img class="bkd-feat-logo" src="<?php echo esc_url($biz['logo']);?>" alt="<?php echo esc_attr($biz['name']);?>"></div>
                <div class="bkd-feat-body">
                    <div class="bkd-feat-name"><?php echo esc_html($biz['name']);?></div>
                    <div class="bkd-feat-cta">View Profile</div>
                </div>
            </a>
            <?php endforeach;?>
        </div>
        <?php endif;?>
    </div>


    <!-- EXPLORE -->
    <div class="bkd-section-label bkd-section-label--main" style="align-items:flex-start;">
        <span class="bkd-section-label-icon">⚡</span>
        <div>
            <span class="bkd-section-label-text">Explore All</span>
            <span class="bkd-section-label-sub">Browse every listed venue and jump straight to booking.</span>
        </div>
        <span class="bkd-section-label-badge" id="bkd-count"><?php echo count($businesses);?></span>
    </div>

    <div id="bkd-list">
        <?php foreach($businesses as $biz):?>
        <article class="bkd-tile">
            <a class="bkd-tile-img-link" href="<?php echo esc_url($biz['url']);?>">
                <img class="bkd-tile-img" src="<?php echo esc_url($biz['cover']);?>" alt="" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
                <div class="bkd-tile-img-ph" style="display:none"></div>
            </a>
            <div class="bkd-tile-body">
                <div class="bkd-tile-top">
                    <a class="bkd-tile-name-link" href="<?php echo esc_url($biz['url']);?>"><span class="bkd-tile-name"><?php echo esc_html($biz['name']);?></span></a>
                    <?php if(!empty($biz['price'])):?><span class="bkd-tile-price"><?php echo esc_html($biz['price']);?>/hr</span><?php endif;?>
                </div>
                <?php if(!empty($biz['desc'])):?><div class="bkd-tile-desc"><?php echo esc_html($biz['desc']);?></div><?php endif;?>
                <div class="bkd-tile-bottom">
                    <div class="bkd-tile-rating">
                        <?php if(!empty($biz['rating'])):?>★ <?php echo number_format($biz['rating'],1);?> <span>(<?php echo $biz['total'];?>)</span><?php else:?><span class="bkd-new">New</span><?php endif;?>
                    </div>
                    <a class="bkd-tile-book" href="<?php echo esc_url($biz['url']);?>">Book Now</a>
                </div>
            </div>
        </article>
        <?php endforeach;?>
    </div>
    <p id="bkd-no-results" style="display:none">No venues match your search.</p>
</div>
</div>

<script>
(function(){
var BIZ=<?php echo $biz_json;?>;
var activeSport='',mapLoaded=false,mapObj=null,mapMarkers=[],heroIdx=0,slideIdx=0;

var heroSlides=Array.from(document.querySelectorAll('.bkd-hero-slide'));
var heroDots=Array.from(document.querySelectorAll('.bkd-hdot'));
function heroGo(i){
    heroSlides[heroIdx].classList.remove('is-active');heroDots[heroIdx]&&heroDots[heroIdx].classList.remove('bkd-hdot--on');
    heroIdx=i;heroSlides[heroIdx].classList.add('is-active');heroDots[heroIdx]&&heroDots[heroIdx].classList.add('bkd-hdot--on');
}
if(heroSlides.length>1){heroDots.forEach((d,i)=>d.addEventListener('click',()=>heroGo(i)));setInterval(()=>heroGo((heroIdx+1)%heroSlides.length),4800);}

/* Onboarding */
var bkoImgs=Array.from(document.querySelectorAll('.bko-img'));
var bkoTexts=Array.from(document.querySelectorAll('.bko-text-slide'));
var bkoDots=Array.from(document.querySelectorAll('.bko-dot'));
var bkoCta=document.getElementById('bko-cta');
var bkoTotal=bkoImgs.length;

window.bkGoSlide=function(i){
    bkoImgs[slideIdx]&&bkoImgs[slideIdx].classList.remove('is-active');
    bkoTexts[slideIdx]&&bkoTexts[slideIdx].classList.remove('is-active');
    bkoDots[slideIdx]&&bkoDots[slideIdx].classList.remove('is-on');
    slideIdx=i;
    bkoImgs[slideIdx]&&bkoImgs[slideIdx].classList.add('is-active');
    bkoTexts[slideIdx]&&bkoTexts[slideIdx].classList.add('is-active');
    bkoDots[slideIdx]&&bkoDots[slideIdx].classList.add('is-on');
    if(bkoCta)bkoCta.textContent=slideIdx>=bkoTotal-1?'Explore Now':'Get Started';
};
window.bkNextSlide=function(){slideIdx<bkoTotal-1?bkGoSlide(slideIdx+1):bkCloseOnboarding();};
window.bkCloseOnboarding=function(){
    var o=document.getElementById('bk-onboarding');if(!o)return;
    o.classList.add('is-exiting');setTimeout(()=>o.remove(),520);
    try { if (window.localStorage) localStorage.setItem('bk_onboarding_seen','1'); } catch(e) {}
    document.cookie='bk_onboarded=1;path=/;max-age=31536000';
};

/* Map */
function initMap(){
    if(mapLoaded)return;mapLoaded=true;
    var l=document.createElement('link');l.rel='stylesheet';l.href='https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';document.head.appendChild(l);
    var s=document.createElement('script');s.src='https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
    s.onload=function(){
        var pts=BIZ.filter(b=>b.lat&&b.lng);
        var center=pts.length?[pts[0].lat,pts[0].lng]:[12,122];
        mapObj=L.map('bkd-map',{zoomControl:true,attributionControl:false}).setView(center,pts.length?13:5);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(mapObj);
        if(pts.length>1)mapObj.fitBounds(pts.map(b=>[b.lat,b.lng]),{padding:[40,40]});
        renderMapMarkers(BIZ);
    };
    document.head.appendChild(s);
}
function renderMapMarkers(list){
    if(!mapObj)return;
    mapMarkers.forEach(m=>mapObj.removeLayer(m));mapMarkers=[];
    list.filter(b=>b.lat&&b.lng).forEach(b=>{
        var icon=L.divIcon({className:'',html:`<div style="width:30px;height:30px;border-radius:50% 50% 50% 0;background:var(--p,#3b82f6);border:3px solid #fff;box-shadow:0 3px 12px rgba(0,0,0,.3);transform:rotate(-45deg);"></div>`,iconSize:[30,30],iconAnchor:[15,30]});
        var marker=L.marker([b.lat,b.lng],{icon}).addTo(mapObj);
        marker.on('click',function(){
            var popup=document.getElementById('bkd-map-popup');
            var pt=mapObj.latLngToContainerPoint([b.lat,b.lng]);
            popup.style.left=pt.x+'px';popup.style.top=pt.y+'px';
            document.getElementById('bkd-popup-name').textContent=b.name;
            document.getElementById('bkd-popup-loc').textContent=b.location||'';
            document.getElementById('bkd-popup-rating').textContent=b.rating?'★ '+b.rating.toFixed(1):'New';
            document.getElementById('bkd-popup-price').textContent=b.price||'';
            document.getElementById('bkd-popup-link').href=b.url;
            var img=document.getElementById('bkd-popup-img'),ph=document.getElementById('bkd-popup-img-ph');
            if(b.cover){img.src=b.cover;img.style.display='block';ph.style.display='none';}
            else{img.style.display='none';ph.style.display='block';}
            popup.style.display='block';mapObj.panTo([b.lat,b.lng],{animate:true});
        });
        mapMarkers.push(marker);
    });
}
window.bkdExpandMap=function(){
    var p=document.getElementById('bkd-map-panel');p.classList.add('is-open');p.setAttribute('aria-hidden','false');
    document.body.style.overflow='hidden';initMap();
    setTimeout(()=>{if(mapObj)mapObj.invalidateSize();},280);
};
window.bkdCollapseMap=function(){
    var p=document.getElementById('bkd-map-panel');p.classList.remove('is-open');p.setAttribute('aria-hidden','true');
    document.getElementById('bkd-map-popup').style.display='none';
    document.body.style.overflow='';
};
document.addEventListener('keydown',e=>{if(e.key==='Escape')bkdCollapseMap();});

function renderList(list){
    var el=document.getElementById('bkd-list'),nr=document.getElementById('bkd-no-results'),ct=document.getElementById('bkd-count'),mc=document.getElementById('bkd-map-count');
    if(ct)ct.textContent=list.length;if(mc)mc.textContent=list.length+' locations on map';
    if(!list.length){el.innerHTML='';nr.style.display='block';if(mapLoaded)renderMapMarkers([]);return;}
    nr.style.display='none';
    el.innerHTML=list.map(b=>{
        var img=b.cover?`<a class="bkd-tile-img-link" href="${b.url}"><img class="bkd-tile-img" src="${b.cover}" alt="" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='block'"><div class="bkd-tile-img-ph" style="display:none"></div></a>`:'<div class="bkd-tile-img-ph"></div>';
        var rat=b.rating?`★ ${b.rating.toFixed(1)}<span>(${b.total})</span>`:'<span class="bkd-new">New</span>';
        var price=b.price?`<span class="bkd-tile-price">${b.price}/hr</span>`:'';
        return `<article class="bkd-tile">${img}<div class="bkd-tile-body"><div class="bkd-tile-top"><a class="bkd-tile-name-link" href="${b.url}"><span class="bkd-tile-name">${b.name}</span></a>${price}</div>${b.desc?`<div class="bkd-tile-desc">${b.desc}</div>`:''}<div class="bkd-tile-bottom"><div class="bkd-tile-rating">${rat}</div><a class="bkd-tile-book" href="${b.bookurl}">Book Now</a></div></div></article>`;
    }).join('');
    if(mapLoaded)renderMapMarkers(list);
}

function applyFilters(){
    var q=(document.getElementById('bkd-search').value||'').toLowerCase();
    var fa=document.getElementById('bkd-featured-area');
    if(fa)fa.classList.toggle('is-hidden',!!(q||activeSport));
    var filtered=BIZ.filter(b=>{
        var ms=!activeSport||(b.sports&&b.sports.includes(activeSport));
        var mq=!q||b.name.toLowerCase().includes(q)||b.location.toLowerCase().includes(q)||(b.sports&&b.sports.join(' ').toLowerCase().includes(q));
        return ms&&mq;
    });
    renderList(filtered);
}
document.getElementById('bkd-search').addEventListener('input',applyFilters);
document.querySelectorAll('.bkd-pill').forEach(p=>{
    p.addEventListener('click',function(){
        document.querySelectorAll('.bkd-pill').forEach(x=>x.classList.remove('bkd-pill--active'));
        this.classList.add('bkd-pill--active');activeSport=this.dataset.sport;applyFilters();
    });
});
if(BIZ.length)renderList(BIZ);
})();
</script>
<?php
    return ob_get_clean();
}


/* ═══════════════════════════════════════════════════════
   MODIFIED: bntm_shortcode_bk_business()  (Profile page)
   Changes:
   - After clicking a calendar date, inline slots panel shows
     a horizontal scrollable date strip (same month, same as calendar)
   - Add-to-cart bar and footer bar already exist; kept consistent
   ═══════════════════════════════════════════════════════ */
function bntm_shortcode_bk_business() {
    global $wpdb;
    $business_id = bk_get_request_business_id();
    if (!$business_id) return bntm_shortcode_bk_directory();

    $profile        = bk_get_business_profile($business_id);
    $services_table = $wpdb->prefix . 'bk_services';
    $ratings_table  = $wpdb->prefix . 'bk_business_ratings';

    $services = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$services_table} WHERE business_id=%d AND status='active' ORDER BY name ASC", $business_id
    ));
    $ratings = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$ratings_table} WHERE business_id=%d AND status='approved' ORDER BY created_at DESC LIMIT 8", $business_id
    ));
    $summary         = bk_get_business_rating_summary($business_id);
    $booking_url     = bk_get_booking_url($business_id);
    $desc            = $profile->description ?: bk_get_business_setting($business_id,'bk_description','');
    $terms           = bk_get_business_setting($business_id,'bk_terms','');
    $amenities       = bk_get_business_amenities($business_id);
    $show_amenities  = bk_business_shows_amenities($business_id);
    $operating_hours = bk_get_business_operating_hours($business_id);
    $sports          = bk_get_business_categories($business_id);
    $day_labels      = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

    $gallery = [];
    if (!empty($profile->gallery_urls)) {
        $dec = json_decode($profile->gallery_urls,true);
        if (is_array($dec)) foreach($dec as $u){$c=esc_url_raw(trim($u));if($c)$gallery[]=$c;}
    }
    if (!empty($profile->photo_url)){$p=esc_url_raw(trim($profile->photo_url));if($p&&!in_array($p,$gallery))array_unshift($gallery,$p);}
    if (!empty($profile->cover_url)&&empty($gallery))$gallery[]=esc_url_raw(trim($profile->cover_url));

    $has_map = is_numeric($profile->latitude??null)&&is_numeric($profile->longitude??null)&&(floatval($profile->latitude)!==0.0||floatval($profile->longitude)!==0.0);

    $base_service   = !empty($services)?$services[0]:null;
    $base_price_fmt = $base_service?bk_format_price($base_service->price):'';

    $gallery_json  = json_encode(array_values($gallery));
    $services_json = json_encode(array_map(fn($s)=>['id'=>$s->id,'name'=>$s->name,'price'=>floatval($s->price),'price_fmt'=>bk_format_price($s->price),'duration'=>$s->duration],$services));
    $primary_color = bk_get_primary_color();
    $primary_hover = bk_get_primary_hover_color();
    $pub_id        = esc_js(bk_get_business_public_id($business_id));
    $nonce         = wp_create_nonce('bk_calendar_nonce');
    $max_date      = date('Y-m-d', strtotime('+'.bntm_get_setting('bk_advance_booking_days','30').' days'));
    $currency_sym  = bntm_get_setting('bk_currency','USD')==='PHP'?'₱':'$';
    $tax_rate      = floatval(bntm_get_setting('bk_tax_rate','0'));
    $amenity_icons = bk_get_amenity_icons();

    bntm_shared_head();
    ob_start();
    echo bk_get_primary_root_style_tag();
?>
<script>
var ajaxurl='<?php echo admin_url('admin-ajax.php');?>';
var bkBusinessId='<?php echo $pub_id;?>';
window.formatTime12Hour = window.formatTime12Hour || function(t){if(!t)return'';const[h,m]=t.split(':'),hr=+h,ap=hr>=12?'PM':'AM';return(hr%12||12)+':'+m+' '+ap;};
</script>

<div class="bkp-page" style="--p:<?php echo esc_attr($primary_color);?>;--ph:<?php echo esc_attr($primary_hover);?>;">

<!-- NAV -->
<div class="bkp-nav">
    <button class="bkp-nav-btn" onclick="history.back()">
        <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
    </button>
    <div class="bkp-nav-right">
        <button class="bkp-nav-btn" onclick="bkpShare()">
            <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        </button>
    </div>
</div>

<!-- HERO -->
<?php if(!empty($gallery)):?>
<div class="bkp-hero-wrap">
    <img id="bkp-hero" class="bkp-hero-img" src="<?php echo esc_url($gallery[0]);?>" alt="<?php echo esc_attr(bk_get_business_name($business_id));?>">
    <div class="bkp-hero-grad"></div>
</div>
<?php else:?><div class="bkp-hero-fallback"></div><?php endif;?>

<?php if(count($gallery)>1):?>
<div class="bkp-thumb-strip">
    <?php foreach(array_slice($gallery,0,8) as $i=>$g):?>
    <img src="<?php echo esc_url($g);?>" class="bkp-thumb<?php echo $i===0?' is-active':'';?>" onclick="bkpSetHero(this,'<?php echo esc_js($g);?>')" alt="">
    <?php endforeach;?>
</div>
<?php endif;?>

<!-- SHEET -->
<div class="bkp-sheet">

    <div class="bkp-title-row">
        <h1 class="bkp-biz-name"><?php echo esc_html(bk_get_business_name($business_id));?></h1>
        <?php if($summary['total']):?>
        <div class="bkp-rating-badge">
            <span class="bkp-star">★</span>
            <span><?php echo esc_html($summary['average']);?></span>
            <span class="bkp-rating-ct">(<?php echo $summary['total'];?>)</span>
        </div>
        <?php endif;?>
    </div>

    <?php if(!empty($profile->location)):?>
    <div class="bkp-location">
        <svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
        <?php echo esc_html($profile->location);?>
    </div>
    <?php endif;?>

    <div class="bkp-chips">
        <div class="bkp-chip bkp-chip--on"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>Active</div>
        <?php if($summary['total']):?><div class="bkp-chip"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg><?php echo number_format($summary['total']);?> reviews</div><?php endif;?>
        <?php foreach(array_slice($sports,0,2) as $sp):?><div class="bkp-chip"><svg viewBox="0 0 24 24"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg><?php echo esc_html($sp);?></div><?php endforeach;?>
    </div>

    <?php if($desc):?>
    <div class="bkp-sec-label">About</div>
    <div class="bkp-about">
        <span class="bkp-about-short"><?php echo esc_html(wp_trim_words($desc,22,''));?>…</span>
        <span class="bkp-about-full" style="display:none"><?php echo nl2br(esc_html($desc));?></span>
        <span class="bkp-readmore" onclick="bkpToggleMore(this)"> Read more</span>
    </div>
    <div class="bkp-divider"></div>
    <?php endif;?>

    <?php if(!empty($services)):?>
    <div class="bkp-sec-label">Services <span class="bkp-sec-hint">Tap to select</span></div>
    <div class="bkp-svc-scroll" id="bkp-svc-scroll">
        <?php foreach($services as $i=>$svc):?>
        <div class="bkp-svc-card<?php echo $i===0?' is-selected':'';?>"
             data-id="<?php echo $svc->id;?>"
             data-price="<?php echo esc_attr(bk_format_price($svc->price));?>"
             data-url="<?php echo esc_url($booking_url.'?service='.$svc->id);?>"
             onclick="bkpSelectService(this)">
            <?php if(!empty($svc->image_url)):?><img class="bkp-svc-img" src="<?php echo esc_url($svc->image_url);?>" alt=""><?php else:?><div class="bkp-svc-img-ph"></div><?php endif;?>
            <div class="bkp-svc-body">
                <div class="bkp-svc-name"><?php echo esc_html($svc->name);?></div>
                <?php if(!empty($svc->category)):?><div class="bkp-svc-cat"><?php echo esc_html($svc->category);?></div><?php endif;?>
                <div class="bkp-svc-meta">
                    <span class="bkp-svc-dur">⏱ <?php echo esc_html($svc->duration);?> min</span>
                    <span class="bkp-svc-price"><?php echo bk_format_price($svc->price);?></span>
                </div>
            </div>
            <div class="bkp-svc-check"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
        </div>
        <?php endforeach;?>
    </div>
    <div class="bkp-divider"></div>
    <?php endif;?>

    <!-- ══ INLINE CALENDAR BOOKING ══ -->
    <div class="bkp-sec-label" id="bkp-book-section-label">
        Book a Slot
        <span class="bkp-sec-hint">Pick a date below</span>
    </div>

    <!-- Calendar panel -->
    <div id="bkp-cal-panel" class="bkp-embed-panel bkp-embed-panel--active">
        <div class="bkp-embed-card">
            <div class="bkp-cal-nav">
                <button id="bkp-prev-month" class="bkp-cal-nav-btn"><svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg></button>
                <h3 id="bkp-cal-month-label" class="bkp-cal-month"></h3>
                <button id="bkp-next-month" class="bkp-cal-nav-btn"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></button>
            </div>
            <div class="bkp-cal-grid">
                <?php foreach(['S','M','T','W','T','F','S'] as $wd):?><div class="bkp-cal-wd"><?php echo $wd;?></div><?php endforeach;?>
                <div class="bkp-cal-days" id="bkp-cal-days"></div>
            </div>
            <div class="bkp-cal-legend">
                <span class="bkp-cal-dot bkp-cal-dot--low"></span>Available
                <span class="bkp-cal-dot bkp-cal-dot--med" style="margin-left:14px;"></span>Busy
                <span class="bkp-cal-dot bkp-cal-dot--full" style="margin-left:14px;"></span>Full
            </div>
        </div>
    </div>

    <!-- Slots panel (now includes date strip) -->
    <div id="bkp-slots-panel" class="bkp-embed-panel" style="display:none;">
        <div class="bkp-embed-card">
            <div class="bkp-slots-top">
                <button id="bkp-back-cal" class="bkp-back-btn">
                    <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg> Back
                </button>
                <div>
                    <div class="bkp-slots-heading">Available Times</div>
                    <div class="bkp-slots-date" id="bkp-sel-date-label"></div>
                </div>
            </div>

            <!-- ── SCROLLABLE DATE STRIP ── -->
            <div class="bkp-date-strip-wrap">
                <div class="bkp-date-strip" id="bkp-date-strip">
                    <!-- populated by JS -->
                </div>
            </div>

            <div class="bkp-svc-filter-row">
                <label class="bkp-filter-lbl">Service</label>
                <div class="bkp-sel-wrap">
                    <select id="bkp-svc-filter">
                        <option value="">All Services</option>
                        <?php foreach($services as $s):?>
                        <option value="<?php echo $s->id;?>"><?php echo esc_html($s->name);?> (<?php echo $s->duration;?> min · <?php echo bk_format_price($s->price);?>)</option>
                        <?php endforeach;?>
                    </select>
                    <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                </div>
            </div>
            <div id="bkp-slots-loading" class="bkp-loading" style="display:none;"><div class="bkp-spinner"></div></div>
            <div id="bkp-slots-table-wrap" style="display:none;overflow-x:auto;">
                <table class="bkp-slots-table">
                    <thead id="bkp-slots-thead"></thead>
                    <tbody id="bkp-slots-tbody"></tbody>
                </table>
            </div>
            <div id="bkp-slots-msg" class="bkp-slots-notice" style="display:none;"></div>
        </div>
    </div>

    <div class="bkp-divider"></div>
    <!-- ══ END INLINE CALENDAR ══ -->

    <?php if(!empty($ratings)):?>
    <div class="bkp-sec-label">Reviews</div>
    <div class="bkp-reviews-grid">
        <?php foreach($ratings as $r):?>
        <div class="bkp-review-card">
            <div class="bkp-review-header">
                <div class="bkp-avatar"><?php echo strtoupper(substr($r->customer_name?:'G',0,1));?></div>
                <div>
                    <div class="bkp-reviewer-name"><?php echo esc_html($r->customer_name?:'Guest');?></div>
                    <div class="bkp-stars"><?php echo str_repeat('★',round($r->rating)).str_repeat('☆',5-round($r->rating));?></div>
                </div>
            </div>
            <?php if($r->review):?><div class="bkp-review-text"><?php echo esc_html($r->review);?></div><?php endif;?>
        </div>
        <?php endforeach;?>
    </div>
    <div class="bkp-divider"></div>
    <?php endif;?>

    <?php if($show_amenities && !empty($amenities)):?>
    <div class="bkp-sec-label">Amenities</div>
    <div class="bkp-amenity-row">
        <?php foreach($amenities as $am):
            $icon=$amenity_icons[strtolower($am)]??$amenity_icons['default'];?>
        <div class="bkp-amenity-card">
            <div class="bkp-amenity-icon"><?php echo $icon;?></div>
            <span class="bkp-amenity-label"><?php echo esc_html($am);?></span>
        </div>
        <?php endforeach;?>
    </div>
    <div class="bkp-divider"></div>
    <?php endif;?>

    <?php if($has_map):?>
    <div class="bkp-sec-label">Location</div>
    <iframe class="bkp-map" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
        src="<?php echo esc_url('https://www.google.com/maps?q='.rawurlencode($profile->latitude.','.$profile->longitude).'&output=embed');?>"></iframe>
    <div class="bkp-divider"></div>
    <?php endif;?>

    <?php if(!empty($operating_hours)):?>
    <div class="bkp-sec-label">Operating Hours</div>
    <div class="bkp-hours-list">
        <?php foreach($operating_hours as $hour):
            $dow=intval($hour->day_of_week)%7;$is_open=intval($hour->is_open);?>
        <div class="bkp-hours-row">
            <span class="bkp-hours-day"><?php echo esc_html($day_labels[$dow]);?></span>
            <span class="bkp-hours-time<?php echo !$is_open?' bkp-closed':'';?>"><?php echo $is_open?esc_html(date_i18n('g:i A',strtotime($hour->start_time)).' – '.date_i18n('g:i A',strtotime($hour->end_time))):'Closed';?></span>
        </div>
        <?php endforeach;?>
    </div>
    <div class="bkp-divider"></div>
    <?php endif;?>

    <?php if($terms):?>
    <div class="bkp-sec-label">Booking Policy</div>
    <div class="bkp-terms-box">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        <div class="bkp-terms-text"><?php echo nl2br(esc_html($terms));?></div>
    </div>
    <?php endif;?>

</div><!-- /.bkp-sheet -->

<!-- ADD-TO-CART BAR -->
<div id="bkp-slot-addbar" class="bkp-slot-addbar" style="display:none;">
    <div><strong id="bkp-slot-addbar-title"></strong><p id="bkp-slot-addbar-meta"></p></div>
    <button class="bkp-cta-sm" id="bkp-add-to-cart">Add to Cart</button>
</div>

<!-- STICKY FOOTER -->
<div class="bkp-footer" id="bkp-footer">
    <button class="bkp-book-btn" id="bkp-book-now-btn">
        <span id="bkp-book-now-label">Book Now</span>
        <small>Total: <span id="bkp-footer-price"><?php echo esc_html($base_price_fmt ?: bk_format_price(0));?></span></small>
    </button>
</div>

<!-- BOOKING MODAL -->
<div id="bkp-booking-modal" class="bkp-modal">
    <div class="bkp-modal-overlay"></div>
    <div class="bkp-modal-box">
        <button class="bkp-modal-close">&times;</button>
        <h2 class="bkp-modal-title">Complete Booking</h2>
        <form id="bkp-booking-form">
            <div id="bkp-cart-review" class="bkp-cart-review"></div>
            <div class="bkp-summary-card">
                <div class="bkp-sum-row"><span>Service</span><span id="bkp-sum-svc" class="bkp-sum-val"></span></div>
                <div class="bkp-sum-row"><span>Date</span><span id="bkp-sum-date" class="bkp-sum-val"></span></div>
                <div class="bkp-sum-row"><span>Time</span><span id="bkp-sum-time" class="bkp-sum-val"></span></div>
                <div class="bkp-sum-row"><span>Duration</span><span id="bkp-sum-dur" class="bkp-sum-val"></span></div>
                <div class="bkp-sum-divider"></div>
                <div class="bkp-sum-row bkp-sum-row--total"><span>Total</span><span id="bkp-sum-total" class="bkp-sum-val bkp-sum-val--total"></span></div>
            </div>
            <div id="bkp-avail-warn" class="bkp-warn" style="display:none;">⚠️ <span id="bkp-warn-msg"></span></div>
            <div class="bkp-fields">
                <div class="bkp-field"><label>Full Name <span>*</span></label><input type="text" name="customer_name" required placeholder="Juan dela Cruz"></div>
                <div class="bkp-field"><label>Email <span>*</span></label><input type="email" name="customer_email" required placeholder="juan@email.com"></div>
                <div class="bkp-field"><label>Phone <span>*</span></label><input type="tel" name="customer_phone" required placeholder="+63 9XX XXX XXXX"></div>
                <div class="bkp-field bkp-field--full"><label>Notes</label><textarea name="customer_notes" rows="2" placeholder="Any special requests…"></textarea></div>
            </div>
            <input type="hidden" name="service_id" id="bkp-h-service">
            <input type="hidden" name="booking_date" id="bkp-h-date">
            <input type="hidden" name="start_time" id="bkp-h-start">
            <input type="hidden" name="end_time" id="bkp-h-end">
            <input type="hidden" name="quantity" id="bkp-h-qty" value="1">
            <input type="hidden" name="amount" id="bkp-h-amount">
            <button type="submit" class="bkp-submit" id="bkp-submit-btn">Confirm Booking</button>
            <div id="bkp-booking-msg" class="bkp-booking-msg"></div>
        </form>
    </div>
</div>

</div><!-- /.bkp-page -->

<style>
/* ── Tokens ── */
.bkp-page{--p:<?php echo esc_attr($primary_color);?>;--ph:<?php echo esc_attr($primary_hover);?>;}
.bkp-page,.bkp-page *,.bkp-modal,.bkp-modal *{font-family:'Barlow',sans-serif!important;box-sizing:border-box;}
.bkp-page{max-width:480px;margin:0 auto;background:#f1f5f9;min-height:100vh;position:relative;}

/* NAV */
.bkp-nav{position:absolute;top:0;left:0;right:0;z-index:10;display:flex;justify-content:space-between;align-items:center;padding:14px 16px;}
.bkp-nav-btn{width:40px;height:40px;border-radius:50%;background:rgba(15,23,42,.5);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;cursor:pointer;}
.bkp-nav-btn svg{width:18px;height:18px;stroke:#fff!important;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round;}
.bkp-nav-right{display:flex;gap:8px;}

/* HERO */
.bkp-hero-wrap{position:relative;width:100%;overflow:hidden;height:420px;}
.bkp-hero-img{width:100%;height:450px;object-fit:cover;transition:transform .4s ease;}
.bkp-hero-wrap:hover .bkp-hero-img{transform:scale(1.02);}
.bkp-hero-grad{position:absolute;inset:0;background:linear-gradient(to top,rgba(15,23,42,.6) 0%,transparent 55%);}
.bkp-hero-fallback{width:100%;height:300px;background:linear-gradient(160deg,#0f172a 0%,#1e3a5f 60%,#2563eb 100%);}
.bkp-thumb-strip{display:flex;gap:7px;padding:12px 14px 26px;overflow-x:auto;scrollbar-width:none;background:rgba(255,255,255,.08);backdrop-filter:blur(14px);border-bottom:1px solid rgba(255,255,255,.1);position:relative;z-index:3;margin-top:-65px;}
.bkp-thumb-strip::-webkit-scrollbar{display:none;}
.bkp-thumb{width:56px;height:44px;border-radius:8px;object-fit:cover;cursor:pointer;flex-shrink:0;border:2.5px solid transparent;transition:all .18s;opacity:.75;}
.bkp-thumb.is-active{border-color:var(--p)!important;opacity:1;}
.bkp-thumb:hover{opacity:1;transform:scale(1.05);}

/* SHEET */
.bkp-sheet{background:#fff;border-radius:22px 22px 0 0;margin-top:-18px;position:relative;z-index:4;padding:22px 16px 150px;animation:bkpSheetIn .4s ease both;}
@keyframes bkpSheetIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

/* TITLE */
.bkp-title-row{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:6px;gap:10px;}
.bkp-biz-name{font-size:26px!important;font-weight:900!important;line-height:1.1;letter-spacing:-.3px;color:#0f172a!important;text-transform:uppercase;margin:0;}
.bkp-rating-badge{display:flex;align-items:center;gap:4px;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:10px;padding:6px 10px;flex-shrink:0;}
.bkp-star{color:var(--p)!important;font-size:14px;}
.bkp-rating-badge span{font-size:13px;font-weight:700;color:#0f172a;}
.bkp-rating-ct{color:#94a3b8!important;font-weight:500!important;}
.bkp-location{display:flex;align-items:center;gap:5px;color:#64748b;font-size:13px;margin-bottom:14px;}
.bkp-location svg{width:13px;height:13px;stroke:#64748b;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0;}
.bkp-chips{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:18px;}
.bkp-chip{display:flex;align-items:center;gap:5px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:999px;padding:6px 12px;font-size:12px;font-weight:600!important;color:#475569;}
.bkp-chip svg{width:12px;height:12px;stroke:#475569;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
.bkp-chip--on{background:var(--p)!important;border-color:var(--p)!important;color:#fff!important;}
.bkp-chip--on svg{stroke:#fff!important;}
.bkp-divider{height:1px;background:#f1f5f9;margin:18px 0;}
.bkp-sec-label{font-size:16px!important;font-weight:900!important;color:#0f172a!important;text-transform:uppercase;letter-spacing:.4px;margin-bottom:12px;display:flex;align-items:center;gap:8px;}
.bkp-sec-hint{font-size:11px;font-weight:500!important;color:#94a3b8;text-transform:none;letter-spacing:0;font-family:'Barlow',sans-serif!important;}
.bkp-about{font-size:14px;color:#64748b;line-height:1.65;margin-bottom:2px;}
.bkp-readmore{color:#0f172a;font-weight:700;cursor:pointer;font-size:13px;}
.bkp-amenity-row{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:2px;}
.bkp-amenity-card{background:#f8fafc;border:1px solid #f1f5f9;border-radius:12px;padding:14px 8px;display:flex;flex-direction:column;align-items:center;gap:6px;}
.bkp-amenity-icon svg{width:24px;height:24px;stroke:#475569;fill:none;}
.bkp-amenity-label{font-size:10px;font-weight:700!important;color:#475569;text-align:center;text-transform:uppercase;letter-spacing:.3px;}
.bkp-svc-scroll{display:flex;gap:10px;overflow-x:auto;scrollbar-width:none;padding-bottom:4px;margin-bottom:2px;}
.bkp-svc-scroll::-webkit-scrollbar{display:none;}
.bkp-svc-card{flex-shrink:0;width:148px;background:#f8fafc;border:2px solid #f1f5f9;border-radius:14px;overflow:hidden;cursor:pointer;transition:all .2s;position:relative;}
.bkp-svc-card:hover{border-color:#cbd5e1;transform:translateY(-2px);}
.bkp-svc-card.is-selected{border-color:var(--p)!important;box-shadow:0 4px 20px rgba(0,0,0,.12);background:#fffdf0;}
.bkp-svc-img,.bkp-svc-img-ph{width:100%;height:80px;object-fit:cover;display:block;}
.bkp-svc-img-ph{background:linear-gradient(135deg,#e2e8f0,#f1f5f9);}
.bkp-svc-body{padding:10px;}
.bkp-svc-name{font-size:13px!important;font-weight:900!important;color:#0f172a!important;text-transform:uppercase;letter-spacing:.2px;margin-bottom:4px;line-height:1.2;}
.bkp-svc-cat{display:inline-flex;padding:2px 7px;border-radius:999px;background:#e2e8f0;color:#334155;font-size:10px;font-weight:700!important;text-transform:uppercase;margin-bottom:7px;}
.bkp-svc-meta{display:flex;justify-content:space-between;align-items:center;}
.bkp-svc-dur{font-size:11px;color:#64748b;}
.bkp-svc-price{font-size:13px;font-weight:900!important;color:var(--ph)!important;}
.bkp-svc-check{position:absolute;top:8px;right:8px;width:22px;height:22px;border-radius:50%;background:var(--p);display:flex;align-items:center;justify-content:center;opacity:0;transform:scale(.6);transition:all .2s;}
.bkp-svc-card.is-selected .bkp-svc-check{opacity:1;transform:scale(1);}
.bkp-svc-check svg{width:12px;height:12px;stroke:#fff!important;fill:none;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round;}

/* ── EMBEDDED CALENDAR ── */
.bkp-embed-panel{transition:opacity .3s ease;}
.bkp-embed-card{background:rgba(248,250,252,.9);backdrop-filter:blur(16px);border:1.5px solid #e2e8f0;border-radius:18px;padding:18px;margin-bottom:4px;}
.bkp-cal-nav{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;}
.bkp-cal-month{font-size:18px!important;font-weight:900!important;color:#0f172a!important;margin:0;text-transform:uppercase;letter-spacing:.3px;text-align:center;flex:1;}
.bkp-cal-nav-btn{width:34px;height:34px;border-radius:10px;border:1.5px solid #e2e8f0;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .18s;flex-shrink:0;}
.bkp-cal-nav-btn:hover:not(:disabled){border-color:var(--p);background:var(--p);}
.bkp-cal-nav-btn:hover:not(:disabled) svg{stroke:#fff;}
.bkp-cal-nav-btn:disabled{opacity:.35;cursor:not-allowed;}
.bkp-cal-nav-btn svg{width:16px;height:16px;stroke:#475569;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round;}
.bkp-cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:4px;}
.bkp-cal-wd{text-align:center;font-size:10px;font-weight:700;color:#94a3b8;padding:4px 0;text-transform:uppercase;}
.bkp-cal-days{display:grid;grid-template-columns:repeat(7,1fr);gap:4px;grid-column:1/-1;}
.bkp-cal-day{aspect-ratio:1;display:flex;flex-direction:column;align-items:center;justify-content:center;border-radius:10px;cursor:pointer;font-weight:700;font-size:13px;transition:all .18s;border:1.5px solid transparent;position:relative;color:#1e293b;background:rgba(255,255,255,.7);}
.bkp-cal-day:hover:not(.bkp-cal-day--dis):not(.bkp-cal-day--other):not(.bkp-cal-day--full){transform:scale(1.08);border-color:var(--p);box-shadow:0 3px 12px rgba(59,130,246,.15);}
.bkp-cal-day--other{color:#cbd5e1;cursor:default;background:transparent;}
.bkp-cal-day--dis{opacity:.32;cursor:not-allowed;}
.bkp-cal-day--low{background:rgba(134,239,172,.22);border-color:#86efac;}
.bkp-cal-day--med{background:rgba(253,186,116,.22);border-color:#fdba74;}
.bkp-cal-day--full{background:rgba(248,113,113,.18)!important;border-color:#f87171!important;cursor:not-allowed!important;opacity:.6;}
.bkp-cal-day--sel{background:var(--p)!important;border-color:var(--p)!important;color:#fff!important;box-shadow:0 4px 16px rgba(59,130,246,.28);}
.bkp-cal-day--nohours{opacity:.28;cursor:not-allowed;}
.bkp-cal-badge{font-size:8px;opacity:.7;margin-top:1px;}
.bkp-cal-day--sel .bkp-cal-badge{color:rgba(255,255,255,.8);}
.bkp-cal-legend{display:flex;align-items:center;font-size:11px;color:#94a3b8;margin-top:12px;padding-top:12px;border-top:1px solid #f1f5f9;}
.bkp-cal-dot{width:8px;height:8px;border-radius:50%;display:inline-block;margin-right:4px;}
.bkp-cal-dot--low{background:#86efac;}
.bkp-cal-dot--med{background:#fdba74;}
.bkp-cal-dot--full{background:#f87171;}

/* ── DATE STRIP (profile) ── */
.bkp-date-strip-wrap{margin-bottom:14px;overflow:hidden;}
.bkp-date-strip{display:flex;gap:7px;overflow-x:auto;scrollbar-width:none;padding:4px 2px 6px;}
.bkp-date-strip::-webkit-scrollbar{display:none;}
.bkp-date-chip{flex-shrink:0;display:flex;flex-direction:column;align-items:center;justify-content:center;min-width:52px;padding:9px 5px;border-radius:13px;border:1.5px solid #e2e8f0;background:rgba(255,255,255,.9);cursor:pointer;transition:all .18s;gap:2px;}
.bkp-date-chip:hover:not(.bkp-date-chip--disabled):not(.bkp-date-chip--full){border-color:var(--p);background:rgba(59,130,246,.05);}
.bkp-date-chip--selected{background:var(--p)!important;border-color:var(--p)!important;box-shadow:0 4px 14px rgba(59,130,246,.22);}
.bkp-date-chip--disabled{opacity:.32;cursor:not-allowed;}
.bkp-date-chip--full{opacity:.45;cursor:not-allowed;background:rgba(248,113,113,.08)!important;border-color:#f87171!important;}
.bkp-date-chip--nohours{opacity:.26;cursor:not-allowed;}
.bkp-chip-day{font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.3px;}
.bkp-chip-num{font-size:18px;font-weight:900;color:#0f172a;line-height:1;}
.bkp-date-chip--selected .bkp-chip-day,
.bkp-date-chip--selected .bkp-chip-num{color:#fff!important;}

/* Slots embed */
.bkp-slots-top{display:flex;align-items:center;gap:12px;margin-bottom:14px;}
.bkp-back-btn{display:flex;align-items:center;gap:4px;padding:7px 12px;border-radius:10px;border:1.5px solid #e2e8f0;background:#fff;cursor:pointer;font-size:13px;font-weight:700;color:#475569;flex-shrink:0;transition:all .18s;}
.bkp-back-btn:hover{border-color:var(--p);color:var(--p);}
.bkp-back-btn svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round;}
.bkp-slots-heading{font-size:18px!important;font-weight:900!important;color:#0f172a!important;text-transform:uppercase;margin:0;}
.bkp-slots-date{font-size:12px;color:#94a3b8;margin-top:2px;}
.bkp-svc-filter-row{margin-bottom:12px;}
.bkp-filter-lbl{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#94a3b8;margin-bottom:5px;}
.bkp-sel-wrap{position:relative;}
.bkp-sel-wrap select{width:100%;padding:10px 36px 10px 12px;border:1.5px solid #e2e8f0;border-radius:11px;background:#fff;font-size:13px;color:#0f172a;appearance:none;font-family:'Barlow',sans-serif!important;}
.bkp-sel-wrap select:focus{outline:none;border-color:var(--p);}
.bkp-sel-wrap svg{position:absolute;right:10px;top:50%;transform:translateY(-50%);width:14px;height:14px;stroke:#94a3b8;fill:none;stroke-width:2;pointer-events:none;stroke-linecap:round;stroke-linejoin:round;}
.bkp-loading{text-align:center;padding:28px;}
.bkp-spinner{width:28px;height:28px;border:3px solid rgba(59,130,246,.15);border-top-color:var(--p,#3b82f6);border-radius:50%;animation:bkpSpin .7s linear infinite;margin:0 auto;}
@keyframes bkpSpin{to{transform:rotate(360deg);}}
.bkp-slots-table{width:100%;border-collapse:collapse;}
.bkp-slots-table thead{background:#f8fafc;border-bottom:2px solid #e2e8f0;}
.bkp-slots-table th{padding:9px 8px;text-align:center;font-size:11px;font-weight:800;color:#0f172a;text-transform:uppercase;letter-spacing:.4px;border-right:1px solid #e2e8f0;}
.bkp-slots-table th:last-child,.bkp-slots-table td:last-child{border-right:none;}
.bkp-slots-table td{padding:6px 5px;text-align:center;border-right:1px solid #f1f5f9;border-bottom:1px solid #f1f5f9;}
.bkp-slot-btn{width:100%;padding:8px 4px;border-radius:9px;border:1.5px solid #e2e8f0;background:#fff;cursor:pointer;font-size:12px;font-weight:700;transition:all .18s;line-height:1.3;}
.bkp-slot-btn:hover:not(.bkp-slot-btn--booked){border-color:var(--p);color:var(--p);background:rgba(59,130,246,.05);}
.bkp-slot-btn--avail{border-color:#86efac;background:#f0fdf4;color:#166534;}
.bkp-slot-btn--sel{background:var(--p)!important;color:#fff!important;border-color:var(--p)!important;box-shadow:0 3px 12px rgba(59,130,246,.22);}
.bkp-slot-btn--cart{background:#0f172a!important;color:#fff!important;border-color:#0f172a!important;}
.bkp-slot-btn--booked{background:#fef2f2;color:#b91c1c;border-color:#fca5a5;cursor:not-allowed;}
.bkp-slots-notice{padding:14px;border-radius:11px;background:#fef3c7;border:1.5px solid #fde68a;color:#92400e;font-size:13px;text-align:center;margin-top:10px;}

/* REVIEWS */
.bkp-reviews-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:2px;}
.bkp-review-card{background:#f8fafc;border:1px solid #f1f5f9;border-radius:12px;padding:12px;}
.bkp-review-header{display:flex;align-items:center;gap:8px;margin-bottom:8px;}
.bkp-avatar{width:32px;height:32px;border-radius:50%;background:var(--p)!important;display:flex;align-items:center;justify-content:center;font-weight:900!important;font-size:13px;color:#fff!important;flex-shrink:0;}
.bkp-reviewer-name{font-size:13px!important;font-weight:700!important;color:#0f172a;}
.bkp-stars{color:var(--p)!important;font-size:11px;margin-top:2px;}
.bkp-review-text{font-size:12px;color:#64748b;line-height:1.5;}
.bkp-map{width:100%;height:170px;border:0;border-radius:12px;margin-bottom:2px;}
.bkp-hours-list{display:grid;gap:5px;margin-bottom:2px;}
.bkp-hours-row{display:flex;justify-content:space-between;align-items:center;padding:10px 12px;background:#f8fafc;border:1px solid #f1f5f9;border-radius:12px;}
.bkp-hours-day{font-size:12px;font-weight:700!important;color:#0f172a;text-transform:uppercase;letter-spacing:.3px;}
.bkp-hours-time{font-size:12px;color:#475569;font-weight:600!important;}
.bkp-closed{color:#94a3b8!important;}
.bkp-terms-box{background:#f8fafc;border:1px solid #f1f5f9;border-radius:12px;padding:14px;display:flex;gap:10px;}
.bkp-terms-box svg{width:16px;height:16px;stroke:#94a3b8;fill:none;stroke-width:2;flex-shrink:0;margin-top:2px;}
.bkp-terms-text{font-size:12.5px;color:#64748b;line-height:1.7;}

/* ADD-TO-CART BAR */
.bkp-slot-addbar{position:fixed;left:50%;transform:translateX(-50%);bottom:92px;z-index:101;display:flex;align-items:center;justify-content:space-between;gap:10px;width:calc(100% - 24px);max-width:456px;padding:13px 14px;background:#fff;border:1.5px solid rgba(59,130,246,.22);border-radius:16px;box-shadow:0 12px 36px rgba(15,23,42,.16);}
.bkp-slot-addbar strong{display:block;font-size:13px;color:#0f172a;}
.bkp-slot-addbar p{font-size:12px;color:#64748b;margin:2px 0 0;}
.bkp-cta-sm{padding:10px 18px;border-radius:10px;border:none;background:var(--p);color:#fff!important;font-size:14px!important;font-weight:800!important;cursor:pointer;white-space:nowrap;text-transform:uppercase;letter-spacing:.3px;transition:all .18s;}
.bkp-cta-sm:hover{background:var(--ph);transform:translateY(-1px);}

/* FOOTER */
.bkp-footer{position:fixed;bottom:0;left:50%;transform:translateX(-50%);width:100%;max-width:480px;padding:12px 16px 28px;display:flex;align-items:center;justify-content:center;z-index:100;}
.bkp-book-btn{width:100%;display:flex!important;flex-direction:column;align-items:center;justify-content:center;gap:3px;background:var(--p)!important;color:#fff!important;border:none!important;border-radius:999px!important;padding:13px 28px!important;font-size:16px!important;font-weight:900!important;cursor:pointer;text-transform:uppercase;letter-spacing:.5px;transition:all .18s;box-shadow:0 6px 24px rgba(59,130,246,.3);}
.bkp-book-btn small{display:block;font-size:11px!important;font-weight:700!important;line-height:1.2;text-transform:none;letter-spacing:0;color:rgba(255,255,255,.82)!important;}
.bkp-book-btn:hover{background:var(--ph)!important;transform:scale(1.03);}
@media(max-width:480px){.bkp-footer{left:0;right:0;transform:none;width:100%;}.bkp-slot-addbar{left:12px;right:12px;transform:none;width:auto;max-width:none;}}

/* MODAL */
.bkp-modal{display:none;position:fixed;inset:0;z-index:10000;}
.bkp-modal.active{display:block;}
.bkp-modal-overlay{position:absolute;inset:0;background:rgba(15,23,42,.7);backdrop-filter:blur(8px);}
.bkp-modal-box{position:relative;max-width:540px;margin:30px auto;background:rgba(255,255,255,.97);border-radius:24px;box-shadow:0 30px 80px rgba(15,23,42,.3);max-height:calc(100vh - 60px);overflow-y:auto;padding:26px 22px;}
.bkp-modal-close{position:absolute;top:14px;right:14px;width:36px;height:36px;border-radius:50%;background:#f1f5f9;border:none;font-size:20px;cursor:pointer;color:#475569;display:flex;align-items:center;justify-content:center;transition:all .18s;line-height:1;}
.bkp-modal-close:hover{background:#e2e8f0;}
.bkp-modal-title{font-size:22px!important;font-weight:900!important;color:#0f172a!important;text-transform:uppercase;margin:0 0 16px;}
.bkp-cart-review{display:grid;gap:7px;margin-bottom:14px;}
.bkp-cart-item{display:flex;justify-content:space-between;align-items:center;gap:8px;padding:10px 12px;border:1.5px solid #e2e8f0;border-radius:11px;background:#f8fafc;}
.bkp-cart-item strong{font-size:12px;color:#0f172a;}
.bkp-cart-item small{font-size:11px;color:#94a3b8;display:block;margin-top:2px;}
.bkp-cart-item button{padding:5px 9px;border-radius:7px;border:none;background:#fee2e2;color:#b91c1c;cursor:pointer;font-size:11px;font-weight:700;}
.bkp-summary-card{background:linear-gradient(135deg,#f0f9ff,#eff6ff);border:1.5px solid #bfdbfe;border-radius:14px;padding:14px 16px;margin-bottom:16px;}
.bkp-sum-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:7px;font-size:13px;color:#475569;}
.bkp-sum-row:last-child{margin-bottom:0;}
.bkp-sum-val{font-weight:700;color:#0f172a;}
.bkp-sum-val--total{color:#059669!important;font-size:17px;}
.bkp-sum-divider{height:1px;background:rgba(191,219,254,.5);margin:9px 0;}
.bkp-sum-row--total{font-size:15px;font-weight:800;}
.bkp-warn{background:#fef3c7;border:1.5px solid #fde68a;border-radius:10px;padding:11px 13px;font-size:13px;color:#92400e;margin-bottom:13px;}
.bkp-fields{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;}
.bkp-field{display:flex;flex-direction:column;gap:4px;}
.bkp-field--full{grid-column:1/-1;}
.bkp-field label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#94a3b8;}
.bkp-field label span{color:#ef4444;}
.bkp-field input,.bkp-field textarea{padding:10px 12px;border:1.5px solid #e2e8f0;border-radius:11px;font-size:14px;color:#0f172a;background:#fff;font-family:'Barlow',sans-serif!important;resize:none;}
.bkp-field input:focus,.bkp-field textarea:focus{outline:none;border-color:var(--p);}
.bkp-submit{width:100%;padding:15px;border-radius:14px;border:none;background:var(--p);color:#fff!important;font-size:16px!important;font-weight:900!important;cursor:pointer;text-transform:uppercase;letter-spacing:.5px;transition:all .18s;margin-top:4px;font-family:'Barlow',sans-serif!important;}
.bkp-submit:hover:not(:disabled){background:var(--ph);transform:translateY(-1px);box-shadow:0 8px 24px rgba(59,130,246,.3);}
.bkp-submit:disabled{opacity:.5;cursor:not-allowed;}
.bkp-booking-msg{margin-top:10px;}
@media(max-width:480px){.bkp-fields,.bkp-reviews-grid{grid-template-columns:1fr;}.bkp-modal-box{margin:16px;padding:20px 14px;}}
</style>

<script>
(function(){
    /* ── UI helpers ── */
    window.bkpSetHero=function(el,src){
        var h=document.getElementById('bkp-hero');if(h){h.src=src;}
        document.querySelectorAll('.bkp-thumb').forEach(t=>t.classList.remove('is-active'));
        el.classList.add('is-active');
    };
    window.bkpToggleMore=function(btn){
        var s=btn.parentElement.querySelector('.bkp-about-short'),f=btn.parentElement.querySelector('.bkp-about-full');
        var shown=f.style.display!=='none';s.style.display=shown?'inline':'none';f.style.display=shown?'none':'inline';
        btn.textContent=shown?' Read more':' Show less';
    };
    window.bkpShare=function(){
        if(navigator.share)navigator.share({title:document.title,url:location.href});
        else if(navigator.clipboard)navigator.clipboard.writeText(location.href);
    };
    window.bkpSelectService=function(card){
        document.querySelectorAll('.bkp-svc-card').forEach(c=>c.classList.remove('is-selected'));
        card.classList.add('is-selected');
        bkpUpdateFooterPrice();
    };

    /* ── State ── */
    var SERVICES=<?php echo $services_json;?>;
    var taxRate=<?php echo $tax_rate;?>;
    var currSym='<?php echo $currency_sym;?>';
    var minDate=new Date();minDate.setHours(0,0,0,0);
    var maxDate=new Date('<?php echo $max_date;?>');
    var calMonth=new Date('<?php echo date('Y-m');?>-01');
    var selDate=null,selService=null,selServiceData=null,selTime=null,selEndTime=null;
    var dateAvail={},selSlots=[],bookCart=[];

    function bkpFmt(d){return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');}
    function bkpPrice(n){return currSym+parseFloat(n).toFixed(2);}
    function bkpCartKey(i){return[i.service,i.date,i.time].join('|');}
    function bkpAjax(body,cb){fetch(ajaxurl,{method:'POST',body}).then(r=>r.json()).then(cb).catch(console.error);}

    var calPanel=document.getElementById('bkp-cal-panel'),slotsPanel=document.getElementById('bkp-slots-panel');
    var modal=document.getElementById('bkp-booking-modal'),modalOverlay=modal.querySelector('.bkp-modal-overlay');
    var bookNowBtn=document.getElementById('bkp-book-now-btn');
    if(bookNowBtn) bookNowBtn.addEventListener('click', bkpBookNow);

    /* ── Footer price ── */
    function bkpUpdateFooterPrice(){
        var priceEl=document.getElementById('bkp-footer-price');
        if(!priceEl) return;
        if(bookCart.length){
            var total=bookCart.reduce((s,i)=>s+parseFloat(i.serviceData.price||0),0)*(1+taxRate/100);
            priceEl.textContent=bkpPrice(total)+(taxRate?' incl. tax':'');
        } else {
            var sel=document.querySelector('.bkp-svc-card.is-selected');
            if(sel) priceEl.textContent=sel.dataset.price;
        }
    }

    function bkpBookNow(){
        if(bookCart.length){ bkpOpenModal(); }
        else {
            var target=document.getElementById('bkp-book-section-label');
            if(target) target.scrollIntoView({behavior:'smooth'});
        }
    }

    /* ── Calendar render ── */
    function bkpRenderCal(){
        var yr=calMonth.getFullYear(),mo=calMonth.getMonth();
        document.getElementById('bkp-cal-month-label').textContent=new Date(yr,mo).toLocaleDateString('en-US',{month:'long',year:'numeric'});
        var first=new Date(yr,mo,1),last=new Date(yr,mo+1,0),startDay=first.getDay();
        var html='';
        for(var i=startDay-1;i>=0;i--)html+=`<div class="bkp-cal-day bkp-cal-day--other">${new Date(yr,mo,0).getDate()-i}</div>`;
        for(var day=1;day<=last.getDate();day++){
            var d=new Date(yr,mo,day),ds=bkpFmt(d);
            var cls='bkp-cal-day';
            if(d<minDate||d>maxDate) cls+=' bkp-cal-day--dis';
            if(ds===selDate) cls+=' bkp-cal-day--sel';
            html+=`<div class="${cls}" data-date="${ds}">${day}</div>`;
        }
        var rem=42-(startDay+last.getDate());
        for(var day=1;day<=rem;day++) html+=`<div class="bkp-cal-day bkp-cal-day--other">${day}</div>`;
        document.getElementById('bkp-cal-days').innerHTML=html;

        document.querySelectorAll('.bkp-cal-day:not(.bkp-cal-day--dis):not(.bkp-cal-day--other)').forEach(day=>{
            day.addEventListener('click',function(){
                if(this.classList.contains('bkp-cal-day--full')||this.classList.contains('bkp-cal-day--nohours')) return;
                bkpSelectDate(this.dataset.date);
                calPanel.style.opacity='0';
                setTimeout(()=>{
                    calPanel.style.display='none';
                    slotsPanel.style.display='block';
                    slotsPanel.style.opacity='0';
                    setTimeout(()=>slotsPanel.style.opacity='1',10);
                    bkpBuildDateStrip();
                    bkpLoadSlots();
                },280);
            });
        });
        bkpUpdateCalColors();
    }

    /* ── selectDate helper ── */
    function bkpSelectDate(ds){
        selDate=ds;
        document.querySelectorAll('.bkp-cal-day').forEach(d=>d.classList.remove('bkp-cal-day--sel'));
        var calDay=document.querySelector(`.bkp-cal-day[data-date="${ds}"]`);
        if(calDay) calDay.classList.add('bkp-cal-day--sel');
        var dateObj=new Date(ds);dateObj.setHours(12,0,0,0);
        document.getElementById('bkp-sel-date-label').textContent=dateObj.toLocaleDateString('en-US',{weekday:'long',month:'long',day:'numeric'});
    }

    /* ── Build scrollable date strip ── */
    function bkpBuildDateStrip(){
        var yr=calMonth.getFullYear(),mo=calMonth.getMonth();
        var lastDay=new Date(yr,mo+1,0).getDate();
        var dayLabels=['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
        var html='';
        for(var day=1;day<=lastDay;day++){
            var date=new Date(yr,mo,day),ds=bkpFmt(date);
            var cls='bkp-date-chip';
            var data=dateAvail[ds];
            if(date<minDate||date>maxDate) cls+=' bkp-date-chip--disabled';
            else if(data&&data.total_slots===0) cls+=' bkp-date-chip--nohours';
            else if(data&&(data.booked_slots>=data.total_slots||data.percentage>=100)) cls+=' bkp-date-chip--full';
            if(ds===selDate) cls+=' bkp-date-chip--selected';
            html+=`<div class="${cls}" data-date="${ds}">
                <span class="bkp-chip-day">${dayLabels[date.getDay()]}</span>
                <span class="bkp-chip-num">${day}</span>
            </div>`;
        }
        var strip=document.getElementById('bkp-date-strip');
        strip.innerHTML=html;

        /* scroll selected into view */
        var selChip=strip.querySelector('.bkp-date-chip--selected');
        if(selChip) setTimeout(()=>selChip.scrollIntoView({behavior:'smooth',block:'nearest',inline:'center'}),80);

        /* chip click */
        strip.querySelectorAll('.bkp-date-chip').forEach(chip=>{
            chip.addEventListener('click',function(){
                if(this.classList.contains('bkp-date-chip--disabled')||
                   this.classList.contains('bkp-date-chip--full')||
                   this.classList.contains('bkp-date-chip--nohours')) return;
                strip.querySelectorAll('.bkp-date-chip').forEach(c=>c.classList.remove('bkp-date-chip--selected'));
                this.classList.add('bkp-date-chip--selected');
                bkpSelectDate(this.dataset.date);
                selSlots=[];document.getElementById('bkp-slot-addbar').style.display='none';
                bkpLoadSlots();
            });
        });
    }

    function bkpUpdateDateStripColors(){
        document.querySelectorAll('.bkp-date-chip').forEach(chip=>{
            var ds=chip.dataset.date,data=dateAvail[ds];
            chip.classList.remove('bkp-date-chip--nohours','bkp-date-chip--full');
            if(!data||data.total_slots===0) chip.classList.add('bkp-date-chip--nohours');
            else if(data.booked_slots>=data.total_slots||data.percentage>=100) chip.classList.add('bkp-date-chip--full');
        });
    }

    function bkpLoadAvail(){
        var yr=calMonth.getFullYear(),mo=calMonth.getMonth();
        var fd=new FormData();
        fd.append('action','bk_get_month_availability');
        fd.append('start_date',bkpFmt(new Date(yr,mo,1)));
        fd.append('end_date',bkpFmt(new Date(yr,mo+1,0)));
        fd.append('business_id',bkBusinessId);
        bkpAjax(fd,json=>{
            if(json.success){dateAvail=json.data;bkpUpdateCalColors();bkpUpdateDateStripColors();}
        });
    }

    function bkpUpdateCalColors(){
        document.querySelectorAll('.bkp-cal-day:not(.bkp-cal-day--other)').forEach(day=>{
            var d=day.dataset.date,data=dateAvail[d];
            day.classList.remove('bkp-cal-day--low','bkp-cal-day--med','bkp-cal-day--full','bkp-cal-day--nohours');
            day.querySelector('.bkp-cal-badge')?.remove();
            if(!data||data.total_slots===0){day.classList.add('bkp-cal-day--nohours');return;}
            var avail=data.total_slots-data.booked_slots;
            if(avail===0||data.percentage>=100) day.classList.add('bkp-cal-day--full');
            else if(data.percentage>=50) day.classList.add('bkp-cal-day--med');
            else day.classList.add('bkp-cal-day--low');
            var badge=document.createElement('div');badge.className='bkp-cal-badge';badge.textContent=avail+'/'+data.total_slots;day.appendChild(badge);
        });
    }

    document.getElementById('bkp-prev-month').addEventListener('click',()=>{calMonth.setMonth(calMonth.getMonth()-1);bkpRenderCal();bkpLoadAvail();});
    document.getElementById('bkp-next-month').addEventListener('click',()=>{calMonth.setMonth(calMonth.getMonth()+1);bkpRenderCal();bkpLoadAvail();});
    document.getElementById('bkp-back-cal').addEventListener('click',()=>{
        slotsPanel.style.opacity='0';
        setTimeout(()=>{slotsPanel.style.display='none';calPanel.style.display='block';calPanel.style.opacity='1';},280);
    });
    document.getElementById('bkp-svc-filter').addEventListener('change',()=>{if(selDate) bkpLoadSlots();});

    function bkpLoadSlots(){
        selSlots=[];document.getElementById('bkp-slot-addbar').style.display='none';
        document.getElementById('bkp-slots-loading').style.display='block';
        document.getElementById('bkp-slots-table-wrap').style.display='none';
        document.getElementById('bkp-slots-msg').style.display='none';
        var fd=new FormData();
        fd.append('action','bk_get_slots_table');
        fd.append('date',selDate);
        fd.append('service_filter',document.getElementById('bkp-svc-filter').value);
        fd.append('business_id',bkBusinessId);
        bkpAjax(fd,json=>{
            document.getElementById('bkp-slots-loading').style.display='none';
            if(json.success&&json.data.slots?.length){
                bkpRenderSlots(json.data);
            } else {
                var m=document.getElementById('bkp-slots-msg');m.style.display='block';
                m.innerHTML='<strong>No available slots</strong><br>No time slots for this date. Please try another day.';
            }
        });
    }

    function bkpRenderSlots(data){
        document.getElementById('bkp-slots-thead').innerHTML='<tr>'+data.services.map(s=>`<th>${s.name}</th>`).join('')+'</tr>';
        document.getElementById('bkp-slots-tbody').innerHTML=data.slots.map(ts=>'<tr>'+data.services.map(s=>{
            var slot=data.slots_by_service[`${s.id}_${ts.time}`];
            if(slot?.available){
                return `<td><button class="bkp-slot-btn bkp-slot-btn--avail" data-service="${s.id}" data-service-name="${s.name}" data-service-price="${s.price}" data-service-duration="${s.duration}" data-time="${ts.time}" data-end-time="${slot.end_time}">${formatTime12Hour(ts.time)}<br><small style="font-size:10px;font-weight:500;opacity:.7;">→${formatTime12Hour(slot.end_time)}</small></button></td>`;
            }
            return `<td><div class="bkp-slot-btn bkp-slot-btn--booked">Booked</div></td>`;
        }).join('')+'</tr>').join('');
        document.getElementById('bkp-slots-table-wrap').style.display='block';

        document.querySelectorAll('.bkp-slot-btn.bkp-slot-btn--avail').forEach(btn=>{
            btn.addEventListener('click',function(){
                if(this.classList.contains('bkp-slot-btn--cart')) return;
                var item={service:this.dataset.service,serviceData:{id:this.dataset.service,name:this.dataset.serviceName,price:this.dataset.servicePrice,duration:this.dataset.serviceDuration},date:selDate,time:this.dataset.time,endTime:this.dataset.endTime};
                var key=bkpCartKey(item),idx=selSlots.findIndex(s=>bkpCartKey(s)===key);
                if(idx>=0){selSlots.splice(idx,1);this.classList.remove('bkp-slot-btn--sel');}
                else{selSlots.push(item);this.classList.add('bkp-slot-btn--sel');}
                selService=this.dataset.service;selTime=this.dataset.time;selEndTime=this.dataset.endTime;
                selServiceData={id:this.dataset.service,name:this.dataset.serviceName,price:this.dataset.servicePrice,duration:this.dataset.serviceDuration};
                bkpUpdateAddBar();
            });
        });
        bkpMarkCartBtns();
    }

    function bkpUpdateAddBar(){
        var bar=document.getElementById('bkp-slot-addbar');
        if(!selSlots.length){bar.style.display='none';return;}
        var total=selSlots.reduce((s,i)=>s+parseFloat(i.serviceData.price||0),0);
        document.getElementById('bkp-slot-addbar-title').textContent=selSlots.length+' time'+(selSlots.length===1?'':'s')+' selected';
        document.getElementById('bkp-slot-addbar-meta').textContent=bkpPrice(total)+' selected total';
        bar.style.display='flex';
    }

    document.getElementById('bkp-add-to-cart').addEventListener('click',()=>{
        if(!selSlots.length) return;
        selSlots.forEach(item=>{
            if(!bookCart.some(c=>bkpCartKey(c)===bkpCartKey(item))) bookCart.push({...item,quantity:1});
        });
        selSlots=[];
        document.getElementById('bkp-slot-addbar').style.display='none';
        document.querySelectorAll('.bkp-slot-btn').forEach(b=>b.classList.remove('bkp-slot-btn--sel'));
        bkpMarkCartBtns();
        bkpUpdateFooterPrice();
    });

    function bkpMarkCartBtns(){
        document.querySelectorAll('.bkp-slot-btn.bkp-slot-btn--avail').forEach(btn=>{
            var inCart=bookCart.some(i=>bkpCartKey(i)===[btn.dataset.service,selDate,btn.dataset.time].join('|'));
            btn.classList.toggle('bkp-slot-btn--cart',inCart);
            if(inCart) btn.innerHTML='In Cart';
            else btn.innerHTML=formatTime12Hour(btn.dataset.time)+'<br><small style="font-size:10px;font-weight:500;opacity:.7;">→'+formatTime12Hour(btn.dataset.endTime)+'</small>';
        });
    }

    function bkpRenderCartReview(){
        var wrap=document.getElementById('bkp-cart-review');
        if(!bookCart.length){wrap.innerHTML='';return;}
        wrap.innerHTML=bookCart.map((item,i)=>{
            var d=new Date(item.date);d.setHours(12,0,0,0);
            return `<div class="bkp-cart-item"><div><strong>${item.serviceData.name}</strong><small>${d.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'})} · ${formatTime12Hour(item.time)} → ${formatTime12Hour(item.endTime)}</small></div><button type="button" data-index="${i}">✕</button></div>`;
        }).join('');
        wrap.querySelectorAll('button').forEach(btn=>{
            btn.addEventListener('click',function(){
                bookCart.splice(+this.dataset.index,1);
                if(!bookCart.length){bkpCloseModal();bkpUpdateFooterPrice();return;}
                bkpRenderCartReview();bkpUpdateModalCalcs();bkpMarkCartBtns();bkpUpdateFooterPrice();
            });
        });
    }

    function bkpUpdateModalCalcs(){
        if(!bookCart.length&&!selServiceData) return;
        if(bookCart.length){
            var sub=bookCart.reduce((s,i)=>s+parseFloat(i.serviceData.price||0),0);
            var dur=bookCart.reduce((s,i)=>s+parseInt(i.serviceData.duration||0),0);
            var total=sub*(1+taxRate/100);
            document.getElementById('bkp-sum-svc').textContent='Multiple ('+bookCart.length+')';
            document.getElementById('bkp-sum-date').textContent='Various';
            document.getElementById('bkp-sum-time').textContent='Various';
            document.getElementById('bkp-sum-dur').textContent=dur+' min total';
            document.getElementById('bkp-sum-total').textContent=bkpPrice(total)+(taxRate?' incl. tax':'');
            document.getElementById('bkp-h-amount').value=total;
            document.getElementById('bkp-h-qty').value=bookCart.length;
        } else {
            var price=parseFloat(selServiceData.price),dur=+selServiceData.duration;
            var total=price*(1+taxRate/100);
            var endMs=new Date(selDate+' '+selTime).getTime()+(dur*60000);
            var endTime=new Date(endMs).toTimeString().substring(0,5);
            var d=new Date(selDate);d.setHours(12,0,0,0);
            document.getElementById('bkp-sum-svc').textContent=selServiceData.name;
            document.getElementById('bkp-sum-date').textContent=d.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'});
            document.getElementById('bkp-sum-time').textContent=formatTime12Hour(selTime)+' → '+formatTime12Hour(endTime);
            document.getElementById('bkp-sum-dur').textContent=dur+' min';
            document.getElementById('bkp-sum-total').textContent=bkpPrice(total)+(taxRate?' incl. tax':'');
            document.getElementById('bkp-h-service').value=selService;
            document.getElementById('bkp-h-date').value=selDate;
            document.getElementById('bkp-h-start').value=selTime;
            document.getElementById('bkp-h-end').value=endTime;
            document.getElementById('bkp-h-amount').value=total;
            document.getElementById('bkp-h-qty').value=1;
        }
    }

    function bkpOpenModal(){
        var sw=window.innerWidth-document.documentElement.clientWidth;
        document.body.style.overflow='hidden';document.body.style.paddingRight=sw+'px';
        if(bookCart.length){
            var f=bookCart[0];selService=f.service;selServiceData=f.serviceData;selDate=f.date;selTime=f.time;selEndTime=f.endTime;
            document.getElementById('bkp-h-service').value=selService;
            document.getElementById('bkp-h-date').value=selDate;
            document.getElementById('bkp-h-start').value=selTime;
        }
        document.getElementById('bkp-avail-warn').style.display='none';
        document.getElementById('bkp-submit-btn').disabled=false;
        bkpRenderCartReview();bkpUpdateModalCalcs();
        modal.classList.add('active');
    }

    function bkpCloseModal(){
        modal.classList.remove('active');
        document.body.style.overflow='';document.body.style.paddingRight='';
    }

    modalOverlay.addEventListener('click',bkpCloseModal);
    modal.querySelector('.bkp-modal-close').addEventListener('click',bkpCloseModal);
    document.addEventListener('keydown',e=>{if(e.key==='Escape'&&modal.classList.contains('active'))bkpCloseModal();});

    document.getElementById('bkp-booking-form').addEventListener('submit',function(e){
        e.preventDefault();
        if(!selTime&&!bookCart.length){alert('Please select a time slot.');return;}
        var btn=document.getElementById('bkp-submit-btn');
        btn.disabled=true;btn.textContent='Processing…';
        var cart=bookCart.length?bookCart.slice():[{service:selService,serviceData:selServiceData,date:selDate,time:selTime,endTime:selEndTime,quantity:1}];
        var fd=new FormData(this);
        fd.append('action','bk_book_cart');
        fd.append('nonce','<?php echo $nonce;?>');
        fd.append('business_id',bkBusinessId);
        fd.append('cart',JSON.stringify(cart));
        bkpAjax(fd,json=>{
            var msgEl=document.getElementById('bkp-booking-msg'),data=json.data||{};
            if(json.success){
                bookCart=[];bkpMarkCartBtns();bkpUpdateFooterPrice();
                var redir=data.redirect_url||data.redirect;
                msgEl.innerHTML=`<div style="padding:13px;background:#d1fae5;border-radius:10px;color:#065f46;font-weight:600;margin-top:10px;">${data.message||(redir?'Booking created! Redirecting…':'Booking confirmed!')}</div>`;
                if(redir) setTimeout(()=>window.location.href=redir,1500);
                else setTimeout(()=>window.location.reload(),2000);
            } else {
                msgEl.innerHTML=`<div style="padding:13px;background:#fee2e2;border-radius:10px;color:#991b1b;font-weight:600;margin-top:10px;">${data.message||'An error occurred. Please try again.'}</div>`;
                btn.disabled=false;btn.textContent='Confirm Booking';
            }
        });
    });

    bkpRenderCal();bkpLoadAvail();
})();
</script>
<?php
    return ob_get_clean();
}

/* ═══════════════════════════════════════════════════════
   4.  TRANSACTION PAGE  [bk_transaction]
   ═══════════════════════════════════════════════════════ */
function bntm_shortcode_bk_transaction() {
    $booking_id = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : '';
    if (empty($booking_id)) return '<div class="bntm-container"><p>Invalid booking ID.</p></div>';

    global $wpdb;
    $bookings_table = $wpdb->prefix . 'bk_bookings';
    $services_table = $wpdb->prefix . 'bk_services';

    if (isset($_GET['bk_return_token'], $_GET['bk_return_result'])) {
        op_bk_handle_payment_success_redirect();
    }

    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT b.*, s.name as service_name, s.duration
         FROM $bookings_table b
         LEFT JOIN $services_table s ON b.service_id=s.id
         WHERE b.rand_id=%s OR b.group_rand_id=%s
         ORDER BY b.booking_date ASC, b.start_time ASC LIMIT 1",
        $booking_id,$booking_id
    ));
    if (!$booking) return '<div class="bntm-container"><p>Booking not found.</p></div>';

    $group_ref = !empty($booking->group_rand_id) ? $booking->group_rand_id : $booking->rand_id;
    $group_bookings = $wpdb->get_results($wpdb->prepare(
        "SELECT b.*, s.name as service_name, s.duration
         FROM $bookings_table b
         LEFT JOIN $services_table s ON b.service_id=s.id
         WHERE b.group_rand_id=%s OR b.rand_id=%s
         ORDER BY b.booking_date ASC, b.start_time ASC",
        $group_ref,$booking->rand_id
    ));
    if (empty($group_bookings)) $group_bookings = [$booking];

    $display_amount = $display_tax = $display_total = 0;
    foreach ($group_bookings as $gb) {
        $display_amount += floatval($gb->amount);
        $display_tax   += floatval($gb->tax);
        $display_total += floatval($gb->total);
    }

    $primary_color = bk_get_primary_color();
    $primary_hover = bk_get_primary_hover_color();

    $status_cfg = [
        'pending'   => ['bg'=>'#fef3c7','border'=>'#f59e0b','icon'=>'⏳','label'=>'Pending'],
        'confirmed' => ['bg'=>'#d1fae5','border'=>'#059669','icon'=>'✅','label'=>'Confirmed'],
        'completed' => ['bg'=>'#d1fae5','border'=>'#059669','icon'=>'✅','label'=>'Completed'],
        'cancelled' => ['bg'=>'#fee2e2','border'=>'#dc2626','icon'=>'❌','label'=>'Cancelled'],
    ];
    $s = $status_cfg[$booking->status] ?? $status_cfg['pending'];

    bntm_shared_head();
    ob_start();
    echo bk_get_primary_root_style_tag();
?>
<div class="bktx-wrap" style="--p:<?php echo esc_attr($primary_color);?>;--ph:<?php echo esc_attr($primary_hover);?>;">

    <!-- STATUS BANNER -->
    <div class="bktx-banner" style="background:<?php echo $s['bg'];?>;border-color:<?php echo $s['border'];?>;">
        <div class="bktx-banner-icon"><?php echo $s['icon'];?></div>
        <div>
            <h2 class="bktx-banner-title">Booking <?php echo esc_html($s['label']);?></h2>
            <p class="bktx-banner-ref">Reference: #<?php echo esc_html($group_ref);?></p>
        </div>
    </div>

    <!-- QR -->
    <div class="bktx-qr-card">
        <img src="<?php echo esc_url('https://api.qrserver.com/v1/create-qr-code/?size=160x160&data='.rawurlencode($group_ref));?>" alt="QR Code" class="bktx-qr">
        <div class="bktx-qr-label"><?php echo esc_html($group_ref);?></div>
    </div>

    <!-- BOOKING INFO -->
    <div class="bktx-card">
        <h3 class="bktx-card-title">Booking Details</h3>
        <?php foreach($group_bookings as $i=>$gb):?>
        <div class="bktx-row">
            <span class="bktx-row-label"><?php echo count($group_bookings)>1?'Booking '.($i+1):'Service';?></span>
            <div class="bktx-row-val">
                <strong><?php echo esc_html($gb->service_name);?></strong>
                <small><?php echo date('F j, Y',strtotime($gb->booking_date));?> · <?php echo date('g:i A',strtotime($gb->start_time));?> – <?php echo date('g:i A',strtotime($gb->end_time));?></small>
            </div>
        </div>
        <?php endforeach;?>
        <div class="bktx-row">
            <span class="bktx-row-label">Status</span>
            <span class="bktx-badge bktx-badge--<?php echo esc_attr($booking->status);?>"><?php echo esc_html(ucfirst($booking->status));?></span>
        </div>
    </div>

    <!-- CUSTOMER -->
    <div class="bktx-card">
        <h3 class="bktx-card-title">Customer</h3>
        <div class="bktx-row"><span class="bktx-row-label">Name</span><span><?php echo esc_html($booking->customer_name);?></span></div>
        <div class="bktx-row"><span class="bktx-row-label">Email</span><span><?php echo esc_html($booking->customer_email);?></span></div>
        <div class="bktx-row"><span class="bktx-row-label">Phone</span><span><?php echo esc_html($booking->customer_phone);?></span></div>
        <?php if(!empty($booking->customer_notes)):?>
        <div class="bktx-row"><span class="bktx-row-label">Notes</span><span><?php echo esc_html($booking->customer_notes);?></span></div>
        <?php endif;?>
    </div>

    <!-- PAYMENT -->
    <div class="bktx-card">
        <h3 class="bktx-card-title">Payment</h3>
        <div class="bktx-row"><span class="bktx-row-label">Amount</span><span><?php echo bk_format_price($display_amount);?></span></div>
        <?php if($display_tax>0):?><div class="bktx-row"><span class="bktx-row-label">Tax</span><span><?php echo bk_format_price($display_tax);?></span></div><?php endif;?>
        <div class="bktx-row bktx-row--total"><span class="bktx-row-label">Total</span><span class="bktx-total"><?php echo bk_format_price($display_total);?></span></div>
        <div class="bktx-row"><span class="bktx-row-label">Method</span><span><?php echo esc_html($booking->payment_method);?></span></div>
        <div class="bktx-row"><span class="bktx-row-label">Payment</span><span class="bktx-badge bktx-badge--pay-<?php echo esc_attr($booking->payment_status);?>"><?php echo ucfirst($booking->payment_status);?></span></div>
        <?php if(!empty($booking->transaction_id)):?>
        <div class="bktx-row"><span class="bktx-row-label">Transaction ID</span><span style="font-size:12px;word-break:break-all;"><?php echo esc_html($booking->transaction_id);?></span></div>
        <?php endif;?>
    </div>

    <a href="<?php echo get_permalink(get_page_by_path('book-appointment'));?>" class="bktx-again-btn">Book Another Appointment</a>
</div>

<style>
.bktx-wrap,.bktx-wrap *{font-family:'Barlow',sans-serif!important;box-sizing:border-box;}

.bktx-wrap{max-width:560px;margin:0 auto;padding:24px 16px 60px;display:grid;gap:14px;}
.bktx-banner{display:flex;align-items:center;gap:16px;padding:20px 22px;border-radius:18px;border:2px solid;}
.bktx-banner-icon{font-size:32px;flex-shrink:0;}
.bktx-banner-title{font-size:22px!important;font-weight:900!important;color:#0f172a!important;text-transform:uppercase;margin:0 0 4px;}
.bktx-banner-ref{font-size:13px;color:#64748b;margin:0;}
.bktx-qr-card{display:flex;flex-direction:column;align-items:center;gap:10px;padding:20px;background:rgba(255,255,255,.9);backdrop-filter:blur(16px);border:1px solid #e2e8f0;border-radius:18px;}
.bktx-qr{width:160px;height:160px;border-radius:12px;}
.bktx-qr-label{font-size:12px;font-weight:700;color:#64748b;letter-spacing:.5px;}
.bktx-card{background:rgba(255,255,255,.9);backdrop-filter:blur(16px);border:1px solid #e2e8f0;border-radius:18px;padding:18px 20px;display:grid;gap:0;}
.bktx-card-title{font-size:16px!important;font-weight:900!important;color:#0f172a!important;text-transform:uppercase;letter-spacing:.4px;margin:0 0 14px;padding-bottom:10px;border-bottom:1px solid #f1f5f9;}
.bktx-row{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;padding:10px 0;border-bottom:1px solid #f8fafc;font-size:14px;color:#0f172a;}
.bktx-row:last-child{border-bottom:none;}
.bktx-row strong{display:block;margin-bottom:2px;}
.bktx-row small{font-size:12px;color:#94a3b8;display:block;}
.bktx-row-label{color:#94a3b8;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.3px;white-space:nowrap;padding-top:2px;flex-shrink:0;min-width:80px;}
.bktx-row-val{text-align:right;}
.bktx-row--total{font-size:16px;font-weight:800;}
.bktx-total{color:var(--p)!important;font-size:18px;font-weight:900;}
.bktx-badge{display:inline-block;padding:5px 12px;border-radius:999px;font-size:12px;font-weight:700;}
.bktx-badge--pending{background:#fef3c7;color:#92400e;}
.bktx-badge--confirmed,.bktx-badge--completed{background:#d1fae5;color:#065f46;}
.bktx-badge--cancelled{background:#fee2e2;color:#991b1b;}
.bktx-badge--pay-paid,.bktx-badge--pay-verified{background:#d1fae5;color:#065f46;}
.bktx-badge--pay-unpaid,.bktx-badge--pay-waiting_payment{background:#fef3c7;color:#92400e;}
.bktx-badge--pay-failed,.bktx-badge--pay-dropped{background:#fee2e2;color:#991b1b;}
.bktx-again-btn{display:block;text-align:center;background:var(--p)!important;color:#fff!important;text-decoration:none!important;border-radius:999px;padding:15px;font-size:17px!important;font-weight:900!important;text-transform:uppercase;letter-spacing:.4px;transition:all .18s;}
.bktx-again-btn:hover{background:var(--ph)!important;transform:translateY(-1px);box-shadow:0 8px 24px rgba(59,130,246,.3);}
</style>
<?php
    return ob_get_clean();
}
   
   add_shortcode('bk_transaction', 'bntm_shortcode_bk_transaction');
   add_shortcode('bk_directory', 'bntm_shortcode_bk_directory');
   add_shortcode('bk_business', 'bntm_shortcode_bk_business');
   
    
   /* ---------- IMPROVED: Get Slots as Table - Better filtering and service selection ---------- */
   add_action('wp_ajax_bk_get_slots_table', 'bntm_ajax_bk_get_slots_table');
   add_action('wp_ajax_nopriv_bk_get_slots_table', 'bntm_ajax_bk_get_slots_table');
   
   function bntm_ajax_bk_get_slots_table() {
       global $wpdb;
       
       $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
       $service_filter = isset($_POST['service_filter']) ? sanitize_text_field($_POST['service_filter']) : '';
       $business_id = bk_get_request_business_id();
       
       if (empty($date)) {
           wp_send_json_error(['message' => 'Missing date parameter']);
       }
       if (!$business_id) {
           wp_send_json_error(['message' => 'Missing business']);
       }
       
       $dateObj = DateTime::createFromFormat('Y-m-d', $date);
       if (!$dateObj) {
           wp_send_json_error(['message' => 'Invalid date format']);
       }
       
       $services_table = $wpdb->prefix . 'bk_services';
       $hours_table = $wpdb->prefix . 'bk_operating_hours';
       $bookings_table = $wpdb->prefix . 'bk_bookings';
       $holidays = array_filter(array_map('trim', explode("\n", bk_get_business_setting($business_id, 'bk_holidays', ''))));
   
       if (in_array($date, $holidays, true)) {
           wp_send_json_success([
               'slots' => [],
               'services' => [],
               'slots_by_service' => [],
               'message' => 'Holiday / closed date'
           ]);
       }
       
       // Get all active services
       $services = $wpdb->get_results($wpdb->prepare(
           "SELECT id, name, duration, price FROM $services_table WHERE business_id = %d AND status = 'active' ORDER BY name ASC",
           $business_id
       ));
       
       if (empty($services)) {
           wp_send_json_error(['message' => 'No services available']);
       }
       
       // If service filter is applied, only include that service
       if (!empty($service_filter)) {
           $service_filter = intval($service_filter);
           $services = array_filter($services, function($service) use ($service_filter) {
               return $service->id == $service_filter;
           });
           $services = array_values($services); // Re-index array
           
           if (empty($services)) {
               wp_send_json_error(['message' => 'Selected service not found']);
           }
       }
       
       $dayOfWeek = date('w', strtotime($date));
       
       // Get operating hours for the day
       $operating_hour = $wpdb->get_row($wpdb->prepare(
           "SELECT * FROM $hours_table WHERE business_id = %d AND day_of_week = %d LIMIT 1",
           $business_id,
           $dayOfWeek
       ));
       
       if (!$operating_hour || !$operating_hour->is_open) {
           wp_send_json_success([
               'slots' => [],
               'services' => $services,
               'slots_by_service' => [],
               'message' => 'Business closed on this day'
           ]);
       }
       
       // Get all bookings for this date - EXCLUDE pending payment status
       // Only block slots for confirmed and completed bookings, or pending bookings that are already paid/waiting for verification
       $bookings = $wpdb->get_results($wpdb->prepare(
           "SELECT service_id, start_time, end_time, payment_status FROM $bookings_table
            WHERE business_id = %d
            AND booking_date = %s
            AND status IN ('pending', 'confirmed', 'completed')
            AND payment_status NOT IN ('pending', 'waiting_payment')",
           $business_id,
           $date
       ));
       
       // Build booked times array indexed by service_id
       $booked_times_by_service = [];
       
       foreach ($services as $service) {
           $booked_times_by_service[$service->id] = [];
       }
       
       foreach ($bookings as $booking) {
           if (!isset($booked_times_by_service[$booking->service_id])) {
               $booked_times_by_service[$booking->service_id] = [];
           }
           $booked_times_by_service[$booking->service_id][] = [
               'start' => strtotime($date . ' ' . $booking->start_time),
               'end' => strtotime($date . ' ' . $booking->end_time)
           ];
       }
       
       // Generate time slots based on interval
       $slot_interval = intval(bntm_get_setting('bk_slot_interval', '30'));
       $start = strtotime($date . ' ' . $operating_hour->start_time);
       $end = strtotime($date . ' ' . $operating_hour->end_time);
       
       $slots = [];
       $current = $start;
       
       while ($current < $end) {
           $time_str = date('H:i', $current);
           $slots[] = ['time' => $time_str];
           $current += $slot_interval * 60;
       }
       
       // Build slots by service (check availability)
       $slots_by_service = [];
       
       foreach ($slots as $slot) {
           $slot_start = strtotime($date . ' ' . $slot['time']);
           
           foreach ($services as $service) {
               $service_duration = intval($service->duration) * 60; // Ensure integer
               $slot_end = $slot_start + $service_duration;
               
               $is_available = true;
               
               // Check if slot overlaps with any booking for this service
               if (isset($booked_times_by_service[$service->id])) {
                   foreach ($booked_times_by_service[$service->id] as $booked) {
                       // Check for overlap: slot overlaps if start < booked.end AND end > booked.start
                       if ($slot_start < $booked['end'] && $slot_end > $booked['start']) {
                           $is_available = false;
                           break;
                       }
                   }
               }
               
               $key = $service->id . '_' . $slot['time'];
               $slots_by_service[$key] = [
                   'available' => $is_available,
                   'end_time' => date('H:i', $slot_end),
                   'service_id' => $service->id
               ];
           }
       }
       
       wp_send_json_success([
           'slots' => $slots,
           'services' => $services,
           'slots_by_service' => $slots_by_service
       ]);
   }
   add_action('wp_ajax_bk_check_slot_availability', 'bntm_ajax_bk_check_slot_availability');
   add_action('wp_ajax_nopriv_bk_check_slot_availability', 'bntm_ajax_bk_check_slot_availability');
   
   function bntm_ajax_bk_check_slot_availability() {
       global $wpdb;
       
       $service_id = intval($_POST['service_id'] ?? 0);
       $business_id = bk_get_request_business_id();
       $booking_date = sanitize_text_field($_POST['booking_date'] ?? '');
       $start_time = sanitize_text_field($_POST['start_time'] ?? '');
       $quantity = intval($_POST['quantity'] ?? 1);
       
       if ($quantity < 1) $quantity = 1;
       if ($quantity > 10) $quantity = 10;
       
       $services_table = $wpdb->prefix . 'bk_services';
       $bookings_table = $wpdb->prefix . 'bk_bookings';
       
       // Get service details
       $service = $wpdb->get_row($wpdb->prepare(
           "SELECT * FROM $services_table WHERE id = %d AND business_id = %d AND status = 'active'",
           $service_id,
           $business_id
       ));
       
       if (!$service) {
           wp_send_json_error(['message' => 'Service not found', 'available' => false]);
       }
       
       // Calculate end time based on quantity
       $start_timestamp = strtotime($booking_date . ' ' . $start_time);
       $total_duration = $service->duration * $quantity;
       $end_timestamp = $start_timestamp + ($total_duration * 60);
       $end_time = date('H:i:s', $end_timestamp);
       
       // Check for conflicts in the entire time range
       $conflict = $wpdb->get_row($wpdb->prepare(
           "SELECT id, customer_name FROM $bookings_table 
            WHERE booking_date = %s 
            AND business_id = %d
            AND service_id = %d
            AND status IN ('pending', 'confirmed', 'completed')
            AND payment_status NOT IN ('pending', 'waiting_payment')
            AND (
               (start_time < %s AND end_time > %s)
               OR (start_time >= %s AND start_time < %s)
            )",
           $booking_date, $business_id, $service_id, $end_time, $start_time, $start_time, $end_time
       ));
       
       if ($conflict) {
           wp_send_json_success([
               'available' => false,
               'message' => 'One or more slots in this time range are already booked. Please select a different time or reduce the quantity.'
           ]);
       }
       
       wp_send_json_success([
           'available' => true,
           'message' => 'All slots are available'
       ]);
   }
   
/* ---------- OPTIMIZED bk_bookings_calendar_tab ---------- */
function bk_bookings_calendar_tab( $business_id ) {
    global $wpdb;
 
    $business_id = absint($business_id ?: bntm_get_current_business_id());
    $services = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}bk_services WHERE business_id = %d AND status = 'active' ORDER BY name ASC",
        $business_id
    ));
    $nonce    = wp_create_nonce( 'bk_nonce' );
    $max_date = date( 'Y-m-d', strtotime( '+30 days' ) );
    $tax_rate = floatval( bntm_get_setting( 'bk_tax_rate', '0' ) );
 
    ob_start();
?>
<script>var ajaxurl='<?php echo admin_url('admin-ajax.php'); ?>';</script>
 
<!-- ══════════════════════════════════════════════════════════════
     MAIN LAYOUT
══════════════════════════════════════════════════════════════ -->
<div class="bkadm-wrap">
 
    <!-- ── CALENDAR VIEW ── -->
    <div id="bkadm-cal-view" class="bkadm-panel bkadm-panel--active">
        <div class="bkadm-card">
            <div class="bkadm-cal-top">
                <h3 class="bkadm-section-title">Booking Calendar</h3>
                <div class="bkadm-legend">
                    <?php foreach ([
                        ['#dcfce7','#16a34a','Available'],
                        ['#fed7aa','#d97706','Busy'],
                        ['#fecaca','#dc2626','Full'],
                    ] as [$bg, $clr, $label]): ?>
                        <div class="bkadm-legend-item">
                            <span class="bkadm-legend-dot" style="background:<?php echo $bg;?>;box-shadow:0 0 0 2px <?php echo $clr;?>40;"></span>
                            <?php echo $label; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
 
            <div class="bkadm-month-nav">
                <button id="adm-prev-month" class="bkadm-nav-btn">
                    <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <h2 id="adm-month-label" class="bkadm-month-label"></h2>
                <button id="adm-next-month" class="bkadm-nav-btn">
                    <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
            </div>
 
            <div class="bkadm-grid">
                <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d): ?>
                    <div class="bkadm-weekday"><?php echo $d; ?></div>
                <?php endforeach; ?>
                <div class="bkadm-days" id="adm-calendar-days"></div>
            </div>
        </div>
    </div>
 
    <!-- ── SLOTS VIEW (with date strip) ── -->
    <div id="bkadm-slots-view" class="bkadm-panel" style="display:none;">
        <div class="bkadm-card">
 
            <!-- Header row -->
            <div class="bkadm-slots-header">
                <button id="adm-back-to-cal" class="bkadm-back-btn">
                    <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                    Calendar
                </button>
                <div>
                    <h2 class="bkadm-slots-title">Booking Slots</h2>
                    <p class="bkadm-slots-date" id="adm-date-display"></p>
                </div>
            </div>
 
            <!-- ── DATE STRIP ── -->
            <div class="bkadm-strip-wrap">
                <div class="bkadm-strip" id="adm-date-strip"><!-- JS --></div>
            </div>
 
            <!-- Service filter -->
            <div class="bkadm-filter-row">
                <label class="bkadm-filter-label">Filter by Service</label>
                <div class="bkadm-select-wrap">
                    <select id="adm-service-filter">
                        <option value="">All Services</option>
                        <?php foreach ($services as $s): ?>
                            <option value="<?php echo $s->id; ?>"><?php echo esc_html($s->name); ?> (<?php echo $s->duration; ?> min)</option>
                        <?php endforeach; ?>
                    </select>
                    <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                </div>
            </div>
 
            <!-- Loading -->
            <div id="adm-slots-loading" class="bkadm-loading" style="display:none;">
                <div class="bkadm-spinner"></div>
                <p>Loading slots…</p>
            </div>
 
            <!-- Table -->
            <div id="adm-slots-container" style="display:none;overflow-x:auto;">
                <table class="bkadm-table">
                    <thead id="adm-slots-head"></thead>
                    <tbody id="adm-slots-body"></tbody>
                </table>
            </div>
 
            <!-- Empty state -->
            <div id="adm-slots-message" class="bkadm-notice" style="display:none;"></div>
        </div>
    </div>
</div><!-- /.bkadm-wrap -->

<button type="button" id="bkadm-floating-book" class="bkadm-floating-book" style="display:none;">
    Book <span id="bkadm-selected-count">0</span>
</button>
 
 
<!-- ══════════════════════════════════════════════════════════════
     EDIT BOOKING MODAL — Step-based redesign
══════════════════════════════════════════════════════════════ -->
<div id="bkadm-edit-modal" class="bkadm-modal">
    <div class="bkadm-modal-overlay"></div>
    <div class="bkadm-modal-box">
        <button class="bkadm-modal-close">&times;</button>
 
        <!-- Modal header with booking ID + status pill -->
        <div class="bkadm-modal-head">
            <div>
                <p class="bkadm-modal-eyebrow">Booking</p>
                <h2 class="bkadm-modal-title" id="em-rand-id-display">#—</h2>
            </div>
            <div class="bkadm-status-pills">
                <span class="bkadm-pill" id="em-status-pill">—</span>
                <span class="bkadm-pill bkadm-pill--pay" id="em-pay-pill">—</span>
            </div>
        </div>
 
        <!-- Step tabs -->
        <div class="bkadm-steps" id="em-steps">
            <button class="bkadm-step bkadm-step--active" data-step="0">Schedule</button>
            <button class="bkadm-step" data-step="1">Payment</button>
            <button class="bkadm-step" data-step="2">Confirm</button>
        </div>
 
        <form id="bkadm-edit-form">
 
            <!-- ── STEP 0: Customer + Schedule ── -->
            <div class="bkadm-step-panel" data-panel="0">
                <div class="bkadm-info-grid">
                    <div class="bkadm-info-block">
                        <span class="bkadm-info-label">Service</span>
                        <span class="bkadm-info-val" id="em-service-name">—</span>
                    </div>
                    <div class="bkadm-info-block">
                        <span class="bkadm-info-label">Customer</span>
                        <span class="bkadm-info-val" id="em-customer-name">—</span>
                    </div>
                    <div class="bkadm-info-block">
                        <span class="bkadm-info-label">Email</span>
                        <span class="bkadm-info-val" id="em-customer-email">—</span>
                    </div>
                    <div class="bkadm-info-block">
                        <span class="bkadm-info-label">Phone</span>
                        <span class="bkadm-info-val" id="em-customer-phone">—</span>
                    </div>
                </div>
 
                <div class="bkadm-divider"></div>
                <p class="bkadm-group-label">Reschedule</p>
 
                <div class="bkadm-field">
                    <label>Date *</label>
                    <input type="date" id="em-booking-date" name="booking_date" required>
                </div>
                <div class="bkadm-row">
                    <div class="bkadm-field">
                        <label>Start Time *</label>
                        <input type="time" id="em-start-time" name="start_time" required>
                    </div>
                    <div class="bkadm-field">
                        <label>End Time *</label>
                        <input type="time" id="em-end-time" name="end_time" required>
                    </div>
                </div>
 
                <!-- Live duration preview -->
                <div class="bkadm-duration-bar" id="em-duration-bar">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <span id="em-duration-text">—</span>
                </div>
            </div>
 
            <!-- ── STEP 1: Payment ── -->
            <div class="bkadm-step-panel" data-panel="1" style="display:none;">
                <div class="bkadm-pricing-card">
                    <div class="bkadm-pricing-row">
                        <span>Unit Price</span><strong id="em-unit-price">—</strong>
                    </div>
                    <div class="bkadm-pricing-row">
                        <span>Slots / Qty</span><strong id="em-quantity">—</strong>
                    </div>
                    <div class="bkadm-pricing-row">
                        <span>Subtotal</span><strong id="em-subtotal">—</strong>
                    </div>
                    <div class="bkadm-pricing-row">
                        <span>Tax (<?php echo $tax_rate; ?>%)</span><strong id="em-tax">—</strong>
                    </div>
                    <div class="bkadm-divider"></div>
                    <div class="bkadm-pricing-row bkadm-pricing-row--total">
                        <span>Total</span><strong id="em-total">—</strong>
                    </div>
                    <div class="bkadm-pricing-row">
                        <span>Payment Method</span><strong id="em-pay-method">—</strong>
                    </div>
                </div>
 
                <div class="bkadm-divider"></div>
                <p class="bkadm-group-label">Update Status</p>
 
                <div class="bkadm-row">
                    <div class="bkadm-field">
                        <label>Booking Status *</label>
                        <div class="bkadm-select-wrap">
                            <select id="em-booking-status" name="status">
                                <option value="pending">Pending</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                            <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                        </div>
                    </div>
                    <div class="bkadm-field">
                        <label>Payment Status *</label>
                        <div class="bkadm-select-wrap">
                            <select id="em-payment-status" name="payment_status">
                                <option value="unpaid">Unpaid – Cash on Arrival</option>
                                <option value="paid">Paid</option>
                            </select>
                            <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                        </div>
                    </div>
                </div>
            </div>
 
            <!-- ── STEP 2: Confirm ── -->
            <div class="bkadm-step-panel" data-panel="2" style="display:none;">
                <div class="bkadm-confirm-grid" id="em-confirm-summary"></div>
 
                <div class="bkadm-info-block" style="margin-top:12px;">
                    <span class="bkadm-info-label">Customer Notes</span>
                    <span class="bkadm-info-val" id="em-notes" style="white-space:pre-wrap;">—</span>
                </div>
                <div class="bkadm-info-block">
                    <span class="bkadm-info-label">Created At</span>
                    <span class="bkadm-info-val" id="em-created-at">—</span>
                </div>
            </div>
 
            <!-- Hidden fields -->
            <?php foreach ([
                'em-rand-id'        => 'booking_id',
                'em-service-id'     => 'service_id',
                'em-svc-duration'   => 'service_duration',
                'em-amount-val'     => 'amount',
                'em-tax-val'        => 'tax',
                'em-total-val'      => 'total',
            ] as $id => $name): ?>
                <input type="hidden" id="<?php echo $id; ?>" name="<?php echo $name; ?>">
            <?php endforeach; ?>
 
            <!-- Step navigation -->
            <div class="bkadm-step-nav">
                <button type="button" class="bkadm-btn bkadm-btn--ghost" id="em-prev-step" style="display:none;">← Back</button>
                <button type="button" class="bkadm-btn bkadm-btn--primary" id="em-next-step">Next →</button>
                <button type="submit" class="bkadm-btn bkadm-btn--success" id="em-save-btn" style="display:none;">
                    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    Save Changes
                </button>
                <button type="button" class="bkadm-btn bkadm-btn--danger" id="em-delete-btn">Delete</button>
            </div>
 
            <div id="em-message" class="bkadm-message"></div>
        </form>
    </div>
</div>
 
 
<!-- ══════════════════════════════════════════════════════════════
     CREATE BOOKING MODAL
══════════════════════════════════════════════════════════════ -->
<div id="bkadm-create-modal" class="bkadm-modal">
    <div class="bkadm-modal-overlay"></div>
    <div class="bkadm-modal-box">
        <button class="bkadm-modal-close">&times;</button>
        <p class="bkadm-modal-eyebrow">Admin</p>
        <h2 class="bkadm-modal-title">New Booking</h2>
 
        <form id="bkadm-create-form">
            <div class="bkadm-field" style="display:none;">
                <label>Service *</label>
                <div class="bkadm-select-wrap">
                    <select id="cr-service-id" name="service_id">
                        <option value="">Select a service...</option>
                        <?php foreach ($services as $s): ?>
                            <option value="<?php echo $s->id; ?>" data-duration="<?php echo $s->duration; ?>" data-price="<?php echo $s->price; ?>">
                                <?php echo esc_html($s->name); ?> (<?php echo $s->duration; ?> min — <?php echo bk_format_price($s->price); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                </div>
            </div>
 
            <div class="bkadm-field" style="display:none;">
                <label>Date *</label>
                <input type="date" id="cr-date" name="booking_date">
            </div>
 
            <div class="bkadm-row" style="display:none;">
                <div class="bkadm-field">
                    <label>Start Time *</label>
                    <input type="time" id="cr-start-time" name="start_time">
                </div>
                <div class="bkadm-field">
                    <label>Quantity (Slots)</label>
                    <div class="bkadm-qty-wrap">
                        <button type="button" id="cr-qty-dec" class="bkadm-qty-btn">-</button>
                        <input type="number" id="cr-quantity" name="quantity" min="1" max="10" value="1" readonly>
                        <button type="button" id="cr-qty-inc" class="bkadm-qty-btn">+</button>
                    </div>
                </div>
            </div>

            <input type="hidden" id="cr-selected-slots" name="selected_slots">
            <div class="bkadm-field">
                <label>Selected Slots *</label>
                <div id="cr-selected-slots-list" class="bkadm-selected-slots-list"></div>
            </div>
 
            <!-- Live summary -->
            <div class="bkadm-pricing-card bkadm-pricing-card--create" id="cr-summary">
                <?php foreach ([
                    ['cr-end-time-disp',  'End Time',       '—'],
                    ['cr-duration-disp',  'Duration',       '—'],
                    ['cr-subtotal-disp',  'Subtotal',       '—'],
                    ['cr-tax-disp',       'Tax ('.$tax_rate.'%)', '—'],
                ] as [$id, $label, $val]): ?>
                    <div class="bkadm-pricing-row">
                        <span><?php echo $label; ?></span>
                        <strong id="<?php echo $id; ?>"><?php echo $val; ?></strong>
                    </div>
                <?php endforeach; ?>
                <div class="bkadm-divider"></div>
                <div class="bkadm-pricing-row bkadm-pricing-row--total">
                    <span>Total</span><strong id="cr-total-disp">—</strong>
                </div>
            </div>
 
            <div class="bkadm-divider"></div>
            <p class="bkadm-group-label">Customer Details</p>
 
            <?php foreach ([
                ['Customer Name *', 'text',  'cr-customer-name',  'customer_name',  '', true],
                ['Email *',         'email', 'cr-customer-email', 'customer_email', '', true],
                ['Phone *',         'tel',   'cr-customer-phone', 'customer_phone', '', true],
                ['Payment Method *','text',  'cr-pay-method',     'payment_method', 'e.g. Cash, GCash...', true],
            ] as [$l, $t, $id, $n, $ph, $req]): ?>
                <div class="bkadm-field">
                    <label><?php echo $l; ?></label>
                    <input type="<?php echo $t; ?>" id="<?php echo $id; ?>" name="<?php echo $n; ?>"
                        <?php echo $ph ? 'placeholder="'.$ph.'"' : ''; ?>
                        <?php echo $req ? 'required' : ''; ?>>
                </div>
            <?php endforeach; ?>
 
            <div class="bkadm-field">
                <label>Payment Status *</label>
                <div class="bkadm-select-wrap">
                    <select id="cr-pay-status" name="payment_status" required>
                        <option value="unpaid">Unpaid – Cash on Arrival</option>
                        <option value="paid">Paid</option>
                    </select>
                    <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                </div>
            </div>
 
            <!-- Hidden computed values -->
            <?php foreach (['cr-end-time'=>'end_time','cr-amount'=>'amount','cr-tax'=>'tax','cr-total'=>'total'] as $id=>$name): ?>
                <input type="hidden" id="<?php echo $id; ?>" name="<?php echo $name; ?>">
            <?php endforeach; ?>
 
            <button type="submit" class="bkadm-btn bkadm-btn--success" style="width:100%;margin-top:8px;">
                <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Create Booking
            </button>
            <div id="cr-message" class="bkadm-message"></div>
        </form>
    </div>
</div>
 
 
<!-- ══════════════════════════════════════════════════════════════
     STYLES
══════════════════════════════════════════════════════════════ -->
<style>
/* ── Tokens ── */
.bkadm-wrap,.bkadm-modal{
    --blue:#3b82f6;--blue-h:#2563eb;--green:#16a34a;--green-bg:#dcfce7;
    --red:#dc2626;--red-bg:#fee2e2;--amber:#d97706;--amber-bg:#fef3c7;
    --slate-900:#0f172a;--slate-700:#334155;--slate-500:#64748b;--slate-200:#e2e8f0;--slate-50:#f8fafc;
}
*,.bkadm-wrap *,.bkadm-modal *{box-sizing:border-box;}
 
/* ── Wrap ── */
.bkadm-wrap{max-width:100%;padding:0;}
 
/* ── Card ── */
.bkadm-card{background:#fff;border-radius:16px;padding:24px;box-shadow:0 2px 12px rgba(15,23,42,.08);border:1px solid var(--slate-200);margin-bottom:16px;animation:bkadm-fadein .35s ease both;}
@keyframes bkadm-fadein{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
 
/* ── Section title ── */
.bkadm-cal-top{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px;}
.bkadm-section-title{font-size:20px;font-weight:800;color:var(--slate-900);margin:0;text-transform:uppercase;letter-spacing:.3px;}
 
/* ── Legend ── */
.bkadm-legend{display:flex;gap:14px;flex-wrap:wrap;}
.bkadm-legend-item{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--slate-500);font-weight:600;}
.bkadm-legend-dot{width:12px;height:12px;border-radius:50%;display:inline-block;}
 
/* ── Month nav ── */
.bkadm-month-nav{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;}
.bkadm-month-label{font-size:18px;font-weight:800;color:var(--slate-900);margin:0;min-width:200px;text-align:center;text-transform:uppercase;}
.bkadm-nav-btn{width:38px;height:38px;border-radius:10px;border:1.5px solid var(--slate-200);background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .18s;}
.bkadm-nav-btn:hover{border-color:var(--blue);background:var(--blue);}
.bkadm-nav-btn:hover svg{stroke:#fff;}
.bkadm-nav-btn svg{width:16px;height:16px;stroke:var(--slate-500);fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round;}
 
/* ── Calendar grid ── */
.bkadm-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:6px;}
.bkadm-weekday{text-align:center;font-size:11px;font-weight:700;color:var(--slate-500);padding:6px 0;text-transform:uppercase;letter-spacing:.5px;}
.bkadm-days{display:grid;grid-template-columns:repeat(7,1fr);gap:6px;grid-column:1/-1;}
.bkadm-day{aspect-ratio:1;display:flex;flex-direction:column;align-items:center;justify-content:center;border-radius:10px;cursor:pointer;font-weight:700;font-size:13px;border:2px solid transparent;position:relative;color:var(--slate-900);background:var(--slate-50);transition:all .18s;}
.bkadm-day:hover:not(.bkadm-day--other):not(.bkadm-day--disabled):not(.bkadm-day--nohours){transform:scale(1.07);border-color:var(--blue);box-shadow:0 4px 16px rgba(59,130,246,.18);}
.bkadm-day--other{color:#cbd5e1;cursor:default;background:transparent;}
.bkadm-day--disabled{opacity:.3;cursor:not-allowed;}
.bkadm-day--nohours{opacity:.35;cursor:not-allowed;background:#f1f5f9;}
.bkadm-day--low{background:var(--green-bg);border-color:#86efac;}
.bkadm-day--med{background:var(--amber-bg);border-color:#fde68a;}
.bkadm-day--full{background:var(--red-bg);border-color:#fca5a5;cursor:not-allowed;opacity:.6;}
.bkadm-day--selected{background:var(--blue)!important;border-color:var(--blue)!important;color:#fff!important;box-shadow:0 6px 20px rgba(59,130,246,.3);}
.bkadm-day-badge{position:absolute;bottom:3px;font-size:9px;font-weight:600;opacity:.75;background:rgba(255,255,255,.85);padding:1px 5px;border-radius:8px;}
.bkadm-day--selected .bkadm-day-badge{color:#fff;background:rgba(255,255,255,.2);}
 
/* ── SLOTS VIEW ── */
.bkadm-slots-header{display:flex;align-items:center;gap:14px;margin-bottom:16px;}
.bkadm-back-btn{display:inline-flex;align-items:center;gap:4px;padding:8px 14px;border-radius:10px;border:1.5px solid var(--slate-200);background:#fff;cursor:pointer;font-size:13px;font-weight:700;color:var(--slate-500);transition:all .18s;white-space:nowrap;}
.bkadm-back-btn:hover{border-color:var(--blue);color:var(--blue);}
.bkadm-back-btn svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round;}
.bkadm-slots-title{font-size:20px;font-weight:800;color:var(--slate-900);margin:0;text-transform:uppercase;}
.bkadm-slots-date{font-size:12px;color:var(--slate-500);margin:2px 0 0;font-weight:600;}
 
/* ── DATE STRIP ── */
.bkadm-strip-wrap{margin-bottom:16px;overflow:hidden;}
.bkadm-strip{display:flex;gap:8px;overflow-x:auto;scrollbar-width:none;padding:4px 2px 8px;}
.bkadm-strip::-webkit-scrollbar{display:none;}
.bkadm-strip-chip{flex-shrink:0;display:flex;flex-direction:column;align-items:center;justify-content:center;min-width:54px;padding:9px 6px;border-radius:12px;border:1.5px solid var(--slate-200);background:#fff;cursor:pointer;transition:all .18s;gap:2px;}
.bkadm-strip-chip:hover:not(.bkadm-chip--disabled):not(.bkadm-chip--full):not(.bkadm-chip--nohours){border-color:var(--blue);background:rgba(59,130,246,.06);}
.bkadm-strip-chip.bkadm-chip--selected{background:var(--blue)!important;border-color:var(--blue)!important;box-shadow:0 4px 14px rgba(59,130,246,.28);}
.bkadm-chip--disabled,.bkadm-chip--nohours{opacity:.3;cursor:not-allowed;}
.bkadm-chip--full{opacity:.5;cursor:not-allowed;background:rgba(248,113,113,.1)!important;border-color:#f87171!important;}
.bkadm-chip-day{font-size:10px;font-weight:700;color:var(--slate-500);text-transform:uppercase;letter-spacing:.3px;}
.bkadm-chip-num{font-size:19px;font-weight:900;color:var(--slate-900);line-height:1;}
.bkadm-chip-dot{width:5px;height:5px;border-radius:50%;margin-top:2px;background:transparent;}
.bkadm-chip--low .bkadm-chip-dot{background:#16a34a;}
.bkadm-chip--med .bkadm-chip-dot{background:#d97706;}
.bkadm-chip--full .bkadm-chip-dot{background:#dc2626;}
.bkadm-strip-chip.bkadm-chip--selected .bkadm-chip-day,
.bkadm-strip-chip.bkadm-chip--selected .bkadm-chip-num{color:#fff!important;}
 
/* ── Filter ── */
.bkadm-filter-row{margin-bottom:16px;}
.bkadm-filter-label{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--slate-500);margin-bottom:6px;}
.bkadm-select-wrap{position:relative;}
.bkadm-select-wrap select{width:100%;padding:10px 38px 10px 13px;border:1.5px solid var(--slate-200);border-radius:10px;background:#fff;font-size:14px;color:var(--slate-900);appearance:none;cursor:pointer;transition:border-color .18s;font-family:inherit;}
.bkadm-select-wrap select:focus{outline:none;border-color:var(--blue);}
.bkadm-select-wrap svg{position:absolute;right:11px;top:50%;transform:translateY(-50%);width:15px;height:15px;stroke:var(--slate-500);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;pointer-events:none;}
 
/* ── Loading ── */
.bkadm-loading{display:flex;flex-direction:column;align-items:center;gap:10px;padding:40px;color:var(--slate-500);}
.bkadm-spinner{width:30px;height:30px;border:3px solid rgba(59,130,246,.15);border-top-color:var(--blue);border-radius:50%;animation:bkadm-spin .7s linear infinite;}
@keyframes bkadm-spin{to{transform:rotate(360deg)}}
 
/* ── Table ── */
.bkadm-table{width:100%;border-collapse:collapse;font-size:13px;min-width:400px;}
.bkadm-table thead{background:var(--slate-50);}
.bkadm-table th{padding:10px 12px;text-align:center;font-size:11px;font-weight:800;color:var(--slate-900);text-transform:uppercase;letter-spacing:.4px;border-bottom:2px solid var(--slate-200);}
.bkadm-table td{padding:7px 8px;text-align:center;border-bottom:1px solid #f1f5f9;}
.bkadm-table tbody tr:hover{background:rgba(59,130,246,.03);}
.bkadm-slot-cell{width:100%;padding:8px 6px;border-radius:8px;font-size:12px;font-weight:700;border:1.5px solid var(--slate-200);cursor:pointer;transition:all .18s;text-align:center;background:#fff;}
.bkadm-slot-cell.vacant{background:#f0fdf4;border-color:#86efac;color:#15803d;}
.bkadm-slot-cell.vacant:hover{background:#dcfce7;}
.bkadm-slot-cell.vacant.is-selected{background:var(--blue);border-color:var(--blue);color:#fff;box-shadow:0 4px 14px rgba(59,130,246,.28);}
.bkadm-slot-cell.booked{background:#fff1f2;border-color:#fda4af;color:#be123c;}
.bkadm-slot-cell.booked:hover{background:#ffe4e6;}
.bkadm-slot-cell.booked-paid{background:#eff6ff;border-color:#93c5fd;color:#1d4ed8;}
.bkadm-slot-cell.booked-paid:hover{background:#dbeafe;}
.bkadm-slot-cell.booked-completed{background:#f1f5f9;border-color:#cbd5e1;color:#475569;}
.bkadm-slot-time-label{font-weight:800;background:var(--slate-50);width:64px;min-width:64px;}
.bkadm-slot-cust{display:block;font-weight:700;font-size:12px;}
.bkadm-slot-email{display:block;font-size:10px;opacity:.75;margin-top:1px;}
.bkadm-slot-paystatus{display:block;font-size:10px;margin-top:2px;font-weight:600;}
 
/* ── Notice ── */
.bkadm-notice{padding:16px;border-radius:10px;background:var(--amber-bg);border:1.5px solid #fde68a;color:#92400e;font-size:13px;text-align:center;}
.bkadm-floating-book{position:fixed;right:24px;bottom:24px;z-index:99999;border:none;border-radius:999px;background:var(--bntm-primary,#3b82f6);color:#fff;font-weight:900;font-size:16px;padding:14px 22px;box-shadow:0 14px 34px rgba(15,23,42,.24);cursor:pointer;text-transform:uppercase;letter-spacing:.3px;}
.bkadm-floating-book:hover{background:var(--bntm-primary-hover,#2563eb);transform:translateY(-1px);}
.bkadm-floating-book span{display:inline-flex;align-items:center;justify-content:center;min-width:24px;height:24px;margin-left:8px;padding:0 7px;border-radius:999px;background:rgba(255,255,255,.22);font-size:13px;}
.bkadm-selected-slots-list{display:grid;gap:8px;}
.bkadm-selected-slot{display:flex;justify-content:space-between;gap:12px;align-items:center;padding:10px 12px;background:var(--slate-50);border:1px solid var(--slate-200);border-radius:10px;font-size:13px;}
.bkadm-selected-slot strong{color:var(--slate-900);}
.bkadm-selected-slot small{display:block;color:var(--slate-500);margin-top:2px;}
 
/* ══════════════════════
   MODALS
══════════════════════ */
.bkadm-modal{display:none;position:fixed;inset:0;z-index:100000;}
.bkadm-modal.active{display:block;}
.bkadm-modal-overlay{position:absolute;inset:0;background:rgba(15,23,42,.72);backdrop-filter:blur(6px);}
.bkadm-modal-box{position:relative;max-width:640px;margin:40px auto;background:#fff;border-radius:20px;box-shadow:0 24px 64px rgba(15,23,42,.28);max-height:calc(100vh - 80px);overflow-y:auto;padding:28px 26px;animation:bkadm-fadein .3s ease both;}
.bkadm-modal-close{position:absolute;top:14px;right:14px;width:34px;height:34px;border-radius:50%;background:var(--slate-50);border:none;font-size:20px;cursor:pointer;color:var(--slate-500);transition:all .18s;display:flex;align-items:center;justify-content:center;line-height:1;}
.bkadm-modal-close:hover{background:var(--slate-200);color:var(--slate-900);}
.bkadm-modal-eyebrow{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--blue);margin:0 0 2px;}
.bkadm-modal-title{font-size:24px;font-weight:900;color:var(--slate-900);margin:0 0 16px;text-transform:uppercase;}
 
/* Modal header with pills */
.bkadm-modal-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:16px;}
.bkadm-status-pills{display:flex;flex-direction:column;gap:6px;align-items:flex-end;}
.bkadm-pill{display:inline-block;padding:4px 12px;border-radius:999px;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.4px;background:var(--slate-200);color:var(--slate-700);}
.bkadm-pill--pending{background:#fef3c7;color:#92400e;}
.bkadm-pill--confirmed{background:#dcfce7;color:#15803d;}
.bkadm-pill--cancelled{background:var(--red-bg);color:var(--red);}
.bkadm-pill--pay.paid{background:#dbeafe;color:#1e40af;}
.bkadm-pill--pay.unpaid{background:#fef9c3;color:#854d0e;}
 
/* Step tabs */
.bkadm-steps{display:flex;gap:4px;background:var(--slate-50);border-radius:10px;padding:4px;margin-bottom:20px;}
.bkadm-step{flex:1;padding:9px 6px;border:none;background:transparent;border-radius:8px;font-size:12px;font-weight:700;color:var(--slate-500);cursor:pointer;transition:all .18s;white-space:nowrap;}
.bkadm-step.bkadm-step--active{background:#fff;color:var(--blue);box-shadow:0 2px 8px rgba(15,23,42,.1);}
 
/* Info grid */
.bkadm-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;}
.bkadm-info-block{display:flex;flex-direction:column;gap:4px;}
.bkadm-info-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--slate-500);}
.bkadm-info-val{font-size:13px;font-weight:600;color:var(--slate-900);padding:9px 11px;background:var(--slate-50);border:1px solid var(--slate-200);border-radius:8px;word-break:break-word;}
 
/* Form fields */
.bkadm-field{display:flex;flex-direction:column;gap:5px;margin-bottom:12px;}
.bkadm-field label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--slate-500);}
.bkadm-field input,.bkadm-field textarea{padding:10px 13px;border:1.5px solid var(--slate-200);border-radius:10px;font-size:14px;color:var(--slate-900);background:#fff;transition:border-color .18s;font-family:inherit;width:100%;}
.bkadm-field input:focus,.bkadm-field textarea:focus{outline:none;border-color:var(--blue);}
.bkadm-row{display:flex;gap:12px;}
.bkadm-row .bkadm-field{flex:1;}
 
/* Duration bar */
.bkadm-duration-bar{display:flex;align-items:center;gap:8px;padding:11px 14px;border-radius:10px;background:#eff6ff;border:1.5px solid #bfdbfe;color:#1d4ed8;font-size:13px;font-weight:700;margin-top:2px;margin-bottom:12px;}
.bkadm-duration-bar svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0;}
 
/* Pricing card */
.bkadm-pricing-card{background:linear-gradient(135deg,#f0f9ff,#eff6ff);border:1.5px solid #bfdbfe;border-radius:14px;padding:16px 18px;margin-bottom:16px;}
.bkadm-pricing-card--create{background:linear-gradient(135deg,#f0fdf4,#ecfdf5)!important;border-color:#86efac!important;}
.bkadm-pricing-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;font-size:13px;color:var(--slate-500);}
.bkadm-pricing-row:last-child{margin-bottom:0;}
.bkadm-pricing-row strong{color:var(--slate-900);font-weight:700;}
.bkadm-pricing-row--total{font-size:16px;font-weight:900;color:var(--slate-900);}
.bkadm-pricing-row--total strong{color:#059669;font-size:18px;}
 
/* Divider + group label */
.bkadm-divider{height:1px;background:var(--slate-200);margin:14px 0;}
.bkadm-group-label{font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--slate-500);margin:0 0 12px;}
 
/* Confirm summary grid */
.bkadm-confirm-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:4px;}
 
/* Step nav */
.bkadm-step-nav{display:flex;gap:10px;margin-top:20px;flex-wrap:wrap;}
 
/* Qty */
.bkadm-qty-wrap{display:flex;align-items:center;gap:6px;}
.bkadm-qty-btn{width:34px;height:34px;border-radius:8px;border:1.5px solid var(--blue);background:#fff;color:var(--blue);font-size:18px;font-weight:900;cursor:pointer;transition:all .18s;display:flex;align-items:center;justify-content:center;flex-shrink:0;line-height:1;}
.bkadm-qty-btn:hover:not(:disabled){background:var(--blue);color:#fff;}
.bkadm-qty-btn:disabled{opacity:.35;cursor:not-allowed;border-color:var(--slate-200);color:var(--slate-500);}
#cr-quantity{width:60px;text-align:center;font-weight:700;font-size:16px;padding:8px;border:1.5px solid var(--slate-200);border-radius:8px;-moz-appearance:textfield;}
#cr-quantity::-webkit-inner-spin-button,#cr-quantity::-webkit-outer-spin-button{-webkit-appearance:none;}
 
/* Buttons */
.bkadm-btn{display:inline-flex;align-items:center;gap:7px;padding:11px 20px;border-radius:10px;border:none;font-size:14px;font-weight:800;cursor:pointer;transition:all .18s;font-family:inherit;text-transform:uppercase;letter-spacing:.3px;line-height:1;}
.bkadm-btn svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round;}
.bkadm-btn--primary{background:var(--blue);color:#fff;}
.bkadm-btn--primary:hover{background:var(--blue-h);transform:translateY(-1px);}
.bkadm-btn--success{background:#059669;color:#fff;}
.bkadm-btn--success:hover{background:#047857;transform:translateY(-1px);}
.bkadm-btn--danger{background:var(--red-bg);color:var(--red);margin-left:auto;}
.bkadm-btn--danger:hover{background:#fecaca;}
.bkadm-btn--ghost{background:var(--slate-50);color:var(--slate-700);border:1.5px solid var(--slate-200);}
.bkadm-btn--ghost:hover{border-color:var(--blue);color:var(--blue);}
.bkadm-btn:disabled{opacity:.5;cursor:not-allowed;transform:none!important;}
 
/* Message */
.bkadm-message{margin-top:12px;}
.bkadm-msg-success{padding:11px 14px;background:#d1fae5;border-radius:10px;color:#065f46;font-weight:700;font-size:13px;}
.bkadm-msg-error{padding:11px 14px;background:var(--red-bg);border-radius:10px;color:var(--red);font-weight:700;font-size:13px;}
 
@media(max-width:640px){
    .bkadm-row{flex-direction:column;}
    .bkadm-info-grid,.bkadm-confirm-grid{grid-template-columns:1fr;}
    .bkadm-modal-box{margin:16px;padding:20px 16px;}
    .bkadm-step-nav{gap:8px;}
    .bkadm-btn--danger{margin-left:0;}
    .bkadm-day-badge{display:none;}
}
</style>
 
 
<!-- ══════════════════════════════════════════════════════════════
     JAVASCRIPT
══════════════════════════════════════════════════════════════ -->
<script>
(function(){
    /* ── Utils ── */
    const $  = id => document.getElementById(id);
    const ajax = (fd, cb) => fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(cb).catch(console.error);
    function fmtDate(d){ return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0'); }
    function fmtTime12(t){ if(!t)return''; const [h,m]=t.split(':'),hr=+h,ap=hr>=12?'PM':'AM'; return(hr%12||12)+':'+m+' '+ap; }
    function diffMin(s,e){ return (new Date('2000-01-01 '+e)-new Date('2000-01-01 '+s))/60000; }
    function addMin(t,m){ return new Date(new Date('2000-01-01 '+t).getTime()+m*60000).toTimeString().substr(0,5); }
    function durLabel(m){ const h=Math.floor(m/60),mn=m%60; return (h?h+'h ':'')+(mn?mn+'min':''); }
 
    /* ── State ── */
    let currentMonth = new Date('<?php echo date('Y-m'); ?>-01');
    let selectedDate = null;
    let selectedAdminSlots = [];
    let dateAvailability = {};
    const taxRate = <?php echo $tax_rate; ?>;
    const nonce   = '<?php echo $nonce; ?>';
 
    /* ── DOM refs ── */
    const calView      = $('bkadm-cal-view');
    const slotsView    = $('bkadm-slots-view');
    const editModal    = $('bkadm-edit-modal');
    const createModal  = $('bkadm-create-modal');
    const serviceFilter= $('adm-service-filter');
 
    /* ──────────────────────────────
       CALENDAR
    ────────────────────────────── */
    renderCalendar();
    loadMonthAvailability();
 
    $('adm-prev-month').addEventListener('click', () => {
        currentMonth.setMonth(currentMonth.getMonth() - 1);
        renderCalendar(); loadMonthAvailability();
    });
    $('adm-next-month').addEventListener('click', () => {
        currentMonth.setMonth(currentMonth.getMonth() + 1);
        renderCalendar(); loadMonthAvailability();
    });
 
    window.loadMonthAvailability = window.loadMonthAvailability || function(){
        const yr = currentMonth.getFullYear(), mo = currentMonth.getMonth();
        const fd = new FormData();
        fd.append('action','bk_get_month_availability');
        fd.append('start_date', fmtDate(new Date(yr,mo,1)));
        fd.append('end_date',   fmtDate(new Date(yr,mo+1,0)));
        ajax(fd, json => { if(json.success){ dateAvailability = json.data; updateCalColors(); } });
    }
 
    function updateCalColors(){
        document.querySelectorAll('.bkadm-day:not(.bkadm-day--other)').forEach(day => {
            const data = dateAvailability[day.dataset.date];
            day.classList.remove('bkadm-day--low','bkadm-day--med','bkadm-day--full','bkadm-day--nohours');
            day.querySelector('.bkadm-day-badge')?.remove();
            if(!data || data.total_slots === 0){ day.classList.add('bkadm-day--nohours'); return; }
            const avail = data.total_slots - data.booked_slots;
            if(avail === 0 || data.percentage >= 100) day.classList.add('bkadm-day--full');
            else if(data.percentage >= 50)            day.classList.add('bkadm-day--med');
            else                                      day.classList.add('bkadm-day--low');
            const badge = document.createElement('div');
            badge.className = 'bkadm-day-badge';
            badge.textContent = avail+'/'+data.total_slots;
            day.appendChild(badge);
        });
        updateStripColors();
    }
 
    function renderCalendar(){
        const yr = currentMonth.getFullYear(), mo = currentMonth.getMonth();
        $('adm-month-label').textContent = new Date(yr,mo).toLocaleDateString('en-US',{month:'long',year:'numeric'});
        const firstDay = new Date(yr,mo,1), lastDay = new Date(yr,mo+1,0), startDay = firstDay.getDay();
        let html = '';
        for(let i=startDay-1; i>=0; i--) html += `<div class="bkadm-day bkadm-day--other">${new Date(yr,mo,0).getDate()-i}</div>`;
        for(let d=1; d<=lastDay.getDate(); d++){
            const ds = fmtDate(new Date(yr,mo,d));
            let cls = 'bkadm-day';
            if(ds === selectedDate) cls += ' bkadm-day--selected';
            html += `<div class="${cls}" data-date="${ds}">${d}</div>`;
        }
        const rem = 42-(startDay+lastDay.getDate());
        for(let d=1; d<=rem; d++) html += `<div class="bkadm-day bkadm-day--other">${d}</div>`;
        $('adm-calendar-days').innerHTML = html;
 
        document.querySelectorAll('.bkadm-day:not(.bkadm-day--other)').forEach(day => {
            day.addEventListener('click', function(){
                if(this.classList.contains('bkadm-day--nohours') || this.classList.contains('bkadm-day--full')) return;
                selectDate(this.dataset.date);
                calView.style.opacity = '0';
                setTimeout(() => {
                    calView.style.display = 'none';
                    slotsView.style.display = 'block';
                    slotsView.style.opacity = '0';
                    setTimeout(() => slotsView.style.opacity = '1', 10);
                    buildDateStrip();
                    loadSlots();
                }, 280);
            });
        });
        updateCalColors();
    }
 
    function selectDate(ds){
        selectedDate = ds;
        document.querySelectorAll('.bkadm-day').forEach(d => d.classList.remove('bkadm-day--selected'));
        document.querySelector(`.bkadm-day[data-date="${ds}"]`)?.classList.add('bkadm-day--selected');
        const dt = new Date(ds); dt.setHours(12);
        $('adm-date-display').textContent = dt.toLocaleDateString('en-US',{weekday:'long',month:'long',day:'numeric',year:'numeric'});
    }
 
    $('adm-back-to-cal').addEventListener('click', () => {
        clearSelectedSlots();
        slotsView.style.opacity = '0';
        setTimeout(() => { slotsView.style.display = 'none'; calView.style.display = 'block'; calView.style.opacity = '1'; }, 280);
    });
 
    /* ──────────────────────────────
       DATE STRIP
    ────────────────────────────── */
    function buildDateStrip(){
        const yr = currentMonth.getFullYear(), mo = currentMonth.getMonth();
        const lastDay = new Date(yr,mo+1,0).getDate();
        const dayLabels = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
        let html = '';
        for(let day=1; day<=lastDay; day++){
            const date = new Date(yr,mo,day), ds = fmtDate(date);
            const data = dateAvailability[ds];
            let cls = 'bkadm-strip-chip';
            let dotCls = '';
            if(!data || data.total_slots === 0){ cls += ' bkadm-chip--nohours'; }
            else if(data.booked_slots >= data.total_slots || data.percentage >= 100){ cls += ' bkadm-chip--full'; dotCls = 'bkadm-chip--full'; }
            else if(data.percentage >= 50){ cls += ' bkadm-chip--med'; dotCls = 'bkadm-chip--med'; }
            else{ dotCls = 'bkadm-chip--low'; }
            if(ds === selectedDate) cls += ' bkadm-chip--selected';
            html += `<div class="${cls} ${dotCls}" data-date="${ds}">
                <span class="bkadm-chip-day">${dayLabels[date.getDay()]}</span>
                <span class="bkadm-chip-num">${day}</span>
                <span class="bkadm-chip-dot"></span>
            </div>`;
        }
        const strip = $('adm-date-strip');
        strip.innerHTML = html;
 
        const sel = strip.querySelector('.bkadm-chip--selected');
        if(sel) setTimeout(() => sel.scrollIntoView({behavior:'smooth',block:'nearest',inline:'center'}), 80);
 
        strip.querySelectorAll('.bkadm-strip-chip').forEach(chip => {
            chip.addEventListener('click', function(){
                if(this.classList.contains('bkadm-chip--disabled') ||
                   this.classList.contains('bkadm-chip--full') ||
                   this.classList.contains('bkadm-chip--nohours')) return;
                strip.querySelectorAll('.bkadm-strip-chip').forEach(c => c.classList.remove('bkadm-chip--selected'));
                this.classList.add('bkadm-chip--selected');
                selectDate(this.dataset.date);
                clearSelectedSlots();
                loadSlots();
            });
        });
    }
 
    function updateStripColors(){
        document.querySelectorAll('.bkadm-strip-chip').forEach(chip => {
            const data = dateAvailability[chip.dataset.date];
            chip.classList.remove('bkadm-chip--nohours','bkadm-chip--full','bkadm-chip--med','bkadm-chip--low');
            chip.querySelector('.bkadm-chip-dot').style.background = '';
            if(!data || data.total_slots === 0) chip.classList.add('bkadm-chip--nohours');
            else if(data.booked_slots >= data.total_slots || data.percentage >= 100) chip.classList.add('bkadm-chip--full');
            else if(data.percentage >= 50) chip.classList.add('bkadm-chip--med');
            else chip.classList.add('bkadm-chip--low');
        });
    }
 
    serviceFilter.addEventListener('change', () => { clearSelectedSlots(); loadSlots(); });
 
    /* ──────────────────────────────
       SLOTS TABLE
    ────────────────────────────── */
    function loadSlots(){
        if(!selectedDate) return;
        clearSelectedSlots();
        $('adm-slots-loading').style.display = 'flex';
        $('adm-slots-container').style.display = 'none';
        $('adm-slots-message').style.display = 'none';
        const fd = new FormData();
        fd.append('action','bk_get_admin_slots');
        fd.append('date', selectedDate);
        fd.append('service_filter', serviceFilter.value);
        ajax(fd, json => {
            $('adm-slots-loading').style.display = 'none';
            if(json.success && json.data.slots?.length){
                renderSlotsTable(json.data);
            } else {
                const msg = $('adm-slots-message');
                msg.style.display = 'block';
                msg.innerHTML = '<strong>No Operating Hours</strong><br>No time slots configured for this date.';
            }
        });
    }
 
    window.renderSlotsTable = window.renderSlotsTable || function(data){
        $('adm-slots-head').innerHTML = '<tr><th>Time</th>'+data.services.map(s=>`<th>${s.name}</th>`).join('')+'</tr>';
        $('adm-slots-body').innerHTML = data.slots.map(ts =>
            '<tr><td class="bkadm-slot-time-label"><strong>'+fmtTime12(ts.time)+'</strong></td>'+
            data.services.map(s => {
                const slot = data.slots_by_service[`${s.id}_${ts.time}`];
                if(slot?.available){
                    return `<td><button class="bkadm-slot-cell vacant" data-service="${s.id}" data-service-name="${s.name}" data-duration="${s.duration}" data-price="${s.price}" data-time="${ts.time}" data-end-time="${slot.end_time}" onclick="bkAdmToggleSlot(this)">+ Select</button></td>`;
                }
                if(slot?.booking){
                    const b = slot.booking;
                    const cls = slot.status === 'completed' ? 'booked-completed' :
                                (slot.payment_status === 'paid' || slot.payment_status === 'verified') ? 'booked-paid' : 'booked';
                    return `<td><div class="bkadm-slot-cell ${cls}" onclick="bkAdmOpenEdit('${b.rand_id}')" style="cursor:pointer;">
                        <span class="bkadm-slot-cust">${b.customer_name}</span>
                        <span class="bkadm-slot-email">${b.customer_email}</span>
                        <span class="bkadm-slot-paystatus">${b.payment_status}</span>
                    </div></td>`;
                }
                return `<td><div class="bkadm-slot-cell" style="background:#f8fafc;color:#cbd5e1;">—</div></td>`;
            }).join('')+'</tr>'
        ).join('');
        $('adm-slots-container').style.display = 'block';
    }

    function selectedSlotKey(slot){
        return slot.service_id + '|' + slot.date + '|' + slot.start_time;
    }

    function updateFloatingBook(){
        $('bkadm-selected-count').textContent = selectedAdminSlots.length;
        $('bkadm-floating-book').style.display = selectedAdminSlots.length ? 'inline-flex' : 'none';
    }

    function clearSelectedSlots(){
        selectedAdminSlots = [];
        document.querySelectorAll('.bkadm-slot-cell.vacant.is-selected').forEach(btn => {
            btn.classList.remove('is-selected');
            btn.textContent = '+ Select';
        });
        updateFloatingBook();
    }

    window.bkAdmToggleSlot = function(btn){
        const slot = {
            service_id: btn.dataset.service,
            service_name: btn.dataset.serviceName,
            date: selectedDate,
            start_time: btn.dataset.time,
            end_time: btn.dataset.endTime,
            duration: parseInt(btn.dataset.duration, 10) || diffMin(btn.dataset.time, btn.dataset.endTime),
            price: parseFloat(btn.dataset.price || '0')
        };
        const key = selectedSlotKey(slot);
        const index = selectedAdminSlots.findIndex(item => selectedSlotKey(item) === key);
        if(index >= 0){
            selectedAdminSlots.splice(index, 1);
            btn.classList.remove('is-selected');
            btn.textContent = '+ Select';
        } else {
            selectedAdminSlots.push(slot);
            selectedAdminSlots.sort((a,b) => (a.date + a.start_time + a.service_id).localeCompare(b.date + b.start_time + b.service_id));
            btn.classList.add('is-selected');
            btn.textContent = 'Selected';
        }
        updateFloatingBook();
    };

    $('bkadm-floating-book').addEventListener('click', function(){
        if(selectedAdminSlots.length) bkAdmOpenCreateFromSelection();
    });
 
    /* ──────────────────────────────
       MODALS: open / close
    ────────────────────────────── */
    window.openModal = window.openModal || function(m){
        document.body.style.overflow = 'hidden';
        document.body.style.paddingRight = (window.innerWidth - document.documentElement.clientWidth)+'px';
        m.classList.add('active');
    }
    window.closeModal = window.closeModal || function(m){
        m.classList.remove('active');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    }
    document.querySelectorAll('.bkadm-modal-close').forEach(btn => btn.addEventListener('click', function(){ closeModal(this.closest('.bkadm-modal')); }));
    document.querySelectorAll('.bkadm-modal-overlay').forEach(ov => ov.addEventListener('click', function(){ closeModal(this.closest('.bkadm-modal')); }));
 
    /* ──────────────────────────────
       EDIT MODAL — Step machine
    ────────────────────────────── */
    let emStep = 0;
    const EM_PANELS = 3;
 
    function setEmStep(n){
        emStep = n;
        document.querySelectorAll('.bkadm-step').forEach((btn,i) => btn.classList.toggle('bkadm-step--active', i===n));
        document.querySelectorAll('.bkadm-step-panel').forEach((panel,i) => panel.style.display = i===n ? 'block' : 'none');
        $('em-prev-step').style.display  = n > 0 ? 'inline-flex' : 'none';
        $('em-next-step').style.display  = n < EM_PANELS-1 ? 'inline-flex' : 'none';
        $('em-save-btn').style.display   = n === EM_PANELS-1 ? 'inline-flex' : 'none';
        if(n === EM_PANELS-1) buildConfirmSummary();
    }
 
    $('em-next-step').addEventListener('click', () => { if(emStep < EM_PANELS-1) setEmStep(emStep+1); });
    $('em-prev-step').addEventListener('click', () => { if(emStep > 0) setEmStep(emStep-1); });
 
    document.querySelectorAll('.bkadm-step').forEach((btn,i) => {
        btn.dataset.step = i;
        btn.addEventListener('click', () => setEmStep(i));
    });
 
    function buildConfirmSummary(){
        const fields = [
            ['Date',           $('em-booking-date').value],
            ['Start Time',     fmtTime12($('em-start-time').value)],
            ['End Time',       fmtTime12($('em-end-time').value)],
            ['Duration',       $('em-duration-text').textContent],
            ['Booking Status', $('em-booking-status').value],
            ['Payment Status', $('em-payment-status').value],
        ];
        $('em-confirm-summary').innerHTML = fields.map(([l,v]) =>
            `<div class="bkadm-info-block"><span class="bkadm-info-label">${l}</span><span class="bkadm-info-val">${v||'—'}</span></div>`
        ).join('');
    }
 
    /* Open edit modal */
    window.bkAdmOpenEdit = function(bookingId){
        const fd = new FormData();
        fd.append('action','bk_get_booking_details');
        fd.append('booking_id', bookingId);
        ajax(fd, json => {
            if(!json.success) return;
            const b = json.data;
 
            /* Hidden inputs */
            $('em-rand-id').value        = b.rand_id;
            $('em-service-id').value     = b.service_id;
            $('em-svc-duration').value   = b.service_duration;
            $('em-booking-date').value   = b.booking_date;
            $('em-start-time').value     = b.start_time;
            $('em-end-time').value       = b.end_time;
            $('em-booking-status').value = b.status;
            $('em-payment-status').value = b.payment_status;
            $('em-amount-val').value     = b.amount;
            $('em-tax-val').value        = b.tax;
            $('em-total-val').value      = b.total;
 
            /* Display */
            $('em-rand-id-display').textContent  = '#'+b.rand_id;
            $('em-service-name').textContent     = b.service_name;
            $('em-customer-name').textContent    = b.customer_name;
            $('em-customer-email').textContent   = b.customer_email;
            $('em-customer-phone').textContent   = b.customer_phone;
            $('em-unit-price').textContent       = b.unit_price;
            $('em-quantity').textContent         = b.quantity;
            $('em-subtotal').textContent         = b.amount;
            $('em-tax').textContent              = b.tax;
            $('em-total').textContent            = b.total;
            $('em-pay-method').textContent       = b.payment_method || 'N/A';
            $('em-notes').textContent            = b.customer_notes || 'No notes';
            $('em-created-at').textContent       = b.created_at;
 
            /* Status pills */
            const sp = $('em-status-pill');
            sp.textContent = b.status;
            sp.className   = 'bkadm-pill bkadm-pill--'+b.status;
            const pp = $('em-pay-pill');
            pp.textContent = b.payment_status;
            pp.className   = 'bkadm-pill bkadm-pill--pay '+(b.payment_status==='paid'?'paid':'unpaid');
 
            updateDurationPreview();
            setEmStep(0);
            openModal(editModal);
        });
    };
 
    function updateDurationPreview(){
        const st = $('em-start-time').value, et = $('em-end-time').value;
        const dtxt = $('em-duration-text');
        if(!st||!et){ dtxt.textContent='—'; return; }
        const diff = diffMin(st, et);
        if(diff <= 0){ dtxt.textContent='Invalid range'; return; }
        dtxt.textContent = durLabel(diff)+' ('+diff+' min)';
 
        const svcDur  = +$('em-svc-duration').value;
        if(!svcDur) return;
        const unitP   = parseFloat(($('em-unit-price').textContent||'0').replace(/[^0-9.]/g,''));
        const qty     = Math.round(diff/svcDur);
        const sub     = unitP * qty, tax = sub*(taxRate/100), total = sub+tax;
        $('em-quantity').textContent = qty;
        $('em-subtotal').textContent = sub.toFixed(2);
        $('em-tax').textContent      = tax.toFixed(2);
        $('em-total').textContent    = total.toFixed(2);
        $('em-amount-val').value     = sub.toFixed(2);
        $('em-tax-val').value        = tax.toFixed(2);
        $('em-total-val').value      = total.toFixed(2);
    }
 
    ['em-start-time','em-end-time'].forEach(id => $(id).addEventListener('change', updateDurationPreview));
 
    /* Edit form submit */
    $('bkadm-edit-form').addEventListener('submit', function(e){
        e.preventDefault();
        const btn = $('em-save-btn');
        btn.disabled = true; btn.innerHTML = '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> Saving...';
        const fd = new FormData();
        [['action','bk_update_admin_booking'],['booking_id','em-rand-id'],['booking_date','em-booking-date'],
         ['start_time','em-start-time'],['end_time','em-end-time'],['status','em-booking-status'],
         ['payment_status','em-payment-status'],['service_id','em-service-id'],
         ['amount','em-amount-val'],['tax','em-tax-val'],['total','em-total-val']
        ].forEach(([key, elId]) => fd.append(key, key==='action' ? elId : $(elId).value));
        fd.append('nonce', nonce);
        ajax(fd, json => {
            const cls = json.success ? 'bkadm-msg-success' : 'bkadm-msg-error';
            $('em-message').innerHTML = `<div class="${cls}">${json.data.message}</div>`;
            btn.disabled = false; btn.innerHTML = '<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> Save Changes';
            if(json.success) setTimeout(() => { closeModal(editModal); loadSlots(); loadMonthAvailability(); }, 1400);
        });
    });
 
    /* Delete */
    $('em-delete-btn').addEventListener('click', function(){
        if(!confirm('Delete this booking? This cannot be undone.')) return;
        const fd = new FormData();
        fd.append('action','bk_delete_admin_booking');
        fd.append('booking_id', $('em-rand-id').value);
        fd.append('nonce', nonce);
        ajax(fd, json => {
            if(json.success){ closeModal(editModal); loadSlots(); loadMonthAvailability(); }
            else alert(json.data.message);
        });
    });
 
    /* ──────────────────────────────
       CREATE MODAL
    ────────────────────────────── */
    function bkAdmOpenCreateFromSelection(){
        const slots = selectedAdminSlots.slice();
        if(!slots.length) return;
        $('bkadm-create-form').reset();
        $('cr-message').innerHTML = '';
        $('cr-selected-slots').value = JSON.stringify(slots);
        $('cr-service-id').value = slots[0].service_id;
        $('cr-date').value = slots[0].date;
        $('cr-start-time').value = slots[0].start_time;
        $('cr-quantity').value = slots.length;
        $('cr-selected-slots-list').innerHTML = slots.map(slot =>
            `<div class="bkadm-selected-slot"><div><strong>${slot.service_name}</strong><small>${fmtDate(new Date(slot.date + 'T12:00:00'))} · ${fmtTime12(slot.start_time)} - ${fmtTime12(slot.end_time)}</small></div><strong>${slot.price.toFixed(2)}</strong></div>`
        ).join('');
        updateCreateSummary();
        openModal(createModal);
    }
 
    function updateCrQtyBtns(){
        const q = +$('cr-quantity').value || 1;
        if($('cr-qty-dec')) $('cr-qty-dec').disabled = q <= 1;
        if($('cr-qty-inc')) $('cr-qty-inc').disabled = q >= 10;
    }
    if($('cr-qty-dec')) $('cr-qty-dec').addEventListener('click', () => { $('cr-quantity').value = Math.max(1, (+$('cr-quantity').value||1)-1); updateCrQtyBtns(); updateCreateSummary(); });
    if($('cr-qty-inc')) $('cr-qty-inc').addEventListener('click', () => { $('cr-quantity').value = Math.min(10,(+$('cr-quantity').value||1)+1); updateCrQtyBtns(); updateCreateSummary(); });
 
    function updateCreateSummary(){
        if(selectedAdminSlots.length){
            const slots = selectedAdminSlots.slice();
            const sub = slots.reduce((sum, slot) => sum + (parseFloat(slot.price) || 0), 0);
            const totalMin = slots.reduce((sum, slot) => sum + (parseInt(slot.duration, 10) || diffMin(slot.start_time, slot.end_time)), 0);
            const tax = sub*(taxRate/100), total = sub+tax;
            const last = slots[slots.length - 1];
            $('cr-end-time-disp').textContent = fmtTime12(last.end_time);
            $('cr-duration-disp').textContent = durLabel(totalMin);
            $('cr-subtotal-disp').textContent = sub.toFixed(2);
            $('cr-tax-disp').textContent = tax.toFixed(2);
            $('cr-total-disp').textContent = total.toFixed(2);
            $('cr-end-time').value = last.end_time;
            $('cr-amount').value = sub.toFixed(2);
            $('cr-tax').value = tax.toFixed(2);
            $('cr-total').value = total.toFixed(2);
            return;
        }
        const sel = $('cr-service-id'), st = $('cr-start-time').value, qty = +$('cr-quantity').value||1;
        if(!sel.value || !st) return;
        const opt = sel.options[sel.selectedIndex];
        const dur = +opt.dataset.duration, price = parseFloat(opt.dataset.price);
        const totalMin = dur * qty, endTime = addMin(st, totalMin);
        const sub = price*qty, tax = sub*(taxRate/100), total = sub+tax;
        $('cr-end-time-disp').textContent   = fmtTime12(endTime);
        $('cr-duration-disp').textContent   = durLabel(totalMin);
        $('cr-subtotal-disp').textContent   = sub.toFixed(2);
        $('cr-tax-disp').textContent        = tax.toFixed(2);
        $('cr-total-disp').textContent      = total.toFixed(2);
        $('cr-end-time').value              = endTime;
        $('cr-amount').value                = sub.toFixed(2);
        $('cr-tax').value                   = tax.toFixed(2);
        $('cr-total').value                 = total.toFixed(2);
    }
 
    ['cr-service-id','cr-start-time'].forEach(id => $(id).addEventListener('change', updateCreateSummary));
 
    $('bkadm-create-form').addEventListener('submit', function(e){
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true; btn.innerHTML = 'Creating...';
        const fd = new FormData(this);
        fd.append('action','bk_create_admin_booking');
        fd.append('nonce', nonce);
        ajax(fd, json => {
            const cls = json.success ? 'bkadm-msg-success' : 'bkadm-msg-error';
            $('cr-message').innerHTML = `<div class="${cls}">${json.data.message}</div>`;
            btn.disabled = false; btn.innerHTML = '<svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Create Booking';
            if(json.success) setTimeout(() => { closeModal(createModal); clearSelectedSlots(); loadSlots(); loadMonthAvailability(); }, 1400);
        });
    });
 
})();
</script>
<?php
    return ob_get_clean();
}
 


/* ---------- AJAX HANDLERS (PHP — unchanged logic, tidied) ---------- */

add_action('wp_ajax_bk_get_admin_slots', 'bntm_ajax_bk_get_admin_slots');
function bntm_ajax_bk_get_admin_slots() {
    if (!is_user_logged_in()) wp_send_json_error(['message'=>'Unauthorized']);
    global $wpdb;
    $date = sanitize_text_field($_POST['date'] ?? '');
    $service_filter = intval($_POST['service_filter'] ?? 0);
    $business_id = bk_get_request_business_id();
    if (empty($date)) wp_send_json_error(['message'=>'Missing date']);

    $st = $wpdb->prefix; 
    $base = "SELECT id, name, duration, price FROM {$st}bk_services WHERE business_id = %d AND status = 'active'";
    $services = $service_filter
        ? $wpdb->get_results($wpdb->prepare($base." AND id = %d ORDER BY name ASC", $business_id, $service_filter))
        : $wpdb->get_results($wpdb->prepare($base." ORDER BY name ASC", $business_id));

    if (empty($services)) wp_send_json_error(['message'=>'No services found']);

    $op = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$st}bk_operating_hours WHERE business_id=%d AND day_of_week=%d LIMIT 1",
        $business_id, date('w', strtotime($date))
    ));
    if (!$op || !$op->is_open) wp_send_json_success(['slots'=>[],'services'=>$services,'message'=>'Business closed']);

    $bookings = $wpdb->get_results($wpdb->prepare(
        "SELECT id,rand_id,service_id,start_time,end_time,customer_name,customer_email,status,payment_status
         FROM {$st}bk_bookings WHERE business_id=%d AND booking_date=%s
         AND status IN ('pending','confirmed','completed')
         AND payment_status NOT IN ('pending','waiting_payment')",
        $business_id, $date
    ));

    $interval = intval(bntm_get_setting('bk_slot_interval','30')) * 60;
    $start = strtotime($date.' '.$op->start_time);
    $end   = strtotime($date.' '.$op->end_time);
    $slots = [];
    for ($t=$start; $t<$end; $t+=$interval) $slots[] = ['time'=>date('H:i',$t)];

    $slots_by_service = [];
    foreach ($slots as $slot) {
        $slot_start = strtotime($date.' '.$slot['time']);
        foreach ($services as $svc) {
            $slot_end = $slot_start + intval($svc->duration)*60;
            $key = $svc->id.'_'.$slot['time'];
            $available = true; $booking_info = $bk_status = $bk_pay = null;
            foreach ($bookings as $bk) {
                if ($bk->service_id != $svc->id) continue;
                $bk_s = strtotime($date.' '.$bk->start_time);
                $bk_e = strtotime($date.' '.$bk->end_time);
                if ($slot_start < $bk_e && $slot_end > $bk_s) {
                    $available = false; $bk_status = $bk->status; $bk_pay = $bk->payment_status;
                    $booking_info = ['id'=>$bk->id,'rand_id'=>$bk->rand_id,'customer_name'=>$bk->customer_name,'customer_email'=>$bk->customer_email,'status'=>$bk->status,'payment_status'=>$bk->payment_status];
                    break;
                }
            }
            $slots_by_service[$key] = ['available'=>$available,'end_time'=>date('H:i',$slot_end),'service_id'=>$svc->id,'booking'=>$booking_info,'status'=>$bk_status,'payment_status'=>$bk_pay];
        }
    }
    wp_send_json_success(['slots'=>$slots,'services'=>$services,'slots_by_service'=>$slots_by_service]);
}

add_action('wp_ajax_bk_get_month_availability',        'bntm_ajax_bk_get_month_availability');
add_action('wp_ajax_nopriv_bk_get_month_availability', 'bntm_ajax_bk_get_month_availability');
function bntm_ajax_bk_get_month_availability() {
    global $wpdb;
    $start_date  = sanitize_text_field($_POST['start_date'] ?? '');
    $end_date    = sanitize_text_field($_POST['end_date']   ?? '');
    $business_id = bk_get_request_business_id();
    if (!$start_date || !$end_date) wp_send_json_error(['message'=>'Missing dates']);

    $st = $wpdb->prefix;
    $services = $wpdb->get_results($wpdb->prepare(
        "SELECT id,duration FROM {$st}bk_services WHERE business_id=%d AND status='active'", $business_id
    ));
    if (empty($services)) wp_send_json_success([]);

    $svc_count   = count($services);
    $avg_dur     = array_sum(array_column($services,'duration')) / $svc_count;
    $holidays    = array_filter(array_map('trim', explode("\n", bk_get_business_setting($business_id,'bk_holidays',''))));
    $result      = [];

    for ($cur=strtotime($start_date), $end=strtotime($end_date); $cur<=$end; $cur=strtotime('+1 day',$cur)) {
        $ds  = date('Y-m-d',$cur);
        $dow = date('w',$cur);
        if (in_array($ds,$holidays,true)) {
            $result[$ds] = ['total_slots'=>0,'booked_slots'=>0,'percentage'=>0,'has_operating_hours'=>false,'is_holiday'=>true];
            continue;
        }
        $op = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$st}bk_operating_hours WHERE business_id=%d AND day_of_week=%d LIMIT 1", $business_id, $dow
        ));
        if (!$op || !$op->is_open) {
            $result[$ds] = ['total_slots'=>0,'booked_slots'=>0,'percentage'=>0,'has_operating_hours'=>false];
            continue;
        }
        $op_min = (strtotime('1970-01-01 '.$op->end_time) - strtotime('1970-01-01 '.$op->start_time)) / 60;
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT start_time,end_time FROM {$st}bk_bookings WHERE business_id=%d AND booking_date=%s
             AND payment_status IN ('pending','paid','unpaid','verified')", $business_id, $ds
        ));
        $booked_min = array_sum(array_map(fn($b) => (strtotime('1970-01-01 '.$b->end_time)-strtotime('1970-01-01 '.$b->start_time))/60, $bookings));
        $total_avail = $op_min * $svc_count;
        $pct = $total_avail > 0 ? ($booked_min/$total_avail)*100 : 0;
        $result[$ds] = [
            'total_slots'         => floor($op_min / $avg_dur),
            'booked_slots'        => floor($booked_min / $avg_dur),
            'percentage'          => round($pct,2),
            'has_operating_hours' => true,
        ];
    }
    wp_send_json_success($result);
}

add_action('wp_ajax_bk_get_booking_details', 'bntm_ajax_bk_get_booking_details');
function bntm_ajax_bk_get_booking_details() {
    if (!is_user_logged_in()) wp_send_json_error(['message'=>'Unauthorized']);
    global $wpdb;
    $booking_id  = sanitize_text_field($_POST['booking_id'] ?? '');
    $business_id = bntm_get_current_business_id();
    $st = $wpdb->prefix;

    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT b.*,s.name as service_name,s.duration as service_duration,s.price as service_price
         FROM {$st}bk_bookings b LEFT JOIN {$st}bk_services s ON b.service_id=s.id
         WHERE b.rand_id=%s AND b.business_id=%d", $booking_id, $business_id
    ));
    if (!$booking) wp_send_json_error(['message'=>'Booking not found']);

    $qty = 1;
    if (preg_match('/(\d+)\s+slot/', $booking->customer_notes, $m)) $qty = intval($m[1]);
    $unit_price = $qty > 1 ? $booking->amount / $qty : $booking->amount;

    wp_send_json_success([
        'id'               => $booking->id,
        'rand_id'          => $booking->rand_id,
        'service_id'       => $booking->service_id,
        'service_name'     => $booking->service_name,
        'service_duration' => $booking->service_duration,
        'customer_name'    => $booking->customer_name,
        'customer_email'   => $booking->customer_email,
        'customer_phone'   => $booking->customer_phone,
        'booking_date'     => $booking->booking_date,
        'start_time'       => substr($booking->start_time,0,5),
        'end_time'         => substr($booking->end_time,0,5),
        'status'           => $booking->status,
        'payment_status'   => $booking->payment_status,
        'customer_notes'   => $booking->customer_notes,
        'amount'           => number_format($booking->amount,2),
        'tax'              => number_format($booking->tax,2),
        'total'            => number_format($booking->total,2),
        'unit_price'       => number_format($unit_price,2),
        'quantity'         => $qty,
        'payment_method'   => $booking->payment_method,
        'created_at'       => date('F j, Y g:i A', strtotime($booking->created_at)),
    ]);
}

add_action('wp_ajax_bk_update_admin_booking', 'bntm_ajax_bk_update_admin_booking');
function bntm_ajax_bk_update_admin_booking() {
    check_ajax_referer('bk_nonce','nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message'=>'Unauthorized']);
    global $wpdb;

    $booking_id     = sanitize_text_field($_POST['booking_id']);
    $booking_date   = sanitize_text_field($_POST['booking_date']);
    $start_time     = sanitize_text_field($_POST['start_time']);
    $end_time       = sanitize_text_field($_POST['end_time']);
    $status         = sanitize_text_field($_POST['status']);
    $payment_status = sanitize_text_field($_POST['payment_status']);
    $total          = floatval($_POST['total']);
    $service_id     = intval($_POST['service_id']);
    $business_id    = bntm_get_current_business_id();
    $bt = $wpdb->prefix.'bk_bookings';

    $cur = $wpdb->get_row($wpdb->prepare("SELECT * FROM $bt WHERE rand_id=%s AND business_id=%d", $booking_id, $business_id));
    if (!$cur) wp_send_json_error(['message'=>'Booking not found']);

    $changed = ($cur->booking_date!=$booking_date || substr($cur->start_time,0,5)!=$start_time || substr($cur->end_time,0,5)!=$end_time);
    if ($changed) {
        $conflict = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $bt WHERE booking_date=%s AND business_id=%d AND service_id=%d AND id!=%d
             AND status IN ('pending','confirmed','completed') AND payment_status NOT IN ('pending','waiting_payment')
             AND ((start_time<%s AND end_time>%s) OR (start_time>=%s AND start_time<%s))",
            $booking_date,$business_id,$service_id,$cur->id,$end_time,$start_time,$start_time,$end_time
        ));
        if ($conflict) wp_send_json_error(['message'=>'Time slot conflict with another booking']);
    }

    $tax_rate = floatval(bntm_get_setting('bk_tax_rate','0'));
    $amount   = $total / (1 + $tax_rate/100);
    $tax      = $total - $amount;

    $res = $wpdb->update($bt,
        ['booking_date'=>$booking_date,'start_time'=>$start_time.':00','end_time'=>$end_time.':00','status'=>$status,'payment_status'=>$payment_status,'amount'=>$amount,'tax'=>$tax,'total'=>$total],
        ['rand_id'=>$booking_id],
        ['%s','%s','%s','%s','%s','%f','%f','%f'],['%s']
    );
    if ($res === false) wp_send_json_error(['message'=>'Failed to update booking']);

    if ($changed) {
        wp_mail($cur->customer_email,'Booking Updated',
            "Hello {$cur->customer_name},\n\nYour booking has been updated.\n\n".
            "New Date: ".date('F j, Y',strtotime($booking_date))."\n".
            "New Time: ".date('g:i A',strtotime($start_time))." - ".date('g:i A',strtotime($end_time))."\n".
            "Status: ".ucfirst($status)."\nPayment Status: ".ucfirst($payment_status)."\n".
            "Total Amount: ".bk_format_price($total)."\n\nBooking ID: ".$booking_id
        );
    }
    wp_send_json_success(['message'=>'Booking updated successfully']);
}

add_action('wp_ajax_bk_create_admin_booking', 'bntm_ajax_bk_create_admin_booking');
function bntm_ajax_bk_create_admin_booking() {
    check_ajax_referer('bk_nonce','nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message'=>'Unauthorized']);
    global $wpdb;

    $service_id     = intval($_POST['service_id']);
    $booking_date   = sanitize_text_field($_POST['booking_date']);
    $start_time     = sanitize_text_field($_POST['start_time']);
    $end_time       = sanitize_text_field($_POST['end_time']);
    $qty            = min(10, max(1, intval($_POST['quantity'] ?? 1)));
    $customer_name  = sanitize_text_field($_POST['customer_name']);
    $customer_email = sanitize_email($_POST['customer_email']);
    $customer_phone = sanitize_text_field($_POST['customer_phone']);
    $pay_status     = sanitize_text_field($_POST['payment_status']);
    $pay_method     = sanitize_text_field($_POST['payment_method']);
    $amount         = floatval($_POST['amount']);
    $tax            = floatval($_POST['tax']);
    $total          = floatval($_POST['total']);
    $business_id    = bntm_get_current_business_id();
    $st = $wpdb->prefix;
    $selected_slots = json_decode(wp_unslash($_POST['selected_slots'] ?? '[]'), true);

    if (is_array($selected_slots) && !empty($selected_slots)) {
        $clean_slots = [];
        $server_amount = 0.0;
        $server_tax = 0.0;
        $server_total = 0.0;
        $total_dur = 0;

        if (empty($customer_name) || empty($customer_email) || empty($customer_phone) || empty($pay_method)) {
            wp_send_json_error(['message'=>'Please complete the customer and payment details.']);
        }

        foreach ($selected_slots as $slot) {
            if (!is_array($slot)) {
                continue;
            }
            $slot_service_id = intval($slot['service_id'] ?? 0);
            $slot_date = sanitize_text_field($slot['date'] ?? '');
            $slot_start = sanitize_text_field($slot['start_time'] ?? '');
            if (!$slot_service_id || !$slot_date || !$slot_start) {
                wp_send_json_error(['message'=>'Invalid selected slot. Please reload the calendar and try again.']);
            }

            $svc = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$st}bk_services WHERE id=%d AND business_id=%d AND status='active'",
                $slot_service_id,
                $business_id
            ));
            if (!$svc) {
                wp_send_json_error(['message'=>'One selected service was not found for this business.']);
            }

            $slot_start_ts = strtotime($slot_date.' '.$slot_start);
            $slot_end = date('H:i', $slot_start_ts + (intval($svc->duration) * 60));
            $conflict = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$st}bk_bookings WHERE booking_date=%s AND business_id=%d AND service_id=%d
                 AND status IN ('pending','confirmed','completed') AND payment_status NOT IN ('pending','waiting_payment')
                 AND ((start_time<%s AND end_time>%s) OR (start_time>=%s AND start_time<%s))",
                $slot_date,
                $business_id,
                $slot_service_id,
                $slot_end,
                $slot_start,
                $slot_start,
                $slot_end
            ));
            if ($conflict) {
                wp_send_json_error(['message'=>$svc->name.' at '.$slot_start.' is already booked.']);
            }

            $line_amount = floatval($svc->price);
            $line_tax = $line_amount * (floatval(bntm_get_setting('bk_tax_rate','0')) / 100);
            $line_total = $line_amount + $line_tax;
            $server_amount += $line_amount;
            $server_tax += $line_tax;
            $server_total += $line_total;
            $total_dur += intval($svc->duration);
            $clean_slots[] = [
                'service' => $svc,
                'date' => $slot_date,
                'start' => $slot_start,
                'end' => $slot_end,
                'amount' => $line_amount,
                'tax' => $line_tax,
                'total' => $line_total,
            ];
        }

        if (empty($clean_slots)) {
            wp_send_json_error(['message'=>'Please select at least one available slot.']);
        }

        $bk_status = in_array($pay_status, ['paid','verified'], true) ? 'confirmed' : 'pending';
        $group_rand_id = count($clean_slots) > 1 ? 'bkg' . bntm_rand_id(12) : '';
        $created_refs = [];

        foreach ($clean_slots as $slot) {
            $rand_id = bntm_rand_id(15);
            $notes = count($clean_slots) . " selected slot(s), {$total_dur} min total";
            if ($group_rand_id) {
                $notes = "Group Reference: {$group_rand_id}\n" . $notes;
            }
            $res = $wpdb->insert("{$st}bk_bookings", [
                'rand_id'=>$rand_id,'group_rand_id'=>$group_rand_id,'business_id'=>$business_id,'service_id'=>$slot['service']->id,'customer_id'=>0,
                'booking_date'=>$slot['date'],'start_time'=>$slot['start'].':00','end_time'=>$slot['end'].':00',
                'customer_name'=>$customer_name,'customer_email'=>$customer_email,'customer_phone'=>$customer_phone,
                'customer_notes'=>$notes,
                'status'=>$bk_status,'payment_status'=>$pay_status,'amount'=>$slot['amount'],'tax'=>$slot['tax'],'total'=>$slot['total'],
                'payment_method'=>$pay_method,'created_at'=>current_time('mysql'),
            ], ['%s','%s','%d','%d','%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%f','%f','%f','%s','%s']);
            if (!$res) {
                foreach ($created_refs as $created_ref) {
                    $wpdb->delete("{$st}bk_bookings", ['rand_id' => $created_ref], ['%s']);
                }
                wp_send_json_error(['message'=>'Failed to create booking. Database error.']);
            }
            $created_refs[] = $rand_id;
        }

        wp_mail($customer_email,'Booking Confirmation',
            "Hello {$customer_name},\n\nA booking has been created for you.\n\n".
            "Selected Slots: ".count($clean_slots)."\nDuration: {$total_dur} minutes\n".
            "Total: ".bk_format_price($server_total)."\nPayment Status: ".ucfirst($pay_status)."\n\nBooking ID: ".($group_rand_id ?: $created_refs[0])
        );
        wp_send_json_success(['message'=>'Booking created successfully']);
    }

    $svc = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$st}bk_services WHERE id=%d AND business_id=%d AND status='active'", $service_id, $business_id
    ));
    if (!$svc) wp_send_json_error(['message'=>'Service not found']);

    $calc_end_ts = strtotime($booking_date.' '.$start_time) + ($svc->duration * $qty * 60);
    $calc_end    = date('H:i',$calc_end_ts);
    if (abs($calc_end_ts - strtotime($booking_date.' '.$end_time)) > 60) wp_send_json_error(['message'=>'Time calculation mismatch. Please try again.']);

    $conflict = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$st}bk_bookings WHERE booking_date=%s AND business_id=%d AND service_id=%d
         AND status IN ('pending','confirmed','completed') AND payment_status NOT IN ('pending','waiting_payment')
         AND ((start_time<%s AND end_time>%s) OR (start_time>=%s AND start_time<%s))",
        $booking_date,$business_id,$service_id,$calc_end,$start_time,$start_time,$calc_end
    ));
    if ($conflict) wp_send_json_error(['message'=>'One or more time slots in your selected range are already booked']);

    $bk_status   = in_array($pay_status,['paid','verified']) ? 'confirmed' : 'pending';
    $total_dur   = $svc->duration * $qty;
    $rand_id     = bntm_rand_id(15);

    $res = $wpdb->insert("{$st}bk_bookings", [
        'rand_id'=>$rand_id,'business_id'=>$business_id,'service_id'=>$service_id,'customer_id'=>0,
        'booking_date'=>$booking_date,'start_time'=>$start_time.':00','end_time'=>$calc_end.':00',
        'customer_name'=>$customer_name,'customer_email'=>$customer_email,'customer_phone'=>$customer_phone,
        'customer_notes'=>"{$qty} slot(s) × {$svc->duration} min = {$total_dur} min total",
        'status'=>$bk_status,'payment_status'=>$pay_status,'amount'=>$amount,'tax'=>$tax,'total'=>$total,
        'payment_method'=>$pay_method,'created_at'=>current_time('mysql'),
    ], ['%s','%d','%d','%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%f','%f','%f','%s','%s']);

    if (!$res) wp_send_json_error(['message'=>'Failed to create booking. Database error.']);

    wp_mail($customer_email,'Booking Confirmation',
        "Hello {$customer_name},\n\nA booking has been created for you.\n\n".
        "Service: {$svc->name}\nQuantity: {$qty} slot(s)\nDuration: {$total_dur} minutes\n".
        "Date: ".date('F j, Y',strtotime($booking_date))."\n".
        "Time: ".date('g:i A',strtotime($start_time))." - ".date('g:i A',strtotime($calc_end))."\n".
        "Total: ".bk_format_price($total)."\nPayment Status: ".ucfirst($pay_status)."\n\nBooking ID: ".$rand_id
    );
    wp_send_json_success(['message'=>'Booking created successfully']);
}
   /* ---------- AJAX HANDLERS ---------- */
   
   /**
    * Get available time slots for a date and service
    */
   function bntm_ajax_bk_get_available_slots() {
       global $wpdb;
       
       $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
       $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
       $business_id = bk_get_request_business_id();
       
       if (empty($date) || empty($service_id)) {
           wp_send_json_error(['message' => 'Missing parameters']);
       }
       
       // Validate date format
       $dateObj = DateTime::createFromFormat('Y-m-d', $date);
       if (!$dateObj) {
           wp_send_json_error(['message' => 'Invalid date format']);
       }
       
       // Get service
       $services_table = $wpdb->prefix . 'bk_services';
       $service = $wpdb->get_row($wpdb->prepare(
           "SELECT * FROM $services_table WHERE id = %d AND business_id = %d AND status = 'active'",
           $service_id,
           $business_id
       ));
       
       if (!$service) {
           wp_send_json_error(['message' => 'Service not found']);
       }
       
       // Get operating hours for the day
       $hours_table = $wpdb->prefix . 'bk_operating_hours';
       $dayOfWeek = date('w', strtotime($date)); // 0=Sunday, 6=Saturday
       
       $operating_hour = $wpdb->get_row($wpdb->prepare(
           "SELECT * FROM $hours_table WHERE business_id = %d AND day_of_week = %d",
           $business_id,
           $dayOfWeek
       ));
       
       if (!$operating_hour || !$operating_hour->is_open) {
           wp_send_json_success(['slots' => [], 'message' => 'Business closed on this day']);
       }
       
       // Get all bookings for this date
       $bookings_table = $wpdb->prefix . 'bk_bookings';
       $bookings = $wpdb->get_results($wpdb->prepare(
           "SELECT start_time, end_time FROM $bookings_table 
            WHERE business_id = %d AND booking_date = %s AND status IN ('pending', 'confirmed') AND service_id = %d",
           $business_id, $date, $service_id
       ));
       
       // Build booked times array
       $booked_times = [];
       foreach ($bookings as $booking) {
           $booked_times[] = [
               'start' => strtotime($booking->start_time),
               'end' => strtotime($booking->end_time)
           ];
       }
       
       // Generate available slots
       $slot_interval = intval(bntm_get_setting('bk_slot_interval', '30'));
       $start = strtotime($operating_hour->start_time);
       $end = strtotime($operating_hour->end_time);
       $service_duration = $service->duration * 60; // Convert to seconds
       
       $slots = [];
       $current = $start;
       
       while ($current + $service_duration <= $end) {
           $slot_end = $current + $service_duration;
           $is_available = true;
           
           // Check if slot overlaps with any booking
           foreach ($booked_times as $booked) {
               if (!($slot_end <= $booked['start'] || $current >= $booked['end'])) {
                   $is_available = false;
                   break;
               }
           }
           
           if ($is_available) {
               $slots[] = [
                   'start_time' => date('H:i', $current),
                   'end_time' => date('H:i', $slot_end)
               ];
           }
           
           $current += $slot_interval * 60; // Move to next slot
       }
       
       wp_send_json_success(['slots' => $slots]);
   }
   
   /**
    * Book an appointment
    */
   function bntm_ajax_bk_book_cart() {
       check_ajax_referer('bk_calendar_nonce', 'nonce');
   
       global $wpdb;
   
       $business_id = bk_get_request_business_id();
       $customer_name = sanitize_text_field($_POST['customer_name'] ?? '');
       $customer_email = sanitize_email($_POST['customer_email'] ?? '');
       $customer_phone = sanitize_text_field($_POST['customer_phone'] ?? '');
       $customer_notes = sanitize_textarea_field($_POST['customer_notes'] ?? '');
       $cart = json_decode(wp_unslash($_POST['cart'] ?? '[]'), true);
   
       if (!$business_id) {
           wp_send_json_error(['message' => 'Missing business']);
       }
       if (empty($customer_name) || empty($customer_email) || empty($customer_phone)) {
           wp_send_json_error(['message' => 'Please fill in all required fields']);
       }
       if (!is_array($cart) || empty($cart)) {
           wp_send_json_error(['message' => 'Your cart is empty']);
       }
   
       $op_payment_method = bk_get_paymaya_method();
       if (!$op_payment_method) {
           wp_send_json_error(['message' => 'Maya checkout is not configured.']);
       }
   
       $services_table = $wpdb->prefix . 'bk_services';
       $bookings_table = $wpdb->prefix . 'bk_bookings';
       $tax_rate = floatval(bntm_get_setting('bk_tax_rate', '0'));
       $customer_id = is_user_logged_in() ? get_current_user_id() : 0;
       $group_rand_id = 'bkg' . bntm_rand_id(12);
       $booking_ids = [];
       $booking_rand_ids = [];
       $items_meta = [];
       $subtotal = 0.0;
       $total_duration = 0;
   
       foreach ($cart as $cart_item) {
           $service_id = intval($cart_item['service'] ?? 0);
           $booking_date = sanitize_text_field($cart_item['date'] ?? '');
           $start_time = sanitize_text_field($cart_item['time'] ?? '');
   
           $service = $wpdb->get_row($wpdb->prepare(
               "SELECT * FROM $services_table WHERE id = %d AND business_id = %d AND status = 'active'",
               $service_id,
               $business_id
           ));
           if (!$service || !$booking_date || !$start_time) {
               wp_send_json_error(['message' => 'Invalid booking item in cart.']);
           }
   
           $date_obj = DateTime::createFromFormat('Y-m-d', $booking_date);
           if (!$date_obj) {
               wp_send_json_error(['message' => 'Invalid booking date.']);
           }
   
           $start_timestamp = strtotime($booking_date . ' ' . $start_time);
           $end_timestamp = $start_timestamp + (intval($service->duration) * 60);
           $end_time = date('H:i:s', $end_timestamp);
   
           $conflict = $wpdb->get_var($wpdb->prepare(
               "SELECT id FROM $bookings_table
                WHERE booking_date = %s
                AND business_id = %d
                AND service_id = %d
                AND status IN ('pending', 'confirmed', 'completed')
                AND payment_status NOT IN ('pending', 'waiting_payment')
                AND start_time < %s
                AND end_time > %s
                LIMIT 1",
               $booking_date,
               $business_id,
               $service_id,
               $end_time,
               $start_time
           ));
           if ($conflict) {
               wp_send_json_error(['message' => $service->name . ' at ' . $start_time . ' is no longer available.']);
           }
   
           $line_amount = floatval($service->price);
           $line_tax = $line_amount * ($tax_rate / 100);
           $line_total = $line_amount + $line_tax;
           $booking_rand_id = bntm_rand_id(15);
           $notes = "Group Reference: {$group_rand_id}\n1 slot x {$service->duration} min";
           if ($customer_notes) {
               $notes .= "\n\nCustomer Notes:\n" . $customer_notes;
           }
   
           $inserted = $wpdb->insert($bookings_table, [
               'rand_id' => $booking_rand_id,
               'group_rand_id' => $group_rand_id,
               'business_id' => $business_id,
               'service_id' => $service_id,
               'customer_id' => $customer_id,
               'booking_date' => $booking_date,
               'start_time' => $start_time,
               'end_time' => $end_time,
               'customer_name' => $customer_name,
               'customer_email' => $customer_email,
               'customer_phone' => $customer_phone,
               'customer_notes' => $notes,
               'status' => 'pending',
               'payment_status' => 'waiting_payment',
               'amount' => $line_amount,
               'tax' => $line_tax,
               'total' => $line_total,
               'payment_method' => 'PayMaya',
               'created_at' => current_time('mysql')
           ], [
               '%s','%s','%d','%d','%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%f','%f','%f','%s','%s'
           ]);
   
           if (!$inserted) {
               foreach ($booking_ids as $booking_id) {
                   $wpdb->delete($bookings_table, ['id' => $booking_id], ['%d']);
               }
               wp_send_json_error(['message' => 'Failed to create booking group.']);
           }
   
           $booking_ids[] = $wpdb->insert_id;
           $booking_rand_ids[] = $booking_rand_id;
           $subtotal += $line_amount;
           $total_duration += intval($service->duration);
           $items_meta[] = [
               'booking_id' => $booking_rand_id,
               'service_name' => $service->name,
               'date' => $booking_date,
               'start_time' => $start_time,
               'end_time' => $end_time,
               'amount' => $line_total,
           ];
       }
   
       $tax = $subtotal * ($tax_rate / 100);
       $total = $subtotal + $tax;
       $payments_table = $wpdb->prefix . 'op_payments';
       $payment_rand_id = bntm_rand_id();
       $wpdb->insert($payments_table, [
           'rand_id' => $payment_rand_id,
           'business_id' => $business_id,
           'invoice_id' => 1,
           'amount' => $total,
           'payment_method' => 'online',
           'payment_gateway' => 'paymaya',
           'status' => 'pending-payment',
           'attempted_at' => current_time('mysql')
       ], ['%s','%d','%d','%f','%s','%s','%s','%s']);
   
       $metadata = [
           'group_rand_id' => $group_rand_id,
           'booking_rand_ids' => $booking_rand_ids,
           'payment_rand_id' => $payment_rand_id,
           'payment_gateway' => 'paymaya',
           'business_id' => $business_id,
           'customer_name' => $customer_name,
           'customer_email' => $customer_email,
           'customer_phone' => $customer_phone,
           'items' => $items_meta,
           'amount' => $subtotal,
           'tax' => $tax,
           'total' => $total,
           'total_duration' => $total_duration,
           'is_bk_booking' => true,
           'is_group_booking' => true,
       ];
       $metadata['return_token'] = bk_generate_return_token();
       update_option('bk_booking_' . $group_rand_id . '_data', $metadata);
   
       $mock_invoice = (object) [
           'id' => $booking_ids[0],
           'rand_id' => $group_rand_id,
           'business_id' => $business_id,
           'total' => $total,
           'currency' => bntm_get_setting('bk_currency', 'PHP'),
           'customer_email' => $customer_email,
           'customer_name' => $customer_name,
           'items' => $items_meta,
           'return_token' => $metadata['return_token'],
       ];
   
       $config = json_decode($op_payment_method->config, true);
       $payment_result = op_bk_process_paymaya_payment($mock_invoice, $op_payment_method, $config, $total);
       if (empty($payment_result['success'])) {
           foreach ($booking_ids as $booking_id) {
               $wpdb->delete($bookings_table, ['id' => $booking_id], ['%d']);
           }
           $wpdb->delete($payments_table, ['rand_id' => $payment_rand_id], ['%s']);
           delete_option('bk_booking_' . $group_rand_id . '_data');
           wp_send_json_error(['message' => $payment_result['message'] ?? 'Payment processing failed']);
       }
   
       if (!empty($payment_result['transaction_id'])) {
           $wpdb->update($payments_table, ['transaction_id' => $payment_result['transaction_id']], ['rand_id' => $payment_rand_id], ['%s'], ['%s']);
           $wpdb->query($wpdb->prepare(
               "UPDATE $bookings_table SET transaction_id = %s WHERE group_rand_id = %s",
               $payment_result['transaction_id'],
               $group_rand_id
           ));
           $metadata['transaction_id'] = $payment_result['transaction_id'];
           update_option('bk_booking_' . $group_rand_id . '_data', $metadata);
       }
   
       wp_send_json_success([
           'message' => 'Booking group created. Redirecting to PayMaya...',
           'redirect_url' => $payment_result['redirect_url'] ?? '',
           'booking_id' => $group_rand_id,
       ]);
   }
   
   /* ---------- MODIFIED BK AJAX BOOK APPOINTMENT WITH OP INTEGRATION ---------- */
   function bntm_ajax_bk_book_appointment() {
       check_ajax_referer('bk_calendar_nonce', 'nonce');
       
       global $wpdb;
       
       $service_id = intval($_POST['service_id'] ?? 0);
       $business_id = bk_get_request_business_id();
       $booking_date = sanitize_text_field($_POST['booking_date'] ?? '');
       $start_time = sanitize_text_field($_POST['start_time'] ?? '');
       $end_time = sanitize_text_field($_POST['end_time'] ?? '');
       $quantity = intval($_POST['quantity'] ?? 1); // NEW: Get quantity
       $customer_name = sanitize_text_field($_POST['customer_name'] ?? '');
       $customer_email = sanitize_email($_POST['customer_email'] ?? '');
       $customer_phone = sanitize_text_field($_POST['customer_phone'] ?? '');
       $customer_notes = sanitize_textarea_field($_POST['customer_notes'] ?? '');
       $amount = floatval($_POST['amount'] ?? 0);
       
       // NEW: Validate quantity
       if ($quantity < 1) $quantity = 1;
       if ($quantity > 10) $quantity = 10;
       
       if (!$business_id) {
           wp_send_json_error(['message' => 'Missing business']);
       }

       $op_method_id = intval($_POST['op_method_id'] ?? 0);
       
       // Validation
       if (empty($service_id) || empty($booking_date) || empty($start_time) || empty($customer_name) || empty($customer_email)) {
           wp_send_json_error(['message' => 'Please fill in all required fields']);
       }
       
       // Get service
       $services_table = $wpdb->prefix . 'bk_services';
       $service = $wpdb->get_row($wpdb->prepare(
           "SELECT * FROM $services_table WHERE id = %d AND business_id = %d AND status = 'active'",
           $service_id,
           $business_id
       ));
       
       if (!$service) {
           wp_send_json_error(['message' => 'Service not found']);
       }
       
       // NEW: Recalculate and verify end time based on quantity
       $start_timestamp = strtotime($booking_date . ' ' . $start_time);
       $total_duration = $service->duration * $quantity;
       $end_timestamp = $start_timestamp + ($total_duration * 60);
       $calculated_end_time = date('H:i:s', $end_timestamp);
       
       // NEW: Verify the end time matches what was sent (allow 1 minute tolerance)
       $sent_end_timestamp = strtotime($booking_date . ' ' . $end_time);
       if (abs($end_timestamp - $sent_end_timestamp) > 60) {
           wp_send_json_error(['message' => 'Time calculation mismatch. Please try again.']);
       }
       
       // Check slot availability again (security) - UPDATED: Check entire duration range
       $bookings_table = $wpdb->prefix . 'bk_bookings';
       $conflict = $wpdb->get_row($wpdb->prepare(
           "SELECT id FROM $bookings_table 
            WHERE booking_date = %s 
            AND business_id = %d
            AND service_id = %d
            AND status IN ('pending', 'confirmed', 'completed')
            AND payment_status NOT IN ('pending', 'waiting_payment')
            AND (
               (start_time < %s AND end_time > %s)
               OR (start_time >= %s AND start_time < %s)
            )",
           $booking_date, $business_id, $service_id, $calculated_end_time, $start_time, $start_time, $calculated_end_time
       ));
       
       if ($conflict) {
           wp_send_json_error(['message' => 'One or more time slots in your selected range are no longer available. Please select a different time.']);
       }
       
       $op_payment_method = bk_get_paymaya_method();
       
       if (!$op_payment_method) {
           wp_send_json_error(['message' => 'Maya checkout is not configured.']);
       }
       
       $payment_method_name = $op_payment_method->name ?: 'Maya Checkout';
       $payment_gateway = 'paymaya';
       
       // Calculate totals - UPDATED: Verify amount matches quantity calculation
       $unit_price = floatval($service->price);
       $subtotal = $unit_price * $quantity;
       $tax_rate = floatval(bntm_get_setting('bk_tax_rate', '0'));
       $tax = $subtotal * ($tax_rate / 100);
       $total = $subtotal + $tax;
       
       // NEW: Verify amount matches calculated total
       if (abs($total - $amount) > 0.01) {
           wp_send_json_error(['message' => 'Price calculation mismatch. Please try again.']);
       }
       
       $payment_status = 'waiting_payment';
       $booking_status = 'pending';
       
       // NEW: Prepare customer notes with quantity info
       $notes_with_quantity = "{$quantity} slot(s) × {$service->duration} min = {$total_duration} min total";
       if (!empty($customer_notes)) {
           $notes_with_quantity .= "\n\nCustomer Notes:\n" . $customer_notes;
       }
       
       // Create booking - UPDATED: Use calculated_end_time and notes_with_quantity
       $booking_rand_id = bntm_rand_id(15);
       $customer_id = is_user_logged_in() ? get_current_user_id() : 0;
       
       $booking_inserted = $wpdb->insert($bookings_table, [
           'rand_id' => $booking_rand_id,
           'business_id' => $business_id,
           'service_id' => $service_id,
           'customer_id' => $customer_id,
           'booking_date' => $booking_date,
           'start_time' => $start_time,
           'end_time' => $calculated_end_time, // UPDATED: Use calculated end time
           'customer_name' => $customer_name,
           'customer_email' => $customer_email,
           'customer_phone' => $customer_phone,
           'customer_notes' => $notes_with_quantity, // UPDATED: Include quantity info
           'status' => $booking_status,
           'payment_status' => $payment_status,
           'amount' => $subtotal, // UPDATED: Use subtotal (before tax)
           'tax' => $tax,
           'total' => $total,
           'payment_method' => $payment_method_name,
           'created_at' => current_time('mysql')
       ], [
           '%s','%d','%d','%d','%s','%s','%s',
           '%s','%s','%s','%s','%s','%s','%f','%f','%f','%s','%s'
       ]);
       
       if (!$booking_inserted) {
           error_log("Failed to create booking. MySQL Error: " . $wpdb->last_error);
           wp_send_json_error(['message' => 'Failed to create booking']);
       }
       
       $booking_id = $wpdb->insert_id;
       
       // Save booking metadata - UPDATED: Include quantity
       $metadata = [
           'service_name' => $service->name,
           'duration' => $service->duration,
           'quantity' => $quantity, // NEW
           'total_duration' => $total_duration, // NEW
           'payment_source' => 'op',
           'payment_gateway' => $payment_gateway,
           'customer_name' => $customer_name,
           'customer_email' => $customer_email,
           'customer_phone' => $customer_phone,
           'booking_date' => $booking_date,
           'start_time' => $start_time,
           'end_time' => $calculated_end_time, // UPDATED
           'amount' => $subtotal, // UPDATED
           'tax' => $tax,
           'total' => $total,
           'is_bk_booking' => true,
           'return_token' => bk_generate_return_token(),
       ];
       
       $metadata['op_method_id'] = $op_method_id;
       $metadata['payment_method'] = $payment_method_name;

       $payments_table = $wpdb->prefix . 'op_payments';
       $payment_rand_id = bntm_rand_id();
       
       $payment_inserted = $wpdb->insert($payments_table, [
           'rand_id' => $payment_rand_id,
           'business_id' => $business_id,
           'invoice_id' => 1,
           'amount' => $total,
           'payment_method' => 'online',
           'payment_gateway' => $payment_gateway,
           'status' => 'pending-payment',
           'attempted_at' => current_time('mysql')
       ], [
           '%s','%d','%d','%f','%s','%s','%s','%s'
       ]);
       
       if (!$payment_inserted) {
           error_log('Failed to create OP payment record for booking: ' . $booking_rand_id);
       }
       
       $metadata['payment_rand_id'] = $payment_rand_id;
       
       update_option('bk_booking_' . $booking_rand_id . '_data', $metadata);
       
       // Send confirmation email - UPDATED: Include quantity and 12-hour format
       bk_send_booking_confirmation_email($customer_email, [
           'name' => $customer_name,
           'service' => $service->name,
           'quantity' => $quantity, // NEW
           'duration' => $service->duration, // NEW
           'total_duration' => $total_duration, // NEW
           'date' => $booking_date,
           'start_time' => $start_time,
           'end_time' => $calculated_end_time, // UPDATED
           'unit_price' => $unit_price, // NEW
           'subtotal' => $subtotal, // NEW
           'tax' => $tax, // NEW
           'tax_rate' => $tax_rate, // NEW
           'total' => $total,
           'booking_id' => $booking_rand_id,
           'payment_status' => $payment_status,
           'payment_method' => $payment_method_name
       ]);
       
       $mock_invoice = (object)[
           'id' => $booking_id,
           'rand_id' => $booking_rand_id,
           'business_id' => $business_id,
           'total' => $total,
           'currency' => bntm_get_setting('bk_currency', 'PHP'),
           'customer_email' => $customer_email,
           'return_token' => $metadata['return_token'],
       ];
       
       $config = json_decode($op_payment_method->config, true);
       $payment_result = op_bk_process_paymaya_payment($mock_invoice, $op_payment_method, $config, $total);
       
       if ($payment_result['success']) {
           if (isset($payment_result['transaction_id'])) {
               $wpdb->update(
                   $payments_table,
                   ['transaction_id' => $payment_result['transaction_id']],
                   ['rand_id' => $payment_rand_id],
                   ['%s'],
                   ['%s']
               );
               
               $metadata['transaction_id'] = $payment_result['transaction_id'];
               update_option('bk_booking_' . $booking_rand_id . '_data', $metadata);
           }
           
           wp_send_json_success([
               'message' => $payment_result['message'],
               'redirect_url' => $payment_result['redirect_url']
           ]);
       }

       $wpdb->delete($bookings_table, ['id' => $booking_id], ['%d']);
       if (isset($payment_rand_id)) {
           $wpdb->delete($payments_table, ['rand_id' => $payment_rand_id], ['%s']);
       }
       delete_option('bk_booking_' . $booking_rand_id . '_data');
       
       wp_send_json_error(['message' => $payment_result['message'] ?? 'Payment processing failed']);
   }
   
   /* ---------- BK PAYMENT GATEWAY IMPLEMENTATIONS ---------- */
   
   function op_bk_process_paymaya_payment($invoice, $payment_method, $config, $amount) {
       $mode = $payment_method->mode;
       $base_url = $mode === 'sandbox'
           ? 'https://pg-sandbox.paymaya.com/checkout/v1/checkouts'
           : 'https://pg.maya.ph/checkout/v1/checkouts';
       
       // BK bookings redirect to booking-transaction page
       $return_token = $invoice->return_token ?? '';
       $success_url = bk_get_return_url($invoice->rand_id, $return_token, 'success');
       $failure_url = bk_get_return_url($invoice->rand_id, $return_token, 'failed');
       $cancel_url = bk_get_return_url($invoice->rand_id, $return_token, 'cancelled');
       
       // Ensure amount is properly formatted as float
       $formatted_amount = floatval($amount);
       
       $checkout_items = [];
       if (!empty($invoice->items) && is_array($invoice->items)) {
           foreach ($invoice->items as $item) {
               $line_amount = floatval($item['amount'] ?? 0);
               $checkout_items[] = [
                   'name' => ($item['service_name'] ?? 'Booking') . ' - ' . ($item['start_time'] ?? ''),
                   'quantity' => 1,
                   'amount' => ['value' => $line_amount],
                   'totalAmount' => ['value' => $line_amount],
               ];
           }
       }
       if (empty($checkout_items)) {
           $checkout_items[] = [
               'name' => 'Booking Payment - ' . $invoice->rand_id,
               'quantity' => 1,
               'amount' => ['value' => $formatted_amount],
               'totalAmount' => ['value' => $formatted_amount],
           ];
       }
   
       // PayMaya checkout data
       $checkout_data = [
           'totalAmount' => [
               'value' => $formatted_amount,
               'currency' => 'PHP'
           ],
           'buyer' => [
               'firstName' => $invoice->customer_name ?? 'Customer',
               'lastName' => 'Booking',
               'contact' => [
                   'email' => $invoice->customer_email ?? 'customer@example.com'
               ]
           ],
           'items' => $checkout_items,
           'redirectUrl' => [
               'success' => $success_url,
               'failure' => $failure_url,
               'cancel' => $cancel_url
           ],
           'requestReferenceNumber' => $invoice->rand_id,
           'metadata' => [
               'booking_id' => $invoice->rand_id,
               'customer_email' => $invoice->customer_email ?? '',
               'is_bk_booking' => 'true',
               'return_token' => $return_token,
           ]
       ];
       
       // Log the request for debugging
       error_log('PayMaya BK Checkout Request: ' . json_encode($checkout_data));
       
       // Authorization should only use public_key (for checkout creation)
       $response = wp_remote_post($base_url, [
           'method' => 'POST',
           'headers' => [
               'Authorization' => 'Basic ' . base64_encode($config['public_key'] . ':'),
               'Content-Type' => 'application/json'
           ],
           'body' => json_encode($checkout_data),
           'timeout' => 30
       ]);
       
       if (is_wp_error($response)) {
           error_log('PayMaya API Error: ' . $response->get_error_message());
           return ['success' => false, 'message' => 'Failed to create PayMaya checkout: ' . $response->get_error_message()];
       }
       
       $status_code = wp_remote_retrieve_response_code($response);
       $response_data = json_decode(wp_remote_retrieve_body($response), true);
       
       // Log the response for debugging
       error_log('PayMaya BK Response Code: ' . $status_code);
       error_log('PayMaya BK Response: ' . print_r($response_data, true));
       
       // Check for both 200 and 201 status codes
       if ($status_code !== 200 && $status_code !== 201) {
           $error_message = isset($response_data['message']) ? $response_data['message'] : 'Unknown error';
           $error_details = isset($response_data['parameters']) ? ' Parameters: ' . json_encode($response_data['parameters']) : '';
           return ['success' => false, 'message' => 'PayMaya checkout creation failed: ' . $error_message . $error_details . ' (Status: ' . $status_code . ')'];
       }
       
       if (!isset($response_data['checkoutId'])) {
           return ['success' => false, 'message' => 'PayMaya checkout creation failed - no checkout ID returned'];
       }
       
       // Use the redirectUrl provided by PayMaya response
       $checkout_url = isset($response_data['redirectUrl']) 
           ? $response_data['redirectUrl']
           : ($mode === 'sandbox'
               ? 'https://pg-sandbox.paymaya.com/checkout?id=' . $response_data['checkoutId']
               : 'https://pg.maya.ph/checkout?id=' . $response_data['checkoutId']);
       
       return [
           'success' => true,
           'message' => 'Redirecting to PayMaya',
           'redirect_url' => $checkout_url,
           'transaction_id' => $response_data['checkoutId']
       ];
   }
   
   /* ---------- BK BOOKING PAYMENT COMPLETION ---------- */
   add_action('template_redirect', 'op_bk_handle_payment_success_redirect', 6);
   function op_bk_handle_payment_success_redirect() {
       if (!isset($_GET['bk_return_token']) || !isset($_GET['bk_return_result'])) {
           return false;
       }
   
       if (!isset($_GET['id'])) {
           return false;
       }
   
       $booking_rand_id = sanitize_text_field(wp_unslash($_GET['id']));
       $gateway = isset($_GET['gateway']) ? sanitize_text_field(wp_unslash($_GET['gateway'])) : '';
       $return_token = sanitize_text_field(wp_unslash($_GET['bk_return_token']));
       $return_result = sanitize_text_field(wp_unslash($_GET['bk_return_result']));
   
       $metadata = get_option('bk_booking_' . $booking_rand_id . '_data');
   
       if (!$metadata || empty($metadata['is_bk_booking']) || empty($gateway)) {
           return false;
       }
   
       if (!hash_equals((string) ($metadata['return_token'] ?? ''), $return_token)) {
           error_log("BK return token mismatch for booking: " . $booking_rand_id);
           return false;
       }
   
       if ($return_result === 'success') {
           $result = op_complete_bk_booking_payment($gateway, $booking_rand_id, true);
           if ($result) {
               error_log("BK Booking payment completion successful");
           } else {
               error_log("BK Booking payment completion failed");
           }
           return $result;
       }
   
       error_log("BK payment return received without success state: " . $booking_rand_id . ' / ' . $return_result);
       return op_mark_bk_booking_payment_return_status($booking_rand_id, $return_result);
   }
   
   function op_mark_bk_booking_payment_return_status($booking_rand_id, $return_result) {
       global $wpdb;
       $bookings_table = $wpdb->prefix . 'bk_bookings';
       $payments_table = $wpdb->prefix . 'op_payments';
       $metadata = get_option('bk_booking_' . $booking_rand_id . '_data');
       if (!$metadata || empty($metadata['is_bk_booking'])) {
           return false;
       }

       $payment_status = $return_result === 'cancelled' ? 'dropped' : 'failed';
       $booking_where = !empty($metadata['is_group_booking']) && !empty($metadata['group_rand_id'])
           ? ['group_rand_id' => $metadata['group_rand_id']]
           : ['rand_id' => $booking_rand_id];
       $booking_where_format = isset($booking_where['group_rand_id']) ? ['%s'] : ['%s'];

       $booking_updated = $wpdb->update(
           $bookings_table,
           ['payment_status' => $payment_status],
           $booking_where,
           ['%s'],
           $booking_where_format
       );

       if (!empty($metadata['payment_rand_id'])) {
           $wpdb->update(
               $payments_table,
               ['status' => $payment_status, 'updated_at' => current_time('mysql')],
               ['rand_id' => $metadata['payment_rand_id']],
               ['%s', '%s'],
               ['%s']
           );
       }

       return $booking_updated !== false;
   }

   function op_complete_bk_booking_payment($gateway, $booking_rand_id, $allow_return_fallback = false) {
       global $wpdb;
       $bookings_table = $wpdb->prefix . 'bk_bookings';
       $payments_table = $wpdb->prefix . 'op_payments';
       $metadata = get_option('bk_booking_' . $booking_rand_id . '_data');
   
       if ($metadata && !empty($metadata['is_group_booking']) && !empty($metadata['group_rand_id'])) {
           $transaction_id = $metadata['transaction_id'] ?? '';
           switch ($gateway) {
               case 'paymaya':
                   $payment_verified = op_verify_paymaya_payment($transaction_id, intval($metadata['business_id'] ?? 0));
                   break;
               default:
                   $payment_verified = false;
                   break;
           }
           if (!$payment_verified && $allow_return_fallback && $gateway === 'paymaya') {
               $payment_verified = true;
               error_log("BK PayMaya verification was not ready; completing from valid success return token: " . $booking_rand_id);
           }
           if ($payment_verified) {
               $wpdb->update(
                   $bookings_table,
                   ['status' => 'confirmed', 'payment_status' => 'paid'],
                   ['group_rand_id' => $metadata['group_rand_id']],
                   ['%s', '%s'],
                   ['%s']
               );
               if (!empty($metadata['payment_rand_id'])) {
                   $wpdb->update(
                       $payments_table,
                       ['status' => 'completed', 'updated_at' => current_time('mysql')],
                       ['rand_id' => $metadata['payment_rand_id']],
                       ['%s', '%s'],
                       ['%s']
                   );
               }
               error_log("BK Booking group payment completed successfully: " . $booking_rand_id . ' / ' . $transaction_id);
               return true;
           }
           return false;
       }
       
       // Get booking
       $booking = $wpdb->get_row($wpdb->prepare(
           "SELECT * FROM $bookings_table WHERE rand_id = %s",
           $booking_rand_id
       ));
       
       if (!$booking) {
           error_log("BK Booking not found: " . $booking_rand_id);
           return false;
       }
       
       if (!$metadata || !isset($metadata['payment_rand_id'])) {
           error_log("BK Booking metadata or payment_rand_id not found");
           return false;
       }
       
       $transaction_id = $metadata['transaction_id'] ?? '';
       
       // Verify payment with gateway
       //$payment_verified = false;
       
       switch ($gateway) {
           case 'paymaya':
               $payment_verified = op_verify_paymaya_payment($transaction_id, $booking->business_id);
               break;

           default:
           $payment_verified = false;
           error_log("Unsupported BK payment gateway during completion: " . $gateway);
               break;
       }

       if (!$payment_verified && $allow_return_fallback && $gateway === 'paymaya') {
           $payment_verified = true;
           error_log("BK PayMaya verification was not ready; completing from valid success return token: " . $booking_rand_id);
       }

       if ($payment_verified) {
           // Update booking status
           $updated = $wpdb->update(
               $bookings_table,
               [
                   'status' => 'confirmed',
                   'payment_status' => 'paid'
               ],
               ['rand_id' => $booking_rand_id],
               ['%s', '%s'],
               ['%s']
           );
           
           if ($updated === false) {
               error_log("Failed to update booking status. MySQL Error: " . $wpdb->last_error);
           }
           
           // Update payment record
           $payment_updated = $wpdb->update(
               $payments_table,
               [
                   'status' => 'completed',
                   'updated_at' => current_time('mysql')
               ],
               ['rand_id' => $metadata['payment_rand_id']],
               ['%s', '%s'],
               ['%s']
           );
           
           if ($payment_updated === false) {
               error_log("Failed to update payment record. MySQL Error: " . $wpdb->last_error);
           }
           
           error_log("BK Booking payment completed successfully: " . $booking_rand_id);
           return true;
       }
       
       error_log("BK Booking payment verification failed");
       return false;
   }
   /* ---------- PAYMENT VERIFICATION HELPERS ---------- */
   
   function op_verify_paymaya_payment($transaction_id, $business_id) {
       if (empty($transaction_id)) {
           return false;
       }
       
       $config = bk_get_maya_config();
       if (empty($config['public_key']) || empty($config['secret_key'])) {
           error_log("Maya payment settings not found");
           return false;
       }
       $mode = $config['mode'];
       
       // Get checkout status
       $status_url = ($mode === 'sandbox'
           ? 'https://pg-sandbox.paymaya.com'
           : 'https://pg.maya.ph') . '/checkout/v1/checkouts/' . $transaction_id;
       
       $api_keys = array_filter([
           $config['secret_key'] ?? '',
           $config['public_key'] ?? '',
       ]);
       $response = null;
       foreach ($api_keys as $api_key) {
           $response = wp_remote_get($status_url, [
               'headers' => [
                   'Authorization' => 'Basic ' . base64_encode($api_key . ':')
               ]
           ]);
           if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) < 400) {
               break;
           }
       }
       
       if (is_wp_error($response)) {
           error_log("PayMaya status check error: " . $response->get_error_message());
           return false;
       }
       
       $status_data = json_decode(wp_remote_retrieve_body($response), true);
       
       // Check if payment was completed
       if (isset($status_data['status']) && 
           in_array($status_data['status'], ['PAYMENT_SUCCESS', 'COMPLETED', 'SUCCESS'])) {
           error_log("PayMaya payment verified: " . $transaction_id);
           return true;
       }
       
       error_log("PayMaya payment verification failed. Status: " . ($status_data['status'] ?? 'unknown'));
       return false;
   }
   /* ---------- BK CALENDAR PAYMENT METHODS ---------- */
   function bntm_ajax_bk_get_payment_methods() {
       $method = bk_get_paymaya_method();
       $methods = [];
       if ($method) {
           $methods[] = [
               'id' => $method->id,
               'type' => $method->gateway,
               'name' => $method->name,
               'description' => 'Secure checkout powered by Maya.'
           ];
       }
   
       wp_send_json_success(['methods' => $methods, 'source' => 'bk']);
   }
   add_action('wp_ajax_bk_get_payment_methods', 'bntm_ajax_bk_get_payment_methods');
   add_action('wp_ajax_nopriv_bk_get_payment_methods', 'bntm_ajax_bk_get_payment_methods');
   
   /**
    * Update operating hour
    */
   add_action('wp_ajax_bk_update_operating_hour', 'bntm_ajax_bk_update_operating_hour');
   
   function bntm_ajax_bk_update_operating_hour() {
       check_ajax_referer('bk_nonce', 'nonce');
       
       if (!is_user_logged_in()) {
           wp_send_json_error(['message' => 'Unauthorized']);
       }
       
       global $wpdb;
       $table = $wpdb->prefix . 'bk_operating_hours';
       $hour_id = intval($_POST['hour_id']);
       $field = sanitize_text_field($_POST['field']);
       $value = sanitize_text_field($_POST['value']);
       
       if (!in_array($field, ['start_time', 'end_time', 'is_open'])) {
           wp_send_json_error(['message' => 'Invalid field']);
       }
       
       $update_data = [$field => $value];
       $update_format = ['%s'];
       
       if ($field === 'is_open') {
           $update_data[$field] = $value ? 1 : 0;
           $update_format = ['%d'];
       }
       
       $result = $wpdb->update(
           $table,
           $update_data,
           ['id' => $hour_id],
           $update_format,
           ['%d']
       );
       
       if ($result !== false) {
           wp_send_json_success(['message' => 'Updated']);
       } else {
           wp_send_json_error(['message' => 'Failed to update']);
       }
   }
   
   /* ---------- FIX: Add Service Edit Handler ---------- */
   add_action('wp_ajax_bk_add_service', 'bntm_ajax_bk_add_service');
   
   function bntm_ajax_bk_add_service() {
       check_ajax_referer('bk_nonce', 'nonce');
       
       if (!is_user_logged_in()) {
           wp_send_json_error(['message' => 'Unauthorized']);
       }
       
       global $wpdb;
       $table = $wpdb->prefix . 'bk_services';
       $business_id = bntm_get_current_business_id();
       
       $service_name = sanitize_text_field($_POST['service_name'] ?? '');
       $category = sanitize_text_field($_POST['category'] ?? '');
       $allowed_categories = bk_get_global_categories();
       $duration = intval($_POST['duration'] ?? 60);
       $price = floatval($_POST['price'] ?? 0);
       $description = sanitize_textarea_field($_POST['description'] ?? '');
       $image_url = '';
       $uploaded = bk_handle_image_upload('service_image', $business_id, 'service');
       if (is_wp_error($uploaded)) {
           wp_send_json_error(['message' => $uploaded->get_error_message()]);
       }
       if ($uploaded) {
           $image_url = $uploaded;
       }
       
       if (empty($service_name)) {
           wp_send_json_error(['message' => 'Service name is required']);
       }
       
       if ($duration < 15 || $duration % 15 !== 0) {
           wp_send_json_error(['message' => 'Duration must be in 15-minute intervals']);
       }
       if ($category !== '' && !in_array($category, $allowed_categories, true)) {
           wp_send_json_error(['message' => 'Please choose a valid category from Admin Settings']);
       }
       
       $result = $wpdb->insert($table, [
           'rand_id' => bntm_rand_id(),
           'business_id' => $business_id,
           'name' => $service_name,
           'category' => $category,
           'duration' => $duration,
           'price' => $price,
           'description' => $description,
           'image_url' => $image_url,
           'status' => 'active',
           'created_at' => current_time('mysql')
       ], ['%s', '%d', '%s', '%s', '%d', '%f', '%s', '%s', '%s', '%s']);
       
       if ($result) {
           wp_send_json_success(['message' => 'Service added successfully!']);
       } else {
           wp_send_json_error(['message' => 'Failed to add service']);
       }
   }
   
   add_action('wp_ajax_bk_edit_service', 'bntm_ajax_bk_edit_service');
   
   function bntm_ajax_bk_edit_service() {
       check_ajax_referer('bk_nonce', 'nonce');
       
       if (!is_user_logged_in()) {
           wp_send_json_error(['message' => 'Unauthorized']);
       }
       
       global $wpdb;
       $table = $wpdb->prefix . 'bk_services';
       $business_id = bntm_get_current_business_id();
       
       $service_id = intval($_POST['service_id'] ?? 0);
       
       // Get the service to verify ownership
       $service = $wpdb->get_row($wpdb->prepare(
           "SELECT * FROM $table WHERE id = %d AND business_id = %d",
           $service_id, $business_id
       ));
       
       if (!$service) {
           wp_send_json_error(['message' => 'Service not found']);
       }
       
       // Send back the service data for editing
       wp_send_json_success([
           'id' => $service->id,
           'name' => $service->name,
           'category' => $service->category ?? '',
           'duration' => $service->duration,
           'price' => $service->price,
           'description' => $service->description,
           'image_url' => $service->image_url ?? '',
           'status' => $service->status
       ]);
   }
   
   add_action('wp_ajax_bk_update_service', 'bntm_ajax_bk_update_service');
   
   function bntm_ajax_bk_update_service() {
       check_ajax_referer('bk_nonce', 'nonce');
       
       if (!is_user_logged_in()) {
           wp_send_json_error(['message' => 'Unauthorized']);
       }
       
       global $wpdb;
       $table = $wpdb->prefix . 'bk_services';
       $business_id = bntm_get_current_business_id();
       
       $service_id = intval($_POST['service_id'] ?? 0);
       $service_name = sanitize_text_field($_POST['service_name'] ?? '');
       $category = sanitize_text_field($_POST['category'] ?? '');
       $allowed_categories = bk_get_global_categories();
       $duration = intval($_POST['duration'] ?? 60);
       $price = floatval($_POST['price'] ?? 0);
       $description = sanitize_textarea_field($_POST['description'] ?? '');
       $service = $wpdb->get_row($wpdb->prepare(
           "SELECT * FROM $table WHERE id = %d AND business_id = %d",
           $service_id,
           $business_id
       ));
       if (!$service) {
           wp_send_json_error(['message' => 'Service not found']);
       }
       
       if (empty($service_name)) {
           wp_send_json_error(['message' => 'Service name is required']);
       }
       
       if ($duration < 15 || $duration % 15 !== 0) {
           wp_send_json_error(['message' => 'Duration must be in 15-minute intervals']);
       }
       if ($category !== '' && !in_array($category, $allowed_categories, true)) {
           wp_send_json_error(['message' => 'Please choose a valid category from Admin Settings']);
       }
       
       $update_data = [
           'name' => $service_name,
           'category' => $category,
           'duration' => $duration,
           'price' => $price,
           'description' => $description
       ];
       $update_format = ['%s', '%s', '%d', '%f', '%s'];
       $uploaded = bk_handle_image_upload('service_image', $business_id, 'service');
       if (is_wp_error($uploaded)) {
           wp_send_json_error(['message' => $uploaded->get_error_message()]);
       }
       if ($uploaded) {
           $update_data['image_url'] = $uploaded;
           $update_format[] = '%s';
       }
   
       $result = $wpdb->update(
           $table,
           $update_data,
           [
               'id' => $service_id,
               'business_id' => $business_id
           ],
           $update_format,
           ['%d', '%d']
       );
       
       if ($result !== false) {
           wp_send_json_success(['message' => 'Service updated successfully!']);
       } else {
           wp_send_json_error(['message' => 'Failed to update service']);
       }
   }
   
   add_action('wp_ajax_bk_delete_service', 'bntm_ajax_bk_delete_service');
   
   function bntm_ajax_bk_delete_service() {
       check_ajax_referer('bk_nonce', 'nonce');
       
       if (!is_user_logged_in()) {
           wp_send_json_error(['message' => 'Unauthorized']);
       }
       
       global $wpdb;
       $table = $wpdb->prefix . 'bk_services';
       $business_id = bntm_get_current_business_id();
       
       $service_id = intval($_POST['service_id'] ?? 0);
       
       $result = $wpdb->delete(
           $table,
           [
               'id' => $service_id,
               'business_id' => $business_id
           ],
           ['%d', '%d']
       );
       
       if ($result) {
           wp_send_json_success(['message' => 'Service deleted successfully']);
       } else {
           wp_send_json_error(['message' => 'Failed to delete service']);
       }
   }
   /**
    * Update booking status
    */
   function bntm_ajax_bk_update_booking_status() {
       check_ajax_referer('bk_nonce', 'nonce');
       
       if (!is_user_logged_in()) {
           wp_send_json_error(['message' => 'Unauthorized']);
       }
       
       global $wpdb;
       $table = $wpdb->prefix . 'bk_bookings';
       $booking_id = sanitize_text_field($_POST['booking_id']);
       $status = sanitize_text_field($_POST['status']);
       
       // Prepare update data
       $update_data = ['status' => $status];
       $update_format = ['%s'];
       
       // If status is cancelled, also set payment_status to 'dropped'
       if ($status === 'cancelled') {
           $update_data['payment_status'] = 'dropped';
           $update_format[] = '%s';
       }
       
       $result = $wpdb->update(
           $table,
           $update_data,
           ['rand_id' => $booking_id],
           $update_format,
           ['%s']
       );
       
       if ($result !== false) {
           $message = $status === 'cancelled' 
               ? 'Booking cancelled and payment status set to dropped' 
               : 'Booking status updated';
           wp_send_json_success(['message' => $message]);
       } else {
           wp_send_json_error(['message' => 'Failed to update booking']);
       }
   }
   
   /* ─────────────────────────────────────────────
      AJAX HANDLER — updated to handle gallery + sports
   ───────────────────────────────────────────── */
   add_action('wp_ajax_bk_save_booking_settings','bk_save_booking_settings_handler');
   function bk_save_booking_settings_handler() {
       check_ajax_referer('bk_nonce','nonce');
       if (!is_user_logged_in()) wp_send_json_error(['message'=>'Unauthorized']);
    
       global $wpdb;
       $business_id = absint($_POST['business_id'] ?? 0);
       if (!$business_id) {
           $business_id = bntm_get_current_business_id();
       }
       if (!$business_id || (!current_user_can('manage_options') && $business_id !== bntm_get_current_business_id())) {
           wp_send_json_error(['message'=>'Unauthorized business.']);
       }

       $profile_table = $wpdb->prefix . 'bk_business_profiles';
       $shared_description = sanitize_textarea_field(wp_unslash($_POST['bk_description'] ?? ''));
       bk_update_business_setting($business_id, 'bk_description', $shared_description);
       bk_update_business_setting($business_id, 'bk_terms', sanitize_textarea_field(wp_unslash($_POST['bk_terms'] ?? '')));
       bk_update_business_setting($business_id, 'bk_holidays', sanitize_textarea_field(wp_unslash($_POST['bk_holidays'] ?? '')));
       bk_update_business_setting($business_id, 'bk_show_amenities', !empty($_POST['show_amenities']) ? '1' : '0');
       $amenities = bk_parse_amenities_list(wp_unslash($_POST['bk_amenities'] ?? []));
       $custom_amenities = bk_parse_amenities_list(wp_unslash($_POST['bk_custom_amenities'] ?? ''));
       foreach ($custom_amenities as $custom_amenity) {
           if (!in_array($custom_amenity, $amenities, true)) {
               $amenities[] = $custom_amenity;
           }
       }
       bk_update_business_setting($business_id, 'bk_amenities', wp_json_encode(array_values($amenities)));
       bk_update_business_setting($business_id, 'bk_remittance_bank_name', sanitize_text_field(wp_unslash($_POST['bk_remittance_bank_name'] ?? '')));
       bk_update_business_setting($business_id, 'bk_remittance_bank_branch', sanitize_text_field(wp_unslash($_POST['bk_remittance_bank_branch'] ?? '')));
       bk_update_business_setting($business_id, 'bk_remittance_account_name', sanitize_text_field(wp_unslash($_POST['bk_remittance_account_name'] ?? '')));
       bk_update_business_setting($business_id, 'bk_remittance_account_number', sanitize_text_field(wp_unslash($_POST['bk_remittance_account_number'] ?? '')));
       bk_update_business_setting($business_id, 'bk_remittance_bank_notes', sanitize_textarea_field(wp_unslash($_POST['bk_remittance_bank_notes'] ?? '')));
       $hours_table = $wpdb->prefix . 'bk_operating_hours';
       foreach ((array) ($_POST['hours'] ?? []) as $hour_id => $hour_data) {
           $hour_id = absint($hour_id);
           $hour_data = (array) $hour_data;
           $existing_hour = $wpdb->get_var($wpdb->prepare(
               "SELECT id FROM {$hours_table} WHERE id = %d AND business_id = %d",
               $hour_id,
               $business_id
           ));
           if (!$existing_hour) {
               continue;
           }
           $start_time = sanitize_text_field(wp_unslash($hour_data['start_time'] ?? '09:00'));
           $end_time = sanitize_text_field(wp_unslash($hour_data['end_time'] ?? '17:00'));
           if (!preg_match('/^\d{2}:\d{2}$/', $start_time)) {
               $start_time = '09:00';
           }
           if (!preg_match('/^\d{2}:\d{2}$/', $end_time)) {
               $end_time = '17:00';
           }
           $wpdb->update(
               $hours_table,
               [
                   'is_open' => !empty($hour_data['is_open']) ? 1 : 0,
                   'start_time' => $start_time . ':00',
                   'end_time' => $end_time . ':00',
               ],
               ['id' => $hour_id, 'business_id' => $business_id],
               ['%d', '%s', '%s'],
               ['%d', '%d']
           );
       }
    
       /* Sports — store per-business */
       /* Image uploads */
       $profile   = bk_get_business_profile($business_id);
       $logo_url  = $profile->logo_url  ?? '';
       $cover_url = $profile->cover_url ?? '';
       $photo_url = $profile->photo_url ?? '';
    
       foreach (['logo_image'=>'logo','cover_image'=>'cover','photo_image'=>'photo'] as $field=>$pfx) {
           $uploaded = bk_handle_image_upload($field,$business_id,$pfx);
           if (is_wp_error($uploaded)) wp_send_json_error(['message'=>$uploaded->get_error_message()]);
           if ($uploaded) {
               if ($field==='logo_image')  $logo_url  = $uploaded;
               if ($field==='cover_image') $cover_url = $uploaded;
               if ($field==='photo_image') $photo_url = $uploaded;
           }
       }
    
       /* Gallery — existing + new uploads */
    $gallery_existing_raw = json_decode(wp_unslash($_POST['gallery_existing'] ?? '[]'), true);
    $gallery_existing = [];
    if (is_array($gallery_existing_raw)) {
        foreach ($gallery_existing_raw as $gallery_url) {
            $gallery_url = esc_url_raw($gallery_url);
            if ($gallery_url && strpos($gallery_url, 'blob:') !== 0 && !in_array($gallery_url, $gallery_existing, true)) {
                $gallery_existing[] = $gallery_url;
            }
        }
    }
       if (!empty($_FILES['gallery_images']['name'][0])) {
           $files = $_FILES['gallery_images'];
           for ($i=0;$i<min(count($files['name']),8-count($gallery_existing));$i++) {
               $_FILES['bk_gallery_'.$i] = ['name'=>$files['name'][$i],'type'=>$files['type'][$i],'tmp_name'=>$files['tmp_name'][$i],'error'=>$files['error'][$i],'size'=>$files['size'][$i]];
               $up = bk_handle_image_upload('bk_gallery_'.$i,$business_id,'gallery');
               if (!is_wp_error($up) && $up) $gallery_existing[] = $up;
           }
       }
       $gallery_json = json_encode(array_values(array_filter($gallery_existing)));
    
       $payload = [
           'logo_url'     => $logo_url,
           'cover_url'    => $cover_url,
           'photo_url'    => $photo_url,
           'gallery_urls' => $gallery_json,
           'location'     => sanitize_text_field($_POST['location']   ?? ''),
           'latitude'     => ($_POST['latitude']  ?? '') !== '' ? floatval($_POST['latitude'])  : null,
           'longitude'    => ($_POST['longitude'] ?? '') !== '' ? floatval($_POST['longitude']) : null,
           'description'  => $shared_description,
           'accent_color' => sanitize_hex_color($_POST['accent_color'] ?? '') ?: bntm_get_setting('color_primary','#3b82f6'),
           'is_listed'    => !empty($_POST['is_listed']) ? 1 : 0,
           'is_featured_home' => !empty($profile->is_featured_home) ? 1 : 0,
       ];
    
       $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$profile_table} WHERE business_id=%d",$business_id));
       if ($exists) {
           $wpdb->update($profile_table,$payload,['business_id'=>$business_id],['%s','%s','%s','%s','%s','%f','%f','%s','%s','%d','%d'],['%d']);
       } else {
           $payload['rand_id']     = bk_generate_public_id();
           $payload['business_id'] = $business_id;
           $payload['created_at']  = current_time('mysql');
           $wpdb->insert($profile_table,$payload);
       }
   
       wp_send_json_success([
           'message'=>'Settings saved successfully! Amenities saved: ' . count($amenities),
           'amenities' => array_values($amenities),
           'show_amenities' => !empty($_POST['show_amenities']) ? '1' : '0',
           'business_id' => $business_id,
       ]);
   }
   
   add_action('wp_ajax_bk_save_admin_settings', 'bk_save_admin_settings_handler');
   function bk_save_admin_settings_handler() {
       check_ajax_referer('bk_nonce', 'nonce');
       if (!current_user_can('manage_options')) {
           wp_send_json_error(['message' => 'Unauthorized']);
       }
   
       global $wpdb;
       $profiles_table = $wpdb->prefix . 'bk_business_profiles';
       $featured_business_ids = array_map('absint', (array) ($_POST['featured_business_ids'] ?? []));
       $featured_business_ids = array_values(array_filter($featured_business_ids));
       $global_categories_raw = json_decode(stripslashes($_POST['global_categories'] ?? '[]'), true);
       $global_categories = [];
       if (is_array($global_categories_raw)) {
           foreach ($global_categories_raw as $category) {
               $category = sanitize_text_field($category);
               if ($category !== '' && !in_array($category, $global_categories, true)) {
                   $global_categories[] = $category;
               }
           }
       }
   
       $wpdb->query("UPDATE {$profiles_table} SET is_featured_home = 0");
       if (!empty($featured_business_ids)) {
           $placeholders = implode(',', array_fill(0, count($featured_business_ids), '%d'));
           $wpdb->query($wpdb->prepare(
               "UPDATE {$profiles_table} SET is_featured_home = 1 WHERE business_id IN ({$placeholders})",
               ...$featured_business_ids
           ));
       }

      $remittance_percentage_fee = max(0, floatval($_POST['remittance_percentage_fee'] ?? 0));
      $remittance_platform_fee = max(0, floatval($_POST['remittance_platform_fee'] ?? 0));
      $remittance_frequency_days = intval($_POST['remittance_frequency_days'] ?? 7);
      if (!in_array($remittance_frequency_days, [7, 15], true)) {
          $remittance_frequency_days = 7;
      }

      bk_update_shared_setting('bk_admin_sports_activities', wp_json_encode($global_categories));
      bk_update_shared_setting('bk_global_categories', wp_json_encode($global_categories));
      bk_update_shared_setting('bk_remittance_percentage_fee', (string) $remittance_percentage_fee);
      bk_update_shared_setting('bk_remittance_platform_fee', (string) $remittance_platform_fee);
      bk_update_shared_setting('bk_remittance_frequency_days', (string) $remittance_frequency_days);
      bk_update_shared_setting('bk_public_primary_color', sanitize_hex_color($_POST['public_primary_color'] ?? '') ?: '#3b82f6');

      $maya_mode = sanitize_text_field(wp_unslash($_POST['maya_mode'] ?? 'sandbox'));
      update_option('bk_maya_mode', in_array($maya_mode, ['sandbox', 'live'], true) ? $maya_mode : 'sandbox');
      $maya_public_key = sanitize_text_field(wp_unslash($_POST['maya_public_key'] ?? ''));
      $maya_secret_key = sanitize_text_field(wp_unslash($_POST['maya_secret_key'] ?? ''));
      if ($maya_public_key !== '') {
          update_option('bk_maya_public_key_enc', bk_encrypt_key($maya_public_key));
      }
      if ($maya_secret_key !== '') {
          update_option('bk_maya_secret_key_enc', bk_encrypt_key($maya_secret_key));
      }
   
       $slide_existing = array_map('esc_url_raw', (array) ($_POST['home_slide_existing'] ?? []));
       $slide_pre_titles = array_map('sanitize_text_field', array_map('wp_unslash', (array) ($_POST['home_slide_pre_title'] ?? [])));
       $slide_titles = array_map('sanitize_text_field', array_map('wp_unslash', (array) ($_POST['home_slide_title'] ?? [])));
       $slide_descriptions = array_map('sanitize_textarea_field', array_map('wp_unslash', (array) ($_POST['home_slide_description'] ?? [])));
       $slide_show_hero = array_map('absint', (array) ($_POST['home_slide_show_hero'] ?? []));
       $slide_show_onboarding = array_map('absint', (array) ($_POST['home_slide_show_onboarding'] ?? []));
       $row_count = max(count($slide_existing), count($slide_titles), !empty($_FILES['home_slide_images']['name']) ? count($_FILES['home_slide_images']['name']) : 0);
       $home_slides = [];

       for ($i = 0; $i < $row_count; $i++) {
           $image_url = $slide_existing[$i] ?? '';
           if (!empty($_FILES['home_slide_images']['name'][$i])) {
               $_FILES['bk_home_slide_'.$i] = [
                   'name' => $_FILES['home_slide_images']['name'][$i],
                   'type' => $_FILES['home_slide_images']['type'][$i],
                   'tmp_name' => $_FILES['home_slide_images']['tmp_name'][$i],
                   'error' => $_FILES['home_slide_images']['error'][$i],
                   'size' => $_FILES['home_slide_images']['size'][$i],
               ];
               $uploaded_slide = bk_handle_image_upload('bk_home_slide_'.$i, 0, 'home-slide');
               if (is_wp_error($uploaded_slide)) {
                   wp_send_json_error(['message' => $uploaded_slide->get_error_message()]);
               }
               if ($uploaded_slide) {
                   $image_url = $uploaded_slide;
               }
           }
           if (!$image_url || strpos($image_url, 'blob:') === 0) {
               continue;
           }
           $home_slides[] = [
               'image_url' => esc_url_raw($image_url),
               'pre_title' => $slide_pre_titles[$i] ?? '',
               'title' => $slide_titles[$i] ?? '',
               'description' => $slide_descriptions[$i] ?? '',
               'show_hero' => !empty($slide_show_hero[$i]) ? 1 : 0,
               'show_onboarding' => !empty($slide_show_onboarding[$i]) ? 1 : 0,
           ];
       }

      $hero_slides = array_values(array_filter($home_slides, static fn($slide) => !empty($slide['show_hero'])));
      $hero_images = array_values(array_map(static fn($slide) => $slide['image_url'], $hero_slides));
      $first_hero = $hero_slides[0] ?? ($home_slides[0] ?? null);
      bk_update_shared_setting('bk_admin_home_slides', wp_json_encode(array_values($home_slides)));
      bk_update_shared_setting('bk_admin_home_hero_carousel_images', wp_json_encode($hero_images));
      bk_update_shared_setting('bk_home_hero_images', wp_json_encode($hero_images));
      bk_update_shared_setting('bk_admin_home_hero_image', !empty($hero_images) ? $hero_images[0] : '');
      bk_update_shared_setting('bk_home_hero_image', !empty($hero_images) ? $hero_images[0] : '');
      bk_update_shared_setting('bk_admin_home_hero_eyebrow', $first_hero['pre_title'] ?? '');
      bk_update_shared_setting('bk_admin_home_hero_title', $first_hero['title'] ?? '');
      bk_update_shared_setting('bk_admin_home_hero_description', $first_hero['description'] ?? '');
   
       wp_send_json_success(['message' => 'Admin settings saved successfully!']);
   }
   /**
    * Save booking settings
    */
   add_action('wp_ajax_bk_save_settings', 'bntm_ajax_bk_save_settings');
   
   function bntm_ajax_bk_save_settings() {
       if (!is_user_logged_in()) {
           wp_send_json_error(['message' => 'Unauthorized']);
       }
       
       bntm_set_setting('bk_slot_interval', intval($_POST['slot_interval']));
       bntm_set_setting('bk_advance_booking_days', intval($_POST['advance_booking_days']));
       bntm_set_setting('bk_currency', sanitize_text_field($_POST['currency']));
       bntm_set_setting('bk_tax_rate', floatval($_POST['tax_rate']));
       
       wp_send_json_success(['message' => 'Booking settings saved successfully!']);
   }
   
   /* ---------- HELPER FUNCTIONS ---------- */
   
   function bk_render_recent_bookings($business_id, $limit = 10) {
       global $wpdb;
       $bookings_table = $wpdb->prefix . 'bk_bookings';
       $services_table = $wpdb->prefix . 'bk_services';
       
       // Get current page from URL parameter
       $current_page = isset($_GET['bk_page']) ? max(1, intval($_GET['bk_page'])) : 1;
       $offset = ($current_page - 1) * $limit;
       
       // Get total count for pagination
       $total_bookings = $wpdb->get_var(
           "SELECT COUNT(*) 
            FROM $bookings_table 
            WHERE payment_status NOT IN ('pending','dropped', 'waiting_payment')"
       );
       
       $total_pages = ceil($total_bookings / $limit);
       
       // Get bookings for current page
       $bookings = $wpdb->get_results($wpdb->prepare(
           "SELECT b.*, s.name as service_name 
            FROM $bookings_table b
            LEFT JOIN $services_table s ON b.service_id = s.id
            WHERE b.payment_status NOT IN ('pending','dropped', 'waiting_payment')
            ORDER BY b.booking_date DESC, b.start_time DESC
            LIMIT %d OFFSET %d",
           $limit, $offset
       ));
       
       if (empty($bookings) && $current_page == 1) {
           return '<p>No recent bookings.</p>';
       }
       
       ob_start();
       ?>
       
      <div class="bntm-table-wrapper">
       <table class="bntm-table">
           <thead>
               <tr>
                   <th>Service</th>
                   <th>Customer</th>
                   <th>Date & Time</th>
                   <th>Status</th>
                   <th>Payment</th>
               </tr>
           </thead>
           <tbody>
               <?php if (!empty($bookings)): ?>
                   <?php foreach ($bookings as $booking): ?>
                       <tr>
                           <td><?php echo esc_html($booking->service_name); ?></td>
                           <td><?php echo esc_html($booking->customer_name); ?></td>
                           <td>
                               <?php echo date('M d, Y H:i', strtotime($booking->booking_date . ' ' . $booking->start_time)); ?>
                           </td>
                           <td>
                               <span class="bk-status-badge status-<?php echo esc_attr($booking->status); ?>">
                                   <?php echo ucfirst($booking->status); ?>
                               </span>
                           </td>
                           <td>
                               <span class="bk-payment-badge payment-<?php echo esc_attr($booking->payment_status); ?>">
                                   <?php echo ucfirst($booking->payment_status); ?>
                               </span>
                           </td>
                       </tr>
                   <?php endforeach; ?>
               <?php else: ?>
                   <tr>
                       <td colspan="5" style="text-align: center;">No bookings found on this page.</td>
                   </tr>
               <?php endif; ?>
           </tbody>
       </table>
       </div>
       <?php if ($total_pages > 1): ?>
           <div class="bk-pagination">
               <?php
               $base_url = remove_query_arg('bk_page');
               
               // Previous button
               if ($current_page > 1): ?>
                   <a href="<?php echo esc_url(add_query_arg('bk_page', $current_page - 1, $base_url)); ?>" class="bk-page-btn">
                       &laquo; Previous
                   </a>
               <?php endif; ?>
               
               <!-- Page numbers -->
               <div class="bk-page-numbers">
                   <?php
                   // Show first page
                   if ($current_page > 3) {
                       echo '<a href="' . esc_url(add_query_arg('bk_page', 1, $base_url)) . '" class="bk-page-num">1</a>';
                       if ($current_page > 4) {
                           echo '<span class="bk-page-dots">...</span>';
                       }
                   }
                   
                   // Show pages around current page
                   for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++) {
                       $active_class = ($i == $current_page) ? ' active' : '';
                       echo '<a href="' . esc_url(add_query_arg('bk_page', $i, $base_url)) . '" class="bk-page-num' . $active_class . '">' . $i . '</a>';
                   }
                   
                   // Show last page
                   if ($current_page < $total_pages - 2) {
                       if ($current_page < $total_pages - 3) {
                           echo '<span class="bk-page-dots">...</span>';
                       }
                       echo '<a href="' . esc_url(add_query_arg('bk_page', $total_pages, $base_url)) . '" class="bk-page-num">' . $total_pages . '</a>';
                   }
                   ?>
               </div>
               
               <!-- Next button -->
               <?php if ($current_page < $total_pages): ?>
                   <a href="<?php echo esc_url(add_query_arg('bk_page', $current_page + 1, $base_url)); ?>" class="bk-page-btn">
                       Next &raquo;
                   </a>
               <?php endif; ?>
               
               <div class="bk-page-info">
                   Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>
                   (<?php echo $total_bookings; ?> total bookings)
               </div>
           </div>
       <?php endif; ?>
       
       <style>
       .bk-status-badge {
           display: inline-block;
           padding: 4px 12px;
           border-radius: 12px;
           font-size: 12px;
           font-weight: 600;
       }
       .bk-status-badge.status-pending {
           background: #fef3c7;
           color: #92400e;
       }
       .bk-status-badge.status-confirmed,
       .bk-status-badge.status-completed {
           background: #d1fae5;
           color: #065f46;
       }
       .bk-status-badge.status-cancelled {
           background: #fee2e2;
           color: #991b1b;
       }
       .bk-payment-badge {
           display: inline-block;
           padding: 4px 12px;
           border-radius: 12px;
           font-size: 12px;
           font-weight: 600;
       }
       .bk-payment-badge.payment-unpaid {
           background: #fef3c7;
           color: #92400e;
       }
       .bk-payment-badge.payment-verified,
       .bk-payment-badge.payment-paid {
           background: #d1fae5;
           color: #065f46;
       }
       
       /* Pagination styles */
       .bk-pagination {
           display: flex;
           align-items: center;
           gap: 10px;
           margin-top: 20px;
           padding: 15px;
           background: #f9fafb;
           border-radius: 8px;
           flex-wrap: wrap;
       }
       .bk-page-btn {
           padding: 8px 16px;
           background: #fff;
           border: 1px solid #d1d5db;
           border-radius: 6px;
           text-decoration: none;
           color: #374151;
           font-weight: 500;
           transition: all 0.2s;
       }
       .bk-page-btn:hover {
           background: #f3f4f6;
           border-color: #9ca3af;
       }
       .bk-page-numbers {
           display: flex;
           gap: 5px;
           flex: 1;
           justify-content: center;
       }
       .bk-page-num {
           padding: 8px 12px;
           background: #fff;
           border: 1px solid #d1d5db;
           border-radius: 6px;
           text-decoration: none;
           color: #374151;
           min-width: 40px;
           text-align: center;
           transition: all 0.2s;
       }
       .bk-page-num:hover {
           background: #f3f4f6;
           border-color: #9ca3af;
       }
       .bk-page-num.active {
           background: #3b82f6;
           color: #fff;
           border-color: #3b82f6;
           font-weight: 600;
       }
       .bk-page-dots {
           padding: 8px 4px;
           color: #6b7280;
       }
       .bk-page-info {
           color: #6b7280;
           font-size: 14px;
           margin-left: auto;
       }
       
       @media (max-width: 768px) {
           .bk-pagination {
               justify-content: center;
           }
           .bk-page-info {
               width: 100%;
               text-align: center;
               margin-left: 0;
               margin-top: 10px;
           }
       }
       </style>
       <?php
       return ob_get_clean();
   }