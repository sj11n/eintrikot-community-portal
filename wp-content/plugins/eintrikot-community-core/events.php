<?php
/**
 * Aktuelles: Mitteilungen und Termine in einem Bereich.
 *
 * Termine sind frei anlegbar (Format, Datum, Uhrzeit, Ort, Treffpunkt, Ansprechperson, Beschreibung). Bei Terminen mit
 * Anmeldung melden sich Mitglieder mit einem Knopf an; die Teilnehmerliste sehen Mitglieder im Portal. Mit der Anmeldung
 * willigt das Mitglied ein, mit Namen aufgeführt zu werden. Mitglieder unter 18 und ausgeblendete Konten stehen nie in
 * der Liste, zählen aber mit. Anmeldungen werden 180 Tage nach dem Termin gelöscht (retention.php).
 * Geburtstage stehen getrennt und eingeklappt unter den Terminen (nur, was Mitglieder freigegeben haben).
 */
namespace Eintrikot\Community;
if (!defined('ABSPATH')) {
    exit();
}

const EVENT_HORIZON_DAYS = 366;
const BIRTHDAY_HORIZON_DAYS = 92;
const SIGNUP_KEEP_DAYS = 180;

/** Formats of an event. Free choice instead of fixed series; "Sonstiges" covers everything else. */
function event_formats() {
    return [
        'treffen' => 'Treffen',
        'unterwegs' => 'EINTRIKOT unterwegs',
        'versammlung' => 'Mitgliederversammlung',
        'netzwerk' => 'Netzwerkabend',
        'online' => 'Online',
        'sonstiges' => 'Sonstiges'
    ];
}

function signup_table() {
    global $wpdb;
    return $wpdb->prefix . 'eintrikot_event_signups';
}

function events_install() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta(
        'CREATE TABLE ' .
            signup_table() .
            " (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 event_id bigint(20) unsigned NOT NULL,
 user_id bigint(20) unsigned NOT NULL,
 event_date date NOT NULL,
 created_at datetime NOT NULL,
 PRIMARY KEY  (id),
 UNIQUE KEY event_user (event_id,user_id),
 KEY event_date (event_date)
 ) {$wpdb->get_charset_collate()};"
    );
}
// The table is also created on the first request after an upgrade, without waiting for an administrator.
add_action('init', function () {
    if (get_option('eintrikot_events_schema') !== '1') {
        events_install();
        update_option('eintrikot_events_schema', '1', false);
    }
});

/* ---------- Eingabe im Backend ---------- */

