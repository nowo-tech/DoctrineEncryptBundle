<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Form\Type;

use Nowo\DoctrineEncryptBundle\Form\Type\SecretType;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\Forms;
use Symfony\Component\Translation\IdentityTranslator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

use function dirname;

/**
 * SecretType is compound: without `error_bubbling: false` an error added to the field moved to the root form,
 * where many layouts do not render it.
 */
final class SecretTypeErrorRenderingTest extends TestCase
{
    public function testErrorAddedToTheSecretFieldStaysOnTheField(): void
    {
        $form = Forms::createFormFactory()->createBuilder(FormType::class)
            ->add('title', TextType::class)
            ->add('apiKey', SecretType::class)
            ->getForm();
        $form->submit(['title' => 'x', 'apiKey' => ['value' => 'bad']]);

        $form->get('apiKey')->addError(new FormError('The API key is invalid.'));

        self::assertFalse($form->get('apiKey')->getConfig()->getErrorBubbling());
        self::assertCount(1, $form->get('apiKey')->getErrors());
        self::assertCount(0, $form->getErrors(), 'not moved to the root form');

        $twig   = new Environment(new FilesystemLoader([dirname((string) (new ReflectionClass(FormExtension::class))->getFileName(), 2) . '/Resources/views/Form']), ['strict_variables' => true]);
        $engine = new TwigRendererEngine(['form_div_layout.html.twig'], $twig);
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([FormRenderer::class => static fn (): FormRenderer => new FormRenderer($engine)]));
        $twig->addExtension(new FormExtension());
        $twig->addExtension(new TranslationExtension(new IdentityTranslator()));
        $view = $form->createView();

        $rootErrors = $twig->createTemplate('{{ form_errors(form) }}')->render(['form' => $view]);
        $row        = $twig->createTemplate('{{ form_row(form.apiKey) }}')->render(['form' => $view]);

        self::assertStringNotContainsString('The API key is invalid.', $rootErrors);
        self::assertStringContainsString('The API key is invalid.', $row, 'rendered next to the secret field');
    }

    public function testErrorBubblingCanStillBeEnabled(): void
    {
        $form = Forms::createFormFactory()->createBuilder(FormType::class)
            ->add('apiKey', SecretType::class, ['error_bubbling' => true])
            ->getForm();
        $form->submit(['apiKey' => []]);

        $form->get('apiKey')->addError(new FormError('Bubbled.'));

        self::assertCount(1, $form->getErrors());
    }
}
