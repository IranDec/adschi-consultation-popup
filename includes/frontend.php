<?php
if (!defined('ABSPATH')) exit;

function acp_enqueue_frontend_scripts() {
    $settings = get_option('acp_settings');
    $recaptcha_type = isset($settings['recaptcha_type']) ? $settings['recaptcha_type'] : 'none';
    $site_key = '';
    if ($recaptcha_type === 'v2') {
        $site_key = !empty($settings['recaptcha_v2_site_key']) ? $settings['recaptcha_v2_site_key'] : '';
    } elseif ($recaptcha_type === 'v3') {
        $site_key = !empty($settings['recaptcha_v3_site_key']) ? $settings['recaptcha_v3_site_key'] : '';
    }

    if (($recaptcha_type === 'v2' || $recaptcha_type === 'v3') && !empty($site_key)) {
        if ($recaptcha_type === 'v3') {
            wp_enqueue_script('google-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . esc_attr($site_key), [], null, true);
        } else {
            wp_enqueue_script('google-recaptcha', 'https://www.google.com/recaptcha/api.js', [], null, true);
        }
    }
}
add_action('wp_enqueue_scripts', 'acp_enqueue_frontend_scripts');

function acp_render_popup_html() {
    $settings = get_option('acp_settings', []);
    $title = !empty($settings['form_title']) ? $settings['form_title'] : acp_t('درخواست مشاوره', 'Request a Consultation', 'Beratung anfordern');
    $recaptcha_type = isset($settings['recaptcha_type']) ? $settings['recaptcha_type'] : 'none';
    $site_key = '';
    if ($recaptcha_type === 'v2') {
        $site_key = !empty($settings['recaptcha_v2_site_key']) ? $settings['recaptcha_v2_site_key'] : '';
    } elseif ($recaptcha_type === 'v3') {
        $site_key = !empty($settings['recaptcha_v3_site_key']) ? $settings['recaptcha_v3_site_key'] : '';
    }

    $show_name = isset($settings['show_name']) ? $settings['show_name'] : '1';
    $show_email = isset($settings['show_email']) ? $settings['show_email'] : '1';
    $show_phone = isset($settings['show_phone']) ? $settings['show_phone'] : '1';
    $show_date = isset($settings['show_date']) ? $settings['show_date'] : '1';

    $req_name = isset($settings['req_name']) ? $settings['req_name'] : '1';
    $req_email = isset($settings['req_email']) ? $settings['req_email'] : '0';
    $req_phone = isset($settings['req_phone']) ? $settings['req_phone'] : '1';
    $req_date = isset($settings['req_date']) ? $settings['req_date'] : '1';

    $show_msg = isset($settings['show_msg']) ? $settings['show_msg'] : '0';
    $req_msg = isset($settings['req_msg']) ? $settings['req_msg'] : '0';

    $show_dept = isset($settings['show_dept']) ? $settings['show_dept'] : '0';
    $req_dept = isset($settings['req_dept']) ? $settings['req_dept'] : '0';
    $dept_options = !empty($settings['dept_options']) ? explode(',', $settings['dept_options']) : [];

    $show_file = isset($settings['show_file']) ? $settings['show_file'] : '0';
    $req_file = isset($settings['req_file']) ? $settings['req_file'] : '0';

    $form_theme = isset($settings['form_theme']) ? $settings['form_theme'] : 'light';
    $form_image_url = !empty($settings['form_image_url']) ? $settings['form_image_url'] : '';

    $is_rtl = is_rtl() || strpos(get_locale(), 'fa_') === 0;
    $dir = $is_rtl ? 'rtl' : 'ltr';
    $align = $is_rtl ? 'right' : 'left';
    $close_pos = $is_rtl ? 'left: 15px;' : 'right: 15px;';

    // Generate Math Captcha numbers securely
    $math_n1 = rand(1, 9);
    $math_n2 = rand(1, 9);
    $math_id = uniqid('math_');
    set_transient('acp_' . $math_id, $math_n1 + $math_n2, 10 * MINUTE_IN_SECONDS);

    ?>
    <style>
        /* Lightweight Popup CSS */
        #acp-popup-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.6); z-index: 999999; backdrop-filter: blur(3px);
            align-items: center; justify-content: center;
        }
        #acp-popup-box {
            background: <?php echo $form_theme === 'dark' ? '#1e1e1e' : '#fff'; ?>;
            color: <?php echo $form_theme === 'dark' ? '#eee' : '#333'; ?>;
            border-radius: 12px; width: 90%; max-width: <?php echo $form_image_url ? '800px' : '450px'; ?>;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3); position: relative; direction: <?php echo $dir; ?>;
            font-family: inherit; overflow: hidden; display: flex; flex-direction: <?php echo $is_rtl ? 'row-reverse' : 'row'; ?>;
        }
        <?php if($form_image_url): ?>
        #acp-popup-image {
            width: 40%; background: url('<?php echo esc_url($form_image_url); ?>') no-repeat center center; background-size: cover;
            display: none;
        }
        @media(min-width: 768px) {
            #acp-popup-image { display: block; }
        }
        <?php endif; ?>
        #acp-popup-content {
            padding: 30px; flex: 1; position: relative;
        }
        #acp-popup-close {
            position: absolute; top: 15px; <?php echo $close_pos; ?> cursor: pointer; font-size: 24px;
            line-height: 1; color: #666; z-index: 10; padding: 5px;
        }
        .acp-form-group { margin-bottom: 15px; text-align: <?php echo $align; ?>; }
        .acp-form-group label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px; }
        .acp-form-group input, .acp-form-group textarea, .acp-form-group select {
            width: 100%; padding: 10px;
            border: 1px solid <?php echo $form_theme === 'dark' ? '#444' : '#ccc'; ?>;
            background: <?php echo $form_theme === 'dark' ? '#2d2d2d' : '#fff'; ?>;
            color: <?php echo $form_theme === 'dark' ? '#fff' : '#000'; ?>;
            border-radius: 6px; box-sizing: border-box; font-family: inherit;
        }
        #acp-popup-close { color: <?php echo $form_theme === 'dark' ? '#bbb' : '#666'; ?> !important; }

        .acp-btn { width: 100%; padding: 12px; background: #007cba; color: #fff; border: none; border-radius: 6px; font-size: 16px; cursor: pointer; transition: 0.3s; }
        .acp-btn:hover { background: #005a8c; }
        #acp-msg { margin-top: 15px; font-size: 14px; text-align: center; display: none; padding: 10px; border-radius: 4px;}
        .acp-success { background: #d4edda; color: #155724; }
        .acp-error { background: #f8d7da; color: #721c24; }
        .grecaptcha-badge { z-index: 9999999 !important; }
    </style>

    <div id="acp-popup-overlay">
        <div id="acp-popup-box">
            <?php if($form_image_url): ?>
            <div id="acp-popup-image"></div>
            <?php endif; ?>
            <div id="acp-popup-content">
                <span id="acp-popup-close">&times;</span>
                <h2 style="margin-top:0; text-align:center; font-size: 22px;"><?php echo esc_html($title); ?></h2>
                <form id="acp-form">
                    <?php wp_nonce_field('acp_submit_action', 'acp_submit_nonce'); ?>

                    <?php if ($show_dept == '1' && !empty($dept_options)): ?>
                    <div class="acp-form-group">
                        <label><?php echo esc_html(acp_t('بخش / موضوع', 'Department / Subject', 'Abteilung / Thema')); ?> <?php if($req_dept == '1') echo '*'; ?></label>
                        <select name="acp_department" <?php if($req_dept == '1') echo 'required'; ?>>
                            <option value=""><?php echo esc_html(acp_t('انتخاب کنید...', 'Select...', 'Auswählen...')); ?></option>
                            <?php foreach($dept_options as $opt): $opt = trim($opt); if(empty($opt)) continue; ?>
                                <option value="<?php echo esc_attr($opt); ?>"><?php echo esc_html($opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <?php if ($show_name == '1'): ?>
                    <div class="acp-form-group">
                        <label><?php echo esc_html(acp_t('نام و نام خانوادگی', 'Full Name', 'Vollständiger Name')); ?> <?php if($req_name == '1') echo '*'; ?></label>
                        <input type="text" name="acp_name" <?php if($req_name == '1') echo 'required'; ?>>
                    </div>
                    <?php endif; ?>

                    <?php if ($show_email == '1'): ?>
                    <div class="acp-form-group">
                        <label><?php echo esc_html(acp_t('ایمیل', 'Email', 'E-Mail')); ?> <?php if($req_email == '1') echo '*'; ?></label>
                        <input type="email" name="acp_email" <?php if($req_email == '1') echo 'required'; ?>>
                    </div>
                    <?php endif; ?>

                    <?php if ($show_phone == '1'): ?>
                    <div class="acp-form-group">
                        <label><?php echo esc_html(acp_t('شماره تماس', 'Phone Number', 'Telefonnummer')); ?> <?php if($req_phone == '1') echo '*'; ?></label>
                        <input type="tel" name="acp_phone" <?php if($req_phone == '1') echo 'required'; ?>>
                    </div>
                    <?php endif; ?>

                    <?php if ($show_date == '1'): ?>
                    <div class="acp-form-group">
                        <label><?php echo esc_html(acp_t('تاریخ درخواستی', 'Requested Date', 'Gewünschtes Datum')); ?> <?php if($req_date == '1') echo '*'; ?></label>
                        <input type="date" name="acp_date" <?php if($req_date == '1') echo 'required'; ?> min="<?php echo date('Y-m-d'); ?>" onclick="if(this.showPicker) this.showPicker();">
                    </div>
                    <?php endif; ?>

                    <?php if ($show_msg == '1'): ?>
                    <div class="acp-form-group">
                        <label><?php echo esc_html(acp_t('پیام شما', 'Your Message', 'Ihre Nachricht')); ?> <?php if($req_msg == '1') echo '*'; ?></label>
                        <textarea name="acp_message" rows="3" <?php if($req_msg == '1') echo 'required'; ?>></textarea>
                    </div>
                    <?php endif; ?>

                    <?php if ($show_file == '1'): ?>
                    <div class="acp-form-group">
                        <label><?php echo esc_html(acp_t('آپلود فایل', 'Upload File', 'Datei hochladen')); ?> <?php if($req_file == '1') echo '*'; ?></label>
                        <input type="file" name="acp_attachment" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" <?php if($req_file == '1') echo 'required'; ?>>
                    </div>
                    <?php endif; ?>

                    <?php if ($recaptcha_type === 'math'): ?>
                        <div class="acp-form-group">
                            <label><?php echo esc_html(acp_t('کپچا (لطفا محاسبه کنید): ', 'CAPTCHA (Please calculate): ', 'CAPTCHA (Bitte berechnen): ')); ?> <?php echo $math_n1; ?> + <?php echo $math_n2; ?> = *</label>
                            <input type="number" name="acp_math_captcha" required>
                            <input type="hidden" name="acp_math_id" value="<?php echo esc_attr($math_id); ?>">
                        </div>
                    <?php elseif ($recaptcha_type === 'v2' && !empty($site_key)): ?>
                        <div class="acp-form-group" style="display:flex; justify-content:center;">
                            <div class="g-recaptcha" data-sitekey="<?php echo esc_attr($site_key); ?>"></div>
                        </div>
                    <?php elseif ($recaptcha_type === 'v3' && !empty($site_key)): ?>
                        <input type="hidden" name="acp_v3_token" id="acp_v3_token">
                    <?php endif; ?>

                    <button type="submit" class="acp-btn" id="acp-submit-btn"><?php echo esc_html(acp_t('ثبت درخواست', 'Submit Request', 'Anfrage absenden')); ?></button>
                    <div id="acp-msg"></div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var overlay = document.getElementById('acp-popup-overlay');
            var closeBtn = document.getElementById('acp-popup-close');
            var triggers = document.querySelectorAll('.acp-trigger-popup, .acp-trigger-popup a, .acp-trigger-popup button');
            var recaptchaType = '<?php echo esc_js($recaptcha_type); ?>';
            var siteKey = '<?php echo esc_js($site_key); ?>';

            triggers.forEach(function(trigger) {
                trigger.addEventListener('click', function(e) {
                    e.preventDefault();
                    overlay.style.display = 'flex';
                });
            });

            closeBtn.addEventListener('click', function() {
                overlay.style.display = 'none';
            });

            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) {
                    overlay.style.display = 'none';
                }
            });

            var form = document.getElementById('acp-form');
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var btn = document.getElementById('acp-submit-btn');
                var msg = document.getElementById('acp-msg');

                function sendFormData(token = '') {
                    btn.disabled = true;
                    btn.innerText = '<?php echo esc_js(acp_t("در حال ارسال...", "Sending...", "Senden...")); ?>';
                    msg.style.display = 'none';

                    var formData = new FormData(form);
                    formData.append('action', 'acp_submit_request');
                    if(token) {
                        formData.append('g-recaptcha-response', token);
                    }

                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        msg.style.display = 'block';
                        if (data.success) {
                            msg.className = 'acp-success';
                            msg.innerText = data.data.message;
                            form.reset();
                            if (recaptchaType === 'v2' && typeof grecaptcha !== 'undefined') grecaptcha.reset();
                            setTimeout(() => { overlay.style.display = 'none'; msg.style.display = 'none'; }, 3000);
                        } else {
                            msg.className = 'acp-error';
                            msg.innerText = data.data.message;
                            if (recaptchaType === 'v2' && typeof grecaptcha !== 'undefined') grecaptcha.reset();
                        }
                        btn.disabled = false;
                        btn.innerText = '<?php echo esc_js(acp_t("ثبت درخواست", "Submit Request", "Anfrage absenden")); ?>';
                    })
                    .catch(error => {
                        msg.style.display = 'block';
                        msg.className = 'acp-error';
                        msg.innerText = '<?php echo esc_js(acp_t("خطای شبکه رخ داد.", "Network error occurred.", "Netzwerkfehler aufgetreten.")); ?>';
                        btn.disabled = false;
                        btn.innerText = '<?php echo esc_js(acp_t("ثبت درخواست", "Submit Request", "Anfrage absenden")); ?>';
                    });
                }

                if (recaptchaType === 'v3' && siteKey && typeof grecaptcha !== 'undefined') {
                    grecaptcha.ready(function() {
                        grecaptcha.execute(siteKey, {action: 'submit'}).then(function(token) {
                            sendFormData(token);
                        });
                    });
                } else {
                    sendFormData();
                }
            });
        });
    </script>
    <?php
}
add_action('wp_footer', 'acp_render_popup_html');
