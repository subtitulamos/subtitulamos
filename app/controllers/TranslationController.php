<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017 subtitulamos.tv
 */

// TODO: Handle zero width space (\U200B), special "", single "..." utf8 codepoint, etc

namespace App\Controllers;

use \Psr\Container\ContainerInterface;
use \Doctrine\ORM\EntityManager;
use \Slim\Views\Twig;
use App\Services\Auth;

class TranslationController
{
    /**
     * Number of sequences that are displayed per page
     * @var int
     */
    const SEQUENCES_PER_PAGE = 20;
    
    protected $container;

    // constructor receives container instance
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function view($id, $request, $response, EntityManager $em, Twig $twig)
    {
        $sub = $em->createQuery("SELECT s, v, e FROM App:Subtitle s JOIN s.version v JOIN v.episode e WHERE e.id = :id")
                   ->setParameter("id", $id)
                   ->getOneOrNullResult();

        if (empty($sub)) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }
        
        // Determine which secondary languages we can use
        $langRes = $em->createQuery("SELECT DISTINCT(s.lang) FROM App:Subtitle s WHERE s.version = :ver AND s.progress = 100")
                    ->setParameter("ver", $sub->getVersion())
                    ->getOneOrNullResult();
        
        $langs = [];
        foreach ($langRes as $lang) {
            $langs[] = $lang;
        }
        
        // Calculate sequence number
        $seqCount = $em->createQuery("SELECT COUNT(s.id) FROM App:Sequence s WHERE s.subtitle = :sub")
                       ->setParameter("sub", $sub)
                       ->getSingleScalarResult();

        return $twig->render($response, 'translate.twig', [
            'sub' => $sub,
            'avail_secondary_langs' => json_encode($langs),
            'episode' => $sub->getVersion()->getEpisode(),
            'page_count' => ceil($seqCount/self::SEQUENCES_PER_PAGE)
        ]);
    }

    public function listSequences($id, $page, $request, $response, EntityManager $em)
    {
        $secondaryLang = $request->getQueryParam("secondaryLang", 0);
        $page = max((int)$page, 1);
        $firstNum = ($page - 1) * self::SEQUENCES_PER_PAGE;

        $seqList = $em->createQuery("SELECT sq FROM App:Sequence sq JOIN App:User u WHERE sq.author = u AND sq.subtitle = :id AND sq.number >= :first AND sq.number <= :last ORDER BY sq.number ASC, sq.revision DESC")
                      ->setParameter("id", $id)
                      ->setParameter("first", $firstNum)
                      ->setParameter("last", $firstNum + self::SEQUENCES_PER_PAGE)
                      ->getResult();
        

        if ($secondaryLang > 0) {
            // Also load the sequence text from another
            $altSeqList = $em->createQuery("SELECT sq.number, sq.text, sq.revision FROM App:Sequence sq JOIN App:Subtitle sb WHERE sq.subtitle = sb AND sb.lang = :lang AND sq.number >= :first AND sq.number <= :last ORDER BY sq.id ASC")
                             ->setParameter("lang", $secondaryLang)
                             ->setParameter("first", $firstNum)
                             ->setParameter("last", $firstNum + self::SEQUENCES_PER_PAGE)
                             ->getResult();
            
            // Now we have to filter out old revisions, since we only care about the text in the latest revision.
            // This is actually hard to do in SQL and it requires some tricks, so we do in code instead.
            $altSeqs = [];
            foreach ($altSeqList as $altSeq) {
                $altSeqs[$altSeq['number']] = $altSeq['text']; // (this will always overwrite old revisions)
            }
        }

        $sequences = [];
        foreach ($seqList as $seq) {
            $snum = $seq->getNumber();

            if (!isset($sequences[$snum])) {
                $sequences[$snum] = json_decode(json_encode($seq), true);
                if (isset($altSeqs[$snum])) {
                    $sequences[$snum]['secondary_text'] = $altSeqs[$snum];
                }
            } else {
                // If sequence was already defined, then we're looking at its history
                if (!isset($sequences[$snum]['history'])) {
                    $sequences[$snum]['history'] = [];
                }

                $sequences[$snum]['history'][] = [
                    "id" => $seq->getId(),
                    "tstart" => $seq->getStartTime(),
                    "tend" => $seq->getEndTime(),
                    "text" => $seq->getText(),
                    "author" => [
                        "id" => $seq->getAuthor()->getId(),
                        "name" => $seq->getAuthor()->getUsername()
                    ],
                ];
            }
        }

        foreach ($sequences as &$seq) {
            if (isset($seq['history'])) {
                $seq['history'] = array_reverse($seq['history']);
            }
        }

        return $response->withJSON($sequences);
    }

    public function open($id, $request, $response, EntityManager $em)
    {
        $seqID = $request->getParsedBodyParam('seqID', 0);

        $res = ['ok' => true];
        // TODO: Implement

        return $response->withJSON($res);
    }

    public function save($id, $request, $response, EntityManager $em, Auth $auth)
    {
        $seqID = $request->getParsedBodyParam('seqID', 0);
        $text = trim($request->getParsedBodyParam('text', ""));
        
        if (empty($text)) {
            $response->getBody()->write("Text cannot be empty");
            return $response->withStatus(400);
        }

        // TODO: Better validate text (multiline etc) + multiline trim

        $seq = $em->getRepository("App:Sequence")->find($seqID);
        if (!$seq) {
            $response->getBody()->write("Sequence does not exist");
            return $response->withStatus(400);
        }

        if ($text == $seq->getText()) {
            // Nothing to change here, send the id of this very sequence
            $response->getBody()->write($seq->getId());
            return $response->withStatus(200);
        }

        // Generate a copy of this sequence, we don't edit the original
        $nseq = clone $seq;
        $nseq->incRevision();
        $nseq->setText($text);
        $nseq->setAuthor($auth->getUser());
        
        $em->persist($nseq);
        $em->flush();
        
        $response->getBody()->write($nseq->getId());
        return $response->withStatus(200);
    }

    public function lockToggle($id, $request, $response, EntityManager $em)
    {
        $seqID = $request->getParsedBodyParam('seqID', 0);
        $seq = $em->getRepository("App:Sequence")->find($seqID);
        if (!$seq) {
            $response->getBody()->write("Sequence does not exist");
            return $response->withStatus(400);
        }

        $seq->setLocked(!$seq->getLocked());
        $em->persist($seq);
        $em->flush();
        
        return $response->withStatus(200);
    }
}
