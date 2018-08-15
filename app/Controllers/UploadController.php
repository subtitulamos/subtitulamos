<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017-2018 subtitulamos.tv
 */

namespace App\Controllers;

use App\Entities\Episode;
use App\Entities\Show;

use App\Entities\Subtitle;

use App\Entities\Version;
use App\Services\Auth;
use App\Services\Srt\SrtParser;
use Doctrine\ORM\EntityManager;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Respect\Validation\Validator as v;

use Slim\Views\Twig;

class UploadController
{
    public function view(ResponseInterface $response, Twig $twig, EntityManager $em)
    {
        $shows = $em->createQuery('SELECT sw FROM App:Show sw ORDER BY sw.name ASC')->getResult();

        return $twig->render($response, 'upload.twig', [
            'shows' => $shows
        ]);
    }

    public function do(RequestInterface $request, ResponseInterface $response, EntityManager $em, \Slim\Router $router, Auth $auth, \Elasticsearch\Client $client)
    {
        $param = $request->getParsedBody();

        $showId = isset($param['show-id']) ? $param['show-id'] : -1;
        $season = isset($param['season']) ? $param['season'] : -1;
        $epNumber = isset($param['episode']) ? $param['episode'] : -1;
        $langCode = isset($param['lang']) ? $param['lang'] : '';
        $epName = isset($param['title']) ? strip_tags(trim($param['title'])) : '';
        $versionName = isset($param['version']) ? strip_tags(trim($param['version'])) : '';
        $comments = isset($param['comments']) ? strip_tags(trim($param['comments'])) : '';

        $errors = [];
        if (!\App\Services\Langs::existsCode($langCode)) {
            $errors[] = ['lang', 'Elige un idioma válido'];
        }

        if (!v::notEmpty()->validate($epName)) {
            $errors[] = ['name', 'El nombre del episodio no puede estar vacío'];
        } elseif (!v::numeric()->between(0, 99)->validate($season) || !v::numeric()->between(0, 99)->validate($epNumber)) {
            $errors[] = ['name', 'Tanto la temporada como el número de episodio deben estar en el rango [0, 99]'];
        }

        if (!v::notEmpty()->validate($versionName)) {
            $errors[] = ['version', 'El nombre de la versión no puede estar vacío'];
        } elseif (!v::length(1, 60)->validate($versionName)) {
            $errors[] = ['version', 'El máximo tamaño de este campo son 60 caracteres'];
        }

        if (!v::notEmpty()->validate($comments)) {
            $errors[] = ['comments', 'Los comentarios no pueden estar vacíos'];
        } elseif (!v::length(1, 100)->validate($comments)) {
            $errors[] = ['comments', 'El comentario no puede superar los 100 caracteres'];
        }

        $uploadList = $request->getUploadedFiles();
        if (isset($uploadList['sub'])) {
            $srtParser = new SrtParser();
            $isOk = $srtParser->parseFile($uploadList['sub']->file, [
                'allow_long_lines' => true,
                'allow_special_tags' => false
            ]);

            if (!$isOk) {
                $errors[] = ['sub', $srtParser->getErrorDesc()];
            }
        }

        $show = null;
        if ($showId != 'NEW') {
            if (!v::numeric()->positive()->validate($showId)) {
                $errors[] = ['show-id', 'Elige una serie de la lista'];
            } else {
                $show = $em->getRepository('App:Show')->find((int)$showId);
                if (!$show) {
                    $errors[] = ['show-id', 'La serie que has elegido no existe'];
                }
            }

            if (empty($errors)) {
                // Since the show already exists, we have to make sure that
                // the an episode with this number doesn't already exist, too
                $e = $em->createQuery('SELECT e FROM App:Episode e WHERE e.show = :showid AND e.season = :season AND e.number = :num')
                    ->setParameter('showid', $show->getId())
                    ->setParameter('season', $season)
                    ->setParameter('num', $epNumber)
                    ->getResult();

                if ($e != null) {
                    $errors[] = ['name', sprintf('El episodio %dx%d de la serie %s ya existe', $season, $epNumber, $show->getName())];
                }
            }
        } else {
            // Create a new show!
            $newShowName = trim($request->getParam('new-show'));
            if (v::notEmpty()->length(1, 100)->validate($newShowName)) {
                if ($em->getRepository('App:Show')->findByName($newShowName)) {
                    $errors[] = ['new-show', 'Esta serie ya existe.'];
                } else {
                    $show = new Show();
                    $show->setName($newShowName);
                    $show->setZeroTolerance(false);
                    $em->persist($show);

                    /* TODO: Log */
                }
            } else {
                $errors[] = ['new-show', 'El nombre no puede estar vacío'];
            }
        }

        if (!empty($errors)) {
            return $response->withJson($errors, 400);
        }

        $episode = new Episode();
        $episode->setSeason((int)$season);
        $episode->setNumber((int)$epNumber);
        $episode->setDownloads(0);
        $episode->setName($epName);
        $episode->setShow($show);

        $version = new Version();
        $version->setComments($comments);
        $version->setEpisode($episode);
        $version->setName($versionName);
        $version->setUser($auth->getUser());

        $subtitle = new Subtitle();
        $subtitle->setLang(\App\Services\Langs::getLangId($langCode));
        $subtitle->setVersion($version);
        $subtitle->setUploadTime(new \DateTime());
        $subtitle->setDirectUpload(true);
        $subtitle->setResync(false);
        $subtitle->setProgress(100);
        $subtitle->setDownloads(0);

        // Persist
        $em->persist($episode);
        $em->persist($version);
        $em->persist($subtitle);
        $sequences = $srtParser->getSequences();
        foreach ($sequences as $k => $sequence) {
            $sequence->setSubtitle($subtitle);
            $sequence->setAuthor($auth->getUser());

            $em->persist($sequence);
        }

        $em->flush();

        // Index the new show if it was just created
        if (isset($newShowName)) {
            $client->index([
                'index' => ELASTICSEARCH_NAMESPACE.'_shows',
                'type' => 'show',
                'id' => $show->getId(),
                'body' => [
                    'name' => $show->getName()
                ]
            ]);
        }

        $response->getBody()->write($router->pathFor('episode', ['id' => $episode->getId()]));
        return $response->withStatus(200);
    }
}
