<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

namespace App\Commands;

use App\Services\AssetManager as AssetManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeployAssetsCommand extends Command
{
    protected function configure()
    {
        $this->setName('app:assets:deploy-css')
            ->setDescription('Deploys css assets and generates an updated css-manifest.json')
            ->setHelp('This command allows you to copy and deploy assets to the public folder,'.
            'generating a mapping between asset names and their public folder at rev-manifest.json');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $assetManager = new AssetManager();
        $assetManager->redeployCSS();

        $output->writeln('Deploy finished');
    }
}
