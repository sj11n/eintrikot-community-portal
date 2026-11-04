<?php
/**
 * Newsletter-Einwilligung: Wer den Newsletter bekommt, entscheidet allein das Mitglied. Drei Zustände:
 * "nicht beantwortet" (Ausgangszustand), "Ja" und "Nein". Nur "Ja" zählt als Einwilligung.
 *
 * Gespeichert wird im Benutzermeta `eintrikot_newsletter`: Zustand, Zeitpunkt, Fassung des Einwilligungstextes und
 * die Zeit, bis zu der die Frage ausgeblendet bleibt ("Später"). Jede Änderung geht in den Änderungsverlauf.
 *
 * Das Portal fragt, bis es eine Antwort gibt: ein ruhiger Hinweis oben im Portal, "Später" blendet ihn 14 Tage aus.
 * Nach einem "Nein" wird nach 12 Monaten einmal im Portal nachgefragt (nie per E-Mail). Unter 18 wird nicht gefragt
 * und es gibt keinen Newsletter. Die Einwilligung kann nur das Mitglied selbst ändern, nicht die Verwaltung.
 */
namespace Eintrikot\Community;
if (!defined('ABSPATH')) {
    exit();
}

/** Version of the consent wording. Raise it whenever the text below changes. */
const NEWSLETTER_TEXT_VERSION = 1;

/** Days a postponed question stays hidden (unanswered) and days until the question comes back after a "No". */
function newsletter_snooze_days() {
    return max(1, (int) apply_filters('eintrikot_newsletter_snooze_days', 14));
}
function newsletter_retry_days() {
    return max(1, (int) apply_filters('eintrikot_newsletter_retry_days', 365));
}

/** The wording the member agrees to. A draft until the board has checked it. */
function newsletter_wording() {
    return 'Ich möchte den EINTRIKOT-Newsletter ungefähr viermal im Jahr per E-Mail erhalten. Ich kann das jederzeit im Profil oder über den Abmeldelink in jeder E-Mail widerrufen.';
}

function newsletter_state_label($state) {
    return ['yes' => 'Ja', 'no' => 'Nein'][$state] ?? 'nicht beantwortet';
}

/** @return array{state: string, at: int, version: int, snooze_until: int} */
function newsletter_consent($user_id) {
    $raw = get_user_meta((int) $user_id, 'eintrikot_newsletter', true);
    $raw = is_array($raw) ? $raw : [];
    $state = $raw['state'] ?? '';
    return [
        'state' => in_array($state, ['yes', 'no'], true) ? $state : '',
        'at' => (int) ($raw['at'] ?? 0),
        'version' => (int) ($raw['version'] ?? 0),
        'snooze_until' => (int) ($raw['snooze_until'] ?? 0)
    ];
}

/** The newsletter is for adults only; accounts without a birthday are treated as adults like everywhere else. */
function newsletter_allowed($user_id) {
    return !is_minor((int) $user_id);
}

/** Stores the member's answer. Only "yes" and "no" are valid; returns false if nothing could be saved. */
function newsletter_set_consent($user_id, $state, $reason = 'Newsletter-Einstellung') {
    $user_id = (int) $user_id;
    if (!in_array($state, ['yes', 'no'], true) || !newsletter_allowed($user_id)) {
        return false;
    }
    $before = newsletter_consent($user_id);
    if ($before['state'] === $state) {
        return true;
    }
    $saved = update_user_meta($user_id, 'eintrikot_newsletter', [
        'state' => $state,
        'at' => time(),
        'version' => NEWSLETTER_TEXT_VERSION,
        'snooze_until' => 0
    ]);
    if ($saved === false) {
        return false;
    }
    log_change(
        $user_id,
        'newsletter',
        newsletter_state_label($before['state']),
        newsletter_state_label($state),
        $reason . ' (Text Version ' . NEWSLETTER_TEXT_VERSION . ')'
    );
    return true;
}

/** "Later": hides the question for a while. A "No" is asked again only after the long period. */
function newsletter_postpone($user_id) {
    $user_id = (int) $user_id;
    $current = newsletter_consent($user_id);
    if ($current['state'] === 'yes') {
        return false;
    }
    $days = $current['state'] === 'no' ? newsletter_retry_days() : newsletter_snooze_days();
    $current['snooze_until'] = time() + $days * DAY_IN_SECONDS;
    return update_user_meta($user_id, 'eintrikot_newsletter', $current) !== false;
}

/** Whether the question is shown to this member now. */
function newsletter_prompt_due($user_id) {
    $user_id = (int) $user_id;
    if ($user_id < 1 || !newsletter_allowed($user_id)) {
        return false;
    }
    $c = newsletter_consent($user_id);
    $now = time();
    if ($c['state'] === 'yes' || $c['snooze_until'] > $now) {
        return false;
    }
    return $c['state'] === '' || $c['at'] + newsletter_retry_days() * DAY_IN_SECONDS <= $now;
}

