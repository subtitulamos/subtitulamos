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

class ShowController
{
    public function view($showId, RequestInterface $request, ResponseInterface $response, EntityManager $em, Twig $twig)
    {
        $show = $em->getRepository("App:Show")->find($showId);
        if (!$show) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        $seasonsRes = $em->createQuery("SELECT DISTINCT e.season FROM App:Episode e WHERE e.show = :id ORDER BY e.season ASC")
            ->setParameter("id", $showId)
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
            $season = $seasons[0];
        }

        $show = $em->createQuery("SELECT sw, e, v, s FROM App:Show sw JOIN sw.episodes e JOIN e.versions v JOIN v.subtitles s WHERE sw.id = :id AND e.season = :season ORDER BY e.number ASC")
            ->setParameter("id", $showId)
            ->setParameter("season", $season)
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
                        'progress' => $sub->getProgress()
                    ];
                }
            }

            $episodeList[] = $epInfo;
        }

        return $twig->render($response, 'show_list.twig', [
            'id' => $showId,
            'show_name' => $show->getName(),
            'seasons' => $seasons,
            'episodes' => $episodeList,
            'cur_season' => $season
        ]);
    }
}
