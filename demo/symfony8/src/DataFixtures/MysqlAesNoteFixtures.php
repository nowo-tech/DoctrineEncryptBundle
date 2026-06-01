<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\MysqlAesNote;
use App\Repository\MysqlAesNoteRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ObjectManager;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistry;

/**
 * Preset MySQL AES demo rows: Doctrine path (secret_orm) and native path (secret_native).
 * Plaintext is set in PHP; ciphertext is written on flush (ORM) or via AES_ENCRYPT / MysqlAesEncryptor (native).
 */
class MysqlAesNoteFixtures extends Fixture
{
    public function __construct(
        private readonly MysqlAesNoteRepository $repository,
        private readonly EncryptorRegistry $encryptorRegistry,
        private readonly Connection $connection,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        foreach ($this->ormSamples() as $sample) {
            $note = new MysqlAesNote();
            $note->setTitle($sample['title']);
            $note->setSecretOrm($sample['secret']);
            $manager->persist($note);
        }

        $manager->flush();

        foreach ($this->nativeSamples() as $sample) {
            if ($this->repository->supportsNativeMysqlAes()) {
                $this->repository->insertWithAesEncrypt($sample['title'], $sample['secret']);
            } else {
                $this->insertNativeViaPhpAes($manager, $sample['title'], $sample['secret']);
            }
        }
    }

    /**
     * @return list<array{title: string, secret: string}>
     */
    private function ormSamples(): array
    {
        return [
            [
                'title'  => 'Doctrine · invoice ACME',
                'secret' => 'password-doctrine-acme',
            ],
            [
                'title'  => 'Doctrine · travel policy',
                'secret' => 'emergency-contact-555',
            ],
            [
                'title'  => 'Doctrine · searchable demo',
                'secret' => 'find-me-invoice-filter',
            ],
        ];
    }

    /**
     * @return list<array{title: string, secret: string}>
     */
    private function nativeSamples(): array
    {
        return [
            [
                'title'  => 'SQL native · invoice beta',
                'secret' => 'password-native-beta',
            ],
            [
                'title'  => 'SQL native · backup codes',
                'secret' => 'find-me-sql-filter',
            ],
        ];
    }

    private function insertNativeViaPhpAes(ObjectManager $manager, string $title, string $plaintext): void
    {
        $note = new MysqlAesNote();
        $note->setTitle($title);
        $manager->persist($note);
        $manager->flush();

        $id = $note->getId();
        if ($id === null) {
            return;
        }

        $encryptor  = $this->encryptorRegistry->get('mysql_aes');
        $ciphertext = $encryptor->encrypt($plaintext);

        $this->connection->executeStatement(
            'UPDATE mysql_aes_note SET secret_native = :blob WHERE id = :id',
            ['blob' => $ciphertext, 'id' => $id],
            ['blob' => ParameterType::BINARY, 'id' => Types::INTEGER],
        );
    }
}
