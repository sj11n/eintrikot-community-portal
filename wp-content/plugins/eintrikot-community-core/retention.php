<?php
/**
 * Speicherfristen (siehe Datenschutzerklärung):
 * - Änderungsprotokoll: Einträge älter als 24 Monate werden gelöscht.
 * - Service-Anfragen: abgeschlossene oder abgelehnte Anfragen 24 Monate nach der letzten Bearbeitung.
 * Der Nachweis der Elternzustimmung wird in consent.php gelöscht.
 */
namespace Eintrikot\Community;
if (!defined('ABSPATH')) {
    exit();
}

const RETENTION_MONTHS = 24;

add_action('init', function () {
    if (!wp_next_scheduled('eintrikot_retention')) {
        wp_schedule_event(time() + 2 * HOUR_IN_SECONDS, 'daily', 'eintrikot_retention');
    }
});

function run_retention() {
    global $wpdb;
    $cutoff = gmdate('Y-m-d H:i:s', strtotime('-' . RETENTION_MONTHS . ' months'));
    $audit = (int) $wpdb->query(
        $wpdb->prepare("DELETE FROM {$wpdb->prefix}eintrikot_audit WHERE created_at < %s", $cutoff)
    );
    $requests = 0;
    $limit = strtotime('-' . RETENTION_MONTHS . ' months');
    // Only requests created before the cutoff can qualify; the last step decides.
    foreach (
        get_posts([
            'post_type' => 'et_request',
            'post_status' => 'any',
            'numberposts' => 200,
            'fields' => 'ids',
            'date_query' => [['column' => 'post_date_gmt', 'before' => $cutoff]]
        ])
        as $id
    ) {
        $state = request_state($id);
        if (!in_array($state['status'] ?? '', ['adopted', 'rejected'], true)) {
            continue;
        }
        $last = strtotime(get_post_field('post_modified_gmt', $id) . ' UTC');
        foreach (is_array($state['history'] ?? null) ? $state['history'] : [] as $step) {
            $at = is_array($step) ? strtotime((string) ($step['at'] ?? '')) : false;
            $last = $at ? max($last, $at) : $last;
        }
        if ($last < $limit && wp_delete_post($id, true)) {
            $requests++;
        }
    }
    return ['audit' => $audit, 'requests' => $requests];
}
add_action('eintrikot_retention', __NAMESPACE__ . '\run_retention');
