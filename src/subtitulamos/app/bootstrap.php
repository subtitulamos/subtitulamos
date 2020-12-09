<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

require __DIR__.'/../vendor/autoload.php';

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\Setup;

function getEnvOrDefault($varname, $default)
{
    $envValue = $_ENV[$varname] ?? null;
    if (!$envValue) {
        $envValue = $default;
    }

    return $envValue;
}

// Load env variables from file
$dotenv = Dotenv\Dotenv::createMutable(__DIR__.'/..');
$dotenv->load();
$dotenv->required(['MARIADB_DATABASE', 'MARIADB_USER', 'MARIADB_PASSWORD', 'MARIADB_HOST']);

define('ENVIRONMENT_NAME', getEnvOrDefault('ENVIRONMENT_NAME', 'dev'));
define('DEBUG', in_array(getEnvOrDefault('DEBUG', 'false'), ['true', 'y', 'yes', 'on']));
define('SITE_URL', getEnvOrDefault('SITE_URL', 'https://www.subtitulamos.tv'));
define('SUBS_TMP_DIR', getEnvOrDefault('SUBS_TMP_DIR', '/tmp/subs'));
define('SONIC_PASSWORD', getEnvOrDefault('SONIC_PASSWORD', 'SecretPassword'));
define('REDIS_HOST', getEnvOrDefault('REDIS_HOST', 'redis'));
define('REDIS_PORT', getEnvOrDefault('REDIS_PORT', 6379));
// Max amount of time a user has to edit/delete their comment after writing it
define('MAX_USER_EDIT_SECONDS', (int)getEnvOrDefault('MAX_USER_EDIT_SECONDS', 60));

if (DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', true);
}

// Initialize Doctrine's ORM stuff
$config = Setup::createAnnotationMetadataConfiguration([__DIR__.'/Entities'], DEBUG, SUBS_TMP_DIR.'/doctrine', null, false);
$conn = [
    'driver' => 'pdo_mysql',
    'dbname' => $_ENV['MARIADB_DATABASE'],
    'user' => $_ENV['MARIADB_USER'],
    'password' => $_ENV['MARIADB_PASSWORD'],
    'host' => $_ENV['MARIADB_HOST'],
    'unix_socket' => getEnvOrDefault('MARIADB_SOCKETNAME', null)
];

// $entityManager is a global isntance and is used as such
$entityManager = EntityManager::create($conn, $config);
$entityManager->getConfiguration()->addEntityNamespace('App', '\App\Entities');
