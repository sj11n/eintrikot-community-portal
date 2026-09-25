# Aufbau und Pflege

Stand: Theme 0.9.1, Community Core 0.16 auf http://eintrikot.myemmel.com.

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

- **Seiten:** öffentliche Texte und Bilder. Hero und Bildplätze sind Cover- bzw. Bildblöcke; Bild über die Mediathek ersetzen, Ausschnitt über den Fokuspunkt.
- **Beiträge:** öffentliche News. Die Startseite zeigt die drei neuesten, die News-Seite neun pro Seite.
- **Vereinsinfos:** interne Mitteilungen im Portal. **EINTRIKOT Kalender:** Termine und Jahrestage.
- **Community-Aufbau:** Kennzahlen (mit Stand-Datum), Beitritt (MeinVerein-Link), Aufnahme & Urkunde, Aktualisierungen (u. a. „Interne Links domainunabhängig machen" vor dem Umzug, „Datenschutzerklärung aktualisieren").
- **Datenschutz:** Der Text liegt als Vorlage `patterns/mvp-datenschutz.php`. Bei Änderungen an Diensten, Abläufen oder Speicherfristen muss er angepasst werden. Speicherfristen, die der Code umsetzt: Änderungsprotokoll und abgeschlossene Service-Anfragen 24 Monate (`retention.php`), Nachweis der Elternzustimmung bis drei Jahre nach dem 18. Geburtstag (`consent.php`). Beim Geburtsdatum protokolliert das Änderungsprotokoll nur „geändert". Die Newsletter-Einstellung ist standardmäßig aus (Einwilligung).

## Mitgliederportal

- Navigation: Start · Mitglieder · Vereinsinfos · Termine · Service. Profil, Redaktion und Verwaltung im Konto-Menü.
- Jeder Bereich hat seine CI-Farbe im Seitenkopf. Avatare ohne Foto zeigen Initialen, Rosé bei Damen, Hellblau bei Herren; neutral grau, wenn der Hockey-Abschnitt nicht geteilt ist.
- Verzeichnis: Suche über freigegebene Felder und DHB-Stationen, Filter als Chips, „Zurück zur Suche" behält Suche und Position. Automatisch erscheinen nur Konten mit EINTRIKOT-Rolle; die Verwaltung kann pro Konto „Immer anzeigen" oder „Nicht anzeigen" wählen.
- Profil: DHB-Vita (Rolle, Team, Altersklasse als Auswahl; Zeitraum), Länderspiele gesamt, Beruf mit Status-Auswahl, Mentoring als Ankreuzfelder („biete an“ / „suche“), Sichtbarkeit pro Abschnitt, Foto mit Zuschnitt. Scheitert das Speichern, bleiben die Eingaben 30 Minuten als Entwurf erhalten, bis sie gespeichert oder verworfen werden. Profilbilder liegen geschützt in den Benutzerdaten, nicht in der Mediathek.
- Social Media: Links zu LinkedIn, Facebook und Instagram im Abschnitt „Social Media“ (eigener Sichtbarkeitsschalter), angezeigt im Profilkopf nur bei vorhandenem Link. Erlaubt sind nur Links auf die jeweilige Domain; bei Instagram und Facebook auch „@name“. Die Logos liegen als `assets/social/linkedin.svg`, `facebook.svg`, `instagram.svg` im Plugin (offizielle Dateien aus den Markenportalen der Netzwerke); fehlt eine Datei, steht der Name des Netzwerks.
- Service-Anfragen mit Vorgangsnummer und Verlauf; Bearbeitung durch Vorstand und Admin (docs/07).
- Änderungsprotokoll unter `/community-protokoll/`, nur lesbar.
- Geschützte Seiten werden nicht zwischengespeichert (`no-store, private`) und nicht indexiert.

## Lokaler Test

Isoliertes WordPress mit SQLite und synthetischen Testmitgliedern (Rollen Mitglied, Vorstand, Administrator), ausgehende Mails werden abgefangen. Prüfung jeweils am Desktop sowie bei 390 und 320 px.

## Offen

- Urkunden-Vorlage hochladen; Anzeigename und Verzeichnis-Einstellung des eigenen Kontos.
- Altplugins und Altseiten bereinigen (docs/04).
- Umzug auf eintrikot.de mit HTTPS und SMTP (docs/06), danach Einladungen.
- Geschützte Dokumentenablage, Newsletter, zweite Anmeldestufe für Vorstand und Admins, Datenschutzerklärung.
