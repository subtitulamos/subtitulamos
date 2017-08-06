<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017 subtitulamos.tv
 */

// TODO: Handle zero width space (\U200B), special "", single "..." utf8 codepoint, etc

namespace App\Controllers;

use \Doctrine\ORM\EntityManager;
use \Slim\Views\Twig;
use App\Entities\Subtitle;
use App\Entities\Sequence;
use App\Entities\OpenLock;
use App\Services\Auth;
use App\Services\Langs;
use App\Services\Translation;

use Respect\Validation\Validator as v;

// TODO: Extract half this logic into a translation service
class TranslationController
{
    /**
     * Number of sequences that are displayed per page
     * @var int
     */
    const SEQUENCES_PER_PAGE = 20;

    public function newTranslation($request, $response, EntityManager $em, \Slim\Router $router)
    {
        $episodeID = $request->getParsedBodyParam('episode', 0);
        $langCode = $request->getParsedBodyParam('lang', 0);
        if (!Langs::existsCode($langCode)) {
            $response->getBody()->write("El idioma no es correcto");
            return $response->withStatus(400);
        }

        if (!v::numeric()->positive()->validate($episodeID)) {
            $response->getBody()->write("La versión no es correcta");
            return $response->withStatus(400);
        }

        $version = $em->createQuery("SELECT v FROM App:Version v WHERE v.episode = :epid ORDER BY v.id ASC")
            ->setParameter("epid", (int)$episodeID)
            ->setMaxResults(1)
            ->getOneOrNullResult();

        if (!$version) {
            $response->getBody()->write("La versión no existe");
            return $response->withStatus(412);
        }

        $lang = Langs::getLangId($langCode);
        $base = null;
        foreach ($version->getSubtitles() as $sub) {
            if ($sub->isDirectUpload()) {
                $base = $sub;
            }

            if ($sub->getLang() == $lang) {
                // Lang already started! -- TODO: Cheap redirect, should not ever get to this page in the first place // could do this via ajax // add a link to body instead
                $response->getBody()->write("<meta http-equiv=\"refresh\" content=\"3;url=" . $router->pathFor("translation", ["id" => $sub->getId()]) . "\" />");
                $response->getBody()->write("Esta versión ya tiene este idioma abierto. Redirigiendo...");
                $response->withHeader('Refresh', 5);
                return $response->withStatus(412);
            }
        }

        // All good, create a new sub in the right lang
        $sub = new Subtitle();
        $sub->setLang($lang);
        $sub->setVersion($version);
        $sub->setProgress(0); // TODO: This progress could be more than 0% if sequences are autofilled
        $sub->setDirectUpload(false);
        $sub->setResync(false);
        $sub->setUploadTime(new \DateTime());
        $sub->setDownloads(0);

        // Autofill sequences
        $modBot = $em->getRepository("App:User")->find(-1);
        foreach ($base->getSequences() as $sequence) {
            if (Translation::containsCreditsText($sequence->getText())) {
                // Autoblock and replace with our credits
                $nseq = clone $sequence;
                $nseq->setSubtitle($sub);
                $nseq->setAuthor($modBot);
                $nseq->setText('www.subtitulamos.tv');
                $nseq->setLocked(true);
                $em->persist($nseq);
            } else {
                $blankSequence = Translation::getBlankSequenceConfidence($sequence);

                if ($blankSequence > 0) {
                    $nseq = clone $sequence;
                    $nseq->setAuthor($modBot);
                    $nseq->setSubtitle($sub);
                    $nseq->setText(' '); //Blank
                    $nseq->setLocked($blankSequence >= 95 ? 1 : 0); // Only lock if we're sure
                    $em->persist($nseq);
                }
            }
        }

        $em->persist($sub);
        $em->flush();

        return $response
            ->withStatus(200)
            ->withHeader('Location', $router->pathFor("translation", ["id" => $sub->getId()]));
    }

