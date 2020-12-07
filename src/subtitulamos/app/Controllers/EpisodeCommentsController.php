<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

namespace App\Controllers;

use App\Entities\EpisodeComment;
use App\Services\Auth;
use App\Services\Utils;
use Doctrine\ORM\EntityManager;
use Psr\Http\Message\ServerRequestInterface;
use Respect\Validation\Validator as v;
use Slim\Views\Twig;

class EpisodeCommentsController
{
    public function create($epId, ServerRequestInterface $request, $response, EntityManager $em, Auth $auth)
    {
        $body = $request->getParsedBody();
        // Validate input first
        $text = $body['text'] ?? '';
        if (!v::stringType()->length(1, 600)->validate($text)) {
            return $response->withStatus(400);
        }

        // Verify episode exists
        $ep = $em->getRepository('App:Episode')->find($epId);

        if (!$ep) {
            throw new \Slim\Exception\HttpNotFoundException($request);
        }

        $comment = new EpisodeComment();
        $comment->setEpisode($ep);
        $comment->setText($text);
        $comment->setType(EpisodeComment::TYPE_GENERIC);
        $comment->setUser($auth->getUser());
        $comment->setPublishTime(new \DateTime());
        $comment->setEditTime(new \DateTime());
        $comment->setSoftDeleted(false);
        $comment->setPinned(false);

        $em->persist($comment);
        $em->flush();

        $response->getBody()->write($comment->getId());
        return $response->withStatus(200);
    }

    public function list($epId, $response, EntityManager $em)
    {
        $comments = $em->createQuery('SELECT ec FROM App:EpisodeComment ec WHERE ec.episode = :id AND ec.softDeleted = 0 ORDER BY ec.pinned DESC, ec.id ASC')
                   ->setParameter('id', $epId)
                   ->getResult();
        return Utils::jsonResponse($response, $comments);
    }

    public function listAll($request, $response, EntityManager $em)
    {
        $resultsPerPage = 20;
        $page = max(1, (int)$request->getParam('page', 1));
        $commentQuery = $em->createQuery('SELECT ec FROM App:EpisodeComment ec WHERE ec.softDeleted = 0  ORDER BY ec.id DESC')
            ->setMaxResults($resultsPerPage)
            ->setFirstResult(($page - 1) * $resultsPerPage)
            ->getResult();

        $comments = [];
        foreach ($commentQuery as $commentInfo) {
            $comment = \json_decode(\json_encode($commentInfo), true);
            $comment['episode'] = [
                'id' => $commentInfo->getEpisode()->getId(),
                'name' => $commentInfo->getEpisode()->getFullName()
            ];

            $comments[] = $comment;
        }

        return Utils::jsonResponse($response, $comments);
    }

    public function viewAll($response, EntityManager $em, Twig $twig)
    {
        return $twig->render($response, 'comment_list.twig', [
            'comment_type_name' => 'episodios',
            'comment_type' => 'episodes'
        ]);
    }

    public function delete($epId, $cId, $request, $response, EntityManager $em)
    {
        $comment = $em->getRepository('App:EpisodeComment')->find($cId);
        if (!$comment) {
            throw new \Slim\Exception\HttpNotFoundException($request);
        }

        if ($comment->getEpisode()->getId() != $epId) {
            $response->withStatus(400);
        }

        $comment->setSoftDeleted(true);
        $em->flush();

        return $response;
    }

    public function togglePin($epId, $cId, $request, $response, EntityManager $em)
    {
        $comment = $em->getRepository('App:EpisodeComment')->find($cId);
        if (!$comment) {
            throw new \Slim\Exception\HttpNotFoundException($request);
        }

        if ($comment->getEpisode()->getId() != $epId) {
            $response->withStatus(400);
        }

        $comment->setPinned(!$comment->getPinned());
        $em->flush();

        return $response;
    }
}
