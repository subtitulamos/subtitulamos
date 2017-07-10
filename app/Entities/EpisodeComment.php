<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017 subtitulamos.tv
 */

namespace App\Entities;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="episode_comments")
 */
class EpisodeComment implements \JsonSerializable
{
    const TYPE_GENERIC = 1;
    const TYPE_EPISODE_TALK = 2;

    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="Episode", inversedBy="comments")
     * @ORM\JoinColumn(name="episode_id", referencedColumnName="id")
     */
    private $episode;

    /**
     * @ORM\Column(type="text")
     */
    private $text;

    /**
     * @ORM\Column(type="integer")
     */
    private $type;

    /**
     * @ORM\ManyToOne(fetch="EAGER", targetEntity="User", inversedBy="epComments")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     */
    private $user;

    /**
     * @ORM\Column(type="datetime", name="publish_time", options={"default": 0})
     */
    private $publishTime;

    /**
     * @ORM\Column(type="datetime", name="edit_time", options={"default": 0})
     */
    private $editTime;

    /**
     * @ORM\Column(type="boolean", name="soft_deleted")
     */
    private $softDeleted;

    public function jsonSerialize()
    {
        return [
            "id" => $this->id,
            "user" => [
                "id" => $this->user->getId(),
                "name" => $this->user->getUsername()
            ],
            "published_at" => $this->publishTime->format(\DateTime::ATOM),
            "edited_at" => $this->editTime->format(\DateTime::ATOM),
            "text" => $this->text
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
     * @return EpisodeComment
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
     * Set type
     *
     * @param integer $type
     * @return EpisodeComment
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type
     *
     * @return integer
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set publishTime
     *
     * @param \DateTime $publishTime
     * @return EpisodeComment
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
     * @return EpisodeComment
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
     * Set episode
     *
     * @param \App\Entities\Episode $episode
     * @return EpisodeComment
     */
    public function setEpisode(\App\Entities\Episode $episode = null)
    {
        $this->episode = $episode;

        return $this;
    }

    /**
     * Get episode
     *
     * @return \App\Entities\Episode
     */
    public function getEpisode()
    {
        return $this->episode;
    }

    /**
     * Set user
     *
     * @param \App\Entities\User $user
     * @return EpisodeComment
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
     * @return EpisodeComment
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
