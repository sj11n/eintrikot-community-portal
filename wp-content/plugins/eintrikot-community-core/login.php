<?php
/**
 * Anmeldung und Passwort: angemeldet bleiben, Schutz vor Passwort-Raten,
 * „Passwort vergessen“ mit EINTRIKOT-Mail, Abmelden auf anderen Geräten, Härtung.
 *
 * Grundsätze (OWASP): Passwörter werden nie verschickt, nur einmalige, befristete Links.
 * Meldungen verraten nicht, ob eine E-Mail-Adresse ein Konto hat.
 */
namespace Eintrikot\Community;
if (!defined('ABSPATH')) {
    exit();
}

const REMEMBER_DAYS_MEMBER = 90;
const REMEMBER_DAYS_STAFF = 14;
const LOGIN_MAX_FAILS = 5;
const LOGIN_LOCK_MINUTES = 15;
const RESET_MAX_PER_HOUR = 3;

/** Accounts that see more than their own data stay signed in for a shorter time. */
function is_staff_account($user_id) {
    return user_can($user_id, 'manage_options') ||
        user_can($user_id, 'eintrikot_manage_members') ||
        user_can($user_id, 'edit_posts');
}

/* ---------- Angemeldet bleiben ---------- */

add_filter(
    'auth_cookie_expiration',
    function ($length, $user_id, $remember) {
        if (!$remember) {
            return $length;
        }
        return (is_staff_account($user_id) ? REMEMBER_DAYS_STAFF : REMEMBER_DAYS_MEMBER) * DAY_IN_SECONDS;
    },
    10,
    3
);

// "Angemeldet bleiben" is ticked by default on the WordPress sign-in page as well.
add_action('login_footer', function () {
    echo '<script>(function(){var r=document.getElementById("rememberme");if(r&&!document.getElementById("login_error")){r.checked=true;}})();</script>';
});
add_filter(
    'gettext',
    function ($translation, $text, $domain) {
        if ($domain === 'default' && $text === 'Remember Me' && !is_admin()) {
            return 'Angemeldet bleiben';
        }
        return $translation;
    },
    10,
    3
);

/** Sign out everywhere else: keeps the current session, ends all others. */
add_action('admin_post_et_logout_others', function () {
    if (!is_user_logged_in()) {
        wp_die('Bitte zuerst anmelden.', '', ['response' => 403]);
    }
    check_admin_referer('et_logout_others');
    \WP_Session_Tokens::get_instance(get_current_user_id())->destroy_others(wp_get_session_token());
    log_change(get_current_user_id(), 'sessions', null, 'andere Geräte abgemeldet', 'Sicherheit');
    wp_safe_redirect(portal_url('service', ['sessions' => 'closed']));
    exit();
});

function logout_others_form() {
    $count = count(\WP_Session_Tokens::get_instance(get_current_user_id())->get_all());
    return '<form class="logout-others" method="post" action="' .
        esc_url(admin_url('admin-post.php')) .
        '"><input type="hidden" name="action" value="et_logout_others">' .
        wp_nonce_field('et_logout_others', '_wpnonce', true, false) .
        '<button class="service-link" type="submit"><span><strong>Auf allen anderen Geräten abmelden</strong><small>' .
        esc_html(
            $count > 1
                ? 'Du bist gerade auf ' . $count . ' Geräten oder Browsern angemeldet.'
                : 'Du bist nur auf diesem Gerät angemeldet.'
        ) .
        '</small></span><span aria-hidden="true">→</span></button></form>';
}

/* ---------- Schutz vor Passwort-Raten ---------- */

function login_throttle_key($login) {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    return 'et_login_fail_' . md5(strtolower(trim((string) $login)) . '|' . $ip);
}

// One message for wrong e-mail and wrong password, so nobody can test who is a member.
add_filter(
    'authenticate',
    function ($user, $login = '') {
        // Locked: refuse even a correct password until the lock expires. This runs after
        // WordPress has checked the password, because the core check ignores earlier errors.
        if (
            $login !== '' &&
            $login !== null &&
            (int) get_transient(login_throttle_key($login)) >= LOGIN_MAX_FAILS
        ) {
            return new \WP_Error(
                'et_locked',
                'Zu viele Versuche. Bitte warte ' .
                    LOGIN_LOCK_MINUTES .
                    ' Minuten oder setze dein Passwort über „Passwort vergessen?“ zurück.'
            );
        }
        if (
            is_wp_error($user) &&
            array_intersect($user->get_error_codes(), [
                'invalid_username',
                'invalid_email',
                'incorrect_password'
            ])
        ) {
            return new \WP_Error(
                'et_failed',
                'E-Mail-Adresse oder Passwort stimmen nicht. Nach ' .
                    LOGIN_MAX_FAILS .
                    ' Fehlversuchen ist die Anmeldung ' .
                    LOGIN_LOCK_MINUTES .
                    ' Minuten gesperrt.'
            );
        }
        return $user;
    },
    99,
    2
);
add_action('wp_login_failed', function ($login) {
    $key = login_throttle_key($login);
    set_transient($key, (int) get_transient($key) + 1, LOGIN_LOCK_MINUTES * MINUTE_IN_SECONDS);
});
add_action(
    'wp_login',
    function ($login) {
        delete_transient(login_throttle_key($login));
    },
    10,
    1
);

