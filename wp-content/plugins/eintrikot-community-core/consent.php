<?php
/**
 * Minderjährige Mitglieder: Zustimmung der Eltern und Jugendregeln.
 *
 * Ablauf: Antrag in MeinVerein → Import ins Portal (mit Geburtsdatum) → unter 18: das junge Mitglied
 * bekommt statt der Begrüßung eine Mail, trägt die E-Mail-Adresse eines Elternteils ein → der
 * Elternteil bestätigt auf einer Portalseite → erst dann Begrüßung, Urkunde und Zugang.
 * Bis zur Zustimmung ist keine Anmeldung möglich. Gespeichert wird der Nachweis (Name, Zeitpunkt,
 * bestätigter Text); Aufbewahrung bis drei Jahre nach dem 18. Geburtstag.
 *
 * Jugendregeln: Solange ein Mitglied unter 18 ist, sehen andere nur Name, Team, Altersklasse und
 * Region – kein Alter, kein Wohnort, keine weiteren Angaben, kein Geburtstag in den Vereinsinfos.
 */
namespace Eintrikot\Community;
if (!defined('ABSPATH')) {
    exit();
}

const CONSENT_VALID_DAYS = 30;
const CONSENT_REMIND_DAYS = 7;
const CONSENT_TEXT_VERSION = '2026-09';
/** Profile fields other members may see while someone is under 18. */
const MINOR_VISIBLE_KEYS = ['team', 'age_class', 'region'];

/* ---------- Alter ---------- */

function member_birthday($data) {
    $b = $data['birthday'] ?? '';
    $d = is_string($b) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $b, wp_timezone()) : false;
    return $d && $d->format('Y-m-d') === $b ? $d : null;
}
/** Age in years, or null without a valid birthday (then the adult rules apply). */
function member_years($data) {
    $birth = member_birthday($data);
    $today = new \DateTimeImmutable('today', wp_timezone());
    return $birth && $birth <= $today ? $birth->diff($today)->y : null;
}
function is_minor_data($data) {
    $years = member_years($data);
    return $years !== null && $years < 18;
}
function is_minor($user_id) {
    return is_minor_data(profile_data($user_id));
}

/* ---------- Zustand ---------- */

/** '' (nicht nötig), 'kid' (wartet auf Eltern-Adresse), 'parent' (wartet auf Zustimmung), 'given'. */
function consent_state($user_id) {
    $state = (string) get_user_meta($user_id, 'eintrikot_consent', true);
    if ($state === 'given') {
        return 'given';
    }
    if (!is_minor($user_id)) {
        return ''; // Turned 18 in the meantime, or no birthday: adult rules.
    }
    return in_array($state, ['kid', 'parent'], true) ? $state : 'kid';
}
/** Whether this account still waits for the parents (no login, no directory, no welcome). */
function consent_pending($user_id) {
    return in_array(consent_state($user_id), ['kid', 'parent'], true) &&
        !get_user_meta($user_id, 'eintrikot_activated_at', true);
}

function consent_token($user_id, $type) {
    $token = wp_generate_password(40, false, false);
    update_user_meta($user_id, 'eintrikot_consent_' . $type . '_hash', hash('sha256', $token));
    update_user_meta($user_id, 'eintrikot_consent_' . $type . '_at', time());
    return $token;
}
function consent_token_valid($user_id, $type, $token) {
    $hash = (string) get_user_meta($user_id, 'eintrikot_consent_' . $type . '_hash', true);
    $at = (int) get_user_meta($user_id, 'eintrikot_consent_' . $type . '_at', true);
    return $hash !== '' &&
        is_string($token) &&
        hash_equals($hash, hash('sha256', $token)) &&
        time() - $at <= CONSENT_VALID_DAYS * DAY_IN_SECONDS;
}
function consent_url($type, $user_id, $token) {
    return portal_url('consent', ['step' => $type, 'u' => (int) $user_id, 'k' => $token]);
}

