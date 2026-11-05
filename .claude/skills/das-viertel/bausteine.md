# Bausteine für „Das Viertel“

Das Plugin baut aus diesen Editor-Bausteinen die Mail (`wp-content/plugins/eintrikot-newsletter/layout.php`). Das Markup muss **genau** so aussehen, sonst meldet der Editor „unerwarteter Inhalt“: Kommentarzeilen, Klassen und Zeilenumbrüche wie unten, Bausteine durch eine Leerzeile getrennt.

Erlaubt sind: Absatz, Überschrift (h2), Liste, Zitat, Gruppe (nicht ineinander), Buttons, Trennlinie, Bild. Alles andere lehnt die Prüfung im Plugin ab.

Im Text sind nur `<strong>`, `<em>`, `<a href>` und `<br>` erlaubt.

## Rubrikzeile

Über jedem Abschnitt. Text in normaler Schreibung, das Plugin setzt Versalien und den farbigen Punkt.

```html
<!-- wp:paragraph {"className":"is-style-rubrik"} -->
<p class="is-style-rubrik">Kurzpass</p>
<!-- /wp:paragraph -->
```

## Inhaltszeile

Steht als erster Baustein. Die Rubriken dieser Ausgabe in ihrer Reihenfolge, getrennt mit „ · “. „In diesem Viertel“ setzt das Plugin davor.

```html
<!-- wp:paragraph {"className":"is-style-inhalt"} -->
<p class="is-style-inhalt">Bully · Eins gegen eins · Rückpass · Kurzpass · Netzfund · Aus der Kabine · Spielplan</p>
<!-- /wp:paragraph -->
```

## Absatz, Zwischenüberschrift, Trennlinie

```html
<!-- wp:paragraph -->
<p>Text mit <strong>fett</strong> und <a href="https://www.hockey.de/">Link</a>.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Zwischenüberschrift</h2>
<!-- /wp:heading -->

<!-- wp:separator -->
<hr class="wp-block-separator has-alpha-channel-opacity"/>
<!-- /wp:separator -->
```

Die Trennlinie steht nach dem Bully und nach dem Aufmacher, sonst nicht.

## Unterschrift (unter dem Bully)

```html
<!-- wp:paragraph {"className":"is-style-unterschrift"} -->
<p class="is-style-unterschrift">– Vorname Name, für den Vorstand</p>
<!-- /wp:paragraph -->
```

## Titelkasten (Aufmacher)

Farbfläche in der Farbe des Viertels. Darin: Rubrikzeile mit dem **Format** (wird zur Formatzeile), Überschrift (groß), ein Satz Unterzeile.

```html
<!-- wp:group {"className":"is-style-titel","layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-titel"><!-- wp:paragraph {"className":"is-style-rubrik"} -->
<p class="is-style-rubrik">Eins gegen eins</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Weltmeister 2002 trifft Weltmeister 2026</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Ein Satz, worum es geht.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
```

## Zitat

Nur echte Zitate aus dem gelieferten Material. Der Name steht in `<cite>`.

```html
<!-- wp:quote -->
<blockquote class="wp-block-quote"><!-- wp:paragraph -->
<p>„Wörtliches Zitat.“</p>
<!-- /wp:paragraph --><cite>Vorname Name</cite></blockquote>
<!-- /wp:quote -->
```

## Frage im Gespräch (Format „Eins gegen eins“)

Die Frage als fetter Absatz, die Antworten als normale Absätze mit dem Namen in Fett am Anfang.

```html
<!-- wp:paragraph -->
<p><strong>Was war damals anders?</strong></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>Vorname:</strong> Antwort aus dem Gespräch.</p>
<!-- /wp:paragraph -->
```

## Infokasten (Steckbrief, Kasten am Rand)

```html
<!-- wp:group {"className":"is-style-kasten","layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-kasten"><!-- wp:paragraph -->
<p><strong>Steckbrief</strong><br>Länderspiele: 123<br>Größter Titel: Olympiasieg 1992<br>Heute: Ärztin in Köln</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
```

## Kurzpass (nummerierte Liste)

3 bis 5 Einträge. Jeder beginnt mit einer fetten Zeile (wird zur Überschrift des Eintrags), dann zwei Sätze, am Ende die Quelle als Link mit der Domain als Text. Die Nummern setzt das Plugin.

```html
<!-- wp:list {"ordered":true} -->
<ol class="wp-block-list"><!-- wp:list-item -->
<li><strong>Überschrift der Meldung</strong> Zwei Sätze Inhalt. <a href="https://www.hockey.de/articles/…">hockey.de</a></li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><strong>Zweite Meldung</strong> Zwei Sätze. <a href="https://www.fih.hockey/news/…">fih.hockey</a></li>
<!-- /wp:list-item --></ol>
<!-- /wp:list -->
```

Ohne Nummern (Aufzählung): `<!-- wp:list -->` und `<ul class="wp-block-list">…</ul>`.

## Große Zahl (Rückpass)

Schwarzer Kasten. Die **erste Überschrift** ist die Zahl, danach ein Absatz mit fetter erster Zeile. Nur nachprüfbare Zahlen.

```html
<!-- wp:group {"className":"is-style-zahl","layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-zahl"><!-- wp:heading -->
<h2 class="wp-block-heading">25</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><strong>Jahre Weltmeister</strong><br>Am 9. März 2002 gewinnen die Herren in Kuala Lumpur das WM-Finale gegen Australien mit 2:1.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
```

## Rahmen (Netzfund, Auswärtsspiel)

```html
<!-- wp:group {"className":"is-style-rahmen","layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-rahmen"><!-- wp:paragraph -->
<p>Ein Satz, was es ist und warum es sich lohnt. <a href="https://…">Quelle</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
```

## Termin (Spielplan)

Jeder Termin ein eigener Absatz. Am Anfang **muss** das Datum fett stehen, als Tag und Monat („12. Juni“). Danach der Titel, nach `<br>` die Einzelheiten. Das Plugin macht daraus eine Datumskachel.

```html
<!-- wp:paragraph {"className":"is-style-termin"} -->
<p class="is-style-termin"><strong>12. Juni</strong> FIH Pro League in Berlin<br>12. bis 16. Juni 2027, Vorverkauf ab November</p>
<!-- /wp:paragraph -->
```

## Abpfiff (Farbkasten mit Button)

Immer am Ende. Ein Satz zum Schluss, der jedes Mal anders ist, dann der Button ins Portal.

```html
<!-- wp:group {"className":"is-style-farbe","layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-farbe"><!-- wp:paragraph {"className":"is-style-rubrik"} -->
<p class="is-style-rubrik">Abpfiff</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Ein Satz zum Schluss.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="https://eintrikot.com/community-portal/">Ins Portal</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->
```

Nach dem Umzug auf www.eintrikot.de den Link anpassen (Portal unter `/community-portal/`).

## Bilder

Bilder setzt die Redaktion im Editor aus der Mediathek. Im Markup steht an der Stelle ein Platzhalter-Absatz:

```html
<!-- wp:paragraph -->
<p>[Bild: Porträt von damals und heute, Alternativtext: „…“]</p>
<!-- /wp:paragraph -->
```

Das Plugin lässt keine Ausgabe als bereit melden, solange noch `[…]`-Platzhalter im Text stehen.
