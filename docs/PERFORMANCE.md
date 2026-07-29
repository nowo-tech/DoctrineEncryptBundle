# Performance and scalability

This page compares **qualitative** performance characteristics of the encryptors and query patterns supported by this bundle. It is not a benchmark report: absolute timings depend on hardware, PHP version, `ext-sodium` / OpenSSL, row size, and MySQL configuration. Use it to choose an approach and to avoid expensive patterns in production.

See also [MYSQL_AES.md](MYSQL_AES.md) (MySQL-native encryption and LIKE demos) and the symfony8 demo at `/mysql-aes-note`.

## Table of contents

- [Summary table (at rest: persist / load)](#summary-table-at-rest-persist-load)
- [Console batch commands](#console-batch-commands)
- [Search, `LIKE`, and filtering](#search-like-and-filtering)
  - [Relative cost (typical ordering, worst → best for large tables)](#relative-cost-typical-ordering-worst-best-for-large-tables)
- [Encryptor choice vs performance](#encryptor-choice-vs-performance)
- [Practical recommendations](#practical-recommendations)
- [Related documentation](#related-documentation)

## Summary table (at rest: persist / load)

| Approach | Where crypto runs | Typical CPU per field | Security (default) | Best for |
|----------|-------------------|------------------------|--------------------|----------|
| **Halite** (Doctrine) | PHP (`ext-sodium` or compat) | **Lowest** among bundle encryptors | Strong (authenticated) | New applications, default choice |
| **Defuse** (Doctrine) | PHP (OpenSSL) | Moderate | Strong (authenticated) | When Defuse is already standard in the project |
| **MysqlAes** (Doctrine) | PHP (`openssl_encrypt`, AES-128-ECB) | Moderate | Weaker (ECB, no auth) | Interop with legacy MySQL `AES_*` data |
| **`AES_ENCRYPT` in SQL** | MySQL server on `INSERT`/`UPDATE` | **No PHP cost on that write** | Same as MysqlAes | Bulk load / legacy SQL pipelines; reads still need decrypt somewhere |
| **EncryptUtil** | PHP (same as configured encryptor) | Same as chosen encryptor | Same as chosen encryptor | One-off encrypt/decrypt outside entities |

**Read path (Doctrine entities):** For every `#[Encrypted]` property, the subscriber decrypts in PHP when the entity is loaded (and encrypts on flush). That cost applies to **all** encryptors, including MysqlAes. Native SQL only avoids PHP on the **write** path for columns you manage manually.

**Stored size:** Halite/Defuse ciphertext is typically **larger** than plaintext (nonce + auth tag or wrapping). MysqlAes / MySQL `AES_ENCRYPT` output is closer to block-aligned binary size; ORM fields still add the `<ENC>` marker when managed by the bundle.

## Console batch commands

`doctrine:encrypt:database` and `doctrine:decrypt:database` process rows in batches (`batch_size` in config, default `5`). Performance is dominated by:

1. **Round-trips** to the database (raise `batch_size` cautiously if memory allows).
2. **Per-row, per-field** encrypt/decrypt in PHP for every `#[Encrypted]` column.

MysqlAes batch work is often **faster per byte** than Halite/Defuse on CPU, but Halite with `ext-sodium` is still usually competitive; prefer **security** over small CPU savings unless you must use MySQL-compatible AES.

## Search, `LIKE`, and filtering

Encrypted columns **cannot** use a normal B-tree index to search by **plaintext** content. Any pattern that finds secrets by meaning either decrypts data or scans ciphertext without matching user intent.

| Pattern | Example | Index-friendly? | Cost as table grows | Finds secret by plaintext? |
|---------|---------|-----------------|---------------------|----------------------------|
| **Plain column `LIKE`** | `title LIKE '%inv%'` | **Yes** (if `title` is indexed) | **Best** — index range/scan on plain text | N/A (not encrypted) |
| **`LIKE` on ciphertext column** | `secret_orm LIKE '%pass%'` | May use index on column, but matches **ciphertext**, not plaintext | Can look “fast” and return **wrong/empty** results | **No** |
| **Filter in PHP after load** | Load entities → `str_contains` on decrypted property | No | **O(n)** rows loaded + **decrypt every candidate row** in PHP | **Yes**, poor at scale |
| **`AES_DECRYPT(...) LIKE` in SQL** | `CAST(AES_DECRYPT(col, :key) AS CHAR) LIKE '%pass%'` | **No** — expression on column, full scan | **Worst** for large tables: decrypt **each row** in the server | **Yes** |
| **`HEX(blob) LIKE`** | `HEX(secret_native) LIKE '%AB%'` | No | Full scan; only matches **hex** of ciphertext | **No** (not plaintext) |

The symfony8 demo implements these modes on `/mysql-aes-note` and `/mysql-aes-note/sql` so you can compare behaviour and load on a small dataset.

### Relative cost (typical ordering, worst → best for large tables)

1. **`WHERE CAST(AES_DECRYPT(...) AS CHAR) LIKE '%…%'`** — decrypt per row in MySQL; no useful index on plaintext; high CPU and I/O on the DB.
2. **Load many rows + decrypt in PHP + filter** — same asymptotic problem, plus ORM/hydration overhead and memory for entities.
3. **`LIKE` on encrypted column (ciphertext)** — may still scan many rows; **incorrect** for searching secrets by value.
4. **`title LIKE` (or other non-encrypted filter)** — use this to narrow the set first, then decrypt only remaining rows in application code if you must.
5. **Dedicated search design** (not provided by this bundle): separate searchable hash/token, external index (OpenSearch, etc.), or accept “no full-text search on secret fields”.

## Encryptor choice vs performance

| Concern | Prefer |
|---------|--------|
| Lowest PHP overhead + strongest crypto | **Halite** with `ext-sodium` |
| Already standardized on Defuse | **Defuse** |
| Must read/write same bytes as MySQL `AES_ENCRYPT` | **MysqlAes** or raw SQL; accept ECB limitations |
| Minimize PHP work on bulk **insert** only | **`AES_ENCRYPT` in SQL** for that column; plan read/decrypt strategy separately |
| Production search on secret field content | **Do not** rely on `LIKE` on encrypted columns; see [Roadmap](ROADMAP.md) |

## Practical recommendations

1. **Default:** `Halite` for `#[Encrypted]` fields; keep filterable metadata (email domain, country code, etc.) in **plain** columns if the product needs SQL `LIKE` or `ORDER BY`.
2. **MysqlAes / MySQL functions:** Use for interoperability or migration, not as a performance upgrade over Halite.
3. **Demos:** Use the LIKE filters to learn behaviour on tens of rows; do not extrapolate to millions of rows without profiling and EXPLAIN.
4. **Profiling:** For your schema, run `EXPLAIN` on filtered queries and measure wall time with realistic row counts before shipping `AES_DECRYPT` in `WHERE`.

## Related documentation

- [Configuration → Encryptors](CONFIGURATION.md#encryptors)
- [MySQL AES_ENCRYPT / AES_DECRYPT](MYSQL_AES.md)
- [Usage](USAGE.md#searching-and-performance)
- [Roadmap → Search on encrypted fields](ROADMAP.md)
