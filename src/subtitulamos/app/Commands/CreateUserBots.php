<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

namespace App\Commands;

use App\Entities\User;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateUserBots extends Command
{
    protected function configure()
    {
        $this->setName('app:bots:synchronize')
            ->setDescription('Makes sure that the properties of the bot users are correct')
            ->setHelp('Creates the bot users on a new installation, and updates their data on existing ones');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        global $entityManager;
        $bots = [
            [
                'id' => -1,
                'name' => 'ModBot'
            ]
        ];

        foreach ($bots as $botInfo) {
            if (!$bot = $entityManager->getRepository('App:User')->find($botInfo['id'])) {
                $bot = new User();
                $bot->setRegisteredAt(new \DateTime());
            }

            $bot->setId($botInfo['id']);
            $bot->setUsername($botInfo['name']);
            $bot->setPassword('');
            $bot->setEmail('bots@subtitulamos.tv');
            $bot->setBanned(true);
            $bot->setRoles([]);

            // Prevent id autogeneration
            $metadata = $entityManager->getClassMetaData(get_class($bot));
            $metadata->setIdGeneratorType(\Doctrine\ORM\Mapping\ClassMetadata::GENERATOR_TYPE_NONE);
            $entityManager->persist($bot);
        }

        $entityManager->flush();
        $output->writeln(count($bots)." bots sync'd");
    }
}
