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

use Respect\Validation\Validator as v;

class UploadController
{
    public function view(ResponseInterface $response, Twig $twig, EntityManager $em)
    {
        $shows = $em->createQuery("SELECT sw FROM App:Show sw ORDER BY sw.name ASC")->getResult();

        return $twig->render($response, 'upload.twig', [
            'shows' => $shows
        ]);
    }

    public function do(RequestInterface $request, ResponseInterface $response, EntityManager $em, \Slim\Router $router, Auth $auth, \Elasticsearch\Client $client)
    {
        $showId = $request->getParam("showId", 0);
        $season = $request->getParam("season", -1);
        $epNumber = $request->getParam("episode", -1);
        $langCode = $request->getParam("lang", "");
        $epName = strip_tags(trim($request->getParam("title", "")));
        $versionName = strip_tags(trim($request->getParam("version", "")));
        $comments = strip_tags(trim($request->getParam("comments", "")));

        $errors = [];
        if (!\App\Services\Langs::existsCode($langCode)) {
            $errors[] = "Elige un idioma válido";
        }

        if (!v::numeric()->positive()->between(0, 99)->validate($season) || !v::numeric()->positive()->between(0, 99)->validate($epNumber)) {
            $errors[] = "La temporada o episodio deben estar en el rango [0, 99]";
        }

        if (!v::notEmpty()->validate($epName)) {
            $errors[] = "El nombre del episodio no puede estar vacío";
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

        $show = null;
        if ($showId != "NEW") {
            if (!v::numeric()->positive()->validate($showId)) {
                $errors[] = "Elige una serie de la lista";
            }
            else {
                $show = $em->getRepository('App:Show')->find((int)$showId);
                if (!$show) {
                    $errors[] = "La serie que has elegido no existe";
                }
            }

            if (empty($errors)) {
                // Since the show already exists, we have to make sure that
                // the an episode with this number doesn't already exist, too
                $e = $em->createQuery("SELECT e FROM App:Episode e WHERE e.show = :showid AND e.season = :season AND e.number = :num")
                    ->setParameter('showid', $show->getId())
                    ->setParameter('season', $season)
                    ->setParameter('num', $epNumber)
                    ->getResult();

                if ($e != null) {
                    $errors[] = sprintf("El episodio %dx%d de la serie %s ya existe", $season, $epNumber, $show->getName());
                }
            }
        }
        else {
            // Create a new show!
            $newShowName = $request->getParam("new-show");
            if (v::notEmpty()->length(1, 100)->validate($newShowName)) {
                if ($em->getRepository("App:Show")->findByName($newShowName)) {
                    $errors[] = "La serie no se ha podido crear puesto que ya existe. Por favor, selecciónala en el desplegable";
                }
                else {
                    $show = new Show();
                    $show->setName($newShowName);
                    $show->setZeroTolerance(false);
                    $em->persist($show);

                    /* TODO: Log */
                }
            }
            else {
                $errors[] = "El nombre de la serie no puede estar vacío";
            }
        }

        if (!empty($errors)) {
            // TODO: Properly present errors via flash messages or something alike
            return $response->withJson($errors, 412);
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
        $sequences = $srt->getSequences();
        foreach ($sequences as $k => $sequence) {
            $sequence->setSubtitle($subtitle);
            $sequence->setAuthor($auth->getUser());

            $em->persist($sequence);
        }

        $em->flush();

        // Index the new show if it was just created
        if (isset($newShowName)) {
            $client->index([
                'index' => ELASTICSEARCH_NAMESPACE . '_shows',
                'type' => 'show',
                'id' => $show->getId(),
                'body' => [
                    'name' => $show->getName()
                ]
            ]);
        }

        return $response
            ->withStatus(302)
            ->withHeader('Location', $router->pathFor("episode", ["id" => $episode->getId()]));
    }
}
