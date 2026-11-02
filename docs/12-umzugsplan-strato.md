# Umzugsplan: eintrikot.de auf eigenen STRATO-Webspace

Stand: 03.11.2026. Ergänzt `docs/06` (Ablauf der Adressumstellung) und `docs/09` (Checkliste mit Import). Dieses Dokument beantwortet, **wie die Seite auf den neuen Webspace kommt** und in welcher Reihenfolge Domain, Webspace und DNS umgestellt werden. Hauptadresse: `https://www.eintrikot.de`.

**Wer macht was:** B = Björn, V = Vorstand, C = Claude.

## Ausgangslage (geprüft am 30.10.2026)

- Die Entwicklungsseite läuft unter `eintrikot.myemmel.com` im privaten STRATO-Konto von B (193.141.3.70).
- `eintrikot.de`: Nameserver bei Hetzner. `@`, `www` und jeder andere Name (Platzhalter `*`) zeigen auf 188.40.29.79; dort antwortet der Server mit 403.
- Mail läuft über Google Workspace (MX `smtp.google.com`). Das ist der wichtigste Bestandteil der Domain und darf beim Umzug nicht ausfallen.
- Der Auth-Code (AuthKey) für den Domain-Transfer liegt vor.
- **Testdomain:** Im STRATO-Paket ist eine weitere Domain inklusive. Darauf ist `https://eintrikot.com` gesichert. Die Seite läuft dort seit dem 02.11.2026 (Kopie der Entwicklungsseite per Duplicator, Ordner `/Eintrikot_App`, `noindex`). Das inklusive SSL-Zertifikat gilt für `eintrikot.com` und `www.eintrikot.com`. Die Zugangsdaten und die Datenbank stehen im Kundenbereich, nicht im Repository.
- Das Einspielen aus GitHub auf `eintrikot.com` ist eingerichtet (Workflow „Einspielen Produktion“, Environment `Production`, nur von Hand, nur von `main`) und mit Probelauf und echtem Lauf geprüft.

### DNS-Zone vom 30.10.2026 (vom Dienstleister geliefert, vor dem Umzug sichern und vergleichen)

| Name | Typ | Wert | Beim Umzug |
|---|---|---|---|
| `eintrikot.de` | A | 188.40.29.79 | **ändern** auf den neuen Webspace (Adresse zeigt STRATO nach dem Transfer) |
| `www.eintrikot.de` | A | 188.40.29.79 | **ändern** auf den neuen Webspace |
| `*.eintrikot.de` (Platzhalter) | A | 188.40.29.79 | **weglassen** (Subdomains sind nicht vorgesehen) |
| `mail.eintrikot.de` | A | 188.40.29.79 | weglassen, sofern nichts auf dem alten Server genutzt wird |
| `eintrikot.de` | MX | 1 `smtp.google.com.` | **übernehmen** (Mail läuft über Google Workspace) |
| `eintrikot.de` | TXT | `v=spf1 a mx include:amazonses.com include:_spf.google.com -all` | **übernehmen**; `include:amazonses.com` nur behalten, wenn Amazon SES noch genutzt wird (siehe Offene Punkte) |
| `eintrikot.de` | TXT | `google-site-verification=…` | **übernehmen** |
| `_dmarc.eintrikot.de` | TXT | `v=DMARC1;p=quarantine;sp=none;pct=50;adkim=r;aspf=r;` | **übernehmen** |
| `google._domainkey.eintrikot.de` | TXT | DKIM-Schlüssel von Google | **übernehmen**, vollständig kopieren |
| Drei CNAME-Einträge für Amazon-SES-DKIM | CNAME | laut Zonenliste des Dienstleisters | nur übernehmen, wenn SES genutzt wird |
| `inx25238512`, `inx64990706`, `inx91815290`, `newsmail` | CNAME | Inxmail laut Zonenliste | nur übernehmen, wenn Inxmail (Newsletter) genutzt wird |
| `default2512._domainkey` | DKIM | laut Zonenliste | nur übernehmen, wenn der Absender bekannt und aktiv ist |
| alter SRV-Eintrag auf `mail.your-server.de` | SRV | laut Zonenliste | **weglassen** |

