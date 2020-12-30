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

        $epRes = $em->createQuery('SELECT e FROM App:Episode e WHERE e.show = :id ORDER BY e.season DESC, e.number DESC')
            ->setParameter('id', $showId)
            ->setMaxResults(1)
            ->getOneOrNullResult();

        return $urlHelper->responseWithRedirectToRoute('episode', ['id' => $epRes->getId()]);
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

        return $urlHelper->responseWithRedirectToRoute('show', ['showId' => $showId]);
    }
}
