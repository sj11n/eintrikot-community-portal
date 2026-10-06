<?php
/**
 * Einmalige Übernahme der Profilangaben aus NDAlumni (Umstieg der Bestandsmitglieder).
 *
 * Grundsätze:
 * - Konten entstehen weiter nur über den MeinVerein-Import. Hier werden bestehende Konten
 *   über die E-Mail-Adresse gefunden und nur leere Felder ergänzt; was im Portal schon
 *   steht, bleibt unverändert. Ein zweiter Durchlauf ist deshalb harmlos.
 * - Alles Übernommene ist privat (nur Mitglied und Mitgliederverwaltung). Das Mitglied
 *   prüft sein Profil und entscheidet pro Abschnitt, was andere sehen.
 * - Nicht übernommen: Bank- und Mandatsdaten, Anschrift, Telefon, Geburtsname, Notizen,
 *   Newsletter-Zustimmung (im Portal neu per Opt-in), Tarif und Beitrag.
 */
namespace Eintrikot\Community;
if (!defined('ABSPATH')) {
    exit();
}

/** Clean cell text; placeholders of the NDAlumni export count as empty. */
function nda_text($value) {
    $v = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $v = trim(preg_replace('/\s+/u', ' ', $v));
    return in_array(mb_strtolower($v), ['', '---', '--', '-', '?', 'nein', 'keins', 'keine', 'k.a.'], true)
        ? ''
        : $v;
}
/** A year from "2003", an Excel day number or a date; '' otherwise. */
function nda_year($value) {
    $v = trim((string) $value);
    if (preg_match('/^(19|20)\d{2}$/D', $v)) {
        return $v;
    }
    if (preg_match('/^\d{5}(\.\d+)?$/D', $v)) {
        return gmdate('Y', (int) round(((float) $v - 25569) * 86400));
    }
    $date = import_date($v);
    return $date !== '' ? substr($date, 0, 4) : '';
}
/** "Damen"/"Herren" from a team or position text, else from the salutation (then guessed). */
function nda_side($text, $salutation) {
    $t = mb_strtolower($text);
    if (preg_match('/damen|frauen|mädchen|torhüterin|stürmerin|\bw\s?u?\d|u\d+\s?w\b|\bw\d{2}/u', $t)) {
        return ['Damen', false];
    }
    if (preg_match('/herren|männer|jungen|\bm\s?u?\d|u\d+\s?m\b|\bm\d{2}/u', $t)) {
        return ['Herren', false];
    }
    return [$salutation === 'Frau' ? 'Damen' : 'Herren', true];
}
/** Portal role for an NDAlumni station. */
function nda_role($position, $team) {
    $p = mb_strtolower($position);
    $map = [
        'Co-Trainer/in' => '/co-?trainer|assistenz/u',
        'Torwarttrainer/in' => '/torwart\w*trainer/u',
        'Athletiktrainer/in' => '/athletik/u',
        'Trainer/in' => '/trainer|\bbt\b/u',
        'Teammanager/in' => '/team\s?manager/u',
        'Physiotherapeut/in' => '/physio|osteopath/u',
        'Mannschaftsarzt/-ärztin' => '/arzt|ärztin/u',
        'Video- und Spielanalyse' => '/video|analys/u',
        'Betreuer/in' => '/delegation|betreuer/u',
        'Kapitän/in' => '/kapitän/u'
    ];
    foreach ($map as $role => $re) {
        if (preg_match($re, $p)) {
            return $role;
        }
    }
    return trim($team) === 'Staff' ? 'Betreuer/in' : 'Spieler/in';
}
/** Field position in our words; '' when the text names a role instead. */
function nda_position($position) {
    $p = mb_strtolower($position);
    if (
        $p === '' ||
        preg_match('/trainer|\bbt\b|manager|physio|osteopath|arzt|delegation|betreuer|halle|^li\.?$/u', $p)
    ) {
        return '';
    }
    $found = [];
    foreach (
        [
            'Tor' => '/\btw\b|^tor$|torwart|torhüter|goalie|keeper/u',
            'Abwehr' => '/abwehr|verteidig|defen[cs]e|libero/u',
            'Mittelfeld' => '/mittel?feld|midfield/u',
            'Sturm' => '/sturm|stürm|strum|rechtsaußen|linksaußen|forward/u'
        ]
        as $label => $re
    ) {
        if (preg_match($re, $p)) {
            $found[] = $label;
        }
    }
    return $found ? implode(' / ', $found) : mb_substr($position, 0, 60);
}
/** Comma list, keeping commas inside brackets ("Soziale Events (Stammtisch, Reunions)"). */
function nda_items($value) {
    $out = [];
    $depth = 0;
    $cur = '';
    foreach (mb_str_split(nda_text($value)) as $ch) {
        $depth += $ch === '(' ? 1 : ($ch === ')' ? -1 : 0);
        if ($ch === ',' && $depth <= 0) {
            $out[] = trim($cur);
            $cur = '';
        } else {
            $cur .= $ch;
        }
    }
    $out[] = trim($cur);
    return array_values(array_filter($out, fn($part) => $part !== ''));
}

