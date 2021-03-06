<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2021 subtitulamos.tv
 */

namespace App\Commands;

use Cocur\Slugify\Slugify;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateSitemaps extends Command
{
    public const SITEMAP_DIR = __DIR__.'/../../public';

    protected function configure()
    {
        $this->setName('app:generate-sitemap')
            ->setDescription('Generates sitemap.xml and a couple of sub sitemaps on public/.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        global $entityManager;
        $slugify = new Slugify();

        // Fetch literally all subtitles
        $allEpisodes = $entityManager->createQuery(
            'SELECT e
                FROM App:Episode e
                    JOIN e.show s'
        )->getResult();

        $episodes = [];
        foreach ($allEpisodes as $ep) {
            $lastmodRow = $entityManager->createQuery(
                'SELECT MAX(sb.uploadTime), MAX(sb.editTime)
                    FROM App:Subtitle sb
                        JOIN sb.version v
                    WHERE v.episode = :ep'
            )->setParameter('ep', $ep)->getOneOrNullResult();

            if (!$lastmodRow) {
                // We don't know what's up.
                // Mark this episode as likely to change within the week
                $lastmod = time();
                $frequency = 'weekly';
            } else {
                $lastmod = max(@strtotime($lastmodRow[1]), @strtotime($lastmodRow[2]));
                $secDif = time() - $lastmod;
                if ($secDif < 60*60*72) {
                    // Changed in the last 72 hours
                    $frequency = 'hourly';
                } elseif ($secDif < 60*60*24*15) {
                    // Changed in the last 15d
                    $frequency = 'daily';
                } elseif ($secDif < 60*60*24*45) {
                    // Changed in the last 45d
                    $frequency = 'weekly';
                } else {
                    // This subtitle has not changed for well over month at this point
                    // It's not very likely to change
                    $frequency = 'monthly';
                }
            }

            // Add the episode
            $episodes[$ep->getId()] = [
                'slug' => $slugify->slugify($ep->getFullName()),
                'lastmod' => $lastmod,
                'frequency' => $frequency
            ];
        }

        // Create the episodes list
        $sitemapEpisodes = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>');
        foreach ($episodes as $id => $episode) {
            $url = $sitemapEpisodes->addChild('url');
            $url->addChild('loc', sprintf('%s/episodes/%d/%s', SITE_URL, $id, $episode['slug']));
            $url->addChild('lastmod', date(DATE_ATOM, $episode['lastmod']));
            $url->addChild('changefreq', $episode['frequency']);
        }
        \file_put_contents(self::SITEMAP_DIR.'/sitemap_episodes.xml', $sitemapEpisodes->asXML());

        // Lastly, generate an updated sitemap list from the files on the directory
        $sitemapList = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></sitemapindex>');
        $sitemapEntry = $sitemapList->addChild('sitemap');
        $sitemapEntry->addChild('loc', SITE_URL.'/sitemap_episodes.xml');
        $sitemapEntry->addChild('lastmod', date(DATE_ATOM));

        \file_put_contents(self::SITEMAP_DIR.'/sitemap.xml', $sitemapList->asXML());
        $output->writeln('Sitemaps generated');

        return 0;
    }
}
