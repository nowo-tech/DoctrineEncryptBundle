<?php

namespace Nowo\DoctrineEncryptBundle\Tests\Functional;

use Doctrine\DBAL\DriverManager;
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

/**
 * @property \Doctrine\DBAL\Logging\DebugStack|SqlQueryCollector $sqlLoggerStack
 */
abstract class AbstractFunctionalTestCase extends TestCase
{
    /** @var DoctrineEncryptSubscriber */
    protected $subscriber;
    /** @var EncryptorInterface */
    protected $encryptor;
    /** @var false|string */
    protected $dbFile;
    /** @var EntityManager */
    protected $entityManager;
    /** @var \Doctrine\DBAL\Logging\DebugStack|SqlQueryCollector */
    protected $sqlLoggerStack;

    abstract protected function getEncryptor(): EncryptorInterface;

    public function setUp(): void
    {
        $paths = [__DIR__ . '/fixtures/Entity'];
        if (class_exists(ORMSetup::class)) {
            $config = ORMSetup::createAttributeMetadataConfiguration($paths, true);
        } else {
            $config = new \Doctrine\ORM\Configuration();
            $config->setMetadataDriverImpl(new AttributeDriver($paths));
            $config->setProxyDir(sys_get_temp_dir() . '/doctrine_orm_proxies');
            $config->setProxyNamespace('Doctrine\Tests\Proxies');
            $config->setAutoGenerateProxyClasses(true);
        }

        $this->dbFile = tempnam(sys_get_temp_dir(), 'nowo_encrypt_db');
        $conn = [
            'driver' => 'pdo_sqlite',
            'path' => $this->dbFile,
        ];

        $useDbal4 = !class_exists(\Doctrine\DBAL\Logging\DebugStack::class);

        if (!$useDbal4 && method_exists(EntityManager::class, 'create')) {
            $this->entityManager = EntityManager::create($conn, $config);
            $this->sqlLoggerStack = new \Doctrine\DBAL\Logging\DebugStack();
            $this->entityManager->getConnection()->getConfiguration()->setSQLLogger($this->sqlLoggerStack);
        } else {
            $sqlCollector = new SqlQueryCollector();
            $dbalConfig = new \Doctrine\DBAL\Configuration();
            if (class_exists(\Doctrine\DBAL\Logging\Middleware::class)) {
                $dbalConfig->setMiddlewares([new \Doctrine\DBAL\Logging\Middleware($sqlCollector)]);
            }
            $connection = DriverManager::getConnection($conn, $dbalConfig);
            $this->entityManager = new EntityManager($connection, $config);
            $this->sqlLoggerStack = $sqlCollector;
        }

        $schemaTool = new SchemaTool($this->entityManager);
        $classes = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($classes);
        $schemaTool->createSchema($classes);

        $this->encryptor = $this->getEncryptor();
        $this->subscriber = new DoctrineEncryptSubscriber($this->encryptor);
        $eventManager = $this->entityManager->getEventManager();
        $eventManager->addEventListener(
            [
                Events::postUpdate,
                Events::preUpdate,
                Events::postLoad,
                Events::onFlush,
                Events::preFlush,
                Events::postFlush,
            ],
            $this->subscriber
        );

        error_reporting(E_ALL);
    }

    public function tearDown(): void
    {
        $this->entityManager->getConnection()->close();
        unlink($this->dbFile);
    }

    protected function getLatestInsertQuery(): ?array
    {
        $insertQueries = array_values(array_filter($this->sqlLoggerStack->queries, static function ($queryData) {
            return stripos($queryData['sql'], 'INSERT ') === 0;
        }));

        return current(array_reverse($insertQueries)) ?: null;
    }

    protected function getLatestUpdateQuery(): ?array
    {
        $insertQueries = array_values(array_filter($this->sqlLoggerStack->queries, static function ($queryData) {
            return stripos($queryData['sql'], 'UPDATE ') === 0;
        }));

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
     * Asserts that a string starts with a given prefix.
     *
     * @param string $stringn
     * @param string $string
     * @param string $message
     */
    public function assertStringDoesNotContain($needle, $string, $ignoreCase = false, $message = ''): void
    {
        if (!\is_string($needle)) {
            throw new \InvalidArgumentException('Argument 1 must be a string');
        }

        if (!\is_string($string)) {
            throw new \InvalidArgumentException('Argument 2 must be a string');
        }

        if (!\is_bool($ignoreCase)) {
            throw new \InvalidArgumentException('Argument 3 must be a boolean');
        }

        $constraint = new LogicalNot(new StringContains(
            $needle,
            $ignoreCase
        ));

        static::assertThat($string, $constraint, $message);
    }
}
