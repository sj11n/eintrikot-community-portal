<?php
/**
 * Aufnahme neuer Mitglieder: Excel-Export aus MeinVerein einlesen, Portalkonten anlegen,
 * Begrüßung mit Mitgliedsurkunde und persönlichem Link zum Festlegen des Passworts senden.
 *
 * Grundsätze:
 * - MeinVerein bleibt führend. Übernommen werden nur Vorname, Nachname, E-Mail,
 *   Mitgliedsnummer und Eintrittsdatum; alle anderen Spalten werden nicht gespeichert.
 * - Es wird nie ein Passwort verschickt. Der Link ist einmalig und 14 Tage gültig
 *   (WordPress-Passwort-Link); das Mitglied legt sein Passwort selbst fest.
 */
namespace Eintrikot\Community;
if (!defined('ABSPATH')) {
    exit();
}

const INVITE_VALID_DAYS = 14;
const INVITE_BATCH = 25;
const MIN_PASSWORD_LENGTH = 10;

/* ---------- Settings (WordPress admin) ---------- */

function onboarding_settings() {
    return wp_parse_args(get_option('eintrikot_onboarding', []), [
        'from_name' => 'EINTRIKOT e.V.',
        'reply_to' => get_option('admin_email'),
        'signature' => "Björn Emmerling\n1. Vorsitzender"
    ]);
}

add_action('admin_menu', function () {
    add_submenu_page(
        'eintrikot-community-setup',
        'Aufnahme & Urkunde',
        'Aufnahme & Urkunde',
        'manage_options',
        'eintrikot-onboarding',
        __NAMESPACE__ . '\onboarding_settings_page'
    );
});

function onboarding_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    $s = onboarding_settings();
    echo '<div class="wrap"><h1>Aufnahme & Urkunde</h1>';
    foreach (['saved' => 'Gespeichert.', 'test' => 'Test-E-Mail an dich verschickt.'] as $key => $text) {
        if (isset($_GET[$key])) {
            echo '<div class="notice notice-success"><p>' . esc_html($text) . '</p></div>';
        }
    }
    if (isset($_GET['error'])) {
        echo '<div class="notice notice-error"><p>' .
            esc_html(sanitize_text_field(wp_unslash($_GET['error']))) .
            '</p></div>';
    }
    if (!function_exists('imagettftext')) {
        echo '<div class="notice notice-error"><p>Auf dem Server fehlt die Bildbearbeitung (GD mit FreeType). Ohne sie kann keine Urkunde erstellt werden.</p></div>';
    }
    if (!defined('WPMS_PLUGIN_VER') && !class_exists('\FluentMail\App\Hooks\Handlers\AdminMenuHandler')) {
        echo '<div class="notice notice-warning"><p>Es ist kein SMTP-Plugin aktiv. E-Mails gehen dann über den einfachen PHP-Versand und landen leichter im Spam. Empfehlung: WP Mail SMTP mit dem STRATO-Postfach einrichten und danach eine Test-E-Mail senden.</p></div>';
    }
    echo '<p>Die Mitglieder-Aufnahme selbst findest du im Portal unter Verwaltung → Neue Mitglieder aufnehmen.</p>';
    echo '<form method="post" enctype="multipart/form-data" action="' .
        esc_url(admin_url('admin-post.php')) .
        '">';
    wp_nonce_field('et_onboarding_settings');
    echo '<input type="hidden" name="action" value="et_onboarding_settings"><table class="form-table"><tbody>';
    echo '<tr><th scope="row"><label for="et-from">Absendername</label></th><td><input id="et-from" class="regular-text" name="from_name" value="' .
        esc_attr($s['from_name']) .
        '"></td></tr>';
    echo '<tr><th scope="row"><label for="et-reply">Antworten an</label></th><td><input id="et-reply" class="regular-text" type="email" name="reply_to" value="' .
        esc_attr($s['reply_to']) .
        '"><p class="description">Antworten der neuen Mitglieder landen hier.</p></td></tr>';
    echo '<tr><th scope="row"><label for="et-sig">Unterschrift im Schreiben</label></th><td><textarea id="et-sig" class="regular-text" rows="3" name="signature">' .
        esc_textarea($s['signature']) .
        '</textarea></td></tr>';
    echo '<tr><th scope="row"><label for="et-bg">Urkunden-Vorlage</label></th><td>' .
        (certificate_background()
            ? '<p><strong>Vorlage hinterlegt.</strong> <a href="' .
                esc_url(
                    admin_url(
                        'admin-post.php?action=et_certificate_preview&_wpnonce=' .
                            wp_create_nonce('et_certificate_preview')
                    )
                ) .
                '" target="_blank" rel="noopener">Muster-Urkunde ansehen</a></p>'
            : '<p><strong>Noch keine Vorlage hinterlegt.</strong></p>') .
        '<input id="et-bg" type="file" name="certificate_bg" accept="image/jpeg,image/png"><p class="description">A4 hochkant als JPEG oder PNG, ohne Name, Mitgliedsnummer und Datum (Unterschriften sind enthalten). Die Datei wird nur in der Datenbank gespeichert, nicht öffentlich.</p></td></tr>';
    echo '</tbody></table>';
    submit_button('Speichern');
    echo '</form><h2>Test</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('et_onboarding_test');
    echo '<input type="hidden" name="action" value="et_onboarding_test"><p>Schickt dir das Begrüßungsschreiben mit einer Muster-Urkunde. Der Link darin ist ein Platzhalter.</p>';
    submit_button('Test-E-Mail an mich senden', 'secondary');
    echo '</form></div>';
}