add_action('add_meta_boxes_et_calendar', function () {
    add_meta_box('et-event', 'Veranstaltung', __NAMESPACE__ . '\event_box', 'et_calendar', 'normal', 'high');
    add_meta_box(
        'et-event-signups',
        'Anmeldungen',
        __NAMESPACE__ . '\event_signups_box',
        'et_calendar',
        'side'
    );
});
function event_format_options($selected) {
    $html = '';
    foreach (event_formats() as $key => $label) {
        $html .=
            '<option value="' .
            esc_attr($key) .
            '"' .
            selected($selected, $key, false) .
            '>' .
            esc_html($label) .
            '</option>';
    }
    return $html;
}
function event_field($label, $name, $value, $type = 'text', $extra = '') {
    return '<p class="et-event-field"><label>' .
        esc_html($label) .
        '<br><input type="' .
        esc_attr($type) .
        '" name="' .
        esc_attr($name) .
        '" value="' .
        esc_attr($value) .
        '" class="widefat"' .
        $extra .
        '></label></p>';
}
function event_box($post) {
    wp_nonce_field('et_calendar_' . $post->ID, 'et_calendar_nonce');
    $m = fn($key) => (string) get_post_meta($post->ID, $key, true);
    echo '<div class="et-event-grid">' .
        '<p class="et-event-field"><label>Format<br><select name="et_format" class="widefat">' .
        event_format_options($m('et_format')) .
        '</select></label></p>' .
        event_field('Datum', 'et_date', $m('et_date'), 'date', ' required') .
        event_field('Bis Datum (nur bei mehreren Tagen)', 'et_end_date', $m('et_end_date'), 'date') .
        event_field('Beginn (Uhrzeit)', 'et_time', $m('et_time'), 'time') .
        event_field('Ende (Uhrzeit)', 'et_end_time', $m('et_end_time'), 'time') .
        event_field(
            'Ort',
            'et_place',
            $m('et_place'),
            'text',
            ' maxlength="160" placeholder="z. B. Hockeypark Mönchengladbach"'
        ) .
        event_field('Treffpunkt (optional)', 'et_meetpoint', $m('et_meetpoint'), 'text', ' maxlength="160"') .
        event_field('Ansprechperson (optional)', 'et_contact', $m('et_contact'), 'text', ' maxlength="160"') .
        event_field(
            'Mehr Informationen (Link, optional)',
            'et_link',
            $m('et_link'),
            'url',
            ' placeholder="https://"'
        ) .
        '</div><p><label><input type="checkbox" name="et_signup" value="1" ' .
        checked($m('et_signup'), '1', false) .
        '> Mitglieder können sich im Portal anmelden</label></p>' .
        event_field('Anmeldeschluss (optional)', 'et_deadline', $m('et_deadline'), 'date') .
        '<p><label><input type="checkbox" name="et_cancelled" value="1" ' .
        checked($m('et_cancelled'), '1', false) .
        '> Abgesagt (bleibt sichtbar, Anmeldung ist geschlossen)</label></p><p><label><input type="checkbox" name="et_annual" value="1" ' .
        checked($m('et_annual'), '1', false) .
        '> Jährlich wiederholen (zum Beispiel ein Jahrestag; ohne Anmeldung)</label></p>' .
        '<p class="description">Die Beschreibung schreibst du oben in das große Textfeld. Veröffentlichte Termine erscheinen für Mitglieder unter „Aktuelles → Termine“, bis ein Jahr im Voraus.</p>';
}
function event_signups_box($post) {
    $ids = event_attendee_ids($post->ID);
    if (!$ids) {
        echo '<p>Noch keine Anmeldungen.</p>';
        return;
    }
    echo '<p><strong>' . esc_html((string) count($ids)) . ' angemeldet</strong></p><ol>';
    foreach ($ids as $user_id) {
        $user = get_user_by('id', $user_id);
        echo '<li>' .
            esc_html($user ? $user->display_name : '#' . $user_id) .
            ($user && is_minor_data(profile_data($user_id)) ? ' <em>(unter 18)</em>' : '') .
            '</li>';
    }
    echo '</ol><p class="description">Diese Liste siehst nur du. Mitglieder sehen Erwachsene mit Namen, alle anderen nur als Zahl.</p>';
}
add_action('save_post_et_calendar', function ($id) {
    if (
        wp_is_post_revision($id) ||
        (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) ||
        !current_user_can('eintrikot_edit_infos') ||
        empty($_POST['et_calendar_nonce'])
    ) {
        return;
    }
    $nonce = $_POST['et_calendar_nonce']; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- this is the nonce itself, checked on the next line
    if (
        !is_string($nonce) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($nonce)), 'et_calendar_' . $id)
    ) {
        return;
    }
    $valid_date = function ($value) {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, wp_timezone());
        return $d && $d->format('Y-m-d') === $value;
    };
    $date = post_text('et_date', '', 10);
    if (!$valid_date($date)) {
        delete_post_meta($id, 'et_date');
        return;
    }
    update_post_meta($id, 'et_date', $date);
    $end = post_text('et_end_date', '', 10);
    $end_ok = $end !== '' && $valid_date($end) && $end > $date;
    update_post_meta($id, 'et_end_date', $end_ok ? $end : '');
    foreach (['et_time', 'et_end_time'] as $key) {
        $time = post_text($key, '', 5);
        update_post_meta($id, $key, preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) ? $time : '');
    }
    foreach (['et_place', 'et_meetpoint', 'et_contact'] as $key) {
        update_post_meta($id, $key, mb_substr(sanitize_text_field(post_text($key, '', 400)), 0, 160));
    }
    $link = esc_url_raw(post_text('et_link', '', 300), ['https']);
    update_post_meta($id, 'et_link', $link);
    $format = post_choice('et_format', array_keys(event_formats()), 'sonstiges');
    update_post_meta($id, 'et_format', $format);
    $deadline = post_text('et_deadline', '', 10);
    update_post_meta($id, 'et_deadline', $deadline !== '' && $valid_date($deadline) ? $deadline : '');
    $annual = isset($_POST['et_annual']); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the nonce is verified above
    update_post_meta($id, 'et_annual', $annual ? '1' : '0');
    // A yearly anniversary has no sign-up: the sign-ups belong to one date.
    update_post_meta($id, 'et_signup', isset($_POST['et_signup']) && !$annual ? '1' : '0'); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the nonce is verified above
    update_post_meta($id, 'et_cancelled', isset($_POST['et_cancelled']) ? '1' : '0'); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the nonce is verified above
});

