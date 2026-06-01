<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Form for repository path (AES_ENCRYPT in SQL). */
class MysqlAesNativeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Title',
                'help'  => 'Stored in plain text.',
                'attr'  => ['class' => 'form-control'],
            ])
            ->add('secret', TextareaType::class, [
                'label'     => 'Secret',
                'help'      => 'Inserted with <code>AES_ENCRYPT</code> into <code>secret_native</code> (BLOB).',
                'help_html' => true,
                'attr'      => ['rows' => 4, 'class' => 'form-control'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
