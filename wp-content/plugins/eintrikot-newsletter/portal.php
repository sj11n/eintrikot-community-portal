<?php
/**
 * Bildschirme im Portal (Designfreigabe B, 04.11.2026):
 * Redaktion → Newsletter (Übersicht), eine Ausgabe (Vorschau, Testversand, Prüfliste, Freigabe, Fortschritt),
 * Service → Newsletter (Archiv für Mitglieder) und das Lesen einer versendeten Ausgabe.
 */

namespace Eintrikot\Newsletter;

use function Eintrikot\Community\page_head;
use function Eintrikot\Community\portal_url;
use function Eintrikot\Community\service_link;

if (!defined('ABSPATH')) {
    exit();
}

const VIEW_ARCHIVE = 'newsletter';
const VIEW_LIST = 'newsletter-redaktion';
const VIEW_ISSUE = 'newsletter-ausgabe';

function issue_url($id) {
    return portal_url(VIEW_ISSUE, ['ausgabe' => (int) $id]);
}
function new_issue_url() {
    return admin_url('post-new.php?post_type=' . POST_TYPE);
}
function edit_url($id) {
    return admin_url('post.php?post=' . (int) $id . '&action=edit');
}
function requested_issue() {
    return isset($_GET['ausgabe']) && is_scalar($_GET['ausgabe']) ? absint($_GET['ausgabe']) : 0;
}

/* ---------- Einbindung ins Portal ---------- */

add_filter(
    'eintrikot_portal_nav_section',
    function ($section, $view) {
        if ($view === VIEW_ARCHIVE) {
            return 'service';
        }
        return in_array($view, [VIEW_LIST, VIEW_ISSUE], true) ? 'editorial' : $section;
    },
    10,
    2
);

add_filter(
    'eintrikot_portal_view',
    function ($html, $view) {
        if (!in_array($view, [VIEW_ARCHIVE, VIEW_LIST, VIEW_ISSUE], true) || !core_ready()) {
            return $html;
        }
        if ($view === VIEW_ARCHIVE) {
            return requested_issue() ? render_read(requested_issue()) : render_archive();
        }
        if (!can_write()) {
            return '<div class="portal-empty"><h1>Kein Zugriff</h1><p>Dieser Bereich ist für deine Rolle nicht freigeschaltet.</p></div>';
        }
        return $view === VIEW_LIST ? render_list() : render_issue(requested_issue());
    },
    10,
    2
);

add_filter('eintrikot_editorial_newsletter', function () {
    $open = count(issues(['draft', 'ready', 'approved', 'sending', 'cancelled']));
    return '<section class="portal-section"><h2>Newsletter</h2>' .
        service_link(
            portal_url(VIEW_LIST),
            'Newsletter-Ausgaben',
            $open ? $open . ' in Arbeit · schreiben, prüfen, freigeben' : 'Schreiben, prüfen, freigeben'
        ) .
        '</section>';
});

add_filter('eintrikot_service_documents', function ($html) {
    return $html . service_link(portal_url(VIEW_ARCHIVE), 'Newsletter', 'Alle Ausgaben zum Nachlesen');
});

add_action(
    'wp_enqueue_scripts',
    function () {
        $portal = (int) get_option('eintrikot_portal_page');
        if ($portal > 0 && is_page($portal)) {
            wp_enqueue_style(
                'eintrikot-newsletter',
                plugins_url('newsletter.css', __FILE__),
                ['eintrikot-portal'],
                asset_version('newsletter.css')
            );
            wp_enqueue_script(
                'eintrikot-newsletter',
                plugins_url('newsletter.js', __FILE__),
                [],
                asset_version('newsletter.js'),
                true
            );
        }
    },
    20
);

/* ---------- Bausteine ---------- */

/** @return \WP_Post[] Issues in the given states, newest first. */
function issues(array $states) {
    return get_posts([
        'post_type' => POST_TYPE,
        'post_status' => ['draft', 'private', 'publish', 'pending'],
        'numberposts' => -1,
        'orderby' => 'modified',
        'order' => 'DESC',
        'meta_query' => in_array('draft', $states, true)
            ? [
                'relation' => 'OR',
                ['key' => '_et_nl_state', 'value' => $states, 'compare' => 'IN'],
                ['key' => '_et_nl_state', 'compare' => 'NOT EXISTS']
            ]
            : [['key' => '_et_nl_state', 'value' => $states, 'compare' => 'IN']]
    ]);
}

