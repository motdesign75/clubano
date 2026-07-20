# Clubano – Releasebeschreibung für Testmandanten

## Betreff
Clubano Test-Update: mehr Klarheit, bessere Finanzprozesse und viele Verbesserungen im Alltag

## Kurzfassung
In den letzten Tagen haben wir Clubano an vielen zentralen Stellen deutlich verbessert. Der Schwerpunkt lag auf:

- klareren Abläufen im Vereinsalltag
- mehr Nachvollziehbarkeit bei Buchungen
- neuen Funktionen für Angebote, Aufgaben und Austritte
- besserer mobiler Nutzung
- mehr Sicherheit bei Dateien, Exporten und Rollen

Dieses Update ist bewusst breit: Es macht Clubano nicht nur schöner, sondern in vielen Bereichen spürbar belastbarer und angenehmer im täglichen Einsatz.

---

## Was neu ist

### Mitglieder
- bessere Mitgliederübersicht ohne unnötiges Seitenscrollen
- Austritte und Kündigungen sind deutlich sichtbarer
- Zeitraum für bevorstehende Austritte ist einstellbar
- Datenauskunft ist jetzt direkt erreichbar
- IBAN kann bei deutschen Konten die BIC automatisch ergänzen
- individuelle Austrittsbestätigung per E-Mail ist konfigurierbar
- Mitgliedsdetail und Mitgliedsbearbeitung wurden klarer und moderner aufgebaut

### Formulare
- Formulare sind auf Mobilgeräten deutlich besser nutzbar
- bei Kursanmeldungen muss sich eine einzelne Person nicht mehr doppelt als Ansprechpartner und Teilnehmer eintragen
- Formularantworten können jetzt storniert oder gelöscht werden
- Hilfstexte unterstützen jetzt Links, zum Beispiel für Datenschutz-Hinweise

### Finanzen
- Kassenbuch als eigener Bereich auf Basis der bestehenden Buchungen
- geführte Buchungsarten für:
  - Bareinnahme
  - Barausgabe
  - Bank zu Kasse
  - Kasse zu Bank
- Buchungen können markiert und abgeschlossen werden
- nach Abschluss ist Korrektur nur noch per Storno vorgesehen
- wer eine Buchung erfasst oder geändert hat, wird gespeichert und angezeigt
- neue Körperschaftsteuer-Auswertung auf Basis der Steuerbereiche
- Kassenbuch und Buchungslisten wurden responsiver und klarer aufgebaut

### Rechnungen und Angebote
- Angebote können jetzt zusätzlich zu Rechnungen erstellt werden
- Briefbogen kann für Rechnungen und Angebote genutzt werden
- wenn der Briefbogen eigene Kopf- und Fußzeilen enthält, werden diese nicht doppelt angezeigt
- Positionen können jetzt zusätzliche Details enthalten
- die Erfassungsmaske für Positionen ist ruhiger und besser bedienbar

### Protokolle
- Protokolle wurden visuell überarbeitet
- Archivieren und Löschen sind möglich
- auf der Detailseite wird jetzt sichtbar, wann ein Protokoll zuletzt geändert wurde
- ein Bearbeitungsfehler bei Uhrzeiten wurde behoben

### Aufgaben und Wiedervorlage
- Aufgaben wurden um Wiedervorlage, Verantwortlichkeit und Statusinformationen erweitert
- es gibt jetzt einen eigenen Aufgabenbereich mit:
  - heute fällig
  - überfällig
  - demnächst
  - Wiedervorlage

### Kommunikation
- beim Mailversand können jetzt zusätzlich zu Mitgliedern auch freie E-Mail-Adressen eingetragen werden

### Navigation und Dashboard
- einklappbare Sidebar
- Dashboard klarer und einfacher strukturiert
- Mitglieder-Charts und Mitgliederstatistiken verbessert

---

## Wichtige Verbesserungen und Bugfixes

### Sicherheit / Datenschutz
- Mandantentrennung im Projektbereich gehärtet
- Projektdokumente und Protokollanhänge werden nicht mehr leichtfertig öffentlich verlinkt
- Export- und PDF-Endpunkte wurden überprüft
- Rollen und sichtbare Aktionen wurden in mehreren Bereichen sauberer aufeinander abgestimmt

### Buchungen / Finanzlogik
- bestehende Sessions mussten sich für die neue Login-/Zeitstempel-Logik einmal neu anmelden
- Bestände zählen nur noch mit den richtigen abgeschlossenen Buchungen
- Kassenbuch und Buchungsansicht wurden an mehreren Stellen responsive nachgebessert

### Rechnungen / PDFs
- PDF-Abstände verbessert, damit Inhalte nicht in die Fußzeile rutschen
- Briefbogen-Verhalten verbessert

### Kontakte
- Fehler beim Anlegen neuer Kontakte über `/contacts/create` behoben

### Protokolle
- Speichern nach Bearbeitung zuverlässiger und nachvollziehbarer gemacht
- Zeitfelder werden sauber als `HH:MM` behandelt

### Dashboard
- Fehlberechnung bei neuen Mitgliedern behoben

---

## Was wir euch im Test besonders bitten zu prüfen

Bitte achtet in eurem Test besonders auf diese Bereiche:

1. Mitgliederübersicht und Austritte
2. Kassenbuch und Buchungen
3. Angebote / Rechnungen / PDFs
4. Formulare und Formularantworten
5. Aufgaben und Wiedervorlage
6. Rechte und Sichtbarkeit mit unterschiedlichen Benutzerrollen

---

## Bekannte Richtung der Weiterentwicklung

Die nächsten großen Schwerpunkte bleiben:

- Sicherheit / Tenant-Audit / Rechte final abschließen
- Kernflows weiter glätten
- Finanzbereich weiter absichern
- Aufgaben + Wiedervorlage vertiefen
- Bankimport vorbereiten

---

## Feedback

Bitte meldet uns besonders:

- Stellen, an denen ihr euch im Ablauf unsicher fühlt
- Ansichten, die auf Handy oder Laptop noch nicht gut wirken
- Rollen-/Rechteprobleme
- Unstimmigkeiten bei Buchungen, PDFs oder Formularen

Je konkreter euer Feedback ist, desto schneller können wir Clubano für den echten Alltag schärfen.

