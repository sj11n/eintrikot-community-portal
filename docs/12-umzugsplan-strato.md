# Umzugsplan: eintrikot.de auf eigenen STRATO-Webspace

Stand: 30.10.2026. Ergänzt `docs/06` (Ablauf der Adressumstellung) und `docs/09` (Checkliste mit Import). Dieses Dokument beantwortet, **wie die Seite auf den neuen Webspace kommt** und in welcher Reihenfolge Domain, Webspace und DNS umgestellt werden. Hauptadresse: `https://www.eintrikot.de`.

**Wer macht was:** B = Björn, V = Vorstand, C = Claude.

## Ausgangslage (geprüft am 30.10.2026)

- Die Entwicklungsseite läuft unter `eintrikot.myemmel.com` im privaten STRATO-Konto von B (193.141.3.70).
- `eintrikot.de`: Nameserver bei Hetzner. `@`, `www` und jeder andere Name (Platzhalter `*`) zeigen auf 188.40.29.79; dort antwortet der Server mit 403.
- Mail läuft über Google Workspace (MX `smtp.google.com`). Das ist der wichtigste Bestandteil der Domain und darf beim Umzug nicht ausfallen.
- Der Auth-Code (AuthKey) für den Domain-Transfer liegt vor.

### DNS-Zone vom 30.10.2026 (vor dem Umzug sichern und vergleichen)

| Name | Typ | Wert |
|---|---|---|
| `eintrikot.de` | A | 188.40.29.79 |
| `*.eintrikot.de` (Platzhalter) | A | 188.40.29.79 |
| `eintrikot.de` | MX | 1 `smtp.google.com.` |
| `eintrikot.de` | TXT | `v=spf1 a mx include:amazonses.com include:_spf.google.com -all` |
| `eintrikot.de` | TXT | `google-site-verification=P9e00JDcxbkxKlzm7liCCiOv0_C3WCXOPfiTQ1CPUK0` |
| `_dmarc.eintrikot.de` | TXT | `v=DMARC1;p=quarantine;sp=none;pct=50;adkim=r;aspf=r;` |
| `google._domainkey.eintrikot.de` | TXT | DKIM-Schlüssel von Google (im DNS-Editor des alten Anbieters vollständig kopieren) |

Vor dem Umzug im DNS-Editor des alten Anbieters prüfen, ob weitere Einträge vorhanden sind (zum Beispiel für andere Dienste), und einen Screenshot der gesamten Zone ablegen.

## Phase 0: Entscheidungen (V und B)

1. **Vertragsinhaber:** Der Vertrag und die Domain sollten auf den Verein laufen, nicht auf das private Konto von B. Sonst hängen Website und Mitgliederdaten an einer Person. Zugangsdaten gehören in den Passwortmanager, mindestens zwei Personen haben Zugriff.
2. **Paket:** Gleiche Produktlinie wie die Entwicklungsseite (STRATO Webhosting). Dort ist der Ablauf (SFTP-Einspielen, PHP 8.3, ZipArchive) bereits erprobt. Anforderungen: PHP 8.3 oder neuer, eine MySQL-Datenbank, SFTP-Zugang, SSL für `eintrikot.de` und `www`, tägliche Sicherung, Cronjob oder ausreichend Besucher für WP-Cron. Nach Recherchen vom 30.10.2026 (Angaben schwanken zwischen den Quellen, vor der Buchung bei STRATO prüfen): Einsteigerpakete ab etwa 5 Euro im Monat Regelpreis, Aktionspreise im ersten Jahr (1 Euro oder 0 Euro im ersten Monat) nur bei 12 Monaten Laufzeit; bei monatlicher Laufzeit 10 bis 15 Euro Einrichtungsgebühr. **Mit dem Regelpreis rechnen.** Das „WordPress-Hosting“ von STRATO ist verwaltet; SFTP-Zugang und eigenes Einspielen von Theme und Plugin vor der Buchung klären, sonst das normale Webhosting nehmen.
3. **Domain:** Der Transfer mit dem Auth-Code ist zugleich der Umzugstag (Phase 5). Die Zone bei STRATO muss vorher vollständig vorbereitet sein, damit die Mail nicht ausfällt.

## Phase 1: Webspace buchen (B oder V)

1. Paket im Namen des Vereins buchen, 12 Monate, Preis und Verlängerung notieren (Kündigungsfrist in den Kalender).
2. Im Kundenbereich: PHP 8.3 oder neuer wählen, Datenbank anlegen, SFTP-Zugang prüfen (Host, Benutzer, Passwort, Port), SSL aktivieren.
3. Den Hostnamen für Tests notieren (STRATO zeigt eine vorläufige Adresse an).

## Phase 2: Seite auf den neuen Webspace kopieren (B, C unterstützt)

