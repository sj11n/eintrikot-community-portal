<?php
/**
 * Inhaltliche Aktualisierungen bestehender Seiten, einzeln und bewusst auszulösen.
 *
 * Vollständiges Ersetzen einer Seite nur, wenn ihr Inhalt exakt einem bekannten
 * Ausgangsstand entspricht (Fingerabdruck). Gezielte Anpassungen ändern nur die
 * genannten Stellen. WordPress legt bei jeder Änderung eine Revision an.
 */
namespace Eintrikot\Community;
if (!defined('ABSPATH')) {
    exit();
}

function content_updates() {
    return [
        '0.7-beitritt' => [
            'title' => 'Seite „Mitglied werden" neu aufbauen',
            'text' =>
                'Ersetzt die Seite durch Beitrittskriterien, Beitrag, freiwillige Jahresspende, Ablauf und den Beitritts-Button (erscheint erst, wenn unter „Beitritt" ein MeinVerein-Link hinterlegt ist). Nur wenn die Seite noch exakt dem Ausgangsstand entspricht.',
            'run' => __NAMESPACE__ . '\update_join_page'
        ],
        '0.7-relative-links' => [
            'title' => 'Interne Links domainunabhängig machen',
            'text' =>
                'Wandelt in Seiten, Beiträgen, Vereinsinfos, Navigation und Vorlagenteilen alle Links und Bilder, die auf diese Website zeigen, in relative Adressen um (z. B. „/mitglied-werden/"). Dann funktionieren sie nach dem Umzug auf eine andere Domain unverändert. Links auf andere Websites und sichtbarer Text bleiben unberührt.',
            'run' => __NAMESPACE__ . '\make_internal_links_relative'
        ],
        '0.16-datenschutz' => [
            'title' => 'Datenschutzerklärung aktualisieren',
            'text' =>
                'Ersetzt den Inhalt der Seite „Datenschutz" durch die Datenschutzerklärung für www.eintrikot.de (Stand September 2026: STRATO, WISO MeinVerein, Google Workspace, EINTRIKOT-App, Minderjährige). Die bisherige Fassung bleibt als Revision erhalten.',
            'run' => __NAMESPACE__ . '\\update_privacy_page'
        ],
        '0.7-vision' => [
            'title' => 'Schreibweise „EINTRIKOT" in der Vision 2030',
            'text' =>
                'Ändert auf der Startseite „Bis 2030 ist Eintrikot" in „Bis 2030 ist EINTRIKOT". Sonst nichts.',
            'run' => __NAMESPACE__ . '\update_vision_spelling'
        ]
    ];
}

function applied_content_updates() {
    $done = get_option('eintrikot_content_updates', []);
    return is_array($done) ? $done : [];
}

function pattern_markup($slug) {
    $file = get_theme_file_path('patterns/' . $slug . '.php');
    if (!is_readable($file)) {
        return new \WP_Error('theme', 'Bitte zuerst das aktuelle Theme installieren.');
    }
    // Read authored block markup, never execute the pattern PHP.
    $parts = explode('?>', file_get_contents($file), 2);
    return isset($parts[1]) ? trim($parts[1]) : new \WP_Error('theme', 'Ungültige Seitenvorlage.');
}

/**
 * Content fingerprint for comparing a page with a known shipped state.
 * Ignores the site origin in links (absolute vs. relative) and whitespace, nothing else.
 */
function page_fingerprint($content) {
    $content = preg_replace('/(["\'(])https?:\/\/[^\/"\'\s)]+(?=\/)/i', '$1', (string) $content);
    return hash('sha256', trim(preg_replace('/\s+/', ' ', $content)));
}

/** Fingerprints of "Mitglied werden" as shipped before 0.7 (MVP import 0.5.0). */
const JOIN_PAGE_ORIGINALS = ['31f086317404b36ec9b113bfbd60ae972fd592695e3ad25f9e57257d94076c15'];

