#!/usr/bin/env bash
# Lokales WordPress zum Testen: SQLite statt MySQL, Testmitglieder, ausgehende Mails werden abgefangen.
# Alles liegt in .lokal/ (nicht im Repository, nichts davon wird eingespielt).
#
#   tools/lokal-test.sh setup   WordPress und SQLite-Plugin laden, installieren, Plugin und Theme einbinden
#   tools/lokal-test.sh test    Regeltests (tests/run.php) ausführen
#   tools/lokal-test.sh serve   Seite unter http://127.0.0.1:8899 starten (Strg+C beendet)
#   tools/lokal-test.sh reset   .lokal/ löschen (der Download-Cache bleibt)
#
# Testkonten für serve: admin / AdminTestPw-9x!, mia.test und vera.vorstand / TestPw-Member-9x!
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LOKAL="$ROOT/.lokal"
CACHE="$LOKAL/cache"
WP="$LOKAL/wordpress"
PORT="${PORT:-8899}"

setup() {
    mkdir -p "$CACHE"
    if [[ ! -f "$CACHE/wordpress.zip" ]]; then
        echo "== WordPress laden"
        curl -fsSL -o "$CACHE/wordpress.zip" https://wordpress.org/latest.zip
    fi
    if [[ ! -f "$CACHE/sqlite.zip" ]]; then
        echo "== SQLite-Plugin laden"
        curl -fsSL -o "$CACHE/sqlite.zip" https://downloads.wordpress.org/plugin/sqlite-database-integration.latest-stable.zip
    fi
    if [[ -f "$WP/wp-config.php" ]]; then
        echo "Schon eingerichtet ($WP). Zum Neuaufbau: tools/lokal-test.sh reset"
        return
    fi
    rm -rf "$WP"
    unzip -q "$CACHE/wordpress.zip" -d "$LOKAL"
    unzip -q "$CACHE/sqlite.zip" -d "$WP/wp-content/plugins/"
    local plugin="$WP/wp-content/plugins/sqlite-database-integration"
    sed "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$plugin#; s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#" \
        "$plugin/db.copy" >"$WP/wp-content/db.php"
    mkdir -p "$WP/wp-content/database" "$WP/wp-content/mu-plugins"
    cat >"$WP/wp-config.php" <<PHP
<?php
define('DB_NAME', 'wp'); define('DB_USER', ''); define('DB_PASSWORD', ''); define('DB_HOST', ''); define('DB_CHARSET', 'utf8'); define('DB_COLLATE', '');
define('AUTH_KEY', 'a'); define('SECURE_AUTH_KEY', 'b'); define('LOGGED_IN_KEY', 'c'); define('NONCE_KEY', 'd');
define('AUTH_SALT', 'e'); define('SECURE_AUTH_SALT', 'f'); define('LOGGED_IN_SALT', 'g'); define('NONCE_SALT', 'h');
\$table_prefix = 'wp_';
define('WP_DEBUG', true); define('WP_DEBUG_LOG', '$LOKAL/debug.log'); define('WP_DEBUG_DISPLAY', false);
define('WP_HOME', 'http://127.0.0.1:$PORT'); define('WP_SITEURL', 'http://127.0.0.1:$PORT');
define('DISABLE_WP_CRON', true);
if (!defined('ABSPATH')) { define('ABSPATH', __DIR__ . '/'); }
require_once ABSPATH . 'wp-settings.php';
PHP
    cat >"$WP/wp-content/mu-plugins/mailcatch.php" <<PHP
<?php
// Mails nie versenden, nur mitschreiben.
add_filter('pre_wp_mail', function (\$short, \$args) {
    \$to = is_array(\$args['to']) ? implode(',', \$args['to']) : \$args['to'];
    file_put_contents('$LOKAL/mails.log', "TO: \$to\nSUBJ: {\$args['subject']}\n\n", FILE_APPEND);
    return true;
}, 10, 2);
PHP
    ln -sfn "$ROOT/wp-content/plugins/eintrikot-community-core" "$WP/wp-content/plugins/eintrikot-community-core"
    ln -sfn "$ROOT/wp-content/themes/eintrikot-community" "$WP/wp-content/themes/eintrikot-community"
    cat >"$LOKAL/einrichten.php" <<PHP
<?php
define('WP_INSTALLING', true);
require '$WP/wp-load.php';
require ABSPATH . 'wp-admin/includes/upgrade.php';
wp_install('Test', 'admin', 'admin@example.test', true, '', 'AdminTestPw-9x!');
PHP
    php "$LOKAL/einrichten.php" >/dev/null
    cat >"$LOKAL/einrichten2.php" <<PHP
<?php
require '$WP/wp-load.php';
switch_theme('eintrikot-community');
activate_plugin('eintrikot-community-core/eintrikot-community-core.php');
update_option('permalink_structure', '/%postname%/');
\$page = \Eintrikot\Community\prepare_portal_page();
wp_update_post(['ID' => \$page, 'post_status' => 'publish']);
foreach ([['mia.test', 'member'], ['vera.vorstand', 'board']] as [\$login, \$role]) {
    \$id = wp_insert_user([
        'user_login' => \$login, 'user_email' => \$login . '@example.test',
        'user_pass' => 'TestPw-Member-9x!', 'role' => 'eintrikot_' . \$role,
        'display_name' => ucfirst(strtok(\$login, '.')) . ' Test',
    ]);
    update_user_meta(\$id, 'eintrikot_activated_at', time());
}
PHP
    php "$LOKAL/einrichten2.php" >/dev/null
    echo "Fertig. Weiter mit: tools/lokal-test.sh test   oder   tools/lokal-test.sh serve"
}

need_setup() {
    [[ -f "$WP/wp-config.php" ]] || setup
}

case "${1:-}" in
    setup) setup ;;
    test)
        need_setup
        rm -f "$LOKAL/debug.log"
        WP_ROOT="$WP" php "$ROOT/tests/run.php"
        # PHP-Hinweise und -Warnungen aus dem Lauf gelten als Fehler.
        if [[ -s "$LOKAL/debug.log" ]]; then
            echo "PHP-Meldungen im Lauf (.lokal/debug.log):"
            cat "$LOKAL/debug.log"
            exit 1
        fi
        ;;
    serve)
        need_setup
        echo "http://127.0.0.1:$PORT  (Strg+C beendet)"
        cat >"$LOKAL/router.php" <<PHP
<?php
\$path = parse_url(\$_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (\$path !== '/' && is_file('$WP' . \$path)) { return false; }
\$_SERVER['SCRIPT_NAME'] = '/index.php';
chdir('$WP');
require '$WP/index.php';
PHP
        cd "$WP" && exec php -S "127.0.0.1:$PORT" -t . "$LOKAL/router.php"
        ;;
    reset)
        find "$LOKAL" -mindepth 1 -maxdepth 1 ! -name cache -exec rm -rf {} +
        echo "Zurückgesetzt."
        ;;
    *)
        sed -n '2,11p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
        exit 1
        ;;
esac
