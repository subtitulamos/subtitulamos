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
     * @param boolean $flush Or not to flush
     * @return void
     */
    public function regenerateRememberToken(bool $flush)
    {
        $rememberTok = Utils::generateRandomString(40);
        $this->user->setRememberToken($rememberTok);

        if ($flush) {
            $this->em->flush();
        }
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
        $this->user->setLastSeen(new \DateTime());
        $this->em->flush();

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
        $this->user->setLastSeen(new \DateTime());
        if ($remember) {
            $this->regenerateRememberToken(false);
        }

        $this->em->flush();
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
        return $role == 'ROLE_GUEST' || ($this->user !== null && \in_array($role, $this->user->getRoles()));
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

    /*******************************************************************
                            FLASH NOTIFICATIONS
     *******************************************************************/
    public function addFlash($type, $msg)
    {
        if (!isset($_SESSION['flash'])) {
            $_SESSION['flash'] = [];
        }

        if (!isset($_SESSION['flash'][$type])) {
            $_SESSION['flash'][$type] = [];
        }

        $_SESSION['flash'][$type][] = $msg;
    }

    public function hasFlashByType($type)
    {
        return isset($_SESSION['flash']) && isset($_SESSION['flash'][$type]) && count($_SESSION['flash'][$type]) > 0;
    }

    public function getFlashByType($type = "")
    {
        if (!isset($_SESSION['flash'])) {
            return [];
        }

        $ret = isset($_SESSION['flash'][$type]) ? $_SESSION['flash'][$type] : [];
        if (!empty($ret)) {
            unset($_SESSION['flash'][$type]);
        }

        return $ret;
    }

    public function getAllFlash($type = "")
    {
        $ret = isset($_SESSION['flash']) ? $_SESSION['flash'] : [];
        if (!empty($ret)) {
            unset($_SESSION['flash']);
        }

        return $ret;
    }

    /**
     * Yield an anonymous class to feed to Twig that determines
     * possible interactions with this class
     *
     * @return Anonymous
     */
    public function getTwigInterface()
    {
        return new class($this) {
            public function __construct(&$auth)
            {
                $this->auth = $auth;
            }

            public function logged()
            {
                return $this->auth->isLogged();
            }

            public function has_role($role)
            {
                return $this->auth->hasRole($role);
            }

            public function user()
            {
                return $this->auth->getUser();
            }

            public function flash()
            {
                $auth = $this->auth;
                return new class($auth) {
                    public function __construct(&$auth)
                    {
                        $this->auth = $auth;
                    }

                    public function has($type)
                    {
                        return $this->auth->hasFlashByType($type);
                    }

                    public function successes()
                    {
                        return $this->auth->getFlashByType('success');
                    }

                    public function errors()
                    {
                        return $this->auth->getFlashByType('error');
                    }

                    public function notices()
                    {
                        return $this->auth->getFlashByType('notice');
                    }

                    public function warnings()
                    {
                        return $this->auth->getFlashByType('warning');
                    }

                    public function all()
                    {
                        return $this->auth->getAllFlash();
                    }
                };
            }
        };
    }
}
