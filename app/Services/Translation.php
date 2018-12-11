<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017-2018 subtitulamos.tv
 */

namespace App\Services;

use App\Entities\OpenLock;
use App\Entities\Sequence;
use App\Entities\Subtitle;
use App\Entities\SubtitleComment;
use App\Entities\User;
use Doctrine\ORM\EntityManager;
use ForceUTF8\Encoding;

class Translation
{
    /**
     * Entity manager handle
     * @var EntityManager
     */
    private $em = null;

    /**
     * Connection to the redis server
     * @var \Redis
     */
    private $redis = null;

    public function __construct(EntityManager $em)
    {
        $this->em = $em;

        $redis = new \Redis();
        $redis->connect(getenv('REDIS_HOST'), getenv('REDIS_PORT'));
        $this->redis = $redis;
    }

    /**
     * Obtains the id of the base subtitle, that is, the canonical one
     * for the version of the given subtitle
     *
     * @param Subtitle $sub
     * @return int
     */
    public function getBaseSubId(Subtitle $sub)
    {
        return $this->em->createQuery('SELECT sb.id FROM App:Subtitle sb WHERE sb.version = :v AND sb.directUpload = 1')
            ->setParameter('v', $sub->getVersion())
            ->getSingleScalarResult();
    }

    /**
     * Finds and returns the latest revision of a given sequence number
     *
     * @param int $subId
     * @param int $seqNum
     * @return \App\Entities\Sequence
     */
    public function getLatestSequenceRev($subId, $seqNum)
    {
        return $this->em->createQuery('SELECT sq FROM App:Sequence sq WHERE sq.subtitle = :sub AND sq.number = :num ORDER BY sq.revision DESC')
            ->setParameter('sub', $subId)
            ->setParameter('num', $seqNum)
            ->setMaxResults(1)
            ->getOneOrNullResult();
    }

    public function getPubSubChanName(Subtitle $sub)
    {
        return sprintf('%s-translate-%d', ENVIRONMENT_NAME, $sub->getId());
    }

    /**
     * Broadcasts to pub/sub channel the opening of a sequence on a sub
     *
     * @param \App\Entities\Subtitle $sub
     * @param \App\Entities\User $byUser
     * @param int $seqNum
     * @param \App\Entities\OpenLock $lock
     * @return void
     */
    public function broadcastOpen(Subtitle $sub, User $byUser, int $seqNum, OpenLock $lock)
    {
        $this->redis->publish($this->getPubSubChanName($sub), \json_encode([
            'type' => 'seq-open',
            'user' => $byUser->getId(),
            'num' => $seqNum,
            'openLockID' => $lock->getId()
        ]));
    }

    /**
     * Broadcasts to pub/sub channel the closing of a sequence on a sub
     *
     * @param \App\Entities\Subtitle $sub
     * @param int $seqNum
     * @return void
     */
    public function broadcastClose(Subtitle $sub, int $seqNum)
    {
        $this->redis->publish($this->getPubSubChanName($sub), \json_encode([
            'type' => 'seq-close',
            'num' => $seqNum
        ]));
    }

    /**
     * Broadcasts to pub/sub channel the edition of a sequence on a sub
     *
     * @param \App\Entities\Sequence $seq
     * @return void
     */
    public function broadcastSeqChange(Sequence $seq)
    {
        $sub = $seq->getSubtitle();
        $this->redis->publish($this->getPubSubChanName($sub), \json_encode([
            'type' => 'seq-change',
            'user' => $seq->getAuthor()->getId(),
            'num' => $seq->getNumber(),
            'nid' => $seq->getId(),
            'ntext' => $seq->getText(),
            'ntstart' => (int)$seq->getStartTime(),
            'ntend' => (int)$seq->getEndTime()
        ]));
    }

    /**
     * Broadcasts to pub/sub channel the lock status of a sequence on a sub
     *
     * @param \App\Entities\Sequence $seq
     * @return void
     */
    public function broadcastLockChange(Sequence $seq)
    {
        $sub = $seq->getSubtitle();
        $this->redis->publish($this->getPubSubChanName($sub), \json_encode([
            'type' => 'seq-lock',
            'id' => $seq->getId(),
            'status' => $seq->getLocked()
        ]));
    }

    /**
     * Broadcasts to pub/sub channel the deletion of a sequence
     *
     * @param \App\Entities\Subtitle $sub
     * @param int $seqId
     * @return void
     */
    public function broadcastDeleteSequence(Subtitle $sub, int $seqId)
    {
        $this->redis->publish($this->getPubSubChanName($sub), \json_encode([
            'type' => 'seq-del',
            'id' => $seqId
        ]));
    }

