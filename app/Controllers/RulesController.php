<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017-2018 subtitulamos.tv
 */

namespace App\Controllers;

use Slim\Views\Twig;

class RulesController
{
    public function view($request, $response, Twig $twig)
    {
        $type = $request->getAttribute('route')->getArgument('type');
        if (!$type || !\in_array($type, ['main', 'community', 'upload'])) {
            $type = 'main';
        }

        return $twig->render($response, 'rules.twig', [
            'rules_type' => $type
        ]);
    }
}
