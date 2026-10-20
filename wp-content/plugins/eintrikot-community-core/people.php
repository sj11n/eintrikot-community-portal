<?php
/**
 * Seite „Menschen“: Fotos und Initialen von Gründungsmitgliedern, Vorstand und Beirat.
 *
 * Jede Person auf der Seite ist ein Block mit Namen (h3). Gibt es ein Mitgliedskonto mit genau
 * diesem Namen und hat die Person im Profil zugestimmt („Profilbild auf der Website“), erscheint
 * ihr Profilbild. Sonst stehen die Initialen. Ein im Backend selbst gesetztes Bild hat Vorrang.
 */
namespace Eintrikot\Community;
if (!defined('ABSPATH')) {
    exit();
}

const PEOPLE_PAGE = 'community-menschen';
const PEOPLE_BLOCKS = ['founder', 'board-person', 'advisory-person', 'board-card', 'advisory-card'];

/** Names listed on the people page (cached until the page changes). */
function people_names() {
    $cached = get_transient('eintrikot_people_names');
    if (is_array($cached)) {
        return $cached;
    }
    $page = get_page_by_path(PEOPLE_PAGE);
    $names = [];
    if ($page && preg_match_all('#<h3[^>]*>(.*?)</h3>#s', $page->post_content, $m)) {
        foreach ($m[1] as $name) {
            $name = trim(wp_strip_all_tags($name));
            if ($name !== '' && !str_contains($name, 'folgt')) {
                $names[] = $name;
            }
        }
    }
    set_transient('eintrikot_people_names', $names, DAY_IN_SECONDS);
    return $names;
}
add_action('save_post_page', function () {
    delete_transient('eintrikot_people_names');
});

/** Whether this member is named on the public people page. */
function named_on_website($user_id) {
    $user = get_user_by('id', $user_id);
    return $user && in_array($user->display_name, people_names(), true);
}

/** Known people of the page, used until they have an account with a team. */
const PEOPLE_TONES = [
    'Andreas Arntzen' => 'blue',
    'Björn Emmerling' => 'blue',
    'Markus Weise' => 'blue',
    'Natascha Keller' => 'rose',
    'Fanny Rinne' => 'rose',
    'Uschi Schmitz' => 'rose',
    'Wibke Weisel' => 'rose'
];
/** 'rose' (Damen), 'blue' (Herren) or '' – from the account's team, else from the list above. */
function person_tone($name, $user) {
    $team = $user ? profile_data($user->ID)['team'] ?? '' : '';
    if ($team === 'Damen' || $team === 'Herren') {
        return $team === 'Damen' ? 'rose' : 'blue';
    }
    return PEOPLE_TONES[$name] ?? '';
}

/** The portal account with exactly this display name, or null (none or ambiguous). */
function person_account($name) {
    $users = get_users([
        'search' => $name,
        'search_columns' => ['display_name'],
        'capability' => 'eintrikot_portal',
        'number' => 2
    ]);
    $users = array_values(array_filter($users, fn($u) => $u->display_name === $name));
    return count($users) === 1 ? $users[0] : null;
}

add_filter(
    'render_block',
    function ($html, $block) {
        if (($block['blockName'] ?? '') !== 'core/group' || is_admin()) {
            return $html;
        }
        $classes = preg_split('/\s+/', (string) ($block['attrs']['className'] ?? ''));
        if (!array_intersect($classes, PEOPLE_BLOCKS)) {
            return $html;
        }
        if (!preg_match('#<h3[^>]*>(.*?)</h3>#s', $html, $m)) {
            return $html;
        }
        $name = trim(wp_strip_all_tags($m[1]));
        // An image chosen in the backend stays.
        if (
            $name === '' ||
            str_contains($name, 'folgt') ||
            str_contains($html, 'wp-block-cover__image-background')
        ) {
            return $html;
        }
        $user = person_account($name);
        $photo = $user ? get_user_meta($user->ID, 'eintrikot_avatar', true) : '';
        $consent = $user && !empty(profile_data($user->ID)['public_photo']);
        $inner =
            $consent && is_string($photo) && str_starts_with($photo, 'data:image/jpeg;base64,')
                ? '<img class="et-person-photo" src="' . esc_attr($photo) . '" alt="' . esc_attr($name) . '">'
                : '<p><span>' . esc_html(member_initials($name)) . '</span></p>';
        // Replace whatever stands in the photo slot ("Porträt folgt", old initials) with photo or initials.
        $html = preg_replace(
            '#(<div class="wp-block-cover__inner-container[^"]*">).*?(</div>)#s',
            '$1' . str_replace(['\\', '$'], ['\\\\', '\\$'], $inner) . '$2',
            $html,
            1
        );
        // Background as in the portal: rosé for Damen, light blue for Herren.
        $tone = person_tone($name, $user);
        return $tone === ''
            ? $html
            : preg_replace('#class="wp-block-cover #', 'class="wp-block-cover tone-' . $tone . ' ', $html, 1);
    },
    10,
    2
);

