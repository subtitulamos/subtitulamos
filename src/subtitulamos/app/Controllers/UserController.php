<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

namespace App\Controllers;

use App\Entities\Ban;
use App\Services\Auth;
use App\Services\UrlHelper;
use App\Services\Utils;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\EntityManager;
use Respect\Validation\Validator as v;

use Slim\Views\Twig;

class UserController
{
    private function renderProfile($user, $request, $response, Twig $twig, EntityManager $em, UrlHelper $urlHelper, SlugifyInterface $slugify, bool $isSelf)
    {
        $upEpisodesRes = $em->createQuery('SELECT e FROM App:Episode e JOIN e.versions v WHERE v.user = :uid GROUP BY e.id')
            ->setParameter('uid', $user->getId())->getResult();

        $uploadedEpisodes = [];
        foreach ($upEpisodesRes as $ep) {
            $fullName = $ep->getFullName();

            $uploadedEpisodes[] = [
                'full_name' => $fullName,
                'url' => $urlHelper->pathFor('episode', ['id' => $ep->getId(), 'slug' => $slugify->slugify($fullName)])
            ];
        }

        $collabEpisodesRes = $em->createQuery('SELECT e FROM App:Episode e JOIN e.versions v JOIN v.subtitles sb JOIN sb.sequences s WHERE s.author = :uid AND sb.directUpload = 0 GROUP BY e.id')
            ->setParameter('uid', $user->getId())->getResult();

        $colaboratedEpisodes = [];
        foreach ($collabEpisodesRes as $ep) {
            $fullName = $ep->getFullName();

            $colaboratedEpisodes[] = [
                'full_name' => $fullName,
                'url' => $urlHelper->pathFor('episode', ['id' => $ep->getId(), 'slug' => $slugify->slugify($fullName)])
            ];
        }

        return $twig->render($response, 'user.twig', [
            'user' => $user,
            'uploaded_episodes' => $uploadedEpisodes,
            'collaborated_episodes' => $colaboratedEpisodes,
            'page_type' => $isSelf ? 'me' : 'public'
        ]);
    }

    public function publicProfile($userId, $request, $response, Twig $twig, EntityManager $em, UrlHelper $urlHelper, SlugifyInterface $slugify)
    {
        $user = $em->getRepository('App:User')->find($userId);
        if (!$user) {
            throw new \Slim\Exception\HttpNotFoundException($request);
        }

        return $this->renderProfile($user, $request, $response, $twig, $em, $urlHelper, $slugify, false);
    }

    public function viewSettings($request, $response, Twig $twig, EntityManager $em, UrlHelper $urlHelper, SlugifyInterface $slugify, Auth $auth)
    {
        return $this->renderProfile($auth->getUser(), $request, $response, $twig, $em, $urlHelper, $slugify, true);
    }

    public function saveSettings($request, $response, Twig $twig, Auth $auth, UrlHelper $urlHelper, EntityManager $em)
    {
        $user = $auth->getUser();
        $password = $body['newpwd'] ?? '';
        if ($password != '') {
            $password_confirmation = $body['pwdconfirm'] ?? '';

            // TODO: Unify this into a single validation/encryption point with reg
            if (!v::length(8, 80)->validate($password)) {
                $auth->addFlash('error', 'La contraseña debe tener 8 caracteres como mínimo');
            } elseif ($password != $password_confirmation) {
                $auth->addFlash('error', 'Las contraseñas no coinciden');
            } else {
                $auth->addFlash('success', 'Contraseña cambiada correctamente');
                $user->setPasswordWithHash($password);

                $em->flush();
            }
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
        $user->setPasswordWithHash($pwd);
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
            $roles[] = 'ROLE_TT';
            $user->setRoles($roles);
        } else {
            array_splice($roles, $ttPos, 1);
            $user->setRoles($roles);
        }

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
        $durationType = $body['days'] ?? '';
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

            $user->setBan($ban);

            $em->persist($ban);
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