function status_pill($id) {
    return '<span class="nl-status nl-' .
        esc_attr(state($id)) .
        '">' .
        esc_html(state_label($id)) .
        '</span>';
}

function steps($id) {
    $at = ['draft' => 0, 'ready' => 1, 'approved' => 2, 'sending' => 2, 'cancelled' => 2, 'sent' => 4][
        state($id)
    ];
    $html = '<ol class="nl-steps" aria-label="Stand der Ausgabe">';
    foreach (['Entwurf', 'Bereit', 'Freigegeben', 'Versendet'] as $i => $label) {
        $class = $i < $at ? 'done' : ($i === $at ? 'current' : '');
        $html .=
            '<li class="' .
            $class .
            '"' .
            ($i === $at ? ' aria-current="step"' : '') .
            '><span aria-hidden="true">' .
            ($i < $at ? '✓' : $i + 1) .
            '</span>' .
            esc_html($label) .
            ($i < $at ? '<span class="screen-reader-text"> (erledigt)</span>' : '') .
            '</li>';
    }
    return $html . '</ol>';
}

function when($timestamp) {
    return $timestamp ? wp_date('d.m.Y, H:i', (int) $timestamp) : '';
}

/** Message from the last action, shown once. */
function flash($type = null, $text = '') {
    $key = 'et_nl_msg_' . get_current_user_id();
    if ($type !== null) {
        set_transient($key, [$type, $text], 5 * MINUTE_IN_SECONDS);
        return '';
    }
    $msg = get_transient($key);
    if (!is_array($msg)) {
        return '';
    }
    delete_transient($key);
    return $msg[0] === 'ok'
        ? '<p role="status" class="portal-success" tabindex="-1">' . esc_html($msg[1]) . '</p>'
        : '<div class="form-error" role="alert" tabindex="-1"><strong>Das hat nicht geklappt.</strong><p>' .
                esc_html($msg[1]) .
                '</p></div>';
}

/** Form to admin-post.php for one action on an issue. */
function action_form($id, $do, $inner, $class = '') {
    return '<form method="post" class="' .
        esc_attr($class) .
        '" action="' .
        esc_url(admin_url('admin-post.php')) .
        '"><input type="hidden" name="action" value="et_newsletter_issue"><input type="hidden" name="do" value="' .
        esc_attr($do) .
        '"><input type="hidden" name="ausgabe" value="' .
        (int) $id .
        '">' .
        wp_nonce_field('et_newsletter_issue_' . (int) $id, '_wpnonce', true, false) .
        $inner .
        '</form>';
}

function preview($data) {
    $base = ['web' => true, 'name' => \Eintrikot\Community\first_name(wp_get_current_user()->display_name)];
    return '<section class="portal-section nl-preview-section"><div class="nl-preview-head"><h2>Vorschau</h2><div class="nl-device" role="group" aria-label="Ansicht der Vorschau" hidden><button type="button" class="active" data-nl-device="desktop" aria-pressed="true">Computer</button><button type="button" data-nl-device="phone" aria-pressed="false">Handy</button><button type="button" data-nl-device="dark" aria-pressed="false">Dunkel</button></div></div>' .
        '<div class="nl-preview"><p class="nl-inbox"><strong>' .
        esc_html(settings()['from_name']) .
        '</strong><span>' .
        esc_html($data['subject'] !== '' ? $data['subject'] : '(noch kein Betreff)') .
        '</span><small>' .
        esc_html($data['preheader'] !== '' ? $data['preheader'] : '(noch kein Vorschautext)') .
        '</small></p><div class="nl-frame"><iframe class="nl-mail" title="Vorschau der E-Mail" loading="lazy" srcdoc="' .
        esc_attr(render_mail($data, $base + ['scheme' => 'light'])) .
        // Dark version as an attribute too: the portal page runs through wptexturize, which leaves attributes alone
        // but would turn "1024x538" in image file names into "1024×538" inside text.
        '" data-nl-dark="' .
        esc_attr(render_mail($data, $base + ['scheme' => 'dark'])) .
        '"></iframe></div></div></section>';
}

/* ---------- Redaktion → Newsletter ---------- */

