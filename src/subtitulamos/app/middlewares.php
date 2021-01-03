<?php
/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2021 subtitulamos.tv
 */

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Views\TwigMiddleware;

/*
* Declare all routes in this file, inside the function.
* Called from index.php to load them
*/
function addMiddlewares(\Slim\App &$app)
{
    $app->add(TwigMiddleware::createFromContainer($app, \Slim\Views\Twig::class));
    $app->addBodyParsingMiddleware(); // Parses JSON, form data & XML
    $app->addRoutingMiddleware();
    $app->add(new \App\Middleware\SessionMiddleware($app->getContainer()));
}
