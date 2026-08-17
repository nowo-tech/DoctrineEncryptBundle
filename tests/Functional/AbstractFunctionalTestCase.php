<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Functional;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use PHPUnit\Framework\Constraint\LogicalNot;
use PHPUnit\Framework\Constraint\StringContains;
use PHPUnit\Framework\TestCase;

use function count;

use const PHP_VERSION_ID;

abstract class AbstractFunctionalTestCase extends TestCase
{
    protected DoctrineEncryptSubscriber $subscriber;
    protected EncryptorInterface $encryptor;
    protected string $dbFile;
    protected EntityManager $entityManager;
    protected SqlQueryCollector $sqlLoggerStack;

    abstract protected function getEncryptor(): EncryptorInterface;

    /**
     * Symfony 8 removed LazyGhost helpers from var-exporter; on PHP 8.4+ use native lazy objects.
     */
    private function configureOrmLazyObjects(Configuration $config): void
    {
        if (PHP_VERSION_ID >= 80400) {
            $config->enableNativeLazyObjects(true);
        }
    }

    protected function setUp(): void
    {
        $paths = [__DIR__ . '/fixtures/Entity'];
        if (class_exists(ORMSetup::class)) {
            $config = ORMSetup::createAttributeMetadataConfiguration($paths, true);
            $this->configureOrmLazyObjects($config);
        } else {
            $config = new Configuration();
            $config->setMetadataDriverImpl(new AttributeDriver($paths));
            $config->setProxyDir(sys_get_temp_dir() . '/doctrine_orm_proxies');
            $config->setProxyNamespace('Doctrine\Tests\Proxies');
            $config->setAutoGenerateProxyClasses(true);
        }

        $dbFile       = tempnam(sys_get_temp_dir(), 'nowo_encrypt_db');
        $this->dbFile = $dbFile !== false ? $dbFile : sys_get_temp_dir() . '/nowo_encrypt_db_' . uniqid('', true);
        $conn         = [
            'driver' => 'pdo_sqlite',
            'path'   => $this->dbFile,
        ];

        $sqlCollector = new SqlQueryCollector();
        $dbalConfig   = new \Doctrine\DBAL\Configuration();
        if (class_exists(Middleware::class)) {
            $dbalConfig->setMiddlewares([new Middleware($sqlCollector)]);
        }
        $connection           = DriverManager::getConnection($conn, $dbalConfig);
        $this->entityManager  = new EntityManager($connection, $config);
        $this->sqlLoggerStack = $sqlCollector;

        $schemaTool = new SchemaTool($this->entityManager);
        $classes    = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($classes);
        $schemaTool->createSchema($classes);

        $this->encryptor  = $this->getEncryptor();
        $this->subscriber = new DoctrineEncryptSubscriber($this->encryptor);
        $eventManager     = $this->entityManager->getEventManager();
        $eventManager->addEventListener(
            [
                Events::postUpdate,
                Events::preUpdate,
                Events::postLoad,
                Events::onFlush,
                Events::preFlush,
                Events::postFlush,
            ],
            $this->subscriber,
        );
    }

    protected function tearDown(): void
    {
        $this->entityManager->getConnection()->close();
        if ($this->dbFile !== '') {
            unlink($this->dbFile);
        }
    }

    /** @return array<string, mixed>|null */
    protected function getLatestInsertQuery(): ?array
    {
        $insertQueries = array_values(array_filter($this->sqlLoggerStack->queries, static fn (array $queryData) => stripos($queryData['sql'], 'INSERT ') === 0));

        return current(array_reverse($insertQueries)) ?: null;
    }

    /** @return array<string, mixed>|null */
    protected function getLatestUpdateQuery(): ?array
    {
        $insertQueries = array_values(array_filter($this->sqlLoggerStack->queries, static fn (array $queryData) => stripos($queryData['sql'], 'UPDATE ') === 0));

        return current(array_reverse($insertQueries)) ?: null;
    }

    /**
     * Using the SQL Logger Stack this method retrieves the current query count executed in this test.
     */
    protected function getCurrentQueryCount(): int
    {
        return count($this->sqlLoggerStack->queries);
    }

    /**
     * Asserts that a string does not contain a given needle.
     */
    public function assertStringDoesNotContain(string $needle, string $string, bool $ignoreCase = false, string $message = ''): void
    {
        $constraint = new LogicalNot(new StringContains(
            $needle,
            $ignoreCase,
        ));

        self::assertThat($string, $constraint, $message);
    }
}
