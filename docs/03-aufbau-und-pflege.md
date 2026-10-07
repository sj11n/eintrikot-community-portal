# Aufbau und Pflege

Stand: Theme 0.9.1, Community Core 0.17 auf http://eintrikot.myemmel.com.

## Repository

| Ordner | Inhalt |
|---|---|
| `wp-content/themes/eintrikot-community` | Gestaltung: Montserrat, Farben, Logo, Band, Icons, Favicon, Kopf- und Fußzeile, Vorlagen |
| `wp-content/plugins/eintrikot-community-core` | Vereinslogik: Rollen, Portal, Profile, Verzeichnis, Service-Anfragen, Protokoll, Kennzahlen, Aufnahme, Urkunde, Anmeldung |
| `.github/workflows`, `tools/deploy.sh` | Prüfen bei jedem Push, Einspielen auf STRATO (docs/05) |
| `docs` | Briefing, Entscheidungen, Anleitungen |

Nur Theme und Plugin werden eingespielt. Alles andere bleibt im Repository.

## Theme

- `templates/mvp-public.html`: öffentliche Seiten. `mvp-portal.html`: Mitgliederportal. `page.html` nutzt dasselbe Aussehen wie die öffentlichen Seiten, `single.html` für News-Beiträge, `index.html`, `search.html` und `404.html` für Archive, Suche und Fehlerseite. Alle verwenden `parts/mvp-header` und `mvp-footer`.
- `patterns/mvp-*.php`: Ausgangsinhalte der Seiten. Beim Einfügen werden sie zu normalen Blöcken. Danach werden Texte und Bilder unter **Seiten** gepflegt, nicht im Theme. Ein Theme-Update überschreibt gespeicherte Seiten nicht.
- `assets/mvp.css` ist die freigegebene Gestaltung, `wordpress.css` passt WordPress-Blöcke an, `refresh.css` enthält die späteren Verfeinerungen. `login.css` gestaltet die WordPress-Anmeldeseiten.

## Pflege im Backend

