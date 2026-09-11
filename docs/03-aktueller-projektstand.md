# Aktueller Projektstand

Stand: 11.09.2026. Neuere Nutzerentscheidungen ergänzen das Originalbriefing und gehen älteren Planungsannahmen vor.

## Systeme und Umgebung

- Separates Repository: https://github.com/sj11n/eintrikot-community-portal
- Tippspiel und bisherige Next.js-App bleiben eigenständig und unverändert.
- Bestehende WordPress-Installation: http://eintrikot.myemmel.com bei STRATO. Öffentlich erkanntes Theme: eintrikot-premium-theme. Backend und Plugins noch nicht geprüft.
- Diese Installation zunächst weiterverwenden; kein neuer Unterordner, keine weitere Datenbank und kein zusätzlicher SSL-Kauf jetzt erforderlich.
- Nutzer akzeptiert HTTP für synthetische Testprofile und separate Testpasswörter. Grundlegende Einladung, Passwortvergabe, Login und Reset dort prüfen; etwaige HTTPS-Voraussetzungen der Plugins prüfen. HTTPS-Abnahme vor Echtimport und Echtnutzung auf eintrikot.de.
- Vor Änderungen vollständiges Backup von Dateien und Datenbank. Bestehende Produktion nicht direkt ändern. Rolle und Nutzung der vorhandenen Installation vor Änderungen bestätigen; Designfreigabe bleibt Voraussetzung für Implementierung.

## Marke

Originalbrandbook vom 21.11.2025 wurde bereitgestellt und textlich gelesen, Bandmotiv visuell geprüft. Montserrat, Original-Logo und Band zwischen Generationen verwenden. Primärfarben Schwarz, Rot und Gold nicht zusammen einsetzen; Kombinationen mit Sekundärfarben bevorzugen. Keine dominierenden schwarzen Flächen.

## Migration und Aktivierung

CSV mit Altdaten kommt später. Zuerst Feldmapping, Dublettenprüfung und synthetischer Testimport; Echtimport ausschließlich auf endgültiger HTTPS-Installation. Keine Bank-, Beitrags- oder vollständigen Verwaltungsdaten übernehmen. Import wiederholbar mit stabiler Zuordnung, Vorschau und Fehlerprotokoll; Profilfelder zunächst privat.

Bestehende Mitgliedschaft prüfen, eindeutigen Benutzernamen vorgeben, nach menschlicher Versandfreigabe Plattformwechsel mitteilen und persönlichen befristeten Aktivierungslink senden. Mitglied setzt Passwort selbst und prüft Profil. Keine Passwörter versenden. Erneuerung abgelaufener Links und Versand-/Aktivierungsstatus vorsehen.

## Newsletter

Kein separater Nutzerprozess: Newsletter-Einstellung in Aktivierung und Profil integrieren. Gewünschtes Modell ist Opt-out, da der Verein von Informationsinteresse ausgeht. Automatische Vorbelegung als abonniert bleibt bis Prüfung bisheriger Beitritts-/Einwilligungstexte ein offener rechtlicher Punkt vor Echtversand. Abmeldung im Profil und in jeder Newsletter-Mail; Mitgliedschaft und Portalzugang bleiben bestehen. Notwendige Konto-/Vereinsmitteilungen gesondert behandeln.

## Noch offen

SFTP-Zugang, gesondertes WordPress-Administratorkonto und vorhandenes Backup-Verfahren. Keine Zugangsdaten im Chat oder Repository. GitHub speichert Code und Dokumentation, keine Mitgliederdaten, Datenbankexports oder Zugangsdaten. Designkonzepte können ohne Hostingzugriff erstellt werden; noch nicht freigegeben.
