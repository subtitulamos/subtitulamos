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

class EpisodeController
{
    public function view($id, RequestInterface $request, ResponseInterface $response, EntityManager $em, Twig $twig)
    {
        $ep = $em->createQuery("SELECT s, v, e FROM App:Episode e JOIN e.versions v JOIN v.subtitles s WHERE e.id = :id")
                   ->setParameter("id", $id)
                   ->getOneOrNullResult();
        
        if(!$ep) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        $langs = [];
        foreach($ep->getVersions() as $version) {
            foreach($version->getSubtitles() as $sub) {
                $lang = \App\Services\Langs::getLangCode($sub->getLang());
                if(!isset($langs[$lang])) {
                    $langs[$lang] = [];
                }

                $langs[$lang][] = $sub;
            }
        }
        
        return $twig->render($response, 'episode.twig', [
            'episode' => $ep,
            'langs' => $langs,
            'uploader_name' => "user"
        ]);
    }
}