    public function view($id, $request, $response, EntityManager $em, Twig $twig)
    {
        $sub = $em->createQuery("SELECT s, v, e FROM App:Subtitle s JOIN s.version v JOIN v.episode e WHERE s.id = :id")
            ->setParameter("id", $id)
            ->getOneOrNullResult();

        if (!$sub) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        // Determine which secondary languages we can use
        $langRes = $em->createQuery("SELECT DISTINCT(s.lang) FROM App:Subtitle s WHERE s.version = :ver AND s.progress = 100")
            ->setParameter("ver", $sub->getVersion())
            ->getResult();

        $langs = [];
        foreach ($langRes as $lang) {
            $langs[] = $lang;
        }

        // Calculate sequence number for the main subtitle version
        $baseSub = $em->createQuery("SELECT sb FROM App:Subtitle sb WHERE sb.version = :v AND sb.directUpload = 1 ORDER BY sb.uploadTime DESC")
            ->setParameter('v', $sub->getVersion())
            ->setMaxResults(1)
            ->getOneOrNullResult();

        $seqCount = $em->createQuery("SELECT COUNT(s.id) FROM App:Sequence s WHERE s.subtitle = :sub")
            ->setParameter("sub", $baseSub)
            ->getSingleScalarResult();

        // Get a list of sequence authors
        $authors = $em->createQuery("SELECT DISTINCT u.id, u.username FROM App:Sequence s JOIN s.author u WHERE s.subtitle = :sub")
            ->setParameter("sub", $sub)
            ->getResult();

        return $twig->render($response, 'translate.twig', [
            'sub' => $sub,
            'avail_secondary_langs' => json_encode($langs),
            'episode' => $sub->getVersion()->getEpisode(),
            'page_count' => ceil($seqCount / self::SEQUENCES_PER_PAGE),
            'sub_lang' => Langs::getLocalizedName(Langs::getLangCode($sub->getLang())),
            'authors' => $authors
        ]);
    }

    public function listOpenLocks($id, $request, $response, EntityManager $em)
    {
        $openLocks = $em->createQuery("SELECT ol FROM App:OpenLock ol JOIN ol.user u WHERE ol.subtitle = :sub ORDER BY ol.sequenceNumber ASC")
            ->setParameter("sub", $id)
            ->getResult();

        $r = [];
        foreach ($openLocks as $openLock) {
            $r[] = [
                'id' => $openLock->getId(),
                'user' => [
                    'id' => $openLock->getUser()->getId(),
                    'username' => $openLock->getUser()->getUsername()
                ],
                'time' => $openLock->getGrantTime()->format('d/M H:i'),
                'seq_number' => $openLock->getSequenceNumber()
            ];
        }

        return $response->withJSON($r);
    }

    public function releaseLock($id, $lockId, $request, $response, EntityManager $em)
    {
        $openLock = $em->getRepository("App:OpenLock")->find($lockId);
        if (!$openLock) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        if ($openLock->getSubtitle()->getId() != $id) {
            return $response->withStatus(400);
        }

        $em->remove($openLock);
        $em->flush();

        return $response->withStatus(200);
    }

