#!/bin/bash
set -e

# Render (and most PaaS) inject a $PORT and probe that port on 0.0.0.0. The
# php:apache base image listens on port 80 only, which Render cannot always
# detect, leaving the service "live" internally but returning 502 externally.
# Bind Apache to IPv4 all-interfaces on both 80 and $PORT so detection always
# succeeds.
: "${PORT:=80}"

# Force IPv4 binding for the default port.
sed -ri 's/^Listen 80$/Listen 0.0.0.0:80/' /etc/apache2/ports.conf

if [ "$PORT" != "80" ]; then
    echo "Listen 0.0.0.0:${PORT}" >> /etc/apache2/ports.conf
    sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:80 *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
fi

# Silence the "Could not reliably determine FQDN" warning.
echo "ServerName localhost" >> /etc/apache2/apache2.conf

exec docker-php-entrypoint apache2-foreground
