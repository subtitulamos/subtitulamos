<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017-2018 subtitulamos.tv
 */

namespace App\Entities;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="subtitle_comments")
 */
class SubtitleComment implements \JsonSerializable
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="Subtitle", inversedBy="comments")
     * @ORM\JoinColumn(name="subtitle_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private $subtitle;

    /**
     * @ORM\Column(type="text")
     */
    private $text;

    /**
     * @ORM\ManyToOne(fetch="EAGER", targetEntity="User", inversedBy="subComments")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     */
    private $user;

    /**
     * @ORM\Column(type="datetime", name="publish_time", options={"default": "CURRENT_TIMESTAMP"})
     */
    private $publishTime;

    /**
     * @ORM\Column(type="datetime", name="edit_time", options={"default": "CURRENT_TIMESTAMP"})
     */
    private $editTime;

    /**
     * @ORM\Column(type="boolean", name="soft_deleted")
     */
    private $softDeleted;

    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->user->getId(),
                'username' => $this->user->getUsername(),
                'roles' => $this->user->getRoles()
            ],
            'published_at' => $this->publishTime->format(\DateTime::ATOM),
            'edited_at' => $this->editTime->format(\DateTime::ATOM),
            'text' => htmlspecialchars($this->text)
        ];
    }

    ///////////////////////////// AUTOMATICALLY GENERATED CODE BELOW \\\\\\\\\\\\\\\\\\\\\\\\\\

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
     * Set text
     *
     * @param string $text
     * @return SubtitleComment
     */
    public function setText($text)
    {
        $this->text = $text;

        return $this;
    }

    /**
     * Get text
     *
     * @return string
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * Set publishTime
     *
     * @param \DateTime $publishTime
     * @return SubtitleComment
     */
    public function setPublishTime($publishTime)
    {
        $this->publishTime = $publishTime;

        return $this;
    }

    /**
     * Get publishTime
     *
     * @return \DateTime
     */
    public function getPublishTime()
    {
        return $this->publishTime;
    }

    /**
     * Set editTime
     *
     * @param \DateTime $editTime
     * @return SubtitleComment
     */
    public function setEditTime($editTime)
    {
        $this->editTime = $editTime;

        return $this;
    }

    /**
     * Get editTime
     *
     * @return \DateTime
     */
    public function getEditTime()
    {
        return $this->editTime;
    }

    /**
     * Set subtitle
     *
     * @param \App\Entities\Subtitle $subtitle
     * @return SubtitleComment
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
     * @return SubtitleComment
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
     * Set softDeleted
     *
     * @param boolean $softDeleted
     * @return SubtitleComment
     */
    public function setSoftDeleted($softDeleted)
    {
        $this->softDeleted = $softDeleted;

        return $this;
    }

    /**
     * Get softDeleted
     *
     * @return boolean
     */
    public function getSoftDeleted()
    {
        return $this->softDeleted;
    }
}
