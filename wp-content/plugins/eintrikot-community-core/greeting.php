<?php
/**
 * Geburtstagsgruß (Konzept und Entscheidungen: docs/16): Jedes Mitglied bekommt an seinem Geburtstag ab 8 Uhr eine
 * kurze Glückwunsch-Mail. Einmal am Tag, ohne Nachholen an einem späteren Tag, ohne Doppelversand.
 *
 * - Empfänger: aktivierte Mitgliedskonten (Mitgliedsnummer, Portal aktiviert) mit gültigem Geburtsdatum und echter
 *   Adresse. Unter 18 nur mit Zustimmung der Eltern.
 * - Mitglieder können den Gruß nicht selbst abschalten, die Mail enthält keinen Hinweis dazu. Nur die Verwaltung kann
 *   ihn auf ausdrücklichen Wunsch für ein einzelnes Konto ausschalten (Widerspruch).
 * - Hauptschalter in der Verwaltung, standardmäßig aus.
 * - Gespeichert wird nur das Jahr des letzten Grußes; keine Öffnungs- oder Klickverfolgung.
 */
namespace Eintrikot\Community;
if (!defined('ABSPATH')) {
    exit();
}

/** From this hour (time zone of the site) the greetings go out. */
const GREETING_HOUR = 8;
/** Attempts per member and day; after a failure the next one follows an hour later. */
const GREETING_TRIES = 3;

function greeting_settings() {
    return wp_parse_args(get_option('eintrikot_greeting', []), [
        'enabled' => false,
        'last_run' => 0,
        'last_sent' => 0,
        'last_failed' => 0
    ]);
}
function greeting_enabled() {
    return !empty(greeting_settings()['enabled']);
}

/* ---------- Wer, wann ---------- */

/**
 * Is $day the birthday of someone born on $birth? On 29 February: in years without leap day on 28 February.
 */
function greeting_is_birthday(\DateTimeImmutable $birth, \DateTimeImmutable $day) {
    $md = $birth->format('m-d');
    if ($md === '02-29' && !$day->format('L')) {
        $md = '02-28';
    }
    return $md === $day->format('m-d');
}

/** Can this account receive a birthday greeting at all (independent of the date)? */
function greeting_eligible($user_id) {
    $user_id = (int) $user_id;
    $user = get_user_by('id', $user_id);
    if (
        !$user ||
        !user_can($user, 'eintrikot_portal') ||
        (string) get_user_meta($user_id, 'eintrikot_member_number', true) === '' ||
        !get_user_meta($user_id, 'eintrikot_activated_at', true) ||
        get_user_meta($user_id, 'eintrikot_no_greeting', true) ||
        mail_list_usable($user->user_email) === ''
    ) {
        return false;
    }
    $data = profile_data($user_id);
    $birth = member_birthday($data);
    if (!$birth || $birth > new \DateTimeImmutable('today', wp_timezone())) {
        return false;
    }
    // Under 18 only with the parents' consent (without it the account could not have been activated anyway).
    return !is_minor_data($data) || consent_state($user_id) === 'given';
}

/** Member accounts that can receive a greeting (candidates for the daily run). */
function greeting_candidates() {
    $ids = array_map(
        'intval',
        get_users([
            'meta_key' => 'eintrikot_member_number',
            'meta_compare' => 'EXISTS',
            'number' => -1,
            'fields' => 'ID'
        ])
    );
    return array_values(array_filter($ids, fn($id) => greeting_eligible($id)));
}

/** IDs of all accounts that have their birthday on $day. */
function greeting_due($day = null) {
    $day = $day ?: new \DateTimeImmutable('today', wp_timezone());
    return array_values(
        array_filter(
            greeting_candidates(),
            fn($id) => greeting_is_birthday(member_birthday(profile_data($id)), $day)
        )
    );
}

/* ---------- Die Mail ---------- */

function greeting_subject($first) {
    return 'Alles Gute zum Geburtstag' . ($first !== '' ? ', ' . $first : '') . '!';
}

