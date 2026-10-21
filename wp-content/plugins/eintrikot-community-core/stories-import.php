<?php
/**
 * Stories importieren: nimmt die mit tools/stories-bundle.php vorbereiteten Pakete (stories-1.zip …) entgegen.
 *
 * Ablauf: Paket hochladen → Probelauf (nur Anzeige, es wird nichts angelegt) → Import in Etappen → Zusammenfassung.
 * Beiträge kommen als Entwurf. Ein zweiter Lauf legt nichts doppelt an (Erkennung über die alte Adresse).
 * "Import zurücknehmen" entfernt alle so angelegten Stories samt Bildern wieder.
 */
namespace Eintrikot\Community;
if (!defined('ABSPATH')) {
    exit();
}

const STORIES_BATCH = 4;
const STORIES_MAX_ZIP_ENTRIES = 600;
const STORIES_MAX_FILE_BYTES = 8 * 1024 * 1024;
const STORIES_MAX_TOTAL_BYTES = 200 * 1024 * 1024;

add_action('admin_menu', function () {
    add_submenu_page(
        'eintrikot-community-setup',
        'Stories importieren',
        'Stories importieren',
        'manage_options',
        'eintrikot-stories-import',
        __NAMESPACE__ . '\stories_import_page'
    );
});

function stories_import_base_dir() {
    return trailingslashit(wp_upload_dir()['basedir']) . 'eintrikot-stories-import';
}

/** The folder is closed for visitors: no listing and no direct access. */
function stories_import_prepare_base() {
    $base = stories_import_base_dir();
    wp_mkdir_p($base);
    foreach (
        [
            'index.php' => "<?php\n// Silence is golden.\n",
            '.htaccess' => "Require all denied\nDeny from all\n"
        ]
        as $name => $content
    ) {
        if (!is_file($base . '/' . $name)) {
            file_put_contents($base . '/' . $name, $content); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- tiny guard files in our own folder
        }
    }
    return $base;
}

function stories_import_remove_dir($dir) {
    $base = realpath(stories_import_base_dir());
    $real = realpath($dir);
    if (!$base || !$real || !str_starts_with($real, $base . DIRECTORY_SEPARATOR) || $real === $base) {
        return;
    }
    $items = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($real, \FilesystemIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : wp_delete_file($item->getPathname()); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- own temporary folder
    }
    rmdir($real); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- own temporary folder
}

function stories_import_state() {
    $state = get_user_meta(get_current_user_id(), 'eintrikot_stories_import', true);
    return is_array($state) ? $state : [];
}
function stories_import_save_state($state) {
    if ($state) {
        update_user_meta(get_current_user_id(), 'eintrikot_stories_import', $state);
    } else {
        delete_user_meta(get_current_user_id(), 'eintrikot_stories_import');
    }
}

/**
 * Unpacks a package into a fresh folder. Only stories.json and pictures under media/ are accepted.
 * Returns [state, error message].
 */
function stories_import_stage($zip_file) {
    if (!class_exists('\ZipArchive')) {
        return [null, 'Auf diesem Server fehlt die ZIP-Unterstützung (ZipArchive).'];
    }
    $zip = new \ZipArchive();
    if ($zip->open($zip_file) !== true) {
        return [null, 'Die Datei ist kein lesbares ZIP-Paket.'];
    }
    if ($zip->numFiles < 1 || $zip->numFiles > STORIES_MAX_ZIP_ENTRIES) {
        $zip->close();
        return [null, 'Das Paket hat eine unerwartete Zahl von Dateien.'];
    }
    $total = 0;
    $entries = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        $name = (string) $stat['name'];
        if (str_ends_with($name, '/')) {
            continue;
        }
        $ok =
            $name === 'stories.json' ||
            preg_match('#^media/[A-Za-z0-9._-]+/[A-Za-z0-9._-]+\.(jpe?g|png)$#i', $name);
        if (!$ok || str_contains($name, '..') || $stat['size'] > STORIES_MAX_FILE_BYTES) {
            $zip->close();
            return [null, 'Das Paket enthält eine unerlaubte oder zu große Datei: ' . $name];
        }
        $total += $stat['size'];
        $entries[] = $i;
    }
    if ($total > STORIES_MAX_TOTAL_BYTES) {
        $zip->close();
        return [null, 'Das Paket ist entpackt zu groß.'];
    }
    $token = wp_generate_password(24, false);
    $dir = stories_import_prepare_base() . '/' . $token;
    foreach ($entries as $i) {
        $name = $zip->getNameIndex($i);
        $target = $dir . '/' . $name;
        wp_mkdir_p(dirname($target));
        $in = $zip->getStream($name);
        $out = fopen($target, 'wb'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- unpacking into our own temporary folder
        if (!$in || !$out) {
            $zip->close();
            stories_import_remove_dir($dir);
            return [null, 'Das Paket lässt sich nicht entpacken.'];
        }
        stream_copy_to_stream($in, $out);
        fclose($in); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- stream of the archive
        fclose($out); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- own temporary file
        if ($name !== 'stories.json' && !@getimagesize($target)) {
            $zip->close();
            stories_import_remove_dir($dir);
            return [null, 'Eine Bilddatei ist beschädigt: ' . $name];
        }
    }
    $zip->close();
    $json = is_file($dir . '/stories.json')
        ? json_decode((string) file_get_contents($dir . '/stories.json'), true)
        : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file in our own folder
    $posts = stories_import_validate($json);
    if (is_string($posts)) {
        stories_import_remove_dir($dir);
        return [null, $posts];
    }
    return [
        [
            'token' => $token,
            'done' => 0,
            'created' => 0,
            'skipped' => 0,
            'failed' => 0,
            'count' => count($posts),
            'started' => time()
        ],
        ''
    ];
}

