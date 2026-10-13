<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Form\Type;

use Nowo\DoctrineEncryptBundle\Form\SecretFieldApplier;
use Nowo\DoctrineEncryptBundle\Util\MaskUtil;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function array_replace;
use function array_replace_recursive;

/**
 * "Leave blank to keep" secret field (API key, token, password stored encrypted).
 *
 * Compound type with two children:
 * - `value` — password input, never pre-filled (`always_empty`); help shows
 *   {@see MaskUtil::secretHint()} of `current_value` when a secret is stored.
 * - `clear` — optional checkbox to remove the stored secret (see `allow_clear`).
 *
 * Submitted data: `['value' => ?string, 'clear' => bool]`, ready for
 * {@see SecretFieldApplier::applyData()}. Not mapped by default (the stored secret is never
 * overwritten by the form component itself).
 *
 * Requires symfony/form (suggested dependency of the bundle).
 *
 * @extends AbstractType<array{value?: string|null, clear?: bool}>
 */
final class SecretType extends AbstractType
{
    public const TRANSLATION_DOMAIN = 'NowoDoctrineEncryptBundle';

    /**
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var string|null $currentValue */
        $currentValue = $options['current_value'];
        /** @var int $visibleLast */
        $visibleLast = $options['hint_visible_last'];
        $hint        = MaskUtil::secretHint($currentValue, $visibleLast);

        $valueDefaults = [
            'label'                       => 'secret.value.label',
            'required'                    => false,
            'always_empty'                => true,
            'trim'                        => true,
            'translation_domain'          => self::TRANSLATION_DOMAIN,
            'help'                        => $hint === null ? 'secret.value.help_empty' : 'secret.value.help_saved',
            'help_translation_parameters' => $hint === null ? [] : ['%hint%' => $hint],
            'attr'                        => array_replace(
                ['autocomplete' => 'new-password'],
                $hint === null ? [] : ['placeholder' => $hint],
            ),
        ];
        /** @var array<string, mixed> $valueOptions */
        $valueOptions = $options['value_options'];
        $builder->add(SecretFieldApplier::KEY_VALUE, PasswordType::class, array_replace_recursive($valueDefaults, $valueOptions));

        /** @var bool|null $allowClear */
        $allowClear = $options['allow_clear'];
        if ($allowClear ?? $hint !== null) {
            /** @var array<string, mixed> $clearOptions */
            $clearOptions = $options['clear_options'];
            $builder->add(SecretFieldApplier::KEY_CLEAR, CheckboxType::class, array_replace_recursive([
                'label'              => 'secret.clear.label',
                'required'           => false,
                'translation_domain' => self::TRANSLATION_DOMAIN,
            ], $clearOptions));
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'current_value'     => null,
            'hint_visible_last' => 4,
            'allow_clear'       => null,
            'value_options'     => [],
            'clear_options'     => [],
            'mapped'            => false,
            'required'          => false,
            'data_class'        => null,
            'empty_data'        => [],
        ]);
        $resolver->setAllowedTypes('current_value', ['null', 'string']);
        $resolver->setAllowedTypes('hint_visible_last', 'int');
        $resolver->setAllowedTypes('allow_clear', ['null', 'bool']);
        $resolver->setAllowedTypes('value_options', 'array');
        $resolver->setAllowedTypes('clear_options', 'array');
        $resolver->setInfo('current_value', 'Stored plain secret, only used to render the "•••• last4" hint (never sent to the browser).');
        $resolver->setInfo('allow_clear', 'Add the "clear" checkbox: null = only when a secret is stored, true = always, false = never.');
    }

    public function getBlockPrefix(): string
    {
        return 'nowo_secret';
    }
}
