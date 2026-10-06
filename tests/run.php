<?php
/**
 * Regeltests für Altersgrenze, Sichtbarkeit, Eltern-Zustimmung und Login-Sperre.
 *
 * Läuft gegen das lokale WordPress aus tools/lokal-test.sh (SQLite, Testdaten, keine echten Mails):
 *   tools/lokal-test.sh test
 * Jeder Lauf legt seine Konten selbst an und räumt sie wieder auf.
 */
namespace Eintrikot\Community;

$wp_root = getenv('WP_ROOT') ?: dirname(__DIR__) . '/.lokal/wordpress';
if (!is_file($wp_root . '/wp-load.php')) {
    fwrite(STDERR, "Kein lokales WordPress unter $wp_root. Zuerst: tools/lokal-test.sh setup\n");
    exit(2);
}
$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
require $wp_root . '/wp-load.php';

$failed = 0;
$passed = 0;
function check($name, $condition) {
    global $failed, $passed;
    if ($condition) {
        $passed++;
        return;
    }
    $failed++;
    echo "FEHLER: $name\n";
}
function day($offset) {
    return (new \DateTimeImmutable('today', wp_timezone()))->modify($offset)->format('Y-m-d');
}
$created = [];
function make_user($role, $profile = [], $meta = []) {
    global $created;
    $login = 'test_' . wp_generate_password(8, false);
    $id = wp_insert_user([
        'user_login' => $login,
        'user_email' => $login . '@example.test',
        'user_pass' => wp_generate_password(24),
        'role' => $role
    ]);
    $created[] = $id;
    update_user_meta($id, 'eintrikot_profile', $profile);
    foreach ($meta as $k => $v) {
        update_user_meta($id, $k, $v);
    }
    return $id;
}

/* ---------- Altersgrenze ---------- */

// Genau am 18. Geburtstag gilt Erwachsenenrecht, einen Tag davor noch Jugendrecht.
check('18 heute = erwachsen', !is_minor_data(['birthday' => day('-18 years')]));
check('18 morgen = minderjährig', is_minor_data(['birthday' => day('-18 years +1 day')]));
check('ohne Geburtsdatum = erwachsen', !is_minor_data([]));
check('Geburtsdatum in der Zukunft = erwachsen', !is_minor_data(['birthday' => day('+1 year')]));
check('ungültiges Datum = erwachsen', !is_minor_data(['birthday' => '2020-02-31']));
check('Alter wird richtig berechnet', member_years(['birthday' => day('-40 years -3 days')]) === 40);

/* ---------- Sichtbarkeit ---------- */

$shared = [
    'city' => 'Köln',
    'team' => 'Damen',
    'region' => 'NRW',
    'age_class' => 'U16',
    'visibility' => ['city' => 'members', 'team' => 'members', 'region' => 'members', 'age_class' => 'members']
];
$adult = $shared + ['birthday' => day('-30 years')];
$kid = $shared + ['birthday' => day('-15 years')];
check('Erwachsener: freigegebener Wohnort sichtbar', visible_value($adult, 'city') === 'Köln');
check('Erwachsener: nicht freigegeben = leer', visible_value(['city' => 'Köln'], 'city') === '');
check('Standard ist privat', visible_value(['city' => 'Köln', 'visibility' => []], 'city') === '');
check('Minderjährig: Wohnort trotz Freigabe verborgen', visible_value($kid, 'city') === '');
check('Minderjährig: Team sichtbar', visible_value($kid, 'team') === 'Damen');
check('Minderjährig: Altersklasse sichtbar', visible_value($kid, 'age_class') === 'U16');
check('Minderjährig: Region sichtbar', visible_value($kid, 'region') === 'NRW');
check('Minderjährig: Vita verborgen', minor_hides($kid, 'stations'));

$with_age = $adult + ['show_age' => true];
check('Alter nur auf Wunsch', member_age($adult) === null);
check('Alter bei Freigabe', member_age($with_age) === 30);
check('Minderjährig: nie ein Alter', member_age($kid + ['show_age' => true]) === null);

/* ---------- Geburtstag: nur, was das Mitglied freigibt ---------- */

