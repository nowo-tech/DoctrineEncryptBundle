<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\SecretMessage;
use App\Form\DeleteFormFactory;
use App\Form\SecretMessageType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\DoctrineEncryptBundle\Util\EncryptUtil;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function array_map;
use function in_array;

#[Route('/secret-message')]
class SecretMessageController extends AbstractController
{
    #[Route('/', name: 'secret_message_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em, DeleteFormFactory $deleteForms): Response
    {
        $messages = $em->getRepository(SecretMessage::class)->findBy([], ['id' => 'DESC']);

        return $this->render('secret_message/index.html.twig', [
            'messages'     => $messages,
            'delete_forms' => $deleteForms->views(
                'secret_message_delete',
                array_map(static fn (SecretMessage $message): int => (int) $message->getId(), $messages),
                'Delete this message?',
            ),
        ]);
    }

    /**
     * List rows via raw SQL (encrypted fields shown as stored in DB).
     * Show / Edit / Delete use the same Doctrine routes (decrypted).
     */
    #[Route('/raw', name: 'secret_message_raw_index', methods: ['GET'])]
    public function rawIndex(): Response
    {
        return $this->redirectToRoute('secret_message_db_values', ['mode' => 'raw']);
    }

    #[Route('/db-values', name: 'secret_message_db_values', methods: ['GET'])]
    public function dbValues(Request $request, Connection $conn, EncryptUtil $encryptUtil): Response
    {
        $mode = $request->query->getString('mode', 'both');
        if (!in_array($mode, ['raw', 'decrypted', 'both'], true)) {
            $mode = 'both';
        }

        $rows = [];
        foreach ($conn->fetchAllAssociative('SELECT id, title, message FROM secret_message ORDER BY id DESC') as $row) {
            $raw    = isset($row['message']) && $row['message'] !== '' ? (string) $row['message'] : null;
            $rows[] = [
                'id'                => (int) $row['id'],
                'title'             => $row['title'],
                'message_raw'       => $raw,
                'message_decrypted' => $raw !== null ? $encryptUtil->decrypt($raw, 'personal_data') : null,
            ];
        }

        return $this->render('secret_message/db_values.html.twig', [
            'rows' => $rows,
            'mode' => $mode,
        ]);
    }

    #[Route('/new', name: 'secret_message_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $secretMessage = new SecretMessage();
        $form          = $this->createForm(SecretMessageType::class, $secretMessage);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($secretMessage);
            $em->flush();
            $this->addFlash('success', 'Secret message created. The message field is stored encrypted in the database.');

            return $this->redirectToRoute('secret_message_index');
        }

        return $this->render('secret_message/new.html.twig', [
            'secret_message' => $secretMessage,
            'form'           => $form,
        ]);
    }

    #[Route('/{id}', name: 'secret_message_show', methods: ['GET'])]
    public function show(SecretMessage $secretMessage, DeleteFormFactory $deleteForms): Response
    {
        return $this->render('secret_message/show.html.twig', [
            'secret_message' => $secretMessage,
            'delete_form'    => $deleteForms->create('secret_message_delete', (int) $secretMessage->getId(), 'Delete this message?', 'mt-4 pt-3 border-top')->createView(),
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
            'form'           => $form,
        ]);
    }

    #[Route('/{id}', name: 'secret_message_delete', methods: ['POST'])]
    public function delete(Request $request, SecretMessage $secretMessage, EntityManagerInterface $em, DeleteFormFactory $deleteForms): Response
    {
        $form = $deleteForms->create('secret_message_delete', (int) $secretMessage->getId());
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->remove($secretMessage);
            $em->flush();
            $this->addFlash('success', 'Secret message deleted.');
        }

        return $this->redirectToRoute('secret_message_index');
    }
}
