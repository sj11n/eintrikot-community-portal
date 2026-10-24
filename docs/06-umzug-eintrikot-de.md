# Umzug auf eintrikot.de

Ziel: Die Website zieht von `http://eintrikot.myemmel.com` auf `https://eintrikot.de`, ohne dass Code angepasst werden muss.

## Wo die Adresse steckt

| Ort | Stand | Beim Umzug |
|---|---|---|
| Code (Theme, Plugin) | enthält keine Domain; alle Adressen werden zur Laufzeit von WordPress erzeugt | nichts |
| Vorlagen im Theme (`patterns/`) | nur relative Links wie `/mitglied-werden/` | nichts |
| Seiteninhalte in der Datenbank | nach „Interne Links domainunabhängig machen" relativ | nichts bzw. Rest per Suchen/Ersetzen |
| WordPress-Einstellungen (WordPress- und Website-Adresse) | `http://eintrikot.myemmel.com` | umstellen |
| Mediathek-Metadaten, Plugin-Einstellungen | können absolute Adressen enthalten | Suchen/Ersetzen |
| GitHub-Variable `SITE_URL` | Entwicklungsadresse | umstellen |

## Vorbereitung (jetzt, auf der Entwicklungsseite)

Community-Aufbau → Aktualisierungen → „Interne Links domainunabhängig machen" ausführen. Danach zeigen interne Links und Bilder in Seiten, Beiträgen, Navigation und Vorlagenteilen auf relative Adressen. Neue Inhalte aus Theme-Vorlagen sind von vornherein relativ.

## Ablauf am Umzugstag

1. **Sicherung:** Dateien und Datenbank sichern (STRATO-Backup bzw. „Einspielen"-Sicherung für Theme/Plugin zusätzlich).
2. **Domain verbinden:** Die Domain `eintrikot.de` ist noch beim alten Anbieter (derzeit DNS bei Hetzner, Stand 25.10.2026). Im STRATO-Kundenbereich `eintrikot.de` und `www.eintrikot.de` dem Webspace-Verzeichnis der Entwicklungsseite zuordnen. Danach beim DNS-Anbieter nur die Einträge für Website ändern: `@` und `www` (A, AAAA) auf die von STRATO angezeigten Adressen (Dev-Seite: 193.141.3.70 und 2a01:238:20a:202:1070::, bitte mit STRATO abgleichen). **MX-, SPF-, DKIM- und DMARC-Einträge (Google Workspace) unverändert lassen.** Ein Tag vorher die TTL auf 300 Sekunden senken. Zieht auch das DNS zu STRATO um, vorher alle bestehenden Einträge notieren und vollständig übernehmen. Ein Domain-Transfer (Auth-Code) ist davon getrennt und kommt erst danach.
3. **SSL-Zertifikat** für `eintrikot.de` im STRATO-Kundenbereich aktivieren (geht erst, wenn die DNS-Einträge auf STRATO zeigen) und warten, bis `https://eintrikot.de` ohne Warnung lädt.
4. **WordPress umstellen:** Einstellungen → Allgemein: WordPress-Adresse und Website-Adresse auf `https://eintrikot.de`. Danach neu anmelden.
5. **Reste ersetzen:** Mit einem Suchen-Ersetzen-Plugin (z. B. „Better Search Replace"), zuerst als Probelauf: `http://eintrikot.myemmel.com` → `https://eintrikot.de`, alle Tabellen. Solche Plugins berücksichtigen serialisierte Daten; nicht per SQL von Hand ersetzen.
6. **Permalinks neu speichern:** Einstellungen → Permalinks → Speichern.
7. **HTTPS erzwingen:** Weiterleitung von `http://` auf `https://` in STRATO aktivieren bzw. in `.htaccess`.
8. **Alte Adresse weiterleiten:** `eintrikot.myemmel.com` dauerhaft (301) auf `https://eintrikot.de` umleiten.
9. **GitHub:** Variable `SITE_URL` auf `https://eintrikot.de`; `WP_CONTENT_PATH` nur ändern, falls sich der Ordner ändert.
10. **Mail:** Das Portal versendet über Google Workspace (SMTP-Plugin, Postfach `@eintrikot.de` als Absender). SPF und DKIM liegen dafür bei Google beziehungsweise im DNS der Domain, nicht bei STRATO. Test-E-Mail an Gmail, Outlook und web.de.
11. **Prüfen:** Startseite, alle Menüpunkte, Anmeldung, Passwort vergessen, Profil speichern, Linkvorschau (LinkedIn Post Inspector), Browser-Konsole ohne „Mixed Content".

Erst nach Schritt 11 echte Mitgliederdaten importieren und Einladungen verschicken. Die vollständige Checkliste mit Prüfpunkten, Import und Einladungen steht in docs/09.

## Zusätzlich vor und nach dem Umzug (Red-Team-Prüfung 25.10.2026)

- **Hauptadresse festlegen:** `www.eintrikot.de` oder `eintrikot.de`; die jeweils andere per 301 darauf umleiten. Die Datenschutzerklärung nennt `www.eintrikot.de`.
- **Dateien löschen:** `readme.html` und `license.txt` im WordPress-Hauptordner (zeigen die Version). Nach WordPress-Updates erneut prüfen.
- **`.htaccess`:** `xmlrpc.php` sperren, zum Beispiel
  `<Files xmlrpc.php>` / `Require all denied` / `</Files>`.
- **HSTS** erst, wenn HTTPS stabil läuft: zuerst `Header always set Strict-Transport-Security "max-age=300"`, nach einer Woche ohne Probleme auf ein Jahr erhöhen. Kein `includeSubDomains` und kein Preload, solange Subdomains (Mail) nicht geprüft sind.
- **Suchmaschinen:** Einstellungen → Lesen → „Suchmaschinen davon abhalten …“ muss **aus** sein. Die alte Adresse leitet per 301 um, damit keine doppelten Inhalte entstehen.
- **Technischer Administrator:** Es darf keinen Benutzer mit Login `admin` geben (siehe `docs/09`).
