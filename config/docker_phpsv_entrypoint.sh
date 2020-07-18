#!/bin/sh
set -e

# Make sure /tmp/subs exists and is writable by everyone
mkdir -p /tmp/subs/doctrine
mkdir -p /tmp/subs/twig
chmod 777 -R /tmp/subs

php composer.phar install # Install dependencies, if needed
./app/console app:twig:clear-cache # Clear the Twig cache

while ! nc mariadb 3306 -e true; do sleep 1; done; # Wait until MariaDB is up
./vendor/bin/doctrine orm:generate-proxies # Regenerate all the ORM proxies

if [ "$1" = "php-fpm" ]; then
    ./app/console app:bots:synchronize # Make sure bots are up-to-date
fi

exec "$@" # Executes CMD
