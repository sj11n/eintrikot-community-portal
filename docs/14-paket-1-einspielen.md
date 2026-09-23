# Paket 1 einspielen und prüfen

Branch `refactor/code-formatierung`, Theme und Plugin 0.7.0. Nur Entwicklungsinstallation.

## Einspielen

1. Theme und Plugin wie gewohnt aktualisieren (Deploy aus GitHub bzw. Upload).
2. **Community-Aufbau → Aktualisierungen:**
   - „Seite ‚Mitglied werden' neu aufbauen" → Übernehmen. Ersetzt den Inhalt nur, wenn die Seite noch den alten Platzhaltertext enthält; sonst bleibt sie unverändert und die neue Fassung steht im Editor als Vorlage „EINTRIKOT – beitritt" bereit.
   - „Schreibweise EINTRIKOT in der Vision 2030" → Übernehmen.
   Beides erzeugt Revisionen und lässt sich unter Seiten → Revisionen zurücknehmen.
3. **Community-Aufbau → Kennzahlen:** „Stand der Kennzahlen" setzen (z. B. September 2026) und speichern.
4. **Community-Aufbau → Beitritt:** leer lassen, bis der MeinVerein-Link feststeht. Ohne Link erscheint kein Beitritts-Button.

## Prüfen

- Startseite am Handy: Hero kürzer, Band unter den Buttons, Kennzahlen im ersten Bildschirm. Desktop unverändert.
- Kennzahlen: drei Kacheln und „Stand: …". „Länderspiele" erscheint automatisch, sobald ≥ 60 % der Portalprofile ihre Länderspiele für Mitglieder freigeben. Stand im Backend unter Kennzahlen einsehbar.
- Vision 2030 linksbündig, „EINTRIKOT".
- Mitglied werden: Kriterien, Beitrag, freiwillige Jahresspende, Ablauf, kein Button.
- Linkvorschau: Startseiten-URL in WhatsApp an sich selbst schicken oder im LinkedIn Post Inspector prüfen. Vorschaubild `assets/og-image.jpg`.

## Zurücknehmen

Seiteninhalte über Revisionen. Code: Commit `Paket 1` auf dem Branch zurücksetzen und neu einspielen.
