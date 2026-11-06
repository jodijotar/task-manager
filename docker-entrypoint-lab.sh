#!/bin/sh
set -e
mkdir -p /var/www/html/uploads
chown -R www-data:www-data /var/www/html/uploads
chmod -R 775 /var/www/html/uploads

exec docker-php-entrypoint "$@"