add_action('admin_post_et_onboarding_settings', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Keine Berechtigung.', '', ['response' => 403]);
    }
    check_admin_referer('et_onboarding_settings');
    $reply = sanitize_email(post_text('reply_to', '', 200));
    update_option(
        'eintrikot_onboarding',
        [
            'from_name' => sanitize_text_field(post_text('from_name', '', 100)),
            'reply_to' => is_email($reply) ? $reply : get_option('admin_email'),
            'signature' => sanitize_textarea_field(post_text('signature', '', 300))
        ],
        false
    );
    $file = $_FILES['certificate_bg'] ?? null;
    if (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $info =
            ($file['error'] ?? 1) === UPLOAD_ERR_OK &&
            is_uploaded_file($file['tmp_name']) &&
            $file['size'] < 6 * 1024 * 1024
                ? wp_getimagesize($file['tmp_name'])
                : false;
        if (!$info || !in_array($info['mime'], ['image/jpeg', 'image/png'], true) || $info[1] <= $info[0]) {
            wp_safe_redirect(
                admin_url(
                    'admin.php?page=eintrikot-onboarding&error=' .
                        rawurlencode('Bitte ein A4-Bild hochkant als JPEG oder PNG bis 6 MB hochladen.')
                )
            );
            exit();
        }
        $image = imagecreatefromstring(file_get_contents($file['tmp_name']));
        // Store as JPEG, at most 1654 px wide (200 dpi on A4).
        if ($image && imagesx($image) > 1654) {
            $image = imagescale($image, 1654);
        }
        ob_start();
        imagejpeg($image, null, 88);
        update_option('eintrikot_certificate_bg', base64_encode(ob_get_clean()), false);
    }
    wp_safe_redirect(admin_url('admin.php?page=eintrikot-onboarding&saved=1'));
    exit();
});

add_action('admin_post_et_certificate_preview', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Keine Berechtigung.', '', ['response' => 403]);
    }
    check_admin_referer('et_certificate_preview');
    $jpeg = certificate_jpeg('Vorname Nachname', '999', wp_date('Y-m-d'));
    if (is_wp_error($jpeg)) {
        wp_die(esc_html($jpeg->get_error_message()));
    }
    nocache_headers();
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="Muster-Mitgliedsurkunde.pdf"');
    echo jpeg_to_pdf($jpeg, 'Muster-Mitgliedsurkunde');
    exit();
});

add_action('admin_post_et_onboarding_test', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Keine Berechtigung.', '', ['response' => 403]);
    }
    check_admin_referer('et_onboarding_test');
    $me = wp_get_current_user();
    $result = send_welcome_mail(
        $me->user_email,
        first_name($me->display_name) ?: 'Vorname',
        $me->user_email,
        '999',
        home_url('/#test-link'),
        certificate_ready()
            ? jpeg_to_pdf(certificate_jpeg($me->display_name, '999', wp_date('Y-m-d')), 'Muster')
            : ''
    );
    wp_safe_redirect(
        admin_url(
            'admin.php?page=eintrikot-onboarding&' .
                ($result
                    ? 'test=1'
                    : 'error=' .
                        rawurlencode('Die E-Mail konnte nicht verschickt werden. Bitte SMTP prüfen.'))
        )
    );
    exit();
});

/* ---------- Reading the MeinVerein export ---------- */

/** Our fields and the column titles we recognise for them (compared without spaces, dashes, dots). */
function import_fields() {
    return [
        'first_name' => ['Vorname', ['vorname', 'firstname', 'rufname']],
        'last_name' => ['Nachname', ['nachname', 'familienname', 'lastname', 'name']],
        'email' => ['E-Mail', ['email', 'emailadresse', 'mail', 'emailprivat', 'eMail']],
        'number' => [
            'Mitgliedsnummer',
            ['mitgliedsnummer', 'mitgliedsnr', 'mitgliednr', 'mitgliednummer', 'nr', 'nummer']
        ],
        'joined' => [
            'Eintrittsdatum',
            ['eintrittsdatum', 'eintritt', 'eintrittam', 'mitgliedseit', 'beitrittsdatum', 'beitritt']
        ]
    ];
}

function normalize_header($text) {
    return preg_replace('/[^a-z0-9]/', '', mb_strtolower(remove_accents(trim((string) $text))));
}

