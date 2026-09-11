# EINTRIKOT Community-Portal

Eigenständiges Vorhaben: öffentliche Website und geschütztes Mitgliederportal in einem WordPress-System auf deutschem PHP-Shared-Hosting.

## Klare Abgrenzung

- Dieses Projekt: WordPress-Community-Portal mit Custom Theme, Ultimate Member und MailPoet.
- Bisherige App: verbleibt im bisherigen Projektordner; ausschließlich mögliche Referenz, keine technische Grundlage dieses Projekts.
- Tippspiel: eigenständige Anwendung, verbleibt unverändert im bisherigen Projektordner und gehört nicht zum Umfang.
- WISO MeinVerein: externes führendes Verwaltungssystem für Mitgliedschaft, Beiträge, SEPA, Spenden, Buchhaltung und rechtlich relevante Daten.

Keine Node-, Vercel- oder Supabase-Abhängigkeit in Produktion. Keine Zugangsdaten, Datenbanken oder Mitgliederdaten aus dem Altprojekt übernommen.

## Maßgeblicher aktueller Stand

Siehe docs/03-aktueller-projektstand.md für die späteren Entscheidungen zu bestehender Installation, HTTP-Testbetrieb, Migration und integriertem Newsletter-Opt-out. Diese aktualisieren die unten dokumentierte ursprüngliche Planung.

## Grundlagen und Status

- docs/01-originalbriefing.txt: verbindliches Nutzerbriefing.
- docs/02-bestandsaufnahme-altprojekt.md: vorläufige Prüfung des alten Projektordners, keine Prüfung einer WordPress-Installation. Die darin genannten Quellpfade beziehen sich auf das Altprojekt.
- assets/eintrikot-logo-blau.svg: unveränderte Kopie des vorhandenen Logos, gegen das Brandbook zu prüfen.

Stand: 11.09.2026. Projekt getrennt angelegt. Noch keine Implementierung, Installation oder Produktionsänderung.

## Nächste Schritte

1. Tatsächliches WordPress-System und Hosting prüfen; KEEP / ADAPT / DEFER / REMOVE ergänzen.
2. Brandbook prüfen und vollständige visuelle Konzepte für Website, Dashboard, Profil und Newsletter sowie benötigte Zustände erstellen.
3. Ausdrückliche Designfreigabe des Nutzers abwarten.
4. Backup und isoliertes Staging einrichten, anschließend abschnittsweise implementieren und testen.

Offen: WordPress-URL / Systembericht bzw. Staging-Zugang, Hostingtarif und originales Brandbook. Wenn noch kein WordPress existiert, dies als Neuaufbau planen.
