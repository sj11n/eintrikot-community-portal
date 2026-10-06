# Einspielen über GitHub

Theme und Plugin werden per GitHub Action auf die Entwicklungsinstallation kopiert. Die Zugangsdaten liegen ausschließlich als GitHub-Secrets im Repository, nie im Code oder im Chat.

## Was passiert

- **Prüfen** (`.github/workflows/pruefen.yml`) läuft bei jedem Push und Pull Request: PHP-Syntax unter PHP 8.1, Sicherheitsprüfung (PHPCS), statische Analyse (PHPStan), Regeltests in einem lokalen WordPress, Formatierung und ShellCheck für die Skripte unter `tools/`. Einspielen startet erst, wenn alles grün ist.
- **Einspielen** (`.github/workflows/einspielen.yml`) läuft automatisch bei jedem Push auf `main`, der Theme, Plugin, `tools/deploy.sh` oder die Workflow-Datei ändert, und von Hand (mit Probelauf). Es startet erst, wenn **Prüfen** grün ist. Eine zweite Freigabe auf GitHub gibt es nicht: die Freigabe ist der Merge, den Björn im Chat gibt.
  1. Anmeldung und Zielpfad prüfen. Stimmt etwas nicht, bricht der Lauf ab, ohne etwas zu ändern.
  2. Den aktuellen Stand von Theme und Plugin herunterladen. Als Artefakt „sicherung-…" (30 Tage) wird er nur **verschlüsselt** abgelegt (siehe „Sicherung"), weil Artefakte eines öffentlichen Repositories für jeden mit GitHub-Konto abrufbar sind.
  3. Neue Fassung in einen versteckten Nachbarordner hochladen (WordPress ignoriert Ordner mit Punkt).
  4. Ordner tauschen – die Seite wechselt in einem Schritt auf den neuen Stand.
  5. Startseite, „Mitglied werden" und Portal abrufen. Bei Fehler oder „kritischer Fehler" automatisch zurücktauschen; der Lauf wird rot.
- Nur die Ordner `themes/eintrikot-community` und `plugins/eintrikot-community-core` werden angefasst. Dateien, die nur auf dem Server liegen, sind danach dort nicht mehr vorhanden (die Sicherung enthält sie).
- Inhalte in der Datenbank (Seiten, Mitglieder, Einstellungen) werden nicht verändert.

## Einmalig einrichten

Auf github.com im Repository **Settings → Environments → New environment** „entwicklung" anlegen.

Required reviewers sind bewusst **nicht** gesetzt (Ein-Personen-Projekt, Freigabe im Chat). Die Regel **Deployment branches** (nur `main`) bleibt.

Im Environment „entwicklung":

| Art | Name | Inhalt |
|---|---|---|
| Secret | `SFTP_HOST` | SFTP-Server laut STRATO-Kundenbereich (bei STRATO in der Regel `ssh.strato.de`) |
| Secret | `SFTP_USER` | SFTP-Benutzer, am besten ein eigener nur für das Einspielen |
| Secret | `SFTP_PASSWORD` | zugehöriges Passwort |
| Secret | `SFTP_KNOWN_HOSTS` | **Pflicht**: Ausgabe von `ssh-keyscan -t ed25519 ssh.strato.de` im Terminal. Fehlt sie, bricht der Lauf ab, ohne etwas zu verändern. |
| Secret | `BACKUP_PASSPHRASE` | Passwort für die verschlüsselte Sicherung (`gh secret set BACKUP_PASSPHRASE`). Fehlt es, wird keine Sicherung als Artefakt abgelegt; der Rücktausch im Lauf und der Git-Verlauf bleiben. |
| Variable | `WP_CONTENT_PATH` | Pfad zu `wp-content`, wie er nach der SFTP-Anmeldung aussieht, z. B. `/eintrikot/wp-content` |
| Variable | `SITE_URL` | `http://eintrikot.myemmel.com` |
| Variable | `DEPLOY_PROTOCOL` | optional, `sftp` (Standard) oder `ftps` |

Den Pfad findest du mit einem SFTP-Programm (z. B. Cyberduck): anmelden, in den WordPress-Ordner wechseln, Pfad von `wp-content` kopieren.

## Erster Lauf

1. **Actions → Einspielen → Run workflow**, Branch `main`, „Nur Probelauf" angehakt. Das Protokoll zeigt, welche Dateien sich ändern würden.
2. Sieht das plausibel aus, denselben Lauf ohne Haken starten.
3. Ab dann spielt jeder Merge nach `main`, der Plugin oder Theme ändert, automatisch ein. (Die frühere Variable `AUTO_DEPLOY` wird nicht mehr gebraucht; sie stand im Environment und war dort für die Bedingung unsichtbar.)

## Zurücknehmen

- Automatisch: schlägt die Prüfung nach dem Tausch fehl, ist der alte Stand sofort wieder aktiv.
- Von Hand: den betreffenden Commit auf `main` mit „Revert" rückgängig machen; das löst einen neuen Lauf mit dem vorherigen Stand aus. Alternativ die Sicherung aus dem Artefakt per SFTP zurückspielen.

## Getestet

Das Skript wurde gegen einen lokalen SFTP-Server geprüft: falscher Pfad und falsches Passwort brechen ohne Änderung ab, Probelauf ändert nichts, ein Lauf spielt Theme und Plugin identisch ein und lädt die Sicherung, eine fehlschlagende Seitenprüfung tauscht automatisch zurück. Seit September 2026 laufen alle Einspielungen auf STRATO über diesen Weg.

## Sicherung

Die Sicherung wird mit AES-256 verschlüsselt (`gpg --symmetric`) und als `sicherung-<Laufnummer>.tar.gz.gpg` 30 Tage aufbewahrt. Entschlüsseln:

```bash
gpg --decrypt sicherung-123.tar.gz.gpg | tar -xz
```

Das Passwort bewahrst du in deinem Passwortmanager auf, nicht nur als GitHub-Secret: GitHub zeigt gespeicherte Secrets nicht mehr an.

## Regeltests und lokales WordPress

`tools/lokal-test.sh` baut ein isoliertes WordPress mit SQLite unter `.lokal/` (nicht im Repository). Es verschickt keine Mails und enthält nur Testkonten.

```bash
tools/lokal-test.sh test    # Regeltests (Altersgrenze, Sichtbarkeit, Zustimmung, Login-Sperre)
tools/lokal-test.sh serve   # Seite zum Ausprobieren unter http://127.0.0.1:8899
```

Die Tests liegen in `tests/run.php`. Wer eine Regel ändert (z. B. welche Angaben Jugendliche zeigen), ändert zuerst den Test.
