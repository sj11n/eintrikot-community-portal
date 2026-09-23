<?php
/**
 * Portal start page: search first, then what is new for the member.
 */
namespace Eintrikot\Community;
if (!defined('ABSPATH')) {
    exit();
}

function first_name($name) {
    $words = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);
    return $words ? $words[0] : '';
}

/** Search form used on the start page and in the directory. */
function member_search_form($q = '', $hidden = []) {
    $html =
        '<form class="page-search" role="search" action="' .
        esc_url(get_permalink((int) get_option('eintrikot_portal_page'))) .
        '" method="get"><input type="hidden" name="view" value="members">';
    foreach ($hidden as $key => $value) {
        if ($value !== '') {
            $html .= '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
        }
    }
    return $html .
        '<label class="search-field"><span class="screen-reader-text">Mitglieder suchen</span><input type="search" name="q" value="' .
        esc_attr($q) .
        '" placeholder="Name, Team oder Ort suchen" autocomplete="off"></label><button class="button solid" type="submit">Suchen</button></form>';
}

/** Open requests of the current member (not yet closed). */
function open_member_requests($limit = 3) {
    $rows = get_posts([
        'post_type' => 'et_request',
        'post_status' => 'private',
        'author' => get_current_user_id(),
        'posts_per_page' => 20,
        'orderby' => 'date',
        'order' => 'DESC'
    ]);
    $open = array_filter(
        $rows,
        fn($row) => !in_array(request_state($row->ID)['status'] ?? 'received', ['adopted', 'rejected'], true)
    );
    return array_slice(array_values($open), 0, $limit);
}

/** Hint for the admin page: how many requests are waiting. */
function open_requests_hint() {
    $rows = get_posts([
        'post_type' => 'et_request',
        'post_status' => 'private',
        'posts_per_page' => -1,
        'fields' => 'ids'
    ]);
    $open = count(
        array_filter(
            $rows,
            fn($id) => in_array(request_state($id)['status'] ?? 'received', ['received', 'review'], true)
        )
    );
    return $open ? $open . ' offen' : 'Keine offenen Anfragen';
}

/**
 * What a complete profile consists of. Each step has a label and whether it is done.
 *
 * @return array<string,array{0:string,1:bool}>
 */
function profile_steps($id) {
    $data = profile_data($id);
    $filled = function ($keys) use ($data) {
        foreach ($keys as $key) {
            if (is_scalar($data[$key] ?? null) && trim((string) $data[$key]) !== '') {
                return true;
            }
        }
        return false;
    };
    $avatar = get_user_meta($id, 'eintrikot_avatar', true);
    $stations = array_filter(is_array($data['stations'] ?? null) ? $data['stations'] : [], 'is_array');
    return [
        'photo' => ['Profilbild', is_string($avatar) && $avatar !== ''],
        'about' => ['Wohnort oder Verein', $filled(['city', 'region', 'club'])],
        'hockey' => ['Team und Altersklasse', $filled(['team', 'age_class', 'phase'])],
        'vita' => ['DHB-Vita', (bool) array_filter($stations, fn($row) => array_filter($row))],
        'career' => ['Beruf', $filled(['job', 'employer', 'industry', 'university'])]
    ];
}

