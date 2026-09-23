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
    add_editor_style(['assets/mvp.css', 'assets/editor.css']);
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
