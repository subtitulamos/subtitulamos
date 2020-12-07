<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

namespace App\Controllers;

use App\Entities\OpenLock;
use App\Entities\Sequence;
use App\Entities\Subtitle;
use App\Services\Auth;
use App\Services\Langs;
use App\Services\Translation;
use App\Services\UrlHelper;
use App\Services\Utils;
use Doctrine\ORM\EntityManager;
use Psr\Http\Message\ServerRequestInterface;
use Respect\Validation\Validator as v;

use Slim\Views\Twig;

class TranslationController
{
    /**
     * Number of sequences that are displayed per page
     * @var int
     */
    const SEQUENCES_PER_PAGE = 20;

    public function newTranslation($request, $response, EntityManager $em, UrlHelper $urlHelper)
    {
        $body = $request->getParsedBody();
        $episodeID = $body['episode'] ?? 0;
        $langCode = $body['lang'] ?? 0;
        if (!Langs::existsCode($langCode)) {
            $response->getBody()->write('El idioma no es correcto');
            return $response->withStatus(400);
        }

        if (!v::numeric()->positive()->validate($episodeID)) {
            $response->getBody()->write('La versión no es correcta');
            return $response->withStatus(400);
        }

        $version = $em->createQuery('SELECT v FROM App:Version v WHERE v.episode = :epid ORDER BY v.id ASC')
            ->setParameter('epid', (int)$episodeID)
            ->setMaxResults(1)
            ->getOneOrNullResult();

        if (!$version) {
            $response->getBody()->write('La versión no existe');
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
                $response->getBody()->write('<meta http-equiv="refresh" content="3;url='.$urlHelper->pathFor('translation', ['id' => $sub->getId()]).'" />');
                $response->getBody()->write('Esta versión ya tiene este idioma abierto. Redirigiendo...');
                $response->withHeader('Refresh', 5);
                return $response->withStatus(412);
            }
        }

        // All good, create a new sub in the right lang
        $sub = new Subtitle();
        $sub->setLang($lang);
        $sub->setVersion($version);
        $sub->setDirectUpload(false);
        $sub->setResync(false);
        $sub->setUploadTime(new \DateTime());
        $sub->setDownloads(0);

        // Autofill sequences
        $modBot = $em->getRepository('App:User')->find(-1);
        $baseSequenceNumbers = [];
        $autofilledSeqCount = 0;
        foreach ($base->getSequences() as $sequence) {
            if (Translation::containsCreditsText($sequence->getText())) {
                // Autoblock and replace with our credits
                $nseq = clone $sequence;
                $nseq->setSubtitle($sub);
                $nseq->setAuthor($modBot);
                $nseq->setText('www.subtitulamos.tv');
                $nseq->setLocked(true);
                $em->persist($nseq);

                ++$autofilledSeqCount;
            } else {
                $blankConfidence = Translation::getBlankSequenceConfidence($sequence);

                if ($blankConfidence > 0) {
                    $nseq = clone $sequence;
                    $nseq->setAuthor($modBot);
                    $nseq->setSubtitle($sub);
                    $nseq->setText(' '); //Blank
                    $nseq->setLocked($blankConfidence >= 95 ? 1 : 0); // Only lock if we're sure
                    $em->persist($nseq);

                    ++$autofilledSeqCount;
                }

                $translationAttempt = Translation::getBasicSequenceTranslation($sequence, $langCode);
                $translationConfidence = $translationAttempt[0];
                $translatedText = $translationAttempt[1];

                if ($translatedText != '') {
                    $nseq = clone $sequence;
                    $nseq->setAuthor($modBot);
                    $nseq->setSubtitle($sub);
                    $nseq->setText($translatedText); //Blank
                    $nseq->setLocked($translationConfidence >= 95 ? 1 : 0); // Only lock if we're sure
                    $em->persist($nseq);

                    ++$autofilledSeqCount;
                }
            }

            $baseSequenceNumbers[$sequence->getNumber()] = true;
        }

        if ($autofilledSeqCount) {
            $sub->setProgress($autofilledSeqCount / count($baseSequenceNumbers) * 100);
        } else {
            $sub->setProgress(0);
        }

        $em->persist($sub);
        $em->flush();

