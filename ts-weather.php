<?php
/**
 * Plugin Name: TS Weather
 * Description: Automatically imports weather data from a predefined CSV file into the WordPress database and displays it using a shortcode.
 * Version: 1.0
 * Author: Hafiz Hamza Javed
 * Author URI: https://github.com/HafizHamzaCS/scrap-weather-data-plugin
 */

// Prevent direct access to the file.
if (!defined('ABSPATH')) {
    exit;
}

function weather_import_enqueue_styles() {
    // Path to the CSS file in the plugin folder
    wp_enqueue_style('weather-import-style', plugin_dir_url(__FILE__) . 'css/style.css');
}
add_action('wp_enqueue_scripts', 'weather_import_enqueue_styles');

// Hook to run the CSV import process when the plugin is activated.
register_activation_hook(__FILE__, 'weather_import_activate');

register_activation_hook(__FILE__, 'weather_import_activate');

function weather_import_activate() {
    global $wpdb;

    // Table name for storing weather data.
    $table_name = $wpdb->prefix . 'weather_data';
    $charset_collate = $wpdb->get_charset_collate();

    // Create the table if it doesn't exist.
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        region VARCHAR(255) NOT NULL,
        snow_valley VARCHAR(50),
        snow_mountain VARCHAR(50),
        new_snow VARCHAR(50),
        lifts_open INT(11),
        report_date VARCHAR(50)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}



// Add both Weather Settings and Scrap Cron Settings to the admin menu
add_action('admin_menu', 'weather_settings_menu');
function weather_settings_menu() {
    add_menu_page(
        'Weather Settings', // Page title
        'Weather', // Menu title
        'manage_options', // Capability
        'access_weather_settings', // Custom capability
        'weather-settings', // Menu slug
        'weather_settings_page', // Callback function
        'dashicons-cloud', // Icon
        81 // Position
    );

    // Add Scrap Cron Settings as a sub-menu item
    add_submenu_page(
        'weather-settings', // Parent slug
        'Scrap Cron Settings', // Page title
        'access_weather_settings', // Custom capability
        'Scrap Cron', // Menu title
        'manage_options', // Capability
        'scrap-cron-settings', // Submenu slug
        'scrap_cron_settings_page' // Callback function
    );
}

// Weather Settings page
function weather_settings_page() {
    global $wpdb;

    // Table name.
    $table_name = $wpdb->prefix . 'weather_data';

    // Get all regions from the database.
    $regions = $wpdb->get_results("SELECT DISTINCT region FROM $table_name");

    if (isset($_POST['save_region'])) {
        // Save the selected region to options.
        $new_region = sanitize_text_field($_POST['region']);
        update_option('selected_weather_region', $new_region);

        // Clear cache to ensure fresh data load.
        wp_cache_delete('alloptions', 'options');

        echo '<div class="updated"><p>Region successfully updated!</p></div>';
    }

    $selected_region = get_option('selected_weather_region', '');

    ?>
    <div class="wrap">
        <h2>Weather Settings</h2>
        <form method="post" action="">
            <label for="region">Select Region:</label>
            <select name="region" id="region">
                <option value="">Select a Region</option>
                <?php foreach ($regions as $region): ?>
                    <option value="<?php echo esc_attr($region->region); ?>" <?php selected($region->region, $selected_region); ?>>
                        <?php echo esc_html($region->region); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="submit" name="save_region" value="Save Region" class="button-primary" /><br>
            <div>
                <span>Weather page shortcode : [weather_region_data]</span>
            </div>
        </form>
    </div>
    <?php
}


function scrap_cron_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Check if form was submitted
    if (isset($_POST['scrap_cron_interval'])) {
        update_option('scrap_cron_interval', sanitize_text_field($_POST['scrap_cron_interval']));
        echo '<div class="updated"><p>Settings saved!</p></div>';
    }

    // Get current interval setting
    $interval = get_option('scrap_cron_interval', '24'); // default to 24 hours
    ?>
    <div class="wrap">
        <?php 

        // Path to your scrap.php file
        $scrap_file = plugin_dir_path(__FILE__) . 'ts-automation.php';

        // Run the scrap script
        include_once($scrap_file);

         ?>
        <h1>Scrap Cron Settings</h1>
        <form method="POST">
            <label for="scrap_cron_interval">Set Cron Job Interval:</label>
            <select name="scrap_cron_interval" id="scrap_cron_interval">
                <option value="1" <?php selected($interval, '1'); ?>>1 Hour</option>
                <option value="6" <?php selected($interval, '6'); ?>>6 Hours</option>
                <option value="12" <?php selected($interval, '12'); ?>>12 Hours</option>
                <option value="24" <?php selected($interval, '24'); ?>>24 Hours</option>
            </select>
            <input type="submit" value="Save Settings" class="button-primary" />
        </form>
    </div>
    <?php
}// Scrap Cron Settings page

