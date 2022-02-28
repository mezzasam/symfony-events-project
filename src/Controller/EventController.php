<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\User;
use App\Form\EventType;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

/**
 * @Route("/event")
 */
class EventController extends AbstractController
{
  private $entityManager;

  public function __construct(EntityManagerInterface $entityManager)
  {
    $this->entityManager = $entityManager;
  }

  /**
   * @Route("/", name="event_index", methods={"GET"})
   */
  public function index(EventRepository $eventRepository): Response
  {
    /** @var \App\Entity\User $user */
    $user = $this->getUser();

    return $this->render('/event/index.html.twig', [
      'all_next_events' => $eventRepository->findAllNextEvents(),
      'next_events_active' => $eventRepository->findNextEventsActive(),
      'past_events_active' => $eventRepository->findPastEventsActive(),
      'user' => $user
    ]);
  }

  /**
   * @Route("/new", name="event_new", methods={"GET", "POST"})
   * @IsGranted("ROLE_ADMIN")
   */
  public function new(Request $request, MailerInterface $mailer, UserRepository $userRepository): Response
  {
    $event = new Event();
    $form = $this->createForm(EventType::class, $event);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      $this->entityManager->persist($event);
      $this->entityManager->flush();
      //$this->sendEmail($mailer, $event, $userRepository->findAll());
      $response = $this->forward('App\Controller\MailerController::sendEmailToAll', [
        'event' => $event,
        'users' => $userRepository->findAll(),
      ]);

      return $this->redirectToRoute('event_index', [], Response::HTTP_SEE_OTHER);
    }

    return $this->renderForm('event/new.html.twig', [
      'event' => $event,
      'form' => $form,
    ]);
  }

  /**
   * @Route("/{id}", name="event_show", methods={"GET"})
   */
  public function show(Event $event): Response
  {
    /** @var \App\Entity\User $user */
    $user = $this->getUser();
    return $this->render('event/show.html.twig', [
      'event' => $event,
      'user' => $user
    ]);
  }

  /**
   * @Route("/{id}/edit", name="event_edit", methods={"GET", "POST"})
   * @IsGranted("ROLE_ADMIN")
   */
  public function edit(Request $request, Event $event): Response
  {
    $form = $this->createForm(EventType::class, $event);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      $this->entityManager->flush();

      return $this->redirectToRoute('event_index', [], Response::HTTP_SEE_OTHER);
    }

    return $this->renderForm('event/edit.html.twig', [
      'event' => $event,
      'form' => $form,
    ]);
  }

  /**
   * @Route("/{id}", name="event_delete", methods={"POST"})
   * @IsGranted("ROLE_ADMIN")
   */
  public function delete(Request $request, Event $event): Response
  {
    if ($this->isCsrfTokenValid('delete' . $event->getId(), $request->request->get('_token'))) {
      $this->entityManager->remove($event);
      $this->entityManager->flush();
    }

    return $this->redirectToRoute('event_index', [], Response::HTTP_SEE_OTHER);
  }

  /**
   * @Route("/{id}/add_participant", name="event_add_participant", methods={"POST"})
   * @IsGranted("ROLE_USER")
   */
  public function addParticipant(Request $request, Event $event, EventRepository $eventRepository): Response
  {

    if ($this->isCsrfTokenValid('put' . $event->getId(), $request->request->get('_token'))) {
      if ($this->getUser()) {
        $event->addUser($this->getUser());
        $this->entityManager->persist($event);
        $this->entityManager->flush();
      }
    }

    return $this->redirectToRoute('event_index', [], Response::HTTP_SEE_OTHER);
  }
}
