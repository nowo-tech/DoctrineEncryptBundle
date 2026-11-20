<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Functional\CleanChangeSet;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\AbstractManagerRegistry;
use Doctrine\Persistence\Proxy;
use LogicException;

/**
 * Manager registry over one entity manager, for transaction-doctrine's WriteUnit in the tests.
 */
final class SingleManagerRegistry extends AbstractManagerRegistry
{
    public function __construct(private readonly EntityManagerInterface $manager)
    {
        parent::__construct('test', ['default' => 'connection'], ['default' => 'manager'], 'default', 'default', Proxy::class);
    }

    protected function getService(string $name): object
    {
        return $name === 'connection' ? $this->manager->getConnection() : $this->manager;
    }

    protected function resetService(string $name): void
    {
        throw new LogicException('The tests never reset the manager.');
    }
}
