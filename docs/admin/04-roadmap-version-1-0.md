# Clubano Roadmap bis Version 1.0

Diese Roadmap übersetzt die Produktstrategie von Clubano in konkrete Arbeitspakete. Sie ist so geschrieben, dass sie intern als Umsetzungsboard, zur Sprintplanung und auch als Ausdruck für Abstimmungen mit dem Team genutzt werden kann.

## Prioritätenlogik

- **Priorität A**: Muss vor Version 1.0 abgeschlossen sein.
- **Priorität B**: Sollte idealerweise kurz nach Version 1.0 kommen.
- **Priorität C**: Wertvoller Ausbau nach der ersten stabilen Produktversion.

## Aufwandsskala

- **S**: wenige Tage
- **M**: etwa 1 bis 2 Wochen
- **L**: mehrere Wochen oder modulübergreifend

## Priorität A – Vor Version 1.0

### A1. Rollen- und Rechtekonzept fertigziehen
**Aufwand:** L

**Ziel**
- Jede Rolle sieht und bearbeitet nur die Bereiche, die fachlich zu ihr passen.

**Arbeitspakete**
- Rollenmodell festlegen: `SAdmin`, `Admin`, `Mitarbeiter`, `Lesen`
- Rechte-Matrix pro Modul definieren
- Policies oder Middleware pro Bereich ergänzen
- Navigation und Aktionen rollenabhängig ausspielen
- Benutzerverwaltung auf Berechtigte begrenzen

**Abnahmekriterium**
- Ein Vereinsnutzer ohne Admin-Rechte kann keine sensiblen Verwaltungsbereiche bedienen.

### A2. Öffentliche und interne Ansichten vollständig trennen
**Aufwand:** M

**Ziel**
- Öffentliche Seiten sind sauber, reduziert und enthalten nie interne Bedienelemente.

**Arbeitspakete**
- Öffentliche Eventlisten und Eventdetails prüfen
- Formulare und Embeds prüfen
- Dokumentation und öffentliche Hilfeseiten prüfen
- Eigenes Public-Layout überall konsistent verwenden
- Medien- und Dateilinks gegen interne Navigation prüfen

**Abnahmekriterium**
- Keine öffentliche URL zeigt Clubano-interne Navigation, Admin-Hinweise oder interne Aktionen.

### A3. Mandantensicherheit abschließen
**Aufwand:** L

**Ziel**
- Ein Tenant kann technisch keine Daten eines anderen Tenants sehen, referenzieren oder verändern.

**Arbeitspakete**
- Alle Fremdschlüssel-Validierungen tenant-spezifisch prüfen
- Datei- und Belegzugriffe mandantensicher machen
- Exportpfade und Sammelaktionen auf Tenant-Grenzen prüfen
- Finanz-, Projekt-, Rechnungs- und Benutzerbezüge prüfen
- Regressionstests für kritische Tenant-Grenzen schreiben

**Abnahmekriterium**
- Tenant A kann keine Daten von Tenant B sehen, auswählen oder manipulieren.

### A4. Deployment, Backup und Restore dokumentieren und stabilisieren
**Aufwand:** M

**Ziel**
- Der Betrieb ist wiederholbar, nachvollziehbar und im Notfall schnell wiederherstellbar.

**Arbeitspakete**
- Deploy-Reihenfolge dokumentieren
- Produktions-Checkliste definieren
- Backup-Strategie für Datenbank, `.env` und `storage/app/public` festhalten
- Restore-Ablauf dokumentieren
- Optional ein kleines Deploy-Skript oder eine feste Routine vorbereiten

**Abnahmekriterium**
- Ein Deploy und ein Restore sind ohne Improvisation reproduzierbar.

### A5. Kritische Kernflows automatisiert testen
**Aufwand:** L

**Ziel**
- Die wichtigsten Geschäftsprozesse sind vor Regressionen geschützt.

**Arbeitspakete**
- Tests für Rollen und Rechte
- Tests für Mandantentrennung
- Tests für Mitgliederanlage, Bearbeitung und Archivierung
- Tests für Mitgliedschafts-Snapshots und Rechnungsanstoß
- Tests für öffentliche Formulare und Event-Anmeldung
- Tests für Mail-, Brief- und Versandprotokoll-Flows

**Abnahmekriterium**
- Die geschäftskritischen Kernprozesse laufen automatisiert durch.

### A6. Rechnungen, Vorlagen, Mail und Brief final produktionsfest machen
**Aufwand:** M

**Ziel**
- Der Dokumenten- und Versandbereich trägt echte Vereinsarbeit stabil.

**Arbeitspakete**
- Rechnungen an Mitglieder, Kontakte und freie Adressen vollständig prüfen
- PDF-Ausgabe und Briefbogen-Nutzung prüfen
- Vorlagen-Typen fachlich absichern
- Versand- und Druckprotokoll end-to-end testen
- Listen- und Detailseiten mobil und auf Desktop gegenprüfen

**Abnahmekriterium**
- Vereine können Rechnungen, Mails und Briefe nachvollziehbar und stabil erzeugen.

### A7. Responsive Kernansichten abschließen
**Aufwand:** M

