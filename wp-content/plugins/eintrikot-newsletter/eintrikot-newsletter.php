<?php
/**
 * Plugin Name: EINTRIKOT Newsletter
 * Description: Newsletter-Ausgaben schreiben, prüfen, freigeben und einzeln an Mitglieder mit Einwilligung versenden. Braucht EINTRIKOT Community Core.
 * Version: 0.1.1
 * Requires PHP: 8.1
 * Requires Plugins: eintrikot-community-core
 */

/**
 * Ablauf (docs/13): Redaktion schreibt eine Ausgabe im WordPress-Editor (eingeschränkte Bausteine), schickt Testmails,
 * hakt die Prüfliste ab und meldet sie als bereit. Der Vorstand gibt frei (nie die Person, die zuletzt geändert hat).
 * Mit der Freigabe wird der Inhalt eingefroren und eine Warteschlange je Empfänger angelegt. WP-Cron versendet in
 * Paketen, jede Mail einzeln. Vor jeder Mail wird die Einwilligung erneut geprüft. Danach steht die Ausgabe im
 * Archiv für Mitglieder. Keine Öffnungs- oder Klickverfolgung.
 */

namespace Eintrikot\Newsletter;

if (!defined('ABSPATH')) {
    exit();
}

const VERSION = '0.1.1';
const SCHEMA = '1';

/** The plugin needs the consent functions of the core plugin. */
function core_ready() {
    return function_exists('Eintrikot\Community\newsletter_recipient_ids') &&
        function_exists('Eintrikot\Community\log_change');
}

/**
 * Cache-busting version for a plugin asset: changes whenever the file changes.
 */
function asset_version($file) {
    $path = __DIR__ . '/' . $file;
    return file_exists($path) ? (string) filemtime($path) : VERSION;
}

require_once __DIR__ . '/issue.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/send.php';
require_once __DIR__ . '/unsubscribe.php';
require_once __DIR__ . '/portal.php';

register_activation_hook(__FILE__, __NAMESPACE__ . '\activate');
register_deactivation_hook(__FILE__, __NAMESPACE__ . '\deactivate');

function activate() {
    install();
    schedule();
}
function deactivate() {
    wp_clear_scheduled_hook('eintrikot_newsletter_tick');
    wp_clear_scheduled_hook('eintrikot_newsletter_cleanup');
}

// Table upgrades also run when WordPress replaces an already active plugin.
add_action('init', function () {
    if (get_option('eintrikot_newsletter_schema') !== SCHEMA) {
        install();
    }
    schedule();
});

add_action('admin_notices', function () {
    if (!core_ready() && current_user_can('activate_plugins')) {
        echo '<div class="notice notice-error"><p>EINTRIKOT Newsletter braucht das Plugin „EINTRIKOT Community Core“ (ab Version 0.38.0).</p></div>';
    }
});
