<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\MysqlAesNote;
use App\Form\MysqlAesNativeType;
use App\Form\MysqlAesNoteType;
use App\Repository\MysqlAesNoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistry;
use Nowo\DoctrineEncryptBundle\Util\EncryptUtil;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function in_array;

#[Route('/mysql-aes-note')]
class MysqlAesNoteController extends AbstractController
{
    #[Route('/', name: 'mysql_aes_note_index', methods: ['GET'])]
    public function index(Request $request, MysqlAesNoteRepository $repository): Response
    {
        $titleFilter  = $request->query->getString('title');
        $secretFilter = $request->query->getString('secret');
        $secretMode   = $request->query->getString('secret_mode', 'plaintext');
        if (!in_array($secretMode, ['plaintext', 'ciphertext'], true)) {
            $secretMode = 'plaintext';
        }

        $titleLike    = MysqlAesNoteRepository::buildLikePattern($titleFilter !== '' ? $titleFilter : null);
        $secretNeedle = $secretFilter !== '' ? $secretFilter : null;
        $notes        = $repository->findDoctrineListFiltered($titleLike, $secretNeedle, $secretMode);

        return $this->render('mysql_aes_note/index.html.twig', [
            'notes'           => $notes,
            'nativeSupported' => $repository->supportsNativeMysqlAes(),
            'filter'          => [
                'title'       => $titleFilter,
                'secret'      => $secretFilter,
                'secret_mode' => $secretMode,
            ],
            'queryDescription' => MysqlAesNoteRepository::describeDoctrineLikeFilter(
                $titleLike,
                $secretNeedle,
                $secretMode,
            ),
        ]);
    }

    /**
     * Form → repository INSERT with AES_ENCRYPT (MySQL/MariaDB only).
     */
    #[Route('/sql/new', name: 'mysql_aes_note_sql_new', methods: ['GET', 'POST'])]
    public function sqlNew(Request $request, MysqlAesNoteRepository $repository): Response
    {
        if (!$repository->supportsNativeMysqlAes()) {
            $this->addFlash('warning', 'Native AES_ENCRYPT requires MySQL/MariaDB. Use DATABASE_URL from .env.example (mysql://…).');

            return $this->redirectToRoute('mysql_aes_note_index');
        }

        $form = $this->createForm(MysqlAesNativeType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $repository->insertWithAesEncrypt(
                (string) $data['title'],
                (string) $data['secret'],
            );
            $this->addFlash('success', 'Inserted with AES_ENCRYPT() in SQL (repository).');

            return $this->redirectToRoute('mysql_aes_note_sql_index');
        }

        return $this->render('mysql_aes_note/sql_new.html.twig', [
            'form' => $form,
        ]);
    }

    /** List rows decrypted with AES_DECRYPT in SQL (optional LIKE filters). */
    #[Route('/sql', name: 'mysql_aes_note_sql_index', methods: ['GET'])]
    public function sqlIndex(Request $request, MysqlAesNoteRepository $repository): Response
    {
        if (!$repository->supportsNativeMysqlAes()) {
            return $this->render('mysql_aes_note/sql_unavailable.html.twig');
        }

        $titleFilter  = $request->query->getString('title');
        $secretFilter = $request->query->getString('secret');
        $secretMode   = $request->query->getString('secret_mode', 'decrypted');
        if (!in_array($secretMode, ['decrypted', 'ciphertext'], true)) {
            $secretMode = 'decrypted';
        }

        $titleLike  = MysqlAesNoteRepository::buildLikePattern($titleFilter !== '' ? $titleFilter : null);
        $secretLike = MysqlAesNoteRepository::buildLikePattern($secretFilter !== '' ? $secretFilter : null);

        return $this->render('mysql_aes_note/sql_index.html.twig', [
            'rows'   => $repository->findDecryptedWithAesDecryptFiltered($titleLike, $secretLike, $secretMode),
            'filter' => [
                'title'       => $titleFilter,
                'secret'      => $secretFilter,
                'secret_mode' => $secretMode,
            ],
            'queryDescription' => MysqlAesNoteRepository::describeSqlLikeFilter($titleLike, $secretLike, $secretMode),
        ]);
    }

    /** Raw HEX(secret_native) as stored by MySQL. */
    #[Route('/sql/raw', name: 'mysql_aes_note_sql_raw', methods: ['GET'])]
    public function sqlRaw(MysqlAesNoteRepository $repository): Response
    {
        if (!$repository->supportsNativeMysqlAes()) {
            return $this->render('mysql_aes_note/sql_unavailable.html.twig');
        }

        return $this->render('mysql_aes_note/sql_raw.html.twig', [
            'rows' => $repository->findAllNativeRaw(),
        ]);
    }

    /** DB storage: encrypted vs decrypted for secret_orm and secret_native. */
    #[Route('/db-values', name: 'mysql_aes_note_db_values', methods: ['GET'])]
    public function dbValues(
        Request $request,
        MysqlAesNoteRepository $repository,
        EncryptUtil $encryptUtil,
        EncryptorRegistry $encryptorRegistry,
    ): Response {
        $mode = $request->query->getString('mode', 'both');
        if (!in_array($mode, ['raw', 'decrypted', 'both'], true)) {
            $mode = 'both';
        }

        return $this->render('mysql_aes_note/db_values.html.twig', [
            'rows'            => $repository->findAllStorageComparison($encryptUtil, $encryptorRegistry),
            'mode'            => $mode,
            'nativeSupported' => $repository->supportsNativeMysqlAes(),
        ]);
    }

    #[Route('/new', name: 'mysql_aes_note_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $note = new MysqlAesNote();
        $form = $this->createForm(MysqlAesNoteType::class, $note);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($note);
            $em->flush();
            $this->addFlash('success', 'Saved via Doctrine (MysqlAes encryptor + subscriber).');

            return $this->redirectToRoute('mysql_aes_note_index');
        }

        return $this->render('mysql_aes_note/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'mysql_aes_note_show', methods: ['GET'])]
    public function show(MysqlAesNote $note): Response
    {
        return $this->render('mysql_aes_note/show.html.twig', [
            'note' => $note,
        ]);
    }

    #[Route('/{id}/edit', name: 'mysql_aes_note_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, MysqlAesNote $note, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(MysqlAesNoteType::class, $note);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Updated via Doctrine.');

            return $this->redirectToRoute('mysql_aes_note_show', ['id' => $note->getId()]);
        }

        return $this->render('mysql_aes_note/edit.html.twig', [
            'note' => $note,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'mysql_aes_note_delete', methods: ['POST'])]
    public function delete(Request $request, MysqlAesNote $note, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $note->getId(), (string) $request->request->get('_token'))) {
            $em->remove($note);
            $em->flush();
            $this->addFlash('success', 'Note deleted.');
        }

        return $this->redirectToRoute('mysql_aes_note_index');
    }
}
