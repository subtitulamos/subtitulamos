<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017 subtitulamos.tv
 */

namespace App\Middleware;

class UnbannedMiddleware
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
     * @param  \Psr\Http\Message\ServerRequestInterface $request  PSR7 request
     * @param  \Psr\Http\Message\ResponseInterface      $response PSR7 response
     * @param  callable                                 $next     Next middleware
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function __invoke($request, $response, $next)
    {
        $auth = $this->container->get('App\Services\Auth');
        $u = $auth->getUser();

        return $u && !$u->getBan() ? $next($request, $response) : $response->withStatus(302)->withHeader('Location', '/banned');
    }
}
