# Verbindliche Entscheidungen

Zusammenfassung der Festlegungen von Björn Emmerling (September 2026). Ergänzt das Originalbriefing (docs/01). Bei Widerspruch gilt dieses Dokument. Die ausführlichen Einzeldokumente bis 0.11 liegen in der Git-Historie.

## Systeme und Abgrenzung

- MeinVerein bleibt führend für Mitgliedschaft, Beiträge, SEPA, Spenden und Verwaltung. Das Portal übernimmt nichts automatisch; Änderungen werden dort von Hand übernommen.
- Tippspiel und bisherige Next.js-App sind getrennte Projekte und bleiben unverändert.
- Produktion: WordPress auf STRATO-Shared-Hosting. Kein Node-, Vercel-, Supabase- oder Headless-System.
- Keine Bank- oder Beitragsdaten, keine vollständige Mitgliederdatenbank, keine Zugangsdaten im Repository. Das Repository ist öffentlich lesbar.
- Echte Mitgliederdaten und Einladungen erst auf eintrikot.de mit HTTPS.
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
- Kennzahlen im Backend pflegbar, mit Stand-Datum. „Länderspiele" ist eine automatische Summe der für Mitglieder freigegebenen Angaben und erscheint erst ab 60 % Profilen mit Angabe. Spendenaufkommen nicht als Fördervolumen bezeichnen.
- Mitglied werden: ab einem Länderspiel in einem DHB-Nationalteam, auch Jugend. Beitrag 50 € pro Jahr ab 32 Jahren; bis einschließlich 31 Jahre beitragsfrei. Zusätzlich eine freiwillige Jahresspende (50/100/150 € oder frei), klar als Spende gekennzeichnet. Ohne hinterlegten MeinVerein-Link kein Beitritts-Button.
- News sind normale WordPress-Beiträge; Startseite zeigt die drei neuesten.
- Kein Tracking ohne Einwilligung. Falls Statistik gewünscht: cookielose Lösung.

## Mitgliederportal

- Nur für angemeldete Mitglieder. Rollen: Mitglied, Redaktion, Vorstand, Administrator; Rechte werden serverseitig geprüft.
- Profil: alle Angaben freiwillig, Sichtbarkeit pro Abschnitt. Geburtsdatum bleibt privat, Alter optional. Private Angaben dürfen weder über Treffer noch Filter oder Zähler sichtbar werden.
- Unter 18: Beitritt nur mit Zustimmung eines Elternteils (Einholung über das Portal, siehe docs/07). Andere Mitglieder sehen nur Name, Team, Altersklasse und Region; kein Alter, Wohnort, keine weiteren Angaben, kein Geburtstag in den Vereinsinfos. Ins Verzeichnis nur, wenn die Eltern es erlauben. Ab 18 automatisch Erwachsenenregeln. Ohne Geburtsdatum gelten die Erwachsenenregeln.
- Kontakt: „Kontakt aufnehmen" per E-Mail über das Portal (Empfängeradresse verborgen, pro Profil abschaltbar) und freiwillig geteilte Kontaktfelder.
- Service-Anfragen werden gespeichert und manuell bearbeitet. Bankwechsel ohne IBAN im Portal. Förderbeitrag ist bis zur Übernahme in MeinVerein nur „angefragt".
- Benachrichtigungen an eine im Backend einstellbare Adresse; Mitglieder erhalten bei Rückmeldungen eine E-Mail. Versand nur über `wp_mail` und ein SMTP-Plugin mit STRATO-Postfach.
- Änderungsprotokoll 24 Monate, danach automatisch gelöscht. Sensible Felder nur als „geändert".
- Zugang: nie ein Passwort verschicken, nur einmalige, befristete Links. Zweite Stufe (Authenticator-App) für Vorstand und Admins bewusst zurückgestellt.

## Arbeitsweise

- Vor Umsetzung visuelles Konzept und ausdrückliche Designfreigabe.
- Produktion nie direkt bearbeiten: Änderungen über Pull Request, Prüfung und das Einspielen aus GitHub (docs/05). Björn startet und genehmigt jedes Einspielen.
- Testen in einem lokalen WordPress mit synthetischen Profilen, Desktop und Handy (390 und 320 px).
