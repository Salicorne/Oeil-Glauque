<?php

namespace App\Controller;

use App\Entity\BoardGameReservation;
use App\Entity\LocalReservation;
use App\Entity\Feature;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Edition;
use App\Entity\GameSlot;
use App\Entity\Game;
use App\Entity\News;
use App\Entity\User;
use App\Form\EditionType;
use App\Form\NewsType;
use App\Repository\EditionRepository;
use App\Repository\FeatureRepository;
use App\Service\FOGMailerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mime\Address;

class AdminController extends FOGController {

    /****************************************
     *      Interface d'administration      *
     ****************************************/

    #[Route("/admin", name: "admin", methods: ['GET'])]
    public function admin(FeatureRepository $featureRepository): Response
    {
        $newsState = $featureRepository->find(6)->getState();
        return $this->render('oeilglauque/admin.html.twig', [
            'newsState' => $newsState
        ]);
    }


    /**********************************
     *      Gestion des éditions      *
     **********************************/


    #[Route("/admin/editions", name: "admin_editions", methods: ['GET'])]
    public function editionsAdmin(EditionRepository $editionRepository): Response
    {
        $editions = array_reverse($editionRepository->findAll());
        return $this->render('oeilglauque/admin/editions.html.twig', [
            'editions' => $editions
        ]);
    }

    #[Route("/admin/editions/updateEdition/{edition}", name: "updateEdition")]
    public function updateEdition(Request $request, $edition, EntityManagerInterface $doctrine): Response
    {
        $editionval = $doctrine->getRepository(Edition::class)->find($edition);
        if(!$editionval) {
            throw $this->createNotFoundException(
                'Aucune édition n\'a pour id '.$edition
            );
        }
        if($request->query->get('dates') != "") {
            $editionval->setDates($request->query->get('dates'));
            $editionval->setHomeText($request->query->get('homeText'));
            $doctrine->flush();
            $this->addFlash('success', "L'édition " . $editionval->getAnnee() . " a bien été mise à jour.");
        }
        return $this->redirectToRoute('admin_editions');
    }

    #[Route("/admin/editions/updateGameSlot/{slot}", name: "updateGameSlot")]
    public function updateGameSlot(Request $request, $slot, EntityManagerInterface $doctrine): Response
    {
        $slotval = $doctrine->getRepository(GameSlot::class)->find($slot);
        if (!$slotval) {
            throw $this->createNotFoundException(
                'Aucun slot n\'a pour id '.$slot
            );
        }
        if($request->query->get('text') != "") {
            $slotval->setText($request->query->get('text'));
            $slotval->setMaxGames($request->query->get('maxGames'));
            $doctrine->flush();
            $this->addFlash('success', "Le slot a bien été mis à jour. ");
        }
        return $this->redirectToRoute('admin_editions');
    }

    #[Route("/admin/editions/addGameSlot/{edition}", name: "addGameSlot")]
    public function addGameSlot(Request $request, $edition, EntityManagerInterface $doctrine): Response
    {
        if($request->query->get('text') != "") {
            $slot = new GameSlot();
            $slot->setText($request->query->get('text'));
            $slot->setMaxGames($request->query->get('maxGames'));
            $edition = $doctrine->getRepository(Edition::class)->find($edition);
            if (!$edition) {
                throw $this->createNotFoundException(
                    'Aucune édition n\'a pour id '.$edition
                );
            }
            $slot->setEdition($edition);
            $doctrine->persist($slot);
            $doctrine->flush();
            $this->addFlash('success', "Le slot a bien été ajouté. ");
        }

        return $this->redirectToRoute('admin_editions');
    }

    /******************************
     *      Gestion des news      *
     ******************************/

    #[Route("/admin/news/rediger", name: "writeNews")]
    public function writeNews(Request $request, EntityManagerInterface $doctrine): Response
    {
        $news = new News();
        $form = $this->createForm(NewsType::class, $news);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $news->setAuthor($this->getUser());

            // Sauvegarde en base
            $entityManager = $doctrine;
            $entityManager->persist($news);
            $entityManager->flush();
            $this->addFlash('success', "La news ".$news->getTitle()." a bien été publiée.");

            return $this->redirectToRoute('newsIndex');
        }

