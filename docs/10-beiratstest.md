# Beiratstest auf der Entwicklungsseite

Stand: 14.10.2026. Der Beirat und der Vorstand testen das Portal auf `http://eintrikot.myemmel.com` bis **31.10.2026**. Es gibt nur erfundene Mitglieder und fünf persönliche Testzugänge. Echte Mitgliederdaten kommen erst nach dem Umzug auf `https://eintrikot.de`.

## Was die Testphase im Portal ändert (seit 0.23.0)

- **Erkennung:** Die Testphase gilt automatisch für Adressen mit `.myemmel.com`. Beim Umzug auf `eintrikot.de` endet sie von selbst, ohne dass etwas umgestellt werden muss.
- **Keine E-Mails:** Das Portal verschickt auf der Entwicklungsseite nichts. Auch „Passwort vergessen“ schickt keine Mail. Das steht auf der Anmeldeseite.
- **Banner** „Testumgebung – keine echten Daten“ oben auf jeder Portalseite mit dem Knopf **Rückmeldung geben** (Art: Unklar, Fehler, Wunsch; Text). Seite und Gerät (Handy oder Computer) werden automatisch eingetragen. Höchstens 30 Rückmeldungen pro Person und Tag.
- **Rückmeldungen** liegen im Backend unter Community-Aufbau → Test-Rückmeldungen. Jede hat einen Status: Neu → Besprechen → Umsetzen / Verwerfen → Erledigt. **Umgesetzt wird nur, was auf „Umsetzen“ steht.**
- **Testphase-Seite** im Backend (Community-Aufbau → Testphase): Testdaten anlegen, Zugänge anlegen, Testphase beenden, Rückmeldungen als Text zum Einfügen in den Chat.

## Anleitung für Björn

**Vorher**
1. Sicherung der Datenbank im STRATO-Kundenbereich (Datenbank exportieren) und die Datei sicher ablegen. Das ist der Weg zurück, falls etwas schiefgeht.
2. Der Stand des Codes vor dem Test ist mit der Markierung `vor-beiratstest` im Repository festgehalten.
3. Warten, bis der Einspiel-Lauf für Version 0.23.0 grün ist.

**Start**
4. Backend → Community-Aufbau → Testphase → **Testdaten anlegen** (18 erfundene Mitglieder, 3 Termine, 2 Vereinsinfos).
5. Dort unter „Zugänge für den Beirat anlegen“ fünf Namen mit E-Mail-Adresse eintragen und **Zugänge anlegen**. Die Passwörter erscheinen **nur einmal** auf dem Bildschirm. Sofort notieren.
6. Selbst einmal mit einem Zugang anmelden (Handy) und den Banner prüfen. Das Konto sieht beim ersten Login die Geburtstagsseite. Das ist gewollt und Teil des Tests.
7. Die Mail unten verschicken. **Link und Passwort getrennt senden**, das Passwort z. B. per Messenger. Ohne HTTPS gilt: kein Passwort verwenden, das anderswo genutzt wird.

**Während des Tests**
8. Einmal pro Woche Backend → Test-Rückmeldungen ansehen, Status setzen und die Liste mit Claude durchgehen („Rückmeldungen als Text“ auf der Testphase-Seite kopieren). Änderungen kommen als Pull Request und werden gesammelt eingespielt, nicht laufend, damit sich das Portal unter den Testenden nicht verändert.

**Ende (bis 31.10.2026)**
9. Rückmeldungen als Text kopieren und sichern. Sie bleiben außerdem im System.
10. **Testphase beenden und Testdaten löschen.** Danach zeigt die Seite 0 Testkonten und 0 Beispiel-Einträge. Löscht alle Testkonten samt Anfragen, Profilbildern und Anmeldungen; das Änderungsprotokoll wird anonymisiert.
11. Kontrolle: Verwaltung → Mitgliederprofile pflegen zeigt keine Testkonten mehr. Die Rückmeldungen im Backend können nach der Auswertung manuell gelöscht werden, **bevor** die Datenbank für den Umzug kopiert wird.
12. Zurück zum alten Stand, falls nötig: Datenbanksicherung (Schritt 1) wiederherstellen. Beim Code ist `vor-beiratstest` die Markierung; über die Pull Requests ist jeder Stand nachvollziehbar.

