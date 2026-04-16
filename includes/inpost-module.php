<?php
if (!defined('ABSPATH')) exit;

function acp_render_inpost_page() {
    if (isset($_POST['acp_save_inpost']) && check_admin_referer('acp_inpost_action', 'acp_inpost_nonce')) {
        $inpost_settings = [
            'enabled' => isset($_POST['enabled']) ? '1' : '0',
            'form_id' => sanitize_text_field($_POST['form_id'] ?? 'default'),

            // Button Design
            'button_text' => sanitize_text_field($_POST['button_text'] ?? ''),
            'button_color' => sanitize_hex_color($_POST['button_color'] ?? '#007cba'),
            'button_text_color' => sanitize_hex_color($_POST['button_text_color'] ?? '#ffffff'),
            'button_icon' => sanitize_text_field($_POST['button_icon'] ?? 'dashicons-format-chat'),
            'button_font_size' => intval($_POST['button_font_size'] ?? 16),
            'button_width' => sanitize_text_field($_POST['button_width'] ?? 'auto'),

            // Banner Design
            'banner_url' => sanitize_url($_POST['banner_url'] ?? ''),
            'banner_width' => sanitize_text_field($_POST['banner_width'] ?? 'full'),

            // Rules
            'rules' => []
        ];

        if (isset($_POST['rules']) && is_array($_POST['rules'])) {
            foreach ($_POST['rules'] as $rule) {
                if (!empty($rule['position']) && !empty($rule['type'])) {
                    $inpost_settings['rules'][] = [
                        'type' => sanitize_text_field($rule['type']),
                        'position' => intval($rule['position'])
                    ];
                }
            }
        }

        // Sort rules by position
        usort($inpost_settings['rules'], function($a, $b) {
            return $a['position'] <=> $b['position'];
        });

        update_option('acp_inpost_settings', $inpost_settings);
        echo '<div class="updated"><p>' . esc_html(acp_t('تنظیمات ماژول درون‌نوشته ذخیره شد.', 'In-post module settings saved.', 'In-Post-Modul-Einstellungen gespeichert.')) . '</p></div>';
    }

    $settings = get_option('acp_inpost_settings', [
        'enabled' => '0',
        'form_id' => 'default',
        'button_text' => acp_t('درخواست مشاوره', 'Request Consultation', 'Beratung anfordern'),
        'button_color' => '#007cba',
        'button_text_color' => '#ffffff',
        'button_icon' => 'dashicons-format-chat',
        'button_font_size' => 16,
        'button_width' => 'auto',
        'banner_url' => '',
        'banner_width' => 'full',
        'rules' => []
    ]);

    $forms = get_option('acp_forms', []);
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(acp_t('ماژول درون‌نوشته (نمایش خودکار بنر و دکمه بین محتوا)', 'In-Post Module (Auto Banner/Button in Content)', 'In-Post-Modul (Automatisches Banner/Button im Inhalt)')); ?></h1>
        <form method="post">
            <?php wp_nonce_field('acp_inpost_action', 'acp_inpost_nonce'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html(acp_t('فعال‌سازی ماژول', 'Enable Module', 'Modul aktivieren')); ?></th>
                    <td>
                        <input type="checkbox" name="enabled" value="1" <?php checked($settings['enabled'], '1'); ?>>
                        <span class="description"><?php echo esc_html(acp_t('در صورت فعال‌سازی، دکمه و بنر بر اساس قوانین تعیین شده در نوشته‌های وبلاگ نمایش داده می‌شوند.', 'If enabled, button and banner will be displayed in blog posts based on rules.', 'Wenn aktiviert, werden Button und Banner basierend auf Regeln in Blog-Posts angezeigt.')); ?></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html(acp_t('فرم متصل', 'Connected Form', 'Verbundenes Formular')); ?></th>
                    <td>
                        <select name="form_id">
                            <?php foreach ($forms as $id => $f): ?>
                                <option value="<?php echo esc_attr($id); ?>" <?php selected($settings['form_id'], $id); ?>><?php echo esc_html($f['form_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <tr><th colspan="2"><h3><?php echo esc_html(acp_t('طراحی دکمه', 'Button Design', 'Button-Design')); ?></h3></th></tr>
                <tr>
                    <th scope="row"><?php echo esc_html(acp_t('متن دکمه', 'Button Text', 'Button-Text')); ?></th>
                    <td><input type="text" name="button_text" class="regular-text" value="<?php echo esc_attr($settings['button_text']); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html(acp_t('رنگ پس‌زمینه دکمه', 'Button Background Color', 'Button-Hintergrundfarbe')); ?></th>
                    <td><input type="color" name="button_color" value="<?php echo esc_attr($settings['button_color']); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html(acp_t('رنگ متن دکمه', 'Button Text Color', 'Button-Textfarbe')); ?></th>
                    <td><input type="color" name="button_text_color" value="<?php echo esc_attr($settings['button_text_color']); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html(acp_t('آیکون (Dashicons)', 'Icon (Dashicons)', 'Symbol (Dashicons)')); ?></th>
                    <td>
                        <input type="text" name="button_icon" class="regular-text" value="<?php echo esc_attr($settings['button_icon']); ?>" placeholder="dashicons-format-chat">
                        <span class="description"><a href="https://developer.wordpress.org/resource/dashicons/" target="_blank">لیست آیکون‌ها</a></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html(acp_t('اندازه فونت (px)', 'Font Size (px)', 'Schriftgröße (px)')); ?></th>
                    <td><input type="number" name="button_font_size" value="<?php echo esc_attr($settings['button_font_size']); ?>" min="10" max="50"></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html(acp_t('ابعاد دکمه', 'Button Width', 'Button-Breite')); ?></th>
                    <td>
                        <select name="button_width">
                            <option value="auto" <?php selected($settings['button_width'], 'auto'); ?>><?php echo esc_html(acp_t('اندازه خودکار (معمولی)', 'Auto Width (Normal)', 'Automatische Breite (Normal)')); ?></option>
                            <option value="full" <?php selected($settings['button_width'], 'full'); ?>><?php echo esc_html(acp_t('تمام صفحه (Full Width)', 'Full Width', 'Volle Breite')); ?></option>
                        </select>
                    </td>
                </tr>

                <tr><th colspan="2"><h3><?php echo esc_html(acp_t('طراحی بنر', 'Banner Design', 'Banner-Design')); ?></h3></th></tr>
                <tr>
                    <th scope="row"><?php echo esc_html(acp_t('آدرس تصویر بنر', 'Banner Image URL', 'Banner-Bild-URL')); ?></th>
                    <td><input type="url" name="banner_url" class="regular-text" value="<?php echo esc_url($settings['banner_url']); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html(acp_t('ابعاد بنر', 'Banner Width', 'Banner-Breite')); ?></th>
                    <td>
                        <select name="banner_width">
                            <option value="auto" <?php selected($settings['banner_width'], 'auto'); ?>><?php echo esc_html(acp_t('اندازه خودکار (معمولی)', 'Auto Width (Normal)', 'Automatische Breite (Normal)')); ?></option>
                            <option value="full" <?php selected($settings['banner_width'], 'full'); ?>><?php echo esc_html(acp_t('تمام صفحه (Full Width)', 'Full Width', 'Volle Breite')); ?></option>
                        </select>
                    </td>
                </tr>

                <tr><th colspan="2"><h3><?php echo esc_html(acp_t('قوانین نمایش در متن', 'Placement Rules', 'Platzierungsregeln')); ?></h3>
                <p class="description"><?php echo esc_html(acp_t('مشخص کنید که دکمه یا بنر در چه درصدی از متن نوشته (مثلا بعد از ۲۰٪ یا ۶۰٪) نمایش داده شود.', 'Specify at what percentage of the text (e.g., 20% or 60%) the button or banner should appear.', 'Geben Sie an, bei wie viel Prozent des Textes (z. B. 20 % oder 60 %) der Button oder das Banner erscheinen soll.')); ?></p>
                </th></tr>

                <tr>
                    <td colspan="2">
                        <table class="widefat" id="acp-rules-table" style="max-width: 600px;">
                            <thead>
                                <tr>
                                    <th><?php echo esc_html(acp_t('نوع المان', 'Element Type', 'Elementtyp')); ?></th>
                                    <th><?php echo esc_html(acp_t('موقعیت (درصد از متن)', 'Position (Percentage of text)', 'Position (Prozentsatz des Textes)')); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $rule_count = count($settings['rules']);
                                // show at least 3 rows or existing ones
                                $rows_to_show = max(5, $rule_count + 1);
                                for ($i = 0; $i < $rows_to_show; $i++):
                                    $rule = isset($settings['rules'][$i]) ? $settings['rules'][$i] : ['type' => '', 'position' => ''];
                                ?>
                                <tr>
                                    <td>
                                        <select name="rules[<?php echo $i; ?>][type]">
                                            <option value=""><?php echo esc_html(acp_t('انتخاب کنید...', 'Select...', 'Auswählen...')); ?></option>
                                            <option value="button" <?php selected($rule['type'], 'button'); ?>><?php echo esc_html(acp_t('دکمه', 'Button', 'Button')); ?></option>
                                            <option value="banner" <?php selected($rule['type'], 'banner'); ?>><?php echo esc_html(acp_t('بنر', 'Banner', 'Banner')); ?></option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" name="rules[<?php echo $i; ?>][position]" value="<?php echo esc_attr($rule['position']); ?>" min="1" max="100" placeholder="%">
                                    </td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </td>
                </tr>
            </table>

            <p><input type="submit" name="acp_save_inpost" class="button button-primary" value="<?php echo esc_attr(acp_t('ذخیره', 'Save', 'Speichern')); ?>"></p>
        </form>

        <hr style="margin-top: 30px;">
        <h2><?php echo esc_html(acp_t('آمار کلیک‌های ماژول', 'Module Click Statistics', 'Modul-Klickstatistiken')); ?></h2>

        <?php
        global $wpdb;
        $meta_key = 'acp_inpost_clicks';
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT post_id, meta_value FROM $wpdb->postmeta WHERE meta_key = %s ORDER BY CAST(meta_value AS UNSIGNED) DESC LIMIT 50",
            $meta_key
        ));
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php echo esc_html(acp_t('عنوان نوشته', 'Post Title', 'Beitragstitel')); ?></th>
                    <th><?php echo esc_html(acp_t('تعداد کلیک', 'Click Count', 'Klickzahl')); ?></th>
                    <th><?php echo esc_html(acp_t('مشاهده', 'View', 'Ansehen')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($results)): ?>
                    <tr><td colspan="3"><?php echo esc_html(acp_t('هنوز کلیکی ثبت نشده است.', 'No clicks recorded yet.', 'Noch keine Klicks aufgezeichnet.')); ?></td></tr>
                <?php else: foreach ($results as $row):
                    $post_title = get_the_title($row->post_id);
                    if (empty($post_title)) $post_title = 'Post ID: ' . $row->post_id;
                ?>
                    <tr>
                        <td><?php echo esc_html($post_title); ?></td>
                        <td><strong><?php echo intval($row->meta_value); ?></strong></td>
                        <td><a href="<?php echo get_permalink($row->post_id); ?>" target="_blank" class="button button-small"><?php echo esc_html(acp_t('مشاهده نوشته', 'View Post', 'Beitrag ansehen')); ?></a></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    if (function_exists('acp_admin_footer')) acp_admin_footer();
}

// Frontend Injection
function acp_inpost_the_content($content) {
    if (!is_single() || !in_the_loop() || !is_main_query()) {
        return $content;
    }

    $settings = get_option('acp_inpost_settings');
    if (empty($settings['enabled']) || $settings['enabled'] !== '1' || empty($settings['rules'])) {
        return $content;
    }

    $paragraphs = explode('</p>', $content);
    $total_p = count($paragraphs);
    if ($total_p <= 1) {
        return $content;
    }

    $inserted_positions = [];
    $form_id = esc_attr($settings['form_id']);

    // Build Button HTML
    $btn_text = esc_html($settings['button_text']);
    $btn_bg = esc_attr($settings['button_color']);
    $btn_color = esc_attr($settings['button_text_color']);
    $btn_fs = intval($settings['button_font_size']) . 'px';
    $btn_icon = esc_attr($settings['button_icon']);
    $btn_width = $settings['button_width'] === 'full' ? '100%' : 'auto';
    $btn_class = 'acp-trigger-popup-' . $form_id . ' acp-inpost-btn';

    $button_html = '<div style="text-align: center; margin: 20px 0;"><button class="' . $btn_class . '" style="background-color: ' . $btn_bg . '; color: ' . $btn_color . '; font-size: ' . $btn_fs . '; width: ' . $btn_width . '; border: none; border-radius: 5px; padding: 10px 20px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px;" data-post-id="' . get_the_ID() . '"><span class="dashicons ' . $btn_icon . '" style="font-size: ' . $btn_fs . '; width: auto; height: auto;"></span> ' . $btn_text . '</button></div>';

    // Build Banner HTML
    $banner_html = '';
    if (!empty($settings['banner_url'])) {
        $ban_url = esc_url($settings['banner_url']);
        $ban_width = $settings['banner_width'] === 'full' ? '100%' : 'auto';
        $ban_class = 'acp-trigger-popup-' . $form_id . ' acp-inpost-banner';
        $banner_html = '<div style="text-align: center; margin: 20px 0;"><img src="' . $ban_url . '" class="' . $ban_class . '" style="width: ' . $ban_width . '; max-width: 100%; border-radius: 5px; cursor: pointer;" data-post-id="' . get_the_ID() . '" alt="Banner"></div>';
    }

    foreach ($settings['rules'] as $rule) {
        $pos_percent = intval($rule['position']);
        $type = $rule['type'];

        $p_index = floor(($pos_percent / 100) * $total_p);

        // Adjust bounds
        if ($p_index < 0) $p_index = 0;
        if ($p_index >= $total_p) $p_index = $total_p - 1;

        // Ensure we don't insert multiple things at the exact same paragraph unless intended,
        // but array insertion makes it shift. Let's append to the specific paragraph.

        if (!isset($inserted_positions[$p_index])) {
            $inserted_positions[$p_index] = '';
        }

        if ($type === 'button') {
            $inserted_positions[$p_index] .= $button_html;
        } elseif ($type === 'banner' && $banner_html) {
            $inserted_positions[$p_index] .= $banner_html;
        }
    }

    $new_content = '';
    foreach ($paragraphs as $index => $p) {
        $new_content .= $p;
        if (trim($p) !== '') {
            $new_content .= '</p>';
        }
        if (isset($inserted_positions[$index])) {
            $new_content .= $inserted_positions[$index];
        }
    }

    // Add JS to track clicks
    $nonce = wp_create_nonce('acp_inpost_click');
    $ajax_url = admin_url('admin-ajax.php');
    $js = "
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var elements = document.querySelectorAll('.acp-inpost-btn, .acp-inpost-banner');
        elements.forEach(function(el) {
            el.addEventListener('click', function() {
                var postId = this.getAttribute('data-post-id');
                var formData = new FormData();
                formData.append('action', 'acp_track_inpost_click');
                formData.append('post_id', postId);
                formData.append('nonce', '{$nonce}');

                fetch('{$ajax_url}', {
                    method: 'POST',
                    body: formData
                });
            });
        });
    });
    </script>
    ";

    return $new_content . $js;
}
add_filter('the_content', 'acp_inpost_the_content');
