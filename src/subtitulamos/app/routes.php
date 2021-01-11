<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2021 subtitulamos.tv
 */

/*
* Declare all routes in this file, inside the function.
* Called from index.php to load them
*/
function addRoutes(&$app)
{
    $needsRole = function ($role) use ($app) {
        return new App\Middleware\RestrictedMiddleware($app->getContainer(), $role);
    };

    // People
    $app->get('/', ['\App\Controllers\HomeController', 'view']);
    $app->get('/overview', ['\App\Controllers\HomeController', 'overviewSubtitles'])->add($needsRole('ROLE_TT'));
    $app->get('/overview/comments', ['\App\Controllers\HomeController', 'overviewComments'])->add($needsRole('ROLE_TT'));
    $app->get('/upload', ['\App\Controllers\UploadController', 'view'])->add($needsRole('ROLE_USER'));
    $app->post('/upload', ['\App\Controllers\UploadController', 'do'])->add($needsRole('ROLE_USER'));

    $app->get('/search/query', ['\App\Controllers\SearchController', 'query']);
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
    $app->post('/subtitles/{id:[0-9]+}/translate/newseq', ['\App\Controllers\TranslationController', 'addSequence'])->add($needsRole('ROLE_MOD'));
    $app->post('/subtitles/{id:[0-9]+}/translate/deleteseq', ['\App\Controllers\TranslationController', 'deleteSequence'])->add($needsRole('ROLE_MOD'));
    $app->post('/subtitles/{id:[0-9]+}/translate/lock', ['\App\Controllers\TranslationController', 'lockToggle'])->add($needsRole('ROLE_TT'));
    $app->delete('/subtitles/{id:[0-9]+}/translate/open-lock/{lockId:[0-9]+}', ['\App\Controllers\TranslationController', 'releaseLock'])->add($needsRole('ROLE_TT'));

    $app->get('/subtitles/{subId:[0-9]+}/translate/comments', ['\App\Controllers\SubtitleCommentsController', 'list'])->add($needsRole('ROLE_USER'));
    $app->post('/subtitles/{subId:[0-9]+}/translate/comments', ['\App\Controllers\SubtitleCommentsController', 'create'])->add($needsRole('ROLE_USER'));
    $app->delete('/subtitles/{subId:[0-9]+}/translate/comments/{cId:[0-9]+}', ['\App\Controllers\SubtitleCommentsController', 'delete'])->add($needsRole('ROLE_USER'));
    $app->post('/subtitles/{subId:[0-9]+}/translate/comments/{cId:[0-9]+}/edit', ['\App\Controllers\SubtitleCommentsController', 'edit'])->add($needsRole('ROLE_USER'));
    $app->post('/subtitles/{subId:[0-9]+}/translate/comments/{cId:[0-9]+}/pin', ['\App\Controllers\SubtitleCommentsController', 'togglePin'])->add($needsRole('ROLE_MOD'));
    /*
    $app->put('/subtitles/{subId}/translate/comments/{cId}', ['\App\Controllers\SubtitleCommentsController', 'edit'])->add($needsRole('ROLE_USER'));
    */

    $app->get('/subtitles/{subId:[0-9]+}/delete', ['\App\Controllers\SubtitleController', 'delete'])->add($needsRole('ROLE_MOD'));
    $app->get('/subtitles/{subId:[0-9]+}/pause', ['\App\Controllers\SubtitleController', 'pause'])->add($needsRole('ROLE_TT'));
    $app->get('/subtitles/{subId:[0-9]+}/unpause', ['\App\Controllers\SubtitleController', 'unpause'])->add($needsRole('ROLE_TT'));
    $app->get('/subtitles/{subId:[0-9]+}/hammer', ['\App\Controllers\SubtitleController', 'viewHammer'])->add($needsRole('ROLE_MOD'));
    $app->post('/subtitles/{subId:[0-9]+}/hammer', ['\App\Controllers\SubtitleController', 'doHammer'])->add($needsRole('ROLE_MOD'));
    $app->post('/subtitles/{subId:[0-9]+}/properties', ['\App\Controllers\SubtitleController', 'saveProperties'])->add($needsRole('ROLE_MOD'));
    $app->post('/subtitles/{subId:[0-9]+}/alert', ['\App\Controllers\AlertController', 'subtitleAlert']); // "Access" managed at the controller level
    $app->get('/subtitles/{id:[0-9]+}/download', ['\App\Controllers\DownloadController', 'download']);

    $app->post('/episodes/{epId:[0-9]+}/edit', ['\App\Controllers\EpisodeController', 'saveEdit'])->add($needsRole('ROLE_MOD'));
    $app->post('/episodes/{epId:[0-9]+}/favorite', ['\App\Controllers\EpisodeController', 'favorite'])->add($needsRole('ROLE_USER'));
    $app->post('/episodes/{epId:[0-9]+}/unfavorite', ['\App\Controllers\EpisodeController', 'unfavorite'])->add($needsRole('ROLE_USER'));

    $app->get('/episodes/{epId:[0-9]+}/resync', ['\App\Controllers\UploadResyncController', 'view'])->add($needsRole('ROLE_USER'));
    $app->post('/episodes/{epId:[0-9]+}/resync', ['\App\Controllers\UploadResyncController', 'do'])->add($needsRole('ROLE_USER'));
    $app->get('/episodes/{epId:[0-9]+}/comments', ['\App\Controllers\EpisodeCommentsController', 'list']);
    $app->post('/episodes/{epId:[0-9]+}/comments', ['\App\Controllers\EpisodeCommentsController', 'create'])->add($needsRole('ROLE_USER'));

    $app->delete('/episodes/{epId:[0-9]+}/comments/{cId:[0-9]+}', ['\App\Controllers\EpisodeCommentsController', 'delete'])->add($needsRole('ROLE_USER'));
    $app->post('/episodes/{epId:[0-9]+}/comments/{cId:[0-9]+}/pin', ['\App\Controllers\EpisodeCommentsController', 'togglePin'])->add($needsRole('ROLE_MOD'));
    $app->post('/episodes/{epId:[0-9]+}/comments/{cId:[0-9]+}/edit', ['\App\Controllers\EpisodeCommentsController', 'edit'])->add($needsRole('ROLE_USER'));

    $app->get('/episodes/{id:[0-9]+}[/{slug}]', ['\App\Controllers\EpisodeController', 'view'])->setName('episode');
    $app->get('/shows', ['\App\Controllers\ShowController', 'viewAll'])->setName('showlist');
    $app->get('/shows/{showId:[0-9]+}[/season/{season:[0-9]+}]', ['\App\Controllers\ShowController', 'view'])->setName('show');
    $app->post('/shows/{showId:[0-9]+}/properties', ['\App\Controllers\ShowController', 'saveProperties'])->add($needsRole('ROLE_MOD'));

    $app->get('/comments/episodes/load', ['\App\Controllers\EpisodeCommentsController', 'listAll'])->add($needsRole('ROLE_TT'));
    $app->get('/comments/subtitles/load', ['\App\Controllers\SubtitleCommentsController', 'listAll'])->add($needsRole('ROLE_TT'));

    $app->post('/login', ['\App\Controllers\AccessController', 'login']);
    $app->post('/register', ['\App\Controllers\AccessController', 'register']);
    $app->get('/logout', ['\App\Controllers\AccessController', 'logout']);

    $app->get('/rules[/{type}]', ['\App\Controllers\RulesController', 'view']);

    $app->get('/me', ['\App\Controllers\UserController', 'viewSettings'])->setName('settings')->add($needsRole('ROLE_USER'));
    $app->post('/me', ['\App\Controllers\UserController', 'saveSettings'])->add($needsRole('ROLE_USER'));

    $app->get('/users/{userId:[0-9]+}', ['\App\Controllers\UserController', 'publicProfile'])->setName('user');
    $app->get('/users/{userId:[0-9]+}/upload-list', ['\App\Controllers\UserController', 'loadUploadList']);
    $app->get('/users/{userId:[0-9]+}/collab-list', ['\App\Controllers\UserController', 'loadCollaborationsList']);
    $app->post('/users/{userId:[0-9]+}/resetpwd', ['\App\Controllers\UserController', 'resetPassword'])->add($needsRole('ROLE_MOD'));
    $app->post('/users/{userId:[0-9]+}/changerole', ['\App\Controllers\UserController', 'changeRole'])->add($needsRole('ROLE_MOD'));
    $app->post('/users/{userId:[0-9]+}/ban', ['\App\Controllers\UserController', 'ban'])->add($needsRole('ROLE_MOD'));
    $app->get('/users/{userId:[0-9]+}/unban', ['\App\Controllers\UserController', 'unban'])->add($needsRole('ROLE_MOD'));

    $app->get('/disclaimer', ['\App\Controllers\TermsController', 'viewDisclaimer']);
    $app->get('/rss', ['\App\Controllers\RSSController', 'viewFeed']);

    $app->get('/panel/userlist', ['\App\Controllers\Panel\PanelUserlistController', 'view'])->setName('userlist')->add($needsRole('ROLE_MOD'));
    $app->get('/panel/alerts', ['\App\Controllers\Panel\PanelAlertsController', 'view'])->setName('alerts')->add($needsRole('ROLE_MOD'));
    $app->post('/panel/alerts', ['\App\Controllers\Panel\PanelAlertsController', 'saveComment'])->add($needsRole('ROLE_MOD'));
    $app->get('/panel/banlist', ['\App\Controllers\Panel\PanelBanlistController', 'view'])->setName('banlist')->add($needsRole('ROLE_MOD'));
    $app->get('/panel/logs', ['\App\Controllers\Panel\PanelLogController', 'view'])->setName('logs')->add($needsRole('ROLE_MOD'));
}
