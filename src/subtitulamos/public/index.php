<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

use App\Services\AssetManager;
use App\Services\Auth;
use App\Services\Langs;
use App\Services\Translation;
use Cocur\Slugify\Slugify;
use Psr\Container\ContainerInterface;

require '../app/bootstrap.php';

// Start session & boot app
session_set_cookie_params([
    'httponly' => true
]);
session_start();

function feature_on($name)
{
    $v = $_ENV[$name.'_ENABLED'] ?? 'false';
    return $v == 'true' || $v == '1' || $v == 'yes';
}

$builder = new \DI\ContainerBuilder();
if (!DEBUG) {
    $builder->enableCompilation(__DIR__.'/tmp');
    $builder->writeProxiesToFile(true, __DIR__.'/tmp/proxies');
}

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
    \Slim\Views\Twig::class => function (ContainerInterface $c) {
        $twig = \Slim\Views\Twig::create(__DIR__.'/../resources/templates', [
            'cache' => SUBS_TMP_DIR.'/twig',
            'strict_variables' => $_ENV['TWIG_STRICT'] ?? true,
            'debug' => DEBUG
        ]);

        if (DEBUG === true) {
            $twig->addExtension(new \Twig\Extension\DebugExtension());
        }

        $twigEnv = $twig->getEnvironment();
        $twigEnv->addGlobal('SITE_URL', SITE_URL);
        $twigEnv->addGlobal('ENVIRONMENT_NAME', ENVIRONMENT_NAME);
        $twigEnv->addGlobal('LANG_LIST', Langs::LANG_LIST);
        $twigEnv->addGlobal('LANG_NAMES', Langs::LANG_NAMES);

        $auth = $c->get('App\Services\Auth');
        $twigEnv->addGlobal('auth', $auth->getTwigInterface());
        $twigEnv->addFunction(new \Twig\TwigFunction('feature_on', 'feature_on'));

        $assetMgr = $c->get('App\Services\AssetManager');
        $twigEnv->addFunction(new \Twig\TwigFunction('webpack_versioned_name', function ($name) use (&$assetMgr) {
            return $assetMgr->getWebpackVersionedName($name);
        }));

        return $twig;
    },
    \App\Services\UrlHelper::class => function (ContainerInterface $c) {
        global $app;
        return new \App\Services\UrlHelper($app->getRouteCollector()->getRouteParser(), $app->getResponseFactory());
    }
]);

$container = $builder->build();

// $app is an instance of \Slim\App, wrapped by PHP-DI
$app = \DI\Bridge\Slim\Bridge::create($container);

require '../app/middlewares.php';
addMiddlewares($app);

require '../app/routes.php';
addRoutes($app);

// Error middleware must always be the LAST middleware to be added
$app->addErrorMiddleware(DEBUG, true, true);
$app->run();
