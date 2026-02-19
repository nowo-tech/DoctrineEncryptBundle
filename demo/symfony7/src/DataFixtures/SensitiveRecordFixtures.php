<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\SensitiveRecord;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class SensitiveRecordFixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['sensitive_record'];
    }

    public function load(ObjectManager $manager): void
    {
        $items = [
            [
                'personalNote' => 'Allergy: penicillin. Emergency contact: +34 600 000 001.',
                'financialNote' => 'Bank account ending 1234. Last payment: 2024-01-15.',
            ],
            [
                'personalNote' => 'Preferred doctor: Dr. Smith. Next check-up: March 2024.',
                'financialNote' => 'Credit card expiry 12/25. Monthly limit: 2000 EUR.',
            ],
            [
                'personalNote' => 'Blood type: O+. Donor registered.',
                'financialNote' => 'IBAN ES12 3456 7890 1234 5678 9012. BIC: EXAMPLE.',
            ],
        ];

        foreach ($items as $data) {
            $record = new SensitiveRecord();
            $record->setPersonalNote($data['personalNote']);
            $record->setFinancialNote($data['financialNote']);
            $manager->persist($record);
        }

        $manager->flush();
    }
}
