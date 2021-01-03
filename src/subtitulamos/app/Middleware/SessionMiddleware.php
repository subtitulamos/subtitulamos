<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2021 subtitulamos.tv
 */

namespace App\Middleware;

use Dflydev\FigCookies\FigRequestCookies;
use Dflydev\FigCookies\FigResponseCookies;
use Dflydev\FigCookies\SetCookie;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SessionMiddleware
{
    /**
     * App container instance
     * @var DI\Container
     */
    private $container;

    public function __construct(\DI\Container $container)
    {
        $this->container = $container;
    }

    /**
    * @param  ServerRequestInterface        $request PSR-7 request
    * @param  RequestHandler $handler PSR-15 request handler
    *
    * @return Response
    */
    public function __invoke(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $auth = $this->container->get('App\Services\Auth');
        $rememberCookie = FigRequestCookies::get($request, 'remember', '');

        if ((!isset($_SESSION['logged']) || $_SESSION['logged'] !== true) && $rememberCookie->getValue()) {
            // Try to load up a remembered session given that there's none
            $newToken = $auth->logByToken($rememberCookie->getValue());
            if ($newToken) {
                $response = $handler->handle($request);
                $response = FigResponseCookies::set($response, SetCookie::create('remember')->withPath('/')->withValue($newToken)->rememberForever());
            }
        } else {
            if (!empty($_SESSION['uid'])) {
                // Load up existing session
                $auth->loadUser($_SESSION['uid']);
            }

            $response = $handler->handle($request);
        }

        return $response;
    }
}
