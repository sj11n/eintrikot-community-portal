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

**Datenschutz bei der Zustimmung (0.19.1; Empfehlungen, die der Datenschutzbeauftragte laut Vorstand mitträgt):**

- Der Schlüssel im Link ist nur als Hash gespeichert, gilt 30 Tage und wird mit der erteilten Zustimmung gelöscht (einmalig verwendbar).
- Der Schlüssel steht in der Adresse, weil er per Mail kommt. Alle Portalseiten senden deshalb `Referrer-Policy: no-referrer` und `noindex`: Der Schlüssel gelangt nicht in Logs oder Verweise anderer Seiten.
- Fehlermeldungen stehen nicht mehr in der Adresse, sondern kommen aus einer festen Liste (`consent_errors()`). Ein präparierter Link kann dort keinen eigenen Text einblenden.
- Nachweis: Name, Zeitpunkt, bestätigter Text, Sorgerechts-Angabe. Die Adresse des Elternteils wird vom jungen Mitglied selbst eingetragen; der Nachweis ist damit eine „angemessene Anstrengung“ im Sinne von Art. 8 Abs. 2 DSGVO (Bestätigung über ein Postfach), keine Identitätsprüfung. Wer mehr will, nutzt „Zustimmung von Hand eintragen“ (Papier mit Unterschrift).
- Geprüft durch `tests/run.php`: Altersgrenze am 18. Geburtstag, Jugendregeln (nur Team, Altersklasse, Region), Verzeichnis nur mit Zustimmung und Freigabe der Eltern, Jugendliche zählen nicht in die öffentlichen Kennzahlen.

Bis zur Zustimmung ist keine Anmeldung möglich und das Konto erscheint nirgends. Gespeichert werden Name, E-Mail, Zeitpunkt und der bestätigte Text; gelöscht wird der Nachweis drei Jahre nach dem 18. Geburtstag. Das Geburtsdatum können Minderjährige nicht selbst ändern.

## Aufnahme

1. In MeinVerein die neuen Mitglieder filtern und als Excel exportieren (am besten nur Vorname, Nachname, E-Mail, Mitgliedsnummer, Eintrittsdatum, Geburtsdatum und Jahresspende, bei uns „Individuelles Feld 1“).
2. Portal → Verwaltung → Neue Mitglieder aufnehmen → Datei hochladen. Erkannt werden gängige Spaltennamen (auch die der MeinVerein-Datei: „Mitgliedsnr.“, „Geburtstag“, „Mitglied seit“, „Zusatzbetrag NDAlumni“ als Jahresspende); sonst „Spalten zuordnen“. Gelesen werden nur diese Spalten; IBAN, Anschrift, Telefon und alles andere in der Datei bleiben unberührt und werden nicht gespeichert.
3. Vorschau prüfen: Neu / Schon im Portal / Fehler. Ein Eintrittsdatum vor der Gründung (23.09.2025) gilt als Fehler und wird in MeinVerein korrigiert. Ausgewählte übernehmen.
4. Das Portal legt Konten mit Rolle „EINTRIKOT Mitglied“ an (Benutzername = E-Mail) und schickt auf Wunsch sofort die Begrüßung: Schreiben, Mitgliedsurkunde als PDF, persönlicher Link „Passwort festlegen“.
5. Unter „Einladungen“ ist der Stand je Mitglied sichtbar: noch nicht eingeladen, eingeladen, Link abgelaufen, aktiv.

Grundsätze: MeinVerein bleibt führend. Übernommen werden nur Name, E-Mail, Mitgliedsnummer, Eintrittsdatum, Geburtsdatum und Jahresspende; die hochgeladene Datei wird nicht gespeichert (die Felder liegen 30 Minuten für die Vorschau bereit). Es wird nie ein Passwort verschickt. Der Link ist einmalig und 14 Tage gültig; das Passwort braucht mindestens 10 Zeichen. Bestandsmitglieder können ohne Urkunde eingeladen werden.

## Umstieg der Bestandsmitglieder von NDAlumni (0.17)

Für die rund 200 bisherigen Mitglieder gibt es keinen neuen Beitritt. Voreingestellt ist im Import deshalb „Bestandsmitglieder“ (`IMPORT_DEFAULT` in `onboarding.php`); sobald neue Mitglieder den vollen Ablauf mit Urkunde bekommen sollen, wird das auf „now“ umgestellt.

