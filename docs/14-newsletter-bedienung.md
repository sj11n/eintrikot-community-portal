# Newsletter: Einrichtung und Bedienung

Stand: 04.11.2026, Plugin `eintrikot-newsletter` 0.1.0 (braucht Core ab 0.38.0). Konzept und Entscheidungen: `docs/13`.

**Wer macht was:** B = Björn, V = Vorstand, R = Redaktion, A = Administrator im WordPress-Backend.

## Einrichten (einmalig)

| Schritt                                                                                                                                                              | Wer  |
| -------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---- |
| 1. Nach dem Merge spielt **Einspielen** das Plugin mit ein. Es ist danach **nicht aktiv**: Backend → Plugins → „EINTRIKOT Newsletter“ → Aktivieren.                  | A    |
| 2. Versandweg: SMTP-Plugin mit dem Google-Workspace-Konto des Vereins (App-Passwort oder OAuth, kein persönliches Passwort). Alias `newsletter@eintrikot.de` anlegen. | B    |
| 3. Echter Cronjob im STRATO-Kundenbereich: jede Minute `https://www.eintrikot.de/wp-cron.php?doing_wp_cron` aufrufen. Ohne ihn kommt der Versand nur bei Seitenaufrufen voran. | B    |
| 4. SPF, DKIM und DMARC prüfen (`docs/12`). Testmails an Gmail, Outlook, web.de und GMX schicken, Spam-Ordner ansehen.                                                 | B, A |
| 5. Probelauf mit Vorstand und Redaktion als Empfängern (nur sie haben dann „Ja“), danach erste echte Ausgabe.                                                         | B, V |

Absender, Absendername, Antwortadresse und Mails pro Minute stehen für Administratoren unten auf **Redaktion → Newsletter** (Vorgabe: `newsletter@eintrikot.de`, Antworten an `info@eintrikot.de`, 20 pro Minute). Zugangsdaten für den Versand liegen nur im SMTP-Plugin auf dem Server, nie im Repository.

Echte Mails an Mitglieder erst nach dem Umzug auf `www.eintrikot.de` mit HTTPS (Abmeldelinks müssen auf die richtige Domain zeigen).

## Eine Ausgabe vom Entwurf bis zum Archiv

1. **Anlegen (R):** Portal → Redaktion → Newsletter → „Neue Ausgabe anlegen“. Im Editor: Titel = Betreff, rechts „Ausgabe“ (z. B. „Dezember 2026“) und „Vorschautext“. Erlaubt sind nur Überschrift, Absatz, Bild (mit Alternativtext), Button und Trennlinie. Anrede, Logo und Fußzeile setzt das System.
2. **Vorschau und Testversand (R):** Über „Vorschau, Test und Prüfung im Portal“ zurück ins Portal. Vorschau als Computer, Handy und dunkel. Testmail an Vorstand, Redaktion oder Testpostfächer, nie an Mitglieder (wird abgelehnt). Betreff mit „[TEST]“.
3. **Prüfliste (R):** fünf Haken setzen. Das System prüft selbst Betreff, Ausgabe, Vorschautext, Bausteine, Alternativtexte und ob die aktuelle Fassung als Testmail verschickt wurde. Dann „Als bereit melden“.
4. **Freigabe (V):** Der Bildschirm zeigt die Empfängerzahl. Haken setzen, „Freigeben und Versand starten“. Wer den Inhalt zuletzt geändert hat, kann nicht freigeben (Vier-Augen-Prinzip). Ändert sich die Empfängerzahl zwischen Ansehen und Klick, muss neu bestätigt werden.
5. **Versand:** in Paketen, jede Mail einzeln. Vor jeder Mail wird die Einwilligung erneut geprüft. Fehlgeschlagene Mails werden bis zu dreimal versucht. Die Seite zeigt den Fortschritt und aktualisiert sich selbst. V kann abbrechen und fortsetzen; niemand bekommt die Ausgabe doppelt.
6. **Danach (R):** Rückläufer im Postfach des Absenders prüfen, kaputte Adressen im Profil korrigieren und auf der Ausgabe „Als geprüft vermerken“. Die Ausgabe steht für alle Mitglieder unter Service → Newsletter.

Wird der Inhalt nach der Freigabe, aber vor der ersten Mail geändert, ist die Ausgabe wieder „Bereit“ und braucht eine neue Freigabe. Sobald der Versand begonnen hat, ist die Ausgabe gesperrt (nicht mehr bearbeitbar oder löschbar); Versand und Archiv zeigen den eingefrorenen Stand der Freigabe.

## Abmeldung

Jede Mail enthält einen persönlichen, signierten Abmeldelink und die Kopfzeilen `List-Unsubscribe` und `List-Unsubscribe-Post`. Der Link zeigt eine Bestätigungsseite; erst der Knopf (oder der One-Click-Aufruf des Mailprogramms per POST) setzt die Einwilligung auf „Nein“. Die Abmeldung steht im Änderungsprotokoll. Wer sich während eines Versands abmeldet, bekommt nichts mehr. Der Link in Testmails ändert nichts.

## Protokoll und Aufbewahrung

- Änderungsprotokoll: Bereit, Freigabe mit Empfängerzahl, zurückgenommene Freigabe, Abbruch, Fortsetzen, Versandende mit Zahlen, Abmeldungen, Änderung der Versand-Einstellungen.
- Versandprotokoll (je Ausgabe und Empfänger: Nutzer-ID, Status, Versuche, Fehlertext, keine Adresse, kein Inhalt) wird nach 12 Monaten gelöscht, beim Löschen eines Kontos sofort.
- Keine Öffnungs- oder Klickverfolgung.

## Wenn etwas hakt

| Beobachtung                                       | Ursache und Abhilfe                                                                                                                                         |
| ------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Fortschritt bleibt stehen                         | WP-Cron läuft nicht. Cronjob (Einrichten, Schritt 3) prüfen. Die Seite stößt beim Freigeben und Fortsetzen einen Durchlauf an.                               |
| Viele Fehler „Mail wurde nicht angenommen“        | SMTP-Anmeldung oder Absender-Alias stimmt nicht. Versand abbrechen, SMTP-Plugin prüfen (Testmail dort), dann fortsetzen. Fehlerzeilen werden nicht wiederholt. |
| Fehler „Versand unterbrochen, Zustellung unklar“  | Der Server hat mitten in einer Mail gestoppt. Diese Person wird nicht erneut angeschrieben, damit niemand doppelt Post bekommt.                              |
| „Freigeben muss eine andere Person …“             | Vier-Augen-Prinzip: Eine andere Person aus dem Vorstand gibt frei.                                                                                          |
| Redaktion → Newsletter zeigt nur einen Hinweis    | Plugin ist nicht aktiviert (Einrichten, Schritt 1).                                                                                                         |

## Lokal testen

`tools/lokal-test.sh test` führt die Regeltests des Core (`tests/run.php`) und des Newsletters (`tests/newsletter.php`) aus. Mails werden lokal nie versendet, nur in `.lokal/mails.log` mitgeschrieben.
