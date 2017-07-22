<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017 subtitulamos.tv
 */

namespace App\Controllers;

use \Psr\Http\Message\ResponseInterface;
use \Psr\Http\Message\RequestInterface;

use \Doctrine\ORM\EntityManager;

use \Slim\Views\Twig;
use \App\Entities\Episode;
use \App\Entities\Show;
use \App\Entities\Version;
use \App\Entities\Subtitle;
use \App\Services\Auth;
use \App\Services\Langs;

use Respect\Validation\Validator as v;

class UploadResyncController
{
    public function view($epId, ResponseInterface $response, Twig $twig, EntityManager $em)
    {
        $ep = $em->getRepository("App:Episode")->find($epId);
        if (!$ep) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        return $twig->render($response, 'upload_resync.twig', [
            'ep_full_name' => $ep->getFullName()
        ]);
    }

    public function do($epId, RequestInterface $request, ResponseInterface $response, EntityManager $em, \Slim\Router $router, Auth $auth)
    {
        $ep = $em->getRepository("App:Episode")->find($epId);
        if (!$ep) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        $langCode = $request->getParam("lang", "");
        $epName = $request->getParam("title", "");
        $versionName = $request->getParam("version", "");
        $comments = $request->getParam("comments", "");

        $errors = [];
        if (!Langs::existsCode($langCode)) {
            $errors[] = "Elige un idioma válido";
        }

        if (!v::notEmpty()->validate($versionName) || !v::notEmpty()->validate($comments)) {
            $errors[] = "Ni el nombre de la versión ni los comentarios pueden estar vacíos";
        }

        $uploadList = $request->getUploadedFiles();
        if (isset($uploadList['sub'])) {
            $srt = new \App\Services\Srt\SrtParser($uploadList['sub']->file);
            if (!$srt->isValid()) {
                $errors[] = $srt->getErrorDesc();
            }
        }

        $version = $em->createQuery("SELECT v FROM App:Version v WHERE v.episode = :ep AND v.name = :name")
            ->setParameter('ep', $ep)
            ->setParameter('name', $versionName)
            ->getOneOrNullResult();

        if ($version) {
            // Verify that no subtitle exists with this lang
            $subExists = $em->createQuery("SELECT COUNT(sb.id) FROM App:Subtitle sb WHERE sb.version = :v AND sb.lang = :lang")
                ->setParameter('v', $version->getId())
                ->setParameter('lang', (string)Langs::getLangId($langCode))
                ->getSingleScalarResult();

            if ($subExists) {
                $errors[] = "Ya existe un subtítulo en esta versión e idioma.";
            }
        }

        if (!empty($errors)) {
            // TODO: Properly present errors via flash messages or something alike
            return $response->withJson($errors, 412);
        }

        if (!$version) {
            $version = new Version();
            $version->setComments($comments);
            $version->setEpisode($ep);
            $version->setName($versionName);
            $version->setUser($auth->getUser());
            $em->persist($version);
        }

        $subtitle = new Subtitle();
        $subtitle->setLang(Langs::getLangId($langCode));
        $subtitle->setVersion($version);
        $subtitle->setUploadTime(new \DateTime());
        $subtitle->setDirectUpload(true);
        $subtitle->setResync(true);
        $subtitle->setProgress(100);
        $subtitle->setDownloads(0);
        $em->persist($subtitle);

        // Set sequences
        $sequences = $srt->getSequences();
        foreach ($sequences as $k => $sequence) {
            $sequence->setSubtitle($subtitle);
            $sequence->setAuthor($auth->getUser());

            $em->persist($sequence);
        }

        $em->flush();

        return $response
            ->withStatus(302)
            ->withHeader('Location', $router->pathFor("episode", ["id" => $ep->getId()]));
    }
}
