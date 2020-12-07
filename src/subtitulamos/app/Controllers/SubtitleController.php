<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

namespace App\Controllers;

use App\Entities\Pause;

use App\Services\Auth;
use App\Services\Langs;
use App\Services\Sonic;
use App\Services\Translation;
use App\Services\UrlHelper;
use Doctrine\ORM\EntityManager;
use Respect\Validation\Validator as v;
use Slim\Views\Twig;

class SubtitleController
{
    public function delete($subId, $request, $response, EntityManager $em, UrlHelper $urlHelper)
    {
        $sub = $em->getRepository('App:Subtitle')->find($subId);
        if (!$sub) {
            throw new \Slim\Exception\HttpNotFoundException($request);
        }

        $version = $sub->getVersion();
        $episode = $version->getEpisode();
        $epId = $episode->getId();
        $show = $episode->getShow();

        $episodeDeleted = false;
        if (count($version->getSubtitles()) == 1) { // If this sub was the last of the episode
            if (count($episode->getVersions()) == 1) { // If this sub was the last of the version
                if (count($show->getEpisodes()) == 1) { // If this episode was the last of the show
                    // Remove show from search completely
                    $ingest = Sonic::getIngestClient();
                    $ingest->pop(Sonic::SHOW_NAME_COLLECTION, 'default', $show->getId(), $show->getName());
                    $ingest->disconnect();

                    // Remove from database
                    $em->remove($show);
                }

                $em->remove($episode);
                $episodeDeleted = true;
            }

            $em->remove($version);
        }

        $em->remove($sub);
        $em->flush();
        return $response->withStatus(200)->withHeader('Location', $episodeDeleted ? '/' : $urlHelper->pathFor('episode', ['id' => $epId]));
    }

    public function pause($subId, $request, $response, EntityManager $em, UrlHelper $urlHelper, Auth $auth)
    {
        $sub = $em->getRepository('App:Subtitle')->find($subId);
        if (!$sub) {
            throw new \Slim\Exception\HttpNotFoundException($request);
        }

        if ($sub->getPause()) {
            // Already paused!
            return $urlHelper->responseWithRedirectToRoute('episode', ['id' => $sub->getVersion()->getEpisode()->getId()]);
        }

        $pause = new Pause();
        $pause->setStart(new \DateTime());
        $pause->setSubtitle($sub);
        $pause->setUser($auth->getUser());
        $em->persist($pause);

        $sub->setPause($pause);
        $em->flush();

        return $urlHelper->responseWithRedirectToRoute('episode', ['id' => $sub->getVersion()->getEpisode()->getId()]);
    }

    public function unpause($subId, $request, $response, EntityManager $em, UrlHelper $urlHelper, Auth $auth)
    {
        $sub = $em->getRepository('App:Subtitle')->find($subId);
        if (!$sub) {
            throw new \Slim\Exception\HttpNotFoundException($request);
        }

        if (!$sub->getPause()) {
            // Not paused!
            return $urlHelper->responseWithRedirectToRoute('episode', ['id' => $sub->getVersion()->getEpisode()->getId()]);
        }

        $pause = $em->getRepository('App:Pause')->find($sub->getPause()->getId());
        $em->remove($pause);
        $sub->setPause(null);

        if ($sub->getProgress() == 100 && !$sub->getCompleteTime()) {
            $sub->setCompleteTime(new \DateTime());
        }

        $em->flush();
        return $urlHelper->responseWithRedirectToRoute('episode', ['id' => $sub->getVersion()->getEpisode()->getId()]);
    }

