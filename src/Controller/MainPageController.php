<?php

namespace App\Controller;

use App\Repository\EditionRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MainPageController extends FOGController {
    
    #[Route("/", name: "index", methods: ['GET'])]
    public function index() : Response{
        $edition = $this->FogParams->getCurrentEdition();//$editionRepository->findOneBy(['annee' => $this->getParameter('current_edition')]);
        $homeText = '';
        if ($edition != null) {
            $homeText = $edition->getHomeText();
        }
        return $this->render('oeilglauque/index.html.twig', [
            'homeText' => $homeText
        ]);
    }
}