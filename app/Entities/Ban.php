<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017 subtitulamos.tv
 */

namespace App\Entities;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="bans")
 */
class Ban
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $reason;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="by_user_id", referencedColumnName="id")
     */
    private $byUser;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="unban_user_id", nullable=true, referencedColumnName="id")
     */
    private $unbanUser;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="target_user_id", referencedColumnName="id")
     */
    private $targetUser;

    /**
     * @ORM\Column(type="datetime")
     */
    private $until;

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
     * Set reason
     *
     * @param string $reason
     *
     * @return Ban
     */
    public function setReason($reason)
    {
        $this->reason = $reason;

        return $this;
    }

    /**
     * Get reason
     *
     * @return string
     */
    public function getReason()
    {
        return $this->reason;
    }

    /**
     * Set until
     *
     * @param \DateTime $until
     *
     * @return Ban
     */
    public function setUntil($until)
    {
        $this->until = $until;

        return $this;
    }

    /**
     * Get until
     *
     * @return \DateTime
     */
    public function getUntil()
    {
        return $this->until;
    }

    /**
     * Set byUser
     *
     * @param \App\Entities\User $byUser
     *
     * @return Ban
     */
    public function setByUser(\App\Entities\User $byUser = null)
    {
        $this->byUser = $byUser;

        return $this;
    }

    /**
     * Get byUser
     *
     * @return \App\Entities\User
     */
    public function getByUser()
    {
        return $this->byUser;
    }

    /**
     * Set targetUser
     *
     * @param \App\Entities\User $targetUser
     *
     * @return Ban
     */
    public function setTargetUser(\App\Entities\User $targetUser = null)
    {
        $this->targetUser = $targetUser;

        return $this;
    }

    /**
     * Get targetUser
     *
     * @return \App\Entities\User
     */
    public function getTargetUser()
    {
        return $this->targetUser;
    }

    /**
     * Set unbanUser
     *
     * @param \App\Entities\User $unbanUser
     *
     * @return Ban
     */
    public function setUnbanUser(\App\Entities\User $unbanUser = null)
    {
        $this->unbanUser = $unbanUser;

        return $this;
    }

    /**
     * Get unbanUser
     *
     * @return \App\Entities\User
     */
    public function getUnbanUser()
    {
        return $this->unbanUser;
    }
}
