# Clubano Deployment

Diese Anwendung braucht fuer ein sauberes Deployment mehr als nur `git pull`.

## Was erhalten bleiben muss

Diese Dinge kommen nicht zuverlaessig aus Git und muessen auf dem Server vorhanden bleiben:

- `.env`
- `storage/app/public/briefbogen`
- `storage/app/public/logos`
- `storage/app/public/photos`
- `storage/app/public/receipts`

## Sicherer Deploy-Ablauf

Im Projektordner auf dem Server:

```bash
cd /var/www/vhosts/clubano.de/app.clubano.de
bash deploy.sh
```

Mit Migrationen:

```bash
cd /var/www/vhosts/clubano.de/app.clubano.de
RUN_MIGRATIONS=1 bash deploy.sh
```

Wenn `public/build` extern gebaut und kopiert wurde:

```bash
cd /var/www/vhosts/clubano.de/app.clubano.de
SKIP_BUILD=1 bash deploy.sh
```

## Was das Skript tut

- `git fetch` und `git reset --hard origin/main`
- `.env` und Projektstruktur pruefen
- Laravel-Verzeichnisse unter `storage/framework` und `bootstrap/cache` anlegen
- `composer install --no-dev --optimize-autoloader`
- `npm ci` und `npm run build`
- `public/build/manifest.json` pruefen
- `storage:link` anlegen
- Rechte auf Projekt, `storage` und `bootstrap/cache` setzen
- `php artisan optimize:clear`
- optional `php artisan migrate --force`

## Was das Skript absichtlich NICHT tut

- kein `migrate:fresh`
- kein automatisches Wiederherstellen von Uploads aus `storage/app/public`
- kein blindes Ueberschreiben der `.env`

## Vor jedem Deploy kurz pruefen

```bash
ls -la .env
find storage/app/public -maxdepth 2 -type d | sort
test -L public/storage && echo STORAGE_LINK_OK || echo STORAGE_LINK_FEHLT
```

## Wenn Belege oder Logos fehlen

Dann fehlen meist Laufzeitdateien in `storage/app/public`.

Typische Kandidaten:

- `storage/app/public/receipts`
- `storage/app/public/logos`
- `storage/app/public/photos`
- `storage/app/public/briefbogen`

Diese Dateien muessen separat vom alten Server, Backup oder Mac kopiert werden.
