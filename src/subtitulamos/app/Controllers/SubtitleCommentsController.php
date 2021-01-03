<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2021 subtitulamos.tv
 */

namespace App\Controllers;

use App\Entities\EventLog;
use App\Entities\SubtitleComment;

use App\Services\Auth;
use App\Services\Langs;
use App\Services\Translation;
use App\Services\Utils;
use DateTime;
use Doctrine\ORM\EntityManager;
use Respect\Validation\Validator as v;

class SubtitleCommentsController
{
    public function create($subId, $request, $response, EntityManager $em, Auth $auth, Translation $translation)
    {
        $body = $request->getParsedBody();
        // Validate input first
        $text = $body['text'] ?? '';
        if (!v::stringType()->length(1, 600)->validate($text)) {
            $response->getBody()->write('El comentario debe tener entre 1 y 600 caracteres');
            return $response->withStatus(400);
        }

        // Verify subtitle exists
        $sub = $em->getRepository('App:Subtitle')->find($subId);

        if (!$sub) {
            throw new \Slim\Exception\HttpNotFoundException($request);
        }

        $comment = new SubtitleComment();
        $comment->setSubtitle($sub);
        $comment->setText($text);
        $comment->setUser($auth->getUser());
        $comment->setPublishTime(new \DateTime());
        $comment->setEditTime(new \DateTime());
        $comment->setSoftDeleted(false);
        $comment->setPinned(false);

        $em->persist($comment);
        $em->flush();

        $translation->broadcastNewComment($comment);
        $response->getBody()->write((string)$comment->getId());
        return $response->withStatus(200);
    }

    public function list($subId, $response, EntityManager $em)
    {
        $comments = $em->createQuery('SELECT sc FROM App:SubtitleComment sc WHERE sc.subtitle = :id AND sc.softDeleted = 0 ORDER BY sc.pinned DESC, sc.id DESC')
            ->setParameter('id', $subId)
            ->getResult();

        return Utils::jsonResponse($response, $comments);
    }

    public function listAll($request, $response, EntityManager $em)
    {
        $params = $request->getQueryParams();
        $resultsPerPage = max(1, (int)($params['count'] ?? 20));
        $from = max((int)($params['from'] ?? 0), 0);

        $commentQuery = $em->createQuery('SELECT sc, sb, v, e FROM App:SubtitleComment sc JOIN sc.subtitle as sb JOIN sb.version as v JOIN v.episode as e WHERE sc.softDeleted = 0 ORDER BY sc.id DESC')
            ->setMaxResults($resultsPerPage)
            ->setFirstResult($from)
            ->getResult();

        $comments = [];
        foreach ($commentQuery as $commentInfo) {
            $lang = Langs::getLocalizedName(Langs::getLangCode($commentInfo->getSubtitle()->getLang()));

            $comment = \json_decode(\json_encode($commentInfo), true);
            $comment['subtitle'] = [
                'id' => $commentInfo->getSubtitle()->getId(),
                'name' => $commentInfo->getSubtitle()->getVersion()->getEpisode()->getFullName().' ['.$lang.']'
            ];

            $comments[] = $comment;
        }

        return Utils::jsonResponse($response, $comments);
    }

    public function edit($cId, $request, $response, EntityManager $em, Translation $translation, Auth $auth)
    {
        $comment = $em->getRepository('App:SubtitleComment')->find($cId);
        if (!$comment) {
            throw new \Slim\Exception\HttpNotFoundException($request);
        }

        $isMod = $auth->hasRole('ROLE_MOD');
        if (!$isMod) {
            // Regular users can only edit the comment if it's recent enough (do MAX_USER_EDIT_SECONDS + gracetime)
            $now = new DateTime();
            if ($now->getTimestamp() - $comment->getPublishTime()->getTimestamp() > MAX_USER_EDIT_SECONDS * 3/2) {
                return $response->withStatus(403);
            }
        }

        $body = $request->getParsedBody();
        // Validate input first
        $text = $body['text'] ?? '';
        if (!v::stringType()->length(1, 600)->validate($text)) {
            $response->getBody()->write('El comentario debe tener entre 1 y 600 caracteres');
            return $response->withStatus(400);
        }

        $comment->setText($text);
        $comment->setEditTime(new \DateTime());

        $event = new EventLog($auth->getUser(), new \DateTime(), sprintf('Comentario editado en el subtítulo [[subtitle:%d]]', $comment->getSubtitle()->getId()));
        $em->persist($event);
        $em->flush();

        $translation->broadcastEditComment($comment);
        return $response->withStatus(200);
    }

    public function delete($subId, $cId, $request, $response, EntityManager $em, Translation $translation, Auth $auth)
    {
        $comment = $em->getRepository('App:SubtitleComment')->find($cId);
        if (!$comment) {
            throw new \Slim\Exception\HttpNotFoundException($request);
        }

        if ($comment->getSubtitle()->getId() != $subId) {
            $response->withStatus(400);
        }

        $isMod = $auth->hasRole('ROLE_MOD');
        if (!$isMod) {
            // Regular users can only edit the comment if it's recent enough (do MAX_USER_EDIT_SECONDS + gracetime)
            $now = new DateTime();
            if ($now->getTimestamp() - $comment->getPublishTime()->getTimestamp() > MAX_USER_EDIT_SECONDS * 3/2) {
                return $response->withStatus(403);
            }
        }

        $comment->setSoftDeleted(true);

        $event = new EventLog($auth->getUser(), new \DateTime(), sprintf('Comentario borrado en el subtítulo [[subtitle:%d]]', $comment->getSubtitle()->getId()));
        $em->persist($event);
        $em->flush();

        $translation->broadcastDeleteComment($comment);
        return $response;
    }

    public function togglePin($subId, $cId, $request, $response, EntityManager $em, Translation $translation)
    {
        $comment = $em->getRepository('App:SubtitleComment')->find($cId);
        if (!$comment) {
            throw new \Slim\Exception\HttpNotFoundException($request);
        }

        if ($comment->getSubtitle()->getId() != $subId) {
            $response->withStatus(400);
        }

        $comment->setPinned(!$comment->getPinned());
        $em->flush();

        $translation->broadcastCommentPinChange($comment);
        return $response;
    }
}
