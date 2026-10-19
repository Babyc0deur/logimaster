#!/bin/sh
set -e
cd /var/www/html

# Render fournit le port dans $PORT
PORT="${PORT:-10000}"

# base SQLite sur le disque persistant, meme si la variable n'a pas ete renseignee sur Render
export DB_CONNECTION="${DB_CONNECTION:-sqlite}"
export DB_DATABASE="${DB_DATABASE:-/var/www/html/storage/app/database.sqlite}"
sed -ri "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
# derriere le proxy de Render : adresse reelle du visiteur
printf 'RemoteIPHeader X-Forwarded-For\n' > /etc/apache2/conf-available/remoteip.conf && a2enconf remoteip >/dev/null

chown -R www-data:www-data storage bootstrap/cache   # le disque persistant est monte en root
mkdir -p storage/app/public && touch "$DB_DATABASE"
chown -R www-data:www-data storage
php artisan storage:link --force >/dev/null 2>&1 || true
php artisan migrate --force
php artisan backup:restore --if-empty   # base vide (redemarrage sans disque persistant) : reprend la derniere sauvegarde externe
php artisan logimaster:install
# LOGIMASTER_RELOAD_DATA=true : remplace les donnees metier par database/data/snapshot.json.gz, une seule fois par version du fichier
# (l'empreinte est gardee sur le disque persistant : les redemarrages suivants n'ecrasent pas les saisies faites depuis)
if [ "${LOGIMASTER_RELOAD_DATA:-false}" = "true" ] && [ -f database/data/snapshot.json.gz ]; then
    SNAP_HASH="$(sha256sum database/data/snapshot.json.gz | cut -d' ' -f1)"
    if [ "$(cat storage/app/.snapshot-hash 2>/dev/null)" != "$SNAP_HASH" ]; then
        php artisan data:load --force && echo "$SNAP_HASH" > storage/app/.snapshot-hash
    fi
fi
php artisan data:load --if-empty   # charge database/data/snapshot.json.gz une seule fois, si le fichier existe et que la base n'a pas encore de vehicules
php artisan organisation:pres   # 10 PRES et leurs regions (sans effet si deja en place)
php artisan districts:geo --force   # chef-lieu de chaque district : le fichier database/data/districts_geo.json fait foi
php artisan indicators:backfill   # instantanes d'indicateurs des districts qui ont de l'activite (ils ne sont pas dans l'export de donnees)
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache && php artisan filament:optimize

# tout ce que les commandes ci-dessus (lancees en root) ont cree doit etre ecrivable par Apache (base SQLite, journal, caches, vues)
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
printf 'ServerName localhost
' > /etc/apache2/conf-available/servername.conf && a2enconf servername >/dev/null

# taches planifiees (alertes, rapports, indicateurs) : une fois par minute en arriere-plan
( while true; do php artisan schedule:run 2>&1 | grep -v 'No scheduled commands are ready to run' ; sleep 60; done ) &   # erreurs des tâches planifiées visibles dans les journaux

exec apache2-foreground