$birth_adult = ['birthday' => '1990-05-17'];
check('Geburtstag: ohne Wahl privat', birthday_mode($birth_adult) === '' && birthday_text($birth_adult) === '');
check('Geburtstag: Tag und Monat ohne Jahr', preg_match('/^17\. \p{L}+$/u', birthday_text($birth_adult + ['birthday_visibility' => 'day'])) === 1);
check('Geburtstag: mit Jahr', preg_match('/^17\. \p{L}+ 1990$/u', birthday_text($birth_adult + ['birthday_visibility' => 'full'])) === 1);
check('Geburtstag: ungültige Wahl = privat', birthday_mode($birth_adult + ['birthday_visibility' => 'alle']) === '');
check('Geburtstag: altes Häkchen gilt als Tag und Monat', birthday_mode($birth_adult + ['birthday_notice' => true]) === 'day');
check('Geburtstag: neue Wahl schlägt das alte Häkchen', birthday_mode($birth_adult + ['birthday_notice' => true, 'birthday_visibility' => '']) === '');
$kid_birth = ['birthday' => day('-15 years'), 'birthday_visibility' => 'full'];
check('Geburtstag: Minderjährige zeigen nie einen Geburtstag', birthday_mode($kid_birth) === '' && birthday_text($kid_birth) === '');
check('Geburtstag: ohne gültiges Datum nichts', birthday_mode(['birthday_visibility' => 'full']) === '');

$bday_day = make_user('eintrikot_member', ['birthday' => day('+10 days -30 years'), 'birthday_visibility' => 'day'], ['eintrikot_activated_at' => time()]);
$bday_full = make_user('eintrikot_member', ['birthday' => day('+12 days -40 years'), 'birthday_visibility' => 'full'], ['eintrikot_activated_at' => time()]);
$bday_private = make_user('eintrikot_member', ['birthday' => day('+11 days -35 years')], ['eintrikot_activated_at' => time()]);
$bday_hidden = make_user('eintrikot_member', ['birthday' => day('+13 days -33 years'), 'birthday_visibility' => 'full', 'directory_listing' => 'hide'], ['eintrikot_activated_at' => time()]);
$bday_kid = make_user('eintrikot_member', ['birthday' => day('+14 days -15 years'), 'birthday_visibility' => 'full'], ['eintrikot_activated_at' => time(), 'eintrikot_consent' => 'given', 'eintrikot_consent_record' => ['method' => 'manual']]);
$titles = array_column(birthday_items(60), 'title');
$name = fn($id) => get_user_by('id', $id)->display_name;
check('Termine: Tag und Monat ohne Alter', in_array('Geburtstag: ' . $name($bday_day), $titles, true));
check('Termine: mit Jahr zeigt das neue Alter', in_array('Geburtstag: ' . $name($bday_full) . ' (wird 40)', $titles, true));
check('Termine: privat erscheint nicht', !array_filter($titles, fn($t) => str_contains($t, $name($bday_private))));
check('Termine: im Verzeichnis ausgeblendete Konten erscheinen nicht', !array_filter($titles, fn($t) => str_contains($t, $name($bday_hidden))));
check('Termine: Minderjährige erscheinen nicht', !array_filter($titles, fn($t) => str_contains($t, $name($bday_kid))));

/* ---------- Eltern-Zustimmung ---------- */

$kid_id = make_user('eintrikot_member', ['birthday' => day('-15 years')]);
$adult_id = make_user('eintrikot_member', ['birthday' => day('-30 years')]);
check('Kind ohne Zustimmung: Zustand kid', consent_state($kid_id) === 'kid');
check('Kind ohne Zustimmung: wartet', consent_pending($kid_id));
check('Erwachsener: keine Zustimmung nötig', consent_state($adult_id) === '' && !consent_pending($adult_id));
check('Kind ohne Zustimmung: nicht im Verzeichnis', !minor_listed($kid_id));

$token = consent_token($kid_id, 'kid');
check('Token gültig', consent_token_valid($kid_id, 'kid', $token));
check('Falscher Token abgelehnt', !consent_token_valid($kid_id, 'kid', $token . 'x'));
check('Leerer Token abgelehnt', !consent_token_valid($kid_id, 'kid', ''));
check('Token gilt nicht für anderen Schritt', !consent_token_valid($kid_id, 'parent', $token));
check('Token gilt nicht für anderes Konto', !consent_token_valid($adult_id, 'kid', $token));
update_user_meta($kid_id, 'eintrikot_consent_kid_at', time() - (CONSENT_VALID_DAYS + 1) * DAY_IN_SECONDS);
check('Abgelaufener Token abgelehnt', !consent_token_valid($kid_id, 'kid', $token));

