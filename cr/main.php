<?php
/**
 * Module Name: Car Rental Booking
 * Module Slug: cr
 * Description: Manage Car rental packages and bookings
 * Version: 1.0.0
 * Author: BNTM
 * Icon: 🚗
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Module constants
define('BNTM_CR_PATH', dirname(__FILE__) . '/');
define('BNTM_CR_URL', plugin_dir_url(__FILE__));

// ============================================================================
// MODULE CONFIGURATION FUNCTIONS
// ============================================================================

function bntm_cr_get_pages() {
    return [
        'Car Rental Booking Dashboard' => '[car_rental_booking_dashboard]',
        'Car Book Now' => '[car_rental_booking_form]',
        'Car Book Now Embed' => '[car_rental_booking_form_embed]',
        'Car Booking Invoice' => '[car_rental_booking_invoice]', // Add this
    ];
}

function bntm_cr_get_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $prefix = $wpdb->prefix;
    
    return [
        // Car Inventory - Store database of cars with specifications
        'cr_car_inventory' => "CREATE TABLE {$prefix}cr_car_inventory (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            car_make VARCHAR(100) NOT NULL,
            car_model VARCHAR(100) NOT NULL,
            plate_number VARCHAR(20) UNIQUE NOT NULL,
            year INT NOT NULL,
            city VARCHAR(50) NOT NULL DEFAULT 'cebu',
            vehicle_type VARCHAR(50) NOT NULL,
            specifications JSON DEFAULT NULL,
            transmission VARCHAR(20) DEFAULT 'automatic',
            fuel_type VARCHAR(20) DEFAULT 'diesel',
            seating_capacity INT DEFAULT 5,
            num_cars INT DEFAULT 1,
            photo_url VARCHAR(500) DEFAULT NULL,
            status VARCHAR(20) DEFAULT 'available',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_city (city),
            INDEX idx_vehicle_type (vehicle_type),
            INDEX idx_status (status)
        ) {$charset};",
        
        // Commercial Rates - City-based fixed duration rates with airport surcharge
        'cr_commercial_rates' => "CREATE TABLE {$prefix}cr_commercial_rates (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            city VARCHAR(50) NOT NULL,
            vehicle_type VARCHAR(50) NOT NULL,
            car_id BIGINT UNSIGNED DEFAULT NULL,
            duration_hours DECIMAL(10,2) NOT NULL,
            flat_rate DECIMAL(10,2) NOT NULL,
            airport_surcharge DECIMAL(10,2) DEFAULT 0,
            status VARCHAR(20) DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_city (city),
            INDEX idx_vehicle_type (vehicle_type),
            INDEX idx_car (car_id)
        ) {$charset};",
        
        // Self-Drive Rates - 24-hour duration based rates
        'cr_self_drive_rates' => "CREATE TABLE {$prefix}cr_self_drive_rates (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            city VARCHAR(50) NOT NULL,
            car_id BIGINT UNSIGNED DEFAULT NULL,
            vehicle_type VARCHAR(50) NOT NULL,
            rate_per_24hours DECIMAL(10,2) NOT NULL,
            exceeding_rate DECIMAL(10,2) NOT NULL,
            security_deposit DECIMAL(10,2) DEFAULT 0,
            status VARCHAR(20) DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_city (city),
            INDEX idx_vehicle_type (vehicle_type),
            INDEX idx_car (car_id)
        ) {$charset};",
        
        // Out of Town Rates - Location/KM based rates with duration
        'cr_out_of_town_rates' => "CREATE TABLE {$prefix}cr_out_of_town_rates (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            city VARCHAR(50) NOT NULL,
            location_name VARCHAR(255) NOT NULL,
            km_distance DECIMAL(10,2) NOT NULL,
            vehicle_type VARCHAR(50) NOT NULL,
            car_id BIGINT UNSIGNED DEFAULT NULL,
            rate_per_trip DECIMAL(10,2) NOT NULL,
            exceeding_rate_per_hour DECIMAL(10,2) NOT NULL,
            status VARCHAR(20) DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_city (city),
            INDEX idx_vehicle_type (vehicle_type),
            INDEX idx_car (car_id)
        ) {$charset};",
        
        'cr_packages' => "CREATE TABLE {$prefix}cr_packages (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            package_name VARCHAR(255) NOT NULL,
            city VARCHAR(50) NOT NULL DEFAULT 'cebu',
            vehicle_category VARCHAR(50) NOT NULL DEFAULT 'car',
            boat_type VARCHAR(100) NOT NULL,
            daily_rate DECIMAL(10,2) NOT NULL,
            hourly_surcharge DECIMAL(10,2) DEFAULT 0,
            max_pax INT NOT NULL,
            photo_url VARCHAR(500) DEFAULT NULL,
            description TEXT,
            status VARCHAR(20) DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_status (status)
        ) {$charset};",
        
        'cr_bookings' => "CREATE TABLE {$prefix}cr_bookings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            package_id BIGINT UNSIGNED NOT NULL,
            car_id BIGINT UNSIGNED DEFAULT NULL,
            city VARCHAR(50) NOT NULL DEFAULT 'cebu',
            rental_type VARCHAR(50) NOT NULL DEFAULT 'commercial',
            vehicle_category VARCHAR(50) NOT NULL DEFAULT 'car',
            customer_name VARCHAR(255) NOT NULL,
            customer_email VARCHAR(255) NOT NULL,
            customer_phone VARCHAR(50) NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            check_in_time DATETIME NULL,
            check_out_time DATETIME NULL,
            base_point VARCHAR(255) DEFAULT '',
            destination VARCHAR(255) DEFAULT '',
            location_name VARCHAR(255) DEFAULT '',
            distance_km DECIMAL(10,2) DEFAULT 0,
            total_hours DECIMAL(10,2) DEFAULT 0,
            number_of_days INT NOT NULL,
            number_of_pax INT NOT NULL,
            base_fee DECIMAL(10,2) DEFAULT 0,
            rate_per_km DECIMAL(10,2) DEFAULT 0,
            overtime_rate DECIMAL(10,2) DEFAULT 0,
            daily_rate DECIMAL(10,2) NOT NULL,
            package_amount DECIMAL(10,2) NOT NULL,
            excess_hours DECIMAL(10,2) DEFAULT 0,
            surcharge_amount DECIMAL(10,2) DEFAULT 0,
            airport_surcharge DECIMAL(10,2) DEFAULT 0,
            pricing_type VARCHAR(20) DEFAULT 'formula',
            other_fees JSON DEFAULT NULL,
            total_amount DECIMAL(10,2) NOT NULL,
            status VARCHAR(20) DEFAULT 'contacted',
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_status (status),
            INDEX idx_date (start_date),
            INDEX idx_rental_type (rental_type)
        ) {$charset};"
    ];
}


// Then add this new shortcode handler
function bntm_shortcode_cr_form_embed() {
    // Force remove headers for clean iframe embed
    if (!defined('IFRAME_REQUEST')) {
        define('IFRAME_REQUEST', true);
    }
    
    // Same form but without any wrapper
    return bntm_shortcode_cr_form();
}

// Update shortcodes array
function bntm_cr_get_shortcodes() {
    return [
        'car_rental_booking_dashboard' => 'bntm_shortcode_cr_dashboard',
        'car_rental_booking_form' => 'bntm_shortcode_cr_form',
        'car_rental_booking_form_embed' => 'bntm_shortcode_cr_form_embed',
        'car_rental_booking_invoice' => 'bntm_shortcode_cr_invoice', // Add this
    ];
}
function bntm_cr_create_tables() {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $tables = bntm_cr_get_tables();
    foreach ($tables as $sql) {
        dbDelta($sql);
    }
    bntm_cr_seed_defaults();
    return count($tables);
}

function bntm_cr_get_city_choices() {
    return [
        'cebu' => 'Cebu',
        'cdo'  => 'CDO',
    ];
}

function bntm_cr_get_vehicle_category_labels() {
    $labels = [];
    foreach (bntm_cr_get_pricing_rules() as $slug => $rule) {
        $labels[$slug] = $rule['label'] ?? ucfirst(str_replace('_', ' ', $slug));
    }
    return $labels;
}

function bntm_cr_get_default_pricing_rules() {
    return [
        'car' => [
            'label' => 'Car',
            'base_fee' => 2500,
            'rate_per_km' => 12,
            'overtime_rate' => 150,
        ],
        'van_innova' => [
            'label' => 'Van / Innova',
            'base_fee' => 2800,
            'rate_per_km' => 15,
            'overtime_rate' => 250,
        ],
        'suv_grandia' => [
            'label' => 'SUV / Grandia',
            'base_fee' => 3200,
            'rate_per_km' => 18,
            'overtime_rate' => 300,
        ],
    ];
}

function bntm_cr_get_default_vehicle_category_slug() {
    $rules = bntm_cr_get_pricing_rules();
    $keys = array_keys($rules);
    return !empty($keys) ? $keys[0] : 'car';
}

function bntm_cr_get_default_routes() {
    return [
        [
            'city' => 'cebu',
            'base_point' => 'Mactan',
            'destination' => 'Simala',
            'distance_km' => 114,
            'fixed_rates' => ['car' => 0, 'van_innova' => 0, 'suv_grandia' => 0],
        ],
        [
            'city' => 'cebu',
            'base_point' => 'Mactan',
            'destination' => 'Oslob',
            'distance_km' => 127,
            'fixed_rates' => ['car' => 0, 'van_innova' => 0, 'suv_grandia' => 0],
        ],
        [
            'city' => 'cebu',
            'base_point' => 'Cebu City',
            'destination' => 'Temple of Leah',
            'distance_km' => 12,
            'fixed_rates' => ['car' => 0, 'van_innova' => 0, 'suv_grandia' => 0],
        ],
        [
            'city' => 'cdo',
            'base_point' => 'Cagayan de Oro City',
            'destination' => 'Dahilayan',
            'distance_km' => 42,
            'fixed_rates' => ['car' => 0, 'van_innova' => 0, 'suv_grandia' => 0],
        ],
        [
            'city' => 'cdo',
            'base_point' => 'Cagayan de Oro City',
            'destination' => 'Camiguin Port',
            'distance_km' => 91,
            'fixed_rates' => ['car' => 0, 'van_innova' => 0, 'suv_grandia' => 0],
        ],
    ];
}

function bntm_cr_seed_defaults() {
    if (!bntm_get_setting('cr_pricing_rules')) {
        bntm_set_setting('cr_pricing_rules', wp_json_encode(bntm_cr_get_default_pricing_rules()));
    }
    if (!bntm_get_setting('cr_route_points')) {
        bntm_set_setting('cr_route_points', wp_json_encode(bntm_cr_get_default_routes()));
    }
}

// ============================================================================
// CAR INVENTORY & VEHICLE TYPE FUNCTIONS
// ============================================================================

function bntm_cr_get_vehicle_types() {
    return [
        'sedan' => 'Sedan',
        'suv' => 'SUV',
        'van' => 'Van',
        'innova' => 'Innova',
        'mpv' => 'MPV',
        'pickup' => 'Pickup',
        'truck' => 'Truck',
    ];
}

function bntm_cr_get_rental_types() {
    return [
        'commercial' => 'Commercial (City Drive)',
        'self_drive' => 'Self-Drive (24 Hours)',
        'out_of_town' => 'Out of Town',
    ];
}

function bntm_cr_parse_specs_text($text) {
    $specs = [];
    $lines = preg_split('/\r\n|\r|\n/', (string) $text);

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        if (str_contains($line, ':')) {
            [$key, $value] = array_map('trim', explode(':', $line, 2));
            $key = sanitize_key($key);
            if ($key !== '') {
                $specs[$key] = sanitize_text_field($value);
            }
            continue;
        }

        $specs[] = sanitize_text_field($line);
    }

    return $specs;
}

function bntm_cr_get_cars_by_city($city = 'cebu', $status = 'available', $vehicle_type = '') {
    global $wpdb;
    $prefix = $wpdb->prefix;

    $sql = "SELECT * FROM {$prefix}cr_car_inventory WHERE city = %s AND status = %s";
    $params = [$city, $status];

    if ($vehicle_type !== '') {
        $sql .= " AND vehicle_type = %s";
        $params[] = $vehicle_type;
    }

    $sql .= " ORDER BY vehicle_type, car_make, car_model";

    $results = $wpdb->get_results($wpdb->prepare($sql, $params));
    
    return $results ?: [];
}

function bntm_cr_get_car_details($car_id) {
    global $wpdb;
    $prefix = $wpdb->prefix;
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$prefix}cr_car_inventory WHERE id = %d",
        $car_id
    ));
}

function bntm_cr_get_commercial_rate($city, $vehicle_type, $duration_hours = null, $car_id = null) {
    global $wpdb;
    $prefix = $wpdb->prefix;

    if ($car_id !== null && intval($car_id) > 0) {
        $rate = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}cr_commercial_rates WHERE city = %s AND vehicle_type = %s AND car_id = %d AND duration_hours = %s AND status = 'active' LIMIT 1",
            $city,
            $vehicle_type,
            intval($car_id),
            $duration_hours
        ));

        if ($rate) {
            return $rate;
        }
    }

    if ($duration_hours !== null && $duration_hours !== '') {
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}cr_commercial_rates WHERE city = %s AND vehicle_type = %s AND duration_hours = %s AND status = 'active' LIMIT 1",
            $city,
            $vehicle_type,
            $duration_hours
        ));
    }

    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$prefix}cr_commercial_rates WHERE city = %s AND vehicle_type = %s AND status = 'active' ORDER BY duration_hours ASC LIMIT 1",
        $city,
        $vehicle_type
    ));
}

function bntm_cr_get_commercial_rates($city, $vehicle_type) {
    global $wpdb;
    $prefix = $wpdb->prefix;

    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$prefix}cr_commercial_rates WHERE city = %s AND vehicle_type = %s AND status = 'active' ORDER BY duration_hours ASC",
        $city,
        $vehicle_type
    ));

    return $results ?: [];
}

function bntm_cr_get_self_drive_rate($city, $vehicle_type, $car_id = null) {
    global $wpdb;
    $prefix = $wpdb->prefix;

    if ($car_id !== null && intval($car_id) > 0) {
        $rate = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}cr_self_drive_rates WHERE car_id = %d AND status = 'active' LIMIT 1",
            intval($car_id)
        ));
        if ($rate) {
            return $rate;
        }
    }
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$prefix}cr_self_drive_rates WHERE city = %s AND vehicle_type = %s AND status = 'active' ORDER BY car_id IS NULL, car_id ASC LIMIT 1",
        $city,
        $vehicle_type
    ));
}

function bntm_cr_get_self_drive_rate_by_car($car_id) {
    global $wpdb;
    $prefix = $wpdb->prefix;

    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$prefix}cr_self_drive_rates WHERE car_id = %d AND status = 'active' LIMIT 1",
        $car_id
    ));
}

function bntm_cr_get_out_of_town_rate($city, $location, $vehicle_type, $car_id = null) {
    global $wpdb;
    $prefix = $wpdb->prefix;

    if ($car_id !== null && intval($car_id) > 0) {
        $rate = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}cr_out_of_town_rates WHERE city = %s AND location_name = %s AND vehicle_type = %s AND car_id = %d AND status = 'active' LIMIT 1",
            $city,
            $location,
            $vehicle_type,
            intval($car_id)
        ));

        if ($rate) {
            return $rate;
        }
    }

    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$prefix}cr_out_of_town_rates WHERE city = %s AND location_name = %s AND vehicle_type = %s AND status = 'active'",
        $city,
        $location,
        $vehicle_type
    ));
}

function bntm_cr_get_out_of_town_locations($city) {
    global $wpdb;
    $prefix = $wpdb->prefix;
    
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT location_name, km_distance FROM {$prefix}cr_out_of_town_rates WHERE city = %s AND status = 'active' ORDER BY location_name",
        $city
    ));
    
    return $results ?: [];
}

function bntm_cr_calculate_commercial_price($hours, $city, $vehicle_type, $from_airport = false, $car_id = null) {
    $duration_hours = max(0, floatval($hours));
    $rate = bntm_cr_get_commercial_rate($city, $vehicle_type, $duration_hours, $car_id);
    
    if (!$rate) {
        return null;
    }

    $base_price = floatval($rate->flat_rate);
    $airport_surcharge = $from_airport ? $rate->airport_surcharge : 0;
    $total = $base_price + $airport_surcharge;
    
    return [
        'duration_hours' => floatval($rate->duration_hours),
        'hours' => $duration_hours,
        'base_price' => $base_price,
        'airport_surcharge' => $airport_surcharge,
        'total' => $total,
        'flat_rate' => $base_price,
    ];
}

function bntm_cr_calculate_self_drive_price($num_days, $city, $vehicle_type, $additional_hours = 0, $car_id = null) {
    $rate = bntm_cr_get_self_drive_rate($city, $vehicle_type, $car_id);
    
    if (!$rate) {
        return null;
    }
    
    $base_price = $rate->rate_per_24hours * $num_days;
    $exceeding_charge = $rate->exceeding_rate * $additional_hours;
    $security_deposit = $rate->security_deposit;
    $total = $base_price + $exceeding_charge + $security_deposit;
    
    return [
        'rate_per_24hours' => $rate->rate_per_24hours,
        'num_days' => $num_days,
        'base_price' => $base_price,
        'additional_hours' => $additional_hours,
        'exceeding_charge' => $exceeding_charge,
        'security_deposit' => $security_deposit,
        'total' => $total,
    ];
}

function bntm_cr_calculate_out_of_town_price($location, $city, $vehicle_type, $hours_used, $car_id = null) {
    $rate = bntm_cr_get_out_of_town_rate($city, $location, $vehicle_type, $car_id);
    
    if (!$rate) {
        return null;
    }
    
    $trip_price = $rate->rate_per_trip;
    $excess_hours = max(0, $hours_used - 10);
    $exceeding_charge = $rate->exceeding_rate_per_hour * $excess_hours;
    $total = $trip_price + $exceeding_charge;
    
    return [
        'location' => $location,
        'km_distance' => $rate->km_distance,
        'trip_price' => $trip_price,
        'hours_used' => $hours_used,
        'excess_hours' => $excess_hours,
        'exceeding_charge' => $exceeding_charge,
        'total' => $total,
        'exceeding_rate_per_hour' => $rate->exceeding_rate_per_hour,
    ];
}

function bntm_cr_get_pricing_rules() {
    $defaults = bntm_cr_get_default_pricing_rules();
    $saved = json_decode((string) bntm_get_setting('cr_pricing_rules', ''), true);
    if (!is_array($saved)) {
        return $defaults;
    }

    $normalized = [];
    foreach ($saved as $slug => $saved_rule) {
        if (!is_array($saved_rule)) {
            continue;
        }

        $slug = sanitize_key(is_string($slug) ? $slug : ($saved_rule['slug'] ?? ''));
        if ($slug === '') {
            continue;
        }

        $default_rule = $defaults[$slug] ?? [
            'label' => $saved_rule['label'] ?? ucfirst(str_replace('_', ' ', $slug)),
            'base_fee' => 0,
            'rate_per_km' => 0,
            'overtime_rate' => 0,
        ];

        $normalized[$slug] = [
            'label' => sanitize_text_field($saved_rule['label'] ?? $default_rule['label']),
            'base_fee' => floatval($saved_rule['base_fee'] ?? $default_rule['base_fee']),
            'rate_per_km' => floatval($saved_rule['rate_per_km'] ?? $default_rule['rate_per_km']),
            'overtime_rate' => floatval($saved_rule['overtime_rate'] ?? $default_rule['overtime_rate']),
        ];
    }

    return !empty($normalized) ? $normalized : $defaults;
}

function bntm_cr_normalize_city($city) {
    $city = strtolower(trim((string) $city));
    return array_key_exists($city, bntm_cr_get_city_choices()) ? $city : 'cebu';
}

function bntm_cr_parse_route_definitions($text) {
    $routes = [];
    $lines = preg_split('/\r\n|\r|\n/', (string) $text);

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        $parts = array_map('trim', explode('|', $line));
        if (count($parts) < 4) {
            continue;
        }

        $routes[] = [
            'city' => bntm_cr_normalize_city($parts[0]),
            'base_point' => sanitize_text_field($parts[1]),
            'destination' => sanitize_text_field($parts[2]),
            'distance_km' => floatval($parts[3]),
            'fixed_rates' => [
                'car' => floatval($parts[4] ?? 0),
                'van_innova' => floatval($parts[5] ?? 0),
                'suv_grandia' => floatval($parts[6] ?? 0),
            ],
        ];
    }

    return $routes;
}

function bntm_cr_routes_to_text($routes) {
    $lines = [];
    if (!is_array($routes)) {
        return '';
    }

    foreach ($routes as $route) {
        $fixed = is_array($route['fixed_rates'] ?? null) ? $route['fixed_rates'] : [];
        $lines[] = implode('|', [
            $route['city'] ?? 'cebu',
            $route['base_point'] ?? '',
            $route['destination'] ?? '',
            floatval($route['distance_km'] ?? 0),
            floatval($fixed['car'] ?? 0),
            floatval($fixed['van_innova'] ?? 0),
            floatval($fixed['suv_grandia'] ?? 0),
        ]);
    }

    return implode("\n", $lines);
}

function bntm_cr_get_routes() {
    $saved = json_decode((string) bntm_get_setting('cr_route_points', ''), true);
    if (!is_array($saved) || empty($saved)) {
        return bntm_cr_get_default_routes();
    }

    $routes = [];
    foreach ($saved as $route) {
        if (!is_array($route)) {
            continue;
        }
        $routes[] = [
            'city' => bntm_cr_normalize_city($route['city'] ?? 'cebu'),
            'base_point' => sanitize_text_field($route['base_point'] ?? ''),
            'destination' => sanitize_text_field($route['destination'] ?? ''),
            'distance_km' => floatval($route['distance_km'] ?? 0),
            'fixed_rates' => [
                'car' => floatval($route['fixed_rates']['car'] ?? 0),
                'van_innova' => floatval($route['fixed_rates']['van_innova'] ?? 0),
                'suv_grandia' => floatval($route['fixed_rates']['suv_grandia'] ?? 0),
            ],
        ];
    }

    return !empty($routes) ? $routes : bntm_cr_get_default_routes();
}

function bntm_cr_find_route($city, $destination, $base_point = '') {
    $city = bntm_cr_normalize_city($city);
    $destination = trim((string) $destination);
    $base_point = trim((string) $base_point);

    foreach (bntm_cr_get_routes() as $route) {
        if ($route['city'] !== $city) {
            continue;
        }
        if (strcasecmp((string) $route['destination'], $destination) !== 0) {
            continue;
        }
        if ($base_point !== '' && strcasecmp((string) $route['base_point'], $base_point) !== 0) {
            continue;
        }
        return $route;
    }

    return null;
}

function bntm_cr_calculate_total($vehicle_category, $distance_km, $total_hours, $route = null) {
    $rules = bntm_cr_get_pricing_rules();
    $vehicle_category = array_key_exists($vehicle_category, $rules) ? $vehicle_category : bntm_cr_get_default_vehicle_category_slug();
    $rule = $rules[$vehicle_category];

    $distance_km = max(0, floatval($distance_km));
    $total_hours = max(0, floatval($total_hours));

    $base_fee = floatval($rule['base_fee']);
    $rate_per_km = floatval($rule['rate_per_km']);
    $overtime_rate = floatval($rule['overtime_rate']);
    $distance_charge = $distance_km * $rate_per_km;
    $base_rate = $base_fee + $distance_charge;
    $overtime_hours = max(0, $total_hours - 10);
    $overtime_charge = $overtime_hours * $overtime_rate;
    $pricing_type = 'formula';
    $fixed_rate = 0;

    if (is_array($route)) {
        $fixed_rates = is_array($route['fixed_rates'] ?? null) ? $route['fixed_rates'] : [];
        $fixed_rate = floatval($fixed_rates[$vehicle_category] ?? 0);
        if ($fixed_rate > 0) {
            $base_rate = $fixed_rate;
            $pricing_type = 'fixed';
        }
    }

    $total_cost = $base_rate + $overtime_charge;

    return [
        'vehicle_category' => $vehicle_category,
        'base_fee' => round($base_fee, 2),
        'rate_per_km' => round($rate_per_km, 2),
        'overtime_rate' => round($overtime_rate, 2),
        'distance_km' => round($distance_km, 2),
        'distance_charge' => round($distance_charge, 2),
        'base_rate' => round($base_rate, 2),
        'total_hours' => round($total_hours, 2),
        'overtime_hours' => round($overtime_hours, 2),
        'overtime_charge' => round($overtime_charge, 2),
        'pricing_type' => $pricing_type,
        'fixed_rate' => round($fixed_rate, 2),
        'total_cost' => round($total_cost, 2),
    ];
}

add_action('init', 'bntm_cr_maybe_upgrade_schema');
function bntm_cr_maybe_upgrade_schema() {
    $schema_version = '2.1.0';
    if (get_option('bntm_cr_schema_version') === $schema_version) {
        return;
    }

    bntm_cr_create_tables();
    update_option('bntm_cr_schema_version', $schema_version, false);
}

// ============================================================================
// AJAX ACTION HOOKS
// ============================================================================

add_action('wp_ajax_cr_add_package', 'bntm_ajax_cr_add_package');
add_action('wp_ajax_cr_update_package', 'bntm_ajax_cr_update_package');


add_action('wp_ajax_cr_update_package_full', 'bntm_ajax_cr_update_package_full');
add_action('wp_ajax_cr_delete_package', 'bntm_ajax_cr_delete_package');
add_action('wp_ajax_cr_update_booking_status', 'bntm_ajax_cr_update_booking_status');
add_action('wp_ajax_cr_delete_booking', 'bntm_ajax_cr_delete_booking');

add_action('wp_ajax_cr_edit_booking', 'bntm_ajax_cr_edit_booking');
add_action('wp_ajax_cr_submit_booking', 'bntm_ajax_cr_submit_booking');
add_action('wp_ajax_nopriv_cr_submit_booking', 'bntm_ajax_cr_submit_booking');

add_action('wp_ajax_cr_save_payment_source', 'bntm_ajax_cr_save_payment_source');
add_action('wp_ajax_cr_add_payment_method', 'bntm_ajax_cr_add_payment_method');
add_action('wp_ajax_cr_remove_payment_method', 'bntm_ajax_cr_remove_payment_method');

add_action('wp_ajax_cr_save_booking_settings', 'bntm_ajax_cr_save_booking_settings');

// New AJAX handlers for rental pricing and locations
add_action('wp_ajax_nopriv_cr_get_out_of_town_locations', 'bntm_ajax_cr_get_out_of_town_locations');
add_action('wp_ajax_cr_get_out_of_town_locations', 'bntm_ajax_cr_get_out_of_town_locations');

add_action('wp_ajax_nopriv_cr_calculate_commercial', 'bntm_ajax_cr_calculate_commercial');
add_action('wp_ajax_cr_calculate_commercial', 'bntm_ajax_cr_calculate_commercial');

add_action('wp_ajax_nopriv_cr_calculate_self_drive', 'bntm_ajax_cr_calculate_self_drive');
add_action('wp_ajax_cr_calculate_self_drive', 'bntm_ajax_cr_calculate_self_drive');

add_action('wp_ajax_nopriv_cr_calculate_out_of_town', 'bntm_ajax_cr_calculate_out_of_town');
add_action('wp_ajax_cr_calculate_out_of_town', 'bntm_ajax_cr_calculate_out_of_town');
add_action('wp_ajax_nopriv_cr_get_inventory_cars', 'bntm_ajax_cr_get_inventory_cars');
add_action('wp_ajax_cr_get_inventory_cars', 'bntm_ajax_cr_get_inventory_cars');

add_action('wp_ajax_cr_save_inventory_car', 'bntm_ajax_cr_save_inventory_car');
add_action('wp_ajax_cr_delete_inventory_car', 'bntm_ajax_cr_delete_inventory_car');

add_action('wp_ajax_cr_save_commercial_rate', 'bntm_ajax_cr_save_commercial_rate');
add_action('wp_ajax_cr_delete_commercial_rate', 'bntm_ajax_cr_delete_commercial_rate');

add_action('wp_ajax_cr_save_self_drive_rate', 'bntm_ajax_cr_save_self_drive_rate');
add_action('wp_ajax_cr_delete_self_drive_rate', 'bntm_ajax_cr_delete_self_drive_rate');

add_action('wp_ajax_cr_save_out_of_town_rate', 'bntm_ajax_cr_save_out_of_town_rate');
add_action('wp_ajax_cr_delete_out_of_town_rate', 'bntm_ajax_cr_delete_out_of_town_rate');

// ============================================================================
// MAIN DASHBOARD SHORTCODE
// ============================================================================

function bntm_shortcode_cr_dashboard() {
    $current_user = wp_get_current_user();
    $is_wp_admin = current_user_can('manage_options');
    $current_role = bntm_get_user_role($current_user->ID);
    
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) {
        return '<p>You do not have permission to access this page.</p>';
    }
    
    $business_id = $current_user->ID;
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
    
    ob_start();
    ?>
      <style>
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
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bntm-cr-container">
        <div class="bntm-tabs">
            <a href="?tab=overview" class="bntm-tab <?php echo $active_tab === 'overview' ? 'active' : ''; ?>">
                Overview
            </a>
            <a href="?tab=fleet" class="bntm-tab <?php echo in_array($active_tab, ['packages', 'fleet'], true) ? 'active' : ''; ?>">
                Cars & Rates
            </a>
            <a href="?tab=bookings" class="bntm-tab <?php echo $active_tab === 'bookings' ? 'active' : ''; ?>">
                Bookings
            </a>
            <a href="?tab=settings" class="bntm-tab <?php echo $active_tab === 'settings' ? 'active' : ''; ?>">
                Settings
            </a>
        </div>
        
        <div class="bntm-tab-content">
            <?php if ($active_tab === 'overview'): ?>
                <?php echo cr_overview_tab($business_id); ?>
            <?php elseif ($active_tab === 'packages' || $active_tab === 'fleet'): ?>
                <?php echo cr_fleet_tab($business_id); ?>
            <?php elseif ($active_tab === 'bookings'): ?>
                <?php echo cr_bookings_tab($business_id); ?>
            <?php elseif ($active_tab === 'settings'): ?>
                <?php echo cr_settings_tab($business_id); ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
    $content = ob_get_clean();
    return bntm_universal_container('Car Rental Booking', $content);
}

// ============================================================================
// TAB FUNCTIONS
// ============================================================================
function cr_fleet_tab($business_id) {
    global $wpdb;

    $inventory_table = $wpdb->prefix . 'cr_car_inventory';
    $commercial_table = $wpdb->prefix . 'cr_commercial_rates';
    $self_drive_table = $wpdb->prefix . 'cr_self_drive_rates';
    $out_of_town_table = $wpdb->prefix . 'cr_out_of_town_rates';

    $city_choices = bntm_cr_get_city_choices();
    $vehicle_types = bntm_cr_get_vehicle_types();
    $inventory = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$inventory_table} WHERE business_id = %d ORDER BY created_at DESC",
        $business_id
    ));
    $commercial_rates = $wpdb->get_results($wpdb->prepare(
        "SELECT r.*, CONCAT(c.car_make, ' ', c.car_model) AS car_name, c.plate_number AS car_plate
         FROM {$commercial_table} r
         LEFT JOIN {$inventory_table} c ON r.car_id = c.id
         WHERE r.business_id = %d ORDER BY r.city, r.vehicle_type, car_name",
        $business_id
    ));
    $self_drive_rates = $wpdb->get_results($wpdb->prepare(
        "SELECT r.*, CONCAT(c.car_make, ' ', c.car_model) AS car_name, c.plate_number AS car_plate
         FROM {$self_drive_table} r
         LEFT JOIN {$inventory_table} c ON r.car_id = c.id
         WHERE r.business_id = %d ORDER BY r.city, r.vehicle_type, car_name",
        $business_id
    ));
    $out_of_town_rates = $wpdb->get_results($wpdb->prepare(
        "SELECT r.*, CONCAT(c.car_make, ' ', c.car_model) AS car_name, c.plate_number AS car_plate
         FROM {$out_of_town_table} r
         LEFT JOIN {$inventory_table} c ON r.car_id = c.id
         WHERE r.business_id = %d ORDER BY r.city, r.location_name, r.vehicle_type, car_name",
        $business_id
    ));

    $nonce = wp_create_nonce('cr_fleet_nonce');

    ob_start();
    ?>
    <style>
    .cr-fleet-tabs {display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px;}
    .cr-fleet-tab-btn {padding:10px 14px;border:1px solid #d1d5db;background:#fff;border-radius:999px;cursor:pointer;font-weight:600;}
    .cr-fleet-tab-btn.active {background:var(--bntm-primary);color:#fff;border-color:var(--bntm-primary);}
    .cr-fleet-panel {display:none;}
    .cr-fleet-panel.active {display:block;}
    .cr-spec-note {font-size:12px;color:#6b7280;}
    </style>

    <div class="bntm-form-section">
        <h3>Cars & Rates</h3>
        <p style="color:#6b7280;margin-top:-6px;">Manage inventory and the rate tables used by the booking wizard.</p>

        <div class="cr-fleet-tabs">
            <button type="button" class="cr-fleet-tab-btn active" data-panel="inventory">Inventory</button>
            <button type="button" class="cr-fleet-tab-btn" data-panel="commercial">Commercial</button>
            <button type="button" class="cr-fleet-tab-btn" data-panel="self_drive">Self-Drive</button>
            <button type="button" class="cr-fleet-tab-btn" data-panel="out_of_town">Out of Town</button>
        </div>

        <div class="cr-fleet-panel active" data-panel="inventory">
            <form id="cr-inventory-form" class="bntm-form" enctype="multipart/form-data">
                <input type="hidden" name="id" value="">
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Car Make *</label>
                        <input type="text" name="car_make" required placeholder="Toyota">
                    </div>
                    <div class="bntm-form-group">
                        <label>Car Model *</label>
                        <input type="text" name="car_model" required placeholder="Vios">
                    </div>
                </div>
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Plate Number *</label>
                        <input type="text" name="plate_number" required placeholder="ABC-1234">
                    </div>
                    <div class="bntm-form-group">
                        <label>Year *</label>
                        <input type="number" name="year" required min="1990" max="<?php echo esc_attr(date('Y') + 1); ?>" placeholder="2024">
                    </div>
                </div>
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>City *</label>
                        <select name="city" required>
                            <?php foreach ($city_choices as $city_key => $city_label): ?>
                                <option value="<?php echo esc_attr($city_key); ?>"><?php echo esc_html($city_label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="bntm-form-group">
                        <label>Vehicle Type *</label>
                        <select name="vehicle_type" required>
                            <?php foreach ($vehicle_types as $type_key => $type_label): ?>
                                <option value="<?php echo esc_attr($type_key); ?>"><?php echo esc_html($type_label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Seating Capacity</label>
                        <input type="number" name="seating_capacity" min="1" value="5">
                    </div>
                    <div class="bntm-form-group">
                        <label>Number of Cars</label>
                        <input type="number" name="num_cars" min="1" value="1">
                    </div>
                </div>
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Transmission</label>
                        <input type="text" name="transmission" value="automatic">
                    </div>
                    <div class="bntm-form-group">
                        <label>Fuel Type</label>
                        <input type="text" name="fuel_type" value="diesel">
                    </div>
                </div>
                <div class="bntm-form-group">
                    <label>Specifications</label>
                    <textarea name="specifications" rows="4" placeholder="AC: Yes&#10;Color: White&#10;GPS: Included"></textarea>
                    <small class="cr-spec-note">Use `key: value` on each line so the details can be stored as JSON.</small>
                </div>
                <div class="bntm-form-group">
                    <label>Photo</label>
                    <input type="file" name="car_photo" accept="image/*">
                </div>
                <div class="bntm-form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="available">Available</option>
                        <option value="inactive">Inactive</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </div>
                <button type="submit" class="bntm-btn-primary">Save Car</button>
            </form>

            <table class="bntm-table" style="margin-top:20px;">
                <thead>
                    <tr>
                        <th>Car</th>
                        <th>City</th>
                        <th>Type</th>
                        <th>Capacity / Specs</th>
                        <th>Fleet</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($inventory)): ?>
                        <tr><td colspan="7" style="text-align:center;">No cars saved yet.</td></tr>
                    <?php else: foreach ($inventory as $car): ?>
                        <tr>
                            <td><?php echo esc_html(trim($car->car_make . ' ' . $car->car_model)); ?><br><small><?php echo esc_html($car->plate_number); ?></small></td>
                            <td><?php echo esc_html($city_choices[bntm_cr_normalize_city($car->city ?? 'cebu')] ?? 'Cebu'); ?></td>
                            <td><?php echo esc_html($vehicle_types[$car->vehicle_type ?? 'sedan'] ?? $car->vehicle_type); ?></td>
                            <td>
                                <?php echo esc_html($car->seating_capacity); ?> seats
                                <?php if (!empty($car->specifications)): ?>
                                    <br><small style="color:#6b7280;"><?php echo esc_html(substr(wp_strip_all_tags((string) $car->specifications), 0, 50)); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($car->num_cars); ?></td>
                            <td><?php echo esc_html(ucfirst($car->status)); ?></td>
                            <td>
                                <button type="button" class="bntm-btn-small bntm-btn-danger cr-delete-inventory" data-id="<?php echo esc_attr($car->id); ?>" data-nonce="<?php echo esc_attr($nonce); ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php
        $rate_sections = [
            'commercial' => [
                'title' => 'Commercial Rates',
                'table' => $commercial_rates,
                'columns' => ['Duration', 'Flat Rate', 'Airport Surcharge'],
            ],
            'self_drive' => [
                'title' => 'Self-Drive Rates',
                'table' => $self_drive_rates,
                'columns' => ['Rate / 24 Hours', 'Exceeding Rate', 'Security Deposit'],
            ],
            'out_of_town' => [
                'title' => 'Out of Town Rates',
                'table' => $out_of_town_rates,
                'columns' => ['Location', 'KM', 'Trip Rate', 'Exceeding / Hour'],
            ],
        ];

        foreach ($rate_sections as $panel_key => $section):
        ?>
        <div class="cr-fleet-panel" data-panel="<?php echo esc_attr($panel_key); ?>">
            <form id="cr-<?php echo esc_attr($panel_key); ?>-form" class="bntm-form">
                <input type="hidden" name="id" value="">
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>City *</label>
                        <select name="city" required>
                            <?php foreach ($city_choices as $city_key => $city_label): ?>
                                <option value="<?php echo esc_attr($city_key); ?>"><?php echo esc_html($city_label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="bntm-form-group">
                        <label>Vehicle Type *</label>
                        <select name="vehicle_type" required>
                            <?php foreach ($vehicle_types as $type_key => $type_label): ?>
                                <option value="<?php echo esc_attr($type_key); ?>"><?php echo esc_html($type_label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <?php if ($panel_key === 'commercial'): ?>
                    <div class="bntm-form-row">
                        <div class="bntm-form-group"><label>Duration (Hours) *</label><input type="number" name="duration_hours" min="1" step="0.5" required placeholder="e.g., 3"></div>
                        <div class="bntm-form-group"><label>Flat Rate *</label><input type="number" name="flat_rate" min="0" step="0.01" required placeholder="e.g., 1500"></div>
                    </div>
                    <div class="bntm-form-group">
                        <label>Assigned Vehicle *</label>
                        <select name="car_id" required>
                            <option value="">Select inventory vehicle</option>
                            <?php foreach ($inventory as $car): ?>
                                <option
                                    value="<?php echo esc_attr($car->id); ?>"
                                    data-city="<?php echo esc_attr($car->city ?? 'cebu'); ?>"
                                    data-vehicle-type="<?php echo esc_attr($car->vehicle_type ?? 'sedan'); ?>">
                                    <?php echo esc_html(trim($car->car_make . ' ' . $car->car_model)); ?> — <?php echo esc_html($car->plate_number); ?> (<?php echo esc_html($city_choices[bntm_cr_normalize_city($car->city ?? 'cebu')] ?? 'Cebu'); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="cr-spec-note">This car will be tied to the commercial rate and used in the booking flow.</small>
                    </div>
                    <div class="bntm-form-group"><label>Airport Surcharge</label><input type="number" name="airport_surcharge" min="0" step="0.01" value="0"></div>
                <?php elseif ($panel_key === 'self_drive'): ?>
                    <div class="bntm-form-row">
                        <div class="bntm-form-group"><label>Rate / 24 Hours *</label><input type="number" name="rate_per_24hours" min="0" step="0.01" required></div>
                        <div class="bntm-form-group"><label>Security Deposit</label><input type="number" name="security_deposit" min="0" step="0.01" value="0"></div>
                    </div>
                    <div class="bntm-form-group">
                        <label>Assigned Vehicle *</label>
                        <select name="car_id" class="cr-self-drive-car-select" required>
                            <option value="">Select inventory vehicle</option>
                            <?php foreach ($inventory as $car): ?>
                                <option
                                    value="<?php echo esc_attr($car->id); ?>"
                                    data-city="<?php echo esc_attr($car->city ?? 'cebu'); ?>"
                                    data-vehicle-type="<?php echo esc_attr($car->vehicle_type ?? 'sedan'); ?>">
                                    <?php echo esc_html(trim($car->car_make . ' ' . $car->car_model)); ?> — <?php echo esc_html($car->plate_number); ?> (<?php echo esc_html($city_choices[bntm_cr_normalize_city($car->city ?? 'cebu')] ?? 'Cebu'); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="cr-spec-note">Pick the exact car that should be available for this self-drive rate.</small>
                    </div>
                    <div class="bntm-form-group"><label>Exceeding Rate / Hour *</label><input type="number" name="exceeding_rate" min="0" step="0.01" required></div>
                <?php else: ?>
                    <div class="bntm-form-row">
                        <div class="bntm-form-group"><label>Location Name *</label><input type="text" name="location_name" required></div>
                        <div class="bntm-form-group"><label>KM Distance *</label><input type="number" name="km_distance" min="0" step="0.01" required></div>
                    </div>
                    <div class="bntm-form-group">
                        <label>Assigned Vehicle *</label>
                        <select name="car_id" required>
                            <option value="">Select inventory vehicle</option>
                            <?php foreach ($inventory as $car): ?>
                                <option
                                    value="<?php echo esc_attr($car->id); ?>"
                                    data-city="<?php echo esc_attr($car->city ?? 'cebu'); ?>"
                                    data-vehicle-type="<?php echo esc_attr($car->vehicle_type ?? 'sedan'); ?>">
                                    <?php echo esc_html(trim($car->car_make . ' ' . $car->car_model)); ?> — <?php echo esc_html($car->plate_number); ?> (<?php echo esc_html($city_choices[bntm_cr_normalize_city($car->city ?? 'cebu')] ?? 'Cebu'); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="cr-spec-note">This car will be tied to the out-of-town rate and booking flow.</small>
                    </div>
                    <div class="bntm-form-row">
                        <div class="bntm-form-group"><label>Trip Rate *</label><input type="number" name="rate_per_trip" min="0" step="0.01" required></div>
                        <div class="bntm-form-group"><label>Exceeding Rate / Hour *</label><input type="number" name="exceeding_rate_per_hour" min="0" step="0.01" required></div>
                    </div>
                <?php endif; ?>

                <div class="bntm-form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <button type="submit" class="bntm-btn-primary">Save <?php echo esc_html($section['title']); ?></button>
            </form>

            <table class="bntm-table" style="margin-top:20px;">
                <thead>
                    <tr>
                        <th>City</th>
                        <th>Vehicle</th>
                        <?php if ($panel_key === 'commercial'): ?>
                            <th>Duration</th><th>Flat</th><th>Airport</th><th>Assigned Car</th>
                        <?php elseif ($panel_key === 'self_drive'): ?>
                            <th>Vehicle</th><th>24 Hours</th><th>Exceeding</th><th>Deposit</th>
                        <?php else: ?>
                            <th>Location</th><th>KM</th><th>Trip</th><th>Exceeding</th><th>Assigned Car</th>
                        <?php endif; ?>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($section['table'])): ?>
                        <tr><td colspan="<?php echo $panel_key === 'commercial' ? 8 : ($panel_key === 'self_drive' ? 8 : 9); ?>" style="text-align:center;">No rates saved yet.</td></tr>
                    <?php else: foreach ($section['table'] as $rate): ?>
                        <tr>
                            <td><?php echo esc_html($city_choices[bntm_cr_normalize_city($rate->city ?? 'cebu')] ?? 'Cebu'); ?></td>
                            <td><?php echo esc_html($vehicle_types[$rate->vehicle_type ?? 'sedan'] ?? $rate->vehicle_type); ?></td>
                            <?php if ($panel_key === 'commercial'): ?>
                                <td><?php echo esc_html($rate->duration_hours); ?> hrs</td>
                                <td>₱<?php echo number_format($rate->flat_rate, 2); ?></td>
                                <td>₱<?php echo number_format($rate->airport_surcharge, 2); ?></td>
                                <td>
                                    <?php echo esc_html($rate->car_name ?: 'Any vehicle'); ?><br>
                                    <small style="color:#6b7280;"><?php echo esc_html($rate->car_plate ?: 'No assigned car'); ?></small>
                                </td>
                            <?php elseif ($panel_key === 'self_drive'): ?>
                                <td>
                                    <?php echo esc_html($rate->car_name ?: 'Any vehicle'); ?><br>
                                    <small style="color:#6b7280;"><?php echo esc_html($rate->car_plate ?: 'No assigned car'); ?></small>
                                </td>
                                <td>₱<?php echo number_format($rate->rate_per_24hours, 2); ?></td>
                                <td>₱<?php echo number_format($rate->exceeding_rate, 2); ?></td>
                                <td>₱<?php echo number_format($rate->security_deposit, 2); ?></td>
                            <?php else: ?>
                                <td><?php echo esc_html($rate->location_name); ?></td>
                                <td><?php echo number_format($rate->km_distance, 2); ?></td>
                                <td>₱<?php echo number_format($rate->rate_per_trip, 2); ?></td>
                                <td>₱<?php echo number_format($rate->exceeding_rate_per_hour, 2); ?></td>
                                <td>
                                    <?php echo esc_html($rate->car_name ?: 'Any vehicle'); ?><br>
                                    <small style="color:#6b7280;"><?php echo esc_html($rate->car_plate ?: 'No assigned car'); ?></small>
                                </td>
                            <?php endif; ?>
                            <td><?php echo esc_html(ucfirst($rate->status)); ?></td>
                            <td>
                                <button type="button" class="bntm-btn-small bntm-btn-danger cr-delete-rate" data-panel="<?php echo esc_attr($panel_key); ?>" data-id="<?php echo esc_attr($rate->id); ?>" data-nonce="<?php echo esc_attr($nonce); ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>
    </div>

    <script>
    (function() {
        const buttons = document.querySelectorAll('.cr-fleet-tab-btn');
        const panels = document.querySelectorAll('.cr-fleet-panel');
        const selfDriveForm = document.getElementById('cr-self_drive-form');

        function showPanel(panelName) {
            buttons.forEach(btn => btn.classList.toggle('active', btn.dataset.panel === panelName));
            panels.forEach(panel => panel.classList.toggle('active', panel.dataset.panel === panelName));
        }

        buttons.forEach(btn => btn.addEventListener('click', () => showPanel(btn.dataset.panel)));

        function saveForm(formId, action, successText) {
            const form = document.getElementById(formId);
            if (!form) return;

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const fd = new FormData(this);
                fd.append('action', action);
                fd.append('nonce', '<?php echo esc_js($nonce); ?>');
                fetch(ajaxurl, {method:'POST', body:fd})
                    .then(r => r.json())
                    .then(json => {
                        alert((json.success ? successText : 'Failed: ') + (json.data?.message || json.data || 'Unknown error'));
                        if (json.success) location.reload();
                    });
            });
        }

        saveForm('cr-inventory-form', 'cr_save_inventory_car', 'Car saved: ');
        saveForm('cr-commercial-form', 'cr_save_commercial_rate', 'Commercial rate saved: ');
        saveForm('cr-self_drive-form', 'cr_save_self_drive_rate', 'Self-drive rate saved: ');
        saveForm('cr-out_of_town-form', 'cr_save_out_of_town_rate', 'Out-of-town rate saved: ');

        function crFilterSelfDriveCars() {
            if (!selfDriveForm) return;

            const city = selfDriveForm.querySelector('select[name="city"]')?.value || '';
            const vehicleType = selfDriveForm.querySelector('select[name="vehicle_type"]')?.value || '';
            const carSelect = selfDriveForm.querySelector('select[name="car_id"]');
            if (!carSelect) return;

            let firstVisible = null;
            carSelect.querySelectorAll('option').forEach((opt, index) => {
                if (index === 0) {
                    opt.hidden = false;
                    return;
                }

                const matchCity = !city || (opt.dataset.city || '') === city;
                const matchType = !vehicleType || (opt.dataset.vehicleType || '') === vehicleType;
                const visible = matchCity && matchType;
                opt.hidden = !visible;
                if (visible && !firstVisible) {
                    firstVisible = opt.value;
                }
            });

            if (carSelect.value && carSelect.selectedOptions[0]?.hidden) {
                carSelect.value = '';
            }

            if (!carSelect.value && firstVisible) {
                carSelect.value = firstVisible;
            }
        }

        selfDriveForm?.querySelectorAll('select[name="city"], select[name="vehicle_type"]').forEach(field => {
            field.addEventListener('change', crFilterSelfDriveCars);
        });

        selfDriveForm?.querySelector('select[name="car_id"]')?.addEventListener('change', crFilterSelfDriveCars);
        crFilterSelfDriveCars();

        document.querySelectorAll('.cr-delete-inventory').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Delete this car from inventory?')) return;
                const fd = new FormData();
                fd.append('action', 'cr_delete_inventory_car');
                fd.append('id', this.dataset.id);
                fd.append('nonce', this.dataset.nonce);
                fetch(ajaxurl, {method:'POST', body:fd}).then(r => r.json()).then(json => {
                    alert(json.data?.message || json.data || 'Done');
                    if (json.success) location.reload();
                });
            });
        });

        document.querySelectorAll('.cr-delete-rate').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Delete this rate row?')) return;
                const fd = new FormData();
                fd.append('action', 'cr_delete_' + this.dataset.panel + '_rate');
                fd.append('id', this.dataset.id);
                fd.append('nonce', this.dataset.nonce);
                fetch(ajaxurl, {method:'POST', body:fd}).then(r => r.json()).then(json => {
                    alert(json.data?.message || json.data || 'Done');
                    if (json.success) location.reload();
                });
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

function cr_overview_tab($business_id) {
    global $wpdb;
    $packages_table = $wpdb->prefix . 'cr_packages';
    $bookings_table = $wpdb->prefix . 'cr_bookings';
    
    $total_packages = $wpdb->get_var("SELECT COUNT(*) FROM $packages_table WHERE status='active'");
    $total_bookings = $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table");
    $pending_bookings = $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table WHERE status IN ('contacted','paid')");
    
    // Get all bookings for calendar - include start_date and end_date
    $all_bookings = $wpdb->get_results("
        SELECT b.*, COALESCE(p.package_name, CONCAT(c.car_make, ' ', c.car_model), b.rental_type) AS package_name
        FROM $bookings_table b
        LEFT JOIN $packages_table p ON b.package_id = p.id
        LEFT JOIN {$wpdb->prefix}cr_car_inventory c ON b.car_id = c.id
        ORDER BY b.start_date ASC
    ");
    
    // Get booking form URL
    $booking_page = get_page_by_path('car-book-now-embed');
    $booking_url = $booking_page ? get_permalink($booking_page->ID) : '';
    
    ob_start();
    ?>
    <style>
    .bntm-dashboard-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .bntm-stat-card {
        background: #ffffff;
        padding: 25px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .bntm-stat-card h3 {
        margin: 0 0 10px 0;
        font-size: 14px;
        color: #6b7280;
        font-weight: 500;
    }
    .bntm-stat-number {
        margin: 0;
        font-size: 32px;
        font-weight: 700;
        color: #111827;
    }
    .bntm-calendar-container {
        background: white;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .bntm-calendar {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 5px;
        margin-top: 15px;
    }
    .bntm-calendar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }
    .bntm-calendar-nav {
        display: flex;
        gap: 10px;
    }
    .bntm-calendar-day {
        text-align: center;
        padding: 10px 5px;
        font-size: 12px;
        font-weight: 600;
        color: #6b7280;
        border-bottom: 2px solid #e5e7eb;
    }
    .bntm-calendar-date {
        text-align: center;
        padding: 8px 5px;
        font-size: 14px;
        border-radius: 4px;
        cursor: pointer;
        position: relative;
        min-height: 70px;
        transition: background 0.2s;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: flex-start;
    }
    .bntm-calendar-date:hover {
        background: #f3f4f6;
    }
    .bntm-calendar-date.other-month {
        color: #d1d5db;
    }
    .bntm-calendar-date.today {
        border: 2px solid var(--bntm-primary);
        background: #fef3c7;
    }
    .date-number {
        font-weight: 600;
        margin-bottom: 4px;
    }
    .booking-bars {
        width: 100%;
        display: flex;
        flex-direction: column;
        gap: 2px;
        margin-top: 2px;
    }
    .booking-bar {
        height: 6px;
        border-radius: 3px;
        position: relative;
        cursor: pointer;
        transition: all 0.2s;
    }
    .booking-bar:hover {
        height: 8px;
        opacity: 0.9;
    }
    .booking-bar.start {
        border-top-left-radius: 10px;
        border-bottom-left-radius: 10px;
    }
    .booking-bar.end {
        border-top-right-radius: 10px;
        border-bottom-right-radius: 10px;
    }
    .booking-bar.single {
        border-radius: 10px;
    }
    /* Different colors for different bookings */
    .booking-bar.color-0 { background: #3b82f6; }
    .booking-bar.color-1 { background: #10b981; }
    .booking-bar.color-2 { background: #8b5cf6; }
    .booking-bar.color-3 { background: #f59e0b; }
    .booking-bar.color-4 { background: #ec4899; }
    .booking-bar.color-5 { background: #14b8a6; }
    .booking-bar.color-6 { background: #f97316; }
    .booking-bar.color-7 { background: #06b6d4; }
    .booking-bar.color-8 { background: #84cc16; }
    .booking-bar.color-9 { background: #a855f7; }
    
    /* Faded for cancelled bookings */
    .booking-bar.cancelled {
        opacity: 0.5;
        background: #9ca3af !important;
    }
    
    .bntm-booking-list {
        margin-top: 20px;
        padding: 15px;
        background: #f9fafb;
        border-radius: 8px;
        max-height: 400px;
        overflow-y: auto;
    }
    .bntm-booking-item {
        padding: 12px;
        background: white;
        border-radius: 4px;
        margin-bottom: 8px;
        border-left: 4px solid;
        cursor: pointer;
        transition: transform 0.2s;
    }
    .bntm-booking-item:hover {
        transform: translateX(4px);
    }
    .bntm-booking-item.color-0 { border-left-color: #3b82f6; }
    .bntm-booking-item.color-1 { border-left-color: #10b981; }
    .bntm-booking-item.color-2 { border-left-color: #8b5cf6; }
    .bntm-booking-item.color-3 { border-left-color: #f59e0b; }
    .bntm-booking-item.color-4 { border-left-color: #ec4899; }
    .bntm-booking-item.color-5 { border-left-color: #14b8a6; }
    .bntm-booking-item.color-6 { border-left-color: #f97316; }
    .bntm-booking-item.color-7 { border-left-color: #06b6d4; }
    .bntm-booking-item.color-8 { border-left-color: #84cc16; }
    .bntm-booking-item.color-9 { border-left-color: #a855f7; }
    
    .calendar-legend {
        display: flex;
        gap: 15px;
        margin-top: 15px;
        padding: 10px;
        background: #f9fafb;
        border-radius: 4px;
        font-size: 12px;
        flex-wrap: wrap;
    }
    .legend-item {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .legend-bar {
        width: 30px;
        height: 6px;
        border-radius: 3px;
    }
    .status-badge {
        font-size: 11px;
        padding: 2px 8px;
        background: #f3f4f6;
        border-radius: 3px;
        text-transform: uppercase;
    }
    </style>
    
    <div class="bntm-dashboard-stats">
        <div class="bntm-stat-card">
            <h3>Active Packages</h3>
            <p class="bntm-stat-number"><?php echo $total_packages; ?></p>
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
        
        <div class="calendar-legend">
            <div class="legend-item">
                <div class="legend-bar" style="background: #3b82f6; border-top-left-radius: 10px; border-bottom-left-radius: 10px;"></div>
                <span>Booking Start</span>
            </div>
            <div class="legend-item">
                <div class="legend-bar" style="background: #3b82f6;"></div>
                <span>In Progress</span>
            </div>
            <div class="legend-item">
                <div class="legend-bar" style="background: #3b82f6; border-top-right-radius: 10px; border-bottom-right-radius: 10px;"></div>
                <span>Booking End</span>
            </div>
            <div class="legend-item">
                <div class="legend-bar" style="background: #fef3c7; border: 2px solid var(--bntm-primary);"></div>
                <span>Today</span>
            </div>
        </div>
        
        <div class="bntm-calendar" id="calendar"></div>
        <div id="selected-bookings" class="bntm-booking-list" style="display: none;"></div>
    </div>
    
    <div class="bntm-form-section">
        <h3>Booking Form Embed Code</h3>
        <p>Copy and paste this code to embed the booking form on any page:</p>
        <textarea readonly onclick="this.select()" style="width: 100%; height: 100px; font-family: monospace; padding: 10px; background: #f9fafb; border: 1px solid #d1d5db; border-radius: 4px;"><?php echo esc_html('<iframe src="' . $booking_url . '" width="100%" height="800" frameborder="0"></iframe>'); ?></textarea>
        <button onclick="navigator.clipboard.writeText(this.previousElementSibling.value); alert('Copied!');" class="bntm-btn-primary" style="margin-top: 10px;">
            Copy Code
        </button>
    </div>
    
    <div class="bntm-form-section">
        <h3>Direct Booking Link</h3>
        <p>Share this link with customers:</p>
        <div style="display: flex; gap: 10px;">
            <input type="text" readonly value="<?php echo esc_attr($booking_url); ?>" style="flex: 1; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
            <button onclick="navigator.clipboard.writeText('<?php echo esc_js($booking_url); ?>'); alert('Link copied!');" class="bntm-btn-primary">
                Copy Link
            </button>
        </div>
    </div>
    
    <script>
    const bookingsData = <?php echo json_encode($all_bookings); ?>;
    let currentDate = new Date();
    
    // Assign unique colors to bookings
    const bookingColors = {};
    bookingsData.forEach((booking, index) => {
        bookingColors[booking.id] = index % 10; // Cycle through 10 colors
    });
    
    // Function to get all dates between start and end
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
        
        const monthNames = ["January", "February", "March", "April", "May", "June",
            "July", "August", "September", "October", "November", "December"];
        
        document.getElementById('current-month').textContent = `${monthNames[month]} ${year}`;
        
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const daysInPrevMonth = new Date(year, month, 0).getDate();
        
        // Group bookings by date with position info
        const bookingsByDate = {};
        
        bookingsData.forEach(booking => {
            if (!booking.start_date || !booking.end_date) return;
            
            const startDate = booking.start_date;
            const endDate = booking.end_date;
            const dateRange = getDateRange(startDate, endDate);
            
            dateRange.forEach((date) => {
                if (!bookingsByDate[date]) {
                    bookingsByDate[date] = [];
                }
                
                const position = date === startDate ? 'start' : 
                               date === endDate ? 'end' : 'middle';
                const isSingle = startDate === endDate;
                
                bookingsByDate[date].push({
                    ...booking,
                    position: isSingle ? 'single' : position,
                    colorIndex: bookingColors[booking.id]
                });
            });
        });
        
        let html = '';
        
        // Day headers
        const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        days.forEach(day => {
            html += `<div class="bntm-calendar-day">${day}</div>`;
        });
        
        // Previous month days
        for (let i = firstDay - 1; i >= 0; i--) {
            html += `<div class="bntm-calendar-date other-month">
                <span class="date-number">${daysInPrevMonth - i}</span>
            </div>`;
        }
        
        // Current month days
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
                    const isCancelled = booking.status === 'cancelled';
                    const colorClass = isCancelled ? '' : `color-${booking.colorIndex}`;
                    const cancelledClass = isCancelled ? 'cancelled' : '';
                    barsHtml += `<div class="booking-bar ${booking.position} ${colorClass} ${cancelledClass}" 
                                     data-booking-id="${booking.id}" 
                                     data-date="${dateStr}"
                                     title="${booking.customer_name} - ${booking.package_name}"></div>`;
                });
                barsHtml += '</div>';
            }
            
            html += `<div class="${classes}" data-date="${dateStr}">
                <span class="date-number">${day}</span>
                ${barsHtml}
            </div>`;
        }
        
        // Next month days
        const remainingCells = 42 - (firstDay + daysInMonth);
        for (let i = 1; i <= remainingCells; i++) {
            html += `<div class="bntm-calendar-date other-month">
                <span class="date-number">${i}</span>
            </div>`;
        }
        
        document.getElementById('calendar').innerHTML = html;
        
        // Add click events to booking bars
        document.querySelectorAll('.booking-bar').forEach(el => {
            el.addEventListener('click', function(e) {
                e.stopPropagation();
                const bookingId = parseInt(this.dataset.bookingId);
                const date = this.dataset.date;
                showBooking(bookingId, date);
            });
        });
        
        // Add click events to calendar dates
        document.querySelectorAll('.bntm-calendar-date').forEach(el => {
            el.addEventListener('click', function() {
                const date = this.dataset.date;
                if (date) {
                    showBookings(date);
                }
            });
        });
    }
    
    function showBooking(bookingId, highlightDate) {
        const booking = bookingsData.find(b => b.id === bookingId);
        if (!booking) return;
        
        showBookings(highlightDate, bookingId);
    }
    
    function showBookings(date, highlightBookingId = null) {
        const bookings = bookingsData.filter(b => {
            if (!b.start_date || !b.end_date) return false;
            const dateRange = getDateRange(b.start_date, b.end_date);
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
            const startDate = new Date(booking.start_date + 'T00:00:00');
            const endDate = new Date(booking.end_date + 'T00:00:00');
            const isStart = booking.start_date === date;
            const isEnd = booking.end_date === date;
            const colorIndex = bookingColors[booking.id];
            const isHighlighted = highlightBookingId === booking.id;
            
            let dateLabel = '';
            if (isStart && isEnd) {
                dateLabel = '<span style="color: #8b5cf6; font-weight: bold;">⬤ Single Day</span>';
            } else if (isStart) {
                dateLabel = '<span style="color: #10b981; font-weight: bold;">▶ Check-in</span>';
            } else if (isEnd) {
                dateLabel = '<span style="color: #ef4444; font-weight: bold;">⬛ Check-out</span>';
            } else {
                dateLabel = '<span style="color: #3b82f6; font-weight: bold;">━ In Progress</span>';
            }
            
            const statusColors = {
                'contacted': '#f59e0b',
                'paid': '#3b82f6',
                'booked': '#10b981',
                'checked_in': '#8b5cf6',
                'checked_out': '#6b7280',
                'completed': '#059669',
                'cancelled': '#ef4444'
            };
            
            const statusColor = statusColors[booking.status] || '#6b7280';
            const highlightStyle = isHighlighted ? 'box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3); transform: scale(1.02);' : '';
            
            html += `
                <div class="bntm-booking-item color-${colorIndex}" style="${highlightStyle}">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 5px;">
                        <strong>${booking.customer_name}</strong>
                        ${dateLabel}
                    </div>
                    <div style="margin-bottom: 5px;">
                        ${booking.package_name}
                        <span style="color: #6b7280;"> | ${booking.number_of_pax} pax</span>
                    </div>
                    <div style="font-size: 12px; color: #6b7280; margin-bottom: 5px;">
                        ${startDate.toLocaleDateString('en-US', {month: 'short', day: 'numeric'})} - 
                        ${endDate.toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'})}
                        (${booking.number_of_days} day${booking.number_of_days > 1 ? 's' : ''})
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-weight: bold;">₱${parseFloat(booking.total_amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                        <span class="status-badge" style="background: ${statusColor}; color: white;">
                            ${booking.status.replace('_', ' ')}
                        </span>
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
        container.style.display = 'block';
        
        // Scroll to bookings list
        container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
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

function cr_packages_tab($business_id) {
    global $wpdb;
    $packages_table = $wpdb->prefix . 'cr_packages';
    
    $packages = $wpdb->get_results("SELECT * FROM $packages_table ORDER BY created_at DESC");
    $nonce = wp_create_nonce('cr_package_nonce');
    $city_choices = bntm_cr_get_city_choices();
    $vehicle_categories = bntm_cr_get_pricing_rules();
    $vehicle_labels = bntm_cr_get_vehicle_category_labels();
    
    ob_start();
    ?>
    <style>
    .package-photo-thumb {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: 4px;
    }
    .bntm-modal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        overflow: auto;
    }
    .bntm-modal-content {
        background-color: #fff;
        margin: 50px auto;
        padding: 30px;
        border-radius: 8px;
        width: 90%;
        max-width: 600px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .bntm-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #e5e7eb;
    }
    .bntm-modal-close {
        font-size: 28px;
        font-weight: bold;
        color: #6b7280;
        cursor: pointer;
        border: none;
        background: none;
        padding: 0;
        width: 30px;
        height: 30px;
        line-height: 1;
    }
    .bntm-modal-close:hover {
        color: #000;
    }
    </style>
    
    <!-- Edit Package Modal -->
    <div id="edit-package-modal" class="bntm-modal">
        <div class="bntm-modal-content">
            <div class="bntm-modal-header">
                <h3 style="margin: 0;">Edit Package</h3>
                <button class="bntm-modal-close" onclick="closeEditPackageModal()">&times;</button>
            </div>
            <form id="edit-package-form" class="bntm-form" enctype="multipart/form-data">
                <input type="hidden" name="package_id" id="edit-package-id-input">
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Package Name *</label>
                        <input type="text" name="package_name" id="edit-package-name" required>
                    </div>
                    <div class="bntm-form-group">
                        <label>Vehicle Type / Model *</label>
                        <input type="text" name="boat_type" id="edit-boat-type" required>
                    </div>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>City *</label>
                        <select name="city" id="edit-city" required>
                            <?php foreach ($city_choices as $city_key => $city_label): ?>
                                <option value="<?php echo esc_attr($city_key); ?>"><?php echo esc_html($city_label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="bntm-form-group">
                        <label>Vehicle Category *</label>
                        <select name="vehicle_category" id="edit-vehicle-category" required>
                            <?php foreach ($vehicle_labels as $category_key => $category_label): ?>
                                <option value="<?php echo esc_attr($category_key); ?>"><?php echo esc_html($category_label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Base Price (₱)</label>
                        <input type="number" name="daily_rate" id="edit-daily-rate" required step="0.01" min="0" readonly>
                        <small>Auto-filled from category settings.</small>
                    </div>
                    <div class="bntm-form-group">
                        <label>Overtime Rate Per Hour (₱)</label>
                        <input type="number" name="hourly_surcharge" id="edit-hourly-surcharge" step="0.01" min="0" readonly>
                        <small>Applied after the 10-hour allowance.</small>
                    </div>
                </div>
                
                <div class="bntm-form-group">
                    <label>Max Passengers *</label>
                    <input type="number" name="max_pax" id="edit-max-pax" required min="1">
                </div>
                
                <div class="bntm-form-group">
                    <label>Car Photo</label>
                    <div id="edit-current-photo" style="margin-bottom: 10px;"></div>
                    <input type="file" name="package_photo" accept="image/*" id="edit-package-photo">
                    <small>Upload new photo (leave empty to keep current)</small>
                    <div id="edit-photo-preview" style="margin-top: 10px; display: none;">
                        <img id="edit-preview-image" style="max-width: 200px; border-radius: 8px;">
                    </div>
                </div>
                
                <div class="bntm-form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit-description" rows="3"></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="bntm-btn-primary" style="flex: 1;">Update Package</button>
                    <button type="button" onclick="closeEditPackageModal()" class="bntm-btn-secondary" style="flex: 1;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="bntm-form-section">
        <h3>Add New Package</h3>
        <form id="add-package-form" class="bntm-form" enctype="multipart/form-data">
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>Package Name *</label>
                    <input type="text" name="package_name" required placeholder="e.g., Toyota Vios">
                </div>
                <div class="bntm-form-group">
                    <label>Vehicle Type / Model *</label>
                    <input type="text" name="boat_type" required placeholder="e.g., Sedan">
                </div>
            </div>
            
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>City *</label>
                    <select name="city" id="add-city" required>
                        <?php foreach ($city_choices as $city_key => $city_label): ?>
                            <option value="<?php echo esc_attr($city_key); ?>"><?php echo esc_html($city_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="bntm-form-group">
                    <label>Vehicle Category *</label>
                    <select name="vehicle_category" id="add-vehicle-category" required>
                        <?php foreach ($vehicle_labels as $category_key => $category_label): ?>
                            <option value="<?php echo esc_attr($category_key); ?>"><?php echo esc_html($category_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>Base Price (₱)</label>
                    <input type="number" name="daily_rate" id="add-daily-rate" required step="0.01" min="0" readonly>
                    <small>Auto-filled from category pricing.</small>
                </div>
                <div class="bntm-form-group">
                    <label>Overtime Rate Per Hour (₱)</label>
                    <input type="number" name="hourly_surcharge" id="add-hourly-surcharge" step="0.01" min="0" readonly>
                    <small>Applied after the 10-hour allowance.</small>
                </div>
            </div>
            
            <div class="bntm-form-group">
                <label>Max Passengers *</label>
                <input type="number" name="max_pax" required min="1" placeholder="e.g., 5">
            </div>
            
            <div class="bntm-form-group">
                <label>Car Photo</label>
                <input type="file" name="package_photo" accept="image/*" id="package-photo-input">
                <small>Upload a photo of the car (JPG, PNG, max 2MB)</small>
                <div id="photo-preview" style="margin-top: 10px; display: none;">
                    <img id="preview-image" style="max-width: 200px; border-radius: 8px;">
                </div>
            </div>
            
            <div class="bntm-form-group">
                <label>Description</label>
                <textarea name="description" rows="3" placeholder="Car features and details"></textarea>
            </div>
            
            <button type="submit" class="bntm-btn-primary">Add Package</button>
        </form>
        <div id="package-message"></div>
    </div>
    
    <div class="bntm-form-section">
        <h3>Existing Packages</h3>
        <table class="bntm-table">
            <thead>
                <tr>
                    <th>Photo</th>
                    <th>Package Name</th>
                    <th>City</th>
                    <th>Category</th>
                    <th>Vehicle Type</th>
                    <th>Base Price</th>
                    <th>Overtime Rate</th>
                    <th>Max Pax</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($packages)): ?>
                <tr><td colspan="10" style="text-align:center;">No packages yet</td></tr>
                <?php else: foreach ($packages as $pkg): ?>
                <tr>
                    <td>
                        <?php if ($pkg->photo_url): ?>
                            <img src="<?php echo esc_url($pkg->photo_url); ?>" class="package-photo-thumb" alt="Car photo">
                        <?php else: ?>
                            <div style="width: 60px; height: 60px; background: #f3f4f6; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 24px;">🚗</div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($pkg->package_name); ?></td>
                    <td><?php echo esc_html($city_choices[bntm_cr_normalize_city($pkg->city ?? 'cebu')] ?? 'Cebu'); ?></td>
                    <td><?php echo esc_html($vehicle_labels[$pkg->vehicle_category ?? 'car'] ?? 'Car'); ?></td>
                    <td><?php echo esc_html($pkg->boat_type); ?></td>
                    <td>₱<?php echo number_format($pkg->daily_rate, 2); ?></td>
                    <td>₱<?php echo number_format($pkg->hourly_surcharge, 2); ?>/hr</td>
                    <td><?php echo $pkg->max_pax; ?> pax</td>
                    <td>
                        <span class="bntm-badge bntm-badge-<?php echo $pkg->status; ?>">
                            <?php echo ucfirst($pkg->status); ?>
                        </span>
                    </td>
                    <td>
                        <button class="bntm-btn-small edit-package-btn" 
                                data-id="<?php echo $pkg->id; ?>"
                                data-name="<?php echo esc_attr($pkg->package_name); ?>"
                                data-city="<?php echo esc_attr($pkg->city ?? 'cebu'); ?>"
                                data-category="<?php echo esc_attr($pkg->vehicle_category ?? 'car'); ?>"
                                data-type="<?php echo esc_attr($pkg->boat_type); ?>"
                                data-rate="<?php echo $pkg->daily_rate; ?>"
                                data-surcharge="<?php echo $pkg->hourly_surcharge; ?>"
                                data-pax="<?php echo $pkg->max_pax; ?>"
                                data-photo="<?php echo esc_attr($pkg->photo_url); ?>"
                                data-description="<?php echo esc_attr($pkg->description); ?>"
                                data-status="<?php echo $pkg->status; ?>">
                            Edit
                        </button>
                        <button class="bntm-btn-small toggle-status-btn" data-id="<?php echo $pkg->id; ?>" data-status="<?php echo $pkg->status; ?>" data-nonce="<?php echo $nonce; ?>">
                            <?php echo $pkg->status === 'active' ? 'Deactivate' : 'Activate'; ?>
                        </button>
                        <button class="bntm-btn-small bntm-btn-danger delete-package-btn" data-id="<?php echo $pkg->id; ?>" data-nonce="<?php echo $nonce; ?>">
                            Delete
                        </button>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    
    <script>
    (function() {
        const vehicleCategories = <?php echo wp_json_encode($vehicle_categories); ?>;

        function syncPackagePricing(categoryFieldId, rateFieldId, overtimeFieldId) {
            const select = document.getElementById(categoryFieldId);
            const rateField = document.getElementById(rateFieldId);
            const overtimeField = document.getElementById(overtimeFieldId);
            if (!select || !rateField || !overtimeField) return;

            const rule = vehicleCategories[select.value] || vehicleCategories.car;
            rateField.value = parseFloat(rule.base_fee || 0).toFixed(2);
            overtimeField.value = parseFloat(rule.overtime_rate || 0).toFixed(2);
        }

        document.getElementById('add-vehicle-category').addEventListener('change', function() {
            syncPackagePricing('add-vehicle-category', 'add-daily-rate', 'add-hourly-surcharge');
        });

        document.getElementById('edit-vehicle-category').addEventListener('change', function() {
            syncPackagePricing('edit-vehicle-category', 'edit-daily-rate', 'edit-hourly-surcharge');
        });

        syncPackagePricing('add-vehicle-category', 'add-daily-rate', 'add-hourly-surcharge');

        // Photo preview for add form
        document.getElementById('package-photo-input').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview-image').src = e.target.result;
                    document.getElementById('photo-preview').style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });
        
        // Photo preview for edit form
        document.getElementById('edit-package-photo').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('edit-preview-image').src = e.target.result;
                    document.getElementById('edit-photo-preview').style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });
        
        // Edit package button
        document.querySelectorAll('.edit-package-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.getElementById('edit-package-id-input').value = this.dataset.id;
                document.getElementById('edit-package-name').value = this.dataset.name;
                document.getElementById('edit-city').value = this.dataset.city || 'cebu';
                document.getElementById('edit-vehicle-category').value = this.dataset.category || 'car';
                document.getElementById('edit-boat-type').value = this.dataset.type;
                syncPackagePricing('edit-vehicle-category', 'edit-daily-rate', 'edit-hourly-surcharge');
                document.getElementById('edit-max-pax').value = this.dataset.pax;
                document.getElementById('edit-description').value = this.dataset.description;
                
                // Show current photo
                const currentPhotoDiv = document.getElementById('edit-current-photo');
                if (this.dataset.photo) {
                    currentPhotoDiv.innerHTML = `
                        <div style="margin-bottom: 10px;">
                            <strong>Current Photo:</strong><br>
                            <img src="${this.dataset.photo}" style="max-width: 200px; border-radius: 8px; margin-top: 5px;">
                        </div>
                    `;
                } else {
                    currentPhotoDiv.innerHTML = '<p style="color: #6b7280;">No photo uploaded</p>';
                }
                
                document.getElementById('edit-photo-preview').style.display = 'none';
                document.getElementById('edit-package-modal').style.display = 'block';
            });
        });
        
        // Submit edit form
        document.getElementById('edit-package-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'cr_update_package_full');
            formData.append('nonce', '<?php echo $nonce; ?>');
            
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Updating...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    alert('Package updated successfully!');
                    location.reload();
                } else {
                    alert(json.data.message);
                    btn.disabled = false;
                    btn.textContent = 'Update Package';
                }
            });
        });
        
        // Add package
        document.getElementById('add-package-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'cr_add_package');
            formData.append('nonce', '<?php echo $nonce; ?>');
            
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Adding...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                document.getElementById('package-message').innerHTML = 
                    '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + 
                    json.data.message + '</div>';
                if (json.success) {
                    setTimeout(() => location.reload(), 1000);
                } else {
                    btn.disabled = false;
                    btn.textContent = 'Add Package';
                }
            });
        });
        
        // Toggle status
        document.querySelectorAll('.toggle-status-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const newStatus = this.dataset.status === 'active' ? 'inactive' : 'active';
                const formData = new FormData();
                formData.append('action', 'cr_update_package');
                formData.append('package_id', this.dataset.id);
                formData.append('status', newStatus);
                formData.append('nonce', this.dataset.nonce);
                
                fetch(ajaxurl, {method: 'POST', body: formData})
                .then(r => r.json())
                .then(json => {
                    if (json.success) location.reload();
                    else alert(json.data.message);
                });
            });
        });
        
        // Delete package
        document.querySelectorAll('.delete-package-btn').forEach(btn => {
            btn.addEventListener('click', function() {  
                if (!confirm('Delete this package?')) return;
                
                const formData = new FormData();
                formData.append('action', 'cr_delete_package');
                formData.append('package_id', this.dataset.id);
                formData.append('nonce', this.dataset.nonce);
                
                fetch(ajaxurl, {method: 'POST', body: formData})
                .then(r => r.json())
                .then(json => {
                    if (json.success) location.reload();
                    else alert(json.data.message);
                });
            });
        });
    })();
    
    function closeEditPackageModal() {
        document.getElementById('edit-package-modal').style.display = 'none';
    }
    
    window.onclick = function(event) {
        const modal = document.getElementById('edit-package-modal');
        if (event.target == modal) {
            closeEditPackageModal();
        }
    }
    </script>
    <?php
    return ob_get_clean();
}

function cr_bookings_tab($business_id) {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'cr_bookings';
    $packages_table = $wpdb->prefix . 'cr_packages';

    $filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';

    $where  = 'b.business_id = %d';
    $params = [$business_id];
    if ($filter_status !== 'all') {
        $where   .= ' AND b.status = %s';
        $params[] = $filter_status;
    }

    $bookings = $wpdb->get_results($wpdb->prepare("
        SELECT b.*,
               COALESCE(p.package_name, CONCAT(c.car_make, ' ', c.car_model), b.rental_type) AS package_name,
               COALESCE(p.boat_type, c.vehicle_type, b.vehicle_category) AS boat_type,
               COALESCE(CONCAT(c.car_make, ' ', c.car_model), '') AS car_name,
               p.daily_rate as pkg_daily_rate,
               p.hourly_surcharge as pkg_hourly_surcharge,
               COALESCE(b.package_amount, b.total_amount) as package_amount
        FROM {$bookings_table} b
        LEFT JOIN {$packages_table} p ON b.package_id = p.id
        LEFT JOIN {$wpdb->prefix}cr_car_inventory c ON b.car_id = c.id
        WHERE {$where}
        ORDER BY b.created_at DESC
        LIMIT 50
    ", $params));

    $packages = $wpdb->get_results("SELECT * FROM {$packages_table} WHERE status='active' ORDER BY package_name");
    $nonce    = wp_create_nonce('cr_booking_nonce');

    ob_start();
    ?>
    <style>
    .cr-status-badge{display:inline-block;padding:3px 10px;border-radius:4px;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.3px;}
    .cr-status-contacted  {background:#fefce8;color:#854d0e;border:1px solid #fde68a;}
    .cr-status-paid       {background:#f0fdf4;color:#166534;border:1px solid #86efac;}
    .cr-status-booked     {background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;}
    .cr-status-checked_in {background:#f5f3ff;color:#5b21b6;border:1px solid #ddd6fe;}
    .cr-status-checked_out{background:#fff7ed;color:#9a3412;border:1px solid #fed7aa;}
    .cr-status-cancelled  {background:#f9fafb;color:#6b7280;border:1px solid #d1d5db;}
    .cr-status-completed  {background:#ecfdf5;color:#065f46;border:1px solid #6ee7b7;}
    .cr-filter-select{padding:8px 12px;border:1px solid #d1d5db;border-radius:4px;font-size:14px;}
    </style>

    <!-- ── Edit Modal ─────────────────────────────────────────────────────── -->
    <div id="crEditModal" class="bntm-modal">
        <div class="bntm-modal-content" style="max-width:660px;">
            <div class="bntm-modal-header">
                <h3 style="margin:0;">Edit Booking</h3>
                <button class="bntm-modal-close" onclick="closeModal('crEditModal')">&times;</button>
            </div>

            <div style="max-height:75vh;overflow-y:auto;padding-right:4px;">

                <!-- Package -->
                <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9ca3af;margin:0 0 10px;">Package</p>
                <div class="bntm-form-group" style="margin-bottom:16px;">
                    <label>Package *</label>
                    <select id="cre-package-id" class="bntm-input">
                        <?php foreach ($packages as $pkg): ?>
                        <option value="<?php echo $pkg->id; ?>"
                                data-daily-rate="<?php echo $pkg->daily_rate; ?>"
                                data-hourly-surcharge="<?php echo $pkg->hourly_surcharge; ?>"
                                data-max-pax="<?php echo $pkg->max_pax; ?>">
                            <?php echo esc_html($pkg->package_name); ?> — <?php echo esc_html($pkg->boat_type); ?>
                            (₱<?php echo number_format($pkg->daily_rate, 2); ?>/day)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <hr style="border:none;border-top:1px solid #e5e7eb;margin:0 0 16px;">

                <!-- Customer -->
                <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9ca3af;margin:0 0 10px;">Customer</p>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                    <div class="bntm-form-group" style="margin:0;">
                        <label>Full Name</label>
                        <input type="text" id="cre-customer-name" class="bntm-input">
                    </div>
                    <div class="bntm-form-group" style="margin:0;">
                        <label>Email</label>
                        <input type="email" id="cre-customer-email" class="bntm-input">
                    </div>
                    <div class="bntm-form-group" style="margin:0;">
                        <label>Phone</label>
                        <input type="tel" id="cre-customer-phone" class="bntm-input">
                    </div>
                    <div class="bntm-form-group" style="margin:0;">
                        <label>Number of Pax</label>
                        <input type="number" id="cre-num-pax" class="bntm-input" min="1">
                    </div>
                </div>

                <hr style="border:none;border-top:1px solid #e5e7eb;margin:0 0 16px;">

                <!-- Dates -->
                <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9ca3af;margin:0 0 10px;">Rental Dates</p>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:8px;">
                    <div class="bntm-form-group" style="margin:0;">
                        <label>Start Date</label>
                        <input type="date" id="cre-start-date" class="bntm-input">
                    </div>
                    <div class="bntm-form-group" style="margin:0;">
                        <label>End Date</label>
                        <input type="date" id="cre-end-date" class="bntm-input">
                    </div>
                    <div class="bntm-form-group" style="margin:0;">
                        <label>Number of Days</label>
                        <input type="text" id="cre-num-days" class="bntm-input"
                               readonly style="background:#f9fafb;color:#6b7280;">
                    </div>
                    <div class="bntm-form-group" style="margin:0;"></div>
                    <div class="bntm-form-group" style="margin:0;">
                        <label>Check-in Time</label>
                        <input type="datetime-local" id="cre-checkin" class="bntm-input">
                    </div>
                    <div class="bntm-form-group" style="margin:0;">
                        <label>Check-out Time</label>
                        <input type="datetime-local" id="cre-checkout" class="bntm-input">
                    </div>
                </div>

                <!-- Surcharge display -->
                <div id="cre-surcharge-wrap" style="display:none;margin-bottom:16px;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="bntm-form-group" style="margin:0;">
                            <label>Excess Hours</label>
                            <input type="text" id="cre-excess-hours" class="bntm-input"
                                   readonly style="background:#fefce8;color:#854d0e;">
                        </div>
                        <div class="bntm-form-group" style="margin:0;">
                            <label>Surcharge Amount (₱)</label>
                            <input type="text" id="cre-surcharge-amt" class="bntm-input"
                                   readonly style="background:#fefce8;color:#854d0e;">
                        </div>
                    </div>
                </div>

                <hr style="border:none;border-top:1px solid #e5e7eb;margin:0 0 16px;">

                <!-- Pricing -->
                <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9ca3af;margin:0 0 10px;">Pricing</p>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:8px;">
                    <div class="bntm-form-group" style="margin:0;">
                        <label>Package Amount (₱) <small style="font-weight:400;color:#9ca3af;">rate × days</small></label>
                        <input type="text" id="cre-package-amount" class="bntm-input"
                               readonly style="background:#f9fafb;color:#6b7280;">
                    </div>
                    <div class="bntm-form-group" style="margin:0;">
                        <label>Grand Total (₱)</label>
                        <input type="text" id="cre-grand-total" class="bntm-input"
                               readonly style="background:#f9fafb;color:#166534;font-weight:700;">
                    </div>
                </div>

                <!-- Other Fees -->
                <div style="margin-bottom:16px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                        <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9ca3af;margin:0;">Other Fees</p>
                        <button type="button" onclick="creAddFeeRow()"
                                style="padding:4px 12px;background:var(--bntm-primary);color:#fff;border:none;border-radius:4px;font-size:12px;font-weight:600;cursor:pointer;">
                            + Add Fee
                        </button>
                    </div>
                    <div id="cre-fees-list" style="margin-bottom:8px;"></div>
                    <div style="display:flex;justify-content:flex-end;gap:16px;padding:8px 10px;background:#f9fafb;border-radius:6px;font-size:13px;">
                        <span style="color:#6b7280;">Fees Total:</span>
                        <strong id="cre-fees-total" style="color:var(--bntm-primary);">₱0.00</strong>
                    </div>
                </div>

                <hr style="border:none;border-top:1px solid #e5e7eb;margin:0 0 16px;">

                <!-- Status -->
                <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9ca3af;margin:0 0 10px;">Status</p>
                <div class="bntm-form-group" style="margin-bottom:16px;">
                    <label>Booking Status</label>
                    <select id="cre-status" class="bntm-input">
                        <option value="contacted">Contacted</option>
                        <option value="paid">Paid – Down Payment</option>
                        <option value="booked">Booked</option>
                        <option value="checked_in">Checked In</option>
                        <option value="checked_out">Checked Out</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>

                <hr style="border:none;border-top:1px solid #e5e7eb;margin:0 0 16px;">

                <!-- Notes -->
                <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9ca3af;margin:0 0 10px;">Notes</p>
                <div class="bntm-form-group" style="margin-bottom:16px;">
                    <label>Notes</label>
                    <textarea id="cre-notes" rows="3" class="bntm-input"></textarea>
                </div>

                <div id="cr-edit-msg" style="margin-bottom:10px;"></div>

                <div style="display:flex;gap:10px;">
                    <button type="button" class="bntm-btn-secondary" style="flex:1;"
                            onclick="closeModal('crEditModal')">Cancel</button>
                    <button type="button" class="bntm-btn-primary" style="flex:1;"
                            id="cr-edit-save-btn" onclick="creSave()">Save Changes</button>
                </div>

            </div><!-- scroll wrapper -->
        </div>
    </div>

    <!-- ── Filter + Table ─────────────────────────────────────────────────── -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
        <h3 style="margin:0;">All Bookings</h3>
        <select onchange="window.location.href='?tab=bookings&status='+this.value" class="cr-filter-select">
            <option value="all"         <?php selected($filter_status,'all'); ?>>All Bookings</option>
            <option value="contacted"   <?php selected($filter_status,'contacted'); ?>>Contacted</option>
            <option value="paid"        <?php selected($filter_status,'paid'); ?>>Paid</option>
            <option value="booked"      <?php selected($filter_status,'booked'); ?>>Booked</option>
            <option value="checked_in"  <?php selected($filter_status,'checked_in'); ?>>Checked In</option>
            <option value="checked_out" <?php selected($filter_status,'checked_out'); ?>>Checked Out</option>
            <option value="completed"   <?php selected($filter_status,'completed'); ?>>Completed</option>
            <option value="cancelled"   <?php selected($filter_status,'cancelled'); ?>>Cancelled</option>
        </select>
    </div>

    <?php if (empty($bookings)): ?>
        <div style="text-align:center;padding:40px;color:#6b7280;"><p>No bookings found.</p></div>
    <?php else: ?>
    <table class="bntm-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Customer</th>
                <th>Package</th>
                <th>Rental Period</th>
                <th>Days</th>
                <th>Pax</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($bookings as $b): ?>
            <tr>
                <td>#<?php echo $b->id; ?></td>
                <td>
                    <?php echo esc_html($b->customer_name); ?><br>
                    <small style="color:#6b7280;"><?php echo esc_html($b->customer_email); ?></small>
                </td>
                <td>
                    <?php echo esc_html($b->package_name); ?><br>
                    <small style="color:#6b7280;"><?php echo esc_html($b->boat_type); ?></small>
                    <br><small style="color:#6b7280;"><?php echo esc_html(ucfirst(str_replace('_', ' ', $b->rental_type ?? 'commercial'))); ?></small>
                    <?php if (!empty($b->car_id) && !empty($b->car_name ?? '')): ?>
                        <br><small style="color:#6b7280;"><?php echo esc_html($b->car_name); ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <?php echo date('M d, Y', strtotime($b->start_date)); ?><br>
                    <small style="color:#6b7280;">to <?php echo date('M d, Y', strtotime($b->end_date)); ?></small>
                </td>
                <td><?php echo $b->number_of_days; ?> day<?php echo $b->number_of_days > 1 ? 's' : ''; ?></td>
                <td><?php echo $b->number_of_pax; ?></td>
                <td>₱<?php echo number_format($b->total_amount, 2); ?></td>
                <td>
                    <span class="cr-status-badge cr-status-<?php echo esc_attr($b->status); ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $b->status)); ?>
                    </span>
                </td>
                <td>
                    <a href="<?php echo get_permalink(get_page_by_path('car-booking-invoice')) . '?id=' . $b->rand_id; ?>"
                       class="bntm-btn-small" target="_blank">View</a>
                    <?php if (in_array($b->status, ['contacted', 'paid'], true)): ?>
                    <button class="bntm-btn-small cr-quick-status-btn"
                            data-id="<?php echo $b->id; ?>"
                            data-status="booked"
                            data-label="confirm"
                            data-nonce="<?php echo $nonce; ?>">
                        Confirm
                    </button>
                    <?php endif; ?>
                    <?php if ($b->status !== 'cancelled' && $b->status !== 'completed'): ?>
                    <button class="bntm-btn-small bntm-btn-danger cr-quick-status-btn"
                            data-id="<?php echo $b->id; ?>"
                            data-status="cancelled"
                            data-label="cancel"
                            data-nonce="<?php echo $nonce; ?>">
                        Cancel
                    </button>
                    <?php endif; ?>
                    <button class="bntm-btn-small cr-edit-btn"
                            data-id="<?php echo $b->id; ?>"
                            data-package-id="<?php echo $b->package_id; ?>"
                            data-name="<?php echo esc_attr($b->customer_name); ?>"
                            data-email="<?php echo esc_attr($b->customer_email); ?>"
                            data-phone="<?php echo esc_attr($b->customer_phone); ?>"
                            data-pax="<?php echo $b->number_of_pax; ?>"
                            data-start-date="<?php echo esc_attr($b->start_date); ?>"
                            data-end-date="<?php echo esc_attr($b->end_date); ?>"
                            data-days="<?php echo intval($b->number_of_days); ?>"
                            data-checkin="<?php echo esc_attr($b->check_in_time ?? ''); ?>"
                            data-checkout="<?php echo esc_attr($b->check_out_time ?? ''); ?>"
                            data-excess-hours="<?php echo floatval($b->excess_hours ?? 0); ?>"
                            data-surcharge="<?php echo floatval($b->surcharge_amount ?? 0); ?>"
                            data-daily-rate="<?php echo floatval($b->daily_rate ?? $b->pkg_daily_rate ?? 0); ?>"
                            data-hourly-surcharge="<?php echo floatval($b->pkg_hourly_surcharge ?? 0); ?>"
                            data-package-amount="<?php echo floatval($b->package_amount); ?>"
                            data-total="<?php echo floatval($b->total_amount); ?>"
                            data-status="<?php echo esc_attr($b->status); ?>"
                            data-notes="<?php echo esc_attr($b->notes ?? ''); ?>"
                            data-other-fees='<?php echo esc_attr($b->other_fees ?? '[]'); ?>'
                            data-nonce="<?php echo $nonce; ?>">Edit</button>
                    <button class="bntm-btn-small bntm-btn-danger cr-delete-btn"
                            data-id="<?php echo $b->id; ?>"
                            data-nonce="<?php echo $nonce; ?>">Delete</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <script>
    let creBookingId = null;
    let creNonce     = null;
    let creDailyRate = 0;
    let creHourlySurcharge = 0;

    // ── Open modal ──────────────────────────────────────────────────────────
    document.querySelectorAll('.cr-edit-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const d = this.dataset;
            creBookingId      = d.id;
            creNonce          = d.nonce;
            creDailyRate      = parseFloat(d.dailyRate      ?? 0);
            creHourlySurcharge= parseFloat(d.hourlySurcharge ?? 0);

            // Package
            document.getElementById('cre-package-id').value = d.packageId;

            // Customer
            document.getElementById('cre-customer-name').value  = d.name  ?? '';
            document.getElementById('cre-customer-email').value = d.email ?? '';
            document.getElementById('cre-customer-phone').value = d.phone ?? '';
            document.getElementById('cre-num-pax').value        = d.pax   ?? 1;

            // Dates
            document.getElementById('cre-start-date').value = d.startDate ?? '';
            document.getElementById('cre-end-date').value   = d.endDate   ?? '';
            document.getElementById('cre-num-days').value   = d.days + ' day' + (d.days > 1 ? 's' : '');

            const ci = d.checkin  ?? '';
            const co = d.checkout ?? '';
            document.getElementById('cre-checkin').value  = ci && ci !== '0000-00-00 00:00:00'
                ? ci.replace(' ', 'T').substring(0, 16) : '';
            document.getElementById('cre-checkout').value = co && co !== '0000-00-00 00:00:00'
                ? co.replace(' ', 'T').substring(0, 16) : '';

            // Pricing
            document.getElementById('cre-package-amount').value =
                parseFloat(d.packageAmount ?? 0).toFixed(2);

            // Existing surcharge
            const exH  = parseFloat(d.excessHours ?? 0);
            const exS  = parseFloat(d.surcharge   ?? 0);
            const wrap = document.getElementById('cre-surcharge-wrap');
            if (exH > 0 && exS > 0) {
                document.getElementById('cre-excess-hours').value = exH.toFixed(2) + ' hrs';
                document.getElementById('cre-surcharge-amt').value = exS.toFixed(2);
                wrap.style.display = 'block';
            } else {
                wrap.style.display = 'none';
                document.getElementById('cre-excess-hours').value  = '';
                document.getElementById('cre-surcharge-amt').value = '0';
            }

            // Fees
            document.getElementById('cre-fees-list').innerHTML = '';
            try {
                const fees = JSON.parse(d.otherFees || '[]');
                (Array.isArray(fees) ? fees : []).forEach(f => creAddFeeRow(f.description, f.amount));
            } catch(e) {}

            // Status / notes
            document.getElementById('cre-status').value = d.status ?? 'contacted';
            document.getElementById('cre-notes').value  = d.notes  ?? '';

            // Reset UI
            document.getElementById('cr-edit-msg').innerHTML       = '';
            document.getElementById('cr-edit-save-btn').disabled   = false;
            document.getElementById('cr-edit-save-btn').textContent = 'Save Changes';

            creRecalcTotal();
            openModal('crEditModal');
        });
    });

    // ── Package change ──────────────────────────────────────────────────────
    document.getElementById('cre-package-id').addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        creDailyRate       = parseFloat(opt.dataset.dailyRate       ?? 0);
        creHourlySurcharge = parseFloat(opt.dataset.hourlyS ?? opt.dataset.hourlySurcharge ?? 0);
        creRecalcDays();
    });

    // ── Date changes ────────────────────────────────────────────────────────
    ['cre-start-date','cre-end-date'].forEach(id =>
        document.getElementById(id).addEventListener('change', () => {
            // Enforce min on end date
            const s = document.getElementById('cre-start-date').value;
            const eEl = document.getElementById('cre-end-date');
            if (s) { eEl.min = s; if (eEl.value && eEl.value < s) eEl.value = s; }
            creRecalcDays();
        })
    );

    function creRecalcDays() {
        const s = document.getElementById('cre-start-date').value;
        const e = document.getElementById('cre-end-date').value;
        if (!s || !e) return;
        const diff = Math.ceil((new Date(e) - new Date(s)) / 86400000) + 1;
        const days = Math.max(1, diff);
        document.getElementById('cre-num-days').value = days + ' day' + (days > 1 ? 's' : '');
        document.getElementById('cre-package-amount').value =
            (creDailyRate * days).toFixed(2);
        creRecalcSurcharge();
        creRecalcTotal();
    }

    // ── Surcharge ───────────────────────────────────────────────────────────
    ['cre-checkin','cre-checkout'].forEach(id =>
        document.getElementById(id).addEventListener('change', creRecalcSurcharge)
    );

    function creRecalcSurcharge() {
        const ci   = document.getElementById('cre-checkin').value;
        const co   = document.getElementById('cre-checkout').value;
        const wrap = document.getElementById('cre-surcharge-wrap');

        if (!ci || !co) { wrap.style.display = 'none'; creRecalcTotal(); return; }

        const diffH = (new Date(co) - new Date(ci)) / 3600000;
        // 24 hrs allowed per calendar day
        const daysStr = document.getElementById('cre-num-days').value;
        const days    = parseInt(daysStr) || 1;
        const allowed = 24 * days;
        const excess  = Math.max(0, diffH - allowed);

        if (excess > 0 && creHourlySurcharge > 0) {
            const surcharge = Math.ceil(excess) * creHourlySurcharge;
            document.getElementById('cre-excess-hours').value  = excess.toFixed(2) + ' hrs';
            document.getElementById('cre-surcharge-amt').value = surcharge.toFixed(2);
            wrap.style.display = 'block';
        } else {
            document.getElementById('cre-excess-hours').value  = '';
            document.getElementById('cre-surcharge-amt').value = '0';
            wrap.style.display = 'none';
        }
        creRecalcTotal();
    }

    // ── Fee rows ────────────────────────────────────────────────────────────
    function creAddFeeRow(desc, amt) {
        desc = desc ?? ''; amt = amt ?? '';
        const list = document.getElementById('cre-fees-list');
        const row  = document.createElement('div');
        row.style.cssText = 'display:grid;grid-template-columns:1fr 90px 32px;gap:8px;margin-bottom:8px;align-items:center;';
        row.innerHTML = `
            <input type="text"   class="bntm-input cre-fee-desc" placeholder="Description"
                   value="${String(desc).replace(/"/g,'&quot;')}" style="font-size:13px;padding:7px 9px;">
            <input type="number" class="bntm-input cre-fee-amt"  placeholder="Amount"
                   value="${parseFloat(amt)||''}" min="0" step="0.01" style="font-size:13px;padding:7px 9px;">
            <button type="button"
                    onclick="this.closest('div').remove(); creRecalcTotal();"
                    style="width:32px;height:32px;border:1px solid #fca5a5;background:#fff5f5;color:#ef4444;border-radius:4px;font-size:16px;cursor:pointer;">×</button>
        `;
        row.querySelector('.cre-fee-amt').addEventListener('input', creRecalcTotal);
        list.appendChild(row);
        creRecalcTotal();
    }
    window.creAddFeeRow = creAddFeeRow;

    function creRecalcTotal() {
        let fees = 0;
        document.querySelectorAll('.cre-fee-amt').forEach(i => fees += parseFloat(i.value) || 0);
        document.getElementById('cre-fees-total').textContent =
            '₱' + fees.toLocaleString('en-US', {minimumFractionDigits:2});

        const pkg      = parseFloat(document.getElementById('cre-package-amount').value) || 0;
        const surcharge= parseFloat(document.getElementById('cre-surcharge-amt')?.value) || 0;
        document.getElementById('cre-grand-total').value =
            (pkg + fees + surcharge).toFixed(2);
    }

    // ── Save ────────────────────────────────────────────────────────────────
    function creSave() {
        const btn = document.getElementById('cr-edit-save-btn');
        const msg = document.getElementById('cr-edit-msg');
        btn.disabled = true; btn.textContent = 'Saving…'; msg.innerHTML = '';

        // Collect fees
        const fees = [];
        document.querySelectorAll('#cre-fees-list > div').forEach(row => {
            const desc = row.querySelector('.cre-fee-desc').value.trim();
            const amt  = parseFloat(row.querySelector('.cre-fee-amt').value) || 0;
            if (desc) fees.push({description: desc, amount: amt});
        });

        // Parse days from display string "3 days"
        const daysStr = document.getElementById('cre-num-days').value;
        const days    = parseInt(daysStr) || 1;

        // Parse excess hours
        const exHStr  = document.getElementById('cre-excess-hours').value;
        const exH     = parseFloat(exHStr) || 0;
        const exS     = parseFloat(document.getElementById('cre-surcharge-amt').value) || 0;

        const fd = new FormData();
        fd.append('action',          'cr_edit_booking');
        fd.append('nonce',           creNonce);
        fd.append('booking_id',      creBookingId);
        fd.append('package_id',      document.getElementById('cre-package-id').value);
        fd.append('customer_name',   document.getElementById('cre-customer-name').value.trim());
        fd.append('customer_email',  document.getElementById('cre-customer-email').value.trim());
        fd.append('customer_phone',  document.getElementById('cre-customer-phone').value.trim());
        fd.append('number_of_pax',   document.getElementById('cre-num-pax').value);
        fd.append('start_date',      document.getElementById('cre-start-date').value);
        fd.append('end_date',        document.getElementById('cre-end-date').value);
        fd.append('number_of_days',  days);
        fd.append('check_in_time',   document.getElementById('cre-checkin').value);
        fd.append('check_out_time',  document.getElementById('cre-checkout').value);
        fd.append('excess_hours',    exH);
        fd.append('surcharge_amount',exS);
        fd.append('package_amount',  document.getElementById('cre-package-amount').value);
        fd.append('total_amount',    document.getElementById('cre-grand-total').value);
        fd.append('other_fees',      JSON.stringify(fees));
        fd.append('status',          document.getElementById('cre-status').value);
        fd.append('notes',           document.getElementById('cre-notes').value);

        fetch(ajaxurl, {method:'POST', body:fd})
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    msg.innerHTML = '<div class="bntm-notice bntm-notice-success">' + json.data.message + '</div>';

                    // Update table row inline
                    const row = document.querySelector(`.cr-edit-btn[data-id="${creBookingId}"]`)?.closest('tr');
                    if (row) {
                        const newStatus = document.getElementById('cre-status').value;
                        const labels = {
                            contacted:'Contacted', paid:'Paid', booked:'Booked',
                            checked_in:'Checked In', checked_out:'Checked Out',
                            cancelled:'Cancelled', completed:'Completed'
                        };
                        const badge = row.querySelector('.cr-status-badge');
                        if (badge) {
                            badge.textContent = labels[newStatus] ?? newStatus;
                            badge.className   = 'cr-status-badge cr-status-' + newStatus;
                        }
                        const cells = row.querySelectorAll('td');
                        if (cells[1]) cells[1].innerHTML =
                            document.getElementById('cre-customer-name').value +
                            '<br><small style="color:#6b7280;">' +
                            document.getElementById('cre-customer-email').value + '</small>';
                        if (cells[6]) cells[6].textContent =
                            '₱' + parseFloat(document.getElementById('cre-grand-total').value)
                                    .toLocaleString('en-US', {minimumFractionDigits:2});

                        // Sync data-attrs for re-opening
                        const eb = row.querySelector('.cr-edit-btn');
                        if (eb) {
                            eb.dataset.status        = newStatus;
                            eb.dataset.name          = document.getElementById('cre-customer-name').value;
                            eb.dataset.email         = document.getElementById('cre-customer-email').value;
                            eb.dataset.phone         = document.getElementById('cre-customer-phone').value;
                            eb.dataset.pax           = document.getElementById('cre-num-pax').value;
                            eb.dataset.startDate     = document.getElementById('cre-start-date').value;
                            eb.dataset.endDate       = document.getElementById('cre-end-date').value;
                            eb.dataset.days          = days;
                            eb.dataset.packageAmount = document.getElementById('cre-package-amount').value;
                            eb.dataset.total         = document.getElementById('cre-grand-total').value;
                            eb.dataset.notes         = document.getElementById('cre-notes').value;
                            eb.dataset.otherFees     = JSON.stringify(fees);
                            eb.dataset.excessHours   = exH;
                            eb.dataset.surcharge     = exS;
                        }
                    }
                    btn.disabled = false; btn.textContent = 'Save Changes';
                } else {
                    msg.innerHTML = '<div class="bntm-notice bntm-notice-error">' + (json.data?.message ?? 'Error') + '</div>';
                    btn.disabled = false; btn.textContent = 'Save Changes';
                }
            })
            .catch(() => {
                msg.innerHTML = '<div class="bntm-notice bntm-notice-error">Network error. Please try again.</div>';
                btn.disabled = false; btn.textContent = 'Save Changes';
            });
    }
    window.creSave = creSave;

    // ── Delete ──────────────────────────────────────────────────────────────
    document.querySelectorAll('.cr-delete-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            if (!confirm('Delete this booking? This cannot be undone.')) return;
            const fd = new FormData();
            fd.append('action',     'cr_delete_booking');
            fd.append('booking_id', this.dataset.id);
            fd.append('nonce',      this.dataset.nonce);
            fetch(ajaxurl, {method:'POST', body:fd})
                .then(r => r.json())
                .then(json => { alert(json.data.message); if (json.success) location.reload(); });
        });
    });

    document.querySelectorAll('.cr-quick-status-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const isCancel = this.dataset.status === 'cancelled';
            const actionText = isCancel ? 'cancel' : 'confirm';
            if (!confirm(`Are you sure you want to ${actionText} this booking?`)) return;

            const fd = new FormData();
            fd.append('action', 'cr_update_booking_status');
            fd.append('booking_id', this.dataset.id);
            fd.append('status', this.dataset.status);
            fd.append('nonce', this.dataset.nonce);

            fetch(ajaxurl, {method:'POST', body:fd})
                .then(r => r.json())
                .then(json => {
                    if (json.success) {
                        location.reload();
                    } else {
                        alert(json.data.message || 'Failed to update booking status.');
                    }
                })
                .catch(() => {
                    alert('Network error. Please try again.');
                });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

function cr_settings_tab($business_id) {
    $payment_source = bntm_get_setting('cr_payment_source', 'manual');
    $manual_methods = json_decode(bntm_get_setting('cr_payment_methods', '[]'), true);
    if (!is_array($manual_methods)) $manual_methods = [];
    $pricing_rules = bntm_cr_get_pricing_rules();
    $route_lines = bntm_cr_routes_to_text(bntm_cr_get_routes());
    $routes_data = bntm_cr_get_routes();
    
    $nonce = wp_create_nonce('cr_payment_nonce');
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <h3>Booking Settings</h3>

        <div style="margin-bottom: 20px;">
            <h4 style="margin-bottom: 12px;">Vehicle Pricing Rules</h4>
            <input type="hidden" id="pricing-rules-json" value="">
            <div style="overflow-x:auto;">
                <table class="bntm-table" style="margin-bottom:12px;">
                    <thead>
                        <tr>
                            <th>Slug</th>
                            <th>Label</th>
                            <th>Base Fee</th>
                            <th>Rate / KM</th>
                            <th>Overtime / Hour</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="cr-pricing-rules-body">
                        <?php foreach ($pricing_rules as $rule_slug => $rule): ?>
                        <tr>
                            <td><input type="text" class="cr-pricing-slug" value="<?php echo esc_attr($rule_slug); ?>"></td>
                            <td><input type="text" class="cr-pricing-label" value="<?php echo esc_attr($rule['label']); ?>"></td>
                            <td><input type="number" class="cr-pricing-base-fee" min="0" step="0.01" value="<?php echo esc_attr($rule['base_fee']); ?>"></td>
                            <td><input type="number" class="cr-pricing-rate-km" min="0" step="0.01" value="<?php echo esc_attr($rule['rate_per_km']); ?>"></td>
                            <td><input type="number" class="cr-pricing-overtime" min="0" step="0.01" value="<?php echo esc_attr($rule['overtime_rate']); ?>"></td>
                            <td><button type="button" class="bntm-btn-small bntm-btn-danger cr-remove-pricing-row">Remove</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button type="button" id="cr-add-pricing-row" class="bntm-btn-secondary">Add Pricing Rule</button>
            <small>Add or remove vehicle pricing rules here. Slug should be unique, like <code>sedan</code> or <code>luxury_van</code>.</small>
        </div>

        <div class="bntm-form-group">
            <label>Base Point Routes</label>
            <input type="hidden" id="route-definitions" value="<?php echo esc_attr($route_lines); ?>">
            <div style="overflow-x:auto;">
                <table class="bntm-table" style="margin-bottom:12px;">
                    <thead>
                        <tr>
                            <th>City</th>
                            <th>Base Point</th>
                            <th>Destination</th>
                            <th>KM</th>
                            <th>Car Fixed</th>
                            <th>Van Fixed</th>
                            <th>SUV Fixed</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="cr-routes-table-body">
                        <?php foreach ($routes_data as $route): ?>
                        <tr>
                            <td>
                                <select class="cr-route-city">
                                    <option value="cebu" <?php selected($route['city'], 'cebu'); ?>>Cebu</option>
                                    <option value="cdo" <?php selected($route['city'], 'cdo'); ?>>CDO</option>
                                </select>
                            </td>
                            <td><input type="text" class="cr-route-base" value="<?php echo esc_attr($route['base_point']); ?>"></td>
                            <td><input type="text" class="cr-route-destination" value="<?php echo esc_attr($route['destination']); ?>"></td>
                            <td><input type="number" class="cr-route-distance" min="0" step="0.01" value="<?php echo esc_attr($route['distance_km']); ?>"></td>
                            <td><input type="number" class="cr-route-car-fixed" min="0" step="0.01" value="<?php echo esc_attr($route['fixed_rates']['car'] ?? 0); ?>"></td>
                            <td><input type="number" class="cr-route-van-fixed" min="0" step="0.01" value="<?php echo esc_attr($route['fixed_rates']['van_innova'] ?? 0); ?>"></td>
                            <td><input type="number" class="cr-route-suv-fixed" min="0" step="0.01" value="<?php echo esc_attr($route['fixed_rates']['suv_grandia'] ?? 0); ?>"></td>
                            <td><button type="button" class="bntm-btn-small bntm-btn-danger cr-remove-route-row">Remove</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button type="button" id="cr-add-route-row" class="bntm-btn-secondary">Add Route Row</button>
            <small>Manage routes here instead of typing route lines manually. The KM in this table will be used for pricing.</small>
        </div>
        
        <div class="bntm-form-group">
            <label>Down Payment Percentage (%)</label>
            <input type="number" 
                   id="downpayment-percentage" 
                   min="0" 
                   max="100" 
                   step="1"
                   value="<?php echo esc_attr(bntm_get_setting('cr_downpayment_percentage', '50')); ?>"
                   placeholder="e.g., 50">
            <small>Set the required down payment percentage (0-100%). Leave 0 for full payment only.</small>
        </div>
        
        <div class="bntm-form-group">
            <label>Terms & Conditions</label>
            <textarea id="booking-terms" 
                      rows="8" 
                      placeholder="Enter terms and conditions to display on invoice"><?php echo esc_textarea(bntm_get_setting('cr_terms', '')); ?></textarea>
            <small>These terms will be displayed at the bottom of booking invoices.</small>
        </div>
        
        <button type="button" id="save-booking-settings-btn" class="bntm-btn-primary" data-nonce="<?php echo $nonce; ?>">
            Save Booking Settings
        </button>
        <div id="booking-settings-message"></div>
    </div>
    <div class="bntm-form-section">
        <h3>Payment Configuration</h3>
        
        <div class="bntm-form-group">
            <label>Payment Source</label>
            <select id="payment-source-select">
                <option value="manual" <?php selected($payment_source, 'manual'); ?>>
                    Manual Payment Methods
                </option>
                <?php if (bntm_is_module_enabled('op') && bntm_is_module_visible('op')): ?>
                    <option value="op" <?php selected($payment_source, 'op'); ?>>
                        Online Payment Module (PayPal, PayMaya, etc.)
                    </option>
                <?php else: ?>
                    <option value="op" disabled>
                        Online Payment Module (Requires OP Module)
                    </option>
                <?php endif; ?>
            </select>
        </div>
        
        <button type="button" id="save-payment-source-btn" class="bntm-btn-primary" data-nonce="<?php echo $nonce; ?>">
            Save Payment Source
        </button>
        <div id="payment-source-message"></div>
    </div>
    
    <div class="bntm-form-section" id="manual-payment-section" style="<?php echo $payment_source === 'op' ? 'display: none;' : ''; ?>">
        <h3>Manual Payment Methods</h3>
        
        <div id="payment-methods-list" style="margin-bottom: 20px;">
            <?php if (empty($manual_methods)): ?>
                <p style="color: #6b7280;">No payment methods configured.</p>
            <?php else: ?>
                <?php foreach ($manual_methods as $index => $method): ?>
                    <div style="padding: 15px; background: #f9fafb; border-radius: 8px; margin-bottom: 10px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong><?php echo esc_html($method['name']); ?></strong>
                                <span style="color: #6b7280; margin-left: 10px;">
                                    <?php echo esc_html($method['type']); ?>
                                </span>
                            </div>
                            <button class="bntm-btn-small bntm-btn-danger remove-payment-method" 
                                    data-index="<?php echo $index; ?>" 
                                    data-nonce="<?php echo $nonce; ?>">
                                Remove
                            </button>
                        </div>
                        <?php if (!empty($method['account_name']) || !empty($method['account_number'])): ?>
                            <div style="margin-top: 8px; font-size: 13px; color: #6b7280;">
                                <?php if (!empty($method['account_name'])): ?>
                                    Account: <?php echo esc_html($method['account_name']); ?><br>
                                <?php endif; ?>
                                <?php if (!empty($method['account_number'])): ?>
                                    Number: <?php echo esc_html($method['account_number']); ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div style="padding: 20px; background: #f9fafb; border-radius: 8px;">
            <h4>Add Payment Method</h4>
            <form id="add-payment-method-form" class="bntm-form">
                <div class="bntm-form-group">
                    <label>Payment Type *</label>
                    <select name="payment_type" required>
                        <option value="">Select Type</option>
                        <option value="bank">Bank Transfer</option>
                        <option value="cash">Cash Payment</option>
                        <option value="gcash">GCash</option>
                        <option value="paymaya">PayMaya</option>
                    </select>
                </div>
                
                <div class="bntm-form-group">
                    <label>Display Name *</label>
                    <input type="text" name="payment_name" required placeholder="e.g., BDO Bank Transfer">
                </div>
                
                <div class="bntm-form-group">
                    <label>Account Name</label>
                    <input type="text" name="account_name" placeholder="Account holder name">
                </div>
                
                <div class="bntm-form-group">
                    <label>Account Number</label>
                    <input type="text" name="account_number" placeholder="Account/Phone number">
                </div>
                
                <div class="bntm-form-group">
                    <label>Instructions</label>
                    <textarea name="payment_description" rows="3" placeholder="Payment instructions"></textarea>
                </div>
                
                <button type="submit" class="bntm-btn-primary">Add Payment Method</button>
            </form>
        </div>
        <div id="payment-method-message"></div>
    </div>
    
    <script>
    (function() {
        const paymentSourceSelect = document.getElementById('payment-source-select');
        const manualSection = document.getElementById('manual-payment-section');
        const routesTableBody = document.getElementById('cr-routes-table-body');
        const pricingRulesBody = document.getElementById('cr-pricing-rules-body');

        function crSlugify(value) {
            return String(value || '')
                .toLowerCase()
                .trim()
                .replace(/[^a-z0-9]+/g, '_')
                .replace(/^_+|_+$/g, '');
        }

        function crSyncPricingRules() {
            const rules = {};
            pricingRulesBody.querySelectorAll('tr').forEach(row => {
                const rawSlug = row.querySelector('.cr-pricing-slug')?.value || '';
                const slug = crSlugify(rawSlug);
                const label = row.querySelector('.cr-pricing-label')?.value.trim() || '';
                if (!slug || !label) return;

                row.querySelector('.cr-pricing-slug').value = slug;
                rules[slug] = {
                    label,
                    base_fee: parseFloat(row.querySelector('.cr-pricing-base-fee')?.value || 0),
                    rate_per_km: parseFloat(row.querySelector('.cr-pricing-rate-km')?.value || 0),
                    overtime_rate: parseFloat(row.querySelector('.cr-pricing-overtime')?.value || 0),
                };
            });
            document.getElementById('pricing-rules-json').value = JSON.stringify(rules);
        }

        function crBindPricingRow(row) {
            row.querySelectorAll('input').forEach(field => {
                field.addEventListener('input', crSyncPricingRules);
                field.addEventListener('change', crSyncPricingRules);
            });
            row.querySelector('.cr-remove-pricing-row')?.addEventListener('click', function() {
                row.remove();
                crSyncPricingRules();
            });
        }

        document.getElementById('cr-add-pricing-row').addEventListener('click', function() {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><input type="text" class="cr-pricing-slug" placeholder="vehicle_slug"></td>
                <td><input type="text" class="cr-pricing-label" placeholder="Vehicle Label"></td>
                <td><input type="number" class="cr-pricing-base-fee" min="0" step="0.01" value="0"></td>
                <td><input type="number" class="cr-pricing-rate-km" min="0" step="0.01" value="0"></td>
                <td><input type="number" class="cr-pricing-overtime" min="0" step="0.01" value="0"></td>
                <td><button type="button" class="bntm-btn-small bntm-btn-danger cr-remove-pricing-row">Remove</button></td>
            `;
            pricingRulesBody.appendChild(row);
            crBindPricingRow(row);
            crSyncPricingRules();
        });

        pricingRulesBody.querySelectorAll('tr').forEach(crBindPricingRow);
        crSyncPricingRules();

        function crSyncRouteDefinitions() {
            const lines = [];
            routesTableBody.querySelectorAll('tr').forEach(row => {
                const city = row.querySelector('.cr-route-city')?.value || 'cebu';
                const base = row.querySelector('.cr-route-base')?.value.trim() || '';
                const destination = row.querySelector('.cr-route-destination')?.value.trim() || '';
                const distance = row.querySelector('.cr-route-distance')?.value || 0;
                const carFixed = row.querySelector('.cr-route-car-fixed')?.value || 0;
                const vanFixed = row.querySelector('.cr-route-van-fixed')?.value || 0;
                const suvFixed = row.querySelector('.cr-route-suv-fixed')?.value || 0;

                if (!base || !destination) return;
                lines.push([city, base, destination, distance, carFixed, vanFixed, suvFixed].join('|'));
            });
            document.getElementById('route-definitions').value = lines.join('\n');
        }

        function crBindRouteRow(row) {
            row.querySelectorAll('input, select').forEach(field => {
                field.addEventListener('input', crSyncRouteDefinitions);
                field.addEventListener('change', crSyncRouteDefinitions);
            });
            row.querySelector('.cr-remove-route-row')?.addEventListener('click', function() {
                row.remove();
                crSyncRouteDefinitions();
            });
        }

        document.getElementById('cr-add-route-row').addEventListener('click', function() {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <select class="cr-route-city">
                        <option value="cebu">Cebu</option>
                        <option value="cdo">CDO</option>
                    </select>
                </td>
                <td><input type="text" class="cr-route-base"></td>
                <td><input type="text" class="cr-route-destination"></td>
                <td><input type="number" class="cr-route-distance" min="0" step="0.01" value="0"></td>
                <td><input type="number" class="cr-route-car-fixed" min="0" step="0.01" value="0"></td>
                <td><input type="number" class="cr-route-van-fixed" min="0" step="0.01" value="0"></td>
                <td><input type="number" class="cr-route-suv-fixed" min="0" step="0.01" value="0"></td>
                <td><button type="button" class="bntm-btn-small bntm-btn-danger cr-remove-route-row">Remove</button></td>
            `;
            routesTableBody.appendChild(row);
            crBindRouteRow(row);
            crSyncRouteDefinitions();
        });

        routesTableBody.querySelectorAll('tr').forEach(crBindRouteRow);
        crSyncRouteDefinitions();
        
        paymentSourceSelect.addEventListener('change', function() {
            manualSection.style.display = this.value === 'op' ? 'none' : 'block';
        });
        
        document.getElementById('save-payment-source-btn').addEventListener('click', function() {
            const formData = new FormData();
            formData.append('action', 'cr_save_payment_source');
            formData.append('payment_source', paymentSourceSelect.value);
            formData.append('nonce', this.dataset.nonce);
            
            this.disabled = true;
            this.textContent = 'Saving...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(function(r) { return r.json(); })
            .then(function(json) {
                document.getElementById('payment-source-message').innerHTML = 
                    '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + 
                    json.data.message + '</div>';
                document.getElementById('save-payment-source-btn').disabled = false;
                document.getElementById('save-payment-source-btn').textContent = 'Save Payment Source';
            });
        });
        
        document.getElementById('add-payment-method-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'cr_add_payment_method');
            formData.append('nonce', '<?php echo $nonce; ?>');
            
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Adding...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(function(r) { return r.json(); })
            .then(function(json) {
                if (json.success) {
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    btn.disabled = false;
                    btn.textContent = 'Add Payment Method';
                }
                document.getElementById('payment-method-message').innerHTML = 
                    '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + 
                    json.data.message + '</div>';
            });
        });
        
        document.querySelectorAll('.remove-payment-method').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (!confirm('Remove this payment method?')) return;
                
                const formData = new FormData();
                formData.append('action', 'cr_remove_payment_method');
                formData.append('index', this.dataset.index);
                formData.append('nonce', this.dataset.nonce);
                
                fetch(ajaxurl, {method: 'POST', body: formData})
                .then(function(r) { return r.json(); })
                .then(function(json) {
                    if (json.success) location.reload();
                    else alert(json.data.message);
                });
            });
        });
        document.getElementById('save-booking-settings-btn').addEventListener('click', function() {
            const formData = new FormData();
            formData.append('action', 'cr_save_booking_settings');
            crSyncPricingRules();
            formData.append('pricing_rules_json', document.getElementById('pricing-rules-json').value);
            crSyncRouteDefinitions();
            formData.append('route_definitions', document.getElementById('route-definitions').value);
            formData.append('downpayment_percentage', document.getElementById('downpayment-percentage').value);
            formData.append('terms', document.getElementById('booking-terms').value);
            formData.append('nonce', this.dataset.nonce);
            
            this.disabled = true;
            this.textContent = 'Saving...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(function(r) { return r.json(); })
            .then(function(json) {
                document.getElementById('booking-settings-message').innerHTML = 
                    '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + 
                    json.data.message + '</div>';
                document.getElementById('save-booking-settings-btn').disabled = false;
                document.getElementById('save-booking-settings-btn').textContent = 'Save Booking Settings';
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ============================================================================
// BOOKING FORM SHORTCODE (Public)
// ============================================================================

function bntm_shortcode_cr_form() {
    global $wpdb;
    
    $city_choices = bntm_cr_get_city_choices();
    $rental_types = bntm_cr_get_rental_types();
    $vehicle_types = bntm_cr_get_vehicle_types();
    $nonce = wp_create_nonce('cr_form_nonce');
    
    ob_start();
    ?>
    <script>var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';</script>
    
    <style>
    .rental-container {
        max-width: 1000px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .rental-header {
        text-align: center;
        margin-bottom: 40px;
    }
    
    .step-section {
        display: none;
        padding: 30px;
        background: white;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
    }
    
    .step-section.active {
        display: block;
    }
    
    .step-section h3 {
        margin-top: 0;
        margin-bottom: 20px;
        color: #1f2937;
    }
    
    .city-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .city-btn {
        padding: 15px;
        border: 2px solid #ddd;
        background: white;
        cursor: pointer;
        border-radius: 8px;
        font-weight: bold;
        transition: all 0.2s;
        font-size: 16px;
    }
    
    .city-btn:hover {
        border-color: var(--bntm-primary);
    }
    
    .city-btn.active {
        border-color: var(--bntm-primary);
        background: var(--bntm-primary);
        color: white;
    }
    
    .rental-type-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }
    
    .rental-type-card {
        padding: 25px;
        border: 2px solid #ddd;
        background: white;
        cursor: pointer;
        border-radius: 12px;
        transition: all 0.3s;
        text-align: center;
    }
    
    .rental-type-card:hover {
        border-color: var(--bntm-primary);
        transform: translateY(-2px);
    }
    
    .rental-type-card.active {
        border-color: var(--bntm-primary);
        background: #f0f9ff;
    }
    
    .rental-type-card h4 {
        margin: 0 0 10px 0;
        color: #1f2937;
        font-size: 18px;
    }
    
    .rental-type-card p {
        margin: 0;
        color: #6b7280;
        font-size: 14px;
    }
    
    .form-section {
        display: none;
    }
    
    .form-section.active {
        display: block;
    }
    
    .price-display {
        background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
        padding: 20px;
        border-radius: 12px;
        margin: 20px 0;
        border: 1px solid #cffafe;
    }
    
    .price-display.hidden {
        display: none;
    }
    
    .price-row {
        display: flex;
        justify-content: space-between;
        margin: 10px 0;
        font-size: 16px;
    }
    
    .price-row strong {
        font-weight: 600;
    }
    
    .price-total {
        font-size: 24px;
        font-weight: bold;
        color: var(--bntm-primary);
        text-align: right;
    }
    
    .bntm-form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }
    
    .bntm-form-group {
        display: flex;
        flex-direction: column;
    }
    
    .bntm-form-group label {
        margin-bottom: 8px;
        font-weight: 600;
        color: #374151;
        font-size: 14px;
    }
    
    .bntm-form-group input,
    .bntm-form-group select,
    .bntm-form-group textarea {
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 14px;
        font-family: inherit;
    }
    
    .bntm-form-group input:focus,
    .bntm-form-group select:focus,
    .bntm-form-group textarea:focus {
        outline: none;
        border-color: var(--bntm-primary);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .customer-details {
        display: none;
        margin-top: 40px;
        padding-top: 30px;
        border-top: 2px solid #e5e7eb;
    }
    
    .customer-details.show {
        display: block;
    }
    
    .bntm-btn-primary {
        display: block;
        width: 100%;
        padding: 15px;
        background: var(--bntm-primary);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s;
        margin-top: 20px;
    }
    
    .bntm-btn-primary:hover:not(:disabled) {
        opacity: 0.9;
        transform: translateY(-2px);
    }
    
    .bntm-btn-primary:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    
    .booking-message {
        margin-top: 20px;
        padding: 15px;
        border-radius: 8px;
        display: none;
    }
    
    .booking-message.show {
        display: block;
    }
    
    .booking-message.success {
        background: #d1fae5;
        border: 1px solid #059669;
        color: #065f46;
    }
    
    .booking-message.error {
        background: #fee2e2;
        border: 1px solid #dc2626;
        color: #991b1b;
    }
    </style>
    
    <div class="rental-container">
        <div class="rental-header">
            <h1>🚗 Car Rental Booking System</h1>
            <p style="color: #6b7280; margin-top: 10px;">Select your city, rental type, and complete your booking</p>
        </div>
        
        <form id="rental-booking-form" class="bntm-form">
            <input type="hidden" name="action" value="cr_submit_booking">
            <input type="hidden" name="nonce" value="<?php echo $nonce; ?>">
            <input type="hidden" name="selected_city" id="selected_city">
            <input type="hidden" name="rental_type" id="rental_type">
            <input type="hidden" name="total_price" id="total_price">
            <input type="hidden" name="car_id" id="master-car-id" value="">
            <input type="hidden" name="start_date" id="hidden-start-date" value="">
            <input type="hidden" name="end_date" id="hidden-end-date" value="">
            
            <!-- STEP 1: Select City -->
            <div id="step1" class="step-section active">
                <h3>Step 1: Select Your City</h3>
                <p style="color: #6b7280; margin-bottom: 20px;">Where would you like to rent a car from?</p>
                <div class="city-grid">
                    <?php foreach ($city_choices as $city_key => $city_label): ?>
                        <button type="button" class="city-btn" data-city="<?php echo $city_key; ?>" onclick="selectCity('<?php echo $city_key; ?>', event)">
                            <?php echo $city_label; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="bntm-btn-primary" onclick="goToStep(2)" style="opacity: 0.5; cursor: not-allowed;" id="next-step1" disabled>
                    Next: Select Rental Type →
                </button>
            </div>
            
            <!-- STEP 2: Select Rental Type -->
            <div id="step2" class="step-section">
                <h3>Step 2: Select Rental Type</h3>
                <p style="color: #6b7280; margin-bottom: 20px;">Choose the type of rental that fits your needs</p>
                <div class="rental-type-grid">
                    <div class="rental-type-card" onclick="selectRentalType('commercial', event)">
                        <h4>💼 Commercial</h4>
                        <p>City drives with hourly rates. Perfect for business trips and airport transfers.</p>
                    </div>
                    <div class="rental-type-card" onclick="selectRentalType('self_drive', event)">
                        <h4>🛣️ Self-Drive</h4>
                        <p>24-hour rental packages. Explore at your own pace without a driver.</p>
                    </div>
                    <div class="rental-type-card" onclick="selectRentalType('out_of_town', event)">
                        <h4>🗺️ Out of Town</h4>
                        <p>Location-based trips with set rates. Travel to your destination with ease.</p>
                    </div>
                </div>
                <button type="button" class="bntm-btn-primary" onclick="goToStep(1)" style="opacity: 0.5;">
                    ← Back to City Selection
                </button>
                <button type="button" class="bntm-btn-primary" onclick="goToStep(3)" style="opacity: 0.5; cursor: not-allowed;" id="next-step2" disabled>
                    Next: Complete Booking Details →
                </button>
            </div>
            
            <!-- STEP 3: Booking Details by Rental Type -->
            <div id="step3" class="step-section">
                <!-- COMMERCIAL RENTAL FORM -->
                <div id="commercial-form" class="form-section">
                    <h3>💼 Commercial Rental (City Drive)</h3>
                    <p style="color: #6b7280; margin-bottom: 20px;">Choose a fixed duration block like 3 hours = 1500, plus optional airport surcharge.</p>
                    
                    <div class="bntm-form-row">
                        <div class="bntm-form-group">
                            <label>Vehicle Type *</label>
                            <select name="vehicle_type" id="commercial-vehicle" required onchange="loadInventoryCars('commercial', this.value); updateCommercialRate()">
                                <option value="">-- Select Vehicle --</option>
                                <?php foreach ($vehicle_types as $type => $label): ?>
                                    <option value="<?php echo $type; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="bntm-form-group">
                            <label>Duration (Hours) *</label>
                            <input type="number" name="commercial_hours" id="commercial-hours" required min="1" step="0.5" onchange="updateCommercialRate()" placeholder="e.g., 3">
                            <small class="cr-spec-note">Use the exact duration block you saved in the rate table, like 3 hours = 1500.</small>
                        </div>
                    </div>

                    <div class="bntm-form-group">
                        <label>Available Car *</label>
                        <select id="commercial-car">
                            <option value="">Select a car from inventory</option>
                        </select>
                        <small class="cr-spec-note">Loaded from the cars saved for the selected city.</small>
                    </div>
                    <div id="commercial-selected-car-info" class="price-display hidden" style="margin-top:-5px;">
                        <div class="price-row">
                            <span>Selected Car:</span>
                            <strong id="commercial-car-name">None</strong>
                        </div>
                        <div class="price-row">
                            <span>City / Type:</span>
                            <span id="commercial-car-meta">-</span>
                        </div>
                    </div>
                    
                    <div class="bntm-form-row">
                        <div class="bntm-form-group">
                            <label>Pickup from Airport? *</label>
                            <select name="airport_pickup" id="airport-pickup" onchange="updateCommercialRate()">
                                <option value="no">No - Regular City Pickup</option>
                                <option value="yes">Yes - Add Airport Surcharge</option>
                            </select>
                        </div>
                        <div class="bntm-form-group">
                            <label>Rental Date *</label>
                            <input type="date" id="commercial-date" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    
                    <div id="commercial-price-display" class="price-display hidden">
                        <div class="price-row">
                            <span>Duration Block:</span>
                            <span id="commercial-duration-display">0 hours</span>
                        </div>
                        <div class="price-row">
                            <span>Fixed Price:</span>
                            <span id="commercial-base">₱0.00</span>
                        </div>
                        <div class="price-row" id="airport-surcharge-row" style="display: none;">
                            <span>Airport Surcharge:</span>
                            <span id="commercial-airport">₱0.00</span>
                        </div>
                        <div class="price-row" style="border-top: 1px solid #7dd3c0; padding-top: 10px; margin-top: 10px;">
                            <strong>Total Amount:</strong>
                            <strong id="commercial-total" class="price-total">₱0.00</strong>
                        </div>
                    </div>
                </div>
                
                <!-- SELF-DRIVE RENTAL FORM -->
                <div id="self-drive-form" class="form-section">
                    <h3>🛣️ Self-Drive Rental (24 Hours)</h3>
                    <p style="color: #6b7280; margin-bottom: 20px;">Rent a car for complete freedom. Rates are per 24-hour block.</p>
                    
                    <div class="bntm-form-row">
                        <div class="bntm-form-group">
                            <label>Vehicle Type *</label>
                            <select name="sd_vehicle_type" id="sd-vehicle" required onchange="loadInventoryCars('sd', this.value); updateSelfDriveRate()">
                                <option value="">-- Select Vehicle --</option>
                                <?php foreach ($vehicle_types as $type => $label): ?>
                                    <option value="<?php echo $type; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="bntm-form-group">
                            <label>Number of Days *</label>
                            <input type="number" name="sd_days" id="sd-days" required min="1" onchange="updateSelfDriveRate()" placeholder="e.g., 3">
                        </div>
                    </div>

                    <div class="bntm-form-group">
                        <label>Available Car *</label>
                        <select id="sd-car">
                            <option value="">Select a car from inventory</option>
                        </select>
                        <small class="cr-spec-note">Loaded from the cars saved for the selected city.</small>
                    </div>
                    <div id="sd-selected-car-info" class="price-display hidden" style="margin-top:-5px;">
                        <div class="price-row">
                            <span>Selected Car:</span>
                            <strong id="sd-car-name">None</strong>
                        </div>
                        <div class="price-row">
                            <span>City / Type:</span>
                            <span id="sd-car-meta">-</span>
                        </div>
                    </div>
                    
                    <div class="bntm-form-row">
                        <div class="bntm-form-group">
                            <label>Start Date *</label>
                            <input type="date" name="sd_start_date" id="sd-start-date" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="bntm-form-group">
                            <label>End Date *</label>
                            <input type="date" name="sd_end_date" id="sd-end-date" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    
                    <div class="bntm-form-row">
                        <div class="bntm-form-group">
                            <label>Additional Hours (beyond 24-hour blocks)</label>
                            <input type="number" name="sd_additional_hours" id="sd-additional-hours" min="0" step="0.5" onchange="updateSelfDriveRate()" placeholder="0">
                        </div>
                    </div>
                    
                    <div id="sd-price-display" class="price-display hidden">
                        <div class="price-row">
                            <span>Rate per 24 Hours:</span>
                            <span id="sd-rate">₱0.00</span>
                        </div>
                        <div class="price-row">
                            <span>Number of Days:</span>
                            <span id="sd-days-display">0</span>
                        </div>
                        <div class="price-row">
                            <span>Base Price (24hrs × days):</span>
                            <span id="sd-base-price">₱0.00</span>
                        </div>
                        <div class="price-row" id="sd-additional-row" style="display: none;">
                            <span>Additional Hours Charge:</span>
                            <span id="sd-additional-charge">₱0.00</span>
                        </div>
                        <div class="price-row" id="sd-deposit-row" style="display: none;">
                            <span>Security Deposit:</span>
                            <span id="sd-deposit">₱0.00</span>
                        </div>
                        <div class="price-row" style="border-top: 1px solid #7dd3c0; padding-top: 10px; margin-top: 10px;">
                            <strong>Total Amount:</strong>
                            <strong id="sd-total" class="price-total">₱0.00</strong>
                        </div>
                    </div>
                </div>
                
                <!-- OUT OF TOWN RENTAL FORM -->
                <div id="out-of-town-form" class="form-section">
                    <h3>🗺️ Out of Town Rental</h3>
                    <p style="color: #6b7280; margin-bottom: 20px;">Pre-set rates for popular destinations. Charged per trip with hourly overflow rates.</p>
                    
                    <div class="bntm-form-row">
                        <div class="bntm-form-group">
                            <label>Destination Location *</label>
                            <select name="oot_location" id="oot-location" required onchange="updateOutOfTownRate()">
                                <option value="">-- Loading Locations --</option>
                            </select>
                        </div>
                        <div class="bntm-form-group">
                            <label>Vehicle Type *</label>
                            <select name="oot_vehicle_type" id="oot-vehicle" required onchange="loadInventoryCars('oot', this.value); updateOutOfTownRate()">
                                <option value="">-- Select Vehicle --</option>
                                <?php foreach ($vehicle_types as $type => $label): ?>
                                    <option value="<?php echo $type; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="bntm-form-group">
                        <label>Available Car *</label>
                        <select id="oot-car">
                            <option value="">Select a car from inventory</option>
                        </select>
                        <small class="cr-spec-note">Loaded from the cars saved for the selected city.</small>
                    </div>
                    <div id="oot-selected-car-info" class="price-display hidden" style="margin-top:-5px;">
                        <div class="price-row">
                            <span>Selected Car:</span>
                            <strong id="oot-car-name">None</strong>
                        </div>
                        <div class="price-row">
                            <span>City / Type:</span>
                            <span id="oot-car-meta">-</span>
                        </div>
                    </div>
                    
                    <div class="bntm-form-row">
                        <div class="bntm-form-group">
                            <label>Hours of Usage *</label>
                            <input type="number" name="oot_hours" id="oot-hours" required min="1" step="0.5" onchange="updateOutOfTownRate()" placeholder="e.g., 12">
                        </div>
                        <div class="bntm-form-group">
                            <label>Travel Date *</label>
                            <input type="date" id="oot-date" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    
                    <div id="oot-price-display" class="price-display hidden">
                        <div class="price-row">
                            <span id="oot-location-name"></span>
                            <span id="oot-km-display">0 KM</span>
                        </div>
                        <div class="price-row">
                            <span>Trip Price:</span>
                            <span id="oot-trip-price">₱0.00</span>
                        </div>
                        <div class="price-row">
                            <span>Hours of Usage:</span>
                            <span id="oot-hours-display">0 hrs</span>
                        </div>
                        <div class="price-row" id="oot-excess-row" style="display: none;">
                            <span>Excess Hours:</span>
                            <span id="oot-excess-hours">0 hrs</span>
                        </div>
                        <div class="price-row" id="oot-exceeding-row" style="display: none;">
                            <span>Exceeding Hours Charge:</span>
                            <span id="oot-exceeding-charge">₱0.00</span>
                        </div>
                        <div class="price-row" style="border-top: 1px solid #7dd3c0; padding-top: 10px; margin-top: 10px;">
                            <strong>Total Amount:</strong>
                            <strong id="oot-total" class="price-total">₱0.00</strong>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Customer Details (shown after rental type selection) -->
            <div id="customer-details" class="customer-details">
                <h3>Step 4: Contact Details</h3>
                <p style="color: #6b7280; margin-bottom: 20px;">Fill in your contact information after choosing a car.</p>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Full Name *</label>
                        <input type="text" name="customer_name" required>
                    </div>
                    <div class="bntm-form-group">
                        <label>Email Address *</label>
                        <input type="email" name="customer_email" required>
                    </div>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Phone Number *</label>
                        <input type="tel" name="customer_phone" required placeholder="+63...">
                    </div>
                    <div class="bntm-form-group">
                        <label>Number of Passengers *</label>
                        <input type="number" name="number_of_pax" required min="1" value="1">
                    </div>
                </div>
                
                <div class="bntm-form-group">
                    <label>Special Requests / Notes</label>
                    <textarea name="notes" rows="4" placeholder="Any special requests or additional information?"></textarea>
                </div>
                
                <button type="button" class="bntm-btn-primary" id="cr-review-btn" onclick="crShowConfirmModal()">
                    ✓ Review &amp; Confirm Booking
                </button>
            </div>
        </form>
        
        <div id="booking-message" class="booking-message"></div>
    </div>

    <!-- Booking Confirmation Modal -->
    <div id="cr-confirm-modal" style="display:none;position:fixed;z-index:10000;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.55);overflow:auto;">
        <div style="background:#fff;margin:40px auto;padding:30px;border-radius:14px;width:90%;max-width:520px;box-shadow:0 8px 30px rgba(0,0,0,0.18);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;padding-bottom:15px;border-bottom:2px solid #e5e7eb;">
                <h2 style="margin:0;color:#1f2937;font-size:20px;">Confirm Your Booking</h2>
                <button type="button" onclick="crCloseConfirm()" style="font-size:26px;font-weight:bold;color:#6b7280;border:none;background:none;cursor:pointer;line-height:1;">&times;</button>
            </div>
            <div id="cr-confirm-summary"></div>
            <div style="display:flex;gap:12px;margin-top:20px;">
                <button type="button" onclick="crCloseConfirm()" style="flex:1;padding:12px;border:1px solid #d1d5db;background:#fff;border-radius:8px;cursor:pointer;font-size:14px;font-weight:600;">&#8592; Edit</button>
                <button type="button" id="cr-confirm-submit-btn" style="flex:2;padding:12px;background:var(--bntm-primary);color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:bold;cursor:pointer;">&#10003; Confirm &amp; Book</button>
            </div>
        </div>
    </div>
    
    <script>
    let selectedCity = '';
    let selectedRentalType = '';
    const crCityChoices = <?php echo wp_json_encode($city_choices); ?>;
    const crVehicleTypes = <?php echo wp_json_encode($vehicle_types); ?>;
    
    function selectCity(city, event) {
        event.preventDefault();
        selectedCity = city;
        document.getElementById('selected_city').value = city;
        
        document.querySelectorAll('.city-btn').forEach(b => b.classList.remove('active'));
        event.target.classList.add('active');
        
        document.getElementById('next-step1').disabled = false;
        document.getElementById('next-step1').style.opacity = '1';
        document.getElementById('next-step1').style.cursor = 'pointer';
        
        // Load out of town locations for this city
        loadOutOfTownLocations(city);
        crRefreshActiveCarLists();
        updateCustomerDetailsVisibility();
    }
    
    function selectRentalType(type, event) {
        event.preventDefault();
        selectedRentalType = type;
        document.getElementById('rental_type').value = type;
        
        document.querySelectorAll('.rental-type-card').forEach(c => c.classList.remove('active'));
        event.target.closest('.rental-type-card').classList.add('active');
        
        // Hide all form sections
        document.getElementById('commercial-form').classList.remove('active');
        document.getElementById('self-drive-form').classList.remove('active');
        document.getElementById('out-of-town-form').classList.remove('active');
        
        // Show selected form
        if (type === 'commercial') {
            document.getElementById('commercial-form').classList.add('active');
        } else if (type === 'self_drive') {
            document.getElementById('self-drive-form').classList.add('active');
        } else if (type === 'out_of_town') {
            document.getElementById('out-of-town-form').classList.add('active');
        }

        crRefreshActiveCarLists();
        
        document.getElementById('next-step2').disabled = false;
        document.getElementById('next-step2').style.opacity = '1';
        document.getElementById('next-step2').style.cursor = 'pointer';
        updateCustomerDetailsVisibility();
    }
    
    function goToStep(step) {
        if (step === 1) {
            document.getElementById('step1').classList.add('active');
            document.getElementById('step2').classList.remove('active');
            document.getElementById('step3').classList.remove('active');
        } else if (step === 2) {
            if (!selectedCity) {
                alert('Please select a city first');
                return;
            }
            document.getElementById('step1').classList.remove('active');
            document.getElementById('step2').classList.add('active');
            document.getElementById('step3').classList.remove('active');
        } else if (step === 3) {
            if (!selectedCity || !selectedRentalType) {
                alert('Please complete the previous steps');
                return;
            }
            document.getElementById('step1').classList.remove('active');
            document.getElementById('step2').classList.remove('active');
            document.getElementById('step3').classList.add('active');
        }
    }
    
    function loadOutOfTownLocations(city) {
        const select = document.getElementById('oot-location');
        select.innerHTML = '<option value="">-- Loading --</option>';
        
        const fd = new FormData();
        fd.append('action', 'cr_get_out_of_town_locations');
        fd.append('city', city);
        
        fetch(ajaxurl, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.data.length > 0) {
                    select.innerHTML = '<option value="">-- Select Location --</option>';
                    data.data.forEach(loc => {
                        const opt = document.createElement('option');
                        opt.value = loc.location_name;
                        opt.textContent = `${loc.location_name} (${loc.km_distance} km)`;
                        opt.dataset.km = loc.km_distance;
                        select.appendChild(opt);
                    });
                } else {
                    select.innerHTML = '<option value="">No locations available</option>';
                }
            })
            .catch(err => {
                select.innerHTML = '<option value="">Error loading locations</option>';
                console.error('Error loading out of town locations:', err);
            });
    }

    function loadInventoryCars(prefix, vehicleType = '') {
        const select = document.getElementById(prefix + '-car');
        if (!select) return;

        select.innerHTML = '<option value="">Loading cars...</option>';

        const fd = new FormData();
        fd.append('action', 'cr_get_inventory_cars');
        fd.append('city', selectedCity);
        fd.append('vehicle_type', vehicleType || '');

        fetch(ajaxurl, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success && Array.isArray(data.data)) {
                    if (data.data.length === 0) {
                        select.innerHTML = '<option value="">No cars available</option>';
                        return;
                    }

                    select.innerHTML = '<option value="">Select a car from inventory</option>';
                    data.data.forEach(car => {
                        const opt = document.createElement('option');
                        opt.value = car.id;
                        opt.textContent = `${car.label} (${car.plate_number}) - ${car.city.toUpperCase()}`;
                        opt.dataset.vehicleType = car.vehicle_type;
                        opt.dataset.city = car.city;
                        opt.dataset.label = car.label;
                        opt.dataset.plateNumber = car.plate_number;
                        opt.dataset.seats = car.seating_capacity || '';
                        opt.dataset.specifications = car.specifications || '';
                        select.appendChild(opt);
                    });

                    if (prefix === 'sd') {
                        renderSelectedInventoryCar('sd');
                    }
                    if (prefix === 'commercial' || prefix === 'oot') {
                        renderSelectedInventoryCar(prefix);
                    }
                } else {
                    select.innerHTML = '<option value="">No cars available</option>';
                    renderSelectedInventoryCar(prefix);
                }
            })
            .catch(() => {
                select.innerHTML = '<option value="">Error loading cars</option>';
                renderSelectedInventoryCar(prefix);
            });
    }

    function renderSelectedInventoryCar(prefix) {
        const select = document.getElementById(prefix + '-car');
        const infoWrap = document.getElementById(prefix + '-selected-car-info');
        if (!select || !infoWrap) return;

        const opt = select.options[select.selectedIndex];
        const nameEl = document.getElementById(prefix + '-car-name');
        const metaEl = document.getElementById(prefix + '-car-meta');

        if (!opt || !opt.value) {
            infoWrap.classList.add('hidden');
            if (nameEl) nameEl.textContent = 'None';
            if (metaEl) metaEl.textContent = '-';
            updateCustomerDetailsVisibility();
            return;
        }

        infoWrap.classList.remove('hidden');
        if (nameEl) nameEl.textContent = opt.dataset.label || opt.textContent || 'Selected Car';
        if (metaEl) {
            const cityLabel = (opt.dataset.city || '').toUpperCase();
            const vehicleLabel = opt.dataset.vehicleType || '';
            const seats = opt.dataset.seats ? `, ${opt.dataset.seats} seats` : '';
            metaEl.textContent = `${cityLabel} / ${vehicleLabel}${seats}`;
        }
        updateCustomerDetailsVisibility();
    }

    function crRefreshActiveCarLists() {
        if (selectedRentalType === 'commercial') {
            loadInventoryCars('commercial', document.getElementById('commercial-vehicle')?.value || '');
        } else if (selectedRentalType === 'self_drive') {
            loadInventoryCars('sd', document.getElementById('sd-vehicle')?.value || '');
        } else if (selectedRentalType === 'out_of_town') {
            loadInventoryCars('oot', document.getElementById('oot-vehicle')?.value || '');
        }
    }

    const sdCarSelect = document.getElementById('sd-car');
    if (sdCarSelect) {
        sdCarSelect.addEventListener('change', function() {
            renderSelectedInventoryCar('sd');
        });
    }
    const commercialCarSelect = document.getElementById('commercial-car');
    if (commercialCarSelect) {
        commercialCarSelect.addEventListener('change', function() {
            renderSelectedInventoryCar('commercial');
        });
    }
    const ootCarSelect = document.getElementById('oot-car');
    if (ootCarSelect) {
        ootCarSelect.addEventListener('change', function() {
            renderSelectedInventoryCar('oot');
        });
    }

    function updateCustomerDetailsVisibility() {
        const details = document.getElementById('customer-details');
        if (!details) return;

        let currentPrefix = '';
        if (selectedRentalType === 'commercial') {
            currentPrefix = 'commercial';
        } else if (selectedRentalType === 'self_drive') {
            currentPrefix = 'sd';
        } else if (selectedRentalType === 'out_of_town') {
            currentPrefix = 'oot';
        }

        if (!selectedCity || !selectedRentalType || !currentPrefix) {
            details.classList.remove('show');
            return;
        }

        const carSelect = document.getElementById(currentPrefix + '-car');
        const hasCar = !!(carSelect && carSelect.value);

        if (hasCar) {
            details.classList.add('show');
        } else {
            details.classList.remove('show');
        }
    }
    
    function updateCommercialRate() {
        const hours = parseFloat(document.getElementById('commercial-hours').value) || 0;
        const vehicleType = document.getElementById('commercial-vehicle').value;
        const carId = document.getElementById('commercial-car').value || 0;
        const airport = document.getElementById('airport-pickup').value === 'yes';
        
        if (!hours || !vehicleType || !carId) {
            document.getElementById('commercial-price-display').classList.add('hidden');
            return;
        }
        
        const fd = new FormData();
        fd.append('action', 'cr_calculate_commercial');
        fd.append('city', selectedCity);
        fd.append('vehicle_type', vehicleType);
        fd.append('car_id', carId);
        fd.append('hours', hours);
        fd.append('airport', airport ? 'yes' : 'no');
        
        fetch(ajaxurl, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const pricing = data.data;
                    document.getElementById('commercial-duration-display').textContent = pricing.duration_hours + ' hours';
                    document.getElementById('commercial-base').textContent = '₱' + parseFloat(pricing.base_price).toLocaleString('en-US', {minimumFractionDigits: 2});
                    
                    if (airport) {
                        document.getElementById('commercial-airport').textContent = '₱' + parseFloat(pricing.airport_surcharge).toLocaleString('en-US', {minimumFractionDigits: 2});
                        document.getElementById('airport-surcharge-row').style.display = 'flex';
                    } else {
                        document.getElementById('airport-surcharge-row').style.display = 'none';
                    }
                    
                    document.getElementById('commercial-total').textContent = '₱' + parseFloat(pricing.total).toLocaleString('en-US', {minimumFractionDigits: 2});
                    document.getElementById('total_price').value = pricing.total;
                    document.getElementById('commercial-price-display').classList.remove('hidden');
                    renderSelectedInventoryCar('commercial');
                }
            });
    }
    
    function updateSelfDriveRate() {
        const days = parseInt(document.getElementById('sd-days').value) || 0;
        const vehicleType = document.getElementById('sd-vehicle').value;
        const carId = document.getElementById('sd-car').value || 0;
        const additionalHours = parseFloat(document.getElementById('sd-additional-hours').value) || 0;
        
        if (!days || !vehicleType) {
            document.getElementById('sd-price-display').classList.add('hidden');
            return;
        }
        
        const fd = new FormData();
        fd.append('action', 'cr_calculate_self_drive');
        fd.append('city', selectedCity);
        fd.append('vehicle_type', vehicleType);
        fd.append('car_id', carId);
        fd.append('days', days);
        fd.append('additional_hours', additionalHours);
        
        fetch(ajaxurl, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const pricing = data.data;
                    document.getElementById('sd-rate').textContent = '₱' + parseFloat(pricing.rate_per_24hours).toLocaleString('en-US', {minimumFractionDigits: 2});
                    document.getElementById('sd-days-display').textContent = days;
                    document.getElementById('sd-base-price').textContent = '₱' + parseFloat(pricing.base_price).toLocaleString('en-US', {minimumFractionDigits: 2});
                    
                    if (additionalHours > 0) {
                        document.getElementById('sd-additional-charge').textContent = '₱' + parseFloat(pricing.exceeding_charge).toLocaleString('en-US', {minimumFractionDigits: 2});
                        document.getElementById('sd-additional-row').style.display = 'flex';
                    } else {
                        document.getElementById('sd-additional-row').style.display = 'none';
                    }
                    
                    if (pricing.security_deposit > 0) {
                        document.getElementById('sd-deposit').textContent = '₱' + parseFloat(pricing.security_deposit).toLocaleString('en-US', {minimumFractionDigits: 2});
                        document.getElementById('sd-deposit-row').style.display = 'flex';
                    } else {
                        document.getElementById('sd-deposit-row').style.display = 'none';
                    }
                    
                    document.getElementById('sd-total').textContent = '₱' + parseFloat(pricing.total).toLocaleString('en-US', {minimumFractionDigits: 2});
                    document.getElementById('total_price').value = pricing.total;
                    document.getElementById('sd-price-display').classList.remove('hidden');
                    renderSelectedInventoryCar('sd');
                }
            });
    }
    
    function updateOutOfTownRate() {
        const location = document.getElementById('oot-location').value;
        const vehicleType = document.getElementById('oot-vehicle').value;
        const carId = document.getElementById('oot-car').value || 0;
        const hours = parseFloat(document.getElementById('oot-hours').value) || 0;
        
        if (!location || !vehicleType || !hours || !carId) {
            document.getElementById('oot-price-display').classList.add('hidden');
            return;
        }
        
        const fd = new FormData();
        fd.append('action', 'cr_calculate_out_of_town');
        fd.append('city', selectedCity);
        fd.append('location', location);
        fd.append('vehicle_type', vehicleType);
        fd.append('car_id', carId);
        fd.append('hours', hours);
        
        fetch(ajaxurl, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const pricing = data.data;
                    document.getElementById('oot-location-name').textContent = location;
                    document.getElementById('oot-km-display').textContent = pricing.km_distance + ' KM';
                    document.getElementById('oot-trip-price').textContent = '₱' + parseFloat(pricing.trip_price).toLocaleString('en-US', {minimumFractionDigits: 2});
                    document.getElementById('oot-hours-display').textContent = hours + ' hrs';
                    if (pricing.excess_hours > 0 && pricing.exceeding_charge > 0) {
                        document.getElementById('oot-excess-hours').textContent = parseFloat(pricing.excess_hours).toFixed(2) + ' hrs';
                        document.getElementById('oot-exceeding-charge').textContent = '₱' + parseFloat(pricing.exceeding_charge).toLocaleString('en-US', {minimumFractionDigits: 2});
                        document.getElementById('oot-excess-row').style.display = 'flex';
                        document.getElementById('oot-exceeding-row').style.display = 'flex';
                    } else {
                        document.getElementById('oot-excess-row').style.display = 'none';
                        document.getElementById('oot-exceeding-row').style.display = 'none';
                    }
                    
                    document.getElementById('oot-total').textContent = '₱' + parseFloat(pricing.total).toLocaleString('en-US', {minimumFractionDigits: 2});
                    document.getElementById('total_price').value = pricing.total;
                    document.getElementById('oot-price-display').classList.remove('hidden');
                    renderSelectedInventoryCar('oot');
                }
            });
    }
    
    // Sync car selects → master hidden car_id
    function crUpdateMasterCarId(prefix) {
        const sel = document.getElementById(prefix + '-car');
        const master = document.getElementById('master-car-id');
        if (sel && master) master.value = sel.value;
    }

    // Sync date inputs → hidden start_date / end_date
    function crUpdateHiddenDates() {
        const sf = document.getElementById('hidden-start-date');
        const ef = document.getElementById('hidden-end-date');
        if (!sf || !ef) return;
        if (selectedRentalType === 'commercial') {
            const d = document.getElementById('commercial-date');
            sf.value = d ? d.value : '';
            ef.value = d ? d.value : '';
        } else if (selectedRentalType === 'self_drive') {
            const s = document.getElementById('sd-start-date');
            const e = document.getElementById('sd-end-date');
            sf.value = s ? s.value : '';
            ef.value = e ? e.value : '';
        } else if (selectedRentalType === 'out_of_town') {
            const d = document.getElementById('oot-date');
            sf.value = d ? d.value : '';
            ef.value = d ? d.value : '';
        }
    }

    // Car select change listeners — update master car_id
    ['commercial', 'sd', 'oot'].forEach(function(prefix) {
        const sel = document.getElementById(prefix + '-car');
        if (sel) sel.addEventListener('change', function() {
            const activePrefix = selectedRentalType === 'commercial' ? 'commercial'
                : selectedRentalType === 'self_drive' ? 'sd'
                : selectedRentalType === 'out_of_town' ? 'oot' : '';
            if (prefix === activePrefix) crUpdateMasterCarId(prefix);
            renderSelectedInventoryCar(prefix);
        });
    });

    // Date change listeners
    ['commercial-date', 'sd-start-date', 'sd-end-date', 'oot-date'].forEach(function(id) {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', crUpdateHiddenDates);
    });

    // Confirmation modal
    function crShowConfirmModal() {
        if (!selectedCity || !selectedRentalType) {
            alert('Please complete all steps: select a city and rental type.');
            return;
        }
        const prefixMap = { commercial: 'commercial', self_drive: 'sd', out_of_town: 'oot' };
        const prefix = prefixMap[selectedRentalType] || '';
        const carSel = document.getElementById(prefix + '-car');
        if (!carSel || !carSel.value) {
            alert('Please select a car from the inventory.');
            return;
        }
        const custName  = document.querySelector('[name="customer_name"]');
        const custEmail = document.querySelector('[name="customer_email"]');
        const custPhone = document.querySelector('[name="customer_phone"]');
        if (!custName  || !custName.value.trim())  { alert('Please enter your full name.'); return; }
        if (!custEmail || !custEmail.value.trim()) { alert('Please enter your email address.'); return; }
        if (!custPhone || !custPhone.value.trim()) { alert('Please enter your phone number.'); return; }

        crUpdateMasterCarId(prefix);
        crUpdateHiddenDates();

        const totalPrice = parseFloat(document.getElementById('total_price').value) || 0;
        const cityLabel  = crCityChoices[selectedCity] || selectedCity.toUpperCase();
        const typeLabels = { commercial: 'Commercial (City Drive)', self_drive: 'Self-Drive (24hr)', out_of_town: 'Out of Town' };
        const rentalLabel = typeLabels[selectedRentalType] || selectedRentalType;

        const carOpt  = carSel.options[carSel.selectedIndex];
        const carName = carOpt && carOpt.value ? (carOpt.dataset.label || carOpt.textContent.trim()) : 'N/A';

        const startDate = document.getElementById('hidden-start-date').value || '—';
        const endDate   = document.getElementById('hidden-end-date').value   || '—';
        const dateStr   = startDate === endDate ? startDate : startDate + ' → ' + endDate;

        let detailsHtml = '';
        if (selectedRentalType === 'commercial') {
            const hours   = document.getElementById('commercial-hours')?.value || '—';
            const airport = document.getElementById('airport-pickup')?.value === 'yes' ? ' + Airport Pickup' : '';
            detailsHtml = '<tr><td style="color:#6b7280;padding:5px 0">Duration</td><td style="font-weight:600;text-align:right">' + hours + ' hrs' + airport + '</td></tr>';
        } else if (selectedRentalType === 'self_drive') {
            const days = document.getElementById('sd-days')?.value || '—';
            detailsHtml = '<tr><td style="color:#6b7280;padding:5px 0">Days</td><td style="font-weight:600;text-align:right">' + days + ' day(s)</td></tr>';
        } else if (selectedRentalType === 'out_of_town') {
            const loc = document.getElementById('oot-location')?.value || '—';
            const hrs = document.getElementById('oot-hours')?.value || '—';
            detailsHtml = '<tr><td style="color:#6b7280;padding:5px 0">Destination</td><td style="font-weight:600;text-align:right">' + loc + '</td></tr>'
                        + '<tr><td style="color:#6b7280;padding:5px 0">Hours</td><td style="font-weight:600;text-align:right">' + hrs + ' hrs</td></tr>';
        }

        const pax   = document.querySelector('[name="number_of_pax"]')?.value || 1;
        const notes = document.querySelector('[name="notes"]')?.value || '';
        const notesRow = notes ? '<tr><td style="color:#6b7280;padding:4px 0">Notes</td><td style="font-weight:600;text-align:right;font-size:12px">' + notes + '</td></tr>' : '';

        document.getElementById('cr-confirm-summary').innerHTML =
            '<table style="width:100%;border-collapse:collapse;font-size:14px">'
            + '<tr><td style="color:#6b7280;padding:6px 0">City</td><td style="font-weight:600;text-align:right">' + cityLabel + '</td></tr>'
            + '<tr><td style="color:#6b7280;padding:6px 0">Rental Type</td><td style="font-weight:600;text-align:right">' + rentalLabel + '</td></tr>'
            + '<tr><td style="color:#6b7280;padding:6px 0">Car</td><td style="font-weight:600;text-align:right">' + carName + '</td></tr>'
            + '<tr><td style="color:#6b7280;padding:6px 0">Date(s)</td><td style="font-weight:600;text-align:right">' + dateStr + '</td></tr>'
            + detailsHtml
            + '<tr><td style="color:#6b7280;padding:6px 0">Passengers</td><td style="font-weight:600;text-align:right">' + pax + '</td></tr>'
            + '<tr style="border-top:1px solid #e5e7eb"><td style="padding:10px 0;font-size:15px" colspan="2"><strong>Customer Details</strong></td></tr>'
            + '<tr><td style="color:#6b7280;padding:4px 0">Name</td><td style="font-weight:600;text-align:right">' + custName.value + '</td></tr>'
            + '<tr><td style="color:#6b7280;padding:4px 0">Email</td><td style="font-weight:600;text-align:right">' + custEmail.value + '</td></tr>'
            + '<tr><td style="color:#6b7280;padding:4px 0">Phone</td><td style="font-weight:600;text-align:right">' + custPhone.value + '</td></tr>'
            + notesRow
            + '</table>'
            + '<div style="background:linear-gradient(135deg,#f0f9ff,#e0f2fe);border:1px solid #cffafe;border-radius:10px;padding:16px 20px;margin-top:16px;text-align:right">'
            + '<div style="color:#6b7280;font-size:13px">Total Amount</div>'
            + '<div style="font-size:26px;font-weight:bold;color:var(--bntm-primary)">&#8369;' + totalPrice.toLocaleString('en-US', {minimumFractionDigits:2}) + '</div>'
            + '</div>';

        document.getElementById('cr-confirm-modal').style.display = 'block';
    }

    function crCloseConfirm() {
        document.getElementById('cr-confirm-modal').style.display = 'none';
    }

    document.getElementById('cr-confirm-submit-btn').addEventListener('click', function() {
        this.disabled = true;
        this.textContent = 'Submitting...';
        const form = document.getElementById('rental-booking-form');
        fetch(ajaxurl, { method: 'POST', body: new FormData(form) })
            .then(r => r.json())
            .then(json => {
                crCloseConfirm();
                const msgDiv = document.getElementById('booking-message');
                msgDiv.classList.remove('success', 'error');
                msgDiv.classList.add(json.success ? 'success' : 'error');
                msgDiv.textContent = (json.data && json.data.message) ? json.data.message : (json.success ? 'Booking submitted!' : 'Submission failed.');
                msgDiv.classList.add('show');
                if (json.success) {
                    const reviewBtn = document.getElementById('cr-review-btn');
                    if (reviewBtn) { reviewBtn.disabled = true; reviewBtn.textContent = '✓ Booking Submitted'; }
                    setTimeout(function() { location.reload(); }, 3000);
                } else {
                    this.disabled = false;
                    this.textContent = '✓ Confirm & Book';
                }
            }.bind(this))
            .catch(function() {
                crCloseConfirm();
                const msgDiv = document.getElementById('booking-message');
                msgDiv.className = 'booking-message show error';
                msgDiv.textContent = 'Network error. Please try again.';
                this.disabled = false;
                this.textContent = '✓ Confirm & Book';
            }.bind(this));
    });

    window.addEventListener('click', function(e) {
        if (e.target === document.getElementById('cr-confirm-modal')) crCloseConfirm();
    });
    </script>
    <?php
    return ob_get_clean();
}
function bntm_shortcode_cr_invoice() {
    $booking_id = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : '';
    
    if (empty($booking_id)) {
        return '<div class="bntm-container"><p>Invalid booking ID.</p></div>';
    }

    global $wpdb;
    $bookings_table = $wpdb->prefix . 'cr_bookings';
    $packages_table = $wpdb->prefix . 'cr_packages';
    
   $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT b.*,
                COALESCE(p.package_name, CONCAT(c.car_make, ' ', c.car_model), b.rental_type) AS package_name,
                COALESCE(p.boat_type, c.vehicle_type, b.vehicle_category) AS package_type,
                COALESCE(CONCAT(c.car_make, ' ', c.car_model), '') AS car_name,
                COALESCE(c.plate_number, '') AS car_plate,
                COALESCE(p.hourly_surcharge, 0) as package_hourly_surcharge,
                p.description, p.photo_url
         FROM $bookings_table b
         LEFT JOIN $packages_table p ON b.package_id = p.id
         LEFT JOIN {$wpdb->prefix}cr_car_inventory c ON b.car_id = c.id
         WHERE b.rand_id = %s",
        $booking_id
    ));

    if (!$booking) {
        return '<div class="bntm-container"><p>Booking not found. ID: ' . esc_html($booking_id) . '</p></div>';
    }
    
    $logo = bntm_get_site_logo();
    $site_title = bntm_get_site_title();
    $show_payment_btn = in_array($booking->status, ['contacted', 'paid']);
    
    // Get payment methods
    $payment_source = bntm_get_setting('cr_payment_source', 'manual');
    $payment_methods = [];
    
    if ($payment_source === 'manual') {
        $payment_methods = json_decode(bntm_get_setting('cr_payment_methods', '[]'), true);
        if (!is_array($payment_methods)) $payment_methods = [];
    }
    
    // Calculate totals
    $downpayment_percent = intval(bntm_get_setting('cr_downpayment_percentage', '50'));
    $terms = bntm_get_setting('cr_terms', '');
    
    // Get package amount from booking
    $package_amount = floatval($booking->package_amount);
    
    // Calculate other fees total
    $other_fees_total = 0;
    $other_fees = json_decode($booking->other_fees, true);
    if (is_array($other_fees) && !empty($other_fees)) {
        foreach ($other_fees as $fee) {
            $other_fees_total += floatval($fee['amount']);
        }
    }
    
    // Get surcharge from database (already calculated and saved)
    $excess_hours = floatval($booking->excess_hours ?? 0);
    $surcharge_amount = floatval($booking->surcharge_amount ?? 0);
    
    // Calculate base total (package + other fees, excluding surcharge)
    $base_total = $package_amount + $other_fees_total;
    
    // Calculate downpayment from base total only (excluding surcharge)
    $downpayment_amount = $downpayment_percent > 0 ? ($base_total * $downpayment_percent / 100) : 0;
    
    // Calculate final total (base + surcharge)
    $final_total = $base_total + $surcharge_amount;
    
    // Calculate remaining balance (final total minus downpayment)
    $balance = $final_total - $downpayment_amount;
    
    ob_start();
    ?>
    <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    .invoice-container { max-width: 800px; margin: 0 auto; padding: 40px 20px; background: white; font-family: Arial, sans-serif; font-size: 11pt; line-height: 1.4; color: #000; }
    .invoice-header { display: flex; justify-content: space-between; padding-bottom: 15px; margin-bottom: 20px; border-bottom: 2px solid #000; }
    .company-name { font-size: 16pt; font-weight: bold; margin-bottom: 5px; }
    .invoice-title { font-size: 22pt; font-weight: bold; text-align: right; }
    .invoice-number { font-size: 14pt; text-align: right; margin-top: 5px; }
    .invoice-info { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 20px; }
    .info-label { font-weight: bold; font-size: 9pt; text-transform: uppercase; margin-bottom: 5px; }
    .customer-name { font-weight: bold; font-size: 11pt; margin-bottom: 3px; }
    .info-table { width: 100%; font-size: 10pt; }
    .info-table td { padding: 3px 0; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
    th { text-align: left; font-weight: bold; padding: 8px 5px; border-bottom: 2px solid #000; font-size: 9pt; text-transform: uppercase; }
    td { padding: 8px 5px; border-bottom: 1px solid #ddd; font-size: 10pt; vertical-align: top; }
    tbody tr:last-child td { border-bottom: 1px solid #000; }
    tfoot td { padding: 6px 5px; border-bottom: none; }
    .total-row { font-size: 12pt; }
    .total-row td { padding: 10px 5px; border-top: 2px solid #000; border-bottom: 2px solid #000; }
    .text-right { text-align: right; }
    .info-box { margin: 15px 0; padding: 12px; border: 1px solid #000; }
    .info-box-title { font-weight: bold; font-size: 9pt; text-transform: uppercase; margin-bottom: 8px; padding-bottom: 5px; border-bottom: 1px solid #ddd; }
    .info-box p { margin: 5px 0; font-size: 10pt; }
    .payment-method-item { padding: 10px; background: #f9fafb; border-radius: 4px; margin-bottom: 8px; }
    .car-photo-invoice { max-width: 150px; border-radius: 8px; margin-top: 5px; }
    .invoice-footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #000; text-align: center; font-size: 10pt; }
    .invoice-footer p { margin: 5px 0; }
    .highlight-row { background: #fff3cd; }
    @media print {
        .invoice-container { padding: 20px; }
        .no-print { display: none !important; }
    }
    @media screen and (max-width: 768px) {
        .invoice-header { flex-direction: column; gap: 15px; }
        .invoice-title, .invoice-number { text-align: left; }
        .invoice-info { grid-template-columns: 1fr; gap: 15px; }
    }
    </style>
    
    <div class="invoice-container">
        <div style="text-align: center; margin-bottom: 20px;" class="no-print">
            <button onclick="window.print()" class="bntm-btn bntm-btn-secondary">Print Invoice</button>
        </div>

        <div class="invoice-header">
            <div>
                <?php if ($logo): ?>
                    <img src="<?php echo esc_url($logo); ?>" alt="Logo" style="max-width: 120px; max-height: 50px; margin-bottom: 10px;">
                <?php endif; ?>
                <div class="company-name"><?php echo esc_html($site_title ?: 'Car Rental Booking'); ?></div>
            </div>
            <div>
                <div class="invoice-title">CAR RENTAL BOOKING</div>
                <div class="invoice-number">#<?php echo esc_html($booking->rand_id); ?></div>
            </div>
        </div>

        <div class="invoice-info">
            <div>
                <div class="info-label">CUSTOMER DETAILS</div>
                <div class="customer-name"><?php echo esc_html($booking->customer_name); ?></div>
                <div><?php echo esc_html($booking->customer_email); ?></div>
                <div><?php echo esc_html($booking->customer_phone); ?></div>
            </div>
            <div>
                <table class="info-table">
                    <tr>
                        <td><strong>Rental Period:</strong></td>
                        <td>
                            <?php echo date('M d, Y', strtotime($booking->start_date)); ?> - 
                            <?php echo date('M d, Y', strtotime($booking->end_date)); ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Duration:</strong></td>
                        <td><?php echo $booking->number_of_days; ?> day<?php echo $booking->number_of_days > 1 ? 's' : ''; ?></td>
                    </tr>
                    <?php if (!empty($booking->destination)): ?>
                    <tr>
                        <td><strong>Destination:</strong></td>
                        <td><?php echo esc_html($booking->destination); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($booking->distance_km)): ?>
                    <tr>
                        <td><strong>Distance:</strong></td>
                        <td><?php echo number_format($booking->distance_km, 2); ?> KM</td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($booking->total_hours)): ?>
                    <tr>
                        <td><strong>Total Hours:</strong></td>
                        <td><?php echo number_format($booking->total_hours, 2); ?> hours</td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($booking->check_in_time) && !empty($booking->check_out_time)): ?>
                    <tr>
                        <td><strong>Check-in:</strong></td>
                        <td><?php echo date('M d, Y g:i A', strtotime($booking->check_in_time)); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Check-out:</strong></td>
                        <td><?php echo date('M d, Y g:i A', strtotime($booking->check_out_time)); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td><strong>Booking Date:</strong></td>
                        <td><?php echo date('M d, Y', strtotime($booking->created_at)); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Status:</strong></td>
                        <td><strong><?php echo ucfirst(str_replace('_', ' ', $booking->status)); ?></strong></td>
                    </tr>
                    <?php if (!empty($booking->rental_type)): ?>
                    <tr>
                        <td><strong>Rental Type:</strong></td>
                        <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $booking->rental_type))); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($booking->car_name)): ?>
                    <tr>
                        <td><strong>Selected Car:</strong></td>
                        <td>
                            <?php echo esc_html($booking->car_name); ?>
                            <?php if (!empty($booking->car_plate)): ?> (<?php echo esc_html($booking->car_plate); ?>)<?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>DESCRIPTION</th>
                    <th class="text-right">RATE</th>
                    <th class="text-right">DAYS</th>
                    <th class="text-right">AMOUNT</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong><?php echo esc_html($booking->package_name); ?></strong><br>
                        <span style="font-size: 9pt; color: #666;">
                            <?php echo esc_html($booking->package_type); ?>
                            <?php if (!empty($booking->city)): ?>
                                <br>City: <?php echo esc_html(strtoupper($booking->city)); ?>
                            <?php endif; ?>
                            <?php if (!empty($booking->base_point) || !empty($booking->destination)): ?>
                                <br>Route: <?php echo esc_html($booking->base_point); ?> -> <?php echo esc_html($booking->destination); ?>
                            <?php endif; ?>
                            <?php if (!empty($booking->distance_km)): ?>
                                <br>Distance: <?php echo number_format($booking->distance_km, 2); ?> KM
                            <?php endif; ?>
                            <?php if (!empty($booking->total_hours)): ?>
                                <br>Total Hours: <?php echo number_format($booking->total_hours, 2); ?> hours
                            <?php endif; ?>
                            <?php if (($booking->overtime_rate ?? 0) > 0): ?>
                                <br>Overtime Rate: ₱<?php echo number_format($booking->overtime_rate, 2); ?>/hr
                            <?php endif; ?>
                        </span>
                       
                        <?php if ($booking->description): ?>
                            <br><span style="font-size: 9pt; color: #666;">
                                <?php echo nl2br(esc_html($booking->description)); ?>
                            </span>
                        <?php endif; ?>
                        <br><span style="font-size: 9pt; color: #666;">
                            Passengers: <?php echo $booking->number_of_pax; ?> pax
                        </span>
                    </td>
                    <td class="text-right">₱<?php echo number_format($booking->base_fee ?? $booking->daily_rate, 2); ?></td>
                    <td class="text-right"><?php echo $booking->number_of_days; ?></td>
                    <td class="text-right">₱<?php echo number_format($package_amount, 2); ?></td>
                </tr>
                <?php 
                if (is_array($other_fees) && !empty($other_fees)):
                    foreach ($other_fees as $fee):
                ?>
                <tr>
                    <td colspan="3"><?php echo esc_html($fee['description']); ?></td>
                    <td class="text-right">₱<?php echo number_format($fee['amount'], 2); ?></td>
                </tr>
                <?php 
                    endforeach;
                endif;
                ?>
                <?php if ($surcharge_amount > 0): ?>
                <tr style="background: #fef3c7;">
                    <td colspan="3">
                        <strong>Excess Time Surcharge</strong><br>
                        <span style="font-size: 9pt; color: #666;">
                            <?php echo number_format($excess_hours, 2); ?> excess hours @ 
                            ₱<?php echo number_format($booking->overtime_rate ?? 0, 2); ?>/hr
                        </span>
                        <br><span style="font-size: 9pt; color: #666;">Included time allowance: 10 hours</span>
                    </td>
                    <td class="text-right"><strong>₱<?php echo number_format($surcharge_amount, 2); ?></strong></td>
                </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <?php if ($downpayment_percent > 0): ?>
                    <tr>
                        <td colspan="3" class="text-right">Package & Fees Subtotal</td>
                        <td class="text-right">₱<?php echo number_format($base_total, 2); ?></td>
                    </tr>
                    <?php if ($surcharge_amount > 0): ?>
                    <tr>
                        <td colspan="3" class="text-right">
                            Excess Time Surcharge
                            <br><small style="font-weight: normal; color: #666;">
                                (<?php echo number_format($excess_hours, 2); ?> excess hours)
                            </small>
                        </td>
                        <td class="text-right">₱<?php echo number_format($surcharge_amount, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td colspan="3" class="text-right">
                            <strong>Grand Total</strong>
                        </td>
                        <td class="text-right">
                            <strong>₱<?php echo number_format($final_total, 2); ?></strong>
                        </td>
                    </tr>
                    <tr style="background: #f9fafb;">
                        <td colspan="3" class="text-right">
                            <strong>Down Payment (<?php echo $downpayment_percent; ?>%)</strong>
                            <br><small style="font-weight: normal; color: #666;">Based on package & fees only</small>
                        </td>
                        <td class="text-right">
                            <strong>₱<?php echo number_format($downpayment_amount, 2); ?></strong>
                        </td>
                    </tr>
                    <tr class="highlight-row">
                        <td colspan="3" class="text-right">
                            <strong>Remaining Balance</strong>
                            <?php if ($surcharge_amount > 0): ?>
                            <br><small style="font-weight: normal; color: #856404;">Includes ₱<?php echo number_format($surcharge_amount, 2); ?> surcharge fee</small>
                            <?php endif; ?>
                        </td>
                        <td class="text-right">
                            <strong style="color: #dc2626;">₱<?php echo number_format($balance, 2); ?></strong>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php if ($surcharge_amount > 0): ?>
                    <tr>
                        <td colspan="3" class="text-right">Subtotal</td>
                        <td class="text-right">₱<?php echo number_format($base_total, 2); ?></td>
                    </tr>
                    <tr>
                        <td colspan="3" class="text-right">
                            Excess Time Surcharge
                            <br><small style="font-weight: normal; color: #666;">
                                (<?php echo number_format($excess_hours, 2); ?> excess hours)
                            </small>
                        </td>
                        <td class="text-right">₱<?php echo number_format($surcharge_amount, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                <?php endif; ?>
                <tr class="total-row">
                    <td colspan="3" class="text-right"><strong>TOTAL AMOUNT DUE</strong></td>
                    <td class="text-right"><strong>₱<?php echo number_format($downpayment_percent > 0 ? $balance : $final_total, 2); ?></strong></td>
                </tr>
            </tfoot>
        </table>

        <?php if ($booking->notes): ?>
        <div class="info-box">
            <div class="info-box-title">SPECIAL REQUESTS / NOTES</div>
            <p><?php echo nl2br(esc_html($booking->notes)); ?></p>
        </div>
        <?php endif; ?>

        <?php if (!empty($payment_methods)): ?>
        <div class="info-box">
            <div class="info-box-title">PAYMENT METHODS</div>
            <?php foreach ($payment_methods as $method): ?>
                <div class="payment-method-item">
                    <strong><?php echo esc_html($method['name']); ?></strong>
                    <?php if (!empty($method['account_name'])): ?>
                        <br><span style="font-size: 9pt;">Account Name: <?php echo esc_html($method['account_name']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($method['account_number'])): ?>
                        <br><span style="font-size: 9pt;">Account Number: <?php echo esc_html($method['account_number']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($method['description'])): ?>
                        <br><span style="font-size: 9pt; color: #666;"><?php echo esc_html($method['description']); ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($show_payment_btn && $payment_source === 'op' && bntm_is_module_enabled('op')): ?>
        <div style="margin-top: 25px; text-align: center;" class="no-print">
            <a href="<?php echo get_permalink(get_page_by_path('payment')) . '?booking=' . esc_attr($booking->rand_id); ?>" 
               class="bntm-btn bntm-btn-primary" style="padding: 12px 30px; font-size: 16px;">
                Pay Now
            </a>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($terms)): ?>
        <div class="info-box">
            <div class="info-box-title">TERMS & CONDITIONS</div>
            <p style="white-space: pre-line; font-size: 9pt;"><?php echo nl2br(esc_html($terms)); ?></p>
        </div>
        <?php endif; ?>
        
        <div class="invoice-footer">
            <p>Thank you for choosing our car rental services!</p>
            <?php if ($site_title): ?>
            <p><?php echo esc_html($site_title); ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// ============================================================================
// AJAX HANDLERS
// ============================================================================

function bntm_cr_upload_image_from_field($field_name) {
    if (empty($_FILES[$field_name]['name'])) {
        return null;
    }

    require_once(ABSPATH . 'wp-admin/includes/file.php');
    $uploaded = wp_handle_upload($_FILES[$field_name], ['test_form' => false]);
    if (isset($uploaded['url']) && empty($uploaded['error'])) {
        return $uploaded['url'];
    }

    return null;
}

function bntm_cr_upsert_row($table, array $data, array $formats, $id = 0) {
    global $wpdb;

    if ($id > 0) {
        return $wpdb->update($table, $data, ['id' => $id], $formats, ['%d']);
    }

    return $wpdb->insert($table, $data, $formats);
}

function bntm_ajax_cr_add_package() {
    check_ajax_referer('cr_package_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'cr_packages';
    $pricing_rules = bntm_cr_get_pricing_rules();
    $vehicle_category = sanitize_text_field($_POST['vehicle_category'] ?? bntm_cr_get_default_vehicle_category_slug());
    if (!isset($pricing_rules[$vehicle_category])) {
        $vehicle_category = bntm_cr_get_default_vehicle_category_slug();
    }
    $rule = $pricing_rules[$vehicle_category];
    
    // Handle file upload
    $photo_url = null;
    if (!empty($_FILES['package_photo']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        $uploaded = wp_handle_upload($_FILES['package_photo'], ['test_form' => false]);
        if (!isset($uploaded['error'])) {
            $photo_url = $uploaded['url'];
        }
    }
    
    $data = [
        'rand_id' => bntm_rand_id(),
        'business_id' => get_current_user_id(),
        'package_name' => sanitize_text_field($_POST['package_name']),
        'city' => bntm_cr_normalize_city($_POST['city'] ?? 'cebu'),
        'vehicle_category' => $vehicle_category,
        'boat_type' => sanitize_text_field($_POST['boat_type']),
        'daily_rate' => floatval($rule['base_fee']),
        'hourly_surcharge' => floatval($rule['overtime_rate']),
        'max_pax' => intval($_POST['max_pax']),
        'photo_url' => $photo_url,
        'description' => sanitize_textarea_field($_POST['description']),
        'status' => 'active'
    ];
    
    $result = $wpdb->insert($table, $data, ['%s','%d','%s','%s','%s','%s','%f','%f','%d','%s','%s','%s']);
    
    if ($result) {
        wp_send_json_success(['message' => 'Package added successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to add package']);
    }
}


function bntm_ajax_cr_update_package() {
check_ajax_referer('cr_package_nonce', 'nonce');

if (!is_user_logged_in()) {
    wp_send_json_error(['message' => 'Unauthorized']);
}

global $wpdb;
$table = $wpdb->prefix . 'cr_packages';

$result = $wpdb->update(
    $table,
    ['status' => sanitize_text_field($_POST['status'])],
    ['id' => intval($_POST['package_id'])],
    ['%s'],
    ['%d']
);

if ($result !== false) {
    wp_send_json_success(['message' => 'Package updated']);
} else {
    wp_send_json_error(['message' => 'Failed to update package']);
}
}


function bntm_ajax_cr_update_package_full() {
    check_ajax_referer('cr_package_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'cr_packages';
    $pricing_rules = bntm_cr_get_pricing_rules();
    $vehicle_category = sanitize_text_field($_POST['vehicle_category'] ?? bntm_cr_get_default_vehicle_category_slug());
    if (!isset($pricing_rules[$vehicle_category])) {
        $vehicle_category = bntm_cr_get_default_vehicle_category_slug();
    }
    $rule = $pricing_rules[$vehicle_category];
    
    $package_id = intval($_POST['package_id']);
    
    // Handle file upload if new photo provided
    $photo_url = null;
    if (!empty($_FILES['package_photo']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        $uploaded = wp_handle_upload($_FILES['package_photo'], ['test_form' => false]);
        if (!isset($uploaded['error'])) {
            $photo_url = $uploaded['url'];
        }
    }
    
    $data = [
        'package_name' => sanitize_text_field($_POST['package_name']),
        'city' => bntm_cr_normalize_city($_POST['city'] ?? 'cebu'),
        'vehicle_category' => $vehicle_category,
        'boat_type' => sanitize_text_field($_POST['boat_type']),
        'daily_rate' => floatval($rule['base_fee']),
        'hourly_surcharge' => floatval($rule['overtime_rate']),
        'max_pax' => intval($_POST['max_pax']),
        'description' => sanitize_textarea_field($_POST['description'])
    ];
    
    $formats = ['%s', '%s', '%s', '%s', '%f', '%f', '%d', '%s'];
    
    // Only update photo if new one was uploaded
    if ($photo_url) {
        $data['photo_url'] = $photo_url;
        $formats[] = '%s';
    }
    
    $result = $wpdb->update(
        $table,
        $data,
        ['id' => $package_id],
        $formats,
        ['%d']
    );
    
    if ($result !== false) {
        wp_send_json_success(['message' => 'Package updated successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to update package']);
    }
}

function bntm_ajax_cr_delete_package() {
check_ajax_referer('cr_package_nonce', 'nonce');

if (!is_user_logged_in()) {
    wp_send_json_error(['message' => 'Unauthorized']);
}

global $wpdb;
$table = $wpdb->prefix . 'cr_packages';

$result = $wpdb->delete($table, ['id' => intval($_POST['package_id'])], ['%d']);

if ($result) {
    wp_send_json_success(['message' => 'Package deleted']);
} else {
    wp_send_json_error(['message' => 'Failed to delete package']);
}
}

function bntm_ajax_cr_update_booking_status() {
check_ajax_referer('cr_booking_nonce', 'nonce');

if (!is_user_logged_in()) {
    wp_send_json_error(['message' => 'Unauthorized']);
}

global $wpdb;
$table = $wpdb->prefix . 'cr_bookings';

$result = $wpdb->update(
    $table,
    ['status' => sanitize_text_field($_POST['status'])],
    ['id' => intval($_POST['booking_id'])],
    ['%s'],
    ['%d']
);

if ($result !== false) {
    wp_send_json_success(['message' => 'Status updated']);
} else {
    wp_send_json_error(['message' => 'Failed to update status']);
}
}

function bntm_ajax_cr_edit_booking() {
    check_ajax_referer('cr_booking_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'cr_bookings';
    $packages_table = $wpdb->prefix . 'cr_packages';
    
    $other_fees = isset($_POST['other_fees']) ? stripslashes($_POST['other_fees']) : '[]';
    
    // Extract excess hours from the string (e.g., "2.50 hours" -> "2.50")
    $excess_hours_raw = isset($_POST['excess_hours']) ? $_POST['excess_hours'] : '0';
    $excess_hours = 0;
    if (is_string($excess_hours_raw) && strpos($excess_hours_raw, 'hours') !== false) {
        $excess_hours = floatval(str_replace(' hours', '', $excess_hours_raw));
    } else {
        $excess_hours = floatval($excess_hours_raw);
    }
    
    $data = [
        'package_id' => intval($_POST['package_id']),
        'customer_name' => sanitize_text_field($_POST['customer_name']),
        'customer_email' => sanitize_email($_POST['customer_email']),
        'customer_phone' => sanitize_text_field($_POST['customer_phone']),
        'start_date' => sanitize_text_field($_POST['start_date']),
        'end_date' => sanitize_text_field($_POST['end_date']),
        'number_of_days' => intval($_POST['number_of_days']),
        'check_in_time' => !empty($_POST['check_in_time']) ? sanitize_text_field($_POST['check_in_time']) : null,
        'check_out_time' => !empty($_POST['check_out_time']) ? sanitize_text_field($_POST['check_out_time']) : null,
        'excess_hours' => $excess_hours,
        'surcharge_amount' => floatval($_POST['surcharge_amount']),
        'number_of_pax' => intval($_POST['number_of_pax']),
        'package_amount' => floatval($_POST['package_amount']),
        'other_fees' => $other_fees,
        'total_amount' => floatval($_POST['total_amount']),
        'status' => sanitize_text_field($_POST['status']),
        'notes' => sanitize_textarea_field($_POST['notes'])
    ];

    $existing_booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", intval($_POST['booking_id'])));
    $selected_package = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$packages_table} WHERE id = %d", intval($_POST['package_id'])));

    if ($existing_booking && $selected_package && !empty($existing_booking->destination)) {
        $route = bntm_cr_find_route($existing_booking->city ?? ($selected_package->city ?? 'cebu'), $existing_booking->destination, $existing_booking->base_point ?? '');
        $fees_total = 0;
        $decoded_fees = json_decode($other_fees, true);
        if (is_array($decoded_fees)) {
            foreach ($decoded_fees as $fee) {
                $fees_total += floatval($fee['amount'] ?? 0);
            }
        }

        if ($route) {
            $calc = bntm_cr_calculate_total(
                $selected_package->vehicle_category ?? bntm_cr_get_default_vehicle_category_slug(),
                $route['distance_km'],
                floatval($existing_booking->total_hours ?? 0),
                $route
            );

            $data['package_amount'] = $calc['base_rate'];
            $data['excess_hours'] = $calc['overtime_hours'];
            $data['surcharge_amount'] = $calc['overtime_charge'];
            $data['total_amount'] = $calc['base_rate'] + $calc['overtime_charge'] + $fees_total;
        }
    }
    
    $format = [
        '%d',  // package_id
        '%s',  // customer_name
        '%s',  // customer_email
        '%s',  // customer_phone
        '%s',  // start_date
        '%s',  // end_date
        '%d',  // number_of_days
        '%s',  // check_in_time
        '%s',  // check_out_time
        '%f',  // excess_hours
        '%f',  // surcharge_amount
        '%d',  // number_of_pax
        '%f',  // package_amount
        '%s',  // other_fees
        '%f',  // total_amount
        '%s',  // status
        '%s'   // notes
    ];
    
    $result = $wpdb->update(
        $table,
        $data,
        ['id' => intval($_POST['booking_id'])],
        $format,
        ['%d']
    );
    
    if ($result !== false) {
        wp_send_json_success(['message' => 'Booking updated successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to update booking: ' . $wpdb->last_error]);
    }
}
function bntm_ajax_cr_delete_booking() {
check_ajax_referer('cr_booking_nonce', 'nonce');

if (!is_user_logged_in()) {
    wp_send_json_error(['message' => 'Unauthorized']);
}

global $wpdb;
$table = $wpdb->prefix . 'cr_bookings';

$result = $wpdb->delete($table, ['id' => intval($_POST['booking_id'])], ['%d']);

if ($result) {
    wp_send_json_success(['message' => 'Booking deleted']);
} else {
    wp_send_json_error(['message' => 'Failed to delete booking']);
}
}

function bntm_ajax_cr_submit_booking() {
    check_ajax_referer('cr_form_nonce', 'nonce');

    global $wpdb;
    $bookings_table = $wpdb->prefix . 'cr_bookings';
    $packages_table = $wpdb->prefix . 'cr_packages';
    $inventory_table = $wpdb->prefix . 'cr_car_inventory';

    $rental_type = sanitize_text_field($_POST['rental_type'] ?? 'commercial');
    $city = bntm_cr_normalize_city($_POST['selected_city'] ?? $_POST['city'] ?? 'cebu');
    $car_id = intval($_POST['car_id'] ?? 0);
    $car = $car_id > 0 ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$inventory_table} WHERE id = %d", $car_id)) : null;
    $vehicle_type = sanitize_text_field($_POST['vehicle_type'] ?? ($car->vehicle_type ?? 'sedan'));
    $customer_name = sanitize_text_field($_POST['customer_name'] ?? '');
    $customer_email = sanitize_email($_POST['customer_email'] ?? '');
    $customer_phone = sanitize_text_field($_POST['customer_phone'] ?? '');
    $notes = sanitize_textarea_field($_POST['notes'] ?? '');
    $start_date = sanitize_text_field($_POST['start_date'] ?? '');
    $end_date = sanitize_text_field($_POST['end_date'] ?? '');
    $number_of_pax = intval($_POST['number_of_pax'] ?? 1);
    $business_id = $car ? intval($car->business_id) : get_current_user_id();
    $car_capacity = $car ? intval($car->seating_capacity ?? 0) : 0;

    $package_id = 0;
    $base_point = '';
    $destination = '';
    $location_name = '';
    $distance_km = 0;
    $total_hours = 0;
    $number_of_days = intval($_POST['number_of_days'] ?? 1);
    $base_fee = 0;
    $rate_per_km = 0;
    $overtime_rate = 0;
    $daily_rate = 0;
    $package_amount = 0;
    $excess_hours = 0;
    $surcharge_amount = 0;
    $airport_surcharge = 0;
    $pricing_type = 'formula';
    $total_amount = 0;

    if ($rental_type === 'commercial') {
        $hours = max(0, floatval($_POST['commercial_hours'] ?? $_POST['total_hours'] ?? 0));
        $airport = sanitize_text_field($_POST['airport_pickup'] ?? 'no') === 'yes';
        $vehicle_type = sanitize_text_field($_POST['commercial_vehicle_type'] ?? $vehicle_type);
        $car_id = intval($_POST['car_id'] ?? $car_id);
        $car = $car_id > 0 ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$inventory_table} WHERE id = %d", $car_id)) : $car;
        if (!$car && $car_id <= 0) {
            wp_send_json_error(['message' => 'Please select a car from the inventory.']);
        }
        if ($car && bntm_cr_normalize_city($car->city ?? 'cebu') !== $city) {
            wp_send_json_error(['message' => 'Selected car does not belong to the chosen city.']);
        }
        if ($car && !empty($car->vehicle_type) && sanitize_text_field($car->vehicle_type) !== $vehicle_type) {
            wp_send_json_error(['message' => 'Selected vehicle type does not match the inventory car.']);
        }
        if ($car_capacity > 0 && $number_of_pax > $car_capacity) {
            wp_send_json_error(['message' => 'Passengers exceed the selected car capacity.']);
        }

        $calc = bntm_cr_calculate_commercial_price($hours, $city, $vehicle_type, $airport, $car_id > 0 ? $car_id : null);
        if (!$calc) {
            wp_send_json_error(['message' => 'Commercial rate not available for this exact duration, city, and vehicle type.']);
        }

        $package_amount = floatval($calc['base_price']);
        $airport_surcharge = floatval($calc['airport_surcharge']);
        $excess_hours = 0;
        $surcharge_amount = $airport_surcharge;
        $total_amount = floatval($calc['total']);
        $total_hours = $hours;
        $number_of_days = 1;
        $base_fee = floatval($calc['flat_rate']);
        $daily_rate = $base_fee;
        $overtime_rate = 0;
        $pricing_type = 'commercial';
    } elseif ($rental_type === 'self_drive') {
        $days = max(1, intval($_POST['sd_days'] ?? $_POST['number_of_days'] ?? 1));
        $additional_hours = max(0, floatval($_POST['sd_additional_hours'] ?? 0));
        $vehicle_type = sanitize_text_field($_POST['sd_vehicle_type'] ?? $vehicle_type);
        $car_id = intval($_POST['car_id'] ?? $car_id);
        $car = $car_id > 0 ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$inventory_table} WHERE id = %d", $car_id)) : $car;
        if (!$car && $car_id <= 0) {
            wp_send_json_error(['message' => 'Please select a car from the inventory.']);
        }
        if ($car && $car->city !== $city) {
            wp_send_json_error(['message' => 'Selected car does not belong to the chosen city.']);
        }
        if ($car && !empty($car->vehicle_type)) {
            $vehicle_type = sanitize_text_field($car->vehicle_type);
        }
        if ($car_capacity > 0 && $number_of_pax > $car_capacity) {
            wp_send_json_error(['message' => 'Passengers exceed the selected car capacity.']);
        }

        $calc = bntm_cr_calculate_self_drive_price($days, $city, $vehicle_type, $additional_hours, $car_id);
        if (!$calc) {
            wp_send_json_error(['message' => 'Self-drive rate not available for this city and vehicle type.']);
        }

        $package_amount = floatval($calc['base_price']);
        $surcharge_amount = floatval($calc['exceeding_charge']);
        $total_amount = floatval($calc['total']);
        $number_of_days = $days;
        $total_hours = floatval($days * 24 + $additional_hours);
        $base_fee = floatval($calc['rate_per_24hours']);
        $daily_rate = $base_fee;
        $overtime_rate = floatval($calc['exceeding_rate']);
        $pricing_type = 'self_drive';
    } elseif ($rental_type === 'out_of_town') {
        $location_name = sanitize_text_field($_POST['oot_location'] ?? '');
        $hours = max(0, floatval($_POST['oot_hours'] ?? 0));
        $vehicle_type = sanitize_text_field($_POST['oot_vehicle_type'] ?? $vehicle_type);
        $car_id = intval($_POST['car_id'] ?? $car_id);
        $car = $car_id > 0 ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$inventory_table} WHERE id = %d", $car_id)) : $car;
        if (!$car && $car_id <= 0) {
            wp_send_json_error(['message' => 'Please select a car from the inventory.']);
        }
        if ($car && bntm_cr_normalize_city($car->city ?? 'cebu') !== $city) {
            wp_send_json_error(['message' => 'Selected car does not belong to the chosen city.']);
        }
        if ($car && !empty($car->vehicle_type) && sanitize_text_field($car->vehicle_type) !== $vehicle_type) {
            wp_send_json_error(['message' => 'Selected vehicle type does not match the inventory car.']);
        }
        if ($car_capacity > 0 && $number_of_pax > $car_capacity) {
            wp_send_json_error(['message' => 'Passengers exceed the selected car capacity.']);
        }

        $calc = bntm_cr_calculate_out_of_town_price($location_name, $city, $vehicle_type, $hours, $car_id > 0 ? $car_id : null);
        if (!$calc) {
            wp_send_json_error(['message' => 'Out-of-town rate not available for this selection.']);
        }

        $package_amount = floatval($calc['trip_price']);
        $surcharge_amount = floatval($calc['exceeding_charge']);
        $total_amount = floatval($calc['total']);
        $distance_km = floatval($calc['km_distance']);
        $total_hours = $hours;
        $number_of_days = 1;
        $base_fee = floatval($calc['trip_price']);
        $daily_rate = $base_fee;
        $overtime_rate = floatval($calc['exceeding_rate_per_hour']);
        $pricing_type = 'out_of_town';
        $destination = $location_name;
    } else {
        $package_id = intval($_POST['package_id'] ?? 0);
        $package = $package_id > 0 ? $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $packages_table WHERE id = %d AND status='active'",
            $package_id
        )) : null;

        if (!$package) {
            wp_send_json_error(['message' => 'Invalid booking selection']);
        }

        $number_of_pax = max(1, intval($_POST['number_of_pax'] ?? 1));
        if ($number_of_pax > intval($package->max_pax)) {
            wp_send_json_error(['message' => 'Number of passengers exceeds package limit']);
        }

        $destination = sanitize_text_field($_POST['destination'] ?? '');
        $base_point = sanitize_text_field($_POST['base_point'] ?? '');
        $total_hours = floatval($_POST['total_hours'] ?? 0);
        $route = bntm_cr_find_route($city, $destination, $base_point);
        if (!$route) {
            wp_send_json_error(['message' => 'Please select a valid destination route.']);
        }

        $calc = bntm_cr_calculate_total($package->vehicle_category ?? bntm_cr_get_default_vehicle_category_slug(), floatval($route['distance_km']), $total_hours, $route);
        $distance_km = floatval($calc['distance_km']);
        $number_of_days = intval($_POST['number_of_days'] ?? 1);
        $base_fee = floatval($calc['base_fee']);
        $rate_per_km = floatval($calc['rate_per_km']);
        $overtime_rate = floatval($calc['overtime_rate']);
        $daily_rate = floatval($calc['base_fee']);
        $package_amount = floatval($calc['base_rate']);
        $excess_hours = floatval($calc['overtime_hours']);
        $surcharge_amount = floatval($calc['overtime_charge']);
        $pricing_type = $calc['pricing_type'];
        $business_id = intval($package->business_id);
        $vehicle_type = sanitize_text_field($package->vehicle_category ?? bntm_cr_get_default_vehicle_category_slug());
        $total_amount = floatval($calc['total_cost']);
    }

    $data = [
        'rand_id' => bntm_rand_id(),
        'business_id' => $business_id,
        'package_id' => $package_id,
        'car_id' => $car_id > 0 ? $car_id : null,
        'city' => $city,
        'rental_type' => $rental_type,
        'vehicle_category' => $vehicle_type,
        'customer_name' => $customer_name,
        'customer_email' => $customer_email,
        'customer_phone' => $customer_phone,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'check_in_time' => null,
        'check_out_time' => null,
        'base_point' => $base_point,
        'destination' => $destination,
        'location_name' => $location_name,
        'distance_km' => $distance_km,
        'total_hours' => $total_hours,
        'number_of_days' => $number_of_days,
        'number_of_pax' => $number_of_pax,
        'base_fee' => $base_fee,
        'rate_per_km' => $rate_per_km,
        'overtime_rate' => $overtime_rate,
        'daily_rate' => $daily_rate,
        'package_amount' => $package_amount,
        'excess_hours' => $excess_hours,
        'surcharge_amount' => $surcharge_amount,
        'airport_surcharge' => $airport_surcharge,
        'pricing_type' => $pricing_type,
        'other_fees' => '[]',
        'total_amount' => $total_amount,
        'notes' => $notes,
        'status' => 'contacted',
    ];

    $result = $wpdb->insert($bookings_table, $data, [
        '%s','%d','%d','%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%f','%f','%d','%d','%f','%f','%f','%f','%f','%f','%f','%f','%s','%s','%f','%s','%s'
    ]);

    if ($result) {
        wp_send_json_success(['message' => 'Booking request submitted successfully!']);
    }

    wp_send_json_error(['message' => 'Failed to submit booking.']);
}

function bntm_ajax_cr_get_inventory_cars() {
    $city = bntm_cr_normalize_city($_POST['city'] ?? 'cebu');
    $vehicle_type = sanitize_text_field($_POST['vehicle_type'] ?? '');
    $cars = bntm_cr_get_cars_by_city($city, 'available', $vehicle_type);

    $payload = [];
    foreach ($cars as $car) {
        $payload[] = [
            'id' => intval($car->id),
            'label' => trim($car->car_make . ' ' . $car->car_model),
            'plate_number' => $car->plate_number,
            'vehicle_type' => $car->vehicle_type,
            'city' => $car->city,
            'seating_capacity' => intval($car->seating_capacity),
            'num_cars' => intval($car->num_cars),
            'transmission' => $car->transmission,
            'fuel_type' => $car->fuel_type,
            'photo_url' => $car->photo_url,
            'specifications' => $car->specifications,
        ];
    }

    wp_send_json_success($payload);
}

function bntm_ajax_cr_save_inventory_car() {
    check_ajax_referer('cr_fleet_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'cr_car_inventory';
    $car_id = intval($_POST['id'] ?? 0);
    $rand_id = $car_id > 0 ? $wpdb->get_var($wpdb->prepare("SELECT rand_id FROM {$table} WHERE id = %d", $car_id)) : bntm_rand_id();
    $photo_url = bntm_cr_upload_image_from_field('car_photo');

    $data = [
        'rand_id' => $rand_id,
        'business_id' => get_current_user_id(),
        'car_make' => sanitize_text_field($_POST['car_make'] ?? ''),
        'car_model' => sanitize_text_field($_POST['car_model'] ?? ''),
        'plate_number' => sanitize_text_field($_POST['plate_number'] ?? ''),
        'year' => intval($_POST['year'] ?? 0),
        'city' => bntm_cr_normalize_city($_POST['city'] ?? 'cebu'),
        'vehicle_type' => sanitize_text_field($_POST['vehicle_type'] ?? 'sedan'),
        'specifications' => wp_json_encode(bntm_cr_parse_specs_text(wp_unslash($_POST['specifications'] ?? ''))),
        'transmission' => sanitize_text_field($_POST['transmission'] ?? 'automatic'),
        'fuel_type' => sanitize_text_field($_POST['fuel_type'] ?? 'diesel'),
        'seating_capacity' => intval($_POST['seating_capacity'] ?? 5),
        'num_cars' => intval($_POST['num_cars'] ?? 1),
        'status' => sanitize_text_field($_POST['status'] ?? 'available'),
    ];

    if ($photo_url) {
        $data['photo_url'] = $photo_url;
    }

    $formats = ['%s','%d','%s','%s','%s','%d','%s','%s','%s','%s','%s','%d','%d','%s'];
    if ($photo_url) {
        $formats[] = '%s';
    }

    $result = bntm_cr_upsert_row($table, $data, $formats, $car_id);
    $result !== false ? wp_send_json_success(['message' => 'Car saved successfully']) : wp_send_json_error(['message' => 'Failed to save car']);
}

function bntm_ajax_cr_delete_inventory_car() {
    check_ajax_referer('cr_fleet_nonce', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $result = $wpdb->delete($wpdb->prefix . 'cr_car_inventory', ['id' => intval($_POST['id'] ?? 0)], ['%d']);
    $result !== false ? wp_send_json_success(['message' => 'Car deleted']) : wp_send_json_error(['message' => 'Failed to delete car']);
}

function bntm_ajax_cr_save_commercial_rate() {
    check_ajax_referer('cr_fleet_nonce', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'cr_commercial_rates';
    $rate_id = intval($_POST['id'] ?? 0);
    $rand_id = $rate_id > 0 ? $wpdb->get_var($wpdb->prepare("SELECT rand_id FROM {$table} WHERE id = %d", $rate_id)) : bntm_rand_id();
    $car_id = intval($_POST['car_id'] ?? 0);
    if ($car_id <= 0) {
        wp_send_json_error(['message' => 'Please select an assigned vehicle for this commercial rate.']);
    }

    $car = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}cr_car_inventory WHERE id = %d",
        $car_id
    ));
    if (!$car) {
        wp_send_json_error(['message' => 'Selected vehicle was not found in inventory.']);
    }

    $selected_city = bntm_cr_normalize_city($_POST['city'] ?? 'cebu');
    if (bntm_cr_normalize_city($car->city ?? 'cebu') !== $selected_city) {
        wp_send_json_error(['message' => 'Selected vehicle must belong to the chosen city.']);
    }

    $vehicle_type = sanitize_text_field($_POST['vehicle_type'] ?? 'sedan');
    if (!empty($car->vehicle_type) && sanitize_text_field($car->vehicle_type) !== $vehicle_type) {
        wp_send_json_error(['message' => 'Selected vehicle type does not match the inventory car.']);
    }

    $data = [
        'rand_id' => $rand_id,
        'business_id' => get_current_user_id(),
        'city' => $selected_city,
        'vehicle_type' => $vehicle_type,
        'car_id' => $car_id,
        'duration_hours' => floatval($_POST['duration_hours'] ?? 0),
        'flat_rate' => floatval($_POST['flat_rate'] ?? 0),
        'airport_surcharge' => floatval($_POST['airport_surcharge'] ?? 0),
        'status' => sanitize_text_field($_POST['status'] ?? 'active'),
    ];
    $result = bntm_cr_upsert_row($table, $data, ['%s','%d','%s','%s','%d','%f','%f','%f','%s'], $rate_id);
    $result !== false ? wp_send_json_success(['message' => 'Commercial rate saved']) : wp_send_json_error(['message' => 'Failed to save rate']);
}

function bntm_ajax_cr_delete_commercial_rate() {
    check_ajax_referer('cr_fleet_nonce', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    global $wpdb;
    $result = $wpdb->delete($wpdb->prefix . 'cr_commercial_rates', ['id' => intval($_POST['id'] ?? 0)], ['%d']);
    $result !== false ? wp_send_json_success(['message' => 'Commercial rate deleted']) : wp_send_json_error(['message' => 'Failed to delete rate']);
}

function bntm_ajax_cr_save_self_drive_rate() {
    check_ajax_referer('cr_fleet_nonce', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'cr_self_drive_rates';
    $rate_id = intval($_POST['id'] ?? 0);
    $rand_id = $rate_id > 0 ? $wpdb->get_var($wpdb->prepare("SELECT rand_id FROM {$table} WHERE id = %d", $rate_id)) : bntm_rand_id();
    $car_id = intval($_POST['car_id'] ?? 0);
    if ($car_id <= 0) {
        wp_send_json_error(['message' => 'Please select a vehicle for this self-drive rate.']);
    }

    $car = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}cr_car_inventory WHERE id = %d",
        $car_id
    ));
    if (!$car) {
        wp_send_json_error(['message' => 'Selected vehicle was not found in inventory.']);
    }

    $selected_city = bntm_cr_normalize_city($_POST['city'] ?? 'cebu');
    if (bntm_cr_normalize_city($car->city ?? 'cebu') !== $selected_city) {
        wp_send_json_error(['message' => 'Selected vehicle must belong to the chosen city.']);
    }

    $vehicle_type = sanitize_text_field($_POST['vehicle_type'] ?? 'sedan');
    if (!empty($car->vehicle_type) && sanitize_text_field($car->vehicle_type) !== $vehicle_type) {
        wp_send_json_error(['message' => 'Selected vehicle type does not match the inventory car.']);
    }

    $data = [
        'rand_id' => $rand_id,
        'business_id' => get_current_user_id(),
        'city' => $selected_city,
        'car_id' => $car_id,
        'vehicle_type' => $vehicle_type,
        'rate_per_24hours' => floatval($_POST['rate_per_24hours'] ?? 0),
        'exceeding_rate' => floatval($_POST['exceeding_rate'] ?? 0),
        'security_deposit' => floatval($_POST['security_deposit'] ?? 0),
        'status' => sanitize_text_field($_POST['status'] ?? 'active'),
    ];
    $result = bntm_cr_upsert_row($table, $data, ['%s','%d','%s','%d','%s','%f','%f','%f','%s'], $rate_id);
    $result !== false ? wp_send_json_success(['message' => 'Self-drive rate saved']) : wp_send_json_error(['message' => 'Failed to save rate']);
}

function bntm_ajax_cr_delete_self_drive_rate() {
    check_ajax_referer('cr_fleet_nonce', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    global $wpdb;
    $result = $wpdb->delete($wpdb->prefix . 'cr_self_drive_rates', ['id' => intval($_POST['id'] ?? 0)], ['%d']);
    $result !== false ? wp_send_json_success(['message' => 'Self-drive rate deleted']) : wp_send_json_error(['message' => 'Failed to delete rate']);
}

function bntm_ajax_cr_save_out_of_town_rate() {
    check_ajax_referer('cr_fleet_nonce', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'cr_out_of_town_rates';
    $rate_id = intval($_POST['id'] ?? 0);
    $rand_id = $rate_id > 0 ? $wpdb->get_var($wpdb->prepare("SELECT rand_id FROM {$table} WHERE id = %d", $rate_id)) : bntm_rand_id();
    $car_id = intval($_POST['car_id'] ?? 0);
    if ($car_id <= 0) {
        wp_send_json_error(['message' => 'Please select an assigned vehicle for this out-of-town rate.']);
    }

    $car = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}cr_car_inventory WHERE id = %d",
        $car_id
    ));
    if (!$car) {
        wp_send_json_error(['message' => 'Selected vehicle was not found in inventory.']);
    }

    $selected_city = bntm_cr_normalize_city($_POST['city'] ?? 'cebu');
    if (bntm_cr_normalize_city($car->city ?? 'cebu') !== $selected_city) {
        wp_send_json_error(['message' => 'Selected vehicle must belong to the chosen city.']);
    }

    $vehicle_type = sanitize_text_field($_POST['vehicle_type'] ?? 'sedan');
    if (!empty($car->vehicle_type) && sanitize_text_field($car->vehicle_type) !== $vehicle_type) {
        wp_send_json_error(['message' => 'Selected vehicle type does not match the inventory car.']);
    }

    $data = [
        'rand_id' => $rand_id,
        'business_id' => get_current_user_id(),
        'city' => $selected_city,
        'location_name' => sanitize_text_field($_POST['location_name'] ?? ''),
        'km_distance' => floatval($_POST['km_distance'] ?? 0),
        'vehicle_type' => $vehicle_type,
        'car_id' => $car_id,
        'rate_per_trip' => floatval($_POST['rate_per_trip'] ?? 0),
        'exceeding_rate_per_hour' => floatval($_POST['exceeding_rate_per_hour'] ?? 0),
        'status' => sanitize_text_field($_POST['status'] ?? 'active'),
    ];
    $result = bntm_cr_upsert_row($table, $data, ['%s','%d','%s','%s','%f','%s','%d','%f','%f','%s'], $rate_id);
    $result !== false ? wp_send_json_success(['message' => 'Out-of-town rate saved']) : wp_send_json_error(['message' => 'Failed to save rate']);
}

function bntm_ajax_cr_delete_out_of_town_rate() {
    check_ajax_referer('cr_fleet_nonce', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    global $wpdb;
    $result = $wpdb->delete($wpdb->prefix . 'cr_out_of_town_rates', ['id' => intval($_POST['id'] ?? 0)], ['%d']);
    $result !== false ? wp_send_json_success(['message' => 'Out-of-town rate deleted']) : wp_send_json_error(['message' => 'Failed to delete rate']);
}

function bntm_ajax_cr_save_payment_source() {
    check_ajax_referer('cr_payment_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $payment_source = sanitize_text_field($_POST['payment_source']);
    
    if (!in_array($payment_source, ['manual', 'op'])) {
        wp_send_json_error(['message' => 'Invalid payment source']);
    }

    bntm_set_setting('cr_payment_source', $payment_source);
    wp_send_json_success(['message' => 'Payment source saved!']);
}

function bntm_ajax_cr_add_payment_method() {
    check_ajax_referer('cr_payment_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $payment_methods = json_decode(bntm_get_setting('cr_payment_methods', '[]'), true);
    if (!is_array($payment_methods)) $payment_methods = [];

    $payment_methods[] = [
        'type' => sanitize_text_field($_POST['payment_type']),
        'name' => sanitize_text_field($_POST['payment_name']),
        'description' => sanitize_textarea_field($_POST['payment_description']),
        'account_name' => sanitize_text_field($_POST['account_name']),
        'account_number' => sanitize_text_field($_POST['account_number'])
    ];

    bntm_set_setting('cr_payment_methods', json_encode($payment_methods));
    wp_send_json_success(['message' => 'Payment method added!']);
}

function bntm_ajax_cr_remove_payment_method() {
    check_ajax_referer('cr_payment_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $index = intval($_POST['index']);
    $payment_methods = json_decode(bntm_get_setting('cr_payment_methods', '[]'), true);
    
    if (!is_array($payment_methods) || !isset($payment_methods[$index])) {
        wp_send_json_error(['message' => 'Payment method not found']);
    }

    array_splice($payment_methods, $index, 1);
    bntm_set_setting('cr_payment_methods', json_encode($payment_methods));
    
    wp_send_json_success(['message' => 'Payment method removed']);
}

function bntm_ajax_cr_save_booking_settings() {
    check_ajax_referer('cr_payment_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $downpayment = intval($_POST['downpayment_percentage']);
    if ($downpayment < 0 || $downpayment > 100) {
        wp_send_json_error(['message' => 'Down payment must be between 0-100%']);
    }

    $pricing_rules = [];
    $pricing_rules_raw = json_decode(wp_unslash($_POST['pricing_rules_json'] ?? ''), true);
    if (is_array($pricing_rules_raw)) {
        foreach ($pricing_rules_raw as $slug => $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $slug = sanitize_key($slug);
            $label = sanitize_text_field($rule['label'] ?? '');
            if ($slug === '' || $label === '') {
                continue;
            }

            $pricing_rules[$slug] = [
                'label' => $label,
                'base_fee' => floatval($rule['base_fee'] ?? 0),
                'rate_per_km' => floatval($rule['rate_per_km'] ?? 0),
                'overtime_rate' => floatval($rule['overtime_rate'] ?? 0),
            ];
        }
    }

    if (empty($pricing_rules)) {
        $pricing_rules = bntm_cr_get_default_pricing_rules();
    }

    $routes = bntm_cr_parse_route_definitions(wp_unslash($_POST['route_definitions'] ?? ''));
    if (empty($routes)) {
        $routes = bntm_cr_get_default_routes();
    }

    bntm_set_setting('cr_pricing_rules', wp_json_encode($pricing_rules));
    bntm_set_setting('cr_route_points', wp_json_encode($routes));
    bntm_set_setting('cr_downpayment_percentage', $downpayment);
    bntm_set_setting('cr_terms', sanitize_textarea_field($_POST['terms']));
    
    wp_send_json_success(['message' => 'Booking settings saved!']);
}

// ============================================================================
// NEW RENTAL SYSTEM AJAX HANDLERS
// ============================================================================

function bntm_ajax_cr_get_out_of_town_locations() {
    $city = sanitize_text_field($_POST['city'] ?? 'cebu');
    $locations = bntm_cr_get_out_of_town_locations($city);
    
    if (!empty($locations)) {
        wp_send_json_success($locations);
    } else {
        wp_send_json_error(['message' => 'No locations found']);
    }
}

function bntm_ajax_cr_calculate_commercial() {
    $city = sanitize_text_field($_POST['city'] ?? 'cebu');
    $vehicle_type = sanitize_text_field($_POST['vehicle_type'] ?? '');
    $car_id = intval($_POST['car_id'] ?? 0);
    $hours = floatval($_POST['hours'] ?? 0);
    $airport = sanitize_text_field($_POST['airport'] ?? 'no') === 'yes';
    
    $pricing = bntm_cr_calculate_commercial_price($hours, $city, $vehicle_type, $airport, $car_id > 0 ? $car_id : null);
    
    if ($pricing) {
        wp_send_json_success($pricing);
    } else {
        wp_send_json_error(['message' => 'Pricing not available for this selection']);
    }
}

function bntm_ajax_cr_calculate_self_drive() {
    $city = sanitize_text_field($_POST['city'] ?? 'cebu');
    $vehicle_type = sanitize_text_field($_POST['vehicle_type'] ?? '');
    $car_id = intval($_POST['car_id'] ?? 0);
    $days = intval($_POST['days'] ?? 0);
    $additional_hours = floatval($_POST['additional_hours'] ?? 0);
    
    $pricing = bntm_cr_calculate_self_drive_price($days, $city, $vehicle_type, $additional_hours, $car_id > 0 ? $car_id : null);
    
    if ($pricing) {
        wp_send_json_success($pricing);
    } else {
        wp_send_json_error(['message' => 'Pricing not available for this selection']);
    }
}

function bntm_ajax_cr_calculate_out_of_town() {
    $city = sanitize_text_field($_POST['city'] ?? 'cebu');
    $location = sanitize_text_field($_POST['location'] ?? '');
    $vehicle_type = sanitize_text_field($_POST['vehicle_type'] ?? '');
    $car_id = intval($_POST['car_id'] ?? 0);
    $hours = floatval($_POST['hours'] ?? 0);
    
    $pricing = bntm_cr_calculate_out_of_town_price($location, $city, $vehicle_type, $hours, $car_id > 0 ? $car_id : null);
    
    if ($pricing) {
        wp_send_json_success($pricing);
    } else {
        wp_send_json_error(['message' => 'Pricing not available for this selection']);
    }
}

// Allow booking form to be embedded in iframe - use send_headers action
add_action('send_headers', 'bntm_cr_allow_iframe_embed');
function bntm_cr_allow_iframe_embed() {
    global $post;
    
    // Check if we're on a page (avoid errors on non-page requests)
    if (is_page() && isset($post->post_content)) {
        // Check if current page has the booking form shortcode
        if (has_shortcode($post->post_content, 'car_rental_booking_form') || 
            has_shortcode($post->post_content, 'car_rental_booking_form_embed')) {
            // Remove X-Frame-Options header to allow iframe embedding
            header_remove('X-Frame-Options');
            // Allow embedding from any origin
            header('Content-Security-Policy: frame-ancestors *');
            // Also set alternative header for older browsers
            header('X-Frame-Options: ALLOWALL');
        }
    }
}
