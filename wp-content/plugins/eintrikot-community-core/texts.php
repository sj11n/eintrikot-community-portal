<?php
/**
 * Aktualisierung „Website-Texte überarbeiten“ (Oktober 2026, mit Björn abgestimmt).
 *
 * Ändert nur die genannten Stellen. Steht ein Text auf der Seite schon anders (im Backend
 * bearbeitet), wird diese Stelle übersprungen und im Ergebnis genannt.
 */
namespace Eintrikot\Community;
if (!defined('ABSPATH')) {
    exit();
}

const DONATION_IBAN = 'DE81 5065 0023 0000 1562 24';
const DONATION_PAYPAL = 'https://paypal.me/eintrikot';

/** Applies one change to $content; notes the result in $log. $old may be a regex ('#…#'). */
function text_change(&$content, $label, $old, $new, &$log) {
    $is_regex = str_starts_with($old, '#');
    if ($is_regex) {
        $next = preg_replace($old, $new, $content, 1, $count);
    } else {
        $pos = strpos($content, $old);
        $count = $pos === false ? 0 : 1;
        $next = $pos === false ? $content : substr_replace($content, $new, $pos, strlen($old));
    }
    if ($count && is_string($next)) {
        $content = $next;
        $log[] = '✓ ' . $label;
    } else {
        $log[] = '– ' . $label . ' (Stelle nicht gefunden, bitte im Backend prüfen)';
    }
}

/** Block markup for a paragraph. */
function block_p($html, $class = '') {
    return '<!-- wp:paragraph' .
        ($class !== '' ? ' {"className":"' . $class . '"}' : '') .
        ' --><p' .
        ($class !== '' ? ' class="' . $class . '"' : '') .
        '>' .
        $html .
        '</p><!-- /wp:paragraph -->';
}

function save_page_text($page, $content, &$log) {
    if ($page && $content !== $page->post_content) {
        $r = wp_update_post(wp_slash(['ID' => $page->ID, 'post_content' => $content]), true);
        if (is_wp_error($r)) {
            $log[] = '✗ ' . $page->post_title . ': ' . $r->get_error_message();
        }
    }
}

