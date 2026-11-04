<?php
/**
 * Mail-Layout (Designfreigabe B, 04.11.2026): Rahmen wie alle EINTRIKOT-Mails (Farbband, Logo, Karte, hell und dunkel),
 * dazu Kicker „Newsletter · Ausgabe“, Betreff als Überschrift, persönliche Anrede, Inhalt aus den erlaubten Bausteinen
 * und eine Fußzeile mit Grund des Empfangs, Abmeldelink, Profil, Datenschutz und Angaben laut Impressum.
 * Tabellen und Inline-Stile für Outlook, Systemschrift als Rückfall, Textfassung als Alternative. Keine Tracking-Pixel.
 */

namespace Eintrikot\Newsletter;

if (!defined('ABSPATH')) {
    exit();
}

/** @return array{from: string, from_name: string, reply_to: string, batch: int} */
function settings() {
    $s = get_option('eintrikot_newsletter_settings', []);
    $s = is_array($s) ? $s : [];
    return [
        'from' => is_email($s['from'] ?? '') ? $s['from'] : 'newsletter@eintrikot.de',
        'from_name' => (string) ($s['from_name'] ?? '') ?: 'EINTRIKOT e. V.',
        'reply_to' => is_email($s['reply_to'] ?? '') ? $s['reply_to'] : 'info@eintrikot.de',
        'batch' => max(1, min(100, (int) ($s['batch'] ?? 20)))
    ];
}

/** Club details for the footer, as in the imprint (theme pattern mvp-impressum). */
function imprint_line() {
    return (string) apply_filters(
        'eintrikot_newsletter_imprint',
        'EINTRIKOT e. V. · Bachstr. 20 · 63452 Hanau · Vertreten durch den Vorstand · Vereinsregister Amtsgericht Hanau, VR 32731'
    );
}

/* ---------- Bausteine → Mail-HTML ---------- */

/** @return array<int, array<string, mixed>> Top-level blocks without empty filler between them. */
function content_blocks($content) {
    return array_values(
        array_filter(parse_blocks((string) $content), static function ($b) {
            return !empty($b['blockName']) || trim($b['innerHTML']) !== '';
        })
    );
}

/** @return string[] Problems that keep an issue from being sent. */
function block_problems(array $blocks) {
    $problems = [];
    foreach ($blocks as $b) {
        $name = (string) ($b['blockName'] ?? '');
        if (!in_array($name, BLOCKS, true) || $name === 'core/button') {
            $problems['type'] =
                'Der Inhalt enthält einen Baustein, den die Mail nicht kennt (erlaubt: Überschrift, Absatz, Bild, Button, Trennlinie).';
            continue;
        }
        if ($name === 'core/image' && trim(image_parts($b)['alt']) === '') {
            $problems['alt'] = 'Mindestens ein Bild hat keinen Alternativtext.';
        }
        if ($name === 'core/image' && image_parts($b)['src'] === '') {
            $problems['src'] = 'Mindestens ein Bild-Baustein ist leer.';
        } elseif ($name === 'core/image' && local_image_path(image_parts($b)['src']) === '') {
            $problems['local'] =
                'Bilder müssen aus der Mediathek dieser Seite kommen, damit sie in die Mail eingebettet werden (nichts wird nachgeladen).';
        }
        if ($name === 'core/buttons') {
            foreach ($b['innerBlocks'] ?? [] as $button) {
                $parts = button_parts($button);
                if ($parts['url'] === '' || $parts['text'] === '') {
                    $problems['button'] = 'Mindestens ein Button hat keinen Text oder keinen Link.';
                }
            }
        }
    }
    return array_values($problems);
}

