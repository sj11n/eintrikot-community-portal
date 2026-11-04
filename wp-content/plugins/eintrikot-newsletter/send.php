<?php
/**
 * Warteschlange: eine Zeile je Ausgabe und Empfänger (Nutzer-ID, keine Adresse, kein Inhalt).
 * Zustände: waiting (wartet), sending (wird gerade zugestellt), sent (gesendet), error (Fehler), skipped (keine
 * Einwilligung mehr). WP-Cron arbeitet jede Minute ein Paket ab (Standard 20). Jede Zeile wird vor dem Versand
 * belegt; so bekommt niemand eine Ausgabe doppelt, auch wenn der Server mitten im Paket stoppt. Eine Zeile, die
 * dabei hängen bleibt, wird als Fehler markiert und nicht erneut versendet (Zustellung unklar).
 */

namespace Eintrikot\Newsletter;

if (!defined('ABSPATH')) {
    exit();
}

const MAX_ATTEMPTS = 3;

function table() {
    global $wpdb;
    return $wpdb->prefix . 'eintrikot_newsletter_send';
}

function install() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta(
        'CREATE TABLE ' .
            table() .
            " (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        issue_id bigint(20) unsigned NOT NULL,
        user_id bigint(20) unsigned NOT NULL,
        status varchar(10) NOT NULL DEFAULT 'waiting',
        attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
        updated_at datetime NOT NULL,
        error varchar(255) NOT NULL DEFAULT '',
        PRIMARY KEY  (id),
        UNIQUE KEY issue_user (issue_id,user_id),
        KEY issue_status (issue_id,status)
    ) " .
            $wpdb->get_charset_collate() .
            ';'
    );
    update_option('eintrikot_newsletter_schema', SCHEMA, false);
}

/* ---------- Zeitplan ---------- */

add_filter('cron_schedules', function ($schedules) {
    $schedules['eintrikot_minute'] = ['interval' => 60, 'display' => 'Jede Minute (EINTRIKOT-Newsletter)'];
    return $schedules;
});
function schedule() {
    if (!wp_next_scheduled('eintrikot_newsletter_tick')) {
        wp_schedule_event(time() + 60, 'eintrikot_minute', 'eintrikot_newsletter_tick');
    }
    if (!wp_next_scheduled('eintrikot_newsletter_cleanup')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'eintrikot_newsletter_cleanup');
    }
}
add_action('eintrikot_newsletter_tick', __NAMESPACE__ . '\tick');
add_action('eintrikot_newsletter_cleanup', __NAMESPACE__ . '\cleanup');

/* ---------- Warteschlange ---------- */

function now_gmt() {
    return current_time('mysql', true);
}

/** One waiting row per recipient. Rows that already exist (e.g. after a restart) stay as they are. */
function queue_fill($id, array $user_ids) {
    global $wpdb;
    foreach ($user_ids as $user_id) {
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}eintrikot_newsletter_send WHERE issue_id = %d AND user_id = %d",
                (int) $id,
                (int) $user_id
            )
        );
        if (!$exists) {
            $wpdb->insert(
                table(),
                [
                    'issue_id' => (int) $id,
                    'user_id' => (int) $user_id,
                    'status' => 'waiting',
                    'attempts' => 0,
                    'updated_at' => now_gmt(),
                    'error' => ''
                ],
                ['%d', '%d', '%s', '%d', '%s', '%s']
            );
        }
    }
}
function queue_clear_waiting($id) {
    global $wpdb;
    $wpdb->delete(table(), ['issue_id' => (int) $id, 'status' => 'waiting'], ['%d', '%s']);
}

/** @return array{waiting: int, sending: int, sent: int, error: int, skipped: int, total: int} */
function queue_counts($id) {
    global $wpdb;
    $counts = ['waiting' => 0, 'sending' => 0, 'sent' => 0, 'error' => 0, 'skipped' => 0];
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT status, COUNT(*) AS n FROM {$wpdb->prefix}eintrikot_newsletter_send WHERE issue_id = %d GROUP BY status",
            (int) $id
        )
    );
    foreach ($rows as $row) {
        if (isset($counts[$row->status])) {
            $counts[$row->status] = (int) $row->n;
        }
    }
    return $counts + ['total' => array_sum($counts)];
}

/** @return array<int, object{user_id: string, attempts: string, error: string}> */
function queue_errors($id) {
    global $wpdb;
    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT user_id, attempts, error FROM {$wpdb->prefix}eintrikot_newsletter_send WHERE issue_id = %d AND status = 'error' ORDER BY id",
            (int) $id
        )
    );
}

/** Sets the row's state, but only if it is still in $from. Returns true if this call changed it. */
function claim($row_id, $from, $to, $attempts = null, $error = null) {
    global $wpdb;
    $data = ['status' => $to, 'updated_at' => now_gmt()];
    $formats = ['%s', '%s'];
    if ($attempts !== null) {
        $data['attempts'] = (int) $attempts;
        $formats[] = '%d';
    }
    if ($error !== null) {
        $data['error'] = mb_substr((string) $error, 0, 250);
        $formats[] = '%s';
    }
    return (int) $wpdb->update(table(), $data, ['id' => (int) $row_id, 'status' => $from], $formats, [
        '%d',
        '%s'
    ]) === 1;
}

/** Signed one-click unsubscribe link for one member and issue (user 0: test mail, changes nothing). */
function unsubscribe_url($user_id, $id) {
    return add_query_arg(
        'et_abmelden',
        (int) $user_id . '-' . (int) $id . '-' . unsubscribe_signature($user_id, $id),
        home_url('/')
    );
}

/* ---------- Ein Durchlauf ---------- */

