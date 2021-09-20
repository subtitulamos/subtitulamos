<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2021 subtitulamos.tv
 */

namespace App\Controllers;

use App\Entities\EpisodeComment;
use App\Entities\EventLog;
use App\Services\Auth;
use App\Services\Utils;
use DateTime;
use Doctrine\ORM\EntityManager;
use Psr\Http\Message\ServerRequestInterface;
use Respect\Validation\Validator as v;

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

        $recentCommentsByUser = $em->createQuery('SELECT ec FROM App:EpisodeComment ec WHERE ec.softDeleted = 0 AND ec.user = :user AND ec.publishTime >= :date_p')
            ->setParameter('user', $auth->getUser())
            ->setParameter('date_p', (new \DateTime())->modify("-3 minutes"))
            ->getResult();

        $countByEpId = [];
        foreach($recentCommentsByUser as $comment) {
            $epId = $comment->getEpisode()->getId();
            $countByEpId[$epId] = isset($countByEpId[$epId]) ? $countByEpId[$epId] + 1 : 1;
            if($countByEpId[$epId] > 4) {
                $response->getBody()->write('Estás escribiendo comentarios muy rápido. Por favor, espera unos segundos y vuelve a intentarlo');
                return $response->withStatus(400);
            }
        }

        if(count($countByEpId) > 3 && !isset($countByEpId[$ep->getId()])) {
            $response->getBody()->write('Estás escribiendo comentarios muy rápido. Por favor, espera unos segundos y vuelve a intentarlo');
            return $response->withStatus(400);
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

        $response->getBody()->write((string)$comment->getId());
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
        $params = $request->getQueryParams();
        $resultsPerPage = max(1, (int)($params['count'] ?? 20));
        $from = max((int)($params['from'] ?? 0), 0);

        $commentQuery = $em->createQuery('SELECT ec FROM App:EpisodeComment ec WHERE ec.softDeleted = 0  ORDER BY ec.id DESC')
            ->setMaxResults($resultsPerPage)
            ->setFirstResult($from)
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

    public function edit($cId, $request, $response, EntityManager $em, Auth $auth)
    {
        $comment = $em->getRepository('App:EpisodeComment')->find($cId);
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
            return $response->withStatus(400);
        }

        $comment->setText($text);
        $comment->setEditTime(new \DateTime());

        $event = new EventLog($auth->getUser(), new \DateTime(), sprintf('Comentario de episodio editado ([[episode:%d]])', $comment->getEpisode()->getId()));
        $em->persist($event);

        $em->flush();

        return $response->withStatus(200);
    }

    public function delete($epId, $cId, $request, $response, EntityManager $em, Auth $auth)
    {
        $comment = $em->getRepository('App:EpisodeComment')->find($cId);
        if (!$comment) {
            throw new \Slim\Exception\HttpNotFoundException($request);
        }

        if ($comment->getEpisode()->getId() != $epId) {
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

        $event = new EventLog($auth->getUser(), new \DateTime(), sprintf('Comentario de episodio borrado ([[episode:%d]])', $comment->getEpisode()->getId()));
        $em->persist($event);

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
