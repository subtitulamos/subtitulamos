# Contributing

Thanks for your interest in helping out! In order to be able to contribute, you will need an environment in which to develop. A detailed setup is provided below.

## What can I contribute to?

You're free to contribute in any open issue that's not being tackled by anyone at the moment. However, if there's not an specific issue for the thing you want to make, **make sure** to open an issue or create a thread in [the forums](https://foro.subtitulamos.tv) first to verify it's something that the community is interested in! Also, when contributing, please follow the contribution rules:

## Contribution rules

- Format the code using [php-cs-fixer](https://github.com/FriendsOfPHP/PHP-CS-Fixer) before creating a pull request. The rules are shipped with the code, on the `php-cs.dist` file that PHP-CS-Fixer will automatically pick up.
- The whole codebase is in english: please use english variable names/comments/commit messages and whatnot.

# Setup

You need a bunch of things to be installed on the system in order to run the website (a Linux/UNIX machine is recommended, as most tools are not officially designed to run on other OS. Something like Vagrant is an alternative if your OS is not \*nix), so let's get on with that:

## Required software

> _Note_: Installation instructions for the following software are not provided since that would greatly increase the length of this guide. All of it is fairly popular and well-documented, so you should be able to easily find this stuff online. Do note that the versions specified in the list are not necessarily minimal requirements, it's just that the website has simply not been tested with releases prior to those, and it may not work correctly.

- PHP 7.1+
  - Plus the [phpredis](https://github.com/phpredis/phpredis) extension. Also available on PECL (`pecl install redis`).
- Redis 3+
- MySQL
- Nginx + php-fpm (you can find a sample Nginx config at the end) / Apache
- Composer
- NodeJS (latest stable version)
- ElasticSearch 5.5+ [[Instructions]](https://www.elastic.co/guide/en/elasticsearch/reference/5.5/_installation.html)
  - ElasticSearch requires a recent JDK 8 version. You may need to [use a backport](https://linux-tips.com/t/how-to-install-java-8-on-debian-jessie/349/2) from a newer OS release.
- Go 1.8+

## First time setup

### Basic setup

The first thing to do after cloning the project itself is downloading the project dependencies. You can achieve this by running `composer install` and `npm install` afterwards. This step may take a while.

While you're downloading, you can copy the `.env.example` file on the project root to `.env`, and modify its contents to the ones correct for your MySQL setup (username, pwd, table name, etc). Alternatively, you can set each of the variables as a true environment variable on your system.

After `.env` file has been set up and the composer dependencies have all been installed, you must run, from the project root:

- `./vendor/bin/doctrine orm:schema:update --force` to create the database schema required by the website
- `php app/console app:bots:synchronize` to create the bot users.

At this point, the website will work, but with no styling at all. For that, you will need to compile the javascript & css. Executing `npm run build` will take care of it! Now it truly works. Well, except the translation area...

### Realtime translation - Setup

The website uses realtime translation based on websockets. Since PHP is not really ideal for the long-running processes that websocket technology requires, a simple Go server is used instead. To get it, run `go get github.com/subtitulamos/subtitulamos-translate` (You may need to set `GOPATH` environment variable if you didn't already). The `go get` command specific above will both download the source from Github and compile the binary. To quickly get the server started, you can use `$GOPATH/bin/subtitulamos-translate -redis-pubsub-env <envname>`, where \<envname\> should equal the value of your `ENVIRONMENT` env variable. Once this is done, you will be able to fully translate.

_Note_: You will generally not need to modify the Go server since all the relevant logic is on PHP, and it is just broadcasted via Redis pub-sub system to the Go process, which simply relies it to the connected clients.

## Development

Once set up, you will see that any modification to a javascript or CSS file requires you to rebuild said file. To greatly speed up development, you can run `npm run watch`, which will start a webpack and postcss watcher and automatically compile the JS/CSS the moment you change them.

Aditionally, it may be annoying to run the realtime translation service every time. For that, [supervisord](http://supervisord.org/) is actually a fairly simple and great way to automatically launch/restart the binary. Once again there're better tutorials out in the web than anything that could be written here, specially since the rt translation binary is so simple and doesn't really require much setup besides passing the args (for detail on those, see [the docs](https://github.com/subtitulamos/subtitulamos-translate).

### Altering the database model

To add new columns to the database, simply edit the model files (or create a new one!) inside `app/Entities`, adding new private field(s) with the `@ORM` annotation (see the Doctrine docs for details on the annotations). Once that's done, run `./vendor/bin/doctrine orm:generate-ent ./` to generate getter/setters for the field(s) you just created. Afterwards, you'll probably want to update your database, there's two ways:

- See a diff with your DB schema `./vendor/bin/doctrine orm:sch:update --dump-sql`, and run these queries manually (This is the safe option).
- Update the DB schema (caution, this can destroy data if you removed a column/table): `./vendor/bin/doctrine orm:sch:update --force`.

## Appendix: Example Nginx config

Here's an Nginx config that should work with the website provided it's running on a server/vagrant box. You need to at least replace the `root` and and `upstream` parts with the right paths, and of course any ports you may have changed from the default.

    server {
        listen 80;
        server_name subtitulamos.test;

        root /path/to/subtitulamos/files;

        location / {
            index index.php index.html;
            try_files $uri $uri/ /index.php?$query_string;
        }

        # Pass the php scripts to declared upstream sv
        location ~ \.php(/|$) {
            # Unmodified fastcgi_params from nginx distribution.
            include fastcgi_params;
            fastcgi_split_path_info ^(.+\.php)(/.*)$;
            fastcgi_param PATH_INFO $fastcgi_path_info;
            fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
            fastcgi_param DOCUMENT_ROOT $realpath_root;
            try_files $uri $uri/ /index.php$is_args$args;
            fastcgi_pass php;
        }

        location /translation-rt {
            proxy_pass http://127.0.0.1:8080;
            proxy_redirect off;

            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header Host $http_host;
            proxy_set_header X-NginX-Proxy true;

            # For websockets
            proxy_http_version 1.1;
            proxy_set_header Upgrade $http_upgrade;
            proxy_set_header Connection "upgrade";
        }
    }

    # Define fastcgi pass upstream (php-fpm must be configured to listen on a unix socket)
    upstream php {
        server unix:</path/to/php-fpm/socket/file>;
    }

### Appendix: Running with in-built PHP server
You can run the PHP side of things usiung the built-in PHP server: `php -S localhost:8888 -t ./public`.
Make sure to set SITE_URL to http://localhost:8888 or else things won't load!
