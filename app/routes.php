<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017-2018 subtitulamos.tv
 */

/*
* Declare all routes in this file, inside the function.
* Called from index.php to load them
*/
function addRoutes(&$app, &$entityManager)
{
    $needsRole = function ($role) use ($app) {
        return new App\Middleware\RestrictedMiddleware($app->getContainer(), $role);
    };

    $app->add(new \App\Middleware\SessionMiddleware($app->getContainer(), $entityManager));
    $app->get('/', ['\App\Controllers\HomeController', 'view']);
    $app->get('/upload', ['\App\Controllers\UploadController', 'view'])->add($needsRole('ROLE_USER'));
    $app->post('/upload', ['\App\Controllers\UploadController', 'do'])->add($needsRole('ROLE_USER'));

    $app->get('/search/query', ['\App\Controllers\SearchController', 'query']);
    $app->get('/search/popular', ['\App\Controllers\SearchController', 'listPopular']);
    $app->get('/search/uploads', ['\App\Controllers\SearchController', 'listRecentUploads']);
    $app->get('/search/modified', ['\App\Controllers\SearchController', 'listRecentChanged']);
    $app->get('/search/completed', ['\App\Controllers\SearchController', 'listRecentCompleted']);
    $app->get('/search/resyncs', ['\App\Controllers\SearchController', 'listRecentResyncs']);
    $app->get('/search/paused', ['\App\Controllers\SearchController', 'listPaused'])->add($needsRole('ROLE_TT'));

    $app->post('/subtitles/translate', ['\App\Controllers\TranslationController', 'newTranslation'])->add($needsRole('ROLE_USER'));
    $app->get('/subtitles/{id:[0-9]+}/translate', ['\App\Controllers\TranslationController', 'view'])->setName('translation')->add($needsRole('ROLE_USER'));
    $app->get('/subtitles/{id:[0-9]+}/translate/load', ['\App\Controllers\TranslationController', 'loadData'])->add($needsRole('ROLE_USER'));
    $app->post('/subtitles/{id:[0-9]+}/translate/open', ['\App\Controllers\TranslationController', 'open'])->add($needsRole('ROLE_USER'));
    $app->post('/subtitles/{id:[0-9]+}/translate/close', ['\App\Controllers\TranslationController', 'close'])->add($needsRole('ROLE_USER'));
    $app->post('/subtitles/{id:[0-9]+}/translate/save', ['\App\Controllers\TranslationController', 'save'])->add($needsRole('ROLE_USER'));
    $app->post('/subtitles/{id:[0-9]+}/translate/create', ['\App\Controllers\TranslationController', 'create'])->add($needsRole('ROLE_USER'));
    $app->post('/subtitles/{id:[0-9]+}/translate/lock', ['\App\Controllers\TranslationController', 'lockToggle'])->add($needsRole('ROLE_JUNIOR_TT'));
    $app->delete('/subtitles/{id:[0-9]+}/translate/open-lock/{lockId:[0-9]+}', ['\App\Controllers\TranslationController', 'releaseLock'])->add($needsRole('ROLE_JUNIOR_TT'));

    $app->get('/subtitles/{subId:[0-9]+}/translate/comments', ['\App\Controllers\SubtitleCommentsController', 'list'])->add($needsRole('ROLE_USER'));
    $app->post('/subtitles/{subId:[0-9]+}/translate/comments', ['\App\Controllers\SubtitleCommentsController', 'create'])->add($needsRole('ROLE_USER'));
    $app->delete('/subtitles/{subId:[0-9]+}/translate/comments/{cId:[0-9]+}', ['\App\Controllers\SubtitleCommentsController', 'delete'])->add($needsRole('ROLE_MOD'));
    /*
    $app->put('/subtitles/{subId}/translate/comments/{cId}', ['\App\Controllers\SubtitleCommentsController', 'edit'])->add($needsRole('ROLE_USER'));
    */

    $app->get('/subtitles/{subId:[0-9]+}/delete', ['\App\Controllers\SubtitleController', 'delete'])->add($needsRole('ROLE_MOD'));
    $app->get('/subtitles/{subId:[0-9]+}/pause', ['\App\Controllers\SubtitleController', 'pause'])->add($needsRole('ROLE_TT'));
    $app->get('/subtitles/{subId:[0-9]+}/unpause', ['\App\Controllers\SubtitleController', 'unpause'])->add($needsRole('ROLE_TT'));
    $app->get('/subtitles/{subId:[0-9]+}/hammer', ['\App\Controllers\SubtitleController', 'viewHammer'])->add($needsRole('ROLE_MOD'));
    $app->post('/subtitles/{subId:[0-9]+}/hammer', ['\App\Controllers\SubtitleController', 'doHammer'])->add($needsRole('ROLE_MOD'));
    $app->get('/subtitles/{subId:[0-9]+}/properties', ['\App\Controllers\SubtitleController', 'editProperties'])->add($needsRole('ROLE_MOD'))->setName('subtitle-edit');
    $app->post('/subtitles/{subId:[0-9]+}/properties', ['\App\Controllers\SubtitleController', 'saveProperties'])->add($needsRole('ROLE_MOD'));
    $app->post('/subtitles/{subId:[0-9]+}/alert', ['\App\Controllers\AlertController', 'subtitleAlert']);

    $app->get('/episodes/{epId:[0-9]+}/edit', ['\App\Controllers\EpisodeController', 'edit'])->add($needsRole('ROLE_MOD'))->setName('ep-edit');
    $app->post('/episodes/{epId:[0-9]+}/edit', ['\App\Controllers\EpisodeController', 'saveEdit'])->add($needsRole('ROLE_MOD'));

    $app->get('/episodes/{epId:[0-9]+}/resync', ['\App\Controllers\UploadResyncController', 'view'])->add($needsRole('ROLE_USER'));
    $app->post('/episodes/{epId:[0-9]+}/resync', ['\App\Controllers\UploadResyncController', 'do'])->add($needsRole('ROLE_USER'));
    $app->get('/episodes/{epId:[0-9]+}/comments', ['\App\Controllers\EpisodeCommentsController', 'list']);
    $app->post('/episodes/{epId:[0-9]+}/comments', ['\App\Controllers\EpisodeCommentsController', 'create'])->add($needsRole('ROLE_USER'));

    $app->delete('/episodes/{epId:[0-9]+}/comments/{cId:[0-9]+}', ['\App\Controllers\EpisodeCommentsController', 'delete'])->add($needsRole('ROLE_USER'));
    /*
    $app->post('/episodes/{epId}/comments/{cId}/edit', ['\App\Controllers\EpisodeCommentsController', 'edit'])->add($needsRole('ROLE_USER'));
    $app->post('/episodes/{id}/comments/{cid}/pin', ['\App\Controllers\TranslationController', 'pin'])->add($needsRole('ROLE_MOD'));
    */
    $app->get('/episodes/{id:[0-9]+}[/{slug}]', ['\App\Controllers\EpisodeController', 'view'])->setName('episode');
    $app->get('/shows/{showId:[0-9]+}/{season:[0-9]+}', ['\App\Controllers\ShowController', 'redirectToView']);
    $app->get('/shows/{showId:[0-9]+}[/season/{season:[0-9]+}]', ['\App\Controllers\ShowController', 'view'])->setName('show');
    $app->get('/shows/{showId:[0-9]+}/properties', ['\App\Controllers\ShowController', 'editProperties'])->add($needsRole('ROLE_MOD'))->setName('show-edit');
    $app->post('/shows/{showId:[0-9]+}/properties', ['\App\Controllers\ShowController', 'saveProperties'])->add($needsRole('ROLE_MOD'));
    $app->get('/subtitles/{id:[0-9]+}/download', ['\App\Controllers\DownloadController', 'download']);

    $app->get('/comments/episodes', ['\App\Controllers\EpisodeCommentsController', 'viewAll'])->add($needsRole('ROLE_TT'));
    $app->get('/comments/episodes/load', ['\App\Controllers\EpisodeCommentsController', 'listAll'])->add($needsRole('ROLE_TT'));
    $app->get('/comments/subtitles', ['\App\Controllers\SubtitleCommentsController', 'viewAll'])->add($needsRole('ROLE_TT'));
    $app->get('/comments/subtitles/load', ['\App\Controllers\SubtitleCommentsController', 'listAll'])->add($needsRole('ROLE_TT'));

    $app->post('/login', ['\App\Controllers\LoginController', 'login']);
    $app->post('/register', ['\App\Controllers\LoginController', 'register']);
    $app->get('/logout', ['\App\Controllers\LoginController', 'logout']);
    $app->get('/banned', ['\App\Controllers\HomeController', 'bannedNotice']);

    $app->get('/rules[/{type}]', ['\App\Controllers\RulesController', 'view']);

    $app->get('/me', ['\App\Controllers\UserController', 'viewSettings'])->setName('settings')->add($needsRole('ROLE_USER'));
    $app->post('/me', ['\App\Controllers\UserController', 'saveSettings'])->add($needsRole('ROLE_USER'));

    $app->get('/users/{userId:[0-9]+}', ['\App\Controllers\UserController', 'publicProfile'])->setName('user');
    $app->post('/users/{userId:[0-9]+}/ban', ['\App\Controllers\UserController', 'ban'])->add($needsRole('ROLE_MOD'));
    $app->get('/users/{userId:[0-9]+}/unban', ['\App\Controllers\UserController', 'unban'])->add($needsRole('ROLE_MOD'));

    $app->get('/disclaimer', ['\App\Controllers\TermsController', 'viewDisclaimer']);
    $app->get('/rss', ['\App\Controllers\RSSController', 'viewFeed']);

    $app->get('/panel', ['\App\Controllers\Panel\PanelIndexController', 'view'])->add($needsRole('ROLE_MOD'));
    $app->get('/panel/alerts', ['\App\Controllers\Panel\PanelAlertsController', 'view'])->setName('alerts')->add($needsRole('ROLE_MOD'));
    $app->post('/panel/alerts', ['\App\Controllers\Panel\PanelAlertsController', 'saveComment'])->add($needsRole('ROLE_MOD'));
    $app->get('/panel/banlist', ['\App\Controllers\Panel\PanelBanlistController', 'view'])->setName('banlist')->add($needsRole('ROLE_MOD'));
}