/** Checks stories.json. Returns the list of posts, or a message. */
function stories_import_validate($json) {
    if (
        !is_array($json) ||
        ($json['schema'] ?? 0) !== 1 ||
        !is_array($json['posts'] ?? null) ||
        !$json['posts']
    ) {
        return 'stories.json fehlt oder hat ein unbekanntes Format.';
    }
    $known = story_categories();
    foreach ($json['posts'] as $post) {
        foreach (['title', 'slug', 'date', 'rubric', 'content', 'source_url'] as $key) {
            if (!is_string($post[$key] ?? null) || $post[$key] === '') {
                return 'In stories.json fehlt eine Angabe: ' . $key;
            }
        }
        if (
            !isset($known[$post['rubric']]) ||
            !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $post['date'])
        ) {
            return 'Ungültige Rubrik oder ungültiges Datum bei: ' . $post['title'];
        }
    }
    return $json['posts'];
}

function stories_import_posts($state) {
    $file = stories_import_base_dir() . '/' . $state['token'] . '/stories.json';
    $json = is_file($file) ? json_decode((string) file_get_contents($file), true) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file in our own folder
    $posts = stories_import_validate($json);
    return is_array($posts) ? $posts : [];
}

function stories_import_existing($source_url) {
    $found = get_posts([
        'post_type' => 'post',
        'post_status' => 'any',
        'meta_key' => '_et_story_source',
        'meta_value' => $source_url,
        'fields' => 'ids',
        'numberposts' => 1
    ]);
    return $found ? (int) $found[0] : 0;
}

function stories_import_term($slug) {
    $term = get_term_by('slug', $slug, 'category');
    if ($term) {
        return (int) $term->term_id;
    }
    $made = wp_insert_term(story_categories()[$slug][0], 'category', ['slug' => $slug]);
    return is_wp_error($made) ? 0 : (int) $made['term_id'];
}

/** One picture into the media library; the same file is not added twice. Returns [id, url] or null. */
function stories_import_attachment($file, $alt) {
    $hash = sha1_file($file);
    $existing = get_posts([
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'meta_key' => '_et_story_file',
        'meta_value' => $hash,
        'fields' => 'ids',
        'numberposts' => 1
    ]);
    if ($existing) {
        return [(int) $existing[0], (string) wp_get_attachment_url($existing[0])];
    }
    $upload = wp_upload_dir();
    $name = wp_unique_filename($upload['path'], sanitize_file_name(basename($file)));
    $type = wp_check_filetype($name);
    if (
        !in_array($type['type'], ['image/jpeg', 'image/png'], true) ||
        !wp_mkdir_p($upload['path']) ||
        !copy($file, $upload['path'] . '/' . $name)
    ) {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- from our own temporary folder into the uploads
        return null;
    }
    $dest = $upload['path'] . '/' . $name;
    $id = wp_insert_attachment(
        [
            'post_mime_type' => $type['type'],
            'post_title' => pathinfo($name, PATHINFO_FILENAME),
            'post_status' => 'inherit',
            'guid' => $upload['url'] . '/' . $name
        ],
        $dest
    );
    if (!$id) {
        return null;
    }
    require_once ABSPATH . 'wp-admin/includes/image.php';
    wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $dest));
    update_post_meta($id, '_wp_attachment_image_alt', sanitize_text_field($alt));
    update_post_meta($id, '_et_story_file', $hash);
    update_post_meta($id, '_et_story_import', '1');
    return [(int) $id, (string) wp_get_attachment_url($id)];
}

