<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\SensitiveRecord;
use App\Form\DeleteFormFactory;
use App\Form\SensitiveRecordType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function array_column;
use function array_map;

#[Route('/sensitive-record')]
class SensitiveRecordController extends AbstractController
{
    #[Route('/', name: 'sensitive_record_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em, DeleteFormFactory $deleteForms): Response
    {
        $records = $em->getRepository(SensitiveRecord::class)->findBy([], ['id' => 'DESC']);

        return $this->render('sensitive_record/index.html.twig', [
            'records'      => $records,
            'delete_forms' => $deleteForms->views(
                'sensitive_record_delete',
                array_map(static fn (SensitiveRecord $record): int => (int) $record->getId(), $records),
                'Delete this record?',
            ),
        ]);
    }

    #[Route('/raw', name: 'sensitive_record_raw_index', methods: ['GET'])]
    public function rawIndex(Connection $conn, DeleteFormFactory $deleteForms): Response
    {
        $rows = $conn->fetchAllAssociative(
            'SELECT id, personal_note, financial_note FROM sensitive_record ORDER BY id DESC',
        );

        return $this->render('sensitive_record/raw_index.html.twig', [
            'rows'         => $rows,
            'delete_forms' => $deleteForms->views('sensitive_record_delete', array_column($rows, 'id'), 'Delete?'),
        ]);
    }

    #[Route('/new', name: 'sensitive_record_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $record = new SensitiveRecord();
        $form   = $this->createForm(SensitiveRecordType::class, $record);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($record);
            $em->flush();
            $this->addFlash('success', 'Sensitive record created. Personal note (Halite) and financial note (Defuse) are stored encrypted.');

            return $this->redirectToRoute('sensitive_record_index');
        }

        return $this->render('sensitive_record/new.html.twig', [
            'sensitive_record' => $record,
            'form'             => $form,
        ]);
    }

    #[Route('/{id}', name: 'sensitive_record_show', methods: ['GET'])]
    public function show(SensitiveRecord $sensitiveRecord, DeleteFormFactory $deleteForms): Response
    {
        return $this->render('sensitive_record/show.html.twig', [
            'sensitive_record' => $sensitiveRecord,
            'delete_form'      => $deleteForms->create('sensitive_record_delete', (int) $sensitiveRecord->getId(), 'Delete this record?')->createView(),
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
            'form'             => $form,
        ]);
    }

    #[Route('/{id}', name: 'sensitive_record_delete', methods: ['POST'])]
    public function delete(Request $request, SensitiveRecord $sensitiveRecord, EntityManagerInterface $em, DeleteFormFactory $deleteForms): Response
    {
        $form = $deleteForms->create('sensitive_record_delete', (int) $sensitiveRecord->getId());
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->remove($sensitiveRecord);
            $em->flush();
            $this->addFlash('success', 'Sensitive record deleted.');
        }

        return $this->redirectToRoute('sensitive_record_index');
    }
}
