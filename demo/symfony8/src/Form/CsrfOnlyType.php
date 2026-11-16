<?php

declare(strict_types=1);

namespace App\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * REQ-DEMO-015: CSRF-only POST (demo row deletes), a FormKit form (profile `demo`). No fields: templates render the
 * CSRF child through the REQ-TWIG-003 child loop and keep their own submit button outside the loop (REQ-TWIG-005).
 *
 * @extends AbstractType<null>
 */
#[FormKitConfig('demo')]
final class CsrfOnlyType extends AbstractType
{
    use FormOptionsTrait;

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_token_id'   => 'demo',
            'csrf_field_name' => '_token',
        ]);
        $resolver->setAllowedTypes('csrf_token_id', 'string');
    }
}
