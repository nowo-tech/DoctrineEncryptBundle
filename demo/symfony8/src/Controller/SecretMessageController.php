<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\SecretMessage;
use App\Form\SecretMessageType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/secret-message')]
class SecretMessageController extends AbstractController
{
    #[Route('/', name: 'secret_message_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $messages = $em->getRepository(SecretMessage::class)->findBy([], ['id' => 'DESC']);

        return $this->render('secret_message/index.html.twig', [
            'messages' => $messages,
        ]);
    }

    /**
     * List rows via raw SQL (encrypted fields shown as stored in DB).
     * Show / Edit / Delete use the same Doctrine routes (decrypted).
     */
    #[Route('/raw', name: 'secret_message_raw_index', methods: ['GET'])]
    public function rawIndex(Connection $conn): Response
    {
        $rows = $conn->fetchAllAssociative(
            'SELECT id, title, message FROM secret_message ORDER BY id DESC'
        );

        return $this->render('secret_message/raw_index.html.twig', [
            'rows' => $rows,
        ]);
    }

    #[Route('/new', name: 'secret_message_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $secretMessage = new SecretMessage();
        $form = $this->createForm(SecretMessageType::class, $secretMessage);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($secretMessage);
            $em->flush();
            $this->addFlash('success', 'Secret message created. The message field is stored encrypted in the database.');

            return $this->redirectToRoute('secret_message_index');
        }

        return $this->render('secret_message/new.html.twig', [
            'secret_message' => $secretMessage,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'secret_message_show', methods: ['GET'])]
    public function show(SecretMessage $secretMessage): Response
    {
        return $this->render('secret_message/show.html.twig', [
            'secret_message' => $secretMessage,
        ]);
    }

    #[Route('/{id}/edit', name: 'secret_message_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, SecretMessage $secretMessage, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(SecretMessageType::class, $secretMessage);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Secret message updated.');

            return $this->redirectToRoute('secret_message_index');
        }

        return $this->render('secret_message/edit.html.twig', [
            'secret_message' => $secretMessage,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'secret_message_delete', methods: ['POST'])]
    public function delete(Request $request, SecretMessage $secretMessage, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $secretMessage->getId(), (string) $request->request->get('_token'))) {
            $em->remove($secretMessage);
            $em->flush();
            $this->addFlash('success', 'Secret message deleted.');
        }

        return $this->redirectToRoute('secret_message_index');
    }
}
