<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017-2018 subtitulamos.tv
 */

namespace App\Entities;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="subtitles")
 */
class Subtitle
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="Version", inversedBy="subtitles")
     * @ORM\JoinColumn(name="version_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private $version;

    /**
     * @ORM\Column(type="integer")
     */
    private $lang;

    /**
     * @ORM\OneToOne(targetEntity="Pause", inversedBy="subtitle")
     * @ORM\JoinColumn(name="pause_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $pause;

    /**
     * @ORM\OneToMany(targetEntity="Sequence", mappedBy="subtitle")
     */
    private $sequences;

    /**
     * @ORM\Column(type="boolean", name="direct_upload")
     */
    private $directUpload;

    /**
     * @ORM\Column(type="datetime", name="upload_time", options={"default": "CURRENT_TIMESTAMP"})
     */
    private $uploadTime;

    /**
     * @ORM\Column(type="datetime", name="complete_time", nullable=true)
     */
    private $completeTime;

    /**
     * @ORM\Column(type="datetime", name="edit_time", nullable=true)
     */
    private $editTime;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="last_edited_by", referencedColumnName="id", onDelete="SET NULL")
     */
    private $lastEditedBy;

    /**
     * @ORM\Column(type="float")
     */
    private $progress;

    /**
     * @ORM\OneToMany(targetEntity="SubtitleComment", mappedBy="subtitle")
     */
    private $comments;

    /**
     * @ORM\Column(type="integer")
     */
    private $downloads;

    /**
     * @ORM\Column(type="boolean")
     */
    private $resync;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->sequences = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Set lang
     *
     * @param integer $lang
     *
     * @return Subtitle
     */
    public function setLang($lang)
    {
        $this->lang = $lang;

        return $this;
    }

    /**
     * Get lang
     *
     * @return integer
     */
    public function getLang()
    {
        return $this->lang;
    }

    /**
     * Set version
     *
     * @param \App\Entities\Version $version
     *
     * @return Subtitle
     */
    public function setVersion(\App\Entities\Version $version = null)
    {
        $this->version = $version;

        return $this;
    }

    /**
     * Get version
     *
     * @return \App\Entities\Version
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Set pause
     *
     * @param \App\Entities\Pause $pause
     *
     * @return Subtitle
     */
    public function setPause(\App\Entities\Pause $pause = null)
    {
        $this->pause = $pause;

        return $this;
    }

    /**
     * Get pause
     *
     * @return \App\Entities\Pause
     */
    public function getPause()
    {
        return $this->pause;
    }

    /**
     * Add sequence
     *
     * @param \App\Entities\Sequence $sequence
     *
     * @return Subtitle
     */
    public function addSequence(\App\Entities\Sequence $sequence)
    {
        $this->sequences[] = $sequence;

        return $this;
    }

    /**
     * Remove sequence
     *
     * @param \App\Entities\Sequence $sequence
     */
    public function removeSequence(\App\Entities\Sequence $sequence)
    {
        $this->sequences->removeElement($sequence);
    }

    /**
     * Get sequences
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getSequences()
    {
        return $this->sequences;
    }

    /**
     * Set isDirectUpload
     *
     * @param boolean $isDirectUpload
     * @return Subtitle
     */
    public function setDirectUpload($directUpload)
    {
        $this->directUpload = $directUpload;

        return $this;
    }

    /**
     * Get isDirectUpload
     *
     * @return boolean
     */
    public function isDirectUpload()
    {
        return $this->directUpload;
    }

    /**
     * Set uploadTime
     *
     * @param \DateTime $uploadTime
     * @return Subtitle
     */
    public function setUploadTime($uploadTime)
    {
        $this->uploadTime = $uploadTime;

        return $this;
    }

    /**
     * Get uploadTime
     *
     * @return \DateTime
     */
    public function getUploadTime()
    {
        return $this->uploadTime;
    }

    /**
     * Set editTime
     *
     * @param \DateTime $editTime
     * @return Subtitle
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
     * Set lastEditedBy
     *
     * @param \App\Entities\User $lastEditedBy
     * @return Subtitle
     */
    public function setLastEditedBy(\App\Entities\User $lastEditedBy)
    {
        $this->lastEditedBy = $lastEditedBy;

        return $this;
    }

    /**
     * Get lastEditedBy
     *
     * @return \App\Entities\User
     */
    public function getLastEditedBy()
    {
        return $this->lastEditedBy;
    }

    /**
     * Set completeTime
     *
     * @param \DateTime $completeTime
     * @return Subtitle
     */
    public function setCompleteTime($completeTime)
    {
        $this->completeTime = $completeTime;

        return $this;
    }

    /**
     * Get completeTime
     *
     * @return \DateTime
     */
    public function getCompleteTime()
    {
        return $this->completeTime;
    }

    /**
     * Set progress
     *
     * @param float $progress
     * @return Subtitle
     */
    public function setProgress($progress)
    {
        $this->progress = $progress;

        return $this;
    }

    /**
     * Get progress
     *
     * @return float
     */
    public function getProgress()
    {
        return $this->progress;
    }

    /**
     * Get directUpload
     *
     * @return boolean
     */
    public function getDirectUpload()
    {
        return $this->directUpload;
    }

    /**
     * Add comments
     *
     * @param \App\Entities\SubtitleComment $comments
     * @return Subtitle
     */
    public function addComment(\App\Entities\SubtitleComment $comments)
    {
        $this->comments[] = $comments;

        return $this;
    }

    /**
     * Remove comments
     *
     * @param \App\Entities\SubtitleComment $comments
     */
    public function removeComment(\App\Entities\SubtitleComment $comments)
    {
        $this->comments->removeElement($comments);
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
     * Set downloads
     *
     * @param integer $downloads
     * @return Subtitle
     */
    public function setDownloads($downloads)
    {
        $this->downloads = $downloads;

        return $this;
    }

    /**
     * Get downloads
     *
     * @return integer
     */
    public function getDownloads()
    {
        return $this->downloads;
    }

    /**
     * Get resync
     *
     * @return boolean
     */
    public function getResync()
    {
        return $this->resync;
    }

    /**
     * Set resync
     *
     * @param boolean $resync
     * @return Subtitle
     */
    public function setResync($resync)
    {
        $this->resync = $resync;

        return $this;
    }
}
