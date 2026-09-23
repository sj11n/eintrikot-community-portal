<?php
namespace Eintrikot\Community;
if (!defined('ABSPATH')) {
    exit();
}
add_action('admin_menu', function () {
    add_submenu_page(
        'eintrikot-community-setup',
        'Website verbinden',
        'Website verbinden',
        'manage_options',
        'eintrikot-site',
        __NAMESPACE__ . '\site_setup'
    );
});
function site_setup() {
    if (!current_user_can('manage_options')) {
        return;
    }
    echo '<div class="wrap"><h1>Website verbinden</h1><p>Veröffentlicht die vorbereiteten Community-Seiten auf dieser Entwicklungsinstallation, legt das News-Archiv an und verbindet Startseite sowie Navigation. Bereits bearbeitete Seiteninhalte bleiben erhalten.</p><form method="post" action="' .
        esc_url(admin_url('admin-post.php')) .
        '">';
    wp_nonce_field('et_connect_site');
    echo '<input type="hidden" name="action" value="et_connect_site">';
    submit_button('Community-Seiten verbinden');
    echo '</form></div>';
}
function nav_link($id, $label) {
    return '<!-- wp:navigation-link ' .
        wp_json_encode([
            'label' => $label,
            'type' => 'page',
            'id' => (int) $id,
            'url' => get_permalink($id),
            'kind' => 'post-type'
        ]) .
        ' /-->';
}
add_action('admin_post_et_connect_site', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Keine Berechtigung.', '', ['response' => 403]);
    }
    check_admin_referer('et_connect_site');
    $ids = get_option('eintrikot_draft_pages', []);
    foreach (pages() as $slug => $title) {
        if (empty($ids[$slug]) || !get_post($ids[$slug])) {
            wp_die('Bitte zuerst alle Seitenentwürfe anlegen.');
        }
    }
    foreach (pages() as $slug => $title) {
        $result = wp_update_post(
            ['ID' => $ids[$slug], 'post_status' => 'publish', 'post_title' => $title],
            true
        );
        if (is_wp_error($result)) {
            wp_die(esc_html($result->get_error_message()));
        }
    }
    $portal = prepare_portal_page();
    wp_update_post(['ID' => $portal, 'post_status' => 'publish']);
    $news = (int) get_option('eintrikot_news_page');
    if (!$news || !get_post($news)) {
        $news = wp_insert_post(
            [
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_title' => 'News',
                'post_name' => 'community-news'
            ],
            true
        );
        if (is_wp_error($news)) {
            wp_die('News-Seite konnte nicht angelegt werden.');
        }
        update_option('eintrikot_news_page', $news, false);
    }
    update_option('show_on_front', 'page');
    update_option('page_on_front', (int) $ids['startseite']);
    update_option('page_for_posts', $news);
    // Dedicated block navigation remains editable in the WordPress site editor.
    if (!get_option('eintrikot_navigation_ready')) {
        $nav =
            nav_link($ids['verein'], 'Der Verein') .
            nav_link($ids['engagement'], 'Engagement') .
            nav_link($news, 'News') .
            nav_link($ids['unterstuetzen'], 'Unterstützen') .
            nav_link($portal, 'Anmelden');
        $navid = wp_insert_post(
            [
                'post_type' => 'wp_navigation',
                'post_status' => 'publish',
                'post_title' => 'EINTRIKOT Hauptmenü',
                'post_content' => wp_slash($nav)
            ],
            true
        );
        if (is_wp_error($navid)) {
            wp_die('Navigation konnte nicht angelegt werden.');
        }
        $logo = esc_url(get_theme_file_uri('assets/logo.svg'));
        $brand =
            '<!-- wp:html --><a class="et-brand" href="' .
            esc_url(home_url('/')) .
            '"><img src="' .
            $logo .
            '" alt="EINTRIKOT" width="180" height="44"></a><!-- /wp:html -->';
        $header =
            '<!-- wp:group {"className":"et-header","layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap"}} --><div class="wp-block-group et-header">' .
            $brand .
            '<!-- wp:navigation {"ref":' .
            (int) $navid .
            ',"overlayMenu":"mobile","layout":{"type":"flex","justifyContent":"right"}} /--></div><!-- /wp:group -->';
        $footer =
            '<!-- wp:group {"className":"et-footer","layout":{"type":"constrained"}} --><div class="wp-block-group et-footer">' .
            $brand .
            '<!-- wp:paragraph --><p>EINTRIKOT e. V. · Gemeinnütziger Verein</p><!-- /wp:paragraph --><!-- wp:navigation {"overlayMenu":"never"} -->';
        foreach (['impressum' => 'Impressum', 'datenschutz' => 'Datenschutz'] as $slug => $label) {
            $page = get_page_by_path($slug);
            if ($page) {
                $footer .= nav_link($page->ID, $label);
            }
        }
        $footer .= '<!-- /wp:navigation --></div><!-- /wp:group -->';
        foreach (['header' => $header, 'footer' => $footer] as $slug => $content) {
            $part = wp_insert_post(
                [
                    'post_type' => 'wp_template_part',
                    'post_status' => 'publish',
                    'post_name' => $slug,
                    'post_title' => ucfirst($slug),
                    'post_content' => wp_slash($content)
                ],
                true
            );
            if (is_wp_error($part)) {
                wp_die('Template-Teil konnte nicht angelegt werden.');
            }
            wp_set_object_terms($part, 'eintrikot-community', 'wp_theme');
            wp_set_object_terms($part, $slug, 'wp_template_part_area');
        }
        update_option('eintrikot_navigation_ready', 1, false);
    }
    wp_safe_redirect(home_url('/'));
    exit();
});