/** Inline text with links, bold and italic only. Links get the mail style. */
function inline_html($html) {
    $html = wp_kses((string) $html, [
        'a' => ['href' => true],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'br' => []
    ]);
    $html = (string) preg_replace_callback(
        '/<a href="([^"]*)">/i',
        static function ($m) {
            return '<a href="' .
                esc_url(html_entity_decode($m[1])) .
                '" style="color:#000000;font-weight:700;text-decoration:underline">';
        },
        $html
    );
    return trim($html);
}
/** Inner HTML of the first element, e.g. the text of <p>…</p>. */
function inner_of($html) {
    return preg_match('/^\s*<[a-z0-9]+[^>]*>(.*)<\/[a-z0-9]+>\s*$/is', (string) $html, $m)
        ? $m[1]
        : (string) $html;
}
/** @return array{src: string, alt: string, caption: string} */
function image_parts(array $block) {
    $html = (string) ($block['innerHTML'] ?? '');
    $src = preg_match('/<img[^>]+src="([^"]+)"/i', $html, $m) ? html_entity_decode($m[1]) : '';
    $id = (int) ($block['attrs']['id'] ?? 0);
    if ($id && wp_get_attachment_image_url($id, 'large')) {
        $src = (string) wp_get_attachment_image_url($id, 'large');
    }
    return [
        'src' => $src,
        'alt' => preg_match('/<img[^>]+alt="([^"]*)"/i', $html, $m)
            ? html_entity_decode($m[1], ENT_QUOTES)
            : '',
        'caption' => preg_match('/<figcaption[^>]*>(.*?)<\/figcaption>/is', $html, $m) ? $m[1] : ''
    ];
}
/** @return array{url: string, text: string} */
function button_parts(array $block) {
    $html = (string) ($block['innerHTML'] ?? '');
    return [
        'url' => preg_match('/<a[^>]+href="([^"]+)"/i', $html, $m) ? html_entity_decode($m[1]) : '',
        'text' => trim(wp_strip_all_tags(preg_match('/<a[^>]*>(.*?)<\/a>/is', $html, $m) ? $m[1] : ''))
    ];
}

function mail_heading($text) {
    return '<h2 style="margin:8px 0 10px;font-family:Montserrat,Arial,sans-serif;font-size:22px;line-height:1.2;font-weight:800;letter-spacing:-0.02em;color:#000000">' .
        $text .
        '</h2>';
}
function mail_paragraph($html) {
    return '<p style="margin:0 0 16px;font-size:16px;line-height:1.6">' . $html . '</p>';
}
function mail_rule() {
    return '<div class="et-rule" style="border-top:1px solid #e4e4e4;margin:28px 0;font-size:0;line-height:0">&nbsp;</div>';
}
/** Button as a table cell, so every client shows it (also Outlook). Same look as the portal mails. */
function mail_button($url, $label) {
    return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:4px 0 24px;border-collapse:separate"><tr><td bgcolor="#c8e2ee" style="background:#c8e2ee;border-radius:999px;border:2px solid #000000"><a href="' .
        esc_url($url) .
        '" style="display:inline-block;padding:13px 26px;font-family:Montserrat,Arial,sans-serif;font-size:16px;font-weight:700;line-height:1.2;color:#000000;text-decoration:none;border-radius:999px">' .
        esc_html($label) .
        ' →</a></td></tr></table>';
}

/** The body of an issue as mail HTML (without greeting and frame). */
function render_body($content) {
    $html = '';
    foreach (content_blocks($content) as $b) {
        switch ($b['blockName'] ?? '') {
            case 'core/heading':
                $html .= mail_heading(inline_html(inner_of($b['innerHTML'] ?? '')));
                break;
            case 'core/paragraph':
                $text = inline_html(inner_of($b['innerHTML'] ?? ''));
                $html .= $text !== '' ? mail_paragraph($text) : '';
                break;
            case 'core/image':
                $img = image_parts($b);
                if ($img['src'] !== '') {
                    $html .=
                        '<img src="' .
                        esc_url($img['src']) .
                        '" width="504" alt="' .
                        esc_attr($img['alt']) .
                        '" style="display:block;width:100%;max-width:504px;height:auto;border:0;border-radius:12px;margin:0 0 ' .
                        ($img['caption'] !== '' ? '8' : '18') .
                        'px">' .
                        ($img['caption'] !== ''
                            ? '<p class="et-muted" style="margin:0 0 18px;font-size:13px;line-height:1.5;color:#595959">' .
                                inline_html($img['caption']) .
                                '</p>'
                            : '');
                }
                break;
            case 'core/buttons':
                foreach ($b['innerBlocks'] ?? [] as $button) {
                    $parts = button_parts($button);
                    if ($parts['url'] !== '' && $parts['text'] !== '') {
                        $html .= mail_button($parts['url'], $parts['text']);
                    }
                }
                break;
            case 'core/separator':
                $html .= mail_rule();
                break;
        }
    }
    return $html;
}

