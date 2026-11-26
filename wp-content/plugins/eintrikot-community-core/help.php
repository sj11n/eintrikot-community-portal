<?php
/**
 * Mitglieder-Hilfe: eigene Seite im Portal (?view=hilfe), Design freigegeben am 26.11.2026 (docs/entwuerfe/hilfe).
 *
 * Bewusst abgesetzt vom Portal: Punktraster, eigene Kopfzeile „EINTRIKOT | Hilfe“, alle Portal-Bilder in einem
 * Fensterrahmen. Inhalte in help-articles.php, Bilder und Videos in help/ (tools/hilfe-medien.sh).
 *
 *   ?view=hilfe                     Startseite mit Suche und Themen
 *   ?view=hilfe&thema=<thema>       alle Anleitungen eines Themas
 *   ?view=hilfe&q=<suche>           Suchergebnis
 *   ?view=hilfe&artikel=<slug>      eine Anleitung
 *   &von=<view>[&von_tab=termine]   wohin „Zurück ins Portal“ führt
 *
 * Ohne Anmeldung sind nur Anleitungen mit 'public' => true lesbar (Anmeldung, Passwort).
 */
namespace Eintrikot\Community;
if (!defined('ABSPATH')) {
    exit();
}
require_once __DIR__ . '/help-articles.php';

/** Portal views „Zurück ins Portal“ may return to. Everything else goes to Start. */
const HELP_BACK_VIEWS = ['portal', 'members', 'aktuelles', 'service', 'profile'];

function help_back_args() {
    $from = sanitize_key(directory_param('von'));
    if (!in_array($from, HELP_BACK_VIEWS, true)) {
        return [];
    }
    $args = ['von' => $from];
    if ($from === 'aktuelles' && directory_param('von_tab') === 'termine') {
        $args['von_tab'] = 'termine';
    }
    return $args;
}

/** Link inside the help. Keeps where the member came from, so „Zurück ins Portal“ still fits. */
function help_url($args = []) {
    return portal_url('hilfe', array_merge(help_back_args(), $args));
}

function help_article_url($slug) {
    return help_url(['artikel' => $slug]);
}

/** Articles the current visitor may read: all for members, only public ones before sign-in. */
function help_visible_articles() {
    $all = help_articles();
    if (member_access()) {
        return $all;
    }
    return array_filter($all, fn($a) => !empty($a['public']));
}

function help_text($html) {
    return wp_kses($html, ['b' => [], 'strong' => [], 'em' => []]);
}

/** Measured elements per screenshot (help/boxes.json, written by tools/hilfe-medien.mjs). */
function help_boxes() {
    static $boxes = null;
    if ($boxes === null) {
        $raw = is_readable(__DIR__ . '/help/boxes.json')
            ? file_get_contents(__DIR__ . '/help/boxes.json')
            : '';
        $boxes = json_decode((string) $raw, true);
        $boxes = is_array($boxes) ? $boxes : [];
    }
    return $boxes;
}

function help_media_url($file) {
    return plugins_url('help/' . $file, __FILE__) . '?v=' . asset_version('help/' . $file);
}

/**
 * Number, frame and arrow of one mark, in the pixel space of the screenshot (viewBox).
 * Returns null when the element is missing from boxes.json, so a renamed element drops its mark
 * instead of pointing at the wrong place (the test in tests/run.php reports it).
 */