function nda_note(array &$notes, $text) {
    $notes[$text] = ($notes[$text] ?? 0) + 1;
}

/** Rows of the export as [normalized header => value]. */
function nda_records($rows) {
    $header = 0;
    foreach (array_slice($rows, 0, 10) as $i => $row) {
        $n = array_map('Eintrikot\Community\normalize_header', $row);
        if (
            in_array('bevorzugteemailadresse', $n, true) ||
            in_array('hockeylebenslauf1mannschaft', $n, true)
        ) {
            $header = $i;
            break;
        }
    }
    $keys = array_map('Eintrikot\Community\normalize_header', $rows[$header] ?? []);
    $out = [];
    foreach (array_slice($rows, $header + 1) as $row) {
        $rec = [];
        foreach ($keys as $i => $k) {
            if ($k !== '' && !isset($rec[$k])) {
                $rec[$k] = (string) ($row[$i] ?? '');
            }
        }
        if (array_filter($rec, fn($v) => trim($v) !== '')) {
            $out[] = $rec;
        }
    }
    return [$keys, $out];
}

/** Portal profile values from one NDAlumni record, plus notes on what could not be taken. */
function nda_profile($r, &$notes) {
    $g = fn($k) => nda_text($r[$k] ?? '');
    $salutation = $g('anrede');
    $d = [];
    $set = function ($key, $value, $max = 200) use (&$d) {
        if ($value !== '') {
            $d[$key] = mb_substr(sanitize_text_field($value), 0, $max);
        }
    };
    $set('city', $g('stadtprivat'));
    if ($g('landprivat') !== '' && $g('landprivat') !== 'Deutschland') {
        $set('region', $g('landprivat'));
    }
    $set('club', $g('aktuellesvereinsteam'));
    $team = $g('mannschaft');
    if ($team !== '' && $team !== 'Staff') {
        $d['team'] = nda_side($team, $salutation)[0];
    } elseif ($salutation !== '') {
        $d['team'] = $salutation === 'Frau' ? 'Damen' : 'Herren';
        nda_note($notes, 'Team aus der Anrede abgeleitet');
    }
    $t = str_replace(['mU', 'wU'], 'U', $team);
    foreach (['U16', 'U18', 'U21'] as $age) {
        if (str_contains($t, $age)) {
            $d['age_class'] = $age;
        }
    }
    if (str_contains($team, 'A-Kader')) {
        $d['age_class'] = 'A-Nationalteam';
    } elseif (str_starts_with($team, 'Masters')) {
        $d['age_class'] = 'Masters';
    }
    $now = mb_strtolower((string) ($r['aktuellindernatio'] ?? ''));
    if (in_array($now, ['ja', 'nein'], true)) {
        $d['phase'] = $now === 'ja' ? 'Aktiv' : 'Alumni';
    }
    $set('caps', $g('anzahlvonnationalspielen'), 60);
    $set('other_sport', $g('sportwennnichthockeydann'));
    $set('job', $g('aktuelleposition'));
    $set('employer', $g('arbeitgeber'));
    $set('industry', $g('branche'));
    $status = ['Rentner' => 'Im Ruhestand', 'Student' => 'Studium', 'Sonstiges' => 'Sonstiges'];
    if (isset($status[$g('berufsstatus')])) {
        $d['employment'] = $status[$g('berufsstatus')];
    } elseif ($g('berufsstatus') === 'Berufstätig') {
        nda_note($notes, '„Berufstätig“: Status bleibt zur Auswahl offen');
    }
    if (!in_array($g('akademischedaten1abschluss'), ['', 'Abschluss'], true)) {
        $set('degree', $g('akademischedaten1abschluss'));
    }
    $set('subject', $g('akademischedaten1programm'));
    $set('university', $g('akademischedaten1schuleuniversitatinstitut'));
    $period = array_filter([
        nda_year($r['akademischedaten1von'] ?? ''),
        nda_year($r['akademischedaten1bis'] ?? '')
    ]);
    if ($period) {
        $d['education_period'] = implode('–', array_unique($period));
    }
    foreach ([2, 3, 4, 5] as $n) {
        if (!in_array($g('akademischedaten' . $n . 'abschluss'), ['', 'Abschluss', 'ja'], true)) {
            nda_note($notes, 'Weitere Ausbildung nicht übernommen (nur die erste)');
            break;
        }
    }
    // DHB-Vita
    $stations = [];
    for ($n = 1; $n <= 5; $n++) {
        $unit = nda_text($r['hockeylebenslauf' . $n . 'mannschaft'] ?? '');
        if ($unit === '') {
            continue;
        }
        $raw = nda_text($r['hockeylebenslauf' . $n . 'position'] ?? '');
        [$side, $guessed] = nda_side($raw, $salutation);
        $role = nda_role($raw, $unit);
        if ($guessed && $role !== 'Spieler/in') {
            nda_note($notes, 'Station: Damen/Herren bei Staff oder Trainer geraten');
        }
        $age =
            [
                'A-Kader' => 'A-Nationalteam',
                'Masters' => 'Masters',
                'U16' => 'U16',
                'U18' => 'U18',
                'U21' => 'U21'
            ][trim($unit)] ?? '';
        if ($age === '') {
            nda_note($notes, 'Station ohne Altersklasse (' . trim($unit) . ')');
        }
        $from = nda_year($r['hockeylebenslauf' . $n . 'von'] ?? '');
        $to = nda_year($r['hockeylebenslauf' . $n . 'bis'] ?? '');
        if ($to !== '' && ($from === '' || (int) $to < (int) $from)) {
            $to = '';
            nda_note($notes, 'Station: Endjahr verworfen (vor oder ohne Beginn)');
        }
        $row = ['role' => $role, 'organisation' => $side, 'age_class' => $age, 'from' => $from, 'to' => $to];
        $position = $role === 'Spieler/in' ? nda_position($raw) : '';
        if ($position !== '') {
            $row = ['role' => $role, 'position' => $position] + $row;
        }
        $stations[] = $row;
    }
    if ($stations) {
        $valid = validate_stations($stations);
        if (is_wp_error($valid)) {
            nda_note($notes, 'DHB-Vita nicht übernommen (' . $valid->get_error_message() . ')');
        } else {
            $d['stations'] = $valid;
        }
    }
    // Mentoring: known NDAlumni answers become checkboxes, the rest stays as text.
    $offer_map = [
        'Teilen von Tipps und Karriere Informationen' => ['career'],
        'Job/Praktika Stellen in meinem Unternehmen' => ['jobs', 'internship'],
        'Unterstützung bei Karriere Orientierungsprojekten (z.B. 1 Tages Praktikum)' => ['internship']
    ];
    $seek_map = [
        'Mentoring (Enge Verbindung aufbauen; Mentor sein oder auf Mentorsuche)' => ['mentoring'],
        'Jobbörse (Suche/Recruiting; Praktika/Jobs)' => ['jobs'],
        'Karriere Orientierung/Reorientierung und Bewerbungshilfe' => ['career'],
        'Tips aus der Berufswelt' => ['career']
    ];
    $offer = [];
    $rest = [];
    foreach (nda_items($r['unterstutzung'] ?? '') as $item) {
        isset($offer_map[$item]) ? array_push($offer, ...$offer_map[$item]) : ($rest[] = $item);
    }
    $seek = [];
    $interests = [];
    foreach (nda_items($r['interessen'] ?? '') as $item) {
        isset($seek_map[$item]) ? array_push($seek, ...$seek_map[$item]) : ($interests[] = $item);
    }
    if ($offer) {
        $d['mentoring_offer'] = array_values(array_unique($offer));
    }
    if ($seek) {
        $d['mentoring_seek'] = array_values(array_unique($seek));
    }
    if ($rest) {
        $d['contribution'] = mb_substr(sanitize_textarea_field(implode(', ', $rest)), 0, 2000);
    }
    if ($interests) {
        $d['support'] = mb_substr(
            sanitize_textarea_field('Interessiert an: ' . implode(', ', $interests)),
            0,
            2000
        );
    }
    foreach (
        ['linkedin' => 'linkedin', 'facebook' => 'facebook', 'instagram' => 'instagram']
        as $key => $col
    ) {
        $raw = $g($col);
        if ($raw === '') {
            continue;
        }
        $url = social_url($key, $raw);
        if ($url) {
            $d[$key] = $url;
        } else {
            nda_note(
                $notes,
                'Social-Media-Link weggelassen (keine Adresse von ' . social_networks()[$key][0] . ')'
            );
        }
    }
    $birthday = import_date($r['geburtsdatum'] ?? '');
    if ($birthday !== '' && $birthday >= '1900-01-01' && $birthday <= wp_date('Y-m-d')) {
        $d['birthday'] = $birthday;
    }
    return $d;
}

