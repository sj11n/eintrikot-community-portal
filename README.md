# EINTRIKOT Community-Portal

Öffentliche Website und geschütztes Mitgliederportal für EINTRIKOT e. V. Eigenes WordPress-Theme und Plugin auf STRATO-Shared-Hosting, derzeit auf http://eintrikot.myemmel.com, später auf eintrikot.de.

## Dokumentation

| Dokument | Inhalt |
|---|---|
| [01 Originalbriefing](docs/01-originalbriefing.txt) | Ausgangsauftrag |
| [02 Entscheidungen](docs/02-entscheidungen.md) | verbindliche Festlegungen zu Marke, Website, Portal und Arbeitsweise |
| [03 Aufbau und Pflege](docs/03-aufbau-und-pflege.md) | Aufbau von Theme und Plugin, Pflege im Backend, offene Punkte |
| [04 Altplugins bereinigen](docs/04-altplugins-bereinigung.md) | Checkliste für das WordPress-Backend |
| [05 Einspielen](docs/05-einspielen.md) | GitHub Action „Einspielen" |
| [06 Umzug auf eintrikot.de](docs/06-umzug-eintrikot-de.md) | Ablauf am Umzugstag |
| [07 Aufnahme, Service, Anmeldung](docs/07-aufnahme-service-anmeldung.md) | neue Mitglieder, Urkunde, Service-Bearbeitung, Passwort |
| [08 Aktuelles, Termine, Anmeldung](docs/08-aktuelles-termine.md) | Bereich Aktuelles, Termine anlegen, Anmeldung und Teilnehmerliste |

## Ablauf einer Änderung

1. Änderung auf einem eigenen Branch, lokal mit WordPress getestet.
2. Pull Request; „Prüfen" muss grün sein. Danach Merge nach `main`.
3. Nach dem Merge spielt **Einspielen** automatisch ein, wenn Plugin, Theme oder das Einspiel-Werkzeug betroffen sind. Von Hand geht es unter Actions → Einspielen → Run workflow (Probelauf ohne Haken).

## Abgrenzung

- MeinVerein bleibt verbindlich für Mitgliedschaft, Beiträge, SEPA und Verwaltung. Portal-Anfragen werden von Hand übernommen.
- Tippspiel und bisherige App sind andere Projekte.
- Keine Zugangsdaten, Mitgliederdaten, Datenbankexporte oder Urkunden-Vorlage im Repository. Das Repository ist öffentlich lesbar.
