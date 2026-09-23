# Paket Portal-UX (0.9)

Umsetzung des Experten-Feedbacks vom 23.09.2026 und der Farbwünsche von Björn.

## Was sich ändert

- **Navigation:** Start · Mitglieder · Vereinsinfos · Termine · Service. Das eigene Profil, Redaktion und Verwaltung liegen im Konto-Menü (Avatar oben rechts). Auf dem Handy heißt „Vereinsinfos“ in der unteren Leiste „Infos“. Die alte Seite „Mehr“ leitet auf „Service“.
- **CI-Farben:** Jeder Bereich hat eine eigene Farbfläche im Seitenkopf und in der Navigation: Start Rosé-Blau-Verlauf mit Band, Mitglieder Hellblau, Vereinsinfos Rosé, Termine Grün, Service Gold, Verwaltung Hellblau, Redaktion Rosé. Formulare und Listen bleiben weiß. Die Seitenleiste ist hellblau hinterlegt. Schwarz, Rot und Gold erscheinen nie zusammen.
- **Kurze Titel** auf Funktionsseiten („Mitglieder“ statt einer langen Headline).
- **Startseite:** Suche direkt oben, Profilfortschritt, neueste Vereinsinfo, nächster Termin, offene Anfragen. Der Willkommenstext erscheint nur beim ersten Besuch.
- **Verzeichnis:** Aktive Suche und Filter als einzeln entfernbare Chips; Filter wirken sofort. Nach einem Profilbesuch führt „Zurück zur Suche“ zur gleichen Suche, zu den gleichen Filtern und zur besuchten Karte.
- **Technische Konten:** Automatisch erscheinen nur Konten mit EINTRIKOT-Rolle. Ein reines Administrator-Konto bleibt verborgen. In „Profil bearbeiten“ kann die Verwaltung das pro Konto auf „Immer anzeigen“ oder „Nicht anzeigen“ stellen. Die Länderspiel-Summe zählt dieselben Konten.
- **Avatare ohne Foto:** Initialen von Vor- und Nachname, Rosé bei Damen, Hellblau bei Herren. Ist der Hockey-Abschnitt nicht geteilt, bleibt der Avatar neutral grau, damit die Farbe nichts Privates verrät.
- **Service-Anfragen:** Verlauf „Eingegangen → In Prüfung → Erledigt“ mit Datum, wer sich kümmert, wie es weitergeht. Förderbeiträge zeigen deutlich „Angefragt – noch nicht verbindlich“ bzw. „Verbindlich übernommen“. Statuswechsel werden mit Datum gespeichert.
- **Profilfoto:** Bild anklicken, auswählen, Ausschnitt verschieben und vergrößern (Maus, Finger oder Tastatur). Ohne JavaScript bleibt das normale Dateifeld.
- **Kleine Zustände:** Fehler direkt am Feld, Eingaben bleiben erhalten, Hinweis „Nicht gespeicherte Änderungen“, hilfreiche leere Suchergebnisse, sichtbarer Fokus, reduzierte Animationen, auf dem Handy verschwindet beim Tippen die untere Leiste.

## Getestet

Lokal mit WordPress und synthetischen Testmitgliedern: Desktop 1280 px, Handy 390 und 320 px; Rollen Mitglied, Vorstand, Administrator; Foto-Zuschnitt, Speichern, Fehlerfall, Zurück zur Suche.

## Nach dem Einspielen prüfen

1. Als Administrator: Erscheint dein eigenes Konto im Verzeichnis? Falls es fehlen soll, passt es. Falls du dort stehen willst: Profil bearbeiten → Verwaltung → „Immer anzeigen“.
2. Startseite, Mitglieder, Service auf dem Handy durchklicken.
