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
function request_status_pill($status, $kind) {
    $labels = request_labels($kind);
    $status = isset($labels[$status]) ? $status : 'received';
    return '<span class="request-status status-' .
        esc_attr($status) .
        '">' .
        esc_html($labels[$status]) .
        '</span>';
}

/** Who looks after a request, in words for the member. */
function request_owner_label($kind) {
    return $kind === 'contact' ? 'Der Vorstand' : 'Die Mitgliederverwaltung';
}

/**
 * Timeline "Eingegangen → In Prüfung → Erledigt" with the dates known for each step.
 * Older requests have no history yet; their current status date is the last update.
 */
function request_timeline($row, $state, $kind) {
    $labels = request_labels($kind);
    $dates = ['received' => $row->post_date_gmt];
    foreach (is_array($state['history'] ?? null) ? $state['history'] : [] as $entry) {
        if (is_array($entry) && isset($labels[$entry['status'] ?? ''])) {
            $dates[$entry['status']] = $entry['at'] ?? '';
        }
    }
    $current = isset($labels[$state['status'] ?? '']) ? $state['status'] : 'received';
    if (!isset($dates[$current]) && !empty($state['updated_at'])) {
        $dates[$current] = $state['updated_at'];
    }
    $final = $current === 'rejected' ? 'rejected' : 'adopted';
    $steps = ['received', 'review', $final];
    $reached = array_search($current, $steps, true);
    $html = '<ol class="request-timeline">';
    foreach ($steps as $i => $step) {
        $class = $i < $reached ? 'is-done' : ($i === $reached ? 'is-current' : 'is-open');
        if ($step === 'rejected') {
            $class .= ' is-rejected';
        }
        $date = $i <= $reached && !empty($dates[$step]) ? get_date_from_gmt($dates[$step], 'd.m.Y') : '';
        $html .=
            '<li class="' .
            esc_attr($class) .
            '"' .
            ($i === $reached ? ' aria-current="step"' : '') .
            '><span class="timeline-dot" aria-hidden="true"></span><strong>' .
            esc_html($labels[$step]) .
            '</strong>' .
            ($date !== '' ? '<small>' . esc_html($date) . '</small>' : '') .
            '</li>';
    }
    return $html . '</ol>';
}

/** Plain-language next step for the member. */
function request_next_step($state, $kind) {
    $who = request_owner_label($kind);
    switch ($state['status'] ?? 'received') {
        case 'review':
            return $who . ' prüft deine Anfrage gerade. Die Rückmeldung erscheint hier.';
        case 'adopted':
            return in_array($kind, ['address', 'bank', 'funding'], true)
                ? 'Erledigt: Die Änderung ist in MeinVerein übernommen.'
                : 'Erledigt. Danke für deine Anfrage.';
        case 'rejected':
            return 'Diese Anfrage wurde nicht übernommen. Die Begründung steht unter „Rückmeldung“. Bei Fragen erreichst du den Vorstand über den Service.';
        default:
            return 'Wir haben deine Anfrage erhalten. ' . $who . ' sieht sie sich als Nächstes an.';
    }
}

