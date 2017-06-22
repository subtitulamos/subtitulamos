<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017 subtitulamos.tv
 */

require __DIR__.'/../vendor/autoload.php';

use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;

// Load env variables from file
if(getenv('ENVIRONMENT') !== 'production') {
    $dotenv = new Dotenv\Dotenv(__DIR__.'/..');
    $dotenv->load();
}

define('DEBUG', getenv('DEBUG') == 'true');

// Initialize Doctrine's ORM stuff
$config = Setup::createAnnotationMetadataConfiguration([__DIR__."/entities"], DEBUG, null, null, false);
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
