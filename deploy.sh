#!/usr/bin/env bash

set -Eeuo pipefail

APP_DIR="${APP_DIR:-/var/www/vhosts/clubano.de/app.clubano.de}"
APP_USER="${APP_USER:-olli1975}"
APP_GROUP="${APP_GROUP:-psacln}"
WEB_GROUP="${WEB_GROUP:-psacln}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"
RUN_MIGRATIONS="${RUN_MIGRATIONS:-0}"
SKIP_BUILD="${SKIP_BUILD:-0}"

log() {
    printf "\n[%s] %s\n" "$(date '+%Y-%m-%d %H:%M:%S')" "$1"
}

fail() {
    printf "\n[FEHLER] %s\n" "$1" >&2
    exit 1
}

require_file() {
    local path="$1"
    [ -e "$path" ] || fail "Fehlt: $path"
}

require_dir() {
    local path="$1"
    [ -d "$path" ] || fail "Fehlt: $path"
}

log "Starte Clubano-Deployment"
require_dir "$APP_DIR"
cd "$APP_DIR"

log "Pruefe Grundstruktur"
require_file "$APP_DIR/artisan"
require_file "$APP_DIR/composer.json"
require_file "$APP_DIR/package.json"
require_dir "$APP_DIR/routes"
require_file "$APP_DIR/routes/web.php"

log "Pruefe .env"
require_file "$APP_DIR/.env"

log "Hole aktuellen Stand aus origin/main"
git fetch origin
git reset --hard origin/main

log "Erzeuge notwendige Laravel-Verzeichnisse"
mkdir -p storage/app/public
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p bootstrap/cache

log "Installiere Composer-Abhaengigkeiten"
"$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction

if [ "$SKIP_BUILD" != "1" ]; then
    if command -v "$NPM_BIN" >/dev/null 2>&1; then
        log "Baue Frontend-Assets"
        "$NPM_BIN" ci
        "$NPM_BIN" run build
    else
        fail "npm nicht gefunden. Entweder Node.js installieren oder SKIP_BUILD=1 setzen und public/build separat deployen."
    fi
else
    log "Build uebersprungen (SKIP_BUILD=1)"
fi

log "Pruefe Vite-Manifest"
require_file "$APP_DIR/public/build/manifest.json"

log "Erzeuge storage:link falls noetig"
if [ ! -L "$APP_DIR/public/storage" ]; then
    "$PHP_BIN" artisan storage:link
fi

log "Setze Besitz und Rechte"
chown -R "$APP_USER:$APP_GROUP" "$APP_DIR"
chgrp -R "$WEB_GROUP" "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
find "$APP_DIR" -type d -exec chmod 755 {} \;
find "$APP_DIR" -type f -exec chmod 644 {} \;
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

log "Leere Laravel-Caches"
"$PHP_BIN" artisan optimize:clear

if [ "$RUN_MIGRATIONS" = "1" ]; then
    log "Fuehre Migrationen aus"
    "$PHP_BIN" artisan migrate --force
else
    log "Migrationen uebersprungen (RUN_MIGRATIONS=0)"
fi

log "Pruefe Routing"
"$PHP_BIN" artisan route:list >/dev/null

log "Deployment abgeschlossen"
cat <<EOF

Wichtige Hinweise:
- Dieses Skript deployt Code, vendor und public/build.
- Laufzeitdaten aus storage/app/public werden NICHT aus Git wiederhergestellt.
- .env muss bereits auf dem Server vorhanden sein.
- Migrationen laufen nur mit RUN_MIGRATIONS=1.
- migrate:fresh wird bewusst NICHT verwendet.

EOF
