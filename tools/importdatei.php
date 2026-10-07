<?php
/**
 * Erzeugt aus der Master-Tabelle (Excel-Download) die bereinigte Importdatei für das Portal.
 *
 *   WP_ROOT=.lokal/wordpress php tools/importdatei.php "<Master.xlsx>" [--ausgabe=<Ordner>] [--eintritt=<CSV>]
 *
 * Voraussetzung ist das lokale WordPress aus tools/lokal-test.sh (nutzt die Import-Funktionen des Plugins).
 *
 * Ergebnis im Ausgabeordner (Standard: ~/Downloads/Portal-Import, nie im Repository):
 *   - Portal-Import.xlsx   zwei Blätter, "Export WisoMV" und "Import_Roh", nur mit den Spalten, die das Portal braucht
 *   - Pruefbericht.txt     Zahlen und offene Punkte, ohne Namen
 *
 * Nicht übernommen werden IBAN, Mandat, Anschrift, Telefon, Geburtsname, Notizen, Tarif und alles andere, was das
 * Portal nicht braucht. Korrekturen kommen aus den Blättern "Team-Zuordnung" und "Stationen Staff" der Master.
 */
namespace Eintrikot\Community;

if (PHP_SAPI !== 'cli') {
    exit('Nur auf der Kommandozeile.');
}
$args = array_slice($argv, 1);
$master = '';
$options = [];
foreach ($args as $arg) {
    if (str_starts_with($arg, '--')) {
        [$k, $v] = array_pad(explode('=', substr($arg, 2), 2), 2, '');
        $options[$k] = $v;
    } else {
        $master = $arg;
    }
}
if ($master === '' || !is_file($master)) {
    fwrite(STDERR, "Aufruf: php tools/importdatei.php \"<Master.xlsx>\" [--ausgabe=<Ordner>] [--eintritt=<CSV>]\n");
    exit(2);
}
$wp_root = getenv('WP_ROOT') ?: dirname(__DIR__) . '/.lokal/wordpress';
if (!is_file($wp_root . '/wp-load.php')) {
    fwrite(STDERR, "Kein lokales WordPress unter $wp_root (tools/lokal-test.sh setup).\n");
    exit(2);
}
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require $wp_root . '/wp-load.php';

$out_dir = $options['ausgabe'] ?? (getenv('HOME') . '/Downloads/Portal-Import');
if (!is_dir($out_dir)) {
    mkdir($out_dir, 0700, true);
}

/* ---------- Hilfen ---------- */

