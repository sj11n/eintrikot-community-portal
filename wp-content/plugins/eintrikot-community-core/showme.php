<?php
namespace Eintrikot\Community;
if (!defined('ABSPATH')) {
    exit();
}
/**
 * "Deine ersten Schritte" on "Start": the first card while the member has not shared "Über dich" (place of
 * residence, region, club) and/or not answered the newsletter question. Two separate choices with their own
 * buttons, nothing preselected; sharing never implies the newsletter or the other way round.
 * "Später erinnern" asks again after SHOWME_LATER_DAYS. Designs approved by B on 25.11.2026
 * (docs/entwuerfe/zeig-dich, images 1 to 6). Under 18 only the region can be shared (youth rules) and there
 * is no newsletter.
 */

const SHOWME_LATER_DAYS = 14;
const SHOWME_GROUP = 'Über dich';

/** Whether the sharing step is due for this account: not shared yet and not postponed. */
function showme_due($user_id) {
    $user_id = (int) $user_id;
    if (!member_access() || $user_id !== get_current_user_id() || !is_portal_user($user_id)) {
        return false;
    }
    if (section_visibility_state(profile_data($user_id), SHOWME_GROUP) === 'all') {
        return false;
    }
    return (int) get_user_meta($user_id, 'eintrikot_showme_later', true) <= time();
}

/** Values of "Über dich" that other members would see once shared (under 18 only the region). */
function showme_values($data) {
    $values = [];
    foreach (array_keys(profile_groups()[SHOWME_GROUP]) as $key) {
        if (minor_hides($data, $key)) {
            continue;
        }
        $value = trim((string) profile_value_text($key, $data[$key] ?? ''));
        if ($value !== '') {
            $values[] = $value;
        }
    }
    return $values;
}

/** The own directory card exactly as other members see it, before or after sharing "Über dich". */
function showme_preview_card($user, $data, $shared) {
    if ($shared) {
        foreach (section_visibility_keys(SHOWME_GROUP) as $key) {
            $data['visibility'][$key] = 'members';
        }
    }
    $line = [];
    foreach (['team', 'age_class', 'phase', 'city'] as $key) {
        $line[$key] = profile_value_text($key, visible_value($data, $key));
    }
    $team = visible_value($data, 'team');
    return member_card_html(
        $user,
        member_line_html($line),
        portal_url('member', ['member' => $user->ID]),
        str_starts_with((string) visible_value($data, 'linkedin'), 'https://'),
        $team === 'Damen' ? 'rose' : ($team === 'Herren' ? 'blue' : 'neutral')
    );
}

/** Link into the profile, scrolled to a section and marked there. */
function profile_focus_url($slug) {
    return portal_url('profile', ['fokus' => $slug]) . '#abschnitt-' . $slug;
}

function showme_button($choice, $label, $class) {
    return '<button class="' .
        esc_attr($class) .
        '" type="submit" name="choice" value="' .
        esc_attr($choice) .
        '">' .
        esc_html($label) .
        '</button>';
}

/** Opening tag of a form that posts to admin-post.php with its own nonce. */
function showme_form($action, $class) {
    return '<form class="' .
        esc_attr($class) .
        '" method="post" action="' .
        esc_url(admin_url('admin-post.php')) .
        '"><input type="hidden" name="action" value="' .
        esc_attr($action) .
        '">' .
        wp_nonce_field($action, '_wpnonce', true, false); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress core builds the hidden nonce field
}

/** Step 1: share "Über dich" (or fill it in first when it is empty). */
function showme_share_step($data, $numbered) {
    $minor = is_minor_data($data);
    $values = showme_values($data);
    $what = $minor ? 'deine Region' : 'Wohnort, Region und Verein';
    return '<div class="showme-step">' .
        ($numbered ? '<h3><span class="step-no">1</span>Zeig dich den anderen.</h3>' : '') .
        '<p>Bisher sehen andere Mitglieder von dir nur Namen und Foto. ' .
        ($values ? 'Gib ' . $what . ' frei' : 'Ergänze ' . $what . ' und gib sie frei') .
        ' – dann finden dich alte Teamkameradinnen und Teamkameraden wieder.</p>' .
        showme_form('et_showme', 'showme-actions') .
        ($values
            ? showme_button('share', 'Freigeben', 'button solid') .
                '<a class="button" href="' .
                esc_url(profile_focus_url('about')) .
                '">Im Profil ansehen</a>'
            : '<a class="button solid" href="' . esc_url(profile_focus_url('about')) . '">Jetzt ergänzen</a>') .
        ($numbered ? '' : showme_button('later', 'Später', 'text-link')) .
        '</form></div>';
}

