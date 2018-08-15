<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017 subtitulamos.tv
 */

namespace App\Controllers;

use App\Entities\Episode;
use App\Entities\Show;

use App\Entities\Subtitle;

use App\Entities\Version;
use App\Services\Auth;
use App\Services\Langs;
use App\Services\Srt\SrtParser;
use Doctrine\ORM\EntityManager;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Respect\Validation\Validator as v;

use Slim\Views\Twig;

class UploadResyncController
{
    public function view($epId, ResponseInterface $response, Twig $twig, EntityManager $em)
    {
        $ep = $em->getRepository('App:Episode')->find($epId);
        if (!$ep) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        $show = $ep->getShow();
        return $twig->render($response, 'upload_resync.twig', [
            'show_name' => $show->getName(),
            'ep_name' => $ep->getNameAndSeason()
        ]);
    }

    public function do($epId, RequestInterface $request, ResponseInterface $response, EntityManager $em, \Slim\Router $router, Auth $auth)
    {
        $ep = $em->getRepository('App:Episode')->find($epId);
        if (!$ep) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        $langCode = $request->getParam('lang', '');
        $versionName = trim(strip_tags($request->getParam('version', '')));
        $comments = trim(strip_tags($request->getParam('comments', '')));

        $errors = [];
        if (!Langs::existsCode($langCode)) {
            $errors[] = ['lang', 'Elige un idioma válido.'];
        }

        if (!v::notEmpty()->validate($versionName)) {
            $errors[] = ['version', 'El nombre de la versión no puede estar vacío.'];
        }

        if (!v::notEmpty()->validate($comments)) {
            $errors[] = ['comments', 'Los comentarios no pueden estar vacíos.'];
        }

        $uploadList = $request->getUploadedFiles();
        if (isset($uploadList['sub'])) {
            $srtParser = new SrtParser();
            $isOk = $srtParser->parseFile($uploadList['sub']->file, [
                'allow_long_lines' => false,
                'allow_special_tags' => false
            ]);

            if (!$isOk) {
                $errors[] = ['sub', $srtParser->getErrorDesc()];
            }
        }

        $version = $em->createQuery('SELECT v FROM App:Version v WHERE v.episode = :ep AND v.name = :name')
            ->setParameter('ep', $ep)
            ->setParameter('name', $versionName)
            ->getOneOrNullResult();

        if ($version) {
            // Verify that no subtitle exists with this lang
            $subExists = $em->createQuery('SELECT COUNT(sb.id) FROM App:Subtitle sb WHERE sb.version = :v AND sb.lang = :lang')
                ->setParameter('v', $version->getId())
                ->setParameter('lang', (string)Langs::getLangId($langCode))
                ->getSingleScalarResult();

            if ($subExists) {
                $errors[] = ['version', 'Ya existe un subtítulo en esta versión e idioma.'];
            }
        }

        if (!empty($errors)) {
            return $response->withJson($errors, 400);
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
        $sequences = $srtParser->getSequences();
        foreach ($sequences as $k => $sequence) {
            $sequence->setSubtitle($subtitle);
            $sequence->setAuthor($auth->getUser());

            $em->persist($sequence);
        }

        $em->flush();

        $response->getBody()->write($router->pathFor('episode', ['id' => $ep->getId()]));
        return $response->withStatus(200);
    }
}
