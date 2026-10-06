# Verbindliche Entscheidungen

Zusammenfassung der Festlegungen von Björn Emmerling (September 2026). Ergänzt das Originalbriefing (docs/01). Bei Widerspruch gilt dieses Dokument. Die ausführlichen Einzeldokumente bis 0.11 liegen in der Git-Historie.

## Systeme und Abgrenzung

- MeinVerein bleibt führend für Mitgliedschaft, Beiträge, SEPA, Spenden und Verwaltung. Das Portal übernimmt nichts automatisch; Änderungen werden dort von Hand übernommen.
- Tippspiel und bisherige Next.js-App sind getrennte Projekte und bleiben unverändert.
- Produktion: WordPress auf STRATO-Shared-Hosting. Kein Node-, Vercel-, Supabase- oder Headless-System.
- Keine Bankdaten, keine vollständige Mitgliederdatenbank, keine Zugangsdaten im Repository. Das Repository ist öffentlich lesbar. Im Portal stehen nur die Beträge (Jahresbeitrag nach Regel, Jahresspende laut MeinVerein), sichtbar für Mitglied und Verwaltung.
- Echte Mitgliederdaten und Einladungen erst auf eintrikot.de mit HTTPS.
- Umstieg von NDAlumni: Bestandsmitglieder durchlaufen keinen neuen Beitritt. Sie bekommen eine Umstiegsmail mit Zugang und der Bitte, ihr Profil zu prüfen – erst nach dem Umzug auf eintrikot.de (technisch gesperrt bis dahin). Die Migration ist so vollständig wie möglich: Profilangaben aus NDAlumni werden privat übernommen, Newsletter nur per neuem Opt-in, Profilbilder vorerst leer. Gründungsmitglieder (Nr. 1–7) haben das Eintrittsdatum 23.09.2025, niemand ein früheres; kein Eintritt nach dem SEPA-Mandatsdatum. Ab wann neue Importe den vollen Ablauf mit Urkunde bekommen, legt Björn fest.
- Der Mitgliedsantrag läuft über den digitalen Antrag von MeinVerein (Name, E-Mail, Geburtsdatum, ggf. IBAN). Alles Weitere pflegt das Mitglied im Portal; das Portal ist der einzige Kontaktpunkt für Mitglieder.

## Marke

- Originalbrandbook vom 21.11.2025: Montserrat (lokal eingebunden), Original-Logo, Band zwischen Generationen.
- Farben: Hellblau #C8E2EE, Rosé #F1D1DD, Grün #CEDFBD, Gold #FFCC4E, Rot #DA525D. Schwarz, Rot und Gold nie zusammen; keine dominierenden schwarzen Flächen.
- Band nie mit sichtbar abgeschnittenem Ende innerhalb seines Blocks; sparsam einsetzen. In Portalformularen hat Funktion Vorrang.
- Keine erfundenen Kennzahlen, Zitate, Testimonials, Partner oder Projekte. Fehlende Inhalte bleiben als ehrliche Platzhalter.

## Öffentliche Website

- Zielgruppe: aktuelle Jugend-, Damen- und Herren-Nationalspieler sowie Ehemalige, Masters und Staff.
- Navigation: Der Verein, Engagement, News, Partner; Menschen (Vorstand/Beirat) über Der Verein. Kein öffentliches Mitgliederverzeichnis.
- Hero-Bezeichnungen „Danas" und „Honamas" bleiben unverändert. Vision und Mission im Wortlaut des Whitepapers 2.0 (Oktober 2025).
- Vision 2030 (nicht aus dem Whitepaper): „Bis 2030 ist EINTRIKOT ein starkes, selbsttragendes Netzwerk mit 500+ Mitgliedern, das alle deutschen Hockey-Nationalteams finanziell, strukturell und persönlich unterstützt." Footer immer „Das Netzwerk der Nationalteams."
- Mitglied werden können alle mit mindestens einem Länderspiel sowie Trainerinnen, Trainer und Staff der Nationalteams. Reihenfolge im Text: Jugend, Aktive, Masters und Alumni.
- Beirat: Andreas Arntzen, Wibke Weisel, Natascha Keller (Gründungsmitglieder, zuvor Beirat der DHB-Alumni-Familie); der Vorstand nimmt an den Beiratssitzungen teil.
- Spenden direkt: Spendenkonto und PayPal (paypal.me/eintrikot) auf „Unterstützen".
- „Mitglied werden"-Buttons beim Darüberfahren Gold (#FFCC4E); im Hero bleibt der Button weiß (kein Gold neben dem roten Band).
- Hero-Band steil im rechten Teil, läuft oben und unten aus dem Bild, nie über dem Text; kein sichtbares Bandende. Portal-Platzhalter ohne Foto bleiben grau, solange der Hockey-Abschnitt nicht freigegeben ist.
- Kennzahlen im Backend pflegbar, mit Stand-Datum. Mitglieder und „seit [Jahr] im Nationaltrikot" rechnet das Portal selbst (Konten im Verzeichnis, frühestes freigegebenes „Von"-Jahr der DHB-Stationen); ein eingetragener Wert ersetzt die Berechnung. „Länderspiele" ist eine automatische Summe der für Mitglieder freigegebenen Angaben und erscheint erst ab 60 % Profilen mit Angabe. Spendenaufkommen nicht als Fördervolumen bezeichnen.
- Mitglied werden: ab einem Länderspiel in einem DHB-Nationalteam, auch Jugend. Beitrag 50 € pro Jahr ab 32 Jahren; bis einschließlich 31 Jahre beitragsfrei. Zusätzlich eine freiwillige Jahresspende (50/100/150 € oder frei), klar als Spende gekennzeichnet. Ohne hinterlegten MeinVerein-Link kein Beitritts-Button.
- News sind normale WordPress-Beiträge; Startseite zeigt die drei neuesten.
- Kein Tracking ohne Einwilligung. Falls Statistik gewünscht: cookielose Lösung.

