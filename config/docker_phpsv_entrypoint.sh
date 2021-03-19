#!/bin/sh
set -e

# Make sure /tmp/subs exists and is writable by everyone
mkdir -p /tmp/subs/doctrine
mkdir -p /tmp/subs/twig
chmod 777 -R /tmp/subs

php composer.phar install --no-dev # Install dependencies, if needed. Only prod ones

./app/console app:twig:clear-cache # Clear the Twig cache
./app/console app:static:create-robots-file # Create Robots files

if [ ! -S /var/run/mysqld/mysqld.sock ]; then
    # Wait until MariaDB is up
    while ! nc mariadb 3306 -e true; do
        sleep 1;
    done;
fi

./vendor/bin/doctrine orm:generate-proxies # Regenerate all the ORM proxies
./vendor/bin/doctrine-migrations migrate --no-interaction # Make sure we're up-to-date on DB migrations
./app/console app:bots:synchronize # Make sure bots are up-to-date
./app/console app:search:regenerate-index # Make sure search index is generated
./app/console app:generate-sitemap # Gen sitemap

exec "$@" # Executes CMD
