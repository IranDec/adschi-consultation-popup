<?php
/**
 * Consultation requests (CRM): list with filters / pagination / bulk actions, detail view with
 * notes history and follow-up date, CSV export.
 *
 * @author Mohammad Babaei <https://adschi.com>
 */
if (!defined('ABSPATH')) exit;

/** Escaped translation for output. */
function acp_te($fa, $en, $de) {
    return esc_html(acp_t($fa, $en, $de));
}

/** All request statuses: key => label, colours, dashicon. Order = workflow order. */
function acp_statuses() {
    return [
        'pending'    => ['label' => acp_t('جدید', 'New', 'Neu'), 'color' => '#b45309', 'bg' => '#fef3c7', 'icon' => 'dashicons-star-filled'],
        'call_later' => ['label' => acp_t('تماس مجدد', 'Call back', 'Rückruf'), 'color' => '#1d4ed8', 'bg' => '#dbeafe', 'icon' => 'dashicons-phone'],
        'called'     => ['label' => acp_t('تماس گرفته شد', 'Contacted', 'Kontaktiert'), 'color' => '#0e7490', 'bg' => '#cffafe', 'icon' => 'dashicons-format-chat'],
        'done'       => ['label' => acp_t('انجام شد', 'Done', 'Erledigt'), 'color' => '#15803d', 'bg' => '#dcfce7', 'icon' => 'dashicons-yes-alt'],
        'spam'       => ['label' => acp_t('اسپم', 'Spam', 'Spam'), 'color' => '#4b5563', 'bg' => '#f3f4f6', 'icon' => 'dashicons-dismiss'],
    ];
}

function acp_status_badge($status) {
    $all = acp_statuses();
    $s = isset($all[$status]) ? $all[$status] : ['label' => $status, 'color' => '#4b5563', 'bg' => '#f3f4f6', 'icon' => 'dashicons-marker'];
    return '<span class="acp-badge" style="color:' . esc_attr($s['color']) . ';background:' . esc_attr($s['bg']) . '">'
        . '<span class="dashicons ' . esc_attr($s['icon']) . '"></span>' . esc_html($s['label']) . '</span>';
}

function acp_crm_url($args = []) {
    return add_query_arg(array_merge(['page' => 'acp-requests'], $args), admin_url('admin.php'));
}

function acp_format_dt($mysql) {
    if (empty($mysql) || '0000-00-00 00:00:00' === $mysql) {
        return '';
    }
    $ts = strtotime($mysql);
    return $ts ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $ts) : $mysql;
}

/** "3 hours ago" style text, based on the site's time (created_at is stored in site time). */
function acp_ago($mysql) {
    $ts = strtotime((string) $mysql);
    if (!$ts) {
        return '';
    }
    $now = strtotime(current_time('mysql'));
    return sprintf(acp_t('%s پیش', '%s ago', 'vor %s'), human_time_diff($ts, $now));
}

// -------------------------------------------------------------------------------------------------
// Filters + query
// -------------------------------------------------------------------------------------------------

/** Current list filters from the URL (validated). */
function acp_crm_filters() {
    $statuses = array_keys(acp_statuses());
    $g = function ($k, $d = '') {
        return isset($_GET[$k]) && is_string($_GET[$k]) ? sanitize_text_field(wp_unslash($_GET[$k])) : $d;
    };
    $status = $g('status', 'all');
    $per_page = (int) $g('per_page', 20);
    $orderby = $g('orderby', 'created_at');
    $date_ok = function ($d) {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : '';
    };
    return [
        'status'   => in_array($status, array_merge(['all', 'open', 'due'], $statuses), true) ? $status : 'all',
        'form'     => sanitize_key($g('form', '')),
        'q'        => mb_substr($g('q', ''), 0, 100),
        'from'     => $date_ok($g('from', '')),
        'to'       => $date_ok($g('to', '')),
        'orderby'  => in_array($orderby, ['created_at', 'name', 'status', 'follow_up_at', 'id'], true) ? $orderby : 'created_at',
        'order'    => 'asc' === strtolower($g('order', 'desc')) ? 'ASC' : 'DESC',
        'per_page' => in_array($per_page, [20, 50, 100], true) ? $per_page : 20,
        'paged'    => max(1, (int) $g('paged', 1)),
    ];
}

/** WHERE clause + params for the filters. */
function acp_crm_where($f) {
    global $wpdb;
    $where = ['1=1'];
    $params = [];
    if ('all' === $f['status']) {
        $where[] = "status <> 'spam'";
    } elseif ('open' === $f['status']) {
        $where[] = "status IN ('pending','call_later')";
    } elseif ('due' === $f['status']) {
        $where[] = "status = 'call_later' AND follow_up_at IS NOT NULL AND follow_up_at <= %s";
        $params[] = date('Y-m-d 23:59:59', strtotime(current_time('mysql')));
    } else {
        $where[] = 'status = %s';
        $params[] = $f['status'];
    }
    if ('' !== $f['form']) {
        $where[] = 'form_id = %s';
        $params[] = $f['form'];
    }
    if ('' !== $f['q']) {
        $like = '%' . $wpdb->esc_like($f['q']) . '%';
        $where[] = '(name LIKE %s OR email LIKE %s OR phone LIKE %s OR message LIKE %s OR department LIKE %s OR admin_note LIKE %s OR id = %d)';
        array_push($params, $like, $like, $like, $like, $like, $like, (int) $f['q']);
    }
    if ('' !== $f['from']) {
        $where[] = 'created_at >= %s';
        $params[] = $f['from'] . ' 00:00:00';
    }
    if ('' !== $f['to']) {
        $where[] = 'created_at <= %s';
        $params[] = $f['to'] . ' 23:59:59';
    }
    return [implode(' AND ', $where), $params];
}

