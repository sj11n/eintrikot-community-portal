<?php
/**
 * Heftgestaltung „Das Viertel“ (Designfreigabe B, 05.11.2026, docs/entwuerfe/newsletter-heft.html):
 * Farbe des Viertels je Ausgabe und Stile für bekannte Editor-Bausteine.
 *
 *   Absatz:  Rubrik, Inhaltszeile, Unterschrift, Termin
 *   Gruppe:  Titelkasten, Farbkasten, Infokasten, Rahmen, Große Zahl
 *   Zitat und Liste ohne eigenen Stil.
 *
 * Alles wird als Tabellen mit Inline-Stilen gebaut (Outlook) und hat Klassen für den Dunkelmodus.
 */

namespace Eintrikot\Newsletter;

if (!defined('ABSPATH')) {
    exit();
}

/** Name of the newsletter in the kicker line and the text version. */
function name() {
    return (string) apply_filters('eintrikot_newsletter_name', 'Das Viertel');
}

/**
 * Colours of the quarter (brand book). accent: surfaces and tiles; strong: dots and numbers on white.
 *
 * @return array<string, array{label: string, accent: string, strong: string, band: string}>
 */
function colors() {
    return [
        'rose' => ['label' => 'Rosé', 'accent' => '#f1d1dd', 'strong' => '#da525d', 'band' => '#c8e2ee'],
        'blau' => ['label' => 'Blau', 'accent' => '#c8e2ee', 'strong' => '#2f6f8f', 'band' => '#cedfbd'],
        'gruen' => ['label' => 'Grün', 'accent' => '#cedfbd', 'strong' => '#5d7f3a', 'band' => '#c8e2ee']
    ];
}
/** @return array{label: string, accent: string, strong: string, band: string} */
function palette($color) {
    return colors()[$color] ?? colors()['rose'];
}
function color($id) {
    $c = (string) get_post_meta((int) $id, '_et_nl_color', true);
    return isset(colors()[$c]) ? $c : 'rose';
}

/**
 * Styles offered in the editor, by block. Keys are the class names (is-style-…).
 *
 * @return array<string, array<string, string>>
 */
function block_styles() {
    return [
        'core/paragraph' => [
            'rubrik' => 'Rubrik',
            'inhalt' => 'Inhaltszeile',
            'unterschrift' => 'Unterschrift',
            'termin' => 'Termin'
        ],
        'core/group' => [
            'titel' => 'Titelkasten',
            'farbe' => 'Farbkasten',
            'kasten' => 'Infokasten',
            'rahmen' => 'Rahmen',
            'zahl' => 'Große Zahl'
        ]
    ];
}
/** The chosen style of a block ('' for the default). */
function block_style(array $block) {
    $classes = preg_split('/\s+/', (string) ($block['attrs']['className'] ?? '')) ?: [];
    foreach ($classes as $class) {
        if (str_starts_with($class, 'is-style-')) {
            $style = substr($class, 9);
            return isset(block_styles()[$block['blockName'] ?? ''][$style]) ? $style : '';
        }
    }
    return '';
}

/**
 * Placeholders like "[Vorname Name]" left in the text (skill drafts mark missing material this way).
 *
 * @return string[]
 */
function open_placeholders($content) {
    $text = html_entity_decode(
        wp_strip_all_tags((string) preg_replace('/<!--.*?-->/s', '', (string) $content))
    );
    preg_match_all('/\[[^\[\]\n]{2,160}\]/u', $text, $m);
    return array_values(array_unique($m[0]));
}

/* ---------- Elemente ---------- */

/** Uppercase plain text of a paragraph (rubric lines are plain text, so no CSS is needed). */
function plain_upper($html) {
    return esc_html(
        mb_strtoupper(html_entity_decode(wp_strip_all_tags((string) $html), ENT_QUOTES, 'UTF-8'))
    );
}

function mail_rubric($html, array $p, $boxed = false) {
    return '<p class="et-rubrik" style="margin:' .
        ($boxed ? '0 0 10px' : '26px 0 8px') .
        ';font-family:Montserrat,Arial,sans-serif;font-size:11px;line-height:1.4;font-weight:800;letter-spacing:0.14em;color:#000000"><span class="et-strong" style="font-size:14px;color:' .
        $p['strong'] .
        '">●</span>&nbsp; ' .
        plain_upper($html) .
        '</p>';
}
function mail_toc($html) {
    return '<p class="et-muted et-toc" style="margin:0 0 26px;padding:10px 0;border-top:2px solid #000000;border-bottom:1px solid #e4e4e4;font-size:13px;line-height:1.7;color:#595959"><strong style="font-size:11px;letter-spacing:0.08em;color:#000000">' .
        esc_html(mb_strtoupper('In diesem Viertel')) .
        '</strong>&nbsp; ' .
        $html .
        '</p>';
}
function mail_signature($html) {
    return '<p class="et-sign" style="margin:0 0 16px;font-size:15px;line-height:1.6;font-style:italic;color:#333333">' .
        $html .
        '</p>';
}

