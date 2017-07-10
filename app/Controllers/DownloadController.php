<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017 subtitulamos.tv
 */

namespace App\Controllers;

use \Psr\Http\Message\ResponseInterface;
use \Psr\Http\Message\RequestInterface;

use \Doctrine\ORM\EntityManager;
use \App\Services\Clock;

class DownloadController
{
    public function download($id, RequestInterface $request, ResponseInterface $response, EntityManager $em)
    {
        $sub = $em->getRepository("App:Subtitle")->find($id);
        if (!$sub) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        if ($sub->getProgress() < 100) {
            $response->getBody()->write("El subtítulo no ha sido completado todavía");
            return $response->withStatus(403);
        }

        // Grab the latest revision sequences
        $sequences = [];
        foreach ($sub->getSequences() as $seq) {
            if (!isset($sequences[$seq->getNumber()]) || $sequences[$seq->getNumber()]->getRevision() < $seq->getRevision()) {
                $sequences[$seq->getNumber()] = $seq;
            }
        }

        // Build the actual downloadable file
        $file = "";
        $sequenceNumber = 1;

        $response = $response->withHeader('Content-Type', 'text/srt');
        $response = $response->withHeader('Content-Disposition', sprintf("attachment; filename=\"%s.srt\"", $sub->getVersion()->getEpisode()->getFullName()));
        
        foreach ($sequences as $seq) {
            $file .= $sequenceNumber."\r\n";
            $file .= Clock::intToTimeStr($seq->getStartTime()) . " --> " . Clock::intToTimeStr($seq->getEndTime()) . "\r\n";
            
            $text = str_replace("\n", "\r\n", str_replace("\r\r", "\r", $seq->getText()));
            /* ^^^ TODO:
                if user has "utf8_download" preference unset then
                    sequence_text = utf8_decode(sequence_text);
            */

            $file .= $text;
            if (substr($text, strlen($text) - 1) != "\n") {
                $file .= "\r\n"; // Add a linebreak if there's none in this last line
            }

            $file .= "\r\n";
            $sequenceNumber++;
        }

        $response->getBody()->write($file);
        return $response;
    }
}
