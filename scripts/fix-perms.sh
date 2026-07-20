#!/usr/bin/env bash
set -euo pipefail

APP_PATH=/var/www/vhosts/clubano.de/app.clubano.de
DOMAIN_USER=olli1975
DOMAIN_GROUP=psacln

chown -R $DOMAIN_USER:$DOMAIN_GROUP "$APP_PATH"
find "$APP_PATH" -type d -exec chmod 2755 {} \;
find "$APP_PATH" -type f -exec chmod 0644 {} \;
chmod -R 2775 "$APP_PATH/storage" "$APP_PATH/bootstrap/cache"

# Debian/Ubuntu
setfacl -R -m u:www-data:rwX "$APP_PATH/storage" "$APP_PATH/bootstrap/cache" || true
setfacl -dR -m u:www-data:rwX "$APP_PATH/storage" "$APP_PATH/bootstrap/cache" || true

# RHEL/Alma/CentOS
setfacl -R -m u:apache:rwX "$APP_PATH/storage" "$APP_PATH/bootstrap/cache" || true
setfacl -dR -m u:apache:rwX "$APP_PATH/storage" "$APP_PATH/bootstrap/cache" || true
