<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2021 subtitulamos.tv
 */

namespace App\Services;

use Slim\Interfaces\ErrorRendererInterface;
use Slim\Views\Twig;
use Throwable;

class AppErrorRenderer implements ErrorRendererInterface
{
    private Twig $twig;

    public function __construct(\DI\Container $container)
    {
        $this->twig = $container->get("Slim\Views\Twig");
    }

    public function __invoke(Throwable $exception, bool $displayErrorDetails): string
    {
        return $this->twig->fetch('errors/generic_error.twig', [
            'show_details' => $displayErrorDetails,
            'exception' => $exception,
        ]);
    }
}