function render_list() {
    $yes = count(\Eintrikot\Community\newsletter_recipient_ids());
    $open = 0;
    foreach (get_users(['capability' => 'eintrikot_portal', 'number' => -1, 'fields' => 'ID']) as $uid) {
        if (
            \Eintrikot\Community\newsletter_allowed((int) $uid) &&
            \Eintrikot\Community\newsletter_consent((int) $uid)['state'] === ''
        ) {
            $open++;
        }
    }
    $row = static function ($post) {
        $s = state($post->ID);
        if ($s === 'sent') {
            $sent = get_post_meta($post->ID, '_et_nl_sent', true);
            $meta =
                'Versendet am ' .
                wp_date('d.m.Y', (int) ($sent['at'] ?? 0)) .
                ' an ' .
                (int) ($sent['sent'] ?? 0) .
                ' Mitglieder';
            if (!get_post_meta($post->ID, '_et_nl_bounces', true)) {
                $meta .= ' · Rückläufer noch prüfen';
            }
        } elseif (in_array($s, ['approved', 'sending', 'cancelled'], true)) {
            $c = queue_counts($post->ID);
            $meta = $c['sent'] . ' von ' . $c['total'] . ' gesendet';
        } else {
            $meta =
                'Zuletzt geändert von ' .
                user_name(last_editor($post->ID)) .
                ' am ' .
                get_the_modified_date('d.m.Y', $post);
        }
        return '<li><a href="' .
            esc_url(issue_url($post->ID)) .
            '"><span><strong>' .
            esc_html(label($post->ID) ?: ($post->post_title ?: '(ohne Titel)')) .
            '</strong><small>' .
            esc_html(
                ($post->post_title !== '' && label($post->ID) !== '' ? $post->post_title . ' · ' : '') . $meta
            ) .
            '</small></span>' .
            status_pill($post->ID) .
            '<span aria-hidden="true">→</span></a></li>';
    };
    $working = issues(['draft', 'ready', 'approved', 'sending', 'cancelled']);
    $sent = issues(['sent']);
    $html =
        '<a class="text-link service-back" href="' .
        esc_url(portal_url('editorial')) .
        '">← Redaktion</a>' .
        page_head(
            'Newsletter',
            'Ausgaben schreiben, prüfen, freigeben. Geht nur an Mitglieder, die zugestimmt haben.',
            '<p class="nl-head-actions"><a class="button solid" href="' .
                esc_url(new_issue_url()) .
                '">Neue Ausgabe anlegen</a></p>',
            'editorial'
        ) .
        flash() .
        '<section class="portal-section nl-reach" aria-label="Empfänger"><div><strong>' .
        esc_html((string) $yes) .
        '</strong><span>Mitglieder mit Einwilligung</span></div><div><strong>' .
        esc_html((string) $open) .
        '</strong><span>noch nicht beantwortet</span></div><p>Nur „Ja“ zählt. Wer nicht geantwortet oder „Nein“ gesagt hat, bekommt nichts. Vereinsmitteilungen laufen weiter über den E-Mail-Verteiler in der Verwaltung.</p></section>' .
        '<section class="portal-section"><h2>In Arbeit</h2>' .
        ($working
            ? '<ul class="nl-issues">' . implode('', array_map($row, $working)) . '</ul>'
            : '<p class="portal-empty">Gerade ist keine Ausgabe in Arbeit. Mit „Neue Ausgabe anlegen“ geht es los.</p>') .
        '</section>';
    if ($sent) {
        $html .=
            '<section class="portal-section"><h2>Versendet</h2><ul class="nl-issues">' .
            implode('', array_map($row, $sent)) .
            '</ul></section>';
    }
    if (\Eintrikot\Community\view_admin()) {
        $s = settings();
        $html .=
            '<section class="portal-section"><h2>Versand-Einstellungen</h2><form class="et-form nl-settings" method="post" action="' .
            esc_url(admin_url('admin-post.php')) .
            '"><input type="hidden" name="action" value="et_newsletter_settings">' .
            wp_nonce_field('et_newsletter_settings', '_wpnonce', true, false) .
            '<label>Absender-Adresse<input type="email" name="from" required value="' .
            esc_attr($s['from']) .
            '"><small>Muss im SMTP-Konto als Adresse oder Alias eingerichtet sein.</small></label><label>Absender-Name<input type="text" name="from_name" maxlength="60" value="' .
            esc_attr($s['from_name']) .
            '"></label><label>Antworten gehen an<input type="email" name="reply_to" required value="' .
            esc_attr($s['reply_to']) .
            '"></label><label>Mails pro Minute<input type="number" name="batch" min="1" max="100" value="' .
            esc_attr((string) $s['batch']) .
            '"><small>Der Versand läuft über WP-Cron. Auf dem Server ruft ein Cronjob jede Minute wp-cron.php auf.</small></label><button class="button" type="submit">Einstellungen speichern</button></form></section>';
    }
    return $html;
}