/** Plain-text version of the body for clients without HTML. */
function render_text($body_html) {
    $text = (string) preg_replace_callback(
        '/<a href="([^"]*)"[^>]*>(.*?)<\/a>/is',
        static function ($m) {
            $label = trim(wp_strip_all_tags(str_replace(' →', '', $m[2])));
            return $label . ' (' . html_entity_decode($m[1]) . ')';
        },
        (string) $body_html
    );
    $text = (string) preg_replace('/<h2[^>]*>(.*?)<\/h2>/is', "\n\n$1\n", $text);
    $text = (string) preg_replace('/<img[^>]*alt="([^"]*)"[^>]*>/i', "[Bild: $1]\n", $text);
    $text = (string) preg_replace('/<div class="et-rule".*?<\/div>/is', "\n----------\n", $text);
    $text = (string) preg_replace('/<br\s*\/?>/i', "\n", $text);
    $text = (string) preg_replace('/<\/(p|table)>/i', "\n\n", $text);
    $text = html_entity_decode(wp_strip_all_tags($text), ENT_QUOTES, 'UTF-8');
    $text = (string) preg_replace("/[ \t]+\n/", "\n", $text);
    return trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
}

/** Snapshot of an issue at approval: archive and sending show exactly this. */
function freeze($id) {
    $post = get_post((int) $id);
    $body = $post ? render_body($post->post_content) : '';
    return [
        'subject' => $post ? $post->post_title : '',
        'preheader' => preheader($id),
        'label' => label($id),
        'body' => $body,
        'text' => render_text($body)
    ];
}
/** The current (not yet frozen) state of an issue, for preview and test mails. */
function draft_data($id) {
    return frozen($id) ?: freeze($id);
}

/* ---------- Rahmen ---------- */

/**
 * The complete mail.
 *
 * @param array{subject: string, preheader: string, label: string, body: string} $data
 * @param array{name?: string, unsubscribe?: string, web?: bool, scheme?: string} $ctx
 *   web: logos as URL (portal preview, archive) instead of embedded; scheme: '' (follows the reader), 'light', 'dark'.
 */
