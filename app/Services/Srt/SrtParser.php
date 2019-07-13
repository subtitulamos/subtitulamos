<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017-2019 subtitulamos.tv
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
     * Parse reads an srt file and processes it
     *
     * @param string $filename
     * @return array $seqOpts Options for the cleanText call on each sequence
     */
    public function parseFile(string $filename, array $seqOpts)
    {
        if (!$filename) {
            $this->errorDesc = 'El nombre de fichero no puede estar vacío.';
            return false;
        }

        $flines = file($filename);

        $this->lastTimeEnd = 0;
        $this->seqNum = 0;

        $parsingState = PARSING_STATE_SEQUENCE;

        $sequences = [];
        $sequence = null;
        $lineNumber = 0;
        foreach ($flines as $line) {
            $lineNumber++;
            $line = trim(self::removeUtf8Bom($line));
            if (empty($line)) {
                if ($parsingState == PARSING_STATE_TEXT) {
                    // We're done with this line
                    if ($sequence) {
                        // Clean the text and trim, because cleanText alwawys returns AT LEAST
                        // a single space, never an empty sequence.
                        $cleanText = !empty($sequence->getText()) ? trim(Translation::cleanText($sequence->getText(), $seqOpts)) : '';
                        if (!empty($cleanText)) {
                            $sequence->setText($cleanText);
                            $sequence->setNumber(++$this->seqNum);
                            $sequences[] = $sequence;
                        }

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
                    $sequence->setRevision(0);
                    $sequence->setLocked(false);
                    $sequence->setVerified(false);

                    $parsingState = PARSING_STATE_TIME;
                    break;

                case PARSING_STATE_TIME:
                    preg_match("/([\d:]+)[,.](\d+)\s*-->\s*([\d:]+)[,.](\d+)/", $line, $matches);
                    if (count($matches) != 5) {
                        $this->errorDesc = 'Formato incorrecto: El formato de los tiempos de la línea '.$lineNumber.' es incorrecto';
                        return false;
                    }

                    $tstart = Clock::timeToInt($matches[1].','.$matches[2]);
                    $tend = Clock::timeToInt($matches[3].','.$matches[4]);
                    if ($tstart > $tend) {
                        $this->errorDesc = 'Formato incorrecto: El tiempo de inicio en la secuencia #'.($this->seqNum + 1).' es superior a su tiempo de fin.';
                        return false;
                    }

                        if ($tstart <= $this->lastTimeEnd) {
                            if ($tstart + 50 < $tend && $tstart + 50 > $this->lastTimeEnd) {
                                // Autocorrección del solapamiento si es inferior a 50ms
                                $tstart += $this->lastTimeEnd - $tstart + 1;
                            } else {
                                $this->errorDesc = 'Formato incorrecto: Los tiempos de las secuencias #'.$this->seqNum.' y #'.($this->seqNum + 1).' '+
                                                   'tienen un solapamiento significativo.';
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

        // We're done, if the file didn't have a final linebreak we will not have detected
        // the end of the last sequence, let's see if we need to upload it
        if (!empty($line) && $parsingState == PARSING_STATE_TEXT && $sequence) {
            if (!empty($sequence->getText())) {
                $sequence->setText(Translation::cleanText($sequence->getText(), $seqOpts));
                $sequence->setNumber(++$this->seqNum);
                $sequences[] = $sequence;
            }
        }

        $this->sequences = $sequences;
        if (count($sequences) < 3) {
            $this->errorDesc = 'Formato incorrecto: Debe haber al menos 3 secuencias en el fichero.';
            return false;
        }

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
