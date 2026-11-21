<?php

declare(strict_types=1);

namespace App\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function array_flip;

/**
 * REQ-DEMO-015: GET list filter for the MysqlAes note lists, a FormKit form (profile `demo`): query names title /
 * secret / secret_mode stay unchanged for bookmarks, no CSRF token.
 *
 * @extends AbstractType<array{title?: string|null, secret?: string|null, secret_mode?: string|null}>
 */
#[FormKitConfig('demo')]
final class ListFilterType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addText($builder, 'title', [
            'label'              => 'Title',
            'translation_domain' => false,
            'required'           => false,
            'placeholder'        => false,
            'help'               => "Plain column · <code>LIKE '%…%'</code>",
            'help_html'          => true,
            'attr'               => ['placeholder' => 'e.g. invoice'],
        ]);
        $this->addText($builder, 'secret', [
            'label'              => 'Secret',
            'translation_domain' => false,
            'required'           => false,
            'placeholder'        => false,
            'help'               => false,
            'attr'               => ['placeholder' => 'e.g. password'],
        ]);
        $this->addChoice($builder, 'secret_mode', [
            'label'              => 'Secret search mode',
            'translation_domain' => false,
            'choices'            => array_flip($options['secret_mode_choices']),
            'placeholder'        => false,
            'help'               => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method'              => 'GET',
            'csrf_protection'     => false,
            'secret_mode_choices' => [],
        ]);
        $resolver->setAllowedTypes('secret_mode_choices', 'array');
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
