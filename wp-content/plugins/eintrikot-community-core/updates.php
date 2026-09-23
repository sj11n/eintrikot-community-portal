<?php
/**
 * Inhaltliche Aktualisierungen bestehender Seiten, einzeln und bewusst auszulösen.
 *
 * Jede Aktualisierung prüft vorher, ob die Seite noch dem erwarteten Stand entspricht.
 * Wurde sie inzwischen im Editor bearbeitet, wird nichts überschrieben. WordPress legt
 * bei jeder Änderung eine Revision an, sodass sich jeder Schritt unter Seiten → Revisionen
 * zurücknehmen lässt.
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
                'Ersetzt den Platzhaltertext durch Beitrittskriterien, Beitrag, freiwillige Jahresspende, Ablauf und den Beitritts-Button (erscheint erst, wenn unter „Beitritt" ein MeinVerein-Link hinterlegt ist).',
            'run' => __NAMESPACE__ . '\update_join_page'
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

/** Makes root-relative links in pattern markup absolute, like the MVP import did. */
function absolutize_pattern_links($content) {
    return preg_replace_callback(
        '/(href|src)="(\/[^"\s]*)"/',
        fn($m) => $m[1] . '="' . esc_url(home_url(html_entity_decode($m[2]))) . '"',
        $content
    );
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

function update_join_page() {
    $page = get_page_by_path('mitglied-werden');
    if (!$page) {
        return new \WP_Error('missing', 'Die Seite „Mitglied werden" wurde nicht gefunden.');
    }
    if (!str_contains($page->post_content, 'Der direkte Online-Antrag wird hier ergänzt')) {
        return new \WP_Error(
            'edited',
            'Die Seite wurde bereits bearbeitet und wird nicht überschrieben. Die neue Fassung steht im Editor als Vorlage „EINTRIKOT – beitritt" bereit.'
        );
    }
    $content = pattern_markup('mvp-beitritt');
    if (is_wp_error($content)) {
        return $content;
    }
    $result = wp_update_post(
        wp_slash(['ID' => $page->ID, 'post_content' => absolutize_pattern_links($content)]),
        true
    );
    return is_wp_error($result) ? $result : 'Seite „Mitglied werden" aktualisiert.';
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
    echo '<div class="wrap"><h1>Aktualisierungen</h1><p>Jede Aktualisierung ändert nur die genannte Seite. Wurde sie inzwischen bearbeitet, wird nichts überschrieben. Jede Änderung lässt sich über die Revisionen der Seite zurücknehmen.</p>';
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