/** Only one run at a time; a lock older than five minutes counts as left over from a stopped run. */
function lock() {
    if (add_option('eintrikot_newsletter_lock', time(), '', false)) {
        return true;
    }
    if ((int) get_option('eintrikot_newsletter_lock') < time() - 5 * MINUTE_IN_SECONDS) {
        update_option('eintrikot_newsletter_lock', time(), false);
        return true;
    }
    return false;
}
function unlock() {
    delete_option('eintrikot_newsletter_lock');
}

/** Approved or running issues, oldest approval first. @return int[] */
function running_issues() {
    $ids = get_posts([
        'post_type' => POST_TYPE,
        'post_status' => 'any',
        'numberposts' => -1,
        'fields' => 'ids',
        'meta_query' => [['key' => '_et_nl_state', 'value' => ['approved', 'sending'], 'compare' => 'IN']]
    ]);
    usort($ids, static function ($a, $b) {
        return ((int) (get_post_meta($a, '_et_nl_approval', true)['at'] ?? 0)) <=>
            ((int) (get_post_meta($b, '_et_nl_approval', true)['at'] ?? 0));
    });
    return array_map('intval', $ids);
}

/**
 * Sends the next batch. Returns the number of mails handed to wp_mail in this run.
 */
function tick($limit = null) {
    global $wpdb;
    if (!core_ready() || !lock()) {
        return 0;
    }
    $limit = $limit === null ? settings()['batch'] : max(1, (int) $limit);
    $handled = 0;
    try {
        // Rows left in "sending" by a stopped run: delivery is unclear, so never send them again.
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}eintrikot_newsletter_send SET status = 'error', error = %s WHERE status = 'sending' AND updated_at < %s",
                'Versand unterbrochen, Zustellung unklar. Nicht erneut gesendet.',
                gmdate('Y-m-d H:i:s', time() - 10 * MINUTE_IN_SECONDS)
            )
        );
        foreach (running_issues() as $id) {
            if ($handled >= $limit) {
                break;
            }
            $data = frozen($id);
            if (!$data) {
                continue;
            }
            if (state($id) === 'approved') {
                set_state($id, 'sending');
                update_post_meta($id, '_et_nl_started_at', time());
            }
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, user_id, attempts FROM {$wpdb->prefix}eintrikot_newsletter_send WHERE issue_id = %d AND status = 'waiting' ORDER BY id LIMIT %d",
                    $id,
                    $limit - $handled
                )
            );
            foreach ($rows as $row) {
                // Cancelled in the meantime (another request): stop at once.
                wp_cache_delete($id, 'post_meta');
                if (state($id) !== 'sending') {
                    break 2;
                }
                if (!claim($row->id, 'waiting', 'sending')) {
                    continue;
                }
                $user_id = (int) $row->user_id;
                // The consent is checked again before every single mail: an unsubscribe applies at once.
                clean_user_cache($user_id);
                if (!\Eintrikot\Community\newsletter_receives($user_id)) {
                    claim(
                        $row->id,
                        'sending',
                        'skipped',
                        null,
                        'Keine Einwilligung mehr oder keine gültige Adresse'
                    );
                    continue;
                }
                $user = get_userdata($user_id);
                $unsubscribe = unsubscribe_url($user_id, $id);
                $ctx = [
                    'name' => \Eintrikot\Community\first_name($user->display_name),
                    'unsubscribe' => $unsubscribe
                ];
                $handled++;
                if (
                    send_one(
                        $user->user_email,
                        $data['subject'],
                        render_mail($data, $ctx),
                        render_mail_text($data, $ctx),
                        $unsubscribe
                    )
                ) {
                    claim($row->id, 'sending', 'sent', (int) $row->attempts + 1, '');
                    continue;
                }
                $attempts = (int) $row->attempts + 1;
                claim(
                    $row->id,
                    'sending',
                    $attempts >= MAX_ATTEMPTS ? 'error' : 'waiting',
                    $attempts,
                    last_mail_error() ?: 'Mail wurde nicht angenommen'
                );
            }
            finish_if_done($id);
        }
    } finally {
        unlock();
    }
    return $handled;
}

/** Nothing waiting any more: the issue is sent and appears in the archive. */
function finish_if_done($id) {
    if (state($id) !== 'sending') {
        return;
    }
    $c = queue_counts($id);
    if ($c['waiting'] + $c['sending'] > 0) {
        return;
    }
    set_state($id, 'sent');
    update_post_meta((int) $id, '_et_nl_sent', [
        'at' => time(),
        'sent' => $c['sent'],
        'error' => $c['error'],
        'skipped' => $c['skipped']
    ]);
    log_issue(
        $id,
        'Wird versendet',
        'Versendet',
        'Versand beendet: ' .
            $c['sent'] .
            ' gesendet, ' .
            $c['error'] .
            ' Fehler, ' .
            $c['skipped'] .
            ' ohne Einwilligung übersprungen'
    );
}

/* ---------- Aufräumen ---------- */

/** Send logs are deleted after 12 months (docs/13, decided by B on 04.11.2026). */
function retention_days() {
    return max(30, (int) apply_filters('eintrikot_newsletter_log_days', 365));
}
function cleanup() {
    global $wpdb;
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}eintrikot_newsletter_send WHERE status IN ('sent', 'error', 'skipped') AND updated_at < %s",
            gmdate('Y-m-d H:i:s', time() - retention_days() * DAY_IN_SECONDS)
        )
    );
}

/** A deleted account takes its send log with it (the consent goes with the user meta). */
add_action('deleted_user', function ($user_id) {
    global $wpdb;
    $wpdb->delete(table(), ['user_id' => (int) $user_id], ['%d']);
});
