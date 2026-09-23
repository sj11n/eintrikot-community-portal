<?php
namespace Eintrikot\Community;
if (!defined('ABSPATH')) {
    exit();
}
/** "Anna Berger" -> "AB", "Anna" -> "A". Uses the first and the last word of the name. */
function member_initials($name) {
    $words = preg_split('/[\s\-]+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);
    if (!$words) {
        return '?';
    }
    $first = mb_strtoupper(mb_substr($words[0], 0, 1));
    return count($words) > 1 ? $first . mb_strtoupper(mb_substr(end($words), 0, 1)) : $first;
}

/**
 * Background tone of the initials avatar: rosé for Damen, light blue for Herren, neutral otherwise.
 * The team only colours the avatar where the viewer may see it (shared, own profile or management),
 * so the colour never reveals a value that the member keeps private.
 */
function member_avatar_tone($id) {
    $data = profile_data($id);
    $team =
        $id === get_current_user_id() || manager_access()
            ? $data['team'] ?? ''
            : visible_value($data, 'team');
    return $team === 'Damen' ? 'rose' : ($team === 'Herren' ? 'blue' : 'neutral');
}

function member_avatar($id, $name) {
    $data = get_user_meta($id, 'eintrikot_avatar', true);
    if (is_string($data) && str_starts_with($data, 'data:image/jpeg;base64,')) {
        return '<img class="et-avatar" src="' . esc_attr($data) . '" alt="" width="64" height="64">';
    }
    return '<span class="et-avatar tone-' .
        member_avatar_tone((int) $id) .
        '" aria-hidden="true">' .
        esc_html(member_initials($name)) .
        '</span>';
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

/** Crop dialog for the profile picture. Works only with JavaScript; without it the plain file field is used. */
function avatar_cropper_markup() {
    return '<dialog class="avatar-cropper" data-avatar-cropper aria-labelledby="et-crop-title"><div class="cropper-inner"><h2 id="et-crop-title">Ausschnitt wählen</h2><p>Bild mit der Maus oder dem Finger verschieben, mit dem Regler vergrößern.</p><div class="crop-stage" tabindex="0" role="img" aria-label="Bildausschnitt. Pfeiltasten verschieben das Bild, Plus und Minus ändern die Größe." data-crop-stage><img alt="" data-crop-image draggable="false"></div><label class="crop-zoom">Größe<input type="range" min="1" max="4" step="0.01" value="1" data-crop-zoom></label><div class="crop-actions"><button type="button" class="button" data-crop-cancel>Abbrechen</button><button type="button" class="button solid" data-crop-apply>Übernehmen</button></div></div></dialog>';
}
