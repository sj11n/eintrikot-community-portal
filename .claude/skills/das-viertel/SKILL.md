---
name: das-viertel
description: Plant und schreibt eine Ausgabe des EINTRIKOT-Newsletters „Das Viertel“ aus dem Scout-Sheet und eigenen Themen. Sichtet Meldungen (rein, raus, geparkt), wählt Rückpass, Aufmacher-Format und Farbe und liefert die Ausgabe als Editor-Bausteine zum Einfügen ins Newsletter-Plugin. Verwenden, wenn eine neue Ausgabe vorbereitet, Themen für den Newsletter gesichtet oder nach dem Versand die Planung nachgepflegt werden soll.
---

# Das Viertel – eine Ausgabe bauen

„Das Viertel“ ist der EINTRIKOT-Newsletter: einmal im Quartal, nur an Mitglieder mit Einwilligung, verschickt über das Plugin `eintrikot-newsletter`. Gestaltung und Rubriken sind von B freigegeben (05.11.2026, `docs/entwuerfe/newsletter-heft.html`). Dieser Skill macht daraus jedes Quartal eine Ausgabe, die wiedererkennbar ist und trotzdem anders aussieht als die letzte.

Der Skill **schreibt Entwürfe, er veröffentlicht nichts**. Versand und Freigabe laufen immer über das Plugin (Testmail, Prüfliste, Vier-Augen-Freigabe).

## Dateien

- `themenregeln.md` – was in den Newsletter gehört, was nicht, Aufbau der Reiter im Sheet, Ergänzung für den Scout.
- `bausteine.md` – jeder Baustein als Editor-Markup zum Kopieren, mit Regeln.
- `vorlage-nr1.html` – vollständige Beispielausgabe (Nr. 1, Dezember 2026). Sie wird in `tests/newsletter.php` gegen das Plugin geprüft. Wer Bausteine ändert, ändert sie hier mit.

## Quellen

Google Sheet „EINTRIKOT Scout Log“, ID `1JMd7XHP10TpU8MdHGmEjosOawqGcp56piIT0SLdXM2E`:

| Reiter            | Inhalt                                                  | Wer schreibt        |
| ----------------- | ------------------------------------------------------- | ------------------- |
| `Untitled`        | Meldungen des Scouts (datum, titel, kategorie, …)       | nur der Scout       |
| `Titel-Termine`   | Titel der Nationalteams mit Datum, Gegner, Quelle       | niemand (Referenz)  |
| `Masters-Termine` | Titel der Masters-Teams                                 | niemand (Referenz)  |
| `Newsletter-Plan` | Themen je Ausgabe mit Entscheidung                      | dieser Skill, Redaktion |
| `Ausgaben`        | eine Zeile je Ausgabe: Format, Farbe, Reihenfolge       | dieser Skill        |

Den Reiter des Scouts **nie ändern**. Zum Lesen und Schreiben die Google-Sheets-Werkzeuge verwenden. Fehlen sie, die Nutzerin oder den Nutzer bitten, das Sheet freizugeben oder die Werte einzufügen.

## Ablauf

Nach Schritt 2 und nach Schritt 5 anhalten und auf die Entscheidung der Redaktion warten.

### 1. Rahmen klären

- Nummer der Ausgabe, Versanddatum und Redaktionsschluss erfragen, wenn sie nicht genannt sind. Nr. 1 erscheint am 01.12.2026, dem Tag der Mitgliederversammlung.
- Den Reiter `Ausgaben` lesen: welche Aufmacher-Formate, Farben und Reihenfolgen zuletzt dran waren.
- Den Reiter `Newsletter-Plan` lesen: Themen mit Entscheidung „geparkt“ aus früheren Ausgaben wieder vorlegen.

### 2. Sichten

- Alle Scout-Meldungen seit dem letzten Versand lesen (Spalte `datum`), dazu die geparkten Themen.
- Jede Meldung nach `themenregeln.md` einordnen: **rein**, **raus** oder **geparkt**, mit Rubrik und einem Satz Begründung. Fristen gegen das **Versanddatum** prüfen, nicht gegen heute. Was zum Versand schon vorbei ist, fliegt raus oder wandert als Rückblick in den Kurzpass.
- Die Vorschläge als Zeilen in `Newsletter-Plan` anhängen (`status` = `vorschlag`). Keine Zeile doppelt anlegen: vorher prüfen, ob die URL schon im Plan steht.
- Der Redaktion eine kurze Tabelle zeigen und fragen:
  - welche Vorschläge passen,
  - welche eigenen Themen dazukommen (Porträt „Was macht eigentlich …?“, Vereinsnachrichten, Auswärtsspiel),
  - wer das Editorial (Bully) unterschreibt.
- **Anhalten.**

### 3. Rückpass wählen

- In `Titel-Termine` und `Masters-Termine` runde Jahrestage suchen: 10, 20, 25, 30, 40, 50, 60, 70, 75, 80, 90 oder 100 Jahre. Sie sollen zwischen dem letzten Versand und sechs Wochen nach diesem Versand liegen.
- Einträge mit „bestätigt“ in `hinweis` bevorzugen. Unbestätigte Daten nur mit dem Zusatz „laut [Quelle]“ verwenden, nie als große Zahl.
- Ein Titel kommt groß in die „Große Zahl“, höchstens ein zweiter als Satz darunter. Immer mit der Frage: „Warst du dabei? Antworte auf diese Mail, wir sammeln Erinnerungen.“

### 4. Format, Farbe, Reihenfolge

