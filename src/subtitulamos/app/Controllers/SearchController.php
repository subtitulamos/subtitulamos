<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2021 subtitulamos.tv
 */

namespace App\Controllers;

use App\Services\Auth;
use App\Services\Langs;
use App\Services\Sonic;
use App\Services\Utils;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\EntityManager;
use Psonic\Exceptions\CommandFailedException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class SearchController
{
    public function listRecentUploads($request, $response, EntityManager $em, SlugifyInterface $slugify, Auth $auth)
    {
        $params = $request->getQueryParams();
        $resultCount = min((int)($params['count'] ?? 5), 15);
        $from = max((int)($params['from'] ?? 0), 0);

        $subs = $em->createQuery('SELECT s, v, e FROM App:Subtitle s JOIN s.version v JOIN v.episode e WHERE s.directUpload = 1 AND s.resync = 0 ORDER BY s.uploadTime DESC')
            ->setMaxResults($resultCount)
            ->setFirstResult($from)
            ->getResult();

        $hideDetails = !$auth->hasRole('ROLE_TT');
        $epList = [];
        foreach ($subs as $sub) {
            $ep = $sub->getVersion()->getEpisode();
            $fullName = $ep->getFullName();

            $epList[] = [
                'id' => $ep->getId(),
                'name' => $ep->getName(),
                'show' => $ep->getShow()->getName(),
                'slug' => $slugify->slugify($fullName),
                'season' => $ep->getSeason(),
                'episode_num' => $ep->getNumber(),
                'time' => $sub->getUploadTime()->format(\DateTime::ATOM),
                'lang' => Langs::getLocalizedName(Langs::getLangCode($sub->getLang())),
                'version' => $sub->getVersion()->getName(),
                'hide_details' => $hideDetails,
                'full_name' => $fullName,
            ];
        }

        return Utils::jsonResponse($response, $epList);
    }

    public function listRecentChanged(ServerRequestInterface $request, ResponseInterface $response, EntityManager $em, SlugifyInterface $slugify, Auth $auth)
    {
        $params = $request->getQueryParams();
        $resultCount = min((int)($params['count'] ?? 5), 15);
        $from = max((int)($params['from'] ?? 0), 0);

        $subs = $em->createQuery('SELECT s, v, e FROM App:Subtitle s JOIN s.version v JOIN v.episode e WHERE s.directUpload = 0 AND s.editTime IS NOT NULL ORDER BY s.editTime DESC')
            ->setMaxResults($resultCount)
            ->setFirstResult($from)
            ->getResult();

        $hideDetails = !$auth->hasRole('ROLE_TT');
        $epList = [];
        foreach ($subs as $sub) {
            $ep = $sub->getVersion()->getEpisode();
            $fullName = $ep->getFullName();

            $epList[] = [
                'id' => $ep->getId(),
                'name' => $ep->getName(),
                'show' => $ep->getShow()->getName(),
                'slug' => $slugify->slugify($fullName),
                'season' => $ep->getSeason(),
                'episode_num' => $ep->getNumber(),
                'time' => $sub->getEditTime()->format(\DateTime::ATOM),
                'lang' => Langs::getLocalizedName(Langs::getLangCode($sub->getLang())),
                'version' => $sub->getVersion()->getName(),
                'last_edited_by' => $sub->getLastEditedBy() ? $sub->getLastEditedBy()->getUsername() : '',
                'progress' => floor($sub->getProgress()),
                'hide_details' => $hideDetails,
                'full_name' => $fullName,
            ];
        }

        return Utils::jsonResponse($response, $epList);
    }

    public function listRecentCompleted(ServerRequestInterface $request, ResponseInterface $response, EntityManager $em, SlugifyInterface $slugify, Auth $auth)
    {
        $params = $request->getQueryParams();
        $resultCount = min((int)($params['count'] ?? 5), 15);
        $from = max((int)($params['from'] ?? 0), 0);

        $subs = $em->createQuery('SELECT s, v, e FROM App:Subtitle s JOIN s.version v JOIN v.episode e WHERE s.directUpload = 0 AND s.progress = 100 AND s.pause IS NULL AND s.completeTime IS NOT NULL ORDER BY s.completeTime DESC')
            ->setMaxResults($resultCount)
            ->setFirstResult($from)
            ->getResult();

        $hideDetails = !$auth->hasRole('ROLE_TT');
        $epList = [];
        foreach ($subs as $sub) {
            $ep = $sub->getVersion()->getEpisode();
            $fullName = $ep->getFullName();

            $epList[] = [
                'id' => $ep->getId(),
                'name' => $ep->getName(),
                'show' => $ep->getShow()->getName(),
                'season' => $ep->getSeason(),
                'episode_num' => $ep->getNumber(),
                'slug' => $slugify->slugify($fullName),
                'time' => $sub->getCompleteTime()->format(\DateTime::ATOM),
                'lang' => Langs::getLocalizedName(Langs::getLangCode($sub->getLang())),
                'version' => $sub->getVersion()->getName(),
                'hide_details' => $hideDetails,
                'full_name' => $fullName,
            ];
        }

        return Utils::jsonResponse($response, $epList);
    }

    public function listRecentResyncs(ServerRequestInterface $request, ResponseInterface $response, EntityManager $em, SlugifyInterface $slugify, Auth $auth)
    {
        $params = $request->getQueryParams();
        $resultCount = min((int)($params['count'] ?? 5), 15);
        $from = max((int)($params['from'] ?? 0), 0);

        $subs = $em->createQuery('SELECT s, v, e FROM App:Subtitle s JOIN s.version v JOIN v.episode e WHERE s.directUpload = 1 AND s.resync = 1 ORDER BY s.uploadTime DESC')
            ->setMaxResults($resultCount)
            ->setFirstResult($from)
            ->getResult();

        $hideDetails = !$auth->hasRole('ROLE_TT');
        $epList = [];
        foreach ($subs as $sub) {
            $ep = $sub->getVersion()->getEpisode();
            $fullName = $ep->getFullName();

            $epList[] = [
                'id' => $ep->getId(),
                'name' => $ep->getName(),
                'show' => $ep->getShow()->getName(),
                'season' => $ep->getSeason(),
                'episode_num' => $ep->getNumber(),
                'slug' => $slugify->slugify($fullName),
                'time' => $sub->getUploadTime()->format(\DateTime::ATOM),
                'lang' => Langs::getLocalizedName(Langs::getLangCode($sub->getLang())),
                'version' => $sub->getVersion()->getName(),
                'last_edited_by' => $sub->getVersion()->getUser()->getUsername(),
                'hide_details' => $hideDetails,
                'full_name' => $fullName,
            ];
        }

        return Utils::jsonResponse($response, $epList);
    }

    public function query($request, $response, EntityManager $em)
    {
        $params = $request->getQueryParams();
        $q = $params['q'] ?? '';
        if (empty($q)) {
            return $response->withStatus(400);
        }

        $shows = [];
        if (mb_strlen($q) > 2) {
            $words = explode(' ', $q);

            $search = Sonic::getSearchClient();
            $newQuery = [];
            foreach ($words as $word) {
                if (!$word || mb_strlen($word) == 1) {
                    continue;
                }

                $sugg = $search->suggest(Sonic::SHOW_NAME_COLLECTION, 'default', $word, /* limit */ 1);
                if (!isset($sugg[0]) || !$sugg[0]) {
                    continue;
                }

                $newQuery[] = $sugg[0];
            }

            $newQ = implode(' ', $newQuery);
            $resultList = [];
            if ($newQ) {
                // Query full, original query (top 5)
                $failed = true;
                try {
                    $showIdsOriginal = $search->query(Sonic::SHOW_NAME_COLLECTION, 'default', $q, 5);
                } catch (CommandFailedException $e) {
                    error_log("Search failed (original)! Query: <$q>. Error: ".$e->getMessage());
                    $showIdsOriginal = [];
                    $failed = true;
                }

                try {
                    $showIdsSuggested = $search->query(Sonic::SHOW_NAME_COLLECTION, 'default', $newQ, 10);
                } catch (CommandFailedException $e) {
                    $showIdsSuggested = [];
                    error_log("Search failed (suggested)! Query: <$newQ>. Error: ".$e->getMessage());
                    if ($failed) {
                        // If the other search also failed, something's probably broken
                        throw $e;
                    }
                }

                $showIds = array_unique(array_merge($showIdsOriginal, $showIdsSuggested));
                if (count($showIds) > 0) {
                    $shows = $em->createQuery('SELECT s.id, s.name FROM App:Show s WHERE s.id IN (:shows)')
                        ->setParameter('shows', $showIds)
                        ->getResult();

                    foreach ($shows as $show) {
                        $resultList[] = [
                            'id' => $show['id'],
                            'name' => $show['name'],
                            'score' => levenshtein($q, $show['name'], /* insert cost */ 2, /* replacement cost */ 1, /* deletion cost */ 3)
                        ];
                    }
                }

                usort($resultList, function ($a, $b) {
                    return $a['score'] <=> $b['score'];
                });
            }

            $search->disconnect();
            return Utils::jsonResponse($response, $resultList);
        }

        return $response->withStatus(400);
    }

    public function listPaused(ServerRequestInterface $request, ResponseInterface $response, EntityManager $em, SlugifyInterface $slugify, Auth $auth)
    {
        $params = $request->getQueryParams();
        $resultCount = min((int)($params['count'] ?? 5), 15);
        $from = max((int)($params['from'] ?? 0), 0);

        $subs = $em->createQuery('SELECT s, v, e FROM App:Subtitle s JOIN s.version v JOIN v.episode e JOIN s.pause p WHERE s.pause IS NOT NULL ORDER BY p.start ASC')
            ->setMaxResults($resultCount)
            ->setFirstResult($from)
            ->getResult();

        $hideDetails = !$auth->hasRole('ROLE_TT');
        $epList = [];
        foreach ($subs as $sub) {
            $ep = $sub->getVersion()->getEpisode();
            $fullName = $ep->getFullName();

            $epList[] = [
                'id' => $ep->getId(),
                'name' => $ep->getName(),
                'slug' => $slugify->slugify($fullName),
                'season' => $ep->getSeason(),
                'episode_num' => $ep->getNumber(),
                'time' => $sub->getPause()->getStart()->format(\DateTime::ATOM),
                'lang' => Langs::getLocalizedName(Langs::getLangCode($sub->getLang())),
                'version' => $sub->getVersion()->getName(),
                'version' => $sub->getVersion()->getName(),
                'progress' => floor($sub->getProgress()),
                'hide_details' => $hideDetails,
                'full_name' => $fullName,
            ];
        }

        return Utils::jsonResponse($response, $epList);
    }
}
