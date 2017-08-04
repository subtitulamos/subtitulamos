<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017 subtitulamos.tv
 */

use \Psr\Container\ContainerInterface;
use App\Services\Auth;

require '../app/bootstrap.php';

// Start session & boot app
session_start();

function feature_on($name)
{
    $v = getenv($name . "_ENABLED");
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
                return new \App\Services\Auth($entityManager);
            },
            \App\Services\AssetManager::class => function (ContainerInterface $c) {
                return new \App\Services\AssetManager();
            },
            \Cocur\Slugify\SlugifyInterface::class => function (ContainerInterface $c) {
                return new Cocur\Slugify\Slugify();
            },
            \Elasticsearch\Client::class => function (ContainerInterface $c) {
                return \Elasticsearch\ClientBuilder::create()->build();
            },
            \Slim\Views\Twig::class => function (ContainerInterface $c) {
                $twig = new \Slim\Views\Twig(__DIR__ . '/../resources/templates', [
                    'cache' => __DIR__ . '/../tmp',
                    'strict_variables' => getenv('TWIG_STRICT') || true,
                    'debug' => DEBUG
                ]);

                $basePath = rtrim(str_ireplace('index.php', '', $c->get('request')->getUri()->getBasePath()), '/');
                $twig->addExtension(new \Slim\Views\TwigExtension(
                    $c->get('router'),
                    $basePath
                ));

                $twigEnv = $twig->getEnvironment();
                $twigEnv->addGlobal("ENVIRONMENT_NAME", ENVIRONMENT_NAME);

                $auth = $c->get('App\Services\Auth');
                $twigEnv->addGlobal("auth", $auth->getTwigInterface());
                $twigEnv->addFunction(new Twig_Function('feature_on', 'feature_on'));

                $assetMgr = $c->get('App\Services\AssetManager');
                $twigEnv->addFunction(new Twig_Function('asset_versioned_name', function ($name) use (&$assetMgr) {
                    return $assetMgr->getAssetVersionedName($name);
                }));
                $twigEnv->addFunction(new Twig_Function('webpack_versioned_name', function ($name) use (&$assetMgr) {
                    return $assetMgr->getWebpackVersionedName($name);
                }));

                return $twig;
            },

        ]);
    }
};

$needsRoles = function ($roles) use ($app) {
    return new App\Middleware\RestrictedMiddleware($app->getContainer(), $roles);
};

// TODO: Extract to own file
$app->add(new \App\Middleware\SessionMiddleware($app->getContainer(), $entityManager));
$app->get('/', ['\App\Controllers\HomeController', 'view']);
$app->get('/upload', ['\App\Controllers\UploadController', 'view'])->add($needsRoles('ROLE_USER'));
$app->post('/upload', ['\App\Controllers\UploadController', 'do'])->add($needsRoles('ROLE_USER'));

$app->get('/search/query', ['\App\Controllers\SearchController', 'query']);
$app->get('/search/popular', ['\App\Controllers\SearchController', 'listPopular']);
$app->get('/search/uploads', ['\App\Controllers\SearchController', 'listRecentUploads']);
$app->get('/search/modified', ['\App\Controllers\SearchController', 'listRecentChanged']);
$app->get('/search/completed', ['\App\Controllers\SearchController', 'listRecentCompleted']);
$app->get('/search/paused', ['\App\Controllers\SearchController', 'listPaused'])->add($needsRoles('ROLE_TH'));

$app->post('/subtitles/translate', ['\App\Controllers\TranslationController', 'newTranslation'])->add($needsRoles('ROLE_USER'));
$app->get('/subtitles/{id}/translate', ['\App\Controllers\TranslationController', 'view'])->setName('translation')->add($needsRoles('ROLE_USER'));
$app->get('/subtitles/{id}/translate/page/{page}', ['\App\Controllers\TranslationController', 'listSequences'])->add($needsRoles('ROLE_USER'));
$app->get('/subtitles/{id}/translate/open-list', ['\App\Controllers\TranslationController', 'listOpenLocks'])->add($needsRoles('ROLE_TH'));
$app->delete('/subtitles/{id}/translate/open-list/{lockId}', ['\App\Controllers\TranslationController', 'releaseLock'])->add($needsRoles('ROLE_TH'));
$app->post('/subtitles/{id}/translate/open', ['\App\Controllers\TranslationController', 'open'])->add($needsRoles('ROLE_USER'));
$app->post('/subtitles/{id}/translate/close', ['\App\Controllers\TranslationController', 'close'])->add($needsRoles('ROLE_USER'));
$app->post('/subtitles/{id}/translate/save', ['\App\Controllers\TranslationController', 'save'])->add($needsRoles('ROLE_USER'));
$app->post('/subtitles/{id}/translate/create', ['\App\Controllers\TranslationController', 'create'])->add($needsRoles('ROLE_USER'));
$app->post('/subtitles/{id}/translate/lock', ['\App\Controllers\TranslationController', 'lockToggle'])->add($needsRoles('ROLE_TH'));


