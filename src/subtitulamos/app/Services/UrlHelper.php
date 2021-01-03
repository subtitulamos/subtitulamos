<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2021 subtitulamos.tv
 */

namespace App\Services;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Interfaces\RouteParserInterface;

class UrlHelper
{
    private RouteParserInterface $routeParser;
    private ResponseFactoryInterface $responseFactory;

    public function __construct(RouteParserInterface $routeParser, ResponseFactoryInterface $responseFactory)
    {
        $this->routeParser = $routeParser;
        $this->responseFactory = $responseFactory;
    }

    public function pathFor(string $pathName, array $data=[], array $queryParams=[]): string
    {
        return $this->routeParser->urlFor($pathName, $data, $queryParams);
    }

    public function responseWithRedirect($target)
    {
        return $this->responseFactory->createResponse(302)->withHeader('Location', $target);
    }

    public function responseWithRedirectToRoute($routeName, $data=[], $queryParams=[])
    {
        return $this->responseWithRedirect($this->pathFor($routeName, $data, $queryParams));
    }
}
