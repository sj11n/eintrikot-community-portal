<?php
namespace Eintrikot\Community;
if (!defined('ABSPATH')) {
    exit();
}
/**
 * "Ansicht wechseln": an administrator can look at the portal as a member, editor or board member.
 * Only the portal view changes. The account keeps its real role, so nothing is logged, nothing sticks to the
 * account, and every form and action is still checked against the real rights.
 */
const PREVIEW_META = 'eintrikot_preview_role';

function preview_roles() {
    return [
        'member' => ['Mitglied', 'Start, Mitglieder, Aktuelles und Service, wie jedes Mitglied sie sieht.'],
        'editor' => ['Redakteur', 'Zusätzlich der Bereich Redaktion für Vereinsinfos und Termine.'],
        'board' => ['Vorstand', 'Zusätzlich Redaktion und Verwaltung mit Anfragen, Profilen und Protokoll.']
    ];
}

/** The role an administrator currently looks through, or '' for the own view. */
function preview_role() {
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        return '';
    }
    $role = get_user_meta(get_current_user_id(), PREVIEW_META, true);
    return is_string($role) && isset(preview_roles()[$role]) ? $role : '';
}

/** What the portal shows. Handlers and forms keep using the real rights (manager_access, current_user_can). */
function view_can_edit_infos() {
    $preview = preview_role();
    return $preview === ''
        ? current_user_can('eintrikot_edit_infos')
        : in_array($preview, ['editor', 'board'], true);
}
function view_manager() {
    $preview = preview_role();
    return $preview === '' ? manager_access() : $preview === 'board';
}
function view_admin() {
    return preview_role() === '' && current_user_can('manage_options');
}

/** Strip above the page while a preview runs, so nobody forgets it. */
function preview_bar() {
    $role = preview_role();
    if ($role === '') {
        return '';
    }
    return '<div class="preview-bar" role="status"><span>Du siehst das Portal als <strong>' .
        esc_html(preview_roles()[$role][0]) .
        '</strong></span><a href="' .
        esc_url(portal_url('preview')) .
        '">Ansicht wechseln</a><form method="post" action="' .
        esc_url(admin_url('admin-post.php')) .
        '"><input type="hidden" name="action" value="et_preview"><input type="hidden" name="role" value="admin">' .
        wp_nonce_field('et_preview', '_wpnonce', true, false) .
        '<button type="submit">Zurück zu Administrator</button></form></div>';
}

function render_preview_page() {
    if (!current_user_can('manage_options')) {
        echo '<div class="portal-empty"><h1>Kein Zugriff</h1><p>Dieser Bereich ist für deine Rolle nicht freigeschaltet.</p></div>';
        return;
    }
    $current = preview_role() ?: 'admin';
    $roles = preview_roles() + ['admin' => ['Administrator', 'Deine eigene Ansicht mit allen Bereichen.']];
    echo page_head(
        'Ansicht wechseln',
        'Sieh das Portal so, wie es eine andere Rolle sieht. Dein Konto und deine Rechte bleiben unverändert.',
        '',
        'admin'
    ) . '<section class="portal-section"><div class="preview-choices">';
    foreach ($roles as $key => $info) {
        echo '<form method="post" action="' .
            esc_url(admin_url('admin-post.php')) .
            '" class="preview-choice' .
            ($key === $current ? ' active' : '') .
            '"><input type="hidden" name="action" value="et_preview"><input type="hidden" name="role" value="' .
            esc_attr($key) .
            '">' .
            wp_nonce_field('et_preview', '_wpnonce', true, false) . // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress core builds the hidden nonce field
            '<strong>' .
            esc_html($info[0]) .
            ($key === $current ? ' (aktuell)' : '') .
            '</strong><small>' .
            esc_html($info[1]) .
            '</small><button type="submit"' .
            ($key === $current ? ' aria-current="true"' : '') .
            '>' .
            ($key === 'admin' ? 'Zurück zu Administrator' : 'Ansehen als ' . esc_html($info[0])) .
            '</button></form>';
    }
    echo '</div><p class="description">Die Ansicht gilt nur im Portal und nur für dich. Das WordPress-Backend bleibt unverändert, und beim Abmelden endet die Vorschau. Gespeichert wird weiterhin mit deinen Administratorrechten.</p></section>';
}

add_action('admin_post_et_preview', function () {
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        wp_die('Keine Berechtigung.', '', ['response' => 403]);
    }
    check_admin_referer('et_preview');
    $role = post_choice('role', array_merge(array_keys(preview_roles()), ['admin']), 'admin');
    if ($role === 'admin') {
        delete_user_meta(get_current_user_id(), PREVIEW_META);
        wp_safe_redirect(portal_url('admin'));
    } else {
        update_user_meta(get_current_user_id(), PREVIEW_META, $role);
        wp_safe_redirect(portal_url('aktuelles'));
    }
    exit();
});

add_action('wp_logout', function ($user_id) {
    delete_user_meta((int) $user_id, PREVIEW_META);
});
