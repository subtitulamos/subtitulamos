<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2021 subtitulamos.tv
 */

namespace App\Controllers;

use App\Entities\Ban;
use App\Entities\EventLog;
use App\Services\Auth;
use App\Services\UrlHelper;
use App\Services\Utils;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\EntityManager;
use Respect\Validation\Validator as v;

use Slim\Views\Twig;

class UserController
{
    private function renderProfile($user, $request, $response, Twig $twig, bool $isSelf)
    {
    }

    public function loadUploadList($userId, $request, $response, EntityManager $em, UrlHelper $urlHelper, SlugifyInterface $slugify)
    {
        $user = $em->getRepository('App:User')->find($userId);
        if (!$user) {
            throw new \Slim\Exception\HttpNotFoundException($request);
        }

        $upEpisodesRes = $em->createQuery('SELECT e FROM App:Episode e JOIN e.versions v WHERE v.user = :uid GROUP BY e.id')
            ->setParameter('uid', $user->getId())->getResult();

        $uploadedEpisodes = [];
        foreach ($upEpisodesRes as $ep) {
            $show = $ep->getShow()->getName();
            $season = $ep->getSeason();
            $episodeNumber = $ep->getNumber();
            $name = $ep->getName();
            $fullName = $ep->getFullName();

            $uploadedEpisodes[] = [
                'show' => $show,
                'season' => $season,
                'episode_number' => $episodeNumber,
                'name' => $name,
                'full_name' => $fullName,
                'url' => $urlHelper->pathFor('episode', ['id' => $ep->getId(), 'slug' => $slugify->slugify($fullName)])
            ];
        }

        usort($uploadedEpisodes, function ($a, $b) {
            return strnatcasecmp($a['full_name'], $b['full_name']);
        });

        return Utils::jsonResponse($response, $uploadedEpisodes)->withStatus(200);
    }

    public function loadCollaborationsList($userId, $request, $response, EntityManager $em, UrlHelper $urlHelper, SlugifyInterface $slugify)
    {
        $user = $em->getRepository('App:User')->find($userId);
        if (!$user) {
            throw new \Slim\Exception\HttpNotFoundException($request);
        }

        $collabEpisodesRes = $em->createQuery('SELECT e FROM App:Episode e JOIN e.versions v JOIN v.subtitles sb JOIN sb.sequences s WHERE s.author = :uid AND sb.directUpload = 0 GROUP BY e.id')
            ->setParameter('uid', $user->getId())->getResult();

        $colaboratedEpisodes = [];
        foreach ($collabEpisodesRes as $ep) {
            $show = $ep->getShow()->getName();
            $season = $ep->getSeason();
            $episodeNumber = $ep->getNumber();
            $name = $ep->getName();
            $fullName = $ep->getFullName();

            $colaboratedEpisodes[] = [
                'show' => $show,
                'season' => $season,
                'episode_number' => $episodeNumber,
                'name' => $name,
                'full_name' => $fullName,
                'url' => $urlHelper->pathFor('episode', ['id' => $ep->getId(), 'slug' => $slugify->slugify($fullName)])
            ];
        }

        usort($colaboratedEpisodes, function ($a, $b) {
            return strnatcasecmp($a['full_name'], $b['full_name']);
        });

        return Utils::jsonResponse($response, $colaboratedEpisodes)->withStatus(200);
    }

    public function publicProfile($userId, $request, $response, Twig $twig, EntityManager $em)
    {
        $user = $em->getRepository('App:User')->find($userId);
        if (!$user) {
            throw new \Slim\Exception\HttpNotFoundException($request);
        }

        return $twig->render($response, 'user.twig', [
            'target_user' => $user,
            'page_type' => 'public'
        ]);
    }

    public function viewSettings($request, $response, Twig $twig, Auth $auth)
    {
        return $twig->render($response, 'user.twig', [
            'target_user' => $auth->getUser(),
            'page_type' => 'me'
        ]);
    }

    public function saveSettings($request, $response, Auth $auth, UrlHelper $urlHelper, EntityManager $em)
    {
        $user = $auth->getUser();
        $body = $request->getParsedBody();
        $oldpass = $body['password-old'] ?? '';
        $password = $body['password-new'] ?? '';
        $fontFamily = $body['font-family'] ?? '';
        $colorSwatch = $body['color-swatch'] ?? '';

        if ($oldpass || $password) {
            // Saving user settings form
            if (!$user->checkPassword($oldpass)) {
                $auth->addFlash('error', 'La contraseña antigua no es correcta');
            } elseif ($password != '') {
                $password_confirmation = $body['password-confirmation'] ?? '';

                // TODO: Unify this into a single validation/encryption point with reg
                if (!v::length(8, 80)->validate($password)) {
                    $auth->addFlash('error', 'La contraseña debe tener 8 caracteres como mínimo');
                } elseif ($password != $password_confirmation) {
                    $auth->addFlash('error', 'Las contraseñas no coinciden');
                } else {
                    $auth->addFlash('success', 'Contraseña cambiada correctamente');
                    $user->setPassword($password);

                    $em->flush();
                }
            }
        } else {
            // Saving user prefs
            $prefs = $user->getPrefs();
            $prefs['translation_font'] = $fontFamily;
            $prefs['color_swatch'] = $colorSwatch;
            $user->setPrefs($prefs);
            $em->flush();

            $auth->addFlash('success', 'Preferencias de traducción actualizadas');
        }

        return $response->withHeader('Location', $urlHelper->pathFor('settings'));
    }

