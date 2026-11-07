<?php
/**
 * Zweite Anmeldestufe für Konten mit erweiterten Rechten (Vorstand, Redaktion, Admins).
 *
 * Nach dem richtigen Passwort setzt WordPress noch kein Anmelde-Cookie: die Anmeldeseite fragt nach dem
 * sechsstelligen Code aus einer Authenticator-App (TOTP, RFC 6238). Wer noch keinen Code eingerichtet
 * hat, richtet ihn beim ersten Mal direkt dort ein und bekommt zehn Wiederherstellungscodes.
 *
 * Notfall: define('EINTRIKOT_2FA_OFF', true); in wp-config.php schaltet die Stufe ab.
 */
namespace Eintrikot\Community;

if (!defined('ABSPATH')) {
    exit();
}

const TOTP_STEP = 30;
const TOTP_DIGITS = 6;
const TWOFA_MINUTES = 10;
const TWOFA_MAX_TRIES = 5;
const TWOFA_RECOVERY_CODES = 10;

/* ---------- TOTP ---------- */

function base32_encode($bytes) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($bytes) as $char) {
        $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 5) as $chunk) {
        $out .= $alphabet[bindec(str_pad($chunk, 5, '0'))];
    }
    return $out;
}

function base32_decode($text) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split(strtoupper(preg_replace('/[^A-Za-z2-7]/', '', (string) $text))) as $char) {
        $bits .= str_pad(decbin(strpos($alphabet, $char)), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $out .= chr(bindec($byte));
        }
    }
    return $out;
}

function totp_code($secret, $counter) {
    $hash = hash_hmac('sha1', pack('J', $counter), base32_decode($secret), true);
    $offset = ord($hash[19]) & 0x0f;
    $value = unpack('N', substr($hash, $offset, 4))[1] & 0x7fffffff;
    return str_pad((string) ($value % 10 ** TOTP_DIGITS), TOTP_DIGITS, '0', STR_PAD_LEFT);
}

/** Accepts the current code and one step before and after (clock drift). Returns the step or false. */
function totp_verify($secret, $code, $time = null) {
    $code = preg_replace('/\D/', '', (string) $code);
    if (strlen($code) !== TOTP_DIGITS || $secret === '') {
        return false;
    }
    $now = intdiv($time ?? time(), TOTP_STEP);
    foreach ([0, -1, 1] as $drift) {
        if (hash_equals(totp_code($secret, $now + $drift), $code)) {
            return $now + $drift;
        }
    }
    return false;
}

/* ---------- Wer braucht die zweite Stufe ---------- */

function twofa_required($user_id) {
    return !(defined('EINTRIKOT_2FA_OFF') && EINTRIKOT_2FA_OFF) && is_staff_account($user_id);
}

function twofa_secret($user_id) {
    return (string) get_user_meta($user_id, 'et_totp_secret', true);
}

/** Letters and digits that cannot be mistaken for each other (no 0/o, 1/l/i). */
function twofa_random_chars($length) {
    $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $out;
}

/** One-time recovery codes; only their hashes are stored. */
function twofa_new_recovery_codes($user_id) {
    $codes = [];
    for ($i = 0; $i < TWOFA_RECOVERY_CODES; $i++) {
        $codes[] = twofa_random_chars(5) . '-' . twofa_random_chars(5);
    }
    update_user_meta($user_id, 'et_totp_recovery', array_map('wp_hash_password', $codes));
    return $codes;
}

function twofa_use_recovery_code($user_id, $code) {
    $code = strtolower(trim((string) $code));
    $hashes = (array) get_user_meta($user_id, 'et_totp_recovery', true);
    foreach ($hashes as $i => $hash) {
        if ($code !== '' && wp_check_password($code, (string) $hash)) {
            unset($hashes[$i]);
            update_user_meta($user_id, 'et_totp_recovery', array_values($hashes));
            return true;
        }
    }
    return false;
}

/* ---------- Anmeldung abfangen ---------- */

/** The pending sign-in lives on the server; the browser only holds a random key in a hidden field. */
function twofa_start($user, $remember, $redirect) {
    $key = wp_generate_password(32, false);
    $setup = twofa_secret($user->ID) === '' ? base32_encode(random_bytes(20)) : '';
    set_transient(
        'et_2fa_' . hash('sha256', $key),
        [
            'user' => $user->ID,
            'remember' => $remember,
            'redirect' => $redirect,
            'tries' => 0,
            'setup' => $setup
        ],
        TWOFA_MINUTES * MINUTE_IN_SECONDS
    );
    return $key;
}

