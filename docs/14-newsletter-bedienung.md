# Newsletter: Einrichtung und Bedienung

Stand: 04.11.2026, Plugin `eintrikot-newsletter` 0.1.1 (braucht Core ab 0.38.0). Konzept und Entscheidungen: `docs/13`.

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

## Checkliste Versandweg (B)

Zum Abhaken, in dieser Reihenfolge. Werte und Kennwörter nie in Chat, Issue oder Repository eintragen.

**A. Absender-Alias in Google Workspace**

- [ ] admin.google.com → Verzeichnis → Nutzer → das Postfach, das versendet (z. B. `info@eintrikot.de`) → Nutzerinformationen → Alternative E-Mail-Adressen: `newsletter@eintrikot.de` hinzufügen.
- [ ] Im Gmail dieses Postfachs: Einstellungen → Konten → „Senden als“: `newsletter@eintrikot.de` hinzufügen, Name „EINTRIKOT e. V.“. Ohne diesen Schritt ersetzt Google den Absender durch die Anmeldeadresse.
- [ ] Rückläufer und Antworten an `newsletter@` landen in diesem Postfach. Optional einen Gmail-Filter „an: newsletter@eintrikot.de“ → Label `Newsletter/Rückläufer`.

**B. SMTP-Plugin auf der Seite**

- [ ] Für das versendende Postfach die Bestätigung in zwei Schritten einschalten und ein **App-Passwort** erzeugen (myaccount.google.com → Sicherheit → App-Passwörter, Name „WordPress eintrikot.de“). Kein persönliches Passwort verwenden.
- [ ] Backend → Plugins → Installieren: „WP Mail SMTP“. Mailer „Andere SMTP“: Host `smtp.gmail.com`, Verschlüsselung TLS, Port `587`, Authentifizierung an, Benutzer = versendendes Postfach, Passwort = App-Passwort.
- [ ] Absender-Adresse dort `info@eintrikot.de`, **„Absender-E-Mail erzwingen“ aus** lassen, sonst überschreibt das Plugin `newsletter@`.
- [ ] Testmail aus WP Mail SMTP an ein eigenes Gmail-Postfach: kommt an, nicht im Spam.
- [ ] Grenze im Blick: Google erlaubt pro Postfach etwa 2.000 Mails am Tag. Bei vier Ausgaben an 200 bis 300 Empfänger reicht das deutlich.

**C. Cronjob bei STRATO**

- [ ] STRATO-Kundenbereich → Paket → Cronjobs (je nach Paket unter „Hosting“). Neuer Cronjob, jede Minute: `https://www.eintrikot.de/wp-cron.php?doing_wp_cron`.
- [ ] Wenn das Paket keine Cronjobs kennt: Ohne Cronjob läuft der Versand nur bei Seitenaufrufen. Jeder Seitenaufruf stößt WP-Cron an, und die Fortschrittsseite lädt sich alle 20 Sekunden neu; dann während des Versands diese Seite offen lassen.
- [ ] Prüfen: Nach der Freigabe einer Probeausgabe steigt „… von … gesendet“ jede Minute, ohne dass jemand die Seite neu lädt.

**D. DNS (Werte in `docs/12`)**

- [ ] SPF enthält `include:_spf.google.com`.
- [ ] DKIM `google._domainkey` ist gesetzt und in admin.google.com → Apps → Gmail → „E-Mail authentifizieren“ als aktiv angezeigt.
- [ ] DMARC `_dmarc` ist gesetzt (heute `p=quarantine; pct=50`, nach zwei Wochen ohne Probleme auf 100 %).

**E. Testmails**

- [ ] Im Portal eine Testausgabe anlegen und per **Testversand** an Postfächer bei Gmail, Outlook.com, web.de und GMX schicken.
- [ ] In jedem Postfach: im Posteingang (nicht Spam), Logo und Bild sichtbar, ohne „Bilder laden“ zu klicken, hell und dunkel lesbar, Abmeldelink zeigt „Der Abmeldelink funktioniert“.
- [ ] In Gmail „Original anzeigen“: SPF, DKIM und DMARC jeweils **PASS**.
- [ ] In Gmail erscheint neben dem Absender „Abmelden“ (Kopfzeile `List-Unsubscribe`). Das kann bei kleinen Absendern einige Ausgaben dauern.

