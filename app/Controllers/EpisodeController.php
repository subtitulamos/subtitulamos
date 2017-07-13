<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017 subtitulamos.tv
 */

namespace App\Controllers;

use \Psr\Http\Message\ResponseInterface;
use \Psr\Http\Message\RequestInterface;

use Doctrine\ORM\EntityManager;

use \Slim\Views\Twig;
use App\Entities\Episode;
use App\Services\Langs;

class EpisodeController
{
    public function view($id, RequestInterface $request, ResponseInterface $response, EntityManager $em, Twig $twig, \Slim\Router $router)
    {
        $ep = $em->createQuery("SELECT e, sb, v, sw FROM App:Episode e JOIN e.versions v JOIN v.subtitles sb JOIN e.show sw WHERE e.id = :id")
                   ->setParameter("id", $id)
                   ->getOneOrNullResult();
        
        if (!$ep) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        $langs = [];
        foreach ($ep->getVersions() as $version) {
            foreach ($version->getSubtitles() as $sub) {
                $lang = Langs::getLocalizedName(Langs::getLangCode($sub->getLang()));
                if (!isset($langs[$lang])) {
                    $langs[$lang] = [];
                }

                $langs[$lang][] = $sub;
            }
        }

        $epSeason = $ep->getSeason();
        $epNumber = $ep->getNumber();
        
        // Get ids for the jump arrows
        $nextId = $em->createQuery("SELECT e.id FROM App:Episode e WHERE e.show = :show AND ((e.season = :epseason AND e.number > :epnumber) OR e.season > :epseason) ORDER BY e.season, e.number ASC")
                     ->setParameter("epseason", $epSeason)
                     ->setParameter("epnumber", $epNumber)
                     ->setParameter("show", $ep->getShow()->getId())
                     ->setMaxResults(1)
                     ->getOneOrNullResult();
        $prevId = $em->createQuery("SELECT e.id FROM App:Episode e WHERE e.show = :show AND ((e.season = :epseason AND e.number < :epnumber) OR e.season < :epseason) ORDER BY e.season, e.number DESC")
                     ->setParameter("epseason", $epSeason)
                     ->setParameter("epnumber", $epNumber)
                     ->setParameter("show", $ep->getShow()->getId())
                     ->setMaxResults(1)
                     ->getOneOrNullResult();
        
        return $twig->render($response, 'episode.twig', [
            'episode' => $ep,
            'langs' => $langs,
            'prev_url' => $prevId ? $router->pathFor("episode", $prevId) : "",
            'next_url' => $nextId ? $router->pathFor("episode", $nextId) : ""
        ]);
    }
}