update_user_meta($kid_id, 'eintrikot_activated_at', time());
update_user_meta($kid_id, 'eintrikot_consent', 'given');
update_user_meta($kid_id, 'eintrikot_consent_record', ['method' => 'online', 'directory' => false]);
check('Zustimmung erteilt: nicht mehr wartend', !consent_pending($kid_id));
check('Eltern erlauben Verzeichnis nicht: nicht gelistet', !minor_listed($kid_id));
update_user_meta($kid_id, 'eintrikot_consent_record', ['method' => 'online', 'directory' => true]);
check('Eltern erlauben Verzeichnis: gelistet', minor_listed($kid_id));
update_user_meta($kid_id, 'eintrikot_consent_record', ['method' => 'manual']);
check('Manuell erfasste Zustimmung: gelistet', minor_listed($kid_id));

/* ---------- Eltern-Seite: Meldungen nur aus fester Liste ---------- */

$page_user = make_user('eintrikot_member', ['birthday' => day('-15 years')]);
$page_token = consent_token($page_user, 'kid');
$render = function ($error) use ($page_user, $page_token) {
    $_GET = ['step' => 'kid', 'u' => (string) $page_user, 'k' => $page_token];
    if ($error !== null) {
        $_GET['error'] = $error;
    }
    return render_consent();
};
check('Seite mit gültigem Schlüssel zeigt das Formular', str_contains($render(null), 'parent_email'));
check('Bekannte Meldung wird angezeigt', str_contains($render('email_own'), 'eigene Adresse'));
check('Frei erfundener Text wird nicht angezeigt', !str_contains($render('Ihr Konto ist gesperrt, rufen Sie an'), 'gesperrt'));
check('HTML in der Meldung wird nicht ausgegeben', !str_contains($render('<script>alert(1)</script>'), '<script>alert'));
check('Alle Meldungen vorhanden', count(consent_errors()) === 6);
$_GET = [];

/* ---------- Verzeichnis ---------- */

check('Mitglied ist gelistet', directory_listed(get_user_by('id', $adult_id)));
update_user_meta($adult_id, 'eintrikot_profile', ['birthday' => day('-30 years'), 'directory_listing' => 'hide']);
check('Verwaltung kann ausblenden', !directory_listed(get_user_by('id', $adult_id)));
$other = make_user('subscriber');
check('Technisches Konto nicht gelistet', !directory_listed(get_user_by('id', $other)));

/* ---------- Kennzahlen: Privates bleibt privat ---------- */

flush_caps_stats();
$base = caps_stats();
$member_stations = [['role' => 'Spieler/in', 'organisation' => 'Damen', 'age_class' => 'U16', 'from' => '1901', 'to' => '1902']];
make_user('eintrikot_member', [
    'birthday' => day('-60 years'),
    'caps' => '11',
    'stations' => $member_stations,
    'visibility' => ['stations' => 'private', 'caps' => 'private']
]);
flush_caps_stats();
$private = caps_stats();
check('Privates Jahr zählt nicht', $private['first_year'] === $base['first_year']);
check('Private Länderspiele zählen nicht', $private['sum'] === $base['sum']);
check('Konto zählt als Mitglied', $private['total'] === $base['total'] + 1);

make_user('eintrikot_member', [
    'birthday' => day('-14 years'),
    'caps' => '5',
    'stations' => $member_stations,
    'visibility' => ['stations' => 'members', 'caps' => 'members']
], ['eintrikot_activated_at' => time(), 'eintrikot_consent' => 'given', 'eintrikot_consent_record' => ['method' => 'manual']]);
flush_caps_stats();
$kids = caps_stats();
check('Jugendliche: freigegebene Station zählt nicht', $kids['first_year'] === $base['first_year']);
check('Jugendliche: Länderspiele zählen nicht', $kids['sum'] === $base['sum']);

make_user('eintrikot_member', [
    'birthday' => day('-60 years'),
    'caps' => '11',
    'stations' => $member_stations,
    'visibility' => ['stations' => 'members', 'caps' => 'members']
]);
flush_caps_stats();
$open = caps_stats();
check('Freigegebenes Jahr zählt', $open['first_year'] === 1901);
check('Freigegebene Länderspiele zählen', $open['sum'] === $base['sum'] + 11);

