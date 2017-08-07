<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017 subtitulamos.tv
 */

namespace App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\Services\AssetManager as AssetManager;

class ClearTwigCache extends Command
{
    protected function configure()
    {
        $this->setName('app:twig:clear-cache')
            ->setDescription('Clears the temporary folders created by twig')
            ->setHelp('Clears the files on the /tmp cache folder');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(__DIR__ . '/../../tmp/twig', \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $fn = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $fn($fileinfo->getRealPath());
        }

        $output->writeln("Cleared Twig template cache");
    }
}