/** Rows of the first worksheet of an .xlsx file (array of arrays), or WP_Error. */
function read_xlsx($path) {
    if (!class_exists('\ZipArchive')) {
        return new \WP_Error(
            'import',
            'Der Server kann keine Excel-Dateien öffnen. Bitte als CSV exportieren.'
        );
    }
    $zip = new \ZipArchive();
    if ($zip->open($path) !== true) {
        return new \WP_Error('import', 'Die Excel-Datei lässt sich nicht öffnen.');
    }
    $strings = [];
    $shared = $zip->getFromName('xl/sharedStrings.xml');
    if ($shared !== false) {
        $xml = simplexml_load_string($shared, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
        foreach ($xml ? $xml->si : [] as $si) {
            $text = isset($si->t) ? (string) $si->t : '';
            foreach ($si->r ?? [] as $run) {
                $text .= (string) $run->t;
            }
            $strings[] = $text;
        }
    }
    // First sheet in workbook order.
    $sheet = 'xl/worksheets/sheet1.xml';
    $workbook = simplexml_load_string(
        (string) $zip->getFromName('xl/workbook.xml'),
        'SimpleXMLElement',
        LIBXML_NONET
    );
    $rels = simplexml_load_string(
        (string) $zip->getFromName('xl/_rels/workbook.xml.rels'),
        'SimpleXMLElement',
        LIBXML_NONET
    );
    if ($workbook && $rels && isset($workbook->sheets->sheet[0])) {
        $rid = (string) $workbook->sheets->sheet[0]->attributes(
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships'
        )->id;
        foreach ($rels->Relationship as $rel) {
            if ((string) $rel['Id'] === $rid) {
                $target = ltrim((string) $rel['Target'], '/');
                $sheet = str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
            }
        }
    }
    $data = $zip->getFromName($sheet);
    $zip->close();
    if ($data === false) {
        return new \WP_Error('import', 'In der Excel-Datei wurde keine Tabelle gefunden.');
    }
    $xml = simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NONET);
    if (!$xml) {
        return new \WP_Error('import', 'Die Excel-Tabelle kann nicht gelesen werden.');
    }
    $rows = [];
    foreach ($xml->sheetData->row as $row) {
        $cells = [];
        foreach ($row->c as $c) {
            preg_match('/^([A-Z]+)/', (string) $c['r'], $m);
            $col = 0;
            foreach (str_split($m[1] ?? 'A') as $ch) {
                $col = $col * 26 + (ord($ch) - 64);
            }
            $type = (string) $c['t'];
            $value = $type === 'inlineStr' ? (string) $c->is->t : (string) $c->v;
            if ($type === 's') {
                $value = $strings[(int) $value] ?? '';
            }
            $cells[$col - 1] = $value;
        }
        if ($cells) {
            $max = max(array_keys($cells));
            $rows[] = array_map(fn($i) => $cells[$i] ?? '', range(0, $max));
        }
        if (count($rows) > 2000) {
            break;
        }
    }
    return $rows;
}

function read_csv($path) {
    $text = (string) file_get_contents($path, false, null, 0, 3 * 1024 * 1024);
    $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
    if (!mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
    }
    $first = strtok($text, "\n");
    $delimiter = ';';
    $best = -1;
    foreach ([';', ',', "\t"] as $d) {
        if (substr_count((string) $first, $d) > $best) {
            $best = substr_count((string) $first, $d);
            $delimiter = $d;
        }
    }
    $rows = [];
    $handle = fopen('php://temp', 'r+');
    fwrite($handle, $text);
    rewind($handle);
    while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false && count($rows) <= 2000) {
        $rows[] = $row;
    }
    fclose($handle);
    return $rows;
}

/** "16.12.2025", "2025-12-16" or an Excel day number -> "2025-12-16"; '' if unknown. */
function import_date($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    if (preg_match('/^\d{4,5}(\.\d+)?$/', $value)) {
        return gmdate('Y-m-d', (int) round(((float) $value - 25569) * 86400));
    }
    foreach (['d.m.Y', 'j.n.Y', 'd.m.y', 'Y-m-d', 'd/m/Y'] as $format) {
        $d = \DateTimeImmutable::createFromFormat('!' . $format, $value);
        if ($d && $d->format($format) === $value) {
            return $d->format('Y-m-d');
        }
    }
    return '';
}

/**
 * Keeps only the five columns we need. Returns ['headers'=>[], 'map'=>[], 'rows'=>[[field=>value]]].
 *
 * @param array<int,array<int,string>> $rows
 * @param array<string,int|string> $map field => column index (or '' to detect)
 */
function import_extract($rows, $map) {
    $header_row = 0;
    // The header is the first row that names at least two of our fields.
    foreach (array_slice($rows, 0, 10) as $i => $row) {
        $hits = 0;
        foreach ($row as $cell) {
            foreach (import_fields() as [$label, $aliases]) {
                if (in_array(normalize_header($cell), array_map('strtolower', $aliases), true)) {
                    $hits++;
                }
            }
        }
        if ($hits >= 2) {
            $header_row = $i;
            break;
        }
    }
    $headers = array_map(fn($h) => mb_substr(trim((string) $h), 0, 60), $rows[$header_row] ?? []);
    $normalized = array_map('Eintrikot\Community\normalize_header', $headers);
    foreach (import_fields() as $field => [$label, $aliases]) {
        if (isset($map[$field]) && $map[$field] !== '' && isset($headers[(int) $map[$field]])) {
            $map[$field] = (int) $map[$field];
            continue;
        }
        $map[$field] = '';
        foreach (array_map('strtolower', $aliases) as $alias) {
            $index = array_search($alias, $normalized, true);
            if ($index !== false) {
                $map[$field] = $index;
                break;
            }
        }
    }
    $out = [];
    foreach (array_slice($rows, $header_row + 1) as $row) {
        if (!array_filter(array_map('trim', array_map('strval', $row)))) {
            continue;
        }
        $item = [];
        foreach ($map as $field => $index) {
            $item[$field] = $index === '' ? '' : mb_substr(trim((string) ($row[$index] ?? '')), 0, 120);
        }
        $out[] = $item;
    }
    return ['headers' => $headers, 'map' => $map, 'rows' => $out];
}