function twofa_pending($key) {
    $state = is_string($key) && $key !== '' ? get_transient('et_2fa_' . hash('sha256', $key)) : false;
    return is_array($state) ? $state : null;
}

// Runs after the password check and the lock (priority 99). Only the sign-in form itself is intercepted,
// so tests and other callers of wp_authenticate() are not affected.
add_filter(
    'authenticate',
    function ($user) {
        if (
            !($user instanceof \WP_User) ||
            ($GLOBALS['pagenow'] ?? '') !== 'wp-login.php' ||
            sanitize_key(wp_unslash($_SERVER['REQUEST_METHOD'] ?? '')) !== 'post' ||
            !twofa_required($user->ID)
        ) {
            return $user;
        }
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- the WordPress sign-in form has no nonce; the password was just checked
        $remember = !empty($_POST['rememberme']);
        $redirect =
            isset($_POST['redirect_to']) && is_string($_POST['redirect_to'])
                ? esc_url_raw(wp_unslash($_POST['redirect_to']))
                : '';
        // phpcs:enable
        twofa_page(twofa_start($user, $remember, $redirect));
        exit();
    },
    100
);

add_action('login_form_et_2fa', function () {
    // phpcs:disable WordPress.Security.NonceVerification.Missing -- the random key of the pending sign-in serves as the nonce
    $key =
        isset($_POST['et_2fa_key']) && is_string($_POST['et_2fa_key'])
            ? sanitize_text_field(wp_unslash($_POST['et_2fa_key']))
            : '';
    $code =
        isset($_POST['et_2fa_code']) && is_string($_POST['et_2fa_code'])
            ? sanitize_text_field(wp_unslash($_POST['et_2fa_code']))
            : '';
    // phpcs:enable
    $state = twofa_pending($key);
    if (!$state || (int) get_transient(login_ip_key()) >= LOGIN_IP_MAX_FAILS) {
        wp_safe_redirect(wp_login_url());
        exit();
    }
    $user_id = (int) $state['user'];
    $setup = (string) $state['setup'];
    $secret = $setup !== '' ? $setup : twofa_secret($user_id);
    $step = totp_verify($secret, $code);
    $ok =
        $step !== false && $step > (int) get_user_meta($user_id, 'et_totp_last_step', true)
            ? true
            : $setup === '' && twofa_use_recovery_code($user_id, $code);
    if (!$ok) {
        $state['tries']++;
        set_transient(
            login_ip_key(),
            (int) get_transient(login_ip_key()) + 1,
            LOGIN_LOCK_MINUTES * MINUTE_IN_SECONDS
        );
        if ($state['tries'] >= TWOFA_MAX_TRIES) {
            delete_transient('et_2fa_' . hash('sha256', $key));
            wp_safe_redirect(wp_login_url());
            exit();
        }
        set_transient('et_2fa_' . hash('sha256', $key), $state, TWOFA_MINUTES * MINUTE_IN_SECONDS);
        twofa_page($key, 'Der Code stimmt nicht. Bitte den aktuellen Code aus der App eingeben.');
        exit();
    }
    delete_transient('et_2fa_' . hash('sha256', $key));
    if ($step !== false) {
        // A code works only once, even within its 30 seconds.
        update_user_meta($user_id, 'et_totp_last_step', $step);
    }
    $codes = [];
    if ($setup !== '') {
        update_user_meta($user_id, 'et_totp_secret', $setup);
        $codes = twofa_new_recovery_codes($user_id);
    }
    $user = get_user_by('id', $user_id);
    wp_set_auth_cookie($user_id, (bool) $state['remember'], is_ssl());
    // WordPress core hooks: the sign-in counts as a normal one (lock reset, session list, login redirect).
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
    do_action('wp_login', $user->user_login, $user);
    $target = apply_filters(
        'login_redirect', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        $state['redirect'] !== '' ? $state['redirect'] : admin_url(),
        $state['redirect'],
        $user
    );
    if ($codes) {
        twofa_codes_page($codes, $target);
        exit();
    }
    wp_safe_redirect($target);
    exit();
});

