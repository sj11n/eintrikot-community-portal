<?php
/**
 * Geburtsdatum nachfragen: Wer ein Mitgliedskonto hat, aber noch kein gültiges Geburtsdatum, sieht nach der Anmeldung
 * zuerst eine eigene Seite mit genau einer Frage. Das Datum bestimmt den Mitgliedsbeitrag (bis 31 beitragsfrei, ab 32
 * 50 € im Jahr) und die Jugendregeln. Die Seite erscheint, bis es eingetragen ist; danach nie wieder.
 *
 * Bestehende Geburtsdaten lassen sich hier nicht überschreiben (das geht nur im Profil). Wer mit dem Datum unter 18
 * wäre, bekommt einen Hinweis und nichts wird gespeichert: dafür braucht es die Zustimmung der Eltern.
 */
namespace Eintrikot\Community;
if (!defined('ABSPATH')) {
    exit();
}

/**
 * Messages of the birthday page. The address carries only the key, never the text (nor the date).
 * The parameter is called "fehler": WordPress deletes "error" from the query (it is reserved for 404 handling).
 */
function birthday_errors() {
    return [
        'invalid' =>
            'Bitte ein gültiges Datum eingeben (Tag, Monat und Jahr), das nicht in der Zukunft liegt.',
        'empty' => 'Bitte dein Geburtsdatum eingeben: Tag, Monat und Jahr.',
        'day' => 'Bitte den Tag als Zahl eingeben, zum Beispiel 14.',
        'month' => 'Bitte den Monat auswählen.',
        'year' => 'Bitte das Jahr vierstellig eingeben, zum Beispiel 1985.',
        'nodate' => 'Diesen Tag gibt es im gewählten Monat nicht.',
        'future' => 'Das Datum liegt in der Zukunft. Bitte prüfe das Jahr.',
        'old' => 'Bitte ein Jahr ab 1900 eingeben.',
        'minor' =>
            'Mit diesem Datum bist du noch unter 18. Dafür brauchen wir die Zustimmung deiner Eltern. Bitte schreib uns an info@eintrikot.de, dann klären wir das gemeinsam.'
    ];
}

/* ---------- Tag, Monat, Jahr ---------- */

function birthday_month_names() {
    return [
        1 => 'Januar',
        'Februar',
        'März',
        'April',
        'Mai',
        'Juni',
        'Juli',
        'August',
        'September',
        'Oktober',
        'November',
        'Dezember'
    ];
}

/** ['day', 'month', 'year'] from a stored date "Y-m-d" (or from parts kept after a failed save). */
function birthday_parts($value) {
    if (is_array($value)) {
        return [
            'day' => mb_substr((string) ($value['day'] ?? ''), 0, 4),
            'month' => mb_substr((string) ($value['month'] ?? ''), 0, 2),
            'year' => mb_substr((string) ($value['year'] ?? ''), 0, 6)
        ];
    }
    if (is_string($value) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $m)) {
        return ['day' => (string) (int) $m[3], 'month' => (string) (int) $m[2], 'year' => $m[1]];
    }
    return ['day' => '', 'month' => '', 'year' => ''];
}

/** The three parts as sent by the form. A single "Y-m-d" value (older form) is read too. */
function birthday_parts_from_post($name) {
    // Callers verify the nonce before reading form values.
    $raw = $_POST[$name] ?? null; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- cleaned right below
    if (is_scalar($raw)) {
        return birthday_parts(trim(wp_unslash((string) $raw)));
    }
    $parts = [];
    foreach (['day', 'month', 'year'] as $key) {
        $v = is_array($raw) ? $raw[$key] ?? '' : '';
        $parts[$key] = is_scalar($v) ? trim(sanitize_text_field(wp_unslash((string) $v))) : '';
    }
    return birthday_parts($parts);
}

/**
 * Checks the parts. Returns [date "Y-m-d" or '', error key of birthday_errors() or ''].
 * Nothing filled in is no error unless $required.
 */
