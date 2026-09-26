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