/** HTML of the greeting (design: Variante B „Festlich“, docs/entwuerfe/geburtstagsmail.html). */
function greeting_html($first, \DateTimeImmutable $day) {
    $name = esc_html($first);
    $dot = fn(
        $color
    ) => '<span style="display:inline-block;width:14px;height:14px;border-radius:50%;border:1.5px solid #000;background:' .
        $color .
        ';margin-right:6px"></span>';
    $hero =
        '<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:#ffffff">Heute ist dein Tag. Herzliche Grüße von EINTRIKOT.</div>' .
        '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse"><tr><td bgcolor="#f1d1dd" style="background:#f1d1dd;background:linear-gradient(120deg,#f1d1dd,#c8e2ee 70%,#cedfbd);padding:30px 28px 26px;color:#000000;font-family:Montserrat,Arial,sans-serif">' .
        '<span style="display:inline-block;font-size:12px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;border:1.5px solid #000;border-radius:99px;padding:4px 11px;margin:0 0 16px;color:#000000">Heute · ' .
        esc_html(wp_date('j. F', $day->getTimestamp())) .
        '</span>' .
        '<h1 style="margin:0;font-size:42px;line-height:1;letter-spacing:-.05em;font-weight:800;color:#000000">Alles Gute' .
        ($name !== '' ? ',<br>' . $name : '') .
        '!</h1>' .
        '<div style="margin:18px 0 0;font-size:0;line-height:0">' .
        $dot('#f1d1dd') .
        $dot('#c8e2ee') .
        $dot('#cedfbd') .
        '</div></td></tr></table>';
    return mail_wrap(
        mail_p('Hallo' . ($name !== '' ? ' ' . $name : '') . ',') .
            mail_p(
                'heute hast du Geburtstag, und dazu gratulieren wir dir von Herzen! Wir wünschen dir ein gesundes, glückliches neues Lebensjahr und viele schöne Momente, auf dem Platz und daneben.'
            ) .
            mail_p('Schön, dass du Teil von EINTRIKOT bist.') .
            mail_p('Liebe Grüße<br>dein EINTRIKOT-Vorstand'),
        $hero
    );
}

/** Sends the greeting to one account. $test: to the given address with "[TEST]" and without bookkeeping. */
function send_greeting($user_id, $test_to = '') {
    $user = get_user_by('id', (int) $user_id);
    if (!$user) {
        return false;
    }
    $first = first_name($user->display_name);
    $day = new \DateTimeImmutable('today', wp_timezone());
    embed_logo_once();
    return wp_mail(
        $test_to !== '' ? $test_to : $user->user_email,
        ($test_to !== '' ? '[TEST] ' : '') . greeting_subject($first),
        greeting_html($first, $day),
        mail_headers()
    );
}

/* ---------- Täglicher Lauf ---------- */

/** Next 8:00 in the time zone of the site, as a timestamp. */
function greeting_next_start() {
    $now = new \DateTimeImmutable('now', wp_timezone());
    $start = $now->setTime(GREETING_HOUR, 0);
    return ($start <= $now ? $start->modify('+1 day') : $start)->getTimestamp();
}

add_action('init', function () {
    if (!wp_next_scheduled('eintrikot_greetings')) {
        wp_schedule_event(greeting_next_start(), 'daily', 'eintrikot_greetings');
    }
});
add_action('eintrikot_greetings', __NAMESPACE__ . '\run_greetings');
add_action('eintrikot_greetings_retry', __NAMESPACE__ . '\run_greetings');

/**
 * Greets everyone whose birthday is today and who has not been greeted this year.
 * Before 8:00 nothing happens; a run that starts late still counts for the same day. Failures are retried an hour
 * later, at most GREETING_TRIES times a day. Returns [sent, failed].
 */
