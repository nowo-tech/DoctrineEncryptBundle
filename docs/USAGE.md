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
  - [Translations](#translations)
- [Searching and performance](#searching-and-performance)
- [Embedded entities](#embedded-entities)
- [Using it from a DDD / hexagonal application](#using-it-from-a-ddd--hexagonal-application)
  - [Where the coupling is](#where-the-coupling-is)
  - [Selecting fields in configuration (listener)](#selecting-fields-in-configuration-listener)
  - [Encrypted DBAL types](#encrypted-dbal-types)
  - [Migrating a field from `#[Encrypted]` to a DBAL type](#migrating-a-field-from-encrypted-to-a-dbal-type)
  - [Recommended setups](#recommended-setups)
  - [Known limitations](#known-limitations)
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

The marker alone is never taken as proof that a value is encrypted (since 2.10.0): a value ending with `<ENC>` is only written as-is when it is ciphertext the listener produced, or when it actually decrypts with the property's profile. Any other value — including a plaintext that happens to end with `<ENC>` — is encrypted, and reads back exactly as it was assigned. When a stored value cannot be decrypted, [`on_decrypt_failure`](CONFIGURATION.md#on_decrypt_failure) decides what the property gets (`keep`, `null` or `throw`).

**Property access:** the listener reads and writes `#[Encrypted]` properties through Symfony PropertyAccess (getter / one-argument setter / public property) and, when a property is not reachable that way (private property without accessors, setter with more than one required argument), directly through reflection (since 2.10.0). A setter, when usable, still receives the ciphertext on flush, so keep setters of encrypted properties free of normalisation (trimming, lower-casing).

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
- **`decrypt(?string $value, ?string $config = null): ?string`** — Decrypts if the value ends with `<ENC>`, otherwise returns the value as-is (**lenient**: unmarked input is treated as plaintext).
- **`decryptStrict(?string $value, ?string $config = null): ?string`** (since 2.10.0) — For input that **must** be ciphertext (sealed exports, imports, values from another store). `null` and `''` are returned as-is; a value without the marker (or with an empty ciphertext) throws `ValueNotEncryptedException`; a value that does not decrypt throws `DecryptionFailedException`. Plaintext never passes through.

```php
use Nowo\DoctrineEncryptBundle\Exception\ExceptionInterface;

try {
    $token = $this->encryptUtil->decryptStrict($sealed, 'financial_data');
} catch (ExceptionInterface) {
    throw new \UnexpectedValueException('invalid sealed value');
}
```

Since 2.10.0 `encrypt('0')` encrypts `'0'` like any other value (it was returned in clear) and `decrypt('0')` returns `'0'` (it returned `''`).

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

- **`value`** — `PasswordType` with `always_empty: true`, `required: false`, `trim: true`, `autocomplete="new-password"`. With a stored value the help reads *"Saved: ••••. Leave blank to keep it."* (`MaskUtil::secretHint()` with `hint_visible_last`, default `0`, also used as placeholder; pass `'hint_visible_last' => 4` for *"Saved: •••• 1234. …"*); otherwise *"Not set."*.
- **`clear`** — `CheckboxType` *"Remove the saved value"*, added when a value is stored (or always/never, see `allow_clear`).

The field is **not mapped** by default (`mapped: false`), so the form component never writes the array into your entity; the stored plaintext is never sent to the browser (only the hint). Child labels and help use the `NowoDoctrineEncryptBundle` translation domain (see [Translations](#translations)); override them per field with `value_options` / `clear_options`. Options are listed in [CONFIGURATION.md → SecretType options](CONFIGURATION.md#secrettype-form-options).

### Translations

`SecretType` labels and help texts live in the `NowoDoctrineEncryptBundle` domain, shipped in `en`, `es`, `it`, `fr`, `pt`, `de` and `nl` (`src/Resources/translations/NowoDoctrineEncryptBundle.<locale>.yaml`). To override them application-wide:

1. Use the same domain, `NowoDoctrineEncryptBundle`.
2. Create `translations/NowoDoctrineEncryptBundle.<locale>.yaml` in your application.
3. Redefine only the keys you need; missing keys fall back to the bundle catalogue (the application translations take priority).

```yaml
# translations/NowoDoctrineEncryptBundle.en.yaml
secret:
    value:
        label: 'API key'
        help_empty: 'No API key stored yet.'
```

Available keys: `secret.value.label`, `secret.value.help_saved` (`%hint%`), `secret.value.help_empty`, `secret.value.placeholder_saved` (`%hint%`), `secret.clear.label`.

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

Encrypted properties inside embedded entities are supported: mark the property in the embedded class with `Encrypted` and ensure the embeddable is correctly mapped. The listener follows properties with `#[ORM\Embedded]`; for embeddables mapped in XML/YAML list the property under `embedded` in [`encrypted_fields`](#selecting-fields-in-configuration-listener), or map the embeddable's fields with an [encrypted DBAL type](#encrypted-dbal-types).

## Using it from a DDD / hexagonal application

DoctrineEncryptBundle is an infrastructure adapter. It has no entities or tables of its own and offers two ways to
encrypt a field:

- **The listener** (since 1.x, the default): a Doctrine ORM listener encrypts the selected properties when the entity
  manager flushes and decrypts them when entities are loaded. Fields are selected with `#[Encrypted]` **or**, since
  2.11, in the bundle configuration (`encrypted_fields`), so an attribute-free domain mapped in XML can use it.
- **Encrypted DBAL types** (since 2.11, opt-in with `dbal_types: true`): `encrypted_string`, `encrypted_text` and
  `encrypted_json`. Encryption happens when Doctrine converts the value for the database, so the entity only ever
  holds plaintext: no listener, no setter call, any mapping driver, embeddables and value objects included.

Both store the same `<ciphertext><ENC>` format with the same profile keys, so a column can move from one to the other
without migrating data.

### Where the coupling is

- With the listener, fields come from `#[Encrypted]` on the property or from `encrypted_fields`. Embeddables are
  followed when the property has `#[ORM\Embedded]` or is listed under `embedded` in `encrypted_fields`.
- Listener-encrypted properties hold strings (`?string`) and must be writable (not `readonly`): the listener puts the
  ciphertext into the entity during the flush and the plaintext back in `postFlush`. By default it goes through the
  property setter when one exists (`property_access: auto`); with `property_access: reflection` it never calls
  domain setters or getters.
- With the encrypted DBAL types nothing touches the entity: the type converts the column value.

### Selecting fields in configuration (listener)

```yaml
# config/packages/nowo_doctrine_encrypt.yaml
nowo_doctrine_encrypt:
    property_access: reflection            # domain setters never see ciphertext
    profiles:
        personal_data: { encryptor_class: Halite }
        financial_data: { encryptor_class: Halite }
    encrypted_fields:
        App\Domain\Customer\Customer:
            properties:
                email: ~                   # ~ = default_profile
                taxId: financial_data
            embedded: [address]            # follow this embedded value object
        App\Domain\Customer\Address:
            properties:
                street: ~
```

The same in XML (Symfony 7.4 deprecated XML configuration files; YAML and PHP take the same keys):

```xml
<nowo-doctrine-encrypt:config property-access="reflection">
    <nowo-doctrine-encrypt:encrypted-field class="App\Domain\Customer\Customer">
        <nowo-doctrine-encrypt:property name="email"/>
        <nowo-doctrine-encrypt:property name="taxId">financial_data</nowo-doctrine-encrypt:property>
        <nowo-doctrine-encrypt:embedded>address</nowo-doctrine-encrypt:embedded>
    </nowo-doctrine-encrypt:encrypted-field>
</nowo-doctrine-encrypt:config>
```

Entries of a parent class apply to its subclasses. What is checked:

- **Container build**: the class exists and is written in its exact case (not a trait, interface or enum), every
  property exists in the class or a parent, every profile exists, no property also carries `#[Encrypted]` or is listed
  both as a property and as embedded, and an `embedded` property is not declared with a scalar type.
- **When Doctrine loads the class's metadata** (first use, or `cache:warmup` with a metadata cache): every listed
  property is a mapped field of that class, every `embedded` entry is an embedded mapping, and no listener-selected
  field is also mapped with an encrypted DBAL type. A mismatch throws `LogicException`.
- **Not checked automatically**: a class that Doctrine does not map at all (for example the domain class when a
  separate persistence record is mapped). Its metadata never loads, so its entry is silently unused.
  `doctrine:encrypt:status` reports such entries in red; run it after changing `encrypted_fields`.

The console commands (`doctrine:encrypt:status`, `doctrine:encrypt:database`, `doctrine:decrypt:database`,
`doctrine:encrypt:rotate-keys`) see these fields like `#[Encrypted]` ones, inside followed embeddables too.

### Encrypted DBAL types

```yaml
nowo_doctrine_encrypt:
    dbal_types: true          # registers the types in DoctrineBundle (doctrine.dbal.types)
    on_decrypt_failure: throw
    profiles:
        personal_data: { encryptor_class: Halite }
        financial_data: { encryptor_class: Halite }
```

| Type | Column | PHP value |
|------|--------|-----------|
| `encrypted_string` | `VARCHAR` (`length` from the mapping) | `?string` (a `Stringable` is accepted on write) |
| `encrypted_text` | `TEXT` / `CLOB` | `?string` |
| `encrypted_json` | `TEXT` / `CLOB` (not a native JSON column: it stores ciphertext) | JSON-encodable value, decoded to arrays |

`encrypted_string` uses `default_profile`; `encrypted_string.financial_data` (and `encrypted_text.<profile>`,
`encrypted_json.<profile>`) uses that profile. One name per profile is registered.

```xml
<!-- config/doctrine/Customer.orm.xml: no bundle class in the domain -->
<entity name="App\Domain\Customer\Customer" table="customer">
    <field name="phone" type="encrypted_string" length="1024" nullable="true"/>
    <field name="iban" type="encrypted_iban" length="1024" nullable="true"/>
    <field name="preferences" type="encrypted_json" nullable="true"/>
    <embedded name="address" class="App\Domain\Customer\Address"/>
</entity>
<embeddable name="App\Domain\Customer\Address">
    <field name="street" type="encrypted_string.personal_data" length="1024" nullable="true"/>
</embeddable>
```

**Value objects**: extend a type in your infrastructure and override `toPlaintext()` / `fromPlaintext()` (and
`getProfile()` when the registered name has no `.<profile>` suffix):

```php
namespace App\Infrastructure\Doctrine;

use App\Domain\Customer\Iban;
use Nowo\DoctrineEncryptBundle\Doctrine\Types\EncryptedStringType;

final class EncryptedIbanType extends EncryptedStringType
{
    protected function toPlaintext(mixed $value): ?string
    {
        return $value instanceof Iban ? $value->toString() : parent::toPlaintext($value);
    }

    protected function fromPlaintext(string $plaintext): mixed
    {
        return Iban::fromString($plaintext);
    }

    public function getProfile(): string
    {
        return 'financial_data';
    }
}
```

```yaml
doctrine:
    dbal:
        types:
            encrypted_iban: App\Infrastructure\Doctrine\EncryptedIbanType
```

Behaviour, the same as the listener where it applies:

- `null` and `''` are stored as-is. A stored value without the `<ENC>` marker is returned as plaintext (rows written
  before encryption was enabled); it is encrypted the next time the field changes, or run `doctrine:encrypt:database`.
- A value that ends with `<ENC>` but does not decrypt follows `on_decrypt_failure`: `throw` =
  `DecryptionFailedException` (names the type and profile, never the value), `null` = `null` and a warning, `keep` =
  the ciphertext without the marker and a warning. When the PHP value cannot hold the ciphertext (`encrypted_json`, a
  value object type), `keep` throws `DecryptionFailedException` instead. The stored value is not rewritten unless the
  field changes.
- Doctrine only converts changed fields, so an unchanged field is never re-encrypted or rewritten.
- The raw SQL commands (`doctrine:encrypt:database`, `doctrine:decrypt:database`, `doctrine:encrypt:rotate-keys`) and
  `doctrine:encrypt:status` include columns mapped with an encrypted type (embedded ones too), with the type's profile.

**How the types get the encryptors.** DBAL types are stateless singletons that Doctrine instantiates itself, so they
cannot receive services. When the kernel boots, the bundle stores the encryptor registry, `on_decrypt_failure` and the
logger in a static holder, `Nowo\DoctrineEncryptBundle\Doctrine\Types\EncryptedTypeRuntime`, and clears it when that
kernel shuts down. Consequences:

- **Workers (FrankenPHP, RoadRunner, Messenger consumers)**: the kernel boots once per worker, the holder is set once
  and holds only immutable services (no request or user data), so nothing leaks between requests. A key change needs a
  worker restart, as with the listener.
- **Several kernels in one process** (some test suites, multi-kernel apps): the last booted kernel's encryptors are
  used by every connection of every kernel. Do not run kernels with different keys side by side in one process with
  the types; use the listener there.
- **Before boot / without the bundle** the types refuse to convert in both directions (`LogicException`, whatever
  `on_decrypt_failure` says): they never write plaintext and never return ciphertext as a value. The same holds for a
  type whose profile is not configured (`InvalidArgumentException`). In a
  plain Doctrine setup register the types with `Type::addType()` and call `EncryptedTypeRuntime::configure($registry,
  'throw', $logger)` yourself.
- **Querying by an encrypted value does not work**: Halite and Defuse ciphertexts are randomized, so `findBy(['phone'
  => '600…'])` or a DQL parameter typed `encrypted_string` encrypts the parameter again and never matches. The bundle
  has no blind index; keep a separate hash column (HMAC with its own key) if you need equality lookups.
- `MysqlAes` produces binary ciphertext; with the types, map such columns as you would for the listener (see
  [MYSQL_AES.md](MYSQL_AES.md)). Prefer Halite.
- A field cannot be selected for the listener (`#[Encrypted]` or `encrypted_fields`) and mapped with an encrypted type
  at the same time: it would be encrypted twice. This is rejected with `LogicException` when its metadata loads.
- **Bulk DQL / QueryBuilder updates must pass the type.** `UPDATE … SET c.phone = :v` with an untyped parameter, or a
  literal in `QueryBuilder::set()`, writes the **plaintext** (no Doctrine type conversion, and listeners do not run on
  bulk queries either). Bind the type explicitly, `->setParameter('v', $phone, 'encrypted_string.personal_data')`.
  Plaintext written that way is read back as-is (no marker), and `doctrine:encrypt:database` encrypts it. A DQL `WHERE`
  on such a column with an untyped parameter sends the plaintext to the database and its logs.
- **Second-level cache**: Doctrine caches the converted PHP values, so with an `#[ORM\Cache]` entity the plaintext of
  encrypted-type fields lands in the cache pool. Do not enable the second-level cache for such entities, or encrypt the
  pool.

### Migrating a field from `#[Encrypted]` to a DBAL type

The stored format and keys are the same, so no data migration is needed:

1. Enable `dbal_types: true` and deploy (the types are registered, nothing uses them yet).
2. Replace `#[ORM\Column(type: 'string', length: 1024)] #[Encrypted('financial_data')]` with
   `#[ORM\Column(type: 'encrypted_string.financial_data', length: 1024)]` (or `type="…"` in XML) and remove
   `#[Encrypted]` (or the `encrypted_fields` entry). Keep the column length.
3. Generate a migration: on DBAL 4 the column SQL is unchanged, so there is nothing to apply; on DBAL 3 only the column
   comment (type hint) changes.

To go back, do the reverse. Values written by the listener load through the type and the other way around.

### Recommended setups

1. **Attribute-free domain mapped in XML**: encrypted DBAL types for entity and embeddable fields and value objects,
   or `encrypted_fields` + `property_access: reflection` for the listener.
2. **Separate persistence model**: keep the domain free of Doctrine and map it to infrastructure records in your
   repository adapter; put `#[ORM\Column]` and `#[Encrypted]` (or an encrypted type) on the record only.
3. **Domain entities mapped with attributes**: `#[Encrypted]` is the same kind of metadata as `#[ORM\*]`. Set
   `property_access: reflection` if setters validate their input.
4. **Application-level encryption** (a payload to a queue, a file): depend on
   `Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface` (default profile) or `EncryptorRegistry::get('profile')`
   behind your own port:

```php
// Domain / application port
interface SecretCipher
{
    public function seal(string $plain): string;
    public function open(string $sealed): string;
}

// Infrastructure
final class NowoSecretCipher implements SecretCipher
{
    public function __construct(private readonly \Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistry $registry) {}

    public function seal(string $plain): string { return $this->registry->get('personal_data')->encrypt($plain); }
    public function open(string $sealed): string { return $this->registry->get('personal_data')->decrypt($sealed); }
}
```

- Your own key management or KMS plugs in as a custom `EncryptorInterface` set in
  `profiles.<name>.encryptor_class` (see [Custom encryptor](custom_encryptor.md)); the listener and the types both use
  it.

### Known limitations

- `encrypted_fields` entries for classes Doctrine does not map are not detected automatically (see
  `doctrine:encrypt:status`).
- No events are dispatched (decrypt failures are handled by `on_decrypt_failure` and logged).
- With the default `property_access: auto`, domain setters may receive ciphertext during a flush (unchanged from
  earlier versions); set `property_access: reflection` or use the DBAL types.

## Console commands

Use the provided commands to inspect status, encrypt or decrypt the whole database, or generate a secret key. See [Commands](COMMANDS.md).

## Custom encryptor

To use a custom encryption class, see [custom_encryptor.md](custom_encryptor.md).
