<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017 subtitulamos.tv
 */

namespace App\Controllers;

use Doctrine\ORM\EntityManager;
use Respect\Validation\Validator as v;

use \Slim\Views\Twig;
use App\Services\Auth;
use App\Services\Langs;
use App\Entities\Subtitle;
use App\Entities\Pause;

class SubtitleController
{
    public function delete($subId, $request, $response, EntityManager $em, \Slim\Router $router)
    {
        $sub = $em->getRepository("App:Subtitle")->find($subId);
        if (!$sub) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        $epId = $sub->getVersion()->getEpisode()->getId();
        $em->remove($sub);
        $em->flush();

        return $response->withStatus(200)->withHeader('Location', $router->pathFor("episode", ["id" => $epId]));
    }

    public function pause($subId, $request, $response, EntityManager $em, \Slim\Router $router, Auth $auth)
    {
        $sub = $em->getRepository("App:Subtitle")->find($subId);
        if (!$sub) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }
        
        if ($sub->getPause()) {
            // Already paused!
            return $response->withStatus(200)->withHeader('Location', $router->pathFor("episode", ["id" => $epId]));
        }
        
        $pause = new Pause();
        $pause->setStart(new \DateTime());
        $pause->setSubtitle($sub);
        $pause->setUser($auth->getUser());
        $em->persist($pause);

        $sub->setPause($pause);
        $em->flush();

        return $response->withStatus(200)->withHeader('Location', $router->pathFor("episode", ["id" => $sub->getVersion()->getEpisode()->getId()]));
    }

    public function unpause($subId, $request, $response, EntityManager $em, \Slim\Router $router, Auth $auth)
    {
        $sub = $em->getRepository("App:Subtitle")->find($subId);
        if (!$sub) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }
        
        if (!$sub->getPause()) {
            // Not paused!
            return $response->withStatus(200)->withHeader('Location', $router->pathFor("episode", ["id" => $epId]));
        }
        
        $pause = $em->getRepository("App:Pause")->find($sub->getPause()->getId());
        $em->remove($pause);
        $sub->setPause(null);
        
        $em->flush();
        return $response->withStatus(200)->withHeader('Location', $router->pathFor("episode", ["id" => $sub->getVersion()->getEpisode()->getId()]));
    }

    public function viewHammer($subId, $request, $response, EntityManager $em, Twig $twig)
    {
        $sub = $em->getRepository("App:Subtitle")->find($subId);
        if (!$sub) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        $users = $em->createQuery("SELECT u, COUNT(u) FROM App:User u JOIN App:Sequence sq WHERE sq.author = u AND sq.subtitle = :sub GROUP BY u")
            ->setParameter('sub', $sub)
            ->getResult();
                
        return $twig->render($response, 'hammer.twig', [
            'subtitle' => $sub,
            'users' => $users
        ]);
    }

    public function doHammer($subId, $request, $response, EntityManager $em, Twig $twig)
    {
        $sub = $em->getRepository("App:Subtitle")->find($subId);
        if (!$sub) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        $target = (int)$request->getParsedBodyParam('user', 0);
        if (!$target) {
            return $response->withStatus(400);
        }

        $seqNums = $em->createQuery("SELECT sq.number, sq.revision FROM App:Sequence sq WHERE sq.author = :u AND sq.subtitle = :sub ORDER BY sq.revision DESC")
                      ->setParameter('sub', $sub)
                      ->setParameter('u', $target)
                      ->getResult();

        foreach ($seqNums as $sq) {
            $em->createQuery("UPDATE App:Sequence sq SET sq.revision = sq.revision - 1 WHERE sq.number = :num AND sq.revision >= :rev AND sq.subtitle = :sub")
            ->setParameter('sub', $sub)
            ->setParameter('num', $sq['number'])
            ->setParameter('rev', $sq['revision'])
            ->execute();

            $response->getBody()->write($sq['number'] . "-" . $sq['revision']."\n");
        }

        $em->createQuery("DELETE FROM App:Sequence sq WHERE sq.author = :u AND sq.subtitle = :sub")
           ->setParameter('sub', $sub)
           ->setParameter('u', $target)
           ->getResult();

        return $response;
    }
}