**F. Probelauf**

- [ ] Nur Vorstand und Redaktion haben im Portal „Ja“ gesagt (Übersicht zeigt die Zahl).
- [ ] Eine echte Ausgabe durch den ganzen Ablauf: Prüfliste, Freigabe durch eine zweite Person, Versand, Archiv, Rückläufer prüfen.
- [ ] Eine Person meldet sich über den Link in der Mail ab und prüft im Profil „Nein“ und im Änderungsprotokoll den Eintrag.

## Eine Ausgabe vom Entwurf bis zum Archiv

1. **Anlegen (R):** Portal → Redaktion → Newsletter → „Neue Ausgabe anlegen“. Im Editor: Titel = Betreff, rechts „Ausgabe“ (z. B. „Dezember 2026“) und „Vorschautext“. Erlaubt sind nur Überschrift, Absatz, Bild (aus der Mediathek, mit Alternativtext), Button und Trennlinie. Bilder werden in die Mail eingebettet, beim Öffnen wird nichts nachgeladen. Anrede, Logo und Fußzeile setzt das System.
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

## Redaktionshilfen in Google Workspace (ohne Code)

Laut Briefing bleiben Themensammlung und Redaktionsübersicht in Google Workspace. Vorlage zum Einrichten (B):

**Google Formular „Thema für EINTRIKOT vorschlagen“** (Drive-Ordner `Newsletter`, Antworten in das Sheet unten)

| Frage                                | Typ                                     | Pflicht |
| ------------------------------------ | --------------------------------------- | ------- |
| Worum geht es?                       | Kurzantwort                             | ja      |
| Kurz beschrieben                     | Absatz                                  | ja      |
| Link zur Quelle                      | Kurzantwort (URL)                       | nein    |
| Gibt es ein Bild? Wer hat die Rechte? | Absatz                                  | nein    |
| Bis wann ist es aktuell?             | Datum                                   | nein    |
| Dein Name und deine E-Mail           | Kurzantwort                             | ja      |
| Darf die Redaktion dich nachfragen?  | Ja/Nein                                 | ja      |

**Google Sheet „Redaktionsübersicht“**, eine Zeile je Thema:

| Spalte       | Inhalt                                                                 |
| ------------ | ---------------------------------------------------------------------- |
| Eingang      | Datum (aus dem Formular)                                               |
| Thema        | Kurztitel                                                              |
| Quelle       | Formular, Mail (Label `Newsletter/Eingang`), Redaktion                 |
| Link         | Quelle oder Drive-Ordner                                               |
| Bildstatus   | kein Bild · Bild da, Rechte offen · Bild da, Rechte geklärt            |
| Relevanz     | hoch · mittel · niedrig                                                |
| Ausgabe      | z. B. „Dezember 2026“                                                  |
| Status       | neu · in Arbeit · im Entwurf · verworfen · versendet                   |
| Freigabe     | Name und Datum der Freigabe im Portal                                  |
| Notiz        | frei                                                                   |

Status und Bildstatus als Dropdown (Daten → Datenvalidierung). Bedingte Formatierung: „Rechte offen“ rot.

**Gmail-Label** `Newsletter/Eingang` für Hinweise per Mail, `Newsletter/Rückläufer` für unzustellbare Mails nach einem Versand.

**Drive-Ordner** `Newsletter/` mit `Bilder` (nur Bilder mit geklärten Rechten), `Texte` (Entwürfe), `Ausgaben` (optional PDF der versendeten Ausgabe; das Archiv steht ohnehin im Portal).

Bilder für eine Ausgabe immer in die **Mediathek der Website** hochladen: Nur von dort bettet das Plugin sie in die Mail ein.

## Lokal testen

`tools/lokal-test.sh test` führt die Regeltests des Core (`tests/run.php`) und des Newsletters (`tests/newsletter.php`) aus. Mails werden lokal nie versendet, nur in `.lokal/mails.log` mitgeschrieben.
