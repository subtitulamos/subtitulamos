<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2021 subtitulamos.tv
 */

namespace App\Services;

use Psr\Http\Message\ResponseInterface;

class Utils
{
    public static function generateRandomString(int $length, $charPool = 'abcdefghijklmnopqrstuvwxyz1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZ-')
    {
        $tok = '';
        $max = mb_strlen($charPool);

        while ($length--) {
            $tok .= $charPool[mt_rand(0, $max - 1)];
        }

        return $tok;
    }

    public static function jsonResponse(ResponseInterface $response, $data): ResponseInterface
    {
        $body = $response->getBody();
        $body->write(json_encode($data));
        return $response->withStatus(200);
    }
}
