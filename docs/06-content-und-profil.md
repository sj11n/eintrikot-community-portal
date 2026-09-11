# Verbindliche Trennung für WordPress

Die aktuelle HTML/JS-Vorschau ist ein Interaktionsentwurf, noch kein WordPress-Theme.

- Theme: Brandfarben, Schrift, Abstände, Band, responsive Darstellung und Vorlagen.
- WordPress-Editor: redaktionelle Seiten, Texte, News, Vorstand, Beirat, freigegebene Testimonials. Wiederverwendbare Blockmuster mit editierbaren Inhalten; Layoutschutz nur dort, wo nötig. Keine fest eingebauten Texte für redaktionelle Seiten im Theme.
- Administrativ pflegbare Kennzahlen: Mitglieder, Spenden, Projekte, Generationen.
- Separates Portal-Plugin: Profilfelder, Rollen, Sichtbarkeit, Mitgliederverzeichnis, Service-Anfragen und Änderungsprotokoll. Themewechsel darf diese Daten und Abläufe nicht entfernen.
- Rollen: Redaktion pflegt freigegebene Inhalte, Vorstand verantwortet Freigaben, Administration verwaltet technische Einstellungen und berechtigte Korrekturen. Revisionen für Inhalte, separates Änderungsprotokoll für Profilkorrekturen.

## Profil und Standort
Geburtsdatum privat speichern, daraus das Alter dynamisch berechnen. Niemals das Geburtsdatum im Mitgliederverzeichnis oder in öffentlichen API-Antworten ausliefern. Alter separat freigebbar. Wohnort und Region optional mit eigener Sichtbarkeit. Freigegebener Wohnort erscheint auch in der Mitgliederübersicht. Keine Privatanschrift notwendig. Keine echten Daten aus dem übermittelten Altprofil in der Vorschau verwenden.

Hockey-Lebenslauf: Rolle, Organisation, Altersklasse, Zeitraum; Länderspiele separat numerisch. Beruf, Ausbildung, Interessen und Möglichkeiten zur Unterstützung ergänzen das freiwillige Profil. Die Vorschau bewahrt Eingaben ausschließlich im Sitzungsspeicher der Seite auf.

## Jährlicher freiwilliger Förderbeitrag
Im Profil unverbindliches Interesse abfragen. Separater Service mit 50/100/150 Euro oder individuellem Jahresbetrag, gewünschtem Beginn und ausdrücklicher Bestätigung. Anfrage mit Zeitstempel, Mitglied, Betrag, Beginn und Status intern speichern; Zugriff auf zuständigen Vorstand/Schatzmeisterin begrenzen. Erst deren Prüfung und manuelle Übernahme in MeinVerein machen eine Änderung verbindlich. Keine automatische Übertragung, keine zweite Beitragsbuchhaltung. Vorschau demonstriert nur lokale Vormerkung; Backend, Bearbeitung und dauerhafte Protokollierung folgen in WordPress.
