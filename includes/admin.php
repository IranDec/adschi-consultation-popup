<?php
if (!defined('ABSPATH')) exit;

/** Styles and script for the plugin's admin pages only. */
add_action('admin_enqueue_scripts', function ($hook) {
    $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
    if (0 !== strpos($page, 'acp-')) {
        return;
    }
    wp_enqueue_style('acp-admin', ACP_URL . 'assets/admin.css', [], ACP_VERSION);
    wp_enqueue_script('acp-admin', ACP_URL . 'assets/admin.js', [], ACP_VERSION, true);
    wp_localize_script('acp-admin', 'acpAdmin', [
        'confirmTitle' => acp_t('تأیید کنید', 'Please confirm', 'Bitte bestätigen'),
        'yes' => acp_t('بله، ادامه', 'Yes, continue', 'Ja, fortfahren'),
        'no' => acp_t('خیر', 'No', 'Nein'),
        'selectFirst' => acp_t('ابتدا درخواست‌ها و یک عملیات را انتخاب کنید.', 'Select requests and an action first.', 'Bitte zuerst Anfragen und eine Aktion auswählen.'),
    ]);
});

add_action('admin_menu', function() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'acp_requests';
    $pending_count = 0;
    if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
        $pending_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'pending'");
    }

    $menu_title = acp_t('درخواست مشاوره', 'Consultation Requests', 'Beratungsanfragen');
    if ($pending_count > 0) {
        $menu_title .= ' <span class="update-plugins count-' . esc_attr($pending_count) . '"><span class="plugin-count">' . esc_html($pending_count) . '</span></span>';
    }

    add_menu_page(
        acp_t('درخواست مشاوره', 'Consultation Requests', 'Beratungsanfragen'),
        $menu_title,
        'manage_options',
        'acp-requests',
        'acp_render_crm_page',
        'dashicons-clipboard',
        25
    );

    add_submenu_page(
        'acp-requests',
        acp_t('فرم‌ها', 'Forms', 'Formulare'),
        acp_t('فرم‌ها', 'Forms', 'Formulare'),
        'manage_options',
        'acp-forms',
        'acp_render_forms_page'
    );

    $inpost_hook = add_submenu_page(
        'acp-requests',
        acp_t('ماژول درون‌نوشته', 'In-Post Module', 'In-Post-Modul'),
        acp_t('ماژول درون‌نوشته', 'In-Post Module', 'In-Post-Modul'),
        'manage_options',
        'acp-inpost',
        'acp_render_inpost_page'
    );
    add_action("admin_print_scripts-$inpost_hook", 'acp_inpost_admin_scripts');

    add_submenu_page(
        'acp-requests',
        acp_t('تنظیمات', 'Settings', 'Einstellungen'),
        acp_t('تنظیمات عمومی', 'Global Settings', 'Allgemeine Einstellungen'),
        'manage_options',
        'acp-settings',
        'acp_render_settings_page'
    );

    add_submenu_page(
        'acp-requests',
        acp_t('گزارش ایمیل‌ها', 'Email Logs', 'E-Mail-Protokolle'),
        acp_t('گزارش ایمیل‌ها', 'Email Logs', 'E-Mail-Protokolle'),
        'manage_options',
        'acp-email-logs',
        'acp_render_email_logs_page'
    );
});

