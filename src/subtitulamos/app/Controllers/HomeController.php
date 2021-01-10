<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2021 subtitulamos.tv
 */

namespace App\Controllers;

use Slim\Views\Twig;

class HomeController
{
    public function view($response, Twig $twig)
    {
        return $twig->render($response, 'index.twig');
    }

    public function overviewSubtitles($response, Twig $twig)
    {
        return $twig->render($response, 'overview/overview_subtitles.twig');
    }

    public function overviewComments($response, Twig $twig)
    {
        return $twig->render($response, 'overview/overview_comments.twig');
    }
}