/** Adds a status to every row: new, exists, invalid (with reason). */
function import_check($rows) {
    $seen = [];
    $numbers = [];
    foreach (get_users(['meta_key' => 'eintrikot_member_number', 'fields' => 'ID']) as $id) {
        $numbers[(string) (int) get_user_meta($id, 'eintrikot_member_number', true)] = (int) $id;
    }
    foreach ($rows as &$row) {
        $row['email'] = strtolower(sanitize_email($row['email']));
        $row['number'] = preg_replace('/\D/', '', $row['number']);
        $row['joined'] = import_date($row['joined']);
        $row['status'] = 'new';
        $row['reason'] = '';
        if ($row['first_name'] === '' && $row['last_name'] === '') {
            [$row['status'], $row['reason']] = ['invalid', 'Name fehlt'];
        } elseif (!is_email($row['email'])) {
            [$row['status'], $row['reason']] = ['invalid', 'E-Mail fehlt oder ist ungültig'];
        } elseif (isset($seen[$row['email']])) {
            [$row['status'], $row['reason']] = ['invalid', 'E-Mail kommt in der Datei doppelt vor'];
        } elseif (email_exists($row['email']) || username_exists($row['email'])) {
            [$row['status'], $row['reason']] = ['exists', 'Schon im Portal'];
        } elseif ($row['number'] !== '' && isset($numbers[(string) (int) $row['number']])) {
            [$row['status'], $row['reason']] = [
                'invalid',
                'Mitgliedsnummer gehört schon zu einem anderen Konto'
            ];
        }
        $seen[$row['email']] = true;
    }
    return $rows;
}

/* ---------- Accounts and invitations ---------- */

function create_member_account($row) {
    $name = trim($row['first_name'] . ' ' . $row['last_name']);
    $id = wp_insert_user([
        'user_login' => $row['email'],
        'user_email' => $row['email'],
        'user_pass' => wp_generate_password(40, true, true),
        'display_name' => $name,
        'first_name' => $row['first_name'],
        'last_name' => $row['last_name'],
        'role' => 'eintrikot_member'
    ]);
    if (is_wp_error($id)) {
        return $id;
    }
    update_user_meta($id, 'eintrikot_member_number', $row['number']);
    update_user_meta($id, 'eintrikot_joined', $row['joined']);
    update_user_meta($id, 'eintrikot_source', 'meinverein');
    log_change(
        $id,
        'account',
        null,
        ['Mitgliedsnummer' => $row['number'], 'Eintritt' => $row['joined']],
        'Aufnahme aus MeinVerein'
    );
    return $id;
}

/** Sends the welcome letter with certificate and a personal set-password link. */
function send_invitation($user_id) {
    $user = get_user_by('id', $user_id);
    if (!$user || !user_can($user, 'eintrikot_portal')) {
        return new \WP_Error('invite', 'Kein Portalkonto.');
    }
    $key = get_password_reset_key($user);
    if (is_wp_error($key)) {
        return $key;
    }
    $link = network_site_url(
        'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode($user->user_login),
        'login'
    );
    $existing = get_user_meta($user_id, 'eintrikot_invite_type', true) === 'existing';
    $pdf =
        !$existing && get_user_meta($user_id, 'eintrikot_member_number', true) !== '' && certificate_ready()
            ? member_certificate_pdf($user_id)
            : '';
    $sent = send_welcome_mail(
        $user->user_email,
        first_name($user->display_name),
        $user->user_login,
        (string) get_user_meta($user_id, 'eintrikot_member_number', true),
        $link,
        is_wp_error($pdf) ? '' : $pdf,
        $user_id,
        $existing
    );
    if (!$sent) {
        return new \WP_Error('invite', 'E-Mail an ' . $user->user_email . ' konnte nicht verschickt werden.');
    }
    $count = (int) get_user_meta($user_id, 'eintrikot_invite_count', true) + 1;
    update_user_meta($user_id, 'eintrikot_invited_at', time());
    update_user_meta($user_id, 'eintrikot_invite_count', $count);
    log_change($user_id, 'invitation', null, 'verschickt (' . $count . '.)', 'Einladung zum Portal');
    return true;
}

