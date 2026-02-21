<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\SecretMessage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SecretMessageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'required' => false,
                'label'    => 'Title (stored in plain text)',
                'attr'     => ['placeholder' => 'e.g. My note'],
            ])
            ->add('message', TextareaType::class, [
                'required' => false,
                'label'    => 'Message (encrypted in database)',
                'attr'     => ['rows' => 5, 'placeholder' => 'Sensitive content – stored encrypted with Halite'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SecretMessage::class,
        ]);
    }
}
