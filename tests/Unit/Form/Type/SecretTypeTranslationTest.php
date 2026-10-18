<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Form\Type;

use Nowo\DoctrineEncryptBundle\Form\Type\SecretType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\Forms;
use Symfony\Component\Translation\DataCollectorTranslator;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

use function array_filter;
use function array_map;
use function array_values;
use function basename;
use function dirname;
use function explode;
use function glob;

/**
 * The stored-secret hint ("•••• 1234") is shown in the help and as the placeholder of the value input. The form
 * theme translates the placeholder in the field domain: the hint must not be looked up as a message (a "missing
 * message" in the profiler); it is a parameter of the `secret.value.placeholder_saved` message.
 */
final class SecretTypeTranslationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function locales(): iterable
    {
        yield 'en' => ['en', 'Saved: •••• 1234. Leave blank to keep it.'];
        yield 'es' => ['es', 'Guardado: •••• 1234. Déjalo en blanco para conservarlo.'];
    }

    #[DataProvider('locales')]
    public function testHintIsAParameterNotAMessage(string $locale, string $help): void
    {
        $translator = new Translator($locale);
        $translator->addLoader('yaml', new YamlFileLoader());
        foreach (glob(dirname(__DIR__, 4) . '/src/Resources/translations/*.yaml') ?: [] as $file) {
            [$domain, $fileLocale] = explode('.', basename($file));
            $translator->addResource('yaml', $file, $fileLocale, $domain);
        }
        $collector = new DataCollectorTranslator($translator);

        $twig   = new Environment(new FilesystemLoader([dirname((string) (new ReflectionClass(FormExtension::class))->getFileName(), 2) . '/Resources/views/Form']), ['strict_variables' => true]);
        $engine = new TwigRendererEngine(['form_div_layout.html.twig'], $twig);
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([FormRenderer::class => static fn (): FormRenderer => new FormRenderer($engine)]));
        $twig->addExtension(new FormExtension($collector));
        $twig->addExtension(new TranslationExtension($collector));

        $form = Forms::createFormFactory()->createBuilder(FormType::class)
            ->add('apiKey', SecretType::class, ['current_value' => 'sk_live_abcd1234', 'label' => false])
            ->getForm()
            ->createView();

        $html = $twig->createTemplate('{{ form_row(form.apiKey) }}')->render(['form' => $form]);

        self::assertStringContainsString('placeholder="•••• 1234"', $html);
        self::assertStringContainsString($help, $html);

        $missing = array_filter(
            $collector->getCollectedMessages(),
            static fn (array $message): bool => $message['state'] === DataCollectorTranslator::MESSAGE_MISSING,
        );
        self::assertSame([], array_values(array_map(static fn (array $message): string => $message['locale'] . ' ' . $message['domain'] . ': ' . $message['id'], $missing)));
    }
}
