<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\SecretMessage;
use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * REQ-DEMO-015: secret message form (Halite-encrypted message), a FormKit form (profile `demo`).
 *
 * @extends AbstractType<SecretMessage>
 */
#[FormKitConfig('demo')]
final class SecretMessageType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addText($builder, 'title', [
            'required'           => false,
            'label'              => 'Title (stored in plain text)',
            'translation_domain' => false,
            'placeholder'        => false,
            'help'               => false,
            'attr'               => ['placeholder' => 'e.g. My note'],
        ]);
        $this->addTextarea($builder, 'message', [
            'required'           => false,
            'label'              => 'Message (encrypted in database)',
            'translation_domain' => false,
            'placeholder'        => false,
            'help'               => 'This field is encrypted with Halite before being stored in the database.',
            'attr'               => ['rows' => 5, 'placeholder' => 'Sensitive content – stored encrypted with Halite'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SecretMessage::class,
        ]);
    }
}
