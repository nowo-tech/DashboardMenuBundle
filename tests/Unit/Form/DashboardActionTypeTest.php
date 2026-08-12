<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Form;

use Nowo\DashboardMenuBundle\Form\DashboardActionType;
use Nowo\DashboardMenuBundle\NowoDashboardMenuBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class DashboardActionTypeTest extends TestCase
{
    public function testConfigureOptionsRequiresCsrfTokenId(): void
    {
        $type     = new DashboardActionType();
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $this->expectException(MissingOptionsException::class);
        $resolver->resolve([]);
    }

    public function testConfigureOptionsSetsRootLevelCsrfDefaults(): void
    {
        $type     = new DashboardActionType();
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve([
            'action'        => '/admin/menus/1/delete',
            'csrf_token_id' => 'delete_menu_1',
        ]);

        self::assertSame('_token', $options['csrf_field_name']);
        self::assertTrue($options['csrf_protection']);
        self::assertSame('POST', $options['method']);
        self::assertSame(NowoDashboardMenuBundle::TRANSLATION_DOMAIN, $options['translation_domain']);
        self::assertSame('', $type->getBlockPrefix());
    }
}