function acp_crm_prepare($sql, $params) {
    global $wpdb;
    return $params ? $wpdb->prepare($sql, $params) : $sql;
}

function acp_crm_query($f, $limit = true) {
    global $wpdb;
    $table = $wpdb->prefix . 'acp_requests';
    list($where, $params) = acp_crm_where($f);
    $total = (int) $wpdb->get_var(acp_crm_prepare("SELECT COUNT(*) FROM $table WHERE $where", $params));
    $order = $f['orderby'] . ' ' . $f['order'] . ('id' === $f['orderby'] ? '' : ', id DESC');
    if ('follow_up_at' === $f['orderby']) {
        $order = 'follow_up_at IS NULL, ' . $order;
    }
    $sql = "SELECT * FROM $table WHERE $where ORDER BY $order";
    if ($limit) {
        $sql .= $wpdb->prepare(' LIMIT %d OFFSET %d', $f['per_page'], ($f['paged'] - 1) * $f['per_page']);
    }
    return [$wpdb->get_results(acp_crm_prepare($sql, $params)), $total];
}

function acp_crm_counts() {
    global $wpdb;
    $table = $wpdb->prefix . 'acp_requests';
    $counts = array_fill_keys(array_keys(acp_statuses()), 0);
    foreach ((array) $wpdb->get_results("SELECT status, COUNT(*) n FROM $table GROUP BY status") as $r) {
        $counts[$r->status] = (int) $r->n;
    }
    $today_end = date('Y-m-d 23:59:59', strtotime(current_time('mysql')));
    $now = current_time('mysql');
    $counts['due'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE status = 'call_later' AND follow_up_at IS NOT NULL AND follow_up_at <= %s", $today_end));
    $counts['overdue'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE status = 'call_later' AND follow_up_at IS NOT NULL AND follow_up_at < %s", $now));
    $counts['month'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE status <> 'spam' AND created_at >= %s", date('Y-m-01 00:00:00', strtotime($now))));
    $counts['all'] = array_sum(array_intersect_key($counts, array_flip(['pending', 'call_later', 'called', 'done'])));
    $counts['open'] = $counts['pending'] + $counts['call_later'];
    return $counts;
}

// -------------------------------------------------------------------------------------------------
// Actions (POST -> redirect)
// -------------------------------------------------------------------------------------------------

/** Adds a history entry. */
function acp_add_note($request_id, $note, $from = '', $to = '') {
    global $wpdb;
    $wpdb->insert($wpdb->prefix . 'acp_request_notes', [
        'request_id' => (int) $request_id,
        'user_id' => get_current_user_id(),
        'note' => (string) $note,
        'status_from' => (string) $from,
        'status_to' => (string) $to,
        'created_at' => current_time('mysql'),
    ], ['%d', '%d', '%s', '%s', '%s', '%s']);
}

/** Changes the status (with history entry); returns true when the row changed. */
function acp_set_status($id, $status, $note = '', $follow_up = null) {
    global $wpdb;
    $table = $wpdb->prefix . 'acp_requests';
    $row = $wpdb->get_row($wpdb->prepare("SELECT id, status, follow_up_at FROM $table WHERE id = %d", $id));
    if (!$row || !isset(acp_statuses()[$status])) {
        return false;
    }
    $data = ['status' => $status, 'updated_at' => current_time('mysql')];
    if ('' !== $note) {
        $data['admin_note'] = $note;
    }
    if (null !== $follow_up) {
        $data['follow_up_at'] = $follow_up ?: null;
    }
    if ('call_later' !== $status && null === $follow_up) {
        $data['follow_up_at'] = null;
    }
    $wpdb->update($table, $data, ['id' => (int) $id]);
    $changed_status = $row->status !== $status;
    $changed_follow = array_key_exists('follow_up_at', $data) && (string) $row->follow_up_at !== (string) $data['follow_up_at'] && !empty($data['follow_up_at']);
    if ($changed_status || '' !== $note || $changed_follow) {
        $text = $note;
        if ($changed_follow) {
            $text = trim($text . "\n" . sprintf(acp_t('پیگیری: %s', 'Follow-up: %s', 'Wiedervorlage: %s'), acp_format_dt($data['follow_up_at'])));
        }
        acp_add_note($id, $text, $changed_status ? $row->status : '', $changed_status ? $status : '');
    }
    return true;
}

/** Deletes requests, their notes and their uploaded files (only files inside the uploads folder). */
function acp_delete_requests(array $ids) {
    global $wpdb;
    $table = $wpdb->prefix . 'acp_requests';
    $uploads = wp_get_upload_dir();
    $n = 0;
    foreach (array_map('intval', $ids) as $id) {
        $att = $wpdb->get_var($wpdb->prepare("SELECT attachment FROM $table WHERE id = %d", $id));
        if ($att && 0 === strpos($att, $uploads['baseurl'] . '/')) {
            $file = realpath($uploads['basedir'] . substr($att, strlen($uploads['baseurl'])));
            $base = realpath($uploads['basedir']);
            if ($file && $base && 0 === strpos($file, $base . DIRECTORY_SEPARATOR) && is_file($file)) {
                wp_delete_file($file);
            }
        }
        $n += (int) $wpdb->delete($table, ['id' => $id], ['%d']);
        $wpdb->delete($wpdb->prefix . 'acp_request_notes', ['request_id' => $id], ['%d']);
    }
    return $n;
}

/** Parses "YYYY-MM-DDTHH:MM" from a datetime-local input; '' when empty, false when invalid. */
function acp_parse_follow_up($value) {
    $value = trim((string) $value);
    if ('' === $value) {
        return '';
    }
    $d = DateTime::createFromFormat('!Y-m-d\TH:i', $value);
    return $d && $d->format('Y-m-d\TH:i') === $value ? $d->format('Y-m-d H:i:00') : false;
}

add_action('admin_init', function () {
    if (empty($_POST['acp_crm_do']) || !isset($_GET['page']) || 'acp-requests' !== $_GET['page']) {
        return;
    }
    if (!current_user_can('manage_options')) {
        wp_die(esc_html(acp_t('دسترسی ندارید.', 'Not allowed.', 'Nicht erlaubt.')));
    }
    check_admin_referer('acp_crm_action', 'acp_crm_nonce');

    $do = sanitize_key(wp_unslash($_POST['acp_crm_do']));
    $back = isset($_POST['acp_back']) ? esc_url_raw(wp_unslash($_POST['acp_back'])) : acp_crm_url();
    if (0 !== strpos($back, admin_url())) {
        $back = acp_crm_url();
    }
    $msg = '';

    if ('update' === $do) {
        $id = (int) ($_POST['req_id'] ?? 0);
        $status = sanitize_key(wp_unslash($_POST['status'] ?? ''));
        $note = sanitize_textarea_field(wp_unslash($_POST['note'] ?? ''));
        $follow = acp_parse_follow_up(wp_unslash($_POST['follow_up_at'] ?? ''));
        if (false === $follow) {
            $msg = 'bad_date';
        } else {
            $msg = acp_set_status($id, $status, $note, $follow) ? 'saved' : 'error';
        }
    } elseif ('quick' === $do) {
        $id = (int) ($_POST['req_id'] ?? 0);
        $msg = acp_set_status($id, sanitize_key(wp_unslash($_POST['status'] ?? ''))) ? 'saved' : 'error';
    } elseif ('bulk' === $do) {
        $ids = array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])));
        $action = sanitize_key(wp_unslash($_POST['bulk_action'] ?? ''));
        if (!$ids || '' === $action) {
            $msg = 'nothing';
        } elseif ('delete' === $action) {
            $msg = 'deleted:' . acp_delete_requests($ids);
        } elseif (isset(acp_statuses()[$action])) {
            $n = 0;
            foreach ($ids as $id) {
                $n += (int) acp_set_status($id, $action);
            }
            $msg = 'bulk:' . $n;
        }
    } elseif ('delete' === $do) {
        $msg = 'deleted:' . acp_delete_requests([(int) ($_POST['req_id'] ?? 0)]);
        $back = remove_query_arg('view', $back);
    }

    wp_safe_redirect(add_query_arg('acp_msg', rawurlencode($msg), $back));
    exit;
});

