# WordPress-Umsetzung – Stand 14. September 2026

Auf der freigegebenen Entwicklungsdomain http://eintrikot.myemmel.com sind Theme **0.5.1** und Community Core **0.5.0** installiert. Die vollständigen acht öffentlichen MVP-Seiten wurden übernommen. Die echte Domain eintrikot.de bleibt unverändert.

## Aufbau und Pflege

- `wp-content/themes/eintrikot-community`: Gestaltung, Montserrat, Brandfarben, SVG-Icons, öffentliche Header/Footer und Blockvorlagen. `assets/mvp.css` bewahrt die akzeptierte Vorschau; `assets/wordpress.css` passt native WordPress-Blöcke an.
- `patterns/mvp-*.php`: Ausgangsinhalte, bei Übernahme als normale WordPress-Blöcke in Seiten gespeichert. Redaktionelle Änderungen erfolgen anschließend unter **Seiten**, nicht im Theme. Ein späteres Theme-Update überschreibt gespeicherte Seiten nicht.
- `templates/mvp-public.html`: öffentliche Seiten. Eindeutige Template-Parts `mvp-header` / `mvp-footer` umgehen die früheren, in der Datenbank gespeicherten Header/Footer.
- Öffentliche News sind normale **Beiträge**. Startseite zeigt die letzten drei, die News-Seite neun pro Seite mit Seitennavigation. Beitragsbild und Inhalt werden nativ gepflegt.
- Hero-Cover können im Editor ein flächiges Bild bekommen. Gründungsmitglieder, Vorstand und Beirat haben kleine austauschbare Bildplätze. Die drei Engagement-Themen besitzen optionale Bildblöcke, die ohne Bild unsichtbar bleiben.
- Kennzahlen: **Community-Aufbau → Kennzahlen**. Länderspiele werden aus numerischen Gesamtangaben der Portalprofile summiert; bei fehlenden Angaben wird kein erfundener Gesamtwert angezeigt.

## Portal

Native WordPress-Anmeldung und ein eigenes Plugin für die Vereinslogik. Das Portal hängt nicht von Ultimate Member ab; vorhandene Altplugins sind noch nicht bereinigt.

- Rollen: Mitglied, Redaktion, Vorstand und Administrator. Vorstand/Admin bearbeiten fremde Mitgliederprofile; Mitglieder nur das eigene. Redaktion erhält nur die benötigten Rechte für eigene öffentliche Beiträge und Bilder, keine neuen Rechte zum Bearbeiten/Löschen fremder Beiträge.
- Mitgliederverzeichnis: kompakte Namenszeile, Team / Altersklasse / Phase / hervorgehobener Wohnort, Suche über freigegebene Felder und DHB-Stationen, kompakte Filter und 25 Ergebnisse pro Seite.
- Profil: strukturierte DHB-Vita, Beruf/Ausbildung und Interessen als aufklappbare Bereiche, Geburtsdatum privat, Alter optional sichtbar, Newsletter-Einstellung und unverbindliches Interesse am freiwilligen Förderbeitrag.
- Profilbilder werden verkleinert in geschützten Benutzermetadaten gespeichert, nicht über eine öffentliche Medien-URL ausgeliefert.
- Fehler erhalten Formulareingaben. Profilrevisionen verhindern stilles Überschreiben zwischenzeitlicher Änderungen; Avatar, Profil und Audit werden zusammen kontrolliert gespeichert.
- Service-Anfragen: Event, Förderidee, Mithilfe, Kontakt, Stammdaten, Bankwechsel und jährlicher Förderbeitrag. Bankwechsel erfasst keine IBAN. Anfragen mit Vorgangsnummer, Status und sichtbarer Rückmeldung; interne Notizen ausschließlich Vorstand/Admin. Doppelte Formularübermittlung erzeugt keinen zweiten Vorgang.
- Bearbeitung: Eingegangen → In Prüfung → abgeschlossen/abgelehnt. Finanz-/Stammdatenübernahme wird ausschließlich manuell in MeinVerein bestätigt. Keine automatische Übertragung, Zahlung oder Mail.
- Änderungsprotokoll: eigene geschützte Seite `/community-protokoll/`, nur lesbar, mit Bearbeiter, Zeitpunkt, Grund und Vorher/Nachher. Keine öffentliche REST-Ausgabe.
- Vereinsinfos: eigener interner Inhaltstyp; Kalender zeigt bevorstehende freigegebene Geburtstage und Termine/Jahrestage. Öffentliche News bleiben davon getrennt.

## Bewusst noch offen

- Tatsächlicher MeinVerein-Beitrittslink, Originalbilder, freigegebene Mitgliederzitate und Beiratsvorstellungen. Bis dahin ehrliche Bild-/Textplätze und Kontaktweg, keine erfundenen Inhalte.
- Geschützte Dokumentenablage und deren Verwaltungsablauf: noch kein produktiver Upload-/Downloadprozess; die Dokumentansicht ist bislang ein leerer Platzhalter.
- CSV-Migration einschließlich Zuordnung alter DHB-Daten, Einladungs- und Passwortsetzprozess, Versandtests. Die CSV wird laut Nutzer später geliefert.
- KI-Newsletter-Import/Freigabe/Versand ausdrücklich später. Aktuell gibt es keinen Versand.
- Weitere Portal-Mikrotexte sind im Plugin definiert; noch nicht jeder funktionale Text ist über eine redaktionelle Oberfläche änderbar.
- Altplugin-Bereinigung, Datenschutz-/Impressumsprüfung sowie echter Domainumzug inklusive HTTPS und Mailzustellung vor Echtbetrieb.

## Prüfung und Grenzen

Lokal wurde eine isolierte WordPress-Installation mit offizieller SQLite-Integration ausschließlich zum Testen verwendet; die Zielinstallation bleibt bei ihrer bestehenden Datenbank. Profil-, Sichtbarkeits-, Konflikt- und Workflowtests sowie reale Browser-Formularabläufe wurden mit synthetischen lokalen Daten geprüft. Öffentliche Seiten und vier Portalansichten wurden bei 1440 und 390 Pixeln kontrolliert; der zusätzliche Menütest bei 842 Pixeln führte zur Header-Korrektur 0.5.1.

Auf der Entwicklungsdomain laden alle acht öffentlichen Ziele. Anonyme Aufrufe des Verzeichnisses liefern nur die Anmeldung, die Protokollseite leitet zur Anmeldung; geschützte Antworten verwenden `no-store, private`. Der angemeldete Portalstart wurde auf der Zielinstallation visuell geprüft. Vollständige Rollen-/Schreibtests auf der Strato-Datenbank, Versand und echte Datenmigration stehen noch aus.

Die Benutzerfreigabe erlaubt Änderungen an dieser Entwicklungsinstallation ohne Altbackup. Der geprüfte MVP bleibt als Referenz unter `preview/` erhalten. Keine Änderungen am Tippspiel oder an MeinVerein.