    /**
     * Broadcasts new comment being published
     *
     * @param \App\Entities\SubtitleComment $c
     * @return void
     */
    public function broadcastNewComment(SubtitleComment $c)
    {
        $sub = $c->getSubtitle();
        $this->redis->publish($this->getPubSubChanName($sub), \json_encode([
            'type' => 'com-new',
            'id' => $c->getId(),
            'user' => $c->getUser()->getId(),
            'time' => $c->getPublishTime()->format(\DateTime::ATOM),
            'text' => $c->getText()
        ]));
    }

    /**
     * Broadcasts comment being deleted
     *
     * @param \App\Entities\SubtitleComment $c
     * @return void
     */
    public function broadcastDeleteComment(SubtitleComment $c)
    {
        $sub = $c->getSubtitle();
        $this->redis->publish($this->getPubSubChanName($sub), \json_encode([
            'type' => 'com-del',
            'id' => $c->getId()
        ]));
    }

    /**
     * Broadcasts comment being deleted
     *
     * @param \App\Entities\Subtitle $sub
     * @param \App\Entities\User $u
     * @return void
     */
    public function broadcastUserInfo(Subtitle $sub, User $u)
    {
        $this->redis->publish($this->getPubSubChanName($sub), \json_encode([
            'type' => 'uinfo',
            'id' => $u->getId(),
            'username' => $u->getUsername(),
            'roles' => $u->getRoles()
        ]));
    }

    /**
     * Set the redis authentication token for real time translation.
     * Token is valid for 24h
     *
     * @param string $token
     * @param \App\Entities\Subtitle $sub
     * @return void
     */
    public function setWSAuthToken(string $token, Subtitle $sub)
    {
        $this->redis->set('authtok-'.ENVIRONMENT_NAME.'-'.$token, $sub->getId(), 24 * 60 * 60);
    }

    /**
     * Calculate the translation progress for a given subtitle
     *
     * @param \App\Entities\Subtitle|int $baseSub
     * @param \App\Entities\Subtitle $sub
     * @param int $modifier
     * @return void
     */
    public function recalculateSubtitleProgress($baseSub, Subtitle $sub)
    {
        if (!$baseSub) {
            $baseSub = $this->getBaseSubId($sub);
        }

        $baseSubSeqCount = $this->em->createQuery('SELECT COUNT(DISTINCT sq.number) FROM App:Sequence sq WHERE sq.subtitle = :sub')
            ->setParameter('sub', $baseSub)
            ->getSingleScalarResult();

        $ourSubSeqCount = $this->em->createQuery('SELECT COUNT(DISTINCT sq.number) FROM App:Sequence sq WHERE sq.subtitle = :sub')
            ->setParameter('sub', $sub->getId())
            ->getSingleScalarResult();

        $sub->setProgress($ourSubSeqCount / $baseSubSeqCount * 100);
        if ($sub->getProgress() == 100 && !$sub->getPause()) {
            // We're done! Mark as such
            $sub->setCompleteTime(new \DateTime());
        } elseif ($sub->getCompleteTime()) {
            $sub->setCompleteTime(null);
        }
        $this->em->flush();
    }

    /**
     * Determine if a string contains a subtitle's credits (both ours and other people's)
     * @param string $text
     * @return bool
     */
    public static function containsCreditsText(string $text)
    {
        if (preg_match("/(?:(www\.)?addic7ed\.com)|(?:corrected by elderman)|(?:^[-*\[]?credito?s[-*\]]?$)|(?:(www\.)?tusubtitulo\.com)/i", $text) == 1) {
            return true;
        }

        return self::containsOwnCreditsText($text);
    }

    /**
     * Determine if a string contains our own credits
     * @param string $text
     * @return bool
     */
    public static function containsOwnCreditsText($text)
    {
        return preg_match("/(www\.)?subtitulamos\.(?:com|tv)/i", $text) == 1;
    }

    /**
     * Determine if a sequence should be left blank based on its contents
     * @param string $text
     * @return int  Degree of confidence that this should be a blank sequence
     */
    public static function getBlankSequenceConfidence($sequence)
    {
        // Mmm, Hmm*, Mhm, Uhm, Mm, Eh, Uh, Oh, Ah, Gah, Mnh, Agh, Argh, Ow, Ouch, Ugh, Uhh... & combinations of those
        if (preg_match("/^(?:(?:[uhm]h*m+|[euoa]+h|ga+h+|mnh+|s+h+|h+[eua]+h+|u+g*h+|a+r*g+h+|ow+|ouch)[.!?\s-]*)*$/i", $sequence->getText()) == 1) {
            return 100;
        }

        // Annotations for the hearing impaired come between [] o ()
        if (preg_match("/^\[[^]]*\]$|^\([^)]*\)$/", $sequence->getText()) == 1) {
            return 100;
        }

        return 0;
    }