/** CSV export of the current filter (all pages). */
add_action('admin_post_acp_export_csv', function () {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html(acp_t('دسترسی ندارید.', 'Not allowed.', 'Nicht erlaubt.')));
    }
    check_admin_referer('acp_export_csv');
    list($rows) = acp_crm_query(acp_crm_filters(), false);
    $forms = get_option('acp_forms', []);
    $statuses = acp_statuses();
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="consultation-requests-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
    // Values starting with = + - @ are prefixed so spreadsheet apps do not run them as formulas.
    $cell = function ($v) {
        $v = (string) $v;
        return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
    };
    fputcsv($out, ['ID', acp_t('فرم', 'Form', 'Formular'), acp_t('نام', 'Name', 'Name'), 'E-Mail', acp_t('تلفن', 'Phone', 'Telefon'),
        acp_t('تاریخ درخواستی', 'Requested date', 'Wunschdatum'), acp_t('بخش', 'Department', 'Abteilung'), acp_t('پیام', 'Message', 'Nachricht'),
        acp_t('وضعیت', 'Status', 'Status'), acp_t('پیگیری', 'Follow-up', 'Wiedervorlage'), acp_t('یادداشت', 'Note', 'Notiz'),
        acp_t('صفحه', 'Page', 'Seite'), acp_t('تاریخ ثبت', 'Submitted', 'Eingegangen')], ';');
    foreach ($rows as $r) {
        fputcsv($out, array_map($cell, [
            $r->id, isset($forms[$r->form_id]['form_name']) ? $forms[$r->form_id]['form_name'] : $r->form_id, $r->name, $r->email, $r->phone,
            $r->req_date, $r->department, $r->message, isset($statuses[$r->status]) ? $statuses[$r->status]['label'] : $r->status,
            $r->follow_up_at, $r->admin_note, isset($r->page_url) ? $r->page_url : '', $r->created_at,
        ]), ';');
    }
    fclose($out);
    exit;
});