/* ---------- Eine Ausgabe ---------- */

function render_issue($id) {
    $post = issue($id);
    $back =
        '<a class="text-link service-back" href="' . esc_url(portal_url(VIEW_LIST)) . '">← Newsletter</a>';
    if (!$post) {
        return $back .
            '<div class="portal-empty"><h1>Ausgabe nicht gefunden</h1><p>Vielleicht wurde sie gelöscht.</p></div>';
    }
    $s = state($id);
    $data = draft_data($id);
    $html =
        $back .
        page_head(
            label($id) ?: ($post->post_title ?: 'Neue Ausgabe'),
            'Betreff: ' . esc_html($post->post_title !== '' ? $post->post_title : '(noch keiner)'),
            steps($id),
            'editorial'
        ) .
        flash();
    if (!locked($id)) {
        $html .=
            '<p class="nl-actions"><a class="button" href="' .
            esc_url(edit_url($id)) .
            '">Im Editor bearbeiten</a></p>';
    }
    switch ($s) {
        case 'draft':
            return $html . preview($data) . test_section($id) . checklist_section($id);
        case 'ready':
            return $html .
                (can_approve() ? approve_section($id) : waiting_section($id)) .
                preview($data) .
                test_section($id);
        case 'approved':
        case 'sending':
        case 'cancelled':
            return $html . progress_section($id) . errors_section($id) . preview($data);
        default:
            return $html . sent_section($id) . errors_section($id) . preview($data);
    }
}

function test_section($id) {
    $test = get_post_meta($id, '_et_nl_test', true);
    $me = wp_get_current_user();
    $value = is_array($test) && !empty($test['to']) ? implode(', ', (array) $test['to']) : $me->user_email;
    return '<section class="portal-section"><h2>Testversand</h2>' .
        action_form(
            $id,
            'test',
            '<label for="nl-test-to">An diese Adressen</label><input id="nl-test-to" type="text" name="to" value="' .
                esc_attr($value) .
                '" autocomplete="off"><small class="nl-hint">Nur Vorstand, Redaktion und Testpostfächer, nie Mitglieder. Mehrere Adressen mit Komma trennen, höchstens 10.</small><p class="nl-buttons"><button class="button" type="submit">Testmail senden</button></p><p class="nl-hint">Der Betreff beginnt mit [TEST].' .
                (is_array($test) && !empty($test['at'])
                    ? ' Zuletzt gesendet am ' .
                        esc_html(when($test['at'])) .
                        ' von ' .
                        esc_html(user_name($test['by'] ?? 0)) .
                        '.'
                    : '') .
                '</p>',
            'et-form nl-test'
        ) .
        '</section>';
}

function system_list($id) {
    $html = '<ul class="nl-auto">';
    foreach (system_checks($id) as [$ok, $text]) {
        $html .=
            '<li class="' .
            ($ok ? 'ok' : 'open') .
            '"><span aria-hidden="true">' .
            ($ok ? '✓' : '×') .
            '</span>' .
            esc_html($text) .
            '<span class="screen-reader-text">' .
            ($ok ? ' (erfüllt)' : ' (offen)') .
            '</span></li>';
    }
    return $html . '</ul>';
}

function checklist_section($id) {
    $ticked = checks($id);
    $boxes = '';
    foreach (checklist() as $key => $label) {
        $boxes .=
            '<label class="check"><input type="checkbox" name="checks[]" value="' .
            esc_attr($key) .
            '" ' .
            checked(in_array($key, $ticked, true), true, false) .
            '><span>' .
            esc_html($label) .
            '</span></label>';
    }
    $open = count(checklist()) - count($ticked);
    return '<section class="portal-section"><h2>Prüfliste</h2><p>Alles abhaken, dann kann der Vorstand freigeben. Was das System selbst prüft, steht darunter.</p>' .
        action_form(
            $id,
            'ready',
            '<div class="nl-checklist">' .
                $boxes .
                '</div><h3 class="nl-sub">Prüft das System</h3>' .
                system_list($id) .
                '<p class="nl-buttons"><button class="button solid" type="submit" name="ready" value="1">Als bereit melden</button> <button class="text-link" type="submit" name="ready" value="0">Nur Haken speichern</button></p>' .
                ($open > 0
                    ? '<p class="nl-hint">Noch ' .
                        $open .
                        ($open === 1 ? ' Punkt' : ' Punkte') .
                        ' offen.</p>'
                    : ''),
            'nl-ready-form'
        ) .
        '</section>';
}