/**
 * Why a person on the people page shows a photo or only initials. One row per name of the page:
 * accounts with exactly that display name, which one the page would use, and what is missing.
 */
function people_diagnosis() {
    $rows = [];
    foreach (people_names() as $name) {
        $found = array_values(
            array_filter(
                get_users(['search' => $name, 'search_columns' => ['display_name'], 'number' => 10]),
                fn($u) => $u->display_name === $name
            )
        );
        $used = person_account($name);
        $accounts = [];
        foreach ($found as $u) {
            $avatar = get_user_meta($u->ID, 'eintrikot_avatar', true);
            $accounts[] = [
                'id' => $u->ID,
                'roles' => implode(', ', $u->roles),
                'portal' => user_can($u, 'eintrikot_portal'),
                'photo' => is_string($avatar) && str_starts_with($avatar, 'data:image/jpeg;base64,'),
                'consent' => !empty(profile_data($u->ID)['public_photo'])
            ];
        }
        if (!$found) {
            $reason = 'Kein Konto mit genau diesem Anzeigenamen.';
        } elseif (count($found) > 1) {
            $reason =
                'Mehrere Konten mit diesem Namen, deshalb zeigt die Seite keines. Eines umbenennen oder löschen.';
        } elseif (!$used) {
            $reason = 'Das Konto hat kein Portalrecht (Rolle ohne „eintrikot_portal“).';
        } elseif (!$accounts[0]['photo']) {
            $reason = 'Im Profil ist kein Profilbild gespeichert.';
        } elseif (!$accounts[0]['consent']) {
            $reason = 'Der Haken „Profilbild auf der Website“ ist nicht gespeichert.';
        } else {
            $reason = 'Alles in Ordnung. Das Foto erscheint (Seite neu laden, Zwischenspeicher leeren).';
        }
        $rows[] = ['name' => $name, 'accounts' => $accounts, 'reason' => $reason];
    }
    return $rows;
}

add_action('admin_menu', function () {
    add_submenu_page(
        'eintrikot-community-setup',
        'Seite „Menschen“ prüfen',
        'Menschen prüfen',
        'manage_options',
        'eintrikot-people-check',
        function () {
            if (!current_user_can('manage_options')) {
                return;
            }
            delete_transient('eintrikot_people_names');
            echo '<div class="wrap"><h1>Seite „Menschen“ prüfen</h1><p>Pro Person der Seite: welche Konten den Namen tragen und was für das Foto noch fehlt.</p><table class="widefat striped"><thead><tr><th>Name auf der Seite</th><th>Konten mit diesem Namen</th><th>Ergebnis</th></tr></thead><tbody>';
            foreach (people_diagnosis() as $row) {
                $list = '';
                foreach ($row['accounts'] as $a) {
                    $list .=
                        '#' .
                        (int) $a['id'] .
                        ' (' .
                        esc_html($a['roles']) .
                        '): Portalrecht ' .
                        ($a['portal'] ? 'ja' : 'nein') .
                        ', Bild ' .
                        ($a['photo'] ? 'ja' : 'nein') .
                        ', Haken ' .
                        ($a['consent'] ? 'ja' : 'nein') .
                        '<br>';
                }
                echo '<tr><td>' .
                    esc_html($row['name']) .
                    '</td><td>' .
                    ($list !== '' ? wp_kses($list, ['br' => []]) : '–') .
                    '</td><td>' .
                    esc_html($row['reason']) .
                    '</td></tr>';
            }
            echo '</tbody></table></div>';
        }
    );
});