/** Masks an address for display: m•••@web.de */
function mask_email($email) {
    [$local, $domain] = array_pad(explode('@', (string) $email, 2), 2, '');
    return mb_substr($local, 0, 1) . '•••@' . $domain;
}

/* ---------- Mails ---------- */

function send_consent_kid_mail($user_id, $reminder = false) {
    $user = get_user_by('id', $user_id);
    if (!$user) {
        return false;
    }
    $link = consent_url('kid', $user_id, consent_token($user_id, 'kid'));
    embed_logo_once();
    $sent = wp_mail(
        $user->user_email,
        ($reminder ? 'Erinnerung: ' : '') . 'Fast geschafft – deine Eltern müssen kurz zustimmen',
        mail_wrap(
            mail_p('Hallo ' . esc_html(first_name($user->display_name)) . ',') .
                mail_p(
                    'schön, dass du Mitglied bei EINTRIKOT werden möchtest! Weil du noch unter 18 bist, brauchen wir die Zustimmung deiner Eltern.'
                ) .
                mail_p(
                    'Trag dazu die E-Mail-Adresse eines Elternteils ein. Wir schicken ihnen eine kurze Erklärung und einen Link zum Bestätigen.'
                ) .
                mail_button($link, 'E-Mail-Adresse der Eltern eintragen') .
                mail_note(
                    'Der Link gilt ' .
                        CONSENT_VALID_DAYS .
                        ' Tage. Sobald deine Eltern zugestimmt haben, bekommst du deine Zugangsdaten zur EINTRIKOT-App.'
                ) .
                mail_signoff()
        ),
        mail_headers()
    );
    if ($sent) {
        update_user_meta($user_id, 'eintrikot_consent', 'kid');
        update_user_meta($user_id, 'eintrikot_invited_at', time());
        if ($reminder) {
            update_user_meta($user_id, 'eintrikot_consent_reminded', 'kid');
        }
        log_change(
            $user_id,
            'parental_consent',
            null,
            $reminder ? 'Erinnerung an Mitglied' : 'Bitte um Eltern-Adresse verschickt',
            'Zustimmung der Eltern'
        );
    }
    return $sent;
}

