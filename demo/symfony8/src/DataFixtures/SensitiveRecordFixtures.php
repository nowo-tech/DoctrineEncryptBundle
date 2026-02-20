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
                'envVarNote' => 'Secret from env (APP_ENCRYPT_KEY).',
            ],
            [
                'personalNote' => 'Preferred doctor: Dr. Smith. Next check-up: March 2024.',
                'financialNote' => 'Credit card expiry 12/25. Monthly limit: 2000 EUR.',
                'envVarNote' => 'Another env_var encrypted value.',
            ],
            [
                'personalNote' => 'Blood type: O+. Donor registered.',
                'financialNote' => 'IBAN ES12 3456 7890 1234 5678 9012. BIC: EXAMPLE.',
                'envVarNote' => 'Third record env_var field.',
            ],
        ];

        foreach ($items as $data) {
            $record = new SensitiveRecord();
            $record->setPersonalNote($data['personalNote']);
            $record->setFinancialNote($data['financialNote']);
            $record->setEnvVarNote($data['envVarNote']);
            $manager->persist($record);
        }

        $manager->flush();
    }
}
