<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

namespace App\Controllers;

use App\Entities\Alert;
use App\Entities\AlertComment;

use App\Services\Auth;
use Doctrine\ORM\EntityManager;
use Respect\Validation\Validator as v;

class AlertController
{
    public function subtitleAlert($subId, $request, $response, EntityManager $em, Auth $auth)
    {
        $sub = $em->getRepository('App:Subtitle')->find($subId);
        if (!$sub) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        if (!$auth->getUser()) {
            return $response->withJSON([
                'ok' => false,
                'msg' => 'Por favor, recarga la página e intentálo de nuevo'
            ]);
        }

        $msg = trim(strip_tags($request->getParam('message', '')));
        $res = ['ok' => true];
        if (v::notEmpty()->validate($msg)) {
            $alert = new Alert();
            $alert->setByUser($auth->getUser());
            $alert->setCreationTime(new \DateTime());
            $alert->setStatus(0);
            $alert->setSubtitle($sub);

            $com = new AlertComment();
            $com->setUser($auth->getUser());
            $com->setType(0);
            $com->setCreationTime(new \DateTime());
            $com->setAlert($alert);
            $com->setText($msg);

            $em->persist($alert);
            $em->persist($com);
            $em->flush();
        } else {
            $res['ok'] = false;
            $res['msg'] = 'Por favor, detalla la situación';
        }

        return $response->withJSON($res);
    }
}
