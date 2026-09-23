<?php
if (!defined('ABSPATH')) {
    exit();
}

/**
 * Cache-busting version for a theme asset: changes whenever the file changes.
 */
function eintrikot_asset_version($file) {
    $path = get_theme_file_path($file);
    return file_exists($path) ? (string) filemtime($path) : null;
}
add_action('after_setup_theme', function () {
    add_theme_support('editor-styles');
    add_editor_style(['assets/mvp.css', 'assets/editor.css', 'assets/refresh.css']);
});
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'eintrikot-community',
        get_theme_file_uri('assets/site.css'),
        [],
        eintrikot_asset_version('assets/site.css')
    );
});
add_action('init', function () {
    register_block_pattern_category('eintrikot', ['label' => 'EINTRIKOT']);
});

add_action(
    'wp_enqueue_scripts',
    function () {
        if (is_page_template('mvp-public') || is_page_template('mvp-portal') || is_singular('post')) {
            wp_dequeue_style('eintrikot-community');
            wp_enqueue_style(
                'eintrikot-mvp',
                get_theme_file_uri('assets/mvp.css'),
                [],
                eintrikot_asset_version('assets/mvp.css')
            );
            wp_enqueue_style(
                'eintrikot-wp-adapter',
                get_theme_file_uri('assets/wordpress.css'),
                ['eintrikot-mvp'],
                eintrikot_asset_version('assets/wordpress.css')
            );
            wp_enqueue_style(
                'eintrikot-refresh',
                get_theme_file_uri('assets/refresh.css'),
                ['eintrikot-wp-adapter'],
                eintrikot_asset_version('assets/refresh.css')
            );
        }
    },
    30
);

add_action('login_enqueue_scripts', function () {
    wp_enqueue_style(
        'eintrikot-login',
        get_theme_file_uri('assets/login.css'),
        [],
        eintrikot_asset_version('assets/login.css')
    );
});
add_filter('login_headerurl', fn() => home_url('/'));
add_filter('login_headertext', fn() => 'EINTRIKOT');

// Administration remains available through wp-admin; the public design stays uncluttered.
add_filter('show_admin_bar', '__return_false');

// Preload the subset WOFF2 so text renders in Montserrat without a visible font swap.
add_action(
    'wp_head',
    function () {
        echo '<link rel="preload" href="' .
            esc_url(get_theme_file_uri('assets/montserrat.woff2')) .
            '" as="font" type="font/woff2" crossorigin>' .
            "\n";
    },
    1
);

/**
 * Description and link preview (Open Graph) for public pages.
 * Skipped when an SEO plugin already provides them, and on protected portal pages.
 */
function eintrikot_share_meta() {
    $title = 'EINTRIKOT – Das Netzwerk der Hockey-Nationalteams';
    $description =
        'Wir verbinden Nationalspielerinnen und Nationalspieler aller Generationen – von der Jugend bis zu den Masters.';
    $url = home_url('/');
    if (is_singular() && !is_front_page()) {
        $post = get_queried_object();
        $title = wp_strip_all_tags(get_the_title($post)) . ' – EINTRIKOT';
        $url = get_permalink($post);
        if (has_excerpt($post)) {
            $description = wp_strip_all_tags(get_the_excerpt($post));
        }
    }
    return [
        'title' => $title,
        'description' => $description,
        'url' => $url,
        'image' => get_theme_file_uri('assets/og-image.jpg')
    ];
}

function eintrikot_is_protected_page() {
    foreach (['eintrikot_portal_page', 'eintrikot_audit_page'] as $option) {
        $id = (int) get_option($option);
        if ($id > 0 && is_page($id)) {
            return true;
        }
    }
    return false;
}

add_action(
    'wp_head',
    function () {
        if (defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') || defined('SEOPRESS_VERSION')) {
            return;
        }
        if (is_404() || is_search() || eintrikot_is_protected_page()) {
            return;
        }
        $meta = eintrikot_share_meta();
        $tags = [
            ['name', 'description', $meta['description']],
            ['property', 'og:type', 'website'],
            ['property', 'og:locale', 'de_DE'],
            ['property', 'og:site_name', 'EINTRIKOT'],
            ['property', 'og:title', $meta['title']],
            ['property', 'og:description', $meta['description']],
            ['property', 'og:url', $meta['url']],
            ['property', 'og:image', $meta['image']],
            ['property', 'og:image:width', '1200'],
            ['property', 'og:image:height', '630'],
            ['property', 'og:image:alt', 'EINTRIKOT – Das Netzwerk der Nationalteams'],
            ['name', 'twitter:card', 'summary_large_image']
        ];
        foreach ($tags as [$attr, $key, $value]) {
            printf('<meta %s="%s" content="%s">' . "\n", $attr, esc_attr($key), esc_attr($value));
        }
    },
    5
);

/**
 * Favicon from the logo mark (the "1"), for Chrome, Safari, iOS and Android.
 * Replaces the WordPress site icon so every browser shows the same mark.
 */
function eintrikot_favicon_tags() {
    $base = get_theme_file_uri('assets/favicon/');
    $v = eintrikot_asset_version('assets/favicon/favicon.svg');
    printf('<link rel="icon" href="%s" sizes="48x48">' . "\n", esc_url($base . 'favicon.ico?v=' . $v));
    printf('<link rel="icon" href="%s" type="image/svg+xml">' . "\n", esc_url($base . 'favicon.svg?v=' . $v));
    printf(
        '<link rel="icon" href="%s" type="image/png" sizes="32x32">' . "\n",
        esc_url($base . 'favicon-32.png?v=' . $v)
    );
    printf('<link rel="apple-touch-icon" href="%s">' . "\n", esc_url($base . 'apple-touch-icon.png?v=' . $v));
    printf('<link rel="manifest" href="%s">' . "\n", esc_url($base . 'site.webmanifest?v=' . $v));
    echo '<meta name="theme-color" content="#C8E2EE">' . "\n";
}
remove_action('wp_head', 'wp_site_icon', 99);
remove_action('login_head', 'wp_site_icon', 99);
remove_action('admin_head', 'wp_site_icon');
add_action('wp_head', 'eintrikot_favicon_tags', 2);
add_action('login_head', 'eintrikot_favicon_tags');
add_action('admin_head', 'eintrikot_favicon_tags');
// Browsers that ask for /favicon.ico directly get the same icon instead of the WordPress logo.
add_action('do_faviconico', function () {
    wp_safe_redirect(get_theme_file_uri('assets/favicon/favicon.ico'), 301);
    exit();
});
