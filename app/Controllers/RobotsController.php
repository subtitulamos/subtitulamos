<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

namespace App\Controllers;

class RobotsController
{
    public function viewRobots($request, $response)
    {
        $response->getBody()->write(sprintf(
            "Sitemap: %s/sitemap.xml\n",
            SITE_URL
        ));
        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
