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
    $envValue = getenv($varname);
    if (!$envValue) {
        $envValue = $default;
    }

    return $envValue;
}

// Load env variables from file
if (!getenv('SKIP_ENV_FILE')) {
    $dotenv = new Dotenv\Dotenv(__DIR__.'/..');
    $dotenv->load();
}

define('ENVIRONMENT_NAME', getEnvOrDefault('ENVIRONMENT', 'dev'));
define('DEBUG', getEnvOrDefault('DEBUG', 'true'));
define('ELASTICSEARCH_NAMESPACE', getEnvOrDefault('ELASTICSEARCH_NAMESPACE', 'ns'));
define('SITE_URL', getEnvOrDefault('SITE_URL', 'https://www.subtitulamos.tv'));
define('SUBS_TMP_DIR', getEnvOrDefault('SUBS_TMP_DIR', '/tmp/subs'));

// Initialize Doctrine's ORM stuff
$config = Setup::createAnnotationMetadataConfiguration([__DIR__.'/Entities'], DEBUG, SUBS_TMP_DIR.'/doctrine', null, false);
$conn = [
    'driver' => 'pdo_mysql',
    'dbname' => getenv('MARIADB_DATABASE'),
    'user' => getenv('MARIADB_USER'),
    'password' => getenv('MARIADB_PASSWORD'),
    'host' => getenv('MARIADB_HOST')
];

// $entityManager is a global isntance and is used as such
$entityManager = EntityManager::create($conn, $config);
$entityManager->getConfiguration()->addEntityNamespace('App', '\App\Entities');
