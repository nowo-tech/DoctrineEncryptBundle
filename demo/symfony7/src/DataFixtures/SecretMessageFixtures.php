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
            ['title' => 'Welcome', 'message' => 'This message is stored encrypted in the database.'],
            ['title' => 'Demo note', 'message' => 'You can create, edit and delete secret messages from the CRUD.'],
            ['title' => 'Halite', 'message' => 'The message field uses Halite for encryption at rest.'],
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
