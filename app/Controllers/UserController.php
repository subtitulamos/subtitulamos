<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017 subtitulamos.tv
 */

namespace App\Controllers;

use \Slim\Views\Twig;
use \Doctrine\ORM\EntityManager;
use \Cocur\Slugify\SlugifyInterface;
use App\Services\Auth;

use \Respect\Validation\Validator as v;

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

        return $twig->render($response, 'user_public_profile.twig', [
            'user' => $user,
            'uploaded_episodes' => $uploadedEpisodes,
            'collaborated_episodes' => $colaboratedEpisodes
        ]);
    }

    public function viewSettings($request, $response, Twig $twig, Auth $auth)
    {
        $user = $auth->getUser();
        return $twig->render($response, 'user_settings.twig', [
            'user' => $user
        ]);
    }

    public function saveSettings($request, $response, Twig $twig, Auth $auth, \Slim\Router $router)
    {
        $user = $auth->getUser();
        $password = $request->getParam('newpwd', '');
        if ($password != '') {
            $password_confirmation = $request->getParam('pwdconfirm', '');

            // TODO: Unify this into a single validation/encryption point
            $errors = [];
            if (!v::length(8, 80)->validate($password)) {
                $auth->addFlash("error", "La contraseña debe tener 8 caracteres como mínimo");
            }
            elseif ($password != $password_confirmation) {
                $auth->addFlash("error", "Las contraseñas no coinciden");
            }
            else {
                $auth->addFlash("success", "Contraseña cambiada correctamente");
                $user->setPassword(\password_hash($password, \PASSWORD_BCRYPT, ['cost' => 13]));
            }
        }

        return $response->withHeader('Location', $router->pathFor("settings"));
    }
}
