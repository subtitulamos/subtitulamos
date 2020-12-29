<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

namespace App\Controllers;

use App\Entities\EventLog;
use App\Entities\Subtitle;

use App\Entities\Version;
use App\Services\Auth;
use App\Services\Langs;
use App\Services\Srt\SrtParser;
use App\Services\UrlHelper;
use App\Services\Utils;
use Doctrine\ORM\EntityManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Respect\Validation\Validator as v;

use Slim\Views\Twig;

class UploadResyncController
{
    public function view($epId, ServerRequestInterface $request, ResponseInterface $response, Twig $twig, EntityManager $em)
    {
        $ep = $em->getRepository('App:Episode')->find($epId);
        if (!$ep) {
            throw new \Slim\Exception\HttpNotFoundException($request);
        }

        $show = $ep->getShow();
        return $twig->render($response, 'upload_resync.twig', [
            'show_id' => $show->getId(),
            'show_name' => $show->getName(),
            'ep_name' => $ep->getNameAndSeason()
        ]);
    }

    public function do($epId, ServerRequestInterface $request, ResponseInterface $response, EntityManager $em, UrlHelper $urlHelper, Auth $auth)
    {
        $ep = $em->getRepository('App:Episode')->find($epId);
        if (!$ep) {
            throw new \Slim\Exception\HttpNotFoundException($request);
        }

        $body = $request->getParsedBody();
        $langCode = $body['lang'] ?? '';
        $versionName = trim(strip_tags($body['version']?? ''));
        $comments = trim(strip_tags($body['comments'] ?? ''));

        $errors = [];
        if (!Langs::existsCode($langCode)) {
            $errors[] = ['lang', 'Elige un idioma válido'];
        }

        if (!v::notEmpty()->validate($versionName)) {
            $errors[] = ['version', 'El nombre de la versión no puede estar vacío'];
        }

        if (!v::notEmpty()->validate($comments)) {
            $errors[] = ['comments', 'Los comentarios no pueden estar vacíos'];
        } elseif (!v::length(1, 150)->validate($comments)) {
            $errors[] = ['comments', 'Los comentarios no pueden superar los 150 caracteres'];
        }

        $uploadList = $request->getUploadedFiles();
        if (isset($uploadList['sub'])) {
            $srtParser = new SrtParser();
            $isOk = $srtParser->parseFile($uploadList['sub'], [
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
            return Utils::jsonResponse($response, $errors)->withStatus(400);
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

        $event = new EventLog($auth->getUser(), new \DateTime(), sprintf('Nueva resincronización creada ([[subtitle:%d]])', $subtitle->getId()));
        $em->persist($event);
        $em->flush();

        $response->getBody()->write($urlHelper->pathFor('episode', ['id' => $ep->getId()]));
        return $response->withStatus(200);
    }
}
