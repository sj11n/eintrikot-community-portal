<?php
/**
 * Plugin Name: EINTRIKOT Community Core
 * Description: Getrennte Einrichtung und Berechtigungen für das EINTRIKOT-Portal. Entwicklungsstand.
 * Version: 0.12.0
 * Requires PHP: 8.1
 */
namespace Eintrikot\Community;
if (!defined('ABSPATH')) {
    exit();
}

function activate() {
    add_role('eintrikot_member', 'EINTRIKOT Mitglied', ['read' => true, 'eintrikot_portal' => true]);
    add_role('eintrikot_editor', 'EINTRIKOT Redaktion', ['read' => true, 'eintrikot_portal' => true]);
    add_role('eintrikot_board', 'EINTRIKOT Vorstand', [
        'read' => true,
        'eintrikot_portal' => true,
        'eintrikot_manage_members' => true
    ]);
    $admin = get_role('administrator');
    if ($admin) {
        $admin->add_cap('eintrikot_portal');
        $admin->add_cap('eintrikot_manage_members');
    }
}
register_activation_hook(__FILE__, __NAMESPACE__ . '\activate');

add_action('admin_menu', function () {
    add_menu_page(
        'EINTRIKOT Community',
        'Community-Aufbau',
        'manage_options',
        'eintrikot-community-setup',
        __NAMESPACE__ . '\setup_page',
        'dashicons-groups'
    );
    add_submenu_page(
        'eintrikot-community-setup',
        'EINTRIKOT Community',
        'Übersicht',
        'manage_options',
        'eintrikot-community-setup',
        __NAMESPACE__ . '\setup_page'
    );
});
function setup_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    $links = [
        'eintrikot-metrics' => ['Kennzahlen', 'Zahlen und Stand-Datum auf der Startseite.'],
        'eintrikot-join' => ['Beitritt', 'Link zum MeinVerein-Beitritt für „Mitglied werden".'],
        'eintrikot-onboarding' => [
            'Aufnahme & Urkunde',
            'Absender, Signatur, Urkunden-Vorlage und Test-E-Mail.'
        ],
        'eintrikot-updates' => ['Aktualisierungen', 'Einzelne, bewusst auszulösende Inhaltsänderungen.']
    ];
    echo '<div class="wrap"><h1>EINTRIKOT Community</h1><p>Einstellungen für Website und Mitgliederportal. Texte und Bilder pflegst du unter Seiten und Beiträge, Mitglieder im Portal.</p><ul>';
    foreach ($links as $slug => [$label, $hint]) {
        printf(
            '<li><a href="%s"><strong>%s</strong></a> – %s</li>',
            esc_url(admin_url('admin.php?page=' . $slug)),
            esc_html($label),
            esc_html($hint)
        );
    }
    $portal = (int) get_option('eintrikot_portal_page');
    if ($portal) {
        printf(
            '<li><a href="%s"><strong>Mitgliederportal öffnen</strong></a></li>',
            esc_url(get_permalink($portal))
        );
    }
    echo '</ul></div>';
}

/**
 * Cache-busting version for a plugin asset: changes whenever the file changes.
 */
function asset_version($file) {
    $path = __DIR__ . '/' . $file;
    return file_exists($path) ? (string) filemtime($path) : null;
}

require_once __DIR__ . '/portal.php';
register_activation_hook(__FILE__, __NAMESPACE__ . '\portal_install');
register_activation_hook(__FILE__, __NAMESPACE__ . '\prepare_portal_page');

// Upgrades also run when WordPress replaces an already active plugin.
add_action('admin_init', function () {
    if (current_user_can('manage_options') && get_option('eintrikot_schema_version') !== '0.4.0') {
        activate();
        portal_install();
        update_option('eintrikot_schema_version', '0.4.0', false);
    }
});
function prepare_portal_page() {
    $existing = (int) get_option('eintrikot_portal_page');
    if ($existing && get_post($existing)) {
        return $existing;
    }
    $id = wp_insert_post(
        [
            'post_type' => 'page',
            'post_status' => 'draft',
            'post_title' => 'Mitgliederportal',
            'post_name' => 'community-portal',
            'post_content' => '<!-- wp:shortcode -->[eintrikot_portal]<!-- /wp:shortcode -->'
        ],
        true
    );
    if (is_wp_error($id)) {
        wp_die(esc_html($id->get_error_message()));
    }
    update_option('eintrikot_portal_page', $id, false);
    return $id;
}
add_action('wp_enqueue_scripts', function () {
    if (
        ((int) get_option('eintrikot_portal_page') > 0 &&
            is_page((int) get_option('eintrikot_portal_page'))) ||
        ((int) get_option('eintrikot_audit_page') > 0 && is_page((int) get_option('eintrikot_audit_page')))
    ) {
        wp_enqueue_style(
            'eintrikot-portal',
            plugins_url('portal.css', __FILE__),
            [],
            asset_version('portal.css')
        );
    }
});

require_once __DIR__ . '/infos.php';

require_once __DIR__ . '/metrics.php';

require_once __DIR__ . '/join.php';

require_once __DIR__ . '/updates.php';