/* ---------- Zwischenspeicher der Kennzahlen wird bei Profiländerungen geleert ---------- */

$cache_user = make_user('eintrikot_member', ['birthday' => day('-30 years')]);
caps_stats();
check('Kennzahlen sind zwischengespeichert', get_transient('eintrikot_caps_stats') !== false);
update_user_meta($cache_user, 'eintrikot_profile', ['birthday' => day('-30 years'), 'caps' => '3']);
check('Profiländerung leert den Zwischenspeicher', get_transient('eintrikot_caps_stats') === false);

/* ---------- Import: Spaltennamen der MeinVerein-Datei ---------- */

$import_rows = [
    ['Mitgliedsnr.', 'Vorname', 'Nachname', 'E-Mail', 'Geburtstag', 'Mitglied seit', 'Zusatzbetrag NDAlumni'],
    ['4711', 'Test', 'Import', 'import.' . wp_generate_password(6, false) . '@example.test', '35000', '45923', '60.0']
];
$import_map = import_extract($import_rows, [])['map'];
check('Import: Mitgliedsnummer erkannt', $import_map['number'] === 0);
check('Import: Geburtstag erkannt', $import_map['birthday'] === 4);
check('Import: Mitglied seit erkannt', $import_map['joined'] === 5);
check('Import: Zusatzbetrag NDAlumni wird zur Jahresspende', $import_map['donation'] === 6);
$import_checked = import_check(import_extract($import_rows, [])['rows']);
check('Import: Excel-Datum und Spende gelesen', $import_checked[0]['birthday'] === '1995-10-28' && $import_checked[0]['donation'] === '6000');
check('Import: Eintritt am Gründungstag ist gültig', $import_checked[0]['status'] === 'new');
$before = import_check([['first_name' => 'A', 'last_name' => 'B', 'email' => 'vorher@example.test', 'number' => '1', 'joined' => '2020-12-15', 'birthday' => '', 'donation' => '']]);
check('Import: Eintritt vor der Gründung wird abgelehnt', $before[0]['status'] === 'invalid');

/* ---------- Aktuelles: Termine und Anmeldung ---------- */

$event_posts = [];
function make_event($title, $meta) {
    global $event_posts;
    $id = wp_insert_post(['post_type' => 'et_calendar', 'post_status' => 'publish', 'post_title' => $title, 'post_content' => 'Beschreibung ' . $title]);
    foreach ($meta as $k => $v) {
        update_post_meta($id, $k, $v);
    }
    $event_posts[] = $id;
    return $id;
}
$ev_open = make_event('Test Treffen Köln', ['et_date' => day('+20 days'), 'et_time' => '18:30', 'et_end_time' => '21:00', 'et_place' => 'Test-Ort', 'et_meetpoint' => 'Eingang', 'et_contact' => 'Anna', 'et_format' => 'treffen', 'et_signup' => '1']);
$ev_multi = make_event('Test Turnier', ['et_date' => day('+30 days'), 'et_end_date' => day('+31 days'), 'et_format' => 'unterwegs', 'et_signup' => '1']);
$ev_cancel = make_event('Test Abgesagt', ['et_date' => day('+25 days'), 'et_signup' => '1', 'et_cancelled' => '1']);
$ev_past = make_event('Test Vergangen', ['et_date' => day('-5 days'), 'et_signup' => '1']);
$ev_running = make_event('Test Läuft gerade', ['et_date' => day('-1 day'), 'et_end_date' => day('+1 day'), 'et_signup' => '1']);
$ev_deadline = make_event('Test Schluss', ['et_date' => day('+40 days'), 'et_signup' => '1', 'et_deadline' => day('-1 day')]);
$ev_annual = make_event('Test Jahrestag', ['et_date' => day('+10 days -3 years'), 'et_annual' => '1', 'et_signup' => '1']);
$ev_free = make_event('Test Ohne Anmeldung', ['et_date' => day('+50 days')]);
$ev_draft = wp_insert_post(['post_type' => 'et_calendar', 'post_status' => 'draft', 'post_title' => 'Test Entwurf']);
update_post_meta($ev_draft, 'et_date', day('+5 days'));
$event_posts[] = $ev_draft;
$items = event_items();
$by = array_column($items, null, 'id');
check('Termine: Entwurf erscheint nicht', !isset($by[$ev_draft]));
check('Termine: Vergangenes erscheint nicht', !isset($by[$ev_past]));
check('Termine: laufender mehrtägiger Termin erscheint', isset($by[$ev_running]));
check('Termine: Felder gelesen', ($by[$ev_open]['place'] ?? '') === 'Test-Ort' && ($by[$ev_open]['meetpoint'] ?? '') === 'Eingang' && ($by[$ev_open]['format'] ?? '') === 'treffen');
check('Termine: Uhrzeit-Text', event_time_text($by[$ev_open]) === '18:30–21:00 Uhr');
check('Termine: mehrtägig hat Ende', ($by[$ev_multi]['end'] ?? '') === day('+31 days'));
check('Termine: Jahrestag hat keine Anmeldung', ($by[$ev_annual]['signup'] ?? true) === false && str_contains($by[$ev_annual]['title'] ?? '', '3. Jahrestag'));
check('Termine: unbekanntes Format wird Sonstiges', (event_from_post(get_post(make_event('Test Format', ['et_date' => day('+9 days'), 'et_format' => 'quatsch'])))['format'] ?? '') === 'sonstiges');
$sorted = array_column($items, 'date');
$copy = $sorted; sort($copy);
check('Termine: nach Datum sortiert', $sorted === $copy);
check('Termine: Geburtstage stehen nicht in der Terminliste', !array_filter($items, fn($i) => str_starts_with($i['title'], 'Geburtstag')));

