<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MysqlAesNote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistry;
use Nowo\DoctrineEncryptBundle\Util\EncryptUtil;
use RuntimeException;

use function is_resource;

/**
 * Examples of MySQL AES_ENCRYPT / AES_DECRYPT in raw SQL (native path).
 */
class MysqlAesNoteRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly Connection $connection,
        private readonly string $mysqlAesKey,
    ) {
        parent::__construct($registry, MysqlAesNote::class);
    }

    public function supportsNativeMysqlAes(): bool
    {
        $platform = $this->connection->getDatabasePlatform();

        return $platform instanceof MySQLPlatform || $platform instanceof MariaDBPlatform;
    }

    /**
     * INSERT with AES_ENCRYPT (plaintext never stored in PHP layer).
     */
    public function insertWithAesEncrypt(string $title, string $plaintext): int
    {
        $this->assertNativeMysql();

        $this->connection->executeStatement(
            'INSERT INTO mysql_aes_note (title, secret_native) VALUES (:title, AES_ENCRYPT(:plain, :key))',
            [
                'title' => $title,
                'plain' => $plaintext,
                'key'   => $this->mysqlAesKey,
            ],
        );

        return (int) $this->connection->lastInsertId();
    }

    /**
     * SELECT with AES_DECRYPT (returns plaintext rows).
     *
     * @return list<array{id: int, title: string, secret_plain: ?string}>
     */
    public function findAllDecryptedWithAesDecrypt(): array
    {
        return $this->findDecryptedWithAesDecryptFiltered(null, null, 'decrypted');
    }

    /**
     * List native AES rows with optional LIKE filters (MySQL).
     *
     * @param 'ciphertext'|'decrypted' $secretMode decrypted = LIKE on CAST(AES_DECRYPT(...)); ciphertext = LIKE on HEX(blob)
     *
     * @return list<array{id: int, title: string, secret_plain: ?string}>
     */
    public function findDecryptedWithAesDecryptFiltered(
        ?string $titleLike,
        ?string $secretLike,
        string $secretMode = 'decrypted',
    ): array {
        $this->assertNativeMysql();

        $conditions = ['secret_native IS NOT NULL'];
        $params     = ['key' => $this->mysqlAesKey];

        if ($titleLike !== null) {
            $conditions[]        = 'title LIKE :titleLike';
            $params['titleLike'] = $titleLike;
        }

        if ($secretLike !== null) {
            if ($secretMode === 'ciphertext') {
                $conditions[]         = 'HEX(secret_native) LIKE :secretLike';
                $params['secretLike'] = $secretLike;
            } else {
                $conditions[]         = 'CAST(AES_DECRYPT(secret_native, :key) AS CHAR) LIKE :secretLike';
                $params['secretLike'] = $secretLike;
            }
        }

        $where = implode(' AND ', $conditions);

        return $this->connection->fetchAllAssociative(
            "SELECT id, title, CAST(AES_DECRYPT(secret_native, :key) AS CHAR) AS secret_plain
             FROM mysql_aes_note
             WHERE {$where}
             ORDER BY id DESC",
            $params,
        );
    }

    /**
     * Doctrine list: title via DQL LIKE; secret via PHP (plaintext) or DQL LIKE on ciphertext column.
     *
     * @param 'ciphertext'|'plaintext' $secretMode
     *
     * @return list<MysqlAesNote>
     */
    public function findDoctrineListFiltered(
        ?string $titleLike,
        ?string $secretNeedle,
        string $secretMode = 'plaintext',
    ): array {
        $qb = $this->createQueryBuilder('n')->orderBy('n.id', 'DESC');

        if ($titleLike !== null) {
            $qb->andWhere('n.title LIKE :titleLike')->setParameter('titleLike', $titleLike);
        }

        if ($secretNeedle !== null && $secretMode === 'ciphertext') {
            $secretLike = self::buildLikePattern($secretNeedle);
            if ($secretLike !== null) {
                $qb->andWhere('n.secretOrm LIKE :secretLike')->setParameter('secretLike', $secretLike);
            }
        }

        /** @var list<MysqlAesNote> $notes */
        $notes = $qb->getQuery()->getResult();

        if ($secretNeedle !== null && $secretMode === 'plaintext') {
            $needle = mb_strtolower($secretNeedle);
            $notes  = array_values(array_filter(
                $notes,
                static fn (MysqlAesNote $note): bool => str_contains(
                    mb_strtolower($note->getSecretOrm() ?? ''),
                    $needle,
                ),
            ));
        }

        return $notes;
    }

    public static function buildLikePattern(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : '%' . addcslashes($trimmed, '%_\\') . '%';
    }

    public static function describeSqlLikeFilter(
        ?string $titleLike,
        ?string $secretLike,
        string $secretMode,
    ): string {
        $parts = [
            'SELECT id, title, CAST(AES_DECRYPT(secret_native, :key) AS CHAR) AS secret_plain',
            'FROM mysql_aes_note',
            'WHERE secret_native IS NOT NULL',
        ];

        if ($titleLike !== null) {
            $parts[] = 'AND title LIKE :titleLike';
        }

        if ($secretLike !== null) {
            if ($secretMode === 'ciphertext') {
                $parts[] = 'AND HEX(secret_native) LIKE :secretLike';
            } else {
                $parts[] = 'AND CAST(AES_DECRYPT(secret_native, :key) AS CHAR) LIKE :secretLike';
            }
        }

        $parts[] = 'ORDER BY id DESC';

        return implode("\n", $parts);
    }

    public static function describeDoctrineLikeFilter(
        ?string $titleLike,
        ?string $secretNeedle,
        string $secretMode,
    ): string {
        if ($secretMode === 'plaintext' && $secretNeedle !== null) {
            return "DQL: title LIKE (if set);\nsecret matched in PHP after Doctrine decrypt (SQL LIKE cannot search plaintext in secret_orm).";
        }

        $parts = ['SELECT n FROM MysqlAesNote n'];
        $where = [];

        if ($titleLike !== null) {
            $where[] = 'n.title LIKE :titleLike';
        }

        if ($secretNeedle !== null && $secretMode === 'ciphertext') {
            $where[] = 'n.secretOrm LIKE :secretLike  -- ciphertext + <ENC>; will not match plaintext';
        }

        if ($where !== []) {
            $parts[] = 'WHERE ' . implode(' AND ', $where);
        }

        $parts[] = 'ORDER BY n.id DESC';

        return implode("\n", $parts);
    }

    /**
     * Raw ciphertext as stored (HEX for display).
     *
     * @return list<array{id: int, title: string, secret_native_hex: ?string}>
     */
    public function findAllNativeRaw(): array
    {
        $this->assertNativeMysql();

        return $this->connection->fetchAllAssociative(
            'SELECT id, title, HEX(secret_native) AS secret_native_hex
             FROM mysql_aes_note
             WHERE secret_native IS NOT NULL
             ORDER BY id DESC',
        );
    }

    /**
     * Raw DB values vs decrypted (Doctrine secret_orm + native secret_native).
     *
     * @return list<array{
     *     id: int,
     *     title: string,
     *     secret_orm_raw: ?string,
     *     secret_orm_decrypted: ?string,
     *     secret_native_hex: ?string,
     *     secret_native_decrypted: ?string
     * }>
     */
    public function findAllStorageComparison(EncryptUtil $encryptUtil, EncryptorRegistry $encryptorRegistry): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, title, secret_orm, secret_native FROM mysql_aes_note ORDER BY id DESC',
        );

        $mysqlAes = $encryptorRegistry->get('mysql_aes');
        $result   = [];

        foreach ($rows as $row) {
            $nativeBlob = $this->normalizeBlob($row['secret_native'] ?? null);
            $ormRaw     = $row['secret_orm'] !== null && $row['secret_orm'] !== '' ? (string) $row['secret_orm'] : null;

            $result[] = [
                'id'                      => (int) $row['id'],
                'title'                   => (string) $row['title'],
                'secret_orm_raw'          => $ormRaw,
                'secret_orm_decrypted'    => $ormRaw !== null ? $encryptUtil->decrypt($ormRaw, 'mysql_aes') : null,
                'secret_native_hex'       => $nativeBlob !== null && $nativeBlob !== '' ? bin2hex($nativeBlob) : null,
                'secret_native_decrypted' => $this->decryptNativeBlob($nativeBlob, $mysqlAes),
            ];
        }

        return $result;
    }

    private function decryptNativeBlob(?string $blob, EncryptorInterface $mysqlAes): ?string
    {
        if ($blob === null || $blob === '') {
            return null;
        }

        if ($this->supportsNativeMysqlAes()) {
            $plain = $this->connection->fetchOne(
                'SELECT CAST(AES_DECRYPT(:blob, :key) AS CHAR)',
                ['blob' => $blob, 'key' => $this->mysqlAesKey],
                ['blob' => ParameterType::BINARY],
            );

            return $plain !== false ? (string) $plain : null;
        }

        return $mysqlAes->decrypt($blob);
    }

    private function normalizeBlob(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_resource($value)) {
            return stream_get_contents($value) ?: null;
        }

        return (string) $value;
    }

    private function assertNativeMysql(): void
    {
        if (!$this->supportsNativeMysqlAes()) {
            throw new RuntimeException('Native AES_ENCRYPT/AES_DECRYPT requires MySQL or MariaDB. Set DATABASE_URL to a MySQL DSN (see demo .env.example).');
        }
    }
}