- **Seiten:** öffentliche Texte und Bilder. Hero und Bildplätze sind Cover-Blöcke; Bild einsetzen: Seite bearbeiten → den grauen Bildplatz („Bild folgt") anklicken → in der Werkzeugleiste „Medien hinzufügen" bzw. „Ersetzen" → Bild aus der Mediathek wählen oder hochladen → Ausschnitt über den Fokuspunkt (Seitenleiste). Der Platzhaltertext verschwindet, sobald ein Bild drin ist. Die Bildplätze laufen über die ganze Breite (16:7, am Handy 4:3); der Hero füllt auf jedem Gerät genau den Bildschirm.
- **Menschen:** Fotos von Gründungsmitgliedern, Vorstand und Beirat kommen aus dem Mitgliederportal, wenn die Person im Profil „Profilbild auf der Website" angehakt hat (Zuordnung über den Namen), sonst Initialen. Hintergrund der Initialen Rosé für Damen, Hellblau für Herren (aus dem Team im Profil, für die sieben Gründungsmitglieder hinterlegt).
- **Menschen:** Vorstand als große Karten mit Foto, Beirat auf grüner Fläche, Gründungsmitglieder am Ende als Namensliste mit Gründungsdatum.
- **Startseite, Hero:** Das Band steht steil im rechten Teil und läuft oben und unten aus dem Bild, nie über dem Text; am Handy liegt es flach im freien unteren Bereich und läuft links und rechts hinaus; „Mitglied werden" bleibt im Hero weiß, sonst beim Darüberfahren Gold.
- **Unterstützen:** drei Karten (Spenden mit Konto und PayPal, Jahresspende, Mit anpacken), darunter der Dank. Kontodaten stehen in `texts.php` (DONATION_IBAN, DONATION_PAYPAL). Ein im Backend gesetztes Bild hat Vorrang.
- **Beiträge:** öffentliche News. Die Startseite zeigt die drei neuesten, die News-Seite neun pro Seite.
- **Vereinsinfos:** interne Mitteilungen im Portal. **EINTRIKOT Kalender:** Termine und Jahrestage.
- **Community-Aufbau:** Kennzahlen (mit Stand-Datum), Beitritt (MeinVerein-Link), Social Media (Adressen der Vereinskanäle: Footer „Folge uns“, LinkedIn-Hinweis unter den News-Listen, „sameAs“ für Suchmaschinen; leer = nicht sichtbar), Aufnahme & Urkunde, Aktualisierungen (u. a. „Interne Links domainunabhängig machen" vor dem Umzug, „Datenschutzerklärung aktualisieren").
- **Datenschutz:** Der Text liegt als Vorlage `patterns/mvp-datenschutz.php`. Bei Änderungen an Diensten, Abläufen oder Speicherfristen muss er angepasst werden. Übernahme aus NDAlumni und Jahresspende sind in 5.2 und 5.9 beschrieben. Speicherfristen, die der Code umsetzt: Änderungsprotokoll und abgeschlossene Service-Anfragen 24 Monate (`retention.php`), Nachweis der Elternzustimmung bis drei Jahre nach dem 18. Geburtstag (`consent.php`). Beim Geburtsdatum protokolliert das Änderungsprotokoll nur „geändert". Die Newsletter-Einstellung ist standardmäßig aus (Einwilligung).

## Mitgliederportal

- Navigation: Start · Mitglieder · Vereinsinfos · Termine · Service. Profil, Redaktion und Verwaltung im Konto-Menü.
- Jeder Bereich hat seine CI-Farbe im Seitenkopf. Avatare ohne Foto zeigen Initialen, Rosé bei Damen, Hellblau bei Herren; neutral grau, wenn der Hockey-Abschnitt nicht geteilt ist.
- Verzeichnis: Suche über freigegebene Felder und DHB-Stationen, Filter als Chips, „Zurück zur Suche" behält Suche und Position. Automatisch erscheinen nur Konten mit EINTRIKOT-Rolle; die Verwaltung kann pro Konto „Immer anzeigen" oder „Nicht anzeigen" wählen.
- Profil: DHB-Vita (Rolle, Team, Altersklasse als Auswahl; Zeitraum), Länderspiele gesamt, Beruf mit Status-Auswahl, Mentoring als Ankreuzfelder („biete an“ / „suche“), Sichtbarkeit pro Abschnitt, Foto mit Zuschnitt. Scheitert das Speichern, bleiben die Eingaben 30 Minuten als Entwurf erhalten, bis sie gespeichert oder verworfen werden. Profilbilder liegen geschützt in den Benutzerdaten, nicht in der Mediathek.
- Social-Media-Links zu LinkedIn, Facebook und Instagram im Abschnitt „Social Media“ (eigener Sichtbarkeitsschalter), angezeigt im Profilkopf nur bei vorhandenem Link; LinkedIn mit Logo und Namen zuerst, in der Mitgliederliste zusätzlich ein kleines LinkedIn-Logo neben dem Namen. Erlaubt sind nur Links auf die jeweilige Domain; bei Instagram und Facebook auch „@name“. Die Logos liegen als `assets/social/linkedin.svg`, `facebook.svg`, `instagram.svg` im Plugin (offizielle Dateien der Netzwerke; Instagram als PNG, weil Metas Verlaufs-SVG ein eingebettetes 10-MB-Bild ist; einfarbige Varianten für den Footer in `assets/social/mono/`); fehlt eine Datei, steht der Name des Netzwerks.
- Mitgliedschaft: Mitgliedsnummer, Eintrittsdatum, Jahresbeitrag und Jahresspende kommen aus dem Import (MeinVerein) und stehen nur im eigenen Profil und für die Verwaltung; andere Mitglieder sehen sie nicht. Korrektur durch die Verwaltung im Abschnitt Verwaltung des Profils, protokolliert (docs/07).
- DHB-Vita: pro Station optional die Position (Vorschläge Tor, Abwehr, Mittelfeld, Sturm), durchsuchbar. Länderspiele sind Freitext; für die Kennzahl zählt die erste Zahl („185 (A-Kader)“ → 185).
- Social Media: Name beim Netzwerk genügt („@name“ bei Instagram/Facebook, „name“ bei LinkedIn) oder ein Link.
- Service-Anfragen mit Vorgangsnummer und Verlauf; Bearbeitung durch Vorstand und Admin (docs/07).
- Änderungsprotokoll unter `/community-protokoll/`, nur lesbar.
- Geschützte Seiten werden nicht zwischengespeichert (`no-store, private`) und nicht indexiert.

## Lokaler Test

Isoliertes WordPress mit SQLite und synthetischen Testmitgliedern (Rollen Mitglied, Vorstand, Administrator), ausgehende Mails werden abgefangen. Aufbau und Regeltests: `tools/lokal-test.sh` (siehe docs/05). Prüfung der Oberfläche jeweils am Desktop sowie bei 390 und 320 px.

## Rollen vergeben und entziehen

Rollen vergibt nur ein **Administrator im WordPress-Backend**, bewusst nicht im Portal: Es geht um die heikelste Berechtigung im System, um wenige Personen und um seltene Änderungen. Eine eigene Oberfläche brächte mehr Risiko und Code als Nutzen. Neu prüfen, wenn mehrere Personen ohne Administratorrecht regelmäßig Rollen verwalten sollen.

**Vergeben:** Backend → Benutzer → Alle Benutzer → Person öffnen → **Rolle** wählen → „Benutzer aktualisieren“. Mehrere auf einmal: Personen ankreuzen, „Ändern der Rolle in …“ wählen, „Ändern“.

| Rolle | Rechte |
|---|---|
| EINTRIKOT Mitglied | Portal, Profil, Verzeichnis, Aktuelles, Service |
| EINTRIKOT Redaktion | wie Mitglied, dazu Vereinsinfos und Termine pflegen |
| EINTRIKOT Vorstand | wie Redaktion, dazu Verwaltung: Mitglieder aufnehmen, fremde Profile bearbeiten, Service-Anfragen, Einladungen, Protokoll |
| Administrator | alles, auch Backend-Einstellungen, Plugins, Kennzahlen |

**Nachvollziehen:** Jeder Rollenwechsel steht im Änderungsprotokoll (Verwaltung → Änderungsprotokoll, Feld „Rolle“: wer, wann, von welcher auf welche Rolle). Neue Konten aus dem Import erscheinen dort nicht als Rollenwechsel. Die Verwaltung sieht die Rolle außerdem im Profil des Mitglieds („Rolle: EINTRIKOT Vorstand“), Mitglieder nicht.

**Wechsel im Vorstand:**
1. Rolle der ausscheidenden Person auf „EINTRIKOT Mitglied“ setzen (oder Redaktion).
2. Prüfen, dass sie keine Administratorrolle hat.
3. Zugänge außerhalb des Portals entziehen: STRATO, GitHub (Repository und Environment), Passwortmanager, Postfach `info@eintrikot.de`, MeinVerein.
4. Im Änderungsprotokoll prüfen, dass der Wechsel steht.
5. Neue Person: Rolle „EINTRIKOT Vorstand“, danach im Portal ansehen, ob „Verwaltung“ im Kontomenü erscheint.

## Mitglieder löschen, Austritt und Löschwunsch

**Wer löscht?** Nur ein **Administrator im WordPress-Backend**: Benutzer → Alle Benutzer → Konto → „Löschen“. Der Vorstand hat im Portal keine Löschfunktion (bewusst: nicht umkehrbar, datenschutzrelevant). Einzelne Konten anlegen geht für den Vorstand mit einer Datei mit einer Zeile (Neue Mitglieder aufnehmen), nicht über Backend → Benutzer → Neu hinzufügen (dort fehlt die Mitgliedsnummer).

**Was beim Löschen mit den Daten passiert**
- Profil, Profilbild, Geburtsdatum, Einwilligungsnachweis und alle weiteren Profildaten werden mit dem Konto gelöscht.
- **Termin-Anmeldungen** werden sofort mit gelöscht (seit 0.21.2).
- **Service-Anfragen** der Person: WordPress fragt beim Löschen, ob sie gelöscht oder einem anderen Konto zugeordnet werden. Bei Löschwünschen „löschen“ wählen.
- Im **Änderungsprotokoll** bleibt stehen, wer wann welches Feld geändert hat (mit „Benutzer #Nummer“, bis 24 Monate). Die **Werte und Gründe** der gelöschten Person werden beim Löschen durch „[gelöscht]“ ersetzt, und die Löschung selbst wird mit Datum und ausführender Person eingetragen.
- Beiträge, Spendenbescheinigungen und Buchführung liegen in MeinVerein und unterliegen den gesetzlichen Aufbewahrungsfristen; das Portal ist davon getrennt.

**Austritt** (Mitglied tritt aus, kein Löschwunsch)
1. In MeinVerein den Austritt erfassen (führend).
2. Im Portal bis zur Löschung den Zugang sperren: Backend → Benutzer → Rolle auf „Abonnent“ (ohne Portalrecht). Das Konto bleibt, das Mitglied kommt nicht mehr ins Portal und erscheint nicht im Verzeichnis.
3. Nach der vereinbarten Frist (zum Beispiel Ende des Beitragsjahrs) das Konto löschen.

**Löschwunsch nach Art. 17 DSGVO** (Frist: ein Monat)
1. Absender prüfen: Die Bitte kommt von der E-Mail-Adresse im Konto, sonst rückfragen.
2. Konto im Backend löschen, Service-Anfragen mitlöschen.
3. Abgleichen: Im Verzeichnis und bei den Terminen ist die Person nicht mehr zu sehen.
4. Im Änderungsprotokoll prüfen: Die Werte der Person stehen dort als „[gelöscht]“, die Löschung ist eingetragen.
5. E-Mails zur Person im Postfach `info@eintrikot.de` löschen, soweit keine Aufbewahrungspflicht besteht. Newsletter-Verteiler prüfen.
6. Der Person bestätigen, was gelöscht wurde und was aus gesetzlichen Gründen bleibt (MeinVerein; im Änderungsprotokoll nur noch, wer wann welches Feld geändert hat).

**Wenn beim Import ein Konto zu viel entsteht:** im Backend löschen. Ist der Import als Ganzes falsch, die Datenbank-Sicherung vom Importtag zurückspielen (`docs/09`).

## Offen

- Eigenes Mitgliedskonto für Björn (Nr. 1, Rolle Vorstand) neben dem technischen Admin-Konto.
- Altplugins und Altseiten bereinigen (docs/04).
- Umzug auf eintrikot.de mit HTTPS und SMTP (docs/06), danach Einladungen.
- Geschützte Dokumentenablage, Newsletter, zweite Anmeldestufe für Vorstand und Admins, Datenschutzerklärung.

## Statische Analyse (PHPCS und PHPStan)

„Prüfen" führt zusätzlich zur Syntaxprüfung zwei Werkzeuge aus. Beide sind reine Entwicklungswerkzeuge und werden nicht eingespielt.

- **PHPCS** (`phpcs.xml.dist`): nur die Sicherheitsregeln von WordPress – Ausgabe escapen, Eingaben bereinigen, Nonces prüfen, SQL vorbereiten. Der Stil bleibt bei Prettier. Hilfsfunktionen, die fertig escaptes HTML liefern (z. B. `page_head`, `service_link`), sind in der Regeldatei als sicher eingetragen. Wer eine neue solche Funktion anlegt, trägt sie dort ein und escaped Eingaben darin selbst. Beachte: `page_head()` gibt `$lead` und `$extra` unverändert aus, Aufrufer müssen sie vorher escapen.
- **PHPStan** (`phpstan.neon.dist`, Stufe 5, mit WordPress-Erweiterung): findet Typfehler und tote Zweige.
- **Keine Baselines.** Seit 0.19.1 ist der Altbestand abgearbeitet; jeder Fund von PHPCS und PHPStan bricht die Prüfung. Eingaben liest man mit `post_text()`, `post_choice()` oder `directory_param()` (entschlacken und `wp_unslash`). Wo ein Wert bewusst roh bleibt (Passwort, Upload, Nonce), steht ein `phpcs:ignore` mit Begründung direkt daneben.

Lokal (einmalig `composer install`):

```bash
composer analyse        # beides
composer phpcs
composer phpstan
```