function acp_render_settings_page() {
    if (isset($_POST['acp_save']) && check_admin_referer('acp_settings_action', 'acp_settings_nonce')) {
        $settings = isset($_POST['acp_settings']) ? $_POST['acp_settings'] : [];
        $sanitized_settings = [
            'admin_email' => sanitize_email($settings['admin_email'] ?? ''),
            'recaptcha_type' => sanitize_text_field($settings['recaptcha_type'] ?? 'none'),
            'recaptcha_v2_site_key' => sanitize_text_field($settings['recaptcha_v2_site_key'] ?? ''),
            'recaptcha_v2_secret_key' => sanitize_text_field($settings['recaptcha_v2_secret_key'] ?? ''),
            'recaptcha_v3_site_key' => sanitize_text_field($settings['recaptcha_v3_site_key'] ?? ''),
            'recaptcha_v3_secret_key' => sanitize_text_field($settings['recaptcha_v3_secret_key'] ?? ''),
        ];
        // We preserve any other settings just in case
        $old_settings = get_option('acp_settings', []);
        $updated_settings = array_merge($old_settings, $sanitized_settings);
        update_option('acp_settings', $updated_settings);
        echo '<div class="updated"><p>' . esc_html(acp_t('تنظیمات ذخیره شد.', 'Settings saved.', 'Einstellungen gespeichert.')) . '</p></div>';
    }

    $settings = get_option('acp_settings', [
        'admin_email' => get_option('admin_email'),
        'recaptcha_type' => 'none',
        'recaptcha_v2_site_key' => '',
        'recaptcha_v2_secret_key' => '',
        'recaptcha_v3_site_key' => '',
        'recaptcha_v3_secret_key' => '',
    ]);
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(acp_t('تنظیمات عمومی', 'Global Settings', 'Allgemeine Einstellungen')); ?></h1>
        <form method="post">
            <?php wp_nonce_field('acp_settings_action', 'acp_settings_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html(acp_t('ایمیل دریافت کننده (مدیر)', 'Admin Email', 'Admin E-Mail')); ?></th>
                    <td><input type="email" name="acp_settings[admin_email]" class="regular-text" value="<?php echo esc_attr($settings['admin_email'] ?? ''); ?>"></td>
                </tr>

                <tr><th colspan="2"><h3><?php echo esc_html(acp_t('تنظیمات امنیتی / کپچا', 'Security / CAPTCHA Settings', 'Sicherheit / CAPTCHA-Einstellungen')); ?></h3></th></tr>
                <tr>
                    <th scope="row"><?php echo esc_html(acp_t('نوع کپچا', 'CAPTCHA Type', 'CAPTCHA-Typ')); ?></th>
                    <td>
                        <select name="acp_settings[recaptcha_type]" id="acp_recaptcha_type">
                            <option value="none" <?php selected($settings['recaptcha_type'] ?? 'none', 'none'); ?>><?php echo esc_html(acp_t('بدون کپچا', 'None', 'Keine')); ?></option>
                            <option value="math" <?php selected($settings['recaptcha_type'] ?? 'none', 'math'); ?>><?php echo esc_html(acp_t('کپچای ریاضی (ساده)', 'Math CAPTCHA (Simple)', 'Mathe-CAPTCHA (Einfach)')); ?></option>
                            <option value="v2" <?php selected($settings['recaptcha_type'] ?? 'none', 'v2'); ?>>Google reCAPTCHA v2 (Checkbox)</option>
                            <option value="v3" <?php selected($settings['recaptcha_type'] ?? 'none', 'v3'); ?>>Google reCAPTCHA v3 (Invisible)</option>
                        </select>
                    </td>
                </tr>
                <tr class="acp_recaptcha_v2_row">
                    <th scope="row">Google reCAPTCHA v2 Site Key</th>
                    <td><input type="text" name="acp_settings[recaptcha_v2_site_key]" class="regular-text" value="<?php echo esc_attr($settings['recaptcha_v2_site_key'] ?? ''); ?>"></td>
                </tr>
                <tr class="acp_recaptcha_v2_row">
                    <th scope="row">Google reCAPTCHA v2 Secret Key</th>
                    <td><input type="text" name="acp_settings[recaptcha_v2_secret_key]" class="regular-text" value="<?php echo esc_attr($settings['recaptcha_v2_secret_key'] ?? ''); ?>"></td>
                </tr>

                <tr class="acp_recaptcha_v3_row">
                    <th scope="row">Google reCAPTCHA v3 Site Key</th>
                    <td><input type="text" name="acp_settings[recaptcha_v3_site_key]" class="regular-text" value="<?php echo esc_attr($settings['recaptcha_v3_site_key'] ?? ''); ?>"></td>
                </tr>
                <tr class="acp_recaptcha_v3_row">
                    <th scope="row">Google reCAPTCHA v3 Secret Key</th>
                    <td><input type="text" name="acp_settings[recaptcha_v3_secret_key]" class="regular-text" value="<?php echo esc_attr($settings['recaptcha_v3_secret_key'] ?? ''); ?>"></td>
                </tr>
            </table>
            <p><input type="submit" name="acp_save" class="button button-primary" value="<?php echo esc_attr(acp_t('ذخیره', 'Save', 'Speichern')); ?>"></p>
        </form>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var recaptchaType = document.getElementById('acp_recaptcha_type');
                var v2Rows = document.querySelectorAll('.acp_recaptcha_v2_row');
                var v3Rows = document.querySelectorAll('.acp_recaptcha_v3_row');

                function toggleKeys() {
                    v2Rows.forEach(function(row) {
                        row.style.display = (recaptchaType.value === 'v2') ? 'table-row' : 'none';
                    });
                    v3Rows.forEach(function(row) {
                        row.style.display = (recaptchaType.value === 'v3') ? 'table-row' : 'none';
                    });
                }

                recaptchaType.addEventListener('change', toggleKeys);
                toggleKeys();
            });
        </script>
    </div>
    <?php
    acp_admin_footer();
}