function waiting_section($id) {
    $ready = get_post_meta($id, '_et_nl_ready', true);
    return '<section class="portal-section nl-wait"><h2>Wartet auf Freigabe</h2><p>Gemeldet von ' .
        esc_html(user_name($ready['by'] ?? 0)) .
        ' am ' .
        esc_html(when($ready['at'] ?? 0)) .
        '. Freigeben kann nur der Vorstand. Änderungen bleiben möglich; wer zuletzt ändert, darf nicht freigeben.</p>' .
        action_form(
            $id,
            'draft',
            '<p class="nl-buttons"><button class="button" type="submit">Zurück in den Entwurf</button></p>'
        ) .
        '</section>';
}

function approve_section($id) {
    $count = count(\Eintrikot\Community\newsletter_recipient_ids());
    $minutes = max(1, (int) ceil($count / settings()['batch']));
    $ready = get_post_meta($id, '_et_nl_ready', true);
    $test = get_post_meta($id, '_et_nl_test', true);
    $editor = last_editor($id);
    $mine = $editor === get_current_user_id();
    $fact = static function ($ok, $text) {
        return '<li class="' .
            ($ok ? 'ok' : 'open') .
            '"><span aria-hidden="true">' .
            ($ok ? '✓' : '×') .
            '</span>' .
            esc_html($text) .
            '</li>';
    };
    $html =
        '<section class="portal-section nl-approve"><h2>Freigeben und versenden</h2><div class="nl-count"><strong>' .
        esc_html((string) $count) .
        '</strong><span>Geht an ' .
        esc_html((string) $count) .
        ($count === 1 ? ' Mitglied' : ' Mitglieder') .
        ' mit Einwilligung.<br>Jede Mail einzeln, etwa ' .
        esc_html((string) settings()['batch']) .
        ' pro Minute, Dauer etwa ' .
        esc_html((string) $minutes) .
        ($minutes === 1 ? ' Minute' : ' Minuten') .
        '.</span></div><ul class="nl-facts">' .
        $fact(
            true,
            'Prüfliste vollständig (' .
                user_name($ready['by'] ?? 0) .
                ', ' .
                wp_date('d.m.Y', (int) ($ready['at'] ?? 0)) .
                ')'
        ) .
        $fact(
            system_ok($id),
            system_ok($id)
                ? 'Prüfungen des Systems erfüllt, Testmail der aktuellen Fassung am ' .
                    wp_date('d.m.Y', (int) ($test['at'] ?? 0))
                : 'Prüfungen des Systems nicht erfüllt: bitte zurück an die Redaktion'
        ) .
        $fact(
            !$mine,
            $mine
                ? 'Du hast den Inhalt zuletzt geändert. Freigeben muss eine andere Person aus dem Vorstand (Vier-Augen-Prinzip).'
                : 'Zuletzt geändert von ' . user_name($editor) . ': Du kannst freigeben (Vier-Augen-Prinzip).'
        ) .
        '</ul>';
    if (!$mine && system_ok($id) && $count > 0) {
        $html .= action_form(
            $id,
            'approve',
            '<input type="hidden" name="expected" value="' .
                esc_attr((string) $count) .
                '"><label class="check"><input type="checkbox" name="confirm" value="1" required data-nl-confirm><span>Ich habe die Vorschau gelesen und gebe diese Ausgabe für ' .
                esc_html((string) $count) .
                ' Empfänger frei.</span></label><p class="nl-buttons"><button class="button solid" type="submit" data-nl-confirmed>Freigeben und Versand starten</button></p>',
            'et-form'
        );
    } elseif ($count === 0) {
        $html .= '<p>Es gibt noch keine Mitglieder mit Einwilligung.</p>';
    }
    return $html .
        action_form(
            $id,
            'draft',
            '<p class="nl-buttons"><button class="text-link" type="submit">Zurück an die Redaktion</button></p>'
        ) .
        '</section>';
}

