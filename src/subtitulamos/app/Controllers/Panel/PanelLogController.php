<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

namespace App\Controllers\Panel;

use App\Services\Langs;
use App\Services\UrlHelper;
use Doctrine\ORM\EntityManager;
use Slim\Views\Twig;

class PanelLogController
{
    public function view($response, Twig $twig, EntityManager $em, UrlHelper $urlHelper)
    {
        $logsRes = $em->createQuery('SELECT elog FROM App:EventLog elog ORDER BY elog.date DESC')->getResult();

        $logs = [];
        foreach ($logsRes as $log) {
            $data = $log->getData();

            $data = preg_replace_callback("/\[\[(\w+):(\w+)\]\]/", function ($m) use (&$urlHelper, &$em) {
                switch ($m[1]) {
                    case 'show':
                        $showId = (int)$m[2];
                        $show = $em->getRepository('App:Show')->find($showId);
                        if (!$show) {
                            return "Serie #$showId";
                        }

                        return "<a href='".$urlHelper->pathFor('show', ['showId' => $showId])."'>".$show->getName().'</a>';

                    case 'episode':
                        $epId = (int)$m[2];
                        $episode = $em->getRepository('App:Episode')->find($epId);
                        if (!$episode) {
                            return "Episodio #$epId";
                        }

                        return "<a href='".$urlHelper->pathFor('episode', ['id' => $epId])."'>".$episode->getFullName().'</a>';

                    case 'user':
                        $uId = (int)$m[2];
                        $user = $em->getRepository('App:User')->find($uId);
                        if (!$user) {
                            return "Usuario #$uId";
                        }

                        return "<a href='".$urlHelper->pathFor('user', ['userId' => $uId])."'>".$user->getUsername().'</a>';

                    case 'subtitle':
                        $subId = (int)$m[2];
                        $subtitle = $em->getRepository('App:Subtitle')->find($subId);
                        if (!$subtitle) {
                            return "Subtítulo #$subId";
                        }

                        return "<a href='".$urlHelper->pathFor('translation', ['id' => $subId])."'>".$subtitle->getVersion()->getEpisode()->getFullName().' > '.$subtitle->getVersion()->getName().' > '.Langs::getLocalizedName(Langs::getLangCode($subtitle->getLang())).'</a>';

                    default:
                        return $m[0];
                }
            }, $data);

            $logs[] = [
                'date' => $log->getDate(),
                'user' => $log->getUser(),
                'data' => $data
            ];
        }

        return $twig->render($response, 'panel/panel_logs.twig', ['logs' => $logs]);
    }
}
