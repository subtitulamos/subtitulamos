<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017 subtitulamos.tv
 */

namespace App\Controllers;

use \Slim\Views\Twig;
use \Doctrine\ORM\EntityManager;
use \Cocur\Slugify\SlugifyInterface;

class UserController
{
    public function publicProfile($userId, $request, $response, Twig $twig, EntityManager $em, \Slim\Router $router, SlugifyInterface $slugify)
    {
        $user = $em->getRepository("App:User")->find($userId);
        if (!$user) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        $upEpisodesRes = $em->createQuery("SELECT e FROM App:Episode e JOIN e.versions v WHERE v.user = :uid GROUP BY e.id")
            ->setParameter("uid", $userId)->getResult();

        $uploadedEpisodes = [];
        foreach ($upEpisodesRes as $ep) {
            $fullName = $ep->getFullName();

            $uploadedEpisodes[] = [
                "full_name" => $fullName,
                "url" => $router->pathFor("episode", ["id" => $ep->getId(), "slug" => $slugify->slugify($fullName)])
            ];
        }

        $collabEpisodesRes = $em->createQuery("SELECT e FROM App:Episode e JOIN e.versions v JOIN v.subtitles sb JOIN sb.sequences s WHERE s.author = :uid AND sb.directUpload = 0 GROUP BY e.id")
            ->setParameter("uid", $userId)->getResult();

        $colaboratedEpisodes = [];
        foreach ($collabEpisodesRes as $ep) {
            $fullName = $ep->getFullName();

            $colaboratedEpisodes[] = [
                "full_name" => $fullName,
                "url" => $router->pathFor("episode", ["id" => $ep->getId(), "slug" => $slugify->slugify($fullName)])
            ];
        }

        return $twig->render($response, 'user_profile.twig', [
            'user' => $user,
            'uploaded_episodes' => $uploadedEpisodes,
            'collaborated_episodes' => $colaboratedEpisodes
        ]);
    }
}
