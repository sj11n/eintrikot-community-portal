<?php
/**
 * Beitritt: Link zum MeinVerein-Online-Antrag und der dazugehörige Button.
 *
 * Solange kein Link hinterlegt ist, gibt der Button nichts aus.
 */
namespace Eintrikot\Community;
if (!defined('ABSPATH')) {
    exit();
}

function join_url() {
    $url = get_option('eintrikot_join_url', '');
    return is_string($url) && str_starts_with($url, 'https://') ? $url : '';
}

add_action('admin_menu', function () {
    add_submenu_page(
        'eintrikot-community-setup',
        'Beitritt',
        'Beitritt',
        'manage_options',
        'eintrikot-join',
        __NAMESPACE__ . '\join_settings_page'
    );
});

function join_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    echo '<div class="wrap"><h1>Beitritt</h1>';
    if (isset($_GET['saved'])) {
        echo '<div class="notice notice-success"><p>Gespeichert.</p></div>';
    }
    echo '<p>Link zum Online-Beitrittsantrag in MeinVerein. Solange das Feld leer ist, zeigt die Seite „Mitglied werden" keinen Beitritts-Button.</p>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('et_join');
    echo '<input type="hidden" name="action" value="et_join">';
    echo '<p><label for="et-join-url">MeinVerein-Link</label><br><input id="et-join-url" class="regular-text code" type="url" name="join_url" placeholder="https://" value="' .
        esc_attr(join_url()) .
        '"></p><p class="description">Nur https-Adressen. Tipp: Link vorher in einem privaten Browserfenster testen.</p>';
    submit_button('Speichern');
    echo '</form>';
    $stats = array_reverse(join_stats(), true);
    echo '<h2>Weg zum Antrag</h2><p>Anonym gezählt, ohne Cookies: wie oft das Hinweisfenster geöffnet und wie oft „weiter zum Antrag“ gewählt wurde. Wiederholte Klicks derselben Verbindung innerhalb einer Stunde zählen einmal. Vergleiche die zweite Zahl mit den Anträgen, die in MeinVerein eingegangen sind.</p>';
    if (!$stats) {
        echo '<p><em>Noch keine Zahlen.</em></p>';
    } else {
        echo '<table class="widefat striped" style="max-width:560px"><thead><tr><th>Monat</th><th>Hinweis geöffnet</th><th>Weiter zum Antrag</th></tr></thead><tbody>';
        foreach ($stats as $month => $row) {
            $d = \DateTimeImmutable::createFromFormat('!Y-m', (string) $month);
            printf(
                '<tr><td>%s</td><td>%d</td><td>%d</td></tr>',
                esc_html($d ? wp_date('F Y', $d->getTimestamp()) : $month),
                (int) ($row['open'] ?? 0),
                (int) ($row['go'] ?? 0)
            );
        }
        echo '</tbody></table>';
    }
    echo '</div>';
}

add_action('admin_post_et_join', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Keine Berechtigung.', '', ['response' => 403]);
    }
    check_admin_referer('et_join');
    $raw = trim(post_text('join_url', '', 500));
    $url = $raw === '' ? '' : esc_url_raw($raw, ['https']);
    if ($raw !== '' && ($url === '' || !wp_http_validate_url($url))) {
        wp_die('Bitte eine gültige https-Adresse eingeben.');
    }
    update_option('eintrikot_join_url', $url, false);
    wp_safe_redirect(admin_url('admin.php?page=eintrikot-join&saved=1'));
    exit();
});

/* ---------- Hinweisfenster vor dem Antrag und anonyme Zählung ---------- */

/** Monthly counts: how often the explanation was opened and how often "weiter zum Antrag" was chosen. */
function join_stats() {
    $stats = get_option('eintrikot_join_stats', []);
    return is_array($stats) ? $stats : [];
}

/**
 * Counts one step without cookies and without storing anything about the visitor. A salted hash of
 * the address only lives for an hour to ignore repeated clicks; it is never saved permanently.
 */
function join_count($step) {
    $marker =
        'et_join_' .
        $step .
        '_' .
        substr(hash_hmac('sha256', $_SERVER['REMOTE_ADDR'] ?? '', wp_salt('nonce')), 0, 20);
    if (get_transient($marker)) {
        return;
    }
    set_transient($marker, 1, HOUR_IN_SECONDS);
    $stats = join_stats();
    $month = wp_date('Y-m');
    $stats[$month][$step] = (int) ($stats[$month][$step] ?? 0) + 1;
    update_option('eintrikot_join_stats', array_slice($stats, -24, null, true), false);
}

