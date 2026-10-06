<?php
namespace Eintrikot\Community;
if (!defined('ABSPATH')) {
    exit();
}

/** Share of portal profiles that must share their caps before the total is shown publicly. */
const CAPS_MIN_SHARE = 0.6;

/**
 * Stored key figures. 'members' and 'first_year' empty = calculated from the portal;
 * a value entered in the backend overrides the calculation.
 */
function metric_values() {
    $raw = get_option('eintrikot_metrics', []);
    $raw = is_array($raw) ? $raw : [];
    // Before 0.17 the member count was always typed in: from now on it is calculated unless set again.
    if (!array_key_exists('first_year', $raw)) {
        unset($raw['members']);
        $raw['first_year'] = '1968';
    }
    unset($raw['generations']);
    return wp_parse_args($raw, ['members' => '', 'donations' => 12750, 'first_year' => '', 'as_of' => '']);
}
/** Members shown on the website: the typed value, else the EINTRIKOT accounts listed in the directory. */
function metric_members() {
    $v = metric_values()['members'];
    return $v !== '' ? (int) $v : caps_stats()['total'];
}
/** Year of the oldest international match: the typed value, else the earliest shared DHB station. */
function metric_first_year() {
    $v = metric_values()['first_year'];
    if ($v !== '') {
        return (int) $v;
    }
    return caps_stats()['first_year'] ?? null;
}

/** "2026-09" -> "September 2026" in the site language; '' when unset or invalid. */
function metrics_as_of_label($as_of) {
    if (!is_string($as_of) || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $as_of)) {
        return '';
    }
    // Mid-month noon, so no timezone offset can shift the month.
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', $as_of . '-15 12:00', wp_timezone());
    return $date ? wp_date('F Y', $date->getTimestamp()) : '';
}

add_action('admin_menu', function () {
    add_submenu_page(
        'eintrikot-community-setup',
        'Kennzahlen',
        'Kennzahlen',
        'manage_options',
        'eintrikot-metrics',
        __NAMESPACE__ . '\metrics_page'
    );
});
function metrics_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    $values = metric_values();
    $caps = caps_stats();
    echo '<div class="wrap"><h1>Kennzahlen</h1>';
    if (isset($_GET['saved'])) {
        echo '<div class="notice notice-success"><p>Kennzahlen gespeichert.</p></div>';
    }
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('et_metrics');
    echo '<input type="hidden" name="action" value="et_metrics">';
    foreach (
        [
            'members' => [
                'Mitglieder',
                'Leer lassen = automatisch: Konten mit EINTRIKOT-Rolle im Mitgliederverzeichnis (derzeit ' .
                number_format_i18n($caps['total']) .
                '). Eine eingetragene Zahl ersetzt die Berechnung.',
                false
            ],
            'donations' => ['Spendenaufkommen in Euro', '', true],
            'first_year' => [
                'Ältestes Länderspiel (Jahr)',
                'Leer lassen = automatisch: das früheste „Von“-Jahr der DHB-Stationen, die Mitglieder freigegeben haben (derzeit ' .
                ($caps['first_year'] ?? '–') .
                '). Ein eingetragenes Jahr ersetzt die Berechnung.',
                false
            ]
        ]
        as $key => [$label, $hint, $required]
    ) {
        echo '<p><label>' .
            esc_html($label) .
            '<br><input type="number" name="' .
            esc_attr($key) .
            '" min="' .
            ($key === 'first_year' ? '1900' : '0') .
            '" max="' .
            ($key === 'first_year' ? '2100' : '999999999') .
            '" step="1"' .
            ($required ? ' required' : '') .
            ' value="' .
            esc_attr($values[$key]) .
            '"></label>' .
            ($hint !== '' ? '<br><span class="description">' . esc_html($hint) . '</span>' : '') .
            '</p>';
    }
    echo '<p><label>Stand der Kennzahlen<br><input type="month" name="as_of" value="' .
        esc_attr($values['as_of']) .
        '"></label><br><span class="description">Erscheint unter den Zahlen als „Stand: Monat Jahr". Leer lassen, um keinen Stand anzuzeigen.</span></p>';
    $share = $caps['total'] ? round(($caps['shared'] / $caps['total']) * 100) : 0;
    echo '<h2>Länderspiele</h2><p>Summe aus den Profilen, die ihre Länderspiele für Mitglieder freigegeben haben. Die Zahl erscheint öffentlich erst, wenn mindestens ' .
        esc_html((string) round(CAPS_MIN_SHARE * 100)) .
        ' % der Portalprofile eine Angabe teilen.</p><p><strong>Aktuell:</strong> ' .
        esc_html(
            $caps['shared'] .
                ' von ' .
                $caps['total'] .
                ' Profilen (' .
                $share .
                ' %), Summe ' .
                number_format_i18n($caps['sum']) .
                '.'
        ) .
        ' ' .
        (caps_public_total() === null ? 'Wird derzeit nicht angezeigt.' : 'Wird angezeigt.') .
        '</p>';
    submit_button('Kennzahlen speichern');
    echo '</form></div>';
}
add_action('admin_post_et_metrics', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Keine Berechtigung.', '', ['response' => 403]);
    }
    check_admin_referer('et_metrics');
    $values = [];
    foreach (['members', 'donations', 'first_year'] as $key) {
        $v = post_text($key, '', 9);
        if ($v === '' && $key !== 'donations') {
            $values[$key] = ''; // calculated
            continue;
        }
        if (!ctype_digit($v) || ($key === 'first_year' && ((int) $v < 1900 || (int) $v > 2100))) {
            wp_die('Bitte ganze nichtnegative Zahlen eingeben (Jahr zwischen 1900 und 2100).');
        }
        $values[$key] = (string) (int) $v;
    }
    $as_of = post_text('as_of', '', 7);
    if ($as_of !== '' && metrics_as_of_label($as_of) === '') {
        wp_die('Bitte den Stand als Monat angeben.');
    }
    $values['as_of'] = $as_of;
    update_option('eintrikot_metrics', $values, false);
    wp_safe_redirect(admin_url('admin.php?page=eintrikot-metrics&saved=1'));
    exit();
});