**Ziel**
- Die Kernflows funktionieren auf Handy, Tablet und Laptop ohne Frust.

**Arbeitspakete**
- Noch offene Kernansichten nachziehen
- Tabellen überall mit mobiler Alternative oder gutem Fallback denken
- Aktionsleisten und Kopfbereiche wrap-fähig machen
- Harte Breiten und Höhen weiter reduzieren

**Abnahmekriterium**
- Kein Kernbereich fühlt sich mobil wie ein ungepflegter Desktop-Abklatsch an.

## Priorität B – Kurz nach Version 1.0

### B1. Eventbereich auf echte Vereinsrealität bringen
**Aufwand:** L

**Ziel**
- Veranstaltungen bilden Anmeldungen, Plätze und Status realistisch ab.

**Arbeitspakete**
- Gesamtplatzlimit pro Event
- Anzeige freier Plätze und ausgebucht
- Teilnehmerstatus: angemeldet, bestätigt, bezahlt, erschienen, storniert
- Bestätigungsmail nach Anmeldung
- Teilnehmerexport
- Optional Warteliste

### B2. Mitgliederakte zur 360-Grad-Sicht ausbauen
**Aufwand:** M

**Ziel**
- Eine Mitgliederakte bündelt alle wichtigen Informationen an einem Ort.

**Arbeitspakete**
- Rechnungen und Zahlungen am Mitglied anzeigen
- Kommunikation bündeln
- Eventteilnahmen sichtbar machen
- Historie oder Timeline ergänzen
- Interne Notizen oder Dokumente strukturieren

### B3. Import und Export praxistauglich machen
**Aufwand:** M

**Ziel**
- Vereine können Bestandsdaten leicht übernehmen und operative Daten sauber exportieren.

**Arbeitspakete**
- Mitgliederimport aus CSV oder Excel
- Kontakteimport
- Eventteilnehmerexport
- Finanzexporte
- Serienlisten für Mail und Brief

### B4. Onboarding und Hilfen in der App abrunden
**Aufwand:** S bis M

**Ziel**
- Neue Vereine finden schneller in echte Arbeit hinein.

**Arbeitspakete**
- Startcenter weiter verfeinern
- Leere Zustände mit klaren nächsten Schritten versehen
- Kontext-Hinweise pro Modul ergänzen
- Beispielvorlagen und Testdaten weiter verbessern
- Öffentliche Dokumentation vervollständigen

## Priorität C – Ausbau nach 1.0

### C1. Finanzen und EÜR fachlich vertiefen
**Aufwand:** L

**Ziel**
- Der Finanzbereich wird fachlich tiefer und steuerlich belastbarer.

**Arbeitspakete**
- Echte EÜR-Kategorien
- Bessere Auswertungen
- Steuerlich sauberere Zuordnung
- Zusätzliche Exportformate

### C2. Kommunikation weiter professionalisieren
**Aufwand:** M

**Ziel**
- Clubano wird zur zuverlässigeren Kommunikationszentrale für Vereine.

**Arbeitspakete**
- Einwilligungslogik weiter schärfen
- Serienmailing verbessern
- Vorlagenbibliothek erweitern
- Spätere zusätzliche Kanäle prüfen

### C3. Aufgaben, Fristen und Erinnerungen
**Aufwand:** M

**Ziel**
- Wiederkehrende Vereinsarbeit und Fristen werden systematisch unterstützt.

**Arbeitspakete**
- Aufgaben je Bereich
- Fristen mit Status
- Wiedervorlagen
- Erinnerungslogik

### C4. Öffentliche Außendarstellung weiter veredeln
**Aufwand:** M

**Ziel**
- Öffentliche Event- und Formularseiten sollen auch nach außen stark wirken.

**Arbeitspakete**
- Öffentliche Vereinsseite
- Eventlisten weiter veredeln
- Embeds weiter verbessern
- Mobile Außenansicht optimieren

## Empfohlene Sprint-Reihenfolge

### Sprint 1
- A1 Rollen und Rechte
- A2 Public vs. intern
- A3 Mandantensicherheit

### Sprint 2
- A4 Deployment, Backup, Restore
- A5 Kernflow-Tests
- A6 Rechnungen, Vorlagen, Mail und Brief

### Sprint 3
- A7 Responsive Kernansichten
- B1 Eventbereich erweitern
- B2 Mitgliederakte verdichten

## Version-1.0-Gate

Clubano sollte aus Produktsicht erst als Version 1.0 gelten, wenn diese Punkte grün sind:

- Rollen und Rechte stehen
- Öffentliche und interne Ansichten sind sauber getrennt
- Mandantensicherheit ist geprüft
- Deployment, Backup und Restore sind dokumentiert
- Kritische Kernflows sind getestet
- Rechnungen, Vorlagen, Mail und Brief laufen stabil
- Kernansichten sind responsiv nutzbar

## Druckhinweis

Diese Seite ist bewusst auch für den Ausdruck vorbereitet. In der HTML-Dokumentation kann sie direkt über die Druckfunktion des Browsers als PDF oder Papierausdruck genutzt werden.
