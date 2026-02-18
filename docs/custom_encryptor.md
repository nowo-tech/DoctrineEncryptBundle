# Custom encryptor

You can use a custom encryption class instead of Halite or Defuse.

## Implement the interface

Your class must implement `Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface`:

```php
namespace App\Encryptor;

use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;

class MyEncryptor implements EncryptorInterface
{
    public function __construct(string $secretKeyPath)
    {
        // Load or derive key from $secretKeyPath
    }

    public function encrypt(string $data): string
    {
        // Return encrypted string
    }

    public function decrypt(string $data): string
    {
        // Return decrypted string
    }
}
```

## Register and configure

1. Register your class as a service (e.g. in `config/services.yaml` or via service attribute).
2. In `config/packages/nowo_doctrine_encrypt.yaml`, set `encryptor_class` to your class FQCN:

```yaml
nowo_doctrine_encrypt:
    encryptor_class: App\Encryptor\MyEncryptor
    secret_directory_path: '%kernel.project_dir%'
```

The bundle will pass `secret_directory_path` (and the resolved key file path) to your encryptor’s constructor; the exact argument is the same as for the built-in encryptors (see `HaliteEncryptor` / `DefuseEncryptor` in the bundle source).

## Security

Prefer using well-audited libraries (e.g. Halite or Defuse). If you implement a custom encryptor, ensure you use a secure key derivation and authenticated encryption where appropriate.
