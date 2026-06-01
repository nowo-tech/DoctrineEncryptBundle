# MySQL AES_ENCRYPT / AES_DECRYPT (`MysqlAes` encryptor)

The bundle provides a **`MysqlAes`** encryptor that uses **AES-128-ECB** with the same key padding as MySQL’s [`AES_ENCRYPT()`](https://dev.mysql.com/doc/refman/8.0/en/encryption-functions.html) / [`AES_DECRYPT()`](https://dev.mysql.com/doc/refman/8.0/en/encryption-functions.html) when `block_encryption_mode` is `aes-128-ecb` (default).

## Configuration

```yaml
nowo_doctrine_encrypt:
    default_config: default
    configs:
        mysql_aes:
            encryptor_class: MysqlAes
            secret_key_env_var: '%env(MYSQL_AES_KEY)%'
            # or secret_directory_path: '%kernel.project_dir%'
            # secret_key_filename: '.MysqlAes.mysql_aes.key'
```

Generate a passphrase file:

```bash
php bin/console doctrine:encrypt:generate-secret-key mysql_aes
```

Use the encryptor on entity properties:

```php
#[Encrypted('mysql_aes')]
private ?string $secret = null;
```

Doctrine will encrypt/decrypt in PHP (same algorithm as MySQL). The subscriber still appends the `<ENC>` marker for ORM-managed fields.

## Native SQL (repository / DQL)

For **pure** `AES_ENCRYPT` / `AES_DECRYPT` in SQL (no `<ENC>` marker), use a **BLOB** (or VARBINARY) column and bind the **passphrase** (not the derived key bytes):

```sql
INSERT INTO my_table (title, secret_native)
VALUES (:title, AES_ENCRYPT(:plain, :passphrase));

SELECT id, title,
       CAST(AES_DECRYPT(secret_native, :passphrase) AS CHAR) AS secret_plain
FROM my_table;
```

The demo apps (`demo/symfony7` and `demo/symfony8`) include `MysqlAesNoteRepository` with these patterns. **Native SQL requires MySQL or MariaDB**; SQLite does not provide `AES_ENCRYPT`.

### LIKE filters (demo lists)

On `/mysql-aes-note` and `/mysql-aes-note/sql` you can filter rows to experiment with `LIKE`:

**Preset data:** run `make fixtures` (or `make setup`) to load sample rows — 3 via Doctrine (`secret_orm`) and 2 via native AES (`secret_native`). Try filtering title `invoice` or secret `find-me` / `password`.

**Raw vs decrypted:** `/mysql-aes-note/db-values` shows what is stored in the database (ciphertext / HEX) next to decrypted values for both columns. Tabs: *Both*, *Encrypted only*, *Decrypted only*.

- **Title:** `title LIKE '%…%'` (works; column is plain text).
- **Secret (SQL, decrypted):** `CAST(AES_DECRYPT(secret_native, :key) AS CHAR) LIKE '%…%'` (works on MySQL; decrypts per row).
- **Secret (SQL, ciphertext):** `HEX(secret_native) LIKE '%…%'` (only matches hex patterns, not plaintext).
- **Secret (Doctrine, ciphertext):** `secret_orm LIKE '%…%'` searches stored ciphertext (with `<ENC>` marker); it does **not** match the original plaintext.
- **Secret (Doctrine, after decrypt):** the demo filters in PHP after load (typical app pattern when you cannot decrypt in SQL).

`LIKE '%foo%'` on encrypted columns without decrypting will not find rows by secret content.

## Performance

Relative cost and scalability differ strongly between encryptors and query styles. At a glance:

| Operation | Relative cost | Notes |
|-----------|---------------|--------|
| **Doctrine + MysqlAes** (persist/load) | Moderate CPU in PHP | Same subscriber path as Halite/Defuse; OpenSSL AES per field |
| **`AES_ENCRYPT` / `AES_DECRYPT` in SQL** (write/read in DB) | Write: offloaded to MySQL; Read: DB decrypt if you `SELECT` decrypted expressions | No `<ENC>` marker on native columns |
| **`title LIKE '%…%'`** | **Low** (indexable plain column) | Preferred way to narrow rows before touching secrets |
| **`CAST(AES_DECRYPT(...) AS CHAR) LIKE '%…%'`** | **High** — full table scan, decrypt per row | Works for plaintext match; does not scale on large tables |
| **`HEX(secret_native) LIKE '%…%'`** | High scan, **wrong semantics** for user text | Only matches hex patterns |
| **Doctrine list + PHP filter on decrypted secret** | **High** — load + decrypt **all** candidates (demo “after decrypt” mode) | Typical app pattern for small result sets |
| **`secret_orm LIKE '%…%'`** | Index may apply to ciphertext only | Does **not** search plaintext; misleading if treated as “search” |

For full comparison (Halite vs Defuse vs MysqlAes, batch commands, and production guidance), see **[PERFORMANCE.md](PERFORMANCE.md)**.

## Security notes

- Prefer **MySQL 8+** and review `block_encryption_mode`; ECB mode does not hide patterns in repeated plaintext.
- Use a strong, unique passphrase; store it in env or a secrets manager, not in git.
- `AES_ENCRYPT` is not authenticated encryption (unlike Halite/Defuse in this bundle). Use Halite/Defuse for new application-level encryption unless you must interoperate with legacy MySQL functions.

See also [CONFIGURATION.md](CONFIGURATION.md) and the demo routes under `/mysql-aes-note` (ports **8007** / **8008**).
