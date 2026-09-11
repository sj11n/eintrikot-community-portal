# Design und lokale Vorschau

Die Gestaltungsrichtung wurde vom Nutzer bestätigt. Präzisierungen: klarer Einstieg „Das Netzwerk der Nationalteams.“ mit Erklärung des Hockey-Netzwerks, verschiedene Brandbook-Farbkombinationen und Rosa-Blau-Verlauf im Hero. Der bisherige Leitgedanke erscheint weiter unten.

## Vorschau

preview/index.html, style.css und app.js bilden einen lokalen klickbaren Gestaltungsprototyp. Start mit `python3 -m http.server 8091 --bind 127.0.0.1 --directory preview`. Keine WordPress-Installation oder fertige Authentifizierung. Keine externen Schriftabrufe; Montserrat liegt mit OFL-Lizenz lokal.

Website, Dashboard, Profil, Newsletter sowie Zugangs- und Serviceansichten sind navigierbar. Profilwerte bleiben ausschließlich im Arbeitsspeicher der Browserseite; keine Serverübertragung. Login/Reset/Anfrage-Buttons zeigen ausdrücklich Vorschauhinweise. Es werden keine E-Mails versendet.

Original-Logo als SVG übernommen, Sichtbereich angepasst und in erlaubtes Schwarz umgefärbt. Bandpfade aus Seite 17 des Brandbooks als Vektor übernommen; Masken erlauben farbliche Varianten. Hero: #F1D1DD → #C8E2EE, Band #DA525D. Lebensphasen: #CEDFBD/Schwarz. Newsletter: #F1D1DD/#DA525D.

## Prüfung und Grenzen

Im integrierten Browser geprüft: Desktop 1536×1024, mobile 320×780. Website, Dashboard, Profil und Newsletter ohne horizontalen Dokumentüberlauf bei 320 px. Mobiles Menü öffnet; Profil-Testwerte, Feldsichtbarkeit, zwei Hockey-Stationen und Newsletter-Abwahl bleiben nach interner Navigation erhalten. JavaScript-Syntaxprüfung erfolgreich, keine Konsolenfehler im Test.

Konzept und Desktop-Screenshot visuell verglichen: Claim und CTA-Texte stimmen; Montserrat lokal; Palette übernommen; Desktop-Zeilenumbruch korrigiert; Band aus Original statt generierter Nachzeichnung; Logo aus Original statt reiner Text-Ersatzmarke. Keine pixelgenaue finale Abnahme: Band-Ausschnitt und Größen, Headerhöhe sowie CTA-Größen weichen noch vom Bildkonzept ab. Zusätzliche Vorschauleiste ist bewusst vorhanden. Finale Feinarbeit und weitere Breakpoints stehen aus.

Fehlende Inhaltsnachweise: Kennzahlen, Förderprojektdetails, Testimonials, Partnerlogos, vollständiger News-Text, MeinVerein-Link, Social-Links und Rechtstexte. Dafür keine Daten erfunden. Die entsprechenden Bereiche sind in der Vorschau teils redaktionelle Hinweise; endgültige Komponenten folgen mit Originalmaterial.

Noch offen: WordPress-Theme und eigenes Funktionsplugin, Ultimate Member, echter Zugang/Reset/Einladung, Backend-Speicherung, MailPoet, Migration, Backup und Staging-Verifikation. Bestehende Installation nicht verändert.

## Bildkonzept

`design/hero-rosa-blau.png` wurde mit dem eingebauten Imagegen erstellt. Prompt: Bestehenden EINTRIKOT-Hero ausschließlich vom rosa Hintergrund auf sanften Verlauf von #F1D1DD links nach #C8E2EE rechts ändern; rote Bandgrafik #DA525D, schwarze Typografie, Navigation, deutsche Texte, Buttons und Abstände erhalten; keine zusätzlichen Formen oder Effekte.
