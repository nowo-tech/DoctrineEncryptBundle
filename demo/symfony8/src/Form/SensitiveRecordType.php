<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\SensitiveRecord;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SensitiveRecordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('personalNote', TextareaType::class, [
                'required' => false,
                'label' => 'Personal note (Halite – personal_data, default path)',
                'label_attr' => ['class' => 'form-label'],
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'e.g. Medical or contact info',
                ],
            ])
            ->add('financialNote', TextareaType::class, [
                'required' => false,
                'label' => 'Financial note (encrypted with Defuse – financial_data, secret_key_filename)',
                'label_attr' => ['class' => 'form-label'],
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'e.g. Account or payment info',
                ],
            ])
            ->add('envVarNote', TextareaType::class, [
                'required' => false,
                'label' => 'Env var note (encrypted with Halite – env_var, %env(APP_ENCRYPT_KEY)%)',
                'label_attr' => ['class' => 'form-label'],
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'e.g. Key from .env',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SensitiveRecord::class,
        ]);
    }
}
