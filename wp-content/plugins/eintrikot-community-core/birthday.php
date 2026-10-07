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
 * Messages of the birthday page. The address carries only the key, never the text.
 * The parameter is called "fehler": WordPress deletes "error" from the query (it is reserved for 404 handling).
 */
function birthday_errors() {
    return [
        'invalid' =>
            'Bitte ein gültiges Datum eingeben (Tag, Monat und Jahr), das nicht in der Zukunft liegt.',
        'minor' =>
            'Mit diesem Datum bist du noch unter 18. Dafür brauchen wir die Zustimmung deiner Eltern. Bitte schreib uns an info@eintrikot.de, dann klären wir das gemeinsam.'
    ];
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
    echo page_head('Noch eine Angabe', 'Ein Datum fehlt noch, dann geht es los.', '', 'portal');
    if (isset($messages[$error])) {
        echo '<div class="form-error" role="alert" tabindex="-1"><p>' .
            esc_html($messages[$error]) .
            '</p></div>';
    }
    echo '<form class="et-form birthday-form" method="post" action="' .
        esc_url(admin_url('admin-post.php')) .
        '">';
    wp_nonce_field('et_birthday');
    echo '<input type="hidden" name="action" value="et_birthday">' .
        '<label class="field" for="et-birthday">Dein Geburtsdatum<input id="et-birthday" name="birthday" type="date" required autocomplete="bday" min="1900-01-01" max="' .
        esc_attr(wp_date('Y-m-d')) .
        '"' .
        ($error !== '' ? ' aria-invalid="true"' : '') .
        '></label>' .
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
    $result = save_member_birthday(get_current_user_id(), post_text('birthday', '', 10));
    wp_safe_redirect(
        $result === '' ? portal_url('portal', ['saved' => 1]) : portal_url('birthday', ['fehler' => $result])
    );
    exit();
});
