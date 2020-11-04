<?php

namespace App\Controller;

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use App\Entity\Edition;
use App\Entity\GameSlot;
use App\Entity\Game;
use App\Entity\News;
use App\Entity\BoardGameReservation;
use App\Entity\LocalReservation;
use App\Entity\BoardGameOrder;
use App\Form\NewsType;
use Symfony\Component\Validator\Constraints\Date;

class AdminController extends CustomController {

    /****************************************
     *      Interface d'administration      *
     ****************************************/

    /**
     * @Route("/admin", name="admin")
     */
    public function admin() {
        return $this->render('oeilglauque/admin.html.twig', array(
            'dates' => $this->getCurrentEdition()->getDates(), 
        ));
    }


    /**********************************
     *      Gestion des éditions      *
     **********************************/


    /**
     * @Route("/admin/editions", name="admin_editions")
     */
    public function editionsAdmin() {
        $editions = $this->getDoctrine()->getRepository(Edition::class)->findAll();
        return $this->render('oeilglauque/admin/editions.html.twig', array(
            'dates' => $this->getCurrentEdition()->getDates(), 
            'editions' => $editions
        ));
    }

    /**
     * @Route("/admin/editions/updateDates/{edition}", name="updateDates")
     */
    public function updateDates(Request $request, $edition) {
        $editionval = $this->getDoctrine()->getRepository(Edition::class)->find($edition);
        if(!$editionval) {
            throw $this->createNotFoundException(
                'Aucune édition n\'a pour id '.$edition
            );
        }
        if($request->query->get('dates') != "") {
            $editionval->setDates($request->query->get('dates'));
            $this->getDoctrine()->getManager()->flush();
            $this->addFlash('success', "Les dates ont bien été mises à jour. ");
        }
        return $this->redirectToRoute('admin_editions');
    }

    /**
     * @Route("/admin/editions/updateSlot/{slot}", name="updateSlot")
     */
    public function updateSlot(Request $request, $slot) {
        $slotval = $this->getDoctrine()->getRepository(GameSlot::class)->find($slot);
        if (!$slotval) {
            throw $this->createNotFoundException(
                'Aucun slot n\'a pour id '.$slot
            );
        }
        if($request->query->get('text') != "") {
            $slotval->setText($request->query->get('text'));
            $slotval->setMaxGames($request->query->get('maxGames'));
            $this->getDoctrine()->getManager()->flush();
            $this->addFlash('success', "Le slot a bien été mis à jour. ");
        }
        return $this->redirectToRoute('admin_editions');
    }

    /**
     * @Route("/admin/editions/addSlot/{edition}", name="addSlot")
     */
    public function addSlot(Request $request, $edition) {
        if($request->query->get('text') != "") {
            $slot = new GameSlot();
            $slot->setText($request->query->get('text'));
            $slot->setMaxGames($request->query->get('maxGames'));
            $editionval = $this->getDoctrine()->getRepository(Edition::class)->find($edition);
            if (!$editionval) {
                throw $this->createNotFoundException(
                    'Aucune édition n\'a pour id '.$edition
                );
            }
            $slot->setEdition($editionval);
            $this->getDoctrine()->getManager()->persist($slot);
            $this->getDoctrine()->getManager()->flush();
            $this->addFlash('success', "Le slot a bien été ajouté. ");
        }

        return $this->redirectToRoute('admin_editions');
    }

    /******************************
     *      Gestion des news      *
     ******************************/

    /**
     * @Route("/admin/news/rediger", name="writeNews")
     */
    public function writeNews(Request $request) {
        $news = new News();
        $form = $this->createForm(NewsType::class, $news);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $news->setAuthor($this->getUser());

            // Sauvegarde en base
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($news);
            $entityManager->flush();
            $this->addFlash('success', "La news ".$news->getTitle()." a bien été publiée.");

            return $this->redirectToRoute('newsIndex');
        }