function render_mail(array $data, array $ctx = []) {
    $web = !empty($ctx['web']);
    $scheme = $ctx['scheme'] ?? '';
    $logo = $web ? plugins_url('eintrikot-community-core/assets/logo-mail.png') : 'cid:eintrikot-logo';
    $logo_dark = $web
        ? plugins_url('eintrikot-community-core/assets/logo-mail-white.png')
        : 'cid:eintrikot-logo-dark';
    $name = trim((string) ($ctx['name'] ?? ''));
    $unsubscribe = (string) ($ctx['unsubscribe'] ?? '');
    $dark =
        '.et-bg{background:#121416!important}.et-card{background:#1f2427!important}' .
        '.et-text,.et-text p,.et-text h1,.et-text h2,.et-text td,.et-text strong,.et-text em,.et-text a{color:#f2f2f2!important}' .
        '.et-text td a{color:#000000!important}' .
        '.et-muted,.et-muted p,.et-muted a{color:#b9c0c4!important}.et-rule{border-color:#3b4247!important}.et-foot{background:#181b1d!important}' .
        '.et-logo-light{display:none!important}.et-logo-dark{display:block!important;max-height:none!important}';
    $style =
        ($scheme === 'light'
            ? ':root{color-scheme:light}'
            : ':root{color-scheme:light dark;supported-color-schemes:light dark}') .
        ($scheme === 'dark' ? $dark : '') .
        ($scheme === ''
            ? '@media (prefers-color-scheme:dark){' .
                $dark .
                '}[data-ogsc] .et-logo-light{display:none!important}[data-ogsc] .et-logo-dark{display:block!important;max-height:none!important}'
            : '') .
        '@media (max-width:480px){.et-text{padding:24px 20px 4px!important}.et-foot{padding:20px!important}.et-h1{font-size:30px!important}}';
    $foot_link = static function ($url, $label, $bold = false) {
        return '<a href="' .
            esc_url($url) .
            '" style="color:#595959;' .
            ($bold ? 'font-weight:700;' : '') .
            'text-decoration:underline">' .
            esc_html($label) .
            '</a>';
    };
    $privacy = get_privacy_policy_url() ?: home_url('/datenschutz/');
    return '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="' .
        ($scheme === 'light' ? 'light' : 'light dark') .
        '"><meta name="supported-color-schemes" content="light dark"><title>' .
        esc_html($data['subject']) .
        '</title><style>' .
        $style .
        '</style></head><body class="et-bg" style="margin:0;padding:0;background:#edf5f8">' .
        '<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:#edf5f8">' .
        esc_html($data['preheader']) .
        str_repeat('&#8199;&#65279;&#847; ', 40) .
        '</div>' .
        '<div class="et-bg" style="background:#edf5f8;padding:24px 12px;font-family:Montserrat,Arial,sans-serif;color:#000000">' .
        ($web
            ? ''
            : '<p class="et-muted" style="max-width:560px;margin:0 auto 12px;font-size:12px;line-height:1.5;color:#595959;text-align:center">Wird die E-Mail nicht richtig angezeigt? <a href="' .
                esc_url(\Eintrikot\Community\portal_url('newsletter')) .
                '" style="color:#595959;text-decoration:underline">Im Portal lesen</a></p>') .
        '<div class="et-card" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden">' .
        '<div style="height:10px;background:#c8e2ee;background:linear-gradient(90deg,#f1d1dd,#c8e2ee)"></div>' .
        '<div class="et-text" style="padding:28px 28px 8px;color:#000000">' .
        '<div style="margin:0 0 28px"><img class="et-logo-light" src="' .
        esc_url($logo, ['http', 'https', 'cid']) .
        '" width="165" height="42" alt="EINTRIKOT" style="display:block;border:0;width:165px;height:42px">' .
        '<div class="et-logo-dark" style="display:none;max-height:0;overflow:hidden;mso-hide:all"><img src="' .
        esc_url($logo_dark, ['http', 'https', 'cid']) .
        '" width="165" height="42" alt="EINTRIKOT" style="display:block;border:0;width:165px;height:42px"></div></div>' .
        ($data['label'] !== ''
            ? '<p class="et-muted" style="margin:0 0 10px;font-size:13px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#595959">Newsletter · ' .
                esc_html($data['label']) .
                '</p>'
            : '') .
        '<h1 class="et-h1" style="margin:0 0 18px;font-family:Montserrat,Arial,sans-serif;font-size:36px;line-height:1.05;font-weight:800;letter-spacing:-0.045em;color:#000000">' .
        esc_html($data['subject']) .
        '</h1>' .
        mail_paragraph($name !== '' ? 'Hallo ' . esc_html($name) . ',' : 'Hallo,') .
        $data['body'] .
        '</div>' .
        '<div class="et-foot et-muted" style="background:#f6f6f6;padding:22px 28px;font-size:13px;line-height:1.6;color:#595959">' .
        '<p style="margin:0 0 10px">Du bekommst diese E-Mail, weil du im EINTRIKOT-Mitgliederportal dem Newsletter zugestimmt hast. ' .
        ($unsubscribe !== '' ? $foot_link($unsubscribe, 'Mit einem Klick abmelden', true) . ' · ' : '') .
        $foot_link(\Eintrikot\Community\portal_url('profile'), 'Im Profil ändern') .
        ' · ' .
        $foot_link($privacy, 'Datenschutz') .
        '</p><p style="margin:0">' .
        esc_html(imprint_line()) .
        '</p></div></div></div></body></html>';
}

