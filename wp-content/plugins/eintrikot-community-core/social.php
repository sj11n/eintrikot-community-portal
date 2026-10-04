<?php
/**
 * Die eigenen Kanäle des Vereins (LinkedIn, Instagram, Facebook) auf der öffentlichen Website.
 *
 * - Adressen unter Community-Aufbau → Social Media. Ohne Adresse erscheint nichts.
 * - Footer: „Folge uns“ mit den einfarbigen Logos (club_social_html() im Footer-Muster, auch als [eintrikot_social]).
 * - News: unter den Beitragslisten ein Hinweis auf LinkedIn.
 * - Suchmaschinen: die Adressen als „sameAs“ der Organisation (schema.org).
 * Alles sind einfache Links: keine eingebetteten Inhalte, keine Daten an die Netzwerke vor einem Klick.
 */
namespace Eintrikot\Community;
if (!defined('ABSPATH')) {
    exit();
}

/** The club's channels: key => https URL, only valid ones, in display order. */
function club_social_urls() {
    $saved = get_option('eintrikot_club_social', []);
    $out = [];
    foreach (['linkedin', 'instagram', 'facebook'] as $key) {
        $url = is_array($saved) ? (string) ($saved[$key] ?? '') : '';
        if (str_starts_with($url, 'https://')) {
            $out[$key] = $url;
        }
    }
    return $out;
}

add_action('admin_menu', function () {
    add_submenu_page(
        'eintrikot-community-setup',
        'Social Media',
        'Social Media',
        'manage_options',
        'eintrikot-social',
        __NAMESPACE__ . '\club_social_page'
    );
});

function club_social_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    $urls = club_social_urls();
    echo '<div class="wrap"><h1>Social Media des Vereins</h1>';
    if (isset($_GET['saved'])) {
        echo '<div class="notice notice-success"><p>Gespeichert.</p></div>';
    }
    if (isset($_GET['invalid'])) {
        echo '<div class="notice notice-error"><p>Nicht gespeichert: ' .
            esc_html(sanitize_text_field(wp_unslash($_GET['invalid']))) .
            ' ist keine Adresse dieses Netzwerks.</p></div>';
    }
    echo '<p>Diese Links erscheinen im Footer jeder Seite („Folge uns“), LinkedIn zusätzlich unter den News. Leere Felder erscheinen nirgends. Es sind einfache Links ohne eingebettete Inhalte.</p><form method="post" action="' .
        esc_url(admin_url('admin-post.php')) .
        '">';
    wp_nonce_field('et_club_social');
    echo '<input type="hidden" name="action" value="et_club_social"><table class="form-table"><tbody>';
    foreach (
        [
            'linkedin' => ['LinkedIn', 'https://www.linkedin.com/company/…'],
            'instagram' => ['Instagram', '@eintrikot oder https://www.instagram.com/…'],
            'facebook' => ['Facebook', 'https://www.facebook.com/…']
        ]
        as $key => [$label, $hint]
    ) {
        echo '<tr><th scope="row"><label for="et-club-' .
            esc_attr($key) .
            '">' .
            esc_html($label) .
            '</label></th><td><input id="et-club-' .
            esc_attr($key) .
            '" class="regular-text code" name="club_social[' .
            esc_attr($key) .
            ']" placeholder="' .
            esc_attr($hint) .
            '" value="' .
            esc_attr($urls[$key] ?? '') .
            '"></td></tr>';
    }
    echo '</tbody></table>';
    submit_button('Speichern');
    echo '</form></div>';
}

add_action('admin_post_et_club_social', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Keine Berechtigung.', '', ['response' => 403]);
    }
    check_admin_referer('et_club_social');
    $raw =
        isset($_POST['club_social']) && is_array($_POST['club_social'])
            ? wp_unslash($_POST['club_social'])
            : [];
    $save = [];
    foreach (['linkedin', 'instagram', 'facebook'] as $key) {
        $value = sanitize_text_field((string) ($raw[$key] ?? ''));
        if ($value === '') {
            continue;
        }
        // Company pages: "linkedin.com/company/name" passes the same domain check as member links.
        $url = social_url($key, $value);
        if (!$url) {
            wp_safe_redirect(
                admin_url(
                    'admin.php?page=eintrikot-social&invalid=' . rawurlencode(social_networks()[$key][0])
                )
            );
            exit();
        }
        $save[$key] = $url;
    }
    update_option('eintrikot_club_social', $save, false);
    wp_safe_redirect(admin_url('admin.php?page=eintrikot-social&saved=1'));
    exit();
});