function send_consent_parent_mail($user_id, $reminder = false) {
    $user = get_user_by('id', $user_id);
    $to = (string) get_user_meta($user_id, 'eintrikot_consent_parent_email', true);
    if (!$user || !is_email($to)) {
        return false;
    }
    $first = esc_html(first_name($user->display_name));
    $link = consent_url('parent', $user_id, consent_token($user_id, 'parent'));
    $h2 = fn($t) => '<h2 style="margin:26px 0 10px;font-size:18px">' . $t . '</h2>';
    $li = fn($items) => '<ul style="margin:0 0 16px;padding-left:20px;font-size:16px;line-height:1.6">' .
        implode('', array_map(fn($i) => '<li style="margin:0 0 4px">' . $i . '</li>', $items)) .
        '</ul>';
    $privacy = get_privacy_policy_url() ?: home_url('/datenschutz/');
    embed_logo_once();
    $sent = wp_mail(
        $to,
        ($reminder ? 'Erinnerung: ' : '') . $first . ' möchte EINTRIKOT beitreten – bitte um Ihre Zustimmung',
        mail_wrap(
            mail_p('Guten Tag,') .
                mail_p(
                    '<strong>' .
                        esc_html($user->display_name) .
                        '</strong> möchte Mitglied bei EINTRIKOT e.V. werden. Weil ' .
                        $first .
                        ' noch nicht volljährig ist, brauchen wir dafür Ihre Zustimmung als Erziehungsberechtigte.'
                ) .
                mail_button($link, 'Zustimmung geben') .
                $h2('Wer wir sind') .
                mail_p(
                    'EINTRIKOT ist das Netzwerk der Hockey-Nationalteams. Wir verbinden aktuelle und ehemalige Nationalspielerinnen und Nationalspieler aller Generationen – von der Jugend bis zu den Masters. Mitglied werden kann, wer mindestens ein Länderspiel in einer deutschen Nationalmannschaft bestritten hat. Das Netzwerk bauen wir gerade auf.'
                ) .
                $h2('Was ' . $first . ' davon hat') .
                $li([
                    'Kontakt zu Spielerinnen und Spielern aus allen Generationen',
                    'Mentoring durch erfahrene Mitglieder – zu Sport, Studium und Beruf',
                    'Veranstaltungen und Treffen im Netzwerk',
                    'die EINTRIKOT-App mit Mitgliederverzeichnis, Vereinsinfos und Terminen'
                ]) .
                $h2('Kosten und Finanzierung') .
                mail_p(
                    'Bis einschließlich 31 Jahre ist die Mitgliedschaft beitragsfrei – für ' .
                        $first .
                        ' entstehen keine Kosten. EINTRIKOT finanziert sich ausschließlich über die Beiträge der Mitglieder ab 32 Jahren und über Spenden. Langfristig wollen wir den Hockey-Leistungssport rund um die Nationalteams auch finanziell stärken.'
                ) .
                $h2('Gemeinnützig') .
                mail_p(
                    'EINTRIKOT e.V. verfolgt ausschließlich und unmittelbar gemeinnützige Zwecke: die Förderung des Leistungssports im Feld- und Hallenhockey rund um die deutschen Nationalmannschaften.'
                ) .
                $h2('Datenschutz') .
                mail_p(
                    'Solange ' .
                        $first .
                        ' minderjährig ist, sehen andere Mitglieder höchstens Name, Team, Altersklasse und Region – kein Alter, keinen Wohnort und keine weiteren Angaben. Mehr in unserer <a href="' .
                        esc_url($privacy) .
                        '" style="color:inherit">Datenschutzerklärung</a>.'
                ) .
                mail_button($link, 'Zustimmung geben') .
                mail_note(
                    'Der Link gilt ' .
                        CONSENT_VALID_DAYS .
                        ' Tage. Ohne Ihre Zustimmung wird die Mitgliedschaft nicht wirksam. Haben Sie nichts angefragt oder passt etwas nicht? Antworten Sie einfach auf diese E-Mail.'
                ) .
                mail_p('Viele Grüße<br>EINTRIKOT e.V.')
        ),
        mail_headers()
    );
    if ($sent) {
        update_user_meta($user_id, 'eintrikot_consent', 'parent');
        if ($reminder) {
            update_user_meta($user_id, 'eintrikot_consent_reminded', 'parent');
        }
        log_change(
            $user_id,
            'parental_consent',
            null,
            ($reminder ? 'Erinnerung an ' : 'Anfrage an ') . mask_email($to),
            'Zustimmung der Eltern'
        );
    }
    return $sent;
}