/** Plain-text version of the complete mail. */
function render_mail_text(array $data, array $ctx = []) {
    $name = trim((string) ($ctx['name'] ?? ''));
    $unsubscribe = (string) ($ctx['unsubscribe'] ?? '');
    return ($data['label'] !== '' ? 'EINTRIKOT-Newsletter · ' . $data['label'] . "\n\n" : '') .
        $data['subject'] .
        "\n\n" .
        ($name !== '' ? 'Hallo ' . $name . ',' : 'Hallo,') .
        "\n\n" .
        ($data['text'] !== '' ? $data['text'] : render_text($data['body'])) .
        "\n\n--\nDu bekommst diese E-Mail, weil du im EINTRIKOT-Mitgliederportal dem Newsletter zugestimmt hast.\n" .
        ($unsubscribe !== '' ? 'Abmelden: ' . $unsubscribe . "\n" : '') .
        'Im Profil ändern: ' .
        \Eintrikot\Community\portal_url('profile') .
        "\n" .
        imprint_line() .
        "\n";
}

/* ---------- Versand einer Mail ---------- */

/** @return string[] */
function mail_headers($unsubscribe) {
    $s = settings();
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $s['from_name'] . ' <' . $s['from'] . '>',
        'Reply-To: ' . $s['reply_to'],
        'List-Id: EINTRIKOT-Newsletter <newsletter.' .
        (wp_parse_url(home_url(), PHP_URL_HOST) ?: 'eintrikot.de') .
        '>'
    ];
    if ($unsubscribe !== '') {
        // Gmail and Outlook expect both lines; the one-click call is a signed POST (unsubscribe.php).
        $headers[] =
            'List-Unsubscribe: <' .
            $unsubscribe .
            '>, <mailto:' .
            $s['reply_to'] .
            '?subject=Newsletter%20abbestellen>';
        $headers[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
    }
    return $headers;
}

/** Last error reported by wp_mail, for the send log. */
function last_mail_error($set = null) {
    static $error = '';
    if ($set !== null) {
        $error = (string) $set;
    }
    return $error;
}
add_action('wp_mail_failed', function ($error) {
    last_mail_error($error instanceof \WP_Error ? $error->get_error_message() : 'Unbekannter Fehler');
});

/**
 * File on this server for an image URL of this site (media library, theme, plugins), or '' for anything else.
 * Only such images can travel inside the mail.
 */
function local_image_path($url) {
    $url = strtok((string) $url, '?#');
    $uploads = wp_get_upload_dir();
    foreach ([[$uploads['baseurl'], $uploads['basedir']], [content_url(), WP_CONTENT_DIR]] as [$base, $dir]) {
        foreach ([set_url_scheme($base, 'http'), set_url_scheme($base, 'https')] as $prefix) {
            if ($url !== false && str_starts_with($url, trailingslashit($prefix))) {
                $relative = rawurldecode(substr($url, strlen(trailingslashit($prefix))));
                // No way out of the folder: no "..", no NUL, only image files.
                if (
                    str_contains($relative, '..') ||
                    str_contains($relative, "\0") ||
                    !preg_match('/\.(jpe?g|png|gif|webp)$/i', $relative)
                ) {
                    return '';
                }
                $path = wp_normalize_path(trailingslashit($dir) . $relative);
                return is_file($path) && is_readable($path) ? $path : '';
            }
        }
    }
    return '';
}

/**
 * Replaces the images of this site with embedded copies (cid:), as the privacy policy promises: a newsletter loads
 * nothing from outside when it is opened.
 *
 * @return array{0: string, 1: array<string, string>} [HTML, cid => file]
 */
