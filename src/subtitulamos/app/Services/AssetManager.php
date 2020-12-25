<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

namespace App\Services;

class AssetManager
{
    /**
     * Webpack-generated associative array with the asset-version bindings
     *
     * @var string
     */

    private $webpackManifest = [];

    /**
     * Path of the manifest file
     * @var string
     */
    public const WEBPACK_MANIFEST_PATH = __DIR__.'/../../resources/assets/manifest.json';

    public function __construct()
    {
        $this->loadManifest();
    }

    /**
     * (Re)load the manifest file from its path
     * @return bool
     */
    public function loadManifest()
    {
        $contents = @\file_get_contents(self::WEBPACK_MANIFEST_PATH);
        if (!empty($contents)) {
            $this->webpackManifest = \json_decode($contents, true);
        }
    }

    public function getWebpackVersionedName($assetName)
    {
        $transformedName = isset($this->webpackManifest[$assetName]) ? $this->webpackManifest[$assetName] : "vunknown-$assetName";
        if (DEBUG) {
            if (str_ends_with($assetName, '.css')) {
                $transformedName .= '?h='.md5(@\file_get_contents(__DIR__."/../../resources/assets/$assetName"));
            }
        }

        return $transformedName;
    }
}
