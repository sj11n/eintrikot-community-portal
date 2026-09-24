# Einspielen über GitHub

Theme und Plugin werden per GitHub Action auf die Entwicklungsinstallation kopiert. Die Zugangsdaten liegen ausschließlich als GitHub-Secrets im Repository, nie im Code oder im Chat.

## Was passiert

- **Prüfen** (`.github/workflows/pruefen.yml`) läuft bei jedem Push und Pull Request: PHP-Syntax unter PHP 8.1, Formatierung, Deploy-Skript `tools/deploy.sh`.
- **Einspielen** (`.github/workflows/einspielen.yml`) läuft von Hand (mit Probelauf) und – sobald die Variable `AUTO_DEPLOY` auf `true` steht – automatisch bei jedem Push auf `main`, der Theme oder Plugin ändert.
  1. Anmeldung und Zielpfad prüfen. Stimmt etwas nicht, bricht der Lauf ab, ohne etwas zu ändern.
  2. Den aktuellen Stand von Theme und Plugin herunterladen und 30 Tage als Artefakt „sicherung-…" aufbewahren.
  3. Neue Fassung in einen versteckten Nachbarordner hochladen (WordPress ignoriert Ordner mit Punkt).
  4. Ordner tauschen – die Seite wechselt in einem Schritt auf den neuen Stand.
  5. Startseite, „Mitglied werden" und Portal abrufen. Bei Fehler oder „kritischer Fehler" automatisch zurücktauschen; der Lauf wird rot.
- Nur die Ordner `themes/eintrikot-community` und `plugins/eintrikot-community-core` werden angefasst. Dateien, die nur auf dem Server liegen, sind danach dort nicht mehr vorhanden (die Sicherung enthält sie).
- Inhalte in der Datenbank (Seiten, Mitglieder, Einstellungen) werden nicht verändert.

## Einmalig einrichten

Auf github.com im Repository **Settings → Environments → New environment** „entwicklung" anlegen.

Empfohlen: dort unter **Required reviewers** dich selbst eintragen. Dann wartet jeder Lauf auf deinen Klick „Approve and deploy".

Im Environment „entwicklung":

| Art | Name | Inhalt |
|---|---|---|
| Secret | `SFTP_HOST` | SFTP-Server laut STRATO-Kundenbereich (bei STRATO in der Regel `ssh.strato.de`) |
| Secret | `SFTP_USER` | SFTP-Benutzer, am besten ein eigener nur für das Einspielen |
| Secret | `SFTP_PASSWORD` | zugehöriges Passwort |
| Secret | `SFTP_KNOWN_HOSTS` | empfohlen: Ausgabe von `ssh-keyscan ssh.strato.de` im Terminal |
| Variable | `WP_CONTENT_PATH` | Pfad zu `wp-content`, wie er nach der SFTP-Anmeldung aussieht, z. B. `/eintrikot/wp-content` |
| Variable | `SITE_URL` | `http://eintrikot.myemmel.com` |
| Variable | `DEPLOY_PROTOCOL` | optional, `sftp` (Standard) oder `ftps` |
| Variable | `AUTO_DEPLOY` | erst nach dem ersten erfolgreichen Lauf auf `true` setzen |

Den Pfad findest du mit einem SFTP-Programm (z. B. Cyberduck): anmelden, in den WordPress-Ordner wechseln, Pfad von `wp-content` kopieren.

## Erster Lauf

1. **Actions → Einspielen → Run workflow**, Branch `main`, „Nur Probelauf" angehakt. Das Protokoll zeigt, welche Dateien sich ändern würden.
2. Sieht das plausibel aus, denselben Lauf ohne Haken starten.
3. Läuft alles, `AUTO_DEPLOY` = `true` setzen. Ab dann spielt jeder Merge nach `main` automatisch ein.

## Zurücknehmen

- Automatisch: schlägt die Prüfung nach dem Tausch fehl, ist der alte Stand sofort wieder aktiv.
- Von Hand: den betreffenden Commit auf `main` mit „Revert" rückgängig machen; das löst einen neuen Lauf mit dem vorherigen Stand aus. Alternativ die Sicherung aus dem Artefakt per SFTP zurückspielen.

## Getestet

Das Skript wurde gegen einen lokalen SFTP-Server geprüft: falscher Pfad und falsches Passwort brechen ohne Änderung ab, Probelauf ändert nichts, ein Lauf spielt Theme und Plugin identisch ein und lädt die Sicherung, eine fehlschlagende Seitenprüfung tauscht automatisch zurück. Seit September 2026 laufen alle Einspielungen auf STRATO über diesen Weg.
