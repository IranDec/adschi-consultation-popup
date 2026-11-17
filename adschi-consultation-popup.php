<?php
/*
Plugin Name: Adschi Consultation Popup
Plugin URI: https://adschi.com/
Description: A modern, lightweight popup form triggered by a CSS class, designed for consultation requests (Name, Email, Phone, Date). Features reCAPTCHA, fast AJAX, and a CRM-style admin dashboard.
Version: 1.7.0
Requires at least: 5.0
Tested up to: 6.5
Author: Mohammad Babaei
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if (!defined('ABSPATH')) exit;

define('ACP_PATH', plugin_dir_path(__FILE__));
define('ACP_URL', plugin_dir_url(__FILE__));
define('ACP_VERSION', '1.7.0');

// Install DB
function acp_install_db() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'acp_requests';
    $table_logs = $wpdb->prefix . 'acp_email_logs';
    $charset_collate = $wpdb->get_charset_collate();

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        form_id varchar(50) DEFAULT 'default' NOT NULL,
        name varchar(255) NOT NULL,
        email varchar(255) NOT NULL,
        phone varchar(100) NOT NULL,
        req_date varchar(50) NOT NULL,
        message text,
        department varchar(255),
        attachment varchar(255),
        status varchar(50) DEFAULT 'pending' NOT NULL,
        admin_note text,
        follow_up_at datetime DEFAULT NULL,
        page_url varchar(255) DEFAULT '' NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        updated_at datetime DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY status (status),
        KEY created_at (created_at)
    ) $charset_collate;";
    dbDelta($sql);

    // 1.7.0: history of status changes and notes per request
    $table_notes = $wpdb->prefix . 'acp_request_notes';
    $sql_notes = "CREATE TABLE $table_notes (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        request_id bigint(20) NOT NULL,
        user_id bigint(20) DEFAULT 0 NOT NULL,
        note text,
        status_from varchar(50) DEFAULT '' NOT NULL,
        status_to varchar(50) DEFAULT '' NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY request_id (request_id)
    ) $charset_collate;";
    dbDelta($sql_notes);

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

    // 1.7.0: the single admin note of older versions becomes the first history entry
    if (version_compare((string) get_option('acp_db_version', '0'), '1.4', '<')) {
        $old = $wpdb->get_results("SELECT id, admin_note, created_at FROM $table_name WHERE admin_note IS NOT NULL AND admin_note <> ''");
        foreach ((array) $old as $r) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_notes WHERE request_id = %d", $r->id));
            if (!$exists) {
                $wpdb->insert($table_notes, ['request_id' => $r->id, 'user_id' => 0, 'note' => $r->admin_note, 'created_at' => $r->created_at]);
            }
        }
    }

    update_option('acp_db_version', '1.4');
}

function acp_check_db() {
    if (get_option('acp_db_version') !== '1.4') {
        acp_install_db();
    }

    // Migrate old settings to multiple forms if not done yet
    $forms = get_option('acp_forms');
    if ($forms === false) {
        $settings = get_option('acp_settings');
        if ($settings) {
            // we have old settings
            $form_data = $settings;
            $form_data['id'] = 'default';
            $form_data['form_name'] = acp_t('فرم پیش‌فرض', 'Default Form', 'Standardformular');

            // keep global settings separate or leave them in the form data but global logic uses acp_settings
            $forms = ['default' => $form_data];
            update_option('acp_forms', $forms);
        } else {
            // completely new installation
            $default_form = [
                'id' => 'default',
                'form_name' => acp_t('فرم پیش‌فرض', 'Default Form', 'Standardformular'),
                'form_title' => acp_t('درخواست مشاوره', 'Request a Consultation', 'Beratung anfordern'),
                'show_name' => '1', 'show_email' => '1', 'show_phone' => '1', 'show_date' => '1',
                'req_name' => '1', 'req_email' => '0', 'req_phone' => '1', 'req_date' => '1',
                'show_msg' => '0', 'req_msg' => '0',
                'show_dept' => '0', 'req_dept' => '0', 'dept_options' => '',
                'show_file' => '0', 'req_file' => '0',
                'form_theme' => 'light', 'form_image_url' => '',
            ];
            update_option('acp_forms', ['default' => $default_form]);
        }
    }
}
add_action('plugins_loaded', 'acp_check_db');
register_activation_hook(__FILE__, 'acp_install_db');

// Include components
require_once ACP_PATH . 'includes/admin.php';
require_once ACP_PATH . 'includes/admin-crm.php';
require_once ACP_PATH . 'includes/frontend.php';
require_once ACP_PATH . 'includes/ajax.php';
require_once ACP_PATH . 'includes/i18n.php';
require_once ACP_PATH . 'includes/inpost-module.php';
