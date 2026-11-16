<?php

declare(strict_types=1);

namespace App\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * REQ-DEMO-015: form for the repository path (AES_ENCRYPT in SQL), a FormKit form (profile `demo`).
 *
 * @extends AbstractType<array{title?: string|null, secret?: string|null}>
 */
#[FormKitConfig('demo')]
final class MysqlAesNativeType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addText($builder, 'title', [
            'label'              => 'Title',
            'translation_domain' => false,
            'placeholder'        => false,
            'help'               => 'Stored in plain text.',
        ]);
        $this->addTextarea($builder, 'secret', [
            'label'              => 'Secret',
            'translation_domain' => false,
            'placeholder'        => false,
            'help'               => 'Inserted with <code>AES_ENCRYPT</code> into <code>secret_native</code> (BLOB).',
            'help_html'          => true,
            'attr'               => ['rows' => 4],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
