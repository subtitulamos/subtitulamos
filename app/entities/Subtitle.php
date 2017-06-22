<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017 subtitulamos.tv
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
     * @ORM\JoinColumn(name="version_id", referencedColumnName="id")
     */
    private $version;

    /**
     * @ORM\Column(type="integer")
     */
    private $lang;

    /**
     * @ORM\OneToOne(targetEntity="Pause", inversedBy="subtitle")
     */
    private $pause;
    
    /**
     * @ORM\OneToMany(targetEntity="Sequence", mappedBy="subtitle")
     */
    private $sequences;

    /**
     * @ORM\Column(type="boolean", name="is_direct_upload")
     */
    private $isDirectUpload;

    /**
     * @ORM\Column(type="datetime", name="upload_time", options={"default": 0})
     */
    private $uploadTime;

    /**
     * @ORM\Column(type="float")
     */
    private $progress;
    
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
    public function setDirectUpload($isDirectUpload)
    {
        $this->isDirectUpload = $isDirectUpload;

        return $this;
    }

    /**
     * Get isDirectUpload
     *
     * @return boolean
     */
    public function isDirectUpload()
    {
        return $this->isDirectUpload;
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
     * Set isDirectUpload
     *
     * @param boolean $isDirectUpload
     * @return Subtitle
     */
    public function setIsDirectUpload($isDirectUpload)
    {
        $this->isDirectUpload = $isDirectUpload;

        return $this;
    }

    /**
     * Get isDirectUpload
     *
     * @return boolean
     */
    public function getIsDirectUpload()
    {
        return $this->isDirectUpload;
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
}
