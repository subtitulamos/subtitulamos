<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017 subtitulamos.tv
 */

namespace App\Services;

use \App\Entities\User;
use \App\Services\Utils;
use \Doctrine\ORM\EntityManager;

class Auth
{
    /**
     * User instance, if logged
     * @var User
     */
    private $user = null;

    /**
     * @var EntityManager
     */
    private $em;

    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * (re)creates a remember token and
     * updates the user instance with it
     * 
     * @return void
     */
    public function regenerateRememberToken()
    {
        $rememberTok = Utils::generateRandomString(40);
        $this->user->setRememberToken($rememberTok);
        $this->em->flush();
    }

    /**
     * Login a user by using a remember token
     *
     * @param string $token
     * @return boolean
     */
    public function logByToken(string $token)
    {
        $user = $this->em->getRepository('App:User')->findOneByRememberToken($token);
        if (!$user) {
            return false;
        }

        $this->log($user, true);
        return true;
    }

    /**
     * Load a user into the auth system
     * from a given ID
     *
     * @param string $id
     * @return boolean
     */
    public function loadUser(string $id)
    {
        if (!(int)$id) {
            return false;
        }

        $user = $this->em->getRepository('App:User')->find((int)$id);
        if (!$user) {
            return false;
        }

        $_SESSION['logged'] = true; // (just to make sure this is set)
        $this->user = $user;
        return true;
    }

    /**
     * Logs a user into the running session instance
     *
     * @param User $user
     * @param boolean $remember
     * @return void
     */
    public function log(User $user, $remember)
    {
        $_SESSION['logged'] = true;
        $_SESSION['uid'] = $user->getId();
        $this->user = $user;
        
        if ($remember) {
            $this->regenerateRememberToken();
        }
    }

    /**
     * Returns whether the currently authenticated user has
     * a role or not. If no user is authenticated this function 
     * will only return true if the role is ROLE_GUEST.
     *
     * @param string $role
     * @return boolean
     */
    public function hasRole($role)
    {
        return $role == 'ROLE_GUEST' ||
                ($this->user !== null && \in_array($role, $this->user->getRoles()));
    }

    /**
     * Whether there's an user that's been properly
     * logged in in this session
     *
     * @return boolean
     */
    public function isLogged()
    {
        return isset($_SESSION['logged']) && $_SESSION['logged'] === true;
    }

    public function logout()
    {
        if ($this->user) {
            $this->user->setRememberToken('');
            $this->em->flush();
        }

        unset($_SESSION['logged']);
        unset($_SESSION['uid']);
        \session_destroy();
    }

    public function getUser()
    {
        return $this->user;
    }
}
