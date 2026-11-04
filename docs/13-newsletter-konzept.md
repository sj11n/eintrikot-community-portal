# Konzept: Newsletter als eigenes Plugin

Stand: 04.11.2026. Status: **gebaut** (Einwilligung im Core ab 0.37.0, Plugin `eintrikot-newsletter` 0.1.0, Bedienung in `docs/14`). B hat das eigene Plugin, die Absenderadresse und das Design am 04.11.2026 freigegeben. Offen bleiben die Prüfpunkte für V (Vorlage zum Abhaken: `docs/15`) und der Versandweg (Schritt 5, Checkliste in `docs/14`). Das Briefing (`docs/01`) nennt MailPoet. Das kostenlose MailPoet ist auf wenige hundert Empfänger und Monatsmengen begrenzt und setzt sein Logo in die Mail. Deshalb wird stattdessen ein kleines eigenes Plugin vorgeschlagen. Die Abweichung vom Briefing braucht die Freigabe von B und V.

**Wer macht was:** B = Björn, V = Vorstand, R = Redaktion, C = Claude.

## Ziel und Rahmen

- Etwa vier Ausgaben im Jahr an etwa 200 bis 300 Empfänger.
- Einfach zu bedienen, nachvollziehbar, nichts geht ohne menschliche Freigabe raus.
- Versand über das Google-Workspace-Konto des Vereins (SMTP). Das Tageslimit von Google liegt weit über unserer Menge.
- Newsletter-Abonnement und Mitgliedschaft bleiben getrennt: Wer nicht zustimmt, bleibt Mitglied und bekommt keinen Newsletter.
- Vereinsmitteilungen (Einladung zur Mitgliederversammlung u. Ä.) sind **kein** Newsletter und laufen weiter über den E-Mail-Verteiler in der Verwaltung.

## Abgrenzung (nicht Teil des Plugins)

- Themensammlung und Redaktionsübersicht bleiben in Google Workspace: Gmail-Label `Newsletter/Eingang`, Google Form „Thema für EINTRIKOT vorschlagen“, Google Sheet „Redaktionsübersicht“ (Quelle, Datum, Link, Bildstatus, Relevanz, Status, Freigabe), Drive für Bilder und Texte.
- Kein Layout-Baukasten, keine Segmente, keine Statistik über Öffnungen (keine Tracking-Pixel).
- Keine Bank-, Beitrags- oder Mitgliederdaten im Newsletter-Plugin.

## Bausteine

### 1. Einwilligung im Core (kommt zuerst, unabhängig vom Plugin)

Heute ist „EINTRIKOT-Newsletter erhalten“ ein Häkchen im Profil (`newsletter`, Standard aus). Neu:

- Drei Zustände: **nicht beantwortet**, **Ja**, **Nein**.
- Gespeichert werden Zustand, Zeitpunkt und die Fassung des Einwilligungstextes (Versionsnummer).
- **Nicht beantwortet:** Das Portal zeigt die Frage („Möchtest du den EINTRIKOT-Newsletter per E-Mail bekommen?“) beim ersten Login und danach bei jedem Login, bis es eine Antwort gibt. Es ist ein ruhiger Hinweis oben im Portal, kein Fenster, das den Zugang blockiert. Neben „Ja“ und „Nein“ gibt es „Später“: Das blendet die Frage 14 Tage aus, danach kommt sie wieder.
- **Nein:** Nach 12 Monaten wird einmal erneut gefragt, nur im Portal, nie per Mail, und danach wieder frühestens nach 12 Monaten. Ein „Nein“ lässt sich im Profil jederzeit auf „Ja“ stellen. Die Wiedervorlage nach einem „Nein“ ist ein **Prüfpunkt für V** (Datenschutz, zusammen mit dem Einwilligungstext).
- **Ja:** Es wird nicht mehr gefragt.
- Die Zeiträume (14 Tage, 12 Monate) sind Einstellungen und lassen sich ohne Umbau ändern.
- Nur **Ja** zählt als Einwilligung. „Nicht beantwortet“ und „Nein“ bekommen nichts.
- Im Profil lässt sich die Entscheidung jederzeit ändern. Jede Änderung geht mit Zeitpunkt in den Audit-Log (`log_change`).
- Der Einwilligungstext ist ein **offener Prüfpunkt für V** (rechtlicher Text, wird nicht von Claude erfunden). Vorschlag zum Prüfen: „Ich möchte den EINTRIKOT-Newsletter ungefähr viermal im Jahr per E-Mail erhalten. Ich kann das jederzeit im Profil oder über den Abmeldelink in jeder Mail widerrufen.“
- Bestandsmitglieder aus dem Import haben „nicht beantwortet“. Niemand wird automatisch angemeldet.

