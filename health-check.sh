#!/bin/bash

APP_DIR="/var/www/vhosts/clubano.de/app.clubano.de"
ENV_FILE="$APP_DIR/.env"
WEBUSER="www-data"
ERROR=0

echo ""
echo "🔍 Starte Sicherheits-Check für Clubano"
echo "========================================"

# 1. .env prüfen
echo -n "📄 Prüfe .env-Dateirechte ... "
if [ -f "$ENV_FILE" ]; then
    PERM=$(stat -c "%a" "$ENV_FILE")
    OWNER=$(stat -c "%U:%G" "$ENV_FILE")
    if [[ "$PERM" == "640" || "$PERM" == "600" ]]; then
        echo "✅ ($PERM | $OWNER)"
    else
        echo "❌ ($PERM | $OWNER) → Bitte setze: chmod 640 .env"
        ERROR=1
    fi
else
    echo "❌ .env-Datei fehlt!"
    ERROR=1
fi

# 2. Schreibrechte in storage & cache prüfen
for DIR in "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"; do
    echo -n "📂 Prüfe Schreibrechte für $(basename "$DIR") ... "
    TESTFILE="$DIR/test_$(date +%s).tmp"
    touch "$TESTFILE" 2>/dev/null
    if [ $? -eq 0 ]; then
        echo "✅"
        rm -f "$TESTFILE"
    else
        echo "❌ Keine Schreibrechte für $DIR"
        ERROR=1
    fi
done

# 3. Suche nach 777-Rechten
echo -n "🔒 Suche nach 777-Rechten im Projekt ... "
FOUND=$(find "$APP_DIR" -type d -perm 0777 | wc -l)
if [ "$FOUND" -eq 0 ]; then
    echo "✅ Keine unsicheren Verzeichnisse gefunden"
else
    echo "❌ $FOUND Verzeichnisse mit 777 gefunden!"
    find "$APP_DIR" -type d -perm 0777
    ERROR=1
fi

# 4. PHP-Benutzer prüfen (über laufende FPM-Prozesse)
echo -n "👤 PHP läuft als Benutzer: "
PHPUSER=$(ps aux | grep "php-fpm: pool" | grep -v root | awk '{print $1}' | head -n 1)
if [ "$PHPUSER" == "$WEBUSER" ]; then
    echo "$PHPUSER ✅"
else
    echo "$PHPUSER ❌ (Erwartet: $WEBUSER)"
    ERROR=1
fi

echo "========================================"
if [ "$ERROR" -eq 0 ]; then
    echo "✅ Sicherheitsprüfung bestanden."
else
    echo "⚠️  Sicherheitsprobleme erkannt. Bitte prüfen."
fi
