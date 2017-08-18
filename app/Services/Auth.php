<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017 subtitulamos.tv
 */

namespace App\Services;

use \App\Entities\User;
use \App\Entities\RememberToken;
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
     * Ccreates a new remember token, bound to user
     *
     * @param \App\Entities\User $u
     * @return string
     */
    public function remember(User $u)
    {
        $newToken = Utils::generateRandomString(40);

        $tok = new RememberToken();
        $tok->setToken($newToken);
        $tok->setUser($u);
        $tok->setCreatedAt(new \DateTime());
        $this->em->persist($tok);

        return $newToken;
    }

    /**
     * Login a user by using a remember token
     *
     * @param string $token
     * @return string
     */
    public function logByToken(string $token)
    {
        $tok = $this->em->getRepository('App:RememberToken')->find($token);
        if (!$tok) {
            return '';
        }

        $this->em->remove($tok); // One time use tokens!

        if((new \DateTime())->getTimestamp() - $tok->getCreatedAt()->getTimestamp() > RememberToken::MAX_DURATION) {
            // Token expired, we cannot use it to login
            $this->em->flush();
            return '';
        }

        $newToken = $this->log($tok->getUser(), true);
        return $newToken;
    }

    /**
     * Load a user into the auth system
     * from a given ID
     *
     * @param string $id
     * @return bool
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

        $this->log($user, false);
        return true;
    }

    /**
     * Logs a user into the running session instance
     *
     * @param User $user
     * @param bool $setRememberToken
     * @return void
     */
    public function log(User $user, bool $setRememberToken)
    {
        $_SESSION['logged'] = true;
        $_SESSION['uid'] = $user->getId();
        $user->setLastSeen(new \DateTime());

        $ban = $user->getBan();
        if ($ban && $ban->isExpired()) {
            # Ban is already over, remove
            $user->setBan(null);
        }

        $token = ($setRememberToken) ? $this->remember($user) : "";
        $this->user = $user;
        $this->em->flush();

        return $token;
    }

    /**
     * Returns whether the currently authenticated user has
     * a role or not. If no user is authenticated this function
     * will only return true if the role is ROLE_GUEST.
     *
     * @param string $role
     * @return bool
     */
    public function hasRole($role)
    {
        return $role == 'ROLE_GUEST' || ($this->user !== null && !$this->user->getBan() && \in_array($role, $this->user->getRoles()));
    }

    /**
     * Whether there's an user that's been properly
     * logged in in this session
     *
     * @return bool
     */
    public function isLogged()
    {
        return isset($_SESSION['logged']) && $_SESSION['logged'] === true;
    }

    public function logout(string $rememberCookie)
    {
        if($rememberCookie) {
            $tok = $this->em->getRepository('App:RememberToken')->find($rememberCookie);
            if($tok) {
                $this->em->remove($tok);
                $this->em->flush();
            }
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
        return new class ($this)
        {
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
                return new class ($auth)
                {
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
