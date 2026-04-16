<?php
if (!defined('ABSPATH')) exit;

function acp_inpost_admin_scripts() {
    wp_enqueue_media();
}

function acp_render_inpost_page() {
    if (isset($_POST['acp_save_inpost']) && check_admin_referer('acp_inpost_action', 'acp_inpost_nonce')) {
        $inpost_settings = [
            'enabled' => isset($_POST['enabled']) ? '1' : '0',
            'form_id' => sanitize_text_field($_POST['form_id'] ?? 'default'),
            'devices' => sanitize_text_field($_POST['devices'] ?? 'all'),
            'categories' => isset($_POST['categories']) && is_array($_POST['categories']) ? array_map('intval', $_POST['categories']) : [],

            // Button Design
            'button_text' => sanitize_text_field($_POST['button_text'] ?? ''),
            'button_color' => sanitize_hex_color($_POST['button_color'] ?? '#007cba'),
            'button_text_color' => sanitize_hex_color($_POST['button_text_color'] ?? '#ffffff'),
            'button_icon' => sanitize_text_field($_POST['button_icon'] ?? 'dashicons-format-chat'),
            'button_font_size' => intval($_POST['button_font_size'] ?? 16),
            'button_width' => sanitize_text_field($_POST['button_width'] ?? 'auto'),
            'button_animation' => sanitize_text_field($_POST['button_animation'] ?? 'none'),

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
        'devices' => 'all',
        'categories' => [],
        'button_text' => acp_t('درخواست مشاوره', 'Request Consultation', 'Beratung anfordern'),
        'button_color' => '#007cba',
        'button_text_color' => '#ffffff',
        'button_icon' => 'dashicons-format-chat',
        'button_font_size' => 16,
        'button_width' => 'auto',
        'button_animation' => 'none',
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
                        <select name="form_id" required>
                            <option value=""><?php echo esc_html(acp_t('انتخاب کنید...', 'Select...', 'Auswählen...')); ?></option>
                            <?php foreach ($forms as $id => $f): ?>
                                <option value="<?php echo esc_attr($id); ?>" <?php selected($settings['form_id'], $id); ?>><?php echo esc_html($f['form_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html(acp_t('انیمیشن دکمه', 'Button Animation', 'Tastenanimation')); ?></th>
                    <td>
                        <select name="button_animation">
                            <option value="none" <?php selected($settings['button_animation'], 'none'); ?>><?php echo esc_html(acp_t('بدون انیمیشن', 'None', 'Keine')); ?></option>
                            <option value="pulse" <?php selected($settings['button_animation'], 'pulse'); ?>><?php echo esc_html(acp_t('ضربان دار (Pulse)', 'Pulse', 'Pulsieren')); ?></option>
                            <option value="shake" <?php selected($settings['button_animation'], 'shake'); ?>><?php echo esc_html(acp_t('تکان ریز (Shake)', 'Shake', 'Schütteln')); ?></option>
                            <option value="shine" <?php selected($settings['button_animation'], 'shine'); ?>><?php echo esc_html(acp_t('برق زدن (Shine)', 'Shine', 'Glänzen')); ?></option>
                            <option value="bounce" <?php selected($settings['button_animation'], 'bounce'); ?>><?php echo esc_html(acp_t('پرش (Bounce)', 'Bounce', 'Hüpfen')); ?></option>
                            <option value="glow" <?php selected($settings['button_animation'], 'glow'); ?>><?php echo esc_html(acp_t('درخشش (Glow)', 'Glow', 'Leuchten')); ?></option>
                            <option value="float" <?php selected($settings['button_animation'], 'float'); ?>><?php echo esc_html(acp_t('شناور (Float)', 'Float', 'Schweben')); ?></option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php echo esc_html(acp_t('نمایش در دستگاه‌ها', 'Display on Devices', 'Auf Geräten anzeigen')); ?></th>
                    <td>
                        <select name="devices">
                            <option value="all" <?php selected($settings['devices'], 'all'); ?>><?php echo esc_html(acp_t('همه دستگاه‌ها', 'All Devices', 'Alle Geräte')); ?></option>
                            <option value="desktop" <?php selected($settings['devices'], 'desktop'); ?>><?php echo esc_html(acp_t('فقط دسکتاپ', 'Desktop Only', 'Nur Desktop')); ?></option>
                            <option value="mobile" <?php selected($settings['devices'], 'mobile'); ?>><?php echo esc_html(acp_t('فقط موبایل/تبلت', 'Mobile/Tablet Only', 'Nur Mobile/Tablet')); ?></option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php echo esc_html(acp_t('نمایش در دسته‌بندی‌ها', 'Display in Categories', 'In Kategorien anzeigen')); ?></th>
                    <td>
                        <select name="categories[]" multiple style="min-width: 200px; height: 100px;">
                            <?php
                            $all_categories = get_categories(['hide_empty' => false]);
                            $selected_cats = $settings['categories'];
                            if (empty($selected_cats) || !is_array($selected_cats)) $selected_cats = [];
                            foreach ($all_categories as $cat):
                            ?>
                                <option value="<?php echo esc_attr($cat->term_id); ?>" <?php echo in_array($cat->term_id, $selected_cats) ? 'selected' : ''; ?>>
                                    <?php echo esc_html($cat->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php echo esc_html(acp_t('اگر هیچ دسته‌ای انتخاب نشود، در همه نوشته‌ها نمایش داده می‌شود. برای انتخاب چند مورد، کلید Ctrl یا Command را نگه دارید.', 'If no category is selected, it will display on all posts. Hold Ctrl or Command to select multiple.', 'Wenn keine Kategorie ausgewählt ist, wird sie in allen Beiträgen angezeigt. Halten Sie Strg oder Command gedrückt, um mehrere auszuwählen.')); ?></p>
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
                        <span class="description"><a href="https://developer.wordpress.org/resource/dashicons/" target="_blank"><?php echo esc_html(acp_t('لیست آیکون‌ها', 'Dashicons List', 'Dashicons-Liste')); ?></a></span>
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
                    <td>
                        <input type="url" name="banner_url" id="acp_banner_url" class="regular-text" value="<?php echo esc_url($settings['banner_url']); ?>">
                        <button type="button" class="button" id="acp_upload_banner_btn"><?php echo esc_html(acp_t('آپلود / انتخاب تصویر', 'Upload / Select Image', 'Bild hochladen / auswählen')); ?></button>
                        <p class="description"><?php echo esc_html(acp_t('ابعاد پیشنهادی: عرض ۸۰۰ پیکسل در ارتفاع ۲۰۰ تا ۳۰۰ پیکسل (بهینه برای محتوای وبلاگ).', 'Recommended dimensions: 800px width by 200-300px height (optimized for blog content).', 'Empfohlene Abmessungen: 800px Breite bei 200-300px Höhe (optimiert für Blog-Inhalte).')); ?></p>
                    </td>
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

        <script>
            jQuery(document).ready(function($){
                $('#acp_upload_banner_btn').click(function(e) {
                    e.preventDefault();
                    var image_frame;
                    if(image_frame){
                        image_frame.open();
                    }
                    image_frame = wp.media({
                        title: '<?php echo esc_js(acp_t("انتخاب بنر", "Select Banner", "Banner auswählen")); ?>',
                        multiple : false,
                        library : { type : 'image'}
                    });
                    image_frame.on('close',function() {
                        var selection =  image_frame.state().get('selection').first();
                        if (selection) {
                            var gallery_attachment = selection.toJSON();
                            $('#acp_banner_url').val(gallery_attachment.url);
                        }
                    });
                    image_frame.on('open',function() {
                        var selection =  image_frame.state().get('selection');
                        var id = $('#acp_banner_url').val();
                    });
                    image_frame.open();
                });
            }));
        </script>

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
    // If automatic injection is disabled for this post, bypass it
    if (get_post_meta(get_the_ID(), '_acp_disable_inpost', true) === '1') {
        return $content;
    }

    if (!is_single() || !in_the_loop() || !is_main_query()) {
        return $content;
    }

    $settings = get_option('acp_inpost_settings');
    if (empty($settings['enabled']) || $settings['enabled'] !== '1' || empty($settings['rules'])) {
        return $content;
    }

    // Check device visibility
    $device = isset($settings['devices']) ? $settings['devices'] : 'all';
    if ($device === 'desktop' && wp_is_mobile()) {
        return $content;
    }
    if ($device === 'mobile' && !wp_is_mobile()) {
        return $content;
    }

    // Check categories
    $categories = isset($settings['categories']) ? $settings['categories'] : [];
    if (!empty($categories) && !in_category($categories)) {
        return $content;
    }

    $elements = acp_generate_inpost_elements($settings);
    $button_html = $elements['button'];
    $banner_html = $elements['banner'];

    if (empty(trim($content))) {
        return $content;
    }

    $dom = new DOMDocument();
    $previous_value = libxml_use_internal_errors(true);

    // Load content properly handling UTF-8
    if (PHP_VERSION_ID >= 80200) {
        $content_mb = mb_encode_numericentity($content, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');
    } else {
        $content_mb = mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8');
    }
    $dom->loadHTML('<body>' . $content_mb . '</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous_value);

    $body = $dom->getElementsByTagName('body')->item(0);
    if (!$body) {
        return $content;
    }

    // Determine if a node is purely a structural wrapper without direct semantic content
    $is_structural_wrapper = function($node) {
        if (!in_array(strtolower($node->nodeName), ['div', 'section', 'article', 'main', 'aside', 'header', 'footer'])) {
            return false;
        }

        // Explicitly reject drilling down into page builder modules, accordions, tabs, etc.
        if ($node->hasAttribute('class')) {
            $classes = explode(' ', strtolower($node->getAttribute('class')));
            $stop_keywords = [
                'module', 'widget', 'accordion', 'toggle', 'faq', 'tab', 'slider', 'carousel', 'gallery'
            ];
            foreach ($classes as $class) {
                foreach ($stop_keywords as $keyword) {
                    if (strpos($class, $keyword) !== false) {
                        return false;
                    }
                }
            }
        }

        $content_tags = ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'table', 'blockquote', 'img', 'figure', 'span', 'a', 'strong', 'em', 'b', 'i', 'iframe', 'video', 'audio'];
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE && in_array(strtolower($child->nodeName), $content_tags)) {
                return false;
            }
            if ($child->nodeType === XML_TEXT_NODE && trim($child->nodeValue) !== '') {
                return false;
            }
        }
        return true;
    };

    // Find the best top-level container to insert blocks into
    $container = $body;
    while (true) {
        $element_children = [];
        $text_length = 0;
        foreach ($container->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $element_children[] = $child;
            } elseif ($child->nodeType === XML_TEXT_NODE) {
                $text_length += strlen(trim($child->nodeValue));
            }
        }

        // Only drill down if there is exactly 1 child element, no direct text, and that child is purely a structural wrapper
        if (count($element_children) === 1 && $text_length === 0) {
            if ($is_structural_wrapper($element_children[0])) {
                $container = $element_children[0];
            } else {
                break;
            }
        } else {
            break;
        }
    }

    $children = [];
    foreach ($container->childNodes as $child) {
        // Skip purely whitespace text nodes
        if ($child->nodeType === XML_TEXT_NODE && trim($child->nodeValue) === '') {
            continue;
        }
        $children[] = $child;
    }

    $total_blocks = count($children);
    if ($total_blocks === 0) {
        // Fallback if structure is unexpected
        $new_content = $content;
    } else {
        $insertions = [];
        foreach ($settings['rules'] as $rule) {
            $pos_percent = intval($rule['position']);
            $type = $rule['type'];

            $idx = floor(($pos_percent / 100) * $total_blocks);
            if ($idx < 0) $idx = 0;
            if ($idx > $total_blocks) $idx = $total_blocks;

            if (!isset($insertions[$idx])) {
                $insertions[$idx] = '';
            }
            if ($type === 'button') {
                $insertions[$idx] .= $button_html;
            } elseif ($type === 'banner' && $banner_html) {
                $insertions[$idx] .= $banner_html;
            }
        }

        // Insert backwards to avoid messing up indices
        for ($i = $total_blocks; $i >= 0; $i--) {
            if (!empty($insertions[$i])) {
                $temp_dom = new DOMDocument();
                $temp_prev = libxml_use_internal_errors(true);

                if (PHP_VERSION_ID >= 80200) {
                    $insertions_mb = mb_encode_numericentity($insertions[$i], [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');
                } else {
                    $insertions_mb = mb_convert_encoding($insertions[$i], 'HTML-ENTITIES', 'UTF-8');
                }

                $temp_dom->loadHTML('<body>' . $insertions_mb . '</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                libxml_clear_errors();
                libxml_use_internal_errors($temp_prev);

                $temp_body = $temp_dom->getElementsByTagName('body')->item(0);
                if ($temp_body) {
                    $nodes_to_insert = [];
                    foreach ($temp_body->childNodes as $node) {
                        $nodes_to_insert[] = $dom->importNode($node, true);
                    }
                    foreach ($nodes_to_insert as $imported_node) {
                        if ($i < $total_blocks) {
                            $container->insertBefore($imported_node, $children[$i]);
                        } else {
                            $container->appendChild($imported_node);
                        }
                    }
                }
            }
        }

        $new_content = '';
        foreach ($body->childNodes as $child) {
            $new_content .= $dom->saveHTML($child);
        }
    }

    $assets = acp_generate_inpost_assets($settings);
    return $new_content . $assets;
}
add_filter('the_content', 'acp_inpost_the_content');


// Helper function to generate Button and Banner HTML based on settings
function acp_generate_inpost_elements($settings, $post_id = null) {
    if (!$post_id) $post_id = get_the_ID();

    $form_id = esc_attr($settings['form_id']);
    $btn_text = esc_html($settings['button_text']);
    $btn_bg = esc_attr($settings['button_color']);
    $btn_color = esc_attr($settings['button_text_color']);
    $btn_fs = intval($settings['button_font_size']) . 'px';
    $btn_icon = esc_attr($settings['button_icon']);
    $btn_width = isset($settings['button_width']) && $settings['button_width'] === 'full' ? '100%' : 'auto';
    $btn_anim = isset($settings['button_animation']) ? $settings['button_animation'] : 'none';
    $btn_class = 'acp-trigger-popup-' . $form_id . ' acp-inpost-btn';

    if ($btn_anim !== 'none') {
        $btn_class .= ' acp-anim-' . $btn_anim;
    }

    $button_html = '<div style="text-align: center; margin: 20px 0;"><button class="' . $btn_class . '" style="background-color: ' . $btn_bg . '; color: ' . $btn_color . '; font-size: ' . $btn_fs . '; width: ' . $btn_width . '; border: none; border-radius: 5px; padding: 10px 20px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px;" data-post-id="' . $post_id . '"><span class="dashicons ' . $btn_icon . '" style="font-size: ' . $btn_fs . '; width: auto; height: auto;"></span> ' . $btn_text . '</button></div>';

    // Build Banner HTML
    $banner_html = '';
    if (!empty($settings['banner_url'])) {
        $ban_url = esc_url($settings['banner_url']);
        $ban_width = isset($settings['banner_width']) && $settings['banner_width'] === 'full' ? '100%' : 'auto';
        $ban_class = 'acp-trigger-popup-' . $form_id . ' acp-inpost-banner';
        $banner_html = '<div style="text-align: center; margin: 20px 0;"><img src="' . $ban_url . '" class="' . $ban_class . '" style="width: ' . $ban_width . '; max-width: 100%; border-radius: 5px; cursor: pointer;" data-post-id="' . $post_id . '" alt="Banner"></div>';
    }

    return ['button' => $button_html, 'banner' => $banner_html];
}

function acp_generate_inpost_assets($settings) {
    static $assets_loaded = false;
    if ($assets_loaded) return '';
    $assets_loaded = true;

    $css = '';
    if (isset($settings['button_animation']) && $settings['button_animation'] !== 'none') {
        $css = "
        <style>
        .acp-anim-pulse { animation: acpPulse 1.5s infinite; }
        @keyframes acpPulse { 0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(0,0,0,0.3); } 50% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(0,0,0,0); } 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(0,0,0,0); } }
        .acp-anim-shake { animation: acpShake 2s infinite; }
        @keyframes acpShake { 0%, 100% { transform: translateX(0); } 10%, 30%, 50%, 70%, 90% { transform: translateX(-4px); } 20%, 40%, 60%, 80% { transform: translateX(4px); } }
        .acp-anim-shine { position: relative; overflow: hidden; }
        .acp-anim-shine::after { content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: linear-gradient(to right, rgba(255,255,255,0) 0%, rgba(255,255,255,0.4) 50%, rgba(255,255,255,0) 100%); transform: rotate(30deg); animation: acpShine 3s infinite; }
        @keyframes acpShine { 0% { transform: translateX(-100%) rotate(30deg); } 20%, 100% { transform: translateX(100%) rotate(30deg); } }
        .acp-anim-bounce { animation: acpBounce 2s infinite; }
        @keyframes acpBounce { 0%, 20%, 50%, 80%, 100% { transform: translateY(0); } 40% { transform: translateY(-10px); } 60% { transform: translateY(-5px); } }
        .acp-anim-glow { animation: acpGlow 2s infinite alternate; }
        @keyframes acpGlow { from { box-shadow: 0 0 5px currentColor; } to { box-shadow: 0 0 20px currentColor, 0 0 30px currentColor; } }
        .acp-anim-float { animation: acpFloat 3s ease-in-out infinite; }
        @keyframes acpFloat { 0% { transform: translateY(0px); } 50% { transform: translateY(-8px); } 100% { transform: translateY(0px); } }
        </style>
        ";
    }

    $nonce = wp_create_nonce('acp_inpost_click');
    $ajax_url = admin_url('admin-ajax.php');
    $js = "
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var elements = document.querySelectorAll('.acp-inpost-btn, .acp-inpost-banner');
        elements.forEach(function(el) {
            // Prevent binding multiple times
            if (el.dataset.acpBound) return;
            el.dataset.acpBound = 'true';
            el.addEventListener('click', function() {
                var postId = this.getAttribute('data-post-id');
                if (!postId) return;
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
    return $css . $js;
}

// Shortcode for manual placement
function acp_inpost_shortcode($atts) {
    $atts = shortcode_atts([
        'type' => 'button' // 'button' or 'banner'
    ], $atts, 'acp_inpost');

    $settings = get_option('acp_inpost_settings');
    if (empty($settings['form_id'])) {
        return '';
    }

    $elements = acp_generate_inpost_elements($settings);
    $assets = acp_generate_inpost_assets($settings);

    $html = $atts['type'] === 'banner' ? $elements['banner'] : $elements['button'];
    return $html . $assets;
}
add_shortcode('acp_inpost', 'acp_inpost_shortcode');


// Add Meta Box for disabling automatic injection
function acp_add_inpost_meta_box() {
    $screens = ['post', 'page'];
    foreach ($screens as $screen) {
        add_meta_box(
            'acp_inpost_meta_box',
            acp_t('تنظیمات پاپ‌آپ درون‌نوشته', 'In-Post Popup Settings', 'In-Post-Popup-Einstellungen'),
            'acp_inpost_meta_box_html',
            $screen,
            'side',
            'default'
        );
    }
}
add_action('add_meta_boxes', 'acp_add_inpost_meta_box');

function acp_inpost_meta_box_html($post) {
    $value = get_post_meta($post->ID, '_acp_disable_inpost', true);
    ?>
    <label for="acp_disable_inpost">
        <input type="checkbox" name="acp_disable_inpost" id="acp_disable_inpost" value="1" <?php checked($value, '1'); ?>>
        <?php echo esc_html(acp_t('غیرفعال‌سازی نمایش خودکار در این نوشته', 'Disable automatic display on this post', 'Automatische Anzeige in diesem Beitrag deaktivieren')); ?>
    </label>
    <p class="description" style="margin-top: 10px;">
        <?php echo esc_html(acp_t('می‌توانید از شورت‌کدهای زیر برای نمایش دستی استفاده کنید:', 'You can use the following shortcodes for manual display:', 'Sie können die folgenden Shortcodes für die manuelle Anzeige verwenden:')); ?>
        <br><br>
        <code>[acp_inpost type="button"]</code><br>
        <code>[acp_inpost type="banner"]</code>
    </p>
    <?php
    wp_nonce_field('acp_inpost_meta_box_save', 'acp_inpost_meta_box_nonce');
}

function acp_save_inpost_meta_box_data($post_id) {
    if (!isset($_POST['acp_inpost_meta_box_nonce'])) {
        return;
    }
    if (!wp_verify_nonce($_POST['acp_inpost_meta_box_nonce'], 'acp_inpost_meta_box_save')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (isset($_POST['acp_disable_inpost'])) {
        update_post_meta($post_id, '_acp_disable_inpost', '1');
    } else {
        delete_post_meta($post_id, '_acp_disable_inpost');
    }
}
add_action('save_post', 'acp_save_inpost_meta_box_data');
