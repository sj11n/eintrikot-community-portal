<?php
/**
 * Regeltests für das Plugin „EINTRIKOT Newsletter“ (docs/13): Empfänger, Freigabe mit vier Augen, Einfrieren,
 * Versand in Paketen ohne Doppelversand, Abbruch und Fortsetzen, Abmeldelink, Kopfzeilen, Protokoll, Aufräumen.
 *
 * Läuft gegen das lokale WordPress aus tools/lokal-test.sh (SQLite, keine echten Mails):
 *   tools/lokal-test.sh test
 * Jeder Lauf legt seine Konten und Ausgaben selbst an und räumt sie wieder auf.
 */
namespace Eintrikot\Newsletter;

use function Eintrikot\Community\newsletter_consent;
use function Eintrikot\Community\newsletter_recipient_ids;
use function Eintrikot\Community\newsletter_set_consent;

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
$created = [];
$issues = [];
function make_user($role, $mail = '', $profile = []) {
    global $created;
    $login = 'nltest_' . wp_generate_password(8, false);
    $id = wp_insert_user([
        'user_login' => $login,
        'user_email' => $mail !== '' ? $mail : $login . '@mail-test.invalid',
        'user_pass' => wp_generate_password(24),
        'role' => $role,
        'display_name' => ucfirst($login) . ' Test'
    ]);
    $created[] = $id;
    update_user_meta($id, 'eintrikot_profile', $profile);
    return $id;
}
function as_user($id) {
    wp_set_current_user($id);
}
function make_issue($author, $content) {
    global $issues;
    as_user($author);
    $id = wp_insert_post([
        'post_type' => POST_TYPE,
        'post_status' => 'draft',
        'post_title' => 'Betreff der Testausgabe',
        'post_content' => $content
    ]);
    $issues[] = $id;
    update_post_meta($id, '_et_nl_label', 'Testausgabe');
    update_post_meta($id, '_et_nl_preheader', 'Vorschautext der Testausgabe');
    track_change($id);
    return $id;
}
function audit_count($like) {
    global $wpdb;
    return (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}eintrikot_audit WHERE reason LIKE %s",
            '%' . $wpdb->esc_like($like) . '%'
        )
    );
}

// Mails: mitschreiben; Adressen in $fail_to schlagen fehl.
$mails = [];
$fail_to = [];
add_filter(
    'pre_wp_mail',
    function ($short, $args) {
        global $mails, $fail_to;
        $to = is_array($args['to']) ? implode(',', $args['to']) : $args['to'];
        if (in_array($to, $fail_to, true)) {
            return false;
        }
        $mails[] = $args + ['to_plain' => $to];
        return true;
    },
    20,
    2
);
function mails_to($to) {
    global $mails;
    return count(array_filter($mails, static fn($m) => $m['to_plain'] === $to));
}

$content =
    '<!-- wp:paragraph --><p>Einleitung mit <a href="https://example.org/seite">Link</a> und <strong>fett</strong>.</p><!-- /wp:paragraph -->' .
    '<!-- wp:separator --><hr class="wp-block-separator"/><!-- /wp:separator -->' .
    '<!-- wp:heading --><h2 class="wp-block-heading">Erste Meldung</h2><!-- /wp:heading -->' .
    '<!-- wp:image --><figure class="wp-block-image"><img src="' .
    get_theme_file_uri('assets/og-image.jpg') .
    '" alt="Spielerinnen beim Training"/></figure><!-- /wp:image -->' .
    '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link" href="https://example.org/anmelden">Jetzt anmelden</a></div><!-- /wp:button --></div><!-- /wp:buttons -->';

/* ---------- Aufbau ---------- */

check('Plugin ist aktiv und findet den Core', core_ready() && is_plugin_active_safe());
function is_plugin_active_safe() {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    return is_plugin_active('eintrikot-newsletter/eintrikot-newsletter.php');
}
global $wpdb;
check('Versandtabelle ist angelegt', $wpdb->get_var('SELECT COUNT(*) FROM ' . table()) !== null);

$editor = make_user('eintrikot_editor');
$board_a = make_user('eintrikot_board');
$board_b = make_user('eintrikot_board');
$member = make_user('eintrikot_member');

