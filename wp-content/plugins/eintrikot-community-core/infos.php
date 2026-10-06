<?php
namespace Eintrikot\Community;
if (!defined('ABSPATH')) {
    exit();
}
add_action('init', function () {
    foreach (['eintrikot_editor', 'eintrikot_board', 'administrator'] as $name) {
        $role = get_role($name);
        if ($role && !$role->has_cap('eintrikot_edit_infos')) {
            $role->add_cap('eintrikot_edit_infos');
        }
    }
    foreach (['eintrikot_editor', 'eintrikot_board'] as $name) {
        $role = get_role($name);
        if ($role) {
            foreach (['edit_posts', 'edit_published_posts', 'publish_posts', 'upload_files'] as $cap) {
                if (!$role->has_cap($cap)) {
                    $role->add_cap($cap);
                }
            }
        }
    }
    $caps = array_fill_keys(
        [
            'edit_post',
            'read_post',
            'delete_post',
            'edit_posts',
            'edit_others_posts',
            'publish_posts',
            'read_private_posts',
            'delete_posts',
            'delete_private_posts',
            'delete_published_posts',
            'delete_others_posts',
            'edit_private_posts',
            'edit_published_posts',
            'create_posts'
        ],
        'eintrikot_edit_infos'
    );
    foreach (['et_info' => 'Vereinsinfos', 'et_calendar' => 'EINTRIKOT Kalender'] as $type => $label) {
        register_post_type($type, [
            'label' => $label,
            'public' => false,
            'publicly_queryable' => false,
            'exclude_from_search' => true,
            'show_ui' => true,
            'show_in_rest' => false,
            'show_in_nav_menus' => false,
            'query_var' => false,
            'rewrite' => false,
            'capabilities' => $caps,
            'map_meta_cap' => false,
            'supports' => ['title', 'editor'],
            'menu_icon' => $type === 'et_info' ? 'dashicons-megaphone' : 'dashicons-calendar-alt'
        ]);
    }
});
function render_infos_body() {
    if (!member_access()) {
        return;
    }
    if (current_user_can('eintrikot_edit_infos')) {
        echo '<p><a class="text-link" href="' .
            esc_url(admin_url('post-new.php?post_type=et_info')) .
            '">Vereinsinfo schreiben →</a></p>';
    }
    $page = max(1, absint($_GET['info_page'] ?? 1));
    $query = new \WP_Query([
        'post_type' => 'et_info',
        'post_status' => 'publish',
        'posts_per_page' => 10,
        'paged' => $page
    ]);
    if (!$query->have_posts()) {
        echo '<p class="portal-empty">Noch keine Vereinsinfos. Neue Mitteilungen erscheinen hier und auf deiner Startseite.</p>';
    }
    foreach ($query->posts as $post) {
        echo '<article class="info-entry"><p class="info-date">' .
            esc_html(get_the_date('j. F Y', $post)) .
            '</p><h2>' .
            esc_html($post->post_title) .
            '</h2>' .
            wp_kses_post(apply_filters('the_content', $post->post_content)) . // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core filter
            '</article>';
    }
    if ($page > 1) {
        echo '<a class="text-link" href="' .
            esc_url(portal_url('aktuelles', ['tab' => 'mitteilungen', 'info_page' => $page - 1])) .
            '">← Neuere Infos</a> ';
    }
    if ($page < $query->max_num_pages) {
        echo '<a class="text-link" href="' .
            esc_url(portal_url('aktuelles', ['tab' => 'mitteilungen', 'info_page' => $page + 1])) .
            '">Ältere Infos →</a>';
    }
}

require_once __DIR__ . '/events.php';
