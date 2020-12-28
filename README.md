# Contributing

Thanks for your interest in helping out! In order to be able to contribute, you will need an environment in which to develop. A detailed setup is provided below.

## What can I contribute to?

You're free to contribute to any open issue that's not being tackled by anyone at the moment. However, if there's not a specific issue for the thing you want to make, **make sure** to open an issue or create a thread in [the forums](https://foro.subtitulamos.tv) first to verify it's something that the community is interested in! Also, when contributing, please follow the contribution rules:

## Contribution rules

- Format the code using [php-cs-fixer](https://github.com/FriendsOfPHP/PHP-CS-Fixer) before creating a pull request. The rules are shipped with the code, on the `php-cs.dist` file that PHP-CS-Fixer will automatically pick up.
- The whole codebase is in english: please use english variable names/comments/commit messages and whatnot.

# Development Setup

## First-time setup

0. Clone the repo locally
1. Copy the `.env.example` file on the project root to `.env`
   - If needed, modify its contents to the ones correct for your usecase. The defaults should work fine.
2. Install Docker, if you don't have it (https://www.docker.com/get-started)
3. Install NodeJS 12.x, if you don't have it (https://nodejs.org/download/release/latest-v12.x/)
   - Higher versions might work, too
4. Run `npm install` from the directory `src/subtitulamos`
5. Execute `./dev.sh` on the repository root from a Linux shell (if you're on Windows, **Git Bash** should be available if you [installed Git](https://git-scm.com/)). This command will
   - build & start all the necessary Docker containers
   - start a [webpack](https://webpack.js.org/) watcher that builds all the CSS/JS in the page any time it changes
6. You're done!! You can access a subtitulamos instance at http://localhost:8080.
7. You can log in as user `root`, with password `root`

After this first setup, any time you want to start subtitulamos, just run `./dev.sh`!

## Nice to have: Proper IDE completion

With the basic setup above, you will be able to run a local server of subtitulamos, including all of its dependencies.

If you would like proper code intelligence in your IDE, you will need to either run your IDE inside the Docker container (for example, [VSCode's "Developing inside a Container"](https://code.visualstudio.com/docs/remote/containers)), or install PHP & Composer locally.

The 2nd option might be easier:

- Install [PHP](https://www.php.net/manual/en/install.php)
  - On Windows, an easy install can be achieved using [XAMPP](https://www.apachefriends.org/download.html). However, it'll install a bunch more things you might not want (like a local MySQL server). For a plain PHP install, just head over to the [PHP Windows Downloads](https://windows.php.net/download#php-7.4), download a compiled version (e.g VC15 x64 Thread Safe), extract it and add it to your PATH.
- Install [Composer](https://getcomposer.org/doc/00-intro.md)
- After that, run `composer install` on the `src/subtitulamos` directory. Done!

# Architecture

## Software used

- PHP 7.4
- MariaDB 10.4
- nginx + php7.4-fpm
- Redis
- [Sonic](https://github.com/valeriansaliou/sonic)

# Development

## Altering the database model

**Important notes**:

- For doing this, you should have PHP installed in your PC, see the "Proper IDE completion" section above for how. If you don't have PHP on your PC, you can execute these commands from the Docker container (run all of them prefixed, like so: `docker container exec -it subs_phpsv <command>`).
- All commands below must be ran from the `src/subtitulamos` directory

**Steps**:

1. To add new columns to a table, simply edit the model files inside `app/Entities`, adding new private field(s) with the `@ORM` annotation (see the Doctrine docs for details on the annotations).
   - If what you'd like is to create a new one, you can also just create a new file in that directory, following the correct annotations (look at other files for reference)
2. Once you're done with schema changes, run `./vendor/bin/doctrine orm:generate-ent ./` in order to create getter/setters for the field(s) you just created.
3. Create a databse migration for this change: `./vendor/bin/doctrine-migrations generate`.
4. Open the autogenerated file under `app/Migrations/` and tweak it as necessary.
5. Restart your PHP container to get the migration executed and applied to your database (e.g docker-compose down & docker-compose up), or run `./vendor/bin/doctrine-migrations migrate --no-interaction` **from the container**.