// Empfänger: Ja, Nein, nicht beantwortet, Beispieladresse, doppelte Adresse, minderjährig.
$yes = [];
for ($i = 0; $i < 5; $i++) {
    $yes[$i] = make_user(
        'eintrikot_member',
        'nl-ja-' . $i . '-' . wp_generate_password(6, false, false) . '@mail-test.invalid'
    );
    newsletter_set_consent($yes[$i], 'yes');
}
$no = make_user('eintrikot_member');
newsletter_set_consent($no, 'no');
$open = make_user('eintrikot_member');
$sample = make_user(
    'eintrikot_member',
    'nl-beispiel-' . wp_generate_password(6, false, false) . '@example.test'
);
newsletter_set_consent($sample, 'yes');
$twin_mail = 'nl-doppelt-' . wp_generate_password(6, false, false) . '@mail-test.invalid';
$twin_a = make_user('eintrikot_member', $twin_mail);
newsletter_set_consent($twin_a, 'yes');
$kid = make_user('eintrikot_member', '', ['birthday' => gmdate('Y-m-d', strtotime('-12 years'))]);
newsletter_set_consent($kid, 'yes');

$ids = newsletter_recipient_ids();
check('Empfänger: alle mit Ja sind dabei', !array_diff($yes, array_keys($ids)));
check('Empfänger: Nein und nicht beantwortet fehlen', !isset($ids[$no]) && !isset($ids[$open]));
check('Empfänger: keine Beispieladressen', !isset($ids[$sample]));
check('Empfänger: Minderjährige fehlen', !isset($ids[$kid]));
check('Empfänger: keine doppelten Adressen', count($ids) === count(array_unique($ids)));

/* ---------- Ausgabe, Inhalt, Mail ---------- */

$issue = make_issue($editor, $content);
check('Ausgabe beginnt als Entwurf', state($issue) === 'draft');
check('Letzte Änderung: Redaktion', last_editor($issue) === $editor);
wp_update_post(['ID' => $issue, 'post_status' => 'publish']);
check('Ausgaben werden nie öffentlich gespeichert', get_post_status($issue) === 'private');

$body = render_body(get_post($issue)->post_content);
check(
    'Mail: Absatz, Überschrift, Bild, Button, Trennlinie',
    str_contains($body, 'Einleitung mit') &&
        str_contains($body, '<h2') &&
        str_contains($body, 'alt="Spielerinnen beim Training"') &&
        str_contains($body, 'Jetzt anmelden →') &&
        str_contains($body, 'et-rule')
);
check(
    'Mail: Links im Text bekommen den Mail-Stil',
    str_contains($body, 'href="https://example.org/seite" style="color:#000000')
);
$html = render_mail(freeze($issue), ['name' => 'Mia', 'unsubscribe' => 'https://example.org/abmelden']);
check(
    'Mail: Anrede, Betreff, Vorschautext',
    str_contains($html, 'Hallo Mia,') &&
        str_contains($html, 'Betreff der Testausgabe') &&
        str_contains($html, 'Vorschautext der Testausgabe')
);
check(
    'Mail: Abmeldelink, Datenschutz und Impressum in der Fußzeile',
    str_contains($html, 'Mit einem Klick abmelden') &&
        str_contains($html, 'Datenschutz') &&
        str_contains($html, 'VR 32731')
);
check('Mail: keine Zählpixel oder fremden Skripte', !preg_match('/<script|width="1" height="1"/i', $html));
$text = render_mail_text(freeze($issue), ['name' => 'Mia', 'unsubscribe' => 'https://example.org/abmelden']);
check(
    'Textfassung: Inhalt, Links und Abmeldelink',
    str_contains($text, 'Hallo Mia,') &&
        str_contains($text, 'Link (https://example.org/seite)') &&
        str_contains($text, 'Jetzt anmelden (https://example.org/anmelden)') &&
        str_contains($text, 'Abmelden: https://example.org/abmelden') &&
        !str_contains($text, '<')
);

