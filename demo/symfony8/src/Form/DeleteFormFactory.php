<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function sprintf;

/**
 * Builds the demo delete forms (REQ-TWIG-005): form name "delete_{id}", CSRF token id "delete{id}" (the id the
 * controllers always checked), POST to the entity delete route, confirm prompt on submit.
 */
final readonly class DeleteFormFactory
{
    public function __construct(
        private FormFactoryInterface $formFactory,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @return FormInterface<null>
     */
    public function create(string $route, int|string $id, string $confirm = 'Delete?', string $class = 'd-inline'): FormInterface
    {
        return $this->formFactory->createNamed('delete_' . $id, CsrfOnlyType::class, null, [
            'action'        => $this->urlGenerator->generate($route, ['id' => $id]),
            'csrf_token_id' => 'delete' . $id,
            'attr'          => [
                'class'    => $class,
                'onsubmit' => sprintf("return confirm('%s');", $confirm),
            ],
        ]);
    }

    /**
     * One form view per row id, for list templates.
     *
     * @param iterable<int|string> $ids
     *
     * @return array<int|string, FormView>
     */
    public function views(string $route, iterable $ids, string $confirm): array
    {
        $views = [];
        foreach ($ids as $id) {
            $views[$id] = $this->create($route, $id, $confirm)->createView();
        }

        return $views;
    }
}
