<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\SensitiveRecord;
use App\Form\SensitiveRecordType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/sensitive-record')]
class SensitiveRecordController extends AbstractController
{
    #[Route('/', name: 'sensitive_record_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $records = $em->getRepository(SensitiveRecord::class)->findBy([], ['id' => 'DESC']);

        return $this->render('sensitive_record/index.html.twig', [
            'records' => $records,
        ]);
    }

    #[Route('/raw', name: 'sensitive_record_raw_index', methods: ['GET'])]
    public function rawIndex(Connection $conn): Response
    {
        $rows = $conn->fetchAllAssociative(
            'SELECT id, personal_note, financial_note FROM sensitive_record ORDER BY id DESC'
        );

        return $this->render('sensitive_record/raw_index.html.twig', [
            'rows' => $rows,
        ]);
    }

    #[Route('/new', name: 'sensitive_record_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $record = new SensitiveRecord();
        $form = $this->createForm(SensitiveRecordType::class, $record);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($record);
            $em->flush();
            $this->addFlash('success', 'Sensitive record created. Personal note (Halite) and financial note (Defuse) are stored encrypted.');

            return $this->redirectToRoute('sensitive_record_index');
        }

        return $this->render('sensitive_record/new.html.twig', [
            'sensitive_record' => $record,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'sensitive_record_show', methods: ['GET'])]
    public function show(SensitiveRecord $sensitiveRecord): Response
    {
        return $this->render('sensitive_record/show.html.twig', [
            'sensitive_record' => $sensitiveRecord,
        ]);
    }

    #[Route('/{id}/edit', name: 'sensitive_record_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, SensitiveRecord $sensitiveRecord, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(SensitiveRecordType::class, $sensitiveRecord);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Sensitive record updated.');

            return $this->redirectToRoute('sensitive_record_index');
        }

        return $this->render('sensitive_record/edit.html.twig', [
            'sensitive_record' => $sensitiveRecord,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'sensitive_record_delete', methods: ['POST'])]
    public function delete(Request $request, SensitiveRecord $sensitiveRecord, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $sensitiveRecord->getId(), (string) $request->request->get('_token'))) {
            $em->remove($sensitiveRecord);
            $em->flush();
            $this->addFlash('success', 'Sensitive record deleted.');
        }

        return $this->redirectToRoute('sensitive_record_index');
    }
}