1. **Eintrittsdaten klären:** Gründungsmitglieder (Nr. 1–7) auf den 23.09.2025; niemand vor der Gründung; kein Eintritt nach dem Datum des SEPA-Mandats; kein Mandat mit Datum vor der Gründung.
2. **Eine Datei, ein Upload:** Die Master-Datei (ohne Bankspalten) mit den Blättern „Export WisoMV“ und „Import_Roh“ unter „Export aus MeinVerein hochladen“ hochladen, Modus „Bestandsmitglieder“, übernehmen. Das Portal legt die Konten aus dem ersten Blatt an und ergänzt die Profile aus dem NDAlumni-Blatt. Es geht keine Mail raus. Einzeln geht es auch: MeinVerein-Export importieren und danach den NDAlumni-Export im Abschnitt „Profile aus NDAlumni übernehmen“.
3. **Was aus NDAlumni kommt:** Zuordnung über die E-Mail-Adresse. Es werden nur leere Felder gefüllt, alles privat. Übernommen: Wohnort, Land (als Region, außer Deutschland), Verein, Team, Altersklasse, Trikotphase, Länderspiele (auch als Text), Sport, Beruf, Unternehmen, Branche, Status (Rentner, Student, Sonstiges), erste Ausbildung, DHB-Stationen mit Position, Mentoring-Angebote und -Wünsche, Interessen und Mitmachen als Text, LinkedIn/Facebook/Instagram (nur gültige Adressen) und die Jahresspende, wo aus MeinVerein keine kam. Nicht übernommen: Bank- und Mandatsdaten, Anschrift, Telefon, Geburtsname, Notizen, Tarif, Newsletter (Opt-in neu im Portal), Profilbilder (nicht im Export).
4. **Einladen erst nach dem Umzug:** Einladungen, Umstiegsmails und Eltern-Mails sind gesperrt, solange das Portal nicht auf eintrikot.de mit HTTPS läuft. Danach unter „Einladungen“ (jeweils bis zu 25): Umstiegsmail mit Zugangslink und der Bitte, das Profil zu prüfen. Vorschau über Community-Aufbau → Aufnahme & Urkunde → „Umstiegsmail für Bestandsmitglieder“.
5. Nach dem Festlegen des Passworts landet das Mitglied im Profil mit dem Hinweis „Bitte prüfe dein Profil“. Mit dem ersten Speichern gilt die Prüfung als erledigt (Eintrag im Änderungsprotokoll).

## Beitrag und Jahresspende im Profil (0.17)

Im Kasten „Mitgliedschaft“ sehen Mitglied und Verwaltung: Mitgliedsnummer, Eintritt, Jahresbeitrag (50 € ab 32, bis einschließlich 31 beitragsfrei; ohne Geburtsdatum mit diesem Hinweis) und die freiwillige Jahresspende laut MeinVerein. Das Mitglied kommt von dort zu „Jahresspende ändern“ und „Bankverbindung ändern“. Wird eine Spenden-Anfrage als „In MeinVerein übernommen“ abgeschlossen, übernimmt das Profil den neuen Betrag automatisch. Die Verwaltung kann Nummer, Eintritt und Spende im Abschnitt Verwaltung korrigieren; alles wird protokolliert.

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

## Geburtstag im Profil (0.19.2)

Das Geburtsdatum kommt aus MeinVerein bzw. dem Antrag und ist zunächst **privat**: Es dient dem Beitrag und der Altersprüfung, sehen kann es nur die Vereinsverwaltung. Im Profil unter „Über dich“ wählt das Mitglied selbst, wer den Geburtstag sonst noch sieht:

- **Niemand** (Standard, auch nach dem Import),
- **Tag und Monat** (Profil und „Termine“, ohne Jahr),
- **Tag, Monat und Jahr** (Profil und „Termine“ mit „wird 40“; das Alter lässt sich daraus ablesen).

