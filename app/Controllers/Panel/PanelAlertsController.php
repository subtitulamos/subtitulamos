<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017-2018 subtitulamos.tv
 */

namespace App\Controllers\Panel;

use App\Entities\Alert;
use App\Entities\AlertComment;
use App\Services\Auth;
use Doctrine\ORM\EntityManager;
use Respect\Validation\Validator as v;
use Slim\Router;
use Slim\Views\Twig;

class PanelAlertsController
{
    public function view($request, $response, Twig $twig, EntityManager $em)
    {
        $alerts = [];

        $alertsCursor = $em->createQuery('SELECT a, ac FROM App:Alert a JOIN a.comments ac ORDER BY a.status ASC, a.creationTime DESC, ac.creationTime ASC')->getResult();

        foreach ($alertsCursor as $alert) {
            $comments = $alert->getComments();

            $alerts[] = [
                'id' => $alert->getId(),
                'closed' => $alert->getStatus() == 1,
                'from_user' => $alert->getByUser(),
                'from_sub' => $alert->getSubtitle()->getVersion()->getEpisode()->getFullName(),
                'from_sub_id' => $alert->getSubtitle()->getId(),
                'first_comment' => $comments[0],
                'comments' => $comments,
                'creation_time' => $alert->getCreationTime()
            ];
        }

        return $twig->render($response, 'panel/panel_alerts.twig', [
            'alerts' => $alerts
        ]);
    }

    public function saveComment($request, $response, Twig $twig, EntityManager $em, Auth $auth, Router $router)
    {
        $alertId = (int)$request->getParam('alert-id');
        $text = trim(strip_tags($request->getParam('comment')));
        $isClose = $request->getParam('close', false) !== false;

        if (!v::notEmpty()->validate($text)) {
            $errors[] = 'El comentario no puede estar vacío';
        }

        if ($alertId) {
            $alert = $em->getRepository('App:Alert')->find($alertId);
            if (!$alert) {
                $errors[] = 'La alerta que referencias no existe';
            }
        } else {
            $errors[] = 'La ID de alerta es incorrecta';
        }

        if (empty($errors)) {
            // Create new alert comment
            $comment = new AlertComment();
            $comment->setAlert($alert);
            $comment->setText($text);
            $comment->setCreationTime(new \DateTime());
            $comment->setType($isClose ? 2 : 1);
            $comment->setUser($auth->getUser());
            $em->persist($comment);

            if ($isClose) {
                $alert->setStatus(1);
            }

            $em->flush();

            $flashMsg = $isClose ? 'Alerta cerrada' : 'Comentario guardado correctamente';
            $auth->addFlash('success', $flashMsg);
        } else {
            foreach ($errors as $error) {
                $auth->addFlash('error', $error);
            }
        }

        return $response->withHeader('Location', $router->pathFor('alerts'));
    }
}
