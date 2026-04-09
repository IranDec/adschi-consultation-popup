<?php
if (!defined('ABSPATH')) exit;

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
        acp_t('تنظیمات', 'Settings', 'Einstellungen'),
        acp_t('تنظیمات', 'Settings', 'Einstellungen'),
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
            'form_title' => sanitize_text_field($settings['form_title'] ?? ''),
            'admin_email' => sanitize_email($settings['admin_email'] ?? ''),
            'recaptcha_type' => sanitize_text_field($settings['recaptcha_type'] ?? 'none'),
            'recaptcha_v2_site_key' => sanitize_text_field($settings['recaptcha_v2_site_key'] ?? ''),
            'recaptcha_v2_secret_key' => sanitize_text_field($settings['recaptcha_v2_secret_key'] ?? ''),
            'recaptcha_v3_site_key' => sanitize_text_field($settings['recaptcha_v3_site_key'] ?? ''),
            'recaptcha_v3_secret_key' => sanitize_text_field($settings['recaptcha_v3_secret_key'] ?? ''),
            'show_name' => isset($settings['show_name']) ? '1' : '0',
            'show_email' => isset($settings['show_email']) ? '1' : '0',
            'show_phone' => isset($settings['show_phone']) ? '1' : '0',
            'show_date' => isset($settings['show_date']) ? '1' : '0',

            'req_name' => isset($settings['req_name']) ? '1' : '0',
            'req_email' => isset($settings['req_email']) ? '1' : '0',
            'req_phone' => isset($settings['req_phone']) ? '1' : '0',
            'req_date' => isset($settings['req_date']) ? '1' : '0',

            'show_msg' => isset($settings['show_msg']) ? '1' : '0',
            'req_msg' => isset($settings['req_msg']) ? '1' : '0',

            'show_dept' => isset($settings['show_dept']) ? '1' : '0',
            'req_dept' => isset($settings['req_dept']) ? '1' : '0',
            'dept_options' => sanitize_text_field($settings['dept_options'] ?? ''),

            'show_file' => isset($settings['show_file']) ? '1' : '0',
            'req_file' => isset($settings['req_file']) ? '1' : '0',

            'form_theme' => sanitize_text_field($settings['form_theme'] ?? 'light'),
            'form_image_url' => sanitize_url($settings['form_image_url'] ?? ''),
        ];
        update_option('acp_settings', $sanitized_settings);
        echo '<div class="updated"><p>' . esc_html(acp_t('تنظیمات ذخیره شد.', 'Settings saved.', 'Einstellungen gespeichert.')) . '</p></div>';
    }

    $settings = get_option('acp_settings', [
        'form_title' => acp_t('درخواست مشاوره', 'Request a Consultation', 'Beratung anfordern'),
        'admin_email' => get_option('admin_email'),
        'recaptcha_type' => 'none',
        'recaptcha_v2_site_key' => '',
        'recaptcha_v2_secret_key' => '',
        'recaptcha_v3_site_key' => '',
        'recaptcha_v3_secret_key' => '',
        'show_name' => '1',
        'show_email' => '1',
        'show_phone' => '1',
        'show_date' => '1',
        'req_name' => '1',
        'req_email' => '0',
        'req_phone' => '1',
        'req_date' => '1',
        'show_msg' => '0',
        'req_msg' => '0',
        'show_dept' => '0',
        'req_dept' => '0',
        'dept_options' => '',
        'show_file' => '0',
        'req_file' => '0',
        'form_theme' => 'light',
        'form_image_url' => '',
    ]);
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(acp_t('تنظیمات فرم مشاوره', 'Consultation Form Settings', 'Einstellungen des Beratungsformulars')); ?></h1>
        <p><?php echo esc_html(acp_t('برای نمایش پاپ‌آپ، کلاس زیر را به دکمه یا لینک خود در المنتور، دیوی و غیره اضافه کنید:', 'To display the popup, add the following CSS class to your button or link in Elementor, Divi, etc.:', 'Um das Popup anzuzeigen, fügen Sie die folgende CSS-Klasse zu Ihrer Schaltfläche oder Ihrem Link in Elementor, Divi usw. hinzu:')); ?> <br><code style="font-size:16px; user-select:all;">acp-trigger-popup</code></p>
        <form method="post">
            <?php wp_nonce_field('acp_settings_action', 'acp_settings_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html(acp_t('عنوان فرم', 'Form Title', 'Formulartitel')); ?></th>
                    <td><input type="text" name="acp_settings[form_title]" class="regular-text" value="<?php echo esc_attr($settings['form_title'] ?? ''); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html(acp_t('ایمیل دریافت کننده (مدیر)', 'Admin Email', 'Admin E-Mail')); ?></th>
                    <td><input type="email" name="acp_settings[admin_email]" class="regular-text" value="<?php echo esc_attr($settings['admin_email'] ?? ''); ?>"></td>
                </tr>

                <tr><th colspan="2"><h3><?php echo esc_html(acp_t('فیلدهای فرم', 'Form Fields', 'Formularfelder')); ?></h3></th></tr>
                <tr>
                    <th></th>
                    <td><strong><?php echo esc_html(acp_t('نمایش', 'Show', 'Anzeigen')); ?></strong> &nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp; <strong><?php echo esc_html(acp_t('اجباری', 'Required', 'Erforderlich')); ?></strong></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html(acp_t('نام و نام خانوادگی', 'Full Name', 'Vollständiger Name')); ?></th>
                    <td>
                        <input type="checkbox" name="acp_settings[show_name]" value="1" <?php checked($settings['show_name'] ?? '1', '1'); ?>> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        <input type="checkbox" name="acp_settings[req_name]" value="1" <?php checked($settings['req_name'] ?? '1', '1'); ?>>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html(acp_t('ایمیل', 'Email', 'E-Mail')); ?></th>
                    <td>
                        <input type="checkbox" name="acp_settings[show_email]" value="1" <?php checked($settings['show_email'] ?? '1', '1'); ?>> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        <input type="checkbox" name="acp_settings[req_email]" value="1" <?php checked($settings['req_email'] ?? '0', '1'); ?>>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html(acp_t('شماره تماس', 'Phone Number', 'Telefonnummer')); ?></th>
                    <td>
                        <input type="checkbox" name="acp_settings[show_phone]" value="1" <?php checked($settings['show_phone'] ?? '1', '1'); ?>> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        <input type="checkbox" name="acp_settings[req_phone]" value="1" <?php checked($settings['req_phone'] ?? '1', '1'); ?>>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html(acp_t('تاریخ', 'Date', 'Datum')); ?></th>
                    <td>
                        <input type="checkbox" name="acp_settings[show_date]" value="1" <?php checked($settings['show_date'] ?? '1', '1'); ?>> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        <input type="checkbox" name="acp_settings[req_date]" value="1" <?php checked($settings['req_date'] ?? '1', '1'); ?>>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html(acp_t('پیام / متن تکمیلی', 'Message / Textarea', 'Nachricht / Textbereich')); ?></th>
                    <td>
                        <input type="checkbox" name="acp_settings[show_msg]" value="1" <?php checked($settings['show_msg'] ?? '0', '1'); ?>> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        <input type="checkbox" name="acp_settings[req_msg]" value="1" <?php checked($settings['req_msg'] ?? '0', '1'); ?>>
                    </td>
                </tr>

                <tr><th colspan="2"><h3><?php echo esc_html(acp_t('فیلدهای فرم (روشن = نمایش)', 'Form Fields (Checked = Show)', 'Formularfelder (Angekreuzt = Anzeigen)')); ?></h3></th></tr>
                <tr>
                    <th scope="row"><?php echo esc_html(acp_t('آپلود فایل', 'File Upload', 'Datei-Upload')); ?></th>
                    <td>
                        <input type="checkbox" name="acp_settings[show_file]" value="1" <?php checked($settings['show_file'] ?? '0', '1'); ?>> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        <input type="checkbox" name="acp_settings[req_file]" value="1" <?php checked($settings['req_file'] ?? '0', '1'); ?>>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html(acp_t('دپارتمان / موضوع (کشویی)', 'Department (Dropdown)', 'Abteilung (Dropdown)')); ?></th>
                    <td>
                        <input type="checkbox" name="acp_settings[show_dept]" value="1" <?php checked($settings['show_dept'] ?? '0', '1'); ?>> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        <input type="checkbox" name="acp_settings[req_dept]" value="1" <?php checked($settings['req_dept'] ?? '0', '1'); ?>>
                        <br><br>
                        <input type="text" name="acp_settings[dept_options]" class="regular-text" placeholder="<?php echo esc_attr(acp_t('مثال: فروش, پشتیبانی, مشاوره', 'Ex: Sales, Support, Consultation', 'Bsp: Vertrieb, Support, Beratung')); ?>" value="<?php echo esc_attr($settings['dept_options'] ?? ''); ?>">
                        <p class="description"><?php echo esc_html(acp_t('گزینه‌ها را با کاما (,) جدا کنید.', 'Separate options with a comma (,).', 'Trennen Sie die Optionen mit einem Komma (,).')); ?></p>
                    </td>
                </tr>

                <tr><th colspan="2"><h3><?php echo esc_html(acp_t('ظاهر و تصویر', 'Appearance & Image', 'Aussehen & Bild')); ?></h3></th></tr>
                <tr>
                    <th scope="row"><?php echo esc_html(acp_t('تم فرم', 'Form Theme', 'Formular-Theme')); ?></th>
                    <td>
                        <select name="acp_settings[form_theme]">
                            <option value="light" <?php selected($settings['form_theme'] ?? 'light', 'light'); ?>><?php echo esc_html(acp_t('روشن (کلاسیک)', 'Light (Classic)', 'Hell (Klassisch)')); ?></option>
                            <option value="dark" <?php selected($settings['form_theme'] ?? 'light', 'dark'); ?>><?php echo esc_html(acp_t('تاریک (مدرن)', 'Dark (Modern)', 'Dunkel (Modern)')); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html(acp_t('آدرس تصویر کنار فرم (اختیاری)', 'Side Image URL (Optional)', 'URL des Seitenbildes (Optional)')); ?></th>
                    <td><input type="url" name="acp_settings[form_image_url]" class="regular-text" value="<?php echo esc_url($settings['form_image_url'] ?? ''); ?>">
                    <p class="description"><?php echo esc_html(acp_t('اگر خالی باشد، فرم به صورت ساده نمایش داده می‌شود.', 'If left blank, the form will display simply.', 'Wenn leer gelassen, wird das Formular einfach angezeigt.')); ?></p></td>
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

function acp_render_crm_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'acp_requests';

    // Handle updates
    if (isset($_POST['acp_update_req']) && check_admin_referer('acp_crm_action', 'acp_crm_nonce')) {
        $id = intval($_POST['req_id']);
        $status = sanitize_text_field($_POST['status']);
        $note = sanitize_textarea_field($_POST['admin_note']);

        $wpdb->update($table_name, ['status' => $status, 'admin_note' => $note], ['id' => $id]);
        echo '<div class="updated"><p>' . esc_html(acp_t('وضعیت بروز شد.', 'Status updated.', 'Status aktualisiert.')) . '</p></div>';
    }

    if (isset($_POST['acp_delete_req']) && check_admin_referer('acp_crm_action', 'acp_crm_nonce')) {
        $id = intval($_POST['req_id']);
        $wpdb->delete($table_name, ['id' => $id]);
        echo '<div class="updated"><p>' . esc_html(acp_t('درخواست حذف شد.', 'Request deleted.', 'Anfrage gelöscht.')) . '</p></div>';
    }

    $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(acp_t('درخواست‌های مشاوره', 'Consultation Requests', 'Beratungsanfragen')); ?></h1>

        <div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; background: #fff; padding: 10px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <div>
                <input type="text" id="acpSearchInput" placeholder="<?php echo esc_attr(acp_t('جستجو...', 'Search...', 'Suchen...')); ?>" style="padding: 5px; width: 250px;">
                <select id="acpStatusFilter" style="padding: 5px; margin-right: 10px; margin-left: 10px;">
                    <option value="all"><?php echo esc_html(acp_t('همه وضعیت‌ها', 'All Statuses', 'Alle Status')); ?></option>
                    <option value="pending"><?php echo esc_html(acp_t('در انتظار', 'Pending', 'Ausstehend')); ?></option>
                    <option value="called"><?php echo esc_html(acp_t('تماس گرفته شد', 'Called', 'Angerufen')); ?></option>
                    <option value="call_later"><?php echo esc_html(acp_t('تماس مجدد', 'Call Later', 'Später anrufen')); ?></option>
                </select>
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped" id="acpRequestsTable">
            <thead>
                <tr>
                    <th style="cursor:pointer;" onclick="acpSortTable(0)">ID &#x21D5;</th>
                    <th style="cursor:pointer;" onclick="acpSortTable(1)"><?php echo esc_html(acp_t('نام', 'Name', 'Name')); ?> &#x21D5;</th>
                    <th style="cursor:pointer;" onclick="acpSortTable(2)"><?php echo esc_html(acp_t('ایمیل / تلفن', 'Email / Phone', 'E-Mail / Telefon')); ?> &#x21D5;</th>
                    <th style="cursor:pointer;" onclick="acpSortTable(3)"><?php echo esc_html(acp_t('تاریخ درخواستی', 'Requested Date', 'Gewünschtes Datum')); ?> &#x21D5;</th>
                    <th><?php echo esc_html(acp_t('بخش / پیام', 'Department / Msg', 'Abteilung / Nachr')); ?></th>
                    <th><?php echo esc_html(acp_t('فایل', 'File', 'Datei')); ?></th>
                    <th style="cursor:pointer;" onclick="acpSortTable(6)"><?php echo esc_html(acp_t('وضعیت', 'Status', 'Status')); ?> &#x21D5;</th>
                    <th style="cursor:pointer;" onclick="acpSortTable(7)"><?php echo esc_html(acp_t('یادداشت مدیر', 'Admin Note', 'Admin-Notiz')); ?> &#x21D5;</th>
                    <th style="cursor:pointer;" onclick="acpSortTable(8)"><?php echo esc_html(acp_t('تاریخ ثبت', 'Submitted At', 'Eingereicht am')); ?> &#x21D5;</th>
                    <th><?php echo esc_html(acp_t('عملیات', 'Actions', 'Aktionen')); ?></th>
                </tr>
            </thead>
            <tbody id="acpRequestsBody">
                <?php if(empty($results)): ?>
                    <tr><td colspan="8"><?php echo esc_html(acp_t('هیچ درخواستی وجود ندارد.', 'No requests found.', 'Keine Anfragen gefunden.')); ?></td></tr>
                <?php else: foreach($results as $row): ?>
                    <tr data-raw-status="<?php echo esc_attr($row->status); ?>">
                        <td><?php echo intval($row->id); ?></td>
                        <td><?php echo esc_html($row->name); ?></td>
                        <td><?php echo esc_html($row->email . ' / ' . $row->phone); ?></td>
                        <td><?php echo esc_html($row->req_date); ?></td>
                        <td>
                            <?php
                            if (!empty($row->department)) echo '<strong>' . esc_html($row->department) . '</strong><br>';
                            if (!empty($row->message)) echo esc_html(wp_trim_words($row->message, 10));
                            ?>
                        </td>
                        <td>
                            <?php if(!empty($row->attachment)): ?>
                                <a href="<?php echo esc_url($row->attachment); ?>" target="_blank" class="button button-small"><?php echo esc_html(acp_t('دانلود', 'Download', 'Herunterladen')); ?></a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            if ($row->status === 'pending') echo '<span style="color:orange;font-weight:bold;">' . acp_t('در انتظار', 'Pending', 'Ausstehend') . '</span>';
                            elseif ($row->status === 'called') echo '<span style="color:green;font-weight:bold;">' . acp_t('تماس گرفته شد', 'Called', 'Angerufen') . '</span>';
                            elseif ($row->status === 'call_later') echo '<span style="color:blue;font-weight:bold;">' . acp_t('تماس مجدد', 'Call Later', 'Später anrufen') . '</span>';
                            ?>
                        </td>
                        <td><?php echo esc_html($row->admin_note); ?></td>
                        <td><?php echo esc_html($row->created_at); ?></td>
                        <td>
                            <button class="button action-edit-req" data-id="<?php echo $row->id; ?>" data-status="<?php echo esc_attr($row->status); ?>" data-note="<?php echo esc_attr($row->admin_note); ?>"><?php echo esc_html(acp_t('ویرایش', 'Edit', 'Bearbeiten')); ?></button>

                            <form method="post" style="display:inline;" onsubmit="return confirm('<?php echo esc_js(acp_t('آیا مطمئن هستید؟', 'Are you sure?', 'Sind Sie sicher?')); ?>');">
                                <?php wp_nonce_field('acp_crm_action', 'acp_crm_nonce'); ?>
                                <input type="hidden" name="req_id" value="<?php echo $row->id; ?>">
                                <button type="submit" name="acp_delete_req" class="button" style="color:red;"><?php echo esc_html(acp_t('حذف', 'Delete', 'Löschen')); ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Edit Modal -->
    <div id="acp-edit-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:99999;">
        <div style="background:#fff; width:400px; margin: 100px auto; padding: 20px; border-radius: 5px;">
            <h2><?php echo esc_html(acp_t('بروزرسانی وضعیت', 'Update Status', 'Status aktualisieren')); ?></h2>
            <form method="post">
                <?php wp_nonce_field('acp_crm_action', 'acp_crm_nonce'); ?>
                <input type="hidden" name="req_id" id="acp-edit-id">
                <p>
                    <label><?php echo esc_html(acp_t('وضعیت', 'Status', 'Status')); ?></label><br>
                    <select name="status" id="acp-edit-status" style="width:100%;">
                        <option value="pending"><?php echo esc_html(acp_t('در انتظار', 'Pending', 'Ausstehend')); ?></option>
                        <option value="called"><?php echo esc_html(acp_t('تماس گرفته شد', 'Called', 'Angerufen')); ?></option>
                        <option value="call_later"><?php echo esc_html(acp_t('تماس مجدد', 'Call Later', 'Später anrufen')); ?></option>
                    </select>
                </p>
                <p>
                    <label><?php echo esc_html(acp_t('یادداشت مدیر', 'Admin Note', 'Admin-Notiz')); ?></label><br>
                    <textarea name="admin_note" id="acp-edit-note" style="width:100%; height:80px;"></textarea>
                </p>
                <p>
                    <input type="submit" name="acp_update_req" class="button button-primary" value="<?php echo esc_attr(acp_t('ذخیره', 'Save', 'Speichern')); ?>">
                    <button type="button" class="button" onclick="document.getElementById('acp-edit-modal').style.display='none';"><?php echo esc_html(acp_t('لغو', 'Cancel', 'Abbrechen')); ?></button>
                </p>
            </form>
        </div>
    </div>
    <script>
        // Sorting and filtering logic
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('acpSearchInput');
            const statusFilter = document.getElementById('acpStatusFilter');
            const tableBody = document.getElementById('acpRequestsBody');

            function filterTable() {
                const term = searchInput.value.toLowerCase();
                const status = statusFilter.value;
                const rows = tableBody.getElementsByTagName('tr');

                for(let i=0; i<rows.length; i++) {
                    if(rows[i].cells.length < 10) continue; // skip empty messages

                    const textContent = rows[i].textContent.toLowerCase();
                    const rowStatus = rows[i].getAttribute('data-raw-status');

                    const matchSearch = textContent.indexOf(term) > -1;
                    const matchStatus = (status === 'all' || rowStatus === status);

                    if(matchSearch && matchStatus) {
                        rows[i].style.display = '';
                    } else {
                        rows[i].style.display = 'none';
                    }
                }
            }

            if(searchInput) searchInput.addEventListener('keyup', filterTable);
            if(statusFilter) statusFilter.addEventListener('change', filterTable);
        });

        let acpSortAsc = true;
        function acpSortTable(colIdx) {
            const table = document.getElementById('acpRequestsTable');
            const tbody = document.getElementById('acpRequestsBody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            if(rows.length === 0 || rows[0].cells.length < 10) return;

            acpSortAsc = !acpSortAsc;

            rows.sort((a, b) => {
                let valA = a.cells[colIdx].innerText.trim();
                let valB = b.cells[colIdx].innerText.trim();

                if (colIdx === 0) { // ID is numeric
                    valA = parseInt(valA) || 0;
                    valB = parseInt(valB) || 0;
                }

                if(valA < valB) return acpSortAsc ? -1 : 1;
                if(valA > valB) return acpSortAsc ? 1 : -1;
                return 0;
            });

            rows.forEach(row => tbody.appendChild(row));
        }

        document.querySelectorAll('.action-edit-req').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.getElementById('acp-edit-id').value = this.getAttribute('data-id');
                document.getElementById('acp-edit-status').value = this.getAttribute('data-status');
                document.getElementById('acp-edit-note').value = this.getAttribute('data-note');
                document.getElementById('acp-edit-modal').style.display = 'block';
            });
        });
    </script>
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
    if (!function_exists('get_plugin_data')) {
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }
    $plugin_data = get_plugin_data(dirname(dirname(__FILE__)) . '/adschi-consultation-popup.php');
    $version = $plugin_data['Version'];
    echo '<div style="margin-top: 30px; padding: 15px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); text-align: center; font-size: 14px; direction: rtl;">';
    echo sprintf(
        acp_t(
            'نسخه افزونه: %s | توسعه دهنده: <a href="https://adschi.com" target="_blank">Mohammad Babaei</a> | برای مشاوره و ساخت ماژول اختصاصی می‌توانید درخواست دهید.',
            'Plugin Version: %s | Developer: <a href="https://adschi.com" target="_blank">Mohammad Babaei</a> | Contact for custom module development.',
            'Plugin-Version: %s | Entwickler: <a href="https://adschi.com" target="_blank">Mohammad Babaei</a> | Kontakt für benutzerdefinierte Modulentwicklung.'
        ),
        esc_html($version)
    );
    echo '</div>';
}
