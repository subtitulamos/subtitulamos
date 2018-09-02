<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017-2018 subtitulamos.tv
 */

namespace App\Controllers;

use App\Entities\Episode;
use App\Services\Langs;

use Doctrine\ORM\EntityManager;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Views\Twig;

class ShowController
{
    public function view($showId, RequestInterface $request, ResponseInterface $response, EntityManager $em, Twig $twig)
    {
        $show = $em->getRepository('App:Show')->find($showId);
        if (!$show) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        $seasonsRes = $em->createQuery('SELECT DISTINCT e.season FROM App:Episode e WHERE e.show = :id ORDER BY e.season ASC')
            ->setParameter('id', $showId)
            ->getResult();

        $seasons = [];
        foreach ($seasonsRes as $seasonRes) {
            $seasons[] = (int)$seasonRes['season'];
        }
        sort($seasons);

        if (empty($seasons)) {
            /*TODO: Error out*/
        }

        // Let's see if the URI contains the season, otherwise, fill it
        $route = $request->getAttribute('route');
        $season = (int)$route->getArgument('season');
        if (!in_array($season, $seasons)) {
            $season = $seasons[count($seasons) - 1];
        }

        $show = $em->createQuery('SELECT sw, e, v, s FROM App:Show sw JOIN sw.episodes e JOIN e.versions v JOIN v.subtitles s WHERE sw.id = :id AND e.season = :season ORDER BY e.number ASC')
            ->setParameter('id', $showId)
            ->setParameter('season', $season)
            ->getOneOrNullResult();

        if (!$show) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        $episodeList = [];
        foreach ($show->getEpisodes() as $ep) {
            $epInfo = [
                'id' => $ep->getId(),
                'name' => $ep->getFullName(),
                'langs' => []
            ];

            foreach ($ep->getVersions() as $v) {
                foreach ($v->getSubtitles() as $sub) {
                    $lang = Langs::getLocalizedName(Langs::getLangCode($sub->getLang()));
                    if (!isset($epInfo['langs'][$lang])) {
                        $epInfo['langs'][$lang] = [];
                    }

                    $epInfo['langs'][$lang][] = [
                        'id' => $sub->getId(),
                        'version_name' => $v->getName(),
                        'progress' => $sub->getProgress(),
                        'pause' => $sub->getPause()
                    ];
                }
            }

            $episodeList[] = $epInfo;
        }

        return $twig->render($response, 'show_list.twig', [
            'show' => [
                'id' => $showId,
                'name' => $show->getName(),
            ],
            'seasons' => $seasons,
            'episodes' => $episodeList,
            'cur_season' => $season
        ]);
    }

    public function canDeleteShow(\App\Entities\Show $show, EntityManager $em)
    {
        $episodeCount = $em->createQuery('SELECT COUNT(e.id) FROM App:Episode e WHERE e.show = :show')
            ->setParameter('show', $show)
            ->getSingleScalarResult();

        return $episodeCount == 0;
    }

    public function editProperties($showId, RequestInterface $request, ResponseInterface $response, EntityManager $em, Twig $twig)
    {
        $show = $em->getRepository('App:Show')->find($showId);
        if (!$show) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        $seasonsRes = $em->createQuery('SELECT DISTINCT e.season FROM App:Episode e WHERE e.show = :id ORDER BY e.season ASC')
            ->setParameter('id', $showId)
            ->getResult();

        $seasons = [];
        foreach ($seasonsRes as $seasonRes) {
            $seasons[] = (int)$seasonRes['season'];
        }
        sort($seasons);

        $canDelete = $this->canDeleteShow($show, $em);
        return $twig->render($response, 'edit_show.twig', [
            'show' => $show,
            'can_delete' => $canDelete,
            'seasons' => $seasons
        ]);
    }

    public function saveProperties($showId, RequestInterface $request, ResponseInterface $response, EntityManager $em, \Elasticsearch\Client $client, Twig $twig, \Slim\Router $router)
    {
        $show = $em->getRepository('App:Show')->find($showId);
        if (!$show) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        $delParam = $request->getParam('delete');

        $canDelete = $this->canDeleteShow($show, $em);
        if ($delParam && $delParam == 'on' && $canDelete) {
            // Delete from search
            $client->delete([
                'index' => ELASTICSEARCH_NAMESPACE.'_shows',
                'type' => 'show',
                'id' => $show->getId()
            ]);

            // Remove from DB
            $em->remove($show);
            $em->flush();
            return $response->withHeader('location', '/');
        }

        $newName = trim(strip_tags($request->getParam('name', '')));
        if ($newName != $show->getName()) {
            $show->setName($newName);
            $em->flush();
        }

        return $response->withHeader('Location', $router->pathFor('show-edit', ['showId' => $showId]));
    }
}
