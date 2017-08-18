<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017 subtitulamos.tv
 */

namespace App\Entities;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="users")
 */
class User
{
    const MIN_PASSWORD_LENGTH = 8;
    const MIN_USERNAME_LENGTH = 3;
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=40, unique=true)
     */
    private $username;

    /**
     * @ORM\Column(type="string", length=60)
     */
    private $password;

    /**
     * @ORM\Column(type="string", length=80, unique=true)
     */
    private $email;

    /**
     * @ORM\OneToMany(targetEntity="Version", mappedBy="user")
     */
    private $versions;

    /**
     * @ORM\OneToMany(targetEntity="EpisodeComment", mappedBy="user")
     */
    private $epComments;

    /**
     * @ORM\OneToMany(targetEntity="SubtitleComment", mappedBy="user")
     */
    private $subComments;

    /**
     * @ORM\Column(type="boolean")
     */
    private $banned;

    /**
     * @ORM\Column(type="json_array")
     */
    private $roles;

    /**
     * @ORM\Column(type="datetime", name="registered_at", nullable=true)
     */
    private $registeredAt;

    /**
     * @ORM\Column(type="datetime", name="last_seen", nullable=true)
     */
    private $lastSeen;

    /**
     * @ORM\OneToOne(targetEntity="Ban", inversedBy="targetUser")
     * @ORM\JoinColumn(name="ban_id", referencedColumnName="id", nullable=true, onDelete="SET NULL")
     */
    private $ban;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->versions = new \Doctrine\Common\Collections\ArrayCollection();
        $this->pauses = new \Doctrine\Common\Collections\ArrayCollection();
        $this->subComments = new \Doctrine\Common\Collections\ArrayCollection();
        $this->epComments = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Set username
     *
     * @param string $username
     *
     * @return User
     */
    public function setUsername($username)
    {
        $this->username = $username;

        return $this;
    }

    /**
     * Get username
     *
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Set password
     *
     * @param string $password
     *
     * @return User
     */
    public function setPassword($password)
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Get password
     *
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * Set email
     *
     * @param string $email
     *
     * @return User
     */
    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Get email
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Add version
     *
     * @param \App\Entities\Version $version
     *
     * @return User
     */
    public function addVersion(\App\Entities\Version $version)
    {
        $this->versions[] = $version;

        return $this;
    }

    /**
     * Remove version
     *
     * @param \App\Entities\Version $version
     */
    public function removeVersion(\App\Entities\Version $version)
    {
        $this->versions->removeElement($version);
    }

    /**
     * Get versions
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getVersions()
    {
        return $this->versions;
    }

    /**
     * Set banned
     *
     * @param boolean $banned
     *
     * @return User
     */
    public function setBanned($banned)
    {
        $this->banned = $banned;

        return $this;
    }

    /**
     * Get banned
     *
     * @return boolean
     */
    public function getBanned()
    {
        return $this->banned;
    }

    /**
     * Set roles
     *
     * @param array $roles
     * @return User
     */
    public function setRoles($roles)
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * Get roles
     *
     * @return array
     */
    public function getRoles()
    {
        return $this->roles;
    }

    /**
     * Add epComments
     *
     * @param \App\Entities\EpisodeComment $epComments
     * @return User
     */
    public function addEpComment(\App\Entities\EpisodeComment $epComments)
    {
        $this->epComments[] = $epComments;

        return $this;
    }

    /**
     * Remove epComments
     *
     * @param \App\Entities\EpisodeComment $epComments
     */
    public function removeEpComment(\App\Entities\EpisodeComment $epComments)
    {
        $this->epComments->removeElement($epComments);
    }

    /**
     * Get epComments
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getEpComments()
    {
        return $this->epComments;
    }

    /**
     * Add subComments
     *
     * @param \App\Entities\SubtitleComment $subComments
     * @return User
     */
    public function addSubComment(\App\Entities\SubtitleComment $subComments)
    {
        $this->subComments[] = $subComments;

        return $this;
    }

    /**
     * Remove subComments
     *
     * @param \App\Entities\SubtitleComment $subComments
     */
    public function removeSubComment(\App\Entities\SubtitleComment $subComments)
    {
        $this->subComments->removeElement($subComments);
    }

    /**
     * Get subComments
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getSubComments()
    {
        return $this->subComments;
    }

    /**
     * Set id (exclusively for bots - users < 0)
     *
     * @return integer
     */
    public function setId(int $id)
    {
        if ($id >= 0) {
            return;
        }

        $this->id = $id;
    }

    /**
     * Set lastSeen
     *
     * @param \DateTime $lastSeen
     *
     * @return User
     */
    public function setLastSeen($lastSeen)
    {
        $this->lastSeen = $lastSeen;

        return $this;
    }

    /**
     * Get lastSeen
     *
     * @return \DateTime
     */
    public function getLastSeen()
    {
        return $this->lastSeen;
    }

    /**
     * Set registeredAt
     *
     * @param \DateTime $registeredAt
     *
     * @return User
     */
    public function setRegisteredAt($registeredAt)
    {
        $this->registeredAt = $registeredAt;

        return $this;
    }

    /**
     * Get registeredAt
     *
     * @return \DateTime
     */
    public function getRegisteredAt()
    {
        return $this->registeredAt;
    }

    /**
     * Set ban
     *
     * @param \App\Entities\Ban $ban
     *
     * @return User
     */
    public function setBan(\App\Entities\Ban $ban = null)
    {
        $this->ban = $ban;

        return $this;
    }

    /**
     * Get ban
     *
     * @return \App\Entities\Ban
     */
    public function getBan()
    {
        return $this->ban;
    }
}