check(
    'Bild ohne Alternativtext wird erkannt',
    (bool) block_problems(
        content_blocks(
            '<!-- wp:image --><figure class="wp-block-image"><img src="https://example.org/b.jpg" alt=""/></figure><!-- /wp:image -->'
        )
    )
);
check(
    'Fremde Bausteine werden erkannt',
    (bool) block_problems(
        content_blocks(
            '<!-- wp:table --><figure class="wp-block-table"><table><tr><td>x</td></tr></table></figure><!-- /wp:table -->'
        )
    )
);
check('Erlaubter Inhalt hat keine Probleme', block_problems(content_blocks($content)) === []);
check(
    'Fremde Bilder werden erkannt (nichts wird nachgeladen)',
    (bool) block_problems(
        content_blocks(
            '<!-- wp:image --><figure class="wp-block-image"><img src="https://example.org/b.jpg" alt="x"/></figure><!-- /wp:image -->'
        )
    )
);
check(
    'Dateien außerhalb von wp-content gelten nicht als eigene Bilder',
    local_image_path(content_url('../wp-config.php')) === '' &&
        local_image_path(content_url('themes/eintrikot-community/style.css')) === ''
);
[$embedded, $files] = embed_images(render_body($content));
check(
    'Eigene Bilder werden in die Mail eingebettet',
    str_contains($embedded, 'src="cid:et-bild-') &&
        !preg_match('/<img[^>]+src="http/i', $embedded) &&
        count($files) === 1 &&
        is_readable(reset($files))
);

/* ---------- Heftgestaltung „Das Viertel“ ---------- */