foreach (['wp_ajax_et_join_count', 'wp_ajax_nopriv_et_join_count'] as $hook) {
    add_action($hook, function () {
        $step = isset($_POST['step']) && is_string($_POST['step']) ? $_POST['step'] : '';
        if (in_array($step, ['open', 'go'], true) && join_url() !== '') {
            join_count($step);
        }
        wp_send_json_success();
    });
}

function join_dialog_markup($url) {
    $mail = 'info@eintrikot.de';
    $points = [
        [
            'Neues Fenster',
            'Der Antrag öffnet sich bei WISO MeinVerein, unserer Mitgliederverwaltung. Das dauert nur wenige Minuten.'
        ],
        [
            'Das brauchst du',
            'Nur deinen Namen und deine E-Mail-Adresse – für Lastschrift oder Spende zusätzlich deine IBAN. Alles Weitere ergänzt du später in der EINTRIKOT-App.'
        ],
        [
            'Zur IBAN',
            'Das Formular fragt immer nach einer IBAN, auch wenn du „Rechnung“ wählst. Zahlst du per Rechnung oder bist du unter 32 und beitragsfrei, wähle „Überspringen“.'
        ],
        [
            'Knopf nicht zu sehen?',
            'Auf manchen Handys steht „Überspringen“ ganz unten: bis zum Ende scrollen oder herauszoomen. Auf dem iPhone klappt es mit Safari besser als mit Chrome. Am Computer geht es immer.'
        ],
        [
            'Danach',
            'Der Vorstand prüft deinen Antrag. Anschließend bekommst du per E-Mail deine Zugangsdaten zur EINTRIKOT-App. Alles Weitere läuft dort.'
        ]
    ];
    $list = '';
    foreach ($points as [$title, $text]) {
        $end = str_ends_with($title, '?') ? '' : '.';
        $list .= '<li><strong>' . esc_html($title . $end) . '</strong> ' . esc_html($text) . '</li>';
    }
    return '<dialog class="et-join-dialog" data-join-dialog aria-labelledby="et-join-title"><button type="button" class="close" data-join-close aria-label="Schließen">×</button>' .
        '<div data-join-step="info"><p class="micro">Mitglied werden</p><h2 id="et-join-title" tabindex="-1">Bevor es losgeht.</h2><p>So läuft dein Antrag – kurz erklärt.</p><ol class="et-join-points">' .
        $list .
        '</ol><div class="et-join-dialog-actions"><a class="button solid" href="' .
        esc_url($url) .
        '" target="_blank" rel="noopener" data-join-go>Verstanden – weiter zum Antrag →</a><button type="button" class="button" data-join-close>Zurück</button></div></div>' .
        '<div data-join-step="done" hidden><p class="micro">Mitglied werden</p><h2 tabindex="-1">Dein Antrag ist geöffnet.</h2><p>Er läuft in einem neuen Fenster. Hängst du fest? Schreib uns an <a class="text-link" href="mailto:' .
        esc_attr($mail) .
        '">' .
        esc_html($mail) .
        '</a>.</p><p>Nach der Prüfung durch den Vorstand bekommst du deine Zugangsdaten zur EINTRIKOT-App per E-Mail.</p><div class="et-join-dialog-actions"><button type="button" class="button solid" data-join-close>Schließen</button><a class="text-link" href="' .
        esc_url($url) .
        '" target="_blank" rel="noopener">Antrag erneut öffnen</a></div></div></dialog>';
}

add_shortcode('eintrikot_join_button', function ($atts) {
    $url = join_url();
    if ($url === '') {
        return '';
    }
    $atts = shortcode_atts(['label' => 'Jetzt Mitglied werden'], $atts, 'eintrikot_join_button');
    wp_enqueue_style('eintrikot-join', plugins_url('join.css', __FILE__), [], asset_version('join.css'));
    wp_enqueue_script('eintrikot-join', plugins_url('join.js', __FILE__), [], asset_version('join.js'), true);
    wp_localize_script('eintrikot-join', 'eintrikotJoin', ['ajax' => admin_url('admin-ajax.php')]);
    // Without JavaScript the button simply opens the application.
    return '<div class="wp-block-buttons et-join-actions"><div class="wp-block-button button solid"><a class="wp-block-button__link wp-element-button" href="' .
        esc_url($url) .
        '" target="_blank" rel="noopener" data-join-open>' .
        esc_html($atts['label']) .
        ' →</a></div></div>' .
        join_dialog_markup($url);
});