    public function viewHammer($subId, $request, $response, EntityManager $em, Twig $twig, Translation $translation)
    {
        $sub = $em->getRepository('App:Subtitle')->find($subId);
        if (!$sub) {
            throw new \Slim\Exception\HttpNotFoundException($request);
        }

        $sequences = $em->createQuery('SELECT sq FROM App:User u JOIN App:Sequence sq WHERE sq.author = u AND sq.subtitle = :sub')
            ->setParameter('sub', $sub)
            ->getResult();

        $revisions = [];
        foreach ($sequences as $seq) {
            $num = $seq->getNumber();
            $rev = $seq->getRevision();
            $revisions[$num] = isset($revisions[$num]) ? max($revisions[$num], $rev) : $rev;
        }

        $seqByAuthor = [];
        foreach ($sequences as $seq) {
            $u = $seq->getAuthor();
            $uid = $u->getId();
            if (!isset($seqByAuthor[$uid])) {
                $seqByAuthor[$uid] = [
                    'user' => $u,
                    'counts' => [
                        'latest' => 0,
                        'corrected' => 0
                    ]
                ];
            }

            $num = $seq->getNumber();
            $rev = $seq->getRevision();
            if ($revisions[$num] > $rev) {
                $seqByAuthor[$uid]['counts']['corrected']++;
            } else {
                $seqByAuthor[$uid]['counts']['latest']++;
            }
        }

        $ep = $sub->getVersion()->getEpisode();
        $show = $ep->getShow();
        return $twig->render($response, 'hammer.twig', [
            'subtitle' => $sub,
            'episode' => $ep,
            'show' => $show,
            'seq_by_author' => $seqByAuthor
        ]);
    }

    public function doHammer($subId, $request, $response, EntityManager $em, Twig $twig, Translation $translation)
    {
        $body = $request->getParsedBody();
        $sub = $em->getRepository('App:Subtitle')->find($subId);
        if (!$sub) {
            throw new \Slim\Exception\HttpNotFoundException($request);
        }

        $target = (int)($body['user'] ?? 0);
        if (!$target) {
            return $response->withStatus(400);
        }

        $type = $body['type'] ?? '';
        if (!$type || !in_array($type, ['complete', 'latest'])) {
            return $response->withStatus(400);
        }

        $sequences = $em->createQuery('SELECT sq FROM App:User u JOIN App:Sequence sq WHERE sq.author = u AND sq.subtitle = :sub ORDER BY sq.number ASC, sq.revision DESC')
            ->setParameter('sub', $sub)
            ->getResult();

        $revisions = [];
        foreach ($sequences as $seq) {
            $num = $seq->getNumber();
            $rev = $seq->getRevision();
            $revisions[$num] = isset($revisions[$num]) ? max($revisions[$num], $rev) : $rev;
        }

        // Begin actual deletion
        foreach ($sequences as $sq) {
            if ($sq->getAuthor()->getId() != $target) {
                continue;
            }

            $num = $sq->getNumber();
            $rev = $sq->getRevision();
            $delete = false;
            if ($revisions[$num] > $rev && $type == 'complete') {
                // Not latest revision - Only delete if we're deleting complete
                $em->createQuery('UPDATE App:Sequence sq SET sq.revision = sq.revision - 1 WHERE sq.number = :num AND sq.revision >= :rev AND sq.subtitle = :sub')
                    ->setParameter('sub', $sub)
                    ->setParameter('num', $sq->getNumber())
                    ->setParameter('rev', $sq->getRevision())
                    ->execute();

                $delete = true;
            } elseif ($revisions[$num] == $rev) {
                // Latest revision, delete always
                $delete = true;
            }

            if ($delete) {
                $translation->broadcastDeleteSequence($sub, $sq->getId()); // Broadcast the deletion
                $em->remove($sq); // Remove it from the database
                --$revisions[$num]; // Reduce revision count by one on this sequence
            }
        }

        // Apply these changes so we can recalculate the proper percentage right after
        $em->flush();

        $baseSubId = $translation->getBaseSubId($sub);
        $translation->recalculateSubtitleProgress($baseSubId, $sub);
        return $response;
    }

    public function editProperties($subId, $request, $response, EntityManager $em, Twig $twig)
    {
        $sub = $em->getRepository('App:Subtitle')->find($subId);
        if (!$sub) {
            throw new \Slim\Exception\HttpNotFoundException($request);
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

    public function saveProperties($subId, $request, $response, EntityManager $em, Twig $twig, Auth $auth, UrlHelper $urlHelper)
    {
        $sub = $em->getRepository('App:Subtitle')->find($subId);
        if (!$sub) {
            throw new \Slim\Exception\HttpNotFoundException($request);
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
        } elseif (!v::length(1, 150)->validate($vcomment)) {
            $errors[] = 'Los comentarios no pueden superar los 150 caracteres';
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

        return $urlHelper->responseWithRedirectToRoute('subtitle-edit', ['subId' => $subId]);
    }
}
