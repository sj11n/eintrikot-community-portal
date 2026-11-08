<?php
/**
 * Eine Ausgabe (Beitragstyp et_newsletter): Betreff = Titel, Ausgabe (z. B. „Nr. 1 · Dezember 2026“), Farbe des Viertels, Vorschautext, Inhalt.
 * Stand, Prüfliste, Freigabe und eingefrorener Inhalt liegen in Metadaten mit Unterstrich (nicht über REST sichtbar).
 */

namespace Eintrikot\Newsletter;

use function Eintrikot\Community\log_change;

if (!defined('ABSPATH')) {
    exit();
}

const POST_TYPE = 'et_newsletter';

/** Building blocks allowed in an issue. Everything else is not offered in the editor and not sent. */
const BLOCKS = [
    'core/heading',
    'core/paragraph',
    'core/image',
    'core/buttons',
    'core/button',
    'core/separator',
    'core/quote',
    'core/list',
    'core/list-item',
    'core/group'
];

/** Checklist the editorial team ticks before an issue is ready. */
function checklist() {
    return [
        'facts' => 'Fakten, Namen und Daten stimmen',
        'links' => 'Alle Links führen an die richtige Stelle',
        'images' => 'Bildrechte sind geklärt',
        'subject' => 'Betreff und Vorschautext passen',
        'devices' => 'Testmail auf Handy und Computer angesehen'
    ];
}

function states() {
    return [
        'draft' => 'Entwurf',
        'ready' => 'Bereit',
        'approved' => 'Freigegeben',
        'sending' => 'Wird versendet',
        'sent' => 'Versendet',
        'cancelled' => 'Abgebrochen'
    ];
}

add_action('init', function () {
    register_post_type(POST_TYPE, [
        'label' => 'Newsletter',
        'labels' => [
            'name' => 'Newsletter',
            'singular_name' => 'Newsletter-Ausgabe',
            'add_new_item' => 'Neue Newsletter-Ausgabe',
            'edit_item' => 'Newsletter-Ausgabe bearbeiten'
        ],
        'public' => false,
        'publicly_queryable' => false,
        'exclude_from_search' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        // The block editor needs REST. Issues are always stored as private, so REST never shows them to visitors.
        'show_in_rest' => true,
        'show_in_nav_menus' => false,
        'query_var' => false,
        'rewrite' => false,
        'capabilities' => array_fill_keys(
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
        ),
        'map_meta_cap' => false,
        'supports' => ['title', 'editor', 'author'],
        'menu_icon' => 'dashicons-email-alt',
        'template' => [['core/paragraph', ['placeholder' => 'Einleitung: Was steckt in dieser Ausgabe?']]]
    ]);
});

/** Issues are never public: "Publish" in the editor stores them as private. */
add_filter(
    'wp_insert_post_data',
    function ($data) {
        if (
            ($data['post_type'] ?? '') === POST_TYPE &&
            in_array($data['post_status'], ['publish', 'future'], true)
        ) {
            $data['post_status'] = 'private';
        }
        return $data;
    },
    10,
    1
);

add_filter(
    'allowed_block_types_all',
    function ($allowed, $context) {
        return isset($context->post) &&
            $context->post instanceof \WP_Post &&
            $context->post->post_type === POST_TYPE
            ? BLOCKS
            : $allowed;
    },
    10,
    2
);

/** Once sending has begun, the issue is frozen: nobody can edit or delete it. */
function locked($id) {
    return in_array(state($id), ['sending', 'sent', 'cancelled'], true);
}
add_filter(
    'user_has_cap',
    function ($allcaps, $caps, $args) {
        if (
            in_array($args[0] ?? '', ['edit_post', 'delete_post'], true) &&
            !empty($args[2]) &&
            get_post_type((int) $args[2]) === POST_TYPE &&
            locked((int) $args[2])
        ) {
            foreach ($caps as $cap) {
                $allcaps[$cap] = false;
            }
        }
        return $allcaps;
    },
    10,
    3
);

/* ---------- Lesen ---------- */