/** Step 1 right after sharing, inside the card while the newsletter question is still open. */
function showme_shared_step($data) {
    $values = showme_values($data);
    return '<div class="showme-step showme-step-done"><h3><span class="step-no">✓</span>Freigegeben.</h3><p>' .
        ($values ? 'Andere Mitglieder sehen jetzt: <strong>' . esc_html(implode(' · ', $values)) . '</strong>. ' : '') .
        '<a class="text-link" href="' .
        esc_url(profile_focus_url('hockey')) .
        '">Auch Hockey-Lebenslauf zeigen →</a></p></div>';
}

/** Step 2: the newsletter question with the same wording and handler as the newsletter bar. */
function showme_newsletter_step() {
    return '<div class="showme-step"><h3><span class="step-no">2</span>Newsletter per E-Mail?</h3><p>' .
        esc_html(newsletter_wording()) .
        '</p>' .
        showme_form('et_newsletter', 'showme-actions') .
        showme_button('yes', 'Ja, gern', 'button solid') .
        showme_button('no', 'Nein, danke', 'button') .
        '</form></div>';
}

/** The card, or '' when nothing is due (then "Start" shows the newsletter bar if that is still open). */
function showme_card() {
    $user = wp_get_current_user();
    if (!$user->ID || !member_access()) {
        return '';
    }
    $data = profile_data($user->ID);
    $just_shared = (bool) get_user_meta($user->ID, 'eintrikot_showme_done', true);
    delete_user_meta($user->ID, 'eintrikot_showme_done');
    $share = !$just_shared && showme_due($user->ID);
    $newsletter = newsletter_prompt_due($user->ID);
    $later =
        showme_form('et_showme', 'showme-later') .
        showme_button('later', 'Später erinnern', 'text-link') .
        '<small class="showme-note">Freigaben nur für angemeldete Mitglieder, nie öffentlich. Beides jederzeit im Profil änderbar.</small></form>';
    // The preview only when sharing changes the directory card (it shows the place of residence, not
    // region or club; under 18 it never changes).
    $before = showme_preview_card($user, $data, false);
    $after = showme_preview_card($user, $data, true);
    $preview =
        $before === $after
            ? ''
            : '<div class="showme-preview"><figure class="showme-before"><figcaption>So sehen dich andere jetzt</figcaption>' .
                $before .
                '</figure><figure class="showme-after"><figcaption>Nach der Freigabe</figcaption>' .
                $after .
                '</figure></div>';
    $label = '<p class="showme-label">Deine ersten Schritte</p>';

    if ($share && $newsletter) {
        return '<section class="showme' .
            ($preview !== '' ? '' : ' showme-single') .
            '" aria-labelledby="showme-title"><div class="showme-text">' .
            $label .
            '<h2 id="showme-title">Zwei kurze Fragen zum Start.</h2>' .
            showme_share_step($data, true) .
            showme_newsletter_step() .
            $later .
            '</div>' .
            $preview .
            '</section>';
    }
    if ($share) {
        return '<section class="showme' .
            ($preview !== '' ? '' : ' showme-single') .
            '" aria-labelledby="showme-title"><div class="showme-text"><p class="showme-label">Dein erster Schritt</p><h2 id="showme-title">Zeig dich den anderen.</h2>' .
            showme_share_step($data, false) .
            '<small class="showme-note">Nur für angemeldete Mitglieder sichtbar, nie öffentlich. Jederzeit im Profil änderbar.</small></div>' .
            $preview .
            '</section>';
    }
    if ($just_shared && $newsletter) {
        return '<section class="showme showme-single" aria-labelledby="showme-title"><div class="showme-text">' .
            $label .
            '<h2 id="showme-title">Noch eine kurze Frage.</h2>' .
            showme_shared_step($data) .
            showme_newsletter_step() .
            $later .
            '</div></section>';
    }
    if ($just_shared) {
        $values = showme_values($data);
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
    return '';
}

/** Shares "Über dich" for the own account, the same way the section switch in the profile does. */
function showme_share($user_id) {
    $user = get_user_by('id', (int) $user_id);
    $data = profile_data($user->ID);
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
        // One "later" for the whole card: both questions come back together.
        update_user_meta($id, 'eintrikot_showme_later', time() + SHOWME_LATER_DAYS * DAY_IN_SECONDS);
        if (newsletter_prompt_due($id)) {
            newsletter_postpone($id);
        }
    } elseif ($choice === 'share') {
        if (is_wp_error(showme_share($id))) {
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
