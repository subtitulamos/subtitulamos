<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017 subtitulamos.tv
 */

namespace App\Controllers;

use \Slim\Views\Twig;

class TermsController
{
    public function viewDMCA($response, Twig $twig)
    {
        return $twig->render($response, 'dmca.twig', []);
    }
}