function state($id) {
    $state = (string) get_post_meta((int) $id, '_et_nl_state', true);
    return isset(states()[$state]) ? $state : 'draft';
}
function state_label($id) {
    return states()[state($id)];
}
function label($id) {
    return (string) get_post_meta((int) $id, '_et_nl_label', true);
}
function preheader($id) {
    return (string) get_post_meta((int) $id, '_et_nl_preheader', true);
}
/** @return string[] */
function checks($id) {
    $v = get_post_meta((int) $id, '_et_nl_checks', true);
    return is_array($v) ? array_values(array_intersect(array_keys(checklist()), $v)) : [];
}
/** Who changed the content last; the approval must come from someone else. */
function last_editor($id) {
    $editor = (int) get_post_meta((int) $id, '_et_nl_last_editor', true);
    return $editor ?: (int) get_post_field('post_author', (int) $id);
}
/** Fingerprint of everything that goes into the mail. */
function content_hash($id) {
    $post = get_post((int) $id);
    return $post
        ? md5(
            $post->post_title .
                "\n" .
                $post->post_content .
                "\n" .
                preheader($id) .
                "\n" .
                label($id) .
                "\n" .
                color($id)
        )
        : '';
}
function issue($id) {
    $post = get_post((int) $id);
    return $post && $post->post_type === POST_TYPE && $post->post_status !== 'trash' ? $post : null;
}
/** @return array{subject: string, preheader: string, label: string, body: string, text: string, color: string}|null */
function frozen($id) {
    $v = get_post_meta((int) $id, '_et_nl_frozen', true);
    return is_array($v) && isset($v['subject'], $v['body'])
        ? $v + ['preheader' => '', 'label' => '', 'text' => '', 'color' => 'rose']
        : null;
}
function user_name($user_id) {
    $user = get_userdata((int) $user_id);
    return $user ? $user->display_name : 'Benutzer #' . (int) $user_id;
}

/* ---------- Rechte ---------- */

function can_write() {
    return \Eintrikot\Community\view_can_edit_infos();
}
function can_approve() {
    return \Eintrikot\Community\view_manager();
}

/* ---------- Änderungen am Inhalt ---------- */

/**
 * Notes who changed the content. A change after the approval (before the first mail went out) takes the
 * approval back: the issue is "Bereit" again and must be approved anew.
 */
function track_change($id) {
    $id = (int) $id;
    $hash = content_hash($id);
    if ($hash === '' || $hash === get_post_meta($id, '_et_nl_hash', true)) {
        return;
    }
    update_post_meta($id, '_et_nl_hash', $hash);
    if (get_current_user_id()) {
        update_post_meta($id, '_et_nl_last_editor', get_current_user_id());
    }
    update_post_meta($id, '_et_nl_changed_at', time());
    if (state($id) === 'approved') {
        withdraw_approval($id, 'Inhalt nach der Freigabe geändert');
    }
}
add_action(
    'save_post_' . POST_TYPE,
    function ($id) {
        if (wp_is_post_revision($id) || wp_is_post_autosave($id)) {
            return;
        }
        track_change($id);
    },
    20
);

/* ---------- Angaben im Editor: Ausgabe, Vorschautext, Farbe des Viertels ---------- */

add_action('add_meta_boxes_' . POST_TYPE, function () {
    add_meta_box('et-newsletter', 'Newsletter', __NAMESPACE__ . '\meta_box', POST_TYPE, 'side', 'high');
});
function meta_box($post) {
    wp_nonce_field('et_newsletter_meta', 'et_newsletter_meta_nonce');
    echo '<p><label for="et-nl-label"><strong>Ausgabe</strong></label><br><input type="text" id="et-nl-label" name="et_nl_label" class="widefat" maxlength="60" placeholder="z. B. Nr. 1 · Dezember 2026" value="' .
        esc_attr(label($post->ID)) .
        '"></p><p><label for="et-nl-preheader"><strong>Vorschautext</strong></label><br><textarea id="et-nl-preheader" name="et_nl_preheader" class="widefat" rows="3" maxlength="150">' .
        esc_textarea(preheader($post->ID)) .
        '</textarea><small>Erscheint im Postfach neben dem Betreff. Höchstens 150 Zeichen.</small></p>' .
        '<p><label for="et-nl-color"><strong>Farbe des Viertels</strong></label><br><select id="et-nl-color" name="et_nl_color" class="widefat">' .
        wp_kses(
            implode(
                '',
                array_map(
                    static fn($key, $c) => '<option value="' .
                        esc_attr($key) .
                        '"' .
                        selected(color($post->ID), $key, false) .
                        '>' .
                        esc_html($c['label']) .
                        '</option>',
                    array_keys(colors()),
                    colors()
                )
            ),
            ['option' => ['value' => true, 'selected' => true]]
        ) .
        '</select><small>Färbt Titelkasten, Rubrikpunkte, Datumskacheln und den Abpfiff.</small></p>' .
        '<p><small>Der Titel ist der Betreff. Die Anrede („Hallo Vorname,“, unter der ersten Rubrik), das Logo und die Fußzeile mit Abmeldelink setzt das System selbst.</small></p>' .
        '<p><a class="button" href="' .
        esc_url(issue_url($post->ID)) .
        '">Vorschau, Test und Prüfung im Portal →</a></p>';
}
add_action('save_post_' . POST_TYPE, function ($id) {
    if (
        empty($_POST['et_newsletter_meta_nonce']) ||
        !wp_verify_nonce(
            sanitize_key(wp_unslash($_POST['et_newsletter_meta_nonce'])),
            'et_newsletter_meta'
        ) ||
        !current_user_can('edit_post', $id)
    ) {
        return;
    }
    update_post_meta(
        $id,
        '_et_nl_label',
        mb_substr(sanitize_text_field(wp_unslash((string) ($_POST['et_nl_label'] ?? ''))), 0, 60)
    );
    $color = sanitize_key(wp_unslash((string) ($_POST['et_nl_color'] ?? '')));
    update_post_meta($id, '_et_nl_color', isset(colors()[$color]) ? $color : 'rose');
    update_post_meta(
        $id,
        '_et_nl_preheader',
        mb_substr(sanitize_text_field(wp_unslash((string) ($_POST['et_nl_preheader'] ?? ''))), 0, 150)
    );
});

