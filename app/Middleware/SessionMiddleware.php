<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017 subtitulamos.tv
 */

namespace App\Middleware;

use Doctrine\ORM\EntityManager;
use Dflydev\FigCookies\FigRequestCookies;
use Dflydev\FigCookies\FigResponseCookies;
use \Dflydev\FigCookies\SetCookie;

class SessionMiddleware
{
    /**
     * App container instance
     * @var DI\Container
     */
    private $container;

    /**
     * @var EntityManager
     */
    private $em;

    public function __construct(\DI\Container $container, EntityManager $em)
    {
        $this->container = $container;
        $this->em = $em;
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
        $rememberCookie = FigRequestCookies::get($request, 'remember', '');
        
        if ((!isset($_SESSION['logged']) || $_SESSION['logged'] !== true) && $rememberCookie->getValue()) {
            // Try to load up a remembered session given that there's none
            $success = $auth->logByToken($rememberCookie->getValue());
            if ($success) {
                $response = FigResponseCookies::set($response, SetCookie::create('remember')->withValue($auth->getUser()->getRememberToken())->rememberForever());
            }
        } elseif (!empty($_SESSION['uid'])) {
            // Load up existing session
            $auth->loadUser($_SESSION['uid']);
            
            if (mt_rand(1, 10) == 1 && $rememberCookie->getValue()) {
                // Regen remember token.
                // TODO: Smarter regen policy
                $auth->regenerateRememberToken();
                $response = FigResponseCookies::set($response, SetCookie::create('remember')->withValue($auth->getUser()->getRememberToken())->rememberForever());
            }
        }

        return $next($request, $response);
    }
}