$app->get('/subtitles/{subId}/translate/comments', ['\App\Controllers\SubtitleCommentsController', 'list'])->add($needsRoles('ROLE_USER'));
$app->post('/subtitles/{subId}/translate/comments/submit', ['\App\Controllers\SubtitleCommentsController', 'create'])->add($needsRoles('ROLE_USER'));
$app->delete('/subtitles/{subId}/translate/comments/{cId}', ['\App\Controllers\SubtitleCommentsController', 'delete'])->add($needsRoles('ROLE_MOD'));
/*
$app->post('/translate/{subId}/comments/{cId}/edit', ['\App\Controllers\SubtitleCommentsController', 'edit'])->add($needsRoles('ROLE_USER'));
 */

$app->get('/subtitles/{subId}/delete', ['\App\Controllers\SubtitleController', 'delete'])->add($needsRoles('ROLE_MOD'));
$app->get('/subtitles/{subId}/pause', ['\App\Controllers\SubtitleController', 'pause'])->add($needsRoles('ROLE_TH'));
$app->get('/subtitles/{subId}/unpause', ['\App\Controllers\SubtitleController', 'unpause'])->add($needsRoles('ROLE_TH'));
$app->get('/subtitles/{subId}/hammer', ['\App\Controllers\SubtitleController', 'viewHammer'])->add($needsRoles('ROLE_MOD'));
$app->post('/subtitles/{subId}/hammer', ['\App\Controllers\SubtitleController', 'doHammer'])->add($needsRoles('ROLE_MOD'));

$app->get('/episodes/{epId}/resync', ['\App\Controllers\UploadResyncController', 'view'])->add($needsRoles('ROLE_USER'));
$app->post('/episodes/{epId}/resync', ['\App\Controllers\UploadResyncController', 'do'])->add($needsRoles('ROLE_USER'));
$app->get('/episodes/{epId}/comments', ['\App\Controllers\EpisodeCommentsController', 'list']);
$app->post('/episodes/{epId}/comments/submit', ['\App\Controllers\EpisodeCommentsController', 'create'])->add($needsRoles('ROLE_USER'));

$app->delete('/episodes/{epId}/comments/{cId}', ['\App\Controllers\EpisodeCommentsController', 'delete'])->add($needsRoles('ROLE_USER'));
/*
$app->post('/episodes/{epId}/comments/{cId}/edit', ['\App\Controllers\EpisodeCommentsController', 'edit'])->add($needsRoles('ROLE_USER'));
$app->post('/episodes/{id}/comments/{cid}/pin', ['\App\Controllers\TranslationController', 'pin'])->add($needsRoles('ROLE_MOD'));
 */
$app->get('/episodes/{id}[/{slug}]', ['\App\Controllers\EpisodeController', 'view'])->setName('episode');
$app->get('/shows/{showId}[/{season}]', ['\App\Controllers\ShowController', 'view'])->setName('show');
$app->get('/subtitles/{id}/download', ['\App\Controllers\DownloadController', 'download']);

$app->get('/login', ['\App\Controllers\LoginController', 'viewLogin']);
$app->post('/login', ['\App\Controllers\LoginController', 'login']);
$app->post('/register', ['\App\Controllers\LoginController', 'register']);
$app->get('/logout', ['\App\Controllers\LoginController', 'logout']);

$app->get('/rules[/{type}]', ['\App\Controllers\RulesController', 'view']);
$app->get('/users/{userId}', ['\App\Controllers\UserController', 'publicProfile'])->setName('user');
$app->get('/me', ['\App\Controllers\UserController', 'viewSettings'])->setName('settings')->add($needsRoles('ROLE_USER'));
$app->post('/me', ['\App\Controllers\UserController', 'saveSettings'])->add($needsRoles('ROLE_USER'));

$app->get('/dmca', ['\App\Controllers\TermsController', 'viewDMCA']);
// Run app
$app->run();