function birthday_from_parts($parts, $required = true) {
    $day = $parts['day'] ?? '';
    $month = $parts['month'] ?? '';
    $year = $parts['year'] ?? '';
    if ($day === '' && $month === '' && $year === '') {
        return ['', $required ? 'empty' : ''];
    }
    if (!preg_match('/^\d{1,2}$/D', $day) || (int) $day < 1 || (int) $day > 31) {
        return ['', 'day'];
    }
    if (!preg_match('/^\d{1,2}$/D', $month) || (int) $month < 1 || (int) $month > 12) {
        return ['', 'month'];
    }
    if (!preg_match('/^\d{4}$/D', $year)) {
        return ['', 'year'];
    }
    if (!checkdate((int) $month, (int) $day, (int) $year)) {
        return ['', 'nodate'];
    }
    $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
    if ($date < '1900-01-01') {
        return ['', 'old'];
    }
    if ($date > wp_date('Y-m-d')) {
        return ['', 'future'];
    }
    return [$date, ''];
}

/** The message for an error key; "nodate" names the day and month ("Den 31. April gibt es nicht."). */
function birthday_error_text($key, $parts = []) {
    $months = birthday_month_names();
    if ($key === 'nodate' && isset($months[(int) ($parts['month'] ?? 0)])) {
        return 'Den ' .
            (int) $parts['day'] .
            '. ' .
            $months[(int) $parts['month']] .
            ' gibt es nicht. Bitte Tag und Monat prüfen.';
    }
    return birthday_errors()[$key] ?? birthday_errors()['invalid'];
}

/** Which of the three fields an error points at ('' = the whole date). */
function birthday_error_field($key) {
    return [
        'day' => 'day',
        'nodate' => 'day',
        'month' => 'month',
        'year' => 'year',
        'future' => 'year',
        'old' => 'year'
    ][$key] ?? '';
}

/**
 * Tag, Monat and Jahr as one group of fields (no calendar: on many devices it only moves month by month).
 * $args: label, hint, required, disabled, error (text), bad ('day', 'month', 'year' or '' for all), note (HTML).
 */
function birthday_date_fields($name, $value, $args = []) {
    $args += [
        'label' => 'Geburtsdatum',
        'hint' => 'Zum Beispiel 14 · März · 1985',
        'required' => false,
        'disabled' => false,
        'error' => '',
        'bad' => '',
        'note' => ''
    ];
    $parts = birthday_parts($value);
    $id = 'et-' . sanitize_html_class($name);
    $described = $id . '-hint' . ($args['error'] !== '' ? ' ' . $id . '-error' : '');
    $attrs = function ($part) use ($args) {
        return ($args['required'] ? ' required' : '') .
            ($args['disabled'] ? ' disabled' : '') .
            ($args['error'] !== '' && ($args['bad'] === '' || $args['bad'] === $part)
                ? ' aria-invalid="true"'
                : '');
    };
    $html =
        '<fieldset class="field date-parts" aria-describedby="' .
        esc_attr($described) .
        '"><legend>' .
        esc_html($args['label']) .
        '</legend><span class="field-hint" id="' .
        esc_attr($id . '-hint') .
        '">' .
        esc_html($args['hint']) .
        '</span><div class="date-parts-row"><label class="date-day">Tag<input id="' .
        esc_attr($id . '-day') .
        '" name="' .
        esc_attr($name) .
        '[day]" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="2" autocomplete="bday-day" placeholder="TT" value="' .
        esc_attr($parts['day']) .
        '"' .
        $attrs('day') .
        '></label><label class="date-month">Monat<select name="' .
        esc_attr($name) .
        '[month]" autocomplete="bday-month"' .
        $attrs('month') .
        '><option value="">Bitte wählen</option>';
    foreach (birthday_month_names() as $n => $month) {
        $html .=
            '<option value="' .
            $n .
            '"' .
            selected($parts['month'], (string) $n, false) .
            '>' .
            esc_html($month) .
            '</option>';
    }
    $html .=
        '</select></label><label class="date-year">Jahr<input name="' .
        esc_attr($name) .
        '[year]" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="4" autocomplete="bday-year" placeholder="JJJJ" value="' .
        esc_attr($parts['year']) .
        '"' .
        $attrs('year') .
        '></label></div>' .
        ($args['error'] !== ''
            ? '<span class="field-error" id="' .
                esc_attr($id . '-error') .
                '">' .
                esc_html($args['error']) .
                '</span>'
            : '') .
        $args['note'] .
        '</fieldset>';
    return $html;
}

