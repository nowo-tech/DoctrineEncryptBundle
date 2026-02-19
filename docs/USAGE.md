# Usage

## Marking properties as encrypted

Use the `Encrypted` attribute (PHP 8+) or annotation on any Doctrine entity property that should be encrypted at rest:

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

With annotations (if you use doctrine/annotations):

```php
/**
 * @ORM\Entity
 * @ORM\Table(name="user")
 */
class User
{
    /**
     * @ORM\Column(type="string", name="email")
     * @Encrypted
     */
    private ?string $email = null;
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

## Embedded entities

Encrypted properties inside embedded entities are supported: mark the property in the embedded class with `Encrypted` and ensure the embeddable is correctly mapped.

## Console commands

Use the provided commands to inspect status, encrypt or decrypt the whole database, or generate a secret key. See [COMMANDS.md](COMMANDS.md).

## Custom encryptor

To use a custom encryption class, see [custom_encryptor.md](custom_encryptor.md).
