<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017-2018 subtitulamos.tv
 */

namespace App\Controllers;

use App\Entities\Alert;
use App\Entities\AlertComment;

use App\Entities\Subtitle;
use App\Services\Auth;
use Doctrine\ORM\EntityManager;
use Respect\Validation\Validator as v;

class AlertController
{
    public function subtitleAlert($subId, $request, $response, EntityManager $em, \Slim\Router $router, \Elasticsearch\Client $client, Auth $auth)
    {
        $sub = $em->getRepository('App:Subtitle')->find($subId);
        if (!$sub) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        $msg = trim(strip_tags($request->getParam('message', '')));
        $res = ['ok' => true];
        if (v::notEmpty()->validate($msg)) {
            $alert = new Alert();
            $alert->setByUser($auth->getUser());
            $alert->setCreationTime(new \DateTime());
            $alert->setStatus(0);
            $alert->setSubtitle($sub);

            $com = new AlertComment();
            $com->setUser($auth->getUser());
            $com->setType(0);
            $com->setCreationTime(new \DateTime());
            $com->setAlert($alert);
            $com->setText($msg);

            $em->persist($alert);
            $em->persist($com);
            $em->flush();
        } else {
            $res['ok'] = false;
            $res['msg'] = 'Por favor, detalla la razón de aviso';
        }

        return $response->withJSON($res);
    }

    public function pause($subId, $request, $response, EntityManager $em, \Slim\Router $router, Auth $auth)
    {
        $sub = $em->getRepository('App:Subtitle')->find($subId);
        if (!$sub) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        if ($sub->getPause()) {
            // Already paused!
            return $response->withStatus(200)->withHeader('Location', $router->pathFor('episode', ['id' => $epId]));
        }

        $pause = new Pause();
        $pause->setStart(new \DateTime());
        $pause->setSubtitle($sub);
        $pause->setUser($auth->getUser());
        $em->persist($pause);

        $sub->setPause($pause);
        $em->flush();

        return $response->withStatus(200)->withHeader('Location', $router->pathFor('episode', ['id' => $sub->getVersion()->getEpisode()->getId()]));
    }

    public function unpause($subId, $request, $response, EntityManager $em, \Slim\Router $router, Auth $auth)
    {
        $sub = $em->getRepository('App:Subtitle')->find($subId);
        if (!$sub) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        if (!$sub->getPause()) {
            // Not paused!
            return $response->withStatus(200)->withHeader('Location', $router->pathFor('episode', ['id' => $epId]));
        }

        $pause = $em->getRepository('App:Pause')->find($sub->getPause()->getId());
        $em->remove($pause);
        $sub->setPause(null);

        if ($sub->getProgress() == 100 && !$sub->getCompleteTime()) {
            $sub->setCompleteTime(new \DateTime());
        }

        $em->flush();
        return $response->withStatus(200)->withHeader('Location', $router->pathFor('episode', ['id' => $sub->getVersion()->getEpisode()->getId()]));
    }

    public function viewHammer($subId, $request, $response, EntityManager $em, Twig $twig)
    {
        $sub = $em->getRepository('App:Subtitle')->find($subId);
        if (!$sub) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        $users = $em->createQuery('SELECT u, COUNT(u) FROM App:User u JOIN App:Sequence sq WHERE sq.author = u AND sq.subtitle = :sub GROUP BY u')
            ->setParameter('sub', $sub)
            ->getResult();

        return $twig->render($response, 'hammer.twig', [
            'subtitle' => $sub,
            'users' => $users
        ]);
    }

