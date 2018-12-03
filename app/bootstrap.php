<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017-2018 subtitulamos.tv
 */

require __DIR__.'/../vendor/autoload.php';

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\Setup;

// Load env variables from file
if (!getenv('SKIP_ENV_FILE')) {
    $dotenv = new Dotenv\Dotenv(__DIR__.'/..');
    $dotenv->load();
}

$env = getenv('ENVIRONMENT');
define('ENVIRONMENT_NAME', $env ? $env : 'dev');
define('DEBUG', getenv('DEBUG') == 'true');
define('ELASTICSEARCH_NAMESPACE', getenv('ELASTICSEARCH_NAMESPACE') ? getenv('ELASTICSEARCH_NAMESPACE') : 'ns');
define('SITE_URL', getenv('SITE_URL') ? getenv('SITE_URL') : 'https://www.subtitulamos.tv');

// Initialize Doctrine's ORM stuff
$config = Setup::createAnnotationMetadataConfiguration([__DIR__.'/Entities'], DEBUG, __DIR__.'/../tmp/doctrine', null, false);
$conn = [
    'driver' => 'pdo_mysql',
    'dbname' => getenv('DATABASE_NAME'),
    'user' => getenv('DATABASE_USER'),
    'password' => getenv('DATABASE_PASSWORD'),
    'host' => getenv('DATABASE_HOST')
];

// $entityManager is a global isntance and is used as such
$entityManager = EntityManager::create($conn, $config);
$entityManager->getConfiguration()->addEntityNamespace('App', '\App\Entities');
