<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017 subtitulamos.tv
 */

namespace App\Controllers;

use Doctrine\ORM\EntityManager;
use Respect\Validation\Validator as v;

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
}
