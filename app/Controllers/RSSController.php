<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017-2018 subtitulamos.tv
 */

namespace App\Controllers;

use App\Services\Langs;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\EntityManager;

class RSSController
{
    public function viewFeed($request, $response, \Slim\Router $router, EntityManager $em, SlugifyInterface $slugify)
    {
        $subs = $em->createQuery('SELECT s, v, e FROM App:Subtitle s JOIN s.version v JOIN v.episode e WHERE s.directUpload = 0 AND s.progress = 100 AND s.pause IS NULL AND s.completeTime IS NOT NULL ORDER BY s.completeTime DESC')
            ->setMaxResults(10)
            ->setFirstResult(0)
            ->getResult();

        $rss = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><rss version="2.0"></rss>');
        $channel = $rss->addChild('channel');
        $channel->addChild('title', 'Últimas traducciones completadas');
        $channel->addChild('description', 'Listado de las últimas traducciones completadas/liberadas de subtítulos');
        $channel->addChild('link', SITE_URL);

        foreach ($subs as $sub) {
            $fullName = str_replace('&', 'and', $sub->getVersion()->getEpisode()->getFullName());

            $item = $channel->addChild('item');
            $item->addChild('title', $fullName);
            $item->addChild('link', SITE_URL.$router->pathFor('episode', ['id' => $sub->getVersion()->getEpisode()->getId(), 'slug' => $slugify->slugify($fullName)]));
            $item->addChild('description', Langs::getLocalizedName(Langs::getLangCode($sub->getLang())));
            $item->addChild('pubDate', $sub->getCompleteTime()->format(\DateTime::ATOM));
            $item->addChild('guid', 'sub-done-'.$sub->getId());
        }

        $response->getBody()->write($rss->asXML());

        return $response->withHeader('Content-Type', 'application/rss+xml; charset=utf-8');
    }
}