/**
 * Caps shared with members, across all portal profiles. Cached; see invalidation below.
 *
 * @return array{sum:int,shared:int,total:int,first_year:int|null}
 */
function caps_stats() {
    $cached = get_transient('eintrikot_caps_stats');
    if (
        is_array($cached) &&
        isset($cached['sum'], $cached['shared'], $cached['total']) &&
        array_key_exists('first_year', $cached)
    ) {
        return $cached;
    }
    $stats = ['sum' => 0, 'shared' => 0, 'total' => 0, 'first_year' => null];
    // Technical accounts and hidden profiles do not count, just as in the directory.
    foreach (get_users(['capability' => 'eintrikot_portal']) as $user) {
        if (!directory_listed($user)) {
            continue;
        }
        $stats['total']++;
        $v = visible_value(profile_data($user->ID), 'caps');
        foreach (shared_stations(profile_data($user->ID)) as $row) {
            $from = is_array($row) ? (int) ($row['from'] ?? 0) : 0;
            if ($from >= 1900 && ($stats['first_year'] === null || $from < $stats['first_year'])) {
                $stats['first_year'] = $from;
            }
        }
        $n = is_scalar($v) ? caps_number((string) $v) : null;
        if ($n !== null) {
            $stats['sum'] += $n;
            $stats['shared']++;
        }
    }
    set_transient('eintrikot_caps_stats', $stats, 12 * HOUR_IN_SECONDS);
    return $stats;
}

/** The number a caps entry starts with ("185 (A-Kader)" → 185, "über 25" → 25), or null. */
function caps_number($text) {
    return preg_match('/(\d{1,6})/', $text, $m) ? (int) $m[1] : null;
}
/** Public caps total, or null while too few profiles share the value. */
function caps_public_total() {
    $stats = caps_stats();
    if (!$stats['total'] || $stats['shared'] / $stats['total'] < CAPS_MIN_SHARE) {
        return null;
    }
    return $stats['sum'];
}

function flush_caps_stats() {
    delete_transient('eintrikot_caps_stats');
}
foreach (['added_user_meta', 'updated_user_meta', 'deleted_user_meta'] as $hook) {
    add_action(
        $hook,
        function ($meta_id, $user_id, $key) {
            if ($key === 'eintrikot_profile') {
                flush_caps_stats();
            }
        },
        10,
        3
    );
}
add_action('set_user_role', __NAMESPACE__ . '\flush_caps_stats');
add_action('deleted_user', __NAMESPACE__ . '\flush_caps_stats');
add_action('user_register', __NAMESPACE__ . '\flush_caps_stats');

add_shortcode('eintrikot_metrics', function () {
    $v = metric_values();
    $items = [
        'members' => ['Mitglieder', metric_members(), ''],
        'donations' => ['Spendenaufkommen', (int) $v['donations'], ' €'],
        'caps' => ['Länderspiele', caps_public_total(), ''],
        'first_year' => ['im Nationaltrikot', metric_first_year(), '']
    ];
    $items = array_filter($items, fn($item) => $item[1] !== null);
    $html = '<div class="et-metrics' . (count($items) === 3 ? ' is-three' : '') . '">';
    foreach ($items as $item) {
        [$label, $value, $suffix] = $item;
        // A year is shown as is: no thousands separator, no count-up.
        $year = $label === 'im Nationaltrikot';
        $html .=
            '<div class="et-metric"><strong' .
            ($year
                ? ''
                : ' data-count="' . esc_attr((string) $value) . '" data-suffix="' . esc_attr($suffix) . '"') .
            '>' .
            esc_html(($year ? 'seit ' . $value : number_format_i18n($value)) . $suffix) .
            '</strong><span>' .
            esc_html($label) .
            '</span></div>';
    }
    $as_of = metrics_as_of_label($v['as_of']);
    if ($as_of !== '') {
        $html .= '<p class="et-metrics-asof">Stand: ' . esc_html($as_of) . '</p>';
    }
    $html .= '</div>';
    return $html;
});

add_action('wp_enqueue_scripts', function () {
    $post = get_post();
    if (!is_singular() || !$post || !has_shortcode($post->post_content, 'eintrikot_metrics')) {
        return;
    }
    wp_enqueue_style(
        'eintrikot-metrics',
        plugins_url('metrics.css', __FILE__),
        [],
        asset_version('metrics.css')
    );
    wp_enqueue_script(
        'eintrikot-metrics',
        plugins_url('metrics.js', __FILE__),
        [],
        asset_version('metrics.js'),
        true
    );
});