        return $this->renderForm('oeilglauque/admin/writeNews.html.twig', array(
            'form' => $form, 
            'edit' => false
        ));
    }

    #[Route("/admin/news/edit/{slug}", name: "editNews")]
    public function editNews(Request $request, News $news, EntityManagerInterface $doctrine): Response
    {
        if(!$news) {
            throw $this->createNotFoundException(
                'Impossible de trouver la resource demandée'
            );
        }
        $form = $this->createForm(NewsType::class, $news);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $news->setAuthor($this->getUser());

            // Sauvegarde en base
            $entityManager = $doctrine;
            $entityManager->flush();
            $this->addFlash('success', "La news a bien été modifiée.");

            return $this->redirectToRoute('newsIndex');
        }

        return $this->renderForm('oeilglauque/admin/writeNews.html.twig', array(
            'form' => $form, 
            'edit' => true
        ));
    }

    #[Route("/admin/news/delete/{slug}", name: "deleteNews")]
    public function deleteNews(News $news, EntityManagerInterface $doctrine): Response
    {
        if(!$news) {
            throw $this->createNotFoundException(
                'Impossible de trouver la resource demandée'
            );
        }
        $entityManager = $doctrine;
        $entityManager->remove($news);
        $entityManager->flush();
        $this->addFlash('success', "La news ".$news->getTitle()." a bien été supprimée.");

        return $this->redirectToRoute('newsIndex');
    }


    /******************************
     *      Nouvelle édition      *
     ******************************/

    #[Route("/admin/editions/nouvelle", name: "newEdition")]
    public function newEdition(Request $request, EditionRepository $editionRepository, EntityManagerInterface $entityManager): Response
    {
        $edition = new Edition();
        $form = $this->createForm(EditionType::class,$edition);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid())
        {
            if ($editionRepository->findOneBy(['annee' => $edition->getAnnee(), 'type' => $edition->getType()]) != null)
            {
                $this->addFlash('danger', 'Une édition du même type existe déjà pour cette année.');
            } 
            else
            {
                $entityManager->persist($edition);
                $entityManager->flush();

                $this->addFlash('success', "L'édition a bien été créée.");
            }
        }

        return $this->renderForm('oeilglauque/admin/newEdition.html.twig',[
            'form' => $form
        ]);
    }

    /*
     * TODO : remove
     */
    #[Route("/admin/editions/creer", name: "createEdition")]
    public function createEdition(Request $request, EntityManagerInterface $doctrine): Response
    {
        if($request->query->get('annee') != "" && $request->query->get('dates')) {
            $editionCheck = $doctrine->getRepository(Edition::class)->findOneBy(['annee' => $request->query->get('annee')]);
            if ($editionCheck) {
                $this->addFlash('danger', "L'édition ".$request->query->get('annee')." existe déjà.");
                return $this->redirectToRoute('newEdition');
            }
            $edition = new Edition();
            $edition->setAnnee($request->query->get('annee'));
            $edition->setDates($request->query->get('dates'));
            $edition->setHomeText($request->query->get('homeText'));
            $doctrine->persist($edition);
            $doctrine->flush();
            $this->addFlash('success', "La nouvelle édition a bien été ajoutée");
        }
        return $this->redirectToRoute('admin');
    }

    /************************************
     *       Gestion des parties        *
     ************************************/

    #[Route("/admin/games/validate", name: "unvalidatedGamesList")]
    public function unvalidatedGamesList(EntityManagerInterface $doctrine): Response
    {
        $games = $doctrine->getRepository(Game::class)->getOrderedGameList($this->FogParams->getCurrentEdition(), false);
        return $this->render('oeilglauque/admin/unvalidatedGamesList.html.twig', array(  
            'games' => $games
        ));
    }

    #[Route("/admin/games", name: "adminGamesList")]
    public function adminGamesList(EntityManagerInterface $doctrine): Response
    {
        $games = $doctrine->getRepository(Game::class)->getOrderedGameList($this->FogParams->getCurrentEdition(), true);
        return $this->render('oeilglauque/admin/gamesList.html.twig', array(
            'games' => $games, 
        ));
    }

    #[Route("/admin/games/validate/{id}", name: "validateGame")]
    public function validateGame($id, FOGMailerService $mailer, EntityManagerInterface $doctrine): Response
    {
        $game = $doctrine->getRepository(Game::class)->find($id);
        if($game) {
            $game->setValidated(true);
            $doctrine->persist($game);
            $doctrine->flush();

            $author = $game->getAuthor();
            $mailer->sendMail(
                new Address($author->getEmail(),$author->getPseudo()),
                'Votre partie '.$game->getTitle().' a été validée !',
                'oeilglauque/emails/game/gameValidationNotif.html.twig',
                ['author' => $author, 'game' => $game]
            );

            $this->addFlash('success', "La partie a bien été validée.");
        }
        return $this->redirectToRoute('unvalidatedGamesList');
    }

    #[Route("/admin/games/deleteGame/{id}", name: "deleteGame")]
    public function deleteGame(Game $game, EntityManagerInterface $doctrine): Response
    {
        if ($game) {
            $doctrine->remove($game);
            $doctrine->flush();
            $this->addFlash('success', "La partie a bien été supprimée.");
        }
        return $this->redirectToRoute('unvalidatedGamesList');
    }

    #[Route("/admin/games/unregister/{idGame}/{idPlayer}", name: "unregisterGamePlayer")]
    public function unregisterGamePlayer(Game $game, int $idPlayer, EntityManagerInterface $doctrine): Response
    {
        //$game = $doctrine->getRepository(Game::class)->find($idGame);
        $player = $doctrine->getRepository(User::class)->find($idPlayer);
        if ($game && $player) {
            $game->removePlayer($player); // Handles 'contains' verification
            $entityManager = $doctrine;
            $entityManager->persist($game);
            $entityManager->flush();
            $this->addFlash('info', "Le joueur ".$player->getPseudo()." a bien été supprimé de la partie ".$game->getTitle());
            return $this->redirectToRoute('adminGamesList');
        } else {
            throw $this->createNotFoundException('Impossible de trouver la partie ou le joueur demandée. ');
        }
    }

    #[Route("/admin/games/lock/{id}/{status}", name: "lockGame")]
    public function lockGame(Game $game, int $status, EntityManagerInterface $doctrine): Response
    {
        if ($game) {
            $game->setLocked($status == 1 ? true : false);
            $entityManager = $doctrine;
            $entityManager->persist($game);
            $entityManager->flush();
            $this->addFlash('info', "La partie ".$game->getTitle()." a bien été ".($status ? 'bloquée' : 'débloquée'));
            return $this->redirectToRoute('adminGamesList');
        } else {
            throw $this->createNotFoundException('Impossible de trouver la partie demandée.');
        }
    }

    /************************************
     *     Gestion des reservations     *
     ************************************/

    /*****************
     *     local     *
     *****************/

    #[Route("/admin/reservations/local", name: "localReservationList")]
    public function localReservationList(EntityManagerInterface $doctrine): Response
    {
        $reservations = $doctrine->getRepository(LocalReservation::class)->getLocalReservationList();
        return $this->render('oeilglauque/admin/localReservationList.html.twig', [
            'reservations' => $reservations,
            'archive' => false
        ]);
    }
    #[Route("/admin/reservations/local/validate/{id}", name: "validateLocalReservation")]
    public function validateLocalReservation(LocalReservation $reservation, EntityManagerInterface $doctrine, FOGMailerService $mailer): Response
    {
        $reservation->setValidated(true);
        $doctrine->persist($reservation);
        $doctrine->flush();

        $mailer->sendMail(
            new Address($reservation->getAuthor()->getEmail(), $reservation->getAuthor()->getPseudo()),
            'Demande de réservation du local FOG Acceptée !',
            'oeilglauque/emails/localReservation/confirmationReservation.html.twig',
            ['reservation' => $reservation],
            [],
            [$mailer->getMailFOG()]
        );

        $this->addFlash('success', "La demande a bien été acceptée.");

        return $this->redirectToRoute('localReservationList');
    }

    #[Route("/admin/reservations/local/delete/{id}", name: "deleteLocalReservation")]
    public function deleteLocalReservation(LocalReservation $reservation, EntityManagerInterface $doctrine, FOGMailerService $mailer): Response
    {
        $doctrine->remove($reservation);
        $doctrine->flush();

        if($reservation->getDate() > new \DateTime()) {
            $mailer->sendMail(
                new Address($reservation->getAuthor()->getEmail(), $reservation->getAuthor()->getPseudo()),
                "Demande de réservation du local FOG refusée",
                'oeilglauque/emails/localReservation/suppressionReservation.html.twig',
                ['reservation' => $reservation],
                [],
                [$mailer->getMailFOG()]
            );
        }

        $this->addFlash('success', "La demande a bien été supprimée.");

        return $this->redirectToRoute('localReservationList');
    }


    #[Route("/admin/reservations/local/archive", name: "localReservationArchive")]
    public function localReservationArchive(EntityManagerInterface $doctrine): Response
    {
        $reservations = $doctrine->getRepository(LocalReservation::class)->getLocalReservationArchive();
        return $this->render('oeilglauque/admin/localReservationList.html.twig', array(
            'reservations' => $reservations,
            'archive' => true
        ));
    }

    /*****************
     *      jeux     *
     *****************/
    #[Route("/admin/reservations/boardGame", name: "boardGameReservationList")]
    public function boardGameReservationList(EntityManagerInterface $doctrine): Response
    {
        $reservations =$doctrine->getRepository(BoardGameReservation::class)->getBoardGameReservationList();
        return $this->render('oeilglauque/admin/boardGameReservationList.html.twig', array(
            'reservations' => $reservations,
            'archive' => false
        ));
    }
    
    #[Route("/admin/reservations/boardGame/validate/{id}", name: "validateBoardGameReservation")]
    public function validateBoardGameReservation(BoardGameReservation $reservation, EntityManagerInterface $doctrine, FOGMailerService $mailer): Response
    {
        $reservation->setValidated(true);
        $doctrine->persist($reservation);
        $doctrine->flush();

        $mailer->sendMail(
            new Address($reservation->getAuthor()->getEmail(), $reservation->getAuthor()->getPseudo()),
            'Demande de réservation de jeu au FOG Acceptée !',
            'oeilglauque/emails/boardGameReservation/confirmationReservation.html.twig',
            ['reservation' => $reservation],
            [],
            [$mailer->getMailFOG()]
        );

        $this->addFlash('success', "La demande a bien été acceptée.");
        return $this->redirectToRoute('boardGameReservationList');
    }

    #[Route("/admin/reservations/boardGame/delete/{id}", name: "deleteBoardGameReservation")]
    public function deleteBoardGameReservation(BoardGameReservation $reservation, EntityManagerInterface $doctrine, FOGMailerService $mailer) : Response {
        if($reservation->getDateBeg() > new \DateTime()) {
            $mailer->sendMail(
                new Address($reservation->getAuthor()->getEmail(), $reservation->getAuthor()->getPseudo()),
                'Demande de réservation de jeu au FOG refusée',
                'oeilglauque/emails/boardGameReservation/suppressionReservation.html.twig',
                ['reservation' => $reservation],
                [],
                [$mailer->getMailFOG()]
            );
        }

        $doctrine->remove($reservation);
        $doctrine->flush();

        $this->addFlash('success', "La demande a bien été supprimée.");

        return $this->redirectToRoute('boardGameReservationList');
    }
    #[Route("/admin/reservations/boardGame/archive", name: "boardGameReservationArchive")]
    public function boardGameReservationArchive(EntityManagerInterface $doctrine): Response
    {
        $reservations = $doctrine->getRepository(BoardGameReservation::class)->getBoardGameReservationArchive();
        return $this->render('oeilglauque/admin/boardGameReservationList.html.twig', array(
            'reservations' => $reservations,
            'archive' => true
        ));
    }

    /*****************
     *    feature    *
     *****************/
    #[Route("/admin/feature", name: "adminFeature")]
    public function adminFeature(EntityManagerInterface $doctrine): Response
    {
        $features =$doctrine->getRepository(Feature::class)->findAll();
        return $this->render('oeilglauque/admin/feature.html.twig', array(
            'features' => $features
        ));
    }

    #[Route("/admin/feature/update/{id}/{state}", name: "updateFeatureState")]
    public function updateFeatureState(int $id, int $state, EntityManagerInterface $doctrine): Response
    {
        $feature = $doctrine->getRepository(Feature::class)->find($id);
        if($feature) {
            $feature->setState($state != 0);
            $doctrine->flush();

            $this->addFlash('success', $feature->getName() . " est désormais " . ($feature->getState() ? "activée" : "désactivée"));
        }
        return $this->redirectToRoute('adminFeature');
    }
}