/** [date, rest] of a date line "<strong>12. Juni</strong> Title<br>Detail", or null without a bold date. */
function date_parts($html) {
    return preg_match('/^\s*<strong>(.+?)<\/strong>\s*(?:<br\s*\/?>)?\s*(.*)$/is', (string) $html, $m)
        ? [trim(wp_strip_all_tags($m[1])), trim($m[2])]
        : null;
}
function mail_date($html, array $p) {
    $parts = date_parts($html);
    if (!$parts) {
        return mail_paragraph($html);
    }
    [$date, $rest] = $parts;
    [$day, $month] = array_pad(preg_split('/\s+/', $date, 2) ?: [$date], 2, '');
    // First line of the rest is the title.
    $lines = preg_split('/<br\s*\/?>/i', $rest, 2) ?: [$rest];
    $title = trim($lines[0]);
    $detail = trim($lines[1] ?? '');
    return '<table role="presentation" class="et-row" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;border-top:1px solid #e4e4e4;margin:0"><tr>' .
        '<td width="62" valign="middle" style="padding:12px 16px 12px 0;width:62px"><table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td class="et-tile et-on-accent" width="62" height="62" align="center" valign="middle" bgcolor="' .
        $p['accent'] .
        '" style="width:62px;height:62px;background:' .
        $p['accent'] .
        ';border-radius:12px;color:#000000;line-height:1.05;font-family:Montserrat,Arial,sans-serif"><span style="font-size:21px;font-weight:800;letter-spacing:-0.04em">' .
        esc_html($day) .
        '</span>' .
        ($month !== ''
            ? '<br><span style="font-size:10px;font-weight:700;letter-spacing:0.08em">' .
                esc_html(mb_strtoupper($month)) .
                '</span>'
            : '') .
        '</td></tr></table></td>' .
        '<td valign="middle" style="padding:12px 0;font-size:15px;line-height:1.45"><strong>' .
        wp_strip_all_tags($title, false) .
        '</strong>' .
        ($detail !== '' ? '<br>' . $detail : '') .
        '</td></tr></table>';
}

function mail_quote(array $block, array $p) {
    $text = '';
    foreach ($block['innerBlocks'] ?? [] as $inner) {
        if (($inner['blockName'] ?? '') === 'core/paragraph') {
            $line = inline_html(inner_of($inner['innerHTML'] ?? ''));
            $text .=
                $line !== ''
                    ? '<p style="margin:0 0 6px;font-size:23px;line-height:1.3;font-weight:700;letter-spacing:-0.025em">' .
                        $line .
                        '</p>'
                    : '';
        }
    }
    $cite = preg_match('/<cite[^>]*>(.*?)<\/cite>/is', (string) ($block['innerHTML'] ?? ''), $m)
        ? inline_html($m[1])
        : '';
    if ($text === '') {
        return '';
    }
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;margin:22px 0"><tr><td class="et-bar" width="6" bgcolor="' .
        $p['strong'] .
        '" style="width:6px;background:' .
        $p['strong'] .
        ';font-size:0;line-height:0">&nbsp;</td><td style="padding:2px 0 2px 18px">' .
        $text .
        ($cite !== ''
            ? '<p class="et-muted" style="margin:0;font-size:13px;line-height:1.5;color:#595959">' .
                $cite .
                '</p>'
            : '') .
        '</td></tr></table>';
}

function mail_list(array $block, array $p) {
    $ordered = !empty($block['attrs']['ordered']);
    $rows = '';
    $n = 0;
    foreach ($block['innerBlocks'] ?? [] as $item) {
        if (($item['blockName'] ?? '') !== 'core/list-item') {
            continue;
        }
        $text = inline_html(
            inner_of(preg_replace('/<ul.*<\/ul>|<ol.*<\/ol>/is', '', $item['innerHTML'] ?? ''))
        );
        if ($text === '') {
            continue;
        }
        $n++;
        // A bold start becomes the headline of the item.
        $text = (string) preg_replace(
            '/^<strong>(.*?)<\/strong>\s*(?:<br\s*\/?>)?\s*/is',
            '<strong style="display:block;font-size:16px;margin:0 0 2px">$1</strong>',
            $text,
            1
        );
        $rows .=
            '<tr><td class="et-strong et-row" width="38" valign="top" style="width:38px;padding:14px 0;border-top:1px solid #e4e4e4;font-size:15px;line-height:1.55;font-weight:800;color:' .
            $p['strong'] .
            '">' .
            ($ordered ? sprintf('%02d', $n) : '●') .
            '</td><td class="et-row" valign="top" style="padding:14px 0;border-top:1px solid #e4e4e4;font-size:15px;line-height:1.55">' .
            $text .
            '</td></tr>';
    }
    return $rows !== ''
        ? '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;margin:6px 0 18px">' .
                $rows .
                '</table>'
        : '';
}

