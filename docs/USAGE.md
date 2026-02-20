# Usage

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

## Multiple encryptors

When you define [multiple configs](CONFIGURATION.md#multiple-encryptors-configs) (e.g. `personal_data`, `financial_data`), specify which config to use per property with the attribute’s config parameter:

```php
#[ORM\Column(type: 'string')]
#[Encrypted('personal_data')]
private ?string $email = null;

#[ORM\Column(type: 'string')]
#[Encrypted('financial_data')]
private ?string $iban = null;
```

Omit the parameter or use `#[Encrypted]` / `#[Encrypted('default')]` to use the default config.

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

If the value does not end with the encryption marker, the filter returns it unchanged. For a full example see the demo page “EncryptUtil & Twig” in `demo/symfony7` or `demo/symfony8` for a full example.

To **decrypt and then mask** for safe display (e.g. show only last 4 characters), chain the filters: `{{ value|decrypt('personal_data')|mask(4) }}`. The `|mask` filter is from **MaskExtension** (e.g. `|mask(4)` or `|mask(2, '••••')`).

## Embedded entities

Encrypted properties inside embedded entities are supported: mark the property in the embedded class with `Encrypted` and ensure the embeddable is correctly mapped.

## Console commands

Use the provided commands to inspect status, encrypt or decrypt the whole database, or generate a secret key. See [Commands](COMMANDS.md).

## Custom encryptor

To use a custom encryption class, see [custom_encryptor.md](custom_encryptor.md).
