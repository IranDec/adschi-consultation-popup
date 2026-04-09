<?php
if (!defined('ABSPATH')) exit;

function acp_submit_request_ajax() {
    check_ajax_referer('acp_submit_action', 'acp_submit_nonce');

    $settings = get_option('acp_settings', []);
    $recaptcha_type = isset($settings['recaptcha_type']) ? $settings['recaptcha_type'] : 'none';
    $secret_key = '';
    if ($recaptcha_type === 'v2') {
        $secret_key = !empty($settings['recaptcha_v2_secret_key']) ? $settings['recaptcha_v2_secret_key'] : '';
    } elseif ($recaptcha_type === 'v3') {
        $secret_key = !empty($settings['recaptcha_v3_secret_key']) ? $settings['recaptcha_v3_secret_key'] : '';
    }

    // Verify CAPTCHA
    if ($recaptcha_type === 'math') {
        $math_id = sanitize_text_field($_POST['acp_math_id'] ?? '');
        $ans = intval($_POST['acp_math_captcha']);
        $expected_ans = get_transient('acp_' . $math_id);

        if ($expected_ans === false || intval($expected_ans) !== $ans) {
            wp_send_json_error(['message' => acp_t('کپچای ریاضی اشتباه یا منقضی شده است.', 'Math CAPTCHA is incorrect or expired.', 'Mathe-CAPTCHA ist falsch oder abgelaufen.')]);
        }
        delete_transient('acp_' . $math_id); // Ensure single use
    } elseif (($recaptcha_type === 'v2' || $recaptcha_type === 'v3') && !empty($secret_key)) {
        $recaptcha_response = isset($_POST['g-recaptcha-response']) ? sanitize_text_field($_POST['g-recaptcha-response']) : '';
        if (empty($recaptcha_response)) {
            wp_send_json_error(['message' => acp_t('لطفاً کپچا را تایید کنید.', 'Please verify the captcha.', 'Bitte Captcha bestätigen.')]);
        }

        $verify_url = 'https://www.google.com/recaptcha/api/siteverify';
        $verify_response = wp_remote_post($verify_url, [
            'body' => [
                'secret' => $secret_key,
                'response' => $recaptcha_response,
                'remoteip' => $_SERVER['REMOTE_ADDR']
            ]
        ]);

        if (is_wp_error($verify_response)) {
            wp_send_json_error(['message' => acp_t('خطا در ارتباط با گوگل.', 'Error connecting to Google.', 'Fehler bei der Verbindung mit Google.')]);
        }

        $body = json_decode(wp_remote_retrieve_body($verify_response));
        if (!$body->success) {
            wp_send_json_error(['message' => acp_t('کپچا نامعتبر است.', 'Invalid Captcha.', 'Ungültiges Captcha.')]);
        }
    }

    $req_name = isset($settings['req_name']) ? $settings['req_name'] : '1';
    $req_email = isset($settings['req_email']) ? $settings['req_email'] : '0';
    $req_phone = isset($settings['req_phone']) ? $settings['req_phone'] : '1';
    $req_date = isset($settings['req_date']) ? $settings['req_date'] : '1';
    $req_msg = isset($settings['req_msg']) ? $settings['req_msg'] : '0';
    $req_dept = isset($settings['req_dept']) ? $settings['req_dept'] : '0';
    $req_file = isset($settings['req_file']) ? $settings['req_file'] : '0';

    $name = isset($_POST['acp_name']) ? sanitize_text_field($_POST['acp_name']) : '';
    $email = isset($_POST['acp_email']) ? sanitize_email($_POST['acp_email']) : '';
    $phone = isset($_POST['acp_phone']) ? sanitize_text_field($_POST['acp_phone']) : '';
    $date = isset($_POST['acp_date']) ? sanitize_text_field($_POST['acp_date']) : '';
    $message = isset($_POST['acp_message']) ? sanitize_textarea_field($_POST['acp_message']) : '';
    $department = isset($_POST['acp_department']) ? sanitize_text_field($_POST['acp_department']) : '';

    // File upload handling
    $attachment_url = '';
    if (!empty($_FILES['acp_attachment']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        $upload_overrides = [
            'test_form' => false,
            'mimes' => [
                'jpg|jpeg|jpe' => 'image/jpeg',
                'png' => 'image/png',
                'pdf' => 'application/pdf',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ]
        ];
        $uploaded_file = wp_handle_upload($_FILES['acp_attachment'], $upload_overrides);
        if (isset($uploaded_file['url'])) {
            $attachment_url = sanitize_url($uploaded_file['url']);
        } else {
            wp_send_json_error(['message' => acp_t('خطا در آپلود فایل.', 'Error uploading file.', 'Fehler beim Hochladen der Datei.') . ' ' . $uploaded_file['error']]);
        }
    }

    if (
        ($req_name == '1' && empty($name)) ||
        ($req_email == '1' && empty($email)) ||
        ($req_phone == '1' && empty($phone)) ||
        ($req_date == '1' && empty($date)) ||
        ($req_msg == '1' && empty($message)) ||
        ($req_dept == '1' && empty($department)) ||
        ($req_file == '1' && empty($attachment_url))
    ) {
        wp_send_json_error(['message' => acp_t('فیلدهای ستاره‌دار الزامی هستند.', 'Required fields are missing.', 'Pflichtfelder fehlen.')]);
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'acp_requests';

    $inserted = $wpdb->insert(
        $table_name,
        [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'req_date' => $date,
            'message' => $message,
            'department' => $department,
            'attachment' => $attachment_url,
            'status' => 'pending',
            'created_at' => current_time('mysql')
        ],
        [
            '%s', // name
            '%s', // email
            '%s', // phone
            '%s', // req_date
            '%s', // message
            '%s', // department
            '%s', // attachment
            '%s', // status
            '%s'  // created_at
        ]
    );

    if ($inserted) {
        // Send Emails
        acp_send_emails($name, $email, $phone, $date, $message, $department, $attachment_url);

        wp_send_json_success(['message' => acp_t('درخواست شما با موفقیت ثبت شد. به زودی با شما تماس می‌گیریم.', 'Request submitted successfully. We will contact you soon.', 'Anfrage erfolgreich eingereicht. Wir werden Sie in Kürze kontaktieren.')]);
    } else {
        wp_send_json_error(['message' => acp_t('خطا در ذخیره اطلاعات. لطفاً مجدداً تلاش کنید.', 'Error saving data. Please try again.', 'Fehler beim Speichern der Daten. Bitte versuchen Sie es erneut.')]);
    }
}
add_action('wp_ajax_acp_submit_request', 'acp_submit_request_ajax');
add_action('wp_ajax_nopriv_acp_submit_request', 'acp_submit_request_ajax');

function acp_log_email($recipient, $subject, $status, $error_msg = '') {
    global $wpdb;
    $table_logs = $wpdb->prefix . 'acp_email_logs';
    $wpdb->insert(
        $table_logs,
        [
            'recipient_email' => $recipient,
            'subject' => $subject,
            'status' => $status,
            'error_msg' => $error_msg,
            'created_at' => current_time('mysql')
        ],
        ['%s', '%s', '%s', '%s', '%s']
    );
}

function acp_send_emails($name, $email, $phone, $date, $message = '', $department = '', $attachment_url = '') {
    $settings = get_option('acp_settings', []);
    $admin_email = !empty($settings['admin_email']) ? $settings['admin_email'] : get_option('admin_email');
    $site_name = get_bloginfo('name');
    $admin_url = admin_url('admin.php?page=acp-requests');

    $headers = array('Content-Type: text/html; charset=UTF-8');

    // Admin Email
    $admin_subject = acp_t('درخواست مشاوره جدید از ', 'New Consultation Request from ', 'Neue Beratungsanfrage von ') . $site_name;

    $locale = get_locale();
    $direction = (strpos($locale, 'fa_') === 0) ? 'direction:rtl; text-align:right;' : 'direction:ltr; text-align:left;';

    $dept_label = acp_t('بخش / موضوع:', 'Department / Subject:', 'Abteilung / Thema:');
    $name_label = acp_t('نام:', 'Name:', 'Name:');
    $phone_label = acp_t('تلفن:', 'Phone:', 'Telefon:');
    $email_label = acp_t('ایمیل:', 'Email:', 'E-Mail:');
    $date_label = acp_t('تاریخ درخواستی:', 'Requested Date:', 'Wunschdatum:');
    $msg_label = acp_t('پیام:', 'Message:', 'Nachricht:');
    $file_label = acp_t('فایل پیوست:', 'Attachment:', 'Anhang:');
    $download_label = acp_t('دانلود فایل', 'Download File', 'Datei herunterladen');
    $title_label = acp_t('درخواست مشاوره جدید', 'New Consultation Request', 'Neue Beratungsanfrage');
    $view_label = acp_t('مشاهده درخواست در پنل مدیریت', 'View Request in Dashboard', 'Anfrage im Dashboard ansehen');

    $dept_html = !empty($department) ? "<p><strong>$dept_label</strong> $department</p>" : "";
    $msg_html = !empty($message) ? "<p><strong>$msg_label</strong><br><span style='background:#f9f9f9; padding:10px; display:block; border-left:3px solid #007cba;'>$message</span></p>" : "";
    $file_html = !empty($attachment_url) ? "<p><strong>$file_label</strong> <a href='$attachment_url' style='color:#007cba; text-decoration:none;'>$download_label</a></p>" : "";

    $admin_body = "
    <div style='font-family:Tahoma, Arial, sans-serif; $direction background:#f4f4f4; padding:20px;'>
        <div style='background:#fff; padding:30px; border-radius:8px; max-width:600px; margin:0 auto; box-shadow:0 4px 10px rgba(0,0,0,0.05); border-top: 4px solid #007cba;'>
            <h2 style='color:#333; border-bottom:1px solid #eee; padding-bottom:10px;'>$title_label</h2>
            $dept_html
            <p><strong>$name_label</strong> $name</p>
            <p><strong>$phone_label</strong> <a href='tel:$phone' style='color:#007cba; text-decoration:none;'>$phone</a></p>
            <p><strong>$email_label</strong> <a href='mailto:$email' style='color:#007cba; text-decoration:none;'>$email</a></p>
            <p><strong>$date_label</strong> $date</p>
            $msg_html
            $file_html
            <hr style='border:none; border-top:1px solid #eee; margin:30px 0 20px;'>
            <div style='text-align:center;'>
                <a href='$admin_url' style='background:#007cba; color:#fff; text-decoration:none; padding:12px 25px; border-radius:5px; font-weight:bold; display:inline-block;'>$view_label</a>
            </div>
        </div>
    </div>";

    // Enable error capturing for wp_mail
    global $phpmailer;
    $admin_sent = wp_mail($admin_email, $admin_subject, $admin_body, $headers);
    if ($admin_sent) {
        acp_log_email($admin_email, $admin_subject, 'success');
    } else {
        $error_msg = isset($phpmailer->ErrorInfo) ? $phpmailer->ErrorInfo : 'Unknown wp_mail error';
        acp_log_email($admin_email, $admin_subject, 'failed', $error_msg);
    }

    // User Email
    if (!empty($email)) {
        $user_subject = acp_t('درخواست مشاوره شما ثبت شد - ', 'Your Consultation Request is Confirmed - ', 'Ihre Beratungsanfrage ist bestätigt - ') . $site_name;

        $hello_label = acp_t("سلام $name عزیز،", "Hello $name,", "Hallo $name,");
        $confirmed_label = acp_t("درخواست مشاوره شما برای تاریخ <strong>$date</strong> با موفقیت ثبت شد.", "Your consultation request for <strong>$date</strong> has been successfully received.", "Ihre Beratungsanfrage für <strong>$date</strong> ist erfolgreich eingegangen.");
        $contact_label = acp_t("کارشناسان ما به زودی از طریق شماره تلفن <strong>$phone</strong> با شما تماس خواهند گرفت.", "Our experts will contact you soon at <strong>$phone</strong>.", "Unsere Experten werden Sie bald unter <strong>$phone</strong> kontaktieren.");
        $thanks_label = acp_t("با تشکر،<br>تیم پشتیبانی", "Best regards,<br>Support Team", "Mit freundlichen Grüßen,<br>Support-Team");

        $user_body = "
        <div style='font-family:Tahoma, Arial, sans-serif; $direction background:#f4f4f4; padding:20px;'>
            <div style='background:#fff; padding:20px; border-radius:8px; max-width:600px; margin:0 auto; box-shadow:0 4px 10px rgba(0,0,0,0.1); border-top: 5px solid #007cba;'>
                <h2 style='color:#333;'>$hello_label</h2>
                <p>$confirmed_label</p>
                <p>$contact_label</p>
                <br>
                <p>$thanks_label <strong>$site_name</strong></p>
            </div>
        </div>";

        $user_sent = wp_mail($email, $user_subject, $user_body, $headers);
        if ($user_sent) {
            acp_log_email($email, $user_subject, 'success');
        } else {
            $error_msg = isset($phpmailer->ErrorInfo) ? $phpmailer->ErrorInfo : 'Unknown wp_mail error';
            acp_log_email($email, $user_subject, 'failed', $error_msg);
        }
    }
}