function run_greetings($now = null) {
    if (!greeting_enabled()) {
        return [0, 0];
    }
    // Cron passes no argument (WordPress hands over ''); tests pass a fixed time.
    $now = $now instanceof \DateTimeImmutable ? $now : new \DateTimeImmutable('now', wp_timezone());
    if ((int) $now->format('G') < GREETING_HOUR) {
        return [0, 0];
    }
    $today = $now->setTime(0, 0);
    $year = $today->format('Y');
    $date = $today->format('Y-m-d');
    $sent = 0;
    $failed = 0;
    $retry = false;
    foreach (greeting_due($today) as $id) {
        if ((string) get_user_meta($id, 'eintrikot_greeted_year', true) === $year) {
            continue;
        }
        $tries = get_user_meta($id, 'eintrikot_greeting_tries', true);
        $tries = is_array($tries) && ($tries['date'] ?? '') === $date ? (int) $tries['count'] : 0;
        if ($tries >= GREETING_TRIES) {
            continue;
        }
        update_user_meta($id, 'eintrikot_greeting_tries', ['date' => $date, 'count' => $tries + 1]);
        if (send_greeting($id)) {
            update_user_meta($id, 'eintrikot_greeted_year', $year);
            delete_user_meta($id, 'eintrikot_greeting_tries');
            $sent++;
        } else {
            $failed++;
            $retry = $retry || $tries + 1 < GREETING_TRIES;
        }
    }
    // Retry only on the same day: never send a greeting late.
    $later = $now->modify('+1 hour');
    if ($retry && $later->format('Y-m-d') === $date && !wp_next_scheduled('eintrikot_greetings_retry')) {
        wp_schedule_single_event($later->getTimestamp(), 'eintrikot_greetings_retry');
    }
    update_option(
        'eintrikot_greeting',
        array_merge(greeting_settings(), [
            'last_run' => time(),
            'last_sent' => $sent,
            'last_failed' => $failed
        ]),
        false
    );
    return [$sent, $failed];
}

/* ---------- Verwaltung im Portal ---------- */

/** Number of greetings for each of the next $days days (only counts, no names). */
function greeting_outlook($days = 7) {
    $candidates = array_map(fn($id) => member_birthday(profile_data($id)), greeting_candidates());
    $out = [];
    $day = new \DateTimeImmutable('today', wp_timezone());
    for ($i = 0; $i < $days; $i++) {
        $out[] = [
            'day' => $day,
            'count' => count(array_filter($candidates, fn($birth) => greeting_is_birthday($birth, $day)))
        ];
        $day = $day->modify('+1 day');
    }
    return $out;
}

function render_greetings() {
    if (!view_manager()) {
        echo '<div class="portal-empty"><h1>Kein Zugriff</h1></div>';
        return;
    }
    $s = greeting_settings();
    echo '<a class="text-link service-back" href="' .
        esc_url(portal_url('admin')) .
        '">← Verwaltung</a>' .
        page_head(
            'Geburtstagsgrüße',
            'Jedes Mitglied bekommt an seinem Geburtstag ab 8 Uhr eine kurze Glückwunsch-Mail vom Verein.',
            '',
            'admin'
        );
    $notices = [
        'on' => 'Geburtstagsgrüße sind eingeschaltet.',
        'off' => 'Geburtstagsgrüße sind ausgeschaltet.',
        'test' => 'Testmail an dich verschickt.',
        'testfail' => 'Die Testmail konnte nicht verschickt werden.'
    ];
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only, key of a fixed list
    $notice =
        isset($_GET['greeting']) && is_scalar($_GET['greeting'])
            ? sanitize_key(wp_unslash($_GET['greeting']))
            : '';
    if (isset($notices[$notice])) {
        echo '<p role="status" class="portal-success" tabindex="-1">' . esc_html($notices[$notice]) . '</p>';
    }
    $form = function ($action, $fields, $button, $solid = false) {
        return '<form method="post" action="' .
            esc_url(admin_url('admin-post.php')) .
            '">' .
            wp_nonce_field($action, '_wpnonce', true, false) .
            '<input type="hidden" name="action" value="' .
            esc_attr($action) .
            '">' .
            $fields .
            '<button class="button' .
            ($solid ? ' solid' : '') .
            '">' .
            esc_html($button) .
            '</button></form>';
    };
    $enabled = !empty($s['enabled']);
    // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup; $form escapes its values
    echo '<section class="portal-section"><h2>' .
        ($enabled ? 'Eingeschaltet' : 'Ausgeschaltet') .
        '</h2><p>' .
        ($enabled
            ? 'Die Grüße gehen jeden Tag ab 8 Uhr automatisch raus.'
            : 'Zurzeit geht kein Geburtstagsgruß raus.') .
        '</p>' .
        $form(
            'et_greeting_switch',
            '<input type="hidden" name="enabled" value="' . ($enabled ? '0' : '1') . '">',
            $enabled ? 'Ausschalten' : 'Einschalten',
            !$enabled
        ) .
        '</section>';
    echo '<section class="portal-section"><h2>Nächste 7 Tage</h2><ul class="greeting-outlook">';
    foreach (greeting_outlook() as $row) {
        echo '<li>' .
            esc_html(wp_date('D, j. M', $row['day']->getTimestamp())) .
            ': <strong>' .
            esc_html((string) $row['count']) .
            '</strong></li>';
    }
    echo '</ul><p>Nur die Zahl der Grüße, keine Namen. Grüße bekommen aktivierte Mitgliedskonten mit Geburtsdatum.</p>';
    if (!empty($s['last_run'])) {
        echo '<p>Letzter Lauf: ' .
            esc_html(wp_date('d.m.Y, H:i', (int) $s['last_run'])) .
            ' Uhr · ' .
            esc_html((string) (int) $s['last_sent']) .
            ' verschickt, ' .
            esc_html((string) (int) $s['last_failed']) .
            ' Fehler</p>';
    }
    echo '</section><section class="portal-section"><h2>Testmail</h2><p>Schickt dir den Gruß mit deinem Vornamen an deine eigene Adresse. Im Betreff steht „[TEST]“.</p>' .
        $form('et_greeting_test', '', 'Testmail an mich senden') .
        '</section><section class="portal-section"><h2>Für ein Konto abschalten</h2><p>Mitglieder können den Gruß nicht selbst abschalten. Möchte jemand ausdrücklich keine Geburtstagsgrüße, setzt du im Profil des Mitglieds unter „Verwaltung“ das Häkchen „Keine Geburtstagsgrüße“.</p></section>';
    // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
}

