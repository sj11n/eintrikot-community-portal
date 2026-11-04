<?php
/**
 * Abmeldung ohne Login über den persönlichen Link in jeder Mail: /?et_abmelden=<Nutzer>-<Ausgabe>-<Signatur>.
 * Die Signatur ist ein HMAC mit einem Schlüssel aus den WordPress-Salts; der Link enthält kein Passwort.
 *
 * GET zeigt eine Bestätigungsseite (Link-Prüfer von Mailprogrammen rufen Links auf, ohne dass jemand klickt).
 * POST meldet ab: der Knopf auf der Seite und der One-Click-Aufruf nach RFC 8058 (List-Unsubscribe-Post).
 * Andere Methoden und falsche Signaturen werden abgelehnt.
 */

namespace Eintrikot\Newsletter;

if (!defined('ABSPATH')) {
    exit();
}

function unsubscribe_signature($user_id, $id) {
    return substr(
        hash_hmac(
            'sha256',
            'eintrikot-newsletter-unsubscribe|' . (int) $user_id . '|' . (int) $id,
            wp_salt('auth')
        ),
        0,
        32
    );
}

/** @return array{0: int, 1: int}|null [user, issue] for a valid token, null otherwise. */
function unsubscribe_token($token) {
    if (!preg_match('/^(\d{1,20})-(\d{1,20})-([a-f0-9]{32})$/', (string) $token, $m)) {
        return null;
    }
    return hash_equals(unsubscribe_signature((int) $m[1], (int) $m[2]), $m[3])
        ? [(int) $m[1], (int) $m[2]]
        : null;
}

/**
 * Handles one call of the unsubscribe link. Returns [HTTP status, page key].
 * Page keys: confirm, done, test, invalid, method.
 *
 * @return array{0: int, 1: string}
 */
function handle_unsubscribe($method, $token) {
    $parsed = unsubscribe_token($token);
    if (!in_array($method, ['GET', 'HEAD', 'POST'], true)) {
        return [405, 'method'];
    }
    if (!$parsed) {
        return [403, 'invalid'];
    }
    [$user_id, $id] = $parsed;
    if ($user_id === 0) {
        return [200, 'test'];
    }
    if (!get_userdata($user_id)) {
        return [404, 'invalid'];
    }
    if ($method !== 'POST') {
        return [
            200,
            \Eintrikot\Community\newsletter_consent($user_id)['state'] === 'yes' ? 'confirm' : 'done'
        ];
    }
    if (\Eintrikot\Community\newsletter_consent($user_id)['state'] === 'yes') {
        $post = get_post($id);
        \Eintrikot\Community\newsletter_set_consent(
            $user_id,
            'no',
            'Abmeldelink im Newsletter' .
                ($post && $post->post_type === POST_TYPE ? ' „' . $post->post_title . '“' : '')
        );
    }
    return [200, 'done'];
}

function unsubscribe_page($key, $token) {
    $logo = get_theme_file_uri('assets/logo.svg');
    // Theme stylesheet first: font and buttons, as on every public page.
    $theme = get_theme_file_uri('assets/mvp.css');
    $profile = \Eintrikot\Community\portal_url('profile');
    $pages = [
        'confirm' => [
            'Newsletter abbestellen?',
            '<p>Mit einem Klick bekommst du keinen EINTRIKOT-Newsletter mehr. Deine Mitgliedschaft bleibt davon unberührt.</p><form method="post"><input type="hidden" name="et_abmelden" value="' .
            esc_attr($token) .
            '"><p><button class="button solid" type="submit">Ja, abbestellen</button></p></form>'
        ],
        'done' => [
            'Du bist abgemeldet.',
            '<p>Du bekommst keinen EINTRIKOT-Newsletter mehr. Deine Mitgliedschaft bleibt davon unberührt.</p><p>Versehentlich geklickt? Im Profil kannst du den Newsletter jederzeit wieder einschalten.</p><p><a class="button solid" href="' .
            esc_url($profile) .
            '">Zum Profil</a> <a class="text-link" href="' .
            esc_url(home_url('/')) .
            '">Zur Website</a></p>'
        ],
        'test' => [
            'Der Abmeldelink funktioniert.',
            '<p>Das war eine Testmail. Geändert wurde nichts: Im echten Newsletter meldet dieser Link das Mitglied mit einem Klick ab.</p>'
        ],
        'invalid' => [
            'Dieser Link ist ungültig.',
            '<p>Bitte nimm den Link aus der letzten Newsletter-Mail oder ändere die Einstellung direkt in deinem Profil.</p><p><a class="button solid" href="' .
            esc_url($profile) .
            '">Zum Profil</a></p>'
        ],
        'method' => ['Nicht erlaubt.', '<p>Dieser Aufruf wird nicht unterstützt.</p>']
    ];
    [$title, $body] = $pages[$key] ?? $pages['invalid'];
    return '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>' .
        esc_html($title) .
        ' – EINTRIKOT</title><link rel="stylesheet" href="' .
        esc_url($theme) .
        '"><link rel="stylesheet" href="' .
        esc_url(plugins_url('eintrikot-community-core/portal.css')) .
        '"><link rel="stylesheet" href="' .
        esc_url(plugins_url('newsletter.css', __FILE__) . '?v=' . asset_version('newsletter.css')) .
        '"></head><body><div class="et-app nl-solo"><main class="nl-bye"><img src="' .
        esc_url($logo) .
        '" alt="EINTRIKOT" width="158"><h1>' .
        esc_html($title) .
        '</h1>' .
        $body .
        '</main></div></body></html>';
}

add_action(
    'init',
    function () {
        // phpcs:disable WordPress.Security.NonceVerification -- the signed token is the authorisation; there is no login
        $raw = $_POST['et_abmelden'] ?? ($_GET['et_abmelden'] ?? null); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- checked against the pattern and signature
        // phpcs:enable WordPress.Security.NonceVerification
        if (!is_string($raw) || !core_ready()) {
            return;
        }
        $token = sanitize_text_field(wp_unslash($raw));
        $method = strtoupper(sanitize_key(wp_unslash($_SERVER['REQUEST_METHOD'] ?? 'GET')));
        [$status, $key] = handle_unsubscribe($method, $token);
        nocache_headers();
        header('X-Robots-Tag: noindex, nofollow');
        status_header($status);
        if ($status === 405) {
            header('Allow: GET, POST');
        }
        echo unsubscribe_page($key, $token); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the page escapes its parts
        exit();
    },
    1
);
