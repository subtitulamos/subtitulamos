<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017 subtitulamos.tv
 */

namespace App\Entities;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="versions")
 */
class Version
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="Episode", inversedBy="versions")
     * @ORM\JoinColumn(name="episode_id", referencedColumnName="id")
     */
    private $episode;

    /**
     * @ORM\Column(type="string", length=60)
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=200)
     */
    private $comments;

    /**
     * @ORM\ManyToOne(targetEntity="User", inversedBy="versions")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     */
    private $user;

    /**
     * @ORM\OneToMany(targetEntity="Subtitle", mappedBy="version")
     */
    private $subtitles;
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->subtitles = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Set comments
     *
     * @param string $comments
     *
     * @return Version
     */
    public function setComments($comments)
    {
        $this->comments = $comments;

        return $this;
    }

    /**
     * Get comments
     *
     * @return string
     */
    public function getComments()
    {
        return $this->comments;
    }

    /**
     * Set episode
     *
     * @param \App\Entities\Episode $episode
     *
     * @return Version
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
     *
     * @return Version
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
     * Add subtitle
     *
     * @param \App\Entities\Subtitle $subtitle
     *
     * @return Version
     */
    public function addSubtitle(\App\Entities\Subtitle $subtitle)
    {
        $this->subtitles[] = $subtitle;

        return $this;
    }

    /**
     * Remove subtitle
     *
     * @param \App\Entities\Subtitle $subtitle
     */
    public function removeSubtitle(\App\Entities\Subtitle $subtitle)
    {
        $this->subtitles->removeElement($subtitle);
    }

    /**
     * Get subtitles
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getSubtitles()
    {
        return $this->subtitles;
    }

    /**
     * Set name
     *
     * @param string $name
     *
     * @return Version
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }
}
