<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

namespace App\Controllers;

use App\Services\Auth;
use App\Services\Langs;
use App\Services\Sonic;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\EntityManager;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class SearchController
{
    // TODO: Separate popular listing into a weekly thing, with its own "popular" table and stuff
    public function listPopular(RequestInterface $request, ResponseInterface $response, EntityManager $em, SlugifyInterface $slugify)
    {
        $episodes = $em->createQuery('SELECT e FROM App:Episode e ORDER BY e.downloads DESC')->setMaxResults(10)->getResult();

        $epList = [];
        foreach ($episodes as $ep) {
            $fullName = $ep->getFullName();

            $epList[] = [
                'id' => $ep->getId(),
                'name' => $ep->getName(),
                'show' => $ep->getShow()->getName(),
                'download_count' => $ep->getDownloads(),
                'slug' => $slugify->slugify($fullName),
                'season' => $ep->getSeason(),
                'episode_num' => $ep->getNumber()
            ];
        }

        return $response->withJson($epList);
    }

    public function listRecentUploads($request, $response, EntityManager $em, SlugifyInterface $slugify, Auth $auth)
    {
        $page = max(1, min(10, (int)$request->getQueryParam('page', 1))) - 1;
        $subs = $em->createQuery('SELECT s, v, e FROM App:Subtitle s JOIN s.version v JOIN v.episode e WHERE s.directUpload = 1 AND s.resync = 0 ORDER BY s.uploadTime DESC')
            ->setMaxResults(10)
            ->setFirstResult($page * 10)
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
                'hide_details' => $hideDetails
            ];
        }

        return $response->withJson($epList);
    }

    public function listRecentChanged(RequestInterface $request, ResponseInterface $response, EntityManager $em, SlugifyInterface $slugify, Auth $auth)
    {
        $page = max(1, min(10, (int)$request->getQueryParam('page', 1))) - 1;
        $subs = $em->createQuery('SELECT s, v, e FROM App:Subtitle s JOIN s.version v JOIN v.episode e WHERE s.directUpload = 0 AND s.editTime IS NOT NULL ORDER BY s.editTime DESC')
            ->setMaxResults(10)
            ->setFirstResult($page * 10)
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
                'last_edited_by' => $sub->getLastEditedBy() ? $sub->getLastEditedBy()->getUsername() : '',
                'progress' => floor($sub->getProgress()),
                'hide_details' => $hideDetails
            ];
        }

        return $response->withJson($epList);
    }

    public function listRecentCompleted(RequestInterface $request, ResponseInterface $response, EntityManager $em, SlugifyInterface $slugify, Auth $auth)
    {
        $page = max(1, min(10, (int)$request->getQueryParam('page', 1))) - 1;
        $subs = $em->createQuery('SELECT s, v, e FROM App:Subtitle s JOIN s.version v JOIN v.episode e WHERE s.directUpload = 0 AND s.progress = 100 AND s.pause IS NULL AND s.completeTime IS NOT NULL ORDER BY s.completeTime DESC')
            ->setMaxResults(10)
            ->setFirstResult($page * 10)
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
                'hide_details' => $hideDetails
            ];
        }

        return $response->withJson($epList);
    }

    public function listRecentResyncs(RequestInterface $request, ResponseInterface $response, EntityManager $em, SlugifyInterface $slugify, Auth $auth)
    {
        $page = max(1, min(10, (int)$request->getQueryParam('page', 1))) - 1;
        $subs = $em->createQuery('SELECT s, v, e FROM App:Subtitle s JOIN s.version v JOIN v.episode e WHERE s.directUpload = 1 AND s.resync = 1 ORDER BY s.uploadTime DESC')
            ->setMaxResults(10)
            ->setFirstResult($page * 10)
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
                'hide_details' => $hideDetails
            ];
        }

        return $response->withJson($epList);
    }

    public function query($request, $response, EntityManager $em)
    {
        $q = $request->getQueryParam('q');
        if (empty($q)) {
            return $response->withStatus(400);
        }

        $shows = [];
        if (mb_strlen($q) > 2) {
            $words = explode(' ', $q);

            $search = Sonic::getSearchClient();
            $newQuery = [];
            foreach ($words as $word) {
                if (!$word) {
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
                $showIds = $search->query(Sonic::SHOW_NAME_COLLECTION, 'default', $newQ, 10);
                if (count($showIds) > 0) {
                    $shows = $em->createQuery('SELECT s.id, s.name FROM App:Show s WHERE s.id IN (:shows)')
                        ->setParameter('shows', $showIds)
                        ->getResult();

                    foreach ($shows as $show) {
                        $resultList[] = [
                            'id' => $show['id'],
                            'name' => $show['name'],
                        ];
                    }
                }
            }

            $search->disconnect();
            return $response->withJson($resultList);
        }

        return $response->withStatus(400);
    }

    public function listPaused($request, $response, EntityManager $em, SlugifyInterface $slugify, Auth $auth)
    {
        $page = max(1, min(10, (int)$request->getQueryParam('page', 1))) - 1;
        $subs = $em->createQuery('SELECT s, v, e FROM App:Subtitle s JOIN s.version v JOIN v.episode e JOIN s.pause p WHERE s.pause IS NOT NULL ORDER BY p.start ASC')
            ->setMaxResults(10)
            ->setFirstResult($page * 10)
            ->getResult();

        $hideDetails = !$auth->hasRole('ROLE_TT');
        $epList = [];
        foreach ($subs as $sub) {
            $ep = $sub->getVersion()->getEpisode();
            $fullName = $ep->getFullName();

            $epList[] = [
                'id' => $ep->getId(),
                'name' => $fullName,
                'slug' => $slugify->slugify($fullName),
                'season' => $ep->getSeason(),
                'episode_num' => $ep->getNumber(),
                'time' => $sub->getPause()->getStart()->format(\DateTime::ATOM),
                'lang' => Langs::getLocalizedName(Langs::getLangCode($sub->getLang())),
                'progress' => floor($sub->getProgress()),
                'hide_details' => $hideDetails
            ];
        }

        return $response->withJson($epList);
    }
}