/** "Folge uns" row with the one-colour logos; '' without any channel. */
function club_social_html() {
    $items = '';
    foreach (club_social_urls() as $key => $url) {
        $label = social_networks()[$key][0];
        $logo = social_logo($key, 'mono/');
        $items .=
            '<li><a href="' .
            esc_url($url) .
            '" target="_blank" rel="noopener noreferrer" aria-label="EINTRIKOT auf ' .
            esc_attr($label) .
            '" title="EINTRIKOT auf ' .
            esc_attr($label) .
            '">' .
            ($logo !== ''
                ? '<img src="' . esc_url(plugins_url($logo, __FILE__)) . '" alt="" width="22" height="22">'
                : esc_html($label)) .
            '</a></li>';
    }
    return $items
        ? '<div class="et-follow"><span class="et-follow-label">Folge uns</span><ul>' . $items . '</ul></div>'
        : '';
}
add_shortcode('eintrikot_social', __NAMESPACE__ . '\club_social_html');

// News: after every public list of posts (start page and news page) a pointer to LinkedIn.
add_filter(
    'render_block',
    function ($html, $block) {
        if (
            ($block['blockName'] ?? '') !== 'core/query' ||
            ($block['attrs']['query']['postType'] ?? 'post') !== 'post' ||
            is_admin()
        ) {
            return $html;
        }
        $url = club_social_urls()['linkedin'] ?? '';
        if ($url === '') {
            return $html;
        }
        $logo = social_logo('linkedin');
        return $html .
            '<p class="et-news-more">' .
            ($logo !== ''
                ? '<img src="' . esc_url(plugins_url($logo, __FILE__)) . '" alt="" width="20" height="20"> '
                : '') .
            'Mehr aus dem Netzwerk: <a href="' .
            esc_url($url) .
            '" target="_blank" rel="noopener noreferrer">EINTRIKOT auf LinkedIn folgen</a></p>';
    },
    10,
    2
);

// Search engines: the club and its channels (no tracking, plain data in the page).
add_action('wp_head', function () {
    $urls = array_values(club_social_urls());
    if (!$urls || is_admin()) {
        return;
    }
    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'NGO',
        'name' => 'EINTRIKOT e. V.',
        'url' => home_url('/'),
        'logo' => get_theme_file_uri('assets/logo.svg'),
        'sameAs' => $urls
    ];
    echo '<script type="application/ld+json">' .
        wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) .
        "</script>\n";
});

// Styles for the public pages (the portal stylesheet is not loaded there).
add_action('wp_enqueue_scripts', function () {
    if (!club_social_urls()) {
        return;
    }
    wp_register_style('eintrikot-social', false);
    wp_enqueue_style('eintrikot-social');
    wp_add_inline_style(
        'eintrikot-social',
        '.et-follow{display:flex;align-items:center;gap:14px;margin-top:22px}.et-follow-label{font-size:12px;font-weight:600;letter-spacing:.06em;text-transform:uppercase}.et-follow ul{display:flex;gap:10px;list-style:none;margin:0;padding:0}.et-follow a{display:inline-flex;align-items:center;justify-content:center;width:44px;height:44px;border-radius:50%;background:#fff;transition:transform .2s}.et-follow a:hover,.et-follow a:focus-visible{transform:translateY(-2px)}.et-follow img,.footer .et-follow img{display:block;width:22px;height:22px;margin:0}.et-news-more{margin:28px 0 0;font-size:15px;line-height:1.6}.et-news-more img{display:inline-block;vertical-align:-4px;margin-right:6px}.et-news-more a{text-decoration:underline;text-underline-offset:4px}'
    );
});
