<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017 subtitulamos.tv
 */

namespace App\Services;

class Utils
{
    public static function generateRandomString(int $length, $charPool = 'abcdefghijklmnopqrstuvwxyz1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZ-')
    {
        $tok = '';
        $max = strlen($charPool);

        while ($length--) {
            $tok .= $charPool[mt_rand(0, $max - 1)];
        }

        return $tok;
    }
}
