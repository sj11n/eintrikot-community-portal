<?php
namespace Eintrikot\Community;
if (!defined('ABSPATH')) {
    exit();
}
function profile_groups() {
    return [
        'Über dich' => [
            'city' => 'Wohnort',
            'region' => 'Region / Bundesland',
            'club' => 'Aktueller Verein / Vereins-Team'
        ],
        'Hockey-Lebenslauf' => [
            'team' => 'Team',
            'age_class' => 'Altersklasse',
            'phase' => 'Trikotphase',
            'hockey' => 'Ein besonderer Moment oder eine Geschichte',
            'caps' => 'Länderspiele gesamt',
            'youth_caps' => 'Länderspiele nach Altersklasse'
        ],
        'Abseits des Platzes' => ['hobbies' => 'Hobbys', 'other_sport' => 'Sport neben Hockey'],
        'Beruf & Ausbildung' => [
            'job' => 'Beruf / Position',
            'employer' => 'Unternehmen / Organisation',
            'industry' => 'Branche',
            'employment' => 'Beruflicher Status',
            'university' => 'Hochschule / Ausbildungsstätte',
            'subject' => 'Studiengang / Ausbildung',
            'degree' => 'Abschluss',
            'education_period' => 'Zeitraum der Ausbildung'
        ],
        'Interessen & Mitmachen' => [
            'mentoring' => 'Mentoring: anbieten oder suchen?',
            'support' => 'Events, Sponsoring, Karrieretipps, Praktika oder Jobs',
            'contribution' => 'So möchte ich mich bei EINTRIKOT einbringen'
        ]
    ];
}
function profile_fields() {
    return array_merge(...array_values(profile_groups()));
}
function long_profile_field($key) {
    return in_array($key, ['hockey', 'support', 'contribution'], true);
}
function profile_revision($id) {
    return hash(
        'sha256',
        wp_json_encode(profile_data($id)) .
            (get_user_by('id', $id)->display_name ?? '') .
            get_user_meta($id, 'eintrikot_avatar', true)
    );
}
/** Stable keys for the profile sections; used for the per-section visibility switch. */
function profile_group_slugs() {
    return [
        'Über dich' => 'about',
        'Hockey-Lebenslauf' => 'hockey',
        'Abseits des Platzes' => 'leisure',
        'Beruf & Ausbildung' => 'career',
        'Interessen & Mitmachen' => 'interests'
    ];
}

/** Keys whose visibility a section switch controls (the DHB-Vita belongs to "Hockey"). */
function section_visibility_keys($group) {
    $keys = array_keys(profile_groups()[$group] ?? []);
    if ($group === 'Hockey-Lebenslauf') {
        $keys[] = 'stations';
    }
    return $keys;
}

/** 'all' when every field of the section is shared with members, 'none', or 'mixed' (older profiles). */
function section_visibility_state($data, $group) {
    $shared = 0;
    $keys = section_visibility_keys($group);
    foreach ($keys as $key) {
        if (($data['visibility'][$key] ?? 'private') === 'members') {
            $shared++;
        }
    }
    return $shared === 0 ? 'none' : ($shared === count($keys) ? 'all' : 'mixed');
}

/** One switch per section instead of a selector per field. Off = only the club administration sees it. */
function section_visibility_switch($group, $data) {
    $slug = profile_group_slugs()[$group] ?? '';
    $state = section_visibility_state($data, $group);
    return '<div class="visibility-switch-row"><label class="visibility-switch"><input type="checkbox" role="switch" name="section_visibility[' .
        esc_attr($slug) .
        ']" value="members"' .
        ($state === 'all' ? ' checked' : '') .
        '><span class="switch-track" aria-hidden="true"></span><span class="switch-label">Für Mitglieder sichtbar</span></label>' .
        ($state === 'mixed'
            ? '<input type="hidden" class="visibility-keep" name="section_visibility_keep[' .
                esc_attr($slug) .
                ']" value="1"><small class="visibility-note">Bisher teilweise geteilt. Das bleibt so, bis du den Schalter umlegst.</small>'
            : '') .
        '</div>';
}