/* ---------- Termine lesen ---------- */

function upcoming_date($source, $annual, $today) {
    try {
        $date = new \DateTimeImmutable($source, $today->getTimezone());
    } catch (\Exception $e) {
        return null;
    }
    if ($annual) {
        $date = $date->setDate((int) $today->format('Y'), (int) $date->format('m'), (int) $date->format('d'));
        if ($date < $today) {
            $date = $date->modify('+1 year');
        }
    }
    return $date;
}

/** One event as a plain array, or null if the post is not a valid, published event. */
function event_from_post($post, $today = null) {
    $today = $today ?: new \DateTimeImmutable('today', wp_timezone());
    $source = get_post_meta($post->ID, 'et_date', true);
    if (!$source || $post->post_status !== 'publish') {
        return null;
    }
    $annual = get_post_meta($post->ID, 'et_annual', true) === '1';
    $start = upcoming_date($source, $annual, $today);
    if (!$start) {
        return null;
    }
    $end = $start;
    $end_source = get_post_meta($post->ID, 'et_end_date', true);
    if (!$annual && $end_source) {
        $parsed = upcoming_date($end_source, false, $today);
        $end = $parsed && $parsed >= $start ? $parsed : $start;
    }
    $m = fn($key) => (string) get_post_meta($post->ID, $key, true);
    $years =
        $annual && (int) $start->format('Y') > (int) substr($source, 0, 4)
            ? (int) $start->format('Y') - (int) substr($source, 0, 4)
            : 0;
    $format = $m('et_format');
    return [
        'id' => (int) $post->ID,
        'title' => ($years ? $years . '. Jahrestag · ' : '') . $post->post_title,
        'date' => $start->format('Y-m-d'),
        'end' => $end->format('Y-m-d'),
        'time' => $m('et_time'),
        'end_time' => $m('et_end_time'),
        'place' => $m('et_place'),
        'meetpoint' => $m('et_meetpoint'),
        'contact' => $m('et_contact'),
        'link' => $m('et_link'),
        'format' => isset(event_formats()[$format]) ? $format : 'sonstiges',
        'content' => $post->post_content,
        'cancelled' => $m('et_cancelled') === '1',
        'signup' => $m('et_signup') === '1' && !$annual,
        'deadline' => $m('et_deadline'),
        'annual' => $annual
    ];
}

/** Events that still run or start within the next days, sorted by date and time. */
function event_items($days = EVENT_HORIZON_DAYS) {
    $today = new \DateTimeImmutable('today', wp_timezone());
    $until = $today->modify('+' . max(1, (int) $days) . ' days')->format('Y-m-d');
    $items = [];
    foreach (
        get_posts(['post_type' => 'et_calendar', 'post_status' => 'publish', 'posts_per_page' => -1])
        as $post
    ) {
        $item = event_from_post($post, $today);
        if ($item && $item['end'] >= $today->format('Y-m-d') && $item['date'] <= $until) {
            $items[] = $item;
        }
    }
    usort($items, fn($a, $b) => strcmp($a['date'] . $a['time'], $b['date'] . $b['time']));
    return $items;
}

function event_by_id($id) {
    $post = get_post((int) $id);
    return $post && $post->post_type === 'et_calendar' ? event_from_post($post) : null;
}

/** Birthdays that members share, for the next days. Only what the member chose, never under 18. */
function birthday_items($days = BIRTHDAY_HORIZON_DAYS) {
    $today = new \DateTimeImmutable('today', wp_timezone());
    $until = $today->modify('+' . max(1, (int) $days) . ' days');
    $items = [];
    foreach (get_users(['capability' => 'eintrikot_portal', 'fields' => 'all']) as $user) {
        $data = profile_data($user->ID);
        // Only what the member shares, and never for accounts that are hidden from the directory.
        $mode = birthday_mode($data);
        if ($mode === '' || !directory_listed($user)) {
            continue;
        }
        $date = upcoming_date($data['birthday'], true, $today);
        if ($date && $date <= $until) {
            $turns = (int) $date->format('Y') - (int) substr($data['birthday'], 0, 4);
            $items[] = [
                'date' => $date->format('Y-m-d'),
                'title' =>
                    'Geburtstag: ' . $user->display_name . ($mode === 'full' ? ' (wird ' . $turns . ')' : '')
            ];
        }
    }
    usort($items, fn($a, $b) => strcmp($a['date'], $b['date']));
    return $items;
}