function update_site_texts() {
    $log = [];
    $vision_new =
        'Bis 2030 ist EINTRIKOT ein starkes, selbsttragendes Netzwerk mit 500+ Mitgliedern, das alle deutschen Hockey-Nationalteams finanziell, strukturell und persönlich unterstützt.';
    $vision_old =
        '#Bis 2030 ist (?:EINTRIKOT|Eintrikot) eine?s? starke[sr]?, selbsttragende[sr]? (?:Community|Netzwerk)(?: mit 500\+ Mitgliedern)?, d(?:ie|as) alle deutschen Hockey[- ]Nationalteams finanziell, strukturell und persönlich unterstützt\.#u';
    $talent_old = 'Jungen Talenten Möglichkeiten eröffnen und ihre Entwicklung begleiten.';
    $talent_new = 'Dort helfen, wo bei den Jugendteams Mittel fehlen: Material, Lehrgänge, Ausrüstung.';

    // Startseite
    $home = get_post((int) get_option('page_on_front'));
    if ($home) {
        $c = $home->post_content;
        $log[] = 'Startseite';
        text_change(
            $c,
            'Hero: gemeinnütziger Verein, Förderung der nächsten Generation',
            '#(bis zu den Masters\.)(\s*</p>)#u',
            '$1 Als gemeinnütziger Verein fördern wir gemeinsam die nächste Generation.$2',
            $log
        );
        text_change($c, 'Vision 2030 mit 500+ Mitgliedern', $vision_old, $vision_new, $log);
        text_change($c, 'Nachwuchs fördern konkret', $talent_old, $talent_new, $log);
        text_change(
            $c,
            'Dank „Von Mitgliedern möglich gemacht“ nur noch auf Unterstützen',
            '#<!-- wp:group (?:(?!-->).)*?"className":"section thanks"(?:(?!-->).)*?-->.*?</section>\s*<!-- /wp:group -->\s*#s',
            '',
            $log
        );
        save_page_text($home, $c, $log);
    } else {
        $log[] = '– Startseite nicht gefunden';
    }

    // Der Verein
    $page = get_page_by_path('community-verein');
    if ($page) {
        $c = $page->post_content;
        $log[] = 'Der Verein';
        text_change($c, 'Vision 2030 wie auf der Startseite', $vision_old, $vision_new, $log);
        text_change(
            $c,
            'Werte: Jugend, Aktive, Masters und Alumni',
            'Jugend, Aktive, Ehemalige und Masters',
            'Jugend, Aktive, Masters und Alumni',
            $log
        );
        text_change(
            $c,
            'Wie finanzieren wir uns? konkret',
            'Mitgliedsbeiträge tragen die Vereinsarbeit. Spenden und ehrenamtliches Engagement ermöglichen zusätzliche Vorhaben.',
            'Mitgliedsbeiträge (50 € im Jahr ab 32) tragen die Vereinsarbeit. Spenden und Jahresspenden unserer Mitglieder fließen in die Förderung. Ehrenamt macht den Rest möglich.',
            $log
        );
        // „Was ist EINTRIKOT? / Was tun wir? / …“ direkt unter den Seitenkopf.
        if (
            preg_match(
                '#<!-- wp:group (?:(?!-->).)*?"className":"section info-grid"(?:(?!-->).)*?-->.*?</section>\s*<!-- /wp:group -->\s*#s',
                $c,
                $m
            ) &&
            preg_match('#^(.*?</div>\s*</div>\s*<!-- /wp:cover -->\s*)#s', $c, $hero) &&
            !str_starts_with(substr($c, strlen($hero[1])), $m[0])
        ) {
            $rest = str_replace($m[0], '', substr($c, strlen($hero[1])));
            $c = $hero[1] . $m[0] . $rest;
            $log[] = '✓ „Was ist EINTRIKOT?“ nach oben';
        } else {
            $log[] =
                '– „Was ist EINTRIKOT?“ nach oben (Aufbau anders als erwartet, bitte im Backend verschieben)';
        }
        save_page_text($page, $c, $log);
    }

    // Engagement
    $page = get_page_by_path('community-engagement');
    if ($page) {
        $c = $page->post_content;
        $log[] = 'Engagement';
        text_change(
            $c,
            'Begegnungen: Alumni',
            'aktive und ehemalige Nationalteams, Masters und Staff',
            'aktive Nationalteams, Masters, Alumni und Staff',
            $log
        );
        save_page_text($page, $c, $log);
    }

    // Unterstützen: neu aufgebaut (drei Wege, dann der Dank). Die alte Fassung bleibt als Revision.
    $page = get_page_by_path('community-unterstuetzen');
    if ($page) {
        $log[] = 'Unterstützen';
        save_page_text($page, support_page_content(), $log);
        $log[] = '✓ Seite neu aufgebaut: Spenden (Konto, PayPal), Jahresspende, Mit anpacken, Dank';
    }

    // Mitglied werden
    $page = get_page_by_path('mitglied-werden');
    if ($page) {
        $c = $page->post_content;
        $log[] = 'Mitglied werden';
        text_change($c, 'Hero: Alumni', 'bis zu den Ehemaligen', 'bis zu den Alumni', $log);
        text_change(
            $c,
            'Wer Mitglied werden kann: Alumni, Staff, unter 18',
            '#Aktive und Ehemalige\.(\s*</p>\s*<!-- /wp:paragraph -->)#u',
            'Aktive und Alumni. Dazu alle, die ein Nationalteam als Trainerin, Trainer oder im Staff begleitet haben.$1' .
                "\n" .
                block_p(
                    'Unter 18? Dann stimmt nach dem Antrag ein Elternteil zu. Das läuft per E-Mail über das Mitgliederportal.'
                ),
            $log
        );
        text_change($c, 'Nachwuchs fördern konkret', $talent_old, $talent_new, $log);
        save_page_text($page, $c, $log);
    }

    // Menschen: Beirat
    $page = get_page_by_path(PEOPLE_PAGE);
    if ($page) {
        $c = $page->post_content;
        $log[] = 'Menschen';
        $people = '';
        foreach (['Andreas Arntzen', 'Wibke Weisel', 'Natascha Keller'] as $name) {
            $people .=
                '<!-- wp:group {"className":"advisory-person","tagName":"article","layout":{"type":"default"}} --><article class="wp-block-group advisory-person"><!-- wp:cover {"overlayColor":"blue","dimRatio":100,"isUserOverlayColor":true,"className":"advisory-photo et-media-slot","minHeight":96} --><div class="wp-block-cover advisory-photo et-media-slot" style="min-height:96px"><span aria-hidden="true" class="wp-block-cover__background has-blue-background-color has-background-dim-100 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:paragraph --><p><span>' .
                esc_html(member_initials($name)) .
                '</span></p><!-- /wp:paragraph --></div></div><!-- /wp:cover -->' .
                "\n" .
                '<!-- wp:group {"tagName":"div","layout":{"type":"default"}} --><div class="wp-block-group"><!-- wp:paragraph {"className":"micro"} --><p class="micro">Beirat · Gründungsmitglied</p><!-- /wp:paragraph -->' .
                "\n" .
                '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">' .
                esc_html($name) .
                '</h3><!-- /wp:heading --></div><!-- /wp:group --></article><!-- /wp:group -->' .
                "\n";
        }
        text_change(
            $c,
            'Beirat: Andreas Arntzen, Wibke Weisel, Natascha Keller',
            '#<!-- wp:group (?:(?!-->).)*?"className":"advisory-person"(?:(?!-->).)*?-->.*?Vorstellung folgt.*?</article>\s*<!-- /wp:group -->\s*#s',
            str_replace(['\\', '$'], ['\\\\', '\\$'], $people),
            $log
        );
        text_change(
            $c,
            'Beirat: Einleitung',
            'Der Beirat begleitet den Vorstand mit Erfahrung und Perspektiven aus dem Hockeysport.',
            'Die Gründungsmitglieder waren zuvor der Beirat der DHB-Alumni-Familie. Drei von ihnen haben den Vorstand übernommen. Gemeinsam mit dem Beirat bringen sie EINTRIKOT voran; an den Beiratssitzungen nimmt der Vorstand teil.',
            $log
        );
        save_page_text($page, $c, $log);
        delete_transient('eintrikot_people_names');
    }

    // Name der Website (Browser-Tab, Suchergebnisse)
    if (get_option('blogname') !== 'EINTRIKOT e. V.') {
        update_option('blogname', 'EINTRIKOT e. V.');
        $log[] = '✓ Titel der Website: EINTRIKOT e. V.';
    }
    return implode("\n", $log);
}