    public function doHammer($subId, $request, $response, EntityManager $em, Twig $twig, Translation $translation)
    {
        $sub = $em->getRepository('App:Subtitle')->find($subId);
        if (!$sub) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        $target = (int)$request->getParsedBodyParam('user', 0);
        if (!$target) {
            return $response->withStatus(400);
        }

        $seqsToDelete = $em->createQuery('SELECT sq.id, sq.number, sq.revision FROM App:Sequence sq WHERE sq.author = :u AND sq.subtitle = :sub ORDER BY sq.revision DESC')
            ->setParameter('sub', $sub)
            ->setParameter('u', $target)
            ->getResult();

        foreach ($seqsToDelete as $sq) {
            $em->createQuery('UPDATE App:Sequence sq SET sq.revision = sq.revision - 1 WHERE sq.number = :num AND sq.revision >= :rev AND sq.subtitle = :sub')
                ->setParameter('sub', $sub)
                ->setParameter('num', $sq['number'])
                ->setParameter('rev', $sq['revision'])
                ->execute();

            $translation->broadcastDeleteSequence($sub, $sq['id']);
        }

        $em->createQuery('DELETE FROM App:Sequence sq WHERE sq.author = :u AND sq.subtitle = :sub')
            ->setParameter('sub', $sub)
            ->setParameter('u', $target)
            ->getResult();

        // Apply these changes so we can recalculate the proper percentage right after
        $em->flush();

        $translation->recalculateSubtitleProgress($baseSubId, $sub, 0);
        $em->flush();
        return $response;
    }

    public function editProperties($subId, $request, $response, EntityManager $em, Twig $twig)
    {
        $sub = $em->getRepository('App:Subtitle')->find($subId);
        if (!$sub) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        $v = $sub->getVersion();
        $ep = $v->getEpisode();

        return $twig->render($response, 'edit_subtitle.twig', [
            'episode' => $ep,
            'version' => $v,
            'subtitle' => $sub,
            'lang' => Langs::getLangCode($sub->getLang())
        ]);
    }

    public function saveProperties($subId, $request, $response, EntityManager $em, Twig $twig, Auth $auth, \Slim\Router $router)
    {
        $sub = $em->getRepository('App:Subtitle')->find($subId);
        if (!$sub) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        $v = $sub->getVersion();

        $vname = trim(strip_tags($request->getParam('vname', '')));
        $vcomment = trim(strip_tags($request->getParam('vcomment', '')));
        $langCode = $request->getParam('lang', -1);

        if (!Langs::existsCode($langCode)) {
            $errors[] = 'Elige un idioma válido';
        }

        if (!v::notEmpty()->validate($vname) || !v::notEmpty()->validate($vcomment)) {
            $errors[] = 'Ni el nombre de la versión ni los comentarios pueden estar vacíos';
        }

        if (empty($errors) && $vname != $v->getName()) {
            $existingVersion = $em->createQuery('SELECT v FROM App:Version v WHERE v.episode = :ep AND v.name = :name')
                ->setParameter('ep', $v->getEpisode())
                ->setParameter('name', $vname)
                ->getOneOrNullResult();

            if ($existingVersion && $existingVersion->getId() != $v->getId()) {
                $errors[] = 'Ya existe una versión con este nombre';
            }
        }

        if (empty($errors) && Langs::getLangId($langCode) != $sub->getLang()) {
            $subExists = $em->createQuery('SELECT COUNT(sb.id) FROM App:Subtitle sb WHERE sb.version = :v AND sb.lang = :lang')
                ->setParameter('v', $v)
                ->setParameter('lang', (string)Langs::getLangId($langCode))
                ->getSingleScalarResult();

            if ($subExists) {
                $errors[] = 'Ya existe un subtítulo en este idioma para esta versión';
            }
        }

        if (empty($errors)) {
            $v->setName($vname);
            $v->setComments($vcomment);
            $sub->setLang(Langs::getLangId($langCode));

            $em->persist($v);
            $em->persist($sub);
            $em->flush();

            $auth->addFlash('success', 'Parámetros de versión / subtítulo actualizados');
        } else {
            foreach ($errors as $error) {
                $auth->addFlash('error', $error);
            }
        }

        return $response->withHeader('Location', $router->pathFor('subtitle-edit', ['subId' => $subId]));
    }
}
