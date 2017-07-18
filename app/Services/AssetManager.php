<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017 subtitulamos.tv
 */

namespace App\Services;

class AssetManager
{
    /**
     * Manifest associative array with the asset-version bindings
     *
     * @var string
     */
    private $manifest = [];

    /**
     * Path of the asset directory
     * @var string
     */
    public const ASSET_PATH = __DIR__ . "/../../resources/assets";

    /**
     * Path of the manifest file
     * @var string
     */
    public const MANIFEST_PATH = self::ASSET_PATH . "/rev-manifest.json";

    /**
     * Path of the deploy directory
     * @var string
     */
    public const DEPLOY_PATH = __DIR__ . "/../../public";

    public function __construct()
    {
        $this->loadManifest();

        if (DEBUG === true) {
            // Update manifest & copy files if needed
            $this->redeployAssets();
        }
    }

    /**
     * (Re)load the manifest file from its path
     *
     * @return bool
     */
    public function loadManifest()
    {
        $contents = @\file_get_contents(self::MANIFEST_PATH);
        if (!empty($contents)) {
            $this->manifest = \json_decode($contents, true);
        }
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function redeployAssets()
    {
        $allowedExts = ["css", "js"];
        $folders = ["css", "js"];

        $manifest = [];
        foreach ($folders as $folder) {
            if (DEBUG !== true) {
                // Clear files on the deploy directory if we're rebuilding on production
                foreach (new \DirectoryIterator(self::DEPLOY_PATH . "/" . $folder) as $fileInfo) {
                    if (!$fileInfo->isDot() && $fileInfo->isFile() && \in_array($fileInfo->getExtension(), $allowedExts)) {
                        unlink($fileInfo->getPathname());
                    }
                }
            }

            foreach (new \DirectoryIterator(self::ASSET_PATH . "/" . $folder) as $fileInfo) {
                $ext = $fileInfo->getExtension();

                if ($fileInfo->isDot() || !$fileInfo->isFile() || !\in_array($fileInfo->getExtension(), $allowedExts)) {
                    continue;
                }

                $fileName = $fileInfo->getFilename();
                $filePath = $fileInfo->getPathname();
                $ver = \shell_exec("git log -n 1 --pretty=format:%h -- " . $filePath);
                if (!$ver) {
                    $ver = "00000";
                }

                $manifestPath = $folder . "/" . $fileName;
                $versionedPath = $folder . "/" . str_replace("." . $ext, "", $fileName) . "-" . $ver . "." . $ext;
                if (DEBUG !== true || !isset($manifest[$manifestPath]) || $manifest[$manifestPath] != $ver) {
                    $targetPath = self::DEPLOY_PATH . "/" . $versionedPath;
                    \copy($filePath, $targetPath);
                }

                $manifest[$manifestPath] = $versionedPath;
            }
        }

        \file_put_contents(self::MANIFEST_PATH, \json_encode($manifest));
        $this->manifest = $manifest;
    }

    public function getAssetVersionedName($assetName)
    {
        return isset($this->manifest[$assetName]) ? $this->manifest[$assetName] : "???";
    }
}
