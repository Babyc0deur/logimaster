#!/bin/sh
set -e
cd /var/www/html

# Render fournit le port dans $PORT
PORT="${PORT:-10000}"
sed -ri "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
# derriere le proxy de Render : adresse reelle du visiteur
printf 'RemoteIPHeader X-Forwarded-For\n' > /etc/apache2/conf-available/remoteip.conf && a2enconf remoteip >/dev/null

chown -R www-data:www-data storage bootstrap/cache   # le disque persistant est monte en root
mkdir -p storage/app/public && touch "${DB_DATABASE:-database/database.sqlite}"
chown -R www-data:www-data storage
php artisan storage:link --force >/dev/null 2>&1 || true
php artisan migrate --force
php artisan logimaster:install
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache

# taches planifiees (alertes, rapports, indicateurs) : une fois par minute en arriere-plan
( while true; do php artisan schedule:run >/dev/null 2>&1; sleep 60; done ) &

exec apache2-foreground