function help_mark_geometry($shot, $mark) {
    $data = help_boxes()[$shot] ?? null;
    $box = $data['t'][$mark['el']] ?? null;
    if (!$data || !is_array($box)) {
        return null;
    }
    $w = (float) $data['w'];
    $h = (float) $data['h'];
    $pad = 6;
    $x = ($box['x'] * $w) / 100 - $pad;
    $y = ($box['y'] * $h) / 100 - $pad;
    $bw = ($box['w'] * $w) / 100 + 2 * $pad;
    $bh = ($box['h'] * $h) / 100 + 2 * $pad;
    $d = (float) ($mark['d'] ?? 70);
    $cx = $x + $bw / 2;
    $cy = $y + $bh / 2;
    [$px, $py] = match ($mark['at'] ?? 'right') {
        'left' => [$x - $d, $cy],
        'top' => [$cx, $y - $d],
        'bottom' => [$cx, $y + $bh + $d],
        // On the frame's top right corner, for cards whose sides are full of content.
        'corner' => [$x + $bw - 4, $y + 4],
        default => [$x + $bw + $d, $cy]
    };
    // A number never leaves the picture.
    $r = 24;
    $px = max($r + 4, min($w - $r - 4, $px));
    $py = max($r + 4, min($h - $r - 4, $py));
    $arrow = '';
    $tx = max($x, min($px, $x + $bw));
    $ty = max($y, min($py, $y + $bh));
    $dx = $tx - $px;
    $dy = $ty - $py;
    $dist = hypot($dx, $dy);
    if ($dist > 46) {
        $ux = $dx / $dist;
        $uy = $dy / $dist;
        $sx = $px + $ux * ($r + 2);
        $sy = $py + $uy * ($r + 2);
        $ex = $tx - $ux * 8;
        $ey = $ty - $uy * 8;
        $mx = ($sx + $ex) / 2 - $uy * 14;
        $my = ($sy + $ey) / 2 + $ux * 14;
        $ang = atan2($ey - $my, $ex - $mx);
        $l = 15;
        $arrow = sprintf(
            '<path class="pfeil" d="M%.1f,%.1f Q%.1f,%.1f %.1f,%.1f"/><path class="pfeil" d="M%.1f,%.1f L%.1f,%.1f L%.1f,%.1f"/>',
            $sx,
            $sy,
            $mx,
            $my,
            $ex,
            $ey,
            $ex - $l * cos($ang - 0.5),
            $ey - $l * sin($ang - 0.5),
            $ex,
            $ey,
            $ex - $l * cos($ang + 0.5),
            $ey - $l * sin($ang + 0.5)
        );
    }
    return compact('x', 'y', 'bw', 'bh', 'px', 'py', 'arrow', 'r');
}

/** Browser-like frame around everything that shows the portal. */
function help_window($inner, $label, $stamp = 'So sieht es im Portal aus', $tag = 'div', $attrs = '') {
    return '<' .
        $tag .
        ' class="hw-fenster"' .
        $attrs .
        '><div class="hw-leiste" aria-hidden="true"><span class="hw-punkte"><i></i><i></i><i></i></span><span class="hw-url">' .
        esc_html($label) .
        '</span>' .
        ($stamp !== '' ? '<span class="hw-stempel">' . esc_html($stamp) . '</span>' : '') .
        '</div>' .
        $inner .
        '</' .
        $tag .
        '>';
}

function help_shot_alt($step) {
    return 'Bildschirmfoto: ' . wp_strip_all_tags($step['title']) . ' (' . ($step['where'] ?? 'Portal') . ')';
}

/** Screenshot with numbered marks and the matching legend. Numbers continue across the steps. */
function help_figure($step, &$number, $id) {
    $shot = $step['shot'];
    $data = help_boxes()[$shot] ?? ['w' => 1280, 'h' => 800];
    $w = (int) $data['w'];
    $h = (int) $data['h'];
    $svg = '';
    $legend = '';
    foreach ($step['marks'] ?? [] as $mark) {
        $g = help_mark_geometry($shot, $mark);
        if (!$g) {
            continue;
        }
        $number++;
        $svg .= sprintf(
            '<g class="m" data-n="%1$d"><rect class="glow" x="%2$.1f" y="%3$.1f" width="%4$.1f" height="%5$.1f" rx="12"/><rect class="rahmen" x="%2$.1f" y="%3$.1f" width="%4$.1f" height="%5$.1f" rx="12"/>%6$s<g class="badge"><circle cx="%7$.1f" cy="%8$.1f" r="%9$d"/><text x="%7$.1f" y="%10$.1f">%1$d</text></g></g>',
            $number,
            $g['x'],
            $g['y'],
            $g['bw'],
            $g['bh'],
            $g['arrow'],
            $g['px'],
            $g['py'],
            $g['r'],
            $g['py'] + 1
        );
        $legend .=
            '<li data-n="' .
            $number .
            '" tabindex="0"><b class="n" aria-hidden="true">' .
            $number .
            '</b><span><span class="screen-reader-text">Markierung ' .
            $number .
            ': </span>' .
            help_text($mark['text']) .
            '</span></li>';
    }
    $img =
        '<div class="hw-buehne"><img src="' .
        esc_url(help_media_url($shot . '.webp')) .
        '" width="' .
        $w .
        '" height="' .
        $h .
        '" alt="' .
        esc_attr(help_shot_alt($step)) .
        '" decoding="async"' .
        ($number > 3 ? ' loading="lazy"' : '') .
        '>' .
        ($svg !== ''
            ? '<svg class="hw-anno" viewBox="0 0 ' . $w . ' ' . $h . '" aria-hidden="true">' . $svg . '</svg>'
            : '') .
        '</div>';
    return help_window(
        $img,
        'eintrikot.de/community-portal · ' . ($step['where'] ?? 'Portal'),
        'So sieht es im Portal aus',
        'figure',
        ' id="' . esc_attr($id) . '"'
    ) .
        ($legend !== ''
            ? '<ol class="hw-legende" data-for="' . esc_attr($id) . '">' . $legend . '</ol>'
            : '');
}

