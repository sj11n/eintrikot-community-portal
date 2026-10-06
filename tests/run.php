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
$titles = array_column(calendar_items(60), 'title');
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
