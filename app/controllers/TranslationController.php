<?php

/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017 subtitulamos.tv
 */

// TODO: Handle zero width space (\U200B), special "", single "..." utf8 codepoint, etc

namespace App\Controllers;
use \Psr\Container\ContainerInterface;
use \Doctrine\ORM\EntityManager;
use \Slim\Views\Twig;

class TranslationController
{
    /**
     * Number of sequences that are displayed per page
     * @var int
     */
    const SEQUENCES_PER_PAGE = 20;
    
    protected $container;

    // constructor receives container instance
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function view($id, $request, $response, EntityManager $em, Twig $twig)
    {
        $sub = $em->createQuery("SELECT s, v, e FROM App:Subtitle s JOIN s.version v JOIN v.episode e WHERE e.id = :id")
                   ->setParameter("id", $id)
                   ->getOneOrNullResult();

        if(empty($sub)) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }
        
        
        $seqCount = $em->createQuery("SELECT COUNT(s.id) FROM App:Sequence s WHERE s.subtitle = :sub")->setParameter("sub", $sub)->getSingleScalarResult();

        return $twig->render($response, 'translate.twig', [
            'sub' => $sub,
            'episode' => $sub->getVersion()->getEpisode(),
            'page_count' => ceil($seqCount/self::SEQUENCES_PER_PAGE)
        ]);
    }

    public function listSequences($id, $page, $request, $response, EntityManager $em) {
        $page = max((int)$page, 1);

        $sequences = $em->createQuery("SELECT s FROM App:Sequence s WHERE s.subtitle = :id")
                        ->setParameter("id", $id)
                        ->setFirstResult(($page - 1) * self::SEQUENCES_PER_PAGE)
                        ->setMaxResults(self::SEQUENCES_PER_PAGE)
                        ->getResult();

        return $response->withJSON($sequences);
    }
}