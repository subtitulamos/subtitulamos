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

// $app is an instance of \Slim\App, wrapped by PHP-DI to insert its own container
$app = new class() extends \DI\Bridge\Slim\App {
    protected function configureContainer(\DI\ContainerBuilder $builder)
    {
        global $entityManager;
        
        // Slim configuration
        $builder->addDefinitions([
            'settings.displayErrorDetails' => true
        ]);

        $builder->addDefinitions([
            \Doctrine\ORM\EntityManager::class => function (ContainerInterface $c) use ($entityManager) {
                return $entityManager;
            },
            \App\Services\Auth::class => function (ContainerInterface $c) use ($entityManager) {
                return new \App\Services\Auth($entityManager);
            },
            \Cocur\Slugify\SlugifyInterface::class => function (ContainerInterface $c) {
                return new Cocur\Slugify\Slugify();
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

                $auth = $c->get('App\Services\Auth');
                $twig->getEnvironment()->addGlobal("auth", new class($auth) {
                    public function __construct(&$auth)
                    {
                        $this->auth = $auth;
                    }

                    public function logged()
                    {
                        return $this->auth->isLogged();
                    }

                    public function has_role($role)
                    {
                        return $this->auth->hasRole($role);
                    }

                    public function user()
                    {
                        return $this->auth->getUser();
                    }
                }
                );
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

$app->get('/search/popular', ['\App\Controllers\SearchController', 'listPopular']);
$app->get('/search/uploads', ['\App\Controllers\SearchController', 'listRecentUploads']);

$app->post('/translate', ['\App\Controllers\TranslationController', 'newTranslation'])->add($needsRoles('ROLE_USER'));
$app->get('/translate/{id}', ['\App\Controllers\TranslationController', 'view'])->setName('translation')->add($needsRoles('ROLE_USER'));
$app->get('/translate/{id}/page/{page}', ['\App\Controllers\TranslationController', 'listSequences'])->add($needsRoles('ROLE_USER'));
$app->post('/translate/{id}/open', ['\App\Controllers\TranslationController', 'open'])->add($needsRoles('ROLE_USER'));
$app->post('/translate/{id}/close', ['\App\Controllers\TranslationController', 'close'])->add($needsRoles('ROLE_USER'));
$app->post('/translate/{id}/save', ['\App\Controllers\TranslationController', 'save'])->add($needsRoles('ROLE_USER'));
$app->post('/translate/{id}/create', ['\App\Controllers\TranslationController', 'create'])->add($needsRoles('ROLE_USER'));
$app->post('/translate/{id}/lock', ['\App\Controllers\TranslationController', 'lockToggle'])->add($needsRoles('ROLE_TH'));

$app->get('/translate/{subId}/comments', ['\App\Controllers\SubtitleCommentsController', 'list'])->add($needsRoles('ROLE_USER'));
$app->post('/translate/{subId}/comments/submit', ['\App\Controllers\SubtitleCommentsController', 'create'])->add($needsRoles('ROLE_USER'));
/*
$app->post('/translate/{subId}/comments/{cId}/edit', ['\App\Controllers\EpisodeCommentsController', 'edit'])->add($needsRoles('ROLE_USER'));
$app->post('/translate/{subId}/comments/{cId}/delete', ['\App\Controllers\EpisodeCommentsController', 'delete'])->add($needsRoles('ROLE_USER'));
*/

$app->get('/episodes/{epId}/resync', ['\App\Controllers\UploadResyncController', 'view'])->add($needsRoles('ROLE_USER'));
$app->post('/episodes/{epId}/resync', ['\App\Controllers\UploadResyncController', 'do'])->add($needsRoles('ROLE_USER'));
$app->get('/episodes/{epId}/comments', ['\App\Controllers\EpisodeCommentsController', 'list']);
$app->post('/episodes/{epId}/comments/submit', ['\App\Controllers\EpisodeCommentsController', 'create'])->add($needsRoles('ROLE_USER'));
/*
$app->post('/episodes/{epId}/comments/{cId}/edit', ['\App\Controllers\EpisodeCommentsController', 'edit'])->add($needsRoles('ROLE_USER'));
$app->post('/episodes/{epId}/comments/{cId}/delete', ['\App\Controllers\EpisodeCommentsController', 'delete'])->add($needsRoles('ROLE_USER'));
$app->post('/episodes/{id}/comments/{cid}/pin', ['\App\Controllers\TranslationController', 'pin'])->add($needsRoles('ROLE_MOD'));
*/
$app->get('/episodes/{id}[/{slug}]', ['\App\Controllers\EpisodeController', 'view'])->setName('episode');


$app->get('/download/{id}', ['\App\Controllers\DownloadController', 'download']);

$app->post('/login', ['\App\Controllers\LoginController', 'login']);
$app->post('/register', ['\App\Controllers\LoginController', 'register']);
$app->get('/logout', ['\App\Controllers\LoginController', 'logout']);

// Run app
$app->run();
