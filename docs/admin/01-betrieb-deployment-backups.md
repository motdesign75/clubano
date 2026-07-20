# Betrieb, Deployment & Backups

Clubano ist nicht nur Code. Für einen stabilen Betrieb müssen Anwendung, Datenbank, Konfiguration und Upload-Dateien gemeinsam betrachtet werden.

## Was immer gesichert werden muss

- `.env`
- Datenbank
- `storage/app/public/logos`
- `storage/app/public/briefbogen`
- `storage/app/public/photos`
- `storage/app/public/receipts`
- weitere nutzerbezogene Dateien in `storage/app/public`

## Deployment

Die technische Grundlage ist in [DEPLOY.md](../../DEPLOY.md) beschrieben.

Wichtige Schritte sind typischerweise:

1. Code aktualisieren
2. Composer-Abhängigkeiten sicherstellen
3. Frontend-Build sicherstellen
4. Migrationen ausführen
5. Caches leeren
6. Upload- und Laufzeitdaten prüfen

## Typische Fehlerbilder

### Weiße Seite / 500-Fehler

Oft verursacht durch:

- fehlende `.env`
- fehlendes `vendor`
- fehlendes `public/build`
- nicht gelaufene Migrationen
- alte kompilierte Views

### Fehlende Belege / Logos / Briefbogen

Meist kein Codeproblem, sondern fehlende Dateien in `storage/app/public`.

## Nach Deploy immer prüfen

- Login funktioniert
- Dashboard lädt
- Logo sichtbar
- Briefbogen vorhanden
- öffentliche Seiten ohne Sidebar
- Mail-/Brief-Flow erreichbar
- Event-/Formular-Embeds korrekt