function help_video($video) {
    $sources = '';
    foreach (['webm' => 'video/webm', 'mp4' => 'video/mp4'] as $ext => $type) {
        if (is_readable(__DIR__ . '/help/' . $video['file'] . '.' . $ext)) {
            $sources .=
                '<source src="' .
                esc_url(help_media_url($video['file'] . '.' . $ext)) .
                '" type="' .
                $type .
                '">';
        }
    }
    if ($sources === '') {
        return '';
    }
    $poster = isset($video['poster'])
        ? ' poster="' . esc_url(help_media_url($video['poster'] . '.webp')) . '"'
        : '';
    return '<div class="hw-video">' .
        help_window(
            '<div class="hw-buehne"><video muted loop playsinline autoplay preload="metadata"' .
                $poster .
                ' aria-label="' .
                esc_attr($video['label']) .
                '">' .
                $sources .
                '</video></div>',
            'eintrikot.de/community-portal',
            'Video · ohne Ton'
        ) .
        '<div class="hw-videobar"><span>Die Schritte unten zeigen dasselbe zum Nachlesen.</span><button type="button" class="hw-vtoggle" hidden>Pause</button></div></div>';
}

function help_icon($topic) {
    return '<span class="hw-ic" style="background:' .
        esc_attr($topic['color']) .
        '" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="#111" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' .
        $topic['icon'] . // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG paths from help_topics()
        '</svg></span>';
}

function help_count_label($articles) {
    $n = count($articles);
    $videos = count(array_filter($articles, fn($a) => !empty($a['video'])));
    return $n .
        ($n === 1 ? ' Anleitung' : ' Anleitungen') .
        ($videos ? ' · ' . $videos . ($videos === 1 ? ' Video' : ' Videos') : '');
}

function help_search_form($q = '') {
    return '<form class="hw-suche" role="search" method="get" action="' .
        esc_url(get_permalink((int) get_option('eintrikot_portal_page'))) .
        '"><input type="hidden" name="view" value="hilfe">' .
        implode(
            '',
            array_map(
                fn($k, $v) => '<input type="hidden" name="' .
                    esc_attr($k) .
                    '" value="' .
                    esc_attr($v) .
                    '">',
                array_keys(help_back_args()),
                help_back_args()
            )
        ) .
        '<label class="screen-reader-text" for="hw-q">Hilfe durchsuchen</label><input id="hw-q" name="q" type="search" value="' .
        esc_attr($q) .
        '" placeholder="z. B. Termin, Profilbild, Passwort" maxlength="80"><button class="hw-knopf voll" type="submit">Suchen</button></form>';
}

/** Lower case without accents, so „Pruefung“, „prüfung“ and „Prüfung“ all match. */
function help_normalize($text) {
    $text = mb_strtolower(wp_strip_all_tags((string) $text));
    return strtr($text, [
        'ä' => 'a',
        'ö' => 'o',
        'ü' => 'u',
        'ß' => 'ss',
        'ae' => 'a',
        'oe' => 'o',
        'ue' => 'u'
    ]);
}

/** Every word must appear in the article. Title hits come first. */
function help_search($q) {
    $words = array_filter(preg_split('/\s+/u', help_normalize($q)) ?: [], fn($w) => mb_strlen($w) > 1);
    if (!$words) {
        return [];
    }
    $hits = [];
    foreach (help_visible_articles() as $slug => $a) {
        $title = help_normalize($a['title']);
        $hay = $title . ' ' . help_normalize($a['lead'] . ' ' . ($a['keywords'] ?? ''));
        foreach ($a['steps'] as $s) {
            $hay .= ' ' . help_normalize($s['title'] . ' ' . $s['text']);
            foreach ($s['marks'] ?? [] as $m) {
                $hay .= ' ' . help_normalize($m['text']);
            }
        }
        foreach ($a['boxes'] ?? [] as $b) {
            $hay .= ' ' . help_normalize($b['title'] . ' ' . implode(' ', $b['items']));
        }
        $score = 0;
        foreach ($words as $w) {
            if (!str_contains($hay, $w)) {
                continue 2;
            }
            $score += str_contains($title, $w) ? 3 : 1;
        }
        $hits[$slug] = $score;
    }
    arsort($hits);
    return array_keys($hits);
}