$vorlage = (string) file_get_contents(dirname(__DIR__) . '/.claude/skills/das-viertel/vorlage-nr1.html');
check(
    'Vorlage Nr. 1 des Skills hat keine Probleme',
    $vorlage !== '' && block_problems(content_blocks($vorlage)) === []
);
$heft = make_issue($editor, $vorlage);
$heft_body = render_body($vorlage, 'rose');
check(
    'Heft: Rubrik, Inhaltszeile, Unterschrift, Titelkasten, Zitat, Liste, Zahl, Rahmen, Termin, Abpfiff',
    str_contains($heft_body, 'class="et-rubrik"') &&
        str_contains($heft_body, 'IN DIESEM VIERTEL') &&
        str_contains($heft_body, 'class="et-sign"') &&
        str_contains($heft_body, 'WAS MACHT EIGENTLICH …?') &&
        str_contains($heft_body, 'class="et-bar"') &&
        str_contains($heft_body, '>01</td>') &&
        str_contains($heft_body, '>70</td>') &&
        str_contains($heft_body, 'class="et-frame"') &&
        str_contains($heft_body, '>12.</span><br><span') &&
        str_contains($heft_body, 'class="et-btn"')
);
check(
    'Heft: Rosé färbt Kacheln und Punkte',
    str_contains($heft_body, '#f1d1dd') && str_contains($heft_body, '#da525d')
);
$gruen = render_body($vorlage, 'gruen');
check('Heft: Grün färbt um', str_contains($gruen, '#cedfbd') && !str_contains($gruen, '#f1d1dd'));
check('Heft: Unbekannte Farbe fällt auf Rosé zurück', palette('lila') === palette('rose'));
$hash = content_hash($heft);
update_post_meta($heft, '_et_nl_color', 'gruen');
check('Heft: Farbwechsel ist eine Änderung (neue Testmail nötig)', content_hash($heft) !== $hash);
check('Heft: Farbe wird eingefroren', freeze($heft)['color'] === 'gruen');
$heft_html = render_mail(freeze($heft), ['name' => 'Mia']);
check(
    'Heft: Kopfzeile „Das Viertel“, Farbband und dunkle Farbe des Viertels',
    str_contains($heft_html, 'Das Viertel · Testausgabe') &&
        str_contains($heft_html, 'linear-gradient(90deg,#cedfbd') &&
        str_contains($heft_html, '.et-text .et-num{color:#cedfbd!important}')
);
check(
    'Heft: Im Dunkelmodus bleiben Links lesbar, nur Buttons schwarz',
    !str_contains($heft_html, '.et-text td a{') && str_contains($heft_html, '.et-text a.et-btn{color:#000000')
);
check(
    'Heft: Anrede steht unter der Bully-Rubrik, nicht über der Inhaltszeile',
    (bool) preg_match('/IN DIESEM VIERTEL.*BULLY<\/p><p[^>]*>Hallo Mia,<\/p><p[^>]*>heute ist/s', $heft_html)
);
$heft_mail_text = render_mail_text(freeze($heft), ['name' => 'Mia']);
check(
    'Heft: Textfassung mit Anrede unter der Bully-Rubrik',
    str_contains($heft_mail_text, "● BULLY\nHallo Mia,\n\nheute ist") &&
        strpos($heft_mail_text, 'IN DIESEM VIERTEL') < strpos($heft_mail_text, 'Hallo Mia,')
);
check(
    'Ohne Rubrik steht die Anrede vor dem Inhalt',
    greet_html('<p>Text</p>', '<p>Hallo,</p>') === '<p>Hallo,</p><p>Text</p>' &&
        greet_text('Text', 'Hallo,') === "Hallo,\n\nText"
);
$heft_text = render_text(freeze($heft)['body']);
check(
    'Heft: Textfassung mit Rubriken, Listenüberschriften und Terminen',
    str_contains($heft_text, '● KURZPASS') &&
        str_contains($heft_text, "01 Neuer Bundestrainer der Damen\nMarkus Lonnes") &&
        str_contains($heft_text, '12. JUNI – FIH Pro League in Berlin') &&
        !str_contains($heft_text, '<')
);
check(
    'Heft: Gruppen in Gruppen werden erkannt',
    (bool) block_problems(
        content_blocks(
            '<!-- wp:group {"className":"is-style-kasten"} --><div class="wp-block-group is-style-kasten"><!-- wp:group --><div class="wp-block-group"><!-- wp:paragraph --><p>x</p><!-- /wp:paragraph --></div><!-- /wp:group --></div><!-- /wp:group -->'
        )
    )
);
check(
    'Heft: Termin ohne fettes Datum wird erkannt',
    (bool) block_problems(
        content_blocks(
            '<!-- wp:paragraph {"className":"is-style-termin"} --><p class="is-style-termin">Pro League in Berlin</p><!-- /wp:paragraph -->'
        )
    )
);
check(
    'Heft: Große Zahl ohne Zahl wird erkannt',
    (bool) block_problems(
        content_blocks(
            '<!-- wp:group {"className":"is-style-zahl"} --><div class="wp-block-group is-style-zahl"><!-- wp:paragraph --><p>x</p><!-- /wp:paragraph --></div><!-- /wp:group -->'
        )
    )
);
check(
    'Heft: Bild im Zitat wird erkannt',
    (bool) block_problems(
        content_blocks(
            '<!-- wp:quote --><blockquote class="wp-block-quote"><!-- wp:image --><figure class="wp-block-image"><img src="x.jpg" alt="x"/></figure><!-- /wp:image --></blockquote><!-- /wp:quote -->'
        )
    )
);
check(
    'Heft: Offene Platzhalter halten die Ausgabe auf',
    system_checks($heft)['placeholders'][0] === false &&
        str_contains(system_checks($heft)['placeholders'][1], '[Vorname Name]') &&
        system_checks($issue)['placeholders'][0] === true
);
check(
    'Heft: Fremde Stilnamen werden ignoriert',
    block_style(['blockName' => 'core/paragraph', 'attrs' => ['className' => 'is-style-fett']]) === ''
);

/* ---------- Testversand ---------- */

