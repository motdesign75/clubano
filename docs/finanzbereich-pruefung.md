# Finanzbereich: fachliche Pruefung und Umsetzungsstand

Stand: 2026-10-08

Diese Notiz bewertet den bestehenden Finanzbereich aus Sicht von Schatzmeister, Kassenpruefung und Produktentwicklung. Sie ersetzt keine Rechts- oder Steuerberatung und behauptet keine ungepruefte Compliance.

| Bereich | Konkretes Problem | Auswirkung | Prioritaet | Vorgeschlagene Loesung | Stand |
| --- | --- | --- | --- | --- | --- |
| Finanzuebersicht | Die Uebersicht zeigte bisher vor allem Zeitraum-Summen und einzelne Buchungsprobleme. Offene Rechnungen, Bankzuordnung und Belege waren auf mehrere Bereiche verteilt. | Neue Schatzmeister muessen suchen, was heute zu tun ist. | Hoch | Uebersicht als Arbeitsliste mit offenen Forderungen, Belegpruefung, Bankzuordnung sowie Bank- und Kassenstand. | Umgesetzt |
| Zahlungszuordnung | Zahlungen koennen aus Rechnung, Bankimport und manueller Buchung entstehen. | Risiko doppelter Zahlungen oder doppelter Buchungen. | Hoch | Exakte Dubletten verhindern, Bankimporte mit bestehenden Zahlungen/Buchungen verknuepfen, Buchung und Zahlung getrennt sichtbar halten. | Teilweise umgesetzt |
| Ausgangsrechnungen | Offene Forderungen wurden in der Finanzuebersicht nicht als eigene Arbeitsaufgabe hervorgehoben. | Ueberfaellige Zahlungen bleiben leichter liegen. | Hoch | Ueberfaellige und bald faellige Rechnungen mit Restbetrag in der Finanzuebersicht anzeigen. | Umgesetzt |
| Eingangsrechnungen | Hochgeladene Belege werden erkannt, aber Zahlungsziel/Faelligkeit ist noch kein eigener stabiler Prozess. | "Zu bezahlen" ist fachlich noch nicht vollstaendig abbildbar. | Hoch | Additives Eingangsrechnungsmodell oder erweiterte Belegfelder fuer Faelligkeit, IBAN, Verwendungszweck, Status und Dublettenwarnung. | Offen |
| Belegablage | Belege und Eigenbelege sind vorhanden, fehlende Belege werden angezeigt. | Gute Grundlage, aber Pruefzugang braucht direktere Rueckverknuepfung von Buchung zu Beleg und Zahlung. | Mittel | Kassenpruefungsansicht mit Buchung, Zahlung, Beleg und Korrekturhistorie pro Vorgang. | Offen |
| Bank und Kasse | Kontostand und offene Verpflichtungen duerfen nicht vermischt werden. | Falsches Liquiditaetsgefuehl, wenn Forderungen oder Rechnungen wie Kontostand wirken. | Hoch | Geldkonten separat anzeigen; offene Forderungen und Belege als Arbeitslisten, nicht als Bestand. | Umgesetzt |
| Haushaltsplanung | Kategorien sind nutzbar, aber der Weg von Buchung zu Auswertung muss konsequent bleiben. | Bereiche koennen sonst nicht belastbar als gewinnbringend oder defizitaer erkannt werden. | Mittel | Kategorien bei Buchungen pflegen und in der Finanzuebersicht auswerten. | Teilweise umgesetzt |
| Rollen und Mandanten | Finanzdaten sind besonders sensibel. | Fremdzugriff waere kritisch. | Hoch | Bestehende Mandantenfilter und Rollen bei neuen Auswertungen weiterverwenden und testen. | Umgesetzt fuer neue Uebersicht |
| Datenerhalt | Strukturelle Umbauten am Datenmodell duerfen keine Altbelege oder Buchungen gefaehrden. | Datenverlust oder nicht nachvollziehbare Korrekturen. | Hoch | Additive Migrationen, vorherige Sicherung, Wiederherstellungstest, Summenvergleich je Mandant und Konto. | Vorgegeben, noch nicht ausgeloest |

## Umgesetzter erster Schritt

Die Finanzuebersicht wurde zur Arbeitsuebersicht erweitert:

- Geld ausstehend: ueberfaellige und bald faellige offene Ausgangsrechnungen mit Restbetrag.
- Belege pruefen: offene Buchungsbelege aus dem Beleg-Eingang.
- Bank zuordnen: Bankumsaetze mit Status zuordnen, bereit oder Dublette.
- Bank und Kasse: aktuelle Geldkonten als reine Bestandsinformation.
- Haushaltsbereiche und bestehende Buchungs-/Belegpruefung bleiben erhalten.

## Naechste fachlich sinnvolle Schritte

1. Eingangsrechnungen als eigenen Workflow modellieren: pruefen, offen, teilweise bezahlt, bezahlt, storniert.
2. Faelligkeit und Zahlungsziel aus Rechnungen strukturiert speichern, inklusive Herkunft der Erkennung.
3. Kassenpruefungsansicht mit Zeitraum, Bestandsvergleich, Belegen, offenen Punkten und Export.
4. Erinnerungen fuer offene Forderungen und Eingangsrechnungen erst nach expliziter Aktivierung automatisieren.
5. Vor jeder Datenmigration Sicherung, Wiederherstellungstest und Summenvergleich dokumentieren.
