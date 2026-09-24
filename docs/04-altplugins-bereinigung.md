# Altplugins und Altseiten bereinigen – Checkliste

Stand: 23.09.2026. Gilt für die Entwicklungsinstallation http://eintrikot.myemmel.com. Alles passiert im WordPress-Backend, nicht im Repository. eintrikot.de bleibt unberührt.

## Befund (von außen geprüft)

Auf jeder öffentlichen Seite geladen, obwohl das Portal sie nicht nutzt:

| Plugin | Wirkung auf der Startseite | Bemerkung |
|---|---|---|
| Ultimate Member | ca. 25 Skripte, Font Awesome (272 KB), jQuery UI, Select2 | Stellt **öffentlich** `/register/`, `/login/`, `/account/`, `/members/`, `/user/`, `/password-reset/`, `/logout/` bereit |
| The Events Calendar | eigenes Archiv `/events/` („0 Veranstaltungen gefunden") | Portal nutzt eigenen Kalender (`et_calendar`) |
| BSK PDF Manager Pro | CSS + JS auf jeder Seite | Dokumentenablage ist im Portal noch Platzhalter |
| STRATO Assistant | – | Hoster-Hilfsplugin, entbehrlich |
| Site Kit by Google | DNS-Prefetch zu googletagmanager.com | Tracking ohne Einwilligung ist datenschutzrechtlich kritisch |

Zusätzlich öffentlich: `/sample-page/`, `/welcome-to-http-eintrikot-myemmel-com/`, Beitrag „Hello world!".

**Dringend:** `/register/` ist ein offenes Registrierungsformular. Jede Person kann sich ein Konto anlegen.

## Reihenfolge

1. **Ist-Stand sichern.** Plugins → Liste als Screenshot. Einstellungen → Allgemein: „Jeder kann sich registrieren" und „Standardrolle" notieren.
2. **Registrierung schließen.** Einstellungen → Allgemein → „Jeder kann sich registrieren" **aus**, Standardrolle „Abonnent". Unter Benutzer prüfen, ob sich bereits fremde Konten angelegt haben.
3. **Ultimate Member: Datenlöschung verhindern.** Ultimate Member → Einstellungen → Allgemein/Sonstiges: Option „Daten bei Deinstallation löschen" muss **aus** sein. Erst dann weiter.
4. **Deaktivieren, nicht löschen.** Nacheinander deaktivieren: Ultimate Member, The Events Calendar, BSK PDF Manager Pro, STRATO Assistant, Site Kit. Nach jedem Schritt prüfen:
   - Startseite lädt, Menü funktioniert (auch mobil).
   - `/community-portal/` → Anmeldung mit Testkonto, Profil speichern, Mitgliederverzeichnis öffnen.
   - „Passwort vergessen?" führt auf die WordPress-Seite, nicht auf `/password-reset/`.
   - Abmelden funktioniert.
5. **Altseiten in den Papierkorb** (nicht endgültig löschen): login, register, account, user, members, password-reset, logout, sample-page, welcome-to-…; Beitrag „Hello world!".
6. **Alte Vorlagenteile entfernen:** Design → Editor → Muster → Vorlagenteile: „Header" und „Footer" (die alten, in der Datenbank gespeicherten) löschen. Das Theme nutzt nur noch „Kopfzeile" und „Fußzeile". Nach dem Deaktivieren von The Events Calendar auch dessen Vorlagen „archive-events" und „single-event".
7. **Permalinks neu speichern.** Einstellungen → Permalinks → „Änderungen speichern" (ohne Änderung).
8. **Eine Woche beobachten**, dann deaktivierte Plugins löschen. Vor dem Löschen von Ultimate Member Schritt 3 erneut prüfen.

## Erwartetes Ergebnis

Startseite vorher: 75 Anfragen, ca. 1,7 MB. Nach Bereinigung: grob 15–20 Anfragen und unter 300 KB. Nachmessen in Chrome → Entwicklertools → Netzwerk.

## Offene Entscheidung

Analyse/Tracking: Falls Besucherstatistiken gewünscht sind, eine einwilligungsfreie Lösung (z. B. serverseitige Statistik von STRATO oder ein cookieloses Tool) statt Google-Tag-Manager wählen. Bis dahin kein Tracking.
