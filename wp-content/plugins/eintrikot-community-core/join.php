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
    echo '</form></div>';
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

add_shortcode('eintrikot_join_button', function ($atts) {
    $url = join_url();
    if ($url === '') {
        return '';
    }
    $atts = shortcode_atts(['label' => 'Jetzt Mitglied werden'], $atts, 'eintrikot_join_button');
    return '<div class="wp-block-buttons et-join-actions"><div class="wp-block-button button solid"><a class="wp-block-button__link wp-element-button" href="' .
        esc_url($url) .
        '">' .
        esc_html($atts['label']) .
        ' →</a></div></div>';
});
