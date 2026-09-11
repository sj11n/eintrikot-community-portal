# Community-Umfang und Vereinfachung

Neue Nutzerentscheidungen ersetzen frühere Beschränkungen, insbesondere den Ausschluss des Mitgliederverzeichnisses.

## Verbindliche Regeln

- Zielgruppe: aktuelle Jugend-, Damen- und Herren-Nationalspieler sowie Ehemalige. Keine auf Ehemalige begrenzte Ansprache.
- Band darf niemals mit sichtbarem abgeschnittenem Ende innerhalb seines sichtbaren Blocks stehen. Keine verschobenen Maskenrechtecke innerhalb gleichfarbiger Flächen. Band sparsam einsetzen; Hero und abgegrenzte Bildflächen. In Portalformularen hat Funktion Vorrang.
- Bestätigte Kennzahlen: 201 Mitglieder, 12.750 EUR Spendenaufkommen, 3 geförderte Projekte, 5 Generationen. Durch berechtigte Administration editierbar, Änderungen protokollieren. Spendenaufkommen nicht ohne Beleg als Fördervolumen bezeichnen.
- Menübegriff Engagement ersetzt Wirkung.
- Lebensphasen mit inhaltlichen Bezeichnungen statt Nummern; Engagement mit flachen SVG-Icons.
- Vereinsdarstellung getrennt: Der Verein (Zweck, Tätigkeiten, Finanzierung durch Beiträge, Vision/Mission, Trikotmodell, Unterstützung) und Menschen (Vorstand/Beirat). Kein öffentliches Mitgliederverzeichnis.
- Testimonials brauchen freigegebene Originalzitate und Porträts. Derzeit ausdrücklich markierte Gestaltungsplätze; keine Namen/Zitate erfunden.

## Einfaches Portal

Start fokussiert auf Mitglieder entdecken, Neuigkeiten und nächste Begegnung. Navigation: Start, Mitglieder, Neuigkeiten, Termine, Mein Profil, Mehr. Auf Mobilgeräten Start/Mitglieder/Termine/Profil unten; Mehr im Kopf. Service-Anfragen, Dokumente und Vorstandskontakt unter Mehr.

Mitglieder: eine Suchzeile und ein ausklappbarer Filterbereich. Suche nach Name/Verein/Beruf; Filter Team, Altersklasse, Trikotphase, Verein, Ausbildung/Beruf, Interessen. Nur für angemeldete berechtigte Mitglieder. Private Angaben dürfen auch indirekt nicht durch Treffer, Filteroptionen oder Zähler offengelegt werden. Keine öffentlichen Profil-URLs mit Inhalten. Sichtbarkeit der eigenen Karte bei Aktivierung ausdrücklich festlegen; vollständige administrative Mitgliedsliste separat.

## Redaktion und Administration

Newsletter ist ein eigener Arbeitsbereich für Redaktion, Vorstand und Administration. Normale Mitglieder sehen nur ihre Newsletter-Präferenz im Profil. Ausgabenfreigabe bleibt dem Vorstand vorbehalten. Kombinierte Rollen später über einzelne Capabilities statt breiter pauschaler Rechte abbilden.

Administration soll sämtliche zulässigen Portalprofile und Inhalte ändern können. Korrekturen benötigen Akteur, Zeitpunkt, betroffenen Eintrag, Feld und nachvollziehbare Änderung; bei Profileingriffen Änderungsgrund. Keine Passwörter, Tokens oder unnötigen persönlichen Angaben ins Log aufnehmen. Technische Adminrechte erlauben nicht automatisch eine Ausweitung der Profilfreigaben.

WordPress-native Protokollierung bevorzugt. Standard-Activity-Logs können Profiländerungen erfassen, aber Vollständigkeit für Ultimate-Member-Metafelder muss getestet oder durch eigene Hooks ergänzt werden. Log-Oberfläche nur lesbar; eine WordPress-Datenbank ist für technische Betreiber nicht absolut unveränderbar. Bei Bedarf separate zugriffsbeschränkte Sicherung/Export, keine Zusage manipulationssicherer Drive-Dateien. Aufbewahrung, Löschung und Zugriffsberechtigung festlegen.

## Aktueller Umsetzungsstand

Alles weiterhin lokale Gestaltungsvorschau, keine produktive Rechteprüfung. Rollenumschalter nur in der ausdrücklich markierten Vorschauleiste. Sechs synthetische Profile, Filter, Kennzahleneditor und ein Beispiel für Admin-Profilkorrektur funktionieren im Browser-Arbeitsspeicher. Profilkorrektur und Kennzahlenänderung erzeugen lesbare Sitzungsprotokolle. Noch keine Backend-Persistenz, echte Anmeldung oder MailPoet/Drive-Anbindung. Keinerlei WordPress-Produktionsänderung.

Geprüft: JS-Syntax; Suchbegriff Nord liefert 2 Testprofile, kombiniert mit U18 genau 1. Admin-Änderung 201→202 und Testverein Nord→Testverein Ost erzeugt Log mit Zeit, vorher/nachher und Grund. Testwerte betreffen nur den separaten QA-Tab. Website und vereinfachter Portalstart bei 320 px ohne horizontalen Überlauf; Desktop-Hero geprüft. Offene vollständige Rollen-/REST-/Datei-Sicherheitstests folgen erst in WordPress.

## Zusätzliche Best Practices

- Jugendprofile: datensparsame Vorgaben, keine öffentlichen Kontaktdaten oder genauen Altersangaben; Einwilligungs-/Vertretungsprozess für Minderjährige fachlich prüfen.
- Verzeichnisfreigaben verständlich erklären; Sichtbarkeitsvorschau für das eigene Profil.
- Falsche Angaben/unerwünschte Kontakte an Vorstand melden können; kein offener Chat erforderlich.
- Mitgliedszugang nach Austritt sperren und Einladungen erneuern können; Mitgliedschaft bleibt in MeinVerein führend.
- Profiländerungen durch Administration für betroffene Mitglieder nachvollziehbar machen.
- Redaktionelle Verantwortliche, Aktualisierungsdatum der Kennzahlen und klare Inhaltsfreigaben festlegen.
- Mehrfaktor-Anmeldung für privilegierte Konten, Wiederherstellungstest und barrierearme Tastaturbedienung vor Go-live.

Quellen: https://docs.ultimatemember.com/article/1513-member-directories-2-1-0 ; https://wordpress.org/plugins/aryo-activity-log/

Bildkonzept mit eingebautem Imagegen: privates Mitgliederverzeichnis, Montserrat/Weiß/Hellblau, kompakte Suche, ausklappbare Filter, reine synthetische Profile, keine öffentlichen Daten. Die spätere Nutzerkorrektur zur vereinfachten Navigation ist im Code umgesetzt; das Bild zeigt noch den vorherigen Navigationsumfang.
