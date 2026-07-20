# Öffentliche Seiten, Embeds & Außenansicht

Clubano unterstützt öffentliche und eingebettete Ausgaben. Diese müssen bewusst anders behandelt werden als interne Verwaltungsseiten.

## Öffentliche Bereiche

Aktuell gehören dazu insbesondere:

- öffentliche Veranstaltungsseiten
- öffentliche Veranstaltungslisten
- Event-Embeds
- öffentliche Formulare
- Formular-Embeds

## Grundsatz

Öffentliche Ansichten dürfen niemals:

- die interne Sidebar zeigen
- interne Aktionen enthalten
- interne Kennzahlen preisgeben
- interne Links oder Admin-Elemente rendern

## Veranstaltungslisten

Öffentliche Veranstaltungslisten sind für die Einbettung auf Vereinswebsites gedacht und sollten:

- optisch eigenständig
- ohne Clubano-Innenlayout
- gut lesbar
- mobil nutzbar

sein.

## Öffentliche Eventdetails

Auch Eventdetailseiten müssen in einem eigenen Public-Layout laufen, damit beim Teilen oder Einbetten keine interne Navigation sichtbar wird.

## Formulare und Embeds

Formulare werden auf zwei Wegen genutzt:

- direkte öffentliche Seite
- Einbettung per `iframe`

Für beides gilt:

- kein internes Layout
- keine Sidebar
- keine Systemnavigation

## Qualitätssicherung

Nach Änderungen an öffentlichen Ansichten sollte immer geprüft werden:

1. keine Sidebar sichtbar
2. keine Logout-/Admin-Links sichtbar
3. keine internen Kennzahlen sichtbar
4. Embed verhält sich wie öffentliche Ansicht
