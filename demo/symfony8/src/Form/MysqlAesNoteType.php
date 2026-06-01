<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\MysqlAesNote;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MysqlAesNoteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Title',
                'help' => 'Stored in plain text.',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('secretOrm', TextareaType::class, [
                'label' => 'Secret',
                'required' => false,
                'help' => 'Encrypted by the bundle (MysqlAes) in column <code>secret_orm</code>.',
                'help_html' => true,
                'attr' => ['rows' => 4, 'class' => 'form-control'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MysqlAesNote::class,
        ]);
    }
}
