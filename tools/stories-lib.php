<?php
/**
 * Hilfsfunktionen für die Übernahme der alten NDAlumni-Beiträge als Stories.
 * Wird von tools/stories-bundle.php und den Regeltests geladen; wird nicht auf die Seite eingespielt.
 */
namespace Eintrikot\Community;

if (!defined('ABSPATH')) {
    exit();
}

/** Title of a story: entities decoded, spaces collapsed, series prefix gone, "..." as an ellipsis. */
function stories_clean_title($title) {
    $title = html_entity_decode(wp_strip_all_tags((string) $title), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $title = preg_replace('/^\(M\)\s*EinTrikot\s*[–-]\s*/iu', '', $title);
    $title = str_replace(['...', '´'], ['…', '’'], $title);
    $title = preg_replace('/…(?=[^\s?!.,:;])/u', '… ', $title);
    $title = preg_replace('/\s+’\s*(?=s\b)/u', '’', $title);
    $title = preg_replace('/"([^"]+)"/u', '„$1“', $title);
    $title = preg_replace('/(\w)&\s/u', '$1 – ', $title);
    $title = preg_replace('/^News\s*[–-]\s*/u', 'Newsletter ', $title);
    $title = preg_replace('/\s+/u', ' ', trim($title));
    return $title;
}

/** Short address part: ä to ae, lower case, words joined by hyphens, at most 70 characters. */
function stories_slug($title, array &$used = []) {
    $map = ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue', 'ß' => 'ss'];
    $slug = strtolower(remove_accents(strtr(wp_strip_all_tags($title), $map)));
    $slug = trim(preg_replace('/[^a-z0-9]+/', '-', $slug), '-');
    if (strlen($slug) > 70) {
        $slug = substr($slug, 0, 70);
        $slug = substr($slug, 0, (int) strrpos($slug, '-') ?: 70);
    }
    $slug = $slug !== '' ? $slug : 'story';
    $base = $slug;
    for ($i = 2; isset($used[$slug]); $i++) {
        $slug = $base . '-' . $i;
    }
    $used[$slug] = true;
    return $slug;
}

/** Category slug (see story_categories()) from the title. Everything not named here is "verein". */
function stories_rubric($title) {
    $t = mb_strtolower($title);
    $has = fn(array $words) => (bool) array_filter($words, fn($w) => str_contains($t, $w));
    if ($has(['eigentlich', 'beirat', 'danke, wibke', 'nike rühr'])) {
        return 'menschen';
    }
    if ($has(['alumni-meetup', 'bridge', 'netzwerk-coach'])) {
        return 'treffen';
    }
    if ($has(['europameisterschaft', 'u21', 'proleague', 'pro league', 'jugend dm', 'kabine', 'axel'])) {
        return 'teams';
    }
    if ($has(['bundesliga', 'podcast', 'hockeyjahr', '„oly“'])) {
        return 'hockeywelt';
    }
    return 'verein';
}

/** Plain-text teaser of about 28 words from the first paragraphs. */
function stories_excerpt($html) {
    $text = html_entity_decode(wp_strip_all_tags(preg_replace('#<br\s*/?>#i', ' ', (string) $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = trim(preg_replace('/\s+/u', ' ', str_replace("\xc2\xa0", ' ', $text)));
    $words = explode(' ', $text);
    if (count($words) <= 28) {
        return $text;
    }
    return rtrim(implode(' ', array_slice($words, 0, 28)), ',;:–-') . '…';
}

/** Inline content of a node: text, line breaks, bold, italic and links with a safe address only. */
function stories_inline(\DOMNode $node) {
    $out = '';
    foreach ($node->childNodes as $child) {
        if ($child instanceof \DOMText) {
            $out .= esc_html(str_replace("\xc2\xa0", ' ', $child->nodeValue));
            continue;
        }
        if (!($child instanceof \DOMElement)) {
            continue;
        }
        $name = strtolower($child->nodeName);
        $inner = stories_inline($child);
        if ($name === 'br') {
            $out .= '<br>';
        } elseif (in_array($name, ['strong', 'b'], true) && trim($inner) !== '') {
            $out .= '<strong>' . $inner . '</strong>';
        } elseif (in_array($name, ['em', 'i'], true) && trim($inner) !== '') {
            $out .= '<em>' . $inner . '</em>';
        } elseif ($name === 'a') {
            $href = trim($child->getAttribute('href'));
            if ($href !== '' && preg_match('#^(https?://|mailto:|/)#i', $href) && trim($inner) !== '') {
                $external = preg_match('#^https?://#i', $href);
                $out .= '<a href="' . esc_url($href) . '"' . ($external ? ' rel="noopener noreferrer"' : '') . '>' . $inner . '</a>';
            } else {
                $out .= $inner;
            }
        } elseif ($name !== 'img') {
            $out .= $inner;
        }
    }
    return $out;
}

/** Tidies inline html: no leading or trailing line breaks, one space between words. */
function stories_tidy($html) {
    $html = preg_replace('/(<br>\s*)+$/u', '', preg_replace('/^(\s*<br>)+/u', '', $html));
    $html = preg_replace('/\s*<br>\s*/u', '<br>', $html);
    return trim(preg_replace('/[ \t\r\n]+/u', ' ', $html));
}

function stories_image_block($path, $alt) {
    return '<!-- wp:image {"id":{{id:' . $path . '}},"sizeSlug":"large","linkDestination":"none"} -->' . "\n" .
        '<figure class="wp-block-image size-large"><img src="{{url:' . $path . '}}" alt="' . esc_attr($alt) . '" class="wp-image-{{id:' . $path . '}}"/></figure>' . "\n" .
        '<!-- /wp:image -->';
}

/**
 * Old NDAlumni html into block markup. Images become image blocks with the placeholders {{id:PATH}} and
 * {{url:PATH}}, which the importer fills in once the picture is in the media library.
 * Returns [markup, image paths in order, warnings].
 */
function stories_html_to_blocks($html, $alt) {
    $warnings = [];
    $images = [];
    $doc = new \DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="root">' . $html . '</div>');
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $root = $doc->getElementById('root');
    $blocks = [];
    $buttons = [];
    $flush = function () use (&$blocks, &$buttons) {
        if ($buttons) {
            $blocks[] = '<!-- wp:buttons -->' . "\n" . '<div class="wp-block-buttons">' . implode('', $buttons) . '</div>' . "\n" . '<!-- /wp:buttons -->';
            $buttons = [];
        }
    };
    $image = function (\DOMElement $img) use (&$blocks, &$images, $alt, $flush) {
        $path = $img->getAttribute('data-eintrikot-media') ?: $img->getAttribute('src');
        if ($path === '') {
            return;
        }
        $flush();
        $images[] = $path;
        $blocks[] = stories_image_block($path, count($images) > 1 ? $alt . ' (Bild ' . count($images) . ')' : $alt);
    };
    $walk = function (\DOMNode $parent) use (&$walk, &$blocks, &$buttons, &$warnings, $image, $flush) {
        foreach ($parent->childNodes as $node) {
            if ($node instanceof \DOMText) {
                $text = trim(str_replace("\xc2\xa0", ' ', $node->nodeValue));
                if ($text !== '') {
                    $flush();
                    $blocks[] = '<!-- wp:paragraph -->' . "\n" . '<p>' . esc_html($text) . '</p>' . "\n" . '<!-- /wp:paragraph -->';
                }
                continue;
            }
            if (!($node instanceof \DOMElement)) {
                continue;
            }
            $name = strtolower($node->nodeName);
            if ($name === 'img') {
                $image($node);
            } elseif ($name === 'p') {
                foreach ($node->getElementsByTagName('img') as $img) {
                    $image($img);
                }
                $links = $node->getElementsByTagName('a');
                $first = $links->length === 1 ? $links->item(0) : null;
                $text = trim(str_replace("\xc2\xa0", ' ', $node->textContent));
                if ($first && str_contains(' ' . $first->getAttribute('class') . ' ', ' btn ') && trim($first->textContent) === $text) {
                    $href = trim($first->getAttribute('href'));
                    if (preg_match('#^(https?://|mailto:|/)#i', $href)) {
                        $buttons[] = '<!-- wp:button {"className":"button"} -->' . "\n" . '<div class="wp-block-button button"><a class="wp-block-button__link wp-element-button" href="' . esc_url($href) . '" rel="noopener noreferrer">' . esc_html($text) . '</a></div>' . "\n" . '<!-- /wp:button -->';
                        continue;
                    }
                }
                $inline = stories_tidy(stories_inline($node));
                // Mail-merge salutation of the old newsletter.
                $inline = preg_replace('/^Hallo \{[A-Za-z]+\},?(<br>)?\s*/u', '', $inline, -1, $merged);
                if ($merged) {
                    $warnings[] = 'Serienbrief-Anrede entfernt';
                    $inline = ucfirst(trim($inline));
                }
                if (trim(wp_strip_all_tags($inline)) !== '') {
                    $flush();
                    $blocks[] = '<!-- wp:paragraph -->' . "\n" . '<p>' . $inline . '</p>' . "\n" . '<!-- /wp:paragraph -->';
                }
            } elseif (in_array($name, ['h1', 'h2', 'h3', 'h4', 'h5'], true)) {
                $level = in_array($name, ['h1', 'h2'], true) ? 2 : 3;
                $inline = stories_tidy(stories_inline($node));
                if (trim(wp_strip_all_tags($inline)) !== '') {
                    $flush();
                    $blocks[] = '<!-- wp:heading {"level":' . $level . '} -->' . "\n" . '<h' . $level . ' class="wp-block-heading">' . $inline . '</h' . $level . '>' . "\n" . '<!-- /wp:heading -->';
                }
            } elseif (in_array($name, ['ul', 'ol'], true)) {
                $items = '';
                foreach ($node->childNodes as $li) {
                    if ($li instanceof \DOMElement && strtolower($li->nodeName) === 'li') {
                        $inline = stories_tidy(stories_inline($li));
                        if (trim(wp_strip_all_tags($inline)) !== '') {
                            $items .= '<!-- wp:list-item -->' . "\n" . '<li>' . $inline . '</li>' . "\n" . '<!-- /wp:list-item -->';
                        }
                    }
                }
                if ($items !== '') {
                    $flush();
                    $ordered = $name === 'ol';
                    $blocks[] = '<!-- wp:list' . ($ordered ? ' {"ordered":true}' : '') . ' -->' . "\n" . '<' . $name . ' class="wp-block-list">' . $items . '</' . $name . '>' . "\n" . '<!-- /wp:list -->';
                }
            } elseif (in_array($name, ['div', 'section', 'article', 'span', 'figure', 'table', 'tbody', 'tr', 'td', 'blockquote'], true)) {
                $walk($node);
            } elseif ($name !== 'br' && trim($node->textContent) !== '') {
                $warnings[] = 'Element <' . $name . '> als Text übernommen';
                $flush();
                $blocks[] = '<!-- wp:paragraph -->' . "\n" . '<p>' . stories_tidy(stories_inline($node)) . '</p>' . "\n" . '<!-- /wp:paragraph -->';
            }
        }
    };
    if ($root) {
        $walk($root);
    }
    $flush();
    return [implode("\n\n", $blocks), $images, array_values(array_unique($warnings))];
}