/**
 * Portal values per person from the export, before looking at accounts:
 * people => [emails, name, data, donation (cents or empty)], notes => counts.
 */
function nda_map($rows) {
    [, $records] = nda_records($rows);
    $out = ['people' => [], 'notes' => []];
    foreach ($records as $r) {
        $emails = [];
        foreach (['bevorzugteemailadresse', 'emailadresseprivat', 'emailgeschaftlich'] as $col) {
            $email = strtolower(sanitize_email($r[$col] ?? ''));
            if ($email !== '') {
                $emails[] = $email;
            }
        }
        $donation = str_replace(',', '.', nda_text($r['zusatzbeitrag'] ?? ''));
        $out['people'][] = [
            'emails' => array_values(array_unique($emails)),
            'name' => trim(nda_text($r['vorname'] ?? '') . ' ' . nda_text($r['nachname'] ?? '')),
            'data' => nda_profile($r, $out['notes']),
            'donation' => preg_match('/^\d{1,6}(?:\.\d{1,2})?$/D', $donation)
                ? (string) (int) round((float) $donation * 100)
                : ''
        ];
    }
    return $out;
}

/** Which accounts get which fields. Only empty portal fields are filled. */
function nda_plan($mapped) {
    $plan = ['users' => [], 'unmatched' => [], 'fields' => [], 'donations' => 0, 'donations_paid' => 0];
    foreach ($mapped['people'] as $person) {
        $user = null;
        foreach ($person['emails'] as $email) {
            if ($user = get_user_by('email', $email)) {
                break;
            }
        }
        if (!$user) {
            $plan['unmatched'][] = $person['name'] ?: '(ohne Namen)';
            continue;
        }
        $current = profile_data($user->ID);
        $fill = [];
        foreach ($person['data'] as $key => $value) {
            $has = $current[$key] ?? null;
            if ($has === null || $has === '' || $has === []) {
                $fill[$key] = $value;
                $plan['fields'][$key] = ($plan['fields'][$key] ?? 0) + 1;
            }
        }
        $cents = $person['donation'];
        if ($cents !== '' && member_donation($user->ID) === '') {
            $plan['donations']++;
            $plan['donations_paid'] += (int) $cents > 0 ? 1 : 0;
        } else {
            $cents = '';
        }
        $plan['users'][$user->ID] = ['fill' => $fill, 'donation' => $cents];
    }
    $plan['notes'] = $mapped['notes'];
    arsort($plan['fields']);
    return $plan;
}