$m1 = make_user('eintrikot_member', ['birthday' => day('-35 years')], ['eintrikot_activated_at' => time()]);
$m2 = make_user('eintrikot_member', ['birthday' => day('-28 years')], ['eintrikot_activated_at' => time()]);
$m_kid = make_user('eintrikot_member', ['birthday' => day('-14 years')], ['eintrikot_activated_at' => time(), 'eintrikot_consent' => 'given', 'eintrikot_consent_record' => ['method' => 'manual']]);
$m_hidden = make_user('eintrikot_member', ['birthday' => day('-40 years'), 'directory_listing' => 'hide'], ['eintrikot_activated_at' => time()]);
$outsider = make_user('subscriber');
check('Anmeldung: Mitglied kann sich anmelden', event_signup($ev_open, $m1) === '' && event_is_signed($ev_open, $m1));
check('Anmeldung: doppelt zählt einmal', event_signup($ev_open, $m1) === '' && count(event_attendee_ids($ev_open)) === 1);
event_signup($ev_open, $m2);
event_signup($ev_open, $m_kid);
event_signup($ev_open, $m_hidden);
check('Anmeldung: Teilnehmerzahl stimmt', count(event_attendee_ids($ev_open)) === 4);
$who = event_visible_attendees($ev_open);
check('Liste: Erwachsene mit Namen', isset($who['names'][$m1], $who['names'][$m2]));
check('Liste: Minderjährige nie mit Namen', !isset($who['names'][$m_kid]));
check('Liste: ausgeblendete Konten nie mit Namen', !isset($who['names'][$m_hidden]));
check('Liste: Verborgene zählen mit', $who['hidden'] === 2);
event_unsign($ev_open, $m2);
check('Abmelden entfernt die Anmeldung', !event_is_signed($ev_open, $m2));
check('Anmeldung: Abgesagtes nicht möglich', event_signup($ev_cancel, $m1) !== '' && !event_is_signed($ev_cancel, $m1));
check('Anmeldung: Vergangenes nicht möglich', event_signup($ev_past, $m1) !== '');
check('Anmeldung: nach Anmeldeschluss nicht möglich', event_signup($ev_deadline, $m1) !== '');
check('Anmeldung: Jahrestag nicht möglich', event_signup($ev_annual, $m1) !== '');
check('Anmeldung: Termin ohne Anmeldung nicht möglich', event_signup($ev_free, $m1) !== '');
check('Anmeldung: Entwurf nicht möglich', event_signup($ev_draft, $m1) !== '');
check('Anmeldung: Technisches Konto nicht möglich', event_signup($ev_open, $outsider) !== '');
check('Anmeldung: mehrtägig und laufend möglich', event_signup($ev_multi, $m1) === '' && event_signup($ev_running, $m1) === '');
wp_set_current_user($m1);
$_GET = ['view' => 'aktuelles', 'tab' => 'termine'];
ob_start();
render_news();
$page = ob_get_clean();
check('Seite Aktuelles: Titel und Reiter', str_contains($page, 'Aktuelles') && str_contains($page, 'news-tabs'));
check('Seite: Termin mit Ort und Format', str_contains($page, 'Test Treffen Köln') && str_contains($page, 'Test-Ort') && str_contains($page, 'Treffen'));
check('Seite: Abmelden für Angemeldeten', str_contains($page, 'Du bist angemeldet') && str_contains($page, 'value="leave"'));
wp_set_current_user($m2);
ob_start();
render_news();
$page2 = ob_get_clean();
check('Seite: Anmelden für Offenen', str_contains($page2, 'Für Veranstaltung anmelden') && str_contains($page2, 'value="join"'));
check('Seite: Teilnehmerliste mit Namen und Zahl', str_contains($page2, 'Wer ist dabei?') && str_contains($page2, get_user_by('id', $m1)->display_name));
check('Seite: Namen von Kindern fehlen', !str_contains($page, get_user_by('id', $m_kid)->display_name));
check('Seite: Entwurf und Vergangenes fehlen', !str_contains($page, 'Test Entwurf') && !str_contains($page, 'Test Vergangen'));
wp_set_current_user(0);
$_GET = [];
check('Navigation: Aktuelles statt Vereinsinfos und Termine', isset(portal_nav_items()['aktuelles']) && !isset(portal_nav_items()['infos']) && !isset(portal_nav_items()['events']));
check('Navigation: mobil vier Punkte', count(portal_mobile_items()) === 4);
global $wpdb;
$wpdb->insert(signup_table(), ['event_id' => $ev_open, 'user_id' => $outsider, 'event_date' => day('-400 days'), 'created_at' => current_time('mysql', true)]);
$retention = run_retention();
check('Aufbewahrung: alte Anmeldungen werden gelöscht', $retention['signups'] >= 1 && !event_is_signed($ev_open, $outsider));
check('Aufbewahrung: aktuelle bleiben', event_is_signed($ev_open, $m1));
$wpdb->query("DELETE FROM {$wpdb->prefix}eintrikot_event_signups WHERE event_id IN (" . implode(',', array_map('intval', $event_posts)) . ')');
foreach ($event_posts as $pid) {
    wp_delete_post($pid, true);
}