/* ---------- Prüfungen des Systems ---------- */

/**
 * What the system checks itself before an issue can be ready. Each entry: [ok, text].
 *
 * @return array<string, array{0: bool, 1: string}>
 */
function system_checks($id) {
    $post = get_post((int) $id);
    $blocks = $post ? content_blocks($post->post_content) : [];
    $problems = $blocks ? block_problems($blocks) : [];
    $tested = (string) get_post_meta((int) $id, '_et_nl_test_hash', true) === content_hash($id);
    $open = $post ? open_placeholders($post->post_content) : [];
    return [
        'subject' => [$post && trim($post->post_title) !== '', 'Betreff ist ausgefüllt'],
        'label' => [label($id) !== '', 'Ausgabe ist benannt (z. B. „Nr. 1 · Dezember 2026“)'],
        'preheader' => [preheader($id) !== '', 'Vorschautext ist ausgefüllt'],
        'content' => [
            $blocks && !$problems,
            $problems
                ? implode(' ', $problems)
                : ($blocks
                    ? 'Inhalt nutzt nur erlaubte Bausteine, jedes Bild hat einen Alternativtext, Termine und Zahlen sind vollständig'
                    : 'Inhalt fehlt')
        ],
        'placeholders' => [
            !$open,
            $open
                ? 'Noch offene Platzhalter: ' .
                    implode(', ', array_slice($open, 0, 3)) .
                    (count($open) > 3 ? ' und ' . (count($open) - 3) . ' weitere' : '')
                : 'Keine offenen Platzhalter […] im Text'
        ],
        'tested' => [$tested, 'Testmail der aktuellen Fassung verschickt'],
        'unsubscribe' => [true, 'Abmeldelink und List-Unsubscribe stehen in jeder Mail (setzt das System)']
    ];
}
function system_ok($id) {
    foreach (system_checks($id) as [$ok]) {
        if (!$ok) {
            return false;
        }
    }
    return true;
}

/* ---------- Zustandswechsel ---------- */

function set_state($id, $state) {
    update_post_meta((int) $id, '_et_nl_state', $state);
}
function log_issue($id, $before, $after, $reason) {
    $post = get_post((int) $id);
    log_change(
        0,
        'newsletter_issue',
        $before,
        $after,
        'Newsletter „' . ($post ? $post->post_title : '#' . (int) $id) . '“ (#' . (int) $id . '): ' . $reason
    );
}

/** Stores ticked checklist items. Returns true when every item is ticked. */
function save_checks($id, array $ticked) {
    $ticked = array_values(array_intersect(array_keys(checklist()), $ticked));
    update_post_meta((int) $id, '_et_nl_checks', $ticked);
    return count($ticked) === count(checklist());
}

