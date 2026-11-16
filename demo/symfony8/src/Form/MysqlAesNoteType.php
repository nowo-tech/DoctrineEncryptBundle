<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\MysqlAesNote;
use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * REQ-DEMO-015: Doctrine path form (MysqlAes encryptor), a FormKit form (profile `demo`).
 *
 * @extends AbstractType<MysqlAesNote>
 */
#[FormKitConfig('demo')]
final class MysqlAesNoteType extends AbstractType
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
        $this->addTextarea($builder, 'secretOrm', [
            'label'              => 'Secret',
            'translation_domain' => false,
            'required'           => false,
            'placeholder'        => false,
            'help'               => 'Encrypted by the bundle (MysqlAes) in column <code>secret_orm</code>.',
            'help_html'          => true,
            'attr'               => ['rows' => 4],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MysqlAesNote::class,
        ]);
    }
}
