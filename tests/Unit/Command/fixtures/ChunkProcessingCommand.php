<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Command\fixtures;

use Doctrine\DBAL\Connection;
use Nowo\DoctrineEncryptBundle\Command\AbstractCommand;
use Nowo\DoctrineEncryptBundle\Command\ChunkedRun;
use Nowo\DoctrineEncryptBundle\Exception\ChunkLockConflictException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Exposes the chunk helpers of AbstractCommand to the tests.
 *
 * @internal
 */
final class ChunkProcessingCommand extends AbstractCommand
{
    /**
     * @param array<int, string> $selectCols Quoted columns
     * @param array<int, string> $idCols Quoted identifier columns
     * @param callable(array<string, mixed>): void $processRow
     */
    public function process(Connection $conn, string $table, array $selectCols, array $idCols, int $batchSize, callable $processRow, ?ChunkedRun $run = null): void
    {
        $this->processTableInBatches($conn, $table, $selectCols, $idCols, $batchSize, $processRow, $run);
    }

    public function lockTimeout(InputInterface $input): ?int
    {
        return $this->resolveLockTimeout($input);
    }

    public function report(OutputInterface $output, ChunkLockConflictException $conflict): void
    {
        $this->reportChunkConflict($output, $conflict);
    }
}