        return $this->render('oeilglauque/admin/writeNews.html.twig', array(
            'dates' => $this->getCurrentEdition()->getDates(), 
            'form' => $form->createView(), 
            'edit' => false, 
        ));
    }

    /**
     * @Route("/admin/news/edit/{slug}", name="editNews")
     */
    public function editNews(Request $request, $slug) {
        $news = $this->getDoctrine()->getRepository(News::class)->findOneBy(['slug' => $slug]);
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
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->flush();
            $this->addFlash('success', "La news a bien été modifiée.");

            return $this->redirectToRoute('newsIndex');
        }

        return $this->render('oeilglauque/admin/writeNews.html.twig', array(
            'dates' => $this->getCurrentEdition()->getDates(), 
            'form' => $form->createView(), 
            'edit' => true, 
        ));
    }

    /**
     * @Route("/admin/news/delete/{slug}", name="deleteNews")
     */
    public function deleteNews(Request $request, $slug) {
        $news = $this->getDoctrine()->getRepository(News::class)->findOneBy(['slug' => $slug]);
        if(!$news) {
            throw $this->createNotFoundException(
                'Impossible de trouver la resource demandée'
            );
        }
        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->remove($news);
        $entityManager->flush();
        $this->addFlash('success', "La news ".$news->getTitle()." a bien été supprimée.");

        return $this->redirectToRoute('newsIndex');
    }


    /******************************
     *      Nouvelle édition      *
     ******************************/

    /**
    * @Route("/admin/editions/nouvelle", name="newEdition")
    */
    public function newEdition() {
        return $this->render('oeilglauque/admin/newEdition.html.twig', array(
            'dates' => $this->getCurrentEdition()->getDates(), 
        ));
    }

    /**
    * @Route("/admin/editions/creer", name="createEdition")
    */
    public function createEdition(Request $request) {
        if($request->query->get('annee') != "" && $request->query->get('dates')) {
            $edition = new Edition();
            $edition->setAnnee($request->query->get('annee'));
            $edition->setDates($request->query->get('dates'));
            $this->getDoctrine()->getManager()->persist($edition);
            $this->getDoctrine()->getManager()->flush();
            $this->addFlash('success', "La nouvelle édition a bien été ajoutée");
        }
        return $this->redirectToRoute('admin');
    }

    /************************************
     *       Gestion des parties        *
     ************************************/

    /**
     * @Route("/admin/games/validate", name="unvalidatedGamesList")
     */
    public function unvalidatedGamesList() {
        $games = $this->getDoctrine()->getRepository(Game::class)->getOrderedGameList($this->getCurrentEdition(), false);
        return $this->render('oeilglauque/admin/unvalidatedGamesList.html.twig', array(
            'dates' => $this->getCurrentEdition()->getDates(), 
            'games' => $games
        ));
    }

    /**
     * @Route("/admin/games", name="adminGamesList")
     */
    public function adminGamesList() {
        $games = $this->getDoctrine()->getRepository(Game::class)->getOrderedGameList($this->getCurrentEdition(), true);
        return $this->render('oeilglauque/admin/gamesList.html.twig', array(
            'dates' => $this->getCurrentEdition()->getDates(), 
            'games' => $games, 
        ));
    }

    /**
     * @Route("/admin/games/validate/{id}", name="validateGame")
     */
    public function validateGame($id) {
        $game = $this->getDoctrine()->getRepository(Game::class)->find($id);
        if($game) {
            $game->setValidated(true);
            $this->getDoctrine()->getManager()->persist($game);
            $this->getDoctrine()->getManager()->flush();
            $this->addFlash('success', "La partie a bien été validée.");
        }
        return $this->redirectToRoute('unvalidatedGamesList');
    }

    /**
     * @Route("/admin/games/deleteGame/{id}", name="deleteGame")
     */
    public function deleteGame($id) {
        $game = $this->getDoctrine()->getRepository(Game::class)->find($id);
        if ($game) {
            $this->getDoctrine()->getManager()->remove($game);
            $this->getDoctrine()->getManager()->flush();
            $this->addFlash('success', "La partie a bien été supprimée.");
        }
        return $this->redirectToRoute('unvalidatedGamesList');
    }

    /************************************
     *     Gestion des reservations     *
     ************************************/

    /*****************
     *     local     *
     *****************/

    /**
     * @Route("/admin/reservations/local", name="localReservationList")
     */
    public function localReservationList() {
        $reservations =$this->getDoctrine()->getRepository(LocalReservation::class)->getLocalReservationList();
        return $this->render('oeilglauque/admin/localReservationList.html.twig', array(
            'reservations' => $reservations,
            'archive' => false
        ));
    }
    /**
     * @Route("/admin/reservations/local/validate/{id}", name="validateLocalReservation")
     */
    public function validateLocalReservation($id) {
        $reservation = $this->getDoctrine()->getRepository(LocalReservation::class)->find($id);
        if($reservation) {
            $reservation->setValidated(true);
            $this->getDoctrine()->getManager()->persist($reservation);
            $this->getDoctrine()->getManager()->flush();

            $this->sendmail('Demande de réservation du local FOG Acceptée !',
                [$reservation->getAuthor()->getEmail() => $reservation->getAuthor()->getPseudo()],
                'localReservation/confirmationReservation',
                ['reservation' => $reservation],
                $this->get('swiftmailer.mailer.default'));

            $this->sendmail('Demande de réservation du local FOG Acceptée',
                [$_ENV['MAILER_ADDRESS'] => 'L\'équipe du FOG'],
                'localReservation/admin/confirmationReservation',
                ['reservation' => $reservation],
                $this->get('swiftmailer.mailer.default'));

            $this->addFlash('success', "La demande a bien été acceptée.");
        }
        return $this->redirectToRoute('localReservationList');
    }

    /**
     * @Route("/admin/reservations/local/delete/{id}", name="deleteLocalReservation")
     */
    public function deleteLocalReservation($id) {
        $archive = false;

        $reservation = $this->getDoctrine()->getRepository(LocalReservation::class)->find($id);
        if ($reservation) {
            $this->getDoctrine()->getManager()->remove($reservation);
            $this->getDoctrine()->getManager()->flush();

            if($reservation->getDate() > new \DateTime()) {
                $this->sendmail('Demande de réservation du local FOG refusée',
                [$reservation->getAuthor()->getEmail() => $reservation->getAuthor()->getPseudo()],
                    'localReservation/suppressionReservation',
                    ['reservation' => $reservation],
                    $this->get('swiftmailer.mailer.default'));

                $this->sendmail('Demande de réservation du local FOG refusée',
                    [$_ENV['MAILER_ADDRESS'] => 'L\'équipe du FOG'],
                    'localReservation/admin/suppressionReservation',
                    ['reservation' => $reservation],
                    $this->get('swiftmailer.mailer.default'));
            }

            $this->addFlash('success', "La demande a bien été supprimée.");
        }
        return $this->redirectToRoute('localReservationList');
    }
    /**
     * @Route("/admin/reservations/local/archive", name="localReservationArchive")
     */
    public function localReservationArchive() {
        $reservations = $this->getDoctrine()->getRepository(LocalReservation::class)->getLocalReservationArchive();
        return $this->render('oeilglauque/admin/localReservationList.html.twig', array(
            'reservations' => $reservations,
            'archive' => true
        ));
    }

    private function sendmail(string $obj, array $to, string $templateName, array $data, \Swift_Mailer $mailer) {
        $dotenv = new Dotenv();
        $dotenv->load(__DIR__.'/../../.env');

        $message = (new \Swift_Message($obj))
            ->setFrom([$_ENV['MAILER_ADDRESS'] => 'L\'équipe du FOG'])
            ->setBcc($_ENV['MAILER_ADDRESS'])
            ->setTo($to)
            ->setBody(
                $this->renderView(
                    'oeilglauque/emails/' . $templateName . '.html.twig',
                    $data
                ),
                'text/html'
            );

        $mailer->send($message);
    }

    /*****************
     *      jeux     *
     *****************/
    /**
     * @Route("/admin/reservations/boardGame", name="boardGameReservationList")
     */
    public function boardGameReservationList() {
        $reservations =$this->getDoctrine()->getRepository(BoardGameReservation::class)->getBoardGameReservationList();
        return $this->render('oeilglauque/admin/boardGameReservationList.html.twig', array(
            'reservations' => $reservations,
            'archive' => false
        ));
    }
    /**
     * @Route("/admin/reservations/boardGame/validate/{id}", name="validateBoardGameReservation")
     */
    public function validateBoardGameReservation($id) {
        $reservation = $this->getDoctrine()->getRepository(BoardGameReservation::class)->find($id);
        if($reservation) {
            $reservation->setValidated(true);
            $this->getDoctrine()->getManager()->persist($reservation);
            $this->getDoctrine()->getManager()->flush();

            $this->sendmail('Demande de réservation de jeu au FOG Acceptée !',
                [$reservation->getAuthor()->getEmail() => $reservation->getAuthor()->getPseudo()],
                'boardGameReservation/confirmationReservation',
                ['reservation' => $reservation],
                $this->get('swiftmailer.mailer.default'));

            $this->sendmail('Demande de réservation de jeu au FOG Acceptée',
                [$_ENV['MAILER_ADDRESS'] => 'L\'équipe du FOG'],
                'boardGameReservation/admin/confirmationReservation',
                ['reservation' => $reservation],
                $this->get('swiftmailer.mailer.default'));

            $this->addFlash('success', "La demande a bien été acceptée.");
        }
        return $this->redirectToRoute('boardGameReservationList');
    }

    /**
     * @Route("/admin/reservations/boardGame/delete/{id}", name="deleteBoardGameReservation")
     */
    public function deleteBoardGameReservation($id) {
        $archive = false;

        $reservation = $this->getDoctrine()->getRepository(BoardGameReservation::class)->find($id);
        if ($reservation) {
            if($reservation->getDateBeg() > new \DateTime()) {
                $this->sendmail('Demande de réservation de jeu au FOG refusée',
                    [$reservation->getAuthor()->getEmail() => $reservation->getAuthor()->getPseudo()],
                    'boardGameReservation/suppressionReservation',
                    ['reservation' => $reservation],
                    $this->get('swiftmailer.mailer.default'));
            }

            if($reservation->getDateBeg() > new \DateTime()) {
                $this->sendmail('Demande de réservation de jeu au FOG refusée',
                    [$_ENV['MAILER_ADDRESS'] => 'L\'équipe du FOG'],
                    'boardGameReservation/admin/suppressionReservation',
                    ['reservation' => $reservation],
                    $this->get('swiftmailer.mailer.default'));
            }

            $this->getDoctrine()->getManager()->remove($reservation);
            $this->getDoctrine()->getManager()->flush();

            $this->addFlash('success', "La demande a bien été supprimée.");
        }
        return $this->redirectToRoute('boardGameReservationList');
    }
    /**
     * @Route("/admin/reservations/boardGame/archive", name="boardGameReservationArchive")
     */
    public function boardGameReservationArchive()
    {
        $reservations = $this->getDoctrine()->getRepository(BoardGameReservation::class)->getBoardGameReservationArchive();
        return $this->render('oeilglauque/admin/boardGameReservationList.html.twig', array(
            'reservations' => $reservations,
            'archive' => true
        ));
    }

    /************************************
     *         Gestion du shop          *
     ************************************/

    /**
     * @Route("/admin/shop", name="adminShop")
     */
    public function adminShop()
    {
        $orders = $this->getDoctrine()->getRepository(BoardGameOrder::class)->findAll();
        $fullTotal = $this->getDoctrine()->getRepository(BoardGameOrder::class)->getFullTotal();
        return $this->render('oeilglauque/admin/shopAdmin.html.twig', array(
            'status' => $this->getParameter('allow_shop'),
            'orders' => $orders,
            'fullTotal' => $fullTotal,
        ));
    }

    /**
     * @Route("/admin/shop/removeOrder/{id}", name="adminShopRemoveOrder")
     */
    public function adminShopRemoveOrder($id) {
        $order = $this->getDoctrine()->getRepository(BoardGameOrder::class)->find($id);
        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->remove($order);
        $entityManager->flush();
        return $this->redirectToRoute('adminShop');
    }

    /**
     * @Route("/admin/shop/enable/{status}", name="adminShopEnabling")
     */
    public function adminShopEnabling($status) {
        $this->container->setParameter('allow_shop_env', $status);
        if ($status) {
            $this->addFlash('success', "Le shop a bien été activé.");
        } else {
            $this->addFlash('success', "Le shop a bien été désactivé.");
        }
        return $this->redirectToRoute('adminShop');
    }
}

?>