/** Stores the consent, informs the parent and sends the normal welcome to the member. */
function record_consent($user_id, $record) {
    $record['time'] = time();
    $record['text_version'] = CONSENT_TEXT_VERSION;
    update_user_meta($user_id, 'eintrikot_consent_record', $record);
    update_user_meta($user_id, 'eintrikot_consent', 'given');
    delete_user_meta($user_id, 'eintrikot_consent_kid_hash');
    delete_user_meta($user_id, 'eintrikot_consent_parent_hash');
    log_change(
        $user_id,
        'parental_consent',
        null,
        'erteilt von ' .
            $record['name'] .
            ($record['method'] === 'manual' ? ' (eingetragen von der Verwaltung)' : ''),
        'Zustimmung der Eltern'
    );
    $parent = (string) get_user_meta($user_id, 'eintrikot_consent_parent_email', true);
    $user = get_user_by('id', $user_id);
    if ($record['method'] === 'online' && is_email($parent) && $user) {
        embed_logo_once();
        wp_mail(
            $parent,
            'Danke – Ihre Zustimmung ist eingegangen',
            mail_wrap(
                mail_p('Guten Tag ' . esc_html($record['name']) . ',') .
                    mail_p(
                        'vielen Dank! Ihre Zustimmung zur Mitgliedschaft von ' .
                            esc_html($user->display_name) .
                            ' bei EINTRIKOT e.V. ist am ' .
                            esc_html(wp_date('d.m.Y \u\m H:i \U\h\r', $record['time'])) .
                            ' eingegangen. ' .
                            esc_html(first_name($user->display_name)) .
                            ' bekommt jetzt die Zugangsdaten zur EINTRIKOT-App.'
                    ) .
                    mail_note(
                        'Möchten Sie Ihre Zustimmung widerrufen oder haben Sie Fragen? Antworten Sie einfach auf diese E-Mail.'
                    ) .
                    mail_p('Viele Grüße<br>EINTRIKOT e.V.')
            ),
            mail_headers()
        );
    }
    return send_invitation($user_id);
}

/* ---------- Öffentliche Seiten (ohne Anmeldung) ---------- */

function consent_page($inner) {
    return '<div class="et-app"><header class="header">' .
        portal_brand() .
        '</header><main class="auth consent-page"><div class="auth-inner">' .
        $inner .
        '</div></main></div>';
}
function consent_invalid() {
    return consent_page(
        '<h1>Link nicht mehr gültig.</h1><p>Dieser Link ist abgelaufen oder wurde schon verwendet. Schreib uns an <a class="text-link" href="mailto:info@eintrikot.de">info@eintrikot.de</a>, dann schicken wir einen neuen.</p>'
    );
}

