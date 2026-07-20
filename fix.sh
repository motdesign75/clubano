#!/bin/bash

# Projektpfad anpassen
PROJECT_DIR="/var/www/vhosts/clubano.de/app.clubano.de"
USER="olli1975"
GROUP="psacln" # In der Regel psacln unter Plesk

echo "📂 Setze Besitzerrechte auf $USER:$GROUP..."
chown -R $USER:$GROUP $PROJECT_DIR

echo "🔒 Setze Dateiberechtigungen..."
find $PROJECT_DIR -type f -exec chmod 644 {} \;
find $PROJECT_DIR -type d -exec chmod 755 {} \;

echo "🗂 Setze Rechte für storage und bootstrap/cache..."
chmod -R ug+rwx $PROJECT_DIR/storage
chmod -R ug+rwx $PROJECT_DIR/bootstrap/cache

echo "🧹 Leere Laravel-Caches..."
cd $PROJECT_DIR
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

echo "📦 Setze Berechtigungen für node_modules und vendor..."
chown -R $USER:$GROUP $PROJECT_DIR/node_modules
chown -R $USER:$GROUP $PROJECT_DIR/vendor

echo "✅ Wartung abgeschlossen. Laravel sollte wieder laufen."