## Mitgliederportal

- Nur für angemeldete Mitglieder. Rollen: Mitglied, Redaktion, Vorstand, Administrator; Rechte werden serverseitig geprüft.
- **Aktuelles** (statt getrennter Bereiche Vereinsinfos und Termine; zwei Reiter, wenn es nicht reicht wird erweitert): Reiter „Vereinsinfos“ (Mitteilungen aus dem Verein) und „Termine“. Termine sind frei anlegbar mit Format (Treffen, EINTRIKOT unterwegs, Mitgliederversammlung, Netzwerkabend, Online, Sonstiges), Datum (auch mehrtägig), Uhrzeit, Ort, Treffpunkt, Ansprechperson, Link und Beschreibung. Nur für Mitglieder sichtbar. Bei Terminen mit Anmeldung melden sich Mitglieder mit „Für Veranstaltung anmelden“ an; die Teilnehmerliste („Wer ist dabei?“) sehen Mitglieder, Erwachsene mit Namen, Minderjährige und ausgeblendete Konten nur als Zahl. Anmeldungen werden 180 Tage nach dem Termin gelöscht. Geburtstage (nur freigegebene) stehen eingeklappt unter den Terminen. Das Jahresevent bekommt später eine eigene Seite mit Anmeldeformular auch für Nicht-Mitglieder und eigener Verwaltung (siehe docs/08).
- Profil: alle Angaben freiwillig, Sichtbarkeit pro Abschnitt. Trikotphase „Aktiv“ oder „Alumni“ (bis 0.16 „Aktuell“/„Ehemalig“, alte Werte werden automatisch so gelesen). Das Geburtsdatum sieht nur die Vereinsverwaltung (Beitrag, Altersprüfung). Das Mitglied selbst wählt im Profil, ob andere Mitglieder den Geburtstag sehen: niemand (Standard), Tag und Monat oder Tag, Monat und Jahr, jeweils im Profil und unter „Termine“; das Alter ist eine eigene Wahl. Die Wahl trifft nur das Mitglied, nie die Verwaltung (Einwilligung). Das frühere Häkchen „Geburtstag in den Terminen, ohne Jahr“ gilt als „Tag und Monat“. Private Angaben dürfen weder über Treffer noch Filter oder Zähler sichtbar werden.
- Unter 18: Beitritt nur mit Zustimmung eines Elternteils (Einholung über das Portal, siehe docs/07). Andere Mitglieder sehen nur Name, Team, Altersklasse und Region; kein Alter, Wohnort, keine weiteren Angaben, kein Geburtstag (auch nicht in „Termine“). Ins Verzeichnis nur, wenn die Eltern es erlauben. Ab 18 automatisch Erwachsenenregeln. Ohne Geburtsdatum gelten die Erwachsenenregeln.
- Kontakt: „Kontakt aufnehmen" per E-Mail über das Portal (Empfängeradresse verborgen, pro Profil abschaltbar) und freiwillig geteilte Kontaktfelder.
- Service-Anfragen werden gespeichert und manuell bearbeitet. Bankwechsel ohne IBAN im Portal. Förderbeitrag ist bis zur Übernahme in MeinVerein nur „angefragt".
- Benachrichtigungen an eine im Backend einstellbare Adresse; Mitglieder erhalten bei Rückmeldungen eine E-Mail. Versand nur über `wp_mail` und ein SMTP-Plugin mit STRATO-Postfach.
- Änderungsprotokoll 24 Monate, danach automatisch gelöscht. Sensible Felder nur als „geändert".
- Zugang: nie ein Passwort verschicken, nur einmalige, befristete Links. Zweite Stufe (Authenticator-App) für Vorstand und Admins bewusst zurückgestellt.

## Arbeitsweise

- Vor Umsetzung visuelles Konzept und ausdrückliche Designfreigabe.
- Produktion nie direkt bearbeiten: Änderungen über Pull Request, Prüfung und das Einspielen aus GitHub (docs/05). Björn gibt jede Änderung im Chat frei (Merge); danach spielt GitHub automatisch ein, prüft die Seite und tauscht bei einem Fehler zurück. Eine zweite Freigabe auf GitHub gibt es bewusst nicht mehr.
- Testen in einem lokalen WordPress mit synthetischen Profilen, Desktop und Handy (390 und 320 px).