/** What was typed on the birthday page before an error, so nothing has to be entered twice. */
function birthday_draft_key($user_id) {
    return 'et_birthday_draft_' . (int) $user_id;
}

/** Member accounts (with a member number) that still have no valid birthday. */
function birthday_needed($user_id) {
    $user_id = (int) $user_id;
    return $user_id > 0 &&
        (string) get_user_meta($user_id, 'eintrikot_member_number', true) !== '' &&
        member_birthday(profile_data($user_id)) === null;
}

/** Saves the birthday if there is none yet. Returns '' on success or a key of birthday_errors(). */
function save_member_birthday($user_id, $date) {
    $date = trim((string) $date);
    $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, wp_timezone());
    if (!$parsed || $parsed->format('Y-m-d') !== $date || $date < '1900-01-01' || $date > wp_date('Y-m-d')) {
        return 'invalid';
    }
    if (is_minor_data(['birthday' => $date])) {
        return 'minor';
    }
    $data = profile_data($user_id);
    if (member_birthday($data) !== null) {
        return ''; // There is one already: it changes only in the profile.
    }
    $data['birthday'] = $date;
    if (update_user_meta($user_id, 'eintrikot_profile', wp_slash($data)) === false) {
        return 'invalid';
    }
    clean_user_cache($user_id);
    log_change($user_id, 'birthday', null, $date, 'Geburtsdatum nach der Anmeldung ergänzt');
    return '';
}

function render_birthday_page() {
    $error = directory_param('fehler');
    $messages = birthday_errors();
    $draft = get_transient(birthday_draft_key(get_current_user_id()));
    $parts = is_array($draft) ? $draft : '';
    $text = isset($messages[$error]) ? birthday_error_text($error, is_array($draft) ? $draft : []) : '';
    echo page_head('Noch eine Angabe', 'Ein Datum fehlt noch, dann geht es los.', '', 'portal');
    if ($text !== '') {
        echo '<div class="form-error" role="alert" tabindex="-1"><p>' . esc_html($text) . '</p></div>';
    }
    echo '<form class="et-form birthday-form" method="post" action="' .
        esc_url(admin_url('admin-post.php')) .
        '">';
    wp_nonce_field('et_birthday');
    echo '<input type="hidden" name="action" value="et_birthday">' .
        birthday_date_fields('birthday', $parts, [
            'label' => 'Dein Geburtsdatum',
            'required' => true,
            'error' => $text !== '' && $error !== 'minor' ? $text : '',
            'bad' => birthday_error_field($error)
        ]) .
        '<div class="visibility-intro"><p><strong>Warum fragen wir danach?</strong> Dein Geburtsdatum bestimmt deinen Mitgliedsbeitrag: Bis einschließlich 31 Jahre bist du beitragsfrei, ab 32 Jahren beträgt der Beitrag 50 € im Jahr. Andere Mitglieder sehen es nicht. Später kannst du in deinem Profil selbst wählen, ob dein Geburtstag für Mitglieder sichtbar sein soll, mit oder ohne Jahr.</p></div>' .
        '<button class="button solid" type="submit">Speichern und weiter</button>' .
        '<p class="minor-note">Fragen dazu? Schreib uns an <a class="text-link" href="mailto:info@eintrikot.de">info@eintrikot.de</a>.</p>' .
        '</form>';
}

add_action('admin_post_et_birthday', function () {
    if (!member_access()) {
        wp_die('Keine Berechtigung.', '', ['response' => 403]);
    }
    check_admin_referer('et_birthday');
    $user_id = get_current_user_id();
    $parts = birthday_parts_from_post('birthday');
    [$date, $result] = birthday_from_parts($parts, true);
    if ($result === '') {
        $result = save_member_birthday($user_id, $date);
    }
    if ($result === '') {
        delete_transient(birthday_draft_key($user_id));
    } else {
        set_transient(birthday_draft_key($user_id), $parts, 15 * MINUTE_IN_SECONDS);
    }
    wp_safe_redirect(
        $result === '' ? portal_url('portal', ['saved' => 1]) : portal_url('birthday', ['fehler' => $result])
    );
    exit();
});
