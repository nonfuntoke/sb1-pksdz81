<?php
/**
 * Plugin Name: Email Marketing Niches AI
 * Description: AI-powered email marketing niche discovery and campaign planning
 * Version: 1.0.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('EMN_VERSION', '1.0.0');
define('EMN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EMN_PLUGIN_URL', plugin_dir_url(__FILE__));

// Initialize plugin
function emn_init() {
    add_action('admin_menu', 'emn_admin_menu');
    add_action('admin_enqueue_scripts', 'emn_admin_scripts');
    add_action('wp_ajax_emn_verify_api', 'emn_verify_api');
    add_action('wp_ajax_emn_get_recommendations', 'emn_get_recommendations');
}
add_action('plugins_loaded', 'emn_init');

// Register admin menu
function emn_admin_menu() {
    add_menu_page(
        'Email Marketing Niches',
        'Email Marketing',
        'manage_options',
        'email-marketing-niches',
        'emn_admin_page',
        'dashicons-email-alt'
    );
    
    add_submenu_page(
        'email-marketing-niches',
        'Settings',
        'Settings',
        'manage_options',
        'email-marketing-settings',
        'emn_settings_page'
    );
}

// Enqueue admin scripts and styles
function emn_admin_scripts($hook) {
    if (!in_array($hook, ['toplevel_page_email-marketing-niches', 'email-marketing_page_email-marketing-settings'])) {
        return;
    }

    wp_enqueue_style(
        'emn-admin-styles',
        EMN_PLUGIN_URL . 'assets/css/admin.css',
        [],
        EMN_VERSION
    );

    wp_enqueue_script(
        'emn-admin-script',
        EMN_PLUGIN_URL . 'assets/js/admin.js',
        ['jquery'],
        EMN_VERSION,
        true
    );

    wp_localize_script('emn-admin-script', 'emnAjax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('emn_nonce')
    ]);
}

// Admin page callback
function emn_admin_page() {
    $api_key = get_option('emn_gemini_api_key');
    if (!$api_key) {
        echo '<div class="wrap"><div class="notice notice-error"><p>Please configure your Gemini AI API key in the <a href="' . admin_url('admin.php?page=email-marketing-settings') . '">settings page</a>.</p></div></div>';
        return;
    }
    require_once EMN_PLUGIN_DIR . 'templates/admin-page.php';
}

// Settings page callback
function emn_settings_page() {
    if (isset($_POST['emn_save_settings']) && check_admin_referer('emn_settings_nonce')) {
        update_option('emn_gemini_api_key', sanitize_text_field($_POST['emn_gemini_api_key']));
        echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
    }
    require_once EMN_PLUGIN_DIR . 'templates/settings-page.php';
}

// AJAX handler for API verification
function emn_verify_api() {
    check_ajax_referer('emn_nonce', 'nonce');
    
    $api_key = get_option('emn_gemini_api_key');
    if (!$api_key) {
        wp_send_json_error('API key not configured');
    }

    $response = wp_remote_get('https://generativelanguage.googleapis.com/v1beta/models/gemini-pro/generateText', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ]
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    wp_send_json_success($body);
}

// AJAX handler for getting AI recommendations
function emn_get_recommendations() {
    check_ajax_referer('emn_nonce', 'nonce');
    
    $api_key = get_option('emn_gemini_api_key');
    if (!$api_key) {
        wp_send_json_error('API key not configured');
    }

    $data = json_decode(stripslashes($_POST['data']), true);
    
    $prompt = sprintf(
        'Based on the following user profile:
        - Industry: %s
        - Audience Demographics: Age %s, Location %s, Interests %s
        - Content Focus: %s
        - Campaign Goals: %s
        - Trends/Topics of Interest: %s

        Recommend 3-5 trending, high-potential email marketing niches. For each niche, provide:
        1. A brief description and popularity metrics
        2. Recommended audience segmentation
        3. Specific content ideas, email subject lines, and messaging styles
        4. Analysis of competitors and differentiation strategies',
        $data['industry'],
        $data['demographics']['ageRange'],
        $data['demographics']['location'],
        implode(', ', $data['demographics']['interests']),
        $data['campaign']['contentFocus'],
        implode(', ', $data['campaign']['goals']),
        implode(', ', $data['trends'])
    );

    $response = wp_remote_post('https://generativelanguage.googleapis.com/v1beta/models/gemini-pro/generateText', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode([
            'prompt' => ['text' => $prompt],
            'temperature' => 0.7,
            'candidate_count' => 1
        ])
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    wp_send_json_success($body);
}