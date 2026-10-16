# Clubano Mobile

Erste Expo-App fuer iOS und Android. Die App nutzt die neuen Clubano-Mobile-API-Endpunkte unter `/api/mobile/*`.

## Start

```bash
cd mobile
npm install
npm run ios
npm run android
```

In `src/config.ts` muss `API_BASE_URL` auf die passende Clubano-Installation zeigen, z.B. lokal `http://127.0.0.1:8000` oder produktiv `https://app.clubano.de`.

## MVP-Funktionen

- Mitglieder-Login per Clubano-Benutzerkonto
- eigene Stammdaten ansehen und Aenderungen zur Pruefung einreichen
- Veranstaltungen ansehen und Rueckmeldung senden
- freigegebene Dienstplaene anzeigen
- Satzung und Beitragsordnung anzeigen
- Vereinsnews als vorbereiteter Bereich ohne Chat
- Kontakt zum Verein

Rechnungen und Mitglieder-Chat sind bewusst nicht enthalten.

## Android-Release-Signierung

Release-Builds verwenden ausschliesslich einen Upload-Schluessel. Die lokale Datei
`~/.config/clubano/android-signing/signing.properties` enthaelt `storeFile`
(absoluter Pfad), `storePassword`, `keyAlias` und `keyPassword`.
Schluessel und Passwortdatei niemals committen; separat verschluesselt sichern.
Ohne diese Konfiguration bricht ein Release-Build ab.

Mit installiertem JDK und Android SDK: in `mobile/android` den Befehl
`bash ./gradlew bundleRelease` ausfuehren. Das Bundle liegt anschliessend unter
`app/build/outputs/bundle/release/app-release.aab`.

Stand Stabilitaetsupdate: Android 1.0.2 (8), iOS-Archiv 1.0.2 (21).
Die iOS-Version wird beim Archivieren mit `MARKETING_VERSION=1.0.2`
und `CURRENT_PROJECT_VERSION=21` gesetzt. Vor einem weiteren Upload die
Buildnummern mit den Store-Konsolen abgleichen und erhoehen.
Android benoetigt vor der Store-Freigabe noch eine Pruefung der Ziel-API und
nativen Bibliotheken sowie die Firebase-Konfiguration und den FCM-Versand.