// -------------------------------------------------------------------------------------------------
// Page
// -------------------------------------------------------------------------------------------------

function acp_render_crm_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    $view = isset($_GET['view']) ? (int) $_GET['view'] : 0;
    echo '<div class="wrap acp-crm">';
    acp_crm_notice();
    if ($view) {
        acp_render_request_detail($view);
    } else {
        acp_render_request_list();
    }
    echo '</div>';
    acp_admin_footer();
}

function acp_crm_notice() {
    if (empty($_GET['acp_msg'])) {
        return;
    }
    $msg = sanitize_text_field(wp_unslash($_GET['acp_msg']));
    list($code, $n) = array_pad(explode(':', $msg, 2), 2, '');
    $texts = [
        'saved'   => ['success', acp_t('ذخیره شد.', 'Saved.', 'Gespeichert.')],
        'deleted' => ['success', sprintf(acp_t('%d درخواست حذف شد.', '%d request(s) deleted.', '%d Anfrage(n) gelöscht.'), (int) $n)],
        'bulk'    => ['success', sprintf(acp_t('وضعیت %d درخواست تغییر کرد.', 'Status of %d request(s) changed.', 'Status von %d Anfrage(n) geändert.'), (int) $n)],
        'nothing' => ['warning', acp_t('هیچ درخواست یا عملیاتی انتخاب نشده است.', 'No request or action selected.', 'Keine Anfrage oder Aktion ausgewählt.')],
        'bad_date'=> ['error', acp_t('تاریخ پیگیری نامعتبر است.', 'Invalid follow-up date.', 'Ungültiges Wiedervorlage-Datum.')],
        'error'   => ['error', acp_t('ذخیره نشد.', 'Could not be saved.', 'Konnte nicht gespeichert werden.')],
    ];
    if (isset($texts[$code])) {
        printf('<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr($texts[$code][0]), esc_html($texts[$code][1]));
    }
}

function acp_render_request_list() {
    $f = acp_crm_filters();
    list($rows, $total) = acp_crm_query($f);
    $counts = acp_crm_counts();
    $statuses = acp_statuses();
    $forms = get_option('acp_forms', []);
    $pages = max(1, (int) ceil($total / $f['per_page']));
    $base = array_filter(array_diff_key($f, ['paged' => 1]), function ($v) {
        return '' !== $v;
    });
    $current_url = acp_crm_url(array_merge($base, ['paged' => $f['paged']]));
    $export_url = wp_nonce_url(add_query_arg(array_merge(['action' => 'acp_export_csv'], $base), admin_url('admin-post.php')), 'acp_export_csv');
    $sort_link = function ($col, $label) use ($f, $base) {
        $order = ($f['orderby'] === $col && 'DESC' === $f['order']) ? 'asc' : 'desc';
        $arrow = $f['orderby'] === $col ? ('DESC' === $f['order'] ? ' ↓' : ' ↑') : '';
        return '<a href="' . esc_url(acp_crm_url(array_merge($base, ['orderby' => $col, 'order' => $order]))) . '">' . esc_html($label) . $arrow . '</a>';
    };
    ?>
    <div class="acp-head">
        <h1><span class="dashicons dashicons-clipboard"></span><?php echo acp_te('درخواست‌های مشاوره', 'Consultation requests', 'Beratungsanfragen'); ?></h1>
        <a class="button" href="<?php echo esc_url($export_url); ?>"><span class="dashicons dashicons-download"></span><?php echo acp_te('خروجی CSV', 'Export CSV', 'CSV exportieren'); ?></a>
    </div>

    <div class="acp-kpis">
        <?php
        $kpis = [
            ['pending', $counts['pending'], acp_t('جدید', 'New', 'Neu'), 'dashicons-star-filled', '#b45309'],
            ['due', $counts['due'], acp_t('پیگیری تا امروز', 'Follow-ups due', 'Wiedervorlagen fällig'), 'dashicons-bell', $counts['overdue'] ? '#b91c1c' : '#1d4ed8'],
            ['call_later', $counts['call_later'], acp_t('تماس مجدد', 'Call back', 'Rückruf'), 'dashicons-phone', '#1d4ed8'],
            ['done', $counts['done'], acp_t('انجام شد', 'Done', 'Erledigt'), 'dashicons-yes-alt', '#15803d'],
            ['month', $counts['month'], acp_t('این ماه', 'This month', 'Diesen Monat'), 'dashicons-calendar-alt', '#4338ca'],
        ];
        $month_start = date('Y-m-01', strtotime(current_time('mysql')));
        foreach ($kpis as $k):
            $is_month = 'month' === $k[0];
            $url = $is_month ? acp_crm_url(['status' => 'all', 'from' => $month_start]) : acp_crm_url(['status' => $k[0]]);
            $active = $is_month ? ('all' === $f['status'] && $f['from'] === $month_start && '' === $f['to']) : ($f['status'] === $k[0] && '' === $f['from']); ?>
            <a class="acp-kpi<?php echo $active ? ' is-active' : ''; ?>" href="<?php echo esc_url($url); ?>" style="--acp-kpi:<?php echo esc_attr($k[4]); ?>">
                <span class="dashicons <?php echo esc_attr($k[3]); ?>"></span>
                <span class="acp-kpi-num"><?php echo (int) $k[1]; ?></span>
                <span class="acp-kpi-label"><?php echo esc_html($k[2]); ?><?php if ('due' === $k[0] && $counts['overdue']) echo ' · ' . sprintf(acp_te('%d عقب‌افتاده', '%d overdue', '%d überfällig'), (int) $counts['overdue']); ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <ul class="acp-tabs">
        <?php
        $tabs = ['all' => acp_t('همه', 'All', 'Alle'), 'open' => acp_t('باز', 'Open', 'Offen')];
        foreach ($statuses as $key => $s) {
            $tabs[$key] = $s['label'];
        }
        foreach ($tabs as $key => $label): ?>
            <li><a class="<?php echo $f['status'] === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url(acp_crm_url(array_merge($base, ['status' => $key, 'paged' => 1]))); ?>"><?php echo esc_html($label); ?> <span class="acp-count"><?php echo (int) (isset($counts[$key]) ? $counts[$key] : 0); ?></span></a></li>
        <?php endforeach; ?>
    </ul>

    <form class="acp-filters" method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
        <input type="hidden" name="page" value="acp-requests">
        <input type="hidden" name="status" value="<?php echo esc_attr($f['status']); ?>">
        <input type="search" name="q" value="<?php echo esc_attr($f['q']); ?>" placeholder="<?php echo esc_attr(acp_t('جستجو: نام، ایمیل، تلفن، پیام…', 'Search: name, e-mail, phone, message…', 'Suche: Name, E-Mail, Telefon, Nachricht…')); ?>">
        <?php if (count($forms) > 1): ?>
            <select name="form">
                <option value=""><?php echo acp_te('همه فرم‌ها', 'All forms', 'Alle Formulare'); ?></option>
                <?php foreach ($forms as $id => $form): ?>
                    <option value="<?php echo esc_attr($id); ?>" <?php selected($f['form'], $id); ?>><?php echo esc_html($form['form_name']); ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <label class="acp-date"><?php echo acp_te('از', 'From', 'Von'); ?> <input type="date" name="from" value="<?php echo esc_attr($f['from']); ?>"></label>
        <label class="acp-date"><?php echo acp_te('تا', 'To', 'Bis'); ?> <input type="date" name="to" value="<?php echo esc_attr($f['to']); ?>"></label>
        <select name="per_page" data-acp-autosubmit>
            <?php foreach ([20, 50, 100] as $n): ?>
                <option value="<?php echo (int) $n; ?>" <?php selected($f['per_page'], $n); ?>><?php echo (int) $n; ?> <?php echo acp_te('در صفحه', 'per page', 'pro Seite'); ?></option>
            <?php endforeach; ?>
        </select>
        <button class="button button-primary"><span class="dashicons dashicons-search"></span><?php echo acp_te('فیلتر', 'Filter', 'Filtern'); ?></button>
        <?php if ($f['q'] || $f['form'] || $f['from'] || $f['to']): ?>
            <a class="button-link" href="<?php echo esc_url(acp_crm_url(['status' => $f['status']])); ?>"><?php echo acp_te('حذف فیلترها', 'Reset', 'Zurücksetzen'); ?></a>
        <?php endif; ?>
    </form>

    <form method="post" id="acp-bulk-form" action="<?php echo esc_url($current_url); ?>">
        <?php wp_nonce_field('acp_crm_action', 'acp_crm_nonce'); ?>
        <input type="hidden" name="acp_crm_do" value="bulk">
        <input type="hidden" name="acp_back" value="<?php echo esc_url($current_url); ?>">
    </form>
    <form method="post" id="acp-quick-form" action="<?php echo esc_url($current_url); ?>">
        <?php wp_nonce_field('acp_crm_action', 'acp_crm_nonce'); ?>
        <input type="hidden" name="acp_crm_do" value="quick">
        <input type="hidden" name="acp_back" value="<?php echo esc_url($current_url); ?>">
        <input type="hidden" name="req_id" value="">
        <input type="hidden" name="status" value="">
    </form>

    <div class="acp-card">
        <?php if (!$rows): ?>
            <div class="acp-empty">
                <span class="dashicons dashicons-clipboard"></span>
                <p><?php echo acp_te('درخواستی با این فیلتر وجود ندارد.', 'No requests match this filter.', 'Keine Anfragen für diesen Filter.'); ?></p>
            </div>
        <?php else: ?>
        <table class="acp-table">
            <thead><tr>
                <th class="acp-col-check"><input type="checkbox" data-acp-check-all aria-label="<?php echo esc_attr(acp_t('انتخاب همه', 'Select all', 'Alle auswählen')); ?>"></th>
                <th><?php echo $sort_link('name', acp_t('مخاطب', 'Contact', 'Kontakt')); ?></th>
                <th><?php echo acp_te('درخواست', 'Request', 'Anfrage'); ?></th>
                <th><?php echo $sort_link('status', acp_t('وضعیت', 'Status', 'Status')); ?></th>
                <th><?php echo $sort_link('follow_up_at', acp_t('پیگیری', 'Follow-up', 'Wiedervorlage')); ?></th>
                <th><?php echo $sort_link('created_at', acp_t('دریافت', 'Received', 'Eingang')); ?></th>
                <th></th>
            </tr></thead>
            <tbody>
            <?php
            $now = current_time('mysql');
            foreach ($rows as $r):
                $detail = acp_crm_url(['view' => $r->id, 'back' => rawurlencode($current_url)]);
                $overdue = 'call_later' === $r->status && $r->follow_up_at && $r->follow_up_at < $now;
                $form_name = isset($forms[$r->form_id]['form_name']) ? $forms[$r->form_id]['form_name'] : $r->form_id;
            ?>
                <tr class="acp-row acp-row-<?php echo esc_attr($r->status); ?>">
                    <td class="acp-col-check"><input type="checkbox" name="ids[]" value="<?php echo (int) $r->id; ?>" form="acp-bulk-form" data-acp-check></td>
                    <td>
                        <a class="acp-name" href="<?php echo esc_url($detail); ?>"><?php echo esc_html($r->name ?: '#' . $r->id); ?></a>
                        <div class="acp-contact">
                            <?php if ($r->phone): ?><a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $r->phone)); ?>"><span class="dashicons dashicons-phone"></span><?php echo esc_html($r->phone); ?></a><?php endif; ?>
                            <?php if ($r->email): ?><a href="mailto:<?php echo esc_attr($r->email); ?>"><span class="dashicons dashicons-email"></span><?php echo esc_html($r->email); ?></a><?php endif; ?>
                        </div>
                    </td>
                    <td class="acp-col-req">
                        <div class="acp-meta">
                            <span>#<?php echo (int) $r->id; ?></span>
                            <?php if (count($forms) > 1): ?><span><?php echo esc_html($form_name); ?></span><?php endif; ?>
                            <?php if ($r->req_date): ?><span><span class="dashicons dashicons-calendar"></span><?php echo esc_html($r->req_date); ?></span><?php endif; ?>
                            <?php if ($r->attachment): ?><span><span class="dashicons dashicons-paperclip"></span></span><?php endif; ?>
                        </div>
                        <?php if ($r->department): ?><strong><?php echo esc_html($r->department); ?></strong><?php endif; ?>
                        <?php if ($r->message): ?><div class="acp-excerpt"><?php echo esc_html(wp_trim_words($r->message, 16)); ?></div><?php endif; ?>
                    </td>
                    <td><?php echo acp_status_badge($r->status); ?></td>
                    <td class="<?php echo $overdue ? 'acp-overdue' : ''; ?>"><?php echo $r->follow_up_at && 'call_later' === $r->status ? esc_html(acp_format_dt($r->follow_up_at)) : '<span class="acp-muted">—</span>'; ?></td>
                    <td><span title="<?php echo esc_attr(acp_format_dt($r->created_at)); ?>"><?php echo esc_html(acp_ago($r->created_at)); ?></span></td>
                    <td class="acp-actions">
                        <?php if ('pending' === $r->status || 'call_later' === $r->status): ?>
                            <button type="button" class="button button-small" data-acp-quick="<?php echo (int) $r->id; ?>" data-status="called"><span class="dashicons dashicons-yes"></span><?php echo acp_te('تماس گرفتم', 'Contacted', 'Kontaktiert'); ?></button>
                        <?php endif; ?>
                        <a class="button button-small button-primary" href="<?php echo esc_url($detail); ?>"><?php echo acp_te('باز کردن', 'Open', 'Öffnen'); ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <div class="acp-list-foot">
        <?php if ($rows): ?>
        <div class="acp-bulk">
            <select name="bulk_action" form="acp-bulk-form">
                <option value=""><?php echo acp_te('عملیات گروهی', 'Bulk action', 'Sammelaktion'); ?></option>
                <?php foreach ($statuses as $key => $s): ?>
                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html(sprintf(acp_t('وضعیت: %s', 'Status: %s', 'Status: %s'), $s['label'])); ?></option>
                <?php endforeach; ?>
                <option value="delete"><?php echo acp_te('حذف (همراه فایل پیوست)', 'Delete (incl. attachment)', 'Löschen (inkl. Anhang)'); ?></option>
            </select>
            <button type="submit" form="acp-bulk-form" class="button" data-acp-confirm="<?php echo esc_attr(acp_t('عملیات روی درخواست‌های انتخاب‌شده انجام شود؟', 'Apply to the selected requests?', 'Auf die ausgewählten Anfragen anwenden?')); ?>"><?php echo acp_te('اجرا', 'Apply', 'Anwenden'); ?></button>
        </div>
        <?php endif; ?>
        <div class="acp-pages">
            <span><?php echo esc_html(sprintf(acp_t('%d مورد', '%d items', '%d Einträge'), $total)); ?></span>
            <?php if ($pages > 1): ?>
                <?php for ($p = max(1, $f['paged'] - 2); $p <= min($pages, $f['paged'] + 2); $p++): ?>
                    <a class="button<?php echo $p === $f['paged'] ? ' button-primary' : ''; ?>" href="<?php echo esc_url(acp_crm_url(array_merge($base, ['paged' => $p]))); ?>"><?php echo (int) $p; ?></a>
                <?php endfor; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if (count($forms) > 1): ?>
    <div class="acp-card acp-forms-stats">
        <h3><?php echo acp_te('درخواست‌ها به تفکیک فرم', 'Requests per form', 'Anfragen pro Formular'); ?></h3>
        <?php
        global $wpdb;
        $per_form = $wpdb->get_results("SELECT form_id, COUNT(*) n FROM {$wpdb->prefix}acp_requests WHERE status <> 'spam' GROUP BY form_id", OBJECT_K);
        foreach ($forms as $id => $form): ?>
            <a href="<?php echo esc_url(acp_crm_url(['form' => $id])); ?>"><?php echo esc_html($form['form_name']); ?> <strong><?php echo (int) (isset($per_form[$id]) ? $per_form[$id]->n : 0); ?></strong></a>
        <?php endforeach; ?>
    </div>
    <?php endif;
}

