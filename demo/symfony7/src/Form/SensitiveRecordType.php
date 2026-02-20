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
                'attr' => ['class' => 'form-control', 'rows' => 3],
            ])
            ->add('financialNote', TextareaType::class, [
                'required' => false,
                'label' => 'Financial note (Defuse – financial_data, secret_key_filename)',
                'attr' => ['class' => 'form-control', 'rows' => 3],
            ])
            ->add('envVarNote', TextareaType::class, [
                'required' => false,
                'label' => 'Env var note (Halite – env_var, %env(APP_ENCRYPT_KEY)%)',
                'attr' => ['class' => 'form-control', 'rows' => 3],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SensitiveRecord::class,
        ]);
    }
}