/** Writes a simple .xlsx with text cells (inline strings, so nothing is turned into a number or a date). */
function write_simple_xlsx($path, array $sheets) {
    $zip = new \ZipArchive();
    if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
        throw new \RuntimeException('Datei kann nicht geschrieben werden: ' . $path);
    }
    $col = function ($i) {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + (($i - 1) % 26)) . $s;
        }
        return $s;
    };
    $esc = fn($v) => htmlspecialchars(
        (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $v),
        ENT_XML1 | ENT_QUOTES,
        'UTF-8'
    );
    $types = '';
    $wb = '';
    $rels = '';
    $i = 0;
    foreach ($sheets as $name => $rows) {
        $i++;
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach (array_values($rows) as $r => $cells) {
            $xml .= '<row r="' . ($r + 1) . '">';
            foreach (array_values($cells) as $c => $value) {
                if ((string) $value === '') {
                    continue;
                }
                $xml .= '<c r="' . $col($c) . ($r + 1) . '" t="inlineStr"><is><t xml:space="preserve">' . $esc($value) . '</t></is></c>';
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData></worksheet>';
        $zip->addFromString("xl/worksheets/sheet$i.xml", $xml);
        $types .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $wb .= '<sheet name="' . $esc($name) . '" sheetId="' . $i . '" r:id="rId' . $i . '"/>';
        $rels .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
    }
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' . $types . '</Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . $wb . '</sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '</Relationships>');
    $zip->close();
}

function sheet_rows($master, $name) {
    $rows = read_xlsx($master, $name);
    if (is_wp_error($rows)) {
        fwrite(STDERR, "Blatt \"$name\": " . $rows->get_error_message() . "\n");
        return [];
    }
    return $rows;
}
$num = fn($v) => (string) (int) round((float) $v);

/* ---------- Spalten, die das Portal braucht (Schlüssel = normalisierter Spaltenname) ---------- */

$person_columns = [
    'vorname' => 'Vorname',
    'nachname' => 'Nachname',
    'anrede' => 'Anrede',
    'bevorzugteemailadresse' => 'Bevorzugte E-Mail-Adresse',
    'emailadresseprivat' => 'E-Mail-Adresse privat',
    'geburtsdatum' => 'Geburtsdatum',
    'stadtprivat' => 'Stadt privat',
    'landprivat' => 'Land privat',
    'aktuellesvereinsteam' => 'Aktuelles Vereins-Team',
    'mannschaft' => 'Mannschaft',
    'aktuellindernatio' => 'Aktuell in der Natio',
    'anzahlvonnationalspielen' => 'Anzahl von Nationalspielen',
    'anzahlvontoren' => 'Anzahl von Toren',
    'sportwennnichthockeydann' => 'Sport: Wenn nicht Hockey, dann',
    'aktuelleposition' => 'Aktuelle Position',
    'arbeitgeber' => 'Arbeitgeber',
    'branche' => 'Branche',
    'berufsstatus' => 'Berufsstatus',
    'interessen' => 'Interessen',
    'unterstutzung' => 'Unterstützung',
    'facebook' => 'Facebook',
    'instagram' => 'Instagram',
    'linkedin' => 'LinkedIn',
    'zusatzbeitrag' => 'Zusatzbeitrag',
    'teamkorrektur' => 'Team (Korrektur)',
    'altersklassekorrektur' => 'Altersklasse (Korrektur)'
];
$academic_parts = [
    'abschluss' => 'Abschluss',
    'programm' => 'Programm',
    'schuleuniversitatinstitut' => 'Schule/Universität/Institut',
    'von' => 'von',
    'bis' => 'bis'
];
$station_parts = [
    'mannschaft' => 'Mannschaft',
    'position' => 'Position',
    'von' => 'von',
    'bis' => 'bis',
    'rollekorrektur' => 'Rolle (Korrektur)',
    'teamkorrektur' => 'Team (Korrektur)',
    'altersklassekorrektur' => 'Altersklasse (Korrektur)'
];
$columns = $person_columns;
for ($n = 1; $n <= 5; $n++) {
    foreach ($academic_parts as $k => $label) {
        $columns['akademischedaten' . $n . $k] = "Akademische Daten $n, $label";
    }
}
for ($n = 1; $n <= 10; $n++) {
    foreach ($station_parts as $k => $label) {
        $columns['hockeylebenslauf' . $n . $k] = "Hockey-Lebenslauf $n, $label";
    }
}
foreach ($columns as $key => $label) {
    if (normalize_header($label) !== $key) {
        fwrite(STDERR, "Spaltenname passt nicht zum Schlüssel: $label ($key)\n");
        exit(1);
    }
}

/* ---------- Quellen lesen ---------- */

$nda_rows = sheet_rows($master, 'Import_Roh');
[, $nda_records] = nda_records($nda_rows);
$mv_rows = sheet_rows($master, 'Export WisoMV');
$mv = import_check(import_extract($mv_rows, [])['rows']);
$report = [];
$report[] = 'Prüfbericht Portal-Importdatei, erstellt ' . wp_date('d.m.Y H:i') . ' aus: ' . basename($master);
$report[] = '';

// Optional: proposed entry dates (CSV with member number and new date), only when asked for.
$entry_fix = [];
if (!empty($options['eintritt']) && is_file($options['eintritt'])) {
    foreach (file($options['eintritt'], FILE_IGNORE_NEW_LINES) as $i => $line) {
        $cells = str_getcsv(ltrim($line, "\xEF\xBB\xBF"), ';');
        if ($i === 0 || count($cells) < 4) {
            continue;
        }
        $date = \DateTimeImmutable::createFromFormat('!d.m.Y', trim($cells[3]));
        if ($date) {
            $entry_fix[(int) $cells[0]] = $date->format('Y-m-d');
        }
    }
}

// Match NDAlumni people to MeinVerein members by e-mail.
$by_email = [];
foreach ($nda_records as $i => $rec) {
    foreach (['bevorzugteemailadresse', 'emailadresseprivat'] as $k) {
        $e = strtolower(trim($rec[$k] ?? ''));
        if ($e !== '') {
            $by_email[$e] = $i;
        }
    }
}
$people = []; // member number => ['mv' => row, 'nda' => record]
$no_nda = [];
$matched = [];
$gone = [];
foreach ($mv as $row) {
    $i = $by_email[$row['email']] ?? null;
    $rec = $i === null ? [] : $nda_records[$i];
    if ($i === null) {
        $no_nda[] = $row['number'];
    } else {
        $matched[$i] = true;
    }
    // Cancelled or deceased: no account, no profile.
    if (
        $rec &&
        (nda_text($rec['gestorbenam'] ?? '') !== '' ||
            nda_text($rec['gekundigtam'] ?? '') !== '' ||
            nda_text($rec['gekundigtzum'] ?? '') !== '')
    ) {
        $gone[] = $row['number'];
        continue;
    }
    $people[(int) $row['number']] = ['mv' => $row, 'nda' => $rec];
}
ksort($people);

/* ---------- Korrekturen der Vorstände ---------- */

$team_fix = [];
$team_rows = sheet_rows($master, 'Team-Zuordnung');
foreach (array_slice($team_rows, 1) as $r) {
    $n = (int) $num($r[0] ?? 0);
    if ($n < 1) {
        continue;
    }
    $team = trim((string) ($r[9] ?? ''));
    $age = trim((string) ($r[10] ?? ''));
    if ($team !== '' || $age !== '') {
        $team_fix[$n] = [$team, $age];
    }
}
$station_fix = []; // number => station => list of [role, side, age, original rows of that line]
foreach (array_slice(sheet_rows($master, 'Stationen Staff'), 1) as $r) {
    $n = (int) $num($r[0] ?? 0);
    $s = (int) $num($r[3] ?? 0);
    if ($n < 1 || $s < 1) {
        continue;
    }
    $station_fix[$n][$s][] = [
        'role' => trim((string) ($r[11] ?? '')),
        'side' => trim((string) ($r[12] ?? '')),
        'age' => trim((string) ($r[13] ?? '')),
        'unit' => trim((string) ($r[4] ?? '')),
        'position' => trim((string) ($r[5] ?? '')),
        'period' => trim((string) ($r[6] ?? ''))
    ];
}

/* ---------- Importdatei bauen ---------- */

$mv_out = [['Mitgliedsnr.', 'Vorname', 'Nachname', 'E-Mail', 'Geburtstag', 'Mitglied seit', 'Jahresspende']];
$nda_out = [array_values($columns)];
$no_birthday = [];
$stats = ['donation' => 0, 'no_birthday' => 0, 'minors' => 0, 'team_fix' => 0, 'age_fix' => 0, 'station_fix' => 0, 'split' => 0, 'entry_fix' => 0, 'invalid_entry' => []];
foreach ($people as $number => $p) {
    $row = $p['mv'];
    $rec = $p['nda'];
    $joined = $row['joined'];
    if (isset($entry_fix[$number]) && $joined !== $entry_fix[$number]) {
        $joined = $entry_fix[$number];
        $stats['entry_fix']++;
    }
    if ($joined !== '' && $joined < FOUNDING_DATE) {
        $stats['invalid_entry'][] = $number;
    }
    $donation = import_whole_number(nda_text($rec['zusatzbeitrag'] ?? ''));
    $donation = $donation !== '' && (float) str_replace(',', '.', $donation) > 0 ? $donation : '';
    if ($donation !== '') {
        $stats['donation']++;
    }
    if ($row['birthday'] === '') {
        $stats['no_birthday']++;
        $no_birthday[] = $number;
    } elseif (is_minor_data(['birthday' => $row['birthday']])) {
        $stats['minors']++;
    }
    $mv_out[] = [$number, $row['first_name'], $row['last_name'], $row['email'], $row['birthday'], $joined, $donation];

    $values = [];
    foreach (array_keys($columns) as $key) {
        $values[$key] = (string) ($rec[$key] ?? '');
    }
    if ($rec) {
        $values['zusatzbeitrag'] = $donation;
        if (isset($team_fix[$number])) {
            [$team, $age] = $team_fix[$number];
            if (in_array($team, ['Damen', 'Herren', 'beides'], true)) {
                $values['teamkorrektur'] = $team;
                $stats['team_fix']++;
            }
            if ($age !== '') {
                $values['altersklassekorrektur'] = $age;
                $stats['age_fix']++;
            }
        }
        $next = 6;
        foreach ($station_fix[$number] ?? [] as $s => $lines) {
            foreach ($lines as $idx => $line) {
                $base = $idx === 0 ? $s : $next++;
                if ($base > 10) {
                    $report[] = "WARNUNG Nr. $number: mehr als 10 Stationen, Zeile ignoriert";
                    continue;
                }
                if ($idx > 0) {
                    // A second line for the same station: copy it as a new station (e.g. the same job for the other team).
                    foreach (['mannschaft', 'position', 'von', 'bis'] as $part) {
                        $values['hockeylebenslauf' . $base . $part] = $values['hockeylebenslauf' . $s . $part];
                    }
                    $stats['split']++;
                }
                foreach (['role' => 'rollekorrektur', 'side' => 'teamkorrektur', 'age' => 'altersklassekorrektur'] as $k => $part) {
                    if ($line[$k] !== '') {
                        $values['hockeylebenslauf' . $base . $part] = $line[$k];
                        $stats['station_fix']++;
                    }
                }
            }
        }
    }
    if ($rec) {
        $nda_out[] = array_values($values); // members without NDAlumni data stay in the MeinVerein sheet only
    }
}

$target = $out_dir . '/Portal-Import.xlsx';
write_simple_xlsx($target, ['Export WisoMV' => $mv_out, 'Import_Roh' => $nda_out]);

/* ---------- Selbstprüfung: die Datei so lesen, wie es das Portal tut ---------- */

$check_mv = import_check(import_extract(read_xlsx($target, 'Export WisoMV'), [])['rows']);
$check_nda = nda_map(read_xlsx($target, 'Import_Roh'));
$status = [];
foreach ($check_mv as $r) {
    $k = $r['status'] . ($r['reason'] !== '' ? ' (' . $r['reason'] . ')' : '');
    $status[$k] = ($status[$k] ?? 0) + 1;
}
$fields = [];
foreach ($check_nda['people'] as $person) {
    foreach (array_keys($person['data']) as $k) {
        $fields[$k] = ($fields[$k] ?? 0) + 1;
    }
}
arsort($fields);

$report[] = '1. Personen';
$report[] = '   Mitglieder im MeinVerein-Blatt: ' . count($mv) . ', davon in die Datei übernommen: ' . count($people);
$report[] = '   Gekündigt oder verstorben (nicht übernommen): ' . ($gone ? count($gone) . ' (Nr. ' . implode(', ', $gone) . ')' : 'keine');
$report[] = '   NDAlumni-Personen: ' . count($nda_records) . ', davon ohne MeinVerein-Eintrag (nicht übernommen): ' . (count($nda_records) - count($matched));
$report[] = '   Mitglieder ohne NDAlumni-Profil: ' . count($no_nda) . ($no_nda ? ' (Nr. ' . implode(', ', $no_nda) . ')' : '');
$report[] = '';
$report[] = '2. Prüfung wie im Portal (Upload)';
foreach ($status as $k => $c) {
    $report[] = "   $k: $c";
}
$report[] = '   Eintritt vor der Gründung (23.09.2025), bitte in der Master korrigieren: ' . ($stats['invalid_entry'] ? 'Nr. ' . implode(', ', $stats['invalid_entry']) : 'keine');
if ($entry_fix) {
    $report[] = '   Eintrittsdaten aus dem Vorschlag eingesetzt: ' . $stats['entry_fix'];
}
$report[] = '';
$report[] = '3. Daten';
$report[] = '   Mit Jahresspende: ' . $stats['donation'];
$report[] = '   Ohne Geburtsdatum: ' . $stats['no_birthday'] . ' (Geburtsdatum ist Pflicht: separat einladen, sobald es vorliegt)';
$report[] = '   Nr. ' . implode(', ', $no_birthday);
$report[] = '   Unter 18: ' . $stats['minors'];
$report[] = '';
$report[] = '4. Profile aus NDAlumni';
$report[] = '   Personen: ' . count($check_nda['people']);
foreach ($fields as $k => $c) {
    $report[] = '   ' . str_pad(nda_field_label($k), 44) . $c;
}
$report[] = '';
$report[] = '5. Korrekturen aus der Master';
$report[] = '   Team eingetragen: ' . $stats['team_fix'] . ' | Altersklasse eingetragen: ' . $stats['age_fix'];
$report[] = '   Stationen korrigiert (Felder): ' . $stats['station_fix'] . ' | zusätzliche Stationen (geteilt): ' . $stats['split'];
$report[] = '';
$report[] = '6. Hinweise des Importers (Anzahl Personen oder Stationen)';
arsort($check_nda['notes']);
foreach ($check_nda['notes'] as $text => $c) {
    $report[] = "   $text: $c";
}
$report[] = '';
$report[] = 'Nicht in der Datei: IBAN, Mandat, Anschrift, Telefon, Geburtsname, Notizen, Tarif, Beiträge, Newsletter-Zustimmung.';
file_put_contents($out_dir . '/Pruefbericht.txt', implode("\n", $report) . "\n");
chmod($target, 0600);
echo implode("\n", $report), "\n\nDatei: $target\nBericht: $out_dir/Pruefbericht.txt\n";
