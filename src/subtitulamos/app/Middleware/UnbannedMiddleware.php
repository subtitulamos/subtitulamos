<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

namespace App\Middleware;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

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
    * @param  ServerRequestInterface        $request PSR-7 request
    * @param  RequestHandler $handler PSR-15 request handler
    *
    * @return Response
    */
    public function __invoke(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $auth = $this->container->get('App\Services\Auth');
        $u = $auth->getUser();
        if ($u && !$u->getBan()) {
            return $handler->handle($request);
        }

        $factory = new Psr17Factory();
        return $factory->createResponse(302)->withHeader('Location', '/banned');
    }
}