/** Whether this account gets the newsletter now: "Yes", adult, portal access and a usable address. */
function newsletter_receives($user_id) {
    $user = get_userdata((int) $user_id);
    return $user instanceof \WP_User &&
        user_can($user, 'eintrikot_portal') &&
        newsletter_consent($user->ID)['state'] === 'yes' &&
        newsletter_allowed($user->ID) &&
        mail_list_usable($user->user_email) !== '';
}

/**
 * Members who said "Yes" and are adults, as [user ID => address], sorted by address. Same address rules as
 * the mail list; an address used by two accounts appears once.
 *
 * @return array<int, string>
 */
function newsletter_recipient_ids() {
    $mails = [];
    foreach (
        get_users(['capability' => 'eintrikot_portal', 'number' => -1, 'fields' => ['ID', 'user_email']])
        as $user
    ) {
        $mail = mail_list_usable($user->user_email);
        if (
            $mail !== '' &&
            !in_array($mail, $mails, true) &&
            newsletter_consent((int) $user->ID)['state'] === 'yes' &&
            newsletter_allowed((int) $user->ID)
        ) {
            $mails[(int) $user->ID] = $mail;
        }
    }
    asort($mails);
    return $mails;
}

/** Addresses of all members who said "Yes" and are adults. Same address rules as the mail list. */
function newsletter_recipients() {
    return array_values(newsletter_recipient_ids());
}

/** The notice at the top of the portal while the question is open. */
function newsletter_prompt_bar() {
    if (!member_access() || !newsletter_prompt_due(get_current_user_id())) {
        return '';
    }
    $again = newsletter_consent(get_current_user_id())['state'] === 'no';
    $button = static function ($choice, $label, $class) {
        return '<button class="' .
            esc_attr($class) .
            '" type="submit" name="choice" value="' .
            esc_attr($choice) .
            '">' .
            esc_html($label) .
            '</button>';
    };
    return '<section class="newsletter-bar" aria-labelledby="newsletter-bar-title"><div><h2 id="newsletter-bar-title">' .
        ($again ? 'Magst du den Newsletter doch bekommen?' : 'Newsletter per E-Mail?') .
        '</h2><p>' .
        esc_html(newsletter_wording()) .
        '</p></div><form method="post" action="' .
        esc_url(admin_url('admin-post.php')) .
        '"><input type="hidden" name="action" value="et_newsletter">' .
        wp_nonce_field('et_newsletter', '_wpnonce', true, false) . // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress core builds the hidden nonce field
        $button('yes', 'Ja, gern', 'button solid') .
        $button('no', 'Nein, danke', 'button') .
        $button('later', 'Später', 'text-link') .
        '</form></section>';
}

add_action('admin_post_et_newsletter', function () {
    if (!member_access()) {
        wp_die('Keine Berechtigung.', '', ['response' => 403]);
    }
    check_admin_referer('et_newsletter');
    $choice = post_choice('choice', ['yes', 'no', 'later']);
    if ($choice === 'later') {
        newsletter_postpone(get_current_user_id());
    } elseif ($choice !== '') {
        newsletter_set_consent(get_current_user_id(), $choice, 'Antwort auf die Frage im Portal');
    }
    wp_safe_redirect(wp_get_referer() ?: portal_url());
    exit();
});

/** The choice in the own profile. Others see only the status. */
function newsletter_profile_section($user_id) {
    $user_id = (int) $user_id;
    $own = $user_id === get_current_user_id();
    $c = newsletter_consent($user_id);
    $head = '<section class="form-section"><h2>In Verbindung bleiben</h2>';
    if (!newsletter_allowed($user_id)) {
        return $head . '<p>Den Newsletter gibt es ab 18 Jahren.</p></section>';
    }
    if (!$own) {
        return $head .
            '<p>EINTRIKOT-Newsletter: <strong>' .
            esc_html(newsletter_state_label($c['state'])) .
            '</strong>' .
            ($c['at'] ? ' (seit ' . esc_html(wp_date('d.m.Y', $c['at'])) . ')' : '') .
            '. Das entscheidet nur das Mitglied selbst.</p></section>';
    }
    $radio = static function ($value, $label) use ($c) {
        return '<label class="check"><input type="radio" name="newsletter_consent" value="' .
            esc_attr($value) .
            '" ' .
            checked($c['state'], $value, false) .
            '><span>' .
            esc_html($label) .
            '</span></label>';
    };
    return $head .
        '<p>' .
        esc_html(newsletter_wording()) .
        '</p>' .
        $radio('yes', 'Ja, ich möchte den Newsletter erhalten') .
        $radio('no', 'Nein, ich möchte ihn nicht erhalten') .
        ($c['state'] === ''
            ? '<p><small>Du hast noch nicht geantwortet. Bis dahin bekommst du den Newsletter nicht.</small></p>'
            : '<p><small>Gespeichert' .
                ($c['at'] ? ' am ' . esc_html(wp_date('d.m.Y', $c['at'])) : '') .
                '. Du kannst das jederzeit ändern.</small></p>') .
        '</section>';
}
