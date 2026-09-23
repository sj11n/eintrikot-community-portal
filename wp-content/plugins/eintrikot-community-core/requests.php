<?php
namespace Eintrikot\Community;
if (!defined('ABSPATH')) {
    exit();
}
function request_state($id) {
    $v = get_post_meta($id, 'et_workflow', true);
    return is_array($v)
        ? $v
        : ['status' => get_post_meta($id, 'et_status', true) ?: 'received', 'reply' => '', 'note' => ''];
}
function request_revision($id) {
    return hash('sha256', wp_json_encode(request_state($id)));
}
function request_labels($kind) {
    return [
        'received' => 'Eingegangen',
        'review' => 'In Prüfung',
        'adopted' => in_array($kind, ['address', 'bank', 'funding'], true)
            ? 'In MeinVerein übernommen'
            : 'Erledigt',
        'rejected' => 'Abgelehnt'
    ];
}
function request_allowed($from, $to) {
    $paths = [
        'received' => ['received', 'review'],
        'review' => ['review', 'adopted', 'rejected'],
        'adopted' => ['adopted', 'review'],
        'rejected' => ['rejected', 'review']
    ];
    return in_array($to, $paths[$from] ?? [], true);
}
function request_can_read($row) {
    return member_access() &&
        $row &&
        $row->post_type === 'et_request' &&
        $row->post_status === 'private' &&
        (manager_access() || (int) $row->post_author === get_current_user_id());
}
function render_requests($all) {
    if (!member_access() || ($all && !manager_access())) {
        return;
    }
    if ($all) {
        echo '<h1>Service-Anfragen</h1><p>Prüfen, Rückmeldung hinterlegen und die Übernahme dokumentieren.</p>';
    }
    $status = directory_param('status');
    $args = [
        'post_type' => 'et_request',
        'post_status' => 'private',
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'DESC'
    ];
    if (!$all) {
        $args['author'] = get_current_user_id();
    }
    $rows = get_posts($args);
    $rows = array_filter(
        $rows,
        fn($row) => $status === '' || (request_state($row->ID)['status'] ?? 'received') === $status
    );
    usort($rows, function ($a, $b) {
        $closed = fn($r) => in_array(request_state($r->ID)['status'] ?? '', ['adopted', 'rejected'], true)
            ? 1
            : 0;
        return $closed($a) <=> $closed($b) ?: strcmp($b->post_date, $a->post_date);
    });
    if ($all) {
        echo '<nav class="request-filters" aria-label="Anfragen filtern">';
        foreach (
            [
                '' => 'Alle',
                'received' => 'Eingegangen',
                'review' => 'In Prüfung',
                'adopted' => 'Abgeschlossen',
                'rejected' => 'Abgelehnt'
            ]
            as $key => $label
        ) {
            echo '<a class="button" ' .
                ($status === $key ? 'aria-current="page"' : '') .
                ' href="' .
                esc_url(portal_url('requests', ['status' => $key])) .
                '">' .
                esc_html($label) .
                '</a>';
        }
        echo '</nav>';
    }
    $total = count($rows);
    $pages = max(1, (int) ceil($total / 20));
    $page = min($pages, max(1, (int) directory_param('request_page')));
    if (!$rows) {
        echo '<p>Noch keine passenden Anfragen.</p>';
    }
    foreach (array_slice($rows, ($page - 1) * 20, 20) as $row) {
        $state = request_state($row->ID);
        $label = request_labels(get_post_meta($row->ID, 'et_kind', true))[$state['status']] ?? 'Eingegangen';
        $author = get_user_by('id', $row->post_author);
        echo '<a class="request-card" href="' .
            esc_url(portal_url('request', ['request' => $row->ID])) .
            '"><div><small>#' .
            esc_html($row->ID) .
            ' · ' .
            esc_html(get_the_date('d.m.Y', $row)) .
            ($all ? ' · ' . esc_html($author ? $author->display_name : 'Gelöschtes Mitglied') : '') .
            '</small><h3>' .
            esc_html($row->post_title) .
            '</h3></div><span class="request-status">' .
            esc_html($label) .
            '</span><span aria-hidden="true">→</span></a>';
    }
    if ($pages > 1) {
        echo '<nav class="pagination" aria-label="Anfragenseiten">';
        if ($page > 1) {
            echo '<a href="' .
                esc_url(
                    portal_url($all ? 'requests' : 'service', [
                        'request_page' => $page - 1,
                        'status' => $status
                    ])
                ) .
                '">← Zurück</a>';
        }
        echo '<span>Seite ' . esc_html($page . ' von ' . $pages) . '</span>';
        if ($page < $pages) {
            echo '<a href="' .
                esc_url(
                    portal_url($all ? 'requests' : 'service', [
                        'request_page' => $page + 1,
                        'status' => $status
                    ])
                ) .
                '">Weiter →</a>';
        }
        echo '</nav>';
    }
}
function render_request($id) {
    $row = get_post($id);
    if (!request_can_read($row)) {
        echo '<h1>Anfrage nicht verfügbar.</h1>';
        return;
    }
    $state = request_state($id);
    $kind = get_post_meta($id, 'et_kind', true);
    $labels = request_labels($kind);
    $manager = manager_access();
    $author = get_user_by('id', $row->post_author);
    echo '<a class="text-link service-back" href="' .
        esc_url(portal_url($manager ? 'requests' : 'service')) .
        '">← ' .
        ($manager ? 'Service-Anfragen' : 'Service') .
        '</a><h1>' .
        esc_html($row->post_title) .
        '</h1><p>#' .
        esc_html($id) .
        ' · ' .
        esc_html(get_the_date('d.m.Y', $row)) .
        ' · <strong>' .
        esc_html($labels[$state['status']] ?? 'Eingegangen') .
        '</strong></p>';
    if ($manager) {
        echo '<p>Antrag von ' . esc_html($author ? $author->display_name : 'Gelöschtes Mitglied') . '</p>';
    }
    echo '<section class="form-section"><h2>Deine Anfrage</h2><p>' .
        nl2br(esc_html($row->post_content)) .
        '</p></section>';
    if (!empty($state['reply'])) {
        echo '<section class="form-section"><h2>Rückmeldung</h2><p>' .
            nl2br(esc_html($state['reply'])) .
            '</p></section>';
    }
    if (!$manager) {
        return;
    }
    $draft = get_transient('et_request_draft_' . get_current_user_id() . '_' . $id);
    if ($draft) {
        delete_transient('et_request_draft_' . get_current_user_id() . '_' . $id);
    }
    if ($draft) {
        echo '<div class="form-error" role="alert">' . esc_html($draft['error']) . '</div>';
    }
    echo '<form class="et-form request-editor" method="post" action="' .
        esc_url(admin_url('admin-post.php')) .
        '">';
    wp_nonce_field('et_request_' . $id);
    echo '<input type="hidden" name="action" value="et_request_update"><input type="hidden" name="request" value="' .
        $id .
        '"><input type="hidden" name="revision" value="' .
        esc_attr(request_revision($id)) .
        '"><h2>Bearbeitung</h2><label>Status<select name="status">';
    foreach ($labels as $value => $label) {
        if (request_allowed($state['status'], $value)) {
            echo '<option value="' .
                esc_attr($value) .
                '" ' .
                selected($state['status'], $value, false) .
                '>' .
                esc_html($label) .
                '</option>';
        }
    }
    echo '</select></label><label>Rückmeldung für das Mitglied<textarea name="reply" maxlength="4000">' .
        esc_textarea($draft['reply'] ?? ($state['reply'] ?? '')) .
        '</textarea></label><label>Interne Notiz<textarea name="note" maxlength="4000">' .
        esc_textarea($draft['note'] ?? ($state['note'] ?? '')) .
        '</textarea><small>Nur für Vorstand und Admin sichtbar.</small></label>';
    if (in_array($kind, ['address', 'bank', 'funding'], true)) {
        echo '<label class="check"><input type="checkbox" name="transferred" value="1"> Die Änderung wurde von mir in MeinVerein übernommen.</label>';
    }
    if (in_array($state['status'], ['adopted', 'rejected'], true)) {
        echo '<p>Mit „In Prüfung“ öffnest du die Anfrage erneut. Das wird protokolliert.</p>';
    }
    if (!empty($draft['conflict'])) {
        echo '<details class="form-section" open><summary>Inzwischen gespeicherte Bearbeitung</summary><p>Status: ' .
            esc_html($labels[$state['status']]) .
            '</p><p>Rückmeldung: ' .
            nl2br(esc_html($state['reply'] ?? '')) .
            '</p><p>Interne Notiz: ' .
            nl2br(esc_html($state['note'] ?? '')) .
            '</p></details><label class="check"><input type="checkbox" name="resolve_conflict" value="1" required> Ich habe den aktuellen Bearbeitungsstand geprüft.</label>';
    }
    echo '<button class="button solid">Bearbeitung speichern</button><p><small>Die Rückmeldung ist anschließend hier im Portal sichtbar. Es wird keine E-Mail versendet.</small></p></form>';
}
function persist_request($id, $next, $revision) {
    global $wpdb;
    $row = get_post($id);
    if (!manager_access() || !request_can_read($row)) {
        return new \WP_Error('access', 'Keine Berechtigung.');
    }
    if ($wpdb->query('START TRANSACTION') === false) {
        return new \WP_Error('storage', 'Speichern derzeit nicht möglich.');
    }
    if (!defined('DB_ENGINE') || DB_ENGINE !== 'sqlite') {
        $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID=%d FOR UPDATE", $id));
    }
    wp_cache_delete($id, 'post_meta');
    $old = request_state($id);
    if (!hash_equals(request_revision($id), $revision)) {
        $wpdb->query('ROLLBACK');
        return new \WP_Error(
            'conflict',
            'Diese Anfrage wurde inzwischen bearbeitet. Bitte vergleiche deinen Entwurf mit dem aktuellen Stand.'
        );
    }
    if (!request_allowed($old['status'], $next['status'])) {
        $wpdb->query('ROLLBACK');
        return new \WP_Error('status', 'Dieser Statuswechsel ist nicht möglich.');
    }
    $ok = update_post_meta($id, 'et_workflow', wp_slash($next)) !== false;
    if ($ok) {
        $ok =
            log_change(
                (int) $row->post_author,
                'request_' . $id,
                $old,
                $next,
                'Service-Anfrage #' . $id . ' bearbeitet'
            ) !== false;
    }
    if (!$ok || $wpdb->query('COMMIT') === false) {
        $wpdb->query('ROLLBACK');
        wp_cache_delete($id, 'post_meta');
        return new \WP_Error('storage', 'Die Bearbeitung konnte nicht gespeichert werden.');
    }
    return true;
}
add_action('admin_post_et_request_update', function () {
    $id = absint(post_text('request', '', 20));
    $row = get_post($id);
    if (!manager_access() || !request_can_read($row)) {
        wp_die('Keine Berechtigung.', '', ['response' => 403]);
    }
    check_admin_referer('et_request_' . $id);
    $old = request_state($id);
    $status = post_text('status', '', 20);
    $reply = post_text('reply', '', 4000);
    $note = post_text('note', '', 4000);
    $kind = get_post_meta($id, 'et_kind', true);
    $error = '';
    if (!request_allowed($old['status'], $status)) {
        $error = 'Bitte einen gültigen Status auswählen.';
    }
    if ($status === 'rejected' && mb_strlen(trim($reply)) < 10) {
        $error = 'Bitte begründe die Ablehnung für das Mitglied.';
    }
    if (
        $status === 'adopted' &&
        $old['status'] !== 'adopted' &&
        in_array($kind, ['address', 'bank', 'funding'], true) &&
        empty($_POST['transferred'])
    ) {
        $error = 'Bitte die tatsächliche Übernahme in MeinVerein bestätigen.';
    }
    $conflict_key = 'et_request_conflict_' . get_current_user_id() . '_' . $id;
    if (get_transient($conflict_key) && empty($_POST['resolve_conflict'])) {
        $error = 'Bitte den aktuellen Bearbeitungsstand vergleichen und bestätigen.';
    }
    $next = [
        'status' => $status,
        'reply' => $reply,
        'note' => $note,
        'actor' => get_current_user_id(),
        'updated_at' => current_time('mysql', true)
    ];
    if ($status === 'adopted') {
        $next['transferred_at'] = $old['transferred_at'] ?? current_time('mysql', true);
    }
    $result = $error
        ? new \WP_Error('validation', $error)
        : persist_request($id, $next, post_text('revision', '', 64));
    if (is_wp_error($result)) {
        $conflict = $result->get_error_code() === 'conflict' || get_transient($conflict_key);
        if ($conflict) {
            set_transient($conflict_key, true, 600);
        }
        set_transient(
            'et_request_draft_' . get_current_user_id() . '_' . $id,
            [
                'error' => $result->get_error_message(),
                'reply' => $reply,
                'note' => $note,
                'conflict' => $conflict
            ],
            300
        );
        wp_safe_redirect(portal_url('request', ['request' => $id]));
        exit();
    }
    delete_transient($conflict_key);
    wp_safe_redirect(portal_url('request', ['request' => $id, 'saved' => 1]));
    exit();
});
