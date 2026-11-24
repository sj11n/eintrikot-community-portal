# Konzept: Automatischer Geburtstagsgruß per E-Mail

Stand: 24.11.2026. Status: **gebaut in Core 0.42.0** (`greeting.php`, Tests in `tests/run.php`). Konzept und Design von B freigegeben am 24.11.2026, Variante B „Festlich“, Mail-Entwurf: `docs/entwuerfe/geburtstagsmail.html`. Umgesetzt mit den Vorschlägen aus der Tabelle unten (nur aktivierte Konten, unter 18 mit Zustimmung der Eltern, Unterschrift „dein EINTRIKOT-Vorstand“). Eingespielt auf Produktion am 24.11.2026. Der Absatz in der Datenschutzerklärung steht seit Core 0.42.1 bereit (Aktualisierung „Datenschutz: Absatz zum Geburtstagsgruß“); V schaut drüber.

**Wer macht was:** B = Björn, V = Vorstand, C = Claude.

## Ziel

Jedes Mitglied bekommt am Morgen seines Geburtstags eine kurze, persönliche Glückwunsch-Mail von EINTRIKOT. Das läuft automatisch, ohne dass jemand daran denken oder klicken muss.

## Was gebaut wird

Ein kleines Modul im Core (`eintrikot-community-core/greeting.php`, kein eigenes Plugin). Im Portal: Verwaltung → Geburtstagsgrüße. Es nutzt nur, was es schon gibt: das Geburtsdatum aus dem Profil, den Mail-Rahmen aller EINTRIKOT-Mails (`mail_wrap`, Logo eingebettet, Hell- und Dunkelmodus) und den Versand über `wp_mail` (später SMTP über Google Workspace wie beim Newsletter).

## Wer bekommt den Gruß

Alle Konten, auf die alles Folgende zutrifft:

1. Mitgliedskonto mit Mitgliedsnummer. Technische Konten und Testkonten der Testphase bekommen nichts.
2. **Im Portal aktiviert** (`eintrikot_activated_at` gesetzt). *Entscheidung B, siehe unten.*
3. Gültiges Geburtsdatum im Profil, heute ist der Geburtstag.
4. Gültige, echte E-Mail-Adresse (gleiche Prüfung wie der E-Mail-Verteiler: keine Beispieladressen).
5. Die Verwaltung hat den Gruß für dieses Konto nicht ausgeschaltet (nur auf ausdrücklichen Wunsch, siehe „Abschalten“).
6. Unter 18: nur mit erteilter Zustimmung der Eltern, also nur wer ohnehin im Portal ist. *Entscheidung B, siehe unten.*

Es zählt nur das Geburtsdatum selbst. Die Einstellung „Wer sieht deinen Geburtstag?“ spielt keine Rolle, denn der Gruß geht nur an das Mitglied, niemand sonst sieht etwas.

## Zeitpunkt und Ablauf

- **Einmal am Tag** ab **8:00 Uhr** deutscher Zeit (Zeitzone der Website) gehen die Grüße an alle Mitglieder mit Geburtstag heute raus. Ein Durchlauf pro Tag reicht; stündlich wäre unnötig.
- Läuft der Durchlauf um 8 Uhr nicht an (WP-Cron startet nur bei einem Seitenaufruf oder durch den Zeitgeber, siehe unten), holt ihn der nächste Anstoß am selben Tag nach. Schlägt eine einzelne Mail fehl, wird sie am selben Tag noch höchstens zweimal versucht, im Abstand von einer Stunde.
- **Kein Nachholen** an einem späteren Tag: Ein Glückwunsch zwei Tage zu spät wirkt schlechter als keiner.
- **Kein Doppelversand:** Nach erfolgreichem Versand wird das Jahr am Konto vermerkt (`eintrikot_greeted_year`).
- **29. Februar:** In Jahren ohne Schalttag kommt der Gruß am 28. Februar.
- **Zeitgeber:** Das STRATO-Paket hat keine Cronjobs. Den Anstoß übernimmt ein Google-Apps-Script im Vereinskonto, das jede Minute `wp-cron.php` aufruft (Einrichtung in `docs/14`, Abschnitt C). Derselbe Zeitgeber treibt auch den Newsletter-Versand an. Fällt er aus, kommt der Gruß beim ersten Seitenaufruf nach 8 Uhr am selben Tag.
- Menge: bei rund 300 Mitgliedern im Schnitt weniger als eine Mail pro Tag. Für den Versand spielt das keine Rolle.

## Inhalt der Mail

Persönlich und kurz. Kein Alter, keine Zahlen, keine Werbung, kein Knopf zu einer Aktion. Mail-Entwurf mit zwei Gestaltungsvarianten in `docs/entwuerfe/geburtstagsmail.html`.

- **Betreff:** „Alles Gute zum Geburtstag, {Vorname}!“
- **Vorschautext:** „Heute ist dein Tag. Herzliche Grüße von EINTRIKOT.“
- **Text:**

  > Hallo {Vorname},
  >
  > heute hast du Geburtstag, und dazu gratulieren wir dir von Herzen! Wir wünschen dir ein gesundes, glückliches neues Lebensjahr und viele schöne Momente, auf dem Platz und daneben.
  >
  > Schön, dass du Teil von EINTRIKOT bist.
  >
  > Liebe Grüße
  > dein EINTRIKOT-Vorstand

- **Kein Hinweis zum Abschalten** in der Mail (Entscheidung B, 24.11.2026). Die Mail endet mit dem Gruß.
- Antworten gehen wie bei allen Mails an die Antwortadresse des Vereins. So kann sich ein Mitglied bedanken, und es landet bei euch.
- Der Text steht fest in `greeting.php`. Ein Eingabefeld in der Verwaltung kann später folgen, falls ihr ihn öfter ändern wollt.

