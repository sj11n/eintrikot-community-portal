# Aufnahme, Service-Bearbeitung und Anmeldung

Ausführliche Anleitung für Vorstand und Mitglieder: Claude-Dokument „EINTRIKOT Portal – Handbuch Aufnahme und Service“.

## Mitglied werden (0.14)

Der Antrag selbst läuft über den digitalen Mitgliedsantrag von WISO MeinVerein (Link unter Community-Aufbau → Beitritt). Vor dem Absprung öffnet „Jetzt Mitglied werden“ ein Hinweisfenster: neues Fenster bei MeinVerein, benötigt werden nur Name, E-Mail und Geburtsdatum (alles Weitere pflegt das Mitglied später in der App), Hinweis auf die Zustimmung der Eltern unter 18, IBAN-Abfrage auch bei „Rechnung“ („Überspringen“ wählen), Tipps zum versteckten Knopf auf manchen Handys (Safari statt Chrome auf dem iPhone, Computer), danach Prüfung durch den Vorstand und Zugangsdaten zur App. Die Urkunde wird bewusst nicht angekündigt. MeinVerein öffnet sich in einem neuen Tab; ohne JavaScript führt der Knopf direkt zum Antrag.

Unter Community-Aufbau → Beitritt steht pro Monat, wie oft das Hinweisfenster geöffnet und wie oft „weiter zum Antrag“ gewählt wurde. Anonym, ohne Cookies; wiederholte Klicks derselben Verbindung innerhalb einer Stunde zählen einmal.

## Minderjährige (0.15)

Die Satzung regelt Minderjährige nicht; ein Beitritt unter 18 wird erst mit Zustimmung der Eltern wirksam. Empfehlung für die nächste Mitgliederversammlung: „Minderjährige werden mit Zustimmung ihrer gesetzlichen Vertreter in Textform aufgenommen.“

1. Der Import übernimmt das Geburtsdatum. Unter 18 zeigt die Vorschau „unter 18“.
2. „Einladen“ schickt dem jungen Mitglied statt der Begrüßung die Bitte, die E-Mail-Adresse eines Elternteils einzutragen (zweimal, mit Vorschlag bei Vertippern wie „gmial.com“; die eigene Adresse wird nicht angenommen).
3. Der Elternteil bekommt eine Mail (Wer wir sind, Nutzen, beitragsfrei bis 31, Finanzierung über Beiträge ab 32 und Spenden, gemeinnützig, Datenschutz) und bestätigt auf einer Portalseite: Name, Sorgerecht (gemeinsam im Einverständnis / allein), Zustimmung, freiwillig Sichtbarkeit im Verzeichnis.
4. Danach gehen automatisch Bestätigung an die Eltern und Begrüßung mit Urkunde und Zugang an das Mitglied.
5. Links gelten 30 Tage. Nach 7 Tagen einmal Erinnerung. Unter „Einladungen“ steht der Stand („Wartet auf Eltern-Adresse“, „Wartet auf Zustimmung der Eltern“, nach 30 Tagen „bitte nachfassen“). Eine Zustimmung auf Papier trägt der Vorstand mit „Zustimmung von Hand eintragen“ ein.

Bis zur Zustimmung ist keine Anmeldung möglich und das Konto erscheint nirgends. Gespeichert werden Name, E-Mail, Zeitpunkt und der bestätigte Text; gelöscht wird der Nachweis drei Jahre nach dem 18. Geburtstag. Das Geburtsdatum können Minderjährige nicht selbst ändern.

## Aufnahme

1. In MeinVerein die neuen Mitglieder filtern und als Excel exportieren (am besten nur Vorname, Nachname, E-Mail, Mitgliedsnummer, Eintrittsdatum).
2. Portal → Verwaltung → Neue Mitglieder aufnehmen → Datei hochladen. Erkannt werden gängige Spaltennamen; sonst „Spalten zuordnen“.
3. Vorschau prüfen: Neu / Schon im Portal / Fehler. Ausgewählte übernehmen.
4. Das Portal legt Konten mit Rolle „EINTRIKOT Mitglied“ an (Benutzername = E-Mail) und schickt auf Wunsch sofort die Begrüßung: Schreiben, Mitgliedsurkunde als PDF, persönlicher Link „Passwort festlegen“.
5. Unter „Einladungen“ ist der Stand je Mitglied sichtbar: noch nicht eingeladen, eingeladen, Link abgelaufen, aktiv.

Grundsätze: MeinVerein bleibt führend. Nur sechs Felder werden übernommen (mit Geburtsdatum); die hochgeladene Datei wird nicht gespeichert (die Felder liegen 30 Minuten für die Vorschau bereit). Es wird nie ein Passwort verschickt. Der Link ist einmalig und 14 Tage gültig; das Passwort braucht mindestens 10 Zeichen. Bestandsmitglieder können ohne Urkunde eingeladen werden.

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
