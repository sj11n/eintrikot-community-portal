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
2. **Domain verbinden:** Im STRATO-Kundenbereich `eintrikot.de` (und `www.eintrikot.de`) auf dasselbe Webspace-Verzeichnis zeigen lassen wie die Entwicklungsseite.
3. **SSL-Zertifikat** für `eintrikot.de` im STRATO-Kundenbereich aktivieren und warten, bis `https://eintrikot.de` ohne Warnung lädt.
4. **WordPress umstellen:** Einstellungen → Allgemein: WordPress-Adresse und Website-Adresse auf `https://eintrikot.de`. Danach neu anmelden.
5. **Reste ersetzen:** Mit einem Suchen-Ersetzen-Plugin (z. B. „Better Search Replace"), zuerst als Probelauf: `http://eintrikot.myemmel.com` → `https://eintrikot.de`, alle Tabellen. Solche Plugins berücksichtigen serialisierte Daten; nicht per SQL von Hand ersetzen.
6. **Permalinks neu speichern:** Einstellungen → Permalinks → Speichern.
7. **HTTPS erzwingen:** Weiterleitung von `http://` auf `https://` in STRATO aktivieren bzw. in `.htaccess`.
8. **Alte Adresse weiterleiten:** `eintrikot.myemmel.com` dauerhaft (301) auf `https://eintrikot.de` umleiten.
9. **GitHub:** Variable `SITE_URL` auf `https://eintrikot.de`; `WP_CONTENT_PATH` nur ändern, falls sich der Ordner ändert.
10. **Mail:** Absenderadresse und SMTP-Einstellungen auf die eintrikot.de-Domain prüfen (SPF/DKIM bei STRATO).
11. **Prüfen:** Startseite, alle Menüpunkte, Anmeldung, Passwort vergessen, Profil speichern, Linkvorschau (LinkedIn Post Inspector), Browser-Konsole ohne „Mixed Content".

Erst nach Schritt 11 echte Mitgliederdaten importieren und Einladungen verschicken (siehe docs/07).