function update_join_page() {
    $page = get_page_by_path('mitglied-werden');
    if (!$page) {
        return new \WP_Error('missing', 'Die Seite „Mitglied werden" wurde nicht gefunden.');
    }
    $content = pattern_markup('mvp-beitritt');
    if (is_wp_error($content)) {
        return $content;
    }
    $current = page_fingerprint($page->post_content);
    if ($current === page_fingerprint($content)) {
        return 'Nichts zu ändern – die Seite entspricht bereits der neuen Fassung.';
    }
    // Replace the whole page only if it is exactly the shipped original; any edit blocks it.
    if (!in_array($current, JOIN_PAGE_ORIGINALS, true)) {
        return new \WP_Error(
            'edited',
            'Die Seite wurde gegenüber dem Ausgangsstand verändert und wird deshalb nicht ersetzt. Die neue Fassung steht im Editor als Vorlage „EINTRIKOT – beitritt" bereit.'
        );
    }
    $result = wp_update_post(wp_slash(['ID' => $page->ID, 'post_content' => $content]), true);
    return is_wp_error($result) ? $result : 'Seite „Mitglied werden" aktualisiert.';
}

function update_privacy_page() {
    $page = get_page_by_path('datenschutz');
    if (!$page) {
        return new \WP_Error('missing', 'Die Seite „Datenschutz" wurde nicht gefunden.');
    }
    $content = pattern_markup('mvp-datenschutz');
    if (is_wp_error($content)) {
        return $content;
    }
    if (page_fingerprint($page->post_content) === page_fingerprint($content)) {
        return 'Nichts zu ändern – die Seite entspricht bereits der neuen Fassung.';
    }
    // Legal text: always replaced as a whole; WordPress keeps the previous version as a revision.
    $result = wp_update_post(wp_slash(['ID' => $page->ID, 'post_content' => $content]), true);
    if (!is_wp_error($result) && (int) get_option('wp_page_for_privacy_policy') !== $page->ID) {
        update_option('wp_page_for_privacy_policy', $page->ID);
    }
    return is_wp_error($result) ? $result : 'Datenschutzerklärung aktualisiert.';
}

function update_vision_spelling() {
    $page = get_post((int) get_option('page_on_front'));
    if (!$page) {
        return new \WP_Error('missing', 'Startseite nicht gefunden.');
    }
    $count = 0;
    $content = str_replace('Bis 2030 ist Eintrikot ', 'Bis 2030 ist EINTRIKOT ', $page->post_content, $count);
    if (!$count) {
        return 'Nichts zu ändern – die Schreibweise stimmt bereits.';
    }
    $result = wp_update_post(wp_slash(['ID' => $page->ID, 'post_content' => $content]), true);
    return is_wp_error($result) ? $result : 'Schreibweise angepasst.';
}

/**
 * Replaces absolute links to this site with root-relative ones.
 *
 * Only attribute values are touched: the origin must directly follow a quote or "(",
 * as in href="…", src="…", "url":"…" (block attributes) or url(…) in inline styles.
 * Both plain and JSON-escaped slashes are handled. Visible text stays unchanged.
 */
function relative_site_links($content, $host) {
    $h = preg_quote($host, '/');
    // Bare origin without path, e.g. href="http://example.org" -> href="/".
    $content = preg_replace('/(["\'])https?:\/\/' . $h . '(?=\1)/i', '$1/', $content);
    $content = preg_replace('/(["\'(])https?:\/\/' . $h . '(?=\/)/i', '$1', $content);
    return preg_replace('/(["\'(])https?:\\\\\/\\\\\/' . $h . '(?=\\\\\/)/i', '$1', $content);
}