/* ---------- Anmeldung ---------- */

/** Why this member cannot sign up for the event right now, or '' if they can. */
function event_signup_problem($event, $user_id) {
    $today = wp_date('Y-m-d');
    if (!$event || !$event['signup']) {
        return 'Für diesen Termin gibt es keine Anmeldung.';
    }
    if ($event['cancelled']) {
        return 'Der Termin ist abgesagt.';
    }
    if ($event['end'] < $today) {
        return 'Der Termin liegt in der Vergangenheit.';
    }
    if ($event['deadline'] !== '' && $event['deadline'] < $today) {
        return 'Die Anmeldung ist geschlossen.';
    }
    if (!is_portal_user($user_id) || consent_pending($user_id)) {
        return 'Die Anmeldung ist nur für Mitglieder möglich.';
    }
    return '';
}
function event_attendee_ids($event_id) {
    global $wpdb;
    $ids = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}eintrikot_event_signups WHERE event_id = %d ORDER BY id",
            $event_id
        )
    );
    return array_map('intval', $ids);
}
function event_is_signed($event_id, $user_id) {
    return in_array((int) $user_id, event_attendee_ids($event_id), true);
}
/** Signs the member up. Returns '' on success or a message. */
function event_signup($event_id, $user_id) {
    global $wpdb;
    $event = event_by_id($event_id);
    $problem = event_signup_problem($event, $user_id);
    if ($problem !== '') {
        return $problem;
    }
    $wpdb->query(
        $wpdb->prepare(
            "INSERT IGNORE INTO {$wpdb->prefix}eintrikot_event_signups (event_id, user_id, event_date, created_at) VALUES (%d, %d, %s, %s)",
            $event_id,
            $user_id,
            $event['date'],
            current_time('mysql', true)
        )
    );
    return '';
}
// When an account is deleted, its event sign-ups go with it at once (not only after 180 days).
add_action('deleted_user', function ($user_id) {
    global $wpdb;
    $wpdb->delete(signup_table(), ['user_id' => (int) $user_id], ['%d']);
});
function event_unsign($event_id, $user_id) {
    global $wpdb;
    $wpdb->delete(signup_table(), ['event_id' => (int) $event_id, 'user_id' => (int) $user_id], ['%d', '%d']);
}
/** Names that members may see: adults who are listed in the directory. The rest only counts. */
function event_visible_attendees($event_id) {
    $names = [];
    $hidden = 0;
    foreach (event_attendee_ids($event_id) as $user_id) {
        $user = get_user_by('id', $user_id);
        if (
            !$user ||
            !is_portal_user($user_id) ||
            is_minor_data(profile_data($user_id)) ||
            !directory_listed($user)
        ) {
            $hidden++;
            continue;
        }
        $names[$user_id] = $user->display_name;
    }
    return ['names' => $names, 'hidden' => $hidden];
}

add_action('admin_post_et_event_signup', function () {
    if (!member_access()) {
        wp_die('Keine Berechtigung.', '', ['response' => 403]);
    }
    $event_id = absint(post_text('event', '0', 10)); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the nonce is checked on the next line
    check_admin_referer('et_event_' . $event_id);
    $user_id = get_current_user_id();
    $result = ['done' => 'left'];
    if (post_choice('do', ['join', 'leave']) === 'join') {
        $problem = event_signup($event_id, $user_id);
        $result = $problem === '' ? ['done' => 'joined'] : ['problem' => 'closed'];
    } else {
        event_unsign($event_id, $user_id);
    }
    wp_safe_redirect(
        add_query_arg($result, portal_url('aktuelles', ['tab' => 'termine'])) . '#termin-' . $event_id
    );
    exit();
});

/* ---------- Anzeige ---------- */

