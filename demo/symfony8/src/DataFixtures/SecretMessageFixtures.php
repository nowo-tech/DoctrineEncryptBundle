<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\SecretMessage;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class SecretMessageFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $messages = [
            ['title' => 'Office Wi-Fi', 'message' => 'Guest network "ACME-Visitors", passphrase rotates every Monday.'],
            ['title' => 'Patient callback', 'message' => 'Call Ms. Garcia after 5 pm about her MRI results.'],
            ['title' => 'Vendor portal', 'message' => 'Recovery codes are kept in the finance safe, drawer 2.'],
            ['title' => 'Payroll note', 'message' => 'March bonus approved for the support team (12 people).'],
        ];

        foreach ($messages as $data) {
            $entity = new SecretMessage();
            $entity->setTitle($data['title']);
            $entity->setMessage($data['message']);
            $manager->persist($entity);
        }

        $manager->flush();
    }
}
