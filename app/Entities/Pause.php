<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017 subtitulamos.tv
 */

namespace App\Entities;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="pauses")
 */
class Pause
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\OneToOne(targetEntity="Subtitle", mappedBy="pause")
     */
    private $subtitle;

    /**
     * @ORM\Column(type="integer")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     */
    private $user;

    /**
     * @ORM\Column(type="datetime")
     */
    private $start;

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
     * Set start
     *
     * @param \DateTime $start
     *
     * @return Pause
     */
    public function setStart($start)
    {
        $this->start = $start;

        return $this;
    }

    /**
     * Get start
     *
     * @return \DateTime
     */
    public function getStart()
    {
        return $this->start;
    }

    /**
     * Set subtitle
     *
     * @param \App\Entities\Subtitle $subtitle
     *
     * @return Pause
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

    /**
     * Set user
     *
     * @param \App\Entities\User $user
     *
     * @return Pause
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
}