as_user($editor);
check(
    'Testversand an ein Mitgliederkonto wird abgelehnt',
    test_addresses(get_userdata($member)->user_email)[1] !== ''
);
check(
    'Testversand: mehr als 10 Adressen werden abgelehnt',
    test_addresses(implode(',', array_map(static fn($n) => "t$n@mail-test.invalid", range(1, 11))))[1] !== ''
);
check('Testversand: ungültige Adresse wird abgelehnt', test_addresses('kein-mail')[1] !== '');
check('Ohne Testmail ist die Ausgabe nicht vollständig geprüft', !system_ok($issue));
$mails = [];
check(
    'Testversand an Redaktion und Testpostfach',
    send_test($issue, get_userdata($board_a)->user_email . ', postfach@mail-test.invalid') === '' &&
        count($mails) === 2
);
check('Testmail: Betreff mit [TEST]', str_starts_with($mails[0]['subject'], '[TEST] '));
check(
    'Testmail: Abmeldelink ändert nichts (Test-Link)',
    handle_unsubscribe('POST', explode('et_abmelden=', unsubscribe_url(0, $issue))[1])[1] === 'test'
);
check('Nach dem Testversand sind die Prüfungen des Systems erfüllt', system_ok($issue));

/* ---------- Bereit und Freigabe ---------- */

check(
    'Bereit nur mit vollständiger Prüfliste',
    mark_ready($issue, ['facts', 'links']) !== '' && state($issue) === 'draft'
);
check('Versand ohne Freigabe ist nicht möglich', tick(50) === 0 && queue_counts($issue)['total'] === 0);
check(
    'Redaktion meldet als bereit',
    mark_ready($issue, array_keys(checklist())) === '' && state($issue) === 'ready'
);
check(
    'Redaktion kann nicht freigeben',
    approve($issue, count(newsletter_recipient_ids())) !== '' && state($issue) === 'ready'
);

// Vorstand A ändert den Inhalt und ist damit der letzte Bearbeiter.
as_user($board_a);
wp_update_post([
    'ID' => $issue,
    'post_content' => $content . '<!-- wp:paragraph --><p>Nachtrag vom Vorstand.</p><!-- /wp:paragraph -->'
]);
check(
    'Änderung im Stand „Bereit“: Vorstand A ist letzter Bearbeiter',
    last_editor($issue) === $board_a && state($issue) === 'ready'
);
check(
    'Freigebender ist nicht der letzte Bearbeiter',
    str_contains(approve($issue, count(newsletter_recipient_ids())), 'Vier-Augen')
);
// Die Änderung braucht eine neue Testmail.
check('Nach einer Änderung fehlt die Testmail der aktuellen Fassung', !system_ok($issue));
send_test($issue, 'postfach@mail-test.invalid');

as_user($board_b);
$count = count(newsletter_recipient_ids());
check(
    'Freigabe ohne die gezeigte Empfängerzahl scheitert',
    approve($issue, $count + 1) !== '' && state($issue) === 'ready'
);
check('Vorstand B gibt frei', approve($issue, $count) === '' && state($issue) === 'approved');
check('Freigabe füllt die Warteschlange je Empfänger', queue_counts($issue)['waiting'] === $count);
check(
    'Inhalt ist eingefroren',
    frozen($issue) !== null && str_contains(frozen($issue)['body'], 'Nachtrag vom Vorstand')
);
check(
    'Protokoll: Freigabe mit Empfängerzahl',
    audit_count('(#' . $issue . '): freigegeben für ' . $count . ' Empfänger') === 1
);

// Änderung nach der Freigabe (noch nichts versendet): zurück auf „Bereit“.
as_user($editor);
wp_update_post(['ID' => $issue, 'post_title' => 'Betreff der Testausgabe, geändert']);
check(
    'Änderung nach Freigabe setzt zurück auf „Bereit“',
    state($issue) === 'ready' && frozen($issue) === null && queue_counts($issue)['waiting'] === 0
);
check(
    'Protokoll: Freigabe zurückgenommen',
    audit_count('(#' . $issue . '): Inhalt nach der Freigabe geändert, Freigabe zurückgenommen') === 1
);
send_test($issue, 'postfach@mail-test.invalid');
as_user($board_b);
check('Erneute Freigabe', approve($issue, count(newsletter_recipient_ids())) === '');

/* ---------- Versand ---------- */