Werte der CNAME-, DKIM- und SRV-Einträge aus der vom Dienstleister gelieferten Liste kopieren, nicht aus diesem Dokument abtippen. Vor dem Umzug im DNS-Editor des alten Anbieters prüfen, ob weitere Einträge vorhanden sind, und einen Screenshot der gesamten Zone ablegen.

## Phase 0: Entscheidungen (V und B)

1. **Vertragsinhaber:** Der Vertrag und die Domain sollten auf den Verein laufen, nicht auf das private Konto von B. Sonst hängen Website und Mitgliederdaten an einer Person. Zugangsdaten gehören in den Passwortmanager, mindestens zwei Personen haben Zugriff.
2. **Paket:** Gleiche Produktlinie wie die Entwicklungsseite (STRATO Webhosting). Dort ist der Ablauf (SFTP-Einspielen, PHP 8.3, ZipArchive) bereits erprobt. Anforderungen: PHP 8.3 oder neuer, eine MySQL-Datenbank, SFTP-Zugang, SSL für `eintrikot.de` und `www`, tägliche Sicherung, Cronjob oder ausreichend Besucher für WP-Cron. Nach Recherchen vom 30.10.2026 (Angaben schwanken zwischen den Quellen, vor der Buchung bei STRATO prüfen): Einsteigerpakete ab etwa 5 Euro im Monat Regelpreis, Aktionspreise im ersten Jahr (1 Euro oder 0 Euro im ersten Monat) nur bei 12 Monaten Laufzeit; bei monatlicher Laufzeit 10 bis 15 Euro Einrichtungsgebühr. **Mit dem Regelpreis rechnen.** Das „WordPress-Hosting“ von STRATO ist verwaltet; SFTP-Zugang und eigenes Einspielen von Theme und Plugin vor der Buchung klären, sonst das normale Webhosting nehmen.
3. **Domain:** Der Transfer mit dem Auth-Code ist zugleich der Umzugstag (Phase 5). Die Zone bei STRATO muss vorher vollständig vorbereitet sein, damit die Mail nicht ausfällt.

## Phase 1: Webspace buchen (B oder V) – erledigt am 31.10.2026

1. Paket im Namen des Vereins buchen, 12 Monate, Preis und Verlängerung notieren (Kündigungsfrist in den Kalender).
2. Im Kundenbereich: PHP 8.3 oder neuer wählen, Datenbank anlegen, SFTP-Zugang prüfen (Host, Benutzer, Passwort, Port), SSL aktivieren.
3. Den Hostnamen für Tests notieren (STRATO zeigt eine vorläufige Adresse an).

## Phase 2: Seite auf den neuen Webspace kopieren – erledigt am 02.11.2026 auf `eintrikot.com`

Voraussetzung: Beiratstest beendet (31.10.2026), Testphase beendet, Testdaten und Test-Rückmeldungen gelöscht (`docs/10`), Altplugins gelöscht (erledigt am 30.10.2026).

1. **Sicherung** der Entwicklungsseite (Datenbank und Dateien).
2. **Kopie mit Duplicator:** Auf der Entwicklungsseite das Duplicator-Backup erzeugen, beide Dateien (Archiv und `installer.php`) per SFTP in den Ordner `/Eintrikot_App` laden, den Installer unter `https://eintrikot.com/…installer.php` aufrufen, Datenbankdaten aus dem STRATO-Kundenbereich eintragen (Host ist nicht `localhost`), neue Adresse `https://eintrikot.com`. Der Installer ersetzt die Adresse in der Datenbank selbst (auch in serialisierten Daten) und räumt danach seine Dateien auf. Der Browser blockiert den Download über `http`; die Dateien deshalb per SFTP-Programm vom Server holen.
3. **Theme und Plugin** kommen nach der Kopie über das Einspielen aus GitHub (Workflow „Einspielen Produktion“), damit der Stand mit `main` übereinstimmt.
4. Nach der Kopie: `readme.html` und `license.txt` löschen, Duplicator-Plugin entfernen, PHP 8.3 im Kundenbereich einstellen, `noindex` setzen (Einstellungen → Lesen). Einladungen gehen auf `.com` absichtlich nicht hinaus, weil sie nur für `eintrikot.de` freigeschaltet sind.