### 2. Plugin `eintrikot-newsletter`

Eigenes Plugin neben `eintrikot-community-core`, mit eigener Versionsnummer und eigenen Tests. Es nutzt vom Core nur die Rollen, `log_change` und eine Funktion `newsletter_recipients()`, die die Empfänger mit Einwilligung „Ja“ liefert (gleiche Filter wie der E-Mail-Verteiler: gültige Adresse, keine Beispieladressen, ohne Duplikate).

**Datenmodell**

- Beitragstyp `et_newsletter` (eine Ausgabe): Betreff, Vorschautext (Preheader), Inhalt, Status (`Entwurf`, `Bereit`, `Freigegeben`, `Wird versendet`, `Versendet`, `Abgebrochen`), Freigabe (wer, wann).
- Tabelle `eintrikot_newsletter_send`: Ausgabe, Empfänger (Nutzer-ID), Status (`wartet`, `gesendet`, `Fehler`), Zeitpunkt, Fehlertext. Kein Inhalt, keine Adresse als Klartext doppelt gespeichert.
- Der Inhalt einer versendeten Ausgabe wird eingefroren (Kopie zum Zeitpunkt der Freigabe), damit das Archiv und der Versand übereinstimmen.

**Rollen**

| Aktion                            | Redaktion | Vorstand |
| --------------------------------- | --------- | -------- |
| Ausgabe schreiben und bearbeiten  | ja        | ja       |
| Testversand                       | ja        | ja       |
| Status „Bereit“ setzen            | ja        | ja       |
| **Freigeben und Versand starten** | nein      | **ja**   |
| Versand abbrechen                 | nein      | ja       |

Der Versand startet nur, wenn der Freigebende nicht der Letzte ist, der den Inhalt geändert hat (Vier-Augen-Prinzip). Jede Änderung nach der Freigabe setzt den Status zurück auf „Bereit“.

**Ablauf einer Ausgabe**

1. R legt eine Ausgabe an (Portal: Service → Newsletter, Bearbeitung im WordPress-Editor mit einem festen, eingeschränkten Satz an Blöcken: Überschrift, Absatz, Bild, Button, Trennlinie).
2. Vorschau im Mail-Layout und **Testversand** an frei wählbare Adressen (nur Vorstand und Redaktion, nie an Mitglieder). Testmails tragen im Betreff „[TEST]“.
3. R setzt „Bereit“. Es erscheint die **Prüfliste** (Fakten, Links, Bildrechte, Abmeldelink vorhanden, Betreff, Vorschautext). Alle Punkte müssen abgehakt sein.
4. V gibt frei. Der Bildschirm zeigt vorher die **Empfängerzahl** („Geht an 142 Mitglieder mit Einwilligung“) und verlangt eine Bestätigung.
5. **Versand in Paketen:** etwa 20 Mails pro Minute, jede Mail einzeln an eine Person. Fehlgeschlagene Mails werden bis zu dreimal wiederholt, danach als „Fehler“ markiert. Bricht der Versand ab oder stoppt der Server, läuft er beim nächsten Durchlauf **ohne Doppelversand** weiter (Status je Empfänger).
6. Nach dem Versand erscheint die Ausgabe im **Archiv** für Mitglieder (Portal, Service → Newsletter). Der Audit-Log hält Freigabe, Zeitpunkt und Zahl der Empfänger fest.

