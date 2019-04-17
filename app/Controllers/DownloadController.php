<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017-2019 subtitulamos.tv
 */

namespace App\Controllers;

use App\Services\Auth;
use App\Services\Clock;

use App\Services\Langs;
use Doctrine\ORM\EntityManager;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class DownloadController
{
    public function download($id, RequestInterface $request, ResponseInterface $response, EntityManager $em, Auth $auth)
    {
        $sub = $em->getRepository('App:Subtitle')->find($id);
        if (!$sub) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        if ($sub->getProgress() < 100 && !$auth->hasRole('ROLE_TT')) {
            $response->getBody()->write('El subtítulo no ha sido completado todavía');
            return $response->withStatus(403);
        }

        if ($sub->getPause() && !$auth->hasRole('ROLE_TT')) {
            $response->getBody()->write('El subtítulo se encuentra bajo revisión');
            return $response->withStatus(403);
        }

        // Count the download first
        $sub->setDownloads($sub->getDownloads() + 1);
        $em->flush();

        // Grab the latest revision sequences
        $sequences = [];
        foreach ($sub->getSequences() as $seq) {
            if (!isset($sequences[$seq->getNumber()]) || $sequences[$seq->getNumber()]->getRevision() < $seq->getRevision()) {
                $sequences[$seq->getNumber()] = $seq;
            }
        }

        ksort($sequences);

        // Build the actual downloadable file
        $file = '';
        $sequenceNumber = 1;

        foreach ($sequences as $seq) {
            $file .= $sequenceNumber."\r\n";
            $file .= Clock::intToTimeStr($seq->getStartTime()).' --> '.Clock::intToTimeStr($seq->getEndTime())."\r\n";

            $text = str_replace("\n", "\r\n", str_replace("\r\r", "\r", $seq->getText()));
            /* TODO:allow user to configure a "utf8_download" preference */
            $text = utf8_decode($text);

            if (trim($text) == '') {
                $text = \str_repeat(' ', 3);
            }

            $file .= $text;
            if (mb_substr($text, mb_strlen($text) - 1) != "\n") {
                $file .= "\r\n"; // Add a linebreak if there's none in this last line
            }

            $file .= "\r\n";
            $sequenceNumber++;
        }

        $filename = $sub->getVersion()->getEpisode()->getFullName().'.'.$sub->getVersion()->getName().'.'.Langs::getLangCode($sub->getLang());
        $response = $response->withHeader('X-Robots-Tag', 'noindex');
        $response = $response->withHeader('Content-Type', 'text/srt');
        $response = $response->withHeader('Content-Disposition', sprintf('attachment; filename="%s.srt"', $filename));
        $response->getBody()->write($file);
        return $response;
    }
}