/** Field-level errors of the last failed save, keyed by field name. */
function profile_field_errors($set = null) {
    static $errors = [];
    if (is_array($set)) {
        $errors = $set;
    }
    return $errors;
}
function field_error_attrs($key) {
    return isset(profile_field_errors()[$key])
        ? ' aria-invalid="true" aria-describedby="et-' . esc_attr($key) . '-error"'
        : '';
}
function field_error_text($key) {
    $errors = profile_field_errors();
    return isset($errors[$key])
        ? '<span class="field-error" id="et-' .
                esc_attr($key) .
                '-error">' .
                esc_html($errors[$key]) .
                '</span>'
        : '';
}

function profile_field($key, $label, $data) {
    $value = $data[$key] ?? '';
    $options = [
        'team' => ['Damen', 'Herren'],
        'phase' => ['Aktuell', 'Ehemalig'],
        'age_class' => ['U16', 'U18', 'U21', 'A-Nationalteam', 'Masters']
    ];
    echo '<div class="field ' .
        (long_profile_field($key) ? 'full' : '') .
        '"><label for="et-' .
        esc_attr($key) .
        '">' .
        esc_html($label) .
        '</label>';
    if (long_profile_field($key)) {
        echo '<textarea id="et-' .
            esc_attr($key) .
            '" name="profile[' .
            esc_attr($key) .
            ']" maxlength="4000">' .
            esc_textarea($value) .
            '</textarea>';
    } elseif (isset($options[$key])) {
        echo '<select id="et-' .
            esc_attr($key) .
            '" name="profile[' .
            esc_attr($key) .
            ']"><option value="">Keine Angabe</option>';
        if ($value !== '' && !in_array($value, $options[$key], true)) {
            $options[$key][] = $value;
        }
        foreach ($options[$key] as $option) {
            echo '<option ' . selected($value, $option, false) . '>' . esc_html($option) . '</option>';
        }
        echo '</select>';
    } else {
        echo '<input id="et-' .
            esc_attr($key) .
            '" name="profile[' .
            esc_attr($key) .
            ']" type="' .
            ($key === 'caps' ? 'number' : 'text') .
            '" ' .
            ($key === 'caps' ? 'min="0" max="999999" step="1" inputmode="numeric"' : 'maxlength="200"') .
            field_error_attrs($key) .
            ' value="' .
            esc_attr($value) .
            '">';
    }
    echo field_error_text($key) . '</div>';
}
function station_fields($index, $row) {
    $labels = [
        'role' => 'Rolle',
        'organisation' => 'Team / Organisation',
        'age_class' => 'Altersklasse',
        'from' => 'Von',
        'to' => 'Bis'
    ];
    echo '<div class="station">';
    foreach ($labels as $key => $label) {
        echo '<label class="field">' .
            esc_html($label) .
            '<input name="stations[' .
            $index .
            '][' .
            $key .
            ']" type="' .
            (in_array($key, ['from', 'to'], true) ? 'number' : 'text') .
            '" ' .
            (in_array($key, ['from', 'to'], true) ? 'min="1900" max="2100"' : 'maxlength="160"') .
            ' value="' .
            esc_attr($row[$key] ?? '') .
            '" ' .
            ($key === 'to' ? 'placeholder="laufend"' : '') .
            '></label>';
    }
    echo '<button type="button" class="text-reset remove-station">Entfernen</button></div>';
}
function render_profile($id) {
    if (!member_access() || !is_portal_user($id) || ($id !== get_current_user_id() && !manager_access())) {
        echo '<h1>Kein Zugriff</h1>';
        return;
    }
    $user = get_user_by('id', $id);
    $data = profile_data($id);
    $draft = get_transient('et_profile_form_' . get_current_user_id() . '_' . $id);
    if (is_array($draft)) {
        $data = $draft['data'];
        profile_field_errors(is_array($draft['fields'] ?? null) ? $draft['fields'] : []);
        delete_transient('et_profile_form_' . get_current_user_id() . '_' . $id);
    }
    $own = $id === get_current_user_id();
    echo '<a class="text-link service-back" href="' .
        esc_url(portal_url('member', ['member' => $id])) .
        '">← ' .
        ($own ? 'Mein Profil ansehen' : 'Profil ansehen') .
        '</a>' .
        page_head(
            $own ? 'Profil bearbeiten' : $user->display_name . ' bearbeiten',
            $own ? 'Du entscheidest, was du teilst.' : 'Änderungen werden mit Grund protokolliert.',
            '',
            $own ? 'account' : 'members'
        ) .
        '<div class="visibility-intro"><p><strong>So funktioniert die Sichtbarkeit:</strong> Name und Profilbild sehen alle Mitglieder. Jeden weiteren Abschnitt teilst du mit einem Schalter – ausgeschaltet sieht ihn nur die Vereinsverwaltung. Leere Felder erscheinen nirgends. Dein Geburtsdatum bleibt immer privat.</p><a class="text-link" href="' .
        esc_url(portal_url('member', ['member' => $id])) .
        '">So sehen dich andere →</a></div>';
    if ($draft) {
        echo '<div class="form-error" role="alert" tabindex="-1"><strong>Bitte prüfe deine Angaben.</strong><p>' .
            esc_html($draft['message']) .
            '</p><p>Deine übrigen Eingaben sind erhalten. Die betroffenen Felder sind markiert.</p></div>';
    }
    echo '<form class="profile-form" data-dirty-check method="post" enctype="multipart/form-data" action="' .
        esc_url(admin_url('admin-post.php')) .
        '">';
    wp_nonce_field('et_profile_' . $id);
    echo '<input type="hidden" name="action" value="et_profile"><input type="hidden" name="member" value="' .
        $id .
        '"><input type="hidden" name="revision" value="' .
        esc_attr(profile_revision($id)) .
        '">';
    foreach (profile_groups() as $group => $fields) {
        $extra = in_array($group, ['Beruf & Ausbildung', 'Interessen & Mitmachen'], true);
        $state = section_visibility_state($data, $group);
        echo $extra
            ? '<details class="form-section profile-extra"><summary>' .
                esc_html($group) .
                '<span class="visibility-badge">' .
                ($state === 'all' ? 'sichtbar' : ($state === 'mixed' ? 'teilweise' : 'privat')) .
                '</span></summary>' .
                section_visibility_switch($group, $data)
            : '<section class="form-section"><div class="section-head"><h2>' .
                esc_html($group) .
                '</h2>' .
                section_visibility_switch($group, $data) .
                '</div>';
        echo '<div class="form-grid">';
        if ($group === 'Über dich') {
            $has_photo =
                is_string(get_user_meta($id, 'eintrikot_avatar', true)) &&
                get_user_meta($id, 'eintrikot_avatar', true) !== '';
            echo '<div class="field avatar-field"><span class="field-label">Profilbild</span><div class="avatar-row"><button type="button" class="avatar-pick" data-avatar-pick aria-label="Profilbild auswählen und zuschneiden">' .
                member_avatar($id, $user->display_name) .
                '<span class="avatar-pick-badge" aria-hidden="true">Ändern</span></button><div class="avatar-actions"><label class="avatar-file">Bild auswählen<input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" data-avatar-input></label>' .
                ($has_photo
                    ? '<label class="check"><input type="checkbox" name="remove_avatar" value="1" data-avatar-remove ' .
                        checked(!empty($draft['remove_avatar']), true, false) .
                        '> Bild entfernen</label>'
                    : '') .
                '</div></div><small>Bild anklicken, auswählen und den Ausschnitt festlegen. JPEG, PNG oder WebP, bis 5 MB. Nur im Mitgliederbereich sichtbar.</small>' .
                field_error_text('avatar') .
                '</div><label class="field">Dein Name<input name="display_name" required maxlength="120"' .
                field_error_attrs('display_name') .
                ' value="' .
                esc_attr($draft ? $data['display_name'] ?? $user->display_name : $user->display_name) .
                '">' .
                field_error_text('display_name') .
                '</label>';
        }
        foreach ($fields as $key => $label) {
            profile_field($key, $label, $data);
        }
        if ($group === 'Über dich') {
            echo '<label class="field">Geburtsdatum<input type="date" name="birthday" min="1900-01-01" max="' .
                esc_attr(wp_date('Y-m-d')) .
                '" value="' .
                esc_attr($data['birthday'] ?? '') .
                '"' .
                field_error_attrs('birthday') .
                '>' .
                field_error_text('birthday') .
                '<small>Bleibt privat. Nur dein Alter kann im Mitgliederprofil erscheinen.</small></label><label class="check"><input type="checkbox" name="show_age" value="1" ' .
                checked(!empty($data['show_age']), true, false) .
                '> Mein Alter im Mitgliederprofil anzeigen</label>';
        }
        echo '</div>';
        if ($group === 'Hockey-Lebenslauf') {
            echo '<h3>DHB-Vita</h3><p>Deine Rollen und Stationen im Nationalteam. Bei einer laufenden Station bleibt „Bis“ leer.</p><div id="stations">';
            foreach ($data['stations'] ?? [[]] as $i => $row) {
                station_fields($i, $row);
            }
            echo '</div>' .
                field_error_text('stations') .
                '<button type="button" class="button" id="add-station">+ Station hinzufügen</button><template id="station-template">';
            station_fields('__INDEX__', []);
            echo '</template>';
            if (!empty($data['vita'])) {
                echo '<details><summary>Bisheriger Hockey-Lebenslauf</summary><p>' .
                    nl2br(esc_html($data['vita'])) .
                    '</p></details>';
            }
        }
        echo $extra ? '</details>' : '</section>';
    }
    echo '<section class="form-section"><h2>EINTRIKOT zusätzlich fördern</h2><label class="field">Möchtest du zusätzlich zum Mitgliedsbeitrag jährlich spenden?<select name="funding_interest"><option value="">Noch offen</option><option value="yes" ' .
        selected($data['funding_interest'] ?? '', 'yes', false) .
        '>Ja, ich möchte zusätzlich fördern</option><option value="no" ' .
        selected($data['funding_interest'] ?? '', 'no', false) .
        '>Derzeit nicht</option></select></label><p>Deine Auswahl ist unverbindlich. Betrag und Beginn bestätigst du in einer separaten Anfrage.</p><a class="text-link" href="' .
        esc_url(portal_url('service', ['service' => 'funding'])) .
        '">Förderanfrage vorbereiten →</a></section><section class="form-section"><h2>In Verbindung bleiben</h2><label class="check"><input type="checkbox" name="birthday_notice" value="1" ' .
        checked(!empty($data['birthday_notice']), true, false) .
        '><span>Mein Geburtstag darf mit meinem Namen in den internen Vereinsinfos erscheinen. Das Geburtsjahr wird nicht angezeigt.</span></label><label class="check"><input type="checkbox" name="newsletter" value="1" ' .
        checked($data['newsletter'] ?? true, true, false) .
        '><span>EINTRIKOT-Newsletter erhalten<br><small>Du kannst diese Einstellung jederzeit ändern.</small></span></label></section>';
    if (manager_access()) {
        $listing = $data['directory_listing'] ?? '';
        $auto = directory_listed_by_role($user)
            ? 'Automatisch: wird angezeigt'
            : 'Automatisch: wird nicht angezeigt';
        echo '<section class="form-section"><h2>Verwaltung</h2><label class="field">Im Mitgliederverzeichnis<select name="directory_listing"><option value="">' .
            esc_html($auto) .
            '</option><option value="show" ' .
            selected($listing, 'show', false) .
            '>Immer anzeigen</option><option value="hide" ' .
            selected($listing, 'hide', false) .
            '>Nicht anzeigen</option></select><small>Automatisch erscheinen alle Konten mit EINTRIKOT-Rolle. Technische Konten ohne diese Rolle, etwa ein reines Administrator-Konto, bleiben verborgen – außer du wählst „Immer anzeigen“.</small></label></section>';
    }
    if ($id !== get_current_user_id()) {
        echo '<label class="field">Grund der Änderung<textarea name="reason" required minlength="5" maxlength="500"' .
            field_error_attrs('reason') .
            '>' .
            esc_textarea($draft['reason'] ?? '') .
            '</textarea>' .
            field_error_text('reason') .
            '</label>';
    }
    if (!empty($draft['conflict'])) {
        echo '<section class="form-section form-error"><h2>Zwischenzeitliche Änderung</h2><p>Oben steht dein Entwurf. Bitte vergleiche ihn mit dem inzwischen gespeicherten Stand.</p><details open><summary>Aktuell gespeicherte Angaben</summary><dl>';
        foreach (profile_data($id) as $key => $value) {
            if ($key === 'visibility') {
                continue;
            }
            echo '<dt>' .
                esc_html(profile_fields()[$key] ?? ($key === 'stations' ? 'DHB-Vita' : $key)) .
                '</dt><dd>' .
                esc_html(
                    is_array($value) ? wp_json_encode($value, JSON_UNESCAPED_UNICODE) : ((string) $value)
                ) .
                '</dd>';
        }
        echo '</dl></details><label class="check"><input type="checkbox" name="resolve_conflict" value="1" required> Ich habe den aktuellen Stand geprüft und möchte meinen Entwurf speichern.</label></section>';
    }
    echo '<div class="save-bar"><button class="button solid" type="submit">Änderungen speichern</button><span class="save-state" data-save-state role="status" aria-live="polite"></span></div></form>';
    echo avatar_cropper_markup();
}
function validate_stations($rows) {
    if (!is_array($rows) || count($rows) > 30) {
        return new \WP_Error('stations', 'Bitte maximal 30 DHB-Stationen angeben.');
    }
    $result = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            return new \WP_Error('stations', 'Eine DHB-Station ist ungültig.');
        }
        $clean = [];
        foreach (['role', 'organisation', 'age_class', 'from', 'to'] as $key) {
            $value = $row[$key] ?? '';
            if (!is_scalar($value) || mb_strlen((string) $value) > 160) {
                return new \WP_Error('stations', 'Bitte die Angaben der DHB-Stationen prüfen.');
            }
            $clean[$key] = sanitize_text_field(trim((string) $value));
        }
        if (!array_filter($clean)) {
            continue;
        }
        if (!$clean['role'] || !$clean['organisation']) {
            return new \WP_Error(
                'stations',
                'Bitte bei jeder DHB-Station Rolle und Team / Organisation angeben.'
            );
        }
        foreach (['from', 'to'] as $year) {
            if ($clean[$year] !== '' && !preg_match('/^(19|20)\d{2}$|^2100$/D', $clean[$year])) {
                return new \WP_Error('stations', 'Die Jahreszahlen müssen zwischen 1900 und 2100 liegen.');
            }
        }
        if ($clean['to'] !== '' && ($clean['from'] === '' || (int) $clean['from'] > (int) $clean['to'])) {
            return new \WP_Error('stations', 'Das Ende einer Station darf nicht vor ihrem Beginn liegen.');
        }
        $result[] = $clean;
    }
    return $result;
}
function profile_error($id, $data, $message, $conflict = false, $fields = []) {
    $reason = $_POST['reason'] ?? '';
    $reason = is_scalar($reason) ? sanitize_textarea_field(wp_unslash((string) $reason)) : '';
    set_transient(
        'et_profile_form_' . get_current_user_id() . '_' . $id,
        [
            'data' => $data,
            'message' =>
                $message .
                (!empty($_FILES['avatar']['name'])
                    ? ' Das ausgewählte Bild bitte noch einmal auswählen.'
                    : ''),
            'reason' => $reason,
            'remove_avatar' => !empty($_POST['remove_avatar']),
            'conflict' => $conflict,
            'fields' => $fields
        ],
        300
    );
    if ($conflict) {
        set_transient('et_profile_conflict_' . get_current_user_id() . '_' . $id, true, 600);
    }
    wp_safe_redirect(
        portal_url($id === get_current_user_id() ? 'profile' : 'edit-member', ['member' => $id])
    );
    exit();
}
function profile_form_text($value, $max = 4000) {
    return is_scalar($value) ? mb_substr(sanitize_textarea_field(wp_unslash((string) $value)), 0, $max) : '';
}
function persist_profile($id, $data, $avatar, $reason, $revision) {
    global $wpdb;
    if ($wpdb->query('START TRANSACTION') === false) {
        return new \WP_Error('storage', 'Speichern ist gerade nicht möglich. Bitte erneut versuchen.');
    }
    // Serialize concurrent writes on the member row. SQLite serializes write transactions itself.
    if (!defined('DB_ENGINE') || DB_ENGINE !== 'sqlite') {
        $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->users} WHERE ID=%d FOR UPDATE", $id));
    }
    clean_user_cache($id);
    wp_cache_delete($id, 'user_meta');
    if (!hash_equals(profile_revision($id), $revision)) {
        $wpdb->query('ROLLBACK');
        return new \WP_Error(
            'conflict',
            'Das Profil wurde inzwischen geändert. Bitte vergleiche deinen Entwurf mit dem aktuellen Stand.'
        );
    }
    $old = profile_data($id);
    $old_name = get_user_by('id', $id)->display_name;
    $old_avatar = get_user_meta($id, 'eintrikot_avatar', true);
    $ok = true;
    if ($old_name !== $data['display_name']) {
        $ok = !is_wp_error(wp_update_user(wp_slash(['ID' => $id, 'display_name' => $data['display_name']])));
    }
    if ($ok && $old !== $data) {
        $ok = update_user_meta($id, 'eintrikot_profile', wp_slash($data)) !== false;
    }
    if ($ok && $avatar !== null && $avatar !== $old_avatar) {
        $ok =
            ($avatar === ''
                ? delete_user_meta($id, 'eintrikot_avatar')
                : update_user_meta($id, 'eintrikot_avatar', $avatar)) !== false;
    }
    if ($ok) {
        foreach ($data as $key => $value) {
            if (
                ($old[$key] ?? null) !== $value &&
                log_change($id, $key, $old[$key] ?? null, $value, $reason) === false
            ) {
                $ok = false;
                break;
            }
        }
    }
    if (
        $ok &&
        $old_name !== $data['display_name'] &&
        ($old['display_name'] ?? null) === $data['display_name']
    ) {
        $ok = log_change($id, 'display_name', $old_name, $data['display_name'], $reason) !== false;
    }
    if ($ok && $avatar !== null && $avatar !== $old_avatar) {
        $ok =
            log_change(
                $id,
                'avatar',
                $old_avatar !== '' ? 'vorhanden' : 'kein Bild',
                $avatar !== '' ? 'aktualisiert' : 'entfernt',
                $reason
            ) !== false;
    }
    if (!$ok || $wpdb->query('COMMIT') === false) {
        $wpdb->query('ROLLBACK');
        clean_user_cache($id);
        wp_cache_delete($id, 'user_meta');
        return new \WP_Error(
            'storage',
            'Die Änderung konnte nicht vollständig gespeichert werden. Bitte erneut versuchen.'
        );
    }
    clean_user_cache($id);
    wp_cache_delete($id, 'user_meta');
    return true;
}
add_action('admin_post_et_profile', function () {
    $id = isset($_POST['member']) && is_scalar($_POST['member']) ? absint($_POST['member']) : 0;
    if (!member_access() || !is_portal_user($id) || ($id !== get_current_user_id() && !manager_access())) {
        wp_die('Keine Berechtigung.', '', ['response' => 403]);
    }
    check_admin_referer('et_profile_' . $id);
    $data = profile_data($id);
    $raw = $_POST['profile'] ?? [];
    $sections = $_POST['section_visibility'] ?? [];
    $keep = $_POST['section_visibility_keep'] ?? [];
    if (!is_array($raw) || !is_array($sections) || !is_array($keep)) {
        wp_die('Ungültige Eingabe.', '', ['response' => 400]);
    }
    $errors = [];
    foreach (profile_fields() as $key => $label) {
        $value = $raw[$key] ?? '';
        $max = long_profile_field($key) ? 4000 : 200;
        if (!is_scalar($value) || mb_strlen(is_scalar($value) ? wp_unslash((string) $value) : '') > $max) {
            $errors[$key] = $label . ' bitte prüfen.';
        }
        $data[$key] = profile_form_text($value, $max);
    }
    // One switch per section sets the visibility of all its fields (and of the DHB-Vita).
    // Partly shared older sections stay as they are until the member flips the switch.
    foreach (profile_group_slugs() as $group => $slug) {
        if (($keep[$slug] ?? '') === '1' && section_visibility_state($data, $group) === 'mixed') {
            continue;
        }
        $shared = ($sections[$slug] ?? '') === 'members';
        foreach (section_visibility_keys($group) as $key) {
            $data['visibility'][$key] = $shared ? 'members' : 'private';
        }
    }
    foreach (['display_name' => 120, 'birthday' => 10] as $key => $max) {
        $v = $_POST[$key] ?? '';
        if (!is_scalar($v) || mb_strlen(is_scalar($v) ? wp_unslash((string) $v) : '') > $max) {
            $errors[$key] =
                $key === 'birthday' ? 'Bitte das Geburtsdatum prüfen.' : 'Bitte deinen Namen prüfen.';
        }
        $data[$key] = profile_form_text($v, $max);
    }
    foreach (['show_age', 'birthday_notice', 'newsletter'] as $key) {
        $data[$key] = isset($_POST[$key]);
    }
    // Only the administration decides whether an account is listed in the directory.
    if (manager_access() && isset($_POST['directory_listing'])) {
        $data['directory_listing'] = in_array($_POST['directory_listing'], ['show', 'hide'], true)
            ? $_POST['directory_listing']
            : '';
    }
    $data['funding_interest'] = in_array($_POST['funding_interest'] ?? '', ['yes', 'no'], true)
        ? $_POST['funding_interest']
        : '';
    $submitted = $_POST['stations'] ?? [];
    $data['stations'] = [];
    if (is_array($submitted)) {
        foreach (array_slice($submitted, 0, 30) as $row) {
            $safe = [];
            foreach (['role', 'organisation', 'age_class', 'from', 'to'] as $key) {
                $safe[$key] = profile_form_text(is_array($row) ? $row[$key] ?? '' : '', 160);
            }
            $data['stations'][] = $safe;
        }
    }
    if (!$data['display_name']) {
        $errors['display_name'] = 'Bitte deinen Namen angeben.';
    }
    if ($data['caps'] !== '' && (!ctype_digit($data['caps']) || strlen($data['caps']) > 6)) {
        $errors['caps'] = 'Bitte eine ganze Zahl ab 0 eingeben.';
    }
    if ($data['birthday'] !== '') {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $data['birthday'], wp_timezone());
        if (
            !$date ||
            $date->format('Y-m-d') !== $data['birthday'] ||
            $data['birthday'] < '1900-01-01' ||
            $data['birthday'] > wp_date('Y-m-d')
        ) {
            $errors['birthday'] = 'Bitte das Geburtsdatum prüfen.';
        }
    }
    $stations = validate_stations(wp_unslash($submitted));
    if (is_wp_error($stations)) {
        $errors['stations'] = $stations->get_error_message();
    }
    $reason = profile_form_text(
        $_POST['reason'] ?? ($id === get_current_user_id() ? 'Eigene Profilpflege' : ''),
        500
    );
    if ($id !== get_current_user_id() && mb_strlen(trim($reason)) < 5) {
        $errors['reason'] = 'Bitte den Grund der Änderung angeben (mindestens fünf Zeichen).';
    }
    if ($errors) {
        profile_error($id, $data, implode(' ', array_unique($errors)), false, $errors);
    }
    $data['stations'] = $stations;
    if (
        get_transient('et_profile_conflict_' . get_current_user_id() . '_' . $id) &&
        empty($_POST['resolve_conflict'])
    ) {
        profile_error($id, $data, 'Bitte bestätige den Vergleich mit dem aktuellen Stand.', true);
    }
    $revision = profile_form_text($_POST['revision'] ?? '', 64);
    if (!hash_equals(profile_revision($id), $revision)) {
        profile_error(
            $id,
            $data,
            'Das Profil wurde inzwischen geändert. Bitte vergleiche deinen Entwurf mit dem aktuellen Stand.',
            true
        );
    }
    $avatar = prepare_avatar();
    if (is_wp_error($avatar)) {
        profile_error($id, $data, $avatar->get_error_message(), false, [
            'avatar' => $avatar->get_error_message()
        ]);
    }
    $result = persist_profile($id, $data, $avatar, $reason, $revision);
    if (is_wp_error($result)) {
        profile_error($id, $data, $result->get_error_message(), $result->get_error_code() === 'conflict');
    }
    delete_transient('et_profile_conflict_' . get_current_user_id() . '_' . $id);
    wp_safe_redirect(
        portal_url($id === get_current_user_id() ? 'profile' : 'member', ['member' => $id, 'saved' => 1])
    );
    exit();
});
add_action('wp_enqueue_scripts', function () {
    if (
        ((int) get_option('eintrikot_portal_page') > 0 &&
            is_page((int) get_option('eintrikot_portal_page'))) ||
        ((int) get_option('eintrikot_audit_page') > 0 && is_page((int) get_option('eintrikot_audit_page')))
    ) {
        wp_enqueue_script(
            'eintrikot-portal-ui',
            plugins_url('portal.js', __FILE__),
            [],
            asset_version('portal.js'),
            true
        );
    }
});
