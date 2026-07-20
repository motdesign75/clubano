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

Ergebnis:
- diese Endpunkte sind tenant-geschützt
- zusätzlich wurden Regressionstests ergänzt

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
- Sichtbarkeit kritischer UI-Aktionen je Rolle

---

## Wichtiger technischer Hinweis

Die Tests sind im Workspace angelegt und syntaktisch geprüft.  
Der echte Testlauf ist lokal aktuell noch eingeschränkt, weil der Test-Runner in dieser Umgebung nicht vollständig verfügbar ist.

Für die nächste Stufe sollte deshalb in einer vollständigen Laravel-Testumgebung einmal ausgeführt werden:

```bash
php artisan test
```

oder alternativ:

```bash
vendor/bin/pest
```

---

## Aktueller Sicherheitsgewinn

Durch Block A ist Clubano jetzt spürbar robuster in diesen Punkten:

- sensible Dateien werden nicht mehr leichtfertig öffentlich verlinkt
- Downloadpfade sind strenger tenant- und objektbezogen
- Rollen und sichtbare Aktionen erzählen dieselbe Wahrheit
- erste kritische Tenant-Grenzen sind als Regressionstests festgehalten

---

## Noch offene Punkte für Block A

1. letzter Gegencheck auf weitere Download-/Datei-Endpunkte
2. Ausführen der neuen Tests in vollständiger Testumgebung
3. optional weitere Policy-/UI-Angleichung in Randmodulen

---

## Empfehlung

Block A ist bereits ein **echter Sicherheitsmeilenstein**.  
Bevor in Phase 1 zu den Kernflows weitergegangen wird, sollte einmal ein technischer Check in der Zielumgebung erfolgen:

- Deploy der Änderungen
- Cache leeren
- Regressionstests laufen lassen
- kritische Endpunkte manuell mit zwei Testmandanten gegenprüfen