function render_consent() {
    $step = isset($_GET['step']) && is_string($_GET['step']) ? $_GET['step'] : '';
    $user_id = isset($_GET['u']) ? absint($_GET['u']) : 0;
    $token = isset($_GET['k']) && is_string($_GET['k']) ? $_GET['k'] : '';
    $user = get_user_by('id', $user_id);
    // Shows no name: this page is reachable without a token.
    if ($step === 'done') {
        return consent_page(
            '<h1>Vielen Dank.</h1><p>Ihre Zustimmung ist eingegangen. Eine Bestätigung ist per E-Mail unterwegs, und Ihr Kind bekommt jetzt die Zugangsdaten zur EINTRIKOT-App.</p><p>Fragen? Schreiben Sie uns an <a class="text-link" href="mailto:info@eintrikot.de">info@eintrikot.de</a>.</p>'
        );
    }
    if (
        !$user ||
        !in_array($step, ['kid', 'parent'], true) ||
        !consent_token_valid($user_id, $step, $token)
    ) {
        return consent_invalid();
    }
    $action = esc_url(admin_url('admin-post.php'));
    $hidden =
        '<input type="hidden" name="u" value="' .
        (int) $user_id .
        '"><input type="hidden" name="k" value="' .
        esc_attr($token) .
        '">';
    $notice =
        isset($_GET['error']) && is_string($_GET['error'])
            ? '<div class="form-error" role="alert" tabindex="-1"><p>' .
                esc_html(wp_unslash($_GET['error'])) .
                '</p></div>'
            : '';
    $first = esc_html(first_name($user->display_name));

    if ($step === 'kid') {
        $parent = (string) get_user_meta($user_id, 'eintrikot_consent_parent_email', true);
        if (isset($_GET['sent']) && is_email($parent)) {
            return consent_page(
                '<h1>Danke, ' .
                    $first .
                    '.</h1><p>Wir haben eine E-Mail an <strong>' .
                    esc_html(mask_email($parent)) .
                    '</strong> geschickt. Sag deinen Eltern am besten kurz Bescheid – die Mail kommt von EINTRIKOT e.V. und landet manchmal im Spam-Ordner.</p><p>Sobald sie zugestimmt haben, bekommst du deine Zugangsdaten zur EINTRIKOT-App.</p><a class="text-link" href="' .
                    esc_url(consent_url('kid', $user_id, $token)) .
                    '">Adresse falsch? Neu eingeben</a>'
            );
        }
        return consent_page(
            '<h1>Fast geschafft, ' .
                $first .
                '.</h1><p>Weil du noch unter 18 bist, müssen deine Eltern deiner Mitgliedschaft kurz zustimmen. Trag dazu die E-Mail-Adresse eines Elternteils ein.</p>' .
                $notice .
                '<form class="et-form consent-form" method="post" action="' .
                $action .
                '" novalidate data-consent-email>' .
                wp_nonce_field('et_consent_kid', '_wpnonce', true, false) .
                '<input type="hidden" name="action" value="et_consent_kid">' .
                $hidden .
                '<label class="field">E-Mail-Adresse eines Elternteils<input type="email" name="parent_email" autocomplete="off" autocapitalize="off" spellcheck="false" inputmode="email" required maxlength="120" data-email-main></label><p class="email-suggest" data-email-suggest hidden></p><label class="field">Zur Sicherheit noch einmal<input type="email" name="parent_email_repeat" autocomplete="off" autocapitalize="off" spellcheck="false" inputmode="email" required maxlength="120" data-email-repeat></label><p class="field-error" data-email-mismatch hidden>Die beiden Adressen stimmen nicht überein.</p><button class="button solid">Mail an meine Eltern schicken</button></form><p class="consent-small">Die Adresse nutzen wir nur für die Zustimmung und für Nachfragen dazu.</p>'
        );
    }

    // Parent confirmation.
    return consent_page(
        '<h1>Zustimmung zur Mitgliedschaft.</h1><p><strong>' .
            esc_html($user->display_name) .
            '</strong> möchte Mitglied bei EINTRIKOT e.V. werden, dem Netzwerk der Hockey-Nationalteams. Die Mitgliedschaft ist bis einschließlich 31 Jahre beitragsfrei.</p>' .
            $notice .
            '<form class="et-form consent-form" method="post" action="' .
            $action .
            '">' .
            wp_nonce_field('et_consent_parent', '_wpnonce', true, false) .
            '<input type="hidden" name="action" value="et_consent_parent">' .
            $hidden .
            '<label class="field">Ihr Vor- und Nachname<input name="parent_name" required maxlength="120" autocomplete="name"></label>' .
            '<fieldset class="consent-custody"><legend>Sorgerecht</legend><label class="check"><input type="radio" name="custody" value="joint" required><span>Ich bin sorgeberechtigt und handle im Einverständnis mit dem anderen sorgeberechtigten Elternteil.</span></label><label class="check"><input type="radio" name="custody" value="sole"><span>Ich bin allein sorgeberechtigt.</span></label></fieldset>' .
            '<label class="check"><input type="checkbox" name="agree" value="1" required><span>Ich stimme der Mitgliedschaft von ' .
            esc_html($user->display_name) .
            ' bei EINTRIKOT e.V. zu. Die <a class="text-link" href="' .
            esc_url(home_url('/mitglied-werden/')) .
            '" target="_blank" rel="noopener">Informationen zur Mitgliedschaft</a> und die <a class="text-link" href="' .
            esc_url(get_privacy_policy_url() ?: home_url('/datenschutz/')) .
            '" target="_blank" rel="noopener">Datenschutzerklärung</a> habe ich zur Kenntnis genommen.</span></label>' .
            '<label class="check"><input type="checkbox" name="directory" value="1"><span><strong>Freiwillig:</strong> ' .
            $first .
            ' darf im Mitgliederverzeichnis erscheinen – mit Name, Team, Altersklasse und Region. Ohne Haken ist ' .
            $first .
            ' dort nicht zu finden.</span></label>' .
            '<button class="button solid">Zustimmung geben</button></form><p class="consent-small">Sie können die Zustimmung jederzeit widerrufen – eine kurze Nachricht an info@eintrikot.de genügt.</p>'
    );
}