function help_article_link($slug, $a) {
    return '<a class="hw-zeile" href="' .
        esc_url(help_article_url($slug)) .
        '"><span><strong>' .
        esc_html($a['title']) .
        '</strong><small>' .
        help_text($a['lead']) .
        '</small></span><span aria-hidden="true">' .
        (!empty($a['video']) ? '▶ ' : '') .
        '→</span></a>';
}

function help_render_start() {
    $articles = help_visible_articles();
    $member = member_access();
    $html =
        '<div class="hw-wrap hw-start"><h1>Wie können wir helfen?</h1><p class="hw-kurz">Kurze Anleitungen mit Bildern und Videos – für alles, was du im Mitgliederportal tun kannst.</p>' .
        help_search_form();
    if (!$member) {
        $html .=
            '<p class="hw-hinweis">Ohne Anmeldung siehst du hier nur die Hilfe zur Anmeldung. Alle anderen Anleitungen findest du, sobald du angemeldet bist.</p>';
    }
    $popular = array_filter(
        ['termin-anmelden', 'sichtbarkeit', 'mitglied-finden', 'anmelden'],
        fn($s) => isset($articles[$s])
    );
    if ($popular) {
        $html .= '<div class="hw-chips"><span>Oft gesucht:</span>';
        foreach ($popular as $slug) {
            $html .=
                '<a href="' .
                esc_url(help_article_url($slug)) .
                '">' .
                esc_html($articles[$slug]['title']) .
                '</a>';
        }
        $html .= '</div>';
    }
    $html .= '<div class="hw-themen">';
    foreach (help_topics() as $key => $topic) {
        $in = array_filter($articles, fn($a) => $a['topic'] === $key);
        if (!$in) {
            continue;
        }
        $html .=
            '<a class="hw-thema" href="' .
            esc_url(help_url(['thema' => $key])) .
            '">' .
            help_icon($topic) .
            '<strong>' .
            esc_html($topic['title']) .
            '</strong><span>' .
            esc_html($topic['hint']) .
            '</span><small>' .
            esc_html(help_count_label($in)) .
            '</small></a>';
    }
    $html .= '</div>';
    if (isset($articles['rundgang'])) {
        $html .=
            '<div class="hw-rundgang">' .
            help_window(
                '<div class="hw-buehne"><img src="' .
                    esc_url(help_media_url('start-ueberblick.webp')) .
                    '" width="1280" height="800" alt="Bildschirmfoto: Startseite des Portals" loading="lazy"></div>',
                'eintrikot.de/community-portal',
                ''
            ) .
            '<div><span class="hw-eyebrow">Neu hier?</span><h2>Das Portal auf einen Blick</h2><p>Was auf „Start“ steht, wo die vier Bereiche sind und wo du dein Profil findest – in sechs Punkten.</p><a class="hw-knopf voll" href="' .
            esc_url(help_article_url('rundgang')) .
            '">Rundgang ansehen →</a></div></div>';
    }
    return $html . '</div>';
}

function help_render_topic($key) {
    $topic = help_topics()[$key];
    $in = array_filter(help_visible_articles(), fn($a) => $a['topic'] === $key);
    $html =
        '<div class="hw-wrap hw-liste"><nav class="hw-brot" aria-label="Brotkrumen"><a href="' .
        esc_url(help_url()) .
        '">Hilfe</a> › <span>' .
        esc_html($topic['title']) .
        '</span></nav><h1>' .
        help_icon($topic) .
        esc_html($topic['title']) .
        '</h1><div class="hw-zeilen">';
    foreach ($in as $slug => $a) {
        $html .= help_article_link($slug, $a);
    }
    if (!$in) {
        $html .= '<p>Zu diesem Thema gibt es noch keine Anleitung.</p>';
    }
    return $html . '</div></div>';
}

