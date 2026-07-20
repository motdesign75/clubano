<?php

return [
    'pages' => [
        'transactions.index' => [
            'title' => 'Buchungen verstehen',
            'summary' => 'Hier pruefst du Geldbewegungen, fehlende Belege und offene Vorgaenge. Der Blick soll erst Klarheit schaffen und dann Entscheidungen leicht machen.',
            'steps' => [
                'Filtere zuerst nach Richtung oder Zeitraum, damit du nur den relevanten Ausschnitt siehst.',
                'Pruefe danach die Hinweise zu offenen Buchungen und fehlenden Belegen.',
                'Oeffne einzelne Buchungen, wenn ein Konto, ein Beleg oder der Steuerbereich unklar ist.',
                'Nutze das Buchungsjournal, wenn du einen sauberen Ausdruck oder eine Kassenpruefung vorbereitest.',
            ],
            'tips' => [
                'Beleg fehlt bedeutet: Die Buchung ist da, aber der Nachweis noch nicht.',
                'Eigenbelege sind fuer Ausnahmefaelle gedacht, nicht fuer den Standard.',
                'Offene Entwuerfe solltest du moeglichst zuerst schliessen.',
            ],
            'faq' => [
                [
                    'question' => 'Womit fange ich an?',
                    'answer' => 'Fast immer mit dem Pruefbedarf. Dort siehst du zuerst, was noch Aufmerksamkeit braucht.',
                ],
                [
                    'question' => 'Was ist der Unterschied zwischen Buchung und Beleg?',
                    'answer' => 'Die Buchung beschreibt die Geldbewegung. Der Beleg ist der Nachweis dafuer.',
                ],
            ],
        ],
        'invoices.create' => [
            'title' => 'Rechnung oder Angebot anlegen',
            'summary' => 'Der einfachste Weg ist immer gleich: Empfaenger waehlen, Rahmendaten setzen, Positionen erfassen und dann speichern oder direkt versenden.',
            'steps' => [
                'Lege zuerst fest, ob die Adresse aus einem Mitglied, einem Kontakt oder einer freien Adresse kommen soll.',
                'Pruefe Datum, Faelligkeit und das passende Ertragskonto.',
                'Erfasse die Positionen so knapp wie moeglich und so klar wie noetig.',
                'Entscheide am Ende, ob das Dokument als Entwurf gespeichert oder direkt weiterverarbeitet wird.',
            ],
            'tips' => [
                'Entwurf ist sinnvoll, wenn noch intern geprueft werden soll.',
                'Die Empfaenger-E-Mail wird fuer den direkten Versand spaeter wiederverwendet.',
                'Ein sauberes Ertragskonto spart spaeter Zeit in der Buchhaltung.',
            ],
            'faq' => [
                [
                    'question' => 'Wann nehme ich Entwurf?',
                    'answer' => 'Wenn Text, Betrag oder Empfaenger intern noch nicht final geprueft sind.',
                ],
                [
                    'question' => 'Wann ist ein Kontakt besser als ein Mitglied?',
                    'answer' => 'Immer dann, wenn die Rechnung an Firmen, Sponsoren oder andere externe Ansprechpartner geht.',
                ],
            ],
        ],
        'protocols.send' => [
            'title' => 'Protokoll sicher versenden',
            'summary' => 'Beim Versand geht immer das erzeugte Protokoll als PDF mit. Bereits gespeicherte Anhaenge werden zusaetzlich an dieselbe Mail angehaengt.',
            'steps' => [
                'Waehle die Empfaenger aus Mitgliedern, Kontakten oder freien Mailadressen aus.',
                'Pruefe rechts, welche Dateien mitgeschickt werden.',
                'Versende erst, wenn Inhalt und Anhaenge im Protokoll final sind.',
                'Kontrolliere danach in der Versandhistorie, an wen das Protokoll gegangen ist.',
            ],
            'tips' => [
                'Kontakte sind ideal fuer Vorstand, Partner oder externe Teilnehmer.',
                'Freie Mailadressen sind praktisch fuer Einzelfaelle, aber weniger sauber nachvollziehbar.',
                'Wenn ein Anhang fehlt, musst du ihn zuerst am Protokoll speichern.',
            ],
            'faq' => [
                [
                    'question' => 'Geht die PDF immer mit?',
                    'answer' => 'Ja. Das Protokoll wird beim Versand automatisch als PDF erzeugt und angehaengt.',
                ],
                [
                    'question' => 'Was passiert mit gespeicherten Anhaengen?',
                    'answer' => 'Sie werden zusaetzlich zur PDF mit derselben Mail verschickt.',
                ],
            ],
        ],
        'mail.create' => [
            'title' => 'Serienmail ruhig vorbereiten',
            'summary' => 'Hier laeuft der Vorlagenversand zusammen: Mitglieder, Kontakte und freie Empfaenger koennen in einem Schritt angeschrieben werden.',
            'steps' => [
                'Waehle zuerst die passende Vorlage und pruefe rechts die Vorschau.',
                'Stelle danach die Empfaenger zusammen, am besten moeglichst aus bestehenden Daten.',
                'Ergaenze freie Mailadressen nur dort, wo kein Mitglied oder Kontakt existiert.',
                'Versende erst, wenn Betreff, Inhalt und Auswahl stimmig sind.',
            ],
            'tips' => [
                'Mitglieder und Kontakte bleiben sauberer nachvollziehbar als freie Adressen.',
                'Empfaenger ohne E-Mail-Adresse werden nicht versendet und muessen gesondert geklaert werden.',
                'Nutze die Suche, bevor du sehr grosse Listen manuell durchschaust.',
            ],
            'faq' => [
                [
                    'question' => 'Warum sollte ich Kontakte statt freier Adressen nutzen?',
                    'answer' => 'Weil Empfaenger, Historie und spaetere Nachvollziehbarkeit besser erhalten bleiben.',
                ],
                [
                    'question' => 'Wann ist eine freie Adresse sinnvoll?',
                    'answer' => 'Nur fuer einzelne Ausnahmen oder kurzfristige Empfaenger ausserhalb der gepflegten Daten.',
                ],
            ],
        ],
        'budgets.index' => [
            'title' => 'Haushaltsplaene mit Jahresblick',
            'summary' => 'Hier legst du den finanziellen Rahmen fuer ein Jahr fest und siehst sofort, welche Plaene bereits bestehen und welcher Jahrgang als naechstes vorbereitet werden sollte.',
            'steps' => [
                'Starte mit dem kommenden Jahr, damit Vorstand und Kasse frueh Orientierung haben.',
                'Oeffne bestehende Plaene, wenn du Plan, Ist und Abweichung direkt vergleichen willst.',
                'Nutze die Uebernahme ins Folgejahr, statt jeden Plan wieder von vorn aufzubauen.',
                'Erzeuge ein PDF, wenn der Plan in Sitzung, Vorstand oder Kassenpruefung gebraucht wird.',
            ],
            'tips' => [
                'Ein Haushaltsplan soll fuehren, nicht verwirren. Lieber wenige klare Positionen als zu viele Kleinteile.',
                'Entwurf reicht fuer die interne Arbeit. Freigeben lohnt sich erst, wenn die Zahlen stabil sind.',
                'Die Aussagekraft steigt, wenn Planpositionen sauber zu euren echten Konten passen.',
            ],
            'faq' => [
                [
                    'question' => 'Mit welchem Jahr beginne ich?',
                    'answer' => 'Fast immer mit dem kommenden Jahr, damit ihr rechtzeitig vor Vorstand oder Mitgliederversammlung einen belastbaren Rahmen habt.',
                ],
                [
                    'question' => 'Warum sollte ich ins Folgejahr uebernehmen?',
                    'answer' => 'Weil sich Vereinsfinanzen oft aehneln und ihr nur noch die Unterschiede anpassen muesst.',
                ],
            ],
        ],
        'budgets.show' => [
            'title' => 'Haushaltsplan lesen und bewerten',
            'summary' => 'In der Detailansicht wird der Plan nicht nur gespeichert, sondern mit euren abgeschlossenen Buchungen gegengeprueft. Genau daraus entsteht der eigentliche Nutzen.',
            'steps' => [
                'Pruefe zuerst oben das Ergebnis aus Plan und Ist, bevor du in Einzelpositionen gehst.',
                'Sieh dir danach Einnahmen und Ausgaben getrennt an, damit nichts durcheinanderlaeuft.',
                'Beobachte besonders groessere Abweichungen, weil dort meist Entscheidungen oder Nachsteuerung noetig werden.',
                'Nutze PDF oder Bearbeiten, wenn der Plan ausserhalb des Browsers gebraucht oder angepasst werden soll.',
            ],
            'tips' => [
                'Eine positive Abweichung ist nicht automatisch gut. Entscheidend ist, warum sie entstanden ist.',
                'Wenn Ist-Werte fehlen, pruefe zuerst, ob die zugehoerigen Buchungen bereits abgeschlossen wurden.',
                'Kurze Notizen pro Position helfen spaeter, Annahmen aus dem Plan noch zu verstehen.',
            ],
            'faq' => [
                [
                    'question' => 'Woher kommen die Ist-Werte?',
                    'answer' => 'Sie werden direkt aus euren abgeschlossenen Buchungen des jeweiligen Jahres berechnet.',
                ],
                [
                    'question' => 'Warum sind Einnahmen und Ausgaben getrennt?',
                    'answer' => 'Weil der Vorstand dadurch schneller erkennt, ob die Veraenderung aus weniger Einnahmen oder hoeheren Ausgaben entsteht.',
                ],
            ],
        ],
    ],
];