function consent_redirect($step, $user_id, $token, $args = []) {
    wp_safe_redirect(add_query_arg($args, consent_url($step, $user_id, $token)));
    exit();
}

foreach (['admin_post_et_consent_kid', 'admin_post_nopriv_et_consent_kid'] as $hook) {
    add_action($hook, function () {
        check_admin_referer('et_consent_kid');
        $user_id = absint($_POST['u'] ?? 0);
        $token = is_string($_POST['k'] ?? null) ? $_POST['k'] : '';
        if (!consent_token_valid($user_id, 'kid', $token) || consent_state($user_id) === 'given') {
            wp_safe_redirect(portal_url('consent'));
            exit();
        }
        $email = strtolower(trim(sanitize_email(wp_unslash((string) ($_POST['parent_email'] ?? '')))));
        $repeat = strtolower(
            trim(sanitize_email(wp_unslash((string) ($_POST['parent_email_repeat'] ?? ''))))
        );
        $user = get_user_by('id', $user_id);
        $error = '';
        if (!is_email($email)) {
            $error = 'Bitte eine gültige E-Mail-Adresse eingeben.';
        } elseif ($email !== $repeat) {
            $error = 'Die beiden Adressen stimmen nicht überein.';
        } elseif ($user && $email === strtolower($user->user_email)) {
            $error = 'Das ist deine eigene Adresse. Bitte die Adresse eines Elternteils eingeben.';
        } elseif ((int) get_user_meta($user_id, 'eintrikot_consent_changes', true) >= 5) {
            $error = 'Die Adresse wurde schon mehrmals geändert. Bitte schreib uns an info@eintrikot.de.';
        }
        if ($error) {
            consent_redirect('kid', $user_id, $token, ['error' => rawurlencode($error)]);
        }
        update_user_meta($user_id, 'eintrikot_consent_parent_email', $email);
        update_user_meta(
            $user_id,
            'eintrikot_consent_changes',
            (int) get_user_meta($user_id, 'eintrikot_consent_changes', true) + 1
        );
        delete_user_meta($user_id, 'eintrikot_consent_reminded');
        if (!send_consent_parent_mail($user_id)) {
            consent_redirect('kid', $user_id, $token, [
                'error' => rawurlencode(
                    'Die E-Mail konnte gerade nicht verschickt werden. Bitte später erneut versuchen.'
                )
            ]);
        }
        consent_redirect('kid', $user_id, $token, ['sent' => 1]);
    });
}

foreach (['admin_post_et_consent_parent', 'admin_post_nopriv_et_consent_parent'] as $hook) {
    add_action($hook, function () {
        check_admin_referer('et_consent_parent');
        $user_id = absint($_POST['u'] ?? 0);
        $token = is_string($_POST['k'] ?? null) ? $_POST['k'] : '';
        if (!consent_token_valid($user_id, 'parent', $token) || consent_state($user_id) === 'given') {
            wp_safe_redirect(portal_url('consent'));
            exit();
        }
        $name = trim(sanitize_text_field(wp_unslash((string) ($_POST['parent_name'] ?? ''))));
        $custody = in_array($_POST['custody'] ?? '', ['joint', 'sole'], true) ? $_POST['custody'] : '';
        if (mb_strlen($name) < 3 || !$custody || empty($_POST['agree'])) {
            consent_redirect('parent', $user_id, $token, [
                'error' => rawurlencode(
                    'Bitte Ihren Namen eingeben, das Sorgerecht auswählen und der Mitgliedschaft zustimmen.'
                )
            ]);
        }
        $user = get_user_by('id', $user_id);
        $record = [
            'method' => 'online',
            'name' => mb_substr($name, 0, 120),
            'email' => (string) get_user_meta($user_id, 'eintrikot_consent_parent_email', true),
            'custody' => $custody,
            'directory' => !empty($_POST['directory']),
            'text' =>
                ($custody === 'sole'
                    ? 'Ich bin allein sorgeberechtigt.'
                    : 'Ich bin sorgeberechtigt und handle im Einverständnis mit dem anderen sorgeberechtigten Elternteil.') .
                ' Ich stimme der Mitgliedschaft von ' .
                ($user ? $user->display_name : '') .
                ' bei EINTRIKOT e.V. zu.' .
                (!empty($_POST['directory'])
                    ? ' Sichtbar im Mitgliederverzeichnis: ja.'
                    : ' Sichtbar im Mitgliederverzeichnis: nein.')
        ];
        record_consent($user_id, $record);
        wp_safe_redirect(portal_url('consent', ['step' => 'done']));
        exit();
    });
}

