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
- **Menschen:** Fotos von Gründungsmitgliedern, Vorstand und Beirat kommen aus dem Mitgliederportal, wenn die Person im Profil „Profilbild auf der Website" angehakt hat (Zuordnung über den Namen), sonst Initialen. Ein im Backend gesetztes Bild hat Vorrang.
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

Isoliertes WordPress mit SQLite und synthetischen Testmitgliedern (Rollen Mitglied, Vorstand, Administrator), ausgehende Mails werden abgefangen. Prüfung jeweils am Desktop sowie bei 390 und 320 px.

## Offen

- Eigenes Mitgliedskonto für Björn (Nr. 1, Rolle Vorstand) neben dem technischen Admin-Konto.
- Altplugins und Altseiten bereinigen (docs/04).
- Umzug auf eintrikot.de mit HTTPS und SMTP (docs/06), danach Einladungen.
- Geschützte Dokumentenablage, Newsletter, zweite Anmeldestufe für Vorstand und Admins, Datenschutzerklärung.
