# FrankenPHP worker mode audit (kernel not reset between requests)

| Field | Value |
|-------|-------|
| Package | `nowo-tech/doctrine-encrypt-bundle` (`symfony-bundle`) |
| Audited revision | `v2.3.13` (this release) |
| Audit date | 2026-09-24 |
| Method | Manual review of every PHP file under `src/` (Doctrine listener, encryptors, registry, Twig filters, utils, attribute reader, DI extension, `Resources/config/services.yml`); console commands skimmed (CLI only) |
| **Verdict** | ✅ **Viable under scenario B** — the decryption cache lives only as long as the entities it describes (`WeakMap`), no longer uses plaintext as keys, the listener is registered once and is resettable, and a manager closed by a failed flush is reset at the start of the next main request. Clearing the identity map between requests remains the application's responsibility |
| Remediation (2026-09-24) | W-01, W-02 and W-03 resolved (W-03 confirmed in `demo/symfony8` with `debug:container --tag=doctrine.event_listener` before the fix); W-04 accepted. Regression tests in `tests/Unit/Subscribers/DoctrineEncryptSubscriberTest.php`, `tests/Unit/EventListener/ClosedEntityManagerRecoveryListenerTest.php`, `tests/Unit/DependencyInjection/DoctrineEncryptExtensionTest.php` |

## Execution model assumed

FrankenPHP worker mode boots the Symfony kernel once per worker and serves many requests with the same container. This audit assumes the **strict** variant: the kernel is **not** rebooted between requests, so every shared service, static property and PHP global survives from one request to the next. Two scenarios are evaluated:

- **A — kernel not rebooted, `services_resetter` still runs:** services tagged `kernel.reset` (or implementing `ResetInterface`) are reset between requests.
- **B — no reset at all:** nothing is reset; any per-request state kept in a service leaks into the next request.

A bundle that is safe under **B** is safe under **A** and under classic mode / PHP-FPM.

## Summary

| Area | Status | Notes |
|------|--------|-------|
| Mutable state in shared services | ✅ Resolved | `DoctrineEncryptSubscriber::$cachedDecryptions` is a `WeakMap<entity, …>` bounded by the live entities (W-01); encryptors memoize the key (harmless) |
| Static properties / `static` locals | ✅ | None; only pure static helpers (`MaskUtil::mask()`, `MysqlAesEncryptor::deriveKey()`) |
| `ResetInterface` / `kernel.reset` coverage | ✅ | The listener implements `ResetInterface` and is tagged `kernel.reset` (clears cache and encryptor override); scenario B does not depend on it |
| Request / user / locale captured in services | ✅ | None |
| Superglobals, `$_ENV`, `putenv`, `ini_set`, `setlocale`, timezone | ✅ | None; env keys come through container `%env()%` arguments |
| Doctrine / EntityManager | ✅ Resolved | A manager closed by a failed flush is reset by `ClosedEntityManagerRecoveryListener` at the next main request (W-02); the bundle never calls `clear()` on an open manager |
| Output, headers, `exit`, shutdown functions | ✅ | None in runtime code |
| Resources (files, sockets, cURL) held open | ✅ | Key files read once; the Halite env-key temp file is removed in `finally` |
| Memory growth across requests | ✅ Resolved | Cache entries are released with the entity objects (W-01) |
| Blocking I/O and timeouts | ✅ | Only local file reads for keys; crypto is CPU-bound |
| Third-party static state | ✅ | Halite / Defuse / OpenSSL used per call; no global state |
| PHPStan FrankenPHP rulesets | ✅ | `ruleset-classic.neon` + `ruleset-worker.neon` included in `phpstan.neon.dist` |

Worker demo: `demo/symfony8/docker/frankenphp/Caddyfile` declares a `worker` block (`file /app/public/index.php`, `watch`) and already advises limiting requests per worker in production.

## Services reviewed

| Service | Shared | Mutable state | Scenario A | Scenario B |
|---------|--------|---------------|------------|------------|
| `nowo_doctrine_encrypt.orm_subscriber` (`Subscribers\DoctrineEncryptSubscriber`) | yes | `$cachedDecryptions` (`WeakMap`, bounded by live entities), `$encryptCounter` / `$decryptCounter` (ints), `$encryptorOverride` / `$encryptorOverrideSet` (CLI only); `ResetInterface` | ✅ | ✅ |
| `Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber` | alias of `nowo_doctrine_encrypt.orm_subscriber` (no second instance) | — | ✅ | ✅ |
| `Nowo\DoctrineEncryptBundle\EventListener\ClosedEntityManagerRecoveryListener` | yes | none (optional `ManagerRegistry`) | ✅ | ✅ |
| `nowo_doctrine_encrypt.encryptor.<profile>` (`HaliteEncryptor`, `DefuseEncryptor`, `MysqlAesEncryptor`) | yes | lazily loaded key (`$encryptionKey` / `$derivedKey`), request-independent | ✅ | ✅ |
| `nowo_doctrine_encrypt.encryptor_registry` (`Encryptors\EncryptorRegistry`) | yes | map of encryptors set at compile time, never written | ✅ | ✅ |
| `nowo_doctrine_attribute_reader` (`Mapping\AttributeReader`) | yes | `$isRepeatableAttribute` cache keyed by attribute class (bounded) | ✅ | ✅ |
| `Twig\DecryptExtension`, `Twig\MaskExtension` | yes | none | ✅ | ✅ |
| `Util\EncryptUtil`, `Util\MaskUtil` | yes | none | ✅ | ✅ |
| 5 console commands (`Command\*`) | yes (CLI only) | not used in HTTP workers | N/A | N/A |