function send_welcome_mail($to, $first_name, $login, $number, $link, $pdf, $user_id = 0, $existing = false) {
    $s = onboarding_settings();
    $portal = get_permalink((int) get_option('eintrikot_portal_page')) ?: home_url('/');
    $number_label = member_number_label($number);
    $p = fn($text) => '<p style="margin:0 0 16px;font-size:16px;line-height:1.6">' . $text . '</p>';
    $body =
        '<div style="background:#edf5f8;padding:24px 12px;font-family:Montserrat,Arial,sans-serif;color:#000"><div style="max-width:560px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden"><div style="height:10px;background:linear-gradient(90deg,#f1d1dd,#c8e2ee)"></div><div style="padding:28px 28px 8px"><p style="margin:0 0 24px;font-weight:800;font-size:20px;letter-spacing:.02em">EINTRIKOT</p>' .
        $p('Hallo ' . esc_html($first_name) . ',') .
        ($existing
            ? $p(
                'unser neues Mitgliederportal ist da. Dort findest du ab sofort andere Mitglieder, Vereinsinfos und Termine – und dein eigenes Profil.'
            )
            : $p(
                'willkommen bei EINTRIKOT – schön, dass du dabei bist. Im Anhang findest du deine persönliche Mitgliedsurkunde' .
                    ($number_label !== ''
                        ? ' mit deiner Mitgliedsnummer <strong>' . esc_html($number_label) . '</strong>'
                        : '') .
                    '.'
            )) .
        $p('<em>Du hast das Trikot getragen. Jetzt trägst du es weiter.</em>') .
        '<h2 style="margin:28px 0 12px;font-size:18px">Dein Zugang zum Mitgliederportal</h2>' .
        $p(
            'Im Portal findest du andere Mitglieder, Vereinsinfos und Termine. Dein Profil pflegst du selbst – du entscheidest, was andere Mitglieder sehen.'
        ) .
        '<table role="presentation" style="width:100%;border-collapse:collapse;margin:0 0 20px;font-size:15px"><tr><td style="padding:10px 0;border-top:1px solid #e4e4e4;color:#595959;width:40%">Benutzername</td><td style="padding:10px 0;border-top:1px solid #e4e4e4"><strong>' .
        esc_html($login) .
        '</strong></td></tr><tr><td style="padding:10px 0;border-top:1px solid #e4e4e4;border-bottom:1px solid #e4e4e4;color:#595959">Passwort</td><td style="padding:10px 0;border-top:1px solid #e4e4e4;border-bottom:1px solid #e4e4e4">legst du selbst fest</td></tr></table>' .
        '<p style="margin:0 0 20px"><a href="' .
        esc_url($link) .
        '" style="display:inline-block;background:#000;color:#fff;text-decoration:none;font-weight:700;padding:14px 26px;border-radius:999px">Passwort festlegen</a></p>' .
        $p(
            '<span style="font-size:14px;color:#595959">Der Link gilt ' .
                INVITE_VALID_DAYS .
                ' Tage und nur einmal. Danach meldest du dich hier an: <a href="' .
                esc_url($portal) .
                '" style="color:#000">' .
                esc_html(preg_replace('#^https?://#', '', $portal)) .
                '</a>. Ist der Link abgelaufen, klicke dort auf „Passwort vergessen?“. Wir schicken dir nie ein Passwort per E-Mail und fragen auch nie danach.</span>'
        ) .
        $p('Fragen? Antworte einfach auf diese E-Mail.') .
        $p('Liebe Grüße<br>' . nl2br(esc_html($s['signature'])) . '<br>für EINTRIKOT e.V.') .
        '</div></div></div>';
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $s['from_name'] . ' <' . get_option('admin_email') . '>',
        'Reply-To: ' . $s['reply_to']
    ];
    $attachments = [];
    if ($pdf !== '') {
        $file =
            trailingslashit(get_temp_dir()) .
            ($user_id ? certificate_filename($user_id) : 'Muster-Mitgliedsurkunde.pdf');
        file_put_contents($file, $pdf);
        $attachments[] = $file;
    }
    $sent = wp_mail(
        $to,
        $existing
            ? 'Dein Zugang zum neuen EINTRIKOT-Mitgliederportal'
            : 'Willkommen bei EINTRIKOT – deine Mitgliedsurkunde und dein Zugang',
        $body,
        $headers,
        $attachments
    );
    foreach ($attachments as $file) {
        wp_delete_file($file);
    }
    return $sent;
}

/* ---------- Password link: 14 days for invitations, minimum length, activation date ---------- */

add_filter(
    'password_reset_key_expired',
    function ($expired, $user_id) {
        $invited = (int) get_user_meta($user_id, 'eintrikot_invited_at', true);
        if ($expired && $invited && !get_user_meta($user_id, 'eintrikot_activated_at', true)) {
            return time() - $invited > INVITE_VALID_DAYS * DAY_IN_SECONDS;
        }
        return $expired;
    },
    10,
    2
);
add_action(
    'validate_password_reset',
    function ($errors, $user) {
        $pass = $_POST['pass1'] ?? '';
        if (is_string($pass) && $pass !== '' && mb_strlen(wp_unslash($pass)) < MIN_PASSWORD_LENGTH) {
            $errors->add(
                'password_too_short',
                'Bitte ein Passwort mit mindestens ' .
                    MIN_PASSWORD_LENGTH .
                    ' Zeichen wählen. Tipp: mehrere Wörter hintereinander sind sicher und gut zu merken.'
            );
        }
    },
    10,
    2
);
add_action('after_password_reset', function ($user) {
    if (!get_user_meta($user->ID, 'eintrikot_activated_at', true)) {
        update_user_meta($user->ID, 'eintrikot_activated_at', time());
        log_change($user->ID, 'activation', null, 'Passwort festgelegt', 'Portalzugang aktiviert');
    }
});
// Hint on the "set new password" page of WordPress.
add_filter('login_message', function ($message) {
    $action = isset($_GET['action']) && is_string($_GET['action']) ? $_GET['action'] : '';
    if (in_array($action, ['rp', 'resetpass'], true)) {
        $message .=
            '<p class="message">Lege jetzt dein persönliches Passwort fest: mindestens ' .
            MIN_PASSWORD_LENGTH .
            ' Zeichen. Am sichersten sind mehrere Wörter hintereinander, zum Beispiel ein kurzer Satz. Danach meldest du dich mit deiner E-Mail-Adresse an.</p>';
    }
    return $message;
});

