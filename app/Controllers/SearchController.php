<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017 subtitulamos.tv
 */

namespace App\Controllers;

use \Psr\Http\Message\ResponseInterface;
use \Psr\Http\Message\RequestInterface;

use \Doctrine\ORM\EntityManager;
use \Cocur\Slugify\SlugifyInterface;
use \Slim\Views\Twig;

use \App\Entities\Episode;
use \App\Services\Langs;

class SearchController
{
    // TODO: Separate popular listing into a weekly thing, with its own "popular" table and stuff
    public function listPopular(RequestInterface $request, ResponseInterface $response, EntityManager $em, SlugifyInterface $slugify)
    {
        $episodes = $em->createQuery("SELECT e FROM App:Episode e ORDER BY e.downloads DESC")->setMaxResults(10)->getResult();

        $epList = [];
        foreach ($episodes as $ep) {
            $fullName = $ep->getFullName();

            $epList[] = [
                "id" => $ep->getId(),
                "name" => $fullName,
                "download_count" => $ep->getDownloads(),
                "slug" => $slugify->slugify($fullName),
                "season" => $ep->getSeason(),
                "episode_num" => $ep->getNumber()
            ];
        }

        return $response->withJson($epList);
    }

    public function listRecentUploads($request, $response, EntityManager $em, SlugifyInterface $slugify)
    {
        $page = max(1, min(10, (int)$request->getQueryParam('page', 1))) - 1;
        $subs = $em->createQuery("SELECT s, v, e FROM App:Subtitle s JOIN s.version v JOIN v.episode e WHERE s.directUpload = 1 AND s.resync = 0 ORDER BY s.uploadTime DESC")
            ->setMaxResults(10)
            ->setFirstResult($page * 10)
            ->getResult();

        $epList = [];
        foreach ($subs as $sub) {
            $ep = $sub->getVersion()->getEpisode();
            $fullName = $ep->getFullName();

            $epList[] = [
                "id" => $ep->getId(),
                "name" => $fullName,
                "slug" => $slugify->slugify($fullName),
                "season" => $ep->getSeason(),
                "episode_num" => $ep->getNumber(),
                "time" => $sub->getUploadTime()->format(\DateTime::ATOM),
                "lang_name" => Langs::getLocalizedName(Langs::getLangCode($sub->getLang())),
            ];
        }

        return $response->withJson($epList);
    }

    public function listRecentChanged(RequestInterface $request, ResponseInterface $response, EntityManager $em, SlugifyInterface $slugify)
    {
        $page = max(1, min(10, (int)$request->getQueryParam('page', 1))) - 1;
        $subs = $em->createQuery("SELECT s, v, e FROM App:Subtitle s JOIN s.version v JOIN v.episode e WHERE s.directUpload = 0 AND s.editTime IS NOT NULL ORDER BY s.editTime DESC")
            ->setMaxResults(10)
            ->setFirstResult($page * 10)
            ->getResult();

        $epList = [];
        foreach ($subs as $sub) {
            $ep = $sub->getVersion()->getEpisode();
            $fullName = $ep->getFullName();

            $epList[] = [
                "id" => $ep->getId(),
                "name" => $fullName,
                "slug" => $slugify->slugify($fullName),
                "time" => $sub->getEditTime()->format(\DateTime::ATOM),
                "lang_name" => Langs::getLocalizedName(Langs::getLangCode($sub->getLang())),
            ];
        }

        return $response->withJson($epList);
    }

    public function listRecentCompleted(RequestInterface $request, ResponseInterface $response, EntityManager $em, SlugifyInterface $slugify)
    {
        $page = max(1, min(10, (int)$request->getQueryParam('page', 1))) - 1;
        $subs = $em->createQuery("SELECT s, v, e FROM App:Subtitle s JOIN s.version v JOIN v.episode e WHERE s.directUpload = 0 AND s.progress = 100 AND s.pause IS NULL AND s.completeTime IS NOT NULL ORDER BY s.completeTime DESC")
            ->setMaxResults(10)
            ->setFirstResult($page * 10)
            ->getResult();

        $epList = [];
        foreach ($subs as $sub) {
            $ep = $sub->getVersion()->getEpisode();
            $fullName = $ep->getFullName();

            $epList[] = [
                "id" => $ep->getId(),
                "name" => $fullName,
                "slug" => $slugify->slugify($fullName),
                "time" => $sub->getCompleteTime()->format(\DateTime::ATOM),
                "lang_name" => Langs::getLocalizedName(Langs::getLangCode($sub->getLang())),
            ];
        }

        return $response->withJson($epList);
    }

    public function query($request, $response, EntityManager $em, \Elasticsearch\Client $client)
    {
        $q = $request->getQueryParam('q');
        if (empty($q)) {
            return $response->withStatus(400);
        }

        $shows = [];

        if (strlen($q) > 3) {
            $episode = $season = -1;

            if (\preg_match('/(\d+)x(\d+)?|S(\d+)E?(\d+)?/i', $q, $matches)) {
                // We may have episode & season
                $season = $matches[1] ? (int)$matches[1] : ($matches[3] ? (int)$matches[3] : -1);
                $episode = $matches[2] ? (int)$matches[2] : ($matches[4] ? (int)$matches[4] : -1);
                $showName = trim(str_replace($matches[0], '', $q));
            }
            else {
                // No episode in search
                $showName = $q;
            }

            $r = $client->search([
                'index' => ELASTICSEARCH_NAMESPACE . '_shows',
                'type' => 'show',
                'body' => [
                    'query' => [
                        'match' => [
                            'name' => [
                                'query' => $showName,
                                'fuzziness' => 'AUTO',
                                'cutoff_frequency' => 0.01
                            ]
                        ]
                    ]
                ]
            ]);

            if (!empty($r['hits'])) {
                foreach ($r['hits']['hits'] as $hit) {
                    $eps = [];
                    if ($season >= 0 || $episode >= 0) {
                        $ep = $em->createQuery("SELECT e FROM App:Episode e WHERE e.show = :show AND e.number = :epnum AND e.season = :season")
                            ->setParameter('show', $hit['_id'])
                            ->setParameter('epnum', $episode)
                            ->setParameter('season', $season)
                            ->getOneOrNullResult();

                        if ($ep) {
                            $eps[] = [
                                'id' => $ep->getId(),
                                'name' => $ep->getName(),
                                'season' => $ep->getSeason(),
                                'number' => $ep->getNumber()
                            ];
                        }
                    }

                    $shows[] = [
                        'id' => $hit['_id'],
                        'name' => $hit['_source']['name'],
                        'episodes' => $eps
                    ];
                }
            }
        }

        return $response->withJson($shows);
    }
}