        return $response
            ->withStatus(200)
            ->withHeader('Location', $urlHelper->pathFor('translation', ['id' => $sub->getId()]));
    }

    public function view($id, $request, $response, EntityManager $em, Twig $twig, Auth $auth, Translation $translation)
    {
        $sub = $em->createQuery('SELECT s, v, e FROM App:Subtitle s JOIN s.version v JOIN v.episode e WHERE s.id = :id')
            ->setParameter('id', $id)
            ->getOneOrNullResult();

        if (!$sub) {
            throw new \Slim\Exception\HttpNotFoundException($request);
        }

        // Determine which secondary languages we can use
        $langRes = $em->createQuery('SELECT DISTINCT(s.lang) as lang FROM App:Subtitle s WHERE s.version = :ver AND s.progress = 100')
            ->setParameter('ver', $sub->getVersion())
            ->getResult();

        $langs = [];
        foreach ($langRes as $langEntry) {
            $langs[] = $langEntry['lang'];
        }

        // Generate a token for the real time socket to use to authenticate, and broadcast ourselves
        $tok = Utils::generateRandomString(64);
        $translation->setWSAuthToken($tok, $sub);
        $translation->broadcastUserInfo($sub, $auth->getUser());

        return $twig->render($response, 'translate.twig', [
            'sub' => $sub,
            'avail_secondary_langs' => json_encode($langs),
            'episode' => $sub->getVersion()->getEpisode(),
            'sub_lang' => Langs::getLocalizedName(Langs::getLangCode($sub->getLang())),
            'wstok' => $tok
        ]);
    }

    public function releaseLock($id, $lockId, $request, $response, EntityManager $em, Translation $translation)
    {
        $oLock = $em->getRepository('App:OpenLock')->find($lockId);
        if (!$oLock) {
            throw new \Slim\Exception\HttpNotFoundException($request);
        }

        if ($oLock->getSubtitle()->getId() != $id) {
            return $response->withStatus(400);
        }

        $translation->broadcastClose($oLock->getSubtitle(), $oLock->getSequenceNumber());
        $em->remove($oLock);
        $em->flush();

        return $response->withStatus(200);
    }

    public function loadData($id, ServerRequestInterface $request, $response, EntityManager $em)
    {
        $params = $request->getQueryParams();
        $secondaryLang = $params['secondaryLang'] ?? 0;

        $usersInvolved = [];
        $sequences = [];

        $sub = $em->getRepository('App:Subtitle')->find($id);
        if (!$sub) {
            throw new \Slim\Exception\HttpNotFoundException($request);
        }

        $openList = $em->createQuery('SELECT ol FROM App:OpenLock ol WHERE ol.subtitle = :id ORDER BY ol.sequenceNumber ASC')
            ->setParameter('id', $id)
            ->getResult();

        $openInfo = [];
        foreach ($openList as $o) {
            $u = $o->getUser();
            $openInfo[$o->getSequenceNumber()] = [
                'lockID' => $o->getId(),
                'by' => $u->getId(),
                'since' => $o->getGrantTime()->format(\DateTime::ATOM)
            ];

            if (!isset($usersInvolved[$u->getId()])) {
                $usersInvolved[$u->getId()] = [
                    'username' => $u->getUsername(),
                    'roles' => $u->getRoles()
                ];
            }
        }

        $seqList = $em->createQuery('SELECT sq FROM App:Sequence sq JOIN sq.author u WHERE sq.subtitle = :id ORDER BY sq.number ASC, sq.revision DESC')
            ->setParameter('id', $id)
            ->getResult();

        foreach ($seqList as $seq) {
            $snum = $seq->getNumber();

            if (!isset($sequences[$snum])) {
                $sequences[$snum] = $seq->jsonSerialize();
                $sequences[$snum]['text'] = str_rot13($sequences[$snum]['text']);
                $sequences[$snum]['openInfo'] = !isset($openInfo[$snum]) ? null : $openInfo[$snum];

                $u = $seq->getAuthor();
                if (!isset($usersInvolved[$u->getId()])) {
                    $usersInvolved[$u->getId()] = [
                        'username' => $u->getUsername(),
                        'roles' => $u->getRoles()
                    ];
                }
            } else {
                // If sequence was already defined, then we're looking at its history
                if (!isset($sequences[$snum]['history'])) {
                    $sequences[$snum]['history'] = [];
                }

                $sequences[$snum]['history'][] = [
                    'id' => $seq->getId(),
                    'tstart' => $seq->getStartTime(),
                    'tend' => $seq->getEndTime(),
                    'text' => $seq->getText(),
                    'author' => $seq->getAuthor()->getId()
                ];

                $u = $seq->getAuthor();
                if (!isset($usersInvolved[$u->getId()])) {
                    $usersInvolved[$u->getId()] = [
                        'username' => $u->getUsername(),
                        'roles' => $u->getRoles()
                    ];
                }
            }
        }

        if ($secondaryLang > 0) {
            // Also load stuff from the base lang
            $secondarySub = $em->createQuery('SELECT sb FROM App:Subtitle sb WHERE sb.progress = 100 AND sb.version = :ver AND sb.lang = :lang')
                ->setParameter('lang', $secondaryLang)
                ->setParameter('ver', $sub->getVersion())
                ->getResult();

            $altSeqList = $em->createQuery('SELECT sq FROM App:Sequence sq WHERE sq.subtitle = :ssub ORDER BY sq.id ASC')
                ->setParameter('ssub', $secondarySub)
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

                if (!isset($sequences[$snum])) {
                    $temp = new Sequence(); // not intended to persist
                    $temp->setNumber($snum);
                    $temp->setStartTime($altSeq->getStartTime());
                    $temp->setEndTime($altSeq->getEndTime());
                    $temp->setText('');

                    $sequences[$snum] = $temp->jsonSerialize();
                    $sequences[$snum]['openInfo'] = !isset($openInfo[$snum]) ? null : $openInfo[$snum];
                }

                $sequences[$snum]['secondary_text'] = str_rot13($altSeq->getText());
            }
        }

        foreach ($sequences as &$seq) {
            if (isset($seq['history'])) {
                $seq['history'] = array_reverse($seq['history']);
            }
        }

        return Utils::jsonResponse($response, ['sequences' => $sequences, 'users' => $usersInvolved]);
    }

    public function open($id, $request, $response, EntityManager $em, Auth $auth, Translation $translation)
    {
        $body = $request->getParsedBody();
        $seqNum = (int)($body['seqNum'] ?? 0);
        if (!$seqNum) {
            return $response->withStatus(400);
        }

        $sub = $em->getRepository('App:Subtitle')->find($id);
        if (!$sub) {
            throw new \Slim\Exception\HttpNotFoundException($request);
        }

        $sequence = $translation->getLatestSequenceRev($id, $seqNum);

        $oLock = $em->createQuery('SELECT ol, u FROM App:OpenLock ol JOIN ol.user u WHERE ol.subtitle = :sub AND ol.sequenceNumber = :num')
            ->setParameter('sub', $sub->getId())
            ->setParameter('num', $seqNum)
            ->getOneOrNullResult();

        $res = ['ok' => true, 'text' => $sequence ? $sequence->getText() : null, 'id' => $sequence ? $sequence->getId() : null];
        if (!$oLock) {
            $byUser = $auth->getUser();

            // Cool, let's create a lock
            $oLock = new OpenLock();
            $oLock->setSubtitle($sub);
            $oLock->setSequenceNumber($seqNum);
            $oLock->setUser($byUser);
            $oLock->setGrantTime(new \DateTime());
            $em->persist($oLock);
            $em->flush();

            $translation->broadcastOpen($sub, $byUser, $seqNum, $oLock);
        } elseif ($oLock->getUser()->getId() != $auth->getUser()->getId()) {
            // Sequence already open!
            $res['ok'] = false;
            $res['msg'] = sprintf('El usuario %s está editando esta secuencia (#%d)', $oLock->getUser()->getUsername(), $seqNum);
        }

        return Utils::jsonResponse($response, $res);
    }

    public function close($id, $request, $response, EntityManager $em, Auth $auth, Translation $translation)
    {
        $body = $request->getParsedBody();
        $seqNum = (int)($body['seqNum'] ?? 0);
        if (!$seqNum) {
            return $response->withStatus(400);
        }

        $oLock = $em->createQuery('SELECT ol FROM App:OpenLock ol WHERE ol.subtitle = :sub AND ol.sequenceNumber = :num')
            ->setParameter('sub', $id)
            ->setParameter('num', $seqNum)
            ->getOneOrNullResult();

        if ($oLock) {
            $translation->broadcastClose($oLock->getSubtitle(), $seqNum);

            $em->remove($oLock);
            $em->flush();
        }

        return $response->withStatus(200);
    }

    public function save($id, $request, $response, EntityManager $em, Auth $auth, Translation $translation)
    {
        $body = $request->getParsedBody();
        $canChangeTimes = $auth->hasRole('ROLE_MOD');

        $seqID = $body['seqID'] ?? 0;
        $text = Translation::cleanText($body['text'] ?? '');
        $nStartTime = $body['tstart'] ?? 0;
        $nEndTime = $body['tend'] ?? 0;

        if ($nStartTime && $nEndTime && $nStartTime >= $nEndTime) {
            return $response->withStatus(400);
        }

        if (Translation::containsInvalidCharacters($text)) {
            return $response->withStatus(400);
        }

        $seq = $em->getRepository('App:Sequence')->find($seqID);
        if (!$seq) {
            throw new \Slim\Exception\HttpNotFoundException($request);
        }

        if ($seq->getSubtitle()->getId() != $id) {
            return $response->withStatus(400);
        }

        $changed = $text != $seq->getText() || ($canChangeTimes && ($nStartTime != $seq->getStartTime() || $nEndTime != $seq->getEndTime()));
        if (!$changed) {
            // Nothing to change here, send the id of this very sequence
            $response->getBody()->write($seq->getId());
            return $response->withStatus(200);
        }

        if ($seq->getLocked() && !$auth->hasRole('ROLE_TT')) {
            return $response->withStatus(403);
        }

        // Fetch the latest revision number for this sequence
        $curRev = $em->createQuery('SELECT MAX(sq.revision) FROM App:Sequence sq WHERE sq.subtitle = :sub AND sq.number = :num')
            ->setParameter('sub', $id)
            ->setParameter('num', $seq->getNumber())
            ->getSingleScalarResult();

        if ($curRev != $seq->getRevision()) {
            // Can't edit an outdated revision!
            return $response->withStatus(400);
        }

        // Update last edition time of parent sub
        $seq->getSubtitle()->setEditTime(new \DateTime());
        $seq->getSubtitle()->setLastEditedBy($auth->getUser());

        // Find an open lock on this sequence and clear it
        $oLock = $em->createQuery('SELECT ol FROM App:OpenLock ol WHERE ol.subtitle = :sub AND ol.sequenceNumber = :num')
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
        if ($canChangeTimes && $nStartTime && $nEndTime) {
            $nseq->setStartTime($nStartTime);
            $nseq->setEndTime($nEndTime);
        }
        $em->persist($nseq);
        $em->flush();

        $translation->broadcastSeqChange($nseq);
        $response->getBody()->write((string)$nseq->getId());
        return $response->withStatus(200);
    }

    /**
     * Handles the creation of a new translation for a given sequence
     */
    public function create($id, $request, $response, EntityManager $em, Auth $auth, Translation $translation)
    {
        $body = $request->getParsedBody();
        $canChangeTimes = $auth->hasRole('ROLE_MOD');

        $seqNum = $body['number'] ?? 0;
        $text = Translation::cleanText($body['text'] ?? '');
        $nStartTime = $body['tstart'] ?? 0;
        $nEndTime = $body['tend'] ?? 0;

        if ($nStartTime && $nEndTime && $nStartTime >= $nEndTime) {
            return $response->withStatus(400);
        }

        if (Translation::containsInvalidCharacters($text)) {
            return $response->withStatus(400);
        }

        $seq = $em->createQuery('SELECT COUNT(sq.id) FROM App:Sequence sq WHERE sq.subtitle = :sub AND sq.number = :num')
            ->setParameter('sub', $id)
            ->setParameter('num', $seqNum)
            ->getSingleScalarResult();

        if ($seq != 0) {
            // A sequence for this number already exists, but it shouldnt
            return $response->withStatus(403);
        }

        // Find the original sequence, and create one lookalike
        $curSub = $em->getRepository('App:Subtitle')->find($id);
        if (!$curSub) {
            throw new \Slim\Exception\HttpNotFoundException($request);
        }

        $baseSubId = $translation->getBaseSubId($curSub);
        $baseSeq = $translation->getLatestSequenceRev($baseSubId, $seqNum);
        if (!$baseSeq) {
            // TODO: Log
            return $response->withStatus(500);
        }

        // Update last edition time of parent sub
        $curSub->setEditTime(new \DateTime());
        $curSub->setLastEditedBy($auth->getUser());

        // Find an open lock on this sequence and clear it
        $oLock = $em->createQuery('SELECT ol FROM App:OpenLock ol WHERE ol.subtitle = :sub AND ol.sequenceNumber = :num')
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
        if ($canChangeTimes && $nStartTime && $nEndTime) {
            $seq->setStartTime($nStartTime);
            $seq->setEndTime($nEndTime);
        }

        $em->persist($seq);
        $em->flush();

        // Update progress
        $translation->recalculateSubtitleProgress($baseSubId, $curSub);

        $translation->broadcastSeqChange($seq);
        $response->getBody()->write((string)$seq->getId());
        return $response->withStatus(200);
    }

    /**
     * Handles the toggling of the locked status on a given sequence
     */
    public function lockToggle($id, $request, $response, EntityManager $em, Translation $translation)
    {
        $body = $request->getParsedBody();
        $seqID = $body['seqID'] ?? 0;
        $seq = $em->getRepository('App:Sequence')->find($seqID);
        if (!$seq) {
            throw new \Slim\Exception\HttpNotFoundException($request);
        }

        $seq->setLocked(!$seq->getLocked());
        $em->persist($seq);
        $em->flush();

        $translation->broadcastLockChange($seq);
        return $response->withStatus(200);
    }
}
