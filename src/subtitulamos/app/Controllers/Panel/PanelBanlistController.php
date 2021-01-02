<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

namespace App\Controllers\Panel;

use Doctrine\ORM\EntityManager;
use Slim\Views\Twig;

class PanelBanlistController
{
    private function fetchBannedUsersByDate($em, $maxDate, $minDate)
    {
        $bans = $em->createQuery('SELECT ban FROM App:User u JOIN App:Ban ban WHERE ban = u.ban AND ban.until > :min_date AND ban.until < :max_date ORDER BY u.username ASC')
            ->setParameter('min_date', $minDate)
            ->setParameter('max_date', $maxDate)
            ->getResult();

        $banned_users = [];
        foreach ($bans as $ban) {
            $banned_users[] = [
                'by' => $ban->getByUser(),
                'target' => $ban->getTargetUser(),
                'until' => $ban->getUntil(),
                'reason' => $ban->getReason()
            ];
        }

        return $banned_users;
    }

    private function fetchOldBans($em)
    {
        $bans = $em->createQuery('SELECT ban FROM App:User u JOIN App:Ban ban WHERE u.id = ban.targetUser AND (ban.until < :right_now OR ban.unbanUser IS NOT NULL) ORDER BY u.username ASC')
            ->setParameter('right_now', new \DateTime())
            ->getResult();

        $banned_users = [];
        foreach ($bans as $ban) {
            $banned_users[] = [
                'by' => $ban->getByUser(),
                'lifter' => $ban->getUnbanUser(),
                'target' => $ban->getTargetUser(),
                'until' => $ban->getUntil(),
                'reason' => $ban->getReason()
            ];
        }

        return $banned_users;
    }

    public function view($request, $response, EntityManager $em, Twig $twig)
    {
        $banned_users = [
            'temporary' => $this->fetchBannedUsersByDate($em, new \DateTime('2037-01-01'), new \DateTime()),
            'permanent' => $this->fetchBannedUsersByDate($em, new \DateTime('3000-01-01'), new \DateTime('2037-01-01')),
            'old' => $this->fetchOldBans($em)
        ];

        return $twig->render($response, 'panel/panel_banlist.twig', [
            'banned_users' => $banned_users
        ]);
    }
}