function progress_section($id) {
    $c = queue_counts($id);
    $s = state($id);
    $approval = get_post_meta($id, '_et_nl_approval', true);
    $done = $c['sent'] + $c['error'] + $c['skipped'];
    $percent = $c['total'] ? (int) round(($done / $c['total']) * 100) : 0;
    $html =
        '<section class="portal-section nl-run"' .
        ($s === 'cancelled' ? '' : ' data-nl-refresh="20"') .
        '><h2>' .
        esc_html($c['sent'] . ' von ' . $c['total'] . ' gesendet') .
        '</h2><div class="nl-bar" role="progressbar" aria-label="Fortschritt des Versands" aria-valuemin="0" aria-valuemax="' .
        (int) $c['total'] .
        '" aria-valuenow="' .
        (int) $done .
        '"><span style="width:' .
        (int) $percent .
        '%"></span></div><p class="nl-run-meta"><span>' .
        esc_html($c['sent'] . ' gesendet') .
        '</span><span>' .
        esc_html($c['waiting'] + $c['sending'] . ' warten') .
        '</span>' .
        ($c['skipped'] ? '<span>' . esc_html($c['skipped'] . ' abgemeldet') . '</span>' : '') .
        '<span class="' .
        ($c['error'] ? 'nl-err' : '') .
        '">' .
        esc_html($c['error'] . ' Fehler') .
        '</span></p><p>Freigegeben von ' .
        esc_html(user_name($approval['by'] ?? 0)) .
        ' am ' .
        esc_html(when($approval['at'] ?? 0)) .
        '. ';
    if ($s === 'cancelled') {
        $html .=
            'Der Versand ist <strong>abgebrochen</strong>. Bereits gesendete Mails bleiben gesendet. Beim Fortsetzen bekommt niemand die Ausgabe doppelt.</p>';
        if (can_approve()) {
            $html .= action_form(
                $id,
                'resume',
                '<p class="nl-buttons"><button class="button solid" type="submit">Versand fortsetzen</button></p>'
            );
        }
        return $html . '</section>';
    }
    $html .=
        'Fehlgeschlagene Mails werden bis zu dreimal wiederholt. Wer sich jetzt abmeldet, bekommt nichts mehr. Die Seite aktualisiert sich von selbst.</p>' .
        ($s === 'approved'
            ? '<p class="nl-hint">Der Versand beginnt mit dem nächsten Durchlauf, spätestens in einer Minute.</p>'
            : '');
    if (can_approve()) {
        $html .= action_form(
            $id,
            'cancel',
            '<p class="nl-buttons"><button class="button danger" type="submit" data-nl-ask="Versand wirklich abbrechen?">Versand abbrechen</button></p><p class="nl-hint">Bereits gesendete Mails bleiben gesendet. Fortsetzen ohne Doppelversand ist möglich.</p>'
        );
    }
    return $html . '</section>';
}

function errors_section($id) {
    $rows = queue_errors($id);
    if (!$rows) {
        return '';
    }
    $html = '<section class="portal-section"><h2>Fehler</h2><ul class="nl-issues">';
    foreach ($rows as $row) {
        $number = \Eintrikot\Community\member_number_label(
            get_user_meta((int) $row->user_id, 'eintrikot_member_number', true)
        );
        $html .=
            '<li><span class="nl-row"><span><strong>' .
            esc_html($number !== '' ? 'Mitglied ' . $number : user_name($row->user_id)) .
            '</strong><small>' .
            esc_html(
                $row->error .
                    ($row->attempts
                        ? ', ' . $row->attempts . ($row->attempts === '1' ? ' Versuch' : ' Versuche')
                        : '')
            ) .
            '</small></span>' .
            (\Eintrikot\Community\view_manager()
                ? '<a class="text-link" href="' .
                    esc_url(portal_url('edit-member', ['member' => (int) $row->user_id])) .
                    '">Profil öffnen</a>'
                : '') .
            '</span></li>';
    }
    return $html .
        '</ul><p class="nl-hint">Nach dem Versand die Rückläufer im Postfach ' .
        esc_html(settings()['from']) .
        ' prüfen und kaputte Adressen im Profil korrigieren.</p></section>';
}