## Testleitfaden

1. **Anmelden und Geburtsdatum:** Mit den Zugangsdaten anmelden. Die Seite fragt nach dem Geburtsdatum. Bitte ein erfundenes Datum (mindestens 18 Jahre) eintragen. Alle Zugangsdaten sind nur für den Test.
2. **Profil:** Foto, Wohnort, Beruf und eine DHB-Station eintragen. Danach „Mein Profil ansehen“ prüfen.
3. **Geburtstag freigeben:** Im Profil festlegen, wer den Geburtstag sieht, und unter Aktuelles → Termine kontrollieren.
4. **Suchen:** Unter Mitglieder nach Name, Ort oder Team suchen und filtern.
5. **Termin:** Unter Aktuelles einen Termin ansehen und mit „Ich bin dabei“ anmelden.
6. **Service:** Eine Idee teilen oder eine freiwillige Jahresspende anfragen.
7. **Handy:** Dieselben Schritte am Handy, auch das Menü unten.
8. **Rückmeldung:** Alles Unklare über „Rückmeldung geben“ im Banner melden. Auch „Das war gut“ hilft.

## Mail an den Beirat und den Vorstand (Entwurf)

> Betreff: EINTRIKOT-Portal: Bitte testet mit (bis 31.10.)
>
> Liebe …,
>
> wir wollen das EINTRIKOT-Portal bald für alle Mitglieder öffnen. Vorher möchte ich eure Augen darauf haben.
>
> **Was ist das?** Der geschützte Mitgliederbereich von EINTRIKOT: Profile und Verzeichnis, Aktuelles und Termine mit „Ich bin dabei“, und ein Service-Bereich für Anfragen.
>
> **Wie hängt das mit WISO MeinVerein zusammen?** MeinVerein bleibt unsere Mitgliederverwaltung: Mitgliedschaft, Beiträge, SEPA und Spenden. Das Portal ergänzt sie. Eure Angaben für die Gemeinschaft (Profil, Station, Beruf, Geburtstag) pflegt jede und jeder im Portal. Bank- und Beitragsdaten stehen nicht im Portal. Wenn jemand etwas ändern will, das MeinVerein betrifft, schickt sie oder er eine Anfrage im Service, und wir übernehmen es dort von Hand.
>
> **Wichtig:** Das ist eine Testseite. Alle anderen Mitglieder dort sind erfunden, es werden keine E-Mails verschickt, und alles wird nach dem Test gelöscht. Die Seite läuft noch ohne HTTPS. Bitte nutzt deshalb kein Passwort, das ihr anderswo verwendet.
>
> **Link:** http://eintrikot.myemmel.com (Anmelden oben rechts). Dein Passwort schicke ich dir getrennt.
>
> **Was ich mir wünsche (20 bis 30 Minuten):**
> 1. Anmelden, das Geburtsdatum eintragen (gern erfunden, mindestens 18 Jahre).
> 2. Profil ausfüllen, Foto und eine DHB-Station eintragen.
> 3. Im Profil festlegen, wer deinen Geburtstag sieht.
> 4. Jemanden im Verzeichnis suchen.
> 5. Unter Aktuelles einen Termin ansehen und dich anmelden.
> 6. Im Service eine Idee teilen oder eine Jahresspende anfragen.
> 7. Das Ganze auch am Handy ansehen.
>
> Alles, was unklar, kaputt oder schön ist: Oben im Portal gibt es den Knopf **Rückmeldung geben**. Das ist die schnellste Art, mir etwas zu sagen. Nicht alles wird umgesetzt, wir gehen die Liste gemeinsam durch.
>
> Danke für eure Zeit!
> Björn