/* ---------- Login-Sperre ---------- */

$locked_id = make_user('eintrikot_member');
wp_set_password('Richtig-Test-Pw-9x!', $locked_id);
$locked_login = get_user_by('id', $locked_id)->user_email;
$ok = wp_authenticate($locked_login, 'Richtig-Test-Pw-9x!');
check('Richtiges Passwort funktioniert', $ok instanceof \WP_User);
for ($i = 0; $i < LOGIN_MAX_FAILS; $i++) {
    wp_authenticate($locked_login, 'falsch-' . $i);
}
$result = wp_authenticate($locked_login, 'Richtig-Test-Pw-9x!');
check('Nach 5 Fehlversuchen gesperrt, auch mit richtigem Passwort', is_wp_error($result) && $result->get_error_code() === 'et_locked');
$unknown = wp_authenticate('niemand_' . wp_generate_password(6, false) . '@example.test', 'egal');
check('Unbekannte Adresse: gleiche Meldung wie falsches Passwort', is_wp_error($unknown) && $unknown->get_error_code() === 'et_failed');
delete_transient(login_throttle_key($locked_login));

/* ---------- Eingabehilfen ---------- */

$_POST['k'] = ['kein', 'Text'];
check('post_choice: Liste statt Text = Standard', post_choice('k', ['a', 'b'], 'x') === 'x');
$_POST['k'] = 'b';
check('post_choice: erlaubter Wert', post_choice('k', ['a', 'b']) === 'b');
$_POST['k'] = 'c';
check('post_choice: unbekannter Wert = leer', post_choice('k', ['a', 'b']) === '');
check('client_ip', client_ip() === '203.0.113.7');

foreach ($created as $id) {
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($id);
}
flush_caps_stats();

echo "\n$passed bestanden, $failed fehlgeschlagen.\n";
exit($failed ? 1 : 0);