## Findings

### W-01 — Decryption cache keeps plaintext values and grows across requests (Medium)

**Status:** Resolved — `src/Subscribers/DoctrineEncryptSubscriber.php`: `$cachedDecryptions` is a `WeakMap<object, array<property, {plaintext, ciphertext}>>`, so an entry is released with its entity and the cache is bounded by the live identity map; plaintext is no longer an array key and `spl_object_id()` reuse can no longer match another object. Re-encryption restores the cached ciphertext only when the current value equals the decrypted one. `preFlush()` keeps its class-level behaviour. The class implements `ResetInterface` (`reset()` clears the cache and the encryptor override) and is tagged `kernel.reset`. Tests: `testDecryptionCacheDoesNotOutliveEntitiesAcrossRequestsWithoutReset`, `testDecryptionCacheDoesNotUsePlaintextAsKeys`, `testChangedValueIsEncryptedAgainInsteadOfRestoringCachedCiphertext`, `testResetClearsDecryptionCacheAndEncryptorOverride`. Plaintext still lives in the managed entities themselves for as long as the application keeps them in the identity map (application responsibility under scenario B).

- **Where:** `src/Subscribers/DoctrineEncryptSubscriber.php:84` (`private array $cachedDecryptions`); filled in `processFields()` on every decrypt at `:289` (`$this->cachedDecryptions[$entity::class][spl_object_id($entity)][$propertyName][$plaintext] = $ciphertext`), which runs from `postLoad()` (`:179-183`), `postUpdate()` (`:159-163`) and `postFlush()` (`:223-231`). It is only emptied in `preFlush()` (`:198`) or by `clearDecryptionCache()` (`:147-150`, used nowhere in HTTP code). The class does not implement `ResetInterface` and `src/Resources/config/services.yml:60-69` adds no `kernel.reset` tag.
- **Worker impact:** in a read-heavy worker (requests that load encrypted entities but never flush), every decrypted field of every loaded entity adds an entry that is never removed, under **both A and B** — the memory grows for the whole life of the worker. The array keys are the **decrypted plaintext** values (e.g. names, IBANs, phone numbers of every user served by that worker), so sensitive data stays in process memory long after the request ended. After a flush, `postFlush()` re-fills the cache with every entity still in the identity map, so it is never truly empty. The cache is keyed by `spl_object_id()`, which PHP reuses once an object is freed; with the current logic a reused id only maps a plaintext to a ciphertext of the **same** plaintext under the same encryptor, so no cross-user data corruption was found, but correctness depends on that coincidence (it would break, for example, if keys were rotated while workers keep running).
- **Recommendation:** make the listener implement `Symfony\Contracts\Service\ResetInterface` with `reset()` clearing `$cachedDecryptions` (and restoring the encryptor override), and/or clear the cache on `onClear`. Better, key the cache with a `WeakMap<object, …>` so entries disappear with the entity, and avoid using plaintext as array keys (store `[property => ciphertext]` and compare with `hash_equals` on re-encrypt). Until fixed, bound worker lifetime with FrankenPHP `max_requests` (or `FRANKENPHP_LOOP_MAX` in the runtime).

### W-02 — Failed flush leaves managed entities encrypted and the EntityManager closed (Medium)

**Status:** Resolved — new `src/EventListener/ClosedEntityManagerRecoveryListener.php` (registered in `services.yml` with `@?doctrine`) runs on `kernel.request` for the main request only (priority 4096) and calls `ManagerRegistry::resetManager()` for every manager that is closed, so a later request never reuses the closed manager and its identity map of re-encrypted entities. Open managers are not cleared. Within the request where the flush failed, entities keep ciphertext until the manager is discarded (unchanged behaviour, same as PHP-FPM). Tests: `ClosedEntityManagerRecoveryListenerTest`.