    public function resetPassword($userId, $request, $response, EntityManager $em, Auth $auth, UrlHelper $urlHelper)
    {
        $user = $em->getRepository('App:User')->find($userId);
        if (!$user) {
            throw new \Slim\Exception\HttpNotFoundException($request);
        }

        $roles = $user->getRoles();
        $isTargetMod = in_array('ROLE_MOD', $roles);
        $isTargetMe = $user->getId() === $auth->getUser()->getId();
        if ($isTargetMod || $isTargetMe) {
            return $response->withHeader('Location', $urlHelper->pathFor('user', ['userId' => $userId]));
        }

        $pwd = Utils::generateRandomString(16);
        $user->setPassword($pwd);

        $event = new EventLog($auth->getUser(), new \DateTime(), sprintf('Contraseña de usuario reiniciada ([[user:%d]])', $user->getId()));
        $em->persist($event);
        $em->flush();

        $auth->addFlash('success', "Contraseña reiniciada. Nueva contraseña: $pwd");
        return $response->withHeader('Location', $urlHelper->pathFor('user', ['userId' => $userId]));
    }

    public function changeRole($userId, $request, $response, EntityManager $em, Auth $auth, UrlHelper $urlHelper)
    {
        $user = $em->getRepository('App:User')->find($userId);
        if (!$user) {
            throw new \Slim\Exception\HttpNotFoundException($request);
        }

        $roles = $user->getRoles();
        $isTargetMod = in_array('ROLE_MOD', $roles);
        $isTargetMe = $user->getId() === $auth->getUser()->getId();
        if ($isTargetMod || $isTargetMe) {
            return $response->withHeader('Location', $urlHelper->pathFor('user', ['userId' => $userId]));
        }

        // For now, this takes no input and just swaps TH/Not TH status
        $ttPos = array_search('ROLE_TT', $roles);
        if ($ttPos === false) {
            $event = new EventLog($auth->getUser(), new \DateTime(), sprintf('TH dado a usuario ([[user:%d]])', $user->getId()));
            $roles[] = 'ROLE_TT';
            $user->setRoles($roles);
        } else {
            $event = new EventLog($auth->getUser(), new \DateTime(), sprintf('TH quitado del usuario ([[user:%d]])', $user->getId()));
            array_splice($roles, $ttPos, 1);
            $user->setRoles($roles);
        }

        $em->persist($event);
        $em->persist($user);
        $em->flush();

        return $response->withHeader('Location', $urlHelper->pathFor('user', ['userId' => $userId]));
    }

    public function ban($userId, $request, $response, EntityManager $em, Auth $auth, UrlHelper $urlHelper)
    {
        $user = $em->getRepository('App:User')->find($userId);
        if (!$user) {
            throw new \Slim\Exception\HttpNotFoundException($request);
        }

        $body = $request->getParsedBody();
        $durationType = $body['duration-type'] ?? '';
        $reason = $body['reason'] ?? '';
        $errors = [];

        $until = new \DateTime();
        if ($durationType == 'permanent') {
            $until->modify('+20 years');
        } else {
            $d = isset($body['days']) ? (int)$body['days'] : 0;
            $h = isset($body['hours']) ? (int)$body['hours'] : 0;

            if ($d >= 0 && $h >= 0 && $d + $h > 0) {
                $until->modify(sprintf('+%d days', $d));
                $until->modify(sprintf('+%d hours', $h));
            } else {
                $errors[] = 'Duración del ban incorrecta';
            }
        }

        if (empty($reason)) {
            $errors[] = 'La razón no puede estar vacía';
        }

        if (empty($errors)) {
            $ban = new Ban();
            $ban->setByUser($auth->getUser());
            $ban->setTargetUser($user);
            $ban->setReason(\strip_tags($reason));
            $ban->setUntil($until);
            $em->persist($ban);

            $event = new EventLog($auth->getUser(), new \DateTime(), sprintf('Usuario baneado ([[user:%d]])', $user->getId()));
            $em->persist($event);

            $em->flush();

            $auth->addFlash('success', 'Usuario baneado hasta el '.$until->format('d/M/Y H:i'));
        } else {
            foreach ($errors as $error) {
                $auth->addFlash('error', $error);
            }
        }

        return $response->withHeader('Location', $urlHelper->pathFor('user', ['userId' => $userId]));
    }

    public function unban($userId, $request, $response, EntityManager $em, Auth $auth, UrlHelper $urlHelper)
    {
        $user = $em->getRepository('App:User')->find($userId);
        if (!$user) {
            throw new \Slim\Exception\HttpNotFoundException($request);
        }

        $errors = [];
        $ban = $user->getBan();
        if (!$ban) {
            $errors[] = 'El usuario no está baneado actualmente';
        }

        if (empty($errors)) {
            $user->setBan(null);
            $ban->setUnbanUser($auth->getUser());

            $event = new EventLog($auth->getUser(), new \DateTime(), sprintf('Usuario desbaneado ([[user:%d]])', $user->getId()));
            $em->persist($event);

            $em->flush();
            $auth->addFlash('success', 'El usuario ha sido desbaneado');
        } else {
            foreach ($errors as $error) {
                $auth->addFlash('error', $error);
            }
        }

        return $response->withHeader('Location', $urlHelper->pathFor('user', ['userId' => $userId]));
    }
}