    public function listSequences($id, $page, $request, $response, EntityManager $em)
    {
        $secondaryLang = $request->getQueryParam("secondaryLang", 0);
        $untranslatedFilter = $request->getQueryParam('untranslated', 'false') == 'true';
        $page = max((int)$page, 1);
        $firstNum = ($page - 1) * self::SEQUENCES_PER_PAGE + 1;
        $sequences = [];

        $sub = $em->getRepository("App:Subtitle")->find($id);
        if (!$sub) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        $qb = $em->createQueryBuilder();
        $qb->select('sq')
            ->from('App:Sequence', 'sq')
            ->join('sq.author', 'u')
            ->where('sq.subtitle = :id')
            ->setParameter('id', $id);

        if ($request->getQueryParam("textFilter")) {
            $filtered = true;

            $textFilter = $request->getQueryParam("textFilter");
            $textFilter = "%" . str_replace("%", "", trim($textFilter)) . "%";

            $qb->andWhere('sq.text LIKE :tx')
                ->setParameter('tx', $textFilter);
        }

        if ($request->getQueryParam("authorFilter")) {
            $filtered = true;

            $qb->andWhere('sq.author = :author')
                ->setParameter('author', $request->getQueryParam("authorFilter"));
        }

        if (!isset($filtered) || $filtered == false) {
            $qb->andWhere("sq.number >= :first")
                ->andWhere("sq.number < :last")
                ->setParameter("first", $firstNum)
                ->setParameter("last", $firstNum + self::SEQUENCES_PER_PAGE);
        } else {
            $snumbers = [];
        }

        $qb->addOrderBy('sq.number', 'ASC')
            ->addOrderBy('sq.revision', 'DESC');

        $seqList = $qb->getQuery()->getResult();
        foreach ($seqList as $seq) {
            $snum = $seq->getNumber();

            if (isset($snumbers)) {
                $snumbers[] = $snum;
            }

            if (!isset($sequences[$snum])) {
                $sequences[$snum] = $seq->jsonSerialize();
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

        if ($secondaryLang > 0) {
            // Also load stuff from the base lang
            $secondarySub = $em->createQuery("SELECT sb FROM App:Subtitle sb WHERE sb.progress = 100 AND sb.version = :ver AND sb.lang = :lang")
                ->setParameter("lang", $secondaryLang)
                ->setParameter("ver", $sub->getVersion())
                ->getResult();

            if (!isset($snumbers)) {
                $snumbers = [];
                for ($i = $firstNum; $i < $firstNum + self::SEQUENCES_PER_PAGE; ++$i) {
                    $snumbers[] = $i;
                }
            }

            $altSeqList = $em->createQuery("SELECT sq FROM App:Sequence sq WHERE sq.subtitle = :ssub AND sq.number IN (:snumbers) ORDER BY sq.id ASC")
                ->setParameter("ssub", $secondarySub)
                ->setParameter("snumbers", $snumbers)
                ->getResult();

            // Now we have to *filter* out old revisions, since we only care about the text in the latest revision.
            // This is actually hard to do in SQL and it requires some tricks, so we do in code instead.
            $altSeqs = [];
            foreach ($altSeqList as $altSeq) {
                // because they're oredered by revision, this will always overwrite the old version
                $altSeqs[$altSeq->getNumber()] = $altSeq;
            }

            // Now, apply this alt language information to the main sequence list, filling in
            // either secondary text or the entire sequence if it doesn't exist.
            foreach ($altSeqs as $altSeq) {
                $snum = $altSeq->getNumber();

                if ($untranslatedFilter && isset($sequences[$snum])) {
                    unset($sequences[$snum]);
                } else {
                    if (!isset($sequences[$snum])) {
                        $temp = new Sequence(); // not intended to persist
                        $temp->setNumber($snum);
                        $temp->setStartTime($altSeq->getStartTime());
                        $temp->setEndTime($altSeq->getEndTime());
                        $temp->setText('');

                        $sequences[$snum] = $temp->jsonSerialize();
                    }

                    $sequences[$snum]['secondary_text'] = $altSeq->getText();
                }
            }
        }

        foreach ($sequences as &$seq) {
            if (isset($seq['history'])) {
                $seq['history'] = array_reverse($seq['history']);
            }
        }

        return $response->withJSON($sequences);
    }

    public function open($id, $request, $response, EntityManager $em, Auth $auth)
    {
        $seqNum = $request->getParsedBodyParam('seqNum', 0);

        if (!$seqNum) {
            return $response->withStatus(400);
        }

        $sub = $em->getRepository("App:Subtitle")->find($id);
        if (!$sub) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        $sequence = $em->createQuery("SELECT sq FROM App:Sequence sq WHERE sq.subtitle = :sub AND sq.number = :num ORDER BY sq.revision DESC")
            ->setParameter('sub', $id)
            ->setParameter('num', $seqNum)
            ->setMaxResults(1)
            ->getOneOrNullResult();

        $oLock = $em->createQuery("SELECT ol, u FROM App:OpenLock ol JOIN ol.user u WHERE ol.subtitle = :sub AND ol.sequenceNumber = :num")
            ->setParameter('sub', $id)
            ->setParameter('num', $seqNum)
            ->getOneOrNullResult();

        $res = ['ok' => true, 'text' => $sequence ? $sequence->getText() : null, 'id' => $sequence ? $sequence->getId() : null];
        if (!$oLock) {
            // Cool, let's create a lock
            $oLock = new OpenLock();
            $oLock->setSubtitle($sub);
            $oLock->setSequenceNumber($seqNum);
            $oLock->setUser($auth->getUser());
            $oLock->setGrantTime(new \DateTime());
            $em->persist($oLock);
            $em->flush();
        } elseif ($oLock->getUser()->getId() != $auth->getUser()->getId()) {
            // Sequence already open!
            $res['ok'] = false;
            $res['msg'] = sprintf("El usuario %s está editando esta secuencia (#%d)", $oLock->getUser()->getUsername(), $seqNum);
        }

        return $response->withJSON($res);
    }

    public function close($id, $request, $response, EntityManager $em, Auth $auth)
    {
        $seqNum = $request->getParsedBodyParam('seqNum', 0);

        if (!$seqNum) {
            return $response->withStatus(400);
        }

        $oLock = $em->createQuery("SELECT ol FROM App:OpenLock ol WHERE ol.subtitle = :sub AND ol.sequenceNumber = :num")
            ->setParameter('sub', $id)
            ->setParameter('num', $seqNum)
            ->getOneOrNullResult();

        if ($oLock) {
            $em->remove($oLock);
            $em->flush();
        }

        return $response->withStatus(200);
    }

    public function save($id, $request, $response, EntityManager $em, Auth $auth)
    {
        $seqID = $request->getParsedBodyParam('seqID', 0);
        $text = Translation::cleanText($request->getParsedBodyParam('text', ""), $auth->hasRole('ROLE_TH'));

        $seq = $em->getRepository("App:Sequence")->find($seqID);
        if (!$seq) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        if ($seq->getSubtitle()->getId() != $id) {
            return $response->withStatus(400);
        }

        if ($text == $seq->getText()) {
            // Nothing to change here, send the id of this very sequence
            $response->getBody()->write($seq->getId());
            return $response->withStatus(200);
        }

        if ($seq->getLocked() && !$auth->hasRole('ROLE_TH')) {
            return $response->withStatus(403);
        }

        // Update last edition time of parent sub
        $seq->getSubtitle()->setEditTime(new \DateTime());
        $seq->getSubtitle()->setLastEditedBy($auth->getUser());

        // Find an open lock on this sequence and clear it
        $oLock = $em->createQuery("SELECT ol FROM App:OpenLock ol WHERE ol.subtitle = :sub AND ol.sequenceNumber = :num")
            ->setParameter('sub', $seq->getSubtitle())
            ->setParameter('num', $seq->getNumber())
            ->getOneOrNullResult();

        if ($oLock) {
            $em->remove($oLock);
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

    /**
     * Handles the creation of a new translation for a given sequence
     */
    public function create($id, $request, $response, EntityManager $em, Auth $auth)
    {
        $seqNum = $request->getParsedBodyParam('number', 0);
        $text = Translation::cleanText($request->getParsedBodyParam('text', ""), $auth->hasRole('ROLE_TH'));

        $seq = $em->createQuery("SELECT COUNT(sq.id) FROM App:Sequence sq WHERE sq.subtitle = :sub AND sq.number = :num")
            ->setParameter('sub', $id)
            ->setParameter('num', $seqNum)
            ->getSingleScalarResult();

        if ($seq != 0) {
            // A sequence for this number already exists, but it shouldnt
            return $response->withStatus(403);
        }

        // Find the original sequence, and create one lookalike
        $curSub = $em->getRepository("App:Subtitle")->find($id);
        if (!$curSub) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        $baseSubId = $em->createQuery("SELECT sb.id FROM App:Subtitle sb WHERE sb.version = :v AND sb.directUpload = 1")
            ->setParameter('v', $curSub->getVersion())
            ->getSingleScalarResult();

        $baseSeq = $em->createQuery("SELECT sq FROM App:Sequence sq WHERE sq.subtitle = :sub AND sq.number = :num ORDER BY sq.revision DESC")
            ->setParameter("sub", $baseSubId)
            ->setParameter("num", $seqNum)
            ->setMaxResults(1)
            ->getOneOrNullResult();

        if (!$baseSeq) {
            // TODO: Log
            return $response->withStatus(500);
        }

        // Update last edition time of parent sub
        $curSub->setEditTime(new \DateTime());
        $curSub->setLastEditedBy($auth->getUser());

        // Find an open lock on this sequence and clear it
        $oLock = $em->createQuery("SELECT ol FROM App:OpenLock ol WHERE ol.subtitle = :sub AND ol.sequenceNumber = :num")
            ->setParameter('sub', $curSub)
            ->setParameter('num', $baseSeq->getNumber())
            ->getOneOrNullResult();

        if ($oLock) {
            $em->remove($oLock);
        }

        // Create new sequence
        $seq = new Sequence();
        $seq->setSubtitle($curSub);
        $seq->setNumber($baseSeq->getNumber());
        $seq->setRevision(0);
        $seq->setAuthor($auth->getUser());
        $seq->setStartTime($baseSeq->getStartTime());
        $seq->setEndTime($baseSeq->getEndTime());
        $seq->setText($text);
        $seq->setLocked(false);
        $seq->setVerified(false);
        $em->persist($seq);

        // Update progress
        $baseSubSeqCount = $em->createQuery("SELECT COUNT(DISTINCT sq.number) FROM App:Sequence sq WHERE sq.subtitle = :sub")
            ->setParameter('sub', $baseSubId)
            ->getSingleScalarResult();

        $ourSubSeqCount = $em->createQuery("SELECT COUNT(DISTINCT sq.number) FROM App:Sequence sq WHERE sq.subtitle = :sub")
            ->setParameter('sub', $curSub->getId())
            ->getSingleScalarResult();

        $ourSubSeqCount++; // Since this one we just translated wasn't persisted yet (flush not called)
        $curSub->setProgress($ourSubSeqCount / $baseSubSeqCount * 100);
        if ($curSub->getProgress() == 100) {
            // We're done! Save our progress
            $curSub->setCompleteTime(new \DateTime());
        }

        $em->persist($curSub);

        // Flush and end
        $em->flush();
        $response->getBody()->write($seq->getId());
        return $response->withStatus(200);
    }

    /**
     * Handles the toggling of the locked status on a given sequence
     */
    public function lockToggle($id, $request, $response, EntityManager $em)
    {
        $seqID = $request->getParsedBodyParam('seqID', 0);
        $seq = $em->getRepository("App:Sequence")->find($seqID);
        if (!$seq) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        $seq->setLocked(!$seq->getLocked());
        $em->persist($seq);
        $em->flush();

        return $response->withStatus(200);
    }

    /**
     * processText normalizes the text into a less modern
     * variant with more widespread support by players
     */
}