Voraussetzung: Beiratstest beendet (31.10.2026), Testphase beendet, Testdaten und Test-Rückmeldungen gelöscht (`docs/10`), Altplugins gelöscht (erledigt am 30.10.2026).

1. **Sicherung** der Entwicklungsseite (Datenbank und Dateien).
2. WordPress auf dem neuen Webspace **neu installieren** (gleiche Hauptversion wie die Entwicklungsseite).
3. **Inhalt übertragen:** Datenbank und `wp-content/uploads` von der Entwicklungsseite auf den neuen Webspace kopieren. Zwei Wege: ein Umzugs-Plugin (zum Beispiel Duplicator oder All-in-One WP Migration) oder Datenbank-Export per phpMyAdmin plus Hochladen der Uploads per SFTP. Danach in der Datenbank `http://eintrikot.myemmel.com` durch die vorläufige Adresse ersetzen (Suchen/Ersetzen-Plugin, Probelauf zuerst).
4. **Theme und Plugin** kommen nicht aus der Kopie, sondern über das Einspielen aus GitHub (Phase 6, Punkt 1), damit der Stand mit `main` übereinstimmt.
5. Suchmaschinen aussperren (`noindex`) und im Mail-Plugin den Versand vorerst **aus** lassen, bis die Seite live ist.

## Phase 3: Test auf dem neuen Webspace (B, C)

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
6. Weiter nach `docs/06` ab Schritt 4 (WordPress-Adresse `https://www.eintrikot.de`, Suchen/Ersetzen, Permalinks, 301-Weiterleitungen, `SITE_URL`) und `docs/09` Abschnitt 2 bis Schritt 14.
7. **Mail prüfen:** Eine Mail an `info@eintrikot.de` senden und empfangen, danach die Test-E-Mail des Portals.
8. Alte Adresse `eintrikot.myemmel.com`: leitet per 301 auf `https://www.eintrikot.de` (oder bleibt als Staging mit `noindex`, siehe Phase 7).

**Rückweg:** Solange der Transfer nicht abgeschlossen ist, bleibt alles beim Alten. Danach lassen sich die A-Einträge der Zone bei STRATO jederzeit selbst ändern.

### Nachricht an den Dienstleister (zwei Fragen)

> Hallo ___, wir ziehen die Domain eintrikot.de zu STRATO um. Zwei Bitten dazu: 1. Schicken Sie uns bitte alle aktuellen DNS-Einträge der Domain (Zonenexport oder Liste), damit wir nichts vergessen. 2. Läuft auf dem bisherigen Server (188.40.29.79) noch etwas für uns? Bitte sichern Sie uns Dateien und Datenbank, bevor er abgeschaltet wird. Bitte kündigen Sie die Domain erst, wenn wir den Abschluss des Transfers bestätigt haben. Vielen Dank! ___

## Phase 6: Einspielen und Betrieb nach dem Umzug (C)

1. GitHub: Environment „produktion“ anlegen (Freigabe durch eine Person vor dem Lauf), eigene Secrets `SFTP_HOST`, `SFTP_USER`, `SFTP_PASSWORD`, `SFTP_KNOWN_HOSTS` und Variablen `WP_CONTENT_PATH`, `SITE_URL=https://www.eintrikot.de`. Keine Zugangsdaten im Chat.
2. Den Workflow `einspielen.yml` so erweitern, dass Entwicklung (automatisch) und Produktion (nur nach Freigabe) getrennt laufen. Ein Probelauf (`dry_run`) kommt zuerst.
3. Die Sicherung des Servers bleibt verschlüsselt (`BACKUP_PASSPHRASE`).

## Phase 7: Nach dem Umzug

- **Staging behalten:** Die Entwicklungsseite auf `myemmel.com` kann als Testseite bleiben (`noindex`, Testbalken, keine Mails). Neue Fassungen zuerst dort prüfen, dann nach Produktion einspielen.
- STRATO-Sicherung einmal testweise zurückspielen (nicht auf der Live-Seite).
- Nach zwei Wochen: DMARC von `quarantine`/`pct=50` auf 100 % anheben, HSTS auf ein Jahr, Kündigungsfrist des alten Anbieters prüfen.

## Offene Punkte

- Wer ist Vertragsinhaber, und welche Zahlungsart wird hinterlegt? (V)
- Welcher Auth-Code gilt bis wann? (B)
- Läuft auf dem alten Server (188.40.29.79) noch etwas, das erhalten bleiben muss? Er antwortet derzeit mit 403. Vor dem Abschalten Dateien sichern (Frage an den Dienstleister). (B)
- Lässt STRATO die DNS-Zone vor dem Abschluss des Transfers bearbeiten? Screenshot der Maske vor dem Auftrag. (B)