/** Creates one story as a draft. Returns 'created', 'skipped' or 'failed'. */
function stories_import_one($state, $post) {
    if (stories_import_existing($post['source_url'])) {
        return 'skipped';
    }
    $dir = stories_import_base_dir() . '/' . $state['token'];
    $content = $post['content'];
    $ids = [];
    foreach ((array) ($post['images'] ?? []) as $image) {
        $path = (string) ($image['path'] ?? '');
        $file = $dir . '/' . $path;
        if (!preg_match('#^media/[A-Za-z0-9._-]+/[A-Za-z0-9._-]+$#', $path) || !is_file($file)) {
            continue;
        }
        $attachment = stories_import_attachment($file, (string) ($image['alt'] ?? $post['title']));
        if ($attachment) {
            $ids[$path] = $attachment[0];
            $content = str_replace(
                ['{{id:' . $path . '}}', '{{url:' . $path . '}}'],
                [(string) $attachment[0], esc_url($attachment[1])],
                $content
            );
        }
    }
    // Addresses of our own pictures stay relative, so they survive the move to another domain.
    $content = relative_site_links(
        $content,
        (string) wp_parse_url(home_url(), PHP_URL_HOST) .
            (wp_parse_url(home_url(), PHP_URL_PORT) ? ':' . wp_parse_url(home_url(), PHP_URL_PORT) : '')
    );
    // Pictures that could not be added leave no broken block behind.
    $content = preg_replace('#<!-- wp:image \{"id":\{\{id:[^}]*\}\}.*?<!-- /wp:image -->\s*#s', '', $content);
    $term = stories_import_term($post['rubric']);
    $id = wp_insert_post(
        [
            'post_type' => 'post',
            'post_status' => 'draft',
            'post_title' => wp_strip_all_tags($post['title']),
            'post_name' => sanitize_title($post['slug']),
            'post_content' => wp_kses_post($content),
            'post_excerpt' => sanitize_textarea_field((string) ($post['excerpt'] ?? '')),
            'post_date' => $post['date'],
            'post_date_gmt' => get_gmt_from_date($post['date']),
            'post_category' => $term ? [$term] : [],
            'meta_input' => [
                '_et_story_source' => esc_url_raw($post['source_url']),
                '_et_story_import' => '1'
            ]
        ],
        true
    );
    if (is_wp_error($id)) {
        return 'failed';
    }
    foreach ($ids as $attachment_id) {
        wp_update_post(['ID' => $attachment_id, 'post_parent' => $id]);
    }
    $featured = (string) ($post['featured'] ?? '');
    if ($featured !== '' && isset($ids[$featured])) {
        set_post_thumbnail($id, $ids[$featured]);
    }
    return 'created';
}

/** Removes everything the import created: stories (any status) and their pictures. Returns [stories, pictures]. */
function stories_import_rollback() {
    $stories = get_posts([
        'post_type' => 'post',
        'post_status' => 'any',
        'meta_key' => '_et_story_import',
        'meta_value' => '1',
        'fields' => 'ids',
        'numberposts' => -1
    ]);
    foreach ($stories as $id) {
        wp_delete_post($id, true);
    }
    $pictures = get_posts([
        'post_type' => 'attachment',
        'post_status' => 'any',
        'meta_key' => '_et_story_import',
        'meta_value' => '1',
        'fields' => 'ids',
        'numberposts' => -1
    ]);
    foreach ($pictures as $id) {
        wp_delete_attachment($id, true);
    }
    return [count($stories), count($pictures)];
}

function stories_import_guard($action) {
    if (!current_user_can('manage_options')) {
        wp_die('Keine Berechtigung.', '', ['response' => 403]);
    }
    check_admin_referer($action);
}
function stories_import_url($args = []) {
    return add_query_arg(array_merge(['page' => 'eintrikot-stories-import'], $args), admin_url('admin.php'));
}