- **Where:** `preFlush()` re-encrypts every identity-map entity of cached classes (`src/Subscribers/DoctrineEncryptSubscriber.php:189-199`); decryption back to plaintext only happens in `postFlush()` (`:223-231`), which Doctrine does not dispatch when the flush throws.
- **Worker impact:** if the flush fails (constraint violation, deadlock, connection loss), managed entities keep `…<ENC>` ciphertext in their properties and the EntityManager is closed. Under **A**, Doctrine's `kernel.reset` clears/replaces the manager before the next request, so the next request reloads clean data. Under **B**, the same identity map is reused: later requests that get those already-managed entities (no new `postLoad`) see ciphertext instead of plaintext, and every write fails with "EntityManager is closed".
- **Recommendation:** keep `services_resetter` enabled (scenario A). For B support, decrypt identity-map entities again on failure (e.g. listen to `onClear` / wrap flush) or call `ManagerRegistry::resetManager()` after a flush exception.

### W-03 — The listener class appears to be registered twice (Low)

**Status:** Resolved — confirmed in `demo/symfony8` (DoctrineBundle installed): before the fix `debug:container --tag=doctrine.event_listener` listed both `Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber` and `nowo_doctrine_encrypt.orm_subscriber`. The `Subscribers\` resource import was replaced by an alias of the class id to `nowo_doctrine_encrypt.orm_subscriber` (`src/Resources/config/services.yml`); after the fix only `nowo_doctrine_encrypt.orm_subscriber` is listed. Side effect: console commands (which autowire the class id) now override the encryptor of the listener that actually runs. Test: `testSubscriberIsRegisteredOnceAndIsResettable`.

- **Where:** `src/Resources/config/services.yml:57-58` registers the whole `Subscribers\` directory with `autoconfigure: true`, and the class carries `#[AsDoctrineListener]` attributes (`src/Subscribers/DoctrineEncryptSubscriber.php:43-48`); `services.yml:60-69` registers the same class again as `nowo_doctrine_encrypt.orm_subscriber` with explicit `doctrine.event_listener` tags. Commands autowire the class-id service (`src/Command/AbstractCommand.php:39`).
- **Worker impact:** when DoctrineBundle autoconfigures `#[AsDoctrineListener]` (DoctrineBundle 2.8+), two independent listener instances handle the same events, each with its own `$cachedDecryptions` — doubling the W-01 memory growth. This could not be verified at runtime here (`doctrine/doctrine-bundle` is not installed in the package `vendor/`); it should be confirmed with `bin/console debug:event-dispatcher` / `debug:container --tag=doctrine.event_listener` in a host app.
- **Recommendation:** exclude `Subscribers/` from the resource import (or drop the attributes) so only one listener instance exists.

### W-04 — Encryption keys are cached for the worker lifetime; missing key files are generated at request time (Info)

**Status:** Accepted — keys are configuration, not per-request state; operational guidance below (provision keys before deploy, restart workers after rotation).

- **Where:** `src/Encryptors/HaliteEncryptor.php:70-93`, `src/Encryptors/DefuseEncryptor.php:62-83`, `src/Encryptors/MysqlAesEncryptor.php:88-95`.
- **Worker impact:** keys are loaded once per worker and reused (good, no per-request file I/O). After a key rotation, workers must be restarted or they keep using the old key. If the key file is missing, the first request generates and writes a new key (`HaliteEncryptor.php:84-86`, `DefuseEncryptor.php:75-78`); concurrent workers can race and write different keys. This is not specific to worker mode but is more visible with many threads.
- **Recommendation:** always provision keys before deploy (`doctrine:encrypt:generate-secret-key` or `secret_key_env_var`) and restart workers after `doctrine:encrypt:rotate-keys`.

`setEncryptor()` / `restoreEncryptor()` (`src/Subscribers/DoctrineEncryptSubscriber.php:119-142`) mutate the shared listener but are only called from console commands; HTTP code must never call them, because the override would persist for every later request in the worker (A and B).

## Usage recommendations in worker mode

- Scenario B is supported. Clearing the identity map between requests (`EntityManager::clear()`) remains the application's responsibility; decrypted entities kept in it keep their plaintext in memory.
- `services_resetter` (scenario A) remains recommended for the rest of the application.
- Do not call `DoctrineEncryptSubscriber::setEncryptor()` from controllers, listeners or Messenger handlers running in the worker.
- Restart workers after key rotation or key file changes.
- Custom encryptors must stay stateless apart from the loaded key (no per-request caches of plaintext).

## Re-audit triggers

Re-run this audit when a change touches `DoctrineEncryptSubscriber` state (`$cachedDecryptions`, counters, override), adds `ResetInterface` / `onClear` handling, changes the service registration in `services.yml`, adds new Doctrine listeners or encryptors with caches, or introduces use of `$_SERVER` / `$_ENV` at runtime.