function help_render_results($q) {
    $articles = help_visible_articles();
    $hits = help_search($q);
    $html =
        '<div class="hw-wrap hw-liste"><nav class="hw-brot" aria-label="Brotkrumen"><a href="' .
        esc_url(help_url()) .
        '">Hilfe</a> › <span>Suche</span></nav><h1>Suche nach „' .
        esc_html($q) .
        '“</h1>' .
        help_search_form($q) .
        '<p class="hw-treffer" role="status">' .
        (count($hits) === 1 ? '1 Anleitung gefunden.' : count($hits) . ' Anleitungen gefunden.') .
        '</p><div class="hw-zeilen">';
    foreach ($hits as $slug) {
        $html .= help_article_link($slug, $articles[$slug]);
    }
    $html .= '</div>';
    if (!$hits) {
        $html .=
            '<p>Probier ein anderes Wort, zum Beispiel „Termin“, „Profil“ oder „Passwort“ – oder schau in die <a class="hw-link" href="' .
            esc_url(help_url()) .
            '">Themen</a>.</p>';
    }
    return $html . help_still_stuck() . '</div>';
}

function help_still_stuck() {
    if (!member_access()) {
        return '<div class="hw-fuss"><p>Kommst du nicht weiter? Schreib uns an <a class="hw-link" href="mailto:info@eintrikot.de">info@eintrikot.de</a>.</p></div>';
    }
    return '<div class="hw-fuss"><p>Hat es nicht geklappt? Schreib uns über <b>Service → Vorstand kontaktieren</b>.</p><a class="hw-knopf" href="' .
        esc_url(portal_url('service', ['service' => 'contact'])) .
        '">Vorstand kontaktieren →</a></div>';
}

function help_render_article($slug) {
    $all = help_articles();
    $a = $all[$slug];
    if (empty($a['public']) && !member_access()) {
        return '<div class="hw-wrap hw-liste"><h1>' .
            esc_html($a['title']) .
            '</h1><p>Diese Anleitung siehst du, sobald du im Mitgliederportal angemeldet bist.</p><a class="hw-knopf voll" href="' .
            esc_url(portal_url()) .
            '">Zur Anmeldung</a></div>';
    }
    $topics = help_topics();
    $topic = $topics[$a['topic']];
    $visible = help_visible_articles();
    $side = '';
    foreach ($visible as $s => $other) {
        if ($other['topic'] !== $a['topic']) {
            continue;
        }
        $side .=
            '<a href="' .
            esc_url(help_article_url($s)) .
            '"' .
            ($s === $slug ? ' class="akt" aria-current="page"' : '') .
            '>' .
            esc_html($other['title']) .
            '</a>';
    }
    $meta = '<span>⏱ ' . (int) $a['minutes'] . ($a['minutes'] === 1 ? ' Minute' : ' Minuten') . '</span>';
    if (!empty($a['video'])) {
        $meta .= '<span>▶ Video ' . (int) $a['video']['seconds'] . ' Sek.</span>';
    }
    $meta .= '<span>Handy &amp; Computer</span>';
    $body =
        '<nav class="hw-brot" aria-label="Brotkrumen"><a href="' .
        esc_url(help_url()) .
        '">Hilfe</a> › <a href="' .
        esc_url(help_url(['thema' => $a['topic']])) .
        '">' .
        esc_html($topic['title']) .
        '</a></nav><h1>' .
        esc_html($a['title']) .
        '</h1><p class="hw-kurz">' .
        help_text($a['lead']) .
        '</p><div class="hw-meta">' .
        $meta .
        '</div>';
    if (!empty($a['video'])) {
        $video = help_video($a['video']);
        $body .= $video !== '' ? $video . '<div class="hw-oder">Schritt für Schritt</div>' : '';
    }
    $number = 0;
    foreach ($a['steps'] as $i => $step) {
        $body .=
            '<section class="hw-schritt"><div class="hw-zahl" aria-hidden="true">' .
            ($i + 1) .
            '</div><div><h2><span class="screen-reader-text">Schritt ' .
            ($i + 1) .
            ': </span>' .
            esc_html($step['title']) .
            '</h2><p>' .
            help_text($step['text']) .
            '</p>' .
            (!empty($step['shot']) ? help_figure($step, $number, 'abb-' . $slug . '-' . ($i + 1)) : '') .
            '</div></section>';
    }
    foreach ($a['boxes'] ?? [] as $box) {
        $body .=
            '<aside class="hw-box ' .
            esc_attr($box['kind']) .
            '"><h2>' .
            esc_html($box['title']) .
            '</h2><ul>';
        foreach ($box['items'] as $item) {
            $body .= '<li>' . help_text($item) . '</li>';
        }
        $body .= '</ul></aside>';
    }
    if (!empty($a['phone'])) {
        $body .=
            '<section class="hw-handy-teil"><h2>Am Handy</h2><div class="hw-handy"><div class="hw-telefon"><img src="' .
            esc_url(help_media_url($a['phone']['shot'] . '.webp')) .
            '" width="390" height="780" alt="Bildschirmfoto am Handy" loading="lazy"></div><p>' .
            help_text($a['phone']['text']) .
            '</p></div></section>';
    }
    $body .= help_still_stuck();
    $related = array_filter($a['related'] ?? [], fn($s) => isset($visible[$s]));
    if ($related) {
        $body .= '<nav class="hw-weiter" aria-label="Passende Anleitungen">';
        foreach ($related as $s) {
            $body .=
                '<a href="' .
                esc_url(help_article_url($s)) .
                '">' .
                esc_html($visible[$s]['title']) .
                ' <span aria-hidden="true">→</span></a>';
        }
        $body .= '</nav>';
    }
    return '<div class="hw-wrap hw-artikel' .
        (!empty($a['dark']) ? ' hw-dunkel' : '') .
        '"><nav class="hw-seitennav" aria-label="' .
        esc_attr($topic['title']) .
        '"><p>' .
        esc_html($topic['title']) .
        '</p>' .
        $side .
        '</nav><article>' .
        $body .
        '</article></div>';
}

