<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2021 subtitulamos.tv
 */

namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

class RulesController
{
    public function view(ServerRequestInterface $request, $response, Twig $twig)
    {
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();

        $type = $route->getArgument('type', 'main');
        if (!in_array($type, ['main', 'community', 'upload'])) {
            $type = 'main';
        }

        return $twig->render($response, 'rules.twig', [
            'rules_type' => $type
        ]);
    }
}
