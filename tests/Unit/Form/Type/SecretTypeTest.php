<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Form\Type;

use Nowo\DoctrineEncryptBundle\Form\SecretFieldApplier;
use Nowo\DoctrineEncryptBundle\Form\Type\SecretType;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\Yaml\Yaml;

use function dirname;
use function is_array;
use function sort;

final class SecretTypeTest extends TypeTestCase
{
    public function testWithoutStoredValueHasNoClearAndEmptyHelp(): void
    {
        $form = $this->factory->create(SecretType::class);

        self::assertTrue($form->has('value'));
        self::assertFalse($form->has('clear'));
        self::assertFalse($form->getConfig()->getMapped());
        self::assertFalse($form->isRequired());

        $value = $form->get('value')->getConfig();
        self::assertSame('secret.value.label', $value->getOption('label'));
        self::assertSame('secret.value.help_empty', $value->getOption('help'));
        self::assertSame([], $value->getOption('help_translation_parameters'));
        self::assertSame(['autocomplete' => 'new-password'], $value->getOption('attr'));
        self::assertTrue($value->getOption('always_empty'));
        self::assertTrue($value->getOption('trim'));
        self::assertFalse($value->getOption('required'));
        self::assertSame(SecretType::TRANSLATION_DOMAIN, $value->getOption('translation_domain'));
    }

    public function testWithStoredValueShowsHintAndClear(): void
    {
        $form = $this->factory->create(SecretType::class, null, ['current_value' => 're_live_abcd1234']);

        $value = $form->get('value')->getConfig();
        self::assertSame('secret.value.help_saved', $value->getOption('help'));
        self::assertSame(['%hint%' => '•••• 1234'], $value->getOption('help_translation_parameters'));
        self::assertSame(['autocomplete' => 'new-password', 'placeholder' => 'secret.value.placeholder_saved'], $value->getOption('attr'));

        self::assertTrue($form->has('clear'));
        $clear = $form->get('clear')->getConfig();
        self::assertSame('secret.clear.label', $clear->getOption('label'));
        self::assertSame(SecretType::TRANSLATION_DOMAIN, $clear->getOption('translation_domain'));
        self::assertFalse($clear->getOption('required'));
    }

    public function testStoredValueIsNeverRendered(): void
    {
        $form = $this->factory->create(SecretType::class, ['value' => 're_live_abcd1234'], ['current_value' => 're_live_abcd1234']);
        $view = $form->createView();

        self::assertSame('', $view['value']->vars['value']);
        self::assertSame('nowo_secret', $view->vars['block_prefixes'][1]);
    }

    public function testHintVisibleLast(): void
    {
        $form = $this->factory->create(SecretType::class, null, ['current_value' => 're_live_abcd1234', 'hint_visible_last' => 0]);

        self::assertSame(['%hint%' => '••••'], $form->get('value')->getConfig()->getOption('help_translation_parameters'));
    }

    public function testAllowClearForcedOnAndOff(): void
    {
        self::assertTrue($this->factory->create(SecretType::class, null, ['allow_clear' => true])->has('clear'));
        self::assertFalse($this->factory->create(SecretType::class, null, ['allow_clear' => false, 'current_value' => 'secret-value-1234'])->has('clear'));
    }

    public function testEmptyStoredValueCountsAsNotSet(): void
    {
        $form = $this->factory->create(SecretType::class, null, ['current_value' => '']);

        self::assertFalse($form->has('clear'));
        self::assertSame('secret.value.help_empty', $form->get('value')->getConfig()->getOption('help'));
    }

    public function testChildOptionsOverrideDefaults(): void
    {
        $form = $this->factory->create(SecretType::class, null, [
            'current_value' => 're_live_abcd1234',
            'value_options' => ['label' => 'API key', 'trim' => false, 'attr' => ['class' => 'wide']],
            'clear_options' => ['label' => 'Remove key', 'translation_domain' => 'form'],
        ]);

        $value = $form->get('value')->getConfig();
        self::assertSame('API key', $value->getOption('label'));
        self::assertFalse($value->getOption('trim'));
        self::assertSame(['autocomplete' => 'new-password', 'placeholder' => 'secret.value.placeholder_saved', 'class' => 'wide'], $value->getOption('attr'));
        self::assertSame('Remove key', $form->get('clear')->getConfig()->getOption('label'));
        self::assertSame('form', $form->get('clear')->getConfig()->getOption('translation_domain'));
    }

    public function testSubmitNewValue(): void
    {
        $form = $this->factory->create(SecretType::class, null, ['current_value' => 'old-secret-1234']);
        $form->submit(['value' => '  re_new_value  ']);

        self::assertTrue($form->isSynchronized());
        self::assertSame(['value' => 're_new_value', 'clear' => false], $form->getData());

        $stored = 'old-secret-1234';
        SecretFieldApplier::applyData($form->getData(), static function (?string $v) use (&$stored): void {
            $stored = $v;
        });
        self::assertSame('re_new_value', $stored);
    }

    public function testSubmitClear(): void
    {
        $form = $this->factory->create(SecretType::class, null, ['current_value' => 'old-secret-1234']);
        $form->submit(['value' => '', 'clear' => '1']);

        self::assertSame(['value' => null, 'clear' => true], $form->getData());

        $stored = 'old-secret-1234';
        SecretFieldApplier::applyData($form->getData(), static function (?string $v) use (&$stored): void {
            $stored = $v;
        });
        self::assertNull($stored);
    }

    public function testSubmitBlankKeeps(): void
    {
        $form = $this->factory->create(SecretType::class);
        $form->submit([]);

        self::assertSame(['value' => null], $form->getData());

        $calls = 0;
        SecretFieldApplier::applyData($form->getData(), static function () use (&$calls): void {
            ++$calls;
        });
        self::assertSame(0, $calls);
    }

    public function testInvalidOptionType(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->factory->create(SecretType::class, null, ['allow_clear' => 'yes']);
    }

    public function testTranslationsHaveSameKeysInEveryLocale(): void
    {
        $dir     = dirname(__DIR__, 4) . '/src/Resources/translations';
        $english = $this->flatten(Yaml::parseFile($dir . '/NowoDoctrineEncryptBundle.en.yaml'));
        $spanish = $this->flatten(Yaml::parseFile($dir . '/NowoDoctrineEncryptBundle.es.yaml'));

        $expected = ['secret.clear.label', 'secret.value.help_empty', 'secret.value.help_saved', 'secret.value.label', 'secret.value.placeholder_saved'];
        self::assertSame($expected, $english);
        self::assertSame($expected, $spanish);
    }

    /**
     * @return list<string>
     */
    private function flatten(mixed $tree, string $prefix = ''): array
    {
        $keys = [];
        foreach ((array) $tree as $key => $value) {
            if (is_array($value)) {
                foreach ($this->flatten($value, $prefix . $key . '.') as $k) {
                    $keys[] = $k;
                }
                continue;
            }
            $keys[] = $prefix . $key;
        }
        sort($keys);

        return $keys;
    }
}