function render_home() {
    $user = wp_get_current_user();
    $name = first_name($user->display_name);
    echo page_head(
        $name !== '' ? 'Hallo, ' . $name . '.' : 'Hallo.',
        'Wen möchtest du wiederfinden?',
        member_search_form(),
        'portal'
    );

    // The welcome note appears only on the very first visit.
    if (!get_user_meta($user->ID, 'eintrikot_welcome_seen', true)) {
        update_user_meta($user->ID, 'eintrikot_welcome_seen', time());
        echo '<section class="home-welcome" aria-labelledby="home-welcome-title"><h2 id="home-welcome-title">Willkommen bei EINTRIKOT.</h2><p>Hier findest du andere Mitglieder, Vereinsinfos und Termine. Starte am besten mit deinem Profil – du entscheidest bei jedem Abschnitt, ob andere Mitglieder ihn sehen.</p><a class="button solid" href="' .
            esc_url(portal_url('profile')) .
            '">Profil ergänzen →</a></section>';
    }

    $steps = profile_steps($user->ID);
    $done = count(array_filter($steps, fn($step) => $step[1]));
    if ($done < count($steps)) {
        $missing = array_map(fn($step) => $step[0], array_filter($steps, fn($step) => !$step[1]));
        echo '<a class="home-progress" href="' .
            esc_url(portal_url('profile')) .
            '"><span class="home-progress-text"><strong>Dein Profil: ' .
            esc_html($done . ' von ' . count($steps)) .
            '</strong><small>Es fehlt noch: ' .
            esc_html(implode(', ', $missing)) .
            '</small></span><span class="home-progress-bar" aria-hidden="true"><span style="width:' .
            esc_attr((string) round(($done / count($steps)) * 100)) .
            '%"></span></span><span class="home-progress-cta">Ergänzen →</span></a>';
    }

    echo '<div class="home-grid">';

    // Latest club info.
    $info = get_posts(['post_type' => 'et_info', 'post_status' => 'publish', 'posts_per_page' => 1]);
    echo '<section class="home-card tone-rose"><h2 class="home-card-label">Vereinsinfo</h2>';
    if ($info) {
        $post = $info[0];
        echo '<h3>' .
            esc_html($post->post_title) .
            '</h3><p class="home-card-meta">' .
            esc_html(get_the_date('j. F Y', $post)) .
            '</p><p>' .
            esc_html(wp_trim_words(wp_strip_all_tags($post->post_content), 26)) .
            '</p>';
    } else {
        echo '<p>Noch keine Vereinsinfos. Neue Mitteilungen erscheinen hier.</p>';
    }
    echo '<a class="text-link" href="' . esc_url(portal_url('infos')) . '">Alle Vereinsinfos →</a></section>';

    // Next event.
    $events = array_values(
        array_filter(calendar_items(92), fn($item) => !str_starts_with($item['title'], 'Geburtstag: '))
    );
    echo '<section class="home-card tone-green"><h2 class="home-card-label">Nächster Termin</h2>';
    if ($events) {
        $ts = strtotime($events[0]['date'] . ' 12:00:00');
        echo '<div class="home-date"><time datetime="' .
            esc_attr($events[0]['date']) .
            '"><strong>' .
            esc_html(wp_date('j.', $ts)) .
            '</strong><span>' .
            esc_html(wp_date('F', $ts)) .
            '</span></time><h3>' .
            esc_html($events[0]['title']) .
            '</h3></div>';
        if (count($events) > 1) {
            echo '<p class="home-card-meta">Danach: ' .
                esc_html(
                    $events[1]['title'] .
                        ' am ' .
                        wp_date('j. F', strtotime($events[1]['date'] . ' 12:00:00'))
                ) .
                '</p>';
        }
    } else {
        echo '<p>In den nächsten drei Monaten stehen keine Termine an.</p>';
    }
    echo '<a class="text-link" href="' . esc_url(portal_url('events')) . '">Alle Termine →</a></section>';

    // Open requests, or an invitation to the service area.
    $open = open_member_requests();
    echo '<section class="home-card tone-gold"><h2 class="home-card-label">' .
        ($open ? 'Deine offenen Anfragen' : 'Service') .
        '</h2>';
    if ($open) {
        echo '<ul class="home-requests">';
        foreach ($open as $row) {
            $status = request_state($row->ID)['status'] ?? 'received';
            echo '<li><a href="' .
                esc_url(portal_url('request', ['request' => $row->ID])) .
                '"><span>' .
                esc_html($row->post_title) .
                '</span>' .
                request_status_pill($status, get_post_meta($row->ID, 'et_kind', true)) .
                '</a></li>';
        }
        echo '</ul><a class="text-link" href="' . esc_url(portal_url('service')) . '">Zum Service →</a>';
    } else {
        echo '<p>Eine Idee teilen, dich einbringen, EINTRIKOT zusätzlich fördern oder Daten ändern.</p><a class="text-link" href="' .
            esc_url(portal_url('service')) .
            '">Zum Service →</a>';
    }
    echo '</section></div>';
}
