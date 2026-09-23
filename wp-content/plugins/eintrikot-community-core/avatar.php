<?php
namespace Eintrikot\Community;
if (!defined('ABSPATH')) {
    exit();
}
function member_avatar($id, $name) {
    $data = get_user_meta($id, 'eintrikot_avatar', true);
    if (is_string($data) && str_starts_with($data, 'data:image/jpeg;base64,')) {
        return '<img class="et-avatar" src="' . esc_attr($data) . '" alt="" width="64" height="64">';
    }
    return '<span class="et-avatar" aria-hidden="true">' . esc_html(mb_substr($name, 0, 1)) . '</span>';
}
function prepare_avatar() {
    if (!empty($_POST['remove_avatar'])) {
        return '';
    }
    if (
        empty($_FILES['avatar']) ||
        ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
    ) {
        return;
    }
    $file = $_FILES['avatar'];
    if (
        !is_array($file) ||
        ($file['error'] ?? 1) !== UPLOAD_ERR_OK ||
        !is_string($file['tmp_name'] ?? null) ||
        !is_uploaded_file($file['tmp_name']) ||
        ($file['size'] ?? 0) > 5 * 1024 * 1024
    ) {
        return new \WP_Error('avatar', 'Bitte ein Bild bis 5 MB verwenden.');
    }
    $size = wp_getimagesize($file['tmp_name']);
    if (
        !$size ||
        !in_array($size['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true) ||
        $size[0] * $size[1] > 24000000
    ) {
        return new \WP_Error('avatar', 'Bitte ein JPEG-, PNG- oder WebP-Bild bis 24 Megapixel verwenden.');
    }
    $editor = wp_get_image_editor($file['tmp_name']);
    if (is_wp_error($editor)) {
        return new \WP_Error('avatar', 'Dieses Bild kann nicht verarbeitet werden.');
    }
    $resized = $editor->resize(256, 256, true);
    if (is_wp_error($resized)) {
        return new \WP_Error('avatar', 'Bild konnte nicht verkleinert werden.');
    }
    $editor->set_quality(80);
    $temp = wp_tempnam('eintrikot-avatar.jpg');
    $saved = $editor->save($temp, 'image/jpeg');
    if (is_wp_error($saved)) {
        wp_delete_file($temp);
        return new \WP_Error('avatar', 'Bild konnte nicht gespeichert werden.');
    }
    $bytes = file_get_contents($saved['path']);
    wp_delete_file($saved['path']);
    if ($temp !== $saved['path']) {
        wp_delete_file($temp);
    }
    if (!$bytes || strlen($bytes) > 200000) {
        return new \WP_Error('avatar', 'Bild konnte nicht gespeichert werden.');
    }
    // Store the resized image in protected user metadata, never as a public media URL.
    return 'data:image/jpeg;base64,' . base64_encode($bytes);
}
