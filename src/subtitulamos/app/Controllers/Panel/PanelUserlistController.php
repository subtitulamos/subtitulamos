<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2021 subtitulamos.tv
 */

namespace App\Controllers\Panel;

use Doctrine\ORM\EntityManager;
use Slim\Views\Twig;

class PanelUserlistController
{
    public function view($response, Twig $twig, EntityManager $em)
    {
        $users = $em->createQuery('SELECT u.id, u.username FROM App:User u WHERE u.id > 0')->getResult();
        return $twig->render($response, 'panel/panel_userlist.twig', ['users' => $users]);
    }
}