add_action('admin_post_et_stories_stage', function () {
    stories_import_guard('et_stories_stage');
    $old = stories_import_state();
    if (!empty($old['token'])) {
        stories_import_remove_dir(stories_import_base_dir() . '/' . $old['token']);
    }
    // phpcs:disable WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification.Missing -- nonce checked by stories_import_guard(); only the temporary path is read and checked right below
    $tmp =
        isset($_FILES['stories_zip']['tmp_name']) && is_string($_FILES['stories_zip']['tmp_name'])
            ? $_FILES['stories_zip']['tmp_name']
            : '';
    // phpcs:enable WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification.Missing
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        stories_import_save_state([]);
        wp_safe_redirect(
            stories_import_url([
                'fehler' => rawurlencode(
                    'Es wurde keine Datei hochgeladen. Eventuell ist sie größer als das Upload-Limit des Servers.'
                )
            ])
        );
        exit();
    }
    [$state, $error] = stories_import_stage($tmp);
    stories_import_save_state($state ?: []);
    wp_safe_redirect(
        $state
            ? stories_import_url(['step' => 'plan'])
            : stories_import_url(['fehler' => rawurlencode($error)])
    );
    exit();
});
add_action('admin_post_et_stories_cancel', function () {
    stories_import_guard('et_stories_cancel');
    $state = stories_import_state();
    if (!empty($state['token'])) {
        stories_import_remove_dir(stories_import_base_dir() . '/' . $state['token']);
    }
    stories_import_save_state([]);
    wp_safe_redirect(stories_import_url());
    exit();
});
add_action('admin_post_et_stories_rollback', function () {
    stories_import_guard('et_stories_rollback');
    [$stories, $pictures] = stories_import_rollback();
    wp_safe_redirect(stories_import_url(['zurueck' => $stories . '-' . $pictures]));
    exit();
});

function stories_import_form($action, $label, $confirm = '', $class = 'button') {
    return '<form method="post" action="' .
        esc_url(admin_url('admin-post.php')) .
        '" style="display:inline-block;margin-right:8px"' .
        ($confirm !== '' ? ' onsubmit="return confirm(\'' . esc_js($confirm) . '\')"' : '') .
        '>' .
        '<input type="hidden" name="action" value="' .
        esc_attr($action) .
        '">' .
        wp_nonce_field($action, '_wpnonce', true, false) . // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress core builds the hidden nonce field
        '<button type="submit" class="' .
        esc_attr($class) .
        '">' .
        esc_html($label) .
        '</button></form>';
}
function stories_import_kses() {
    return [
        'form' => ['method' => true, 'action' => true, 'style' => true, 'onsubmit' => true],
        'input' => ['type' => true, 'name' => true, 'value' => true],
        'button' => ['type' => true, 'class' => true]
    ];
}