/* ---------- Kein Zugang vor der Zustimmung ---------- */

add_filter(
    'authenticate',
    function ($user) {
        if ($user instanceof \WP_User && consent_pending($user->ID)) {
            return new \WP_Error(
                'et_consent',
                'Deine Mitgliedschaft wartet noch auf die Zustimmung deiner Eltern. Danach bekommst du deine Zugangsdaten per E-Mail.'
            );
        }
        return $user;
    },
    100
);
// "Passwort vergessen" stays silent for accounts that wait for the parents.
add_action(
    'lostpassword_post',
    function ($errors, $user) {
        if ($user instanceof \WP_User && consent_pending($user->ID)) {
            wp_safe_redirect(add_query_arg('checkemail', 'confirm', wp_login_url()));
            exit();
        }
    },
    5,
    2
);

/* ---------- Erinnerungen ---------- */

add_action('init', function () {
    if (!wp_next_scheduled('eintrikot_consent_reminders')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'eintrikot_consent_reminders');
    }
});
add_action('eintrikot_consent_reminders', function () {
    foreach (
        get_users([
            'meta_query' => [['key' => 'eintrikot_consent', 'value' => ['kid', 'parent'], 'compare' => 'IN']]
        ])
        as $user
    ) {
        $state = consent_state($user->ID);
        if (
            !consent_pending($user->ID) ||
            get_user_meta($user->ID, 'eintrikot_consent_reminded', true) === $state
        ) {
            continue;
        }
        $since = (int) get_user_meta($user->ID, 'eintrikot_consent_' . $state . '_at', true);
        if ($since && time() - $since > CONSENT_REMIND_DAYS * DAY_IN_SECONDS) {
            $state === 'kid'
                ? send_consent_kid_mail($user->ID, true)
                : send_consent_parent_mail($user->ID, true);
        }
    }
    // Retention: the consent record is deleted three years after the 18th birthday.
    foreach (get_users(['meta_key' => 'eintrikot_consent_record']) as $user) {
        $birth = member_birthday(profile_data($user->ID));
        if ($birth && $birth->modify('+21 years') < new \DateTimeImmutable('today', wp_timezone())) {
            delete_user_meta($user->ID, 'eintrikot_consent_record');
            delete_user_meta($user->ID, 'eintrikot_consent_parent_email');
        }
    }
});

/* ---------- Verwaltung: Zustimmung von Hand ---------- */