function acp_render_email_logs_page() {
    global $wpdb;
    $table_logs = $wpdb->prefix . 'acp_email_logs';
    $results = $wpdb->get_results("SELECT * FROM $table_logs ORDER BY created_at DESC LIMIT 100");
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(acp_t('گزارش ایمیل‌ها', 'Email Logs', 'E-Mail-Protokolle')); ?></h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th><?php echo esc_html(acp_t('ایمیل دریافت کننده', 'Recipient', 'Empfänger')); ?></th>
                    <th><?php echo esc_html(acp_t('موضوع', 'Subject', 'Betreff')); ?></th>
                    <th><?php echo esc_html(acp_t('وضعیت', 'Status', 'Status')); ?></th>
                    <th><?php echo esc_html(acp_t('جزئیات / خطا', 'Details / Error', 'Details / Fehler')); ?></th>
                    <th><?php echo esc_html(acp_t('تاریخ', 'Date', 'Datum')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($results)): ?>
                    <tr><td colspan="6"><?php echo esc_html(acp_t('هیچ گزارشی ثبت نشده است.', 'No logs found.', 'Keine Protokolle gefunden.')); ?></td></tr>
                <?php else: foreach($results as $row): ?>
                    <tr data-raw-status="<?php echo esc_attr($row->status); ?>">
                        <td><?php echo intval($row->id); ?></td>
                        <td><?php echo esc_html($row->recipient_email); ?></td>
                        <td><?php echo esc_html($row->subject); ?></td>
                        <td>
                            <?php if($row->status === 'success'): ?>
                                <span style="color:green;font-weight:bold;"><?php echo esc_html(acp_t('موفق', 'Success', 'Erfolg')); ?></span>
                            <?php elseif($row->status === 'skipped'): ?>
                                <span style="color:#b26a00;font-weight:bold;" title="<?php echo esc_attr(acp_t('برای جلوگیری از سوءاستفاده ارسال نشد', 'Not sent to prevent abuse', 'Zum Schutz vor Missbrauch nicht gesendet')); ?>"><?php echo esc_html(acp_t('ارسال نشد', 'Skipped', 'Übersprungen')); ?></span>
                            <?php else: ?>
                                <span style="color:red;font-weight:bold;"><?php echo esc_html(acp_t('ناموفق', 'Failed', 'Fehlgeschlagen')); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($row->error_msg); ?></td>
                        <td><?php echo esc_html($row->created_at); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    acp_admin_footer();
}

function acp_admin_footer() {
    $version = defined('ACP_VERSION') ? ACP_VERSION : '';
    echo '<div class="acp-footer"><div class="acp-footer-card">'
        . '<div class="acp-footer-title">Adschi Consultation Popup <span class="acp-footer-ver">v' . esc_html($version) . '</span></div>'
        . '<div class="acp-footer-by">' . esc_html(acp_t('توسعه‌دهنده', 'Developed by', 'Entwickelt von')) . ' <a href="https://adschi.com" target="_blank" rel="noopener noreferrer">Mohammad Babaei</a> &middot; <a href="https://adschi.com" target="_blank" rel="noopener noreferrer">adschi.com</a></div>'
        . '<div class="acp-footer-note">' . esc_html(acp_t('برای ساخت ماژول اختصاصی با ما در تماس باشید.', 'Contact us for custom module development.', 'Kontakt für individuelle Modulentwicklung.')) . '</div>'
        . '</div></div>';
}

