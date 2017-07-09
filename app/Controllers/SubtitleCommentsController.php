<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017 subtitulamos.tv
 */

namespace App\Controllers;

use Doctrine\ORM\EntityManager;
use Respect\Validation\Validator as v;

use App\Entities\Subtitle;
use App\Entities\SubtitleComment;
use App\Services\Auth;

class SubtitleCommentsController
{
    public function create($subId, $request, $response, EntityManager $em, Auth $auth)
    {
        // Validate input first
        $text = $request->getParsedBodyParam('text', '');
        if (!v::stringType()->length(1, 600)->validate($text)) {
            $response->getBody()->write("Invalid text parameter value");
            return $response->withStatus(400);
        }

        // Verify subtitle exists
        $sub = $em->getRepository("App:Subtitle")->find($subId);
        
        if (!$sub) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        $comment = new SubtitleComment();
        $comment->setSubtitle($sub);
        $comment->setText($text);
        $comment->setUser($auth->getUser());
        $comment->setPublishTime(new \DateTime());
        $comment->setEditTime(new \DateTime());
        
        $em->persist($comment);
        $em->flush();

        $response->getBody()->write($comment->getId());
        return $response->withStatus(200);
    }

    public function list($subId, $request, $response, EntityManager $em)
    {
        $comments = $em->getRepository("App:SubtitleComment")->findBy(["subtitle" => (int)$subId]);
        return $response->withJson($comments);
    }
}
