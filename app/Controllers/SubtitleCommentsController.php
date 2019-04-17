<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017-2018 subtitulamos.tv
 */

namespace App\Controllers;

use App\Entities\Subtitle;
use App\Entities\SubtitleComment;

use App\Services\Auth;
use App\Services\Langs;
use App\Services\Translation;
use Doctrine\ORM\EntityManager;
use Respect\Validation\Validator as v;
use Slim\Views\Twig;

class SubtitleCommentsController
{
    public function create($subId, $request, $response, EntityManager $em, Auth $auth, Translation $translation)
    {
        // Validate input first
        $text = $request->getParsedBodyParam('text', '');
        if (!v::stringType()->length(1, 600)->validate($text)) {
            $response->getBody()->write('El comentario debe tener entre 1 y 600 caracteres');
            return $response->withStatus(400);
        }

        // Verify subtitle exists
        $sub = $em->getRepository('App:Subtitle')->find($subId);

        if (!$sub) {
            throw new \Slim\Exception\NotFoundException($request, $response);
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
        $response->getBody()->write($comment->getId());
        return $response->withStatus(200);
    }

    public function list($subId, $response, EntityManager $em)
    {
        $comments = $em->createQuery('SELECT sc FROM App:SubtitleComment sc WHERE sc.subtitle = :id AND sc.softDeleted = 0 ORDER BY sc.pinned DESC, sc.id DESC')
            ->setParameter('id', $subId)
            ->getResult();

        return $response->withJson($comments);
    }

    public function listAll($request, $response, EntityManager $em)
    {
        $resultsPerPage = 20;
        $page = max(1, (int)$request->getParam('page', 1));
        $commentQuery = $em->createQuery('SELECT sc, sb, v, e FROM App:SubtitleComment sc JOIN sc.subtitle as sb JOIN sb.version as v JOIN v.episode as e WHERE sc.softDeleted = 0 ORDER BY sc.id DESC')
            ->setMaxResults($resultsPerPage)
            ->setFirstResult(($page - 1) * $resultsPerPage)
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

        return $response->withJson($comments);
    }

    public function viewAll($response, EntityManager $em, Twig $twig)
    {
        return $twig->render($response, 'comment_list.twig', [
            'comment_type_name' => 'traducciones',
            'comment_type' => 'subtitles'
        ]);
    }

    public function delete($subId, $cId, $request, $response, EntityManager $em, Translation $translation)
    {
        $comment = $em->getRepository('App:SubtitleComment')->find($cId);
        if (!$comment) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        if ($comment->getSubtitle()->getId() != $subId) {
            $response->withStatus(400);
        }

        $comment->setSoftDeleted(true);
        $em->flush();

        $translation->broadcastDeleteComment($comment);
        return $response;
    }

    public function togglePin($subId, $cId, $request, $response, EntityManager $em, Translation $translation)
    {
        $comment = $em->getRepository('App:SubtitleComment')->find($cId);
        if (!$comment) {
            throw new \Slim\Exception\NotFoundException($request, $response);
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