add_action('admin_post_et_consent_manual', function () {
    if (!manager_access()) {
        wp_die('Keine Berechtigung.', '', ['response' => 403]);
    }
    check_admin_referer('et_consent_manual');
    $user_id = absint($_POST['user'] ?? 0);
    $name = trim(sanitize_text_field(wp_unslash((string) ($_POST['parent_name'] ?? ''))));
    $note = trim(sanitize_text_field(wp_unslash((string) ($_POST['note'] ?? ''))));
    if (!consent_pending($user_id) || mb_strlen($name) < 3 || mb_strlen($note) < 5) {
        onboarding_notice(
            false,
            'Bitte Namen des Elternteils und einen Vermerk (z. B. „Unterschrift auf Papier vom …“) angeben.'
        );
    }
    $result = record_consent($user_id, [
        'method' => 'manual',
        'name' => mb_substr($name, 0, 120),
        'email' => (string) get_user_meta($user_id, 'eintrikot_consent_parent_email', true),
        'custody' => 'manual',
        'directory' => false,
        'text' => 'Von der Verwaltung eingetragen: ' . mb_substr($note, 0, 300)
    ]);
    onboarding_notice(
        !is_wp_error($result),
        is_wp_error($result)
            ? 'Zustimmung gespeichert, aber die Begrüßung konnte nicht verschickt werden: ' .
                $result->get_error_message()
            : 'Zustimmung gespeichert und Begrüßung verschickt.'
    );
});

/** Status text for the admin list, or null when no consent is involved. */
function consent_label($user_id) {
    $state = consent_state($user_id);
    if ($state === 'given') {
        $record = get_user_meta($user_id, 'eintrikot_consent_record', true);
        return is_array($record) && !empty($record['time'])
            ? ['given', 'Eltern haben zugestimmt am ' . wp_date('d.m.Y', (int) $record['time'])]
            : null;
    }
    if (!consent_pending($user_id)) {
        return null;
    }
    $since = (int) get_user_meta($user_id, 'eintrikot_consent_' . $state . '_at', true);
    $overdue = $since && time() - $since > CONSENT_VALID_DAYS * DAY_IN_SECONDS;
    if ($state === 'kid' && !$since) {
        return ['waiting', 'Unter 18 · Zustimmung der Eltern nötig'];
    }
    return [
        $overdue ? 'overdue' : 'waiting',
        ($state === 'kid' ? 'Wartet auf Eltern-Adresse' : 'Wartet auf Zustimmung der Eltern') .
        ($since ? ' seit ' . wp_date('d.m.Y', $since) : '') .
        ($overdue ? ' · bitte nachfassen' : '')
    ];
}

function consent_manual_form($user_id) {
    ob_start();
    echo '<details class="consent-manual"><summary>Zustimmung von Hand eintragen</summary><form method="post" action="' .
        esc_url(admin_url('admin-post.php')) .
        '">';
    wp_nonce_field('et_consent_manual');
    echo '<input type="hidden" name="action" value="et_consent_manual"><input type="hidden" name="user" value="' .
        (int) $user_id .
        '"><label>Name des Elternteils<input name="parent_name" required maxlength="120"></label><label>Vermerk<input name="note" required maxlength="300" placeholder="z. B. Unterschrift auf Papier vom 01.10.2026"></label><button class="text-reset">Speichern und Begrüßung senden</button></form></details>';
    return ob_get_clean();
}

/* ---------- Jugendregeln im Verzeichnis ---------- */

/** While a member is under 18, other members only see name, team, age class and region. */
function minor_hides($data, $key) {
    return is_minor_data($data) && !in_array($key, MINOR_VISIBLE_KEYS, true);
}
/** Minors appear in the directory only after consent, and only if the parents allowed it. */
function minor_listed($user_id) {
    if (!is_minor($user_id)) {
        return true;
    }
    if (consent_pending($user_id)) {
        return false;
    }
    $record = get_user_meta($user_id, 'eintrikot_consent_record', true);
    // Accounts from before this rule (no record) keep the normal rules.
    return !is_array($record) || !empty($record['directory']) || ($record['method'] ?? '') === 'manual';
}

/* ---------- Oberfläche ---------- */

add_action('wp_enqueue_scripts', function () {
    if (current_view() === 'consent') {
        wp_enqueue_script(
            'eintrikot-consent',
            plugins_url('consent.js', __FILE__),
            [],
            asset_version('consent.js'),
            true
        );
    }
});