/**
 * A group block as a box. $inner is the rendered content; for "zahl" the first heading becomes the number.
 */
function mail_group(array $block, array $p, $color) {
    $style = block_style($block);
    $inner = $block['innerBlocks'] ?? [];
    if ($style === 'zahl') {
        $number = '';
        foreach ($inner as $i => $b) {
            if (($b['blockName'] ?? '') === 'core/heading') {
                $number = trim(wp_strip_all_tags(inner_of($b['innerHTML'] ?? '')));
                unset($inner[$i]);
                break;
            }
        }
        return '<table role="presentation" class="et-numbox" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#000000" style="border-collapse:separate;background:#000000;border-radius:14px;margin:4px 0 16px"><tr>' .
            ($number !== ''
                ? '<td class="et-num" valign="middle" style="padding:22px 0 22px 22px;font-family:Montserrat,Arial,sans-serif;font-size:72px;line-height:0.9;font-weight:800;letter-spacing:-0.06em;color:' .
                    $p['accent'] .
                    ';white-space:nowrap">' .
                    esc_html($number) .
                    '</td>'
                : '') .
            '<td class="et-on-dark" valign="middle" style="padding:22px;font-size:15px;line-height:1.5;color:#ffffff">' .
            render_blocks(array_values($inner), $color, 'zahl') .
            '</td></tr></table>';
    }
    $boxes = [
        'titel' => ['et-on-accent', $p['accent'], 'padding:26px 24px 10px', ''],
        'farbe' => ['et-on-accent', $p['accent'], 'padding:22px 22px 6px', ''],
        'kasten' => ['et-box', '#f6f6f6', 'padding:16px 18px 2px;font-size:14px', ''],
        'rahmen' => ['et-frame', '#ffffff', 'padding:18px 20px 2px', 'border:2px solid #000000;']
    ];
    if (!isset($boxes[$style])) {
        return render_blocks($inner, $color, 'group');
    }
    [$class, $bg, $padding, $border] = $boxes[$style];
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:separate;margin:6px 0 22px"><tr><td class="' .
        $class .
        '" bgcolor="' .
        $bg .
        '" style="' .
        $border .
        'background:' .
        $bg .
        ';border-radius:14px;' .
        $padding .
        ($class === 'et-on-accent' ? ';color:#000000' : '') .
        '">' .
        render_blocks($inner, $color, $style) .
        '</td></tr></table>';
}

/**
 * Dark mode rules that depend on the colour of the quarter.
 */
function dark_layout_css($color) {
    $p = palette($color);
    return '.et-text .et-on-accent,.et-text .et-on-accent p,.et-text .et-on-accent h2,.et-text .et-on-accent td,.et-text .et-on-accent strong,.et-text .et-on-accent em,.et-text .et-on-accent a,.et-text .et-on-accent span{color:#000000!important}' .
        '.et-text .et-on-accent .et-strong{color:' .
        $p['strong'] .
        '!important}' .
        '.et-text .et-strong{color:' .
        $p['accent'] .
        '!important}.et-text .et-num{color:' .
        $p['accent'] .
        '!important}' .
        '.et-numbox{background:#0d0f10!important}.et-box{background:#2a3034!important}.et-frame{background:#1f2427!important;border-color:#f2f2f2!important}' .
        '.et-toc{border-top-color:#f2f2f2!important;border-bottom-color:#3b4247!important}.et-row{border-color:#3b4247!important}' .
        '.et-text .et-sign{color:#d6d6d6!important}';
}

/* ---------- Editor ---------- */

/** Whether the block editor is open for an issue. */
function editing_issue() {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    return $screen && $screen->post_type === POST_TYPE;
}
add_action('enqueue_block_editor_assets', function () {
    if (!editing_issue()) {
        return;
    }
    wp_enqueue_script(
        'eintrikot-newsletter-editor',
        plugins_url('newsletter-editor.js', __FILE__),
        ['wp-blocks', 'wp-dom-ready'],
        asset_version('newsletter-editor.js'),
        true
    );
    wp_add_inline_script(
        'eintrikot-newsletter-editor',
        'window.eintrikotNewsletterStyles = ' . wp_json_encode(block_styles()) . ';',
        'before'
    );
});
// enqueue_block_assets also reaches the editor iframe.
add_action('enqueue_block_assets', function () {
    if (!is_admin() || !editing_issue()) {
        return;
    }
    wp_enqueue_style(
        'eintrikot-newsletter-editor',
        plugins_url('newsletter-editor.css', __FILE__),
        [],
        asset_version('newsletter-editor.css')
    );
    $post = $GLOBALS['post'] ?? null;
    $p = palette($post instanceof \WP_Post ? color($post->ID) : 'rose');
    wp_add_inline_style(
        'eintrikot-newsletter-editor',
        ':root{--et-accent:' . $p['accent'] . ';--et-strong:' . $p['strong'] . '}'
    );
});