/** The whole help page, without the portal shell: own header, dotted background. */
function render_help() {
    $member = member_access();
    $slug = sanitize_key(directory_param('artikel'));
    $topic = sanitize_key(directory_param('thema'));
    $q = trim(directory_param('q'));
    if ($slug !== '' && isset(help_articles()[$slug])) {
        $content = help_render_article($slug);
    } elseif ($topic !== '' && isset(help_topics()[$topic])) {
        $content = help_render_topic($topic);
    } elseif ($q !== '') {
        $content = help_render_results(mb_substr($q, 0, 80));
    } else {
        $content = help_render_start();
    }
    $back = help_back_args();
    $back_url = $member
        ? portal_url($back['von'] ?? 'portal', isset($back['von_tab']) ? ['tab' => $back['von_tab']] : [])
        : portal_url();
    return '<div class="et-app et-help"><a class="skip-link" href="#hilfe-inhalt">Zum Inhalt springen</a><header class="hw-kopf"><div class="hw-wrap"><a class="hw-marke" href="' .
        esc_url(help_url()) .
        '" aria-label="EINTRIKOT Hilfe – Startseite der Hilfe"><img src="' .
        esc_url(get_theme_file_uri('assets/logo.svg')) .
        '" alt="EINTRIKOT" width="120" height="30"><span class="hw-tag">Hilfe</span></a><a class="hw-knopf" href="' .
        esc_url($back_url) .
        '">' .
        ($member ? '← Zurück ins Portal' : '← Zur Anmeldung') .
        '</a></div></header><main id="hilfe-inhalt" tabindex="-1">' .
        $content .
        '</main><footer class="hw-ende"><div class="hw-wrap">EINTRIKOT e. V. · Hilfe für Mitglieder</div></footer></div>';
}

/** Small „?“ button in the portal header. Opens the article that fits the current page. */
function help_button($view) {
    $tab = $view === 'aktuelles' && directory_param('tab') === 'termine' ? 'termine' : '';
    $slug = help_context_article($view, $tab);
    $args = in_array($view, HELP_BACK_VIEWS, true) ? ['von' => $view] : [];
    if ($tab !== '') {
        $args['von_tab'] = $tab;
    }
    if ($slug !== '') {
        $args['artikel'] = $slug;
    }
    return '<a class="help-button" href="' .
        esc_url(portal_url('hilfe', $args)) .
        '"><span aria-hidden="true">?</span>Hilfe<span class="screen-reader-text"> zu dieser Seite</span></a>';
}

add_action('wp_enqueue_scripts', function () {
    $page = (int) get_option('eintrikot_portal_page');
    if ($page <= 0 || !is_page($page) || current_view() !== 'hilfe') {
        return;
    }
    wp_enqueue_style('eintrikot-help', plugins_url('help.css', __FILE__), [], asset_version('help.css'));
    wp_enqueue_script('eintrikot-help', plugins_url('help.js', __FILE__), [], asset_version('help.js'), true);
});