function news_tabs($tab) {
    $tabs = ['mitteilungen' => 'Vereinsinfos', 'termine' => 'Termine'];
    $html = '<nav class="news-tabs" aria-label="Aktuelles">';
    foreach ($tabs as $key => $label) {
        $html .=
            '<a href="' .
            esc_url(portal_url('aktuelles', ['tab' => $key])) .
            '"' .
            ($key === $tab ? ' class="active" aria-current="page"' : '') .
            '>' .
            esc_html($label) .
            '</a>';
    }
    return $html . '</nav>';
}

function event_date_text($event) {
    $start = strtotime($event['date'] . ' 12:00:00');
    $end = strtotime($event['end'] . ' 12:00:00');
    if ($event['end'] === $event['date']) {
        return wp_date('l, j. F Y', $start);
    }
    return wp_date(wp_date('Y-m', $start) === wp_date('Y-m', $end) ? 'j.' : 'j. F', $start) .
        ' – ' .
        wp_date('j. F Y', $end);
}
function event_time_text($event) {
    if ($event['time'] === '') {
        return '';
    }
    return $event['time'] . ($event['end_time'] !== '' ? '–' . $event['end_time'] : '') . ' Uhr';
}

function event_signup_block($event) {
    if (!$event['signup']) {
        return '';
    }
    $user_id = get_current_user_id();
    $signed = event_is_signed($event['id'], $user_id);
    $problem = event_signup_problem($event, $user_id);
    $form = fn($do, $label, $class) => '<form class="event-signup" method="post" action="' .
        esc_url(admin_url('admin-post.php')) .
        '">' .
        wp_nonce_field('et_event_' . $event['id'], '_wpnonce', true, false) .
        '<input type="hidden" name="action" value="et_event_signup"><input type="hidden" name="event" value="' .
        (int) $event['id'] .
        '"><input type="hidden" name="do" value="' .
        esc_attr($do) .
        '"><button type="submit" class="button ' .
        esc_attr($class) .
        '">' .
        esc_html($label) .
        '</button></form>';
    $deadline =
        $event['deadline'] !== ''
            ? '<small>Anmeldung bis ' .
                esc_html(wp_date('j. F Y', strtotime($event['deadline'] . ' 12:00:00'))) .
                '.</small>'
            : '';
    if ($signed) {
        return '<div class="event-signup-box is-signed"><p><strong>Du bist angemeldet.</strong></p>' .
            $form('leave', 'Abmelden', '') .
            '</div>';
    }
    if ($problem !== '') {
        return '<div class="event-signup-box"><p>' . esc_html($problem) . '</p></div>';
    }
    return '<div class="event-signup-box">' .
        $form('join', 'Für Veranstaltung anmelden', 'solid') .
        '<small>Mit der Anmeldung steht dein Name in der Teilnehmerliste, die andere Mitglieder sehen. Du kannst dich jederzeit wieder abmelden.</small>' .
        $deadline .
        '</div>';
}

function event_attendees_block($event) {
    if (!$event['signup']) {
        return '';
    }
    $who = event_visible_attendees($event['id']);
    $total = count($who['names']) + $who['hidden'];
    if ($total === 0) {
        return '<p class="event-attendees-empty">Noch niemand angemeldet.</p>';
    }
    $list = '';
    foreach ($who['names'] as $user_id => $name) {
        $list .=
            '<li><a href="' .
            esc_url(portal_url('member', ['member' => $user_id])) .
            '">' .
            esc_html($name) .
            '</a></li>';
    }
    if ($who['hidden'] > 0) {
        $list .=
            '<li class="more">' .
            ($who['names'] ? 'und ' : '') .
            esc_html((string) $who['hidden']) .
            ' weitere</li>';
    }
    return '<details class="event-attendees"><summary>Wer ist dabei? (' .
        esc_html((string) $total) .
        ')</summary><ul>' .
        $list .
        '</ul></details>';
}

