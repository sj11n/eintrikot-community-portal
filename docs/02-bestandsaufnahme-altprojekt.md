# EINTRIKOT: Bestandsaufnahme für den WordPress-Neustart

Stand: 11.09.2026. Maßgeblich ist das neue Nutzerbriefing. Diese Bestandsaufnahme ersetzt für dieses Vorhaben widersprechende Zielentscheidungen der Dokumente 01–09; sie verändert die bestehende Anwendung nicht.

## Prüfstand und Grenzen

Geprüft wurden das lokale Dateiinventar, package.json, vorhandene Architekturunterlagen, globale Gestaltung, Konfiguration und ausgewählte Portal-, Login-, Veranstaltungs-, Dokumenten- und Kontaktdateien. Im Arbeitsverzeichnis befindet sich Next.js 15 / React 19 mit Supabase, zusätzlich ein älterer HTML-Prototyp und ein Pitchdeck. WordPress-Core, wp-content, PHP-Theme und Plugin-Inventar sind hier nicht vorhanden. Der Git-Arbeitsstand war vor dieser Dokumentation sauber.

Ein vorhandenes WordPress-System wurde noch nicht geprüft: URL, Zugang oder Export und STRATO-Tarif fehlen. WordPress-Version, aktive Plugins, Theme, PHP-/Datenbankversion, Speicherlimits, Cron, SMTP, Backup und Inhaltsbestand bleiben ausdrücklich unbekannt. Lokale Dateien belegen keinen aktuellen Produktionszustand. Zugangsdaten wurden nicht aus .env-Dateien ausgelesen.

Vorhandene Markenquelle: assets/eintrikot-logo-blau.svg sowie eine PNG-Variante. Ältere Unterlagen nennen Montserrat und Farben #DA525D, #FFCC4E, #FFFFFF, #F1D1DD, #C8E2EE, #CEDFBD, #BBBABB und Schwarz. Das originale Brandbook ist lokal nicht vorhanden; dessen Regeln sind damit noch nicht verifiziert.

## KEEP / ADAPT / DEFER / REMOVE

REMOVE bedeutet aus dem neuen WordPress-Zielumfang ausschließen, nicht bestehende Dateien oder laufende Dienste löschen.

| Entscheidung | Bestand / Funktion | Konsequenz |
|---|---|---|
| KEEP | Originales Logo, Montserrat, sportliche Vita | Als Referenz erhalten; Brandbook vor verbindlichem Konzept abgleichen. |
| KEEP | MeinVerein als führendes Mitgliedschaftssystem | Beitritt, Beiträge, SEPA, Spenden und rechtlich relevante Daten bleiben dort. |
| KEEP | Google Workspace und Dokumentkategorien | Für Redaktion und geeignete Ablagen nutzen; Zugriffsrechte separat prüfen. |
| ADAPT | Öffentliche Darstellung / Pitchdeck | Vollständige Homepage mit allen zehn Briefing-Abschnitten neu konzipieren. |
| ADAPT | Portal, Profil, Termine, Dokumente | Inhalte und Abläufe prüfen; als WordPress-Oberflächen neu umsetzen. Aktuelle Beispieldaten sind keine echten Inhalte. |
| ADAPT | Login | Ultimate Member mit E-Mail/Passwort, Einladung und Reset statt Magic Link / Google OAuth. |
| ADAPT | Rollen | Besucher, Mitglied, Redaktion, Vorstand, technische Administration; Rechte serverseitig, nicht nur Navigation ausblenden. |
| ADAPT | Profil-Sichtbarkeit | Pro Feld nur ich / Mitglieder / nicht anzeigen. Standard privat; keine öffentliche Profilansicht. Bedeutung: nur ich = eigene Ansicht, Mitglieder = geschützte Mitgliederansicht, nicht anzeigen = auch im eigenen Profil ausgeblendet, im Editor weiter änderbar. Technischer Zugriff berechtigter Administration bleibt gesondert zu regeln. |
| ADAPT | Kontakt und Service-Anfragen | Persistenter Backend-Eintrag plus rollenbezogene E-Mail; Fehler, Wiederholung und Bearbeitungsstatus nachvollziehbar machen. Der bestehende Kontakt-Endpunkt implementiert keinen Versand und keine Speicherung. |
| ADAPT | News und Kommunikation | Editierbarer WordPress-Inhaltstyp, öffentliche/interne Trennung; MailPoet und menschliche Freigabe ergänzen. |
| ADAPT | Backup und Betrieb | WordPress-Dateien und Datenbank gemeinsam sichern; Wiederherstellung in isolierter Umgebung testen. |
| DEFER | Mitgliederverzeichnis | In Version 1 deaktiviert. Profil-Sichtbarkeiten schaffen keine Verzeichnisfunktion. |
| DEFER | Homescreen / PWA | Responsive App-Anmutung zuerst; später Installation prüfen. Geschützte Inhalte nicht unkontrolliert offline cachen. |
| DEFER | Tippspiel, komplexer Shop, Zahlungen, Chat | Nicht Teil dieses V1-Auftrags. Vorhandenes Tippspiel bleibt unangetastet. |
| REMOVE | Next.js-, Node-, Vercel-, Supabase-Zielarchitektur | Keine Produktionsabhängigkeit im neuen Portal. Keine automatische Datenmigration. |
| REMOVE | Vollständiger Mitgliederimport, Finanz-/Bankdatenfunktionen | Keine vollständige Vereinsdatenbank in WordPress. Nur notwendige Zugangsdaten und freiwillige Profilangaben. |
| REMOVE | Öffentliche Registrierung, Demo-Rollenwechsel, Mock-Kennzahlen | Keine Übernahme als produktive Funktion oder belegte Außendarstellung. |
| REMOVE | Dominantes Schwarz, Ersatzmarken, externe Font-CDNs | Neues Design nach Brandbook; Montserrat lokal bereitstellen. |

