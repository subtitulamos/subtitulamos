<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017-2018 subtitulamos.tv
 */

use App\Services\AssetManager;
use App\Services\Auth;
use App\Services\Langs;
use App\Services\Translation;
use Cocur\Slugify\Slugify;
use Psr\Container\ContainerInterface;

require '../app/bootstrap.php';

// Start session & boot app
session_start();

function feature_on($name)
{
    $v = getenv($name.'_ENABLED');
    return $v == 'true' || $v == '1' || $v == 'yes';
}

// $app is an instance of \Slim\App, wrapped by PHP-DI to insert its own container
$app = new class() extends \DI\Bridge\Slim\App {
    protected function configureContainer(\DI\ContainerBuilder $builder)
    {
        global $entityManager;

        // Slim configuration
        $builder->addDefinitions([
            'settings.displayErrorDetails' => DEBUG
        ]);

        $builder->addDefinitions([
            \Doctrine\ORM\EntityManager::class => function (ContainerInterface $c) use ($entityManager) {
                return $entityManager;
            },
            \App\Services\Auth::class => function (ContainerInterface $c) use ($entityManager) {
                return new Auth($entityManager);
            },
            \App\Services\AssetManager::class => function (ContainerInterface $c) {
                return new AssetManager();
            },
            \App\Services\Translation::class => function (ContainerInterface $c) use ($entityManager) {
                return new Translation($entityManager);
            },
            \Cocur\Slugify\SlugifyInterface::class => function (ContainerInterface $c) {
                return new Slugify();
            },
            \Elasticsearch\Client::class => function (ContainerInterface $c) {
                return \Elasticsearch\ClientBuilder::create()->build();
            },
            \Slim\Views\Twig::class => function (ContainerInterface $c) {
                $twig = new \Slim\Views\Twig(__DIR__.'/../resources/templates', [
                    'cache' => __DIR__.'/../tmp/twig',
                    'strict_variables' => getenv('TWIG_STRICT') || true,
                    'debug' => DEBUG
                ]);

                $basePath = rtrim(str_ireplace('index.php', '', $c->get('request')->getUri()->getBasePath()), '/');
                $twig->addExtension(new \Slim\Views\TwigExtension(
                    $c->get('router'),
                    $basePath
                ));

                if (DEBUG === true) {
                    $twig->addExtension(new Twig_Extension_Debug());
                }

                $twigEnv = $twig->getEnvironment();
                $twigEnv->addGlobal('SITE_URL', SITE_URL);
                $twigEnv->addGlobal('ENVIRONMENT_NAME', ENVIRONMENT_NAME);
                $twigEnv->addGlobal('LANG_LIST', Langs::LANG_LIST);
                $twigEnv->addGlobal('LANG_NAMES', Langs::LANG_NAMES);

                $auth = $c->get('App\Services\Auth');
                $twigEnv->addGlobal('auth', $auth->getTwigInterface());
                $twigEnv->addFunction(new Twig_Function('feature_on', 'feature_on'));

                $assetMgr = $c->get('App\Services\AssetManager');
                $twigEnv->addFunction(new Twig_Function('css_versioned_name', function ($name) use (&$assetMgr) {
                    return $assetMgr->getCssVersionedName($name);
                }));
                $twigEnv->addFunction(new Twig_Function('webpack_versioned_name', function ($name) use (&$assetMgr) {
                    return $assetMgr->getWebpackVersionedName($name);
                }));

                return $twig;
            },

        ]);
    }
};

require '../app/routes.php';
addRoutes($app, $entityManager);

// Run app
$app->run();