## Abschalten

- **Mitglieder können den Gruß nicht selbst abschalten.** Kein Häkchen im Profil, kein Hinweis in der Mail (Entscheidung B, 24.11.2026).
- **Einzelnes Konto (nur Verwaltung):** Schreibt ein Mitglied, dass es keine Grüße möchte, setzt die Verwaltung beim Bearbeiten des Kontos ein Häkchen „Keine Geburtstagsgrüße“. Das Mitglied sieht dieses Feld nicht. Die Änderung geht ins Änderungsprotokoll. Damit lässt sich ein Widerspruch umsetzen, ohne dass es eine sichtbare Option gibt.
- **Verwaltung:** ein Hauptschalter „Geburtstagsgrüße verschicken“ (an/aus) für den ganzen Verein, standardmäßig **aus**, bis B ihn nach dem Test auf Staging anschaltet.

## Verwaltung

Im Portal unter **Verwaltung → Geburtstagsgrüße** (Vorstand und Administratoren):

- Hauptschalter an/aus.
- **Testmail an mich:** schickt den Gruß mit dem eigenen Vornamen an die eigene Adresse, Betreff mit „[TEST]“.
- **Nächste 7 Tage:** nur die Zahl der Grüße pro Tag („Mo 1, Di 0, Mi 2 …“), keine Namen. Wer Namen will, sieht die freigegebenen Geburtstage ohnehin unter Termine.
- **Letzter Durchlauf:** Zeitpunkt und Ergebnis („heute 2 verschickt, 0 Fehler“).

## Was gespeichert wird

- Am Konto: das Jahr des letzten Grußes und gegebenenfalls „Keine Geburtstagsgrüße“ (von der Verwaltung gesetzt). Sonst nichts, keine Öffnungs- oder Klickverfolgung.
- Kein Eintrag pro Gruß im Änderungsprotokoll (es ist keine Änderung an Daten). Nur das Setzen oder Entfernen von „Keine Geburtstagsgrüße“ geht ins Protokoll.

## Datenschutz

B sieht hier kein Problem, weil die Mail nur an das Mitglied selbst geht. Das Konzept hält es deshalb schlank: Der Gruß geht an alle, ohne Option im Profil und ohne Hinweis in der Mail. Wer keine Grüße möchte, meldet sich beim Verein, und die Verwaltung schaltet sie für dieses Konto ab. Der Absatz steht in der Datenschutzerklärung unter Ziffer 7 (`patterns/mvp-datenschutz.php`, auf B's Wunsch von C formuliert): Zweck, genutzte Daten, Rechtsgrundlage Art. 6 Abs. 1 lit. f DSGVO und Widerspruch per Mail an info@eintrikot.de. Auf die Live-Seite kommt er über WordPress → EINTRIKOT → Aktualisierungen → „Datenschutz: Absatz zum Geburtstagsgruß“. V prüft den Wortlaut.

## Entscheidungen für B

Umgesetzt wie vorgeschlagen; jede Zeile lässt sich mit einer kleinen Änderung anders entscheiden.


| Frage | Vorschlag C |
| --- | --- |
| Nur aktivierte Portal-Konten oder auch Mitglieder, die ihre Einladung noch nicht angenommen haben? | **Nur aktivierte.** Wer das Portal nie betreten hat, hat seine Mitgliedschaft im Portal noch nicht bestätigt. |
| Mitglieder unter 18? | **Ja, mit Zustimmung der Eltern.** Ein Glückwunsch an das Mitglied ist unbedenklich, und die Jugendlichen sind dann ohnehin im Portal. |
| Gestaltung | **Entschieden: Variante B „Festlich“** (B, 24.11.2026). |
| Unterschrift | „dein EINTRIKOT-Vorstand“, alternativ „Liebe Grüße, EINTRIKOT e.V.“ wie in den anderen Mails. |

## Tests (vor dem Einspielen)

- Geburtstag heute → genau ein Gruß, auch bei mehreren Durchläufen am Tag.
- Geburtstag gestern oder morgen → keiner.
- 29. Februar: im Schaltjahr am 29., sonst am 28.
- Vor 8 Uhr → keiner, ab 8 Uhr → einer.
- „Keine Geburtstagsgrüße“ gesetzt, Hauptschalter aus, nicht aktiviert, ohne Mitgliedsnummer, Beispieladresse, ungültiges Datum → keiner.
- Unter 18 ohne Zustimmung → keiner, mit Zustimmung → einer.
- Fehler beim Versand → erneuter Versuch am selben Tag, höchstens zweimal, nie am nächsten Tag.
- Mehrere Durchläufe am selben Tag → kein zweiter Gruß.
- „Keine Geburtstagsgrüße“: nur die Verwaltung setzt es, das Mitglied sieht es im Profil nicht, Änderung im Protokoll.
- Die Mail enthält keinen Hinweis zum Abschalten.
- Testmail trägt „[TEST]“ und geht nur an die eigene Adresse.

## Einführung

1. ~~B gibt Ablauf und Design frei.~~ Erledigt am 24.11.2026 (Variante B).
2. ~~C baut das Modul mit Tests.~~ Erledigt: Core 0.42.0.
3. ~~Einspielen auf Produktion~~ Erledigt am 24.11.2026 (Staging auf B's Wunsch übersprungen, Testseite mit gleichem Stand).
4. Absatz in der Datenschutzerklärung übernehmen (Aktualisierung in WordPress), V schaut drüber.
5. Testmail an B (Gmail, Outlook, Apple Mail, hell und dunkel), dann schaltet B den Hauptschalter an.
