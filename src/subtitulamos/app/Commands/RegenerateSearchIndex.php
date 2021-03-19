<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2021 subtitulamos.tv
 */

namespace App\Commands;

use App\Services\Meili;
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

        $meili = Meili::getClient();

        // Make sure we clear the shows index, so we start from scratch
        $indexes = $meili->getAllIndexes();
        foreach ($indexes as $index) {
            if ($index->getUid() == 'shows') {
                $index->delete();
                break;
            }
        }

        $showIndex = $meili->createIndex('shows', ['primaryKey' => 'show_id']);
        $showIndex->updateSettings([
            'distinctAttribute' => 'show_id',
            'rankingRules' => [
                'typo',
                'words',
                'proximity',
                'attribute',
                'wordsPosition',
                'exactness',
            ],
            'searchableAttributes' => [
              'show_name',
            ],
            'displayedAttributes' => [
              'show_id',
              'show_name',
            ],
            'stopWords' => [
              'the',
              'a',
              'an'
            ]
          ]);

        $documents = [];
        foreach ($shows as $show) {
            $documents[] = Meili::buildDocumentFromShow($show);
        }
        $showIndex->addDocuments($documents);

        $output->writeln(count($shows)." shows sync'd to search engine");
        return 0;
    }
}
