# Usage

## Table of contents

- [Marking properties as encrypted](#marking-properties-as-encrypted)
- [Multiple encryptors](#multiple-encryptors)
- [EncryptUtil (programmatic encrypt/decrypt)](#encryptutil-programmatic-encryptdecrypt)
- [Twig filter `|decrypt`](#twig-filter-decrypt)
- [Twig filter `|mask`](#twig-filter-mask)
- [MaskUtil (masking in PHP)](#maskutil-masking-in-php)
  - [Saved-secret hint](#saved-secret-hint-secrethint--secret_hint)
- ["Leave blank to keep" secret fields (`SecretType` / `SecretFieldApplier`)](#leave-blank-to-keep-secret-fields-secrettype--secretfieldapplier)
  - [SecretFieldApplier (no symfony/form needed)](#secretfieldapplier-no-symfonyform-needed)
  - [SecretType form field](#secrettype-form-field)
- [Searching and performance](#searching-and-performance)
- [Embedded entities](#embedded-entities)
- [Console commands](#console-commands)
- [Custom encryptor](#custom-encryptor)

## Marking properties as encrypted

Use the `Encrypted` attribute (PHP 8+) on any Doctrine entity property that should be encrypted at rest:

```php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;

#[ORM\Entity]
#[ORM\Table(name: 'user')]
class User
{
    // ...

    #[ORM\Column(type: 'string', name: 'email')]
    #[Encrypted]
    private ?string $email = null;

    // ...
}
```

The bundle will:

- **On persist/update:** Encrypt the value before it is written to the database.
- **On load:** Decrypt the value when the entity is loaded.

An `<ENC>` marker is appended to stored values so the bundle can tell encrypted from plain text; existing unencrypted data can still be read and will be encrypted on the next update.

**Column type:** Halite and Defuse produce ASCII ciphertext, so any `string` / `text` column works (size it for the ciphertext, which is longer than the plaintext). The deprecated **`MysqlAes`** profile produces **raw binary**: a `utf8mb4` `string` / `text` column rejects it (MySQL error 1366 *Incorrect string value*), so map the field as `binary` (`VARBINARY`) or `text` with `charset: binary`, or move to another profile. Details and the resulting schema diff: [MYSQL_AES.md › Column type](MYSQL_AES.md#column-type-for-orm-managed-fields-utf8mb4-limitation).

## Multiple encryptors

When you define [multiple profiles](CONFIGURATION.md#example-multiple-encryptors) (e.g. `personal_data`, `financial_data`), specify which profile to use per property with the attribute’s alias parameter:

```php
#[ORM\Column(type: 'string')]
#[Encrypted('personal_data')]
private ?string $email = null;

#[ORM\Column(type: 'string')]
#[Encrypted('financial_data')]
private ?string $iban = null;
```

Omit the parameter or use `#[Encrypted]` / `#[Encrypted('default')]` to use the default profile.

## EncryptUtil (programmatic encrypt/decrypt)

When you need to encrypt or decrypt values outside of entities (e.g. in a service, API response, or before passing to a template), inject `Nowo\DoctrineEncryptBundle\Util\EncryptUtil`:

```php
use Nowo\DoctrineEncryptBundle\Util\EncryptUtil;

class MyService
{
    public function __construct(private readonly EncryptUtil $encryptUtil) {}

    public function storeSensitive(string $value): string
    {
        return $this->encryptUtil->encrypt($value);  // default config
    }

    public function storeFinancial(string $iban): string
    {
        return $this->encryptUtil->encrypt($iban, 'financial_data');
    }

    public function readSensitive(?string $stored): ?string
    {
        return $this->encryptUtil->decrypt($stored);  // default config
    }
}
```

- **`encrypt(?string $value, ?string $config = null): ?string`** — Encrypts and appends the `<ENC>` marker. Use `$config = null` for the default config, or e.g. `'financial_data'` for a named config.
- **`decrypt(?string $value, ?string $config = null): ?string`** — Decrypts if the value ends with `<ENC>`, otherwise returns the value as-is.

The service is autowireable by type-hinting `EncryptUtil`.

## Twig filter `|decrypt`

In Twig templates you can decrypt a value that was encrypted (e.g. passed from the controller) using the **`decrypt`** filter. Use it when the value is still encrypted in the variable (e.g. from an API or stored ciphertext):

```twig
{{ encryptedValue|decrypt }}
```

With a **specific config** (when using multiple encryptors):

```twig
{{ encryptedValue|decrypt('financial_data') }}
```

If the value does not end with the encryption marker, the filter returns it unchanged. For a full example see the demo page “EncryptUtil & Twig” in `demo/symfony8`.

## Twig filter `|mask`

The bundle provides a **`mask`** Twig filter (**MaskExtension**) to mask sensitive values for display: it shows a replacement string (e.g. `****`) plus the last N characters. Useful after decrypting or for any plain value you do not want to show in full.

```twig
{{ value|mask }}
{{ value|mask(4) }}
{{ value|mask(2, '••••') }}
```

- **`|mask`** — same as `|mask(4)` (default: last 4 chars visible, prefix `****`).
- **`|mask(visibleLast)`** — number of characters to leave visible at the end (e.g. `4` → `****5678`).
- **`|mask(visibleLast, replacement)`** — custom replacement string (e.g. `|mask(2, '••••')`).

The output is **not** marked HTML-safe: Twig auto-escaping applies to both the visible tail and the replacement. Characters are counted multibyte-safe (UTF-8), so a tail is never cut in the middle of a character.

To **decrypt and then mask** in one go, chain the filters:

```twig
{{ value|decrypt('personal_data')|mask(4) }}
```

## MaskUtil (masking in PHP)

When you need to mask a value in PHP (e.g. in a service or API response), use **`Nowo\DoctrineEncryptBundle\Util\MaskUtil`**. The class exposes a static method and is also registered as a public service (`nowo_doctrine_encrypt.mask_util`):

```php
use Nowo\DoctrineEncryptBundle\Util\MaskUtil;

// Static call
$masked = MaskUtil::mask('12345678', 4);           // '****5678'
$masked = MaskUtil::mask('12345678', 2, '••••');  // '••••78'
```

- **`MaskUtil::mask(?string $value, ?int $visibleLast = 4, ?string $replacement = '****'): ?string`** — returns the masked string, or `null` if `$value` is null. If `$visibleLast` is 0 or the value length is less than or equal to `$visibleLast`, returns only `$replacement`.

You can inject the service by type-hinting `MaskUtil` (or the alias `nowo_doctrine_encrypt.mask_util`) where needed.

### Saved-secret hint (`secretHint` / `|secret_hint`)

For settings screens that store an API key, token or SMTP password: prove a value is saved **without sending it to the browser** (leave the password input empty and show a hint next to it).

```php
MaskUtil::secretHint('re_live_abcd1234');          // '•••• 1234'
MaskUtil::secretHint(null);                        // null  (nothing saved)
MaskUtil::secretHint('short');                     // '••••' (≤ 2 × visibleLast chars: nothing revealed)
MaskUtil::secretHint('re_live_abcd1234', 2, '***'); // '*** 34'
```

```twig
{{ settings.resendApiKey|secret_hint ?? 'Not set' }}
```

- **`MaskUtil::secretHint(?string $secret, int $visibleLast = 4, string $mask = '••••'): ?string`** — `null` for null/empty; multibyte-safe; secrets of at most `2 × $visibleLast` characters (or `$visibleLast <= 0`) return only the mask.

## "Leave blank to keep" secret fields (`SecretType` / `SecretFieldApplier`)

Settings screens that edit a stored secret (API key, webhook signing secret, SMTP password) usually follow the same rules: the input is **never pre-filled**, a **blank submission keeps** the stored value, a **new value replaces** it and an optional **"clear" checkbox removes** it. Since 2.8.0 the bundle ships both halves.

### SecretFieldApplier (no symfony/form needed)

```php
use Nowo\DoctrineEncryptBundle\Form\SecretFieldApplier;

SecretFieldApplier::apply(
    $form->get('clearApiKey')->getData() === true, // clear wins
    trim((string) $form->get('plainApiKey')->getData()),
    $settings->setResendApiKey(...),                // receives the new value, or null
);
```

| `$clear` | `$plain` | Result |
| --- | --- | --- |
| `true` | anything | `$setter(null)` |
| `false` | non-empty string | `$setter($plain)` |
| `false` | `null` or `''` | no call (stored value kept) |

`SecretFieldApplier::applyData(mixed $data, callable $setter)` does the same for the data of a `SecretType` field (`['value' => ?string, 'clear' => bool]`; keys also available as `SecretFieldApplier::KEY_VALUE` / `KEY_CLEAR`). A null or non-array payload, a missing or non-string `value` and a `clear` that is not exactly `true` keep the stored value.

### SecretType form field

Requires `symfony/form` (suggested dependency).

```php
use Nowo\DoctrineEncryptBundle\Form\Type\SecretType;
use Symfony\Component\Validator\Constraints\Regex;

$builder->add('apiKey', SecretType::class, [
    'label'         => 'resend_settings.api_key.label',
    'current_value' => $settings->getResendApiKey(), // plain stored value, only used for the hint
    'value_options' => ['constraints' => [new Regex('/^re_[A-Za-z0-9_-]+$/')]],
]);

// Controller, after $form->isSubmitted() && $form->isValid():
SecretFieldApplier::applyData($form->get('apiKey')->getData(), $settings->setResendApiKey(...));
```

What it renders:

- **`value`** — `PasswordType` with `always_empty: true`, `required: false`, `trim: true`, `autocomplete="new-password"`. With a stored value the help reads *"Saved: •••• 1234. Leave blank to keep it."* (`MaskUtil::secretHint()`, also used as placeholder); otherwise *"Not set."*.
- **`clear`** — `CheckboxType` *"Remove the saved value"*, added when a value is stored (or always/never, see `allow_clear`).

The field is **not mapped** by default (`mapped: false`), so the form component never writes the array into your entity; the stored plaintext is never sent to the browser (only the hint). Child labels and help use the `NowoDoctrineEncryptBundle` translation domain (English and Spanish shipped); override them with `value_options` / `clear_options`. Options are listed in [CONFIGURATION.md → SecretType options](CONFIGURATION.md#secrettype-form-options).

## Searching and performance

By design, values in `#[Encrypted]` columns are opaque at the database layer. You **cannot** run a meaningful `WHERE encrypted_column LIKE '%secret%'` on plaintext without decrypting every candidate row (in SQL or PHP).

| What you need | Practical approach | Performance |
|---------------|-------------------|-------------|
| Filter by **non-secret** fields | Plain columns + `LIKE` / indexes | **Good** |
| Filter by **secret** value on small sets | Load entities (decrypt on load) and filter in PHP | **Moderate**; degrades with table size |
| Filter by **secret** in MySQL (`MysqlAes` native column) | `CAST(AES_DECRYPT(...) AS CHAR) LIKE …` | **Poor** at scale (full scan + decrypt per row) |
| Full-text / search product requirements | Separate search index, hash/token column, or plain metadata | Design explicitly; not provided by this bundle |

The symfony8 demo (`/mysql-aes-note`, `/mysql-aes-note/sql`) lets you compare these behaviours side by side. See **[PERFORMANCE.md](PERFORMANCE.md)** for tables comparing Halite, Defuse, MysqlAes, and each `LIKE` variant.

## Embedded entities

Encrypted properties inside embedded entities are supported: mark the property in the embedded class with `Encrypted` and ensure the embeddable is correctly mapped.

## Console commands

Use the provided commands to inspect status, encrypt or decrypt the whole database, or generate a secret key. See [Commands](COMMANDS.md).

## Custom encryptor

To use a custom encryption class, see [custom_encryptor.md](custom_encryptor.md).