function make_internal_links_relative() {
    $host = wp_parse_url(home_url(), PHP_URL_HOST);
    $path = (string) wp_parse_url(home_url(), PHP_URL_PATH);
    if (!$host) {
        return new \WP_Error('host', 'Adresse der Website nicht ermittelbar.');
    }
    if (trim($path, '/') !== '') {
        return new \WP_Error(
            'subdir',
            'WordPress liegt in einem Unterordner; relative Links wären hier nicht sicher.'
        );
    }
    $posts = get_posts([
        'post_type' => [
            'page',
            'post',
            'et_info',
            'wp_navigation',
            'wp_template_part',
            'wp_template',
            'wp_block'
        ],
        'post_status' => ['publish', 'draft', 'private', 'pending', 'future'],
        'posts_per_page' => -1,
        'suppress_filters' => true
    ]);
    $changed = 0;
    foreach ($posts as $post) {
        $content = relative_site_links($post->post_content, $host);
        if ($content === $post->post_content) {
            continue;
        }
        $result = wp_update_post(wp_slash(['ID' => $post->ID, 'post_content' => $content]), true);
        if (is_wp_error($result)) {
            return $result;
        }
        $changed++;
    }
    return $changed
        ? sprintf('%d Inhalte angepasst. Links auf %s sind jetzt relativ.', $changed, $host)
        : 'Nichts zu ändern – keine absoluten Links auf diese Website gefunden.';
}

add_action('admin_menu', function () {
    add_submenu_page(
        'eintrikot-community-setup',
        'Aktualisierungen',
        'Aktualisierungen',
        'manage_options',
        'eintrikot-updates',
        __NAMESPACE__ . '\content_updates_page'
    );
});

function content_updates_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    $done = applied_content_updates();
    $notice = get_transient('et_update_notice_' . get_current_user_id());
    delete_transient('et_update_notice_' . get_current_user_id());
    echo '<div class="wrap"><h1>Aktualisierungen</h1><p>Jede Aktualisierung beschreibt genau, was sie ändert. Eine Seite wird nur dann vollständig ersetzt, wenn sie noch exakt dem Ausgangsstand entspricht – jede eigene Bearbeitung verhindert das. Gezielte Anpassungen (Links, Schreibweise) ändern nur die genannten Stellen und lassen den übrigen Inhalt unberührt. Jede Änderung legt eine Revision an und lässt sich unter Seiten → Revisionen zurücknehmen.</p>';
    if (is_array($notice)) {
        echo '<div class="notice notice-' .
            ($notice['ok'] ? 'success' : 'warning') .
            '"><p>' .
            esc_html($notice['text']) .
            '</p></div>';
    }
    echo '<table class="widefat striped" style="max-width:900px"><tbody>';
    foreach (content_updates() as $key => $update) {
        echo '<tr><td><strong>' .
            esc_html($update['title']) .
            '</strong><p>' .
            esc_html($update['text']) .
            '</p>';
        if (isset($done[$key])) {
            echo '<p><em>Übernommen am ' . esc_html(wp_date('d.m.Y H:i', (int) $done[$key])) . '.</em></p>';
        }
        echo '</td><td style="width:180px;vertical-align:middle">';
        if (!isset($done[$key])) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('et_content_update_' . $key);
            echo '<input type="hidden" name="action" value="et_content_update"><input type="hidden" name="update" value="' .
                esc_attr($key) .
                '">';
            submit_button('Übernehmen', 'primary', 'submit', false);
            echo '</form>';
        }
        echo '</td></tr>';
    }
    echo '</tbody></table></div>';
}

add_action('admin_post_et_content_update', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Keine Berechtigung.', '', ['response' => 403]);
    }
    $key = post_text('update', '', 40);
    $updates = content_updates();
    if (!isset($updates[$key])) {
        wp_die('Unbekannte Aktualisierung.', '', ['response' => 400]);
    }
    check_admin_referer('et_content_update_' . $key);
    $done = applied_content_updates();
    if (!isset($done[$key])) {
        $result = call_user_func($updates[$key]['run']);
        $ok = !is_wp_error($result);
        if ($ok) {
            $done[$key] = time();
            update_option('eintrikot_content_updates', $done, false);
        }
        set_transient(
            'et_update_notice_' . get_current_user_id(),
            ['ok' => $ok, 'text' => $ok ? (string) $result : $result->get_error_message()],
            120
        );
    }
    wp_safe_redirect(admin_url('admin.php?page=eintrikot-updates'));
    exit();
});
