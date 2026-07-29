# Full example

This page shows a complete example: an entity with encrypted fields, fixtures, a controller, a Twig template, and what the encrypted data looks like in the database.

## Table of contents

- [Entity](#entity)
- [Fixtures](#fixtures)
- [Controller](#controller)
- [Template](#template)
- [What you see in the database](#what-you-see-in-the-database)
- [See also](#see-also)

## Entity

```php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;

#[ORM\Entity]
#[ORM\Table(name: 'user_v')]
class UserV
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'text', name: 'total_money')]
    #[Encrypted]
    private ?string $totalMoney = null;

    #[ORM\Column(type: 'string', length: 100, name: 'first_name')]
    private ?string $firstName = null;

    #[ORM\Column(type: 'string', length: 100, name: 'last_name')]
    private ?string $lastName = null;

    #[ORM\Column(type: 'text', name: 'credit_card_number')]
    #[Encrypted]
    private ?string $creditCardNumber = null;

    // getters and setters...
}
```

## Fixtures

```php
namespace App\DataFixtures;

use App\Entity\UserV;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class LoadUserData extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $user = new UserV();
        $user->setFirstName('Victor');
        $user->setLastName('Melnik');
        $user->setTotalMoney('20');
        $user->setCreditCardNumber('1234567890');

        $manager->persist($user);
        $manager->flush();
    }
}
```

## Controller

```php
namespace App\Controller;

use App\Entity\UserV;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DemoController extends AbstractController
{
    #[Route('/show-user/{id}', name: 'app_user_show', requirements: ['id' => '\d+'])]
    public function show(UserV $user): Response
    {
        return $this->render('demo/show_user.html.twig', ['user' => $user]);
    }
}
```

## Template

```twig
<div>Common info: {{ user.lastName ~ ' ' ~ user.firstName }}</div>
<div>
    Decoded info:
    <dl>
        <dt>Total money</dt>
        <dd>{{ user.totalMoney }}</dd>
        <dt>Credit card</dt>
        <dd>{{ user.creditCardNumber }}</dd>
    </dl>
</div>
```

When you open `/show-user/{id}`, the entity is loaded, encrypted fields are decrypted automatically, and you see plain values in the template. If you need to decrypt a value that is still encrypted in a variable (e.g. from an API), use the Twig filter: `{{ value|decrypt }}` or `{{ value|decrypt('financial_data') }}`. To mask sensitive data for display (e.g. show only the last 4 digits), use `{{ value|mask(4) }}` or chain `{{ value|decrypt|mask(4) }}`. See [Usage](USAGE.md) for **EncryptUtil**, **MaskUtil**, and the Twig filters (`|decrypt`, `|mask`). In the database, encrypted columns store ciphertext with an `<ENC>` suffix so the bundle can tell encrypted from plain text.

## What you see in the database

After persisting and flushing, encrypted columns look like this:

```
id                  | 1
total_money         | def50200100cd243434bc5fbbe5ecc87c153cda9d62e4c2f5ffb27c29b37df0cacd6d4a4b51408b3cefa950ea6b7ed22ab3b98344c8723f5ccee9c6d0aca8f48169c175bbdaba96d8c8106f1132ba5774954434a030df00771<ENC>
first_name          | Victor
last_name           | Melnik
credit_card_number  | def50200af8d084c22099d29b3940334de4c5c57df8517934dfd567e2d04f9a16a60e455690ab5e118ad007054845351df31a9d9370fdfac97ebdeb3e9589e3a1c094202e715c5c1607acb24667a1a3981e2fa626058a8d8<ENC>
```

Without the secret key file (e.g. `.Halite.key`), this data cannot be decrypted.

## See also

- [Usage](USAGE.md) — Encrypted attribute, EncryptUtil, MaskUtil, Twig filters (decrypt, mask), embedded entities
- [Commands](COMMANDS.md) — status, encrypt/decrypt database, generate key, rotate keys
- [Demo](DEMO.md) — runnable demo apps
