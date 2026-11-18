<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Command;

use Closure;
use InvalidArgumentException;

use function usleep;

/**
 * State of one run of a database-wide command (doctrine:encrypt:database, doctrine:decrypt:database): how its chunks
 * are locked and retried, and what they committed.
 *
 * A chunk is replayed after a lock conflict (deadlock, lock wait timeout, serialization failure), so the row callback
 * may see the same row more than once. Counters and reports therefore go through add() / record(): what an attempt
 * adds stays pending until its chunk commits, and is dropped when the attempt rolls back. Created per command
 * execution, never shared: safe in a long-running process.
 *
 * @internal used by the bundle's database commands
 */
final class ChunkedRun
{
    /** Seconds a chunk waits for a row lock before it gives up (and is retried). */
    public const DEFAULT_LOCK_TIMEOUT_SECONDS = 10;

    /** Attempts per chunk before the command stops with a lock conflict. */
    public const DEFAULT_MAX_ATTEMPTS = 3;

    private int $committedChunks = 0;

    /** @var array<string, int> */
    private array $totals = [];

    /** @var array<string, int> */
    private array $pendingTotals = [];

    /** @var array<string, list<string>> */
    private array $records = [];

    /** @var array<string, list<string>> */
    private array $pendingRecords = [];

    /** @var Closure(int): void Called after each committed chunk with its row count (progress bars) */
    private Closure $onChunkCommitted;

    /** @var Closure(int): void Called with the attempt number before a chunk is retried */
    private readonly Closure $backoff;

    /**
     * @param int $lockTimeoutSeconds Row lock wait per chunk, in seconds (0 = the server's own setting)
     * @param int $maxAttempts Attempts per chunk (1 = no retry)
     * @param (callable(int): void)|null $backoff Called with the attempt number before a retry (default: 100 ms per attempt)
     */
    public function __construct(
        public readonly int $lockTimeoutSeconds = self::DEFAULT_LOCK_TIMEOUT_SECONDS,
        public readonly int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS,
        ?callable $backoff = null,
    ) {
        if ($lockTimeoutSeconds < 0) {
            throw new InvalidArgumentException('The lock timeout must be zero or more seconds.');
        }
        if ($maxAttempts < 1) {
            throw new InvalidArgumentException('A chunk needs at least one attempt.');
        }
        $this->backoff = $backoff !== null ? $backoff(...) : static function (int $attempt): void {
            usleep(100_000 * $attempt);
        };
        $this->onChunkCommitted = static function (int $rows): void {
        };
    }

    /**
     * Sets the callback run after each committed chunk, with the chunk's row count (e.g. a progress bar).
     *
     * @param callable(int): void $callback
     */
    public function onChunkCommitted(callable $callback): void
    {
        // @igor-ignore - Per-run value object created by each command execution (not a service), never shared.
        $this->onChunkCommitted = $callback(...);
    }

    /**
     * Adds to a counter of the current attempt (kept only if its chunk commits).
     */
    public function add(string $counter, int $amount = 1): void
    {
        // @igor-ignore - Per-run value object created by each command execution (not a service), never shared.
        $this->pendingTotals[$counter] = ($this->pendingTotals[$counter] ?? 0) + $amount;
    }

    /**
     * Records an item for a report of the current attempt (kept only if its chunk commits).
     */
    public function record(string $report, string $item): void
    {
        // @igor-ignore - Per-run value object created by each command execution (not a service), never shared.
        $this->pendingRecords[$report][] = $item;
    }

    /**
     * Committed value of a counter.
     */
    public function total(string $counter): int
    {
        return $this->totals[$counter] ?? 0;
    }

    /**
     * Committed items of a report.
     *
     * @return list<string>
     */
    public function records(string $report): array
    {
        return $this->records[$report] ?? [];
    }

    /**
     * Number of chunks committed so far in this run (any table).
     */
    public function committedChunks(): int
    {
        return $this->committedChunks;
    }

    /**
     * Starts an attempt: drops whatever a rolled-back attempt left pending.
     */
    public function beginAttempt(): void
    {
        // @igor-ignore - Per-run value object created by each command execution (not a service), never shared.
        $this->pendingTotals = [];
        // @igor-ignore - Per-run value object created by each command execution (not a service), never shared.
        $this->pendingRecords = [];
    }

    /**
     * The chunk of the current attempt committed: keeps its counters and reports.
     */
    public function commitAttempt(int $rows): void
    {
        foreach ($this->pendingTotals as $counter => $amount) {
            // @igor-ignore - Per-run value object created by each command execution (not a service), never shared.
            $this->totals[$counter] = ($this->totals[$counter] ?? 0) + $amount;
        }
        foreach ($this->pendingRecords as $report => $items) {
            // @igor-ignore - Per-run value object created by each command execution (not a service), never shared.
            $this->records[$report] = [...($this->records[$report] ?? []), ...$items];
        }
        $this->beginAttempt();
        // @igor-ignore - Per-run value object created by each command execution (not a service), never shared.
        ++$this->committedChunks;
        ($this->onChunkCommitted)($rows);
    }

    /**
     * Waits before the next attempt of a chunk.
     */
    public function backoff(int $attempt): void
    {
        ($this->backoff)($attempt);
    }
}
