<?php
/**
 * Inhalte der Mitglieder-Hilfe (Erste Ausgabe: zehn Anleitungen).
 *
 * Jede Anleitung ist eine Aufgabe aus Mitgliedersicht, höchstens fünf Schritte, Du-Form, einfache Sprache.
 * Bilder und Videos liegen in help/ und werden mit tools/hilfe-medien.sh aus einem lokalen Testportal mit
 * erfundenen Personen erzeugt. Die Lage der Markierungen kommt aus help/boxes.json (vom selben Skript):
 * "el" nennt ein dort vermessenes Element, "at" die Seite, auf der die Nummer sitzt, "d" den Abstand.
 * Wer ein Element umbenennt, passt es hier und in tools/hilfe-medien.mjs an.
 *
 * Texte dürfen <b> enthalten. Auf goldenen Seiten (Service) sind die Markierungen schwarz ("dark"),
 * weil Schwarz, Rot und Gold nie zusammen stehen.
 */
namespace Eintrikot\Community;
if (!defined('ABSPATH')) {
    exit();
}

/** Themen der Hilfe in fester Reihenfolge, mit Farbe aus dem Brandbook. */
function help_topics() {
    return [
        'start' => [
            'title' => 'Erste Schritte',
            'hint' => 'Das Portal auf einen Blick und die zwei Fragen zum Start',
            'color' => '#F1D1DD',
            'icon' => '<path d="M5 12l4 4 10-10"/>'
        ],
        'profil' => [
            'title' => 'Profil & Sichtbarkeit',
            'hint' => 'Angaben pflegen, speichern – und wer was sieht',
            'color' => '#F1D1DD',
            'icon' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/>'
        ],
        'mitglieder' => [
            'title' => 'Mitglieder finden',
            'hint' => 'Suchen, filtern und Profile ansehen',
            'color' => '#C8E2EE',
            'icon' => '<circle cx="11" cy="11" r="6"/><path d="M20 20l-4.5-4.5"/>'
        ],
        'termine' => [
            'title' => 'Termine & Vereinsinfos',
            'hint' => 'Neuigkeiten lesen, an- und abmelden',
            'color' => '#CEDFBD',
            'icon' => '<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M3 10h18M8 3v4M16 3v4"/>'
        ],
        'service' => [
            'title' => 'Service & Anfragen',
            'hint' => 'Eine Anfrage stellen und ihren Stand sehen',
            'color' => '#FFCC4E',
            'icon' => '<path d="M4 6h16M4 12h10M4 18h7"/>'
        ],
        'konto' => [
            'title' => 'Konto & Anmeldung',
            'hint' => 'Anmelden, angemeldet bleiben, Passwort vergessen',
            'color' => '#ECECE8',
            'icon' => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 018 0v3"/>'
        ]
    ];
}

