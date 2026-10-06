# Aktuelles, Termine und Anmeldung (0.20)

Der Bereich **Aktuelles** ersetzt die getrennten Punkte „Vereinsinfos“ und „Termine“. Er hat zwei Reiter: **Vereinsinfos** (Mitteilungen aus dem Verein) und **Termine**. Alte Adressen (`?view=infos`, `?view=events`) führen dorthin. Die untere Handyleiste hat damit vier Punkte: Start, Mitglieder, Aktuelles, Service. Die Entscheidung stammt aus dem Konzept „EINTRIKOT 2027“: erst klein, bei Bedarf erweitern.

## Termine anlegen (Redaktion und Vorstand)

Unter Servicebereich → Redaktion → „Termine pflegen“ (WordPress-Backend, „EINTRIKOT Kalender“). Der Kasten **Veranstaltung** enthält:

| Feld | Zweck |
|---|---|
| Format | Treffen, EINTRIKOT unterwegs, Mitgliederversammlung, Netzwerkabend, Online, Sonstiges |
| Datum / Bis Datum | Beginn, bei mehreren Tagen auch Ende |
| Beginn / Ende (Uhrzeit) | optional |
| Ort | z. B. „Hockeypark Mönchengladbach“ |
| Treffpunkt | optional, z. B. „30 Minuten nach dem letzten Spiel am Haupteingang“ |
| Ansprechperson | optional, z. B. die Ambassadorin oder der Ambassador |
| Mehr Informationen | optionaler https-Link |
| Anmeldung | „Mitglieder können sich im Portal anmelden“, optional mit Anmeldeschluss |
| Abgesagt | der Termin bleibt sichtbar, die Anmeldung ist geschlossen |
| Jährlich wiederholen | für Jahrestage; ohne Anmeldung |

Die Beschreibung steht im großen Textfeld. Veröffentlichte Termine sind für Mitglieder bis ein Jahr im Voraus sichtbar; Entwürfe und vergangene Termine nicht. Mehrtägige Termine bleiben bis zum letzten Tag sichtbar. Die Termine gehören nicht zu einer festen Reihe: „EINTRIKOT unterwegs“ ist nur eines von mehreren Formaten.

## Anmeldung („Wer ist dabei?“)

- Mitglieder melden sich beim Termin mit **„Für Veranstaltung anmelden“** an und mit „Abmelden“ wieder ab.
- Mit der Anmeldung steht der Name in der **Teilnehmerliste**, die andere Mitglieder im Portal sehen („Wer ist dabei? (n)“). Das steht direkt am Knopf.
- Mitglieder unter 18 und aus dem Verzeichnis ausgeblendete Konten stehen nie mit Namen in der Liste, zählen aber mit („und 2 weitere“).
- Gesperrt sind: abgesagte und vergangene Termine, Termine nach dem Anmeldeschluss, Jahrestage, Termine ohne Anmeldung.
- Die Redaktion sieht alle Angemeldeten im Kasten **Anmeldungen** des Termins (mit Hinweis „unter 18“).
- Anmeldungen werden **180 Tage nach dem Termin** gelöscht (tägliche Aufräumung, `retention.php`).
- Die Tabelle `eintrikot_event_signups` legt das Plugin beim ersten Aufruf selbst an.

## Geburtstage

Freigegebene Geburtstage stehen eingeklappt unter den Terminen („Geburtstage in den nächsten drei Monaten“), damit sie echte Veranstaltungen nicht verdrängen. Siehe docs/07.

## Jahresevent und Nicht-Mitglieder (später)

Das Jahresevent „EINMAL IM TRIKOT“ (Konzept 2027) bekommt eine **eigene Seite** mit Anmeldeformular und einer eigenen Verwaltung, weil dort auch Nicht-Mitglieder per Einladung teilnehmen sollen (Gästeliste, Einladungen, Anmeldestatus, Kosten, Plätze, Hall-of-Fame-Abstimmung). Das ist bewusst **nicht** Teil dieser Stufe. Der Termin im Portal kann später auf diese Seite verlinken (Feld „Mehr Informationen“).
