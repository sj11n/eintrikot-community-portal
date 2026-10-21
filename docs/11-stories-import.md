# Stories aus NDAlumni übernehmen

Stand: 22.10.2026. Die 41 öffentlichen Beiträge aus 2025 und 2026 (Sicherung im Ordner „News sammeln Eintrikot“) werden als Stories übernommen. Alles passiert zuerst auf der Entwicklungsseite. Die Quelldaten bleiben unverändert, die vorbereiteten Pakete liegen nur lokal und kommen nie ins Repository.

## Überblick

1. **Vorbereiten (lokal):** `tools/stories-bundle.php` bereinigt die Beiträge und erzeugt Pakete.
2. **Importieren (Backend):** Community-Aufbau → **Stories importieren**: Paket hochladen, Probelauf ansehen, importieren. Alles kommt als **Entwurf**.
3. **Prüfen und veröffentlichen:** Entwürfe ansehen, Rubrik, Titelbild und Text kontrollieren, dann veröffentlichen.

## 1. Vorbereiten

```bash
WP_ROOT=.lokal/wordpress php tools/stories-bundle.php "/Users/…/News sammeln Eintrikot"
```

Ergebnis in `~/Downloads/Stories-Import` (außerhalb des Repositorys): `stories-1.zip`, `stories-2.zip` (je höchstens 25 MB Bilder, mit `--max-mb=10` kleiner, falls der Server weniger Upload erlaubt) und `Pruefbericht.txt`.

Was aufgeräumt wird:

- **Titel:** Reihenfolge-Präfix „(M)EinTrikot –“ weg, Leerzeichen, „…“, Apostroph und Anführungszeichen, „News – Juli 2026“ heißt „Newsletter Juli 2026“.
- **Adressen** ohne Reste wie `und-ndash`; Umlaute als `ae`, `oe`, `ue`.
- **Rubrik** aus dem Titel: Menschen („Was macht eigentlich…“, „Wer ist eigentlich…“, Beirat, Interviews, Danke), Treffen (Meetup, Bridge, Netzwerk-Coach), Teams (Turniere, U21, Pro League, Jugend-DM), Hockeywelt (Bundesliga, Podcast, Hockeyjahr, „OLY“), sonst Verein. Die Zuordnung steht im Prüfbericht.
- **Text** als Blöcke (Absatz, Überschrift, Liste, Bild, Knopf); Links nur mit sicheren Adressen; Serienbrief-Anrede „Hallo {usersFirstname}“ entfernt.
- **Bilder** auf höchstens 2000 Pixel; Alternativtext aus dem Titel; das erste Bild wird Titelbild (und nicht doppelt im Text gezeigt).
- **Vorschau** (Auszug) aus den ersten Wörtern.

Der Prüfbericht nennt außerdem alle Links auf `eintrikot.de`. Sie zeigen heute auf die alte NDAlumni-Seite und müssen nach dem Umzug geprüft werden (derzeit 4).

## 2. Importieren

1. **Vorher:** Datenbanksicherung im STRATO-Kundenbereich.
2. Backend → Community-Aufbau → **Stories importieren** → `stories-1.zip` wählen → „Paket prüfen“.
3. **Probelauf:** Tabelle mit Datum, Rubrik, Titel, Bildern und Status (neu oder schon vorhanden). Es wird nichts angelegt.
4. „Als Entwürfe importieren“. Das läuft in Etappen von vier Beiträgen und geht von selbst weiter.
5. Danach `stories-2.zip` genauso. Schon importierte Beiträge werden an der alten Adresse erkannt und übersprungen, ein zweiter Lauf legt nichts doppelt an.

Was die Funktion sicherstellt: nur Administratoren, Sicherheitsprüfung bei jedem Schritt; im Paket sind nur `stories.json` und JPEG/PNG-Bilder erlaubt (keine Pfade mit `..`, keine Skripte, Größen begrenzt); der entpackte Ordner ist für Besucher gesperrt und wird nach dem Import gelöscht; Text wird durch den WordPress-Filter für Beiträge geleitet; Adressen eigener Bilder bleiben relativ.

**Zurücknehmen:** Auf derselben Seite gibt es „Import zurücknehmen“. Es löscht alle importierten Stories (auch veröffentlichte) und ihre Bilder wieder. Eigene, von Hand geschriebene Beiträge bleiben unberührt. Alternativ hilft die Datenbanksicherung.

## 3. Prüfen und veröffentlichen

- Beiträge → Entwürfe: Titel, Rubrik, Titelbild und Text je Beitrag ansehen (Vorschau).
- Veröffentlichen: Beiträge → Mehrfachaktionen → Bearbeiten → Status „Veröffentlicht“. Das Veröffentlichungsdatum bleibt das alte, die Reihenfolge stimmt also.
- Ein Beitrag, der nicht mehr aktuell ist (zum Beispiel die Stellenausschreibung „Netzwerk-Coach“ mit Bewerbungsfrist 31.8.2025), kann Entwurf bleiben oder gelöscht werden.
- Titelbilder: Große, breite Bilder (ab 1400 Pixel Breite) laufen als Banner über die Seite, kleinere, quadratische oder hochformatige stehen in der Textspalte unter dem Titel, damit sie scharf bleiben.

## Beim Umzug auf eintrikot.de

- Die neue Domain zeigt auf dieselbe Installation (`docs/06`). Die Bilder in `wp-content/uploads` ziehen deshalb mit. Nur wenn je eine **neue** Installation aufgesetzt wird, muss der Ordner `uploads` zusätzlich zur Datenbank kopiert werden.
- Bilder und interne Links stehen als relative Adressen im Text und brauchen kein Suchen und Ersetzen.
- Die vier Links auf die alte Seite (siehe Prüfbericht) nach dem Umzug prüfen.
