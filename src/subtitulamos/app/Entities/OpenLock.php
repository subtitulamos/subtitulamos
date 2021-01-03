<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2021 subtitulamos.tv
 */

namespace App\Entities;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="open_locks")
 */
class OpenLock
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="Subtitle")
     * @ORM\JoinColumn(name="subtitle_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private $subtitle;

    /**
     * @ORM\Column(type="integer", name="sequence_number")
     */
    private $sequenceNumber;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     */
    private $user;

    /**
     * @ORM\Column(type="datetime")
     */
    private $grantTime;

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set grantTime
     *
     * @param \DateTime $grantTime
     * @return OpenLock
     */
    public function setGrantTime($grantTime)
    {
        $this->grantTime = $grantTime;

        return $this;
    }

    /**
     * Get grantTime
     *
     * @return \DateTime
     */
    public function getGrantTime()
    {
        return $this->grantTime;
    }

    /**
     * Set user
     *
     * @param \App\Entities\User $user
     * @return OpenLock
     */
    public function setUser(\App\Entities\User $user = null)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user
     *
     * @return \App\Entities\User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set sequenceNumber
     *
     * @param integer $sequenceNumber
     * @return OpenLock
     */
    public function setSequenceNumber($sequenceNumber)
    {
        $this->sequenceNumber = $sequenceNumber;

        return $this;
    }

    /**
     * Get sequenceNumber
     *
     * @return integer
     */
    public function getSequenceNumber()
    {
        return $this->sequenceNumber;
    }

    /**
     * Set subtitle
     *
     * @param \App\Entities\Subtitle $subtitle
     * @return OpenLock
     */
    public function setSubtitle(\App\Entities\Subtitle $subtitle = null)
    {
        $this->subtitle = $subtitle;

        return $this;
    }

    /**
     * Get subtitle
     *
     * @return \App\Entities\Subtitle
     */
    public function getSubtitle()
    {
        return $this->subtitle;
    }
}
