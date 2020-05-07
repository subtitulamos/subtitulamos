<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

namespace App\Controllers;

use App\Services\Auth;

use App\Services\Langs;

use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\EntityManager;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Views\Twig;

class EpisodeController
{
    public function view($id, RequestInterface $request, ResponseInterface $response, EntityManager $em, Twig $twig, \Slim\Router $router, SlugifyInterface $slugify)
    {
        $ep = $em->createQuery('SELECT e, sb, v, sw, p FROM App:Episode e JOIN e.versions v JOIN v.subtitles sb JOIN e.show sw LEFT JOIN sb.pause p WHERE e.id = :id')
            ->setParameter('id', $id)
            ->getOneOrNullResult();

        if (!$ep) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        // The only correct URL is with a slug (and a right one at that), redirect to the right URI
        $route = $request->getAttribute('route');
        $slug = $route->getArgument('slug');
        $properSlug = $slugify->slugify($ep->getFullName());
        if (empty($slug) || $slug != $properSlug) {
            return $response->withRedirect($router->pathFor('episode', ['id' => $ep->getId(), 'slug' => $properSlug]), 301);
        }

        // Determine which languages this sub is using
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
        $nextEp = $em->createQuery('SELECT e FROM App:Episode e WHERE e.show = :show AND ((e.season = :epseason AND e.number > :epnumber) OR e.season > :epseason) ORDER BY e.season ASC, e.number ASC')
            ->setParameter('epseason', $epSeason)
            ->setParameter('epnumber', $epNumber)
            ->setParameter('show', $ep->getShow()->getId())
            ->setMaxResults(1)
            ->getOneOrNullResult();
        $prevEp = $em->createQuery('SELECT e FROM App:Episode e WHERE e.show = :show AND ((e.season = :epseason AND e.number < :epnumber) OR e.season < :epseason) ORDER BY e.season DESC, e.number DESC')
            ->setParameter('epseason', $epSeason)
            ->setParameter('epnumber', $epNumber)
            ->setParameter('show', $ep->getShow()->getId())
            ->setMaxResults(1)
            ->getOneOrNullResult();

        return $twig->render($response, 'episode.twig', [
            'episode' => $ep,
            'langs' => $langs,
            'slug' => $properSlug,
            'prev_url' => $prevEp ? $router->pathFor('episode', ['id' => $prevEp->getId(), 'slug' => $slugify->slugify($prevEp->getFullName())]) : '',
            'next_url' => $nextEp ? $router->pathFor('episode', ['id' => $nextEp->getId(), 'slug' => $slugify->slugify($nextEp->getFullName())]) : ''
        ]);
    }

    public function edit($epId, $request, $response, EntityManager $em, Twig $twig)
    {
        $ep = $em->getRepository('App:Episode')->find($epId);
        if (!$ep) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        return $twig->render($response, 'edit_episode.twig', [
            'episode' => $ep
        ]);
    }

    public function saveEdit($epId, $request, $response, EntityManager $em, Twig $twig, Auth $auth, \Slim\Router $router)
    {
        $ep = $em->getRepository('App:Episode')->find($epId);
        if (!$ep) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        $season = (int)$request->getParam('season', '');
        $epNumber = (int)$request->getParam('episode', '');
        $epName = trim(strip_tags($request->getParam('name', '')));
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
            $auth->addFlash('success', 'Parámetros de capítulo actualizados');
            $em->persist($ep);
            $em->flush();
        }

        return $response->withHeader('Location', $router->pathFor('ep-edit', ['epId' => $epId]));
    }
}