// Members land in the portal after signing in, not in the WordPress dashboard.
add_filter(
    'login_redirect',
    function ($redirect, $requested, $user) {
        if (
            $user instanceof \WP_User &&
            user_can($user, 'eintrikot_portal') &&
            !user_can($user, 'edit_posts')
        ) {
            return (int) get_option('eintrikot_portal_page') ? portal_url() : $redirect;
        }
        return $redirect;
    },
    10,
    3
);

/* ---------- Portal view: Verwaltung → Neue Mitglieder aufnehmen ---------- */

function invite_state($user_id) {
    if (get_user_meta($user_id, 'eintrikot_activated_at', true)) {
        return [
            'active',
            'Aktiv seit ' . wp_date('d.m.Y', (int) get_user_meta($user_id, 'eintrikot_activated_at', true))
        ];
    }
    $invited = (int) get_user_meta($user_id, 'eintrikot_invited_at', true);
    if ($invited) {
        $expired = time() - $invited > INVITE_VALID_DAYS * DAY_IN_SECONDS;
        return [
            $expired ? 'expired' : 'invited',
            ($expired ? 'Link abgelaufen · eingeladen am ' : 'Eingeladen am ') . wp_date('d.m.Y', $invited)
        ];
    }
    return ['open', 'Noch nicht eingeladen'];
}