/* ---------- Passwort vergessen ---------- */

// Unknown address or too many requests: the page looks exactly like a successful request.
add_action(
    'lostpassword_post',
    function ($errors, $user) {
        $input =
            isset($_POST['user_login']) && is_string($_POST['user_login'])
                ? wp_unslash($_POST['user_login'])
                : '';
        if ($input === '') {
            return;
        }
        $limited = false;
        if ($user instanceof \WP_User) {
            $key = 'et_reset_' . $user->ID;
            $count = (int) get_transient($key);
            $limited = $count >= RESET_MAX_PER_HOUR;
            if (!$limited) {
                set_transient($key, $count + 1, HOUR_IN_SECONDS);
            }
        }
        if (!$user || $limited) {
            wp_safe_redirect(add_query_arg('checkemail', 'confirm', wp_login_url()));
            exit();
        }
    },
    10,
    2
);
add_filter('wp_login_errors', function ($errors) {
    if ($errors instanceof \WP_Error && $errors->get_error_code() === 'confirm') {
        $errors->remove('confirm');
        $errors->add(
            'confirm',
            'Wenn diese Adresse bei uns hinterlegt ist, ist eine E-Mail mit einem Link unterwegs. Der Link gilt 24 Stunden und nur einmal. Nichts angekommen? Bitte auch im Spam-Ordner nachsehen.',
            'message'
        );
    }
    return $errors;
});
add_action('lostpassword_form', function () {
    echo '<p class="description" style="margin:0 0 16px">Gib die E-Mail-Adresse ein, mit der du dich anmeldest. Du bekommst einen Link, mit dem du ein neues Passwort festlegst.</p>';
});

/** Shared look of all EINTRIKOT e-mails (the welcome mail uses the same layout). */
/**
 * E-mail frame for all EINTRIKOT mails. Light by default; clients with a dark mode that
 * honour prefers-color-scheme (Apple Mail, iOS, Outlook.com) get a matching dark version with
 * a white logo. Clients that invert colours themselves (Gmail) still show a readable button,
 * because it is light blue with black text rather than black.
 */
function mail_wrap($inner) {
    return '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light dark"><meta name="supported-color-schemes" content="light dark"><style>' .
        ':root{color-scheme:light dark;supported-color-schemes:light dark}' .
        '@media (prefers-color-scheme:dark){' .
        '.et-bg{background:#121416!important}.et-card{background:#1f2427!important}' .
        '.et-text,.et-text p,.et-text h2,.et-text td,.et-text strong,.et-text em{color:#f2f2f2!important}' .
        '.et-muted,.et-muted a{color:#b9c0c4!important}.et-rule{border-color:#3b4247!important}' .
        '.et-logo-light{display:none!important}.et-logo-dark{display:block!important;max-height:none!important}}' .
        '[data-ogsc] .et-logo-light{display:none!important}[data-ogsc] .et-logo-dark{display:block!important;max-height:none!important}' .
        '[data-ogsc] .et-text,[data-ogsc] .et-text p,[data-ogsc] .et-text h2,[data-ogsc] .et-text td{color:#f2f2f2!important}' .
        '</style></head><body class="et-bg" style="margin:0;padding:0;background:#edf5f8">' .
        '<div class="et-bg" style="background:#edf5f8;padding:24px 12px;font-family:Montserrat,Arial,sans-serif;color:#000"><div class="et-card" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden"><div style="height:10px;background:#c8e2ee;background:linear-gradient(90deg,#f1d1dd,#c8e2ee)"></div><div class="et-text" style="padding:28px 28px 8px;color:#000">' .
        '<div style="margin:0 0 28px"><img class="et-logo-light" src="cid:eintrikot-logo" width="165" height="42" alt="EINTRIKOT" style="display:block;border:0;width:165px;height:42px">' .
        '<div class="et-logo-dark" style="display:none;max-height:0;overflow:hidden;mso-hide:all"><img src="cid:eintrikot-logo-dark" width="165" height="42" alt="EINTRIKOT" style="display:block;border:0;width:165px;height:42px"></div></div>' .
        $inner .
        '</div></div></div></body></html>';
}
function mail_p($html) {
    return '<p style="margin:0 0 16px;font-size:16px;line-height:1.6">' . $html . '</p>';
}
/** Small grey note text. */
function mail_note($html) {
    return '<p class="et-muted" style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#595959">' .
        $html .
        '</p>';
}
/** Button as a table cell with a background colour, so every client shows it (also Outlook). */
function mail_button($url, $label) {
    return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:4px 0 24px;border-collapse:separate"><tr><td bgcolor="#c8e2ee" style="background:#c8e2ee;border-radius:999px;border:2px solid #000"><a href="' .
        esc_url($url) .
        '" style="display:inline-block;padding:13px 26px;font-family:Montserrat,Arial,sans-serif;font-size:16px;font-weight:700;line-height:1.2;color:#000000;text-decoration:none;border-radius:999px">' .
        esc_html($label) .
        ' →</a></td></tr></table>';
}
function mail_headers() {
    $s = function_exists(__NAMESPACE__ . '\onboarding_settings') ? onboarding_settings() : [];
    return array_filter([
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . ($s['from_name'] ?? 'EINTRIKOT e.V.') . ' <' . get_option('admin_email') . '>',
        !empty($s['reply_to']) ? 'Reply-To: ' . $s['reply_to'] : ''
    ]);
}
/** Both logo variants travel inside the mail: no external image, so nothing blocked and no tracking. */
function mail_embed_logos($mailer) {
    foreach (
        ['eintrikot-logo' => 'logo-mail.png', 'eintrikot-logo-dark' => 'logo-mail-white.png']
        as $cid => $file
    ) {
        $path = __DIR__ . '/assets/' . $file;
        if (is_readable($path)) {
            $mailer->addEmbeddedImage($path, $cid, $file, 'base64', 'image/png');
        }
    }
}
/** Embeds the logos into the next e-mail that WordPress sends. */
function embed_logo_once() {
    $embed = function ($mailer) use (&$embed) {
        remove_action('phpmailer_init', $embed);
        mail_embed_logos($mailer);
    };
    add_action('phpmailer_init', $embed);
}
function mail_signoff() {
    return mail_p('Fragen? Antworte einfach auf diese E-Mail.') . mail_p('Liebe Grüße<br>EINTRIKOT e.V.');
}

