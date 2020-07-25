<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

namespace App\Controllers;

use App\Entities\User;
use App\Services\Auth;
use Dflydev\FigCookies\FigRequestCookies;
use Dflydev\FigCookies\FigResponseCookies;
use Dflydev\FigCookies\SetCookie;
use Doctrine\ORM\EntityManager;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Respect\Validation\Validator as v;

class LoginController
{
    const MINIMUM_PASSWORD_LENGTH = 8;
    const MINIMUM_USER_LENGTH = 4;

    protected $container;

    // constructor receives container instance
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Attempt to login a user
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    public function login(ServerRequestInterface $request, ResponseInterface $response, EntityManager $em, Auth $auth)
    {
        $username = $request->getParam('username', '');
        $password = $request->getParam('password', '');
        $remember = $request->getParam('remember', '') == 'true';

        if (!$username || !$password) {
            return $response->withStatus(400);
        }

        if (\filter_var($username, \FILTER_VALIDATE_EMAIL)) {
            // Logging in with email
            $user = $em->getRepository('App:User')->findOneByEmail($username);
            $loginName = 'Correo electrónico';
        } else {
            $user = $em->getRepository('App:User')->findOneByUsername($username);
            $loginName = 'Usuario';
        }

        if (!$user || !\password_verify($password, $user->getPassword())) {
            return $response->withJson([$loginName.' o contraseña incorrectos'], 403);
        }

        $token = $auth->log($user, $remember);
        if ($remember) {
            $response = FigResponseCookies::set($response, SetCookie::create('remember')->withPath('/')->withValue($token)->rememberForever());
        }

        return $response->withStatus(200);
    }

    public function register(ServerRequestInterface $request, ResponseInterface $response, EntityManager $em, Auth $auth)
    {
        $username = $request->getParam('username', '');
        $password = $request->getParam('password', '');
        $password_confirmation = $request->getParam('password_confirmation', '');
        $email = $request->getParam('email', '');
        $terms = $request->getParam('terms', false) == 'true';

        $errors = [];
        if (!$terms) {
            $errors[] = ['terms' => 'Debes aceptar los términos y condiciones'];
        }

        if (!v::alnum('_')->noWhitespace()->length(3, 24)->validate($username)) {
            $errors[] = ['username' => 'El nombre de usuario debe tener entre 3 y 24 caracteres y solo puede contener letras, números y guiones bajos'];
        } elseif ($em->getRepository('App:User')->findByUsername($username) != null) {
            $errors[] = ['username' => 'El nombre de usuario ya está en uso'];
        }

        if (!v::email()->validate($email)) {
            $errors[] = ['email' => 'El correo electrónico no tiene un formato válido'];
        } elseif ($em->getRepository('App:User')->findByEmail($email) != null) {
            $errors[] = ['email' => 'El correo electrónico ya está en uso'];
        }

        if (!v::length(8, 80)->validate($password)) {
            $errors[] = ['password' => 'La contraseña debe tener 8 caracteres como mínimo'];
        } elseif ($password != $password_confirmation) {
            $errors[] = ['password_confirmation' => 'Las contraseñas no coinciden'];
        }

        if (!empty($errors)) {
            return $response->withJson($errors, 400);
        }

        // Onwards with registration!
        $user = new User();
        $user->setUsername($username);
        $user->setPassword(\password_hash($password, \PASSWORD_BCRYPT, ['cost' => 13]));
        $user->setEmail($email);
        $user->setBanned(false);
        $user->setRoles(['ROLE_USER']);
        $user->setRegisteredAt(new \DateTime());

        $em->persist($user);
        $em->flush();

        $auth->log($user, false);
        return $response->withStatus(200);
    }

    public function logout($request, $response, Auth $auth)
    {
        if (ini_get('session.use_cookies')) {
            $response = FigResponseCookies::expire($response, session_name());
        }

        $rememberCookie = FigRequestCookies::get($request, 'remember', '');
        $auth->logout($rememberCookie->getValue());

        $response = FigResponseCookies::expire($response, 'remember');
        return $response->withHeader('Location', '/');
    }
}
