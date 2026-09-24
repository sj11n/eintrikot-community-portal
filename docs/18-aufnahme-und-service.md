# Aufnahme neuer Mitglieder und Service-Bearbeitung (0.10)

Ausführliche Anleitung für Vorstand und Mitglieder: Claude-Dokument „EINTRIKOT Portal – Handbuch Aufnahme und Service“.

## Aufnahme

1. In MeinVerein die neuen Mitglieder filtern und als Excel exportieren (am besten nur Vorname, Nachname, E-Mail, Mitgliedsnummer, Eintrittsdatum).
2. Portal → Verwaltung → Neue Mitglieder aufnehmen → Datei hochladen. Erkannt werden gängige Spaltennamen; sonst „Spalten zuordnen“.
3. Vorschau prüfen: Neu / Schon im Portal / Fehler. Ausgewählte übernehmen.
4. Das Portal legt Konten mit Rolle „EINTRIKOT Mitglied“ an (Benutzername = E-Mail) und schickt auf Wunsch sofort die Begrüßung: Schreiben, Mitgliedsurkunde als PDF, persönlicher Link „Passwort festlegen“.
5. Unter „Einladungen“ ist der Stand je Mitglied sichtbar: noch nicht eingeladen, eingeladen, Link abgelaufen, aktiv.

Grundsätze: MeinVerein bleibt führend. Nur fünf Felder werden übernommen; die hochgeladene Datei wird nicht gespeichert (die fünf Felder liegen 30 Minuten für die Vorschau bereit). Es wird nie ein Passwort verschickt. Der Link ist einmalig und 14 Tage gültig; das Passwort braucht mindestens 10 Zeichen. Bestandsmitglieder können ohne Urkunde eingeladen werden.

## Urkunde

Die Vorlage (A4, ohne Name, Nummer, Datum, mit Unterschriften) wird unter Community-Aufbau → Aufnahme & Urkunde hochgeladen und nur in der Datenbank gespeichert – nie im Repository, da es öffentlich ist. Das Portal setzt Name, Mitgliedsnummer (vierstellig) und Eintrittsdatum an die Positionen der bisherigen Canva-Urkunde. Mitglieder laden ihre Urkunde unter Service → Unterlagen herunter.

## Voraussetzungen vor dem Einsatz

- SMTP über das STRATO-Postfach (z. B. WP Mail SMTP), danach Test-E-Mail unter Aufnahme & Urkunde.
- HTTPS: Einladungen an alle Mitglieder erst auf eintrikot.de mit HTTPS verschicken.

## Service-Bearbeitung

Statt eines Status-Menüs hat jede Anfrage Schaltflächen für den nächsten Schritt (In Prüfung nehmen, Erledigt bzw. In MeinVerein übernommen, Ablehnen, Wieder öffnen, Nur Texte speichern), eine Aufgabenbeschreibung je Anfrageart und die strukturierten Angaben (Adresse, Betrag, Beginn). Die Liste startet mit „Offen“ und zeigt Zahlen je Status.

## Anmeldung und Passwort (0.11)

- **Angemeldet bleiben:** Haken ist vorausgewählt. Mit Haken 90 Tage (Vorstand, Redaktion, Admins: 14 Tage), ohne Haken bis der Browser geschlossen wird.
- **Auf allen anderen Geräten abmelden:** Service → Konto.
- **Passwort vergessen:** einmaliger Link per EINTRIKOT-Mail, 24 Stunden gültig. Die Seite sagt immer „Wenn diese Adresse bei uns hinterlegt ist …“, auch bei unbekannten Adressen. Höchstens 3 Anfragen pro Stunde und Konto. Nach dem Festlegen werden alle Sitzungen beendet und das Mitglied bekommt eine Bestätigungsmail. Eine erfolgreiche Anmeldung macht offene Links ungültig (WordPress-Standard).
- **Passwort-Raten:** 5 Fehlversuche je Konto und Anschluss → 15 Minuten gesperrt; eine gemeinsame Fehlermeldung für falsche E-Mail und falsches Passwort.
- **Härtung:** XML-RPC aus, Benutzerliste der REST-Schnittstelle und Autorenseiten für Gäste gesperrt, keine Benutzer-Sitemap.
- **Mit HTTPS (eintrikot.de):** WordPress setzt die Anmelde-Cookies dann automatisch als „secure“; zusätzlich HTTPS-Weiterleitung einschalten. Zweite Stufe (Authenticator-App) für Vorstand und Admins ist vorbereitet als Empfehlung, aber bewusst noch nicht eingerichtet.
