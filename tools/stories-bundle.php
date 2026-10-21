<?php
/**
 * Bereitet die alten NDAlumni-Beiträge für den Stories-Import vor.
 *
 *   WP_ROOT=.lokal/wordpress php tools/stories-bundle.php "<Ordner News sammeln Eintrikot>" [--ausgabe=<Ordner>] [--max-mb=25]
 *
 * Liest <Ordner>/wordpress-import/bundle/posts.json samt Bildern und schreibt in den Ausgabeordner (Standard:
 * ~/Downloads/Stories-Import, nie im Repository):
 *   - stories-1.zip, stories-2.zip …   Pakete für Community-Aufbau → Stories importieren (je höchstens --max-mb MB)
 *   - Pruefbericht.txt                 Zuordnung, Hinweise, Links auf die alte Seite
 *
 * Aufgeräumt wird: Titel und Adressen, Rubrik, Inhalt als Blöcke, Bilder auf höchstens 2000 px, Alternativtexte,
 * Vorschautext, Serienbrief-Platzhalter. Die Quelldaten bleiben unverändert.
 */
namespace Eintrikot\Community;

if (PHP_SAPI !== 'cli') {
    exit('Nur auf der Kommandozeile.');
}
$source = '';
$options = [];
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--')) {
        [$k, $v] = array_pad(explode('=', substr($arg, 2), 2), 2, '');
        $options[$k] = $v;
    } else {
        $source = rtrim($arg, '/');
    }
}
$bundle_dir = $source . '/wordpress-import/bundle';
if ($source === '' || !is_file($bundle_dir . '/posts.json')) {
    fwrite(STDERR, "Aufruf: php tools/stories-bundle.php \"<Ordner News sammeln Eintrikot>\" [--ausgabe=<Ordner>] [--max-mb=25]\n");
    exit(2);
}
$wp_root = getenv('WP_ROOT') ?: dirname(__DIR__) . '/.lokal/wordpress';
if (!is_file($wp_root . '/wp-load.php')) {
    fwrite(STDERR, "Kein lokales WordPress unter $wp_root (tools/lokal-test.sh setup).\n");
    exit(2);
}
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require $wp_root . '/wp-load.php';
require_once __DIR__ . '/stories-lib.php';

$out_dir = $options['ausgabe'] ?? getenv('HOME') . '/Downloads/Stories-Import';
$max_bytes = (int) ($options['max-mb'] ?? 25) * 1024 * 1024;
if (!is_dir($out_dir) && !mkdir($out_dir, 0700, true)) {
    fwrite(STDERR, "Ausgabeordner nicht anlegbar: $out_dir\n");
    exit(1);
}

/** Picture file for the package: at most 2000 px on the long side; smaller files are used as they are. */
function stories_prepare_image($file) {
    $info = @getimagesize($file);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
        return [null, 'kein JPEG/PNG'];
    }
    [$w, $h, $type] = $info;
    if (max($w, $h) <= 2000) {
        return [file_get_contents($file), ''];
    }
    $scale = 2000 / max($w, $h);
    $nw = (int) round($w * $scale);
    $nh = (int) round($h * $scale);
    $src = $type === IMAGETYPE_PNG ? imagecreatefrompng($file) : imagecreatefromjpeg($file);
    $dst = imagecreatetruecolor($nw, $nh);
    if ($type === IMAGETYPE_PNG) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    ob_start();
    $type === IMAGETYPE_PNG ? imagepng($dst, null, 7) : imagejpeg($dst, null, 85);
    return [ob_get_clean(), ''];
}

