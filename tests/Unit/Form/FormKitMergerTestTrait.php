<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Form;

use Nowo\FormKitBundle\Form\Constraint\ConstraintDefinitionFactory;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use PHPUnit\Framework\Assert;

use function sprintf;

/**
 * Shared FormKit profile stub for unit tests that build dashboard form types.
 */
trait FormKitMergerTestTrait
{
    protected function formKitMerger(): FormOptionsMerger
    {
        return new FormOptionsMerger(
            [
                'dashboard_menu' => [
                    'translation_domain' => 'NowoDashboardMenuBundle',
                    'defaults'           => [
                        'attr'     => ['class' => 'nowo-ui-input form-control'],
                        'row_attr' => ['class' => 'mb-1'],
                    ],
                    'field_types' => [
                        'checkbox' => [
                            'attr'     => ['class' => 'form-check-input'],
                            'row_attr' => ['class' => 'form-check mb-1'],
                        ],
                        'choice' => [
                            'attr' => ['class' => 'form-select'],
                        ],
                        'entity' => [
                            'attr' => ['class' => 'form-select'],
                        ],
                        'file' => [
                            'attr' => ['class' => 'nowo-ui-input form-control'],
                        ],
                        'textarea' => [
                            'attr' => ['class' => 'nowo-ui-input form-control'],
                        ],
                    ],
                ],
            ],
            'dashboard_menu',
            new ConstraintDefinitionFactory(),
        );
    }

    protected function injectFormKitMerger(object $type): void
    {
        if (!method_exists($type, 'setFormOptionsMerger')) {
            Assert::fail(sprintf('%s must use FormOptionsTrait.', $type::class));
        }
        $type->setFormOptionsMerger($this->formKitMerger());
    }
}
