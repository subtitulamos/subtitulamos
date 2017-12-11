<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017 subtitulamos.tv
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
    $app->get('/subtitles/{id}/translate', ['\App\Controllers\TranslationController', 'view'])->setName('translation')->add($needsRole('ROLE_USER'));
    $app->get('/subtitles/{id}/translate/load', ['\App\Controllers\TranslationController', 'loadData'])->add($needsRole('ROLE_USER'));
    $app->post('/subtitles/{id}/translate/open', ['\App\Controllers\TranslationController', 'open'])->add($needsRole('ROLE_USER'));
    $app->post('/subtitles/{id}/translate/close', ['\App\Controllers\TranslationController', 'close'])->add($needsRole('ROLE_USER'));
    $app->post('/subtitles/{id}/translate/save', ['\App\Controllers\TranslationController', 'save'])->add($needsRole('ROLE_USER'));
    $app->post('/subtitles/{id}/translate/create', ['\App\Controllers\TranslationController', 'create'])->add($needsRole('ROLE_USER'));
    $app->post('/subtitles/{id}/translate/lock', ['\App\Controllers\TranslationController', 'lockToggle'])->add($needsRole('ROLE_JUNIOR_TT'));
    $app->delete('/subtitles/{id}/translate/open-lock/{lockId}', ['\App\Controllers\TranslationController', 'releaseLock'])->add($needsRole('ROLE_JUNIOR_TT'));

    $app->get('/subtitles/{subId}/translate/comments', ['\App\Controllers\SubtitleCommentsController', 'list'])->add($needsRole('ROLE_USER'));
    $app->post('/subtitles/{subId}/translate/comments/submit', ['\App\Controllers\SubtitleCommentsController', 'create'])->add($needsRole('ROLE_USER'));
    $app->delete('/subtitles/{subId}/translate/comments/{cId}', ['\App\Controllers\SubtitleCommentsController', 'delete'])->add($needsRole('ROLE_MOD'));
    /*
    $app->post('/translate/{subId}/comments/{cId}/edit', ['\App\Controllers\SubtitleCommentsController', 'edit'])->add($needsRole('ROLE_USER'));
    */

    $app->get('/subtitles/{subId}/delete', ['\App\Controllers\SubtitleController', 'delete'])->add($needsRole('ROLE_MOD'));
    $app->get('/subtitles/{subId}/pause', ['\App\Controllers\SubtitleController', 'pause'])->add($needsRole('ROLE_TT'));
    $app->get('/subtitles/{subId}/unpause', ['\App\Controllers\SubtitleController', 'unpause'])->add($needsRole('ROLE_TT'));
    $app->get('/subtitles/{subId}/hammer', ['\App\Controllers\SubtitleController', 'viewHammer'])->add($needsRole('ROLE_MOD'));
    $app->post('/subtitles/{subId}/hammer', ['\App\Controllers\SubtitleController', 'doHammer'])->add($needsRole('ROLE_MOD'));
    $app->get('/subtitles/{subId}/properties', ['\App\Controllers\SubtitleController', 'editProperties'])->add($needsRole('ROLE_MOD'))->setName('subtitle-edit');
    $app->post('/subtitles/{subId}/properties', ['\App\Controllers\SubtitleController', 'saveProperties'])->add($needsRole('ROLE_MOD'));
    $app->post('/subtitles/{subId}/alert', ['\App\Controllers\AlertController', 'subtitleAlert']);

    $app->get('/episodes/{epId}/edit', ['\App\Controllers\EpisodeController', 'edit'])->add($needsRole('ROLE_MOD'))->setName('ep-edit');
    $app->post('/episodes/{epId}/edit', ['\App\Controllers\EpisodeController', 'saveEdit'])->add($needsRole('ROLE_MOD'));

    $app->get('/episodes/{epId}/resync', ['\App\Controllers\UploadResyncController', 'view'])->add($needsRole('ROLE_USER'));
    $app->post('/episodes/{epId}/resync', ['\App\Controllers\UploadResyncController', 'do'])->add($needsRole('ROLE_USER'));
    $app->get('/episodes/{epId}/comments', ['\App\Controllers\EpisodeCommentsController', 'list']);
    $app->post('/episodes/{epId}/comments/submit', ['\App\Controllers\EpisodeCommentsController', 'create'])->add($needsRole('ROLE_USER'));

    $app->delete('/episodes/{epId}/comments/{cId}', ['\App\Controllers\EpisodeCommentsController', 'delete'])->add($needsRole('ROLE_USER'));
    /*
    $app->post('/episodes/{epId}/comments/{cId}/edit', ['\App\Controllers\EpisodeCommentsController', 'edit'])->add($needsRole('ROLE_USER'));
    $app->post('/episodes/{id}/comments/{cid}/pin', ['\App\Controllers\TranslationController', 'pin'])->add($needsRole('ROLE_MOD'));
    */
    $app->get('/episodes/{id}[/{slug}]', ['\App\Controllers\EpisodeController', 'view'])->setName('episode');
    $app->get('/shows/{showId}[/season/{season}]', ['\App\Controllers\ShowController', 'view'])->setName('show');
    $app->get('/shows/{showId}/properties', ['\App\Controllers\ShowController', 'editProperties'])->add($needsRole('ROLE_MOD'))->setName('show-edit');
    $app->post('/shows/{showId}/properties', ['\App\Controllers\ShowController', 'saveProperties'])->add($needsRole('ROLE_MOD'));
    $app->get('/subtitles/{id}/download', ['\App\Controllers\DownloadController', 'download']);

    $app->get('/comments/episodes', ['\App\Controllers\EpisodeCommentsController', 'viewAll'])->add($needsRole('ROLE_TT'));
    $app->get('/comments/episodes/load', ['\App\Controllers\EpisodeCommentsController', 'listAll'])->add($needsRole('ROLE_TT'));
    $app->get('/comments/subtitles', ['\App\Controllers\SubtitleCommentsController', 'viewAll'])->add($needsRole('ROLE_TT'));
    $app->get('/comments/subtitles/load', ['\App\Controllers\SubtitleCommentsController', 'listAll'])->add($needsRole('ROLE_TT'));

    $app->get('/login', ['\App\Controllers\LoginController', 'viewLogin']);
    $app->post('/login', ['\App\Controllers\LoginController', 'login']);
    $app->post('/register', ['\App\Controllers\LoginController', 'register']);
    $app->get('/logout', ['\App\Controllers\LoginController', 'logout']);
    $app->get('/banned', ['\App\Controllers\HomeController', 'bannedNotice']);

    $app->get('/rules[/{type}]', ['\App\Controllers\RulesController', 'view']);

    $app->get('/me', ['\App\Controllers\UserController', 'viewSettings'])->setName('settings')->add($needsRole('ROLE_USER'));
    $app->post('/me', ['\App\Controllers\UserController', 'saveSettings'])->add($needsRole('ROLE_USER'));

    $app->get('/users/{userId}', ['\App\Controllers\UserController', 'publicProfile'])->setName('user');
    $app->post('/users/{userId}/ban', ['\App\Controllers\UserController', 'ban'])->add($needsRole('ROLE_MOD'));
    $app->get('/users/{userId}/unban', ['\App\Controllers\UserController', 'unban'])->add($needsRole('ROLE_MOD'));

    $app->get('/dmca', ['\App\Controllers\TermsController', 'viewDMCA']);
    $app->get('/rss', ['\App\Controllers\RSSController', 'viewFeed']);

    $app->get('/panel', ['\App\Controllers\Panel\PanelIndexController', 'view'])->add($needsRole('ROLE_MOD'));
    $app->get('/panel/alerts', ['\App\Controllers\Panel\PanelAlertsController', 'view'])->setName('alerts')->add($needsRole('ROLE_MOD'));
    $app->post('/panel/alerts', ['\App\Controllers\Panel\PanelAlertsController', 'saveComment'])->add($needsRole('ROLE_MOD'));
}
