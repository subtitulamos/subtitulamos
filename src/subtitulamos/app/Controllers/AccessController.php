<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2021 subtitulamos.tv
 */

namespace App\Controllers;

use App\Entities\User;
use App\Services\Auth;
use App\Services\Utils;
use Dflydev\FigCookies\FigRequestCookies;
use Dflydev\FigCookies\FigResponseCookies;
use Dflydev\FigCookies\SetCookie;
use Doctrine\ORM\EntityManager;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Respect\Validation\Validator as v;

class AccessController
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
        $body = $request->getParsedBody();
        $username = $body['username'] ?? '';
        $password = $body['password'] ?? '';
        $remember = true;

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

        if (!$user || !$user->checkPassword($password)) {
            return Utils::jsonResponse($response, [$loginName.' o contraseña incorrectos'])->withStatus(403);
        }

        $token = $auth->log($user, $remember);
        if ($remember) {
            $response = FigResponseCookies::set($response, SetCookie::create('remember')->withPath('/')->withValue($token)->rememberForever());
        }

        return $response->withStatus(200);
    }

    public function register(ServerRequestInterface $request, ResponseInterface $response, EntityManager $em, Auth $auth)
    {
        $body = $request->getParsedBody();
        $username = $body['username'] ?? '';
        $password = $body['password'] ?? '';
        $password_confirmation = $body['password-confirmation'] ?? '';
        $email = $body['email'] ?? '';
        $terms = ($body['terms'] ?? false) == 'on';

        $errors = [];
        if (!$terms) {
            $errors[] = ['terms', 'Debes aceptar los términos y condiciones'];
        }

        if (!v::alnum('_')->noWhitespace()->length(3, 24)->validate($username)) {
            $errors[] = ['username', 'El nombre de usuario debe tener entre 3 y 24 caracteres y solo puede contener letras, números y guiones bajos'];
        } elseif ($em->getRepository('App:User')->findByUsername($username) != null) {
            $errors[] = ['username', 'El nombre de usuario ya está en uso'];
        }

        if (!v::email()->validate($email)) {
            $errors[] = ['email', 'El correo electrónico no tiene un formato válido'];
        } elseif ($em->getRepository('App:User')->findByEmail($email) != null) {
            $errors[] = ['email', 'El correo electrónico ya está en uso'];
        }

        if (!v::length(8, 80)->validate($password)) {
            $errors[] = ['password', 'La contraseña debe tener 8 caracteres como mínimo'];
        } elseif ($password != $password_confirmation) {
            $errors[] = ['password_confirmation', 'Las contraseñas no coinciden'];
        }

        if (!empty($errors)) {
            return Utils::jsonResponse($response, $errors)->withStatus(400);
        }

        // Onwards with registration!
        $user = new User();
        $user->setUsername($username);
        $user->setPassword($password);
        $user->setEmail($email);
        $user->setBanned(false);
        $user->setRoles(['ROLE_USER']);
        $user->setRegisteredAt(new \DateTime());

        $em->persist($user);
        $em->flush();

        $auth->log($user, false);
        return $response->withStatus(200);
    }

    public function logout(ServerRequestInterface $request, $response, Auth $auth)
    {
        if (ini_get('session.use_cookies')) {
            $response = FigResponseCookies::expire($response, session_name());
        }

        $rememberCookie = FigRequestCookies::get($request, 'remember', '');
        $auth->logout($rememberCookie->getValue());

        $response = FigResponseCookies::expire($response, 'remember');

        $params = $request->getQueryParams();
        $returnUrl = $params['return-path'] ?? '/';
        return $response->withHeader('Location', '/'.mb_substr($returnUrl, 1));
    }
}