function event_card($event) {
    $formats = event_formats();
    $meta = array_filter([event_time_text($event), $event['place']]);
    $body = '';
    $content =
        trim(wp_strip_all_tags((string) $event['content'])) !== ''
            ? wp_kses_post(apply_filters('the_content', $event['content'])) // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core filter
            : '';
    foreach (['meetpoint' => 'Treffpunkt', 'contact' => 'Ansprechperson'] as $key => $label) {
        if ($event[$key] !== '') {
            $body .= '<p><strong>' . esc_html($label) . ':</strong> ' . esc_html($event[$key]) . '</p>';
        }
    }
    if ($event['link'] !== '') {
        $body .=
            '<p><a class="text-link" href="' .
            esc_url($event['link']) .
            '" target="_blank" rel="noopener noreferrer">Mehr Informationen →</a></p>';
    }
    return '<li id="termin-' .
        (int) $event['id'] .
        '" class="et-event' .
        ($event['cancelled'] ? ' is-cancelled' : '') .
        '"><time datetime="' .
        esc_attr($event['date']) .
        '"><strong>' .
        esc_html(wp_date('j.', strtotime($event['date'] . ' 12:00:00'))) .
        '</strong> ' .
        esc_html(wp_date('D', strtotime($event['date'] . ' 12:00:00'))) .
        '</time><div class="et-event-main"><span class="et-format">' .
        esc_html($formats[$event['format']]) .
        ($event['cancelled'] ? ' · abgesagt' : '') .
        '</span><h3>' .
        esc_html($event['title']) .
        '</h3><p class="et-event-meta">' .
        esc_html(event_date_text($event) . ($meta ? ' · ' . implode(' · ', $meta) : '')) .
        '</p>' .
        ($content !== '' || $body !== ''
            ? '<details><summary>Mehr zum Termin</summary><div class="et-event-body">' .
                $content .
                $body .
                '</div></details>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- content passed through wp_kses_post() above, the rest is escaped
            : '') .
        event_signup_block($event) .
        event_attendees_block($event) .
        '</div></li>';
}

/** "Termine": events for the next year, grouped by month; birthdays folded away below. */
function render_events_body() {
    if (current_user_can('eintrikot_edit_infos')) {
        echo '<p><a class="text-link" href="' .
            esc_url(admin_url('edit.php?post_type=et_calendar')) .
            '">Termine pflegen →</a></p>';
    }
    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- only shows a message after a redirect
    if (isset($_GET['done'])) {
        $done = directory_param('done');
        echo '<p role="status" class="portal-success">' .
            esc_html($done === 'joined' ? 'Du bist angemeldet.' : 'Du bist abgemeldet.') .
            '</p>';
    }
    if (directory_param('problem') === 'closed') {
        echo '<p role="alert" class="form-error">Die Anmeldung war leider nicht mehr möglich.</p>';
    }
    // phpcs:enable WordPress.Security.NonceVerification.Recommended
    $items = event_items();
    if (!$items) {
        echo '<p class="portal-empty">Aktuell keine Veranstaltungen geplant.</p>';
    }
    $month = '';
    foreach ($items as $item) {
        $label = wp_date('F Y', strtotime($item['date'] . ' 12:00:00'));
        if ($label !== $month) {
            echo ($month !== '' ? '</ol></section>' : '') .
                '<section class="portal-section et-events"><h2>' .
                esc_html($label) .
                '</h2><ol>';
            $month = $label;
        }
        echo event_card($item);
    }
    if ($items) {
        echo '</ol></section>';
    }
    $birthdays = birthday_items();
    if ($birthdays) {
        echo '<details class="et-birthdays"><summary>Geburtstage in den nächsten drei Monaten (' .
            esc_html((string) count($birthdays)) .
            ')</summary><ul>';
        foreach ($birthdays as $item) {
            echo '<li><time datetime="' .
                esc_attr($item['date']) .
                '">' .
                esc_html(wp_date('j. F', strtotime($item['date'] . ' 12:00:00'))) .
                '</time> ' .
                esc_html(preg_replace('/^Geburtstag: /', '', $item['title'])) .
                '</li>';
        }
        echo '</ul></details>';
    }
}

function render_news() {
    if (!member_access()) {
        return;
    }
    $tab = directory_param('tab') === 'termine' ? 'termine' : 'mitteilungen';
    // Old addresses keep working: ?view=events and ?view=infos.
    if (current_view() === 'events') {
        $tab = 'termine';
    }
    echo page_head(
        'Aktuelles',
        'Vereinsinfos und die nächsten Termine – nur für Mitglieder.',
        news_tabs($tab),
        'aktuelles'
    );
    if ($tab === 'termine') {
        render_events_body();
    } else {
        render_infos_body();
    }
}
