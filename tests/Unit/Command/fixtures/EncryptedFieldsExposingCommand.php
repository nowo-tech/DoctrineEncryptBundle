<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Command\fixtures;

use Nowo\DoctrineEncryptBundle\Command\AbstractCommand;
use ReflectionProperty;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Exposes the encrypted-field helpers of AbstractCommand to tests.
 */
final class EncryptedFieldsExposingCommand extends AbstractCommand
{
    /** @return array<int, array{property: ReflectionProperty, config: string}> */
    public function withConfig(object $metadata): array
    {
        return $this->getEncryptionablePropertiesWithConfig($metadata, 'default_profile');
    }

    /** @return list<array{field: string, config: string, type: string}> */
    public function typeFields(object $metadata): array
    {
        return $this->getEncryptedTypeFields($metadata, 'default_profile');
    }

    public function embeddable(object $metadata): bool
    {
        return $this->isEmbeddable($metadata);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return self::SUCCESS;
    }
}
