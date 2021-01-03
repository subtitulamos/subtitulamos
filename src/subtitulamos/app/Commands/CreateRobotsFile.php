<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2021 subtitulamos.tv
 */

namespace App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateRobotsFile extends Command
{
    protected function configure()
    {
        $this->setName('app:static:create-robots-file')
            ->setDescription('Creates the robots file based on the site configuration')
            ->setHelp('Creates the robots file based on the site configuration');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $robotsContent = file_get_contents(__DIR__.'/../../resources/assets/robots_base.txt');
        $robotsContent .= "\n# Sitemap!\nSitemap: ".SITE_URL."/sitemap.xml\n";
        file_put_contents(__DIR__.'/../../public/robots.txt', $robotsContent);

        $output->writeln('Robots file created');
        return 0;
    }
}