function acp_render_request_detail($id) {
    global $wpdb;
    $r = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}acp_requests WHERE id = %d", $id));
    $back = isset($_GET['back']) ? esc_url_raw(rawurldecode(wp_unslash($_GET['back']))) : acp_crm_url();
    if (0 !== strpos($back, admin_url())) {
        $back = acp_crm_url();
    }
    echo '<p><a class="acp-back" href="' . esc_url($back) . '">&larr; ' . acp_te('بازگشت به لیست', 'Back to the list', 'Zurück zur Liste') . '</a></p>';
    if (!$r) {
        echo '<div class="notice notice-error"><p>' . acp_te('درخواست پیدا نشد.', 'Request not found.', 'Anfrage nicht gefunden.') . '</p></div>';
        return;
    }
    $forms = get_option('acp_forms', []);
    $notes = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}acp_request_notes WHERE request_id = %d ORDER BY created_at DESC, id DESC", $id));
    $statuses = acp_statuses();
    $self = acp_crm_url(['view' => $id, 'back' => rawurlencode($back)]);
    $phone_digits = preg_replace('/[^0-9+]/', '', (string) $r->phone);
    $wa = '';
    if (preg_match('/^(\+|00)([1-9][0-9]{6,14})$/', $phone_digits, $m)) {
        $wa = 'https://wa.me/' . $m[2];
    }
    $follow_value = $r->follow_up_at ? date('Y-m-d\TH:i', strtotime($r->follow_up_at)) : '';
    ?>
    <div class="acp-head">
        <h1><?php echo esc_html($r->name ?: '#' . $r->id); ?> <span class="acp-id">#<?php echo (int) $r->id; ?></span> <?php echo acp_status_badge($r->status); ?></h1>
        <div class="acp-head-actions">
            <?php if ($r->phone): ?><a class="button" href="tel:<?php echo esc_attr($phone_digits); ?>"><span class="dashicons dashicons-phone"></span><?php echo acp_te('تماس', 'Call', 'Anrufen'); ?></a><?php endif; ?>
            <?php if ($wa): ?><a class="button" href="<?php echo esc_url($wa); ?>" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-whatsapp"></span>WhatsApp</a><?php endif; ?>
            <?php if ($r->email): ?><a class="button" href="mailto:<?php echo esc_attr($r->email); ?>"><span class="dashicons dashicons-email"></span><?php echo acp_te('ایمیل', 'E-mail', 'E-Mail'); ?></a><?php endif; ?>
        </div>
    </div>

    <div class="acp-detail">
        <div class="acp-card">
            <h2><?php echo acp_te('اطلاعات درخواست', 'Request', 'Anfrage'); ?></h2>
            <dl class="acp-dl">
                <dt><?php echo acp_te('نام', 'Name', 'Name'); ?></dt><dd><?php echo esc_html($r->name ?: '—'); ?></dd>
                <dt><?php echo acp_te('تلفن', 'Phone', 'Telefon'); ?></dt><dd><?php echo $r->phone ? '<a href="tel:' . esc_attr($phone_digits) . '">' . esc_html($r->phone) . '</a>' : '—'; ?></dd>
                <dt>E-Mail</dt><dd><?php echo $r->email ? '<a href="mailto:' . esc_attr($r->email) . '">' . esc_html($r->email) . '</a>' : '—'; ?></dd>
                <dt><?php echo acp_te('تاریخ درخواستی', 'Requested date', 'Wunschdatum'); ?></dt><dd><?php echo esc_html($r->req_date ?: '—'); ?></dd>
                <?php if ($r->department): ?><dt><?php echo acp_te('بخش / موضوع', 'Department / subject', 'Abteilung / Thema'); ?></dt><dd><?php echo esc_html($r->department); ?></dd><?php endif; ?>
                <dt><?php echo acp_te('فرم', 'Form', 'Formular'); ?></dt><dd><?php echo esc_html(isset($forms[$r->form_id]['form_name']) ? $forms[$r->form_id]['form_name'] : $r->form_id); ?></dd>
                <?php if (!empty($r->page_url)): ?><dt><?php echo acp_te('صفحه', 'Page', 'Seite'); ?></dt><dd><a href="<?php echo esc_url($r->page_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(wp_parse_url($r->page_url, PHP_URL_PATH) ?: $r->page_url); ?></a></dd><?php endif; ?>
                <dt><?php echo acp_te('دریافت', 'Received', 'Eingang'); ?></dt><dd><?php echo esc_html(acp_format_dt($r->created_at)); ?> <span class="acp-muted">(<?php echo esc_html(acp_ago($r->created_at)); ?>)</span></dd>
                <?php if ($r->attachment): ?><dt><?php echo acp_te('فایل پیوست', 'Attachment', 'Anhang'); ?></dt><dd><a class="button button-small" href="<?php echo esc_url($r->attachment); ?>" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-paperclip"></span><?php echo esc_html(wp_basename(wp_parse_url($r->attachment, PHP_URL_PATH))); ?></a></dd><?php endif; ?>
            </dl>
            <?php if ($r->message): ?>
                <h3><?php echo acp_te('پیام', 'Message', 'Nachricht'); ?></h3>
                <div class="acp-message"><?php echo nl2br(esc_html($r->message)); ?></div>
            <?php endif; ?>
        </div>

        <div class="acp-side">
            <div class="acp-card">
                <h2><?php echo acp_te('وضعیت و پیگیری', 'Status and follow-up', 'Status und Wiedervorlage'); ?></h2>
                <form method="post" action="<?php echo esc_url($self); ?>" class="acp-update">
                    <?php wp_nonce_field('acp_crm_action', 'acp_crm_nonce'); ?>
                    <input type="hidden" name="acp_crm_do" value="update">
                    <input type="hidden" name="req_id" value="<?php echo (int) $r->id; ?>">
                    <input type="hidden" name="acp_back" value="<?php echo esc_url($self); ?>">
                    <div class="acp-status-pick">
                        <?php foreach ($statuses as $key => $s): ?>
                            <label style="--acp-c:<?php echo esc_attr($s['color']); ?>;--acp-bg:<?php echo esc_attr($s['bg']); ?>">
                                <input type="radio" name="status" value="<?php echo esc_attr($key); ?>" <?php checked($r->status, $key); ?>>
                                <span><span class="dashicons <?php echo esc_attr($s['icon']); ?>"></span><?php echo esc_html($s['label']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="acp-follow" data-acp-follow>
                        <label for="acp-follow"><?php echo acp_te('تاریخ پیگیری (تماس مجدد)', 'Follow-up (call back)', 'Wiedervorlage (Rückruf)'); ?></label>
                        <input type="datetime-local" id="acp-follow" name="follow_up_at" value="<?php echo esc_attr($follow_value); ?>">
                        <span class="acp-quick-dates">
                            <button type="button" class="button button-small" data-acp-in="2h">+2 h</button>
                            <button type="button" class="button button-small" data-acp-in="tomorrow"><?php echo acp_te('فردا ۹:۰۰', 'Tomorrow 9:00', 'Morgen 9:00'); ?></button>
                            <button type="button" class="button button-small" data-acp-in="week"><?php echo acp_te('یک هفته', 'In a week', 'In einer Woche'); ?></button>
                        </span>
                    </p>
                    <p>
                        <label for="acp-note"><?php echo acp_te('یادداشت جدید', 'New note', 'Neue Notiz'); ?></label>
                        <textarea id="acp-note" name="note" rows="3" placeholder="<?php echo esc_attr(acp_t('مثلاً: تماس گرفتم، فردا دوباره زنگ می‌زنم', 'e.g. called, will call again tomorrow', 'z. B. angerufen, rufe morgen nochmal an')); ?>"></textarea>
                    </p>
                    <button class="button button-primary button-large"><?php echo acp_te('ذخیره', 'Save', 'Speichern'); ?></button>
                </form>
            </div>

            <div class="acp-card">
                <h2><?php echo acp_te('تاریخچه', 'History', 'Verlauf'); ?></h2>
                <ul class="acp-timeline">
                    <?php foreach ($notes as $n):
                        $user = $n->user_id ? get_userdata($n->user_id) : null; ?>
                        <li>
                            <div class="acp-tl-head">
                                <strong><?php echo esc_html($user ? $user->display_name : acp_t('سیستم', 'System', 'System')); ?></strong>
                                <span class="acp-muted"><?php echo esc_html(acp_format_dt($n->created_at)); ?></span>
                            </div>
                            <?php if ($n->status_to): ?>
                                <div class="acp-tl-status"><?php echo $n->status_from ? acp_status_badge($n->status_from) . ' → ' : ''; ?><?php echo acp_status_badge($n->status_to); ?></div>
                            <?php endif; ?>
                            <?php if ('' !== trim((string) $n->note)): ?><div class="acp-tl-note"><?php echo nl2br(esc_html($n->note)); ?></div><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                    <li>
                        <div class="acp-tl-head"><strong><?php echo acp_te('درخواست دریافت شد', 'Request received', 'Anfrage eingegangen'); ?></strong><span class="acp-muted"><?php echo esc_html(acp_format_dt($r->created_at)); ?></span></div>
                    </li>
                </ul>
            </div>

            <form method="post" action="<?php echo esc_url($self); ?>" class="acp-delete">
                <?php wp_nonce_field('acp_crm_action', 'acp_crm_nonce'); ?>
                <input type="hidden" name="acp_crm_do" value="delete">
                <input type="hidden" name="req_id" value="<?php echo (int) $r->id; ?>">
                <input type="hidden" name="acp_back" value="<?php echo esc_url($back); ?>">
                <button class="button-link acp-danger" data-acp-confirm="<?php echo esc_attr(acp_t('این درخواست و فایل پیوست آن برای همیشه حذف شود؟', 'Delete this request and its attachment permanently?', 'Diese Anfrage und ihren Anhang endgültig löschen?')); ?>"><span class="dashicons dashicons-trash"></span><?php echo acp_te('حذف درخواست', 'Delete request', 'Anfrage löschen'); ?></button>
            </form>
        </div>
    </div>
    <?php
}
