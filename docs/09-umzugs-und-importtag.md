# Checkliste: Umzug auf eintrikot.de und Import der Mitglieder

Stand: 07.10.2026. Diese Liste gilt für den Weg von `http://eintrikot.myemmel.com` auf `https://eintrikot.de` und für den ersten Import der rund 200 Mitglieder. Sie baut auf `docs/06` (Umzug), `docs/07` (Aufnahme, Einladungen) und `docs/05` (Einspielen) auf und ersetzt sie nicht.

**Wer macht was:** B = Björn, C = Claude, V = Vorstand oder Beirat, D = Datenschutzbeauftragte(r).

**Grundregeln**
- Echte Mitgliederdaten gibt es erst auf `https://eintrikot.de`. Auf der Entwicklungsseite wird nichts importiert.
- Der Import legt nur **Konten** an. Es geht keine Mail raus. Einladungen sind eine eigene, spätere Stufe.
- Vor jedem Schritt, der Daten verändert, gibt es eine Sicherung.
- Wird ein Prüfpunkt rot, wird angehalten. Nichts „durchziehen“.

---

## 1. Vorbereitung (vor dem Umzugstag)

### Daten
- [ ] **B:** Die 12 Eintrittsdaten in der Master ändern (Nr. 1–7 auf 23.09.2025, die anderen laut `Eintrittsdaten-Korrektur-Vorschlag.csv`), danach in MeinVerein angleichen. Master und MeinVerein müssen dasselbe sagen.
- [x] **B:** Blätter „Team-Zuordnung“ und „Stationen Staff“ ausgefüllt (erledigt 07.10.).
- [ ] **B:** Die 18 Mitglieder ohne Geburtsdatum klären (Nummern stehen im Prüfbericht): Geburtsdatum nachfragen und in der Master ergänzen. Das Geburtsdatum ist Pflicht (Mitgliedsbeitrag ab 32). Diese 18 werden **wie alle anderen eingeladen**; das Portal muss sie nach der ersten Anmeldung zur Eingabe auffordern (siehe Abschnitt „Offen“).
- [ ] **B:** Master als Excel herunterladen (neueste Version).
- [ ] **C:** Importdatei neu erzeugen: `WP_ROOT=.lokal/wordpress php tools/importdatei.php "<Master>.xlsx"` und den `Pruefbericht.txt` lesen. Erwartung: **201 Mitglieder, alle „Neu“, 0 Fehler**. Sind die Eintrittsdaten in der Master noch alt, dazu `--eintritt=<CSV>`.
- [ ] **B:** Stichprobe in Excel: 3 Mitglieder in `Portal-Import.xlsx` gegen die Master vergleichen (Nummer, Name, E-Mail, Eintritt, Geburtstag, Jahresspende).