/** Die Anleitungen. Reihenfolge innerhalb eines Themas = Reihenfolge in der Seitennavigation. */
function help_articles() {
    return [
        'rundgang' => [
            'topic' => 'start',
            'title' => 'Das Portal auf einen Blick',
            'lead' =>
                'Was auf <b>Start</b> steht, wo die vier Bereiche sind und wo du dein Profil findest – in sechs Punkten.',
            'minutes' => 2,
            'keywords' => 'überblick rundgang start startseite menü navigation bereiche neu erklärung',
            'steps' => [
                [
                    'title' => 'Die Startseite',
                    'text' =>
                        'Nach der Anmeldung landest du auf <b>Start</b>. Hier siehst du auf einen Blick, was im Verein los ist und was bei dir noch offen ist.',
                    'shot' => 'start-ueberblick',
                    'where' => 'Start',
                    'marks' => [
                        [
                            'el' => 'nav',
                            'at' => 'right',
                            'd' => 60,
                            'text' =>
                                '<b>Die vier Bereiche</b>: Start, Mitglieder, Aktuelles und Service. Am Handy liegt diese Leiste unten.'
                        ],
                        [
                            'el' => 'suche',
                            'at' => 'right',
                            'text' => '<b>Mitglieder suchen</b> geht direkt von hier.'
                        ],
                        [
                            'el' => 'fortschritt',
                            'at' => 'corner',
                            'text' => '<b>Dein Profil</b>: was noch fehlt – mit „Ergänzen“ springst du hin.'
                        ],
                        ['el' => 'info', 'at' => 'corner', 'text' => 'Die <b>neueste Vereinsinfo</b>.'],
                        [
                            'el' => 'termin',
                            'at' => 'corner',
                            'text' => 'Der <b>nächste Termin</b> – mit dem Weg zu allen Terminen.'
                        ],
                        [
                            'el' => 'anfragen',
                            'at' => 'corner',
                            'text' => '<b>Deine offenen Anfragen</b> und wie weit sie sind.'
                        ]
                    ]
                ],
                [
                    'title' => 'Oben rechts: dein Konto',
                    'text' =>
                        'Ein Klick auf deinen Namen öffnet dein Kontomenü. Dort findest du dein eigenes Profil und das Abmelden.',
                    'shot' => 'kontomenue',
                    'where' => 'Start',
                    'marks' => [
                        [
                            'el' => 'hilfe',
                            'at' => 'left',
                            'd' => 60,
                            'text' =>
                                '<b>Hilfe</b> öffnet die Anleitung zu der Seite, auf der du gerade bist.'
                        ],
                        ['el' => 'trigger', 'at' => 'corner', 'text' => 'Dein <b>Name</b> öffnet das Menü.'],
                        [
                            'el' => 'ansehen',
                            'at' => 'left',
                            'd' => 90,
                            'text' => '<b>Mein Profil ansehen</b>: so, wie andere Mitglieder es sehen.'
                        ],
                        [
                            'el' => 'bearbeiten',
                            'at' => 'left',
                            'd' => 90,
                            'text' => '<b>Profil bearbeiten</b>: Angaben ändern und freigeben.'
                        ],
                        [
                            'el' => 'ab',
                            'at' => 'left',
                            'd' => 90,
                            'text' => '<b>Abmelden</b> beendet die Sitzung.'
                        ]
                    ]
                ]
            ],
            'phone' => [
                'shot' => 'handy-start',
                'text' =>
                    'Am Handy liegen die vier Bereiche in der Leiste unten: <b>Start · Mitglieder · Aktuelles · Service</b>. Dein Kontomenü öffnest du oben rechts über dein Bild.'
            ],
            'related' => ['erste-schritte', 'mitglied-finden', 'termin-anmelden']
        ],

        'erste-schritte' => [
            'topic' => 'start',
            'title' => 'Deine ersten Schritte: Freigabe und Newsletter',
            'lead' =>
                'Ganz oben auf <b>Start</b> warten zwei kurze Fragen. Beide beantwortest du mit einem Klick und kannst sie jederzeit im Profil ändern.',
            'minutes' => 1,
            'keywords' =>
                'zeig dich freigeben freigabe newsletter später erinnern wohnort region verein erste schritte karte',
            'steps' => [
                [
                    'title' => 'Zeig dich den anderen',
                    'text' =>
                        'Bisher sehen andere Mitglieder von dir nur Namen und Foto. Rechts siehst du, wie deine Karte im Mitgliederverzeichnis jetzt aussieht und wie nach der Freigabe.',
                    'shot' => 'start-erste-schritte',
                    'where' => 'Start',
                    'marks' => [
                        [
                            'el' => 'vorschau',
                            'at' => 'corner',
                            'text' => '<b>So sehen dich andere</b> – jetzt und nach der Freigabe.'
                        ],
                        [
                            'el' => 'freigeben',
                            'at' => 'left',
                            'd' => 60,
                            'text' =>
                                '<b>Freigeben</b> zeigt Wohnort, Region und Verein für Mitglieder. Sonst ändert sich nichts.'
                        ],
                        [
                            'el' => 'news',
                            'at' => 'left',
                            'd' => 60,
                            'text' =>
                                '<b>Ja, gern</b> oder <b>Nein, danke</b>: Newsletter ungefähr viermal im Jahr per E-Mail.'
                        ],
                        [
                            'el' => 'spaeter',
                            'at' => 'left',
                            'd' => 60,
                            'text' => '<b>Später erinnern</b> blendet die Karte für 14 Tage aus.'
                        ]
                    ]
                ]
            ],
            'boxes' => [
                [
                    'kind' => 'privat',
                    'title' => 'Wer sieht das?',
                    'items' => [
                        'Freigegebene Angaben sehen nur angemeldete Mitglieder. Öffentlich im Internet steht nichts davon.',
                        'Bist du unter 18, fragt die Karte nur nach deiner Region. Die Newsletter-Frage kommt erst ab 18.',
                        'Beides änderst du jederzeit unter <b>Profil bearbeiten</b>. Den Newsletter kannst du außerdem über den Abmeldelink in jeder E-Mail abbestellen.'
                    ]
                ]
            ],
            'related' => ['sichtbarkeit', 'profil-bearbeiten']
        ],

        'profil-bearbeiten' => [
            'topic' => 'profil',
            'title' => 'Profil bearbeiten und speichern',
            'lead' =>
                'Wohnort, Verein, Hockey-Lebenslauf, Beruf: Was du einträgst, hilft anderen, dich wiederzufinden. Du entscheidest, was davon sichtbar ist.',
            'minutes' => 3,
            'keywords' =>
                'profil ändern bearbeiten speichern profilbild foto bild hochladen angaben beruf verein lebenslauf',
            'steps' => [
                [
                    'title' => 'Profil bearbeiten öffnen',
                    'text' =>
                        'Klicke oben rechts auf deinen Namen und dann auf <b>Profil bearbeiten</b>. Am Handy tippst du oben rechts auf dein Bild.',
                    'shot' => 'kontomenue',
                    'where' => 'Start',
                    'marks' => [
                        [
                            'el' => 'trigger',
                            'at' => 'left',
                            'text' => 'Dein <b>Name</b> öffnet das Kontomenü.'
                        ],
                        [
                            'el' => 'bearbeiten',
                            'at' => 'left',
                            'd' => 90,
                            'text' => '<b>Profil bearbeiten</b> wählen.'
                        ]
                    ]
                ],
                [
                    'title' => 'Angaben eintragen',
                    'text' =>
                        'Das Profil ist in Abschnitte geteilt, zum Beispiel <b>Über dich</b> und <b>Hockey-Lebenslauf</b>. Fülle aus, was du teilen möchtest. Leere Felder erscheinen nirgends.',
                    'shot' => 'profil-ueberdich',
                    'where' => 'Profil bearbeiten',
                    'marks' => [
                        [
                            'el' => 'bild',
                            'at' => 'right',
                            'text' =>
                                '<b>Bild auswählen</b>, dann den Ausschnitt festlegen. JPEG, PNG oder WebP bis 5 MB.'
                        ],
                        [
                            'el' => 'felder',
                            'at' => 'right',
                            'd' => 60,
                            'text' => 'Wohnort, Region und Verein – oder was du sonst teilen möchtest.'
                        ]
                    ]
                ],
                [
                    'title' => 'Speichern',
                    'text' =>
                        'Klicke unten auf <b>Änderungen speichern</b>. Der Knopf bleibt beim Scrollen immer am unteren Rand. Danach erscheint oben „Gespeichert. Deine Änderungen sind jetzt sichtbar.“',
                    'shot' => 'profil-schalter',
                    'where' => 'Profil bearbeiten',
                    'marks' => [
                        [
                            'el' => 'speichern',
                            'at' => 'right',
                            'text' => '<b>Änderungen speichern</b> nicht vergessen.'
                        ]
                    ]
                ]
            ],
            'boxes' => [
                [
                    'kind' => 'info',
                    'title' => 'Gut zu wissen',
                    'items' => [
                        'Hat das Speichern nicht geklappt, bleiben deine Eingaben 30 Minuten als Entwurf erhalten. Lade die Seite einfach neu.',
                        'Mitgliedsnummer, Beitrag und Spende kommen aus der Mitgliederverwaltung. Änderungen daran stößt du über <b>Service</b> an.',
                        'Ob andere Mitglieder einen Abschnitt sehen, stellst du mit dem Schalter am Abschnitt ein.'
                    ]
                ]
            ],
            'related' => ['sichtbarkeit', 'anfrage-stellen']
        ],

        'sichtbarkeit' => [
            'topic' => 'profil',
            'title' => 'Wer sieht mein Profil?',
            'lead' =>
                'Du entscheidest. Name und Profilbild sehen alle Mitglieder, alles andere gibst du Abschnitt für Abschnitt frei. Öffentlich im Internet steht nichts davon.',
            'minutes' => 2,
            'keywords' =>
                'sichtbarkeit sichtbar privat datenschutz freigeben schalter geburtstag alter öffentlich wer sieht',
            'steps' => [
                [
                    'title' => 'Die drei Regeln',
                    'text' =>
                        'Unter <b>Profil bearbeiten</b> steht ganz oben, was immer sichtbar ist, was du entscheidest und was öffentlich ist.',
                    'shot' => 'profil-oben',
                    'where' => 'Profil bearbeiten',
                    'marks' => [
                        [
                            'el' => 'sicht',
                            'at' => 'right',
                            'd' => 50,
                            'text' => 'Immer sichtbar, du entscheidest, öffentlich: die drei Regeln.'
                        ],
                        [
                            'el' => 'sosehen',
                            'at' => 'right',
                            'text' =>
                                '<b>So sehen dich andere</b> zeigt dein Profil mit den Augen eines anderen Mitglieds.'
                        ]
                    ]
                ],
                [
                    'title' => 'Abschnitt freigeben und speichern',
                    'text' =>
                        'Jeder Abschnitt hat rechts einen Schalter <b>Für Mitglieder sichtbar</b>. Schwarz heißt: sichtbar. Danach unten <b>Änderungen speichern</b>.',
                    'shot' => 'profil-schalter',
                    'where' => 'Profil bearbeiten',
                    'marks' => [
                        [
                            'el' => 'schalter',
                            'at' => 'left',
                            'text' =>
                                'Schalter an: andere Mitglieder sehen diesen Abschnitt. Aus: nur du und die Vereinsverwaltung.'
                        ],
                        ['el' => 'speichern', 'at' => 'right', 'text' => '<b>Änderungen speichern</b>.']
                    ]
                ],
                [
                    'title' => 'Geburtstag und Alter',
                    'text' =>
                        'Deinen Geburtstag gibst du extra frei: niemand, nur Tag und Monat, oder mit Jahr. Ob dein Alter im Profil steht, ist eine eigene Wahl.',
                    'shot' => 'profil-geburtstag',
                    'where' => 'Profil bearbeiten',
                    'marks' => [
                        [
                            'el' => 'wahl',
                            'at' => 'right',
                            'text' =>
                                '<b>Wer sieht deinen Geburtstag?</b> Freigegebene Geburtstage stehen auch unter „Termine“.'
                        ],
                        [
                            'el' => 'alter',
                            'at' => 'right',
                            'text' =>
                                '<b>Mein Alter im Mitgliederprofil anzeigen</b> – nur, wenn du es anhakst.'
                        ]
                    ]
                ]
            ],
            'boxes' => [
                [
                    'kind' => 'privat',
                    'title' => 'Wer sieht das?',
                    'items' => [
                        'Was ausgeschaltet ist, sehen nur du und die Vereinsverwaltung.',
                        'Private Angaben tauchen auch in Suchtreffern und Filtern nicht auf.',
                        'Öffentlich ist nur ein Profilbild, das du selbst für die Website freigibst.',
                        'Die Vereinsverwaltung schaltet nie etwas für dich frei.'
                    ]
                ]
            ],
            'related' => ['profil-bearbeiten', 'mitglied-finden']
        ],

        'mitglied-finden' => [
            'topic' => 'mitglieder',
            'title' => 'Ein Mitglied finden',
            'lead' =>
                'Alte Teamkameradin, Trainer von damals, jemand aus deiner Stadt: Unter <b>Mitglieder</b> suchst du nach Name, Team oder Ort.',
            'minutes' => 1,
            'video' => [
                'file' => 'video-mitglied',
                'seconds' => 20,
                'poster' => 'mitglieder-liste',
                'label' => 'Video: Ein Mitglied finden, mit Untertiteln für jeden Schritt'
            ],
            'keywords' =>
                'mitglied suchen finden verzeichnis filter a-z buchstabe sortieren name team ort teamkamerad kontakt',
            'steps' => [
                [
                    'title' => 'Suchen, filtern, blättern',
                    'text' =>
                        'Klicke links auf <b>Mitglieder</b>. Tippe einen Namen, ein Team oder einen Ort ein und klicke <b>Suchen</b>. Mit <b>Filter</b>, der A–Z-Leiste und der Sortierung grenzt du weiter ein.',
                    'shot' => 'mitglieder-liste',
                    'where' => 'Mitglieder',
                    'marks' => [
                        [
                            'el' => 'suche',
                            'at' => 'right',
                            'text' => '<b>Suchfeld</b>: Name, Team oder Ort, zum Beispiel „Köln“.'
                        ],
                        [
                            'el' => 'filter',
                            'at' => 'right',
                            'text' => '<b>Filter</b>: Team, Altersklasse, Region und mehr zum Anklicken.'
                        ],
                        [
                            'el' => 'sort',
                            'at' => 'right',
                            'text' => '<b>Sortiert nach</b> Nachname oder Vorname.'
                        ],
                        [
                            'el' => 'az',
                            'at' => 'left',
                            'd' => 40,
                            'text' =>
                                '<b>A–Z</b> zeigt nur einen Buchstaben. Graue Buchstaben haben keine Treffer.'
                        ],
                        [
                            'el' => 'karte',
                            'at' => 'right',
                            'text' => 'Eine <b>Karte</b> anklicken öffnet das Profil.'
                        ]
                    ]
                ],
                [
                    'title' => 'Profil ansehen und zurück',
                    'text' =>
                        'Im Profil siehst du, was das Mitglied freigegeben hat. Mit <b>Zurück zur Suche</b> kommst du zu deinen Treffern zurück, die Suche bleibt erhalten.',
                    'shot' => 'mitglied-profil',
                    'where' => 'Mitglieder',
                    'marks' => [
                        [
                            'el' => 'zurueck',
                            'at' => 'right',
                            'text' => '<b>Zurück zur Suche</b> – deine Treffer bleiben.'
                        ],
                        ['el' => 'kopf', 'at' => 'corner', 'text' => 'Name, Bild und was das Mitglied teilt.']
                    ]
                ]
            ],
            'boxes' => [
                [
                    'kind' => 'privat',
                    'title' => 'Warum sehe ich bei manchen so wenig?',
                    'items' => [
                        'Jedes Mitglied entscheidet selbst, was es teilt. „Noch keine Angaben geteilt“ heißt: Name und Foto, sonst nichts.',
                        'Private Angaben tauchen auch in Treffern und Filtern nicht auf.'
                    ]
                ]
            ],
            'related' => ['sichtbarkeit', 'rundgang']
        ],

        'termin-anmelden' => [
            'topic' => 'termine',
            'title' => 'Für einen Termin anmelden',
            'lead' =>
                'Alle Treffen, Online-Runden und Ausflüge stehen unter <b>Aktuelles → Termine</b>. Ein Klick genügt, und du stehst auf der Liste.',
            'minutes' => 1,
            'video' => [
                'file' => 'video-termin',
                'seconds' => 30,
                'poster' => 'termine-liste',
                'label' => 'Video: Für einen Termin anmelden, mit Untertiteln für jeden Schritt'
            ],
            'keywords' =>
                'termin anmelden abmelden veranstaltung treffen event teilnehmen teilnehmerliste wer ist dabei kalender',
            'steps' => [
                [
                    'title' => 'Termine öffnen',
                    'text' =>
                        'Klicke links auf <b>Aktuelles</b> und dann auf den Reiter <b>Termine</b>. Der nächste Termin steht oben.',
                    'shot' => 'termine-liste',
                    'where' => 'Aktuelles',
                    'marks' => [
                        [
                            'el' => 'reiter',
                            'at' => 'right',
                            'd' => 60,
                            'text' => 'Reiter <b>Termine</b> – daneben findest du die <b>Vereinsinfos</b>.'
                        ],
                        [
                            'el' => 'mehr',
                            'at' => 'right',
                            'text' =>
                                '<b>Mehr zum Termin</b> klappt Ort, Treffpunkt, Ansprechperson und Beschreibung auf.'
                        ],
                        [
                            'el' => 'anmelden',
                            'at' => 'right',
                            'text' => '<b>Für Veranstaltung anmelden</b> – das war’s schon.'
                        ]
                    ]
                ],
                [
                    'title' => 'Angemeldet – und jederzeit wieder abmelden',
                    'text' =>
                        'Am Termin steht jetzt ein Knopf zum Abmelden. Unter <b>Wer ist dabei?</b> siehst du, wer noch kommt.',
                    'shot' => 'termine-angemeldet',
                    'where' => 'Aktuelles',
                    'marks' => [
                        [
                            'el' => 'wer',
                            'at' => 'bottom',
                            'd' => 56,
                            'text' => '<b>Wer ist dabei?</b> zeigt die Anmeldungen. Die Zahl zählt alle.'
                        ],
                        [
                            'el' => 'ab',
                            'at' => 'right',
                            'text' => '<b>Abmelden</b> nimmt dich wieder von der Liste.'
                        ]
                    ]
                ]
            ],
            'boxes' => [
                [
                    'kind' => 'privat',
                    'title' => 'Wer sieht das?',
                    'items' => [
                        'Andere Mitglieder sehen deinen Namen in der Teilnehmerliste. Öffentlich ist nichts davon.',
                        'Mitglieder unter 18 stehen nie mit Namen in der Liste, sie zählen nur mit.',
                        'Anmeldungen werden 180 Tage nach dem Termin automatisch gelöscht.'
                    ]
                ]
            ],
            'phone' => [
                'shot' => 'handy-termine',
                'text' =>
                    'Am Handy liegt das Menü unten: <b>Start · Mitglieder · Aktuelles · Service</b>. Sonst ist alles gleich – Termin antippen, <b>Für Veranstaltung anmelden</b>, fertig.'
            ],
            'related' => ['vereinsinfos', 'rundgang']
        ],

        'vereinsinfos' => [
            'topic' => 'termine',
            'title' => 'Vereinsinfos lesen',
            'lead' =>
                'Neuigkeiten aus dem Verein, nur für Mitglieder: Sie stehen unter <b>Aktuelles → Vereinsinfos</b>, die neueste zusätzlich auf <b>Start</b>.',
            'minutes' => 1,
            'keywords' =>
                'vereinsinfo vereinsinfos neuigkeiten mitteilung mitteilungen nachrichten aktuelles lesen news',
            'steps' => [
                [
                    'title' => 'Vereinsinfos öffnen',
                    'text' =>
                        'Klicke links auf <b>Aktuelles</b>. Der Reiter <b>Vereinsinfos</b> ist schon geöffnet, die neueste Info steht oben.',
                    'shot' => 'vereinsinfos',
                    'where' => 'Aktuelles',
                    'marks' => [
                        [
                            'el' => 'reiter',
                            'at' => 'right',
                            'd' => 60,
                            'text' => 'Reiter <b>Vereinsinfos</b>.'
                        ],
                        [
                            'el' => 'eintrag',
                            'at' => 'right',
                            'text' => 'Die neueste Info mit Datum – darunter die älteren.'
                        ]
                    ]
                ]
            ],
            'boxes' => [
                [
                    'kind' => 'info',
                    'title' => 'Gut zu wissen',
                    'items' => [
                        'Vereinsinfos sind nur im Portal zu lesen, nicht auf der öffentlichen Website.',
                        'Den EINTRIKOT-Newsletter per E-Mail bestellst du unter <b>Profil bearbeiten</b>.'
                    ]
                ]
            ],
            'related' => ['termin-anmelden', 'erste-schritte']
        ],

        'anfrage-stellen' => [
            'topic' => 'service',
            'title' => 'Eine Anfrage an den Vorstand stellen',
            'lead' =>
                'Eine Frage, ein Anliegen, neue Kontaktdaten: Unter <b>Service</b> schreibst du dem Verein, und der Stand deiner Anfrage bleibt für dich sichtbar.',
            'minutes' => 2,
            'dark' => true,
            'keywords' =>
                'anfrage service vorstand kontakt kontaktieren frage anliegen nachricht schreiben adresse stammdaten bankverbindung förderidee einbringen spende',
            'steps' => [
                [
                    'title' => 'Service öffnen und das Passende wählen',
                    'text' =>
                        'Klicke links auf <b>Service</b>. Für eine allgemeine Frage wählst du <b>Vorstand kontaktieren</b>. Für neue Adresse, Bankverbindung, eine Förderidee oder eine Spende gibt es eigene Einträge.',
                    'shot' => 'service-angebote',
                    'where' => 'Service',
                    'marks' => [
                        [
                            'el' => 'mitmachen',
                            'at' => 'right',
                            'd' => 50,
                            'text' =>
                                '<b>Mitmachen</b>: Förderidee einreichen, dich einbringen, zusätzlich fördern.'
                        ],
                        [
                            'el' => 'kontakt',
                            'at' => 'right',
                            'd' => 50,
                            'text' =>
                                '<b>Meine Daten und Kontakt</b>: Stammdaten, Bankverbindung, <b>Vorstand kontaktieren</b>.'
                        ]
                    ]
                ],
                [
                    'title' => 'Nachricht schreiben und absenden',
                    'text' => 'Schreibe kurz, worum es geht, und klicke <b>Anfrage absenden</b>.',
                    'shot' => 'anfrage-formular',
                    'where' => 'Service',
                    'marks' => [
                        ['el' => 'feld', 'at' => 'right', 'd' => 50, 'text' => '<b>Deine Nachricht</b>.'],
                        ['el' => 'absenden', 'at' => 'right', 'text' => '<b>Anfrage absenden</b>.']
                    ]
                ],
                [
                    'title' => 'Eingegangen',
                    'text' =>
                        'Du siehst sofort „Deine Anfrage ist eingegangen.“ und darunter den Stand. Der Verein bekommt gleichzeitig Bescheid. Diese Seite findest du jederzeit wieder unter <b>Service → Deine Anfragen</b>.',
                    'shot' => 'anfrage-eingegangen',
                    'where' => 'Service',
                    'marks' => [
                        ['el' => 'bestaetigung', 'at' => 'right', 'd' => 50, 'text' => 'Die Bestätigung.'],
                        [
                            'el' => 'stand',
                            'at' => 'right',
                            'd' => 50,
                            'text' => 'Der Stand: eingegangen, in Prüfung, erledigt.'
                        ]
                    ]
                ]
            ],
            'boxes' => [
                [
                    'kind' => 'info',
                    'title' => 'Gut zu wissen',
                    'items' => [
                        'Sobald es eine Rückmeldung gibt oder die Anfrage erledigt ist, bekommst du eine E-Mail mit einem Link zur Anfrage.',
                        'Den Stand siehst du jederzeit im Portal unter <b>Service</b> und auf <b>Start</b>.',
                        'Für Termine brauchst du keine Anfrage: Du meldest dich direkt am Termin unter <b>Aktuelles</b> an.',
                        'Kontodaten gibst du nie in eine Nachricht ein. Dafür gibt es <b>Bankverbindung ändern</b> mit einem sicheren Weg.'
                    ]
                ]
            ],
            'related' => ['anfrage-stand', 'termin-anmelden']
        ],

        'anfrage-stand' => [
            'topic' => 'service',
            'title' => 'Den Stand meiner Anfrage sehen',
            'lead' =>
                'Jede Anfrage hat eine eigene Seite. Dort siehst du, wie weit sie ist, wer sich kümmert und die Rückmeldung des Vereins. Gibt es etwas Neues, bekommst du eine E-Mail.',
            'minutes' => 1,
            'dark' => true,
            'keywords' =>
                'anfrage stand status rückmeldung antwort erledigt in prüfung eingegangen offen anfragen service',
            'steps' => [
                [
                    'title' => 'Deine Anfragen finden',
                    'text' =>
                        'Offene Anfragen stehen auf <b>Start</b> und ganz oben unter <b>Service</b>. Gibt es eine Antwort, die du noch nicht gelesen hast, steht dort <b>Neue Rückmeldung</b>. Klicke eine Anfrage an, um sie zu öffnen. Oder du klickst einfach auf den Knopf in der E-Mail.',
                    'shot' => 'service',
                    'where' => 'Service',
                    'marks' => [
                        [
                            'el' => 'anfragen',
                            'at' => 'right',
                            'd' => 50,
                            'text' =>
                                '<b>Deine Anfragen</b> mit ihrem Stand, zum Beispiel „In Prüfung“ oder „Neue Rückmeldung“.'
                        ]
                    ]
                ],
                [
                    'title' => 'Stand und Rückmeldung lesen',
                    'text' =>
                        'Oben siehst du, wie weit die Anfrage ist. Darunter steht, wer sich kümmert und wie es weitergeht. Sobald der Verein antwortet, erscheint die <b>Rückmeldung</b>.',
                    'shot' => 'anfrage-verlauf',
                    'where' => 'Service',
                    'marks' => [
                        [
                            'el' => 'stand',
                            'at' => 'right',
                            'd' => 50,
                            'text' => '<b>Stand</b>: eingegangen, in Prüfung, erledigt – mit Datum.'
                        ],
                        [
                            'el' => 'weiter',
                            'at' => 'left',
                            'd' => 50,
                            'text' => '<b>Wer kümmert sich</b> und <b>Wie es weitergeht</b>.'
                        ],
                        [
                            'el' => 'rueck',
                            'at' => 'right',
                            'd' => 50,
                            'text' => 'Die <b>Rückmeldung</b> des Vereins.'
                        ]
                    ]
                ]
            ],
            'boxes' => [
                [
                    'kind' => 'info',
                    'title' => 'Gut zu wissen',
                    'items' => [
                        'Eine E-Mail kommt, wenn es eine Rückmeldung gibt oder die Anfrage erledigt oder abgelehnt ist. Für Zwischenschritte wie „In Prüfung“ schicken wir keine.',
                        'Die Rückmeldung selbst steht nur im Portal. In der E-Mail ist nur ein Link, damit nichts Persönliches im Postfach liegt.',
                        'Erledigte Anfragen bleiben unter <b>Service → Deine Anfragen</b> zum Nachlesen.'
                    ]
                ]
            ],
            'related' => ['anfrage-stellen', 'rundgang']
        ],

        'anmelden' => [
            'topic' => 'konto',
            'title' => 'Anmelden und Passwort vergessen',
            'lead' =>
                'Du meldest dich mit deiner E-Mail-Adresse und deinem Passwort an. Passwort vergessen? Dann bekommst du sofort einen neuen Link per E-Mail.',
            'minutes' => 2,
            'public' => true,
            'keywords' =>
                'anmelden login einloggen passwort vergessen zurücksetzen neues passwort link e-mail angemeldet bleiben erstes mal zugang',
            'steps' => [
                [
                    'title' => 'Anmelden',
                    'text' =>
                        'Gib die E-Mail-Adresse ein, unter der du beim Verein gemeldet bist, dazu dein Passwort, und klicke <b>Anmelden</b>.',
                    'shot' => 'anmelden',
                    'where' => 'Anmeldung',
                    'marks' => [
                        [
                            'el' => 'email',
                            'at' => 'right',
                            'd' => 60,
                            'text' => 'Deine <b>E-Mail-Adresse</b>.'
                        ],
                        ['el' => 'passwort', 'at' => 'right', 'd' => 60, 'text' => 'Dein <b>Passwort</b>.'],
                        [
                            'el' => 'bleiben',
                            'at' => 'right',
                            'd' => 60,
                            'text' =>
                                '<b>Angemeldet bleiben</b>: 90 Tage auf diesem Gerät. Nur auf eigenen Geräten anhaken.'
                        ],
                        [
                            'el' => 'vergessen',
                            'at' => 'right',
                            'd' => 60,
                            'text' => '<b>Passwort vergessen?</b>'
                        ]
                    ]
                ],
                [
                    'title' => 'Passwort vergessen',
                    'text' =>
                        'Klicke <b>Passwort vergessen?</b>, gib deine E-Mail-Adresse ein und klicke <b>Neues Passwort erhalten</b>. In der E-Mail klickst du den Link und legst ein neues Passwort fest.',
                    'shot' => 'passwort-vergessen',
                    'where' => 'Passwort zurücksetzen',
                    'marks' => [
                        [
                            'el' => 'feld',
                            'at' => 'right',
                            'd' => 50,
                            'text' => 'Die E-Mail-Adresse, mit der du dich anmeldest.'
                        ],
                        [
                            'el' => 'knopf',
                            'at' => 'right',
                            'd' => 50,
                            'text' => '<b>Neues Passwort erhalten</b> – dann ins Postfach schauen.'
                        ]
                    ]
                ]
            ],
            'boxes' => [
                [
                    'kind' => 'info',
                    'title' => 'Zum ersten Mal hier?',
                    'items' => [
                        'Sobald deine Mitgliedschaft bestätigt ist, bekommst du per E-Mail einen persönlichen Link. Damit legst du dein Passwort selbst fest.',
                        'Link nicht erhalten oder abgelaufen? Unter <b>Passwort vergessen?</b> deine E-Mail-Adresse eingeben – du bekommst sofort einen neuen Link.',
                        'Keine E-Mail da? Schau im Spam-Ordner nach.'
                    ]
                ]
            ],
            'related' => ['rundgang', 'erste-schritte']
        ]
    ];
}

/** Welche Anleitung der „?“-Knopf auf einer Portalseite öffnet. */
function help_context_article($view, $tab = '') {
    $map = [
        'portal' => 'rundgang',
        'members' => 'mitglied-finden',
        'member' => 'mitglied-finden',
        'events' => 'termin-anmelden',
        'infos' => 'vereinsinfos',
        'service' => 'anfrage-stellen',
        'more' => 'anfrage-stellen',
        'request' => 'anfrage-stand',
        'profile' => 'sichtbarkeit'
    ];
    if ($view === 'aktuelles') {
        return $tab === 'termine' ? 'termin-anmelden' : 'vereinsinfos';
    }
    return $map[$view] ?? '';
}