function sent_section($id) {
    $sent = get_post_meta($id, '_et_nl_sent', true);
    $approval = get_post_meta($id, '_et_nl_approval', true);
    $bounces = get_post_meta($id, '_et_nl_bounces', true);
    $html =
        '<section class="portal-section nl-done"><h2>Versendet an ' .
        esc_html((string) (int) ($sent['sent'] ?? 0)) .
        ' Mitglieder</h2><p>Freigegeben von ' .
        esc_html(user_name($approval['by'] ?? 0)) .
        ' am ' .
        esc_html(when($approval['at'] ?? 0)) .
        ', fertig am ' .
        esc_html(when($sent['at'] ?? 0)) .
        '. ' .
        esc_html(
            (int) ($sent['error'] ?? 0) .
                ' Fehler, ' .
                (int) ($sent['skipped'] ?? 0) .
                ' vor dem Versand abgemeldet.'
        ) .
        ' <a class="text-link" href="' .
        esc_url(portal_url(VIEW_ARCHIVE, ['ausgabe' => (int) $id])) .
        '">Im Archiv ansehen</a></p>';
    if (is_array($bounces) && !empty($bounces['at'])) {
        return $html .
            '<p class="nl-auto-line"><span aria-hidden="true">✓</span> Rückläufer geprüft von ' .
            esc_html(user_name($bounces['by'] ?? 0)) .
            ' am ' .
            esc_html(when($bounces['at'])) .
            '</p></section>';
    }
    return $html .
        action_form(
            $id,
            'bounces',
            '<label class="check"><input type="checkbox" name="confirm" value="1" required><span>Rückläufer im Postfach ' .
                esc_html(settings()['from']) .
                ' geprüft und kaputte Adressen im Profil korrigiert</span></label><p class="nl-buttons"><button class="button" type="submit">Als geprüft vermerken</button></p>',
            'et-form'
        ) .
        '</section>';
}

/* ---------- Service → Newsletter (Archiv) ---------- */

function render_archive() {
    $me = get_current_user_id();
    $c = \Eintrikot\Community\newsletter_consent($me);
    if (!\Eintrikot\Community\newsletter_allowed($me)) {
        $mine = '<p><strong>Den Newsletter gibt es ab 18 Jahren.</strong></p>';
    } elseif ($c['state'] === 'yes') {
        $mine =
            '<p><strong>Du bekommst den Newsletter.</strong> Ungefähr viermal im Jahr an ' .
            esc_html(wp_get_current_user()->user_email) .
            '.</p><a class="text-link" href="' .
            esc_url(portal_url('profile')) .
            '">Im Profil ändern</a>';
    } else {
        $mine =
            '<p><strong>Du bekommst den Newsletter nicht.</strong> Hier kannst du trotzdem alle Ausgaben lesen.</p><a class="text-link" href="' .
            esc_url(portal_url('profile')) .
            '">Im Profil einschalten</a>';
    }
    $items = '';
    foreach (issues(['sent']) as $post) {
        $data = frozen($post->ID);
        if (!$data) {
            continue;
        }
        $sent = get_post_meta($post->ID, '_et_nl_sent', true);
        $items .=
            '<li><a href="' .
            esc_url(portal_url(VIEW_ARCHIVE, ['ausgabe' => $post->ID])) .
            '"><time datetime="' .
            esc_attr(wp_date('Y-m-d', (int) ($sent['at'] ?? 0))) .
            '">' .
            esc_html($data['label'] !== '' ? $data['label'] : wp_date('m/Y', (int) ($sent['at'] ?? 0))) .
            '</time><span><strong>' .
            esc_html($data['subject']) .
            '</strong><small>' .
            esc_html($data['preheader']) .
            '</small></span><span aria-hidden="true">→</span></a></li>';
    }
    return '<a class="text-link service-back" href="' .
        esc_url(portal_url('service')) .
        '">← Service</a>' .
        page_head('Newsletter', 'Alle bisherigen Ausgaben zum Nachlesen.', '', 'service') .
        '<section class="portal-section nl-mine">' .
        $mine .
        '</section><section class="portal-section"><h2>Ausgaben</h2>' .
        ($items !== ''
            ? '<ul class="nl-archive">' . $items . '</ul>'
            : '<p class="portal-empty">Noch keine Ausgabe versendet. Die erste erscheint hier, sobald sie verschickt ist.</p>') .
        '</section>';
}

function render_read($id) {
    $back =
        '<a class="text-link service-back" href="' . esc_url(portal_url(VIEW_ARCHIVE)) . '">← Newsletter</a>';
    $data = issue($id) && state($id) === 'sent' ? frozen($id) : null;
    if (!$data) {
        return $back .
            '<div class="portal-empty"><h1>Ausgabe nicht gefunden</h1><p>Im Archiv stehen nur versendete Ausgaben.</p></div>';
    }
    $sent = get_post_meta($id, '_et_nl_sent', true);
    return $back .
        page_head(
            $data['subject'],
            'Newsletter' .
                ($data['label'] !== '' ? ' · ' . esc_html($data['label']) : '') .
                ' · versendet am ' .
                esc_html(wp_date('d.m.Y', (int) ($sent['at'] ?? 0))),
            '',
            'service'
        ) .
        '<article class="nl-read"><iframe class="nl-mail" title="' .
        esc_attr($data['subject']) .
        '" srcdoc="' .
        esc_attr(
            render_mail($data, [
                'web' => true,
                'scheme' => 'light',
                'name' => \Eintrikot\Community\first_name(wp_get_current_user()->display_name)
            ])
        ) .
        '"></iframe></article>';
}