/** „Unterstützen“ als klare Übersicht: drei Wege (Spenden, Jahresspende, Mitanpacken), dann der Dank. */
function support_page_content() {
    $btn = function ($href, $label, $class = 'button solid', $extra = '') {
        $solid = str_contains($class, 'solid');
        return '<!-- wp:button {"className":"' .
            $class .
            '","backgroundColor":"' .
            ($solid ? 'black' : 'white') .
            '","textColor":"' .
            ($solid ? 'white' : 'black') .
            '"} --><div class="wp-block-button ' .
            $class .
            '"><a class="wp-block-button__link has-' .
            ($solid ? 'white' : 'black') .
            '-color has-' .
            ($solid ? 'black' : 'white') .
            '-background-color has-text-color has-background wp-element-button" href="' .
            $href .
            '"' .
            $extra .
            '>' .
            $label .
            '</a></div><!-- /wp:button -->';
    };
    $card = function ($tone, $micro, $title, $body) {
        return '<!-- wp:group {"className":"support-card is-' .
            $tone .
            '","tagName":"article","layout":{"type":"default"}} --><article class="wp-block-group support-card is-' .
            $tone .
            '">' .
            block_p(esc_html($micro), 'micro') .
            '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">' .
            esc_html($title) .
            '</h3><!-- /wp:heading -->' .
            $body .
            '</article><!-- /wp:group -->';
    };
    $thanks = [
        ['Andreas Arntzen', 'Für seine Spende zur Deckung der administrativen Aufwände.'],
        ['Kathrin Jacobsen', 'Für Logo, Farben und Design von EINTRIKOT.'],
        ['Axel Kaste', 'Für die Bilder und seinen besonderen Einsatz als Bildlieferant.']
    ];
    $thanks_html = '';
    foreach ($thanks as [$name, $text]) {
        $thanks_html .=
            '<!-- wp:group {"tagName":"article","layout":{"type":"default"}} --><article class="wp-block-group"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">' .
            esc_html($name) .
            '</h3><!-- /wp:heading -->' .
            block_p(esc_html($text)) .
            '</article><!-- /wp:group -->';
    }
    return '<!-- wp:cover {"overlayColor":"blue","dimRatio":100,"isUserOverlayColor":true,"className":"section page-intro et-mvp-page-hero"} --><div class="wp-block-cover section page-intro et-mvp-page-hero"><span aria-hidden="true" class="wp-block-cover__background has-blue-background-color has-background-dim-100 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Gemeinsam für den deutschen Hockeyleistungssport.</h1><!-- /wp:heading -->' .
        block_p(
            'EINTRIKOT ist ein gemeinnütziger Verein. Mitgliedschaft, ehrenamtliches Engagement und Spenden tragen unsere Arbeit.',
            'lead'
        ) .
        '</div></div><!-- /wp:cover -->' .
        "\n" .
        '<!-- wp:group {"className":"section support-ways","anchor":"spenden","tagName":"section","layout":{"type":"default"}} --><section class="wp-block-group section support-ways" id="spenden"><!-- wp:heading {"level":2} --><h2 class="wp-block-heading">Drei Wege, uns zu unterstützen.</h2><!-- /wp:heading -->' .
        '<!-- wp:group {"className":"support-grid","tagName":"div","layout":{"type":"default"}} --><div class="wp-block-group support-grid">' .
        $card(
            'blue',
            'Einmalig',
            'Spenden',
            block_p('Jede Spende fließt in die Förderung der Nationalteams.') .
                block_p(
                    '<span>Empfänger</span>EINTRIKOT e. V.<br><span>IBAN</span><strong>' .
                        DONATION_IBAN .
                        '</strong><br><span>Zweck</span>Spende EINTRIKOT',
                    'bank-box'
                ) .
                $btn(
                    DONATION_PAYPAL,
                    'Mit PayPal spenden ↗',
                    'button solid',
                    ' target="_blank" rel="noopener noreferrer"'
                ) .
                block_p(
                    '<small>Bis 300 € reicht dem Finanzamt dein Kontoauszug. Für höhere Beträge stellen wir eine Zuwendungsbestätigung aus.</small>'
                )
        ) .
        $card(
            'green',
            'Für Mitglieder',
            'Jahresspende',
            block_p(
                'Mit einer festen Jahresspende hilfst du uns, langfristig zu planen: 50, 100 oder 150 € oder ein Betrag deiner Wahl, jederzeit änderbar.'
            ) .
                $btn(
                    '/community-portal/?view=service&amp;service=funding',
                    'Im Mitgliederportal einrichten ↗'
                )
        ) .
        $card(
            'white',
            'Ehrenamt',
            'Mit anpacken',
            block_p(
                'Events organisieren, Mentoring anbieten, Texte schreiben oder Bilder liefern: Viel von dem, was wir tun, entsteht ehrenamtlich.'
            ) . $btn('mailto:info@eintrikot.de', 'Schreib uns ↗', 'button')
        ) .
        '</div><!-- /wp:group -->' .
        block_p(
            'Größere Spende, Sponsoring oder Fragen? <a href="/community-kontakt/">Sprich direkt mit dem Vorstand ↗</a>',
            'support-note'
        ) .
        '</section><!-- /wp:group -->' .
        "\n" .
        '<!-- wp:group {"className":"section thanks","tagName":"section","layout":{"type":"default"}} --><section class="wp-block-group section thanks"><!-- wp:heading {"level":2} --><h2 class="wp-block-heading">Von Mitgliedern<br>möglich gemacht.</h2><!-- /wp:heading -->' .
        block_p('Danke für euren Beitrag zu EINTRIKOT.') .
        '<!-- wp:group {"className":"info-grid","tagName":"div","layout":{"type":"default"}} --><div class="wp-block-group info-grid">' .
        $thanks_html .
        '</div><!-- /wp:group --></section><!-- /wp:group -->';
}