add_action('admin_post_et_greeting_switch', function () {
    if (!manager_access()) {
        wp_die('Keine Berechtigung.', '', ['response' => 403]);
    }
    check_admin_referer('et_greeting_switch');
    $before = greeting_enabled();
    $after = post_text('enabled', '0', 1) === '1';
    update_option('eintrikot_greeting', array_merge(greeting_settings(), ['enabled' => $after]), false);
    if ($before !== $after) {
        log_change(
            0,
            'birthday_greeting',
            $before ? 'an' : 'aus',
            $after ? 'an' : 'aus',
            'Geburtstagsgrüße ' . ($after ? 'eingeschaltet' : 'ausgeschaltet')
        );
    }
    wp_safe_redirect(portal_url('greetings', ['greeting' => $after ? 'on' : 'off']));
    exit();
});

add_action('admin_post_et_greeting_test', function () {
    if (!manager_access()) {
        wp_die('Keine Berechtigung.', '', ['response' => 403]);
    }
    check_admin_referer('et_greeting_test');
    $me = wp_get_current_user();
    $ok = send_greeting($me->ID, $me->user_email);
    wp_safe_redirect(portal_url('greetings', ['greeting' => $ok ? 'test' : 'testfail']));
    exit();
});

/** Checkbox in the administration part of a profile (only the administration sees and sets it). */
function greeting_profile_field($user_id) {
    return '<label class="check"><input type="checkbox" name="no_greeting" value="1" ' .
        checked((bool) get_user_meta($user_id, 'eintrikot_no_greeting', true), true, false) .
        '> Keine Geburtstagsgrüße</label><small>Nur setzen, wenn das Mitglied ausdrücklich keine Glückwunsch-Mail möchte. Das Mitglied sieht diese Einstellung nicht.</small>';
}

/** Saves the checkbox; called from the profile form of the administration. */
function greeting_profile_save($user_id, $reason) {
    $before = (bool) get_user_meta($user_id, 'eintrikot_no_greeting', true);
    $after = post_text('no_greeting', '', 1) === '1';
    if ($before === $after) {
        return;
    }
    if ($after) {
        update_user_meta($user_id, 'eintrikot_no_greeting', 1);
    } else {
        delete_user_meta($user_id, 'eintrikot_no_greeting');
    }
    log_change(
        $user_id,
        'birthday_greeting',
        $before ? 'aus' : 'an',
        $after ? 'aus' : 'an',
        $reason !== ''
            ? $reason
            : 'Geburtstagsgrüße für dieses Konto ' . ($after ? 'ausgeschaltet' : 'eingeschaltet')
    );
}