/** Writes the plan: private values, review pending, one log entry per member. */
function nda_apply($plan) {
    $count = 0;
    foreach ($plan['users'] as $id => $item) {
        $id = (int) $id;
        if (!get_user_by('id', $id)) {
            continue;
        }
        $data = profile_data($id);
        $visibility = is_array($data['visibility'] ?? null) ? $data['visibility'] : [];
        foreach ($item['fill'] as $key => $value) {
            $current = $data[$key] ?? null;
            if ($current !== null && $current !== '' && $current !== []) {
                continue; // Changed since the preview: the portal value wins.
            }
            $data[$key] = $value;
            if ($key !== 'birthday') {
                $visibility[$key] = $visibility[$key] ?? 'private';
            }
        }
        $data['visibility'] = $visibility;
        update_user_meta($id, 'eintrikot_profile', wp_slash($data));
        if ($item['donation'] !== '' && member_donation($id) === '') {
            set_member_donation($id, $item['donation'], 'Übernahme aus NDAlumni');
        }
        update_user_meta($id, 'eintrikot_invite_type', 'existing');
        if (get_user_meta($id, 'eintrikot_review', true) !== 'done') {
            update_user_meta($id, 'eintrikot_review', 'pending');
        }
        log_change(
            $id,
            'import',
            null,
            array_keys($item['fill']),
            'Übernahme aus NDAlumni (privat, zur Prüfung durch das Mitglied)'
        );
        $count++;
    }
    flush_caps_stats();
    return $count;
}