/* ---------- Seiten ---------- */

function twofa_page($key, $error = '') {
    $state = twofa_pending($key);
    $setup = $state ? (string) $state['setup'] : '';
    $user = $state ? get_user_by('id', (int) $state['user']) : null;
    nocache_headers();
    login_header('Zweite Anmeldestufe', '', $error !== '' ? new \WP_Error('et_2fa', $error) : null);
    echo '<form name="et2faform" id="loginform" action="' .
        esc_url(site_url('wp-login.php?action=et_2fa', 'login_post')) .
        '" method="post">';
    if ($setup !== '' && $user) {
        $uri =
            'otpauth://totp/' .
            rawurlencode('EINTRIKOT:' . $user->user_email) .
            '?secret=' .
            $setup .
            '&issuer=EINTRIKOT&digits=' .
            TOTP_DIGITS .
            '&period=' .
            TOTP_STEP;
        echo '<p><strong>Einmalig einrichten.</strong> Dein Konto hat erweiterte Rechte und braucht deshalb bei jeder Anmeldung einen Code aus einer Authenticator-App (zum Beispiel Passwörter auf dem iPhone, Google Authenticator oder 1Password).</p>' .
            '<p><a href="' .
            esc_attr($uri) .
            '">Auf diesem Handy in der App öffnen</a> oder den Schlüssel von Hand eintragen:</p>' .
            '<p><code style="font-size:1rem;word-break:break-all">' .
            esc_html(trim(chunk_split($setup, 4, ' '))) .
            '</code></p>';
    } else {
        echo '<p>Gib den sechsstelligen Code aus deiner Authenticator-App ein. Ohne Handy geht auch ein Wiederherstellungscode.</p>';
    }
    echo '<p><label for="et_2fa_code">Code</label><input type="text" name="et_2fa_code" id="et_2fa_code" class="input" inputmode="numeric" autocomplete="one-time-code" autocapitalize="none" spellcheck="false" required autofocus></p>' .
        '<input type="hidden" name="et_2fa_key" value="' .
        esc_attr($key) .
        '"><p class="submit"><input type="submit" class="button button-primary button-large" value="' .
        esc_attr($setup !== '' ? 'Einrichten und anmelden' : 'Anmelden') .
        '"></p></form>';
    login_footer();
}

function twofa_codes_page($codes, $target) {
    nocache_headers();
    login_header('Wiederherstellungscodes');
    echo '<div id="loginform"><p><strong>Eingerichtet.</strong> Bewahre diese Wiederherstellungscodes sicher auf, etwa im Passwort-Manager. Jeder Code gilt einmal, falls das Handy fehlt. Sie werden nur jetzt angezeigt.</p><ul style="font-family:monospace;font-size:1rem;line-height:1.8;list-style:none;padding:0">';
    foreach ($codes as $code) {
        echo '<li>' . esc_html($code) . '</li>';
    }
    echo '</ul><p class="submit"><a class="button button-primary button-large" href="' .
        esc_url(wp_validate_redirect($target, admin_url())) .
        '">Weiter</a></p></div>';
    login_footer();
}

/* ---------- Zurücksetzen durch Admins ---------- */

// Lost phone and recovery codes: an admin resets the second step on the profile in the back end,
// the person sets it up again at the next sign-in.
add_action('edit_user_profile', function ($user) {
    if (!current_user_can('manage_options') || !is_staff_account($user->ID)) {
        return;
    }
    echo '<h2>Zweite Anmeldestufe</h2><table class="form-table"><tr><th>Status</th><td>' .
        (twofa_secret($user->ID) !== '' ? 'Eingerichtet.' : 'Noch nicht eingerichtet.') .
        '<br><label><input type="checkbox" name="et_2fa_reset" value="1"> Zurücksetzen (bei der nächsten Anmeldung neu einrichten)</label></td></tr></table>';
});
add_action('edit_user_profile_update', function ($user_id) {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress checks the update-user nonce before this action
    if (current_user_can('manage_options') && !empty($_POST['et_2fa_reset'])) {
        delete_user_meta($user_id, 'et_totp_secret');
        delete_user_meta($user_id, 'et_totp_recovery');
        delete_user_meta($user_id, 'et_totp_last_step');
    }
});
