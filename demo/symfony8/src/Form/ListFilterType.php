<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function array_flip;

/**
 * GET list filter for the MysqlAes note lists (REQ-TWIG-005 rule 6): query names title / secret / secret_mode stay
 * unchanged for bookmarks, no CSRF token.
 *
 * @extends AbstractType<array{title?: string|null, secret?: string|null, secret_mode?: string|null}>
 */
final class ListFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label'     => 'Title',
                'required'  => false,
                'help'      => "Plain column · <code>LIKE '%…%'</code>",
                'help_html' => true,
                'row_attr'  => ['class' => 'col-lg-3 col-md-6'],
                'attr'      => ['placeholder' => 'e.g. invoice'],
            ])
            ->add('secret', TextType::class, [
                'label'    => 'Secret',
                'required' => false,
                'row_attr' => ['class' => 'col-lg-3 col-md-6'],
                'attr'     => ['placeholder' => 'e.g. password'],
            ])
            ->add('secret_mode', ChoiceType::class, [
                'label'    => 'Secret search mode',
                'choices'  => array_flip($options['secret_mode_choices']),
                'row_attr' => ['class' => 'col-lg-4 col-md-8'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method'              => 'GET',
            'csrf_protection'     => false,
            'secret_mode_choices' => [],
            'attr'                => ['class' => 'row g-3 align-items-start mb-4 border rounded p-3 bg-body-tertiary'],
        ]);
        $resolver->setAllowedTypes('secret_mode_choices', 'array');
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