/** Labels for the preview. */
function nda_field_label($key) {
    $labels = profile_fields() + [
        'stations' => 'DHB-Vita',
        'birthday' => 'Geburtsdatum (nur wo aus MeinVerein keins kam)'
    ];
    return $labels[$key] ?? $key;
}

/** Section in "Neue Mitglieder aufnehmen". */
function render_ndalumni_step() {
    $plan = get_transient('et_ndalumni_' . get_current_user_id());
    echo '<section class="portal-section onboarding-step" id="ndalumni"><h2><span class="step-no">+</span> Profile aus NDAlumni übernehmen</h2><p>Einmalig für den Umstieg. Enthält die Import-Datei oben ein Blatt „Import_Roh“ (NDAlumni), passiert das automatisch mit. Sonst hier nach dem MeinVerein-Import die Mitgliederliste aus NDAlumni (Excel oder CSV, alle Spalten) hochladen. Das Portal findet die Konten über die E-Mail-Adresse und ergänzt nur leere Felder. Alles bleibt privat, bis das Mitglied sein Profil geprüft und freigegeben hat. Bank- und Mandatsdaten, Anschrift und Telefon werden nicht gelesen.</p><form class="et-form" method="post" enctype="multipart/form-data" action="' .
        esc_url(admin_url('admin-post.php')) .
        '">';
    wp_nonce_field('et_ndalumni_upload');
    echo '<input type="hidden" name="action" value="et_ndalumni_upload"><label>NDAlumni-Export<input type="file" name="ndalumni" accept=".xlsx,.csv" required></label><button class="button">Vorschau anzeigen</button></form>';
    if (is_array($plan)) {
        echo '<h3>Vorschau</h3><p><strong>' .
            count($plan['users']) .
            '</strong> Konten gefunden' .
            ($plan['unmatched']
                ? ', <strong>' .
                    count($plan['unmatched']) .
                    '</strong> ohne Konto im Portal (werden übersprungen)'
                : '') .
            '. Jahresspende wird für <strong>' .
            (int) $plan['donations'] .
            '</strong> Mitglieder ergänzt, bei denen im Portal noch nichts steht (davon ' .
            (int) ($plan['donations_paid'] ?? 0) .
            ' mit Betrag, die übrigen „keine“).</p><div class="table-scroll"><table class="import-table"><thead><tr><th scope="col">Feld</th><th scope="col">Profile</th></tr></thead><tbody>';
        foreach ($plan['fields'] as $key => $n) {
            echo '<tr><td>' . esc_html(nda_field_label($key)) . '</td><td>' . (int) $n . '</td></tr>';
        }
        echo '</tbody></table></div>';
        if ($plan['notes']) {
            echo '<h3>Hinweise</h3><ul>';
            foreach ($plan['notes'] as $text => $n) {
                echo '<li>' . esc_html($text) . ': ' . (int) $n . '</li>';
            }
            echo '</ul>';
        }
        if ($plan['unmatched']) {
            echo '<details><summary>Ohne Konto im Portal</summary><p>' .
                esc_html(implode(', ', $plan['unmatched'])) .
                '</p></details>';
        }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('et_ndalumni_apply');
        echo '<input type="hidden" name="action" value="et_ndalumni_apply"><button class="button solid">Angaben übernehmen</button> <span class="portal-hint">Es werden keine E-Mails verschickt.</span></form>';
    }
    echo '</section>';
}