function embed_images($html) {
    $files = [];
    $html = (string) preg_replace_callback(
        '/<img([^>]*?)src="([^"]+)"/i',
        static function ($m) use (&$files) {
            $path = local_image_path(html_entity_decode($m[2]));
            if ($path === '') {
                return $m[0];
            }
            $cid = 'et-bild-' . substr(md5($path), 0, 12);
            $files[$cid] = $path;
            return '<img' . $m[1] . 'src="cid:' . $cid . '"';
        },
        (string) $html
    );
    return [$html, $files];
}

/** Sends one mail to one address: HTML, text version, logos and images embedded. */
function send_one($to, $subject, $html, $text, $unsubscribe) {
    last_mail_error('');
    [$html, $images] = embed_images($html);
    $init = static function ($mailer) use ($text, $images) {
        $mailer->AltBody = $text;
        if (function_exists('Eintrikot\Community\mail_embed_logos')) {
            \Eintrikot\Community\mail_embed_logos($mailer);
        }
        foreach ($images as $cid => $path) {
            $mailer->addEmbeddedImage($path, $cid, basename($path));
        }
    };
    add_action('phpmailer_init', $init);
    $sent = wp_mail($to, $subject, $html, mail_headers($unsubscribe));
    remove_action('phpmailer_init', $init);
    return (bool) $sent;
}

/* ---------- Testversand ---------- */

/**
 * Addresses allowed for a test: any valid address, but never one of a plain member account. Test mails go to
 * the board, the editorial team and test mailboxes (Gmail, Outlook, web.de, GMX) only.
 *
 * @return array{0: string[], 1: string} [addresses, error]
 */
function test_addresses($raw) {
    $list = array_values(
        array_unique(
            array_filter(
                array_map(static fn($a) => strtolower(trim($a)), preg_split('/[\s,;]+/', (string) $raw) ?: [])
            )
        )
    );
    if (!$list) {
        return [[], 'Bitte mindestens eine Adresse angeben.'];
    }
    if (count($list) > 10) {
        return [[], 'Höchstens 10 Adressen pro Testversand.'];
    }
    foreach ($list as $mail) {
        if (!is_email($mail)) {
            return [[], '„' . $mail . '“ ist keine gültige E-Mail-Adresse.'];
        }
        $user = get_user_by('email', $mail);
        if (
            $user &&
            user_can($user, 'eintrikot_portal') &&
            !user_can($user, 'eintrikot_edit_infos') &&
            !user_can($user, 'eintrikot_manage_members')
        ) {
            return [
                [],
                '„' .
                $mail .
                '“ gehört einem Mitglied. Testmails gehen nur an Vorstand, Redaktion und Testpostfächer.'
            ];
        }
    }
    return [$list, ''];
}

/** Sends the current version as "[TEST]" to the given addresses. Returns an error text or ''. */
function send_test($id, $raw) {
    if (!issue($id) || !can_write() || locked($id)) {
        return 'Testversand ist für diese Ausgabe nicht möglich.';
    }
    [$list, $error] = test_addresses($raw);
    if ($error !== '') {
        return $error;
    }
    $data = draft_data($id);
    if (trim($data['subject']) === '') {
        return 'Bitte zuerst einen Betreff (Titel) eintragen.';
    }
    $me = wp_get_current_user();
    $unsubscribe = unsubscribe_url(0, $id);
    $ctx = ['name' => \Eintrikot\Community\first_name($me->display_name), 'unsubscribe' => $unsubscribe];
    $failed = [];
    foreach ($list as $mail) {
        if (
            !send_one(
                $mail,
                '[TEST] ' . $data['subject'],
                render_mail($data, $ctx),
                render_mail_text($data, $ctx),
                $unsubscribe
            )
        ) {
            $failed[] = $mail;
        }
    }
    update_post_meta((int) $id, '_et_nl_test', [
        'by' => get_current_user_id(),
        'at' => time(),
        'to' => $list
    ]);
    if ($failed) {
        return 'Nicht verschickt an: ' .
            implode(', ', $failed) .
            (last_mail_error() !== '' ? ' (' . last_mail_error() . ')' : '');
    }
    update_post_meta((int) $id, '_et_nl_test_hash', content_hash($id));
    return '';
}
