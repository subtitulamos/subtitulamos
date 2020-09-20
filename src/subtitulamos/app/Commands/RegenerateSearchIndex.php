<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

namespace App\Commands;

use App\Services\Sonic;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RegenerateSearchIndex extends Command
{
    protected function configure()
    {
        $this->setName('app:search:regenerate-index')
            ->setDescription('Renegerate the search index for all shows')
            ->setHelp('Renegerate the search index for all shows');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        global $entityManager;
        $shows = $entityManager->getRepository('App:Show')->findAll();

        $ingest = Sonic::getIngestClient();
        foreach ($shows as $show) {
            $ingest->push(Sonic::SHOW_NAME_COLLECTION, 'default', $show->getId(), $show->getName());
        }
        $ingest->disconnect();

        $output->writeln(count($shows)." shows sync'd to search engine");
    }
}