## Geplante visuelle Freigabeunterlagen

Noch keine Designfreigabe und keine Implementierung. Die Konzepte sollen nach Prüfung der Markenquelle zusammenhängend ausgearbeitet werden:

1. Öffentliche Website: Sticky-Navigation; Hero mit Leitgedanke und beiden CTAs; Kennzahlen mit belegten Werten; Erklärung; drei Lebensphasen; Wirkung; News; generationenübergreifende Testimonials; Partner; Beitrittsabschluss und Footer. Zusätzlich News-Liste und Artikeldetail als wiederverwendbare Vorlagen.
2. Mitglieder-Dashboard: Mitglieder-News, Termine, Service-Anfragen, Dokumente, Profil und Vorstandskontakt; Desktop und mobile Navigation.
3. Profil: sämtliche freiwilligen Briefing-Felder, Sichtbarkeit je Feld, Speichern, Fehler- und Erfolgszustand; kein öffentliches Verzeichnis.
4. Zugang: Login, Passwort vergessen, Reset, Einladung, abgelaufener Link und gesperrter Zugang.
5. Services: Event-Anmeldung, Förderidee, Mitarbeit und Vorstandskontakt samt Bestätigung und Anfrageverlauf; Dokumentliste mit Berechtigungszuständen.
6. Newsletter: E-Mail-Layout, mobile Variante, Anmeldung/Abmeldung, Archiv; redaktioneller Entwurf und Vorstandsfreigabe.

Vorläufige Richtung: Weiß und helles Blau als großzügige Flächen, klare Montserrat-Hierarchie, sparsame Markenakzente. Keine verbindliche neue Markenfarbe ohne Brandbook. Authentische Vereinsbilder mit belegten Nutzungsrechten; keine erfundenen Testimonials, Förderzahlen oder Partner. Fehlende Inhalte in Konzepten eindeutig als Platzhalter kennzeichnen.

## Technische Prüfpunkte vor Umsetzung