/* ---------- Aktionen ---------- */

add_action('admin_post_et_newsletter_issue', function () {
    $id = isset($_POST['ausgabe']) ? absint($_POST['ausgabe']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked right below with the issue in the action name
    check_admin_referer('et_newsletter_issue_' . $id);
    if (!core_ready() || !\Eintrikot\Community\member_access() || !can_write() || !issue($id)) {
        wp_die('Keine Berechtigung.', '', ['response' => 403]);
    }
    $do = \Eintrikot\Community\post_choice('do', [
        'test',
        'ready',
        'draft',
        'approve',
        'cancel',
        'resume',
        'bounces'
    ]);
    $ok = [
        'test' => 'Testmail verschickt. Bitte auf Handy und Computer ansehen.',
        'ready' => 'Als bereit gemeldet. Jetzt kann der Vorstand freigeben.',
        'draft' => 'Zurück im Entwurf.',
        'approve' => 'Freigegeben. Der Versand beginnt spätestens in einer Minute.',
        'cancel' => 'Versand abgebrochen.',
        'resume' => 'Versand wird fortgesetzt.',
        'bounces' => 'Vermerkt: Rückläufer geprüft.'
    ];
    $confirmed = \Eintrikot\Community\post_choice('confirm', ['1']) === '1';
    switch ($do) {
        case 'test':
            $error = send_test($id, \Eintrikot\Community\post_text('to', '', 1000));
            break;
        case 'ready':
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked above
            $ticked =
                isset($_POST['checks']) && is_array($_POST['checks'])
                    ? array_map('sanitize_key', wp_unslash($_POST['checks']))
                    : [];
            if (\Eintrikot\Community\post_choice('ready', ['0', '1']) !== '1') {
                $error = state($id) === 'draft' ? '' : 'Die Prüfliste gibt es nur im Entwurf.';
                if ($error === '') {
                    save_checks($id, $ticked);
                    $ok['ready'] = 'Haken gespeichert.';
                }
                break;
            }
            $error = mark_ready($id, $ticked);
            break;
        case 'draft':
            $error = back_to_draft($id);
            break;
        case 'approve':
            $error = $confirmed
                ? approve($id, \Eintrikot\Community\post_text('expected', '0', 10))
                : 'Bitte die Freigabe mit dem Haken bestätigen.';
            if ($error === '') {
                spawn_cron();
            }
            break;
        case 'cancel':
            $error = cancel($id);
            break;
        case 'resume':
            $error = resume($id);
            if ($error === '') {
                spawn_cron();
            }
            break;
        case 'bounces':
            $error = $confirmed ? confirm_bounces($id) : 'Bitte mit dem Haken bestätigen.';
            break;
        default:
            $error = 'Unbekannte Aktion.';
    }
    flash($error === '' ? 'ok' : 'error', $error === '' ? $ok[$do] : $error);
    wp_safe_redirect(issue_url($id));
    exit();
});

add_action('admin_post_et_newsletter_settings', function () {
    check_admin_referer('et_newsletter_settings');
    if (!current_user_can('manage_options')) {
        wp_die('Keine Berechtigung.', '', ['response' => 403]);
    }
    $before = settings();
    $from = sanitize_email(\Eintrikot\Community\post_text('from', '', 200));
    $reply = sanitize_email(\Eintrikot\Community\post_text('reply_to', '', 200));
    $after = [
        'from' => is_email($from) ? $from : $before['from'],
        'from_name' => \Eintrikot\Community\post_text('from_name', $before['from_name'], 60),
        'reply_to' => is_email($reply) ? $reply : $before['reply_to'],
        'batch' => max(1, min(100, (int) \Eintrikot\Community\post_text('batch', '20', 4)))
    ];
    update_option('eintrikot_newsletter_settings', $after, false);
    \Eintrikot\Community\log_change(
        0,
        'newsletter_issue',
        $before,
        $after,
        'Versand-Einstellungen des Newsletters geändert'
    );
    flash('ok', 'Einstellungen gespeichert.');
    wp_safe_redirect(portal_url(VIEW_LIST));
    exit();
});
