<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

namespace App\Controllers;

use App\Entities\EventLog;
use App\Entities\Favorite;
use App\Entities\Show;
use App\Services\Auth;

use App\Services\Langs;
use App\Services\UrlHelper;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\EntityManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

class EpisodeController
{
    public function view($id, ServerRequestInterface $request, ResponseInterface $response, EntityManager $em, Twig $twig, UrlHelper $urlHelper, SlugifyInterface $slugify)
    {
        $ep = $em->createQuery('SELECT e, sb, v, sw, p FROM App:Episode e JOIN e.versions v JOIN v.subtitles sb JOIN e.show sw LEFT JOIN sb.pause p WHERE e.id = :id')
            ->setParameter('id', $id)
            ->getOneOrNullResult();

        if (!$ep) {
            throw new \Slim\Exception\HttpNotFoundException($request);
        }

        // The only correct URL is with a slug (and a right one at that), redirect to the right URI otherwise
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $slug = $route->getArgument('slug');
        $properSlug = $slugify->slugify($ep->getFullName());
        if (empty($slug) || $slug != $properSlug) {
            return $urlHelper->responseWithRedirectToRoute('episode', ['id' => $ep->getId(), 'slug' => $properSlug])->withStatus(301);
        }

        // Collect info from versions - which languages this sub is using, and total downloads
        $downloads = 0;
        $langs = [];
        foreach ($ep->getVersions() as $version) {
            foreach ($version->getSubtitles() as $sub) {
                $lang = $sub->getLang();
                if (!isset($langs[$lang])) {
                    $langs[$lang] = [];
                }

                $langs[$lang][] = $sub;
                $downloads += $sub->getDownloads();
            }
        }

        $showId = $ep->getShow()->getId();

        // Get the data all episodes in all seasons to show top nav bar
        $episodesInShow = $em->createQuery('SELECT DISTINCT e FROM App:Episode e WHERE e.show = :id ORDER BY e.season ASC')
            ->setParameter('id', $showId)
            ->getResult();

        $seasons = [];
        foreach ($episodesInShow as $curEp) {
            $curSeasonNum = $curEp->getSeason();
            $curEpisodeNum = $curEp->getNumber();
            if (!isset($seasons[$curSeasonNum])) {
                $seasons[$curSeasonNum] = [
                    'number' => $curSeasonNum,
                    'url' => '#',
                    'episodes' => []
                ];
            }

            $seasons[$curSeasonNum]['episodes'][] = [
                'ep' => $curEp,
                'number' => $curEpisodeNum,
                'url' => $urlHelper->pathFor('episode', ['id' => $curEp->getId(), 'slug' => $slugify->slugify($curEp->getFullName())])
            ];
        }

        foreach (array_keys($seasons) as $curSeasonNum) {
            usort($seasons[$curSeasonNum]['episodes'], function ($a, $b) {
                return $a['number'] <=> $b['number'];
            });

            $topEpisode = $seasons[$curSeasonNum]['episodes'][0];
            $seasons[$curSeasonNum]['url'] = $urlHelper->pathFor('episode', [
                'id' => $topEpisode['ep']->getId(),
                'slug' => $slugify->slugify($topEpisode['ep']->getFullName())
            ]);
        }

        // Render
        return $twig->render($response, 'episode.twig', [
            'episode' => $ep,
            'langs' => $langs,
            'slug' => $properSlug,
            'season_data' => $seasons,
            'downloads' => $downloads
        ]);
    }

    public function saveEdit($epId, $request, $response, EntityManager $em, Auth $auth, UrlHelper $urlHelper)
    {
        $ep = $em->getRepository('App:Episode')->find($epId);
        if (!$ep) {
            throw new \Slim\Exception\HttpNotFoundException($request);
        }

        $body = $request->getParsedBody();
        $season = (int)($body['season'] ?? 0);
        $epNumber = (int)($body['episode'] ?? 0);
        $epName = trim(strip_tags(($body['name'] ?? '')));
        $save = false;

        if ($season != $ep->getSeason() || $epNumber != $ep->getNumber()) {
            // Let's make sure that an episode with this number doesn't already exist, too
            $e = $em->createQuery('SELECT e FROM App:Episode e WHERE e.show = :showid AND e.season = :season AND e.number = :num')
                ->setParameter('showid', $ep->getShow())
                ->setParameter('season', $season)
                ->setParameter('num', $epNumber)
                ->getResult();

            if (!$e) {
                $ep->setSeason($season);
                $ep->setNumber($epNumber);
                $save = true;
            } else {
                $auth->addFlash('error', 'Un capítulo con esa temporada y episodio ya existe en esta serie');
            }
        }

        if ($epName && $epName != $ep->getName()) {
            $ep->setName($epName);
            $save = true;
        }

        if ($save) {
            $event = new EventLog(
                $auth->getUser(),
                new \DateTime(),
                sprintf('Propiedades de episodio actualizadas ([[episode:%d]])', $ep->getId())
            );
            $em->persist($event);

            $auth->addFlash('success', 'Parámetros de capítulo actualizados');
            $em->flush();
        }

        return $response->withHeader('Location', $urlHelper->pathFor('episode', ['id' => $epId]));
    }

    public function favorite($epId, $request, ResponseInterface $response, EntityManager $em, Auth $auth)
    {
        $ep = $em->getRepository('App:Episode')->find($epId);
        if (!$ep) {
            throw new \Slim\Exception\HttpNotFoundException($request);
        }

        // Make sure no fav exists already with these properties
        $fav = $em->getRepository('App:Favorite')->findOneBy(['user' => $auth->getUser(), 'episode' => $epId]);
        if ($fav) {
            throw new \Slim\Exception\HttpBadRequestException($request);
        }

        $fav = new Favorite($auth->getUser(), $ep);
        $em->persist($fav);
        $em->flush();

        return $response->withStatus(200);
    }

    public function unfavorite($epId, $request, ResponseInterface $response, EntityManager $em, Auth $auth)
    {
        $fav = $em->getRepository('App:Favorite')->findOneBy(['user' => $auth->getUser(), 'episode' => $epId]);
        if (!$fav) {
            throw new \Slim\Exception\HttpNotFoundException($request);
        }

        $em->remove($fav);
        $em->flush();

        return $response->withStatus(200);
    }
}
