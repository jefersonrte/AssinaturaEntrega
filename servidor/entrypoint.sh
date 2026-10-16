#!/bin/sh
# Garante que a pasta de dados (volume) pertença ao usuário do Apache antes de subir.
set -e
mkdir -p /var/www/html/data
chown -R www-data:www-data /var/www/html/data
exec apache2-foreground