$mails = [];
$fail_to = [get_userdata($yes[4])->user_email];
as_user(0);
$first = tick(2);
check('Erstes Paket: genau zwei Mails', $first === 2 && count($mails) === 2 && state($issue) === 'sending');
check(
    'Protokoll: Versandstart steht in der Freigabe',
    audit_count('(#' . $issue . '): freigegeben für') === 2 && audit_count('Versand gestartet') >= 1
);
$m = $mails[0];
$headers = implode("\n", (array) $m['headers']);
check('Jede Mail einzeln an eine Person', !str_contains($m['to_plain'], ','));
check(
    'Versendete Mail lädt keine Bilder nach',
    !preg_match('/<img[^>]+src="http/i', $m['message']) && str_contains($m['message'], 'cid:et-bild-')
);
check(
    'Mail hat persönlichen Abmeldelink',
    str_contains($m['message'], 'et_abmelden=') && str_contains($m['message'], 'Mit einem Klick abmelden')
);
check(
    'Mail hat List-Unsubscribe und One-Click',
    str_contains($headers, 'List-Unsubscribe: <http') &&
        str_contains($headers, 'List-Unsubscribe-Post: List-Unsubscribe=One-Click')
);
check(
    'Absender newsletter@ und Antwort an info@',
    str_contains($headers, 'From: EINTRIKOT e. V. <newsletter@eintrikot.de>') &&
        str_contains($headers, 'Reply-To: info@eintrikot.de')
);

// Abmeldung während des Versands greift vor der nächsten Mail.
$waiting_user = (int) $wpdb->get_var(
    $wpdb->prepare(
        'SELECT user_id FROM ' . table() . " WHERE issue_id = %d AND status = 'waiting' ORDER BY id LIMIT 1",
        $issue
    )
);
$token = explode('et_abmelden=', unsubscribe_url($waiting_user, $issue))[1];
check(
    'Abmeldelink per GET zeigt nur die Bestätigung',
    handle_unsubscribe('GET', $token) === [200, 'confirm'] &&
        newsletter_consent($waiting_user)['state'] === 'yes'
);
check(
    'Abmeldelink mit falscher Methode wird abgelehnt',
    handle_unsubscribe('PUT', $token)[0] === 405 && newsletter_consent($waiting_user)['state'] === 'yes'
);
check(
    'Manipulierter Abmeldelink wird abgelehnt',
    handle_unsubscribe('POST', substr($token, 0, -1) . (substr($token, -1) === 'a' ? 'b' : 'a'))[0] === 403
);
$other = $waiting_user === $yes[0] ? $yes[1] : $yes[0];
check(
    'Signatur gilt nur für dieses Mitglied',
    handle_unsubscribe('POST', $other . substr($token, strpos($token, '-')))[0] === 403
);
check(
    'Abmeldelink per POST (One-Click) setzt auf Nein',
    handle_unsubscribe('POST', $token) === [200, 'done'] &&
        newsletter_consent($waiting_user)['state'] === 'no'
);
check('Protokoll: Abmeldung über den Link', audit_count('Abmeldelink im Newsletter') >= 1);

// Abbruch und Fortsetzen ohne Doppelversand.
as_user($editor);
check('Redaktion kann nicht abbrechen', cancel($issue) !== '' && state($issue) === 'sending');
as_user($board_a);
check('Vorstand bricht ab', cancel($issue) === '' && state($issue) === 'cancelled');
as_user(0);
check('Abgebrochen: es geht nichts mehr raus', tick(50) === 0);
as_user($board_a);
check('Vorstand setzt fort', resume($issue) === '' && state($issue) === 'sending');
as_user(0);
for ($round = 0; $round < 6 && state($issue) === 'sending'; $round++) {
    tick(50);
}
$c = queue_counts($issue);
check('Versand beendet', state($issue) === 'sent');
check(
    'Abgemeldetes Mitglied wurde übersprungen',
    mails_to(get_userdata($waiting_user)->user_email) === 0 && $c['skipped'] === 1
);
$doubles = array_filter(array_count_values(array_column($mails, 'to_plain')), static fn($n) => $n > 1);
check(
    'Niemand bekommt die Ausgabe doppelt',
    !array_diff_key($doubles, [get_userdata($yes[4])->user_email => true])
);
check(
    'Fehlerhafte Adresse: drei Versuche, dann Fehler',
    $c['error'] === 1 &&
        (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT attempts FROM ' . table() . ' WHERE issue_id = %d AND user_id = %d',
                $issue,
                $yes[4]
            )
        ) === MAX_ATTEMPTS
);
check(
    'Alle anderen haben die Ausgabe genau einmal',
    $c['sent'] ===
        count(array_unique(array_diff(array_column($mails, 'to_plain'), [get_userdata($yes[4])->user_email])))
);
check('Protokoll: Versand beendet', audit_count('(#' . $issue . '): Versand beendet') === 1);
check(
    'Nach dem Versand ist die Ausgabe gesperrt',
    !user_can($board_a, 'edit_post', $issue) && !user_can($editor, 'delete_post', $issue)
);
$fail_to = [];