// Register the [weather_region_data] shortcode
function register_weather_region_data_shortcode() {
    add_shortcode('weather_region_data', 'weather_region_data_shortcode');
}
add_action('init', 'register_weather_region_data_shortcode');



function weather_region_data_shortcode($atts) {
    global $wpdb;

    wp_cache_delete('alloptions', 'options');

    // Get region from shortcode attributes (if any)
    $region = isset($atts['region']) ? sanitize_text_field($atts['region']) : get_option('selected_weather_region', '');

    if (empty($region)) {
        return '<p>No region selected.</p>';
    }

    // Table name
    $table_name = $wpdb->prefix . 'weather_data';

    // Fetch data for the selected region
    $region_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE region = %s", $region));

    if (!$region_data) {
        return '<p>No data found for the selected region.</p>';
    }

    // Build the HTML output
    $output = '<div class="bh-main-snow-report">
                    <div class="bh-snow-report">
                        <h2>Snow depth on the mountain and in the valley</h2>
                        <div class="region-name">' . esc_html($region_data->region) . '</div>
                        <div class="bh-grid">
                            <!-- Left Column -->
                            <img src="https://weather.techosolution.website/wp-content/uploads/2024/11/Screenshot-2024-11-18-200218.png" alt="Mountain Icon" />
                            <!-- Right Column -->
                            <div class="bh-snow-levels">
                                <div>❄ Mountain: <span>' . esc_html($region_data->snow_mountain) . '</span></div>
                                <div class="bh-valley">❄ Valley: <span>' . esc_html($region_data->snow_valley) . '</span></div>
                            </div>
                            <hr class="bh-mountain-line1">
                            <hr class="bh-mountain-line2">
                        </div>
                        
                        <div class="bh-details">
                            <div class="bh-detail-item">
                                <span class="bh-label">Snow depth valley:</span>
                                <span class="bh-value">' . esc_html($region_data->snow_valley) . '</span>
                            </div>
                            <div class="bh-detail-item">
                                <span class="bh-label">Open trails (km):</span>
                                <span class="bh-value new-snow">' . esc_html($region_data->new_snow) . '</span>
                            </div>
                            <div class="bh-detail-item">
                                <span class="bh-label">Open descent:</span>
                                <span class="bh-value"></span>
                            </div>
                            <div class="bh-detail-item">
                                <span class="bh-label">Walking routes open (km):</span>
                                <span class="bh-value lifts-open">' . esc_html($region_data->lifts_open) . '</span>
                            </div>
                        </div>
                    </div>
                </div>';

    return $output;
}



// Add an uninstall hook to clean up the database.
register_uninstall_hook(__FILE__, 'weather_import_uninstall');

function weather_import_uninstall() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'weather_data';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}


// 2. Create a Custom Cron Schedule Based on User Input

// Register custom cron schedule
function scrap_cron_add_schedule($schedules) {
    // Get user-defined interval (in hours)
    $interval = get_option('scrap_cron_interval', 24); // Default to 24 hours if no setting exists
    $interval_seconds = $interval * 60 * 60; // Convert to seconds

    // Add custom interval to cron schedules
    $schedules['scrap_interval'] = array(
        'interval' => $interval_seconds,
        'display'  => "Scrap Interval ($interval hours)"
    );

    return $schedules;
}
add_filter('cron_schedules', 'scrap_cron_add_schedule');

// Schedule the cron event
function scrap_schedule_cron_job() {
    if (!wp_next_scheduled('scrap_cron_event')) {
        // Get the user-defined interval and convert it to seconds
        $interval = get_option('scrap_cron_interval', 24); // Default to 24 hours
        $interval_seconds = $interval * 60 * 60;

        // Schedule the cron event
        wp_schedule_event(time(), 'scrap_interval', 'scrap_cron_event');
    }
}
add_action('wp', 'scrap_schedule_cron_job');

function scrap_cron_event_callback() {
    // Path to your scraper file
    $scrap_file = plugin_dir_path(__FILE__) . 'ts-automation.php';

    // Ensure the scraper file exists
    if (file_exists($scrap_file)) {
        // Include and execute the scraper file
        include_once($scrap_file);

        // Log successful operation
        error_log('Weather data updated successfully using scraper file.');
    } else {
        // Log an error if the scraper file is not found
        error_log('Scraper file not found: ' . $scrap_file);
    }
}
add_action('scrap_cron_event', 'scrap_cron_event_callback');
