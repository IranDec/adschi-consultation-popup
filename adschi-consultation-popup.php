<?php
/*
Plugin Name: Adschi Consultation Popup
Plugin URI: https://adschi.com/
Description: A modern, lightweight popup form triggered by a CSS class, designed for consultation requests (Name, Email, Phone, Date). Features reCAPTCHA, fast AJAX, and a CRM-style admin dashboard.
Version: 1.2
Requires at least: 5.0
Tested up to: 6.5
Author: Mohammad Babaei
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if (!defined('ABSPATH')) exit;

define('ACP_PATH', plugin_dir_path(__FILE__));
define('ACP_URL', plugin_dir_url(__FILE__));

// Install DB
function acp_install_db() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'acp_requests';
    $table_logs = $wpdb->prefix . 'acp_email_logs';
    $charset_collate = $wpdb->get_charset_collate();

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        email varchar(255) NOT NULL,
        phone varchar(100) NOT NULL,
        req_date varchar(50) NOT NULL,
        message text,
        department varchar(255),
        attachment varchar(255),
        status varchar(50) DEFAULT 'pending' NOT NULL,
        admin_note text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql);

    $sql_logs = "CREATE TABLE $table_logs (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        recipient_email varchar(255) NOT NULL,
        subject varchar(255) NOT NULL,
        status varchar(50) NOT NULL,
        error_msg text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql_logs);

    update_option('acp_db_version', '1.2');
}

function acp_check_db() {
    if (get_option('acp_db_version') !== '1.2') {
        acp_install_db();
    }
}
add_action('plugins_loaded', 'acp_check_db');
register_activation_hook(__FILE__, 'acp_install_db');

// Include components
require_once ACP_PATH . 'includes/admin.php';
require_once ACP_PATH . 'includes/frontend.php';
require_once ACP_PATH . 'includes/ajax.php';
require_once ACP_PATH . 'includes/i18n.php';
