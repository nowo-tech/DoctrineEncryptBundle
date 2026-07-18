# MySQL AES_ENCRYPT / AES_DECRYPT (`MysqlAes` encryptor)

The bundle provides a **`MysqlAes`** encryptor that uses **AES-128-ECB** with the same key padding as MySQL’s [`AES_ENCRYPT()`](https://dev.mysql.com/doc/refman/8.0/en/encryption-functions.html) / [`AES_DECRYPT()`](https://dev.mysql.com/doc/refman/8.0/en/encryption-functions.html) when `block_encryption_mode` is `aes-128-ecb` (default).

## Configuration

```yaml
nowo_doctrine_encrypt:
    default_profile: default
    profiles:
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

## The `<ENC>` marker — when you need it (and when you must not add it)

This is the most common source of confusion with **MysqlAes**.

| How you insert | Column (demo) | `<ENC>` in DB? | How to read |
|----------------|---------------|----------------|-------------|
| **`$em->persist()`** + `#[Encrypted('mysql_aes')]` | `secret_orm` | **Yes** (added by subscriber) | Doctrine load, `\|decrypt('mysql_aes')`, `EncryptUtil` |
| **`EncryptUtil::encrypt($plain, 'mysql_aes')`** then save | `secret_orm` | **Yes** (util adds it) | Same as above |
| **`INSERT … AES_ENCRYPT(…)`** in SQL | `secret_native` (BLOB) | **No** | `AES_DECRYPT` in SQL, or `MysqlAesEncryptor::decrypt($blob)` in PHP |
| **`INSERT … CONCAT(AES_ENCRYPT(…), '<ENC>')`** (advanced) | `secret_orm` | **Yes** (you append in SQL) | Doctrine / `\|decrypt('mysql_aes')` — see below |

**Do not** append `<ENC>` to `secret_native` rows that you only read with `AES_DECRYPT`.  
**Do not** expect `AES_DECRYPT(secret_orm, …)` to work on Doctrine-managed columns (ciphertext differs and includes `<ENC>`).

### “I used `AES_ENCRYPT` and Doctrine / Twig does not decrypt”

That is **expected** if:

- You wrote to **`secret_orm`** without `<ENC>`, or
- You wrote to **`secret_native`** but load the entity with Doctrine (subscriber looks for `<ENC>` on `secret_orm` only), or
- You use `{{ value|decrypt }}` on a blob/hex string **without** the `<ENC>` suffix.

**Fix:** pick one read path per column (see table above). In the demo, use **`/mysql-aes-note/db-values`** to compare raw vs decrypted.

### Insert encrypted data **for Doctrine** (you do not build `<ENC>` yourself)

Let the bundle add the marker:

```php
// Option A — entity (recommended)
$note = new MysqlAesNote();
$note->setTitle('Invoice');
$note->setSecretOrm('my-secret'); // plaintext in PHP
$em->persist($note);
$em->flush(); // stored as: MysqlAes ciphertext + "<ENC>"

// Option B — EncryptUtil (e.g. before raw UPDATE)
use Nowo\DoctrineEncryptBundle\Util\EncryptUtil;

$stored = $encryptUtil->encrypt('my-secret', 'mysql_aes');
// $stored ends with "<ENC>" — safe to persist on an Encrypted property
```

Twig (value must be the **raw DB string** including `<ENC>`, e.g. from SQL export):

```twig
{{ row.secret_orm_raw|decrypt('mysql_aes') }}
```

### Insert with **`AES_ENCRYPT` in SQL** (no `<ENC>`)

Use a **separate BLOB column** (demo: `secret_native`):

```sql
INSERT INTO mysql_aes_note (title, secret_native)
VALUES ('SQL row', AES_ENCRYPT('my-secret', 'your_mysql_aes_passphrase'));
```

Read in SQL:

```sql
SELECT CAST(AES_DECRYPT(secret_native, 'your_mysql_aes_passphrase') AS CHAR) AS plain
FROM mysql_aes_note WHERE id = 1;
```

Read in PHP (no `<ENC>`, no Twig `|decrypt`):

```php
$plain = $encryptorRegistry->get('mysql_aes')->decrypt($binaryBlobFromDb);
```

### Advanced: SQL `INSERT` into an ORM column **with** `<ENC>` (MySQL only)

Only if you **must** insert in SQL but read via Doctrine/`#[Encrypted('mysql_aes')]` on the **same** column. The ciphertext bytes from MySQL must match PHP `MysqlAes` (same passphrase, default `block_encryption_mode`).

```sql
INSERT INTO mysql_aes_note (title, secret_orm)
VALUES (
    'From SQL for ORM',
    CONCAT(AES_ENCRYPT('my-secret', :passphrase), '<ENC>')
);
```

Prefer **VARBINARY/BLOB** for binary-safe storage; the demo uses `TEXT` for `secret_orm` (works for typical UTF-8 secrets but binary in `TEXT` can be charset-sensitive).

After insert, `$em->find(MysqlAesNote::class, $id)` should decrypt on load.  
**Do not** use this on `secret_native` if you also use `AES_DECRYPT` there — choose one format per column.

## Inserting encrypted data in MySQL (native SQL)

Use this when the **ciphertext must be produced by MySQL** (`AES_ENCRYPT`), e.g. legacy data, ETL, or another service writing directly to the DB. The plaintext is **not** stored; only the binary result of `AES_ENCRYPT` goes into the column.

### 1. Table and column type

Store ciphertext in **BLOB** or **VARBINARY** (not plain `TEXT`/`VARCHAR`):

```sql
CREATE TABLE my_secrets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    secret_native BLOB NULL
);
```

The **passphrase** is the same string you configure for `MysqlAes` (`MYSQL_AES_KEY` or key file content). MySQL pads it to 16 bytes internally (AES-128-ECB, default `block_encryption_mode`).

### 2. INSERT in SQL (mysql client)

Replace the passphrase with your env value (never commit real keys):

```sql
INSERT INTO my_secrets (title, secret_native)
VALUES (
    'Invoice ACME',
    AES_ENCRYPT('password-doctrine-acme', 'change_me_mysql_aes_passphrase')
);
```

Verify (decrypt in the same session):

```sql
SELECT id, title,
       CAST(AES_DECRYPT(secret_native, 'change_me_mysql_aes_passphrase') AS CHAR) AS secret_plain
FROM my_secrets;
```

Optional: inspect raw bytes as hex:

```sql
SELECT id, title, HEX(secret_native) AS secret_hex FROM my_secrets;
```

### 3. INSERT with bound parameters (Doctrine DBAL)

Recommended in PHP: bind **plaintext** and **passphrase**; MySQL performs encryption server-side.

```php
use Doctrine\DBAL\Connection;

final class MySecretRepository
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $mysqlAesKey, // inject %env(MYSQL_AES_KEY)%
    ) {
    }

    public function insertEncrypted(string $title, string $plaintext): int
    {
        $this->connection->executeStatement(
            'INSERT INTO my_secrets (title, secret_native)
             VALUES (:title, AES_ENCRYPT(:plain, :key))',
            [
                'title' => $title,
                'plain' => $plaintext,
                'key'   => $this->mysqlAesKey,
            ],
        );

        return (int) $this->connection->lastInsertId();
    }
}
```

Symfony `services.yaml`:

```yaml
services:
    App\Repository\MySecretRepository:
        arguments:
            $mysqlAesKey: '%env(MYSQL_AES_KEY)%'
```

The demo implements the same pattern in `MysqlAesNoteRepository::insertWithAesEncrypt()` (symfony7/symfony8, route `/mysql-aes-note/sql/new`).

### 4. UPDATE an existing row

```sql
UPDATE my_secrets
SET secret_native = AES_ENCRYPT('new-secret-value', :passphrase)
WHERE id = :id;
```

```php
$this->connection->executeStatement(
    'UPDATE my_secrets SET secret_native = AES_ENCRYPT(:plain, :key) WHERE id = :id',
    ['plain' => $newSecret, 'key' => $this->mysqlAesKey, 'id' => $id],
);
```

### 5. INSERT with multiple columns (plain + encrypted)

Keep searchable metadata in **plain** columns; encrypt only sensitive fields:

```sql
INSERT INTO my_secrets (title, secret_native)
VALUES (
    :title,
    AES_ENCRYPT(:secret, :key)
);
-- title = plain (LIKE / INDEX possible)
-- secret_native = binary ciphertext
```

### 6. Two paths — do not mix formats on the same column

| Path | How to insert | Column example | Stored format |
|------|----------------|----------------|---------------|
| **Doctrine + `#[Encrypted('mysql_aes')]`** | `$em->persist($entity); $em->flush();` | `secret_orm` TEXT | MysqlAes ciphertext + `<ENC>` marker |
| **Native MySQL** | `INSERT … AES_ENCRYPT(:plain, :key)` | `secret_native` BLOB | Raw MySQL AES blob, **no** `<ENC>` |
| **SQL for ORM (advanced)** | `INSERT … CONCAT(AES_ENCRYPT(…), '<ENC>')` | `secret_orm` | MySQL AES blob + `<ENC>` (see section above) |

`AES_DECRYPT` on a Doctrine `secret_orm` value will **not** return the original text.  
`{{ x|decrypt('mysql_aes') }}` does **nothing** unless `x` ends with `<ENC>`.

### 7. Same key as the bundle `MysqlAes` encryptor

The passphrase in SQL must match the config used by PHP:

```yaml
nowo_doctrine_encrypt:
    profiles:
        mysql_aes:
            encryptor_class: MysqlAes
            secret_key_env_var: '%env(MYSQL_AES_KEY)%'
```

Then rows inserted with `AES_ENCRYPT(..., :key)` and rows encrypted via `#[Encrypted('mysql_aes')]` use the **same algorithm**, but **different storage formats** unless you only use the native BLOB column for SQL inserts.

## Native SQL (repository / read)

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
