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
        $episodes = $em->createQuery("SELECT e FROM App:Episode e ORDER BY e.downloads DESC")->setMaxResults(5)->getResult();
        
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

    public function listRecentUploads(RequestInterface $request, ResponseInterface $response, EntityManager $em, SlugifyInterface $slugify)
    {
        $subs = $em->createQuery("SELECT s, v, e FROM App:Subtitle s JOIN s.version v JOIN v.episode e WHERE s.isDirectUpload = 1 ORDER BY s.uploadTime DESC")->setMaxResults(5)->getResult();
        
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
                "upload_time" => $sub->getUploadTime()->format(\DateTime::ATOM),
                "lang_name" => Langs::getLocalizedName(Langs::getLangCode($sub->getLang())),
            ];
        }

        return $response->withJson($epList);
    }
}
