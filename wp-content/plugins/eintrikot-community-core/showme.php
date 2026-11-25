<?php
namespace Eintrikot\Community;
if (!defined('ABSPATH')) {
    exit();
}
/**
 * "Zeig dich": the first card on "Start" while the member has not shared "Über dich" (place of residence, region,
 * club). Sharing stays the member's own choice: the card shows what others would see and shares only on a click.
 * "Später" asks again after SHOWME_LATER_DAYS. Design approved by B on 25.11.2026 (docs/entwuerfe/zeig-dich).
 */

const SHOWME_LATER_DAYS = 14;
const SHOWME_GROUP = 'Über dich';

/** Whether the card is due for this account: not shared yet, not postponed, adult. */
function showme_due($user_id) {
    $user_id = (int) $user_id;
    if (!member_access() || $user_id !== get_current_user_id() || !is_portal_user($user_id)) {
        return false;
    }
    $data = profile_data($user_id);
    if (is_minor_data($data)) {
        return false; // Youth rules decide what minors show (consent.php).
    }
    if (section_visibility_state($data, SHOWME_GROUP) === 'all') {
        return false;
    }
    return (int) get_user_meta($user_id, 'eintrikot_showme_later', true) <= time();
}

/** Values of "Über dich" in profile order, without empty ones. */
function showme_values($data) {
    $values = [];
    foreach (array_keys(profile_groups()[SHOWME_GROUP]) as $key) {
        $value = trim((string) profile_value_text($key, $data[$key] ?? ''));
        if ($value !== '') {
            $values[] = $value;
        }
    }
    return $values;
}

/** The member row as the directory shows it, with an optional line instead of the shared one. */
function showme_member_row($user, $line) {
    return '<span class="et-member" aria-hidden="true">' .
        member_avatar($user->ID, $user->display_name) .
        '<span><strong><span class="member-name">' .
        esc_html($user->display_name) .
        '</span></strong><small>' .
        ($line !== '' ? $line : 'Noch keine Angaben geteilt') .
        '</small></span><span class="member-arrow">→</span></span>';
}

/** Link into the profile, scrolled to a section and marked there. */
function profile_focus_url($slug) {
    return portal_url('profile', ['fokus' => $slug]) . '#abschnitt-' . $slug;
}

/** The card (or the confirmation right after sharing). Empty when nothing is due. */
function showme_card() {
    $user = wp_get_current_user();
    if (!$user->ID || !member_access()) {
        return '';
    }
    if (get_user_meta($user->ID, 'eintrikot_showme_done', true)) {
        delete_user_meta($user->ID, 'eintrikot_showme_done');
        $values = showme_values(profile_data($user->ID));
        return '<section class="showme showme-done" role="status" aria-labelledby="showme-title"><div class="showme-text"><h2 id="showme-title">Freigegeben.</h2><p>' .
            ($values
                ? 'Andere Mitglieder sehen jetzt: <strong>' . esc_html(implode(' · ', $values)) . '</strong>. '
                : '') .
            'Du kannst das jederzeit im Profil ändern.</p><div class="showme-actions"><a class="button" href="' .
            esc_url(profile_focus_url('hockey')) .
            '">Auch Hockey-Lebenslauf zeigen →</a><a class="text-link" href="' .
            esc_url(portal_url('member', ['member' => $user->ID])) .
            '">Profil ansehen</a></div></div></section>';
    }
    if (!showme_due($user->ID)) {
        return '';
    }
    $data = profile_data($user->ID);
    $values = showme_values($data);
    $button = static function ($choice, $label, $class) {
        return '<button class="' .
            esc_attr($class) .
            '" type="submit" name="choice" value="' .
            esc_attr($choice) .
            '">' .
            esc_html($label) .
            '</button>';
    };
    $form =
        '<form class="showme-actions" method="post" action="' .
        esc_url(admin_url('admin-post.php')) .
        '"><input type="hidden" name="action" value="et_showme">' .
        wp_nonce_field('et_showme', '_wpnonce', true, false) . // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress core builds the hidden nonce field
        ($values
            ? $button('share', 'Freigeben', 'button solid') .
                '<a class="button" href="' .
                esc_url(profile_focus_url('about')) .
                '">Im Profil ansehen</a>'
            : '<a class="button solid" href="' . esc_url(profile_focus_url('about')) . '">Jetzt ergänzen</a>') .
        $button('later', 'Später', 'text-link') .
        '</form>';
    $text =
        '<div class="showme-text"><p class="showme-label">Dein erster Schritt</p><h2 id="showme-title">Zeig dich den anderen.</h2><p>' .
        ($values
            ? 'Bisher sehen andere Mitglieder von dir nur Namen und Foto. Gib Wohnort, Region und Verein frei – dann finden dich alte Teamkameradinnen und Teamkameraden wieder.'
            : 'Bisher sehen andere Mitglieder von dir nur Namen und Foto. Ergänze Wohnort, Region und Verein und gib sie frei – dann finden dich alte Teamkameradinnen und Teamkameraden wieder.') .
        '</p>' .
        $form .
        '<small class="showme-note">Nur für angemeldete Mitglieder sichtbar, nie öffentlich. Jederzeit im Profil änderbar.</small></div>';
    if (!$values) {
        return '<section class="showme showme-single" aria-labelledby="showme-title">' . $text . '</section>';
    }
    // What the directory shows today, and the same line with "Über dich" added.
    $now = member_line_html(directory_projection($user)['shared']);
    $after = trim(
        implode(' · ', array_filter([wp_strip_all_tags($now), implode(' · ', $values)])),
        ' ·'
    );
    return '<section class="showme" aria-labelledby="showme-title">' .
        $text .
        '<div class="showme-preview"><figure class="showme-before"><figcaption>So sehen dich andere jetzt</figcaption>' .
        showme_member_row($user, $now) .
        '</figure><figure class="showme-after"><figcaption>Nach der Freigabe</figcaption>' .
        showme_member_row($user, esc_html($after)) .
        '</figure></div></section>';
}

/** Shares "Über dich" for the own account, the same way the section switch in the profile does. */
function showme_share($user_id) {
    $user = get_user_by('id', (int) $user_id);
    $data = profile_data($user->ID);
    if (is_minor_data($data)) {
        return new \WP_Error('minor', 'Für Mitglieder unter 18 gelten eigene Regeln.');
    }
    $data['display_name'] = $data['display_name'] ?? $user->display_name;
    foreach (section_visibility_keys(SHOWME_GROUP) as $key) {
        $data['visibility'][$key] = 'members';
    }
    return persist_profile(
        $user->ID,
        $data,
        null,
        'Freigabe über „Zeig dich“ auf der Startseite',
        profile_revision($user->ID)
    );
}

add_action('admin_post_et_showme', function () {
    if (!member_access()) {
        wp_die('Keine Berechtigung.', '', ['response' => 403]);
    }
    check_admin_referer('et_showme');
    $id = get_current_user_id();
    $choice = post_choice('choice', ['share', 'later']);
    if ($choice === 'later') {
        update_user_meta($id, 'eintrikot_showme_later', time() + SHOWME_LATER_DAYS * DAY_IN_SECONDS);
    } elseif ($choice === 'share') {
        $result = showme_share($id);
        if (is_wp_error($result)) {
            // Saving failed (for example a parallel change): the profile shows the section to do it by hand.
            wp_safe_redirect(profile_focus_url('about'));
            exit();
        }
        delete_user_meta($id, 'eintrikot_showme_later');
        update_user_meta($id, 'eintrikot_showme_done', 1);
    }
    wp_safe_redirect(portal_url());
    exit();
});