/* ---------- Hängengebliebene Zeile ---------- */

$issue2 = make_issue($editor, '<!-- wp:paragraph --><p>Zweite Ausgabe.</p><!-- /wp:paragraph -->');
as_user($editor);
send_test($issue2, 'postfach@mail-test.invalid');
mark_ready($issue2, array_keys(checklist()));
as_user($board_a);
approve($issue2, count(newsletter_recipient_ids()));
as_user(0);
$stuck = (int) $wpdb->get_var(
    $wpdb->prepare('SELECT id FROM ' . table() . ' WHERE issue_id = %d ORDER BY id LIMIT 1', $issue2)
);
$wpdb->update(
    table(),
    ['status' => 'sending', 'updated_at' => gmdate('Y-m-d H:i:s', time() - 3600)],
    ['id' => $stuck]
);
$mails = [];
for ($round = 0; $round < 6 && state($issue2) !== 'sent'; $round++) {
    tick(50);
}
check(
    'Unterbrochene Zustellung wird nicht wiederholt',
    $wpdb->get_var($wpdb->prepare('SELECT status FROM ' . table() . ' WHERE id = %d', $stuck)) === 'error' &&
        state($issue2) === 'sent'
);

/* ---------- Archiv, Rechte, Aufräumen ---------- */

as_user($member);
$archive = render_archive();
check('Archiv zeigt versendete Ausgaben', str_contains($archive, 'Betreff der Testausgabe, geändert'));
$draft = make_issue($editor, '<!-- wp:paragraph --><p>Noch geheim.</p><!-- /wp:paragraph -->');
as_user($member);
check(
    'Archiv zeigt keine Entwürfe',
    !str_contains(render_archive(), 'Noch geheim') && str_contains(render_read($draft), 'nicht gefunden')
);
check('Mitglied liest eine versendete Ausgabe', str_contains(render_read($issue), 'srcdoc='));
check('Mitglied kann keine Ausgabe freigeben', approve($draft, 1) !== '');
check('Mitglied sieht die Redaktionsansicht nicht', !can_write());

$wpdb->update(
    table(),
    ['updated_at' => gmdate('Y-m-d H:i:s', time() - 400 * DAY_IN_SECONDS)],
    ['issue_id' => $issue2]
);
cleanup();
check(
    'Versandprotokoll nach 12 Monaten gelöscht',
    queue_counts($issue2)['total'] === 0 && queue_counts($issue)['total'] > 0
);

/* ---------- Aufräumen ---------- */

require_once ABSPATH . 'wp-admin/includes/user.php';
$before = (int) $wpdb->get_var(
    $wpdb->prepare('SELECT COUNT(*) FROM ' . table() . ' WHERE user_id = %d', $yes[0])
);
wp_delete_user($yes[0]);
check(
    'Gelöschtes Konto nimmt sein Versandprotokoll mit',
    $before > 0 &&
        (int) $wpdb->get_var(
            $wpdb->prepare('SELECT COUNT(*) FROM ' . table() . ' WHERE user_id = %d', $yes[0])
        ) === 0
);

foreach ($issues as $id) {
    $wpdb->delete(table(), ['issue_id' => $id]);
    wp_delete_post($id, true);
}
foreach ($created as $id) {
    wp_delete_user($id);
}
as_user(0);

echo "$passed bestanden, $failed fehlgeschlagen.\n";
exit($failed ? 1 : 0);