/** Funding requests: say clearly whether the amount is only requested or already binding. */
function funding_summary($id, $state) {
    $details = get_post_meta($id, 'et_details', true);
    if (!is_array($details) || !isset($details['annual_amount_cents'])) {
        return '';
    }
    $amount = number_format_i18n(
        $details['annual_amount_cents'] / 100,
        $details['annual_amount_cents'] % 100 ? 2 : 0
    );
    $start = !empty($details['effective_date'])
        ? wp_date('j. F Y', strtotime($details['effective_date'] . ' 12:00:00'))
        : '';
    $ending = (int) $details['annual_amount_cents'] === 0;
    $what = $ending
        ? 'Beenden deines freiwilligen Förderbeitrags' . ($start !== '' ? ' zum ' . $start : '')
        : $amount . ' € jährlich' . ($start !== '' ? ' ab ' . $start : '');
    $status = $state['status'] ?? 'received';
    if ($status === 'adopted') {
        $date = !empty($state['transferred_at']) ? get_date_from_gmt($state['transferred_at'], 'd.m.Y') : '';
        return '<section class="funding-state is-binding"><p class="funding-state-label">Verbindlich übernommen</p><p><strong>' .
            esc_html($what) .
            '</strong></p><p>In MeinVerein hinterlegt' .
            ($date !== '' ? ' am ' . esc_html($date) : '') .
            '. Ab jetzt gilt diese Vereinbarung.</p></section>';
    }
    if ($status === 'rejected') {
        return '<section class="funding-state is-rejected"><p class="funding-state-label">Nicht übernommen</p><p><strong>' .
            esc_html($what) .
            '</strong></p><p>An deinem bisherigen Beitrag ändert sich nichts.</p></section>';
    }
    return '<section class="funding-state is-requested"><p class="funding-state-label">Angefragt – noch nicht verbindlich</p><p><strong>' .
        esc_html($what) .
        '</strong></p><p>Verbindlich wird die Änderung erst, wenn der Vorstand sie in MeinVerein übernommen hat. Bis dahin ändert sich an deinem Beitrag nichts.</p></section>';
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
        echo '<a class="text-link service-back" href="' .
            esc_url(portal_url('admin')) .
            '">← Verwaltung</a>' .
            page_head('Service-Anfragen', 'Prüfen, Rückmeldung hinterlegen und die Übernahme dokumentieren.');
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
        echo '<p class="portal-empty">' .
            ($all
                ? 'Keine Anfragen mit diesem Status.'
                : 'Du hast noch keine Anfragen gestellt. Wähle oben aus, wobei wir dir helfen können.') .
            '</p>';
    }
    foreach (array_slice($rows, ($page - 1) * 20, 20) as $row) {
        $state = request_state($row->ID);
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
            '</h3></div>' .
            request_status_pill($state['status'] ?? 'received', get_post_meta($row->ID, 'et_kind', true)) .
            '<span aria-hidden="true">→</span></a>';
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
        '</a>' .
        page_head(
            $row->post_title,
            esc_html('#' . $id . ' · eingereicht am ' . get_the_date('d.m.Y', $row)) .
                ($manager
                    ? ' · von ' . esc_html($author ? $author->display_name : 'Gelöschtes Mitglied')
                    : ''),
            '',
            $manager ? 'admin' : 'service'
        );
    if (isset($_GET['saved']) && !$manager) {
        echo '<div class="request-confirm" role="status" tabindex="-1"><h2>Deine Anfrage ist eingegangen.</h2><p>Hier siehst du jederzeit, wie weit sie ist. Es wird keine E-Mail versendet – der Stand erscheint hier und auf deiner Startseite.</p></div>';
    } elseif (isset($_GET['saved'])) {
        echo '<p role="status" class="portal-success">Bearbeitung gespeichert.</p>';
    }
    $handler = !empty($state['actor']) ? get_user_by('id', (int) $state['actor']) : null;
    echo '<section class="request-overview"><h2 class="screen-reader-text">Stand</h2>' .
        request_timeline($row, $state, $kind) .
        '<dl class="request-facts"><div><dt>Wer kümmert sich</dt><dd>' .
        esc_html(request_owner_label($kind)) .
        ($handler ? ' · bearbeitet von ' . esc_html($handler->display_name) : '') .
        '</dd></div><div><dt>' .
        ($manager ? 'Das sieht das Mitglied' : 'Wie es weitergeht') .
        '</dt><dd>' .
        esc_html(request_next_step($state, $kind)) .
        '</dd></div></dl></section>';
    if ($kind === 'funding') {
        echo funding_summary($id, $state);
    }
    echo '<section class="form-section"><h2>' .
        ($manager ? 'Anfrage' : 'Deine Anfrage') .
        '</h2><p>' .
        nl2br(esc_html($row->post_content)) .
        '</p></section>';
    if (!empty($state['reply'])) {
        echo '<section class="form-section request-reply"><h2>Rückmeldung</h2><p>' .
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
    // Keep the dates of every status change for the member's timeline.
    $next['history'] = is_array($old['history'] ?? null) ? $old['history'] : [];
    if ($status !== ($old['status'] ?? 'received')) {
        $next['history'][] = ['status' => $status, 'at' => current_time('mysql', true)];
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
