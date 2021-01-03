<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2021 subtitulamos.tv
 */

namespace App\Services;

//use Psr\Log\LoggerInterface;

class Clock
{
    private $hour;
    private $min;
    private $sec;
    private $milli;

    public function __construct($time)
    {
        $totalSecs = floor($time / 1000);
        $this->milli = $time - $totalSecs * 1000;
        $this->hour = floor($totalSecs / 3600);
        $this->min = floor(($totalSecs - $this->hour * 3600) / 60);
        $this->sec = $totalSecs - $this->hour * 3600 - $this->min * 60;
    }

    public function addHour($c)
    {
        $this->hour += (int)$c;
    }

    public function addMin($c)
    {
        $c = (int)$c;
        $nmin = $this->min + $c;
        if ($nmin >= 60) {
            $addedHours = floor($nmin / 60);
            $this->addHour($addedHours);

            $nmin -= $addedHours * 60;
        }

        $this->min = $nmin;
    }

    public function addSec($c)
    {
        $c = (int)$c;
        $nsec = $this->sec + $c;
        if ($nsec >= 60) {
            $addedMinutes = floor($nsec / 60);
            $this->addMin($addedMinutes);

            $nsec -= $addedMinutes * 60;
        }

        $this->sec = $nsec;
    }

    public function addMilli($c)
    {
        $c = (int)$c;
        $nmilli = $this->milli + $c;
        if ($nmilli >= 1000) {
            $addedSeconds = floor($nmilli / 1000);
            $this->addSec($addedSeconds);

            $nmilli -= $addedSeconds * 1000;
        }

        $this->milli = $nmilli;
    }

    public function noMilliString()
    {
        return str_pad($this->hour, 2, '0', STR_PAD_LEFT).':'.str_pad($this->min, 2, '0', STR_PAD_LEFT).':'.str_pad($this->sec, 2, '0', STR_PAD_LEFT);
    }

    public function __toString()
    {
        return str_pad($this->hour, 2, '0', STR_PAD_LEFT).':'.str_pad($this->min, 2, '0', STR_PAD_LEFT).':'.str_pad($this->sec, 2, '0', STR_PAD_LEFT).','.str_pad($this->milli, 3, '0', STR_PAD_LEFT);
    }

    public static function parse(string $time)
    {
        if (!preg_match("/(?:(\d*):)?(\d*):(\d*)(?:[\.,](\d*))?/", $time, $matches)) {
            // TODO: $this->logger->error("Could not match $timeString as time string");
            return ['hour' => 0, 'min' => 0, 'sec' => 0, 'milli' => 0];
        }

        return [
            'hour' => $matches[2] ? (int)$matches[1] : 0,
            'min' => $matches[2] ? (int)$matches[2] : 0,
            'sec' => $matches[3] ? (int)$matches[3] : 0,
            'milli' => $matches[4] ? (int)$matches[4] : 0
        ];
    }

    /**
    * @param $timeString The time string value to convert
    * @return int
    */
    public static function timeToInt($timeString)
    {
        $parsed = self::parse($timeString);
        return ($parsed['sec'] + $parsed['min'] * 60 + $parsed['hour'] * 3600) * 1000 + $parsed['milli'];
    }

    /**
    * @param $timeValue The time int value to convert
    * @return string
    */
    public static function intToTimeStr($timeValue)
    {
        return (string)(new self($timeValue));
    }
}
