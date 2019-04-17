<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017-2019 subtitulamos.tv
 */

namespace App\Controllers\Panel;

use Slim\Views\Twig;

class PanelIndexController
{
    public function view($request, $response, Twig $twig)
    {
        return $twig->render($response, 'panel/panel_index.twig', []);
    }
}