$data = json_decode((string) file_get_contents($bundle_dir . '/posts.json'), true);
$posts = is_array($data['posts'] ?? null) ? $data['posts'] : [];
usort($posts, fn($a, $b) => strcmp($a['post_date'], $b['post_date']));
$used_slugs = [];
$items = [];
$report = [];
$old_links = [];
$by_rubric = [];
foreach ($posts as $post) {
    $title = stories_clean_title($post['title']);
    $slug = stories_slug($title, $used_slugs);
    $rubric = stories_rubric($title);
    [$content, $paths, $warnings] = stories_html_to_blocks($post['content_html'], $title);
    $files = [];
    foreach (array_values(array_unique($paths)) as $path) {
        $file = $bundle_dir . '/' . ltrim($path, '/');
        if (!is_file($file)) {
            $warnings[] = 'Bild fehlt: ' . $path;
            $content = str_replace(['{{id:' . $path . '}}', '{{url:' . $path . '}}'], ['0', ''], $content);
            continue;
        }
        [$bytes, $problem] = stories_prepare_image($file);
        if ($bytes === null) {
            $warnings[] = "Bild nicht brauchbar ($problem): $path";
            continue;
        }
        $files[$path] = $bytes;
    }
    $content = preg_replace('#<!-- wp:image \{"id":0[^}]*\} -->.*?<!-- /wp:image -->\s*#s', '', $content);
    $featured = $post['featured_image'] && isset($files[$post['featured_image']]) ? $post['featured_image'] : (array_key_first($files) ?: '');
    // The title picture is shown at the top of the story; when the text starts with it, drop that copy.
    if ($featured !== '') {
        $lead = '{{id:' . $featured . '}}';
        if (preg_match('#^<!-- wp:image \{"id":' . preg_quote($lead, '#') . '.*?<!-- /wp:image -->\s*#s', $content, $first)) {
            $content = substr($content, strlen($first[0]));
        }
    }
    if (preg_match('/\{[A-Za-z]+\}/', $content)) {
        $warnings[] = 'Platzhalter {…} im Text';
    }
    if (trim(wp_strip_all_tags($content)) === '') {
        $warnings[] = 'Kein Text';
    }
    preg_match_all('#href="(https?://(?:www\.)?eintrikot\.de[^"]*)"#', $content, $m);
    foreach ($m[1] as $link) {
        $old_links[] = [$title, $link];
    }
    $items[] = [
        'meta' => [
            'title' => $title,
            'slug' => $slug,
            'date' => $post['post_date'],
            'rubric' => $rubric,
            'content' => $content,
            'excerpt' => stories_excerpt($content),
            'source_url' => $post['source_url'],
            'featured' => $featured,
            'images' => array_map(fn($p) => ['path' => $p, 'alt' => $title], array_keys($files))
        ],
        'files' => $files,
        'size' => array_sum(array_map('strlen', $files))
    ];
    $by_rubric[$rubric] = ($by_rubric[$rubric] ?? 0) + 1;
    $report[] = sprintf('%s | %-10s | %2d Bild(er) | %s%s', substr($post['post_date'], 0, 10), $rubric, count($files), $title, $warnings ? '  [' . implode('; ', $warnings) . ']' : '');
}

// Split into packages of at most --max-mb of pictures each.
$parts = [[]];
$sum = 0;
foreach ($items as $item) {
    if ($sum > 0 && $sum + $item['size'] > $max_bytes) {
        $parts[] = [];
        $sum = 0;
    }
    $parts[array_key_last($parts)][] = $item;
    $sum += $item['size'];
}
foreach (glob($out_dir . '/stories-*.zip') ?: [] as $old) {
    unlink($old);
}
$written = [];
foreach ($parts as $n => $part) {
    $zip_file = $out_dir . '/stories-' . ($n + 1) . '.zip';
    $zip = new \ZipArchive();
    if ($zip->open($zip_file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
        fwrite(STDERR, "ZIP nicht schreibbar: $zip_file\n");
        exit(1);
    }
    $zip->addFromString('stories.json', wp_json_encode(['schema' => 1, 'posts' => array_column($part, 'meta')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    foreach ($part as $item) {
        foreach ($item['files'] as $path => $bytes) {
            $zip->addFromString($path, $bytes);
        }
    }
    $zip->close();
    $written[] = [basename($zip_file), count($part), round(filesize($zip_file) / 1048576, 1)];
}

$lines = ['Prüfbericht Stories-Import, erstellt ' . wp_date('d.m.Y H:i'), '', count($items) . ' Beiträge, ' . count($parts) . ' Paket(e):'];
foreach ($written as [$name, $count, $mb]) {
    $lines[] = "   $name: $count Beiträge, $mb MB";
}
$lines[] = '';
$lines[] = 'Rubriken: ' . implode(', ', array_map(fn($k, $v) => "$k $v", array_keys($by_rubric), $by_rubric));
$lines[] = '';
$lines[] = 'Beiträge (alle werden als Entwurf angelegt):';
foreach ($report as $line) {
    $lines[] = '   ' . $line;
}
$lines[] = '';
$lines[] = 'Links auf eintrikot.de (zeigen heute auf die alte Seite, nach dem Umzug prüfen): ' . count($old_links);
foreach ($old_links as [$t, $l]) {
    $lines[] = '   ' . mb_substr($t, 0, 40) . ' → ' . $l;
}
file_put_contents($out_dir . '/Pruefbericht.txt', implode("\n", $lines) . "\n");
echo implode("\n", array_slice($lines, 0, 6 + count($written))) . "\nBericht: $out_dir/Pruefbericht.txt\n";