/** Draft → ready: all checklist items ticked and all system checks passed. */
function mark_ready($id, array $ticked) {
    if (!issue($id) || state($id) !== 'draft' || !can_write()) {
        return 'Diese Ausgabe kann gerade nicht als bereit gemeldet werden.';
    }
    if (!save_checks($id, $ticked)) {
        return 'Bitte alle Punkte der Prüfliste abhaken.';
    }
    if (!system_ok($id)) {
        return 'Noch nicht alles erledigt: Bitte die Punkte mit Kreuz in der Prüfliste ansehen.';
    }
    set_state($id, 'ready');
    update_post_meta((int) $id, '_et_nl_ready', ['by' => get_current_user_id(), 'at' => time()]);
    log_issue($id, 'Entwurf', 'Bereit', 'als bereit gemeldet');
    return '';
}

/** Ready → draft (back to the editorial team). */
function back_to_draft($id) {
    if (!issue($id) || state($id) !== 'ready' || !can_write()) {
        return 'Diese Ausgabe ist nicht im Stand „Bereit“.';
    }
    set_state($id, 'draft');
    log_issue($id, 'Bereit', 'Entwurf', 'zurück an die Redaktion');
    return '';
}

/**
 * Ready → approved, by the board, never by the person who changed the content last. Freezes the content and
 * fills the queue. $expected is the number of recipients the approver saw; if it changed, nothing happens.
 */
function approve($id, $expected) {
    if (!issue($id) || state($id) !== 'ready') {
        return 'Nur Ausgaben im Stand „Bereit“ können freigegeben werden.';
    }
    if (!can_approve()) {
        return 'Freigeben kann nur der Vorstand.';
    }
    if (last_editor($id) === get_current_user_id()) {
        return 'Du hast den Inhalt zuletzt geändert. Freigeben muss eine andere Person aus dem Vorstand (Vier-Augen-Prinzip).';
    }
    if (!system_ok($id)) {
        return 'Die Prüfungen des Systems sind nicht alle erfüllt. Bitte zurück an die Redaktion geben.';
    }
    $recipients = \Eintrikot\Community\newsletter_recipient_ids();
    if (!$recipients) {
        return 'Es gibt noch keine Mitglieder mit Einwilligung.';
    }
    if (count($recipients) !== (int) $expected) {
        return 'Die Zahl der Empfänger hat sich gerade geändert: jetzt ' .
            count($recipients) .
            '. Bitte noch einmal ansehen und bestätigen.';
    }
    update_post_meta((int) $id, '_et_nl_frozen', freeze($id));
    update_post_meta((int) $id, '_et_nl_approval', [
        'by' => get_current_user_id(),
        'at' => time(),
        'recipients' => count($recipients),
        'hash' => content_hash($id)
    ]);
    queue_fill($id, array_keys($recipients));
    set_state($id, 'approved');
    log_issue(
        $id,
        'Bereit',
        'Freigegeben',
        'freigegeben für ' . count($recipients) . ' Empfänger, Versand gestartet'
    );
    return '';
}

/** Approved → ready, e.g. after a change of the content before the first mail went out. */
function withdraw_approval($id, $reason) {
    queue_clear_waiting($id);
    delete_post_meta((int) $id, '_et_nl_frozen');
    delete_post_meta((int) $id, '_et_nl_approval');
    set_state($id, 'ready');
    log_issue($id, 'Freigegeben', 'Bereit', $reason . ', Freigabe zurückgenommen');
}

/** Approved or sending → cancelled. Mails already sent stay sent; the rest waits for a restart. */
function cancel($id) {
    if (!issue($id) || !in_array(state($id), ['approved', 'sending'], true) || !can_approve()) {
        return 'Abbrechen kann nur der Vorstand, solange der Versand läuft.';
    }
    $before = state_label($id);
    set_state($id, 'cancelled');
    log_issue(
        $id,
        $before,
        'Abgebrochen',
        'Versand abgebrochen, ' . queue_counts($id)['sent'] . ' bereits gesendet'
    );
    return '';
}

/** Cancelled → sending: continues with those still waiting. Nobody gets the issue twice. */
function resume($id) {
    if (!issue($id) || state($id) !== 'cancelled' || !can_approve()) {
        return 'Fortsetzen geht nur bei einem abgebrochenen Versand.';
    }
    set_state($id, 'sending');
    log_issue($id, 'Abgebrochen', 'Wird versendet', 'Versand fortgesetzt');
    return '';
}

/** After sending: the editorial team confirms that bounces in the sender's mailbox were checked. */
function confirm_bounces($id) {
    if (!issue($id) || state($id) !== 'sent' || !can_write()) {
        return 'Erst nach dem Versand möglich.';
    }
    update_post_meta((int) $id, '_et_nl_bounces', ['by' => get_current_user_id(), 'at' => time()]);
    return '';
}
