<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017 subtitulamos.tv
 */

namespace App\Services\Srt;

use App\Entities\Sequence;
use App\Services\Clock;
use App\Services\Translation;
use ForceUTF8\Encoding;

const PARSING_STATE_SEQUENCE = 0;
const PARSING_STATE_TIME = 1;
const PARSING_STATE_TEXT = 2;

class SrtParser
{
    /**
     * Whether the file in this class is currently
     * a valid one or not
     *
     * @var boolean
     */
    private $valid = false;

    /**
     * Description of the error, if any
     *
     * @var string
     */
    private $errorDesc = null;

    /**
     * Contains all the sequences in this file
     *
     * @var array
     */
    private $sequences = [];

    /**
     * getter for the valid property
     * @return boolean
     */
    public function isValid()
    {
        return $this->valid;
    }

    /**
     * Parse reads an srt file and processes it
     *
     * @param string $filename
     * @return boolean
     */
    public function parseFile(string $filename, bool $allowSpecialTags)
    {
        $flines = file($filename);

        $this->lastTimeEnd = 0;
        $this->seqNum = 0;

        $parsingState = PARSING_STATE_SEQUENCE;

        $sequences = [];
        $sequence = null;
        foreach ($flines as $line) {
            $line = trim(self::removeUtf8Bom($line));
            if (empty($line)) {
                if ($parsingState == PARSING_STATE_TEXT) {
                    // We're done with this line
                    if ($sequence) {
                        $sequence->setText(Translation::cleanText($sequence->getText(), $allowSpecialTags));

                        $sequences[] = $sequence;
                        $sequence = null;
                    }

                    $parsingState = PARSING_STATE_SEQUENCE;
                }

                continue;
            }

            switch ($parsingState) {
                case PARSING_STATE_SEQUENCE:
                    if (!is_numeric($line)) {
                        break;
                    }

                    $sequence = new Sequence();
                    $sequence->setNumber(++$this->seqNum);
                    $sequence->setRevision(0);
                    $sequence->setLocked(false);
                    $sequence->setVerified(false);

                    $parsingState = PARSING_STATE_TIME;
                    break;

                case PARSING_STATE_TIME:
                    preg_match("/([\d:]+)[,.](\d+)\s*-->\s*([\d:]+)[,.](\d+)/", $line, $matches);
                    if (count($matches) != 5) {
                        $this->errorDesc = 'Formato incorrecto: El formato de los tiempos de la línea '.$line.' es incorrecto';
                        return false;
                    }

                    $tstart = Clock::timeToInt($matches[1].','.$matches[2]);
                    $tend = Clock::timeToInt($matches[3].','.$matches[4]);
                    if ($tstart > $tend) {
                        $this->errorDesc = 'Formato incorrecto: El tiempo de inicio en la secuencia #'.($this->seqNum + 1).' es mayor que su tiempo de fin.';
                        return false;
                    }

                        if ($tstart <= $this->lastTimeEnd) {
                            if ($tstart + 50 < $tend && $tstart + 50 > $this->lastTimeEnd) {
                                // Autocorrección del solapamiento si es inferior a 50ms
                                $tstart += $this->lastTimeEnd - $tstart + 1;
                            } else {
                                $this->errorDesc = 'Formato incorrecto: Los tiempos de las secuencias #'.$this->seqNum.' y #'.($this->seqNum + 1).' tienen un solapamiento significativo.';
                                return false;
                            }
                        }


                    $sequence->setStartTime($tstart);
                    $sequence->setEndTime($tend);

                    $this->lastTimeEnd = $tend;

                    $parsingState = PARSING_STATE_TEXT;
                    break;

                case PARSING_STATE_TEXT:
                    $line = Encoding::toUTF8($line);
                    if (empty($sequence->getText())) {
                        $sequence->setText($line);
                    } else {
                        $sequence->setText($sequence->getText()."\n".$line);
                    }

                    break;
            }
        }

        $this->sequences = $sequences;
        $this->valid = true;
        return true;
    }

    /**
     * @return string
     */
    public function getErrorDesc()
    {
        return $this->errorDesc;
    }

    /**
     * @return string
     */
    public function getSequences()
    {
        return $this->sequences;
    }

    public static function removeUtf8Bom($text)
    {
        $bom = pack('H*', 'EFBBBF');
        $text = preg_replace("/^$bom/", '', $text);

        return $text;
    }
}
