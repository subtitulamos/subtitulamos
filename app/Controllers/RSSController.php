<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017 subtitulamos.tv
 */

namespace App\Controllers;

use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\EntityManager;

class RSSController
{
    public const BASIC_RSS_FORMAT = '<?xml version="1.0"?>
    <rss version="2.0">
        %s
    </rss>';

    public const RSS_CHAN_FORMAT = '<channel>
        <title>%s</title>
        <link>https://subtitulamos.tv</link>
        <description>%s</description>
        %s
    </channel>';

    public const RSS_ITEM_FORMAT = '<item>
        <title>%s</title>
        <link>%s</link>
        <description>%s</description>
        <pubDate>%s</pubDate>
        <guid>%s</guid>
    </item>';

    public function viewFeed($request, $response, \Slim\Router $router, EntityManager $em, SlugifyInterface $slugify)
    {
        $subs = $em->createQuery('SELECT s, v, e FROM App:Subtitle s JOIN s.version v JOIN v.episode e WHERE s.directUpload = 0 AND s.progress = 100 AND s.pause IS NULL AND s.completeTime IS NOT NULL ORDER BY s.completeTime DESC')
            ->setMaxResults(10)
            ->setFirstResult(0)
            ->getResult();

        $items = '';
        foreach ($subs as $sub) {
            $fullName = $sub->getVersion()->getEpisode()->getFullName();

            $items .= sprintf(
                self::RSS_ITEM_FORMAT,
                $fullName,
                'https://'.$request->getServerParam('HTTP_HOST').$router->pathFor('episode', ['id' => $sub->getVersion()->getEpisode()->getId()]).'/'.$slugify->slugify($fullName),
                $sub->getVersion()->getName(),
                $sub->getCompleteTime()->format(\DateTime::ATOM),
                'sub-done-'.$sub->getId()
            );
        }

        $feed = self::BASIC_RSS_FORMAT;

        $response->getBody()->write(sprintf(
            self::BASIC_RSS_FORMAT,
            sprintf(
                self::RSS_CHAN_FORMAT,
                'Últimas traducciones completadas',
                'Listado de las últimas traducciones completadas/liberadas en subtitulamos.tv',
                $items
            )
        ));

        return $response->withHeader('Content-Type', 'application/rss+xml');
    }
}