add_filter(
    'retrieve_password_notification_email',
    function ($mail, $key, $login, $user) {
        $link = network_site_url(
            'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode($login),
            'login'
        );
        $mail['subject'] = 'Neues Passwort für das EINTRIKOT-Mitgliederportal';
        $mail['message'] = mail_wrap(
            mail_p('Hallo ' . esc_html(first_name($user->display_name) ?: $user->display_name) . ',') .
                mail_p(
                    'für dein Konto wurde ein neues Passwort angefordert. Mit diesem Knopf legst du es selbst fest:'
                ) .
                mail_button($link, 'Neues Passwort festlegen') .
                mail_note(
                    'Der Link gilt 24 Stunden und nur einmal. Anmelden kannst du dich danach mit deiner E-Mail-Adresse <strong>' .
                        esc_html($user->user_email) .
                        '</strong>. Hast du nichts angefordert? Dann ignoriere diese E-Mail – dein bisheriges Passwort bleibt gültig. Wir schicken dir nie ein Passwort per E-Mail und fragen auch nie danach.'
                ) .
                mail_signoff()
        );
        $mail['headers'] = mail_headers();
        embed_logo_once();
        return $mail;
    },
    10,
    4
);

// After a new password: sign out everywhere, tell the member, no mail to the site admin.
remove_action('after_password_reset', 'wp_password_change_notification');
add_action(
    'after_password_reset',
    function ($user) {
        \WP_Session_Tokens::get_instance($user->ID)->destroy_all();
        embed_logo_once();
        wp_mail(
            $user->user_email,
            'Dein EINTRIKOT-Passwort wurde geändert',
            mail_wrap(
                mail_p('Hallo ' . esc_html(first_name($user->display_name) ?: $user->display_name) . ',') .
                    mail_p(
                        'dein Passwort für das Mitgliederportal wurde gerade geändert. Aus Sicherheitsgründen bist du jetzt auf allen Geräten abgemeldet.'
                    ) .
                    mail_p(
                        'Warst du das nicht? Dann setze dein Passwort über „Passwort vergessen?“ sofort neu und antworte auf diese E-Mail.'
                    ) .
                    mail_signoff()
            ),
            mail_headers()
        );
    },
    20
);

/* ---------- Härtung ---------- */

add_filter('xmlrpc_enabled', '__return_false');
add_filter('wp_headers', function ($headers) {
    unset($headers['X-Pingback']);
    return $headers;
});
// Member names and user names are not listed publicly through the REST API or author pages.
add_filter('rest_endpoints', function ($endpoints) {
    if (!current_user_can('list_users')) {
        foreach (array_keys($endpoints) as $route) {
            if (str_starts_with($route, '/wp/v2/users')) {
                unset($endpoints[$route]);
            }
        }
    }
    return $endpoints;
});
add_filter('wp_sitemaps_add_provider', fn($provider, $name) => $name === 'users' ? false : $provider, 10, 2);
add_action('template_redirect', function () {
    if ((is_author() || isset($_GET['author'])) && !current_user_can('list_users')) {
        wp_safe_redirect(home_url('/'), 301);
        exit();
    }
});