function stories_import_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    $state = stories_import_state();
    $step = isset($_GET['step']) && is_string($_GET['step']) ? sanitize_key(wp_unslash($_GET['step'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only picks which page to show
    echo '<div class="wrap"><h1>Stories importieren</h1>';
    $error = directory_param('fehler');
    if ($error !== '') {
        echo '<div class="notice notice-error"><p>' . esc_html(rawurldecode($error)) . '</p></div>';
    }
    if (directory_param('zurueck') !== '') {
        [$s, $p] = array_map('intval', array_pad(explode('-', directory_param('zurueck')), 2, 0));
        echo '<div class="notice notice-success"><p>' .
            esc_html("Import zurückgenommen: $s Stories und $p Bilder entfernt.") .
            '</p></div>';
    }
    $imported = count(
        get_posts([
            'post_type' => 'post',
            'post_status' => 'any',
            'meta_key' => '_et_story_import',
            'meta_value' => '1',
            'fields' => 'ids',
            'numberposts' => -1
        ])
    );

    if ($step === 'run' && !empty($state['token'])) {
        stories_import_run_page($state);
        echo '</div>';
        return;
    }
    if ($step === 'plan' && !empty($state['token'])) {
        $posts = stories_import_posts($state);
        echo '<h2>Probelauf</h2><p>Es wird noch nichts angelegt. Alle Beiträge kommen als <strong>Entwurf</strong>; schon vorhandene werden übersprungen.</p><table class="widefat striped"><thead><tr><th>Datum</th><th>Rubrik</th><th>Titel</th><th>Bilder</th><th>Status</th></tr></thead><tbody>';
        $new = 0;
        foreach ($posts as $post) {
            $exists = stories_import_existing($post['source_url']);
            $new += $exists ? 0 : 1;
            echo '<tr><td>' .
                esc_html(substr($post['date'], 0, 10)) .
                '</td><td>' .
                esc_html(story_categories()[$post['rubric']][0]) .
                '</td><td>' .
                esc_html($post['title']) .
                '</td><td>' .
                (int) count((array) ($post['images'] ?? [])) .
                '</td><td>' .
                ($exists ? 'schon vorhanden' : 'neu') .
                '</td></tr>';
        }
        echo '</tbody></table><p><strong>' .
            (int) $new .
            ' neu</strong>, ' .
            (int) (count($posts) - $new) .
            ' schon vorhanden.</p><p>Vorher eine Datenbanksicherung im STRATO-Kundenbereich machen.</p>';
        echo '<p><a class="button button-primary" href="' .
            esc_url(wp_nonce_url(stories_import_url(['step' => 'run']), 'et_stories_run')) .
            '">Als Entwürfe importieren</a> ';
        echo wp_kses(
            stories_import_form('et_stories_cancel', 'Abbrechen und Dateien löschen'),
            stories_import_kses()
        ) . '</p></div>';
        return;
    }
    echo '<p>Paket aus <code>tools/stories-bundle.php</code> (stories-1.zip, stories-2.zip …) hochladen. Zuerst gibt es einen Probelauf. Danach werden die Beiträge als <strong>Entwürfe</strong> mit Bildern angelegt. Mehrere Pakete nacheinander sind möglich, nichts wird doppelt angelegt.</p>';
    echo '<form method="post" enctype="multipart/form-data" action="' .
        esc_url(admin_url('admin-post.php')) .
        '"><input type="hidden" name="action" value="et_stories_stage">' .
        wp_nonce_field('et_stories_stage', '_wpnonce', true, false) . // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress core builds the hidden nonce field
        '<p><input type="file" name="stories_zip" accept=".zip,application/zip" required></p><p><button type="submit" class="button button-primary">Paket prüfen (Probelauf)</button></p></form>';
    echo '<h2>Stand</h2><p>Importierte Stories auf dieser Seite: <strong>' .
        (int) $imported .
        '</strong>.</p>';
    if ($imported) {
        echo '<p>Die Entwürfe findest du unter Beiträge. Zum Veröffentlichen: Beiträge → Mehrfachaktionen → Bearbeiten → Status „Veröffentlicht“.</p>';
        echo wp_kses(
            stories_import_form(
                'et_stories_rollback',
                'Import zurücknehmen',
                'Alle importierten Stories und ihre Bilder jetzt löschen? Das lässt sich nicht rückgängig machen.',
                'button button-link-delete'
            ),
            stories_import_kses()
        );
    }
    echo '</div>';
}

/** One batch per request; the page continues by itself until all posts are done. */
function stories_import_run_page($state) {
    check_admin_referer('et_stories_run');
    $posts = stories_import_posts($state);
    $total = count($posts);
    $from = (int) $state['done'];
    foreach (array_slice($posts, $from, STORIES_BATCH) as $post) {
        $result = stories_import_one($state, $post);
        $state[$result === 'created' ? 'created' : ($result === 'skipped' ? 'skipped' : 'failed')]++;
        $state['done']++;
        stories_import_save_state($state);
    }
    if ($state['done'] >= $total) {
        stories_import_remove_dir(stories_import_base_dir() . '/' . $state['token']);
        stories_import_save_state([]);
        echo '<div class="notice notice-success"><p>' .
            esc_html(
                sprintf(
                    'Fertig: %d angelegt, %d schon vorhanden, %d fehlgeschlagen. Alle als Entwurf.',
                    $state['created'],
                    $state['skipped'],
                    $state['failed']
                )
            ) .
            '</p></div>';
        echo '<p><a class="button button-primary" href="' .
            esc_url(admin_url('edit.php?post_status=draft')) .
            '">Entwürfe ansehen</a> <a class="button" href="' .
            esc_url(stories_import_url()) .
            '">Zurück</a></p>';
        return;
    }
    $next = wp_nonce_url(stories_import_url(['step' => 'run']), 'et_stories_run');
    echo '<p>' .
        esc_html(sprintf('Importiere … %d von %d Beiträgen.', $state['done'], $total)) .
        '</p><p><a href="' .
        esc_url($next) .
        '">Weiter</a> (geht von selbst weiter)</p>';
    echo '<script>setTimeout(function(){window.location.href=' .
        wp_json_encode(html_entity_decode($next)) .
        ';},600);</script>';
}
