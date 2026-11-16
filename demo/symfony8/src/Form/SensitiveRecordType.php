<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\SensitiveRecord;
use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * REQ-DEMO-015: sensitive record form (one field per encryptor config), a FormKit form (profile `demo`).
 *
 * @extends AbstractType<SensitiveRecord>
 */
#[FormKitConfig('demo')]
final class SensitiveRecordType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addTextarea($builder, 'personalNote', [
            'required'           => false,
            'label'              => 'Personal note (Halite – personal_data, default path)',
            'translation_domain' => false,
            'placeholder'        => false,
            'help'               => 'Stored encrypted with Halite (config: personal_data).',
            'attr'               => ['rows' => 3, 'placeholder' => 'e.g. Medical or contact info'],
        ]);
        $this->addTextarea($builder, 'financialNote', [
            'required'           => false,
            'label'              => 'Financial note (encrypted with Defuse – financial_data, secret_key_filename)',
            'translation_domain' => false,
            'placeholder'        => false,
            'help'               => 'Stored encrypted with Defuse (config: financial_data).',
            'attr'               => ['rows' => 3, 'placeholder' => 'e.g. Account or payment info'],
        ]);
        $this->addTextarea($builder, 'envVarNote', [
            'required'           => false,
            'label'              => 'Env var note (encrypted with Halite – env_var, %env(APP_ENCRYPT_KEY)%)',
            'translation_domain' => false,
            'placeholder'        => false,
            'help'               => false,
            'attr'               => ['rows' => 3, 'placeholder' => 'e.g. Key from .env'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SensitiveRecord::class,
        ]);
    }
}
