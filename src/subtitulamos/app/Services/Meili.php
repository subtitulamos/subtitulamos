<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2021 subtitulamos.tv
 */

namespace App\Services;

use GuzzleHttp\Client;

class Meili
{
    public static function getClient(?Client $client)
    {
        return new \MeiliSearch\Client('http://search:7700', MEILI_MASTER_KEY, $client);
    }

    public static function buildDocumentFromShow(\App\Entities\Show $show)
    {
        return [
            "show_id" => $show->getId(),
            "show_name" => $show->getName()
        ];
    }
}
