# Entscheidungen für den Ausbau – 23.09.2026

Getroffen von Björn Emmerling im Review vom 23.09.2026. Ergänzt das Originalbriefing; bei Widerspruch gilt dieses Dokument.

## Öffentliche Website

- **Mitglied werden:** Beitritt ab mindestens einem Länderspiel in einem DHB-Nationalteam, auch Jugend. Mitgliedsbeitrag 50 € pro Jahr ab 32 Jahren, darunter beitragsfrei. Zusätzlich eine **freiwillige Jahresspende** (50/100/150 € oder frei), eindeutig als freiwillig und als Spende gekennzeichnet, nicht als Beitrag.
- **Beitritts-Button:** Backend-Feld für den MeinVerein-Link. Solange leer, wird kein Beitritts-Button angezeigt (keine Ersatzaktion), da noch nicht live.
- **Hero-Text:** Bezeichnungen „Danas" und „Honamas" bleiben unverändert.
- **Kennzahlen:** Stand-Datum unter den Zahlen, im Backend pflegbar. „Länderspiele" erscheint erst, wenn mindestens 60 % der Portalprofile eine Angabe haben, und summiert nur für Mitglieder freigegebene Angaben.
- **Linkvorschau:** Titel „EINTRIKOT – Das Netzwerk der Hockey-Nationalteams", Beschreibung „Wir verbinden Nationalspielerinnen und Nationalspieler aller Generationen – von der Jugend bis zu den Masters.", Vorschaubild Logo auf Hero-Verlauf (1200×630).

## Mitgliederportal

- **Kontakt:** Beides. (1) „Kontakt aufnehmen" per E-Mail über das Portal, Empfängeradresse bleibt verborgen, Absenderadresse wird mitgeschickt, pro Profil abschaltbar. (2) Zusätzlich freiwillig geteilte Kontaktfelder (E-Mail, Telefon, LinkedIn) mit eigener Sichtbarkeit.
- **Unter 18:** Im Verzeichnis sichtbar mit Name, Team, Altersklasse und Region; kein Alter, kein Wohnort, keine Kontaktfelder, kein Geburtstag in den Vereinsinfos. Zusätzlicher Hinweis im Profil und optionaler **Jugendmodus**: dann nur Name und Team sichtbar. Ab dem 18. Geburtstag gelten automatisch die Erwachsenenregeln. Grundlage ist das Geburtsdatum; ohne Geburtsdatum gelten die Erwachsenenregeln.
- **Benachrichtigungen:** Neue Service-Anfragen an eine feste, im Backend einstellbare Adresse. Mitglieder erhalten bei jeder Rückmeldung bzw. Statusänderung eine E-Mail.
- **Mailversand:** SMTP über ein STRATO-Postfach per SMTP-Plugin (z. B. WP Mail SMTP). Zugangsdaten nur im Backend, nie im Code. Der Code nutzt ausschließlich `wp_mail`.
- **Änderungsprotokoll:** Aufbewahrung 24 Monate, danach automatische Löschung. Geburtsdatum und vergleichbar sensible Felder werden nur als „geändert" protokolliert, nicht im Klartext.

## Umsetzungspakete

1. Öffentliche Website: Hero mobil, Vision-Block, Kennzahlen, Linkvorschau, Mitglied werden (inkl. Backend-Feld).
2. Portal-Grundlagen: Navigation (Start, Mitglieder, Termine, Profil, Mehr), Service in zwei Gruppen, Login-Hinweis zur Aktivierung.
3. Sichtbarkeit: eigene Karte ein/aus, Jugendregeln und Jugendmodus, Sichtbarkeit pro Abschnitt statt pro Feld.
4. Kontakt: Kontaktanfrage per Mail, Kontaktfelder.
5. Wiederfinden: Zeitraum aus DHB-Vita auf Karte und als Filter.
6. Persönliche Startseite und Einstieg beim ersten Login.
7. Benachrichtigungen und Protokoll-Aufbewahrung.
8. Technik: Avatare als geschützte Dateien, Einmal-Schaltflächen entfernen, Altlasten im Theme bereinigen.

## Testen

Gewünscht: isoliertes lokales WordPress mit SQLite und synthetischen Profilen. Stand 23.09.2026 blockiert die Netzwerkfreigabe wordpress.org sowohl in der Cloud-Umgebung als auch auf dem Mac-Arbeitsbereich.
