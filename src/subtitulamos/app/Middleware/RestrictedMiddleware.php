<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2021 subtitulamos.tv
 */

namespace App\Middleware;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RestrictedMiddleware
{
    /**
     * List of roles that are allowed by this middleware.
     * Defined when instanced
     *
     * @var array
     */
    private $allowedRoles = [];

    /**
     * App container instance
     * @var DI\Container
     */
    private $container;

    public function __construct(\DI\Container $container, $roles)
    {
        $this->container = $container;

        if (\is_string($roles)) {
            $roles = [$roles];
        }

        $this->allowedRoles = $roles;
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

        $allowed = false;
        foreach ($this->allowedRoles as $role) {
            $allowed = $allowed || $auth->hasRole($role);
        }

        if ($allowed) { // If allowed, continue with the chain
            return $handler->handle($request);
        }

        $factory = new Psr17Factory();
        $u = $auth->getUser();
        $twig = $this->container->get("Slim\Views\Twig");
        return $twig->render($factory->createResponse(401), 'restricted.twig', [
            'is_logged_in' => $u
        ]);
    }
}