**Abmeldung**

- Jede Mail enthält einen **persönlichen, signierten Link** (HMAC mit Geheimnis aus den WordPress-Schlüsseln, enthält Nutzer-ID und Ausgabe, kein Klartext-Passwort). Der Link funktioniert ohne Login und setzt die Einwilligung auf „Nein“, mit Bestätigungsseite im Portal-Design.
- Zusätzlich die Kopfzeilen `List-Unsubscribe` und `List-Unsubscribe-Post: List-Unsubscribe=One-Click`, die Gmail und Outlook erwarten. Der One-Click-Aufruf akzeptiert nur `POST` mit gültiger Signatur.
- Die Abmeldung greift sofort. Läuft gerade ein Versand, wird die Einwilligung vor **jeder einzelnen Mail** erneut geprüft.

**Layout**

- Eine feste HTML-Vorlage im EINTRIKOT-Design (Logo, Farben und Schriften laut Brandbook), kompatibel mit gängigen Mailprogrammen (Tabellen-Layout, Fließtext in Systemschrift als Rückfall, Bilder mit Alternativtext, Textfassung mitgeliefert).
- Fußzeile: Vereinsname, Anschrift und vertretungsberechtigte Person laut Impressum (Daten kommen aus dem Impressum der Seite), Hinweis zur Abmeldung, Link zur Datenschutzerklärung. Wortlaut zur Prüfung durch V.
- **Designfreigabe:** Das Mail-Layout wird vor dem Bau als Entwurf vorgelegt und von B freigegeben (Projektregel).

**Versandweg**

- WordPress sendet über `wp_mail`. Ein SMTP-Plugin (z. B. WP Mail SMTP) oder eine kleine eigene Anbindung verbindet mit Google Workspace (Konto des Vereins, App-Passwort oder OAuth, **kein** persönliches Passwort).
- Absender: eine feste Adresse auf `eintrikot.de` (Vorschlag: `newsletter@eintrikot.de` als Alias in Google Workspace, Antworten gehen an `info@eintrikot.de`). Das ist die Entscheidung von B.
- SPF, DKIM und DMARC der Domain müssen stimmen (siehe `docs/12`). Vor der ersten echten Ausgabe: Testmails an Gmail, Outlook, web.de und GMX, Spam-Ordner prüfen.
- Die Zugangsdaten für den Versand liegen in der Konfiguration des Servers, nicht im Repository.

**Warteschlange**

- Der Versand läuft über WordPress-Cron. Da WP-Cron nur bei Aufrufen läuft, richtet B zusätzlich einen echten Cronjob im STRATO-Kundenbereich ein (ein Aufruf von `wp-cron.php` pro Minute), sonst kommt der Versand zu langsam voran.
- Auf der Newsletter-Seite steht der Fortschritt („80 von 142 gesendet, 2 Fehler“).

**Fehlerhafte Adressen**

- Mails, die nicht zugestellt werden können, kommen als Rückläufer im Postfach des Absenders an. Das prüft R nach jedem Versand (Checkpunkt auf der Prüfliste). Kaputte Adressen korrigiert R im Profil oder lässt sie vom Mitglied korrigieren. Eine automatische Rückläufer-Auswertung ist nicht vorgesehen.

### 3. Redaktionshilfen (Google Workspace, ohne Code)

- Google Form, Sheet, Label und Drive-Ordner nach dem Muster aus dem Briefing. C legt auf Wunsch die Vorlagen und die Spalten an, B richtet sie in Google ein.
- Optional später: Ein KI-gestützter Entwurf aus freigegebenen Themen (Betreff, Vorschautext, Teasertexte). Er erscheint als **Entwurf** im Plugin und durchläuft denselben Ablauf. Es wird nichts automatisch veröffentlicht oder versendet.

## Datenschutz

