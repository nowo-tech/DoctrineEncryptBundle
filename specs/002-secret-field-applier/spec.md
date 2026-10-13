# Feature Specification: "Leave blank to keep" secret fields

**Feature Branch**: `002-secret-field-applier`  
**Created**: 2026-10-13  
**Status**: Implemented (v2.8.0)  

**Package**: `nowo-tech/doctrine-encrypt-bundle`  
**Origin**: Symfony Beacon spec 137 (kit small extractions), FR-003 / KIT-SECRET-APPLIER  
**Code inventory**: [`../001-baseline/code-inventory.md`](../001-baseline/code-inventory.md)

---

## Summary

Hosts that edit encrypted secrets (API keys, webhook signing secrets, SMTP passwords) in settings forms all repeat the same pair of fields and the same controller logic: a password input that is never pre-filled, a blank submission that keeps the stored value, a new value that replaces it, and a "clear" checkbox that removes it. The bundle ships that logic (`SecretFieldApplier`) and an optional form type (`SecretType`) built on the existing `MaskUtil::secretHint()`.

---

## User Scenarios

### US-01 — Apply a submitted secret (P1)

**Given** a "clear" flag and a submitted plain value, **When** the host calls `SecretFieldApplier::apply($clear, $plain, $setter)`, **Then** clear calls `$setter(null)`, a non-empty plain calls `$setter($plain)`, and anything else makes no call.

### US-02 — One form field per secret (P2)

**Given** symfony/form is installed, **When** the host adds `SecretType` with `current_value`, **Then** the form renders a never pre-filled password input whose help (and placeholder) shows `•••• last4`, plus a "clear" checkbox when a value is stored, and the submitted data `['value' => ?string, 'clear' => bool]` can be passed straight to `SecretFieldApplier::applyData()`.

---

## Requirements

- **FR-FORM-001**: `Form\SecretFieldApplier::apply(bool $clear, ?string $plain, callable $setter): void` — clear wins and calls `$setter(null)`; otherwise a non-null, non-empty `$plain` calls `$setter($plain)`; otherwise no call. No dependency on symfony/form.
- **FR-FORM-002**: `Form\SecretFieldApplier::applyData(mixed $data, callable $setter): void` — reads keys `value` / `clear` (`KEY_VALUE`, `KEY_CLEAR`); non-array data, a missing or non-string `value` and a `clear` that is not exactly `true` keep the stored value.
- **FR-FORM-003**: `Form\Type\SecretType` (block prefix `nowo_secret`) — compound, `mapped: false`, `required: false`, `data_class: null`. Child `value` (`PasswordType`, `always_empty`, `trim`, `autocomplete=new-password`, help `secret.value.help_saved` with `%hint%` or `secret.value.help_empty`, placeholder = hint). Child `clear` (`CheckboxType`, `secret.clear.label`) added when `allow_clear` is `true`, or when it is `null` (default) and `current_value` is non-empty.
- **FR-FORM-004**: Options `current_value` (`?string`), `hint_visible_last` (`int`, default 4), `allow_clear` (`?bool`), `value_options` / `clear_options` (arrays merged recursively over the child defaults).
- **FR-FORM-005**: Default labels in translation domain `NowoDoctrineEncryptBundle`, locales `en` and `es`, same keys in every locale.
- **FR-FORM-006**: `symfony/form` is listed in `suggest` and `require-dev`, not `require`. `MaskUtil` is unchanged.

---

## Success Criteria

- **SC-001**: Every branch of `SecretFieldApplier` covered by unit tests; `SecretType` covered by a `TypeTestCase` (options, rendering never exposes the stored value, submit / clear / keep).
- **SC-002**: Line coverage gate unchanged; `make release-check` passes.
- **SC-003**: USAGE, CONFIGURATION, README, CHANGELOG, UPGRADING and RELEASE document the feature.

---

## Explicit non-goals

- Mapping the submitted array onto entity properties automatically (the host keeps calling the setter it chooses).
- A Twig form theme: the type renders with the host's theme (`nowo_secret` block prefix available for overrides).
- Integration with third-party password widgets (hosts pass their own type options through `value_options`, or keep their own type and use `SecretFieldApplier::apply()`).

---

## Validation

`make release-check` (cs, rector, PHPStan, Igor, PHPUnit with coverage, demos).
