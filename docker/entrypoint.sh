#!/bin/sh
# Makes sure the data folder exists and belongs to the web server, then starts Apache.
set -e
DATA=/var/www/html/App_Data
mkdir -p "$DATA/sessions" "$DATA/uploads" "$DATA/thumbs"
chown -R www-data:www-data "$DATA"
chmod 750 "$DATA" "$DATA/sessions"
exec docker-php-entrypoint "$@"
