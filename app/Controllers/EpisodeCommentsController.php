<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017 subtitulamos.tv
 */

namespace App\Controllers;

use App\Entities\Episode;
use App\Entities\EpisodeComment;

use App\Services\Auth;
use Doctrine\ORM\EntityManager;
use Respect\Validation\Validator as v;

class EpisodeCommentsController
{
    public function create($epId, $request, $response, EntityManager $em, Auth $auth)
    {
        // Validate input first
        $text = $request->getParsedBodyParam('text', '');
        if (!v::stringType()->length(1, 600)->validate($text)) {
            $response->getBody()->write('Invalid text parameter value');
            return $response->withStatus(400);
        }

        // Verify episode exists
        $ep = $em->getRepository('App:Episode')->find($epId);

        if (!$ep) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        $comment = new EpisodeComment();
        $comment->setEpisode($ep);
        $comment->setText($text);
        $comment->setType(EpisodeComment::TYPE_GENERIC);
        $comment->setUser($auth->getUser());
        $comment->setPublishTime(new \DateTime());
        $comment->setEditTime(new \DateTime());
        $comment->setSoftDeleted(false);

        $em->persist($comment);
        $em->flush();

        $response->getBody()->write($comment->getId());
        return $response->withStatus(200);
    }

    public function list($epId, $response, EntityManager $em)
    {
        $comments = $em->createQuery('SELECT ec FROM App:EpisodeComment ec WHERE ec.episode = :id AND ec.softDeleted = 0')
                   ->setParameter('id', $epId)
                   ->getResult();
        return $response->withJson($comments);
    }

    public function delete($epId, $cId, $request, $response, EntityManager $em)
    {
        $comment = $em->getRepository('App:EpisodeComment')->find($cId);
        if (!$comment) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        if ($comment->getEpisode()->getId() != $epId) {
            $response->withStatus(400);
        }

        $comment->setSoftDeleted(true);
        $em->flush();

        return $response;
    }
}
