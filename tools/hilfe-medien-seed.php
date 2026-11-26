<?php
/**
 * Testdaten für die Hilfe-Bilder (tools/hilfe-medien.sh). Läuft nur gegen die Wegwerf-Kopie des lokalen
 * Testportals, nie gegen das gemeinsame .lokal/wordpress und nie gegen einen Server.
 *
 * Alle Personen sind erfunden: die Demo-Mitglieder aus testphase.php und das Testkonto „Mia Test“.
 *
 *   PORT=8906 php tools/hilfe-medien-seed.php <wordpress-ordner>
 */
namespace Eintrikot\Community;

// Not $wp: WordPress keeps its main object in that global.
$copy = $argv[1] ?? '';
if ($copy === '' || !is_file($copy . '/wp-load.php') || !str_contains((string) realpath($copy), '/.lokal/hilfe-medien/')) {
    fwrite(STDERR, "Nur für die Kopie unter .lokal/hilfe-medien/ gedacht.\n");
    exit(2);
}
$_SERVER['HTTP_HOST'] = '127.0.0.1:' . (getenv('PORT') ?: '8906');
require $copy . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_set_current_user(1);

// Deutsche Oberfläche, damit Datumsangaben und WordPress-Texte stimmen.
if (get_option('WPLANG') !== 'de_DE') {
    require_once ABSPATH . 'wp-admin/includes/translation-install.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    if (!wp_download_language_pack('de_DE')) {
        fwrite(STDERR, "Deutsche Sprachdatei fehlt (keine Verbindung?). Bilder wären englisch.\n");
        exit(1);
    }
    update_option('WPLANG', 'de_DE');
}

// Konten aus abgebrochenen Testläufen (test_…) dürfen nicht im Verzeichnis auftauchen.
foreach (get_users(['search' => 'test_*', 'search_columns' => ['user_login']]) as $user) {
    $user->set_role('subscriber');
}

// Demo-Termine und -Infos neu anlegen, damit die Daten relativ zu heute stimmen.
foreach (
    get_posts([
        'post_type' => ['et_calendar', 'et_info'],
        'post_status' => 'any',
        'numberposts' => -1,
        'meta_key' => 'et_testdata',
        'meta_value' => '1'
    ])
    as $post
) {
    wp_delete_post($post->ID, true);
}
testphase_create_demo();

// Demo-Mitglieder: Verein und Länderspiele ergänzen, alles für Mitglieder sichtbar.
$clubs = [
    'Rot-Weiss Köln',
    'Uhlenhorster HC',
    'Club an der Alster',
    'Mannheimer HC',
    'Berliner HC',
    'Harvestehuder THC',
    'Crefelder HTC',
    'TSV Mannheim'
];
foreach (get_users(['meta_key' => 'eintrikot_testkonto', 'meta_value' => '1', 'fields' => 'ID']) as $k => $id) {
    $profile = profile_data((int) $id);
    $profile['club'] = $clubs[$k % count($clubs)];
    $profile['caps'] = (string) (12 + (($k * 23) % 160));
    foreach (array_keys(profile_group_slugs()) as $group) {
        foreach (section_visibility_keys($group) as $key) {
            $profile['visibility'][$key] = 'members';
        }
    }
    update_user_meta((int) $id, 'eintrikot_profile', $profile);
}

// Mia: neu im Portal, „Über dich“ noch nicht geteilt, Newsletter-Frage offen, eine Anfrage in Prüfung.
$mia = get_user_by('login', 'mia.test');
$vera = get_user_by('login', 'vera.vorstand');
if (!$mia || !$vera) {
    fwrite(STDERR, "Testkonten mia.test / vera.vorstand fehlen. Zuerst: tools/lokal-test.sh setup\n");
    exit(1);
}
$visibility = [];
foreach (array_keys(profile_group_slugs()) as $group) {
    foreach (section_visibility_keys($group) as $key) {
        $visibility[$key] = 'private';
    }
}
// Der ganze Hockey-Lebenslauf ist geteilt (Schalter an), „Über dich“ noch nicht.
foreach (array_keys(profile_group_slugs()) as $group) {
    $keys = section_visibility_keys($group);
    if (in_array('team', $keys, true)) {
        foreach ($keys as $key) {
            $visibility[$key] = 'members';
        }
    }
}
wp_update_user(['ID' => $mia->ID, 'display_name' => 'Mia Test', 'first_name' => 'Mia', 'last_name' => 'Test']);
update_user_meta($mia->ID, 'eintrikot_profile', [
    'city' => 'Bonn',
    'team' => 'Damen',
    'caps' => '7',
    'visibility' => $visibility,
    'display_name' => 'Mia Test',
    'birthday' => '1992-03-04',
    'birthday_visibility' => 'full',
    'show_age' => false,
    'public_photo' => false,
    'directory_listing' => 'hide',
    'stations' => [['role' => 'Spieler/in', 'organisation' => 'Damen', 'age_class' => 'U16', 'from' => '2012', 'to' => '2014']]
]);
foreach (
    [
        'eintrikot_member_number' => 1234,
        'eintrikot_joined' => '2025-09-23',
        'eintrikot_donation_cents' => 7550,
        'eintrikot_donation_updated' => wp_date('Y-m-d', strtotime('-7 weeks')),
        'eintrikot_activated_at' => time(),
        'eintrikot_welcome_seen' => time()
    ]
    as $key => $value
) {
    update_user_meta($mia->ID, $key, $value);
}
foreach (array_keys(get_user_meta($mia->ID)) as $key) {
    if (str_starts_with($key, 'eintrikot_showme') || str_starts_with($key, 'eintrikot_newsletter')) {
        delete_user_meta($mia->ID, $key);
    }
}
global $wpdb;
$wpdb->delete($wpdb->prefix . 'eintrikot_event_signups', ['user_id' => $mia->ID]);

foreach (get_posts(['post_type' => 'et_request', 'post_status' => 'any', 'author' => $mia->ID, 'numberposts' => -1]) as $old) {
    wp_delete_post($old->ID, true);
}
$request = wp_insert_post([
    'post_type' => 'et_request',
    'post_status' => 'private',
    'post_author' => $mia->ID,
    'post_title' => service_types()['contact'],
    'post_content' => 'Gibt es für das Sommertreffen schon eine Hotelempfehlung?',
    'post_date' => wp_date('Y-m-d H:i:s', strtotime('-2 days'))
]);
$reviewed = wp_date('Y-m-d H:i:s', strtotime('-1 day'));
update_post_meta($request, 'et_kind', 'contact');
update_post_meta($request, 'et_details', []);
update_post_meta($request, 'et_status', 'received');
update_post_meta($request, 'et_workflow', [
    'status' => 'review',
    'reply' => 'Ja, wir sammeln gerade Hotels in der Nähe und schicken die Liste mit der nächsten Vereinsinfo.',
    'note' => '',
    'actor' => $vera->ID,
    'updated_at' => $reviewed,
    'history' => [['status' => 'review', 'at' => $reviewed]]
]);
echo "Anfrage $request\n";