- Separates Staging mit eigener Datenbank, Zugriffsschutz und Suchmaschinen-Sperre; ausgehende E-Mails dort abfangen und keine echten Newsletter verschicken.
- Vor erster Änderung vollständiges Backup samt dokumentiertem Restore-Test. Keine Produktionsänderung vor Abnahme.
- Schlankes Custom Theme für Darstellung; vereinseigene Funktionen in separatem Plugin, damit ein Themewechsel keine Datenlogik entfernt. Ultimate Member und MailPoet gemäß Briefing vorsehen. Konkrete Versionen, Fähigkeiten und Zusatzbedarf erst am Staging prüfen.
- Einladung erst nach bestätigtem MeinVerein-Beitritt; minimaler Zugangssatz, befristete einmalige Aktivierung, Sperrung bei entzogenem Zugang. Keine offene Registrierungsroute, auch nicht über API oder Plugin-Standardseiten.
- Rechte für Seiten, REST-Endpunkte, Profilfelder, Suchergebnisse, Feeds und Dateien prüfen. Eine versteckte Dokumentseite schützt keine öffentlich abrufbare Datei; Drive-Berechtigungen oder geschützte Auslieferung erforderlich.
- Login-Rate-Limit, generische Fehlermeldungen, sichere Reset-Tokens und Cache-Ausschluss geschützter Antworten vorsehen.
- Newsletter-Abonnement separat führen. Redaktion erstellt Entwürfe; Vorstand gibt konkrete Ausgabe frei. Technisch kontrollieren, dass Redaktion weder direkt noch über Zeitplanung/API senden kann. Auch Testsendungen nur an festgelegte Testempfänger.
- Workspace-Redaktion: Gmail-Label Newsletter/Eingang; Form „Thema für EINTRIKOT vorschlagen“; Sheet mit Quelle, Datum, Link, Bildstatus, Relevanz, Status, Freigabe; Drive-Ordner für Bilder, freigegebene Texte, Ausgabenarchiv. Noch nicht eingerichtet.
- KI-Ablauf: sammeln → recherchieren → Setzen/Optional/Nacharbeiten/Zurückstellen → menschliche Themenfreigabe → Kanalentwürfe → Fakten-, Link-, Bildrechte- und Abmeldeprüfung → menschliche Veröffentlichungs-/Versandfreigabe.
- Zusatzkosten maximal 15 EUR/Monat. Noch keine belastbare Kostenbestätigung: Empfängerzahl, Versandfrequenz, aktuelle MailPoet-Konditionen, bestehender Hostingtarif und Backup-Leistungen müssen geprüft werden. Keine kostenpflichtigen Dienste bestellt.
- Impressum, Datenschutz, Einwilligungen und Aufbewahrungsfristen bleiben offene fachliche/rechtliche Prüfpunkte. Frühere Dokumentaussagen „geklärt“ sind hierfür kein Nachweis.

## Abnahmetests nach jedem Implementierungsabschnitt

Desktop sowie 320, 375 und 768 px: keine horizontalen Überläufe, lesbare Texte, bedienbare Navigation, ausreichend große Ziele, Tastaturbedienung und sichtbarer Fokus.

Zugang: unbekannte E-Mail, falsches Passwort, Rate-Limit, gültiger/abgelaufener/verwendeter Reset-Link, Einladung und gesperrtes Mitglied. Besucher dürfen weder direkt noch über API geschützte Inhalte abrufen.

Profil: alle Felder freiwillig; jede Sichtbarkeitsstufe als Eigentümer, anderes Mitglied und Besucher prüfen; keine fremden Änderungen möglich.

Services: gültige und ungültige Eingabe, doppelte Übermittlung, Backend-Nachweis, korrektes Routing, Versandfehler und kontrollierter Wiederholungsversand.

Newsletter: getrennte Einwilligung, Abmeldung, Testversand, Archivberechtigung, Versandversuch als Redaktion muss scheitern, freigegebene Ausgabe als Vorstand prüfen.

Betrieb: Restore und Update-Rollback auf Staging; abschließend Vorstand/Beirat plus 10–20 Testmitglieder, dokumentierte Abnahme und Go-live-Entscheidung.

## Benötigte Grundlagen für den nächsten Schritt

WordPress-URL und Staging-Zugang oder ein bereinigter Systembericht mit Theme-/Pluginliste und Hostingtarif; originales Brandbook als Datei oder zugänglicher Link. Für die spätere Befüllung zusätzlich MeinVerein-Beitrittslink, freigegebene Bilder/Texte, echte Kennzahlen und Newsletter-Empfängerzahl/Versandrhythmus. Keine Passwörter in diese Dokumentation schreiben.