function render_onboarding() {
    if (!manager_access()) {
        echo '<div class="portal-empty"><h1>Kein Zugriff</h1></div>';
        return;
    }
    $key = 'et_import_' . get_current_user_id();
    $import = get_transient($key);
    $notice = get_transient($key . '_notice');
    delete_transient($key . '_notice');
    echo '<a class="text-link service-back" href="' .
        esc_url(portal_url('admin')) .
        '">← Verwaltung</a>' .
        page_head(
            'Neue Mitglieder aufnehmen',
            'Aus MeinVerein übernehmen, Konten anlegen, Begrüßung mit Urkunde und Zugangslink verschicken.',
            '',
            'admin'
        );
    if (is_array($notice)) {
        echo '<div class="' .
            ($notice['ok'] ? 'portal-success' : 'form-error') .
            '" role="status"><p>' .
            esc_html($notice['text']) .
            '</p></div>';
    }
    if (!wp_is_using_https() && !str_starts_with(home_url(), 'https://')) {
        echo '<div class="onboarding-warning"><strong>Hinweis:</strong> Die Website läuft noch ohne HTTPS. Links zum Festlegen des Passworts werden dann unverschlüsselt übertragen. Einladungen an alle Mitglieder am besten erst nach dem Umzug auf eintrikot.de verschicken; Tests mit eigenen Konten sind unkritisch.</div>';
    }
    if (!certificate_ready()) {
        echo '<div class="onboarding-warning"><strong>Urkunde:</strong> Es ist noch keine Urkunden-Vorlage hinterlegt' .
            (current_user_can('manage_options')
                ? ' (<a class="text-link" href="' .
                    esc_url(admin_url('admin.php?page=eintrikot-onboarding')) .
                    '">jetzt hochladen</a>)'
                : '') .
            '. Einladungen gehen dann ohne Urkunde raus.</div>';
    }

    // Step 1: upload.
    echo '<section class="portal-section onboarding-step"><h2><span class="step-no">1</span> Export aus MeinVerein hochladen</h2><ol class="onboarding-howto"><li>In MeinVerein die neuen Mitglieder filtern (zum Beispiel Eintritt seit der letzten Aufnahme).</li><li>Die Liste als Excel exportieren. Am besten nur die Spalten Vorname, Nachname, E-Mail, Mitgliedsnummer und Eintrittsdatum.</li><li>Die Datei hier hochladen. Alle anderen Spalten werden ignoriert und nicht gespeichert.</li></ol><form class="et-form" method="post" enctype="multipart/form-data" action="' .
        esc_url(admin_url('admin-post.php')) .
        '">';
    wp_nonce_field('et_import_upload');
    echo '<input type="hidden" name="action" value="et_import_upload"><label>Excel- oder CSV-Datei<input type="file" name="import" accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required></label>';
    if (is_array($import) && $import['headers']) {
        echo '<details class="import-mapping"' .
            (in_array('', array_intersect_key($import['map'], array_flip(['email', 'number'])), true)
                ? ' open'
                : '') .
            '><summary>Spalten zuordnen</summary><p>Nur nötig, wenn eine Spalte nicht erkannt wurde. Danach die Datei noch einmal hochladen.</p><div class="filter-fields">';
        foreach (import_fields() as $field => [$label]) {
            echo '<label>' .
                esc_html($label) .
                '<select name="map[' .
                esc_attr($field) .
                ']"><option value="">Nicht vorhanden</option>';
            foreach ($import['headers'] as $i => $header) {
                echo '<option value="' .
                    (int) $i .
                    '" ' .
                    selected($import['map'][$field], $i, false) .
                    '>' .
                    esc_html($header !== '' ? $header : 'Spalte ' . ($i + 1)) .
                    '</option>';
            }
            echo '</select></label>';
        }
        echo '</div></details>';
    }
    echo '<button class="button solid">Datei prüfen</button></form></section>';

    // Step 2: preview and create.
    if (is_array($import) && $import['rows']) {
        $rows = import_check($import['rows']);
        $new = array_filter($rows, fn($r) => $r['status'] === 'new');
        echo '<section class="portal-section onboarding-step"><h2><span class="step-no">2</span> Vorschau prüfen</h2><p>' .
            esc_html(
                count($rows) .
                    ' Zeilen gelesen · ' .
                    count($new) .
                    ' neu · ' .
                    (count($rows) - count($new)) .
                    ' übersprungen'
            ) .
            '</p><form method="post" action="' .
            esc_url(admin_url('admin-post.php')) .
            '">';
        wp_nonce_field('et_import_create');
        echo '<input type="hidden" name="action" value="et_import_create"><div class="table-scroll"><table class="import-table"><thead><tr><th scope="col"><span class="screen-reader-text">Übernehmen</span></th><th scope="col">Name</th><th scope="col">E-Mail</th><th scope="col">Nr.</th><th scope="col">Eintritt</th><th scope="col">Status</th></tr></thead><tbody>';
        foreach ($rows as $i => $r) {
            $ok = $r['status'] === 'new';
            echo '<tr class="is-' .
                esc_attr($r['status']) .
                '"><td>' .
                ($ok
                    ? '<input type="checkbox" name="rows[]" value="' .
                        (int) $i .
                        '" checked aria-label="' .
                        esc_attr(trim($r['first_name'] . ' ' . $r['last_name']) . ' übernehmen') .
                        '">'
                    : '') .
                '</td><td>' .
                esc_html(trim($r['first_name'] . ' ' . $r['last_name'])) .
                '</td><td>' .
                esc_html($r['email']) .
                '</td><td>' .
                esc_html(member_number_label($r['number'])) .
                '</td><td>' .
                esc_html($r['joined'] ? wp_date('d.m.Y', strtotime($r['joined'] . ' 12:00')) : '–') .
                '</td><td>' .
                ($ok
                    ? '<span class="request-status status-adopted">Neu</span>'
                    : '<span class="request-status status-' .
                        ($r['status'] === 'exists' ? 'received' : 'rejected') .
                        '">' .
                        esc_html($r['reason']) .
                        '</span>') .
                '</td></tr>';
        }
        echo '</tbody></table></div>';
        if ($new) {
            echo '<fieldset class="import-choice"><legend>Was soll passieren?</legend><label class="check"><input type="radio" name="invite" value="now" checked><span>Konten anlegen und Begrüßung mit Urkunde und Zugangslink sofort senden (höchstens ' .
                INVITE_BATCH .
                ' auf einmal, der Rest wird unten zum Nachsenden angeboten)</span></label><label class="check"><input type="radio" name="invite" value="later"><span>Nur Konten anlegen, Begrüßung später senden</span></label><label class="check"><input type="radio" name="invite" value="existing"><span>Bestandsmitglieder, die ihre Urkunde schon haben: Konten anlegen, später nur den Portalzugang schicken (ohne Urkunde)</span></label></fieldset><button class="button solid">Ausgewählte übernehmen</button>';
        }
        echo '</form></section>';
    }

    // Step 3: status of all accounts from MeinVerein.
    $members = get_users([
        'meta_key' => 'eintrikot_member_number',
        'orderby' => 'registered',
        'order' => 'DESC',
        'number' => 200
    ]);
    $open = array_filter($members, fn($u) => invite_state($u->ID)[0] === 'open');
    echo '<section class="portal-section onboarding-step"><h2><span class="step-no">3</span> Einladungen</h2>';
    if (!$members) {
        echo '<p class="portal-empty">Noch keine Konten aus MeinVerein angelegt.</p></section>';
        return;
    }
    if ($open) {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="invite-bulk">';
        wp_nonce_field('et_invite');
        echo '<input type="hidden" name="action" value="et_invite"><input type="hidden" name="scope" value="open"><button class="button solid">' .
            esc_html(
                min(count($open), INVITE_BATCH) .
                    ' ' .
                    (count($open) === 1 ? 'Einladung' : 'Einladungen') .
                    ' jetzt senden'
            ) .
            '</button><span>' .
            esc_html(count($open) . ' noch nicht eingeladen') .
            '</span></form>';
    }
    echo '<div class="table-scroll"><table class="import-table"><thead><tr><th scope="col">Name</th><th scope="col">Nr.</th><th scope="col">Stand</th><th scope="col"><span class="screen-reader-text">Aktionen</span></th></tr></thead><tbody>';
    foreach ($members as $u) {
        [$state, $label] = invite_state($u->ID);
        echo '<tr><td><a class="text-link" href="' .
            esc_url(portal_url('member', ['member' => $u->ID])) .
            '">' .
            esc_html($u->display_name) .
            '</a><br><small>' .
            esc_html($u->user_email) .
            '</small></td><td>' .
            esc_html(member_number_label(get_user_meta($u->ID, 'eintrikot_member_number', true))) .
            '</td><td><span class="request-status status-' .
            esc_attr(
                ['active' => 'adopted', 'invited' => 'review', 'expired' => 'rejected', 'open' => 'received'][
                    $state
                ]
            ) .
            '">' .
            esc_html($label) .
            '</span></td><td class="row-actions">' .
            (certificate_ready()
                ? '<a class="text-link" href="' .
                    esc_url(portal_url('certificate', ['member' => $u->ID])) .
                    '" target="_blank" rel="noopener">Urkunde</a>'
                : '');
        if ($state !== 'active') {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('et_invite');
            echo '<input type="hidden" name="action" value="et_invite"><input type="hidden" name="user" value="' .
                (int) $u->ID .
                '"><button class="text-reset">' .
                ($state === 'open' ? 'Einladen' : 'Erneut senden') .
                '</button></form>';
        }
        echo '</td></tr>';
    }
    echo '</tbody></table></div></section>';
}

