<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

namespace App\Services;

class Sonic
{
    const SHOW_NAME_COLLECTION = 'showNames';

    public static function getClient()
    {
        return new \Psonic\Client('sonic', 1491, 30);
    }

    public static function getIngestClient()
    {
        $client = new \Psonic\Ingest(self::getClient());
        $client->connect(SONIC_PASSWORD);
        return $client;
    }

    public static function getSearchClient()
    {
        $client = new \Psonic\Search(self::getClient());
        $client->connect(SONIC_PASSWORD);
        return $client;
    }
}
