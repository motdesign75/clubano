# Rollen, Rechte & SaaS-Konzept

Clubano ist als mandantenfähige SaaS-Anwendung aufgebaut. Das bedeutet:

- eine Anwendung
- mehrere Vereine
- klare Trennung der Daten pro Tenant

## Rollenebenen

### SAdmin

Plattformweite Rolle für:

- Admin-Dashboard
- globale Tenant-Steuerung
- Lizenzverwaltung
- Systemaufsicht

Nur `SAdmin` darf den eigentlichen Adminbereich sehen.

### Vereinsrollen

Innerhalb eines Vereins arbeiten Benutzer mit Vereinsrollen, zum Beispiel:

- Admin
- Mitarbeitende
- Leserechte

Die konkrete Rechteausgestaltung sollte weiter entlang der Module geschärft werden.

## Was besonders sensibel ist

- Finanzen
- Rechnungen
- Mitglieder
- Kommunikation
- Benutzerverwaltung
- öffentliche Veröffentlichungen

## SaaS-Sicherheitsprinzipien

Für Clubano gelten folgende Grundregeln:

1. Fremdschlüssel tenant-spezifisch validieren
2. öffentliche Routen strikt von internen Layouts trennen
3. Dateien nicht blind per Pfad ausliefern
4. sensible Bereiche nur rollenbasiert sichtbar machen

## Adminbereich

Der Adminbereich dient nicht dem normalen Vereinsalltag, sondern der Systemverwaltung. Er sollte nicht an Vereinsbenutzer delegiert werden.