## Phase 3: Test auf `https://eintrikot.com` (B, C)

1. Startseite, alle Menüpunkte, Anmeldung, Portal, Profil speichern, Stories mit Bildern, Mitglied werden.
2. Prüfen: keine Adressen mit `myemmel` im Quelltext (`curl` oder „Quelltext anzeigen“), Bilder laden, keine Fehlermeldungen.
3. Plugin-Version, Theme-Version und PHP-Version notieren und mit der Entwicklungsseite vergleichen.

## Phase 4: Vorbereitung des Transfers (B)

Der alte Provider (der Dienstleister, der das DNS bei Hetzner verwaltet) muss am Umzugstag **nichts umstellen**. Mit dem Domain-Transfer übernimmt STRATO die Domain und die DNS-Zone.

1. **Zwei Fragen an den Dienstleister** (Text unten): Zonenexport und Inhalt des alten Servers.
2. **Zone bei STRATO vollständig vorbereiten**, bevor sie wirksam wird (Tabelle oben, einschließlich Google-DKIM und DMARC). `@` und `www` zeigen auf den neuen Webspace. Beim Auftrag für den Domain-Umzug fragt STRATO, ob DNS-Einstellungen mitgenommen oder vorher bearbeitet werden können. Vor dem Absenden einen Screenshot dieser Maske prüfen lassen (C).
3. **Mail-Test vorher:** Google-Workspace-SMTP einrichten und Test-Mails an Gmail, Outlook und web.de senden (`docs/09`).
4. Ruhigen Tag wählen, nicht vor einem Wochenende. Eine kurze Nichterreichbarkeit der Website beim Umschalten ist in Ordnung; die Mail muss durchgehend laufen.

## Phase 5: Transfer und Umstellung (Umzugstag)

1. **Sicherung** der neuen Seite, Screenshot der alten Zone.
2. **Auth-Code prüfen** (Gültigkeit), Transfer bei STRATO beauftragen, Zone wie in Phase 4 vorbereitet lassen.
3. Der alte Provider gibt den Transfer frei und **kündigt die Domain nicht vor dem Abschluss**.
4. Nach dem Abschluss verwaltet STRATO die Zone. Warten, bis `eintrikot.de` und `www.eintrikot.de` auf den neuen Webspace zeigen (bis zu einem Tag, je nach Zwischenspeichern). Die Hosts-Zeile auf dem eigenen Rechner entfernen.
5. SSL-Zertifikat für `eintrikot.de` und `www.eintrikot.de` aktivieren, auf „ohne Warnung“ warten.
6. **Adresse von `.com` auf `.de` umstellen.** Die Seite läuft bis dahin auf `https://eintrikot.com`. Die Domain `eintrikot.de` wird nach dem Transfer an denselben Webspace-Ordner angebunden (zweite Domain beim STRATO-Paket prüfen, Kosten klären). Dann: WordPress-Adresse und Website-Adresse auf `https://www.eintrikot.de` setzen, in der Datenbank mit Better Search Replace die **volle Adresse** `https://eintrikot.com` durch `https://www.eintrikot.de` ersetzen (nie nur den Namen, wegen serialisierter Daten; Probelauf zuerst), Permalinks neu speichern, `noindex` wieder entfernen, Variable `SITE_URL` im Environment `Production` auf `https://www.eintrikot.de` setzen. Danach `docs/06` ab Schritt 4 und `docs/09` Abschnitt 2 bis Schritt 14.
7. `eintrikot.com` und `www.eintrikot.com` leiten danach per 301 auf `https://www.eintrikot.de`, `eintrikot.de` auf `https://www.eintrikot.de`. Das SSL-Zertifikat muss für die neue Adresse gelten.
8. **Mail prüfen:** Eine Mail an `info@eintrikot.de` senden und empfangen, danach die Test-E-Mail des Portals.
9. Alte Adresse `eintrikot.myemmel.com`: leitet per 301 auf `https://www.eintrikot.de` (oder bleibt als Staging mit `noindex`, siehe Phase 7).

