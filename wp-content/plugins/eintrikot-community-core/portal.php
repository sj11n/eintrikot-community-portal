<?php
namespace Eintrikot\Community;
if (!defined('ABSPATH')) {
    exit();
}
function member_access() {
    return is_user_logged_in() && current_user_can('eintrikot_portal');
}
function manager_access() {
    return current_user_can('manage_options') || current_user_can('eintrikot_manage_members');
}
function portal_url($view = 'portal', $extra = []) {
    return add_query_arg(
        array_merge(['view' => $view], $extra),
        get_permalink((int) get_option('eintrikot_portal_page'))
    );
}
function profile_data($id) {
    $v = get_user_meta($id, 'eintrikot_profile', true);
    return is_array($v) ? $v : [];
}
function visible_value($data, $key) {
    return ($data['visibility'][$key] ?? 'private') === 'members' ? $data[$key] ?? '' : '';
}
function log_change($target, $field, $before, $after, $reason) {
    global $wpdb;
    return $wpdb->insert(
        $wpdb->prefix . 'eintrikot_audit',
        [
            'created_at' => current_time('mysql', true),
            'actor' => get_current_user_id(),
            'target' => (int) $target,
            'field' => sanitize_key($field),
            'before_value' => wp_json_encode($before),
            'after_value' => wp_json_encode($after),
            'reason' => sanitize_textarea_field($reason)
        ],
        ['%s', '%d', '%d', '%s', '%s', '%s', '%s']
    );
}
function portal_install() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta("CREATE TABLE {$wpdb->prefix}eintrikot_audit (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 created_at datetime NOT NULL,
 actor bigint(20) unsigned NOT NULL,
 target bigint(20) unsigned NOT NULL,
 field varchar(100) NOT NULL,
 before_value longtext NOT NULL,
 after_value longtext NOT NULL,
 reason text NOT NULL,
 PRIMARY KEY  (id)
 ) {$wpdb->get_charset_collate()};");
}
add_action('init', function () {
    register_post_type('et_request', [
        'label' => 'Service-Anfragen',
        'public' => false,
        'publicly_queryable' => false,
        'show_ui' => false,
        'show_in_rest' => false,
        'rewrite' => false,
        'query_var' => false,
        'supports' => ['title', 'editor', 'author']
    ]);
});
add_action('template_redirect', function () {
    if ((int) get_option('eintrikot_portal_page') > 0 && is_page((int) get_option('eintrikot_portal_page'))) {
        nocache_headers();
        header('X-Robots-Tag: noindex, nofollow', true);
    }
});
function is_portal_user($id) {
    $u = get_user_by('id', $id);
    return $u && user_can($u, 'eintrikot_portal');
}
function post_text($key, $default = '', $max = 4000) {
    $raw = $_POST[$key] ?? $default;
    if (!is_scalar($raw)) {
        wp_die('Ungültige Eingabe.', '', ['response' => 400]);
    }
    $value = sanitize_textarea_field(wp_unslash((string) $raw));
    if (mb_strlen($value) > $max) {
        wp_die('Eingabe ist zu lang.', '', ['response' => 400]);
    }
    return $value;
}

require_once __DIR__ . '/services.php';

require_once __DIR__ . '/avatar.php';

require_once __DIR__ . '/shell.php';

require_once __DIR__ . '/profile.php';

require_once __DIR__ . '/directory.php';

require_once __DIR__ . '/requests.php';

require_once __DIR__ . '/audit.php';

require_once __DIR__ . '/home.php';