- **Aufmacher-Format:** eines, das in den **zwei** letzten Ausgaben nicht dran war:
  - Was macht eigentlich …?
  - Eins gegen eins
  - Langer Ball
  - Zeitlupe
  - Hinter der Bande

  Passt der Stoff der Redaktion klar zu einem Format, hat der Stoff Vorrang.
- **Farbe des Viertels:** Rosé, Blau oder Grün, nie dieselbe wie in der letzten Ausgabe. Wenn nichts dagegen spricht: Dezember Rosé, März Grün, Juni Blau, September Rosé.
- **Reihenfolge:** Fest sind oben „Inhaltszeile, Bully, Aufmacher“ und unten „Aus der Kabine, Spielplan, Abpfiff“. Den Mittelteil (Kurzpass, Rückpass, Netzfund, Auswärtsspiel) nicht zweimal hintereinander gleich ordnen.
- **Einstieg des Aufmachers** wechselt zwischen Szene, Zitat, Frage und Zahl, nie zweimal hintereinander gleich.

### 5. Schreiben

- Die Ausgabe nach `bausteine.md` als Editor-Markup schreiben. `vorlage-nr1.html` ist das Muster.
- Datei: `.newsletter/YYYY-MM-viertel-nr-N.html` im Repo-Ordner. Der Ordner ist in `.gitignore`. Das Repo ist öffentlich, und Porträts oder Vereinsinterna gehören nicht hinein.
- Im Chat dazu ausgeben:
  - **Betreff:** höchstens 50 Zeichen, konkret, keine Clickbait-Fragen.
  - **Vorschautext:** höchstens 110 Zeichen, nennt zwei oder drei Themen.
  - **Ausgabe:** z. B. „Nr. 2 · März 2027“.
  - **Farbe des Viertels**
  - **Offene Platzhalter:** Liste aller `[…]` mit der Frage, wer sie füllt.
- Die Einfüge-Anleitung ausgeben (siehe unten).
- **Anhalten.** Die Redaktion prüft im Plugin über Vorschau und Testmail.

### 6. Nach dem Versand

- In `Newsletter-Plan` die verwendeten Zeilen auf `status` = `verwendet` setzen und die Ausgabe eintragen.
- In `Ausgaben` die Zeile ergänzen: Nr., Versanddatum, Betreff, Aufmacher-Format, Farbe, Reihenfolge des Mittelteils, Rückpass-Jahrestag.

## Schreibregeln

- **Du**, warm und direkt, mit Humor, aber ohne Witzelei. Kurze Sätze. Aktiv statt Passiv. Keine Anglizismen, wo es ein gutes deutsches Wort gibt, Fachbegriffe aus dem Hockey bleiben.
- **Länge:** etwa 8 bis 10 Minuten Lesezeit, also 1.600 bis 2.200 Wörter. Davon geht etwa die Hälfte an den Aufmacher.
- **Nichts erfinden.** Keine Zitate, Namen, Zahlen, Ergebnisse oder Erlebnisse, die nicht in einer Quelle oder im Material der Redaktion stehen. Fehlt etwas, kommt ein Platzhalter `[…]` hinein, der sagt, was fehlt. Das gilt besonders für Porträts und Gespräche: Zitate stehen nur drin, wenn sie aus dem gelieferten Gespräch stammen.
- **Keine erfundenen Kennzahlen oder Testimonials** (Projektregel). Die „Große Zahl“ ist immer eine nachprüfbare Zahl mit Quelle, z. B. Jahre seit einem Titel.
- **Jede Kurzpass-Meldung hat eine Quelle** als Link am Ende, gezeigt als Domain („hockey.de“).
- **Namen und Vereine** genau wie in der Quelle schreiben.
- **Bilder** nur aus der Mediathek des Portals: eigene Fotos oder der Drive-Ordner der Redaktion. Nie Bilder von verlinkten Seiten übernehmen (Urheberrecht). Im Markup steht für ein Bild nur ein Platzhalter-Absatz `[Bild: …, Alternativtext: …]`. Das Bild setzt die Redaktion im Editor.
- **Keine Mitgliederdaten** aus dem Portal oder aus WISO in den Text und keine Bank- oder Beitragsdaten (Projektregel).
- Die Anrede „Hallo Vorname,“, das Logo und die Fußzeile mit Abmeldelink setzt das Plugin. Nicht ins Markup schreiben. Die Anrede steht direkt unter der Bully-Rubrik, der Bully beginnt deshalb klein und schließt an sie an („heute ist …“).

## Einfügen ins Plugin (Anleitung für die Redaktion)

1. Portal → Redaktion → Newsletter → **Neue Ausgabe anlegen**.
2. Titel = Betreff. Rechts unter „Newsletter“: **Ausgabe**, **Vorschautext**, **Farbe des Viertels**.
3. Oben rechts ⋮ → **Code-Editor** (oder Strg/Cmd + Umschalt + Alt + M), den gesamten Inhalt der `.html`-Datei einfügen, dann über ⋮ → **Visueller Editor** zurück.
4. Platzhalter `[…]` füllen, Bilder aus der Mediathek setzen (mit Alternativtext).
5. Speichern → **Vorschau, Test und Prüfung im Portal** → Testmail an dich → Prüfliste → bereit melden.

Meldet der Editor bei einem Baustein „unerwarteter Inhalt“, war das Markup fehlerhaft. Dann den Baustein über „Wiederherstellen“ reparieren lassen und mir das Markup zeigen.