- Gespeichert werden nur: Einwilligungszustand mit Zeitpunkt und Textversion, Versandstatus je Empfänger. Keine Öffnungs- oder Klickverfolgung.
- Versandprotokolle werden nach 12 Monaten gelöscht (Wert zur Prüfung durch V).
- Wird ein Konto gelöscht, entfallen Einwilligung und Versandstatus mit dem Konto (Ergänzung der Löschcheckliste in `docs/03`).
- Die Datenschutzerklärung (`patterns/mvp-datenschutz.php`) nennt den Newsletter, den Versand über Google Workspace und das Widerrufsrecht. Der Text ist ein **offener Prüfpunkt für V**.
- Ob Bestandsmitglieder ohne Einwilligung Vereinsinfos per Mail erhalten dürfen, ist davon getrennt und eine Entscheidung des Vorstands.

## Tests (vor dem Einspielen)

- Empfängerliste enthält nur „Ja“, keine Beispieladressen, keine Duplikate.
- Versand ohne Freigabe ist nicht möglich. Rolle „Redaktion“ kann nicht freigeben. Freigebender ist nicht der letzte Bearbeiter.
- Änderung nach Freigabe setzt zurück auf „Bereit“.
- Abbruch und Wiederaufnahme versendet niemanden doppelt.
- Abmeldelink: gültige Signatur setzt auf „Nein“, manipulierter Link und falsche Methode werden abgelehnt, Abmeldung während des Versands greift.
- Jede Mail enthält Abmeldelink und `List-Unsubscribe`-Kopfzeilen.
- Audit-Log enthält Freigabe, Versandstart und Abmeldungen.
- Die üblichen Prüfungen: `phpcs`, `phpstan`, Prettier, `tests/run.php`.

## Reihenfolge

| Schritt                                                                    | Wer  | Voraussetzung                          |
| -------------------------------------------------------------------------- | ---- | -------------------------------------- |
| 1. Konzept prüfen, Fragen unten beantworten                                | B, V | –                                      |
| 2. Einwilligung (Drei-Zustände-Opt-in, Frage beim ersten Login) im Core    | C    | Freigabe Einwilligungstext             |
| 3. Mail-Layout als Entwurf vorlegen, **Designfreigabe**                    | C, B | Brandbook, Logo                        |
| 4. Plugin bauen, Tests, auf Entwicklungsseite prüfen                       | C    | Schritte 1 bis 3                       |
| 5. Versandweg einrichten (Alias, SMTP, Cronjob, SPF/DKIM/DMARC), Testmails | B, C | Umzug auf `www.eintrikot.de` mit HTTPS |
| 6. Probelauf mit Vorstand und Redaktion als Empfängern                     | B, V | Schritt 5                              |
| 7. Erste echte Ausgabe                                                     | V    | Schritt 6                              |

Schritt 2 kann schon vor dem Domainumzug laufen, damit Einwilligungen vorliegen, wenn die Einladungen rausgehen. Echte Mails an Mitglieder erst nach Schritt 5.

## Offene Fragen

1. Eigenes Plugin statt MailPoet: B einverstanden (04.11.2026). Die Zustimmung von V steht noch aus.
2. Erledigt: Absender `newsletter@eintrikot.de` als Alias, Antworten an `info@eintrikot.de` (B, 04.11.2026). In der Portal-Übersicht für Administratoren änderbar.
3. Wortlaut der Einwilligung und der Fußzeile: prüft V, ggf. mit Datenschutzbeauftragtem.
4. Erledigt: **Inxmail** wird nicht mehr genutzt (B, 04.11.2026). Die vier Inxmail-CNAME-Einträge kommen nicht in die neue Zone. Ursprüngliche Frage: Wird Inxmail noch für Newsletter genutzt (CNAME-Einträge in der DNS-Zone, `docs/12`)? Wenn ja, wer, mit welchen Empfängern, und soll das ersetzt werden?
5. Erledigt: Versandprotokolle werden 12 Monate aufbewahrt (B, 04.11.2026).
