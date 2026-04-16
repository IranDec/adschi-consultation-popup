<?php
if (!defined('ABSPATH')) exit;

function acp_enqueue_frontend_scripts() {
    $settings = get_option('acp_settings');
    $inpost_settings = get_option('acp_inpost_settings', []);

    // Load dashicons on frontend if in-post module is enabled and using an icon
    if (!empty($inpost_settings['enabled']) && $inpost_settings['enabled'] === '1' && !empty($inpost_settings['button_icon'])) {
        wp_enqueue_style('dashicons');
    }

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
    $forms = get_option('acp_forms', []);
    if (empty($forms)) return;

    $recaptcha_type = isset($settings['recaptcha_type']) ? $settings['recaptcha_type'] : 'none';
    $site_key = '';
    if ($recaptcha_type === 'v2') $site_key = isset($settings['recaptcha_v2_site_key']) ? $settings['recaptcha_v2_site_key'] : '';
    if ($recaptcha_type === 'v3') $site_key = isset($settings['recaptcha_v3_site_key']) ? $settings['recaptcha_v3_site_key'] : '';

    $is_rtl = is_rtl() || strpos(get_locale(), 'fa_') === 0;
    $dir = $is_rtl ? 'rtl' : 'ltr';
    $align = $is_rtl ? 'right' : 'left';
    $close_pos = $is_rtl ? 'left: 15px;' : 'right: 15px;';

    // Generate Math Captcha numbers securely (one per request)
    $math_n1 = rand(1, 9);
    $math_n2 = rand(1, 9);
    $math_id = uniqid('math_');
    set_transient('acp_' . $math_id, $math_n1 + $math_n2, 10 * MINUTE_IN_SECONDS);

    ?>
    <style>
        /* Shared Styles */
        .acp-popup-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.6); z-index: 999999; backdrop-filter: blur(3px);
            align-items: center; justify-content: center;
        }
        .acp-popup-box {
            border-radius: 12px; width: 90%;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3); position: relative; direction: <?php echo $dir; ?>;
            font-family: inherit; overflow: hidden; display: flex; flex-direction: <?php echo $is_rtl ? 'row-reverse' : 'row'; ?>;
        }
        .acp-popup-content {
            padding: 30px; flex: 1; position: relative;
        }
        .acp-popup-close {
            position: absolute; top: 15px; <?php echo $close_pos; ?> cursor: pointer; font-size: 24px;
            line-height: 1; z-index: 10; padding: 5px;
        }
        .acp-form-group { margin-bottom: 15px; text-align: <?php echo $align; ?>; }
        .acp-form-group label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px; }
        .acp-form-group input, .acp-form-group textarea, .acp-form-group select {
            width: 100%; padding: 10px; border-radius: 6px; box-sizing: border-box; font-family: inherit;
        }
        .acp-btn { width: 100%; padding: 12px; background: #007cba; color: #fff; border: none; border-radius: 6px; font-size: 16px; cursor: pointer; transition: 0.3s; }
        .acp-btn:hover { background: #005a8c; }
        .acp-msg { margin-top: 15px; font-size: 14px; text-align: center; display: none; padding: 10px; border-radius: 4px;}
        .acp-success { background: #d4edda; color: #155724; }
        .acp-error { background: #f8d7da; color: #721c24; }
        .grecaptcha-badge { z-index: 9999999 !important; }
    </style>

    <?php foreach ($forms as $id => $f):
        $title = !empty($f['form_title']) ? $f['form_title'] : acp_t('درخواست مشاوره', 'Request a Consultation', 'Beratung anfordern');
        $theme = isset($f['form_theme']) ? $f['form_theme'] : 'light';
        $img_url = !empty($f['form_image_url']) ? $f['form_image_url'] : '';
        $dept_options = !empty($f['dept_options']) ? explode(',', $f['dept_options']) : [];

        // Use global reCAPTCHA variables for all forms, but math_id is shared since only one form is shown at a time
    ?>
    <style>
        #acp-popup-box-<?php echo esc_attr($id); ?> {
            background: <?php echo $theme === 'dark' ? '#1e1e1e' : '#fff'; ?>;
            color: <?php echo $theme === 'dark' ? '#eee' : '#333'; ?>;
            max-width: <?php echo $img_url ? '800px' : '450px'; ?>;
        }
        <?php if($img_url): ?>
        #acp-popup-image-<?php echo esc_attr($id); ?> {
            width: 40%; background: url('<?php echo esc_url($img_url); ?>') no-repeat center center; background-size: cover;
            display: none;
        }
        @media(min-width: 768px) {
            #acp-popup-image-<?php echo esc_attr($id); ?> { display: block; }
        }
        <?php endif; ?>
        #acp-popup-box-<?php echo esc_attr($id); ?> .acp-form-group input,
        #acp-popup-box-<?php echo esc_attr($id); ?> .acp-form-group textarea,
        #acp-popup-box-<?php echo esc_attr($id); ?> .acp-form-group select {
            border: 1px solid <?php echo $theme === 'dark' ? '#444' : '#ccc'; ?>;
            background: <?php echo $theme === 'dark' ? '#2d2d2d' : '#fff'; ?>;
            color: <?php echo $theme === 'dark' ? '#fff' : '#000'; ?>;
        }
        #acp-popup-box-<?php echo esc_attr($id); ?> .acp-popup-close {
            color: <?php echo $theme === 'dark' ? '#bbb' : '#666'; ?> !important;
        }
    </style>

    <div id="acp-popup-overlay-<?php echo esc_attr($id); ?>" class="acp-popup-overlay">
        <div id="acp-popup-box-<?php echo esc_attr($id); ?>" class="acp-popup-box">
            <?php if($img_url): ?>
            <div id="acp-popup-image-<?php echo esc_attr($id); ?>"></div>
            <?php endif; ?>
            <div class="acp-popup-content">
                <span class="acp-popup-close" data-id="<?php echo esc_attr($id); ?>">&times;</span>
                <h2 style="margin-top:0; text-align:center; font-size: 22px;"><?php echo esc_html($title); ?></h2>
                <form class="acp-form" data-id="<?php echo esc_attr($id); ?>">
                    <?php wp_nonce_field('acp_submit_action', 'acp_submit_nonce'); ?>
                    <input type="hidden" name="acp_form_id" value="<?php echo esc_attr($id); ?>">

                    <?php if (isset($f['show_dept']) && $f['show_dept'] == '1' && !empty($dept_options)): ?>
                    <div class="acp-form-group">
                        <label><?php echo esc_html(acp_t('بخش / موضوع', 'Department / Subject', 'Abteilung / Thema')); ?> <?php if(isset($f['req_dept']) && $f['req_dept'] == '1') echo '*'; ?></label>
                        <select name="acp_department" <?php if(isset($f['req_dept']) && $f['req_dept'] == '1') echo 'required'; ?>>
                            <option value=""><?php echo esc_html(acp_t('انتخاب کنید...', 'Select...', 'Auswählen...')); ?></option>
                            <?php foreach($dept_options as $opt): $opt = trim($opt); if(empty($opt)) continue; ?>
                                <option value="<?php echo esc_attr($opt); ?>"><?php echo esc_html($opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <?php if (isset($f['show_name']) && $f['show_name'] == '1'): ?>
                    <div class="acp-form-group">
                        <label><?php echo esc_html(acp_t('نام و نام خانوادگی', 'Full Name', 'Vollständiger Name')); ?> <?php if(isset($f['req_name']) && $f['req_name'] == '1') echo '*'; ?></label>
                        <input type="text" name="acp_name" <?php if(isset($f['req_name']) && $f['req_name'] == '1') echo 'required'; ?>>
                    </div>
                    <?php endif; ?>

                    <?php if (isset($f['show_email']) && $f['show_email'] == '1'): ?>
                    <div class="acp-form-group">
                        <label><?php echo esc_html(acp_t('ایمیل', 'Email', 'E-Mail')); ?> <?php if(isset($f['req_email']) && $f['req_email'] == '1') echo '*'; ?></label>
                        <input type="email" name="acp_email" <?php if(isset($f['req_email']) && $f['req_email'] == '1') echo 'required'; ?>>
                    </div>
                    <?php endif; ?>

                    <?php if (isset($f['show_phone']) && $f['show_phone'] == '1'): ?>
                    <div class="acp-form-group">
                        <label><?php echo esc_html(acp_t('شماره تماس', 'Phone Number', 'Telefonnummer')); ?> <?php if(isset($f['req_phone']) && $f['req_phone'] == '1') echo '*'; ?></label>
                        <input type="tel" name="acp_phone" <?php if(isset($f['req_phone']) && $f['req_phone'] == '1') echo 'required'; ?>>
                    </div>
                    <?php endif; ?>

                    <?php if (isset($f['show_date']) && $f['show_date'] == '1'): ?>
                    <div class="acp-form-group">
                        <label><?php echo esc_html(acp_t('تاریخ درخواستی', 'Requested Date', 'Gewünschtes Datum')); ?> <?php if(isset($f['req_date']) && $f['req_date'] == '1') echo '*'; ?></label>
                        <input type="date" name="acp_date" <?php if(isset($f['req_date']) && $f['req_date'] == '1') echo 'required'; ?> min="<?php echo date('Y-m-d'); ?>" onclick="if(this.showPicker) this.showPicker();">
                    </div>
                    <?php endif; ?>

                    <?php if (isset($f['show_msg']) && $f['show_msg'] == '1'): ?>
                    <div class="acp-form-group">
                        <label><?php echo esc_html(acp_t('پیام شما', 'Your Message', 'Ihre Nachricht')); ?> <?php if(isset($f['req_msg']) && $f['req_msg'] == '1') echo '*'; ?></label>
                        <textarea name="acp_message" rows="3" <?php if(isset($f['req_msg']) && $f['req_msg'] == '1') echo 'required'; ?>></textarea>
                    </div>
                    <?php endif; ?>

                    <?php if (isset($f['show_file']) && $f['show_file'] == '1'): ?>
                    <div class="acp-form-group">
                        <label><?php echo esc_html(acp_t('آپلود فایل', 'Upload File', 'Datei hochladen')); ?> <?php if(isset($f['req_file']) && $f['req_file'] == '1') echo '*'; ?></label>
                        <input type="file" name="acp_attachment" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" <?php if(isset($f['req_file']) && $f['req_file'] == '1') echo 'required'; ?>>
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
                        <input type="hidden" name="acp_v3_token" id="acp_v3_token_<?php echo esc_attr($id); ?>">
                    <?php endif; ?>

                    <button type="submit" class="acp-btn acp-submit-btn"><?php echo esc_html(acp_t('ثبت درخواست', 'Submit Request', 'Anfrage absenden')); ?></button>
                    <div class="acp-msg"></div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var formsData = <?php echo json_encode(array_keys($forms)); ?>;
            var recaptchaType = '<?php echo esc_js($recaptcha_type); ?>';
            var siteKey = '<?php echo esc_js($site_key); ?>';

            formsData.forEach(function(id) {
                var overlay = document.getElementById('acp-popup-overlay-' + id);
                var closeBtns = document.querySelectorAll('.acp-popup-close[data-id="' + id + '"]');
                var triggers = document.querySelectorAll('.acp-trigger-popup-' + id + ', .acp-trigger-popup-' + id + ' a, .acp-trigger-popup-' + id + ' button');

                // legacy support
                if (id === 'default') {
                    var legacyTriggers = document.querySelectorAll('.acp-trigger-popup, .acp-trigger-popup a, .acp-trigger-popup button');
                    legacyTriggers.forEach(function(trigger) {
                        trigger.addEventListener('click', function(e) {
                            e.preventDefault();
                            overlay.style.display = 'flex';
                        });
                    });
                }

                triggers.forEach(function(trigger) {
                    trigger.addEventListener('click', function(e) {
                        e.preventDefault();
                        overlay.style.display = 'flex';
                    });
                });

                closeBtns.forEach(function(closeBtn) {
                    closeBtn.addEventListener('click', function() {
                        overlay.style.display = 'none';
                    });
                });

                overlay.addEventListener('click', function(e) {
                    if (e.target === overlay) {
                        overlay.style.display = 'none';
                    }
                });

                var form = document.querySelector('form[data-id="' + id + '"]');
                if(form) {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        var btn = form.querySelector('.acp-submit-btn');
                        var msg = form.querySelector('.acp-msg');

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
                                    msg.className = 'acp-msg acp-success';
                                    msg.innerText = data.data.message;
                                    form.reset();
                                    if (recaptchaType === 'v2' && typeof grecaptcha !== 'undefined') grecaptcha.reset();
                                    setTimeout(() => { overlay.style.display = 'none'; msg.style.display = 'none'; }, 3000);
                                } else {
                                    msg.className = 'acp-msg acp-error';
                                    msg.innerText = data.data.message;
                                    if (recaptchaType === 'v2' && typeof grecaptcha !== 'undefined') grecaptcha.reset();
                                }
                                btn.disabled = false;
                                btn.innerText = '<?php echo esc_js(acp_t("ثبت درخواست", "Submit Request", "Anfrage absenden")); ?>';
                            })
                            .catch(error => {
                                msg.style.display = 'block';
                                msg.className = 'acp-msg acp-error';
                                msg.innerText = '<?php echo esc_js(acp_t("خطای شبکه رخ داد.", "Network error occurred.", "Netzwerkfehler aufgetreten.")); ?>';
                                btn.disabled = false;
                                btn.innerText = '<?php echo esc_js(acp_t("ثبت درخواست", "Submit Request", "Anfrage absenden")); ?>';
                            });
                        }

                        if (recaptchaType === 'v3' && siteKey && typeof grecaptcha !== 'undefined') {
                            grecaptcha.ready(function() {
                                grecaptcha.execute(siteKey, {action: 'submit'}).then(function(token) {
                                    var tokenInput = document.getElementById('acp_v3_token_' + id);
                                    if(tokenInput) tokenInput.value = token;
                                    sendFormData(token);
                                });
                            });
                        } else {
                            sendFormData();
                        }
                    });
                }
            });
        });
    </script>
    <?php
}

add_action('wp_footer', 'acp_render_popup_html');