    /**
     * Determine if a given sequence has an easy translation
     * @param string $text
     * @return array{int,string} Degree of confidence that this sequence should be translated, translation
     */
    public static function getBasicSequenceTranslation($sequence, $language)
    {
        // Basic translation is currently only available for spanish
        if ($language == 'es-es' || $language == 'es-lat') {
            $singleLineText = preg_replace("/\n\r/", ' ', $sequence->getText());

            // ---> Previously on <show name>...
            // Detection is only available for the first 100 sequences
            if ($sequence->getNumber() < 100) {
                // Get the show name
                $showName = $sequence->getSubtitle()->getVersion()->getEpisode()->getShow()->getName();
                if (preg_match("/^Previously on ['\"]?".preg_quote($showName)."['\"]?(\s*\.\.\.)?$/i", $singleLineText, $matches) === 1) {
                    $translatedStr = 'Anteriormente en '.$showName;
                    if ($matches[1]) {
                        $translatedStr .= '...';
                    }

                    return [100, $translatedStr];
                }
            }

            // ---> Yes/No
            if (preg_match("/^([Yy]es|[Nn]o|NO|YES)([\.,!\?]*)$/", $singleLineText, $matches) === 1) {
                $translatedStr = '';
                switch ($matches[1]) {
                    case 'yes': $translatedStr = 'sí'; break;
                    case 'Yes': case 'YES': $translatedStr = 'Sí'; break;
                    case 'no': $translatedStr = 'no'; break;
                    case 'No': case 'NO': $translatedStr = 'No'; break;
                }

                if ($matches[2]) {
                    // We can only do this properly with a set of characters
                    if ($matches[2] == '!') {
                        $translatedStr = '¡'.$translatedStr.'!';
                        $confidence = 100;
                    } elseif ($matches[2] == '?') {
                        $translatedStr = '¿'.$translatedStr.'?';
                        $confidence = 100;
                    } elseif ($matches[2] == '...' || $matches[2] == ',' || $matches[2] == '.') {
                        $translatedStr .= $matches[2];
                        $confidence = mb_strlen($matches[2]) == 1 ? 100 : 90;
                    }
                } else {
                    $confidence = 95; // Fairly confident we're right here.
                }

                return [$confidence, $translatedStr];
            }
        }

        return [0, ''];
    }

    /**
     * Clear the text from artifacts that would render it
     * unplayable on older devices or less standard-compliant ones
     *
     * @param string $text
     * @param array $opts
     * @return void
     */
    public static function cleanText(string $text, array $opts = [])
    {
        $allowSpecialTags = isset($opts['allow_special_tags']) && $opts['allow_special_tags'] === true;
        $allowLongLines = isset($opts['allow_long_lines']) && $opts['allow_long_lines'] === true;

        if ($allowSpecialTags) {
            $text = strip_tags($text, '<font>');

            $dom = new \DOMDocument();
            $dom->loadHTML(mb_convert_encoding('<div>'.$text.'</div>', 'HTML-ENTITIES', 'UTF-8'), \LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD);

            $xpath = new \DOMXPath($dom);
            $nodes = $xpath->query('//font');
            foreach ($nodes as $node) {
                $color = $node->hasAttribute('color') ? $node->getAttribute('color') : '';
                $attributes = $node->attributes;
                while ($attributes->length) {  // https://stackoverflow.com/a/10281657/2205532
                    $node->removeAttribute($attributes->item(0)->name);
                }

                if ($color) {
                    $node->setAttribute('color', $color);
                }
            }

            $text = trim(Encoding::toUTF8(\html_entity_decode(strip_tags($dom->saveHTML($dom->documentElement), '<font>'))));
        } else {
            $text = strip_tags($text);
        }

        $pregReplacements = [
            '…' => '...',
            '“' => '"',
            '”' => '"',
            '/[\x{200B}-\x{200D}]/u' => '', //(Remove all 0-width space: https://stackoverflow.com/a/11305926/2205532)
        ];

        foreach ($pregReplacements as $k => $v) {
            // FIXME: This won't work with regex as is implied by the var name
            $text = str_replace($k, $v, $text);
        }

        $text = trim($text);
        if (empty($text)) {
            // At least one space
            $text = ' ';
        }

        // Remove multiple spaces concatenated / trim each line
        $lines = explode("\n", $text);
        foreach ($lines as &$line) {
            $line = \mb_substr(trim(preg_replace('/ +/', ' ', $line)), 0, $allowLongLines ? 80 : 40);
        }

        // Make sure that we only have two lines, and convert them back to string
        $lines = \array_slice($lines, 0, 2);
        $text = implode("\n", $lines);

        return $text;
    }
}
