# Concurrency

How the bundle stays correct when the same rows are written at the same time (REQ-RUNTIME-003).

## Table of contents

- [Summary](#summary)
- [Write paths](#write-paths)
- [How a command chunk works](#how-a-command-chunk-works)
- [Conflict codes](#conflict-codes)
- [Host options](#host-options)
- [Maintenance mode](#maintenance-mode)
- [Residual risks](#residual-risks)
- [Tests and CI](#tests-and-ci)
- [History](#history)

## Summary

- **At runtime the bundle never writes on its own.** The listener (`DoctrineEncryptSubscriber`) and the encrypted
  DBAL types only transform values inside the host's own `flush()`. The host's transaction, locking and conflict
  answer apply unchanged. The bundle never flushes, clears or resets the host's entity manager.
  `ClosedEntityManagerRecoveryListener` resets a manager only when it is already closed (REQ-RUNTIME-002 rule 4),
  and only the managers the bundle encrypts for.
- **The bundle has no HTTP routes**, so it gives no 409 answer of its own. A lock conflict in a host flush is the
  host's (for example transaction-doctrine's `ConcurrencyConflictResponder`).
- **The operator commands write through DBAL**, in chunks, one transaction per chunk. Each chunk locks its rows, so an
  application write is never overwritten. Lock conflicts are retried, then answered with `CONFLICT_001` or
  `CONFLICT_002` in the command output.

## Write paths

| Path | Transaction | Concurrency control (rule 4) | Conflict answer (rule 6) |
| --- | --- | --- | --- |
| Listener: encrypt on `onFlush` / `preUpdate`, decrypt on `postLoad` / `postUpdate` / `postFlush` | The host's flush | The host's (version column, locks). The bundle changes values only, never the write set | The host's |
| Encrypted DBAL types (`encrypted_string`, `encrypted_text`, `encrypted_json`) | The host's flush | The host's | The host's |
| `doctrine:encrypt:database` | One per chunk (`batch_size` rows), through transaction-doctrine's `DoctrineTransactionRunner::forConnection()` | Keyset page of ids (no lock), then `SELECT … FOR UPDATE` of the chunk's rows by id, in id order, inside the transaction; the value is re-checked under the lock (already marked values are skipped) | Replay of the chunk (3 attempts), then exit code `1` with `CONFLICT_001` / `CONFLICT_002` |
| `doctrine:decrypt:database` | Same | Same (values without the marker are skipped) | Same |
| `doctrine:encrypt:rotate-keys` | Runs the decrypt and encrypt commands above | Same, plus [maintenance mode](#maintenance-mode) | Same (the step's exit code stops the rotation) |
| `doctrine:encrypt:generate-secret-key`, `--backup` of `rotate-keys` | No database write (key files, backup files) | Key files are written with restrictive permissions; run one rotation at a time | — |
| `doctrine:encrypt:status` | Read only | — | — |

There is no counter, sequence or "only one" rule in the bundle, and no Messenger handler.

## How a command chunk works

`AbstractCommand::processTableInBatches()`:

1. Reads the ids of the next chunk without locks: `SELECT id … WHERE id > :last ORDER BY id LIMIT :batch` (composite
   ids use the expanded row comparison `(a > ?) OR (a = ? AND b > ?)`). Rows inserted or deleted meanwhile never shift
   the pages.
2. Opens the chunk transaction (transaction-doctrine runner: rollback to the entry level, savepoint-aware, transient
   failures classified) and re-reads the rows by id with a row lock, in id order (a fixed lock order):
   - MySQL, MariaDB, PostgreSQL (and other platforms): `SELECT … WHERE id IN (…) ORDER BY id FOR UPDATE`;
   - SQL Server: `FROM t WITH (UPDLOCK, ROWLOCK)`;
   - SQLite: no row locks. The write transaction locks the database file, and a concurrent writer gets
     `SQLITE_BUSY` (a lock wait timeout, retried) instead of overwriting the chunk.

   The lookup is by primary key, so only those rows are locked: no gap or next-key locks on the table.
3. Encrypts or decrypts the locked, current values and writes them by id. An application write to one of these rows
   either committed before the locked read (and is seen: an already encrypted value is skipped) or waits for the
   chunk's commit and lands after it.
4. Commits. Counters and the decrypt failure report count only committed chunks.

The lock wait is bounded by `--lock-timeout` (rule 9). No HTTP call, mail or file I/O runs while the chunk holds its
locks; the encryption itself is CPU only.

## Conflict codes

A deadlock (MySQL / MariaDB 1213, PostgreSQL 40P01), lock wait timeout (1205, 55P03, SQLite busy) or serialization
failure (40001, MariaDB 1020) inside a chunk rolls the chunk back. The chunk is replayed up to 3 times, 100 ms × the
attempt number apart. When it is still blocked the command prints:

| Code | When | Meaning |
| --- | --- | --- |
| `CONFLICT_001` | No chunk of the run was committed | Nothing changed. Running the command again is the same as the first run. |
| `CONFLICT_002` | Earlier chunks were committed | The run stopped half way: the earlier chunks are committed, the blocked chunk was rolled back. Run the command again to resume: values already processed are skipped. |

It then exits with code `1` (`ChunkLockConflictException`, a bundle `ExceptionInterface`, carries `conflictCode` and
the table). Unique-constraint violations and lost connections are not conflicts: they are rethrown unchanged, and the
chunk is rolled back.

## Host options

| Option | Default | Effect |
| --- | --- | --- |
| `--lock-timeout=SECONDS` on `doctrine:encrypt:database`, `doctrine:decrypt:database`, `doctrine:encrypt:rotate-keys` | `10` | How long a chunk waits for a row lock. MySQL / MariaDB: `innodb_lock_wait_timeout` for the session, restored after each table. PostgreSQL: `SET LOCAL lock_timeout` per chunk. `0` keeps the server's setting (MySQL 50 s; PostgreSQL waits without limit). SQL Server and SQLite keep their own setting. |
| `batchSize` argument / `nowo_doctrine_encrypt.batch_size` | `5` | Rows per chunk, so rows locked at once. Smaller chunks hold locks for less time. |

## Maintenance mode

The commands are safe against concurrent writes, but two things still need the writers stopped:

- **Key rotation (`doctrine:encrypt:rotate-keys`) requires maintenance mode.** Between the decrypt and re-encrypt
  steps the data is in plain text. Every running process (PHP-FPM, FrankenPHP workers with or without kernel reset,
  Messenger consumers) still holds the old key, so a value it writes meanwhile is encrypted with the old key. The
  re-encrypt step leaves such a value unchanged and reports it ("end with `<ENC>` but do not decrypt"), and the new key
  cannot read it: keep the old key backup until those values are fixed. Stop the writers before the first step and
  restart them after the last one.
- **A full `doctrine:decrypt:database` on a live application** decrypts what is stored when each chunk runs; the
  listener still encrypts what the application writes afterwards. Disable encryption in the application, or stop it,
  if the goal is a fully decrypted database.

`doctrine:encrypt:database` on a live application is safe: the listener already writes encrypted values, and the
command skips them.

## Residual risks

- **Lock waits on the application.** A chunk holds the locks of up to `batch_size` rows until it commits (milliseconds
  per row). An application transaction that writes one of them waits that long. Keep `batch_size` small on busy
  tables.
- **Chunk retries are bounded.** On a table that is written very often a chunk can stay blocked for its 3 attempts;
  the command then stops with `CONFLICT_002` and resumes when run again.
- **Platforms without CI.** SQL Server (`UPDLOCK, ROWLOCK` hint) and Oracle / DB2 (`FOR UPDATE`) are covered by
  unit tests of the generated SQL only; the lock timeout option does not apply to them.
- **Key rotation** needs maintenance mode, see above. This is a documented precondition, not a lock.

## Tests and CI

- `tests/Database/ChunkLockDatabaseTest.php`, on MySQL 8.4, MariaDB 11.4 and PostgreSQL 16, with two connections or a
  second process (`tests/Database/Support/concurrent_writer.php`); every session sets a lock timeout so a hang fails
  fast:
  - an application write made by another process during a chunk waits for the chunk and is not overwritten;
  - a row locked for longer than the lock timeout: `CONFLICT_001` when it is in the first chunk (nothing changed),
    `CONFLICT_002` when it is in a later chunk (earlier chunks committed, the session lock timeout restored), and the
    rerun resumes;
  - a chunk replayed after the lock is released counts each value once;
  - a real deadlock with an application transaction that writes two rows of the chunk in the opposite order loses
    nothing, whichever side the server picks as the victim.
- `tests/Unit/Command/ChunkedProcessingTest.php`: keyset paging (single and composite ids, rows inserted and deleted
  during a run), the locked SQL and lock timeout per platform, replay and the 001 / 002 codes.
- CI job **Databases** (MySQL 8.4, MariaDB 11.4, PostgreSQL 16; DBAL 4 and DBAL 3), `--fail-on-skipped`, a required
  check on `master`.

## History

- **2.0.7 – 2.10.1, KA-H1:** the database commands ignored listener-encrypted fields inside embeddables during a key
  rotation (data loss for those fields). Fixed in 2.11.0; CHANGELOG entry only, no GHSA (owner decision 2026-11-10).
- **Up to 2.13.0:** the database commands read each chunk without locks, outside the chunk transaction, so an
  application write to a row between the read and the command's `UPDATE` was overwritten when the commands ran on a
  live application. Fixed in the release after 2.13.0 by the locked chunks described above.
