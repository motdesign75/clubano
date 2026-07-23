# Clubano – Block A Dokumentation

## Thema
Phase 1 – **Sicherheit / Tenant-Audit / Rechte**

## Ziel
Block A sichert Clubano an den Stellen ab, an denen Mandantentrennung, sensible Dateien und Rollen besonders kritisch sind. Der Fokus liegt auf drei Fragen:

1. Kann ein Verein Daten eines anderen Vereins sehen oder herunterladen?
2. Werden sensible Dateien über öffentliche Pfade ausgeliefert?
3. Zeigt die Oberfläche nur die Aktionen, die eine Rolle wirklich ausführen darf?

---

## Bereits umgesetzt

### 1. Dateibasierte Tenant-Lecks gehärtet

#### Projektdokumente
- neue Projektdokumente werden nicht mehr öffentlich gespeichert, sondern auf `local`
- Speicherpfad ist tenant- und projektbezogen
- Download und Löschen prüfen zusätzlich:
  - Tenant des Projekts
  - Projekt-Zuordnung des Dokuments
  - Tenant-Zuordnung des Dokuments
- direkte öffentliche `Storage::url(...)`-Ausgabe wurde entfernt

#### Protokollanhänge
- neue Anhänge werden auf `local` gespeichert
- bestehende Altanhänge auf `public` bleiben lesbar
- Anhänge werden über die geschützte Route ausgeliefert
- Löschen eines Protokolls entfernt die gespeicherten Anhänge mit

#### Briefbogen / Vereinsdateien
- Briefbogen in der Vereinsbearbeitung wird nicht mehr über direkte Storage-URLs angezeigt
- Vorschau und Öffnen laufen über die geschützte Route `tenant.letterhead`

---

### 2. Export- und PDF-Zugriffe geprüft und abgesichert

#### Geschärfte Endpunkte
- `pdf-test` ist jetzt nur noch für `Admin` erreichbar

#### Geprüfte Export-/PDF-Strecken
- Mitglieder-Datenauskunft
- Formularantwort-Export
- Event-Teilnehmerexport
- Event-Dienstplanexport
- Buchungsbelege

Ergebnis:
- diese Endpunkte sind tenant-geschützt
- zusätzlich wurden Regressionstests ergänzt
- Buchungsbelege sind zusätzlich auf `Admin` begrenzt

---

### 3. Rollensichtbarkeit in der Oberfläche angeglichen

Das feste Rollenmodell lautet:
- `Admin`
- `Bearbeiten`
- `Lesen`

Im `User`-Modell wurden dafür klare Helfer ergänzt:
- `canManageMembers()`
- `canManageForms()`
- `canManageContacts()`
- `canManageProjects()`
- `canManageProtocols()`
- `canManageFinance()`
- `canManageTenantSettings()`

#### Mitglieder
- `Lesen` sieht keine Bearbeitungs-, Archivierungs- oder Bulk-Aktionen
- `Bearbeiten` sieht:
  - Mitglied anlegen
  - Datenauskunft
  - Bearbeiten
  - Archivieren
  - Serienaktionen
- Finanz-/SEPA-Daten auf der Mitgliedsdetailseite sind nur für `Admin` sichtbar

#### Formulare
- `Lesen` sieht keine Verwaltungsaktionen
- `Bearbeiten` sieht:
  - Neues Formular
  - Bearbeiten
  - Einbetten
  - Löschen
  - Antworten exportieren / stornieren / löschen

#### Protokolle
- `Lesen` sieht:
  - Übersicht
  - Detailseite
  - Anhänge
- `Lesen` sieht nicht:
  - Neues Protokoll
  - Bearbeiten
  - Versenden
  - Archivieren
  - Löschen

#### Kontakte
- Kontakte nutzen bereits eine saubere `ContactPolicy`
- hier war keine zusätzliche UI-Härtung nötig

---

## Ergänzte Regressionstests

### Vorhanden
- `tests/Feature/ProjectTenantIsolationTest.php`
- `tests/Feature/ProjectDocumentTenantIsolationTest.php`
- `tests/Feature/ExportTenantIsolationTest.php`
- `tests/Feature/RoleVisibilityTest.php`

### Inhaltlich abgesichert
- Projektliste tenant-isoliert
- Projektdokumente nur im richtigen Tenant und Projekt abrufbar
- Export- und PDF-Endpunkte nicht cross-tenant nutzbar
- Buchungsbelege nicht ohne Finanzrolle oder über Tenant-Grenzen abrufbar
- Sichtbarkeit kritischer UI-Aktionen je Rolle

---

## Technischer Teststand

Der Regressionstestlauf wurde lokal erfolgreich ausgeführt:

```bash
php artisan test
```

Ergebnis:

- 42 Tests bestanden
- 27 Tests übersprungen
- 158 Assertions geprüft

Die übersprungenen Tests stammen aus alten optionalen Jetstream-/Fortify-Scaffold-Bereichen, die in Clubano aktuell nicht aktiv bzw. nicht vollständig installiert sind:

- API Tokens
- Jetstream Browser Sessions
- Jetstream Livewire-Profilkomponenten
- Zwei-Faktor-Verwaltung
- ältere Fortify-Duplikattests für Registrierung, Passwortreset und Verifikation

Diese Tests werden nicht als Produktregression gewertet, solange die zugehörigen Features nicht aktiviert werden.

---

## Aktueller Sicherheitsgewinn

Durch Block A ist Clubano jetzt spürbar robuster in diesen Punkten:

- sensible Dateien werden nicht mehr leichtfertig öffentlich verlinkt
- Downloadpfade sind strenger tenant- und objektbezogen
- Rollen und sichtbare Aktionen erzählen dieselbe Wahrheit
- erste kritische Tenant-Grenzen sind als Regressionstests festgehalten

---

## Noch offene Punkte für Block A

1. kritische Endpunkte manuell mit zwei Testmandanten gegenprüfen
2. optional weitere Policy-/UI-Angleichung in Randmodulen

---

## Empfehlung

Block A ist bereits ein **echter Sicherheitsmeilenstein**.  
Bevor in Phase 1 zu den Kernflows weitergegangen wird, sollte einmal ein technischer Check in der Zielumgebung erfolgen:

- Deploy der Änderungen
- Cache leeren
- Regressionstests laufen lassen
- kritische Endpunkte manuell mit zwei Testmandanten gegenprüfen
