<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

namespace App\Controllers;

use App\Entities\EventLog;
use App\Services\Auth;
use App\Services\Langs;
use App\Services\UrlHelper;
use Doctrine\ORM\EntityManager;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

class ShowController
{
    public function viewAll(ResponseInterface $response, EntityManager $em, Twig $twig)
    {
        $em->getRepository('App:Show')->findAll(); // This hydrates shows on the following query
        $seasons = $em->createQuery('SELECT e, COUNT(DISTINCT e.season) FROM App:Episode e GROUP BY e.show')->getResult();

        $showListByInitial = [];
        foreach ($seasons as $res) {
            $show = $res[0]->getShow();
            $seasonCount = $res[1];

            $name = $show->getName();
            $initial = mb_strtolower($name[0]);
            if (!isset($showListByInitial[$initial])) {
                $showListByInitial[$initial] = [];
            }

            $showListByInitial[$initial][] = [
                'show' => $show,
                'season_count' => $seasonCount
            ];
        }

        foreach ($showListByInitial as $inital => $list) {
            usort($list, function ($a, $b) {
                return strnatcmp($a['show']->getName(), $b['show']->getName());
            });
        }

        ksort($showListByInitial);
        return $twig->render($response, 'shows_list.twig', [
            'shows_by_letter' => $showListByInitial
        ]);
    }

    public function view($showId, ServerRequestInterface $request, ResponseInterface $response, EntityManager $em, Twig $twig, UrlHelper $urlHelper)
    {
        $show = $em->getRepository('App:Show')->find($showId);
        if (!$show) {
            throw new \Slim\Exception\HttpNotFoundException($request);
        }

        $seasonsRes = $em->createQuery('SELECT DISTINCT e.season FROM App:Episode e WHERE e.show = :id ORDER BY e.season ASC')
            ->setParameter('id', $showId)
            ->getResult();

        $seasons = [];
        foreach ($seasonsRes as $seasonRes) {
            $seasons[] = (int)$seasonRes['season'];
        }
        sort($seasons);

        // Let's see if the URI contains the season, otherwise, fill it
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $seasonArg = $route->getArgument('season');
        if ($seasonArg !== null) {
            $season = (int)$seasonArg;

            if (!in_array($season, $seasons)) {
                // If the season passed is not a valid season, redirect to default
                return $urlHelper->responseWithRedirectToRoute('show', ['showId' => $showId]);
            }
        } else {
            // Default season: latest
            $season = $seasons[count($seasons) - 1];
        }

        $show = $em->createQuery('SELECT sw, e, v, s FROM App:Show sw JOIN sw.episodes e JOIN e.versions v JOIN v.subtitles s WHERE sw.id = :id AND e.season = :season ORDER BY e.number ASC')
            ->setParameter('id', $showId)
            ->setParameter('season', $season)
            ->getOneOrNullResult();

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

        return $twig->render($response, 'show_seasons.twig', [
            'show' => [
                'id' => $showId,
                'name' => $show->getName(),
            ],
            'seasons' => $seasons,
            'episodes' => $episodeList,
            'cur_season' => $season,
            'add_canonical' => $seasonArg === null
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
            throw new \Slim\Exception\HttpNotFoundException($request);
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

    public function saveProperties($showId, ServerRequestInterface $request, ResponseInterface $response, EntityManager $em, UrlHelper $urlHelper, Auth $auth)
    {
        $show = $em->getRepository('App:Show')->find($showId);
        if (!$show) {
            throw new \Slim\Exception\HttpNotFoundException($request);
        }

        $body = $request->getParsedBody();
        $newName = trim(strip_tags(($body['name'] ?? '')));
        if ($newName != $show->getName()) {
            $show->setName($newName);

            $event = new EventLog(
                $auth->getUser(),
                new \DateTime(),
                sprintf('Propiedades de serie editadas ([[show:%d]])', $showId)
            );
            $em->persist($event);
            $em->flush();
        }

        return $urlHelper->responseWithRedirectToRoute('show-edit', ['showId' => $showId]);
    }
}
