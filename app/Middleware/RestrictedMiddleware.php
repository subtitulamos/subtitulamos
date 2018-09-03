<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017-2018 subtitulamos.tv
 */

namespace App\Middleware;

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

        $allowed = false;
        foreach ($this->allowedRoles as $role) {
            $allowed = $allowed || $auth->hasRole($role);
        }

        if ($allowed) { // If allowed, continue with the chain
            return $next($request, $response);
        }

        $twig = $this->container->get("Slim\Views\Twig");
        return $twig->render($response, 'restricted.twig', [
            'is_logged_in' => $u
        ]);
    }
}