function onboarding_notice($ok, $text) {
    set_transient('et_import_' . get_current_user_id() . '_notice', ['ok' => $ok, 'text' => $text], 300);
    wp_safe_redirect(portal_url('onboarding'));
    exit();
}

add_action('admin_post_et_import_upload', function () {
    if (!manager_access()) {
        wp_die('Keine Berechtigung.', '', ['response' => 403]);
    }
    check_admin_referer('et_import_upload');
    $file = $_FILES['import'] ?? null;
    if (
        !is_array($file) ||
        ($file['error'] ?? 1) !== UPLOAD_ERR_OK ||
        !is_uploaded_file($file['tmp_name']) ||
        $file['size'] > 3 * 1024 * 1024
    ) {
        onboarding_notice(false, 'Bitte eine Excel- oder CSV-Datei bis 3 MB auswählen.');
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
    $map =
        isset($_POST['map']) && is_array($_POST['map'])
            ? array_map(fn($v) => is_scalar($v) && $v !== '' ? absint($v) : '', wp_unslash($_POST['map']))
            : [];
    $import = import_extract($rows, array_intersect_key($map, import_fields()));
    // Only the five fields are kept, for 30 minutes, for this administrator.
    set_transient('et_import_' . get_current_user_id(), $import, 30 * MINUTE_IN_SECONDS);
    $missing = array_keys(array_filter($import['map'], fn($v) => $v === ''));
    $labels = array_map(fn($f) => import_fields()[$f][0], array_intersect($missing, ['email', 'last_name']));
    onboarding_notice(
        !$labels,
        $labels
            ? 'Nicht erkannt: ' .
                implode(', ', $labels) .
                '. Bitte unter „Spalten zuordnen“ auswählen und die Datei erneut hochladen.'
            : count($import['rows']) . ' Zeilen gelesen. Bitte die Vorschau prüfen.'
    );
});

add_action('admin_post_et_import_create', function () {
    if (!manager_access()) {
        wp_die('Keine Berechtigung.', '', ['response' => 403]);
    }
    check_admin_referer('et_import_create');
    $import = get_transient('et_import_' . get_current_user_id());
    if (!is_array($import)) {
        onboarding_notice(false, 'Die Vorschau ist abgelaufen. Bitte die Datei erneut hochladen.');
    }
    $rows = import_check($import['rows']);
    $chosen = array_map('absint', is_array($_POST['rows'] ?? null) ? $_POST['rows'] : []);
    $invite = ($_POST['invite'] ?? '') === 'now';
    $existing = ($_POST['invite'] ?? '') === 'existing';
    $created = 0;
    $invited = 0;
    $errors = [];
    foreach ($chosen as $i) {
        if (!isset($rows[$i]) || $rows[$i]['status'] !== 'new') {
            continue;
        }
        $id = create_member_account($rows[$i]);
        if (is_wp_error($id)) {
            $errors[] = $rows[$i]['email'] . ': ' . $id->get_error_message();
            continue;
        }
        $created++;
        if ($existing) {
            update_user_meta($id, 'eintrikot_invite_type', 'existing');
        }
        if ($invite && $invited < INVITE_BATCH) {
            $result = send_invitation($id);
            is_wp_error($result) ? ($errors[] = $result->get_error_message()) : $invited++;
        }
    }
    delete_transient('et_import_' . get_current_user_id());
    onboarding_notice(
        !$errors,
        $created .
            ' ' .
            ($created === 1 ? 'Konto' : 'Konten') .
            ' angelegt, ' .
            $invited .
            ' ' .
            ($invited === 1 ? 'Einladung' : 'Einladungen') .
            ' verschickt.' .
            ($errors ? ' Probleme: ' . implode(' · ', array_slice($errors, 0, 5)) : '')
    );
});

add_action('admin_post_et_invite', function () {
    if (!manager_access()) {
        wp_die('Keine Berechtigung.', '', ['response' => 403]);
    }
    check_admin_referer('et_invite');
    $ids = [];
    if (($_POST['scope'] ?? '') === 'open') {
        foreach (
            get_users(['meta_key' => 'eintrikot_member_number', 'fields' => 'ID', 'number' => 500])
            as $id
        ) {
            if (invite_state((int) $id)[0] === 'open') {
                $ids[] = (int) $id;
            }
        }
    } else {
        $ids[] = absint($_POST['user'] ?? 0);
    }
    $sent = 0;
    $errors = [];
    foreach (array_slice($ids, 0, INVITE_BATCH) as $id) {
        $result = send_invitation($id);
        is_wp_error($result) ? ($errors[] = $result->get_error_message()) : $sent++;
    }
    onboarding_notice(
        !$errors,
        $sent .
            ' ' .
            ($sent === 1 ? 'Einladung' : 'Einladungen') .
            ' verschickt.' .
            ($errors ? ' ' . implode(' · ', array_slice($errors, 0, 5)) : '')
    );
});