**Rückweg:** Solange der Transfer nicht abgeschlossen ist, bleibt alles beim Alten. Danach lassen sich die A-Einträge der Zone bei STRATO jederzeit selbst ändern.

### Nachricht an den Dienstleister (zwei Fragen)

> Hallo ___, wir ziehen die Domain eintrikot.de zu STRATO um. Zwei Bitten dazu: 1. Schicken Sie uns bitte alle aktuellen DNS-Einträge der Domain (Zonenexport oder Liste), damit wir nichts vergessen. 2. Läuft auf dem bisherigen Server (188.40.29.79) noch etwas für uns? Bitte sichern Sie uns Dateien und Datenbank, bevor er abgeschaltet wird. Bitte kündigen Sie die Domain erst, wenn wir den Abschluss des Transfers bestätigt haben. Vielen Dank! ___

## Phase 6: Einspielen und Betrieb – eingerichtet

1. Environment `Production` ist angelegt: nur Branch `main`, Secrets `SFTP_HOST`, `SFTP_USER`, `SFTP_PASSWORD`, `SFTP_KNOWN_HOSTS`, `BACKUP_PASSPHRASE`, Variablen `WP_CONTENT_PATH` (`/Eintrikot_App/wp-content`) und `SITE_URL`. Keine Zugangsdaten im Chat. Optional: „Required reviewers“ eintragen, dann wartet jeder Lauf auf eine Bestätigung.
2. Der Workflow `einspielen-produktion.yml` läuft nur von Hand (Probelauf `dry_run` ist voreingestellt). Entwicklung (`einspielen.yml`) läuft weiter automatisch beim Merge auf `main`. Beschreibung in `docs/05`.
3. Die Sicherung des Servers bleibt verschlüsselt (`BACKUP_PASSPHRASE`).
4. Bei Umstellung auf `.de`: Variable `SITE_URL` anpassen, sonst prüft der Lauf die falsche Adresse.

## Phase 7: Nach dem Umzug

- **Staging behalten:** Die Entwicklungsseite auf `myemmel.com` kann als Testseite bleiben (`noindex`, Testbalken, keine Mails). Neue Fassungen zuerst dort prüfen, dann nach Produktion einspielen.
- STRATO-Sicherung einmal testweise zurückspielen (nicht auf der Live-Seite).
- Nach zwei Wochen: DMARC von `quarantine`/`pct=50` auf 100 % anheben, HSTS auf ein Jahr, Kündigungsfrist des alten Anbieters prüfen.

## Offene Punkte

- Wer ist Vertragsinhaber, und welche Zahlungsart wird hinterlegt? (V)
- Welcher Auth-Code gilt bis wann? (B)
- Läuft auf dem alten Server (188.40.29.79) noch etwas, das erhalten bleiben muss? Er antwortet derzeit mit 403. Vor dem Abschalten Dateien sichern (Frage an den Dienstleister). (B)
- Lässt STRATO die DNS-Zone vor dem Abschluss des Transfers bearbeiten? Screenshot der Maske vor dem Auftrag. (B)
- Wer nutzt **Amazon SES** und **Inxmail** (Newsletter, Vereinsmails)? Davon hängt ab, welche Einträge in die neue Zone und in den SPF-Eintrag kommen. Im Zweifel beim Dienstleister und im Vorstand nachfragen. (V, B)
- Zweite Domain beim Paket: Kann `eintrikot.de` an denselben Ordner wie `eintrikot.com` angebunden werden, und was kostet das? Die inklusive Domain ist mit `eintrikot.com` belegt. (B)
- Wurden vor dem Duplicator-Backup alle Test- und Beiratsdaten gelöscht (`docs/10`)? Sonst liegen sie auch auf `.com`. (B)
