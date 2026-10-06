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
