<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2021 subtitulamos.tv
 */

namespace App\Entities;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="favorites")
 */
class Favorite
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     */
    private User $user;

    /**
     * @ORM\ManyToOne(targetEntity="Episode")
     * @ORM\JoinColumn(name="episode_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private Episode $episode;

    public function __construct(User $user, Episode $episode)
    {
        $this->user = $user;
        $this->episode = $episode;
    }

    public function getEpisode(): Episode
    {
        return $this->episode;
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
