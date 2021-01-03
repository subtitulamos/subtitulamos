<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2021 subtitulamos.tv
 */

namespace App\Entities;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="alerts")
 */
class Alert
{
    /**
    * @ORM\Column(type="integer")
    * @ORM\Id
    * @ORM\GeneratedValue(strategy="AUTO")
    */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="by_user_id", referencedColumnName="id")
     */
    private $byUser;

    /**
    * @ORM\Column(type="integer")
    */
    private $status;

    /**
     * @ORM\ManyToOne(targetEntity="Subtitle")
     * @ORM\JoinColumn(name="for_subtitle_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $subtitle;

    /**
     * @ORM\Column(type="datetime", name="create_time", options={"default": "CURRENT_TIMESTAMP"})
     */
    private $creationTime;

    /**
     * @ORM\OneToMany(targetEntity="AlertComment", mappedBy="alert")
     */
    private $comments;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->comments = new \Doctrine\Common\Collections\ArrayCollection();
    }

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
     * Set status
     *
     * @param integer $status
     *
     * @return Alert
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get status
     *
     * @return integer
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set creationTime
     *
     * @param \DateTime $creationTime
     *
     * @return Alert
     */
    public function setCreationTime($creationTime)
    {
        $this->creationTime = $creationTime;

        return $this;
    }

    /**
     * Get creationTime
     *
     * @return \DateTime
     */
    public function getCreationTime()
    {
        return $this->creationTime;
    }

    /**
     * Set byUser
     *
     * @param \App\Entities\User $byUser
     *
     * @return Alert
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
     * Add comment
     *
     * @param \App\Entities\AlertComment $comment
     *
     * @return Alert
     */
    public function addComment(\App\Entities\AlertComment $comment)
    {
        $this->comments[] = $comment;

        return $this;
    }

    /**
     * Remove comment
     *
     * @param \App\Entities\AlertComment $comment
     */
    public function removeComment(\App\Entities\AlertComment $comment)
    {
        $this->comments->removeElement($comment);
    }

    /**
     * Get comments
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getComments()
    {
        return $this->comments;
    }

    /**
     * Set subtitle
     *
     * @param \App\Entities\Subtitle $subtitle
     *
     * @return Alert
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