### Technik und Inhalt
- [ ] **B/C:** Altplugins und Altseiten bereinigt (`docs/04`): Registrierung geschlossen, Ultimate Member und Co. deaktiviert, Altseiten im Papierkorb.
- [ ] **B:** **E-Mail-Versand einrichten:** STRATO-Postfach für den Absender (z. B. `info@eintrikot.de`), SMTP-Plugin (z. B. WP Mail SMTP), SPF und DKIM bei STRATO. Danach unter Community-Aufbau → Aufnahme & Urkunde **Absender und Signatur** eintragen und die **Test-E-Mail** senden. Kommt sie bei Gmail, Outlook und web.de im Posteingang an (nicht im Spam)?
- [ ] **V:** **Umstiegsmail lesen und freigeben** (Community-Aufbau → Aufnahme & Urkunde → „Umstiegsmail für Bestandsmitglieder“).
- [ ] **D:** Datenschutzerklärung und Impressum lesen (neu seit September: Geburtstag mit Wahl des Mitglieds, Anmeldung zu Terminen, Zustimmung der Eltern, Herkunft der Daten aus NDAlumni, Aufbewahrung 180 Tage für Anmeldungen).
- [ ] **B:** Social-Media-Adressen unter Community-Aufbau → Social Media eintragen (sonst fehlt „Folge uns“ im Footer).
- [ ] **B:** Kennzahl **Mitglieder** unter Community-Aufbau → Kennzahlen fest auf **201** setzen (mit Stand-Datum), sonst zählt die Startseite nur die Konten im Portal.
- [ ] **B:** Termin für den Umzug festlegen: ruhiger Tag, mit Zeit für Fehlersuche. Keine Mitglieder-Mail vorher.
- [ ] **C:** GitHub: Sicherung-Passwort (`BACKUP_PASSPHRASE`) im Passwortmanager vorhanden, ein Einspiel-Lauf mit Sicherung war grün (erledigt, Lauf #43).

---

## 2. Umzugstag

Ablauf wie in `docs/06`. Hier die Prüfpunkte dazu.

| # | Schritt (Kurzform) | Wer | Prüfpunkt (sonst anhalten) |
|---|---|---|---|
| 1 | Sicherung Dateien und Datenbank (STRATO) | B | Sicherung ist sichtbar und herunterladbar |
| 2 | Domain `eintrikot.de` und `www` auf das Verzeichnis zeigen lassen | B | Seite lädt unter der neuen Adresse (noch ohne HTTPS) |
| 3 | SSL-Zertifikat aktivieren | B | `https://eintrikot.de` lädt **ohne Warnung** |
| 4 | WordPress- und Website-Adresse auf `https://eintrikot.de`, neu anmelden | B | Anmeldung klappt |
| 5 | Suchen und Ersetzen (Probelauf, dann echt): `http://eintrikot.myemmel.com` → `https://eintrikot.de` | B/C | Probelauf zeigt nur erwartete Treffer |
| 6 | Permalinks neu speichern | B | Alle Menüpunkte öffnen |
| 7 | HTTPS erzwingen (STRATO oder `.htaccess`) | B | `http://eintrikot.de` leitet auf `https` um |
| 8 | Alte Adresse (301) auf `https://eintrikot.de` | B | Alte Adresse leitet weiter |
| 9 | GitHub: Variable `SITE_URL` auf `https://eintrikot.de` | B/C | Nächster Einspiel-Lauf prüft die neue Adresse und ist grün |
| 10 | Mail: Absender und SMTP auf die neue Domain | B | Test-E-Mail kommt an |
| 11 | Prüfen: Startseite, Menü, Anmeldung, „Passwort vergessen“, Profil speichern, Linkvorschau, Browser-Konsole ohne „Mixed Content“ | B/C | Alles grün |

**Erst wenn Schritt 11 grün ist, geht es mit dem Import weiter.** Das Portal prüft das selbst: Einladungen sind gesperrt, bis die Seite unter HTTPS läuft.

---

## 3. Importtag (nach Schritt 11)

1. [ ] **B:** **Datenbank sichern** (STRATO), direkt vor dem Import. Das ist der Rückweg.
2. [ ] **B:** Als Administrator anmelden.
3. [ ] **B:** Verwaltung → **Neue Mitglieder aufnehmen** → Datei **`Portal-Import.xlsx`** hochladen (liegt in `~/Downloads/Portal-Import/`).
4. [ ] **B:** Vorschau prüfen. Erwartung:
   - **201 Neu**, keine „Fehler“, keine „Schon im Portal“.
   - Der Hinweis „Die Datei enthält auch das NDAlumni-Blatt: Profilangaben für **201** Personen“.
   - Stichprobe: Nummer, Eintritt (Nr. 1–7: 23.09.2025), Geburtsdatum, Spende.
5. [ ] **B:** Auswahl **„Bestandsmitglieder (Umstieg von NDAlumni): Konten anlegen, später nur den Portalzugang mit der Bitte schicken, das Profil zu prüfen (ohne Urkunde)“**. Nicht „Begrüßung … sofort senden“. Dann **„Ausgewählte übernehmen“**.
6. [ ] **B/C:** Ergebnis prüfen:
   - Schritt 3 „Einladungen“: **201 Konten**, alle „Noch nicht eingeladen“.
   - Mitgliederverzeichnis zeigt die Mitglieder.
   - 5 Profile öffnen: Team, DHB-Vita mit plausiblen Jahren (nicht 1905), Länderspiele und Tore ohne „.0“, „Mehr dazu“ bei Mitgliedern mit mehreren Ausbildungen.
7. [ ] **B:** Rollen vergeben: Vorstand, Redaktion und Verwaltung bei den betreffenden Personen (Benutzer → Rolle). Dein eigenes Mitgliedskonto entsteht durch den Import (deine E-Mail steht in der Datei); das technische Administratorkonto bleibt getrennt.
8. [ ] **B:** Kennzahlen prüfen (Startseite: Mitglieder 201).
9. [ ] **B:** Die **Importdatei und alle Master-Downloads aus dem Ordner „Downloads“ löschen** (sie enthalten Namen, E-Mail-Adressen und Geburtsdaten; die Master selbst enthält zusätzlich IBAN).

**Wenn etwas nicht stimmt:** Nichts weiter anklicken, Datenbank-Sicherung von Schritt 1 zurückspielen, Ursache klären (C), Importdatei neu erzeugen, Schritt 3 wiederholen. Konten lassen sich so ohne Spuren zurücknehmen; eine verschickte Einladung nicht.

---

## 4. Einladungen (erst nach dem Import)

1. [ ] **V:** Vorstand und Beirat vorab informieren: Was kommt, wann, von welcher Absenderadresse. Auf Wunsch zuerst nur diese einladen.
2. [ ] **B:** **Probelauf mit 2 bis 3 Personen** (in der Liste unter „Einladungen“ bei der Zeile auf „Einladen“, z. B. bei dir selbst und zwei Vorständen). Prüfen: Mail kommt an und liegt nicht im Spam, der Link führt zum Passwort festlegen, danach „Bitte prüfe dein Profil“, Profil speichern, Aktuelles, Termine, Abmelden.
3. [ ] **B:** **Erste Charge**: „25 Einladungen jetzt senden“. Ein bis zwei Tage beobachten: Rückläufer, Antworten, Fragen.
4. [ ] **B:** Weitere Chargen mit jeweils 25 (bei 201 Mitgliedern sind das 9 Klicks), zum Beispiel 50 bis 75 pro Tag, damit Rückfragen bearbeitbar bleiben.
5. [ ] **B:** Nach 14 Tagen laufen Links ab. Der Stand in der Liste heißt dann „Link abgelaufen“; die Zeile hat den Knopf „Erneut senden“.
6. [ ] **B:** **Die 18 ohne Geburtsdatum** laufen mit den anderen. Nach ihrer ersten Anmeldung fragt das Portal nach dem Geburtsdatum. Wer es nicht eingibt, wird nachgefasst; im Notfall trägt die Verwaltung es nach Rückmeldung ein (Profil bearbeiten, Grund angeben).
7. [ ] **V:** Newsletter: im Portal gilt neues Opt-in. Das Mitglied entscheidet im Profil („EINTRIKOT-Newsletter erhalten“).

---

## 5. Nach dem Start (Woche 1 bis 4)

- [ ] Zahlen ansehen: wie viele aktiv („Aktiv seit …“), wie viele Links abgelaufen. Nachfassen bei Bedarf.
- [ ] Erste **Termine** im Backend anlegen (EINTRIKOT Kalender), erste **Vereinsinfo** veröffentlichen, damit „Aktuelles“ nicht leer ist.
- [ ] Deaktivierte Altplugins endgültig löschen (nach einer Woche, `docs/04` Schritt 8).
- [ ] Eine Sicherung testweise wiederherstellen (nicht auf der Live-Seite), damit der Rückweg erprobt ist.
- [ ] Rückmeldungen sammeln (Texte, Bedienung, Profil), in einem Pull Request bündeln.
- [ ] Mobilansicht (390 und 320 Pixel) mit echten Daten ansehen.

---

## Zeitplan (Vorschlag)

| Wann | Was |
|---|---|
| 1 bis 2 Wochen vorher | Abschnitt 1 komplett, Mail-Versand getestet |
| Umzugstag, Vormittag | Abschnitt 2 |
| Gleicher Tag oder Tag danach | Abschnitt 3 (Import), mit Pause nach Schritt 6 |
| 1 bis 3 Tage später | Abschnitt 4, Schritte 1 bis 3 (Probelauf, erste Charge) |
| Woche danach | weitere Chargen, die 18 klären |

---

## Offen vor den Einladungen

- **Abfrage des Geburtsdatums nach der Anmeldung** für die 18 Mitglieder ohne Geburtsdatum (und für künftige Fälle): eine eigene, kurze Seite direkt nach dem Einloggen mit Erklärung („Dein Geburtsdatum bestimmt deinen Mitgliedsbeitrag: bis 31 beitragsfrei, ab 32 50 € im Jahr“). Entscheidung und Umsetzung stehen noch aus.
