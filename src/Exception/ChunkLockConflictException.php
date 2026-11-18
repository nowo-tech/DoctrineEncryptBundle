<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Exception;

use Nowo\TransactionDoctrine\ConflictCode;
use RuntimeException;
use Throwable;

use function sprintf;

/**
 * A database-wide command could not lock the rows of a chunk (deadlock, lock wait timeout or serialization failure)
 * after all its attempts. The chunk was rolled back; chunks committed before it stay committed.
 *
 * The code follows REQ-RUNTIME-003 rule 6: CONFLICT_001 when nothing of the run was committed (running the command
 * again is the same as the first run), CONFLICT_002 when earlier chunks were committed (the run stopped half way:
 * running the command again resumes it, since values already processed are skipped). The message never contains SQL,
 * parameters or values; the storage error stays in the previous exception, for the logs only.
 */
final class ChunkLockConflictException extends RuntimeException implements ExceptionInterface
{
    private function __construct(
        string $message,
        public readonly ConflictCode $conflictCode,
        public readonly string $table,
        ?Throwable $previous,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * @param string $table Quoted table name of the chunk
     * @param int $attempts Attempts made on the chunk
     * @param bool $committed Whether the run committed earlier chunks
     * @param Throwable $previous The last transient storage failure
     */
    public static function afterAttempts(string $table, int $attempts, bool $committed, Throwable $previous): self
    {
        $code = $committed ? ConflictCode::Committed : ConflictCode::RetrySafe;

        return new self(
            sprintf('Could not lock a chunk of rows of %s after %d attempt(s): another transaction holds them (lock conflict) [%s].', $table, $attempts, $code->value),
            $code,
            $table,
            $previous,
        );
    }
}