function acp_render_forms_page() {
    $forms = get_option('acp_forms', []);

    // Handle Add/Edit Form
    if (isset($_POST['acp_save_form']) && check_admin_referer('acp_form_action', 'acp_form_nonce')) {
        $form_id = sanitize_text_field($_POST['form_id']);
        if (empty($form_id)) {
            $form_id = 'form_' . uniqid();
        }

        $form_data = [
            'id' => $form_id,
            'form_name' => sanitize_text_field($_POST['form_name'] ?? 'Form ' . $form_id),
            'form_title' => sanitize_text_field($_POST['form_title'] ?? ''),
            'show_name' => isset($_POST['show_name']) ? '1' : '0',
            'show_email' => isset($_POST['show_email']) ? '1' : '0',
            'show_phone' => isset($_POST['show_phone']) ? '1' : '0',
            'show_date' => isset($_POST['show_date']) ? '1' : '0',
            'req_name' => isset($_POST['req_name']) ? '1' : '0',
            'req_email' => isset($_POST['req_email']) ? '1' : '0',
            'req_phone' => isset($_POST['req_phone']) ? '1' : '0',
            'req_date' => isset($_POST['req_date']) ? '1' : '0',
            'show_msg' => isset($_POST['show_msg']) ? '1' : '0',
            'req_msg' => isset($_POST['req_msg']) ? '1' : '0',
            'show_dept' => isset($_POST['show_dept']) ? '1' : '0',
            'req_dept' => isset($_POST['req_dept']) ? '1' : '0',
            'dept_options' => sanitize_text_field($_POST['dept_options'] ?? ''),
            'show_file' => isset($_POST['show_file']) ? '1' : '0',
            'req_file' => isset($_POST['req_file']) ? '1' : '0',
            'form_theme' => sanitize_text_field($_POST['form_theme'] ?? 'light'),
            'form_image_url' => sanitize_url($_POST['form_image_url'] ?? ''),
        ];

        $forms[$form_id] = $form_data;
        update_option('acp_forms', $forms);
        echo '<div class="updated"><p>' . esc_html(acp_t('فرم ذخیره شد.', 'Form saved.', 'Formular gespeichert.')) . '</p></div>';
    }

    // Handle Delete Form
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['form']) && check_admin_referer('acp_delete_form')) {
        $del_id = sanitize_text_field($_GET['form']);
        if (isset($forms[$del_id])) {
            unset($forms[$del_id]);
            update_option('acp_forms', $forms);
            echo '<div class="updated"><p>' . esc_html(acp_t('فرم حذف شد.', 'Form deleted.', 'Formular gelöscht.')) . '</p></div>';
        }
    }

    $edit_form_id = isset($_GET['edit']) ? sanitize_text_field($_GET['edit']) : '';
    $current_form = null;
    if ($edit_form_id && isset($forms[$edit_form_id])) {
        $current_form = $forms[$edit_form_id];
    }

    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php echo esc_html(acp_t('مدیریت فرم‌ها', 'Forms Management', 'Formularverwaltung')); ?></h1>
        <a href="<?php echo admin_url('admin.php?page=acp-forms&action=new'); ?>" class="page-title-action"><?php echo esc_html(acp_t('افزودن جدید', 'Add New', 'Neu hinzufügen')); ?></a>
        <hr class="wp-header-end">

        <?php if (isset($_GET['action']) && $_GET['action'] == 'new' || $current_form):
            $f = $current_form ?: [
                'id' => '', 'form_name' => '', 'form_title' => '',
                'show_name' => '1', 'show_email' => '1', 'show_phone' => '1', 'show_date' => '1',
                'req_name' => '1', 'req_email' => '0', 'req_phone' => '1', 'req_date' => '1',
                'show_msg' => '0', 'req_msg' => '0',
                'show_dept' => '0', 'req_dept' => '0', 'dept_options' => '',
                'show_file' => '0', 'req_file' => '0',
                'form_theme' => 'light', 'form_image_url' => '',
            ];
        ?>
        <h2><?php echo $current_form ? esc_html(acp_t('ویرایش فرم', 'Edit Form', 'Formular bearbeiten')) : esc_html(acp_t('فرم جدید', 'New Form', 'Neues Formular')); ?></h2>
        <form method="post" action="<?php echo admin_url('admin.php?page=acp-forms'); ?>">
            <?php wp_nonce_field('acp_form_action', 'acp_form_nonce'); ?>
            <input type="hidden" name="form_id" value="<?php echo esc_attr($f['id']); ?>">

            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html(acp_t('نام فرم (برای مدیریت)', 'Form Name (Internal)', 'Formularname (Intern)')); ?></th>
                    <td><input type="text" name="form_name" class="regular-text" value="<?php echo esc_attr($f['form_name']); ?>" required></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html(acp_t('عنوان پاپ‌آپ', 'Popup Title', 'Popup-Titel')); ?></th>
                    <td><input type="text" name="form_title" class="regular-text" value="<?php echo esc_attr($f['form_title']); ?>"></td>
                </tr>

                <tr><th colspan="2"><h3><?php echo esc_html(acp_t('فیلدهای فرم', 'Form Fields', 'Formularfelder')); ?></h3></th></tr>
                <tr>
                    <th></th>
                    <td><strong><?php echo esc_html(acp_t('نمایش', 'Show', 'Anzeigen')); ?></strong> &nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp; <strong><?php echo esc_html(acp_t('اجباری', 'Required', 'Erforderlich')); ?></strong></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html(acp_t('نام و نام خانوادگی', 'Full Name', 'Vollständiger Name')); ?></th>
                    <td>
                        <input type="checkbox" name="show_name" value="1" <?php checked($f['show_name'], '1'); ?>> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        <input type="checkbox" name="req_name" value="1" <?php checked($f['req_name'], '1'); ?>>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html(acp_t('ایمیل', 'Email', 'E-Mail')); ?></th>
                    <td>
                        <input type="checkbox" name="show_email" value="1" <?php checked($f['show_email'], '1'); ?>> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        <input type="checkbox" name="req_email" value="1" <?php checked($f['req_email'], '1'); ?>>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html(acp_t('شماره تماس', 'Phone Number', 'Telefonnummer')); ?></th>
                    <td>
                        <input type="checkbox" name="show_phone" value="1" <?php checked($f['show_phone'], '1'); ?>> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        <input type="checkbox" name="req_phone" value="1" <?php checked($f['req_phone'], '1'); ?>>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html(acp_t('تاریخ', 'Date', 'Datum')); ?></th>
                    <td>
                        <input type="checkbox" name="show_date" value="1" <?php checked($f['show_date'], '1'); ?>> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        <input type="checkbox" name="req_date" value="1" <?php checked($f['req_date'], '1'); ?>>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html(acp_t('پیام / متن تکمیلی', 'Message / Textarea', 'Nachricht / Textbereich')); ?></th>
                    <td>
                        <input type="checkbox" name="show_msg" value="1" <?php checked($f['show_msg'], '1'); ?>> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        <input type="checkbox" name="req_msg" value="1" <?php checked($f['req_msg'], '1'); ?>>
                    </td>
                </tr>

                <tr><th colspan="2"><h3><?php echo esc_html(acp_t('فیلدهای اضافی', 'Additional Fields', 'Zusätzliche Felder')); ?></h3></th></tr>
                <tr>
                    <th scope="row"><?php echo esc_html(acp_t('آپلود فایل', 'File Upload', 'Datei-Upload')); ?></th>
                    <td>
                        <input type="checkbox" name="show_file" value="1" <?php checked($f['show_file'], '1'); ?>> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        <input type="checkbox" name="req_file" value="1" <?php checked($f['req_file'], '1'); ?>>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html(acp_t('دپارتمان / موضوع (کشویی)', 'Department (Dropdown)', 'Abteilung (Dropdown)')); ?></th>
                    <td>
                        <input type="checkbox" name="show_dept" value="1" <?php checked($f['show_dept'], '1'); ?>> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        <input type="checkbox" name="req_dept" value="1" <?php checked($f['req_dept'], '1'); ?>>
                        <br><br>
                        <input type="text" name="dept_options" class="regular-text" placeholder="<?php echo esc_attr(acp_t('مثال: فروش, پشتیبانی, مشاوره', 'Ex: Sales, Support, Consultation', 'Bsp: Vertrieb, Support, Beratung')); ?>" value="<?php echo esc_attr($f['dept_options']); ?>">
                        <p class="description"><?php echo esc_html(acp_t('گزینه‌ها را با کاما (,) جدا کنید.', 'Separate options with a comma (,).', 'Trennen Sie die Optionen mit einem Komma (,).')); ?></p>
                    </td>
                </tr>

                <tr><th colspan="2"><h3><?php echo esc_html(acp_t('ظاهر و تصویر', 'Appearance & Image', 'Aussehen & Bild')); ?></h3></th></tr>
                <tr>
                    <th scope="row"><?php echo esc_html(acp_t('تم فرم', 'Form Theme', 'Formular-Theme')); ?></th>
                    <td>
                        <select name="form_theme">
                            <option value="light" <?php selected($f['form_theme'], 'light'); ?>><?php echo esc_html(acp_t('روشن (کلاسیک)', 'Light (Classic)', 'Hell (Klassisch)')); ?></option>
                            <option value="dark" <?php selected($f['form_theme'], 'dark'); ?>><?php echo esc_html(acp_t('تاریک (مدرن)', 'Dark (Modern)', 'Dunkel (Modern)')); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html(acp_t('آدرس تصویر کنار فرم (اختیاری)', 'Side Image URL (Optional)', 'URL des Seitenbildes (Optional)')); ?></th>
                    <td><input type="url" name="form_image_url" class="regular-text" value="<?php echo esc_url($f['form_image_url']); ?>">
                    <p class="description"><?php echo esc_html(acp_t('اگر خالی باشد، فرم به صورت ساده نمایش داده می‌شود.', 'If left blank, the form will display simply.', 'Wenn leer gelassen, wird das Formular einfach angezeigt.')); ?></p></td>
                </tr>
            </table>

            <p><input type="submit" name="acp_save_form" class="button button-primary" value="<?php echo esc_attr(acp_t('ذخیره فرم', 'Save Form', 'Formular speichern')); ?>"></p>
        </form>

        <?php else: ?>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php echo esc_html(acp_t('نام فرم', 'Form Name', 'Formularname')); ?></th>
                    <th><?php echo esc_html(acp_t('کلاس فراخوانی (CSS Class)', 'Trigger Class', 'Trigger-Klasse')); ?></th>
                    <th><?php echo esc_html(acp_t('عملیات', 'Actions', 'Aktionen')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($forms)): ?>
                <tr><td colspan="3"><?php echo esc_html(acp_t('هیچ فرمی یافت نشد.', 'No forms found.', 'Keine Formulare gefunden.')); ?></td></tr>
                <?php else: foreach ($forms as $id => $f): ?>
                <tr>
                    <td><strong><?php echo esc_html($f['form_name']); ?></strong></td>
                    <td><code style="user-select:all;">acp-trigger-popup-<?php echo esc_attr($id); ?></code></td>
                    <td>
                        <a href="<?php echo admin_url('admin.php?page=acp-forms&edit=' . esc_attr($id)); ?>" class="button"><?php echo esc_html(acp_t('ویرایش', 'Edit', 'Bearbeiten')); ?></a>
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=acp-forms&action=delete&form=' . esc_attr($id)), 'acp_delete_form'); ?>" class="button" style="color:red;" onclick="return confirm('<?php echo esc_js(acp_t('آیا مطمئن هستید؟', 'Are you sure?', 'Sind Sie sicher?')); ?>');"><?php echo esc_html(acp_t('حذف', 'Delete', 'Löschen')); ?></a>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>

        <?php endif; ?>
    </div>
    <?php
    acp_admin_footer();
}