add_action('admin_post_et_ndalumni_upload', function () {
    if (!manager_access()) {
        wp_die('Keine Berechtigung.', '', ['response' => 403]);
    }
    check_admin_referer('et_ndalumni_upload');
    $file = $_FILES['ndalumni'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- upload: type, size and is_uploaded_file() are checked below
    if (
        !is_array($file) ||
        ($file['error'] ?? 1) !== UPLOAD_ERR_OK ||
        !is_uploaded_file($file['tmp_name']) ||
        $file['size'] > 5 * 1024 * 1024
    ) {
        onboarding_notice(false, 'Bitte eine Excel- oder CSV-Datei bis 5 MB auswählen.');
    }
    $name = strtolower((string) ($file['name'] ?? ''));
    $rows = str_ends_with($name, '.xlsx')
        ? read_xlsx($file['tmp_name'])
        : (str_ends_with($name, '.csv')
            ? read_csv($file['tmp_name'])
            : new \WP_Error('import', 'Bitte eine .xlsx- oder .csv-Datei hochladen.'));
    if (is_wp_error($rows)) {
        onboarding_notice(false, $rows->get_error_message());
    }
    [$keys] = nda_records($rows);
    if (!in_array('bevorzugteemailadresse', $keys, true) && !in_array('emailadresseprivat', $keys, true)) {
        onboarding_notice(
            false,
            'Das sieht nicht nach dem NDAlumni-Export aus (Spalte „Bevorzugte E-Mail-Adresse“ fehlt).'
        );
    }
    // Only the planned portal values are kept, never the uploaded file.
    set_transient('et_ndalumni_' . get_current_user_id(), nda_plan(nda_map($rows)), 30 * MINUTE_IN_SECONDS);
    wp_safe_redirect(portal_url('onboarding') . '#ndalumni');
    exit();
});

add_action('admin_post_et_ndalumni_apply', function () {
    if (!manager_access()) {
        wp_die('Keine Berechtigung.', '', ['response' => 403]);
    }
    check_admin_referer('et_ndalumni_apply');
    $key = 'et_ndalumni_' . get_current_user_id();
    $plan = get_transient($key);
    if (!is_array($plan)) {
        onboarding_notice(false, 'Die Vorschau ist abgelaufen. Bitte die Datei erneut hochladen.');
    }
    $count = nda_apply($plan);
    delete_transient($key);
    onboarding_notice(
        true,
        'Profilangaben für ' . $count . ' Mitglieder übernommen (privat). Es wurden keine E-Mails verschickt.'
    );
});