„Mein Alter im Mitgliederprofil anzeigen“ bleibt eine eigene Wahl. Die Vereinsverwaltung sieht die Auswahl beim Bearbeiten, ändert sie aber nie (Einwilligung). Mitglieder unter 18 zeigen keinen Geburtstag, auch wenn sie ihn früher gewählt haben; ab 18 gilt ihre Wahl. Konten, die aus dem Verzeichnis ausgeblendet sind, erscheinen auch nicht unter „Termine“. Das frühere Häkchen „in den Terminen, ohne Jahr“ wird als „Tag und Monat“ gelesen und beim nächsten Speichern durch die neue Wahl ersetzt.

## Import aus Excel: Zahlen, Tore, weitere Ausbildung (0.20.1)

- **Jahre aus Excel (0.20.2):** „2003.0“ galt als Excel-Tageszahl und wurde zum Jahr 1905; 164 von 182 DHB-Vita-Stationen der Master-Tabelle wären so falsch gewesen. Jahre mit „.0“ werden jetzt als Jahre gelesen.
- **Zahlen aus Excel:** Excel und Google Tabellen liefern ganze Zahlen als „3.0“. Der Import las daraus früher „30“ (Mitgliedsnummer 3 wäre als 30 angelegt worden). Jetzt gilt „3.0“ als 3; das gilt für Mitgliedsnummer, Länderspiele und Tore.
- **Tore:** neues Profilfeld im Hockey-Abschnitt, aus „Anzahl von Toren“.
- **Weitere Ausbildungen:** Das Profil hat einen strukturierten Eintrag. Weitere Einträge aus „Akademische Daten 2–5“ kommen als eine Zeile „Weitere Ausbildung: …“ unter „Mehr dazu“.
- **Gekündigt oder verstorben** (Spalten „Gekündigt am/zum“, „Gestorben am“): werden nicht übernommen.
- **Team:** Steht in „Mannschaft“ nichts oder nur „Staff“, bleibt das Team **leer** (seit 0.20.4 wird es nicht mehr aus der Anrede geraten). Mitglieder ergänzen es selbst. Wer es vorab eintragen will, nutzt die Spalte „Team“ im Blatt „Team-Zuordnung“ oder „Damen“/„Herren“ in der Spalte „Mannschaft“.

## Bereinigte Importdatei erzeugen (0.20.3)

Aus der Master-Tabelle (Download als Excel) entsteht mit `tools/importdatei.php` eine Datei nur mit den Spalten, die das Portal braucht. Sie enthält **keine** IBAN, kein Mandat, keine Anschrift, kein Telefon, keinen Geburtsnamen, keine Notizen, keinen Tarif und keine Beiträge.

```bash
tools/lokal-test.sh setup     # einmalig: lokales WordPress
WP_ROOT=.lokal/wordpress php tools/importdatei.php "<Master>.xlsx" [--eintritt=<CSV>]
```

Ergebnis in `~/Downloads/Portal-Import/` (nie im Repository): `Portal-Import.xlsx` (Blätter „Export WisoMV“ und „Import_Roh“) und `Pruefbericht.txt` mit Zahlen und offenen Punkten, ohne Namen. Die Datei lässt sich beliebig oft neu erzeugen. Das Skript liest sie am Ende so zurück, wie es das Portal beim Upload tut.

**Korrekturen kommen aus der Master:**
- Blatt **Team-Zuordnung**: Spalte „Team“ (Damen, Herren oder beides) und „Altersklasse“. „Beides“ lässt das Team im Profil leer.
- Blatt **Stationen Staff**: Korrekturen für Rolle, Damen/Herren und Altersklasse einer Station. Steht eine Station in zwei Zeilen (z. B. dieselbe Aufgabe für Damen und Herren), wird die zweite Zeile eine zusätzliche Station. Bis zu zehn Stationen sind möglich.
- Wo nichts eingetragen ist, gilt die Ableitung des Importers: Rolle aus dem Positionstext und Damen/Herren aus Text oder Anrede bei den Stationen. Das Team im Profilkopf wird nicht geraten.
- **Ohne Geburtsdatum** (Pflichtangabe): Der Bericht listet die Nummern. Diese Mitglieder werden separat eingeladen, sobald das Datum vorliegt.
- **Eintrittsdaten** stehen in der Master. Mit `--eintritt=<CSV>` lassen sich Korrekturen einsetzen, bevor sie in der Master stehen.

Nicht übernommen werden Personen mit „Gekündigt am/zum“ oder „Gestorben am“ sowie NDAlumni-Konten ohne MeinVerein-Eintrag.
