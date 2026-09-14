# EINTRIKOT Community-Portal

Öffentliche Website und geschütztes Mitgliederportal für EINTRIKOT e. V., als eigenständiges WordPress-Projekt auf PHP-Shared-Hosting.

## Aktueller Stand

Theme 0.6.0 und Community Core 0.6.0 sind auf http://eintrikot.myemmel.com installiert. Öffentliche MVP-Inhalte wurden in native, bearbeitbare WordPress-Blöcke übernommen. Profile, Mitgliederverzeichnis, Vereinsinfos, Kalender, Service-Anfragen und Änderungsprotokoll liegen im eigenen Core-Plugin.

[Umsetzung, Pflege und offene Arbeiten](docs/11-wordpress-umsetzung.md) dokumentiert den tatsächlichen Stand. Die akzeptierte Gestaltungsvorschau bleibt unter `preview/` als Referenz erhalten.

## Abgrenzung

- Dieses Repository: WordPress-Community-Portal und sein Theme/Plugin.
- Tippspiel und bisherige App: andere Projekte, unverändert.
- MeinVerein: verbindliche Quelle für Mitgliedschaft, Beiträge, SEPA und Verwaltung. Portal-Anfragen werden manuell geprüft und übernommen.
- Kein Node-, Vercel-, Supabase- oder Headless-System in Produktion. Keine Zugangsdaten oder vollständige Altdatenbank im Repository.

## Pflege

Öffentliche Texte und Bilder unter **Seiten**, News unter **Beiträge**, interne Mitteilungen unter **Vereinsinfos**, wiederkehrende Anlässe im **EINTRIKOT Kalender**. Profilpflege erfolgt im Portal über Mitglieder bzw. das Konto-Menü. Die Website bleibt auf der Entwicklungsdomain; eintrikot.de wurde nicht verändert.
